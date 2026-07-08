<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $clients = [
            ['nik' => '3201234567890010', 'name' => 'Hendra Wijaya', 'phone' => '081311111001', 'email' => 'hendra@email.com', 'address' => 'Jl. Gatot Subroto No. 5, Jakarta', 'npwp' => '01.234.567.8-010.000'],
            ['nik' => '3201234567890011', 'name' => 'Sri Wahyuni', 'phone' => '081311111002', 'email' => 'sri@email.com', 'address' => 'Jl. Pemuda No. 12, Surabaya', 'npwp' => '01.234.567.8-011.000'],
            ['nik' => '3201234567890012', 'name' => 'PT. Maju Bersama', 'phone' => '0215678901', 'email' => 'info@majubersama.com', 'address' => 'Jl. Industri No. 100, Bekasi'],
            ['nik' => '3201234567890013', 'name' => 'Rudi Hermawan', 'phone' => '081311111003', 'email' => 'rudi@email.com', 'address' => 'Jl. Pahlawan No. 8, Semarang'],
            ['nik' => '3201234567890014', 'name' => 'Siti Aminah', 'phone' => '081311111004', 'email' => 'siti@email.com', 'address' => 'Jl. Diponegoro No. 15, Yogyakarta'],
        ];

        foreach ($clients as $client) {
            Client::firstOrCreate(['nik' => $client['nik']], $client);
        }
    }
}
