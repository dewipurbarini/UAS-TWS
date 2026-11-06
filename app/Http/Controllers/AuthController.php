<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ApiResponse;
use App\Support\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Login dan data akun (/me).
 * Tidak mengurus CRUD pengumuman.
 */
class AuthController extends Controller
{
    /**
     * Hash bcrypt cadangan. Bukan password sungguhan.
     * Dipakai agar Hash::check tetap berjalan ketika username tidak ada,
     * sehingga waktu respons tidak membedakan "user tidak ada" dan "password salah".
     */
    private const HASH_DUMMY = '$2y$12$IUjVGWIZjU1O9TU7Z40REe6XyufGa.Z/3tBL.0t31W/0qxFe/bGga';

    /**
     * Pesan yang sama untuk username salah maupun password salah.
     */
    private const PESAN_LOGIN_SALAH = 'Username atau password salah';

    /**
     * POST /login.
     * Parameter: body JSON username dan password.
     * Kembalian: 200 + token, 401 bila salah, 422 bila kosong, 429 bila terlalu sering gagal.
     */
    public function login(Request $request): JsonResponse
    {
        // Null dari middleware ubah-string-kosong dijadikan string kosong.
        $username = trim((string) ($request->input('username') ?? ''));
        $password = (string) ($request->input('password') ?? '');

        // Field kosong ditolak sebagai validasi, bukan sebagai "password salah".
        if ($username === '' || $password === '') {
            $errors = [];
            if ($username === '') {
                $errors['username'] = ['Username wajib diisi'];
            }
            if ($password === '') {
                $errors['password'] = ['Password wajib diisi'];
            }

            return ApiResponse::gagal('Data login tidak lengkap', 422, $errors);
        }

        // Kunci pembatas: kombinasi IP dan username, sesuai soal.
        $kunci = 'login|'.$request->ip().'|'.$username;

        // Sudah 5 kali gagal dalam jendela 60 detik. Percobaan ke-6 ditolak.
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $detik = RateLimiter::availableIn($kunci);

            return ApiResponse::gagal(
                'Terlalu banyak percobaan login. Coba lagi dalam '.$detik.' detik.',
                429,
                null,
                ['Retry-After' => $detik]
            );
        }

        // where memakai binding (prepared statement). Tidak ada SQL yang disusun dari input mentah.
        $user = User::query()->where('username', $username)->first();

        // Jika user tidak ada, hash dummy tetap dicek. Jangan return lebih awal.
        $hash = $user?->password ?? self::HASH_DUMMY;
        $cocok = Hash::check($password, $hash);

        if ($user === null || $cocok === false) {
            // hit hanya saat gagal. Angka 60 adalah jendela waktu dalam detik.
            RateLimiter::hit($kunci, 60);

            return ApiResponse::gagal(self::PESAN_LOGIN_SALAH, 401);
        }

        // Login benar menghapus hitungan gagal untuk kombinasi IP + username ini.
        RateLimiter::clear($kunci);

        $token = app(JwtService::class)->buatToken($user);

        // Password tidak dimasukkan ke respons.
        return ApiResponse::sukses('Login berhasil', [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'nama' => $user->nama,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * GET /me.
     * Parameter: request yang sudah lolos jwt.auth.
     * Kembalian: id, username, nama, role, dan exp dari token.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $payload = $request->attributes->get('jwt_payload');

        return ApiResponse::sukses('Data akun berhasil diambil', [
            'id' => $user->id,
            'username' => $user->username,
            'nama' => $user->nama,
            'role' => $user->role,
            'exp' => $payload['exp'] ?? null,
        ]);
    }
}
