<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('absen_shift_profile_book'))
{
	function absen_shift_profile_book()
	{
		return array(
			'pagi' => array(
				'shift_name' => 'Shift Pagi - Sore',
				'shift_time' => '08:00 - 17:00'
			),
			'siang' => array(
				'shift_name' => 'Shift Siang - Malam',
				'shift_time' => '12:00 - 23:00'
			)
		);
	}
}

if (!function_exists('absen_salary_profile_book'))
{
	function absen_salary_profile_book()
	{
		return array(
			'A' => array('salary_tier' => 'A', 'salary_monthly' => 1000000, 'work_days' => 28),
			'B' => array('salary_tier' => 'B', 'salary_monthly' => 2000000, 'work_days' => 28),
			'C' => array('salary_tier' => 'C', 'salary_monthly' => 3000000, 'work_days' => 28),
			'D' => array('salary_tier' => 'D', 'salary_monthly' => 4000000, 'work_days' => 28)
		);
	}
}

if (!function_exists('absen_employee_job_title_options'))
{
	function absen_employee_job_title_options()
	{
		return array(
			'NOC',
			'Admin',
			'Koordinator',
			'Teknisi',
			'Marketing',
			'Debt Collector',
			'Magang'
		);
	}
}

if (!function_exists('absen_employee_branch_options'))
{
	function absen_employee_branch_options()
	{
		return array(
			'Baros',
			'Cadasari'
		);
	}
}

if (!function_exists('absen_default_employee_branch'))
{
	function absen_default_employee_branch()
	{
		return 'Baros';
	}
}

if (!function_exists('absen_resolve_employee_branch'))
{
	function absen_resolve_employee_branch($branch)
	{
		$branch_value = trim((string) $branch);
		if ($branch_value === '')
		{
			return '';
		}

		$options = absen_employee_branch_options();
		for ($i = 0; $i < count($options); $i += 1)
		{
			if (strcasecmp($branch_value, (string) $options[$i]) === 0)
			{
				return (string) $options[$i];
			}
		}

		return '';
	}
}

if (!function_exists('absen_password_session_timeout_seconds'))
{
	function absen_password_session_timeout_seconds()
	{
		return 1800;
	}
}

if (!function_exists('absen_password_looks_hashed'))
{
	function absen_password_looks_hashed($password_value)
	{
		$password_text = trim((string) $password_value);
		if ($password_text === '')
		{
			return FALSE;
		}

		return strpos($password_text, '$2y$') === 0 ||
			strpos($password_text, '$2a$') === 0 ||
			strpos($password_text, '$2b$') === 0 ||
			strpos($password_text, '$argon2i$') === 0 ||
			strpos($password_text, '$argon2id$') === 0;
	}
}

if (!function_exists('absen_hash_password'))
{
	function absen_hash_password($plain_password)
	{
		$password_text = (string) $plain_password;
		if ($password_text === '')
		{
			return '';
		}

		$hashed = @password_hash($password_text, PASSWORD_DEFAULT);
		if (!is_string($hashed) || trim($hashed) === '')
		{
			return '';
		}

		return $hashed;
	}
}

if (!function_exists('absen_verify_account_password'))
{
	function absen_verify_account_password($account_row, $input_password, &$needs_upgrade = FALSE)
	{
		$needs_upgrade = FALSE;
		$password_input = (string) $input_password;
		if (!is_array($account_row))
		{
			return FALSE;
		}

		$stored_password = '';
		if (isset($account_row['password']) && trim((string) $account_row['password']) !== '')
		{
			$stored_password = (string) $account_row['password'];
		}
		elseif (isset($account_row['password_hash']) && trim((string) $account_row['password_hash']) !== '')
		{
			$stored_password = (string) $account_row['password_hash'];
		}

		if ($stored_password === '')
		{
			return FALSE;
		}

		if (absen_password_looks_hashed($stored_password))
		{
			$verified = @password_verify($password_input, $stored_password);
			if ($verified !== TRUE)
			{
				return FALSE;
			}

			if (@password_needs_rehash($stored_password, PASSWORD_DEFAULT))
			{
				$needs_upgrade = TRUE;
			}

			return TRUE;
		}

		$verified_plain = hash_equals((string) $stored_password, $password_input);
		if ($verified_plain)
		{
			$needs_upgrade = TRUE;
			return TRUE;
		}

		return FALSE;
	}
}

