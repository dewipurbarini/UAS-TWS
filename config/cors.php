<?php

// Pengaturan CORS. Client tugas ini satu origin dengan API, tetapi path tetap dibuka
// agar fetch dari origin lain (misalnya port berbeda) tidak langsung ditolak browser.

return [
    // Tanda * artinya semua path, karena API tidak memakai awalan /api.
    'paths' => ['*'],

    // Semua method HTTP diizinkan (GET, POST, PUT, DELETE, OPTIONS).
    'allowed_methods' => ['*'],

    // Semua origin diizinkan. Tugas ini tidak memakai cookie lintas situs.
    'allowed_origins' => ['*'],

    // Tidak memakai pola regex tambahan.
    'allowed_origins_patterns' => [],

    // Header yang boleh dikirim browser, termasuk Authorization untuk JWT.
    'allowed_headers' => ['*'],

    // Header yang boleh dibaca JavaScript. Retry-After dipakai hitung mundur login.
    'exposed_headers' => ['Retry-After'],

    // Hasil preflight tidak di-cache lama.
    'max_age' => 0,

    // Cookie tidak ikut dikirim. Token dibawa lewat header Authorization.
    'supports_credentials' => false,
];
