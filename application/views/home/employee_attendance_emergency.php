<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$records = isset($records) && is_array($records) ? $records : array();
$notice_success = $this->session->flashdata('attendance_notice_success');
$notice_error = $this->session->flashdata('attendance_notice_error');
$can_process_emergency_attendance = isset($can_process_emergency_attendance) && $can_process_emergency_attendance === TRUE;
$can_edit_attendance_records = isset($can_edit_attendance_records) && $can_edit_attendance_records === TRUE;
$can_delete_attendance_records = isset($can_delete_attendance_records) && $can_delete_attendance_records === TRUE;
$can_partial_delete_attendance_records = isset($can_partial_delete_attendance_records) && $can_partial_delete_attendance_records === TRUE;
$can_edit_attendance_datetime = isset($can_edit_attendance_datetime) && $can_edit_attendance_datetime === TRUE;
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
	<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Data Absen Darurat'; ?></title>
	<link rel="icon" type="image/svg+xml" href="/src/assets/sinyal.svg">
	<link rel="shortcut icon" type="image/svg+xml" href="/src/assets/sinyal.svg">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<style>
		* { box-sizing: border-box; scrollbar-width: none; -ms-overflow-style: none; }
		*::-webkit-scrollbar { width: 0; height: 0; }
		body { margin: 0; font-family: 'Outfit', sans-serif; background: #eef6ff; color: #11263d; }
		.page { max-width: 1380px; margin: 0 auto; padding: 1.1rem 1rem 1.4rem; }
		.head { display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; margin-bottom: 0.9rem; }
		.title { margin: 0; font-size: 1.25rem; font-weight: 800; }
		.actions { display: flex; align-items: center; gap: 0.5rem; }
		.btn { text-decoration: none; display: inline-flex; align-items: center; justify-content: center; padding: 0.52rem 0.88rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700; border: 0; cursor: pointer; }
		.btn.primary { background: #1f6fd6; color: #ffffff; }
		.btn.outline { background: #ffffff; color: #224162; border: 1px solid #c8dbee; }
		.notice { border-radius: 10px; padding: 0.72rem 0.84rem; margin-bottom: 0.7rem; font-size: 0.82rem; font-weight: 600; }
		.notice.success { background: #e6f7ee; border: 1px solid #c7e8d5; color: #1f7a4c; }
		.notice.error { background: #fdeeee; border: 1px solid #f4cece; color: #9d2b2b; }
		.table-card { background: #ffffff; border: 1px solid #d8e7f5; border-radius: 14px; overflow: hidden; box-shadow: 0 12px 26px rgba(8, 37, 69, 0.08); }
		.mode-tabs { padding: 0.85rem 0.85rem 0; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
		.mode-link { text-decoration: none; display: inline-flex; align-items: center; justify-content: center; padding: 0.5rem 0.86rem; border-radius: 999px; font-size: 0.79rem; font-weight: 700; color: #23466a; background: #ffffff; border: 1px solid #c8dbee; }
		.mode-link.active { color: #ffffff; border-color: #1f6fd6; background: linear-gradient(145deg, #1d5ea2 0%, #2b82d5 100%); }
		.table-tools { padding: 0.75rem 0.85rem 0; }
		.search-input { width: min(100%, 360px); border: 1px solid #c5d8ea; border-radius: 10px; padding: 0.56rem 0.68rem; font-family: inherit; font-size: 0.82rem; color: #183654; background: #ffffff; }
		.search-input:focus { outline: none; border-color: #2b82d5; box-shadow: 0 0 0 3px rgba(43, 130, 213, 0.14); }
		.search-help { margin: 0.42rem 0 0; font-size: 0.74rem; color: #617991; font-weight: 600; }
		.table-wrap { overflow-x: auto; }
		.table-wrap.is-dragging { user-select: none; }
		@media (pointer: fine) {
			.table-wrap { cursor: grab; }
			.table-wrap.is-dragging,
			.table-wrap.is-dragging * { cursor: grabbing !important; }
		}
		table { width: 100%; min-width: 2050px; border-collapse: collapse; }
		th, td { padding: 0.62rem 0.56rem; border-bottom: 1px solid #e9f1f9; text-align: left; vertical-align: middle; }
		th { font-size: 0.67rem; font-weight: 700; color: #627b97; text-transform: uppercase; letter-spacing: 0.08em; background: #f8fbff; }
		td { font-size: 0.8rem; color: #223850; }
		.profile-avatar { width: 38px; height: 38px; border-radius: 999px; object-fit: cover; border: 1px solid #c4d8eb; background: #eef5fd; }
		.photo {
			width: 70px;
			height: 70px;
			object-fit: cover;
			border-radius: 9px;
			border: 0;
			outline: 0;
			box-shadow: none;
			background: transparent;
			display: block;
		}
		.shift-chip { display: inline-flex; align-items: center; justify-content: center; min-width: 54px; height: 26px; border-radius: 999px; padding: 0 0.55rem; font-size: 0.72rem; font-weight: 700; background: #e9f3ff; color: #1c5e9f; }
		.badge { display: inline-flex; align-items: center; justify-content: center; min-width: 88px; height: 26px; border-radius: 999px; padding: 0 0.6rem; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; }
		.badge.hadir { background: #e7f7ed; color: #2a8b61; }
		.badge.terlambat { background: #fdecec; color: #b43838; }
		.badge.pending { background: #fff5de; color: #a7761f; }
		html.theme-dark .badge.hadir,
		body.theme-dark .badge.hadir {
			background: #123624;
			color: #7df3b5;
			border: 1px solid rgba(125, 243, 181, 0.34);
		}
		html.theme-dark .badge.terlambat,
		body.theme-dark .badge.terlambat {
			background: #3a181d;
			color: #ff9c9c;
			border: 1px solid rgba(255, 156, 156, 0.36);
		}
		html.theme-dark .badge.pending,
		body.theme-dark .badge.pending {
			background: #3e2d0d;
			color: #ffd786;
			border: 1px solid rgba(255, 215, 134, 0.34);
		}
		.distance-link { color: #1f6fd6; text-decoration: none; font-weight: 700; }
		.distance-link:hover { text-decoration: underline; }
		.row-actions { display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; }
		.row-form { margin: 0; }
		.approve-btn, .reject-btn {
			border: 0;
			border-radius: 9px;
			padding: 0.42rem 0.58rem;
			font-family: inherit;
			font-size: 0.72rem;
			font-weight: 700;
			cursor: pointer;
		}
		.approve-btn { background: #1f8b4f; color: #ffffff; }
		.reject-btn { background: #ba3434; color: #ffffff; }
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
		.row-delete-form { margin: 0; }
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
		.edit-modal.show { display: block; }
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
		.edit-title { margin: 0; font-size: 1rem; font-weight: 800; }
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
		.edit-body { padding: 0.95rem 1rem 1rem; }
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
		.edit-info-value { margin: 0.28rem 0 0; font-size: 0.9rem; font-weight: 700; color: #0e3158; }
		.edit-field { display: grid; gap: 0.38rem; margin-top: 0.4rem; }
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
		.edit-help { margin: 0; font-size: 0.74rem; color: #617991; font-weight: 600; }
		.edit-actions { margin-top: 0.85rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
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
		html.theme-dark .edit-info,
		body.theme-dark .edit-info {
			background: #162b3f !important;
			border-color: #3d5c77 !important;
		}
		html.theme-dark .edit-info-title,
		body.theme-dark .edit-info-title { color: #abc2d8 !important; }
		html.theme-dark .edit-info-value,
		body.theme-dark .edit-info-value { color: #edf5ff !important; }
		html.theme-dark .edit-label,
		body.theme-dark .edit-label { color: #abc2d8 !important; }
		html.theme-dark .edit-help,
		body.theme-dark .edit-help { color: #c8d9ea !important; }
		html.theme-dark .edit-input,
		body.theme-dark .edit-input {
			background: #0f2436 !important;
			border-color: #3e5974 !important;
			color: #e6eef8 !important;
			color-scheme: dark;
		}
		html.theme-dark .edit-input:focus,
		body.theme-dark .edit-input:focus {
			border-color: #69aae8 !important;
			box-shadow: 0 0 0 3px rgba(97, 168, 232, 0.22) !important;
		}
		html.theme-dark .edit-clear,
		body.theme-dark .edit-clear {
			background: #173149 !important;
			border-color: #446887 !important;
			color: #eaf4ff !important;
		}
		.muted { color: #8aa0b8; font-weight: 600; }
		.empty { padding: 1rem 0.9rem; font-size: 0.82rem; color: #5a728c; font-weight: 600; }
		.table-meta { padding: 0.62rem 0.85rem 0.2rem; font-size: 0.78rem; color: #516b86; font-weight: 700; }
		.pager { display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.85rem 0.88rem; flex-wrap: wrap; }
		.pager-btn {
			border: 1px solid #bfd3e6;
			background: #ffffff;
			color: #1f3d5c;
			border-radius: 10px;
			min-width: 2.2rem;
			height: 2rem;
			padding: 0 0.6rem;
			font-family: inherit;
			font-size: 0.78rem;
			font-weight: 700;
			cursor: pointer;
		}
		.pager-btn.wide { padding: 0 0.82rem; }
		.pager-btn.active { background: linear-gradient(145deg, #1d5ea2 0%, #2b82d5 100%); border-color: #1f6fd6; color: #ffffff; }
		@media (max-width: 820px) {
			.page { padding: 0.9rem 0.7rem 1.2rem; }
			.head { flex-direction: column; align-items: flex-start; }
			.actions { flex-wrap: wrap; }
			.btn { min-width: 124px; }
			.edit-modal { padding: 0.7rem; }
			.edit-panel { width: min(100%, 96vw); margin: max(0.5rem, env(safe-area-inset-top)) auto; }
			.edit-body { padding: 0.8rem; }
			.edit-actions { flex-direction: column; }
			.edit-submit,
			.edit-clear { width: 100%; }
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
				try { window.localStorage.setItem("home_index_theme", themeValue); } catch (error) {}
				try { document.cookie = "home_index_theme=" + encodeURIComponent(themeValue) + ";path=/;max-age=31536000;SameSite=Lax"; } catch (error) {}
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
			<h1 class="title">Data Absen Darurat</h1>
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
				<a href="<?php echo site_url('home/employee_data'); ?>" class="mode-link">Data Harian</a>
				<a href="<?php echo site_url('home/employee_data_monthly'); ?>" class="mode-link">Data Bulanan</a>
				<a href="<?php echo site_url('home/employee_data_emergency'); ?>" class="mode-link active">Absen Darurat</a>
			</div>
			<?php if (empty($records)): ?>
				<div class="empty">Belum ada data pengajuan absen darurat.</div>
			<?php else: ?>
				<div class="table-tools">
					<input id="emergencyAttendanceSearchInput" type="text" class="search-input" placeholder="Cari ID atau nama karyawan...">
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
								<th>Alasan Darurat</th>
								<th>Status</th>
								<th>Aksi</th>
							</tr>
						</thead>
						<tbody id="emergencyAttendanceTableBody">
							<?php $no = 1; ?>
							<?php foreach ($records as $row): ?>
								<?php
								$row_employee_id = isset($row['employee_id']) && trim((string) $row['employee_id']) !== '' ? (string) $row['employee_id'] : '-';
								$row_username = isset($row['username']) ? (string) $row['username'] : '';
								$row_profile_photo = isset($row['profile_photo']) && trim((string) $row['profile_photo']) !== ''
									? (string) $row['profile_photo']
									: (is_file(FCPATH.'src/assets/fotoku.webp') ? '/src/assets/fotoku.webp' : '/src/assets/fotoku.JPG');
								$row_profile_photo_url = $row_profile_photo;
								if (strpos($row_profile_photo_url, 'data:') !== 0 && preg_match('/^https?:\/\//i', $row_profile_photo_url) !== 1)
								{
									$row_profile_photo_url = base_url(ltrim($row_profile_photo_url, '/'));
								}
								$row_address = isset($row['address']) && trim((string) $row['address']) !== '' ? (string) $row['address'] : '-';
								$row_job_title = isset($row['job_title']) && trim((string) $row['job_title']) !== '' ? (string) $row['job_title'] : '-';
								$row_branch_origin = isset($row['branch_origin']) && trim((string) $row['branch_origin']) !== '' ? (string) $row['branch_origin'] : '-';
								$row_phone = isset($row['phone']) && trim((string) $row['phone']) !== '' ? (string) $row['phone'] : '-';
								$row_date_key = isset($row['date']) ? (string) $row['date'] : '';
								$row_date_label = isset($row['date_label']) && trim((string) $row['date_label']) !== '' ? (string) $row['date_label'] : '-';
								$row_shift_name = isset($row['shift_name']) && trim((string) $row['shift_name']) !== '' ? (string) $row['shift_name'] : '-';
								$row_shift_short = '-';
								if (stripos($row_shift_name, 'pagi') !== FALSE) { $row_shift_short = 'Pagi'; }
								elseif (stripos($row_shift_name, 'siang') !== FALSE) { $row_shift_short = 'Siang'; }
								elseif (stripos($row_shift_name, 'multi') !== FALSE) { $row_shift_short = 'Multi'; }
								elseif (trim($row_shift_name) !== '' && trim($row_shift_name) !== '-') { $row_shift_short = $row_shift_name; }

								$row_check_in_raw = isset($row['check_in_time']) ? (string) $row['check_in_time'] : '';
								$row_check_out_raw = isset($row['check_out_time']) ? (string) $row['check_out_time'] : '';
								$row_late_display = isset($row['check_in_late']) && trim((string) $row['check_in_late']) !== '' && (string) $row['check_in_late'] !== '00:00:00'
									? (string) $row['check_in_late']
									: '-';
								$row_salary_cut = isset($row['salary_cut_amount']) ? (int) $row['salary_cut_amount'] : 0;
								$row_salary_cut_display = $row_salary_cut > 0 ? ('Rp '.number_format($row_salary_cut, 0, ',', '.')) : '-';
								$row_duration = isset($row['work_duration']) && trim((string) $row['work_duration']) !== '' ? (string) $row['work_duration'] : '-';
								$row_check_in_photo = isset($row['check_in_photo']) ? trim((string) $row['check_in_photo']) : '';
								$row_check_out_photo = isset($row['check_out_photo']) ? trim((string) $row['check_out_photo']) : '';
								$row_check_in_distance = isset($row['check_in_distance_m']) && trim((string) $row['check_in_distance_m']) !== '' ? (string) $row['check_in_distance_m'] : '';
								$row_check_out_distance = isset($row['check_out_distance_m']) && trim((string) $row['check_out_distance_m']) !== '' ? (string) $row['check_out_distance_m'] : '';
								$row_emergency_reason = isset($row['emergency_reason']) && trim((string) $row['emergency_reason']) !== '' ? (string) $row['emergency_reason'] : '-';
								$row_status_key = isset($row['status_key']) ? (string) $row['status_key'] : 'pending';
								$row_status_label = isset($row['status_label']) ? (string) $row['status_label'] : 'Menunggu';
								$row_status_badge_class = isset($row['status_badge_class']) ? (string) $row['status_badge_class'] : 'pending';
								$row_request_id = isset($row['id']) ? (string) $row['id'] : '';
								$row_can_review = isset($row['can_review']) && $row['can_review'] === TRUE;
								$row_record_version = isset($row['record_version']) ? (int) $row['record_version'] : 1;
								if ($row_record_version <= 0) { $row_record_version = 1; }
								$row_attendance_exists = isset($row['attendance_record_exists']) && $row['attendance_record_exists'] === TRUE;
								$row_can_edit_record = isset($row['can_edit_record']) && $row['can_edit_record'] === TRUE;
								$row_can_delete_record = isset($row['can_delete_record']) && $row['can_delete_record'] === TRUE;
								$row_can_partial_delete_record = isset($row['can_partial_delete_record']) && $row['can_partial_delete_record'] === TRUE;
								$row_manage_processed = !$row_can_review && ($row_can_edit_record || $row_can_delete_record || $row_can_partial_delete_record);
								$row_modal_check_in = isset($row['attendance_check_in_time']) ? (string) $row['attendance_check_in_time'] : $row_check_in_raw;
								$row_modal_check_out = isset($row['attendance_check_out_time']) ? (string) $row['attendance_check_out_time'] : $row_check_out_raw;
								$row_modal_cut = isset($row['attendance_salary_cut_amount']) ? (int) $row['attendance_salary_cut_amount'] : $row_salary_cut;
								$row_display_check_in_raw = (!$row_can_review && $row_attendance_exists) ? $row_modal_check_in : $row_check_in_raw;
								$row_display_check_out_raw = (!$row_can_review && $row_attendance_exists) ? $row_modal_check_out : $row_check_out_raw;
								$row_check_in_display = $has_real_time($row_display_check_in_raw) ? substr($row_display_check_in_raw, 0, 5) : '-';
								$row_check_out_display = $has_real_time($row_display_check_out_raw) ? substr($row_display_check_out_raw, 0, 5) : '-';
								?>
								<tr class="emergency-attendance-row" data-id="<?php echo htmlspecialchars(strtolower($row_employee_id), ENT_QUOTES, 'UTF-8'); ?>" data-name="<?php echo htmlspecialchars(strtolower($row_username), ENT_QUOTES, 'UTF-8'); ?>" data-date-key="<?php echo htmlspecialchars($row_date_key, ENT_QUOTES, 'UTF-8'); ?>" data-date-label="<?php echo htmlspecialchars($row_date_label, ENT_QUOTES, 'UTF-8'); ?>">
									<td class="row-no"><?php echo (int) $no; ?></td>
									<td><?php echo htmlspecialchars($row_employee_id, ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<img class="profile-avatar" src="<?php echo htmlspecialchars($row_profile_photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="PP <?php echo htmlspecialchars($row_username !== '' ? $row_username : 'Karyawan', ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async">
									</td>
									<td><?php echo htmlspecialchars($row_username !== '' ? $row_username : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($row_address, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($row_job_title, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($row_branch_origin, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($row_phone, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($row_date_label, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><span class="shift-chip"><?php echo htmlspecialchars($row_shift_short, ENT_QUOTES, 'UTF-8'); ?></span></td>
									<td><?php echo htmlspecialchars($row_check_in_display, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($row_late_display, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($row_salary_cut_display, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($row_check_out_display, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($row_duration, ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<?php if ($row_check_in_photo !== ''): ?>
											<img class="photo" src="<?php echo htmlspecialchars($row_check_in_photo, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto masuk" loading="lazy" decoding="async">
										<?php else: ?>
											<span class="muted">-</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ($row_check_out_photo !== ''): ?>
											<img class="photo" src="<?php echo htmlspecialchars($row_check_out_photo, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto pulang" loading="lazy" decoding="async">
										<?php else: ?>
											<span class="muted">-</span>
										<?php endif; ?>
									</td>
									<td><?php echo $row_check_in_distance !== '' ? htmlspecialchars($row_check_in_distance.' m', ENT_QUOTES, 'UTF-8') : '<span class="muted">-</span>'; ?></td>
									<td><?php echo $row_check_out_distance !== '' ? htmlspecialchars($row_check_out_distance.' m', ENT_QUOTES, 'UTF-8') : '<span class="muted">-</span>'; ?></td>
									<td><?php echo htmlspecialchars($row_emergency_reason, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><span class="badge <?php echo htmlspecialchars($row_status_badge_class, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row_status_label, ENT_QUOTES, 'UTF-8'); ?></span></td>
									<td>
										<div class="row-actions">
											<?php if ($can_process_emergency_attendance && $row_can_review && $row_request_id !== ''): ?>
												<form method="post" action="<?php echo site_url('home/update_emergency_attendance_status'); ?>" class="row-form" onsubmit="return window.confirm('Setujui pengajuan absen darurat ini?');">
													<input type="hidden" name="request_id" value="<?php echo htmlspecialchars($row_request_id, ENT_QUOTES, 'UTF-8'); ?>">
													<input type="hidden" name="status" value="approved">
													<button type="submit" class="approve-btn">Terima</button>
												</form>
												<form method="post" action="<?php echo site_url('home/update_emergency_attendance_status'); ?>" class="row-form" onsubmit="return window.confirm('Tolak pengajuan absen darurat ini?');">
													<input type="hidden" name="request_id" value="<?php echo htmlspecialchars($row_request_id, ENT_QUOTES, 'UTF-8'); ?>">
													<input type="hidden" name="status" value="rejected">
													<button type="submit" class="reject-btn">Tolak</button>
												</form>
											<?php elseif ($row_manage_processed): ?>
												<?php if ($row_attendance_exists): ?>
													<?php
													$has_check_in_attendance = $has_real_time($row_display_check_in_raw);
													$has_check_out_attendance = $has_real_time($row_display_check_out_raw);
													?>
													<?php if ($row_can_edit_record): ?>
														<button
															type="button"
															class="edit-btn"
															data-edit-row
															data-username="<?php echo htmlspecialchars($row_username, ENT_QUOTES, 'UTF-8'); ?>"
															data-date="<?php echo htmlspecialchars($row_date_key, ENT_QUOTES, 'UTF-8'); ?>"
															data-date-label="<?php echo htmlspecialchars($row_date_label, ENT_QUOTES, 'UTF-8'); ?>"
															data-check-in-time="<?php echo htmlspecialchars($row_modal_check_in, ENT_QUOTES, 'UTF-8'); ?>"
															data-check-out-time="<?php echo htmlspecialchars($row_modal_check_out, ENT_QUOTES, 'UTF-8'); ?>"
															data-current-cut="<?php echo htmlspecialchars((string) max(0, $row_modal_cut), ENT_QUOTES, 'UTF-8'); ?>"
															data-record-version="<?php echo (int) $row_record_version; ?>"
														>Edit</button>
													<?php endif; ?>
													<?php if ($row_can_delete_record && $has_check_in_attendance && $has_check_out_attendance): ?>
														<form method="post" action="<?php echo site_url('home/delete_attendance_record'); ?>" class="row-delete-form" onsubmit="return window.confirm('Hapus data absensi ini (masuk + pulang)?');">
															<input type="hidden" name="username" value="<?php echo htmlspecialchars($row_username, ENT_QUOTES, 'UTF-8'); ?>">
															<input type="hidden" name="date" value="<?php echo htmlspecialchars($row_date_key, ENT_QUOTES, 'UTF-8'); ?>">
															<input type="hidden" name="expected_version" value="<?php echo (int) $row_record_version; ?>">
															<input type="hidden" name="return_to" value="home/employee_data_emergency">
															<button type="submit" class="delete-btn">Hapus Full</button>
														</form>
													<?php endif; ?>
													<?php if ($row_can_partial_delete_record && $has_check_in_attendance && !$has_check_out_attendance): ?>
															<form method="post" action="<?php echo site_url('home/delete_attendance_record_partial'); ?>" class="row-delete-form" onsubmit="return window.confirm('Hapus absen masuk saja untuk data ini?');">
																<input type="hidden" name="username" value="<?php echo htmlspecialchars($row_username, ENT_QUOTES, 'UTF-8'); ?>">
																<input type="hidden" name="date" value="<?php echo htmlspecialchars($row_date_key, ENT_QUOTES, 'UTF-8'); ?>">
																<input type="hidden" name="expected_version" value="<?php echo (int) $row_record_version; ?>">
																<input type="hidden" name="delete_part" value="masuk">
																<input type="hidden" name="return_to" value="home/employee_data_emergency">
																<button type="submit" class="delete-part-btn">Hapus Masuk</button>
															</form>
													<?php endif; ?>
													<?php if ($row_can_partial_delete_record && !$has_check_in_attendance && $has_check_out_attendance): ?>
															<form method="post" action="<?php echo site_url('home/delete_attendance_record_partial'); ?>" class="row-delete-form" onsubmit="return window.confirm('Hapus absen pulang saja untuk data ini?');">
																<input type="hidden" name="username" value="<?php echo htmlspecialchars($row_username, ENT_QUOTES, 'UTF-8'); ?>">
																<input type="hidden" name="date" value="<?php echo htmlspecialchars($row_date_key, ENT_QUOTES, 'UTF-8'); ?>">
																<input type="hidden" name="expected_version" value="<?php echo (int) $row_record_version; ?>">
																<input type="hidden" name="delete_part" value="pulang">
																<input type="hidden" name="return_to" value="home/employee_data_emergency">
																<button type="submit" class="delete-part-btn">Hapus Pulang</button>
															</form>
													<?php endif; ?>
												<?php else: ?>
													<span class="muted">Data harian tidak ada</span>
												<?php endif; ?>
											<?php else: ?>
												<span class="muted">-</span>
											<?php endif; ?>
										</div>
									</td>
								</tr>
								<?php $no += 1; ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div id="emergencyAttendanceSearchEmpty" class="empty" style="display:none;">Data absen darurat tidak ditemukan.</div>
				<div id="emergencyAttendancePageMeta" class="table-meta"></div>
				<div id="emergencyAttendancePager" class="pager"></div>
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
					<input type="hidden" name="return_to" value="home/employee_data_emergency">
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
			var searchInput = document.getElementById('emergencyAttendanceSearchInput');
			var emptyInfo = document.getElementById('emergencyAttendanceSearchEmpty');
			var pageMeta = document.getElementById('emergencyAttendancePageMeta');
			var pager = document.getElementById('emergencyAttendancePager');
			var rows = Array.prototype.slice.call(document.querySelectorAll('#emergencyAttendanceTableBody .emergency-attendance-row'));
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

				if (keyword !== '') {
					for (var keywordIndex = 0; keywordIndex < rows.length; keywordIndex += 1) {
						var keywordMatched = filteredRows.indexOf(rows[keywordIndex]) !== -1;
						rows[keywordIndex].style.display = keywordMatched ? '' : 'none';
					}
					assignVisibleRowNumbers(filteredRows);
					if (emptyInfo) {
						emptyInfo.style.display = filteredRows.length > 0 ? 'none' : 'block';
					}
					if (pageMeta) {
						pageMeta.textContent = filteredRows.length > 0
							? ('Pencarian semua tanggal: menampilkan ' + filteredRows.length + ' data')
							: '';
					}
					if (pager) {
						pager.innerHTML = '';
					}
					return;
				}

				var datePages = uniqueDates(filteredRows);
				var totalPages = datePages.length > 0 ? datePages.length : 1;
				if (currentPage < 1) { currentPage = 1; }
				if (currentPage > totalPages) { currentPage = totalPages; }

				var activeDateKey = datePages.length > 0 ? datePages[currentPage - 1] : '';
				var visibleRows = [];
				for (var rowIndex = 0; rowIndex < rows.length; rowIndex += 1) {
					var rowDateKey = String(rows[rowIndex].getAttribute('data-date-key') || '');
					if (rowDateKey === '') {
						rowDateKey = '__unknown__';
					}
					var showRow = activeDateKey !== '' && rowDateKey === activeDateKey && filteredRows.indexOf(rows[rowIndex]) !== -1;
					rows[rowIndex].style.display = showRow ? '' : 'none';
					if (showRow) {
						visibleRows.push(rows[rowIndex]);
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
						var dateLabel = visibleRows.length > 0 ? String(visibleRows[0].getAttribute('data-date-label') || '-') : '-';
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
							pager.appendChild(buildPagerButton(String(pageNo), pageNo, pageNo === currentPage ? 'active' : ''));
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
