<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends CI_Controller {
	const OFFICE_LAT = -6.217076;
	const OFFICE_LNG = 106.132128;
	const OFFICE_ALT_LAT = -6.270011;
	const OFFICE_ALT_LNG = 106.120800;
	const OFFICE_RADIUS_M = 100;
	const MAX_GPS_ACCURACY_M = 50;
	const CHECK_IN_MIN_TIME = '06:30:00';
	const CHECK_IN_MAX_TIME = '17:00:00';
	const CHECK_OUT_MAX_TIME = '23:59:00';
	const ATTENDANCE_TIME_BYPASS_USERS = array();
	const ATTENDANCE_FORCE_LATE_USERS = array();
	const ATTENDANCE_FORCE_LATE_DURATION = '00:30:00';
	const ATTENDANCE_REMINDER_SLOTS = array('11:00', '13:00', '17:00');
	const ATTENDANCE_REMINDER_SLOT_GRACE_MINUTES = 59;
	const LATE_TOLERANCE_SECONDS = 600;
	const WORK_DAYS_DEFAULT = 22;
	const MIN_EFFECTIVE_WORK_DAYS = 20;
	const DEDUCTION_ROUND_BASE = 1000;
	const HALF_DAY_LATE_THRESHOLD_SECONDS = 14400;
	const WEEKLY_HOLIDAY_DAY = 1;
	const ADMIN_DASHBOARD_SUMMARY_CACHE_TTL_SECONDS = 45;
	const PROFILE_PHOTO_MAX_WIDTH = 512;
	const PROFILE_PHOTO_MAX_HEIGHT = 512;
	const PROFILE_PHOTO_JPEG_QUALITY = 82;
	const PROFILE_PHOTO_THUMB_SIZE = 160;
	const PROFILE_PHOTO_THUMB_JPEG_QUALITY = 76;

	public function __construct()
	{
		parent::__construct();
		$this->load->library('session');
		$this->load->library('absen_sheet_sync');
		$this->load->helper('absen_account_store');
		$this->load->helper('absen_data_store');
		date_default_timezone_set('Asia/Jakarta');

		if ($this->session->userdata('absen_logged_in') === TRUE)
		{
			if ($this->is_current_session_expired())
			{
				$this->session->sess_destroy();
				redirect('login');
				return;
			}

			$session_profile_ok = $this->sync_session_actor_profile();
			if ($session_profile_ok !== TRUE)
			{
				$this->session->sess_destroy();
				redirect('login');
				return;
			}
			$this->session->set_userdata('absen_last_activity_at', time());

			$password_change_required = ((int) $this->session->userdata('absen_password_change_required')) === 1;
			if ($password_change_required && !$this->is_force_password_route())
			{
				redirect('home/force_change_password');
				return;
			}
		}
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
			$attendance_branch = $this->resolve_attendance_branch_for_user($username, $user_profile);
			$office_points = $this->attendance_office_points_for_branch($attendance_branch);
			$primary_office = isset($office_points[0]) && is_array($office_points[0])
				? $office_points[0]
				: array(
					'label' => 'Kantor',
					'lat' => (float) self::OFFICE_LAT,
					'lng' => (float) self::OFFICE_LNG
				);
			$is_first_loan = $this->is_first_loan_request($username);
			$dashboard_snapshot = $this->build_user_dashboard_snapshot($username, $shift_name, $shift_time);
			$data = array(
				'title' => 'Dashboard Absen - User',
				'username' => $display_name !== '' ? $display_name : ($username !== '' ? $username : 'user'),
				'profile_photo' => isset($user_profile['profile_photo']) && trim((string) $user_profile['profile_photo']) !== ''
					? (string) $user_profile['profile_photo']
					: $this->default_employee_profile_photo(),
				'job_title' => $job_title,
				'shift_name' => $shift_name !== '' ? $shift_name : 'Shift Pagi - Sore',
				'shift_time' => $shift_time !== '' ? $shift_time : '08:00 - 23:00',
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
					'office_lat' => isset($primary_office['lat']) ? (float) $primary_office['lat'] : (float) self::OFFICE_LAT,
					'office_lng' => isset($primary_office['lng']) ? (float) $primary_office['lng'] : (float) self::OFFICE_LNG,
					'radius_m' => self::OFFICE_RADIUS_M,
					'max_accuracy_m' => self::MAX_GPS_ACCURACY_M,
					'office_points' => $office_points
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
		$can_super_admin_manage = $this->can_access_super_admin_features();
		$can_manage_accounts = $this->can_manage_employee_accounts();
		$can_sync_sheet_accounts = $this->can_sync_sheet_accounts_feature();
		$can_view_log_data = $this->can_view_log_data_feature();
		$dashboard_navbar_title = $this->dashboard_navbar_title($username);
		$dashboard_role_label = $this->dashboard_role_label($username);
		$data = array(
			'title' => 'Dashboard Absen Online',
			'username' => $display_name !== '' ? $display_name : $username,
			'dashboard_navbar_title' => $dashboard_navbar_title,
			'dashboard_role_label' => $dashboard_role_label,
			'dashboard_status_label' => $this->dashboard_status_label(),
			'summary' => isset($admin_snapshot['summary']) && is_array($admin_snapshot['summary'])
				? $admin_snapshot['summary']
				: array(),
			'recent_logs' => isset($admin_snapshot['recent_logs']) && is_array($admin_snapshot['recent_logs'])
				? $admin_snapshot['recent_logs']
				: array(),
			'announcements' => array(
				array('title' => 'Pengingat Check Out', 'content' => 'Jangan lupa lakukan check out sebelum batas jam shift masing-masing.'),
				array('title' => 'Verifikasi Data Profil', 'content' => 'Pastikan NIP, unit kerja, dan nomor telepon sudah sesuai.'),
				array('title' => 'Kebijakan Keterlambatan', 'content' => 'Toleransi keterlambatan maksimal 10 menit dari jam masuk.')
			),
			'employee_accounts' => $this->build_employee_account_options(),
			'job_title_options' => $this->employee_job_title_options(),
			'branch_options' => $this->employee_branch_options(),
			'default_branch' => $this->default_employee_branch(),
			'weekly_day_off_options' => $this->weekly_day_off_options(),
			'default_weekly_day_off' => $this->default_weekly_day_off(),
			'can_view_log_data' => $can_view_log_data,
			'can_manage_accounts' => $can_manage_accounts,
			'can_sync_sheet_accounts' => $can_sync_sheet_accounts,
			'can_manage_feature_accounts' => $can_super_admin_manage,
			'admin_feature_catalog' => $this->admin_feature_permission_catalog(),
			'admin_feature_accounts' => $this->build_manageable_admin_feature_account_options(),
			'privileged_password_targets' => $this->build_privileged_password_target_options(),
			'account_notice_success' => (string) $this->session->flashdata('account_notice_success'),
			'account_notice_error' => (string) $this->session->flashdata('account_notice_error')
		);
		$this->load->view('home/index', $data);
	}

	public function cara_pakai()
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

		$username = (string) $this->session->userdata('absen_username');
		$display_name = (string) $this->session->userdata('absen_display_name');
		$dashboard_navbar_title = $this->dashboard_navbar_title($username);
		$data = array(
			'title' => 'Cara Pakai '.$dashboard_navbar_title,
			'username' => $display_name !== '' ? $display_name : ($username !== '' ? $username : 'Admin'),
			'dashboard_navbar_title' => $dashboard_navbar_title,
			'can_view_log_data' => $this->can_view_log_data_feature()
		);

		$this->load->view('home/admin_guide', $data);
	}

	public function log_data()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if (!$this->can_view_log_data_feature())
		{
			redirect('home');
			return;
		}

		$username = (string) $this->session->userdata('absen_username');
		$display_name = (string) $this->session->userdata('absen_display_name');
		$dashboard_navbar_title = $this->dashboard_navbar_title($username);
		$per_page = 20;
		$page_raw = (int) $this->input->get('page', TRUE);
		$current_page = $page_raw > 0 ? $page_raw : 1;
		$all_logs = $this->load_conflict_logs(0);
		$total_logs = count($all_logs);
		$total_pages = $total_logs > 0 ? (int) ceil($total_logs / $per_page) : 1;
		if ($current_page > $total_pages)
		{
			$current_page = $total_pages;
		}
		if ($current_page < 1)
		{
			$current_page = 1;
		}
		$offset = ($current_page - 1) * $per_page;
		$logs_page = array_slice($all_logs, $offset, $per_page);
		$data = array(
			'title' => 'Log Data Aktivitas',
			'username' => $display_name !== '' ? $display_name : ($username !== '' ? $username : 'Admin'),
			'dashboard_navbar_title' => $dashboard_navbar_title,
			'logs' => $logs_page,
			'log_notice_success' => (string) $this->session->flashdata('log_notice_success'),
			'log_notice_error' => (string) $this->session->flashdata('log_notice_error'),
			'pagination' => array(
				'per_page' => $per_page,
				'current_page' => $current_page,
				'total_logs' => $total_logs,
				'total_pages' => $total_pages
			)
		);

		$this->load->view('home/log_data', $data);
	}

	public function rollback_log_entry()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if (!$this->can_view_log_data_feature())
		{
			redirect('home');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home/log_data');
			return;
		}

		$entry_id = trim((string) $this->input->post('entry_id', TRUE));
		$page = (int) $this->input->post('page', TRUE);
		if ($page <= 0)
		{
			$page = 1;
		}
		$redirect_url = 'home/log_data'.($page > 1 ? '?page='.$page : '');

		if ($entry_id === '')
		{
			$this->session->set_flashdata('log_notice_error', 'Entry log rollback tidak valid.');
			redirect($redirect_url);
			return;
		}

		$logs = $this->load_conflict_logs(0);
		$entry_index = $this->find_log_entry_index_by_id($logs, $entry_id);
		if ($entry_index < 0)
		{
			$this->session->set_flashdata('log_notice_error', 'Entry log tidak ditemukan atau sudah terhapus.');
			redirect($redirect_url);
			return;
		}

		$entry = isset($logs[$entry_index]) && is_array($logs[$entry_index]) ? $logs[$entry_index] : array();
		$can_rollback = $this->can_rollback_log_entry($entry);
		if ($can_rollback !== TRUE)
		{
			$this->session->set_flashdata('log_notice_error', 'Entry log ini tidak mendukung rollback aman.');
			redirect($redirect_url);
			return;
		}

		$rollback_status = strtolower(trim((string) (isset($entry['rollback_status']) ? $entry['rollback_status'] : '')));
		if ($rollback_status === 'rolled_back')
		{
			$this->session->set_flashdata('log_notice_error', 'Entry log ini sudah pernah di-rollback.');
			redirect($redirect_url);
			return;
		}

		$rollback_note = '';
		$rollback_result = $this->rollback_log_entry_data($entry, $rollback_note);
		if ($rollback_result !== TRUE)
		{
			$this->session->set_flashdata('log_notice_error', $rollback_note !== '' ? $rollback_note : 'Rollback gagal diproses.');
			redirect($redirect_url);
			return;
		}

		$actor = $this->current_actor_username();
		if ($actor === '')
		{
			$actor = 'system';
		}
		$logs[$entry_index]['rollback_status'] = 'rolled_back';
		$logs[$entry_index]['rolled_back_by'] = $actor;
		$logs[$entry_index]['rolled_back_at'] = date('Y-m-d H:i:s');
		$logs[$entry_index]['rollback_note'] = $rollback_note;
		$save_log_state = $this->save_conflict_logs($logs);
		if ($save_log_state !== TRUE)
		{
			$this->session->set_flashdata('log_notice_error', 'Rollback data berhasil, tetapi update status log gagal disimpan.');
			redirect($redirect_url);
			return;
		}

		$entry_action = isset($entry['action']) ? trim((string) $entry['action']) : '';
		$entry_source = isset($entry['source']) ? trim((string) $entry['source']) : '';
		$entry_username = isset($entry['username']) ? trim((string) $entry['username']) : '';
		$entry_display_name = isset($entry['display_name']) ? trim((string) $entry['display_name']) : '';
		$this->log_activity_event(
			'rollback_log_entry',
			'log_data',
			$entry_username,
			$entry_display_name,
			'Rollback log '.$entry_id.' ('.$entry_source.' / '.$entry_action.').',
			array(
				'target_id' => $entry_id,
				'field' => isset($entry['field']) ? (string) $entry['field'] : '',
				'field_label' => isset($entry['field_label']) ? (string) $entry['field_label'] : '',
				'old_value' => isset($entry['new_value']) ? (string) $entry['new_value'] : '',
				'new_value' => isset($entry['old_value']) ? (string) $entry['old_value'] : ''
			)
		);

		$success_message = 'Rollback berhasil: '.$rollback_note;
		$this->session->set_flashdata('log_notice_success', $success_message);
		redirect($redirect_url);
	}

	public function force_change_password()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		$password_change_required = ((int) $this->session->userdata('absen_password_change_required')) === 1;
		if (!$password_change_required)
		{
			redirect('home');
			return;
		}

		$username = (string) $this->session->userdata('absen_username');
		$display_name = (string) $this->session->userdata('absen_display_name');
		$data = array(
			'title' => 'Ganti Password Pertama Kali',
			'username' => $display_name !== '' ? $display_name : ($username !== '' ? $username : 'user'),
			'password_notice_success' => (string) $this->session->flashdata('password_notice_success'),
			'password_notice_error' => (string) $this->session->flashdata('password_notice_error')
		);
		$this->load->view('home/force_change_password', $data);
	}

	public function submit_force_change_password()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home/force_change_password');
			return;
		}

		$password_change_required = ((int) $this->session->userdata('absen_password_change_required')) === 1;
		if (!$password_change_required)
		{
			redirect('home');
			return;
		}

		$username_key = strtolower(trim((string) $this->session->userdata('absen_username')));
		$current_password = trim((string) $this->input->post('current_password', FALSE));
		$new_password = trim((string) $this->input->post('new_password', FALSE));
		$confirm_password = trim((string) $this->input->post('confirm_password', FALSE));
		if ($username_key === '')
		{
			$this->session->sess_destroy();
			redirect('login');
			return;
		}

		if ($current_password === '')
		{
			$this->session->set_flashdata('password_notice_error', 'Password saat ini wajib diisi.');
			redirect('home/force_change_password');
			return;
		}
		if ($new_password === '' || strlen($new_password) < 3)
		{
			$this->session->set_flashdata('password_notice_error', 'Password baru minimal 3 karakter.');
			redirect('home/force_change_password');
			return;
		}
		if ($new_password !== $confirm_password)
		{
			$this->session->set_flashdata('password_notice_error', 'Konfirmasi password baru tidak sama.');
			redirect('home/force_change_password');
			return;
		}
		if ($new_password === $current_password)
		{
			$this->session->set_flashdata('password_notice_error', 'Password baru harus berbeda dari password saat ini.');
			redirect('home/force_change_password');
			return;
		}

		$account_book = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (!isset($account_book[$username_key]) || !is_array($account_book[$username_key]))
		{
			$this->session->sess_destroy();
			redirect('login');
			return;
		}

		$needs_upgrade = FALSE;
		$is_current_password_valid = function_exists('absen_verify_account_password')
			? absen_verify_account_password($account_book[$username_key], $current_password, $needs_upgrade)
			: ((isset($account_book[$username_key]['password']) ? (string) $account_book[$username_key]['password'] : '') === $current_password);
		if ($is_current_password_valid !== TRUE)
		{
			$this->session->set_flashdata('password_notice_error', 'Password saat ini tidak sesuai.');
			redirect('home/force_change_password');
			return;
		}

		if (!function_exists('absen_account_set_password') || !absen_account_set_password($account_book[$username_key], $new_password, FALSE))
		{
			$this->session->set_flashdata('password_notice_error', 'Gagal memproses password baru. Coba lagi.');
			redirect('home/force_change_password');
			return;
		}

		$saved = function_exists('absen_save_account_book')
			? absen_save_account_book($account_book)
			: FALSE;
		if (!$saved)
		{
			$this->session->set_flashdata('password_notice_error', 'Gagal menyimpan password baru. Coba lagi.');
			redirect('home/force_change_password');
			return;
		}

		$this->session->set_userdata('absen_password_change_required', 0);
		$this->session->set_userdata('absen_last_activity_at', time());
		$this->log_activity_event(
			'force_change_password',
			'account_data',
			$username_key,
			isset($account_book[$username_key]['display_name']) ? (string) $account_book[$username_key]['display_name'] : $username_key,
			'Pengguna mengganti password wajib saat login pertama.'
		);
		$this->session->set_flashdata('password_notice_success', 'Password berhasil diperbarui.');
		redirect('home');
	}

	public function create_employee_account()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if (!$this->can_manage_employee_accounts())
		{
			$this->session->set_flashdata('account_notice_error', 'Akun login kamu belum punya izin untuk kelola akun karyawan.');
			redirect('home');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home');
			return;
		}

		$username_key = $this->normalize_username_key($this->input->post('new_username', TRUE));
		$display_name_input = trim((string) $this->input->post('new_display_name', TRUE));
		$display_name_input = preg_replace('/\s+/', ' ', $display_name_input);
		$password = trim((string) $this->input->post('new_password', FALSE));
		$branch = $this->resolve_employee_branch($this->input->post('new_branch', TRUE));
		$phone = $this->normalize_phone_number($this->input->post('new_phone', TRUE));
		$shift_key = strtolower(trim((string) $this->input->post('new_shift', TRUE)));
		$cross_branch_enabled = $this->resolve_cross_branch_enabled_value($this->input->post('new_cross_branch_enabled', TRUE));
		$salary_raw = trim((string) $this->input->post('new_salary_monthly', TRUE));
		$salary_digits = preg_replace('/\D+/', '', $salary_raw);
		$salary_monthly = $salary_digits === '' ? 0 : (int) $salary_digits;
		$job_title = trim((string) $this->input->post('new_job_title', TRUE));
		$address = trim((string) $this->input->post('new_address', TRUE));
		$coordinate_input = trim((string) $this->input->post('new_coordinate_point', TRUE));
		$coordinate_point = $this->normalize_coordinate_point($coordinate_input);
		$weekly_day_off = $this->resolve_employee_weekly_day_off($this->input->post('new_weekly_day_off', TRUE));

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

		if ($this->is_reserved_system_username($username_key))
		{
			$this->session->set_flashdata('account_notice_error', 'Username sistem (admin/developer/bos) tidak boleh dipakai untuk karyawan.');
			redirect('home');
			return;
		}

		if ($display_name_input === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Nama lengkap karyawan wajib diisi.');
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

		if ($coordinate_point === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Titik koordinat wajib diisi dengan format latitude, longitude.');
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
		$display_name = $display_name_input;

		$month_policy = $this->calculate_month_work_policy(date('Y-m-d'), $weekly_day_off);
		$work_days = isset($month_policy['work_days']) ? (int) $month_policy['work_days'] : self::WORK_DAYS_DEFAULT;
		if ($work_days <= 0)
		{
			$work_days = self::WORK_DAYS_DEFAULT;
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

		$profile_photo_upload = $this->upload_employee_profile_photo('new_profile_photo', $username_key);
		if (!isset($profile_photo_upload['success']) || $profile_photo_upload['success'] !== TRUE)
		{
			$message = isset($profile_photo_upload['message']) && trim((string) $profile_photo_upload['message']) !== ''
				? (string) $profile_photo_upload['message']
				: 'Upload PP karyawan gagal.';
			$this->session->set_flashdata('account_notice_error', $message);
			redirect('home');
			return;
		}
		$profile_photo_path = isset($profile_photo_upload['relative_path']) ? trim((string) $profile_photo_upload['relative_path']) : '';
		if ($profile_photo_path === '')
		{
			$this->session->set_flashdata('account_notice_error', 'File PP karyawan tidak valid.');
			redirect('home');
			return;
		}

		$new_account_row = array(
			'role' => 'user',
			'display_name' => $display_name,
			'branch' => $branch,
			'cross_branch_enabled' => $cross_branch_enabled,
			'phone' => $phone,
			'shift_name' => (string) $shift_profiles[$shift_key]['shift_name'],
			'shift_time' => (string) $shift_profiles[$shift_key]['shift_time'],
			'salary_tier' => $salary_tier,
			'salary_monthly' => $salary_monthly,
			'work_days' => (int) $work_days,
			'weekly_day_off' => (int) $weekly_day_off,
			'job_title' => $job_title,
			'address' => $address,
			'profile_photo' => $profile_photo_path,
			'coordinate_point' => $coordinate_point,
			'employee_status' => 'Aktif',
			'force_password_change' => 1,
			'sheet_row' => 0,
			'sheet_sync_source' => 'web',
			'sheet_last_sync_at' => ''
		);
		if (!function_exists('absen_account_set_password') || !absen_account_set_password($new_account_row, $password, TRUE))
		{
			$this->session->set_flashdata('account_notice_error', 'Gagal memproses password akun baru.');
			redirect('home');
			return;
		}
		$account_book[$username_key] = $new_account_row;

		$saved = function_exists('absen_save_account_book')
			? absen_save_account_book($account_book)
			: FALSE;
		if (!$saved)
		{
			$this->delete_local_uploaded_file($profile_photo_path);
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
		$create_note = 'Buat akun karyawan baru.';
		$create_note .= ' Jabatan: '.$job_title.'.';
		$create_note .= ' Cabang: '.$branch.'.';
		$create_note .= ' Lintas cabang: '.($cross_branch_enabled === 1 ? 'Iya' : 'Tidak').'.';
		$this->log_activity_event(
			'create_account',
			'account_data',
			$username_key,
			$display_name,
			$create_note,
			array(
				'new_value' => 'username='.$username_key.', salary='.$salary_monthly.', work_days='.(int) $work_days.', coordinate='.$coordinate_point
			)
		);

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

		if (!$this->can_sync_sheet_accounts_feature())
		{
			$this->session->set_flashdata('account_notice_error', 'Akun login kamu belum punya izin untuk sync akun dari sheet.');
			redirect('home');
			return;
		}

		if (!isset($this->absen_sheet_sync))
		{
			$this->session->set_flashdata('account_notice_error', 'Library sinkronisasi spreadsheet belum aktif.');
			redirect('home');
			return;
		}

		$actor = strtolower(trim((string) $this->session->userdata('absen_username')));
		if ($actor === '')
		{
			$actor = 'admin';
		}
		$actor_context = $this->build_sync_actor_context($actor);
		$result = $this->absen_sheet_sync->sync_accounts_from_sheet(array(
			'force' => TRUE,
			'actor' => $actor,
			'actor_context' => $actor_context
		));
		if (isset($result['success']) && $result['success'] === TRUE)
		{
			$created = isset($result['created']) ? (int) $result['created'] : 0;
			$updated = isset($result['updated']) ? (int) $result['updated'] : 0;
			$this->session->set_flashdata('account_notice_success', 'Sync spreadsheet selesai. Buat baru: '.$created.', update: '.$updated.'.');
			$this->log_activity_event(
				'sync_accounts_from_sheet',
				'spreadsheet_data',
				'',
				'',
				'Menjalankan Sync Akun dari Sheet.',
				array(
					'sheet' => 'DATABASE',
					'new_value' => 'created='.$created.', updated='.$updated
				)
			);
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

		$actor = strtolower(trim((string) $this->session->userdata('absen_username')));
		if ($actor === '')
		{
			$actor = 'admin';
		}
		$actor_context = $this->build_sync_actor_context($actor);
		$branch_scope = $this->is_branch_scoped_admin() ? $this->current_actor_branch() : '';
		$result = $this->absen_sheet_sync->sync_attendance_from_sheet(array(
			'force' => TRUE,
			'overwrite_web_source' => FALSE,
			'prune_missing_attendance' => FALSE,
			'actor' => $actor,
			'actor_context' => $actor_context,
			'branch_scope' => $branch_scope
		));
		if (isset($result['success']) && $result['success'] === TRUE)
		{
			$this->clear_admin_dashboard_live_summary_cache();
			$created_accounts = isset($result['created_accounts']) ? (int) $result['created_accounts'] : 0;
			$updated_accounts = isset($result['updated_accounts']) ? (int) $result['updated_accounts'] : 0;
			$created_attendance = isset($result['created_attendance']) ? (int) $result['created_attendance'] : 0;
			$updated_attendance = isset($result['updated_attendance']) ? (int) $result['updated_attendance'] : 0;
			$pruned_attendance = isset($result['pruned_attendance']) ? (int) $result['pruned_attendance'] : 0;
			$skipped_rows = isset($result['skipped_rows']) ? (int) $result['skipped_rows'] : 0;
			$backfilled_phone_cells = isset($result['backfilled_phone_cells']) ? (int) $result['backfilled_phone_cells'] : 0;
			$backfilled_branch_cells = isset($result['backfilled_branch_cells']) ? (int) $result['backfilled_branch_cells'] : 0;
			$phone_backfill_error = isset($result['phone_backfill_error']) ? trim((string) $result['phone_backfill_error']) : '';
			$branch_backfill_error = isset($result['branch_backfill_error']) ? trim((string) $result['branch_backfill_error']) : '';
			$this->session->set_flashdata(
				'account_notice_success',
				'Sync Data Absen selesai. Akun baru: '.$created_accounts.', akun update: '.$updated_accounts.
				', absen baru: '.$created_attendance.', absen update: '.$updated_attendance.
				', absen stale terhapus: '.$pruned_attendance.
				', baris dilewati: '.$skipped_rows.
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
			$this->log_activity_event(
				'sync_attendance_from_sheet',
				'spreadsheet_data',
				'',
				'',
				'Menjalankan Sync Data Absen dari Sheet.',
				array(
					'sheet' => 'Data Absen',
					'new_value' => 'account_created='.$created_accounts.', account_updated='.$updated_accounts.', attendance_created='.$created_attendance.', attendance_updated='.$updated_attendance
				)
			);
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

		$actor = strtolower(trim((string) $this->session->userdata('absen_username')));
		if ($actor === '')
		{
			$actor = 'admin';
		}
		$actor_context = $this->build_sync_actor_context($actor);
		$branch_scope = $this->is_branch_scoped_admin() ? $this->current_actor_branch() : '';
		$result = $this->absen_sheet_sync->sync_attendance_to_sheet(array(
			'force' => TRUE,
			'actor' => $actor,
			'actor_context' => $actor_context,
			'branch_scope' => $branch_scope
		));
		if (isset($result['success']) && $result['success'] === TRUE)
		{
			$month = isset($result['month']) ? (string) $result['month'] : date('Y-m');
			$processed_users = isset($result['processed_users']) ? (int) $result['processed_users'] : 0;
			$updated_rows = isset($result['updated_rows']) ? (int) $result['updated_rows'] : 0;
			$appended_rows = isset($result['appended_rows']) ? (int) $result['appended_rows'] : 0;
			$pruned_rows = isset($result['pruned_rows']) ? (int) $result['pruned_rows'] : 0;
			$prune_error = isset($result['prune_error']) ? trim((string) $result['prune_error']) : '';
			$this->session->set_flashdata(
				'account_notice_success',
				'Sync data web -> Data Absen selesai. Bulan: '.$month.', user diproses: '.$processed_users.', row update: '.$updated_rows.', row baru: '.$appended_rows.', row stale terhapus: '.$pruned_rows.'.'
			);
			if ($prune_error !== '')
			{
				$this->session->set_flashdata('account_notice_error', 'Data web berhasil sync, tapi hapus row stale gagal: '.$prune_error);
			}
			$this->log_activity_event(
				'sync_web_to_sheet',
				'web_data',
				'',
				'',
				'Menjalankan Sync Data Web ke Sheet.',
				array(
					'sheet' => 'Data Absen',
					'new_value' => 'month='.$month.', processed='.$processed_users.', updated='.$updated_rows.', appended='.$appended_rows.', pruned='.$pruned_rows
				)
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

		$result = $this->absen_sheet_sync->sync_accounts_from_sheet(array(
			'force' => TRUE,
			'actor' => 'cli',
			'actor_context' => $this->build_sync_actor_context('cli', TRUE)
		));
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

		$result = $this->absen_sheet_sync->sync_attendance_from_sheet(array(
			'force' => TRUE,
			'overwrite_web_source' => FALSE,
			'prune_missing_attendance' => FALSE,
			'actor' => 'cli',
			'actor_context' => $this->build_sync_actor_context('cli', TRUE)
		));
		if (isset($result['success']) && $result['success'] === TRUE)
		{
			$processed = isset($result['processed']) ? (int) $result['processed'] : 0;
			$created_accounts = isset($result['created_accounts']) ? (int) $result['created_accounts'] : 0;
			$updated_accounts = isset($result['updated_accounts']) ? (int) $result['updated_accounts'] : 0;
			$created_attendance = isset($result['created_attendance']) ? (int) $result['created_attendance'] : 0;
			$updated_attendance = isset($result['updated_attendance']) ? (int) $result['updated_attendance'] : 0;
			$pruned_attendance = isset($result['pruned_attendance']) ? (int) $result['pruned_attendance'] : 0;
			$skipped_rows = isset($result['skipped_rows']) ? (int) $result['skipped_rows'] : 0;
			$backfilled_phone_cells = isset($result['backfilled_phone_cells']) ? (int) $result['backfilled_phone_cells'] : 0;
			$phone_backfill_error = isset($result['phone_backfill_error']) ? trim((string) $result['phone_backfill_error']) : '';
			echo "Sync Data Absen OK. processed=".$processed.
				", account_created=".$created_accounts.
				", account_updated=".$updated_accounts.
				", attendance_created=".$created_attendance.
				", attendance_updated=".$updated_attendance.
				", attendance_pruned=".$pruned_attendance.
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

		$result = $this->absen_sheet_sync->sync_attendance_to_sheet(array(
			'force' => TRUE,
			'actor' => 'cli',
			'actor_context' => $this->build_sync_actor_context('cli', TRUE)
		));
		if (isset($result['success']) && $result['success'] === TRUE)
		{
			$month = isset($result['month']) ? (string) $result['month'] : date('Y-m');
			$processed_users = isset($result['processed_users']) ? (int) $result['processed_users'] : 0;
			$updated_rows = isset($result['updated_rows']) ? (int) $result['updated_rows'] : 0;
			$appended_rows = isset($result['appended_rows']) ? (int) $result['appended_rows'] : 0;
			$skipped_users = isset($result['skipped_users']) ? (int) $result['skipped_users'] : 0;
			$pruned_rows = isset($result['pruned_rows']) ? (int) $result['pruned_rows'] : 0;
			$prune_error = isset($result['prune_error']) ? trim((string) $result['prune_error']) : '';
			echo "Sync web->Data Absen OK. month=".$month.
				", users=".$processed_users.
				", updated=".$updated_rows.
				", appended=".$appended_rows.
				", skipped=".$skipped_users.
				", pruned=".$pruned_rows."\n";
			if ($prune_error !== '')
			{
				echo "Peringatan: hapus row stale gagal: ".$prune_error."\n";
			}
			return;
		}

		$message = isset($result['message']) && trim((string) $result['message']) !== ''
			? (string) $result['message']
			: 'Sinkronisasi web -> Data Absen gagal.';
		echo "Sync web->Data Absen gagal: ".$message."\n";
	}

	public function sync_web_attendance_to_sheet_auto_cli()
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

		$result = $this->absen_sheet_sync->sync_attendance_to_sheet(array(
			'force' => FALSE,
			'actor' => 'cron',
			'actor_context' => $this->build_sync_actor_context('cron', TRUE)
		));
		if (isset($result['success']) && $result['success'] === TRUE)
		{
			if (isset($result['skipped']) && $result['skipped'] === TRUE)
			{
				$message = isset($result['message']) && trim((string) $result['message']) !== ''
					? (string) $result['message']
					: 'Auto sync web->Data Absen dilewati.';
				echo "Auto sync web->Data Absen dilewati: ".$message."\n";
				return;
			}

			$month = isset($result['month']) ? (string) $result['month'] : date('Y-m');
			$processed_users = isset($result['processed_users']) ? (int) $result['processed_users'] : 0;
			$updated_rows = isset($result['updated_rows']) ? (int) $result['updated_rows'] : 0;
			$appended_rows = isset($result['appended_rows']) ? (int) $result['appended_rows'] : 0;
			$skipped_users = isset($result['skipped_users']) ? (int) $result['skipped_users'] : 0;
			$pruned_rows = isset($result['pruned_rows']) ? (int) $result['pruned_rows'] : 0;
			$prune_error = isset($result['prune_error']) ? trim((string) $result['prune_error']) : '';
			echo "Auto sync web->Data Absen OK. month=".$month.
				", users=".$processed_users.
				", updated=".$updated_rows.
				", appended=".$appended_rows.
				", skipped=".$skipped_users.
				", pruned=".$pruned_rows."\n";
			if ($prune_error !== '')
			{
				echo "Peringatan: hapus row stale gagal: ".$prune_error."\n";
			}
			return;
		}

		$message = isset($result['message']) && trim((string) $result['message']) !== ''
			? (string) $result['message']
			: 'Auto sync web -> Data Absen gagal.';
		echo "Auto sync web->Data Absen gagal: ".$message."\n";
	}

	public function attendance_reminder_auto_cli($slot_override = '')
	{
		if (!$this->input->is_cli_request())
		{
			show_404();
			return;
		}

		$slot = $this->resolve_attendance_reminder_slot($slot_override);
		if ($slot === '')
		{
			$allowed_slots = self::ATTENDANCE_REMINDER_SLOTS;
			echo "Reminder absensi dilewati. Slot aktif: ".implode(', ', $allowed_slots)." WIB.\n";
			return;
		}

		$date_key = date('Y-m-d');
		$state = $this->load_attendance_reminder_state();
		$slot_key = $date_key.'|'.$slot;
		if (in_array($slot_key, $state['sent_slots'], TRUE))
		{
			echo "Reminder absensi dilewati. Slot ".$slot." sudah pernah terkirim hari ini.\n";
			return;
		}

		$group_target_error = '';
		$group_target = $this->resolve_attendance_reminder_group_target($group_target_error);
		if ($group_target === '')
		{
			echo "Reminder absensi gagal: ".$group_target_error."\n";
			return;
		}

		$payload = $this->build_attendance_reminder_payload($date_key);
		$group_message = $this->build_attendance_reminder_group_message($payload, $slot);
		$group_result = $this->send_whatsapp_notification($group_target, $group_message);
		if (!isset($group_result['success']) || $group_result['success'] !== TRUE)
		{
			$message = isset($group_result['message']) ? (string) $group_result['message'] : 'Pengiriman ke grup gagal.';
			echo "Reminder absensi gagal kirim ke grup: ".$message."\n";
			return;
		}

		$state['sent_slots'][] = $slot_key;
		$state['sent_slots'] = $this->normalize_attendance_reminder_key_list($state['sent_slots']);
		$direct_dm_enabled = $this->attendance_reminder_direct_dm_enabled();
		$dm_sent = 0;
		$dm_failed = 0;
		$dm_skipped = 0;
		if ($direct_dm_enabled)
		{
			$employee_rows = isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : array();
			for ($i = 0; $i < count($employee_rows); $i += 1)
			{
				$row = $employee_rows[$i];
				$row_username = isset($row['username']) ? (string) $row['username'] : '';
				$row_display_name = isset($row['display_name']) ? (string) $row['display_name'] : $row_username;
				$is_present = isset($row['is_present']) && $row['is_present'] === TRUE;
				$is_leave_today = isset($row['is_leave_today']) && $row['is_leave_today'] === TRUE;
				if ($is_present || $is_leave_today)
				{
					continue;
				}
				$dm_key = $slot_key.'|'.strtolower(trim((string) $row_username));
				if (in_array($dm_key, $state['direct_sent'], TRUE))
				{
					$dm_skipped += 1;
					continue;
				}

				$row_phone = isset($row['phone']) ? (string) $row['phone'] : '';
				$direct_message = $this->build_attendance_reminder_direct_message($row_display_name, $slot);
				$direct_result = $this->send_whatsapp_notification($row_phone, $direct_message);
				if (isset($direct_result['success']) && $direct_result['success'] === TRUE)
				{
					$state['direct_sent'][] = $dm_key;
					$dm_sent += 1;
				}
				else
				{
					$dm_failed += 1;
				}
			}
		}

		$state['direct_sent'] = $this->normalize_attendance_reminder_key_list($state['direct_sent']);
		$this->save_attendance_reminder_state($state);
		echo "Reminder absensi terkirim. slot=".$slot.
			", hadir=".(int) (isset($payload['present_count']) ? $payload['present_count'] : 0).
			", belum=".(int) (isset($payload['missing_count']) ? $payload['missing_count'] : 0).
			", alpha=".(int) (isset($payload['alpha_count']) ? $payload['alpha_count'] : 0).
			", dm_enabled=".($direct_dm_enabled ? '1' : '0').
			", dm_sent=".$dm_sent.
			", dm_failed=".$dm_failed.
			", dm_skipped=".$dm_skipped."\n";
	}

	public function reset_attendance_cli()
	{
		if (!$this->input->is_cli_request())
		{
			show_404();
			return;
		}

		$before_rows = $this->load_attendance_records();
		$before_count = is_array($before_rows) ? count($before_rows) : 0;

		$this->save_attendance_records(array());

		$after_rows = $this->load_attendance_records();
		$after_count = is_array($after_rows) ? count($after_rows) : 0;
		if ($after_count > 0)
		{
			echo "Reset data absen gagal. remaining=".$after_count."\n";
			return;
		}

		echo "Reset data absen OK. removed=".$before_count.", remaining=0\n";
	}

	public function reset_attendance_columns_cli($month = '')
	{
		if (!$this->input->is_cli_request())
		{
			show_404();
			return;
		}

		$month = trim((string) $month);
		if ($month === '')
		{
			$month = date('Y-m');
		}
		if (!preg_match('/^\d{4}\-\d{2}$/', $month))
		{
			echo "Format bulan tidak valid. Gunakan YYYY-MM.\n";
			return;
		}

		$records = $this->load_attendance_records();
		if (!is_array($records) || empty($records))
		{
			echo "Data absen kosong. Tidak ada yang direset.\n";
			return;
		}

		$reset_values = array(
			'check_in_time' => '',
			'check_in_late' => '00:00:00',
			'check_out_time' => '',
			'work_duration' => '',
			'jenis_masuk' => '',
			'jenis_pulang' => '',
			'check_in_photo' => '',
			'check_out_photo' => '',
			'sheet_sudah_berapa_absen' => 0,
			'sheet_hari_efektif' => 0,
			'sheet_total_hadir' => 0,
				'sheet_total_telat_1_30' => 0,
				'sheet_total_telat_31_60' => 0,
				'sheet_total_telat_1_3' => 0,
				'sheet_total_telat_gt_4' => 0,
				'sheet_total_izin' => 0,
				'sheet_total_cuti' => 0,
				'sheet_total_izin_cuti' => 0,
				'sheet_total_alpha' => 0,
			'late_reason' => '',
			'alasan_izin_cuti' => '',
			'alasan_alpha' => '',
			'salary_cut_amount' => '0',
			'salary_cut_rule' => 'Tidak telat',
			'salary_cut_category' => ''
		);

		$matched_rows = 0;
		$updated_rows = 0;
		$now = date('Y-m-d H:i:s');
		for ($i = 0; $i < count($records); $i += 1)
		{
			if (!isset($records[$i]) || !is_array($records[$i]))
			{
				continue;
			}

			$row_date = isset($records[$i]['date']) ? trim((string) $records[$i]['date']) : '';
			if (!$this->is_valid_date_format($row_date) || substr($row_date, 0, 7) !== $month)
			{
				continue;
			}

			$matched_rows += 1;
			$changed = FALSE;
			foreach ($reset_values as $field => $target_value)
			{
				$current_value = isset($records[$i][$field]) ? (string) $records[$i][$field] : '';
				$next_value = (string) $target_value;
				if ($current_value !== $next_value)
				{
					$records[$i][$field] = $target_value;
					$changed = TRUE;
				}
			}

			if ($changed)
			{
				$records[$i]['updated_at'] = $now;
				$updated_rows += 1;
			}
		}

		if ($matched_rows <= 0)
		{
			echo "Tidak ada data absen untuk bulan ".$month.".\n";
			return;
		}

		if ($updated_rows > 0)
		{
			$this->save_attendance_records($records);
		}

		echo "Reset kolom absen OK. month=".$month.", matched=".$matched_rows.", updated=".$updated_rows."\n";
	}

	public function optimize_profile_photos_cli($mode = '')
	{
		if (!$this->input->is_cli_request())
		{
			show_404();
			return;
		}

		$mode_value = strtolower(trim((string) $mode));
		$is_dry_run = $mode_value === 'dry-run' || $mode_value === '--dry-run' || $mode_value === 'preview' || $mode_value === '--preview';
		if (!$this->can_process_profile_photo_image())
		{
			echo "Optimasi foto profil dilewati: extension GD belum aktif di server.\n";
			return;
		}

		if (!function_exists('absen_load_account_book') || !function_exists('absen_save_account_book'))
		{
			echo "Helper akun tidak tersedia. Pastikan helper absen_account_store aktif.\n";
			return;
		}

		$account_book = absen_load_account_book();
		if (!is_array($account_book) || empty($account_book))
		{
			echo "Data akun kosong. Tidak ada foto profil yang diproses.\n";
			return;
		}

		$file_map = array();
		foreach ($account_book as $username_key => $row)
		{
			if (!is_array($row))
			{
				continue;
			}

			$profile_photo = isset($row['profile_photo']) ? trim((string) $row['profile_photo']) : '';
			if ($profile_photo === '')
			{
				continue;
			}
			if (strpos($profile_photo, 'data:') === 0 || preg_match('/^https?:\/\//i', $profile_photo) === 1)
			{
				continue;
			}

			$relative_path = '/'.ltrim(str_replace('\\', '/', $profile_photo), '/');
			if (strpos($relative_path, '/uploads/profile_photo/') !== 0)
			{
				continue;
			}

			if (!isset($file_map[$relative_path]))
			{
				$file_map[$relative_path] = array();
			}
			$file_map[$relative_path][] = (string) $username_key;
		}

		$total_files = count($file_map);
		if ($total_files <= 0)
		{
			echo "Tidak ada foto profil local di uploads/profile_photo untuk diproses.\n";
			return;
		}

		$processed_files = 0;
		$missing_files = 0;
		$optimized_files = 0;
		$converted_files = 0;
		$skipped_files = 0;
		$thumb_created = 0;
		$thumb_refreshed = 0;
		$updated_accounts = 0;
		$bytes_before = 0;
		$bytes_after = 0;
		$has_account_updates = FALSE;
		$fcp_root = rtrim(str_replace('\\', '/', (string) FCPATH), '/');
		$format_size = function ($size_bytes) {
			$bytes = (int) $size_bytes;
			if ($bytes < 1024)
			{
				return $bytes.' B';
			}
			if ($bytes < (1024 * 1024))
			{
				return number_format($bytes / 1024, 1, '.', '').' KB';
			}
			return number_format($bytes / (1024 * 1024), 2, '.', '').' MB';
		};

		foreach ($file_map as $relative_path => $usernames)
		{
			$absolute_path = rtrim((string) FCPATH, '/\\').DIRECTORY_SEPARATOR.
				str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, ltrim((string) $relative_path, '/\\'));
			if (!is_file($absolute_path))
			{
				$missing_files += 1;
				continue;
			}

			$processed_files += 1;
			$before_size = (int) @filesize($absolute_path);
			$bytes_before += max(0, $before_size);
			if ($is_dry_run)
			{
				$bytes_after += max(0, $before_size);
				continue;
			}

			$file_ext = strtolower(pathinfo($absolute_path, PATHINFO_EXTENSION));
			$final_path = $absolute_path;
			$optimized = FALSE;
			$optimize_result = $this->optimize_profile_photo_image(
				$absolute_path,
				$file_ext,
				self::PROFILE_PHOTO_MAX_WIDTH,
				self::PROFILE_PHOTO_MAX_HEIGHT,
				self::PROFILE_PHOTO_JPEG_QUALITY
			);
			if (isset($optimize_result['success']) && $optimize_result['success'] === TRUE &&
				isset($optimize_result['output_path']) && is_file((string) $optimize_result['output_path']))
			{
				$optimized = TRUE;
				$optimized_files += 1;
				$final_path = (string) $optimize_result['output_path'];
				if (str_replace('\\', '/', $final_path) !== str_replace('\\', '/', $absolute_path))
				{
					$converted_files += 1;
				}
			}

			$final_info = pathinfo($final_path);
			$final_dir = isset($final_info['dirname']) ? (string) $final_info['dirname'] : '';
			$final_base_name = isset($final_info['filename']) ? (string) $final_info['filename'] : '';
			$thumb_path = ($final_dir !== '' && $final_base_name !== '')
				? $final_dir.DIRECTORY_SEPARATOR.$final_base_name.'_thumb.jpg'
				: '';
			$thumb_exists_before = $thumb_path !== '' && is_file($thumb_path);
			$thumb_saved = $this->create_profile_photo_thumbnail(
				$final_path,
				self::PROFILE_PHOTO_THUMB_SIZE,
				self::PROFILE_PHOTO_THUMB_JPEG_QUALITY
			);
			if ($thumb_saved)
			{
				if ($thumb_exists_before)
				{
					$thumb_refreshed += 1;
				}
				else
				{
					$thumb_created += 1;
				}
			}
			elseif (!$optimized)
			{
				$skipped_files += 1;
			}

			$after_size = is_file($final_path) ? (int) @filesize($final_path) : 0;
			$bytes_after += max(0, $after_size);

			$final_path_normalized = str_replace('\\', '/', $final_path);
			$new_relative_path = (string) $relative_path;
			if ($fcp_root !== '' && strpos($final_path_normalized, $fcp_root.'/') === 0)
			{
				$new_relative_path = '/'.ltrim(substr($final_path_normalized, strlen($fcp_root) + 1), '/');
			}
			if ($new_relative_path === (string) $relative_path)
			{
				continue;
			}

			$linked_users = is_array($usernames) ? $usernames : array();
			for ($user_index = 0; $user_index < count($linked_users); $user_index += 1)
			{
				$account_key = (string) $linked_users[$user_index];
				if (!isset($account_book[$account_key]) || !is_array($account_book[$account_key]))
				{
					continue;
				}
				$current_profile_photo = isset($account_book[$account_key]['profile_photo'])
					? trim((string) $account_book[$account_key]['profile_photo'])
					: '';
				$current_relative_path = '/'.ltrim(str_replace('\\', '/', $current_profile_photo), '/');
				if ($current_relative_path !== (string) $relative_path)
				{
					continue;
				}

				$account_book[$account_key]['profile_photo'] = $new_relative_path;
				$updated_accounts += 1;
				$has_account_updates = TRUE;
			}
		}

		$save_status = 'skipped';
		if (!$is_dry_run && $has_account_updates)
		{
			$saved = absen_save_account_book($account_book);
			$save_status = $saved ? 'ok' : 'failed';
		}

		$size_saved = max(0, $bytes_before - $bytes_after);
		echo "Optimasi foto profil ".($is_dry_run ? "preview" : "selesai").".\n";
		echo "files=".$total_files.
			", processed=".$processed_files.
			", missing=".$missing_files.
			", optimized=".$optimized_files.
			", converted=".$converted_files.
			", skipped=".$skipped_files.
			", thumb_new=".$thumb_created.
			", thumb_refresh=".$thumb_refreshed.
			", account_updated=".$updated_accounts.
			", account_save=".$save_status."\n";
		echo "size_before=".$format_size($bytes_before).
			", size_after=".$format_size($bytes_after).
			", saved=".$format_size($size_saved)."\n";
		if ($is_dry_run)
		{
			echo "Mode preview aktif: tidak ada file atau akun yang diubah.\n";
		}
		if (!$is_dry_run && $save_status === 'failed')
		{
			echo "Perhatian: update profile_photo di account book gagal disimpan.\n";
		}
	}

	public function storage_status()
	{
		$is_cli = $this->input->is_cli_request();
		if (!$is_cli)
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
		}

		$this->load->helper('absen_data_store');
		$this->load->helper('absen_account_store');

		$storage_config = function_exists('absen_storage_config')
			? absen_storage_config()
			: array();
		$db_storage_enabled = function_exists('absen_db_storage_enabled')
			? absen_db_storage_enabled()
			: FALSE;
		$mirror_json_backup = function_exists('absen_db_storage_mirror_json')
			? absen_db_storage_mirror_json()
			: TRUE;
		$table_name = function_exists('absen_data_store_safe_table_name')
			? absen_data_store_safe_table_name()
			: 'absen_data_store';
		$accounts_store_key = function_exists('absen_accounts_store_key')
			? absen_accounts_store_key()
			: 'accounts_book';
		$db_error_message = function_exists('absen_data_store_last_db_error_message')
			? absen_data_store_last_db_error_message()
			: '';
		$db_env_host = trim((string) getenv('DB_HOST'));
		$db_env_port = trim((string) getenv('DB_PORT'));
		$db_env_user = trim((string) getenv('DB_USER'));
		$db_env_name = trim((string) getenv('DB_NAME'));
		$db_env_pass = trim((string) getenv('DB_PASS'));

		$db = function_exists('absen_data_store_db_instance')
			? absen_data_store_db_instance()
			: NULL;
		$db_connected = is_object($db) && isset($db->conn_id) && $db->conn_id ? TRUE : FALSE;
		$table_ready = FALSE;
		$table_error = '';
		$total_store_rows = 0;
		$last_write_at = '';
		$store_rows = array();

		if ($db_connected && function_exists('absen_data_store_ensure_table'))
		{
			$table_ready = absen_data_store_ensure_table($db) ? TRUE : FALSE;
			if ($table_ready)
			{
				$summary_query = $db->select('COUNT(*) AS total_rows, MAX(updated_at) AS last_write_at')
					->from($table_name)
					->get();
				if ($summary_query)
				{
					$summary_row = $summary_query->row_array();
					$total_store_rows = isset($summary_row['total_rows']) ? (int) $summary_row['total_rows'] : 0;
					$last_write_at = isset($summary_row['last_write_at']) ? trim((string) $summary_row['last_write_at']) : '';
				}

				$list_query = $db->select('store_key, updated_at')
					->from($table_name)
					->order_by('store_key', 'ASC')
					->get();
				if ($list_query)
				{
					$list_rows = $list_query->result_array();
					for ($i = 0; $i < count($list_rows); $i += 1)
					{
						$row = is_array($list_rows[$i]) ? $list_rows[$i] : array();
						$key = isset($row['store_key']) ? trim((string) $row['store_key']) : '';
						if ($key === '')
						{
							continue;
						}
						$store_rows[] = array(
							'store_key' => $key,
							'updated_at' => isset($row['updated_at']) ? trim((string) $row['updated_at']) : ''
						);
					}
				}
			}
			else
			{
				$db_error_payload = method_exists($db, 'error') ? $db->error() : array();
				$table_error = isset($db_error_payload['message']) ? trim((string) $db_error_payload['message']) : '';
			}
		}

		$store_key_lookup = array();
		for ($i = 0; $i < count($store_rows); $i += 1)
		{
			$key = isset($store_rows[$i]['store_key']) ? trim((string) $store_rows[$i]['store_key']) : '';
			if ($key !== '')
			{
				$store_key_lookup[$key] = TRUE;
			}
		}

		$expected_store_keys = array(
			$accounts_store_key,
			'attendance_records',
			'leave_requests',
			'loan_requests',
			'overtime_records',
			'conflict_logs'
		);
		$expected_key_status = array();
		for ($i = 0; $i < count($expected_store_keys); $i += 1)
		{
			$key = (string) $expected_store_keys[$i];
			$expected_key_status[] = array(
				'store_key' => $key,
				'exists' => isset($store_key_lookup[$key]) ? TRUE : FALSE
			);
		}

		$accounts = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (!is_array($accounts))
		{
			$accounts = array();
		}

		$admin_count = 0;
		$user_count = 0;
		$admin_usernames = array();
		foreach ($accounts as $username_key => $row)
		{
			$username = strtolower(trim((string) $username_key));
			if ($username === '' || !is_array($row))
			{
				continue;
			}
			$role = strtolower(trim((string) (isset($row['role']) ? $row['role'] : 'user')));
			if ($role === 'admin')
			{
				$admin_count += 1;
				$admin_usernames[] = $username;
			}
			else
			{
				$user_count += 1;
			}
		}
		sort($admin_usernames);

		$required_system_accounts = array('admin', 'developer', 'bos');
		$required_status = array();
		for ($i = 0; $i < count($required_system_accounts); $i += 1)
		{
			$key = (string) $required_system_accounts[$i];
			$required_status[$key] = isset($accounts[$key]) && is_array($accounts[$key]) ? TRUE : FALSE;
		}

		$status_payload = array(
			'checked_at' => date('Y-m-d H:i:s'),
			'runtime' => array(
				'is_cli' => $is_cli ? TRUE : FALSE,
				'php_sapi' => PHP_SAPI
			),
			'db_storage' => array(
				'enabled' => $db_storage_enabled ? TRUE : FALSE,
				'mirror_json_backup' => $mirror_json_backup ? TRUE : FALSE,
				'table' => $table_name,
				'db_connected' => $db_connected ? TRUE : FALSE,
				'db_error' => $db_error_message,
				'table_ready' => $table_ready ? TRUE : FALSE,
				'table_error' => $table_error,
				'total_store_rows' => $total_store_rows,
				'last_write_at' => $last_write_at,
				'store_rows' => $store_rows,
				'expected_keys' => $expected_key_status
			),
			'accounts' => array(
				'total' => count($accounts),
				'admin_count' => $admin_count,
				'employee_count' => $user_count,
				'admin_usernames' => $admin_usernames,
				'required_system_accounts' => $required_status
			),
			'config' => array(
				'accounts_store_key' => $accounts_store_key,
				'raw_storage_config' => $storage_config,
				'db_env' => array(
					'host' => $db_env_host,
					'port' => $db_env_port,
					'user' => $db_env_user,
					'database' => $db_env_name,
					'pass_set' => $db_env_pass !== '' ? TRUE : FALSE
				)
			)
		);

		if ($is_cli)
		{
			echo json_encode(array('success' => TRUE, 'status' => $status_payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
			return;
		}

		$this->json_response(array('success' => TRUE, 'status' => $status_payload));
	}

	public function storage_seed_from_json()
	{
		$is_cli = $this->input->is_cli_request();
		if (!$is_cli)
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
		}

		$this->load->helper('absen_data_store');
		$this->load->helper('absen_account_store');

		$db_storage_enabled = function_exists('absen_db_storage_enabled')
			? absen_db_storage_enabled()
			: FALSE;
		if (!$db_storage_enabled)
		{
			$message = 'DB storage belum aktif. Set ABSEN_DB_STORAGE_ENABLED=true lalu ulangi.';
			if ($is_cli)
			{
				echo $message."\n";
				return;
			}
			$this->json_response(array('success' => FALSE, 'message' => $message), 422);
			return;
		}

		$db = function_exists('absen_data_store_db_instance')
			? absen_data_store_db_instance()
			: NULL;
		if (!is_object($db) || !isset($db->conn_id) || !$db->conn_id)
		{
			$connection_error = function_exists('absen_data_store_last_db_error_message')
				? trim((string) absen_data_store_last_db_error_message())
				: '';
			$message = 'Koneksi MariaDB gagal. Cek DB_HOST/DB_PORT/DB_USER/DB_PASS/DB_NAME.';
			if ($connection_error !== '')
			{
				$message .= ' Detail: '.$connection_error;
			}
			if ($is_cli)
			{
				echo $message."\n";
				return;
			}
			$this->json_response(array('success' => FALSE, 'message' => $message), 500);
			return;
		}

		if (!function_exists('absen_data_store_ensure_table') || !absen_data_store_ensure_table($db))
		{
			$db_error_payload = method_exists($db, 'error') ? $db->error() : array();
			$db_error_message = isset($db_error_payload['message']) ? trim((string) $db_error_payload['message']) : '';
			$message = 'Table data store gagal disiapkan.'.($db_error_message !== '' ? ' '.$db_error_message : '');
			if ($is_cli)
			{
				echo $message."\n";
				return;
			}
			$this->json_response(array('success' => FALSE, 'message' => $message), 500);
			return;
		}

		$accounts_store_key = function_exists('absen_accounts_store_key')
			? absen_accounts_store_key()
			: 'accounts_book';

		$sources = array(
			$accounts_store_key => array('file' => function_exists('absen_accounts_file_path') ? absen_accounts_file_path() : APPPATH.'cache/accounts.json'),
			'attendance_records' => array('file' => $this->attendance_file_path()),
			'leave_requests' => array('file' => $this->leave_requests_file_path()),
			'loan_requests' => array('file' => $this->loan_requests_file_path()),
			'overtime_records' => array('file' => $this->overtime_file_path()),
			'conflict_logs' => array('file' => $this->conflict_logs_file_path())
		);

		$results = array();
		$total_saved = 0;
		$failed = array();
		foreach ($sources as $store_key => $meta)
		{
			$file_path = isset($meta['file']) ? trim((string) $meta['file']) : '';
			$raw = function_exists('absen_data_store_read_json_file')
				? absen_data_store_read_json_file($file_path, array())
				: array();
			if (!is_array($raw))
			{
				$raw = array();
			}

			$payload = $raw;
			if ($store_key === $accounts_store_key)
			{
				$account_book = array();
				foreach ($raw as $key => $row)
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
				$payload = function_exists('absen_sanitize_account_book')
					? absen_sanitize_account_book($account_book)
					: $account_book;
			}
			else
			{
				$payload = array_values($raw);
			}

			$saved = function_exists('absen_data_store_save_value')
				? absen_data_store_save_value($store_key, $payload, '')
				: FALSE;
			$row_count = is_array($payload) ? count($payload) : 0;
			$results[] = array(
				'store_key' => $store_key,
				'file' => $file_path,
				'rows' => $row_count,
				'saved' => $saved ? TRUE : FALSE
			);
			if ($saved)
			{
				$total_saved += 1;
			}
			else
			{
				$failed[] = $store_key;
			}
		}

		$accounts = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (!is_array($accounts))
		{
			$accounts = array();
		}
		$admin_count = 0;
		$employee_count = 0;
		foreach ($accounts as $username_key => $row)
		{
			if (!is_array($row))
			{
				continue;
			}
			$role = strtolower(trim((string) (isset($row['role']) ? $row['role'] : 'user')));
			if ($role === 'admin')
			{
				$admin_count += 1;
			}
			else
			{
				$employee_count += 1;
			}
		}

		$payload = array(
			'success' => empty($failed),
			'message' => empty($failed)
				? 'Seed JSON -> MariaDB selesai.'
				: 'Sebagian key gagal disimpan: '.implode(', ', $failed),
			'seeded_at' => date('Y-m-d H:i:s'),
			'saved_keys' => $total_saved,
			'total_keys' => count($sources),
			'results' => $results,
			'accounts_summary' => array(
				'total' => count($accounts),
				'admin_count' => $admin_count,
				'employee_count' => $employee_count,
				'required_system_accounts' => array(
					'admin' => isset($accounts['admin']) && is_array($accounts['admin']),
					'developer' => isset($accounts['developer']) && is_array($accounts['developer']),
					'bos' => isset($accounts['bos']) && is_array($accounts['bos'])
				)
			)
		);

		if ($is_cli)
		{
			echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
			return;
		}

		$status_code = empty($failed) ? 200 : 500;
		$this->json_response($payload, $status_code);
	}

	public function delete_employee_account()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if (!$this->can_manage_employee_accounts())
		{
			$this->session->set_flashdata('account_notice_error', 'Akun login kamu belum punya izin untuk kelola akun karyawan.');
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

		if ($this->is_reserved_system_username($username_key))
		{
			$this->session->set_flashdata('account_notice_error', 'Akun sistem (admin/developer/bos) tidak dapat dihapus.');
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
		$deleted_display_name = isset($deleted_account_row['display_name']) ? trim((string) $deleted_account_row['display_name']) : $username_key;
		$delete_note = 'Hapus akun karyawan dan data terkait.';
		$delete_note .= ' attendance='.(int) $purge_summary['attendance'];
		$delete_note .= ', leave='.(int) $purge_summary['leave'];
		$delete_note .= ', loan='.(int) $purge_summary['loan'];
		$delete_note .= ', overtime='.(int) $purge_summary['overtime'];
		$this->log_activity_event(
			'delete_account',
			'account_data',
			$username_key,
			$deleted_display_name,
			$delete_note
		);

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

		if (!$this->can_manage_employee_accounts())
		{
			$this->session->set_flashdata('account_notice_error', 'Akun login kamu belum punya izin untuk kelola akun karyawan.');
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
		$display_name_input = trim((string) $this->input->post('edit_display_name', TRUE));
		$display_name_input = preg_replace('/\s+/', ' ', $display_name_input);
		$has_display_name_field = $this->input->post('edit_display_name', FALSE) !== NULL;
		if (!$has_new_username_field)
		{
			// Kompatibel dengan halaman lama yang belum punya input edit_new_username.
			$new_username_key = $username_key;
		}
		$password_input = trim((string) $this->input->post('edit_password', FALSE));
		$branch = $this->resolve_employee_branch($this->input->post('edit_branch', TRUE));
		$phone = $this->normalize_phone_number($this->input->post('edit_phone', TRUE));
		$shift_key = strtolower(trim((string) $this->input->post('edit_shift', TRUE)));
		$has_cross_branch_field = $this->input->post('edit_cross_branch_enabled', FALSE) !== NULL;
		$cross_branch_enabled = $this->resolve_cross_branch_enabled_value($this->input->post('edit_cross_branch_enabled', TRUE));
		$salary_raw = trim((string) $this->input->post('edit_salary_monthly', TRUE));
		$salary_digits = preg_replace('/\D+/', '', $salary_raw);
		$salary_monthly = $salary_digits === '' ? 0 : (int) $salary_digits;
		$job_title = trim((string) $this->input->post('edit_job_title', TRUE));
		$address = trim((string) $this->input->post('edit_address', TRUE));
		$weekly_day_off = $this->resolve_employee_weekly_day_off($this->input->post('edit_weekly_day_off', TRUE));

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

		if ($this->is_reserved_system_username($username_key))
		{
			$this->session->set_flashdata('account_notice_error', 'Akun sistem (admin/developer/bos) tidak bisa diedit dari form ini.');
			redirect('home#manajemen-karyawan');
			return;
		}
		if ($this->is_reserved_system_username($new_username_key))
		{
			$this->session->set_flashdata('account_notice_error', 'Username sistem (admin/developer/bos) tidak boleh dipakai untuk karyawan.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($has_display_name_field && $display_name_input === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Nama lengkap akun wajib diisi.');
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
		$month_policy = $this->calculate_month_work_policy(date('Y-m-d'), $weekly_day_off);
		$work_days = isset($month_policy['work_days']) ? (int) $month_policy['work_days'] : self::WORK_DAYS_DEFAULT;
		if ($work_days <= 0)
		{
			$work_days = self::WORK_DAYS_DEFAULT;
		}
		$salary_tier = $this->resolve_salary_tier_from_amount($salary_monthly);

		$current_row = $account_book[$username_key];
		$display_name_value = $has_display_name_field
			? $display_name_input
			: (isset($current_row['display_name']) ? trim((string) $current_row['display_name']) : '');
		if ($display_name_value === '')
		{
			$display_name_value = $new_username_key;
		}

		$profile_photo_upload = $this->upload_employee_profile_photo(
			'edit_profile_photo',
			$new_username_key !== '' ? $new_username_key : $username_key,
			FALSE
		);
		if (!isset($profile_photo_upload['success']) || $profile_photo_upload['success'] !== TRUE)
		{
			$message = isset($profile_photo_upload['message']) && trim((string) $profile_photo_upload['message']) !== ''
				? (string) $profile_photo_upload['message']
				: 'Upload PP karyawan gagal.';
			$this->session->set_flashdata('account_notice_error', $message);
			redirect('home#manajemen-karyawan');
			return;
		}
		$uploaded_profile_photo_path = '';
		if (!(isset($profile_photo_upload['skipped']) && $profile_photo_upload['skipped'] === TRUE))
		{
			$uploaded_profile_photo_path = isset($profile_photo_upload['relative_path']) ? trim((string) $profile_photo_upload['relative_path']) : '';
		}

		$updated_account_row = array(
			'role' => 'user',
			'display_name' => $display_name_value,
			'branch' => $branch,
			'cross_branch_enabled' => $has_cross_branch_field
				? $cross_branch_enabled
				: $this->resolve_cross_branch_enabled_value(isset($current_row['cross_branch_enabled']) ? $current_row['cross_branch_enabled'] : 0),
			'phone' => $phone,
			'shift_name' => (string) $shift_profiles[$shift_key]['shift_name'],
			'shift_time' => (string) $shift_profiles[$shift_key]['shift_time'],
			'salary_tier' => $salary_tier,
			'salary_monthly' => $salary_monthly,
			'work_days' => (int) $work_days,
			'weekly_day_off' => (int) $weekly_day_off,
			'job_title' => $job_title,
			'address' => $address,
			'profile_photo' => $uploaded_profile_photo_path !== ''
				? $uploaded_profile_photo_path
				: (
					isset($current_row['profile_photo']) && trim((string) $current_row['profile_photo']) !== ''
						? (string) $current_row['profile_photo']
						: $this->default_employee_profile_photo()
				),
			'coordinate_point' => isset($current_row['coordinate_point']) ? trim((string) $current_row['coordinate_point']) : '',
			'employee_status' => isset($current_row['employee_status']) && trim((string) $current_row['employee_status']) !== ''
				? (string) $current_row['employee_status']
				: 'Aktif',
			'force_password_change' => isset($current_row['force_password_change']) && (int) $current_row['force_password_change'] === 1 ? 1 : 0,
			'password_changed_at' => isset($current_row['password_changed_at']) ? (string) $current_row['password_changed_at'] : '',
			'sheet_row' => isset($current_row['sheet_row']) ? (int) $current_row['sheet_row'] : 0,
			'sheet_sync_source' => 'web',
			'sheet_last_sync_at' => date('Y-m-d H:i:s')
		);
		$existing_password_value = isset($current_row['password']) ? trim((string) $current_row['password']) : '';
		if ($existing_password_value === '')
		{
			$fallback_password = function_exists('absen_hash_password') ? absen_hash_password('123') : '123';
			$existing_password_value = $fallback_password !== '' ? $fallback_password : '123';
		}
		$updated_account_row['password'] = $existing_password_value;
		$updated_account_row['password_hash'] = $existing_password_value;
		if ($password_input !== '')
		{
			if (!function_exists('absen_account_set_password') || !absen_account_set_password($updated_account_row, $password_input, TRUE))
			{
				$this->session->set_flashdata('account_notice_error', 'Gagal memproses password akun.');
				redirect('home#manajemen-karyawan');
				return;
			}
		}
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
			if ($uploaded_profile_photo_path !== '')
			{
				$this->delete_local_uploaded_file($uploaded_profile_photo_path);
			}
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
		$changed_fields = array();
		$changed_entries = array();
		$track_keys = array(
			'display_name' => 'nama',
			'branch' => 'cabang',
			'cross_branch_enabled' => 'lintas_cabang',
			'phone' => 'tlp',
			'shift_name' => 'shift',
			'salary_monthly' => 'gaji_pokok',
			'work_days' => 'hari_masuk',
			'weekly_day_off' => 'hari_libur_mingguan',
			'job_title' => 'jabatan',
			'address' => 'alamat'
		);
		foreach ($track_keys as $key => $label)
		{
			$old_value = isset($current_row[$key]) ? trim((string) $current_row[$key]) : '';
			$new_value = isset($updated_account_row[$key]) ? trim((string) $updated_account_row[$key]) : '';
			if ($old_value !== $new_value)
			{
				$changed_fields[] = $label;
				$changed_entries[] = array(
					'field' => $key,
					'field_label' => $label,
					'old_value' => $old_value,
					'new_value' => $new_value
				);
			}
		}
		if ($new_username_key !== $username_key)
		{
			$changed_fields[] = 'username';
			$changed_entries[] = array(
				'field' => 'username',
				'field_label' => 'username',
				'old_value' => $username_key,
				'new_value' => $new_username_key
			);
		}
		if ($password_input !== '')
		{
			$changed_fields[] = 'password';
			$changed_entries[] = array(
				'field' => 'password',
				'field_label' => 'password',
				'old_value' => '***',
				'new_value' => '***'
			);
		}
		$update_note = 'Edit akun karyawan.';
		if (!empty($changed_fields))
		{
			$update_note .= ' Field berubah: '.implode(', ', $changed_fields).'.';
		}
		$this->log_activity_event(
			'update_account',
			'account_data',
			$new_username_key,
			$display_name_value,
			$update_note
		);
		for ($entry_index = 0; $entry_index < count($changed_entries); $entry_index += 1)
		{
			$entry_row = is_array($changed_entries[$entry_index]) ? $changed_entries[$entry_index] : array();
			$field_key = isset($entry_row['field']) ? trim((string) $entry_row['field']) : '';
			$field_label = isset($entry_row['field_label']) ? trim((string) $entry_row['field_label']) : $field_key;
			if ($field_key === '')
			{
				continue;
			}

			$field_old = isset($entry_row['old_value']) ? (string) $entry_row['old_value'] : '';
			$field_new = isset($entry_row['new_value']) ? (string) $entry_row['new_value'] : '';
			$field_action = $field_key === 'username' ? 'update_account_username' : 'update_account_field';
			$field_note = 'Edit akun karyawan. Field '.$field_label.' diubah.';
			$this->log_activity_event(
				$field_action,
				'account_data',
				$new_username_key,
				$display_name_value,
				$field_note,
				array(
					'field' => $field_key,
					'field_label' => $field_label,
					'old_value' => $field_old,
					'new_value' => $field_new
				)
			);
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

	public function update_privileged_account_password()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if (!$this->can_access_super_admin_features())
		{
			$this->session->set_flashdata('account_notice_error', 'Fitur ini hanya bisa diakses akun Developer dan Bos.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home#manajemen-karyawan');
			return;
		}

		$actor_username = $this->current_actor_username();
		$target_username = strtolower(trim((string) $this->input->post('target_account', TRUE)));
		$new_username_raw = $this->input->post('new_username', TRUE);
		$has_new_username_field = $this->input->post('new_username', FALSE) !== NULL;
		$new_username_key = $this->normalize_username_key($new_username_raw);
		$new_display_name = trim((string) $this->input->post('new_display_name', TRUE));
		$new_display_name = preg_replace('/\s+/', ' ', $new_display_name);
		$new_password = trim((string) $this->input->post('new_password', FALSE));
		$confirm_password = trim((string) $this->input->post('confirm_password', FALSE));
		$allowed_targets = $this->allowed_privileged_password_targets($actor_username);

		if (empty($allowed_targets))
		{
			$this->session->set_flashdata('account_notice_error', 'Akun login kamu tidak diizinkan mengubah informasi akun admin.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if (!in_array($target_username, $allowed_targets, TRUE))
		{
			$this->session->set_flashdata('account_notice_error', 'Target akun tidak diizinkan untuk role login kamu.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$wants_username_update = $has_new_username_field && trim((string) $new_username_raw) !== '';
		$wants_display_name_update = $new_display_name !== '';
		$wants_password_update = $new_password !== '' || $confirm_password !== '';
		if (!$wants_username_update && !$wants_display_name_update && !$wants_password_update)
		{
			$this->session->set_flashdata('account_notice_error', 'Isi minimal satu perubahan: username baru, nama baru, atau password baru.');
			redirect('home#manajemen-karyawan');
			return;
		}
		if ($wants_username_update)
		{
			if ($new_username_key === '' || !preg_match('/^[a-z0-9_]{3,30}$/', $new_username_key))
			{
				$this->session->set_flashdata('account_notice_error', 'Username baru hanya boleh huruf kecil, angka, underscore (3-30 karakter).');
				redirect('home#manajemen-karyawan');
				return;
			}
		}

		$account_book = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (!isset($account_book[$target_username]) || !is_array($account_book[$target_username]))
		{
			$this->session->set_flashdata('account_notice_error', 'Akun '.$target_username.' tidak ditemukan.');
			redirect('home#manajemen-karyawan');
			return;
		}
		$target_role = strtolower(trim((string) (isset($account_book[$target_username]['role']) ? $account_book[$target_username]['role'] : '')));
		if ($target_role !== 'admin')
		{
			$this->session->set_flashdata('account_notice_error', 'Akun '.$target_username.' bukan akun admin.');
			redirect('home#manajemen-karyawan');
			return;
		}
		$old_login_alias = $this->normalize_username_key(isset($account_book[$target_username]['login_alias']) ? (string) $account_book[$target_username]['login_alias'] : '');
		$new_admin_login_alias = $old_login_alias;
		if ($wants_username_update)
		{
			if ($target_username === 'admin')
			{
				if ($new_username_key === 'admin')
				{
					$new_admin_login_alias = '';
				}
				else
				{
					if ($this->is_reserved_system_username($new_username_key))
					{
						$this->session->set_flashdata('account_notice_error', 'Username sistem (admin/developer/bos) tidak boleh dipakai.');
						redirect('home#manajemen-karyawan');
						return;
					}
					if (isset($account_book[$new_username_key]))
					{
						$this->session->set_flashdata('account_notice_error', 'Username '.$new_username_key.' sudah terdaftar.');
						redirect('home#manajemen-karyawan');
						return;
					}
					foreach ($account_book as $candidate_username => $candidate_row)
					{
						$candidate_key = strtolower(trim((string) $candidate_username));
						if ($candidate_key === '' || $candidate_key === $target_username || !is_array($candidate_row))
						{
							continue;
						}
						$candidate_alias = $this->normalize_username_key(isset($candidate_row['login_alias']) ? (string) $candidate_row['login_alias'] : '');
						if ($candidate_alias !== '' && $candidate_alias === $new_username_key)
						{
							$this->session->set_flashdata('account_notice_error', 'Username '.$new_username_key.' sudah dipakai sebagai login akun lain.');
							redirect('home#manajemen-karyawan');
							return;
						}
					}
					$new_admin_login_alias = $new_username_key;
				}
			}
			else
			{
				if ($this->is_reserved_system_username($target_username))
				{
					$this->session->set_flashdata('account_notice_error', 'Username akun sistem developer/bos tidak bisa diubah.');
					redirect('home#manajemen-karyawan');
					return;
				}
				if ($this->is_reserved_system_username($new_username_key))
				{
					$this->session->set_flashdata('account_notice_error', 'Username sistem (admin/developer/bos) tidak boleh dipakai.');
					redirect('home#manajemen-karyawan');
					return;
				}
				if ($new_username_key !== $target_username && isset($account_book[$new_username_key]))
				{
					$this->session->set_flashdata('account_notice_error', 'Username '.$new_username_key.' sudah terdaftar.');
					redirect('home#manajemen-karyawan');
					return;
				}
			}
		}

		$old_display_name = isset($account_book[$target_username]['display_name']) && trim((string) $account_book[$target_username]['display_name']) !== ''
			? trim((string) $account_book[$target_username]['display_name'])
			: $target_username;
		$display_name_changed = FALSE;
		$password_changed = FALSE;
		$username_changed = FALSE;
		$username_login_changed = FALSE;

		if ($wants_display_name_update && $new_display_name !== $old_display_name)
		{
			$account_book[$target_username]['display_name'] = $new_display_name;
			$display_name_changed = TRUE;
		}

		if ($wants_password_update)
		{
			if ($new_password === '' || strlen($new_password) < 3)
			{
				$this->session->set_flashdata('account_notice_error', 'Password baru minimal 3 karakter.');
				redirect('home#manajemen-karyawan');
				return;
			}
			if ($new_password !== $confirm_password)
			{
				$this->session->set_flashdata('account_notice_error', 'Konfirmasi password tidak sama.');
				redirect('home#manajemen-karyawan');
				return;
			}
			$force_change_target = $actor_username !== $target_username;
			if (!function_exists('absen_account_set_password') || !absen_account_set_password($account_book[$target_username], $new_password, $force_change_target))
			{
				$this->session->set_flashdata('account_notice_error', 'Gagal memproses password baru. Coba lagi.');
				redirect('home#manajemen-karyawan');
				return;
			}
			$password_changed = TRUE;
		}

		if ($wants_username_update)
		{
			if ($target_username === 'admin')
			{
				if ($new_admin_login_alias !== $old_login_alias)
				{
					$account_book[$target_username]['login_alias'] = $new_admin_login_alias;
					$username_login_changed = TRUE;
				}
			}
			elseif ($new_username_key !== $target_username)
			{
				$moved_account_row = $account_book[$target_username];
				unset($account_book[$target_username]);
				$account_book[$new_username_key] = $moved_account_row;
				$username_changed = TRUE;
			}
		}
		$final_username = $username_changed ? $new_username_key : $target_username;

		if (!$username_changed && !$username_login_changed && !$display_name_changed && !$password_changed)
		{
			$this->session->set_flashdata('account_notice_success', 'Informasi akun '.$target_username.' tidak berubah.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$saved = function_exists('absen_save_account_book')
			? absen_save_account_book($account_book)
			: FALSE;
		if (!$saved)
		{
			$this->session->set_flashdata('account_notice_error', 'Gagal menyimpan perubahan informasi akun. Coba lagi.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$target_display_name = isset($account_book[$final_username]['display_name']) && trim((string) $account_book[$final_username]['display_name']) !== ''
			? trim((string) $account_book[$final_username]['display_name'])
			: $final_username;
		if ($username_changed)
		{
			$this->log_activity_event(
				'update_account_username',
				'account_data',
				$final_username,
				$target_display_name,
				'Edit akun privileged. Field username diubah.',
				array(
					'field' => 'username',
					'field_label' => 'username',
					'old_value' => $target_username,
					'new_value' => $final_username
				)
			);
		}
		if ($username_login_changed)
		{
			$old_login_label = $old_login_alias !== '' ? $old_login_alias : 'admin';
			$new_login_label = $new_admin_login_alias !== '' ? $new_admin_login_alias : 'admin';
			$this->log_activity_event(
				'update_account_field',
				'account_data',
				$final_username,
				$target_display_name,
				'Edit akun privileged. Field username login diubah.',
				array(
					'field' => 'login_alias',
					'field_label' => 'username_login',
					'old_value' => $old_login_label,
					'new_value' => $new_login_label
				)
			);
		}
		if ($display_name_changed)
		{
			$this->log_activity_event(
				'update_account_field',
				'account_data',
				$final_username,
				$target_display_name,
				'Edit akun privileged. Field nama diubah.',
				array(
					'field' => 'display_name',
					'field_label' => 'nama',
					'old_value' => $old_display_name,
					'new_value' => $new_display_name
				)
			);
		}
		if ($password_changed)
		{
			$this->log_activity_event(
				'update_password',
				'account_data',
				$final_username,
				$target_display_name,
				'Ganti password akun '.$final_username.'.',
				array(
					'field' => 'password',
					'field_label' => 'Password',
					'old_value' => '***',
					'new_value' => '***'
				)
			);
		}

		if ($actor_username === $target_username && $username_changed)
		{
			$this->session->set_userdata('absen_username', $final_username);
			$actor_username = $final_username;
		}
		if ($actor_username === $final_username && $display_name_changed)
		{
			$this->session->set_userdata('absen_display_name', $target_display_name);
		}
		if ($actor_username === $final_username && $password_changed)
		{
			$this->session->set_userdata('absen_password_change_required', 0);
		}
		$success_parts = array();
		if ($username_changed || $username_login_changed)
		{
			$success_parts[] = 'username';
		}
		if ($display_name_changed)
		{
			$success_parts[] = 'nama';
		}
		if ($password_changed)
		{
			$success_parts[] = 'password';
		}
		$this->session->set_flashdata('account_notice_success', 'Informasi akun '.$final_username.' berhasil diperbarui ('.implode(' + ', $success_parts).').');
		redirect('home#manajemen-karyawan');
	}

	public function update_privileged_account_display_name()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if (!$this->can_access_super_admin_features())
		{
			$this->session->set_flashdata('account_notice_error', 'Fitur ini hanya bisa diakses akun Developer dan Bos.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home#manajemen-karyawan');
			return;
		}

		$actor_username = $this->current_actor_username();
		$target_username = strtolower(trim((string) $this->input->post('target_account', TRUE)));
		$new_display_name = trim((string) $this->input->post('new_display_name', TRUE));
		$new_display_name = preg_replace('/\s+/', ' ', $new_display_name);
		$allowed_targets = $this->allowed_privileged_password_targets($actor_username);

		if (empty($allowed_targets))
		{
			$this->session->set_flashdata('account_notice_error', 'Akun login kamu tidak diizinkan mengganti nama akun admin.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if (!in_array($target_username, $allowed_targets, TRUE))
		{
			$this->session->set_flashdata('account_notice_error', 'Target akun tidak diizinkan untuk role login kamu.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($target_username !== 'admin')
		{
			$this->session->set_flashdata('account_notice_error', 'Hanya akun admin yang bisa diganti nama dari form ini.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($new_display_name === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Nama baru akun admin wajib diisi.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$account_book = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (!isset($account_book[$target_username]) || !is_array($account_book[$target_username]))
		{
			$this->session->set_flashdata('account_notice_error', 'Akun '.$target_username.' tidak ditemukan.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$target_role = strtolower(trim((string) (isset($account_book[$target_username]['role']) ? $account_book[$target_username]['role'] : '')));
		if ($target_role !== 'admin')
		{
			$this->session->set_flashdata('account_notice_error', 'Akun '.$target_username.' bukan akun admin.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$old_display_name = isset($account_book[$target_username]['display_name']) && trim((string) $account_book[$target_username]['display_name']) !== ''
			? trim((string) $account_book[$target_username]['display_name'])
			: $target_username;
		if ($old_display_name === $new_display_name)
		{
			$this->session->set_flashdata('account_notice_success', 'Nama akun admin tetap sama (tidak ada perubahan).');
			redirect('home#manajemen-karyawan');
			return;
		}

		$account_book[$target_username]['display_name'] = $new_display_name;
		$saved = function_exists('absen_save_account_book')
			? absen_save_account_book($account_book)
			: FALSE;
		if (!$saved)
		{
			$this->session->set_flashdata('account_notice_error', 'Gagal menyimpan nama akun admin. Coba lagi.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$this->log_activity_event(
			'update_account_field',
			'account_data',
			$target_username,
			$new_display_name,
			'Edit akun admin. Field nama diubah.',
			array(
				'field' => 'display_name',
				'field_label' => 'nama',
				'old_value' => $old_display_name,
				'new_value' => $new_display_name
			)
		);

		$this->session->set_flashdata('account_notice_success', 'Nama akun admin berhasil diperbarui menjadi '.$new_display_name.'.');
		redirect('home#manajemen-karyawan');
	}

	public function create_feature_admin_account()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if (!$this->can_access_super_admin_features())
		{
			$this->session->set_flashdata('account_notice_error', 'Fitur ini hanya bisa diakses akun Developer dan Bos.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home#manajemen-karyawan');
			return;
		}

		$username_key = $this->normalize_username_key($this->input->post('feature_admin_username', TRUE));
		$display_name = trim((string) $this->input->post('feature_admin_display_name', TRUE));
		$display_name = preg_replace('/\s+/', ' ', $display_name);
		$password = trim((string) $this->input->post('feature_admin_password', FALSE));
		$feature_permissions = $this->normalize_admin_feature_permissions($this->input->post('feature_permissions', FALSE));

		if ($username_key === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Username akun fitur wajib diisi.');
			redirect('home#manajemen-karyawan');
			return;
		}
		if (!preg_match('/^[a-z0-9_]{3,30}$/', $username_key))
		{
			$this->session->set_flashdata('account_notice_error', 'Username hanya boleh huruf kecil, angka, underscore (3-30 karakter).');
			redirect('home#manajemen-karyawan');
			return;
		}
		if ($this->is_reserved_system_username($username_key))
		{
			$this->session->set_flashdata('account_notice_error', 'Username sistem (admin/developer/bos) tidak boleh dipakai.');
			redirect('home#manajemen-karyawan');
			return;
		}
		if ($display_name === '')
		{
			$display_name = $username_key;
		}
		if ($password === '' || strlen($password) < 3)
		{
			$this->session->set_flashdata('account_notice_error', 'Password akun fitur minimal 3 karakter.');
			redirect('home#manajemen-karyawan');
			return;
		}
		if (empty($feature_permissions))
		{
			$this->session->set_flashdata('account_notice_error', 'Pilih minimal 1 fitur untuk akun baru.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$account_book = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (isset($account_book[$username_key]) && is_array($account_book[$username_key]))
		{
			$this->session->set_flashdata('account_notice_error', 'Username '.$username_key.' sudah terdaftar.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$new_account_row = array(
			'role' => 'admin',
			'display_name' => $display_name,
			'branch' => '',
			'phone' => '',
			'shift_name' => '',
			'shift_time' => '',
			'salary_tier' => '',
			'salary_monthly' => 0,
			'work_days' => 22,
			'job_title' => 'Admin',
			'address' => $this->default_employee_address(),
			'profile_photo' => '',
			'coordinate_point' => '',
			'employee_status' => 'Aktif',
			'sheet_row' => 0,
			'sheet_sync_source' => '',
			'sheet_last_sync_at' => '',
			'feature_permissions' => $feature_permissions,
			'force_password_change' => 1,
			'password_changed_at' => ''
		);
		if (!function_exists('absen_account_set_password') || !absen_account_set_password($new_account_row, $password, TRUE))
		{
			$this->session->set_flashdata('account_notice_error', 'Gagal memproses password akun fitur.');
			redirect('home#manajemen-karyawan');
			return;
		}
		$account_book[$username_key] = $new_account_row;

		$saved = function_exists('absen_save_account_book')
			? absen_save_account_book($account_book)
			: FALSE;
		if (!$saved)
		{
			$this->session->set_flashdata('account_notice_error', 'Gagal menyimpan akun fitur baru.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$this->log_activity_event(
			'create_account',
			'account_data',
			$username_key,
			$display_name,
			'Buat akun admin fitur baru.',
			array(
				'field' => 'feature_permissions',
				'field_label' => 'fitur',
				'new_value' => implode(',', $feature_permissions)
			)
		);

		$this->session->set_flashdata('account_notice_success', 'Akun fitur '.$username_key.' berhasil dibuat.');
		redirect('home#manajemen-karyawan');
	}

	public function update_feature_admin_account_permissions()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if (!$this->can_access_super_admin_features())
		{
			$this->session->set_flashdata('account_notice_error', 'Fitur ini hanya bisa diakses akun Developer dan Bos.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home#manajemen-karyawan');
			return;
		}

		$actor_username = $this->current_actor_username();
		$target_username = strtolower(trim((string) $this->input->post('feature_target_account', TRUE)));
		$feature_permissions = $this->normalize_admin_feature_permissions($this->input->post('feature_permissions', FALSE));
		$allowed_targets = $this->manageable_admin_targets_for_actor($actor_username);
		if ($target_username === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Pilih akun admin yang ingin diubah fiturnya.');
			redirect('home#manajemen-karyawan');
			return;
		}
		if (!in_array($target_username, $allowed_targets, TRUE))
		{
			$this->session->set_flashdata('account_notice_error', 'Kamu tidak punya izin mengubah fitur akun '.$target_username.'.');
			redirect('home#manajemen-karyawan');
			return;
		}
		if (in_array($target_username, $this->privileged_admin_usernames(), TRUE))
		{
			if ($actor_username === 'developer' && $target_username === 'bos')
			{
				// Developer diizinkan mengelola fitur akun bos.
			}
			else
			{
				$message = 'Fitur akun sistem '.$target_username.' tidak bisa diubah dari form ini.';
				if ($actor_username === 'bos' && $target_username === 'developer')
				{
					$message = 'Bos tidak diizinkan mengubah fitur akun developer.';
				}
				$this->session->set_flashdata('account_notice_error', $message);
				redirect('home#manajemen-karyawan');
				return;
			}
		}

		$account_book = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (!isset($account_book[$target_username]) || !is_array($account_book[$target_username]))
		{
			$this->session->set_flashdata('account_notice_error', 'Akun '.$target_username.' tidak ditemukan.');
			redirect('home#manajemen-karyawan');
			return;
		}
		$target_role = strtolower(trim((string) (isset($account_book[$target_username]['role']) ? $account_book[$target_username]['role'] : '')));
		if ($target_role !== 'admin')
		{
			$this->session->set_flashdata('account_notice_error', 'Akun '.$target_username.' bukan akun admin.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$old_permissions = $this->normalize_admin_feature_permissions(
			isset($account_book[$target_username]['feature_permissions']) ? $account_book[$target_username]['feature_permissions'] : array()
		);
		$old_permissions_csv = implode(',', $old_permissions);
		$new_permissions_csv = implode(',', $feature_permissions);
		if ($old_permissions_csv === $new_permissions_csv)
		{
			$this->session->set_flashdata('account_notice_success', 'Fitur akun '.$target_username.' tidak berubah.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$account_book[$target_username]['feature_permissions'] = $feature_permissions;
		$saved = function_exists('absen_save_account_book')
			? absen_save_account_book($account_book)
			: FALSE;
		if (!$saved)
		{
			$this->session->set_flashdata('account_notice_error', 'Gagal menyimpan perubahan fitur akun.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$target_display_name = isset($account_book[$target_username]['display_name']) && trim((string) $account_book[$target_username]['display_name']) !== ''
			? (string) $account_book[$target_username]['display_name']
			: $target_username;
		$this->log_activity_event(
			'update_account_field',
			'account_data',
			$target_username,
			$target_display_name,
			'Edit akun admin. Field fitur diubah.',
			array(
				'field' => 'feature_permissions',
				'field_label' => 'fitur',
				'old_value' => $old_permissions_csv,
				'new_value' => $new_permissions_csv
			)
		);

		$this->session->set_flashdata('account_notice_success', 'Fitur akun '.$target_username.' berhasil diperbarui.');
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

		$cached = $this->load_admin_dashboard_live_summary_cache(self::ADMIN_DASHBOARD_SUMMARY_CACHE_TTL_SECONDS);
		if (is_array($cached))
		{
			$this->json_response(array(
				'success' => TRUE,
				'summary' => isset($cached['summary']) && is_array($cached['summary']) ? $cached['summary'] : array(),
				'generated_at' => isset($cached['generated_at']) ? (string) $cached['generated_at'] : date('Y-m-d H:i:s')
			));
			return;
		}

		$summary = $this->build_admin_dashboard_summary_only();
		$generated_at = date('Y-m-d H:i:s');
		$this->save_admin_dashboard_live_summary_cache($summary, $generated_at);
		$this->json_response(array(
			'success' => TRUE,
			'summary' => $summary,
			'generated_at' => $generated_at
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

		$username = (string) $this->session->userdata('absen_username');
		$user_profile = $this->get_employee_profile($username);
		$attendance_branch = $this->resolve_attendance_branch_for_user($username, $user_profile);
		$shift_name = (string) $this->session->userdata('absen_shift_name');
		$shift_time = (string) $this->session->userdata('absen_shift_time');
		$shift_key = $this->resolve_shift_key_from_shift_values($shift_name, $shift_time);
		$nearest_office = $this->nearest_attendance_office((float) $latitude, (float) $longitude, $attendance_branch);
		$distance_m = isset($nearest_office['distance_m']) ? (float) $nearest_office['distance_m'] : 0.0;
		$office_label = isset($nearest_office['label']) ? (string) $nearest_office['label'] : 'kantor';
		$geofence_check = $this->evaluate_geofence($distance_m, $accuracy_m, $office_label);
		if ($geofence_check['inside'] !== TRUE)
		{
			$this->json_response(array(
				'success' => FALSE,
				'message' => $geofence_check['message']
			), 422);
			return;
		}

		$is_time_window_bypassed = $this->should_bypass_attendance_time_window($username);
		$session_salary_tier = strtoupper(trim((string) $this->session->userdata('absen_salary_tier')));
		$session_salary_monthly = (float) $this->session->userdata('absen_salary_monthly');
		$profile_salary_tier = isset($user_profile['salary_tier']) ? strtoupper(trim((string) $user_profile['salary_tier'])) : '';
		$profile_salary_monthly = isset($user_profile['salary_monthly']) ? (float) $user_profile['salary_monthly'] : 0;
		$profile_weekly_day_off = isset($user_profile['weekly_day_off'])
			? $this->resolve_employee_weekly_day_off($user_profile['weekly_day_off'])
			: $this->default_weekly_day_off();
		$salary_tier = $profile_salary_tier !== '' ? $profile_salary_tier : $session_salary_tier;
		$salary_monthly = $profile_salary_monthly > 0 ? $profile_salary_monthly : $session_salary_monthly;
		$date_key = date('Y-m-d');
		$date_label = date('d-m-Y');
		$current_time = date('H:i:s');
		$current_seconds = $this->time_to_seconds($current_time);
		$month_policy = $this->calculate_month_work_policy($date_key, $profile_weekly_day_off);
		$work_days = isset($month_policy['work_days']) ? (int) $month_policy['work_days'] : self::WORK_DAYS_DEFAULT;
		if ($work_days <= 0)
		{
			$work_days = self::WORK_DAYS_DEFAULT;
		}

		if ($action === 'masuk')
		{
			$check_in_window = $this->resolve_shift_check_in_window($shift_key);
			$check_in_start_time = isset($check_in_window['start']) ? (string) $check_in_window['start'] : self::CHECK_IN_MIN_TIME;
			$check_in_end_time = isset($check_in_window['end']) ? (string) $check_in_window['end'] : self::CHECK_IN_MAX_TIME;
			$check_in_start_label = isset($check_in_window['start_label']) ? (string) $check_in_window['start_label'] : '06:30';
			$check_in_end_label = isset($check_in_window['end_label']) ? (string) $check_in_window['end_label'] : '17:00';

			if (!$is_time_window_bypassed && $current_seconds < $this->time_to_seconds($check_in_start_time))
			{
				$this->json_response(array(
					'success' => FALSE,
					'message' => 'Absen masuk baru bisa dilakukan mulai jam '.$check_in_start_label.' WIB.'
				), 422);
				return;
			}

			if (!$is_time_window_bypassed && $current_seconds > $this->time_to_seconds($check_in_end_time))
			{
				$this->json_response(array(
					'success' => FALSE,
					'message' => 'Batas maksimal absen masuk adalah jam '.$check_in_end_label.' WIB.'
				), 422);
				return;
			}
		}
		else
		{
			$check_out_window = $this->resolve_shift_check_out_window($shift_key);
			$check_out_end_time = isset($check_out_window['end']) ? (string) $check_out_window['end'] : self::CHECK_OUT_MAX_TIME;
			$check_out_end_label = isset($check_out_window['end_label']) ? (string) $check_out_window['end_label'] : '23:59';
			if (!$is_time_window_bypassed && $current_seconds > $this->time_to_seconds($check_out_end_time))
			{
				$this->json_response(array(
					'success' => FALSE,
					'message' => 'Batas maksimal absen pulang adalah jam '.$check_out_end_label.' WIB.'
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
				'branch' => $attendance_branch,
				'check_in_time' => '',
				'check_in_late' => '00:00:00',
				'check_in_photo' => '',
				'check_in_lat' => '',
				'check_in_lng' => '',
				'check_in_accuracy_m' => '',
				'check_in_distance_m' => '',
				'jenis_masuk' => '',
				'jenis_pulang' => '',
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
		$record['branch'] = $attendance_branch;
		$record['salary_tier'] = $salary_tier;
		$record['salary_monthly'] = number_format($salary_monthly, 0, '.', '');
		$record['work_days_per_month'] = $work_days;
		$record['days_in_month'] = $month_policy['days_in_month'];
		$record['weekly_off_days'] = $month_policy['weekly_off_days'];

		if ($action === 'masuk')
		{
			$record['check_in_time'] = $current_time;
			$record['check_in_late'] = $this->calculate_late_duration($current_time, $shift_time);
			$is_force_late_user = $this->should_force_late_attendance($username);
			if ($is_force_late_user && $this->duration_to_seconds($record['check_in_late']) <= 0)
			{
				$record['check_in_late'] = self::ATTENDANCE_FORCE_LATE_DURATION;
			}
			$record['check_in_photo'] = $photo;
			$record['check_in_lat'] = (string) $latitude;
			$record['check_in_lng'] = (string) $longitude;
			$record['check_in_accuracy_m'] = number_format($accuracy_m, 2, '.', '');
			$record['check_in_distance_m'] = number_format($distance_m, 2, '.', '');
			$record['jenis_masuk'] = 'Absen Masuk';

			$late_seconds = $this->duration_to_seconds($record['check_in_late']);
			if ($is_force_late_user && $late_seconds > 0 && $late_reason_input === '')
			{
				$late_reason_input = 'Telat (mode testing)';
			}
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
			$record['jenis_pulang'] = 'Absen Pulang';
			$record['work_duration'] = $this->calculate_work_duration($record['check_in_time'], $current_time);
			$message = 'Absen pulang berhasil disimpan.';
		}

		$record['updated_at'] = date('Y-m-d H:i:s');
		$records[$record_index] = $record;

		$this->save_attendance_records($records);
		$this->clear_admin_dashboard_live_summary_cache();

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

		$needs_upgrade = FALSE;
		$is_current_password_valid = function_exists('absen_verify_account_password')
			? absen_verify_account_password($account_book[$username_key], $current_password, $needs_upgrade)
			: ((isset($account_book[$username_key]['password']) ? (string) $account_book[$username_key]['password'] : '') === $current_password);
		if ($is_current_password_valid !== TRUE)
		{
			$this->session->set_flashdata('password_notice_error', 'Password saat ini tidak sesuai.');
			redirect('home#ubah-password');
			return;
		}

		if (!function_exists('absen_account_set_password') || !absen_account_set_password($account_book[$username_key], $new_password, FALSE))
		{
			$this->session->set_flashdata('password_notice_error', 'Gagal memproses password baru. Coba lagi.');
			redirect('home#ubah-password');
			return;
		}
		$saved = function_exists('absen_save_account_book')
			? absen_save_account_book($account_book)
			: FALSE;
		if (!$saved)
		{
			$this->session->set_flashdata('password_notice_error', 'Gagal menyimpan password baru. Coba lagi.');
			redirect('home#ubah-password');
			return;
		}

		$this->session->set_userdata('absen_password_change_required', 0);
		$this->session->set_userdata('absen_last_activity_at', time());
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
		$scope_lookup = $this->scoped_employee_lookup();
		$employee_id_book = $this->employee_id_book();
		$employee_profiles = $this->employee_profile_book();
		$month_policy_cache = array();
		for ($i = 0; $i < count($records); $i += 1)
		{
			$row_username = isset($records[$i]['username']) ? (string) $records[$i]['username'] : '';
			$row_username_key = strtolower(trim((string) $row_username));
			if ($row_username_key === '' || !isset($scope_lookup[$row_username_key]))
			{
				unset($records[$i]);
				continue;
			}
			$check_in_raw = isset($records[$i]['check_in_time']) ? trim((string) $records[$i]['check_in_time']) : '';
			$check_out_raw = isset($records[$i]['check_out_time']) ? trim((string) $records[$i]['check_out_time']) : '';
			$has_check_in = $this->has_real_attendance_time($check_in_raw);
			$has_check_out = $this->has_real_attendance_time($check_out_raw);
			if (!$has_check_in && !$has_check_out)
			{
				unset($records[$i]);
				continue;
			}
			$records[$i]['employee_id'] = $this->resolve_employee_id_from_book($row_username, $employee_id_book);
			$row_profile = isset($employee_profiles[$row_username_key]) && is_array($employee_profiles[$row_username_key])
				? $employee_profiles[$row_username_key]
				: $this->get_employee_profile($row_username);
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
			$row_branch_origin = isset($row_profile['branch']) ? (string) $row_profile['branch'] : '';
			$row_branch_origin = $this->resolve_employee_branch($row_branch_origin);
			if ($row_branch_origin === '')
			{
				$row_branch_origin = $this->default_employee_branch();
			}
			$row_branch_attendance = isset($records[$i]['branch']) ? (string) $records[$i]['branch'] : '';
			$row_branch_attendance = $this->resolve_employee_branch($row_branch_attendance);
			if ($row_branch_attendance === '')
			{
				$row_branch_attendance = $row_branch_origin;
			}
			$records[$i]['branch_origin'] = $row_branch_origin;
			$records[$i]['branch_attendance'] = $row_branch_attendance;
			$row_weekly_day_off = isset($row_profile['weekly_day_off'])
				? $this->resolve_employee_weekly_day_off($row_profile['weekly_day_off'])
				: $this->default_weekly_day_off();
			$record_date = isset($records[$i]['date']) ? (string) $records[$i]['date'] : '';
			$month_policy_cache_key = $record_date !== '' ? (substr($record_date, 0, 7).'|'.$row_weekly_day_off) : ('current|'.$row_weekly_day_off);
			if (!isset($month_policy_cache[$month_policy_cache_key]))
			{
				$month_policy_cache[$month_policy_cache_key] = $this->calculate_month_work_policy($record_date, $row_weekly_day_off);
			}
			$month_policy = $month_policy_cache[$month_policy_cache_key];

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
				if ($check_in_time === '')
				{
					$records[$i]['check_in_late'] = '00:00:00';
					$records[$i]['salary_cut_amount'] = '0';
					$records[$i]['salary_cut_rule'] = 'Tidak telat';
					$records[$i]['salary_cut_category'] = '';
				}
				else
				{
					$late_duration = isset($records[$i]['check_in_late']) ? trim((string) $records[$i]['check_in_late']) : '';
					if ($late_duration === '' && $shift_time !== '')
					{
						$late_duration = $this->calculate_late_duration($check_in_time, $shift_time);
					}
					if ($late_duration === '')
					{
						$late_duration = '00:00:00';
					}
					$records[$i]['check_in_late'] = $late_duration;

					$late_seconds = $this->duration_to_seconds($late_duration);
					$salary_tier = isset($records[$i]['salary_tier']) ? (string) $records[$i]['salary_tier'] : '';
					$salary_monthly = isset($records[$i]['salary_monthly']) ? (float) $records[$i]['salary_monthly'] : 0;
					$work_days = isset($records[$i]['work_days_per_month']) && (int) $records[$i]['work_days_per_month'] > 0
						? (int) $records[$i]['work_days_per_month']
						: $month_policy['work_days'];
					$deduction_result = $this->calculate_late_deduction(
						$salary_tier,
						$salary_monthly,
						$work_days,
						$late_seconds,
						$record_date,
						$row_username,
						$row_weekly_day_off
					);
					$records[$i]['salary_cut_amount'] = number_format($deduction_result['amount'], 0, '.', '');
					$records[$i]['salary_cut_rule'] = $deduction_result['rule'];
					$records[$i]['salary_cut_category'] = isset($deduction_result['category_key']) ? (string) $deduction_result['category_key'] : '';
				}
			}

			if (!isset($records[$i]['work_days_per_month']) || (int) $records[$i]['work_days_per_month'] <= 0)
			{
				$records[$i]['work_days_per_month'] = $month_policy['work_days'];
				$records[$i]['days_in_month'] = $month_policy['days_in_month'];
				$records[$i]['weekly_off_days'] = $month_policy['weekly_off_days'];
			}
			$row_branch_for_distance = $row_branch_attendance;

			if ((!isset($records[$i]['check_in_distance_m']) || $records[$i]['check_in_distance_m'] === '') &&
				isset($records[$i]['check_in_lat']) && isset($records[$i]['check_in_lng']) &&
				is_numeric($records[$i]['check_in_lat']) && is_numeric($records[$i]['check_in_lng']))
			{
				$nearest_check_in_office = $this->nearest_attendance_office(
					(float) $records[$i]['check_in_lat'],
					(float) $records[$i]['check_in_lng'],
					$row_branch_for_distance
				);
				$records[$i]['check_in_distance_m'] = number_format(
					isset($nearest_check_in_office['distance_m']) ? (float) $nearest_check_in_office['distance_m'] : 0,
					2,
					'.',
					''
				);
			}

			if ((!isset($records[$i]['check_out_distance_m']) || $records[$i]['check_out_distance_m'] === '') &&
				isset($records[$i]['check_out_lat']) && isset($records[$i]['check_out_lng']) &&
				is_numeric($records[$i]['check_out_lat']) && is_numeric($records[$i]['check_out_lng']))
			{
				$nearest_check_out_office = $this->nearest_attendance_office(
					(float) $records[$i]['check_out_lat'],
					(float) $records[$i]['check_out_lng'],
					$row_branch_for_distance
				);
				$records[$i]['check_out_distance_m'] = number_format(
					isset($nearest_check_out_office['distance_m']) ? (float) $nearest_check_out_office['distance_m'] : 0,
					2,
					'.',
					''
				);
			}
		}
		$records = array_values($records);
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

		$records = $this->load_attendance_records();
		$leave_requests = $this->load_leave_requests();
		$scope_lookup = $this->scoped_employee_lookup();
		$month_index = array();
		$current_month_key = date('Y-m');
		$month_index[$current_month_key] = TRUE;

		for ($record_i = 0; $record_i < count($records); $record_i += 1)
		{
			$row = $records[$record_i];
			$row_username = isset($row['username']) ? strtolower(trim((string) $row['username'])) : '';
			if ($row_username === '' || !isset($scope_lookup[$row_username]))
			{
				continue;
			}
			$row_date = isset($row['date']) ? trim((string) $row['date']) : '';
			if (!$this->is_valid_date_format($row_date))
			{
				continue;
			}
			$month_index[substr($row_date, 0, 7)] = TRUE;
		}

		for ($request_i = 0; $request_i < count($leave_requests); $request_i += 1)
		{
			$request = $leave_requests[$request_i];
			$request_status = isset($request['status']) ? strtolower(trim((string) $request['status'])) : '';
			if ($request_status !== 'diterima')
			{
				continue;
			}
			$request_username = isset($request['username']) ? strtolower(trim((string) $request['username'])) : '';
			if ($request_username === '' || !isset($scope_lookup[$request_username]))
			{
				continue;
			}
			$start_date = isset($request['start_date']) ? trim((string) $request['start_date']) : '';
			$end_date = isset($request['end_date']) ? trim((string) $request['end_date']) : '';
			if (!$this->is_valid_date_format($start_date) || !$this->is_valid_date_format($end_date))
			{
				continue;
			}

			$start_month_ts = strtotime(substr($start_date, 0, 7).'-01 00:00:00');
			$end_month_ts = strtotime(substr($end_date, 0, 7).'-01 00:00:00');
			if ($start_month_ts === FALSE || $end_month_ts === FALSE)
			{
				continue;
			}
			if ($end_month_ts < $start_month_ts)
			{
				$temp_ts = $start_month_ts;
				$start_month_ts = $end_month_ts;
				$end_month_ts = $temp_ts;
			}

			for ($cursor_ts = $start_month_ts; $cursor_ts <= $end_month_ts; $cursor_ts = strtotime('+1 month', $cursor_ts))
			{
				if ($cursor_ts === FALSE)
				{
					break;
				}
				$month_index[date('Y-m', $cursor_ts)] = TRUE;
			}
		}

		$available_months = array_keys($month_index);
		if (empty($available_months))
		{
			$available_months = array($current_month_key);
		}
		rsort($available_months, SORT_STRING);

		$page_raw = (int) $this->input->get('page', TRUE);
		$current_page = $page_raw > 0 ? $page_raw : 1;
		$month_input = trim((string) $this->input->get('month', TRUE));
		if (preg_match('/^\d{4}-\d{2}$/', $month_input))
		{
			if (!isset($month_index[$month_input]))
			{
				$month_index[$month_input] = TRUE;
				$available_months = array_keys($month_index);
				rsort($available_months, SORT_STRING);
			}
		}
		else
		{
			$total_pages = count($available_months);
			if ($current_page > $total_pages)
			{
				$current_page = $total_pages;
			}
			if ($current_page < 1)
			{
				$current_page = 1;
			}
			$month_input = isset($available_months[$current_page - 1]) ? (string) $available_months[$current_page - 1] : $current_month_key;
		}

		$current_page_index = array_search($month_input, $available_months, TRUE);
		if ($current_page_index === FALSE)
		{
			$available_months[] = $month_input;
			rsort($available_months, SORT_STRING);
			$current_page_index = array_search($month_input, $available_months, TRUE);
		}
		$current_page = $current_page_index === FALSE ? 1 : ((int) $current_page_index + 1);
		$total_pages = count($available_months);

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
		$month_policy_default = $this->calculate_month_work_policy($month_start, $this->default_weekly_day_off());
		$work_days_default = isset($month_policy_default['work_days']) ? (int) $month_policy_default['work_days'] : self::WORK_DAYS_DEFAULT;
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
			$username_scope_key = strtolower($username);
			if (!isset($scope_lookup[$username_scope_key]))
			{
				continue;
			}
			$employee_id = $this->resolve_employee_id_from_book($username, $employee_id_book);
			$user_profile = $this->get_employee_profile($username);
			$profile_weekly_day_off = isset($user_profile['weekly_day_off'])
				? $this->resolve_employee_weekly_day_off($user_profile['weekly_day_off'])
				: $this->default_weekly_day_off();
			$profile_month_policy = $this->calculate_month_work_policy($month_start, $profile_weekly_day_off);
			$resolved_work_days = isset($profile_month_policy['work_days']) ? (int) $profile_month_policy['work_days'] : $work_days_default;
			if ($resolved_work_days <= 0)
			{
				$resolved_work_days = $work_days_default;
			}
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
					'weekly_day_off' => $profile_weekly_day_off,
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
			$users[$username]['weekly_day_off'] = $profile_weekly_day_off;
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
				$stored_late = isset($row['check_in_late']) ? trim((string) $row['check_in_late']) : '';
				if ($stored_late !== '')
				{
					$late_seconds = $this->duration_to_seconds($stored_late);
				}
				elseif ($shift_time !== '')
				{
					$late_seconds = $this->duration_to_seconds($this->calculate_late_duration($check_in_time, $shift_time));
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
			$username_scope_key = strtolower($username);
			if (!isset($scope_lookup[$username_scope_key]))
			{
				continue;
			}
			$employee_id = $this->resolve_employee_id_from_book($username, $employee_id_book);
			$user_profile = $this->get_employee_profile($username);
			$profile_weekly_day_off = isset($user_profile['weekly_day_off'])
				? $this->resolve_employee_weekly_day_off($user_profile['weekly_day_off'])
				: $this->default_weekly_day_off();
			$profile_month_policy = $this->calculate_month_work_policy($month_start, $profile_weekly_day_off);
			$resolved_work_days = isset($profile_month_policy['work_days']) ? (int) $profile_month_policy['work_days'] : $work_days_default;
			if ($resolved_work_days <= 0)
			{
				$resolved_work_days = $work_days_default;
			}
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
					'weekly_day_off' => $profile_weekly_day_off,
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
				$users[$username]['weekly_day_off'] = $profile_weekly_day_off;
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

			$request_type = $this->resolve_leave_request_type($request);
			$is_izin_request = $request_type === 'izin';
			$is_cuti_request = $request_type === 'cuti';
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
				// Tetap dihitung walau ada jejak hadir pada tanggal yang sama.
				$users[$username]['leave_dates'][$date_key] = TRUE;
				if ($is_izin_request)
				{
					$users[$username]['izin_dates'][$date_key] = TRUE;
					$users[$username]['leave_type_by_date'][$date_key] = 'izin';
				}
				elseif ($is_cuti_request)
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

			$weekly_day_off_n = isset($user_data['weekly_day_off'])
				? $this->resolve_employee_weekly_day_off($user_data['weekly_day_off'])
				: $this->default_weekly_day_off();
			$month_policy = $this->calculate_month_work_policy($month_start, $weekly_day_off_n);
			$work_days_plan = isset($month_policy['work_days']) ? (int) $month_policy['work_days'] : $work_days_default;
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
			$total_alpha = 0;
			if ($this->monthly_infer_alpha_from_gap_enabled())
			{
				$total_alpha = $hari_efektif_bulan - $hadir_days - $leave_days;
				if ($total_alpha < 0)
				{
					$total_alpha = 0;
				}
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
				$request_leave_days = $izin_days + $cuti_days;
				$request_leave_days_unique = isset($user_data['leave_dates']) && is_array($user_data['leave_dates'])
					? count($user_data['leave_dates'])
					: 0;
				// Prioritaskan data pengajuan yang sudah diterima.
				// Nilai sheet dipakai sebagai fallback jika lebih besar.
				if ($sheet_izin_cuti > $request_leave_days)
				{
					$izin_days = $sheet_izin_cuti;
					$cuti_days = 0;
				}
				$leave_days = $request_leave_days_unique;
				if ($sheet_izin_cuti > $leave_days)
				{
					$leave_days = $sheet_izin_cuti;
				}

				$sheet_alpha = isset($sheet_summary['total_alpha']) ? (int) $sheet_summary['total_alpha'] : 0;
				$total_alpha = max(0, $sheet_alpha);
				$total_alpha_izin = $total_alpha + $izin_days;

				$total_telat_1_30 = max(0, (int) (isset($sheet_summary['total_telat_1_30']) ? $sheet_summary['total_telat_1_30'] : 0));
				$total_telat_31_60 = max(0, (int) (isset($sheet_summary['total_telat_31_60']) ? $sheet_summary['total_telat_31_60'] : 0));
				$total_telat_1_3_jam = max(0, (int) (isset($sheet_summary['total_telat_1_3']) ? $sheet_summary['total_telat_1_3'] : 0));
				$total_telat_gt_4_jam = max(0, (int) (isset($sheet_summary['total_telat_gt_4']) ? $sheet_summary['total_telat_gt_4'] : 0));
				$leave_used_before_period = 0;
			}

			if (
				$this->monthly_demo_randomization_enabled() &&
				!$has_sheet_summary &&
				$this->should_randomize_monthly_demo_data($hadir_days, $leave_days, $total_alpha, $hari_efektif_bulan)
			)
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
				'weekly_day_off' => $weekly_day_off_n,
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
			'month_pagination' => array(
				'current_page' => $current_page,
				'total_pages' => $total_pages,
				'available_months' => $available_months
			),
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
		if (!$this->is_username_in_actor_scope($username))
		{
			$this->session->set_flashdata('attendance_notice_error', 'Akses data absensi ditolak karena beda cabang.');
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
		$deduction_note = 'Ubah potongan absensi pada data web.';
		$deduction_note .= ' Tanggal '.$date_key.'.';
		$this->log_activity_event(
			'update_attendance_deduction',
			'web_data',
			$username,
			$username,
			$deduction_note,
			array(
				'field' => 'salary_cut_amount',
				'new_value' => (string) $salary_cut_amount
			)
		);

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

	public function delete_attendance_record()
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

		if ($username === '' || $date_key === '' || !$this->is_valid_date_format($date_key))
		{
			$this->session->set_flashdata('attendance_notice_error', 'Data absensi yang ingin dihapus tidak valid.');
			redirect('home/employee_data');
			return;
		}
		if (!$this->is_username_in_actor_scope($username))
		{
			$this->session->set_flashdata('attendance_notice_error', 'Akses data absensi ditolak karena beda cabang.');
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
			$this->session->set_flashdata('attendance_notice_error', 'Data absensi tidak ditemukan atau sudah dihapus.');
			redirect('home/employee_data');
			return;
		}

		$record_row = $records[$record_index];
		$check_in_time = isset($record_row['check_in_time']) && trim((string) $record_row['check_in_time']) !== ''
			? (string) $record_row['check_in_time']
			: '-';
		$check_out_time = isset($record_row['check_out_time']) && trim((string) $record_row['check_out_time']) !== ''
			? (string) $record_row['check_out_time']
			: '-';
		unset($records[$record_index]);
		$this->save_attendance_records(array_values($records));
		$this->clear_admin_dashboard_live_summary_cache();

		$delete_note = 'Hapus data absensi tidak valid dari data web.';
		$delete_note .= ' Tanggal '.$date_key.'.';
		$delete_note .= ' Jam masuk '.$check_in_time.', jam pulang '.$check_out_time.'.';
		$this->log_activity_event(
			'delete_attendance_record',
			'web_data',
			$username,
			$username,
			$delete_note,
			array(
				'target_id' => strtolower($username).'|'.$date_key,
				'old_value' => 'masuk='.$check_in_time.',pulang='.$check_out_time
			)
		);

		$this->session->set_flashdata(
			'attendance_notice_success',
			'Data absensi '.$username.' tanggal '.$date_key.' berhasil dihapus.'
		);
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
		$this->log_activity_event(
			'submit_leave_request',
			'web_data',
			$username,
			$username,
			'Karyawan membuat pengajuan '.$request_type_label.'.',
			array(
				'target_id' => $request_id,
				'new_value' => $start_date.' s/d '.$end_date
			)
		);

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
		$this->log_activity_event(
			'submit_loan_request',
			'web_data',
			$username,
			$username,
			'Karyawan membuat pengajuan pinjaman.',
			array(
				'target_id' => $loan_id,
				'new_value' => 'amount='.(int) $amount.', tenor='.(int) $tenor_months
			)
		);

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
		$request_username = isset($request_row['username']) ? (string) $request_row['username'] : '';
		if (!$this->is_username_in_actor_scope($request_username))
		{
			$this->session->set_flashdata('leave_notice_error', 'Akses pengajuan ditolak karena beda cabang.');
			redirect('home/leave_requests');
			return;
		}
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
		$leave_type = $this->resolve_leave_request_type($request_row);
		$leave_type_label = $leave_type === 'cuti' ? 'cuti' : 'izin';
		$leave_action = strtolower(trim((string) $request_row['status'])) === 'diterima'
			? 'approve_leave_request'
			: 'reject_leave_request';
		$leave_note = 'Ubah status pengajuan '.$leave_type_label.' menjadi '.$request_row['status'].'.';
		$leave_note .= ' Periode '.(isset($request_row['start_date']) ? (string) $request_row['start_date'] : '-');
		$leave_note .= ' s/d '.(isset($request_row['end_date']) ? (string) $request_row['end_date'] : '-').'.';
		$this->log_activity_event(
			$leave_action,
			'web_data',
			isset($request_row['username']) ? (string) $request_row['username'] : '',
			isset($request_row['username']) ? (string) $request_row['username'] : '',
			$leave_note,
			array(
				'target_id' => isset($request_row['id']) ? (string) $request_row['id'] : $request_id,
				'new_value' => (string) $request_row['status']
			)
		);

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
		$request_username = isset($request_row['username']) ? (string) $request_row['username'] : '';
		if (!$this->is_username_in_actor_scope($request_username))
		{
			$this->session->set_flashdata('loan_notice_error', 'Akses pengajuan ditolak karena beda cabang.');
			redirect('home/loan_requests');
			return;
		}
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
		$loan_action = strtolower(trim((string) $request_row['status'])) === 'diterima'
			? 'approve_loan_request'
			: 'reject_loan_request';
		$loan_note = 'Ubah status pengajuan pinjaman menjadi '.$request_row['status'].'.';
		$loan_note .= ' Nominal Rp '.number_format((int) (isset($request_row['amount']) ? $request_row['amount'] : 0), 0, ',', '.').'.';
		$this->log_activity_event(
			$loan_action,
			'web_data',
			isset($request_row['username']) ? (string) $request_row['username'] : '',
			isset($request_row['username']) ? (string) $request_row['username'] : '',
			$loan_note,
			array(
				'target_id' => isset($request_row['id']) ? (string) $request_row['id'] : $request_id,
				'new_value' => (string) $request_row['status']
			)
		);

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

	public function delete_leave_request()
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

		if (!$this->is_developer_actor())
		{
			$this->session->set_flashdata('leave_notice_error', 'Hanya akun developer yang bisa menghapus data pengajuan cuti/izin.');
			redirect('home/leave_requests');
			return;
		}

		$request_id = trim((string) $this->input->post('request_id', TRUE));
		if ($request_id === '')
		{
			$this->session->set_flashdata('leave_notice_error', 'Data pengajuan yang ingin dihapus tidak valid.');
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
			$this->session->set_flashdata('leave_notice_error', 'Data pengajuan tidak ditemukan atau sudah dihapus.');
			redirect('home/leave_requests');
			return;
		}

		$request_row = $requests[$target_index];
		$request_username = isset($request_row['username']) ? (string) $request_row['username'] : '';
		if (!$this->is_username_in_actor_scope($request_username))
		{
			$this->session->set_flashdata('leave_notice_error', 'Akses pengajuan ditolak karena beda cabang.');
			redirect('home/leave_requests');
			return;
		}

		$support_file_path = isset($request_row['support_file_path']) ? trim((string) $request_row['support_file_path']) : '';
		if ($support_file_path !== '')
		{
			$this->delete_local_uploaded_file($support_file_path);
		}

		unset($requests[$target_index]);
		$this->save_leave_requests(array_values($requests));

		$leave_type = $this->resolve_leave_request_type($request_row);
		$leave_type_label = $leave_type === 'cuti' ? 'cuti' : 'izin';
		$leave_note = 'Hapus data pengajuan '.$leave_type_label.'.';
		$leave_note .= ' Periode '.(isset($request_row['start_date']) ? (string) $request_row['start_date'] : '-');
		$leave_note .= ' s/d '.(isset($request_row['end_date']) ? (string) $request_row['end_date'] : '-').'.';
		$this->log_activity_event(
			'delete_leave_request',
			'web_data',
			$request_username,
			$request_username,
			$leave_note,
			array(
				'target_id' => $request_id,
				'old_value' => isset($request_row['status']) ? (string) $request_row['status'] : ''
			)
		);

		$this->session->set_flashdata('leave_notice_success', 'Data pengajuan cuti/izin berhasil dihapus.');
		redirect('home/leave_requests');
	}

	public function delete_loan_request()
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

		if (!$this->is_developer_actor())
		{
			$this->session->set_flashdata('loan_notice_error', 'Hanya akun developer yang bisa menghapus data pengajuan pinjaman.');
			redirect('home/loan_requests');
			return;
		}

		$request_id = trim((string) $this->input->post('request_id', TRUE));
		if ($request_id === '')
		{
			$this->session->set_flashdata('loan_notice_error', 'Data pinjaman yang ingin dihapus tidak valid.');
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
			$this->session->set_flashdata('loan_notice_error', 'Data pengajuan pinjaman tidak ditemukan atau sudah dihapus.');
			redirect('home/loan_requests');
			return;
		}

		$request_row = $requests[$target_index];
		$request_username = isset($request_row['username']) ? (string) $request_row['username'] : '';
		if (!$this->is_username_in_actor_scope($request_username))
		{
			$this->session->set_flashdata('loan_notice_error', 'Akses pengajuan ditolak karena beda cabang.');
			redirect('home/loan_requests');
			return;
		}

		unset($requests[$target_index]);
		$this->save_loan_requests(array_values($requests));

		$loan_note = 'Hapus data pengajuan pinjaman.';
		$loan_note .= ' Nominal Rp '.number_format((int) (isset($request_row['amount']) ? $request_row['amount'] : 0), 0, ',', '.').'.';
		$this->log_activity_event(
			'delete_loan_request',
			'web_data',
			$request_username,
			$request_username,
			$loan_note,
			array(
				'target_id' => $request_id,
				'old_value' => isset($request_row['status']) ? (string) $request_row['status'] : ''
			)
		);

		$this->session->set_flashdata('loan_notice_success', 'Data pengajuan pinjaman berhasil dihapus.');
		redirect('home/loan_requests');
	}

	public function delete_overtime_record()
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

		if (!$this->is_developer_actor())
		{
			$this->session->set_flashdata('overtime_notice_error', 'Hanya akun developer yang bisa menghapus data lembur.');
			redirect('home/overtime_data');
			return;
		}

		$record_id = trim((string) $this->input->post('record_id', TRUE));
		if ($record_id === '')
		{
			$this->session->set_flashdata('overtime_notice_error', 'Data lembur yang ingin dihapus tidak valid.');
			redirect('home/overtime_data');
			return;
		}

		$records = $this->load_overtime_records();
		$target_index = -1;
		for ($i = 0; $i < count($records); $i += 1)
		{
			if (isset($records[$i]['id']) && (string) $records[$i]['id'] === $record_id)
			{
				$target_index = $i;
				break;
			}
		}

		if ($target_index < 0)
		{
			$this->session->set_flashdata('overtime_notice_error', 'Data lembur tidak ditemukan atau sudah dihapus.');
			redirect('home/overtime_data');
			return;
		}

		$record_row = $records[$target_index];
		$record_username = isset($record_row['username']) ? (string) $record_row['username'] : '';
		if (!$this->is_username_in_actor_scope($record_username))
		{
			$this->session->set_flashdata('overtime_notice_error', 'Akses data lembur ditolak karena beda cabang.');
			redirect('home/overtime_data');
			return;
		}

		unset($records[$target_index]);
		$this->save_overtime_records(array_values($records));

		$overtime_note = 'Hapus data lembur.';
		$overtime_note .= ' Tanggal '.(isset($record_row['overtime_date']) ? (string) $record_row['overtime_date'] : '-');
		$overtime_note .= ', jam '.(isset($record_row['start_time']) ? (string) $record_row['start_time'] : '-');
		$overtime_note .= '-'.(isset($record_row['end_time']) ? (string) $record_row['end_time'] : '-').'.';
		$this->log_activity_event(
			'delete_overtime_record',
			'web_data',
			$record_username,
			$record_username,
			$overtime_note,
			array(
				'target_id' => $record_id,
				'old_value' => isset($record_row['nominal']) ? (string) $record_row['nominal'] : ''
			)
		);

		$this->session->set_flashdata('overtime_notice_success', 'Data lembur berhasil dihapus.');
		redirect('home/overtime_data');
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
		$visible_requests = array();
		for ($i = 0; $i < count($requests); $i += 1)
		{
			$request_username = isset($requests[$i]['username']) ? (string) $requests[$i]['username'] : '';
			if (!$this->is_username_in_actor_scope($request_username))
			{
				continue;
			}
			$visible_requests[] = $requests[$i];
		}

		$data = array(
			'title' => 'Pengajuan Cuti / Izin',
			'requests' => $visible_requests,
			'is_developer_actor' => $this->is_developer_actor()
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
		$visible_requests = array();
		for ($i = 0; $i < count($requests); $i += 1)
		{
			$request_username = isset($requests[$i]['username']) ? (string) $requests[$i]['username'] : '';
			if (!$this->is_username_in_actor_scope($request_username))
			{
				continue;
			}
			$visible_requests[] = $requests[$i];
		}

		$data = array(
			'title' => 'Pengajuan Pinjaman',
			'requests' => $visible_requests,
			'is_developer_actor' => $this->is_developer_actor()
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
		$scoped_employee_names = array();
		for ($i = 0; $i < count($employee_names); $i += 1)
		{
			$employee_name_key = isset($employee_names[$i]) ? (string) $employee_names[$i] : '';
			if (!$this->is_username_in_actor_scope($employee_name_key))
			{
				continue;
			}
			$scoped_employee_names[] = $employee_name_key;
		}
		$employee_names = $scoped_employee_names;
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
		$visible_records = array();
		for ($i = 0; $i < count($records); $i += 1)
		{
			$record_username = isset($records[$i]['username']) ? (string) $records[$i]['username'] : '';
			if (!$this->is_username_in_actor_scope($record_username))
			{
				continue;
			}
			$visible_records[] = $records[$i];
		}

		$data = array(
			'title' => 'Data Lembur',
			'employee_names' => $employee_names,
			'employee_options' => $employee_options,
			'records' => $visible_records,
			'is_developer_actor' => $this->is_developer_actor()
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
		if (!$this->is_username_in_actor_scope($employee_name))
		{
			$this->session->set_flashdata('overtime_notice_error', 'Akses input lembur ditolak karena beda cabang.');
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
		$overtime_note = 'Input data lembur.';
		$overtime_note .= ' Tanggal '.$overtime_date.', jam '.$start_time.'-'.$end_time.', nominal Rp '.number_format($nominal_value, 0, ',', '.').'.';
		$this->log_activity_event(
			'input_overtime',
			'web_data',
			$employee_name,
			$employee_name,
			$overtime_note,
			array(
				'new_value' => $reason
			)
		);
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
			$shift_time = '08:00 - 23:00';
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

			$late_duration = isset($records[$i]['check_in_late']) ? trim((string) $records[$i]['check_in_late']) : '';
			if ($late_duration === '' && $row_check_in !== '')
			{
				$late_duration = $this->calculate_late_duration($row_check_in, $row_shift_time);
			}
			if ($late_duration === '')
			{
				$late_duration = '00:00:00';
			}
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

		return '23:00';
	}

	private function build_admin_dashboard_summary_only()
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

		$activity_start_key = $this->resolve_admin_activity_start_key($month_start_key, $today_key, $metric_maps);
		$start_ts = $activity_start_key !== '' ? strtotime($activity_start_key.' 00:00:00') : FALSE;
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
		$sheet_summary_totals = $this->build_admin_sheet_month_summary_totals(date('Y-m'));
		if (isset($sheet_summary_totals['has_data']) && $sheet_summary_totals['has_data'] === TRUE)
		{
			$summary['total_hadir_bulan_ini'] = isset($sheet_summary_totals['total_hadir']) ? (int) $sheet_summary_totals['total_hadir'] : $summary['total_hadir_bulan_ini'];
			$summary['total_terlambat_bulan_ini'] = isset($sheet_summary_totals['total_terlambat']) ? (int) $sheet_summary_totals['total_terlambat'] : $summary['total_terlambat_bulan_ini'];
			$summary['total_izin_bulan_ini'] = isset($sheet_summary_totals['total_izin']) ? (int) $sheet_summary_totals['total_izin'] : $summary['total_izin_bulan_ini'];
			$summary['total_alpha_bulan_ini'] = isset($sheet_summary_totals['total_alpha']) ? (int) $sheet_summary_totals['total_alpha'] : $summary['total_alpha_bulan_ini'];
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

		return $summary;
	}

	private function build_admin_sheet_month_summary_totals($month_key = '')
	{
		$month_key = trim((string) $month_key);
		if (!preg_match('/^\d{4}\-\d{2}$/', $month_key))
		{
			$month_key = date('Y-m');
		}

		$employees = $this->scoped_employee_usernames();
		$employee_lookup = array();
		for ($i = 0; $i < count($employees); $i += 1)
		{
			$username_key = strtolower(trim((string) $employees[$i]));
			if ($username_key === '')
			{
				continue;
			}
			$employee_lookup[$username_key] = TRUE;
		}
		if (empty($employee_lookup))
		{
			return array(
				'has_data' => FALSE,
				'total_hadir' => 0,
				'total_terlambat' => 0,
				'total_izin' => 0,
				'total_alpha' => 0,
				'users' => 0
			);
		}

		$records = $this->load_attendance_records();
		$latest_by_username = array();
		for ($i = 0; $i < count($records); $i += 1)
		{
			$row = isset($records[$i]) && is_array($records[$i]) ? $records[$i] : array();
			$username_key = isset($row['username']) ? strtolower(trim((string) $row['username'])) : '';
			if ($username_key === '' || !isset($employee_lookup[$username_key]))
			{
				continue;
			}

			$row_date = isset($row['date']) ? trim((string) $row['date']) : '';
			$row_month = isset($row['sheet_month']) ? trim((string) $row['sheet_month']) : '';
			if ($row_month === '' && strlen($row_date) >= 7)
			{
				$row_month = substr($row_date, 0, 7);
			}
			if ($row_month !== $month_key)
			{
				continue;
			}

			$has_sheet_summary = isset($row['sheet_total_hadir']) ||
				isset($row['sheet_sudah_berapa_absen']) ||
				isset($row['sheet_total_alpha']) ||
				isset($row['sheet_total_izin_cuti']) ||
				isset($row['sheet_total_izin']) ||
				isset($row['sheet_total_cuti']) ||
				isset($row['sheet_total_telat_1_30']) ||
				isset($row['sheet_total_telat_31_60']) ||
				isset($row['sheet_total_telat_1_3']) ||
				isset($row['sheet_total_telat_gt_4']);
			if (!$has_sheet_summary)
			{
				continue;
			}

			$sort_key = isset($row['updated_at']) ? trim((string) $row['updated_at']) : '';
			if ($sort_key === '')
			{
				$check_in_text = isset($row['check_in_time']) ? trim((string) $row['check_in_time']) : '00:00:00';
				$sort_key = ($row_date !== '' ? $row_date : ($month_key.'-01')).' '.$check_in_text;
			}
			$current_sort = isset($latest_by_username[$username_key]['sort_key'])
				? (string) $latest_by_username[$username_key]['sort_key']
				: '';
			if ($current_sort === '' || strcmp($sort_key, $current_sort) >= 0)
			{
				$latest_by_username[$username_key] = array(
					'sort_key' => $sort_key,
					'row' => $row
				);
			}
		}

		if (empty($latest_by_username))
		{
			return array(
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
		foreach ($latest_by_username as $username_key => $snapshot)
		{
			$row = isset($snapshot['row']) && is_array($snapshot['row']) ? $snapshot['row'] : array();
			$hadir = isset($row['sheet_total_hadir']) ? (int) $row['sheet_total_hadir'] : 0;
			if ($hadir <= 0)
			{
				$hadir = isset($row['sheet_sudah_berapa_absen']) ? (int) $row['sheet_sudah_berapa_absen'] : 0;
			}
			$hadir = max(0, $hadir);
			$total_hadir += $hadir;

			$telat_1_30 = max(0, (int) (isset($row['sheet_total_telat_1_30']) ? $row['sheet_total_telat_1_30'] : 0));
			$telat_31_60 = max(0, (int) (isset($row['sheet_total_telat_31_60']) ? $row['sheet_total_telat_31_60'] : 0));
			$telat_1_3 = max(0, (int) (isset($row['sheet_total_telat_1_3']) ? $row['sheet_total_telat_1_3'] : 0));
			$telat_gt_4 = max(0, (int) (isset($row['sheet_total_telat_gt_4']) ? $row['sheet_total_telat_gt_4'] : 0));
			$total_terlambat += $telat_1_30 + $telat_31_60 + $telat_1_3 + $telat_gt_4;

			$izin_cuti = isset($row['sheet_total_izin_cuti']) ? (int) $row['sheet_total_izin_cuti'] : 0;
			if ($izin_cuti <= 0)
			{
				$total_izin_value = max(0, (int) (isset($row['sheet_total_izin']) ? $row['sheet_total_izin'] : 0));
				$total_cuti_value = max(0, (int) (isset($row['sheet_total_cuti']) ? $row['sheet_total_cuti'] : 0));
				$izin_cuti = $total_izin_value + $total_cuti_value;
			}
			$total_izin += max(0, $izin_cuti);

			$total_alpha += max(0, (int) (isset($row['sheet_total_alpha']) ? $row['sheet_total_alpha'] : 0));
		}

		return array(
			'has_data' => TRUE,
			'total_hadir' => (int) $total_hadir,
			'total_terlambat' => (int) $total_terlambat,
			'total_izin' => (int) $total_izin,
			'total_alpha' => (int) $total_alpha,
			'users' => count($latest_by_username)
		);
	}

	private function admin_dashboard_live_summary_cache_file_path()
	{
		return APPPATH.'cache/admin_dashboard_live_summary_cache.json';
	}

	private function load_admin_dashboard_live_summary_cache($max_age_seconds = 0)
	{
		$file_path = $this->admin_dashboard_live_summary_cache_file_path();
		$cached_payload = NULL;
		if (function_exists('absen_data_store_load_value'))
		{
			$loaded = absen_data_store_load_value('admin_dashboard_live_summary_cache', NULL, $file_path);
			if (is_array($loaded))
			{
				$cached_payload = $loaded;
			}
		}
		elseif (is_file($file_path))
		{
			$raw = @file_get_contents($file_path);
			if (is_string($raw) && trim($raw) !== '')
			{
				if (substr($raw, 0, 3) === "\xEF\xBB\xBF")
				{
					$raw = substr($raw, 3);
				}
				$decoded = json_decode($raw, TRUE);
				if (is_array($decoded))
				{
					$cached_payload = $decoded;
				}
			}
		}

		if (!is_array($cached_payload))
		{
			return NULL;
		}

		$summary = isset($cached_payload['summary']) && is_array($cached_payload['summary'])
			? $cached_payload['summary']
			: array();
		$generated_at = isset($cached_payload['generated_at']) ? trim((string) $cached_payload['generated_at']) : '';
		$cached_at = isset($cached_payload['cached_at']) ? (int) $cached_payload['cached_at'] : 0;
		if ($cached_at <= 0 && $generated_at !== '')
		{
			$generated_timestamp = strtotime($generated_at);
			if ($generated_timestamp !== FALSE)
			{
				$cached_at = (int) $generated_timestamp;
			}
		}

		if ($cached_at <= 0 || empty($summary))
		{
			return NULL;
		}

		$max_age_seconds = (int) $max_age_seconds;
		if ($max_age_seconds > 0 && (time() - $cached_at) > $max_age_seconds)
		{
			return NULL;
		}

		if ($generated_at === '')
		{
			$generated_at = date('Y-m-d H:i:s', $cached_at);
		}

		return array(
			'summary' => $summary,
			'generated_at' => $generated_at,
			'cached_at' => $cached_at
		);
	}

	private function save_admin_dashboard_live_summary_cache($summary, $generated_at = '')
	{
		$file_path = $this->admin_dashboard_live_summary_cache_file_path();
		$summary = is_array($summary) ? $summary : array();
		$generated_at = trim((string) $generated_at);
		if ($generated_at === '')
		{
			$generated_at = date('Y-m-d H:i:s');
		}

		$payload = array(
			'summary' => $summary,
			'generated_at' => $generated_at,
			'cached_at' => time(),
			'updated_at' => date('Y-m-d H:i:s')
		);

		if (function_exists('absen_data_store_save_value'))
		{
			$saved = absen_data_store_save_value('admin_dashboard_live_summary_cache', $payload, $file_path);
			if ($saved)
			{
				return TRUE;
			}
		}

		$directory = dirname($file_path);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0777, TRUE);
		}

		return (bool) @file_put_contents($file_path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
	}

	private function clear_admin_dashboard_live_summary_cache()
	{
		$file_path = $this->admin_dashboard_live_summary_cache_file_path();
		if (function_exists('absen_data_store_save_value'))
		{
			$cleared = absen_data_store_save_value('admin_dashboard_live_summary_cache', array(), $file_path);
			if ($cleared)
			{
				return TRUE;
			}
		}

		if (is_file($file_path))
		{
			return (bool) @unlink($file_path);
		}

		return TRUE;
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

		$activity_start_key = $this->resolve_admin_activity_start_key($month_start_key, $today_key, $metric_maps);
		$start_ts = $activity_start_key !== '' ? strtotime($activity_start_key.' 00:00:00') : FALSE;
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
		$sheet_summary_totals = $this->build_admin_sheet_month_summary_totals(date('Y-m'));
		if (isset($sheet_summary_totals['has_data']) && $sheet_summary_totals['has_data'] === TRUE)
		{
			$summary['total_hadir_bulan_ini'] = isset($sheet_summary_totals['total_hadir']) ? (int) $sheet_summary_totals['total_hadir'] : $summary['total_hadir_bulan_ini'];
			$summary['total_terlambat_bulan_ini'] = isset($sheet_summary_totals['total_terlambat']) ? (int) $sheet_summary_totals['total_terlambat'] : $summary['total_terlambat_bulan_ini'];
			$summary['total_izin_bulan_ini'] = isset($sheet_summary_totals['total_izin']) ? (int) $sheet_summary_totals['total_izin'] : $summary['total_izin_bulan_ini'];
			$summary['total_alpha_bulan_ini'] = isset($sheet_summary_totals['total_alpha']) ? (int) $sheet_summary_totals['total_alpha'] : $summary['total_alpha_bulan_ini'];
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
		$employees = $this->scoped_employee_usernames();
		$employee_lookup = array();
		$weekly_day_off_by_username = array();
		$employee_profiles = $this->employee_profile_book();
		for ($i = 0; $i < count($employees); $i += 1)
		{
			$username_key = strtolower(trim((string) $employees[$i]));
			if ($username_key === '')
			{
				continue;
			}
			$employee_lookup[$username_key] = TRUE;
			$profile = isset($employee_profiles[$username_key]) && is_array($employee_profiles[$username_key])
				? $employee_profiles[$username_key]
				: array();
			$weekly_day_off_by_username[$username_key] = isset($profile['weekly_day_off'])
				? $this->resolve_employee_weekly_day_off($profile['weekly_day_off'])
				: $this->default_weekly_day_off();
		}

		$checkin_seconds_by_date = array();
		$checkout_seconds_by_date = array();
		$late_by_date = array();
		$today_key = date('Y-m-d');
		$min_date = $today_key;
		$max_date = $today_key;
		$has_any_activity = FALSE;

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

			$check_in = isset($records[$i]['check_in_time']) ? trim((string) $records[$i]['check_in_time']) : '';
			$row_has_activity = FALSE;
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
				$row_has_activity = TRUE;
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
				$row_has_activity = TRUE;
			}

			if ($row_has_activity)
			{
				$has_any_activity = TRUE;
				if ($date_key < $min_date)
				{
					$min_date = $date_key;
				}
				if ($date_key > $max_date)
				{
					$max_date = $date_key;
				}
			}
		}

		$leave_result = $this->build_admin_leave_map($employee_lookup);
		$leave_by_date = isset($leave_result['by_date']) && is_array($leave_result['by_date'])
			? $leave_result['by_date']
			: array();
		$leave_count_by_date = isset($leave_result['count_by_date']) && is_array($leave_result['count_by_date'])
			? $leave_result['count_by_date']
			: array();
		$leave_min_date = isset($leave_result['min_date']) ? trim((string) $leave_result['min_date']) : '';
		$leave_max_date = isset($leave_result['max_date']) ? trim((string) $leave_result['max_date']) : '';
		if ($leave_min_date !== '')
		{
			if (!$has_any_activity || $leave_min_date < $min_date)
			{
				$min_date = $leave_min_date;
			}
		}
		if ($leave_max_date !== '')
		{
			if (!$has_any_activity || $leave_max_date > $max_date)
			{
				$max_date = $leave_max_date;
			}
		}
		if ($leave_min_date !== '' || $leave_max_date !== '')
		{
			$has_any_activity = TRUE;
		}
		if (!$has_any_activity)
		{
			$min_date = $today_key;
			$max_date = $today_key;
		}

		return array(
			'employees' => $employees,
			'employee_lookup' => $employee_lookup,
			'employee_count' => count($employee_lookup),
			'has_any_activity' => $has_any_activity ? TRUE : FALSE,
			'weekly_day_off_by_username' => $weekly_day_off_by_username,
			'checkin_seconds_by_date' => $checkin_seconds_by_date,
			'checkout_seconds_by_date' => $checkout_seconds_by_date,
			'late_by_date' => $late_by_date,
			'leave_by_date' => $leave_by_date,
			'leave_count_by_date' => $leave_count_by_date,
			'min_date' => $min_date,
			'max_date' => $max_date
		);
	}

	private function resolve_admin_activity_start_key($period_start_key, $period_end_key, $metric_maps)
	{
		$start_key = trim((string) $period_start_key);
		$end_key = trim((string) $period_end_key);
		if (!$this->is_valid_date_format($start_key) || !$this->is_valid_date_format($end_key))
		{
			return '';
		}
		if ($start_key > $end_key)
		{
			$temp = $start_key;
			$start_key = $end_key;
			$end_key = $temp;
		}

		$candidates = array();
		$date_maps = array('checkin_seconds_by_date', 'checkout_seconds_by_date', 'leave_by_date');
		for ($i = 0; $i < count($date_maps); $i += 1)
		{
			$map_key = (string) $date_maps[$i];
			$map_rows = isset($metric_maps[$map_key]) && is_array($metric_maps[$map_key])
				? $metric_maps[$map_key]
				: array();
			foreach ($map_rows as $date_key => $row)
			{
				$date_text = trim((string) $date_key);
				if (!$this->is_valid_date_format($date_text))
				{
					continue;
				}
				if ($date_text < $start_key || $date_text > $end_key)
				{
					continue;
				}
				if (!is_array($row) || empty($row))
				{
					continue;
				}
				$candidates[$date_text] = TRUE;
			}
		}

		if (empty($candidates))
		{
			return '';
		}

		$keys = array_keys($candidates);
		sort($keys);
		return isset($keys[0]) ? (string) $keys[0] : '';
	}

	private function build_admin_leave_map($employee_lookup)
	{
		$by_date = array();
		$count_by_date = array();
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
					if (!isset($count_by_date[$date_key]))
					{
						$count_by_date[$date_key] = 0;
					}

					if (!isset($by_date[$date_key][$username_key]) || $request_type === 'cuti')
					{
						$by_date[$date_key][$username_key] = $request_type;
					}
					$count_by_date[$date_key] += 1;
				}
			}

			return array(
				'by_date' => $by_date,
				'count_by_date' => $count_by_date,
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
		$day_checkouts = isset($metric_maps['checkout_seconds_by_date'][$date_key]) && is_array($metric_maps['checkout_seconds_by_date'][$date_key])
			? $metric_maps['checkout_seconds_by_date'][$date_key]
			: array();
		$day_late_users = isset($metric_maps['late_by_date'][$date_key]) && is_array($metric_maps['late_by_date'][$date_key])
			? $metric_maps['late_by_date'][$date_key]
			: array();
			$day_leave_users = isset($metric_maps['leave_by_date'][$date_key]) && is_array($metric_maps['leave_by_date'][$date_key])
				? $metric_maps['leave_by_date'][$date_key]
				: array();
			$day_leave_count = isset($metric_maps['leave_count_by_date'][$date_key])
				? max(0, (int) $metric_maps['leave_count_by_date'][$date_key])
				: count($day_leave_users);

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

			$izin_cuti = $day_leave_count;
		$alpha = 0;
		$today_key = date('Y-m-d');
		$check_in_max_seconds = $this->time_to_seconds(self::CHECK_IN_MAX_TIME);
		$is_alpha_open = TRUE;
			$has_day_activity = !empty($day_checkins) || !empty($day_checkouts) || $day_leave_count > 0;
		if ($date_key === $today_key)
		{
			if ($hour_cutoff !== NULL)
			{
				$is_alpha_open = (int) $hour_cutoff >= $check_in_max_seconds;
			}
			else
			{
				$is_alpha_open = $this->time_to_seconds(date('H:i:s')) >= $check_in_max_seconds;
			}
		}
		if ($is_alpha_open && $has_day_activity)
		{
			$weekday_n = (int) date('N', strtotime($date_key.' 00:00:00'));
			$employee_lookup = isset($metric_maps['employee_lookup']) && is_array($metric_maps['employee_lookup'])
				? $metric_maps['employee_lookup']
				: array();
			$weekly_day_off_by_username = isset($metric_maps['weekly_day_off_by_username']) && is_array($metric_maps['weekly_day_off_by_username'])
				? $metric_maps['weekly_day_off_by_username']
				: array();
			$target_headcount = 0;
			foreach ($employee_lookup as $username_key => $is_active)
			{
				if (!$is_active)
				{
					continue;
				}
				$user_weekly_off = isset($weekly_day_off_by_username[$username_key])
					? $this->resolve_employee_weekly_day_off($weekly_day_off_by_username[$username_key])
					: $this->default_weekly_day_off();
				if ($weekday_n !== $user_weekly_off)
				{
					$target_headcount += 1;
				}
			}
			$izin_cuti_target = 0;
			foreach ($day_leave_users as $leave_username => $leave_type)
			{
				$leave_username_key = strtolower(trim((string) $leave_username));
				if ($leave_username_key === '' || !isset($employee_lookup[$leave_username_key]))
				{
					continue;
				}
				$user_weekly_off = isset($weekly_day_off_by_username[$leave_username_key])
					? $this->resolve_employee_weekly_day_off($weekly_day_off_by_username[$leave_username_key])
					: $this->default_weekly_day_off();
				if ($weekday_n !== $user_weekly_off)
				{
					$izin_cuti_target += 1;
				}
			}
			$alpha = max(0, $target_headcount - $hadir - $izin_cuti_target);
		}

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
		$has_any_activity = isset($metric_maps['has_any_activity']) && $metric_maps['has_any_activity'] === TRUE;
		if (!$has_any_activity)
		{
			return array(
				'metric' => $metric_key,
				'metric_label' => isset($metric_labels[$metric_key]) ? (string) $metric_labels[$metric_key] : 'Metrik',
				'range' => $range_key,
				'range_label' => isset($range_labels[$range_key]) ? (string) $range_labels[$range_key] : $range_key,
				'points' => array(),
				'last_value' => 0,
				'prev_value' => 0,
				'change_value' => 0,
				'trend' => 'flat',
				'generated_at' => date('Y-m-d H:i:s')
			);
		}
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
			$month_start_key = date('Y-m-01', $now_ts);
			$today_key = date('Y-m-d', $now_ts);
			$activity_start_key = $this->resolve_admin_activity_start_key($month_start_key, $today_key, $metric_maps);
			$start_month_ts = $activity_start_key !== ''
				? strtotime($activity_start_key.' 00:00:00')
				: FALSE;
			if ($start_month_ts !== FALSE && $start_month_ts <= $today_midnight_ts)
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

		$last_index = count($points) - 1;
		$last_value = $last_index >= 0 && isset($points[$last_index]['value'])
			? (int) $points[$last_index]['value']
			: 0;
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
		if (function_exists('absen_data_store_load_value'))
		{
			$rows = absen_data_store_load_value('attendance_records', NULL, $file_path);
			if (is_array($rows))
			{
				return array_values($rows);
			}
		}

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
		$normalized = array_values(is_array($records) ? $records : array());
		if (function_exists('absen_data_store_save_value'))
		{
			$saved_to_store = absen_data_store_save_value('attendance_records', $normalized, $file_path);
			if ($saved_to_store)
			{
				return;
			}
		}

		$directory = dirname($file_path);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0755, TRUE);
		}
		$payload = json_encode($normalized, JSON_PRETTY_PRINT);
		@file_put_contents($file_path, $payload);
	}

	private function leave_requests_file_path()
	{
		return APPPATH.'cache/leave_requests.json';
	}

	private function load_leave_requests()
	{
		$file_path = $this->leave_requests_file_path();
		if (function_exists('absen_data_store_load_value'))
		{
			$rows = absen_data_store_load_value('leave_requests', NULL, $file_path);
			if (is_array($rows))
			{
				return array_values($rows);
			}
		}

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
		$normalized = array_values(is_array($requests) ? $requests : array());
		if (function_exists('absen_data_store_save_value'))
		{
			$saved_to_store = absen_data_store_save_value('leave_requests', $normalized, $file_path);
			if ($saved_to_store)
			{
				return;
			}
		}

		$directory = dirname($file_path);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0755, TRUE);
		}
		$payload = json_encode($normalized, JSON_PRETTY_PRINT);
		@file_put_contents($file_path, $payload);
	}

	private function loan_requests_file_path()
	{
		return APPPATH.'cache/loan_requests.json';
	}

	private function load_loan_requests()
	{
		$file_path = $this->loan_requests_file_path();
		if (function_exists('absen_data_store_load_value'))
		{
			$rows = absen_data_store_load_value('loan_requests', NULL, $file_path);
			if (is_array($rows))
			{
				return array_values($rows);
			}
		}

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
		$normalized = array_values(is_array($requests) ? $requests : array());
		if (function_exists('absen_data_store_save_value'))
		{
			$saved_to_store = absen_data_store_save_value('loan_requests', $normalized, $file_path);
			if ($saved_to_store)
			{
				return;
			}
		}

		$directory = dirname($file_path);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0755, TRUE);
		}
		$payload = json_encode($normalized, JSON_PRETTY_PRINT);
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
		if (function_exists('absen_data_store_load_value'))
		{
			$rows = absen_data_store_load_value('overtime_records', NULL, $file_path);
			if (is_array($rows))
			{
				return array_values($rows);
			}
		}

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
		$normalized = array_values(is_array($records) ? $records : array());
		if (function_exists('absen_data_store_save_value'))
		{
			$saved_to_store = absen_data_store_save_value('overtime_records', $normalized, $file_path);
			if ($saved_to_store)
			{
				return;
			}
		}

		$directory = dirname($file_path);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0755, TRUE);
		}
		$payload = json_encode($normalized, JSON_PRETTY_PRINT);
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
			if (!$this->is_username_in_actor_scope($username))
			{
				continue;
			}

			$job_title = $this->resolve_employee_job_title(isset($profile['job_title']) ? (string) $profile['job_title'] : '');
			if ($job_title === '')
			{
				$job_title = $this->default_employee_job_title();
			}
			$weekly_day_off_current = isset($profile['weekly_day_off'])
				? $this->resolve_employee_weekly_day_off($profile['weekly_day_off'])
				: $this->default_weekly_day_off();
			$month_policy_current = $this->calculate_month_work_policy(date('Y-m-d'), $weekly_day_off_current);
			$work_days_current = isset($month_policy_current['work_days']) ? (int) $month_policy_current['work_days'] : self::WORK_DAYS_DEFAULT;
			if ($work_days_current <= 0)
			{
				$work_days_current = self::WORK_DAYS_DEFAULT;
			}

			$options[] = array(
				'username' => $username,
				'employee_id' => $this->resolve_employee_id_from_book($username, $id_book),
				'display_name' => isset($profile['display_name']) && trim((string) $profile['display_name']) !== ''
					? (string) $profile['display_name']
					: $username,
				'branch' => isset($profile['branch']) ? (string) $profile['branch'] : $this->default_employee_branch(),
				'cross_branch_enabled' => $this->resolve_cross_branch_enabled_value(
					isset($profile['cross_branch_enabled']) ? $profile['cross_branch_enabled'] : 0
				),
				'phone' => isset($profile['phone']) ? (string) $profile['phone'] : '',
				'shift_name' => isset($profile['shift_name']) ? (string) $profile['shift_name'] : '',
				'shift_key' => $this->resolve_shift_key_from_profile($profile),
				'salary_tier' => isset($profile['salary_tier']) ? (string) $profile['salary_tier'] : '',
				'salary_monthly' => isset($profile['salary_monthly']) ? (int) $profile['salary_monthly'] : 0,
				'job_title' => $job_title,
				'address' => isset($profile['address']) ? (string) $profile['address'] : $this->default_employee_address(),
				'coordinate_point' => isset($profile['coordinate_point']) ? (string) $profile['coordinate_point'] : '',
				'work_days' => $work_days_current,
				'weekly_day_off' => $weekly_day_off_current
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
		$shift_name = isset($profile['shift_name']) ? (string) $profile['shift_name'] : '';
		$shift_time = isset($profile['shift_time']) ? (string) $profile['shift_time'] : '';
		return $this->resolve_shift_key_from_shift_values($shift_name, $shift_time);
	}

	private function resolve_shift_key_from_shift_values($shift_name = '', $shift_time = '')
	{
		$shift_name = strtolower(trim((string) $shift_name));
		$shift_time = strtolower(trim((string) $shift_time));
		if (strpos($shift_name, 'multi') !== FALSE ||
			(strpos($shift_time, '06:30') !== FALSE && strpos($shift_time, '23:59') !== FALSE))
		{
			return 'multishift';
		}
		if (
			strpos($shift_name, 'siang') !== FALSE ||
			strpos($shift_time, '14:00') !== FALSE ||
			strpos($shift_time, '12:00') !== FALSE
		)
		{
			return 'siang';
		}

		return 'pagi';
	}

	private function resolve_shift_check_in_window($shift_key = '')
	{
		$shift_key = strtolower(trim((string) $shift_key));
		if ($shift_key === 'siang')
		{
			return array(
				'start' => '13:30:00',
				'end' => '23:00:00',
				'start_label' => '13:30',
				'end_label' => '23:00'
			);
		}
		if ($shift_key === 'multishift')
		{
			return array(
				'start' => self::CHECK_IN_MIN_TIME,
				'end' => self::CHECK_OUT_MAX_TIME,
				'start_label' => '06:30',
				'end_label' => '23:59'
			);
		}

		return array(
			'start' => '07:30:00',
			'end' => self::CHECK_IN_MAX_TIME,
			'start_label' => '07:30',
			'end_label' => '17:00'
		);
	}

	private function resolve_shift_check_out_window($shift_key = '')
	{
		$shift_key = strtolower(trim((string) $shift_key));
		if ($shift_key === 'multishift')
		{
			return array(
				'end' => self::CHECK_OUT_MAX_TIME,
				'end_label' => '23:59'
			);
		}

		return array(
			'end' => '23:00:00',
			'end_label' => '23:00'
		);
	}

	private function resolve_attendance_branch_for_user($username = '', $profile = array())
	{
		$username_key = strtolower(trim((string) $username));
		$branch_value = '';
		if (is_array($profile) && isset($profile['branch']))
		{
			$branch_value = (string) $profile['branch'];
		}

		if ($branch_value === '' && $username_key !== '')
		{
			$session_username = strtolower(trim((string) $this->session->userdata('absen_username')));
			if ($session_username !== '' && $session_username === $username_key)
			{
				$branch_value = (string) $this->session->userdata('absen_branch');
			}
		}

		if ($branch_value === '' && $username_key !== '')
		{
			$profile_lookup = $this->get_employee_profile($username_key);
			if (is_array($profile_lookup) && isset($profile_lookup['branch']))
			{
				$branch_value = (string) $profile_lookup['branch'];
			}
		}

		$resolved_branch = $this->resolve_employee_branch($branch_value);
		if ($resolved_branch === '')
		{
			$resolved_branch = $this->default_employee_branch();
		}

		return $resolved_branch;
	}

	private function attendance_office_points_for_branch($branch = '')
	{
		$resolved_branch = $this->resolve_employee_branch($branch);
		if ($resolved_branch === '')
		{
			$resolved_branch = $this->default_employee_branch();
		}

		if (strcasecmp($resolved_branch, 'Cadasari') === 0)
		{
			return array(
				array(
					'label' => 'Kantor 2',
					'lat' => (float) self::OFFICE_ALT_LAT,
					'lng' => (float) self::OFFICE_ALT_LNG
				)
			);
		}

		return array(
			array(
				'label' => 'Kantor 1',
				'lat' => (float) self::OFFICE_LAT,
				'lng' => (float) self::OFFICE_LNG
			)
		);
	}

	private function nearest_attendance_office($latitude, $longitude, $branch = '')
	{
		$points = $this->attendance_office_points_for_branch($branch);
		$nearest = array(
			'label' => 'kantor',
			'distance_m' => INF,
			'lat' => (float) self::OFFICE_LAT,
			'lng' => (float) self::OFFICE_LNG
		);

		for ($i = 0; $i < count($points); $i += 1)
		{
			$point = isset($points[$i]) && is_array($points[$i]) ? $points[$i] : array();
			$point_lat = isset($point['lat']) ? (float) $point['lat'] : (float) self::OFFICE_LAT;
			$point_lng = isset($point['lng']) ? (float) $point['lng'] : (float) self::OFFICE_LNG;
			$distance_m = $this->calculate_distance_meter((float) $latitude, (float) $longitude, $point_lat, $point_lng);
			if ($distance_m < $nearest['distance_m'])
			{
				$nearest['distance_m'] = $distance_m;
				$nearest['label'] = isset($point['label']) && trim((string) $point['label']) !== ''
					? (string) $point['label']
					: 'kantor';
				$nearest['lat'] = $point_lat;
				$nearest['lng'] = $point_lng;
			}
		}

		if (!is_finite($nearest['distance_m']))
		{
			$nearest['distance_m'] = $this->calculate_distance_meter(
				(float) $latitude,
				(float) $longitude,
				(float) self::OFFICE_LAT,
				(float) self::OFFICE_LNG
			);
		}

		return $nearest;
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

	private function profile_photo_upload_dir()
	{
		return 'uploads/profile_photo';
	}

	private function upload_employee_profile_photo($field_name, $username_key = '', $is_required = TRUE)
	{
		$parse_size_to_bytes = function ($size_value) {
			$size_text = trim((string) $size_value);
			if ($size_text === '')
			{
				return 0;
			}
			$unit = strtolower(substr($size_text, -1));
			$number = (float) $size_text;
			if ($number <= 0)
			{
				return 0;
			}
			switch ($unit)
			{
				case 'g':
					$number *= 1024;
					// no break
				case 'm':
					$number *= 1024;
					// no break
				case 'k':
					$number *= 1024;
					break;
			}
			return (int) round($number);
		};
		$format_size_label = function ($size_bytes) {
			$bytes = (int) $size_bytes;
			if ($bytes <= 0)
			{
				return '0 MB';
			}
			$size_mb = $bytes / (1024 * 1024);
			if ($size_mb < 1)
			{
				$size_kb = (int) ceil($bytes / 1024);
				return $size_kb.' KB';
			}
			$size_mb_label = number_format($size_mb, $size_mb >= 10 ? 0 : 1, ',', '.');
			return $size_mb_label.' MB';
		};
		$app_max_size_bytes = (int) round(2.3 * 1024 * 1024);
		$server_upload_max_bytes = $parse_size_to_bytes(ini_get('upload_max_filesize'));
		$server_post_max_bytes = $parse_size_to_bytes(ini_get('post_max_size'));
		$effective_max_size_bytes = $app_max_size_bytes;
		if ($server_upload_max_bytes > 0 && $server_upload_max_bytes < $effective_max_size_bytes)
		{
			$effective_max_size_bytes = $server_upload_max_bytes;
		}
		if ($server_post_max_bytes > 0 && $server_post_max_bytes < $effective_max_size_bytes)
		{
			$effective_max_size_bytes = $server_post_max_bytes;
		}

		$file_data = isset($_FILES[$field_name]) && is_array($_FILES[$field_name]) ? $_FILES[$field_name] : NULL;
		if ($file_data === NULL)
		{
			if (!$is_required)
			{
				return array(
					'success' => TRUE,
					'skipped' => TRUE,
					'message' => 'File PP tidak diubah.'
				);
			}
			return array(
				'success' => FALSE,
				'message' => 'PP karyawan wajib diupload.'
			);
		}

		$file_error = isset($file_data['error']) ? (int) $file_data['error'] : UPLOAD_ERR_NO_FILE;
		if ($file_error === UPLOAD_ERR_NO_FILE)
		{
			if (!$is_required)
			{
				return array(
					'success' => TRUE,
					'skipped' => TRUE,
					'message' => 'File PP tidak diubah.'
				);
			}
			return array(
				'success' => FALSE,
				'message' => 'PP karyawan wajib diupload.'
			);
		}
		if ($file_error !== UPLOAD_ERR_OK)
		{
			$error_message = 'Upload PP karyawan gagal. Coba ulangi lagi.';
			switch ($file_error)
			{
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					$error_message = 'Ukuran PP melebihi batas upload (maks '.$format_size_label($effective_max_size_bytes).').';
					break;
				case UPLOAD_ERR_PARTIAL:
					$error_message = 'Upload PP tidak selesai. Coba upload ulang.';
					break;
				case UPLOAD_ERR_NO_TMP_DIR:
					$error_message = 'Folder sementara upload tidak tersedia di server.';
					break;
				case UPLOAD_ERR_CANT_WRITE:
					$error_message = 'Server gagal menulis file upload.';
					break;
				case UPLOAD_ERR_EXTENSION:
					$error_message = 'Upload PP diblokir ekstensi server.';
					break;
			}
			return array(
				'success' => FALSE,
				'message' => $error_message
			);
		}

		$original_name = isset($file_data['name']) ? trim((string) $file_data['name']) : '';
		$file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
		$allowed_extensions = array('png', 'jpg', 'jpeg', 'heic');
		if ($file_ext === '' || !in_array($file_ext, $allowed_extensions, TRUE))
		{
			return array(
				'success' => FALSE,
				'message' => 'Format PP harus png, jpg, jpeg, atau heic.'
			);
		}

		$file_size = isset($file_data['size']) ? (int) $file_data['size'] : 0;
		if ($file_size <= 0 || $file_size > $effective_max_size_bytes)
		{
			return array(
				'success' => FALSE,
				'message' => 'Ukuran PP maksimal '.$format_size_label($effective_max_size_bytes).'.'
			);
		}

		$tmp_name = isset($file_data['tmp_name']) ? (string) $file_data['tmp_name'] : '';
		if ($tmp_name === '' || !is_uploaded_file($tmp_name))
		{
			return array(
				'success' => FALSE,
				'message' => 'File PP tidak valid.'
			);
		}

		$upload_directory_relative = trim($this->profile_photo_upload_dir(), '/\\');
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
				'message' => 'Folder upload PP tidak tersedia.'
			);
		}

		$username_safe = preg_replace('/[^a-z0-9_]+/i', '', strtolower(trim((string) $username_key)));
		if ($username_safe === '')
		{
			$username_safe = 'user';
		}
		$file_name = 'pp_'.$username_safe.'_'.date('YmdHis').'_'.strtolower(substr(md5(uniqid('', TRUE)), 0, 10)).'.'.$file_ext;
		$target_path = rtrim($upload_directory_absolute, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file_name;
		if (!@move_uploaded_file($tmp_name, $target_path))
		{
			return array(
				'success' => FALSE,
				'message' => 'Gagal menyimpan file PP.'
			);
		}

		$final_path = $target_path;
		$optimize_result = $this->optimize_profile_photo_image(
			$target_path,
			$file_ext,
			self::PROFILE_PHOTO_MAX_WIDTH,
			self::PROFILE_PHOTO_MAX_HEIGHT,
			self::PROFILE_PHOTO_JPEG_QUALITY
		);
		if (isset($optimize_result['success']) && $optimize_result['success'] === TRUE &&
			isset($optimize_result['output_path']) && is_file((string) $optimize_result['output_path']))
		{
			$final_path = (string) $optimize_result['output_path'];
		}
		$this->create_profile_photo_thumbnail(
			$final_path,
			self::PROFILE_PHOTO_THUMB_SIZE,
			self::PROFILE_PHOTO_THUMB_JPEG_QUALITY
		);

		$final_file_name = basename($final_path);
		$final_file_ext = strtolower(pathinfo($final_file_name, PATHINFO_EXTENSION));

		return array(
			'success' => TRUE,
			'file_name' => $final_file_name,
			'original_name' => $original_name,
			'relative_path' => '/'.str_replace('\\', '/', $upload_directory_relative.'/'.$final_file_name),
			'file_ext' => $final_file_ext !== '' ? $final_file_ext : $file_ext
		);
	}

	private function can_process_profile_photo_image()
	{
		return function_exists('imagecreatetruecolor') &&
			function_exists('imagecopyresampled') &&
			function_exists('imagejpeg');
	}

	private function create_profile_photo_image_resource($file_path, $file_ext = '')
	{
		$path = trim((string) $file_path);
		if ($path === '' || !is_file($path))
		{
			return NULL;
		}

		$ext = strtolower(trim((string) $file_ext));
		if ($ext === '')
		{
			$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		}

		if (($ext === 'jpg' || $ext === 'jpeg') && function_exists('imagecreatefromjpeg'))
		{
			return @imagecreatefromjpeg($path);
		}
		if ($ext === 'png' && function_exists('imagecreatefrompng'))
		{
			return @imagecreatefrompng($path);
		}
		if ($ext === 'webp' && function_exists('imagecreatefromwebp'))
		{
			return @imagecreatefromwebp($path);
		}

		if (!function_exists('getimagesize'))
		{
			return NULL;
		}

		$image_info = @getimagesize($path);
		$mime = isset($image_info['mime']) ? strtolower(trim((string) $image_info['mime'])) : '';
		if (($mime === 'image/jpeg' || $mime === 'image/jpg') && function_exists('imagecreatefromjpeg'))
		{
			return @imagecreatefromjpeg($path);
		}
		if ($mime === 'image/png' && function_exists('imagecreatefrompng'))
		{
			return @imagecreatefrompng($path);
		}
		if ($mime === 'image/webp' && function_exists('imagecreatefromwebp'))
		{
			return @imagecreatefromwebp($path);
		}

		return NULL;
	}

	private function optimize_profile_photo_image($source_path, $source_ext = '', $max_width = 512, $max_height = 512, $jpeg_quality = 82)
	{
		$path = trim((string) $source_path);
		if ($path === '' || !is_file($path))
		{
			return array('success' => FALSE, 'output_path' => $path);
		}

		if (!$this->can_process_profile_photo_image())
		{
			return array('success' => FALSE, 'output_path' => $path);
		}

		$source_image = $this->create_profile_photo_image_resource($path, $source_ext);
		if (!$source_image)
		{
			return array('success' => FALSE, 'output_path' => $path);
		}

		$source_width = (int) @imagesx($source_image);
		$source_height = (int) @imagesy($source_image);
		if ($source_width <= 0 || $source_height <= 0)
		{
			@imagedestroy($source_image);
			return array('success' => FALSE, 'output_path' => $path);
		}

		$max_w = max(128, (int) $max_width);
		$max_h = max(128, (int) $max_height);
		$scale = min(1, $max_w / $source_width, $max_h / $source_height);
		$target_width = max(1, (int) round($source_width * $scale));
		$target_height = max(1, (int) round($source_height * $scale));

		$target_image = @imagecreatetruecolor($target_width, $target_height);
		if (!$target_image)
		{
			@imagedestroy($source_image);
			return array('success' => FALSE, 'output_path' => $path);
		}

		$white = imagecolorallocate($target_image, 255, 255, 255);
		imagefilledrectangle($target_image, 0, 0, $target_width, $target_height, $white);
		imagecopyresampled(
			$target_image,
			$source_image,
			0,
			0,
			0,
			0,
			$target_width,
			$target_height,
			$source_width,
			$source_height
		);

		$quality = max(55, min(90, (int) $jpeg_quality));
		$output_path = preg_replace('/\.[^.]+$/', '.jpg', $path);
		if (!is_string($output_path) || trim($output_path) === '')
		{
			$output_path = $path.'.jpg';
		}

		$saved = @imagejpeg($target_image, $output_path, $quality);
		@imagedestroy($target_image);
		@imagedestroy($source_image);
		if (!$saved || !is_file($output_path))
		{
			return array('success' => FALSE, 'output_path' => $path);
		}

		if ($output_path !== $path && is_file($path))
		{
			@unlink($path);
		}

		return array(
			'success' => TRUE,
			'output_path' => $output_path
		);
	}

	private function create_profile_photo_thumbnail($source_path, $thumb_size = 160, $jpeg_quality = 76)
	{
		$path = trim((string) $source_path);
		if ($path === '' || !is_file($path) || !$this->can_process_profile_photo_image())
		{
			return FALSE;
		}

		$source_image = $this->create_profile_photo_image_resource($path);
		if (!$source_image)
		{
			return FALSE;
		}

		$source_width = (int) @imagesx($source_image);
		$source_height = (int) @imagesy($source_image);
		if ($source_width <= 0 || $source_height <= 0)
		{
			@imagedestroy($source_image);
			return FALSE;
		}

		$crop_side = min($source_width, $source_height);
		$src_x = (int) floor(($source_width - $crop_side) / 2);
		$src_y = (int) floor(($source_height - $crop_side) / 2);
		$target_side = max(64, (int) $thumb_size);
		$thumb_image = @imagecreatetruecolor($target_side, $target_side);
		if (!$thumb_image)
		{
			@imagedestroy($source_image);
			return FALSE;
		}

		$white = imagecolorallocate($thumb_image, 255, 255, 255);
		imagefilledrectangle($thumb_image, 0, 0, $target_side, $target_side, $white);
		imagecopyresampled(
			$thumb_image,
			$source_image,
			0,
			0,
			$src_x,
			$src_y,
			$target_side,
			$target_side,
			$crop_side,
			$crop_side
		);

		$path_info = pathinfo($path);
		$base_name = isset($path_info['filename']) ? (string) $path_info['filename'] : '';
		$directory = isset($path_info['dirname']) ? (string) $path_info['dirname'] : '';
		if ($base_name === '' || $directory === '')
		{
			@imagedestroy($thumb_image);
			@imagedestroy($source_image);
			return FALSE;
		}
		$thumb_path = $directory.DIRECTORY_SEPARATOR.$base_name.'_thumb.jpg';
		$quality = max(50, min(90, (int) $jpeg_quality));
		$saved = @imagejpeg($thumb_image, $thumb_path, $quality);
		@imagedestroy($thumb_image);
		@imagedestroy($source_image);

		return $saved && is_file($thumb_path);
	}

	private function normalize_coordinate_point($coordinate_value)
	{
		$raw = trim((string) $coordinate_value);
		if ($raw === '')
		{
			return '';
		}

		$raw = str_replace(array("\r", "\n", "\t"), ' ', $raw);
		$raw = str_replace(';', ',', $raw);
		$raw = preg_replace('/\s+/', ' ', $raw);
		$parts = array();
		if (strpos($raw, ',') !== FALSE)
		{
			$parts = explode(',', $raw);
		}
		else
		{
			$parts = preg_split('/\s+/', $raw);
		}
		if (!is_array($parts) || count($parts) < 2)
		{
			return '';
		}

		$latitude_text = trim((string) $parts[0]);
		$longitude_text = trim((string) $parts[1]);
		if ($latitude_text === '' || $longitude_text === '')
		{
			return '';
		}
		if (!is_numeric($latitude_text) || !is_numeric($longitude_text))
		{
			return '';
		}

		$latitude = (float) $latitude_text;
		$longitude = (float) $longitude_text;
		if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180)
		{
			return '';
		}

		return $this->format_coordinate_number($latitude).', '.$this->format_coordinate_number($longitude);
	}

	private function format_coordinate_number($value)
	{
		$number = number_format((float) $value, 8, '.', '');
		$number = rtrim(rtrim($number, '0'), '.');
		if ($number === '-0')
		{
			return '0';
		}

		return $number !== '' ? $number : '0';
	}

	private function delete_local_uploaded_file($relative_path)
	{
		$path_value = trim((string) $relative_path);
		if ($path_value === '')
		{
			return;
		}
		if (strpos($path_value, 'data:') === 0 || preg_match('/^https?:\/\//i', $path_value) === 1)
		{
			return;
		}

		$absolute_path = rtrim((string) FCPATH, '/\\').DIRECTORY_SEPARATOR.
			str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, ltrim($path_value, '/\\'));
		if (is_file($absolute_path))
		{
			@unlink($absolute_path);
		}

		$path_info = pathinfo($absolute_path);
		$base_name = isset($path_info['filename']) ? (string) $path_info['filename'] : '';
		$directory = isset($path_info['dirname']) ? (string) $path_info['dirname'] : '';
		if ($base_name !== '' && $directory !== '')
		{
			$thumb_path = $directory.DIRECTORY_SEPARATOR.$base_name.'_thumb.jpg';
			if (is_file($thumb_path))
			{
				@unlink($thumb_path);
			}
		}
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

	private function weekly_day_off_options()
	{
		return array(
			1 => 'Senin',
			2 => 'Selasa',
			3 => 'Rabu',
			4 => 'Kamis',
			5 => 'Jumat',
			6 => 'Sabtu',
			7 => 'Minggu'
		);
	}

	private function default_weekly_day_off()
	{
		$weekly_day_off = (int) self::WEEKLY_HOLIDAY_DAY;
		if ($weekly_day_off === 0)
		{
			return 7;
		}
		if ($weekly_day_off < 1 || $weekly_day_off > 7)
		{
			return 1;
		}

		return $weekly_day_off;
	}

	private function resolve_employee_weekly_day_off($weekly_day_off)
	{
		if ($weekly_day_off === NULL)
		{
			return $this->default_weekly_day_off();
		}

		$weekly_day_off_text = trim((string) $weekly_day_off);
		if ($weekly_day_off_text === '')
		{
			return $this->default_weekly_day_off();
		}

		return $this->normalize_weekly_day_off((int) $weekly_day_off_text);
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

	private function resolve_cross_branch_enabled_value($value)
	{
		if (function_exists('absen_resolve_cross_branch_enabled'))
		{
			return ((int) absen_resolve_cross_branch_enabled($value)) === 1 ? 1 : 0;
		}

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

	private function dashboard_role_label($username = '')
	{
		$username_key = strtolower(trim((string) $username));
		if ($username_key === '')
		{
			$username_key = $this->current_actor_username();
		}

		if ($username_key === 'developer')
		{
			return 'Developer';
		}

		if ($username_key === 'bos')
		{
			return 'Bos';
		}

		return 'Admin';
	}

	private function dashboard_navbar_title($username = '')
	{
		return 'Dashboard '.$this->dashboard_role_label($username).' Absen';
	}

	private function dashboard_status_label()
	{
		return 'Ringkasan Operasional Harian';
	}

	private function privileged_admin_usernames()
	{
		return array('developer', 'bos');
	}

	private function allowed_privileged_password_targets($actor_username = '')
	{
		$actor = strtolower(trim((string) $actor_username));
		if ($actor === '')
		{
			$actor = $this->current_actor_username();
		}
		return $this->manageable_admin_targets_for_actor($actor);
	}

	private function build_privileged_password_target_options($actor_username = '')
	{
		$targets = $this->allowed_privileged_password_targets($actor_username);
		if (empty($targets))
		{
			return array();
		}

		$accounts = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		$options = array();
		for ($i = 0; $i < count($targets); $i += 1)
		{
			$username = strtolower(trim((string) $targets[$i]));
			if ($username === '')
			{
				continue;
			}

			$display_name = $username;
			$login_label = $username;
			if (isset($accounts[$username]) && is_array($accounts[$username]))
			{
				$row = $accounts[$username];
				$display_name_value = isset($row['display_name']) ? trim((string) $row['display_name']) : '';
				if ($display_name_value !== '')
				{
					$display_name = $display_name_value;
				}
				if ($username === 'admin')
				{
					$login_alias = $this->normalize_username_key(isset($row['login_alias']) ? (string) $row['login_alias'] : '');
					if ($login_alias !== '')
					{
						$login_label = $login_alias;
					}
				}
			}

			$label = $display_name;
			if ($login_label !== '')
			{
				$label .= ' ('.$login_label.')';
			}
			$options[] = array(
				'username' => $username,
				'label' => $label
			);
		}

		return $options;
	}

	private function admin_feature_permission_catalog()
	{
		return array(
			'manage_accounts' => 'Kelola akun karyawan',
			'sync_sheet_accounts' => 'Sync akun dari sheet',
			'view_log_data' => 'Akses log data'
		);
	}

	private function normalize_admin_feature_permissions($value)
	{
		$catalog = $this->admin_feature_permission_catalog();
		$allowed_keys = array_keys($catalog);
		$raw_items = array();
		if (is_array($value))
		{
			$raw_items = $value;
		}
		elseif (is_string($value))
		{
			$raw_text = trim($value);
			if ($raw_text !== '')
			{
				$raw_items = preg_split('/[\s,;|]+/', $raw_text);
			}
		}

		$permissions = array();
		for ($i = 0; $i < count($raw_items); $i += 1)
		{
			$key = strtolower(trim((string) $raw_items[$i]));
			if ($key === '' || !in_array($key, $allowed_keys, TRUE))
			{
				continue;
			}
			if (!in_array($key, $permissions, TRUE))
			{
				$permissions[] = $key;
			}
		}

		return $permissions;
	}

	private function actor_admin_feature_permissions($actor_username = '')
	{
		$actor = strtolower(trim((string) $actor_username));
		if ($actor === '')
		{
			$actor = $this->current_actor_username();
		}
		if ($actor === '')
		{
			return array();
		}
		if (in_array($actor, $this->privileged_admin_usernames(), TRUE))
		{
			return array_keys($this->admin_feature_permission_catalog());
		}

		$accounts = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (!isset($accounts[$actor]) || !is_array($accounts[$actor]))
		{
			return array();
		}
		$role = strtolower(trim((string) (isset($accounts[$actor]['role']) ? $accounts[$actor]['role'] : 'user')));
		if ($role !== 'admin')
		{
			return array();
		}

		return $this->normalize_admin_feature_permissions(
			isset($accounts[$actor]['feature_permissions']) ? $accounts[$actor]['feature_permissions'] : array()
		);
	}

	private function actor_has_admin_feature($feature_key)
	{
		$feature = strtolower(trim((string) $feature_key));
		if ($feature === '')
		{
			return FALSE;
		}
		if ($this->can_access_super_admin_features())
		{
			return TRUE;
		}

		$permissions = $this->actor_admin_feature_permissions();
		return in_array($feature, $permissions, TRUE);
	}

	private function can_manage_employee_accounts()
	{
		return $this->actor_has_admin_feature('manage_accounts');
	}

	private function can_sync_sheet_accounts_feature()
	{
		return $this->actor_has_admin_feature('sync_sheet_accounts');
	}

	private function can_view_log_data_feature()
	{
		return $this->actor_has_admin_feature('view_log_data');
	}

	private function manageable_admin_targets_for_actor($actor_username = '')
	{
		$actor = strtolower(trim((string) $actor_username));
		if ($actor === '')
		{
			$actor = $this->current_actor_username();
		}
		if (!in_array($actor, $this->privileged_admin_usernames(), TRUE))
		{
			return array();
		}

		$accounts = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		$targets = array();
		foreach ($accounts as $username => $row)
		{
			$username_key = strtolower(trim((string) $username));
			if ($username_key === '' || !is_array($row))
			{
				continue;
			}
			$role = strtolower(trim((string) (isset($row['role']) ? $row['role'] : 'user')));
			if ($role !== 'admin')
			{
				continue;
			}
			if ($actor === 'bos' && $username_key === 'developer')
			{
				continue;
			}
			$targets[] = $username_key;
		}

		$priority = array('developer' => 1, 'admin' => 2, 'bos' => 3);
		usort($targets, function ($a, $b) use ($priority) {
			$ap = isset($priority[$a]) ? (int) $priority[$a] : 99;
			$bp = isset($priority[$b]) ? (int) $priority[$b] : 99;
			if ($ap !== $bp)
			{
				return $ap - $bp;
			}
			return strcmp($a, $b);
		});

		return array_values(array_unique($targets));
	}

	private function build_manageable_admin_feature_account_options()
	{
		$targets = $this->manageable_admin_targets_for_actor();
		if (empty($targets))
		{
			return array();
		}
		$actor = $this->current_actor_username();
		$accounts = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		$options = array();
		for ($i = 0; $i < count($targets); $i += 1)
		{
			$username = (string) $targets[$i];
			if (in_array($username, $this->privileged_admin_usernames(), TRUE))
			{
				if (!($actor === 'developer' && $username === 'bos'))
				{
					continue;
				}
			}
			if (!isset($accounts[$username]) || !is_array($accounts[$username]))
			{
				continue;
			}
			$row = $accounts[$username];
			$options[] = array(
				'username' => $username,
				'display_name' => isset($row['display_name']) && trim((string) $row['display_name']) !== ''
					? trim((string) $row['display_name'])
					: $username,
				'feature_permissions' => $this->normalize_admin_feature_permissions(
					isset($row['feature_permissions']) ? $row['feature_permissions'] : array()
				)
			);
		}

		return $options;
	}

	private function current_actor_username()
	{
		return strtolower(trim((string) $this->session->userdata('absen_username')));
	}

	private function is_developer_actor()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			return FALSE;
		}

		$role = strtolower(trim((string) $this->session->userdata('absen_role')));
		if ($role !== 'admin')
		{
			return FALSE;
		}

		return $this->current_actor_username() === 'developer';
	}

	private function is_current_session_expired()
	{
		$last_activity_at = (int) $this->session->userdata('absen_last_activity_at');
		$timeout_seconds = function_exists('absen_password_session_timeout_seconds')
			? (int) absen_password_session_timeout_seconds()
			: 1800;
		if (function_exists('absen_session_is_expired'))
		{
			return absen_session_is_expired($last_activity_at, $timeout_seconds);
		}

		return $timeout_seconds > 0 && $last_activity_at > 0 && (time() - $last_activity_at) > $timeout_seconds;
	}

	private function is_force_password_route()
	{
		$method = '';
		if (isset($this->router) && method_exists($this->router, 'fetch_method'))
		{
			$method = strtolower(trim((string) $this->router->fetch_method()));
		}

		return in_array($method, array('force_change_password', 'submit_force_change_password'), TRUE);
	}

	private function sync_session_actor_profile()
	{
		$username_key = $this->current_actor_username();
		if ($username_key === '')
		{
			return FALSE;
		}

		$accounts = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (!is_array($accounts) || !isset($accounts[$username_key]) || !is_array($accounts[$username_key]))
		{
			return FALSE;
		}

		$account = $accounts[$username_key];
		$role_value = isset($account['role']) ? strtolower(trim((string) $account['role'])) : 'user';
		if ($role_value !== 'admin')
		{
			$role_value = 'user';
		}

		$branch_value = isset($account['branch']) ? trim((string) $account['branch']) : '';
		if ($role_value === 'user' && $branch_value === '')
		{
			$branch_value = $this->default_employee_branch();
		}
		if ($role_value === 'admin' && $username_key === 'admin' && $branch_value === '')
		{
			$branch_value = $this->default_employee_branch();
		}

		$requires_password_change = function_exists('absen_account_requires_password_change')
			? absen_account_requires_password_change($account)
			: FALSE;
		$feature_permissions = $this->normalize_admin_feature_permissions(
			isset($account['feature_permissions']) ? $account['feature_permissions'] : array()
		);

		$this->session->set_userdata(array(
			'absen_role' => $role_value,
			'absen_display_name' => isset($account['display_name']) && trim((string) $account['display_name']) !== ''
				? (string) $account['display_name']
				: $username_key,
			'absen_shift_name' => isset($account['shift_name']) ? (string) $account['shift_name'] : '',
			'absen_shift_time' => isset($account['shift_time']) ? (string) $account['shift_time'] : '',
			'absen_phone' => isset($account['phone']) ? (string) $account['phone'] : '',
			'absen_branch' => $branch_value,
			'absen_salary_tier' => isset($account['salary_tier']) ? (string) $account['salary_tier'] : '',
			'absen_salary_monthly' => isset($account['salary_monthly']) ? (int) $account['salary_monthly'] : 0,
			'absen_work_days' => isset($account['work_days']) ? (int) $account['work_days'] : 0,
			'absen_weekly_day_off' => isset($account['weekly_day_off']) ? (int) $account['weekly_day_off'] : $this->default_weekly_day_off(),
			'absen_feature_permissions' => implode(',', $feature_permissions),
			'absen_password_change_required' => $requires_password_change ? 1 : 0
		));

		return TRUE;
	}

	private function can_access_super_admin_features()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			return FALSE;
		}

		$role = strtolower(trim((string) $this->session->userdata('absen_role')));
		if ($role === '' || $role === 'user')
		{
			return FALSE;
		}

		$actor = $this->current_actor_username();
		if ($actor === '')
		{
			return FALSE;
		}

		return in_array($actor, $this->privileged_admin_usernames(), TRUE);
	}

	private function is_branch_scoped_admin()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			return FALSE;
		}

		$role = strtolower(trim((string) $this->session->userdata('absen_role')));
		if ($role !== 'admin')
		{
			return FALSE;
		}

		$actor = $this->current_actor_username();
		if ($actor !== 'admin')
		{
			return FALSE;
		}

		return !$this->can_access_super_admin_features();
	}

	private function current_actor_branch()
	{
		$branch = $this->resolve_employee_branch($this->session->userdata('absen_branch'));
		if ($branch !== '')
		{
			return $branch;
		}

		$actor = $this->current_actor_username();
		if ($actor === '')
		{
			return '';
		}

		$accounts = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (isset($accounts[$actor]) && is_array($accounts[$actor]))
		{
			$account_branch = $this->resolve_employee_branch(isset($accounts[$actor]['branch']) ? (string) $accounts[$actor]['branch'] : '');
			if ($account_branch !== '')
			{
				return $account_branch;
			}
		}

		return $actor === 'admin' ? $this->default_employee_branch() : '';
	}

	private function is_username_in_actor_scope($username)
	{
		$username_key = strtolower(trim((string) $username));
		if ($username_key === '')
		{
			return FALSE;
		}

		if (!$this->is_branch_scoped_admin())
		{
			return TRUE;
		}

		$profile = $this->get_employee_profile($username_key);
		$target_branch = $this->resolve_employee_branch(isset($profile['branch']) ? (string) $profile['branch'] : '');
		if ($target_branch === '')
		{
			$target_branch = $this->default_employee_branch();
		}
		$scope_branch = $this->current_actor_branch();
		if ($scope_branch === '')
		{
			return FALSE;
		}

		return strcasecmp($target_branch, $scope_branch) === 0;
	}

	private function scoped_employee_lookup()
	{
		$profiles = $this->employee_profile_book();
		$lookup = array();
		foreach ($profiles as $username_key => $profile)
		{
			$username = strtolower(trim((string) $username_key));
			if ($username === '')
			{
				continue;
			}
			if (!$this->is_username_in_actor_scope($username))
			{
				continue;
			}
			$lookup[$username] = TRUE;
		}

		return $lookup;
	}

	private function scoped_employee_usernames()
	{
		$lookup = $this->scoped_employee_lookup();
		$usernames = array_keys($lookup);
		sort($usernames);
		return $usernames;
	}

	private function is_reserved_system_username($username)
	{
		$username_key = strtolower(trim((string) $username));
		if ($username_key === '')
		{
			return FALSE;
		}

		if ($username_key === 'admin')
		{
			return TRUE;
		}

		return in_array($username_key, $this->privileged_admin_usernames(), TRUE);
	}

	private function employee_profile_book($force_refresh = FALSE)
	{
		static $profile_cache = NULL;
		if (!$force_refresh && is_array($profile_cache))
		{
			return $profile_cache;
		}

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
			if ($this->is_reserved_system_username($username_key))
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
				'display_name' => isset($account['display_name']) && trim((string) $account['display_name']) !== ''
					? trim((string) $account['display_name'])
					: $username_key,
				'branch' => $this->resolve_employee_branch(isset($account['branch']) ? (string) $account['branch'] : ''),
				'cross_branch_enabled' => $this->resolve_cross_branch_enabled_value(
					isset($account['cross_branch_enabled']) ? $account['cross_branch_enabled'] : 0
				),
				'phone' => isset($account['phone']) ? (string) $account['phone'] : '',
				'shift_name' => isset($account['shift_name']) ? (string) $account['shift_name'] : 'Shift Pagi - Sore',
				'shift_time' => isset($account['shift_time']) ? (string) $account['shift_time'] : '08:00 - 23:00',
				'job_title' => $job_title,
				'salary_tier' => isset($account['salary_tier']) ? (string) $account['salary_tier'] : 'A',
				'salary_monthly' => isset($account['salary_monthly']) ? (int) $account['salary_monthly'] : 0,
				'work_days' => isset($account['work_days']) ? (int) $account['work_days'] : 28,
				'weekly_day_off' => $this->resolve_employee_weekly_day_off(isset($account['weekly_day_off']) ? $account['weekly_day_off'] : NULL),
				'profile_photo' => isset($account['profile_photo']) ? (string) $account['profile_photo'] : $this->default_employee_profile_photo(),
				'coordinate_point' => isset($account['coordinate_point']) ? trim((string) $account['coordinate_point']) : '',
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
		$profile_cache = $profiles;
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
			if (!isset($profile['coordinate_point']))
			{
				$profile['coordinate_point'] = '';
			}
			if (!isset($profile['cross_branch_enabled']))
			{
				$profile['cross_branch_enabled'] = 0;
			}
			$profile['cross_branch_enabled'] = $this->resolve_cross_branch_enabled_value($profile['cross_branch_enabled']);
			if (!isset($profile['weekly_day_off']))
			{
				$profile['weekly_day_off'] = $this->default_weekly_day_off();
			}
			else
			{
				$profile['weekly_day_off'] = $this->resolve_employee_weekly_day_off($profile['weekly_day_off']);
			}

			return $profile;
		}

		return array(
			'profile_photo' => $this->default_employee_profile_photo(),
			'address' => $this->default_employee_address(),
			'job_title' => $this->default_employee_job_title(),
			'cross_branch_enabled' => 0,
			'coordinate_point' => '',
			'weekly_day_off' => $this->default_weekly_day_off()
		);
	}

	private function employee_phone_book($force_refresh = FALSE)
	{
		static $phone_cache = NULL;
		if (!$force_refresh && is_array($phone_cache))
		{
			return $phone_cache;
		}

		$profiles = $this->employee_profile_book($force_refresh);
		$phone_book = array();
		foreach ($profiles as $username => $profile)
		{
			$phone_book[(string) $username] = isset($profile['phone']) ? (string) $profile['phone'] : '';
		}

		$phone_cache = $phone_book;
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
		if (function_exists('absen_normalize_phone_number'))
		{
			return absen_normalize_phone_number($phone);
		}

		$digits = preg_replace('/\D+/', '', (string) $phone);
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
		$group_name_alias = '';
		if (stripos($phone_raw, 'group:') === 0)
		{
			$group_name_alias = trim(substr($phone_raw, 6));
			$phone_raw = $group_name_alias;
		}
		elseif (stripos($phone_raw, 'group_name:') === 0)
		{
			$group_name_alias = trim(substr($phone_raw, 11));
			$phone_raw = $group_name_alias;
		}

		$is_group_target = (strpos($phone_raw, '@g.us') !== FALSE) || (strpos($phone_raw, '@broadcast') !== FALSE);
		if (!$is_group_target && $group_name_alias !== '')
		{
			$is_group_target = TRUE;
		}
		if (
			!$is_group_target &&
			preg_match('/[a-z]/i', $phone_raw) === 1 &&
			preg_match('/\d/', $phone_raw) !== 1
		)
		{
			$is_group_target = TRUE;
			$group_name_alias = $phone_raw;
		}

		$phone_normalized = $is_group_target ? $phone_raw : $this->normalize_phone_number($phone_raw);
		if ($is_group_target)
		{
			$normalized_group_target = $this->normalize_fonnte_group_id($phone_normalized);
			if ($normalized_group_target !== '')
			{
				$phone_normalized = $normalized_group_target;
			}
		}
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
			$payload_data = array(
				'phone' => $phone_normalized,
				'message' => $message
			);
			if ($is_group_target)
			{
				$payload_data['target'] = $phone_normalized;
			}
			if ($group_name_alias !== '')
			{
				$payload_data['group'] = $group_name_alias;
				$payload_data['group_name'] = $group_name_alias;
			}
			$payload = json_encode($payload_data);
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
			if (
				$is_group_target &&
				strpos($phone_normalized, '@g.us') === FALSE &&
				strpos($phone_normalized, '@broadcast') === FALSE
			)
			{
				$resolve_group_error = '';
				$resolved_group_target = $this->resolve_fonnte_group_target($phone_normalized, $fonnte_token, $resolve_group_error);
				if ($resolved_group_target === '')
				{
					$error_text = $resolve_group_error !== ''
						? $resolve_group_error
						: 'Target grup untuk Fonnte harus Group ID WhatsApp (akhiran @g.us).';
					return array(
						'success' => FALSE,
						'message' => $error_text
					);
				}
				$phone_normalized = $resolved_group_target;
			}

			$payload_data = array(
				'target' => $phone_normalized,
				'message' => $message
			);
			if (!$is_group_target)
			{
				$payload_data['countryCode'] = '62';
			}
			$payload = http_build_query($payload_data);

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

	private function resolve_fonnte_group_target($group_target, $fonnte_token, &$error_message = '')
	{
		$error_message = '';
		$target_value = trim((string) $group_target);
		$fonnte_token = trim((string) $fonnte_token);
		if ($target_value === '')
		{
			$error_message = 'Target grup WhatsApp kosong.';
			return '';
		}
		if ($fonnte_token === '')
		{
			$error_message = 'Token Fonnte kosong.';
			return '';
		}

		$normalized_group_id = $this->normalize_fonnte_group_id($target_value);
		if ($normalized_group_id !== '')
		{
			return $normalized_group_id;
		}

		$lookup = $this->fetch_fonnte_group_list($fonnte_token, FALSE);
		$lookup_success = isset($lookup['success']) && $lookup['success'] === TRUE;
		$groups = isset($lookup['groups']) && is_array($lookup['groups']) ? $lookup['groups'] : array();
		if (empty($groups))
		{
			$this->refresh_fonnte_group_list($fonnte_token);
			$lookup = $this->fetch_fonnte_group_list($fonnte_token, TRUE);
			$lookup_success = isset($lookup['success']) && $lookup['success'] === TRUE;
			$groups = isset($lookup['groups']) && is_array($lookup['groups']) ? $lookup['groups'] : array();
		}

		$matched_group_id = $this->find_fonnte_group_id_by_name($target_value, $groups);
		if ($matched_group_id !== '')
		{
			return $matched_group_id;
		}

		$base_error = isset($lookup['message']) ? trim((string) $lookup['message']) : '';
		if (!$lookup_success && $base_error !== '')
		{
			$error_message = $base_error;
			return '';
		}

		$error_message = 'Group "'.$target_value.'" tidak ditemukan di Fonnte. Isi WA_ATTENDANCE_GROUP_TARGET dengan Group ID (akhiran @g.us) atau update list group Fonnte dulu.';
		return '';
	}

	private function fetch_fonnte_group_list($fonnte_token, $force_refresh = FALSE)
	{
		static $cache = array();
		$fonnte_token = trim((string) $fonnte_token);
		$cache_key = sha1($fonnte_token);
		if (!$force_refresh && isset($cache[$cache_key]) && is_array($cache[$cache_key]))
		{
			return $cache[$cache_key];
		}

		$response = $this->http_post_request(
			'https://api.fonnte.com/get-whatsapp-group',
			'',
			array(
				'Authorization: '.$fonnte_token,
				'Content-Type: application/x-www-form-urlencoded'
			)
		);
		if (!$response['success'])
		{
			$result = array(
				'success' => FALSE,
				'groups' => array(),
				'message' => isset($response['message']) ? (string) $response['message'] : 'Gagal mengambil list group Fonnte.'
			);
			$cache[$cache_key] = $result;
			return $result;
		}

		$decoded = json_decode(isset($response['body']) ? (string) $response['body'] : '', TRUE);
		if (!is_array($decoded))
		{
			$result = array(
				'success' => FALSE,
				'groups' => array(),
				'message' => 'Respons list group Fonnte tidak valid.'
			);
			$cache[$cache_key] = $result;
			return $result;
		}

		if (isset($decoded['status']) && $decoded['status'] === FALSE)
		{
			$reason = isset($decoded['reason']) ? trim((string) $decoded['reason']) : '';
			$result = array(
				'success' => FALSE,
				'groups' => array(),
				'message' => $reason !== '' ? 'Fonnte gagal: '.$reason : 'Fonnte gagal mengambil list group.'
			);
			$cache[$cache_key] = $result;
			return $result;
		}

		$groups = $this->extract_fonnte_group_entries($decoded);
		$result = array(
			'success' => TRUE,
			'groups' => $groups,
			'message' => 'OK'
		);
		$cache[$cache_key] = $result;
		return $result;
	}

	private function refresh_fonnte_group_list($fonnte_token)
	{
		$fonnte_token = trim((string) $fonnte_token);
		if ($fonnte_token === '')
		{
			return array(
				'success' => FALSE,
				'message' => 'Token Fonnte kosong.'
			);
		}

		$response = $this->http_post_request(
			'https://api.fonnte.com/fetch-group',
			'',
			array(
				'Authorization: '.$fonnte_token,
				'Content-Type: application/x-www-form-urlencoded'
			)
		);
		if (!$response['success'])
		{
			return array(
				'success' => FALSE,
				'message' => isset($response['message']) ? (string) $response['message'] : 'Gagal refresh list group Fonnte.'
			);
		}

		$decoded = json_decode(isset($response['body']) ? (string) $response['body'] : '', TRUE);
		if (is_array($decoded) && isset($decoded['status']) && $decoded['status'] === FALSE)
		{
			$reason = isset($decoded['reason']) ? trim((string) $decoded['reason']) : '';
			return array(
				'success' => FALSE,
				'message' => $reason !== '' ? 'Fonnte gagal: '.$reason : 'Fonnte gagal refresh list group.'
			);
		}

		return array(
			'success' => TRUE,
			'message' => 'OK'
		);
	}

	private function extract_fonnte_group_entries($value)
	{
		$candidates = array();
		$this->collect_fonnte_group_entries($value, $candidates);

		$unique_groups = array();
		for ($i = 0; $i < count($candidates); $i += 1)
		{
			$item = isset($candidates[$i]) && is_array($candidates[$i]) ? $candidates[$i] : array();
			$group_id = isset($item['id']) ? trim((string) $item['id']) : '';
			$group_name = isset($item['name']) ? trim((string) $item['name']) : '';
			if ($group_id === '')
			{
				continue;
			}

			$cache_key = strtolower($group_id);
			if (!isset($unique_groups[$cache_key]))
			{
				$unique_groups[$cache_key] = array(
					'id' => $group_id,
					'name' => $group_name
				);
				continue;
			}

			if ($group_name !== '' && trim((string) $unique_groups[$cache_key]['name']) === '')
			{
				$unique_groups[$cache_key]['name'] = $group_name;
			}
		}

		return array_values($unique_groups);
	}

	private function collect_fonnte_group_entries($value, &$groups)
	{
		if (!is_array($value))
		{
			return;
		}

		$name = '';
		$name_keys = array('name', 'group_name', 'groupname', 'nama', 'subject');
		for ($i = 0; $i < count($name_keys); $i += 1)
		{
			$key = $name_keys[$i];
			if (isset($value[$key]) && trim((string) $value[$key]) !== '')
			{
				$name = trim((string) $value[$key]);
				break;
			}
		}

		$id = '';
		$id_keys = array('id', 'group_id', 'groupid', 'jid', 'target', 'group');
		for ($i = 0; $i < count($id_keys); $i += 1)
		{
			$key = $id_keys[$i];
			if (isset($value[$key]) && trim((string) $value[$key]) !== '')
			{
				$id = trim((string) $value[$key]);
				break;
			}
		}

		$id = $this->normalize_fonnte_group_id($id);
		if ($id !== '')
		{
			$groups[] = array(
				'id' => $id,
				'name' => $name
			);
		}

		foreach ($value as $item)
		{
			if (is_array($item))
			{
				$this->collect_fonnte_group_entries($item, $groups);
			}
		}
	}

	private function normalize_fonnte_group_id($value)
	{
		$group_id = trim((string) $value);
		if ($group_id === '')
		{
			return '';
		}

		$group_id_lower = strtolower($group_id);
		if (preg_match('/([0-9\-]+@g\.us)/', $group_id_lower, $matches) === 1)
		{
			return (string) $matches[1];
		}
		if (preg_match('/([0-9\-]+@broadcast)/', $group_id_lower, $matches) === 1)
		{
			return (string) $matches[1];
		}
		if (preg_match('/^\d+\-\d+$/', $group_id) === 1)
		{
			return $group_id.'@g.us';
		}
		if (strpos($group_id_lower, '@broadcast') !== FALSE)
		{
			return $group_id_lower;
		}
		if (strpos($group_id_lower, '@g.us') !== FALSE)
		{
			return $group_id_lower;
		}

		return '';
	}

	private function find_fonnte_group_id_by_name($target_group_name, $groups)
	{
		$target_name = $this->normalize_fonnte_group_name($target_group_name);
		if ($target_name === '' || !is_array($groups) || empty($groups))
		{
			return '';
		}

		$fallback_group_id = '';
		for ($i = 0; $i < count($groups); $i += 1)
		{
			$group = isset($groups[$i]) && is_array($groups[$i]) ? $groups[$i] : array();
			$group_id = isset($group['id']) ? trim((string) $group['id']) : '';
			$group_name = isset($group['name']) ? $this->normalize_fonnte_group_name($group['name']) : '';
			if ($group_id === '' || $group_name === '')
			{
				continue;
			}

			if ($group_name === $target_name)
			{
				return $group_id;
			}

			if (
				$fallback_group_id === '' &&
				(strpos($group_name, $target_name) !== FALSE || strpos($target_name, $group_name) !== FALSE)
			)
			{
				$fallback_group_id = $group_id;
			}
		}

		return $fallback_group_id;
	}

	private function normalize_fonnte_group_name($value)
	{
		$name = strtolower(trim((string) $value));
		if ($name === '')
		{
			return '';
		}

		return preg_replace('/\s+/', ' ', $name);
	}

	private function resolve_attendance_reminder_slot($slot_override = '')
	{
		$allowed_slots = self::ATTENDANCE_REMINDER_SLOTS;
		$slot_text = strtolower(trim((string) $slot_override));
		if ($slot_text !== '')
		{
			$slot_text = str_replace(array('.', '-', '_'), ':', $slot_text);
			if (preg_match('/^\d{4}$/', $slot_text))
			{
				$slot_text = substr($slot_text, 0, 2).':'.substr($slot_text, 2, 2);
			}
			if (preg_match('/^\d{1,2}\:\d{2}$/', $slot_text))
			{
				$parts = explode(':', $slot_text);
				$slot_text = str_pad((string) ((int) $parts[0]), 2, '0', STR_PAD_LEFT).':'.str_pad((string) ((int) $parts[1]), 2, '0', STR_PAD_LEFT);
			}
			return in_array($slot_text, $allowed_slots, TRUE) ? $slot_text : '';
		}

		$current_minutes = ((int) date('H')) * 60 + ((int) date('i'));
		$grace_minutes = (int) self::ATTENDANCE_REMINDER_SLOT_GRACE_MINUTES;
		if ($grace_minutes < 0)
		{
			$grace_minutes = 0;
		}

		$best_slot = '';
		$best_slot_minutes = -1;
		for ($i = 0; $i < count($allowed_slots); $i += 1)
		{
			$candidate = isset($allowed_slots[$i]) ? trim((string) $allowed_slots[$i]) : '';
			if (!preg_match('/^\d{2}\:\d{2}$/', $candidate))
			{
				continue;
			}

			$parts = explode(':', $candidate);
			$candidate_minutes = ((int) $parts[0]) * 60 + ((int) $parts[1]);
			$minute_diff = $current_minutes - $candidate_minutes;
			if ($minute_diff < 0 || $minute_diff > $grace_minutes)
			{
				continue;
			}
			if ($candidate_minutes > $best_slot_minutes)
			{
				$best_slot = $candidate;
				$best_slot_minutes = $candidate_minutes;
			}
		}

		return $best_slot;
	}

	private function resolve_attendance_reminder_group_target(&$error_message = '')
	{
		$error_message = '';
		$group_target = trim((string) getenv('WA_ATTENDANCE_GROUP_TARGET'));
		if ($group_target !== '')
		{
			return $group_target;
		}

		$group_name = trim((string) getenv('WA_ATTENDANCE_GROUP_NAME'));
		if ($group_name !== '')
		{
			return 'group:'.$group_name;
		}

		$error_message = 'Isi WA_ATTENDANCE_GROUP_TARGET (nomor/group id) atau WA_ATTENDANCE_GROUP_NAME (nama grup).';
		return '';
	}

	private function attendance_reminder_direct_dm_enabled()
	{
		$raw = strtolower(trim((string) getenv('WA_ATTENDANCE_DIRECT_DM_ENABLED')));
		if ($raw === '')
		{
			return FALSE;
		}

		return in_array($raw, array('1', 'true', 'yes', 'on'), TRUE);
	}

	private function attendance_reminder_state_file_path()
	{
		return APPPATH.'cache/attendance_reminder_state.json';
	}

	private function load_attendance_reminder_state()
	{
		$file_path = $this->attendance_reminder_state_file_path();
		$state = array(
			'sent_slots' => array(),
			'direct_sent' => array(),
			'updated_at' => ''
		);

		if (function_exists('absen_data_store_load_value'))
		{
			$loaded_state = absen_data_store_load_value('attendance_reminder_state', NULL, $file_path);
			if (is_array($loaded_state))
			{
				$state = array_merge($state, $loaded_state);
			}
		}
		elseif (is_file($file_path))
		{
			$raw = file_get_contents($file_path);
			if (is_string($raw) && trim($raw) !== '')
			{
				$decoded = json_decode($raw, TRUE);
				if (is_array($decoded))
				{
					$state = array_merge($state, $decoded);
				}
			}
		}

		$state['sent_slots'] = $this->normalize_attendance_reminder_key_list(isset($state['sent_slots']) ? $state['sent_slots'] : array());
		$state['direct_sent'] = $this->normalize_attendance_reminder_key_list(isset($state['direct_sent']) ? $state['direct_sent'] : array());
		return $this->prune_attendance_reminder_state($state);
	}

	private function save_attendance_reminder_state($state)
	{
		$file_path = $this->attendance_reminder_state_file_path();
		$state = is_array($state) ? $state : array();
		$state['sent_slots'] = $this->normalize_attendance_reminder_key_list(isset($state['sent_slots']) ? $state['sent_slots'] : array());
		$state['direct_sent'] = $this->normalize_attendance_reminder_key_list(isset($state['direct_sent']) ? $state['direct_sent'] : array());
		$state['updated_at'] = date('Y-m-d H:i:s');
		$state = $this->prune_attendance_reminder_state($state);

		if (function_exists('absen_data_store_save_value'))
		{
			$saved = absen_data_store_save_value('attendance_reminder_state', $state, $file_path);
			if ($saved)
			{
				return TRUE;
			}
		}

		$directory = dirname($file_path);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0777, TRUE);
		}

		return (bool) file_put_contents($file_path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
	}

	private function normalize_attendance_reminder_key_list($keys)
	{
		if (!is_array($keys))
		{
			return array();
		}

		$normalized = array();
		for ($i = 0; $i < count($keys); $i += 1)
		{
			$key_value = trim((string) $keys[$i]);
			if ($key_value === '')
			{
				continue;
			}
			$normalized[] = $key_value;
		}

		return array_values(array_unique($normalized));
	}

	private function prune_attendance_reminder_state($state)
	{
		$state = is_array($state) ? $state : array();
		$today_timestamp = strtotime(date('Y-m-d').' 00:00:00');
		$keep_days = 14;

		$prune_keys = function ($keys) use ($today_timestamp, $keep_days) {
			$result = array();
			$list = is_array($keys) ? $keys : array();
			for ($i = 0; $i < count($list); $i += 1)
			{
				$key_value = trim((string) $list[$i]);
				if ($key_value === '')
				{
					continue;
				}

				$key_date = substr($key_value, 0, 10);
				if (!$this->is_valid_date_format($key_date))
				{
					continue;
				}
				$key_timestamp = strtotime($key_date.' 00:00:00');
				if ($key_timestamp === FALSE)
				{
					continue;
				}
				$age_days = (int) floor(($today_timestamp - $key_timestamp) / 86400);
				if ($age_days < 0 || $age_days > $keep_days)
				{
					continue;
				}

				$result[] = $key_value;
			}

			return array_values(array_unique($result));
		};

		$state['sent_slots'] = $prune_keys(isset($state['sent_slots']) ? $state['sent_slots'] : array());
		$state['direct_sent'] = $prune_keys(isset($state['direct_sent']) ? $state['direct_sent'] : array());
		return $state;
	}

	private function build_attendance_reminder_payload($date_key = '')
	{
		$date_value = trim((string) $date_key);
		if (!$this->is_valid_date_format($date_value))
		{
			$date_value = date('Y-m-d');
		}

		$profiles = $this->employee_profile_book();
		$employee_lookup = array();
		foreach ($profiles as $username_key => $profile)
		{
			$normalized_username = strtolower(trim((string) $username_key));
			if ($normalized_username === '')
			{
				continue;
			}
			$employee_lookup[$normalized_username] = TRUE;
		}

		$present_by_username = array();
		$records = $this->load_attendance_records();
		for ($i = 0; $i < count($records); $i += 1)
		{
			$row = isset($records[$i]) && is_array($records[$i]) ? $records[$i] : array();
			$row_username = isset($row['username']) ? strtolower(trim((string) $row['username'])) : '';
			if ($row_username === '' || !isset($employee_lookup[$row_username]))
			{
				continue;
			}

			$row_date = isset($row['date']) ? trim((string) $row['date']) : '';
			if ($row_date !== $date_value)
			{
				continue;
			}

			$row_check_in = isset($row['check_in_time']) ? trim((string) $row['check_in_time']) : '';
			if ($this->has_real_attendance_time($row_check_in))
			{
				$present_by_username[$row_username] = TRUE;
			}
		}

		$leave_result = $this->build_admin_leave_map($employee_lookup);
		$leave_by_date = isset($leave_result['by_date']) && is_array($leave_result['by_date'])
			? $leave_result['by_date']
			: array();
		$day_leave_map = isset($leave_by_date[$date_value]) && is_array($leave_by_date[$date_value])
			? $leave_by_date[$date_value]
			: array();
		$employee_id_book = $this->employee_id_book();
		$rows = array();
		foreach ($profiles as $username_key => $profile)
		{
			$username_value = strtolower(trim((string) $username_key));
			if ($username_value === '')
			{
				continue;
			}

			$display_name = isset($profile['display_name']) && trim((string) $profile['display_name']) !== ''
				? trim((string) $profile['display_name'])
				: $username_value;
			$phone_value = isset($profile['phone']) ? trim((string) $profile['phone']) : '';
			if ($phone_value === '')
			{
				$phone_value = $this->get_employee_phone($username_value);
			}
			$employee_id = $this->resolve_employee_id_from_book($username_value, $employee_id_book);
			$sort_sequence = 9999;
			if ($employee_id !== '-' && preg_match('/^\d+$/', $employee_id) === 1)
			{
				$sort_sequence = (int) $employee_id;
			}

			$leave_type = isset($day_leave_map[$username_value]) ? strtolower(trim((string) $day_leave_map[$username_value])) : '';
			$is_leave_today = $leave_type === 'izin' || $leave_type === 'cuti';
			$is_present = isset($present_by_username[$username_value]);
			$rows[] = array(
				'username' => $username_value,
				'display_name' => $display_name,
				'phone' => $phone_value,
				'employee_id' => $employee_id,
				'sort_sequence' => $sort_sequence,
				'is_present' => $is_present ? TRUE : FALSE,
				'is_leave_today' => $is_leave_today ? TRUE : FALSE,
				'leave_type' => $leave_type
			);
		}

		usort($rows, function ($left, $right) {
			$left_sequence = isset($left['sort_sequence']) ? (int) $left['sort_sequence'] : 9999;
			$right_sequence = isset($right['sort_sequence']) ? (int) $right['sort_sequence'] : 9999;
			if ($left_sequence !== $right_sequence)
			{
				return $left_sequence - $right_sequence;
			}

			$left_name = isset($left['display_name']) ? strtolower(trim((string) $left['display_name'])) : '';
			$right_name = isset($right['display_name']) ? strtolower(trim((string) $right['display_name'])) : '';
			return strcmp($left_name, $right_name);
		});

		$present_count = 0;
		$missing_count = 0;
		$alpha_names = array();
		for ($i = 0; $i < count($rows); $i += 1)
		{
			$is_present = isset($rows[$i]['is_present']) && $rows[$i]['is_present'] === TRUE;
			$is_leave_today = isset($rows[$i]['is_leave_today']) && $rows[$i]['is_leave_today'] === TRUE;
			if ($is_present)
			{
				$present_count += 1;
			}
			else
			{
				$missing_count += 1;
				if (!$is_leave_today)
				{
					$alpha_names[] = isset($rows[$i]['display_name']) ? (string) $rows[$i]['display_name'] : '-';
				}
			}
		}

		return array(
			'date_key' => $date_value,
			'date_label' => date('d-m-Y', strtotime($date_value)),
			'rows' => $rows,
			'total_employees' => count($rows),
			'present_count' => $present_count,
			'missing_count' => $missing_count,
			'alpha_names' => $alpha_names,
			'alpha_count' => count($alpha_names)
		);
	}

	private function build_attendance_reminder_group_message($payload, $slot)
	{
		$slot_label = trim((string) $slot);
		$rows = isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : array();
		$lines = array();
		$lines[] = 'List Absensi Hari ini :';
		$lines[] = '✅ : Kalau sudah absen';
		$lines[] = '❌ : Kalau belum absen';
		$lines[] = '';

		for ($i = 0; $i < count($rows); $i += 1)
		{
			$row_name = isset($rows[$i]['display_name']) ? (string) $rows[$i]['display_name'] : '-';
			$row_status = isset($rows[$i]['is_present']) && $rows[$i]['is_present'] === TRUE ? '✅' : '❌';
			$lines[] = '- '.$row_name.' '.$row_status;
		}

		$lines[] = '';
		$lines[] = 'Jam cek: '.$slot_label.' WIB';

		if ($slot_label === '17:00')
		{
			$alpha_names = isset($payload['alpha_names']) && is_array($payload['alpha_names']) ? $payload['alpha_names'] : array();
			$alpha_count = isset($payload['alpha_count']) ? (int) $payload['alpha_count'] : count($alpha_names);
			$lines[] = '';
			$lines[] = 'Total Alpha Hari ini: '.$alpha_count.' orang';
			if ($alpha_count > 0)
			{
				for ($i = 0; $i < count($alpha_names); $i += 1)
				{
					$lines[] = '- '.(string) $alpha_names[$i];
				}
			}
			else
			{
				$lines[] = '- Tidak ada';
			}
		}

		return implode("\n", $lines);
	}

	private function build_attendance_reminder_direct_message($display_name, $slot)
	{
		$name = trim((string) $display_name);
		if ($name === '')
		{
			$name = 'Karyawan';
		}

		$slot_label = trim((string) $slot);
		if ($slot_label === '17:00')
		{
			return "Halo ".$name.",\nSampai last call jam 17:00 WIB kamu belum absen masuk hari ini.\nMohon segera konfirmasi ke admin jika ada kendala.\nTerima kasih.";
		}

		return "Halo ".$name.",\nSampai jam ".$slot_label." WIB kamu belum melakukan absen masuk hari ini.\nMohon segera absen dari dashboard.\nTerima kasih.";
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

	private function has_real_attendance_time($time_value)
	{
		$value = trim((string) $time_value);
		if ($value === '' || $value === '-' || $value === '--')
		{
			return FALSE;
		}
		if (strcasecmp($value, 'null') === 0 || strcasecmp($value, 'n/a') === 0)
		{
			return FALSE;
		}
		if (preg_match('/^(\d{1,2})\:(\d{2})(?:\:(\d{2}))?$/', $value, $matches) === 1)
		{
			$hour = (int) $matches[1];
			$minute = isset($matches[2]) ? (int) $matches[2] : 0;
			$second = isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : 0;
			if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59)
			{
				return FALSE;
			}

			return sprintf('%02d:%02d:%02d', $hour, $minute, $second) !== '00:00:00';
		}

		$timestamp = strtotime($value);
		if ($timestamp === FALSE)
		{
			return FALSE;
		}

		return date('H:i:s', $timestamp) !== '00:00:00';
	}

	private function duration_to_seconds($duration_value)
	{
		return $this->time_to_seconds($duration_value);
	}

	private function should_bypass_attendance_time_window($username)
	{
		$username_key = strtolower(trim((string) $username));
		if ($username_key === '')
		{
			return FALSE;
		}

		$bypass_usernames = self::ATTENDANCE_TIME_BYPASS_USERS;
		if (!is_array($bypass_usernames) || empty($bypass_usernames))
		{
			return FALSE;
		}

		for ($i = 0; $i < count($bypass_usernames); $i += 1)
		{
			$candidate = strtolower(trim((string) $bypass_usernames[$i]));
			if ($candidate === '')
			{
				continue;
			}
			if ($username_key === $candidate)
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	private function should_force_late_attendance($username)
	{
		$username_key = strtolower(trim((string) $username));
		if ($username_key === '')
		{
			return FALSE;
		}

		$force_late_usernames = self::ATTENDANCE_FORCE_LATE_USERS;
		if (!is_array($force_late_usernames) || empty($force_late_usernames))
		{
			return FALSE;
		}

		for ($i = 0; $i < count($force_late_usernames); $i += 1)
		{
			$candidate = strtolower(trim((string) $force_late_usernames[$i]));
			if ($candidate === '')
			{
				continue;
			}
			if ($username_key === $candidate)
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	private function calculate_late_deduction($salary_tier, $salary_monthly, $work_days, $late_seconds, $date_key = '', $username = '', $weekly_day_off_override = NULL)
	{
		static $monthly_cut_cache = array();
		$late_seconds = (int) $late_seconds;
		$salary_monthly = $this->resolve_monthly_salary($salary_tier, (float) $salary_monthly);
		$weekly_day_off_n = $this->default_weekly_day_off();
		if ($weekly_day_off_override !== NULL)
		{
			$weekly_day_off_n = $this->resolve_employee_weekly_day_off($weekly_day_off_override);
		}
		else
		{
			$username_key = strtolower(trim((string) $username));
			if ($username_key !== '')
			{
				$user_profile = $this->get_employee_profile($username_key);
				$weekly_day_off_n = isset($user_profile['weekly_day_off'])
					? $this->resolve_employee_weekly_day_off($user_profile['weekly_day_off'])
					: $weekly_day_off_n;
			}
		}
		$month_policy = $this->calculate_month_work_policy($date_key, $weekly_day_off_n);
		$year = isset($month_policy['year']) ? (int) $month_policy['year'] : (int) date('Y');
		$month = isset($month_policy['month']) ? (int) $month_policy['month'] : (int) date('n');
		$weekly_leave_taken = isset($month_policy['weekly_off_days']) ? (int) $month_policy['weekly_off_days'] : 0;
		$cache_key = implode('|', array(
			number_format((float) $salary_monthly, 2, '.', ''),
			(string) $year,
			(string) $month,
			(string) $weekly_day_off_n,
			(string) $weekly_leave_taken
		));
		if (isset($monthly_cut_cache[$cache_key]) && is_array($monthly_cut_cache[$cache_key]))
		{
			$monthly_summary = $monthly_cut_cache[$cache_key];
		}
		else
		{
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
			$monthly_cut_cache[$cache_key] = $monthly_summary;
		}
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

	private function monthly_demo_randomization_enabled()
	{
		$raw = strtolower(trim((string) getenv('ABSEN_MONTHLY_DEMO_RANDOMIZE')));
		if ($raw === '')
		{
			return FALSE;
		}

		return in_array($raw, array('1', 'true', 'yes', 'on'), TRUE);
	}

	private function monthly_infer_alpha_from_gap_enabled()
	{
		$raw = strtolower(trim((string) getenv('ABSEN_MONTHLY_INFER_ALPHA_FROM_GAP')));
		if ($raw === '')
		{
			return FALSE;
		}

		return in_array($raw, array('1', 'true', 'yes', 'on'), TRUE);
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
		static $weekday_occurrence_cache = array();
		$year = (int) $year;
		$month = (int) $month;
		$weekday_n = $this->normalize_weekly_day_off($weekday_n);
		$cache_key = sprintf('%04d-%02d-%d', $year, $month, $weekday_n);
		if (isset($weekday_occurrence_cache[$cache_key]))
		{
			return (int) $weekday_occurrence_cache[$cache_key];
		}

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

		$weekday_occurrence_cache[$cache_key] = $total;
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
		$weekly_quota = $this->count_weekday_occurrences($year, $month, $weekly_day_off_n);
		if ($weekly_quota <= 0)
		{
			$weekly_quota = $this->weekly_quota_by_days($days_in_month);
		}
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
		static $month_policy_cache = array();
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
		$weekly_day_off_n = $this->normalize_weekly_day_off($weekly_day_off === NULL ? $this->default_weekly_day_off() : $weekly_day_off);
		$cache_key = sprintf('%04d-%02d-%d', $year, $month, $weekly_day_off_n);
		if (isset($month_policy_cache[$cache_key]) && is_array($month_policy_cache[$cache_key]))
		{
			return $month_policy_cache[$cache_key];
		}

		$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		$weekly_off_days = $this->count_weekday_occurrences($year, $month, $weekly_day_off_n);
		if ($weekly_off_days <= 0)
		{
			$weekly_off_days = $this->weekly_quota_by_days($days_in_month);
		}
		$work_days = max($days_in_month - $weekly_off_days, 1);

		$policy = array(
			'year' => $year,
			'month' => $month,
			'days_in_month' => $days_in_month,
			'weekly_day_off' => $weekly_day_off_n,
			'weekly_off_days' => $weekly_off_days,
			'work_days' => $work_days
		);
		$month_policy_cache[$cache_key] = $policy;
		return $policy;
	}

	private function conflict_logs_file_path()
	{
		return APPPATH.'cache/conflict_logs.json';
	}

	private function read_conflict_logs_from_disk()
	{
		$file_path = $this->conflict_logs_file_path();
		if (function_exists('absen_data_store_load_value'))
		{
			$rows = absen_data_store_load_value('conflict_logs', NULL, $file_path);
			if (is_array($rows))
			{
				return array_values($rows);
			}
		}

		if (!is_file($file_path))
		{
			return array();
		}

		$content = @file_get_contents($file_path);
		if ($content === FALSE || trim((string) $content) === '')
		{
			return array();
		}
		if (substr($content, 0, 3) === "\xEF\xBB\xBF")
		{
			$content = substr($content, 3);
		}

		$decoded = json_decode($content, TRUE);
		if (!is_array($decoded))
		{
			return array();
		}

		return array_values($decoded);
	}

	private function save_conflict_logs($rows)
	{
		$rows = is_array($rows) ? array_values($rows) : array();
		$normalized_rows = array();
		for ($i = 0; $i < count($rows); $i += 1)
		{
			$row = isset($rows[$i]) && is_array($rows[$i]) ? $rows[$i] : array();
			$normalized_rows[] = $this->normalize_data_log_entry($row);
		}

		$limit = $this->data_logs_limit();
		if ($limit > 0 && count($normalized_rows) > $limit)
		{
			$normalized_rows = array_slice($normalized_rows, count($normalized_rows) - $limit);
		}

		$file_path = $this->conflict_logs_file_path();
		if (function_exists('absen_data_store_save_value'))
		{
			$saved_to_store = absen_data_store_save_value('conflict_logs', array_values($normalized_rows), $file_path);
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

		$payload = json_encode(array_values($normalized_rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($payload === FALSE)
		{
			return FALSE;
		}

		return @file_put_contents($file_path, $payload) !== FALSE;
	}

	private function load_conflict_logs($limit = 500)
	{
		$raw_rows = $this->read_conflict_logs_from_disk();
		$rows = array();
		for ($i = 0; $i < count($raw_rows); $i += 1)
		{
			$row = isset($raw_rows[$i]) && is_array($raw_rows[$i]) ? $raw_rows[$i] : array();
			$rows[] = $this->normalize_data_log_entry($row);
		}

		usort($rows, function ($a, $b) {
			$left = isset($a['logged_at']) ? trim((string) $a['logged_at']) : '';
			$right = isset($b['logged_at']) ? trim((string) $b['logged_at']) : '';
			if ($left === $right)
			{
				$left_id = isset($a['entry_id']) ? trim((string) $a['entry_id']) : '';
				$right_id = isset($b['entry_id']) ? trim((string) $b['entry_id']) : '';
				return strcmp($right_id, $left_id);
			}
			return strcmp($right, $left);
		});

		$limit = (int) $limit;
		if ($limit > 0 && count($rows) > $limit)
		{
			$rows = array_slice($rows, 0, $limit);
		}

		return $rows;
	}

	private function find_log_entry_index_by_id($rows, $entry_id)
	{
		$entry_key = trim((string) $entry_id);
		if ($entry_key === '' || !is_array($rows))
		{
			return -1;
		}

		for ($i = 0; $i < count($rows); $i += 1)
		{
			$row = isset($rows[$i]) && is_array($rows[$i]) ? $rows[$i] : array();
			$row_id = isset($row['entry_id']) ? trim((string) $row['entry_id']) : '';
			if ($row_id !== '' && hash_equals($row_id, $entry_key))
			{
				return $i;
			}
		}

		return -1;
	}

	private function generate_data_log_entry_id()
	{
		return 'LG-'.date('YmdHis').'-'.strtoupper(substr(md5(uniqid('', TRUE)), 0, 8));
	}

	private function build_sync_actor_context($actor = 'system', $force_cli = FALSE)
	{
		$actor = strtolower(trim((string) $actor));
		if ($actor === '')
		{
			$actor = 'system';
		}

		$is_cli = $force_cli === TRUE || ($this->input->is_cli_request() === TRUE);
		$ip_address = '';
		$computer_name = '';
		if ($is_cli)
		{
			$ip_address = '127.0.0.1';
			$computer_name = $this->normalize_device_label(function_exists('gethostname') ? gethostname() : php_uname('n'));
		}
		else
		{
			$ip_address = method_exists($this->input, 'ip_address')
				? trim((string) $this->input->ip_address())
				: '';
			if ($ip_address === '' && isset($_SERVER['REMOTE_ADDR']))
			{
				$ip_address = trim((string) $_SERVER['REMOTE_ADDR']);
			}
			if ($ip_address === '0.0.0.0')
			{
				$ip_address = '';
			}

			$computer_name = $this->resolve_client_computer_name($ip_address);
		}

		$mac_address = $this->resolve_client_mac_address($ip_address, $is_cli);

		return array(
			'actor' => $actor,
			'is_cli' => $is_cli ? 1 : 0,
			'ip_address' => $ip_address,
			'computer_name' => $computer_name,
			'mac_address' => $mac_address
		);
	}

	private function resolve_client_computer_name($ip_address = '')
	{
		$candidates = array(
			isset($_SERVER['HTTP_X_COMPUTER_NAME']) ? (string) $_SERVER['HTTP_X_COMPUTER_NAME'] : '',
			isset($_SERVER['HTTP_X_DEVICE_NAME']) ? (string) $_SERVER['HTTP_X_DEVICE_NAME'] : '',
			isset($_SERVER['HTTP_X_CLIENT_HOSTNAME']) ? (string) $_SERVER['HTTP_X_CLIENT_HOSTNAME'] : '',
			isset($_SERVER['REMOTE_HOST']) ? (string) $_SERVER['REMOTE_HOST'] : ''
		);
		for ($i = 0; $i < count($candidates); $i += 1)
		{
			$label = $this->normalize_device_label($candidates[$i]);
			if ($label !== '')
			{
				return $label;
			}
		}

		$ip_address = trim((string) $ip_address);
		if ($ip_address !== '' && filter_var($ip_address, FILTER_VALIDATE_IP))
		{
			$reverse = @gethostbyaddr($ip_address);
			if (is_string($reverse))
			{
				$reverse = trim($reverse);
				if ($reverse !== '' && strcasecmp($reverse, $ip_address) !== 0)
				{
					return $this->normalize_device_label($reverse);
				}
			}
		}

		return '';
	}

	private function resolve_client_mac_address($ip_address = '', $is_cli = FALSE)
	{
		if (!function_exists('shell_exec'))
		{
			return '';
		}

		$ip_address = trim((string) $ip_address);
		$is_cli = $is_cli === TRUE;
		if ($is_cli)
		{
			$commands = array();
			if (stripos(PHP_OS, 'WIN') === 0)
			{
				$commands[] = 'getmac';
				$commands[] = 'arp -a';
			}
			else
			{
				$commands[] = 'ip link 2>/dev/null';
				$commands[] = 'ifconfig -a 2>/dev/null';
				$commands[] = 'arp -an 2>/dev/null';
			}

			for ($i = 0; $i < count($commands); $i += 1)
			{
				$output = @shell_exec($commands[$i]);
				$mac = $this->extract_mac_from_text($output);
				if ($mac !== '')
				{
					return $mac;
				}
			}

			return '';
		}

		if ($ip_address === '' || !filter_var($ip_address, FILTER_VALIDATE_IP))
		{
			return '';
		}
		if (!$this->is_private_or_loopback_ip($ip_address))
		{
			// MAC client internet biasanya tidak bisa didapat dari server.
			return '';
		}

		$commands = array();
		if (stripos(PHP_OS, 'WIN') === 0)
		{
			$commands[] = 'arp -a '.$ip_address;
			$commands[] = 'arp -a';
		}
		else
		{
			$commands[] = 'arp -n '.$ip_address.' 2>/dev/null';
			$commands[] = 'arp -an '.$ip_address.' 2>/dev/null';
			$commands[] = 'arp -an 2>/dev/null';
		}

		for ($i = 0; $i < count($commands); $i += 1)
		{
			$output = @shell_exec($commands[$i]);
			if (!is_string($output) || trim($output) === '')
			{
				continue;
			}

			$lines = preg_split('/\r\n|\r|\n/', $output);
			for ($line_index = 0; $line_index < count($lines); $line_index += 1)
			{
				$line = isset($lines[$line_index]) ? (string) $lines[$line_index] : '';
				if ($line === '')
				{
					continue;
				}
				if (strpos($line, $ip_address) === FALSE && strpos($commands[$i], ' '.$ip_address) !== FALSE)
				{
					continue;
				}
				$mac = $this->extract_mac_from_text($line);
				if ($mac !== '')
				{
					return $mac;
				}
			}

			$fallback_mac = $this->extract_mac_from_text($output);
			if ($fallback_mac !== '')
			{
				return $fallback_mac;
			}
		}

		return '';
	}

	private function extract_mac_from_text($text)
	{
		$text = trim((string) $text);
		if ($text === '')
		{
			return '';
		}

		$matches = array();
		if (preg_match('/([0-9A-Fa-f]{2}[-:]){5}[0-9A-Fa-f]{2}/', $text, $matches))
		{
			$mac = strtoupper(str_replace('-', ':', (string) $matches[0]));
			if ($mac === '00:00:00:00:00:00' || $mac === 'FF:FF:FF:FF:FF:FF')
			{
				return '';
			}
			return $mac;
		}

		return '';
	}

	private function normalize_device_label($value)
	{
		$label = trim((string) $value);
		if ($label === '')
		{
			return '';
		}
		$label = preg_replace('/\s+/', ' ', $label);
		if ($label === NULL)
		{
			$label = trim((string) $value);
		}
		if (strlen($label) > 120)
		{
			$label = substr($label, 0, 120);
		}

		return $label;
	}

	private function is_private_or_loopback_ip($ip_address)
	{
		$ip_address = trim((string) $ip_address);
		if ($ip_address === '')
		{
			return FALSE;
		}
		if ($ip_address === '127.0.0.1' || $ip_address === '::1')
		{
			return TRUE;
		}

		if (filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		{
			$is_public = filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== FALSE;
			return !$is_public;
		}

		if (filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
		{
			$ip_lower = strtolower($ip_address);
			if (strpos($ip_lower, 'fe80:') === 0)
			{
				return TRUE;
			}
			if (strpos($ip_lower, 'fc') === 0 || strpos($ip_lower, 'fd') === 0)
			{
				return TRUE;
			}
			return FALSE;
		}

		return FALSE;
	}

	private function data_logs_limit()
	{
		return 5000;
	}

	private function append_data_log($entry)
	{
		if (!is_array($entry) || empty($entry))
		{
			return FALSE;
		}

		$rows = $this->read_conflict_logs_from_disk();
		$rows[] = $this->normalize_data_log_entry($entry);
		return $this->save_conflict_logs($rows);
	}

	private function normalize_data_log_entry($entry)
	{
		$entry = is_array($entry) ? $entry : array();
		$entry_id = '';
		if (isset($entry['entry_id']) && trim((string) $entry['entry_id']) !== '')
		{
			$entry_id = trim((string) $entry['entry_id']);
		}
		elseif (isset($entry['id']) && trim((string) $entry['id']) !== '')
		{
			$entry_id = trim((string) $entry['id']);
		}
		if ($entry_id === '')
		{
			$entry_id = $this->generate_data_log_entry_id();
		}

		$rollback_status = strtolower(trim((string) (isset($entry['rollback_status']) ? $entry['rollback_status'] : '')));
		if ($rollback_status !== 'rolled_back')
		{
			$rollback_status = '';
		}
		return array(
			'entry_id' => $entry_id,
			'log_type' => isset($entry['log_type']) && trim((string) $entry['log_type']) !== ''
				? trim((string) $entry['log_type'])
				: 'activity',
			'logged_at' => isset($entry['logged_at']) && trim((string) $entry['logged_at']) !== ''
				? trim((string) $entry['logged_at'])
				: date('Y-m-d H:i:s'),
			'source' => isset($entry['source']) ? trim((string) $entry['source']) : '',
			'actor' => isset($entry['actor']) ? trim((string) $entry['actor']) : 'system',
			'ip_address' => isset($entry['ip_address']) ? trim((string) $entry['ip_address']) : '',
			'mac_address' => isset($entry['mac_address']) ? trim((string) $entry['mac_address']) : '',
			'computer_name' => isset($entry['computer_name']) ? trim((string) $entry['computer_name']) : '',
			'username' => isset($entry['username']) ? trim((string) $entry['username']) : '',
			'display_name' => isset($entry['display_name']) ? trim((string) $entry['display_name']) : '',
			'field' => isset($entry['field']) ? trim((string) $entry['field']) : '',
			'field_label' => isset($entry['field_label']) ? trim((string) $entry['field_label']) : '',
			'old_value' => isset($entry['old_value']) ? trim((string) $entry['old_value']) : '',
			'new_value' => isset($entry['new_value']) ? trim((string) $entry['new_value']) : '',
			'action' => isset($entry['action']) ? trim((string) $entry['action']) : '',
			'sheet' => isset($entry['sheet']) ? trim((string) $entry['sheet']) : '',
			'row_number' => isset($entry['row_number']) ? (int) $entry['row_number'] : 0,
			'note' => isset($entry['note']) ? trim((string) $entry['note']) : '',
			'target_id' => isset($entry['target_id']) ? trim((string) $entry['target_id']) : '',
			'rollback_status' => $rollback_status,
			'rolled_back_at' => isset($entry['rolled_back_at']) ? trim((string) $entry['rolled_back_at']) : '',
			'rolled_back_by' => isset($entry['rolled_back_by']) ? trim((string) $entry['rolled_back_by']) : '',
			'rollback_note' => isset($entry['rollback_note']) ? trim((string) $entry['rollback_note']) : ''
		);
	}

	private function rollback_allowed_account_field_map()
	{
		return array(
			'display_name' => 'Nama',
			'branch' => 'Cabang',
			'phone' => 'No Tlp',
			'shift_name' => 'Shift',
			'salary_monthly' => 'Gaji Pokok',
			'work_days' => 'Hari Masuk',
			'weekly_day_off' => 'Hari Libur Mingguan',
			'job_title' => 'Jabatan',
			'address' => 'Alamat'
		);
	}

	private function can_rollback_log_entry($entry)
	{
		if (!is_array($entry))
		{
			return FALSE;
		}

		$rollback_status = strtolower(trim((string) (isset($entry['rollback_status']) ? $entry['rollback_status'] : '')));
		if ($rollback_status === 'rolled_back')
		{
			return FALSE;
		}

		$source = strtolower(trim((string) (isset($entry['source']) ? $entry['source'] : '')));
		$action = strtolower(trim((string) (isset($entry['action']) ? $entry['action'] : '')));
		$field = trim((string) (isset($entry['field']) ? $entry['field'] : ''));

		if ($source !== 'account_data')
		{
			return FALSE;
		}

		if ($action === 'update_account_field')
		{
			$allowed_fields = $this->rollback_allowed_account_field_map();
			return isset($allowed_fields[$field]);
		}

		if ($action === 'update_account_username' && $field === 'username')
		{
			return TRUE;
		}

		return FALSE;
	}

	private function rollback_log_entry_data($entry, &$message)
	{
		$message = '';
		$entry = is_array($entry) ? $entry : array();
		$source = strtolower(trim((string) (isset($entry['source']) ? $entry['source'] : '')));
		$action = strtolower(trim((string) (isset($entry['action']) ? $entry['action'] : '')));

		if ($source !== 'account_data')
		{
			$message = 'Rollback hanya diizinkan untuk perubahan data akun.';
			return FALSE;
		}

		if ($action === 'update_account_field')
		{
			return $this->rollback_account_field_entry($entry, $message);
		}

		if ($action === 'update_account_username')
		{
			return $this->rollback_account_username_entry($entry, $message);
		}

		$message = 'Jenis aksi log ini belum mendukung rollback aman.';
		return FALSE;
	}

	private function rollback_account_field_entry($entry, &$message)
	{
		$message = '';
		$field = trim((string) (isset($entry['field']) ? $entry['field'] : ''));
		$allowed_fields = $this->rollback_allowed_account_field_map();
		if ($field === '' || !isset($allowed_fields[$field]))
		{
			$message = 'Field rollback tidak valid.';
			return FALSE;
		}

		$username_key = strtolower(trim((string) (isset($entry['username']) ? $entry['username'] : '')));
		if ($username_key === '' || $this->is_reserved_system_username($username_key))
		{
			$message = 'Target akun rollback tidak valid.';
			return FALSE;
		}

		$old_value = isset($entry['old_value']) ? (string) $entry['old_value'] : '';
		$new_value = isset($entry['new_value']) ? (string) $entry['new_value'] : '';

		$account_book = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (!isset($account_book[$username_key]) || !is_array($account_book[$username_key]))
		{
			$message = 'Akun target rollback tidak ditemukan.';
			return FALSE;
		}

		$current_row = $account_book[$username_key];
		$current_role = strtolower(trim((string) (isset($current_row['role']) ? $current_row['role'] : 'user')));
		if ($current_role !== 'user')
		{
			$message = 'Rollback dibatalkan karena target bukan akun karyawan.';
			return FALSE;
		}

		$current_field_value = isset($current_row[$field]) ? (string) $current_row[$field] : '';
		if (!$this->rollback_field_values_equal($field, $current_field_value, $new_value))
		{
			$message = 'Rollback dibatalkan karena nilai saat ini sudah berubah lagi setelah log tersebut.';
			return FALSE;
		}

		$updated_row = $current_row;
		if ($field === 'display_name')
		{
			$display_name = trim((string) $old_value);
			if ($display_name === '')
			{
				$message = 'Rollback nama dibatalkan karena nilai lama kosong.';
				return FALSE;
			}
			$updated_row['display_name'] = $display_name;
		}
		elseif ($field === 'branch')
		{
			$branch_value = $this->resolve_employee_branch($old_value);
			if ($branch_value === '')
			{
				$message = 'Rollback cabang dibatalkan karena nilai lama cabang tidak valid.';
				return FALSE;
			}
			$updated_row['branch'] = $branch_value;
		}
		elseif ($field === 'phone')
		{
			$updated_row['phone'] = trim((string) $old_value);
		}
		elseif ($field === 'shift_name')
		{
			$shift_name_old = trim((string) $old_value);
			if ($shift_name_old === '')
			{
				$message = 'Rollback shift dibatalkan karena nilai lama kosong.';
				return FALSE;
			}
			$shift_profiles = function_exists('absen_shift_profile_book') ? absen_shift_profile_book() : array();
			$shift_key = $this->resolve_shift_key_from_shift_values($shift_name_old, '');
			if (!isset($shift_profiles[$shift_key]))
			{
				$message = 'Rollback shift dibatalkan karena konfigurasi shift tidak tersedia.';
				return FALSE;
			}
			$updated_row['shift_name'] = (string) $shift_profiles[$shift_key]['shift_name'];
			$updated_row['shift_time'] = (string) $shift_profiles[$shift_key]['shift_time'];
		}
		elseif ($field === 'salary_monthly')
		{
			$salary_digits = preg_replace('/\D+/', '', (string) $old_value);
			$salary_amount = $salary_digits === '' ? 0 : (int) $salary_digits;
			if ($salary_amount <= 0)
			{
				$message = 'Rollback gaji pokok dibatalkan karena nilai lama tidak valid.';
				return FALSE;
			}
			$updated_row['salary_monthly'] = $salary_amount;
			$updated_row['salary_tier'] = $this->resolve_salary_tier_from_amount($salary_amount);
		}
		elseif ($field === 'work_days')
		{
			$work_days_old = (int) trim((string) $old_value);
			if ($work_days_old <= 0 || $work_days_old > 31)
			{
				$message = 'Rollback hari masuk dibatalkan karena nilai lama tidak valid.';
				return FALSE;
			}
			$updated_row['work_days'] = $work_days_old;
		}
		elseif ($field === 'weekly_day_off')
		{
			$weekly_day_off_old = trim((string) $old_value);
			if ($weekly_day_off_old === '')
			{
				$message = 'Rollback hari libur mingguan dibatalkan karena nilai lama kosong.';
				return FALSE;
			}
			$updated_row['weekly_day_off'] = $this->resolve_employee_weekly_day_off($weekly_day_off_old);
		}
		elseif ($field === 'job_title')
		{
			$job_title_old = $this->resolve_employee_job_title($old_value);
			if ($job_title_old === '')
			{
				$message = 'Rollback jabatan dibatalkan karena nilai lama tidak valid.';
				return FALSE;
			}
			$updated_row['job_title'] = $job_title_old;
		}
		elseif ($field === 'address')
		{
			$address_old = trim((string) $old_value);
			$updated_row['address'] = $address_old !== '' ? $address_old : $this->default_employee_address();
		}
		else
		{
			$message = 'Field rollback belum didukung.';
			return FALSE;
		}

		$updated_row['sheet_sync_source'] = 'web';
		$updated_row['sheet_last_sync_at'] = date('Y-m-d H:i:s');
		$account_book[$username_key] = $updated_row;
		$saved = function_exists('absen_save_account_book')
			? absen_save_account_book($account_book)
			: FALSE;
		if (!$saved)
		{
			$message = 'Gagal menyimpan rollback ke data akun.';
			return FALSE;
		}

		$message = 'Field '.$allowed_fields[$field].' untuk akun '.$username_key.' berhasil dikembalikan.';
		return TRUE;
	}

	private function rollback_account_username_entry($entry, &$message)
	{
		$message = '';
		$old_username = $this->normalize_username_key(isset($entry['old_value']) ? (string) $entry['old_value'] : '');
		$new_username = $this->normalize_username_key(isset($entry['new_value']) ? (string) $entry['new_value'] : '');
		$target_username = $this->normalize_username_key(isset($entry['username']) ? (string) $entry['username'] : '');
		if ($target_username === '')
		{
			$target_username = $new_username;
		}

		if ($old_username === '' || $new_username === '' || $old_username === $new_username)
		{
			$message = 'Data username pada log rollback tidak valid.';
			return FALSE;
		}
		if ($this->is_reserved_system_username($old_username) || $this->is_reserved_system_username($new_username))
		{
			$message = 'Rollback username sistem tidak diizinkan.';
			return FALSE;
		}

		$account_book = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		$current_username = $target_username;
		if (!isset($account_book[$current_username]) || !is_array($account_book[$current_username]))
		{
			if (isset($account_book[$new_username]) && is_array($account_book[$new_username]))
			{
				$current_username = $new_username;
			}
		}
		if (!isset($account_book[$current_username]) || !is_array($account_book[$current_username]))
		{
			$message = 'Akun target rollback username tidak ditemukan.';
			return FALSE;
		}
		if ($current_username !== $new_username)
		{
			$message = 'Rollback username dibatalkan karena username saat ini sudah berubah lagi.';
			return FALSE;
		}
		if (isset($account_book[$old_username]) && is_array($account_book[$old_username]))
		{
			$message = 'Rollback username dibatalkan karena username lama sudah dipakai akun lain.';
			return FALSE;
		}

		$current_row = $account_book[$current_username];
		$current_role = strtolower(trim((string) (isset($current_row['role']) ? $current_row['role'] : 'user')));
		if ($current_role !== 'user')
		{
			$message = 'Rollback username hanya untuk akun karyawan.';
			return FALSE;
		}

		unset($account_book[$current_username]);
		$current_row['sheet_sync_source'] = 'web';
		$current_row['sheet_last_sync_at'] = date('Y-m-d H:i:s');
		$account_book[$old_username] = $current_row;
		$saved = function_exists('absen_save_account_book')
			? absen_save_account_book($account_book)
			: FALSE;
		if (!$saved)
		{
			$message = 'Gagal menyimpan rollback username.';
			return FALSE;
		}

		$renamed_related = $this->rename_employee_related_records($current_username, $old_username);
		$renamed_total = (int) $renamed_related['attendance'] + (int) $renamed_related['leave'] + (int) $renamed_related['loan'] + (int) $renamed_related['overtime'];
		$message = 'Username akun berhasil dikembalikan dari '.$current_username.' ke '.$old_username.'. Data terkait diperbarui: '.$renamed_total.' baris.';
		return TRUE;
	}

	private function rollback_field_values_equal($field, $value_left, $value_right)
	{
		$field_key = trim((string) $field);
		$left = trim((string) $value_left);
		$right = trim((string) $value_right);

		if ($field_key === 'salary_monthly')
		{
			$left_digits = preg_replace('/\D+/', '', $left);
			$right_digits = preg_replace('/\D+/', '', $right);
			return (int) ($left_digits === '' ? 0 : $left_digits) === (int) ($right_digits === '' ? 0 : $right_digits);
		}

		if ($field_key === 'work_days')
		{
			return (int) $left === (int) $right;
		}
		if ($field_key === 'weekly_day_off')
		{
			return $this->resolve_employee_weekly_day_off($left) === $this->resolve_employee_weekly_day_off($right);
		}

		if ($field_key === 'branch')
		{
			$left_branch = $this->resolve_employee_branch($left);
			$right_branch = $this->resolve_employee_branch($right);
			return strcasecmp($left_branch, $right_branch) === 0;
		}

		if ($field_key === 'job_title')
		{
			$left_job = $this->resolve_employee_job_title($left);
			$right_job = $this->resolve_employee_job_title($right);
			return strcasecmp($left_job, $right_job) === 0;
		}

		if ($field_key === 'shift_name')
		{
			return strcasecmp($left, $right) === 0;
		}

		return $left === $right;
	}

	private function log_activity_event($action, $source, $target_username = '', $target_display_name = '', $note = '', $extra = array())
	{
		$extra = is_array($extra) ? $extra : array();
		$actor = strtolower(trim((string) $this->session->userdata('absen_username')));
		if ($actor === '')
		{
			$actor = 'system';
		}
		$context = $this->build_sync_actor_context($actor);
		$entry = array(
			'log_type' => 'activity',
			'logged_at' => date('Y-m-d H:i:s'),
			'source' => trim((string) $source),
			'actor' => $actor,
			'ip_address' => isset($context['ip_address']) ? trim((string) $context['ip_address']) : '',
			'mac_address' => isset($context['mac_address']) ? trim((string) $context['mac_address']) : '',
			'computer_name' => isset($context['computer_name']) ? trim((string) $context['computer_name']) : '',
			'username' => trim((string) $target_username),
			'display_name' => trim((string) $target_display_name),
			'field' => isset($extra['field']) ? trim((string) $extra['field']) : '',
			'field_label' => isset($extra['field_label']) ? trim((string) $extra['field_label']) : '',
			'old_value' => isset($extra['old_value']) ? trim((string) $extra['old_value']) : '',
			'new_value' => isset($extra['new_value']) ? trim((string) $extra['new_value']) : '',
			'action' => trim((string) $action),
			'sheet' => isset($extra['sheet']) ? trim((string) $extra['sheet']) : '',
			'row_number' => isset($extra['row_number']) ? (int) $extra['row_number'] : 0,
			'note' => trim((string) $note),
			'target_id' => isset($extra['target_id']) ? trim((string) $extra['target_id']) : ''
		);

		$this->append_data_log($entry);
	}

	private function evaluate_geofence($distance_m, $accuracy_m, $office_label = '')
	{
		$radius_m = (float) self::OFFICE_RADIUS_M;
		$distance_m = (float) $distance_m;
		$accuracy_m = (float) $accuracy_m;
		$office_label = trim((string) $office_label);
		if ($office_label === '')
		{
			$office_label = 'titik kantor';
		}

		if ($distance_m <= $radius_m && $accuracy_m <= (float) self::MAX_GPS_ACCURACY_M)
		{
			return array(
				'inside' => TRUE,
				'message' => 'Lokasi valid di dalam radius '.$office_label.'.'
			);
		}

		if (($distance_m + $accuracy_m) <= $radius_m)
		{
			return array(
				'inside' => TRUE,
				'message' => 'Lokasi valid di dalam radius '.$office_label.' (toleransi akurasi GPS).'
			);
		}

		if (($distance_m - $accuracy_m) > $radius_m)
		{
			return array(
				'inside' => FALSE,
				'message' => 'Lokasi di luar radius '.$office_label.'. Jarak kamu '.round($distance_m, 2).'m dari '.$office_label.' (maks '.self::OFFICE_RADIUS_M.'m).'
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
