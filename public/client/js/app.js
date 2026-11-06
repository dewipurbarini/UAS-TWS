/**
 * Alur utama halaman.
 * Urutan: cek token → /me → muat pengumuman → sambungkan tombol.
 */
const keadaan = {
    akun: null,
    data: [],
    idHapus: null,
    timer429: null,
};

const el = (id) => document.getElementById(id);

/**
 * Menyalakan atau mematikan kerangka loading daftar.
 */
function setMuat(nyala) {
    el('kerangka').hidden = !nyala;
}

/**
 * Pesan untuk status yang perlu kalimat tetap, sesuai soal.
 * 401 di luar form login mengembalikan pengguna ke layar masuk.
 */
function tanganiStatus(hasil, form) {
    const status = hasil.status;
    const pesan = (hasil.data && hasil.data.pesan) || 'Terjadi gangguan.';

    if (status === 401 && form !== el('form-login')) {
        window.Api.Token.hapus();
        tampilkanLogin();
        Ui.toast('gagal', 'Sesi berakhir / login gagal');
        return true;
    }
    if (status === 403) {
        Ui.toast('gagal', 'Anda tidak punya akses untuk aksi ini');
        return true;
    }
    if (status === 409) {
        Ui.toast('gagal', 'Kode info sudah dipakai');
        if (form) {
            Ui.tampilkanError(form, { kode_info: ['Kode info sudah dipakai'] });
        }
        return true;
    }
    if (status === 422 && form) {
        Ui.tampilkanError(form, hasil.data.errors || {});
        Ui.toast('gagal', pesan);
        return true;
    }
    if (status === 429) {
        return false;
    }
    if (status >= 500) {
        Ui.toast('gagal', 'Terjadi gangguan pada server. Coba lagi nanti.');
        return true;
    }
    if (status >= 400) {
        Ui.toast('gagal', pesan);
        return true;
    }
    return false;
}

/**
 * Menampilkan layar login dan menyembunyikan aplikasi.
 */
function tampilkanLogin() {
    el('layar-login').hidden = false;
    el('layar-app').hidden = true;
    el('area-akun').hidden = true;
    document.body.dataset.role = '';
    keadaan.akun = null;
}

/**
 * Menampilkan aplikasi setelah /me berhasil.
 */
function tampilkanApp() {
    el('layar-login').hidden = true;
    el('layar-app').hidden = false;
    el('area-akun').hidden = false;
    el('nama-akun').textContent = keadaan.akun.nama;
    el('peran-akun').textContent = keadaan.akun.role;
    // data-role dipakai CSS untuk menyembunyikan tombol admin.
    document.body.dataset.role = keadaan.akun.role;
    const admin = keadaan.akun.role === 'admin';
    document.querySelectorAll('.khusus-admin').forEach((node) => {
        node.hidden = !admin;
    });
}

/**
 * Login: event submit → fetch POST /login → simpan token atau tampilkan error.
 */
async function prosesLogin(event) {
    event.preventDefault();
    const form = el('form-login');
    Ui.bersihkanError(form);
    el('pesan-login').textContent = '';
    const tombol = el('tombol-masuk');
    tombol.disabled = true;

    try {
        const hasil = await window.Api.minta('/login', {
            method: 'POST',
            pakaiToken: false,
            body: {
                username: el('username').value.trim(),
                password: el('password').value,
            },
        });

        if (hasil.status === 429) {
            mulaiHitungMundur(hasil);
            return;
        }
        if (hasil.status === 401) {
            el('pesan-login').className = 'pesan-form gagal';
            el('pesan-login').textContent = 'Sesi berakhir / login gagal. ' + (hasil.data.pesan || '');
            return;
        }
        if (tanganiStatus(hasil, form)) {
            return;
        }

        window.Api.Token.simpan(hasil.data.data.token);
        keadaan.akun = hasil.data.data.user;
        el('pesan-login').className = 'pesan-form sukses';
        el('pesan-login').textContent = '';
        tampilkanApp();
        Ui.toast('sukses', 'Login berhasil');
        await muatPengumuman();
    } catch (error) {
        el('pesan-login').className = 'pesan-form gagal';
        el('pesan-login').textContent = error.message;
    } finally {
        // Jangan mengaktifkan tombol bila hitung mundur 429 masih berjalan.
        if (!keadaan.timer429) {
            tombol.disabled = false;
        }
    }
}

/**
 * Menonaktifkan tombol login dan menghitung detik dari header Retry-After.
 */
function mulaiHitungMundur(hasil) {
    let sisa = parseInt(hasil.retryAfter || '60', 10);
    if (Number.isNaN(sisa) || sisa < 1) {
        sisa = 60;
    }
    const tombol = el('tombol-masuk');
    const pesan = el('pesan-login');
    clearInterval(keadaan.timer429);
    tombol.disabled = true;
    pesan.className = 'pesan-form gagal';

    const tulis = () => {
        pesan.textContent = 'Terlalu banyak percobaan. Coba lagi dalam ' + sisa + ' detik.';
    };
    tulis();
    keadaan.timer429 = setInterval(() => {
        sisa -= 1;
        if (sisa <= 0) {
            clearInterval(keadaan.timer429);
            keadaan.timer429 = null;
            tombol.disabled = false;
            pesan.textContent = 'Silakan coba masuk lagi.';
            return;
        }
        tulis();
    }, 1000);
}

