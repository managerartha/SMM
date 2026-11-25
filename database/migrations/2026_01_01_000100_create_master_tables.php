<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data master & pengaturan: divisi, pengaturan, kategori bukti (1–16),
 * jenis dokumen, matriks pengesahan, kategori dokumen eksternal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('division_id')->references('id')->on('divisions')->nullOnDelete();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('evidence_categories', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary(); // 1–16
            $table->string('name');
            $table->string('folder_name');
            $table->timestamps();
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->boolean('review_required')->default(true);
            $table->unsignedSmallInteger('review_interval_months')->default(12);
            $table->unsignedTinyInteger('default_evidence_category_id')->nullable();
            $table->foreign('default_evidence_category_id')->references('id')->on('evidence_categories')->nullOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Matriks pengesahan generik. subject_key contoh: document:SOP, capa_closure, audit_report_process.
        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->string('subject_key')->unique();
            $table->string('label');
            $table->string('approver_role')->nullable()->comment('Nama peran pengesah; null = tidak disahkan');
            $table->string('mode', 20)->default('approve')->comment('approve | verify');
            $table->unsignedSmallInteger('phase')->default(1)->comment('Tahap modul yang memakai aturan ini');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('external_document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->unsignedTinyInteger('default_evidence_category_id')->nullable();
            $table->foreign('default_evidence_category_id', 'ext_doc_cat_evidence_fk')->references('id')->on('evidence_categories')->nullOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_document_categories');
        Schema::dropIfExists('approval_rules');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('evidence_categories');
        Schema::dropIfExists('settings');
        Schema::table('users', fn (Blueprint $t) => $t->dropForeign(['division_id']));
        Schema::dropIfExists('divisions');
    }
};
