<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_type_stages', function (Blueprint $table) {
            $table->integer('sla_days')->nullable()->after('stage_name');
        });

        Schema::table('document_stages', function (Blueprint $table) {
            $table->integer('sla_days')->nullable()->after('stage_name');
        });

        Schema::create('document_type_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_catalog_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['document_type_id', 'document_catalog_id'], 'dtd_unique');
        });

        Schema::create('order_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_catalog_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('original_name');
            $table->string('path');
            $table->bigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_documents');
        Schema::dropIfExists('document_type_documents');
        Schema::table('document_type_stages', function (Blueprint $table) {
            $table->dropColumn('sla_days');
        });
        Schema::table('document_stages', function (Blueprint $table) {
            $table->dropColumn('sla_days');
        });
    }
};