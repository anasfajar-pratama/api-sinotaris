<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AjbCase;
use App\Models\AjbStep;
use App\Models\AjbSeller;
use App\Models\AjbBuyer;
use App\Models\AjbCertificate;
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
        $query = AjbCase::with(['document.client', 'sellers', 'buyers'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where('case_number', 'like', "%{$request->search}%"))
            ->when($request->source_type, fn ($q) => $q->where('source_type', $request->source_type))
            ->latest();

        return response()->json($query->paginate($request->per_page ?? 15));
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
            'sellers',
            'buyers',
            'certificates',
            'taxPayments',
            'documents',
            'steps.completedBy',
            'bpnSubmission',
        ])->findOrFail($id);

        return response()->json(['ajb_case' => $ajbCase]);
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
        $ajbCase = AjbCase::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255',
            'nik'            => 'required|string|max:20',
            'npwp'           => 'nullable|string|max:30',
            'address'        => 'required|string',
            'marital_status' => 'required|in:single,married,widowed',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $seller = AjbSeller::create(['ajb_case_id' => $id, ...$request->all()]);
        return response()->json(['message' => 'Data penjual berhasil ditambahkan', 'seller' => $seller], 201);
    }

    public function updateSeller(Request $request, int $id, int $sellerId): JsonResponse
    {
        $seller = AjbSeller::where('ajb_case_id', $id)->findOrFail($sellerId);
        $seller->update($request->all());
        return response()->json(['message' => 'Data penjual berhasil diperbarui', 'seller' => $seller->fresh()]);
    }

    public function addBuyer(Request $request, int $id): JsonResponse
    {
        $ajbCase = AjbCase::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'nik'     => 'required|string|max:20',
            'npwp'    => 'nullable|string|max:30',
            'address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $buyer = AjbBuyer::create(['ajb_case_id' => $id, ...$request->all()]);
        return response()->json(['message' => 'Data pembeli berhasil ditambahkan', 'buyer' => $buyer], 201);
    }

    public function updateBuyer(Request $request, int $id, int $buyerId): JsonResponse
    {
        $buyer = AjbBuyer::where('ajb_case_id', $id)->findOrFail($buyerId);
        $buyer->update($request->all());
        return response()->json(['message' => 'Data pembeli berhasil diperbarui', 'buyer' => $buyer->fresh()]);
    }

    public function addCertificate(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cert_number' => 'required|string',
            'cert_type'   => 'required|in:SHM,SHGB,SHSRS,girik,other',
            'land_area'   => 'required|numeric',
            'address'     => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $cert = AjbCertificate::create(['ajb_case_id' => $id, ...$request->all()]);
        return response()->json(['message' => 'Data sertifikat berhasil ditambahkan', 'certificate' => $cert], 201);
    }

    public function updateCertificate(Request $request, int $id, int $certId): JsonResponse
    {
        $cert = AjbCertificate::where('ajb_case_id', $id)->findOrFail($certId);
        $cert->update($request->all());

        // Auto-advance to step 2 if verified
        if ($request->has('verified_at') && $request->verified_at) {
            $cert->update(['verified_by' => $request->user()->id]);
        }

        return response()->json(['message' => 'Data sertifikat berhasil diperbarui', 'certificate' => $cert->fresh()]);
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
