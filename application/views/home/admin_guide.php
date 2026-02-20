<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$username = isset($username) ? (string) $username : 'Admin';
$can_view_log_data = isset($can_view_log_data) && $can_view_log_data === TRUE;
$dashboard_navbar_title = isset($dashboard_navbar_title) && trim((string) $dashboard_navbar_title) !== ''
	? trim((string) $dashboard_navbar_title)
	: 'Dashboard Admin Absen';
$script_name = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
$base_path = str_replace('\\', '/', dirname($script_name));
if ($base_path === '/' || $base_path === '.')
{
	$base_path = '';
}

$logo_path = 'src/assets/pns_logo_nav.png';
if (is_file(FCPATH.'src/assets/pns_logo_nav.png'))
{
	$logo_path = 'src/assets/pns_logo_nav.png';
}
elseif (is_file(FCPATH.'src/assts/pns_logo_nav.png'))
{
	$logo_path = 'src/assts/pns_logo_nav.png';
}
elseif (is_file(FCPATH.'src/assets/pns_new.png'))
{
	$logo_path = 'src/assets/pns_new.png';
}
elseif (is_file(FCPATH.'src/assts/pns_new.png'))
{
	$logo_path = 'src/assts/pns_new.png';
}
elseif (is_file(FCPATH.'src/assets/pns_dashboard.png'))
{
	$logo_path = 'src/assets/pns_dashboard.png';
}
elseif (is_file(FCPATH.'src/assts/pns_dashboard.png'))
{
	$logo_path = 'src/assts/pns_dashboard.png';
}

