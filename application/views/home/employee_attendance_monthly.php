<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$rows = isset($rows) && is_array($rows) ? $rows : array();
$selected_month = isset($selected_month) ? (string) $selected_month : date('Y-m');
$selected_month_label = isset($selected_month_label) ? (string) $selected_month_label : date('F Y');
$month_pagination = isset($month_pagination) && is_array($month_pagination) ? $month_pagination : array();
$current_page = isset($month_pagination['current_page']) ? (int) $month_pagination['current_page'] : 1;
$total_pages = isset($month_pagination['total_pages']) ? (int) $month_pagination['total_pages'] : 1;
if ($current_page < 1)
{
	$current_page = 1;
}
if ($total_pages < 1)
{
	$total_pages = 1;
}
$page_window = 5;
$start_page = max(1, $current_page - (int) floor($page_window / 2));
$end_page = min($total_pages, $start_page + $page_window - 1);
if (($end_page - $start_page + 1) < $page_window)
{
	$start_page = max(1, $end_page - $page_window + 1);
}
$build_month_page_url = function ($page_number) {
	$page_value = max(1, (int) $page_number);
	return site_url('home/employee_data_monthly').'?page='.$page_value;
};
$month_page_urls = array();
for ($page_number = 1; $page_number <= $total_pages; $page_number += 1)
{
	$month_page_urls[] = array(
		'page' => $page_number,
		'url' => $build_month_page_url($page_number)
	);
}
$export_month_url = site_url('home/employee_data_monthly').'?month='.rawurlencode($selected_month).'&export=excel';
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
	<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Data Absensi Bulanan'; ?></title>
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

		.meta-line {
			margin: 0 0 0.85rem;
			font-size: 0.82rem;
			color: #516a82;
			font-weight: 600;
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

		.table-tools {
			padding: 0.75rem 0.85rem 0;
			display: flex;
			align-items: center;
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 0.7rem;
		}

		.search-input,
		.month-input {
			border: 1px solid #c5d8ea;
			border-radius: 10px;
			padding: 0.56rem 0.68rem;
			font-family: inherit;
			font-size: 0.82rem;
			color: #183654;
			background: #ffffff;
		}

		.search-input {
			width: min(100%, 360px);
		}

		.month-form {
			display: flex;
			align-items: center;
			gap: 0.5rem;
		}

		.table-tools-actions {
			display: flex;
			align-items: center;
			gap: 0.5rem;
			flex-wrap: wrap;
		}

		.month-submit {
			border: 0;
			border-radius: 10px;
			padding: 0.58rem 0.78rem;
			font-family: inherit;
			font-size: 0.8rem;
			font-weight: 700;
			color: #ffffff;
			background: linear-gradient(145deg, #1d5ea2 0%, #2b82d5 100%);
			cursor: pointer;
		}

		.btn.excel {
			background: #1e8f4d;
			color: #ffffff;
		}

		.search-help {
			margin: 0.42rem 0.85rem 0;
			font-size: 0.74rem;
			color: #617991;
			font-weight: 600;
		}

		.month-pager {
			display: flex;
			align-items: center;
			gap: 0.45rem;
			padding: 0.65rem 0.85rem 0.2rem;
			flex-wrap: wrap;
		}

		.pager-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: 2.65rem;
			height: 2.1rem;
			padding: 0 0.72rem;
			border-radius: 0.7rem;
			border: 1px solid #b9cfe4;
			background: #ffffff;
			color: #1c4670;
			font-size: 0.78rem;
			font-weight: 700;
			text-decoration: none;
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
			min-width: 5.8rem;
		}

		.pager-btn.is-disabled {
			opacity: 0.46;
			pointer-events: none;
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
			min-width: 2340px;
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

		.count-pill {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 0.2rem 0.52rem;
			border-radius: 999px;
			background: #edf6ff;
			color: #245f96;
			font-size: 0.7rem;
			font-weight: 700;
		}

		.profile-avatar {
			width: 40px;
			height: 40px;
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

		.money {
			font-weight: 700;
			color: #1d4a75;
		}

		.money.minus {
			color: #b33b3b;
		}

		.empty {
			padding: 1rem;
			text-align: center;
			font-size: 0.9rem;
			color: #526a82;
		}

		/* Fallback dark-mode khusus halaman bulanan (tetap bekerja walau cache global belum update) */
		html.theme-dark body,
		body.theme-dark {
			background: #0d1a28 !important;
			color: #e6eef8 !important;
		}

		html.theme-dark .meta-line,
		body.theme-dark .meta-line {
			color: #c4d5e7 !important;
		}

		html.theme-dark .table-card,
		body.theme-dark .table-card {
			background: linear-gradient(180deg, #122436 0%, #101f2f 100%) !important;
			border-color: #324a61 !important;
			box-shadow: 0 12px 28px rgba(0, 0, 0, 0.32) !important;
		}

		html.theme-dark .mode-link,
		body.theme-dark .mode-link {
			background: #10283c !important;
			border-color: #38566f !important;
			color: #dbe8f6 !important;
		}

		html.theme-dark .mode-link.active,
		body.theme-dark .mode-link.active {
			background: linear-gradient(140deg, #2f79c1 0%, #1b588f 100%) !important;
			border-color: #4f89c1 !important;
			color: #ffffff !important;
		}

		html.theme-dark .btn.outline,
		body.theme-dark .btn.outline {
			background: rgba(10, 44, 72, 0.68) !important;
			border-color: rgba(171, 203, 233, 0.45) !important;
			color: #f4f8ff !important;
		}

		html.theme-dark .search-input,
		html.theme-dark .month-input,
		body.theme-dark .search-input,
		body.theme-dark .month-input {
			background: #0f2436 !important;
			border-color: #3e5974 !important;
			color: #e6eef8 !important;
		}

		html.theme-dark .search-input::placeholder,
		body.theme-dark .search-input::placeholder {
			color: #9eb4ca !important;
		}

		html.theme-dark .search-help,
		body.theme-dark .search-help {
			color: #c1d0e0 !important;
		}

		html.theme-dark .month-pager,
		body.theme-dark .month-pager {
			background: transparent !important;
		}

		html.theme-dark .pager-btn,
		body.theme-dark .pager-btn {
			background: #16344c !important;
			border-color: #4a6f8e !important;
			color: #eaf4ff !important;
		}

		html.theme-dark .pager-btn.active,
		body.theme-dark .pager-btn.active {
			background: linear-gradient(140deg, #2f79c1 0%, #1b588f 100%) !important;
			border-color: #4f89c1 !important;
			color: #ffffff !important;
		}

		html.theme-dark .pager-btn.is-disabled,
		body.theme-dark .pager-btn.is-disabled {
			background: #1b3044 !important;
			border-color: #3a5771 !important;
			color: #9eb4ca !important;
			opacity: 1 !important;
		}

		html.theme-dark th,
		body.theme-dark th {
			background: #11273b !important;
			border-color: #32495f !important;
			color: #d4e4f5 !important;
		}

		html.theme-dark td,
		body.theme-dark td {
			border-color: #2e455b !important;
			color: #e3edf8 !important;
		}

		html.theme-dark tbody tr:nth-child(2n),
		body.theme-dark tbody tr:nth-child(2n) {
			background: rgba(16, 33, 49, 0.58) !important;
		}

		html.theme-dark tbody tr:hover,
		html.theme-dark tbody tr:focus-within,
		body.theme-dark tbody tr:hover,
		body.theme-dark tbody tr:focus-within {
			background: #1a3248 !important;
		}

		html.theme-dark .count-pill,
		body.theme-dark .count-pill {
			background: #21384e !important;
			color: #e3effb !important;
			border: 1px solid #47617a !important;
		}

		html.theme-dark .money,
		body.theme-dark .money {
			color: #d8eaff !important;
		}

		html.theme-dark .money.minus,
		body.theme-dark .money.minus {
			color: #ffd3d3 !important;
		}

		html.theme-dark ::selection,
		body.theme-dark ::selection {
			background: rgba(87, 145, 198, 0.45) !important;
			color: #f4f8ff !important;
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

			.meta-line {
				font-size: 0.76rem;
				line-height: 1.45;
			}

			.table-tools {
				padding: 0.62rem 0.72rem 0;
				gap: 0.56rem;
			}

			.search-input {
				width: 100%;
				max-width: 100%;
			}

			.month-form {
				width: 100%;
				display: grid;
				grid-template-columns: 1fr auto;
				gap: 0.42rem;
			}

			.table-tools-actions {
				width: 100%;
				flex-direction: column;
				align-items: stretch;
			}

			.month-input {
				width: 100%;
				min-width: 0;
			}

			.month-pager {
				padding: 0.55rem 0.72rem 0.1rem;
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
		}

		@media (max-width: 520px) {
			.page {
				padding: 0.75rem 0.55rem 1rem;
			}

			.table-card {
				border-radius: 12px;
			}

			.month-form {
				grid-template-columns: 1fr;
			}

			.month-submit {
				width: 100%;
			}

			.table-tools-actions .btn.excel {
				width: 100%;
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
			if (themeValue !== "dark" && themeValue !== "light") {
				var rootTheme = String(document.documentElement.getAttribute("data-theme") || "").toLowerCase();
				if (rootTheme === "dark" || rootTheme === "light") {
					themeValue = rootTheme;
				}
			}
			if (themeValue !== "dark" && themeValue !== "light") {
				themeValue = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches
					? "dark"
					: "light";
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
			} else {
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
			<h1 class="title">Data Absensi Bulanan</h1>
			<div class="actions">
				<a href="<?php echo site_url('home'); ?>" class="btn outline">Kembali Dashboard</a>
				<a href="<?php echo site_url('home/leave_requests'); ?>" class="btn outline">Data Pengajuan</a>
				<a href="<?php echo site_url('logout'); ?>" class="btn primary">Logout</a>
			</div>
		</div>
		<p class="meta-line">Periode: <strong><?php echo htmlspecialchars($selected_month_label, ENT_QUOTES, 'UTF-8'); ?></strong> | Halaman <?php echo (int) $current_page; ?> dari <?php echo (int) $total_pages; ?>.</p>

		<div class="table-card">
			<div class="mode-tabs">
				<a href="<?php echo site_url('home/employee_data'); ?>" class="mode-link">Data Harian</a>
				<a href="<?php echo site_url('home/employee_data_monthly'); ?>" class="mode-link active">Data Bulanan</a>
				<a href="<?php echo site_url('home/employee_data_emergency'); ?>" class="mode-link">Absen Darurat</a>
			</div>
			<?php if ($total_pages > 1): ?>
				<div class="month-pager">
					<?php if ($current_page > 1): ?>
						<a href="<?php echo htmlspecialchars($build_month_page_url($current_page - 1), ENT_QUOTES, 'UTF-8'); ?>" class="pager-btn wide">Sebelumnya</a>
					<?php else: ?>
						<span class="pager-btn wide is-disabled">Sebelumnya</span>
					<?php endif; ?>
					<?php for ($page_number = $start_page; $page_number <= $end_page; $page_number += 1): ?>
						<a href="<?php echo htmlspecialchars($build_month_page_url($page_number), ENT_QUOTES, 'UTF-8'); ?>" class="pager-btn<?php echo $page_number === $current_page ? ' active' : ''; ?>"><?php echo $page_number; ?></a>
					<?php endfor; ?>
					<?php if ($current_page < $total_pages): ?>
						<a href="<?php echo htmlspecialchars($build_month_page_url($current_page + 1), ENT_QUOTES, 'UTF-8'); ?>" class="pager-btn wide">Berikutnya</a>
					<?php else: ?>
						<span class="pager-btn wide is-disabled">Berikutnya</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if (empty($rows)): ?>
				<div class="table-tools">
					<div class="table-tools-actions">
						<form method="get" action="<?php echo site_url('home/employee_data_monthly'); ?>" class="month-form">
							<input type="month" name="month" class="month-input" value="<?php echo htmlspecialchars($selected_month, ENT_QUOTES, 'UTF-8'); ?>">
							<button type="submit" class="month-submit">Terapkan</button>
						</form>
						<a href="<?php echo htmlspecialchars($export_month_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn excel">Export Excel</a>
					</div>
				</div>
				<div class="empty">Belum ada data absensi / pengajuan diterima pada bulan ini.</div>
			<?php else: ?>
				<div class="table-tools">
					<input id="monthlySearchInput" type="text" class="search-input" placeholder="Cari ID atau nama karyawan...">
					<div class="table-tools-actions">
						<form method="get" action="<?php echo site_url('home/employee_data_monthly'); ?>" class="month-form">
							<input type="month" name="month" class="month-input" value="<?php echo htmlspecialchars($selected_month, ENT_QUOTES, 'UTF-8'); ?>">
							<button type="submit" class="month-submit">Terapkan</button>
						</form>
						<a href="<?php echo htmlspecialchars($export_month_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn excel">Export Excel</a>
					</div>
				</div>
				<p class="search-help">Rumus bulanan: total telat + (alpha + izin), cuti tidak dipotong gaji.</p>
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
								<th>Telp</th>
								<th>Gaji Bulanan</th>
								<th>Hari Masuk</th>
								<th>Hari Efektif</th>
								<th>Hadir</th>
								<th>Cuti</th>
								<th>Alpha/Izin</th>
								<th>Telat 1-30 Menit</th>
								<th>Telat 31-60 Menit</th>
								<th>Telat 1-3 Jam</th>
								<th>Telat >4 Jam</th>
								<th>1-30 Menit</th>
								<th>31-60 Menit</th>
								<th>1-3 Jam</th>
								<th>&gt;4 Jam</th>
								<th>Alpha/Izin</th>
								<th>Total Potongan</th>
								<th>Gaji Bersih</th>
							</tr>
						</thead>
						<tbody id="monthlyTableBody">
							<?php $no = 1; ?>
							<?php foreach ($rows as $row): ?>
								<?php
								$username = isset($row['username']) ? (string) $row['username'] : '-';
								$pot_1_30_total = isset($row['total_1_30_amount']) ? (int) $row['total_1_30_amount'] : 0;
								$pot_31_60_total = isset($row['total_31_60_amount']) ? (int) $row['total_31_60_amount'] : 0;
								$pot_1_3_total = isset($row['total_1_3_amount']) ? (int) $row['total_1_3_amount'] : 0;
								$pot_gt_4_total = isset($row['total_gt_4_amount']) ? (int) $row['total_gt_4_amount'] : 0;
								$pot_alpha_izin_total = isset($row['total_alpha_izin_amount']) ? (int) $row['total_alpha_izin_amount'] : 0;
								$employee_id = isset($row['employee_id']) && trim((string) $row['employee_id']) !== '' ? (string) $row['employee_id'] : '-';
								$profile_photo = isset($row['profile_photo']) && trim((string) $row['profile_photo']) !== ''
									? (string) $row['profile_photo']
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
								$address = isset($row['address']) && trim((string) $row['address']) !== ''
									? (string) $row['address']
									: 'Kp. Kesekian Kalinya, Pandenglang, Banten';
								$job_title = isset($row['job_title']) && trim((string) $row['job_title']) !== ''
									? (string) $row['job_title']
									: 'Teknisi';
								$phone = isset($row['phone']) && trim((string) $row['phone']) !== ''
									? (string) $row['phone']
									: '-';
								?>
								<tr class="monthly-row" data-id="<?php echo htmlspecialchars(strtolower($employee_id), ENT_QUOTES, 'UTF-8'); ?>" data-name="<?php echo htmlspecialchars(strtolower($username), ENT_QUOTES, 'UTF-8'); ?>">
									<td><?php echo $no; ?></td>
									<td><?php echo htmlspecialchars($employee_id, ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<img class="profile-avatar" src="<?php echo htmlspecialchars($profile_photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="PP <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async">
									</td>
									<td><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="address-cell"><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($job_title, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="money">Rp <?php echo number_format((int) (isset($row['salary_monthly']) ? $row['salary_monthly'] : 0), 0, ',', '.'); ?></td>
									<td><?php echo htmlspecialchars((string) (isset($row['work_days_plan']) ? $row['work_days_plan'] : 0), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) (isset($row['hari_effective']) ? $row['hari_effective'] : 0), ENT_QUOTES, 'UTF-8'); ?></td>
									<td><span class="count-pill"><?php echo htmlspecialchars((string) (isset($row['hadir_days']) ? $row['hadir_days'] : 0), ENT_QUOTES, 'UTF-8'); ?>x</span></td>
									<td><span class="count-pill"><?php echo htmlspecialchars((string) (isset($row['cuti_days']) ? $row['cuti_days'] : 0), ENT_QUOTES, 'UTF-8'); ?>x</span></td>
									<td><span class="count-pill"><?php echo htmlspecialchars((string) (isset($row['total_alpha_izin']) ? $row['total_alpha_izin'] : 0), ENT_QUOTES, 'UTF-8'); ?>x</span></td>
									<td><span class="count-pill"><?php echo htmlspecialchars((string) (isset($row['total_telat_1_30']) ? $row['total_telat_1_30'] : 0), ENT_QUOTES, 'UTF-8'); ?>x</span></td>
									<td><span class="count-pill"><?php echo htmlspecialchars((string) (isset($row['total_telat_31_60']) ? $row['total_telat_31_60'] : 0), ENT_QUOTES, 'UTF-8'); ?>x</span></td>
									<td><span class="count-pill"><?php echo htmlspecialchars((string) (isset($row['total_telat_1_3_jam']) ? $row['total_telat_1_3_jam'] : 0), ENT_QUOTES, 'UTF-8'); ?>x</span></td>
									<td><span class="count-pill"><?php echo htmlspecialchars((string) (isset($row['total_telat_gt_4_jam']) ? $row['total_telat_gt_4_jam'] : 0), ENT_QUOTES, 'UTF-8'); ?>x</span></td>
									<td class="money minus">Rp <?php echo number_format($pot_1_30_total, 0, ',', '.'); ?></td>
									<td class="money minus">Rp <?php echo number_format($pot_31_60_total, 0, ',', '.'); ?></td>
									<td class="money minus">Rp <?php echo number_format($pot_1_3_total, 0, ',', '.'); ?></td>
									<td class="money minus">Rp <?php echo number_format($pot_gt_4_total, 0, ',', '.'); ?></td>
									<td class="money minus">Rp <?php echo number_format($pot_alpha_izin_total, 0, ',', '.'); ?></td>
									<td class="money minus">Rp <?php echo number_format((int) (isset($row['total_potongan']) ? $row['total_potongan'] : 0), 0, ',', '.'); ?></td>
									<td class="money">Rp <?php echo number_format((int) (isset($row['gaji_bersih']) ? $row['gaji_bersih'] : 0), 0, ',', '.'); ?></td>
								</tr>
								<?php $no += 1; ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div id="monthlySearchEmpty" class="empty" style="display:none;">Data karyawan tidak ditemukan.</div>
			<?php endif; ?>
		</div>
	</div>

	<script>
		(function () {
			var searchInput = document.getElementById('monthlySearchInput');
			var emptyInfo = document.getElementById('monthlySearchEmpty');
			var tableBody = document.getElementById('monthlyTableBody');
			var pager = document.querySelector('.month-pager');
			var metaLine = document.querySelector('.meta-line');
			var baseMetaText = metaLine ? String(metaLine.textContent || '') : '';
			var monthPageTargets = <?php echo json_encode($month_page_urls, JSON_UNESCAPED_SLASHES); ?>;
			var currentPage = <?php echo (int) $current_page; ?>;

			if (!searchInput || !tableBody) {
				return;
			}

			var initialRows = Array.prototype.slice.call(tableBody.querySelectorAll('.monthly-row')).map(function (row) {
				return row.cloneNode(true);
			});
			var allRows = initialRows.slice();
			var allRowsLoaded = false;
			var loadingPromise = null;
			var inputDelayTimer = null;
			var searchToken = 0;

			var setMetaText = function (text) {
				if (metaLine) {
					metaLine.textContent = text;
				}
			};

			var setSearchState = function (isActive) {
				if (pager) {
					pager.style.display = isActive ? 'none' : '';
				}
				if (!isActive) {
					setMetaText(baseMetaText);
				}
			};

			var renderRows = function (rowsToRender) {
				tableBody.innerHTML = '';
				for (var i = 0; i < rowsToRender.length; i += 1) {
					var rowClone = rowsToRender[i].cloneNode(true);
					var firstCell = rowClone.querySelector('td');
					if (firstCell) {
						firstCell.textContent = String(i + 1);
					}
					tableBody.appendChild(rowClone);
				}
				if (emptyInfo) {
					emptyInfo.style.display = rowsToRender.length > 0 ? 'none' : 'block';
				}
			};

			var loadRowsFromAllMonths = function () {
				if (allRowsLoaded) {
					return Promise.resolve(allRows);
				}
				if (loadingPromise) {
					return loadingPromise;
				}

				loadingPromise = new Promise(function (resolve) {
					var targets = [];
					for (var i = 0; i < monthPageTargets.length; i += 1) {
						var target = monthPageTargets[i];
						var pageNumber = parseInt(target.page, 10);
						if (isNaN(pageNumber) || pageNumber === currentPage) {
							continue;
						}
						targets.push(target);
					}

					if (!targets.length) {
						allRowsLoaded = true;
						loadingPromise = null;
						resolve(allRows);
						return;
					}

					var loadNext = function (targetIndex) {
						if (targetIndex >= targets.length) {
							allRowsLoaded = true;
							loadingPromise = null;
							resolve(allRows);
							return;
						}

						var target = targets[targetIndex];
						fetch(String(target.url || ''), {
							credentials: 'same-origin'
						})
						.then(function (response) {
							if (!response.ok) {
								throw new Error('HTTP ' + response.status);
							}
							return response.text();
						})
						.then(function (html) {
							var parser = new DOMParser();
							var doc = parser.parseFromString(html, 'text/html');
							var fetchedRows = doc.querySelectorAll('#monthlyTableBody .monthly-row');
							for (var rowIndex = 0; rowIndex < fetchedRows.length; rowIndex += 1) {
								allRows.push(fetchedRows[rowIndex].cloneNode(true));
							}
							loadNext(targetIndex + 1);
						})
						.catch(function (error) {
							console.error('Gagal memuat data bulanan halaman ' + String(target.page || ''), error);
							loadNext(targetIndex + 1);
						});
					};

					loadNext(0);
				});

				return loadingPromise;
			};

			var filterRows = function (token) {
				var keyword = String(searchInput.value || '').toLowerCase().trim();
				if (keyword === '') {
					setSearchState(false);
					renderRows(initialRows);
					return;
				}

				setSearchState(true);
				setMetaText('Pencarian semua bulan: memuat data...');

				loadRowsFromAllMonths().then(function (rows) {
					if (token !== searchToken) {
						return;
					}

					var filteredRows = [];
					for (var i = 0; i < rows.length; i += 1) {
						var row = rows[i];
						var idValue = String(row.getAttribute('data-id') || '');
						var nameValue = String(row.getAttribute('data-name') || '');
						if (idValue.indexOf(keyword) !== -1 || nameValue.indexOf(keyword) !== -1) {
							filteredRows.push(row);
						}
					}

					renderRows(filteredRows);
					setMetaText(filteredRows.length > 0
						? ('Pencarian semua bulan: menampilkan ' + filteredRows.length + ' data')
						: 'Pencarian semua bulan: data tidak ditemukan.');
				});
			};

			searchInput.addEventListener('input', function () {
				searchToken += 1;
				var activeToken = searchToken;
				if (inputDelayTimer !== null) {
					window.clearTimeout(inputDelayTimer);
				}
				inputDelayTimer = window.setTimeout(function () {
					filterRows(activeToken);
				}, 180);
			});

			renderRows(initialRows);
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
