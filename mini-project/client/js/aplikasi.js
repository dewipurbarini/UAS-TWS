// Alamat API adalah folder di atas halaman client, tanpa garis miring di ujung.
const API = new URL("..", window.location.href).pathname.replace(/\/$/, "");

// Keadaan halaman yang berubah selama pengguna memakai aplikasi.
const keadaan = {
  token: sessionStorage.getItem("token") || "", // token JWT, hilang bila tab ditutup
  pengguna: null, // objek id, username, dan role dari GET /me
  data: [], // daftar pengumuman terakhir
  idHapus: null, // id yang menunggu konfirmasi hapus
  mode: "tambah", // "tambah" atau "ubah"
};

// Mengambil elemen sekali agar pemanggilan berikutnya memakai objek yang sama.
const elemen = {
  layarMasuk: document.querySelector("#layar-masuk"), // halaman login
  layarAplikasi: document.querySelector("#layar-aplikasi"), // halaman data
  formulirMasuk: document.querySelector("#formulir-masuk"), // form login
  pesanMasuk: document.querySelector("#pesan-masuk"), // teks gagal login
  tombolMasuk: document.querySelector("#tombol-masuk"), // tombol login
  namaPengguna: document.querySelector("#nama-pengguna"), // username di kepala
  peranPengguna: document.querySelector("#peran-pengguna"), // lencana role
  tombolKeluar: document.querySelector("#tombol-keluar"), // logout
  tombolTambah: document.querySelector("#tombol-tambah"), // buka form tambah
  tombolLog: document.querySelector("#tombol-log"), // buka tabel log
  saringanTeks: document.querySelector("#saringan-teks"), // kotak cari
  saringanKategori: document.querySelector("#saringan-kategori"), // pilih kategori
  statusDaftar: document.querySelector("#status-daftar"), // kalimat memuat atau kosong
  daftarKartu: document.querySelector("#daftar-kartu"), // wadah kartu
  panelLog: document.querySelector("#panel-log"), // blok tabel log
  isiLog: document.querySelector("#isi-log"), // tubuh tabel log
  modalFormulir: document.querySelector("#modal-formulir"), // modal tambah/ubah
  formulirData: document.querySelector("#formulir-data"), // form data
  judulModal: document.querySelector("#judul-modal"), // judul modal
  pesanFormulir: document.querySelector("#pesan-formulir"), // pesan 422 atau 409
  batalFormulir: document.querySelector("#batal-formulir"), // tutup form
  modalHapus: document.querySelector("#modal-hapus"), // modal konfirmasi
  teksHapus: document.querySelector("#teks-hapus"), // kalimat konfirmasi
  batalHapus: document.querySelector("#batal-hapus"), // batal hapus
  yaHapus: document.querySelector("#ya-hapus"), // setuju hapus
  wadahToast: document.querySelector("#wadah-toast"), // tumpukan pemberitahuan
};

// True bila akun yang masuk berrole admin.
function adalahAdmin() {
  // Role diambil dari respons server, bukan dari pilihan di layar.
  return keadaan.pengguna && keadaan.pengguna.role === "admin";
}

// Menampilkan toast singkat di pojok bawah.
function toast(pesan, gagal = false) {
  // Buat satu elemen pemberitahuan.
  const kotak = document.createElement("div");
  // Kelas dasar, ditambah kelas gagal bila diminta.
  kotak.className = gagal ? "toast toast-gagal" : "toast";
  // Isi teks pemberitahuan.
  kotak.textContent = pesan;
  // Tampilkan di wadah.
  elemen.wadahToast.appendChild(kotak);
  // Hilangkan sendiri setelah empat detik.
  setTimeout(() => kotak.remove(), 4000);
}

