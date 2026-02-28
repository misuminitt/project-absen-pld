<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$summary = isset($summary) && is_array($summary) ? $summary : array();
$recent_logs = isset($recent_logs) && is_array($recent_logs) ? $recent_logs : array();
$recent_loans = isset($recent_loans) && is_array($recent_loans) ? $recent_loans : array();
$day_off_swap_requests = isset($day_off_swap_requests) && is_array($day_off_swap_requests) ? array_values($day_off_swap_requests) : array();
$username = isset($username) && $username !== '' ? (string) $username : 'user';
$profile_photo = isset($profile_photo) && trim((string) $profile_photo) !== ''
	? (string) $profile_photo
	: (is_file(FCPATH.'src/assets/fotoku.webp') ? '/src/assets/fotoku.webp' : '/src/assets/fotoku.JPG');
$profile_photo_url = $profile_photo;
if (strpos($profile_photo_url, 'data:') !== 0 && preg_match('/^https?:\/\//i', $profile_photo_url) !== 1)
{
	$profile_photo_relative = ltrim($profile_photo_url, '/\\');
	$profile_photo_info = pathinfo($profile_photo_relative);
	$profile_photo_thumb_relative = '';
	$profile_photo_cache_version = 0;
	$profile_photo_absolute = FCPATH.str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $profile_photo_relative);
	if (is_file($profile_photo_absolute))
	{
		$profile_photo_cache_version = (int) @filemtime($profile_photo_absolute);
	}
	if (isset($profile_photo_info['filename']) && trim((string) $profile_photo_info['filename']) !== '')
	{
		$profile_photo_dir = isset($profile_photo_info['dirname']) ? (string) $profile_photo_info['dirname'] : '';
		$profile_photo_thumb_relative = $profile_photo_dir !== '' && $profile_photo_dir !== '.'
			? $profile_photo_dir.'/'.$profile_photo_info['filename'].'_thumb.webp'
			: $profile_photo_info['filename'].'_thumb.webp';
	}
	if ($profile_photo_thumb_relative !== '' &&
		is_file(FCPATH.str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $profile_photo_thumb_relative)))
	{
		$profile_photo_thumb_absolute = FCPATH.str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $profile_photo_thumb_relative);
		$profile_photo_thumb_version = (int) @filemtime($profile_photo_thumb_absolute);
		// Pakai thumbnail hanya jika tidak lebih lama dari foto utama.
		if ($profile_photo_thumb_version >= $profile_photo_cache_version)
		{
			$profile_photo_relative = $profile_photo_thumb_relative;
			$profile_photo_cache_version = $profile_photo_thumb_version;
		}
	}
	$profile_photo_url = base_url(ltrim($profile_photo_relative, '/'));
	if ($profile_photo_cache_version > 0)
	{
		$profile_photo_url .= '?v='.$profile_photo_cache_version;
	}
}
$job_title = isset($job_title) && $job_title !== '' ? (string) $job_title : 'Teknisi';
$shift_name = isset($shift_name) && $shift_name !== '' ? (string) $shift_name : 'Shift Pagi - Sore';
$shift_time = isset($shift_time) && $shift_time !== '' ? (string) $shift_time : '08:00 - 17:00';
$shift_name_lower = strtolower($shift_name);
$shift_time_lower = strtolower($shift_time);
$shift_key_dashboard = 'pagi';
if (strpos($shift_name_lower, 'multi') !== FALSE ||
	((strpos($shift_time_lower, '08:00') !== FALSE || strpos($shift_time_lower, '07:00') !== FALSE || strpos($shift_time_lower, '06:30') !== FALSE) &&
	 (strpos($shift_time_lower, '23:59') !== FALSE || strpos($shift_time_lower, '23:00') !== FALSE)))
{
	$shift_key_dashboard = 'multishift';
}
elseif (
	strpos($shift_name_lower, 'siang') !== FALSE ||
	strpos($shift_time_lower, '14:00') !== FALSE ||
	strpos($shift_time_lower, '12:00') !== FALSE
)
{
	$shift_key_dashboard = 'siang';
}
if ($shift_key_dashboard === 'multishift')
{
	$shift_time = '08:00 - 23:59';
}
elseif ($shift_key_dashboard === 'siang')
{
	$shift_time = '14:00 - 23:59';
}
else
{
	$shift_time = '08:00 - 17:00';
}

$hero_note_text = 'Absen masuk dibuka mulai 07:30 WIB. Jam masuk resmi 08:00 WIB (tidak telat sampai 08:00).';
$attendance_rule_text = 'Masuk: dibuka 07:30 WIB | Jam masuk resmi 08:00 WIB | Pulang: maksimal 23:00 WIB (wajib sudah absen masuk)';
$attendance_shift_detail_lines = array();
if ($shift_key_dashboard === 'siang')
{
	$hero_note_text = 'Mode Shift Siang aktif. Absen masuk bisa dilakukan dari jam 13:30 sampai 23:00 WIB.';
	$attendance_rule_text = 'Masuk: 13:30 - 23:00 WIB | Tidak telat sampai 14:00 WIB | Pulang: maksimal 23:59 WIB (wajib sudah absen masuk)';
	$attendance_shift_detail_lines = array(
		'Tidak telat jika absen masuk pukul 13:30 - 14:00 WIB.',
		'Mulai telat pada pukul 14:01 - 23:59 WIB.',
		'Agar tidak telat: lakukan absen sebelum 14:01 WIB.'
	);
}
elseif ($shift_key_dashboard === 'multishift')
{
	$hero_note_text = 'Mode Multi Shift aktif. Absen dibuka 07:30 WIB. Jam masuk resmi 08:00 atau 14:00 WIB.';
	$attendance_rule_text = 'Masuk: dibuka 07:30 - 23:00 WIB | Tidak telat sampai 08:00 atau 14:00 WIB | Pulang: maksimal 23:59 WIB (wajib sudah absen masuk)';
	$attendance_shift_detail_lines = array(
		'Tidak telat jika absen masuk pukul 07:30 - 08:00 WIB atau 13:30 - 14:00 WIB.',
		'Mulai telat pada pukul 08:01 - 13:29 WIB dan 14:01 - 23:59 WIB.',
		'Agar tidak telat: lakukan absen sebelum 08:01 WIB atau pada slot 13:30 - 14:00 WIB.'
	);
}
else
{
	$attendance_shift_detail_lines = array(
		'Tidak telat jika absen masuk pukul 07:30 - 08:00 WIB.',
		'Mulai telat pada pukul 08:01 - 17:00 WIB.',
		'Agar tidak telat: lakukan absen sebelum 08:01 WIB.'
	);
}
$geofence = isset($geofence) && is_array($geofence) ? $geofence : array();
$loan_config = isset($loan_config) && is_array($loan_config) ? $loan_config : array();
$loan_min_principal = isset($loan_config['min_principal']) ? (int) $loan_config['min_principal'] : 500000;
$loan_max_principal = isset($loan_config['max_principal']) ? (int) $loan_config['max_principal'] : 10000000;
$password_notice_success = isset($password_notice_success) ? trim((string) $password_notice_success) : '';
$password_notice_error = isset($password_notice_error) ? trim((string) $password_notice_error) : '';
$swap_request_notice_success = isset($swap_request_notice_success) ? trim((string) $swap_request_notice_success) : '';
$swap_request_notice_error = isset($swap_request_notice_error) ? trim((string) $swap_request_notice_error) : '';
$office_lat = isset($geofence['office_lat']) ? (float) $geofence['office_lat'] : -6.217076;
$office_lng = isset($geofence['office_lng']) ? (float) $geofence['office_lng'] : 106.132128;
$office_radius_m = isset($geofence['radius_m']) ? (float) $geofence['radius_m'] : 100.0;
$max_accuracy_m = isset($geofence['max_accuracy_m']) ? (float) $geofence['max_accuracy_m'] : 50.0;
$office_points = isset($geofence['office_points']) && is_array($geofence['office_points']) ? $geofence['office_points'] : array();
$office_fallback_points = isset($geofence['office_fallback_points']) && is_array($geofence['office_fallback_points'])
	? $geofence['office_fallback_points']
	: array();
