<?php

namespace Database\Seeders;

use App\Models\Pengumuman;
use Illuminate\Database\Seeder;

/**
 * Delapan pengumuman awal. Soal meminta minimal lima.
 * Topik mengikuti contoh: UTS, beasiswa, libur, jaringan, lomba, wisuda, perpustakaan, KRS.
 */
class PengumumanSeeder extends Seeder
{
    /**
     * Mengisi tabel pengumuman.
     */
    public function run(): void
    {
        $baris = [
            [
                'kode_info' => 'INFO-001',
                'judul' => 'Jadwal UTS Semester Ganjil',
                'kategori' => 'Akademik',
                'tanggal' => '2026-10-06',
                'penulis' => 'Biro Akademik',
                'jumlah_dibaca' => 120,
            ],
            [
                'kode_info' => 'INFO-002',
                'judul' => 'Pembukaan Beasiswa Prestasi',
                'kategori' => 'Beasiswa',
                'tanggal' => '2026-10-12',
                'penulis' => 'Bagian Kemahasiswaan',
                'jumlah_dibaca' => 86,
            ],
            [
                'kode_info' => 'INFO-003',
                'judul' => 'Libur Nasional Maulid Nabi',
                'kategori' => 'Umum',
                'tanggal' => '2026-09-05',
                'penulis' => 'Rektorat',
                'jumlah_dibaca' => 210,
            ],
            [
                'kode_info' => 'INFO-004',
                'judul' => 'Pemeliharaan Jaringan Kampus',
                'kategori' => 'Fasilitas',
                'tanggal' => '2026-10-20',
                'penulis' => 'UPT TIK',
                'jumlah_dibaca' => 54,
            ],
            [
                'kode_info' => 'INFO-005',
                'judul' => 'Lomba Karya Tulis Kemahasiswaan',
                'kategori' => 'Kemahasiswaan',
                'tanggal' => '2026-11-01',
                'penulis' => 'UKM Penalaran',
                'jumlah_dibaca' => 73,
            ],
            [
                'kode_info' => 'INFO-006',
                'judul' => 'Pendaftaran Wisuda Periode November',
                'kategori' => 'Akademik',
                'tanggal' => '2026-10-28',
                'penulis' => 'Biro Akademik',
                'jumlah_dibaca' => 145,
            ],
            [
                'kode_info' => 'INFO-007',
                'judul' => 'Perubahan Jam Layanan Perpustakaan',
                'kategori' => 'Fasilitas',
                'tanggal' => '2026-09-15',
                'penulis' => 'Perpustakaan Pusat',
                'jumlah_dibaca' => 39,
            ],
            [
                'kode_info' => 'INFO-008',
                'judul' => 'Pengisian KRS Semester Genap',
                'kategori' => 'Akademik',
                'tanggal' => '2026-11-10',
                'penulis' => 'Biro Akademik',
                'jumlah_dibaca' => 98,
            ],
        ];

        foreach ($baris as $item) {
            // updateOrCreate memakai binding. Aman dijalankan ulang tanpa menduplikasi kode_info.
            Pengumuman::query()->updateOrCreate(
                ['kode_info' => $item['kode_info']],
                $item
            );
        }
    }
}