if (!function_exists('absen_account_set_password'))
{
	function absen_account_set_password(&$account_row, $plain_password, $force_change = NULL)
	{
		if (!is_array($account_row))
		{
			$account_row = array();
		}

		$hashed = absen_hash_password($plain_password);
		if ($hashed === '')
		{
			return FALSE;
		}

		$account_row['password'] = $hashed;
		$account_row['password_hash'] = $hashed;
		$account_row['password_changed_at'] = date('Y-m-d H:i:s');
		if ($force_change !== NULL)
		{
			$account_row['force_password_change'] = $force_change ? 1 : 0;
		}

		return TRUE;
	}
}

if (!function_exists('absen_account_requires_password_change'))
{
	function absen_account_requires_password_change($account_row)
	{
		if (!is_array($account_row))
		{
			return FALSE;
		}

		if (isset($account_row['force_password_change']))
		{
			return ((int) $account_row['force_password_change']) === 1;
		}
		if (isset($account_row['must_change_password']))
		{
			return ((int) $account_row['must_change_password']) === 1;
		}

		return FALSE;
	}
}

if (!function_exists('absen_session_is_expired'))
{
	function absen_session_is_expired($last_activity_at, $timeout_seconds = NULL)
	{
		$timeout = $timeout_seconds === NULL
			? (int) absen_password_session_timeout_seconds()
			: (int) $timeout_seconds;
		if ($timeout <= 0)
		{
			return FALSE;
		}

		$last_activity = (int) $last_activity_at;
		if ($last_activity <= 0)
		{
			return FALSE;
		}

		return (time() - $last_activity) > $timeout;
	}
}

if (!function_exists('absen_resolve_employee_job_title'))
{
	function absen_resolve_employee_job_title($job_title)
	{
		$job_title_value = trim((string) $job_title);
		if ($job_title_value === '')
		{
			return '';
		}

		$options = absen_employee_job_title_options();
		for ($i = 0; $i < count($options); $i += 1)
		{
			if (strcasecmp($job_title_value, (string) $options[$i]) === 0)
			{
				return (string) $options[$i];
			}
		}

		return '';
	}
}

if (!function_exists('absen_normalize_username_key_value'))
{
	function absen_normalize_username_key_value($username)
	{
		$normalized = strtolower(trim((string) $username));
		if ($normalized === '')
		{
			return '';
		}

		$normalized = preg_replace('/[^a-z0-9_]+/', '_', $normalized);
		$normalized = preg_replace('/_+/', '_', (string) $normalized);
		$normalized = trim((string) $normalized, '_');
		if (strlen($normalized) > 30)
		{
			$normalized = rtrim(substr($normalized, 0, 30), '_');
		}

		return $normalized;
	}
}

if (!function_exists('absen_resolve_login_username_key'))
{
	function absen_resolve_login_username_key($input_username, $accounts)
	{
		$username_key = absen_normalize_username_key_value($input_username);
		if ($username_key === '')
		{
			return '';
		}
		$admin_alias = '';
		if (is_array($accounts) && isset($accounts['admin']) && is_array($accounts['admin']))
		{
			$admin_alias = absen_normalize_username_key_value(isset($accounts['admin']['login_alias']) ? $accounts['admin']['login_alias'] : '');
		}
		if ($username_key === 'admin' && $admin_alias !== '')
		{
			// Jika username login admin sudah disetel custom, login via "admin" dinonaktifkan.
			return '';
		}

		if (is_array($accounts) && isset($accounts[$username_key]) && is_array($accounts[$username_key]))
		{
			return $username_key;
		}

		if (!is_array($accounts))
		{
			return $username_key;
		}

		foreach ($accounts as $stored_username => $row)
		{
			$stored_key = strtolower(trim((string) $stored_username));
			if ($stored_key === '' || !is_array($row))
			{
				continue;
			}
			$alias = absen_normalize_username_key_value(isset($row['login_alias']) ? $row['login_alias'] : '');
			if ($alias !== '' && $alias === $username_key)
			{
				return $stored_key;
			}
		}

		return $username_key;
	}
}

