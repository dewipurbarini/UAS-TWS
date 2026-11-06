# Skenario video

Urutan demo, perkiraan 8–12 menit. Siapkan `php artisan serve` dan database yang sudah di-seed. Buka client di `http://127.0.0.1:8000/client/` dan phpMyAdmin (atau tabel di IDE) untuk `pengumuman_db`.

## 1. Perkenalan

Sebutkan: DEWI PURBARINI, NIM 23676003, kelas R7C, objek Pengumuman Kampus. Tunjukkan header client yang memuat kalimat `Pengumuman Kampus — DEWI PURBARINI — 23676003`.

## 2. Akun uji

Sebutkan dengan suara:

- admin / admin123
- user / user123

Jelaskan bahwa di database yang tersimpan adalah hash bcrypt, bukan kedua password itu.

## 3. Struktur database

Tunjukkan tabel `users`, `pengumuman`, dan `log_api`.

- `kode_info` unik
- `jumlah_dibaca` bertipe angka
- minimal 5 kolom data dan 8 baris pengumuman
- `log_api` masih sedikit atau kosong sebelum demo dimulai

## 4. Status code lewat klien HTTP atau curl

Ikuti `tests/api.http` atau `docs/TESTING.md`:

1. Login admin → 200 dan tunjukkan payload `sub`, `username`, `role`, `exp` (bisa di [jwt.io](https://jwt.io) tanpa menempelkan kunci rahasia ke situs itu bila tidak ingin; cukup baca penjelasan di `JwtService`).
2. Username salah dan password salah → pesan sama, 401.
3. Enam gagal berturut-turut → 429 dan header `Retry-After`.
4. `/me` tanpa token, token rusak, token kedaluwarsa → 401 dengan pesan yang berbeda.
5. `/me` token valid → 200.
6. User: GET daftar dan detail → 200.
7. `/pengumuman/abc` → 400, `/pengumuman/9999` → 404.
8. POST sebagai user → 403. POST admin valid → 201.
9. POST kode kembar → 409.
10. POST field jelek → 422 dan objek `errors`.
11. PUT admin → 200. DELETE user → 403. DELETE admin → 200.
12. GET `/log` admin → 200, termasuk baris yang gagal. GET `/log` user → 403.

## 5. Client

1. Login sebagai user. Tunjukkan tidak ada tombol Tambah, Ubah, Hapus, dan Log.
2. Logout, login sebagai admin. Tunjukkan tombol itu ada.
3. Buka modal tambah, kosongkan field, simpan, tunjukkan pesan di bawah input.
4. Ketik pencarian yang tidak cocok, tunjukkan keadaan kosong.
5. Buka modal hapus, batalkan, lalu hapus satu data uji bila ingin.
6. Buka tab Log.
7. Mode responsif browser: lebar sekitar 375px (kartu) dan sekitar 1366px (tabel). Header identitas tetap terlihat.

## 6. Isi log

Di phpMyAdmin, buka `log_api`. Tunjukkan kolom waktu, method, path, status, ip, username, pesan. Tunjukkan satu baris 401 dan satu baris 200. Tekankan password tidak ada di tabel ini.

## 7. Tunjuk kode

Tiga tempat yang wajib ditunjuk:

1. `JWT_SECRET` di `.env` dan pembacaan di `config/jwt.php` serta `app/Support/JwtService.php` (jangan membacakan isi kunci di video bila video akan diunggah publik; cukup tunjuk nama variabel).
2. Urutan middleware di `bootstrap/app.php`: `LogApi`, `ForceJson`, lalu `jwt.auth`, lalu `role.admin`.
3. `RateLimiter` di `AuthController::login`: `tooManyAttempts`, `hit` hanya saat gagal, `clear` saat berhasil, header `Retry-After`.

Tutup dengan alamat client dan lokasi dokumentasi di folder `docs/`.
