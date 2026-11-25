<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legalitas & Kontrak (dokumen eksternal). Perpanjangan = versi baru di bawah nomor internal yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_documents', function (Blueprint $table) {
            $table->id();
            $table->string('number', 100)->unique();
            $table->foreignId('external_document_category_id')->constrained();
            $table->string('title');
            $table->string('issuer')->comment('Penerbit / pihak kedua');
            $table->unsignedTinyInteger('evidence_category_id')->nullable();
            $table->foreign('evidence_category_id')->references('id')->on('evidence_categories')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('external_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_document_id')->constrained();
            $table->unsignedSmallInteger('version_no');
            $table->string('original_number')->comment('Nomor asli dari penerbit');
            $table->date('issued_at');
            $table->date('expires_at')->nullable()->index();
            $table->foreignId('file_id')->constrained('files');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['external_document_id', 'version_no'], 'ext_doc_ver_unique');
        });

        Schema::table('external_documents', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('external_document_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('external_documents', fn (Blueprint $t) => $t->dropForeign(['current_version_id']));
        Schema::dropIfExists('external_document_versions');
        Schema::dropIfExists('external_documents');
    }
};