if (!function_exists('absen_default_account_book'))
{
	function absen_default_account_book()
	{
		$shifts = absen_shift_profile_book();
		$salaries = absen_salary_profile_book();

		$build_user = function ($username, $phone, $job_title, $shift_key, $salary_tier) use ($shifts, $salaries) {
			$shift_key = isset($shifts[$shift_key]) ? $shift_key : 'pagi';
			$salary_tier = strtoupper(trim((string) $salary_tier));
			if (!isset($salaries[$salary_tier]))
			{
				$salary_tier = 'A';
			}

			return array(
				'role' => 'user',
				'password' => absen_hash_password('123'),
				'login_alias' => '',
				'display_name' => (string) $username,
				'branch' => absen_default_employee_branch(),
				'phone' => (string) $phone,
				'shift_name' => (string) $shifts[$shift_key]['shift_name'],
				'shift_time' => (string) $shifts[$shift_key]['shift_time'],
				'salary_tier' => (string) $salaries[$salary_tier]['salary_tier'],
				'salary_monthly' => (int) $salaries[$salary_tier]['salary_monthly'],
				'work_days' => (int) $salaries[$salary_tier]['work_days'],
				'job_title' => (string) $job_title,
				'address' => 'Kp. Kesekian Kalinya, Pandenglang, Banten',
				'profile_photo' => '',
				'coordinate_point' => '',
				'employee_status' => 'Aktif',
				'feature_permissions' => array(),
				'force_password_change' => 1,
				'password_changed_at' => '',
				'sheet_row' => 0,
				'sheet_sync_source' => '',
				'sheet_last_sync_at' => ''
			);
		};

		return array(
			'admin' => array(
				'role' => 'admin',
				'password' => absen_hash_password('absen123'),
				'login_alias' => '',
				'display_name' => 'admin',
				'branch' => absen_default_employee_branch(),
				'phone' => '',
				'shift_name' => '',
				'shift_time' => '',
				'salary_tier' => '',
				'salary_monthly' => 0,
				'work_days' => 22,
				'job_title' => 'Admin',
				'coordinate_point' => '',
				'employee_status' => 'Aktif',
				'feature_permissions' => array(),
				'force_password_change' => 0,
				'password_changed_at' => '',
				'sheet_row' => 0,
				'sheet_sync_source' => '',
				'sheet_last_sync_at' => ''
			),
			'developer' => array(
				'role' => 'admin',
				'password' => absen_hash_password('123'),
				'login_alias' => '',
				'display_name' => 'Developer',
				'branch' => '',
				'phone' => '',
				'shift_name' => '',
				'shift_time' => '',
				'salary_tier' => '',
				'salary_monthly' => 0,
				'work_days' => 22,
				'job_title' => 'Admin',
				'coordinate_point' => '',
				'employee_status' => 'Aktif',
				'feature_permissions' => array('manage_accounts', 'sync_sheet_accounts', 'view_log_data'),
				'force_password_change' => 0,
				'password_changed_at' => '',
				'sheet_row' => 0,
				'sheet_sync_source' => '',
				'sheet_last_sync_at' => ''
			),
			'bos' => array(
				'role' => 'admin',
				'password' => absen_hash_password('123'),
				'login_alias' => '',
				'display_name' => 'Bos',
				'branch' => '',
				'phone' => '',
				'shift_name' => '',
				'shift_time' => '',
				'salary_tier' => '',
				'salary_monthly' => 0,
				'work_days' => 22,
				'job_title' => 'Admin',
				'coordinate_point' => '',
				'employee_status' => 'Aktif',
				'feature_permissions' => array('manage_accounts', 'sync_sheet_accounts', 'view_log_data'),
				'force_password_change' => 0,
				'password_changed_at' => '',
				'sheet_row' => 0,
				'sheet_sync_source' => '',
				'sheet_last_sync_at' => ''
			)
		);
	}
}

if (!function_exists('absen_accounts_file_path'))
{
	function absen_accounts_file_path()
	{
		return APPPATH.'cache/accounts.json';
	}
}

