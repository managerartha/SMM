<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Keluhan yang ditarik otomatis dari LMS: nomor tiket LMS (cegah dobel) & dicatat oleh sistem (tanpa pengguna). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('external_id', 100)->nullable()->unique()->after('class_code')->comment('Nomor tiket LMS, mis. lms:KLH-261106-4750B');
            $table->foreignId('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn('external_id');
        });
    }
};
