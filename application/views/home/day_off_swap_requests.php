<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $requests = isset($requests) && is_array($requests) ? $requests : array(); ?>
<?php
$notice_success = isset($notice_success) ? (string) $notice_success : (string) $this->session->flashdata('day_off_swap_notice_success');
$notice_warning = isset($notice_warning) ? (string) $notice_warning : (string) $this->session->flashdata('day_off_swap_notice_warning');
$notice_error = isset($notice_error) ? (string) $notice_error : (string) $this->session->flashdata('day_off_swap_notice_error');
$can_process_day_off_swap_requests = isset($can_process_day_off_swap_requests) && $can_process_day_off_swap_requests === TRUE;
$can_delete_day_off_swap_requests = isset($can_delete_day_off_swap_requests) && $can_delete_day_off_swap_requests === TRUE;
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
	<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Pengajuan Tukar Hari Libur'; ?></title>
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
			max-width: 1420px;
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
			flex-wrap: wrap;
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

		.table-tools {
			padding: 0.75rem 0.85rem 0;
		}

		.search-input {
			width: min(100%, 380px);
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

		table {
			width: 100%;
			min-width: 1760px;
			border-collapse: collapse;
		}

		th,
		td {
			padding: 0.62rem 0.56rem;
			border-bottom: 1px solid #e9f1f9;
			text-align: left;
			vertical-align: top;
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

		.profile-avatar {
			width: 38px;
			height: 38px;
			border-radius: 999px;
			object-fit: cover;
			border: 1px solid #c7d9eb;
			background: #f3f8ff;
		}

		.row-subtext {
			display: block;
			margin-top: 0.18rem;
			font-size: 0.72rem;
			color: #6d8299;
			font-weight: 600;
		}

		.reason {
			white-space: pre-wrap;
			word-break: break-word;
			max-width: 320px;
		}

		.status-chip {
			display: inline-flex;
			padding: 0.22rem 0.55rem;
			border-radius: 999px;
			font-size: 0.68rem;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.04em;
		}

		.status-chip.waiting {
			background: #fff4df;
			color: #a66c15;
		}

		.status-chip.approved {
			background: #e4f7ed;
			color: #237d52;
		}

		.status-chip.rejected {
			background: #ffe9e9;
			color: #ad2d2d;
		}

		html.theme-dark .status-chip {
			font-weight: 800;
			border: 1px solid rgba(168, 195, 221, 0.42);
		}

		html.theme-dark .status-chip.waiting {
			background: #4c3b1e !important;
			border-color: #8f6f34 !important;
			color: #ffe9bc !important;
		}

		html.theme-dark .status-chip.approved {
			background: #1d4031 !important;
			border-color: #2f6f53 !important;
			color: #d4f8e6 !important;
		}

		html.theme-dark .status-chip.rejected {
			background: #4a222b !important;
			border-color: #8f4c58 !important;
			color: #ffdbe1 !important;
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

		html.theme-dark #swapPageMeta.table-meta,
		html.theme-dark .table-card .table-meta {
			background: #13273a !important;
			color: #d9e7f6 !important;
			border-top: 1px solid #35516b !important;
		}

		.pager {
			display: flex;
			align-items: center;
			gap: 0.45rem;
			padding: 0 0.85rem 0.9rem;
			flex-wrap: wrap;
		}

		html.theme-dark #swapPager.pager {
			background: #13273a !important;
			border-top: 1px solid #35516b;
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

		html.theme-dark #swapPager .pager-btn {
			background: #173149 !important;
			border-color: #486985 !important;
			color: #e4effd !important;
		}

		.pager-btn:hover {
			background: #f0f7ff;
		}

		html.theme-dark #swapPager .pager-btn:hover {
			background: #1e3d5a !important;
		}

		.pager-btn.active {
			background: linear-gradient(180deg, #1f6fbd 0%, #0f5c93 100%);
			border-color: #0f5c93;
			color: #ffffff;
		}

		html.theme-dark #swapPager .pager-btn.active {
			background: linear-gradient(180deg, #2f79c1 0%, #1b588f 100%) !important;
			border-color: #4f89c1 !important;
			color: #ffffff !important;
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

		.notice.warning {
			background: #fff8e8;
			border: 1px solid #f1ddaf;
			color: #9c6a12;
		}

		.notice.error {
			background: #ffeded;
			border: 1px solid #f0c1c1;
			color: #a43232;
		}

		.admin-actions {
			display: flex;
			gap: 0.42rem;
			align-items: center;
		}

		.admin-action-form {
			display: inline-flex;
			gap: 0.38rem;
			align-items: center;
			margin: 0;
		}

		.admin-note-input {
			min-width: 180px;
			border: 1px solid #c5d8ea;
			border-radius: 8px;
			padding: 0.38rem 0.5rem;
			font-size: 0.74rem;
			font-family: inherit;
			color: #1b3c5e;
			background: #ffffff;
		}

		.admin-note-input:focus {
			outline: none;
			border-color: #2b82d5;
			box-shadow: 0 0 0 3px rgba(43, 130, 213, 0.14);
		}

		.admin-btn {
			border: 0;
			border-radius: 8px;
			padding: 0.38rem 0.58rem;
			font-size: 0.72rem;
			font-weight: 700;
			cursor: pointer;
			color: #ffffff;
		}

		.admin-btn.approve {
			background: linear-gradient(145deg, #1d5ea2 0%, #2b82d5 100%);
		}

		.admin-btn.reject {
			background: #a94444;
		}

		.admin-btn.delete {
			background: #8f2630;
		}

		.processed-label {
			font-size: 0.74rem;
			font-weight: 700;
			color: #5f7591;
		}

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

			.admin-actions,
			.admin-action-form {
				flex-wrap: wrap;
			}

			.admin-note-input {
				width: 100%;
				min-width: 0;
			}

			.admin-btn {
				width: 100%;
				text-align: center;
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
			<h1 class="title">Pengajuan Tukar Hari Libur</h1>
			<div class="actions">
				<a href="<?php echo site_url('home'); ?>" class="btn outline">Kembali Dashboard</a>
				<a href="<?php echo site_url('home/leave_requests'); ?>" class="btn outline">Data Cuti / Izin</a>
				<a href="<?php echo site_url('home/loan_requests'); ?>" class="btn outline">Data Pinjaman</a>
				<a href="<?php echo site_url('logout'); ?>" class="btn primary">Logout</a>
			</div>
		</div>

		<?php if ($notice_success !== ''): ?>
			<div class="notice success"><?php echo htmlspecialchars($notice_success, ENT_QUOTES, 'UTF-8'); ?></div>
		<?php endif; ?>
		<?php if ($notice_warning !== ''): ?>
			<div class="notice warning"><?php echo htmlspecialchars($notice_warning, ENT_QUOTES, 'UTF-8'); ?></div>
		<?php endif; ?>
		<?php if ($notice_error !== ''): ?>
			<div class="notice error"><?php echo htmlspecialchars($notice_error, ENT_QUOTES, 'UTF-8'); ?></div>
		<?php endif; ?>

		<div class="table-card">
			<?php if (empty($requests)): ?>
				<div class="empty">Belum ada data pengajuan tukar hari libur.</div>
			<?php else: ?>
				<div class="table-tools">
					<input id="swapSearchInput" type="text" class="search-input" placeholder="Cari ID atau nama karyawan...">
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
								<th>Cabang</th>
								<th>Libur Asli (jadi masuk)</th>
								<th>Libur Pengganti (jadi libur)</th>
								<th>Catatan Karyawan</th>
								<th>Status</th>
								<th>Catatan Admin</th>
								<th>Diajukan</th>
								<th>Ditinjau</th>
								<th>Aksi Admin</th>
							</tr>
						</thead>
						<tbody id="swapRequestTableBody">
							<?php $no = 1; ?>
							<?php foreach ($requests as $row): ?>
								<?php
								$status_key = isset($row['status']) ? strtolower(trim((string) $row['status'])) : 'pending';
								$status_class = 'waiting';
								if ($status_key === 'approved') {
									$status_class = 'approved';
								} elseif ($status_key === 'rejected') {
									$status_class = 'rejected';
								}
								$status_label = isset($row['status_label']) && trim((string) $row['status_label']) !== ''
									? (string) $row['status_label']
									: ($status_key === 'approved' ? 'Diterima' : ($status_key === 'rejected' ? 'Ditolak' : 'Menunggu'));
								$is_pending = $status_key === 'pending';
								$request_id = isset($row['request_id']) ? trim((string) $row['request_id']) : '';
								$employee_id_value = isset($row['employee_id']) && trim((string) $row['employee_id']) !== ''
									? (string) $row['employee_id']
									: '-';
								$username_value = isset($row['username']) ? trim((string) $row['username']) : '-';
								$display_name_value = isset($row['display_name']) ? trim((string) $row['display_name']) : '';
								$name_label = $display_name_value !== '' ? $display_name_value : $username_value;
								if ($username_value !== '' && $username_value !== '-' && strcasecmp($name_label, $username_value) !== 0) {
									$name_label .= ' ('.$username_value.')';
								}
								$profile_photo_value = isset($row['profile_photo']) && trim((string) $row['profile_photo']) !== ''
									? (string) $row['profile_photo']
									: (is_file(FCPATH.'src/assets/fotoku.webp') ? '/src/assets/fotoku.webp' : '/src/assets/fotoku.JPG');
								$profile_photo_url = $profile_photo_value;
								if (strpos($profile_photo_url, 'data:') !== 0 && preg_match('/^https?:\/\//i', $profile_photo_url) !== 1) {
									$profile_photo_relative = ltrim($profile_photo_url, '/\\');
									$profile_photo_info = pathinfo($profile_photo_relative);
									$profile_photo_thumb_relative = '';
									if (isset($profile_photo_info['filename']) && trim((string) $profile_photo_info['filename']) !== '') {
										$profile_photo_dir = isset($profile_photo_info['dirname']) ? (string) $profile_photo_info['dirname'] : '';
										$profile_photo_thumb_relative = $profile_photo_dir !== '' && $profile_photo_dir !== '.'
											? $profile_photo_dir.'/'.$profile_photo_info['filename'].'_thumb.webp'
											: $profile_photo_info['filename'].'_thumb.webp';
									}
									if (
										$profile_photo_thumb_relative !== '' &&
										is_file(FCPATH.str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $profile_photo_thumb_relative))
									) {
										$profile_photo_relative = $profile_photo_thumb_relative;
									}
									$profile_photo_url = base_url(ltrim($profile_photo_relative, '/'));
								}
								$reviewed_label = '-';
								$reviewed_at_value = isset($row['reviewed_at']) ? trim((string) $row['reviewed_at']) : '';
								if ($reviewed_at_value !== '') {
									$reviewed_label = $reviewed_at_value;
								}
								$reviewed_by_value = isset($row['reviewed_by']) ? trim((string) $row['reviewed_by']) : '';
								if ($reviewed_by_value !== '') {
									$reviewed_label .= ' oleh '.$reviewed_by_value;
								}
								?>
								<tr
									class="swap-row"
									data-id="<?php echo htmlspecialchars(strtolower($employee_id_value), ENT_QUOTES, 'UTF-8'); ?>"
									data-name="<?php echo htmlspecialchars(strtolower($name_label), ENT_QUOTES, 'UTF-8'); ?>"
								>
									<td class="row-no"><?php echo $no; ?></td>
									<td><?php echo htmlspecialchars($employee_id_value, ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<img class="profile-avatar" src="<?php echo htmlspecialchars($profile_photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="PP <?php echo htmlspecialchars($name_label, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async">
									</td>
									<td>
										<?php echo htmlspecialchars($name_label, ENT_QUOTES, 'UTF-8'); ?>
										<?php if (isset($row['job_title']) && trim((string) $row['job_title']) !== ''): ?>
											<span class="row-subtext"><?php echo htmlspecialchars((string) $row['job_title'], ENT_QUOTES, 'UTF-8'); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo htmlspecialchars(isset($row['branch']) ? (string) $row['branch'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($row['workday_label']) ? (string) $row['workday_label'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($row['offday_label']) ? (string) $row['offday_label'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><span class="reason"><?php echo htmlspecialchars(isset($row['note']) && trim((string) $row['note']) !== '' ? (string) $row['note'] : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
									<td><span class="status-chip <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8'); ?></span></td>
									<td><span class="reason"><?php echo htmlspecialchars(isset($row['review_note']) && trim((string) $row['review_note']) !== '' ? (string) $row['review_note'] : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
									<td><?php echo htmlspecialchars(isset($row['requested_at']) && trim((string) $row['requested_at']) !== '' ? (string) $row['requested_at'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($reviewed_label, ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<div class="admin-actions">
											<?php if ($can_process_day_off_swap_requests && $is_pending && $request_id !== ''): ?>
												<form method="post" action="<?php echo site_url('home/update_day_off_swap_request_status'); ?>" class="admin-action-form">
													<input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request_id, ENT_QUOTES, 'UTF-8'); ?>">
													<input type="hidden" name="return_to" value="home/day_off_swap_requests">
													<input type="text" name="review_note" class="admin-note-input" maxlength="200" placeholder="Catatan admin (opsional)">
													<button type="submit" name="status" value="approved" class="admin-btn approve">Terima</button>
													<button type="submit" name="status" value="rejected" class="admin-btn reject">Tolak</button>
												</form>
											<?php else: ?>
												<span class="processed-label"><?php echo $is_pending ? 'Akses dibatasi' : 'Sudah diproses'; ?></span>
											<?php endif; ?>
											<?php if ($can_delete_day_off_swap_requests && $request_id !== ''): ?>
												<form method="post" action="<?php echo site_url('home/delete_day_off_swap_request'); ?>" class="admin-action-form" onsubmit="return window.confirm('Hapus data pengajuan tukar hari libur ini?');">
													<input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request_id, ENT_QUOTES, 'UTF-8'); ?>">
													<input type="hidden" name="return_to" value="home/day_off_swap_requests">
													<button type="submit" class="admin-btn delete">Hapus</button>
												</form>
											<?php endif; ?>
										</div>
									</td>
								</tr>
								<?php $no += 1; ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div id="swapSearchEmpty" class="empty" style="display:none;">Data pengajuan tidak ditemukan.</div>
				<div id="swapPageMeta" class="table-meta"></div>
				<div id="swapPager" class="pager"></div>
			<?php endif; ?>
		</div>
	</div>

	<script>
		(function () {
			var searchInput = document.getElementById('swapSearchInput');
			var emptyInfo = document.getElementById('swapSearchEmpty');
			var pageMeta = document.getElementById('swapPageMeta');
			var pager = document.getElementById('swapPager');
			var rows = Array.prototype.slice.call(document.querySelectorAll('#swapRequestTableBody .swap-row'));
			var pageSize = 15;

			if (!searchInput || !rows.length) {
				return;
			}

			var currentPage = 1;

			var assignVisibleRowNumbers = function (visibleRows, offset) {
				for (var i = 0; i < visibleRows.length; i += 1) {
					var noCell = visibleRows[i].querySelector('.row-no');
					if (noCell) {
						noCell.textContent = String(offset + i + 1);
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

				var totalPages = filteredRows.length > 0 ? Math.ceil(filteredRows.length / pageSize) : 1;
				if (currentPage < 1) {
					currentPage = 1;
				}
				if (currentPage > totalPages) {
					currentPage = totalPages;
				}
				var startIndex = (currentPage - 1) * pageSize;
				var endIndex = startIndex + pageSize;
				var visibleRows = [];

				for (var j = 0; j < rows.length; j += 1) {
					rows[j].style.display = 'none';
				}
				for (var k = startIndex; k < endIndex && k < filteredRows.length; k += 1) {
					filteredRows[k].style.display = '';
					visibleRows.push(filteredRows[k]);
				}
				assignVisibleRowNumbers(visibleRows, startIndex);

				if (emptyInfo) {
					emptyInfo.style.display = filteredRows.length > 0 ? 'none' : 'block';
				}
				if (pageMeta) {
					if (filteredRows.length === 0) {
						pageMeta.textContent = '';
					} else {
						var from = startIndex + 1;
						var until = Math.min(endIndex, filteredRows.length);
						pageMeta.textContent = 'Menampilkan ' + from + ' - ' + until + ' dari ' + filteredRows.length + ' data | Halaman ' + currentPage + ' / ' + totalPages;
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
						if (event.target.closest(noDragSelector)) {
							event.preventDefault();
							event.stopPropagation();
						}
					}, true);
				})(wraps[i]);
			}
		})();
	</script>
</body>
</html>
