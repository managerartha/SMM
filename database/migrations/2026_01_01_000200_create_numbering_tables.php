<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penomoran otomatis.
 * - number_sequences: penghitung per cakupan (dikunci FOR UPDATE saat dipakai).
 * - issued_numbers: catatan permanen semua nomor yang pernah dikeluarkan (tidak pernah dipakai ulang).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key')->unique();
            $table->unsignedInteger('last_value')->default(0);
            $table->timestamps();
        });

        Schema::create('issued_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('object_type', 50);
            $table->string('number', 100);
            $table->string('scope_key');
            $table->unsignedInteger('sequence_value');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['object_type', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_numbers');
        Schema::dropIfExists('number_sequences');
    }
};
