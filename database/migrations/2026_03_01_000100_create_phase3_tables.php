<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 3: data induk (program, pemasok), kelas dari LMS, catatan mutu, personel, sarana & prasarana,
 * rekap survei, sasaran mutu & KPI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('institution', 50)->nullable()->comment('Kemnaker RI | BNSP | Internal | lainnya');
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->string('lms_program_id', 100)->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('name');
            $table->string('category', 30)->comment('venue | katering | percetakan | alat | trainer | transportasi | atk | lainnya');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('aktif')->comment('aktif | dievaluasi | tidak_dipakai');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('supplier_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->string('period', 20)->comment('mis. 2027-S1');
            $table->unsignedTinyInteger('quality');
            $table->unsignedTinyInteger('timeliness');
            $table->unsignedTinyInteger('price');
            $table->unsignedTinyInteger('service');
            $table->decimal('average', 3, 2);
            $table->string('decision', 20)->comment('dipertahankan | dibina | diganti');
            $table->text('notes')->nullable();
            $table->foreignId('evaluated_by')->constrained('users');
            $table->date('evaluated_at');
            $table->timestamps();
        });

        Schema::create('training_classes', function (Blueprint $table) {
            $table->id();
            $table->string('class_code', 100)->unique()->comment('Kode kelas (kode_batch) dari LMS – tidak dibuat SMM');
            $table->string('program_name');
            $table->foreignId('training_program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scheme', 50)->nullable()->comment('jenis_pelatihan di LMS');
            $table->string('client')->nullable()->comment('Perusahaan inhouse');
            $table->string('location')->nullable();
            $table->date('start_date')->nullable()->index();
            $table->date('end_date')->nullable();
            $table->string('trainers')->nullable();
            $table->unsignedInteger('participants')->nullable();
            $table->unsignedInteger('attended')->nullable()->comment('Peserta hadir penuh');
            $table->unsignedInteger('passed')->nullable();
            $table->decimal('average_score', 5, 2)->nullable();
            $table->string('lms_status', 30)->nullable();
            $table->string('source', 10)->default('csv')->comment('csv | api');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lms_imports', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->comment('kelas | survei');
            $table->string('source', 10)->comment('csv | api');
            $table->string('file_name')->nullable();
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->json('errors')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quality_records', function (Blueprint $table) {
            $table->id();
            $table->string('number', 60)->unique();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete()->comment('Formulir terkendali yang diisi');
            $table->foreignId('training_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('division_id')->constrained();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('activity_date')->index();
            $table->unsignedSmallInteger('retention_years')->default(5);
            $table->date('retention_until')->index();
            $table->unsignedTinyInteger('evidence_category_id')->nullable();
            $table->foreign('evidence_category_id')->references('id')->on('evidence_categories')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('personnel', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('type', 20)->comment('tenaga_ahli | tenaga_teknis');
            $table->string('name');
            $table->string('position')->nullable()->comment('Mis. Ahli K3 Umum, Trainer, Teknisi');
            $table->boolean('is_trainer')->default(false);
            $table->string('employment', 20)->nullable()->comment('tetap | kontrak | freelance');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->date('joined_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('aktif')->comment('aktif | nonaktif');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('personnel_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel');
            $table->string('category', 30)->comment('cv | ijazah | sertifikat | lisensi | sk | kontrak | ktp | lainnya');
            $table->string('title');
            $table->string('original_number')->nullable();
            $table->string('issuer')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->foreignId('file_id')->constrained('files');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('replaced_by_id')->nullable()->comment('Diperbarui oleh dokumen lain (riwayat)');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('category', 10);
            $table->string('name');
            $table->string('brand_model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('condition', 20)->default('baik')->comment('baik | rusak_ringan | rusak_berat | afkir');
            $table->date('acquired_at')->nullable();
            $table->unsignedSmallInteger('inspection_interval_months')->nullable();
            $table->date('next_inspection_at')->nullable()->index();
            $table->boolean('needs_calibration')->default(false);
            $table->date('next_calibration_at')->nullable()->index();
            $table->string('status', 20)->default('aktif')->comment('aktif | nonaktif');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('facility_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained();
            $table->string('type', 20)->comment('pemeriksaan | kalibrasi | perbaikan | lainnya');
            $table->date('performed_at');
            $table->string('condition_after', 20)->nullable();
            $table->text('result');
            $table->unsignedBigInteger('cost')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('valid_until')->nullable()->comment('Masa berlaku kalibrasi');
            $table->foreignId('performed_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_class_id')->nullable()->constrained()->nullOnDelete();
            $table->string('class_code', 100)->nullable()->index();
            $table->string('survey_type', 30)->comment('kepuasan_peserta | evaluasi_trainer | evaluasi_klien');
            $table->date('responded_at')->index();
            $table->decimal('score', 3, 2)->comment('Skor keseluruhan 1–5');
            $table->json('aspects')->nullable()->comment('Skor per aspek (tanpa data pribadi)');
            $table->text('comment')->nullable();
            $table->string('subject')->nullable()->comment('Mis. nama trainer yang dievaluasi');
            $table->boolean('is_negative')->default(false)->index();
            $table->foreignId('complaint_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id', 100)->nullable()->unique()->comment('ID respons di LMS (mencegah impor ganda)');
            $table->foreignId('lms_import_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('objective')->nullable()->comment('Pernyataan sasaran mutu');
            $table->string('unit', 20)->default('%');
            $table->decimal('target', 12, 2);
            $table->string('comparator', 2)->default('>=')->comment('>= | <=');
            $table->string('source', 40)->default('manual')->comment('manual atau kunci perhitungan otomatis');
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('kpi_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_id')->constrained();
            $table->char('period', 7)->comment('YYYY-MM');
            $table->decimal('value', 12, 2);
            $table->boolean('is_auto')->default(false);
            $table->text('note')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['kpi_id', 'period']);
        });
    }

    public function down(): void
    {
        foreach (['kpi_values', 'kpis', 'survey_responses', 'facility_logs', 'facilities', 'personnel_documents', 'personnel',
            'quality_records', 'lms_imports', 'training_classes', 'supplier_evaluations', 'suppliers', 'training_programs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
