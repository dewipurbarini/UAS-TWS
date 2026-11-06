# Keputusan teknis

Project yang ada saat dikerjakan adalah Laravel **12.71.0** hasil `php artisan --version`. Belum ada route API, model khusus, atau migration di luar bawaan framework. Pekerjaan baru ditambahkan di atas kerangka itu.

## Versi dan database

- Laravel 12 dipakai apa adanya. Tidak diturunkan ke Laravel 10.
- Database dipindah dari SQLite bawaan ke **MySQL** `pengumuman_db` di XAMPP (MariaDB 10.4, user `root`, password kosong). Soal meminta MySQL.
- `CACHE_STORE=file` agar pembatas login jalan tanpa Redis. File cache cukup untuk satu komputer pengembangan.
- `APP_DEBUG=false` di `.env` contoh produksi. Handler API tetap mengirim pesan generik walaupun debug menyala.

## Route tanpa `/api`

Di `bootstrap/app.php`, route API didaftarkan dengan `apiPrefix: ''`. Path yang hidup adalah `/login`, `/me`, `/pengumuman`, dan `/log`, sesuai soal.

## JWT

Paket **`firebase/php-jwt` v7** dipakai, bukan Sanctum, Passport, atau tymon-jwt. Alasannya: satu paket kecil, algoritma HS256 terlihat di `JwtService`, dan payload (`sub`, `username`, `role`, `exp`) bisa dijelaskan baris per baris. Kunci ada di `.env` sebagai `JWT_SECRET` dan dibaca lewat `config/jwt.php`.

## Middleware

`LogApi` dan `ForceJson` dipasang di middleware global, dengan `LogApi` di paling luar. Dengan begitu status 401, 403, 404 route, 405, 422, 429, dan 500 ikut tercatat setelah respons terbentuk. Halaman `/` dan `/up` tidak dicatat dan tidak dipaksa menjadi JSON.

Alias `jwt.auth` dan `role.admin` didaftarkan di `bootstrap/app.php`. `role.admin` selalu setelah `jwt.auth`.

## Tabel users

Migration bawaan `create_users_table` diubah: kolom email, `email_verified_at`, `remember_token`, dan `updated_at` tidak dipakai. Yang ada: `id`, `username` unik, `password` hash, `nama`, `role`, `created_at`.

Tabel `sessions` dan `password_reset_tokens` dibiarkan karena bagian dari kerangka Laravel. API ini tidak membacanya.

## Client

Client diletakkan di `public/client/` supaya `php artisan serve` menyajikannya sebagai file statis dan tidak bentrok dengan route API. Tidak memakai React/Vue. Bootstrap 5 hanya sebagai dasar form dan grid, lalu ditimpa palet sendiri di `style.css`.

Token disimpan di `sessionStorage` dengan kunci `token_pengumuman`.

## Validasi dan error

Validasi memakai `Validator::make` agar bentuk 422 bisa disusun sendiri. `id` dicek manual di controller supaya `/pengumuman/abc` menjadi 400, bukan 404 bawaan Laravel.

`kode_info` kembar dicek sebelum simpan, lalu `QueryException` SQLSTATE `23000` ditangkap sebagai cadangan (409).

Pesan login salah username dan salah password sama: `Username atau password salah`. Jika username tidak ada, `Hash::check` tetap dijalankan ke hash dummy bcrypt cost 12.

## Yang tidak diubah

Halaman `resources/views/welcome.blade.php` dibiarkan. Client tugas tidak memakai view itu.