$attendance_branch = isset($geofence['attendance_branch']) ? trim((string) $geofence['attendance_branch']) : '';
$geofence_cross_branch_enabled = isset($geofence['cross_branch_enabled']) ? ((int) $geofence['cross_branch_enabled'] === 1 ? 1 : 0) : 0;
$geofence_shift_key = isset($geofence['shift_key']) ? trim((string) $geofence['shift_key']) : '';
$normalized_office_points = array();
for ($office_point_i = 0; $office_point_i < count($office_points); $office_point_i += 1)
{
	$office_row = isset($office_points[$office_point_i]) && is_array($office_points[$office_point_i])
		? $office_points[$office_point_i]
		: array();
	$office_row_lat = isset($office_row['lat']) ? (float) $office_row['lat'] : 0.0;
	$office_row_lng = isset($office_row['lng']) ? (float) $office_row['lng'] : 0.0;
	if (!is_finite($office_row_lat) || !is_finite($office_row_lng))
	{
		continue;
	}
	$normalized_office_points[] = array(
		'label' => isset($office_row['label']) ? (string) $office_row['label'] : 'Kantor',
		'lat' => $office_row_lat,
		'lng' => $office_row_lng
	);
}
if (empty($normalized_office_points))
{
	$normalized_office_points[] = array(
		'label' => 'Kantor',
		'lat' => $office_lat,
		'lng' => $office_lng
	);
}
$normalized_office_fallback_points = array();
for ($office_fallback_i = 0; $office_fallback_i < count($office_fallback_points); $office_fallback_i += 1)
{
	$office_fallback_row = isset($office_fallback_points[$office_fallback_i]) && is_array($office_fallback_points[$office_fallback_i])
		? $office_fallback_points[$office_fallback_i]
		: array();
	$office_fallback_lat = isset($office_fallback_row['lat']) ? (float) $office_fallback_row['lat'] : 0.0;
	$office_fallback_lng = isset($office_fallback_row['lng']) ? (float) $office_fallback_row['lng'] : 0.0;
	if (!is_finite($office_fallback_lat) || !is_finite($office_fallback_lng))
	{
		continue;
	}
	$normalized_office_fallback_points[] = array(
		'label' => isset($office_fallback_row['label']) ? (string) $office_fallback_row['label'] : 'Kantor',
		'lat' => $office_fallback_lat,
		'lng' => $office_fallback_lng
	);
}
$navbar_logo_file = 'src/assets/pns_logo_nav.png';
$favicon_file = 'src/assets/sinyal.svg';
$favicon_type = 'image/svg+xml';

$navbar_logo_url = base_url($navbar_logo_file);
$navbar_logo_version = is_file(FCPATH.$navbar_logo_file) ? (string) @md5_file(FCPATH.$navbar_logo_file) : '';
if ($navbar_logo_version !== '')
{
	$navbar_logo_url .= '?v='.rawurlencode($navbar_logo_version);
}

