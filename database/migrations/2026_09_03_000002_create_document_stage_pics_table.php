<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_stage_pics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('document_stages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->default('assigned'); // assigned | transferred
            $table->text('note')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->index(['stage_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_stage_pics');
    }
};
