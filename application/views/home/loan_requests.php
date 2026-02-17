<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $requests = isset($requests) && is_array($requests) ? $requests : array(); ?>
<?php
$notice_success = $this->session->flashdata('loan_notice_success');
$notice_warning = $this->session->flashdata('loan_notice_warning');
$notice_error = $this->session->flashdata('loan_notice_error');
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Pengajuan Pinjaman'; ?></title>
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

		.processed-label {
			font-size: 0.74rem;
			font-weight: 700;
			color: #5f7591;
		}
	</style>
</head>
<body>
	<div class="page">
		<div class="head">
			<h1 class="title">Pengajuan Pinjaman</h1>
			<div class="actions">
				<a href="<?php echo site_url('home'); ?>" class="btn outline">Kembali Dashboard</a>
				<a href="<?php echo site_url('home/leave_requests'); ?>" class="btn outline">Data Cuti / Izin</a>
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
			<?php if (empty($requests)): ?>
				<div class="empty">Belum ada pengajuan pinjaman dari karyawan.</div>
			<?php else: ?>
				<div class="table-tools">
					<input id="loanSearchInput" type="text" class="search-input" placeholder="Cari ID, nama, atau no telp karyawan...">
					<p class="search-help">Pencarian berlaku untuk kolom ID, Nama, dan Telp.</p>
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
								<th>Aksi Admin</th>
							</tr>
						</thead>
						<tbody id="loanRequestTableBody">
							<?php $no = 1; ?>
							<?php foreach ($requests as $row): ?>
								<?php
								$status_raw = isset($row['status']) ? strtolower(trim((string) $row['status'])) : 'menunggu';
								$status_class = 'waiting';
								if ($status_raw === 'disetujui' || $status_raw === 'approved' || $status_raw === 'diterima')
								{
									$status_class = 'approved';
								}
								elseif ($status_raw === 'ditolak' || $status_raw === 'rejected')
								{
									$status_class = 'rejected';
								}
								$status_label = isset($row['status']) && trim((string) $row['status']) !== '' ? (string) $row['status'] : 'Menunggu';
								$phone_value = isset($row['phone']) && trim((string) $row['phone']) !== '' ? (string) $row['phone'] : '-';
								$employee_id_value = isset($row['employee_id']) && trim((string) $row['employee_id']) !== '' ? (string) $row['employee_id'] : '-';
								$profile_photo_value = isset($row['profile_photo']) && trim((string) $row['profile_photo']) !== ''
									? (string) $row['profile_photo']
									: '/src/assets/fotoku.JPG';
								$profile_photo_url = $profile_photo_value;
								if (strpos($profile_photo_url, 'data:') !== 0 && preg_match('/^https?:\/\//i', $profile_photo_url) !== 1)
								{
									$profile_photo_url = base_url(ltrim($profile_photo_url, '/'));
								}
								$job_title_value = isset($row['job_title']) && trim((string) $row['job_title']) !== '' ? (string) $row['job_title'] : 'Teknisi';
								$request_id = isset($row['id']) ? (string) $row['id'] : '';
								$is_waiting = $status_raw === 'menunggu' || $status_raw === 'pending' || $status_raw === 'waiting';
								?>
								<tr class="loan-row" data-id="<?php echo htmlspecialchars(strtolower($employee_id_value), ENT_QUOTES, 'UTF-8'); ?>" data-name="<?php echo htmlspecialchars(strtolower((string) (isset($row['username']) ? $row['username'] : '')), ENT_QUOTES, 'UTF-8'); ?>" data-phone="<?php echo htmlspecialchars(strtolower($phone_value), ENT_QUOTES, 'UTF-8'); ?>">
									<td><?php echo $no; ?></td>
									<td><?php echo htmlspecialchars($employee_id_value, ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<img class="profile-avatar" src="<?php echo htmlspecialchars($profile_photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="PP <?php echo htmlspecialchars(isset($row['username']) ? (string) $row['username'] : 'Karyawan', ENT_QUOTES, 'UTF-8'); ?>">
									</td>
									<td><?php echo htmlspecialchars(isset($row['username']) ? (string) $row['username'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><span class="phone"><?php echo htmlspecialchars($phone_value, ENT_QUOTES, 'UTF-8'); ?></span></td>
									<td><?php echo htmlspecialchars($job_title_value, ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($row['request_date_label']) ? (string) $row['request_date_label'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><span class="amount"><?php echo htmlspecialchars(isset($row['amount_label']) ? (string) $row['amount_label'] : 'Rp 0', ENT_QUOTES, 'UTF-8'); ?></span></td>
									<td><span class="reason"><?php echo htmlspecialchars(isset($row['reason']) ? (string) $row['reason'] : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
									<td><span class="reason"><?php echo htmlspecialchars(isset($row['transparency']) ? (string) $row['transparency'] : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
									<td><span class="status-chip <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8'); ?></span></td>
									<td>
										<div class="admin-actions">
											<?php if ($is_waiting && $request_id !== ''): ?>
												<form method="post" action="<?php echo site_url('home/update_loan_request_status'); ?>" class="admin-action-form">
													<input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request_id, ENT_QUOTES, 'UTF-8'); ?>">
													<button type="submit" name="status" value="diterima" class="admin-btn approve">Terima</button>
													<button type="submit" name="status" value="ditolak" class="admin-btn reject">Tolak</button>
												</form>
											<?php else: ?>
												<span class="processed-label">Sudah diproses</span>
											<?php endif; ?>
										</div>
									</td>
								</tr>
								<?php $no += 1; ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div id="loanSearchEmpty" class="empty" style="display:none;">Data pengajuan tidak ditemukan.</div>
			<?php endif; ?>
		</div>
	</div>
	<script>
		(function () {
			var searchInput = document.getElementById('loanSearchInput');
			var emptyInfo = document.getElementById('loanSearchEmpty');
			var rows = document.querySelectorAll('#loanRequestTableBody .loan-row');

			if (!searchInput || !rows.length) {
				return;
			}

			var filterRows = function () {
				var keyword = String(searchInput.value || '').toLowerCase().trim();
				var visibleCount = 0;

				for (var i = 0; i < rows.length; i += 1) {
					var row = rows[i];
					var idValue = String(row.getAttribute('data-id') || '');
					var nameValue = String(row.getAttribute('data-name') || '');
					var phoneValue = String(row.getAttribute('data-phone') || '');
					var matched = keyword === '' || idValue.indexOf(keyword) !== -1 || nameValue.indexOf(keyword) !== -1 || phoneValue.indexOf(keyword) !== -1;
					row.style.display = matched ? '' : 'none';
					if (matched) {
						visibleCount += 1;
					}
				}

				if (emptyInfo) {
					emptyInfo.style.display = visibleCount > 0 ? 'none' : 'block';
				}
			};

			searchInput.addEventListener('input', filterRows);
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
