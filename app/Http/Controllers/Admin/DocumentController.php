<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentCatalog;
use App\Models\DocumentFile;
use App\Models\DocumentStage;
use App\Models\DocumentStagePic;
use App\Models\StageDocument;
use App\Models\DocumentType;
use App\Models\DocumentTypeActorDocument;
use App\Models\ActivityLog;
use App\Support\FileConverter;
use App\Notifications\DocumentStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Document::with(['documentType', 'client', 'creator'])
            ->when($request->search, fn ($q) => $q->where('title', 'like', "%{$request->search}%")
                ->orWhere('doc_number', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->type_id, fn ($q) => $q->where('type_id', $request->type_id))
            ->when($request->category, fn ($q) => $q->whereHas('documentType', fn ($t) => $t->where('category', $request->category)))
            ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
            ->when($request->priority, fn ($q) => $q->where('priority', $request->priority))
            ->when($request->date_from, fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest();

        $documents = $query->paginate($request->per_page ?? 15);

        return response()->json($documents);
    }

    public function store(Request $request): JsonResponse
    {
        // Hosting/shared-hosting kadang tidak menerapkan konversi '' -> null
        // (mis. saat request tiba sebagai multipart/form-data).
        // Normalisasi eksplisit agar field tanggal kosong tidak sampai ke MySQL.
        if ($request->filled('deadline') === false && $request->exists('deadline')) {
            $request->merge(['deadline' => null]);
        }

        $validator = Validator::make($request->all(), [
            'type_id'     => 'required|exists:document_types,id',
            'client_id'   => 'required|exists:clients,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority'    => 'required|in:low,normal,high,urgent',
            'deadline'    => 'nullable|date|after:today',
            'notes'       => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $document = Document::create([
            ...$request->only(['type_id', 'client_id', 'title', 'description', 'priority', 'deadline', 'notes']),
            'doc_number'    => Document::generateDocNumber(),
            'tracking_code' => Document::generateTrackingCode(),
            'created_by'    => $request->user()->id,
            'status'        => 'draft',
            'current_stage' => 1,
        ]);

        // Create stages from document type template (fallback: default 5)
        $defaultStages = [
            'Pembuatan Dokumen',
            'Verifikasi',
            'Proses',
            'Review / Pemeriksaan',
            'Selesai',
        ];
        $type = DocumentType::find($document->type_id);
        $stageNames = $type?->stages?->pluck('stage_name')->all() ?: $defaultStages;
        $stageSla = $type?->stages?->pluck('sla_days')->all() ?? [];

        foreach ($stageNames as $i => $stageName) {
            DocumentStage::create([
                'document_id'   => $document->id,
                'stage_number'  => $i + 1,
                'stage_name'    => $stageName,
                'sla_days'      => $stageSla[$i] ?? null,
                'status'        => $i === 0 ? 'in_progress' : 'pending',
                'started_at'    => $i === 0 ? now() : null,
            ]);
        }

        ActivityLog::create([
            'user_id'   => $request->user()->id,
            'action'    => 'created',
            'module'    => 'document',
            'record_id' => $document->id,
            'description' => 'Order dibuat: ' . $document->title,
            'new_data'  => json_encode($document->toArray()),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message'  => 'Dokumen berhasil dibuat',
            'document' => $document->load(['documentType', 'client', 'stages']),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $document = Document::with([
            'documentType.requiredDocuments.documentCatalog',
            'documentType.stages',
            'client',
            'creator',
            'files',
            'stages.handler',
            'stages.pic',
            'stages.documents',
            'stages.picHistory',
            'ajbCase',
            'actors.actorType',
            'actors.documents.documentCatalog',
            'assets.assetType',
            'assets.documents.documentCatalog',
            'orderDocuments.documentCatalog',
        ])->findOrFail($id);

        $completeness = $this->completeness($document);

        return response()->json(['document' => $document, 'completeness' => $completeness]);
    }

    private function completeness(Document $document): array
    {
        $items = [];

        // Dokumen wajib level order (wajib)
        // Upload di card Aset (OrderAssetDocument) juga dianggap memenuhi butir
        // yang sama — mis. IMB/SLF/SPPT diunggah lewat aset tetap melengkapi order.
        $document->documentType?->requiredDocuments->each(function ($req) use (&$items, $document) {
            $catalog = $req->documentCatalog;
            $uploadedInOrder = $document->orderDocuments->contains(fn ($d) => $d->document_catalog_id === $req->document_catalog_id);
            $uploadedInAssets = $document->assets->contains(
                fn ($asset) => $asset->documents->contains(fn ($d) => $d->document_catalog_id === $req->document_catalog_id)
            );
            $items[] = [
                'group'     => 'Order',
                'key'       => $catalog?->key,
                'label'     => $catalog?->label ?? 'Dokumen',
                'required'  => true,
                'uploaded'  => $uploadedInOrder || $uploadedInAssets,
            ];
        });

        // Dokumen wajib per pihak (tampil sesuai aktor)
        $requiredDocs = DocumentTypeActorDocument::with('documentCatalog')
            ->where('document_type_id', $document->type_id)
            ->where('is_required', true)
            ->orderBy('sort_order')
            ->get();

        $spouseCatalogs = DocumentCatalog::whereIn('key', ['ktp_pasangan', 'npwp_pasangan'])->get()->keyBy('key');

        foreach ($document->actors as $actor) {
            $label = $actor->actorType?->label ?? 'Pihak';

            foreach ($requiredDocs->where('actor_type_id', $actor->actor_type_id) as $req) {
                $catalog = $req->documentCatalog;
                $items[] = [
                    'group'     => $label,
                    'key'       => $catalog?->key,
                    'label'     => $catalog?->label ?? 'Dokumen',
                    'required'  => true,
                    'uploaded'  => $actor->documents->contains(fn ($d) => $d->document_catalog_id === $req->document_catalog_id),
                ];
            }

            // Dokumen pasangan (opsional) hanya jika status perkawinan = married
            if (($actor->data['marital_status'] ?? null) === 'married') {
                foreach (['ktp_pasangan' => 'KTP Pasangan', 'npwp_pasangan' => 'NPWP Pasangan'] as $key => $spLabel) {
                    $catalog = $spouseCatalogs->get($key);
                    $items[] = [
                        'group'     => $label,
                        'key'       => $key,
                        'label'     => $spLabel,
                        'required'  => false,
                        'uploaded'  => $actor->documents->contains(fn ($d) => $d->document_catalog_id === $catalog?->id),
                    ];
                }
            }
        }

        $requiredItems = collect($items)->where('required', true);
        $requiredCount = $requiredItems->count();
        $uploadedCount = $requiredItems->where('uploaded', true)->count();

        return [
            'items'          => $items,
            'total_required' => $requiredCount,
            'total_uploaded' => $uploadedCount,
            'percentage'     => $requiredCount ? (int) round($uploadedCount / $requiredCount * 100) : 100,
        ];
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $oldData  = $document->toArray();

        if ($request->filled('deadline') === false && $request->exists('deadline')) {
            $request->merge(['deadline' => null]);
        }

        $validator = Validator::make($request->all(), [
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority'    => 'sometimes|in:low,normal,high,urgent',
            'deadline'    => 'nullable|date',
            'notes'       => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $document->update($request->only(['title', 'description', 'priority', 'deadline', 'notes']));

        ActivityLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'updated',
            'module'     => 'document',
            'record_id'  => $document->id,
            'description' => 'Data order diperbarui: ' . ($document->fresh()->title ?? ''),
            'old_data'   => json_encode($oldData),
            'new_data'   => json_encode($document->fresh()->toArray()),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Dokumen berhasil diperbarui', 'document' => $document->fresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $document->delete();

        ActivityLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'deleted',
            'module'     => 'document',
            'record_id'  => $id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Dokumen berhasil dihapus']);
    }

    public function timeline(Request $request, int $id): JsonResponse
    {
        $stages = DocumentStage::with(['handler', 'pic', 'documents', 'picHistory'])
            ->where('document_id', $id)
            ->orderBy('stage_number')
            ->get();

        return response()->json(['stages' => $stages]);
    }

    /**
     * Riwayat aktivitas / perubahan data order (termasuk log dari OrderController
     * yang memakai module=document + record_id=id order).
     */
    public function activity(Request $request, int $id): JsonResponse
    {
        Document::findOrFail($id);

        $logs = ActivityLog::with('user')
            ->where('module', 'document')
            ->where('record_id', $id)
            ->latest()
            ->paginate($request->per_page ?? 30);

        return response()->json($logs);
    }

    public function updateStage(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        $lastStage = $document->stages()->max('stage_number') ?? 5;
        $validator = Validator::make($request->all(), [
            'stage_number' => "required|integer|min:1|max:{$lastStage}",
            'status'       => 'required|in:pending,in_progress,completed',
            'notes'        => 'nullable|string',
            'pic_id'       => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $stage = DocumentStage::where('document_id', $id)
            ->where('stage_number', $request->stage_number)
            ->firstOrFail();

        if ($stage->status === 'completed' && $request->filled('pic_id') && $request->pic_id != $stage->pic_id) {
            return response()->json(['message' => 'PIC tahapan tidak dapat diubah setelah tahap selesai'], 422);
        }

        $oldPicId = $stage->pic_id;
        // pic_id null (tidak dipilih) = tidak ada perubahan PIC.
        $picChanged = $request->filled('pic_id') && $request->pic_id != $oldPicId;
        $newPicId = $picChanged ? (int) $request->pic_id : $oldPicId;

        $updates = [
            'status'     => $request->status,
            'notes'      => $request->notes,
            'handled_by' => $request->user()->id,
        ];

        if ($picChanged) {
            $updates['pic_id'] = $newPicId;
        }

        if ($request->status === 'in_progress' && $stage->status !== 'in_progress') {
            $updates['started_at'] = now();
        }

        if ($request->status === 'completed' && $stage->status !== 'completed') {
            $updates['completed_at'] = now();
        } elseif ($request->status !== 'completed') {
            $updates['completed_at'] = null;
        }

        $stage->update($updates);

        // Catat riwayat PIC (pindah tugas / penugasan) hanya saat PIC benar-benar berubah.
        if ($picChanged) {
            DocumentStagePic::create([
                'stage_id'    => $stage->id,
                'user_id'     => $newPicId,
                'assigned_by' => $request->user()->id,
                'action'      => $oldPicId === null ? 'assigned' : 'transferred',
                'note'        => $request->notes,
                'assigned_at' => now(),
            ]);
        }

        // Catat aktivitas perubahan tahapan (mulai/selesai/pindah PIC/catatan).
        $actionLabel = match (true) {
            $request->status === 'completed' => 'Tahap "' . $stage->stage_name . '" ditandai selesai',
            $request->status === 'in_progress' && $stage->wasChanged('started_at') && !$picChanged
                => 'Tahap "' . $stage->stage_name . '" dimulai',
            $picChanged && $oldPicId !== null => 'PIC tahap "' . $stage->stage_name . '" dipindah ke ' . ($stage->fresh()->pic?->name ?? "user #{$newPicId}"),
            $picChanged                       => 'PIC tahap "' . $stage->stage_name . '" ditugaskan ke ' . ($stage->fresh()->pic?->name ?? "user #{$newPicId}"),
            default                           => 'Catatan/PIC tahap "' . $stage->stage_name . '" diperbarui',
        };
        ActivityLog::create([
            'user_id'    => $request->user()->id,
            'action'     => $request->status === 'completed' ? 'stage_completed' : ($request->status === 'in_progress' && $stage->wasChanged('started_at') && !$picChanged ? 'stage_started' : 'stage_updated'),
            'module'     => 'document',
            'record_id'  => $document->id,
            'description'=> $actionLabel,
            'ip_address' => $request->ip(),
        ]);

        // Update document status (dynamic based on stage template)
        if ($request->stage_number == $lastStage && $request->status === 'completed') {
            $document->update(['status' => 'completed', 'current_stage' => $lastStage]);
        } elseif ($request->status === 'in_progress') {
            $docStatus = match ($request->stage_number) {
                1 => 'draft',
                $lastStage => 'review',
                default => 'in_progress',
            };
            $document->update(['status' => $docStatus, 'current_stage' => $request->stage_number]);
        }

        // Notify client
        if ($document->client?->user) {
            $document->client->user->notify(new DocumentStatusUpdated($document, $stage));
        }

        return response()->json(['message' => 'Status tahapan berhasil diperbarui', 'stage' => $stage->fresh()]);
    }

    public function addNote(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $document->update(['notes' => $document->notes . "\n\n[" . now()->format('d/m/Y H:i') . " - " . $request->user()->name . "]\n" . $request->note]);

        return response()->json(['message' => 'Catatan berhasil ditambahkan']);
    }

    public function uploadFile(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $document = Document::findOrFail($id);
        $file     = $request->file('file');
        $path     = FileConverter::store($file, "documents/{$id}");

        $docFile = DocumentFile::create([
            'document_id'   => $id,
            'filename'      => basename($path),
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'type'          => $request->type,
            'size'          => Storage::disk('public')->size($path),
            'uploaded_by'   => $request->user()->id,
        ]);

        return response()->json(['message' => 'File berhasil diupload', 'file' => $docFile], 201);
    }

    public function deleteFile(Request $request, int $id, int $fileId): JsonResponse
    {
        $file = DocumentFile::where('document_id', $id)->findOrFail($fileId);
        Storage::disk('public')->delete($file->path);
        $file->delete();

        return response()->json(['message' => 'File berhasil dihapus']);
    }

    public function uploadStageDocument(Request $request, int $id, int $stageId): JsonResponse
    {
        $document = Document::findOrFail($id);
        $stage = DocumentStage::where('document_id', $id)->findOrFail($stageId);

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $path = FileConverter::store($file, "documents/{$id}/stages/{$stageId}");

        $doc = StageDocument::create([
            'stage_id'      => $stageId,
            'filename'      => basename($path),
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'size'          => Storage::disk('public')->size($path),
            'uploaded_by'   => $request->user()->id,
        ]);

        ActivityLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'document_uploaded',
            'module'     => 'document',
            'record_id'  => $id,
            'description'=> 'Berkas pendukung tahap "' . $stage->stage_name . '" diupload: ' . $file->getClientOriginalName(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Berkas berhasil diupload', 'document' => $doc], 201);
    }

    public function deleteStageDocument(Request $request, int $id, int $stageId, int $fileId): JsonResponse
    {
        $stage = DocumentStage::where('document_id', $id)->findOrFail($stageId);
        $file = StageDocument::where('stage_id', $stageId)->findOrFail($fileId);
        Storage::disk('public')->delete($file->path);
        $file->delete();

        ActivityLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'document_deleted',
            'module'     => 'document',
            'record_id'  => $id,
            'description'=> 'Berkas pendukung tahap "' . $stage->stage_name . '" dihapus: ' . $file->original_name,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Berkas dihapus']);
    }

    public function publicTrack(Request $request, string $code): JsonResponse
    {
        $document = Document::with(['documentType', 'stages'])
            ->where('tracking_code', strtoupper($code))
            ->first();

        if (!$document) {
            return response()->json(['message' => 'Dokumen tidak ditemukan. Periksa kembali kode tracking Anda.'], 404);
        }

        return response()->json([
            'doc_number'   => $document->doc_number,
            'title'        => $document->title,
            'type'         => $document->documentType?->name,
            'status'       => $document->status,
            'current_stage' => $document->current_stage,
            'stages'       => $document->stages->map(fn ($s) => [
                'stage_number' => $s->stage_number,
                'stage_name'   => $s->stage_name,
                'status'       => $s->status,
                'completed_at' => $s->completed_at,
            ]),
            'created_at'   => $document->created_at,
            'deadline'     => $document->deadline,
        ]);
    }
}
