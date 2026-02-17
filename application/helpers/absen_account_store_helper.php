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
				'password' => '123',
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
				'employee_status' => 'Aktif',
				'sheet_row' => 0,
				'sheet_sync_source' => '',
				'sheet_last_sync_at' => ''
			);
		};

		return array(
			'admin' => array(
				'role' => 'admin',
				'password' => 'absen123',
				'display_name' => 'admin',
				'phone' => '',
				'shift_name' => '',
				'shift_time' => '',
				'salary_tier' => '',
				'salary_monthly' => 0,
				'work_days' => 22,
				'job_title' => 'Admin',
				'employee_status' => 'Aktif',
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

			$password = (string) (isset($row['password']) ? $row['password'] : '');
			if ($password === '')
			{
				$password = $role === 'admin' ? 'absen123' : '123';
			}

			$display_name = trim((string) (isset($row['display_name']) ? $row['display_name'] : ''));
			if ($display_name === '')
			{
				$display_name = $username_key;
			}

			$branch = trim((string) (isset($row['branch']) ? $row['branch'] : ''));
			if ($role === 'admin')
			{
				$branch = '';
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
				'employee_status' => $employee_status,
				'sheet_row' => $sheet_row,
				'sheet_sync_source' => $sheet_sync_source,
				'sheet_last_sync_at' => $sheet_last_sync_at
			);
		}

		if (!isset($sanitized['admin']) || !is_array($sanitized['admin']))
		{
			$sanitized['admin'] = $default_admin;
		}

		$sanitized['admin']['role'] = 'admin';
		if (!isset($sanitized['admin']['password']) || trim((string) $sanitized['admin']['password']) === '')
		{
			$sanitized['admin']['password'] = 'absen123';
		}
		if (!isset($sanitized['admin']['display_name']) || trim((string) $sanitized['admin']['display_name']) === '')
		{
			$sanitized['admin']['display_name'] = 'admin';
		}
		if (!isset($sanitized['admin']['employee_status']) || trim((string) $sanitized['admin']['employee_status']) === '')
		{
			$sanitized['admin']['employee_status'] = 'Aktif';
		}
		if (!isset($sanitized['admin']['sheet_row']) || (int) $sanitized['admin']['sheet_row'] < 0)
		{
			$sanitized['admin']['sheet_row'] = 0;
		}
		if (!isset($sanitized['admin']['sheet_sync_source']))
		{
			$sanitized['admin']['sheet_sync_source'] = '';
		}
		if (!isset($sanitized['admin']['sheet_last_sync_at']))
		{
			$sanitized['admin']['sheet_last_sync_at'] = '';
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
