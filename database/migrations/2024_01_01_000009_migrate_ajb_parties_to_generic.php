<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ajb_sellers')) {
            return;
        }

        $ensureActor = fn (string $key, string $label) => DB::table('actor_types')
            ->updateOrInsert(['key' => $key], ['label' => $label, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $ensureAsset = fn (string $key, string $label) => DB::table('asset_types')
            ->updateOrInsert(['key' => $key], ['label' => $label, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $ensureActor('penjual', 'Penjual');
        $ensureActor('pembeli', 'Pembeli');
        $ensureAsset('sertifikat-tanah', 'Sertifikat Tanah');

        // Copy sellers -> order_actors (actor_type 'penjual')
        foreach (DB::table('ajb_sellers')->get() as $row) {
            $documentId = DB::table('ajb_cases')->where('id', $row->ajb_case_id)->value('document_id');
            if (!$documentId) continue;
            DB::table('order_actors')->insert([
                'document_id'   => $documentId,
                'actor_type_id' => DB::table('actor_types')->where('key', 'penjual')->value('id'),
                'data'          => json_encode([
                    'name'          => $row->name,
                    'nik'           => $row->nik,
                    'npwp'          => $row->npwp,
                    'address'       => $row->address,
                    'phone'         => $row->phone,
                    'marital_status'=> $row->marital_status,
                    'spouse_name'   => $row->spouse_name,
                    'spouse_nik'    => $row->spouse_nik,
                ]),
                'sort_order'    => 0,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // Copy buyers -> order_actors (actor_type 'pembeli')
        foreach (DB::table('ajb_buyers')->get() as $row) {
            $documentId = DB::table('ajb_cases')->where('id', $row->ajb_case_id)->value('document_id');
            if (!$documentId) continue;
            DB::table('order_actors')->insert([
                'document_id'   => $documentId,
                'actor_type_id' => DB::table('actor_types')->where('key', 'pembeli')->value('id'),
                'data'          => json_encode([
                    'name'    => $row->name,
                    'nik'     => $row->nik,
                    'npwp'    => $row->npwp,
                    'address' => $row->address,
                    'phone'   => $row->phone,
                ]),
                'sort_order'    => 0,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // Copy certificates -> order_assets (asset_type 'sertifikat-tanah')
        foreach (DB::table('ajb_certificates')->get() as $row) {
            $documentId = DB::table('ajb_cases')->where('id', $row->ajb_case_id)->value('document_id');
            if (!$documentId) continue;
            DB::table('order_assets')->insert([
                'document_id'   => $documentId,
                'asset_type_id' => DB::table('asset_types')->where('key', 'sertifikat-tanah')->value('id'),
                'data'          => json_encode([
                    'cert_number'  => $row->cert_number,
                    'cert_type'    => $row->cert_type,
                    'land_area'    => $row->land_area,
                    'address'      => $row->address,
                    'verified_at'  => $row->verified_at,
                    'verified_by'  => $row->verified_by,
                    'notes'        => $row->notes,
                ]),
                'sort_order'    => 0,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // Old AJB party tables are replaced by the generic order actor/asset model.
        Schema::dropIfExists('ajb_certificates');
        Schema::dropIfExists('ajb_buyers');
        Schema::dropIfExists('ajb_sellers');
    }

    public function down(): void
    {
        // Reverse migration is intentionally not supported; data already generalized.
    }
};
