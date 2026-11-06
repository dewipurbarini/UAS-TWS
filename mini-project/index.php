<?php

// Semua file API memakai pemeriksaan tipe yang sama.
declare(strict_types=1);

// Samakan waktu token dan jendela login dengan zona kampus.
date_default_timezone_set('Asia/Jakarta');

// Muat koneksi database, pembuat token, dan fungsi pembantu.
require __DIR__ . '/lib/koneksi.php';
require __DIR__ . '/lib/jwt.php';
require __DIR__ . '/lib/bantu.php';

// Path yang dicatat di log; diisi setelah URL dibersihkan.
$GLOBALS['jalur_log'] = '/';
// Username untuk log; tetap null sampai token atau login berhasil dikenali.
$GLOBALS['pengguna_log'] = null;

// Ambil jalur tanpa query string.
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
// Folder tempat index.php berada, misalnya /UAS-TWS/mini-project.
$dasar = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
// Buang awalan folder supaya yang tersisa hanya jalur API, misalnya /pengumuman/1.
$jalur = $uri;
// Hanya potong bila URI benar-benar diawali folder proyek.
if ($dasar !== '' && str_starts_with($uri, $dasar)) {
    // Sisa setelah nama folder adalah jalur yang dipakai router.
    $jalur = substr($uri, strlen($dasar));
}
// Pastikan selalu diawali garis miring dan tanpa garis miring di ujung.
$jalur = '/' . trim((string) $jalur, '/');
// Akar menjadi "/" bukan string kosong.
if ($jalur === '/') {
    // Biarkan penanda akar apa adanya.
    $jalur = '/';
}
// Simpan jalur bersih agar fungsi kirim menulis path yang sama ke log.
$GLOBALS['jalur_log'] = $jalur;
// Metode HTTP dalam huruf besar: GET, POST, PUT, atau DELETE.
$metode = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Membuka folder proyek di browser langsung diarahkan ke halaman klien.
if (($jalur === '/' || $jalur === '/index.php') && $metode === 'GET') {
    // Catat kunjungan singkat sebelum pindah halaman.
    try {
        // Status 302 menandai pengalihan, bukan data API.
        catatLog($metode, $jalur, 302, null, 'Alihkan ke klien');
    } catch (Throwable $e) {
        // Kegagalan log tidak boleh menghalangi pengguna membuka halaman.
    }
    // Pindahkan browser ke antarmuka web.
    header('Location: client/index.html');
    // Beri kode pengalihan yang sesuai.
    http_response_code(302);
    // Berhenti agar tidak ikut memproses router API.
    exit;
}

