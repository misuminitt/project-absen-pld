<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$summary = isset($summary) && is_array($summary) ? $summary : array();
$recent_logs = isset($recent_logs) && is_array($recent_logs) ? $recent_logs : array();
$employee_accounts = isset($employee_accounts) && is_array($employee_accounts) ? $employee_accounts : array();
$username = isset($username) ? (string) $username : 'Pengguna';
$account_notice_success = isset($account_notice_success) ? trim((string) $account_notice_success) : '';
$account_notice_error = isset($account_notice_error) ? trim((string) $account_notice_error) : '';
$job_title_options = isset($job_title_options) && is_array($job_title_options) ? array_values($job_title_options) : array(
	'NOC',
	'Admin',
	'Koordinator',
	'Teknisi',
	'Marketing',
	'Debt Collector',
	'Magang'
);
$default_job_title = in_array('Teknisi', $job_title_options, TRUE)
	? 'Teknisi'
	: (isset($job_title_options[0]) ? (string) $job_title_options[0] : 'Teknisi');
$branch_options = isset($branch_options) && is_array($branch_options) ? array_values($branch_options) : array(
	'Baros',
	'Cadasari'
);
$default_branch = isset($default_branch) && trim((string) $default_branch) !== ''
	? trim((string) $default_branch)
	: (isset($branch_options[0]) ? (string) $branch_options[0] : 'Baros');
$weekly_day_off_options = isset($weekly_day_off_options) && is_array($weekly_day_off_options)
	? $weekly_day_off_options
	: array(
		1 => 'Senin',
		2 => 'Selasa',
		3 => 'Rabu',
		4 => 'Kamis',
		5 => 'Jumat',
		6 => 'Sabtu',
		7 => 'Minggu'
	);
$default_weekly_day_off = isset($default_weekly_day_off) ? (int) $default_weekly_day_off : 1;
if (!isset($weekly_day_off_options[$default_weekly_day_off])) {
	$default_weekly_day_off = 1;
}
$can_view_log_data = isset($can_view_log_data) && $can_view_log_data === TRUE;
$can_manage_accounts = isset($can_manage_accounts) && $can_manage_accounts === TRUE;
$can_sync_sheet_accounts = isset($can_sync_sheet_accounts) && $can_sync_sheet_accounts === TRUE;
$can_manage_feature_accounts = isset($can_manage_feature_accounts) && $can_manage_feature_accounts === TRUE;
$dashboard_navbar_title = isset($dashboard_navbar_title) && trim((string) $dashboard_navbar_title) !== ''
	? trim((string) $dashboard_navbar_title)
	: 'Dashboard Admin Absen';
$dashboard_role_label = isset($dashboard_role_label) && trim((string) $dashboard_role_label) !== ''
	? trim((string) $dashboard_role_label)
	: 'Admin';
$dashboard_status_label = isset($dashboard_status_label) && trim((string) $dashboard_status_label) !== ''
	? trim((string) $dashboard_status_label)
	: 'Ringkasan Operasional Harian';
$privileged_password_targets = isset($privileged_password_targets) && is_array($privileged_password_targets)
	? array_values($privileged_password_targets)
	: array(
		array('username' => 'developer', 'label' => 'Developer (developer)'),
		array('username' => 'admin', 'label' => 'Admin (admin)'),
		array('username' => 'bos', 'label' => 'Bos (bos)')
	);
$admin_feature_catalog = isset($admin_feature_catalog) && is_array($admin_feature_catalog)
	? $admin_feature_catalog
	: array();
$admin_feature_accounts = isset($admin_feature_accounts) && is_array($admin_feature_accounts)
	? array_values($admin_feature_accounts)
	: array();

$status_hari_ini = $dashboard_status_label;
$status_class = 'status-info';

$script_name = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
$base_path = str_replace('\\', '/', dirname($script_name));
if ($base_path === '/' || $base_path === '.') {
	$base_path = '';
}

$logo_path = 'src/assets/pns_logo_nav.png';
if (is_file(FCPATH.'src/assets/pns_logo_nav.png')) {
	$logo_path = 'src/assets/pns_logo_nav.png';
}
elseif (is_file(FCPATH.'src/assts/pns_logo_nav.png')) {
	$logo_path = 'src/assts/pns_logo_nav.png';
}
elseif (is_file(FCPATH.'src/assets/pns_new.png')) {
	$logo_path = 'src/assets/pns_new.png';
}
elseif (is_file(FCPATH.'src/assts/pns_new.png')) {
	$logo_path = 'src/assts/pns_new.png';
}
elseif (is_file(FCPATH.'src/assets/pns_dashboard.png')) {
	$logo_path = 'src/assets/pns_dashboard.png';
}
elseif (is_file(FCPATH.'src/assts/pns_dashboard.png')) {
	$logo_path = 'src/assts/pns_dashboard.png';
}

