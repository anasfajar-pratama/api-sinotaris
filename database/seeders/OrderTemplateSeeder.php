<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActorType;
use App\Models\AssetType;
use App\Models\DocumentCatalog;
use App\Models\ProfileField;
use App\Models\DocumentType;
use App\Models\DocumentTypeActor;
use App\Models\DocumentTypeActorField;
use App\Models\DocumentTypeActorDocument;
use App\Models\DocumentTypeAsset;
use App\Models\DocumentTypeStage;

class OrderTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Master data: actor types ----
        $actors = [
            'penjual' => 'Penjual',
            'pembeli' => 'Pembeli',
            'pemberi-hibah' => 'Pemberi Hibah',
            'penerima-hibah' => 'Penerima Hibah',
            'pemberi-wasiat' => 'Pemberi Wasiat (Testator)',
            'ahli-waris' => 'Ahli Waris',
            'mantan-pasangan' => 'Mantan Suami/Istri',
            'suami' => 'Suami',
            'istri' => 'Istri',
            'pendiri' => 'Pendiri',
            'peserta' => 'Peserta',
            'pemegang-saham' => 'Pemegang Saham',
            'direktur' => 'Direktur',
            'pemberi-kuasa' => 'Pemberi Kuasa',
            'penerima-kuasa' => 'Penerima Kuasa',
            'pemohon' => 'Pemohon',
            'kuasa-perusahaan' => 'Kuasa Perusahaan',
            'kreditur' => 'Bank / Kreditur (Penerima HT)',
            'debitur' => 'Debitur (Pemberi HT)',
            'pihak-i' => 'Pihak I',
            'pihak-ii' => 'Pihak II',
        ];
        foreach ($actors as $key => $label) {
            ActorType::firstOrCreate(['key' => $key], ['label' => $label, 'is_active' => true]);
        }

        // ---- Master data: profile fields ----
        $fields = [
            ['name', 'Nama Lengkap', 'text'],
            ['nik', 'NIK', 'text'],
            ['npwp', 'NPWP', 'text'],
            ['birth_place', 'Tempat Lahir', 'text'],
            ['birth_date', 'Tanggal Lahir', 'date'],
            ['address', 'Alamat', 'textarea'],
            ['phone', 'Telepon', 'text'],
            ['email', 'Email', 'text'],
            ['marital_status', 'Status Perkawinan', 'select'],
            ['spouse_name', 'Nama Pasangan', 'text'],
            ['spouse_nik', 'NIK Pasangan', 'text'],
            ['citizenship', 'Kewarganegaraan', 'text'],
            ['job', 'Pekerjaan', 'text'],
            ['company_name', 'Nama Perusahaan', 'text'],
            ['company_address', 'Alamat Perusahaan', 'textarea'],
            ['company_id', 'Nomor Induk Berusaha', 'text'],
        ];
        $fieldMap = [];
        foreach ($fields as [$key, $label, $type]) {
            $pf = ProfileField::firstOrCreate(['key' => $key], ['label' => $label, 'data_type' => $type, 'is_active' => true]);
            $fieldMap[$key] = $pf->id;
        }

        // ---- Master data: document catalog ----
        $docs = [
            ['ktp', 'KTP', 'identity'],
            ['npwp', 'NPWP', 'identity'],
            ['kk', 'Kartu Keluarga', 'identity'],
            ['marriage-certificate', 'Buku Nikah', 'legal'],
            ['akta-cerai', 'Akta Cerai / Putusan', 'legal'],
            ['skw', 'SK Ahli Waris', 'legal'],
            ['passport', 'Paspor', 'identity'],
            ['akta-pendirian', 'Akta Pendirian', 'legal'],
            ['company-sk', 'SK Kemenkumham / Izin Usaha', 'legal'],
            ['surat-kuasa', 'Surat Kuasa', 'legal'],
            ['perjanjian-kredit', 'Perjanjian Kredit', 'legal'],
            ['offering-letter', 'Offering Letter', 'legal'],
            ['nib', 'NIB', 'legal'],
            ['pilihan-nama', 'Pilihan Nama / Modal', 'supporting'],
            ['sertifikat', 'Sertifikat Tanah', 'asset'],
            ['pbb', 'Bukti PBB', 'asset'],
            ['sppt-stts', 'SPPT / STTS PBB', 'asset'],
            ['imb', 'IMB / PBG', 'asset'],
            ['slf', 'SLF', 'asset'],
            ['daftar-aset', 'Daftar Aset', 'supporting'],
            ['other', 'Dokumen Lainnya', 'supporting'],
        ];
        $docMap = [];
        foreach ($docs as [$key, $label, $cat]) {
            $dc = DocumentCatalog::firstOrCreate(['key' => $key], ['label' => $label, 'category' => $cat, 'is_active' => true]);
            $docMap[$key] = $dc->id;
        }

        // ---- Master data: asset types ----
        $assets = ['sertifikat-tanah' => 'Sertifikat Tanah'];
        foreach ($assets as $key => $label) {
            AssetType::firstOrCreate(['key' => $key], ['label' => $label, 'is_active' => true]);
        }

        // ---- Template tahapan per jenis order ----
        $defaultStages = [
            'Pembuatan Dokumen',
            'Verifikasi',
            'Proses',
            'Review / Pemeriksaan',
            'Selesai',
        ];

        $stageTemplates = [
            'ajb' => [
                'Data Masuk',
                'Cek Sertifikat',
                'Pembayaran Pajak (BPHTB & SSP)',
                'Pembuatan Akta Jual Beli',
                'Input Akta AJB ke Sistem BPN',
                'Pengiriman Dokumen ke BPN',
                'Proses di BPN',
                'Pembayaran SPS & Selesai',
            ],
        ];

        // ---- Template mapping per document type ----
        // actor fields default = common individual fields; docs default = KTP (wajib) + NPWP.
        $defaultFields = ['name', 'nik', 'address', 'phone', 'marital_status', 'spouse_name', 'spouse_nik'];
        $defaultDocs = ['ktp', 'npwp'];

        $templates = [
            'ajb' => [
                'actors' => ['penjual', 'pembeli'],
                'assets' => ['sertifikat-tanah'],
            ],
            'ppjb' => [
                'actors' => ['penjual', 'pembeli'],
                'assets' => ['sertifikat-tanah'],
            ],
            'hibah' => [
                'actors' => ['pemberi-hibah', 'penerima-hibah'],
                'assets' => ['sertifikat-tanah'],
            ],
            'waris' => [
                'actors' => ['ahli-waris', 'mantan-pasangan'],
                'assets' => ['sertifikat-tanah'],
            ],
            'wasiat' => [
                'actors' => ['pemberi-wasiat'],
                'assets' => [],
            ],
            'pranikah' => [
                'actors' => ['suami', 'istri'],
                'assets' => [],
            ],
            'pendirian-pt' => [
                'actors' => ['pendiri', 'peserta', 'pemegang-saham', 'direktur'],
                'assets' => [],
            ],
            'surat-kuasa' => [
                'actors' => ['pemberi-kuasa', 'penerima-kuasa'],
                'assets' => [],
            ],
            'legalisasi' => [
                'actors' => ['pemohon', 'kuasa-perusahaan'],
                'assets' => [],
            ],
            'hak-tanggungan' => [
                'actors' => ['kreditur', 'debitur'],
                'assets' => ['sertifikat-tanah'],
            ],
        ];

        foreach ($templates as $slug => $config) {
            $dt = DocumentType::where('slug', $slug)->first();
            if (!$dt) continue;

            $stageList = $stageTemplates[$slug] ?? $defaultStages;
            foreach ($stageList as $snum => $stageName) {
                DocumentTypeStage::firstOrCreate(
                    ['document_type_id' => $dt->id, 'stage_number' => $snum + 1],
                    ['stage_name' => $stageName]
                );
            }

            foreach ($config['actors'] as $sort => $actorKey) {
                $actorId = ActorType::where('key', $actorKey)->value('id');
                if (!$actorId) continue;

                $dta = DocumentTypeActor::firstOrCreate(
                    ['document_type_id' => $dt->id, 'actor_type_id' => $actorId],
                    ['is_required' => true, 'sort_order' => $sort + 1]
                );

                foreach ($defaultFields as $fsort => $fieldKey) {
                    if (!isset($fieldMap[$fieldKey])) continue;
                    DocumentTypeActorField::firstOrCreate(
                        ['document_type_id' => $dt->id, 'actor_type_id' => $actorId, 'profile_field_id' => $fieldMap[$fieldKey]],
                        ['document_type_actor_id' => $dta->id, 'is_required' => in_array($fieldKey, ['name', 'nik']), 'sort_order' => $fsort + 1]
                    );
                }

                foreach ($defaultDocs as $dsort => $docKey) {
                    if (!isset($docMap[$docKey])) continue;
                    DocumentTypeActorDocument::firstOrCreate(
                        ['document_type_id' => $dt->id, 'actor_type_id' => $actorId, 'document_catalog_id' => $docMap[$docKey]],
                        ['document_type_actor_id' => $dta->id, 'is_required' => $docKey === 'ktp', 'sort_order' => $dsort + 1]
                    );
                }
            }

            foreach ($config['assets'] ?? [] as $sort => $assetKey) {
                $assetId = AssetType::where('key', $assetKey)->value('id');
                if (!$assetId) continue;
                DocumentTypeAsset::firstOrCreate(
                    ['document_type_id' => $dt->id, 'asset_type_id' => $assetId],
                    ['is_required' => true, 'sort_order' => $sort + 1]
                );
            }
        }
    }
}