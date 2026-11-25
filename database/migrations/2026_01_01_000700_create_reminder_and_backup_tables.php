<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mencegah pengingat yang sama terkirim dua kali.
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('remindable');
            $table->string('kind', 30)->comment('exp-90, exp-60, exp-30, exp-0, review-30');
            $table->date('target_date');
            $table->timestamp('sent_at')->useCurrent();
            $table->unique(['remindable_type', 'remindable_id', 'kind', 'target_date'], 'reminder_unique');
        });

        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->comment('harian | bulanan | manual');
            $table->string('status', 20)->comment('berjalan | berhasil | gagal');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('file_name')->nullable();
            $table->string('drive_file_id')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('files_in_manifest')->nullable();
            $table->unsignedInteger('files_missing')->nullable();
            $table->unsignedInteger('pruned')->nullable();
            $table->text('message')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
        Schema::dropIfExists('reminder_logs');
    }
};