if (!function_exists('absen_sanitize_account_book'))
{
	function absen_sanitize_account_book($account_book)
	{
		$shift_profiles = absen_shift_profile_book();
		$salary_profiles = absen_salary_profile_book();
		$default_book = absen_default_account_book();
		$default_admin = isset($default_book['admin']) && is_array($default_book['admin'])
			? $default_book['admin']
			: array();
		$allowed_admin_features = array('manage_accounts', 'sync_sheet_accounts', 'view_log_data');

		$sanitized = array();
		if (!is_array($account_book))
		{
			$account_book = array();
		}

		foreach ($account_book as $username => $row)
		{
			$username_key = strtolower(trim((string) $username));
			if ($username_key === '' || !is_array($row))
			{
				continue;
			}

			$role = strtolower(trim((string) (isset($row['role']) ? $row['role'] : 'user')));
			if ($role !== 'admin')
			{
				$role = 'user';
			}

			$password = '';
			if (isset($row['password']) && trim((string) $row['password']) !== '')
			{
				$password = (string) $row['password'];
			}
			elseif (isset($row['password_hash']) && trim((string) $row['password_hash']) !== '')
			{
				$password = (string) $row['password_hash'];
			}
			if ($password === '')
			{
				$password = $role === 'admin' ? 'absen123' : '123';
			}
			if (!absen_password_looks_hashed($password))
			{
				$hashed_password = absen_hash_password($password);
				if ($hashed_password !== '')
				{
					$password = $hashed_password;
				}
			}

			$force_password_change = 0;
			if (isset($row['force_password_change']))
			{
				$force_password_change = ((int) $row['force_password_change']) === 1 ? 1 : 0;
			}
			elseif (isset($row['must_change_password']))
			{
				$force_password_change = ((int) $row['must_change_password']) === 1 ? 1 : 0;
			}
			$password_changed_at = trim((string) (isset($row['password_changed_at']) ? $row['password_changed_at'] : ''));

			$display_name = trim((string) (isset($row['display_name']) ? $row['display_name'] : ''));
			if ($display_name === '')
			{
				$display_name = $username_key;
			}
			$login_alias = absen_normalize_username_key_value(isset($row['login_alias']) ? $row['login_alias'] : '');
			if ($role !== 'admin' || $username_key !== 'admin')
			{
				$login_alias = '';
			}
			elseif (in_array($login_alias, array('admin', 'developer', 'bos'), TRUE))
			{
				$login_alias = '';
			}

			$branch = trim((string) (isset($row['branch']) ? $row['branch'] : ''));
			if ($role === 'admin')
			{
				if ($username_key === 'admin')
				{
					$admin_branch = absen_resolve_employee_branch($branch);
					$branch = $admin_branch !== '' ? $admin_branch : absen_default_employee_branch();
				}
				else
				{
					$branch = '';
				}
			}
			else
			{
				$branch_resolved = absen_resolve_employee_branch($branch);
				$branch = $branch_resolved !== '' ? $branch_resolved : absen_default_employee_branch();
			}

			$phone = trim((string) (isset($row['phone']) ? $row['phone'] : ''));
			$job_title = trim((string) (isset($row['job_title']) ? $row['job_title'] : ''));
			if ($role === 'admin')
			{
				$job_title = 'Admin';
			}
			else
			{
				$job_title_resolved = absen_resolve_employee_job_title($job_title);
				$job_title = $job_title_resolved !== '' ? $job_title_resolved : 'Teknisi';
			}

			$address = trim((string) (isset($row['address']) ? $row['address'] : ''));
			if ($address === '')
			{
				$address = 'Kp. Kesekian Kalinya, Pandenglang, Banten';
			}

			$profile_photo = trim((string) (isset($row['profile_photo']) ? $row['profile_photo'] : ''));
			$coordinate_point = trim((string) (isset($row['coordinate_point']) ? $row['coordinate_point'] : ''));
			$employee_status = trim((string) (isset($row['employee_status']) ? $row['employee_status'] : ''));
			if ($employee_status === '')
			{
				$employee_status = 'Aktif';
			}
			$sheet_row = isset($row['sheet_row']) ? (int) $row['sheet_row'] : 0;
			if ($sheet_row < 0)
			{
				$sheet_row = 0;
			}
			$sheet_sync_source = trim((string) (isset($row['sheet_sync_source']) ? $row['sheet_sync_source'] : ''));
			$sheet_last_sync_at = trim((string) (isset($row['sheet_last_sync_at']) ? $row['sheet_last_sync_at'] : ''));
			$feature_permissions = array();
			$raw_feature_permissions = isset($row['feature_permissions']) ? $row['feature_permissions'] : array();
			if (is_string($raw_feature_permissions))
			{
				$raw_feature_permissions = preg_split('/[\s,;|]+/', trim((string) $raw_feature_permissions));
			}
			if (is_array($raw_feature_permissions))
			{
				foreach ($raw_feature_permissions as $feature_item)
				{
					$feature_key = strtolower(trim((string) $feature_item));
					if ($feature_key === '' || !in_array($feature_key, $allowed_admin_features, TRUE))
					{
						continue;
					}
					if (!in_array($feature_key, $feature_permissions, TRUE))
					{
						$feature_permissions[] = $feature_key;
					}
				}
			}

			$shift_name = trim((string) (isset($row['shift_name']) ? $row['shift_name'] : ''));
			$shift_time = trim((string) (isset($row['shift_time']) ? $row['shift_time'] : ''));
			$salary_tier = strtoupper(trim((string) (isset($row['salary_tier']) ? $row['salary_tier'] : 'A')));
			if (!isset($salary_profiles[$salary_tier]))
			{
				$salary_tier = 'A';
			}
			$salary_monthly = isset($row['salary_monthly']) ? (int) $row['salary_monthly'] : (int) $salary_profiles[$salary_tier]['salary_monthly'];
			if ($salary_monthly <= 0)
			{
				$salary_monthly = (int) $salary_profiles[$salary_tier]['salary_monthly'];
			}
			$work_days = isset($row['work_days']) ? (int) $row['work_days'] : (int) $salary_profiles[$salary_tier]['work_days'];
			if ($work_days <= 0)
			{
				$work_days = (int) $salary_profiles[$salary_tier]['work_days'];
			}

			if ($role === 'admin')
			{
				$shift_name = '';
				$shift_time = '';
				$salary_tier = '';
				$salary_monthly = 0;
				$work_days = 22;
				$sheet_row = 0;
				$sheet_sync_source = '';
				$sheet_last_sync_at = '';
			}
			else
			{
				$feature_permissions = array();
				if ($shift_name === '' || $shift_time === '')
				{
					$fallback_shift_key = 'pagi';
					if (strpos(strtolower($shift_name), 'siang') !== FALSE || strpos(strtolower($shift_time), '12:00') !== FALSE)
					{
						$fallback_shift_key = 'siang';
					}
					$shift_name = (string) $shift_profiles[$fallback_shift_key]['shift_name'];
					$shift_time = (string) $shift_profiles[$fallback_shift_key]['shift_time'];
				}
			}

			$sanitized[$username_key] = array(
				'role' => $role,
				'password' => $password,
				'password_hash' => $password,
				'force_password_change' => $force_password_change,
				'password_changed_at' => $password_changed_at,
				'login_alias' => $login_alias,
				'display_name' => $display_name,
				'branch' => $branch,
				'phone' => $phone,
				'shift_name' => $shift_name,
				'shift_time' => $shift_time,
				'salary_tier' => $salary_tier,
				'salary_monthly' => $salary_monthly,
				'work_days' => $work_days,
				'job_title' => $job_title,
				'address' => $address,
				'profile_photo' => $profile_photo,
				'coordinate_point' => $coordinate_point,
				'employee_status' => $employee_status,
				'feature_permissions' => $feature_permissions,
				'sheet_row' => $sheet_row,
				'sheet_sync_source' => $sheet_sync_source,
				'sheet_last_sync_at' => $sheet_last_sync_at
			);
		}

		$required_admin_accounts = array(
			'admin' => isset($default_book['admin']) && is_array($default_book['admin']) ? $default_book['admin'] : $default_admin,
			'developer' => isset($default_book['developer']) && is_array($default_book['developer']) ? $default_book['developer'] : array(),
			'bos' => isset($default_book['bos']) && is_array($default_book['bos']) ? $default_book['bos'] : array()
		);

		foreach ($required_admin_accounts as $required_username => $required_defaults)
		{
			if (!is_array($required_defaults))
			{
				$required_defaults = array();
			}

			if (!isset($sanitized[$required_username]) || !is_array($sanitized[$required_username]))
			{
				$sanitized[$required_username] = $required_defaults;
			}

			$sanitized[$required_username]['role'] = 'admin';
			if (!isset($sanitized[$required_username]['password']) || trim((string) $sanitized[$required_username]['password']) === '')
			{
				$fallback_password = isset($required_defaults['password']) && trim((string) $required_defaults['password']) !== ''
					? (string) $required_defaults['password']
					: ($required_username === 'admin' ? 'absen123' : '123');
				$fallback_password_hashed = absen_hash_password($fallback_password);
				$sanitized[$required_username]['password'] = $fallback_password_hashed !== '' ? $fallback_password_hashed : $fallback_password;
			}
			elseif (!absen_password_looks_hashed((string) $sanitized[$required_username]['password']))
			{
				$rehash_value = absen_hash_password((string) $sanitized[$required_username]['password']);
				if ($rehash_value !== '')
				{
					$sanitized[$required_username]['password'] = $rehash_value;
				}
			}
			$sanitized[$required_username]['password_hash'] = (string) $sanitized[$required_username]['password'];
			$current_login_alias = isset($sanitized[$required_username]['login_alias'])
				? absen_normalize_username_key_value($sanitized[$required_username]['login_alias'])
				: '';
			if ($required_username !== 'admin')
			{
				$current_login_alias = '';
			}
			elseif (in_array($current_login_alias, array('admin', 'developer', 'bos'), TRUE))
			{
				$current_login_alias = '';
			}
			$sanitized[$required_username]['login_alias'] = $current_login_alias;
			if (!isset($sanitized[$required_username]['display_name']) || trim((string) $sanitized[$required_username]['display_name']) === '')
			{
				$fallback_display_name = isset($required_defaults['display_name']) && trim((string) $required_defaults['display_name']) !== ''
					? (string) $required_defaults['display_name']
					: $required_username;
				$sanitized[$required_username]['display_name'] = $fallback_display_name;
			}
			if (!isset($sanitized[$required_username]['employee_status']) || trim((string) $sanitized[$required_username]['employee_status']) === '')
			{
				$sanitized[$required_username]['employee_status'] = 'Aktif';
			}
			if (!isset($sanitized[$required_username]['profile_photo']))
			{
				$sanitized[$required_username]['profile_photo'] = '';
			}
			$sanitized[$required_username]['coordinate_point'] = '';
			if (!isset($sanitized[$required_username]['address']) || trim((string) $sanitized[$required_username]['address']) === '')
			{
				$sanitized[$required_username]['address'] = 'Kp. Kesekian Kalinya, Pandenglang, Banten';
			}

			if ($required_username === 'admin')
			{
				$admin_branch_current = isset($sanitized[$required_username]['branch'])
					? trim((string) $sanitized[$required_username]['branch'])
					: '';
				$admin_branch_resolved = absen_resolve_employee_branch($admin_branch_current);
				if ($admin_branch_resolved === '')
				{
					$admin_branch_resolved = absen_default_employee_branch();
				}
				$sanitized[$required_username]['branch'] = $admin_branch_resolved;
			}
			else
			{
				$sanitized[$required_username]['branch'] = '';
			}
			$sanitized[$required_username]['phone'] = '';
			$sanitized[$required_username]['shift_name'] = '';
			$sanitized[$required_username]['shift_time'] = '';
			$sanitized[$required_username]['salary_tier'] = '';
			$sanitized[$required_username]['salary_monthly'] = 0;
			$sanitized[$required_username]['work_days'] = 22;
			$sanitized[$required_username]['job_title'] = 'Admin';
			$sanitized[$required_username]['sheet_row'] = 0;
			$sanitized[$required_username]['sheet_sync_source'] = '';
			$sanitized[$required_username]['sheet_last_sync_at'] = '';
			$current_required_features = isset($sanitized[$required_username]['feature_permissions'])
				? $sanitized[$required_username]['feature_permissions']
				: array();
			if (is_string($current_required_features))
			{
				$current_required_features = preg_split('/[\s,;|]+/', trim((string) $current_required_features));
			}
			$sanitized_required_features = array();
			if (is_array($current_required_features))
			{
				foreach ($current_required_features as $feature_item)
				{
					$feature_key = strtolower(trim((string) $feature_item));
					if ($feature_key === '' || !in_array($feature_key, $allowed_admin_features, TRUE))
					{
						continue;
					}
					if (!in_array($feature_key, $sanitized_required_features, TRUE))
					{
						$sanitized_required_features[] = $feature_key;
					}
				}
			}
			$default_required_features = isset($required_defaults['feature_permissions']) ? $required_defaults['feature_permissions'] : array();
			if (is_array($default_required_features))
			{
				foreach ($default_required_features as $feature_item)
				{
					$feature_key = strtolower(trim((string) $feature_item));
					if ($feature_key === '' || !in_array($feature_key, $allowed_admin_features, TRUE))
					{
						continue;
					}
					if (!in_array($feature_key, $sanitized_required_features, TRUE))
					{
						$sanitized_required_features[] = $feature_key;
					}
				}
			}
			$sanitized[$required_username]['feature_permissions'] = $sanitized_required_features;
			$sanitized[$required_username]['force_password_change'] = isset($sanitized[$required_username]['force_password_change']) && (int) $sanitized[$required_username]['force_password_change'] === 1
				? 1
				: 0;
			if (!isset($sanitized[$required_username]['password_changed_at']))
			{
				$sanitized[$required_username]['password_changed_at'] = '';
			}
		}

		ksort($sanitized);
		return $sanitized;
	}
}