// Pesan yang dipakai bersama untuk kode status yang diminta tugas.
function pesanStatus(status, tubuh, header) {
  // 401 berarti token hilang, rusak, atau login salah.
  if (status === 401) {
    // Pakai kalimat server bila ada.
    return tubuh.pesan || "Token tidak ditemukan atau tidak berlaku";
  }
  // 403 berarti role tidak cukup.
  if (status === 403) {
    // Kalimat hak akses.
    return tubuh.pesan || "Hanya admin yang boleh melakukan aksi ini";
  }
  // 409 berarti kode unik bentrok.
  if (status === 409) {
    // Kalimat konflik.
    return tubuh.pesan || "Kode info sudah dipakai";
  }
  // 422 means validation failed; gabungkan pesan field.
  if (status === 422) {
    // errors adalah objek nama field ke kalimat.
    const daftar = tubuh.errors ? Object.values(tubuh.errors).join(" ") : "";
    // Gabung kalimat umum dan rincian field.
    return (tubuh.pesan || "Validasi gagal") + (daftar ? ": " + daftar : "");
  }
  // 429 berarti terlalu banyak gagal login.
  if (status === 429) {
    // Retry-After dikirim server dalam detik.
    const tunggu = header.get("Retry-After");
    // Tambahkan detik bila header ada.
    return (tubuh.pesan || "Terlalu banyak percobaan") + (tunggu ? " Coba lagi dalam " + tunggu + " detik." : "");
  }
  // Status lain memakai pesan server atau kalimat umum.
  return tubuh.pesan || "Permintaan gagal";
}

// Memanggil API dengan token Bearer bila sudah login.
async function api(jalur, opsi = {}) {
  // Header JSON dipakai semua permintaan yang punya tubuh.
  const header = { "Content-Type": "application/json" };
  // Token hanya dipasang bila ada.
  if (keadaan.token) {
    // Bentuk header yang diwajibkan tugas.
    header.Authorization = "Bearer " + keadaan.token;
  }
  // Kirim permintaan ke folder API.
  const respons = await fetch(API + jalur, {
    method: opsi.method || "GET", // metode bawaan GET
    headers: header, // sertakan tipe dan token
    body: opsi.body ? JSON.stringify(opsi.body) : undefined, // JSON hanya bila ada tubuh
  });
  // Baca teks dulu agar respons kosong tidak membuat JSON.parse melempar.
  const teks = await respons.text();
  // Tubuh kosong menjadi objek kosong.
  let tubuh = {};
  // Urai JSON bila ada isi.
  if (teks) {
    // Bungkus agar HTML kesalahan Apache tidak merusak aplikasi.
    try {
      // Ubah teks menjadi objek.
      tubuh = JSON.parse(teks);
    } catch (e) {
      // Simpan cuplikan bila server tidak mengirim JSON.
      tubuh = { pesan: "Respons server tidak dikenali" };
    }
  }
  // Kembalikan status, isi, dan header sekaligus.
  return { status: respons.status, tubuh, header: respons.headers };
}

// Mengosongkan token dan kembali ke layar masuk.
function keluar() {
  // Hapus token dari memori tab.
  keadaan.token = "";
  // Hapus identitas.
  keadaan.pengguna = null;
  // Hapus simpanan tab.
  sessionStorage.removeItem("token");
  // Tampilkan login.
  elemen.layarMasuk.hidden = false;
  // Sembunyikan aplikasi.
  elemen.layarAplikasi.hidden = true;
}

// Menerapkan role ke tombol yang hanya boleh admin.
function aturHak() {
  // Admin melihat tombol, user tidak.
  const tampil = adalahAdmin();
  // Atribut hidden menyembunyikan tombol dari user.
  elemen.tombolTambah.hidden = !tampil;
  // Log juga hanya untuk admin.
  elemen.tombolLog.hidden = !tampil;
  // Sembunyikan panel log bila yang masuk bukan admin.
  if (!tampil) {
    // Tutup tabel bila sedang terbuka lalu role berganti.
    elemen.panelLog.hidden = true;
  }
}