$favicon_url = site_url('home/favicon');
$favicon_version = is_file(FCPATH.$favicon_file) ? (string) @md5_file(FCPATH.$favicon_file) : '';
if ($favicon_version !== '')
{
	$favicon_url .= '?v='.rawurlencode($favicon_version);
}
$user_dashboard_css_file = 'src/assets/css/home-user-dashboard.css';
$user_dashboard_js_file = 'src/assets/js/home-user-dashboard.js';
$theme_global_css_file = 'src/assets/css/theme-global.css';
$theme_global_js_file = 'src/assets/js/theme-global-init.js';
$user_dashboard_css_path = FCPATH.$user_dashboard_css_file;
$user_dashboard_js_path = FCPATH.$user_dashboard_js_file;
$theme_global_css_path = FCPATH.$theme_global_css_file;
$theme_global_js_path = FCPATH.$theme_global_js_file;
$user_dashboard_css_version = is_file($user_dashboard_css_path) ? (string) @md5_file($user_dashboard_css_path) : '1';
$user_dashboard_js_version = is_file($user_dashboard_js_path) ? (string) @md5_file($user_dashboard_js_path) : '1';
$theme_global_css_version = is_file($theme_global_css_path) ? (string) @filemtime($theme_global_css_path) : '1';
$theme_global_js_version = is_file($theme_global_js_path) ? (string) @filemtime($theme_global_js_path) : '1';
if ($user_dashboard_css_version === '')
{
	$user_dashboard_css_version = '1';
}
if ($user_dashboard_js_version === '')
{
	$user_dashboard_js_version = '1';
}
if ($theme_global_css_version === '')
{
	$theme_global_css_version = '1';
}
if ($theme_global_js_version === '')
{
	$theme_global_js_version = '1';
}
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
	'shiftKey' => $geofence_shift_key !== '' ? $geofence_shift_key : $shift_key_dashboard,
	'attendanceBranch' => $attendance_branch,
	'crossBranchEnabled' => $geofence_cross_branch_enabled,
	'officeLat' => $office_lat,
	'officeLng' => $office_lng,
	'officeRadiusM' => $office_radius_m,
	'maxAccuracyM' => $max_accuracy_m,
	'officePoints' => $normalized_office_points,
	'officeFallbackPoints' => $normalized_office_fallback_points
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($user_dashboard_config_json === FALSE) {
	$user_dashboard_config_json = '{}';
}
?>
<?php
$_home_theme_cookie_value = isset($_COOKIE['home_index_theme']) ? strtolower(trim((string) $_COOKIE['home_index_theme'])) : '';
$_home_theme_session_value = '';
if (isset($this) && isset($this->session) && method_exists($this->session, 'userdata'))
{
	$_home_theme_session_value = strtolower(trim((string) $this->session->userdata('home_index_theme')));
}
if ($_home_theme_cookie_value === 'dark' || $_home_theme_cookie_value === 'light')
{
	$_home_theme_value = $_home_theme_cookie_value;
}
elseif ($_home_theme_session_value === 'dark' || $_home_theme_session_value === 'light')
{
	$_home_theme_value = $_home_theme_session_value;
}
else
{
	$_home_theme_value = '';
}
$_home_theme_is_dark = $_home_theme_value === 'dark';
$_home_theme_html_class = $_home_theme_is_dark ? ' class="theme-dark"' : '';
$_home_theme_html_data = ' data-theme="' . ($_home_theme_is_dark ? 'dark' : 'light') . '"';
$_home_theme_body_class = $_home_theme_is_dark ? ' class="theme-dark"' : '';
?>
<!DOCTYPE html>
<html lang="id"<?php echo $_home_theme_html_class; ?><?php echo $_home_theme_html_data; ?>>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Dashboard Absen - User'; ?></title>
	<link rel="icon" type="<?php echo htmlspecialchars($favicon_type, ENT_QUOTES, 'UTF-8'); ?>" href="/src/assets/sinyal.svg">
	<link rel="shortcut icon" type="<?php echo htmlspecialchars($favicon_type, ENT_QUOTES, 'UTF-8'); ?>" href="/src/assets/sinyal.svg">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?php echo htmlspecialchars(base_url($user_dashboard_css_file), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo rawurlencode($user_dashboard_css_version); ?>">
	<script>
		(function () {
			var themeValue = "";
			try {
				themeValue = String(window.localStorage.getItem("home_index_theme") || "").toLowerCase();
			} catch (error) {}
			if (themeValue !== "dark" && themeValue !== "light") {
				var cookieMatch = document.cookie.match(/(?:^|;\s*)home_index_theme=(dark|light)\b/i);
				if (cookieMatch && cookieMatch[1]) {
					themeValue = String(cookieMatch[1]).toLowerCase();
				}
			}
			if (themeValue === "dark" || themeValue === "light") {
				try {
					window.localStorage.setItem("home_index_theme", themeValue);
				} catch (error) {}
				try {
					document.cookie = "home_index_theme=" + encodeURIComponent(themeValue) + ";path=/;max-age=31536000;SameSite=Lax";
				} catch (error) {}
			}
			if (themeValue === "dark") {
				document.documentElement.classList.add("theme-dark");
				document.documentElement.setAttribute("data-theme", "dark");
			} else if (themeValue === "light") {
				document.documentElement.classList.remove("theme-dark");
				document.documentElement.setAttribute("data-theme", "light");
			}
		})();
	</script>
	<script src="<?php echo htmlspecialchars(base_url($theme_global_js_file), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo rawurlencode($theme_global_js_version); ?>"></script>
		<link rel="stylesheet" href="<?php echo htmlspecialchars(base_url($theme_global_css_file), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo rawurlencode($theme_global_css_version); ?>">
</head>
<body<?php echo $_home_theme_body_class; ?> data-theme-mobile-toggle="1" data-theme-native-toggle="1">
	<nav class="topbar">
		<div class="topbar-container">
			<div class="topbar-inner">
				<a href="<?php echo site_url('home'); ?>" class="brand">
					<img class="brand-logo" src="<?php echo htmlspecialchars($navbar_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Absen Online">
					<span class="brand-text">Dashboard User Absen</span>
				</a>
				<div class="topbar-actions">
					<button type="button" class="theme-navbar-toggle" id="themeToggleButton" aria-label="Aktifkan mode malam" aria-pressed="false" title="Ganti ke mode malam">
						<span class="theme-navbar-toggle-track" aria-hidden="true">
							<span class="theme-navbar-toggle-icon sun">&#9728;</span>
							<span class="theme-navbar-toggle-icon moon">&#9790;</span>
							<span class="theme-navbar-toggle-knob"></span>
						</span>
					</button>
					<a href="<?php echo site_url('logout'); ?>" class="logout">Logout</a>
				</div>
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
			<p class="hero-note"><?php echo htmlspecialchars($hero_note_text, ENT_QUOTES, 'UTF-8'); ?></p>
			<p class="shift-badge">Jabatan: <?php echo htmlspecialchars($job_title, ENT_QUOTES, 'UTF-8'); ?></p>
			<p class="shift-badge"><?php echo htmlspecialchars($shift_name, ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($shift_time, ENT_QUOTES, 'UTF-8'); ?></p>

			<div class="action-wrap">
				<div class="action-column">
					<button type="button" class="action-btn checkin" data-attendance-open="true">
						<p class="action-title">Absen</p>
						<p class="action-text">Tap untuk pilih absen masuk atau pulang.</p>
					</button>
					<button type="button" class="action-btn leave is-coming-soon" disabled aria-disabled="true" title="COMING SOON">
						<span class="coming-soon-badge">COMING SOON</span>
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
					<button type="button" class="action-btn swap" data-swap-open="true">
						<p class="action-title">Pengajuan Tukar Libur</p>
						<p class="action-text">Ajukan tukar hari libur 1x. Wajib menunggu persetujuan admin.</p>
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
					<p class="meta-value"><span id="summaryTargetPulang"><?php echo htmlspecialchars(isset($summary['target_pulang']) ? (string) $summary['target_pulang'] : '23:00', ENT_QUOTES, 'UTF-8'); ?></span> WIB</p>
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
							<th>Catatan</th>
							<th>Potongan</th>
						</tr>
					</thead>
					<tbody id="historyTableBody">
						<?php if (empty($recent_logs)): ?>
							<tr>
								<td colspan="6">Belum ada riwayat absensi.</td>
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
									<td><?php echo htmlspecialchars(isset($log['catatan']) ? (string) $log['catatan'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($log['potongan']) ? (string) $log['potongan'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
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
							<?php foreach ($recent_loans as $loan_index => $loan): ?>
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
								<tr class="loan-history-row" data-loan-index="<?php echo (int) $loan_index; ?>" tabindex="0" role="button" aria-label="Lihat rincian pinjaman">
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
								<?php if (!empty($attendance_shift_detail_lines)): ?>
									<ul class="shift-detail-list">
										<?php foreach ($attendance_shift_detail_lines as $shift_detail_line): ?>
											<li><?php echo htmlspecialchars((string) $shift_detail_line, ENT_QUOTES, 'UTF-8'); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</div>
							<div class="info-item">
								<p class="info-title">Aturan Waktu</p>
								<p class="info-value"><?php echo htmlspecialchars($attendance_rule_text, ENT_QUOTES, 'UTF-8'); ?></p>
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

	<div id="loanHistoryDetailModal" class="request-modal" aria-hidden="true">
		<div class="modal-overlay" data-loan-history-close></div>
		<section class="request-panel loan-history-detail-panel" role="dialog" aria-modal="true" aria-labelledby="loanHistoryDetailTitle">
			<div class="request-head">
				<h2 id="loanHistoryDetailTitle" class="request-title">Detail Riwayat Pinjaman</h2>
				<button type="button" class="modal-close" id="closeLoanHistoryDetailModal" aria-label="Tutup popup detail pinjaman">&times;</button>
			</div>
			<div class="request-body loan-history-detail-body">
				<div class="loan-history-detail-section">
					<h3 class="loan-history-detail-heading">Rincian Pinjaman</h3>
					<div class="loan-history-detail-grid">
						<div class="loan-history-detail-item">
							<span>Nominal Pinjaman</span>
							<strong id="loanHistoryDetailPrincipal">Rp 0</strong>
						</div>
						<div class="loan-history-detail-item">
							<span>Tenor</span>
							<strong id="loanHistoryDetailTenor">-</strong>
						</div>
						<div class="loan-history-detail-item">
							<span>Bunga per bulan</span>
							<strong id="loanHistoryDetailRate">0,00%</strong>
						</div>
						<div class="loan-history-detail-item">
							<span>Bunga per Bulan (Rupiah)</span>
							<strong id="loanHistoryDetailMonthlyInterest">Rp 0</strong>
						</div>
						<div class="loan-history-detail-item">
							<span>Total Bunga</span>
							<strong id="loanHistoryDetailTotalInterest">Rp 0</strong>
						</div>
						<div class="loan-history-detail-item">
							<span>Total Bayar</span>
							<strong id="loanHistoryDetailTotalPayment">Rp 0</strong>
						</div>
					</div>
				</div>
				<div class="loan-history-detail-section">
					<h3 class="loan-history-detail-heading">Rincian Pembayaran</h3>
					<div class="table-wrap loan-history-detail-table-wrap">
						<table class="loan-history-detail-table">
							<thead>
								<tr>
									<th>Tenor</th>
									<th>Tagihan</th>
									<th>Jatuh Tempo</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody id="loanHistoryDetailInstallments">
								<tr>
									<td colspan="4">Belum ada rincian pembayaran.</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</section>
	</div>

	<div id="swapDayOffModal" class="request-modal" aria-hidden="true">
		<div class="modal-overlay" data-swap-close></div>
		<section class="request-panel" role="dialog" aria-modal="true" aria-labelledby="swapDayOffTitle">
			<div class="request-head">
				<h2 id="swapDayOffTitle" class="request-title">Form Pengajuan Tukar Hari Libur</h2>
				<button type="button" class="modal-close" id="closeSwapDayOffModal" aria-label="Tutup popup tukar hari libur">&times;</button>
			</div>
			<div class="request-body">
				<form id="swapDayOffRequestForm" class="request-form" method="post" action="<?php echo site_url('home/submit_day_off_swap_request'); ?>">
					<p class="request-kind">Jenis pengajuan: <strong>Tukar Hari Libur (1x)</strong></p>
					<div class="request-grid">
						<div class="request-field">
							<label for="swapWorkdayDate" class="request-label">Tanggal Libur Asli (jadi masuk)</label>
							<input id="swapWorkdayDate" class="request-input" type="date" name="swap_workday_date" required>
						</div>
						<div class="request-field">
							<label for="swapOffdayDate" class="request-label">Tanggal Libur Pengganti (jadi libur)</label>
							<input id="swapOffdayDate" class="request-input" type="date" name="swap_offday_date" required>
						</div>
					</div>
					<div class="request-field">
						<label for="swapNoteInput" class="request-label">Alasan/Catatan (Wajib)</label>
						<textarea id="swapNoteInput" class="request-textarea" name="swap_note" maxlength="200" placeholder="Contoh: acara keluarga" required></textarea>
					</div>
					<button type="submit" class="request-submit">Kirim Pengajuan Tukar Libur</button>
				</form>
			</div>
		</section>
	</div>

	<div id="toastMessage" class="toast" role="status" aria-live="polite"></div>

	<script>
		window.__USER_DASHBOARD_RECENT_LOANS = <?php echo json_encode($recent_loans, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
		window.__USER_DASHBOARD_CONFIG = <?php echo $user_dashboard_config_json; ?>;
		(function (cfg) {
			if (!cfg || typeof cfg !== 'object') {
				window.__USER_DASHBOARD_CONFIG = {};
				return;
			}
			var points = Array.isArray(cfg.officePoints) ? cfg.officePoints.slice() : [];
			var fallbackPoints = Array.isArray(cfg.officeFallbackPoints) ? cfg.officeFallbackPoints : [];
			var branch = String(cfg.attendanceBranch || '').toLowerCase();
			var crossBranchEnabled = Number(cfg.crossBranchEnabled) === 1 ? 1 : 0;
			var shouldIncludeFallback = crossBranchEnabled === 1;
			var defaultFallbackPoints = [
				{ label: 'Kantor 1', lat: -6.217076, lng: 106.132128 },
				{ label: 'Kantor 2', lat: -6.270039, lng: 106.120796 }
			];
			var normalized = [];
			var seen = {};
			var pushUnique = function (sourceList) {
				for (var i = 0; i < sourceList.length; i += 1) {
					var row = sourceList[i] && typeof sourceList[i] === 'object' ? sourceList[i] : {};
					var lat = Number(row.lat);
					var lng = Number(row.lng);
					if (!isFinite(lat) || !isFinite(lng)) {
						continue;
					}
					var key = lat.toFixed(6) + ',' + lng.toFixed(6);
					if (seen[key]) {
						continue;
					}
					seen[key] = true;
					normalized.push({
						label: String(row.label || 'Kantor'),
						lat: lat,
						lng: lng
					});
				}
			};
			pushUnique(points);
			if (shouldIncludeFallback) {
				pushUnique(fallbackPoints);
				if (normalized.length < 2) {
					pushUnique(defaultFallbackPoints);
				}
			}
			if (!normalized.length) {
				var primaryLat = Number(cfg.officeLat);
				var primaryLng = Number(cfg.officeLng);
				if (isFinite(primaryLat) && isFinite(primaryLng)) {
					normalized.push({
						label: 'Kantor',
						lat: primaryLat,
						lng: primaryLng
					});
				}
			}
			if (branch === 'cadasari' && normalized.length > 1 && shouldIncludeFallback) {
				normalized.sort(function (a, b) {
					var aIsKantor2 = String(a.label || '').toLowerCase().indexOf('kantor 2') !== -1;
					var bIsKantor2 = String(b.label || '').toLowerCase().indexOf('kantor 2') !== -1;
					if (aIsKantor2 === bIsKantor2) {
						return 0;
					}
					return aIsKantor2 ? -1 : 1;
				});
			}
			cfg.officePoints = normalized;
			window.__USER_DASHBOARD_CONFIG = cfg;
		})(window.__USER_DASHBOARD_CONFIG || {});
	</script>
	<script defer src="<?php echo htmlspecialchars(base_url($user_dashboard_js_file), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo rawurlencode($user_dashboard_js_version); ?>"></script>
	<script>
	(function () {
		var tbody = document.getElementById('historyTableBody');
		if (!tbody) {
			return;
		}

		var rowCache = Object.create(null);
		var normalizeTimer = null;
		var isNormalizing = false;
		var observer = null;

		function text(value) {
			return String(value || '').replace(/\s+/g, ' ').trim();
		}

		function rowKeyFromCells(cells) {
			if (!cells || cells.length < 1) {
				return '';
			}
			return text(cells[0].textContent);
		}

		function rowKeyFromPayload(row) {
			var payload = row && typeof row === 'object' ? row : {};
			return text(payload.tanggal);
		}

		function ensureRowCells(row, count) {
			while (row.cells.length < count) {
				row.appendChild(document.createElement('td'));
			}
		}

		function isEmpty(v) {
			var t = text(v);
			return t === '' || t === '-';
		}

		function cacheValue(value) {
			return isEmpty(value) ? '' : text(value);
		}

		function scheduleNormalize(delayMs) {
			if (normalizeTimer !== null) {
				return;
			}
			var wait = typeof delayMs === 'number' && delayMs >= 0 ? delayMs : 40;
			normalizeTimer = window.setTimeout(function () {
				normalizeTimer = null;
				normalizeHistoryRowsInDom();
			}, wait);
		}

		function normalizeHistoryRowsInDom() {
			if (isNormalizing) {
				return;
			}
			isNormalizing = true;
			if (observer) {
				observer.disconnect();
			}

			var rows = tbody.querySelectorAll('tr');
			try {
				for (var i = 0; i < rows.length; i += 1) {
					var row = rows[i];
					if (row.cells.length === 1) {
						var colspanRaw = row.cells[0].getAttribute('colspan');
						var colspanVal = parseInt(colspanRaw || '1', 10);
						if (isFinite(colspanVal) && colspanVal > 1) {
							continue;
						}
					}
					ensureRowCells(row, 6);
					var cells = row.cells;
					var key = rowKeyFromCells(cells);
					if (key === '') {
						continue;
					}

					var cache = rowCache[key] || { catatan: '', potongan: '' };
					var catatanNow = text(cells[4].textContent);
					var potonganNow = text(cells[5].textContent);
					var catatanFixed = isEmpty(catatanNow) ? (cache.catatan !== '' ? cache.catatan : '-') : catatanNow;
					var potonganFixed = isEmpty(potonganNow) ? (cache.potongan !== '' ? cache.potongan : '-') : potonganNow;

					if (catatanNow !== catatanFixed) {
						cells[4].textContent = catatanFixed;
					}
					if (potonganNow !== potonganFixed) {
						cells[5].textContent = potonganFixed;
					}

					rowCache[key] = {
						catatan: cacheValue(catatanFixed) !== '' ? cacheValue(catatanFixed) : cache.catatan,
						potongan: cacheValue(potonganFixed) !== '' ? cacheValue(potonganFixed) : cache.potongan
					};
				}
			} finally {
				isNormalizing = false;
				if (observer) {
					observer.observe(tbody, { childList: true });
				}
			}
		}

		function normalizePayloadRows(rows) {
			if (!Array.isArray(rows)) {
				return rows;
			}
			for (var i = 0; i < rows.length; i += 1) {
				var payloadRow = rows[i] && typeof rows[i] === 'object' ? rows[i] : {};
				var key = rowKeyFromPayload(payloadRow);
				if (key === '') {
					continue;
				}
				var cache = rowCache[key] || { catatan: '', potongan: '' };
				var catatanRaw = text(payloadRow.catatan);
				var potonganRaw = text(payloadRow.potongan);
				var catatanFixed = isEmpty(catatanRaw) ? (cache.catatan !== '' ? cache.catatan : '-') : catatanRaw;
				var potonganFixed = isEmpty(potonganRaw) ? (cache.potongan !== '' ? cache.potongan : '-') : potonganRaw;

				payloadRow.catatan = catatanFixed;
				payloadRow.potongan = potonganFixed;
				rows[i] = payloadRow;

				rowCache[key] = {
					catatan: cacheValue(catatanFixed) !== '' ? cacheValue(catatanFixed) : cache.catatan,
					potongan: cacheValue(potonganFixed) !== '' ? cacheValue(potonganFixed) : cache.potongan
				};
			}
			scheduleNormalize(20);
			return rows;
		}

		function isLiveDataRequest(url) {
			var value = String(url || '').toLowerCase();
			if (value === '') {
				return false;
			}
			return value.indexOf('/home/user_dashboard_live_data') !== -1
				|| value.indexOf('/index.php/home/user_dashboard_live_data') !== -1
				|| value.indexOf('home/user_dashboard_live_data') === 0
				|| value.indexOf('index.php/home/user_dashboard_live_data') === 0
				|| value.indexOf('user_dashboard_live_data') !== -1;
		}

		function patchLiveDataFetch() {
			if (window.__USER_DASHBOARD_FETCH_PATCHED === true) {
				return;
			}
			if (typeof window.fetch !== 'function' || typeof window.Response !== 'function') {
				return;
			}
			var nativeFetch = window.fetch.bind(window);
			window.__USER_DASHBOARD_FETCH_PATCHED = true;
			window.fetch = function (input, init) {
				var requestUrl = '';
				if (typeof input === 'string') {
					requestUrl = input;
				}
				else if (input && typeof input.url === 'string') {
					requestUrl = input.url;
				}

				var result = nativeFetch(input, init);
				if (!isLiveDataRequest(requestUrl)) {
					return result;
				}

				return result.then(function (response) {
					if (!response || typeof response.clone !== 'function') {
						return response;
					}
					var contentType = '';
					if (response.headers && typeof response.headers.get === 'function') {
						contentType = String(response.headers.get('content-type') || '').toLowerCase();
					}
					if (contentType.indexOf('application/json') === -1) {
						return response;
					}

					return response.clone().json().then(function (payload) {
						if (!payload || payload.success !== true || !Array.isArray(payload.recent_logs)) {
							return response;
						}
						payload.recent_logs = normalizePayloadRows(payload.recent_logs);
						var headers = response.headers ? new Headers(response.headers) : new Headers();
						headers.set('Content-Type', 'application/json; charset=utf-8');
						return new Response(JSON.stringify(payload), {
							status: response.status,
							statusText: response.statusText,
							headers: headers
						});
					}).catch(function () {
						return response;
					});
				});
			};
		}

		observer = new MutationObserver(function () {
			scheduleNormalize(30);
		});
	observer.observe(tbody, { childList: true });
	scheduleNormalize(0);
	patchLiveDataFetch();
	}());
	</script>
	<script>
	(function () {
		var tbody = document.getElementById('loanHistoryTableBody');
		var modal = document.getElementById('loanHistoryDetailModal');
		if (!tbody || !modal) {
			return;
		}

		var closeButton = document.getElementById('closeLoanHistoryDetailModal');
		var overlayClose = modal.querySelector('[data-loan-history-close]');
		var principalEl = document.getElementById('loanHistoryDetailPrincipal');
		var tenorEl = document.getElementById('loanHistoryDetailTenor');
		var rateEl = document.getElementById('loanHistoryDetailRate');
		var monthlyInterestEl = document.getElementById('loanHistoryDetailMonthlyInterest');
		var totalInterestEl = document.getElementById('loanHistoryDetailTotalInterest');
		var totalPaymentEl = document.getElementById('loanHistoryDetailTotalPayment');
		var installmentsBody = document.getElementById('loanHistoryDetailInstallments');

		var bindTimer = null;
		var observer = null;
		var liveLoans = normalizeLoanRows(window.__USER_DASHBOARD_RECENT_LOANS);
		window.__USER_DASHBOARD_RECENT_LOANS = liveLoans;

		function text(value) {
			return String(value || '').replace(/\s+/g, ' ').trim();
		}

		function parseIntSafe(value) {
			var parsed = parseInt(String(value || '0'), 10);
			return isFinite(parsed) ? parsed : 0;
		}

		function parseMoneyInt(value) {
			if (typeof value === 'number' && isFinite(value)) {
				return Math.max(0, Math.round(value));
			}
			var digits = String(value || '').replace(/\D+/g, '');
			var parsed = parseInt(digits, 10);
			if (!isFinite(parsed) || parsed < 0) {
				return 0;
			}
			return parsed;
		}

		function parseFloatSafe(value, fallbackValue) {
			var parsed = parseFloat(String(value || '').replace(',', '.'));
			if (!isFinite(parsed)) {
				return typeof fallbackValue === 'number' ? fallbackValue : 0;
			}
			return parsed;
		}

		function formatRupiah(value) {
			var number = parseMoneyInt(value);
			return 'Rp ' + number.toLocaleString('id-ID');
		}

		function formatRate(value) {
			var number = parseFloatSafe(value, 0);
			if (number < 0) {
				number = 0;
			}
			return number.toFixed(2).replace('.', ',') + '%';
		}

		function parseDateAny(rawValue) {
			var value = text(rawValue);
			if (value === '') {
				return null;
			}
			var isoMatch = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
			if (isoMatch) {
				var isoYear = parseIntSafe(isoMatch[1]);
				var isoMonth = parseIntSafe(isoMatch[2]);
				var isoDay = parseIntSafe(isoMatch[3]);
				if (isoYear > 0 && isoMonth >= 1 && isoMonth <= 12 && isoDay >= 1 && isoDay <= 31) {
					return new Date(isoYear, isoMonth - 1, isoDay);
				}
			}
			var dmyMatch = value.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
			if (dmyMatch) {
				var day = parseIntSafe(dmyMatch[1]);
				var month = parseIntSafe(dmyMatch[2]);
				var year = parseIntSafe(dmyMatch[3]);
				if (year > 0 && month >= 1 && month <= 12 && day >= 1 && day <= 31) {
					return new Date(year, month - 1, day);
				}
			}
			return null;
		}

		function formatDateSlash(dateValue) {
			if (!(dateValue instanceof Date) || isNaN(dateValue.getTime())) {
				return '-';
			}
			var day = String(dateValue.getDate()).padStart(2, '0');
			var month = String(dateValue.getMonth() + 1).padStart(2, '0');
			var year = String(dateValue.getFullYear());
			return day + '/' + month + '/' + year;
		}

		function toIsoDate(dateValue) {
			if (!(dateValue instanceof Date) || isNaN(dateValue.getTime())) {
				return '';
			}
			var day = String(dateValue.getDate()).padStart(2, '0');
			var month = String(dateValue.getMonth() + 1).padStart(2, '0');
			var year = String(dateValue.getFullYear());
			return year + '-' + month + '-' + day;
		}

		function addMonthsSafe(dateValue, monthOffset) {
			if (!(dateValue instanceof Date) || isNaN(dateValue.getTime())) {
				return null;
			}
			var source = new Date(dateValue.getTime());
			var day = source.getDate();
			source.setDate(1);
			source.setMonth(source.getMonth() + monthOffset);
			var maxDay = new Date(source.getFullYear(), source.getMonth() + 1, 0).getDate();
			source.setDate(Math.min(day, maxDay));
			return source;
		}

		function resolveTenorMonths(payload) {
			var tenorMonths = parseIntSafe(payload.tenor_months);
			if (tenorMonths > 0) {
				return tenorMonths;
			}
			var tenorMatch = String(payload.tenor || '').match(/(\d+)/);
			if (tenorMatch && tenorMatch[1]) {
				tenorMonths = parseIntSafe(tenorMatch[1]);
			}
			return tenorMonths > 0 ? tenorMonths : 0;
		}

		function resolveRequestDateIso(payload) {
			var directDate = parseDateAny(payload.request_date);
			if (directDate instanceof Date && !isNaN(directDate.getTime())) {
				return toIsoDate(directDate);
			}
			var labelDate = parseDateAny(payload.request_date_label || payload.tanggal);
			if (labelDate instanceof Date && !isNaN(labelDate.getTime())) {
				return toIsoDate(labelDate);
			}
			return '';
		}

		function buildInstallments(payload, principal, tenorMonths, totalPayment, monthlyInstallment, requestDateIso) {
			var installmentsRaw = Array.isArray(payload.installments) ? payload.installments : [];
			var normalized = [];

			for (var i = 0; i < installmentsRaw.length; i += 1) {
				var row = installmentsRaw[i] && typeof installmentsRaw[i] === 'object' ? installmentsRaw[i] : {};
				var monthValue = parseIntSafe(row.month);
				if (monthValue <= 0) {
					monthValue = i + 1;
				}
				var amountValue = parseMoneyInt(row.amount);
				if (amountValue <= 0 && monthlyInstallment > 0) {
					amountValue = monthlyInstallment;
				}
				var dueDate = parseDateAny(row.due_date || row.due_date_label);
				if (!(dueDate instanceof Date) || isNaN(dueDate.getTime())) {
					var requestDate = parseDateAny(requestDateIso);
					if (requestDate instanceof Date && !isNaN(requestDate.getTime())) {
						dueDate = addMonthsSafe(requestDate, monthValue);
					}
				}
				normalized.push({
					month: monthValue,
					amount: Math.max(0, amountValue),
					due_date: dueDate instanceof Date && !isNaN(dueDate.getTime()) ? toIsoDate(dueDate) : '',
					due_date_label: dueDate instanceof Date && !isNaN(dueDate.getTime()) ? formatDateSlash(dueDate) : '-',
					status: text(row.status || row.payment_status || row.paid_status || '')
				});
			}

			if (!normalized.length && tenorMonths > 0) {
				var baseInstallment = totalPayment > 0 ? Math.floor(totalPayment / tenorMonths) : (monthlyInstallment > 0 ? monthlyInstallment : 0);
				var remainder = totalPayment > 0 ? (totalPayment - (baseInstallment * tenorMonths)) : 0;
				var requestDateAuto = parseDateAny(requestDateIso);
				for (var monthIndex = 1; monthIndex <= tenorMonths; monthIndex += 1) {
					var billValue = baseInstallment;
					if (monthIndex === tenorMonths && remainder > 0) {
						billValue += remainder;
					}
					if (billValue <= 0 && monthlyInstallment > 0) {
						billValue = monthlyInstallment;
					}
					var dueDateAuto = requestDateAuto instanceof Date && !isNaN(requestDateAuto.getTime())
						? addMonthsSafe(requestDateAuto, monthIndex)
						: null;
					normalized.push({
						month: monthIndex,
						amount: Math.max(0, billValue),
						due_date: dueDateAuto instanceof Date && !isNaN(dueDateAuto.getTime()) ? toIsoDate(dueDateAuto) : '',
						due_date_label: dueDateAuto instanceof Date && !isNaN(dueDateAuto.getTime()) ? formatDateSlash(dueDateAuto) : '-',
						status: ''
					});
				}
			}

			return normalized;
		}

		function normalizeLoanRow(row) {
			var payload = row && typeof row === 'object' ? row : {};
			var tenorMonths = resolveTenorMonths(payload);
			var principal = parseMoneyInt(payload.nominal_value || payload.amount || payload.principal);
			if (principal <= 0) {
				principal = parseMoneyInt(payload.nominal);
			}
			var isFirstLoan = payload.is_first_loan === true || payload.is_first_loan === 1 || String(payload.is_first_loan).toLowerCase() === 'true';
			var monthlyRatePercent = parseFloatSafe(payload.monthly_rate_percent, isFirstLoan ? 0 : 2.95);
			if (monthlyRatePercent < 0) {
				monthlyRatePercent = 0;
			}
			var monthlyInterestAmount = parseMoneyInt(payload.monthly_interest_amount);
			if (monthlyInterestAmount <= 0 && principal > 0 && tenorMonths > 0) {
				monthlyInterestAmount = Math.round(principal * monthlyRatePercent / 100);
			}
			var totalInterestAmount = parseMoneyInt(payload.total_interest_amount || payload.interest_amount);
			if (totalInterestAmount <= 0 && monthlyInterestAmount > 0 && tenorMonths > 0) {
				totalInterestAmount = monthlyInterestAmount * tenorMonths;
			}
			var totalPayment = parseMoneyInt(payload.total_payment);
			if (totalPayment <= 0) {
				totalPayment = principal + totalInterestAmount;
			}
			if (totalPayment <= 0) {
				totalPayment = principal;
			}
			var monthlyInstallment = parseMoneyInt(payload.monthly_installment || payload.monthly_installment_estimate || payload.cicilan_bulanan);
			if (monthlyInstallment <= 0 && tenorMonths > 0 && totalPayment > 0) {
				monthlyInstallment = Math.round(totalPayment / tenorMonths);
			}
			var requestDateIso = resolveRequestDateIso(payload);
			var installments = buildInstallments(payload, principal, tenorMonths, totalPayment, monthlyInstallment, requestDateIso);
			if (tenorMonths <= 0 && installments.length > 0) {
				tenorMonths = installments.length;
			}
			if (monthlyInstallment <= 0 && installments.length > 0) {
				monthlyInstallment = parseMoneyInt(installments[0].amount);
			}

			return {
				tanggal: text(payload.tanggal) !== '' ? text(payload.tanggal) : '-',
				nominal: text(payload.nominal) !== '' ? text(payload.nominal) : formatRupiah(principal),
				tenor: text(payload.tenor) !== '' ? text(payload.tenor) : (tenorMonths > 0 ? String(tenorMonths) + ' bulan' : '-'),
				cicilan_bulanan: text(payload.cicilan_bulanan) !== '' ? text(payload.cicilan_bulanan) : formatRupiah(monthlyInstallment),
				status: text(payload.status) !== '' ? text(payload.status) : 'Menunggu',
				request_date: requestDateIso,
				nominal_value: principal,
				tenor_months: tenorMonths,
				monthly_rate_percent: monthlyRatePercent,
				monthly_interest_amount: monthlyInterestAmount,
				total_interest_amount: totalInterestAmount,
				total_payment: totalPayment,
				monthly_installment: monthlyInstallment,
				installments: installments
			};
		}

		function isLunasStatus(value) {
			var statusText = text(value).toLowerCase();
			if (statusText === '') {
				return false;
			}
			if (statusText.indexOf('belum') !== -1 && statusText.indexOf('lunas') !== -1) {
				return false;
			}
			return statusText.indexOf('lunas') !== -1;
		}

		function normalizeLoanRows(rows) {
			if (!Array.isArray(rows)) {
				return [];
			}
			var normalized = [];
			for (var i = 0; i < rows.length; i += 1) {
				normalized.push(normalizeLoanRow(rows[i]));
			}
			return normalized;
		}

		function buildFallbackLoanFromRow(row) {
			var cells = row && row.cells ? row.cells : [];
			return normalizeLoanRow({
				tanggal: cells[0] ? cells[0].textContent : '-',
				nominal: cells[1] ? cells[1].textContent : 'Rp 0',
				tenor: cells[2] ? cells[2].textContent : '-',
				cicilan_bulanan: cells[3] ? cells[3].textContent : 'Rp 0',
				status: cells[4] ? cells[4].textContent : 'Menunggu'
			});
		}

		function renderInstallments(loan) {
			if (!installmentsBody) {
				return;
			}
			installmentsBody.innerHTML = '';
			var installments = Array.isArray(loan.installments) ? loan.installments : [];
			if (!installments.length) {
				var emptyRow = document.createElement('tr');
				var emptyCell = document.createElement('td');
				emptyCell.colSpan = 4;
				emptyCell.textContent = 'Belum ada rincian pembayaran.';
				emptyRow.appendChild(emptyCell);
				installmentsBody.appendChild(emptyRow);
				return;
			}

			for (var i = 0; i < installments.length; i += 1) {
				var installment = installments[i] && typeof installments[i] === 'object' ? installments[i] : {};
				var row = document.createElement('tr');
				var monthValue = parseIntSafe(installment.month);
				if (monthValue <= 0) {
					monthValue = i + 1;
				}
				var tenorValue = parseIntSafe(loan.tenor_months);
				var tenorLabel = tenorValue > 0 ? (monthValue + '/' + tenorValue) : String(monthValue);
				var amountValue = parseMoneyInt(installment.amount);
				var dueDateLabel = text(installment.due_date_label);
				if (dueDateLabel === '' || dueDateLabel === '-') {
					dueDateLabel = text(installment.due_date);
					if (dueDateLabel !== '') {
						var parsedDue = parseDateAny(dueDateLabel);
						dueDateLabel = parsedDue instanceof Date && !isNaN(parsedDue.getTime()) ? formatDateSlash(parsedDue) : '-';
					} else {
						dueDateLabel = '-';
					}
				}

				var tenorCell = document.createElement('td');
				tenorCell.textContent = tenorLabel;
				row.appendChild(tenorCell);

				var amountCell = document.createElement('td');
				amountCell.textContent = formatRupiah(amountValue);
				row.appendChild(amountCell);

				var dueDateCell = document.createElement('td');
				dueDateCell.textContent = dueDateLabel;
				row.appendChild(dueDateCell);

				var statusCell = document.createElement('td');
				var statusPill = document.createElement('span');
				var installmentStatusText = text(installment.status);
				var paymentDone = isLunasStatus(installmentStatusText) || isLunasStatus(loan.status);
				statusPill.className = 'loan-payment-status ' + (paymentDone ? 'lunas' : 'belum-lunas');
				statusPill.textContent = paymentDone ? 'Lunas' : 'Belum Lunas';
				statusCell.appendChild(statusPill);
				row.appendChild(statusCell);

				installmentsBody.appendChild(row);
			}
		}

		function renderLoanDetail(loan) {
			if (principalEl) {
				principalEl.textContent = formatRupiah(loan.nominal_value);
			}
			if (tenorEl) {
				tenorEl.textContent = loan.tenor_months > 0 ? String(loan.tenor_months) + ' bulan' : '-';
			}
			if (rateEl) {
				rateEl.textContent = formatRate(loan.monthly_rate_percent);
			}
			if (monthlyInterestEl) {
				monthlyInterestEl.textContent = formatRupiah(loan.monthly_interest_amount);
			}
			if (totalInterestEl) {
				totalInterestEl.textContent = formatRupiah(loan.total_interest_amount);
			}
			if (totalPaymentEl) {
				totalPaymentEl.textContent = formatRupiah(loan.total_payment);
			}
			renderInstallments(loan);
		}

		function isOpen(elementId) {
			var el = document.getElementById(elementId);
			return !!(el && el.classList && el.classList.contains('show'));
		}

		function updateBodyOverflow() {
			var hasModalOpen = modal.classList.contains('show')
				|| isOpen('attendanceModal')
				|| isOpen('requestModal')
				|| isOpen('loanModal')
				|| isOpen('swapDayOffModal');
			document.body.style.overflow = hasModalOpen ? 'hidden' : '';
		}

		function openDetail(loan) {
			renderLoanDetail(loan);
			modal.classList.add('show');
			modal.setAttribute('aria-hidden', 'false');
			updateBodyOverflow();
		}

		function closeDetail() {
			modal.classList.remove('show');
			modal.setAttribute('aria-hidden', 'true');
			updateBodyOverflow();
		}

		function extractLoanFromRow(row) {
			var index = parseIntSafe(row.getAttribute('data-loan-index'));
			if (index >= 0 && index < liveLoans.length) {
				return liveLoans[index];
			}
			return buildFallbackLoanFromRow(row);
		}

		function activateRow(row) {
			if (!row) {
				return;
			}
			var loan = extractLoanFromRow(row);
			openDetail(loan);
		}

		function scheduleBindRows(delayMs) {
			if (bindTimer !== null) {
				return;
			}
			var wait = typeof delayMs === 'number' && delayMs >= 0 ? delayMs : 30;
			bindTimer = window.setTimeout(function () {
				bindTimer = null;
				applyRowsAccessibility();
			}, wait);
		}

		function applyRowsAccessibility() {
			var rows = tbody.querySelectorAll('tr');
			var loanIndex = 0;
			for (var i = 0; i < rows.length; i += 1) {
				var row = rows[i];
				if (row.cells.length === 1) {
					var colspan = parseIntSafe(row.cells[0].getAttribute('colspan') || '1');
					if (colspan > 1) {
						row.classList.remove('loan-history-row');
						row.removeAttribute('data-loan-index');
						row.removeAttribute('tabindex');
						row.removeAttribute('role');
						row.removeAttribute('aria-label');
						continue;
					}
				}
				row.classList.add('loan-history-row');
				row.setAttribute('data-loan-index', String(loanIndex));
				row.setAttribute('tabindex', '0');
				row.setAttribute('role', 'button');
				row.setAttribute('aria-label', 'Lihat rincian pinjaman');
				loanIndex += 1;
			}
		}

		function isLiveDataRequest(url) {
			var value = String(url || '').toLowerCase();
			if (value === '') {
				return false;
			}
			return value.indexOf('/home/user_dashboard_live_data') !== -1
				|| value.indexOf('/index.php/home/user_dashboard_live_data') !== -1
				|| value.indexOf('home/user_dashboard_live_data') === 0
				|| value.indexOf('index.php/home/user_dashboard_live_data') === 0
				|| value.indexOf('user_dashboard_live_data') !== -1;
		}

		function patchLiveDataFetch() {
			if (window.__USER_DASHBOARD_LOAN_FETCH_PATCHED === true) {
				return;
			}
			if (typeof window.fetch !== 'function') {
				return;
			}

			var nativeFetch = window.fetch.bind(window);
			window.__USER_DASHBOARD_LOAN_FETCH_PATCHED = true;
			window.fetch = function (input, init) {
				var requestUrl = '';
				if (typeof input === 'string') {
					requestUrl = input;
				}
				else if (input && typeof input.url === 'string') {
					requestUrl = input.url;
				}

				var result = nativeFetch(input, init);
				if (!isLiveDataRequest(requestUrl)) {
					return result;
				}

				return result.then(function (response) {
					if (!response || typeof response.clone !== 'function') {
						return response;
					}
					var contentType = '';
					if (response.headers && typeof response.headers.get === 'function') {
						contentType = String(response.headers.get('content-type') || '').toLowerCase();
					}
					if (contentType.indexOf('application/json') === -1) {
						return response;
					}

					response.clone().json().then(function (payload) {
						if (!payload || payload.success !== true || !Array.isArray(payload.recent_loans)) {
							return;
						}
						liveLoans = normalizeLoanRows(payload.recent_loans);
						window.__USER_DASHBOARD_RECENT_LOANS = liveLoans;
						scheduleBindRows(20);
					}).catch(function () {});
					return response;
				});
			};
		}

		tbody.addEventListener('click', function (event) {
			var row = event.target && event.target.closest ? event.target.closest('tr.loan-history-row[data-loan-index]') : null;
			if (!row) {
				return;
			}
			activateRow(row);
		});

		tbody.addEventListener('keydown', function (event) {
			var key = String(event.key || '').toLowerCase();
			if (key !== 'enter' && key !== ' ') {
				return;
			}
			var row = event.target && event.target.closest ? event.target.closest('tr.loan-history-row[data-loan-index]') : null;
			if (!row) {
				return;
			}
			event.preventDefault();
			activateRow(row);
		});

		if (closeButton) {
			closeButton.addEventListener('click', closeDetail);
		}
		if (overlayClose) {
			overlayClose.addEventListener('click', closeDetail);
		}

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && modal.classList.contains('show')) {
				closeDetail();
			}
		});

		observer = new MutationObserver(function () {
			scheduleBindRows(25);
		});
		observer.observe(tbody, { childList: true });

		applyRowsAccessibility();
		patchLiveDataFetch();
	}());
	</script>
	<script>
	(function () {
		var modal = document.getElementById('swapDayOffModal');
		if (!modal) {
			return;
		}
		var toast = document.getElementById('toastMessage');
		var openButtons = document.querySelectorAll('[data-swap-open]');
		var closeButton = document.getElementById('closeSwapDayOffModal');
		var overlayClose = modal.querySelector('[data-swap-close]');
		var firstField = document.getElementById('swapWorkdayDate');
		var form = document.getElementById('swapDayOffRequestForm');
		var submitButton = form ? form.querySelector('button[type="submit"]') : null;
		var submitLabel = submitButton ? String(submitButton.textContent || 'Kirim Pengajuan Tukar Libur') : 'Kirim Pengajuan Tukar Libur';
		var toastTimer = null;
		var initialSuccess = <?php echo json_encode($swap_request_notice_success, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
		var initialError = <?php echo json_encode($swap_request_notice_error, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

		function showToast(message) {
			if (!toast) {
				return;
			}
			toast.textContent = String(message || '');
			toast.classList.add('show');
			if (toastTimer !== null) {
				window.clearTimeout(toastTimer);
			}
			toastTimer = window.setTimeout(function () {
				toast.classList.remove('show');
			}, 7000);
		}

		function isOpen(elementId) {
			var el = document.getElementById(elementId);
			return !!(el && el.classList && el.classList.contains('show'));
		}

		function updateBodyOverflow() {
			var hasModalOpen = modal.classList.contains('show')
				|| isOpen('attendanceModal')
				|| isOpen('requestModal')
				|| isOpen('loanModal');
			document.body.style.overflow = hasModalOpen ? 'hidden' : '';
		}

		function openModal() {
			modal.classList.add('show');
			modal.setAttribute('aria-hidden', 'false');
			updateBodyOverflow();
			if (firstField) {
				window.setTimeout(function () {
					firstField.focus();
				}, 30);
			}
		}

		function closeModal() {
			modal.classList.remove('show');
			modal.setAttribute('aria-hidden', 'true');
			updateBodyOverflow();
		}

		for (var i = 0; i < openButtons.length; i += 1) {
			openButtons[i].addEventListener('click', function (event) {
				event.preventDefault();
				openModal();
			});
		}

		if (closeButton) {
			closeButton.addEventListener('click', closeModal);
		}
		if (overlayClose) {
			overlayClose.addEventListener('click', closeModal);
		}
		if (form) {
			form.addEventListener('submit', function (event) {
				if (typeof window.fetch !== 'function' || typeof window.FormData !== 'function') {
					closeModal();
					return;
				}
				event.preventDefault();

				var actionUrl = String(form.getAttribute('action') || '').trim();
				if (actionUrl === '') {
					showToast('Endpoint pengajuan tukar libur tidak ditemukan.');
					return;
				}

				var formData = new FormData(form);
				if (submitButton) {
					submitButton.disabled = true;
					submitButton.textContent = 'Mengirim...';
				}

				fetch(actionUrl, {
					method: 'POST',
					body: formData,
					headers: {
						'X-Requested-With': 'XMLHttpRequest'
					}
				}).then(function (response) {
					return response.json().catch(function () {
						return {
							success: false,
							message: 'Respons server tidak valid.'
						};
					}).then(function (payload) {
						if (!response.ok || !payload || payload.success !== true) {
							throw new Error(payload && payload.message ? payload.message : 'Pengajuan tukar hari libur gagal dikirim.');
						}
						return payload;
					});
				}).then(function (payload) {
					showToast(payload.message || 'Pengajuan tukar hari libur berhasil dikirim.');
					form.reset();
					closeModal();
				}).catch(function (error) {
					showToast(error && error.message ? error.message : 'Pengajuan tukar hari libur gagal dikirim.');
				}).finally(function () {
					if (submitButton) {
						submitButton.disabled = false;
						submitButton.textContent = submitLabel;
					}
				});
			});
		}

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && modal.classList.contains('show')) {
				closeModal();
			}
		});

		if (initialSuccess) {
			showToast(initialSuccess);
		}
		else if (initialError) {
			showToast(initialError);
		}
	}());
	</script>
</body>
</html>
