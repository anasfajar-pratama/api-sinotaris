<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
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
}
