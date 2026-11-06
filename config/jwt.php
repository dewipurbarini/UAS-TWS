<?php

// File konfigurasi JWT. Dibaca lewat config('jwt.xxx'), bukan env() langsung di controller,
// supaya nilai tetap ada walaupun nanti konfigurasi di-cache.

return [
    // Kunci rahasia untuk tanda tangan HS256. Wajib diisi di .env, jangan dipakai nilai kosong.
    'secret' => env('JWT_SECRET'),

    // Masa berlaku token dalam menit. Default 60 menit bila .env tidak mengisi.
    'ttl' => (int) env('JWT_TTL_MINUTES', 60),

    // Algoritma yang dipakai. HS256 = HMAC SHA-256 dengan satu kunci rahasia.
    'algo' => 'HS256',

    // Nama penerbit token. Disimpan di payload agar token dari aplikasi lain ditolak.
    'issuer' => env('JWT_ISSUER', 'pengumuman-kampus'),
];
