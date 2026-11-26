<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 2: Keluhan & Feedback, NCR & CAPA (dengan pemantauan efektivitas 30/60/90 hari), Risiko & Peluang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->string('type', 20)->comment('keluhan | feedback | saran');
            $table->string('source', 30)->comment('peserta | klien | internal | survei | pemasok | lainnya');
            $table->string('channel', 30)->nullable()->comment('whatsapp | email | telepon | langsung | formulir | lms | lainnya');
            $table->date('received_at');
            $table->string('reporter_name')->nullable();
            $table->string('reporter_contact')->nullable();
            $table->string('reporter_org')->nullable()->comment('Perusahaan / instansi');
            $table->string('class_code', 100)->nullable()->comment('Kode kelas (batch) LMS');
            $table->string('category', 30)->nullable();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->string('severity', 20)->nullable()->comment('rendah | sedang | tinggi');
            $table->foreignId('pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('response_due_date')->nullable();
            $table->string('status', 20)->default('baru')->index()->comment('baru | diproses | selesai | dibatalkan');
            $table->text('response')->nullable()->comment('Tanggapan kepada pelapor');
            $table->timestamp('responded_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('ncr_id')->nullable();
            $table->unsignedTinyInteger('evidence_category_id')->nullable();
            $table->foreign('evidence_category_id')->references('id')->on('evidence_categories')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('ncrs', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->string('source', 30)->comment('keluhan | audit_internal | temuan_internal | survei | pemasok | eksternal | lainnya');
            $table->foreignId('complaint_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->comment('Uraian ketidaksesuaian');
            $table->string('requirement')->nullable()->comment('Persyaratan / klausul yang tidak dipenuhi');
            $table->foreignId('division_id')->constrained();
            $table->string('severity', 10)->default('minor')->comment('minor | mayor');
            $table->date('found_at');
            $table->foreignId('pic_id')->constrained('users')->comment('Penanggung jawab NCR');
            $table->date('target_date')->nullable()->index();
            $table->string('status', 30)->default('terbuka')->index();
            $table->text('correction')->nullable()->comment('Koreksi / tindakan segera');
            $table->timestamp('correction_at')->nullable();
            $table->string('root_cause_method', 30)->nullable()->comment('5why | fishbone | lainnya');
            $table->text('root_cause')->nullable();
            $table->timestamp('root_cause_at')->nullable();
            $table->text('verification_note')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('closure_note')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('closed_by_name')->nullable();
            $table->string('closed_by_position')->nullable();
            $table->unsignedSmallInteger('reopened_count')->default(0);
            $table->text('cancel_reason')->nullable();
            $table->unsignedTinyInteger('evidence_category_id')->nullable();
            $table->foreign('evidence_category_id')->references('id')->on('evidence_categories')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->foreign('ncr_id')->references('id')->on('ncrs')->nullOnDelete();
        });

        Schema::create('ncr_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ncr_id')->constrained();
            $table->unsignedSmallInteger('cycle')->default(1)->comment('Siklus ke-n (naik bila NCR dibuka ulang)');
            $table->text('description');
            $table->foreignId('pic_id')->constrained('users');
            $table->date('due_date');
            $table->string('status', 20)->default('rencana')->comment('rencana | selesai | dibatalkan');
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_note')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('ncr_effectiveness_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ncr_id')->constrained();
            $table->unsignedSmallInteger('cycle')->default(1);
            $table->unsignedSmallInteger('day')->comment('30 | 60 | 90');
            $table->date('due_date')->index();
            $table->string('result', 20)->nullable()->comment('efektif | tidak_efektif');
            $table->text('note')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['ncr_id', 'cycle', 'day']);
        });

        Schema::create('ncr_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ncr_id')->constrained();
            $table->string('action', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('actor_name')->nullable();
            $table->string('actor_position')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->string('type', 10)->comment('risiko | peluang');
            $table->foreignId('division_id')->constrained();
            $table->string('process')->nullable()->comment('Proses / kegiatan terkait');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('cause')->nullable();
            $table->text('consequence')->nullable()->comment('Dampak / manfaat bila terjadi');
            $table->unsignedTinyInteger('likelihood');
            $table->unsignedTinyInteger('impact');
            $table->unsignedTinyInteger('score');
            $table->string('level', 10)->index();
            $table->text('existing_controls')->nullable();
            $table->text('treatment')->nullable()->comment('Rencana penanganan / pemanfaatan');
            $table->foreignId('treatment_pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('treatment_due_date')->nullable();
            $table->string('treatment_status', 20)->default('rencana')->comment('rencana | berjalan | selesai');
            $table->foreignId('owner_id')->constrained('users');
            $table->date('review_date')->nullable()->index();
            $table->string('status', 10)->default('aktif')->index()->comment('aktif | ditutup');
            $table->text('close_note')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('ncr_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('evidence_category_id')->nullable();
            $table->foreign('evidence_category_id')->references('id')->on('evidence_categories')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_id')->constrained();
            $table->unsignedTinyInteger('likelihood');
            $table->unsignedTinyInteger('impact');
            $table->unsignedTinyInteger('score');
            $table->string('level', 10);
            $table->text('note')->nullable();
            $table->foreignId('assessed_by')->constrained('users');
            $table->timestamp('assessed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
        Schema::dropIfExists('risks');
        Schema::dropIfExists('ncr_events');
        Schema::dropIfExists('ncr_effectiveness_checks');
        Schema::dropIfExists('ncr_actions');
        Schema::table('complaints', fn (Blueprint $t) => $t->dropForeign(['ncr_id']));
        Schema::dropIfExists('ncrs');
        Schema::dropIfExists('complaints');
    }
};