/**
 * Keluar: hapus token dan kembali ke login. Server JWT tidak punya daftar token yang dicabut.
 */
function keluar() {
    window.Api.Token.hapus();
    tampilkanLogin();
    Ui.toast('info', 'Anda sudah keluar');
}

/**
 * Mengambil daftar, lalu mengisi filter kategori dan menggambar ulang.
 */
async function muatPengumuman() {
    setMuat(true);
    el('kosong').hidden = true;
    try {
        const hasil = await window.Api.minta('/pengumuman');
        if (tanganiStatus(hasil)) {
            keadaan.data = [];
            return;
        }
        keadaan.data = hasil.data.data || [];
        isiKategori();
        gambarUlang();
    } catch (error) {
        Ui.toast('gagal', error.message);
    } finally {
        setMuat(false);
    }
}

/**
 * Pilihan kategori diambil dari data yang ada, ditambah pilihan "Semua".
 */
function isiKategori() {
    const select = el('filter-kategori');
    const sekarang = select.value;
    const unik = [...new Set(keadaan.data.map((item) => item.kategori))].sort();
    select.innerHTML = '<option value="">Semua</option>';
    unik.forEach((nama) => {
        const opsi = document.createElement('option');
        opsi.value = nama;
        opsi.textContent = nama;
        select.appendChild(opsi);
    });
    select.value = sekarang;
}

/**
 * Menyaring dan mengurutkan di browser. Server tetap mengirim daftar lengkap.
 */
function gambarUlang() {
    const cari = el('kotak-cari').value.trim().toLowerCase();
    const kategori = el('filter-kategori').value;
    const urutan = el('urutan').value;
    let daftar = keadaan.data.filter((item) => {
        const teks = (item.judul + ' ' + item.kode_info + ' ' + item.penulis).toLowerCase();
        const lolosCari = cari === '' || teks.includes(cari);
        const lolosKategori = kategori === '' || item.kategori === kategori;
        return lolosCari && lolosKategori;
    });

    daftar.sort((a, b) => {
        if (urutan === 'terlama') {
            return String(a.tanggal).localeCompare(String(b.tanggal));
        }
        if (urutan === 'dibaca') {
            return b.jumlah_dibaca - a.jumlah_dibaca;
        }
        if (urutan === 'judul') {
            return String(a.judul).localeCompare(String(b.judul), 'id');
        }
        return String(b.tanggal).localeCompare(String(a.tanggal));
    });

    const admin = keadaan.akun && keadaan.akun.role === 'admin';
    Ui.gambarPengumuman(daftar, admin);
    el('jumlah-data').textContent = daftar.length + ' dari ' + keadaan.data.length + ' pengumuman';
    const kosong = el('kosong');
    kosong.hidden = daftar.length !== 0;
    el('teks-kosong').textContent = keadaan.data.length === 0
        ? 'Belum ada pengumuman.'
        : 'Tidak ada pengumuman yang cocok dengan pencarian.';
}

/**
 * Membuka modal. Bila item ada, form diisi untuk ubah.
 */
function bukaForm(item) {
    const form = el('form-data');
    Ui.bersihkanError(form);
    el('judul-modal').textContent = item ? 'Ubah pengumuman' : 'Tambah pengumuman';
    el('id-data').value = item ? item.id : '';
    el('kode_info').value = item ? item.kode_info : '';
    el('judul').value = item ? item.judul : '';
    el('kategori').value = item ? item.kategori : '';
    el('tanggal').value = item ? String(item.tanggal).slice(0, 10) : '';
    el('penulis').value = item ? item.penulis : '';
    el('jumlah_dibaca').value = item ? item.jumlah_dibaca : '0';
    el('modal-form').showModal();
}

function tutupForm() {
    el('modal-form').close();
}

/**
 * Simpan: POST bila tidak ada id, PUT bila ada.
 * Loading ada di tombol simpan.
 */
async function simpan(event) {
    event.preventDefault();
    const form = el('form-data');
    const tombol = el('tombol-simpan');
    const id = el('id-data').value;
    const body = {
        kode_info: el('kode_info').value.trim(),
        judul: el('judul').value.trim(),
        kategori: el('kategori').value.trim(),
        tanggal: el('tanggal').value,
        penulis: el('penulis').value.trim(),
        jumlah_dibaca: el('jumlah_dibaca').value === '' ? '' : Number(el('jumlah_dibaca').value),
    };
    tombol.disabled = true;
    tombol.innerHTML = '<i class="bi bi-arrow-repeat"></i> Menyimpan...';
    try {
        const hasil = await window.Api.minta(id ? '/pengumuman/' + id : '/pengumuman', {
            method: id ? 'PUT' : 'POST',
            body,
        });
        if (tanganiStatus(hasil, form)) {
            return;
        }
        tutupForm();
        Ui.toast('sukses', hasil.data.pesan || 'Tersimpan');
        await muatPengumuman();
    } catch (error) {
        Ui.toast('gagal', error.message);
    } finally {
        tombol.disabled = false;
        tombol.innerHTML = '<i class="bi bi-check2"></i> Simpan';
    }
}

