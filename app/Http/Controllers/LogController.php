<?php

namespace App\Http\Controllers;

use App\Models\LogApi;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Melihat isi tabel log_api. Hanya admin.
 */
class LogController extends Controller
{
    /**
     * GET /log.
     * Parameter: query opsional limit (bilangan bulat positif, maksimal 200).
     * Kembalian: 200 dan daftar log terbaru. Limit yang aneh dikembalikan ke 50.
     */
    public function index(Request $request): JsonResponse
    {
        $mentah = $request->query('limit', 50);
        $limit = 50;

        // Hanya angka bulat positif yang diterima. Selain itu pakai default 50.
        if (is_string($mentah) || is_int($mentah)) {
            $teks = (string) $mentah;
            if (preg_match('/^[1-9][0-9]*$/', $teks) === 1) {
                $limit = (int) $teks;
            }
        }

        // Dibatasi agar satu request tidak menarik seluruh tabel bila sudah sangat panjang.
        if ($limit > 200) {
            $limit = 200;
        }

        $daftar = LogApi::query()
            ->orderByDesc('waktu')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return ApiResponse::sukses('Daftar log berhasil diambil', $daftar);
    }
}
