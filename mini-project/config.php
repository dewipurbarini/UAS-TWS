<?php

// Baris ini menyatakan bahwa file wajib memakai sintaks PHP modern.
declare(strict_types=1);

// Mengembalikan satu array pengaturan agar file lain tinggal memanggil require.
return [
    // Alamat server MySQL di komputer sendiri (XAMPP).
    'host' => '127.0.0.1',
    // Nama basis data khusus objek Pengumuman Kampus.
    'nama_db' => 'pengumuman_kampus',
    // Pengguna bawaan XAMPP.
    'pengguna' => 'root',
    // Sandi root XAMPP memang kosong.
    'sandi' => '',
    // Kunci rahasia HMAC milik sendiri; jangan memakai contoh dari praktikum.
    'kunci_jwt' => 'dewipurbarini-23676003-rahasia-jwt-pengumuman-kampus',
    // Masa berlaku token dalam detik: 2 jam.
    'masa_token' => 7200,
    // Jumlah gagal login yang masih ditoleransi dalam satu jendela waktu.
    'maks_gagal' => 5,
    // Jendela hitung gagal login, dalam detik (10 menit).
    'jendela_gagal' => 600,
];