// Menggambar kartu dari data yang lolos saringan.
function gambarKartu() {
  // Kata kunci pencarian, huruf kecil agar tidak peduli kapital.
  const kata = elemen.saringanTeks.value.trim().toLowerCase();
  // Kategori yang dipilih, kosong berarti semua.
  const kategori = elemen.saringanKategori.value;
  // Saring array di browser; server tetap sumber data lengkap.
  const tampil = keadaan.data.filter((item) => {
    // Cocokkan kategori bila ada pilihan.
    const lolosKategori = kategori === "" || item.kategori === kategori;
    // Gabungkan judul dan kode sebagai sasaran cari.
    const sasaran = (item.judul + " " + item.kode_info).toLowerCase();
    // Kata kosong dianggap cocok.
    const lolosKata = kata === "" || sasaran.includes(kata);
    // Dua syarat harus sekaligus benar.
    return lolosKategori && lolosKata;
  });
  // Kosongkan kisi sebelum menggambar ulang.
  elemen.daftarKartu.innerHTML = "";
  // Tidak ada hasil.
  if (tampil.length === 0) {
    // Bedakan data memang kosong dan saringan yang terlalu sempit.
    elemen.statusDaftar.textContent = keadaan.data.length === 0
      ? "Belum ada pengumuman."
      : "Tidak ada pengumuman yang cocok dengan saringan.";
    // Berhenti karena tidak ada kartu.
    return;
  }
  // Ada hasil, kosongkan kalimat status.
  elemen.statusDaftar.textContent = "";
  // Gambar setiap pengumuman.
  tampil.forEach((item) => {
    // Buat artikel kartu.
    const kartu = document.createElement("article");
    // Kelas tampilan kartu.
    kartu.className = "kartu";
    // Angka prioritas menentukan kelas warna.
    const kelasPrioritas = Number(item.prioritas) >= 4 ? "prioritas-tinggi" : "prioritas-sedang";
    // Susun isi kartu. Nilai teks melewati textContent lewat template yang sudah diamankan di bawah.
    kartu.innerHTML = ""
      + '<div class="meta"><span></span><span class="' + kelasPrioritas + '"></span></div>'
      + "<h2></h2><p class=\"ringkas\"></p><div class=\"kaki-kartu\"><span></span><span></span></div>"
      + '<div class="aksi-kartu"><button type="button" class="tombol-garis ubah"><i class="bi bi-pencil"></i> Ubah</button>'
      + '<button type="button" class="tombol-bahaya hapus"><i class="bi bi-trash"></i> Hapus</button></div>';
    // Isi kode dan kategori lewat textContent agar tidak menjadi HTML.
    kartu.querySelector(".meta span").textContent = item.kode_info + " · " + item.kategori;
    // Isi angka prioritas.
    kartu.querySelector("." + kelasPrioritas).textContent = "Prioritas " + item.prioritas;
    // Isi judul.
    kartu.querySelector("h2").textContent = item.judul;
    // Isi ringkasan.
    kartu.querySelector(".ringkas").textContent = item.ringkasan;
    // Isi penulis.
    kartu.querySelector(".kaki-kartu span").textContent = item.penulis;
    // Isi tanggal pada span kedua.
    kartu.querySelectorAll(".kaki-kartu span")[1].textContent = item.tanggal;
    // Tombol ubah.
    const tombolUbah = kartu.querySelector(".ubah");
    // Tombol hapus.
    const tombolHapus = kartu.querySelector(".hapus");
    // User tetap melihat tombol, tetapi dinonaktifkan sesuai ketentuan tugas.
    tombolUbah.disabled = !adalahAdmin();
    // Hapus juga mengikuti role.
    tombolHapus.disabled = !adalahAdmin();
    // Klik ubah membuka modal dengan data ini.
    tombolUbah.addEventListener("click", () => bukaUbah(item));
    // Klik hapus membuka konfirmasi.
    tombolHapus.addEventListener("click", () => bukaHapus(item));
    // Masukkan kartu ke kisi.
    elemen.daftarKartu.appendChild(kartu);
  });
}

// Mengambil daftar dari server lalu menggambarnya.
async function muatData() {
  // Beritahu bahwa data sedang diminta.
  elemen.statusDaftar.textContent = "Memuat pengumuman...";
  // Panggil endpoint daftar.
  const hasil = await api("/pengumuman");
  // Token ditolak: keluarkan pengguna.
  if (hasil.status === 401) {
    // Pesan lalu kembali ke login.
    toast(pesanStatus(401, hasil.tubuh, hasil.header), true);
    // Hapus sesi.
    keluar();
    // Berhenti.
    return;
  }
  // Status lain yang gagal.
  if (hasil.status !== 200) {
    // Tampilkan pesan status.
    elemen.statusDaftar.textContent = pesanStatus(hasil.status, hasil.tubuh, hasil.header);
    // Berhenti tanpa menggambar data lama sebagai data baru.
    return;
  }
  // Simpan array data.
  keadaan.data = hasil.tubuh.data || [];
  // Gambar kartu.
  gambarKartu();
}

