# Dokumentasi API

Base URL saat pengembangan: `http://127.0.0.1:8000`

Semua respons JSON memakai bentuk:

```json
{ "sukses": true, "pesan": "...", "data": {} }
```

Bila gagal, `sukses` bernilai `false`. Kunci `errors` hanya muncul pada status **422**.

Header untuk route yang butuh login:

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

## Ringkasan status

| Kode | Arti di API ini |
|---|---|
| 200 | Baca, ubah, atau hapus berhasil |
| 201 | Tambah pengumuman berhasil |
| 400 | `id` bukan bilangan bulat positif |
| 401 | Login salah, token tidak ada, rusak, atau kedaluwarsa |
| 403 | Role tidak berhak |
| 404 | Data atau route tidak ada |
| 405 | Method tidak diizinkan untuk path itu |
| 409 | `kode_info` sudah dipakai |
| 422 | Field tidak lolos validasi |
| 429 | Login gagal terlalu sering |
| 500 | Gangguan server, tanpa detail SQL |

## POST `/login`

Publik. Body:

```json
{ "username": "admin", "password": "admin123" }
```

Contoh:

```bash
curl -X POST http://127.0.0.1:8000/login ^
  -H "Content-Type: application/json" ^
  -d "{\"username\":\"admin\",\"password\":\"admin123\"}"
```

Sukses **200**:

```json
{
  "sukses": true,
  "pesan": "Login berhasil",
  "data": {
    "token": "<jwt>",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": { "id": 1, "username": "admin", "nama": "Dewi Purbarini", "role": "admin" }
  }
}
```

Payload token memuat `iss`, `iat`, `exp`, `sub` (id user), `username`, dan `role`.

| Kondisi | Status | Pesan |
|---|---|---|
| Username atau password salah | 401 | `Username atau password salah` (teksnya sama untuk kedua kasus) |
| Field kosong | 422 | `Data login tidak lengkap` + `errors` |
| 5 gagal dalam 60 detik, percobaan berikutnya | 429 | menyebut sisa detik, header `Retry-After` |

## GET `/me`

Perlu token. Sukses **200**:

```json
{
  "sukses": true,
  "pesan": "Data akun berhasil diambil",
  "data": { "id": 1, "username": "admin", "nama": "Dewi Purbarini", "role": "admin", "exp": 1793974837 }
}
```

| Kondisi | Status | Pesan |
|---|---|---|
| Tidak ada header Authorization | 401 | `Token tidak ada` |
| Bukan format `Bearer ...`, tanda tangan salah, atau rusak | 401 | `Token tidak valid` |
| `exp` sudah lewat | 401 | `Token kedaluwarsa` |

## GET `/pengumuman`

Perlu login (admin atau user). **200**, `data` adalah array. Urutan tanggal terbaru.

## GET `/pengumuman/{id}`

Perlu login.

| Kondisi | Status | Pesan |
|---|---|---|
| Id sah dan ada | 200 | `Detail pengumuman berhasil diambil` |
| `abc`, `0`, `-1`, atau pecahan | 400 | `Id tidak valid` |
| Id angka tetapi tidak ada baris | 404 | `Pengumuman tidak ditemukan` |

## POST `/pengumuman`

Hanya admin. Body:

```json
{
  "kode_info": "INFO-009",
  "judul": "Contoh judul",
  "kategori": "Umum",
  "tanggal": "2026-11-06",
  "penulis": "Biro Akademik",
  "jumlah_dibaca": 0
}
```

Sukses **201** dan `data` berisi baris baru.

| Kondisi | Status |
|---|---|
| Role user | 403 `Akses ditolak: hanya admin` |
| `kode_info` sudah ada | 409 `Kode info sudah dipakai` |
| Field kosong, tanggal bukan `YYYY-MM-DD`, atau `jumlah_dibaca` negatif / bukan bilangan | 422 `Data tidak valid` |

Aturan validasi: `kode_info` wajib, string, maks 20; `judul` wajib, maks 150; `kategori` wajib, maks 50; `tanggal` wajib format `Y-m-d`; `penulis` wajib, maks 100; `jumlah_dibaca` wajib, integer, minimal 0.

## PUT `/pengumuman/{id}`

Hanya admin. Body sama dengan tambah. Sukses **200**. Kode yang sama dengan baris itu sendiri tidak dianggap kembar. Bentrok dengan baris lain menghasilkan 409. Id tidak valid 400. Id tidak ada 404. Body jelek 422.

## DELETE `/pengumuman/{id}`

Hanya admin. Sukses **200**:

```json
{ "sukses": true, "pesan": "Pengumuman berhasil dihapus", "data": { "id": 9 } }
```

User mendapat 403. Id tidak valid 400. Id tidak ada 404.

## GET `/log`

Hanya admin. Query opsional `?limit=20` (1–200, selain itu dipakai 50). Urutan waktu terbaru. Sukses **200**, `data` array baris `log_api`. User mendapat 403.

Kolom: `waktu`, `method`, `path`, `status`, `ip`, `username`, `pesan`. Password dan token tidak pernah disimpan.

## Route lain

- Path yang tidak terdaftar: **404** `Route tidak ditemukan`
- Method salah, misalnya POST `/me`: **405** `Method tidak diizinkan`
- Gangguan server: **500** `Terjadi kesalahan pada server`
