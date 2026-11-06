<?php

// Tipe ketat untuk status HTTP, path, dan isi JSON.
declare(strict_types=1);

// Ambil alamat IP pengakses langsung dari koneksi, bukan dari header yang mudah dipalsukan.
function alamatIp(): string
{
    // REMOTE_ADDR diisi oleh Apache; jika aneh, pakai alamat kosong yang tetap tercatat.
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Catat satu baris ke tabel log_api sesuai ketentuan tugas.
function catatLog(string $metode, string $jalur, int $status, ?string $username, string $pesan): void
{
    // Siapkan pernyataan agar nilai pengguna tidak pernah menempel langsung di SQL.
    $perintah = koneksi()->prepare(
        'INSERT INTO log_api (waktu, method, path, status, ip, username, pesan)
         VALUES (NOW(), ?, ?, ?, ?, ?, ?)'
    );
    // Isi enam tanda tanya sesuai urutan kolom.
    $perintah->execute([$metode, $jalur, $status, alamatIp(), $username, $pesan]);
}

// Kirim JSON, catat log, lalu hentikan skrip agar tidak ada keluaran ganda.
function kirim(int $status, array $isi, ?string $username, string $pesan, array $headerTambahan = []): void
{
    // Log tidak boleh menggagalkan jawaban ke klien; kalau tabel bermasalah, jawaban tetap jalan.
    try {
        // Simpan method, path, status, IP, username, dan pesan.
        catatLog($_SERVER['REQUEST_METHOD'] ?? 'GET', $GLOBALS['jalur_log'], $status, $username, $pesan);
    } catch (Throwable $e) {
        // Sengaja diabaikan: pesan SQL tidak boleh ikut terkirim ke klien.
    }
    // Tetapkan kode status HTTP yang diminta pemanggil.
    http_response_code($status);
    // Beritahu klien bahwa tubuh respons adalah JSON beraksara UTF-8.
    header('Content-Type: application/json; charset=utf-8');
    // Pasang header tambahan, misalnya Retry-After saat login dibatasi.
    foreach ($headerTambahan as $nama => $nilai) {
        // Gabungkan nama dan nilai menjadi satu header HTTP.
        header($nama . ': ' . $nilai);
    }
    // Ubah array menjadi teks JSON tanpa mengubah huruf Indonesia menjadi kode \u.
    echo json_encode($isi, JSON_UNESCAPED_UNICODE);
    // Berhenti di sini supaya router tidak memproses apa pun lagi.
    exit;
}

// Baca tubuh permintaan sebagai array, atau null bila JSON-nya rusak.
function bacaJson(): ?array
{
    // php://input adalah aliran mentah; PHP tidak mengisi $_POST untuk application/json.
    $mentah = file_get_contents('php://input');
    // Tubuh kosong dianggap objek kosong yang sah, misalnya tidak terjadi pada POST normal.
    if ($mentah === false || trim($mentah) === '') {
        // Kembalikan array kosong agar validasi yang menolak field wajib.
        return [];
    }
    // Urai teks menjadi array asosiatif.
    $data = json_decode($mentah, true);
    // json_decode mengembalikan null jika formatnya rusak.
    if (!is_array($data)) {
        // Pembeda ini membuat pemanggil bisa membalas 422.
        return null;
    }
    // JSON valid dikembalikan apa adanya.
    return $data;
}

// Ambil token dari header Authorization: Bearer.
function ambilBearer(): ?string
{
    // Apache biasanya menyalin header ini ke variabel server.
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    // Sebagian konfigurasi Apache hanya menyimpan salinan lewat RewriteRule.
    if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        // Pakai salinan yang dipertahankan .htaccess.
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    // Cocokkan awalan Bearer lalu ambil tokennya, tanpa spasi.
    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $cocok)) {
        // Indeks 1 adalah isi kurung tangkap pada pola regex.
        return $cocok[1];
    }
    // Tidak ada header yang sah.
    return null;
}

