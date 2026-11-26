<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 4: audit internal, tinjauan manajemen, program inovasi mutu, laporan kinerja layanan, paket bukti.
 */
return new class extends Migration
{
    public function up(): void
    {
        $approval = function (Blueprint $t) {
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('approved_by_name')->nullable();
            $t->string('approved_by_position')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->text('approval_note')->nullable();
        };

        Schema::create('audit_programs', function (Blueprint $t) use ($approval) {
            $t->id();
            $t->unsignedSmallInteger('year')->unique();
            $t->text('objective')->nullable();
            $t->text('scope')->nullable();
            $t->text('criteria')->nullable();
            $t->string('status', 20)->default('draf')->comment('draf | pengesahan | disahkan');
            $approval($t);
            $t->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('audits', function (Blueprint $t) use ($approval) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('audit_program_id')->constrained();
            $t->foreignId('division_id')->constrained();
            $t->string('process')->comment('Proses yang diaudit');
            $t->text('scope')->nullable();
            $t->text('criteria')->nullable();
            $t->date('planned_date')->index();
            $t->foreignId('auditor_id')->constrained('users');
            $t->foreignId('auditee_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 20)->default('rencana')->comment('rencana | pelaksanaan | pengesahan | selesai | dibatalkan');
            $t->date('executed_at')->nullable();
            $t->text('conclusion')->nullable();
            $approval($t);
            $t->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $t->text('cancel_reason')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('audit_checklist_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->constrained();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->string('clause', 100)->nullable();
            $t->text('question');
            $t->string('result', 20)->nullable()->comment('sesuai | tidak_sesuai | observasi | na');
            $t->text('evidence')->nullable();
            $t->timestamps();
        });

        Schema::create('audit_findings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->constrained();
            $t->string('type', 20)->comment('mayor | minor | observasi | peluang');
            $t->string('clause', 100)->nullable();
            $t->text('description');
            $t->foreignId('ncr_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('management_reviews', function (Blueprint $t) use ($approval) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->date('meeting_date')->index();
            $t->date('period_from');
            $t->date('period_to');
            $t->string('location')->nullable();
            $t->text('attendees')->nullable();
            $t->json('inputs')->nullable()->comment('Masukan ISO 9001 9.3.2');
            $t->json('outputs')->nullable()->comment('Keluaran ISO 9001 9.3.3');
            $t->string('status', 20)->default('draf')->comment('draf | pengesahan | disahkan');
            $approval($t);
            $t->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('review_actions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('management_review_id')->constrained();
            $t->text('description');
            $t->foreignId('pic_id')->constrained('users');
            $t->date('due_date');
            $t->string('status', 20)->default('terbuka')->comment('terbuka | selesai');
            $t->text('result')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('innovations', function (Blueprint $t) use ($approval) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->string('title');
            $t->text('background');
            $t->text('objective');
            $t->text('plan')->nullable();
            $t->string('indicator')->nullable()->comment('Ukuran keberhasilan');
            $t->string('baseline')->nullable();
            $t->unsignedBigInteger('budget')->default(0);
            $t->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('proposer_id')->constrained('users');
            $t->foreignId('pic_id')->nullable()->constrained('users')->nullOnDelete();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('status', 20)->default('usulan')->comment('usulan | disetujui | ditolak | pelaksanaan | selesai');
            $approval($t);
            $t->text('result')->nullable();
            $t->string('result_value')->nullable();
            $t->text('evaluation')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('performance_reports', function (Blueprint $t) use ($approval) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->string('type', 10)->comment('bulanan | tahunan');
            $t->char('period', 7)->comment('YYYY-MM atau YYYY');
            $t->json('data')->comment('Snapshot angka saat dibuat');
            $t->text('analysis')->nullable();
            $t->string('status', 20)->default('draf');
            $approval($t);
            $t->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('evidence_packages', function (Blueprint $t) {
            $t->id();
            $t->date('date_from');
            $t->date('date_to');
            $t->string('status', 20)->default('antri')->comment('antri | proses | selesai | gagal');
            $t->json('summary')->nullable();
            $t->text('error')->nullable();
            $t->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['evidence_packages', 'performance_reports', 'innovations', 'review_actions', 'management_reviews', 'audit_findings', 'audit_checklist_items', 'audits', 'audit_programs'] as $x) {
            Schema::dropIfExists($x);
        }
    }
};
