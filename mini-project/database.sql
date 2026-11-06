-- Membuat basis data khusus pengumuman kampus bila belum ada.
CREATE DATABASE IF NOT EXISTS pengumuman_kampus
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Memakai basis data tersebut untuk perintah berikutnya.
USE pengumuman_kampus;

-- Menghapus tabel lama agar berkas ini bisa diimpor ulang dari awal.
DROP TABLE IF EXISTS login_gagal;
DROP TABLE IF EXISTS log_api;
DROP TABLE IF EXISTS pengumuman;
DROP TABLE IF EXISTS users;

-- Tabel akun: sandi disimpan sebagai hash bcrypt, bukan teks biasa.
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin', 'user') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel objek tugas. kode_info adalah kolom pertama yang wajib unik.
CREATE TABLE pengumuman (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode_info VARCHAR(20) NOT NULL UNIQUE,
  judul VARCHAR(150) NOT NULL,
  kategori VARCHAR(50) NOT NULL,
  tanggal DATE NOT NULL,
  penulis VARCHAR(100) NOT NULL,
  prioritas INT NOT NULL,
  ringkasan VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Setiap permintaan API dicatat di sini.
CREATE TABLE log_api (
  id INT AUTO_INCREMENT PRIMARY KEY,
  waktu DATETIME NOT NULL,
  method VARCHAR(10) NOT NULL,
  path VARCHAR(255) NOT NULL,
  status INT NOT NULL,
  ip VARCHAR(45) NOT NULL,
  username VARCHAR(50) NULL,
  pesan VARCHAR(255) NOT NULL
);

-- Jejak login yang gagal, dipakai untuk batas percobaan per alamat IP.
CREATE TABLE login_gagal (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  waktu DATETIME NOT NULL,
  INDEX idx_login_gagal_ip_waktu (ip, waktu)
);

-- Akun admin. Sandi asli: Admin23676003
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$C4baoUEsO/NEijEgy1pAnuklqejPxu8mPm/2ti6yDKh73OTiBvL2q', 'admin');

-- Akun user biasa. Sandi asli: User23676003
INSERT INTO users (username, password, role) VALUES
('user', '$2y$10$ghnlwY2EnXcnZXLuddITsevYPRdgbw9T2ZYecSQls2938hHzTLKzO', 'user');

-- Enam pengumuman awal yang masuk akal untuk kampus.
INSERT INTO pengumuman (kode_info, judul, kategori, tanggal, penulis, prioritas, ringkasan) VALUES
('INF-2026-001', 'Pembukaan Beasiswa Prestasi Ganjil', 'Beasiswa', '2026-11-10', 'Bagian Kemahasiswaan', 5, 'Mahasiswa semester 3 ke atas dapat mengunggah transkrip sampai 20 November 2026.'),
('INF-2026-002', 'Perubahan Jadwal Ujian Tengah Semester', 'Akademik', '2026-11-12', 'Biro Akademik', 4, 'UTS mata kuliah Teknologi Web Service dimajukan menjadi pukul 08.00 di ruang B2.'),
('INF-2026-003', 'Open Recruitment Unit Kegiatan Mahasiswa', 'Kemahasiswaan', '2026-11-15', 'Dewan Mahasiswa', 3, 'Pendaftaran UKM dibuka selama dua minggu melalui meja informasi lantai 1.'),
('INF-2026-004', 'Pemadaman Listrik Gedung C', 'Umum', '2026-11-18', 'Bagian Sarana', 2, 'Listrik gedung C padam pukul 12.00 sampai 15.00 untuk perawatan panel.'),
('INF-2026-005', 'Batas Unggah Laporan Praktikum', 'Akademik', '2026-11-20', 'Laboratorium Komputer', 4, 'Laporan praktikum pertemuan 7 diunggah sebelum pukul 23.59 pada tanggal tersebut.'),
('INF-2026-006', 'Sosialisasi Beasiswa Daerah', 'Beasiswa', '2026-11-22', 'Pusat Karier', 1, 'Sosialisasi di aula utama, terbuka untuk semua angkatan tanpa seleksi administrasi.');
