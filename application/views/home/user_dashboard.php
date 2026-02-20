<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$summary = isset($summary) && is_array($summary) ? $summary : array();
$recent_logs = isset($recent_logs) && is_array($recent_logs) ? $recent_logs : array();
$recent_loans = isset($recent_loans) && is_array($recent_loans) ? $recent_loans : array();
$username = isset($username) && $username !== '' ? (string) $username : 'user';
$profile_photo = isset($profile_photo) && trim((string) $profile_photo) !== ''
	? (string) $profile_photo
	: '/src/assets/fotoku.JPG';
$profile_photo_url = $profile_photo;
if (strpos($profile_photo_url, 'data:') !== 0 && preg_match('/^https?:\/\//i', $profile_photo_url) !== 1)
{
	$profile_photo_relative = ltrim($profile_photo_url, '/\\');
	$profile_photo_info = pathinfo($profile_photo_relative);
	$profile_photo_thumb_relative = '';
	if (isset($profile_photo_info['filename']) && trim((string) $profile_photo_info['filename']) !== '')
	{
		$profile_photo_dir = isset($profile_photo_info['dirname']) ? (string) $profile_photo_info['dirname'] : '';
		$profile_photo_thumb_relative = $profile_photo_dir !== '' && $profile_photo_dir !== '.'
			? $profile_photo_dir.'/'.$profile_photo_info['filename'].'_thumb.jpg'
			: $profile_photo_info['filename'].'_thumb.jpg';
	}
	if ($profile_photo_thumb_relative !== '' &&
		is_file(FCPATH.str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $profile_photo_thumb_relative)))
	{
		$profile_photo_relative = $profile_photo_thumb_relative;
	}
	$profile_photo_url = base_url(ltrim($profile_photo_relative, '/'));
}
$job_title = isset($job_title) && $job_title !== '' ? (string) $job_title : 'Teknisi';
$shift_name = isset($shift_name) && $shift_name !== '' ? (string) $shift_name : 'Shift Pagi - Sore';
$shift_time = isset($shift_time) && $shift_time !== '' ? (string) $shift_time : '08:00 - 17:00';
$geofence = isset($geofence) && is_array($geofence) ? $geofence : array();
$loan_config = isset($loan_config) && is_array($loan_config) ? $loan_config : array();
$loan_min_principal = isset($loan_config['min_principal']) ? (int) $loan_config['min_principal'] : 500000;
$loan_max_principal = isset($loan_config['max_principal']) ? (int) $loan_config['max_principal'] : 10000000;
$password_notice_success = isset($password_notice_success) ? trim((string) $password_notice_success) : '';
$password_notice_error = isset($password_notice_error) ? trim((string) $password_notice_error) : '';
$office_lat = isset($geofence['office_lat']) ? (float) $geofence['office_lat'] : -6.217062;
$office_lng = isset($geofence['office_lng']) ? (float) $geofence['office_lng'] : 106.1321109;
$office_radius_m = isset($geofence['radius_m']) ? (float) $geofence['radius_m'] : 100.0;
$max_accuracy_m = isset($geofence['max_accuracy_m']) ? (float) $geofence['max_accuracy_m'] : 50.0;
$logo_file = 'src/assets/pns_logo_nav.png';
if (is_file(FCPATH.'src/assets/pns_logo_nav.png'))
{
	$logo_file = 'src/assets/pns_logo_nav.png';
}
elseif (is_file(FCPATH.'src/assts/pns_logo_nav.png'))
{
	$logo_file = 'src/assts/pns_logo_nav.png';
}
elseif (is_file(FCPATH.'src/assets/pns_new.png'))
{
	$logo_file = 'src/assets/pns_new.png';
}
elseif (is_file(FCPATH.'src/assts/pns_new.png'))
{
	$logo_file = 'src/assts/pns_new.png';
}
elseif (is_file(FCPATH.'src/assets/pns_dashboard.png'))
{
	$logo_file = 'src/assets/pns_dashboard.png';
}
elseif (is_file(FCPATH.'src/assts/pns_dashboard.png'))
{
	$logo_file = 'src/assts/pns_dashboard.png';
}
$logo_url = base_url($logo_file);
$user_dashboard_css_file = 'src/assets/css/home-user-dashboard.css';
$user_dashboard_js_file = 'src/assets/js/home-user-dashboard.js';
$user_dashboard_css_version = is_file(FCPATH.$user_dashboard_css_file) ? (string) filemtime(FCPATH.$user_dashboard_css_file) : '1';
$user_dashboard_js_version = is_file(FCPATH.$user_dashboard_js_file) ? (string) filemtime(FCPATH.$user_dashboard_js_file) : '1';
$user_dashboard_config_json = json_encode(array(
	'submitEndpoint' => parse_url(site_url('home/submit_attendance'), PHP_URL_PATH),
	'leaveRequestEndpoint' => parse_url(site_url('home/submit_leave_request'), PHP_URL_PATH),
	'loanRequestEndpoint' => parse_url(site_url('home/submit_loan_request'), PHP_URL_PATH),
	'dashboardSummaryEndpoint' => parse_url(site_url('home/user_dashboard_live_data'), PHP_URL_PATH),
	'loanConfig' => array(
		'minPrincipal' => isset($loan_config['min_principal']) ? (int) $loan_config['min_principal'] : 500000,
		'maxPrincipal' => isset($loan_config['max_principal']) ? (int) $loan_config['max_principal'] : 10000000,
		'minTenorMonths' => isset($loan_config['min_tenor_months']) ? (int) $loan_config['min_tenor_months'] : 1,
		'maxTenorMonths' => isset($loan_config['max_tenor_months']) ? (int) $loan_config['max_tenor_months'] : 12,
		'isFirstLoan' => isset($loan_config['is_first_loan']) ? (bool) $loan_config['is_first_loan'] : TRUE
	),
	'shiftTimeText' => $shift_time,
	'officeLat' => $office_lat,
	'officeLng' => $office_lng,
	'officeRadiusM' => $office_radius_m,
	'maxAccuracyM' => $max_accuracy_m
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($user_dashboard_config_json === FALSE) {
	$user_dashboard_config_json = '{}';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Dashboard Absen - User'; ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?php echo htmlspecialchars(base_url($user_dashboard_css_file), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo rawurlencode($user_dashboard_css_version); ?>">
</head>
<body>
	<nav class="topbar">
		<div class="topbar-container">
			<div class="topbar-inner">
				<a href="<?php echo site_url('home'); ?>" class="brand">
					<img class="brand-logo" src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Absen Online">
					<span class="brand-text">Dashboard User Absen</span>
				</a>
				<a href="<?php echo site_url('logout'); ?>" class="logout">Logout</a>
			</div>
		</div>
	</nav>

	<main class="container">
		<section class="hero">
			<p class="pill" id="summaryStatusPill"><?php echo htmlspecialchars(isset($summary['status_hari_ini']) ? (string) $summary['status_hari_ini'] : 'Belum Absen', ENT_QUOTES, 'UTF-8'); ?></p>
			<div class="hero-greeting">
				<img class="hero-avatar" src="<?php echo htmlspecialchars($profile_photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="PP <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
				<h1 class="hero-title">Halo, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>. Siap absen hari ini?</h1>
			</div>
			<p class="hero-subtitle">Setiap absen wajib kamera dan GPS aktif. Ambil foto langsung dari popup absensi sebelum menyimpan.</p>
			<p class="hero-note">Absen masuk dibuka mulai 06:30 WIB. Batas maksimal absen pulang adalah 23:59 WIB.</p>
			<p class="shift-badge">Jabatan: <?php echo htmlspecialchars($job_title, ENT_QUOTES, 'UTF-8'); ?></p>
			<p class="shift-badge"><?php echo htmlspecialchars($shift_name, ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($shift_time, ENT_QUOTES, 'UTF-8'); ?></p>

			<div class="action-wrap">
				<div class="action-column">
					<button type="button" class="action-btn checkin" data-attendance-open="true">
						<p class="action-title">Absen</p>
						<p class="action-text">Tap untuk pilih absen masuk atau pulang.</p>
					</button>
					<button type="button" class="action-btn leave" data-request-type="cuti">
						<p class="action-title">Pengajuan Cuti</p>
						<p class="action-text">Ajukan cuti jika kamu akan libur kerja.</p>
					</button>
				</div>
				<div class="action-column">
					<button type="button" class="action-btn permit" data-request-type="izin">
						<p class="action-title">Pengajuan Izin</p>
						<p class="action-text">Ajukan izin untuk keperluan tertentu.</p>
					</button>
					<button type="button" class="action-btn loan" data-loan-open="true">
						<p class="action-title">Pengajuan Pinjaman</p>
						<p class="action-text">Ajukan pinjaman dengan nominal, tenor, alasan, dan rincian otomatis.</p>
					</button>
				</div>
			</div>

			<div class="meta">
				<div class="meta-box">
					<p class="meta-label">Jam Masuk / Pulang</p>
					<p class="meta-value">
						<span id="summaryJamMasuk"><?php echo htmlspecialchars(isset($summary['jam_masuk']) ? (string) $summary['jam_masuk'] : '-', ENT_QUOTES, 'UTF-8'); ?></span>
						/
						<span id="summaryJamPulang"><?php echo htmlspecialchars(isset($summary['jam_pulang']) ? (string) $summary['jam_pulang'] : '-', ENT_QUOTES, 'UTF-8'); ?></span>
					</p>
				</div>
				<div class="meta-box warning">
					<p class="meta-label">Target Jam Pulang</p>
					<p class="meta-value"><span id="summaryTargetPulang"><?php echo htmlspecialchars(isset($summary['target_pulang']) ? (string) $summary['target_pulang'] : '17:00', ENT_QUOTES, 'UTF-8'); ?></span> WIB</p>
				</div>
			</div>

			<div class="summary-grid">
				<div class="summary-item">
					<p class="summary-label">Hadir Bulan Ini</p>
					<p class="summary-value"><span id="summaryHadirBulan"><?php echo htmlspecialchars((string) (isset($summary['total_hadir_bulan_ini']) ? $summary['total_hadir_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Hari</p>
				</div>
				<div class="summary-item">
					<p class="summary-label">Terlambat</p>
					<p class="summary-value"><span id="summaryTerlambatBulan"><?php echo htmlspecialchars((string) (isset($summary['total_terlambat_bulan_ini']) ? $summary['total_terlambat_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Hari</p>
				</div>
				<div class="summary-item">
					<p class="summary-label">Izin/Cuti</p>
					<p class="summary-value"><span id="summaryIzinBulan"><?php echo htmlspecialchars((string) (isset($summary['total_izin_bulan_ini']) ? $summary['total_izin_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Hari</p>
				</div>
			</div>
		</section>

		<section class="history" id="ubah-password">
			<h2 class="history-title">Ganti Password Akun</h2>
			<?php if ($password_notice_success !== ''): ?>
				<div class="password-alert success"><?php echo htmlspecialchars($password_notice_success, ENT_QUOTES, 'UTF-8'); ?></div>
			<?php endif; ?>
			<?php if ($password_notice_error !== ''): ?>
				<div class="password-alert error"><?php echo htmlspecialchars($password_notice_error, ENT_QUOTES, 'UTF-8'); ?></div>
			<?php endif; ?>
			<form class="password-form" method="post" action="<?php echo site_url('home/update_my_password'); ?>" autocomplete="off">
				<div class="password-grid">
					<div class="password-field">
						<label class="password-label" for="currentPassword">Password Saat Ini</label>
						<input class="password-input" type="password" id="currentPassword" name="current_password" required>
					</div>
					<div class="password-field">
						<label class="password-label" for="newPassword">Password Baru</label>
						<input class="password-input" type="password" id="newPassword" name="new_password" minlength="3" required>
					</div>
					<div class="password-field">
						<label class="password-label" for="confirmPassword">Konfirmasi Password Baru</label>
						<input class="password-input" type="password" id="confirmPassword" name="confirm_password" minlength="3" required>
					</div>
				</div>
				<button type="submit" class="password-submit">Simpan Password Baru</button>
			</form>
		</section>

		<section class="history">
			<h2 class="history-title">Riwayat Absensi Terbaru</h2>
			<div class="table-wrap">
				<table>
					<thead>
						<tr>
							<th>Tanggal</th>
							<th>Masuk</th>
							<th>Pulang</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody id="historyTableBody">
						<?php if (empty($recent_logs)): ?>
							<tr>
								<td colspan="4">Belum ada riwayat absensi.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($recent_logs as $log): ?>
								<?php
								$status = isset($log['status']) ? strtolower((string) $log['status']) : '';
								$status_class = strpos($status, 'terlambat') !== FALSE ? 'terlambat' : 'hadir';
								?>
								<tr>
									<td><?php echo htmlspecialchars(isset($log['tanggal']) ? (string) $log['tanggal'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($log['masuk']) ? (string) $log['masuk'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($log['pulang']) ? (string) $log['pulang'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><span class="badge <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(isset($log['status']) ? (string) $log['status'] : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>

		<section class="history">
			<h2 class="history-title">Riwayat Pinjaman</h2>
			<div class="table-wrap">
				<table>
					<thead>
						<tr>
							<th>Tanggal</th>
							<th>Nominal</th>
							<th>Tenor</th>
							<th>Cicilan/Bulan</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody id="loanHistoryTableBody">
						<?php if (empty($recent_loans)): ?>
							<tr>
								<td colspan="5">Belum ada riwayat pinjaman.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($recent_loans as $loan): ?>
								<?php
								$loan_status = isset($loan['status']) ? strtolower(trim((string) $loan['status'])) : '';
								$loan_status_class = 'menunggu';
								if (strpos($loan_status, 'terima') !== FALSE)
								{
									$loan_status_class = 'diterima';
								}
								elseif (strpos($loan_status, 'tolak') !== FALSE)
								{
									$loan_status_class = 'ditolak';
								}
								?>
								<tr>
									<td><?php echo htmlspecialchars(isset($loan['tanggal']) ? (string) $loan['tanggal'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($loan['nominal']) ? (string) $loan['nominal'] : 'Rp 0', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($loan['tenor']) ? (string) $loan['tenor'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($loan['cicilan_bulanan']) ? (string) $loan['cicilan_bulanan'] : 'Rp 0', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><span class="badge <?php echo htmlspecialchars($loan_status_class, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(isset($loan['status']) ? (string) $loan['status'] : 'Menunggu', ENT_QUOTES, 'UTF-8'); ?></span></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</main>

	<div id="attendanceModal" class="attendance-modal" aria-hidden="true">
		<div class="modal-overlay" data-modal-close></div>
		<section class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="attendanceTitle">
			<div class="modal-head">
				<h2 id="attendanceTitle" class="modal-title">Proses Absensi</h2>
				<button type="button" class="modal-close" id="closeAttendanceModal" aria-label="Tutup popup">&times;</button>
			</div>
			<div class="modal-body">
				<div class="modal-grid">
					<div class="preview-card">
						<p class="card-label">Preview Kamera</p>
						<div class="video-wrap">
							<video id="cameraPreview" class="camera-video" autoplay muted playsinline></video>
							<div id="cameraPlaceholder" class="camera-placeholder">Meminta akses kamera...</div>
						</div>
						<div class="control-row">
							<select id="cameraSelect" class="camera-select" aria-label="Pilih kamera">
								<option value="">Memuat daftar kamera...</option>
							</select>
							<button type="button" id="capturePhotoButton" class="capture-btn" disabled>Ambil Foto</button>
						</div>
						<canvas id="captureCanvas" width="1280" height="720" hidden></canvas>
					</div>
					<div class="info-card">
						<p class="card-label">Informasi Absensi</p>
						<div class="info-item">
							<p class="info-title">Jenis Absensi (Wajib Pilih)</p>
							<select id="attendanceTypeSelect" class="request-input" aria-label="Pilih jenis absensi">
								<option value="masuk">Absen Masuk</option>
								<option value="pulang">Absen Pulang</option>
							</select>
						</div>
							<div class="info-item">
								<p class="info-title">Shift</p>
								<p id="shiftValue" class="info-value"><?php echo htmlspecialchars($shift_name.' ('.$shift_time.')', ENT_QUOTES, 'UTF-8'); ?></p>
							</div>
							<div class="info-item">
								<p class="info-title">Aturan Waktu</p>
								<p class="info-value">Masuk: mulai 06:30 WIB | Pulang: maksimal 23:59 WIB</p>
							</div>
							<div class="info-item">
								<p class="info-title">GPS</p>
								<p id="gpsValue" class="info-value">Meminta lokasi...</p>
							</div>
						<div class="info-item">
							<p class="info-title">Waktu</p>
							<p id="timeValue" class="info-value">-</p>
						</div>
						<div class="info-item late-reason-wrap" id="lateReasonWrap">
							<p class="info-title">Alasan Telat (Wajib Jika Telat)</p>
							<textarea id="lateReasonInput" class="late-reason-input" placeholder="Tulis alasan keterlambatan saat absen masuk..."></textarea>
						</div>
					</div>
				</div>

				<div id="captureResult" class="capture-result">
					<h3>Hasil Foto Absensi</h3>
					<img id="capturedImage" class="capture-image" src="" alt="Hasil foto absensi">
					<p id="resultMeta" class="result-meta"></p>
				</div>
			</div>
		</section>
	</div>

	<div id="requestModal" class="request-modal" aria-hidden="true">
		<div class="modal-overlay" data-request-close></div>
		<section class="request-panel" role="dialog" aria-modal="true" aria-labelledby="requestTitle">
			<div class="request-head">
				<h2 id="requestTitle" class="request-title">Form Pengajuan</h2>
				<button type="button" class="modal-close" id="closeRequestModal" aria-label="Tutup popup pengajuan">&times;</button>
			</div>
			<div class="request-body">
				<form id="leaveRequestForm" class="request-form" novalidate>
					<input type="hidden" id="requestTypeInput" name="request_type" value="">
					<p class="request-kind">Jenis pengajuan: <strong id="requestTypeLabel">-</strong></p>
					<div class="request-field is-hidden" id="izinTypeWrap">
						<label for="izinTypeInput" class="request-label">Jenis Izin</label>
						<select id="izinTypeInput" class="request-input" name="izin_type">
							<option value="sakit">Sakit</option>
							<option value="darurat">Izin Darurat</option>
						</select>
					</div>
					<div class="request-grid">
						<div class="request-field">
							<label for="requestStartDate" class="request-label">Tanggal Mulai</label>
							<input id="requestStartDate" class="request-input" type="date" name="start_date" required>
						</div>
						<div class="request-field">
							<label for="requestEndDate" class="request-label">Tanggal Selesai</label>
							<input id="requestEndDate" class="request-input" type="date" name="end_date" required>
						</div>
					</div>
					<div class="request-field">
						<label for="requestReason" class="request-label" id="requestReasonLabel">Alasan Izin</label>
						<textarea id="requestReason" class="request-textarea" name="reason" placeholder="Contoh: ada keperluan keluarga / urusan administrasi penting..." required></textarea>
					</div>
					<div class="request-field">
						<label for="requestSupportFile" class="request-label" id="requestSupportLabel">Bukti (Opsional)</label>
						<input id="requestSupportFile" class="request-input" type="file" name="support_file" accept=".pdf,.png,.jpg,.heic">
						<p class="request-hint" id="requestSupportHint">Format yang diizinkan: .pdf, .png, .jpg, .heic</p>
					</div>
					<button type="submit" id="submitLeaveRequestButton" class="request-submit">Kirim Pengajuan</button>
				</form>
			</div>
		</section>
	</div>

	<div id="loanModal" class="request-modal" aria-hidden="true">
		<div class="modal-overlay" data-loan-close></div>
		<section class="request-panel" role="dialog" aria-modal="true" aria-labelledby="loanTitle">
			<div class="request-head">
				<h2 id="loanTitle" class="request-title">Form Pengajuan Pinjaman</h2>
				<button type="button" class="modal-close" id="closeLoanModal" aria-label="Tutup popup pinjaman">&times;</button>
			</div>
			<div class="request-body">
				<form id="loanRequestForm" class="request-form" novalidate>
					<div class="request-field">
						<div class="request-label-row">
							<label for="loanAmount" class="request-label">Nominal Pinjaman</label>
							<p class="request-label-note">Min Rp <?php echo number_format($loan_min_principal, 0, ',', '.'); ?> | Max Rp <?php echo number_format($loan_max_principal, 0, ',', '.'); ?></p>
						</div>
						<input id="loanAmount" class="request-input" type="text" inputmode="numeric" autocomplete="off" placeholder="Contoh: 1500000" required>
					</div>
					<div class="request-field">
						<label for="loanTenor" class="request-label">Tenor (Bulan)</label>
						<input id="loanTenor" type="hidden" value="">
						<div class="tenor-picker" id="loanTenorPicker">
							<div class="tenor-grid">
								<?php for ($tenor_option = 1; $tenor_option <= 4; $tenor_option += 1): ?>
									<button type="button" class="tenor-btn" data-loan-tenor="<?php echo $tenor_option; ?>" aria-pressed="false"><?php echo $tenor_option; ?> bulan</button>
								<?php endfor; ?>
							</div>
							<button type="button" id="toggleMoreTenorButton" class="tenor-more-toggle">Lihat lainnya...</button>
							<div id="tenorMoreWrap" class="tenor-grid is-hidden">
								<?php for ($tenor_option = 5; $tenor_option <= 12; $tenor_option += 1): ?>
									<button type="button" class="tenor-btn" data-loan-tenor="<?php echo $tenor_option; ?>" aria-pressed="false"><?php echo $tenor_option; ?> bulan</button>
								<?php endfor; ?>
							</div>
						</div>
					</div>
					<div class="request-field">
						<label for="loanReason" class="request-label">Alasan Pinjaman</label>
						<textarea id="loanReason" class="request-textarea" placeholder="Jelaskan alasan pengajuan pinjaman..." required></textarea>
					</div>
					<div class="request-field">
						<label class="request-label">Rincian Pinjaman</label>
						<div class="loan-detail-card" id="loanDetailCard" hidden>
							<div id="loanDetailContent" class="loan-detail-content" hidden>
								<div class="loan-detail-row">
									<span>Nominal pinjaman</span>
									<strong id="loanNominalValue">Rp 0</strong>
								</div>
								<div class="loan-detail-row">
									<span>Tenor</span>
									<strong id="loanTenorValue">0 bulan</strong>
								</div>
								<div class="loan-detail-row loan-detail-status">
									<span>Status</span>
									<strong id="loanStatusValue">-</strong>
								</div>
								<div class="loan-detail-row loan-detail-row-highlight">
									<span>Bunga <span class="loan-rate" id="loanRateValue">0,00% per bulan</span></span>
									<strong id="loanInterestValue">Rp 0</strong>
								</div>
								<div class="loan-detail-row">
									<span>Cicilan per bulan</span>
									<strong id="loanMonthlyInstallmentValue">Rp 0</strong>
								</div>
								<div class="loan-detail-divider"></div>
								<div class="loan-detail-row loan-detail-total">
									<span>Total cicilan</span>
									<strong id="loanTotalValue">Rp 0</strong>
								</div>
							</div>
						</div>
					</div>
					<button type="submit" id="submitLoanRequestButton" class="request-submit">Kirim Pengajuan Pinjaman</button>
				</form>
			</div>
		</section>
	</div>

	<div id="toastMessage" class="toast" role="status" aria-live="polite"></div>

		<script>
		window.__USER_DASHBOARD_CONFIG = <?php echo $user_dashboard_config_json; ?>;
	</script>
	<script defer src="<?php echo htmlspecialchars(base_url($user_dashboard_js_file), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo rawurlencode($user_dashboard_js_version); ?>"></script>
</body>
</html>
