<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migration tabel log_api. Diisi middleware, bukan oleh pengguna lewat form.
return new class extends Migration
{
    /**
     * Membuat tabel log_api.
     */
    public function up(): void
    {
        Schema::create('log_api', function (Blueprint $table) {
            $table->id();
            // Waktu request selesai dicatat.
            $table->dateTime('waktu');
            // GET, POST, PUT, DELETE, dan seterusnya.
            $table->string('method', 10);
            // Path seperti /login atau /pengumuman/1, tanpa query string.
            $table->string('path', 255);
            // Kode HTTP akhir, misalnya 200 atau 401.
            $table->smallInteger('status');
            // IPv4 atau IPv6. Panjang 45 cukup untuk IPv6.
            $table->string('ip', 45);
            // Null bila request belum punya identitas (token tidak sah atau bukan login).
            $table->string('username', 50)->nullable();
            // Cuplikan field pesan dari JSON respons.
            $table->string('pesan', 255);
            // Index agar urutan terbaru di GET /log tidak memindai seluruh tabel.
            $table->index('waktu');
        });
    }

    /**
     * Menghapus tabel log.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_api');
    }
};
