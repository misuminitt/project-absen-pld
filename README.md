# Project Absen PLD
<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.1+">
  <img src="https://img.shields.io/badge/CodeIgniter-3-EF4223?logo=codeigniter&logoColor=white" alt="CodeIgniter 3">
  <img src="https://img.shields.io/badge/Database-MariaDB%20%2F%20MySQL-003545?logo=mariadb&logoColor=white" alt="MariaDB / MySQL">
  <img src="https://img.shields.io/badge/Sync-Google%20Sheets-34A853" alt="Google Sheets Sync">
</p>
Sistem absensi internal untuk manajemen akun karyawan, absensi harian/bulanan, pengajuan (cuti/izin, pinjaman, tukar hari libur, lembur), serta sinkronisasi data web dan Google Sheets.

## Requirements
- PHP 8.1+
- Apache 2.4+ atau Nginx
- MariaDB/MySQL
- Ekstensi PHP: `curl`, `json`, `mbstring`, `openssl`, `gd`
- Akses tulis folder: `application/cache`, `application/logs`, `uploads/`

## Installation
1. Clone repository.
2. Copy `.env.example` menjadi `.env`.
3. Isi konfigurasi database dan env yang dibutuhkan.
4. Pastikan dependency/extension server sudah aktif.
5. Jalankan project di web server (Apache/Nginx + PHP).

### Quick Setup
```bash
git clone https://github.com/<username>/<repo>.git
cd project-absen-pld
cp .env.example .env
```

### Konfigurasi Dasar `.env`
```env
CI_ENV=development
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=
DB_PASS=
DB_NAME=
```

### Struktur Singkat
- `application/` : controller, view, config utama CodeIgniter.
- `src/assets/` : CSS, JS, gambar/logo frontend.
- `uploads/` : foto profil dan foto absensi.
- `src/secrets/` : file credential lokal (jangan dipush).

### Troubleshooting
Tanyakan dulu apakah perlu ditambah. (rubah bagian sini)
- Jika gambar upload `403`, cek permission folder dan ownership web server.
- Jika sync sheet gagal, cek path credential dan role service account.
- Jika request timeout (`524`), cek beban server dan timeout upstream.

**Author:** misuminitt
