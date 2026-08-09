<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Document;
use App\Models\DocumentStage;
use App\Models\DocumentType;
use App\Models\Client;
use App\Models\User;
use App\Models\ActorType;
use App\Models\AssetType;
use App\Models\DocumentCatalog;
use App\Models\OrderActor;
use App\Models\OrderActorDocument;
use App\Models\OrderAsset;
use App\Models\OrderAssetDocument;
use App\Models\OrderDocument;
use Illuminate\Support\Facades\Storage;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        $staff   = User::where('email', 'staff@sinotaris.id')->first();
        $clients = Client::all();
        $types   = DocumentType::with('stages', 'actorDefinitions.actorType', 'actorDefinitions.fields.profileField', 'actorDefinitions.documents.documentCatalog', 'assetDefinitions.assetType', 'requiredDocuments.documentCatalog')
            ->get()->keyBy('slug');

        if ($clients->isEmpty() || !$staff) return;

        $defaultStages = ['Pembuatan Dokumen', 'Verifikasi', 'Proses', 'Review / Pemeriksaan', 'Selesai'];

        // Contoh data per tipe aktor (key mengikuti profile field; hanya yang dipakai template yg disimpan)
        $actorData = [
            'penjual' => [
                'name' => 'Bambang Susilo', 'nik' => '3201234567899001', 'npwp' => '01.111.222.3-001.000',
                'address' => 'Jl. Anggrek No. 5, Kel. Merdeka, Jakarta Timur', 'phone' => '081298765432',
                'marital_status' => 'married', 'spouse_name' => 'Tuti Susilo', 'spouse_nik' => '3201234567899002',
                'birth_place' => 'Bekasi', 'birth_date' => '1978-03-12', 'citizenship' => 'WNI', 'job' => 'Wiraswasta',
            ],
            'pembeli' => [
                'name' => 'Ahmad Fauzi', 'nik' => '3201234567899003', 'npwp' => '01.777.888.3-009.000',
                'address' => 'Jl. Melati No. 12, Kel. Cikoko, Jakarta Selatan', 'phone' => '081345678901',
                'marital_status' => 'single', 'birth_place' => 'Jakarta', 'birth_date' => '1990-07-22', 'citizenship' => 'WNI', 'job' => 'Karyawan Swasta',
            ],
            'pemberi-hibah' => [
                'name' => 'H. Suyanto', 'nik' => '3273061234560001', 'npwp' => '02.333.444.5-009.000',
                'address' => 'Jl. Raya Serpong No. 9, Tangerang Selatan', 'phone' => '081234567890',
                'marital_status' => 'married', 'spouse_name' => 'Hj. Sumiyati', 'spouse_nik' => '3273061234560002',
                'birth_place' => 'Solo', 'birth_date' => '1955-01-15', 'citizenship' => 'WNI', 'job' => 'Pensiunan',
            ],
            'penerima-hibah' => [
                'name' => 'Ratna Sari', 'nik' => '3273061234560003', 'npwp' => '02.555.666.7-009.000',
                'address' => 'Jl. Raya Serpong No. 9, Tangerang Selatan', 'phone' => '081355667788',
                'marital_status' => 'single', 'birth_place' => 'Tangerang', 'birth_date' => '1992-05-30', 'citizenship' => 'WNI', 'job' => 'Wiraswasta',
            ],
            'ahli-waris' => [
                'name' => 'Siti Rahmawati', 'nik' => '3173061234560004', 'npwp' => '03.444.555.6-001.000',
                'address' => 'Jl. Pahlawan No. 3, Kel. Petojo Utara, Jakarta Pusat', 'phone' => '081377889900',
                'marital_status' => 'widow', 'birth_place' => 'Jakarta', 'birth_date' => '1968-11-02', 'citizenship' => 'WNI', 'job' => 'Ibu Rumah Tangga',
            ],
            'mantan-pasangan' => [
                'name' => 'Joko Prasetyo', 'nik' => '3173061234560005', 'npwp' => '03.666.777.8-001.000',
                'address' => 'Jl. Kenanga No. 21, Kel. Cempaka Putih, Jakarta Pusat', 'phone' => '081322334455',
                'marital_status' => 'divorced', 'birth_place' => 'Semarang', 'birth_date' => '1964-02-18', 'citizenship' => 'WNI', 'job' => 'Karyawan',
            ],
            'pendiri' => [
                'name' => 'Andi Wijaya', 'nik' => '3171010203040001', 'npwp' => '02.111.222.3-003.000',
                'address' => 'Jl. Sudirman Kav. 45, Jakarta Selatan', 'phone' => '081288990011',
                'marital_status' => 'married', 'spouse_name' => 'Dewi Lestari', 'spouse_nik' => '3171010203040002',
                'birth_place' => 'Makassar', 'birth_date' => '1985-04-10', 'citizenship' => 'WNI', 'job' => 'Pengusaha',
            ],
            'peserta' => [
                'name' => 'Budi Hartono', 'nik' => '3171010203040003', 'npwp' => '02.222.333.4-003.000',
                'address' => 'Jl. Rasuna Said Blok X, Jakarta Selatan', 'phone' => '081266778899',
                'marital_status' => 'single', 'birth_place' => 'Surabaya', 'birth_date' => '1990-09-21', 'citizenship' => 'WNI', 'job' => 'Konsultan',
            ],
            'pemegang-saham' => [
                'name' => 'Cindy Lestari', 'nik' => '3171010203040004', 'npwp' => '02.333.444.5-003.000',
                'address' => 'Jl. Gatot Subroto No. 33, Jakarta Selatan', 'phone' => '081244556677',
                'marital_status' => 'married', 'spouse_name' => 'Ricky Tandiono', 'spouse_nik' => '3171010203040005',
                'birth_place' => 'Medan', 'birth_date' => '1988-12-05', 'citizenship' => 'WNI', 'job' => 'Investor',
            ],
            'direktur' => [
                'name' => 'Dian Pratama', 'nik' => '3171010203040006', 'npwp' => '02.444.555.6-003.000',
                'address' => 'Jl. Thamrin No. 8, Jakarta Pusat', 'phone' => '081233445566',
                'marital_status' => 'married', 'spouse_name' => 'Maya Anggraini', 'spouse_nik' => '3171010203040007',
                'birth_place' => 'Bandung', 'birth_date' => '1983-06-17', 'citizenship' => 'WNI', 'job' => 'Direktur',
            ],
            'pemberi-kuasa' => [
                'name' => 'Eko Nugroho', 'nik' => '3374061122330001', 'npwp' => '03.555.666.7-002.000',
                'address' => 'Jl. Malioboro No. 15, Yogyakarta', 'phone' => '081199887766',
                'marital_status' => 'married', 'spouse_name' => 'Rina Wulandari', 'spouse_nik' => '3374061122330002',
                'birth_place' => 'Yogyakarta', 'birth_date' => '1975-08-09', 'citizenship' => 'WNI', 'job' => 'Pengusaha',
            ],
            'penerima-kuasa' => [
                'name' => 'Fajar Ramadhan', 'nik' => '3374061122330003', 'npwp' => '03.666.777.8-002.000',
                'address' => 'Jl. Parangtritis No. 4, Yogyakarta', 'phone' => '081188776655',
                'marital_status' => 'single', 'birth_place' => 'Klaten', 'birth_date' => '1995-03-25', 'citizenship' => 'WNI', 'job' => 'Advokat',
            ],
        ];

        $assetData = [
            'sertifikat-tanah' => [
                'sertifikat_no' => 'SHM.123/KEL.MERDEKA',
                'jenis'          => 'SHM',
                'luas'           => '150',
                'lokasi'         => 'Jl. Anggrek No. 5 RT.001/RW.002, Kel. Merdeka, Kec. Jatinegara, Jakarta Timur',
            ],
        ];

        // 5 contoh order: 1 tiap jenis, dengan progres bertahap. Hibah = contoh selesai penuh.
        $samples = [
            [
                'slug' => 'ajb', 'client_idx' => 0,
                'title' => 'AJB - Tanah SHM No. 123/Kel. Merdeka',
                'status' => 'in_progress', 'current_stage' => 3, 'priority' => 'high',
                'deadline' => now()->addDays(21),
                'description' => 'Pengalihan hak atas tanah dan bangunan via Akta Jual Beli, pembayaran pajak berjalan.',
            ],
            [
                'slug' => 'hibah', 'client_idx' => 1,
                'title' => 'Akta Hibah Tanah - H. Suyanto',
                'status' => 'completed', 'current_stage' => null, 'priority' => 'normal',
                'deadline' => now()->subDays(7),
                'description' => 'Hibah sebidang tanah dari orang tua kepada anak; seluruh proses tuntas.',
            ],
            [
                'slug' => 'waris', 'client_idx' => 2,
                'title' => 'Akta Waris - Siti Rahmawati',
                'status' => 'in_progress', 'current_stage' => 2, 'priority' => 'high',
                'deadline' => now()->addDays(14),
                'description' => 'Pembagian warisan bersama ahli waris; verifikasi dokumen waris sedang berlangsung.',
            ],
            [
                'slug' => 'pendirian-pt', 'client_idx' => 3,
                'title' => 'Pendirian PT - PT. Nusantara Abadi',
                'status' => 'review', 'current_stage' => 4, 'priority' => 'urgent',
                'deadline' => now()->addDays(10),
                'description' => 'Pendirian PT dengan 4 pihak (pendiri, peserta, pemegang saham, direktur); tahap review.',
            ],
            [
                'slug' => 'surat-kuasa', 'client_idx' => 0,
                'title' => 'Surat Kuasa Jual - Eko Nugroho',
                'status' => 'draft', 'current_stage' => 1, 'priority' => 'low',
                'deadline' => now()->addDays(30),
                'description' => 'Surat kuasa menjual atas sebuah properti; baru dimulai.',
            ],
        ];

        $docSeq = 1;
        foreach ($samples as $sample) {
            $type   = $types->get($sample['slug']);
            $client = $clients->values()->get($sample['client_idx'] % $clients->count());
            if (!$type || !$client) continue;

            $stageNames = $type->stages->pluck('stage_name')->all() ?: $defaultStages;
            $stageSla   = $type->stages->pluck('sla_days')->all() ?? [];
            $total      = count($stageNames);
            $isDone     = $sample['status'] === 'completed';
            $current    = $isDone ? $total : $sample['current_stage'];

            $doc = Document::create([
                'doc_number'    => 'DOC/' . now()->year . '/01/' . str_pad($docSeq++, 4, '0', STR_PAD_LEFT),
                'tracking_code' => strtoupper(substr(md5($sample['slug'] . $docSeq), 0, 10)),
                'type_id'       => $type->id,
                'client_id'     => $client->id,
                'created_by'    => $staff->id,
                'title'         => $sample['title'],
                'description'   => $sample['description'] ?? null,
                'status'        => $sample['status'],
                'current_stage' => $current,
                'priority'      => $sample['priority'],
                'deadline'      => $sample['deadline'],
            ]);

            foreach ($stageNames as $i => $stageName) {
                $num = $i + 1;
                if ($isDone) {
                    $stageStatus = 'completed';
                } elseif ($num < $current) {
                    $stageStatus = 'completed';
                } elseif ($num == $current) {
                    $stageStatus = 'in_progress';
                } else {
                    $stageStatus = 'pending';
                }

                DocumentStage::create([
                    'document_id'  => $doc->id,
                    'stage_number' => $num,
                    'stage_name'   => $stageName,
                    'sla_days'     => $stageSla[$i] ?? null,
                    'status'       => $stageStatus,
                    'notes'        => $stageStatus === 'completed' ? 'Tahapan selesai sesuai SLA.' : null,
                    'handled_by'   => $stageStatus === 'completed' ? $staff->id : null,
                    'completed_at' => $stageStatus === 'completed' ? now()->subDays($total - $num) : null,
                ]);
            }

            // Order actors sesuai template jenis
            foreach ($type->actorDefinitions as $def) {
                $actorKey  = $def->actorType?->key;
                $sampleRow = $actorData[$actorKey] ?? [];
                if (!$actorKey || empty($sampleRow)) continue;

                $fieldKeys = $def->fields->pluck('profileField.key')->all();
                $data      = array_intersect_key($sampleRow, array_flip($fieldKeys));

                $actor = OrderActor::create([
                    'document_id'   => $doc->id,
                    'actor_type_id' => $def->actor_type_id,
                    'data'          => $data,
                    'sort_order'    => $def->sort_order,
                ]);

                foreach (($def->documents ?? collect())->where('is_required', true)->unique('document_catalog_id') as $reqDoc) {
                    $catalog = $reqDoc->documentCatalog;
                    if (!$catalog) continue;
                    $rel = "orders/{$doc->id}/actors/{$actor->id}/{$catalog->key}.pdf";
                    Storage::disk('public')->put($rel, 'Contoh dokumen pihak - ' . $catalog->label);
                    OrderActorDocument::create([
                        'order_actor_id'     => $actor->id,
                        'document_catalog_id'=> $catalog->id,
                        'filename'           => basename($rel),
                        'original_name'      => $catalog->label . '.pdf',
                        'path'               => $rel,
                        'size'               => strlen($catalog->label) + 8,
                        'uploaded_by'        => $staff->id,
                    ]);
                }
            }

            // Order assets sesuai template jenis
            foreach ($type->assetDefinitions as $def) {
                $assetKey  = $def->assetType?->key;
                $sampleRow = $assetData[$assetKey] ?? [];
                if (!$assetKey || empty($sampleRow)) continue;

                $asset = OrderAsset::create([
                    'document_id'   => $doc->id,
                    'asset_type_id' => $def->asset_type_id,
                    'data'          => $sampleRow,
                    'sort_order'    => $def->sort_order,
                ]);

                foreach (['sertifikat', 'pbb'] as $docKey) {
                    $catalog = DocumentCatalog::where('key', $docKey)->first();
                    if (!$catalog) continue;
                    $rel = "orders/{$doc->id}/assets/{$asset->id}/{$docKey}.pdf";
                    Storage::disk('public')->put($rel, 'Contoh dokumen aset - ' . $catalog->label);
                    OrderAssetDocument::create([
                        'order_asset_id'     => $asset->id,
                        'document_catalog_id'=> $catalog->id,
                        'filename'           => basename($rel),
                        'original_name'      => $catalog->label . '.pdf',
                        'path'               => $rel,
                        'size'               => strlen($catalog->label) + 8,
                        'uploaded_by'        => $staff->id,
                    ]);
                }
            }

            // Dokumen wajib level order (dokumen pendukung seluruh order)
            foreach ($type->requiredDocuments as $req) {
                $catalog = $req->documentCatalog;
                if (!$catalog) continue;
                $rel = "orders/{$doc->id}/documents/{$catalog->key}.pdf";
                Storage::disk('public')->put($rel, 'Contoh dokumen order - ' . $catalog->label);
                OrderDocument::create([
                    'document_id'          => $doc->id,
                    'document_catalog_id'  => $catalog->id,
                    'filename'             => basename($rel),
                    'original_name'        => $catalog->label . '.pdf',
                    'path'                 => $rel,
                    'size'                 => strlen($catalog->label) + 8,
                    'uploaded_by'          => $staff->id,
                ]);
            }
        }

        echo "Documents seeded successfully (5 sample orders).\n";
    }
}