// Wajibkan token yang masih berlaku; kalau gagal, fungsi ini langsung mengirim 401.
function wajibLogin(): array
{
    // Baca konfigurasi untuk mengambil kunci JWT.
    $konfigurasi = require dirname(__DIR__) . '/config.php';
    // Ambil string token dari header.
    $token = ambilBearer();
    // Tanpa token, permintaan tidak boleh masuk ke data.
    if ($token === null) {
        // 401 berarti klien belum membuktikan siapa dirinya.
        kirim(401, ['pesan' => 'Token tidak ditemukan'], null, 'Token tidak ditemukan');
    }
    // Dekode dan periksa tanda tangan serta masa berlaku.
    try {
        // jwtBaca melempar pengecualian bila token rusak atau kedaluwarsa.
        $muatan = jwtBaca($token, $konfigurasi['kunci_jwt']);
    } catch (RuntimeException $e) {
        // Pesan untuk token rusak dan kedaluwarsa sengaja sama.
        kirim(401, ['pesan' => 'Token tidak valid atau kedaluwarsa'], null, 'Token ditolak');
    }
    // Pastikan klaim minimum yang diminta tugas ada di dalam token.
    foreach (['sub', 'username', 'role'] as $klaim) {
        // Klaim kosong berarti token tidak lengkap.
        if (!isset($muatan[$klaim]) || $muatan[$klaim] === '') {
            // Tolak dengan status yang sama seperti token rusak.
            kirim(401, ['pesan' => 'Token tidak valid atau kedaluwarsa'], null, 'Klaim token tidak lengkap');
        }
    }
    // Kembalikan identitas yang sudah terpercaya ke router.
    return $muatan;
}

// Hanya admin yang boleh menambah, mengubah, menghapus, dan membaca log.
function wajibAdmin(array $pengguna): void
{
    // Role selain admin ditolak meskipun tokennya sah.
    if (($pengguna['role'] ?? '') !== 'admin') {
        // 403 berarti identitas diketahui, tetapi haknya tidak cukup.
        kirim(403, ['pesan' => 'Hanya admin yang boleh melakukan aksi ini'], $pengguna['username'], 'Akses ditolak');
    }
}

// Ubah id di URL menjadi bilangan bulat positif, atau kirim 400 bila tidak sah.
function idDariJalur(string $id): int
{
    // Hanya deretan angka yang diterima; "1abc" dan "-1" ditolak.
    if (!preg_match('/^[1-9][0-9]*$/', $id)) {
        // 400 khusus untuk id yang bentuknya tidak valid.
        kirim(400, ['pesan' => 'Id tidak valid'], $GLOBALS['pengguna_log'], 'Id tidak valid');
    }
    // Ubah teks angka menjadi integer.
    return (int) $id;
}