function bukaHapus(item) {
    keadaan.idHapus = item.id;
    el('teks-hapus').textContent = 'Hapus "' + item.judul + '" (' + item.kode_info + ')? Data tidak dikembalikan dari halaman ini.';
    el('modal-hapus').showModal();
}

/**
 * Hapus setelah pengguna menekan tombol di modal konfirmasi.
 */
async function hapusYa() {
    const tombol = el('tombol-hapus-ya');
    tombol.disabled = true;
    try {
        const hasil = await window.Api.minta('/pengumuman/' + keadaan.idHapus, { method: 'DELETE' });
        if (tanganiStatus(hasil)) {
            return;
        }
        el('modal-hapus').close();
        Ui.toast('sukses', hasil.data.pesan || 'Terhapus');
        await muatPengumuman();
    } catch (error) {
        Ui.toast('gagal', error.message);
    } finally {
        tombol.disabled = false;
    }
}

/**
 * Panel log hanya dipakai admin. Tetap memanggil server; bila user memaksa, server membalas 403.
 */
async function muatLog() {
    el('kerangka-log').hidden = false;
    try {
        const hasil = await window.Api.minta('/log?limit=50');
        if (tanganiStatus(hasil)) {
            return;
        }
        const daftar = hasil.data.data || [];
        Ui.gambarLog(daftar);
        el('jumlah-log').textContent = daftar.length + ' catatan terbaru';
    } catch (error) {
        Ui.toast('gagal', error.message);
    } finally {
        el('kerangka-log').hidden = true;
    }
}

function gantiTab(nama) {
    const data = nama === 'data';
    el('panel-data').hidden = !data;
    el('panel-log').hidden = data;
    el('tab-data').classList.toggle('tab-aktif', data);
    el('tab-log').classList.toggle('tab-aktif', !data);
    if (!data) {
        muatLog();
    }
}

/**
 * Klik ubah/hapus bisa datang dari tabel atau kartu. Mencari data-ubah / data-hapus.
 */
function klikDaftar(event) {
    const ubah = event.target.closest('[data-ubah]');
    const hapus = event.target.closest('[data-hapus]');
    if (ubah) {
        const item = keadaan.data.find((row) => String(row.id) === ubah.dataset.ubah);
        if (item) {
            bukaForm(item);
        }
    }
    if (hapus) {
        const item = keadaan.data.find((row) => String(row.id) === hapus.dataset.hapus);
        if (item) {
            bukaHapus(item);
        }
    }
}

/**
 * Saat halaman dibuka: bila ada token, coba /me. 401 berarti sesi tidak berlaku.
 */
async function mulai() {
    el('form-login').addEventListener('submit', prosesLogin);
    el('tombol-keluar').addEventListener('click', keluar);
    el('tombol-tambah').addEventListener('click', () => bukaForm(null));
    el('tombol-tambah-kosong').addEventListener('click', () => bukaForm(null));
    el('form-data').addEventListener('submit', simpan);
    el('tutup-form').addEventListener('click', tutupForm);
    el('batal-form').addEventListener('click', tutupForm);
    el('tutup-hapus').addEventListener('click', () => el('modal-hapus').close());
    el('batal-hapus').addEventListener('click', () => el('modal-hapus').close());
    el('tombol-hapus-ya').addEventListener('click', hapusYa);
    el('tab-data').addEventListener('click', () => gantiTab('data'));
    el('tab-log').addEventListener('click', () => gantiTab('log'));
    el('tombol-muat-log').addEventListener('click', muatLog);
    el('kotak-cari').addEventListener('input', gambarUlang);
    el('filter-kategori').addEventListener('change', gambarUlang);
    el('urutan').addEventListener('change', gambarUlang);
    el('isi-tabel').addEventListener('click', klikDaftar);
    el('isi-kartu').addEventListener('click', klikDaftar);

    const token = window.Api.Token.ambil();
    if (!token) {
        tampilkanLogin();
        return;
    }
    try {
        const hasil = await window.Api.minta('/me');
        if (hasil.status === 401) {
            window.Api.Token.hapus();
            tampilkanLogin();
            Ui.toast('gagal', 'Sesi berakhir / login gagal');
            return;
        }
        keadaan.akun = hasil.data.data;
        tampilkanApp();
        await muatPengumuman();
    } catch (error) {
        tampilkanLogin();
        Ui.toast('gagal', error.message);
    }
}

mulai();
