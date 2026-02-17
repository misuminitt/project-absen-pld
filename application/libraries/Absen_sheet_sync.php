<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Absen_sheet_sync {
	protected $CI;
	protected $config = array();
	protected $access_token = '';
	protected $access_token_expire_at = 0;
	protected $sheet_context = NULL;

	public function __construct($params = array())
	{
		$this->CI =& get_instance();
		$this->CI->load->helper('absen_account_store');
		$this->CI->load->config('absen_sheet_sync', TRUE);
		$config = $this->CI->config->item('absen_sheet_sync', 'absen_sheet_sync');
		if (!is_array($config))
		{
			$config = array();
		}

		$defaults = array(
			'enabled' => FALSE,
			'spreadsheet_id' => '',
			'sheet_gid' => 0,
			'sheet_title' => '',
			'credential_json_path' => '',
			'credential_json_raw' => '',
			'sync_interval_seconds' => 60,
			'request_timeout_seconds' => 15,
			'default_user_password' => '123',
			'writeback_on_web_change' => FALSE,
			'attendance_sync_enabled' => TRUE,
			'attendance_sheet_gid' => 0,
			'attendance_sheet_title' => '',
			'attendance_sync_interval_seconds' => 60,
			'state_file' => APPPATH.'cache/sheet_sync_state.json',
			'log_prefix' => '[SheetSync] ',
			'field_labels' => array(),
			'header_aliases' => array()
		);

		if (is_array($params))
		{
			$config = array_merge($config, $params);
		}

		$this->config = array_merge($defaults, $config);
	}

	public function is_enabled()
	{
		return isset($this->config['enabled']) && $this->config['enabled'] === TRUE;
	}
	public function sync_accounts_from_sheet($options = array())
	{
		$force = isset($options['force']) && $options['force'] === TRUE;
		if (!$this->is_enabled())
		{
			return array(
				'success' => FALSE,
				'skipped' => TRUE,
				'message' => 'Sync spreadsheet dinonaktifkan.'
			);
		}

		$state = $this->read_sync_state();
		$interval_seconds = isset($this->config['sync_interval_seconds'])
			? (int) $this->config['sync_interval_seconds']
			: 60;
		if ($interval_seconds < 0)
		{
			$interval_seconds = 0;
		}

		$last_pull_at = isset($state['last_pull_at']) ? (int) $state['last_pull_at'] : 0;
		if (!$force && $interval_seconds > 0 && $last_pull_at > 0 && (time() - $last_pull_at) < $interval_seconds)
		{
			return array(
				'success' => TRUE,
				'skipped' => TRUE,
				'message' => 'Menunggu interval sync berikutnya.'
			);
		}

		$context_result = $this->resolve_sheet_context(FALSE);
		if (!$context_result['success'])
		{
			$this->write_sync_state(array(
				'last_error_at' => time(),
				'last_error_message' => isset($context_result['message']) ? (string) $context_result['message'] : 'Gagal membaca context spreadsheet.'
			));
			return $context_result;
		}

		$context = isset($context_result['data']) && is_array($context_result['data'])
			? $context_result['data']
			: array();
		$sheet_title = isset($context['sheet_title']) ? (string) $context['sheet_title'] : '';
		$header_row_number = isset($context['header_row_number']) ? (int) $context['header_row_number'] : 1;
		if ($header_row_number <= 0)
		{
			$header_row_number = 1;
		}
		$data_start_row = $header_row_number + 1;
		$field_indexes = isset($context['field_indexes']) && is_array($context['field_indexes'])
			? $context['field_indexes']
			: array();

		$rows_result = $this->sheet_values_get($sheet_title, 'A'.$data_start_row.':ZZ');
		if (!$rows_result['success'])
		{
			$this->write_sync_state(array(
				'last_error_at' => time(),
				'last_error_message' => isset($rows_result['message']) ? (string) $rows_result['message'] : 'Gagal membaca data spreadsheet.'
			));
			return $rows_result;
		}

		$values = isset($rows_result['data']['values']) && is_array($rows_result['data']['values'])
			? $rows_result['data']['values']
			: array();
		$salary_profiles = function_exists('absen_salary_profile_book') ? absen_salary_profile_book() : array();
		$shift_profiles = function_exists('absen_shift_profile_book') ? absen_shift_profile_book() : array();
		$default_shift = isset($shift_profiles['pagi']) && is_array($shift_profiles['pagi'])
			? $shift_profiles['pagi']
			: array('shift_name' => 'Shift Pagi - Sore', 'shift_time' => '08:00 - 17:00');
		$default_password = isset($this->config['default_user_password']) && trim((string) $this->config['default_user_password']) !== ''
			? (string) $this->config['default_user_password']
			: '123';

		$account_book = function_exists('absen_load_account_book') ? absen_load_account_book() : array();
		if (!is_array($account_book))
		{
			$account_book = array();
		}

		$row_to_username = array();
		$name_to_username = array();
		$used_usernames = array();
		foreach ($account_book as $username_key => $row)
		{
			$username_normalized = strtolower(trim((string) $username_key));
			if ($username_normalized === '')
			{
				continue;
			}

			$used_usernames[$username_normalized] = TRUE;
			if (!is_array($row))
			{
				continue;
			}

			$role = strtolower(trim((string) (isset($row['role']) ? $row['role'] : 'user')));
			if ($role !== 'user')
			{
				continue;
			}

			$sheet_row = isset($row['sheet_row']) ? (int) $row['sheet_row'] : 0;
			if ($sheet_row > 1 && !isset($row_to_username[$sheet_row]))
			{
				$row_to_username[$sheet_row] = $username_normalized;
			}

			$name_value = isset($row['display_name']) && trim((string) $row['display_name']) !== ''
				? (string) $row['display_name']
				: $username_normalized;
			$name_key = $this->normalize_name_key($name_value);
			if ($name_key !== '' && !isset($name_to_username[$name_key]))
			{
				$name_to_username[$name_key] = $username_normalized;
			}
		}

		$created = 0;
		$updated = 0;
		$processed = 0;
		$changed = FALSE;
		$sync_time = date('Y-m-d H:i:s');
		$synced_usernames = array();
		for ($i = 0; $i < count($values); $i += 1)
		{
			$row = is_array($values[$i]) ? $values[$i] : array();
			$row_number = $data_start_row + $i;

			$name_value = $this->get_row_value($row, $field_indexes, 'name');
			if ($name_value === '')
			{
				continue;
			}

			$job_title_raw = $this->get_row_value($row, $field_indexes, 'job_title');
			$status_raw = $this->get_row_value($row, $field_indexes, 'status');
			$address_raw = $this->get_row_value($row, $field_indexes, 'address');
			$phone_raw = $this->get_row_value($row, $field_indexes, 'phone');
			$branch_raw = $this->get_row_value($row, $field_indexes, 'branch');
			$salary_raw = $this->get_row_value($row, $field_indexes, 'salary');
			$name_key = $this->normalize_name_key($name_value);

			$username_key = '';
			if ($name_key !== '' && isset($name_to_username[$name_key]))
			{
				$username_key = strtolower(trim((string) $name_to_username[$name_key]));
				if (isset($synced_usernames[$username_key]))
				{
					$username_key = '';
				}
			}
			if ($username_key === '' && isset($row_to_username[$row_number]))
			{
				$username_key = strtolower(trim((string) $row_to_username[$row_number]));
			}
			if ($username_key === '')
			{
				$username_key = $this->build_unique_username_from_name($name_value, $used_usernames);
			}
			else
			{
				$used_usernames[$username_key] = TRUE;
			}

			if ($username_key === '' || $username_key === 'admin')
			{
				continue;
			}

			$existing = isset($account_book[$username_key]) && is_array($account_book[$username_key])
				? $account_book[$username_key]
				: array();
			$existing_role = strtolower(trim((string) (isset($existing['role']) ? $existing['role'] : 'user')));
			if ($existing_role === 'admin')
			{
				continue;
			}
			$existing_sync_source = strtolower(trim((string) (isset($existing['sheet_sync_source']) ? $existing['sheet_sync_source'] : '')));
			if (!empty($existing) && $existing_sync_source === 'web')
			{
				$synced_usernames[$username_key] = TRUE;
				$processed += 1;
				continue;
			}

			$job_title_from_sheet = $this->resolve_job_title($job_title_raw);
			$status_from_sheet = strtolower(trim((string) $status_raw));
			$salary_amount = $this->parse_money_to_int($salary_raw);
			$is_existing_row = !empty($existing);
			if ($status_from_sheet !== '' &&
				strpos($status_from_sheet, 'aktif') === FALSE &&
				strpos($status_from_sheet, 'nonaktif') === FALSE &&
				strpos($status_from_sheet, 'resign') === FALSE &&
				strpos($status_from_sheet, 'izin') === FALSE &&
				strpos($status_from_sheet, 'cuti') === FALSE)
			{
				continue;
			}

			if (!$is_existing_row)
			{
				$phone_candidate = trim((string) $phone_raw);
				$address_candidate = trim((string) $address_raw);
				if ($job_title_from_sheet === '' && $salary_amount <= 0 && $phone_candidate === '' && $address_candidate === '')
				{
					continue;
				}
			}

			$existing_salary_monthly = isset($existing['salary_monthly']) ? (int) $existing['salary_monthly'] : 0;
			$existing_salary_tier = strtoupper(trim((string) (isset($existing['salary_tier']) ? $existing['salary_tier'] : '')));
			if (!isset($salary_profiles[$existing_salary_tier]))
			{
				$existing_salary_tier = '';
			}

			if ($salary_amount > 0)
			{
				$salary_monthly = $salary_amount;
				$salary_tier = $this->resolve_salary_tier_from_amount($salary_amount, $salary_profiles);
			}
			else
			{
				$salary_tier = $existing_salary_tier !== '' ? $existing_salary_tier : 'A';
				if (!isset($salary_profiles[$salary_tier]))
				{
					$salary_tier = 'A';
				}
				$salary_monthly = $existing_salary_monthly > 0
					? $existing_salary_monthly
					: (isset($salary_profiles[$salary_tier]['salary_monthly']) ? (int) $salary_profiles[$salary_tier]['salary_monthly'] : 0);
			}

			if (!isset($salary_profiles[$salary_tier]))
			{
				$salary_tier = 'A';
			}

			$work_days = isset($existing['work_days']) ? (int) $existing['work_days'] : 0;
			if ($work_days <= 0)
			{
				$work_days = isset($salary_profiles[$salary_tier]['work_days'])
					? (int) $salary_profiles[$salary_tier]['work_days']
					: 28;
			}

			$job_title_value = $job_title_from_sheet;
			if ($job_title_value === '')
			{
				$job_title_value = $this->resolve_job_title(isset($existing['job_title']) ? (string) $existing['job_title'] : '');
			}
			if ($job_title_value === '')
			{
				$job_title_value = $this->default_job_title();
			}

			$address_value = trim((string) $address_raw);
			if ($address_value === '')
			{
				$address_value = isset($existing['address']) && trim((string) $existing['address']) !== ''
					? (string) $existing['address']
					: $this->default_address();
			}

			$phone_value = trim((string) $phone_raw);
			if ($phone_value === '')
			{
				$phone_value = isset($existing['phone']) ? (string) $existing['phone'] : '';
			}

			$branch_value = $this->resolve_branch_name($branch_raw);
			if ($branch_value === '')
			{
				$branch_value = $this->resolve_branch_name(isset($existing['branch']) ? (string) $existing['branch'] : '');
			}
			if ($branch_value === '')
			{
				$branch_value = $this->default_branch_name();
			}

			$status_value = trim((string) $status_raw);
			if ($status_value === '')
			{
				$status_value = isset($existing['employee_status']) && trim((string) $existing['employee_status']) !== ''
					? (string) $existing['employee_status']
					: 'Aktif';
			}

			$shift_name = isset($existing['shift_name']) && trim((string) $existing['shift_name']) !== ''
				? (string) $existing['shift_name']
				: (string) $default_shift['shift_name'];
			$shift_time = isset($existing['shift_time']) && trim((string) $existing['shift_time']) !== ''
				? (string) $existing['shift_time']
				: (string) $default_shift['shift_time'];
			$password_value = isset($existing['password']) && trim((string) $existing['password']) !== ''
				? (string) $existing['password']
				: $default_password;
			$profile_photo = isset($existing['profile_photo']) && trim((string) $existing['profile_photo']) !== ''
				? (string) $existing['profile_photo']
				: '';

			$base_row = array(
				'role' => 'user',
				'password' => $password_value,
				'display_name' => $name_value,
				'branch' => $branch_value,
				'phone' => $phone_value,
				'shift_name' => $shift_name,
				'shift_time' => $shift_time,
				'salary_tier' => $salary_tier,
				'salary_monthly' => $salary_monthly,
				'work_days' => $work_days,
				'job_title' => $job_title_value,
				'address' => $address_value,
				'profile_photo' => $profile_photo,
				'employee_status' => $status_value,
				'sheet_row' => (int) $row_number,
				'sheet_sync_source' => 'google_sheet'
			);

			$merged_row = is_array($existing) ? array_merge($existing, $base_row) : $base_row;
			$processed += 1;
			$synced_usernames[$username_key] = TRUE;

			if (!isset($account_book[$username_key]) || !is_array($account_book[$username_key]))
			{
				$merged_row['sheet_last_sync_at'] = $sync_time;
				$account_book[$username_key] = $merged_row;
				$changed = TRUE;
				$created += 1;
				continue;
			}

			$existing_row = $account_book[$username_key];
			if (!$this->account_rows_equal($existing_row, $merged_row))
			{
				$merged_row['sheet_last_sync_at'] = $sync_time;
				$account_book[$username_key] = $merged_row;
				$changed = TRUE;
				$updated += 1;
			}
		}
		$pruned = 0;
		if (isset($this->config['prune_missing_sheet_users']) && $this->config['prune_missing_sheet_users'] === TRUE)
		{
			foreach ($account_book as $username_key => $row)
			{
				$username_normalized = strtolower(trim((string) $username_key));
				if ($username_normalized === '' || !is_array($row))
				{
					continue;
				}

				$role = strtolower(trim((string) (isset($row['role']) ? $row['role'] : 'user')));
				$sheet_source = strtolower(trim((string) (isset($row['sheet_sync_source']) ? $row['sheet_sync_source'] : '')));
				$sheet_row = isset($row['sheet_row']) ? (int) $row['sheet_row'] : 0;
				if ($role === 'user' && $sheet_source === 'google_sheet' && $sheet_row > 1 && !isset($synced_usernames[$username_normalized]))
				{
					unset($account_book[$username_normalized]);
					$changed = TRUE;
					$pruned += 1;
				}
			}
		}

		if ($changed)
		{
			$saved = function_exists('absen_save_account_book') ? absen_save_account_book($account_book) : FALSE;
			if (!$saved)
			{
				return array(
					'success' => FALSE,
					'skipped' => FALSE,
					'message' => 'Gagal menyimpan perubahan akun dari spreadsheet.'
				);
			}
		}

		$this->write_sync_state(array(
			'last_pull_at' => time(),
			'last_error_at' => 0,
			'last_error_message' => '',
			'last_result' => array(
				'processed' => $processed,
				'created' => $created,
				'updated' => $updated,
				'pruned' => $pruned,
				'changed' => $changed
			)
		));

		return array(
			'success' => TRUE,
			'skipped' => FALSE,
			'message' => 'Sinkronisasi dari spreadsheet selesai.',
			'changed' => $changed,
			'processed' => $processed,
			'created' => $created,
			'updated' => $updated,
			'pruned' => $pruned
		);
	}

	public function sync_attendance_from_sheet($options = array())
	{
		$force = isset($options['force']) && $options['force'] === TRUE;
		if (!$this->is_enabled())
		{
			return array(
				'success' => FALSE,
				'skipped' => TRUE,
				'message' => 'Sync spreadsheet dinonaktifkan.'
			);
		}

		if (!(isset($this->config['attendance_sync_enabled']) && $this->config['attendance_sync_enabled'] === TRUE))
		{
			return array(
				'success' => FALSE,
				'skipped' => TRUE,
				'message' => 'Sync data absen spreadsheet dinonaktifkan.'
			);
		}

		$state = $this->read_sync_state();
		$interval_seconds = isset($this->config['attendance_sync_interval_seconds'])
			? (int) $this->config['attendance_sync_interval_seconds']
			: (isset($this->config['sync_interval_seconds']) ? (int) $this->config['sync_interval_seconds'] : 60);
		if ($interval_seconds < 0)
		{
			$interval_seconds = 0;
		}

		$last_pull_at = isset($state['last_attendance_pull_at']) ? (int) $state['last_attendance_pull_at'] : 0;
		if (!$force && $interval_seconds > 0 && $last_pull_at > 0 && (time() - $last_pull_at) < $interval_seconds)
		{
			return array(
				'success' => TRUE,
				'skipped' => TRUE,
				'message' => 'Menunggu interval sync absensi berikutnya.'
			);
		}

		$spreadsheet_id = trim((string) $this->config['spreadsheet_id']);
		if ($spreadsheet_id === '')
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Spreadsheet ID belum diatur untuk sync absensi.'
			);
		}

		$attendance_sheet_title = trim((string) (isset($this->config['attendance_sheet_title']) ? $this->config['attendance_sheet_title'] : ''));
		if ($attendance_sheet_title === '')
		{
			$attendance_sheet_gid = isset($this->config['attendance_sheet_gid']) ? (int) $this->config['attendance_sheet_gid'] : 0;
			if ($attendance_sheet_gid > 0)
			{
				$title_result = $this->resolve_sheet_title_from_gid($spreadsheet_id, $attendance_sheet_gid);
				if (!$title_result['success'])
				{
					$this->write_sync_state(array(
						'last_attendance_error_at' => time(),
						'last_attendance_error_message' => isset($title_result['message']) ? (string) $title_result['message'] : 'Gagal membaca metadata sheet absensi.'
					));
					return $title_result;
				}
				$attendance_sheet_title = isset($title_result['sheet_title']) ? trim((string) $title_result['sheet_title']) : '';
			}
		}

		if ($attendance_sheet_title === '')
		{
			$attendance_sheet_title = trim((string) (isset($this->config['sheet_title']) ? $this->config['sheet_title'] : ''));
		}
		if ($attendance_sheet_title === '')
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Nama sheet Data Absen tidak ditemukan.'
			);
		}

		$header_result = $this->sheet_values_get($attendance_sheet_title, 'A1:ZZ20');
		if (!$header_result['success'])
		{
			$this->write_sync_state(array(
				'last_attendance_error_at' => time(),
				'last_attendance_error_message' => isset($header_result['message']) ? (string) $header_result['message'] : 'Gagal membaca header Data Absen.'
			));
			return $header_result;
		}

		$header_rows = isset($header_result['data']['values']) && is_array($header_result['data']['values'])
			? $header_result['data']['values']
			: array();
		if (empty($header_rows))
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Sheet Data Absen kosong.'
			);
		}

		$header_row_index = -1;
		for ($i = 0; $i < count($header_rows); $i += 1)
		{
			$row = is_array($header_rows[$i]) ? $header_rows[$i] : array();
			$has_name = FALSE;
			$has_date = FALSE;
			for ($j = 0; $j < count($row); $j += 1)
			{
				$token = $this->normalize_attendance_header(isset($row[$j]) ? $row[$j] : '');
				if ($token === 'namakaryawan')
				{
					$has_name = TRUE;
				}
				if ($token === 'tanggalabsen' || $token === 'tanggal')
				{
					$has_date = TRUE;
				}
			}

			if ($has_name && $has_date)
			{
				$header_row_index = $i;
				break;
			}
		}

		if ($header_row_index < 0)
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Header "Nama Karyawan / Tanggal Absen" tidak ditemukan pada sheet Data Absen.'
			);
		}

		$header_values = isset($header_rows[$header_row_index]) && is_array($header_rows[$header_row_index])
			? $header_rows[$header_row_index]
			: array();
		$sub_header_values = isset($header_rows[$header_row_index + 1]) && is_array($header_rows[$header_row_index + 1])
			? $header_rows[$header_row_index + 1]
			: array();
		$field_indexes = $this->build_attendance_field_indexes($header_values, $sub_header_values);
		if (!isset($field_indexes['name']) || !isset($field_indexes['date_absen']))
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Kolom wajib Data Absen (Nama Karyawan, Tanggal Absen) tidak ditemukan.'
			);
		}

		$has_sub_header = FALSE;
		for ($i = 0; $i < count($sub_header_values); $i += 1)
		{
			$token = $this->normalize_attendance_header(isset($sub_header_values[$i]) ? $sub_header_values[$i] : '');
			if ($token === 'masuk' || $token === 'pulang' || $token === '130menit' || $token === '3160menit' || $token === '13jam' || $token === '4jam')
			{
				$has_sub_header = TRUE;
				break;
			}
		}

		$data_start_row = $header_row_index + ($has_sub_header ? 3 : 2);
		if ($data_start_row <= 1)
		{
			$data_start_row = 2;
		}

		$data_result = $this->sheet_values_get($attendance_sheet_title, 'A'.$data_start_row.':ZZ');
		if (!$data_result['success'])
		{
			$this->write_sync_state(array(
				'last_attendance_error_at' => time(),
				'last_attendance_error_message' => isset($data_result['message']) ? (string) $data_result['message'] : 'Gagal membaca baris Data Absen.'
			));
			return $data_result;
		}

		$data_rows = isset($data_result['data']['values']) && is_array($data_result['data']['values'])
			? $data_result['data']['values']
			: array();

		$salary_profiles = function_exists('absen_salary_profile_book') ? absen_salary_profile_book() : array();
		$shift_profiles = function_exists('absen_shift_profile_book') ? absen_shift_profile_book() : array();
		$default_shift = isset($shift_profiles['pagi']) && is_array($shift_profiles['pagi'])
			? $shift_profiles['pagi']
			: array('shift_name' => 'Shift Pagi - Sore', 'shift_time' => '08:00 - 17:00');
		$default_password = isset($this->config['default_user_password']) && trim((string) $this->config['default_user_password']) !== ''
			? (string) $this->config['default_user_password']
			: '123';

		$account_book = function_exists('absen_load_account_book') ? absen_load_account_book() : array();
		if (!is_array($account_book))
		{
			$account_book = array();
		}

		$display_lookup = array();
		$display_lookup_compact = array();
		$used_usernames = array();
		foreach ($account_book as $username_key => $row)
		{
			$username_normalized = strtolower(trim((string) $username_key));
			if ($username_normalized === '')
			{
				continue;
			}
			$used_usernames[$username_normalized] = TRUE;
			if (!is_array($row))
			{
				continue;
			}

			$role = strtolower(trim((string) (isset($row['role']) ? $row['role'] : 'user')));
			if ($role !== 'user')
			{
				continue;
			}

			$display_name = isset($row['display_name']) && trim((string) $row['display_name']) !== ''
				? (string) $row['display_name']
				: $username_normalized;
			$name_key = $this->normalize_name_key($display_name);
			if ($name_key !== '' && !isset($display_lookup[$name_key]))
			{
				$display_lookup[$name_key] = $username_normalized;
			}

			$name_compact = $this->normalize_name_lookup_key($display_name);
			if ($name_compact !== '' && !isset($display_lookup_compact[$name_compact]))
			{
				$display_lookup_compact[$name_compact] = $username_normalized;
			}
		}
		$database_phone_lookup = $this->build_database_phone_lookup($account_book);
		$attendance_phone_updates = array();
		$attendance_phone_update_rows = array();
		$phone_column_letter = '';
		if (isset($field_indexes['phone']))
		{
			$phone_column_letter = $this->column_letter_from_index((int) $field_indexes['phone']);
		}
		$attendance_branch_updates = array();
		$attendance_branch_update_rows = array();
		$branch_column_letter = '';
		if (isset($field_indexes['branch']))
		{
			$branch_column_letter = $this->column_letter_from_index((int) $field_indexes['branch']);
		}
		$backfilled_phone_cells = 0;
		$phone_backfill_error = '';
		$backfilled_branch_cells = 0;
		$branch_backfill_error = '';

		$attendance_file = APPPATH.'cache/attendance_records.json';
		$attendance_records = $this->load_json_array_file($attendance_file);
		$attendance_index = array();
		for ($i = 0; $i < count($attendance_records); $i += 1)
		{
			$row_username = isset($attendance_records[$i]['username']) ? strtolower(trim((string) $attendance_records[$i]['username'])) : '';
			$row_date = isset($attendance_records[$i]['date']) ? trim((string) $attendance_records[$i]['date']) : '';
			if ($row_username === '' || !$this->is_valid_attendance_date($row_date))
			{
				continue;
			}
			$attendance_index[$row_username.'|'.$row_date] = $i;
		}

		$processed = 0;
		$created_accounts = 0;
		$updated_accounts = 0;
		$created_attendance = 0;
		$updated_attendance = 0;
		$skipped_rows = 0;
		$changed_accounts = FALSE;
		$changed_attendance = FALSE;
		$sync_time = date('Y-m-d H:i:s');

		for ($i = 0; $i < count($data_rows); $i += 1)
		{
			$row = is_array($data_rows[$i]) ? $data_rows[$i] : array();
			$row_number = $data_start_row + $i;
			$name_value = $this->get_attendance_row_value($row, $field_indexes, 'name');
			if ($name_value === '')
			{
				continue;
			}

			$name_key = $this->normalize_name_key($name_value);
			$name_compact = $this->normalize_name_lookup_key($name_value);
			$username_key = '';
			if ($name_key !== '' && isset($display_lookup[$name_key]))
			{
				$username_key = (string) $display_lookup[$name_key];
			}
			if ($username_key === '' && $name_compact !== '' && isset($display_lookup_compact[$name_compact]))
			{
				$username_key = (string) $display_lookup_compact[$name_compact];
			}
			if ($username_key === '')
			{
				$username_key = $this->build_unique_username_from_name($name_value, $used_usernames);
			}
			if ($username_key === '' || $username_key === 'admin')
			{
				$skipped_rows += 1;
				continue;
			}

			$existing_account = isset($account_book[$username_key]) && is_array($account_book[$username_key])
				? $account_book[$username_key]
				: array();
			$existing_role = strtolower(trim((string) (isset($existing_account['role']) ? $existing_account['role'] : 'user')));
			if ($existing_role === 'admin')
			{
				$skipped_rows += 1;
				continue;
			}

			$job_title_raw = $this->get_attendance_row_value($row, $field_indexes, 'job_title');
			$status_raw = $this->get_attendance_row_value($row, $field_indexes, 'status');
			$address_raw = $this->get_attendance_row_value($row, $field_indexes, 'address');
			$phone_raw = $this->get_attendance_row_value($row, $field_indexes, 'phone');
			$branch_raw = $this->get_attendance_row_value($row, $field_indexes, 'branch');
			$employee_id_raw = $this->get_attendance_row_value($row, $field_indexes, 'employee_id');
			$shift_name_raw = $this->get_attendance_row_value($row, $field_indexes, 'shift_name');
			$date_absen_raw = $this->get_attendance_row_value($row, $field_indexes, 'date_absen');
			$date_meta = $this->parse_attendance_date_meta($date_absen_raw);
			$record_date = isset($date_meta['anchor_date']) ? (string) $date_meta['anchor_date'] : '';
			$month_key = isset($date_meta['month_key']) ? (string) $date_meta['month_key'] : '';
			$date_label = $record_date !== '' ? date('d-m-Y', strtotime($record_date)) : '';

			$work_days_value = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'hari_efektif'));
			$total_hadir = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'total_hadir'));
			$sudah_absen = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'sudah_berapa_absen'));
			if ($total_hadir <= 0 && $sudah_absen > 0)
			{
				$total_hadir = $sudah_absen;
			}
			$total_telat_1_30 = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'telat_1_30'));
			$total_telat_31_60 = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'telat_31_60'));
			$total_telat_1_3 = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'telat_1_3'));
			$total_telat_gt_4 = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'telat_gt_4'));
			$total_izin_cuti = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'total_izin_cuti'));
			$total_alpha = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'total_alpha'));

			$salary_tier = isset($existing_account['salary_tier']) ? strtoupper(trim((string) $existing_account['salary_tier'])) : 'A';
			if (!isset($salary_profiles[$salary_tier]))
			{
				$salary_tier = 'A';
			}
			$salary_monthly = isset($existing_account['salary_monthly']) ? (int) $existing_account['salary_monthly'] : 0;
			if ($salary_monthly <= 0)
			{
				$salary_monthly = isset($salary_profiles[$salary_tier]['salary_monthly'])
					? (int) $salary_profiles[$salary_tier]['salary_monthly']
					: 1000000;
			}

			$job_title_value = $this->resolve_job_title($job_title_raw);
			if ($job_title_value === '')
			{
				$job_title_value = $this->resolve_job_title(isset($existing_account['job_title']) ? (string) $existing_account['job_title'] : '');
			}
			if ($job_title_value === '')
			{
				$job_title_value = $this->default_job_title();
			}

			$status_value = trim((string) $status_raw);
			if ($status_value === '')
			{
				$status_value = isset($existing_account['employee_status']) && trim((string) $existing_account['employee_status']) !== ''
					? (string) $existing_account['employee_status']
					: 'Aktif';
			}

			$address_value = trim((string) $address_raw);
			if ($address_value === '')
			{
				$address_value = isset($existing_account['address']) && trim((string) $existing_account['address']) !== ''
					? (string) $existing_account['address']
					: $this->default_address();
			}

			$phone_raw_value = trim((string) $phone_raw);
			$phone_from_lookup = $this->resolve_phone_from_lookup($username_key, $name_value, $database_phone_lookup, $employee_id_raw);
			$phone_value = $phone_from_lookup !== '' ? $phone_from_lookup : $phone_raw_value;
			if ($phone_value === '')
			{
				$phone_value = isset($existing_account['phone']) ? trim((string) $existing_account['phone']) : '';
			}
			$phone_changed = $phone_value !== '' &&
				$this->normalize_phone_compare_key($phone_raw_value) !== $this->normalize_phone_compare_key($phone_value);
			if ($phone_changed && $phone_column_letter !== '' && !isset($attendance_phone_update_rows[$row_number]))
			{
				$attendance_phone_update_rows[$row_number] = TRUE;
				$attendance_phone_updates[] = array(
					'range' => $this->quote_sheet_title($attendance_sheet_title).'!'.$phone_column_letter.$row_number,
					'majorDimension' => 'ROWS',
					'values' => array(
						array((string) $phone_value)
					)
				);
			}

			$branch_raw_value = trim((string) $branch_raw);
			$branch_value = $this->resolve_branch_name($branch_raw_value);
			if ($branch_value === '')
			{
				$branch_value = $this->resolve_branch_name(isset($existing_account['branch']) ? (string) $existing_account['branch'] : '');
			}
			if ($branch_value === '')
			{
				$branch_value = $this->default_branch_name();
			}
			$branch_changed = $branch_value !== '' &&
				strtolower($branch_raw_value) !== strtolower($branch_value);
			if ($branch_changed && $branch_column_letter !== '' && !isset($attendance_branch_update_rows[$row_number]))
			{
				$attendance_branch_update_rows[$row_number] = TRUE;
				$attendance_branch_updates[] = array(
					'range' => $this->quote_sheet_title($attendance_sheet_title).'!'.$branch_column_letter.$row_number,
					'majorDimension' => 'ROWS',
					'values' => array(
						array((string) $branch_value)
					)
				);
			}

			$shift_name_value = trim((string) $shift_name_raw);
			if ($shift_name_value === '')
			{
				$shift_name_value = isset($existing_account['shift_name']) && trim((string) $existing_account['shift_name']) !== ''
					? (string) $existing_account['shift_name']
					: (string) $default_shift['shift_name'];
			}
			$shift_time_value = $this->extract_shift_time_from_name($shift_name_value);
			if ($shift_time_value === '')
			{
				$shift_time_value = isset($existing_account['shift_time']) && trim((string) $existing_account['shift_time']) !== ''
					? (string) $existing_account['shift_time']
					: (string) $default_shift['shift_time'];
			}

			$password_value = isset($existing_account['password']) && trim((string) $existing_account['password']) !== ''
				? (string) $existing_account['password']
				: $default_password;
			$profile_photo = isset($existing_account['profile_photo']) && trim((string) $existing_account['profile_photo']) !== ''
				? (string) $existing_account['profile_photo']
				: '';
			$existing_sheet_row = isset($existing_account['sheet_row']) ? (int) $existing_account['sheet_row'] : 0;
			$existing_sheet_source = isset($existing_account['sheet_sync_source']) ? (string) $existing_account['sheet_sync_source'] : '';
			$existing_sheet_last_sync = isset($existing_account['sheet_last_sync_at']) ? (string) $existing_account['sheet_last_sync_at'] : '';

			$account_row = array(
				'role' => 'user',
				'password' => $password_value,
				'display_name' => $name_value,
				'branch' => $branch_value,
				'phone' => $phone_value,
				'shift_name' => $shift_name_value,
				'shift_time' => $shift_time_value,
				'salary_tier' => $salary_tier,
				'salary_monthly' => $salary_monthly,
				'work_days' => $work_days_value > 0 ? $work_days_value : (isset($existing_account['work_days']) ? (int) $existing_account['work_days'] : 22),
				'job_title' => $job_title_value,
				'address' => $address_value,
				'profile_photo' => $profile_photo,
				'employee_status' => $status_value,
				'sheet_row' => $existing_sheet_row > 0 ? $existing_sheet_row : 0,
				'sheet_sync_source' => $existing_sheet_source,
				'sheet_last_sync_at' => $existing_sheet_last_sync
			);

			$should_save_account = !isset($account_book[$username_key]) || !is_array($account_book[$username_key]);
			if (!$should_save_account)
			{
				if (!$this->account_rows_equal($account_book[$username_key], $account_row))
				{
					$should_save_account = TRUE;
				}
			}

			if ($should_save_account)
			{
				$account_book[$username_key] = is_array($existing_account) ? array_merge($existing_account, $account_row) : $account_row;
				if (!isset($existing_account['role']))
				{
					$created_accounts += 1;
				}
				else
				{
					$updated_accounts += 1;
				}
				$changed_accounts = TRUE;
			}

			$display_lookup[$this->normalize_name_key($name_value)] = $username_key;
			$compact_key = $this->normalize_name_lookup_key($name_value);
			if ($compact_key !== '')
			{
				$display_lookup_compact[$compact_key] = $username_key;
			}

			if ($record_date !== '')
			{
				$check_in_time = $this->normalize_clock_time($this->get_attendance_row_value($row, $field_indexes, 'waktu_masuk'));
				$check_out_time = $this->normalize_clock_time($this->get_attendance_row_value($row, $field_indexes, 'waktu_pulang'));
				$late_duration = $this->normalize_duration_value($this->get_attendance_row_value($row, $field_indexes, 'telat_duration'));
				$work_duration = $this->normalize_duration_value($this->get_attendance_row_value($row, $field_indexes, 'durasi_bekerja'));
				$late_seconds = $this->duration_text_to_seconds($late_duration);
				$late_category = '';
				if ($late_seconds > 0 && $late_seconds <= 1800)
				{
					$late_category = 'telat_1_30';
				}
				elseif ($late_seconds > 1800 && $late_seconds <= 3600)
				{
					$late_category = 'telat_31_60';
				}
				elseif ($late_seconds > 3600 && $late_seconds <= 14400)
				{
					$late_category = 'telat_1_3_jam';
				}
				elseif ($late_seconds > 14400)
				{
					$late_category = 'telat_gt_4_jam';
				}

				$month_policy = $this->calculate_month_work_policy_from_date($record_date);
				$attendance_row = array(
					'username' => $username_key,
					'date' => $record_date,
					'date_label' => $date_label !== '' ? $date_label : date('d-m-Y', strtotime($record_date)),
					'shift_name' => $shift_name_value,
					'shift_time' => $shift_time_value,
					'check_in_time' => $check_in_time,
					'check_in_late' => $late_duration !== '' ? $late_duration : '00:00:00',
					'check_in_photo' => $this->get_attendance_row_value($row, $field_indexes, 'foto_masuk'),
					'check_in_lat' => '',
					'check_in_lng' => '',
					'check_in_accuracy_m' => '',
					'check_in_distance_m' => '',
					'late_reason' => $this->get_attendance_row_value($row, $field_indexes, 'alasan_telat'),
					'salary_cut_amount' => '0',
					'salary_cut_rule' => $late_seconds > 0 ? 'Sinkron Spreadsheet' : 'Tidak telat',
					'salary_cut_category' => $late_category,
					'salary_tier' => $salary_tier,
					'salary_monthly' => number_format((int) $salary_monthly, 0, '.', ''),
					'work_days_per_month' => $work_days_value > 0 ? $work_days_value : $month_policy['work_days'],
					'days_in_month' => $month_policy['days_in_month'],
					'weekly_off_days' => $month_policy['weekly_off_days'],
					'check_out_time' => $check_out_time,
					'work_duration' => $work_duration,
					'check_out_photo' => $this->get_attendance_row_value($row, $field_indexes, 'foto_pulang'),
					'check_out_lat' => '',
					'check_out_lng' => '',
					'check_out_accuracy_m' => '',
					'check_out_distance_m' => '',
					'updated_at' => $sync_time,
					'sheet_sync_source' => 'google_sheet_attendance',
					'sheet_sync_row' => (int) $row_number,
					'sheet_month' => $month_key,
					'sheet_tanggal_absen' => $date_absen_raw,
					'sheet_sudah_berapa_absen' => $sudah_absen,
					'sheet_hari_efektif' => $work_days_value,
					'sheet_total_hadir' => $total_hadir,
					'sheet_total_telat_1_30' => $total_telat_1_30,
					'sheet_total_telat_31_60' => $total_telat_31_60,
					'sheet_total_telat_1_3' => $total_telat_1_3,
					'sheet_total_telat_gt_4' => $total_telat_gt_4,
					'sheet_total_izin_cuti' => $total_izin_cuti,
					'sheet_total_alpha' => $total_alpha
				);

				$attendance_key = $username_key.'|'.$record_date;
				if (isset($attendance_index[$attendance_key]))
				{
					$index_existing = (int) $attendance_index[$attendance_key];
					$existing_attendance = isset($attendance_records[$index_existing]) && is_array($attendance_records[$index_existing])
						? $attendance_records[$index_existing]
						: array();
					$existing_source = isset($existing_attendance['sheet_sync_source']) ? strtolower(trim((string) $existing_attendance['sheet_sync_source'])) : '';
					if ($existing_source !== '' && $existing_source !== 'google_sheet_attendance')
					{
						$skipped_rows += 1;
					}
					else
					{
						$attendance_records[$index_existing] = array_merge($existing_attendance, $attendance_row);
						$updated_attendance += 1;
						$changed_attendance = TRUE;
					}
				}
				else
				{
					$attendance_records[] = $attendance_row;
					$attendance_index[$attendance_key] = count($attendance_records) - 1;
					$created_attendance += 1;
					$changed_attendance = TRUE;
				}
			}

			$processed += 1;
		}
		if (!empty($attendance_phone_updates))
		{
			$batch_phone_result = $this->sheet_values_batch_update($attendance_phone_updates);
			if (isset($batch_phone_result['success']) && $batch_phone_result['success'] === TRUE)
			{
				$backfilled_phone_cells = count($attendance_phone_updates);
			}
			else
			{
				$phone_backfill_error = isset($batch_phone_result['message']) && trim((string) $batch_phone_result['message']) !== ''
					? (string) $batch_phone_result['message']
					: 'Gagal mengisi kolom Tlp di Data Absen.';
			}
		}
		if (!empty($attendance_branch_updates))
		{
			$batch_branch_result = $this->sheet_values_batch_update($attendance_branch_updates);
			if (isset($batch_branch_result['success']) && $batch_branch_result['success'] === TRUE)
			{
				$backfilled_branch_cells = count($attendance_branch_updates);
			}
			else
			{
				$branch_backfill_error = isset($batch_branch_result['message']) && trim((string) $batch_branch_result['message']) !== ''
					? (string) $batch_branch_result['message']
					: 'Gagal mengisi kolom Cabang di Data Absen.';
			}
		}

		if ($changed_accounts)
		{
			$saved_accounts = function_exists('absen_save_account_book') ? absen_save_account_book($account_book) : FALSE;
			if (!$saved_accounts)
			{
				return array(
					'success' => FALSE,
					'skipped' => FALSE,
					'message' => 'Gagal menyimpan akun saat sinkronisasi Data Absen.'
				);
			}
		}

		if ($changed_attendance)
		{
			if (!$this->save_json_array_file($attendance_file, $attendance_records))
			{
				return array(
					'success' => FALSE,
					'skipped' => FALSE,
					'message' => 'Gagal menyimpan attendance_records.json setelah sinkronisasi Data Absen.'
				);
			}
		}

		$this->write_sync_state(array(
			'last_attendance_pull_at' => time(),
			'last_attendance_error_at' => 0,
			'last_attendance_error_message' => '',
			'last_attendance_result' => array(
				'processed' => $processed,
				'created_accounts' => $created_accounts,
				'updated_accounts' => $updated_accounts,
				'created_attendance' => $created_attendance,
				'updated_attendance' => $updated_attendance,
				'skipped_rows' => $skipped_rows,
				'changed_accounts' => $changed_accounts,
				'changed_attendance' => $changed_attendance,
				'backfilled_phone_cells' => $backfilled_phone_cells,
				'phone_backfill_error' => $phone_backfill_error,
				'backfilled_branch_cells' => $backfilled_branch_cells,
				'branch_backfill_error' => $branch_backfill_error
			)
		));
		$message = 'Sinkronisasi Data Absen selesai.';
		if ($backfilled_phone_cells > 0)
		{
			$message .= ' Kolom Tlp tersinkron dari DATABASE: '.$backfilled_phone_cells.' baris.';
		}
		if ($backfilled_branch_cells > 0)
		{
			$message .= ' Kolom Cabang tersinkron: '.$backfilled_branch_cells.' baris.';
		}
		if ($phone_backfill_error !== '')
		{
			$message .= ' Isi balik Tlp ke sheet gagal: '.$phone_backfill_error;
		}
		if ($branch_backfill_error !== '')
		{
			$message .= ' Isi balik Cabang ke sheet gagal: '.$branch_backfill_error;
		}

		return array(
			'success' => TRUE,
			'skipped' => FALSE,
			'message' => $message,
			'processed' => $processed,
			'created_accounts' => $created_accounts,
			'updated_accounts' => $updated_accounts,
			'created_attendance' => $created_attendance,
			'updated_attendance' => $updated_attendance,
			'skipped_rows' => $skipped_rows,
			'changed_accounts' => $changed_accounts,
			'changed_attendance' => $changed_attendance,
			'backfilled_phone_cells' => $backfilled_phone_cells,
			'phone_backfill_error' => $phone_backfill_error,
			'backfilled_branch_cells' => $backfilled_branch_cells,
			'branch_backfill_error' => $branch_backfill_error
		);
	}

	public function sync_attendance_to_sheet($options = array())
	{
		$force = isset($options['force']) && $options['force'] === TRUE;
		if (!$this->is_enabled())
		{
			return array(
				'success' => FALSE,
				'skipped' => TRUE,
				'message' => 'Sync spreadsheet dinonaktifkan.'
			);
		}

		if (!(isset($this->config['attendance_sync_enabled']) && $this->config['attendance_sync_enabled'] === TRUE))
		{
			return array(
				'success' => FALSE,
				'skipped' => TRUE,
				'message' => 'Sync data absen spreadsheet dinonaktifkan.'
			);
		}

		$state = $this->read_sync_state();
		$interval_seconds = isset($this->config['attendance_sync_interval_seconds'])
			? (int) $this->config['attendance_sync_interval_seconds']
			: (isset($this->config['sync_interval_seconds']) ? (int) $this->config['sync_interval_seconds'] : 60);
		if ($interval_seconds < 0)
		{
			$interval_seconds = 0;
		}

		$last_push_at = isset($state['last_attendance_push_at']) ? (int) $state['last_attendance_push_at'] : 0;
		if (!$force && $interval_seconds > 0 && $last_push_at > 0 && (time() - $last_push_at) < $interval_seconds)
		{
			return array(
				'success' => TRUE,
				'skipped' => TRUE,
				'message' => 'Menunggu interval sync web -> Data Absen berikutnya.'
			);
		}

		$spreadsheet_id = trim((string) $this->config['spreadsheet_id']);
		if ($spreadsheet_id === '')
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Spreadsheet ID belum diatur untuk sync Data Absen.'
			);
		}

		$attendance_sheet_title = trim((string) (isset($this->config['attendance_sheet_title']) ? $this->config['attendance_sheet_title'] : ''));
		if ($attendance_sheet_title === '')
		{
			$attendance_sheet_gid = isset($this->config['attendance_sheet_gid']) ? (int) $this->config['attendance_sheet_gid'] : 0;
			if ($attendance_sheet_gid > 0)
			{
				$title_result = $this->resolve_sheet_title_from_gid($spreadsheet_id, $attendance_sheet_gid);
				if (!(isset($title_result['success']) && $title_result['success'] === TRUE))
				{
					return $title_result;
				}
				$attendance_sheet_title = isset($title_result['sheet_title']) ? trim((string) $title_result['sheet_title']) : '';
			}
		}

		if ($attendance_sheet_title === '')
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Nama sheet Data Absen tidak ditemukan.'
			);
		}

		$header_result = $this->sheet_values_get($attendance_sheet_title, 'A1:ZZ20');
		if (!(isset($header_result['success']) && $header_result['success'] === TRUE))
		{
			return $header_result;
		}

		$header_rows = isset($header_result['data']['values']) && is_array($header_result['data']['values'])
			? $header_result['data']['values']
			: array();
		if (empty($header_rows))
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Sheet Data Absen kosong.'
			);
		}

		$header_row_index = -1;
		for ($i = 0; $i < count($header_rows); $i += 1)
		{
			$row = is_array($header_rows[$i]) ? $header_rows[$i] : array();
			$has_name = FALSE;
			$has_date = FALSE;
			for ($j = 0; $j < count($row); $j += 1)
			{
				$token = $this->normalize_attendance_header(isset($row[$j]) ? $row[$j] : '');
				if ($token === 'namakaryawan')
				{
					$has_name = TRUE;
				}
				if ($token === 'tanggalabsen' || $token === 'tanggal')
				{
					$has_date = TRUE;
				}
			}
			if ($has_name && $has_date)
			{
				$header_row_index = $i;
				break;
			}
		}
		if ($header_row_index < 0)
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Header Data Absen tidak ditemukan.'
			);
		}

		$header_values = isset($header_rows[$header_row_index]) && is_array($header_rows[$header_row_index])
			? $header_rows[$header_row_index]
			: array();
		$sub_header_values = isset($header_rows[$header_row_index + 1]) && is_array($header_rows[$header_row_index + 1])
			? $header_rows[$header_row_index + 1]
			: array();
		$field_indexes = $this->build_attendance_field_indexes($header_values, $sub_header_values);
		if (!isset($field_indexes['name']) || !isset($field_indexes['date_absen']))
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Kolom wajib Data Absen tidak ditemukan.'
			);
		}

		$has_sub_header = FALSE;
		for ($i = 0; $i < count($sub_header_values); $i += 1)
		{
			$token = $this->normalize_attendance_header(isset($sub_header_values[$i]) ? $sub_header_values[$i] : '');
			if ($token === 'masuk' || $token === 'pulang' || $token === '130menit' || $token === '3160menit' || $token === '13jam' || $token === '4jam')
			{
				$has_sub_header = TRUE;
				break;
			}
		}
		$data_start_row = $header_row_index + ($has_sub_header ? 3 : 2);
		if ($data_start_row <= 1)
		{
			$data_start_row = 2;
		}

		$data_result = $this->sheet_values_get($attendance_sheet_title, 'A'.$data_start_row.':ZZ');
		if (!(isset($data_result['success']) && $data_result['success'] === TRUE))
		{
			return $data_result;
		}

		$data_rows = isset($data_result['data']['values']) && is_array($data_result['data']['values'])
			? $data_result['data']['values']
			: array();

		$target_month = isset($options['month']) ? trim((string) $options['month']) : '';
		if (!preg_match('/^\d{4}-\d{2}$/', $target_month))
		{
			$target_month = date('Y-m');
		}
		$target_month_start = $target_month.'-01';
		$target_month_start_ts = strtotime($target_month_start.' 00:00:00');
		if ($target_month_start_ts === FALSE)
		{
			$target_month = date('Y-m');
			$target_month_start = $target_month.'-01';
			$target_month_start_ts = strtotime($target_month_start.' 00:00:00');
		}
		$target_month_end = date('Y-m-t', $target_month_start_ts);

		$account_book = function_exists('absen_load_account_book') ? absen_load_account_book() : array();
		if (!is_array($account_book))
		{
			$account_book = array();
		}

		$employee_id_lookup = $this->build_employee_id_lookup_from_accounts($account_book);

		$display_lookup = array();
		$display_lookup_compact = array();
		$user_rows = array();
		foreach ($account_book as $username_key => $row)
		{
			$username = strtolower(trim((string) $username_key));
			if ($username === '' || !is_array($row))
			{
				continue;
			}
			$role = strtolower(trim((string) (isset($row['role']) ? $row['role'] : 'user')));
			if ($role !== 'user')
			{
				continue;
			}
			$user_rows[$username] = $row;

			$display_name = isset($row['display_name']) && trim((string) $row['display_name']) !== ''
				? trim((string) $row['display_name'])
				: $username;
			$name_key = $this->normalize_name_key($display_name);
			if ($name_key !== '' && !isset($display_lookup[$name_key]))
			{
				$display_lookup[$name_key] = $username;
			}
			$compact_key = $this->normalize_name_lookup_key($display_name);
			if ($compact_key !== '' && !isset($display_lookup_compact[$compact_key]))
			{
				$display_lookup_compact[$compact_key] = $username;
			}

			if (!isset($display_lookup[$this->normalize_name_key($username)]))
			{
				$display_lookup[$this->normalize_name_key($username)] = $username;
			}
			if (!isset($display_lookup_compact[$this->normalize_name_lookup_key($username)]))
			{
				$display_lookup_compact[$this->normalize_name_lookup_key($username)] = $username;
			}
		}

		$sheet_row_by_username = array();
		for ($i = 0; $i < count($data_rows); $i += 1)
		{
			$row = is_array($data_rows[$i]) ? $data_rows[$i] : array();
			$row_number = $data_start_row + $i;
			$name_value = $this->get_attendance_row_value($row, $field_indexes, 'name');
			if ($name_value === '')
			{
				continue;
			}

			$username_key = '';
			$name_key = $this->normalize_name_key($name_value);
			if ($name_key !== '' && isset($display_lookup[$name_key]))
			{
				$username_key = (string) $display_lookup[$name_key];
			}
			if ($username_key === '')
			{
				$compact_key = $this->normalize_name_lookup_key($name_value);
				if ($compact_key !== '' && isset($display_lookup_compact[$compact_key]))
				{
					$username_key = (string) $display_lookup_compact[$compact_key];
				}
			}
			if ($username_key === '')
			{
				continue;
			}
			if (!isset($sheet_row_by_username[$username_key]))
			{
				$sheet_row_by_username[$username_key] = (int) $row_number;
			}
		}

		$attendance_records = $this->load_json_array_file(APPPATH.'cache/attendance_records.json');
		$leave_requests = $this->load_json_array_file(APPPATH.'cache/leave_requests.json');

		$records_by_user = array();
		for ($i = 0; $i < count($attendance_records); $i += 1)
		{
			$row = is_array($attendance_records[$i]) ? $attendance_records[$i] : array();
			$username_key = strtolower(trim((string) (isset($row['username']) ? $row['username'] : '')));
			$date_key = trim((string) (isset($row['date']) ? $row['date'] : ''));
			if ($username_key === '' || $date_key === '' || strpos($date_key, $target_month) !== 0)
			{
				continue;
			}
			if (!isset($records_by_user[$username_key]))
			{
				$records_by_user[$username_key] = array();
			}
			$records_by_user[$username_key][] = $row;
		}

		$leave_by_user = array();
		$latest_leave_reason_by_user = array();
		for ($i = 0; $i < count($leave_requests); $i += 1)
		{
			$request = is_array($leave_requests[$i]) ? $leave_requests[$i] : array();
			$username_key = strtolower(trim((string) (isset($request['username']) ? $request['username'] : '')));
			if ($username_key === '')
			{
				continue;
			}
			$status = strtolower(trim((string) (isset($request['status']) ? $request['status'] : '')));
			if ($status !== 'diterima')
			{
				continue;
			}
			$request_type = $this->resolve_leave_request_type_row($request);
			if ($request_type !== 'izin' && $request_type !== 'cuti')
			{
				continue;
			}

			$start_date = trim((string) (isset($request['start_date']) ? $request['start_date'] : ''));
			$end_date = trim((string) (isset($request['end_date']) ? $request['end_date'] : ''));
			if (!$this->is_valid_attendance_date($start_date) || !$this->is_valid_attendance_date($end_date))
			{
				continue;
			}
			$start_ts = strtotime($start_date.' 00:00:00');
			$end_ts = strtotime($end_date.' 00:00:00');
			if ($start_ts === FALSE || $end_ts === FALSE)
			{
				continue;
			}
			if ($end_ts < $start_ts)
			{
				$temp = $start_ts;
				$start_ts = $end_ts;
				$end_ts = $temp;
			}

			$request_reason = trim((string) (isset($request['reason']) ? $request['reason'] : ''));
			$latest_sort_key = trim((string) (isset($request['updated_at']) ? $request['updated_at'] : ''));
			if ($latest_sort_key === '')
			{
				$latest_sort_key = trim((string) (isset($request['created_at']) ? $request['created_at'] : ''));
			}
			if ($latest_sort_key === '')
			{
				$latest_sort_key = $end_date.' 23:59:59';
			}
			if (!isset($latest_leave_reason_by_user[$username_key]) ||
				strcmp($latest_sort_key, $latest_leave_reason_by_user[$username_key]['sort']) >= 0)
			{
				$latest_leave_reason_by_user[$username_key] = array(
					'sort' => $latest_sort_key,
					'reason' => $request_reason
				);
			}

			for ($cursor = $start_ts; $cursor <= $end_ts; $cursor = strtotime('+1 day', $cursor))
			{
				$date_key = date('Y-m-d', $cursor);
				if (strpos($date_key, $target_month) !== 0)
				{
					continue;
				}
				if (!isset($leave_by_user[$username_key]))
				{
					$leave_by_user[$username_key] = array();
				}
				if (!isset($leave_by_user[$username_key][$date_key]) || $request_type === 'cuti')
				{
					$leave_by_user[$username_key][$date_key] = $request_type;
				}
			}
		}

		$batch_updates = array();
		$append_rows = array();
		$processed_users = 0;
		$updated_rows = 0;
		$appended_rows = 0;
		$skipped_users = 0;

		foreach ($user_rows as $username_key => $account_row)
		{
			$processed_users += 1;
			$display_name = isset($account_row['display_name']) && trim((string) $account_row['display_name']) !== ''
				? trim((string) $account_row['display_name'])
				: (string) $username_key;
			$job_title = $this->resolve_job_title(isset($account_row['job_title']) ? (string) $account_row['job_title'] : '');
			if ($job_title === '')
			{
				$job_title = $this->default_job_title();
			}
			$status_value = isset($account_row['employee_status']) && trim((string) $account_row['employee_status']) !== ''
				? trim((string) $account_row['employee_status'])
				: 'Aktif';
			$address_value = isset($account_row['address']) ? trim((string) $account_row['address']) : '';
			if ($address_value === '')
			{
				$address_value = $this->default_address();
			}
			$branch_value = $this->resolve_branch_name(isset($account_row['branch']) ? (string) $account_row['branch'] : '');
			if ($branch_value === '')
			{
				$branch_value = $this->default_branch_name();
			}
			$phone_value = isset($account_row['phone']) ? trim((string) $account_row['phone']) : '';
			$shift_name_default = isset($account_row['shift_name']) ? trim((string) $account_row['shift_name']) : '';
			if ($shift_name_default === '')
			{
				$shift_name_default = 'Shift Pagi - Sore';
			}
			$employee_id = isset($employee_id_lookup[$username_key]) ? (string) $employee_id_lookup[$username_key] : '-';

			$user_records = isset($records_by_user[$username_key]) && is_array($records_by_user[$username_key])
				? $records_by_user[$username_key]
				: array();
			$latest_record = NULL;
			$latest_sort_key = '';
			$latest_activity_date = '';

			$computed_hadir_dates = array();
			$computed_late_1_30 = 0;
			$computed_late_31_60 = 0;
			$computed_late_1_3 = 0;
			$computed_late_gt_4 = 0;
			$baseline_totals = array(
				'hari_efektif' => 0,
				'sudah_absen' => 0,
				'total_hadir' => 0,
				'telat_1_30' => 0,
				'telat_31_60' => 0,
				'telat_1_3' => 0,
				'telat_gt_4' => 0,
				'total_izin_cuti' => 0,
				'total_alpha' => 0,
				'anchor_date' => ''
			);

			for ($i = 0; $i < count($user_records); $i += 1)
			{
				$row = is_array($user_records[$i]) ? $user_records[$i] : array();
				$row_date = trim((string) (isset($row['date']) ? $row['date'] : ''));
				if (!$this->is_valid_attendance_date($row_date))
				{
					continue;
				}
				if ($latest_activity_date === '' || strcmp($row_date, $latest_activity_date) > 0)
				{
					$latest_activity_date = $row_date;
				}

				$check_in = trim((string) (isset($row['check_in_time']) ? $row['check_in_time'] : ''));
				if ($check_in !== '')
				{
					$computed_hadir_dates[$row_date] = TRUE;
					$late_text = trim((string) (isset($row['check_in_late']) ? $row['check_in_late'] : ''));
					$late_seconds = $this->duration_text_to_seconds($late_text);
					if ($late_seconds > 0 && $late_seconds <= 1800)
					{
						$computed_late_1_30 += 1;
					}
					elseif ($late_seconds > 1800 && $late_seconds <= 3600)
					{
						$computed_late_31_60 += 1;
					}
					elseif ($late_seconds > 3600 && $late_seconds <= 14400)
					{
						$computed_late_1_3 += 1;
					}
					elseif ($late_seconds > 14400)
					{
						$computed_late_gt_4 += 1;
					}
				}

				$row_sort = trim((string) (isset($row['updated_at']) ? $row['updated_at'] : ''));
				if ($row_sort === '')
				{
					$row_sort = $row_date.' '.($check_in !== '' ? $check_in : '00:00:00');
				}
				if ($latest_record === NULL || strcmp($row_sort, $latest_sort_key) >= 0)
				{
					$latest_record = $row;
					$latest_sort_key = $row_sort;
				}

				$sheet_month = trim((string) (isset($row['sheet_month']) ? $row['sheet_month'] : ''));
				if ($sheet_month === '' || $sheet_month === $target_month)
				{
					$has_summary = isset($row['sheet_total_hadir']) || isset($row['sheet_sudah_berapa_absen']) || isset($row['sheet_total_alpha']);
					if ($has_summary)
					{
						$baseline_totals['hari_efektif'] = max($baseline_totals['hari_efektif'], (int) (isset($row['sheet_hari_efektif']) ? $row['sheet_hari_efektif'] : 0));
						$baseline_totals['sudah_absen'] = max($baseline_totals['sudah_absen'], (int) (isset($row['sheet_sudah_berapa_absen']) ? $row['sheet_sudah_berapa_absen'] : 0));
						$baseline_totals['total_hadir'] = max($baseline_totals['total_hadir'], (int) (isset($row['sheet_total_hadir']) ? $row['sheet_total_hadir'] : 0));
						$baseline_totals['telat_1_30'] = max($baseline_totals['telat_1_30'], (int) (isset($row['sheet_total_telat_1_30']) ? $row['sheet_total_telat_1_30'] : 0));
						$baseline_totals['telat_31_60'] = max($baseline_totals['telat_31_60'], (int) (isset($row['sheet_total_telat_31_60']) ? $row['sheet_total_telat_31_60'] : 0));
						$baseline_totals['telat_1_3'] = max($baseline_totals['telat_1_3'], (int) (isset($row['sheet_total_telat_1_3']) ? $row['sheet_total_telat_1_3'] : 0));
						$baseline_totals['telat_gt_4'] = max($baseline_totals['telat_gt_4'], (int) (isset($row['sheet_total_telat_gt_4']) ? $row['sheet_total_telat_gt_4'] : 0));
						$baseline_totals['total_izin_cuti'] = max($baseline_totals['total_izin_cuti'], (int) (isset($row['sheet_total_izin_cuti']) ? $row['sheet_total_izin_cuti'] : 0));
						$baseline_totals['total_alpha'] = max($baseline_totals['total_alpha'], (int) (isset($row['sheet_total_alpha']) ? $row['sheet_total_alpha'] : 0));

						$baseline_anchor = '';
						if (isset($row['sheet_tanggal_absen']) && trim((string) $row['sheet_tanggal_absen']) !== '')
						{
							$meta = $this->parse_attendance_date_meta((string) $row['sheet_tanggal_absen']);
							$baseline_anchor = isset($meta['anchor_date']) ? trim((string) $meta['anchor_date']) : '';
						}
						if ($baseline_anchor === '')
						{
							$baseline_anchor = $row_date;
						}
						if ($baseline_anchor !== '' && ($baseline_totals['anchor_date'] === '' || strcmp($baseline_anchor, $baseline_totals['anchor_date']) > 0))
						{
							$baseline_totals['anchor_date'] = $baseline_anchor;
						}
					}
				}
			}

			$leave_dates_map = isset($leave_by_user[$username_key]) && is_array($leave_by_user[$username_key])
				? $leave_by_user[$username_key]
				: array();
			$leave_count = count($leave_dates_map);
			$latest_leave_date = '';
			foreach ($leave_dates_map as $leave_date => $leave_type)
			{
				if ($latest_leave_date === '' || strcmp((string) $leave_date, $latest_leave_date) > 0)
				{
					$latest_leave_date = (string) $leave_date;
				}
			}
			if ($latest_leave_date !== '' && ($latest_activity_date === '' || strcmp($latest_leave_date, $latest_activity_date) > 0))
			{
				$latest_activity_date = $latest_leave_date;
			}

			$computed_hadir = count($computed_hadir_dates);
			$total_hadir = max($computed_hadir, $baseline_totals['total_hadir']);
			$sudah_absen = max($total_hadir, $baseline_totals['sudah_absen']);
			$total_telat_1_30 = max($computed_late_1_30, $baseline_totals['telat_1_30']);
			$total_telat_31_60 = max($computed_late_31_60, $baseline_totals['telat_31_60']);
			$total_telat_1_3 = max($computed_late_1_3, $baseline_totals['telat_1_3']);
			$total_telat_gt_4 = max($computed_late_gt_4, $baseline_totals['telat_gt_4']);
			$total_izin_cuti = max($leave_count, $baseline_totals['total_izin_cuti']);

			$hari_efektif = $baseline_totals['hari_efektif'];
			if ($hari_efektif <= 0)
			{
				if ($latest_record !== NULL && isset($latest_record['work_days_per_month']) && (int) $latest_record['work_days_per_month'] > 0)
				{
					$hari_efektif = (int) $latest_record['work_days_per_month'];
				}
				else
				{
					$month_policy = $this->calculate_month_work_policy_from_date($target_month_start);
					$hari_efektif = isset($month_policy['work_days']) ? (int) $month_policy['work_days'] : 22;
				}
			}
			if ($hari_efektif <= 0)
			{
				$hari_efektif = 22;
			}

			$total_alpha = $hari_efektif - $total_hadir - $total_izin_cuti;
			if ($total_alpha < 0)
			{
				$total_alpha = 0;
			}
			if ($baseline_totals['total_alpha'] > 0 && $total_alpha === 0 && $total_hadir === 0 && $total_izin_cuti === 0)
			{
				$total_alpha = (int) $baseline_totals['total_alpha'];
			}

			$range_end = $latest_activity_date;
			$current_month_key = date('Y-m');
			if ($target_month === $current_month_key)
			{
				$today = date('Y-m-d');
				if ($range_end === '' || strcmp($today, $range_end) > 0)
				{
					$range_end = $today;
				}
			}
			if ($range_end === '')
			{
				$range_end = $target_month_end;
			}
			if (!$this->is_valid_attendance_date($range_end))
			{
				$range_end = $target_month_end;
			}
			$date_absen_value = $target_month_start.' s/d '.$range_end;

			$waktu_masuk = '';
			$telat_duration = '00:00:00';
			$waktu_pulang = '';
			$durasi_bekerja = '';
			$foto_masuk_raw = '';
			$foto_pulang_raw = '';
			$alasan_telat = '';
			$shift_name_value = $shift_name_default;
			if ($latest_record !== NULL && is_array($latest_record))
			{
				$shift_name_latest = trim((string) (isset($latest_record['shift_name']) ? $latest_record['shift_name'] : ''));
				if ($shift_name_latest !== '')
				{
					$shift_name_value = $shift_name_latest;
				}
				$waktu_masuk = $this->normalize_clock_time(isset($latest_record['check_in_time']) ? $latest_record['check_in_time'] : '');
				$telat_duration = $this->normalize_duration_value(isset($latest_record['check_in_late']) ? $latest_record['check_in_late'] : '00:00:00');
				$waktu_pulang = $this->normalize_clock_time(isset($latest_record['check_out_time']) ? $latest_record['check_out_time'] : '');
				$durasi_bekerja = $this->normalize_duration_value(isset($latest_record['work_duration']) ? $latest_record['work_duration'] : '');
				$foto_masuk_raw = trim((string) (isset($latest_record['check_in_photo']) ? $latest_record['check_in_photo'] : ''));
				$foto_pulang_raw = trim((string) (isset($latest_record['check_out_photo']) ? $latest_record['check_out_photo'] : ''));
				$alasan_telat = trim((string) (isset($latest_record['late_reason']) ? $latest_record['late_reason'] : ''));
			}

			$foto_masuk_value = $this->normalize_attendance_photo_cell($foto_masuk_raw);
			$foto_pulang_value = $this->normalize_attendance_photo_cell($foto_pulang_raw);
			$jenis_masuk = $foto_masuk_raw !== '' ? 'Absen Masuk' : '';
			$jenis_pulang = $foto_pulang_raw !== '' ? 'Absen Pulang' : '';
			$alasan_izin_cuti = isset($latest_leave_reason_by_user[$username_key]['reason'])
				? trim((string) $latest_leave_reason_by_user[$username_key]['reason'])
				: '';
			$alasan_alpha = $total_alpha > 0 ? 'Tidak hadir' : '';

			$field_values = array(
				'employee_id' => $employee_id,
				'name' => $display_name,
				'job_title' => $job_title,
				'status' => $status_value,
				'address' => $address_value,
				'phone' => $phone_value,
				'branch' => $branch_value,
				'date_absen' => $date_absen_value,
				'shift_name' => $shift_name_value,
				'waktu_masuk' => $waktu_masuk,
				'telat_duration' => $telat_duration,
				'waktu_pulang' => $waktu_pulang,
				'durasi_bekerja' => $durasi_bekerja,
				'jenis_masuk' => $jenis_masuk,
				'jenis_pulang' => $jenis_pulang,
				'foto_masuk' => $foto_masuk_value,
				'foto_pulang' => $foto_pulang_value,
				'sudah_berapa_absen' => (string) $sudah_absen,
				'hari_efektif' => (string) $hari_efektif,
				'total_hadir' => (string) $total_hadir,
				'telat_1_30' => (string) $total_telat_1_30,
				'telat_31_60' => (string) $total_telat_31_60,
				'telat_1_3' => (string) $total_telat_1_3,
				'telat_gt_4' => (string) $total_telat_gt_4,
				'total_izin_cuti' => (string) $total_izin_cuti,
				'total_alpha' => (string) $total_alpha,
				'alasan_telat' => $alasan_telat,
				'alasan_izin_cuti' => $alasan_izin_cuti,
				'alasan_alpha' => $alasan_alpha
			);

			$sheet_row = isset($sheet_row_by_username[$username_key]) ? (int) $sheet_row_by_username[$username_key] : 0;
			if ($sheet_row > 1)
			{
				$updated_rows += 1;
				foreach ($field_values as $field_key => $field_value)
				{
					if (!isset($field_indexes[$field_key]))
					{
						continue;
					}
					$column_letter = $this->column_letter_from_index((int) $field_indexes[$field_key]);
					$batch_updates[] = array(
						'range' => $this->quote_sheet_title($attendance_sheet_title).'!'.$column_letter.$sheet_row,
						'majorDimension' => 'ROWS',
						'values' => array(
							array((string) $field_value)
						)
					);
				}
				continue;
			}

			$max_field_index = 0;
			foreach ($field_indexes as $index_value)
			{
				$index_int = (int) $index_value;
				if ($index_int > $max_field_index)
				{
					$max_field_index = $index_int;
				}
			}
			$row_length = $max_field_index + 1;
			if ($row_length < 1)
			{
				$row_length = 1;
			}
			$append_row = array_fill(0, $row_length, '');
			foreach ($field_values as $field_key => $field_value)
			{
				if (!isset($field_indexes[$field_key]))
				{
					continue;
				}
				$field_index = (int) $field_indexes[$field_key];
				if ($field_index < 0)
				{
					continue;
				}
				$append_row[$field_index] = (string) $field_value;
			}
			$append_rows[] = $append_row;
			$appended_rows += 1;
		}

		if (empty($batch_updates) && empty($append_rows))
		{
			$skipped_users = $processed_users;
		}

		if (!empty($batch_updates))
		{
			$batch_result = $this->sheet_values_batch_update($batch_updates);
			if (!(isset($batch_result['success']) && $batch_result['success'] === TRUE))
			{
				return $batch_result;
			}
		}

		if (!empty($append_rows))
		{
			$append_result = $this->sheet_values_append($attendance_sheet_title, 'A:ZZ', $append_rows);
			if (!(isset($append_result['success']) && $append_result['success'] === TRUE))
			{
				return $append_result;
			}
		}

		$this->write_sync_state(array(
			'last_attendance_push_at' => time(),
			'last_attendance_push_error_at' => 0,
			'last_attendance_push_error_message' => '',
			'last_attendance_push_result' => array(
				'month' => $target_month,
				'processed_users' => $processed_users,
				'updated_rows' => $updated_rows,
				'appended_rows' => $appended_rows,
				'skipped_users' => $skipped_users
			)
		));

		return array(
			'success' => TRUE,
			'skipped' => FALSE,
			'message' => 'Sinkronisasi web -> Data Absen selesai.',
			'month' => $target_month,
			'processed_users' => $processed_users,
			'updated_rows' => $updated_rows,
			'appended_rows' => $appended_rows,
			'skipped_users' => $skipped_users
		);
	}

	public function push_account_to_sheet($username_key, $account_row, $action = 'upsert')
	{
		if (!$this->is_enabled())
		{
			return array(
				'success' => FALSE,
				'skipped' => TRUE,
				'message' => 'Sync spreadsheet dinonaktifkan.'
			);
		}

		if (!(isset($this->config['writeback_on_web_change']) && $this->config['writeback_on_web_change'] === TRUE))
		{
			return array(
				'success' => FALSE,
				'skipped' => TRUE,
				'message' => 'Sinkronisasi web -> spreadsheet dinonaktifkan.'
			);
		}

		if (!is_array($account_row))
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Data akun tidak valid untuk sinkronisasi spreadsheet.'
			);
		}

		$action = strtolower(trim((string) $action));
		if ($action !== 'delete')
		{
			$action = 'upsert';
		}

		$context_result = $this->resolve_sheet_context(TRUE);
		if (!$context_result['success'])
		{
			return $context_result;
		}

		$context = isset($context_result['data']) && is_array($context_result['data'])
			? $context_result['data']
			: array();
		$field_indexes = isset($context['field_indexes']) && is_array($context['field_indexes'])
			? $context['field_indexes']
			: array();
		$sheet_title = isset($context['sheet_title']) ? (string) $context['sheet_title'] : '';
		$header_count = isset($context['header_count']) ? (int) $context['header_count'] : 0;

		$name_value = isset($account_row['display_name']) && trim((string) $account_row['display_name']) !== ''
			? trim((string) $account_row['display_name'])
			: trim((string) $username_key);
		if ($name_value === '')
		{
			$name_value = 'user';
		}

		$job_title_value = $this->resolve_job_title(isset($account_row['job_title']) ? (string) $account_row['job_title'] : '');
		if ($job_title_value === '')
		{
			$job_title_value = $this->default_job_title();
		}

		$status_value = isset($account_row['employee_status']) && trim((string) $account_row['employee_status']) !== ''
			? trim((string) $account_row['employee_status'])
			: 'Aktif';
		if ($action === 'delete')
		{
			$status_value = 'Nonaktif';
		}

		$address_value = isset($account_row['address']) ? trim((string) $account_row['address']) : '';
		$branch_value = $this->resolve_branch_name(isset($account_row['branch']) ? (string) $account_row['branch'] : '');
		if ($branch_value === '')
		{
			$branch_value = $this->default_branch_name();
		}
		$phone_value = isset($account_row['phone']) ? trim((string) $account_row['phone']) : '';
		$salary_value = isset($account_row['salary_monthly']) ? (int) $account_row['salary_monthly'] : 0;
		if ($salary_value < 0)
		{
			$salary_value = 0;
		}

		$field_values = array(
			'name' => $name_value,
			'job_title' => $job_title_value,
			'status' => $status_value,
			'address' => $address_value,
			'phone' => $phone_value,
			'branch' => $branch_value,
			'salary' => $salary_value > 0 ? (string) $salary_value : ''
		);

		$sheet_row = isset($account_row['sheet_row']) ? (int) $account_row['sheet_row'] : 0;
		if ($sheet_row > 1)
		{
			$update_data = array();
			$update_field_keys = array();
			foreach ($field_values as $field_key => $field_value)
			{
				if (!isset($field_indexes[$field_key]))
				{
					continue;
				}
				$column_letter = $this->column_letter_from_index((int) $field_indexes[$field_key]);
				$update_data[] = array(
					'range' => $this->quote_sheet_title($sheet_title).'!'.$column_letter.$sheet_row,
					'majorDimension' => 'ROWS',
					'values' => array(array((string) $field_value))
				);
				$update_field_keys[] = (string) $field_key;
			}

			if (empty($update_data))
			{
				return array(
					'success' => FALSE,
					'skipped' => FALSE,
					'message' => 'Kolom sinkronisasi spreadsheet tidak ditemukan.'
				);
			}

			$batch_result = $this->sheet_values_batch_update($update_data);
			if (!$batch_result['success'])
			{
				$batch_message = isset($batch_result['message']) ? trim((string) $batch_result['message']) : '';
				if (!$this->is_protected_cell_error($batch_message))
				{
					return $batch_result;
				}

				$written_cells = 0;
				$blocked_fields = array();
				for ($i = 0; $i < count($update_data); $i += 1)
				{
					$single_result = $this->sheet_values_batch_update(array($update_data[$i]));
					if (isset($single_result['success']) && $single_result['success'] === TRUE)
					{
						$written_cells += 1;
						continue;
					}

					$single_message = isset($single_result['message']) ? trim((string) $single_result['message']) : '';
					if ($this->is_protected_cell_error($single_message))
					{
						$field_key = isset($update_field_keys[$i]) ? (string) $update_field_keys[$i] : '';
						if ($field_key !== '' && !isset($blocked_fields[$field_key]))
						{
							$blocked_fields[$field_key] = TRUE;
						}
						continue;
					}

					return $single_result;
				}

				if ($written_cells <= 0 && empty($blocked_fields))
				{
					return $batch_result;
				}

				$warning = '';
				if (!empty($blocked_fields))
				{
					$labels = array();
					foreach (array_keys($blocked_fields) as $blocked_field_key)
					{
						$labels[] = $this->field_label_from_key($blocked_field_key);
					}
					$warning = 'Sebagian kolom tidak bisa diupdate karena proteksi sheet: '.implode(', ', $labels).'.';
				}

				return array(
					'success' => TRUE,
					'skipped' => FALSE,
					'message' => 'Data akun berhasil dikirim ke spreadsheet.',
					'sheet_row' => $sheet_row,
					'warning' => $warning
				);
			}

			return array(
				'success' => TRUE,
				'skipped' => FALSE,
				'message' => 'Data akun berhasil dikirim ke spreadsheet.',
				'sheet_row' => $sheet_row
			);
		}
		if ($action === 'delete')
		{
			return array(
				'success' => TRUE,
				'skipped' => TRUE,
				'message' => 'Baris spreadsheet tidak terhubung untuk akun ini.'
			);
		}

		$max_field_index = 0;
		foreach ($field_indexes as $field_key => $index_value)
		{
			$index_int = (int) $index_value;
			if ($index_int > $max_field_index)
			{
				$max_field_index = $index_int;
			}
		}

		$row_length = $header_count > ($max_field_index + 1) ? $header_count : ($max_field_index + 1);
		if ($row_length < 1)
		{
			$row_length = 1;
		}
		$row_values = array_fill(0, $row_length, '');
		foreach ($field_values as $field_key => $field_value)
		{
			if (!isset($field_indexes[$field_key]))
			{
				continue;
			}
			$index_int = (int) $field_indexes[$field_key];
			if ($index_int < 0)
			{
				continue;
			}
			if (!isset($row_values[$index_int]))
			{
				$row_values[$index_int] = '';
			}
			$row_values[$index_int] = (string) $field_value;
		}

		$append_result = $this->sheet_values_append($sheet_title, 'A:ZZ', array($row_values));
		if (!$append_result['success'])
		{
			return $append_result;
		}

		$updated_range = isset($append_result['data']['updates']['updatedRange'])
			? (string) $append_result['data']['updates']['updatedRange']
			: '';
		$appended_row = $this->extract_row_number_from_updated_range($updated_range);

		return array(
			'success' => TRUE,
			'skipped' => FALSE,
			'message' => 'Data akun berhasil ditambahkan ke spreadsheet.',
			'sheet_row' => $appended_row
		);
	}
	protected function resolve_sheet_context($allow_header_mutation)
	{
		if (is_array($this->sheet_context))
		{
			return array(
				'success' => TRUE,
				'skipped' => FALSE,
				'message' => 'OK',
				'data' => $this->sheet_context
			);
		}

		$spreadsheet_id = trim((string) $this->config['spreadsheet_id']);
		if ($spreadsheet_id === '')
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Spreadsheet ID belum diatur.'
			);
		}

		$sheet_title = trim((string) $this->config['sheet_title']);
		if ($sheet_title === '')
		{
			$title_result = $this->resolve_sheet_title_from_gid($spreadsheet_id, isset($this->config['sheet_gid']) ? (int) $this->config['sheet_gid'] : 0);
			if (!$title_result['success'])
			{
				return $title_result;
			}
			$sheet_title = isset($title_result['sheet_title']) ? (string) $title_result['sheet_title'] : '';
		}

		if ($sheet_title === '')
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Nama sheet tidak ditemukan dari gid.'
			);
		}

		$header_scan_result = $this->sheet_values_get($sheet_title, 'A1:ZZ10');
		if (!$header_scan_result['success'])
		{
			return $header_scan_result;
		}

		$header_scan_rows = isset($header_scan_result['data']['values']) && is_array($header_scan_result['data']['values'])
			? $header_scan_result['data']['values']
			: array();
		$header_row_number = 1;
		$header_values = array();
		$field_indexes = array();
		$best_score = -1;

		for ($scan_index = 0; $scan_index < count($header_scan_rows); $scan_index += 1)
		{
			$scan_row = is_array($header_scan_rows[$scan_index]) ? $header_scan_rows[$scan_index] : array();
			$scan_indexes = $this->map_header_field_indexes($scan_row);
			$score = count($scan_indexes);
			if (isset($scan_indexes['name']))
			{
				$score += 2;
			}

			if ($score > $best_score)
			{
				$best_score = $score;
				$header_row_number = $scan_index + 1;
				$header_values = $scan_row;
				$field_indexes = $scan_indexes;
			}
		}

		if ($best_score < 0)
		{
			$header_row_number = 1;
			$header_values = array();
			$field_indexes = array();
		}

		$field_labels = isset($this->config['field_labels']) && is_array($this->config['field_labels'])
			? $this->config['field_labels']
			: array();
		$header_changed = FALSE;

		foreach ($field_labels as $field_key => $field_label)
		{
			if (isset($field_indexes[$field_key]))
			{
				continue;
			}

			$header_values[] = (string) $field_label;
			$field_indexes[$field_key] = count($header_values) - 1;
			$header_changed = TRUE;
		}

		if ($header_changed && $allow_header_mutation === TRUE)
		{
			$update_result = $this->sheet_values_update($sheet_title, $header_row_number.':'.$header_row_number, array($header_values));
			if (!$update_result['success'])
			{
				return $update_result;
			}
		}

		$header_count = count($header_values);
		if ($header_count < 1)
		{
			$header_count = 1;
		}

		$this->sheet_context = array(
			'spreadsheet_id' => $spreadsheet_id,
			'sheet_title' => $sheet_title,
			'header_row_number' => (int) $header_row_number,
			'header_values' => $header_values,
			'header_count' => $header_count,
			'field_indexes' => $field_indexes
		);

		return array(
			'success' => TRUE,
			'skipped' => FALSE,
			'message' => 'OK',
			'data' => $this->sheet_context
		);
	}

	protected function resolve_sheet_title_from_gid($spreadsheet_id, $sheet_gid)
	{
		$token_result = $this->request_access_token();
		if (!$token_result['success'])
		{
			return $token_result;
		}

		$token = isset($token_result['access_token']) ? (string) $token_result['access_token'] : '';
		$url = 'https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheet_id).'?fields=sheets(properties(sheetId%2Ctitle))';
		$request_result = $this->http_request_json(
			'GET',
			$url,
			array('Authorization: Bearer '.$token),
			NULL
		);
		if (!$request_result['success'])
		{
			return $request_result;
		}

		$sheets = isset($request_result['data']['sheets']) && is_array($request_result['data']['sheets'])
			? $request_result['data']['sheets']
			: array();
		if (empty($sheets))
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Daftar sheet kosong.'
			);
		}

		$matched_title = '';
		for ($i = 0; $i < count($sheets); $i += 1)
		{
			$properties = isset($sheets[$i]['properties']) && is_array($sheets[$i]['properties'])
				? $sheets[$i]['properties']
				: array();
			$row_gid = isset($properties['sheetId']) ? (int) $properties['sheetId'] : 0;
			$row_title = isset($properties['title']) ? trim((string) $properties['title']) : '';
			if ($sheet_gid > 0 && $row_gid === $sheet_gid && $row_title !== '')
			{
				$matched_title = $row_title;
				break;
			}
		}

		if ($matched_title === '')
		{
			$properties = isset($sheets[0]['properties']) && is_array($sheets[0]['properties'])
				? $sheets[0]['properties']
				: array();
			$matched_title = isset($properties['title']) ? trim((string) $properties['title']) : '';
		}

		if ($matched_title === '')
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Nama sheet tidak ditemukan di metadata spreadsheet.'
			);
		}

		return array(
			'success' => TRUE,
			'skipped' => FALSE,
			'message' => 'OK',
			'sheet_title' => $matched_title
		);
	}

	protected function sheet_values_get($sheet_title, $a1_range)
	{
		$token_result = $this->request_access_token();
		if (!$token_result['success'])
		{
			return $token_result;
		}

		$spreadsheet_id = trim((string) $this->config['spreadsheet_id']);
		$token = isset($token_result['access_token']) ? (string) $token_result['access_token'] : '';
		$range = $this->quote_sheet_title($sheet_title).'!'.$a1_range;
		$url = 'https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheet_id).'/values/'.rawurlencode($range);
		$url .= '?majorDimension=ROWS';

		return $this->http_request_json(
			'GET',
			$url,
			array('Authorization: Bearer '.$token),
			NULL
		);
	}
	protected function sheet_values_update($sheet_title, $a1_range, $rows)
	{
		$token_result = $this->request_access_token();
		if (!$token_result['success'])
		{
			return $token_result;
		}

		$spreadsheet_id = trim((string) $this->config['spreadsheet_id']);
		$token = isset($token_result['access_token']) ? (string) $token_result['access_token'] : '';
		$range = $this->quote_sheet_title($sheet_title).'!'.$a1_range;
		$url = 'https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheet_id).'/values/'.rawurlencode($range);
		$url .= '?valueInputOption=USER_ENTERED';

		$payload = json_encode(array(
			'range' => $range,
			'majorDimension' => 'ROWS',
			'values' => is_array($rows) ? $rows : array()
		));

		return $this->http_request_json(
			'PUT',
			$url,
			array(
				'Authorization: Bearer '.$token,
				'Content-Type: application/json'
			),
			$payload
		);
	}

	protected function sheet_values_append($sheet_title, $a1_range, $rows)
	{
		$token_result = $this->request_access_token();
		if (!$token_result['success'])
		{
			return $token_result;
		}

		$spreadsheet_id = trim((string) $this->config['spreadsheet_id']);
		$token = isset($token_result['access_token']) ? (string) $token_result['access_token'] : '';
		$range = $this->quote_sheet_title($sheet_title).'!'.$a1_range;
		$url = 'https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheet_id).'/values/'.rawurlencode($range).':append';
		$url .= '?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';

		$payload = json_encode(array(
			'majorDimension' => 'ROWS',
			'values' => is_array($rows) ? $rows : array()
		));

		return $this->http_request_json(
			'POST',
			$url,
			array(
				'Authorization: Bearer '.$token,
				'Content-Type: application/json'
			),
			$payload
		);
	}

	protected function sheet_values_batch_update($data_rows)
	{
		$token_result = $this->request_access_token();
		if (!$token_result['success'])
		{
			return $token_result;
		}

		$spreadsheet_id = trim((string) $this->config['spreadsheet_id']);
		$token = isset($token_result['access_token']) ? (string) $token_result['access_token'] : '';
		$url = 'https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheet_id).'/values:batchUpdate';

		$payload = json_encode(array(
			'valueInputOption' => 'USER_ENTERED',
			'data' => is_array($data_rows) ? $data_rows : array()
		));

		return $this->http_request_json(
			'POST',
			$url,
			array(
				'Authorization: Bearer '.$token,
				'Content-Type: application/json'
			),
			$payload
		);
	}

	protected function request_access_token()
	{
		if ($this->access_token !== '' && $this->access_token_expire_at > (time() + 30))
		{
			return array(
				'success' => TRUE,
				'skipped' => FALSE,
				'message' => 'OK',
				'access_token' => $this->access_token
			);
		}

		$credentials_result = $this->load_service_account_credentials();
		if (!$credentials_result['success'])
		{
			return $credentials_result;
		}
		$credentials = $credentials_result['credentials'];

		$jwt_result = $this->build_service_account_jwt($credentials);
		if (!$jwt_result['success'])
		{
			return $jwt_result;
		}

		$token_uri = isset($credentials['token_uri']) && trim((string) $credentials['token_uri']) !== ''
			? (string) $credentials['token_uri']
			: 'https://oauth2.googleapis.com/token';
		$body = http_build_query(array(
			'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'assertion' => $jwt_result['jwt']
		), '', '&');

		$request_result = $this->http_request_json(
			'POST',
			$token_uri,
			array('Content-Type: application/x-www-form-urlencoded'),
			$body
		);
		if (!$request_result['success'])
		{
			return $request_result;
		}

		$token_value = isset($request_result['data']['access_token'])
			? trim((string) $request_result['data']['access_token'])
			: '';
		$expires_in = isset($request_result['data']['expires_in']) ? (int) $request_result['data']['expires_in'] : 3600;
		if ($token_value === '')
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Google OAuth tidak mengembalikan access_token.'
			);
		}

		$this->access_token = $token_value;
		$this->access_token_expire_at = time() + ($expires_in > 0 ? $expires_in : 3600);

		return array(
			'success' => TRUE,
			'skipped' => FALSE,
			'message' => 'OK',
			'access_token' => $this->access_token
		);
	}
	protected function load_service_account_credentials()
	{
		$path = isset($this->config['credential_json_path']) ? trim((string) $this->config['credential_json_path']) : '';
		$json_raw = isset($this->config['credential_json_raw']) ? trim((string) $this->config['credential_json_raw']) : '';
		$content = '';

		if ($json_raw !== '')
		{
			$content = $json_raw;
		}
		elseif ($path !== '' && is_file($path))
		{
			$read_result = @file_get_contents($path);
			if ($read_result !== FALSE)
			{
				$content = $read_result;
			}
		}

		if ($content === '')
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'File kredensial service account Google tidak ditemukan.'
			);
		}

		$decoded = json_decode($content, TRUE);
		if (!is_array($decoded))
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Isi kredensial service account tidak valid (JSON rusak).'
			);
		}

		$client_email = isset($decoded['client_email']) ? trim((string) $decoded['client_email']) : '';
		$private_key = isset($decoded['private_key']) ? (string) $decoded['private_key'] : '';
		if ($client_email === '' || $private_key === '')
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Kredensial service account belum lengkap (client_email/private_key).'
			);
		}

		return array(
			'success' => TRUE,
			'skipped' => FALSE,
			'message' => 'OK',
			'credentials' => $decoded
		);
	}

	protected function build_service_account_jwt($credentials)
	{
		$client_email = isset($credentials['client_email']) ? trim((string) $credentials['client_email']) : '';
		$private_key = isset($credentials['private_key']) ? (string) $credentials['private_key'] : '';
		if ($client_email === '' || $private_key === '')
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'client_email/private_key tidak tersedia untuk membuat JWT.'
			);
		}

		$token_uri = isset($credentials['token_uri']) && trim((string) $credentials['token_uri']) !== ''
			? (string) $credentials['token_uri']
			: 'https://oauth2.googleapis.com/token';
		$issued_at = time();
		$expires_at = $issued_at + 3600;

		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT'
		);
		$payload = array(
			'iss' => $client_email,
			'scope' => 'https://www.googleapis.com/auth/spreadsheets',
			'aud' => $token_uri,
			'iat' => $issued_at,
			'exp' => $expires_at
		);

		$header_encoded = $this->base64url_encode(json_encode($header));
		$payload_encoded = $this->base64url_encode(json_encode($payload));
		$unsigned = $header_encoded.'.'.$payload_encoded;
		$signature = '';
		$signed = openssl_sign($unsigned, $signature, $private_key, OPENSSL_ALGO_SHA256);
		if (!$signed)
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Gagal menandatangani JWT service account.'
			);
		}

		$jwt = $unsigned.'.'.$this->base64url_encode($signature);
		return array(
			'success' => TRUE,
			'skipped' => FALSE,
			'message' => 'OK',
			'jwt' => $jwt
		);
	}

	protected function base64url_encode($raw)
	{
		$encoded = base64_encode((string) $raw);
		$encoded = str_replace('+', '-', $encoded);
		$encoded = str_replace('/', '_', $encoded);
		$encoded = str_replace('=', '', $encoded);
		return $encoded;
	}

	protected function http_request_json($method, $url, $headers, $body)
	{
		$response = $this->http_request($method, $url, $headers, $body);
		if (!$response['success'])
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => isset($response['message']) ? (string) $response['message'] : 'HTTP request gagal.'
			);
		}

		$decoded = array();
		if (isset($response['body']) && trim((string) $response['body']) !== '')
		{
			$decoded_value = json_decode((string) $response['body'], TRUE);
			if (is_array($decoded_value))
			{
				$decoded = $decoded_value;
			}
		}

		return array(
			'success' => TRUE,
			'skipped' => FALSE,
			'message' => 'OK',
			'data' => $decoded
		);
	}
	protected function http_request($method, $url, $headers, $body)
	{
		$method = strtoupper(trim((string) $method));
		if ($method === '')
		{
			$method = 'GET';
		}
		$timeout = isset($this->config['request_timeout_seconds']) ? (int) $this->config['request_timeout_seconds'] : 15;
		if ($timeout <= 0)
		{
			$timeout = 15;
		}

		if (function_exists('curl_init'))
		{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
			if (is_array($headers) && !empty($headers))
			{
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			}
			if ($body !== NULL)
			{
				curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
			}

			$response_body = curl_exec($ch);
			$curl_error = curl_error($ch);
			$status_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($response_body === FALSE)
			{
				return array(
					'success' => FALSE,
					'status_code' => $status_code,
					'body' => '',
					'message' => 'HTTP cURL gagal: '.$curl_error
				);
			}

			if ($status_code < 200 || $status_code >= 300)
			{
				$error_message = 'HTTP gagal (status '.$status_code.').';
				$decoded_error = json_decode((string) $response_body, TRUE);
				if (is_array($decoded_error) && isset($decoded_error['error']['message']))
				{
					$error_message = (string) $decoded_error['error']['message'];
				}
				return array(
					'success' => FALSE,
					'status_code' => $status_code,
					'body' => (string) $response_body,
					'message' => $error_message
				);
			}

			return array(
				'success' => TRUE,
				'status_code' => $status_code,
				'body' => (string) $response_body,
				'message' => 'OK'
			);
		}

		$header_text = '';
		if (is_array($headers))
		{
			for ($i = 0; $i < count($headers); $i += 1)
			{
				$header_text .= $headers[$i]."\r\n";
			}
		}
		$context = stream_context_create(array(
			'http' => array(
				'method' => $method,
				'header' => $header_text,
				'content' => $body !== NULL ? (string) $body : '',
				'timeout' => $timeout,
				'ignore_errors' => TRUE
			)
		));
		$response_body = @file_get_contents($url, FALSE, $context);
		if ($response_body === FALSE)
		{
			return array(
				'success' => FALSE,
				'status_code' => 0,
				'body' => '',
				'message' => 'HTTP request gagal (stream).'
			);
		}

		$status_code = 0;
		if (isset($http_response_header) && is_array($http_response_header) && !empty($http_response_header))
		{
			$first_line = (string) $http_response_header[0];
			if (preg_match('/\s(\d{3})\s/', $first_line, $matches))
			{
				$status_code = (int) $matches[1];
			}
		}

		if ($status_code < 200 || $status_code >= 300)
		{
			$error_message = 'HTTP gagal (status '.$status_code.').';
			$decoded_error = json_decode((string) $response_body, TRUE);
			if (is_array($decoded_error) && isset($decoded_error['error']['message']))
			{
				$error_message = (string) $decoded_error['error']['message'];
			}
			return array(
				'success' => FALSE,
				'status_code' => $status_code,
				'body' => (string) $response_body,
				'message' => $error_message
			);
		}

		return array(
			'success' => TRUE,
			'status_code' => $status_code,
			'body' => (string) $response_body,
			'message' => 'OK'
		);
	}
	protected function map_header_field_indexes($header_values)
	{
		$indexes = array();
		if (!is_array($header_values))
		{
			return $indexes;
		}

		for ($i = 0; $i < count($header_values); $i += 1)
		{
			$field_key = $this->field_key_from_header(isset($header_values[$i]) ? (string) $header_values[$i] : '');
			if ($field_key !== '' && !isset($indexes[$field_key]))
			{
				$indexes[$field_key] = $i;
			}
		}

		return $indexes;
	}

	protected function field_key_from_header($header_value)
	{
		$key = strtolower(trim((string) $header_value));
		if ($key === '')
		{
			return '';
		}

		$key = preg_replace('/[^a-z0-9]+/', '', $key);
		$aliases = isset($this->config['header_aliases']) && is_array($this->config['header_aliases'])
			? $this->config['header_aliases']
			: array();
		if (isset($aliases[$key]))
		{
			return (string) $aliases[$key];
		}

		return '';
	}

	protected function get_row_value($row, $field_indexes, $field_key)
	{
		if (!is_array($row) || !is_array($field_indexes) || !isset($field_indexes[$field_key]))
		{
			return '';
		}

		$index = (int) $field_indexes[$field_key];
		if ($index < 0 || !isset($row[$index]))
		{
			return '';
		}

		return trim((string) $row[$index]);
	}

	protected function normalize_attendance_header($value)
	{
		$text = strtolower(trim((string) $value));
		if ($text === '')
		{
			return '';
		}

		$text = preg_replace('/[^a-z0-9]+/', '', $text);
		return trim((string) $text);
	}

	protected function build_attendance_field_indexes($header_values, $sub_header_values)
	{
		$indexes = array();
		$max_count = max(
			is_array($header_values) ? count($header_values) : 0,
			is_array($sub_header_values) ? count($sub_header_values) : 0
		);

		for ($i = 0; $i < $max_count; $i += 1)
		{
			$main = $this->normalize_attendance_header(is_array($header_values) && isset($header_values[$i]) ? $header_values[$i] : '');
			$sub = $this->normalize_attendance_header(is_array($sub_header_values) && isset($sub_header_values[$i]) ? $sub_header_values[$i] : '');
			if ($main === '' && $sub === '')
			{
				continue;
			}

			$field_key = '';
			if ($main === 'id')
			{
				$field_key = 'employee_id';
			}
			elseif ($main === 'nomakaryawan' || $main === 'namakaryawan' || $main === 'nama')
			{
				$field_key = 'name';
			}
			elseif ($main === 'jabatan')
			{
				$field_key = 'job_title';
			}
			elseif ($main === 'status' || $main === 'statuskaryawan')
			{
				$field_key = 'status';
			}
			elseif ($main === 'alamat')
			{
				$field_key = 'address';
			}
			elseif ($main === 'tlp' || $main === 'telp' || $main === 'telepon' || $main === 'phone')
			{
				$field_key = 'phone';
			}
			elseif ($main === 'cabang' || $main === 'branch')
			{
				$field_key = 'branch';
			}
			elseif ($main === 'tanggalabsen' || $main === 'tanggal')
			{
				$field_key = 'date_absen';
			}
			elseif ($main === 'namashift' || $main === 'shift')
			{
				$field_key = 'shift_name';
			}
			elseif ($main === 'waktumasuk')
			{
				$field_key = 'waktu_masuk';
			}
			elseif ($main === 'waktupulang')
			{
				$field_key = 'waktu_pulang';
			}
			elseif ($main === 'durasibekerja' || $main === 'durasi')
			{
				$field_key = 'durasi_bekerja';
			}
			elseif ($main === 'fotomasuk')
			{
				$field_key = 'foto_masuk';
			}
			elseif ($main === 'fotopulang')
			{
				$field_key = 'foto_pulang';
			}
			elseif ($main === 'sudahberapaabsen')
			{
				$field_key = 'sudah_berapa_absen';
			}
			elseif ($main === 'hariefektif')
			{
				$field_key = 'hari_efektif';
			}
			elseif ($main === 'totalhadir')
			{
				$field_key = 'total_hadir';
			}
			elseif ($main === 'totalizincuti')
			{
				$field_key = 'total_izin_cuti';
			}
			elseif ($main === 'totalalpha')
			{
				$field_key = 'total_alpha';
			}
			elseif ($main === 'alasantelat')
			{
				$field_key = 'alasan_telat';
			}
			elseif ($main === 'alasanizincuti')
			{
				$field_key = 'alasan_izin_cuti';
			}
			elseif ($main === 'alasanalpha')
			{
				$field_key = 'alasan_alpha';
			}
			elseif ($main === 'jenis')
			{
				if ($sub === 'masuk')
				{
					$field_key = 'jenis_masuk';
				}
				elseif ($sub === 'pulang')
				{
					$field_key = 'jenis_pulang';
				}
			}
			elseif ($main === 'telat')
			{
				if ($sub === '130menit')
				{
					$field_key = 'telat_1_30';
				}
				elseif ($sub === '3160menit')
				{
					$field_key = 'telat_31_60';
				}
				elseif ($sub === '13jam')
				{
					$field_key = 'telat_1_3';
				}
				elseif ($sub === '4jam')
				{
					$field_key = 'telat_gt_4';
				}
				else
				{
					$field_key = 'telat_duration';
				}
			}

			if ($field_key !== '' && !isset($indexes[$field_key]))
			{
				$indexes[$field_key] = $i;
			}
		}

		return $indexes;
	}

	protected function get_attendance_row_value($row, $field_indexes, $field_key)
	{
		if (!is_array($row) || !is_array($field_indexes) || !isset($field_indexes[$field_key]))
		{
			return '';
		}
		$index = (int) $field_indexes[$field_key];
		if ($index < 0 || !isset($row[$index]))
		{
			return '';
		}

		return trim((string) $row[$index]);
	}

	protected function parse_attendance_date_meta($value)
	{
		$text = trim((string) $value);
		$result = array(
			'raw' => $text,
			'start_date' => '',
			'end_date' => '',
			'anchor_date' => '',
			'month_key' => ''
		);

		if ($text === '')
		{
			return $result;
		}

		$matches = array();
		preg_match_all('/\d{4}-\d{2}-\d{2}/', $text, $matches);
		$dates = isset($matches[0]) && is_array($matches[0]) ? $matches[0] : array();
		if (!empty($dates))
		{
			$start = isset($dates[0]) ? (string) $dates[0] : '';
			$end = isset($dates[count($dates) - 1]) ? (string) $dates[count($dates) - 1] : $start;
			$result['start_date'] = $this->is_valid_attendance_date($start) ? $start : '';
			$result['end_date'] = $this->is_valid_attendance_date($end) ? $end : '';
			$result['anchor_date'] = $result['end_date'] !== '' ? $result['end_date'] : $result['start_date'];
			if ($result['anchor_date'] !== '')
			{
				$result['month_key'] = substr($result['anchor_date'], 0, 7);
			}
			return $result;
		}

		$timestamp = strtotime($text);
		if ($timestamp !== FALSE)
		{
			$date_value = date('Y-m-d', $timestamp);
			$result['start_date'] = $date_value;
			$result['end_date'] = $date_value;
			$result['anchor_date'] = $date_value;
			$result['month_key'] = substr($date_value, 0, 7);
		}

		return $result;
	}

	protected function is_valid_attendance_date($value)
	{
		$text = trim((string) $value);
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text))
		{
			return FALSE;
		}
		$timestamp = strtotime($text.' 00:00:00');
		if ($timestamp === FALSE)
		{
			return FALSE;
		}

		return date('Y-m-d', $timestamp) === $text;
	}

	protected function extract_shift_time_from_name($shift_name)
	{
		$shift_name = trim((string) $shift_name);
		if ($shift_name === '')
		{
			return '';
		}

		if (preg_match('/(\d{2}:\d{2})(?::\d{2})?\s*-\s*(\d{2}:\d{2})(?::\d{2})?/', $shift_name, $matches))
		{
			return $matches[1].' - '.$matches[2];
		}

		$all = array();
		preg_match_all('/(\d{2}:\d{2})/', $shift_name, $all);
		$times = isset($all[1]) && is_array($all[1]) ? $all[1] : array();
		if (count($times) >= 2)
		{
			return $times[0].' - '.$times[1];
		}

		return '';
	}

	protected function normalize_clock_time($value)
	{
		$text = trim((string) $value);
		if ($text === '')
		{
			return '';
		}

		if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $text, $matches))
		{
			$hour = (int) $matches[1];
			$minute = isset($matches[2]) ? (int) $matches[2] : 0;
			$second = isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : 0;
			return sprintf('%02d:%02d:%02d', max(0, $hour), max(0, $minute), max(0, $second));
		}

		$timestamp = strtotime($text);
		if ($timestamp === FALSE)
		{
			return '';
		}

		return date('H:i:s', $timestamp);
	}

	protected function normalize_duration_value($value)
	{
		$seconds = $this->duration_text_to_seconds($value);
		if ($seconds < 0)
		{
			$seconds = 0;
		}
		$hours = (int) floor($seconds / 3600);
		$minutes = (int) floor(($seconds % 3600) / 60);
		$secs = (int) ($seconds % 60);
		return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
	}

	protected function duration_text_to_seconds($value)
	{
		$text = strtolower(trim((string) $value));
		if ($text === '')
		{
			return 0;
		}

		$match_time = array();
		if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $text, $match_time))
		{
			$hour = (int) $match_time[1];
			$minute = (int) $match_time[2];
			$second = isset($match_time[3]) && $match_time[3] !== '' ? (int) $match_time[3] : 0;
			return ($hour * 3600) + ($minute * 60) + $second;
		}

		$hours = 0;
		$minutes = 0;
		$seconds = 0;
		if (preg_match('/(\d+)\s*jam/', $text, $matches))
		{
			$hours = (int) $matches[1];
		}
		if (preg_match('/(\d+)\s*menit/', $text, $matches))
		{
			$minutes = (int) $matches[1];
		}
		if (preg_match('/(\d+)\s*detik/', $text, $matches))
		{
			$seconds = (int) $matches[1];
		}

		return ($hours * 3600) + ($minutes * 60) + $seconds;
	}

	protected function calculate_month_work_policy_from_date($date_value)
	{
		$timestamp = strtotime((string) $date_value.' 00:00:00');
		if ($timestamp === FALSE)
		{
			$timestamp = time();
		}
		$year = (int) date('Y', $timestamp);
		$month = (int) date('n', $timestamp);
		$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		$weekly_off_days = (int) floor($days_in_month / 7);
		$work_days = max(1, $days_in_month - $weekly_off_days);

		return array(
			'year' => $year,
			'month' => $month,
			'days_in_month' => $days_in_month,
			'weekly_off_days' => $weekly_off_days,
			'work_days' => $work_days
		);
	}

	protected function load_json_array_file($file_path)
	{
		$file_path = trim((string) $file_path);
		if ($file_path === '' || !is_file($file_path))
		{
			return array();
		}

		$content = @file_get_contents($file_path);
		if ($content === FALSE || trim($content) === '')
		{
			return array();
		}
		if (substr($content, 0, 3) === "\xEF\xBB\xBF")
		{
			$content = substr($content, 3);
		}

		$decoded = json_decode($content, TRUE);
		return is_array($decoded) ? array_values($decoded) : array();
	}

	protected function save_json_array_file($file_path, $rows)
	{
		$file_path = trim((string) $file_path);
		if ($file_path === '')
		{
			return FALSE;
		}

		$directory = dirname($file_path);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0755, TRUE);
		}
		if (!is_dir($directory))
		{
			return FALSE;
		}

		$payload = json_encode(array_values(is_array($rows) ? $rows : array()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($payload === FALSE)
		{
			return FALSE;
		}

		return @file_put_contents($file_path, $payload) !== FALSE;
	}

	protected function parse_money_to_int($value)
	{
		$digits = preg_replace('/\D+/', '', (string) $value);
		if ($digits === '')
		{
			return 0;
		}

		return (int) $digits;
	}

	protected function resolve_salary_tier_from_amount($amount, $salary_profiles)
	{
		$amount_value = (int) $amount;
		if ($amount_value <= 0)
		{
			return 'A';
		}

		if ($amount_value <= 1500000)
		{
			return isset($salary_profiles['A']) ? 'A' : $this->first_salary_tier_key($salary_profiles);
		}
		if ($amount_value <= 2500000)
		{
			if (isset($salary_profiles['B']))
			{
				return 'B';
			}
			return isset($salary_profiles['A']) ? 'A' : $this->first_salary_tier_key($salary_profiles);
		}
		if ($amount_value <= 3500000)
		{
			if (isset($salary_profiles['C']))
			{
				return 'C';
			}
			if (isset($salary_profiles['B']))
			{
				return 'B';
			}
			return isset($salary_profiles['A']) ? 'A' : $this->first_salary_tier_key($salary_profiles);
		}

		if (isset($salary_profiles['D']))
		{
			return 'D';
		}
		if (isset($salary_profiles['C']))
		{
			return 'C';
		}
		if (isset($salary_profiles['B']))
		{
			return 'B';
		}

		return isset($salary_profiles['A']) ? 'A' : $this->first_salary_tier_key($salary_profiles);
	}

	protected function first_salary_tier_key($salary_profiles)
	{
		if (!is_array($salary_profiles) || empty($salary_profiles))
		{
			return 'A';
		}

		$keys = array_keys($salary_profiles);
		if (isset($keys[0]) && trim((string) $keys[0]) !== '')
		{
			return strtoupper(trim((string) $keys[0]));
		}

		return 'A';
	}

	protected function default_address()
	{
		return 'Kp. Kesekian Kalinya, Pandenglang, Banten';
	}

	protected function default_branch_name()
	{
		if (function_exists('absen_default_employee_branch'))
		{
			$value = trim((string) absen_default_employee_branch());
			if ($value !== '')
			{
				return $value;
			}
		}

		return 'Baros';
	}

	protected function resolve_branch_name($branch)
	{
		if (function_exists('absen_resolve_employee_branch'))
		{
			$resolved = trim((string) absen_resolve_employee_branch($branch));
			if ($resolved !== '')
			{
				return $resolved;
			}
		}

		$value = trim((string) $branch);
		if ($value === '')
		{
			return '';
		}

		$options = function_exists('absen_employee_branch_options') ? absen_employee_branch_options() : array('Baros', 'Cadasari');
		if (!is_array($options))
		{
			$options = array('Baros', 'Cadasari');
		}

		for ($i = 0; $i < count($options); $i += 1)
		{
			if (strcasecmp($value, (string) $options[$i]) === 0)
			{
				return (string) $options[$i];
			}
		}

		return '';
	}

	protected function default_job_title()
	{
		return 'Teknisi';
	}

	protected function resolve_job_title($job_title)
	{
		if (function_exists('absen_resolve_employee_job_title'))
		{
			$resolved = absen_resolve_employee_job_title($job_title);
			if ($resolved !== '')
			{
				return (string) $resolved;
			}
			return '';
		}

		return trim((string) $job_title);
	}
	protected function build_unique_username_from_name($name_value, &$used_usernames)
	{
		$base = $this->username_key_from_name($name_value);
		if ($base === '')
		{
			$base = 'user';
		}

		if (!isset($used_usernames[$base]))
		{
			$used_usernames[$base] = TRUE;
			return $base;
		}

		$suffix = 2;
		while ($suffix < 10000)
		{
			$suffix_text = '_'.$suffix;
			$max_length = 30 - strlen($suffix_text);
			if ($max_length < 1)
			{
				$max_length = 1;
			}
			$candidate = substr($base, 0, $max_length).$suffix_text;
			if (!isset($used_usernames[$candidate]))
			{
				$used_usernames[$candidate] = TRUE;
				return $candidate;
			}
			$suffix += 1;
		}

		return '';
	}

	protected function username_key_from_name($name_value)
	{
		$value = trim((string) $name_value);
		if ($value === '')
		{
			return '';
		}

		if (function_exists('iconv'))
		{
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
			if ($converted !== FALSE && trim((string) $converted) !== '')
			{
				$value = (string) $converted;
			}
		}

		$parts = preg_split('/\s+/', trim((string) $value));
		if (!is_array($parts))
		{
			$parts = array();
		}

		$selected = '';
		for ($i = 0; $i < count($parts); $i += 1)
		{
			$part = strtolower(trim((string) $parts[$i]));
			if ($part === '')
			{
				continue;
			}
			$part = preg_replace('/[^a-z0-9]+/', '', $part);
			if ($part === '')
			{
				continue;
			}
			if (strlen($part) >= 2)
			{
				$selected = $part;
				break;
			}
			if ($selected === '')
			{
				$selected = $part;
			}
		}

		if ($selected === '')
		{
			$selected = strtolower(trim((string) $value));
			$selected = preg_replace('/[^a-z0-9]+/', '', $selected);
		}
		if ($selected === '')
		{
			$selected = 'user';
		}

		if (strlen($selected) > 30)
		{
			$selected = substr($selected, 0, 30);
		}
		if (strlen($selected) < 3)
		{
			$selected = str_pad($selected, 3, 'x');
		}

		return $selected;
	}

	protected function normalize_name_key($value)
	{
		$name = strtolower(trim((string) $value));
		if ($name === '')
		{
			return '';
		}

		$name = preg_replace('/\s+/', ' ', $name);
		return trim((string) $name);
	}

	protected function normalize_name_lookup_key($value)
	{
		$name = strtolower(trim((string) $value));
		if ($name === '')
		{
			return '';
		}

		if (function_exists('iconv'))
		{
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
			if ($converted !== FALSE && trim((string) $converted) !== '')
			{
				$name = strtolower(trim((string) $converted));
			}
		}

		$name = preg_replace('/[^a-z0-9]+/', '', $name);
		return trim((string) $name);
	}

	protected function build_database_phone_lookup($account_book = array())
	{
		$lookup = array(
			'by_username' => array(),
			'by_name' => array(),
			'by_compact' => array(),
			'by_employee_id' => array(),
			'by_employee_no' => array()
		);
		if (is_array($account_book))
		{
			foreach ($account_book as $username_key => $row)
			{
				$username = strtolower(trim((string) $username_key));
				if ($username === '' || !is_array($row))
				{
					continue;
				}

				$role = strtolower(trim((string) (isset($row['role']) ? $row['role'] : 'user')));
				if ($role !== 'user')
				{
					continue;
				}

				$phone = isset($row['phone']) ? trim((string) $row['phone']) : '';
				if ($phone === '')
				{
					continue;
				}

				$lookup['by_username'][$username] = $phone;
				$display_name = isset($row['display_name']) && trim((string) $row['display_name']) !== ''
					? (string) $row['display_name']
					: $username;
				$name_key = $this->normalize_name_key($display_name);
				if ($name_key !== '' && !isset($lookup['by_name'][$name_key]))
				{
					$lookup['by_name'][$name_key] = $phone;
				}
				$compact_key = $this->normalize_name_lookup_key($display_name);
				if ($compact_key !== '' && !isset($lookup['by_compact'][$compact_key]))
				{
					$lookup['by_compact'][$compact_key] = $phone;
				}
			}
		}

		$context_result = $this->resolve_sheet_context(FALSE);
		if (!(isset($context_result['success']) && $context_result['success'] === TRUE))
		{
			return $lookup;
		}

		$context = isset($context_result['data']) && is_array($context_result['data'])
			? $context_result['data']
			: array();
		$field_indexes = isset($context['field_indexes']) && is_array($context['field_indexes'])
			? $context['field_indexes']
			: array();
		$header_values = isset($context['header_values']) && is_array($context['header_values'])
			? $context['header_values']
			: array();
		$sheet_title = isset($context['sheet_title']) ? trim((string) $context['sheet_title']) : '';
		$header_row_number = isset($context['header_row_number']) ? (int) $context['header_row_number'] : 1;
		if ($header_row_number <= 0)
		{
			$header_row_number = 1;
		}
		if ($sheet_title === '' || !isset($field_indexes['name']) || !isset($field_indexes['phone']))
		{
			return $lookup;
		}
		$database_employee_id_index = $this->resolve_header_index_by_tokens($header_values, array('id', 'idkaryawan', 'employeeid'));
		$database_employee_no_index = $this->resolve_header_index_by_tokens($header_values, array('no', 'nomor', 'nomorkaryawan', 'nourut'));

		$data_start_row = $header_row_number + 1;
		if ($data_start_row <= 1)
		{
			$data_start_row = 2;
		}

		$rows_result = $this->sheet_values_get($sheet_title, 'A'.$data_start_row.':ZZ');
		if (!(isset($rows_result['success']) && $rows_result['success'] === TRUE))
		{
			return $lookup;
		}

		$rows = isset($rows_result['data']['values']) && is_array($rows_result['data']['values'])
			? $rows_result['data']['values']
			: array();
		for ($i = 0; $i < count($rows); $i += 1)
		{
			$row = is_array($rows[$i]) ? $rows[$i] : array();
			$name_value = $this->get_row_value($row, $field_indexes, 'name');
			$phone_value = trim((string) $this->get_row_value($row, $field_indexes, 'phone'));
			if ($name_value === '' || $phone_value === '')
			{
				continue;
			}

			$name_key = $this->normalize_name_key($name_value);
			if ($name_key !== '')
			{
				$lookup['by_name'][$name_key] = $phone_value;
			}
			$compact_key = $this->normalize_name_lookup_key($name_value);
			if ($compact_key !== '')
			{
				$lookup['by_compact'][$compact_key] = $phone_value;
			}

			if ($database_employee_id_index >= 0 && isset($row[$database_employee_id_index]))
			{
				$employee_id_key = $this->normalize_identifier_key($row[$database_employee_id_index]);
				if ($employee_id_key !== '')
				{
					$lookup['by_employee_id'][$employee_id_key] = $phone_value;
				}
			}
			if ($database_employee_no_index >= 0 && isset($row[$database_employee_no_index]))
			{
				$employee_no_key = $this->normalize_identifier_key($row[$database_employee_no_index]);
				if ($employee_no_key !== '')
				{
					$lookup['by_employee_no'][$employee_no_key] = $phone_value;
				}
			}
		}

		return $lookup;
	}

	protected function resolve_phone_from_lookup($username_key, $name_value, $phone_lookup, $employee_id_value = '')
	{
		if (!is_array($phone_lookup))
		{
			return '';
		}

		$employee_id_key = $this->normalize_identifier_key($employee_id_value);
		if ($employee_id_key !== '' &&
			isset($phone_lookup['by_employee_id']) &&
			is_array($phone_lookup['by_employee_id']) &&
			isset($phone_lookup['by_employee_id'][$employee_id_key]))
		{
			$phone = trim((string) $phone_lookup['by_employee_id'][$employee_id_key]);
			if ($phone !== '')
			{
				return $phone;
			}
		}
		if ($employee_id_key !== '' &&
			isset($phone_lookup['by_employee_no']) &&
			is_array($phone_lookup['by_employee_no']) &&
			isset($phone_lookup['by_employee_no'][$employee_id_key]))
		{
			$phone = trim((string) $phone_lookup['by_employee_no'][$employee_id_key]);
			if ($phone !== '')
			{
				return $phone;
			}
		}

		$name_key = $this->normalize_name_key($name_value);
		if ($name_key !== '' &&
			isset($phone_lookup['by_name']) &&
			is_array($phone_lookup['by_name']) &&
			isset($phone_lookup['by_name'][$name_key]))
		{
			$phone = trim((string) $phone_lookup['by_name'][$name_key]);
			if ($phone !== '')
			{
				return $phone;
			}
		}

		$compact_key = $this->normalize_name_lookup_key($name_value);
		if ($compact_key !== '' &&
			isset($phone_lookup['by_compact']) &&
			is_array($phone_lookup['by_compact']) &&
			isset($phone_lookup['by_compact'][$compact_key]))
		{
			$phone = trim((string) $phone_lookup['by_compact'][$compact_key]);
			if ($phone !== '')
			{
				return $phone;
			}
		}

		$username = strtolower(trim((string) $username_key));
		if ($username !== '' &&
			isset($phone_lookup['by_username']) &&
			is_array($phone_lookup['by_username']) &&
			isset($phone_lookup['by_username'][$username]))
		{
			$phone = trim((string) $phone_lookup['by_username'][$username]);
			if ($phone !== '')
			{
				return $phone;
			}
		}

		return '';
	}

	protected function normalize_identifier_key($value)
	{
		$text = strtolower(trim((string) $value));
		if ($text === '')
		{
			return '';
		}

		if (function_exists('iconv'))
		{
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
			if ($converted !== FALSE && trim((string) $converted) !== '')
			{
				$text = strtolower(trim((string) $converted));
			}
		}

		$text = preg_replace('/[^a-z0-9]+/', '', $text);
		if ($text === '')
		{
			return '';
		}
		if (preg_match('/^\d+$/', $text))
		{
			$text = ltrim($text, '0');
			if ($text === '')
			{
				$text = '0';
			}
		}

		return $text;
	}

	protected function normalize_phone_compare_key($value)
	{
		$digits = preg_replace('/\D+/', '', (string) $value);
		return trim((string) $digits);
	}

	protected function resolve_header_index_by_tokens($header_values, $tokens)
	{
		if (!is_array($header_values) || !is_array($tokens))
		{
			return -1;
		}

		$normalized_tokens = array();
		for ($i = 0; $i < count($tokens); $i += 1)
		{
			$key = $this->normalize_attendance_header(isset($tokens[$i]) ? (string) $tokens[$i] : '');
			if ($key !== '')
			{
				$normalized_tokens[$key] = TRUE;
			}
		}
		if (empty($normalized_tokens))
		{
			return -1;
		}

		for ($i = 0; $i < count($header_values); $i += 1)
		{
			$key = $this->normalize_attendance_header(isset($header_values[$i]) ? (string) $header_values[$i] : '');
			if ($key !== '' && isset($normalized_tokens[$key]))
			{
				return (int) $i;
			}
		}

		return -1;
	}

	protected function account_rows_equal($row_a, $row_b)
	{
		$keys = array(
			'role',
			'password',
			'display_name',
			'branch',
			'phone',
			'shift_name',
			'shift_time',
			'salary_tier',
			'salary_monthly',
			'work_days',
			'job_title',
			'address',
			'profile_photo',
			'employee_status',
			'sheet_row',
			'sheet_sync_source'
		);

		for ($i = 0; $i < count($keys); $i += 1)
		{
			$key = $keys[$i];
			$value_a = isset($row_a[$key]) ? $row_a[$key] : NULL;
			$value_b = isset($row_b[$key]) ? $row_b[$key] : NULL;
			if ((string) $value_a !== (string) $value_b)
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	protected function is_protected_cell_error($message)
	{
		$text = strtolower(trim((string) $message));
		if ($text === '')
		{
			return FALSE;
		}

		if (strpos($text, 'protected cell') !== FALSE)
		{
			return TRUE;
		}
		if (strpos($text, 'trying to edit a protected') !== FALSE)
		{
			return TRUE;
		}
		if (strpos($text, 'sel dilindungi') !== FALSE)
		{
			return TRUE;
		}
		if (strpos($text, 'sel terproteksi') !== FALSE)
		{
			return TRUE;
		}
		if (strpos($text, 'objek dilindungi') !== FALSE)
		{
			return TRUE;
		}

		return FALSE;
	}

	protected function field_label_from_key($field_key)
	{
		$field_key = trim((string) $field_key);
		if ($field_key === '')
		{
			return 'Kolom';
		}

		$field_labels = isset($this->config['field_labels']) && is_array($this->config['field_labels'])
			? $this->config['field_labels']
			: array();
		if (isset($field_labels[$field_key]) && trim((string) $field_labels[$field_key]) !== '')
		{
			return trim((string) $field_labels[$field_key]);
		}

		$fallback = array(
			'name' => 'Nama',
			'job_title' => 'Jabatan',
			'status' => 'Status',
			'address' => 'Alamat',
			'phone' => 'Tlp',
			'branch' => 'Cabang',
			'salary' => 'Gaji Pokok'
		);
		if (isset($fallback[$field_key]))
		{
			return (string) $fallback[$field_key];
		}

		return ucfirst(str_replace('_', ' ', $field_key));
	}

	protected function build_employee_id_lookup_from_accounts($account_book)
	{
		$lookup = array();
		if (!is_array($account_book))
		{
			return $lookup;
		}

		$usernames = array();
		foreach ($account_book as $username_key => $row)
		{
			$username = strtolower(trim((string) $username_key));
			if ($username === '' || !is_array($row))
			{
				continue;
			}
			$role = strtolower(trim((string) (isset($row['role']) ? $row['role'] : 'user')));
			if ($role !== 'user')
			{
				continue;
			}
			$usernames[] = $username;
		}

		sort($usernames);
		$limit = min(count($usernames), 999);
		for ($i = 0; $i < $limit; $i += 1)
		{
			$sequence = $i + 1;
			if ($sequence < 100)
			{
				$lookup[$usernames[$i]] = str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
			}
			else
			{
				$lookup[$usernames[$i]] = (string) $sequence;
			}
		}

		return $lookup;
	}

	protected function resolve_leave_request_type_row($request_row)
	{
		$type_value = strtolower(trim((string) (isset($request_row['request_type']) ? $request_row['request_type'] : '')));
		if ($type_value === 'cuti' || $type_value === 'izin')
		{
			return $type_value;
		}

		$type_label = strtolower(trim((string) (isset($request_row['request_type_label']) ? $request_row['request_type_label'] : '')));
		if (strpos($type_label, 'cuti') !== FALSE)
		{
			return 'cuti';
		}
		if (strpos($type_label, 'izin') !== FALSE)
		{
			return 'izin';
		}

		return '';
	}

	protected function normalize_attendance_photo_cell($value)
	{
		$text = trim((string) $value);
		if ($text === '')
		{
			return '';
		}
		if (stripos($text, 'data:image/') === 0)
		{
			return 'Foto dari Web';
		}
		if (strlen($text) > 1000)
		{
			return substr($text, 0, 1000);
		}

		return $text;
	}

	protected function quote_sheet_title($sheet_title)
	{
		$title = str_replace("'", "''", (string) $sheet_title);
		return "'".$title."'";
	}

	protected function column_letter_from_index($index)
	{
		$index_value = (int) $index;
		if ($index_value < 0)
		{
			$index_value = 0;
		}

		$letter = '';
		$index_value += 1;
		while ($index_value > 0)
		{
			$mod = ($index_value - 1) % 26;
			$letter = chr(65 + $mod).$letter;
			$index_value = (int) floor(($index_value - 1) / 26);
		}

		return $letter;
	}

	protected function extract_row_number_from_updated_range($updated_range)
	{
		$range = trim((string) $updated_range);
		if ($range === '')
		{
			return 0;
		}

		if (preg_match('/!.*?(\d+):.*?(\d+)/', $range, $matches))
		{
			return (int) $matches[1];
		}
		if (preg_match('/!.*?(\d+)/', $range, $matches))
		{
			return (int) $matches[1];
		}

		return 0;
	}

	protected function read_sync_state()
	{
		$file_path = isset($this->config['state_file']) ? (string) $this->config['state_file'] : '';
		if ($file_path === '' || !is_file($file_path))
		{
			return array();
		}

		$content = @file_get_contents($file_path);
		if ($content === FALSE || trim($content) === '')
		{
			return array();
		}

		$decoded = json_decode($content, TRUE);
		return is_array($decoded) ? $decoded : array();
	}

	protected function write_sync_state($new_state)
	{
		$file_path = isset($this->config['state_file']) ? (string) $this->config['state_file'] : '';
		if ($file_path === '')
		{
			return FALSE;
		}

		$existing = $this->read_sync_state();
		if (!is_array($existing))
		{
			$existing = array();
		}
		$state = array_merge($existing, is_array($new_state) ? $new_state : array());

		$directory = dirname($file_path);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0755, TRUE);
		}

		if (!is_dir($directory))
		{
			return FALSE;
		}

		$payload = json_encode($state, JSON_PRETTY_PRINT);
		if ($payload === FALSE)
		{
			return FALSE;
		}

		return @file_put_contents($file_path, $payload) !== FALSE;
	}
}
