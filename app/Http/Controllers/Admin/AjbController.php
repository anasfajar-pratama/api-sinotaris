<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AjbCase;
use App\Models\AjbStep;
use App\Models\ActorType;
use App\Models\AssetType;
use App\Models\OrderActor;
use App\Models\OrderAsset;
use App\Models\AjbTaxPayment;
use App\Models\AjbDocument;
use App\Models\AjbBpnSubmission;
use App\Models\Document;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AjbController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AjbCase::with(['document.client', 'document.actors.actorType', 'document.assets.assetType'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where('case_number', 'like', "%{$request->search}%"))
            ->when($request->source_type, fn ($q) => $q->where('source_type', $request->source_type))
            ->latest();

        $cases = $query->paginate($request->per_page ?? 15);
        $cases->getCollection()->transform(fn ($case) => $this->withParties($case));

        return response()->json($cases);
    }

    private function withParties(AjbCase $case): AjbCase
    {
        $flatActor = fn ($actor) => array_merge(['id' => $actor->id, 'actor_type' => $actor->actorType], is_array($actor->data) ? $actor->data : []);
        $flatAsset = fn ($asset) => array_merge(['id' => $asset->id, 'asset_type' => $asset->assetType], is_array($asset->data) ? $asset->data : []);

        $case->sellers = $case->document?->actors
            ->where('actorType.key', 'penjual')->values()->map($flatActor);
        $case->buyers = $case->document?->actors
            ->where('actorType.key', 'pembeli')->values()->map($flatActor);
        $case->certificates = $case->document?->assets->values()->map($flatAsset);
        return $case;
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id'   => 'required|exists:clients,id',
            'source_type' => 'required|in:bank,notaris,walk_in',
            'notes'       => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Create parent document
            $ajbType = \App\Models\DocumentType::where('slug', 'ajb')->first();
            $document = Document::create([
                'doc_number'    => Document::generateDocNumber(),
                'tracking_code' => Document::generateTrackingCode(),
                'type_id'       => $ajbType?->id,
                'client_id'     => $request->client_id,
                'created_by'    => $request->user()->id,
                'title'         => 'Akta Jual Beli - ' . now()->format('d/m/Y'),
                'status'        => 'in_progress',
                'current_stage' => 1,
                'priority'      => 'normal',
            ]);

            // Create AJB case
            $ajbCase = AjbCase::create([
                'document_id' => $document->id,
                'case_number' => AjbCase::generateCaseNumber(),
                'source_type' => $request->source_type,
                'current_step' => 1,
                'status'       => 'active',
                'notes'        => $request->notes,
            ]);

            // Initialize 8 steps
            foreach (AjbCase::STEPS as $num => $name) {
                AjbStep::create([
                    'ajb_case_id'  => $ajbCase->id,
                    'step_number'  => $num,
                    'step_name'    => $name,
                    'status'       => $num === 1 ? 'in_progress' : 'pending',
                ]);
            }

            DB::commit();

            ActivityLog::create([
                'user_id'    => $request->user()->id,
                'action'     => 'created',
                'module'     => 'ajb',
                'record_id'  => $ajbCase->id,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message'  => 'Kasus AJB berhasil dibuat',
                'ajb_case' => $ajbCase->load(['document.client', 'sellers', 'buyers', 'steps']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal membuat kasus AJB: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ajbCase = AjbCase::with([
            'document.client',
            'document.actors.actorType',
            'document.assets.assetType',
            'document.stages',
            'taxPayments',
            'documents',
            'steps.completedBy',
            'bpnSubmission',
        ])->findOrFail($id);

        return response()->json(['ajb_case' => $this->withParties($ajbCase)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ajbCase = AjbCase::findOrFail($id);
        $ajbCase->update($request->only(['notes', 'status']));
        return response()->json(['message' => 'Kasus AJB berhasil diperbarui', 'ajb_case' => $ajbCase->fresh()]);
    }

    public function updateStep(Request $request, int $id, int $stepNumber): JsonResponse
    {
        $ajbCase = AjbCase::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,in_progress,completed',
            'notes'  => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $step = AjbStep::where('ajb_case_id', $id)
            ->where('step_number', $stepNumber)
            ->firstOrFail();

        $step->update([
            'status'       => $request->status,
            'notes'        => $request->notes,
            'completed_by' => $request->user()->id,
            'completed_at' => $request->status === 'completed' ? now() : null,
        ]);

        // Advance to next step
        if ($request->status === 'completed' && $stepNumber < 8) {
            $nextStep = AjbStep::where('ajb_case_id', $id)
                ->where('step_number', $stepNumber + 1)
                ->first();
            $nextStep?->update(['status' => 'in_progress']);
            $ajbCase->update(['current_step' => $stepNumber + 1]);
        } elseif ($request->status === 'completed' && $stepNumber == 8) {
            $ajbCase->update(['status' => 'completed', 'current_step' => 8]);
            $ajbCase->document->update(['status' => 'completed']);
        }

        return response()->json(['message' => 'Tahapan AJB berhasil diperbarui', 'step' => $step->fresh()]);
    }

    public function addSeller(Request $request, int $id): JsonResponse
    {
        return $this->addActor($request, $id, 'penjual', 'Data penjual berhasil ditambahkan');
    }

    public function updateSeller(Request $request, int $id, int $actorId): JsonResponse
    {
        return $this->updateActor($request, $id, $actorId, 'Data penjual berhasil diperbarui');
    }

    public function addBuyer(Request $request, int $id): JsonResponse
    {
        return $this->addActor($request, $id, 'pembeli', 'Data pembeli berhasil ditambahkan');
    }

    public function updateBuyer(Request $request, int $id, int $actorId): JsonResponse
    {
        return $this->updateActor($request, $id, $actorId, 'Data pembeli berhasil diperbarui');
    }

    private function addActor(Request $request, int $id, string $actorKey, string $message): JsonResponse
    {
        $ajbCase = AjbCase::findOrFail($id);
        $actorType = ActorType::where('key', $actorKey)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255',
            'nik'            => 'nullable|string|max:20',
            'npwp'           => 'nullable|string|max:30',
            'address'        => 'nullable|string',
            'phone'          => 'nullable|string|max:20',
            'marital_status' => 'nullable|in:single,married,widowed',
            'spouse_name'    => 'nullable|string|max:255',
            'spouse_nik'     => 'nullable|string|max:20',
            'data'           => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $data = array_merge($request->except(['data']), $request->input('data', []));
        $count = OrderActor::where('document_id', $ajbCase->document_id)->count();

        $actor = OrderActor::create([
            'document_id'   => $ajbCase->document_id,
            'actor_type_id' => $actorType->id,
            'data'          => $data,
            'sort_order'    => $count + 1,
        ]);

        return response()->json(['message' => $message, 'actor' => $actor->load('actorType')], 201);
    }

    private function updateActor(Request $request, int $id, int $actorId, string $message): JsonResponse
    {
        $ajbCase = AjbCase::findOrFail($id);
        $actor = OrderActor::where('document_id', $ajbCase->document_id)->findOrFail($actorId);

        $data = $request->has('data')
            ? array_merge(is_array($actor->data) ? $actor->data : [], $request->input('data'))
            : array_merge(is_array($actor->data) ? $actor->data : [], $request->except(['data']));

        $actor->update(['data' => $data]);
        return response()->json(['message' => $message, 'actor' => $actor->fresh()->load('actorType')]);
    }

    public function addCertificate(Request $request, int $id): JsonResponse
    {
        $ajbCase = AjbCase::findOrFail($id);
        $assetType = AssetType::where('key', 'sertifikat-tanah')->firstOrFail();

        $validator = Validator::make($request->all(), [
            'cert_number' => 'required|string',
            'cert_type'   => 'required|in:SHM,SHGB,SHSRS,girik,other',
            'land_area'   => 'required|numeric',
            'address'     => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $count = OrderAsset::where('document_id', $ajbCase->document_id)->count();
        $asset = OrderAsset::create([
            'document_id'   => $ajbCase->document_id,
            'asset_type_id' => $assetType->id,
            'data'          => $request->only(['cert_number', 'cert_type', 'land_area', 'address', 'notes']),
            'sort_order'    => $count + 1,
        ]);

        return response()->json(['message' => 'Data sertifikat berhasil ditambahkan', 'certificate' => $asset->load('assetType')], 201);
    }

    public function updateCertificate(Request $request, int $id, int $assetId): JsonResponse
    {
        $ajbCase = AjbCase::findOrFail($id);
        $asset = OrderAsset::where('document_id', $ajbCase->document_id)->findOrFail($assetId);

        $data = array_merge(is_array($asset->data) ? $asset->data : [], $request->only([
            'cert_number', 'cert_type', 'land_area', 'address', 'notes', 'verified_at',
        ]));
        if ($request->has('verified_at') && $request->verified_at) {
            $data['verified_by'] = $request->user()->id;
        }
        $asset->update(['data' => $data]);

        return response()->json(['message' => 'Data sertifikat berhasil diperbarui', 'certificate' => $asset->fresh()->load('assetType')]);
    }

    public function addTaxPayment(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type'           => 'required|in:bphtb,ssp,sps',
            'amount'         => 'required|numeric|min:0',
            'payment_date'   => 'required|date',
            'receipt_number' => 'nullable|string',
            'file'           => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store("ajb/{$id}/tax", 'public');
        }

        $payment = AjbTaxPayment::create([
            'ajb_case_id'    => $id,
            'type'           => $request->type,
            'amount'         => $request->amount,
            'payment_date'   => $request->payment_date,
            'receipt_number' => $request->receipt_number,
            'file_path'      => $filePath,
            'status'         => 'paid',
        ]);

        return response()->json(['message' => 'Pembayaran berhasil dicatat', 'payment' => $payment], 201);
    }

    public function updateTaxPayment(Request $request, int $id, int $paymentId): JsonResponse
    {
        $payment = AjbTaxPayment::where('ajb_case_id', $id)->findOrFail($paymentId);
        $payment->update($request->only(['status', 'notes']));
        return response()->json(['message' => 'Status pembayaran diperbarui', 'payment' => $payment->fresh()]);
    }

    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file'     => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'doc_type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $path = $file->store("ajb/{$id}/docs", 'public');

        $doc = AjbDocument::create([
            'ajb_case_id'  => $id,
            'doc_type'     => $request->doc_type,
            'filename'     => basename($path),
            'path'         => $path,
            'uploaded_by'  => $request->user()->id,
        ]);

        return response()->json(['message' => 'Dokumen berhasil diupload', 'document' => $doc], 201);
    }

    public function updateBpnSubmission(Request $request, int $id): JsonResponse
    {
        $ajbCase = AjbCase::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'spa_number'       => 'nullable|string',
            'submission_date'  => 'nullable|date',
            'sps_number'       => 'nullable|string',
            'sps_amount'       => 'nullable|numeric',
            'payment_date'     => 'nullable|date',
            'status'           => 'nullable|in:pending,submitted,processed,completed',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $submission = AjbBpnSubmission::updateOrCreate(
            ['ajb_case_id' => $id],
            $request->only(['spa_number', 'submission_date', 'sps_number', 'sps_amount', 'payment_date', 'status'])
        );

        return response()->json(['message' => 'Data BPN berhasil diperbarui', 'submission' => $submission]);
    }
}
