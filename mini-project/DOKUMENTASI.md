# Dokumentasi Mini Project TWS

Pengumuman Kampus — Dewi Purbarini — NIM 23676003 — Kelas R7C

Aplikasi ini menyalin pola praktikum `api-mhs-auth`: PHP, MySQL, dan PDO. Objeknya diganti menjadi pengumuman kampus. Setiap baris PHP, SQL, HTML, CSS, dan JavaScript yang ditulis untuk tugas ini punya komentar penjelasan di samping atau di atas barisnya.

## Menjalankan

1. Nyalakan Apache dan MySQL dari XAMPP.
2. Impor struktur dan data awal:

```
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\UAS-TWS\mini-project\database.sql
```

3. Buka [http://localhost/UAS-TWS/mini-project/client/index.html](http://localhost/UAS-TWS/mini-project/client/index.html).

Membuka `http://localhost/UAS-TWS/` ikut dialihkan ke halaman yang sama.

## Akun uji

| Username | Password | Role | Hak |
| --- | --- | --- | --- |
| admin | Admin23676003 | admin | baca, tambah, ubah, hapus, lihat log |
| user | User23676003 | user | baca saja |

Sandi di database disimpan sebagai hash bcrypt. Teks sandi di atas yang disebut saat demo.

Kunci JWT ada di `config.php`, bukan kunci contoh praktikum.

## Struktur

| Berkas | Peran |
| --- | --- |
| `config.php` | Host database, nama basis data, kunci JWT, masa token, batas gagal login |
| `index.php` | Router: login, profil, CRUD `/pengumuman`, dan `/log` |
| `lib/koneksi.php` | Satu koneksi PDO, prepared statement asli, zona waktu +07:00 |
| `lib/jwt.php` | Membuat dan memeriksa JWT HS256 tanpa pustaka luar |
| `lib/bantu.php` | JSON, Bearer, validasi, log, dan pengiriman status HTTP |
| `database.sql` | Tabel `users`, `pengumuman`, `log_api`, `login_gagal`, plus data awal |
| `client/index.html` | Halaman masuk dan papan pengumuman |
| `client/css/gaya.css` | Tampilan yang disusun sendiri |
| `client/js/aplikasi.js` | Panggilan API, saringan, modal, dan hak role |
| `.htaccess` | Meneruskan jalur API ke `index.php` dan menjaga header Authorization |

## Data pengumuman

Selain `id` dan `created_at`, kolom datanya ada tujuh:

- `kode_info` unik, dan merupakan kolom data pertama
- `judul`
- `kategori`
- `tanggal`
- `penulis`
- `prioritas` sebagai kolom angka 1 sampai 5
- `ringkasan`

Berkas SQL sudah berisi enam baris contoh.

## Endpoint

Semua jalur kecuali `POST /login` membutuhkan header `Authorization: Bearer <token>`.

| Metode | Jalur | Siapa | Hasil |
| --- | --- | --- | --- |
| POST | `/login` | publik | 200 dan JWT, atau 401 / 422 / 429 |
| GET | `/me` | admin dan user | profil dari token |
| GET | `/pengumuman` | admin dan user | daftar |
| GET | `/pengumuman/{id}` | admin dan user | detail |
| POST | `/pengumuman` | admin | 201 |
| PUT | `/pengumuman/{id}` | admin | 200 |
| DELETE | `/pengumuman/{id}` | admin | 200 |
| GET | `/log` | admin | 100 catatan terbaru |

JWT memuat `sub`, `username`, `role`, dan `exp`. Masa berlakunya 2 jam.

## Kode status

| Kode | Kapan |
| --- | --- |
| 200 | baca, ubah, atau hapus berhasil |
| 201 | pengumuman baru tersimpan |
| 302 | membuka folder proyek, browser dialihkan ke klien |
| 400 | id pada URL bukan bilangan bulat positif |
| 401 | tanpa token, token rusak, token kedaluwarsa, atau username/password salah |
| 403 | user mencoba tambah, ubah, hapus, atau membuka log |
| 404 | id sah tetapi data atau endpoint tidak ada |
| 409 | `kode_info` sudah dipakai baris lain |
| 422 | JSON rusak atau field tidak lolos validasi |
| 429 | IP yang sama gagal login 5 kali dalam 10 menit; respons membawa `Retry-After` |
| 500 | gangguan server; pesan SQL tidak dikirim ke klien |

Username yang tidak terdaftar dan password yang salah memakai kalimat yang sama: `Username atau password salah`.

## Alur singkat

1. Klien mengirim username dan password ke `POST /login`.
2. Server menolak lebih dulu bila IP sudah mencapai lima kegagalan.
3. Sandi dicek dengan `password_verify`. Akun yang tidak ada tetap melewati `password_verify` memakai hash cadangan, supaya jawaban tidak membedakan kedua kasus.
4. Login yang benar menghapus jejak gagal IP itu dan mengembalikan token.
5. Permintaan berikutnya membawa token. Server menghitung ulang tanda tangan HMAC dan menolak token yang diubah atau sudah lewat `exp`.
6. Role `user` lolos pada GET. POST, PUT, DELETE, dan GET `/log` berhenti di 403.
7. Setiap jawaban, termasuk yang gagal, menulis satu baris ke `log_api`: waktu, method, path, status, IP, username, dan pesan.
8. Tombol Tambah dan Log disembunyikan untuk user. Tombol Ubah dan Hapus pada kartu dinonaktifkan.

## Validasi server

Browser juga punya `required`, tetapi keputusan akhir ada di server.

- `kode_info`: 3–20 karakter, hanya `A-Z`, `0-9`, dan `-`
- `judul`: 5–150 karakter
- `kategori`: Akademik, Kemahasiswaan, Beasiswa, atau Umum
- `tanggal`: `YYYY-MM-DD` yang benar-benar ada
- `penulis`: 3–100 karakter
- `prioritas`: bilangan bulat 1–5
- `ringkasan`: 10–255 karakter

Nilai ditulis lewat prepared statement. Input tidak digabung ke teks SQL.

## Klien

Font Fraunces dan Outfit dimuat dari Google Fonts. Ikon `bi` dimuat dari Bootstrap Icons. Tidak ada tema Bootstrap atau Tailwind yang disalin utuh; susunan, warna, kartu, modal, dan tabel ada di `gaya.css`.

Token disimpan di `sessionStorage`, jadi sesi hilang ketika tab ditutup. Saringan judul dan kategori dikerjakan di browser setelah daftar diunduh. Tambah, ubah, dan hapus tetap dikirim ke server.

Pesan 401, 403, 409, 422, dan 429 ditampilkan di formulir atau toast. Saat daftar dimuat, halaman menampilkan kalimat "Memuat pengumuman...". Bila tabel kosong, halaman menampilkan "Belum ada pengumuman."

## Contoh permintaan

Login:

```
POST /UAS-TWS/mini-project/login
Content-Type: application/json

{"username":"admin","password":"Admin23676003"}
```

Membaca daftar:

```
GET /UAS-TWS/mini-project/pengumuman
Authorization: Bearer <token>
```

## Batas yang disengaja

- Satu IP dihitung gagal bersama-sama, bukan per username.
- Log yang ditampilkan dibatasi 100 baris terbaru.
- Token tidak disimpan di database; server hanya memeriksa tanda tangan dan `exp`.
- Halaman klien dan API satu origin, jadi tidak memasang CORS.
