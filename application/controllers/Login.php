<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Login extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('session');
		$this->load->library('absen_sheet_sync');
		$this->load->helper('absen_account_store');
		$this->load->helper('absen_data_store');
	}

	public function index()
	{
		$accounts = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		$session_timeout_seconds = function_exists('absen_password_session_timeout_seconds')
			? (int) absen_password_session_timeout_seconds()
			: 1800;

		if ($this->session->userdata('absen_logged_in') === TRUE)
		{
			$last_activity_at = (int) $this->session->userdata('absen_last_activity_at');
			$is_session_expired = function_exists('absen_session_is_expired')
				? absen_session_is_expired($last_activity_at, $session_timeout_seconds)
				: ($session_timeout_seconds > 0 && $last_activity_at > 0 && (time() - $last_activity_at) > $session_timeout_seconds);
			if ($is_session_expired)
			{
				$this->session->sess_destroy();
			}
			else
			{
				$this->session->set_userdata('absen_last_activity_at', time());
				redirect('home');
				return;
			}
		}

		$data = array(
			'title' => 'Login Absen Online',
			'login_error' => '',
			'old_username' => ''
		);

		if ($this->input->method(TRUE) === 'POST')
		{
			if (isset($this->absen_sheet_sync))
			{
				$this->absen_sheet_sync->sync_accounts_from_sheet(array('force' => FALSE));
				$accounts = function_exists('absen_load_account_book')
					? absen_load_account_book()
					: array();
			}

			$username = trim((string) $this->input->post('username', TRUE));
			$password = (string) $this->input->post('password', FALSE);
			$username_key = function_exists('absen_resolve_login_username_key')
				? absen_resolve_login_username_key($username, $accounts)
				: strtolower(trim((string) $username));
			$data['old_username'] = $username;

			if ($username === '' || $password === '')
			{
				$data['login_error'] = 'Username dan password wajib diisi.';
			}
			elseif (!isset($accounts[$username_key]) || !is_array($accounts[$username_key]))
			{
				$data['login_error'] = 'Username atau password yang kamu masukkan salah.';
			}
			else
			{
				$account = $accounts[$username_key];
				$needs_password_upgrade = FALSE;
				$is_password_valid = function_exists('absen_verify_account_password')
					? absen_verify_account_password($account, $password, $needs_password_upgrade)
					: ((isset($account['password']) ? (string) $account['password'] : '') === $password);
				if ($is_password_valid !== TRUE)
				{
					$data['login_error'] = 'Username atau password yang kamu masukkan salah.';
				}
				else
				{
					if ($needs_password_upgrade)
					{
						$accounts_for_upgrade = is_array($accounts) ? $accounts : array();
						if (isset($accounts_for_upgrade[$username_key]) && is_array($accounts_for_upgrade[$username_key]) && function_exists('absen_account_set_password'))
						{
							$must_force_change = function_exists('absen_account_requires_password_change')
								? absen_account_requires_password_change($accounts_for_upgrade[$username_key])
								: FALSE;
							absen_account_set_password($accounts_for_upgrade[$username_key], $password, $must_force_change);
							if (function_exists('absen_save_account_book'))
							{
								absen_save_account_book($accounts_for_upgrade);
							}
							$accounts = $accounts_for_upgrade;
							$account = $accounts[$username_key];
						}
					}

					$role_value = isset($account['role']) ? strtolower(trim((string) $account['role'])) : 'user';
					if ($role_value !== 'admin')
					{
						$role_value = 'user';
					}
					$branch_value = isset($account['branch']) ? trim((string) $account['branch']) : '';
					if ($role_value === 'user' && $branch_value === '' && function_exists('absen_default_employee_branch'))
					{
						$branch_value = (string) absen_default_employee_branch();
					}
					$password_change_required = function_exists('absen_account_requires_password_change')
						? absen_account_requires_password_change($account)
						: FALSE;
					$this->session->set_userdata(array(
						'absen_logged_in' => TRUE,
						'absen_username' => $username_key,
						'absen_display_name' => isset($account['display_name']) && trim((string) $account['display_name']) !== ''
							? (string) $account['display_name']
							: $username_key,
						'absen_role' => $role_value,
						'absen_shift_name' => isset($account['shift_name']) ? (string) $account['shift_name'] : '',
						'absen_shift_time' => isset($account['shift_time']) ? (string) $account['shift_time'] : '',
						'absen_phone' => isset($account['phone']) ? (string) $account['phone'] : '',
						'absen_branch' => $branch_value,
						'absen_salary_tier' => isset($account['salary_tier']) ? (string) $account['salary_tier'] : '',
						'absen_salary_monthly' => isset($account['salary_monthly']) ? (int) $account['salary_monthly'] : 0,
						'absen_work_days' => isset($account['work_days']) ? (int) $account['work_days'] : 0,
						'absen_password_change_required' => $password_change_required ? 1 : 0,
						'absen_last_activity_at' => time()
					));

					if ($password_change_required)
					{
						redirect('home/force_change_password');
						return;
					}

					redirect('home');
					return;
				}
			}
		}

		$this->load->view('auth/login', $data);
	}

	public function logout()
	{
		$this->session->sess_destroy();
		redirect('login');
	}
}
