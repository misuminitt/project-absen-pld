<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$records_source = isset($records) && is_array($records) ? $records : array();
$records = array();
$has_real_time = function ($value) {
	$text = trim((string) $value);
	if ($text === '' || $text === '-' || $text === '--')
	{
		return FALSE;
	}
	if (!preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $text))
	{
		return FALSE;
	}
	if (strlen($text) === 5)
	{
		$text .= ':00';
	}

	return $text !== '00:00:00';
};
for ($record_i = 0; $record_i < count($records_source); $record_i += 1)
{
	$row = isset($records_source[$record_i]) && is_array($records_source[$record_i])
		? $records_source[$record_i]
		: array();
	$check_in_raw = isset($row['check_in_time']) ? (string) $row['check_in_time'] : '';
	$check_out_raw = isset($row['check_out_time']) ? (string) $row['check_out_time'] : '';
	if (!$has_real_time($check_in_raw) && !$has_real_time($check_out_raw))
	{
		continue;
	}
	$records[] = $row;
}
?>
<?php
$notice_success = $this->session->flashdata('attendance_notice_success');
$notice_error = $this->session->flashdata('attendance_notice_error');
$can_edit_attendance_records = isset($can_edit_attendance_records) && $can_edit_attendance_records === TRUE;
$can_delete_attendance_records = isset($can_delete_attendance_records) && $can_delete_attendance_records === TRUE;
$can_partial_delete_attendance_records = isset($can_partial_delete_attendance_records) && $can_partial_delete_attendance_records === TRUE;
$can_edit_attendance_datetime = isset($can_edit_attendance_datetime) && $can_edit_attendance_datetime === TRUE;
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
	<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Data Absensi Karyawan'; ?></title>
	<link rel="icon" type="image/svg+xml" href="/src/assets/sinyal.svg">
	<link rel="shortcut icon" type="image/svg+xml" href="/src/assets/sinyal.svg">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<style>
		* {
			box-sizing: border-box;
			scrollbar-width: none;
			-ms-overflow-style: none;
		}

		*::-webkit-scrollbar {
			width: 0;
			height: 0;
		}

		body {
			margin: 0;
			font-family: 'Outfit', sans-serif;
			background: #eef6ff;
			color: #11263d;
		}

		.page {
			max-width: 1380px;
			margin: 0 auto;
			padding: 1.1rem 1rem 1.4rem;
		}

		.head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.8rem;
			margin-bottom: 0.9rem;
		}

		.title {
			margin: 0;
			font-size: 1.25rem;
			font-weight: 800;
		}

		.actions {
			display: flex;
			align-items: center;
			gap: 0.5rem;
		}

		.btn {
			text-decoration: none;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 0.52rem 0.88rem;
			border-radius: 999px;
			font-size: 0.8rem;
			font-weight: 700;
			border: 0;
			cursor: pointer;
		}

		.btn.primary {
			background: #1f6fd6;
			color: #ffffff;
		}

		.btn.outline {
			background: #ffffff;
			color: #224162;
			border: 1px solid #c8dbee;
		}

		.table-card {
			background: #ffffff;
			border: 1px solid #d8e7f5;
			border-radius: 14px;
			overflow: hidden;
			box-shadow: 0 12px 26px rgba(8, 37, 69, 0.08);
		}

		.mode-tabs {
			padding: 0.85rem 0.85rem 0;
			display: flex;
			align-items: center;
			gap: 0.5rem;
			flex-wrap: wrap;
		}

		.mode-link {
			text-decoration: none;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 0.5rem 0.86rem;
			border-radius: 999px;
			font-size: 0.79rem;
			font-weight: 700;
			color: #23466a;
			background: #ffffff;
			border: 1px solid #c8dbee;
		}

		.mode-link.active {
			color: #ffffff;
			border-color: #1f6fd6;
			background: linear-gradient(145deg, #1d5ea2 0%, #2b82d5 100%);
		}

		.table-wrap {
			overflow-x: auto;
		}

		.table-wrap.is-dragging {
			user-select: none;
		}

		@media (pointer: fine) {
			.table-wrap {
				cursor: grab;
			}

			.table-wrap.is-dragging,
			.table-wrap.is-dragging * {
				cursor: grabbing !important;
			}
		}

		.table-tools {
			padding: 0.75rem 0.85rem 0;
		}

		.search-input {
			width: min(100%, 360px);
			border: 1px solid #c5d8ea;
			border-radius: 10px;
			padding: 0.56rem 0.68rem;
			font-family: inherit;
			font-size: 0.82rem;
			color: #183654;
			background: #ffffff;
		}

		.search-input:focus {
			outline: none;
			border-color: #2b82d5;
			box-shadow: 0 0 0 3px rgba(43, 130, 213, 0.14);
		}

		.search-help {
			margin: 0.42rem 0 0;
			font-size: 0.74rem;
			color: #617991;
			font-weight: 600;
		}

		table {
			width: 100%;
			min-width: 1880px;
			border-collapse: collapse;
		}

		th,
		td {
			padding: 0.62rem 0.56rem;
			border-bottom: 1px solid #e9f1f9;
			text-align: left;
			vertical-align: middle;
		}

		th {
			font-size: 0.67rem;
			font-weight: 700;
			color: #627b97;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			background: #f8fbff;
		}

		td {
			font-size: 0.8rem;
			color: #223850;
		}

		.shift-chip {
			display: inline-flex;
			padding: 0.22rem 0.55rem;
			border-radius: 999px;
			background: #e6f4ff;
			color: #2366a3;
			font-size: 0.68rem;
			font-weight: 700;
		}

		.photo {
			width: 80px;
			height: 56px;
			border-radius: 8px;
			object-fit: cover;
			border: 1px solid #d5e2f0;
			display: block;
		}

		.profile-avatar {
			width: 42px;
			height: 42px;
			border-radius: 999px;
			object-fit: cover;
			border: 1px solid #cfe1f3;
			display: block;
			background: #f2f7fd;
		}

		.address-cell {
			min-width: 220px;
			max-width: 280px;
			white-space: normal;
			line-height: 1.35;
		}

		.muted {
			color: #6a7f95;
		}

		.distance-link {
			color: #1f6fd6;
			font-weight: 700;
			text-decoration: none;
		}

		.distance-link:hover {
			text-decoration: underline;
		}

		.deduction-amount {
			font-weight: 700;
			color: #b33b3b;
		}

		.empty {
			padding: 1rem;
			text-align: center;
			font-size: 0.9rem;
			color: #526a82;
		}

		.table-meta {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.7rem;
			padding: 0.72rem 0.85rem;
			font-size: 0.78rem;
			color: #5b748f;
			border-top: 1px solid #e9f1f9;
			background: #fbfdff;
		}

		.pager {
			display: flex;
			align-items: center;
			gap: 0.45rem;
			padding: 0 0.85rem 0.9rem;
			flex-wrap: wrap;
		}

		.pager-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: 2.7rem;
			height: 2.45rem;
			padding: 0 0.82rem;
			border-radius: 0.75rem;
			border: 1px solid #b9cfe4;
			background: #ffffff;
			color: #1c4670;
			font-size: 0.8rem;
			font-weight: 700;
			cursor: pointer;
		}

		.pager-btn:hover {
			background: #f0f7ff;
		}

		.pager-btn.active {
			background: linear-gradient(180deg, #1f6fbd 0%, #0f5c93 100%);
			border-color: #0f5c93;
			color: #ffffff;
		}

		.pager-btn.wide {
			padding: 0 0.95rem;
			min-width: 6rem;
		}

		.notice {
			margin-bottom: 0.85rem;
			padding: 0.72rem 0.82rem;
			border-radius: 10px;
			font-size: 0.82rem;
			font-weight: 600;
		}

		.notice.success {
			background: #e8f7ee;
			border: 1px solid #b6e4c7;
			color: #1f7348;
		}

		.notice.error {
			background: #ffeded;
			border: 1px solid #f0c1c1;
			color: #a43232;
		}

		.edit-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			border: 0;
			border-radius: 8px;
			padding: 0.38rem 0.58rem;
			font-size: 0.72rem;
			font-weight: 700;
			cursor: pointer;
			color: #ffffff;
			background: linear-gradient(145deg, #1d5ea2 0%, #2b82d5 100%);
		}

		.row-actions {
			display: flex;
			align-items: center;
			flex-wrap: wrap;
			gap: 0.35rem;
		}

		.row-delete-form {
			margin: 0;
		}

		.delete-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			border: 0;
			border-radius: 8px;
			padding: 0.38rem 0.58rem;
			font-size: 0.72rem;
			font-weight: 700;
			cursor: pointer;
			color: #ffffff;
			background: linear-gradient(145deg, #a72f2f 0%, #d34a4a 100%);
		}

		.delete-part-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			border: 0;
			border-radius: 8px;
			padding: 0.38rem 0.58rem;
			font-size: 0.72rem;
			font-weight: 700;
			cursor: pointer;
			color: #ffffff;
			background: linear-gradient(145deg, #8a5a1a 0%, #d5902d 100%);
		}

		.edit-modal {
			position: fixed;
			inset: 0;
			z-index: 120;
			display: none;
			overflow-y: auto;
			padding: 0.9rem;
		}

		.edit-modal.show {
			display: block;
		}

		.modal-overlay {
			position: absolute;
			inset: 0;
			background: rgba(7, 20, 36, 0.6);
		}

		.edit-panel {
			position: relative;
			width: min(100%, 560px);
			max-width: 560px;
			margin: min(8vh, 56px) auto;
			background: #ffffff;
			border-radius: 16px;
			box-shadow: 0 24px 42px rgba(4, 24, 50, 0.28);
			overflow: hidden;
		}

		.edit-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.8rem;
			padding: 0.9rem 1rem;
			background: linear-gradient(120deg, #0d2c55 0%, #1d5ea2 100%);
			color: #ffffff;
		}

		.edit-title {
			margin: 0;
			font-size: 1rem;
			font-weight: 800;
		}

		.edit-close {
			width: 36px;
			height: 36px;
			border-radius: 999px;
			border: 0;
			background: rgba(255, 255, 255, 0.16);
			color: #ffffff;
			font-size: 1.3rem;
			line-height: 1;
			cursor: pointer;
		}

		.edit-body {
			padding: 0.95rem 1rem 1rem;
		}

		.edit-info {
			border: 1px dashed #c4d8ec;
			border-radius: 10px;
			padding: 0.62rem 0.7rem;
			background: #f5faff;
			margin-bottom: 0.65rem;
		}

		.edit-info-title {
			margin: 0;
			font-size: 0.68rem;
			font-weight: 700;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			color: #5f7591;
		}

		.edit-info-value {
			margin: 0.28rem 0 0;
			font-size: 0.9rem;
			font-weight: 700;
			color: #0e3158;
		}

		.edit-field {
			display: grid;
			gap: 0.38rem;
			margin-top: 0.4rem;
		}

		.edit-label {
			margin: 0;
			font-size: 0.72rem;
			font-weight: 700;
			letter-spacing: 0.06em;
			text-transform: uppercase;
			color: #5f7591;
		}

		.edit-input {
			width: 100%;
			border: 1px solid #bfd2e6;
			border-radius: 10px;
			padding: 0.56rem 0.62rem;
			font-family: inherit;
			font-size: 0.84rem;
			color: #1b3552;
			background: #ffffff;
		}

		.edit-input:focus {
			outline: none;
			border-color: #2b82d5;
			box-shadow: 0 0 0 3px rgba(43, 130, 213, 0.14);
		}

		.edit-help {
			margin: 0;
			font-size: 0.74rem;
			color: #617991;
			font-weight: 600;
		}

		.edit-actions {
			margin-top: 0.85rem;
			display: flex;
			gap: 0.5rem;
			flex-wrap: wrap;
		}

		.edit-submit {
			border: 0;
			border-radius: 10px;
			padding: 0.62rem 0.9rem;
			background: linear-gradient(145deg, #1d5ea2 0%, #2b82d5 100%);
			color: #ffffff;
			font-size: 0.82rem;
			font-weight: 700;
			cursor: pointer;
		}

		.edit-clear {
			border: 1px solid #c8dbee;
			border-radius: 10px;
			padding: 0.62rem 0.9rem;
			background: #ffffff;
			color: #23466a;
			font-size: 0.82rem;
			font-weight: 700;
			cursor: pointer;
		}
	
		/* mobile-fix-20260219 */
		@media (max-width: 860px) {
			.page {
				padding: 0.9rem 0.72rem 1.2rem;
			}

			.head {
				flex-direction: column;
				align-items: flex-start;
				gap: 0.62rem;
			}

			.title {
				font-size: 1.05rem;
				line-height: 1.3;
			}

			.actions {
				width: 100%;
				display: grid;
				grid-template-columns: 1fr;
				gap: 0.42rem;
			}

			.btn,
			.mode-link {
				width: 100%;
				justify-content: center;
			}

			.mode-tabs {
				padding: 0.72rem 0.72rem 0;
			}

			.table-tools {
				padding: 0.62rem 0.72rem 0;
			}

			.search-input {
				width: 100%;
				max-width: 100%;
			}

			.search-help {
				margin-top: 0.36rem;
				font-size: 0.7rem;
				line-height: 1.45;
			}

			.table-meta {
				flex-direction: column;
				align-items: flex-start;
				gap: 0.3rem;
				padding: 0.62rem 0.72rem;
			}

			.pager {
				padding: 0 0.72rem 0.72rem;
				gap: 0.35rem;
			}

			.pager-btn {
				flex: 1 1 0;
				min-width: 2.3rem;
				height: 2.2rem;
				padding: 0 0.5rem;
				font-size: 0.75rem;
			}

			.pager-btn.wide {
				min-width: 4.5rem;
			}

			.edit-modal {
				padding: 0.7rem;
			}

			.edit-panel {
				width: min(100%, 96vw);
				margin: max(0.5rem, env(safe-area-inset-top)) auto;
			}

			.edit-body {
				padding: 0.8rem;
			}

			.edit-actions {
				flex-direction: column;
			}

			.edit-submit,
			.edit-clear {
				width: 100%;
			}
		}

		@media (max-width: 520px) {
			.page {
				padding: 0.75rem 0.55rem 1rem;
			}

			.table-card {
				border-radius: 12px;
			}

			th,
			td {
				padding: 0.5rem 0.46rem;
				font-size: 0.74rem;
			}
		}

		/* mobile-fix-20260219-navbar-compact */
		@media (max-width: 860px) {
			.head {
				flex-direction: column;
				align-items: flex-start;
				gap: 0.55rem;
			}

			.actions {
				width: 100%;
				display: flex;
				flex-wrap: wrap;
				gap: 0.4rem;
			}

			.btn {
				width: auto;
				min-width: 0;
				padding: 0.46rem 0.72rem;
				font-size: 0.74rem;
			}
		}

		@media (max-width: 520px) {
			.actions .btn {
				flex: 1 1 calc(50% - 0.4rem);
				justify-content: center;
			}

			.actions .btn.primary {
				flex-basis: 100%;
			}
		}
</style>
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
	<script src="<?php echo htmlspecialchars('/src/assets/js/theme-global-init.js?v=20260225f', ENT_QUOTES, 'UTF-8'); ?>"></script>
		<link rel="stylesheet" href="<?php echo htmlspecialchars('/src/assets/css/theme-global.css?v=20260225k', ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body<?php echo $_home_theme_body_class; ?>>
	<div class="page">
		<div class="head">
			<h1 class="title">Data Absensi Karyawan</h1>
			<div class="actions">
				<a href="<?php echo site_url('home'); ?>" class="btn outline">Kembali Dashboard</a>
				<a href="<?php echo site_url('home/leave_requests'); ?>" class="btn outline">Data Pengajuan</a>
				<a href="<?php echo site_url('logout'); ?>" class="btn primary">Logout</a>
			</div>
		</div>

		<?php if ($notice_success): ?>
			<div class="notice success"><?php echo htmlspecialchars((string) $notice_success, ENT_QUOTES, 'UTF-8'); ?></div>
		<?php endif; ?>
		<?php if ($notice_error): ?>
			<div class="notice error"><?php echo htmlspecialchars((string) $notice_error, ENT_QUOTES, 'UTF-8'); ?></div>
		<?php endif; ?>

		<div class="table-card">
			<div class="mode-tabs">
				<a href="<?php echo site_url('home/employee_data'); ?>" class="mode-link active">Data Harian</a>
				<a href="<?php echo site_url('home/employee_data_monthly'); ?>" class="mode-link">Data Bulanan</a>
			</div>
			<?php if (empty($records)): ?>
				<div class="empty">Belum ada data absensi yang tersimpan.</div>
			<?php else: ?>
				<div class="table-tools">
					<input id="attendanceSearchInput" type="text" class="search-input" placeholder="Cari ID atau nama karyawan...">
					<p class="search-help">Pencarian berlaku untuk kolom ID dan Nama.</p>
				</div>
				<div class="table-wrap">
					<table>
						<thead>
							<tr>
								<th>No</th>
								<th>ID</th>
								<th>PP</th>
								<th>Nama</th>
								<th>Alamat</th>
								<th>Jabatan</th>
								<th>Cabang Asal</th>
								<th>Cabang Absen</th>
								<th>Telp</th>
								<th>Tanggal</th>
								<th>Shift</th>
								<th>Absen Masuk</th>
								<th>Telat</th>
								<th>Potongan Gaji</th>
								<th>Absen Pulang</th>
								<th>Durasi Bekerja</th>
								<th>Foto Masuk</th>
								<th>Foto Pulang</th>
								<th>Jarak Masuk (Meter)</th>
								<th>Jarak Pulang (Meter)</th>
								<th>Alasan Telat</th>
								<th>Aksi</th>
							</tr>
						</thead>
						<tbody id="attendanceTableBody">
							<?php $no = 1; ?>
							<?php foreach ($records as $row): ?>
								<?php
								$shift_name = isset($row['shift_name']) ? (string) $row['shift_name'] : '';
								$shift_short = '-';
								$check_in_raw = isset($row['check_in_time']) ? (string) $row['check_in_time'] : '';
								$check_out_raw = isset($row['check_out_time']) ? (string) $row['check_out_time'] : '';
								$check_in_display = '-';
								$check_out_display = '-';
								$late_display = '-';
								$late_reason_display = '-';
								$salary_cut_amount = isset($row['salary_cut_amount']) ? (float) $row['salary_cut_amount'] : 0;
								$salary_cut_display = '-';
								$row_record_version = isset($row['record_version']) ? (int) $row['record_version'] : 1;
								if ($row_record_version <= 0)
								{
									$row_record_version = 1;
								}
								$row_username = isset($row['username']) ? (string) $row['username'] : '';
								$row_employee_id = isset($row['employee_id']) && trim((string) $row['employee_id']) !== ''
									? (string) $row['employee_id']
									: '-';
								$row_profile_photo = isset($row['profile_photo']) && trim((string) $row['profile_photo']) !== ''
									? (string) $row['profile_photo']
									: (is_file(FCPATH.'src/assets/fotoku.webp') ? '/src/assets/fotoku.webp' : '/src/assets/fotoku.JPG');
								$row_profile_photo_url = $row_profile_photo;
								if (strpos($row_profile_photo_url, 'data:') !== 0 && preg_match('/^https?:\/\//i', $row_profile_photo_url) !== 1)
								{
									$row_profile_photo_relative = ltrim($row_profile_photo_url, '/\\');
									$row_profile_photo_info = pathinfo($row_profile_photo_relative);
									$row_profile_photo_thumb_relative = '';
									$row_profile_photo_cache_version = 0;
									$row_profile_photo_absolute = FCPATH.str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $row_profile_photo_relative);
									if (is_file($row_profile_photo_absolute))
									{
										$row_profile_photo_cache_version = (int) @filemtime($row_profile_photo_absolute);
									}
									if (isset($row_profile_photo_info['filename']) && trim((string) $row_profile_photo_info['filename']) !== '')
									{
										$row_profile_photo_dir = isset($row_profile_photo_info['dirname']) ? (string) $row_profile_photo_info['dirname'] : '';
										$row_profile_photo_thumb_relative = $row_profile_photo_dir !== '' && $row_profile_photo_dir !== '.'
											? $row_profile_photo_dir.'/'.$row_profile_photo_info['filename'].'_thumb.webp'
											: $row_profile_photo_info['filename'].'_thumb.webp';
									}
									if ($row_profile_photo_thumb_relative !== '' &&
										is_file(FCPATH.str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $row_profile_photo_thumb_relative)))
									{
										$row_profile_photo_thumb_absolute = FCPATH.str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $row_profile_photo_thumb_relative);
										$row_profile_photo_thumb_version = (int) @filemtime($row_profile_photo_thumb_absolute);
										if ($row_profile_photo_thumb_version >= $row_profile_photo_cache_version)
										{
											$row_profile_photo_relative = $row_profile_photo_thumb_relative;
											$row_profile_photo_cache_version = $row_profile_photo_thumb_version;
										}
									}
									$row_profile_photo_url = base_url(ltrim($row_profile_photo_relative, '/'));
									if ($row_profile_photo_cache_version > 0)
									{
										$row_profile_photo_url .= '?v='.$row_profile_photo_cache_version;
									}
								}
								$row_address = isset($row['address']) && trim((string) $row['address']) !== ''
									? (string) $row['address']
									: 'Kp. Kesekian Kalinya, Pandenglang, Banten';
								$row_job_title = isset($row['job_title']) && trim((string) $row['job_title']) !== ''
									? (string) $row['job_title']
									: 'Teknisi';
								$row_branch_origin = isset($row['branch_origin']) && trim((string) $row['branch_origin']) !== ''
									? (string) $row['branch_origin']
									: 'Baros';
								$row_branch_attendance = isset($row['branch_attendance']) && trim((string) $row['branch_attendance']) !== ''
									? (string) $row['branch_attendance']
									: $row_branch_origin;
								$row_phone = isset($row['phone']) && trim((string) $row['phone']) !== ''
									? (string) $row['phone']
									: '-';
								$row_date_key = isset($row['date']) ? (string) $row['date'] : '';
								if ($check_in_raw !== '')
								{
									$check_in_display = substr($check_in_raw, 0, 5);
								}
								if ($check_out_raw !== '')
								{
									$check_out_display = substr($check_out_raw, 0, 5);
								}
								if (isset($row['check_in_late']) && trim((string) $row['check_in_late']) !== '' && (string) $row['check_in_late'] !== '00:00:00')
								{
									$late_display = (string) $row['check_in_late'];
								}
								if (isset($row['late_reason']) && trim((string) $row['late_reason']) !== '')
								{
									$late_reason_display = (string) $row['late_reason'];
								}
								if ($salary_cut_amount > 0)
								{
									$salary_cut_display = 'Rp '.number_format($salary_cut_amount, 0, ',', '.');
								}

								if (stripos($shift_name, 'pagi') !== FALSE)
								{
									$shift_short = 'Pagi';
								}
								elseif (stripos($shift_name, 'siang') !== FALSE)
								{
									$shift_short = 'Siang';
								}
								elseif (stripos($shift_name, 'multi') !== FALSE)
								{
									$shift_short = 'Multi';
								}
								?>
								<tr class="attendance-row" data-id="<?php echo htmlspecialchars(strtolower($row_employee_id), ENT_QUOTES, 'UTF-8'); ?>" data-name="<?php echo htmlspecialchars(strtolower($row_username), ENT_QUOTES, 'UTF-8'); ?>" data-date-key="<?php echo htmlspecialchars($row_date_key, ENT_QUOTES, 'UTF-8'); ?>" data-date-label="<?php echo htmlspecialchars(isset($row['date_label']) ? (string) $row['date_label'] : '-', ENT_QUOTES, 'UTF-8'); ?>" data-record-version="<?php echo (int) $row_record_version; ?>">
									<td class="row-no"><?php echo $no; ?></td>
									<td><?php echo htmlspecialchars($row_employee_id, ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<img class="profile-avatar" src="<?php echo htmlspecialchars($row_profile_photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="PP <?php echo htmlspecialchars($row_username !== '' ? $row_username : 'Karyawan', ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async">
									</td>
									<td><?php echo htmlspecialchars($row_username !== '' ? $row_username : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="address-cell"><?php echo htmlspecialchars($row_address, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($row_job_title, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($row_branch_origin, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($row_branch_attendance, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($row_phone, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($row['date_label']) ? (string) $row['date_label'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><span class="shift-chip"><?php echo htmlspecialchars($shift_short, ENT_QUOTES, 'UTF-8'); ?></span></td>
									<td><?php echo htmlspecialchars($check_in_display, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($late_display, ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<?php if ($salary_cut_display !== '-'): ?>
											<span class="deduction-amount"><?php echo htmlspecialchars($salary_cut_display, ENT_QUOTES, 'UTF-8'); ?></span>
										<?php else: ?>
											<span class="muted">-</span>
										<?php endif; ?>
									</td>
									<td><?php echo htmlspecialchars($check_out_display, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($row['work_duration']) && $row['work_duration'] !== '' ? (string) $row['work_duration'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<?php if (isset($row['check_in_photo']) && $row['check_in_photo'] !== ''): ?>
											<img class="photo" src="<?php echo htmlspecialchars((string) $row['check_in_photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Foto masuk" loading="lazy" decoding="async">
										<?php else: ?>
											<span class="muted">-</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if (isset($row['check_out_photo']) && $row['check_out_photo'] !== ''): ?>
											<img class="photo" src="<?php echo htmlspecialchars((string) $row['check_out_photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Foto pulang" loading="lazy" decoding="async">
										<?php else: ?>
											<span class="muted">-</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if (isset($row['check_in_distance_m']) && $row['check_in_distance_m'] !== '' && isset($row['check_in_lat']) && isset($row['check_in_lng']) && $row['check_in_lat'] !== '' && $row['check_in_lng'] !== ''): ?>
											<?php $check_in_map = 'https://www.google.com/maps?q='.rawurlencode((string) $row['check_in_lat'].','.(string) $row['check_in_lng']); ?>
											<a class="distance-link" href="<?php echo htmlspecialchars($check_in_map, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
												<?php echo htmlspecialchars((string) $row['check_in_distance_m'].' m', ENT_QUOTES, 'UTF-8'); ?>
											</a>
										<?php elseif (isset($row['check_in_distance_m']) && $row['check_in_distance_m'] !== ''): ?>
											<?php echo htmlspecialchars((string) $row['check_in_distance_m'].' m', ENT_QUOTES, 'UTF-8'); ?>
										<?php else: ?>
											<span class="muted">-</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if (isset($row['check_out_distance_m']) && $row['check_out_distance_m'] !== '' && isset($row['check_out_lat']) && isset($row['check_out_lng']) && $row['check_out_lat'] !== '' && $row['check_out_lng'] !== ''): ?>
											<?php $check_out_map = 'https://www.google.com/maps?q='.rawurlencode((string) $row['check_out_lat'].','.(string) $row['check_out_lng']); ?>
											<a class="distance-link" href="<?php echo htmlspecialchars($check_out_map, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
												<?php echo htmlspecialchars((string) $row['check_out_distance_m'].' m', ENT_QUOTES, 'UTF-8'); ?>
											</a>
										<?php elseif (isset($row['check_out_distance_m']) && $row['check_out_distance_m'] !== ''): ?>
											<?php echo htmlspecialchars((string) $row['check_out_distance_m'].' m', ENT_QUOTES, 'UTF-8'); ?>
										<?php else: ?>
											<span class="muted">-</span>
										<?php endif; ?>
									</td>
									<td><?php echo htmlspecialchars($late_reason_display, ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<div class="row-actions">
											<?php if ($can_edit_attendance_records): ?>
												<button
													type="button"
													class="edit-btn"
													data-edit-row
													data-username="<?php echo htmlspecialchars($row_username, ENT_QUOTES, 'UTF-8'); ?>"
													data-date="<?php echo htmlspecialchars($row_date_key, ENT_QUOTES, 'UTF-8'); ?>"
													data-date-label="<?php echo htmlspecialchars(isset($row['date_label']) ? (string) $row['date_label'] : '-', ENT_QUOTES, 'UTF-8'); ?>"
													data-check-in-time="<?php echo htmlspecialchars($check_in_raw, ENT_QUOTES, 'UTF-8'); ?>"
													data-check-out-time="<?php echo htmlspecialchars($check_out_raw, ENT_QUOTES, 'UTF-8'); ?>"
													data-current-cut="<?php echo htmlspecialchars((string) max(0, (int) round($salary_cut_amount)), ENT_QUOTES, 'UTF-8'); ?>"
													data-record-version="<?php echo (int) $row_record_version; ?>"
												>Edit</button>
											<?php endif; ?>
											<?php if ($can_delete_attendance_records): ?>
												<form method="post" action="<?php echo site_url('home/delete_attendance_record'); ?>" class="row-delete-form" onsubmit="return window.confirm('Hapus data absensi ini (masuk + pulang)?');">
													<input type="hidden" name="username" value="<?php echo htmlspecialchars($row_username, ENT_QUOTES, 'UTF-8'); ?>">
													<input type="hidden" name="date" value="<?php echo htmlspecialchars($row_date_key, ENT_QUOTES, 'UTF-8'); ?>">
													<input type="hidden" name="expected_version" value="<?php echo (int) $row_record_version; ?>">
													<button type="submit" class="delete-btn">Hapus Full</button>
												</form>
											<?php endif; ?>
											<?php if ($can_partial_delete_attendance_records): ?>
												<?php if ($has_real_time($check_in_raw)): ?>
													<form method="post" action="<?php echo site_url('home/delete_attendance_record_partial'); ?>" class="row-delete-form" onsubmit="return window.confirm('Hapus absen masuk saja untuk data ini?');">
														<input type="hidden" name="username" value="<?php echo htmlspecialchars($row_username, ENT_QUOTES, 'UTF-8'); ?>">
														<input type="hidden" name="date" value="<?php echo htmlspecialchars($row_date_key, ENT_QUOTES, 'UTF-8'); ?>">
														<input type="hidden" name="expected_version" value="<?php echo (int) $row_record_version; ?>">
														<input type="hidden" name="delete_part" value="masuk">
														<button type="submit" class="delete-part-btn">Hapus Masuk</button>
													</form>
												<?php endif; ?>
												<?php if ($has_real_time($check_out_raw)): ?>
													<form method="post" action="<?php echo site_url('home/delete_attendance_record_partial'); ?>" class="row-delete-form" onsubmit="return window.confirm('Hapus absen pulang saja untuk data ini?');">
														<input type="hidden" name="username" value="<?php echo htmlspecialchars($row_username, ENT_QUOTES, 'UTF-8'); ?>">
														<input type="hidden" name="date" value="<?php echo htmlspecialchars($row_date_key, ENT_QUOTES, 'UTF-8'); ?>">
														<input type="hidden" name="expected_version" value="<?php echo (int) $row_record_version; ?>">
														<input type="hidden" name="delete_part" value="pulang">
														<button type="submit" class="delete-part-btn">Hapus Pulang</button>
													</form>
												<?php endif; ?>
											<?php endif; ?>
											<?php if (!$can_edit_attendance_records && !$can_delete_attendance_records && !$can_partial_delete_attendance_records): ?>
												<span class="muted">Akses dibatasi</span>
											<?php endif; ?>
										</div>
									</td>
								</tr>
								<?php $no += 1; ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div id="attendanceSearchEmpty" class="empty" style="display:none;">Data absensi tidak ditemukan.</div>
				<div id="attendancePageMeta" class="table-meta"></div>
				<div id="attendancePager" class="pager"></div>
			<?php endif; ?>
		</div>
	</div>

	<div id="deductionModal" class="edit-modal" aria-hidden="true">
		<div class="modal-overlay" data-edit-close></div>
		<section class="edit-panel" role="dialog" aria-modal="true" aria-labelledby="deductionModalTitle">
			<div class="edit-head">
				<h2 id="deductionModalTitle" class="edit-title">Edit Data Absensi</h2>
				<button type="button" class="edit-close" id="closeDeductionModal" aria-label="Tutup popup edit">&times;</button>
			</div>
			<div class="edit-body">
				<div class="edit-info">
					<p class="edit-info-title">Karyawan</p>
					<p id="deductionEmployeeValue" class="edit-info-value">-</p>
				</div>
				<div class="edit-info">
					<p class="edit-info-title">Tanggal Absensi</p>
					<p id="deductionDateValue" class="edit-info-value">-</p>
				</div>

				<form id="deductionForm" method="post" action="<?php echo site_url('home/update_attendance_deduction'); ?>">
					<input type="hidden" id="deductionUsernameInput" name="username" value="">
					<input type="hidden" id="deductionDateInput" name="date" value="">
					<input type="hidden" id="deductionExpectedVersionInput" name="expected_version" value="1">
					<?php if ($can_edit_attendance_datetime): ?>
						<div class="edit-field">
							<label for="deductionEditDateInput" class="edit-label">Tanggal Absensi (Edit)</label>
							<input id="deductionEditDateInput" class="edit-input" type="date" name="edit_date" autocomplete="off">
							<p class="edit-help">Khusus bos/developer/adminbaros/admin_cadasari. Format tanggal: YYYY-MM-DD.</p>
						</div>
						<div class="edit-field">
							<label for="deductionCheckInInput" class="edit-label">Jam Masuk (Edit)</label>
							<input id="deductionCheckInInput" class="edit-input" type="time" name="edit_check_in_time" step="1" autocomplete="off">
						</div>
						<div class="edit-field">
							<label for="deductionCheckOutInput" class="edit-label">Jam Pulang (Edit)</label>
							<input id="deductionCheckOutInput" class="edit-input" type="time" name="edit_check_out_time" step="1" autocomplete="off">
						</div>
					<?php endif; ?>
					<div class="edit-field">
						<label for="deductionAmountInput" class="edit-label">Potongan Gaji (Rp)</label>
						<input id="deductionAmountInput" class="edit-input" type="text" name="salary_cut_amount" inputmode="numeric" autocomplete="off" placeholder="Contoh: 25000">
						<p class="edit-help">Isi `0` atau klik tombol hapus jika ingin memberi toleransi darurat.</p>
					</div>
					<div class="edit-actions">
						<button type="button" id="clearDeductionButton" class="edit-clear">Hapus Potongan</button>
						<button type="submit" class="edit-submit">Simpan Perubahan</button>
					</div>
				</form>
			</div>
		</section>
	</div>

	<script>
		(function () {
			var searchInput = document.getElementById('attendanceSearchInput');
			var emptyInfo = document.getElementById('attendanceSearchEmpty');
			var pageMeta = document.getElementById('attendancePageMeta');
			var pager = document.getElementById('attendancePager');
			var rows = Array.prototype.slice.call(document.querySelectorAll('#attendanceTableBody .attendance-row'));

			if (!searchInput || !rows.length) {
				return;
			}

			var currentPage = 1;

			var uniqueDates = function (filteredRows) {
				var seen = {};
				var dates = [];
				for (var i = 0; i < filteredRows.length; i += 1) {
					var dateKey = String(filteredRows[i].getAttribute('data-date-key') || '');
					if (dateKey === '') {
						dateKey = '__unknown__';
					}
					if (!seen[dateKey]) {
						seen[dateKey] = true;
						dates.push(dateKey);
					}
				}
				return dates;
			};

			var assignVisibleRowNumbers = function (visibleRows) {
				for (var i = 0; i < visibleRows.length; i += 1) {
					var noCell = visibleRows[i].querySelector('.row-no');
					if (noCell) {
						noCell.textContent = String(i + 1);
					}
				}
			};

			var buildPagerButton = function (label, targetPage, extraClass) {
				var button = document.createElement('button');
				button.type = 'button';
				button.className = 'pager-btn' + (extraClass ? ' ' + extraClass : '');
				button.textContent = label;
				button.addEventListener('click', function () {
					currentPage = targetPage;
					renderRows();
				});
				return button;
			};

			var renderRows = function () {
				var keyword = String(searchInput.value || '').toLowerCase().trim();
				var filteredRows = [];

				for (var i = 0; i < rows.length; i += 1) {
					var row = rows[i];
					var idValue = String(row.getAttribute('data-id') || '');
					var nameValue = String(row.getAttribute('data-name') || '');
					var matched = keyword === '' || idValue.indexOf(keyword) !== -1 || nameValue.indexOf(keyword) !== -1;
					if (matched) {
						filteredRows.push(row);
					}
				}

				var datePages = uniqueDates(filteredRows);
				var totalPages = datePages.length > 0 ? datePages.length : 1;
				if (currentPage < 1) {
					currentPage = 1;
				}
				if (currentPage > totalPages) {
					currentPage = totalPages;
				}
				var activeDateKey = datePages.length > 0 ? datePages[currentPage - 1] : '';
				var visibleRows = [];
				for (var j = 0; j < rows.length; j += 1) {
					var rowDateKey = String(rows[j].getAttribute('data-date-key') || '');
					if (rowDateKey === '') {
						rowDateKey = '__unknown__';
					}
					var showRow = activeDateKey !== '' && rowDateKey === activeDateKey && filteredRows.indexOf(rows[j]) !== -1;
					rows[j].style.display = showRow ? '' : 'none';
					if (showRow) {
						visibleRows.push(rows[j]);
					}
				}
				assignVisibleRowNumbers(visibleRows);

				if (emptyInfo) {
					emptyInfo.style.display = filteredRows.length > 0 ? 'none' : 'block';
				}
				if (pageMeta) {
					if (filteredRows.length === 0) {
						pageMeta.textContent = '';
					} else {
						var dateLabel = '-';
						if (visibleRows.length > 0) {
							dateLabel = String(visibleRows[0].getAttribute('data-date-label') || '-');
						}
						pageMeta.textContent = 'Tanggal: ' + dateLabel + ' | Menampilkan ' + visibleRows.length + ' data | Halaman ' + currentPage + ' / ' + totalPages;
					}
				}

				if (pager) {
					pager.innerHTML = '';
					if (filteredRows.length > 0 && totalPages > 1) {
						if (currentPage > 1) {
							pager.appendChild(buildPagerButton('Sebelumnya', currentPage - 1, 'wide'));
						}
						var startPage = Math.max(1, currentPage - 2);
						var endPage = Math.min(totalPages, startPage + 4);
						if ((endPage - startPage + 1) < 5) {
							startPage = Math.max(1, endPage - 4);
						}
						for (var pageNo = startPage; pageNo <= endPage; pageNo += 1) {
							var pageButton = buildPagerButton(String(pageNo), pageNo, pageNo === currentPage ? 'active' : '');
							pager.appendChild(pageButton);
						}
						if (currentPage < totalPages) {
							pager.appendChild(buildPagerButton('Selanjutnya', currentPage + 1, 'wide'));
						}
					}
				}
			};

			searchInput.addEventListener('input', function () {
				currentPage = 1;
				renderRows();
			});
			renderRows();
		})();

		(function () {
			var modal = document.getElementById('deductionModal');
			var openButtons = document.querySelectorAll('[data-edit-row]');
			var closeTargets = document.querySelectorAll('[data-edit-close]');
			var closeButton = document.getElementById('closeDeductionModal');
			var form = document.getElementById('deductionForm');
			var employeeValue = document.getElementById('deductionEmployeeValue');
			var dateValue = document.getElementById('deductionDateValue');
			var usernameInput = document.getElementById('deductionUsernameInput');
			var dateInput = document.getElementById('deductionDateInput');
			var expectedVersionInput = document.getElementById('deductionExpectedVersionInput');
			var editDateInput = document.getElementById('deductionEditDateInput');
			var checkInInput = document.getElementById('deductionCheckInInput');
			var checkOutInput = document.getElementById('deductionCheckOutInput');
			var amountInput = document.getElementById('deductionAmountInput');
			var clearButton = document.getElementById('clearDeductionButton');

			if (!modal || !openButtons.length || !form || !amountInput) {
				return;
			}

			var toDigits = function (value) {
				return String(value || '').replace(/[^\d]/g, '');
			};

			var formatThousands = function (value) {
				var digits = toDigits(value);
				if (digits === '') {
					return '';
				}
				return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
			};

			var normalizeTimeForInput = function (value) {
				var text = String(value || '').trim();
				if (/^\d{2}:\d{2}:\d{2}$/.test(text) || /^\d{2}:\d{2}$/.test(text)) {
					return text;
				}
				return '';
			};

			var openModal = function (button) {
				var username = String(button.getAttribute('data-username') || '-');
				var dateLabel = String(button.getAttribute('data-date-label') || '-');
				var dateKey = String(button.getAttribute('data-date') || '');
				var checkInTime = String(button.getAttribute('data-check-in-time') || '');
				var checkOutTime = String(button.getAttribute('data-check-out-time') || '');
				var currentCut = String(button.getAttribute('data-current-cut') || '0');
				var recordVersion = parseInt(String(button.getAttribute('data-record-version') || '1'), 10);
				if (!isFinite(recordVersion) || recordVersion <= 0) {
					recordVersion = 1;
				}

				if (employeeValue) {
					employeeValue.textContent = username;
				}
				if (dateValue) {
					dateValue.textContent = dateLabel;
				}
				if (usernameInput) {
					usernameInput.value = username;
				}
				if (dateInput) {
					dateInput.value = dateKey;
				}
				if (editDateInput) {
					editDateInput.value = dateKey;
				}
				if (checkInInput) {
					checkInInput.value = normalizeTimeForInput(checkInTime);
				}
				if (checkOutInput) {
					checkOutInput.value = normalizeTimeForInput(checkOutTime);
				}
				if (expectedVersionInput) {
					expectedVersionInput.value = String(recordVersion);
				}
				amountInput.value = formatThousands(currentCut);

				modal.classList.add('show');
				modal.setAttribute('aria-hidden', 'false');
				setTimeout(function () {
					amountInput.focus();
					amountInput.select();
				}, 30);
			};

			var closeModal = function () {
				modal.classList.remove('show');
				modal.setAttribute('aria-hidden', 'true');
			};

			for (var i = 0; i < openButtons.length; i += 1) {
				openButtons[i].addEventListener('click', function () {
					openModal(this);
				});
			}

			for (var j = 0; j < closeTargets.length; j += 1) {
				closeTargets[j].addEventListener('click', closeModal);
			}

			if (closeButton) {
				closeButton.addEventListener('click', closeModal);
			}

			if (clearButton) {
				clearButton.addEventListener('click', function () {
					amountInput.value = '0';
					amountInput.focus();
				});
			}

			amountInput.addEventListener('input', function () {
				var start = amountInput.selectionStart;
				var previousLength = amountInput.value.length;
				amountInput.value = formatThousands(amountInput.value);
				var currentLength = amountInput.value.length;
				var nextPos = (start || 0) + (currentLength - previousLength);
				amountInput.setSelectionRange(nextPos, nextPos);
			});

			form.addEventListener('submit', function () {
				var raw = toDigits(amountInput.value);
				amountInput.value = raw === '' ? '0' : raw;
			});

			window.addEventListener('keydown', function (event) {
				if (event.key === 'Escape' && modal.classList.contains('show')) {
					closeModal();
				}
			});
		})();

		(function () {
			var wraps = document.querySelectorAll('.table-wrap');
			if (!wraps.length) {
				return;
			}

			var dragThreshold = 6;
			var clickBlockMs = 220;
			var noDragSelector = 'a, button, input, select, textarea, label, [role="button"]';

			for (var i = 0; i < wraps.length; i += 1) {
				(function (wrap) {
					var pointerDown = false;
					var dragging = false;
					var startX = 0;
					var startScrollLeft = 0;
					var lastDragEndedAt = 0;

					var onMouseMove = function (event) {
						if (!pointerDown) {
							return;
						}

						var deltaX = event.clientX - startX;
						if (!dragging && Math.abs(deltaX) >= dragThreshold) {
							dragging = true;
						}

						if (!dragging) {
							return;
						}

						wrap.scrollLeft = startScrollLeft - deltaX;
						event.preventDefault();
					};

					var stopDrag = function () {
						if (!pointerDown) {
							return;
						}

						pointerDown = false;
						window.removeEventListener('mousemove', onMouseMove);
						window.removeEventListener('mouseup', stopDrag);
						wrap.classList.remove('is-dragging');

						if (dragging) {
							lastDragEndedAt = Date.now();
						}
						dragging = false;
					};

					wrap.addEventListener('mousedown', function (event) {
						if (event.button !== 0) {
							return;
						}

						if (wrap.scrollWidth <= wrap.clientWidth) {
							return;
						}

						if (event.target.closest(noDragSelector)) {
							return;
						}

						pointerDown = true;
						dragging = false;
						startX = event.clientX;
						startScrollLeft = wrap.scrollLeft;
						wrap.classList.add('is-dragging');
						window.addEventListener('mousemove', onMouseMove);
						window.addEventListener('mouseup', stopDrag);
						event.preventDefault();
					});

					wrap.addEventListener('mouseleave', stopDrag);

					wrap.addEventListener('click', function (event) {
						if (Date.now() - lastDragEndedAt > clickBlockMs) {
							return;
						}

						if (!event.target.closest(noDragSelector)) {
							return;
						}

						event.preventDefault();
						event.stopPropagation();
					}, true);
				})(wraps[i]);
			}
		})();
	</script>
</body>
</html>