// Mengambil dan menggambar log admin.
async function muatLog() {
  // Minta 100 log terbaru.
  const hasil = await api("/log");
  // Hak tidak cukup atau token gagal.
  if (hasil.status !== 200) {
    // Tampilkan toast, termasuk 403.
    toast(pesanStatus(hasil.status, hasil.tubuh, hasil.header), true);
    // Jangan buka panel.
    return;
  }
  // Kosongkan baris lama.
  elemen.isiLog.innerHTML = "";
  // Tidak ada catatan.
  if (!hasil.tubuh.data || hasil.tubuh.data.length === 0) {
    // Satu baris kosong.
    elemen.isiLog.innerHTML = '<tr><td colspan="7">Belum ada catatan.</td></tr>';
  }
  // Gambar setiap baris log.
  (hasil.tubuh.data || []).forEach((baris) => {
    // Buat baris tabel.
    const tr = document.createElement("tr");
    // Tujuh sel sesuai kolom.
    ["waktu", "method", "path", "status", "ip", "username", "pesan"].forEach((kunci) => {
      // Buat sel.
      const td = document.createElement("td");
      // Username boleh null; tampilkan tanda hubung.
      td.textContent = baris[kunci] === null || baris[kunci] === undefined ? "-" : String(baris[kunci]);
      // Masukkan sel.
      tr.appendChild(td);
    });
    // Masukkan baris.
    elemen.isiLog.appendChild(tr);
  });
  // Tampilkan panel.
  elemen.panelLog.hidden = false;
}

// Menutup modal formulir.
function tutupFormulir() {
  // Sembunyikan lapisan.
  elemen.modalFormulir.hidden = true;
  // Kosongkan pesan kesalahan.
  elemen.pesanFormulir.textContent = "";
}

// Menutup modal hapus.
function tutupHapus() {
  // Sembunyikan modal.
  elemen.modalHapus.hidden = true;
  // Lupakan id yang tadi dipilih.
  keadaan.idHapus = null;
}

// Membuka formulir kosong untuk data baru.
function bukaTambah() {
  // Mode menentukan POST saat disimpan.
  keadaan.mode = "tambah";
  // Judul modal.
  elemen.judulModal.textContent = "Tambah pengumuman";
  // Kosongkan field.
  elemen.formulirData.reset();
  // Hapus id suntingan bila sebelumnya membuka ubah.
  delete elemen.formulirData.dataset.id;
  // Bersihkan pesan.
  elemen.pesanFormulir.textContent = "";
  // Tampilkan modal.
  elemen.modalFormulir.hidden = false;
}

// Membuka formulir yang sudah terisi.
function bukaUbah(item) {
  // Mode menentukan PUT saat disimpan.
  keadaan.mode = "ubah";
  // Judul modal.
  elemen.judulModal.textContent = "Ubah pengumuman";
  // Simpan id di dataset formulir.
  elemen.formulirData.dataset.id = String(item.id);
  // Isi setiap field dari objek.
  ["kode_info", "judul", "kategori", "tanggal", "penulis", "prioritas", "ringkasan"].forEach((nama) => {
    // Cari input sesuai nama.
    elemen.formulirData.elements[nama].value = item[nama];
  });
  // Bersihkan pesan lama.
  elemen.pesanFormulir.textContent = "";
  // Tampilkan modal.
  elemen.modalFormulir.hidden = false;
}

// Membuka konfirmasi hapus.
function bukaHapus(item) {
  // Simpan id yang akan dihapus.
  keadaan.idHapus = item.id;
  // Sebutkan judulnya agar pengguna yakin.
  elemen.teksHapus.textContent = "Pengumuman \"" + item.judul + "\" akan dihapus.";
  // Tampilkan modal.
  elemen.modalHapus.hidden = false;
}

