<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // === Master data (dictionary) ===

        Schema::create('actor_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('profile_fields', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->enum('data_type', ['text', 'number', 'date', 'select', 'textarea'])->default('text');
            $table->json('options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->enum('category', ['identity', 'legal', 'asset', 'supporting'])->default('identity');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('asset_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // === Product template (per document type) ===

        Schema::create('document_type_actors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_type_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('label_override')->nullable();
            $table->timestamps();
            $table->unique(['document_type_id', 'actor_type_id'], 'dta_unique');
        });

        Schema::create('document_type_actor_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_type_actor_id')->nullable();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_field_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['document_type_id', 'actor_type_id', 'profile_field_id'], 'dtaf_unique');
            $table->foreign('document_type_actor_id')->references('id')->on('document_type_actors')->cascadeOnDelete();
        });

        Schema::create('document_type_actor_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_type_actor_id')->nullable();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_catalog_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['document_type_id', 'actor_type_id', 'document_catalog_id'], 'dtad_unique');
            $table->foreign('document_type_actor_id')->references('id')->on('document_type_actors')->cascadeOnDelete();
        });

        Schema::create('document_type_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_type_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['document_type_id', 'asset_type_id'], 'dtas_unique');
        });

        // === Order instance data ===

        Schema::create('order_actors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_type_id')->constrained()->cascadeOnDelete();
            $table->json('data')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('order_actor_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_actor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_catalog_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('original_name');
            $table->string('path');
            $table->bigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('order_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_type_id')->constrained()->cascadeOnDelete();
            $table->json('data')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('order_asset_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_asset_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('order_asset_documents');
        Schema::dropIfExists('order_assets');
        Schema::dropIfExists('order_actor_documents');
        Schema::dropIfExists('order_actors');
        Schema::dropIfExists('document_type_assets');
        Schema::dropIfExists('document_type_actor_documents');
        Schema::dropIfExists('document_type_actor_fields');
        Schema::dropIfExists('document_type_actors');
        Schema::dropIfExists('asset_types');
        Schema::dropIfExists('document_catalogs');
        Schema::dropIfExists('profile_fields');
        Schema::dropIfExists('actor_types');
    }
};
