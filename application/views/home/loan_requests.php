<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $requests = isset($requests) && is_array($requests) ? $requests : array(); ?>
<?php
$notice_success = $this->session->flashdata('loan_notice_success');
$notice_warning = $this->session->flashdata('loan_notice_warning');
$notice_error = $this->session->flashdata('loan_notice_error');
$is_developer_actor = isset($is_developer_actor) && $is_developer_actor === TRUE;
$can_process_loan_requests = isset($can_process_loan_requests) && $can_process_loan_requests === TRUE;
$can_delete_loan_requests = isset($can_delete_loan_requests) && $can_delete_loan_requests === TRUE;
$loan_pagination = isset($loan_pagination) && is_array($loan_pagination) ? $loan_pagination : array();
$loan_current_page = isset($loan_pagination['current_page']) ? (int) $loan_pagination['current_page'] : 1;
$loan_total_pages = isset($loan_pagination['total_pages']) ? (int) $loan_pagination['total_pages'] : 1;
$loan_start_page = isset($loan_pagination['start_page']) ? (int) $loan_pagination['start_page'] : 1;
$loan_end_page = isset($loan_pagination['end_page']) ? (int) $loan_pagination['end_page'] : 1;
$loan_mode = isset($loan_pagination['mode']) ? strtolower(trim((string) $loan_pagination['mode'])) : 'daily';
if ($loan_mode !== 'monthly')
{
	$loan_mode = 'daily';
}
$loan_group_label_prefix = isset($loan_pagination['group_label_prefix']) ? (string) $loan_pagination['group_label_prefix'] : 'Tanggal aktif';
$loan_current_date_label = isset($loan_pagination['current_group_label'])
	? (string) $loan_pagination['current_group_label']
	: (isset($loan_pagination['current_date_label']) ? (string) $loan_pagination['current_date_label'] : '-');