$logo_url = $base_path.'/'.$logo_path;
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Cara Pakai Dashboard Admin'; ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<style>
		:root {
			--brand-dark: #083c68;
			--brand-main: #0f5c93;
			--brand-soft: #e8f4ff;
			--text-main: #0d2238;
			--text-soft: #4d637a;
			--line-soft: #dbe7f3;
			--surface: #ffffff;
			--warn-bg: #fff6e9;
			--warn-text: #935d0e;
		}

		* {
			box-sizing: border-box;
			scrollbar-width: none;
			-ms-overflow-style: none;
		}

		*::-webkit-scrollbar {
			width: 0;
			height: 0;
		}

		html,
		body {
			margin: 0;
			padding: 0;
			min-height: 100%;
		}

		body {
			font-family: 'Plus Jakarta Sans', sans-serif;
			color: var(--text-main);
			background:
				radial-gradient(circle at 10% 8%, rgba(73, 172, 255, 0.14) 0%, transparent 34%),
				radial-gradient(circle at 90% 5%, rgba(255, 216, 165, 0.18) 0%, transparent 28%),
				linear-gradient(180deg, #f0f8ff 0%, #ffffff 42%);
		}

		.topbar {
			background: linear-gradient(120deg, var(--brand-dark) 0%, var(--brand-main) 100%);
			color: #ffffff;
			box-shadow: 0 10px 24px rgba(8, 60, 104, 0.22);
			position: sticky;
			top: 0;
			z-index: 60;
		}

		.topbar-container {
			width: 100%;
			padding: 1rem;
		}

		.topbar-inner {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.9rem;
		}

		.brand-block {
			display: inline-flex;
			align-items: center;
			gap: 0.56rem;
			text-decoration: none;
		}

		.brand-logo {
			height: 48px;
			width: auto;
			object-fit: contain;
		}

		.brand-text {
			font-size: 1.04rem;
			font-weight: 700;
			letter-spacing: 0.02em;
			color: #ffffff;
		}

		.top-actions {
			display: inline-flex;
			align-items: center;
			gap: 0.45rem;
		}

		.back-btn,
		.logout {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			text-decoration: none;
			padding: 0.5rem 0.9rem;
			border-radius: 999px;
			font-size: 0.8rem;
			font-weight: 700;
			transition: background-color 0.2s ease;
			border: 1px solid rgba(255, 255, 255, 0.24);
			color: #ffffff;
			background: rgba(255, 255, 255, 0.1);
		}

		.back-btn:hover,
		.logout:hover {
			background: rgba(255, 255, 255, 0.2);
		}

		.page {
			max-width: 1200px;
			margin: 0 auto;
			padding: 1.2rem 1rem 1.8rem;
		}

		.hero {
			background: linear-gradient(145deg, #ffffff 0%, #f4faff 100%);
			border: 1px solid var(--line-soft);
			border-radius: 20px;
			padding: 1.2rem;
			box-shadow: 0 14px 36px rgba(7, 49, 84, 0.08);
			margin-bottom: 1rem;
		}

		.hero h1 {
			margin: 0;
			font-size: 1.35rem;
			font-weight: 800;
			letter-spacing: -0.01em;
		}

		.hero p {
			margin: 0.48rem 0 0;
			font-size: 0.92rem;
			color: var(--text-soft);
			font-weight: 500;
			line-height: 1.55;
		}

		.section {
			background: var(--surface);
			border: 1px solid var(--line-soft);
			border-radius: 16px;
			padding: 1rem;
			box-shadow: 0 8px 20px rgba(7, 49, 84, 0.06);
			margin-bottom: 0.92rem;
		}

		.section h2 {
			margin: 0 0 0.72rem;
			font-size: 1rem;
			font-weight: 800;
		}

		.section p,
		.section li {
			font-size: 0.86rem;
			color: #294661;
			line-height: 1.58;
		}

		.section ul,
		.section ol {
			margin: 0.55rem 0 0;
			padding-left: 1.2rem;
		}

		.grid {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 0.78rem;
		}

		.info-card {
			background: #f9fcff;
			border: 1px solid #d7e7f6;
			border-radius: 12px;
			padding: 0.8rem;
		}

		.info-card h3 {
			margin: 0;
			font-size: 0.88rem;
			font-weight: 800;
			color: #133f69;
		}

		.info-card p {
			margin: 0.42rem 0 0;
			font-size: 0.82rem;
			line-height: 1.55;
			color: #2f4e6d;
		}

		.rule-table-wrap {
			overflow-x: auto;
			border: 1px solid #d7e7f6;
			border-radius: 12px;
		}

		table {
			width: 100%;
			border-collapse: collapse;
			min-width: 640px;
		}

		th,
		td {
			padding: 0.56rem 0.62rem;
			border-bottom: 1px solid #e9f2fb;
			text-align: left;
			vertical-align: top;
		}

		th {
			background: #f6fbff;
			font-size: 0.7rem;
			font-weight: 800;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			color: #5f7894;
		}

		td {
			font-size: 0.82rem;
			color: #233f5a;
		}

		.warn-box {
			margin-top: 0.78rem;
			padding: 0.72rem 0.82rem;
			border-radius: 10px;
			border: 1px solid #efd3a4;
			background: var(--warn-bg);
			color: var(--warn-text);
			font-size: 0.82rem;
			font-weight: 700;
		}

		.code-box {
			margin-top: 0.72rem;
			padding: 0.72rem 0.82rem;
			border-radius: 10px;
			background: #0f2f58;
			color: #d9ecff;
			border: 1px solid #27527f;
		}

		.code-box code {
			display: block;
			white-space: pre-wrap;
			font-family: Consolas, 'Courier New', monospace;
			font-size: 0.76rem;
			line-height: 1.58;
		}

		.faq-list {
			display: grid;
			gap: 0.62rem;
		}

		.faq-item {
			border: 1px solid #d7e7f6;
			border-radius: 12px;
			background: #f9fcff;
			padding: 0.72rem 0.82rem;
		}

		.faq-item summary {
			cursor: pointer;
			font-size: 0.84rem;
			font-weight: 800;
			color: #133f69;
			outline: none;
		}

		.faq-item summary::-webkit-details-marker {
			color: #1a5f97;
		}

		.faq-item p {
			margin: 0.52rem 0 0;
			font-size: 0.83rem;
			color: #2f4e6d;
			line-height: 1.56;
		}

		.wa-help-box {
			margin-top: 0.95rem;
			padding: 0.9rem;
			border: 1px solid var(--line-soft);
			border-radius: 12px;
			background: #f8fcff;
		}

		.wa-help-box p {
			margin: 0 0 0.7rem;
			font-size: 0.82rem;
			color: #2b4a69;
			line-height: 1.5;
		}

		.wa-help-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 0.56rem 1rem;
			border-radius: 999px;
			text-decoration: none;
			font-size: 0.82rem;
			font-weight: 700;
			color: #ffffff;
			background: #18a84b;
			transition: background-color 0.2s ease, transform 0.2s ease;
		}

		.wa-help-btn:hover {
			background: #10903f;
			transform: translateY(-1px);
		}

		.footer-note {
			margin-top: 0.85rem;
			font-size: 0.76rem;
			color: #607a94;
			font-weight: 600;
		}

		@media (max-width: 860px) {
			.grid {
				grid-template-columns: 1fr;
			}

			.brand-text {
				display: none;
			}

			.top-actions {
				flex-wrap: wrap;
				justify-content: flex-end;
			}
		}
	
		/* mobile-fix-20260219 */
		@media (max-width: 860px) {
			.topbar-container {
				padding: 0.78rem;
			}

			.topbar-inner {
				flex-direction: column;
				align-items: flex-start;
				gap: 0.56rem;
			}

			.brand-text {
				display: none;
			}

			.top-actions {
				width: 100%;
				display: grid;
				grid-template-columns: repeat(2, minmax(0, 1fr));
				gap: 0.4rem;
			}

			.back-btn,
			.logout {
				width: 100%;
				padding: 0.48rem 0.68rem;
				font-size: 0.76rem;
			}

			.page {
				padding: 0.9rem 0.72rem 1.2rem;
			}

			.hero {
				padding: 0.9rem;
				border-radius: 14px;
			}

			.hero h1 {
				font-size: 1.08rem;
			}

			.hero p {
				font-size: 0.8rem;
			}

			.section {
				padding: 0.82rem;
				border-radius: 13px;
			}

			.section h2 {
				font-size: 0.92rem;
			}

			.section p,
			.section li {
				font-size: 0.79rem;
			}

			.grid {
				grid-template-columns: 1fr;
			}
		}

		@media (max-width: 520px) {
			.page {
				padding: 0.75rem 0.55rem 1rem;
			}

			.top-actions {
				grid-template-columns: 1fr;
			}

			table {
				min-width: 520px;
			}
		}

		/* mobile-fix-20260219-navbar-compact */
		@media (max-width: 860px) {
			.topbar-container {
				padding: 0.72rem;
			}

			.topbar-inner {
				display: flex;
				flex-direction: column;
				align-items: stretch;
				gap: 0.48rem;
			}

			.brand-block {
				min-width: 0;
			}

			.brand-logo {
				height: 36px;
			}

			.brand-text {
				display: block;
				font-size: 0.88rem;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}

			.top-actions {
				display: flex;
				flex-wrap: wrap;
				justify-content: flex-start;
				gap: 0.36rem;
			}

			.back-btn,
			.logout {
				width: auto;
				min-width: 0;
				padding: 0.42rem 0.7rem;
				font-size: 0.73rem;
			}
		}

		@media (max-width: 520px) {
			.top-actions .back-btn,
			.top-actions .logout {
				flex: 1 1 calc(50% - 0.36rem);
				justify-content: center;
			}

			.top-actions .logout {
				flex-basis: 100%;
			}
		}
</style>
</head>
<body>
	<nav class="topbar">
		<div class="topbar-container">
			<div class="topbar-inner">
				<a href="<?php echo site_url('home'); ?>" class="brand-block">
					<img class="brand-logo" src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Absen Online">
					<span class="brand-text"><?php echo htmlspecialchars($dashboard_navbar_title, ENT_QUOTES, 'UTF-8'); ?></span>
				</a>
				<div class="top-actions">
					<a href="<?php echo site_url('home'); ?>" class="back-btn">Kembali ke Dashboard</a>
					<?php if ($can_view_log_data): ?>
						<a href="<?php echo site_url('home/log_data'); ?>" class="back-btn">Log Data</a>
					<?php endif; ?>
					<a href="<?php echo site_url('logout'); ?>" class="logout">Logout</a>
				</div>
			</div>
		</div>
	</nav>

	<main class="page">
		<section class="hero">
			<h1>Cara Pakai <?php echo htmlspecialchars($dashboard_navbar_title, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></h1>
			<p>Halaman ini menjelaskan alur penggunaan web, fungsi tombol utama, aturan potongan telat/alpha/izin, dan catatan sinkronisasi sheet supaya data web dan spreadsheet tetap konsisten.</p>
		</section>

		<section class="section">
			<h2>Alur Pakai Singkat</h2>
			<ol>
				<li>Jika login sebagai Developer/Bos, kelola akun karyawan dulu (buat/edit/hapus) di bagian Manajemen Akun Karyawan.</li>
				<li>Pastikan data harian masuk ke web lewat tombol <strong>Sync Data Absen dari Sheet</strong> jika sumbernya dari Google Sheet.</li>
				<li>Jika ada perubahan data dari web, klik <strong>Sync Data Web ke Sheet</strong> agar Data Absen di sheet ikut update.</li>
				<li>Pakai menu Data Lembur, Pengajuan Pinjaman, Pengajuan Cuti/Izin, dan Cek Absensi untuk operasional harian.</li>
			</ol>
		</section>

		<section class="section">
			<h2>Fungsi Tombol Utama</h2>
			<div class="grid">
				<article class="info-card">
					<h3>Sync Akun dari Sheet</h3>
					<p>Menarik data akun karyawan dari sheet ke web. Dipakai saat perubahan data akun dilakukan dari spreadsheet. Fitur ini khusus akun Developer/Bos.</p>
				</article>
				<article class="info-card">
					<h3>Sync Data Absen dari Sheet</h3>
					<p>Menarik data absensi dari tab Data Absen di sheet ke web. Data attendance web akan diisi/update dari sheet.</p>
				</article>
				<article class="info-card">
					<h3>Sync Data Web ke Sheet</h3>
					<p>Mengirim perubahan data absensi dari web ke tab Data Absen di sheet. Sinkronisasi ini manual, bukan realtime otomatis.</p>
				</article>
				<article class="info-card">
					<h3>Buat / Edit / Hapus Akun Karyawan</h3>
					<p>Kelola akun login karyawan. Buat akun baru, ubah data akun, atau hapus akun beserta data terkait. Fitur ini khusus akun Developer/Bos.</p>
				</article>
				<article class="info-card">
					<h3>Data Lembur</h3>
					<p>Input dan kelola data lembur: nama, tanggal, durasi/lembur, nominal, dan alasan lembur karyawan.</p>
				</article>
				<article class="info-card">
					<h3>Pengajuan Cuti / Izin dan Pinjaman</h3>
					<p>Review dan proses pengajuan yang dikirim karyawan. Status pengajuan berpengaruh ke ringkasan bulanan.</p>
				</article>
			</div>
		</section>

		<section class="section">
			<h2>Aturan Potongan Telat, Alpha, dan Izin/Cuti</h2>
			<div class="rule-table-wrap">
				<table>
					<thead>
						<tr>
							<th>Kategori</th>
							<th>Batas Waktu</th>
							<th>Rumus Potongan</th>
							<th>Keterangan</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>Telat 1-30 menit</td>
							<td>&gt; 10 menit sampai 30 menit</td>
							<td>21% x gaji harian</td>
							<td>Toleransi telat sistem: 10 menit dari jam mulai shift.</td>
						</tr>
						<tr>
							<td>Telat 31-60 menit</td>
							<td>&gt; 30 menit sampai 60 menit</td>
							<td>32% x gaji harian</td>
							<td>Dihitung per kejadian keterlambatan.</td>
						</tr>
						<tr>
							<td>Telat 1-3 jam</td>
							<td>&gt; 60 menit sampai 4 jam</td>
							<td>53% x gaji harian</td>
							<td>Masuk kategori telat berat.</td>
						</tr>
						<tr>
							<td>Telat &gt; 4 jam</td>
							<td>Lebih dari 4 jam</td>
							<td>77% x gaji harian</td>
							<td>Potongan paling besar untuk telat.</td>
						</tr>
						<tr>
							<td>Alpha</td>
							<td>Tidak hadir tanpa status izin/cuti</td>
							<td>100% x gaji harian</td>
							<td>Jika izin/cuti valid disetujui, hari tersebut tidak dihitung alpha.</td>
						</tr>
					</tbody>
				</table>
			</div>
			<div class="warn-box">
				Cuti tahunan memiliki kuota 12 hari. Jika izin/cuti melebihi kuota kebijakan, kelebihan hari dapat dihitung sebagai alpha pada perhitungan potongan.
			</div>
		</section>

		<section class="section">
			<h2>Pertanyaan yang Sering Diajukan (FAQ)</h2>
			<div class="faq-list">
				<details class="faq-item" open>
					<summary>Kapan saya perlu menggunakan tombol Sync Akun dari Sheet?</summary>
					<p>Gunakan tombol ini ketika sumber perubahan akun berasal dari tab <strong>DATABASE</strong> spreadsheet, misalnya perubahan nomor telepon, jabatan, cabang, status, atau gaji pokok yang ingin diterapkan ke data web.</p>
				</details>
				<details class="faq-item">
					<summary>Bagaimana urutan yang benar untuk sinkronisasi dari web ke spreadsheet?</summary>
					<p>Urutan yang benar adalah: <strong>lakukan perubahan data di web terlebih dahulu</strong>, pastikan perubahan sudah tersimpan, kemudian klik <strong>Sync Data Web ke Sheet</strong>. Dengan urutan ini, tab <strong>Data Absen</strong> pada spreadsheet akan mengikuti data terbaru dari web.</p>
				</details>
				<details class="faq-item">
					<summary>Bagaimana urutan jika data di spreadsheet diubah dan hasilnya ingin masuk ke web?</summary>
					<p>Jika yang diubah adalah data akun pada tab <strong>DATABASE</strong>, jalankan <strong>Sync Akun dari Sheet</strong>. Jika yang diubah adalah data absensi pada tab <strong>Data Absen</strong>, jalankan <strong>Sync Data Absen dari Sheet</strong>. Pilih tombol sesuai sumber perubahan agar arah sinkronisasi tepat.</p>
				</details>
				<details class="faq-item">
					<summary>Apa yang terjadi jika dua orang mengedit Data Absen di spreadsheet secara bersamaan?</summary>
					<p>Google Sheets menyimpan perubahan secara kolaboratif. Jika dua pengguna mengubah sel yang berbeda, perubahan umumnya tetap terbaca seluruhnya. Namun jika mengubah sel yang sama, nilai yang tersimpan terakhir akan menjadi nilai final dan itulah yang dipakai saat sinkronisasi ke web dijalankan.</p>
				</details>
				<details class="faq-item">
					<summary>Apa yang terjadi jika dua orang mengedit data web secara bersamaan?</summary>
					<p>Untuk data yang sama, sistem dapat mengalami kondisi perubahan terakhir menimpa perubahan sebelumnya. Karena itu, disarankan tidak mengedit akun yang sama secara bersamaan dan melakukan sinkronisasi setelah perubahan final ditetapkan.</p>
				</details>
				<details class="faq-item">
					<summary>Bagaimana jika saya masih punya pertanyaan tentang website atau fiturnya?</summary>
					<p>Jika Anda memiliki pertanyaan lebih lanjut mengenai website atau fitur pada website ini, silakan hubungi WhatsApp: <a href="https://wa.me/628111806075" target="_blank" rel="noopener noreferrer"><strong>08111806075</strong></a>.</p>
				</details>
			</div>
			<div class="wa-help-box">
				<p>Jika masih ada pertanyaan, klik tombol berikut untuk langsung chat WhatsApp.</p>
				<a class="wa-help-btn" href="https://wa.me/628111806075" target="_blank" rel="noopener noreferrer">Hubungi WhatsApp</a>
			</div>
		</section>

		<section class="section">
			<h2>Update Fitur Terbaru (Kolaborasi Admin)</h2>
			<p>Berikut fitur baru yang ditambahkan untuk mencegah data saling timpa saat dashboard dikelola bersama oleh beberapa admin.</p>
			<ul>
				<li><strong>Notifikasi perubahan admin (popup):</strong> muncul saat login dan saat sedang di dashboard ketika ada perubahan data dari admin lain.</li>
				<li><strong>Sync lock global:</strong> saat satu admin sedang proses sync, admin lain sementara ditahan sampai lock selesai.</li>
				<li><strong>Optimistic lock (versi data):</strong> simpan/edit akan ditolak jika data yang sama sudah diubah admin lain, lalu muncul info konflik versi.</li>
				<li><strong>Draft saat refresh paksa:</strong> ketika sedang edit lalu ada update baru, sistem bisa simpan draft sementara di browser lalu rekonsiliasi ulang.</li>
				<li><strong>Rekonsiliasi per field:</strong> jika nilai server dan draft berbeda di field yang sama, admin bisa pilih pakai nilai server atau draft.</li>
				<li><strong>Strict logout saat pending sync:</strong> logout ditolak jika masih ada perubahan admin yang belum di-sync ke sheet.</li>
				<li><strong>Kontrol sync lebih aman:</strong> arah sinkronisasi tetap manual per tombol, dengan validasi konflik dan status lock.</li>
			</ul>
			<div class="warn-box">
				Tips operasional: selesai edit data web, segera jalankan <strong>Sync Data Web ke Sheet</strong> sebelum lanjut pekerjaan lain atau logout.
			</div>
		</section>

		<section class="section">
			<h2>Fitur Lainnya</h2>
			<ul>
				<li>Dashboard menampilkan ringkasan realtime (total hadir, telat, izin/cuti, alpha) dan grafik per periode.</li>
				<li>Riwayat absen terbaru menampilkan jam masuk/pulang, status, serta catatan.</li>
				<li>Pencarian akun karyawan mendukung pencarian berdasarkan ID dan username.</li>
				<li>Data absensi mempertimbangkan foto masuk/pulang, durasi kerja, serta alasan telat/izin/alpha.</li>
			</ul>
		</section>

		<p class="footer-note">Tips: setelah perubahan data di web, klik <strong>Sync Data Web ke Sheet</strong> supaya tab <strong>Data Absen</strong> di spreadsheet ikut update.</p>
	</main>
</body>
</html>
