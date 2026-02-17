<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Login extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('session');
		$this->load->library('absen_sheet_sync');
		$this->load->helper('absen_account_store');
	}

	public function index()
	{
		$accounts = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();

		if ($this->session->userdata('absen_logged_in') === TRUE)
		{
			redirect('home');
			return;
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
			$username_key = strtolower($username);
			$data['old_username'] = $username;

			if ($username === '' || $password === '')
			{
				$data['login_error'] = 'Username dan password wajib diisi.';
			}
			elseif (!isset($accounts[$username_key]) || $accounts[$username_key]['password'] !== $password)
			{
				$data['login_error'] = 'Username atau password yang kamu masukkan salah.';
			}
			else
			{
				$account = $accounts[$username_key];
				$this->session->set_userdata(array(
					'absen_logged_in' => TRUE,
					'absen_username' => $username_key,
					'absen_display_name' => isset($account['display_name']) && trim((string) $account['display_name']) !== ''
						? (string) $account['display_name']
						: $username_key,
					'absen_role' => $account['role'],
					'absen_shift_name' => $account['shift_name'],
					'absen_shift_time' => $account['shift_time'],
					'absen_phone' => isset($account['phone']) ? (string) $account['phone'] : '',
					'absen_salary_tier' => $account['salary_tier'],
					'absen_salary_monthly' => $account['salary_monthly'],
					'absen_work_days' => $account['work_days']
				));

				redirect('home');
				return;
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
