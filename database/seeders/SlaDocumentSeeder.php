<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentCatalog;
use App\Models\DocumentType;
use App\Models\DocumentTypeDocument;
use App\Models\DocumentTypeStage;

class SlaDocumentSeeder extends Seeder
{
    // Distribusi SLA (hari kerja) per tahap per slug; jumlah = SLA total.
    private array $stageSla = [
        'ajb'             => [1, 1, 2, 2, 2, 2, 2, 2], // 14
        'ppjb'            => [2, 3, 5, 2, 2],           // 14
        'hibah'           => [2, 3, 5, 2, 2],           // 14
        'waris'           => [4, 5, 6, 4, 2],           // 21
        'hak-tanggungan'  => [1, 2, 3, 2, 2],           // 10
        'pendirian-pt'    => [1, 1, 0, 0, 0],           // 2
        'wasiat'          => [1, 1, 0, 0, 0],           // 2
        'pranikah'        => [1, 1, 0, 0, 0],           // 2
        'surat-kuasa'     => [1, 0, 0, 0, 0],           // 1
        'legalisasi'      => [1, 0, 0, 0, 0],           // 1
    ];

    // Dokumen wajib level order (bukan milik aktor/aset).
    private array $orderDocuments = [
        'ajb'            => ['imb', 'slf', 'sppt-stts'],
        'ppjb'           => ['imb', 'slf', 'sppt-stts'],
        'hibah'          => ['imb', 'slf', 'sppt-stts'],
        'waris'          => ['akta-cerai', 'skw', 'sppt-stts'],
        'hak-tanggungan' => ['perjanjian-kredit', 'offering-letter', 'sppt-stts'],
        'pendirian-pt'   => ['akta-pendirian', 'company-sk', 'nib', 'pilihan-nama'],
        'pranikah'       => ['daftar-aset'],
        'wasiat'         => [],
        'surat-kuasa'    => [],
        'legalisasi'     => [],
    ];

    public function run(): void
    {
        foreach ($this->stageSla as $slug => $slaPerStage) {
            $type = DocumentType::where('slug', $slug)->first();
            if (!$type) continue;

            $stages = DocumentTypeStage::where('document_type_id', $type->id)
                ->orderBy('stage_number')
                ->get();

            $total = 0;
            foreach ($stages as $i => $stage) {
                $days = $slaPerStage[$i] ?? 0;
                $total += $days;
                $stage->update(['sla_days' => $days]);
            }

            $type->update(['sla_days' => max($total, 1)]);
        }

        foreach ($this->orderDocuments as $slug => $docKeys) {
            $type = DocumentType::where('slug', $slug)->first();
            if (!$type) continue;

            foreach ($docKeys as $sort => $key) {
                $catalog = DocumentCatalog::where('key', $key)->first();
                if (!$catalog) continue;

                DocumentTypeDocument::firstOrCreate(
                    ['document_type_id' => $type->id, 'document_catalog_id' => $catalog->id],
                    ['is_required' => true, 'sort_order' => $sort + 1]
                );
            }
        }
    }
}