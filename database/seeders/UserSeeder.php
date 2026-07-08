<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@sinotaris.id'],
            [
                'name'      => 'Super Administrator',
                'password'  => Hash::make('password'),
                'phone'     => '081200000001',
                'is_active' => true,
            ]
        );
        $superAdmin->assignRole('super-admin');

        // Notaris
        $notaris = User::firstOrCreate(
            ['email' => 'notaris@sinotaris.id'],
            [
                'name'      => 'Dra. Siti Rahayu, S.H., M.Kn.',
                'password'  => Hash::make('password'),
                'phone'     => '081200000002',
                'is_active' => true,
            ]
        );
        $notaris->assignRole('notaris');

        // Staff
        $staff = User::firstOrCreate(
            ['email' => 'staff@sinotaris.id'],
            [
                'name'      => 'Ali Santoso',
                'password'  => Hash::make('password'),
                'phone'     => '081200000003',
                'is_active' => true,
            ]
        );
        $staff->assignRole('staff');

        // Klien 1
        $klienUser = User::firstOrCreate(
            ['email' => 'klien@sinotaris.id'],
            [
                'name'      => 'Ahmad Fauzi',
                'password'  => Hash::make('password'),
                'phone'     => '081200000004',
                'is_active' => true,
            ]
        );
        $klienUser->assignRole('klien');

        Client::firstOrCreate(
            ['user_id' => $klienUser->id],
            [
                'nik'       => '3201234567890001',
                'name'      => 'Ahmad Fauzi',
                'phone'     => '081200000004',
                'email'     => 'klien@sinotaris.id',
                'address'   => 'Jl. Merdeka No. 10, Jakarta Selatan',
                'npwp'      => '01.234.567.8-901.000',
            ]
        );

        // Klien 2
        $klienUser2 = User::firstOrCreate(
            ['email' => 'klien2@sinotaris.id'],
            [
                'name'      => 'Dewi Kurniasih',
                'password'  => Hash::make('password'),
                'phone'     => '081200000005',
                'is_active' => true,
            ]
        );
        $klienUser2->assignRole('klien');

        Client::firstOrCreate(
            ['user_id' => $klienUser2->id],
            [
                'nik'       => '3201234567890002',
                'name'      => 'Dewi Kurniasih',
                'phone'     => '081200000005',
                'email'     => 'klien2@sinotaris.id',
                'address'   => 'Jl. Sudirman No. 25, Bandung',
                'npwp'      => '01.234.567.8-902.000',
            ]
        );

        echo "Users seeded successfully.\n";
        echo "Login credentials:\n";
        echo "  admin@sinotaris.id    / password (Super Admin)\n";
        echo "  notaris@sinotaris.id  / password (Notaris)\n";
        echo "  staff@sinotaris.id    / password (Staff)\n";
        echo "  klien@sinotaris.id    / password (Klien)\n";
    }
}
