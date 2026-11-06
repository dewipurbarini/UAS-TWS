<?php

namespace App\Http\Middleware;

use App\Models\LogApi as CatatanLog;
use App\Support\JwtService;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Mencatat setiap request API ke tabel log_api.
 * Dipasang paling luar agar status akhir (401, 403, 404, 422, 429, 500) ikut tersimpan.
 * Kegagalan menulis log tidak boleh menggagalkan respons ke klien.
 */
class LogApi
{
    /**
     * Menjalankan request, lalu menulis log setelah respons terbentuk.
     * Parameter: $request, $next.
     * Kembalian: response yang sama dengan yang akan dikirim ke klien.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // next() menjalankan middleware dalam dan controller. Hasilnya sudah berisi status akhir.
            $respons = $next($request);
        } catch (Throwable $e) {
            // Exception (misalnya database mati) diubah dulu menjadi JSON, baru dicatat.
            $respons = $this->renderAman($request, $e);
        }

        // Halaman web bawaan tidak dicatat supaya tabel log hanya berisi lalu lintas API.
        if (! $request->is('/') && ! $request->is('up')) {
            $this->catat($request, $respons);
        }

        return $respons;
    }

    /**
     * Mengubah exception menjadi response lewat handler aplikasi.
     * Parameter: $request, $e exception yang tertangkap.
     * Kembalian: response JSON generik bila handler ikut gagal.
     */
    private function renderAman(Request $request, Throwable $e): Response
    {
        try {
            // Error server dicatat di file log Laravel untuk pengembang, tidak dikirim ke klien.
            if (! $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface || $e->getStatusCode() >= 500) {
                report($e);
            }
        } catch (Throwable) {
            // report() gagal pun tidak boleh menghentikan respons.
        }

        try {
            return app(ExceptionHandler::class)->render($request, $e);
        } catch (Throwable) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Terjadi kesalahan pada server',
            ], 500);
        }
    }

    /**
     * Menyimpan satu baris log.
     * Parameter: request dan response akhir.
     * Kembalian: tidak ada. Error penulisan ditelan.
     */
    private function catat(Request $request, Response $respons): void
    {
        try {
            CatatanLog::create([
                'waktu' => now(),
                'method' => $request->method(),
                // path() tanpa garis miring di depan, jadi ditambah agar sama dengan contoh /login.
                'path' => '/'.$request->path(),
                'status' => $respons->getStatusCode(),
                'ip' => (string) $request->ip(),
                'username' => $this->username($request),
                'pesan' => $this->pesan($respons),
            ]);
        } catch (Throwable) {
            // Sengaja kosong: log gagal (misalnya database mati) tidak mengubah respons API.
        }
    }

    /**
     * Menentukan username yang dicatat.
     * Urutan: user dari token yang sah, lalu username di body /login, selain itu null.
     * Password dan token tidak pernah dibaca untuk disimpan.
     */
    private function username(Request $request): ?string
    {
        $akun = $request->attributes->get('auth_user');
        if ($akun !== null && ! empty($akun->username)) {
            return mb_substr((string) $akun->username, 0, 50);
        }

        // Bila middleware auth tidak sempat jalan (misalnya method tidak diizinkan),
        // username tetap diambil dari token yang masih sah. Token itu sendiri tidak disimpan.
        $header = (string) $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            try {
                $payload = app(JwtService::class)->verifikasi(trim(substr($header, 7)));

                return mb_substr($payload['username'], 0, 50);
            } catch (Throwable) {
                // Token rusak atau kedaluwarsa: username tetap kosong, kecuali aturan login di bawah.
            }
        }

        // Hanya /login yang boleh mengambil username dari body. Endpoint lain tanpa token tetap null.
        if ($request->is('login')) {
            $dariBody = trim((string) ($request->input('username') ?? ''));
            if ($dariBody !== '') {
                return mb_substr($dariBody, 0, 50);
            }
        }

        return null;
    }

    /**
     * Mengambil field pesan dari JSON respons, dipotong 255 karakter.
     */
    private function pesan(Response $respons): string
    {
        $json = json_decode($respons->getContent(), true);
        $pesan = is_array($json) ? (string) ($json['pesan'] ?? '') : '';

        return mb_substr($pesan, 0, 255);
    }
}
