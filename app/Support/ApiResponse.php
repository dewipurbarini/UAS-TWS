<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Helper format JSON yang sama untuk seluruh API.
 * Fungsi: membungkus sukses dan gagal agar bentuk respons tidak beda-beda di tiap controller.
 * Parameter dan nilai kembalian dijelaskan di masing-masing method.
 */
class ApiResponse
{
    /**
     * Membuat respons sukses.
     * Parameter: $pesan teks untuk manusia, $data isi yang diminta, $status kode HTTP (200 atau 201).
     * Kembalian: JsonResponse dengan kunci sukses, pesan, dan data.
     */
    public static function sukses(string $pesan, mixed $data = null, int $status = 200): JsonResponse
    {
        // sukses true menandai operasi berhasil. Client membaca kunci ini, bukan hanya status HTTP.
        return response()->json([
            'sukses' => true,
            'pesan' => $pesan,
            'data' => $data,
        ], $status);
    }

    /**
     * Membuat respons gagal.
     * Parameter: $pesan teks error, $status kode HTTP, $errors daftar validasi (hanya untuk 422), $header header tambahan.
     * Kembalian: JsonResponse. Kunci errors hanya muncul bila memang ada.
     */
    public static function gagal(string $pesan, int $status = 400, ?array $errors = null, array $header = []): JsonResponse
    {
        // Bentuk dasar error. Tidak menyertakan detail SQL atau stack trace.
        $badan = [
            'sukses' => false,
            'pesan' => $pesan,
        ];

        // errors hanya untuk validasi 422, sesuai format soal.
        if ($errors !== null) {
            $badan['errors'] = $errors;
        }

        $respons = response()->json($badan, $status);

        // Header tambahan, misalnya Retry-After pada status 429.
        foreach ($header as $nama => $nilai) {
            $respons->header($nama, (string) $nilai);
        }

        return $respons;
    }
}
