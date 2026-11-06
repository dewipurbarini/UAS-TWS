<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiResponse;
use App\Support\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Middleware jwt.auth.
 * Fungsi: memastikan ada token Bearer yang masih berlaku, lalu menyimpan akun ke request.
 * Bila gagal, respons 401. Role belum dicek di sini (itu tugas RoleAdmin).
 */
class JwtAuth
{
    /**
     * Parameter: $request, $next.
     * Kembalian: 401 atau response dari controller.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');

        // Tidak ada header sama sekali.
        if (trim($header) === '') {
            return ApiResponse::gagal('Token tidak ada', 401);
        }

        // Format wajib: "Bearer <token>". Kata Bearer diikuti satu spasi.
        if (! str_starts_with($header, 'Bearer ')) {
            return ApiResponse::gagal('Token tidak valid', 401);
        }

        $token = trim(substr($header, 7));
        if ($token === '') {
            return ApiResponse::gagal('Token tidak valid', 401);
        }

        try {
            $payload = app(JwtService::class)->verifikasi($token);
        } catch (Throwable $e) {
            // verifikasi memakai RuntimeException dengan pesan pendek, bukan pesan SQL.
            if ($e->getMessage() === 'kedaluwarsa') {
                return ApiResponse::gagal('Token kedaluwarsa', 401);
            }

            return ApiResponse::gagal('Token tidak valid', 401);
        }

        // Akun diambil lagi dari database supaya nama dan role yang dipakai adalah data terbaru.
        $user = User::query()->find($payload['sub']);
        if ($user === null) {
            return ApiResponse::gagal('Token tidak valid', 401);
        }

        // Disimpan di attributes, bukan di session. Controller dan log membaca dari sini.
        $request->attributes->set('auth_user', $user);
        $request->attributes->set('jwt_payload', $payload);

        return $next($request);
    }
}
