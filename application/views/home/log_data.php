<?php
$username = isset($username) ? (string) $username : 'Admin';
$dashboard_navbar_title = isset($dashboard_navbar_title) && trim((string) $dashboard_navbar_title) !== ''
	? trim((string) $dashboard_navbar_title)
	: 'Dashboard Admin Absen';
$logs = isset($logs) && is_array($logs) ? array_values($logs) : array();
$log_notice_success = isset($log_notice_success) ? trim((string) $log_notice_success) : '';
$log_notice_error = isset($log_notice_error) ? trim((string) $log_notice_error) : '';
$pagination = isset($pagination) && is_array($pagination) ? $pagination : array();
$current_page = isset($pagination['current_page']) ? (int) $pagination['current_page'] : 1;
$total_pages = isset($pagination['total_pages']) ? (int) $pagination['total_pages'] : 1;
$total_logs = isset($pagination['total_logs']) ? (int) $pagination['total_logs'] : count($logs);
$per_page = isset($pagination['per_page']) ? (int) $pagination['per_page'] : 20;
if ($current_page < 1)
{
	$current_page = 1;
}
if ($total_pages < 1)
{
	$total_pages = 1;
}
$page_url_base = site_url('home/log_data');
$start_item = $total_logs > 0 ? (($current_page - 1) * $per_page) + 1 : 0;
$end_item = $start_item > 0 ? min($start_item + count($logs) - 1, $total_logs) : 0;
$page_window = 5;
$start_page = max(1, $current_page - (int) floor($page_window / 2));
$end_page = min($total_pages, $start_page + $page_window - 1);
if (($end_page - $start_page + 1) < $page_window)
{
	$start_page = max(1, $end_page - $page_window + 1);
}
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
	<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Log Data Aktivitas'; ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<style>
		:root {
			--brand-dark: #083c68;
			--brand-main: #0f5c93;
			--text-main: #0d2238;
			--text-soft: #4d637a;
			--line-soft: #dbe7f3;
			--surface: #ffffff;
		}

		* {
			box-sizing: border-box;
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
			flex-wrap: wrap;
			justify-content: flex-end;
		}

		.nav-btn,
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

		.nav-btn:hover,
		.logout:hover {
			background: rgba(255, 255, 255, 0.2);
		}

		.page {
			max-width: 1320px;
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
			font-size: 1.32rem;
			font-weight: 800;
			letter-spacing: -0.01em;
		}

		.hero p {
			margin: 0.48rem 0 0;
			font-size: 0.9rem;
			color: var(--text-soft);
			line-height: 1.52;
		}

		.notice-box {
			margin-top: 0.85rem;
			padding: 0.7rem 0.82rem;
			border-radius: 10px;
			font-size: 0.82rem;
			font-weight: 600;
		}

		.notice-box.success {
			background: #e7f8f0;
			color: #176a46;
			border: 1px solid #b9e8cf;
		}

		.notice-box.error {
			background: #fff2f2;
			color: #a73a3a;
			border: 1px solid #f4caca;
		}

		.table-card {
			background: var(--surface);
			border: 1px solid var(--line-soft);
			border-radius: 16px;
			box-shadow: 0 8px 20px rgba(7, 49, 84, 0.06);
			overflow: hidden;
		}

		.table-wrap {
			overflow-x: auto;
			cursor: grab;
			scrollbar-width: none;
			-ms-overflow-style: none;
		}

		.table-wrap::-webkit-scrollbar {
			width: 0;
			height: 0;
		}

		.table-wrap.is-dragging {
			user-select: none;
			cursor: grabbing;
		}

		table {
			width: 100%;
			border-collapse: collapse;
			min-width: 2300px;
		}

		th,
		td {
			padding: 0.62rem 0.65rem;
			border-bottom: 1px solid #e9f2fb;
			text-align: left;
			vertical-align: top;
			font-size: 0.8rem;
			white-space: nowrap;
		}

		th {
			background: #f4f9ff;
			color: #1d476f;
			font-weight: 800;
			position: sticky;
			top: 0;
			z-index: 1;
		}

		tbody tr:hover {
			background: #fbfdff;
		}

		.code {
			font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
			font-size: 0.78rem;
			color: #1f4d77;
		}

		.entry-id {
			color: #173f63;
		}

		.empty {
			padding: 1rem;
			font-size: 0.9rem;
			color: var(--text-soft);
		}

		.value-box {
			min-width: 170px;
			max-width: 270px;
			white-space: normal;
			word-break: break-word;
		}

		.note-box {
			min-width: 260px;
			max-width: 360px;
			white-space: normal;
			word-break: break-word;
		}

		.rollback-col {
			min-width: 138px;
		}

		.rollback-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			border: 1px solid #1e6db8;
			background: #f2f8ff;
			color: #124777;
			border-radius: 0.6rem;
			padding: 0.34rem 0.62rem;
			font-size: 0.75rem;
			font-weight: 700;
			cursor: pointer;
		}

		.rollback-btn:hover {
			background: #e8f2ff;
		}

		.rollback-status {
			display: inline-block;
			padding: 0.3rem 0.55rem;
			border-radius: 999px;
			font-size: 0.72rem;
			font-weight: 700;
			line-height: 1;
		}

		.rollback-status.done {
			background: #e8f8ef;
			color: #1b7f53;
			border: 1px solid #b6e8ce;
		}

		.rollback-status.na {
			background: #f2f5f9;
			color: #506579;
			border: 1px solid #d6e1ec;
		}

		.name-box {
			min-width: 180px;
			white-space: normal;
		}

		.computer-box {
			min-width: 160px;
			white-space: normal;
			word-break: break-word;
		}

		.table-meta {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.7rem;
			padding: 0.8rem 1rem;
			font-size: 0.82rem;
			color: var(--text-soft);
			border-top: 1px solid #e9f2fb;
			background: #fbfdff;
		}

		.pager {
			display: flex;
			align-items: center;
			gap: 0.46rem;
			padding: 0.8rem 1rem 1rem;
			flex-wrap: wrap;
		}

		.pager-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: 2.9rem;
			height: 2.7rem;
			padding: 0 0.88rem;
			border-radius: 0.8rem;
			border: 1px solid #b9cfe4;
			color: #1c4670;
			background: #f8fbff;
			font-weight: 700;
			font-size: 0.82rem;
			text-decoration: none;
			transition: all 0.2s ease;
		}

		.pager-btn:hover {
			background: #ebf4ff;
		}

		.pager-btn.active {
			background: linear-gradient(180deg, #1f6fbd 0%, #0f5c93 100%);
			border-color: #0f5c93;
			color: #ffffff;
		}

		.pager-btn.wide {
			min-width: 6.2rem;
			padding: 0 1rem;
		}

		@media (max-width: 860px) {
			.brand-text {
				display: none;
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

			.nav-btn,
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

			.table-meta {
				flex-direction: column;
				align-items: flex-start;
				padding: 0.62rem 0.72rem;
				gap: 0.3rem;
			}

			.pager {
				padding: 0.62rem 0.72rem 0.72rem;
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

			.top-actions {
				grid-template-columns: 1fr;
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

			.nav-btn,
			.logout {
				width: auto;
				min-width: 0;
				padding: 0.42rem 0.7rem;
				font-size: 0.73rem;
			}
		}

		@media (max-width: 520px) {
			.top-actions .nav-btn,
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
					<a href="<?php echo site_url('home'); ?>" class="nav-btn">Dashboard</a>
					<a href="<?php echo site_url('home/cara_pakai'); ?>" class="nav-btn">Cara Pakai</a>
					<a href="<?php echo site_url('logout'); ?>" class="logout">Logout</a>
				</div>
			</div>
		</div>
	</nav>

	<main class="page">
		<section class="hero">
			<h1>Log Data Aktivitas - <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></h1>
			<p>Berisi aktivitas perubahan data web/sheet/akun dan konflik sinkronisasi. Gunakan log ini untuk audit, identifikasi pelaku, dan rollback bila diperlukan.</p>
			<?php if ($log_notice_success !== ''): ?>
				<div class="notice-box success"><?php echo htmlspecialchars($log_notice_success, ENT_QUOTES, 'UTF-8'); ?></div>
			<?php endif; ?>
			<?php if ($log_notice_error !== ''): ?>
				<div class="notice-box error"><?php echo htmlspecialchars($log_notice_error, ENT_QUOTES, 'UTF-8'); ?></div>
			<?php endif; ?>
		</section>

		<section class="table-card">
			<?php if (empty($logs)): ?>
				<div class="empty">Belum ada data log aktivitas.</div>
			<?php else: ?>
				<div class="table-wrap">
					<table>
						<thead>
							<tr>
								<th>Entry ID</th>
								<th>Tipe</th>
								<th>Waktu</th>
								<th>Sumber</th>
								<th>Aktor</th>
								<th>IP</th>
								<th>Komputer</th>
								<th>MAC</th>
								<th>Username</th>
								<th>Nama</th>
								<th>Target ID</th>
								<th>Field</th>
								<th>Nilai Lama</th>
								<th>Nilai Baru</th>
								<th>Aksi</th>
								<th>Sheet</th>
								<th>Row</th>
								<th>Catatan</th>
								<th>Rollback</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($logs as $row): ?>
								<?php
								$log_type = isset($row['log_type']) && trim((string) $row['log_type']) !== ''
									? (string) $row['log_type']
									: 'conflict';
								$logged_at = isset($row['logged_at']) ? (string) $row['logged_at'] : '';
								$source = isset($row['source']) ? (string) $row['source'] : '-';
								$actor = isset($row['actor']) ? (string) $row['actor'] : '-';
								$ip_address = isset($row['ip_address']) ? (string) $row['ip_address'] : '';
								$computer_name = isset($row['computer_name']) ? (string) $row['computer_name'] : '';
								$mac_address = isset($row['mac_address']) ? (string) $row['mac_address'] : '';
								$username_row = isset($row['username']) ? (string) $row['username'] : '-';
								$display_name = isset($row['display_name']) ? (string) $row['display_name'] : '-';
								$target_id = isset($row['target_id']) ? (string) $row['target_id'] : '';
								$field_label = isset($row['field_label']) && trim((string) $row['field_label']) !== ''
									? (string) $row['field_label']
									: (isset($row['field']) ? (string) $row['field'] : '-');
								$old_value = isset($row['old_value']) ? (string) $row['old_value'] : '';
								$new_value = isset($row['new_value']) ? (string) $row['new_value'] : '';
								$action = isset($row['action']) ? (string) $row['action'] : '-';
								$sheet = isset($row['sheet']) ? (string) $row['sheet'] : '-';
								$row_number = isset($row['row_number']) ? (int) $row['row_number'] : 0;
								$note = isset($row['note']) ? (string) $row['note'] : '';
								$entry_id = isset($row['entry_id']) ? (string) $row['entry_id'] : '';
								$entry_field = isset($row['field']) ? trim((string) $row['field']) : '';
								$rollback_status = strtolower(trim((string) (isset($row['rollback_status']) ? $row['rollback_status'] : '')));
								$rollback_note = isset($row['rollback_note']) ? trim((string) $row['rollback_note']) : '';
								$rollback_allowed_fields = array('display_name', 'branch', 'phone', 'shift_name', 'salary_monthly', 'work_days', 'job_title', 'address');
								$can_rollback = $rollback_status !== 'rolled_back' &&
									strtolower($source) === 'account_data' &&
									(
										(strtolower($action) === 'update_account_field' && in_array($entry_field, $rollback_allowed_fields, TRUE)) ||
										(strtolower($action) === 'update_account_username' && $entry_field === 'username')
									);
								?>
								<tr>
									<td class="code entry-id"><?php echo htmlspecialchars($entry_id !== '' ? $entry_id : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="code"><?php echo htmlspecialchars($log_type, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($logged_at, ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="code"><?php echo htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="code"><?php echo htmlspecialchars($actor, ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="code"><?php echo htmlspecialchars($ip_address !== '' ? $ip_address : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="computer-box"><?php echo htmlspecialchars($computer_name !== '' ? $computer_name : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="code"><?php echo htmlspecialchars($mac_address !== '' ? $mac_address : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="code"><?php echo htmlspecialchars($username_row, ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="name-box"><?php echo htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="code"><?php echo htmlspecialchars($target_id !== '' ? $target_id : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($field_label, ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="value-box"><?php echo htmlspecialchars($old_value !== '' ? $old_value : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="value-box"><?php echo htmlspecialchars($new_value !== '' ? $new_value : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="code"><?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars($sheet !== '' ? $sheet : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo $row_number > 0 ? $row_number : '-'; ?></td>
									<td class="note-box"><?php echo htmlspecialchars($note !== '' ? $note : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td class="rollback-col">
										<?php if ($rollback_status === 'rolled_back'): ?>
											<span class="rollback-status done" title="<?php echo htmlspecialchars($rollback_note !== '' ? $rollback_note : 'Sudah rollback', ENT_QUOTES, 'UTF-8'); ?>">Sudah Rollback</span>
										<?php elseif ($can_rollback): ?>
											<form method="post" action="<?php echo site_url('home/rollback_log_entry'); ?>" onsubmit="return confirm('Rollback data pada log ini?');">
												<input type="hidden" name="entry_id" value="<?php echo htmlspecialchars($entry_id, ENT_QUOTES, 'UTF-8'); ?>">
												<input type="hidden" name="page" value="<?php echo (int) $current_page; ?>">
												<button type="submit" class="rollback-btn">Rollback</button>
											</form>
										<?php else: ?>
											<span class="rollback-status na">Tidak Tersedia</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="table-meta">
					<span>Menampilkan <?php echo (int) $start_item; ?> - <?php echo (int) $end_item; ?> dari <?php echo (int) $total_logs; ?> log</span>
					<span>Halaman <?php echo (int) $current_page; ?> / <?php echo (int) $total_pages; ?></span>
				</div>
				<?php if ($total_pages > 1): ?>
					<div class="pager">
						<?php if ($current_page > 1): ?>
							<a class="pager-btn wide" href="<?php echo htmlspecialchars($page_url_base.'?page='.($current_page - 1), ENT_QUOTES, 'UTF-8'); ?>">Sebelumnya</a>
						<?php endif; ?>
						<?php for ($page_no = $start_page; $page_no <= $end_page; $page_no += 1): ?>
							<a class="pager-btn <?php echo $page_no === $current_page ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($page_url_base.'?page='.$page_no, ENT_QUOTES, 'UTF-8'); ?>">
								<?php echo (int) $page_no; ?>
							</a>
						<?php endfor; ?>
						<?php if ($current_page < $total_pages): ?>
							<a class="pager-btn wide" href="<?php echo htmlspecialchars($page_url_base.'?page='.($current_page + 1), ENT_QUOTES, 'UTF-8'); ?>">Selanjutnya</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</section>
	</main>
	<script>
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
