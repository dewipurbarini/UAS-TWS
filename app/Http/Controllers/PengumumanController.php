<?php

namespace App\Http\Controllers;

use App\Models\Pengumuman;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * CRUD pengumuman kampus.
 * Semua query lewat Eloquent, yang memakai PDO prepared statement.
 */
class PengumumanController extends Controller
{
    /**
     * GET /pengumuman. Boleh diakses admin dan user.
     * Kembalian: 200 dan array pengumuman, urutan tanggal terbaru.
     */
    public function index(): JsonResponse
    {
        $daftar = Pengumuman::query()
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::sukses('Daftar pengumuman berhasil diambil', $daftar);
    }

    /**
     * GET /pengumuman/{id}.
     * Parameter: $id dari URL, sengaja string agar "abc" tidak dipaksa jadi 404 oleh Laravel.
     * Kembalian: 200, 400 bila id bukan bilangan bulat positif, 404 bila data tidak ada.
     */
    public function show(string $id): JsonResponse
    {
        $tolak = $this->tolakIdTidakValid($id);
        if ($tolak !== null) {
            return $tolak;
        }

        $item = Pengumuman::query()->find($id);
        if ($item === null) {
            return ApiResponse::gagal('Pengumuman tidak ditemukan', 404);
        }

        return ApiResponse::sukses('Detail pengumuman berhasil diambil', $item);
    }

    /**
     * POST /pengumuman. Hanya admin (dicek middleware).
     * Kembalian: 201, 409 bila kode_info kembar, 422 bila validasi gagal.
     */
    public function store(Request $request): JsonResponse
    {
        $hasil = $this->validasi($request);
        if ($hasil instanceof JsonResponse) {
            return $hasil;
        }

        // Cek lebih dulu agar klien dapat 409 yang jelas, bukan error SQL.
        if (Pengumuman::query()->where('kode_info', $hasil['kode_info'])->exists()) {
            return ApiResponse::gagal('Kode info sudah dipakai', 409);
        }

        try {
            $baru = Pengumuman::query()->create($hasil);
        } catch (QueryException $e) {
            // Cadangan bila dua request bersamaan lolos cek di atas. 23000 = pelanggaran unique.
            if (($e->errorInfo[0] ?? '') === '23000') {
                return ApiResponse::gagal('Kode info sudah dipakai', 409);
            }
            throw $e;
        }

        return ApiResponse::sukses('Pengumuman berhasil ditambah', $baru, 201);
    }

    /**
     * PUT /pengumuman/{id}. Hanya admin.
     * Kembalian: 200, 400, 404, 409, atau 422.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tolak = $this->tolakIdTidakValid($id);
        if ($tolak !== null) {
            return $tolak;
        }

        $item = Pengumuman::query()->find($id);
        if ($item === null) {
            return ApiResponse::gagal('Pengumuman tidak ditemukan', 404);
        }

        $hasil = $this->validasi($request);
        if ($hasil instanceof JsonResponse) {
            return $hasil;
        }

        // Kode yang sama dengan baris ini sendiri bukan bentrok.
        $kembar = Pengumuman::query()
            ->where('kode_info', $hasil['kode_info'])
            ->where('id', '!=', $item->id)
            ->exists();
        if ($kembar) {
            return ApiResponse::gagal('Kode info sudah dipakai', 409);
        }

        try {
            $item->update($hasil);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? '') === '23000') {
                return ApiResponse::gagal('Kode info sudah dipakai', 409);
            }
            throw $e;
        }

        return ApiResponse::sukses('Pengumuman berhasil diubah', $item->fresh());
    }

    /**
     * DELETE /pengumuman/{id}. Hanya admin.
     * Kembalian: 200 bila terhapus, 400 bila id jelek, 404 bila tidak ada.
     */
    public function destroy(string $id): JsonResponse
    {
        $tolak = $this->tolakIdTidakValid($id);
        if ($tolak !== null) {
            return $tolak;
        }

        $item = Pengumuman::query()->find($id);
        if ($item === null) {
            return ApiResponse::gagal('Pengumuman tidak ditemukan', 404);
        }

        $idTerhapus = $item->id;
        $item->delete();

        return ApiResponse::sukses('Pengumuman berhasil dihapus', ['id' => $idTerhapus]);
    }

    /**
     * Menolak id yang bukan bilangan bulat positif.
     * Parameter: $id mentah dari URL.
     * Kembalian: response 400, atau null bila id boleh dipakai.
     */
    private function tolakIdTidakValid(string $id): ?JsonResponse
    {
        // 0, -1, 1.5, dan abc ditolak. Tidak memakai findOrFail agar bukan 404.
        if (preg_match('/^[1-9][0-9]*$/', $id) !== 1) {
            return ApiResponse::gagal('Id tidak valid', 400);
        }

        return null;
    }

    /**
     * Validasi input tambah dan ubah.
     * Parameter: request JSON.
     * Kembalian: array data bersih, atau response 422.
     */
    private function validasi(Request $request): array|JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'kode_info' => ['required', 'string', 'max:20'],
                'judul' => ['required', 'string', 'max:150'],
                'kategori' => ['required', 'string', 'max:50'],
                'tanggal' => ['required', 'date_format:Y-m-d'],
                'penulis' => ['required', 'string', 'max:100'],
                'jumlah_dibaca' => ['required', 'integer', 'min:0'],
            ],
            [
                'kode_info.required' => 'Kode info wajib diisi',
                'kode_info.max' => 'Kode info maksimal 20 karakter',
                'judul.required' => 'Judul wajib diisi',
                'judul.max' => 'Judul maksimal 150 karakter',
                'kategori.required' => 'Kategori wajib diisi',
                'kategori.max' => 'Kategori maksimal 50 karakter',
                'tanggal.required' => 'Tanggal wajib diisi',
                'tanggal.date_format' => 'Tanggal harus berformat YYYY-MM-DD',
                'penulis.required' => 'Penulis wajib diisi',
                'penulis.max' => 'Penulis maksimal 100 karakter',
                'jumlah_dibaca.required' => 'Jumlah dibaca wajib diisi',
                'jumlah_dibaca.integer' => 'Jumlah dibaca harus berupa angka bulat',
                'jumlah_dibaca.min' => 'Jumlah dibaca tidak boleh negatif',
            ]
        );

        if ($validator->fails()) {
            // toArray() bentuknya { "kolom": ["pesan"] }, sesuai soal.
            return ApiResponse::gagal('Data tidak valid', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();
        // Disimpan sebagai integer, bukan string.
        $data['jumlah_dibaca'] = (int) $data['jumlah_dibaca'];

        return $data;
    }
}