// Seluruh API dibungkus agar kesalahan SQL tidak ikut tampil ke klien.
try {
    // Cabang menurut metode dan jalur.
    if ($metode === 'POST' && $jalur === '/login') {
        // Proses autentikasi dan, bila lolos, keluarkan JWT.
        tanganiLogin();
    }

    // Semua jalur di bawah ini mensyaratkan token yang masih berlaku.
    $pengguna = wajibLogin();
    // Simpan username untuk log pada permintaan yang sudah dikenal.
    $GLOBALS['pengguna_log'] = $pengguna['username'];

    // Identitas pemilik token.
    if ($metode === 'GET' && $jalur === '/me') {
        // Kembalikan klaim yang sudah diperiksa, tanpa membuat query ulang yang membocorkan sandi.
        kirim(200, [
            'id' => (int) $pengguna['sub'],
            'username' => $pengguna['username'],
            'role' => $pengguna['role'],
        ], $pengguna['username'], 'Profil berhasil dibaca');
    }

    // Daftar pengumuman boleh dibaca admin maupun user.
    if ($metode === 'GET' && $jalur === '/pengumuman') {
        // Ambil semua baris, yang terbaru di atas.
        $perintah = koneksi()->query(
            'SELECT id, kode_info, judul, kategori, tanggal, penulis, prioritas, ringkasan, created_at
             FROM pengumuman ORDER BY tanggal DESC, id DESC'
        );
        // fetchAll aman di sini karena tidak ada input pengguna di dalam SQL.
        kirim(200, ['data' => $perintah->fetchAll()], $pengguna['username'], 'Daftar pengumuman');
    }

    // Detail, ubah, dan hapus memakai pola /pengumuman/{id}.
    if (preg_match('#^/pengumuman/([^/]+)$#', $jalur, $cocok)) {
        // Ubah potongan URL menjadi id, atau langsung 400 bila bentuknya salah.
        $id = idDariJalur($cocok[1]);
        // Baca satu baris.
        if ($metode === 'GET') {
            // Kirim detail atau 404.
            kirimDetail($id, $pengguna['username']);
        }
        // Ubah baris hanya untuk admin.
        if ($metode === 'PUT') {
            // Cek role sebelum menyentuh database.
            wajibAdmin($pengguna);
            // Lanjutkan proses ubah.
            ubahPengumuman($id, $pengguna['username']);
        }
        // Hapus baris hanya untuk admin.
        if ($metode === 'DELETE') {
            // User biasa berhenti di 403.
            wajibAdmin($pengguna);
            // Lanjutkan proses hapus.
            hapusPengumuman($id, $pengguna['username']);
        }
    }

    // Tambah pengumuman hanya untuk admin.
    if ($metode === 'POST' && $jalur === '/pengumuman') {
        // Tolak role user.
        wajibAdmin($pengguna);
        // Simpan baris baru.
        tambahPengumuman($pengguna['username']);
    }

    // Riwayat permintaan hanya untuk admin.
    if ($metode === 'GET' && $jalur === '/log') {
        // Sembunyikan log dari role user.
        wajibAdmin($pengguna);
        // Ambil 100 catatan terbaru agar tabel klien tetap ringan.
        $perintah = koneksi()->query(
            'SELECT id, waktu, method, path, status, ip, username, pesan
             FROM log_api ORDER BY id DESC LIMIT 100'
        );
        // Balas daftar log.
        kirim(200, ['data' => $perintah->fetchAll()], $pengguna['username'], 'Daftar log');
    }

    // Tidak ada cabang yang cocok.
    kirim(404, ['pesan' => 'Endpoint tidak ditemukan'], $pengguna['username'], 'Endpoint tidak ditemukan');
} catch (PDOException $e) {
    // Pesan asli database sengaja tidak dimasukkan ke respons.
    kirim(500, ['pesan' => 'Terjadi kesalahan pada server'], $GLOBALS['pengguna_log'], 'Kesalahan server');
}

