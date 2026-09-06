<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            // Kategori tugas: notaris (umum) vs ppat (pertanahan).
            $table->string('category')->nullable()->after('slug');
        });

        // Isi kategori untuk jenis order bawaan.
        $map = [
            'ajb'              => 'ppat',    // Akta Jual Beli tanah
            'hibah'            => 'ppat',    // Hibah tanah (PPAT)
            'hak-tanggungan'   => 'ppat',    // APHT - Hak Tanggungan
            'ppjb'             => 'notaris', // PPJB sering notaris; jika tanah bisa PPAT, disepakati notaris
            'waris'            => 'notaris',
            'wasiat'           => 'notaris',
            'pranikah'         => 'notaris',
            'pendirian-pt'     => 'notaris',
            'surat-kuasa'      => 'notaris',
            'legalisasi'       => 'notaris',
        ];
        foreach ($map as $slug => $category) {
            DB::table('document_types')->where('slug', $slug)->update(['category' => $category]);
        }
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