$logo_url = $base_path.'/'.$logo_path;
$home_index_css_file = 'src/assets/css/home-index.css';
$home_index_js_file = 'src/assets/js/home-index.js';
$home_index_css_version = is_file(FCPATH.$home_index_css_file) ? (string) filemtime(FCPATH.$home_index_css_file) : '1';
$home_index_js_version = is_file(FCPATH.$home_index_js_file) ? (string) filemtime(FCPATH.$home_index_js_file) : '1';
$home_index_config_json = json_encode(array(
	'accountRows' => $employee_accounts,
	'defaultJobTitle' => $default_job_title,
	'defaultBranch' => $default_branch,
	'defaultWeeklyDayOff' => (int) $default_weekly_day_off,
	'featureAccounts' => $admin_feature_accounts,
	'summaryUrl' => site_url('home/admin_dashboard_live_summary'),
	'statusLabelFixed' => $dashboard_status_label,
	'chartEndpoint' => site_url('home/admin_metric_chart_data')
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($home_index_config_json === FALSE) {
	$home_index_config_json = '{}';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Dashboard Absen Online'; ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
	<link rel="stylesheet" href="<?php echo htmlspecialchars($base_path.'/'.$home_index_css_file, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo rawurlencode($home_index_css_version); ?>">
</head>
<body>
	<div class="main-shell">
		<nav class="topbar">
			<div class="topbar-container">
				<div class="topbar-inner">
					<a href="<?php echo site_url('home'); ?>" class="brand-block">
						<img class="brand-logo" src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Absen Online">
						<span class="brand-text"><?php echo htmlspecialchars($dashboard_navbar_title, ENT_QUOTES, 'UTF-8'); ?></span>
					</a>
					<div class="nav-right">
						<?php if ($can_view_log_data): ?>
							<a href="<?php echo site_url('home/log_data'); ?>" class="logout">Log Data</a>
						<?php endif; ?>
						<a href="<?php echo site_url('logout'); ?>" class="logout">Logout</a>
					</div>
				</div>
			</div>
		</nav>

		<header class="hero">
			<div class="container-xl">
				<div class="hero-card">
					<div class="row g-3 align-items-center">
						<div class="col-lg-7">
							<p class="status-pill <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>" id="summaryStatusPill"><?php echo htmlspecialchars($status_hari_ini, ENT_QUOTES, 'UTF-8'); ?></p>
							<h1 class="hero-title">Halo, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>. Selamat datang di Dashboard Absen.</h1>
							<p class="hero-subtitle">
								Kelola data kehadiran tim, sinkronisasi spreadsheet, dan operasional administrasi absensi dalam satu halaman kerja.
							</p>
						</div>
						<div class="col-lg-5">
							<div class="row g-2">
								<div class="col-sm-6">
									<div class="clock-box">
										<p class="clock-label">Tanggal Hari Ini</p>
										<p class="clock-value" id="currentDate">-</p>
									</div>
								</div>
								<div class="col-sm-6">
									<div class="clock-box">
										<p class="clock-label">Jam Sekarang</p>
										<p class="clock-value" id="currentTime">-</p>
									</div>
								</div>
								<div class="col-12">
									<a href="<?php echo site_url('home/cara_pakai'); ?>" class="clock-box clock-box-link" aria-label="Buka halaman Cara Pakai Dashboard">
										<p class="clock-label">Cara Pakai Dashboard</p>
										<p class="clock-value">Buka Panduan Lengkap</p>
										<p class="clock-help-text">Klik untuk lihat fungsi tombol, aturan potongan, dan alur sinkronisasi.</p>
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</header>

		<main class="pb-4">
			<div class="container-xl">
				<section id="ringkasan-bulan" class="mb-4">
					<h2 class="section-title">Ringkasan Bulan Ini</h2>
					<div class="row g-3">
						<div class="col-sm-6 col-xl-3">
							<article class="mini-card is-clickable" role="button" tabindex="0" data-metric-card="hadir" aria-label="Lihat grafik Total Hadir">
								<p class="mini-label">Total Hadir</p>
								<p class="mini-value"><span id="summaryTotalHadir"><?php echo htmlspecialchars((string) (isset($summary['total_hadir_bulan_ini']) ? $summary['total_hadir_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Orang</p>
								<p class="mini-hint">Klik untuk lihat grafik realtime</p>
							</article>
						</div>
						<div class="col-sm-6 col-xl-3">
							<article class="mini-card is-clickable" role="button" tabindex="0" data-metric-card="terlambat" aria-label="Lihat grafik Total Terlambat">
								<p class="mini-label">Total Terlambat</p>
								<p class="mini-value"><span id="summaryTotalTerlambat"><?php echo htmlspecialchars((string) (isset($summary['total_terlambat_bulan_ini']) ? $summary['total_terlambat_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Orang</p>
								<p class="mini-hint">Klik untuk lihat grafik realtime</p>
							</article>
						</div>
						<div class="col-sm-6 col-xl-3">
							<article class="mini-card is-clickable" role="button" tabindex="0" data-metric-card="izin_cuti" aria-label="Lihat grafik Total Izin/Cuti">
								<p class="mini-label">Total Izin/Cuti</p>
								<p class="mini-value"><span id="summaryTotalIzin"><?php echo htmlspecialchars((string) (isset($summary['total_izin_bulan_ini']) ? $summary['total_izin_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Orang</p>
								<p class="mini-hint">Klik untuk lihat grafik realtime</p>
							</article>
						</div>
						<div class="col-sm-6 col-xl-3">
							<article class="mini-card is-clickable" role="button" tabindex="0" data-metric-card="alpha" aria-label="Lihat grafik Total Alpha">
								<p class="mini-label">Total Alpha</p>
								<p class="mini-value"><span id="summaryTotalAlpha"><?php echo htmlspecialchars((string) (isset($summary['total_alpha_bulan_ini']) ? $summary['total_alpha_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Orang</p>
								<p class="mini-hint">Klik untuk lihat grafik realtime</p>
							</article>
						</div>
					</div>
				</section>

				<section id="aksi-cepat" class="mb-4">
					<h2 class="section-title">Aksi Cepat</h2>
					<div class="action-grid">
						<article class="action-card">
							<h3 class="action-title">Data Lembur</h3>
							<p class="action-text">Input manual data lembur karyawan: nama, tanggal, jam lembur, nominal, dan alasan.</p>
							<a href="<?php echo site_url('home/overtime_data'); ?>" class="action-btn">Buka Data Lembur</a>
						</article>
						<article class="action-card">
							<h3 class="action-title">Pengajuan Pinjaman</h3>
							<p class="action-text">Lihat data pengajuan pinjaman yang dikirim oleh karyawan.</p>
							<a href="<?php echo site_url('home/loan_requests'); ?>" class="action-btn secondary">Buka Pinjaman</a>
						</article>
						<article class="action-card">
							<h3 class="action-title">Pengajuan Cuti / Izin</h3>
							<p class="action-text">Lihat seluruh data pengajuan cuti dan izin yang dikirim oleh karyawan.</p>
							<a href="<?php echo site_url('home/leave_requests'); ?>" class="action-btn">Buka Pengajuan</a>
						</article>
						<article class="action-card">
							<h3 class="action-title">Cek Absensi Karyawan</h3>
							<p class="action-text">Lihat rekap absensi lengkap karyawan (masuk, pulang, telat, foto, dan jarak dari titik kantor).</p>
							<a href="<?php echo site_url('home/employee_data'); ?>" class="action-btn secondary">Buka Data Absen</a>
						</article>
					</div>
				</section>

				<section id="manajemen-karyawan" class="mb-4">
					<?php if ($can_manage_accounts): ?>
						<h2 class="section-title">Manajemen Akun Karyawan</h2>
					<?php else: ?>
						<h2 class="section-title">Sinkronisasi Data Absen</h2>
					<?php endif; ?>

					<?php if ($account_notice_success !== ''): ?>
						<div class="account-notice success"><?php echo htmlspecialchars($account_notice_success, ENT_QUOTES, 'UTF-8'); ?></div>
					<?php endif; ?>
					<?php if ($account_notice_error !== ''): ?>
						<div class="account-notice error"><?php echo htmlspecialchars($account_notice_error, ENT_QUOTES, 'UTF-8'); ?></div>
					<?php endif; ?>
					<div class="account-grid mb-3">
						<article class="account-card">
							<h3>Sinkronisasi Spreadsheet</h3>
							<?php if ($can_sync_sheet_accounts): ?>
								<p>Tarik data terbaru dari Google Sheet ke web (akun + Data Absen).</p>
							<?php else: ?>
								<p>Tarik data terbaru dari Google Sheet ke web (Data Absen).</p>
							<?php endif; ?>
							<div class="d-flex flex-wrap gap-2">
								<?php if ($can_sync_sheet_accounts): ?>
									<form method="post" action="<?php echo site_url('home/sync_sheet_accounts_now'); ?>">
										<button type="submit" class="account-submit">Sync Akun dari Sheet</button>
									</form>
								<?php endif; ?>
								<form method="post" action="<?php echo site_url('home/sync_sheet_attendance_now'); ?>">
									<button type="submit" class="account-submit">Sync Data Absen dari Sheet</button>
								</form>
								<form method="post" action="<?php echo site_url('home/sync_web_attendance_to_sheet_now'); ?>">
									<button type="submit" class="account-submit">Sync Data Web ke Sheet</button>
								</form>
							</div>
						</article>
					</div>

					<?php if ($can_manage_accounts): ?>
						<div class="account-grid mb-3">
							<article class="account-card">
								<h3>Aksi Manajemen Akun</h3>
								<p>Pilih tombol untuk membuka pop up form sesuai kebutuhan.</p>
								<div class="account-action-grid">
									<button type="button" class="account-action-btn" data-manage-modal-open="employeeCreateModal">Buat Akun Karyawan Baru</button>
									<button type="button" class="account-action-btn secondary" data-manage-modal-open="employeeManageModal">Hapus / Edit Akun Karyawan</button>
									<?php if ($can_manage_feature_accounts): ?>
										<button type="button" class="account-action-btn" data-manage-modal-open="privilegedManageModal">Kelola Akun Privileged</button>
									<?php endif; ?>
								</div>
							</article>
						</div>

						<div class="manage-modal" id="employeeCreateModal" data-manage-modal aria-hidden="true" role="dialog" aria-labelledby="employeeCreateModalTitle">
							<div class="manage-modal-card">
								<div class="manage-modal-head">
									<h3 class="manage-modal-title" id="employeeCreateModalTitle">Buat Akun Karyawan Baru</h3>
									<button type="button" class="manage-modal-close" data-manage-modal-close aria-label="Tutup popup">&times;</button>
								</div>
								<div class="manage-modal-body" id="employeeCreateModalBody"></div>
							</div>
						</div>

						<div class="manage-modal" id="employeeManageModal" data-manage-modal aria-hidden="true" role="dialog" aria-labelledby="employeeManageModalTitle">
							<div class="manage-modal-card">
								<div class="manage-modal-head">
									<h3 class="manage-modal-title" id="employeeManageModalTitle">Hapus / Edit Akun Karyawan</h3>
									<button type="button" class="manage-modal-close" data-manage-modal-close aria-label="Tutup popup">&times;</button>
								</div>
								<div class="manage-modal-body manage-modal-grid two-col" id="employeeManageModalBody"></div>
							</div>
						</div>

						<?php if ($can_manage_feature_accounts): ?>
							<div class="manage-modal" id="privilegedManageModal" data-manage-modal aria-hidden="true" role="dialog" aria-labelledby="privilegedManageModalTitle">
								<div class="manage-modal-card">
									<div class="manage-modal-head">
										<h3 class="manage-modal-title" id="privilegedManageModalTitle">Kelola Akun Privileged</h3>
										<button type="button" class="manage-modal-close" data-manage-modal-close aria-label="Tutup popup">&times;</button>
									</div>
									<div class="manage-modal-body manage-modal-grid two-col" id="privilegedManageModalBody"></div>
								</div>
							</div>
						<?php endif; ?>

						<div class="account-grid mb-3 manage-source-block" id="manageAccountSourceWrap">
							<div class="account-column-stack">
								<article class="account-card" id="createEmployeeSourceCard">
									<h3>Buat Akun Karyawan Baru</h3>
									<p>Akun dengan izin kelola akun bisa menambahkan akun login karyawan langsung dari dashboard.</p>
									<form method="post" action="<?php echo site_url('home/create_employee_account'); ?>" class="account-form" enctype="multipart/form-data">
									<div>
										<p class="account-label">Username</p>
										<input type="text" name="new_username" id="newUsernameInput" class="account-input" placeholder="contoh: userbaru" autocomplete="off" autocapitalize="off" spellcheck="false" required>
									</div>
									<div>
										<p class="account-label">Nama Lengkap</p>
										<input type="text" name="new_display_name" class="account-input" placeholder="contoh: Muhammad Ridwan K." autocomplete="off" required>
									</div>
									<div class="account-form-row two">
										<div>
											<p class="account-label">Password</p>
											<input type="text" name="new_password" class="account-input" placeholder="minimal 3 karakter" required>
										</div>
										<div>
											<p class="account-label">No Telp</p>
											<input type="text" name="new_phone" class="account-input" placeholder="08xxxxxxxxxx" required>
										</div>
									</div>
									<div class="account-form-row two">
										<div>
											<p class="account-label">Cabang</p>
											<select name="new_branch" class="account-input" required>
												<?php foreach ($branch_options as $branch_option): ?>
													<?php $branch_option_value = trim((string) $branch_option); ?>
													<?php if ($branch_option_value === ''): ?>
														<?php continue; ?>
													<?php endif; ?>
													<option value="<?php echo htmlspecialchars($branch_option_value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo strcasecmp($branch_option_value, $default_branch) === 0 ? ' selected' : ''; ?>>
														<?php echo htmlspecialchars($branch_option_value, ENT_QUOTES, 'UTF-8'); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</div>
										<div>
											<p class="account-label">Shift</p>
											<select name="new_shift" class="account-input" required>
												<option value="pagi">Shift Pagi - Sore (08:00 - 23:00)</option>
												<option value="siang">Shift Siang - Malam (14:00 - 23:00)</option>
												<option value="multishift">Multi Shift (06:30 - 23:59)</option>
											</select>
										</div>
									</div>
									<div>
										<p class="account-label">Lintas Cabang</p>
										<select name="new_cross_branch_enabled" class="account-input" required>
											<option value="0" selected>Tidak</option>
											<option value="1">Iya</option>
										</select>
									</div>
									<div class="account-form-row two">
										<div>
											<p class="account-label">Gaji Pokok (Rp)</p>
											<input type="text" name="new_salary_monthly" class="account-input" placeholder="contoh: 2500000" required>
										</div>
										<div>
											<p class="account-label">Hari Libur Mingguan</p>
											<select name="new_weekly_day_off" class="account-input" required>
												<?php foreach ($weekly_day_off_options as $weekly_day_off_value => $weekly_day_off_label): ?>
													<?php
													$weekly_day_off_n = (int) $weekly_day_off_value;
													if ($weekly_day_off_n < 1 || $weekly_day_off_n > 7) {
														continue;
													}
													?>
													<option value="<?php echo $weekly_day_off_n; ?>"<?php echo $weekly_day_off_n === $default_weekly_day_off ? ' selected' : ''; ?>>
														<?php echo htmlspecialchars((string) $weekly_day_off_label, ENT_QUOTES, 'UTF-8'); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</div>
									</div>
									<div>
										<p class="account-label">Jabatan</p>
										<select name="new_job_title" class="account-input" required>
											<?php foreach ($job_title_options as $job_title_option): ?>
												<?php $job_title_option_value = trim((string) $job_title_option); ?>
												<?php if ($job_title_option_value === ''): ?>
													<?php continue; ?>
												<?php endif; ?>
												<option value="<?php echo htmlspecialchars($job_title_option_value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $job_title_option_value === $default_job_title ? ' selected' : ''; ?>>
													<?php echo htmlspecialchars($job_title_option_value, ENT_QUOTES, 'UTF-8'); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div>
										<p class="account-label">Alamat</p>
										<input type="text" name="new_address" class="account-input" placeholder="Kp. Kesekian Kalinya, Pandenglang, Banten">
									</div>
									<div class="account-form-row two">
										<div>
											<p class="account-label">Titik Koordinat</p>
											<input type="text" name="new_coordinate_point" class="account-input" placeholder="-6.217076, 106.132128" required>
										</div>
										<div>
											<p class="account-label">Upload PP (Wajib)</p>
											<input type="file" name="new_profile_photo" class="account-input" accept=".png,.jpg,.jpeg,.heic" required>
										</div>
									</div>
										<button type="submit" class="account-submit">Simpan Akun Baru</button>
									</form>
								</article>

								<?php if ($can_manage_feature_accounts): ?>
									<article class="account-card" id="privilegedRenameSourceCard">
										<h3>Ganti Nama Akun Admin</h3>
										<p>Khusus Developer/Bos. Ubah nama tampilan akun admin untuk login dashboard.</p>
										<form method="post" action="<?php echo site_url('home/update_privileged_account_display_name'); ?>" class="account-form">
											<input type="hidden" name="target_account" value="admin">
											<div>
												<p class="account-label">Nama Baru Akun Admin</p>
												<input type="text" name="new_display_name" class="account-input" placeholder="contoh: Admin Operasional" autocomplete="off" required>
											</div>
											<button type="submit" class="account-submit">Simpan Nama Admin</button>
										</form>
									</article>

									<article class="account-card" id="privilegedPasswordSourceCard">
										<h3>Ganti Informasi Akun Privileged</h3>
										<p>Khusus Developer/Bos. Bisa ubah username login admin, nama akun, dan/atau password akun admin. Username akun developer/bos tetap tidak bisa diubah. Bos tidak bisa mengubah akun developer.</p>
										<form method="post" action="<?php echo site_url('home/update_privileged_account_password'); ?>" class="account-form">
											<div>
												<p class="account-label">Target Akun</p>
												<select name="target_account" class="account-input" required>
													<?php foreach ($privileged_password_targets as $target_account_row): ?>
														<?php
														$target_account_username = '';
														$target_account_option_label = '';
														if (is_array($target_account_row)) {
															$target_account_username = strtolower(trim((string) (isset($target_account_row['username']) ? $target_account_row['username'] : '')));
															$target_account_option_label = trim((string) (isset($target_account_row['label']) ? $target_account_row['label'] : ''));
														} else {
															$target_account_username = strtolower(trim((string) $target_account_row));
														}
														if ($target_account_username === '') {
															continue;
														}
														if ($target_account_option_label === '') {
															$target_account_option_label = $target_account_username;
														}
														?>
														<option value="<?php echo htmlspecialchars($target_account_username, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($target_account_option_label, ENT_QUOTES, 'UTF-8'); ?></option>
													<?php endforeach; ?>
												</select>
											</div>
											<div>
												<p class="account-label">Username Baru (Opsional)</p>
												<input type="text" name="new_username" id="privilegedNewUsernameInput" class="account-input" placeholder="contoh: admin_ops_baru" autocomplete="off" autocapitalize="off" spellcheck="false">
											</div>
											<div>
												<p class="account-label">Nama Baru (Opsional)</p>
												<input type="text" name="new_display_name" class="account-input" placeholder="Kosongkan jika tidak diubah" autocomplete="off">
											</div>
											<div class="account-form-row two">
												<div>
													<p class="account-label">Password Baru (Opsional)</p>
													<input type="text" name="new_password" class="account-input" placeholder="Kosongkan jika tidak diubah">
												</div>
												<div>
													<p class="account-label">Konfirmasi Password</p>
													<input type="text" name="confirm_password" class="account-input" placeholder="Isi jika password diubah">
												</div>
											</div>
											<button type="submit" class="account-submit">Simpan Informasi</button>
										</form>
									</article>

									<article class="account-card" id="privilegedCreateSourceCard">
										<h3>Buat Akun Admin Fitur</h3>
										<p>Khusus Developer/Bos. Buat akun admin custom dan pilih fitur yang diizinkan.</p>
										<form method="post" action="<?php echo site_url('home/create_feature_admin_account'); ?>" class="account-form">
											<div>
												<p class="account-label">Username</p>
												<input type="text" name="feature_admin_username" class="account-input" placeholder="contoh: admin_ops" autocomplete="off" autocapitalize="off" spellcheck="false" required>
											</div>
											<div>
												<p class="account-label">Nama Lengkap</p>
												<input type="text" name="feature_admin_display_name" class="account-input" placeholder="contoh: Admin Operasional" autocomplete="off" required>
											</div>
											<div>
												<p class="account-label">Password</p>
												<input type="text" name="feature_admin_password" class="account-input" placeholder="minimal 3 karakter" required>
											</div>
											<div>
												<p class="account-label">Fitur Akses</p>
												<div class="d-flex flex-column gap-2">
													<?php foreach ($admin_feature_catalog as $feature_key => $feature_label): ?>
														<?php $feature_key_value = trim((string) $feature_key); ?>
														<?php if ($feature_key_value === ''): ?>
															<?php continue; ?>
														<?php endif; ?>
														<label class="d-flex align-items-center gap-2">
															<input type="checkbox" name="feature_permissions[]" value="<?php echo htmlspecialchars($feature_key_value, ENT_QUOTES, 'UTF-8'); ?>">
															<span><?php echo htmlspecialchars((string) $feature_label, ENT_QUOTES, 'UTF-8'); ?></span>
														</label>
													<?php endforeach; ?>
												</div>
											</div>
											<button type="submit" class="account-submit">Simpan Akun Fitur</button>
										</form>
									</article>
								<?php endif; ?>
							</div>

							<div class="account-column-stack">
								<article class="account-card" id="employeeDeleteSourceCard">
									<h3>Hapus Akun Karyawan</h3>
									<p>Pilih akun yang ingin dihapus. Data absen, cuti/izin, pinjaman, dan lembur karyawan tersebut juga akan dibersihkan.</p>
									<form method="post" action="<?php echo site_url('home/delete_employee_account'); ?>" class="account-form" id="deleteEmployeeForm">
									<div>
										<p class="account-label">Pilih Karyawan</p>
										<input
											type="text"
											name="delete_username"
											id="deleteUsernameInput"
											class="account-input"
											list="deleteEmployeeUsernameList"
											placeholder="Cari ID atau username karyawan"
											autocomplete="off"
											required
										>
										<datalist id="deleteEmployeeUsernameList">
											<?php foreach ($employee_accounts as $employee): ?>
												<?php
												$employee_username_value = isset($employee['username']) ? trim((string) $employee['username']) : '';
												if ($employee_username_value === '') {
													continue;
												}
												$employee_id_value = isset($employee['employee_id']) ? trim((string) $employee['employee_id']) : '-';
												?>
												<option value="<?php echo htmlspecialchars($employee_username_value, ENT_QUOTES, 'UTF-8'); ?>" label="<?php echo htmlspecialchars($employee_id_value.' - '.$employee_username_value, ENT_QUOTES, 'UTF-8'); ?>"></option>
												<?php if ($employee_id_value !== '' && $employee_id_value !== '-'): ?>
													<option value="<?php echo htmlspecialchars($employee_id_value.' - '.$employee_username_value, ENT_QUOTES, 'UTF-8'); ?>"></option>
												<?php endif; ?>
											<?php endforeach; ?>
										</datalist>
									</div>
										<button type="submit" class="account-submit delete">Hapus Akun</button>
									</form>
								</article>

								<article class="account-card" id="employeeEditSourceCard">
									<h3>Edit Akun Karyawan</h3>
									<p>Ubah data akun karyawan terpilih. Password boleh dikosongkan jika tidak ingin diubah.</p>
									<form method="post" action="<?php echo site_url('home/update_employee_account'); ?>" class="account-form" id="editEmployeeForm" enctype="multipart/form-data">
									<div>
										<p class="account-label">Pilih Karyawan</p>
										<input
											type="text"
											name="edit_username"
											id="editUsernameInput"
											class="account-input"
											list="editEmployeeUsernameList"
											placeholder="Cari ID atau username karyawan"
											autocomplete="off"
											required
										>
										<datalist id="editEmployeeUsernameList">
											<?php foreach ($employee_accounts as $employee): ?>
												<?php
												$employee_username_value = isset($employee['username']) ? trim((string) $employee['username']) : '';
												if ($employee_username_value === '') {
													continue;
												}
												$employee_id_value = isset($employee['employee_id']) ? trim((string) $employee['employee_id']) : '-';
												?>
												<option value="<?php echo htmlspecialchars($employee_username_value, ENT_QUOTES, 'UTF-8'); ?>" label="<?php echo htmlspecialchars($employee_id_value.' - '.$employee_username_value, ENT_QUOTES, 'UTF-8'); ?>"></option>
												<?php if ($employee_id_value !== '' && $employee_id_value !== '-'): ?>
													<option value="<?php echo htmlspecialchars($employee_id_value.' - '.$employee_username_value, ENT_QUOTES, 'UTF-8'); ?>"></option>
												<?php endif; ?>
											<?php endforeach; ?>
										</datalist>
									</div>
									<div>
										<p class="account-label">Username</p>
										<input type="text" name="edit_new_username" id="editNewUsernameInput" class="account-input" placeholder="contoh: userbaru" autocomplete="off" autocapitalize="off" spellcheck="false" required>
									</div>
									<div>
										<p class="account-label">Nama Lengkap</p>
										<input type="text" name="edit_display_name" id="editDisplayNameInput" class="account-input" placeholder="contoh: Muhammad Ridwan K." autocomplete="off" required>
									</div>
									<div class="account-form-row two">
										<div>
											<p class="account-label">Password Baru (Opsional)</p>
											<input type="text" name="edit_password" id="editPasswordInput" class="account-input" placeholder="Kosongkan jika tidak diubah">
										</div>
										<div>
											<p class="account-label">No Telp</p>
											<input type="text" name="edit_phone" id="editPhoneInput" class="account-input" placeholder="08xxxxxxxxxx" required>
										</div>
									</div>
									<div class="account-form-row two">
										<div>
											<p class="account-label">Cabang</p>
											<select name="edit_branch" id="editBranchInput" class="account-input" required>
												<?php foreach ($branch_options as $branch_option): ?>
													<?php $branch_option_value = trim((string) $branch_option); ?>
													<?php if ($branch_option_value === ''): ?>
														<?php continue; ?>
													<?php endif; ?>
													<option value="<?php echo htmlspecialchars($branch_option_value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo strcasecmp($branch_option_value, $default_branch) === 0 ? ' selected' : ''; ?>>
														<?php echo htmlspecialchars($branch_option_value, ENT_QUOTES, 'UTF-8'); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</div>
										<div>
											<p class="account-label">Shift</p>
											<select name="edit_shift" id="editShiftInput" class="account-input" required>
												<option value="pagi">Shift Pagi - Sore (08:00 - 23:00)</option>
												<option value="siang">Shift Siang - Malam (14:00 - 23:00)</option>
												<option value="multishift">Multi Shift (06:30 - 23:59)</option>
											</select>
										</div>
									</div>
									<div>
										<p class="account-label">Lintas Cabang</p>
										<select name="edit_cross_branch_enabled" id="editCrossBranchInput" class="account-input" required>
											<option value="0">Tidak</option>
											<option value="1">Iya</option>
										</select>
									</div>
									<div>
										<p class="account-label">Gaji Pokok (Rp)</p>
										<input type="text" name="edit_salary_monthly" id="editSalaryMonthlyInput" class="account-input" placeholder="contoh: 2500000" required>
									</div>
									<div>
										<p class="account-label">Jabatan</p>
										<select name="edit_job_title" id="editJobTitleInput" class="account-input" required>
											<?php foreach ($job_title_options as $job_title_option): ?>
												<?php $job_title_option_value = trim((string) $job_title_option); ?>
												<?php if ($job_title_option_value === ''): ?>
													<?php continue; ?>
												<?php endif; ?>
												<option value="<?php echo htmlspecialchars($job_title_option_value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $job_title_option_value === $default_job_title ? ' selected' : ''; ?>>
													<?php echo htmlspecialchars($job_title_option_value, ENT_QUOTES, 'UTF-8'); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div>
										<p class="account-label">Hari Libur Mingguan</p>
										<select name="edit_weekly_day_off" id="editWeeklyDayOffInput" class="account-input" required>
											<?php foreach ($weekly_day_off_options as $weekly_day_off_value => $weekly_day_off_label): ?>
												<?php
												$weekly_day_off_n = (int) $weekly_day_off_value;
												if ($weekly_day_off_n < 1 || $weekly_day_off_n > 7) {
													continue;
												}
												?>
												<option value="<?php echo $weekly_day_off_n; ?>"<?php echo $weekly_day_off_n === $default_weekly_day_off ? ' selected' : ''; ?>>
													<?php echo htmlspecialchars((string) $weekly_day_off_label, ENT_QUOTES, 'UTF-8'); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div>
										<p class="account-label">Alamat</p>
										<input type="text" name="edit_address" id="editAddressInput" class="account-input" placeholder="Kp. Kesekian Kalinya, Pandenglang, Banten">
									</div>
									<div>
										<p class="account-label">Ganti PP (Opsional)</p>
										<input type="file" name="edit_profile_photo" id="editProfilePhotoInput" class="account-input" accept=".png,.jpg,.jpeg,.heic">
									</div>
										<button type="submit" class="account-submit">Simpan Perubahan Akun</button>
									</form>
								</article>
								<?php if ($can_manage_feature_accounts): ?>
									<article class="account-card" id="privilegedFeatureSourceCard">
										<h3>Edit Fitur Akun</h3>
										<p>Khusus Developer/Bos. Developer bisa mengubah fitur akun bos/admin. Bos tidak bisa mengubah fitur akun developer.</p>
										<?php if (empty($admin_feature_accounts)): ?>
											<p class="text-secondary mb-0">Belum ada akun admin yang bisa diubah fiturnya.</p>
										<?php else: ?>
											<form method="post" action="<?php echo site_url('home/update_feature_admin_account_permissions'); ?>" class="account-form" id="editFeatureAdminForm">
												<div>
													<p class="account-label">Target Akun</p>
													<select name="feature_target_account" id="featureTargetAccountInput" class="account-input" required>
														<?php foreach ($admin_feature_accounts as $feature_account): ?>
															<?php
															$feature_username = isset($feature_account['username']) ? trim((string) $feature_account['username']) : '';
															$feature_display_name = isset($feature_account['display_name']) ? trim((string) $feature_account['display_name']) : '';
															if ($feature_username === '') {
																continue;
															}
															$feature_option_label = $feature_display_name !== '' ? $feature_display_name.' ('.$feature_username.')' : $feature_username;
															?>
															<option value="<?php echo htmlspecialchars($feature_username, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($feature_option_label, ENT_QUOTES, 'UTF-8'); ?></option>
														<?php endforeach; ?>
													</select>
												</div>
												<div>
													<p class="account-label">Fitur Akses</p>
													<div class="d-flex flex-column gap-2">
														<?php foreach ($admin_feature_catalog as $feature_key => $feature_label): ?>
															<?php $feature_key_value = trim((string) $feature_key); ?>
															<?php if ($feature_key_value === ''): ?>
																<?php continue; ?>
															<?php endif; ?>
															<label class="d-flex align-items-center gap-2">
																<input type="checkbox" name="feature_permissions[]" value="<?php echo htmlspecialchars($feature_key_value, ENT_QUOTES, 'UTF-8'); ?>" data-feature-permission-checkbox>
																<span><?php echo htmlspecialchars((string) $feature_label, ENT_QUOTES, 'UTF-8'); ?></span>
															</label>
														<?php endforeach; ?>
													</div>
												</div>
												<button type="submit" class="account-submit">Simpan Fitur</button>
											</form>
										<?php endif; ?>
									</article>
								<?php endif; ?>
							</div>
						</div>

						<div class="table-wrap">
							<?php if (!empty($employee_accounts)): ?>
								<div class="account-table-toolbar">
									<input
										type="text"
										id="employeeAccountSearchInput"
										class="account-search-input"
										placeholder="Cari ID atau nama karyawan"
										autocomplete="off"
									>
								</div>
							<?php endif; ?>
							<div class="table-responsive">
								<table class="table table-custom account-table">
									<thead>
										<tr>
											<th>ID</th>
											<th>Username</th>
											<th>Jabatan</th>
											<th>Telp</th>
											<th>Cabang</th>
											<th>Shift</th>
											<th>Gaji Pokok</th>
										</tr>
									</thead>
									<tbody id="employeeAccountTableBody">
										<?php if (empty($employee_accounts)): ?>
											<tr class="employee-account-empty">
												<td colspan="7" class="text-center py-4 text-secondary">Belum ada akun karyawan.</td>
											</tr>
										<?php else: ?>
											<?php foreach ($employee_accounts as $employee): ?>
												<tr class="employee-account-row">
													<td><?php echo htmlspecialchars(isset($employee['employee_id']) ? (string) $employee['employee_id'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo htmlspecialchars(isset($employee['username']) ? (string) $employee['username'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo htmlspecialchars(isset($employee['job_title']) ? (string) $employee['job_title'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo htmlspecialchars(isset($employee['phone']) ? (string) $employee['phone'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo htmlspecialchars(isset($employee['branch']) ? (string) $employee['branch'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo htmlspecialchars(isset($employee['shift_name']) ? (string) $employee['shift_name'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
													<td>
														<?php
														$salary_monthly_value = isset($employee['salary_monthly']) ? (int) $employee['salary_monthly'] : 0;
														echo htmlspecialchars($salary_monthly_value > 0 ? 'Rp '.number_format($salary_monthly_value, 0, ',', '.') : '-', ENT_QUOTES, 'UTF-8');
														?>
													</td>
												</tr>
											<?php endforeach; ?>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
							<div class="account-pagination-wrap" id="employeeAccountPaginationWrap"></div>
						</div>
					<?php endif; ?>
				</section>

				<section id="riwayat-absen" class="mb-4">
					<h2 class="section-title">Riwayat Absen Terbaru</h2>
					<div class="table-wrap">
						<div class="table-responsive">
							<table class="table table-custom">
								<thead>
									<tr>
										<th>Tanggal</th>
										<th>Masuk</th>
										<th>Pulang</th>
										<th>Status</th>
										<th>Catatan</th>
									</tr>
								</thead>
								<tbody>
									<?php if (empty($recent_logs)): ?>
										<tr>
											<td colspan="5" class="text-center py-4 text-secondary">Belum ada data riwayat absen.</td>
										</tr>
									<?php else: ?>
										<?php foreach ($recent_logs as $log): ?>
											<?php
											$status = isset($log['status']) ? strtolower((string) $log['status']) : '';
											$chip_class = 'chip-hadir';
											if (strpos($status, 'terlambat') !== FALSE) {
												$chip_class = 'chip-terlambat';
											}
											elseif (strpos($status, 'izin') !== FALSE || strpos($status, 'cuti') !== FALSE) {
												$chip_class = 'chip-izin';
											}
											?>
											<tr>
												<td><?php echo htmlspecialchars(isset($log['tanggal']) ? (string) $log['tanggal'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
												<td><?php echo htmlspecialchars(isset($log['masuk']) ? (string) $log['masuk'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
												<td><?php echo htmlspecialchars(isset($log['pulang']) ? (string) $log['pulang'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
												<td><span class="status-chip <?php echo htmlspecialchars($chip_class, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(isset($log['status']) ? (string) $log['status'] : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
												<td><?php echo htmlspecialchars(isset($log['catatan']) ? (string) $log['catatan'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</section>

				<div class="metric-modal" id="metricModal" aria-hidden="true" role="dialog" aria-labelledby="metricModalTitle">
					<div class="metric-modal-card">
						<div class="metric-modal-head">
							<div class="metric-modal-title-wrap">
								<h3 class="metric-modal-title" id="metricModalTitle">Grafik Ringkasan</h3>
								<p class="metric-modal-subtitle" id="metricModalSubtitle">Realtime data</p>
							</div>
							<button type="button" class="metric-modal-close" id="metricModalClose" aria-label="Tutup popup grafik">&times;</button>
						</div>
						<div class="metric-modal-body">
							<div class="metric-legend" id="metricChartLegend">
								<span class="label">Memuat data grafik...</span>
							</div>
							<div class="metric-chart-wrap">
								<div class="metric-chart-canvas" id="metricChartCanvas"></div>
							</div>
							<div class="metric-range-row">
								<button type="button" class="metric-range-btn" data-metric-range="1H">1H</button>
								<button type="button" class="metric-range-btn" data-metric-range="1M">1M</button>
								<button type="button" class="metric-range-btn active" data-metric-range="1B">1B</button>
								<button type="button" class="metric-range-btn" data-metric-range="1T">1T</button>
								<button type="button" class="metric-range-btn" data-metric-range="ALL">Semuanya</button>
								<span class="metric-range-note">Gunakan scroll mouse untuk zoom dekat/jauh.</span>
							</div>
						</div>
					</div>
				</div>

				<p class="footer-note mb-0">Absen Online PNS - monitoring kehadiran lebih cepat dan rapi.</p>
			</div>
		</main>
	</div>

		<script>
		window.__HOME_INDEX_CONFIG = <?php echo $home_index_config_json; ?>;
	</script>
	<script defer src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.2/dist/lightweight-charts.standalone.production.js"></script>
	<script defer src="<?php echo htmlspecialchars($base_path.'/'.$home_index_js_file, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo rawurlencode($home_index_js_version); ?>"></script>
</body>
</html>
