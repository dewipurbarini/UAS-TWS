# Penjelasan kode

Dokumen ini untuk belajar sebelum presentasi. Baca sambil membuka file yang disebut.

Pemilik tugas: **DEWI PURBARINI**, NIM **23676003**, kelas **R7C**, objek **Pengumuman Kampus**.

## Alur singkat

1. Pengguna mengirim `POST /login` dengan username dan password.
2. `AuthController` membatasi percobaan gagal, lalu mencocokkan password dengan hash bcrypt.
3. Bila cocok, `JwtService` membuat token HS256. Token itu dikirim ke browser dan disimpan di `sessionStorage`.
4. Request berikutnya membawa header `Authorization: Bearer <token>`.
5. Middleware berjalan dari luar ke dalam: catat log (dibungkus), paksa JSON, cek token, cek role, baru controller.
6. Controller mengembalikan JSON `{ sukses, pesan, data }`.
7. `LogApi` menulis status dan pesan akhir ke `log_api`. Gagal menulis log tidak mengubah respons.

## Apa itu JWT

JWT adalah string tiga bagian yang dipisah titik: `header.payload.signature`.

- **Header** menyebut tipe token dan algoritma. Di sini algoritmanya `HS256` (HMAC dengan SHA-256).
- **Payload** adalah data yang dibawa: `sub` (id user), `username`, `role`, `iat` (waktu dibuat), `exp` (waktu kedaluwarsa), dan `iss` (penerbit).
- **Signature** adalah tanda tangan dari header + payload memakai `JWT_SECRET`. Orang yang tidak punya kunci tidak bisa membuat token yang lolos cek.

`exp` wajib ada supaya token tidak berlaku selamanya. Bila waktu server sudah melewati `exp`, middleware membalas 401 `Token kedaluwarsa`. Lama hidup diatur `JWT_TTL_MINUTES` (default 60 menit).

Payload bisa dibaca siapa saja (itu hanya Base64, bukan enkripsi). Yang dilindungi adalah keasliannya. Karena itu password tidak dimasukkan ke payload.

## Urutan middleware dan alasannya

1. **`LogApi`** paling luar. Ia memanggil lapisan dalam, lalu mencatat status yang sudah final. 401 dan 500 ikut tercatat.
2. **`ForceJson`** menyetel `Accept: application/json` agar error tidak menjadi halaman HTML.
3. **`jwt.auth`** menolak permintaan tanpa token yang sah dan meletakkan akun di `$request->attributes`.
4. **`role.admin`** hanya pada route tulis dan `/log`. Ia membaca role dari database. User mendapat 403.
5. Controller mengerjakan satu tugas.

`role.admin` tidak boleh dipasang sebelum `jwt.auth`, karena belum ada akun yang dicek.

## Password, rate limit, prepared statement

- Password di-hash dengan `Hash::make` (bcrypt, cost 12) di `UserSeeder`. Saat login, `Hash::check` membandingkan teks yang diketik dengan hash. Hash tidak bisa dibalik menjadi password.
- Jika username tidak ada, kode tetap memanggil `Hash::check` pada hash dummy. Waktu respons tidak membedakan "user tidak ada" dan "password salah". Pesannya juga sama.
- `RateLimiter` memakai kunci `login|<ip>|<username>`. `hit` hanya saat gagal, jendela 60 detik, maksimal 5 gagal. Percobaan berikutnya 429 dan header `Retry-After`. Login berhasil memanggil `clear`.
- Eloquent mengubah `where('username', $username)` menjadi SQL dengan placeholder. PDO mengikat nilai itu. Input pengguna tidak disambung ke teks SQL. Project ini tidak memakai `DB::raw` atau `whereRaw`.

## File server

