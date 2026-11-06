<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware role.admin.
 * Fungsi: menolak akun yang role-nya bukan admin.
 * Harus dipasang setelah jwt.auth, karena butuh akun yang sudah diletakkan di request.
 */
class RoleAdmin
{
    /**
     * Parameter: $request, $next.
     * Kembalian: 403 bila bukan admin, selain itu response berikutnya.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('auth_user');

        // Role dibaca dari database (lewat jwt.auth), bukan hanya dari teks di token.
        if ($user === null || $user->role !== 'admin') {
            return ApiResponse::gagal('Akses ditolak: hanya admin', 403);
        }

        return $next($request);
    }
}