// Mengumpulkan nilai formulir menjadi objek JSON.
function ambilForm() {
  // FormData membaca semua field bernama.
  const data = new FormData(elemen.formulirData);
  // Kembalikan objek dengan prioritas sebagai angka.
  return {
    kode_info: String(data.get("kode_info") || "").trim(), // kode tanpa spasi
    judul: String(data.get("judul") || "").trim(), // judul tanpa spasi
    kategori: String(data.get("kategori") || ""), // kategori pilihan
    tanggal: String(data.get("tanggal") || ""), // tanggal dari input date
    penulis: String(data.get("penulis") || "").trim(), // penulis
    prioritas: Number(data.get("prioritas")), // kolom angka
    ringkasan: String(data.get("ringkasan") || "").trim(), // ringkasan
  };
}

// Mengirim login.
async function kirimMasuk(peristiwa) {
  // Cegah browser memuat ulang halaman.
  peristiwa.preventDefault();
  // Kosongkan pesan lama.
  elemen.pesanMasuk.textContent = "";
  // Matikan tombol selama menunggu.
  elemen.tombolMasuk.disabled = true;
  // Ubah label tombol menjadi status memuat.
  elemen.tombolMasuk.textContent = "Memeriksa...";
  // Bungkus agar tombol selalu hidup kembali.
  try {
    // Panggil login tanpa token.
    const hasil = await api("/login", {
      method: "POST", // metode login
      body: {
        username: elemen.formulirMasuk.username.value.trim(), // username
        password: elemen.formulirMasuk.password.value, // sandi apa adanya
      },
    });
    // Gagal: tampilkan pesan sesuai status, termasuk 429.
    if (hasil.status !== 200) {
      // Tulis di bawah formulir.
      elemen.pesanMasuk.textContent = pesanStatus(hasil.status, hasil.tubuh, hasil.header);
      // Berhenti sebelum menyimpan token.
      return;
    }
    // Simpan token di memori dan di tab.
    keadaan.token = hasil.tubuh.token;
    // Simpan agar refresh halaman belum langsung keluar.
    sessionStorage.setItem("token", keadaan.token);
    // Lanjut memuat profil dan data.
    await mulaiAplikasi();
  } catch (e) {
    // Jaringan putus atau server mati.
    elemen.pesanMasuk.textContent = "Tidak dapat menghubungi server";
  } finally {
    // Hidupkan lagi tombol.
    elemen.tombolMasuk.disabled = false;
    // Kembalikan label.
    elemen.tombolMasuk.textContent = "Masuk";
  }
}

// Menyimpan tambah atau ubah.
async function simpanData(peristiwa) {
  // Jangan muat ulang halaman.
  peristiwa.preventDefault();
  // Kosongkan pesan.
  elemen.pesanFormulir.textContent = "";
  // Tentukan jalur dan metode dari mode.
  const ubah = keadaan.mode === "ubah";
  // Id hanya dipakai saat mengubah.
  const id = elemen.formulirData.dataset.id;
  // Panggil endpoint yang sesuai.
  const hasil = await api(ubah ? "/pengumuman/" + id : "/pengumuman", {
    method: ubah ? "PUT" : "POST", // ubah atau tambah
    body: ambilForm(), // isi field
  });
  // Berhasil membuat atau mengubah.
  if (hasil.status === 201 || hasil.status === 200) {
    // Tutup modal.
    tutupFormulir();
    // Beri tahu keberhasilan.
    toast(hasil.tubuh.pesan || "Tersimpan");
    // Muat ulang daftar dari server.
    await muatData();
    // Selesai.
    return;
  }
  // 401 mengeluarkan pengguna.
  if (hasil.status === 401) {
    // Beri tahu lalu keluar.
    toast(pesanStatus(401, hasil.tubuh, hasil.header), true);
    // Hapus sesi.
    keluar();
    // Berhenti.
    return;
  }
  // 403, 409, 422, dan status lain tampil di dalam modal.
  elemen.pesanFormulir.textContent = pesanStatus(hasil.status, hasil.tubuh, hasil.header);
}

