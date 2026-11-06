# API Pengumuman Kampus

Mini Project mata kuliah Teknologi Web Service: REST API dan client web untuk objek **Pengumuman Kampus**.

| Item | Nilai |
|---|---|
| Nama | DEWI PURBARINI |
| NIM | 23676003 |
| Kelas | R7C |
| Endpoint utama | `/pengumuman` |

## Teknologi

- PHP 8.2 dan Laravel 12
- MySQL (XAMPP), diakses lewat Eloquent. Eloquent memakai PDO dan prepared statement
- JWT HS256 dengan paket `firebase/php-jwt`
- Client statis: HTML, CSS, JavaScript `fetch`, Bootstrap 5 dan Bootstrap Icons lewat CDN

## Akun uji

| Username | Password | Role |
|---|---|---|
| `admin` | `admin123` | admin |
| `user` | `user123` | user |

Password di database disimpan sebagai hash bcrypt, bukan teks asli.

## Cara menjalankan dari nol

1. Pasang dependency PHP:

```bash
composer install
```

2. Salin lingkungan:

```bash
copy .env.example .env
php artisan key:generate
```

3. Buat database MySQL `pengumuman_db`, lalu isi `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pengumuman_db
DB_USERNAME=root
DB_PASSWORD=
```

4. Buat kunci JWT (jangan di-commit):

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Tempel hasilnya ke `JWT_SECRET` di `.env`. `JWT_TTL_MINUTES` default-nya 60.

5. Buat tabel dan data awal:

```bash
php artisan migrate --seed
php artisan serve
```

6. Buka client di [http://127.0.0.1:8000/client/](http://127.0.0.1:8000/client/).

### Alternatif impor SQL

Jika `migrate` tidak dipakai, impor file `database/pengumuman_db.sql` ke database `pengumuman_db` lewat phpMyAdmin atau:

```bash
mysql -u root pengumuman_db < database/pengumuman_db.sql
```

File itu sudah berisi struktur dan data awal (2 akun dan 8 pengumuman). `APP_KEY` dan `JWT_SECRET` tetap harus ada di `.env`.

## Fitur

- Login JWT, data akun `/me`, dan logout di client
- Daftar, detail, tambah, ubah, dan hapus pengumuman
- Role `admin` dan `user`. Menulis data dan melihat log hanya untuk admin
- Validasi server, kode `kode_info` unik, dan pesan error JSON yang seragam
- Pembatasan login gagal (429 + header `Retry-After`)
- Setiap request API dicatat di tabel `log_api`
- Client: cari, filter kategori, urutan, kartu di ponsel, tabel di laptop, modal, dan toast

## Struktur penting

```text
app/Http/Controllers/     AuthController, PengumumanController, LogController
app/Http/Middleware/      ForceJson, LogApi, JwtAuth, RoleAdmin
app/Models/               User, Pengumuman, LogApi
app/Support/              JwtService, ApiResponse
config/jwt.php
database/migrations/      users, pengumuman, log_api
database/seeders/
database/pengumuman_db.sql
public/client/            halaman web
routes/api.php            tanpa awalan /api
docs/                     penjelasan, API, skenario video, pengujian
tests/api.http            kumpulan request uji
```

Dokumentasi belajar ada di folder `docs/`.
