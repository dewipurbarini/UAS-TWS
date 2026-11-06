<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migration tabel users. Mengganti migration bawaan Laravel yang memakai email.
return new class extends Migration
{
    /**
     * Membuat tabel users, plus tabel bawaan sessions dan password_reset_tokens.
     * Dua tabel tambahan tetap ada agar fitur sesi Laravel tidak rusak, tetapi API ini tidak memakainya.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // Kunci utama, naik sendiri.
            $table->id();
            // Username unik, maksimal 50 karakter, sesuai soal.
            $table->string('username', 50)->unique();
            // Menyimpan hash bcrypt, bukan password asli. Panjang 255 cukup untuk bcrypt.
            $table->string('password', 255);
            // Nama tampilan, misalnya nama mahasiswa atau admin.
            $table->string('nama', 100);
            // Hanya dua peran yang sah.
            $table->enum('role', ['admin', 'user']);
            // Waktu baris dibuat. updated_at sengaja tidak dibuat.
            $table->timestamp('created_at')->nullable();
        });

        // Tabel ini bawaan Laravel. Dibiarkan agar perintah bawaan yang menyentuh reset password tidak error.
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Dipakai bila SESSION_DRIVER=database. API JWT tidak membaca tabel ini.
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Menghapus tabel bila migration di-rollback.
     */
    public function down(): void
    {
        // sessions dihapus lebih dulu karena punya relasi ke users.
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
