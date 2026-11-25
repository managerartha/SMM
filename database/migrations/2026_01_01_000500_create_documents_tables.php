<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumen terkendali: dokumen → revisi → jejak persetujuan.
 * Aturan "hanya satu revisi berlaku" dan "hanya satu revisi terbuka" dijaga indeks unik
 * pada kolom turunan (NULL tidak dihitung bentrok oleh indeks unik).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('number', 100)->unique();
            $table->foreignId('document_type_id')->constrained();
            $table->foreignId('division_id')->constrained();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->foreignId('owner_id')->constrained('users');
            $table->unsignedTinyInteger('evidence_category_id')->nullable();
            $table->foreign('evidence_category_id')->references('id')->on('evidence_categories')->nullOnDelete();
            $table->string('access_scope', 20)->default('terbatas')->comment('umum | terbatas');
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->date('next_review_date')->nullable()->index();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('role_name')->nullable();
            $table->timestamps();
        });

        Schema::create('document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained();
            $table->unsignedSmallInteger('revision_no');
            $table->string('status', 30)->index();
            $table->text('change_reason')->nullable();
            $table->text('change_summary')->nullable();
            $table->foreignId('pdf_file_id')->nullable()->constrained('files');
            $table->foreignId('source_file_id')->nullable()->constrained('files');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('reviewer_id')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('approved_by_name')->nullable();
            $table->string('approved_by_position')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->date('effective_date')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->unsignedBigInteger('superseded_by_id')->nullable();
            $table->timestamps();

            $table->unsignedTinyInteger('current_flag')->nullable()
                ->storedAs("IF(`status` = 'berlaku', 1, NULL)");
            $table->unsignedTinyInteger('open_flag')->nullable()
                ->storedAs("IF(`status` IN ('draf','review','pengesahan'), 1, NULL)");

            $table->unique(['document_id', 'revision_no']);
            $table->unique(['document_id', 'current_flag'], 'doc_rev_one_current');
            $table->unique(['document_id', 'open_flag'], 'doc_rev_one_open');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('current_revision_id')->references('id')->on('document_revisions')->nullOnDelete();
        });

        Schema::create('document_revision_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_revision_id')->constrained();
            $table->string('action', 30)->comment('dibuat|diajukan|diteruskan|ditolak_review|disahkan|ditolak_pengesahan|dibatalkan|kedaluwarsa|file_diganti');
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('actor_name')->nullable();
            $table->string('actor_position')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_revision_events');
        Schema::table('documents', fn (Blueprint $t) => $t->dropForeign(['current_revision_id']));
        Schema::dropIfExists('document_revisions');
        Schema::dropIfExists('document_access');
        Schema::dropIfExists('documents');
    }
};
