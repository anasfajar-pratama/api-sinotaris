<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_type_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->integer('stage_number');
            $table->string('stage_name');
            $table->timestamps();
            $table->unique(['document_type_id', 'stage_number'], 'dts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_type_stages');
    }
};