| File | Tugas |
|---|---|
| `config/jwt.php` | Secret, TTL, algoritma, issuer dari `.env` |
| `app/Support/JwtService.php` | `buatToken` dan `verifikasi` |
| `app/Support/ApiResponse.php` | Bentuk JSON sukses dan gagal |
| `app/Http/Middleware/ForceJson.php` | Paksa `Accept` JSON |
| `app/Http/Middleware/LogApi.php` | Tulis `log_api` setelah respons ada |
| `app/Http/Middleware/JwtAuth.php` | 401 bila token bermasalah |
| `app/Http/Middleware/RoleAdmin.php` | 403 bila bukan admin |
| `app/Http/Controllers/AuthController.php` | `/login` dan `/me` |
| `app/Http/Controllers/PengumumanController.php` | CRUD dan validasi |
| `app/Http/Controllers/LogController.php` | `GET /log` |
| `app/Models/User.php` | Tabel `users`, password disembunyikan |
| `app/Models/Pengumuman.php` | Tabel `pengumuman` |
| `app/Models/LogApi.php` | Tabel `log_api` |
| `routes/api.php` | Daftar endpoint, tanpa awalan `/api` |
| `bootstrap/app.php` | Prefix kosong, alias middleware, JSON untuk error |
| `database/seeders/*` | Dua akun dan delapan pengumuman |

`id` pada URL dicek dengan pola bilangan bulat positif. `abc` dan `-1` mengembalikan 400 di controller, bukan 404 otomatis Laravel. `kode_info` kembar mengembalikan 409, baik dari cek biasa maupun dari SQLSTATE `23000`.

## Client

Lokasi: `public/client/`.

### HTML (`index.html`)

Satu halaman dengan dua layar: login dan aplikasi. Header selalu memuat identitas. Form login punya `label` yang terhubung ke `input` lewat `for` dan `id`. Modal tambah/ubah dan modal hapus memakai elemen `<dialog>`. Tabel untuk laptop dan wadah kartu untuk ponsel diisi JavaScript yang sama.

### CSS (`css/style.css`)

Variabel di `:root`:

- `--warna-utama` biru tua `#0e2744`, seperti jas almamater
- `--warna-aksen` emas `#c6a15b`, sebagai garis dan tombol
- `--warna-teal` `#1f6f64`, sebagai aksen kartu
- latar kertas `#f3efe4` agar tidak seperti tema Bootstrap bawaan

Font judul **Fraunces**, font isi **Source Sans 3**, keduanya dari Google Fonts. Di bawah 800px, tabel disembunyikan, kartu ditampilkan, dan tombol selebar layar supaya tidak meluber. `body[data-role="user"] .khusus-admin` menyembunyikan aksi admin.

Bootstrap 5 dipakai untuk dasar form dan reset. Ikon dari Bootstrap Icons. Warna, kartu, dan tombol ditulis sendiri di `style.css`.

### JavaScript

- `api.js`: konstanta `API_BASE` (kosong = host yang sama). `minta()` melakukan `fetch`, menambahkan `Authorization: Bearer` dari `sessionStorage`, dan mengembalikan status, JSON, serta `Retry-After`.
- `ui.js`: toast, pesan error per field, gambar tabel/kartu, dan pelolosan HTML.
- `app.js`: alur. Submit login → `POST /login` → simpan token → `GET /pengumuman`. Status 401 di luar form login menghapus token dan kembali ke layar masuk. 403, 409, 422, dan 429 punya kalimat sendiri. 429 mematikan tombol login selama detik di `Retry-After`.

`sessionStorage` dipilih karena isinya hilang saat tab ditutup. Di komputer lab kampus, token tidak tertinggal seperti `localStorage`.

Role dibaca dari respons `/me` (dan dari objek user saat login). Bila `role` bukan `admin`, tombol bertanda `khusus-admin` diberi `hidden` dan CSS menyembunyikannya. Server tetap menolak dengan 403 bila request tetap dikirim.

## Pertanyaan yang mungkin ditanyakan

1. **Mengapa pesan login salah dibuat sama?**  
   Supaya orang tidak bisa menebak username mana yang terdaftar. Waktu cek hash juga disamakan dengan hash dummy.

