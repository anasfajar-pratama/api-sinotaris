<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ActorType;
use App\Models\AssetType;
use App\Models\Document;
use App\Models\DocumentCatalog;
use App\Models\DocumentType;
use App\Models\OrderActor;
use App\Models\OrderActorDocument;
use App\Models\OrderAsset;
use App\Models\OrderAssetDocument;
use App\Models\OrderDocument;
use App\Support\FileConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Catat aktivitas perubahan data order (module=document, record_id=document_id)
     * agar halaman detail bisa menampilkan riwayat perubahan.
     */
    private function logActivity(Request $request, int $documentId, string $action, ?string $label, ?array $oldData = null, ?array $newData = null): void
    {
        ActivityLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => $action,
            'module'     => 'document',
            'record_id'  => $documentId,
            'description'=> $label,
            'old_data'   => $oldData !== null ? json_encode($oldData) : null,
            'new_data'   => $newData !== null ? json_encode($newData) : null,
            'ip_address' => $request->ip(),
        ]);
    }

    public function template(Request $request, int $documentTypeId): JsonResponse
    {
        $type = DocumentType::with([
            'actorDefinitions' => fn ($q) => $q->orderBy('sort_order'),
            'actorDefinitions.actorType',
            'actorDefinitions.fields.profileField',
            'actorDefinitions.documents.documentCatalog',
            'assetDefinitions' => fn ($q) => $q->orderBy('sort_order'),
            'assetDefinitions.assetType',
            'stages' => fn ($q) => $q->orderBy('stage_number'),
            'requiredDocuments.documentCatalog',
        ])->findOrFail($documentTypeId);

        $assetDocs = DocumentCatalog::whereIn('category', ['asset', 'supporting'])->orderBy('id')->get();

        $actors = $type->actorDefinitions->map(fn ($def) => [
            'id'          => $def->id,
            'actor_type'  => ['key' => $def->actorType->key, 'label' => $def->label_override ?: $def->actorType->label],
            'is_required' => $def->is_required,
            'fields'      => $def->fields->map(fn ($f) => [
                'key'        => $f->profileField->key,
                'label'      => $f->profileField->label,
                'data_type'  => $f->profileField->data_type,
                'options'    => $f->profileField->options,
                'is_required'=> $f->is_required,
            ]),
            'documents'   => $def->documents->map(fn ($d) => [
                'key'        => $d->documentCatalog->key,
                'label'      => $d->documentCatalog->label,
                'category'   => $d->documentCatalog->category,
                'is_required'=> $d->is_required,
            ]),
        ]);

        $assets = $type->assetDefinitions->map(fn ($def) => [
            'id'          => $def->id,
            'asset_type'  => ['key' => $def->assetType->key, 'label' => $def->assetType->label],
            'is_required' => $def->is_required,
            'documents'   => $assetDocs->map(fn ($d) => [
                'key'        => $d->key,
                'label'      => $d->label,
                'category'   => $d->category,
                'is_required'=> $d->key === 'sertifikat',
            ]),
        ]);

        return response()->json([
            'document_type' => $type,
            'actors'        => $actors,
            'assets'        => $assets,
        ]);
    }

    public function storeActor(Request $request, int $documentId): JsonResponse
    {
        $document = Document::findOrFail($documentId);

        $validator = Validator::make($request->all(), [
            'actor_type_key' => 'required|exists:actor_types,key',
            'data'           => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $actor = OrderActor::create([
            'document_id'   => $document->id,
            'actor_type_id' => ActorType::where('key', $request->actor_type_key)->value('id'),
            'data'          => $request->input('data', []),
            'sort_order'    => $document->actors()->count() + 1,
        ]);

        $this->logActivity(
            $request,
            $documentId,
            'created',
            'Pihak ditambahkan: ' . ($actor->actorType->label ?? $request->actor_type_key),
            null,
            ['entity' => 'actor', 'entity_id' => $actor->id, 'data' => $actor->data]
        );

        return response()->json(['message' => 'Aktor berhasil ditambahkan', 'actor' => $actor->load('actorType')], 201);
    }

    public function updateActor(Request $request, int $documentId, int $actorId): JsonResponse
    {
        $actor = OrderActor::where('document_id', $documentId)->findOrFail($actorId);
        $oldData = $actor->data;
        $data = $request->input('data');
        if ($data !== null) {
            // Frontend mengirim data penuh form, jadi ganti total (bukan merge)
            // agar field yang dikosongkan benar-benar terhapus.
            $actor->update(['data' => $data]);
        }

        $this->logActivity(
            $request,
            $documentId,
            'updated',
            'Data pihak diperbarui: ' . ($actor->actorType->label ?? "Pihak #{$actorId}"),
            ['entity' => 'actor', 'entity_id' => $actor->id, 'data' => $oldData],
            ['entity' => 'actor', 'entity_id' => $actor->id, 'data' => $actor->fresh()->data]
        );

        return response()->json(['message' => 'Aktor berhasil diperbarui', 'actor' => $actor->fresh()->load('actorType')]);
    }

    public function destroyActor(Request $request, int $documentId, int $actorId): JsonResponse
    {
        $actor = OrderActor::where('document_id', $documentId)->findOrFail($actorId);

        $this->logActivity(
            $request,
            $documentId,
            'deleted',
            'Pihak dihapus: ' . ($actor->actorType->label ?? "Pihak #{$actorId}"),
            ['entity' => 'actor', 'entity_id' => $actor->id, 'data' => $actor->data]
        );

        $actor->delete();
        return response()->json(['message' => 'Aktor berhasil dihapus']);
    }

    public function uploadActorDocument(Request $request, int $documentId, int $actorId): JsonResponse
    {
        $actor = OrderActor::where('document_id', $documentId)->findOrFail($actorId);

        $validator = Validator::make($request->all(), [
            'file'      => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'doc_key'   => 'nullable|exists:document_catalogs,key',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $path = FileConverter::store($file, "orders/{$documentId}/actors/{$actorId}");

        $doc = OrderActorDocument::create([
            'order_actor_id'     => $actor->id,
            'document_catalog_id'=> $request->doc_key ? DocumentCatalog::where('key', $request->doc_key)->value('id') : null,
            'filename'           => basename($path),
            'original_name'      => $file->getClientOriginalName(),
            'path'               => $path,
            'size'               => Storage::disk('public')->size($path),
            'uploaded_by'        => $request->user()->id,
        ]);

        $this->logActivity(
            $request,
            $documentId,
            'document_uploaded',
            'Berkas pihak diupload: ' . ($doc->documentCatalog->label ?? $file->getClientOriginalName()) . ' (' . ($actor->actorType->label ?? "Pihak #{$actorId}") . ')',
            null,
            ['entity' => 'actor', 'entity_id' => $actor->id, 'document_id' => $doc->id, 'doc_key' => $doc->document_catalog_id, 'original_name' => $doc->original_name]
        );

        return response()->json(['message' => 'Dokumen berhasil diupload', 'document' => $doc->load('documentCatalog')], 201);
    }

    public function destroyActorDocument(Request $request, int $documentId, int $actorId, int $fileId): JsonResponse
    {
        $doc = OrderActorDocument::where('order_actor_id', $actorId)->findOrFail($fileId);
        $actor = OrderActor::where('document_id', $documentId)->findOrFail($actorId);

        $this->logActivity(
            $request,
            $documentId,
            'document_deleted',
            'Berkas pihak dihapus: ' . ($doc->documentCatalog->label ?? $doc->original_name) . ' (' . ($actor->actorType->label ?? "Pihak #{$actorId}") . ')',
            ['entity' => 'actor', 'entity_id' => $actor->id, 'document_id' => $doc->id, 'original_name' => $doc->original_name]
        );

        Storage::disk('public')->delete($doc->path);
        $doc->delete();
        return response()->json(['message' => 'Dokumen berhasil dihapus']);
    }

    public function storeAsset(Request $request, int $documentId): JsonResponse
    {
        $document = Document::findOrFail($documentId);

        $validator = Validator::make($request->all(), [
            'asset_type_key' => 'required|exists:asset_types,key',
            'data'           => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $asset = OrderAsset::create([
            'document_id'   => $document->id,
            'asset_type_id' => AssetType::where('key', $request->asset_type_key)->value('id'),
            'data'          => $request->input('data', []),
            'sort_order'    => $document->assets()->count() + 1,
        ]);

        $this->logActivity(
            $request,
            $documentId,
            'created',
            'Aset ditambahkan: ' . ($asset->assetType->label ?? $request->asset_type_key),
            null,
            ['entity' => 'asset', 'entity_id' => $asset->id, 'data' => $asset->data]
        );

        return response()->json(['message' => 'Aset berhasil ditambahkan', 'asset' => $asset->load('assetType')], 201);
    }

    public function updateAsset(Request $request, int $documentId, int $assetId): JsonResponse
    {
        $asset = OrderAsset::where('document_id', $documentId)->findOrFail($assetId);
        $oldData = $asset->data;
        $data = $request->input('data');
        if ($data !== null) {
            // Frontend mengirim data penuh form, jadi ganti total (bukan merge)
            // agar field yang dikosongkan benar-benar terhapus.
            $asset->update(['data' => $data]);
        }

        $this->logActivity(
            $request,
            $documentId,
            'updated',
            'Data aset diperbarui: ' . ($asset->assetType->label ?? "Aset #{$assetId}"),
            ['entity' => 'asset', 'entity_id' => $asset->id, 'data' => $oldData],
            ['entity' => 'asset', 'entity_id' => $asset->id, 'data' => $asset->fresh()->data]
        );

        return response()->json(['message' => 'Aset berhasil diperbarui', 'asset' => $asset->fresh()->load('assetType')]);
    }

    public function destroyAsset(Request $request, int $documentId, int $assetId): JsonResponse
    {
        $asset = OrderAsset::where('document_id', $documentId)->findOrFail($assetId);

        $this->logActivity(
            $request,
            $documentId,
            'deleted',
            'Aset dihapus: ' . ($asset->assetType->label ?? "Aset #{$assetId}"),
            ['entity' => 'asset', 'entity_id' => $asset->id, 'data' => $asset->data]
        );

        $asset->delete();
        return response()->json(['message' => 'Aset berhasil dihapus']);
    }

    public function uploadAssetDocument(Request $request, int $documentId, int $assetId): JsonResponse
    {
        $asset = OrderAsset::where('document_id', $documentId)->findOrFail($assetId);

        $validator = Validator::make($request->all(), [
            'file'    => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'doc_key' => 'nullable|exists:document_catalogs,key',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $path = FileConverter::store($file, "orders/{$documentId}/assets/{$assetId}");

        $doc = OrderAssetDocument::create([
            'order_asset_id'     => $asset->id,
            'document_catalog_id'=> $request->doc_key ? DocumentCatalog::where('key', $request->doc_key)->value('id') : null,
            'filename'           => basename($path),
            'original_name'      => $file->getClientOriginalName(),
            'path'               => $path,
            'size'               => Storage::disk('public')->size($path),
            'uploaded_by'        => $request->user()->id,
        ]);

        $this->logActivity(
            $request,
            $documentId,
            'document_uploaded',
            'Berkas aset diupload: ' . ($doc->documentCatalog->label ?? $file->getClientOriginalName()) . ' (' . ($asset->assetType->label ?? "Aset #{$assetId}") . ')',
            null,
            ['entity' => 'asset', 'entity_id' => $asset->id, 'document_id' => $doc->id, 'doc_key' => $doc->document_catalog_id, 'original_name' => $doc->original_name]
        );

        return response()->json(['message' => 'Dokumen berhasil diupload', 'document' => $doc->load('documentCatalog')], 201);
    }

    public function destroyAssetDocument(Request $request, int $documentId, int $assetId, int $fileId): JsonResponse
    {
        $doc = OrderAssetDocument::where('order_asset_id', $assetId)->findOrFail($fileId);
        $asset = OrderAsset::where('document_id', $documentId)->findOrFail($assetId);

        $this->logActivity(
            $request,
            $documentId,
            'document_deleted',
            'Berkas aset dihapus: ' . ($doc->documentCatalog->label ?? $doc->original_name) . ' (' . ($asset->assetType->label ?? "Aset #{$assetId}") . ')',
            ['entity' => 'asset', 'entity_id' => $asset->id, 'document_id' => $doc->id, 'original_name' => $doc->original_name]
        );

        Storage::disk('public')->delete($doc->path);
        $doc->delete();
        return response()->json(['message' => 'Dokumen berhasil dihapus']);
    }

    public function storeOrderDocument(Request $request, int $documentId): JsonResponse
    {
        $document = Document::findOrFail($documentId);

        $validator = Validator::make($request->all(), [
            'file'    => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'doc_key' => 'nullable|exists:document_catalogs,key',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $path = FileConverter::store($file, "orders/{$documentId}/documents");

        $doc = OrderDocument::create([
            'document_id'         => $document->id,
            'document_catalog_id' => $request->doc_key ? DocumentCatalog::where('key', $request->doc_key)->value('id') : null,
            'filename'            => basename($path),
            'original_name'       => $file->getClientOriginalName(),
            'path'                => $path,
            'size'                => Storage::disk('public')->size($path),
            'uploaded_by'         => $request->user()->id,
        ]);

        $this->logActivity(
            $request,
            $documentId,
            'document_uploaded',
            'Berkas order diupload: ' . ($doc->documentCatalog->label ?? $file->getClientOriginalName()),
            null,
            ['entity' => 'order', 'document_id' => $doc->id, 'doc_key' => $doc->document_catalog_id, 'original_name' => $doc->original_name]
        );

        return response()->json(['message' => 'Dokumen berhasil diupload', 'document' => $doc->load('documentCatalog')], 201);
    }

    public function destroyOrderDocument(Request $request, int $documentId, int $fileId): JsonResponse
    {
        $doc = OrderDocument::where('document_id', $documentId)->findOrFail($fileId);

        $this->logActivity(
            $request,
            $documentId,
            'document_deleted',
            'Berkas order dihapus: ' . ($doc->documentCatalog->label ?? $doc->original_name),
            ['entity' => 'order', 'document_id' => $doc->id, 'original_name' => $doc->original_name]
        );

        Storage::disk('public')->delete($doc->path);
        $doc->delete();
        return response()->json(['message' => 'Dokumen berhasil dihapus']);
    }
}
