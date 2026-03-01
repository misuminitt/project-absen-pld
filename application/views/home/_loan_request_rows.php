<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$requests_for_render = isset($requests_for_render) && is_array($requests_for_render) ? $requests_for_render : array();
$render_start_no = isset($render_start_no) ? (int) $render_start_no : 1;
if ($render_start_no < 1)
{
	$render_start_no = 1;
}
$loan_current_page_for_render = isset($loan_current_page_for_render) ? (int) $loan_current_page_for_render : 1;
if ($loan_current_page_for_render < 1)
{
	$loan_current_page_for_render = 1;
}
$loan_mode_for_render = isset($loan_mode_for_render) ? strtolower(trim((string) $loan_mode_for_render)) : 'daily';
if ($loan_mode_for_render !== 'monthly')
{
	$loan_mode_for_render = 'daily';
}
$can_process_loan_requests = isset($can_process_loan_requests) && $can_process_loan_requests === TRUE;
$can_delete_loan_requests = isset($can_delete_loan_requests) && $can_delete_loan_requests === TRUE;
$no = $render_start_no;
?>
<?php foreach ($requests_for_render as $row): ?>
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
	$is_waiting = $status_raw === 'menunggu' || $status_raw === 'pending' || $status_raw === 'waiting';
	$lunas_label = '-';
	$lunas_class = 'waiting';
	if (!$is_waiting)
	{
		$sheet_keterangan = isset($row['source_sheet_keterangan']) ? trim((string) $row['source_sheet_keterangan']) : '';
		if ($sheet_keterangan === '')
		{
			$status_note_text = isset($row['status_note']) ? trim((string) $row['status_note']) : '';
			$status_note_upper = function_exists('mb_strtoupper') ? mb_strtoupper($status_note_text, 'UTF-8') : strtoupper($status_note_text);
			if (strpos((string) $status_note_upper, 'BELUM LUNAS') !== FALSE)
			{
				$sheet_keterangan = 'BELUM LUNAS';
			}
			elseif (strpos((string) $status_note_upper, 'LUNAS') !== FALSE)
			{
				$sheet_keterangan = 'LUNAS';
			}
		}
		$sheet_keterangan = function_exists('mb_strtoupper') ? mb_strtoupper((string) $sheet_keterangan, 'UTF-8') : strtoupper((string) $sheet_keterangan);
		if ($sheet_keterangan === 'LUNAS')
		{
			$lunas_label = 'LUNAS';
			$lunas_class = 'approved';
		}
		elseif ($sheet_keterangan === 'BELUM LUNAS')
		{
			$lunas_label = 'BELUM LUNAS';
			$lunas_class = 'rejected';
		}
	}
	$phone_value = isset($row['phone']) && trim((string) $row['phone']) !== '' ? (string) $row['phone'] : '-';
	$employee_id_value = isset($row['employee_id']) && trim((string) $row['employee_id']) !== '' ? (string) $row['employee_id'] : '-';
	$profile_photo_value = isset($row['profile_photo']) && trim((string) $row['profile_photo']) !== ''
		? (string) $row['profile_photo']
		: (is_file(FCPATH.'src/assets/fotoku.webp') ? '/src/assets/fotoku.webp' : '/src/assets/fotoku.JPG');
	$profile_photo_url = $profile_photo_value;
	if (strpos($profile_photo_url, 'data:') !== 0 && preg_match('/^https?:\/\//i', $profile_photo_url) !== 1)
	{
		$profile_photo_relative = ltrim($profile_photo_url, '/\\');
		$profile_photo_info = pathinfo($profile_photo_relative);
		$profile_photo_thumb_relative = '';
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
			$profile_photo_relative = $profile_photo_thumb_relative;
		}
		$profile_photo_url = base_url(ltrim($profile_photo_relative, '/'));
	}
	$job_title_value = isset($row['job_title']) && trim((string) $row['job_title']) !== '' ? (string) $row['job_title'] : 'Teknisi';
	$request_id = isset($row['id']) ? (string) $row['id'] : '';
	?>
	<tr class="loan-row" data-id="<?php echo htmlspecialchars(strtolower($employee_id_value), ENT_QUOTES, 'UTF-8'); ?>" data-name="<?php echo htmlspecialchars(strtolower((string) (isset($row['username']) ? $row['username'] : '')), ENT_QUOTES, 'UTF-8'); ?>" data-phone="<?php echo htmlspecialchars(strtolower($phone_value), ENT_QUOTES, 'UTF-8'); ?>">
		<td class="row-no"><?php echo $no; ?></td>
		<td><?php echo htmlspecialchars($employee_id_value, ENT_QUOTES, 'UTF-8'); ?></td>
		<td>
			<img class="profile-avatar" src="<?php echo htmlspecialchars($profile_photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="PP <?php echo htmlspecialchars(isset($row['username']) ? (string) $row['username'] : 'Karyawan', ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async">
		</td>
		<td><?php echo htmlspecialchars(isset($row['username']) ? (string) $row['username'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
		<td><span class="phone"><?php echo htmlspecialchars($phone_value, ENT_QUOTES, 'UTF-8'); ?></span></td>
		<td><?php echo htmlspecialchars($job_title_value, ENT_QUOTES, 'UTF-8'); ?></td>
		<td><?php echo htmlspecialchars(isset($row['request_date_label']) ? (string) $row['request_date_label'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
		<td><span class="amount"><?php echo htmlspecialchars(isset($row['amount_label']) ? (string) $row['amount_label'] : 'Rp 0', ENT_QUOTES, 'UTF-8'); ?></span></td>
		<td><span class="reason"><?php echo htmlspecialchars(isset($row['reason']) ? (string) $row['reason'] : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
		<td><span class="reason"><?php echo htmlspecialchars(isset($row['transparency']) ? (string) $row['transparency'] : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
		<td><span class="status-chip <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8'); ?></span></td>
		<td><span class="status-chip <?php echo htmlspecialchars($lunas_class, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lunas_label, ENT_QUOTES, 'UTF-8'); ?></span></td>
		<td>
			<div class="admin-actions">
				<?php if ($can_process_loan_requests && $is_waiting && $request_id !== ''): ?>
					<form method="post" action="<?php echo site_url('home/update_loan_request_status'); ?>" class="admin-action-form">
						<input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request_id, ENT_QUOTES, 'UTF-8'); ?>">
						<input type="hidden" name="return_page" value="<?php echo (int) $loan_current_page_for_render; ?>">
						<input type="hidden" name="return_mode" value="<?php echo htmlspecialchars($loan_mode_for_render, ENT_QUOTES, 'UTF-8'); ?>">
						<button type="submit" name="status" value="diterima" class="admin-btn approve">Terima</button>
						<button type="submit" name="status" value="ditolak" class="admin-btn reject">Tolak</button>
					</form>
				<?php else: ?>
					<span class="processed-label"><?php echo $is_waiting ? 'Akses dibatasi' : 'Sudah diproses'; ?></span>
				<?php endif; ?>
				<?php if ($can_delete_loan_requests && $request_id !== ''): ?>
					<form method="post" action="<?php echo site_url('home/delete_loan_request'); ?>" class="admin-action-form" onsubmit="return window.confirm('Hapus data pinjaman ini?');">
						<input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request_id, ENT_QUOTES, 'UTF-8'); ?>">
						<input type="hidden" name="return_page" value="<?php echo (int) $loan_current_page_for_render; ?>">
						<input type="hidden" name="return_mode" value="<?php echo htmlspecialchars($loan_mode_for_render, ENT_QUOTES, 'UTF-8'); ?>">
						<button type="submit" class="admin-btn delete">Hapus</button>
					</form>
				<?php endif; ?>
			</div>
		</td>
	</tr>
	<?php $no += 1; ?>
<?php endforeach; ?>
