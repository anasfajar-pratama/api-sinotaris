<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajb_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('case_number')->unique();
            $table->enum('source_type', ['bank', 'notaris', 'walk_in'])->default('walk_in');
            $table->integer('current_step')->default(1);
            $table->enum('status', ['active', 'on_hold', 'completed', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ajb_sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ajb_case_id')->constrained('ajb_cases')->cascadeOnDelete();
            $table->string('name');
            $table->string('nik', 20);
            $table->string('npwp', 30)->nullable();
            $table->text('address');
            $table->string('phone', 20)->nullable();
            $table->enum('marital_status', ['single', 'married', 'widowed'])->default('single');
            $table->string('spouse_name')->nullable();
            $table->string('spouse_nik', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('ajb_buyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ajb_case_id')->constrained('ajb_cases')->cascadeOnDelete();
            $table->string('name');
            $table->string('nik', 20);
            $table->string('npwp', 30)->nullable();
            $table->text('address');
            $table->string('phone', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('ajb_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ajb_case_id')->constrained('ajb_cases')->cascadeOnDelete();
            $table->string('cert_number');
            $table->enum('cert_type', ['SHM', 'SHGB', 'SHSRS', 'girik', 'other']);
            $table->decimal('land_area', 12, 2);
            $table->text('address');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ajb_tax_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ajb_case_id')->constrained('ajb_cases')->cascadeOnDelete();
            $table->enum('type', ['bphtb', 'ssp', 'sps']);
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('receipt_number')->nullable();
            $table->string('file_path')->nullable();
            $table->enum('status', ['pending', 'paid', 'verified'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ajb_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ajb_case_id')->constrained('ajb_cases')->cascadeOnDelete();
            $table->string('doc_type');
            $table->string('filename');
            $table->string('path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ajb_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ajb_case_id')->constrained('ajb_cases')->cascadeOnDelete();
            $table->integer('step_number');
            $table->string('step_name');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ajb_bpn_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ajb_case_id')->constrained('ajb_cases')->cascadeOnDelete();
            $table->string('spa_number')->nullable();
            $table->date('submission_date')->nullable();
            $table->string('sps_number')->nullable();
            $table->decimal('sps_amount', 15, 2)->nullable();
            $table->date('payment_date')->nullable();
            $table->enum('status', ['pending', 'submitted', 'processed', 'completed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajb_bpn_submissions');
        Schema::dropIfExists('ajb_steps');
        Schema::dropIfExists('ajb_documents');
        Schema::dropIfExists('ajb_tax_payments');
        Schema::dropIfExists('ajb_certificates');
        Schema::dropIfExists('ajb_buyers');
        Schema::dropIfExists('ajb_sellers');
        Schema::dropIfExists('ajb_cases');
    }
};
