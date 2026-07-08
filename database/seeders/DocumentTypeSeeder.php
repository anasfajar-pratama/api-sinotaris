<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentType;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Akta Jual Beli (AJB)', 'slug' => 'ajb', 'description' => 'Akta peralihan hak atas tanah/bangunan melalui jual beli', 'sla_days' => 30],
            ['name' => 'Akta Hibah', 'slug' => 'hibah', 'description' => 'Pemindahan hak secara cuma-cuma (hadiah)', 'sla_days' => 21],
            ['name' => 'Akta Waris', 'slug' => 'waris', 'description' => 'Pembagian harta warisan sesuai hukum yang berlaku', 'sla_days' => 30],
            ['name' => 'Akta Wasiat', 'slug' => 'wasiat', 'description' => 'Surat pernyataan kehendak terakhir seseorang', 'sla_days' => 14],
            ['name' => 'Perjanjian Pranikah', 'slug' => 'pranikah', 'description' => 'Perjanjian sebelum pernikahan tentang harta', 'sla_days' => 14],
            ['name' => 'Akta Pendirian PT', 'slug' => 'pendirian-pt', 'description' => 'Pendirian badan hukum Perseroan Terbatas', 'sla_days' => 21],
            ['name' => 'Akta PPJB', 'slug' => 'ppjb', 'description' => 'Perjanjian Pengikatan Jual Beli', 'sla_days' => 14],
            ['name' => 'Surat Kuasa', 'slug' => 'surat-kuasa', 'description' => 'Pemberian kuasa kepada pihak lain', 'sla_days' => 7],
            ['name' => 'Legalisasi Dokumen', 'slug' => 'legalisasi', 'description' => 'Pengesahan dokumen oleh notaris', 'sla_days' => 3],
            ['name' => 'Hak Tanggungan', 'slug' => 'hak-tanggungan', 'description' => 'Jaminan atas hak atas tanah', 'sla_days' => 21],
        ];

        foreach ($types as $type) {
            DocumentType::firstOrCreate(['slug' => $type['slug']], $type);
        }
    }
}
