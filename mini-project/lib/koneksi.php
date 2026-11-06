<?php

// Mengunci pemeriksaan tipe agar kesalahan jenis data ketahuan lebih awal.
declare(strict_types=1);

// Menyimpan satu koneksi PDO agar tidak membuka MySQL berulang dalam satu request.
function koneksi(): PDO
{
    // Variabel statis tetap hidup selama request yang sama.
    static $pdo = null;

    // Kalau koneksi sudah ada, pakai lagi supaya tidak membuat koneksi baru.
    if ($pdo instanceof PDO) {
        // Kembalikan objek yang sudah tersimpan.
        return $pdo;
    }

    // Baca array pengaturan dari config.php, satu tingkat di atas folder lib.
    $konfigurasi = require dirname(__DIR__) . '/config.php';

    // Susun DSN: driver, host, nama database, dan charset agar huruf Indonesia aman.
    $dsn = 'mysql:host=' . $konfigurasi['host']
        . ';dbname=' . $konfigurasi['nama_db']
        . ';charset=utf8mb4';

    // Buat objek PDO dengan username, sandi, dan opsi keamanan.
    $pdo = new PDO($dsn, $konfigurasi['pengguna'], $konfigurasi['sandi'], [
        // Lempar pengecualian jika query gagal, jangan diam-diam mengembalikan false.
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Hasil query berupa array asosiatif: nama kolom menjadi kunci.
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Matikan emulate prepare agar MySQL benar-benar memakai prepared statement.
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Samakan jam MySQL dengan zona yang dipakai PHP untuk menghitung Retry-After.
    $pdo->exec("SET time_zone = '+07:00'");

    // Simpan sekaligus kembalikan koneksi tersebut.
    return $pdo;
}
