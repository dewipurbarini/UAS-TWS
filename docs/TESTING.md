# Hasil pengujian

Server: `http://127.0.0.1:8000`  
Tanggal uji: 6 November 2026  
Akun: `admin` / `admin123` dan `user` / `user123`

Status di bawah diambil dari tabel `log_api` (kolom `status` adalah kode HTTP yang benar-benar dikirim) dan dari header `curl`. Token di laporan dipotong.

## 1. Login admin benar

`POST /login` `{"username":"admin","password":"admin123"}`

- Status **200**
- Pesan: `Login berhasil`
- `data.token` ada, `token_type` = `Bearer`, `expires_in` = 3600
- Payload token (isi, bukan string utuh): `sub` = `"1"`, `username` = `admin`, `role` = `admin`, plus `exp`, `iat`, dan `iss` = `pengumuman-kampus`

## 2. Username salah dan password salah

| Request | Status | Pesan |
|---|---|---|
| username `bukan-akun`, password apa saja | 401 | `Username atau password salah` |
| username `admin`, password `salah-sekali` | 401 | `Username atau password salah` |

Kedua pesan **identik**.

## 3. Enam login gagal berturut-turut

Username `brute-uji`, password salah, satu jendela 60 detik.

| Percobaan | Status |
|---|---|
| 1 sampai 5 | 401 `Username atau password salah` |
| 6 | **429** |

Cuplikan header percobaan yang ditolak:

```http
HTTP/1.1 429 Too Many Requests
Retry-After: 44
Content-Type: application/json
```

Body: `Terlalu banyak percobaan login. Coba lagi dalam 44 detik.`

Baris yang sama tercatat di `log_api` (status 429, username `brute-uji`).

## 4. Token bermasalah pada GET `/me`

| Kasus | Status | Pesan |
|---|---|---|
| Tanpa header | 401 | `Token tidak ada` |
| `Authorization: Bearer aaa.bbb.ccc` | 401 | `Token tidak valid` |
| `Authorization: Token abc` | 401 | `Token tidak valid` |
| Token HS256 dengan `exp` di masa lalu | 401 | `Token kedaluwarsa` |

## 5. GET `/me` token admin

**200**. Data: id 1, username `admin`, nama Dewi Purbarini, role `admin`, dan `exp`.

## 6. User membaca pengumuman

Login `user` / `user123` mengembalikan **200**.

- `GET /pengumuman` **200**, 8 baris. Contoh: `INFO-008` tanggal `2026-11-10`
- `GET /pengumuman/1` **200**, `INFO-001` Jadwal UTS

## 7. Id tidak valid dan tidak ada

| Path | Status | Pesan |
|---|---|---|
| `/pengumuman/abc` | 400 | `Id tidak valid` |
| `/pengumuman/-1` | 400 | `Id tidak valid` |
| `/pengumuman/9999` | 404 | `Pengumuman tidak ditemukan` |

## 8–11. Tulis data

| Aksi | Status | Pesan |
|---|---|---|
| POST sebagai user | 403 | `Akses ditolak: hanya admin` |
| POST admin body valid (`INFO-UJI`) | **201** | `Pengumuman berhasil ditambah` (id 9) |
| POST `kode_info` yang sama | 409 | `Kode info sudah dipakai` |
| POST dan PUT field kosong, tanggal `06-11-2026`, `jumlah_dibaca` -3 | 422 | `Data tidak valid` beserta `errors` per kolom |
| PUT id 9 oleh admin | 200 | `Pengumuman berhasil diubah` |
| DELETE id 9 sebagai user | 403 | `Akses ditolak: hanya admin` |
| DELETE id 9 sebagai admin | 200 | `Pengumuman berhasil dihapus` |

Cuplikan `errors` 422: `kode_info` wajib diisi, `judul` wajib diisi, `kategori` wajib diisi, `tanggal` harus `YYYY-MM-DD`, `penulis` wajib diisi, `jumlah_dibaca` tidak boleh negatif.

## 12. Log

- `GET /log?limit=8` sebagai admin: **200**, memuat request di atas, termasuk yang gagal (401, 403, 404, 409, 422, 429)
- `GET /log` sebagai user: **403**

## 13. Error server

Koneksi diarahkan sesaat ke database yang tidak ada, lalu `GET /pengumuman` dengan token yang masih berlaku.

- Status **500**
- Body: `{"sukses":false,"pesan":"Terjadi kesalahan pada server"}`
- Tidak ada `SQLSTATE`, nama database, atau stack trace
- `.env` dikembalikan setelah uji

Tambahan: `GET /tidak-ada` → 404 `Route tidak ditemukan`. `POST /me` → 405 `Method tidak diizinkan`.

## 14. Client

Diuji di `http://127.0.0.1:8000/client/`.

| Yang dicoba | Hasil |
|---|---|
| Login `user` / `user123` | Masuk, nama Mahasiswa Uji, role user |
| Tombol admin | Tambah, Log, Ubah, dan Hapus tidak ada di pohon interaktif. `hidden` pada tombol itu `true` |
| Login `admin` / `admin123` | Tombol Tambah, tab Log API, serta Ubah/Hapus per baris muncul |
| Form tambah dikirim kosong | Modal tetap terbuka. Pesan di bawah input: kode, kategori, judul, tanggal, penulis wajib diisi. Toast: `Data tidak valid` |
| Cari `zzzz-tidak-ada` | Teks: `Tidak ada pengumuman yang cocok dengan pencarian.` |
| Tombol hapus | Modal konfirmasi menyebut judul dan kode, contoh INFO-008. Data tidak terhapus sebelum tombol Hapus di modal |
| Tab Log | Tabel terisi (5 catatan pada sesi uji client) |
| Lebar 375px | Kartu tampil, tabel disembunyikan, `scrollWidth` sama dengan lebar layar (tidak meluber). Identitas header tetap terbaca |
| Lebar laptop | Tabel tampil, kartu disembunyikan |

Setelah pengujian API, database di-seed ulang supaya `database/pengumuman_db.sql` hanya berisi data awal (2 akun, 8 pengumuman, `log_api` kosong).