if (!function_exists('absen_load_account_book'))
{
	function absen_load_account_book()
	{
		$file_path = absen_accounts_file_path();
		if (function_exists('get_instance'))
		{
			$CI =& get_instance();
			if (is_object($CI) && isset($CI->load) && is_object($CI->load))
			{
				$CI->load->helper('absen_data_store');
			}
		}

		$decoded = NULL;
		if (function_exists('absen_data_store_load_value'))
		{
			$store_key = function_exists('absen_accounts_store_key')
				? absen_accounts_store_key()
				: 'accounts_book';
			$decoded = absen_data_store_load_value($store_key, NULL, $file_path);
		}
		if (!is_array($decoded))
		{
			if (!is_file($file_path))
			{
				return absen_sanitize_account_book(absen_default_account_book());
			}

			$content = @file_get_contents($file_path);
			if ($content === FALSE || trim($content) === '')
			{
				return absen_sanitize_account_book(absen_default_account_book());
			}

			if (substr($content, 0, 3) === "\xEF\xBB\xBF")
			{
				$content = substr($content, 3);
			}

			$decoded = json_decode($content, TRUE);
			if (!is_array($decoded))
			{
				return absen_sanitize_account_book(absen_default_account_book());
			}
		}

		$account_book = array();
		foreach ($decoded as $key => $row)
		{
			if (is_int($key) && is_array($row))
			{
				$row_username = strtolower(trim((string) (isset($row['username']) ? $row['username'] : '')));
				if ($row_username !== '')
				{
					$account_book[$row_username] = $row;
				}
				continue;
			}

			$account_book[(string) $key] = $row;
		}

		$sanitized = absen_sanitize_account_book($account_book);
		if (!is_array($sanitized) || empty($sanitized))
		{
			return absen_sanitize_account_book(absen_default_account_book());
		}

		return $sanitized;
	}
}

if (!function_exists('absen_save_account_book'))
{
	function absen_save_account_book($account_book)
	{
		$sanitized = absen_sanitize_account_book($account_book);
		if (!is_array($sanitized) || empty($sanitized))
		{
			return FALSE;
		}

		$file_path = absen_accounts_file_path();
		if (function_exists('get_instance'))
		{
			$CI =& get_instance();
			if (is_object($CI) && isset($CI->load) && is_object($CI->load))
			{
				$CI->load->helper('absen_data_store');
			}
		}

		if (function_exists('absen_data_store_save_value'))
		{
			$store_key = function_exists('absen_accounts_store_key')
				? absen_accounts_store_key()
				: 'accounts_book';
			$saved_to_store = absen_data_store_save_value($store_key, $sanitized, $file_path);
			if ($saved_to_store)
			{
				return TRUE;
			}
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

		$payload = json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($payload === FALSE)
		{
			return FALSE;
		}

		return @file_put_contents($file_path, $payload) !== FALSE;
	}
}
