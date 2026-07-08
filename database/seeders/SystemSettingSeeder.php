<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'office_name', 'value' => 'Kantor Notaris/PPAT Siti Rahayu, S.H., M.Kn.', 'group' => 'office'],
            ['key' => 'office_address', 'value' => 'Jl. Sudirman No. 100, Jakarta Pusat 10220', 'group' => 'office'],
            ['key' => 'office_phone', 'value' => '(021) 5678-9012', 'group' => 'office'],
            ['key' => 'office_email', 'value' => 'info@notarisrahayu.id', 'group' => 'office'],
            ['key' => 'office_hours', 'value' => 'Senin-Jumat: 08.00-16.00 WIB', 'group' => 'office'],
            ['key' => 'app_name', 'value' => 'SiNotaris', 'group' => 'general'],
            ['key' => 'app_logo', 'value' => '', 'group' => 'general'],
            ['key' => 'timezone', 'value' => 'Asia/Jakarta', 'group' => 'general'],
            ['key' => 'default_sla_days', 'value' => '14', 'group' => 'document'],
            ['key' => 'max_file_size_mb', 'value' => '10', 'group' => 'document'],
            ['key' => 'allowed_file_types', 'value' => 'pdf,jpg,jpeg,png,doc,docx', 'group' => 'document'],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