$loan_current_page_total = isset($loan_pagination['current_page_total']) ? (int) $loan_pagination['current_page_total'] : count($requests);
$loan_total_records = isset($loan_pagination['total_records']) ? (int) $loan_pagination['total_records'] : count($requests);
if ($loan_current_page < 1)
{
	$loan_current_page = 1;
}
if ($loan_total_pages < 1)
{
	$loan_total_pages = 1;
}
if ($loan_current_page > $loan_total_pages)
{
	$loan_current_page = $loan_total_pages;
}
if ($loan_start_page < 1)
{
	$loan_start_page = 1;
}
if ($loan_start_page > $loan_total_pages)
{
	$loan_start_page = $loan_total_pages;
}
if ($loan_end_page < $loan_start_page)
{
	$loan_end_page = $loan_start_page;
}
if ($loan_end_page > $loan_total_pages)
{
	$loan_end_page = $loan_total_pages;
}
$build_loan_page_url = function ($page_number, $mode = 'daily') {
	$page_value = max(1, (int) $page_number);
	$mode_value = strtolower(trim((string) $mode));
	if ($mode_value !== 'monthly')
	{
		$mode_value = 'daily';
	}
	$query_parts = array('page='.$page_value);
	if ($mode_value !== 'daily')
	{
		$query_parts[] = 'mode='.$mode_value;
	}
	return site_url('home/loan_requests').'?'.implode('&', $query_parts);
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
	<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Pengajuan Pinjaman'; ?></title>
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

		.view-mode-tabs {
			display: flex;
			align-items: center;
			gap: 0.55rem;
			margin-bottom: 0.72rem;
			padding-bottom: 0.72rem;
			border-bottom: 1px solid #e5eef8;
		}

		.view-mode-tab {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 0.58rem 1rem;
			border-radius: 999px;
			border: 1px solid #2f5e8b;
			color: #e9f3ff;
			background: #10345a;
			font-size: 0.82rem;
			font-weight: 700;
			text-decoration: none;
		}

		.view-mode-tab.active {
			background: linear-gradient(180deg, #1f6fbd 0%, #0f5c93 100%);
			border-color: #0f5c93;
			color: #ffffff;
			box-shadow: 0 9px 20px rgba(42, 130, 213, 0.34);
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
			min-width: 1480px;
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

		.phone {
			white-space: nowrap;
			font-weight: 600;
			color: #2a4969;
		}

		.amount {
			font-weight: 800;
			color: #1b4f87;
			white-space: nowrap;
		}

		.status-chip {
			display: inline-flex;
			padding: 0.22rem 0.55rem;
			border-radius: 999px;
			font-size: 0.68rem;
			font-weight: 700;
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

		.reason {
			white-space: pre-wrap;
			word-break: break-word;
			max-width: 320px;
		}

		.profile-avatar {
			width: 38px;
			height: 38px;
			border-radius: 999px;
			object-fit: cover;
			border: 1px solid #c7d9eb;
			background: #f3f8ff;
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

		html.theme-dark #loanPageMeta.table-meta,
		html.theme-dark .table-card .table-meta {
			background: #13273a !important;
			color: #d9e7f6 !important;
			border-top: 1px solid #35516b !important;
		}

		html.theme-dark .view-mode-tabs {
			border-bottom-color: rgba(109, 145, 182, 0.2);
		}

		html.theme-dark .view-mode-tab {
			background: #173149 !important;
			border-color: #486985 !important;
			color: #e4effd !important;
		}

		html.theme-dark .view-mode-tab.active {
			background: linear-gradient(180deg, #2f79c1 0%, #1b588f 100%) !important;
			border-color: #4f89c1 !important;
			color: #ffffff !important;
		}

		.pager {
			display: flex;
			align-items: center;
			gap: 0.45rem;
			padding: 0 0.85rem 0.9rem;
			flex-wrap: wrap;
		}

		html.theme-dark #loanPager.pager {
			background: #13273a !important;
			border-top: 0 !important;
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
			text-decoration: none;
			cursor: pointer;
		}

		html.theme-dark #loanPager .pager-btn {
			background: #173149 !important;
			border-color: #486985 !important;
			color: #e4effd !important;
		}

		.pager-btn:hover {
			background: #f0f7ff;
		}

		html.theme-dark #loanPager .pager-btn:hover {
			background: #1e3d5a !important;
		}

		.pager-btn.active {
			background: linear-gradient(180deg, #1f6fbd 0%, #0f5c93 100%);
			border-color: #0f5c93;
			color: #ffffff;
		}

		html.theme-dark #loanPager .pager-btn.active {
			background: linear-gradient(180deg, #2f79c1 0%, #1b588f 100%) !important;
			border-color: #4f89c1 !important;
			color: #ffffff !important;
		}

		html.theme-dark #loanPager .pager-btn.is-disabled {
			opacity: 0.48;
		}

		.pager-btn.wide {
			padding: 0 0.95rem;
			min-width: 6rem;
		}

		.pager-btn.is-disabled {
			opacity: 0.46;
			pointer-events: none;
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

			.btn {
				width: 100%;
				justify-content: center;
			}

			.table-tools {
				padding: 0.62rem 0.72rem 0;
			}

			.view-mode-tabs {
				margin-bottom: 0.6rem;
				padding-bottom: 0.6rem;
				gap: 0.4rem;
			}

			.view-mode-tab {
				padding: 0.48rem 0.78rem;
				font-size: 0.75rem;
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
			.admin-action-form,
			.row-actions {
				flex-wrap: wrap;
			}

			.admin-action-form {
				width: 100%;
			}

			.admin-btn,
			.row-delete-btn {
				width: 100%;
				text-align: center;
			}
		}

		@media (max-width: 520px) {
			.page {
				padding: 0.75rem 0.55rem 1rem;
			}

			.table-card,
			.form-card {
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
			<h1 class="title">Pengajuan Pinjaman</h1>
			<div class="actions">
				<a href="<?php echo site_url('home'); ?>" class="btn outline">Kembali Dashboard</a>
				<a href="<?php echo site_url('home/leave_requests'); ?>" class="btn outline">Data Cuti / Izin</a>
				<a href="<?php echo site_url('home/day_off_swap_requests'); ?>" class="btn outline">Tukar Hari Libur</a>
				<a href="<?php echo site_url('home/employee_data'); ?>" class="btn outline">Data Absensi</a>
				<a href="<?php echo site_url('logout'); ?>" class="btn primary">Logout</a>
			</div>
		</div>

		<?php if ($notice_success): ?>
			<div class="notice success"><?php echo htmlspecialchars((string) $notice_success, ENT_QUOTES, 'UTF-8'); ?></div>
		<?php endif; ?>
		<?php if ($notice_warning): ?>
			<div class="notice warning"><?php echo htmlspecialchars((string) $notice_warning, ENT_QUOTES, 'UTF-8'); ?></div>
		<?php endif; ?>
		<?php if ($notice_error): ?>
			<div class="notice error"><?php echo htmlspecialchars((string) $notice_error, ENT_QUOTES, 'UTF-8'); ?></div>
		<?php endif; ?>

		<div class="table-card">
			<?php if ($loan_total_records <= 0): ?>
				<div class="empty">Belum ada pengajuan pinjaman dari karyawan.</div>
			<?php else: ?>
				<div class="table-tools">
					<div class="view-mode-tabs">
						<a href="<?php echo htmlspecialchars($build_loan_page_url(1, 'daily'), ENT_QUOTES, 'UTF-8'); ?>" class="view-mode-tab<?php echo $loan_mode === 'daily' ? ' active' : ''; ?>">Data Terbaru</a>
						<a href="<?php echo htmlspecialchars($build_loan_page_url(1, 'monthly'), ENT_QUOTES, 'UTF-8'); ?>" class="view-mode-tab<?php echo $loan_mode === 'monthly' ? ' active' : ''; ?>">Data Bulanan</a>
					</div>
					<input id="loanSearchInput" type="text" class="search-input" placeholder="Cari ID, nama, atau no telp karyawan...">
					<p class="search-help">Saat mengetik, pencarian akan memindai semua halaman pada mode aktif (ID, Nama, Telp).</p>
				</div>
				<div class="table-wrap">
					<table>
						<thead>
							<tr>
								<th>No</th>
								<th>ID</th>
								<th>PP</th>
								<th>Nama</th>
								<th>Telp</th>
								<th>Jabatan</th>
								<th>Tanggal Pengajuan</th>
								<th>Nominal</th>
								<th>Alasan Pinjaman</th>
								<th>Rincian Pinjaman</th>
								<th>Status</th>
								<th>Status Lunas</th>
								<th>Aksi Admin</th>
							</tr>
						</thead>
						<tbody id="loanRequestTableBody">
							<?php
							$this->load->view('home/_loan_request_rows', array(
								'requests_for_render' => $requests,
								'render_start_no' => 1,
								'loan_current_page_for_render' => $loan_current_page,
								'loan_mode_for_render' => $loan_mode,
								'can_process_loan_requests' => $can_process_loan_requests,
								'can_delete_loan_requests' => $can_delete_loan_requests
							));
							?>
						</tbody>
					</table>
				</div>
				<div id="loanSearchEmpty" class="empty" style="display:none;">Data pengajuan tidak ditemukan.</div>
				<div id="loanPageMeta" class="table-meta">
					<span><?php echo htmlspecialchars($loan_group_label_prefix.': '.$loan_current_date_label.' | Halaman '.$loan_current_page.' dari '.$loan_total_pages, ENT_QUOTES, 'UTF-8'); ?></span>
					<span><?php echo htmlspecialchars('Data halaman ini: '.$loan_current_page_total.' | Total data: '.$loan_total_records, ENT_QUOTES, 'UTF-8'); ?></span>
				</div>
				<?php if ($loan_total_pages > 1): ?>
					<div id="loanPager" class="pager">
						<?php if ($loan_current_page > 1): ?>
							<a href="<?php echo htmlspecialchars($build_loan_page_url($loan_current_page - 1, $loan_mode), ENT_QUOTES, 'UTF-8'); ?>" class="pager-btn wide">Sebelumnya</a>
						<?php endif; ?>
						<?php for ($page_number = $loan_start_page; $page_number <= $loan_end_page; $page_number += 1): ?>
							<a href="<?php echo htmlspecialchars($build_loan_page_url($page_number, $loan_mode), ENT_QUOTES, 'UTF-8'); ?>" class="pager-btn<?php echo $page_number === $loan_current_page ? ' active' : ''; ?>"><?php echo (int) $page_number; ?></a>
						<?php endfor; ?>
						<?php if ($loan_current_page < $loan_total_pages): ?>
							<a href="<?php echo htmlspecialchars($build_loan_page_url($loan_current_page + 1, $loan_mode), ENT_QUOTES, 'UTF-8'); ?>" class="pager-btn wide">Selanjutnya</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
	<script>
		(function () {
			var searchInput = document.getElementById('loanSearchInput');
			var emptyInfo = document.getElementById('loanSearchEmpty');
			var tableBody = document.getElementById('loanRequestTableBody');
			var pager = document.getElementById('loanPager');
			var metaBox = document.getElementById('loanPageMeta');
			var searchEndpoint = <?php echo json_encode(site_url('home/loan_requests')); ?>;
			var currentPage = <?php echo (int) $loan_current_page; ?>;
			var modeValue = <?php echo json_encode($loan_mode); ?>;
			var modeLabel = <?php echo json_encode(isset($loan_pagination['mode_label']) ? (string) $loan_pagination['mode_label'] : 'Data Terbaru'); ?>;

			if (!searchInput || !tableBody) {
				return;
			}

			var metaSpans = metaBox ? metaBox.querySelectorAll('span') : [];
			var baseMetaTextLeft = metaSpans.length > 0 ? String(metaSpans[0].textContent || '') : '';
			var baseMetaTextRight = metaSpans.length > 1 ? String(metaSpans[1].textContent || '') : '';
			var initialTableHtml = String(tableBody.innerHTML || '');
			var searchToken = 0;
			var debounceTimer = null;
			var activeAbortController = null;

			var setMetaText = function (left, right) {
				if (!metaSpans.length) {
					return;
				}
				metaSpans[0].textContent = left;
				if (metaSpans.length > 1) {
					metaSpans[1].textContent = right;
				}
			};

			var setSearchState = function (isActive) {
				if (pager) {
					pager.style.display = isActive ? 'none' : '';
				}
				if (!isActive && metaSpans.length) {
					setMetaText(baseMetaTextLeft, baseMetaTextRight);
				}
			};

			var restoreInitialRows = function () {
				tableBody.innerHTML = initialTableHtml;
				if (emptyInfo) {
					emptyInfo.style.display = 'none';
				}
			};

			var buildSearchUrl = function (keyword) {
				var params = new URLSearchParams();
				params.set('ajax', '1');
				params.set('mode', modeValue);
				params.set('page', String(currentPage));
				params.set('keyword', keyword);
				return searchEndpoint + '?' + params.toString();
			};

			var fetchWithTimeout = function (url, timeoutMs) {
				var controller = null;
				if (typeof AbortController !== 'undefined') {
					controller = new AbortController();
					activeAbortController = controller;
				}

				var options = { credentials: 'same-origin' };
				if (controller) {
					options.signal = controller.signal;
				}

				var timer = window.setTimeout(function () {
					if (controller) {
						controller.abort();
					}
				}, timeoutMs);

				return fetch(url, options).then(function (response) {
					window.clearTimeout(timer);
					return response;
				}).catch(function (error) {
					window.clearTimeout(timer);
					throw error;
				});
			};

			var applySearch = function (token) {
				var keyword = String(searchInput.value || '').toLowerCase().trim();
				if (keyword === '') {
					if (activeAbortController && typeof activeAbortController.abort === 'function') {
						activeAbortController.abort();
					}
					activeAbortController = null;
					setSearchState(false);
					restoreInitialRows();
					return;
				}

				setSearchState(true);
				setMetaText('Pencarian server: memuat data...', 'Mode: ' + modeLabel);

				fetchWithTimeout(buildSearchUrl(keyword), 15000)
					.then(function (response) {
						if (!response.ok) {
							throw new Error('HTTP ' + response.status);
						}
						return response.json();
					})
					.then(function (payload) {
						if (token !== searchToken) {
							return;
						}

						var rowsHtml = payload && typeof payload.rows_html === 'string' ? payload.rows_html : '';
						var matchedCount = payload && typeof payload.count === 'number'
							? payload.count
							: 0;

						tableBody.innerHTML = rowsHtml;
						if (emptyInfo) {
							emptyInfo.style.display = matchedCount > 0 ? 'none' : 'block';
						}
						setMetaText(
							matchedCount > 0
								? ('Pencarian semua halaman: menampilkan ' + matchedCount + ' data')
								: 'Pencarian semua halaman: data tidak ditemukan.',
							'Mode: ' + modeLabel + ' | Source: server'
						);
					})
					.catch(function (error) {
						if (token !== searchToken) {
							return;
						}
						if (error && error.name === 'AbortError') {
							return;
						}
						console.error('Pencarian server gagal', error);
						setMetaText('Pencarian server gagal. Coba lagi.', 'Mode: ' + modeLabel);
					})
					.then(function () {
						activeAbortController = null;
					});
			};

			searchInput.addEventListener('input', function () {
				searchToken += 1;
				var activeToken = searchToken;
				if (debounceTimer !== null) {
					window.clearTimeout(debounceTimer);
				}
				debounceTimer = window.setTimeout(function () {
					applySearch(activeToken);
				}, 220);
			});

			restoreInitialRows();
			setSearchState(false);
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
