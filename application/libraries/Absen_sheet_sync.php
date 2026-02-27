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
		$this->CI->load->helper('absen_data_store');
		$attendance_mirror_helper = APPPATH.'helpers/attendance_mirror_helper.php';
		if (is_file($attendance_mirror_helper) && is_readable($attendance_mirror_helper))
		{
			$this->CI->load->helper('attendance_mirror');
		}
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
			'fixed_account_sheet_rows' => array(),
			'attendance_sync_enabled' => TRUE,
			'attendance_push_enabled' => TRUE,
			'attendance_sheet_gid' => 0,
			'attendance_sheet_title' => '',
			'attendance_sync_interval_seconds' => 60,
			'attendance_push_interval_seconds' => 300,
			'conflict_log_max_entries' => 2000,
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
		$actor = strtolower(trim((string) (isset($options['actor']) ? $options['actor'] : 'system')));
		if ($actor === '')
		{
			$actor = 'system';
		}
		$actor_context = isset($options['actor_context']) && is_array($options['actor_context'])
			? $options['actor_context']
			: array();
		$actor_ip = isset($actor_context['ip_address']) ? trim((string) $actor_context['ip_address']) : '';
		$actor_mac = isset($actor_context['mac_address']) ? trim((string) $actor_context['mac_address']) : '';
		$actor_computer = isset($actor_context['computer_name']) ? trim((string) $actor_context['computer_name']) : '';
		$conflict_logs = array();
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
			: array('shift_name' => 'Shift Pagi - Sore', 'shift_time' => '07:00 - 17:00');
		$default_password = isset($this->config['default_user_password']) && trim((string) $this->config['default_user_password']) !== ''
			? (string) $this->config['default_user_password']
			: '123';
		$default_password_hashed = function_exists('absen_hash_password')
			? absen_hash_password($default_password)
			: '';

		$account_book = function_exists('absen_load_account_book') ? absen_load_account_book() : array();
		if (!is_array($account_book))
		{
			$account_book = array();
		}

		$row_to_username = array();
		$name_to_username = array();
		$used_usernames = array();
		$fixed_name_to_username = array();
		$fixed_sheet_row_map = $this->fixed_account_sheet_row_map();
		$fixed_row_to_username = array();
		foreach ($fixed_sheet_row_map as $fixed_username_key => $fixed_row_number)
		{
			$fixed_row_int = (int) $fixed_row_number;
			if ($fixed_row_int > 1 && !isset($fixed_row_to_username[$fixed_row_int]))
			{
				$fixed_row_to_username[$fixed_row_int] = strtolower(trim((string) $fixed_username_key));
			}
		}
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

			$fixed_sheet_row = isset($fixed_sheet_row_map[$username_normalized]) ? (int) $fixed_sheet_row_map[$username_normalized] : 0;
			$sheet_row = isset($row['sheet_row']) ? (int) $row['sheet_row'] : 0;
			if ($fixed_sheet_row > 1)
			{
				$sheet_row = $fixed_sheet_row;
			}
			if ($sheet_row > 1 && !isset($row_to_username[$sheet_row]))
			{
				$row_to_username[$sheet_row] = $username_normalized;
			}

			$name_value = isset($row['display_name']) && trim((string) $row['display_name']) !== ''
				? (string) $row['display_name']
				: $username_normalized;
			$name_key = $this->normalize_name_key($name_value);
			if ($fixed_sheet_row > 1 && $name_key !== '' && !isset($fixed_name_to_username[$name_key]))
			{
				$fixed_name_to_username[$name_key] = $username_normalized;
			}
			if ($name_key !== '' && !isset($name_to_username[$name_key]) && $fixed_sheet_row <= 1)
			{
				$name_to_username[$name_key] = $username_normalized;
			}
		}
		foreach ($fixed_row_to_username as $fixed_row_number => $fixed_username_key)
		{
			$fixed_row_int = (int) $fixed_row_number;
			$fixed_username = strtolower(trim((string) $fixed_username_key));
			if ($fixed_row_int <= 1 || $fixed_username === '')
			{
				continue;
			}
			$row_to_username[$fixed_row_int] = $fixed_username;
			$used_usernames[$fixed_username] = TRUE;
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
			if ($name_key !== '' && isset($fixed_name_to_username[$name_key]))
			{
				$fixed_name_username = strtolower(trim((string) $fixed_name_to_username[$name_key]));
				$fixed_name_row = isset($fixed_sheet_row_map[$fixed_name_username]) ? (int) $fixed_sheet_row_map[$fixed_name_username] : 0;
				if ($fixed_name_row > 1 && $row_number !== $fixed_name_row)
				{
					continue;
				}
			}

			$username_key = '';
			if (isset($row_to_username[$row_number]))
			{
				$username_key = strtolower(trim((string) $row_to_username[$row_number]));
			}
			if ($username_key === '' && $name_key !== '' && isset($name_to_username[$name_key]))
			{
				$username_key = strtolower(trim((string) $name_to_username[$name_key]));
				if (isset($synced_usernames[$username_key]))
				{
					$username_key = '';
				}
			}
			if ($username_key === '')
			{
				$username_key = $this->build_unique_username_from_name($name_value, $used_usernames);
			}
			else
			{
				$used_usernames[$username_key] = TRUE;
			}

			if ($username_key === '' || $this->is_reserved_system_username($username_key))
			{
				continue;
			}
			$fixed_sheet_row_for_user = $this->resolve_fixed_account_sheet_row($username_key);
			if ($fixed_sheet_row_for_user > 1 && $row_number !== $fixed_sheet_row_for_user)
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
			$is_web_locked_account = !empty($existing) && $existing_sync_source === 'web';

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
				$phone_candidate = $this->normalize_phone_number($phone_raw);
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

			$phone_value = $this->normalize_phone_number($phone_raw);
			if ($phone_value === '')
			{
				$phone_value = isset($existing['phone']) ? $this->normalize_phone_number($existing['phone']) : '';
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
				: ($default_password_hashed !== '' ? $default_password_hashed : $default_password);
			$password_hash_value = isset($existing['password_hash']) && trim((string) $existing['password_hash']) !== ''
				? (string) $existing['password_hash']
				: $password_value;
			$force_password_change_value = isset($existing['force_password_change']) && (int) $existing['force_password_change'] === 1
				? 1
				: ($is_existing_row ? 0 : 1);
			$password_changed_at_value = isset($existing['password_changed_at'])
				? trim((string) $existing['password_changed_at'])
				: '';
			$profile_photo = isset($existing['profile_photo']) && trim((string) $existing['profile_photo']) !== ''
				? (string) $existing['profile_photo']
				: '';
			$coordinate_point = isset($existing['coordinate_point']) ? trim((string) $existing['coordinate_point']) : '';
			$record_version = isset($existing['record_version']) ? (int) $existing['record_version'] : 1;
			if ($record_version <= 0)
			{
				$record_version = 1;
			}

			$target_sheet_row = $fixed_sheet_row_for_user > 1 ? $fixed_sheet_row_for_user : (int) $row_number;
			$base_row = array(
				'role' => 'user',
				'password' => $password_value,
				'password_hash' => $password_hash_value !== '' ? $password_hash_value : $password_value,
				'force_password_change' => $force_password_change_value,
				'password_changed_at' => $password_changed_at_value,
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
				'coordinate_point' => $coordinate_point,
				'employee_status' => $status_value,
				'record_version' => $record_version,
				'sheet_row' => $target_sheet_row,
				'sheet_sync_source' => 'google_sheet'
			);

			$merged_row = is_array($existing) ? array_merge($existing, $base_row) : $base_row;
			if ($is_existing_row && !empty($existing))
			{
				// Proteksi field profil agar sync sheet -> web tidak mengacak data identitas.
				$profile_locked_keys = array(
					'display_name',
					'job_title',
					'employee_status',
					'address',
					'coordinate_point',
					'phone'
				);
				for ($k = 0; $k < count($profile_locked_keys); $k += 1)
				{
					$key = (string) $profile_locked_keys[$k];
					if (isset($existing[$key]))
					{
						$merged_row[$key] = $existing[$key];
					}
				}
			}
			if ($is_web_locked_account && !empty($existing))
			{
				// Akun sumber "web" tetap dikontrol dari web.
				// Saat sync akun dari sheet, field profil tetap dipertahankan dari data web.
				$locked_keys = array(
					'password',
					'display_name',
					'branch',
					'shift_name',
					'shift_time',
					'salary_tier',
					'salary_monthly',
					'work_days',
					'job_title',
					'address',
					'profile_photo',
					'coordinate_point',
					'employee_status',
					'sheet_row',
					'sheet_sync_source'
				);
				for ($k = 0; $k < count($locked_keys); $k += 1)
				{
					$key = (string) $locked_keys[$k];
					if (isset($existing[$key]))
					{
						$merged_row[$key] = $existing[$key];
					}
				}
				$merged_row['sheet_sync_source'] = 'web';

				$merged_row['phone'] = isset($existing['phone']) ? (string) $existing['phone'] : '';
			}
			if ($fixed_sheet_row_for_user > 1)
			{
				$merged_row['sheet_row'] = $fixed_sheet_row_for_user;
			}
			$processed += 1;
			$synced_usernames[$username_key] = TRUE;

			if (!isset($account_book[$username_key]) || !is_array($account_book[$username_key]))
			{
				$merged_row['sheet_last_sync_at'] = $sync_time;
				$merged_row['record_version'] = 1;
				$account_book[$username_key] = $merged_row;
				$changed = TRUE;
				$created += 1;
				continue;
			}

			$existing_row = $account_book[$username_key];
			if (!$this->account_rows_equal($existing_row, $merged_row))
			{
				$loggable_fields = array(
					'display_name',
					'phone',
					'branch',
					'shift_name',
					'shift_time',
					'salary_monthly',
					'work_days',
					'job_title',
					'address',
					'employee_status'
				);
				for ($idx = 0; $idx < count($loggable_fields); $idx += 1)
				{
					$field_key = (string) $loggable_fields[$idx];
					$old_value = isset($existing_row[$field_key]) ? (string) $existing_row[$field_key] : '';
					$new_value = isset($merged_row[$field_key]) ? (string) $merged_row[$field_key] : '';
					if ($this->conflict_values_equal($field_key, $old_value, $new_value))
					{
						continue;
					}
					if (trim($old_value) === '' || trim($new_value) === '')
					{
						continue;
					}

					$conflict_logs[] = $this->build_conflict_log_entry(array(
						'source' => 'sheet_to_web',
						'actor' => $actor,
						'ip_address' => $actor_ip,
						'mac_address' => $actor_mac,
						'computer_name' => $actor_computer,
						'username' => $username_key,
						'display_name' => $name_value,
						'field' => $field_key,
						'old_value' => $old_value,
						'new_value' => $new_value,
						'action' => 'overwrite',
						'sheet' => 'DATABASE',
						'row_number' => (int) $row_number,
						'note' => $is_web_locked_account
							? 'Nilai akun web ditimpa dari sheet pada kolom yang diizinkan (umumnya Tlp).'
							: 'Nilai akun web ditimpa dari sheet DATABASE saat Sync Akun dari Sheet.'
					));
				}

				$merged_row['sheet_last_sync_at'] = $sync_time;
				$existing_record_version = isset($existing_row['record_version']) ? (int) $existing_row['record_version'] : 1;
				if ($existing_record_version <= 0)
				{
					$existing_record_version = 1;
				}
				$merged_row['record_version'] = $existing_record_version + 1;
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
		$this->append_conflict_logs($conflict_logs);

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
		$overwrite_web_source = isset($options['overwrite_web_source']) && $options['overwrite_web_source'] === TRUE;
		$prune_missing_attendance = isset($options['prune_missing_attendance']) && $options['prune_missing_attendance'] === TRUE;
		$branch_scope_input = isset($options['branch_scope']) ? trim((string) $options['branch_scope']) : '';
		$branch_scope = '';
		if ($branch_scope_input !== '')
		{
			$branch_scope = $this->resolve_branch_name($branch_scope_input);
			if ($branch_scope === '')
			{
				return array(
					'success' => FALSE,
					'skipped' => FALSE,
					'message' => 'Cabang scope sync tidak valid.'
				);
			}
		}
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
			: array('shift_name' => 'Shift Pagi - Sore', 'shift_time' => '07:00 - 17:00');
		$default_password = isset($this->config['default_user_password']) && trim((string) $this->config['default_user_password']) !== ''
			? (string) $this->config['default_user_password']
			: '123';
		$default_password_hashed = function_exists('absen_hash_password')
			? absen_hash_password($default_password)
			: '';

		$account_book = function_exists('absen_load_account_book') ? absen_load_account_book() : array();
		if (!is_array($account_book))
		{
			$account_book = array();
		}

		$display_lookup = array();
		$display_lookup_compact = array();
		$employee_id_to_username = array();
		$preferred_attendance_row_by_username = array();
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

			$preferred_attendance_row = isset($row['attendance_sheet_row']) ? (int) $row['attendance_sheet_row'] : 0;
			if ($preferred_attendance_row > 1)
			{
				$preferred_attendance_row_by_username[$username_normalized] = $preferred_attendance_row;
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
		$employee_id_lookup = $this->build_employee_id_lookup_from_accounts($account_book);
		foreach ($employee_id_lookup as $employee_username => $employee_id)
		{
			$employee_username_key = strtolower(trim((string) $employee_username));
			if ($employee_username_key === '')
			{
				continue;
			}
			$employee_id_key = $this->normalize_identifier_key($employee_id);
			if ($employee_id_key === '' || isset($employee_id_to_username[$employee_id_key]))
			{
				continue;
			}
			$employee_id_to_username[$employee_id_key] = $employee_username_key;
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
		elseif (isset($field_indexes['branch_origin']))
		{
			$branch_column_letter = $this->column_letter_from_index((int) $field_indexes['branch_origin']);
		}
		$attendance_coordinate_updates = array();
		$attendance_coordinate_update_rows = array();
		$coordinate_column_letter = '';
		if (isset($field_indexes['coordinate_point']))
		{
			$coordinate_column_letter = $this->column_letter_from_index((int) $field_indexes['coordinate_point']);
		}
		$backfilled_phone_cells = 0;
		$phone_backfill_error = '';
		$backfilled_branch_cells = 0;
		$branch_backfill_error = '';
		$backfilled_coordinate_cells = 0;
		$coordinate_backfill_error = '';

		$attendance_file = APPPATH.'cache/attendance_records.json';
		$attendance_records = function_exists('absen_data_store_load_value')
			? absen_data_store_load_value('attendance_records', array(), $attendance_file)
			: $this->load_json_array_file($attendance_file);
		if (!is_array($attendance_records))
		{
			$attendance_records = array();
		}
		if (empty($attendance_records) && function_exists('attendance_mirror_load_all'))
		{
			$mirror_error = '';
			$mirror_rows = attendance_mirror_load_all($mirror_error);
			if (is_array($mirror_rows) && !empty($mirror_rows))
			{
				$attendance_records = array_values($mirror_rows);
			}
			if ($mirror_error !== '')
			{
				log_message('error', '[AttendanceMirror] '.$mirror_error);
			}
		}
		$purged_summary_snapshots = 0;
		if (!empty($attendance_records))
		{
			$filtered_attendance_records = array();
			for ($i = 0; $i < count($attendance_records); $i += 1)
			{
				$current_row = isset($attendance_records[$i]) && is_array($attendance_records[$i])
					? $attendance_records[$i]
					: array();
				if ($this->is_attendance_sheet_summary_snapshot_row($current_row))
				{
					$purged_summary_snapshots += 1;
					continue;
				}
				$filtered_attendance_records[] = $current_row;
			}
			$attendance_records = array_values($filtered_attendance_records);
		}
		$attendance_index = array();
		$attendance_index_by_month = array();
		for ($i = 0; $i < count($attendance_records); $i += 1)
		{
			$row_username = isset($attendance_records[$i]['username']) ? strtolower(trim((string) $attendance_records[$i]['username'])) : '';
			$row_date = isset($attendance_records[$i]['date']) ? trim((string) $attendance_records[$i]['date']) : '';
			if ($row_username === '' || !$this->is_valid_attendance_date($row_date))
			{
				continue;
			}
			$attendance_index[$row_username.'|'.$row_date] = $i;

			$row_month = isset($attendance_records[$i]['sheet_month']) ? trim((string) $attendance_records[$i]['sheet_month']) : '';
			if ($row_month === '' && strlen($row_date) >= 7)
			{
				$row_month = substr($row_date, 0, 7);
			}
			if ($row_month !== '')
			{
				$month_index_key = $row_username.'|'.$row_month;
				if (!isset($attendance_index_by_month[$month_index_key]))
				{
					$attendance_index_by_month[$month_index_key] = $i;
				}
				else
				{
					$prev_index = (int) $attendance_index_by_month[$month_index_key];
					$prev_date = isset($attendance_records[$prev_index]['date']) ? trim((string) $attendance_records[$prev_index]['date']) : '';
					if ($prev_date === '' || strcmp($row_date, $prev_date) >= 0)
					{
						$attendance_index_by_month[$month_index_key] = $i;
					}
				}
			}
		}

		$processed = 0;
		$created_accounts = 0;
		$updated_accounts = 0;
		$created_attendance = 0;
		$updated_attendance = 0;
		$pruned_attendance = $purged_summary_snapshots;
		$skipped_rows = 0;
		$changed_accounts = FALSE;
		$changed_attendance = $purged_summary_snapshots > 0;
		$sync_time = date('Y-m-d H:i:s');
		$synced_attendance_keys = array();
		$synced_usernames = array();
		$synced_months = array();
		$detected_attendance_row_by_username = array();

		for ($i = 0; $i < count($data_rows); $i += 1)
		{
			$row = is_array($data_rows[$i]) ? $data_rows[$i] : array();
			$row_number = $data_start_row + $i;
			$name_value = $this->get_attendance_row_value($row, $field_indexes, 'name');
			$employee_id_raw = $this->get_attendance_row_value($row, $field_indexes, 'employee_id');
			if ($name_value === '' && trim((string) $employee_id_raw) === '')
			{
				continue;
			}

			$name_key = $this->normalize_name_key($name_value);
			$name_compact = $this->normalize_name_lookup_key($name_value);
			$employee_id_key = $this->normalize_identifier_key($employee_id_raw);
			$username_key_from_employee_id = '';
			$username_key_from_name = '';
			if ($employee_id_key !== '' && isset($employee_id_to_username[$employee_id_key]))
			{
				$username_key_from_employee_id = (string) $employee_id_to_username[$employee_id_key];
			}
			if ($name_key !== '' && isset($display_lookup[$name_key]))
			{
				$username_key_from_name = (string) $display_lookup[$name_key];
			}
			if ($username_key_from_name === '' && $name_compact !== '' && isset($display_lookup_compact[$name_compact]))
			{
				$username_key_from_name = (string) $display_lookup_compact[$name_compact];
			}
			$username_key = $this->resolve_attendance_username_candidate_conflict(
				(int) $row_number,
				$username_key_from_employee_id,
				$username_key_from_name,
				$preferred_attendance_row_by_username
			);
			if ($username_key === '')
			{
				$skipped_rows += 1;
				continue;
			}
			if ($this->is_reserved_system_username($username_key))
			{
				$skipped_rows += 1;
				continue;
			}
			if (!isset($account_book[$username_key]) || !is_array($account_book[$username_key]))
			{
				// Strict mode: Sync Data Absen hanya menerima akun yang sudah ada,
				// supaya tidak muncul akun alias/duplikat dari nama sheet yang tidak presisi.
				$skipped_rows += 1;
				continue;
			}

			$existing_account = $account_book[$username_key];
			$is_existing_account = TRUE;
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
			$salary_raw = $this->get_attendance_row_value($row, $field_indexes, 'salary');
			$branch_raw = $this->get_attendance_row_value($row, $field_indexes, 'branch');
			if ($branch_raw === '')
			{
				$branch_raw = $this->get_attendance_row_value($row, $field_indexes, 'branch_origin');
			}
			$branch_attendance_raw = $this->get_attendance_row_value($row, $field_indexes, 'branch_attendance');
			if ($branch_raw === '')
			{
				$branch_raw = $branch_attendance_raw;
			}
			$coordinate_raw = $this->get_attendance_row_value($row, $field_indexes, 'coordinate_point');
			$shift_name_raw = $this->get_attendance_row_value($row, $field_indexes, 'shift_name');
			$cross_branch_raw = $this->get_attendance_row_value($row, $field_indexes, 'cross_branch_enabled');
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
				$total_izin = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'total_izin'));
				$total_cuti = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'total_cuti'));
			$total_izin_cuti = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'total_izin_cuti'));
			$combined_izin_cuti = $total_izin + $total_cuti;
			if ($combined_izin_cuti > $total_izin_cuti)
			{
				$total_izin_cuti = $combined_izin_cuti;
			}
			$total_alpha = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'total_alpha'));
			if ($record_date === '')
			{
				$has_monthly_summary_value =
					$total_hadir > 0 ||
					$sudah_absen > 0 ||
					$total_telat_1_30 > 0 ||
					$total_telat_31_60 > 0 ||
					$total_telat_1_3 > 0 ||
					$total_telat_gt_4 > 0 ||
					$total_izin_cuti > 0 ||
					$total_alpha > 0;
				if ($has_monthly_summary_value)
				{
					$month_key = date('Y-m');
					$record_date = $month_key.'-01';
					$date_label = date('d-m-Y', strtotime($record_date));
				}
			}

			$salary_tier = isset($existing_account['salary_tier']) ? strtoupper(trim((string) $existing_account['salary_tier'])) : 'A';
			if (!isset($salary_profiles[$salary_tier]))
			{
				$salary_tier = 'A';
			}
			$salary_monthly_existing = isset($existing_account['salary_monthly']) ? (int) $existing_account['salary_monthly'] : 0;
			$salary_from_sheet = $this->parse_money_to_int($salary_raw);
			if ($salary_from_sheet > 0)
			{
				$salary_monthly = $salary_from_sheet;
				$salary_tier = $this->resolve_salary_tier_from_amount($salary_monthly, $salary_profiles);
			}
			else
			{
				$salary_monthly = $salary_monthly_existing;
			}
			if ($salary_monthly <= 0)
			{
				$salary_monthly = isset($salary_profiles[$salary_tier]['salary_monthly'])
					? (int) $salary_profiles[$salary_tier]['salary_monthly']
					: 1000000;
			}

			$job_title_from_sheet = $this->resolve_job_title($job_title_raw);
			$job_title_existing = $this->resolve_job_title(isset($existing_account['job_title']) ? (string) $existing_account['job_title'] : '');
			$job_title_value = $is_existing_account && $job_title_existing !== ''
				? $job_title_existing
				: $job_title_from_sheet;
			if ($job_title_value === '')
			{
				$job_title_value = $job_title_existing;
			}
			if ($job_title_value === '')
			{
				$job_title_value = $this->default_job_title();
			}

			$status_from_sheet = trim((string) $status_raw);
			$status_existing = isset($existing_account['employee_status']) ? trim((string) $existing_account['employee_status']) : '';
			$status_value = $is_existing_account && $status_existing !== ''
				? $status_existing
				: $status_from_sheet;
			if ($status_value === '')
			{
				$status_value = $status_existing !== '' ? $status_existing : 'Aktif';
			}

			$address_from_sheet = trim((string) $address_raw);
			$address_existing = isset($existing_account['address']) ? trim((string) $existing_account['address']) : '';
			$address_value = $is_existing_account && $address_existing !== ''
				? $address_existing
				: $address_from_sheet;
			if ($address_value === '')
			{
				$address_value = $address_existing !== '' ? $address_existing : $this->default_address();
			}

			$phone_raw_value = $this->normalize_phone_number($phone_raw);
			$existing_phone_value = isset($existing_account['phone']) ? $this->normalize_phone_number($existing_account['phone']) : '';
			if ($is_existing_account && $existing_phone_value !== '')
			{
				$phone_value = $existing_phone_value;
			}
			else
			{
				$phone_from_lookup = $this->resolve_phone_from_lookup($username_key, $name_value, $database_phone_lookup, $employee_id_raw);
				$phone_value = $phone_from_lookup !== '' ? $this->normalize_phone_number($phone_from_lookup) : $phone_raw_value;
				if ($phone_value === '')
				{
					$phone_value = $existing_phone_value;
				}
			}
			// Isi otomatis Tlp hanya ketika kolom Tlp di Data Absen masih kosong.
			// Jangan overwrite nilai yang sudah ada agar tidak terjadi perubahan nomor tak terduga.
			$phone_changed = $phone_raw_value === '' && $phone_value !== '';
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
			$branch_existing = $this->resolve_branch_name(isset($existing_account['branch']) ? (string) $existing_account['branch'] : '');
			$branch_from_sheet = $this->resolve_branch_name($branch_raw_value);
			$branch_value = $is_existing_account && $branch_existing !== ''
				? $branch_existing
				: $branch_from_sheet;
			if ($branch_value === '')
			{
				$branch_value = $branch_existing;
			}
			if ($branch_value === '')
			{
				$branch_value = $this->default_branch_name();
			}
			if ($branch_scope !== '' && strcasecmp($branch_value, $branch_scope) !== 0)
			{
				$skipped_rows += 1;
				continue;
			}
			// Sama seperti Tlp: Cabang hanya auto-fill jika kolom Cabang masih kosong.
			$branch_changed = $branch_raw_value === '' && $branch_value !== '';
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
			$cross_branch_existing = $this->normalize_cross_branch_enabled_value(
				isset($existing_account['cross_branch_enabled']) ? $existing_account['cross_branch_enabled'] : 0
			);
			$cross_branch_raw_value = trim((string) $cross_branch_raw);
			$cross_branch_enabled = $cross_branch_raw_value !== ''
				? $this->normalize_cross_branch_enabled_value($cross_branch_raw_value)
				: $cross_branch_existing;

			$coordinate_raw_value = trim((string) $coordinate_raw);
			$coordinate_existing = isset($existing_account['coordinate_point']) ? trim((string) $existing_account['coordinate_point']) : '';
			$coordinate_value = $coordinate_raw_value !== '' ? $coordinate_raw_value : $coordinate_existing;
			$coordinate_changed = $coordinate_raw_value === '' && $coordinate_value !== '';
			if ($coordinate_changed && $coordinate_column_letter !== '' && !isset($attendance_coordinate_update_rows[$row_number]))
			{
				$attendance_coordinate_update_rows[$row_number] = TRUE;
				$attendance_coordinate_updates[] = array(
					'range' => $this->quote_sheet_title($attendance_sheet_title).'!'.$coordinate_column_letter.$row_number,
					'majorDimension' => 'ROWS',
					'values' => array(
						array((string) $coordinate_value)
					)
				);
			}

			$shift_name_existing = isset($existing_account['shift_name']) ? trim((string) $existing_account['shift_name']) : '';
			$shift_time_existing = isset($existing_account['shift_time']) ? trim((string) $existing_account['shift_time']) : '';
			$shift_name_sheet = trim((string) $shift_name_raw);
			$shift_time_sheet = $this->extract_shift_time_from_name($shift_name_sheet);
			$shift_key_sheet = $this->resolve_shift_key_from_values($shift_name_sheet, $shift_time_sheet, '');
			$shift_key_existing = $this->resolve_shift_key_from_values($shift_name_existing, $shift_time_existing, '');
			$shift_key_value = $shift_key_sheet !== ''
				? $shift_key_sheet
				: ($shift_key_existing !== '' ? $shift_key_existing : 'pagi');
			if (!isset($shift_profiles[$shift_key_value]) || !is_array($shift_profiles[$shift_key_value]))
			{
				$shift_key_value = 'pagi';
			}
			$shift_profile_value = isset($shift_profiles[$shift_key_value]) && is_array($shift_profiles[$shift_key_value])
				? $shift_profiles[$shift_key_value]
				: $default_shift;
			$shift_name_value = isset($shift_profile_value['shift_name'])
				? (string) $shift_profile_value['shift_name']
				: (string) $default_shift['shift_name'];
			$shift_time_value = isset($shift_profile_value['shift_time'])
				? (string) $shift_profile_value['shift_time']
				: (string) $default_shift['shift_time'];
			$existing_employee_id_value = $this->normalize_employee_id_value(
				isset($existing_account['employee_id']) ? $existing_account['employee_id'] : ''
			);
			$sheet_employee_id_value = $this->normalize_employee_id_value($employee_id_raw);
			$fallback_employee_id_value = isset($employee_id_lookup[$username_key])
				? $this->normalize_employee_id_value($employee_id_lookup[$username_key])
				: '';
			$employee_id_value = $existing_employee_id_value !== ''
				? $existing_employee_id_value
				: ($sheet_employee_id_value !== '' ? $sheet_employee_id_value : $fallback_employee_id_value);
			if ($employee_id_value === '' && $fallback_employee_id_value !== '')
			{
				$employee_id_value = $fallback_employee_id_value;
			}
			if ($employee_id_value !== '')
			{
				$employee_id_lookup[$username_key] = $employee_id_value;
				$employee_id_value_key = $this->normalize_identifier_key($employee_id_value);
				if ($employee_id_value_key !== '' &&
					(!isset($employee_id_to_username[$employee_id_value_key]) ||
						(string) $employee_id_to_username[$employee_id_value_key] === $username_key))
				{
					$employee_id_to_username[$employee_id_value_key] = $username_key;
				}
			}

			$password_value = isset($existing_account['password']) && trim((string) $existing_account['password']) !== ''
				? (string) $existing_account['password']
				: ($default_password_hashed !== '' ? $default_password_hashed : $default_password);
			$password_hash_value = isset($existing_account['password_hash']) && trim((string) $existing_account['password_hash']) !== ''
				? (string) $existing_account['password_hash']
				: $password_value;
			$force_password_change_value = isset($existing_account['force_password_change']) && (int) $existing_account['force_password_change'] === 1
				? 1
				: ($is_existing_account ? 0 : 1);
			$password_changed_at_value = isset($existing_account['password_changed_at'])
				? trim((string) $existing_account['password_changed_at'])
				: '';
			$profile_photo = isset($existing_account['profile_photo']) && trim((string) $existing_account['profile_photo']) !== ''
				? (string) $existing_account['profile_photo']
				: '';
			$existing_sheet_row = isset($existing_account['sheet_row']) ? (int) $existing_account['sheet_row'] : 0;
			$existing_sheet_source = isset($existing_account['sheet_sync_source']) ? (string) $existing_account['sheet_sync_source'] : '';
			$existing_sheet_last_sync = isset($existing_account['sheet_last_sync_at']) ? (string) $existing_account['sheet_last_sync_at'] : '';
			$display_name_existing = isset($existing_account['display_name']) ? trim((string) $existing_account['display_name']) : '';
			$display_name_value = $is_existing_account && $display_name_existing !== ''
				? $display_name_existing
				: $name_value;
			if ($display_name_value === '')
			{
				$display_name_value = $username_key;
			}

			$sheet_summary_month_key = $month_key;
			if (!preg_match('/^\d{4}\-\d{2}$/', $sheet_summary_month_key))
			{
				$sheet_summary_month_key = strlen($record_date) >= 7
					? substr($record_date, 0, 7)
					: date('Y-m');
			}
			$sheet_summary_sort_key = $record_date !== ''
				? ($record_date.' 23:59:59')
				: ($sheet_summary_month_key.'-01 00:00:00');
			$incoming_sheet_summary = array(
				'_sort_key' => $sheet_summary_sort_key,
				'month' => $sheet_summary_month_key,
				'hari_efektif' => (int) $work_days_value,
				'sudah_berapa_absen' => (int) $sudah_absen,
				'total_hadir' => (int) $total_hadir,
				'total_telat_1_30' => (int) $total_telat_1_30,
				'total_telat_31_60' => (int) $total_telat_31_60,
				'total_telat_1_3' => (int) $total_telat_1_3,
				'total_telat_gt_4' => (int) $total_telat_gt_4,
				'total_izin' => (int) $total_izin,
				'total_cuti' => (int) $total_cuti,
				'total_izin_cuti' => (int) $total_izin_cuti,
				'total_alpha' => (int) $total_alpha
			);
			$sheet_summary_by_month = isset($existing_account['sheet_summary_by_month']) && is_array($existing_account['sheet_summary_by_month'])
				? $existing_account['sheet_summary_by_month']
				: array();
			$current_month_summary = isset($sheet_summary_by_month[$sheet_summary_month_key]) && is_array($sheet_summary_by_month[$sheet_summary_month_key])
				? $sheet_summary_by_month[$sheet_summary_month_key]
				: array();
			$current_month_sort_key = isset($current_month_summary['_sort_key'])
				? trim((string) $current_month_summary['_sort_key'])
				: '';
			if ($current_month_sort_key === '' || strcmp($sheet_summary_sort_key, $current_month_sort_key) >= 0)
			{
				$sheet_summary_by_month[$sheet_summary_month_key] = $incoming_sheet_summary;
			}
			if (!empty($sheet_summary_by_month))
			{
				ksort($sheet_summary_by_month);
			}
			$active_sheet_summary = isset($sheet_summary_by_month[$sheet_summary_month_key]) && is_array($sheet_summary_by_month[$sheet_summary_month_key])
				? $sheet_summary_by_month[$sheet_summary_month_key]
				: $incoming_sheet_summary;

			$account_row = array(
				'role' => 'user',
				'password' => $password_value,
				'password_hash' => $password_hash_value !== '' ? $password_hash_value : $password_value,
				'force_password_change' => $force_password_change_value,
				'password_changed_at' => $password_changed_at_value,
				'display_name' => $display_name_value,
				'employee_id' => $employee_id_value,
				'branch' => $branch_value,
				'phone' => $phone_value,
				'shift_name' => $shift_name_value,
				'shift_time' => $shift_time_value,
				'salary_tier' => $salary_tier,
				'salary_monthly' => $salary_monthly,
				'work_days' => ($is_existing_account && isset($existing_account['work_days']) && (int) $existing_account['work_days'] > 0)
					? (int) $existing_account['work_days']
					: ($work_days_value > 0 ? $work_days_value : 22),
				'job_title' => $job_title_value,
				'address' => $address_value,
				'profile_photo' => $profile_photo,
				'coordinate_point' => $coordinate_value,
				'cross_branch_enabled' => $cross_branch_enabled,
				'employee_status' => $status_value,
				'sheet_summary' => $active_sheet_summary,
				'sheet_summary_by_month' => $sheet_summary_by_month,
				// Jangan isi sheet_row dari Data Absen (berbeda struktur dengan sheet akun).
				// Simpan hanya jika memang sudah ada sheet_row valid sebelumnya.
				'sheet_row' => $existing_sheet_row > 1 ? $existing_sheet_row : 0,
				'sheet_sync_source' => $existing_sheet_source !== '' ? $existing_sheet_source : 'google_sheet',
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
				if ($month_key === '' && strlen($record_date) >= 7)
				{
					$month_key = substr($record_date, 0, 7);
				}
				$synced_usernames[$username_key] = TRUE;
				if ($month_key !== '')
				{
					$synced_months[$month_key] = TRUE;
				}

				$check_in_time = $this->normalize_clock_time($this->get_attendance_row_value($row, $field_indexes, 'waktu_masuk'));
				$check_out_time = $this->normalize_clock_time($this->get_attendance_row_value($row, $field_indexes, 'waktu_pulang'));
				$has_check_in = $this->has_real_attendance_clock_time($check_in_time);
				$has_check_out = $this->has_real_attendance_clock_time($check_out_time);
				$is_summary_only_row = !$has_check_in && !$has_check_out;
				$is_range_date_row = $this->attendance_date_meta_is_range($date_meta);
				$late_duration = $this->normalize_duration_value($this->get_attendance_row_value($row, $field_indexes, 'telat_duration'));
				$work_duration = $this->normalize_duration_value($this->get_attendance_row_value($row, $field_indexes, 'durasi_bekerja'));
				$check_in_photo_value = $this->get_attendance_row_value($row, $field_indexes, 'foto_masuk');
				$check_out_photo_value = $this->get_attendance_row_value($row, $field_indexes, 'foto_pulang');
				$jenis_masuk_value = $this->get_attendance_row_value($row, $field_indexes, 'jenis_masuk');
				$jenis_pulang_value = $this->get_attendance_row_value($row, $field_indexes, 'jenis_pulang');
				$late_reason_value = $this->get_attendance_row_value($row, $field_indexes, 'alasan_telat');
				$alasan_izin_cuti_value = $this->get_attendance_row_value($row, $field_indexes, 'alasan_izin_cuti');
				$alasan_alpha_value = $this->get_attendance_row_value($row, $field_indexes, 'alasan_alpha');
				$has_presence_signal_strong =
					$has_check_in ||
					$has_check_out ||
					trim((string) $check_in_photo_value) !== '' ||
					trim((string) $check_out_photo_value) !== '';
				$has_daily_presence =
					$has_presence_signal_strong ||
					trim((string) $jenis_masuk_value) !== '' ||
					trim((string) $jenis_pulang_value) !== '';
				// Untuk row tanggal range (snapshot per-periode), import hanya bila ada sinyal absensi kuat
				// (jam/foto) agar data harian tetap bisa ketarik tanpa memproduksi row palsu dari kolom Jenis.
				$should_import_attendance_row = $is_range_date_row
					? $has_presence_signal_strong
					: $has_daily_presence;
				if ($should_import_attendance_row)
				{
				$today_key = date('Y-m-d');
				if ($record_date !== '' && strcmp($record_date, $today_key) > 0)
				{
					$record_date = $today_key;
					$date_label = date('d-m-Y', strtotime($record_date));
				}
				if ($is_range_date_row && $record_date === $today_key && $has_check_out)
				{
					$current_seconds = $this->clock_text_to_seconds(date('H:i:s'));
					$checkout_seconds = $this->clock_text_to_seconds($check_out_time);
					if ($checkout_seconds <= 0 || $checkout_seconds > $current_seconds)
					{
						$check_out_time = '';
						$work_duration = '';
						$check_out_photo_value = '';
						$jenis_pulang_value = '';
						$has_check_out = FALSE;
					}
				}
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
				$attendance_branch = $this->resolve_branch_name($branch_attendance_raw);
				if ($attendance_branch === '')
				{
					$attendance_branch = $branch_value;
				}
				if ($is_range_date_row && !$is_summary_only_row && $month_key !== '')
				{
					$current_signature = $this->build_attendance_sync_signature_from_values(
						$check_in_time,
						$check_out_time,
						$check_in_photo_value,
						$check_out_photo_value,
						$late_duration,
						$work_duration,
						$jenis_masuk_value,
						$jenis_pulang_value,
						$late_reason_value
					);
					$existing_signature_index = $this->find_attendance_index_by_signature(
						$attendance_records,
						$username_key,
						$month_key,
						$current_signature
					);
					if ($existing_signature_index >= 0)
					{
						$existing_signature_row = isset($attendance_records[$existing_signature_index]) && is_array($attendance_records[$existing_signature_index])
							? $attendance_records[$existing_signature_index]
							: array();
						$existing_signature_date = isset($existing_signature_row['date']) ? trim((string) $existing_signature_row['date']) : '';
						if ($this->is_valid_attendance_date($existing_signature_date))
						{
							$record_date = $existing_signature_date;
							$date_label = date('d-m-Y', strtotime($record_date));
						}
					}
				}
				$synced_attendance_keys[$username_key.'|'.$record_date] = TRUE;
				$attendance_row = array(
					'username' => $username_key,
					'date' => $record_date,
					'date_label' => $date_label !== '' ? $date_label : date('d-m-Y', strtotime($record_date)),
					'shift_name' => $shift_name_value,
					'shift_time' => $shift_time_value,
					'branch' => $attendance_branch,
					'check_in_time' => $check_in_time,
					'check_in_late' => $late_duration !== '' ? $late_duration : '00:00:00',
					'check_in_photo' => $check_in_photo_value,
					'check_in_lat' => '',
					'check_in_lng' => '',
					'check_in_accuracy_m' => '',
					'check_in_distance_m' => '',
					'late_reason' => $late_reason_value,
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
					'check_out_photo' => $check_out_photo_value,
					'check_out_lat' => '',
					'check_out_lng' => '',
					'check_out_accuracy_m' => '',
					'check_out_distance_m' => '',
					'jenis_masuk' => $jenis_masuk_value,
					'jenis_pulang' => $jenis_pulang_value,
					'alasan_izin_cuti' => $alasan_izin_cuti_value,
					'alasan_alpha' => $alasan_alpha_value,
					'record_version' => 1,
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
					'sheet_total_izin' => $total_izin,
					'sheet_total_cuti' => $total_cuti,
					'sheet_total_izin_cuti' => $total_izin_cuti,
					'sheet_total_alpha' => $total_alpha,
					'sheet_summary_only' => $is_summary_only_row ? 1 : 0
					);

				$attendance_key = $username_key.'|'.$record_date;
				$attendance_month_key = $month_key !== '' ? ($username_key.'|'.$month_key) : '';
				$index_existing = -1;
				if (isset($attendance_index[$attendance_key]))
				{
					$index_existing = (int) $attendance_index[$attendance_key];
				}
				// Penting: jangan fallback ke index per-bulan.
				// Jika fallback dipakai, record lama pada bulan yang sama bisa ikut tertimpa
				// saat tanggal sheet berubah (contoh: tanggal 20 menjadi 22).
				// Update hanya boleh terjadi untuk key exact username+tanggal.

				if ($index_existing >= 0)
				{
					$existing_attendance = isset($attendance_records[$index_existing]) && is_array($attendance_records[$index_existing])
						? $attendance_records[$index_existing]
						: array();
					$merged_attendance = array_merge($existing_attendance, $attendance_row);
					if ($is_summary_only_row)
					{
						$preserve_if_empty_keys = array(
							'check_in_time',
							'check_in_photo',
							'check_in_lat',
							'check_in_lng',
							'check_in_accuracy_m',
							'check_in_distance_m',
							'check_out_time',
							'work_duration',
							'check_out_photo',
							'check_out_lat',
							'check_out_lng',
							'check_out_accuracy_m',
							'check_out_distance_m',
							'jenis_masuk',
							'jenis_pulang',
							'late_reason'
						);
						for ($preserve_i = 0; $preserve_i < count($preserve_if_empty_keys); $preserve_i += 1)
						{
							$key = (string) $preserve_if_empty_keys[$preserve_i];
							$existing_value = isset($existing_attendance[$key]) ? trim((string) $existing_attendance[$key]) : '';
							if ($existing_value === '')
							{
								continue;
							}
							$incoming_value = isset($attendance_row[$key]) ? trim((string) $attendance_row[$key]) : '';
							if ($incoming_value === '')
							{
								$merged_attendance[$key] = $existing_attendance[$key];
							}
						}

						$existing_late = isset($existing_attendance['check_in_late']) ? trim((string) $existing_attendance['check_in_late']) : '';
						$incoming_late = isset($attendance_row['check_in_late']) ? trim((string) $attendance_row['check_in_late']) : '';
						if ($existing_late !== '' && ($incoming_late === '' || $incoming_late === '00:00:00'))
						{
							$merged_attendance['check_in_late'] = $existing_attendance['check_in_late'];
						}

						$existing_cut_amount = isset($existing_attendance['salary_cut_amount']) ? (string) $existing_attendance['salary_cut_amount'] : '0';
						$incoming_cut_amount = isset($attendance_row['salary_cut_amount']) ? (string) $attendance_row['salary_cut_amount'] : '0';
						$existing_cut_digits = preg_replace('/\D+/', '', $existing_cut_amount);
						$incoming_cut_digits = preg_replace('/\D+/', '', $incoming_cut_amount);
						if ((int) $existing_cut_digits > 0 && (int) $incoming_cut_digits <= 0)
						{
							$merged_attendance['salary_cut_amount'] = $existing_attendance['salary_cut_amount'];
							if (isset($existing_attendance['salary_cut_rule']) && trim((string) $existing_attendance['salary_cut_rule']) !== '')
							{
								$merged_attendance['salary_cut_rule'] = $existing_attendance['salary_cut_rule'];
							}
							if (isset($existing_attendance['salary_cut_category']) && trim((string) $existing_attendance['salary_cut_category']) !== '')
							{
								$merged_attendance['salary_cut_category'] = $existing_attendance['salary_cut_category'];
							}
						}
					}
					if (!$overwrite_web_source)
					{
						// Mode merge-only: jangan menurunkan sumber record lokal yang sudah bukan dari sheet.
						$existing_source = isset($existing_attendance['sheet_sync_source']) ? trim((string) $existing_attendance['sheet_sync_source']) : '';
						if ($existing_source !== '' && strtolower($existing_source) !== 'google_sheet_attendance')
						{
							$merged_attendance['sheet_sync_source'] = $existing_source;
						}
					}
					if (!$this->attendance_rows_equal($existing_attendance, $merged_attendance))
					{
						$existing_record_version = isset($existing_attendance['record_version']) ? (int) $existing_attendance['record_version'] : 1;
						if ($existing_record_version <= 0)
						{
							$existing_record_version = 1;
						}
						$merged_attendance['updated_at'] = $sync_time;
						$merged_attendance['record_version'] = $existing_record_version + 1;
						$attendance_records[$index_existing] = $merged_attendance;
						$updated_attendance += 1;
						$changed_attendance = TRUE;
					}

					$attendance_index[$attendance_key] = $index_existing;
					if ($attendance_month_key !== '')
					{
						$attendance_index_by_month[$attendance_month_key] = $index_existing;
					}
				}
				else
				{
					$attendance_row['updated_at'] = $sync_time;
					$attendance_row['record_version'] = 1;
					$attendance_records[] = $attendance_row;
					$new_index = count($attendance_records) - 1;
					$attendance_index[$attendance_key] = $new_index;
					if ($attendance_month_key !== '')
					{
						$attendance_index_by_month[$attendance_month_key] = $new_index;
					}
					$created_attendance += 1;
					$changed_attendance = TRUE;
				}
				}
			}

			$processed += 1;
		}

		if ($prune_missing_attendance && !empty($synced_attendance_keys) && !empty($synced_usernames) && !empty($synced_months))
		{
			$filtered_records = array();
			for ($i = 0; $i < count($attendance_records); $i += 1)
			{
				$current_row = isset($attendance_records[$i]) && is_array($attendance_records[$i])
					? $attendance_records[$i]
					: array();
				$row_username = isset($current_row['username']) ? strtolower(trim((string) $current_row['username'])) : '';
				$row_date = isset($current_row['date']) ? trim((string) $current_row['date']) : '';
				$row_month = isset($current_row['sheet_month']) ? trim((string) $current_row['sheet_month']) : '';
				if ($row_month === '' && strlen($row_date) >= 7)
				{
					$row_month = substr($row_date, 0, 7);
				}

				$in_sync_scope = $row_username !== '' && $row_month !== '' &&
					isset($synced_usernames[$row_username]) &&
					isset($synced_months[$row_month]);
				if (!$in_sync_scope)
				{
					$filtered_records[] = $current_row;
					continue;
				}

				$row_key = $row_username.'|'.$row_date;
				if ($row_date !== '' && isset($synced_attendance_keys[$row_key]))
				{
					$filtered_records[] = $current_row;
					continue;
				}

				$row_source = isset($current_row['sheet_sync_source'])
					? strtolower(trim((string) $current_row['sheet_sync_source']))
					: '';
				$can_prune = $row_source === 'google_sheet_attendance' || $overwrite_web_source;
				if ($can_prune)
				{
					$pruned_attendance += 1;
					$changed_attendance = TRUE;
					continue;
				}

				$filtered_records[] = $current_row;
			}

			if ($pruned_attendance > 0)
			{
				$attendance_records = array_values($filtered_records);
			}
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
		if (!empty($attendance_coordinate_updates))
		{
			$batch_coordinate_result = $this->sheet_values_batch_update($attendance_coordinate_updates);
			if (isset($batch_coordinate_result['success']) && $batch_coordinate_result['success'] === TRUE)
			{
				$backfilled_coordinate_cells = count($attendance_coordinate_updates);
			}
			else
			{
				$coordinate_backfill_error = isset($batch_coordinate_result['message']) && trim((string) $batch_coordinate_result['message']) !== ''
					? (string) $batch_coordinate_result['message']
					: 'Gagal mengisi kolom Titik Koordinat di Data Absen.';
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
			$saved_attendance = function_exists('absen_data_store_save_value')
				? absen_data_store_save_value('attendance_records', array_values($attendance_records), $attendance_file)
				: $this->save_json_array_file($attendance_file, $attendance_records);
			if (!$saved_attendance)
			{
				return array(
					'success' => FALSE,
					'skipped' => FALSE,
					'message' => 'Gagal menyimpan data absensi setelah sinkronisasi Data Absen.'
				);
			}

			if (function_exists('attendance_mirror_save_by_date'))
			{
				$mirror_error = '';
				$mirror_saved = attendance_mirror_save_by_date(array_values($attendance_records), TRUE, $mirror_error);
				if (!$mirror_saved || $mirror_error !== '')
				{
					log_message('error', '[AttendanceMirror] '.($mirror_error !== '' ? $mirror_error : 'Gagal sinkron mirror per tanggal setelah sync from sheet.'));
				}
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
				'pruned_attendance' => $pruned_attendance,
				'skipped_rows' => $skipped_rows,
				'changed_accounts' => $changed_accounts,
				'changed_attendance' => $changed_attendance,
				'backfilled_phone_cells' => $backfilled_phone_cells,
				'phone_backfill_error' => $phone_backfill_error,
				'backfilled_branch_cells' => $backfilled_branch_cells,
				'branch_backfill_error' => $branch_backfill_error,
				'backfilled_coordinate_cells' => $backfilled_coordinate_cells,
				'coordinate_backfill_error' => $coordinate_backfill_error
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
		if ($backfilled_coordinate_cells > 0)
		{
			$message .= ' Kolom Titik Koordinat tersinkron: '.$backfilled_coordinate_cells.' baris.';
		}
		if ($pruned_attendance > 0)
		{
			$message .= ' Data absen stale terhapus: '.$pruned_attendance.' baris.';
		}
		if ($phone_backfill_error !== '')
		{
			$message .= ' Isi balik Tlp ke sheet gagal: '.$phone_backfill_error;
		}
		if ($branch_backfill_error !== '')
		{
			$message .= ' Isi balik Cabang ke sheet gagal: '.$branch_backfill_error;
		}
		if ($coordinate_backfill_error !== '')
		{
			$message .= ' Isi balik Titik Koordinat ke sheet gagal: '.$coordinate_backfill_error;
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
			'pruned_attendance' => $pruned_attendance,
			'skipped_rows' => $skipped_rows,
			'changed_accounts' => $changed_accounts,
			'changed_attendance' => $changed_attendance,
			'backfilled_phone_cells' => $backfilled_phone_cells,
			'phone_backfill_error' => $phone_backfill_error,
			'backfilled_branch_cells' => $backfilled_branch_cells,
			'branch_backfill_error' => $branch_backfill_error,
			'backfilled_coordinate_cells' => $backfilled_coordinate_cells,
			'coordinate_backfill_error' => $coordinate_backfill_error
		);
	}

	public function read_attendance_sheet_month_summary_totals($month_key = '', $branch_scope_input = '')
	{
		$month_key = trim((string) $month_key);
		if (!preg_match('/^\d{4}\-\d{2}$/', $month_key))
		{
			$month_key = date('Y-m');
		}

		$branch_scope = '';
		$branch_scope_input = trim((string) $branch_scope_input);
		if ($branch_scope_input !== '')
		{
			$branch_scope = $this->resolve_branch_name($branch_scope_input);
			if ($branch_scope === '')
			{
				return array(
					'success' => FALSE,
					'message' => 'Cabang scope ringkasan tidak valid.'
				);
			}
		}

		if (!$this->is_enabled())
		{
			return array(
				'success' => FALSE,
				'message' => 'Sync spreadsheet dinonaktifkan.'
			);
		}
		if (!(isset($this->config['attendance_sync_enabled']) && $this->config['attendance_sync_enabled'] === TRUE))
		{
			return array(
				'success' => FALSE,
				'message' => 'Sync data absen spreadsheet dinonaktifkan.'
			);
		}

		$spreadsheet_id = trim((string) $this->config['spreadsheet_id']);
		if ($spreadsheet_id === '')
		{
			return array(
				'success' => FALSE,
				'message' => 'Spreadsheet ID belum diatur.'
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
					return array(
						'success' => FALSE,
						'message' => isset($title_result['message']) ? (string) $title_result['message'] : 'Gagal membaca metadata sheet absensi.'
					);
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
				'message' => 'Nama sheet Data Absen tidak ditemukan.'
			);
		}

		$header_result = $this->sheet_values_get($attendance_sheet_title, 'A1:ZZ20');
		if (!$header_result['success'])
		{
			return array(
				'success' => FALSE,
				'message' => isset($header_result['message']) ? (string) $header_result['message'] : 'Gagal membaca header Data Absen.'
			);
		}

		$header_rows = isset($header_result['data']['values']) && is_array($header_result['data']['values'])
			? $header_result['data']['values']
			: array();
		if (empty($header_rows))
		{
			return array(
				'success' => TRUE,
				'has_data' => FALSE,
				'total_hadir' => 0,
				'total_terlambat' => 0,
				'total_izin' => 0,
				'total_alpha' => 0,
				'users' => 0
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
				'message' => 'Header "Nama Karyawan / Tanggal Absen" tidak ditemukan.'
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
			return array(
				'success' => FALSE,
				'message' => isset($data_result['message']) ? (string) $data_result['message'] : 'Gagal membaca baris Data Absen.'
			);
		}

		$data_rows = isset($data_result['data']['values']) && is_array($data_result['data']['values'])
			? $data_result['data']['values']
			: array();
		if (empty($data_rows))
		{
			return array(
				'success' => TRUE,
				'has_data' => FALSE,
				'total_hadir' => 0,
				'total_terlambat' => 0,
				'total_izin' => 0,
				'total_alpha' => 0,
				'users' => 0
			);
		}

		$rows_by_employee = array();
		for ($i = 0; $i < count($data_rows); $i += 1)
		{
			$row = is_array($data_rows[$i]) ? $data_rows[$i] : array();
			$name_value = $this->get_attendance_row_value($row, $field_indexes, 'name');
			$employee_id_raw = $this->get_attendance_row_value($row, $field_indexes, 'employee_id');
			if ($name_value === '' && trim((string) $employee_id_raw) === '')
			{
				continue;
			}

			$date_absen_raw = $this->get_attendance_row_value($row, $field_indexes, 'date_absen');
			$date_meta = $this->parse_attendance_date_meta($date_absen_raw);
			$row_month = isset($date_meta['month_key']) ? trim((string) $date_meta['month_key']) : '';
			if (!preg_match('/^\d{4}\-\d{2}$/', $row_month))
			{
				continue;
			}
			if ($row_month !== $month_key)
			{
				continue;
			}

			if ($branch_scope !== '')
			{
				$row_branch = $this->resolve_branch_name($this->get_attendance_row_value($row, $field_indexes, 'branch'));
				if ($row_branch === '')
				{
					$row_branch = $this->resolve_branch_name($this->get_attendance_row_value($row, $field_indexes, 'branch_origin'));
				}
				if ($row_branch === '')
				{
					$row_branch = $this->resolve_branch_name($this->get_attendance_row_value($row, $field_indexes, 'branch_attendance'));
				}
				if ($row_branch === '' || strcasecmp($row_branch, $branch_scope) !== 0)
				{
					continue;
				}
			}

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
			$total_izin = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'total_izin'));
			$total_cuti = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'total_cuti'));
			$total_izin_cuti = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'total_izin_cuti'));
			$total_alpha = $this->parse_money_to_int($this->get_attendance_row_value($row, $field_indexes, 'total_alpha'));
			$combined_izin_cuti = $total_izin + $total_cuti;
			if ($combined_izin_cuti > $total_izin_cuti)
			{
				$total_izin_cuti = $combined_izin_cuti;
			}

			$employee_key = $this->normalize_employee_id_value($employee_id_raw);
			if ($employee_key === '')
			{
				$employee_key = $this->normalize_name_lookup_key($name_value);
			}
			if ($employee_key === '')
			{
				continue;
			}

			$sort_key = '';
			$anchor_date = isset($date_meta['anchor_date']) ? trim((string) $date_meta['anchor_date']) : '';
			if ($anchor_date !== '')
			{
				$sort_key = $anchor_date.' 23:59:59';
			}
			elseif ($this->attendance_date_meta_is_range($date_meta))
			{
				$start_date = isset($date_meta['start_date']) ? trim((string) $date_meta['start_date']) : '';
				$end_date = isset($date_meta['end_date']) ? trim((string) $date_meta['end_date']) : '';
				$sort_key = ($end_date !== '' ? $end_date : ($start_date !== '' ? $start_date : ($month_key.'-01'))).' 23:59:59';
			}
			else
			{
				$sort_key = $month_key.'-01 00:00:00';
			}

			$current_sort_key = isset($rows_by_employee[$employee_key]['_sort_key'])
				? trim((string) $rows_by_employee[$employee_key]['_sort_key'])
				: '';
			if ($current_sort_key !== '' && strcmp($sort_key, $current_sort_key) < 0)
			{
				continue;
			}

			$rows_by_employee[$employee_key] = array(
				'_sort_key' => $sort_key,
				'total_hadir' => max(0, (int) $total_hadir),
				'total_telat_1_30' => max(0, (int) $total_telat_1_30),
				'total_telat_31_60' => max(0, (int) $total_telat_31_60),
				'total_telat_1_3' => max(0, (int) $total_telat_1_3),
				'total_telat_gt_4' => max(0, (int) $total_telat_gt_4),
				'total_izin_cuti' => max(0, (int) $total_izin_cuti),
				'total_alpha' => max(0, (int) $total_alpha)
			);
		}

		if (empty($rows_by_employee))
		{
			return array(
				'success' => TRUE,
				'has_data' => FALSE,
				'total_hadir' => 0,
				'total_terlambat' => 0,
				'total_izin' => 0,
				'total_alpha' => 0,
				'users' => 0
			);
		}

		$total_hadir = 0;
		$total_terlambat = 0;
		$total_izin = 0;
		$total_alpha = 0;
		foreach ($rows_by_employee as $summary_row)
		{
			$total_hadir += isset($summary_row['total_hadir']) ? (int) $summary_row['total_hadir'] : 0;
			$total_terlambat +=
				(isset($summary_row['total_telat_1_30']) ? (int) $summary_row['total_telat_1_30'] : 0) +
				(isset($summary_row['total_telat_31_60']) ? (int) $summary_row['total_telat_31_60'] : 0) +
				(isset($summary_row['total_telat_1_3']) ? (int) $summary_row['total_telat_1_3'] : 0) +
				(isset($summary_row['total_telat_gt_4']) ? (int) $summary_row['total_telat_gt_4'] : 0);
			$total_izin += isset($summary_row['total_izin_cuti']) ? (int) $summary_row['total_izin_cuti'] : 0;
			$total_alpha += isset($summary_row['total_alpha']) ? (int) $summary_row['total_alpha'] : 0;
		}

		return array(
			'success' => TRUE,
			'has_data' => TRUE,
			'total_hadir' => (int) $total_hadir,
			'total_terlambat' => (int) $total_terlambat,
			'total_izin' => (int) $total_izin,
			'total_alpha' => (int) $total_alpha,
			'users' => count($rows_by_employee)
		);
	}

	public function sync_attendance_to_sheet($options = array())
	{
		$force = isset($options['force']) && $options['force'] === TRUE;
		$branch_scope_input = isset($options['branch_scope']) ? trim((string) $options['branch_scope']) : '';
		$branch_scope = '';
		if ($branch_scope_input !== '')
		{
			$branch_scope = $this->resolve_branch_name($branch_scope_input);
			if ($branch_scope === '')
			{
				return array(
					'success' => FALSE,
					'skipped' => FALSE,
					'message' => 'Cabang scope sync tidak valid.'
				);
			}
		}
		$actor = strtolower(trim((string) (isset($options['actor']) ? $options['actor'] : 'system')));
		if ($actor === '')
		{
			$actor = 'system';
		}
		$actor_context = isset($options['actor_context']) && is_array($options['actor_context'])
			? $options['actor_context']
			: array();
		$actor_ip = isset($actor_context['ip_address']) ? trim((string) $actor_context['ip_address']) : '';
		$actor_mac = isset($actor_context['mac_address']) ? trim((string) $actor_context['mac_address']) : '';
		$actor_computer = isset($actor_context['computer_name']) ? trim((string) $actor_context['computer_name']) : '';
		$conflict_logs = array();
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
		if (!$force && isset($this->config['attendance_push_enabled']) && $this->config['attendance_push_enabled'] !== TRUE)
		{
			return array(
				'success' => TRUE,
				'skipped' => TRUE,
				'message' => 'Auto sync web -> Data Absen dinonaktifkan.'
			);
		}

		$state = $this->read_sync_state();
		$interval_seconds = isset($this->config['attendance_push_interval_seconds'])
			? (int) $this->config['attendance_push_interval_seconds']
			: (isset($this->config['attendance_sync_interval_seconds'])
				? (int) $this->config['attendance_sync_interval_seconds']
				: (isset($this->config['sync_interval_seconds']) ? (int) $this->config['sync_interval_seconds'] : 60));
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
		$username_by_employee_id = array();
		foreach ($employee_id_lookup as $employee_username_key => $employee_id_value)
		{
			$employee_username = strtolower(trim((string) $employee_username_key));
			if ($employee_username === '')
			{
				continue;
			}
			$employee_id_key = $this->normalize_identifier_key($employee_id_value);
			if ($employee_id_key === '' || isset($username_by_employee_id[$employee_id_key]))
			{
				continue;
			}
			$username_by_employee_id[$employee_id_key] = $employee_username;
		}

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
			$row_branch = $this->resolve_branch_name(isset($row['branch']) ? (string) $row['branch'] : '');
			if ($row_branch === '')
			{
				$row_branch = $this->default_branch_name();
			}
			if ($branch_scope !== '' && strcasecmp($row_branch, $branch_scope) !== 0)
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
		$sheet_row_data_by_username = array();
		$sheet_row_meta_by_username = array();
		$stale_row_number_map = array();
		$expected_employee_id_key_by_username = array();
		foreach ($employee_id_lookup as $employee_username_key => $employee_id_value)
		{
			$username_key = strtolower(trim((string) $employee_username_key));
			if ($username_key === '')
			{
				continue;
			}
			$expected_key = $this->normalize_identifier_key($employee_id_value);
			if ($expected_key === '')
			{
				continue;
			}
			$expected_employee_id_key_by_username[$username_key] = $expected_key;
		}
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
			$employee_id_value = $this->get_attendance_row_value($row, $field_indexes, 'employee_id');
			$employee_id_key = $this->normalize_identifier_key($employee_id_value);
			if ($employee_id_key !== '' && isset($username_by_employee_id[$employee_id_key]))
			{
				$username_key = (string) $username_by_employee_id[$employee_id_key];
			}
			$name_key = $this->normalize_name_key($name_value);
			if ($username_key === '' && $name_key !== '' && isset($display_lookup[$name_key]))
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
				if ($branch_scope !== '')
				{
					continue;
				}

				$inferred_username = $this->username_key_from_name($name_value);
				if ($inferred_username !== '' && isset($user_rows[$inferred_username]))
				{
					$username_key = $inferred_username;
				}
				else
				{
					// Row nama yang tidak bisa dipetakan ke akun web aktif dianggap stale.
					$stale_row_number_map[(int) $row_number] = TRUE;
					continue;
				}
			}
			if ($branch_scope !== '')
			{
				$row_branch_scope = $this->resolve_branch_name($this->get_attendance_row_value($row, $field_indexes, 'branch'));
				if ($row_branch_scope === '')
				{
					$row_branch_scope = $this->resolve_branch_name($this->get_attendance_row_value($row, $field_indexes, 'branch_origin'));
				}
				if ($row_branch_scope === '')
				{
					$row_branch_scope = $this->resolve_branch_name($this->get_attendance_row_value($row, $field_indexes, 'branch_attendance'));
				}
				if ($row_branch_scope !== '' && strcasecmp($row_branch_scope, $branch_scope) !== 0)
				{
					continue;
				}
			}
			if (!isset($sheet_row_by_username[$username_key]))
			{
				$sheet_row_by_username[$username_key] = (int) $row_number;
				$sheet_row_data_by_username[$username_key] = $row;
				$sheet_row_meta_by_username[$username_key] = array(
					'row_number' => (int) $row_number,
					'employee_id_key' => $employee_id_key
				);
			}
			else
			{
				$expected_id_key = isset($expected_employee_id_key_by_username[$username_key])
					? (string) $expected_employee_id_key_by_username[$username_key]
					: '';
				$current_matches_expected = $expected_id_key !== '' && $employee_id_key !== '' && $employee_id_key === $expected_id_key;
				$existing_meta = isset($sheet_row_meta_by_username[$username_key]) && is_array($sheet_row_meta_by_username[$username_key])
					? $sheet_row_meta_by_username[$username_key]
					: array();
				$existing_row_number = isset($existing_meta['row_number']) ? (int) $existing_meta['row_number'] : 0;
				$existing_employee_id_key = isset($existing_meta['employee_id_key']) ? (string) $existing_meta['employee_id_key'] : '';
				$existing_matches_expected = $expected_id_key !== '' &&
					$existing_employee_id_key !== '' &&
					$existing_employee_id_key === $expected_id_key;

				if ($current_matches_expected && !$existing_matches_expected)
				{
					$sheet_row_by_username[$username_key] = (int) $row_number;
					$sheet_row_data_by_username[$username_key] = $row;
					$sheet_row_meta_by_username[$username_key] = array(
						'row_number' => (int) $row_number,
						'employee_id_key' => $employee_id_key
					);
					if ($existing_row_number > 1 && $existing_employee_id_key === '')
					{
						$stale_row_number_map[$existing_row_number] = TRUE;
					}
					continue;
				}

				if (!$current_matches_expected && $existing_matches_expected)
				{
					if ($employee_id_key === '')
					{
						$stale_row_number_map[(int) $row_number] = TRUE;
					}
					continue;
				}

				if ($employee_id_key !== '' &&
					$existing_employee_id_key !== '' &&
					$employee_id_key === $existing_employee_id_key)
				{
					$stale_row_number_map[(int) $row_number] = TRUE;
				}
			}
		}

		$attendance_records = function_exists('absen_data_store_load_value')
			? absen_data_store_load_value('attendance_records', array(), APPPATH.'cache/attendance_records.json')
			: $this->load_json_array_file(APPPATH.'cache/attendance_records.json');
		if (!is_array($attendance_records))
		{
			$attendance_records = array();
		}
		if (empty($attendance_records) && function_exists('attendance_mirror_load_all'))
		{
			$mirror_error = '';
			$mirror_rows = attendance_mirror_load_all($mirror_error);
			if (is_array($mirror_rows) && !empty($mirror_rows))
			{
				$attendance_records = array_values($mirror_rows);
			}
			if ($mirror_error !== '')
			{
				log_message('error', '[AttendanceMirror] '.$mirror_error);
			}
		}

		$leave_requests = function_exists('absen_data_store_load_value')
			? absen_data_store_load_value('leave_requests', array(), APPPATH.'cache/leave_requests.json')
			: $this->load_json_array_file(APPPATH.'cache/leave_requests.json');
		if (!is_array($leave_requests))
		{
			$leave_requests = array();
		}

		$records_by_user = array();
		$checkin_by_date = array();
		$checkout_by_date = array();
		$activity_dates = array();
		for ($i = 0; $i < count($attendance_records); $i += 1)
		{
			$row = is_array($attendance_records[$i]) ? $attendance_records[$i] : array();
			if ($this->is_attendance_sheet_summary_snapshot_row($row))
			{
				continue;
			}
			$username_key = strtolower(trim((string) (isset($row['username']) ? $row['username'] : '')));
			$date_key = trim((string) (isset($row['date']) ? $row['date'] : ''));
			if ($username_key === '' || !$this->is_valid_attendance_date($date_key) || strpos($date_key, $target_month) !== 0)
			{
				continue;
			}
			if (!isset($records_by_user[$username_key]))
			{
				$records_by_user[$username_key] = array();
			}
			$records_by_user[$username_key][] = $row;

			$check_in_raw = isset($row['check_in_time']) ? (string) $row['check_in_time'] : '';
			if ($this->has_real_attendance_clock_time($check_in_raw))
			{
				if (!isset($checkin_by_date[$date_key]))
				{
					$checkin_by_date[$date_key] = array();
				}
				$checkin_by_date[$date_key][$username_key] = TRUE;
				$activity_dates[$date_key] = TRUE;
			}

			$check_out_raw = isset($row['check_out_time']) ? (string) $row['check_out_time'] : '';
			if ($this->has_real_attendance_clock_time($check_out_raw))
			{
				if (!isset($checkout_by_date[$date_key]))
				{
					$checkout_by_date[$date_key] = array();
				}
				$checkout_by_date[$date_key][$username_key] = TRUE;
				$activity_dates[$date_key] = TRUE;
			}
		}

			$leave_by_user = array();
			$leave_izin_by_user = array();
			$leave_cuti_by_user = array();
			$leave_by_date = array();
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
				$request_reason_formatted = $request_type.' : '.($request_reason !== '' ? $request_reason : '-');
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
						'reason' => $request_reason_formatted
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
					if ($request_type === 'izin')
					{
						if (!isset($leave_izin_by_user[$username_key]))
						{
							$leave_izin_by_user[$username_key] = array();
						}
						$leave_izin_by_user[$username_key][$date_key] = TRUE;
					}
					elseif ($request_type === 'cuti')
					{
						if (!isset($leave_cuti_by_user[$username_key]))
						{
							$leave_cuti_by_user[$username_key] = array();
						}
						$leave_cuti_by_user[$username_key][$date_key] = TRUE;
					}
					if (!isset($leave_by_date[$date_key]))
					{
						$leave_by_date[$date_key] = array();
					}
				if (!isset($leave_by_date[$date_key][$username_key]) || $request_type === 'cuti')
				{
					$leave_by_date[$date_key][$username_key] = $request_type;
				}
				$activity_dates[$date_key] = TRUE;
			}
		}

		$activity_date_keys = array_keys($activity_dates);
		sort($activity_date_keys, SORT_STRING);
		$today_key = date('Y-m-d');
		$check_in_max_seconds = $this->clock_text_to_seconds('17:00:00');
		$current_seconds = $this->clock_text_to_seconds(date('H:i:s'));
		$alpha_reset_lookup_by_date = $this->load_alpha_reset_lookup_by_date($target_month);
		$alpha_reset_force_user_lookup = array();
		$reset_period_end = $target_month_end;
		$current_month_key = date('Y-m');
		if ($target_month === $current_month_key && strcmp($today_key, $reset_period_end) < 0)
		{
			$reset_period_end = $today_key;
		}
		$alpha_reset_force_user_lookup = $this->build_alpha_reset_force_user_lookup(
			$alpha_reset_lookup_by_date,
			$target_month_start,
			$reset_period_end
		);

		$batch_updates = array();
		$append_rows = array();
		$processed_users = 0;
		$updated_rows = 0;
		$appended_rows = 0;
		$skipped_users = 0;

		foreach ($user_rows as $username_key => $account_row)
		{
			$processed_users += 1;
			$account_sync_source = strtolower(trim((string) (isset($account_row['sheet_sync_source']) ? $account_row['sheet_sync_source'] : '')));
			$allow_profile_identity_sync = ($account_sync_source === 'web');
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
			$cross_branch_enabled = $this->normalize_cross_branch_enabled_value(
				isset($account_row['cross_branch_enabled']) ? $account_row['cross_branch_enabled'] : 0
			);
			$cross_branch_label = $cross_branch_enabled === 1 ? 'Ya' : 'Tidak';
			$salary_value = isset($account_row['salary_monthly']) ? (int) $account_row['salary_monthly'] : 0;
			if ($salary_value < 0)
			{
				$salary_value = 0;
			}
			$phone_value = isset($account_row['phone']) ? trim((string) $account_row['phone']) : '';
			$coordinate_point_value = isset($account_row['coordinate_point']) ? trim((string) $account_row['coordinate_point']) : '';
			$shift_name_default = isset($account_row['shift_name']) ? trim((string) $account_row['shift_name']) : '';
			if ($shift_name_default === '')
			{
				$shift_name_default = 'Shift Pagi - Sore';
			}
			$employee_id = isset($employee_id_lookup[$username_key]) ? (string) $employee_id_lookup[$username_key] : '-';
			$user_weekly_day_off = $this->normalize_weekly_day_off_value(isset($account_row['weekly_day_off']) ? $account_row['weekly_day_off'] : 1);

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
					'has_summary' => FALSE,
					'hari_efektif' => 0,
					'sudah_absen' => 0,
					'total_hadir' => 0,
					'telat_1_30' => 0,
					'telat_31_60' => 0,
					'telat_1_3' => 0,
					'telat_gt_4' => 0,
					'total_izin' => 0,
					'total_cuti' => 0,
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
				if ($sheet_month === '' && strlen($row_date) >= 7)
				{
					$sheet_month = substr($row_date, 0, 7);
				}
				if ($sheet_month === $target_month)
				{
					$has_summary = isset($row['sheet_total_hadir']) || isset($row['sheet_sudah_berapa_absen']) || isset($row['sheet_total_alpha']);
					if ($has_summary)
					{
						$baseline_totals['has_summary'] = TRUE;
						$baseline_totals['hari_efektif'] = max($baseline_totals['hari_efektif'], (int) (isset($row['sheet_hari_efektif']) ? $row['sheet_hari_efektif'] : 0));
						$baseline_totals['sudah_absen'] = max($baseline_totals['sudah_absen'], (int) (isset($row['sheet_sudah_berapa_absen']) ? $row['sheet_sudah_berapa_absen'] : 0));
							$baseline_totals['total_hadir'] = max($baseline_totals['total_hadir'], (int) (isset($row['sheet_total_hadir']) ? $row['sheet_total_hadir'] : 0));
							$baseline_totals['telat_1_30'] = max($baseline_totals['telat_1_30'], (int) (isset($row['sheet_total_telat_1_30']) ? $row['sheet_total_telat_1_30'] : 0));
							$baseline_totals['telat_31_60'] = max($baseline_totals['telat_31_60'], (int) (isset($row['sheet_total_telat_31_60']) ? $row['sheet_total_telat_31_60'] : 0));
							$baseline_totals['telat_1_3'] = max($baseline_totals['telat_1_3'], (int) (isset($row['sheet_total_telat_1_3']) ? $row['sheet_total_telat_1_3'] : 0));
							$baseline_totals['telat_gt_4'] = max($baseline_totals['telat_gt_4'], (int) (isset($row['sheet_total_telat_gt_4']) ? $row['sheet_total_telat_gt_4'] : 0));
							$baseline_totals['total_izin'] = max($baseline_totals['total_izin'], (int) (isset($row['sheet_total_izin']) ? $row['sheet_total_izin'] : 0));
							$baseline_totals['total_cuti'] = max($baseline_totals['total_cuti'], (int) (isset($row['sheet_total_cuti']) ? $row['sheet_total_cuti'] : 0));
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
				$leave_izin_dates_map = isset($leave_izin_by_user[$username_key]) && is_array($leave_izin_by_user[$username_key])
					? $leave_izin_by_user[$username_key]
					: array();
				$leave_cuti_dates_map = isset($leave_cuti_by_user[$username_key]) && is_array($leave_cuti_by_user[$username_key])
					? $leave_cuti_by_user[$username_key]
					: array();
				$total_izin = count($leave_izin_dates_map);
				$total_cuti = count($leave_cuti_dates_map);
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
			$is_current_target_month = ($target_month === date('Y-m'));
			$prefer_baseline_summary = (!empty($baseline_totals['has_summary']) && !$is_current_target_month);
			$is_force_alpha_reset_user = isset($alpha_reset_force_user_lookup[$username_key]);
			$total_hadir = max($computed_hadir, $baseline_totals['total_hadir']);
			$sudah_absen = max($total_hadir, $baseline_totals['sudah_absen']);
			if ($prefer_baseline_summary)
			{
				$total_telat_1_30 = max(0, (int) $baseline_totals['telat_1_30']);
				$total_telat_31_60 = max(0, (int) $baseline_totals['telat_31_60']);
				$total_telat_1_3 = max(0, (int) $baseline_totals['telat_1_3']);
				$total_telat_gt_4 = max(0, (int) $baseline_totals['telat_gt_4']);
			}
			else
			{
				$total_telat_1_30 = max(0, (int) $computed_late_1_30);
				$total_telat_31_60 = max(0, (int) $computed_late_31_60);
				$total_telat_1_3 = max(0, (int) $computed_late_1_3);
				$total_telat_gt_4 = max(0, (int) $computed_late_gt_4);
			}
				$total_izin = max($total_izin, $baseline_totals['total_izin']);
				$total_cuti = max($total_cuti, $baseline_totals['total_cuti']);
				$total_izin_cuti = max($total_izin + $total_cuti, max($leave_count, $baseline_totals['total_izin_cuti']));

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

			$total_alpha = 0;
			if ($is_force_alpha_reset_user)
			{
				$total_alpha = 0;
			}
			elseif ($prefer_baseline_summary)
			{
				$total_alpha = max(0, (int) $baseline_totals['total_alpha']);
			}
			else
			{
				for ($activity_i = 0; $activity_i < count($activity_date_keys); $activity_i += 1)
				{
					$activity_date = (string) $activity_date_keys[$activity_i];
					if (!$this->is_valid_attendance_date($activity_date))
					{
						continue;
					}
					// Jangan menghitung tanggal di masa depan.
					if (strcmp($activity_date, $today_key) > 0)
					{
						continue;
					}
					// Hari ini baru mulai hitung alpha setelah batas jam masuk.
					if ($activity_date === $today_key && $current_seconds < $check_in_max_seconds)
					{
						continue;
					}

					$day_has_activity =
						(isset($checkin_by_date[$activity_date]) && !empty($checkin_by_date[$activity_date])) ||
						(isset($checkout_by_date[$activity_date]) && !empty($checkout_by_date[$activity_date])) ||
						(isset($leave_by_date[$activity_date]) && !empty($leave_by_date[$activity_date]));
					if (!$day_has_activity)
					{
						continue;
					}

					$weekday_n = (int) date('N', strtotime($activity_date.' 00:00:00'));
					if ($weekday_n === $user_weekly_day_off)
					{
						continue;
					}

					if (isset($checkin_by_date[$activity_date][$username_key]))
					{
						continue;
					}
					if (isset($leave_by_date[$activity_date][$username_key]))
					{
						continue;
					}
					if (isset($alpha_reset_lookup_by_date[$activity_date]) &&
						isset($alpha_reset_lookup_by_date[$activity_date][$username_key]))
					{
						continue;
					}

					$total_alpha += 1;
				}
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
			$jenis_masuk_raw = '';
			$jenis_pulang_raw = '';
			$alasan_telat = '';
			$shift_name_value = $shift_name_default;
			if ($latest_record !== NULL && is_array($latest_record))
			{
				$shift_name_latest = trim((string) (isset($latest_record['shift_name']) ? $latest_record['shift_name'] : ''));
				// Nama shift di sheet harus mengikuti profil akun terbaru.
				// Shift dari record absensi lama hanya dipakai kalau profil akun kosong.
				if ($shift_name_value === '' && $shift_name_latest !== '')
				{
					$shift_name_value = $shift_name_latest;
				}
				$waktu_masuk = $this->normalize_clock_time(isset($latest_record['check_in_time']) ? $latest_record['check_in_time'] : '');
				$telat_duration = $this->normalize_duration_value(isset($latest_record['check_in_late']) ? $latest_record['check_in_late'] : '00:00:00');
				$waktu_pulang = $this->normalize_clock_time(isset($latest_record['check_out_time']) ? $latest_record['check_out_time'] : '');
				$durasi_bekerja = $this->normalize_duration_value(isset($latest_record['work_duration']) ? $latest_record['work_duration'] : '');
				$foto_masuk_raw = trim((string) (isset($latest_record['check_in_photo']) ? $latest_record['check_in_photo'] : ''));
				$foto_pulang_raw = trim((string) (isset($latest_record['check_out_photo']) ? $latest_record['check_out_photo'] : ''));
				$jenis_masuk_raw = trim((string) (isset($latest_record['jenis_masuk']) ? $latest_record['jenis_masuk'] : ''));
				$jenis_pulang_raw = trim((string) (isset($latest_record['jenis_pulang']) ? $latest_record['jenis_pulang'] : ''));
				$alasan_telat = trim((string) (isset($latest_record['late_reason']) ? $latest_record['late_reason'] : ''));
			}

			$foto_masuk_value = $this->normalize_attendance_photo_cell($foto_masuk_raw);
			$foto_pulang_value = $this->normalize_attendance_photo_cell($foto_pulang_raw);
			$jenis_masuk = $jenis_masuk_raw !== '' ? $jenis_masuk_raw : '';
			if ($jenis_masuk === '' && ($waktu_masuk !== '' || $foto_masuk_raw !== ''))
			{
				$jenis_masuk = 'Absen Masuk';
			}
			$jenis_pulang = $jenis_pulang_raw !== '' ? $jenis_pulang_raw : '';
			if ($jenis_pulang === '' && ($waktu_pulang !== '' || $foto_pulang_raw !== ''))
			{
				$jenis_pulang = 'Absen Pulang';
			}
			$alasan_izin_cuti = isset($latest_leave_reason_by_user[$username_key]['reason'])
				? trim((string) $latest_leave_reason_by_user[$username_key]['reason'])
				: '';
			$alasan_alpha = '';
			if (!$is_force_alpha_reset_user && $total_alpha > 0)
			{
				$alasan_alpha = $latest_record !== NULL && isset($latest_record['alasan_alpha'])
					? trim((string) $latest_record['alasan_alpha'])
					: '';
				if ($alasan_alpha === '' && empty($baseline_totals['has_summary']))
				{
					$alasan_alpha = 'Tidak hadir';
				}
			}
			$branch_attendance_value = $branch_value;
			if ($latest_record !== NULL && is_array($latest_record))
			{
				$branch_attendance_latest = $this->resolve_branch_name(
					isset($latest_record['branch']) ? (string) $latest_record['branch'] : ''
				);
				if ($branch_attendance_latest !== '')
				{
					$branch_attendance_value = $branch_attendance_latest;
				}
			}

			$field_values = array(
				'employee_id' => $employee_id,
				'name' => $display_name,
				'job_title' => $job_title,
				'status' => $status_value,
				'address' => $address_value,
				'salary' => $salary_value > 0 ? (string) $salary_value : '',
				'phone' => $phone_value,
				'branch' => $branch_value,
				'branch_origin' => $branch_value,
				'branch_attendance' => $branch_attendance_value,
				'cross_branch_enabled' => $cross_branch_label,
				'coordinate_point' => $coordinate_point_value,
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
					'total_izin' => (string) $total_izin,
					'total_cuti' => (string) $total_cuti,
					'total_izin_cuti' => (string) $total_izin_cuti,
					'total_alpha' => (string) $total_alpha,
				'alasan_telat' => $alasan_telat,
				'alasan_izin_cuti' => $alasan_izin_cuti,
				'alasan_alpha' => $alasan_alpha
			);

			$sheet_row = isset($sheet_row_by_username[$username_key]) ? (int) $sheet_row_by_username[$username_key] : 0;
			if ($sheet_row > 1)
			{
				$current_sheet_row = isset($sheet_row_data_by_username[$username_key]) && is_array($sheet_row_data_by_username[$username_key])
					? $sheet_row_data_by_username[$username_key]
					: array();
				$row_update_data = array();
				$profile_identity_fields = array(
					'name',
					'job_title',
					'status',
					'address',
					'phone',
					'coordinate_point'
				);
				foreach ($field_values as $field_key => $field_value)
				{
					if (in_array($field_key, $profile_identity_fields, TRUE) && !$allow_profile_identity_sync)
					{
						continue;
					}
					if (!isset($field_indexes[$field_key]))
					{
						continue;
					}
					$field_index = (int) $field_indexes[$field_key];
					$current_sheet_value = isset($current_sheet_row[$field_index]) ? (string) $current_sheet_row[$field_index] : '';
					if ($this->sheet_field_values_equal($field_key, $current_sheet_value, (string) $field_value))
					{
						continue;
					}
					if (trim($current_sheet_value) !== '' && trim((string) $field_value) !== '')
					{
						$conflict_logs[] = $this->build_conflict_log_entry(array(
							'source' => 'web_to_sheet',
							'actor' => $actor,
							'ip_address' => $actor_ip,
							'mac_address' => $actor_mac,
							'computer_name' => $actor_computer,
							'username' => $username_key,
							'display_name' => $display_name,
							'field' => $field_key,
							'old_value' => $current_sheet_value,
							'new_value' => (string) $field_value,
							'action' => 'overwrite',
							'sheet' => 'Data Absen',
							'row_number' => (int) $sheet_row,
							'note' => 'Nilai sheet ditimpa dari data web saat Sync Data Web ke Sheet.'
						));
					}

					$column_letter = $this->column_letter_from_index($field_index);
					$row_update_data[] = array(
						'range' => $this->quote_sheet_title($attendance_sheet_title).'!'.$column_letter.$sheet_row,
						'majorDimension' => 'ROWS',
						'values' => array(
							array((string) $field_value)
						)
					);
				}
				if (!empty($row_update_data))
				{
					$updated_rows += 1;
					$batch_updates = array_merge($batch_updates, $row_update_data);
				}
				else
				{
					$skipped_users += 1;
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

		$pruned_rows = 0;
		$prune_error = '';
		if (!empty($stale_row_number_map))
		{
			$attendance_sheet_gid = isset($this->config['attendance_sheet_gid']) ? (int) $this->config['attendance_sheet_gid'] : 0;
			if ($attendance_sheet_gid <= 0)
			{
				$gid_result = $this->resolve_sheet_gid_from_title($spreadsheet_id, $attendance_sheet_title);
				if (isset($gid_result['success']) && $gid_result['success'] === TRUE)
				{
					$attendance_sheet_gid = isset($gid_result['sheet_gid']) ? (int) $gid_result['sheet_gid'] : 0;
				}
			}

			if ($attendance_sheet_gid <= 0)
			{
				$prune_error = 'Gagal menentukan sheetId untuk hapus baris stale Data Absen.';
			}
			else
			{
				$stale_rows = array_keys($stale_row_number_map);
				rsort($stale_rows, SORT_NUMERIC);
				$delete_requests = array();
				for ($i = 0; $i < count($stale_rows); $i += 1)
				{
					$row_number = (int) $stale_rows[$i];
					if ($row_number <= 1)
					{
						continue;
					}
					$delete_requests[] = array(
						'deleteDimension' => array(
							'range' => array(
								'sheetId' => $attendance_sheet_gid,
								'dimension' => 'ROWS',
								'startIndex' => $row_number - 1,
								'endIndex' => $row_number
							)
						)
					);
				}

				if (!empty($delete_requests))
				{
					$delete_result = $this->sheet_batch_update_requests($delete_requests);
					if (isset($delete_result['success']) && $delete_result['success'] === TRUE)
					{
						$pruned_rows = count($delete_requests);
					}
					else
					{
						$prune_error = isset($delete_result['message']) && trim((string) $delete_result['message']) !== ''
							? (string) $delete_result['message']
							: 'Gagal menghapus baris stale Data Absen.';
					}
				}
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
				'skipped_users' => $skipped_users,
				'pruned_rows' => $pruned_rows,
				'prune_error' => $prune_error
			)
		));
		$this->append_conflict_logs($conflict_logs);

		$message = 'Sinkronisasi web -> Data Absen selesai.';
		if ($pruned_rows > 0)
		{
			$message .= ' Baris stale terhapus: '.$pruned_rows.'.';
		}
		if ($prune_error !== '')
		{
			$message .= ' Hapus baris stale gagal: '.$prune_error;
		}

		return array(
			'success' => TRUE,
			'skipped' => FALSE,
			'message' => $message,
			'month' => $target_month,
			'processed_users' => $processed_users,
			'updated_rows' => $updated_rows,
			'appended_rows' => $appended_rows,
			'skipped_users' => $skipped_users,
			'pruned_rows' => $pruned_rows,
			'prune_error' => $prune_error
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

		$fixed_sheet_row = $this->resolve_fixed_account_sheet_row($username_key);
		$sheet_row = $fixed_sheet_row > 1
			? $fixed_sheet_row
			: (isset($account_row['sheet_row']) ? (int) $account_row['sheet_row'] : 0);
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

	protected function resolve_sheet_gid_from_title($spreadsheet_id, $sheet_title)
	{
		$token_result = $this->request_access_token();
		if (!$token_result['success'])
		{
			return $token_result;
		}

		$sheet_title = trim((string) $sheet_title);
		if ($sheet_title === '')
		{
			return array(
				'success' => FALSE,
				'skipped' => FALSE,
				'message' => 'Nama sheet kosong saat mencari sheetId.'
			);
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
		for ($i = 0; $i < count($sheets); $i += 1)
		{
			$properties = isset($sheets[$i]['properties']) && is_array($sheets[$i]['properties'])
				? $sheets[$i]['properties']
				: array();
			$row_title = isset($properties['title']) ? trim((string) $properties['title']) : '';
			if ($row_title === '' || strcasecmp($row_title, $sheet_title) !== 0)
			{
				continue;
			}

			$row_gid = isset($properties['sheetId']) ? (int) $properties['sheetId'] : 0;
			if ($row_gid > 0)
			{
				return array(
					'success' => TRUE,
					'skipped' => FALSE,
					'message' => 'OK',
					'sheet_gid' => $row_gid
				);
			}
		}

		return array(
			'success' => FALSE,
			'skipped' => FALSE,
			'message' => 'sheetId tidak ditemukan untuk sheet '.$sheet_title.'.'
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

	protected function sheet_batch_update_requests($requests)
	{
		$token_result = $this->request_access_token();
		if (!$token_result['success'])
		{
			return $token_result;
		}

		$spreadsheet_id = trim((string) $this->config['spreadsheet_id']);
		$token = isset($token_result['access_token']) ? (string) $token_result['access_token'] : '';
		$url = 'https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheet_id).':batchUpdate';

		$payload = json_encode(array(
			'requests' => is_array($requests) ? array_values($requests) : array()
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
		$last_main_token = '';
		$max_count = max(
			is_array($header_values) ? count($header_values) : 0,
			is_array($sub_header_values) ? count($sub_header_values) : 0
		);

		for ($i = 0; $i < $max_count; $i += 1)
		{
			$main_raw = $this->normalize_attendance_header(is_array($header_values) && isset($header_values[$i]) ? $header_values[$i] : '');
			$sub = $this->normalize_attendance_header(is_array($sub_header_values) && isset($sub_header_values[$i]) ? $sub_header_values[$i] : '');
			if ($main_raw !== '')
			{
				$last_main_token = $main_raw;
			}
			$main = $main_raw !== ''
				? $main_raw
				: ($sub !== '' ? $last_main_token : '');
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
			elseif (
				$main === 'gaji' ||
				$main === 'gajipokok' ||
				$main === 'gajidasar' ||
				$main === 'salary' ||
				$main === 'salarybasic' ||
				$main === 'basicsalary'
			)
			{
				$field_key = 'salary';
			}
			elseif ($main === 'tlp' || $main === 'telp' || $main === 'telepon' || $main === 'phone')
			{
				$field_key = 'phone';
			}
			elseif ($main === 'cabangasal' || $main === 'branchasal' || $main === 'originbranch')
			{
				$field_key = 'branch_origin';
			}
			elseif ($main === 'cabangabsen' || $main === 'branchabsen' || $main === 'attendancebranch')
			{
				$field_key = 'branch_attendance';
			}
			elseif ($main === 'lintascabang' || $main === 'crossbranch' || $main === 'branchlintas')
			{
				$field_key = 'cross_branch_enabled';
			}
			elseif ($main === 'cabang' || $main === 'branch')
			{
				if ($sub === 'asal')
				{
					$field_key = 'branch_origin';
				}
				elseif ($sub === 'absen')
				{
					$field_key = 'branch_attendance';
				}
				else
				{
					$field_key = 'branch';
				}
			}
			elseif (
				$main === 'titikkoordinat' ||
				$main === 'koordinat' ||
				$main === 'koordinatkaryawan' ||
				$main === 'koordinatpegawai' ||
				$main === 'titikkoordinatkantor' ||
				$main === 'koordinatkantor'
			)
			{
				$field_key = 'coordinate_point';
			}
			elseif ($main === 'titik' && $sub === 'koordinat')
			{
				$field_key = 'coordinate_point';
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
				elseif ($main === 'totalizin')
				{
					$field_key = 'total_izin';
				}
				elseif ($main === 'totalcuti')
				{
					$field_key = 'total_cuti';
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
			'month_key' => '',
			'date_count' => 0,
			'is_range' => FALSE
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
			$result['date_count'] = count($dates);
			$result['is_range'] = $result['date_count'] > 1;
			$result['start_date'] = $this->is_valid_attendance_date($start) ? $start : '';
			$result['end_date'] = $this->is_valid_attendance_date($end) ? $end : '';
			$result['anchor_date'] = $result['end_date'] !== '' ? $result['end_date'] : $result['start_date'];
			if ($result['anchor_date'] !== '')
			{
				$result['month_key'] = substr($result['anchor_date'], 0, 7);
			}
			return $result;
		}

		$matched_date = '';
		$date_patterns = array(
			'/^(\d{4})[\/\-](\d{2})[\/\-](\d{2})$/',
			'/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/'
		);
		for ($pattern_i = 0; $pattern_i < count($date_patterns); $pattern_i += 1)
		{
			$pattern = (string) $date_patterns[$pattern_i];
			$matches = array();
			if (preg_match($pattern, $text, $matches) !== 1)
			{
				continue;
			}
			if ($pattern_i === 0)
			{
				$matched_date = sprintf('%04d-%02d-%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
			}
			else
			{
				$matched_date = sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);
			}
			break;
		}
		if ($matched_date !== '' && $this->is_valid_attendance_date($matched_date))
		{
			$result['start_date'] = $matched_date;
			$result['end_date'] = $matched_date;
			$result['anchor_date'] = $matched_date;
			$result['month_key'] = substr($matched_date, 0, 7);
			$result['date_count'] = 1;
			$result['is_range'] = FALSE;
		}

		return $result;
	}

	protected function attendance_date_meta_is_range($date_meta)
	{
		if (!is_array($date_meta))
		{
			return FALSE;
		}
		if (isset($date_meta['is_range']) && $date_meta['is_range'])
		{
			return TRUE;
		}
		$date_count = isset($date_meta['date_count']) ? (int) $date_meta['date_count'] : 0;
		return $date_count > 1;
	}

	protected function is_attendance_sheet_summary_snapshot_row($row)
	{
		if (!is_array($row))
		{
			return FALSE;
		}

		$source = strtolower(trim((string) (isset($row['sheet_sync_source']) ? $row['sheet_sync_source'] : '')));
		if ($source !== 'google_sheet_attendance')
		{
			return FALSE;
		}

		if (isset($row['sheet_summary_only']) && (int) $row['sheet_summary_only'] === 1)
		{
			return TRUE;
		}

		$sheet_date_text = trim((string) (isset($row['sheet_tanggal_absen']) ? $row['sheet_tanggal_absen'] : ''));
		if ($sheet_date_text === '')
		{
			return FALSE;
		}

		$date_meta = $this->parse_attendance_date_meta($sheet_date_text);
		$is_range_row = $this->attendance_date_meta_is_range($date_meta);
		if (!$is_range_row)
		{
			$start_date = isset($date_meta['start_date']) ? trim((string) $date_meta['start_date']) : '';
			$end_date = isset($date_meta['end_date']) ? trim((string) $date_meta['end_date']) : '';
			$is_range_row = $start_date !== '' && $end_date !== '' && $start_date !== $end_date;
		}
		if (!$is_range_row)
		{
			return FALSE;
		}

		$is_empty_marker = function ($value) {
			$text = trim((string) $value);
			if ($text === '' || $text === '-' || $text === '--')
			{
				return TRUE;
			}
			if (strcasecmp($text, 'null') === 0 || strcasecmp($text, 'n/a') === 0)
			{
				return TRUE;
			}

			return FALSE;
		};

		$check_in_time = isset($row['check_in_time']) ? (string) $row['check_in_time'] : '';
		$check_out_time = isset($row['check_out_time']) ? (string) $row['check_out_time'] : '';
		$has_presence_data =
			$this->has_real_attendance_clock_time($check_in_time) ||
			$this->has_real_attendance_clock_time($check_out_time) ||
			!$is_empty_marker(isset($row['check_in_photo']) ? $row['check_in_photo'] : '') ||
			!$is_empty_marker(isset($row['check_out_photo']) ? $row['check_out_photo'] : '') ||
			!$is_empty_marker(isset($row['check_in_lat']) ? $row['check_in_lat'] : '') ||
			!$is_empty_marker(isset($row['check_in_lng']) ? $row['check_in_lng'] : '') ||
			!$is_empty_marker(isset($row['check_out_lat']) ? $row['check_out_lat'] : '') ||
			!$is_empty_marker(isset($row['check_out_lng']) ? $row['check_out_lng'] : '');
		if ($has_presence_data)
		{
			return FALSE;
		}

		return TRUE;
	}

	protected function build_attendance_sync_signature_from_values(
		$check_in_time,
		$check_out_time,
		$check_in_photo,
		$check_out_photo,
		$late_duration,
		$work_duration,
		$jenis_masuk,
		$jenis_pulang,
		$late_reason
	)
	{
		$values = array(
			$this->normalize_clock_time($check_in_time),
			$this->normalize_clock_time($check_out_time),
			$this->normalize_sheet_text_compare_key($check_in_photo, TRUE),
			$this->normalize_sheet_text_compare_key($check_out_photo, TRUE),
			$this->normalize_duration_value($late_duration),
			$this->normalize_duration_value($work_duration),
			$this->normalize_sheet_text_compare_key($jenis_masuk, TRUE),
			$this->normalize_sheet_text_compare_key($jenis_pulang, TRUE),
			$this->normalize_sheet_text_compare_key($late_reason, TRUE)
		);
		$has_value = FALSE;
		for ($i = 0; $i < count($values); $i += 1)
		{
			if (trim((string) $values[$i]) !== '')
			{
				$has_value = TRUE;
				break;
			}
		}
		if (!$has_value)
		{
			return '';
		}

		return md5(implode('|', $values));
	}

	protected function build_attendance_sync_signature_from_row($row)
	{
		if (!is_array($row))
		{
			return '';
		}

		return $this->build_attendance_sync_signature_from_values(
			isset($row['check_in_time']) ? (string) $row['check_in_time'] : '',
			isset($row['check_out_time']) ? (string) $row['check_out_time'] : '',
			isset($row['check_in_photo']) ? (string) $row['check_in_photo'] : '',
			isset($row['check_out_photo']) ? (string) $row['check_out_photo'] : '',
			isset($row['check_in_late']) ? (string) $row['check_in_late'] : '',
			isset($row['work_duration']) ? (string) $row['work_duration'] : '',
			isset($row['jenis_masuk']) ? (string) $row['jenis_masuk'] : '',
			isset($row['jenis_pulang']) ? (string) $row['jenis_pulang'] : '',
			isset($row['late_reason']) ? (string) $row['late_reason'] : ''
		);
	}

	protected function find_attendance_index_by_signature($attendance_records, $username_key, $month_key, $signature)
	{
		if (!is_array($attendance_records))
		{
			return -1;
		}
		$username_lookup = strtolower(trim((string) $username_key));
		$month_lookup = trim((string) $month_key);
		$signature_lookup = trim((string) $signature);
		if ($username_lookup === '' || $month_lookup === '' || $signature_lookup === '')
		{
			return -1;
		}

		$matched_index = -1;
		$matched_date = '';
		for ($i = 0; $i < count($attendance_records); $i += 1)
		{
			$row = isset($attendance_records[$i]) && is_array($attendance_records[$i])
				? $attendance_records[$i]
				: array();
			$row_username = isset($row['username']) ? strtolower(trim((string) $row['username'])) : '';
			if ($row_username !== $username_lookup)
			{
				continue;
			}
			$row_date = isset($row['date']) ? trim((string) $row['date']) : '';
			if (!$this->is_valid_attendance_date($row_date))
			{
				continue;
			}
			if (substr($row_date, 0, 7) !== $month_lookup)
			{
				continue;
			}
			$row_signature = $this->build_attendance_sync_signature_from_row($row);
			if ($row_signature === '' || $row_signature !== $signature_lookup)
			{
				continue;
			}
			if ($matched_index < 0 || $matched_date === '' || strcmp($row_date, $matched_date) < 0)
			{
				$matched_index = $i;
				$matched_date = $row_date;
			}
		}

		return $matched_index;
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

	protected function load_alpha_reset_lookup_by_date($target_month = '')
	{
		$month_key = trim((string) $target_month);
		$file_path = APPPATH.'cache/alpha_reset_state.json';
		$decoded = NULL;
		if (function_exists('absen_data_store_load_value'))
		{
			$loaded = absen_data_store_load_value('alpha_reset_state', NULL, $file_path);
			if (is_array($loaded))
			{
				$decoded = $loaded;
			}
		}
		if (!is_array($decoded))
		{
			if (!is_file($file_path))
			{
				return array();
			}
			$raw = @file_get_contents($file_path);
			if ($raw === FALSE || trim($raw) === '')
			{
				return array();
			}
			$decoded = json_decode($raw, TRUE);
			if (!is_array($decoded))
			{
				return array();
			}
		}
		$by_date = isset($decoded['by_date']) && is_array($decoded['by_date'])
			? $decoded['by_date']
			: array();
		if (empty($by_date))
		{
			return array();
		}

		$result = array();
		foreach ($by_date as $date_key => $usernames)
		{
			$date_text = trim((string) $date_key);
			if (!$this->is_valid_attendance_date($date_text))
			{
				continue;
			}
			if ($month_key !== '' && substr($date_text, 0, 7) !== $month_key)
			{
				continue;
			}
			if (!is_array($usernames))
			{
				continue;
			}
			$user_lookup = array();
			for ($i = 0; $i < count($usernames); $i += 1)
			{
				$user_key = strtolower(trim((string) $usernames[$i]));
				if ($user_key === '')
				{
					continue;
				}
				$user_lookup[$user_key] = TRUE;
			}
			if (!empty($user_lookup))
			{
				$result[$date_text] = $user_lookup;
			}
		}

		return $result;
	}

	protected function build_alpha_reset_force_user_lookup($alpha_reset_lookup_by_date, $period_start_date, $period_end_date)
	{
		$result = array();
		if (!is_array($alpha_reset_lookup_by_date) || empty($alpha_reset_lookup_by_date))
		{
			return $result;
		}

		$start_date = trim((string) $period_start_date);
		$end_date = trim((string) $period_end_date);
		if (!$this->is_valid_attendance_date($start_date) || !$this->is_valid_attendance_date($end_date))
		{
			return $result;
		}
		if (strcmp($end_date, $start_date) < 0)
		{
			return $result;
		}

		$start_ts = strtotime($start_date.' 00:00:00');
		$end_ts = strtotime($end_date.' 00:00:00');
		if ($start_ts === FALSE || $end_ts === FALSE || $end_ts < $start_ts)
		{
			return $result;
		}

		$required_dates = array();
		for ($cursor = $start_ts; $cursor <= $end_ts; $cursor = strtotime('+1 day', $cursor))
		{
			if ($cursor === FALSE)
			{
				break;
			}
			$required_dates[] = date('Y-m-d', $cursor);
		}
		$required_date_count = count($required_dates);
		if ($required_date_count <= 0)
		{
			return $result;
		}

		$coverage_by_user = array();
		for ($i = 0; $i < $required_date_count; $i += 1)
		{
			$date_key = (string) $required_dates[$i];
			if (!isset($alpha_reset_lookup_by_date[$date_key]) || !is_array($alpha_reset_lookup_by_date[$date_key]))
			{
				continue;
			}
			$user_lookup = $alpha_reset_lookup_by_date[$date_key];
			foreach ($user_lookup as $username_key => $allowed)
			{
				if ($allowed !== TRUE)
				{
					continue;
				}
				$username = strtolower(trim((string) $username_key));
				if ($username === '')
				{
					continue;
				}
				if (!isset($coverage_by_user[$username]))
				{
					$coverage_by_user[$username] = 0;
				}
				$coverage_by_user[$username] += 1;
			}
		}

		foreach ($coverage_by_user as $username_key => $covered_dates)
		{
			if ((int) $covered_dates >= $required_date_count)
			{
				$result[(string) $username_key] = TRUE;
			}
		}

		return $result;
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

	protected function resolve_shift_key_from_values($shift_name = '', $shift_time = '', $default_shift_key = '')
	{
		$shift_name_key = strtolower(trim((string) $shift_name));
		$shift_time_key = strtolower(trim((string) $shift_time));
		$default_key = strtolower(trim((string) $default_shift_key));

		if ($shift_name_key === '' && $shift_time_key === '')
		{
			return $default_key;
		}

		if (
			strpos($shift_name_key, 'multi') !== FALSE ||
			(
				(strpos($shift_time_key, '06:30') !== FALSE || strpos($shift_time_key, '07:00') !== FALSE) &&
				(strpos($shift_time_key, '23:59') !== FALSE || strpos($shift_time_key, '23:00') !== FALSE)
			)
		)
		{
			return 'multishift';
		}

		if (
			strpos($shift_name_key, 'siang') !== FALSE ||
			strpos($shift_time_key, '14:00') !== FALSE ||
			strpos($shift_time_key, '12:00') !== FALSE
		)
		{
			return 'siang';
		}

		if (
			strpos($shift_name_key, 'pagi') !== FALSE ||
			strpos($shift_time_key, '07:00') !== FALSE ||
			strpos($shift_time_key, '08:00') !== FALSE ||
			strpos($shift_time_key, '07:30') !== FALSE
		)
		{
			return 'pagi';
		}

		return $default_key;
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

	protected function has_real_attendance_clock_time($value)
	{
		$time_text = trim((string) $value);
		if ($time_text === '' || $time_text === '-' || $time_text === '--')
		{
			return FALSE;
		}

		$normalized = $this->normalize_clock_time($time_text);
		if ($normalized === '' || $normalized === '00:00:00')
		{
			return FALSE;
		}

		return TRUE;
	}

	protected function clock_text_to_seconds($value)
	{
		$normalized = $this->normalize_clock_time($value);
		if ($normalized === '')
		{
			return 0;
		}
		$parts = explode(':', $normalized);
		$hours = isset($parts[0]) ? (int) $parts[0] : 0;
		$minutes = isset($parts[1]) ? (int) $parts[1] : 0;
		$seconds = isset($parts[2]) ? (int) $parts[2] : 0;

		return max(0, ($hours * 3600) + ($minutes * 60) + $seconds);
	}

	protected function normalize_weekly_day_off_value($weekly_day_off)
	{
		$day_value = (int) $weekly_day_off;
		if ($day_value === 0)
		{
			$day_value = 7;
		}
		if ($day_value < 1 || $day_value > 7)
		{
			$day_value = 1;
		}

		return $day_value;
	}

	protected function normalize_cross_branch_enabled_value($value)
	{
		if (is_bool($value))
		{
			return $value ? 1 : 0;
		}

		$text = strtolower(trim((string) $value));
		if ($text === '')
		{
			return 0;
		}

		if ($text === '1' || $text === 'ya' || $text === 'yes' || $text === 'true' || $text === 'aktif' || $text === 'enabled')
		{
			return 1;
		}

		return 0;
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

	protected function conflict_log_file_path()
	{
		return APPPATH.'cache/conflict_logs.json';
	}

	protected function append_conflict_logs($entries)
	{
		if (!is_array($entries) || empty($entries))
		{
			return;
		}

		$file_path = $this->conflict_log_file_path();
		$rows = function_exists('absen_data_store_load_value')
			? absen_data_store_load_value('conflict_logs', array(), $file_path)
			: $this->load_json_array_file($file_path);
		if (!is_array($rows))
		{
			$rows = array();
		}

		for ($i = 0; $i < count($entries); $i += 1)
		{
			$entry = is_array($entries[$i]) ? $entries[$i] : array();
			if (empty($entry))
			{
				continue;
			}
			$rows[] = $entry;
		}

		$max_entries = isset($this->config['conflict_log_max_entries'])
			? (int) $this->config['conflict_log_max_entries']
			: 2000;
		if ($max_entries <= 0)
		{
			$max_entries = 2000;
		}
		$total_rows = count($rows);
		if ($total_rows > $max_entries)
		{
			$rows = array_slice($rows, $total_rows - $max_entries);
		}

		if (function_exists('absen_data_store_save_value'))
		{
			absen_data_store_save_value('conflict_logs', array_values($rows), $file_path);
			return;
		}

		$this->save_json_array_file($file_path, $rows);
	}

	protected function build_conflict_log_entry($options = array())
	{
		$source = strtolower(trim((string) (isset($options['source']) ? $options['source'] : 'sync')));
		if ($source === '')
		{
			$source = 'sync';
		}

		$actor = strtolower(trim((string) (isset($options['actor']) ? $options['actor'] : 'system')));
		if ($actor === '')
		{
			$actor = 'system';
		}

		$action = strtolower(trim((string) (isset($options['action']) ? $options['action'] : 'overwrite')));
		if ($action === '')
		{
			$action = 'overwrite';
		}

		$field_key = trim((string) (isset($options['field']) ? $options['field'] : ''));
		$field_label = $this->field_label_from_key($field_key);

		return array(
			'log_type' => 'conflict',
			'logged_at' => date('Y-m-d H:i:s'),
			'source' => $source,
			'actor' => $actor,
			'ip_address' => trim((string) (isset($options['ip_address']) ? $options['ip_address'] : '')),
			'mac_address' => trim((string) (isset($options['mac_address']) ? $options['mac_address'] : '')),
			'computer_name' => trim((string) (isset($options['computer_name']) ? $options['computer_name'] : '')),
			'username' => trim((string) (isset($options['username']) ? $options['username'] : '')),
			'display_name' => trim((string) (isset($options['display_name']) ? $options['display_name'] : '')),
			'field' => $field_key,
			'field_label' => $field_label,
			'old_value' => $this->conflict_value_to_text(isset($options['old_value']) ? $options['old_value'] : ''),
			'new_value' => $this->conflict_value_to_text(isset($options['new_value']) ? $options['new_value'] : ''),
			'action' => $action,
			'sheet' => trim((string) (isset($options['sheet']) ? $options['sheet'] : '')),
			'row_number' => isset($options['row_number']) ? (int) $options['row_number'] : 0,
			'note' => $this->conflict_value_to_text(isset($options['note']) ? $options['note'] : '', 500)
		);
	}

	protected function conflict_value_to_text($value, $max_length = 300)
	{
		$text = trim((string) $value);
		if ($text === '')
		{
			return '';
		}
		$text = preg_replace('/\s+/', ' ', $text);
		$max_length = (int) $max_length;
		if ($max_length <= 0)
		{
			$max_length = 300;
		}
		if (strlen($text) > $max_length)
		{
			return substr($text, 0, $max_length - 3).'...';
		}

		return $text;
	}

	protected function conflict_values_equal($field_key, $left, $right)
	{
		$field_key = trim((string) $field_key);
		$left_text = trim((string) $left);
		$right_text = trim((string) $right);

			$numeric_fields = array(
				'salary_monthly',
				'work_days',
				'sudah_berapa_absen',
				'hari_efektif',
				'total_hadir',
				'total_izin',
				'total_cuti',
				'telat_1_30',
				'telat_31_60',
				'telat_1_3',
				'telat_gt_4',
				'total_izin_cuti',
			'total_alpha'
		);
		if (in_array($field_key, $numeric_fields, TRUE))
		{
			return $this->parse_money_to_int($left_text) === $this->parse_money_to_int($right_text);
		}

		return $left_text === $right_text;
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

				$phone = isset($row['phone']) ? $this->normalize_phone_number($row['phone']) : '';
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
			$phone_value = $this->normalize_phone_number($this->get_row_value($row, $field_indexes, 'phone'));
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
			$phone = $this->normalize_phone_number($phone_lookup['by_employee_id'][$employee_id_key]);
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
			$phone = $this->normalize_phone_number($phone_lookup['by_employee_no'][$employee_id_key]);
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
			$phone = $this->normalize_phone_number($phone_lookup['by_name'][$name_key]);
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
			$phone = $this->normalize_phone_number($phone_lookup['by_compact'][$compact_key]);
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
			$phone = $this->normalize_phone_number($phone_lookup['by_username'][$username]);
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

	protected function normalize_phone_number($value)
	{
		if (function_exists('absen_normalize_phone_number'))
		{
			return absen_normalize_phone_number($value);
		}

		$digits = preg_replace('/\D+/', '', (string) $value);
		if ($digits === '')
		{
			return '';
		}

		if (strpos($digits, '0') === 0)
		{
			return '62'.substr($digits, 1);
		}

		return $digits;
	}

	protected function normalize_phone_compare_key($value)
	{
		return $this->normalize_phone_number($value);
	}

	protected function normalize_sheet_text_compare_key($value, $case_insensitive = FALSE)
	{
		$text = trim((string) $value);
		if ($text !== '' && substr($text, 0, 1) === "'")
		{
			$text = ltrim(substr($text, 1));
		}
		$text = preg_replace('/\s+/', ' ', $text);
		$text = trim((string) $text);
		if ($case_insensitive)
		{
			$text = strtolower($text);
		}

		return $text;
	}

	protected function sheet_field_values_equal($field_key, $current_value, $next_value)
	{
		$field_key = trim((string) $field_key);
		$current_text = trim((string) $current_value);
		$next_text = trim((string) $next_value);

		if ($field_key === 'phone')
		{
			// Untuk update ke sheet, format 08... dan 62... harus dianggap beda
			// agar nomor lama 08... bisa ditimpa menjadi 62....
			$current_digits = preg_replace('/\D+/', '', $current_text);
			$next_digits = preg_replace('/\D+/', '', $next_text);
			return $current_digits === $next_digits;
		}

		$numeric_fields = array(
			'hari_efektif',
			'sudah_berapa_absen',
			'total_hadir',
			'total_izin',
			'total_cuti',
			'telat_1_30',
			'telat_31_60',
			'telat_1_3',
			'telat_gt_4',
			'total_izin_cuti',
		'total_alpha',
		'salary'
		);
		if (in_array($field_key, $numeric_fields, TRUE))
		{
			return $this->parse_money_to_int($current_text) === $this->parse_money_to_int($next_text);
		}

		if ($field_key === 'employee_id')
		{
			$current_id = $this->normalize_identifier_key($current_text);
			$next_id = $this->normalize_identifier_key($next_text);
			if ($current_id !== '' || $next_id !== '')
			{
				return $current_id === $next_id;
			}
		}

		if ($field_key === 'waktu_masuk' || $field_key === 'waktu_pulang')
		{
			return $this->normalize_clock_time($current_text) === $this->normalize_clock_time($next_text);
		}

		if ($field_key === 'telat_duration' || $field_key === 'durasi_bekerja')
		{
			return $this->normalize_duration_value($current_text) === $this->normalize_duration_value($next_text);
		}

		if ($field_key === 'date_absen')
		{
			$current_meta = $this->parse_attendance_date_meta($current_text);
			$next_meta = $this->parse_attendance_date_meta($next_text);
			$current_anchor = isset($current_meta['anchor_date']) ? trim((string) $current_meta['anchor_date']) : '';
			$next_anchor = isset($next_meta['anchor_date']) ? trim((string) $next_meta['anchor_date']) : '';
			if ($current_anchor !== '' || $next_anchor !== '')
			{
				return $current_anchor === $next_anchor;
			}
		}

		$case_insensitive_fields = array(
			'status',
			'branch',
			'branch_origin',
			'branch_attendance',
			'cross_branch_enabled',
			'jenis_masuk',
			'jenis_pulang'
		);
		$case_insensitive = in_array($field_key, $case_insensitive_fields, TRUE);

		return $this->normalize_sheet_text_compare_key($current_text, $case_insensitive) ===
			$this->normalize_sheet_text_compare_key($next_text, $case_insensitive);
	}

	protected function attendance_rows_equal($row_a, $row_b)
	{
		if (!is_array($row_a) || !is_array($row_b))
		{
			return FALSE;
		}

		$keys = array_unique(array_merge(array_keys($row_a), array_keys($row_b)));
		foreach ($keys as $raw_key)
		{
			$key = (string) $raw_key;
			if ($key === 'updated_at')
			{
				continue;
			}

			$value_a = isset($row_a[$key]) ? (string) $row_a[$key] : '';
			$value_b = isset($row_b[$key]) ? (string) $row_b[$key] : '';

			if ($key === 'check_in_time' || $key === 'check_out_time')
			{
				if ($this->normalize_clock_time($value_a) !== $this->normalize_clock_time($value_b))
				{
					return FALSE;
				}
				continue;
			}

			if ($key === 'check_in_late' || $key === 'work_duration')
			{
				if ($this->normalize_duration_value($value_a) !== $this->normalize_duration_value($value_b))
				{
					return FALSE;
				}
				continue;
			}

			if ($key === 'sheet_tanggal_absen')
			{
				$meta_a = $this->parse_attendance_date_meta($value_a);
				$meta_b = $this->parse_attendance_date_meta($value_b);
				$anchor_a = isset($meta_a['anchor_date']) ? trim((string) $meta_a['anchor_date']) : '';
				$anchor_b = isset($meta_b['anchor_date']) ? trim((string) $meta_b['anchor_date']) : '';
				if ($anchor_a !== '' || $anchor_b !== '')
				{
					if ($anchor_a !== $anchor_b)
					{
						return FALSE;
					}
					continue;
				}
			}

				$numeric_keys = array(
					'salary_monthly',
					'work_days_per_month',
					'days_in_month',
					'weekly_off_days',
					'sheet_sudah_berapa_absen',
					'sheet_hari_efektif',
					'sheet_total_hadir',
					'sheet_total_telat_1_30',
					'sheet_total_telat_31_60',
					'sheet_total_telat_1_3',
					'sheet_total_telat_gt_4',
					'sheet_total_izin',
					'sheet_total_cuti',
					'sheet_total_izin_cuti',
					'sheet_total_alpha'
				);
			if (in_array($key, $numeric_keys, TRUE))
			{
				if ($this->parse_money_to_int($value_a) !== $this->parse_money_to_int($value_b))
				{
					return FALSE;
				}
				continue;
			}

			if ($key === 'check_in_photo' || $key === 'check_out_photo')
			{
				if ($this->normalize_attendance_photo_cell($value_a) !== $this->normalize_attendance_photo_cell($value_b))
				{
					return FALSE;
				}
				continue;
			}

			if ($this->normalize_sheet_text_compare_key($value_a) !== $this->normalize_sheet_text_compare_key($value_b))
			{
				return FALSE;
			}
		}

		return TRUE;
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
			'employee_id',
			'branch',
			'cross_branch_enabled',
			'phone',
			'shift_name',
			'shift_time',
			'salary_tier',
			'salary_monthly',
			'work_days',
			'job_title',
			'address',
			'profile_photo',
			'coordinate_point',
			'employee_status',
			'sheet_summary',
			'sheet_summary_by_month',
			'sheet_row',
			'sheet_sync_source'
		);

		$normalize_compare_value = function ($value) {
			if (is_array($value))
			{
				return (string) json_encode($value);
			}
			if (is_bool($value))
			{
				return $value ? '1' : '0';
			}
			return (string) $value;
		};

		for ($i = 0; $i < count($keys); $i += 1)
		{
			$key = $keys[$i];
			$value_a = isset($row_a[$key]) ? $row_a[$key] : NULL;
			$value_b = isset($row_b[$key]) ? $row_b[$key] : NULL;
			if ($normalize_compare_value($value_a) !== $normalize_compare_value($value_b))
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
				'branch_origin' => 'Cabang Asal',
				'branch_attendance' => 'Cabang Absen',
				'cross_branch_enabled' => 'Lintas Cabang',
				'total_izin' => 'Total Izin',
				'total_cuti' => 'Total Cuti',
				'coordinate_point' => 'Titik Koordinat',
				'salary' => 'Gaji Pokok'
			);
		if (isset($fallback[$field_key]))
		{
			return (string) $fallback[$field_key];
		}

		return ucfirst(str_replace('_', ' ', $field_key));
	}

	protected function fixed_account_sheet_row_map()
	{
		$raw_map = isset($this->config['fixed_account_sheet_rows']) && is_array($this->config['fixed_account_sheet_rows'])
			? $this->config['fixed_account_sheet_rows']
			: array();
		$fixed_map = array();
		foreach ($raw_map as $raw_username => $raw_row)
		{
			$username_key = strtolower(trim((string) $raw_username));
			if ($username_key === '' || $this->is_reserved_system_username($username_key))
			{
				continue;
			}
			$row_number = (int) $raw_row;
			if ($row_number <= 1)
			{
				continue;
			}
			$fixed_map[$username_key] = $row_number;
		}

		return $fixed_map;
	}

	protected function resolve_fixed_account_sheet_row($username_key)
	{
		$lookup_key = strtolower(trim((string) $username_key));
		if ($lookup_key === '')
		{
			return 0;
		}
		$fixed_map = $this->fixed_account_sheet_row_map();
		if (!isset($fixed_map[$lookup_key]))
		{
			return 0;
		}
		$row_number = (int) $fixed_map[$lookup_key];
		return $row_number > 1 ? $row_number : 0;
	}

	protected function reserved_system_usernames()
	{
		return array('admin', 'developer', 'bos');
	}

	protected function is_reserved_system_username($username)
	{
		$username_key = strtolower(trim((string) $username));
		if ($username_key === '')
		{
			return FALSE;
		}

		return in_array($username_key, $this->reserved_system_usernames(), TRUE);
	}

	protected function normalize_employee_id_value($value)
	{
		if (function_exists('absen_normalize_employee_id_value'))
		{
			return (string) absen_normalize_employee_id_value($value);
		}

		$text = trim((string) $value);
		if ($text === '' || $text === '-')
		{
			return '';
		}

		$digits = preg_replace('/\D+/', '', $text);
		if (!is_string($digits) || $digits === '')
		{
			return '';
		}

		$sequence = (int) $digits;
		if ($sequence <= 0)
		{
			return '';
		}

		if ($sequence < 100)
		{
			return str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
		}

		return (string) $sequence;
	}

	protected function build_employee_id_lookup_from_accounts($account_book)
	{
		$lookup = array();
		if (!is_array($account_book))
		{
			return $lookup;
		}

		$used_ids = array();
		$usernames_without_id = array();
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

			$stored_id = $this->normalize_employee_id_value(isset($row['employee_id']) ? $row['employee_id'] : '');
			if ($stored_id !== '' && !isset($used_ids[$stored_id]))
			{
				$lookup[$username] = $stored_id;
				$used_ids[$stored_id] = TRUE;
				continue;
			}

			$usernames_without_id[] = $username;
		}

		sort($usernames_without_id, SORT_STRING);
		$sequence = 1;
		for ($i = 0; $i < count($usernames_without_id); $i += 1)
		{
			$resolved_id = '';
			while ($sequence <= 9999)
			{
				if ($sequence < 100)
				{
					$candidate_id = str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
				}
				else
				{
					$candidate_id = (string) $sequence;
				}
				$sequence += 1;
				if (isset($used_ids[$candidate_id]))
				{
					continue;
				}

				$resolved_id = $candidate_id;
				break;
			}

			if ($resolved_id === '')
			{
				continue;
			}
			$username = (string) $usernames_without_id[$i];
			$lookup[$username] = $resolved_id;
			$used_ids[$resolved_id] = TRUE;
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
			$stored_relative_path = $this->store_attendance_data_uri_photo($text);
			if ($stored_relative_path !== '')
			{
				return $this->resolve_public_asset_url($stored_relative_path);
			}
			return 'Foto dari Web';
		}
		if (preg_match('#^https?://#i', $text) === 1)
		{
			return $text;
		}
		if (strpos($text, '/') === 0)
		{
			return $this->resolve_public_asset_url($text);
		}
		if (stripos($text, 'uploads/') === 0)
		{
			return $this->resolve_public_asset_url('/'.$text);
		}
		if (strlen($text) > 1000)
		{
			return substr($text, 0, 1000);
		}

		return $text;
	}

	protected function store_attendance_data_uri_photo($data_uri)
	{
		$payload = trim((string) $data_uri);
		if ($payload === '')
		{
			return '';
		}

		$matches = array();
		if (!preg_match('#^data:image/([a-zA-Z0-9.+-]+);base64,(.+)$#', $payload, $matches))
		{
			return '';
		}

		$mime_sub = strtolower(trim((string) (isset($matches[1]) ? $matches[1] : '')));
		$encoded = isset($matches[2]) ? (string) $matches[2] : '';
		if ($encoded === '')
		{
			return '';
		}
		$encoded = str_replace(' ', '+', $encoded);
		$binary = base64_decode($encoded, TRUE);
		if ($binary === FALSE || $binary === '')
		{
			return '';
		}

		$ext = 'jpg';
		if ($mime_sub === 'png' || $mime_sub === 'gif' || $mime_sub === 'webp' || $mime_sub === 'bmp')
		{
			$ext = $mime_sub;
		}
		elseif ($mime_sub === 'jpeg' || $mime_sub === 'jpg')
		{
			$ext = 'jpg';
		}
		elseif ($mime_sub === 'heic' || $mime_sub === 'heif')
		{
			$ext = 'heic';
		}

		$relative_dir = 'uploads/attendance_photo';
		$absolute_dir = rtrim((string) FCPATH, '/\\').DIRECTORY_SEPARATOR.
			str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $relative_dir);
		if (!is_dir($absolute_dir))
		{
			@mkdir($absolute_dir, 0755, TRUE);
		}
		if (!is_dir($absolute_dir))
		{
			return '';
		}

		$file_hash = sha1($binary);
		$file_name = 'attendance_'.$file_hash.'.'.$ext;
		$absolute_path = rtrim($absolute_dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file_name;
		if (!is_file($absolute_path))
		{
			@file_put_contents($absolute_path, $binary);
		}
		if (!is_file($absolute_path))
		{
			return '';
		}

		return '/'.str_replace('\\', '/', $relative_dir.'/'.$file_name);
	}

	protected function resolve_public_asset_url($path)
	{
		$target = trim((string) $path);
		if ($target === '')
		{
			return '';
		}
		if (preg_match('#^https?://#i', $target) === 1)
		{
			return $target;
		}
		if (strpos($target, '/') !== 0)
		{
			$target = '/'.$target;
		}

		$base_url = '';
		$env_public_base_url = trim((string) getenv('ABSEN_PUBLIC_BASE_URL'));
		if ($env_public_base_url !== '')
		{
			$base_url = $env_public_base_url;
		}
		if ($base_url === '' && is_object($this->CI) && isset($this->CI->config) && is_object($this->CI->config))
		{
			$config_base_url = trim((string) $this->CI->config->item('base_url'));
			if ($config_base_url !== '')
			{
				$base_url = $config_base_url;
			}
		}
		if ($base_url === '' && isset($_SERVER['HTTP_HOST']) && trim((string) $_SERVER['HTTP_HOST']) !== '')
		{
			$is_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
			$scheme = $is_https ? 'https' : 'http';
			$base_url = $scheme.'://'.trim((string) $_SERVER['HTTP_HOST']).'/';
		}
		if ($base_url === '')
		{
			return $target;
		}

		return rtrim($base_url, '/').$target;
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