// Periksa tubuh pengumuman dan kembalikan daftar kesalahan per kolom.
function validasiPengumuman(array $data): array
{
    // Siapkan wadah pesan; kosong berarti data layak disimpan.
    $salah = [];
    // Ambil kode dan buang spasi di ujung.
    $kode = trim((string) ($data['kode_info'] ?? ''));
    // Kode wajib huruf besar, angka, atau tanda hubung, panjang 3 sampai 20.
    if (!preg_match('/^[A-Z0-9-]{3,20}$/', $kode)) {
        // Pesan ini yang ditampilkan client pada field kode.
        $salah['kode_info'] = 'Kode info wajib 3-20 karakter: huruf besar, angka, atau tanda hubung';
    }
    // Ambil judul tanpa spasi pinggir.
    $judul = trim((string) ($data['judul'] ?? ''));
    // Judul terlalu pendek tidak cukup menjelaskan pengumuman.
    if (mb_strlen($judul) < 5 || mb_strlen($judul) > 150) {
        // Batas atas mengikuti lebar kolom di database.
        $salah['judul'] = 'Judul wajib 5 sampai 150 karakter';
    }
    // Daftar kategori yang diizinkan server, tidak percaya pada pilihan di browser.
    $kategoriSah = ['Akademik', 'Kemahasiswaan', 'Beasiswa', 'Umum'];
    // Ambil kategori apa adanya karena nilainya harus persis salah satu daftar.
    $kategori = (string) ($data['kategori'] ?? '');
    // Tolak kategori di luar daftar.
    if (!in_array($kategori, $kategoriSah, true)) {
        // Sertakan pilihan yang benar agar pengguna tahu isi yang diharapkan.
        $salah['kategori'] = 'Kategori harus Akademik, Kemahasiswaan, Beasiswa, atau Umum';
    }
    // Ambil tanggal dalam bentuk teks.
    $tanggal = (string) ($data['tanggal'] ?? '');
    // Pola YYYY-MM-DD belum menjamin tanggalnya ada, jadi dicek lagi.
    $objekTanggal = DateTime::createFromFormat('Y-m-d', $tanggal);
    // Format harus cocok dan tanggal seperti 2026-02-31 harus gagal.
    if (!$objekTanggal || $objekTanggal->format('Y-m-d') !== $tanggal) {
        // Pesan untuk input tanggal yang tidak nyata.
        $salah['tanggal'] = 'Tanggal wajib format YYYY-MM-DD yang benar';
    }
    // Ambil nama penulis.
    $penulis = trim((string) ($data['penulis'] ?? ''));
    // Nama yang terlalu pendek atau terlalu panjang ditolak.
    if (mb_strlen($penulis) < 3 || mb_strlen($penulis) > 100) {
        // Pesan batas panjang penulis.
        $salah['penulis'] = 'Penulis wajib 3 sampai 100 karakter';
    }
    // Prioritas adalah kolom angka wajib pada tugas ini.
    $prioritas = $data['prioritas'] ?? null;
    // Terima angka 1 sampai 5, termasuk angka yang terkirim sebagai teks "3".
    if (!is_numeric($prioritas) || (int) $prioritas < 1 || (int) $prioritas > 5 || (string) (int) $prioritas !== (string) $prioritas) {
        // Tolak desimal seperti 1.5 dan nilai di luar rentang.
        $salah['prioritas'] = 'Prioritas wajib bilangan bulat 1 sampai 5';
    }
    // Ringkasan membuat kartu pengumuman punya isi, bukan judul saja.
    $ringkasan = trim((string) ($data['ringkasan'] ?? ''));
    // Batasi panjang agar muat di kolom VARCHAR.
    if (mb_strlen($ringkasan) < 10 || mb_strlen($ringkasan) > 255) {
        // Pesan bila ringkasan kosong atau kepanjangan.
        $salah['ringkasan'] = 'Ringkasan wajib 10 sampai 255 karakter';
    }
    // Kembalikan semua kesalahan sekaligus, bukan satu per satu.
    return $salah;
}

// Rapikan data yang sudah lolos validasi agar tipe kolomnya konsisten.
function dataBersih(array $data): array
{
    // Kembalikan hanya kolom yang boleh ditulis ke tabel.
    return [
        // Kode tanpa spasi.
        'kode_info' => trim((string) $data['kode_info']),
        // Judul tanpa spasi.
        'judul' => trim((string) $data['judul']),
        // Kategori yang sudah pasti ada di daftar.
        'kategori' => (string) $data['kategori'],
        // Tanggal yang sudah pasti nyata.
        'tanggal' => (string) $data['tanggal'],
        // Nama penulis tanpa spasi pinggir.
        'penulis' => trim((string) $data['penulis']),
        // Prioritas dipaksa menjadi integer.
        'prioritas' => (int) $data['prioritas'],
        // Ringkasan tanpa spasi pinggir.
        'ringkasan' => trim((string) $data['ringkasan']),
    ];
}
