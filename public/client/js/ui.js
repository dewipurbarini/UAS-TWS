/**
 * Urusan tampilan: toast, error field, daftar, dan log.
 * Tidak memanggil server. app.js yang menghubungkan event ke API.
 */
const Ui = {
    /**
     * Menampilkan pemberitahuan singkat.
     * Parameter: jenis (sukses/gagal/info) dan teks.
     */
    toast(jenis, teks) {
        const kotak = document.getElementById('toast');
        kotak.hidden = false;
        kotak.className = 'toast ' + jenis;
        kotak.textContent = teks;
        clearTimeout(Ui._timerToast);
        // Hilang sendiri agar tidak menutupi layar.
        Ui._timerToast = setTimeout(() => {
            kotak.hidden = true;
        }, 3600);
    },

    /**
     * Mengosongkan pesan error di bawah input dalam satu form.
     */
    bersihkanError(form) {
        form.querySelectorAll('.error-field').forEach((el) => {
            el.textContent = '';
        });
    },

    /**
     * Menulis error 422 di bawah input yang namanya sama dengan kunci errors.
     * Parameter: form dan objek errors dari server.
     */
    tampilkanError(form, errors) {
        Ui.bersihkanError(form);
        if (!errors) {
            return;
        }
        Object.keys(errors).forEach((kolom) => {
            const tempat = form.querySelector('[data-error-for="' + kolom + '"]');
            const daftar = errors[kolom];
            if (tempat && Array.isArray(daftar)) {
                tempat.textContent = daftar.join(' ');
            }
        });
    },

    /**
     * Kelas warna badge dari nama kategori.
     */
    kelasKategori(nama) {
        const kunci = (nama || '').toLowerCase();
        const peta = {
            akademik: 'kat-akademik',
            beasiswa: 'kat-beasiswa',
            kemahasiswaan: 'kat-kemahasiswaan',
            umum: 'kat-umum',
            fasilitas: 'kat-fasilitas',
        };
        return peta[kunci] || 'kat-lain';
    },

    /**
     * Ikon kecil di samping kategori.
     */
    ikonKategori(nama) {
        const kunci = (nama || '').toLowerCase();
        const peta = {
            akademik: 'bi-mortarboard',
            beasiswa: 'bi-award',
            kemahasiswaan: 'bi-people',
            umum: 'bi-megaphone',
            fasilitas: 'bi-building',
        };
        return peta[kunci] || 'bi-info-circle';
    },

    /**
     * Menggambar tabel (laptop) dan kartu (ponsel) dari array yang sudah disaring.
     * Parameter: daftar pengumuman, dan apakah akun ini admin.
     */
    gambarPengumuman(daftar, admin) {
        const tabel = document.getElementById('isi-tabel');
        const kartu = document.getElementById('isi-kartu');
        tabel.innerHTML = '';
        kartu.innerHTML = '';

        daftar.forEach((item) => {
            const badge = '<span class="badge ' + Ui.kelasKategori(item.kategori) + '"><i class="bi ' + Ui.ikonKategori(item.kategori) + '"></i> ' + Ui.aman(item.kategori) + '</span>';
            // Tombol aksi hanya disusun bila admin. CSS juga menyembunyikannya untuk user.
            const aksi = admin
                ? '<td class="khusus-admin"><div class="aksi">' +
                    '<button type="button" class="ikon-aksi" data-ubah="' + item.id + '" aria-label="Ubah"><i class="bi bi-pencil"></i></button>' +
                    '<button type="button" class="ikon-aksi" data-hapus="' + item.id + '" aria-label="Hapus"><i class="bi bi-trash"></i></button>' +
                  '</div></td>'
                : '';

            const baris = document.createElement('tr');
            baris.innerHTML =
                '<td>' + Ui.aman(item.kode_info) + '</td>' +
                '<td>' + Ui.aman(item.judul) + '</td>' +
                '<td>' + badge + '</td>' +
                '<td>' + Ui.aman(item.tanggal) + '</td>' +
                '<td>' + Ui.aman(item.penulis) + '</td>' +
                '<td><i class="bi bi-eye"></i> ' + Ui.aman(item.jumlah_dibaca) + '</td>' +
                aksi;
            tabel.appendChild(baris);

            const kartuItem = document.createElement('article');
            kartuItem.className = 'kartu-item';
            kartuItem.innerHTML =
                badge +
                '<h3>' + Ui.aman(item.judul) + '</h3>' +
                '<p class="meta">' + Ui.aman(item.kode_info) + ' · ' + Ui.aman(item.tanggal) + '</p>' +
                '<p class="meta"><i class="bi bi-person"></i> ' + Ui.aman(item.penulis) +
                ' · <i class="bi bi-eye"></i> ' + Ui.aman(item.jumlah_dibaca) + ' dibaca</p>' +
                (admin
                    ? '<div class="aksi"><button type="button" class="tombol tombol-ringan" data-ubah="' + item.id + '"><i class="bi bi-pencil"></i> Ubah</button>' +
                      '<button type="button" class="tombol tombol-bahaya" data-hapus="' + item.id + '"><i class="bi bi-trash"></i> Hapus</button></div>'
                    : '');
            kartu.appendChild(kartuItem);
        });
    },

    /**
     * Menggambar tabel dan kartu log.
     */
    gambarLog(daftar) {
        const tabel = document.getElementById('isi-log');
        const kartu = document.getElementById('isi-log-kartu');
        tabel.innerHTML = '';
        kartu.innerHTML = '';
        daftar.forEach((item) => {
            const keluarga = String(item.status).charAt(0);
            const status = '<span class="status-pill s-' + keluarga + '">' + Ui.aman(item.status) + '</span>';
            const baris = document.createElement('tr');
            baris.innerHTML =
                '<td>' + Ui.aman(Ui.waktu(item.waktu)) + '</td>' +
                '<td>' + Ui.aman(item.method) + '</td>' +
                '<td>' + Ui.aman(item.path) + '</td>' +
                '<td>' + status + '</td>' +
                '<td>' + Ui.aman(item.ip) + '</td>' +
                '<td>' + Ui.aman(item.username || '—') + '</td>' +
                '<td>' + Ui.aman(item.pesan) + '</td>';
            tabel.appendChild(baris);

            const kartuItem = document.createElement('article');
            kartuItem.className = 'kartu-item';
            kartuItem.innerHTML =
                '<p class="meta">' + Ui.aman(Ui.waktu(item.waktu)) + '</p>' +
                '<h3>' + Ui.aman(item.method) + ' ' + Ui.aman(item.path) + '</h3>' +
                '<p>' + status + ' · ' + Ui.aman(item.username || 'tanpa akun') + '</p>' +
                '<p class="meta">' + Ui.aman(item.pesan) + '</p>';
            kartu.appendChild(kartuItem);
        });
    },

    /**
     * Mengubah tanggal dari JSON (kadang berisi jam) menjadi YYYY-MM-DD untuk tampilan.
     */
    waktu(nilai) {
        if (!nilai) {
            return '';
        }
        return String(nilai).replace('T', ' ').slice(0, 19);
    },

    /**
     * Menghindari HTML dari data pengguna masuk ke innerHTML.
     */
    aman(nilai) {
        return String(nilai ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');
    },
};

window.Ui = Ui;