// Menghapus setelah konfirmasi.
async function hapusData() {
  // Tanpa id, tidak ada yang dikirim.
  if (!keadaan.idHapus) {
    // Tutup saja.
    tutupHapus();
    // Berhenti.
    return;
  }
  // Kirim DELETE.
  const hasil = await api("/pengumuman/" + keadaan.idHapus, { method: "DELETE" });
  // Tutup modal lebih dulu agar tidak diklik dua kali.
  tutupHapus();
  // Berhasil.
  if (hasil.status === 200) {
    // Beri tahu.
    toast(hasil.tubuh.pesan || "Dihapus");
    // Segarkan daftar.
    await muatData();
    // Selesai.
    return;
  }
  // Token gagal.
  if (hasil.status === 401) {
    // Keluar.
    toast(pesanStatus(401, hasil.tubuh, hasil.header), true);
    // Hapus sesi.
    keluar();
    // Berhenti.
    return;
  }
  // Termasuk 403 dan 404.
  toast(pesanStatus(hasil.status, hasil.tubuh, hasil.header), true);
}

// Setelah token ada, ambil profil lalu data.
async function mulaiAplikasi() {
  // Minta identitas dari token.
  const saya = await api("/me");
  // Token tidak diterima.
  if (saya.status !== 200) {
    // Tampilkan alasan.
    elemen.pesanMasuk.textContent = pesanStatus(saya.status, saya.tubuh, saya.header);
    // Buang token yang rusak.
    keluar();
    // Berhenti.
    return;
  }
  // Simpan profil.
  keadaan.pengguna = saya.tubuh;
  // Tampilkan username.
  elemen.namaPengguna.textContent = saya.tubuh.username;
  // Tampilkan role.
  elemen.peranPengguna.textContent = saya.tubuh.role;
  // Sembunyikan atau tampilkan aksi admin.
  aturHak();
  // Tutup layar login.
  elemen.layarMasuk.hidden = true;
  // Buka aplikasi.
  elemen.layarAplikasi.hidden = false;
  // Muat kartu.
  await muatData();
}

// Sambungkan peristiwa setelah elemen ada.
elemen.formulirMasuk.addEventListener("submit", kirimMasuk);
// Keluar membuang token.
elemen.tombolKeluar.addEventListener("click", keluar);
// Tambah membuka modal kosong.
elemen.tombolTambah.addEventListener("click", bukaTambah);
// Log memuat tabel.
elemen.tombolLog.addEventListener("click", () => {
  // Panel yang sedang terbuka ditutup bila diklik lagi.
  if (!elemen.panelLog.hidden) {
    // Sembunyikan.
    elemen.panelLog.hidden = true;
    // Berhenti agar tidak langsung terbuka lagi.
    return;
  }
  // Ambil data log.
  muatLog();
});
// Saringan menggambar ulang tanpa meminta server.
elemen.saringanTeks.addEventListener("input", gambarKartu);
// Ganti kategori juga menggambar ulang.
elemen.saringanKategori.addEventListener("change", gambarKartu);
// Simpan form data.
elemen.formulirData.addEventListener("submit", simpanData);
// Batal form.
elemen.batalFormulir.addEventListener("click", tutupFormulir);
// Batal hapus.
elemen.batalHapus.addEventListener("click", tutupHapus);
// Setuju hapus.
elemen.yaHapus.addEventListener("click", hapusData);
// Klik lapisan gelap menutup modal yang sesuai.
document.querySelectorAll(".lapisan").forEach((lapisan) => {
  // Dengarkan klik.
  lapisan.addEventListener("click", () => {
    // data-tutup memberitahu modal mana.
    if (lapisan.dataset.tutup === "formulir") {
      // Tutup form.
      tutupFormulir();
    }
    // Lapisan hapus.
    if (lapisan.dataset.tutup === "hapus") {
      // Tutup konfirmasi.
      tutupHapus();
    }
  });
});

// Bila tab masih menyimpan token, coba lanjutkan sesi.
if (keadaan.token) {
  // Profil yang gagal akan mengembalikan layar masuk.
  mulaiAplikasi();
}
