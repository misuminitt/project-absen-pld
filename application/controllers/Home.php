<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends CI_Controller {
	const OFFICE_LAT = -6.217062;
	const OFFICE_LNG = 106.1321109;
	const OFFICE_RADIUS_M = 100;
	const MAX_GPS_ACCURACY_M = 50;
	const CHECK_IN_MIN_TIME = '06:30:00';
	const CHECK_IN_MAX_TIME = '17:00:00';
	const CHECK_OUT_MAX_TIME = '23:59:00';
	const LATE_TOLERANCE_SECONDS = 600;
	const WORK_DAYS_DEFAULT = 22;
	const MIN_EFFECTIVE_WORK_DAYS = 20;
	const DEDUCTION_ROUND_BASE = 1000;
	const HALF_DAY_LATE_THRESHOLD_SECONDS = 14400;
	const WEEKLY_HOLIDAY_DAY = 1;

	public function __construct()
	{
		parent::__construct();
		$this->load->library('session');
		$this->load->library('absen_sheet_sync');
		$this->load->helper('absen_account_store');
		date_default_timezone_set('Asia/Jakarta');
	}

	public function index()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		// Sinkronisasi spreadsheet dijalankan manual lewat tombol sync.

		$role = (string) $this->session->userdata('absen_role');
		$username = (string) $this->session->userdata('absen_username');
		$display_name = (string) $this->session->userdata('absen_display_name');
		$shift_name = (string) $this->session->userdata('absen_shift_name');
		$shift_time = (string) $this->session->userdata('absen_shift_time');

		if ($role === 'user')
		{
			$user_profile = $this->get_employee_profile($username);
			$job_title = isset($user_profile['job_title']) && trim((string) $user_profile['job_title']) !== ''
				? (string) $user_profile['job_title']
				: $this->default_employee_job_title();
			$is_first_loan = $this->is_first_loan_request($username);
			$dashboard_snapshot = $this->build_user_dashboard_snapshot($username, $shift_name, $shift_time);
			$data = array(
				'title' => 'Dashboard Absen - User',
				'username' => $display_name !== '' ? $display_name : ($username !== '' ? $username : 'user'),
				'job_title' => $job_title,
				'shift_name' => $shift_name !== '' ? $shift_name : 'Shift Pagi - Sore',
				'shift_time' => $shift_time !== '' ? $shift_time : '08:00 - 17:00',
				'summary' => isset($dashboard_snapshot['summary']) && is_array($dashboard_snapshot['summary'])
					? $dashboard_snapshot['summary']
					: array(),
				'recent_logs' => isset($dashboard_snapshot['recent_logs']) && is_array($dashboard_snapshot['recent_logs'])
					? $dashboard_snapshot['recent_logs']
					: array(),
				'recent_loans' => isset($dashboard_snapshot['recent_loans']) && is_array($dashboard_snapshot['recent_loans'])
					? $dashboard_snapshot['recent_loans']
					: array(),
				'geofence' => array(
					'office_lat' => self::OFFICE_LAT,
					'office_lng' => self::OFFICE_LNG,
					'radius_m' => self::OFFICE_RADIUS_M,
					'max_accuracy_m' => self::MAX_GPS_ACCURACY_M
				),
				'loan_config' => array(
					'min_principal' => 500000,
					'max_principal' => 10000000,
					'min_tenor_months' => 1,
					'max_tenor_months' => 12,
					'is_first_loan' => $is_first_loan
				),
				'password_notice_success' => (string) $this->session->flashdata('password_notice_success'),
				'password_notice_error' => (string) $this->session->flashdata('password_notice_error')
			);

			$this->load->view('home/user_dashboard', $data);
			return;
		}

		$admin_snapshot = $this->build_admin_dashboard_snapshot();
		$data = array(
			'title' => 'Dashboard Absen Online',
			'username' => $display_name !== '' ? $display_name : $username,
			'summary' => isset($admin_snapshot['summary']) && is_array($admin_snapshot['summary'])
				? $admin_snapshot['summary']
				: array(),
			'recent_logs' => isset($admin_snapshot['recent_logs']) && is_array($admin_snapshot['recent_logs'])
				? $admin_snapshot['recent_logs']
				: array(),
			'announcements' => array(
				array('title' => 'Pengingat Check Out', 'content' => 'Jangan lupa lakukan check out sebelum jam 17:00 WIB.'),
				array('title' => 'Verifikasi Data Profil', 'content' => 'Pastikan NIP, unit kerja, dan nomor telepon sudah sesuai.'),
				array('title' => 'Kebijakan Keterlambatan', 'content' => 'Toleransi keterlambatan maksimal 10 menit dari jam masuk.')
			),
			'employee_accounts' => $this->build_employee_account_options(),
			'job_title_options' => $this->employee_job_title_options(),
			'branch_options' => $this->employee_branch_options(),
			'default_branch' => $this->default_employee_branch(),
			'account_notice_success' => (string) $this->session->flashdata('account_notice_success'),
			'account_notice_error' => (string) $this->session->flashdata('account_notice_error')
		);
		$this->load->view('home/index', $data);
	}

	public function create_employee_account()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			redirect('home');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home');
			return;
		}

		$username_key = $this->normalize_username_key($this->input->post('new_username', TRUE));
		$password = trim((string) $this->input->post('new_password', FALSE));
		$branch = $this->resolve_employee_branch($this->input->post('new_branch', TRUE));
		$phone = trim((string) $this->input->post('new_phone', TRUE));
		$shift_key = strtolower(trim((string) $this->input->post('new_shift', TRUE)));
		$salary_raw = trim((string) $this->input->post('new_salary_monthly', TRUE));
		$salary_digits = preg_replace('/\D+/', '', $salary_raw);
		$salary_monthly = $salary_digits === '' ? 0 : (int) $salary_digits;
		$job_title = trim((string) $this->input->post('new_job_title', TRUE));
		$address = trim((string) $this->input->post('new_address', TRUE));
		$work_days = (int) $this->input->post('new_work_days', TRUE);

		if ($username_key === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Username karyawan wajib diisi.');
			redirect('home');
			return;
		}

		if (!preg_match('/^[a-z0-9_]{3,30}$/', $username_key))
		{
			$this->session->set_flashdata('account_notice_error', 'Username hanya boleh huruf kecil, angka, underscore (3-30 karakter).');
			redirect('home');
			return;
		}

		if ($username_key === 'admin')
		{
			$this->session->set_flashdata('account_notice_error', 'Username admin tidak boleh dipakai untuk karyawan.');
			redirect('home');
			return;
		}

		if ($password === '' || strlen($password) < 3)
		{
			$this->session->set_flashdata('account_notice_error', 'Password minimal 3 karakter.');
			redirect('home');
			return;
		}

		if ($branch === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Cabang karyawan tidak valid.');
			redirect('home');
			return;
		}

		if ($phone === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Nomor telepon wajib diisi.');
			redirect('home');
			return;
		}

		$shift_profiles = function_exists('absen_shift_profile_book') ? absen_shift_profile_book() : array();
		if (!isset($shift_profiles[$shift_key]))
		{
			$this->session->set_flashdata('account_notice_error', 'Shift karyawan tidak valid.');
			redirect('home');
			return;
		}

		if ($salary_monthly <= 0)
		{
			$this->session->set_flashdata('account_notice_error', 'Gaji pokok wajib diisi.');
			redirect('home');
			return;
		}

		$job_title_resolved = $this->resolve_employee_job_title($job_title);
		if ($job_title !== '' && $job_title_resolved === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Jabatan karyawan tidak valid.');
			redirect('home');
			return;
		}
		if ($job_title_resolved === '')
		{
			$job_title_resolved = $this->default_employee_job_title();
		}
		$job_title = $job_title_resolved;
		if ($address === '')
		{
			$address = $this->default_employee_address();
		}

		$salary_profiles = function_exists('absen_salary_profile_book') ? absen_salary_profile_book() : array();
		$default_work_days = isset($salary_profiles['A']) && isset($salary_profiles['A']['work_days'])
			? (int) $salary_profiles['A']['work_days']
			: 28;
		if ($work_days <= 0)
		{
			$work_days = $default_work_days > 0 ? $default_work_days : 28;
		}
		$salary_tier = $this->resolve_salary_tier_from_amount($salary_monthly);

		$account_book = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (isset($account_book[$username_key]))
		{
			$this->session->set_flashdata('account_notice_error', 'Username '.$username_key.' sudah terdaftar.');
			redirect('home');
			return;
		}

		$account_book[$username_key] = array(
			'role' => 'user',
			'password' => $password,
			'display_name' => $username_key,
			'branch' => $branch,
			'phone' => $phone,
			'shift_name' => (string) $shift_profiles[$shift_key]['shift_name'],
			'shift_time' => (string) $shift_profiles[$shift_key]['shift_time'],
			'salary_tier' => $salary_tier,
			'salary_monthly' => $salary_monthly,
			'work_days' => (int) $work_days,
			'job_title' => $job_title,
			'address' => $address,
			'profile_photo' => $this->default_employee_profile_photo(),
			'employee_status' => 'Aktif',
			'sheet_row' => 0,
			'sheet_sync_source' => 'web',
			'sheet_last_sync_at' => ''
		);

		$saved = function_exists('absen_save_account_book')
			? absen_save_account_book($account_book)
			: FALSE;
		if (!$saved)
		{
			$this->session->set_flashdata('account_notice_error', 'Gagal menyimpan akun karyawan baru.');
			redirect('home');
			return;
		}

		$sheet_sync_error = '';
		$sheet_sync_warning = '';
		if (isset($this->absen_sheet_sync))
		{
			$sheet_sync_result = $this->absen_sheet_sync->push_account_to_sheet($username_key, $account_book[$username_key], 'upsert');
			if (isset($sheet_sync_result['success']) && $sheet_sync_result['success'] === TRUE)
			{
				$sheet_sync_warning = isset($sheet_sync_result['warning']) ? trim((string) $sheet_sync_result['warning']) : '';
				$sheet_row_synced = isset($sheet_sync_result['sheet_row']) ? (int) $sheet_sync_result['sheet_row'] : 0;
				if ($sheet_row_synced > 1 && (!isset($account_book[$username_key]['sheet_row']) || (int) $account_book[$username_key]['sheet_row'] !== $sheet_row_synced))
				{
					$account_book[$username_key]['sheet_row'] = $sheet_row_synced;
					$account_book[$username_key]['sheet_sync_source'] = 'google_sheet';
					$account_book[$username_key]['sheet_last_sync_at'] = date('Y-m-d H:i:s');
					if (function_exists('absen_save_account_book'))
					{
						absen_save_account_book($account_book);
					}
				}
			}
			elseif (!(isset($sheet_sync_result['skipped']) && $sheet_sync_result['skipped'] === TRUE))
			{
				$sheet_sync_error = isset($sheet_sync_result['message']) && trim((string) $sheet_sync_result['message']) !== ''
					? (string) $sheet_sync_result['message']
					: 'Sinkronisasi spreadsheet gagal.';
			}
		}

		$this->session->set_flashdata('account_notice_success', 'Akun karyawan '.$username_key.' berhasil dibuat.');
		if ($sheet_sync_error !== '')
		{
			$this->session->set_flashdata('account_notice_error', 'Akun tersimpan, tapi sync spreadsheet gagal: '.$sheet_sync_error);
		}
		elseif ($sheet_sync_warning !== '')
		{
			$this->session->set_flashdata('account_notice_error', 'Akun tersimpan, namun sinkron spreadsheet sebagian: '.$sheet_sync_warning);
		}
		redirect('home');
	}

	public function sync_sheet_accounts_now()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			redirect('home');
			return;
		}

		if (!isset($this->absen_sheet_sync))
		{
			$this->session->set_flashdata('account_notice_error', 'Library sinkronisasi spreadsheet belum aktif.');
			redirect('home');
			return;
		}

		$result = $this->absen_sheet_sync->sync_accounts_from_sheet(array('force' => TRUE));
		if (isset($result['success']) && $result['success'] === TRUE)
		{
			$created = isset($result['created']) ? (int) $result['created'] : 0;
			$updated = isset($result['updated']) ? (int) $result['updated'] : 0;
			$this->session->set_flashdata('account_notice_success', 'Sync spreadsheet selesai. Buat baru: '.$created.', update: '.$updated.'.');
		}
		else
		{
			$message = isset($result['message']) && trim((string) $result['message']) !== ''
				? (string) $result['message']
				: 'Sinkronisasi spreadsheet gagal.';
			$this->session->set_flashdata('account_notice_error', $message);
		}

		redirect('home');
	}

	public function sync_sheet_attendance_now()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			redirect('home');
			return;
		}

		if (!isset($this->absen_sheet_sync))
		{
			$this->session->set_flashdata('account_notice_error', 'Library sinkronisasi spreadsheet belum aktif.');
			redirect('home');
			return;
		}

		$result = $this->absen_sheet_sync->sync_attendance_from_sheet(array('force' => TRUE));
		if (isset($result['success']) && $result['success'] === TRUE)
		{
			$created_accounts = isset($result['created_accounts']) ? (int) $result['created_accounts'] : 0;
			$updated_accounts = isset($result['updated_accounts']) ? (int) $result['updated_accounts'] : 0;
			$created_attendance = isset($result['created_attendance']) ? (int) $result['created_attendance'] : 0;
			$updated_attendance = isset($result['updated_attendance']) ? (int) $result['updated_attendance'] : 0;
			$backfilled_phone_cells = isset($result['backfilled_phone_cells']) ? (int) $result['backfilled_phone_cells'] : 0;
			$backfilled_branch_cells = isset($result['backfilled_branch_cells']) ? (int) $result['backfilled_branch_cells'] : 0;
			$phone_backfill_error = isset($result['phone_backfill_error']) ? trim((string) $result['phone_backfill_error']) : '';
			$branch_backfill_error = isset($result['branch_backfill_error']) ? trim((string) $result['branch_backfill_error']) : '';
			$this->session->set_flashdata(
				'account_notice_success',
				'Sync Data Absen selesai. Akun baru: '.$created_accounts.', akun update: '.$updated_accounts.
				', absen baru: '.$created_attendance.', absen update: '.$updated_attendance.
				', tlp terisi otomatis: '.$backfilled_phone_cells.
				', cabang terisi otomatis: '.$backfilled_branch_cells.'.'
			);
			if ($phone_backfill_error !== '')
			{
				$this->session->set_flashdata('account_notice_error', 'Data web berhasil sync, tapi isi balik kolom Tlp ke sheet gagal: '.$phone_backfill_error);
			}
			elseif ($branch_backfill_error !== '')
			{
				$this->session->set_flashdata('account_notice_error', 'Data web berhasil sync, tapi isi balik kolom Cabang ke sheet gagal: '.$branch_backfill_error);
			}
		}
		else
		{
			$message = isset($result['message']) && trim((string) $result['message']) !== ''
				? (string) $result['message']
				: 'Sinkronisasi Data Absen gagal.';
			$this->session->set_flashdata('account_notice_error', $message);
		}

		redirect('home');
	}

	public function sync_web_attendance_to_sheet_now()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			redirect('home');
			return;
		}

		if (!isset($this->absen_sheet_sync))
		{
			$this->session->set_flashdata('account_notice_error', 'Library sinkronisasi spreadsheet belum aktif.');
			redirect('home');
			return;
		}

		$result = $this->absen_sheet_sync->sync_attendance_to_sheet(array('force' => TRUE));
		if (isset($result['success']) && $result['success'] === TRUE)
		{
			$month = isset($result['month']) ? (string) $result['month'] : date('Y-m');
			$processed_users = isset($result['processed_users']) ? (int) $result['processed_users'] : 0;
			$updated_rows = isset($result['updated_rows']) ? (int) $result['updated_rows'] : 0;
			$appended_rows = isset($result['appended_rows']) ? (int) $result['appended_rows'] : 0;
			$this->session->set_flashdata(
				'account_notice_success',
				'Sync data web -> Data Absen selesai. Bulan: '.$month.', user diproses: '.$processed_users.', row update: '.$updated_rows.', row baru: '.$appended_rows.'.'
			);
		}
		else
		{
			$message = isset($result['message']) && trim((string) $result['message']) !== ''
				? (string) $result['message']
				: 'Sinkronisasi data web -> Data Absen gagal.';
			$this->session->set_flashdata('account_notice_error', $message);
		}

		redirect('home');
	}

	public function sync_sheet_accounts_cli()
	{
		if (!$this->input->is_cli_request())
		{
			show_404();
			return;
		}

		if (!isset($this->absen_sheet_sync))
		{
			echo "Library sinkronisasi spreadsheet belum aktif.\n";
			return;
		}

		$result = $this->absen_sheet_sync->sync_accounts_from_sheet(array('force' => TRUE));
		if (isset($result['success']) && $result['success'] === TRUE)
		{
			$created = isset($result['created']) ? (int) $result['created'] : 0;
			$updated = isset($result['updated']) ? (int) $result['updated'] : 0;
			$processed = isset($result['processed']) ? (int) $result['processed'] : 0;
			echo "Sync spreadsheet OK. processed=".$processed.", created=".$created.", updated=".$updated."\n";
			return;
		}

		$message = isset($result['message']) && trim((string) $result['message']) !== ''
			? (string) $result['message']
			: 'Sinkronisasi spreadsheet gagal.';
		echo "Sync spreadsheet gagal: ".$message."\n";
	}

	public function sync_sheet_attendance_cli()
	{
		if (!$this->input->is_cli_request())
		{
			show_404();
			return;
		}

		if (!isset($this->absen_sheet_sync))
		{
			echo "Library sinkronisasi spreadsheet belum aktif.\n";
			return;
		}

		$result = $this->absen_sheet_sync->sync_attendance_from_sheet(array('force' => TRUE));
		if (isset($result['success']) && $result['success'] === TRUE)
		{
			$processed = isset($result['processed']) ? (int) $result['processed'] : 0;
			$created_accounts = isset($result['created_accounts']) ? (int) $result['created_accounts'] : 0;
			$updated_accounts = isset($result['updated_accounts']) ? (int) $result['updated_accounts'] : 0;
			$created_attendance = isset($result['created_attendance']) ? (int) $result['created_attendance'] : 0;
			$updated_attendance = isset($result['updated_attendance']) ? (int) $result['updated_attendance'] : 0;
			$skipped_rows = isset($result['skipped_rows']) ? (int) $result['skipped_rows'] : 0;
			$backfilled_phone_cells = isset($result['backfilled_phone_cells']) ? (int) $result['backfilled_phone_cells'] : 0;
			$phone_backfill_error = isset($result['phone_backfill_error']) ? trim((string) $result['phone_backfill_error']) : '';
			echo "Sync Data Absen OK. processed=".$processed.
				", account_created=".$created_accounts.
				", account_updated=".$updated_accounts.
				", attendance_created=".$created_attendance.
				", attendance_updated=".$updated_attendance.
				", skipped=".$skipped_rows.
				", tlp_backfilled=".$backfilled_phone_cells."\n";
			if ($phone_backfill_error !== '')
			{
				echo "Peringatan: isi balik kolom Tlp ke sheet gagal: ".$phone_backfill_error."\n";
			}
			return;
		}

		$message = isset($result['message']) && trim((string) $result['message']) !== ''
			? (string) $result['message']
			: 'Sinkronisasi Data Absen gagal.';
		echo "Sync Data Absen gagal: ".$message."\n";
	}

	public function sync_web_attendance_to_sheet_cli()
	{
		if (!$this->input->is_cli_request())
		{
			show_404();
			return;
		}

		if (!isset($this->absen_sheet_sync))
		{
			echo "Library sinkronisasi spreadsheet belum aktif.\n";
			return;
		}

		$result = $this->absen_sheet_sync->sync_attendance_to_sheet(array('force' => TRUE));
		if (isset($result['success']) && $result['success'] === TRUE)
		{
			$month = isset($result['month']) ? (string) $result['month'] : date('Y-m');
			$processed_users = isset($result['processed_users']) ? (int) $result['processed_users'] : 0;
			$updated_rows = isset($result['updated_rows']) ? (int) $result['updated_rows'] : 0;
			$appended_rows = isset($result['appended_rows']) ? (int) $result['appended_rows'] : 0;
			$skipped_users = isset($result['skipped_users']) ? (int) $result['skipped_users'] : 0;
			echo "Sync web->Data Absen OK. month=".$month.
				", users=".$processed_users.
				", updated=".$updated_rows.
				", appended=".$appended_rows.
				", skipped=".$skipped_users."\n";
			return;
		}

		$message = isset($result['message']) && trim((string) $result['message']) !== ''
			? (string) $result['message']
			: 'Sinkronisasi web -> Data Absen gagal.';
		echo "Sync web->Data Absen gagal: ".$message."\n";
	}

	public function delete_employee_account()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			redirect('home');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home');
			return;
		}

		$username_key = strtolower(trim((string) $this->input->post('delete_username', TRUE)));
		if ($username_key === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Pilih akun karyawan yang ingin dihapus.');
			redirect('home');
			return;
		}

		if ($username_key === 'admin')
		{
			$this->session->set_flashdata('account_notice_error', 'Akun admin tidak dapat dihapus.');
			redirect('home');
			return;
		}

		$account_book = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (!isset($account_book[$username_key]) || !is_array($account_book[$username_key]))
		{
			$this->session->set_flashdata('account_notice_error', 'Akun karyawan tidak ditemukan.');
			redirect('home');
			return;
		}

		$role = strtolower(trim((string) (isset($account_book[$username_key]['role']) ? $account_book[$username_key]['role'] : 'user')));
		if ($role !== 'user')
		{
			$this->session->set_flashdata('account_notice_error', 'Hanya akun karyawan yang bisa dihapus.');
			redirect('home');
			return;
		}

		$deleted_account_row = $account_book[$username_key];
		unset($account_book[$username_key]);
		$saved = function_exists('absen_save_account_book')
			? absen_save_account_book($account_book)
			: FALSE;
		if (!$saved)
		{
			$this->session->set_flashdata('account_notice_error', 'Gagal menghapus akun karyawan.');
			redirect('home');
			return;
		}

		$purge_summary = $this->purge_employee_related_records($username_key);
		$removed_total = (int) $purge_summary['attendance'] + (int) $purge_summary['leave'] + (int) $purge_summary['loan'] + (int) $purge_summary['overtime'];
		$sheet_sync_error = '';
		$sheet_sync_warning = '';
		if (isset($this->absen_sheet_sync))
		{
			$sheet_sync_result = $this->absen_sheet_sync->push_account_to_sheet($username_key, $deleted_account_row, 'delete');
			if (isset($sheet_sync_result['success']) && $sheet_sync_result['success'] === TRUE)
			{
				$sheet_sync_warning = isset($sheet_sync_result['warning']) ? trim((string) $sheet_sync_result['warning']) : '';
			}
			elseif (!(isset($sheet_sync_result['success']) && $sheet_sync_result['success'] === TRUE) &&
				!(isset($sheet_sync_result['skipped']) && $sheet_sync_result['skipped'] === TRUE))
			{
				$sheet_sync_error = isset($sheet_sync_result['message']) && trim((string) $sheet_sync_result['message']) !== ''
					? (string) $sheet_sync_result['message']
					: 'Sinkronisasi spreadsheet gagal.';
			}
		}

		$this->session->set_flashdata('account_notice_success', 'Akun '.$username_key.' berhasil dihapus. Data terkait yang dibersihkan: '.$removed_total.' baris.');
		if ($sheet_sync_error !== '')
		{
			$this->session->set_flashdata('account_notice_error', 'Akun dihapus, tapi sync spreadsheet gagal: '.$sheet_sync_error);
		}
		elseif ($sheet_sync_warning !== '')
		{
			$this->session->set_flashdata('account_notice_error', 'Akun dihapus, namun sinkron spreadsheet sebagian: '.$sheet_sync_warning);
		}
		redirect('home');
	}

	public function update_employee_account()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			redirect('home');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home');
			return;
		}

		$username_key = strtolower(trim((string) $this->input->post('edit_username', TRUE)));
		$new_username_raw = $this->input->post('edit_new_username', TRUE);
		$has_new_username_field = $this->input->post('edit_new_username', FALSE) !== NULL;
		$new_username_key = $this->normalize_username_key($new_username_raw);
		if (!$has_new_username_field)
		{
			// Kompatibel dengan halaman lama yang belum punya input edit_new_username.
			$new_username_key = $username_key;
		}
		$password_input = trim((string) $this->input->post('edit_password', FALSE));
		$branch = $this->resolve_employee_branch($this->input->post('edit_branch', TRUE));
		$phone = trim((string) $this->input->post('edit_phone', TRUE));
		$shift_key = strtolower(trim((string) $this->input->post('edit_shift', TRUE)));
		$salary_raw = trim((string) $this->input->post('edit_salary_monthly', TRUE));
		$salary_digits = preg_replace('/\D+/', '', $salary_raw);
		$salary_monthly = $salary_digits === '' ? 0 : (int) $salary_digits;
		$job_title = trim((string) $this->input->post('edit_job_title', TRUE));
		$address = trim((string) $this->input->post('edit_address', TRUE));
		$work_days = (int) $this->input->post('edit_work_days', TRUE);

		if ($username_key === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Pilih akun karyawan yang ingin diedit.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($new_username_key === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Username akun wajib diisi.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if (!preg_match('/^[a-z0-9_]{3,30}$/', $new_username_key))
		{
			$this->session->set_flashdata('account_notice_error', 'Username hanya boleh huruf kecil, angka, underscore (3-30 karakter).');
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($username_key === 'admin')
		{
			$this->session->set_flashdata('account_notice_error', 'Akun admin tidak bisa diedit dari form ini.');
			redirect('home#manajemen-karyawan');
			return;
		}
		if ($new_username_key === 'admin')
		{
			$this->session->set_flashdata('account_notice_error', 'Username admin tidak boleh dipakai untuk karyawan.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$account_book = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (!isset($account_book[$username_key]) || !is_array($account_book[$username_key]))
		{
			$this->session->set_flashdata('account_notice_error', 'Akun karyawan tidak ditemukan.');
			redirect('home#manajemen-karyawan');
			return;
		}
		if ($new_username_key !== $username_key && isset($account_book[$new_username_key]))
		{
			$this->session->set_flashdata('account_notice_error', 'Username '.$new_username_key.' sudah terdaftar. Coba tambah akhiran seperti _2.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$role = strtolower(trim((string) (isset($account_book[$username_key]['role']) ? $account_book[$username_key]['role'] : 'user')));
		if ($role !== 'user')
		{
			$this->session->set_flashdata('account_notice_error', 'Hanya akun karyawan yang bisa diedit.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($password_input !== '' && strlen($password_input) < 3)
		{
			$this->session->set_flashdata('account_notice_error', 'Password baru minimal 3 karakter.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($branch === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Cabang karyawan tidak valid.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($phone === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Nomor telepon wajib diisi.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$shift_profiles = function_exists('absen_shift_profile_book') ? absen_shift_profile_book() : array();
		if (!isset($shift_profiles[$shift_key]))
		{
			$this->session->set_flashdata('account_notice_error', 'Shift karyawan tidak valid.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($salary_monthly <= 0)
		{
			$this->session->set_flashdata('account_notice_error', 'Gaji pokok wajib diisi.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$job_title_resolved = $this->resolve_employee_job_title($job_title);
		if ($job_title !== '' && $job_title_resolved === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Jabatan karyawan tidak valid.');
			redirect('home#manajemen-karyawan');
			return;
		}
		if ($job_title_resolved === '')
		{
			$job_title_resolved = $this->default_employee_job_title();
		}
		$job_title = $job_title_resolved;
		if ($address === '')
		{
			$address = $this->default_employee_address();
		}
		$salary_profiles = function_exists('absen_salary_profile_book') ? absen_salary_profile_book() : array();
		$default_work_days = isset($salary_profiles['A']) && isset($salary_profiles['A']['work_days'])
			? (int) $salary_profiles['A']['work_days']
			: 28;
		if ($work_days <= 0)
		{
			$work_days = $default_work_days > 0 ? $default_work_days : 28;
		}
		$salary_tier = $this->resolve_salary_tier_from_amount($salary_monthly);

		$current_row = $account_book[$username_key];
		$display_name_value = isset($current_row['display_name']) ? trim((string) $current_row['display_name']) : '';
		if ($display_name_value === '' || strtolower($display_name_value) === $username_key)
		{
			$display_name_value = $new_username_key;
		}

		$updated_account_row = array(
			'role' => 'user',
			'password' => $password_input !== ''
				? $password_input
				: (isset($current_row['password']) ? (string) $current_row['password'] : '123'),
			'display_name' => $display_name_value,
			'branch' => $branch,
			'phone' => $phone,
			'shift_name' => (string) $shift_profiles[$shift_key]['shift_name'],
			'shift_time' => (string) $shift_profiles[$shift_key]['shift_time'],
			'salary_tier' => $salary_tier,
			'salary_monthly' => $salary_monthly,
			'work_days' => (int) $work_days,
			'job_title' => $job_title,
			'address' => $address,
			'profile_photo' => isset($current_row['profile_photo']) && trim((string) $current_row['profile_photo']) !== ''
				? (string) $current_row['profile_photo']
				: $this->default_employee_profile_photo(),
			'employee_status' => isset($current_row['employee_status']) && trim((string) $current_row['employee_status']) !== ''
				? (string) $current_row['employee_status']
				: 'Aktif',
			'sheet_row' => isset($current_row['sheet_row']) ? (int) $current_row['sheet_row'] : 0,
			'sheet_sync_source' => 'web',
			'sheet_last_sync_at' => date('Y-m-d H:i:s')
		);
		if ($new_username_key !== $username_key)
		{
			unset($account_book[$username_key]);
		}
		$account_book[$new_username_key] = $updated_account_row;

		$saved = function_exists('absen_save_account_book')
			? absen_save_account_book($account_book)
			: FALSE;
		if (!$saved)
		{
			$this->session->set_flashdata('account_notice_error', 'Gagal menyimpan perubahan akun karyawan.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$renamed_related = array(
			'attendance' => 0,
			'leave' => 0,
			'loan' => 0,
			'overtime' => 0
		);
		if ($new_username_key !== $username_key)
		{
			$renamed_related = $this->rename_employee_related_records($username_key, $new_username_key);
		}

		$sheet_sync_error = '';
		$sheet_sync_warning = '';
		if (isset($this->absen_sheet_sync))
		{
			$sheet_sync_result = $this->absen_sheet_sync->push_account_to_sheet($new_username_key, $account_book[$new_username_key], 'upsert');
			if (isset($sheet_sync_result['success']) && $sheet_sync_result['success'] === TRUE)
			{
				$sheet_sync_warning = isset($sheet_sync_result['warning']) ? trim((string) $sheet_sync_result['warning']) : '';
				$sheet_row_synced = isset($sheet_sync_result['sheet_row']) ? (int) $sheet_sync_result['sheet_row'] : 0;
				if ($sheet_row_synced > 1 && (!isset($account_book[$new_username_key]['sheet_row']) || (int) $account_book[$new_username_key]['sheet_row'] !== $sheet_row_synced))
				{
					$account_book[$new_username_key]['sheet_row'] = $sheet_row_synced;
					$account_book[$new_username_key]['sheet_sync_source'] = 'google_sheet';
					$account_book[$new_username_key]['sheet_last_sync_at'] = date('Y-m-d H:i:s');
					if (function_exists('absen_save_account_book'))
					{
						absen_save_account_book($account_book);
					}
				}
			}
			elseif (!(isset($sheet_sync_result['skipped']) && $sheet_sync_result['skipped'] === TRUE))
			{
				$sheet_sync_error = isset($sheet_sync_result['message']) && trim((string) $sheet_sync_result['message']) !== ''
					? (string) $sheet_sync_result['message']
					: 'Sinkronisasi spreadsheet gagal.';
			}
		}

		$success_message = 'Akun '.$new_username_key.' berhasil diperbarui.';
		if ($new_username_key !== $username_key)
		{
			$renamed_total = (int) $renamed_related['attendance'] + (int) $renamed_related['leave'] + (int) $renamed_related['loan'] + (int) $renamed_related['overtime'];
			$success_message = 'Akun '.$username_key.' berhasil diganti menjadi '.$new_username_key.'. Referensi data yang diperbarui: '.$renamed_total.' baris.';
		}
		$this->session->set_flashdata('account_notice_success', $success_message);
		if ($sheet_sync_error !== '')
		{
			$this->session->set_flashdata('account_notice_error', 'Perubahan tersimpan, tapi sync spreadsheet gagal: '.$sheet_sync_error);
		}
		elseif ($sheet_sync_warning !== '')
		{
			$this->session->set_flashdata('account_notice_error', 'Perubahan tersimpan, namun sinkron spreadsheet sebagian: '.$sheet_sync_warning);
		}
		redirect('home#manajemen-karyawan');
	}

	public function admin_dashboard_live_summary()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Sesi login sudah habis.'), 401);
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Akses ditolak.'), 403);
			return;
		}

		$snapshot = $this->build_admin_dashboard_snapshot();
		$this->json_response(array(
			'success' => TRUE,
			'summary' => isset($snapshot['summary']) && is_array($snapshot['summary']) ? $snapshot['summary'] : array(),
			'generated_at' => date('Y-m-d H:i:s')
		));
	}

	public function admin_metric_chart_data()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Sesi login sudah habis.'), 401);
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Akses ditolak.'), 403);
			return;
		}

		$metric = strtolower(trim((string) $this->input->get('metric', TRUE)));
		$range = strtoupper(trim((string) $this->input->get('range', TRUE)));

		$payload = $this->build_admin_metric_chart_payload($metric, $range);
		if (isset($payload['success']) && $payload['success'] === FALSE)
		{
			$status_code = isset($payload['status_code']) ? (int) $payload['status_code'] : 422;
			unset($payload['status_code']);
			$this->json_response($payload, $status_code);
			return;
		}

		$this->json_response(array_merge(array('success' => TRUE), $payload));
	}

	public function submit_attendance()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Sesi login sudah habis.'), 401);
			return;
		}

		if ((string) $this->session->userdata('absen_role') !== 'user')
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Akses ditolak.'), 403);
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Metode request tidak valid.'), 405);
			return;
		}

		$action = strtolower(trim((string) $this->input->post('action', TRUE)));
		$photo = (string) $this->input->post('photo', FALSE);
		$latitude = trim((string) $this->input->post('latitude', TRUE));
		$longitude = trim((string) $this->input->post('longitude', TRUE));
		$accuracy = trim((string) $this->input->post('accuracy', TRUE));
		$late_reason_input = trim((string) $this->input->post('late_reason', TRUE));

		if ($action !== 'masuk' && $action !== 'pulang')
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Jenis absensi tidak valid.'), 422);
			return;
		}

		if ($photo === '' || strpos($photo, 'data:image/') !== 0)
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Foto absensi wajib diambil.'), 422);
			return;
		}

		if (!is_numeric($latitude) || !is_numeric($longitude))
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Koordinat GPS tidak valid.'), 422);
			return;
		}

		if (!is_numeric($accuracy))
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Akurasi GPS tidak valid.'), 422);
			return;
		}

		$accuracy_m = (float) $accuracy;
		if ($accuracy_m <= 0 || $accuracy_m > 1000)
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Akurasi GPS tidak valid.'), 422);
			return;
		}

		$distance_m = $this->calculate_distance_meter((float) $latitude, (float) $longitude, self::OFFICE_LAT, self::OFFICE_LNG);
		$geofence_check = $this->evaluate_geofence($distance_m, $accuracy_m);
		if ($geofence_check['inside'] !== TRUE)
		{
			$this->json_response(array(
				'success' => FALSE,
				'message' => $geofence_check['message']
			), 422);
			return;
		}

		$username = (string) $this->session->userdata('absen_username');
		$shift_name = (string) $this->session->userdata('absen_shift_name');
		$shift_time = (string) $this->session->userdata('absen_shift_time');
		$session_salary_tier = strtoupper(trim((string) $this->session->userdata('absen_salary_tier')));
		$session_salary_monthly = (float) $this->session->userdata('absen_salary_monthly');
		$session_work_days = (int) $this->session->userdata('absen_work_days');
		$user_profile = $this->get_employee_profile($username);
		$profile_salary_tier = isset($user_profile['salary_tier']) ? strtoupper(trim((string) $user_profile['salary_tier'])) : '';
		$profile_salary_monthly = isset($user_profile['salary_monthly']) ? (float) $user_profile['salary_monthly'] : 0;
		$profile_work_days = isset($user_profile['work_days']) ? (int) $user_profile['work_days'] : 0;
		$salary_tier = $profile_salary_tier !== '' ? $profile_salary_tier : $session_salary_tier;
		$salary_monthly = $profile_salary_monthly > 0 ? $profile_salary_monthly : $session_salary_monthly;
		$date_key = date('Y-m-d');
		$date_label = date('d-m-Y');
		$current_time = date('H:i:s');
		$current_seconds = $this->time_to_seconds($current_time);
		$month_policy = $this->calculate_month_work_policy($date_key);
		$work_days = $profile_work_days > 0
			? $profile_work_days
			: ($session_work_days > 0 ? $session_work_days : $month_policy['work_days']);

		if ($action === 'masuk')
		{
			if ($current_seconds < $this->time_to_seconds(self::CHECK_IN_MIN_TIME))
			{
				$this->json_response(array(
					'success' => FALSE,
					'message' => 'Absen masuk baru bisa dilakukan mulai jam 06:30 WIB.'
				), 422);
				return;
			}

			if ($current_seconds > $this->time_to_seconds(self::CHECK_IN_MAX_TIME))
			{
				$this->json_response(array(
					'success' => FALSE,
					'message' => 'Batas maksimal absen masuk adalah jam 17:00 WIB.'
				), 422);
				return;
			}
		}
		else
		{
			if ($current_seconds > $this->time_to_seconds(self::CHECK_OUT_MAX_TIME))
			{
				$this->json_response(array(
					'success' => FALSE,
					'message' => 'Batas maksimal absen pulang adalah jam 23:59 WIB.'
				), 422);
				return;
			}
		}

		$records = $this->load_attendance_records();
		$record_index = -1;
		for ($i = 0; $i < count($records); $i += 1)
		{
			if ($records[$i]['username'] === $username && $records[$i]['date'] === $date_key)
			{
				$record_index = $i;
				break;
			}
		}

		if ($record_index === -1)
		{
			$records[] = array(
				'username' => $username,
				'date' => $date_key,
				'date_label' => $date_label,
				'shift_name' => $shift_name,
				'shift_time' => $shift_time,
				'check_in_time' => '',
				'check_in_late' => '00:00:00',
				'check_in_photo' => '',
				'check_in_lat' => '',
				'check_in_lng' => '',
				'check_in_accuracy_m' => '',
				'check_in_distance_m' => '',
				'late_reason' => '',
				'salary_cut_amount' => 0,
				'salary_cut_rule' => '',
				'salary_cut_category' => '',
				'salary_tier' => $salary_tier,
				'salary_monthly' => number_format($salary_monthly, 0, '.', ''),
				'work_days_per_month' => $work_days,
				'days_in_month' => $month_policy['days_in_month'],
				'weekly_off_days' => $month_policy['weekly_off_days'],
				'check_out_time' => '',
				'work_duration' => '',
				'check_out_photo' => '',
				'check_out_lat' => '',
				'check_out_lng' => '',
				'check_out_accuracy_m' => '',
				'check_out_distance_m' => '',
				'updated_at' => date('Y-m-d H:i:s')
			);
			$record_index = count($records) - 1;
		}

		$record = $records[$record_index];
		$record['shift_name'] = $shift_name;
		$record['shift_time'] = $shift_time;
		$record['salary_tier'] = $salary_tier;
		$record['salary_monthly'] = number_format($salary_monthly, 0, '.', '');
		$record['work_days_per_month'] = $work_days;
		$record['days_in_month'] = $month_policy['days_in_month'];
		$record['weekly_off_days'] = $month_policy['weekly_off_days'];

		if ($action === 'masuk')
		{
			$record['check_in_time'] = $current_time;
			$record['check_in_late'] = $this->calculate_late_duration($current_time, $shift_time);
			$record['check_in_photo'] = $photo;
			$record['check_in_lat'] = (string) $latitude;
			$record['check_in_lng'] = (string) $longitude;
			$record['check_in_accuracy_m'] = number_format($accuracy_m, 2, '.', '');
			$record['check_in_distance_m'] = number_format($distance_m, 2, '.', '');

			$late_seconds = $this->duration_to_seconds($record['check_in_late']);
			if ($late_seconds > 0 && $late_reason_input === '')
			{
				$this->json_response(array(
					'success' => FALSE,
					'message' => 'Kamu telat masuk, alasan keterlambatan wajib diisi.'
				), 422);
				return;
			}

			$record['late_reason'] = $late_seconds > 0 ? $late_reason_input : '';
			$deduction_result = $this->calculate_late_deduction(
				$salary_tier,
				$salary_monthly,
				$work_days,
				$late_seconds,
				$date_key,
				$username
			);
			$record['salary_cut_amount'] = number_format($deduction_result['amount'], 0, '.', '');
			$record['salary_cut_rule'] = $deduction_result['rule'];
			$record['salary_cut_category'] = isset($deduction_result['category_key']) ? (string) $deduction_result['category_key'] : '';

			if ($record['check_out_time'] !== '')
			{
				$record['work_duration'] = $this->calculate_work_duration($record['check_in_time'], $record['check_out_time']);
			}

			$message = 'Absen masuk berhasil disimpan.';
		}
		else
		{
			if ($record['check_in_time'] === '')
			{
				$this->json_response(array('success' => FALSE, 'message' => 'Absen masuk harus dilakukan terlebih dahulu.'), 422);
				return;
			}

			$record['check_out_time'] = $current_time;
			$record['check_out_photo'] = $photo;
			$record['check_out_lat'] = (string) $latitude;
			$record['check_out_lng'] = (string) $longitude;
			$record['check_out_accuracy_m'] = number_format($accuracy_m, 2, '.', '');
			$record['check_out_distance_m'] = number_format($distance_m, 2, '.', '');
			$record['work_duration'] = $this->calculate_work_duration($record['check_in_time'], $current_time);
			$message = 'Absen pulang berhasil disimpan.';
		}

		$record['updated_at'] = date('Y-m-d H:i:s');
		$records[$record_index] = $record;

		$this->save_attendance_records($records);

		$this->json_response(array(
			'success' => TRUE,
			'message' => $message
		));
	}

	public function user_dashboard_live_data()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Sesi login sudah habis.'), 401);
			return;
		}

		if ((string) $this->session->userdata('absen_role') !== 'user')
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Akses ditolak.'), 403);
			return;
		}

		$username = (string) $this->session->userdata('absen_username');
		$shift_name = (string) $this->session->userdata('absen_shift_name');
		$shift_time = (string) $this->session->userdata('absen_shift_time');
		$snapshot = $this->build_user_dashboard_snapshot($username, $shift_name, $shift_time);

		$this->json_response(array(
			'success' => TRUE,
			'summary' => isset($snapshot['summary']) && is_array($snapshot['summary']) ? $snapshot['summary'] : array(),
			'recent_logs' => isset($snapshot['recent_logs']) && is_array($snapshot['recent_logs']) ? $snapshot['recent_logs'] : array(),
			'recent_loans' => isset($snapshot['recent_loans']) && is_array($snapshot['recent_loans']) ? $snapshot['recent_loans'] : array(),
			'updated_at' => date('Y-m-d H:i:s')
		));
	}

	public function update_my_password()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') !== 'user')
		{
			redirect('home');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home#ubah-password');
			return;
		}

		$username_key = strtolower(trim((string) $this->session->userdata('absen_username')));
		$current_password = trim((string) $this->input->post('current_password', FALSE));
		$new_password = trim((string) $this->input->post('new_password', FALSE));
		$confirm_password = trim((string) $this->input->post('confirm_password', FALSE));

		if ($username_key === '')
		{
			$this->session->set_flashdata('password_notice_error', 'Akun login tidak valid. Silakan login ulang.');
			redirect('home#ubah-password');
			return;
		}

		if ($current_password === '')
		{
			$this->session->set_flashdata('password_notice_error', 'Password saat ini wajib diisi.');
			redirect('home#ubah-password');
			return;
		}

		if ($new_password === '' || strlen($new_password) < 3)
		{
			$this->session->set_flashdata('password_notice_error', 'Password baru minimal 3 karakter.');
			redirect('home#ubah-password');
			return;
		}

		if ($new_password !== $confirm_password)
		{
			$this->session->set_flashdata('password_notice_error', 'Konfirmasi password baru tidak sama.');
			redirect('home#ubah-password');
			return;
		}

		if ($new_password === $current_password)
		{
			$this->session->set_flashdata('password_notice_error', 'Password baru harus berbeda dari password saat ini.');
			redirect('home#ubah-password');
			return;
		}

		$account_book = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (!isset($account_book[$username_key]) || !is_array($account_book[$username_key]))
		{
			$this->session->set_flashdata('password_notice_error', 'Akun tidak ditemukan. Silakan login ulang.');
			redirect('home#ubah-password');
			return;
		}

		$role = strtolower(trim((string) (isset($account_book[$username_key]['role']) ? $account_book[$username_key]['role'] : 'user')));
		if ($role !== 'user')
		{
			$this->session->set_flashdata('password_notice_error', 'Hanya akun karyawan yang bisa ubah password dari halaman ini.');
			redirect('home#ubah-password');
			return;
		}

		$stored_password = isset($account_book[$username_key]['password'])
			? (string) $account_book[$username_key]['password']
			: '';
		if ($stored_password === '' || $stored_password !== $current_password)
		{
			$this->session->set_flashdata('password_notice_error', 'Password saat ini tidak sesuai.');
			redirect('home#ubah-password');
			return;
		}

		$account_book[$username_key]['password'] = $new_password;
		$saved = function_exists('absen_save_account_book')
			? absen_save_account_book($account_book)
			: FALSE;
		if (!$saved)
		{
			$this->session->set_flashdata('password_notice_error', 'Gagal menyimpan password baru. Coba lagi.');
			redirect('home#ubah-password');
			return;
		}

		$this->session->set_flashdata('password_notice_success', 'Password berhasil diperbarui.');
		redirect('home#ubah-password');
	}

	public function employee_data()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			redirect('home');
			return;
		}

		$records = $this->load_attendance_records();
		$employee_id_book = $this->employee_id_book();
		for ($i = 0; $i < count($records); $i += 1)
		{
			$row_username = isset($records[$i]['username']) ? (string) $records[$i]['username'] : '';
			$records[$i]['employee_id'] = $this->resolve_employee_id_from_book($row_username, $employee_id_book);
			$row_profile = $this->get_employee_profile($row_username);
			$records[$i]['profile_photo'] = isset($row_profile['profile_photo']) && trim((string) $row_profile['profile_photo']) !== ''
				? (string) $row_profile['profile_photo']
				: $this->default_employee_profile_photo();
			$records[$i]['address'] = isset($row_profile['address']) && trim((string) $row_profile['address']) !== ''
				? (string) $row_profile['address']
				: $this->default_employee_address();
			$records[$i]['job_title'] = isset($row_profile['job_title']) && trim((string) $row_profile['job_title']) !== ''
				? (string) $row_profile['job_title']
				: $this->default_employee_job_title();
			$records[$i]['phone'] = isset($row_profile['phone']) && trim((string) $row_profile['phone']) !== ''
				? (string) $row_profile['phone']
				: $this->get_employee_phone($row_username);

			if (!isset($records[$i]['late_reason']))
			{
				$records[$i]['late_reason'] = '';
			}

			$salary_cut_rule_existing = isset($records[$i]['salary_cut_rule']) ? (string) $records[$i]['salary_cut_rule'] : '';
			$is_admin_adjusted_cut = stripos($salary_cut_rule_existing, 'Disesuaikan admin') === 0;
			if ($is_admin_adjusted_cut !== TRUE)
			{
				$check_in_time = isset($records[$i]['check_in_time']) ? trim((string) $records[$i]['check_in_time']) : '';
				$shift_time = isset($records[$i]['shift_time']) ? trim((string) $records[$i]['shift_time']) : '';
				if ($check_in_time !== '' && $shift_time !== '')
				{
					$records[$i]['check_in_late'] = $this->calculate_late_duration($check_in_time, $shift_time);
				}

				$late_seconds = 0;
				if (isset($records[$i]['check_in_late']) && $records[$i]['check_in_late'] !== '')
				{
					$late_seconds = $this->duration_to_seconds((string) $records[$i]['check_in_late']);
				}
				$salary_tier = isset($records[$i]['salary_tier']) ? (string) $records[$i]['salary_tier'] : '';
				$salary_monthly = isset($records[$i]['salary_monthly']) ? (float) $records[$i]['salary_monthly'] : 0;
				$record_date = isset($records[$i]['date']) ? (string) $records[$i]['date'] : '';
				$month_policy = $this->calculate_month_work_policy($record_date);
				$work_days = isset($records[$i]['work_days_per_month']) && (int) $records[$i]['work_days_per_month'] > 0
					? (int) $records[$i]['work_days_per_month']
					: $month_policy['work_days'];
				$deduction_result = $this->calculate_late_deduction(
					$salary_tier,
					$salary_monthly,
					$work_days,
					$late_seconds,
					$record_date,
					$row_username
				);
				$records[$i]['salary_cut_amount'] = number_format($deduction_result['amount'], 0, '.', '');
				$records[$i]['salary_cut_rule'] = $deduction_result['rule'];
				$records[$i]['salary_cut_category'] = isset($deduction_result['category_key']) ? (string) $deduction_result['category_key'] : '';
			}

			if (!isset($records[$i]['work_days_per_month']) || (int) $records[$i]['work_days_per_month'] <= 0)
			{
				$record_date = isset($records[$i]['date']) ? (string) $records[$i]['date'] : '';
				$month_policy = $this->calculate_month_work_policy($record_date);
				$records[$i]['work_days_per_month'] = $month_policy['work_days'];
				$records[$i]['days_in_month'] = $month_policy['days_in_month'];
				$records[$i]['weekly_off_days'] = $month_policy['weekly_off_days'];
			}

			if ((!isset($records[$i]['check_in_distance_m']) || $records[$i]['check_in_distance_m'] === '') &&
				isset($records[$i]['check_in_lat']) && isset($records[$i]['check_in_lng']) &&
				is_numeric($records[$i]['check_in_lat']) && is_numeric($records[$i]['check_in_lng']))
			{
				$records[$i]['check_in_distance_m'] = number_format(
					$this->calculate_distance_meter(
						(float) $records[$i]['check_in_lat'],
						(float) $records[$i]['check_in_lng'],
						self::OFFICE_LAT,
						self::OFFICE_LNG
					),
					2,
					'.',
					''
				);
			}

			if ((!isset($records[$i]['check_out_distance_m']) || $records[$i]['check_out_distance_m'] === '') &&
				isset($records[$i]['check_out_lat']) && isset($records[$i]['check_out_lng']) &&
				is_numeric($records[$i]['check_out_lat']) && is_numeric($records[$i]['check_out_lng']))
			{
				$records[$i]['check_out_distance_m'] = number_format(
					$this->calculate_distance_meter(
						(float) $records[$i]['check_out_lat'],
						(float) $records[$i]['check_out_lng'],
						self::OFFICE_LAT,
						self::OFFICE_LNG
					),
					2,
					'.',
					''
				);
			}
		}
		usort($records, function ($a, $b) {
			$left = (string) $a['date'].' '.(string) $a['check_in_time'];
			$right = (string) $b['date'].' '.(string) $b['check_in_time'];
			return strcmp($right, $left);
		});

		$data = array(
			'title' => 'Data Absensi Karyawan',
			'records' => $records
		);
		$this->load->view('home/employee_attendance', $data);
	}

	public function employee_data_monthly()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			redirect('home');
			return;
		}

		$month_input = trim((string) $this->input->get('month', TRUE));
		if (!preg_match('/^\d{4}-\d{2}$/', $month_input))
		{
			$month_input = date('Y-m');
		}

		$month_start = $month_input.'-01';
		$month_start_ts = strtotime($month_start.' 00:00:00');
		if ($month_start_ts === FALSE)
		{
			$month_input = date('Y-m');
			$month_start = $month_input.'-01';
			$month_start_ts = strtotime($month_start.' 00:00:00');
		}

		$year = (int) date('Y', $month_start_ts);
		$month = (int) date('n', $month_start_ts);
		$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		$month_end = sprintf('%04d-%02d-%02d', $year, $month, $days_in_month);
		$month_end_ts = strtotime($month_end.' 23:59:59');
		$month_policy = $this->calculate_month_work_policy($month_start);
		$work_days_default = isset($month_policy['work_days']) ? (int) $month_policy['work_days'] : self::WORK_DAYS_DEFAULT;
		$weekly_day_off_n = $this->normalize_weekly_day_off(self::WEEKLY_HOLIDAY_DAY);
		$year_start_ts = strtotime(sprintf('%04d-01-01 00:00:00', $year));
		$before_period_end_ts = strtotime($month_start.' 00:00:00') - 1;

		$month_names = array(
			1 => 'Januari',
			2 => 'Februari',
			3 => 'Maret',
			4 => 'April',
			5 => 'Mei',
			6 => 'Juni',
			7 => 'Juli',
			8 => 'Agustus',
			9 => 'September',
			10 => 'Oktober',
			11 => 'November',
			12 => 'Desember'
		);
		$month_label = (isset($month_names[$month]) ? $month_names[$month] : date('F', $month_start_ts)).' '.$year;

		$records = $this->load_attendance_records();
		$users = array();
		$employee_id_book = $this->employee_id_book();

		for ($i = 0; $i < count($records); $i += 1)
		{
			$row = $records[$i];
			$username = isset($row['username']) ? trim((string) $row['username']) : '';
			if ($username === '')
			{
				continue;
			}
			$employee_id = $this->resolve_employee_id_from_book($username, $employee_id_book);
			$user_profile = $this->get_employee_profile($username);
			$profile_work_days = isset($user_profile['work_days']) && (int) $user_profile['work_days'] > 0
				? (int) $user_profile['work_days']
				: 0;
			$resolved_work_days = $profile_work_days > 0 ? $profile_work_days : $work_days_default;
			$profile_salary_tier = isset($user_profile['salary_tier']) ? trim((string) $user_profile['salary_tier']) : '';
			$profile_salary_monthly = isset($user_profile['salary_monthly']) ? (float) $user_profile['salary_monthly'] : 0;
			$profile_photo = isset($user_profile['profile_photo']) ? trim((string) $user_profile['profile_photo']) : '';
			$profile_address = isset($user_profile['address']) ? trim((string) $user_profile['address']) : '';
			$profile_job_title = isset($user_profile['job_title']) ? trim((string) $user_profile['job_title']) : '';
			$profile_phone = isset($user_profile['phone']) ? trim((string) $user_profile['phone']) : '';

			if (!isset($users[$username]))
			{
				$users[$username] = array(
					'username' => $username,
					'employee_id' => $employee_id,
					'profile_photo' => $profile_photo !== '' ? $profile_photo : $this->default_employee_profile_photo(),
					'address' => $profile_address !== '' ? $profile_address : $this->default_employee_address(),
					'job_title' => $profile_job_title !== '' ? $profile_job_title : $this->default_employee_job_title(),
					'phone' => $profile_phone !== '' ? $profile_phone : $this->get_employee_phone($username),
					'salary_tier' => $profile_salary_tier !== ''
						? $profile_salary_tier
						: (isset($row['salary_tier']) ? (string) $row['salary_tier'] : ''),
					'salary_monthly' => $profile_salary_monthly > 0
						? $profile_salary_monthly
						: (isset($row['salary_monthly']) ? (float) $row['salary_monthly'] : 0),
					'work_days_plan' => isset($row['work_days_per_month']) && (int) $row['work_days_per_month'] > 0
						? (int) $row['work_days_per_month']
						: $resolved_work_days,
					'hadir_dates' => array(),
					'leave_dates' => array(),
					'izin_dates' => array(),
					'cuti_dates' => array(),
					'leave_type_by_date' => array(),
					'cuti_before_dates' => array(),
					'izin_days_total' => 0,
					'cuti_days_total' => 0,
					'late_1_30' => 0,
					'late_31_60' => 0,
					'late_1_3' => 0,
					'late_gt_4' => 0,
					'sheet_summary' => array(),
					'has_activity' => FALSE
				);
			}
			else
			{
				if (isset($row['salary_tier']) && trim((string) $row['salary_tier']) !== '')
				{
					$users[$username]['salary_tier'] = (string) $row['salary_tier'];
				}
				if (isset($row['salary_monthly']) && (float) $row['salary_monthly'] > 0)
				{
					$users[$username]['salary_monthly'] = (float) $row['salary_monthly'];
				}
				if (isset($row['work_days_per_month']) && (int) $row['work_days_per_month'] > 0)
				{
					$users[$username]['work_days_plan'] = (int) $row['work_days_per_month'];
				}
				if (!isset($users[$username]['employee_id']) || trim((string) $users[$username]['employee_id']) === '')
				{
					$users[$username]['employee_id'] = $employee_id;
				}
				if (!isset($users[$username]['profile_photo']) || trim((string) $users[$username]['profile_photo']) === '')
				{
					$users[$username]['profile_photo'] = $profile_photo !== '' ? $profile_photo : $this->default_employee_profile_photo();
				}
				if (!isset($users[$username]['address']) || trim((string) $users[$username]['address']) === '')
				{
					$users[$username]['address'] = $profile_address !== '' ? $profile_address : $this->default_employee_address();
				}
				if (!isset($users[$username]['job_title']) || trim((string) $users[$username]['job_title']) === '')
				{
					$users[$username]['job_title'] = $profile_job_title !== '' ? $profile_job_title : $this->default_employee_job_title();
				}
				if (!isset($users[$username]['phone']) || trim((string) $users[$username]['phone']) === '')
				{
					$users[$username]['phone'] = $profile_phone !== '' ? $profile_phone : $this->get_employee_phone($username);
				}
			}

			// Samakan seluruh akun user dengan konfigurasi profil karyawan.
			if ($profile_salary_tier !== '')
			{
				$users[$username]['salary_tier'] = $profile_salary_tier;
			}
			if ($profile_salary_monthly > 0)
			{
				$users[$username]['salary_monthly'] = $profile_salary_monthly;
			}
			if ($profile_work_days > 0)
			{
				$users[$username]['work_days_plan'] = $profile_work_days;
			}
			if ($profile_photo !== '')
			{
				$users[$username]['profile_photo'] = $profile_photo;
			}
			if ($profile_address !== '')
			{
				$users[$username]['address'] = $profile_address;
			}
			if ($profile_job_title !== '')
			{
				$users[$username]['job_title'] = $profile_job_title;
			}
			if ($profile_phone !== '')
			{
				$users[$username]['phone'] = $profile_phone;
			}

			$row_date = isset($row['date']) ? (string) $row['date'] : '';
			if (!$this->is_valid_date_format($row_date) || substr($row_date, 0, 7) !== $month_input)
			{
				continue;
			}

			$users[$username]['has_activity'] = TRUE;
			$sheet_month = isset($row['sheet_month']) ? trim((string) $row['sheet_month']) : substr($row_date, 0, 7);
			if ($sheet_month === $month_input && (isset($row['sheet_total_hadir']) || isset($row['sheet_sudah_berapa_absen']) || isset($row['sheet_total_alpha'])))
			{
				$summary_sort_key = isset($row['updated_at']) ? trim((string) $row['updated_at']) : '';
				if ($summary_sort_key === '')
				{
					$summary_sort_key = $row_date.' 00:00:00';
				}
				$current_sort = isset($users[$username]['sheet_summary']['_sort_key']) ? (string) $users[$username]['sheet_summary']['_sort_key'] : '';
				if ($current_sort === '' || strcmp($summary_sort_key, $current_sort) >= 0)
				{
					$users[$username]['sheet_summary'] = array(
						'_sort_key' => $summary_sort_key,
						'hari_efektif' => isset($row['sheet_hari_efektif']) ? (int) $row['sheet_hari_efektif'] : 0,
						'sudah_berapa_absen' => isset($row['sheet_sudah_berapa_absen']) ? (int) $row['sheet_sudah_berapa_absen'] : 0,
						'total_hadir' => isset($row['sheet_total_hadir']) ? (int) $row['sheet_total_hadir'] : 0,
						'total_telat_1_30' => isset($row['sheet_total_telat_1_30']) ? (int) $row['sheet_total_telat_1_30'] : 0,
						'total_telat_31_60' => isset($row['sheet_total_telat_31_60']) ? (int) $row['sheet_total_telat_31_60'] : 0,
						'total_telat_1_3' => isset($row['sheet_total_telat_1_3']) ? (int) $row['sheet_total_telat_1_3'] : 0,
						'total_telat_gt_4' => isset($row['sheet_total_telat_gt_4']) ? (int) $row['sheet_total_telat_gt_4'] : 0,
						'total_izin_cuti' => isset($row['sheet_total_izin_cuti']) ? (int) $row['sheet_total_izin_cuti'] : 0,
						'total_alpha' => isset($row['sheet_total_alpha']) ? (int) $row['sheet_total_alpha'] : 0
					);
				}
			}

			$check_in_time = isset($row['check_in_time']) ? trim((string) $row['check_in_time']) : '';
			if ($check_in_time !== '')
			{
				$users[$username]['hadir_dates'][$row_date] = TRUE;
				$late_seconds = 0;
				$late_category = '';
				$shift_time = isset($row['shift_time']) ? trim((string) $row['shift_time']) : '';
				$computed_late = $shift_time !== '' ? $this->calculate_late_duration($check_in_time, $shift_time) : '';
				if ($computed_late !== '')
				{
					$late_seconds = $this->duration_to_seconds($computed_late);
				}
				elseif (isset($row['check_in_late']) && trim((string) $row['check_in_late']) !== '')
				{
					$late_seconds = $this->duration_to_seconds((string) $row['check_in_late']);
				}

				if ($late_seconds > 0 && $late_seconds <= 1800)
				{
					$late_category = 'late_1_30';
				}
				elseif ($late_seconds > 1800 && $late_seconds <= 3600)
				{
					$late_category = 'late_31_60';
				}
				elseif ($late_seconds > 3600 && $late_seconds <= self::HALF_DAY_LATE_THRESHOLD_SECONDS)
				{
					$late_category = 'late_1_3';
				}
				elseif ($late_seconds > self::HALF_DAY_LATE_THRESHOLD_SECONDS)
				{
					$late_category = 'late_gt_4';
				}

				if ($late_category !== '')
				{
					$salary_cut_raw = isset($row['salary_cut_amount']) ? (string) $row['salary_cut_amount'] : '';
					$salary_cut_digits = preg_replace('/\D+/', '', $salary_cut_raw);
					$salary_cut_amount = $salary_cut_digits === '' ? 0 : (int) $salary_cut_digits;
					$salary_cut_rule = isset($row['salary_cut_rule']) ? strtolower(trim((string) $row['salary_cut_rule'])) : '';
					$is_admin_removed_cut = strpos($salary_cut_rule, 'disesuaikan admin (potongan dihapus)') === 0;
					$should_count_late = FALSE;

					if ($salary_cut_amount > 0)
					{
						$should_count_late = TRUE;
					}
					elseif ($is_admin_removed_cut !== TRUE)
					{
						// Fallback untuk data lama yang belum punya salary_cut_amount lengkap.
						$should_count_late = TRUE;
					}

					if ($should_count_late)
					{
						if ($late_category === 'late_1_30')
						{
							$users[$username]['late_1_30'] += 1;
						}
						elseif ($late_category === 'late_31_60')
						{
							$users[$username]['late_31_60'] += 1;
						}
						elseif ($late_category === 'late_1_3')
						{
							$users[$username]['late_1_3'] += 1;
						}
						elseif ($late_category === 'late_gt_4')
						{
							$users[$username]['late_gt_4'] += 1;
						}
					}
				}
			}
		}

		$leave_requests = $this->load_leave_requests();
		for ($i = 0; $i < count($leave_requests); $i += 1)
		{
			$request = $leave_requests[$i];
			$status = isset($request['status']) ? strtolower(trim((string) $request['status'])) : '';
			if ($status !== 'diterima')
			{
				continue;
			}

			$username = isset($request['username']) ? trim((string) $request['username']) : '';
			if ($username === '')
			{
				continue;
			}
			$employee_id = $this->resolve_employee_id_from_book($username, $employee_id_book);
			$user_profile = $this->get_employee_profile($username);
			$profile_work_days = isset($user_profile['work_days']) && (int) $user_profile['work_days'] > 0
				? (int) $user_profile['work_days']
				: 0;
			$resolved_work_days = $profile_work_days > 0 ? $profile_work_days : $work_days_default;
			$profile_salary_tier = isset($user_profile['salary_tier']) ? trim((string) $user_profile['salary_tier']) : '';
			$profile_salary_monthly = isset($user_profile['salary_monthly']) ? (float) $user_profile['salary_monthly'] : 0;
			$profile_photo = isset($user_profile['profile_photo']) ? trim((string) $user_profile['profile_photo']) : '';
			$profile_address = isset($user_profile['address']) ? trim((string) $user_profile['address']) : '';
			$profile_job_title = isset($user_profile['job_title']) ? trim((string) $user_profile['job_title']) : '';
			$profile_phone = isset($user_profile['phone']) ? trim((string) $user_profile['phone']) : '';

			if (!isset($users[$username]))
			{
				$users[$username] = array(
					'username' => $username,
					'employee_id' => $employee_id,
					'profile_photo' => $profile_photo !== '' ? $profile_photo : $this->default_employee_profile_photo(),
					'address' => $profile_address !== '' ? $profile_address : $this->default_employee_address(),
					'job_title' => $profile_job_title !== '' ? $profile_job_title : $this->default_employee_job_title(),
					'phone' => $profile_phone !== '' ? $profile_phone : $this->get_employee_phone($username),
					'salary_tier' => $profile_salary_tier,
					'salary_monthly' => $profile_salary_monthly,
					'work_days_plan' => $resolved_work_days,
					'hadir_dates' => array(),
					'leave_dates' => array(),
					'izin_dates' => array(),
					'cuti_dates' => array(),
					'leave_type_by_date' => array(),
					'cuti_before_dates' => array(),
					'izin_days_total' => 0,
					'cuti_days_total' => 0,
					'late_1_30' => 0,
					'late_31_60' => 0,
					'late_1_3' => 0,
					'late_gt_4' => 0,
					'sheet_summary' => array(),
					'has_activity' => FALSE
				);
			}
			else
			{
				if ($profile_salary_tier !== '')
				{
					$users[$username]['salary_tier'] = $profile_salary_tier;
				}
				if ($profile_salary_monthly > 0)
				{
					$users[$username]['salary_monthly'] = $profile_salary_monthly;
				}
				if ($profile_work_days > 0)
				{
					$users[$username]['work_days_plan'] = $profile_work_days;
				}
				if (!isset($users[$username]['employee_id']) || trim((string) $users[$username]['employee_id']) === '')
				{
					$users[$username]['employee_id'] = $employee_id;
				}
				if (!isset($users[$username]['profile_photo']) || trim((string) $users[$username]['profile_photo']) === '')
				{
					$users[$username]['profile_photo'] = $profile_photo !== '' ? $profile_photo : $this->default_employee_profile_photo();
				}
				if (!isset($users[$username]['address']) || trim((string) $users[$username]['address']) === '')
				{
					$users[$username]['address'] = $profile_address !== '' ? $profile_address : $this->default_employee_address();
				}
				if (!isset($users[$username]['job_title']) || trim((string) $users[$username]['job_title']) === '')
				{
					$users[$username]['job_title'] = $profile_job_title !== '' ? $profile_job_title : $this->default_employee_job_title();
				}
				if (!isset($users[$username]['phone']) || trim((string) $users[$username]['phone']) === '')
				{
					$users[$username]['phone'] = $profile_phone !== '' ? $profile_phone : $this->get_employee_phone($username);
				}
			}

			$request_type = isset($request['request_type']) ? strtolower(trim((string) $request['request_type'])) : '';
			$request_type_label = isset($request['request_type_label']) ? strtolower(trim((string) $request['request_type_label'])) : '';
			$is_izin_request = $request_type === 'izin' || $request_type_label === 'izin';
			$is_cuti_request = $request_type === 'cuti' || $request_type_label === 'cuti';
			if ($is_izin_request !== TRUE && $is_cuti_request !== TRUE)
			{
				continue;
			}

			$start_date = isset($request['start_date']) ? trim((string) $request['start_date']) : '';
			$end_date = isset($request['end_date']) ? trim((string) $request['end_date']) : '';
			if (!$this->is_valid_date_format($start_date) || !$this->is_valid_date_format($end_date))
			{
				continue;
			}

			$start_ts = strtotime($start_date.' 00:00:00');
			$end_ts = strtotime($end_date.' 23:59:59');
			if ($start_ts === FALSE || $end_ts === FALSE || $end_ts < $start_ts)
			{
				continue;
			}

			if ($is_cuti_request && $year_start_ts !== FALSE && $before_period_end_ts !== FALSE && $before_period_end_ts >= $year_start_ts)
			{
				$before_overlap_start = max($start_ts, $year_start_ts);
				$before_overlap_end = min($end_ts, $before_period_end_ts);
				if ($before_overlap_end >= $before_overlap_start)
				{
					$before_loop_start = strtotime(date('Y-m-d', $before_overlap_start).' 00:00:00');
					$before_loop_end = strtotime(date('Y-m-d', $before_overlap_end).' 00:00:00');
					for ($ts_before = $before_loop_start; $ts_before <= $before_loop_end; $ts_before += 86400)
					{
						$before_date_key = date('Y-m-d', $ts_before);
						$users[$username]['cuti_before_dates'][$before_date_key] = TRUE;
					}
				}
			}

			$overlap_start = max($start_ts, $month_start_ts);
			$overlap_end = min($end_ts, $month_end_ts);
			if ($overlap_end < $overlap_start)
			{
				continue;
			}

			$users[$username]['has_activity'] = TRUE;
			$loop_start = strtotime(date('Y-m-d', $overlap_start).' 00:00:00');
			$loop_end = strtotime(date('Y-m-d', $overlap_end).' 00:00:00');
			for ($ts = $loop_start; $ts <= $loop_end; $ts += 86400)
			{
				$date_key = date('Y-m-d', $ts);
				// Total izin/cuti bulanan diambil dari data pengajuan yang sudah diterima.
				if (isset($users[$username]['hadir_dates'][$date_key]))
				{
					continue;
				}

				$users[$username]['leave_dates'][$date_key] = TRUE;
				if ($is_izin_request)
				{
					$users[$username]['izin_dates'][$date_key] = TRUE;
					$users[$username]['leave_type_by_date'][$date_key] = 'izin';
					if (isset($users[$username]['cuti_dates'][$date_key]))
					{
						unset($users[$username]['cuti_dates'][$date_key]);
					}
				}
				elseif (!isset($users[$username]['izin_dates'][$date_key]))
				{
					$users[$username]['cuti_dates'][$date_key] = TRUE;
					if (!isset($users[$username]['leave_type_by_date'][$date_key]))
					{
						$users[$username]['leave_type_by_date'][$date_key] = 'cuti';
					}
				}
			}
		}

		$rows = array();
		foreach ($users as $username => $user_data)
		{
			if (!isset($user_data['has_activity']) || $user_data['has_activity'] !== TRUE)
			{
				continue;
			}

			$work_days_plan = isset($user_data['work_days_plan']) ? (int) $user_data['work_days_plan'] : $work_days_default;
			if ($work_days_plan <= 0)
			{
				$work_days_plan = $work_days_default;
			}

			$hadir_days = isset($user_data['hadir_dates']) && is_array($user_data['hadir_dates'])
				? count($user_data['hadir_dates'])
				: 0;
			$leave_days = isset($user_data['leave_dates']) && is_array($user_data['leave_dates'])
				? count($user_data['leave_dates'])
				: 0;
			$izin_days = isset($user_data['izin_dates']) && is_array($user_data['izin_dates'])
				? count($user_data['izin_dates'])
				: 0;
			$cuti_days = isset($user_data['cuti_dates']) && is_array($user_data['cuti_dates'])
				? count($user_data['cuti_dates'])
				: 0;
			$leave_used_before_period = isset($user_data['cuti_before_dates']) && is_array($user_data['cuti_before_dates'])
				? count($user_data['cuti_before_dates'])
				: 0;
			$hari_efektif_bulan = max($days_in_month - (int) $month_policy['weekly_off_days'], 1);
			$total_alpha = $hari_efektif_bulan - $hadir_days - $leave_days;
			if ($total_alpha < 0)
			{
				$total_alpha = 0;
			}
			$total_alpha_izin = $total_alpha + $izin_days;
			$total_telat_1_30 = isset($user_data['late_1_30']) ? (int) $user_data['late_1_30'] : 0;
			$total_telat_31_60 = isset($user_data['late_31_60']) ? (int) $user_data['late_31_60'] : 0;
			$total_telat_1_3_jam = isset($user_data['late_1_3']) ? (int) $user_data['late_1_3'] : 0;
			$total_telat_gt_4_jam = isset($user_data['late_gt_4']) ? (int) $user_data['late_gt_4'] : 0;
			$sheet_summary = isset($user_data['sheet_summary']) && is_array($user_data['sheet_summary'])
				? $user_data['sheet_summary']
				: array();
			$has_sheet_summary = !empty($sheet_summary);
			if ($has_sheet_summary)
			{
				$sheet_hari_efektif = isset($sheet_summary['hari_efektif']) ? (int) $sheet_summary['hari_efektif'] : 0;
				if ($sheet_hari_efektif > 0)
				{
					$hari_efektif_bulan = $sheet_hari_efektif;
					$work_days_plan = $sheet_hari_efektif;
				}

				$sheet_hadir = isset($sheet_summary['total_hadir']) ? (int) $sheet_summary['total_hadir'] : 0;
				if ($sheet_hadir <= 0)
				{
					$sheet_hadir = isset($sheet_summary['sudah_berapa_absen']) ? (int) $sheet_summary['sudah_berapa_absen'] : 0;
				}
				$sheet_hadir = max(0, $sheet_hadir);
				$hadir_days = $sheet_hadir;

				$sheet_izin_cuti = isset($sheet_summary['total_izin_cuti']) ? (int) $sheet_summary['total_izin_cuti'] : 0;
				$sheet_izin_cuti = max(0, $sheet_izin_cuti);
				$izin_days = $sheet_izin_cuti;
				$cuti_days = 0;
				$leave_days = $izin_days + $cuti_days;

				$sheet_alpha = isset($sheet_summary['total_alpha']) ? (int) $sheet_summary['total_alpha'] : 0;
				$total_alpha = max(0, $sheet_alpha);
				$total_alpha_izin = $total_alpha + $izin_days;

				$total_telat_1_30 = max(0, (int) (isset($sheet_summary['total_telat_1_30']) ? $sheet_summary['total_telat_1_30'] : 0));
				$total_telat_31_60 = max(0, (int) (isset($sheet_summary['total_telat_31_60']) ? $sheet_summary['total_telat_31_60'] : 0));
				$total_telat_1_3_jam = max(0, (int) (isset($sheet_summary['total_telat_1_3']) ? $sheet_summary['total_telat_1_3'] : 0));
				$total_telat_gt_4_jam = max(0, (int) (isset($sheet_summary['total_telat_gt_4']) ? $sheet_summary['total_telat_gt_4'] : 0));
				$leave_used_before_period = 0;
			}

			if (!$has_sheet_summary && $this->should_randomize_monthly_demo_data($hadir_days, $leave_days, $total_alpha, $hari_efektif_bulan))
			{
				$randomized = $this->build_randomized_monthly_demo_data(
					$username,
					$year,
					$month,
					$hari_efektif_bulan,
					$leave_used_before_period
				);
				$hadir_days = isset($randomized['hadir_days']) ? (int) $randomized['hadir_days'] : $hadir_days;
				$izin_days = isset($randomized['izin_days']) ? (int) $randomized['izin_days'] : $izin_days;
				$cuti_days = isset($randomized['cuti_days']) ? (int) $randomized['cuti_days'] : $cuti_days;
				$leave_days = $izin_days + $cuti_days;
				$total_alpha = isset($randomized['total_alpha']) ? (int) $randomized['total_alpha'] : $total_alpha;
				if ($total_alpha < 0)
				{
					$total_alpha = 0;
				}
				$total_alpha_izin = $total_alpha + $izin_days;
				$total_telat_1_30 = isset($randomized['late_1_30']) ? (int) $randomized['late_1_30'] : $total_telat_1_30;
				$total_telat_31_60 = isset($randomized['late_31_60']) ? (int) $randomized['late_31_60'] : $total_telat_31_60;
				$total_telat_1_3_jam = isset($randomized['late_1_3']) ? (int) $randomized['late_1_3'] : $total_telat_1_3_jam;
				$total_telat_gt_4_jam = isset($randomized['late_gt_4']) ? (int) $randomized['late_gt_4'] : $total_telat_gt_4_jam;
			}

			$salary_tier = isset($user_data['salary_tier']) ? (string) $user_data['salary_tier'] : '';
			$salary_monthly = $this->resolve_monthly_salary($salary_tier, isset($user_data['salary_monthly']) ? (float) $user_data['salary_monthly'] : 0);
			$summary = $this->calculate_monthly_deduction_summary(
				$salary_monthly,
				$year,
				$month,
				$weekly_day_off_n,
				$leave_used_before_period,
				$cuti_days,
				$month_policy['weekly_off_days'],
				$total_alpha_izin,
				$total_telat_1_30,
				$total_telat_31_60,
				$total_telat_1_3_jam,
				$total_telat_gt_4_jam
			);
			$potongan_per_hari = isset($summary['potongan_per_hari']) && is_array($summary['potongan_per_hari'])
				? $summary['potongan_per_hari']
				: array();
			$total_telat_1_30 = isset($summary['adjusted_counts']['telat_1_30']) ? (int) $summary['adjusted_counts']['telat_1_30'] : $total_telat_1_30;
			$total_telat_31_60 = isset($summary['adjusted_counts']['telat_31_60']) ? (int) $summary['adjusted_counts']['telat_31_60'] : $total_telat_31_60;
			$total_telat_1_3_jam = isset($summary['adjusted_counts']['telat_1_3_jam']) ? (int) $summary['adjusted_counts']['telat_1_3_jam'] : $total_telat_1_3_jam;
			$total_telat_gt_4_jam = isset($summary['adjusted_counts']['telat_gt_4_jam']) ? (int) $summary['adjusted_counts']['telat_gt_4_jam'] : $total_telat_gt_4_jam;
			$total_alpha_izin = isset($summary['adjusted_counts']['alpha_final']) ? (int) $summary['adjusted_counts']['alpha_final'] : $total_alpha_izin;
			$total_1_30_amount = (isset($potongan_per_hari['telat_1_30']) ? (int) $potongan_per_hari['telat_1_30'] : 0) * $total_telat_1_30;
			$total_31_60_amount = (isset($potongan_per_hari['telat_31_60']) ? (int) $potongan_per_hari['telat_31_60'] : 0) * $total_telat_31_60;
			$total_1_3_amount = (isset($potongan_per_hari['telat_1_3_jam']) ? (int) $potongan_per_hari['telat_1_3_jam'] : 0) * $total_telat_1_3_jam;
			$total_gt_4_amount = (isset($potongan_per_hari['telat_gt_4_jam']) ? (int) $potongan_per_hari['telat_gt_4_jam'] : 0) * $total_telat_gt_4_jam;
			$total_alpha_izin_amount = (isset($potongan_per_hari['alpha']) ? (int) $potongan_per_hari['alpha'] : 0) * $total_alpha_izin;

			$rows[] = array(
				'username' => $username,
				'employee_id' => isset($user_data['employee_id']) ? (string) $user_data['employee_id'] : '-',
				'profile_photo' => isset($user_data['profile_photo']) && trim((string) $user_data['profile_photo']) !== ''
					? (string) $user_data['profile_photo']
					: $this->default_employee_profile_photo(),
				'address' => isset($user_data['address']) && trim((string) $user_data['address']) !== ''
					? (string) $user_data['address']
					: $this->default_employee_address(),
				'job_title' => isset($user_data['job_title']) && trim((string) $user_data['job_title']) !== ''
					? (string) $user_data['job_title']
					: $this->default_employee_job_title(),
				'phone' => isset($user_data['phone']) && trim((string) $user_data['phone']) !== ''
					? (string) $user_data['phone']
					: $this->get_employee_phone($username),
				'salary_monthly' => (int) round($salary_monthly),
				'work_days_plan' => $work_days_plan,
				'hadir_days' => $hadir_days,
				'izin_days' => $izin_days,
				'cuti_days' => $cuti_days,
				'leave_days' => $leave_days,
				'total_alpha' => $total_alpha,
				'total_alpha_izin' => $total_alpha_izin,
				'total_telat_1_30' => $total_telat_1_30,
				'total_telat_31_60' => $total_telat_31_60,
				'total_telat_1_3_jam' => $total_telat_1_3_jam,
				'total_telat_gt_4_jam' => $total_telat_gt_4_jam,
				'potongan_per_hari' => $potongan_per_hari,
				'leave_used_before_period' => $leave_used_before_period,
				'weekly_quota' => isset($summary['weekly_quota']) ? (int) $summary['weekly_quota'] : $month_policy['weekly_off_days'],
				'remaining_leave' => isset($summary['remaining_leave']) ? (int) $summary['remaining_leave'] : 0,
				'overflow_awal' => isset($summary['overflow_awal']) ? (int) $summary['overflow_awal'] : 0,
				'total_1_30_amount' => (int) $total_1_30_amount,
				'total_31_60_amount' => (int) $total_31_60_amount,
				'total_1_3_amount' => (int) $total_1_3_amount,
				'total_gt_4_amount' => (int) $total_gt_4_amount,
				'total_alpha_izin_amount' => (int) $total_alpha_izin_amount,
				'total_potongan' => isset($summary['total_potongan']) ? (int) $summary['total_potongan'] : 0,
				'gaji_bersih' => isset($summary['gaji_bersih']) ? (int) $summary['gaji_bersih'] : (int) round($salary_monthly),
				'hari_effective' => isset($summary['hari_effective']) ? (int) $summary['hari_effective'] : max($work_days_plan, self::MIN_EFFECTIVE_WORK_DAYS)
			);
		}

		usort($rows, function ($a, $b) {
			return strcmp(
				strtolower((string) $a['username']),
				strtolower((string) $b['username'])
			);
		});

		$data = array(
			'title' => 'Data Absensi Bulanan',
			'selected_month' => $month_input,
			'selected_month_label' => $month_label,
			'rows' => $rows
		);
		$this->load->view('home/employee_attendance_monthly', $data);
	}

	public function update_attendance_deduction()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			redirect('home');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home/employee_data');
			return;
		}

		$username = trim((string) $this->input->post('username', TRUE));
		$date_key = trim((string) $this->input->post('date', TRUE));
		$salary_cut_raw = trim((string) $this->input->post('salary_cut_amount', TRUE));
		$salary_cut_digits = preg_replace('/\D+/', '', $salary_cut_raw);
		$salary_cut_amount = $salary_cut_digits === '' ? 0 : (int) $salary_cut_digits;

		if ($username === '' || $date_key === '')
		{
			$this->session->set_flashdata('attendance_notice_error', 'Data absensi yang ingin diedit tidak valid.');
			redirect('home/employee_data');
			return;
		}

		$records = $this->load_attendance_records();
		$record_index = -1;
		for ($i = 0; $i < count($records); $i += 1)
		{
			$row_username = isset($records[$i]['username']) ? (string) $records[$i]['username'] : '';
			$row_date = isset($records[$i]['date']) ? (string) $records[$i]['date'] : '';
			if ($row_username === $username && $row_date === $date_key)
			{
				$record_index = $i;
				break;
			}
		}

		if ($record_index < 0)
		{
			$this->session->set_flashdata('attendance_notice_error', 'Data absensi tidak ditemukan.');
			redirect('home/employee_data');
			return;
		}

		$records[$record_index]['salary_cut_amount'] = number_format(max(0, $salary_cut_amount), 0, '.', '');
		$records[$record_index]['salary_cut_rule'] = $salary_cut_amount > 0
			? 'Disesuaikan admin'
			: 'Disesuaikan admin (potongan dihapus)';
		$records[$record_index]['salary_cut_adjusted_by'] = (string) $this->session->userdata('absen_username');
		$records[$record_index]['salary_cut_adjusted_at'] = date('Y-m-d H:i:s');
		$records[$record_index]['updated_at'] = date('Y-m-d H:i:s');

		$this->save_attendance_records($records);

		if ($salary_cut_amount > 0)
		{
			$this->session->set_flashdata(
				'attendance_notice_success',
				'Potongan gaji untuk '.$username.' berhasil diperbarui menjadi Rp '.number_format($salary_cut_amount, 0, ',', '.').'.'
			);
		}
		else
		{
			$this->session->set_flashdata(
				'attendance_notice_success',
				'Potongan gaji untuk '.$username.' berhasil dihapus (toleransi darurat).'
			);
		}

		redirect('home/employee_data');
	}

	public function submit_leave_request()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Sesi login sudah habis.'), 401);
			return;
		}

		if ((string) $this->session->userdata('absen_role') !== 'user')
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Akses ditolak.'), 403);
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Metode request tidak valid.'), 405);
			return;
		}

		$request_type = strtolower(trim((string) $this->input->post('request_type', TRUE)));
		$izin_type = strtolower(trim((string) $this->input->post('izin_type', TRUE)));
		$start_date = trim((string) $this->input->post('start_date', TRUE));
		$end_date = trim((string) $this->input->post('end_date', TRUE));
		$reason = trim((string) $this->input->post('reason', TRUE));

		if ($request_type !== 'cuti' && $request_type !== 'izin')
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Jenis pengajuan tidak valid.'), 422);
			return;
		}

		if ($request_type === 'izin')
		{
			if ($izin_type !== 'sakit' && $izin_type !== 'darurat')
			{
				$this->json_response(array('success' => FALSE, 'message' => 'Jenis izin wajib dipilih (sakit / darurat).'), 422);
				return;
			}
		}
		else
		{
			$izin_type = '';
		}

		if (!$this->is_valid_date_format($start_date) || !$this->is_valid_date_format($end_date))
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Format tanggal pengajuan tidak valid.'), 422);
			return;
		}

		$start_timestamp = strtotime($start_date.' 00:00:00');
		$end_timestamp = strtotime($end_date.' 00:00:00');
		if ($start_timestamp === FALSE || $end_timestamp === FALSE || $end_timestamp < $start_timestamp)
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Rentang tanggal pengajuan tidak valid.'), 422);
			return;
		}

		if ($reason === '')
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Alasan pengajuan wajib diisi.'), 422);
			return;
		}

		$support_file_required = $request_type === 'izin' && $izin_type === 'sakit';
		$support_file_upload = $this->upload_leave_support_file('support_file', $support_file_required);
		if ($support_file_upload['success'] !== TRUE)
		{
			$status_code = isset($support_file_upload['status_code']) ? (int) $support_file_upload['status_code'] : 422;
			$this->json_response(array('success' => FALSE, 'message' => (string) $support_file_upload['message']), $status_code);
			return;
		}

		$username = (string) $this->session->userdata('absen_username');
		$phone = trim((string) $this->session->userdata('absen_phone'));
		if ($phone === '')
		{
			$phone = $this->get_employee_phone($username);
		}
		$request_records = $this->load_leave_requests();
		$request_id = 'REQ-'.date('YmdHis').'-'.strtoupper(substr(md5(uniqid('', TRUE)), 0, 6));
		$duration_days = (int) floor(($end_timestamp - $start_timestamp) / 86400) + 1;
		$request_type_label = 'Cuti';
		$izin_type_label = '';
		if ($request_type === 'izin')
		{
			$izin_type_label = $izin_type === 'sakit' ? 'Izin Sakit' : 'Izin Darurat';
			$request_type_label = $izin_type_label;
		}

		$request_records[] = array(
			'id' => $request_id,
			'username' => $username,
			'phone' => $phone,
			'request_type' => $request_type,
			'request_type_label' => $request_type_label,
			'izin_type' => $izin_type,
			'izin_type_label' => $izin_type_label,
			'request_date' => date('Y-m-d'),
			'request_date_label' => date('d-m-Y'),
			'start_date' => $start_date,
			'start_date_label' => date('d-m-Y', $start_timestamp),
			'end_date' => $end_date,
			'end_date_label' => date('d-m-Y', $end_timestamp),
			'duration_days' => $duration_days,
			'reason' => $reason,
			'support_file_name' => isset($support_file_upload['file_name']) ? (string) $support_file_upload['file_name'] : '',
			'support_file_original_name' => isset($support_file_upload['original_name']) ? (string) $support_file_upload['original_name'] : '',
			'support_file_path' => isset($support_file_upload['relative_path']) ? (string) $support_file_upload['relative_path'] : '',
			'support_file_ext' => isset($support_file_upload['file_ext']) ? (string) $support_file_upload['file_ext'] : '',
			'status' => 'Menunggu',
			'status_note' => '',
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s')
		);

		$this->save_leave_requests($request_records);

		$request_type_message = $request_type === 'cuti'
			? 'cuti'
			: ($izin_type === 'sakit' ? 'izin sakit' : 'izin darurat');

		$this->json_response(array(
			'success' => TRUE,
			'message' => 'Pengajuan '.$request_type_message.' berhasil dikirim.'
		));
	}

	public function submit_loan_request()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Sesi login sudah habis.'), 401);
			return;
		}

		if ((string) $this->session->userdata('absen_role') !== 'user')
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Akses ditolak.'), 403);
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Metode request tidak valid.'), 405);
			return;
		}

		$amount_raw = trim((string) $this->input->post('amount', TRUE));
		$amount_digits = preg_replace('/\D+/', '', $amount_raw);
		$amount = $amount_digits === '' ? 0 : (int) $amount_digits;
		$tenor_months = (int) trim((string) $this->input->post('tenor_months', TRUE));
		$reason = trim((string) $this->input->post('reason', TRUE));

		if ($reason === '')
		{
			$this->json_response(array('success' => FALSE, 'message' => 'Alasan pinjaman wajib diisi.'), 422);
			return;
		}

		$username = (string) $this->session->userdata('absen_username');
		$phone = trim((string) $this->session->userdata('absen_phone'));
		if ($phone === '')
		{
			$phone = $this->get_employee_phone($username);
		}

		$loan_records = $this->load_loan_requests();
		$is_first_loan = $this->is_first_loan_request($username, $loan_records);
		$loan_calculation = $this->calculate_spaylater_loan($amount, $tenor_months, $is_first_loan);
		if (!isset($loan_calculation['success']) || $loan_calculation['success'] !== TRUE)
		{
			$message = isset($loan_calculation['message']) ? (string) $loan_calculation['message'] : 'Perhitungan pinjaman gagal diproses.';
			$this->json_response(array('success' => FALSE, 'message' => $message), 422);
			return;
		}
		$loan_result = isset($loan_calculation['data']) && is_array($loan_calculation['data'])
			? $loan_calculation['data']
			: array();
		$loan_transparency = $this->build_loan_detail_text($loan_result);

		$loan_id = 'LOAN-'.date('YmdHis').'-'.strtoupper(substr(md5(uniqid('', TRUE)), 0, 6));
		$loan_records[] = array(
			'id' => $loan_id,
			'username' => $username,
			'phone' => $phone,
			'request_date' => date('Y-m-d'),
			'request_date_label' => date('d-m-Y'),
			'amount' => $amount,
			'amount_label' => 'Rp '.number_format($amount, 0, ',', '.'),
			'tenor_months' => isset($loan_result['tenor_months']) ? (int) $loan_result['tenor_months'] : $tenor_months,
			'is_first_loan' => isset($loan_result['is_first_loan']) ? (bool) $loan_result['is_first_loan'] : $is_first_loan,
			'monthly_rate_percent' => isset($loan_result['monthly_rate_percent']) ? (float) $loan_result['monthly_rate_percent'] : 2.95,
			'monthly_interest_amount' => isset($loan_result['monthly_interest_amount']) ? (int) $loan_result['monthly_interest_amount'] : 0,
			'total_interest_amount' => isset($loan_result['total_interest_amount']) ? (int) $loan_result['total_interest_amount'] : 0,
			'interest_rate_percent' => isset($loan_result['monthly_rate_percent']) ? (float) $loan_result['monthly_rate_percent'] : 2.95,
			'interest_amount' => isset($loan_result['total_interest_amount']) ? (int) $loan_result['total_interest_amount'] : 0,
			'total_payment' => isset($loan_result['total_payment']) ? (int) $loan_result['total_payment'] : $amount,
			'monthly_installment_estimate' => isset($loan_result['monthly_installment_estimate']) ? (int) $loan_result['monthly_installment_estimate'] : 0,
			'installments' => isset($loan_result['installments']) && is_array($loan_result['installments']) ? $loan_result['installments'] : array(),
			'reason' => $reason,
			'transparency' => $loan_transparency,
			'status' => 'Menunggu',
			'status_note' => '',
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s')
		);

		$this->save_loan_requests($loan_records);

		$this->json_response(array(
			'success' => TRUE,
			'message' => 'Pengajuan pinjaman berhasil dikirim.',
			'loan_summary' => $loan_result
		));
	}

	public function update_leave_request_status()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			redirect('home');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home/leave_requests');
			return;
		}

		$request_id = trim((string) $this->input->post('request_id', TRUE));
		$next_status = strtolower(trim((string) $this->input->post('status', TRUE)));
		if ($request_id === '' || ($next_status !== 'diterima' && $next_status !== 'ditolak'))
		{
			$this->session->set_flashdata('leave_notice_error', 'Aksi status pengajuan tidak valid.');
			redirect('home/leave_requests');
			return;
		}

		$requests = $this->load_leave_requests();
		$target_index = -1;
		for ($i = 0; $i < count($requests); $i += 1)
		{
			if (isset($requests[$i]['id']) && (string) $requests[$i]['id'] === $request_id)
			{
				$target_index = $i;
				break;
			}
		}

		if ($target_index < 0)
		{
			$this->session->set_flashdata('leave_notice_error', 'Data pengajuan tidak ditemukan.');
			redirect('home/leave_requests');
			return;
		}

		$request_row = $requests[$target_index];
		$request_row['status'] = $next_status === 'diterima' ? 'Diterima' : 'Ditolak';
		$request_row['updated_at'] = date('Y-m-d H:i:s');
		$request_row['status_note'] = '';

		$phone = isset($request_row['phone']) ? trim((string) $request_row['phone']) : '';
		if ($phone === '')
		{
			$phone = $this->get_employee_phone(isset($request_row['username']) ? (string) $request_row['username'] : '');
			$request_row['phone'] = $phone;
		}

		$requests[$target_index] = $request_row;
		$this->save_leave_requests($requests);

		$whatsapp_message = $this->build_leave_status_whatsapp_message($request_row);
		$whatsapp_result = $this->send_whatsapp_notification($phone, $whatsapp_message);

		if ($whatsapp_result['success'])
		{
			$this->session->set_flashdata(
				'leave_notice_success',
				'Status pengajuan berhasil diubah menjadi '.$request_row['status'].' dan notifikasi WhatsApp sudah terkirim.'
			);
		}
		else
		{
			$reason = isset($whatsapp_result['message']) ? (string) $whatsapp_result['message'] : 'Pengiriman WhatsApp gagal.';
			$this->session->set_flashdata(
				'leave_notice_warning',
				'Status pengajuan berhasil diubah menjadi '.$request_row['status'].', tetapi notifikasi WhatsApp gagal dikirim. '.$reason
			);
		}

		redirect('home/leave_requests');
	}

	public function update_loan_request_status()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			redirect('home');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home/loan_requests');
			return;
		}

		$request_id = trim((string) $this->input->post('request_id', TRUE));
		$next_status = strtolower(trim((string) $this->input->post('status', TRUE)));
		if ($request_id === '' || ($next_status !== 'diterima' && $next_status !== 'ditolak'))
		{
			$this->session->set_flashdata('loan_notice_error', 'Aksi status pengajuan pinjaman tidak valid.');
			redirect('home/loan_requests');
			return;
		}

		$requests = $this->load_loan_requests();
		$target_index = -1;
		for ($i = 0; $i < count($requests); $i += 1)
		{
			if (isset($requests[$i]['id']) && (string) $requests[$i]['id'] === $request_id)
			{
				$target_index = $i;
				break;
			}
		}

		if ($target_index < 0)
		{
			$this->session->set_flashdata('loan_notice_error', 'Data pengajuan pinjaman tidak ditemukan.');
			redirect('home/loan_requests');
			return;
		}

		$request_row = $requests[$target_index];
		$request_row['status'] = $next_status === 'diterima' ? 'Diterima' : 'Ditolak';
		$request_row['updated_at'] = date('Y-m-d H:i:s');
		$request_row['status_note'] = '';

		$phone = isset($request_row['phone']) ? trim((string) $request_row['phone']) : '';
		if ($phone === '')
		{
			$phone = $this->get_employee_phone(isset($request_row['username']) ? (string) $request_row['username'] : '');
			$request_row['phone'] = $phone;
		}

		$requests[$target_index] = $request_row;
		$this->save_loan_requests($requests);

		$whatsapp_message = $this->build_loan_status_whatsapp_message($request_row);
		$whatsapp_result = $this->send_whatsapp_notification($phone, $whatsapp_message);

		if ($whatsapp_result['success'])
		{
			$this->session->set_flashdata(
				'loan_notice_success',
				'Status pengajuan pinjaman berhasil diubah menjadi '.$request_row['status'].' dan notifikasi WhatsApp sudah terkirim.'
			);
		}
		else
		{
			$reason = isset($whatsapp_result['message']) ? (string) $whatsapp_result['message'] : 'Pengiriman WhatsApp gagal.';
			$this->session->set_flashdata(
				'loan_notice_warning',
				'Status pengajuan pinjaman berhasil diubah menjadi '.$request_row['status'].', tetapi notifikasi WhatsApp gagal dikirim. '.$reason
			);
		}

		redirect('home/loan_requests');
	}

	public function leave_requests()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			redirect('home');
			return;
		}

		$requests = $this->load_leave_requests();
		$employee_id_book = $this->employee_id_book();
		$should_save_requests = FALSE;
		for ($i = 0; $i < count($requests); $i += 1)
		{
			$type_value = isset($requests[$i]['request_type']) ? strtolower(trim((string) $requests[$i]['request_type'])) : '';
			$existing_label_value = isset($requests[$i]['request_type_label']) ? strtolower(trim((string) $requests[$i]['request_type_label'])) : '';

			if (!isset($requests[$i]['izin_type']) || trim((string) $requests[$i]['izin_type']) === '')
			{
				if ($type_value === 'izin' && strpos($existing_label_value, 'sakit') !== FALSE)
				{
					$requests[$i]['izin_type'] = 'sakit';
				}
				elseif ($type_value === 'izin' && strpos($existing_label_value, 'darurat') !== FALSE)
				{
					$requests[$i]['izin_type'] = 'darurat';
				}
				else
				{
					$requests[$i]['izin_type'] = '';
				}
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['izin_type_label']) || trim((string) $requests[$i]['izin_type_label']) === '')
			{
				$izin_type_value = strtolower(trim((string) $requests[$i]['izin_type']));
				if ($izin_type_value === 'sakit')
				{
					$requests[$i]['izin_type_label'] = 'Izin Sakit';
				}
				elseif ($izin_type_value === 'darurat')
				{
					$requests[$i]['izin_type_label'] = 'Izin Darurat';
				}
				else
				{
					$requests[$i]['izin_type_label'] = '';
				}
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['id']) || trim((string) $requests[$i]['id']) === '')
			{
				$legacy_key = (isset($requests[$i]['username']) ? (string) $requests[$i]['username'] : '').
					'|'.(isset($requests[$i]['request_date']) ? (string) $requests[$i]['request_date'] : '').
					'|'.(isset($requests[$i]['start_date']) ? (string) $requests[$i]['start_date'] : '').
					'|'.(isset($requests[$i]['end_date']) ? (string) $requests[$i]['end_date'] : '').
					'|'.(isset($requests[$i]['created_at']) ? (string) $requests[$i]['created_at'] : '').
					'|'.$i;
				$requests[$i]['id'] = 'REQ-LEGACY-'.strtoupper(substr(md5($legacy_key), 0, 10));
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['request_type_label']) || $requests[$i]['request_type_label'] === '')
			{
				$izin_type_value = strtolower(trim((string) $requests[$i]['izin_type']));
				if ($type_value === 'cuti')
				{
					$requests[$i]['request_type_label'] = 'Cuti';
				}
				elseif ($izin_type_value === 'sakit')
				{
					$requests[$i]['request_type_label'] = 'Izin Sakit';
				}
				elseif ($izin_type_value === 'darurat')
				{
					$requests[$i]['request_type_label'] = 'Izin Darurat';
				}
				else
				{
					$requests[$i]['request_type_label'] = 'Izin';
				}
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['request_date_label']) || $requests[$i]['request_date_label'] === '')
			{
				$requests[$i]['request_date_label'] = isset($requests[$i]['request_date']) && $this->is_valid_date_format((string) $requests[$i]['request_date'])
					? date('d-m-Y', strtotime((string) $requests[$i]['request_date']))
					: '-';
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['start_date_label']) || $requests[$i]['start_date_label'] === '')
			{
				$requests[$i]['start_date_label'] = isset($requests[$i]['start_date']) && $this->is_valid_date_format((string) $requests[$i]['start_date'])
					? date('d-m-Y', strtotime((string) $requests[$i]['start_date']))
					: '-';
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['end_date_label']) || $requests[$i]['end_date_label'] === '')
			{
				$requests[$i]['end_date_label'] = isset($requests[$i]['end_date']) && $this->is_valid_date_format((string) $requests[$i]['end_date'])
					? date('d-m-Y', strtotime((string) $requests[$i]['end_date']))
					: '-';
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['duration_days']) || (int) $requests[$i]['duration_days'] <= 0)
			{
				if (isset($requests[$i]['start_date']) && isset($requests[$i]['end_date']) &&
					$this->is_valid_date_format((string) $requests[$i]['start_date']) &&
					$this->is_valid_date_format((string) $requests[$i]['end_date']))
				{
					$start_timestamp = strtotime((string) $requests[$i]['start_date'].' 00:00:00');
					$end_timestamp = strtotime((string) $requests[$i]['end_date'].' 00:00:00');
					if ($start_timestamp !== FALSE && $end_timestamp !== FALSE && $end_timestamp >= $start_timestamp)
					{
						$requests[$i]['duration_days'] = (int) floor(($end_timestamp - $start_timestamp) / 86400) + 1;
					}
					else
					{
						$requests[$i]['duration_days'] = 1;
					}
				}
				else
				{
					$requests[$i]['duration_days'] = 1;
				}
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['status']) || trim((string) $requests[$i]['status']) === '')
			{
				$requests[$i]['status'] = 'Menunggu';
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['reason']))
			{
				$requests[$i]['reason'] = '';
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['support_file_name']))
			{
				$requests[$i]['support_file_name'] = '';
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['support_file_original_name']))
			{
				$requests[$i]['support_file_original_name'] = '';
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['support_file_path']))
			{
				$requests[$i]['support_file_path'] = '';
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['support_file_ext']))
			{
				$requests[$i]['support_file_ext'] = '';
				$should_save_requests = TRUE;
			}

				if (!isset($requests[$i]['phone']) || trim((string) $requests[$i]['phone']) === '')
				{
					$username = isset($requests[$i]['username']) ? (string) $requests[$i]['username'] : '';
					$requests[$i]['phone'] = $this->get_employee_phone($username);
					$should_save_requests = TRUE;
				}

				$row_username = isset($requests[$i]['username']) ? (string) $requests[$i]['username'] : '';
				$requests[$i]['employee_id'] = $this->resolve_employee_id_from_book($row_username, $employee_id_book);
				$row_profile = $this->get_employee_profile($row_username);
				$requests[$i]['profile_photo'] = isset($row_profile['profile_photo']) && trim((string) $row_profile['profile_photo']) !== ''
					? (string) $row_profile['profile_photo']
					: $this->default_employee_profile_photo();
				$requests[$i]['job_title'] = isset($row_profile['job_title']) && trim((string) $row_profile['job_title']) !== ''
					? (string) $row_profile['job_title']
					: $this->default_employee_job_title();
			}

		if ($should_save_requests)
		{
			$this->save_leave_requests($requests);
		}

		usort($requests, function ($a, $b) {
			$left = isset($a['created_at']) ? (string) $a['created_at'] : '';
			$right = isset($b['created_at']) ? (string) $b['created_at'] : '';
			return strcmp($right, $left);
		});

		$data = array(
			'title' => 'Pengajuan Cuti / Izin',
			'requests' => $requests
		);
		$this->load->view('home/leave_requests', $data);
	}

	public function loan_requests()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			redirect('home');
			return;
		}

		$requests = $this->load_loan_requests();
		$employee_id_book = $this->employee_id_book();
		$should_save_requests = FALSE;
		$first_loan_tracker = array();
		for ($i = 0; $i < count($requests); $i += 1)
		{
			if (!isset($requests[$i]['id']) || trim((string) $requests[$i]['id']) === '')
			{
				$legacy_key = (isset($requests[$i]['username']) ? (string) $requests[$i]['username'] : '').
					'|'.(isset($requests[$i]['request_date']) ? (string) $requests[$i]['request_date'] : '').
					'|'.(isset($requests[$i]['created_at']) ? (string) $requests[$i]['created_at'] : '').
					'|'.$i;
				$requests[$i]['id'] = 'LOAN-LEGACY-'.strtoupper(substr(md5($legacy_key), 0, 10));
				$should_save_requests = TRUE;
			}

			$username = isset($requests[$i]['username']) ? trim((string) $requests[$i]['username']) : '';
			if ($username === '')
			{
				$requests[$i]['username'] = '-';
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['phone']) || trim((string) $requests[$i]['phone']) === '')
			{
				$requests[$i]['phone'] = $this->get_employee_phone($username);
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['request_date_label']) || trim((string) $requests[$i]['request_date_label']) === '')
			{
				$request_date = isset($requests[$i]['request_date']) ? (string) $requests[$i]['request_date'] : '';
				$requests[$i]['request_date_label'] = $this->is_valid_date_format($request_date)
					? date('d-m-Y', strtotime($request_date))
					: '-';
				$should_save_requests = TRUE;
			}

			$amount_digits = preg_replace('/\D+/', '', isset($requests[$i]['amount']) ? (string) $requests[$i]['amount'] : '');
			$amount_value = $amount_digits === '' ? 0 : (int) $amount_digits;
			if (!isset($requests[$i]['amount']) || (int) $requests[$i]['amount'] <= 0)
			{
				$requests[$i]['amount'] = $amount_value;
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['amount_label']) || trim((string) $requests[$i]['amount_label']) === '')
			{
				$requests[$i]['amount_label'] = 'Rp '.number_format((int) $requests[$i]['amount'], 0, ',', '.');
				$should_save_requests = TRUE;
			}

			$tenor_months_value = isset($requests[$i]['tenor_months']) ? (int) $requests[$i]['tenor_months'] : 0;
			if ($tenor_months_value < 1 || $tenor_months_value > 12)
			{
				$tenor_months_value = 1;
				$requests[$i]['tenor_months'] = $tenor_months_value;
				$should_save_requests = TRUE;
			}

			$username_key = strtolower(trim((string) $username));
			$is_first_for_account = !isset($first_loan_tracker[$username_key]);
			if (!isset($requests[$i]['is_first_loan']) || (bool) $requests[$i]['is_first_loan'] !== $is_first_for_account)
			{
				$requests[$i]['is_first_loan'] = $is_first_for_account;
				$should_save_requests = TRUE;
			}
			$first_loan_tracker[$username_key] = TRUE;

			$loan_summary_result = $this->calculate_spaylater_loan(
				(int) $requests[$i]['amount'],
				$tenor_months_value,
				$is_first_for_account
			);
			$loan_summary = isset($loan_summary_result['data']) && is_array($loan_summary_result['data'])
				? $loan_summary_result['data']
				: array();
			$next_monthly_rate_percent = isset($loan_summary['monthly_rate_percent']) ? (float) $loan_summary['monthly_rate_percent'] : ($is_first_for_account ? 0.0 : 2.95);
			$next_monthly_interest_amount = isset($loan_summary['monthly_interest_amount']) ? (int) $loan_summary['monthly_interest_amount'] : 0;
			$next_total_interest_amount = isset($loan_summary['total_interest_amount']) ? (int) $loan_summary['total_interest_amount'] : 0;
			$next_total_payment = isset($loan_summary['total_payment']) ? (int) $loan_summary['total_payment'] : (int) $requests[$i]['amount'];
			$next_monthly_installment_estimate = isset($loan_summary['monthly_installment_estimate'])
				? (int) $loan_summary['monthly_installment_estimate']
				: 0;
			$next_installments = isset($loan_summary['installments']) && is_array($loan_summary['installments'])
				? $loan_summary['installments']
				: array();

			if (!isset($requests[$i]['monthly_rate_percent']) || (float) $requests[$i]['monthly_rate_percent'] !== $next_monthly_rate_percent)
			{
				$requests[$i]['monthly_rate_percent'] = $next_monthly_rate_percent;
				$should_save_requests = TRUE;
			}
			if (!isset($requests[$i]['monthly_interest_amount']) || (int) $requests[$i]['monthly_interest_amount'] !== $next_monthly_interest_amount)
			{
				$requests[$i]['monthly_interest_amount'] = $next_monthly_interest_amount;
				$should_save_requests = TRUE;
			}
			if (!isset($requests[$i]['total_interest_amount']) || (int) $requests[$i]['total_interest_amount'] !== $next_total_interest_amount)
			{
				$requests[$i]['total_interest_amount'] = $next_total_interest_amount;
				$should_save_requests = TRUE;
			}
			if (!isset($requests[$i]['interest_rate_percent']) || (float) $requests[$i]['interest_rate_percent'] !== $next_monthly_rate_percent)
			{
				$requests[$i]['interest_rate_percent'] = $next_monthly_rate_percent;
				$should_save_requests = TRUE;
			}
			if (!isset($requests[$i]['interest_amount']) || (int) $requests[$i]['interest_amount'] !== $next_total_interest_amount)
			{
				$requests[$i]['interest_amount'] = $next_total_interest_amount;
				$should_save_requests = TRUE;
			}
			if (!isset($requests[$i]['total_payment']) || (int) $requests[$i]['total_payment'] !== $next_total_payment)
			{
				$requests[$i]['total_payment'] = $next_total_payment;
				$should_save_requests = TRUE;
			}
			if (!isset($requests[$i]['monthly_installment_estimate']) || (int) $requests[$i]['monthly_installment_estimate'] !== $next_monthly_installment_estimate)
			{
				$requests[$i]['monthly_installment_estimate'] = $next_monthly_installment_estimate;
				$should_save_requests = TRUE;
			}
			if (!isset($requests[$i]['installments']) || !is_array($requests[$i]['installments']) || json_encode($requests[$i]['installments']) !== json_encode($next_installments))
			{
				$requests[$i]['installments'] = $next_installments;
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['reason']))
			{
				$requests[$i]['reason'] = '';
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['transparency']) || trim((string) $requests[$i]['transparency']) === '')
			{
				$requests[$i]['transparency'] = $this->build_loan_detail_text($loan_summary);
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['status']) || trim((string) $requests[$i]['status']) === '')
			{
				$requests[$i]['status'] = 'Menunggu';
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['status_note']))
			{
				$requests[$i]['status_note'] = '';
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['created_at']) || trim((string) $requests[$i]['created_at']) === '')
			{
				$requests[$i]['created_at'] = date('Y-m-d H:i:s');
				$should_save_requests = TRUE;
			}

			if (!isset($requests[$i]['updated_at']) || trim((string) $requests[$i]['updated_at']) === '')
			{
				$requests[$i]['updated_at'] = (string) $requests[$i]['created_at'];
				$should_save_requests = TRUE;
			}

				$row_username = isset($requests[$i]['username']) ? (string) $requests[$i]['username'] : '';
				$requests[$i]['employee_id'] = $this->resolve_employee_id_from_book($row_username, $employee_id_book);
				$row_profile = $this->get_employee_profile($row_username);
				$requests[$i]['profile_photo'] = isset($row_profile['profile_photo']) && trim((string) $row_profile['profile_photo']) !== ''
					? (string) $row_profile['profile_photo']
					: $this->default_employee_profile_photo();
				$requests[$i]['job_title'] = isset($row_profile['job_title']) && trim((string) $row_profile['job_title']) !== ''
					? (string) $row_profile['job_title']
					: $this->default_employee_job_title();
			}

		if ($should_save_requests)
		{
			$this->save_loan_requests($requests);
		}

		usort($requests, function ($a, $b) {
			$left = isset($a['created_at']) ? (string) $a['created_at'] : '';
			$right = isset($b['created_at']) ? (string) $b['created_at'] : '';
			return strcmp($right, $left);
		});

		$data = array(
			'title' => 'Pengajuan Pinjaman',
			'requests' => $requests
		);
		$this->load->view('home/loan_requests', $data);
	}

	public function overtime_data()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			redirect('home');
			return;
		}

		$employee_names = $this->employee_username_list();
		$employee_id_book = $this->employee_id_book();
		$employee_options = array();
		for ($i = 0; $i < count($employee_names); $i += 1)
		{
			$employee_name = (string) $employee_names[$i];
			$employee_options[] = array(
				'username' => $employee_name,
				'employee_id' => $this->resolve_employee_id_from_book($employee_name, $employee_id_book)
			);
		}
		$records = $this->load_overtime_records();
		$should_save_records = FALSE;
		for ($i = 0; $i < count($records); $i += 1)
		{
			if (!isset($records[$i]['id']) || trim((string) $records[$i]['id']) === '')
			{
				$legacy_key = (isset($records[$i]['username']) ? (string) $records[$i]['username'] : '').
					'|'.(isset($records[$i]['overtime_date']) ? (string) $records[$i]['overtime_date'] : '').
					'|'.(isset($records[$i]['start_time']) ? (string) $records[$i]['start_time'] : '').
					'|'.(isset($records[$i]['end_time']) ? (string) $records[$i]['end_time'] : '').
					'|'.(isset($records[$i]['created_at']) ? (string) $records[$i]['created_at'] : '').
					'|'.$i;
				$records[$i]['id'] = 'OVT-LEGACY-'.strtoupper(substr(md5($legacy_key), 0, 10));
				$should_save_records = TRUE;
			}

			if (!isset($records[$i]['username']) || trim((string) $records[$i]['username']) === '')
			{
				$records[$i]['username'] = '-';
				$should_save_records = TRUE;
			}
			$row_username = isset($records[$i]['username']) ? (string) $records[$i]['username'] : '';
			$records[$i]['employee_id'] = $this->resolve_employee_id_from_book($row_username, $employee_id_book);
			$row_profile = $this->get_employee_profile($row_username);
			if (!isset($records[$i]['phone']) || trim((string) $records[$i]['phone']) === '')
			{
				$profile_phone = isset($row_profile['phone']) ? trim((string) $row_profile['phone']) : '';
				$records[$i]['phone'] = $profile_phone !== '' ? $profile_phone : $this->get_employee_phone($row_username);
				$should_save_records = TRUE;
			}
			if (!isset($records[$i]['profile_photo']) || trim((string) $records[$i]['profile_photo']) === '')
			{
				$records[$i]['profile_photo'] = isset($row_profile['profile_photo']) && trim((string) $row_profile['profile_photo']) !== ''
					? (string) $row_profile['profile_photo']
					: $this->default_employee_profile_photo();
				$should_save_records = TRUE;
			}
			if (!isset($records[$i]['job_title']) || trim((string) $records[$i]['job_title']) === '')
			{
				$records[$i]['job_title'] = isset($row_profile['job_title']) && trim((string) $row_profile['job_title']) !== ''
					? (string) $row_profile['job_title']
					: $this->default_employee_job_title();
				$should_save_records = TRUE;
			}

			if (!isset($records[$i]['overtime_date_label']) || trim((string) $records[$i]['overtime_date_label']) === '')
			{
				$overtime_date = isset($records[$i]['overtime_date']) ? (string) $records[$i]['overtime_date'] : '';
				$records[$i]['overtime_date_label'] = $this->is_valid_date_format($overtime_date)
					? date('d-m-Y', strtotime($overtime_date))
					: '-';
				$should_save_records = TRUE;
			}

			if (!isset($records[$i]['start_time']) || trim((string) $records[$i]['start_time']) === '')
			{
				$records[$i]['start_time'] = '-';
				$should_save_records = TRUE;
			}

			if (!isset($records[$i]['end_time']) || trim((string) $records[$i]['end_time']) === '')
			{
				$records[$i]['end_time'] = '-';
				$should_save_records = TRUE;
			}

			$nominal_digits = preg_replace('/\D+/', '', isset($records[$i]['nominal']) ? (string) $records[$i]['nominal'] : '');
			$nominal_value = $nominal_digits === '' ? 0 : (int) $nominal_digits;
			if (!isset($records[$i]['nominal']) || (int) $records[$i]['nominal'] <= 0)
			{
				$records[$i]['nominal'] = $nominal_value;
				$should_save_records = TRUE;
			}

			if (!isset($records[$i]['nominal_label']) || trim((string) $records[$i]['nominal_label']) === '')
			{
				$records[$i]['nominal_label'] = 'Rp '.number_format((int) $records[$i]['nominal'], 0, ',', '.');
				$should_save_records = TRUE;
			}

			if (!isset($records[$i]['reason']))
			{
				$records[$i]['reason'] = '';
				$should_save_records = TRUE;
			}

			if (!isset($records[$i]['created_at']) || trim((string) $records[$i]['created_at']) === '')
			{
				$records[$i]['created_at'] = date('Y-m-d H:i:s');
				$should_save_records = TRUE;
			}

			if (!isset($records[$i]['updated_at']) || trim((string) $records[$i]['updated_at']) === '')
			{
				$records[$i]['updated_at'] = (string) $records[$i]['created_at'];
				$should_save_records = TRUE;
			}
		}

		if ($should_save_records)
		{
			$this->save_overtime_records($records);
		}

		usort($records, function ($a, $b) {
			$left_date = isset($a['overtime_date']) ? (string) $a['overtime_date'] : '';
			$right_date = isset($b['overtime_date']) ? (string) $b['overtime_date'] : '';
			if ($left_date !== $right_date)
			{
				return strcmp($right_date, $left_date);
			}

			$left_created = isset($a['created_at']) ? (string) $a['created_at'] : '';
			$right_created = isset($b['created_at']) ? (string) $b['created_at'] : '';
			return strcmp($right_created, $left_created);
		});

		$data = array(
			'title' => 'Data Lembur',
			'employee_names' => $employee_names,
			'employee_options' => $employee_options,
			'records' => $records
		);
		$this->load->view('home/overtime_data', $data);
	}

	public function submit_overtime()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') === 'user')
		{
			redirect('home');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home/overtime_data');
			return;
		}

		$employee_name = strtolower(trim((string) $this->input->post('employee_name', TRUE)));
		$overtime_date = trim((string) $this->input->post('overtime_date', TRUE));
		$start_time = trim((string) $this->input->post('start_time', TRUE));
		$end_time = trim((string) $this->input->post('end_time', TRUE));
		$nominal_raw = trim((string) $this->input->post('nominal', TRUE));
		$nominal_digits = preg_replace('/\D+/', '', $nominal_raw);
		$nominal_value = $nominal_digits === '' ? 0 : (int) $nominal_digits;
		$reason = trim((string) $this->input->post('reason', TRUE));

		$employee_names = $this->employee_username_list();
		if ($employee_name === '' || !in_array($employee_name, $employee_names, TRUE))
		{
			$this->session->set_flashdata('overtime_notice_error', 'Nama karyawan lembur tidak valid.');
			redirect('home/overtime_data');
			return;
		}

		if (!$this->is_valid_date_format($overtime_date))
		{
			$this->session->set_flashdata('overtime_notice_error', 'Tanggal lembur tidak valid.');
			redirect('home/overtime_data');
			return;
		}

		if (!preg_match('/^\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}$/', $end_time))
		{
			$this->session->set_flashdata('overtime_notice_error', 'Jam lembur harus format HH:MM.');
			redirect('home/overtime_data');
			return;
		}

		$start_seconds = $this->time_to_seconds($start_time.':00');
		$end_seconds = $this->time_to_seconds($end_time.':00');
		if ($end_seconds <= $start_seconds)
		{
			$this->session->set_flashdata('overtime_notice_error', 'Jam selesai lembur harus lebih besar dari jam mulai.');
			redirect('home/overtime_data');
			return;
		}

		if ($nominal_value <= 0)
		{
			$this->session->set_flashdata('overtime_notice_error', 'Nominal lembur wajib diisi.');
			redirect('home/overtime_data');
			return;
		}

		if ($reason === '')
		{
			$this->session->set_flashdata('overtime_notice_error', 'Alasan lembur wajib diisi.');
			redirect('home/overtime_data');
			return;
		}

		$employee_profile = $this->get_employee_profile($employee_name);
		$employee_phone = isset($employee_profile['phone']) ? trim((string) $employee_profile['phone']) : '';
		if ($employee_phone === '')
		{
			$employee_phone = $this->get_employee_phone($employee_name);
		}
		$employee_profile_photo = isset($employee_profile['profile_photo']) && trim((string) $employee_profile['profile_photo']) !== ''
			? (string) $employee_profile['profile_photo']
			: $this->default_employee_profile_photo();
		$employee_job_title = isset($employee_profile['job_title']) && trim((string) $employee_profile['job_title']) !== ''
			? (string) $employee_profile['job_title']
			: $this->default_employee_job_title();

		$records = $this->load_overtime_records();
		$records[] = array(
			'id' => 'OVT-'.date('YmdHis').'-'.strtoupper(substr(md5(uniqid('', TRUE)), 0, 6)),
			'username' => $employee_name,
			'phone' => $employee_phone,
			'profile_photo' => $employee_profile_photo,
			'job_title' => $employee_job_title,
			'overtime_date' => $overtime_date,
			'overtime_date_label' => date('d-m-Y', strtotime($overtime_date)),
			'start_time' => $start_time,
			'end_time' => $end_time,
			'nominal' => $nominal_value,
			'nominal_label' => 'Rp '.number_format($nominal_value, 0, ',', '.'),
			'reason' => $reason,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s')
		);

		$this->save_overtime_records($records);
		$this->session->set_flashdata('overtime_notice_success', 'Data lembur berhasil disimpan.');
		redirect('home/overtime_data');
	}

	private function build_user_dashboard_snapshot($username, $shift_name = '', $shift_time = '')
	{
		$username = strtolower(trim((string) $username));
		$shift_name = trim((string) $shift_name);
		$shift_time = trim((string) $shift_time);
		if ($shift_name === '')
		{
			$shift_name = 'Shift Pagi - Sore';
		}
		if ($shift_time === '')
		{
			$shift_time = '08:00 - 17:00';
		}

		$today_key = date('Y-m-d');
		$current_year = (int) date('Y');
		$current_month = (int) date('n');
		$current_month_prefix = sprintf('%04d-%02d', $current_year, $current_month);
		$target_pulang = $this->extract_shift_target_time($shift_time);

		$summary = array(
			'status_hari_ini' => 'Siap Absen',
			'jam_masuk' => '-',
			'jam_pulang' => '-',
			'target_pulang' => $target_pulang,
			'total_hadir_bulan_ini' => 0,
			'total_terlambat_bulan_ini' => 0,
			'total_izin_bulan_ini' => 0
		);
		$recent_logs = array();
		$recent_loans = array();

		if ($username === '')
		{
			return array(
				'summary' => $summary,
				'recent_logs' => $recent_logs,
				'recent_loans' => $recent_loans
			);
		}

		$records = $this->load_attendance_records();
		$today_record = NULL;
		$hadir_dates = array();
		$late_dates = array();
		$all_logs = array();

		for ($i = 0; $i < count($records); $i += 1)
		{
			$row_username = isset($records[$i]['username']) ? strtolower(trim((string) $records[$i]['username'])) : '';
			if ($row_username !== $username)
			{
				continue;
			}

			$row_date = isset($records[$i]['date']) ? trim((string) $records[$i]['date']) : '';
			if ($row_date === '' || !$this->is_valid_date_format($row_date))
			{
				continue;
			}

			$row_check_in = isset($records[$i]['check_in_time']) ? trim((string) $records[$i]['check_in_time']) : '';
			$row_check_out = isset($records[$i]['check_out_time']) ? trim((string) $records[$i]['check_out_time']) : '';
			$row_shift_time = isset($records[$i]['shift_time']) ? trim((string) $records[$i]['shift_time']) : '';
			if ($row_shift_time === '')
			{
				$row_shift_time = $shift_time;
			}

			$late_duration = $row_check_in !== '' ? $this->calculate_late_duration($row_check_in, $row_shift_time) : '00:00:00';
			$late_seconds = $this->duration_to_seconds($late_duration);
			$is_late = $row_check_in !== '' && $late_seconds > 0;

			if (strpos($row_date, $current_month_prefix) === 0 && $row_check_in !== '')
			{
				$hadir_dates[$row_date] = TRUE;
				if ($is_late)
				{
					$late_dates[$row_date] = TRUE;
				}
			}

			if ($row_date === $today_key)
			{
				$today_record = $records[$i];
			}

			$log_status = '-';
			if ($row_check_in !== '')
			{
				$log_status = $is_late ? 'Terlambat' : 'Hadir';
			}

			$all_logs[] = array(
				'date' => $row_date,
				'sort_key' => $row_date.' '.($row_check_in !== '' ? $row_check_in : '00:00:00'),
				'masuk' => $this->format_user_dashboard_time_hhmm($row_check_in),
				'pulang' => $this->format_user_dashboard_time_hhmm($row_check_out),
				'status' => $log_status
			);
		}

		$approved_leave_days = $this->build_user_approved_leave_days_map($username, $current_year, $current_month);
		$summary['total_izin_bulan_ini'] = count($approved_leave_days);
		$summary['total_hadir_bulan_ini'] = count($hadir_dates);
		$summary['total_terlambat_bulan_ini'] = count($late_dates);

		if (is_array($today_record))
		{
			$today_in = isset($today_record['check_in_time']) ? trim((string) $today_record['check_in_time']) : '';
			$today_out = isset($today_record['check_out_time']) ? trim((string) $today_record['check_out_time']) : '';
			$summary['jam_masuk'] = $this->format_user_dashboard_time_hhmm($today_in);
			$summary['jam_pulang'] = $this->format_user_dashboard_time_hhmm($today_out);
			if ($today_in !== '' && $today_out !== '')
			{
				$summary['status_hari_ini'] = 'Sudah Check Out';
			}
			elseif ($today_in !== '')
			{
				$summary['status_hari_ini'] = 'Sudah Check In';
			}
		}
		else
		{
			$summary['jam_masuk'] = '-';
			$summary['jam_pulang'] = '-';
			if (isset($approved_leave_days[$today_key]))
			{
				$summary['status_hari_ini'] = $approved_leave_days[$today_key] === 'cuti' ? 'Cuti' : 'Izin';
			}
		}

		usort($all_logs, function ($a, $b) {
			$left = isset($a['sort_key']) ? (string) $a['sort_key'] : '';
			$right = isset($b['sort_key']) ? (string) $b['sort_key'] : '';
			return strcmp($right, $left);
		});

		$recent_logs_limit = 3;
		for ($i = 0; $i < count($all_logs) && $i < $recent_logs_limit; $i += 1)
		{
			$recent_logs[] = array(
				'tanggal' => $this->format_user_dashboard_date_label(isset($all_logs[$i]['date']) ? (string) $all_logs[$i]['date'] : ''),
				'masuk' => isset($all_logs[$i]['masuk']) ? (string) $all_logs[$i]['masuk'] : '-',
				'pulang' => isset($all_logs[$i]['pulang']) ? (string) $all_logs[$i]['pulang'] : '-',
				'status' => isset($all_logs[$i]['status']) ? (string) $all_logs[$i]['status'] : '-'
			);
		}

		$loan_rows = array();
		$loan_records = $this->load_loan_requests();
		for ($i = 0; $i < count($loan_records); $i += 1)
		{
			$row_username = isset($loan_records[$i]['username']) ? strtolower(trim((string) $loan_records[$i]['username'])) : '';
			if ($row_username !== $username)
			{
				continue;
			}

			$request_date = isset($loan_records[$i]['request_date']) ? trim((string) $loan_records[$i]['request_date']) : '';
			$request_date_label = isset($loan_records[$i]['request_date_label']) ? trim((string) $loan_records[$i]['request_date_label']) : '';
			if ($request_date_label === '' && $this->is_valid_date_format($request_date))
			{
				$request_date_label = date('d-m-Y', strtotime($request_date));
			}
			if ($request_date_label === '')
			{
				$request_date_label = '-';
			}

			$amount_digits = preg_replace('/\D+/', '', isset($loan_records[$i]['amount']) ? (string) $loan_records[$i]['amount'] : '');
			$amount_value = $amount_digits === '' ? 0 : (int) $amount_digits;
			$amount_label = $amount_value > 0 ? 'Rp '.number_format($amount_value, 0, ',', '.') : 'Rp 0';

			$tenor_months = isset($loan_records[$i]['tenor_months']) ? (int) $loan_records[$i]['tenor_months'] : 0;
			if ($tenor_months < 0)
			{
				$tenor_months = 0;
			}
			$tenor_label = $tenor_months > 0 ? $tenor_months.' bulan' : '-';

			$monthly_installment = isset($loan_records[$i]['monthly_installment_estimate'])
				? (int) $loan_records[$i]['monthly_installment_estimate']
				: 0;
			if ($monthly_installment <= 0 && $tenor_months > 0)
			{
				$total_payment = isset($loan_records[$i]['total_payment']) ? (int) $loan_records[$i]['total_payment'] : 0;
				if ($total_payment > 0)
				{
					$monthly_installment = (int) round($total_payment / $tenor_months);
				}
			}
			$monthly_installment_label = $monthly_installment > 0 ? 'Rp '.number_format($monthly_installment, 0, ',', '.') : 'Rp 0';

			$status_label = isset($loan_records[$i]['status']) ? trim((string) $loan_records[$i]['status']) : '';
			if ($status_label === '')
			{
				$status_label = 'Menunggu';
			}

			$sort_key = isset($loan_records[$i]['created_at']) ? trim((string) $loan_records[$i]['created_at']) : '';
			if ($sort_key === '' && $request_date !== '')
			{
				$sort_key = $request_date.' 00:00:00';
			}

			$loan_rows[] = array(
				'sort_key' => $sort_key,
				'tanggal' => $request_date_label,
				'nominal' => $amount_label,
				'tenor' => $tenor_label,
				'cicilan_bulanan' => $monthly_installment_label,
				'status' => $status_label
			);
		}

		usort($loan_rows, function ($a, $b) {
			$left = isset($a['sort_key']) ? (string) $a['sort_key'] : '';
			$right = isset($b['sort_key']) ? (string) $b['sort_key'] : '';
			return strcmp($right, $left);
		});

		$recent_loans_limit = 5;
		for ($i = 0; $i < count($loan_rows) && $i < $recent_loans_limit; $i += 1)
		{
			$recent_loans[] = array(
				'tanggal' => isset($loan_rows[$i]['tanggal']) ? (string) $loan_rows[$i]['tanggal'] : '-',
				'nominal' => isset($loan_rows[$i]['nominal']) ? (string) $loan_rows[$i]['nominal'] : 'Rp 0',
				'tenor' => isset($loan_rows[$i]['tenor']) ? (string) $loan_rows[$i]['tenor'] : '-',
				'cicilan_bulanan' => isset($loan_rows[$i]['cicilan_bulanan']) ? (string) $loan_rows[$i]['cicilan_bulanan'] : 'Rp 0',
				'status' => isset($loan_rows[$i]['status']) ? (string) $loan_rows[$i]['status'] : 'Menunggu'
			);
		}

		return array(
			'summary' => $summary,
			'recent_logs' => $recent_logs,
			'recent_loans' => $recent_loans
		);
	}

	private function build_user_approved_leave_days_map($username, $year, $month)
	{
		$username = strtolower(trim((string) $username));
		$year = (int) $year;
		$month = (int) $month;
		$days_map = array();
		if ($username === '' || $year <= 0 || $month < 1 || $month > 12)
		{
			return $days_map;
		}

		$requests = $this->load_leave_requests();
		for ($i = 0; $i < count($requests); $i += 1)
		{
			$row_username = isset($requests[$i]['username']) ? strtolower(trim((string) $requests[$i]['username'])) : '';
			if ($row_username !== $username)
			{
				continue;
			}

			$status_value = isset($requests[$i]['status']) ? strtolower(trim((string) $requests[$i]['status'])) : '';
			if ($status_value !== 'diterima')
			{
				continue;
			}

			$request_type = $this->resolve_leave_request_type($requests[$i]);
			if ($request_type !== 'cuti' && $request_type !== 'izin')
			{
				continue;
			}

			$start_date = isset($requests[$i]['start_date']) ? trim((string) $requests[$i]['start_date']) : '';
			$end_date = isset($requests[$i]['end_date']) ? trim((string) $requests[$i]['end_date']) : '';
			if (!$this->is_valid_date_format($start_date) || !$this->is_valid_date_format($end_date))
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

			for ($cursor = $start_ts; $cursor <= $end_ts; $cursor = strtotime('+1 day', $cursor))
			{
				$cursor_year = (int) date('Y', $cursor);
				$cursor_month = (int) date('n', $cursor);
				if ($cursor_year !== $year || $cursor_month !== $month)
				{
					continue;
				}

				$date_key = date('Y-m-d', $cursor);
				if (!isset($days_map[$date_key]) || $request_type === 'cuti')
				{
					$days_map[$date_key] = $request_type;
				}
			}
		}

		return $days_map;
	}

	private function resolve_leave_request_type($request_row)
	{
		$request_type = isset($request_row['request_type']) ? strtolower(trim((string) $request_row['request_type'])) : '';
		if ($request_type === 'cuti' || $request_type === 'izin')
		{
			return $request_type;
		}

		$type_label = isset($request_row['request_type_label']) ? strtolower(trim((string) $request_row['request_type_label'])) : '';
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

	private function format_user_dashboard_date_label($date_key)
	{
		$date_key = trim((string) $date_key);
		if (!$this->is_valid_date_format($date_key))
		{
			return $date_key !== '' ? $date_key : '-';
		}

		$timestamp = strtotime($date_key.' 00:00:00');
		if ($timestamp === FALSE)
		{
			return $date_key;
		}

		$day_names = array(
			'Minggu',
			'Senin',
			'Selasa',
			'Rabu',
			'Kamis',
			'Jumat',
			'Sabtu'
		);
		$month_names = array(
			1 => 'Januari',
			2 => 'Februari',
			3 => 'Maret',
			4 => 'April',
			5 => 'Mei',
			6 => 'Juni',
			7 => 'Juli',
			8 => 'Agustus',
			9 => 'September',
			10 => 'Oktober',
			11 => 'November',
			12 => 'Desember'
		);

		$day_name = $day_names[(int) date('w', $timestamp)];
		$day_number = (int) date('j', $timestamp);
		$month_number = (int) date('n', $timestamp);
		$year_number = (int) date('Y', $timestamp);
		$month_name = isset($month_names[$month_number]) ? $month_names[$month_number] : date('F', $timestamp);

		return $day_name.', '.$day_number.' '.$month_name.' '.$year_number;
	}

	private function format_user_dashboard_time_hhmm($time_value)
	{
		$time_value = trim((string) $time_value);
		if ($time_value === '')
		{
			return '-';
		}

		if (preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $time_value, $matches))
		{
			return $matches[1].':'.$matches[2];
		}

		return $time_value;
	}

	private function extract_shift_target_time($shift_time)
	{
		$shift_time = (string) $shift_time;
		$matches = array();
		preg_match_all('/(\d{2}:\d{2})/', $shift_time, $matches);
		if (isset($matches[1]) && is_array($matches[1]))
		{
			if (isset($matches[1][1]) && trim((string) $matches[1][1]) !== '')
			{
				return (string) $matches[1][1];
			}
			if (isset($matches[1][0]) && trim((string) $matches[1][0]) !== '')
			{
				return (string) $matches[1][0];
			}
		}

		return '17:00';
	}

	private function build_admin_dashboard_snapshot()
	{
		$metric_maps = $this->build_admin_metric_maps();
		$today_key = date('Y-m-d');
		$month_start_key = date('Y-m-01');
		$summary = array(
			'status_hari_ini' => 'Monitoring Hari Ini',
			'jam_masuk' => '-',
			'jam_pulang' => '-',
			'total_hadir_bulan_ini' => 0,
			'total_terlambat_bulan_ini' => 0,
			'total_izin_bulan_ini' => 0,
			'total_alpha_bulan_ini' => 0
		);

		$start_ts = strtotime($month_start_key.' 00:00:00');
		$end_ts = strtotime($today_key.' 00:00:00');
		if ($start_ts !== FALSE && $end_ts !== FALSE && $start_ts <= $end_ts)
		{
			for ($cursor = $start_ts; $cursor <= $end_ts; $cursor = strtotime('+1 day', $cursor))
			{
				$date_key = date('Y-m-d', $cursor);
				$day_counts = $this->build_admin_day_counts($date_key, $metric_maps);
				$summary['total_hadir_bulan_ini'] += isset($day_counts['hadir']) ? (int) $day_counts['hadir'] : 0;
				$summary['total_terlambat_bulan_ini'] += isset($day_counts['terlambat']) ? (int) $day_counts['terlambat'] : 0;
				$summary['total_izin_bulan_ini'] += isset($day_counts['izin_cuti']) ? (int) $day_counts['izin_cuti'] : 0;
				$summary['total_alpha_bulan_ini'] += isset($day_counts['alpha']) ? (int) $day_counts['alpha'] : 0;
			}
		}

		$today_checkins = isset($metric_maps['checkin_seconds_by_date'][$today_key]) && is_array($metric_maps['checkin_seconds_by_date'][$today_key])
			? $metric_maps['checkin_seconds_by_date'][$today_key]
			: array();
		if (!empty($today_checkins))
		{
			$min_seconds = min(array_values($today_checkins));
			$summary['jam_masuk'] = $this->format_user_dashboard_time_hhmm(gmdate('H:i:s', max(0, (int) $min_seconds)));
		}

		$today_checkouts = isset($metric_maps['checkout_seconds_by_date'][$today_key]) && is_array($metric_maps['checkout_seconds_by_date'][$today_key])
			? $metric_maps['checkout_seconds_by_date'][$today_key]
			: array();
		if (!empty($today_checkouts))
		{
			$max_seconds = max(array_values($today_checkouts));
			$summary['jam_pulang'] = $this->format_user_dashboard_time_hhmm(gmdate('H:i:s', max(0, (int) $max_seconds)));
		}

		if (empty($today_checkins))
		{
			$summary['status_hari_ini'] = 'Belum Ada Absen Masuk';
		}
		elseif ($summary['jam_pulang'] !== '-')
		{
			$summary['status_hari_ini'] = 'Monitoring Check Out';
		}

		$recent_rows = array();
		$records = $this->load_attendance_records();
		$employee_lookup = isset($metric_maps['employee_lookup']) && is_array($metric_maps['employee_lookup'])
			? $metric_maps['employee_lookup']
			: array();
		for ($i = 0; $i < count($records); $i += 1)
		{
			$username_key = isset($records[$i]['username']) ? strtolower(trim((string) $records[$i]['username'])) : '';
			if ($username_key === '' || !isset($employee_lookup[$username_key]))
			{
				continue;
			}

			$date_key = isset($records[$i]['date']) ? trim((string) $records[$i]['date']) : '';
			if (!$this->is_valid_date_format($date_key))
			{
				continue;
			}

			$check_in = isset($records[$i]['check_in_time']) ? trim((string) $records[$i]['check_in_time']) : '';
			$check_out = isset($records[$i]['check_out_time']) ? trim((string) $records[$i]['check_out_time']) : '';
			if ($check_in === '' && $check_out === '')
			{
				continue;
			}

			$late_duration = isset($records[$i]['check_in_late']) ? trim((string) $records[$i]['check_in_late']) : '';
			if ($late_duration === '' && $check_in !== '')
			{
				$row_shift_time = isset($records[$i]['shift_time']) ? (string) $records[$i]['shift_time'] : '';
				$late_duration = $this->calculate_late_duration($check_in, $row_shift_time);
			}
			$is_late = $this->duration_to_seconds($late_duration) > 0;
			$status_label = $check_in !== '' ? ($is_late ? 'Terlambat' : 'Hadir') : '-';
			$note = '-';
			if ($status_label === 'Hadir')
			{
				$note = $check_out !== '' ? 'Selesai check out' : 'On time';
			}
			elseif ($status_label === 'Terlambat')
			{
				$note = $late_duration !== '' && $late_duration !== '00:00:00'
					? 'Terlambat '.$late_duration
					: 'Terlambat';
			}

			$sort_key = isset($records[$i]['updated_at']) ? trim((string) $records[$i]['updated_at']) : '';
			if ($sort_key === '')
			{
				$sort_key = $date_key.' '.($check_in !== '' ? $check_in : '00:00:00');
			}

			$recent_rows[] = array(
				'sort_key' => $sort_key,
				'tanggal' => $this->format_user_dashboard_date_label($date_key),
				'masuk' => $this->format_user_dashboard_time_hhmm($check_in),
				'pulang' => $this->format_user_dashboard_time_hhmm($check_out),
				'status' => $status_label,
				'catatan' => $note
			);
		}

		usort($recent_rows, function ($a, $b) {
			$left = isset($a['sort_key']) ? (string) $a['sort_key'] : '';
			$right = isset($b['sort_key']) ? (string) $b['sort_key'] : '';
			return strcmp($right, $left);
		});

		$recent_logs = array();
		$recent_limit = 6;
		for ($i = 0; $i < count($recent_rows) && $i < $recent_limit; $i += 1)
		{
			$recent_logs[] = array(
				'tanggal' => isset($recent_rows[$i]['tanggal']) ? (string) $recent_rows[$i]['tanggal'] : '-',
				'masuk' => isset($recent_rows[$i]['masuk']) ? (string) $recent_rows[$i]['masuk'] : '-',
				'pulang' => isset($recent_rows[$i]['pulang']) ? (string) $recent_rows[$i]['pulang'] : '-',
				'status' => isset($recent_rows[$i]['status']) ? (string) $recent_rows[$i]['status'] : '-',
				'catatan' => isset($recent_rows[$i]['catatan']) ? (string) $recent_rows[$i]['catatan'] : '-'
			);
		}

		return array(
			'summary' => $summary,
			'recent_logs' => $recent_logs
		);
	}

	private function build_admin_metric_maps()
	{
		$employees = $this->employee_username_list();
		$employee_lookup = array();
		for ($i = 0; $i < count($employees); $i += 1)
		{
			$employee_lookup[(string) $employees[$i]] = TRUE;
		}

		$checkin_seconds_by_date = array();
		$checkout_seconds_by_date = array();
		$late_by_date = array();
		$today_key = date('Y-m-d');
		$min_date = $today_key;
		$max_date = $today_key;

		$records = $this->load_attendance_records();
		for ($i = 0; $i < count($records); $i += 1)
		{
			$username_key = isset($records[$i]['username']) ? strtolower(trim((string) $records[$i]['username'])) : '';
			if ($username_key === '' || !isset($employee_lookup[$username_key]))
			{
				continue;
			}

			$date_key = isset($records[$i]['date']) ? trim((string) $records[$i]['date']) : '';
			if (!$this->is_valid_date_format($date_key))
			{
				continue;
			}

			if ($date_key < $min_date)
			{
				$min_date = $date_key;
			}
			if ($date_key > $max_date)
			{
				$max_date = $date_key;
			}

			$check_in = isset($records[$i]['check_in_time']) ? trim((string) $records[$i]['check_in_time']) : '';
			if ($check_in !== '')
			{
				$check_in_seconds = max(0, (int) $this->time_to_seconds($check_in));
				if (!isset($checkin_seconds_by_date[$date_key]))
				{
					$checkin_seconds_by_date[$date_key] = array();
				}
				if (!isset($checkin_seconds_by_date[$date_key][$username_key]) || $check_in_seconds < (int) $checkin_seconds_by_date[$date_key][$username_key])
				{
					$checkin_seconds_by_date[$date_key][$username_key] = $check_in_seconds;
				}

				$late_duration = isset($records[$i]['check_in_late']) ? trim((string) $records[$i]['check_in_late']) : '';
				if ($late_duration === '')
				{
					$row_shift_time = isset($records[$i]['shift_time']) ? (string) $records[$i]['shift_time'] : '';
					$late_duration = $this->calculate_late_duration($check_in, $row_shift_time);
				}
				if ($this->duration_to_seconds($late_duration) > 0)
				{
					if (!isset($late_by_date[$date_key]))
					{
						$late_by_date[$date_key] = array();
					}
					$late_by_date[$date_key][$username_key] = TRUE;
				}
			}

			$check_out = isset($records[$i]['check_out_time']) ? trim((string) $records[$i]['check_out_time']) : '';
			if ($check_out !== '')
			{
				$check_out_seconds = max(0, (int) $this->time_to_seconds($check_out));
				if (!isset($checkout_seconds_by_date[$date_key]))
				{
					$checkout_seconds_by_date[$date_key] = array();
				}
				if (!isset($checkout_seconds_by_date[$date_key][$username_key]) || $check_out_seconds > (int) $checkout_seconds_by_date[$date_key][$username_key])
				{
					$checkout_seconds_by_date[$date_key][$username_key] = $check_out_seconds;
				}
			}
		}

		$leave_result = $this->build_admin_leave_map($employee_lookup);
		$leave_by_date = isset($leave_result['by_date']) && is_array($leave_result['by_date'])
			? $leave_result['by_date']
			: array();
		$leave_min_date = isset($leave_result['min_date']) ? trim((string) $leave_result['min_date']) : '';
		$leave_max_date = isset($leave_result['max_date']) ? trim((string) $leave_result['max_date']) : '';
		if ($leave_min_date !== '' && $leave_min_date < $min_date)
		{
			$min_date = $leave_min_date;
		}
		if ($leave_max_date !== '' && $leave_max_date > $max_date)
		{
			$max_date = $leave_max_date;
		}

		return array(
			'employees' => $employees,
			'employee_lookup' => $employee_lookup,
			'employee_count' => count($employees),
			'checkin_seconds_by_date' => $checkin_seconds_by_date,
			'checkout_seconds_by_date' => $checkout_seconds_by_date,
			'late_by_date' => $late_by_date,
			'leave_by_date' => $leave_by_date,
			'min_date' => $min_date,
			'max_date' => $max_date
		);
	}

	private function build_admin_leave_map($employee_lookup)
	{
		$by_date = array();
		$min_date = '';
		$max_date = '';
		$requests = $this->load_leave_requests();

		for ($i = 0; $i < count($requests); $i += 1)
		{
			$username_key = isset($requests[$i]['username']) ? strtolower(trim((string) $requests[$i]['username'])) : '';
			if ($username_key === '' || !isset($employee_lookup[$username_key]))
			{
				continue;
			}

			$status_value = isset($requests[$i]['status']) ? strtolower(trim((string) $requests[$i]['status'])) : '';
			if ($status_value !== 'diterima')
			{
				continue;
			}

			$request_type = $this->resolve_leave_request_type($requests[$i]);
			if ($request_type !== 'cuti' && $request_type !== 'izin')
			{
				continue;
			}

			$start_date = isset($requests[$i]['start_date']) ? trim((string) $requests[$i]['start_date']) : '';
			$end_date = isset($requests[$i]['end_date']) ? trim((string) $requests[$i]['end_date']) : '';
			if (!$this->is_valid_date_format($start_date) || !$this->is_valid_date_format($end_date))
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

			for ($cursor = $start_ts; $cursor <= $end_ts; $cursor = strtotime('+1 day', $cursor))
			{
				$date_key = date('Y-m-d', $cursor);
				if ($min_date === '' || $date_key < $min_date)
				{
					$min_date = $date_key;
				}
				if ($max_date === '' || $date_key > $max_date)
				{
					$max_date = $date_key;
				}

				if (!isset($by_date[$date_key]))
				{
					$by_date[$date_key] = array();
				}

				if (!isset($by_date[$date_key][$username_key]) || $request_type === 'cuti')
				{
					$by_date[$date_key][$username_key] = $request_type;
				}
			}
		}

		return array(
			'by_date' => $by_date,
			'min_date' => $min_date,
			'max_date' => $max_date
		);
	}

	private function build_admin_day_counts($date_key, $metric_maps, $hour_cutoff_seconds = NULL)
	{
		$date_key = trim((string) $date_key);
		if (!$this->is_valid_date_format($date_key))
		{
			return array(
				'hadir' => 0,
				'terlambat' => 0,
				'izin_cuti' => 0,
				'alpha' => 0
			);
		}

		$hour_cutoff = NULL;
		if ($hour_cutoff_seconds !== NULL)
		{
			$hour_cutoff = max(0, min(86399, (int) $hour_cutoff_seconds));
		}

		$day_checkins = isset($metric_maps['checkin_seconds_by_date'][$date_key]) && is_array($metric_maps['checkin_seconds_by_date'][$date_key])
			? $metric_maps['checkin_seconds_by_date'][$date_key]
			: array();
		$day_late_users = isset($metric_maps['late_by_date'][$date_key]) && is_array($metric_maps['late_by_date'][$date_key])
			? $metric_maps['late_by_date'][$date_key]
			: array();
		$day_leave_users = isset($metric_maps['leave_by_date'][$date_key]) && is_array($metric_maps['leave_by_date'][$date_key])
			? $metric_maps['leave_by_date'][$date_key]
			: array();

		$hadir = 0;
		foreach ($day_checkins as $username => $seconds)
		{
			$seconds_value = max(0, (int) $seconds);
			if ($hour_cutoff === NULL || $seconds_value <= $hour_cutoff)
			{
				$hadir += 1;
			}
		}

		$terlambat = 0;
		foreach ($day_late_users as $username => $is_late)
		{
			if (!$is_late || !isset($day_checkins[$username]))
			{
				continue;
			}
			$seconds_value = max(0, (int) $day_checkins[$username]);
			if ($hour_cutoff === NULL || $seconds_value <= $hour_cutoff)
			{
				$terlambat += 1;
			}
		}

		$izin_cuti = count($day_leave_users);
		$weekly_day_off = $this->normalize_weekly_day_off(self::WEEKLY_HOLIDAY_DAY);
		$weekday_n = (int) date('N', strtotime($date_key.' 00:00:00'));
		$is_workday = $weekday_n !== $weekly_day_off;
		$employee_count = isset($metric_maps['employee_count']) ? max(0, (int) $metric_maps['employee_count']) : 0;
		$target_headcount = $is_workday ? $employee_count : 0;
		$alpha = max(0, $target_headcount - $hadir - $izin_cuti);

		return array(
			'hadir' => (int) $hadir,
			'terlambat' => (int) $terlambat,
			'izin_cuti' => (int) $izin_cuti,
			'alpha' => (int) $alpha
		);
	}

	private function build_admin_metric_chart_payload($metric, $range)
	{
		$metric_key = strtolower(trim((string) $metric));
		if ($metric_key === 'izin')
		{
			$metric_key = 'izin_cuti';
		}
		$metric_key = str_replace('-', '_', $metric_key);
		$allowed_metrics = array('hadir', 'terlambat', 'izin_cuti', 'alpha');
		if (!in_array($metric_key, $allowed_metrics, TRUE))
		{
			return array(
				'success' => FALSE,
				'status_code' => 422,
				'message' => 'Jenis metrik grafik tidak valid.'
			);
		}

		$range_key = strtoupper(trim((string) $range));
		if ($range_key === '')
		{
			$range_key = '1B';
		}
		$allowed_ranges = array('1H', '1M', '1B', '1T', 'ALL');
		if (!in_array($range_key, $allowed_ranges, TRUE))
		{
			$range_key = '1B';
		}

		$metric_labels = array(
			'hadir' => 'Total Hadir',
			'terlambat' => 'Total Terlambat',
			'izin_cuti' => 'Total Izin/Cuti',
			'alpha' => 'Total Alpha'
		);
		$range_labels = array(
			'1H' => '1 Hari',
			'1M' => '1 Minggu',
			'1B' => '1 Bulan',
			'1T' => '1 Tahun',
			'ALL' => 'Semuanya'
		);

		$metric_maps = $this->build_admin_metric_maps();
		$points = array();
		$now_ts = time();
		$today_midnight_ts = strtotime(date('Y-m-d 00:00:00', $now_ts));

		if ($range_key === '1H')
		{
			$start_hour_ts = strtotime(date('Y-m-d H:00:00', $now_ts)) - (23 * 3600);
			for ($i = 0; $i < 24; $i += 1)
			{
				$slot_ts = $start_hour_ts + ($i * 3600);
				$date_key = date('Y-m-d', $slot_ts);
				$hour_value = (int) date('H', $slot_ts);
				$hour_cutoff = ($hour_value * 3600) + 3599;
				$counts = $this->build_admin_day_counts($date_key, $metric_maps, $hour_cutoff);
				$points[] = array(
					'ts' => date('c', $slot_ts),
					'label' => date('d M H:00', $slot_ts),
					'value' => isset($counts[$metric_key]) ? (int) $counts[$metric_key] : 0
				);
			}
		}
		elseif ($range_key === '1M')
		{
			$start_day_ts = $today_midnight_ts - (6 * 86400);
			for ($i = 0; $i < 7; $i += 1)
			{
				$slot_ts = $start_day_ts + ($i * 86400);
				$date_key = date('Y-m-d', $slot_ts);
				$counts = $this->build_admin_day_counts($date_key, $metric_maps);
				$points[] = array(
					'ts' => date('c', $slot_ts),
					'label' => date('d M', $slot_ts),
					'value' => isset($counts[$metric_key]) ? (int) $counts[$metric_key] : 0
				);
			}
		}
		elseif ($range_key === '1B')
		{
			$start_month_ts = strtotime(date('Y-m-01 00:00:00', $now_ts));
			if ($start_month_ts !== FALSE)
			{
				for ($slot_ts = $start_month_ts; $slot_ts <= $today_midnight_ts; $slot_ts = strtotime('+1 day', $slot_ts))
				{
					$date_key = date('Y-m-d', $slot_ts);
					$counts = $this->build_admin_day_counts($date_key, $metric_maps);
					$points[] = array(
						'ts' => date('c', $slot_ts),
						'label' => date('d M', $slot_ts),
						'value' => isset($counts[$metric_key]) ? (int) $counts[$metric_key] : 0
					);
				}
			}
		}
		else
		{
			$start_month_ts = $range_key === '1T'
				? strtotime(date('Y-m-01 00:00:00', $now_ts).' -11 month')
				: strtotime(substr((string) (isset($metric_maps['min_date']) ? $metric_maps['min_date'] : date('Y-m-d')), 0, 7).'-01 00:00:00');
			$end_month_ts = strtotime(date('Y-m-01 00:00:00', $now_ts));
			if ($start_month_ts === FALSE || $end_month_ts === FALSE || $start_month_ts > $end_month_ts)
			{
				$start_month_ts = $end_month_ts !== FALSE ? $end_month_ts : $today_midnight_ts;
			}

			for ($month_ts = $start_month_ts; $month_ts <= $end_month_ts; $month_ts = strtotime('+1 month', $month_ts))
			{
				$year_value = (int) date('Y', $month_ts);
				$month_value = (int) date('n', $month_ts);
				$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month_value, $year_value);
				$month_total = 0;
				for ($day = 1; $day <= $days_in_month; $day += 1)
				{
					$day_ts = strtotime(sprintf('%04d-%02d-%02d 00:00:00', $year_value, $month_value, $day));
					if ($day_ts === FALSE || $day_ts > $today_midnight_ts)
					{
						continue;
					}
					$date_key = date('Y-m-d', $day_ts);
					$counts = $this->build_admin_day_counts($date_key, $metric_maps);
					$month_total += isset($counts[$metric_key]) ? (int) $counts[$metric_key] : 0;
				}

				$points[] = array(
					'ts' => date('c', $month_ts),
					'label' => date('M Y', $month_ts),
					'value' => (int) $month_total
				);
			}
		}

		if (empty($points))
		{
			$points[] = array(
				'ts' => date('c', $now_ts),
				'label' => date('d M H:i', $now_ts),
				'value' => 0
			);
		}

		$last_index = count($points) - 1;
		$last_value = isset($points[$last_index]['value']) ? (int) $points[$last_index]['value'] : 0;
		$prev_value = $last_index > 0 && isset($points[$last_index - 1]['value'])
			? (int) $points[$last_index - 1]['value']
			: $last_value;
		$change_value = $last_value - $prev_value;
		$trend = 'flat';
		if ($change_value > 0)
		{
			$trend = 'up';
		}
		elseif ($change_value < 0)
		{
			$trend = 'down';
		}

		return array(
			'metric' => $metric_key,
			'metric_label' => isset($metric_labels[$metric_key]) ? (string) $metric_labels[$metric_key] : 'Metrik',
			'range' => $range_key,
			'range_label' => isset($range_labels[$range_key]) ? (string) $range_labels[$range_key] : $range_key,
			'points' => $points,
			'last_value' => $last_value,
			'prev_value' => $prev_value,
			'change_value' => $change_value,
			'trend' => $trend,
			'generated_at' => date('Y-m-d H:i:s')
		);
	}

	private function attendance_file_path()
	{
		return APPPATH.'cache/attendance_records.json';
	}

	private function load_attendance_records()
	{
		$file_path = $this->attendance_file_path();
		if (!is_file($file_path))
		{
			return array();
		}

		$content = @file_get_contents($file_path);
		if ($content === FALSE || trim($content) === '')
		{
			return array();
		}

		// Handle UTF-8 BOM that can make json_decode fail on Windows-generated files.
		if (substr($content, 0, 3) === "\xEF\xBB\xBF")
		{
			$content = substr($content, 3);
		}

		$data = json_decode($content, TRUE);
		if (!is_array($data))
		{
			return array();
		}

		return $data;
	}

	private function save_attendance_records($records)
	{
		$file_path = $this->attendance_file_path();
		$directory = dirname($file_path);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0755, TRUE);
		}
		$payload = json_encode(array_values($records), JSON_PRETTY_PRINT);
		@file_put_contents($file_path, $payload);
	}

	private function leave_requests_file_path()
	{
		return APPPATH.'cache/leave_requests.json';
	}

	private function load_leave_requests()
	{
		$file_path = $this->leave_requests_file_path();
		if (!is_file($file_path))
		{
			return array();
		}

		$content = @file_get_contents($file_path);
		if ($content === FALSE || trim($content) === '')
		{
			return array();
		}

		// Handle UTF-8 BOM that can make json_decode fail on Windows-generated files.
		if (substr($content, 0, 3) === "\xEF\xBB\xBF")
		{
			$content = substr($content, 3);
		}

		$data = json_decode($content, TRUE);
		if (!is_array($data))
		{
			return array();
		}

		return $data;
	}

	private function save_leave_requests($requests)
	{
		$file_path = $this->leave_requests_file_path();
		$directory = dirname($file_path);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0755, TRUE);
		}
		$payload = json_encode(array_values($requests), JSON_PRETTY_PRINT);
		@file_put_contents($file_path, $payload);
	}

	private function loan_requests_file_path()
	{
		return APPPATH.'cache/loan_requests.json';
	}

	private function load_loan_requests()
	{
		$file_path = $this->loan_requests_file_path();
		if (!is_file($file_path))
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

		$data = json_decode($content, TRUE);
		if (!is_array($data))
		{
			return array();
		}

		return $data;
	}

	private function save_loan_requests($requests)
	{
		$file_path = $this->loan_requests_file_path();
		$directory = dirname($file_path);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0755, TRUE);
		}
		$payload = json_encode(array_values($requests), JSON_PRETTY_PRINT);
		@file_put_contents($file_path, $payload);
	}

	private function is_first_loan_request($username, $loan_records = NULL)
	{
		$username_key = strtolower(trim((string) $username));
		if ($username_key === '')
		{
			return TRUE;
		}

		$records = is_array($loan_records) ? $loan_records : $this->load_loan_requests();
		for ($i = 0; $i < count($records); $i += 1)
		{
			$row_username = isset($records[$i]['username']) ? strtolower(trim((string) $records[$i]['username'])) : '';
			if ($row_username === $username_key)
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	private function calculate_spaylater_loan($principal, $tenor_months, $is_first_loan = FALSE)
	{
		$principal_value = (int) $principal;
		$tenor_value = (int) $tenor_months;
		$is_first_loan_value = (bool) $is_first_loan;

		if ($principal_value < 500000 || $principal_value > 10000000)
		{
			return array(
				'success' => FALSE,
				'message' => 'Nominal pinjaman harus antara Rp 500.000 sampai Rp 10.000.000.'
			);
		}

		if ($tenor_value < 1 || $tenor_value > 12)
		{
			return array(
				'success' => FALSE,
				'message' => 'Tenor pinjaman harus antara 1 sampai 12 bulan.'
			);
		}

		$monthly_rate_percent = $is_first_loan_value ? 0.0 : 2.95;
		$monthly_interest_amount = (int) round($principal_value * $monthly_rate_percent / 100);
		$total_interest_amount = (int) ($monthly_interest_amount * $tenor_value);
		$total_payment = (int) ($principal_value + $total_interest_amount);
		$monthly_installment_estimate = (int) round($total_payment / $tenor_value);

		$base_installment = (int) floor($total_payment / $tenor_value);
		$remainder = (int) ($total_payment - ($base_installment * $tenor_value));
		$installments = array();
		for ($month = 1; $month <= $tenor_value; $month += 1)
		{
			$amount = $base_installment;
			if ($month === $tenor_value && $remainder > 0)
			{
				$amount += $remainder;
			}
			$installments[] = array(
				'month' => $month,
				'amount' => (int) $amount
			);
		}

		return array(
			'success' => TRUE,
			'data' => array(
				'principal' => $principal_value,
				'tenor_months' => $tenor_value,
				'is_first_loan' => $is_first_loan_value,
				'monthly_rate_percent' => (float) $monthly_rate_percent,
				'monthly_interest_amount' => $monthly_interest_amount,
				'total_interest_amount' => $total_interest_amount,
				'interest_rate_percent' => (float) $monthly_rate_percent,
				'interest_amount' => $total_interest_amount,
				'total_payment' => $total_payment,
				'monthly_installment_estimate' => $monthly_installment_estimate,
				'installments' => $installments
			)
		);
	}

	/**
	 * Backward-compatible wrapper for legacy call sites.
	 */
	private function calculate_progressive_flat_loan($principal, $tenor_months, $is_first_loan)
	{
		return $this->calculate_spaylater_loan($principal, $tenor_months, $is_first_loan);
	}

	/**
	 * Backward-compatible wrapper.
	 */
	private function calculate_flat_loan($principal, $tenor_months, $is_first_loan)
	{
		return $this->calculate_spaylater_loan($principal, $tenor_months, $is_first_loan);
	}

	private function build_loan_detail_text($loan_data)
	{
		if (!is_array($loan_data))
		{
			return '-';
		}

		$principal = isset($loan_data['principal']) ? (int) $loan_data['principal'] : 0;
		$tenor_months = isset($loan_data['tenor_months']) ? (int) $loan_data['tenor_months'] : 0;
		$is_first_loan = isset($loan_data['is_first_loan']) ? (bool) $loan_data['is_first_loan'] : FALSE;
		$monthly_rate_percent = isset($loan_data['monthly_rate_percent'])
			? (float) $loan_data['monthly_rate_percent']
			: (isset($loan_data['interest_rate_percent']) ? (float) $loan_data['interest_rate_percent'] : ($is_first_loan ? 0.0 : 2.95));
		$monthly_interest_amount = isset($loan_data['monthly_interest_amount'])
			? (int) $loan_data['monthly_interest_amount']
			: ($principal > 0 ? (int) round($principal * $monthly_rate_percent / 100) : 0);
		$total_interest_amount = isset($loan_data['total_interest_amount'])
			? (int) $loan_data['total_interest_amount']
			: (isset($loan_data['interest_amount']) ? (int) $loan_data['interest_amount'] : ($monthly_interest_amount * max(1, $tenor_months)));
		$total_payment = isset($loan_data['total_payment']) ? (int) $loan_data['total_payment'] : 0;
		$monthly_installment_estimate = isset($loan_data['monthly_installment_estimate'])
			? (int) $loan_data['monthly_installment_estimate']
			: ($tenor_months > 0 ? (int) round($total_payment / $tenor_months) : 0);
		$installments = isset($loan_data['installments']) && is_array($loan_data['installments'])
			? $loan_data['installments']
			: array();

		$lines = array();
		$lines[] = 'Nominal pinjaman: Rp '.number_format($principal, 0, ',', '.');
		$lines[] = 'Tenor: '.$tenor_months.' bulan';
		$lines[] = 'Status: '.($is_first_loan ? 'Pinjaman pertama akun (0% bunga)' : 'Pinjaman lanjutan akun (bunga berlaku)');
		$lines[] = 'Bunga per bulan: '.number_format($monthly_rate_percent, 2, ',', '.').'%';
		$lines[] = 'Bunga per bulan (rupiah): Rp '.number_format($monthly_interest_amount, 0, ',', '.');
		$lines[] = 'Total bunga: Rp '.number_format($total_interest_amount, 0, ',', '.');
		$lines[] = 'Total bayar: Rp '.number_format($total_payment, 0, ',', '.');
		$lines[] = 'Estimasi cicilan per bulan: Rp '.number_format($monthly_installment_estimate, 0, ',', '.');
		$lines[] = 'Rincian cicilan:';
		for ($i = 0; $i < count($installments); $i += 1)
		{
			$month = isset($installments[$i]['month']) ? (int) $installments[$i]['month'] : ($i + 1);
			$amount = isset($installments[$i]['amount']) ? (int) $installments[$i]['amount'] : 0;
			$lines[] = '- Bulan '.$month.': Rp '.number_format($amount, 0, ',', '.');
		}

		return implode("\n", $lines);
	}

	private function overtime_file_path()
	{
		return APPPATH.'cache/overtime_records.json';
	}

	private function load_overtime_records()
	{
		$file_path = $this->overtime_file_path();
		if (!is_file($file_path))
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

		$data = json_decode($content, TRUE);
		if (!is_array($data))
		{
			return array();
		}

		return $data;
	}

	private function save_overtime_records($records)
	{
		$file_path = $this->overtime_file_path();
		$directory = dirname($file_path);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0755, TRUE);
		}
		$payload = json_encode(array_values($records), JSON_PRETTY_PRINT);
		@file_put_contents($file_path, $payload);
	}

	private function build_employee_account_options()
	{
		$profiles = $this->employee_profile_book();
		$id_book = $this->employee_id_book();
		$options = array();

		foreach ($profiles as $username_key => $profile)
		{
			$username = strtolower(trim((string) $username_key));
			if ($username === '')
			{
				continue;
			}

			$job_title = $this->resolve_employee_job_title(isset($profile['job_title']) ? (string) $profile['job_title'] : '');
			if ($job_title === '')
			{
				$job_title = $this->default_employee_job_title();
			}

			$options[] = array(
				'username' => $username,
				'employee_id' => $this->resolve_employee_id_from_book($username, $id_book),
				'branch' => isset($profile['branch']) ? (string) $profile['branch'] : $this->default_employee_branch(),
				'phone' => isset($profile['phone']) ? (string) $profile['phone'] : '',
				'shift_name' => isset($profile['shift_name']) ? (string) $profile['shift_name'] : '',
				'shift_key' => $this->resolve_shift_key_from_profile($profile),
				'salary_tier' => isset($profile['salary_tier']) ? (string) $profile['salary_tier'] : '',
				'salary_monthly' => isset($profile['salary_monthly']) ? (int) $profile['salary_monthly'] : 0,
				'job_title' => $job_title,
				'address' => isset($profile['address']) ? (string) $profile['address'] : $this->default_employee_address(),
				'work_days' => isset($profile['work_days']) ? (int) $profile['work_days'] : 28
			);
		}

		usort($options, function ($a, $b) {
			return strcmp(
				strtolower((string) (isset($a['username']) ? $a['username'] : '')),
				strtolower((string) (isset($b['username']) ? $b['username'] : ''))
			);
		});

		return $options;
	}

	private function resolve_shift_key_from_profile($profile)
	{
		$shift_name = isset($profile['shift_name']) ? strtolower(trim((string) $profile['shift_name'])) : '';
		$shift_time = isset($profile['shift_time']) ? strtolower(trim((string) $profile['shift_time'])) : '';
		if (strpos($shift_name, 'siang') !== FALSE || strpos($shift_time, '12:00') !== FALSE)
		{
			return 'siang';
		}

		return 'pagi';
	}

	private function rename_username_in_rows($rows, $old_username, $new_username)
	{
		$old_key = strtolower(trim((string) $old_username));
		$new_key = strtolower(trim((string) $new_username));
		$updated = 0;

		if (!is_array($rows))
		{
			return array(array(), $updated);
		}

		for ($i = 0; $i < count($rows); $i += 1)
		{
			if (!is_array($rows[$i]))
			{
				continue;
			}
			$row_username = isset($rows[$i]['username']) ? strtolower(trim((string) $rows[$i]['username'])) : '';
			if ($row_username !== $old_key)
			{
				continue;
			}
			$rows[$i]['username'] = $new_key;
			$updated += 1;
		}

		return array($rows, $updated);
	}

	private function rename_employee_related_records($old_username, $new_username)
	{
		$old_key = strtolower(trim((string) $old_username));
		$new_key = strtolower(trim((string) $new_username));
		$result = array(
			'attendance' => 0,
			'leave' => 0,
			'loan' => 0,
			'overtime' => 0
		);

		if ($old_key === '' || $new_key === '' || $old_key === $new_key)
		{
			return $result;
		}

		$attendance_rows = $this->load_attendance_records();
		$attendance_renamed = $this->rename_username_in_rows($attendance_rows, $old_key, $new_key);
		$result['attendance'] = isset($attendance_renamed[1]) ? (int) $attendance_renamed[1] : 0;
		if ($result['attendance'] > 0)
		{
			$this->save_attendance_records($attendance_renamed[0]);
		}

		$leave_rows = $this->load_leave_requests();
		$leave_renamed = $this->rename_username_in_rows($leave_rows, $old_key, $new_key);
		$result['leave'] = isset($leave_renamed[1]) ? (int) $leave_renamed[1] : 0;
		if ($result['leave'] > 0)
		{
			$this->save_leave_requests($leave_renamed[0]);
		}

		$loan_rows = $this->load_loan_requests();
		$loan_renamed = $this->rename_username_in_rows($loan_rows, $old_key, $new_key);
		$result['loan'] = isset($loan_renamed[1]) ? (int) $loan_renamed[1] : 0;
		if ($result['loan'] > 0)
		{
			$this->save_loan_requests($loan_renamed[0]);
		}

		$overtime_rows = $this->load_overtime_records();
		$overtime_renamed = $this->rename_username_in_rows($overtime_rows, $old_key, $new_key);
		$result['overtime'] = isset($overtime_renamed[1]) ? (int) $overtime_renamed[1] : 0;
		if ($result['overtime'] > 0)
		{
			$this->save_overtime_records($overtime_renamed[0]);
		}

		return $result;
	}

	private function purge_employee_related_records($username)
	{
		$username_key = strtolower(trim((string) $username));
		if ($username_key === '')
		{
			return array(
				'attendance' => 0,
				'leave' => 0,
				'loan' => 0,
				'overtime' => 0
			);
		}

		$result = array(
			'attendance' => 0,
			'leave' => 0,
			'loan' => 0,
			'overtime' => 0
		);

		$filter_by_username = function ($rows) use ($username_key, &$result) {
			$filtered = array();
			$removed = 0;

			if (!is_array($rows))
			{
				return array($filtered, $removed);
			}

			for ($i = 0; $i < count($rows); $i += 1)
			{
				$row_username = isset($rows[$i]['username']) ? strtolower(trim((string) $rows[$i]['username'])) : '';
				if ($row_username === $username_key)
				{
					$removed += 1;
					continue;
				}
				$filtered[] = $rows[$i];
			}

			return array($filtered, $removed);
		};

		$attendance_records = $this->load_attendance_records();
		$attendance_filtered = $filter_by_username($attendance_records);
		$result['attendance'] = (int) $attendance_filtered[1];
		if ($result['attendance'] > 0)
		{
			$this->save_attendance_records($attendance_filtered[0]);
		}

		$leave_requests = $this->load_leave_requests();
		$leave_filtered = $filter_by_username($leave_requests);
		$result['leave'] = (int) $leave_filtered[1];
		if ($result['leave'] > 0)
		{
			$this->save_leave_requests($leave_filtered[0]);
		}

		$loan_requests = $this->load_loan_requests();
		$loan_filtered = $filter_by_username($loan_requests);
		$result['loan'] = (int) $loan_filtered[1];
		if ($result['loan'] > 0)
		{
			$this->save_loan_requests($loan_filtered[0]);
		}

		$overtime_records = $this->load_overtime_records();
		$overtime_filtered = $filter_by_username($overtime_records);
		$result['overtime'] = (int) $overtime_filtered[1];
		if ($result['overtime'] > 0)
		{
			$this->save_overtime_records($overtime_filtered[0]);
		}

		return $result;
	}

	private function employee_username_list()
	{
		$profiles = $this->employee_profile_book();
		$names = array();
		foreach ($profiles as $username => $profile)
		{
			$username_value = strtolower(trim((string) $username));
			if ($username_value !== '')
			{
				$names[] = $username_value;
			}
		}
		sort($names);
		return $names;
	}

	private function admin_account_id()
	{
		return '00';
	}

	private function format_employee_id($sequence)
	{
		$sequence_value = (int) $sequence;
		if ($sequence_value <= 0)
		{
			return '-';
		}

		if ($sequence_value < 100)
		{
			return str_pad((string) $sequence_value, 2, '0', STR_PAD_LEFT);
		}

		return (string) $sequence_value;
	}

	private function employee_id_book()
	{
		$names = $this->employee_username_list();
		$id_book = array(
			'admin' => $this->admin_account_id()
		);

		$limit = min(count($names), 100);
		for ($i = 0; $i < $limit; $i += 1)
		{
			$id_book[(string) $names[$i]] = $this->format_employee_id($i + 1);
		}

		return $id_book;
	}

	private function resolve_employee_id_from_book($username, $employee_id_book)
	{
		$username_key = strtolower(trim((string) $username));
		if ($username_key === 'admin')
		{
			return $this->admin_account_id();
		}

		if ($username_key === '')
		{
			return '-';
		}

		if (is_array($employee_id_book) && isset($employee_id_book[$username_key]))
		{
			return (string) $employee_id_book[$username_key];
		}

		return '-';
	}

	private function get_employee_id($username)
	{
		return $this->resolve_employee_id_from_book($username, $this->employee_id_book());
	}

	private function leave_support_upload_dir()
	{
		return 'uploads/leave_support';
	}

	private function upload_leave_support_file($field_name, $is_required)
	{
		$is_required = $is_required === TRUE;
		if (!isset($_FILES[$field_name]) || !is_array($_FILES[$field_name]))
		{
			if ($is_required)
			{
				return array(
					'success' => FALSE,
					'status_code' => 422,
					'message' => 'Surat izin sakit wajib diupload.'
				);
			}

			return array(
				'success' => TRUE,
				'file_name' => '',
				'original_name' => '',
				'relative_path' => '',
				'file_ext' => ''
			);
		}

		$file_data = $_FILES[$field_name];
		$file_error = isset($file_data['error']) ? (int) $file_data['error'] : UPLOAD_ERR_NO_FILE;
		if ($file_error === UPLOAD_ERR_NO_FILE)
		{
			if ($is_required)
			{
				return array(
					'success' => FALSE,
					'status_code' => 422,
					'message' => 'Surat izin sakit wajib diupload.'
				);
			}

			return array(
				'success' => TRUE,
				'file_name' => '',
				'original_name' => '',
				'relative_path' => '',
				'file_ext' => ''
			);
		}

		if ($file_error !== UPLOAD_ERR_OK)
		{
			return array(
				'success' => FALSE,
				'status_code' => 422,
				'message' => 'Upload bukti gagal. Coba ulangi lagi.'
			);
		}

		$original_name = isset($file_data['name']) ? trim((string) $file_data['name']) : '';
		$file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
		$allowed_extensions = array('pdf', 'png', 'jpg', 'heic');
		if ($file_ext === '' || !in_array($file_ext, $allowed_extensions, TRUE))
		{
			return array(
				'success' => FALSE,
				'status_code' => 422,
				'message' => 'Format bukti harus .pdf, .png, .jpg, atau .heic.'
			);
		}

		$file_size = isset($file_data['size']) ? (int) $file_data['size'] : 0;
		$max_size_bytes = 8 * 1024 * 1024;
		if ($file_size <= 0 || $file_size > $max_size_bytes)
		{
			return array(
				'success' => FALSE,
				'status_code' => 422,
				'message' => 'Ukuran bukti maksimal 8MB.'
			);
		}

		$tmp_name = isset($file_data['tmp_name']) ? (string) $file_data['tmp_name'] : '';
		if ($tmp_name === '' || !is_uploaded_file($tmp_name))
		{
			return array(
				'success' => FALSE,
				'status_code' => 422,
				'message' => 'File bukti tidak valid.'
			);
		}

		$upload_directory_relative = trim($this->leave_support_upload_dir(), '/\\');
		$upload_directory_absolute = rtrim((string) FCPATH, '/\\').DIRECTORY_SEPARATOR.
			str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $upload_directory_relative);
		if (!is_dir($upload_directory_absolute))
		{
			@mkdir($upload_directory_absolute, 0755, TRUE);
		}

		if (!is_dir($upload_directory_absolute))
		{
			return array(
				'success' => FALSE,
				'status_code' => 500,
				'message' => 'Folder upload bukti tidak tersedia.'
			);
		}

		$file_name = 'support_'.date('YmdHis').'_'.strtolower(substr(md5(uniqid('', TRUE)), 0, 10)).'.'.$file_ext;
		$target_path = rtrim($upload_directory_absolute, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file_name;
		if (!@move_uploaded_file($tmp_name, $target_path))
		{
			return array(
				'success' => FALSE,
				'status_code' => 500,
				'message' => 'Gagal menyimpan file bukti.'
			);
		}

		$relative_path = str_replace('\\', '/', $upload_directory_relative.'/'.$file_name);
		return array(
			'success' => TRUE,
			'file_name' => $file_name,
			'original_name' => $original_name,
			'relative_path' => $relative_path,
			'file_ext' => $file_ext
		);
	}

	private function is_valid_date_format($date_value)
	{
		$date_value = trim((string) $date_value);
		if ($date_value === '')
		{
			return FALSE;
		}

		$date_time = DateTime::createFromFormat('Y-m-d', $date_value);
		return ($date_time instanceof DateTime) && $date_time->format('Y-m-d') === $date_value;
	}

	private function default_employee_profile_photo()
	{
		$preferred_file = 'src/assets/fotoku.JPG';
		$fallback_file = 'src/assets/fotoku.jpg';
		$preferred_abs = rtrim((string) FCPATH, '/\\').DIRECTORY_SEPARATOR.str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $preferred_file);
		if (is_file($preferred_abs))
		{
			return '/'.$preferred_file;
		}

		$fallback_abs = rtrim((string) FCPATH, '/\\').DIRECTORY_SEPARATOR.str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $fallback_file);
		if (is_file($fallback_abs))
		{
			return '/'.$fallback_file;
		}

		return '/'.$preferred_file;
	}

	private function default_employee_address()
	{
		return 'Kp. Kesekian Kalinya, Pandenglang, Banten';
	}

	private function employee_branch_options()
	{
		if (function_exists('absen_employee_branch_options'))
		{
			$options = absen_employee_branch_options();
			if (is_array($options) && !empty($options))
			{
				return array_values($options);
			}
		}

		return array(
			'Baros',
			'Cadasari'
		);
	}

	private function default_employee_branch()
	{
		if (function_exists('absen_default_employee_branch'))
		{
			$branch = trim((string) absen_default_employee_branch());
			if ($branch !== '')
			{
				return $branch;
			}
		}

		return 'Baros';
	}

	private function resolve_employee_branch($branch)
	{
		if (function_exists('absen_resolve_employee_branch'))
		{
			$resolved = trim((string) absen_resolve_employee_branch($branch));
			if ($resolved !== '')
			{
				return $resolved;
			}
		}

		$branch_value = trim((string) $branch);
		if ($branch_value === '')
		{
			return '';
		}

		$options = $this->employee_branch_options();
		for ($i = 0; $i < count($options); $i += 1)
		{
			if (strcasecmp($branch_value, (string) $options[$i]) === 0)
			{
				return (string) $options[$i];
			}
		}

		return '';
	}

	private function employee_job_title_options()
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

	private function resolve_employee_job_title($job_title)
	{
		$job_title_value = trim((string) $job_title);
		if ($job_title_value === '')
		{
			return '';
		}

		$options = $this->employee_job_title_options();
		for ($i = 0; $i < count($options); $i += 1)
		{
			if (strcasecmp($job_title_value, (string) $options[$i]) === 0)
			{
				return (string) $options[$i];
			}
		}

		return '';
	}

	private function default_employee_job_title()
	{
		return 'Teknisi';
	}

	private function normalize_username_key($username)
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

	private function employee_profile_book()
	{
		$accounts = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		$profiles = array();

		foreach ($accounts as $username => $account)
		{
			$username_key = strtolower(trim((string) $username));
			if ($username_key === '' || !is_array($account))
			{
				continue;
			}

			$role = strtolower(trim((string) (isset($account['role']) ? $account['role'] : 'user')));
			if ($role !== 'user')
			{
				continue;
			}

			$job_title = $this->resolve_employee_job_title(isset($account['job_title']) ? (string) $account['job_title'] : '');
			if ($job_title === '')
			{
				$job_title = $this->default_employee_job_title();
			}

			$profiles[$username_key] = array(
				'branch' => $this->resolve_employee_branch(isset($account['branch']) ? (string) $account['branch'] : ''),
				'phone' => isset($account['phone']) ? (string) $account['phone'] : '',
				'shift_name' => isset($account['shift_name']) ? (string) $account['shift_name'] : 'Shift Pagi - Sore',
				'shift_time' => isset($account['shift_time']) ? (string) $account['shift_time'] : '08:00 - 17:00',
				'job_title' => $job_title,
				'salary_tier' => isset($account['salary_tier']) ? (string) $account['salary_tier'] : 'A',
				'salary_monthly' => isset($account['salary_monthly']) ? (int) $account['salary_monthly'] : 0,
				'work_days' => isset($account['work_days']) ? (int) $account['work_days'] : 28,
				'profile_photo' => isset($account['profile_photo']) ? (string) $account['profile_photo'] : $this->default_employee_profile_photo(),
				'address' => isset($account['address']) && trim((string) $account['address']) !== ''
					? (string) $account['address']
					: $this->default_employee_address()
			);
			if ($profiles[$username_key]['branch'] === '')
			{
				$profiles[$username_key]['branch'] = $this->default_employee_branch();
			}
		}

		ksort($profiles);
		return $profiles;
	}

	private function get_employee_profile($username)
	{
		$profiles = $this->employee_profile_book();
		$username_key = strtolower(trim((string) $username));
		if ($username_key !== '' && isset($profiles[$username_key]) && is_array($profiles[$username_key]))
		{
			$profile = $profiles[$username_key];
			if (!isset($profile['profile_photo']) || trim((string) $profile['profile_photo']) === '')
			{
				$profile['profile_photo'] = $this->default_employee_profile_photo();
			}
			if (!isset($profile['address']) || trim((string) $profile['address']) === '')
			{
				$profile['address'] = $this->default_employee_address();
			}
			if (!isset($profile['branch']) || trim((string) $profile['branch']) === '')
			{
				$profile['branch'] = $this->default_employee_branch();
			}
			$profile['job_title'] = $this->resolve_employee_job_title(isset($profile['job_title']) ? (string) $profile['job_title'] : '');
			if ($profile['job_title'] === '')
			{
				$profile['job_title'] = $this->default_employee_job_title();
			}

			return $profile;
		}

		return array(
			'profile_photo' => $this->default_employee_profile_photo(),
			'address' => $this->default_employee_address(),
			'job_title' => $this->default_employee_job_title()
		);
	}

	private function employee_phone_book()
	{
		$profiles = $this->employee_profile_book();
		$phone_book = array();
		foreach ($profiles as $username => $profile)
		{
			$phone_book[(string) $username] = isset($profile['phone']) ? (string) $profile['phone'] : '';
		}

		return $phone_book;
	}

	private function get_employee_phone($username)
	{
		$phone_book = $this->employee_phone_book();
		$username_key = strtolower(trim((string) $username));
		if ($username_key !== '' && isset($phone_book[$username_key]))
		{
			return (string) $phone_book[$username_key];
		}

		return '';
	}

	private function normalize_phone_number($phone)
	{
		$digits = preg_replace('/\D+/', '', (string) $phone);
		if ($digits === '')
		{
			return '';
		}

		if (strpos($digits, '62') === 0)
		{
			return $digits;
		}

		if (strpos($digits, '0') === 0)
		{
			return '62'.substr($digits, 1);
		}

		return $digits;
	}

	private function build_leave_status_whatsapp_message($request_row)
	{
		$username = isset($request_row['username']) ? (string) $request_row['username'] : 'karyawan';
		$type_label = isset($request_row['request_type_label']) && trim((string) $request_row['request_type_label']) !== ''
			? (string) $request_row['request_type_label']
			: 'Pengajuan';
		$status_label = isset($request_row['status']) && trim((string) $request_row['status']) !== ''
			? strtoupper((string) $request_row['status'])
			: 'MENUNGGU';
		$start_date_label = isset($request_row['start_date_label']) && trim((string) $request_row['start_date_label']) !== ''
			? (string) $request_row['start_date_label']
			: '-';
		$end_date_label = isset($request_row['end_date_label']) && trim((string) $request_row['end_date_label']) !== ''
			? (string) $request_row['end_date_label']
			: '-';
		$duration_days = isset($request_row['duration_days']) ? (int) $request_row['duration_days'] : 1;
		$reason = isset($request_row['reason']) ? trim((string) $request_row['reason']) : '';
		if ($reason === '')
		{
			$reason = '-';
		}

		return "Halo ".$username.", pengajuan ".$type_label." kamu ".$status_label." oleh admin.\n".
			"Periode: ".$start_date_label." s/d ".$end_date_label." (".$duration_days." hari)\n".
			"Alasan: ".$reason."\n".
			"Terima kasih.";
	}

	private function build_loan_status_whatsapp_message($request_row)
	{
		$username = isset($request_row['username']) ? (string) $request_row['username'] : 'karyawan';
		$status_label = isset($request_row['status']) && trim((string) $request_row['status']) !== ''
			? strtoupper((string) $request_row['status'])
			: 'MENUNGGU';
		$request_date_label = isset($request_row['request_date_label']) && trim((string) $request_row['request_date_label']) !== ''
			? (string) $request_row['request_date_label']
			: '-';
		$amount_label = isset($request_row['amount_label']) && trim((string) $request_row['amount_label']) !== ''
			? (string) $request_row['amount_label']
			: 'Rp 0';
		$reason = isset($request_row['reason']) ? trim((string) $request_row['reason']) : '';
		$transparency = isset($request_row['transparency']) ? trim((string) $request_row['transparency']) : '';
		if ($reason === '')
		{
			$reason = '-';
		}
		if ($transparency === '')
		{
			$transparency = '-';
		}

		return "Halo ".$username.", pengajuan PINJAMAN kamu ".$status_label." oleh admin.\n".
			"Tanggal pengajuan: ".$request_date_label."\n".
			"Nominal: ".$amount_label."\n".
			"Alasan: ".$reason."\n".
			"Rincian pinjaman: ".$transparency."\n".
			"Terima kasih.";
	}

	private function send_whatsapp_notification($phone, $message)
	{
		$phone_raw = trim((string) $phone);
		$phone_normalized = $this->normalize_phone_number($phone_raw);
		$message = trim((string) $message);

		if ($phone_normalized === '')
		{
			return array(
				'success' => FALSE,
				'message' => 'Nomor WhatsApp karyawan belum tersedia.'
			);
		}

		if ($message === '')
		{
			return array(
				'success' => FALSE,
				'message' => 'Isi pesan WhatsApp kosong.'
			);
		}

		$gateway_url = trim((string) getenv('WA_GATEWAY_URL'));
		$gateway_token = trim((string) getenv('WA_GATEWAY_TOKEN'));
		if ($gateway_url !== '')
		{
			$payload = json_encode(array(
				'phone' => $phone_normalized,
				'message' => $message
			));
			$headers = array('Content-Type: application/json');
			if ($gateway_token !== '')
			{
				$headers[] = 'Authorization: Bearer '.$gateway_token;
			}

			$gateway_response = $this->http_post_request($gateway_url, $payload, $headers);
			if ($gateway_response['success'])
			{
				return array(
					'success' => TRUE,
					'message' => 'Notifikasi WA terkirim lewat gateway.'
				);
			}

			return array(
				'success' => FALSE,
				'message' => $gateway_response['message']
			);
		}

		$fonnte_token = trim((string) getenv('FONNTE_TOKEN'));
		if ($fonnte_token !== '')
		{
			$payload = http_build_query(array(
				'target' => $phone_normalized,
				'message' => $message,
				'countryCode' => '62'
			));

			$fonnte_response = $this->http_post_request(
				'https://api.fonnte.com/send',
				$payload,
				array(
					'Authorization: '.$fonnte_token,
					'Content-Type: application/x-www-form-urlencoded'
				)
			);

			if ($fonnte_response['success'])
			{
				$decoded_body = json_decode($fonnte_response['body'], TRUE);
				if (is_array($decoded_body) && isset($decoded_body['status']) && $decoded_body['status'] === TRUE)
				{
					return array(
						'success' => TRUE,
						'message' => 'Notifikasi WA terkirim lewat Fonnte.'
					);
				}
				if (is_array($decoded_body) && isset($decoded_body['reason']) && trim((string) $decoded_body['reason']) !== '')
				{
					return array(
						'success' => FALSE,
						'message' => 'Fonnte gagal: '.trim((string) $decoded_body['reason'])
					);
				}
			}

			return array(
				'success' => FALSE,
				'message' => $fonnte_response['message']
			);
		}

		return array(
			'success' => FALSE,
			'message' => 'Konfigurasi gateway WhatsApp belum diatur. Set WA_GATEWAY_URL atau FONNTE_TOKEN.'
		);
	}

	private function http_post_request($url, $payload, $headers = array())
	{
		$url = trim((string) $url);
		if ($url === '')
		{
			return array(
				'success' => FALSE,
				'status_code' => 0,
				'body' => '',
				'message' => 'URL endpoint kosong.'
			);
		}

		if (!function_exists('curl_init'))
		{
			return array(
				'success' => FALSE,
				'status_code' => 0,
				'body' => '',
				'message' => 'Ekstensi cURL belum aktif.'
			);
		}

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$response_body = curl_exec($ch);
		$response_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error_message = curl_error($ch);
		curl_close($ch);

		if ($response_body === FALSE)
		{
			return array(
				'success' => FALSE,
				'status_code' => $response_code,
				'body' => '',
				'message' => 'HTTP request gagal: '.$error_message
			);
		}

		if ($response_code < 200 || $response_code >= 300)
		{
			return array(
				'success' => FALSE,
				'status_code' => $response_code,
				'body' => (string) $response_body,
				'message' => 'HTTP request gagal dengan status '.$response_code.'.'
			);
		}

		return array(
			'success' => TRUE,
			'status_code' => $response_code,
			'body' => (string) $response_body,
			'message' => 'OK'
		);
	}

	private function calculate_late_duration($check_in_time, $shift_time)
	{
		if ($check_in_time === '' || $shift_time === '')
		{
			return '00:00:00';
		}

		$start_time = '';
		if (preg_match('/(\d{2}:\d{2})/', $shift_time, $matches))
		{
			$start_time = $matches[1].':00';
		}

		if ($start_time === '')
		{
			return '00:00:00';
		}

		$start_seconds = strtotime('1970-01-01 '.$start_time);
		$check_seconds = strtotime('1970-01-01 '.$check_in_time);
		if ($check_seconds === FALSE || $start_seconds === FALSE)
		{
			return '00:00:00';
		}

		$late_threshold_seconds = $start_seconds + (int) self::LATE_TOLERANCE_SECONDS;
		if ($check_seconds <= $late_threshold_seconds)
		{
			return '00:00:00';
		}

		$diff = $check_seconds - $late_threshold_seconds;
		return gmdate('H:i:s', $diff);
	}

	private function calculate_work_duration($check_in_time, $check_out_time)
	{
		if ($check_in_time === '' || $check_out_time === '')
		{
			return '';
		}

		$start_seconds = strtotime('1970-01-01 '.$check_in_time);
		$end_seconds = strtotime('1970-01-01 '.$check_out_time);
		if ($start_seconds === FALSE || $end_seconds === FALSE || $end_seconds < $start_seconds)
		{
			return '00:00:00';
		}

		return gmdate('H:i:s', $end_seconds - $start_seconds);
	}

	private function time_to_seconds($time_value)
	{
		$time_value = trim((string) $time_value);
		if ($time_value === '')
		{
			return 0;
		}

		$parts = explode(':', $time_value);
		$hours = isset($parts[0]) ? (int) $parts[0] : 0;
		$minutes = isset($parts[1]) ? (int) $parts[1] : 0;
		$seconds = isset($parts[2]) ? (int) $parts[2] : 0;

		return ($hours * 3600) + ($minutes * 60) + $seconds;
	}

	private function duration_to_seconds($duration_value)
	{
		return $this->time_to_seconds($duration_value);
	}

	private function calculate_late_deduction($salary_tier, $salary_monthly, $work_days, $late_seconds, $date_key = '', $username = '')
	{
		$late_seconds = (int) $late_seconds;
		$salary_monthly = $this->resolve_monthly_salary($salary_tier, (float) $salary_monthly);
		$weekly_day_off_n = $this->normalize_weekly_day_off(self::WEEKLY_HOLIDAY_DAY);
		$month_policy = $this->calculate_month_work_policy($date_key, $weekly_day_off_n);
		$year = isset($month_policy['year']) ? (int) $month_policy['year'] : (int) date('Y');
		$month = isset($month_policy['month']) ? (int) $month_policy['month'] : (int) date('n');
		$weekly_leave_taken = isset($month_policy['weekly_off_days']) ? (int) $month_policy['weekly_off_days'] : 0;
		$monthly_summary = $this->calculate_monthly_deduction_summary(
			$salary_monthly,
			$year,
			$month,
			$weekly_day_off_n,
			0,
			0,
			$weekly_leave_taken,
			0,
			0,
			0,
			0,
			0
		);
		$potongan_per_hari = isset($monthly_summary['potongan_per_hari']) && is_array($monthly_summary['potongan_per_hari'])
			? $monthly_summary['potongan_per_hari']
			: array(
				'telat_1_30' => 0,
				'telat_31_60' => 0,
				'telat_1_3_jam' => 0,
				'telat_gt_4_jam' => 0,
				'alpha' => 0
			);

		if ($late_seconds <= 0)
		{
			return array(
				'amount' => 0,
				'rule' => 'Tidak telat',
				'category_key' => '',
				'potongan_per_hari' => $potongan_per_hari
			);
		}

		$rule = '';
		$category_key = '';
		$amount = 0;
		if ($late_seconds <= 1800)
		{
			$category_key = 'telat_1_30';
			$rule = 'Telat 1-30 menit';
			$amount = isset($potongan_per_hari[$category_key]) ? (int) $potongan_per_hari[$category_key] : 0;
		}
		elseif ($late_seconds <= 3600)
		{
			$category_key = 'telat_31_60';
			$rule = 'Telat 31-60 menit';
			$amount = isset($potongan_per_hari[$category_key]) ? (int) $potongan_per_hari[$category_key] : 0;
		}
		elseif ($late_seconds > self::HALF_DAY_LATE_THRESHOLD_SECONDS)
		{
			$category_key = 'telat_gt_4_jam';
			$rule = 'Telat lebih dari 4 jam';
			$amount = isset($potongan_per_hari[$category_key]) ? (int) $potongan_per_hari[$category_key] : 0;
		}
		else
		{
			$category_key = 'telat_1_3_jam';
			$rule = 'Telat lebih dari 1 jam hingga 4 jam';
			$amount = isset($potongan_per_hari[$category_key]) ? (int) $potongan_per_hari[$category_key] : 0;
		}

		return array(
			'amount' => max(0, (int) $amount),
			'rule' => $rule,
			'category_key' => $category_key,
			'potongan_per_hari' => $potongan_per_hari
		);
	}

	private function resolve_monthly_salary($salary_tier, $salary_monthly)
	{
		$salary_monthly = (float) $salary_monthly;
		if ($salary_monthly > 0)
		{
			return $salary_monthly;
		}

		$tier = strtoupper(trim((string) $salary_tier));
		$tier_monthly_salary = array(
			'A' => 1000000,
			'B' => 2000000,
			'C' => 3000000,
			'D' => 4000000
		);

		if ($tier !== '' && isset($tier_monthly_salary[$tier]))
		{
			return (float) $tier_monthly_salary[$tier];
		}

		return 1000000.0;
	}

	private function resolve_salary_tier_from_amount($salary_monthly)
	{
		$amount = (int) $salary_monthly;
		if ($amount <= 1500000)
		{
			return 'A';
		}
		if ($amount <= 2500000)
		{
			return 'B';
		}
		if ($amount <= 3500000)
		{
			return 'C';
		}

		return 'D';
	}

	private function should_randomize_monthly_demo_data($hadir_days, $leave_days, $total_alpha, $hari_efektif)
	{
		$hadir_days = max(0, (int) $hadir_days);
		$leave_days = max(0, (int) $leave_days);
		$total_alpha = max(0, (int) $total_alpha);
		$hari_efektif = max(1, (int) $hari_efektif);

		$very_low_attendance = $hadir_days <= 2;
		$alpha_ratio = $hari_efektif > 0 ? ($total_alpha / $hari_efektif) : 0;
		$dominant_alpha = $alpha_ratio >= 0.75;
		$almost_no_leave = $leave_days <= 1;

		return $very_low_attendance && $dominant_alpha && $almost_no_leave;
	}

	private function seeded_rand_range(&$seed, $min_value, $max_value)
	{
		$min_value = (int) $min_value;
		$max_value = (int) $max_value;
		if ($max_value < $min_value)
		{
			$temp = $max_value;
			$max_value = $min_value;
			$min_value = $temp;
		}
		if ($max_value === $min_value)
		{
			return $min_value;
		}

		$seed = (int) ((1103515245 * (int) $seed + 12345) & 0x7fffffff);
		$range = ($max_value - $min_value) + 1;
		return $min_value + ($seed % $range);
	}

	private function build_randomized_monthly_demo_data($username, $year, $month, $hari_efektif, $leave_used_before_period)
	{
		$hari_efektif = max(1, (int) $hari_efektif);
		$leave_used_before_period = max(0, (int) $leave_used_before_period);
		$seed_source = strtolower(trim((string) $username)).'|'.(int) $year.'|'.(int) $month;
		$seed = abs((int) crc32($seed_source));

		$remaining_leave = max(0, 12 - $leave_used_before_period);
		$max_cuti = min($remaining_leave, max(0, min(2, $hari_efektif - 1)));
		$cuti_days = $this->seeded_rand_range($seed, 0, $max_cuti);

		$max_izin = max(0, min(3, $hari_efektif - $cuti_days - 1));
		$izin_days = $this->seeded_rand_range($seed, 0, $max_izin);

		$min_hadir = max(1, (int) floor($hari_efektif * 0.65));
		$max_hadir = max($min_hadir, $hari_efektif - $cuti_days - $izin_days);
		$hadir_days = $this->seeded_rand_range($seed, $min_hadir, $max_hadir);
		$hadir_limit = $hari_efektif - $cuti_days - $izin_days;
		if ($hadir_days > $hadir_limit)
		{
			$hadir_days = $hadir_limit;
		}
		if ($hadir_days < 0)
		{
			$hadir_days = 0;
		}

		$total_alpha = $hari_efektif - $hadir_days - $cuti_days - $izin_days;
		if ($total_alpha < 0)
		{
			$total_alpha = 0;
		}

		$max_late_total = max(0, min($hadir_days, 8));
		$late_total = $this->seeded_rand_range($seed, 1, max(1, $max_late_total));
		if ($max_late_total === 0)
		{
			$late_total = 0;
		}

		$late_gt_4 = $this->seeded_rand_range($seed, 0, min($late_total, 2));
		$remaining = $late_total - $late_gt_4;
		$late_1_3 = $this->seeded_rand_range($seed, 0, min($remaining, 3));
		$remaining -= $late_1_3;
		$late_31_60 = $this->seeded_rand_range($seed, 0, min($remaining, 3));
		$remaining -= $late_31_60;
		$late_1_30 = $remaining;

		return array(
			'hadir_days' => max(0, (int) $hadir_days),
			'izin_days' => max(0, (int) $izin_days),
			'cuti_days' => max(0, (int) $cuti_days),
			'total_alpha' => max(0, (int) $total_alpha),
			'late_1_30' => max(0, (int) $late_1_30),
			'late_31_60' => max(0, (int) $late_31_60),
			'late_1_3' => max(0, (int) $late_1_3),
			'late_gt_4' => max(0, (int) $late_gt_4)
		);
	}

	private function round_to_nearest_thousand($amount)
	{
		$amount = (float) $amount;
		$base = (int) self::DEDUCTION_ROUND_BASE;
		if ($base <= 0)
		{
			$base = 1000;
		}

		return (int) (round($amount / $base) * $base);
	}

	private function normalize_weekly_day_off($weekly_day_off)
	{
		$weekly_day_off = (int) $weekly_day_off;
		if ($weekly_day_off >= 1 && $weekly_day_off <= 7)
		{
			return $weekly_day_off;
		}

		// Kompatibilitas nilai lama date('w'): 0=Sunday, 1=Monday ... 6=Saturday.
		if ($weekly_day_off === 0)
		{
			return 7;
		}

		return 1;
	}

	private function count_weekday_occurrences($year, $month, $weekday_n)
	{
		$year = (int) $year;
		$month = (int) $month;
		$weekday_n = $this->normalize_weekly_day_off($weekday_n);
		$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		$total = 0;
		for ($day = 1; $day <= $days_in_month; $day += 1)
		{
			$current_weekday = (int) date('N', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $day)));
			if ($current_weekday === $weekday_n)
			{
				$total += 1;
			}
		}

		return $total;
	}

	private function weekly_quota_by_days($days_in_month)
	{
		$days_in_month = max(1, (int) $days_in_month);
		return (int) floor($days_in_month / 7);
	}

	private function calculate_monthly_deduction_summary(
		$gaji_bulanan,
		$year,
		$month,
		$weekly_day_off,
		$leave_used_before_period = 0,
		$leave_taken_this_month = 0,
		$weekly_leave_taken = 0,
		$total_alpha = 0,
		$total_telat_1_30 = 0,
		$total_telat_31_60 = 0,
		$total_telat_1_3_jam = 0,
		$total_telat_gt_4_jam = 0
	)
	{
		$gaji_bulanan = max(0, (float) $gaji_bulanan);
		$year = (int) $year;
		$month = (int) $month;
		if ($year < 1970)
		{
			$year = (int) date('Y');
		}
		if ($month < 1 || $month > 12)
		{
			$month = (int) date('n');
		}

		$weekly_day_off_n = $this->normalize_weekly_day_off($weekly_day_off);
		$leave_used_before_period = max(0, (int) $leave_used_before_period);
		$leave_taken_this_month = max(0, (int) $leave_taken_this_month);
		$weekly_leave_taken = max(0, (int) $weekly_leave_taken);
		$total_alpha = max(0, (int) $total_alpha);
		$total_telat_1_30 = max(0, (int) $total_telat_1_30);
		$total_telat_31_60 = max(0, (int) $total_telat_31_60);
		$total_telat_1_3_jam = max(0, (int) $total_telat_1_3_jam);
		$total_telat_gt_4_jam = max(0, (int) $total_telat_gt_4_jam);

		$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		$weekly_quota = $this->weekly_quota_by_days($days_in_month);
		$weekly_excess = max(0, $weekly_leave_taken - $weekly_quota);

		$annual_quota = 12;
		$remaining_leave = max(0, $annual_quota - $leave_used_before_period);
		$annual_excess = max(0, $leave_taken_this_month - $remaining_leave);

		$alpha_final = $total_alpha + $weekly_excess + $annual_excess;
		$hari_effective = max($days_in_month - $weekly_quota, 1);
		$alpha_final = min($alpha_final, $hari_effective);

		$total_event = $total_telat_1_30 + $total_telat_31_60 + $total_telat_1_3_jam + $total_telat_gt_4_jam + $alpha_final;
		$overflow_awal = 0;
		if ($total_event > $hari_effective)
		{
			$overflow_awal = $total_event - $hari_effective;
			$overflow = $overflow_awal;

			// Kurangi alpha dulu.
			$reduce_alpha = min($alpha_final, $overflow);
			$alpha_final -= $reduce_alpha;
			$overflow -= $reduce_alpha;

			// Lalu kurangi telat dari kategori terbesar ke terkecil (konservatif).
			if ($overflow > 0)
			{
				$reduce = min($total_telat_gt_4_jam, $overflow);
				$total_telat_gt_4_jam -= $reduce;
				$overflow -= $reduce;
			}
			if ($overflow > 0)
			{
				$reduce = min($total_telat_1_3_jam, $overflow);
				$total_telat_1_3_jam -= $reduce;
				$overflow -= $reduce;
			}
			if ($overflow > 0)
			{
				$reduce = min($total_telat_31_60, $overflow);
				$total_telat_31_60 -= $reduce;
				$overflow -= $reduce;
			}
			if ($overflow > 0)
			{
				$reduce = min($total_telat_1_30, $overflow);
				$total_telat_1_30 -= $reduce;
				$overflow -= $reduce;
			}
		}

		$gaji_harian = $hari_effective > 0 ? ($gaji_bulanan / $hari_effective) : 0;
		$potongan_per_hari = array(
			'telat_1_30' => $this->round_to_nearest_thousand(0.21 * $gaji_harian),
			'telat_31_60' => $this->round_to_nearest_thousand(0.32 * $gaji_harian),
			'telat_1_3_jam' => $this->round_to_nearest_thousand(0.53 * $gaji_harian),
			'telat_gt_4_jam' => $this->round_to_nearest_thousand(0.77 * $gaji_harian),
			'alpha' => $this->round_to_nearest_thousand(1.00 * $gaji_harian)
		);

		$total_potongan =
			($potongan_per_hari['telat_1_30'] * $total_telat_1_30) +
			($potongan_per_hari['telat_31_60'] * $total_telat_31_60) +
			($potongan_per_hari['telat_1_3_jam'] * $total_telat_1_3_jam) +
			($potongan_per_hari['telat_gt_4_jam'] * $total_telat_gt_4_jam) +
			($potongan_per_hari['alpha'] * $alpha_final);

		$gaji_bersih = (int) max(0, round($gaji_bulanan - $total_potongan));

		return array(
			'days_in_month' => $days_in_month,
			'weekly_day_off' => $weekly_day_off_n,
			'weekly_quota' => $weekly_quota,
			'weekly_excess' => $weekly_excess,
			'annual_quota' => $annual_quota,
			'remaining_leave' => $remaining_leave,
			'annual_excess' => $annual_excess,
			'hari_effective' => $hari_effective,
			'gaji_harian' => $gaji_harian,
			'potongan_per_hari' => $potongan_per_hari,
			'potongan_per_kategori' => $potongan_per_hari,
			'adjusted_counts' => array(
				'telat_1_30' => $total_telat_1_30,
				'telat_31_60' => $total_telat_31_60,
				'telat_1_3_jam' => $total_telat_1_3_jam,
				'telat_gt_4_jam' => $total_telat_gt_4_jam,
				'alpha_final' => $alpha_final
			),
			'overflow_awal' => $overflow_awal,
			'total_potongan' => (int) $total_potongan,
			'gaji_bersih' => $gaji_bersih
		);
	}

	private function calculate_month_work_policy($date_key = '', $weekly_day_off = NULL)
	{
		$base_timestamp = time();
		if ($date_key !== '')
		{
			$timestamp = strtotime($date_key);
			if ($timestamp !== FALSE)
			{
				$base_timestamp = $timestamp;
			}
		}

		$year = (int) date('Y', $base_timestamp);
		$month = (int) date('n', $base_timestamp);
		$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		$weekly_day_off_n = $this->normalize_weekly_day_off($weekly_day_off === NULL ? self::WEEKLY_HOLIDAY_DAY : $weekly_day_off);
		$weekly_off_days = $this->weekly_quota_by_days($days_in_month);
		$work_days = max($days_in_month - $weekly_off_days, 1);

		return array(
			'year' => $year,
			'month' => $month,
			'days_in_month' => $days_in_month,
			'weekly_day_off' => $weekly_day_off_n,
			'weekly_off_days' => $weekly_off_days,
			'work_days' => $work_days
		);
	}

	private function evaluate_geofence($distance_m, $accuracy_m)
	{
		$radius_m = (float) self::OFFICE_RADIUS_M;
		$distance_m = (float) $distance_m;
		$accuracy_m = (float) $accuracy_m;

		if ($distance_m <= $radius_m && $accuracy_m <= (float) self::MAX_GPS_ACCURACY_M)
		{
			return array(
				'inside' => TRUE,
				'message' => 'Lokasi valid di dalam radius kantor.'
			);
		}

		if (($distance_m + $accuracy_m) <= $radius_m)
		{
			return array(
				'inside' => TRUE,
				'message' => 'Lokasi valid di dalam radius kantor (toleransi akurasi GPS).'
			);
		}

		if (($distance_m - $accuracy_m) > $radius_m)
		{
			return array(
				'inside' => FALSE,
				'message' => 'Lokasi di luar radius kantor. Jarak kamu '.round($distance_m, 2).'m dari titik kantor (maks '.self::OFFICE_RADIUS_M.'m).'
			);
		}

		return array(
			'inside' => FALSE,
			'message' => 'Posisi GPS belum cukup akurat (jarak '.round($distance_m, 2).'m, akurasi '.round($accuracy_m, 2).'m). Aktifkan lokasi akurat lalu coba lagi.'
		);
	}

	private function calculate_distance_meter($lat1, $lng1, $lat2, $lng2)
	{
		$earth_radius = 6371000.0;
		$lat1_rad = deg2rad((float) $lat1);
		$lng1_rad = deg2rad((float) $lng1);
		$lat2_rad = deg2rad((float) $lat2);
		$lng2_rad = deg2rad((float) $lng2);

		$delta_lat = $lat2_rad - $lat1_rad;
		$delta_lng = $lng2_rad - $lng1_rad;

		$a = sin($delta_lat / 2) * sin($delta_lat / 2) +
			cos($lat1_rad) * cos($lat2_rad) *
			sin($delta_lng / 2) * sin($delta_lng / 2);
		$c = 2 * atan2(sqrt($a), sqrt(1 - $a));

		return $earth_radius * $c;
	}

	private function json_response($payload, $status_code = 200)
	{
		$this->output
			->set_status_header((int) $status_code)
			->set_content_type('application/json', 'utf-8')
			->set_output(json_encode($payload));
	}
}
