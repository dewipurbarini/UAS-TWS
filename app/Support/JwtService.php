<?php

namespace App\Support;

use App\Models\User;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use stdClass;
use Throwable;
use UnexpectedValueException;

/**
 * Layanan JWT HS256 memakai paket firebase/php-jwt.
 * Dipilih karena kecil, hanya mengurus token, dan algoritmanya bisa dijelaskan (HMAC SHA-256).
 * Tidak memakai Sanctum/Passport agar alurnya tetap terlihat di satu file.
 */
class JwtService
{
    /**
     * Membuat token untuk user yang sudah lolos cek password.
     * Parameter: $user baris akun dari database.
     * Kembalian: string token (header.payload.signature).
     */
    public function buatToken(User $user): string
    {
        // Kunci dibaca dari config, yang sumbernya .env (JWT_SECRET).
        $rahasia = (string) config('jwt.secret');

        // Tanpa kunci, tanda tangan tidak boleh dibuat. Dilempar agar menjadi error server generik.
        if ($rahasia === '') {
            throw new \RuntimeException('JWT_SECRET kosong');
        }

        $sekarang = time();
        // TTL dalam menit diubah ke detik. Minimal 1 menit agar token tidak langsung mati bila env salah isi 0.
        $menit = (int) config('jwt.ttl', 60);
        if ($menit < 1) {
            $menit = 60;
        }

        // Isi payload yang wajib: sub (id), username, role, exp. iat dan iss ditambah agar token lebih jelas.
        $payload = [
            'iss' => (string) config('jwt.issuer'),
            'iat' => $sekarang,
            'exp' => $sekarang + ($menit * 60),
            'sub' => (string) $user->id,
            'username' => $user->username,
            'role' => $user->role,
        ];

        // encode menempelkan header alg HS256 lalu menandatangani dengan kunci rahasia.
        return JWT::encode($payload, $rahasia, (string) config('jwt.algo', 'HS256'));
    }

    /**
     * Memeriksa token dan mengembalikan payload bila sah.
     * Parameter: $token string tanpa kata Bearer.
     * Kembalian: array dengan sub, username, role, exp.
     * Melempar RuntimeException dengan kode pesan: tidak_valid atau kedaluwarsa.
     */
    public function verifikasi(string $token): array
    {
        $rahasia = (string) config('jwt.secret');
        if ($rahasia === '') {
            throw new \RuntimeException('JWT_SECRET kosong');
        }

        try {
            // Key mengunci algoritma ke HS256, jadi token bertipe "none" atau RS256 ditolak.
            $payload = JWT::decode($token, new Key($rahasia, (string) config('jwt.algo', 'HS256')));
        } catch (ExpiredException) {
            // exp sudah lewat. Pesan ini yang nanti dikirim sebagai 401.
            throw new \RuntimeException('kedaluwarsa');
        } catch (SignatureInvalidException|BeforeValidException|UnexpectedValueException) {
            // Tanda tangan salah, format rusak, atau belum waktunya dipakai.
            throw new \RuntimeException('tidak_valid');
        } catch (Throwable) {
            // Error lain dari pustaka JWT tetap dianggap token tidak valid, bukan error SQL.
            throw new \RuntimeException('tidak_valid');
        }

        // Issuer harus sama dengan aplikasi ini.
        if (! $payload instanceof stdClass || ($payload->iss ?? '') !== (string) config('jwt.issuer')) {
            throw new \RuntimeException('tidak_valid');
        }

        // Klaim wajib harus ada. Kalau tidak, token tidak kita percaya.
        if (! isset($payload->sub, $payload->username, $payload->role, $payload->exp)) {
            throw new \RuntimeException('tidak_valid');
        }

        return [
            'sub' => (string) $payload->sub,
            'username' => (string) $payload->username,
            'role' => (string) $payload->role,
            'exp' => (int) $payload->exp,
        ];
    }
}