// Periksa batas gagal login, lalu terbitkan token bila sandi benar.
function tanganiLogin(): void
{
    // Butuh pengaturan jendela gagal dan kunci token.
    $konfigurasi = require __DIR__ . '/config.php';
    // Urai tubuh JSON.
    $data = bacaJson();
    // JSON rusak tidak diproses sebagai sandi.
    if ($data === null) {
        // 422 karena bentuk permintaan tidak dapat divalidasi.
        kirim(422, ['pesan' => 'Validasi gagal', 'errors' => ['body' => 'JSON tidak valid']], null, 'JSON login tidak valid');
    }
    // Ambil username tanpa spasi.
    $username = trim((string) ($data['username'] ?? ''));
    // Sandi tidak di-trim agar spasi yang memang bagian sandi tetap berarti.
    $sandi = (string) ($data['password'] ?? '');
    // Siapkan kesalahan field yang kosong.
    $salah = [];
    // Username wajib diisi.
    if ($username === '') {
        // Pesan field username.
        $salah['username'] = 'Username wajib diisi';
    }
    // Sandi wajib diisi.
    if ($sandi === '') {
        // Pesan field sandi.
        $salah['password'] = 'Password wajib diisi';
    }
    // Ada field kosong: ini belum dihitung sebagai gagal login.
    if ($salah !== []) {
        // Balas 422 beserta daftar field.
        kirim(422, ['pesan' => 'Validasi gagal', 'errors' => $salah], null, 'Validasi login gagal');
    }
    // Lihat apakah IP ini sedang dikunci.
    $tunggu = detikTerkunci($konfigurasi);
    // Angka bukan null berarti batas gagal sudah tercapai.
    if ($tunggu !== null) {
        // 429 disertai header Retry-After sesuai sisa jendela waktu.
        kirim(429, ['pesan' => 'Terlalu banyak percobaan login. Coba lagi nanti.'], $username, 'Login dibatasi', [
            'Retry-After' => (string) $tunggu,
        ]);
    }
    // Cari akun berdasarkan username memakai prepared statement.
    $perintah = koneksi()->prepare('SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
    // Isi tanda tanya dengan username yang sudah dibersihkan.
    $perintah->execute([$username]);
    // Ambil satu baris atau false bila username tidak ada.
    $akun = $perintah->fetch();
    // Hash cadangan ini hasil password_hash agar akun yang tidak ada tetap melewati password_verify.
    $hashCadangan = '$2y$10$UMpVBLuVzZ66q10y97pPKOEMTnixyWG.MM3jI7ZQiDa5iLxeyFL6.';
    // Pakai hash sungguhan bila akun ada; bila tidak, pakai hash cadangan.
    $hash = is_array($akun) ? $akun['password'] : $hashCadangan;
    // Bandingkan sandi. Hash cadangan hampir mustahil cocok dengan sandi pengguna.
    $cocok = password_verify($sandi, $hash);
    // Username tidak ada ATAU sandi salah memakai pesan yang sama persis.
    if (!is_array($akun) || !$cocok) {
        // Catat kegagalan untuk menghitung rate limit.
        catatGagal();
        // 401 dengan kalimat yang tidak memberitahu bagian mana yang salah.
        kirim(401, ['pesan' => 'Username atau password salah'], $username, 'Login gagal');
    }
    // Login benar menghapus hitungan gagal IP ini.
    bersihkanGagal();
    // Susun klaim yang diwajibkan tugas: sub, username, role, dan exp.
    $sekarang = time();
    // Buat token bertanda tangan.
    $token = jwtBuat([
        'sub' => (int) $akun['id'],
        'username' => $akun['username'],
        'role' => $akun['role'],
        'exp' => $sekarang + (int) $konfigurasi['masa_token'],
    ], $konfigurasi['kunci_jwt']);
    // Kembalikan token dan ringkasan akun, tanpa hash sandi.
    kirim(200, [
        'token' => $token,
        'user' => [
            'id' => (int) $akun['id'],
            'username' => $akun['username'],
            'role' => $akun['role'],
        ],
    ], $akun['username'], 'Login berhasil');
}

// Kembalikan sisa detik penguncian, atau null bila IP masih boleh mencoba.
function detikTerkunci(array $konfigurasi): ?int
{
    // Angka jendela dipaksa integer karena MySQL tidak menerima placeholder di dalam INTERVAL.
    $jendela = (int) $konfigurasi['jendela_gagal'];
    // Hapus percobaan yang sudah lebih tua daripada jendela waktu.
    $hapus = koneksi()->prepare(
        'DELETE FROM login_gagal WHERE waktu < (NOW() - INTERVAL ' . $jendela . ' SECOND)'
    );
    // Tidak ada data pengguna di SQL ini, hanya perintah penghapusan.
    $hapus->execute();
    // Ambil waktu percobaan yang masih dihitung untuk IP ini, dari yang tertua.
    $baca = koneksi()->prepare('SELECT waktu FROM login_gagal WHERE ip = ? ORDER BY waktu ASC');
    // Saring menurut alamat pengakses.
    $baca->execute([alamatIp()]);
    // Semua waktu gagal yang tersisa.
    $baris = $baca->fetchAll(PDO::FETCH_COLUMN);
    // Di bawah batas, login masih dilayani.
    if (count($baris) < (int) $konfigurasi['maks_gagal']) {
        // null berarti tidak terkunci.
        return null;
    }
    // Penguncian berakhir ketika percobaan tertua keluar dari jendela.
    $tertua = strtotime((string) $baris[0]);
    // Selisih detik, minimal 1 agar header Retry-After tidak nol.
    return max(1, $tertua + (int) $konfigurasi['jendela_gagal'] - time());
}

// Tambah satu baris gagal login untuk IP saat ini.
function catatGagal(): void
{
    // Prepared statement untuk menyimpan IP.
    $perintah = koneksi()->prepare('INSERT INTO login_gagal (ip, waktu) VALUES (?, NOW())');
    // Isi IP pengakses.
    $perintah->execute([alamatIp()]);
}

// Hapus jejak gagal setelah sandi yang benar diterima.
function bersihkanGagal(): void
{
    // Hapus semua jejak IP ini, bukan jejak pengguna lain.
    $perintah = koneksi()->prepare('DELETE FROM login_gagal WHERE ip = ?');
    // Jalankan penghapusan.
    $perintah->execute([alamatIp()]);
}

// Kirim satu pengumuman atau 404 bila id yang sah tidak ada di tabel.
function kirimDetail(int $id, string $username): void
{
    // Siapkan query satu baris.
    $perintah = koneksi()->prepare(
        'SELECT id, kode_info, judul, kategori, tanggal, penulis, prioritas, ringkasan, created_at
         FROM pengumuman WHERE id = ?'
    );
    // Isi id yang sudah dipastikan bilangan positif.
    $perintah->execute([$id]);
    // Ambil barisnya.
    $baris = $perintah->fetch();
    // Tidak ada baris berarti sumber daya tidak ditemukan.
    if (!$baris) {
        // 404 berbeda dari 400: bentuk id benar, datanya yang tidak ada.
        kirim(404, ['pesan' => 'Pengumuman tidak ditemukan'], $username, 'Detail tidak ditemukan');
    }
    // Balas objek pengumuman.
    kirim(200, ['data' => $baris], $username, 'Detail pengumuman');
}

// Simpan pengumuman baru setelah validasi dan cek kode unik.
function tambahPengumuman(string $username): void
{
    // Baca tubuh permintaan.
    $data = bacaJson();
    // Tolak JSON yang tidak bisa diurai.
    if ($data === null) {
        // 422 untuk tubuh yang rusak.
        kirim(422, ['pesan' => 'Validasi gagal', 'errors' => ['body' => 'JSON tidak valid']], $username, 'JSON tambah tidak valid');
    }
    // Kumpulkan kesalahan field.
    $salah = validasiPengumuman($data);
    // Ada kesalahan: jangan sentuh tabel.
    if ($salah !== []) {
        // 422 disertai peta field.
        kirim(422, ['pesan' => 'Validasi gagal', 'errors' => $salah], $username, 'Validasi tambah gagal');
    }
    // Rapikan tipe data.
    $bersih = dataBersih($data);
    // Tolak kode yang sudah dipakai baris lain.
    if (kodeBentrok($bersih['kode_info'], 0)) {
        // 409 adalah konflik keunikan.
        kirim(409, ['pesan' => 'Kode info sudah dipakai'], $username, 'Kode info bentrok');
    }
    // Sisipkan baris baru.
    $perintah = koneksi()->prepare(
        'INSERT INTO pengumuman (kode_info, judul, kategori, tanggal, penulis, prioritas, ringkasan)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    // Isi nilai sesuai urutan tanda tanya.
    $perintah->execute([
        $bersih['kode_info'],
        $bersih['judul'],
        $bersih['kategori'],
        $bersih['tanggal'],
        $bersih['penulis'],
        $bersih['prioritas'],
        $bersih['ringkasan'],
    ]);
    // Ambil id yang baru saja dibuat MySQL.
    $idBaru = (int) koneksi()->lastInsertId();
    // Baca kembali baris lengkap agar klien mendapat created_at dari server.
    $baca = koneksi()->prepare(
        'SELECT id, kode_info, judul, kategori, tanggal, penulis, prioritas, ringkasan, created_at
         FROM pengumuman WHERE id = ?'
    );
    // Cari baris baru.
    $baca->execute([$idBaru]);
    // 201 menandai sumber daya berhasil dibuat.
    kirim(201, ['pesan' => 'Pengumuman ditambahkan', 'data' => $baca->fetch()], $username, 'Pengumuman ditambahkan');
}

// Ubah pengumuman yang sudah ada.
function ubahPengumuman(int $id, string $username): void
{
    // Pastikan id yang sah benar-benar ada sebelum validasi isi.
    $ada = koneksi()->prepare('SELECT id FROM pengumuman WHERE id = ?');
    // Cari id.
    $ada->execute([$id]);
    // Tidak ada baris: 404, bukan 422.
    if (!$ada->fetch()) {
        // Beritahu bahwa target ubah tidak ditemukan.
        kirim(404, ['pesan' => 'Pengumuman tidak ditemukan'], $username, 'Ubah gagal, data tidak ada');
    }
    // Baca JSON perubahan.
    $data = bacaJson();
    // JSON rusak.
    if ($data === null) {
        // Tolak sebelum membandingkan keunikan.
        kirim(422, ['pesan' => 'Validasi gagal', 'errors' => ['body' => 'JSON tidak valid']], $username, 'JSON ubah tidak valid');
    }
    // Validasi semua kolom.
    $salah = validasiPengumuman($data);
    // Masih ada yang salah.
    if ($salah !== []) {
        // Kembalikan seluruh field yang gagal.
        kirim(422, ['pesan' => 'Validasi gagal', 'errors' => $salah], $username, 'Validasi ubah gagal');
    }
    // Rapikan nilai.
    $bersih = dataBersih($data);
    // Kode boleh tetap milik baris ini, tetapi tidak boleh milik baris lain.
    if (kodeBentrok($bersih['kode_info'], $id)) {
        // Konflik keunikan saat mengubah.
        kirim(409, ['pesan' => 'Kode info sudah dipakai'], $username, 'Kode info bentrok saat ubah');
    }
    // Perbarui seluruh kolom data.
    $perintah = koneksi()->prepare(
        'UPDATE pengumuman
         SET kode_info = ?, judul = ?, kategori = ?, tanggal = ?, penulis = ?, prioritas = ?, ringkasan = ?
         WHERE id = ?'
    );
    // Jalankan pembaruan.
    $perintah->execute([
        $bersih['kode_info'],
        $bersih['judul'],
        $bersih['kategori'],
        $bersih['tanggal'],
        $bersih['penulis'],
        $bersih['prioritas'],
        $bersih['ringkasan'],
        $id,
    ]);
    // Baca baris yang baru diubah agar respons berisi nilai dari database.
    $baca = koneksi()->prepare(
        'SELECT id, kode_info, judul, kategori, tanggal, penulis, prioritas, ringkasan, created_at
         FROM pengumuman WHERE id = ?'
    );
    // Cari baris yang sama dengan id yang diubah.
    $baca->execute([$id]);
    // 200 menandai perubahan berhasil.
    kirim(200, ['pesan' => 'Pengumuman diperbarui', 'data' => $baca->fetch()], $username, 'Pengumuman diperbarui');
}

// Hapus satu pengumuman.
function hapusPengumuman(int $id, string $username): void
{
    // Hapus hanya bila id cocok; prepared statement menjaga id tetap data, bukan SQL.
    $perintah = koneksi()->prepare('DELETE FROM pengumuman WHERE id = ?');
    // Jalankan penghapusan.
    $perintah->execute([$id]);
    // rowCount nol berarti tidak ada baris yang terhapus.
    if ($perintah->rowCount() === 0) {
        // Id sah tetapi datanya sudah tidak ada.
        kirim(404, ['pesan' => 'Pengumuman tidak ditemukan'], $username, 'Hapus gagal, data tidak ada');
    }
    // 200 karena penghapusan selesai dan ada pesan konfirmasi.
    kirim(200, ['pesan' => 'Pengumuman dihapus'], $username, 'Pengumuman dihapus');
}

// True bila kode_info sudah dimiliki baris lain.
function kodeBentrok(string $kode, int $kecualiId): bool
{
    // Kecuali id sendiri agar proses ubah tidak bentrok dengan baris yang sedang disunting.
    $perintah = koneksi()->prepare('SELECT id FROM pengumuman WHERE kode_info = ? AND id <> ? LIMIT 1');
    // Isi kode dan id yang dikecualikan.
    $perintah->execute([$kode, $kecualiId]);
    // Ada baris berarti kode tidak unik.
    return (bool) $perintah->fetch();
}