2. **Apa bedanya 401 dan 403?**  
   401 berarti identitas belum sah (belum login, token rusak, atau token mati). 403 berarti identitas sah, tetapi role tidak boleh melakukan aksi itu.

3. **Mengapa 409 bukan 422?**  
   422 berarti bentuk data jelek (kosong, tanggal salah, angka negatif). 409 berarti bentuknya sah, tetapi `kode_info` bentrok dengan data yang sudah ada.

4. **Di mana prepared statement dipakai?**  
   Setiap `where`, `create`, `update`, dan `find` Eloquent. Nilai dari pengguna diikat PDO, tidak digabung ke string SQL.

5. **Apa fungsi `exp`?**  
   Batas waktu token. Setelah lewat, token ditolak walaupun tanda tangannya benar.

6. **Mengapa token ditaruh di `sessionStorage`?**  
   Token hilang ketika tab ditutup, jadi lebih aman untuk komputer bersama daripada `localStorage`.

7. **Apa itu CDN dan mengapa Bootstrap dipakai?**  
   CDN adalah server publik yang menyajikan file CSS/ikon. Bootstrap dipakai untuk dasar form dan grid. Tampilan akhirnya ditimpa palet dan komponen sendiri, bukan tema jadi.

8. **Mengapa tidak memakai Sanctum?**  
   Soal meminta JWT HS256 yang bisa dijelaskan. Sanctum memakai cookie/session atau token lain. `firebase/php-jwt` hanya mengurus tanda tangan, jadi alurnya pendek.

9. **Mengapa ada hash dummy?**  
   `Hash::check` butuh waktu. Jika username tidak ada dan kita langsung menolak, responsnya lebih cepat. Perbedaan waktu itu bisa dipakai untuk menebak username. Hash dummy membuat kedua jalur sama-sama memanggil bcrypt.

10. **Apa isi middleware `jwt.auth`?**  
    Baca header, pastikan diawali `Bearer `, verifikasi tanda tangan dan `exp`, lalu ambil user dari database. Gagal di salah satu langkah menghasilkan 401.

11. **Mengapa role dicek lagi di database?**  
    Role di token bisa saja sudah tidak sesuai bila suatu saat role di tabel berubah. Keputusan izin memakai kolom `users.role`.

12. **Bagaimana 400 berbeda dari 404 pada `/pengumuman/{id}`?**  
    400 = id-nya bukan angka positif (`abc`, `-1`). 404 = angkanya sah, tetapi tidak ada baris dengan id itu.

13. **Mengapa log tidak boleh menggagalkan request?**  
    Log adalah catatan tambahan. Bila database log gagal, pengguna tetap harus menerima respons API. Penulisan log dibungkus `try/catch`.

14. **Apa yang disimpan di `log_api.username` saat login gagal?**  
    Username dari body, bukan password. Bila tidak ada token yang sah dan bukan login, nilainya `NULL`.

15. **Mengapa `APP_DEBUG` dimatikan pada contoh produksi?**  
    Halaman debug Laravel bisa menampilkan SQL. API ini juga memaksa pesan 500 menjadi `Terjadi kesalahan pada server`.

16. **Jelaskan kelompok CSS `:root`.**  
    Itu palet bersama. Mengganti satu variabel mengubah tombol, header, dan fokus sekaligus, jadi warna tetap konsisten.

17. **Bagaimana client menampilkan error 422?**  
    Server mengirim `errors` berbentuk `{ "kolom": ["pesan"] }`. `ui.js` mencari `<small data-error-for="kolom">` dan menuliskan pesannya di bawah input.

18. **Apa yang terjadi bila user tetap memanggil POST `/pengumuman` dari konsol browser?**  
    Tombolnya tersembunyi, tetapi server tetap menjawab 403. Menyembunyikan tombol bukan pengaman; pengamannya ada di `RoleAdmin`.
