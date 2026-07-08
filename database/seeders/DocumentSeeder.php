<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Document;
use App\Models\DocumentStage;
use App\Models\DocumentType;
use App\Models\Client;
use App\Models\User;
use App\Models\AjbCase;
use App\Models\AjbStep;
use App\Models\AjbSeller;
use App\Models\AjbBuyer;
use App\Models\AjbCertificate;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        $staff = User::where('email', 'staff@sinotaris.id')->first();
        $clients = Client::all();
        $types = DocumentType::all()->keyBy('slug');

        if ($clients->isEmpty() || !$staff) return;

        // Sample documents in various states
        $documents = [
            [
                'type' => 'hibah', 'client_idx' => 0, 'status' => 'completed',
                'title' => 'Akta Hibah Tanah - Hendra Wijaya', 'priority' => 'normal',
                'current_stage' => 5,
            ],
            [
                'type' => 'waris', 'client_idx' => 1, 'status' => 'in_progress',
                'title' => 'Akta Waris - Sri Wahyuni', 'priority' => 'high',
                'current_stage' => 2,
            ],
            [
                'type' => 'surat-kuasa', 'client_idx' => 2, 'status' => 'review',
                'title' => 'Surat Kuasa Jual - PT. Maju Bersama', 'priority' => 'urgent',
                'current_stage' => 4,
            ],
            [
                'type' => 'legalisasi', 'client_idx' => 3, 'status' => 'draft',
                'title' => 'Legalisasi KTP - Rudi Hermawan', 'priority' => 'low',
                'current_stage' => 1,
            ],
        ];

        foreach ($documents as $idx => $data) {
            $type = $types->get($data['type']);
            $client = $clients->values()->get($data['client_idx'] % $clients->count());
            if (!$type || !$client) continue;

            $doc = Document::create([
                'doc_number'    => 'DOC/' . now()->year . '/01/' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT),
                'tracking_code' => strtoupper(substr(md5($idx . time()), 0, 10)),
                'type_id'       => $type->id,
                'client_id'     => $client->id,
                'created_by'    => $staff->id,
                'title'         => $data['title'],
                'status'        => $data['status'],
                'current_stage' => $data['current_stage'],
                'priority'      => $data['priority'],
                'deadline'      => now()->addDays(rand(5, 30)),
            ]);

            // Create stages
            for ($i = 1; $i <= 5; $i++) {
                $stageStatus = 'pending';
                if ($i < $data['current_stage']) $stageStatus = 'completed';
                elseif ($i == $data['current_stage']) $stageStatus = 'in_progress';

                DocumentStage::create([
                    'document_id'  => $doc->id,
                    'stage_number' => $i,
                    'stage_name'   => ['Pembuatan Dokumen', 'Verifikasi', 'Proses', 'Review/Pemeriksaan', 'Selesai'][$i - 1],
                    'status'       => $stageStatus,
                    'completed_at' => $stageStatus === 'completed' ? now()->subDays(5 - $i) : null,
                ]);
            }
        }

        // Create sample AJB case
        $ajbType = $types->get('ajb');
        $client  = $clients->first();
        if ($ajbType && $client) {
            $ajbDoc = Document::create([
                'doc_number'    => 'DOC/' . now()->year . '/01/0010',
                'tracking_code' => 'AJB' . strtoupper(substr(md5('ajb1'), 0, 7)),
                'type_id'       => $ajbType->id,
                'client_id'     => $client->id,
                'created_by'    => $staff->id,
                'title'         => 'AJB - Tanah SHM No. 123/Kel. Merdeka',
                'status'        => 'in_progress',
                'current_stage' => 3,
                'priority'      => 'high',
                'deadline'      => now()->addDays(21),
            ]);

            $ajbCase = AjbCase::create([
                'document_id'  => $ajbDoc->id,
                'case_number'  => 'AJB/' . now()->year . '/01/0001',
                'source_type'  => 'bank',
                'current_step' => 3,
                'status'       => 'active',
            ]);

            foreach (AjbCase::STEPS as $num => $name) {
                AjbStep::create([
                    'ajb_case_id'  => $ajbCase->id,
                    'step_number'  => $num,
                    'step_name'    => $name,
                    'status'       => $num < 3 ? 'completed' : ($num == 3 ? 'in_progress' : 'pending'),
                    'completed_at' => $num < 3 ? now()->subDays(3 - $num) : null,
                    'completed_by' => $num < 3 ? $staff->id : null,
                ]);
            }

            AjbSeller::create([
                'ajb_case_id'    => $ajbCase->id,
                'name'           => 'Bambang Susilo',
                'nik'            => '3201234567899001',
                'npwp'           => '01.111.222.3-001.000',
                'address'        => 'Jl. Anggrek No. 5, Jakarta Timur',
                'marital_status' => 'married',
                'spouse_name'    => 'Tuti Susilo',
                'spouse_nik'     => '3201234567899002',
            ]);

            AjbBuyer::create([
                'ajb_case_id' => $ajbCase->id,
                'name'        => $client->name,
                'nik'         => $client->nik,
                'npwp'        => $client->npwp,
                'address'     => $client->address,
            ]);

            AjbCertificate::create([
                'ajb_case_id' => $ajbCase->id,
                'cert_number' => 'SHM.123/KEL.MERDEKA',
                'cert_type'   => 'SHM',
                'land_area'   => 150.00,
                'address'     => 'Jl. Anggrek No. 5 RT.01/RW.02 Kel. Merdeka Kec. Jatinegara, Jakarta Timur',
                'verified_at' => now()->subDays(5),
                'verified_by' => $staff->id,
            ]);
        }

        echo "Documents and AJB cases seeded successfully.\n";
    }
}
