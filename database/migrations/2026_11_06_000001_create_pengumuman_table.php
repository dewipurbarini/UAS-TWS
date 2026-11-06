<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migration tabel pengumuman. Ini objek utama tugas, pengganti tabel mahasiswa.
return new class extends Migration
{
    /**
     * Membuat tabel pengumuman.
     */
    public function up(): void
    {
        Schema::create('pengumuman', function (Blueprint $table) {
            // Kunci utama.
            $table->id();
            // Kolom pertama objek. Unik, contoh INFO-001. Maksimal 20 karakter.
            $table->string('kode_info', 20)->unique();
            // Judul pengumuman yang tampil di client.
            $table->string('judul', 150);
            // Contoh: Akademik, Beasiswa, Kemahasiswaan, Umum, Fasilitas.
            $table->string('kategori', 50);
            // Tanggal pengumuman, tanpa jam.
            $table->date('tanggal');
            // Nama penulis pengumuman.
            $table->string('penulis', 100);
            // Kolom angka yang disyaratkan soal. Default 0, tidak boleh negatif lewat validasi.
            $table->integer('jumlah_dibaca')->default(0);
            // Waktu baris dibuat.
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Menghapus tabel pengumuman.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengumuman');
    }
};
