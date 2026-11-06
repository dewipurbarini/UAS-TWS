// Alamat API. Kosong berarti host yang sama dengan halaman ini (http://localhost:8000).
// Ubah konstanta ini bila API pindah ke server lain.
const API_BASE = '';

/**
 * Menyimpan dan membaca token.
 * sessionStorage dipilih karena hilang saat tab ditutup.
 * Lebih aman di komputer lab daripada localStorage yang menetap.
 */
const Token = {
    ambil() {
        return sessionStorage.getItem('token_pengumuman');
    },
    simpan(token) {
        sessionStorage.setItem('token_pengumuman', token);
    },
    hapus() {
        sessionStorage.removeItem('token_pengumuman');
    },
};

/**
 * Mengirim request ke API.
 * Parameter: path (misalnya /login), opsi { method, body, pakaiToken }.
 * Kembalian: objek { status, data, retryAfter }.
 * Melempar Error bila jaringan putus atau respons bukan JSON.
 */
async function minta(path, opsi = {}) {
    // Header JSON. Accept juga dipaksa di server, tetapi kita kirim dari sini juga.
    const header = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    };

    // Token ikut hanya bila diminta dan memang ada.
    const pakaiToken = opsi.pakaiToken !== false;
    const token = Token.ambil();
    if (pakaiToken && token) {
        header.Authorization = 'Bearer ' + token;
    }

    let respons;
    try {
        // fetch mengirim method, header, dan body. Body dikosongkan untuk GET.
        respons = await fetch(API_BASE + path, {
            method: opsi.method || 'GET',
            headers: header,
            body: opsi.body ? JSON.stringify(opsi.body) : undefined,
        });
    } catch (e) {
        // Gagal sebelum ada status HTTP: server mati atau jaringan putus.
        const error = new Error('Tidak dapat terhubung ke server. Periksa koneksi Anda.');
        error.jaringan = true;
        throw error;
    }

    let data = {};
    try {
        data = await respons.json();
    } catch (e) {
        // Server mengirim HTML atau teks kosong. Jangan tampilkan mentah ke pengguna.
        data = { sukses: false, pesan: 'Terjadi gangguan pada server. Coba lagi nanti.' };
    }

    return {
        status: respons.status,
        data: data,
        // Dipakai hitung mundur saat 429.
        retryAfter: respons.headers.get('Retry-After'),
    };
}

// Dibuka agar file app.js bisa memakai fungsi ini tanpa modul.
window.Api = { minta, Token };
