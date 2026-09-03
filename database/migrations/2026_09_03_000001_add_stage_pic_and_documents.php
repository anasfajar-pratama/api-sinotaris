<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_stages', function (Blueprint $table) {
            $table->foreignId('pic_id')->nullable()->after('handled_by')->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable()->after('notes');
        });

        Schema::create('stage_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('document_stages')->cascadeOnDelete();
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
        Schema::dropIfExists('stage_documents');
        Schema::table('document_stages', function (Blueprint $table) {
            $table->dropForeign(['pic_id']);
            $table->dropColumn(['pic_id', 'started_at']);
        });
    }
};
