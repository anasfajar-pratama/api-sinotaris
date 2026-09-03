<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetType;
use App\Models\ActorType;
use App\Models\DocumentCatalog;
use App\Models\DocumentType;
use App\Models\DocumentTypeActor;
use App\Models\DocumentTypeActorDocument;
use App\Models\DocumentTypeActorField;
use App\Models\DocumentTypeAsset;
use App\Models\DocumentTypeStage;
use App\Models\ProfileField;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = DB::table('system_settings')->get();
        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $settings = $request->input('settings', []);
        foreach ($settings as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }
        return response()->json(['message' => 'Pengaturan berhasil disimpan']);
    }

    public function documentTypes(): JsonResponse
    {
        $types = DocumentType::all();
        return response()->json(['types' => $types]);
    }

    public function createDocumentType(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'slug'     => 'required|string|unique:document_types,slug',
            'sla_days' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $type = DocumentType::create($request->only(['name', 'slug', 'description', 'sla_days', 'is_active']));
        return response()->json(['message' => 'Jenis dokumen berhasil ditambahkan', 'type' => $type], 201);
    }

    public function updateDocumentType(Request $request, int $id): JsonResponse
    {
        $type = DocumentType::findOrFail($id);
        $type->update($request->only(['name', 'description', 'sla_days', 'is_active']));
        return response()->json(['message' => 'Jenis dokumen berhasil diperbarui', 'type' => $type->fresh()]);
    }

    public function orderMapping(): JsonResponse
    {
        $types = DocumentType::with([
            'actorDefinitions' => fn ($q) => $q->orderBy('sort_order'),
            'actorDefinitions.actorType',
            'actorDefinitions.fields.profileField',
            'actorDefinitions.documents.documentCatalog',
            'assetDefinitions' => fn ($q) => $q->orderBy('sort_order'),
            'assetDefinitions.assetType',
            'stages',
        ])->get();

        return response()->json([
            'types'            => $types,
            'actor_types'      => ActorType::orderBy('label')->get(),
            'profile_fields'   => ProfileField::orderBy('label')->get(),
            'document_catalog' => DocumentCatalog::orderBy('label')->get(),
            'asset_types'      => AssetType::orderBy('label')->get(),
        ]);
    }

    public function syncOrderMapping(Request $request): JsonResponse
    {
        $typeId = $request->input('type_id');
        $type = DocumentType::findOrFail($typeId);

        $validator = Validator::make($request->all(), [
            'type_id' => 'required|integer|exists:document_types,id',
            'actors' => 'array',
            'assets' => 'array',
            'stages' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        DB::transaction(function () use ($request, $type) {
            $typeId = $type->id;

            $actorKeys = collect($request->input('actors', []))->pluck('actor_type_key')->filter()->all();
            DocumentTypeActor::where('document_type_id', $typeId)->whereNotIn('actor_type_id', ActorType::whereIn('key', $actorKeys)->pluck('id'))->delete();

            $sort = 1;
            foreach ($request->input('actors', []) as $actorDef) {
                $actorType = ActorType::where('key', $actorDef['actor_type_key'] ?? null)->first();
                if (!$actorType) continue;
                $actorTypeId = $actorType->id;

                $dta = DocumentTypeActor::updateOrCreate(
                    ['document_type_id' => $typeId, 'actor_type_id' => $actorTypeId],
                    ['label_override' => $actorDef['label_override'] ?? null, 'is_required' => (bool)($actorDef['is_required'] ?? false), 'sort_order' => $sort++]
                );

                $fieldKeys = collect($actorDef['fields'] ?? [])->pluck('profile_field_key')->filter()->all();
                DocumentTypeActorField::where('document_type_id', $typeId)
                    ->where('actor_type_id', $actorTypeId)
                    ->whereNotIn('profile_field_id', ProfileField::whereIn('key', $fieldKeys)->pluck('id'))
                    ->delete();

                $fsort = 1;
                foreach ($actorDef['fields'] ?? [] as $field) {
                    $pf = ProfileField::where('key', $field['profile_field_key'] ?? null)->first();
                    if (!$pf) continue;
                    DocumentTypeActorField::updateOrCreate(
                        ['document_type_id' => $typeId, 'actor_type_id' => $actorTypeId, 'profile_field_id' => $pf->id],
                        ['document_type_actor_id' => $dta->id, 'is_required' => (bool)($field['is_required'] ?? false), 'sort_order' => $fsort++]
                    );
                }

                $docKeys = collect($actorDef['documents'] ?? [])->pluck('document_catalog_key')->filter()->all();
                DocumentTypeActorDocument::where('document_type_id', $typeId)
                    ->where('actor_type_id', $actorTypeId)
                    ->whereNotIn('document_catalog_id', DocumentCatalog::whereIn('key', $docKeys)->pluck('id'))
                    ->delete();

                $dsort = 1;
                foreach ($actorDef['documents'] ?? [] as $docDef) {
                    $dc = DocumentCatalog::where('key', $docDef['document_catalog_key'] ?? null)->first();
                    if (!$dc) continue;
                    DocumentTypeActorDocument::updateOrCreate(
                        ['document_type_id' => $typeId, 'actor_type_id' => $actorTypeId, 'document_catalog_id' => $dc->id],
                        ['document_type_actor_id' => $dta->id, 'is_required' => (bool)($docDef['is_required'] ?? false), 'sort_order' => $dsort++]
                    );
                }
            }

            $assetKeys = collect($request->input('assets', []))->pluck('asset_type_key')->filter()->all();
            DocumentTypeAsset::where('document_type_id', $typeId)->whereNotIn('asset_type_id', AssetType::whereIn('key', $assetKeys)->pluck('id'))->delete();

            $asort = 1;
            foreach ($request->input('assets', []) as $assetDef) {
                $at = AssetType::where('key', $assetDef['asset_type_key'] ?? null)->first();
                if (!$at) continue;
                DocumentTypeAsset::updateOrCreate(
                    ['document_type_id' => $typeId, 'asset_type_id' => $at->id],
                    ['is_required' => (bool)($assetDef['is_required'] ?? false), 'sort_order' => $asort++]
                );
            }

            // Stages
            $stageNames = collect($request->input('stages', []))
                ->pluck('stage_name')
                ->map(fn ($n) => trim((string) $n))
                ->filter(fn ($n) => $n !== '')
                ->values();
            DocumentTypeStage::where('document_type_id', $typeId)->whereNotIn('stage_number', $stageNames->keys()->map(fn ($i) => $i + 1)->all())->delete();

            foreach ($stageNames as $i => $stageName) {
                DocumentTypeStage::updateOrCreate(
                    ['document_type_id' => $typeId, 'stage_number' => $i + 1],
                    ['stage_name' => $stageName]
                );
            }
        });

        $type->load('actorDefinitions.actorType', 'actorDefinitions.fields.profileField', 'actorDefinitions.documents.documentCatalog', 'assetDefinitions.assetType', 'stages');
        return response()->json(['message' => 'Mapping order berhasil disimpan', 'type' => $type]);
    }
}
