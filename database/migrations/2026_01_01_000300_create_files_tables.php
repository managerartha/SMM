<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berkas (dipakai semua modul). Penyimpanan utama di Google Drive:
 * pending (masih di server) → stored (sudah di Drive, salinan lokal dihapus) | failed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('original_name');
            $table->string('extension', 10);
            $table->string('mime', 150);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->char('md5', 32);
            $table->string('state', 20)->default('pending')->index();
            $table->string('local_path')->nullable();
            $table->string('drive_folder_path')->comment('Jalur folder di Drive, mis. Dokumen Terkendali/SOP/ASI-SOP-OPS-01');
            $table->string('drive_name');
            $table->string('drive_file_id')->nullable()->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('stored_at')->nullable();
            $table->nullableMorphs('attachable');
            $table->string('purpose', 50)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Cache jalur folder Drive → ID folder supaya tidak perlu mencari ulang.
        Schema::create('drive_folders', function (Blueprint $table) {
            $table->id();
            $table->string('path', 500);
            $table->char('path_hash', 64)->unique();
            $table->string('drive_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_folders');
        Schema::dropIfExists('files');
    }
};
