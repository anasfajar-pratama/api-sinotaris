<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentStage;
use App\Models\ActivityLog;
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

        // Create default stages
        $stages = [
            ['stage_number' => 1, 'stage_name' => 'Pembuatan Dokumen', 'status' => 'in_progress'],
            ['stage_number' => 2, 'stage_name' => 'Verifikasi', 'status' => 'pending'],
            ['stage_number' => 3, 'stage_name' => 'Proses', 'status' => 'pending'],
            ['stage_number' => 4, 'stage_name' => 'Review / Pemeriksaan', 'status' => 'pending'],
            ['stage_number' => 5, 'stage_name' => 'Selesai', 'status' => 'pending'],
        ];
        foreach ($stages as $stage) {
            DocumentStage::create(['document_id' => $document->id, ...$stage]);
        }

        ActivityLog::create([
            'user_id'   => $request->user()->id,
            'action'    => 'created',
            'module'    => 'document',
            'record_id' => $document->id,
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
        $document = Document::with(['documentType', 'client', 'creator', 'files', 'stages.handler', 'ajbCase'])
            ->findOrFail($id);

        return response()->json(['document' => $document]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        $oldData  = $document->toArray();

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
        $stages = DocumentStage::with('handler')
            ->where('document_id', $id)
            ->orderBy('stage_number')
            ->get();

        return response()->json(['stages' => $stages]);
    }

    public function updateStage(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'stage_number' => 'required|integer|min:1|max:5',
            'status'       => 'required|in:pending,in_progress,completed',
            'notes'        => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $stage = DocumentStage::where('document_id', $id)
            ->where('stage_number', $request->stage_number)
            ->firstOrFail();

        $stage->update([
            'status'       => $request->status,
            'notes'        => $request->notes,
            'handled_by'   => $request->user()->id,
            'completed_at' => $request->status === 'completed' ? now() : null,
        ]);

        // Update document status
        if ($request->stage_number == 5 && $request->status === 'completed') {
            $document->update(['status' => 'completed', 'current_stage' => 5]);
        } elseif ($request->status === 'in_progress') {
            $docStatus = match ($request->stage_number) {
                1 => 'draft',
                2, 3 => 'in_progress',
                4 => 'review',
                5 => 'completed',
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
        $path     = $file->store("documents/{$id}", 'public');

        $docFile = DocumentFile::create([
            'document_id'   => $id,
            'filename'      => basename($path),
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'type'          => $request->type,
            'size'          => $file->getSize(),
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
