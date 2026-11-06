<?php

// Aktifkan tipe ketat untuk payload token dan kunci rahasia.
declare(strict_types=1);

// Ubah data biner menjadi Base64 yang aman ditaruh di URL (pengganti + / =).
function jwtBase64Url(string $data): string
{
    // Encode biasa dulu, lalu ganti karakter yang bermasalah di URL, lalu buang padding "=".
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Balikkan Base64-URL menjadi teks asli.
function jwtBase64UrlDecode(string $data): string
{
    // Kembalikan alfabet Base64 standar.
    $data = strtr($data, '-_', '+/');
    // Hitung sisa pembagian 4 karena padding "=" tadi dibuang.
    $sisa = strlen($data) % 4;
    // Jika sisa bukan nol, tambahkan "=" sampai panjangnya kelipatan 4.
    if ($sisa !== 0) {
        // Tambah padding yang hilang.
        $data .= str_repeat('=', 4 - $sisa);
    }
    // Decode dan jika gagal jadikan string kosong agar pemanggil bisa menolak token.
    $hasil = base64_decode($data, true);
    // Kembalikan hasil decode atau string kosong bila Base64 rusak.
    return $hasil === false ? '' : $hasil;
}

// Buat token JWT HS256 yang berisi sub, username, role, dan exp.
function jwtBuat(array $muatan, string $kunci): string
{
    // Header memberitahu penerima bahwa algoritma yang dipakai HMAC SHA-256.
    $header = jwtBase64Url((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    // Bagian tengah adalah data pengguna yang dikodekan, bukan dienkripsi.
    $isi = jwtBase64Url((string) json_encode($muatan));
    // Tanda tangan dihitung dari "header.isi" memakai kunci rahasia server.
    $tanda = jwtBase64Url(hash_hmac('sha256', $header . '.' . $isi, $kunci, true));
    // Gabungkan tiga bagian dengan titik, sesuai format JWT.
    return $header . '.' . $isi . '.' . $tanda;
}

// Periksa tanda tangan dan masa berlaku, lalu kembalikan muatan token.
function jwtBaca(string $token, string $kunci): array
{
    // JWT yang sah selalu terdiri dari tepat tiga potong.
    $bagian = explode('.', $token);
    // Tolak token yang terpotong atau kelebihan titik.
    if (count($bagian) !== 3) {
        // Pesan ini ditangkap pemanggil dan diubah menjadi HTTP 401.
        throw new RuntimeException('Token tidak valid atau kedaluwarsa');
    }
    // Ambil header, muatan, dan tanda tangan ke variabel terpisah.
    [$header, $isi, $tanda] = $bagian;
    // Hitung ulang tanda tangan yang seharusnya dari header dan isi.
    $harapan = jwtBase64Url(hash_hmac('sha256', $header . '.' . $isi, $kunci, true));
    // Bandingkan dengan hash_equals agar waktu banding tidak membocorkan isi tanda tangan.
    if (!hash_equals($harapan, $tanda)) {
        // Tanda tangan beda berarti token diubah atau kunci tidak cocok.
        throw new RuntimeException('Token tidak valid atau kedaluwarsa');
    }
    // Kembalikan muatan dari Base64 menjadi array PHP.
    $muatan = json_decode(jwtBase64UrlDecode($isi), true);
    // Tolak bila JSON rusak atau bukan objek.
    if (!is_array($muatan)) {
        // Bentuk muatan tidak dikenali.
        throw new RuntimeException('Token tidak valid atau kedaluwarsa');
    }
    // Klaim exp wajib ada dan masih lebih besar daripada waktu sekarang.
    if (!isset($muatan['exp']) || (int) $muatan['exp'] < time()) {
        // Token sudah lewat masa berlakunya.
        throw new RuntimeException('Token tidak valid atau kedaluwarsa');
    }
    // Token lolos, kembalikan klaim sub, username, role, dan exp.
    return $muatan;
}
