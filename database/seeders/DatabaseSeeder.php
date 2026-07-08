<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            DocumentTypeSeeder::class,
            ClientSeeder::class,
            DocumentSeeder::class,
            SystemSettingSeeder::class,
        ]);
    }
}
