<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends CI_Controller {
	const OFFICE_LAT = -6.217076;
	const OFFICE_LNG = 106.132128;
	const OFFICE_ALT_LAT = -6.270039;
	const OFFICE_ALT_LNG = 106.120796;
	const OFFICE_RADIUS_M = 100;
	const MAX_GPS_ACCURACY_M = 50;
	const MAX_GPS_ACCURACY_ALT_M = 125;
	const CHECK_IN_MIN_TIME = '07:30:00';
	const CHECK_IN_MAX_TIME = '17:00:00';
	const CHECK_OUT_MAX_TIME = '23:59:00';
	const MULTISHIFT_CHECK_IN_MAX_TIME = '23:00:00';
	const MULTISHIFT_ONTIME_MORNING_END = '08:00:00';
	const MULTISHIFT_ONTIME_AFTERNOON_START = '13:30:00';
	const MULTISHIFT_ONTIME_AFTERNOON_END = '14:00:00';
	const ATTENDANCE_TIME_BYPASS_USERS = array();
	const ATTENDANCE_FORCE_LATE_USERS = array();
	const ATTENDANCE_FORCE_LATE_DURATION = '00:30:00';
	const ATTENDANCE_REMINDER_SLOTS = array('08:15', '14:15');
	const ATTENDANCE_REMINDER_SLOT_GRACE_MINUTES = 59;
	const ATTENDANCE_REMINDER_MEMBER_USERNAME_OVERRIDES = array(
		'supriatna' => 'yatna',
		'andini' => 'andini',
		'andini_salsabilah' => 'andini',
		'muhammad_arudji_prayoga' => 'yoga',
		'm_arudji_prayoga' => 'yoga'
	);
	const ATTENDANCE_REMINDER_GROUP_JOB_TITLE_MAP = array(
		'team_diskusi' => array('Teknisi', 'Koordinator'),
		'team_marketing_pld' => array('Marketing'),
		'admin_cs_noc_pld' => array('Admin', 'NOC', 'Magang'),
		'voucher_pencabutan_pld' => array('Debt Collector')
	);
	const ATTENDANCE_REMINDER_GROUPS = array(
		array(
			'key' => 'team_diskusi',
			'name' => 'TEAM DISKUSI',
			'target' => '6283890121138-1626927852@g.us',
			'members' => array(
				'Andi',
				'Ardi Rizki',
				'Asep Sulaeman',
				'Dedi Supriyadi',
				'Deni Yusup',
				'M. Efaria Setiawan',
				'Khoirul Imam Ferdiansyah',
				'Jaelani',
				'Ahmad Maulana',
				'Rian Fadilah',
				'Supriatna',
				'Yusuf Bachriel',
				'Yudi',
				'Acep Muzakil Amini',
				'Madromin'
			)
		),
		array(
			'key' => 'team_marketing_pld',
			'name' => 'TEAM MARKETING PLD',
			'target' => 'false_120363401649644997@g.us_6656878031771907051_96139235860502@lid',
			'members' => array(
				'Tb Hilmi',
				'Siti Anisa Musyana',
				'Inayah Hidayanti'
			)
		),
		array(
			'key' => 'admin_cs_noc_pld',
			'name' => 'ADMIN, CS & NOC PLD',
			'target' => 'false_120363381776766004@g.us_A529BE34DBDEA349BB975F466B9B5D7A_96139235860502@lid',
			'members' => array(
				'Kaisar Alqodar',
				'Wildan Nurfauzan',
				'Ahmad Rizalus Solihin',
				'Murodi',
				'Muhammad Arudji Prayoga',
				'Andini',
				'Desti Chaerunnisa',
				'Syifa Aida Firmanti',
				'Muhammad Ridwan K.',
				'Vointra Namara Fidelito',
				'Hendra Hidayatulloh'
			)
		),
		array(
			'key' => 'voucher_pencabutan_pld',
			'name' => 'VOUCHER & PENCABUTAN PLD',
			'target' => 'false_120363404559964751@g.us_ACFCE37EA79CA867EC9DC989F216BB7A_262839247872126@lid',
			'members' => array(
				'Sahlan',
				'Azri Pahriyansyah',
				'M. Arifuddin',
				'Rahmatullah'
			)
		)
	);
	const SUBMISSION_NOTIFY_ADMIN_PHONE = '62895329871876';
	const LATE_TOLERANCE_SECONDS = 600;
	const WORK_DAYS_DEFAULT = 22;
	const MIN_EFFECTIVE_WORK_DAYS = 20;
	const DEDUCTION_ROUND_BASE = 1000;
	const HALF_DAY_LATE_THRESHOLD_SECONDS = 14400;
	const WEEKLY_HOLIDAY_DAY = 1;
	const CUSTOM_WEEKDAY_OFF_BY_USERNAME = array(
		'hendra' => array(6, 7)
	);
	const CUSTOM_ALLOWED_ATTENDANCE_DAYS_BY_USERNAME = array(
		'kaisar' => array(1, 5)
	);
	const ADMIN_DASHBOARD_SUMMARY_CACHE_TTL_SECONDS = 45;
	const PROFILE_PHOTO_MAX_WIDTH = 512;
	const PROFILE_PHOTO_MAX_HEIGHT = 512;
	const PROFILE_PHOTO_WEBP_QUALITY = 82;
	const PROFILE_PHOTO_THUMB_SIZE = 160;
	const PROFILE_PHOTO_THUMB_WEBP_QUALITY = 76;
	const ATTENDANCE_PHOTO_MAX_WIDTH = 1280;
	const ATTENDANCE_PHOTO_MAX_HEIGHT = 1280;
	const ATTENDANCE_PHOTO_WEBP_QUALITY = 76;
	const ALPHA_RESET_STATE_STORE_KEY = 'alpha_reset_state';
	const COLLAB_STATE_STORE_KEY = 'admin_collab_state';
	const COLLAB_STATE_EVENT_LIMIT = 600;
	const COLLAB_STATE_DEFAULT_POLL_MS = 10000;
	const COLLAB_SYNC_LOCK_TTL_SECONDS = 240;
	const COLLAB_SYNC_LOCK_WAIT_REFRESH_SECONDS = 5;
	const SYNC_LOCAL_BACKUP_STATE_SESSION_KEY = 'sync_local_backup_state';
	const SYNC_LOCAL_BACKUP_READY_TTL_SECONDS = 21600;

	private $attendance_records_cache_loaded = FALSE;
	private $attendance_records_cache = array();

	public function __construct()
	{
		parent::__construct();
		$this->load->library('session');
		$this->load->library('absen_sheet_sync');
		$this->load->helper('absen_account_store');
		$this->load->helper('absen_data_store');
		$attendance_mirror_helper = APPPATH.'helpers/attendance_mirror_helper.php';
		if (is_file($attendance_mirror_helper) && is_readable($attendance_mirror_helper))
		{
			$this->load->helper('attendance_mirror');
		}
		date_default_timezone_set('Asia/Jakarta');
		$this->sync_home_theme_preference_state();

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

	private function normalize_home_theme_value($value)
	{
		$theme = strtolower(trim((string) $value));
		if ($theme === 'dark' || $theme === 'light')
		{
			return $theme;
		}
		return '';
	}

	private function persist_home_theme_preference($theme)
	{
		$normalized_theme = $this->normalize_home_theme_value($theme);
		if ($normalized_theme === '')
		{
			return '';
		}

		$this->session->set_userdata('home_index_theme', $normalized_theme);
		$_COOKIE['home_index_theme'] = $normalized_theme;

		$expires_at = time() + 31536000;
		$is_https = isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
		if (PHP_VERSION_ID >= 70300)
		{
			setcookie('home_index_theme', $normalized_theme, array(
				'expires' => $expires_at,
				'path' => '/',
				'secure' => $is_https,
				'httponly' => FALSE,
				'samesite' => 'Lax'
			));
		}
		else
		{
			setcookie('home_index_theme', $normalized_theme, $expires_at, '/', '', $is_https, FALSE);
		}

		return $normalized_theme;
	}

	private function sync_home_theme_preference_state()
	{
		$session_theme = $this->normalize_home_theme_value($this->session->userdata('home_index_theme'));
		$cookie_theme = $this->normalize_home_theme_value($this->input->cookie('home_index_theme', TRUE));

		if ($cookie_theme !== '')
		{
			if ($cookie_theme !== $session_theme)
			{
				$this->session->set_userdata('home_index_theme', $cookie_theme);
			}
			$_COOKIE['home_index_theme'] = $cookie_theme;
			return $cookie_theme;
		}

		if ($session_theme !== '')
		{
			return $this->persist_home_theme_preference($session_theme);
		}

		return '';
	}

	public function set_theme_preference()
	{
		$requested_theme = $this->input->post('theme', TRUE);
		if ($requested_theme === NULL || trim((string) $requested_theme) === '')
		{
			$requested_theme = $this->input->get('theme', TRUE);
		}
		if ($requested_theme === NULL || trim((string) $requested_theme) === '')
		{
			$raw_payload = file_get_contents('php://input');
			if (is_string($raw_payload) && trim($raw_payload) !== '')
			{
				$decoded_payload = json_decode($raw_payload, TRUE);
				if (is_array($decoded_payload) && array_key_exists('theme', $decoded_payload))
				{
					$requested_theme = $decoded_payload['theme'];
				}
			}
		}

		$saved_theme = $this->persist_home_theme_preference($requested_theme);
		if ($saved_theme === '')
		{
			$this->output
				->set_status_header(400)
				->set_content_type('application/json', 'utf-8')
				->set_output(json_encode(array(
					'success' => FALSE,
					'message' => 'Tema tidak valid.',
					'theme' => ''
				)));
			return;
		}

		$this->output
			->set_content_type('application/json', 'utf-8')
			->set_output(json_encode(array(
				'success' => TRUE,
				'theme' => $saved_theme
			)));
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
			$cross_branch_enabled = $this->resolve_cross_branch_for_user($username, $user_profile);
			$shift_key = $this->resolve_shift_key_from_shift_values($shift_name, $shift_time);
			$office_points = $this->attendance_office_points_for_shift(
				$shift_key,
				$attendance_branch,
				$cross_branch_enabled
			);
			$primary_office = isset($office_points[0]) && is_array($office_points[0])
				? $office_points[0]
				: array(
					'label' => 'Kantor',
					'lat' => (float) self::OFFICE_LAT,
					'lng' => (float) self::OFFICE_LNG
				);
			$is_first_loan = $this->is_first_loan_request($username);
			$dashboard_snapshot = $this->build_user_dashboard_snapshot($username, $shift_name, $shift_time);
			$all_office_points = array(
				array(
					'label' => 'Kantor 1',
					'lat' => (float) self::OFFICE_LAT,
					'lng' => (float) self::OFFICE_LNG
				),
				array(
					'label' => 'Kantor 2',
					'lat' => (float) self::OFFICE_ALT_LAT,
					'lng' => (float) self::OFFICE_ALT_LNG
				)
			);
			$data = array(
				'title' => 'Dashboard Absen - User',
				'username' => $display_name !== '' ? $display_name : ($username !== '' ? $username : 'user'),
				'profile_photo' => isset($user_profile['profile_photo']) && trim((string) $user_profile['profile_photo']) !== ''
					? (string) $user_profile['profile_photo']
					: $this->default_employee_profile_photo(),
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
					'office_lat' => isset($primary_office['lat']) ? (float) $primary_office['lat'] : (float) self::OFFICE_LAT,
					'office_lng' => isset($primary_office['lng']) ? (float) $primary_office['lng'] : (float) self::OFFICE_LNG,
					'radius_m' => self::OFFICE_RADIUS_M,
					'max_accuracy_m' => self::MAX_GPS_ACCURACY_M,
					'office_points' => $office_points,
					'office_fallback_points' => $all_office_points,
					'attendance_branch' => $attendance_branch,
					'cross_branch_enabled' => $cross_branch_enabled,
					'shift_key' => $shift_key
				),
				'loan_config' => array(
					'min_principal' => 500000,
					'max_principal' => 10000000,
					'min_tenor_months' => 1,
					'max_tenor_months' => 12,
					'is_first_loan' => $is_first_loan
				),
				'day_off_swap_requests' => $this->build_user_day_off_swap_request_rows($username, 12),
				'swap_request_notice_success' => (string) $this->session->flashdata('swap_request_notice_success'),
				'swap_request_notice_error' => (string) $this->session->flashdata('swap_request_notice_error'),
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
		$sync_backup_status = $this->sync_local_backup_status_for_actor($this->current_actor_username());
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
			'day_off_swaps' => $this->build_day_off_swap_management_rows(),
			'day_off_swap_requests' => $this->build_day_off_swap_request_management_rows('pending'),
			'job_title_options' => $this->employee_job_title_options(),
			'branch_options' => $this->employee_branch_options(),
			'default_branch' => $this->default_employee_branch(),
			'weekly_day_off_options' => $this->weekly_day_off_options(),
			'default_weekly_day_off' => $this->default_weekly_day_off(),
			'can_view_log_data' => $can_view_log_data,
			'can_manage_accounts' => $can_manage_accounts,
			'can_process_day_off_swap_requests' => $this->can_process_day_off_swap_requests_feature(),
			'can_sync_sheet_accounts' => $can_sync_sheet_accounts,
			'can_manage_feature_accounts' => $can_super_admin_manage,
			'admin_feature_catalog' => $this->admin_feature_permission_catalog(),
			'admin_feature_accounts' => $this->build_manageable_admin_feature_account_options(),
			'privileged_password_targets' => $this->build_privileged_password_target_options(),
			'account_notice_success' => (string) $this->session->flashdata('account_notice_success'),
			'account_notice_error' => (string) $this->session->flashdata('account_notice_error'),
			'collab_revision' => $this->collab_current_revision(),
			'collab_feed_url' => site_url('home/admin_change_feed'),
			'collab_sync_lock_url' => site_url('home/sync_lock_status'),
			'collab_poll_ms' => self::COLLAB_STATE_DEFAULT_POLL_MS,
			'collab_actor' => $this->current_actor_username(),
			'collab_lock_wait_refresh_seconds' => self::COLLAB_SYNC_LOCK_WAIT_REFRESH_SECONDS,
			'sync_backup_ready' => isset($sync_backup_status['ready']) && $sync_backup_status['ready'] === TRUE,
			'sync_backup_status_text' => isset($sync_backup_status['status_text']) ? (string) $sync_backup_status['status_text'] : ''
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
		if (!$this->assert_expected_revision_or_redirect('home', 'account_notice_error'))
		{
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

		$month_policy = $this->calculate_employee_month_work_policy($username_key, date('Y-m-d'), $weekly_day_off);
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
			'custom_allowed_weekdays' => array(),
			'custom_off_ranges' => array(),
			'custom_work_ranges' => array(),
			'job_title' => $job_title,
			'address' => $address,
			'profile_photo' => $profile_photo_path,
			'coordinate_point' => $coordinate_point,
			'employee_status' => 'Aktif',
			'force_password_change' => 1,
			'record_version' => 1,
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
					$account_book[$username_key]['sheet_sync_source'] = 'web';
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
		if (!$this->assert_expected_revision_or_redirect('home', 'account_notice_error'))
		{
			return;
		}

		$actor = strtolower(trim((string) $this->session->userdata('absen_username')));
		if ($actor === '')
		{
			$actor = 'admin';
		}
		$actor_context = $this->build_sync_actor_context($actor);
		$lock_result = $this->collab_try_acquire_sync_lock($actor, 'sync_sheet_accounts');
		if (!isset($lock_result['success']) || $lock_result['success'] !== TRUE)
		{
			$lock_wait = isset($lock_result['remaining_seconds']) ? (int) $lock_result['remaining_seconds'] : 0;
			$lock_owner = isset($lock_result['owner']) ? trim((string) $lock_result['owner']) : 'admin lain';
			$this->session->set_flashdata(
				'account_notice_error',
				'Sync lock sedang aktif oleh '.$lock_owner.'. Tunggu '.max(1, $lock_wait).' detik lalu coba lagi.'
			);
			redirect('home');
			return;
		}
		$lock_token = isset($lock_result['token']) ? trim((string) $lock_result['token']) : '';
		try
		{
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
		}
		finally
		{
			$this->collab_release_sync_lock($lock_token, $actor);
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
		if (!$this->assert_expected_revision_or_redirect('home', 'account_notice_error'))
		{
			return;
		}

		$actor = strtolower(trim((string) $this->session->userdata('absen_username')));
		if ($actor === '')
		{
			$actor = 'admin';
		}
		if (!$this->assert_sync_local_backup_ready_or_redirect('home#manajemen-karyawan', 'account_notice_error', $actor))
		{
			return;
		}
		$actor_context = $this->build_sync_actor_context($actor);
		$branch_scope = $this->is_branch_scoped_admin() ? $this->current_actor_branch() : '';
		$lock_result = $this->collab_try_acquire_sync_lock($actor, 'sync_sheet_attendance');
		if (!isset($lock_result['success']) || $lock_result['success'] !== TRUE)
		{
			$lock_wait = isset($lock_result['remaining_seconds']) ? (int) $lock_result['remaining_seconds'] : 0;
			$lock_owner = isset($lock_result['owner']) ? trim((string) $lock_result['owner']) : 'admin lain';
			$this->session->set_flashdata(
				'account_notice_error',
				'Sync lock sedang aktif oleh '.$lock_owner.'. Tunggu '.max(1, $lock_wait).' detik lalu coba lagi.'
			);
			redirect('home');
			return;
		}
		$lock_token = isset($lock_result['token']) ? trim((string) $lock_result['token']) : '';
		$this->sync_local_backup_consume($actor);
		try
		{
			$result = $this->absen_sheet_sync->sync_attendance_from_sheet(array(
				'force' => TRUE,
				// Mode aman: merge-only (tanpa overwrite agresif, tanpa prune stale).
				// Sheet boleh update data yang ada, tapi tidak menghapus data lokal yang tidak ditemukan di sheet.
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
		}
		finally
		{
			$this->collab_release_sync_lock($lock_token, $actor);
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
		if (!$this->assert_expected_revision_or_redirect('home', 'account_notice_error'))
		{
			return;
		}

		$actor = strtolower(trim((string) $this->session->userdata('absen_username')));
		if ($actor === '')
		{
			$actor = 'admin';
		}
		if (!$this->assert_sync_local_backup_ready_or_redirect('home#manajemen-karyawan', 'account_notice_error', $actor))
		{
			return;
		}
		$actor_context = $this->build_sync_actor_context($actor);
		$branch_scope = $this->is_branch_scoped_admin() ? $this->current_actor_branch() : '';
		$lock_result = $this->collab_try_acquire_sync_lock($actor, 'sync_web_to_sheet');
		if (!isset($lock_result['success']) || $lock_result['success'] !== TRUE)
		{
			$lock_wait = isset($lock_result['remaining_seconds']) ? (int) $lock_result['remaining_seconds'] : 0;
			$lock_owner = isset($lock_result['owner']) ? trim((string) $lock_result['owner']) : 'admin lain';
			$this->session->set_flashdata(
				'account_notice_error',
				'Sync lock sedang aktif oleh '.$lock_owner.'. Tunggu '.max(1, $lock_wait).' detik lalu coba lagi.'
			);
			redirect('home');
			return;
		}
		$lock_token = isset($lock_result['token']) ? trim((string) $lock_result['token']) : '';
		$this->sync_local_backup_consume($actor);
		try
		{
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
		}
		finally
		{
			$this->collab_release_sync_lock($lock_token, $actor);
		}

		redirect('home');
	}

	public function prepare_sync_local_backup_now()
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
			redirect('home#manajemen-karyawan');
			return;
		}

		$actor = strtolower(trim((string) $this->session->userdata('absen_username')));
		if ($actor === '')
		{
			$actor = 'admin';
		}

		$backup_result = $this->create_sync_local_backup_snapshot($actor);
		if (!isset($backup_result['success']) || $backup_result['success'] !== TRUE)
		{
			$message = isset($backup_result['message']) && trim((string) $backup_result['message']) !== ''
				? (string) $backup_result['message']
				: 'Backup local gagal dibuat. Cek izin tulis folder application/cache.';
			$this->session->set_flashdata('account_notice_error', $message);
			redirect('home#manajemen-karyawan');
			return;
		}

		$this->sync_local_backup_mark_ready($actor, $backup_result);

		$copied_files = isset($backup_result['copied_files']) ? (int) $backup_result['copied_files'] : 0;
		$missing_files = isset($backup_result['missing_files']) ? (int) $backup_result['missing_files'] : 0;
		$backup_dir = isset($backup_result['backup_dir_relative']) ? trim((string) $backup_result['backup_dir_relative']) : '';

		$success_message = 'Backup local selesai ('.$copied_files.' file). Sekarang kamu bisa jalankan 1x aksi sync.';
		if ($missing_files > 0)
		{
			$success_message .= ' File belum ada/tidak terbaca: '.$missing_files.'.';
		}
		if ($backup_dir !== '')
		{
			$success_message .= ' Folder: '.$backup_dir.'.';
		}
		$this->session->set_flashdata('account_notice_success', $success_message);

		$this->log_activity_event(
			'prepare_sync_local_backup',
			'system_data',
			'',
			'',
			'Membuat backup lokal sebelum aksi sync.',
			array(
				'new_value' => 'copied_files='.$copied_files.', missing_files='.$missing_files.', backup_dir='.$backup_dir
			)
		);

		redirect('home#manajemen-karyawan');
	}

	private function sync_local_backup_actor_key($actor = '')
	{
		$actor_key = strtolower(trim((string) $actor));
		if ($actor_key === '')
		{
			$actor_key = strtolower(trim((string) $this->session->userdata('absen_username')));
		}
		if ($actor_key === '')
		{
			$actor_key = 'admin';
		}
		return $actor_key;
	}

	private function sync_local_backup_session_default_state()
	{
		return array(
			'ready' => FALSE,
			'actor' => '',
			'created_at' => '',
			'created_ts' => 0,
			'backup_dir_relative' => '',
			'copied_files' => 0
		);
	}

	private function sync_local_backup_clear_session_state()
	{
		$this->session->unset_userdata(self::SYNC_LOCAL_BACKUP_STATE_SESSION_KEY);
	}

	private function sync_local_backup_status_for_actor($actor = '')
	{
		$actor_key = $this->sync_local_backup_actor_key($actor);
		$default_state = $this->sync_local_backup_session_default_state();
		$state = $this->session->userdata(self::SYNC_LOCAL_BACKUP_STATE_SESSION_KEY);
		if (!is_array($state))
		{
			$state = $default_state;
		}

		$state_actor = strtolower(trim((string) (isset($state['actor']) ? $state['actor'] : '')));
		$state_ready = isset($state['ready']) && $state['ready'] ? TRUE : FALSE;
		$created_at = trim((string) (isset($state['created_at']) ? $state['created_at'] : ''));
		$created_ts = isset($state['created_ts']) ? (int) $state['created_ts'] : 0;
		if ($created_ts <= 0 && $created_at !== '')
		{
			$parsed_ts = strtotime($created_at);
			$created_ts = $parsed_ts !== FALSE ? (int) $parsed_ts : 0;
		}

		$ttl_seconds = (int) self::SYNC_LOCAL_BACKUP_READY_TTL_SECONDS;
		if ($ttl_seconds < 300)
		{
			$ttl_seconds = 300;
		}
		$is_expired = $created_ts > 0 ? ((time() - $created_ts) > $ttl_seconds) : TRUE;

		if (!$state_ready || $state_actor === '' || $state_actor !== $actor_key || $is_expired)
		{
			if ($state_ready)
			{
				$this->sync_local_backup_clear_session_state();
			}
			return array(
				'ready' => FALSE,
				'status_text' => 'Belum ada backup lokal aktif. Klik "Backup Local Dulu (Wajib)" sebelum sync.'
			);
		}

		$copied_files = isset($state['copied_files']) ? max(0, (int) $state['copied_files']) : 0;
		$backup_dir_relative = trim((string) (isset($state['backup_dir_relative']) ? $state['backup_dir_relative'] : ''));
		$status_text = 'Backup aktif dibuat '.$created_at.' ('.$copied_files.' file).';
		if ($backup_dir_relative !== '')
		{
			$status_text .= ' Lokasi: '.$backup_dir_relative.'.';
		}
		$status_text .= ' Setelah 1x sync, wajib backup lagi.';

		return array(
			'ready' => TRUE,
			'status_text' => $status_text
		);
	}

	private function sync_local_backup_mark_ready($actor = '', $backup_result = array())
	{
		$actor_key = $this->sync_local_backup_actor_key($actor);
		$state = $this->sync_local_backup_session_default_state();
		$state['ready'] = TRUE;
		$state['actor'] = $actor_key;
		$state['created_at'] = date('Y-m-d H:i:s');
		$state['created_ts'] = time();
		$state['backup_dir_relative'] = isset($backup_result['backup_dir_relative']) ? trim((string) $backup_result['backup_dir_relative']) : '';
		$state['copied_files'] = isset($backup_result['copied_files']) ? max(0, (int) $backup_result['copied_files']) : 0;
		$this->session->set_userdata(self::SYNC_LOCAL_BACKUP_STATE_SESSION_KEY, $state);
	}

	private function sync_local_backup_consume($actor = '')
	{
		$actor_key = $this->sync_local_backup_actor_key($actor);
		$status = $this->sync_local_backup_status_for_actor($actor_key);
		if (isset($status['ready']) && $status['ready'] === TRUE)
		{
			$this->sync_local_backup_clear_session_state();
		}
	}

	private function sync_local_backup_required_message()
	{
		return 'Sebelum sync, wajib lakukan backup lokal dulu. Klik tombol "Backup Local Dulu (Wajib)" di bagian Sinkronisasi Spreadsheet.';
	}

	private function assert_sync_local_backup_ready_or_redirect($redirect_target = 'home', $flash_key = 'account_notice_error', $actor = '')
	{
		$status = $this->sync_local_backup_status_for_actor($actor);
		if (isset($status['ready']) && $status['ready'] === TRUE)
		{
			return TRUE;
		}

		$this->session->set_flashdata($flash_key, $this->sync_local_backup_required_message());
		redirect($redirect_target);
		return FALSE;
	}

	private function sync_local_backup_root_directory()
	{
		return APPPATH.'cache/local_sync_backups';
	}

	private function sync_local_backup_source_files()
	{
		return array(
			'accounts' => function_exists('absen_accounts_file_path') ? absen_accounts_file_path() : APPPATH.'cache/accounts.json',
			'attendance_records' => $this->attendance_file_path(),
			'leave_requests' => $this->leave_requests_file_path(),
			'loan_requests' => $this->loan_requests_file_path(),
			'overtime_records' => $this->overtime_file_path(),
			'day_off_swaps' => $this->day_off_swap_file_path(),
			'day_off_swap_requests' => $this->day_off_swap_request_file_path(),
			'conflict_logs' => $this->conflict_logs_file_path()
		);
	}

	private function sanitize_backup_fragment($value = '')
	{
		$value = strtolower(trim((string) $value));
		$value = preg_replace('/[^a-z0-9_-]+/', '_', $value);
		$value = trim((string) $value, '_');
		return $value !== '' ? $value : 'data';
	}

	private function relative_path_from_project_root($absolute_path = '')
	{
		$absolute = trim((string) $absolute_path);
		if ($absolute === '')
		{
			return '';
		}
		$project_root = realpath(FCPATH);
		$absolute_real = realpath($absolute);
		$resolved_path = $absolute_real !== FALSE ? $absolute_real : $absolute;
		$resolved_path = str_replace('\\', '/', (string) $resolved_path);
		if ($project_root !== FALSE)
		{
			$project_root_normalized = str_replace('\\', '/', (string) $project_root);
			if (strpos($resolved_path, $project_root_normalized) === 0)
			{
				return ltrim(substr($resolved_path, strlen($project_root_normalized)), '/');
			}
		}
		return $resolved_path;
	}

	private function create_sync_local_backup_snapshot($actor = '')
	{
		$actor_key = $this->sync_local_backup_actor_key($actor);
		$backup_root = $this->sync_local_backup_root_directory();
		if (!is_dir($backup_root) && !@mkdir($backup_root, 0755, TRUE))
		{
			return array(
				'success' => FALSE,
				'message' => 'Folder backup lokal tidak bisa dibuat: '.$this->relative_path_from_project_root($backup_root)
			);
		}
		if (!is_writable($backup_root))
		{
			return array(
				'success' => FALSE,
				'message' => 'Folder backup lokal tidak bisa ditulis: '.$this->relative_path_from_project_root($backup_root)
			);
		}

		$folder_name = 'pre_sync_'.date('Ymd_His').'_'.$this->sanitize_backup_fragment($actor_key).'_'.substr(md5(uniqid($actor_key, TRUE)), 0, 6);
		$backup_dir = rtrim($backup_root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$folder_name;
		if (!@mkdir($backup_dir, 0755, TRUE))
		{
			return array(
				'success' => FALSE,
				'message' => 'Folder snapshot backup gagal dibuat: '.$this->relative_path_from_project_root($backup_dir)
			);
		}

		$sources = $this->sync_local_backup_source_files();
		$copied_files = 0;
		$missing_files = 0;
		$failed_files = array();
		$copied_relative_paths = array();
		$source_keys = array_keys($sources);
		for ($i = 0; $i < count($source_keys); $i += 1)
		{
			$source_key = (string) $source_keys[$i];
			$source_path = trim((string) (isset($sources[$source_key]) ? $sources[$source_key] : ''));
			if ($source_path === '' || !is_file($source_path) || !is_readable($source_path))
			{
				$missing_files += 1;
				continue;
			}

			$target_name = sprintf(
				'%02d_%s_%s',
				$i + 1,
				$this->sanitize_backup_fragment($source_key),
				basename($source_path)
			);
			$target_path = $backup_dir.DIRECTORY_SEPARATOR.$target_name;
			if (!@copy($source_path, $target_path))
			{
				$failed_files[] = $source_key;
				continue;
			}

			$copied_files += 1;
			$copied_relative_paths[] = $this->relative_path_from_project_root($target_path);
		}

		if ($copied_files <= 0)
		{
			@rmdir($backup_dir);
			return array(
				'success' => FALSE,
				'message' => 'Backup local gagal. Tidak ada file yang berhasil disalin.'
			);
		}

		$manifest = array(
			'actor' => $actor_key,
			'created_at' => date('Y-m-d H:i:s'),
			'copied_files' => $copied_files,
			'missing_files' => $missing_files,
			'failed_files' => $failed_files,
			'files' => $copied_relative_paths
		);
		$manifest_json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($manifest_json !== FALSE)
		{
			@file_put_contents($backup_dir.DIRECTORY_SEPARATOR.'manifest.json', $manifest_json);
		}

		return array(
			'success' => TRUE,
			'backup_dir' => $backup_dir,
			'backup_dir_relative' => $this->relative_path_from_project_root($backup_dir),
			'copied_files' => $copied_files,
			'missing_files' => $missing_files,
			'failed_files' => $failed_files
		);
	}

	public function reset_total_alpha_now()
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

		if (!$this->can_sync_sheet_accounts_feature())
		{
			$this->session->set_flashdata('account_notice_error', 'Akun login kamu belum punya izin untuk reset total alpha.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if (!$this->assert_expected_revision_or_redirect('home#manajemen-karyawan', 'account_notice_error'))
		{
			return;
		}

		$actor = strtolower(trim((string) $this->session->userdata('absen_username')));
		if ($actor === '')
		{
			$actor = 'admin';
		}

		$lock_result = $this->collab_try_acquire_sync_lock($actor, 'reset_total_alpha');
		if (!isset($lock_result['success']) || $lock_result['success'] !== TRUE)
		{
			$lock_wait = isset($lock_result['remaining_seconds']) ? (int) $lock_result['remaining_seconds'] : 0;
			$lock_owner = isset($lock_result['owner']) ? trim((string) $lock_result['owner']) : 'admin lain';
			$this->session->set_flashdata(
				'account_notice_error',
				'Sync lock sedang aktif oleh '.$lock_owner.'. Tunggu '.max(1, $lock_wait).' detik lalu coba lagi.'
			);
			redirect('home#manajemen-karyawan');
			return;
		}

		$lock_token = isset($lock_result['token']) ? trim((string) $lock_result['token']) : '';
		try
		{
			$metric_maps = $this->build_admin_metric_maps();
			$metric_maps['alpha_reset_by_date'] = array();

			$today_ts = strtotime(date('Y-m-d 00:00:00'));
			$month_start_ts = strtotime(date('Y-m-01 00:00:00'));
			if ($today_ts === FALSE || $month_start_ts === FALSE || $month_start_ts > $today_ts)
			{
				$this->session->set_flashdata('account_notice_error', 'Reset total alpha gagal: tanggal sistem tidak valid.');
				redirect('home#manajemen-karyawan');
				return;
			}

			$state = $this->load_alpha_reset_state();
			$state_by_date = isset($state['by_date']) && is_array($state['by_date'])
				? $state['by_date']
				: array();

			$month_start_key = date('Y-m-d', $month_start_ts);
			$today_key = date('Y-m-d', $today_ts);
			foreach ($state_by_date as $date_key => $usernames)
			{
				$date_text = trim((string) $date_key);
				if (!$this->is_valid_date_format($date_text))
				{
					unset($state_by_date[$date_key]);
					continue;
				}
				if ($date_text >= $month_start_key && $date_text <= $today_key)
				{
					unset($state_by_date[$date_key]);
				}
			}

			$reset_user_lookup = $this->scoped_employee_lookup(TRUE);
			$reset_usernames = array_keys($reset_user_lookup);
			sort($reset_usernames, SORT_STRING);
			if (empty($reset_usernames))
			{
				$this->session->set_flashdata('account_notice_error', 'Reset total alpha gagal: tidak ada karyawan dalam cakupan akun ini.');
				redirect('home#manajemen-karyawan');
				return;
			}

			$reset_dates = 0;
			$reset_entries = 0;
			for ($cursor = $month_start_ts; $cursor <= $today_ts; $cursor = strtotime('+1 day', $cursor))
			{
				if ($cursor === FALSE)
				{
					break;
				}

				$date_key = date('Y-m-d', $cursor);
				$state_by_date[$date_key] = $reset_usernames;
				$reset_dates += 1;
				$reset_entries += count($reset_usernames);
			}

			$records = $this->load_attendance_records();
			$reset_record_rows = 0;
			$month_key = date('Y-m', $month_start_ts);
			$now_text = date('Y-m-d H:i:s');
			if (is_array($records) && !empty($records))
			{
				for ($i = 0; $i < count($records); $i += 1)
				{
					if (!isset($records[$i]) || !is_array($records[$i]))
					{
						continue;
					}

					$row_date = isset($records[$i]['date']) ? trim((string) $records[$i]['date']) : '';
					$row_month = isset($records[$i]['sheet_month']) ? trim((string) $records[$i]['sheet_month']) : '';
					if ($row_month === '' && $this->is_valid_date_format($row_date))
					{
						$row_month = substr($row_date, 0, 7);
					}
					if ($row_month !== $month_key)
					{
						continue;
					}

					$changed = FALSE;
					$current_alpha_total = isset($records[$i]['sheet_total_alpha']) ? (int) $records[$i]['sheet_total_alpha'] : 0;
					if ($current_alpha_total !== 0)
					{
						$records[$i]['sheet_total_alpha'] = 0;
						$changed = TRUE;
					}
					$current_alpha_reason = isset($records[$i]['alasan_alpha']) ? trim((string) $records[$i]['alasan_alpha']) : '';
					if ($current_alpha_reason !== '')
					{
						$records[$i]['alasan_alpha'] = '';
						$changed = TRUE;
					}
					if ($changed)
					{
						$records[$i]['updated_at'] = $now_text;
						$reset_record_rows += 1;
					}
				}
				if ($reset_record_rows > 0)
				{
					$this->save_attendance_records($records);
				}
			}

			$state['by_date'] = $state_by_date;
			if (!$this->save_alpha_reset_state($state))
			{
				$this->session->set_flashdata('account_notice_error', 'Reset total alpha gagal disimpan ke cache.');
				redirect('home#manajemen-karyawan');
				return;
			}

			$this->clear_admin_dashboard_live_summary_cache();
			$this->log_activity_event(
				'reset_total_alpha',
				'web_data',
				'',
				'',
				'Mereset total alpha bulan berjalan.',
				array(
					'new_value' => 'reset_dates='.$reset_dates.', reset_entries='.$reset_entries.', reset_record_rows='.$reset_record_rows
				)
			);

			$this->session->set_flashdata(
				'account_notice_success',
				'Reset total alpha berhasil. Tanggal diproses: '.$reset_dates.', data alpha di-nolkan: '.$reset_entries.', record bulan ini direset: '.$reset_record_rows.'.'
			);
		}
		finally
		{
			$this->collab_release_sync_lock($lock_token, $actor);
		}

		redirect('home#manajemen-karyawan');
	}

	public function favicon()
	{
		$svg_path = FCPATH.'src/assets/sinyal.svg';
		if (is_file($svg_path))
		{
			if (!is_readable($svg_path))
			{
				@chmod($svg_path, 0644);
				clearstatcache(TRUE, $svg_path);
			}
			$content = @file_get_contents($svg_path);
			if ($content !== FALSE && trim($content) !== '')
			{
				$this->output
					->set_content_type('image/svg+xml')
					->set_output($content);
				return;
			}
		}

		$png_candidates = array(
			FCPATH.'src/assets/pns_logo_nav.png',
			FCPATH.'src/assets/pns_new.png'
		);
		for ($i = 0; $i < count($png_candidates); $i += 1)
		{
			$png_path = (string) $png_candidates[$i];
			if (!is_file($png_path))
			{
				continue;
			}
			$content = @file_get_contents($png_path);
			if ($content === FALSE || $content === '')
			{
				continue;
			}
			$this->output
				->set_content_type('image/png')
				->set_output($content);
			return;
		}

		$this->output
			->set_content_type('image/x-icon')
			->set_output('');
	}

	public function login_logo()
	{
		$this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		$this->output->set_header('Pragma: no-cache');

		$svg_path = FCPATH.'src/assets/pns_new.svg';
		if (is_file($svg_path))
		{
			if (!is_readable($svg_path))
			{
				@chmod($svg_path, 0644);
				clearstatcache(TRUE, $svg_path);
			}
			$content = @file_get_contents($svg_path);
			if ($content !== FALSE && trim($content) !== '')
			{
				$this->output
					->set_content_type('image/svg+xml')
					->set_output($content);
				return;
			}
		}

		$png_candidates = array(
			FCPATH.'src/assets/pns_new.png'
		);
		for ($i = 0; $i < count($png_candidates); $i += 1)
		{
			$png_path = (string) $png_candidates[$i];
			if (!is_file($png_path))
			{
				continue;
			}
			$content = @file_get_contents($png_path);
			if ($content === FALSE || $content === '')
			{
				continue;
			}
			$this->output
				->set_content_type('image/png')
				->set_output($content);
			return;
		}

		show_404();
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
			// CLI juga pakai mode aman merge-only.
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

	public function attendance_reminder_auto_cli($slot_override = '', $force = '')
	{
		if (!$this->input->is_cli_request())
		{
			show_404();
			return;
		}

		$force_value = strtolower(trim((string) $force));
		$is_force_resend = in_array($force_value, array('1', 'true', 'yes', 'y', 'force', 'ulang'), TRUE);
		$slot = $this->resolve_attendance_reminder_slot($slot_override);
		if ($slot === '')
		{
			$allowed_slots = self::ATTENDANCE_REMINDER_SLOTS;
			echo "Reminder absensi dilewati. Slot aktif: ".implode(', ', $allowed_slots)." WIB.\n";
			return;
		}

		$date_key = date('Y-m-d');
		$state = $this->load_attendance_reminder_state();
		$slot_key_legacy = $date_key.'|'.$slot;
		$configured_groups = $this->attendance_reminder_group_definitions();
		if (empty($configured_groups))
		{
			echo "Reminder absensi gagal: daftar grup reminder belum dikonfigurasi.\n";
			return;
		}

		$pending_groups = array();
		for ($group_index = 0; $group_index < count($configured_groups); $group_index += 1)
		{
			$group_row = isset($configured_groups[$group_index]) && is_array($configured_groups[$group_index])
				? $configured_groups[$group_index]
				: array();
			$group_key = isset($group_row['key']) ? (string) $group_row['key'] : '';
			$group_state_key = $this->attendance_reminder_group_state_key($date_key, $slot, $group_key);
			$already_sent = in_array($group_state_key, $state['sent_slots'], TRUE)
				|| in_array($slot_key_legacy, $state['sent_slots'], TRUE);
			if ($already_sent && !$is_force_resend)
			{
				continue;
			}
			$pending_groups[] = $group_row;
		}

		if (empty($pending_groups))
		{
			echo "Reminder absensi dilewati. Slot ".$slot." sudah pernah terkirim hari ini.\n";
			return;
		}

		$payload = $this->build_attendance_reminder_payload($date_key);
		$groups_sent = 0;
		$groups_failed = 0;
		$group_fail_messages = array();
		for ($group_index = 0; $group_index < count($pending_groups); $group_index += 1)
		{
			$group_row = isset($pending_groups[$group_index]) && is_array($pending_groups[$group_index])
				? $pending_groups[$group_index]
				: array();
			$group_key = isset($group_row['key']) ? (string) $group_row['key'] : '';
			$group_name = isset($group_row['name']) ? trim((string) $group_row['name']) : '';
			$group_target = isset($group_row['target']) ? trim((string) $group_row['target']) : '';
			$group_members = isset($group_row['members']) && is_array($group_row['members'])
				? $group_row['members']
				: array();
			if ($group_target === '')
			{
				$groups_failed += 1;
				$group_fail_messages[] = ($group_name !== '' ? $group_name : $group_key).': target grup kosong.';
				continue;
			}

			$group_payload = $this->build_attendance_reminder_group_payload($payload, $group_members, $group_name);
			$group_message = $this->build_attendance_reminder_group_message($group_payload, $slot, $group_name);
			$group_result = $this->send_whatsapp_notification($group_target, $group_message);
			if (!isset($group_result['success']) || $group_result['success'] !== TRUE)
			{
				$groups_failed += 1;
				$failed_reason = isset($group_result['message']) ? trim((string) $group_result['message']) : 'Pengiriman ke grup gagal.';
				$group_fail_messages[] = ($group_name !== '' ? $group_name : $group_key).': '.$failed_reason;
				continue;
			}

			$groups_sent += 1;
			$state['sent_slots'][] = $this->attendance_reminder_group_state_key($date_key, $slot, $group_key);
		}

		$state['sent_slots'] = $this->normalize_attendance_reminder_key_list($state['sent_slots']);
		if ($groups_sent <= 0)
		{
			$this->save_attendance_reminder_state($state);
			$fail_text = empty($group_fail_messages) ? 'Semua grup gagal dikirim.' : implode(' | ', $group_fail_messages);
			echo "Reminder absensi gagal: ".$fail_text."\n";
			return;
		}

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
				$dm_key = $slot_key_legacy.'|'.strtolower(trim((string) $row_username));
				if (in_array($dm_key, $state['direct_sent'], TRUE) && !$is_force_resend)
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
			", group_sent=".$groups_sent.
			", group_failed=".$groups_failed.
			", hadir=".(int) (isset($payload['present_count']) ? $payload['present_count'] : 0).
			", belum=".(int) (isset($payload['missing_count']) ? $payload['missing_count'] : 0).
			", alpha=".(int) (isset($payload['alpha_count']) ? $payload['alpha_count'] : 0).
			", force=".($is_force_resend ? '1' : '0').
			", dm_enabled=".($direct_dm_enabled ? '1' : '0').
			", dm_sent=".$dm_sent.
			", dm_failed=".$dm_failed.
			", dm_skipped=".$dm_skipped."\n";
		if ($groups_failed > 0 && !empty($group_fail_messages))
		{
			echo "Detail grup gagal: ".implode(' | ', $group_fail_messages)."\n";
		}
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
			$source_optimize_path = $absolute_path;
			$source_optimize_ext = $file_ext;
			if ($file_ext === 'webp')
			{
				$path_info = pathinfo($absolute_path);
				$base_name = isset($path_info['filename']) ? (string) $path_info['filename'] : '';
				$directory = isset($path_info['dirname']) ? (string) $path_info['dirname'] : '';
				if ($base_name !== '' && $directory !== '')
				{
					$legacy_candidates = array(
						$directory.DIRECTORY_SEPARATOR.$base_name.'.jpg',
						$directory.DIRECTORY_SEPARATOR.$base_name.'.jpeg',
						$directory.DIRECTORY_SEPARATOR.$base_name.'.png'
					);
					for ($legacy_i = 0; $legacy_i < count($legacy_candidates); $legacy_i += 1)
					{
						$legacy_path = (string) $legacy_candidates[$legacy_i];
						if (!is_file($legacy_path))
						{
							continue;
						}

						$source_optimize_path = $legacy_path;
						$source_optimize_ext = strtolower(pathinfo($legacy_path, PATHINFO_EXTENSION));
						break;
					}
				}
			}
			$final_path = $absolute_path;
			$optimized = FALSE;
			$optimize_result = $this->optimize_profile_photo_image(
				$source_optimize_path,
				$source_optimize_ext,
				self::PROFILE_PHOTO_MAX_WIDTH,
				self::PROFILE_PHOTO_MAX_HEIGHT,
				self::PROFILE_PHOTO_WEBP_QUALITY
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
				? $final_dir.DIRECTORY_SEPARATOR.$final_base_name.'_thumb.webp'
				: '';
			$thumb_exists_before = $thumb_path !== '' && is_file($thumb_path);
			$thumb_saved = $this->create_profile_photo_thumbnail(
				$final_path,
				self::PROFILE_PHOTO_THUMB_SIZE,
				self::PROFILE_PHOTO_THUMB_WEBP_QUALITY
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

	public function migrate_default_profile_photo_cli($mode = '')
	{
		if (!$this->input->is_cli_request())
		{
			show_404();
			return;
		}

		$mode_value = strtolower(trim((string) $mode));
		$is_dry_run = $mode_value === 'dry-run' || $mode_value === '--dry-run' || $mode_value === 'preview' || $mode_value === '--preview';
		if (!function_exists('absen_load_account_book') || !function_exists('absen_save_account_book'))
		{
			echo "Helper akun tidak tersedia. Pastikan helper absen_account_store aktif.\n";
			return;
		}

		$default_photo_path = $this->default_employee_profile_photo();
		if (strtolower($default_photo_path) !== '/src/assets/fotoku.webp')
		{
			echo "Foto default belum tersedia sebagai webp. Pastikan GD imagewebp aktif dan file sumber fotoku tersedia.\n";
			echo "default_current=".$default_photo_path."\n";
			return;
		}

		$legacy_default_photos = array(
			'/src/assets/fotoku.JPG',
			'/src/assets/fotoku.jpg',
			'/src/assets/fotoku.jpeg',
			'/src/assets/fotoku.png'
		);
		$legacy_map = array();
		for ($i = 0; $i < count($legacy_default_photos); $i += 1)
		{
			$legacy_map[strtolower((string) $legacy_default_photos[$i])] = TRUE;
		}

		$account_book = absen_load_account_book();
		if (!is_array($account_book) || empty($account_book))
		{
			echo "Data akun kosong. Tidak ada referensi foto default yang dipindah.\n";
			return;
		}

		$total_accounts = 0;
		$updated_accounts = 0;
		foreach ($account_book as $username_key => $row)
		{
			if (!is_array($row))
			{
				continue;
			}
			$total_accounts += 1;
			$current_photo = isset($row['profile_photo']) ? trim((string) $row['profile_photo']) : '';
			if ($current_photo === '')
			{
				continue;
			}

			$current_photo_key = strtolower('/'.ltrim(str_replace('\\', '/', $current_photo), '/'));
			if (!isset($legacy_map[$current_photo_key]))
			{
				continue;
			}

			$account_book[(string) $username_key]['profile_photo'] = $default_photo_path;
			$updated_accounts += 1;
		}

		$save_status = 'skipped';
		if (!$is_dry_run && $updated_accounts > 0)
		{
			$save_status = absen_save_account_book($account_book) ? 'ok' : 'failed';
		}

		echo "Migrasi foto default ".($is_dry_run ? 'preview' : 'selesai').".\n";
		echo "accounts_total=".$total_accounts.", updated=".$updated_accounts.", save=".$save_status."\n";
		echo "default_new=".$default_photo_path."\n";
		if ($is_dry_run)
		{
			echo "Mode preview aktif: data akun tidak diubah.\n";
		}
	}

	public function optimize_attendance_photos_cli($mode = '')
	{
		if (!$this->input->is_cli_request())
		{
			show_404();
			return;
		}

		$mode_value = strtolower(trim((string) $mode));
		$is_dry_run = $mode_value === 'dry-run' || $mode_value === '--dry-run' || $mode_value === 'preview' || $mode_value === '--preview';
		$raw_rows = absen_data_store_load_value('attendance_records', array(), $this->attendance_file_path());
		$rows_before = $this->normalize_attendance_record_versions(array_values(is_array($raw_rows) ? $raw_rows : array()));

		$summarize_rows = function ($rows) {
			$summary = array(
				'records' => is_array($rows) ? count($rows) : 0,
				'photo_fields_total' => 0,
				'photo_fields_empty' => 0,
				'photo_base64' => 0,
				'photo_local_webp' => 0,
				'photo_local_non_webp' => 0,
				'photo_other' => 0
			);
			$list = is_array($rows) ? $rows : array();
			for ($i = 0; $i < count($list); $i += 1)
			{
				$row = isset($list[$i]) && is_array($list[$i]) ? $list[$i] : array();
				$photo_fields = array(
					isset($row['check_in_photo']) ? (string) $row['check_in_photo'] : '',
					isset($row['check_out_photo']) ? (string) $row['check_out_photo'] : ''
				);
				for ($field_i = 0; $field_i < count($photo_fields); $field_i += 1)
				{
					$summary['photo_fields_total'] += 1;
					$photo_value = trim((string) $photo_fields[$field_i]);
					if ($photo_value === '')
					{
						$summary['photo_fields_empty'] += 1;
						continue;
					}
					if (strpos($photo_value, 'data:image/') === 0)
					{
						$summary['photo_base64'] += 1;
						continue;
					}
					if (preg_match('/^https?:\/\//i', $photo_value) === 1)
					{
						$summary['photo_other'] += 1;
						continue;
					}

					$relative_path = '/'.ltrim(str_replace('\\', '/', $photo_value), '/\\');
					if (strpos($relative_path, '/uploads/attendance_photo/') === 0)
					{
						$photo_ext = strtolower(pathinfo($relative_path, PATHINFO_EXTENSION));
						if ($photo_ext === 'webp')
						{
							$summary['photo_local_webp'] += 1;
						}
						else
						{
							$summary['photo_local_non_webp'] += 1;
						}
						continue;
					}

					$summary['photo_other'] += 1;
				}
			}

			return $summary;
		};

		$before_summary = $summarize_rows($rows_before);
		if (!$is_dry_run)
		{
			$this->save_attendance_records($rows_before);
		}
		$rows_after = $is_dry_run ? $rows_before : $this->load_attendance_records();
		$after_summary = $summarize_rows($rows_after);

		$upload_directory_relative = trim($this->attendance_photo_upload_dir(), '/\\');
		$upload_directory_absolute = rtrim((string) FCPATH, '/\\').DIRECTORY_SEPARATOR.
			str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $upload_directory_relative);
		$disk_summary = array(
			'total_files' => 0,
			'webp_files' => 0,
			'non_webp_files' => 0,
			'size_bytes' => 0
		);
		if (is_dir($upload_directory_absolute))
		{
			$files = @scandir($upload_directory_absolute);
			$file_list = is_array($files) ? $files : array();
			for ($i = 0; $i < count($file_list); $i += 1)
			{
				$file_name = (string) $file_list[$i];
				if ($file_name === '.' || $file_name === '..')
				{
					continue;
				}
				$file_path = rtrim($upload_directory_absolute, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file_name;
				if (!is_file($file_path))
				{
					continue;
				}

				$disk_summary['total_files'] += 1;
				$disk_summary['size_bytes'] += max(0, (int) @filesize($file_path));
				$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
				if ($file_ext === 'webp')
				{
					$disk_summary['webp_files'] += 1;
				}
				else
				{
					$disk_summary['non_webp_files'] += 1;
				}
			}
		}

		echo "Optimasi foto absen ".($is_dry_run ? 'preview' : 'selesai').".\n";
		echo "before=".json_encode($before_summary)."\n";
		echo "after=".json_encode($after_summary)."\n";
		echo "disk=".json_encode($disk_summary)."\n";
		if ($is_dry_run)
		{
			echo "Mode preview aktif: attendance_records tidak disimpan ulang.\n";
		}
	}

	public function optimize_media_assets_cli($mode = '')
	{
		if (!$this->input->is_cli_request())
		{
			show_404();
			return;
		}

		echo "== [1/3] Migrasi referensi foto default ==\n";
		$this->migrate_default_profile_photo_cli($mode);
		echo "== [2/3] Optimasi foto profil upload ==\n";
		$this->optimize_profile_photos_cli($mode);
		echo "== [3/3] Optimasi foto absen ==\n";
		$this->optimize_attendance_photos_cli($mode);
		echo "== Selesai optimize_media_assets_cli ==\n";
	}

	public function cleanup_legacy_media_files_cli($mode = '')
	{
		if (!$this->input->is_cli_request())
		{
			show_404();
			return;
		}

		$mode_value = strtolower(trim((string) $mode));
		$is_apply = $mode_value === 'apply' || $mode_value === '--apply';
		$is_preview = !$is_apply;
		echo "Cleanup media legacy ".($is_preview ? 'preview' : 'apply').".\n";

		$normalize_relative = function ($path_value) {
			$path_text = trim((string) $path_value);
			if ($path_text === '')
			{
				return '';
			}
			if (preg_match('/^https?:\/\//i', $path_text) === 1)
			{
				$url_path = parse_url($path_text, PHP_URL_PATH);
				$path_text = is_string($url_path) ? trim((string) $url_path) : '';
				if ($path_text === '')
				{
					return '';
				}
			}

			return '/'.ltrim(str_replace('\\', '/', $path_text), '/\\');
		};

		$build_abs = function ($relative_path) {
			$relative = trim((string) $relative_path);
			if ($relative === '')
			{
				return '';
			}

			return rtrim((string) FCPATH, '/\\').DIRECTORY_SEPARATOR.
				str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, ltrim($relative, '/\\'));
		};

		$profile_keep = array();
		if (function_exists('absen_load_account_book'))
		{
			$account_book = absen_load_account_book();
			if (is_array($account_book))
			{
				foreach ($account_book as $row)
				{
					if (!is_array($row))
					{
						continue;
					}

					$profile_photo_value = isset($row['profile_photo']) ? (string) $row['profile_photo'] : '';
					$relative_photo = $normalize_relative($profile_photo_value);
					if ($relative_photo === '' || strpos($relative_photo, '/uploads/profile_photo/') !== 0)
					{
						continue;
					}

					$profile_keep[$relative_photo] = TRUE;
					$path_info = pathinfo($relative_photo);
					$base_name = isset($path_info['filename']) ? (string) $path_info['filename'] : '';
					$directory = isset($path_info['dirname']) ? (string) $path_info['dirname'] : '';
					if ($base_name !== '' && $directory !== '')
					{
						$profile_keep[$directory.'/'.$base_name.'_thumb.webp'] = TRUE;
						$profile_keep[$directory.'/'.$base_name.'_thumb.jpg'] = TRUE;
					}
				}
			}
		}

		$attendance_keep = array();
		$attendance_rows = $this->normalize_attendance_record_versions(
			array_values((array) absen_data_store_load_value('attendance_records', array(), $this->attendance_file_path()))
		);
		for ($i = 0; $i < count($attendance_rows); $i += 1)
		{
			$row = isset($attendance_rows[$i]) && is_array($attendance_rows[$i]) ? $attendance_rows[$i] : array();
			$check_in_photo = $normalize_relative(isset($row['check_in_photo']) ? (string) $row['check_in_photo'] : '');
			if ($check_in_photo !== '' && strpos($check_in_photo, '/uploads/attendance_photo/') === 0)
			{
				$attendance_keep[$check_in_photo] = TRUE;
			}

			$check_out_photo = $normalize_relative(isset($row['check_out_photo']) ? (string) $row['check_out_photo'] : '');
			if ($check_out_photo !== '' && strpos($check_out_photo, '/uploads/attendance_photo/') === 0)
			{
				$attendance_keep[$check_out_photo] = TRUE;
			}
		}

		$cleanup_dir = function ($relative_dir, $keep_map) use ($build_abs, $is_apply) {
			$result = array(
				'scanned' => 0,
				'kept' => 0,
				'candidates' => 0,
				'deleted' => 0,
				'failed' => 0
			);
			$allowed_ext = array(
				'jpg' => TRUE,
				'jpeg' => TRUE,
				'png' => TRUE,
				'webp' => TRUE
			);

			$directory_abs = $build_abs('/'.trim((string) $relative_dir, '/'));
			if ($directory_abs === '' || !is_dir($directory_abs))
			{
				return $result;
			}

			$items = @scandir($directory_abs);
			$list = is_array($items) ? $items : array();
			for ($i = 0; $i < count($list); $i += 1)
			{
				$file_name = (string) $list[$i];
				if ($file_name === '.' || $file_name === '..')
				{
					continue;
				}

				$relative_file = '/'.trim((string) $relative_dir, '/').'/'.$file_name;
				$absolute_file = $build_abs($relative_file);
				if ($absolute_file === '' || !is_file($absolute_file))
				{
					continue;
				}

				$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
				if (!isset($allowed_ext[$file_ext]))
				{
					continue;
				}

				$result['scanned'] += 1;
				if (isset($keep_map[$relative_file]))
				{
					$result['kept'] += 1;
					continue;
				}

				$result['candidates'] += 1;
				if (!$is_apply)
				{
					continue;
				}

				if (@unlink($absolute_file))
				{
					$result['deleted'] += 1;
				}
				else
				{
					$result['failed'] += 1;
				}
			}

			return $result;
		};

		$profile_result = $cleanup_dir('uploads/profile_photo', $profile_keep);
		$attendance_result = $cleanup_dir('uploads/attendance_photo', $attendance_keep);

		echo "profile=".json_encode($profile_result)."\n";
		echo "attendance=".json_encode($attendance_result)."\n";
		echo "apply=".($is_apply ? '1' : '0')."\n";
		if ($is_preview)
		{
			echo "Mode preview: tidak ada file yang dihapus.\n";
			echo "Gunakan: php index.php home/cleanup_legacy_media_files_cli apply\n";
		}
	}

	public function restore_attendance_from_backup_cli($backup_name = '')
	{
		if (!$this->input->is_cli_request())
		{
			show_404();
			return;
		}

		$cache_dir = APPPATH.'cache';
		$base_file = $cache_dir.DIRECTORY_SEPARATOR.'attendance_records.json';
		$backup_patterns = array(
			'attendance_records.json.bak_*',
			'attendance_records.before_restore_*.json',
			'attendance_records.before_cleanup_*.json'
		);
		$backup_files = array();
		for ($pattern_i = 0; $pattern_i < count($backup_patterns); $pattern_i += 1)
		{
			$pattern = $cache_dir.DIRECTORY_SEPARATOR.$backup_patterns[$pattern_i];
			$matched = glob($pattern);
			if (!is_array($matched) || empty($matched))
			{
				continue;
			}
			for ($match_i = 0; $match_i < count($matched); $match_i += 1)
			{
				$path = (string) $matched[$match_i];
				if ($path === '')
				{
					continue;
				}
				$backup_files[$path] = $path;
			}
		}
		$backup_files = array_values($backup_files);
		if (empty($backup_files))
		{
			echo "Backup attendance_records tidak ditemukan di ".$cache_dir.".\n";
			return;
		}

		$build_stats = function ($rows) {
			$user_lookup = array();
			$date_min = '';
			$date_max = '';
			$safe_rows = is_array($rows) ? $rows : array();
			for ($stats_i = 0; $stats_i < count($safe_rows); $stats_i += 1)
			{
				$current = isset($safe_rows[$stats_i]) && is_array($safe_rows[$stats_i]) ? $safe_rows[$stats_i] : array();
				$username = strtolower(trim((string) (isset($current['username']) ? $current['username'] : '')));
				if ($username !== '')
				{
					$user_lookup[$username] = TRUE;
				}
				$date_value = trim((string) (isset($current['date']) ? $current['date'] : ''));
				if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $date_value))
				{
					continue;
				}
				if ($date_min === '' || strcmp($date_value, $date_min) < 0)
				{
					$date_min = $date_value;
				}
				if ($date_max === '' || strcmp($date_value, $date_max) > 0)
				{
					$date_max = $date_value;
				}
			}

			return array(
				'users' => count($user_lookup),
				'date_min' => $date_min,
				'date_max' => $date_max
			);
		};

		$candidates = array();
		for ($i = 0; $i < count($backup_files); $i += 1)
		{
			$path = (string) $backup_files[$i];
			$content = @file_get_contents($path);
			if ($content === FALSE || trim((string) $content) === '')
			{
				continue;
			}
			if (substr($content, 0, 3) === "\xEF\xBB\xBF")
			{
				$content = substr($content, 3);
			}
			$decoded = json_decode($content, TRUE);
			if (!is_array($decoded))
			{
				continue;
			}
			$raw_rows = array_values($decoded);
			$normalized_rows = $this->normalize_attendance_record_versions($raw_rows);
			$raw_stats = $build_stats($raw_rows);
			$normalized_stats = $build_stats($normalized_rows);
			$candidates[] = array(
				'path' => $path,
				'name' => basename($path),
				'raw_rows' => count($raw_rows),
				'normalized_rows' => count($normalized_rows),
				'raw_users' => isset($raw_stats['users']) ? (int) $raw_stats['users'] : 0,
				'normalized_users' => isset($normalized_stats['users']) ? (int) $normalized_stats['users'] : 0,
				'raw_date_min' => isset($raw_stats['date_min']) ? (string) $raw_stats['date_min'] : '',
				'raw_date_max' => isset($raw_stats['date_max']) ? (string) $raw_stats['date_max'] : '',
				'normalized_date_min' => isset($normalized_stats['date_min']) ? (string) $normalized_stats['date_min'] : '',
				'normalized_date_max' => isset($normalized_stats['date_max']) ? (string) $normalized_stats['date_max'] : '',
				'mtime' => (int) @filemtime($path),
				'data' => $raw_rows
			);
		}

		if (empty($candidates))
		{
			echo "Backup attendance_records ditemukan, tapi tidak ada yang valid JSON.\n";
			return;
		}

		$backup_key = strtolower(trim((string) $backup_name));
		$list_only = $backup_key === 'list' || $backup_key === '--list' || $backup_key === '-l';
		usort($candidates, function ($left, $right) {
			$left_mtime = isset($left['mtime']) ? (int) $left['mtime'] : 0;
			$right_mtime = isset($right['mtime']) ? (int) $right['mtime'] : 0;
			return $right_mtime <=> $left_mtime;
		});

		if ($list_only)
		{
			echo "Daftar backup attendance_records (urut terbaru):\n";
			for ($list_i = 0; $list_i < count($candidates); $list_i += 1)
			{
				$list_name = isset($candidates[$list_i]['name']) ? (string) $candidates[$list_i]['name'] : '-';
				$list_raw_rows = isset($candidates[$list_i]['raw_rows']) ? (int) $candidates[$list_i]['raw_rows'] : 0;
				$list_normalized_rows = isset($candidates[$list_i]['normalized_rows']) ? (int) $candidates[$list_i]['normalized_rows'] : 0;
				$list_raw_users = isset($candidates[$list_i]['raw_users']) ? (int) $candidates[$list_i]['raw_users'] : 0;
				$list_normalized_users = isset($candidates[$list_i]['normalized_users']) ? (int) $candidates[$list_i]['normalized_users'] : 0;
				$list_raw_date_min = isset($candidates[$list_i]['raw_date_min']) ? (string) $candidates[$list_i]['raw_date_min'] : '';
				$list_raw_date_max = isset($candidates[$list_i]['raw_date_max']) ? (string) $candidates[$list_i]['raw_date_max'] : '';
				$list_norm_date_min = isset($candidates[$list_i]['normalized_date_min']) ? (string) $candidates[$list_i]['normalized_date_min'] : '';
				$list_norm_date_max = isset($candidates[$list_i]['normalized_date_max']) ? (string) $candidates[$list_i]['normalized_date_max'] : '';
				$list_mtime = isset($candidates[$list_i]['mtime']) ? (int) $candidates[$list_i]['mtime'] : 0;
				$list_time = $list_mtime > 0 ? date('Y-m-d H:i:s', $list_mtime) : '-';
				echo "- ".$list_name.
					" | raw_rows=".$list_raw_rows.
					" (users=".$list_raw_users.", date=".$list_raw_date_min."..".$list_raw_date_max.")".
					" | normalized_rows=".$list_normalized_rows.
					" (users=".$list_normalized_users.", date=".$list_norm_date_min."..".$list_norm_date_max.")".
					" | mtime=".$list_time."\n";
			}
			return;
		}

		if ($backup_key === '')
		{
			echo "Nama backup wajib diisi.\n";
			echo "Lihat daftar: php index.php home/restore_attendance_from_backup_cli list\n";
			echo "Restore contoh: php index.php home/restore_attendance_from_backup_cli attendance_records.before_restore_YYYYMMDD_HHMMSS.json\n";
			return;
		}

		$selected_index = -1;
		$backup_basename = basename($backup_key);
		for ($match_i = 0; $match_i < count($candidates); $match_i += 1)
		{
			$candidate_name = isset($candidates[$match_i]['name']) ? strtolower((string) $candidates[$match_i]['name']) : '';
			if ($candidate_name === $backup_basename)
			{
				$selected_index = $match_i;
				break;
			}
		}
		if ($selected_index < 0)
		{
			echo "Backup ".$backup_name." tidak ditemukan.\n";
			echo "Jalankan: php index.php home/restore_attendance_from_backup_cli list\n";
			return;
		}

		$selected = $candidates[$selected_index];
		$restore_rows = isset($selected['data']) && is_array($selected['data']) ? $selected['data'] : array();
		$restore_count = count($restore_rows);
		if ($restore_count <= 0)
		{
			echo "Backup terpilih kosong setelah normalisasi. Batal restore.\n";
			return;
		}

		$current_rows = $this->load_attendance_records();
		$current_count = is_array($current_rows) ? count($current_rows) : 0;
		if (is_file($base_file))
		{
			$before_file = $cache_dir.DIRECTORY_SEPARATOR.'attendance_records.before_restore_'.date('Ymd_His').'.json';
			@copy($base_file, $before_file);
		}

		$this->save_attendance_records($restore_rows);
		$this->clear_admin_dashboard_live_summary_cache();

		$after_rows = $this->load_attendance_records();
		$after_count = is_array($after_rows) ? count($after_rows) : 0;
		$selected_name = isset($selected['name']) ? (string) $selected['name'] : '-';
		echo "Restore attendance_records selesai.\n";
		echo "backup=".$selected_name.", before=".$current_count.", restored=".$restore_count.", after=".$after_count."\n";
	}

	public function cleanup_attendance_records_cli($mode = '')
	{
		if (!$this->input->is_cli_request())
		{
			show_404();
			return;
		}

		$mode = strtolower(trim((string) $mode));
		if ($mode === '')
		{
			$mode = strtolower(trim((string) $this->uri->segment(4)));
		}
		if ($mode === '')
		{
			$mode = 'safe';
		}
		if ($mode !== 'safe' && $mode !== 'strict')
		{
			echo "Mode cleanup tidak dikenal: ".$mode."\n";
			echo "Gunakan: php index.php home/cleanup_attendance_records_cli [safe|strict]\n";
			return;
		}

		$file_path = $this->attendance_file_path();
		$raw_rows = function_exists('absen_data_store_load_value')
			? absen_data_store_load_value('attendance_records', array(), $file_path)
			: array();
		if (!is_array($raw_rows))
		{
			$raw_rows = array();
		}

		if (empty($raw_rows) && is_file($file_path))
		{
			$content = @file_get_contents($file_path);
			if ($content !== FALSE && trim((string) $content) !== '')
			{
				if (substr($content, 0, 3) === "\xEF\xBB\xBF")
				{
					$content = substr($content, 3);
				}
				$decoded = json_decode($content, TRUE);
				if (is_array($decoded))
				{
					$raw_rows = array_values($decoded);
				}
			}
		}

		$before_count = count($raw_rows);
		if ($before_count <= 0)
		{
			echo "Cleanup attendance_records dibatalkan: data kosong.\n";
			return;
		}

		$cache_dir = dirname($file_path);
		if (is_file($file_path))
		{
			$backup_file = $cache_dir.DIRECTORY_SEPARATOR.'attendance_records.before_cleanup_'.date('Ymd_His').'.json';
			@copy($file_path, $backup_file);
		}

		$working_rows = $mode === 'strict'
			? $this->normalize_attendance_record_versions(array_values($raw_rows))
			: array_values($raw_rows);
		$working_count = count($working_rows);

		// Dedup per username+tanggal: simpan record paling lengkap/terbaru.
		$dedup_map = array();
		$dedup_rows = array();
		for ($i = 0; $i < $working_count; $i += 1)
		{
			$row = isset($working_rows[$i]) && is_array($working_rows[$i]) ? $working_rows[$i] : array();
			$username = strtolower(trim((string) (isset($row['username']) ? $row['username'] : '')));
			$date_key = trim((string) (isset($row['date']) ? $row['date'] : ''));
			if ($username === '' || !$this->is_valid_date_format($date_key))
			{
				if ($mode === 'safe')
				{
					$dedup_rows[] = $row;
				}
				continue;
			}

			$composite_key = $username.'|'.$date_key;
			$current_ts = isset($row['updated_at']) ? strtotime((string) $row['updated_at']) : FALSE;
			$current_ts = $current_ts === FALSE ? 0 : (int) $current_ts;
			$current_version = isset($row['record_version']) ? (int) $row['record_version'] : 1;
			$current_checkout = $this->has_real_attendance_time(isset($row['check_out_time']) ? $row['check_out_time'] : '') ? 1 : 0;
			$current_checkin = $this->has_real_attendance_time(isset($row['check_in_time']) ? $row['check_in_time'] : '') ? 1 : 0;
			$current_score = ($current_checkout * 1000000000) + ($current_checkin * 100000000) + ($current_version * 1000000) + $current_ts;

			if (!isset($dedup_map[$composite_key]))
			{
				$dedup_map[$composite_key] = array(
					'index' => count($dedup_rows),
					'score' => $current_score
				);
				$dedup_rows[] = $row;
				continue;
			}

			$existing_index = isset($dedup_map[$composite_key]['index']) ? (int) $dedup_map[$composite_key]['index'] : -1;
			$existing_score = isset($dedup_map[$composite_key]['score']) ? (int) $dedup_map[$composite_key]['score'] : -1;
			if ($existing_index < 0 || $current_score >= $existing_score)
			{
				if ($existing_index >= 0 && isset($dedup_rows[$existing_index]) && is_array($dedup_rows[$existing_index]))
				{
					$dedup_rows[$existing_index] = $row;
				}
				else
				{
					$dedup_map[$composite_key]['index'] = count($dedup_rows);
					$dedup_rows[] = $row;
				}
				$dedup_map[$composite_key]['score'] = $current_score;
			}
		}

		$dedup_rows = array_values($dedup_rows);
		if ($mode === 'strict')
		{
			usort($dedup_rows, function ($left, $right) {
				$left_date = isset($left['date']) ? (string) $left['date'] : '';
				$right_date = isset($right['date']) ? (string) $right['date'] : '';
				if ($left_date !== $right_date)
				{
					return strcmp($right_date, $left_date);
				}
				$left_time = isset($left['check_in_time']) ? (string) $left['check_in_time'] : '';
				$right_time = isset($right['check_in_time']) ? (string) $right['check_in_time'] : '';
				return strcmp($right_time, $left_time);
			});
		}

		$this->save_attendance_records($dedup_rows);
		$this->clear_admin_dashboard_live_summary_cache();
		$after_rows = $this->load_attendance_records();
		$after_count = is_array($after_rows) ? count($after_rows) : 0;

		echo "Cleanup attendance_records selesai.\n";
		echo "mode=".$mode.", before=".$before_count.", working=".$working_count.", deduped=".count($dedup_rows).", after=".$after_count."\n";
	}

	public function restore_attendance_merge_cli($backup_name = '', $mode = 'apply')
	{
		if (!$this->input->is_cli_request())
		{
			show_404();
			return;
		}

		$backup_name = trim((string) $backup_name);
		if ($backup_name === '')
		{
			$backup_name = trim((string) $this->uri->segment(4));
		}

		$mode = strtolower(trim((string) $mode));
		if ($mode === '')
		{
			$mode = strtolower(trim((string) $this->uri->segment(5)));
		}
		if ($mode === '')
		{
			$mode = 'apply';
		}
		if ($mode !== 'apply' && $mode !== 'preview')
		{
			echo "Mode tidak dikenal: ".$mode."\n";
			echo "Gunakan: php index.php home/restore_attendance_merge_cli [backup_file|list] [apply|preview]\n";
			return;
		}

		$candidates = $this->attendance_merge_cli_load_backup_candidates();
		if (empty($candidates))
		{
			echo "Backup attendance_records tidak ditemukan.\n";
			return;
		}

		$user_context = $this->attendance_merge_cli_build_user_context();
		$today_date = date('Y-m-d');
		$allow_future_dates = FALSE;

		$backup_key = strtolower(trim((string) $backup_name));
		$list_only = $backup_key === '' || $backup_key === 'list' || $backup_key === '--list' || $backup_key === '-l';
		if ($list_only)
		{
			echo "Daftar backup attendance_records (siap merge, filter user aktif, tanpa data palsu):\n";
			for ($list_i = 0; $list_i < count($candidates); $list_i += 1)
			{
				$candidate_name = isset($candidates[$list_i]['name']) ? (string) $candidates[$list_i]['name'] : '-';
				$candidate_mtime = isset($candidates[$list_i]['mtime']) ? (int) $candidates[$list_i]['mtime'] : 0;
				$candidate_time_label = $candidate_mtime > 0 ? date('Y-m-d H:i:s', $candidate_mtime) : '-';
				$candidate_rows = isset($candidates[$list_i]['rows']) && is_array($candidates[$list_i]['rows'])
					? $candidates[$list_i]['rows']
					: array();
				$prepared = $this->attendance_merge_cli_prepare_rows(
					$candidate_rows,
					isset($candidates[$list_i]['priority']) ? (int) $candidates[$list_i]['priority'] : 100,
					$user_context,
					$allow_future_dates,
					$today_date
				);
				$prepared_rows = isset($prepared['rows']) && is_array($prepared['rows']) ? $prepared['rows'] : array();
				$prepared_rows_flat = array();
				for ($prepared_i = 0; $prepared_i < count($prepared_rows); $prepared_i += 1)
				{
					if (isset($prepared_rows[$prepared_i]['row']) && is_array($prepared_rows[$prepared_i]['row']))
					{
						$prepared_rows_flat[] = $prepared_rows[$prepared_i]['row'];
					}
				}
				$prepared_stats = $this->attendance_merge_cli_rows_stats($prepared_rows_flat);
				echo "- ".$candidate_name.
					" | raw_rows=".count($candidate_rows).
					" | prepared_rows=".count($prepared_rows).
					" (users=".(int) $prepared_stats['users'].", date=".(string) $prepared_stats['date_min']."..".(string) $prepared_stats['date_max'].")".
					" | mtime=".$candidate_time_label."\n";
			}
			echo "Jalankan contoh:\n";
			echo "php index.php home/restore_attendance_merge_cli attendance_records.before_cleanup_YYYYMMDD_HHMMSS.json preview\n";
			echo "php index.php home/restore_attendance_merge_cli attendance_records.before_cleanup_YYYYMMDD_HHMMSS.json apply\n";
			echo "php index.php home/restore_attendance_merge_cli latest_before_cleanup preview\n";
			return;
		}

		$selected_index = -1;
		$target_name = strtolower(trim((string) basename($backup_key)));
		if ($target_name === 'latest' || $target_name === 'terbaru')
		{
			$selected_index = 0;
		}
		if ($selected_index < 0 && ($target_name === 'latest_before_cleanup' || $target_name === 'terbaru_before_cleanup'))
		{
			for ($latest_i = 0; $latest_i < count($candidates); $latest_i += 1)
			{
				$latest_name = isset($candidates[$latest_i]['name']) ? strtolower(trim((string) $candidates[$latest_i]['name'])) : '';
				if (strpos($latest_name, 'attendance_records.before_cleanup_') === 0)
				{
					$selected_index = $latest_i;
					break;
				}
			}
		}
		for ($i = 0; $i < count($candidates); $i += 1)
		{
			$candidate_name = isset($candidates[$i]['name']) ? strtolower(trim((string) $candidates[$i]['name'])) : '';
			if ($candidate_name === $target_name)
			{
				$selected_index = $i;
				break;
			}
		}
		if ($selected_index < 0)
		{
			echo "Backup ".$backup_name." tidak ditemukan.\n";
			echo "Lihat daftar: php index.php home/restore_attendance_merge_cli list\n";
			return;
		}

		$selected = $candidates[$selected_index];
		$backup_rows_raw = isset($selected['rows']) && is_array($selected['rows'])
			? $selected['rows']
			: array();
		$current_rows_raw = $this->load_attendance_records();

		$datasets = array(
			array(
				'name' => isset($selected['name']) ? (string) $selected['name'] : 'backup',
				'priority' => 120,
				'rows' => $backup_rows_raw
			),
			array(
				'name' => 'current_live',
				'priority' => 240,
				'rows' => is_array($current_rows_raw) ? $current_rows_raw : array()
			)
		);
		$merge_result = $this->attendance_merge_cli_merge_datasets(
			$datasets,
			$user_context,
			$allow_future_dates,
			$today_date
		);
		$merged_rows = isset($merge_result['rows']) && is_array($merge_result['rows'])
			? $merge_result['rows']
			: array();
		$merge_stats = isset($merge_result['stats']) && is_array($merge_result['stats'])
			? $merge_result['stats']
			: array();

		$before_rows = $this->load_attendance_records();
		$before_count = is_array($before_rows) ? count($before_rows) : 0;
		$after_count = $before_count;

		$selected_name = isset($selected['name']) ? (string) $selected['name'] : '-';
		echo "Restore+merge attendance preview:\n";
		echo "backup=".$selected_name.
			", backup_raw=".count($backup_rows_raw).
			", current_raw=".(is_array($current_rows_raw) ? count($current_rows_raw) : 0).
			", merged=".count($merged_rows).
			", before=".$before_count."\n";
		echo "stats: valid_input=".(int) (isset($merge_stats['valid_input']) ? $merge_stats['valid_input'] : 0).
			", skipped_snapshot=".(int) (isset($merge_stats['skipped_snapshot']) ? $merge_stats['skipped_snapshot'] : 0).
			", skipped_user=".(int) (isset($merge_stats['skipped_user']) ? $merge_stats['skipped_user'] : 0).
			", skipped_date=".(int) (isset($merge_stats['skipped_date']) ? $merge_stats['skipped_date'] : 0).
			", skipped_future=".(int) (isset($merge_stats['skipped_future']) ? $merge_stats['skipped_future'] : 0).
			", skipped_time=".(int) (isset($merge_stats['skipped_time']) ? $merge_stats['skipped_time'] : 0).
			", dedup_replaced=".(int) (isset($merge_stats['dedup_replaced']) ? $merge_stats['dedup_replaced'] : 0).
			", cap_trimmed=".(int) (isset($merge_stats['cap_trimmed']) ? $merge_stats['cap_trimmed'] : 0)."\n";

		$preview_stats = $this->attendance_merge_cli_rows_stats($merged_rows);
		echo "merged_scope: users=".(int) $preview_stats['users'].
			", range=".(string) $preview_stats['date_min']."..".(string) $preview_stats['date_max']."\n";

		if ($mode === 'preview')
		{
			echo "Mode preview: tidak menyimpan perubahan.\n";
			return;
		}

		$file_path = $this->attendance_file_path();
		$cache_dir = dirname($file_path);
		if (is_file($file_path))
		{
			$before_file = $cache_dir.DIRECTORY_SEPARATOR.'attendance_records.before_restore_merge_'.date('Ymd_His').'.json';
			@copy($file_path, $before_file);
		}

		$this->save_attendance_records($merged_rows);
		$this->clear_admin_dashboard_live_summary_cache();
		$saved_rows = $this->load_attendance_records();
		$after_count = is_array($saved_rows) ? count($saved_rows) : 0;

		echo "Restore+merge attendance selesai.\n";
		echo "before=".$before_count.", merged=".count($merged_rows).", after=".$after_count."\n";
	}

	private function attendance_merge_cli_load_backup_candidates()
	{
		$cache_dir = APPPATH.'cache';
		$patterns = array(
			'attendance_records.before_cleanup_*.json' => 210,
			'attendance_records.before_restore_*.json' => 205,
			'attendance_records.before_restore_merge_*.json' => 202,
			'attendance_records.json.bak_*' => 180
		);
		$file_map = array();
		foreach ($patterns as $pattern => $priority)
		{
			$paths = glob($cache_dir.DIRECTORY_SEPARATOR.$pattern);
			if (!is_array($paths) || empty($paths))
			{
				continue;
			}
			for ($i = 0; $i < count($paths); $i += 1)
			{
				$path = (string) $paths[$i];
				if ($path === '' || !is_file($path))
				{
					continue;
				}
				$file_map[$path] = array(
					'path' => $path,
					'name' => basename($path),
					'priority' => (int) $priority,
					'mtime' => (int) @filemtime($path)
				);
			}
		}

		$candidates = array_values($file_map);
		for ($i = 0; $i < count($candidates); $i += 1)
		{
			$candidates[$i]['rows'] = $this->attendance_merge_cli_load_json_rows_file($candidates[$i]['path']);
		}
		usort($candidates, function ($left, $right) {
			$left_mtime = isset($left['mtime']) ? (int) $left['mtime'] : 0;
			$right_mtime = isset($right['mtime']) ? (int) $right['mtime'] : 0;
			if ($left_mtime !== $right_mtime)
			{
				return $right_mtime <=> $left_mtime;
			}
			$left_priority = isset($left['priority']) ? (int) $left['priority'] : 0;
			$right_priority = isset($right['priority']) ? (int) $right['priority'] : 0;
			return $right_priority <=> $left_priority;
		});

		return $candidates;
	}

	private function attendance_merge_cli_load_json_rows_file($path)
	{
		$file_path = (string) $path;
		if ($file_path === '' || !is_file($file_path))
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
		return is_array($decoded) ? array_values($decoded) : array();
	}

	private function attendance_merge_cli_build_user_context()
	{
		$accounts = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		$active_users = array();
		$alias_map = array();

		foreach ($accounts as $username_key => $account_row)
		{
			$username = strtolower(trim((string) $username_key));
			if ($username === '' || !is_array($account_row))
			{
				continue;
			}
			if ($this->is_reserved_system_username($username))
			{
				continue;
			}

			$role = strtolower(trim((string) (isset($account_row['role']) ? $account_row['role'] : 'user')));
			if ($role !== 'user')
			{
				continue;
			}

			$active_users[$username] = TRUE;
			$this->attendance_merge_cli_add_alias($alias_map, $username, $username);

			$display_name = isset($account_row['display_name']) ? (string) $account_row['display_name'] : '';
			$login_alias = isset($account_row['login_alias']) ? (string) $account_row['login_alias'] : '';
			$employee_id = isset($account_row['employee_id']) ? (string) $account_row['employee_id'] : '';
			$this->attendance_merge_cli_add_alias($alias_map, $display_name, $username);
			$this->attendance_merge_cli_add_alias($alias_map, $login_alias, $username);
			$this->attendance_merge_cli_add_alias($alias_map, $employee_id, $username);
		}

		return array(
			'active_users' => $active_users,
			'alias_map' => $alias_map
		);
	}

	private function attendance_merge_cli_add_alias(&$alias_map, $token, $username)
	{
		$username_key = strtolower(trim((string) $username));
		$token_text = trim((string) $token);
		if ($username_key === '' || $token_text === '')
		{
			return;
		}

		$candidates = array();
		$candidates[] = strtolower($token_text);
		$normalized = $this->normalize_username_key($token_text);
		if ($normalized !== '')
		{
			$candidates[] = $normalized;
			$compact = str_replace('_', '', $normalized);
			if ($compact !== '')
			{
				$candidates[] = $compact;
			}
		}

		for ($i = 0; $i < count($candidates); $i += 1)
		{
			$key = trim((string) $candidates[$i]);
			if ($key === '')
			{
				continue;
			}
			if (!isset($alias_map[$key]))
			{
				$alias_map[$key] = $username_key;
			}
		}
	}

	private function attendance_merge_cli_resolve_username($row, $user_context)
	{
		if (!is_array($row) || !is_array($user_context))
		{
			return '';
		}

		$active_users = isset($user_context['active_users']) && is_array($user_context['active_users'])
			? $user_context['active_users']
			: array();
		$alias_map = isset($user_context['alias_map']) && is_array($user_context['alias_map'])
			? $user_context['alias_map']
			: array();

		$candidates = array(
			isset($row['username']) ? (string) $row['username'] : '',
			isset($row['display_name']) ? (string) $row['display_name'] : '',
			isset($row['name']) ? (string) $row['name'] : '',
			isset($row['employee_id']) ? (string) $row['employee_id'] : ''
		);

		for ($i = 0; $i < count($candidates); $i += 1)
		{
			$candidate_raw = trim((string) $candidates[$i]);
			if ($candidate_raw === '')
			{
				continue;
			}
			$variants = array();
			$variants[] = strtolower($candidate_raw);
			$normalized = $this->normalize_username_key($candidate_raw);
			if ($normalized !== '')
			{
				$variants[] = $normalized;
				$compact = str_replace('_', '', $normalized);
				if ($compact !== '')
				{
					$variants[] = $compact;
				}
			}

			for ($variant_i = 0; $variant_i < count($variants); $variant_i += 1)
			{
				$variant_key = trim((string) $variants[$variant_i]);
				if ($variant_key === '')
				{
					continue;
				}
				if (isset($alias_map[$variant_key]))
				{
					$mapped_username = strtolower(trim((string) $alias_map[$variant_key]));
					if ($mapped_username !== '' && isset($active_users[$mapped_username]))
					{
						return $mapped_username;
					}
				}
				if (isset($active_users[$variant_key]))
				{
					return $variant_key;
				}
			}
		}

		return '';
	}

	private function attendance_merge_cli_extract_date($row)
	{
		if (!is_array($row))
		{
			return '';
		}

		$date_keys = array('date', 'attendance_date', 'tanggal', 'sheet_tanggal_absen', 'date_label');
		for ($i = 0; $i < count($date_keys); $i += 1)
		{
			$key = $date_keys[$i];
			$value = isset($row[$key]) ? (string) $row[$key] : '';
			$normalized = $this->attendance_merge_cli_normalize_date_text($value);
			if ($normalized !== '')
			{
				return $normalized;
			}
		}

		return '';
	}

	private function attendance_merge_cli_normalize_date_text($raw_text)
	{
		$text = trim((string) $raw_text);
		if ($text === '')
		{
			return '';
		}

		if ($this->is_valid_date_format($text))
		{
			return $text;
		}

		$matches_ymd = array();
		preg_match_all('/\d{4}\-\d{2}\-\d{2}/', $text, $matches_ymd);
		if (isset($matches_ymd[0]) && is_array($matches_ymd[0]) && !empty($matches_ymd[0]))
		{
			$last = (string) $matches_ymd[0][count($matches_ymd[0]) - 1];
			if ($this->is_valid_date_format($last))
			{
				return $last;
			}
		}

		$matches_dmy = array();
		preg_match_all('/(\d{2})[\-\/](\d{2})[\-\/](\d{4})/', $text, $matches_dmy, PREG_SET_ORDER);
		if (!empty($matches_dmy))
		{
			$last_match = $matches_dmy[count($matches_dmy) - 1];
			$converted = sprintf('%04d-%02d-%02d', (int) $last_match[3], (int) $last_match[2], (int) $last_match[1]);
			if ($this->is_valid_date_format($converted))
			{
				return $converted;
			}
		}

		$month_map = array(
			'januari' => 1,
			'februari' => 2,
			'maret' => 3,
			'april' => 4,
			'mei' => 5,
			'juni' => 6,
			'juli' => 7,
			'agustus' => 8,
			'september' => 9,
			'oktober' => 10,
			'november' => 11,
			'desember' => 12
		);
		$text_lower = strtolower($text);
		$month_match = array();
		if (preg_match('/(\d{1,2})\s+([a-z]+)\s+(\d{4})/', $text_lower, $month_match))
		{
			$day_value = (int) $month_match[1];
			$month_name = trim((string) $month_match[2]);
			$year_value = (int) $month_match[3];
			if (isset($month_map[$month_name]))
			{
				$converted = sprintf('%04d-%02d-%02d', $year_value, (int) $month_map[$month_name], $day_value);
				if ($this->is_valid_date_format($converted))
				{
					return $converted;
				}
			}
		}

		return '';
	}

	private function attendance_merge_cli_normalize_row($row, $user_context, $allow_future_dates, $today_date)
	{
		if (!is_array($row))
		{
			return array();
		}
		if ($this->is_attendance_sheet_snapshot_row($row))
		{
			return array(
				'_skip_reason' => 'snapshot'
			);
		}

		$username = $this->attendance_merge_cli_resolve_username($row, $user_context);
		if ($username === '')
		{
			return array(
				'_skip_reason' => 'user'
			);
		}

		$date_key = $this->attendance_merge_cli_extract_date($row);
		if (!$this->is_valid_date_format($date_key))
		{
			return array(
				'_skip_reason' => 'date'
			);
		}

		if (!$allow_future_dates && $today_date !== '' && strcmp($date_key, $today_date) > 0)
		{
			return array(
				'_skip_reason' => 'future'
			);
		}

		$check_in_raw = isset($row['check_in_time']) ? trim((string) $row['check_in_time']) : '';
		$check_out_raw = isset($row['check_out_time']) ? trim((string) $row['check_out_time']) : '';
		$has_check_in = $this->has_real_attendance_time($check_in_raw);
		$has_check_out = $this->has_real_attendance_time($check_out_raw);
		if (!$has_check_in && !$has_check_out)
		{
			return array(
				'_skip_reason' => 'time'
			);
		}

		$normalized = $row;
		$normalized['username'] = $username;
		$normalized['date'] = $date_key;
		if (!isset($normalized['date_label']) || trim((string) $normalized['date_label']) === '')
		{
			$normalized['date_label'] = date('d-m-Y', strtotime($date_key.' 00:00:00'));
		}
		$record_version = isset($normalized['record_version']) ? (int) $normalized['record_version'] : 1;
		if ($record_version <= 0)
		{
			$record_version = 1;
		}
		$normalized['record_version'] = $record_version;
		$normalized['_skip_reason'] = '';
		return $normalized;
	}

	private function attendance_merge_cli_row_rank($row, $source_priority)
	{
		$check_in_raw = isset($row['check_in_time']) ? (string) $row['check_in_time'] : '';
		$check_out_raw = isset($row['check_out_time']) ? (string) $row['check_out_time'] : '';
		$has_check_in = $this->has_real_attendance_time($check_in_raw) ? 1 : 0;
		$has_check_out = $this->has_real_attendance_time($check_out_raw) ? 1 : 0;
		$updated_ts = isset($row['updated_at']) ? strtotime((string) $row['updated_at']) : FALSE;
		if ($updated_ts === FALSE)
		{
			$updated_ts = 0;
		}
		$record_version = isset($row['record_version']) ? (int) $row['record_version'] : 1;
		if ($record_version < 0)
		{
			$record_version = 0;
		}
		$filled_fields = 0;
		$check_keys = array(
			'check_in_time',
			'check_out_time',
			'check_in_photo',
			'check_out_photo',
			'check_in_lat',
			'check_in_lng',
			'check_out_lat',
			'check_out_lng',
			'work_duration',
			'late_reason',
			'salary_cut_amount'
		);
		for ($i = 0; $i < count($check_keys); $i += 1)
		{
			$value = isset($row[$check_keys[$i]]) ? trim((string) $row[$check_keys[$i]]) : '';
			if ($value === '' || $value === '-' || $value === '--')
			{
				continue;
			}
			$filled_fields += 1;
		}

		$in_seconds = $this->attendance_merge_cli_time_to_seconds($check_in_raw);
		$out_seconds = $this->attendance_merge_cli_time_to_seconds($check_out_raw);

		return array(
			(int) $source_priority,
			$has_check_out,
			$has_check_in,
			$filled_fields,
			$record_version,
			(int) $updated_ts,
			$out_seconds,
			$in_seconds
		);
	}

	private function attendance_merge_cli_time_to_seconds($raw_time)
	{
		$time_text = trim((string) $raw_time);
		if (!preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $time_text))
		{
			return 0;
		}
		if (strlen($time_text) === 5)
		{
			$time_text .= ':00';
		}
		if ($time_text === '00:00:00')
		{
			return 0;
		}
		$parts = explode(':', $time_text);
		if (count($parts) !== 3)
		{
			return 0;
		}
		return ((int) $parts[0] * 3600) + ((int) $parts[1] * 60) + (int) $parts[2];
	}

	private function attendance_merge_cli_is_rank_better($candidate_rank, $existing_rank)
	{
		$candidate = is_array($candidate_rank) ? array_values($candidate_rank) : array();
		$existing = is_array($existing_rank) ? array_values($existing_rank) : array();
		$max_len = max(count($candidate), count($existing));
		for ($i = 0; $i < $max_len; $i += 1)
		{
			$left = isset($candidate[$i]) ? (int) $candidate[$i] : 0;
			$right = isset($existing[$i]) ? (int) $existing[$i] : 0;
			if ($left > $right)
			{
				return TRUE;
			}
			if ($left < $right)
			{
				return FALSE;
			}
		}

		return FALSE;
	}

	private function attendance_merge_cli_prepare_rows($rows, $source_priority, $user_context, $allow_future_dates, $today_date)
	{
		$input_rows = is_array($rows) ? array_values($rows) : array();
		$prepared_rows = array();
		$stats = array(
			'valid_input' => 0,
			'skipped_snapshot' => 0,
			'skipped_user' => 0,
			'skipped_date' => 0,
			'skipped_future' => 0,
			'skipped_time' => 0
		);

		for ($i = 0; $i < count($input_rows); $i += 1)
		{
			$normalized = $this->attendance_merge_cli_normalize_row(
				isset($input_rows[$i]) ? $input_rows[$i] : array(),
				$user_context,
				$allow_future_dates,
				$today_date
			);
			if (!is_array($normalized) || empty($normalized))
			{
				continue;
			}
			$skip_reason = isset($normalized['_skip_reason']) ? (string) $normalized['_skip_reason'] : '';
			unset($normalized['_skip_reason']);
			if ($skip_reason !== '')
			{
				if ($skip_reason === 'snapshot')
				{
					$stats['skipped_snapshot'] += 1;
				}
				elseif ($skip_reason === 'user')
				{
					$stats['skipped_user'] += 1;
				}
				elseif ($skip_reason === 'date')
				{
					$stats['skipped_date'] += 1;
				}
				elseif ($skip_reason === 'future')
				{
					$stats['skipped_future'] += 1;
				}
				elseif ($skip_reason === 'time')
				{
					$stats['skipped_time'] += 1;
				}
				continue;
			}

			$stats['valid_input'] += 1;
			$prepared_rows[] = array(
				'row' => $normalized,
				'rank' => $this->attendance_merge_cli_row_rank($normalized, (int) $source_priority)
			);
		}

		return array(
			'rows' => $prepared_rows,
			'stats' => $stats
		);
	}

	private function attendance_merge_cli_merge_datasets($datasets, $user_context, $allow_future_dates, $today_date)
	{
		$dataset_rows = is_array($datasets) ? array_values($datasets) : array();
		$merged_map = array();
		$stats = array(
			'valid_input' => 0,
			'skipped_snapshot' => 0,
			'skipped_user' => 0,
			'skipped_date' => 0,
			'skipped_future' => 0,
			'skipped_time' => 0,
			'dedup_replaced' => 0,
			'cap_trimmed' => 0
		);

		for ($dataset_i = 0; $dataset_i < count($dataset_rows); $dataset_i += 1)
		{
			$source_priority = isset($dataset_rows[$dataset_i]['priority']) ? (int) $dataset_rows[$dataset_i]['priority'] : 100;
			$source_rows = isset($dataset_rows[$dataset_i]['rows']) && is_array($dataset_rows[$dataset_i]['rows'])
				? $dataset_rows[$dataset_i]['rows']
				: array();
			$prepared = $this->attendance_merge_cli_prepare_rows(
				$source_rows,
				$source_priority,
				$user_context,
				$allow_future_dates,
				$today_date
			);
			$prepared_rows = isset($prepared['rows']) && is_array($prepared['rows'])
				? $prepared['rows']
				: array();
			$prepared_stats = isset($prepared['stats']) && is_array($prepared['stats'])
				? $prepared['stats']
				: array();
			$stats['valid_input'] += (int) (isset($prepared_stats['valid_input']) ? $prepared_stats['valid_input'] : 0);
			$stats['skipped_snapshot'] += (int) (isset($prepared_stats['skipped_snapshot']) ? $prepared_stats['skipped_snapshot'] : 0);
			$stats['skipped_user'] += (int) (isset($prepared_stats['skipped_user']) ? $prepared_stats['skipped_user'] : 0);
			$stats['skipped_date'] += (int) (isset($prepared_stats['skipped_date']) ? $prepared_stats['skipped_date'] : 0);
			$stats['skipped_future'] += (int) (isset($prepared_stats['skipped_future']) ? $prepared_stats['skipped_future'] : 0);
			$stats['skipped_time'] += (int) (isset($prepared_stats['skipped_time']) ? $prepared_stats['skipped_time'] : 0);

			for ($row_i = 0; $row_i < count($prepared_rows); $row_i += 1)
			{
				$current_row = isset($prepared_rows[$row_i]['row']) && is_array($prepared_rows[$row_i]['row'])
					? $prepared_rows[$row_i]['row']
					: array();
				$current_rank = isset($prepared_rows[$row_i]['rank']) && is_array($prepared_rows[$row_i]['rank'])
					? $prepared_rows[$row_i]['rank']
					: array();
				$username = strtolower(trim((string) (isset($current_row['username']) ? $current_row['username'] : '')));
				$date_key = trim((string) (isset($current_row['date']) ? $current_row['date'] : ''));
				if ($username === '' || !$this->is_valid_date_format($date_key))
				{
					continue;
				}
				$composite_key = $username.'|'.$date_key;
				if (!isset($merged_map[$composite_key]))
				{
					$merged_map[$composite_key] = array(
						'row' => $current_row,
						'rank' => $current_rank
					);
					continue;
				}
				$existing_rank = isset($merged_map[$composite_key]['rank']) ? $merged_map[$composite_key]['rank'] : array();
				if ($this->attendance_merge_cli_is_rank_better($current_rank, $existing_rank))
				{
					$merged_map[$composite_key] = array(
						'row' => $current_row,
						'rank' => $current_rank
					);
					$stats['dedup_replaced'] += 1;
				}
			}
		}

		$merged_rows = array();
		foreach ($merged_map as $entry)
		{
			if (!isset($entry['row']) || !is_array($entry['row']))
			{
				continue;
			}
			$merged_rows[] = $entry['row'];
		}
		usort($merged_rows, function ($left, $right) {
			$left_date = isset($left['date']) ? (string) $left['date'] : '';
			$right_date = isset($right['date']) ? (string) $right['date'] : '';
			if ($left_date !== $right_date)
			{
				return strcmp($right_date, $left_date);
			}
			$left_time = isset($left['check_in_time']) ? (string) $left['check_in_time'] : '';
			$right_time = isset($right['check_in_time']) ? (string) $right['check_in_time'] : '';
			if ($left_time !== $right_time)
			{
				return strcmp($right_time, $left_time);
			}
			$left_user = isset($left['username']) ? (string) $left['username'] : '';
			$right_user = isset($right['username']) ? (string) $right['username'] : '';
			return strcmp($left_user, $right_user);
		});
		$merged_rows = $this->attendance_merge_cli_apply_sheet_count_caps($merged_rows, $stats);

		return array(
			'rows' => $merged_rows,
			'stats' => $stats
		);
	}

	private function attendance_merge_cli_apply_sheet_count_caps($rows, &$stats)
	{
		$records = is_array($rows) ? array_values($rows) : array();
		if (empty($records))
		{
			return $records;
		}

		$cap_by_user = array();
		for ($i = 0; $i < count($records); $i += 1)
		{
			$row = isset($records[$i]) && is_array($records[$i]) ? $records[$i] : array();
			$username = strtolower(trim((string) (isset($row['username']) ? $row['username'] : '')));
			if ($username === '')
			{
				continue;
			}
			$user_cap = $this->attendance_merge_cli_row_sheet_hadir_cap($row);
			if ($user_cap <= 0)
			{
				continue;
			}
			if (!isset($cap_by_user[$username]) || $user_cap > (int) $cap_by_user[$username])
			{
				$cap_by_user[$username] = (int) $user_cap;
			}
		}
		if (empty($cap_by_user))
		{
			return $records;
		}

		$kept_count_by_user = array();
		$filtered = array();
		$trimmed = 0;
		for ($row_i = 0; $row_i < count($records); $row_i += 1)
		{
			$row = isset($records[$row_i]) && is_array($records[$row_i]) ? $records[$row_i] : array();
			$username = strtolower(trim((string) (isset($row['username']) ? $row['username'] : '')));
			if ($username === '' || !isset($cap_by_user[$username]))
			{
				$filtered[] = $row;
				continue;
			}
			$cap_value = (int) $cap_by_user[$username];
			if ($cap_value <= 0)
			{
				$filtered[] = $row;
				continue;
			}
			$kept_now = isset($kept_count_by_user[$username]) ? (int) $kept_count_by_user[$username] : 0;
			if ($kept_now >= $cap_value)
			{
				$trimmed += 1;
				continue;
			}
			$filtered[] = $row;
			$kept_count_by_user[$username] = $kept_now + 1;
		}

		if ($trimmed > 0 && is_array($stats))
		{
			$stats['cap_trimmed'] = isset($stats['cap_trimmed']) ? ((int) $stats['cap_trimmed'] + $trimmed) : $trimmed;
		}

		return array_values($filtered);
	}

	private function attendance_merge_cli_row_sheet_hadir_cap($row)
	{
		if (!is_array($row))
		{
			return 0;
		}

		$candidate_keys = array('sheet_sudah_berapa_absen', 'sheet_total_hadir', 'sudah_berapa_absen', 'total_hadir');
		$max_value = 0;
		for ($i = 0; $i < count($candidate_keys); $i += 1)
		{
			$key = $candidate_keys[$i];
			$raw = isset($row[$key]) ? trim((string) $row[$key]) : '';
			if ($raw === '')
			{
				continue;
			}
			$digits = preg_replace('/[^0-9]/', '', $raw);
			if ($digits === '')
			{
				continue;
			}
			$value = (int) $digits;
			if ($value > $max_value)
			{
				$max_value = $value;
			}
		}

		return $max_value > 0 ? $max_value : 0;
	}

	private function attendance_merge_cli_rows_stats($rows)
	{
		$records = is_array($rows) ? array_values($rows) : array();
		$user_lookup = array();
		$date_min = '';
		$date_max = '';
		for ($i = 0; $i < count($records); $i += 1)
		{
			$row = isset($records[$i]) && is_array($records[$i]) ? $records[$i] : array();
			$username = strtolower(trim((string) (isset($row['username']) ? $row['username'] : '')));
			$date_key = trim((string) (isset($row['date']) ? $row['date'] : ''));
			if ($username !== '')
			{
				$user_lookup[$username] = TRUE;
			}
			if (!$this->is_valid_date_format($date_key))
			{
				continue;
			}
			if ($date_min === '' || strcmp($date_key, $date_min) < 0)
			{
				$date_min = $date_key;
			}
			if ($date_max === '' || strcmp($date_key, $date_max) > 0)
			{
				$date_max = $date_key;
			}
		}

		return array(
			'users' => count($user_lookup),
			'date_min' => $date_min !== '' ? $date_min : '-',
			'date_max' => $date_max !== '' ? $date_max : '-'
		);
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
		if (!$this->assert_expected_revision_or_redirect('home', 'account_notice_error'))
		{
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
		$current_record_version = isset($account_book[$username_key]['record_version'])
			? (int) $account_book[$username_key]['record_version']
			: 1;
		if ($current_record_version <= 0)
		{
			$current_record_version = 1;
		}
		$expected_record_version = (int) $this->input->post('expected_version', TRUE);
		if ($expected_record_version <= 0 || $expected_record_version !== $current_record_version)
		{
			$this->session->set_flashdata(
				'account_notice_error',
				'Konflik versi data akun '.$username_key.'. Data sudah diubah admin lain. Muat ulang dashboard lalu coba lagi.'
			);
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
		$removed_total = (int) $purge_summary['attendance'] + (int) $purge_summary['leave'] + (int) $purge_summary['loan'] + (int) $purge_summary['overtime'] + (int) $purge_summary['day_off_swap'] + (int) $purge_summary['day_off_swap_request'];
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
		$delete_note .= ', day_off_swap='.(int) $purge_summary['day_off_swap'];
		$delete_note .= ', day_off_swap_request='.(int) $purge_summary['day_off_swap_request'];
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
		if (!$this->assert_expected_revision_or_redirect('home#manajemen-karyawan', 'account_notice_error'))
		{
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
		$current_row = $account_book[$username_key];
		$current_record_version = isset($account_book[$username_key]['record_version'])
			? (int) $account_book[$username_key]['record_version']
			: 1;
		if ($current_record_version <= 0)
		{
			$current_record_version = 1;
		}
		$expected_record_version = (int) $this->input->post('expected_version', TRUE);
		if ($expected_record_version <= 0 || $expected_record_version !== $current_record_version)
		{
			$this->session->set_flashdata(
				'account_notice_error',
				'Konflik versi data akun '.$username_key.'. Data sudah diubah admin lain. Muat ulang dashboard lalu coba lagi.'
			);
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
		$custom_schedule_payload = $this->resolve_custom_schedule_payload_from_post('edit_');
		if (!isset($custom_schedule_payload['success']) || $custom_schedule_payload['success'] !== TRUE)
		{
			$message = isset($custom_schedule_payload['message']) && trim((string) $custom_schedule_payload['message']) !== ''
				? (string) $custom_schedule_payload['message']
				: 'Jadwal kustom akun karyawan tidak valid.';
			$this->session->set_flashdata('account_notice_error', $message);
			redirect('home#manajemen-karyawan');
			return;
		}
		$current_custom_schedule = $this->normalize_employee_custom_schedule(array(
			'custom_allowed_weekdays' => isset($current_row['custom_allowed_weekdays']) ? $current_row['custom_allowed_weekdays'] : array(),
			'custom_off_ranges' => isset($current_row['custom_off_ranges']) ? $current_row['custom_off_ranges'] : array(),
			'custom_work_ranges' => isset($current_row['custom_work_ranges']) ? $current_row['custom_work_ranges'] : array()
		));
		$custom_schedule_values = isset($custom_schedule_payload['provided']) && $custom_schedule_payload['provided'] === TRUE
			? $this->normalize_employee_custom_schedule($custom_schedule_payload)
			: $current_custom_schedule;

		$schedule_username_key = $new_username_key !== '' ? $new_username_key : $username_key;
		$month_policy = $this->calculate_employee_month_work_policy(
			$schedule_username_key,
			date('Y-m-d'),
			$weekly_day_off,
			$custom_schedule_values
		);
		$work_days = isset($month_policy['work_days']) ? (int) $month_policy['work_days'] : self::WORK_DAYS_DEFAULT;
		if ($work_days <= 0)
		{
			$work_days = self::WORK_DAYS_DEFAULT;
		}
		$salary_tier = $this->resolve_salary_tier_from_amount($salary_monthly);
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
			'custom_allowed_weekdays' => isset($custom_schedule_values['custom_allowed_weekdays']) ? $custom_schedule_values['custom_allowed_weekdays'] : array(),
			'custom_off_ranges' => isset($custom_schedule_values['custom_off_ranges']) ? $custom_schedule_values['custom_off_ranges'] : array(),
			'custom_work_ranges' => isset($custom_schedule_values['custom_work_ranges']) ? $custom_schedule_values['custom_work_ranges'] : array(),
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
			'record_version' => $current_record_version + 1,
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
					$account_book[$new_username_key]['sheet_sync_source'] = 'web';
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
			'custom_allowed_weekdays' => 'hari_kerja_khusus',
			'custom_off_ranges' => 'rentang_libur_khusus',
			'custom_work_ranges' => 'rentang_masuk_khusus',
			'job_title' => 'jabatan',
			'address' => 'alamat'
		);
		foreach ($track_keys as $key => $label)
		{
			$old_raw = isset($current_row[$key]) ? $current_row[$key] : '';
			$new_raw = isset($updated_account_row[$key]) ? $updated_account_row[$key] : '';
			if (is_array($old_raw) || is_array($new_raw))
			{
				$old_encoded = json_encode($old_raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				$new_encoded = json_encode($new_raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				$old_value = $old_encoded !== FALSE ? (string) $old_encoded : '';
				$new_value = $new_encoded !== FALSE ? (string) $new_encoded : '';
			}
			else
			{
				$old_value = trim((string) $old_raw);
				$new_value = trim((string) $new_raw);
			}
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

	public function submit_day_off_swap_request()
	{
		$is_ajax = $this->input->is_ajax_request()
			|| strtolower(trim((string) $this->input->get_request_header('X-Requested-With', TRUE))) === 'xmlhttprequest';

		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			if ($is_ajax)
			{
				$this->json_response(array('success' => FALSE, 'message' => 'Sesi login sudah habis.'), 401);
				return;
			}
			redirect('login');
			return;
		}

		if ((string) $this->session->userdata('absen_role') !== 'user')
		{
			if ($is_ajax)
			{
				$this->json_response(array('success' => FALSE, 'message' => 'Akses ditolak.'), 403);
				return;
			}
			redirect('home');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			if ($is_ajax)
			{
				$this->json_response(array('success' => FALSE, 'message' => 'Metode request tidak valid.'), 405);
				return;
			}
			redirect('home#swap-day-off-request');
			return;
		}

		$username_key = $this->current_actor_username();
		$workday_date = trim((string) $this->input->post('swap_workday_date', TRUE));
		$offday_date = trim((string) $this->input->post('swap_offday_date', TRUE));
		$swap_note = trim((string) $this->input->post('swap_note', TRUE));
		if ($swap_note !== '')
		{
			$swap_note = preg_replace('/\s+/', ' ', $swap_note);
			if ($swap_note === NULL)
			{
				$swap_note = '';
			}
			if (strlen($swap_note) > 200)
			{
				$swap_note = substr($swap_note, 0, 200);
			}
		}
		if ($swap_note === '')
		{
			if ($is_ajax)
			{
				$this->json_response(array('success' => FALSE, 'message' => 'Alasan/catatan pengajuan tukar hari libur wajib diisi.'), 422);
				return;
			}
			$this->session->set_flashdata('swap_request_notice_error', 'Alasan/catatan pengajuan tukar hari libur wajib diisi.');
			redirect('home#swap-day-off-request');
			return;
		}

		$validation = $this->validate_day_off_swap_candidate($username_key, $workday_date, $offday_date);
		if (!isset($validation['success']) || $validation['success'] !== TRUE)
		{
			if ($is_ajax)
			{
				$this->json_response(
					array(
						'success' => FALSE,
						'message' => isset($validation['message']) ? (string) $validation['message'] : 'Pengajuan tukar hari libur tidak valid.'
					),
					422
				);
				return;
			}
			$this->session->set_flashdata('swap_request_notice_error', isset($validation['message']) ? (string) $validation['message'] : 'Pengajuan tukar hari libur tidak valid.');
			redirect('home#swap-day-off-request');
			return;
		}

		$request_rows = $this->day_off_swap_request_book(TRUE);
		$request_id = $this->generate_day_off_swap_request_id($username_key, $workday_date, $offday_date);
		$request_rows[] = array(
			'request_id' => $request_id,
			'username' => $username_key,
			'branch' => isset($validation['branch']) ? (string) $validation['branch'] : $this->default_employee_branch(),
			'workday_date' => $workday_date,
			'offday_date' => $offday_date,
			'note' => $swap_note,
			'status' => 'pending',
			'requested_by' => $username_key,
			'requested_at' => date('Y-m-d H:i:s'),
			'reviewed_by' => '',
			'reviewed_at' => '',
			'review_note' => '',
			'swap_id' => ''
		);
		$saved_request = $this->save_day_off_swap_request_book($request_rows);
		if (!$saved_request)
		{
			if ($is_ajax)
			{
				$this->json_response(array('success' => FALSE, 'message' => 'Gagal menyimpan pengajuan tukar hari libur.'), 500);
				return;
			}
			$this->session->set_flashdata('swap_request_notice_error', 'Gagal menyimpan pengajuan tukar hari libur.');
			redirect('home#swap-day-off-request');
			return;
		}

		$display_name = isset($validation['display_name']) ? (string) $validation['display_name'] : $username_key;
		$activity_note = 'Karyawan mengajukan tukar hari libur 1x. Tanggal '.$workday_date.' jadi masuk, tanggal '.$offday_date.' jadi libur.';
		if ($swap_note !== '')
		{
			$activity_note .= ' Catatan: '.$swap_note;
		}
		$this->log_activity_event(
			'submit_day_off_swap_request',
			'web_data',
			$username_key,
			$display_name,
			$activity_note,
			array(
				'target_id' => $request_id,
				'field' => 'day_off_swap_request',
				'field_label' => 'pengajuan_tukar_hari_libur',
				'old_value' => '',
				'new_value' => $workday_date.' => '.$offday_date
			)
		);

		$latest_swap_request = count($request_rows) > 0
			? $request_rows[count($request_rows) - 1]
			: array();
		$admin_notify_result = $this->notify_admin_new_submission('day_off_swap', $latest_swap_request);
		$admin_notify_success = isset($admin_notify_result['success']) && $admin_notify_result['success'] === TRUE;
		$admin_notify_message = $admin_notify_success
			? 'Pengajuan tukar hari libur berhasil dikirim. Menunggu persetujuan admin. Notifikasi WA ke admin sudah terkirim.'
			: 'Pengajuan tukar hari libur berhasil dikirim. Menunggu persetujuan admin.';
		if (!$admin_notify_success)
		{
			$notify_reason = isset($admin_notify_result['message']) ? trim((string) $admin_notify_result['message']) : '';
			if ($notify_reason !== '')
			{
				$admin_notify_message .= ' Notifikasi WA ke admin gagal: '.$notify_reason;
			}
			log_message('error', 'Notifikasi WA pengajuan tukar hari libur gagal: '.($notify_reason !== '' ? $notify_reason : 'unknown error'));
		}

		if ($is_ajax)
		{
			$this->json_response(
				array(
					'success' => TRUE,
					'message' => $admin_notify_message
				)
			);
			return;
		}

		$this->session->set_flashdata('swap_request_notice_success', $admin_notify_message);
		redirect('home#swap-day-off-request');
	}

	public function update_day_off_swap_request_status()
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

		$redirect_target = $this->resolve_day_off_swap_request_redirect_target(
			$this->input->post('return_to', TRUE)
		);

		if (!$this->can_process_day_off_swap_requests_feature())
		{
			$this->set_day_off_swap_request_notice(
				$redirect_target,
				'error',
				'Akun login kamu belum punya izin untuk proses pengajuan tukar hari libur.'
			);
			redirect($redirect_target);
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect($redirect_target);
			return;
		}

		if (!$this->assert_expected_revision_or_redirect(
			$redirect_target,
			$this->day_off_swap_request_notice_flash_key($redirect_target, 'error')
		))
		{
			return;
		}

		$request_id = trim((string) $this->input->post('request_id', TRUE));
		$next_status = strtolower(trim((string) $this->input->post('status', TRUE)));
		$decision_note = trim((string) $this->input->post('review_note', TRUE));
		if ($decision_note !== '')
		{
			$decision_note = preg_replace('/\s+/', ' ', $decision_note);
			if ($decision_note === NULL)
			{
				$decision_note = '';
			}
			if (strlen($decision_note) > 200)
			{
				$decision_note = substr($decision_note, 0, 200);
			}
		}

		if ($request_id === '' || !in_array($next_status, array('approved', 'rejected'), TRUE))
		{
			$this->set_day_off_swap_request_notice(
				$redirect_target,
				'error',
				'Aksi status pengajuan tukar hari libur tidak valid.'
			);
			redirect($redirect_target);
			return;
		}

		$request_rows = $this->day_off_swap_request_book(TRUE);
		$target_index = -1;
		$request_row = array();
		for ($i = 0; $i < count($request_rows); $i += 1)
		{
			$row = isset($request_rows[$i]) && is_array($request_rows[$i]) ? $request_rows[$i] : array();
			$row_request_id = isset($row['request_id']) ? trim((string) $row['request_id']) : '';
			if ($row_request_id === $request_id)
			{
				$target_index = $i;
				$request_row = $row;
				break;
			}
		}

		if ($target_index < 0)
		{
			$this->set_day_off_swap_request_notice(
				$redirect_target,
				'error',
				'Data pengajuan tukar hari libur tidak ditemukan.'
			);
			redirect($redirect_target);
			return;
		}
		if (!$this->is_day_off_swap_request_in_actor_scope($request_row))
		{
			$this->set_day_off_swap_request_notice(
				$redirect_target,
				'error',
				'Akses pengajuan tukar hari libur ditolak karena beda cabang.'
			);
			redirect($redirect_target);
			return;
		}

		$current_status = strtolower(trim((string) (isset($request_row['status']) ? $request_row['status'] : 'pending')));
		if ($current_status !== 'pending')
		{
			$this->set_day_off_swap_request_notice(
				$redirect_target,
				'error',
				'Pengajuan tukar hari libur ini sudah diproses sebelumnya.'
			);
			redirect($redirect_target);
			return;
		}

		$username_key = $this->normalize_username_key(isset($request_row['username']) ? (string) $request_row['username'] : '');
		$workday_date = isset($request_row['workday_date']) ? trim((string) $request_row['workday_date']) : '';
		$offday_date = isset($request_row['offday_date']) ? trim((string) $request_row['offday_date']) : '';
		$request_note = isset($request_row['note']) ? trim((string) $request_row['note']) : '';
		$actor = $this->current_actor_username();
		$target_profile = $this->get_employee_profile($username_key);
		$target_display_name = isset($target_profile['display_name']) && trim((string) $target_profile['display_name']) !== ''
			? (string) $target_profile['display_name']
			: $username_key;

		if ($next_status === 'approved')
		{
			$validation = $this->validate_day_off_swap_candidate($username_key, $workday_date, $offday_date, '', $request_id);
			if (!isset($validation['success']) || $validation['success'] !== TRUE)
			{
				$this->set_day_off_swap_request_notice(
					$redirect_target,
					'error',
					isset($validation['message']) ? (string) $validation['message'] : 'Pengajuan tukar hari libur gagal disetujui.'
				);
				redirect($redirect_target);
				return;
			}

			$swap_note = $request_note;
			if ($decision_note !== '')
			{
				$swap_note = $swap_note !== ''
					? $swap_note.' | Catatan admin: '.$decision_note
					: 'Catatan admin: '.$decision_note;
			}
			$append_error = '';
			$append_result = $this->append_day_off_swap_entry($username_key, $workday_date, $offday_date, $swap_note, $actor, $append_error);
			if (!isset($append_result['success']) || $append_result['success'] !== TRUE)
			{
				$this->set_day_off_swap_request_notice(
					$redirect_target,
					'error',
					$append_error !== '' ? $append_error : 'Gagal mengaktifkan tukar hari libur dari pengajuan.'
				);
				redirect($redirect_target);
				return;
			}
			$request_row['swap_id'] = isset($append_result['swap_id']) ? (string) $append_result['swap_id'] : '';
		}

		$request_row['status'] = $next_status;
		$request_row['reviewed_by'] = $actor !== '' ? $actor : 'admin';
		$request_row['reviewed_at'] = date('Y-m-d H:i:s');
		$request_row['review_note'] = $decision_note;
		$request_rows[$target_index] = $request_row;
		$saved_request = $this->save_day_off_swap_request_book($request_rows);
		if (!$saved_request)
		{
			$this->set_day_off_swap_request_notice(
				$redirect_target,
				'error',
				'Gagal memperbarui status pengajuan tukar hari libur.'
			);
			redirect($redirect_target);
			return;
		}

		$action_key = $next_status === 'approved'
			? 'approve_day_off_swap_request'
			: 'reject_day_off_swap_request';
		$status_label = $this->day_off_swap_request_status_label($next_status);
		$activity_note = 'Status pengajuan tukar hari libur diubah menjadi '.$status_label.'.';
		$activity_note .= ' Tanggal '.$workday_date.' jadi masuk, tanggal '.$offday_date.' jadi libur.';
		if ($decision_note !== '')
		{
			$activity_note .= ' Catatan admin: '.$decision_note;
		}
		$this->log_activity_event(
			$action_key,
			'account_data',
			$username_key,
			$target_display_name,
			$activity_note,
			array(
				'target_id' => $request_id,
				'field' => 'day_off_swap_request',
				'field_label' => 'pengajuan_tukar_hari_libur',
				'old_value' => 'pending',
				'new_value' => $next_status
			)
		);

		$employee_phone = isset($target_profile['phone']) ? trim((string) $target_profile['phone']) : '';
		if ($employee_phone === '')
		{
			$employee_phone = $this->get_employee_phone($username_key);
		}

		$whatsapp_result = array(
			'success' => FALSE,
			'message' => 'Nomor WhatsApp karyawan belum tersedia.'
		);
		if ($employee_phone !== '')
		{
			$whatsapp_message = $this->build_day_off_swap_status_whatsapp_message($request_row);
			$whatsapp_result = $this->send_whatsapp_notification($employee_phone, $whatsapp_message);
		}

		$notice_message = 'Pengajuan tukar hari libur akun '.$username_key.' berhasil '.strtolower($status_label).'.';
		if (isset($whatsapp_result['success']) && $whatsapp_result['success'] === TRUE)
		{
			$notice_message .= ' Notifikasi WhatsApp ke karyawan sudah terkirim.';
		}
		else
		{
			$wa_reason = isset($whatsapp_result['message']) ? trim((string) $whatsapp_result['message']) : 'unknown error';
			$notice_message .= ' Namun notifikasi WhatsApp ke karyawan gagal dikirim.';
			if ($wa_reason !== '')
			{
				$notice_message .= ' '.$wa_reason;
			}
			log_message('error', 'Notifikasi WA status pengajuan tukar hari libur gagal: '.$wa_reason.' | request_id='.$request_id.' | username='.$username_key);
		}

		$this->set_day_off_swap_request_notice(
			$redirect_target,
			'success',
			$notice_message
		);
		redirect($redirect_target);
	}

	public function day_off_swap_requests()
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

		if (!$this->can_process_day_off_swap_requests_feature())
		{
			$this->session->set_flashdata('account_notice_error', 'Akun login kamu belum punya izin untuk membuka pengajuan tukar hari libur.');
			redirect('home');
			return;
		}

		$data = array(
			'title' => 'Pengajuan Tukar Hari Libur',
			'requests' => $this->build_day_off_swap_request_management_rows(''),
			'can_process_day_off_swap_requests' => $this->can_process_day_off_swap_requests_feature(),
			'can_delete_day_off_swap_requests' => $this->can_delete_day_off_swap_requests_feature(),
			'notice_success' => (string) $this->session->flashdata('day_off_swap_notice_success'),
			'notice_warning' => (string) $this->session->flashdata('day_off_swap_notice_warning'),
			'notice_error' => (string) $this->session->flashdata('day_off_swap_notice_error')
		);
		$view_path = APPPATH.'views/home/day_off_swap_requests.php';
		if (!is_file($view_path) || !is_readable($view_path))
		{
			log_message('error', 'View tidak bisa dibaca: '.$view_path);
			$this->session->set_flashdata('account_notice_error', 'Menu pengajuan tukar hari libur belum bisa dibuka karena file view belum terbaca server.');
			redirect('home');
			return;
		}
		$this->load->view('home/day_off_swap_requests', $data);
	}

	public function delete_day_off_swap_request()
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

		$redirect_target = $this->resolve_day_off_swap_request_redirect_target(
			$this->input->post('return_to', TRUE)
		);
		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect($redirect_target);
			return;
		}

		if (!$this->can_delete_day_off_swap_requests_feature())
		{
			$this->set_day_off_swap_request_notice(
				$redirect_target,
				'error',
				'Akun login kamu belum punya izin untuk hapus data pengajuan tukar hari libur.'
			);
			redirect($redirect_target);
			return;
		}

		$request_id = trim((string) $this->input->post('request_id', TRUE));
		if ($request_id === '')
		{
			$this->set_day_off_swap_request_notice(
				$redirect_target,
				'error',
				'Data pengajuan tukar hari libur tidak valid.'
			);
			redirect($redirect_target);
			return;
		}

		$request_rows = $this->day_off_swap_request_book(TRUE);
		$target_index = -1;
		$target_row = array();
		for ($i = 0; $i < count($request_rows); $i += 1)
		{
			$row = isset($request_rows[$i]) && is_array($request_rows[$i]) ? $request_rows[$i] : array();
			$row_request_id = isset($row['request_id']) ? trim((string) $row['request_id']) : '';
			if ($row_request_id !== $request_id)
			{
				continue;
			}

			if (!$this->is_day_off_swap_request_in_actor_scope($row))
			{
				$this->set_day_off_swap_request_notice(
					$redirect_target,
					'error',
					'Akses pengajuan tukar hari libur ditolak karena beda cabang.'
				);
				redirect($redirect_target);
				return;
			}

			$target_index = $i;
			$target_row = $row;
			break;
		}

		if ($target_index < 0)
		{
			$this->set_day_off_swap_request_notice(
				$redirect_target,
				'error',
				'Data pengajuan tukar hari libur tidak ditemukan atau sudah dihapus.'
			);
			redirect($redirect_target);
			return;
		}

		array_splice($request_rows, $target_index, 1);
		if (!$this->save_day_off_swap_request_book($request_rows))
		{
			$this->set_day_off_swap_request_notice(
				$redirect_target,
				'error',
				'Gagal menghapus data pengajuan tukar hari libur.'
			);
			redirect($redirect_target);
			return;
		}

		$username_key = $this->normalize_username_key(isset($target_row['username']) ? (string) $target_row['username'] : '');
		$target_profile = $this->get_employee_profile($username_key);
		$target_display_name = isset($target_profile['display_name']) && trim((string) $target_profile['display_name']) !== ''
			? (string) $target_profile['display_name']
			: $username_key;
		$this->log_activity_event(
			'delete_day_off_swap_request',
			'account_data',
			$username_key,
			$target_display_name,
			'Hapus data pengajuan tukar hari libur.',
			array(
				'target_id' => $request_id,
				'field' => 'day_off_swap_request',
				'field_label' => 'pengajuan_tukar_hari_libur',
				'old_value' => isset($target_row['status']) ? (string) $target_row['status'] : 'pending',
				'new_value' => ''
			)
		);

		$this->set_day_off_swap_request_notice(
			$redirect_target,
			'success',
			'Data pengajuan tukar hari libur akun '.$username_key.' berhasil dihapus.'
		);
		redirect($redirect_target);
	}

	private function resolve_day_off_swap_request_redirect_target($return_to = '')
	{
		$return_key = strtolower(trim((string) $return_to));
		if ($return_key === 'home/day_off_swap_requests')
		{
			return 'home/day_off_swap_requests';
		}

		return 'home#manajemen-karyawan';
	}

	private function day_off_swap_request_notice_flash_key($redirect_target = '', $type = 'error')
	{
		$type_key = strtolower(trim((string) $type)) === 'success' ? 'success' : 'error';
		$target = strtolower(trim((string) $redirect_target));
		if ($target === 'home/day_off_swap_requests')
		{
			return 'day_off_swap_notice_'.$type_key;
		}

		return 'account_notice_'.$type_key;
	}

	private function set_day_off_swap_request_notice($redirect_target = '', $type = 'error', $message = '')
	{
		$flash_key = $this->day_off_swap_request_notice_flash_key($redirect_target, $type);
		$this->session->set_flashdata($flash_key, (string) $message);
	}

	public function create_day_off_swap()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if (!$this->can_manage_employee_accounts())
		{
			$this->session->set_flashdata('account_notice_error', 'Akun login kamu belum punya izin untuk kelola tukar hari libur.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home#manajemen-karyawan');
			return;
		}

		if (!$this->assert_expected_revision_or_redirect('home#manajemen-karyawan', 'account_notice_error'))
		{
			return;
		}

		$username_key = $this->normalize_username_key($this->input->post('swap_username', TRUE));
		$workday_date = trim((string) $this->input->post('swap_workday_date', TRUE));
		$offday_date = trim((string) $this->input->post('swap_offday_date', TRUE));
		$swap_note = trim((string) $this->input->post('swap_note', TRUE));
		if ($swap_note !== '')
		{
			$swap_note = preg_replace('/\s+/', ' ', $swap_note);
			if ($swap_note === NULL)
			{
				$swap_note = '';
			}
			if (strlen($swap_note) > 200)
			{
				$swap_note = substr($swap_note, 0, 200);
			}
		}

		if ($username_key === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Pilih akun karyawan untuk tukar hari libur.');
			redirect('home#manajemen-karyawan');
			return;
		}
		if (!$this->is_username_in_actor_scope($username_key))
		{
			$this->session->set_flashdata('account_notice_error', 'Akses ditolak. Akun karyawan berada di luar cakupan admin kamu.');
			redirect('home#manajemen-karyawan');
			return;
		}
		if (!$this->is_valid_date_format($workday_date) || !$this->is_valid_date_format($offday_date))
		{
			$this->session->set_flashdata('account_notice_error', 'Format tanggal tukar libur tidak valid. Gunakan YYYY-MM-DD.');
			redirect('home#manajemen-karyawan');
			return;
		}
		if ($workday_date === $offday_date)
		{
			$this->session->set_flashdata('account_notice_error', 'Tanggal kerja pengganti dan tanggal libur pengganti tidak boleh sama.');
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
		$role = strtolower(trim((string) (isset($account_book[$username_key]['role']) ? $account_book[$username_key]['role'] : 'user')));
		if ($role !== 'user')
		{
			$this->session->set_flashdata('account_notice_error', 'Fitur tukar hari libur hanya untuk akun karyawan.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$profile_weekly_day_off = isset($account_book[$username_key]['weekly_day_off'])
			? $this->resolve_employee_weekly_day_off($account_book[$username_key]['weekly_day_off'])
			: $this->default_weekly_day_off();
		$is_workday_date_regular_workday = $this->is_employee_regular_workday_without_swap($username_key, $workday_date, $profile_weekly_day_off);
		if ($is_workday_date_regular_workday)
		{
			$this->session->set_flashdata(
				'account_notice_error',
				'Tanggal '.$this->format_user_dashboard_date_label($workday_date).' bukan hari libur normal akun '.$username_key.'.'
			);
			redirect('home#manajemen-karyawan');
			return;
		}

		$is_offday_date_regular_workday = $this->is_employee_regular_workday_without_swap($username_key, $offday_date, $profile_weekly_day_off);
		if (!$is_offday_date_regular_workday)
		{
			$this->session->set_flashdata(
				'account_notice_error',
				'Tanggal '.$this->format_user_dashboard_date_label($offday_date).' bukan hari kerja normal akun '.$username_key.'.'
			);
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($this->has_employee_attendance_record_on_date($username_key, $offday_date))
		{
			$this->session->set_flashdata(
				'account_notice_error',
				'Tanggal '.$this->format_user_dashboard_date_label($offday_date).' sudah memiliki data absensi. Hapus data absensi tanggal tersebut dulu sebelum dijadikan libur.'
			);
			redirect('home#manajemen-karyawan');
			return;
		}

		$swap_rows = $this->day_off_swap_book(TRUE);
		$conflict_date = '';
		$conflict_label = '';
		for ($i = 0; $i < count($swap_rows); $i += 1)
		{
			$row = isset($swap_rows[$i]) && is_array($swap_rows[$i]) ? $swap_rows[$i] : array();
			$row_username = isset($row['username']) ? strtolower(trim((string) $row['username'])) : '';
			if ($row_username !== $username_key)
			{
				continue;
			}

			$row_workday_date = isset($row['workday_date']) ? trim((string) $row['workday_date']) : '';
			$row_offday_date = isset($row['offday_date']) ? trim((string) $row['offday_date']) : '';
			if (
				$workday_date === $row_workday_date ||
				$workday_date === $row_offday_date ||
				$offday_date === $row_workday_date ||
				$offday_date === $row_offday_date
			)
			{
				$conflict_date = $workday_date === $row_workday_date || $workday_date === $row_offday_date
					? $workday_date
					: $offday_date;
				$conflict_label = $this->format_user_dashboard_date_label($conflict_date);
				break;
			}
		}
		if ($conflict_date !== '')
		{
			$this->session->set_flashdata(
				'account_notice_error',
				'Akun '.$username_key.' sudah punya tukar hari libur aktif yang memakai tanggal '.$conflict_label.'.'
			);
			redirect('home#manajemen-karyawan');
			return;
		}

		$swap_rows[] = array(
			'swap_id' => $this->generate_day_off_swap_id($username_key, $workday_date, $offday_date),
			'username' => $username_key,
			'workday_date' => $workday_date,
			'offday_date' => $offday_date,
			'note' => $swap_note,
			'created_by' => $this->current_actor_username(),
			'created_at' => date('Y-m-d H:i:s')
		);
		$saved_swap = $this->save_day_off_swap_book($swap_rows);
		if (!$saved_swap)
		{
			$this->session->set_flashdata('account_notice_error', 'Gagal menyimpan data tukar hari libur.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$target_profile = $this->get_employee_profile($username_key);
		$target_display_name = isset($target_profile['display_name']) && trim((string) $target_profile['display_name']) !== ''
			? (string) $target_profile['display_name']
			: $username_key;
		$activity_note = 'Set tukar hari libur 1x. Tanggal '.$workday_date.' jadi masuk, tanggal '.$offday_date.' jadi libur.';
		if ($swap_note !== '')
		{
			$activity_note .= ' Catatan: '.$swap_note;
		}
		$this->log_activity_event(
			'create_day_off_swap',
			'account_data',
			$username_key,
			$target_display_name,
			$activity_note,
			array(
				'field' => 'day_off_swap',
				'field_label' => 'tukar_hari_libur',
				'old_value' => '',
				'new_value' => $workday_date.' => '.$offday_date
			)
		);

		$this->session->set_flashdata(
			'account_notice_success',
			'Tukar hari libur akun '.$username_key.' berhasil disimpan: '.$this->format_user_dashboard_date_label($workday_date).' jadi masuk, '.$this->format_user_dashboard_date_label($offday_date).' jadi libur.'
		);
		redirect('home#manajemen-karyawan');
	}

	public function delete_day_off_swap()
	{
		if ($this->session->userdata('absen_logged_in') !== TRUE)
		{
			redirect('login');
			return;
		}

		if (!$this->can_manage_employee_accounts())
		{
			$this->session->set_flashdata('account_notice_error', 'Akun login kamu belum punya izin untuk menghapus tukar hari libur.');
			redirect('home#manajemen-karyawan');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home#manajemen-karyawan');
			return;
		}

		if (!$this->assert_expected_revision_or_redirect('home#manajemen-karyawan', 'account_notice_error'))
		{
			return;
		}

		$swap_id = trim((string) $this->input->post('swap_id', TRUE));
		if ($swap_id === '')
		{
			$this->session->set_flashdata('account_notice_error', 'Data tukar hari libur tidak valid.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$swap_rows = $this->day_off_swap_book(TRUE);
		$target_index = -1;
		$target_row = array();
		for ($i = 0; $i < count($swap_rows); $i += 1)
		{
			$row = isset($swap_rows[$i]) && is_array($swap_rows[$i]) ? $swap_rows[$i] : array();
			$row_swap_id = isset($row['swap_id']) ? trim((string) $row['swap_id']) : '';
			if ($row_swap_id !== $swap_id)
			{
				continue;
			}

			$row_username = isset($row['username']) ? strtolower(trim((string) $row['username'])) : '';
			if (!$this->is_username_in_actor_scope($row_username))
			{
				$this->session->set_flashdata('account_notice_error', 'Akses data tukar hari libur ditolak karena beda cabang.');
				redirect('home#manajemen-karyawan');
				return;
			}

			$target_index = $i;
			$target_row = $row;
			break;
		}

		if ($target_index < 0)
		{
			$this->session->set_flashdata('account_notice_error', 'Data tukar hari libur tidak ditemukan atau sudah dihapus.');
			redirect('home#manajemen-karyawan');
			return;
		}

		array_splice($swap_rows, $target_index, 1);
		$saved_swap = $this->save_day_off_swap_book($swap_rows);
		if (!$saved_swap)
		{
			$this->session->set_flashdata('account_notice_error', 'Gagal menghapus data tukar hari libur.');
			redirect('home#manajemen-karyawan');
			return;
		}

		$username_key = isset($target_row['username']) ? strtolower(trim((string) $target_row['username'])) : '';
		$target_profile = $this->get_employee_profile($username_key);
		$target_display_name = isset($target_profile['display_name']) && trim((string) $target_profile['display_name']) !== ''
			? (string) $target_profile['display_name']
			: $username_key;
		$workday_date = isset($target_row['workday_date']) ? trim((string) $target_row['workday_date']) : '';
		$offday_date = isset($target_row['offday_date']) ? trim((string) $target_row['offday_date']) : '';
		$activity_note = 'Batalkan tukar hari libur 1x. Tanggal '.$workday_date.' kembali libur normal, tanggal '.$offday_date.' kembali hari kerja normal.';
		$this->log_activity_event(
			'delete_day_off_swap',
			'account_data',
			$username_key,
			$target_display_name,
			$activity_note,
			array(
				'field' => 'day_off_swap',
				'field_label' => 'tukar_hari_libur',
				'old_value' => $workday_date.' => '.$offday_date,
				'new_value' => ''
			)
		);

		$this->session->set_flashdata(
			'account_notice_success',
			'Tukar hari libur akun '.$username_key.' berhasil dihapus.'
		);
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
		if (!$this->assert_expected_revision_or_redirect('home#manajemen-karyawan', 'account_notice_error'))
		{
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
		if (!$this->assert_expected_revision_or_redirect('home#manajemen-karyawan', 'account_notice_error'))
		{
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
		if (!$this->assert_expected_revision_or_redirect('home#manajemen-karyawan', 'account_notice_error'))
		{
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
		if (!$this->assert_expected_revision_or_redirect('home#manajemen-karyawan', 'account_notice_error'))
		{
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

	public function admin_change_feed()
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

		$since_id = (int) $this->input->get('since_id', TRUE);
		if ($since_id < 0)
		{
			$since_id = 0;
		}
		$limit = (int) $this->input->get('limit', TRUE);
		if ($limit <= 0)
		{
			$limit = 25;
		}
		if ($limit > 120)
		{
			$limit = 120;
		}
		$bootstrap = (int) $this->input->get('bootstrap', TRUE) === 1;

		$state = $this->collab_load_state();
		$events = $this->collab_state_feed_events($state, $since_id, $limit, $bootstrap);
		$lock_info = $this->collab_sync_lock_info_from_state($state);
		$actor = $this->current_actor_username();
		$pending_sync = $this->collab_actor_pending_sync_status($state, $actor);

		$this->json_response(array(
			'success' => TRUE,
			'revision' => isset($state['revision']) ? (int) $state['revision'] : 0,
			'events' => $events,
			'lock' => $lock_info,
			'actor' => $actor,
			'pending_sync' => $pending_sync,
			'server_time' => date('Y-m-d H:i:s')
		));
	}

	public function sync_lock_status()
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

		$state = $this->collab_load_state();
		$this->json_response(array(
			'success' => TRUE,
			'lock' => $this->collab_sync_lock_info_from_state($state),
			'actor' => $this->current_actor_username(),
			'revision' => isset($state['revision']) ? (int) $state['revision'] : 0,
			'server_time' => date('Y-m-d H:i:s')
		));
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
		$cross_branch_enabled = $this->resolve_cross_branch_for_user($username, $user_profile);
		$shift_name = (string) $this->session->userdata('absen_shift_name');
		$shift_time = (string) $this->session->userdata('absen_shift_time');
		$shift_key = $this->resolve_shift_key_from_shift_values($shift_name, $shift_time);
		$nearest_office = $this->nearest_attendance_office(
			(float) $latitude,
			(float) $longitude,
			$attendance_branch,
			$shift_key,
			$cross_branch_enabled
		);
		$attendance_branch_from_location = $attendance_branch;
		if ($cross_branch_enabled === 1)
		{
			$attendance_branch_from_location = $this->resolve_attendance_branch_from_nearest_office(
				$nearest_office,
				$attendance_branch
			);
		}
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
		$month_policy = $this->calculate_employee_month_work_policy($username, $date_key, $profile_weekly_day_off);
		$schedule_block_message = $this->attendance_schedule_block_message($username, $date_key, $profile_weekly_day_off);
		if ($schedule_block_message !== '')
		{
			$this->json_response(array(
				'success' => FALSE,
				'message' => $schedule_block_message
			), 422);
			return;
		}
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
			$check_in_start_label = isset($check_in_window['start_label']) ? (string) $check_in_window['start_label'] : '07:30';
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
				'branch' => $attendance_branch_from_location,
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
				'record_version' => 1,
				'updated_at' => date('Y-m-d H:i:s')
			);
			$record_index = count($records) - 1;
		}

		$record = $records[$record_index];
		$current_record_version = isset($record['record_version']) ? (int) $record['record_version'] : 1;
		if ($current_record_version <= 0)
		{
			$current_record_version = 1;
		}
		$record['shift_name'] = $shift_name;
		$record['shift_time'] = $shift_time;
		$record_branch = $this->resolve_employee_branch(isset($record['branch']) ? (string) $record['branch'] : '');
		if ($action === 'masuk' || $record_branch === '')
		{
			$record_branch = $attendance_branch_from_location;
		}
		$record['branch'] = $record_branch;
		$record['salary_tier'] = $salary_tier;
		$record['salary_monthly'] = number_format($salary_monthly, 0, '.', '');
		$record['work_days_per_month'] = $work_days;
		$record['days_in_month'] = $month_policy['days_in_month'];
		$record['weekly_off_days'] = $month_policy['weekly_off_days'];

		if ($action === 'masuk')
		{
			$record['check_in_time'] = $current_time;
			$record['check_in_late'] = $this->calculate_late_duration($current_time, $shift_time, $shift_name);
			$is_force_late_user = $this->should_force_late_attendance($username);
			if ($is_force_late_user && $this->duration_to_seconds($record['check_in_late']) <= 0)
			{
				$record['check_in_late'] = self::ATTENDANCE_FORCE_LATE_DURATION;
			}
			$record['check_in_photo'] = $this->normalize_attendance_photo_reference($photo, $username, $date_key, 'in');
			$record['check_in_lat'] = (string) $latitude;
			$record['check_in_lng'] = (string) $longitude;
			$record['check_in_accuracy_m'] = number_format($accuracy_m, 2, '.', '');
			$record['check_in_distance_m'] = number_format($distance_m, 2, '.', '');
			$record['jenis_masuk'] = 'Absen Masuk';

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
			$record['check_out_photo'] = $this->normalize_attendance_photo_reference($photo, $username, $date_key, 'out');
			$record['check_out_lat'] = (string) $latitude;
			$record['check_out_lng'] = (string) $longitude;
			$record['check_out_accuracy_m'] = number_format($accuracy_m, 2, '.', '');
			$record['check_out_distance_m'] = number_format($distance_m, 2, '.', '');
			$record['jenis_pulang'] = 'Absen Pulang';
			$record['work_duration'] = $this->calculate_work_duration($record['check_in_time'], $current_time);
			$message = 'Absen pulang berhasil disimpan.';
		}

		$record['updated_at'] = date('Y-m-d H:i:s');
		$record['record_version'] = $current_record_version + 1;
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
		$employee_profiles = $this->employee_profile_book(TRUE);
		$scope_lookup = $this->scoped_employee_lookup(TRUE);
		$scope_lookup_normalized = array();
		$scope_lookup_compact = array();
		foreach ($scope_lookup as $scope_username_key => $scope_allowed)
		{
			$scope_username_normalized = $this->normalize_username_key($scope_username_key);
			if ($scope_username_normalized !== '')
			{
				$scope_lookup_normalized[$scope_username_normalized] = TRUE;
				$scope_username_compact = str_replace('_', '', $scope_username_normalized);
				if ($scope_username_compact !== '')
				{
					$scope_lookup_compact[$scope_username_compact] = TRUE;
				}
			}
		}
		$employee_id_book = $this->employee_id_book(TRUE);
		$employee_profiles_by_normalized = array();
		$employee_profiles_by_display_name_normalized = array();
		$employee_id_by_normalized = array();
		$employee_id_by_compact = array();
		$profile_username_by_employee_id = array();
		foreach ($employee_profiles as $profile_username_key => $profile_row)
		{
			if (!is_array($profile_row))
			{
				continue;
			}
			$profile_username_normalized = $this->normalize_username_key($profile_username_key);
			if ($profile_username_normalized !== '')
			{
				if (!isset($employee_profiles_by_normalized[$profile_username_normalized]))
				{
					$employee_profiles_by_normalized[$profile_username_normalized] = $profile_row;
				}
				$resolved_profile_id = $this->resolve_employee_id_from_book($profile_username_key, $employee_id_book);
				if ($resolved_profile_id !== '-' && !isset($employee_id_by_normalized[$profile_username_normalized]))
				{
					$employee_id_by_normalized[$profile_username_normalized] = $resolved_profile_id;
					$profile_username_compact = str_replace('_', '', $profile_username_normalized);
					if ($profile_username_compact !== '' && !isset($employee_id_by_compact[$profile_username_compact]))
					{
						$employee_id_by_compact[$profile_username_compact] = $resolved_profile_id;
					}
				}
				if ($resolved_profile_id !== '-')
				{
					$resolved_profile_id_key = trim((string) $resolved_profile_id);
					if ($resolved_profile_id_key !== '' && !isset($profile_username_by_employee_id[$resolved_profile_id_key]))
					{
						$profile_username_by_employee_id[$resolved_profile_id_key] = strtolower(trim((string) $profile_username_key));
					}
				}
			}

			$profile_display_name_normalized = $this->normalize_username_key(
				isset($profile_row['display_name']) ? (string) $profile_row['display_name'] : ''
			);
			if ($profile_display_name_normalized !== '')
			{
				if (!isset($employee_profiles_by_display_name_normalized[$profile_display_name_normalized]))
				{
					$employee_profiles_by_display_name_normalized[$profile_display_name_normalized] = $profile_row;
				}
				if ($profile_username_normalized !== '' && isset($employee_id_by_normalized[$profile_username_normalized]))
				{
					$employee_id_by_normalized[$profile_display_name_normalized] = $employee_id_by_normalized[$profile_username_normalized];
					$profile_display_compact = str_replace('_', '', $profile_display_name_normalized);
					if ($profile_display_compact !== '')
					{
						$employee_id_by_compact[$profile_display_compact] = $employee_id_by_normalized[$profile_username_normalized];
					}
				}
			}
		}
		$employee_alias_lookup = array();
		$employee_alias_lookup_compact = array();
		$account_book = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		$account_book_by_username = array();
		$account_lookup_username = array();
		if (is_array($account_book))
		{
			foreach ($account_book as $account_username_key => $account_row)
			{
				$account_username_normalized = strtolower(trim((string) $account_username_key));
				if ($account_username_normalized === '' || !is_array($account_row))
				{
					continue;
				}
				$account_book_by_username[$account_username_normalized] = $account_row;
				$lookup_candidates = array(
					$account_username_normalized
				);

				$account_username_key_normalized = $this->normalize_username_key($account_username_normalized);
				if ($account_username_key_normalized !== '')
				{
					$lookup_candidates[] = $account_username_key_normalized;
					$lookup_candidates[] = str_replace('_', '', $account_username_key_normalized);
				}

				$account_login_alias = isset($account_row['login_alias']) ? (string) $account_row['login_alias'] : '';
				$account_login_alias_lower = strtolower(trim($account_login_alias));
				if ($account_login_alias_lower !== '')
				{
					$lookup_candidates[] = $account_login_alias_lower;
				}
				$account_login_alias_normalized = $this->normalize_username_key($account_login_alias);
				if ($account_login_alias_normalized !== '')
				{
					$lookup_candidates[] = $account_login_alias_normalized;
					$lookup_candidates[] = str_replace('_', '', $account_login_alias_normalized);
				}

				$account_display_name = isset($account_row['display_name']) ? (string) $account_row['display_name'] : '';
				$account_display_name_lower = strtolower(trim($account_display_name));
				if ($account_display_name_lower !== '')
				{
					$lookup_candidates[] = $account_display_name_lower;
				}
				$account_display_name_normalized = $this->normalize_username_key($account_display_name);
				if ($account_display_name_normalized !== '')
				{
					$lookup_candidates[] = $account_display_name_normalized;
					$lookup_candidates[] = str_replace('_', '', $account_display_name_normalized);
				}

				$account_employee_id = $this->resolve_employee_id_from_book($account_username_normalized, $employee_id_book);
				if ($account_employee_id !== '-')
				{
					$lookup_candidates[] = trim((string) $account_employee_id);
				}
				for ($lookup_i = 0; $lookup_i < count($lookup_candidates); $lookup_i += 1)
				{
					$lookup_key = trim((string) $lookup_candidates[$lookup_i]);
					if ($lookup_key === '' || isset($account_lookup_username[$lookup_key]))
					{
						continue;
					}
					$account_lookup_username[$lookup_key] = $account_username_normalized;
				}
			}
		}
		foreach ($employee_profiles as $profile_username_key => $profile_row)
		{
			if (!is_array($profile_row))
			{
				continue;
			}
			$canonical_username = strtolower(trim((string) $profile_username_key));
			if ($canonical_username === '')
			{
				continue;
			}
			$employee_alias_lookup[$canonical_username] = $canonical_username;
			$canonical_normalized = $this->normalize_username_key($canonical_username);
			if ($canonical_normalized !== '' && !isset($employee_alias_lookup[$canonical_normalized]))
			{
				$employee_alias_lookup[$canonical_normalized] = $canonical_username;
			}
			$canonical_compact = str_replace('_', '', $canonical_normalized);
			if ($canonical_compact !== '' && !isset($employee_alias_lookup_compact[$canonical_compact]))
			{
				$employee_alias_lookup_compact[$canonical_compact] = $canonical_username;
			}

			$display_name = isset($profile_row['display_name']) ? (string) $profile_row['display_name'] : '';
			$display_name_key = strtolower(trim($display_name));
			if ($display_name_key !== '' && !isset($employee_alias_lookup[$display_name_key]))
			{
				$employee_alias_lookup[$display_name_key] = $canonical_username;
			}
			$display_name_normalized = $this->normalize_username_key($display_name);
			if ($display_name_normalized !== '' && !isset($employee_alias_lookup[$display_name_normalized]))
			{
				$employee_alias_lookup[$display_name_normalized] = $canonical_username;
			}
			$display_name_compact = str_replace('_', '', $display_name_normalized);
			if ($display_name_compact !== '' && !isset($employee_alias_lookup_compact[$display_name_compact]))
			{
				$employee_alias_lookup_compact[$display_name_compact] = $canonical_username;
			}

			$profile_employee_id = $this->resolve_employee_id_from_book($canonical_username, $employee_id_book);
			if ($profile_employee_id !== '-' && $profile_employee_id !== '' && !isset($employee_alias_lookup[$profile_employee_id]))
			{
				$employee_alias_lookup[$profile_employee_id] = $canonical_username;
			}

			$account_row = isset($account_book_by_username[$canonical_username]) && is_array($account_book_by_username[$canonical_username])
				? $account_book_by_username[$canonical_username]
				: array();
			$login_alias_raw = isset($account_row['login_alias']) ? (string) $account_row['login_alias'] : '';
			$login_alias_key = strtolower(trim($login_alias_raw));
			if ($login_alias_key !== '' && !isset($employee_alias_lookup[$login_alias_key]))
			{
				$employee_alias_lookup[$login_alias_key] = $canonical_username;
			}
			$login_alias_normalized = $this->normalize_username_key($login_alias_raw);
			if ($login_alias_normalized !== '' && !isset($employee_alias_lookup[$login_alias_normalized]))
			{
				$employee_alias_lookup[$login_alias_normalized] = $canonical_username;
			}
			$login_alias_compact = str_replace('_', '', $login_alias_normalized);
			if ($login_alias_compact !== '' && !isset($employee_alias_lookup_compact[$login_alias_compact]))
			{
				$employee_alias_lookup_compact[$login_alias_compact] = $canonical_username;
			}
		}
		$historical_phone_by_username = array();
		$historical_phone_by_normalized_username = array();
		$historical_phone_by_compact_username = array();
		$historical_employee_id_by_username = array();
		$historical_employee_id_by_normalized_username = array();
		$historical_employee_id_by_compact_username = array();
		$historical_profile_photo_by_username = array();
		$historical_profile_photo_by_normalized_username = array();
		$historical_profile_photo_by_compact_username = array();
		for ($phone_i = 0; $phone_i < count($records); $phone_i += 1)
		{
			$phone_username_raw = isset($records[$phone_i]['username']) ? (string) $records[$phone_i]['username'] : '';
			$phone_username_key = strtolower(trim((string) $phone_username_raw));
			if ($phone_username_key === '')
			{
				continue;
			}
			$phone_candidate = $this->normalize_phone_number(isset($records[$phone_i]['phone']) ? (string) $records[$phone_i]['phone'] : '');
			if ($phone_candidate !== '')
			{
				$historical_phone_by_username[$phone_username_key] = $phone_candidate;
				$phone_username_normalized = $this->normalize_username_key($phone_username_raw);
				if ($phone_username_normalized !== '')
				{
					$historical_phone_by_normalized_username[$phone_username_normalized] = $phone_candidate;
					$phone_username_compact = str_replace('_', '', $phone_username_normalized);
					if ($phone_username_compact !== '')
					{
						$historical_phone_by_compact_username[$phone_username_compact] = $phone_candidate;
					}
				}
			}

			$historical_employee_id_value = isset($records[$phone_i]['employee_id']) ? trim((string) $records[$phone_i]['employee_id']) : '';
			if ($historical_employee_id_value !== '' && $historical_employee_id_value !== '-')
			{
				$historical_employee_id_by_username[$phone_username_key] = $historical_employee_id_value;
				$phone_username_normalized = $this->normalize_username_key($phone_username_raw);
				if ($phone_username_normalized !== '')
				{
					$historical_employee_id_by_normalized_username[$phone_username_normalized] = $historical_employee_id_value;
					$phone_username_compact = str_replace('_', '', $phone_username_normalized);
					if ($phone_username_compact !== '')
					{
						$historical_employee_id_by_compact_username[$phone_username_compact] = $historical_employee_id_value;
					}
				}
			}

			$historical_profile_photo_value = isset($records[$phone_i]['profile_photo']) ? trim((string) $records[$phone_i]['profile_photo']) : '';
			if ($historical_profile_photo_value !== '')
			{
				$historical_profile_photo_by_username[$phone_username_key] = $historical_profile_photo_value;
				$phone_username_normalized = $this->normalize_username_key($phone_username_raw);
				if ($phone_username_normalized !== '')
				{
					$historical_profile_photo_by_normalized_username[$phone_username_normalized] = $historical_profile_photo_value;
					$phone_username_compact = str_replace('_', '', $phone_username_normalized);
					if ($phone_username_compact !== '')
					{
						$historical_profile_photo_by_compact_username[$phone_username_compact] = $historical_profile_photo_value;
					}
				}
			}
		}
		$profile_phone_by_display_name = array();
		$profile_phone_by_display_name_compact = array();
		foreach ($employee_profiles as $profile_username_key => $profile_row)
		{
			if (!is_array($profile_row))
			{
				continue;
			}
			$profile_phone_value = $this->normalize_phone_number(isset($profile_row['phone']) ? (string) $profile_row['phone'] : '');
			if ($profile_phone_value === '')
			{
				continue;
			}
			$profile_display_name_key = strtolower(trim((string) (isset($profile_row['display_name']) ? $profile_row['display_name'] : '')));
			$profile_display_name_normalized = $this->normalize_username_key(
				isset($profile_row['display_name']) ? (string) $profile_row['display_name'] : ''
			);
			if ($profile_display_name_key !== '')
			{
				$profile_phone_by_display_name[$profile_display_name_key] = $profile_phone_value;
			}
			if ($profile_display_name_normalized !== '')
			{
				$profile_phone_by_display_name[$profile_display_name_normalized] = $profile_phone_value;
				$profile_display_name_compact = str_replace('_', '', $profile_display_name_normalized);
				if ($profile_display_name_compact !== '')
				{
					$profile_phone_by_display_name_compact[$profile_display_name_compact] = $profile_phone_value;
				}
			}
			$profile_username_key = strtolower(trim((string) $profile_username_key));
			if ($profile_username_key !== '')
			{
				$historical_phone_by_username[$profile_username_key] = $profile_phone_value;
			}
			$profile_username_normalized = $this->normalize_username_key($profile_username_key);
			if ($profile_username_normalized !== '')
			{
				$historical_phone_by_normalized_username[$profile_username_normalized] = $profile_phone_value;
				$profile_username_compact = str_replace('_', '', $profile_username_normalized);
				if ($profile_username_compact !== '')
				{
					$historical_phone_by_compact_username[$profile_username_compact] = $profile_phone_value;
				}
			}
		}
		$month_policy_cache = array();
		for ($i = 0; $i < count($records); $i += 1)
		{
			if ($this->is_attendance_sheet_snapshot_row($records[$i]))
			{
				unset($records[$i]);
				continue;
			}

			$row_username = isset($records[$i]['username']) ? (string) $records[$i]['username'] : '';
			$row_username_key_raw = strtolower(trim((string) $row_username));
				$row_username_normalized = $this->normalize_username_key($row_username);
				$row_username_compact = str_replace('_', '', $row_username_normalized);
				$row_employee_id_raw = isset($records[$i]['employee_id']) ? trim((string) $records[$i]['employee_id']) : '';
				$row_name_raw = '';
			if (isset($records[$i]['display_name']) && trim((string) $records[$i]['display_name']) !== '')
			{
				$row_name_raw = (string) $records[$i]['display_name'];
			}
			elseif (isset($records[$i]['name']) && trim((string) $records[$i]['name']) !== '')
			{
				$row_name_raw = (string) $records[$i]['name'];
			}
			$row_name_key = strtolower(trim((string) $row_name_raw));
			$row_name_normalized = $this->normalize_username_key($row_name_raw);
			$row_name_compact = str_replace('_', '', $row_name_normalized);
			$row_username_key = '';
				$candidate_keys = array(
					$row_username_key_raw,
					$row_username_normalized,
					$row_username_compact,
					$row_employee_id_raw,
					$row_name_key,
					$row_name_normalized,
					$row_name_compact
				);
			for ($candidate_i = 0; $candidate_i < count($candidate_keys); $candidate_i += 1)
			{
				$candidate_key = trim((string) $candidate_keys[$candidate_i]);
				if ($candidate_key === '')
				{
					continue;
				}
				if (isset($scope_lookup[$candidate_key]))
				{
					$row_username_key = $candidate_key;
					break;
				}
				if (isset($employee_alias_lookup[$candidate_key]))
				{
					$alias_key = strtolower(trim((string) $employee_alias_lookup[$candidate_key]));
					if ($alias_key !== '' && isset($scope_lookup[$alias_key]))
					{
						$row_username_key = $alias_key;
						break;
					}
				}
				if (isset($employee_alias_lookup_compact[$candidate_key]))
				{
					$alias_compact_key = strtolower(trim((string) $employee_alias_lookup_compact[$candidate_key]));
					if ($alias_compact_key !== '' && isset($scope_lookup[$alias_compact_key]))
					{
						$row_username_key = $alias_compact_key;
						break;
					}
				}
				if (isset($profile_username_by_employee_id[$candidate_key]))
				{
					$profile_employee_key = strtolower(trim((string) $profile_username_by_employee_id[$candidate_key]));
					if ($profile_employee_key !== '' && isset($scope_lookup[$profile_employee_key]))
					{
						$row_username_key = $profile_employee_key;
						break;
					}
				}
			}
			if ($row_username_key === '' && $row_username_normalized !== '' && isset($scope_lookup_normalized[$row_username_normalized]))
			{
				$row_username_key = $row_username_normalized;
			}
			if ($row_username_key === '' && $row_username_compact !== '' && isset($scope_lookup_compact[$row_username_compact]))
			{
				$row_username_key = $row_username_compact;
			}
			if ($row_username_key !== '' && !isset($scope_lookup[$row_username_key]) && isset($employee_alias_lookup[$row_username_key]))
			{
				$row_username_key = strtolower(trim((string) $employee_alias_lookup[$row_username_key]));
			}
			if ($row_username_key !== '' && !isset($scope_lookup[$row_username_key]) && isset($employee_alias_lookup_compact[$row_username_key]))
			{
				$row_username_key = strtolower(trim((string) $employee_alias_lookup_compact[$row_username_key]));
			}
			if ($row_username_key !== '' && !isset($scope_lookup[$row_username_key]))
			{
				$row_username_key = '';
			}
			if ($row_username_key === '')
			{
				unset($records[$i]);
				continue;
			}
			$records[$i]['username'] = $row_username_key;
			$row_username = $row_username_key;
			$row_username_normalized = $this->normalize_username_key($row_username_key);
			$row_username_compact = str_replace('_', '', $row_username_normalized);
			$check_in_raw = isset($records[$i]['check_in_time']) ? trim((string) $records[$i]['check_in_time']) : '';
			$check_out_raw = isset($records[$i]['check_out_time']) ? trim((string) $records[$i]['check_out_time']) : '';
			$has_check_in = $this->has_real_attendance_time($check_in_raw);
			$has_check_out = $this->has_real_attendance_time($check_out_raw);
			if (!$has_check_in && !$has_check_out)
			{
				unset($records[$i]);
				continue;
			}
			$row_employee_id = $this->resolve_employee_id_from_book($row_username, $employee_id_book);
			if ($row_employee_id === '-' && $row_username_normalized !== '' && isset($employee_id_by_normalized[$row_username_normalized]))
			{
				$row_employee_id = (string) $employee_id_by_normalized[$row_username_normalized];
			}
			if ($row_employee_id === '-' && $row_username_compact !== '' && isset($employee_id_by_compact[$row_username_compact]))
			{
				$row_employee_id = (string) $employee_id_by_compact[$row_username_compact];
			}
			$records[$i]['employee_id'] = $row_employee_id;
			if (isset($employee_profiles[$row_username_key]) && is_array($employee_profiles[$row_username_key]))
			{
				$row_profile = $employee_profiles[$row_username_key];
			}
			elseif ($row_username_normalized !== '' && isset($employee_profiles_by_normalized[$row_username_normalized]) && is_array($employee_profiles_by_normalized[$row_username_normalized]))
			{
				$row_profile = $employee_profiles_by_normalized[$row_username_normalized];
			}
			elseif ($row_username_normalized !== '' && isset($employee_profiles_by_display_name_normalized[$row_username_normalized]) && is_array($employee_profiles_by_display_name_normalized[$row_username_normalized]))
			{
				$row_profile = $employee_profiles_by_display_name_normalized[$row_username_normalized];
			}
			else
			{
				$row_profile = $this->get_employee_profile($row_username);
			}
			$default_profile_photo = $this->default_employee_profile_photo();
			$default_address = $this->default_employee_address();
			$default_job_title = $this->default_employee_job_title();

			$row_account_fallback_username = '';
			if (isset($account_book_by_username[$row_username_key]) && is_array($account_book_by_username[$row_username_key]))
			{
				$row_account_fallback_username = $row_username_key;
			}
			if ($row_account_fallback_username === '' && isset($employee_alias_lookup[$row_username_key]))
			{
				$row_alias_username = strtolower(trim((string) $employee_alias_lookup[$row_username_key]));
				if ($row_alias_username !== '' && isset($account_book_by_username[$row_alias_username]) && is_array($account_book_by_username[$row_alias_username]))
				{
					$row_account_fallback_username = $row_alias_username;
				}
			}
			if ($row_account_fallback_username === '' && $row_username_normalized !== '' && isset($employee_alias_lookup[$row_username_normalized]))
			{
				$row_alias_username = strtolower(trim((string) $employee_alias_lookup[$row_username_normalized]));
				if ($row_alias_username !== '' && isset($account_book_by_username[$row_alias_username]) && is_array($account_book_by_username[$row_alias_username]))
				{
					$row_account_fallback_username = $row_alias_username;
				}
			}
				if ($row_account_fallback_username === '' && $row_username_compact !== '' && isset($employee_alias_lookup_compact[$row_username_compact]))
				{
					$row_alias_username = strtolower(trim((string) $employee_alias_lookup_compact[$row_username_compact]));
					if ($row_alias_username !== '' && isset($account_book_by_username[$row_alias_username]) && is_array($account_book_by_username[$row_alias_username]))
					{
						$row_account_fallback_username = $row_alias_username;
					}
				}
				if ($row_account_fallback_username === '')
				{
					$row_account_lookup_candidates = array(
						$row_username_key,
						$row_username_key_raw,
						$row_username_normalized,
						$row_username_compact,
						$row_employee_id_raw,
						trim((string) $row_employee_id),
						$row_name_key,
						$row_name_normalized,
						$row_name_compact
				);
				for ($lookup_i = 0; $lookup_i < count($row_account_lookup_candidates); $lookup_i += 1)
				{
					$lookup_key = trim((string) $row_account_lookup_candidates[$lookup_i]);
					if ($lookup_key === '' || !isset($account_lookup_username[$lookup_key]))
					{
						continue;
					}
					$lookup_username = strtolower(trim((string) $account_lookup_username[$lookup_key]));
					if ($lookup_username === '' || !isset($account_book_by_username[$lookup_username]) || !is_array($account_book_by_username[$lookup_username]))
					{
						continue;
					}
					$row_account_fallback_username = $lookup_username;
					break;
				}
				if ($row_account_fallback_username === '')
				{
					$direct_account_candidates = array(
						$row_username_key_raw,
						$row_username_key,
						$row_username_normalized,
						$row_name_key
					);
					for ($direct_i = 0; $direct_i < count($direct_account_candidates); $direct_i += 1)
					{
						$direct_key = strtolower(trim((string) $direct_account_candidates[$direct_i]));
						if ($direct_key === '')
						{
							continue;
						}
						if (!isset($account_book_by_username[$direct_key]) || !is_array($account_book_by_username[$direct_key]))
						{
							continue;
						}
						$row_account_fallback_username = $direct_key;
						break;
					}
				}
			}

			$row_account_fallback = array();
			if ($row_account_fallback_username !== '' && isset($account_book_by_username[$row_account_fallback_username]) && is_array($account_book_by_username[$row_account_fallback_username]))
			{
				$row_account_data = $account_book_by_username[$row_account_fallback_username];
				$row_account_fallback = array(
					'display_name' => isset($row_account_data['display_name']) && trim((string) $row_account_data['display_name']) !== ''
						? trim((string) $row_account_data['display_name'])
						: $row_account_fallback_username,
					'branch' => $this->resolve_employee_branch(isset($row_account_data['branch']) ? (string) $row_account_data['branch'] : ''),
					'phone' => isset($row_account_data['phone']) ? (string) $row_account_data['phone'] : '',
					'shift_name' => isset($row_account_data['shift_name']) ? (string) $row_account_data['shift_name'] : 'Shift Pagi - Sore',
					'shift_time' => isset($row_account_data['shift_time']) ? (string) $row_account_data['shift_time'] : '08:00 - 17:00',
					'job_title' => $this->resolve_employee_job_title(isset($row_account_data['job_title']) ? (string) $row_account_data['job_title'] : ''),
					'salary_tier' => isset($row_account_data['salary_tier']) ? (string) $row_account_data['salary_tier'] : 'A',
					'salary_monthly' => isset($row_account_data['salary_monthly']) ? (int) $row_account_data['salary_monthly'] : 0,
					'work_days' => isset($row_account_data['work_days']) ? (int) $row_account_data['work_days'] : self::WORK_DAYS_DEFAULT,
					'weekly_day_off' => $this->resolve_employee_weekly_day_off(isset($row_account_data['weekly_day_off']) ? $row_account_data['weekly_day_off'] : NULL),
					'profile_photo' => isset($row_account_data['profile_photo']) ? (string) $row_account_data['profile_photo'] : $default_profile_photo,
					'coordinate_point' => isset($row_account_data['coordinate_point']) ? trim((string) $row_account_data['coordinate_point']) : '',
					'address' => isset($row_account_data['address']) && trim((string) $row_account_data['address']) !== ''
						? (string) $row_account_data['address']
						: $default_address
				);
				if ($row_account_fallback['branch'] === '')
				{
					$row_account_fallback['branch'] = $this->default_employee_branch();
				}
				if ($row_account_fallback['job_title'] === '')
				{
					$row_account_fallback['job_title'] = $default_job_title;
				}
				if (trim((string) $row_account_fallback['profile_photo']) === '')
				{
					$row_account_fallback['profile_photo'] = $default_profile_photo;
				}
				if (trim((string) $row_account_fallback['address']) === '')
				{
					$row_account_fallback['address'] = $default_address;
				}
			}
			if ($row_account_fallback_username !== '' && $row_username_key !== $row_account_fallback_username)
			{
				// Pastikan record harian memakai key akun final saat fallback ditemukan
				// (mis. data dari sheet/alias), agar PP/no tlp/ID konsisten.
				$row_username_key = $row_account_fallback_username;
				$records[$i]['username'] = $row_username_key;
				$row_username = $row_username_key;
				$row_username_normalized = $this->normalize_username_key($row_username_key);
				$row_username_compact = str_replace('_', '', $row_username_normalized);
				$row_employee_id_from_username = $this->resolve_employee_id_from_book($row_username, $employee_id_book);
				if ($row_employee_id_from_username !== '-')
				{
					$row_employee_id = $row_employee_id_from_username;
					$records[$i]['employee_id'] = $row_employee_id;
				}
				if (isset($employee_profiles[$row_username_key]) && is_array($employee_profiles[$row_username_key]))
				{
					$row_profile = $employee_profiles[$row_username_key];
				}
			}
			if (!is_array($row_profile))
			{
				$row_profile = array();
			}
			if (!empty($row_account_fallback))
			{
				$row_account_profile_photo = trim((string) $row_account_fallback['profile_photo']);
				if ($row_account_profile_photo !== '')
				{
					$row_profile['profile_photo'] = $row_account_profile_photo;
				}
				$row_account_phone = $this->normalize_phone_number((string) $row_account_fallback['phone']);
				if ($row_account_phone !== '')
				{
					$row_profile['phone'] = $row_account_phone;
				}
				if (trim((string) $row_account_fallback['address']) !== '')
				{
					$row_profile['address'] = (string) $row_account_fallback['address'];
				}
				if (trim((string) $row_account_fallback['job_title']) !== '')
				{
					$row_profile['job_title'] = (string) $row_account_fallback['job_title'];
				}
				if (trim((string) $row_account_fallback['branch']) !== '')
				{
					$row_profile['branch'] = (string) $row_account_fallback['branch'];
				}
				if (trim((string) $row_account_fallback['display_name']) !== '')
				{
					$records[$i]['display_name'] = (string) $row_account_fallback['display_name'];
					if (!isset($records[$i]['name']) || trim((string) $records[$i]['name']) === '')
					{
						$records[$i]['name'] = (string) $row_account_fallback['display_name'];
					}
				}
			}
			if (($row_employee_id === '-' || trim((string) $row_employee_id) === '') && $row_employee_id_raw !== '' && $row_employee_id_raw !== '-')
			{
				$row_employee_id = $row_employee_id_raw;
				$records[$i]['employee_id'] = $row_employee_id;
			}
			if (($row_employee_id === '-' || trim((string) $row_employee_id) === '') && $row_account_fallback_username !== '')
			{
				$row_employee_id_from_fallback = $this->resolve_employee_id_from_book($row_account_fallback_username, $employee_id_book);
				if ($row_employee_id_from_fallback !== '-')
				{
					$row_employee_id = $row_employee_id_from_fallback;
					$records[$i]['employee_id'] = $row_employee_id;
				}
			}
			if (($row_employee_id === '-' || trim((string) $row_employee_id) === '') && isset($historical_employee_id_by_username[$row_username_key]))
			{
				$row_employee_id = (string) $historical_employee_id_by_username[$row_username_key];
				$records[$i]['employee_id'] = $row_employee_id;
			}
			if (($row_employee_id === '-' || trim((string) $row_employee_id) === '') && $row_username_normalized !== '' && isset($historical_employee_id_by_normalized_username[$row_username_normalized]))
			{
				$row_employee_id = (string) $historical_employee_id_by_normalized_username[$row_username_normalized];
				$records[$i]['employee_id'] = $row_employee_id;
			}
			if (($row_employee_id === '-' || trim((string) $row_employee_id) === '') && $row_username_compact !== '' && isset($historical_employee_id_by_compact_username[$row_username_compact]))
			{
				$row_employee_id = (string) $historical_employee_id_by_compact_username[$row_username_compact];
				$records[$i]['employee_id'] = $row_employee_id;
			}
			$row_profile_phone_preview = $this->normalize_phone_number(
				isset($row_profile['phone']) ? (string) $row_profile['phone'] : ''
			);
			$needs_profile_rescue = $row_employee_id === '-' ||
				$row_profile_phone_preview === '' ||
				!(
					isset($row_profile['profile_photo']) &&
					trim((string) $row_profile['profile_photo']) !== '' &&
					trim((string) $row_profile['profile_photo']) !== trim((string) $default_profile_photo)
				);
			if ($needs_profile_rescue)
			{
				$rescue_username = '';
				$rescue_candidates = array(
						$row_username_key_raw,
						$row_username_normalized,
						$row_username_compact,
						$row_employee_id_raw,
						$row_name_key,
						$row_name_normalized,
						$row_name_compact
					);
				for ($rescue_i = 0; $rescue_i < count($rescue_candidates); $rescue_i += 1)
				{
					$rescue_key = trim((string) $rescue_candidates[$rescue_i]);
					if ($rescue_key === '')
					{
						continue;
					}
					$candidate_username = '';
					if (isset($scope_lookup[$rescue_key]))
					{
						$candidate_username = $rescue_key;
					}
					elseif (isset($employee_alias_lookup[$rescue_key]))
					{
						$candidate_username = strtolower(trim((string) $employee_alias_lookup[$rescue_key]));
					}
					elseif (isset($employee_alias_lookup_compact[$rescue_key]))
					{
						$candidate_username = strtolower(trim((string) $employee_alias_lookup_compact[$rescue_key]));
					}
					elseif (isset($profile_username_by_employee_id[$rescue_key]))
					{
						$candidate_username = strtolower(trim((string) $profile_username_by_employee_id[$rescue_key]));
					}
					if ($candidate_username === '')
					{
						continue;
					}
					if (!isset($scope_lookup[$candidate_username]))
					{
						continue;
					}
					if (!isset($employee_profiles[$candidate_username]) || !is_array($employee_profiles[$candidate_username]))
					{
						continue;
					}
					$rescue_username = $candidate_username;
					break;
				}
				if ($rescue_username !== '' && $rescue_username !== $row_username_key)
				{
					$row_username_key = $rescue_username;
					$records[$i]['username'] = $row_username_key;
					$row_username = $row_username_key;
					$row_username_normalized = $this->normalize_username_key($row_username_key);
					$row_username_compact = str_replace('_', '', $row_username_normalized);
					$row_employee_id = $this->resolve_employee_id_from_book($row_username, $employee_id_book);
					if ($row_employee_id === '-' && $row_username_normalized !== '' && isset($employee_id_by_normalized[$row_username_normalized]))
					{
						$row_employee_id = (string) $employee_id_by_normalized[$row_username_normalized];
					}
					if ($row_employee_id === '-' && $row_username_compact !== '' && isset($employee_id_by_compact[$row_username_compact]))
					{
						$row_employee_id = (string) $employee_id_by_compact[$row_username_compact];
					}
					$records[$i]['employee_id'] = $row_employee_id;
					$row_profile = $employee_profiles[$row_username_key];
				}
			}
			$current_profile_photo = isset($row_profile['profile_photo']) ? trim((string) $row_profile['profile_photo']) : '';
			$needs_photo_fallback = $current_profile_photo === '' || $current_profile_photo === $default_profile_photo;
			if ($needs_photo_fallback && isset($historical_profile_photo_by_username[$row_username_key]))
			{
				$candidate_photo = trim((string) $historical_profile_photo_by_username[$row_username_key]);
				if ($candidate_photo !== '' && $candidate_photo !== $default_profile_photo)
				{
					$row_profile['profile_photo'] = $candidate_photo;
				}
			}
			$current_profile_photo = isset($row_profile['profile_photo']) ? trim((string) $row_profile['profile_photo']) : '';
			$needs_photo_fallback = $current_profile_photo === '' || $current_profile_photo === $default_profile_photo;
			if ($needs_photo_fallback && $row_username_normalized !== '' && isset($historical_profile_photo_by_normalized_username[$row_username_normalized]))
			{
				$candidate_photo = trim((string) $historical_profile_photo_by_normalized_username[$row_username_normalized]);
				if ($candidate_photo !== '' && $candidate_photo !== $default_profile_photo)
				{
					$row_profile['profile_photo'] = $candidate_photo;
				}
			}
			$current_profile_photo = isset($row_profile['profile_photo']) ? trim((string) $row_profile['profile_photo']) : '';
			$needs_photo_fallback = $current_profile_photo === '' || $current_profile_photo === $default_profile_photo;
			if ($needs_photo_fallback && $row_username_compact !== '' && isset($historical_profile_photo_by_compact_username[$row_username_compact]))
			{
				$candidate_photo = trim((string) $historical_profile_photo_by_compact_username[$row_username_compact]);
				if ($candidate_photo !== '' && $candidate_photo !== $default_profile_photo)
				{
					$row_profile['profile_photo'] = $candidate_photo;
				}
			}
			$records[$i]['profile_photo'] = isset($row_profile['profile_photo']) && trim((string) $row_profile['profile_photo']) !== ''
				? (string) $row_profile['profile_photo']
				: $this->default_employee_profile_photo();
			$records[$i]['address'] = isset($row_profile['address']) && trim((string) $row_profile['address']) !== ''
				? (string) $row_profile['address']
				: $this->default_employee_address();
			$records[$i]['job_title'] = isset($row_profile['job_title']) && trim((string) $row_profile['job_title']) !== ''
				? (string) $row_profile['job_title']
				: $this->default_employee_job_title();
			$row_phone_value = isset($row_profile['phone']) && trim((string) $row_profile['phone']) !== ''
				? (string) $row_profile['phone']
				: $this->get_employee_phone($row_username);
			$row_phone_value = $this->normalize_phone_number($row_phone_value);
			if ($row_phone_value === '')
			{
				$row_phone_value = $this->normalize_phone_number(isset($records[$i]['phone']) ? (string) $records[$i]['phone'] : '');
			}
			if ($row_phone_value === '' && isset($historical_phone_by_username[$row_username_key]))
			{
				$row_phone_value = (string) $historical_phone_by_username[$row_username_key];
			}
			if ($row_phone_value === '' && $row_username_normalized !== '' && isset($historical_phone_by_normalized_username[$row_username_normalized]))
			{
				$row_phone_value = (string) $historical_phone_by_normalized_username[$row_username_normalized];
			}
			if ($row_phone_value === '' && $row_username_compact !== '' && isset($historical_phone_by_compact_username[$row_username_compact]))
			{
				$row_phone_value = (string) $historical_phone_by_compact_username[$row_username_compact];
			}
			if ($row_phone_value === '' && isset($profile_phone_by_display_name[$row_username_key]))
			{
				$row_phone_value = (string) $profile_phone_by_display_name[$row_username_key];
			}
			if ($row_phone_value === '' && $row_username_normalized !== '' && isset($profile_phone_by_display_name[$row_username_normalized]))
			{
				$row_phone_value = (string) $profile_phone_by_display_name[$row_username_normalized];
			}
			if ($row_phone_value === '' && $row_username_compact !== '' && isset($profile_phone_by_display_name_compact[$row_username_compact]))
			{
				$row_phone_value = (string) $profile_phone_by_display_name_compact[$row_username_compact];
			}
			$records[$i]['phone'] = $row_phone_value;
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
			$month_policy_cache_key = $record_date !== ''
				? (substr($record_date, 0, 7).'|'.$row_weekly_day_off.'|'.$row_username_key)
				: ('current|'.$row_weekly_day_off.'|'.$row_username_key);
			if (!isset($month_policy_cache[$month_policy_cache_key]))
			{
				$month_policy_cache[$month_policy_cache_key] = $this->calculate_employee_month_work_policy($row_username_key, $record_date, $row_weekly_day_off);
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
				$shift_name = isset($records[$i]['shift_name']) ? trim((string) $records[$i]['shift_name']) : '';
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
					if ($late_duration === '' && ($shift_time !== '' || $shift_name !== ''))
					{
						$late_duration = $this->calculate_late_duration($check_in_time, $shift_time, $shift_name);
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
			$row_shift_name_for_distance = isset($records[$i]['shift_name']) ? trim((string) $records[$i]['shift_name']) : '';
			$row_shift_time_for_distance = isset($records[$i]['shift_time']) ? trim((string) $records[$i]['shift_time']) : '';
			$row_shift_key_for_distance = $this->resolve_shift_key_from_shift_values($row_shift_name_for_distance, $row_shift_time_for_distance);

			if ((!isset($records[$i]['check_in_distance_m']) || $records[$i]['check_in_distance_m'] === '') &&
				isset($records[$i]['check_in_lat']) && isset($records[$i]['check_in_lng']) &&
				is_numeric($records[$i]['check_in_lat']) && is_numeric($records[$i]['check_in_lng']))
			{
				$nearest_check_in_office = $this->nearest_attendance_office(
					(float) $records[$i]['check_in_lat'],
					(float) $records[$i]['check_in_lng'],
					$row_branch_for_distance,
					$row_shift_key_for_distance
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
					$row_branch_for_distance,
					$row_shift_key_for_distance
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
			'records' => $records,
			'can_edit_attendance_records' => $this->can_edit_attendance_records_feature(),
			'can_delete_attendance_records' => $this->can_delete_attendance_records_feature(),
			'can_partial_delete_attendance_records' => $this->can_partial_delete_attendance_records_feature(),
			'can_edit_attendance_datetime' => $this->can_edit_attendance_datetime()
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
		// Pastikan pembacaan profil terbaru setelah update akun (termasuk PP).
		$this->employee_profile_book(TRUE);
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
			$profile_month_policy = $this->calculate_employee_month_work_policy($username, $month_start, $profile_weekly_day_off);
			$resolved_work_days = isset($profile_month_policy['work_days']) ? (int) $profile_month_policy['work_days'] : $work_days_default;
			if ($resolved_work_days <= 0)
			{
				$resolved_work_days = $work_days_default;
			}
			$has_custom_schedule = $this->employee_has_custom_schedule($username);
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
					'work_days_plan' => !$has_custom_schedule && isset($row['work_days_per_month']) && (int) $row['work_days_per_month'] > 0
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
				if (!$has_custom_schedule && isset($row['work_days_per_month']) && (int) $row['work_days_per_month'] > 0)
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
				$shift_name = isset($row['shift_name']) ? trim((string) $row['shift_name']) : '';
				$stored_late = isset($row['check_in_late']) ? trim((string) $row['check_in_late']) : '';
				if ($stored_late !== '')
				{
					$late_seconds = $this->duration_to_seconds($stored_late);
				}
				elseif ($shift_time !== '' || $shift_name !== '')
				{
					$late_seconds = $this->duration_to_seconds($this->calculate_late_duration($check_in_time, $shift_time, $shift_name));
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
			$profile_month_policy = $this->calculate_employee_month_work_policy($username, $month_start, $profile_weekly_day_off);
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
			$month_policy = $this->calculate_employee_month_work_policy($username, $month_start, $weekly_day_off_n);
			$work_days_plan = isset($month_policy['work_days']) ? (int) $month_policy['work_days'] : $work_days_default;
			if ($work_days_plan <= 0)
			{
				$work_days_plan = $work_days_default;
			}
			$has_custom_schedule = $this->employee_has_custom_schedule($username);

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
				if ($sheet_hari_efektif > 0 && !$has_custom_schedule)
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
				$total_telat_gt_4_jam,
				$hari_efektif_bulan,
				max(0, $days_in_month - $hari_efektif_bulan)
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

		if ($this->is_monthly_attendance_export_requested())
		{
			$this->stream_monthly_attendance_export_excel($rows, $month_input, $month_label);
			return;
		}

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

	private function is_monthly_attendance_export_requested()
	{
		$export_mode = strtolower(trim((string) $this->input->get('export', TRUE)));
		return in_array($export_mode, array('excel', 'xls', 'xlsx', 'csv'), TRUE);
	}

	private function stream_monthly_attendance_export_excel($rows, $month_key, $month_label)
	{
		$rows = is_array($rows) ? $rows : array();
		$month_key_text = trim((string) $month_key);
		if (!preg_match('/^\d{4}-\d{2}$/', $month_key_text))
		{
			$month_key_text = date('Y-m');
		}
		$month_label_text = trim((string) $month_label);
		if ($month_label_text === '')
		{
			$month_label_text = $month_key_text;
		}

		while (ob_get_level() > 0)
		{
			if (@ob_end_clean() === FALSE)
			{
				break;
			}
		}

		$filename = 'data-absensi-bulanan-'.$month_key_text.'.csv';
		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header('Pragma: no-cache');
		header('Expires: 0');

		$output = fopen('php://output', 'w');
		if ($output === FALSE)
		{
			show_error('Gagal membuat file export.', 500);
			return;
		}

		fwrite($output, "\xEF\xBB\xBF");
		fputcsv($output, array('Data Absensi Bulanan', $month_label_text), ';');
		fputcsv($output, array(), ';');
		fputcsv($output, array(
			'No',
			'ID',
			'Nama',
			'Alamat',
			'Jabatan',
			'Telp',
			'Gaji Bulanan',
			'Hari Masuk',
			'Hari Efektif',
			'Hadir',
			'Cuti',
			'Alpha/Izin',
			'Telat 1-30 Menit',
			'Telat 31-60 Menit',
			'Telat 1-3 Jam',
			'Telat >4 Jam',
			'Potongan 1-30 Menit',
			'Potongan 31-60 Menit',
			'Potongan 1-3 Jam',
			'Potongan >4 Jam',
			'Potongan Alpha/Izin',
			'Total Potongan',
			'Gaji Bersih'
		), ';');

		$row_number = 1;
		for ($i = 0; $i < count($rows); $i += 1)
		{
			$row = is_array($rows[$i]) ? $rows[$i] : array();
			$salary_monthly = isset($row['salary_monthly']) ? (int) $row['salary_monthly'] : 0;
			$total_1_30_amount = isset($row['total_1_30_amount']) ? (int) $row['total_1_30_amount'] : 0;
			$total_31_60_amount = isset($row['total_31_60_amount']) ? (int) $row['total_31_60_amount'] : 0;
			$total_1_3_amount = isset($row['total_1_3_amount']) ? (int) $row['total_1_3_amount'] : 0;
			$total_gt_4_amount = isset($row['total_gt_4_amount']) ? (int) $row['total_gt_4_amount'] : 0;
			$total_alpha_izin_amount = isset($row['total_alpha_izin_amount']) ? (int) $row['total_alpha_izin_amount'] : 0;
			$total_potongan = isset($row['total_potongan']) ? (int) $row['total_potongan'] : 0;
			$gaji_bersih = isset($row['gaji_bersih']) ? (int) $row['gaji_bersih'] : 0;

			fputcsv($output, array(
				$row_number,
				isset($row['employee_id']) && trim((string) $row['employee_id']) !== '' ? (string) $row['employee_id'] : '-',
				isset($row['username']) ? (string) $row['username'] : '-',
				isset($row['address']) && trim((string) $row['address']) !== '' ? (string) $row['address'] : '-',
				isset($row['job_title']) && trim((string) $row['job_title']) !== '' ? (string) $row['job_title'] : '-',
				isset($row['phone']) && trim((string) $row['phone']) !== '' ? (string) $row['phone'] : '-',
				'Rp '.number_format($salary_monthly, 0, ',', '.'),
				isset($row['work_days_plan']) ? (string) ((int) $row['work_days_plan']) : '0',
				isset($row['hari_effective']) ? (string) ((int) $row['hari_effective']) : '0',
				isset($row['hadir_days']) ? (string) ((int) $row['hadir_days']) : '0',
				isset($row['cuti_days']) ? (string) ((int) $row['cuti_days']) : '0',
				isset($row['total_alpha_izin']) ? (string) ((int) $row['total_alpha_izin']) : '0',
				isset($row['total_telat_1_30']) ? (string) ((int) $row['total_telat_1_30']) : '0',
				isset($row['total_telat_31_60']) ? (string) ((int) $row['total_telat_31_60']) : '0',
				isset($row['total_telat_1_3_jam']) ? (string) ((int) $row['total_telat_1_3_jam']) : '0',
				isset($row['total_telat_gt_4_jam']) ? (string) ((int) $row['total_telat_gt_4_jam']) : '0',
				'Rp '.number_format($total_1_30_amount, 0, ',', '.'),
				'Rp '.number_format($total_31_60_amount, 0, ',', '.'),
				'Rp '.number_format($total_1_3_amount, 0, ',', '.'),
				'Rp '.number_format($total_gt_4_amount, 0, ',', '.'),
				'Rp '.number_format($total_alpha_izin_amount, 0, ',', '.'),
				'Rp '.number_format($total_potongan, 0, ',', '.'),
				'Rp '.number_format($gaji_bersih, 0, ',', '.')
			), ';');

			$row_number += 1;
		}

		fclose($output);
		exit;
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
		if (!$this->can_edit_attendance_records_feature())
		{
			$this->session->set_flashdata('attendance_notice_error', 'Akun login kamu belum punya izin untuk edit data absensi karyawan.');
			redirect('home/employee_data');
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
		$edit_date_raw = trim((string) $this->input->post('edit_date', TRUE));
		$edit_check_in_raw = trim((string) $this->input->post('edit_check_in_time', TRUE));
		$edit_check_out_raw = trim((string) $this->input->post('edit_check_out_time', TRUE));
		$can_edit_datetime = $this->can_edit_attendance_datetime();

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
		$current_record_version = isset($records[$record_index]['record_version'])
			? (int) $records[$record_index]['record_version']
			: 1;
		if ($current_record_version <= 0)
		{
			$current_record_version = 1;
		}
		$expected_record_version = (int) $this->input->post('expected_version', TRUE);
		if ($expected_record_version <= 0 || $expected_record_version !== $current_record_version)
		{
			$this->session->set_flashdata(
				'attendance_notice_error',
				'Konflik versi data absensi '.$username.' tanggal '.$date_key.'. Muat ulang tabel lalu coba lagi.'
			);
			redirect('home/employee_data');
			return;
		}

		$original_date_key = isset($records[$record_index]['date']) ? trim((string) $records[$record_index]['date']) : $date_key;
		$original_check_in = isset($records[$record_index]['check_in_time']) ? trim((string) $records[$record_index]['check_in_time']) : '';
		$original_check_out = isset($records[$record_index]['check_out_time']) ? trim((string) $records[$record_index]['check_out_time']) : '';
		$new_date_key = $original_date_key !== '' ? $original_date_key : $date_key;
		$new_check_in = $original_check_in;
		$new_check_out = $original_check_out;
		$date_time_changed = FALSE;

		if ($edit_date_raw !== '' || $edit_check_in_raw !== '' || $edit_check_out_raw !== '')
		{
			if (!$can_edit_datetime)
			{
				$this->session->set_flashdata(
					'attendance_notice_error',
					'Hanya akun bos/developer/adminbaros/admin_cadasari yang diizinkan mengubah tanggal atau jam absensi.'
				);
				redirect('home/employee_data');
				return;
			}

			if ($edit_date_raw !== '')
			{
				if (!$this->is_valid_date_format($edit_date_raw))
				{
					$this->session->set_flashdata('attendance_notice_error', 'Format tanggal absensi tidak valid. Gunakan YYYY-MM-DD.');
					redirect('home/employee_data');
					return;
				}
				$new_date_key = $edit_date_raw;
			}

			$normalize_clock_input = function ($raw_value, &$is_valid) {
				$text = trim((string) $raw_value);
				if ($text === '')
				{
					$is_valid = TRUE;
					return '';
				}
				if (preg_match('/^(\d{1,2})\:(\d{2})(?:\:(\d{2}))?$/', $text, $matches) !== 1)
				{
					$is_valid = FALSE;
					return '';
				}
				$hour = isset($matches[1]) ? (int) $matches[1] : 0;
				$minute = isset($matches[2]) ? (int) $matches[2] : 0;
				$second = isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : 0;
				if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59)
				{
					$is_valid = FALSE;
					return '';
				}
				$is_valid = TRUE;
				return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
			};

			$check_in_valid = TRUE;
			$check_out_valid = TRUE;
			$new_check_in = $normalize_clock_input($edit_check_in_raw, $check_in_valid);
			$new_check_out = $normalize_clock_input($edit_check_out_raw, $check_out_valid);
			if (!$check_in_valid || !$check_out_valid)
			{
				$this->session->set_flashdata('attendance_notice_error', 'Format jam absensi tidak valid. Gunakan HH:MM atau HH:MM:SS.');
				redirect('home/employee_data');
				return;
			}

			if ($new_check_out !== '' && $new_check_in === '')
			{
				$this->session->set_flashdata('attendance_notice_error', 'Jam pulang tidak boleh diisi jika jam masuk masih kosong.');
				redirect('home/employee_data');
				return;
			}
			if ($new_check_in !== '' && $new_check_out !== '')
			{
				$check_in_seconds = $this->time_to_seconds($new_check_in);
				$check_out_seconds = $this->time_to_seconds($new_check_out);
				if ($check_out_seconds < $check_in_seconds)
				{
					$this->session->set_flashdata('attendance_notice_error', 'Jam pulang tidak boleh lebih awal dari jam masuk.');
					redirect('home/employee_data');
					return;
				}
			}

			if ($new_date_key !== $original_date_key)
			{
				for ($duplicate_i = 0; $duplicate_i < count($records); $duplicate_i += 1)
				{
					if ($duplicate_i === $record_index || !isset($records[$duplicate_i]) || !is_array($records[$duplicate_i]))
					{
						continue;
					}
					$duplicate_username = isset($records[$duplicate_i]['username']) ? (string) $records[$duplicate_i]['username'] : '';
					$duplicate_date = isset($records[$duplicate_i]['date']) ? trim((string) $records[$duplicate_i]['date']) : '';
					if ($duplicate_username === $username && $duplicate_date === $new_date_key)
					{
						$this->session->set_flashdata(
							'attendance_notice_error',
							'Tanggal absensi '.$new_date_key.' untuk '.$username.' sudah ada. Ubah tanggal lain atau hapus data duplikat dulu.'
						);
						redirect('home/employee_data');
						return;
					}
				}
			}

			$date_time_changed =
				($new_date_key !== $original_date_key) ||
				($new_check_in !== $original_check_in) ||
				($new_check_out !== $original_check_out);
		}

		$records[$record_index]['salary_cut_amount'] = number_format(max(0, $salary_cut_amount), 0, '.', '');
		$records[$record_index]['salary_cut_rule'] = $salary_cut_amount > 0
			? 'Disesuaikan admin'
			: 'Disesuaikan admin (potongan dihapus)';
		if ($date_time_changed)
		{
			$records[$record_index]['date'] = $new_date_key;
			$records[$record_index]['date_label'] = date('d-m-Y', strtotime($new_date_key));
			$records[$record_index]['sheet_month'] = substr($new_date_key, 0, 7);
			$records[$record_index]['sheet_tanggal_absen'] = $new_date_key;
			$records[$record_index]['check_in_time'] = $new_check_in;
			$records[$record_index]['check_out_time'] = $new_check_out;
			$records[$record_index]['work_duration'] = $this->calculate_work_duration($new_check_in, $new_check_out);
			$shift_time = isset($records[$record_index]['shift_time']) ? trim((string) $records[$record_index]['shift_time']) : '';
			$shift_name = isset($records[$record_index]['shift_name']) ? trim((string) $records[$record_index]['shift_name']) : '';
			$records[$record_index]['check_in_late'] = $new_check_in !== '' && ($shift_time !== '' || $shift_name !== '')
				? $this->calculate_late_duration($new_check_in, $shift_time, $shift_name)
				: '00:00:00';
			if ($records[$record_index]['check_in_late'] === '00:00:00')
			{
				$records[$record_index]['late_reason'] = '';
			}
		}
		$records[$record_index]['salary_cut_adjusted_by'] = (string) $this->session->userdata('absen_username');
		$records[$record_index]['salary_cut_adjusted_at'] = date('Y-m-d H:i:s');
		$records[$record_index]['record_version'] = $current_record_version + 1;
		$records[$record_index]['updated_at'] = date('Y-m-d H:i:s');

		$this->save_attendance_records($records);
		$this->clear_admin_dashboard_live_summary_cache();
		$deduction_note = 'Ubah data absensi pada data web.';
		$deduction_note .= ' Tanggal '.$date_key.'.';
		if ($date_time_changed)
		{
			$deduction_note .= ' Update tanggal/jam: '.$original_date_key.' '.$original_check_in.'-'.$original_check_out;
			$deduction_note .= ' -> '.$new_date_key.' '.$new_check_in.'-'.$new_check_out.'.';
		}
		$this->log_activity_event(
			'update_attendance_deduction',
			'web_data',
			$username,
			$username,
			$deduction_note,
			array(
				'field' => 'salary_cut_amount',
				'new_value' => (string) $salary_cut_amount,
				'target_id' => strtolower($username).'|'.$new_date_key
			)
		);

		if ($salary_cut_amount > 0 && $date_time_changed)
		{
			$this->session->set_flashdata(
				'attendance_notice_success',
				'Data absensi '.$username.' berhasil diperbarui (tanggal/jam + potongan Rp '.number_format($salary_cut_amount, 0, ',', '.').').'
			);
		}
		elseif ($salary_cut_amount > 0)
		{
			$this->session->set_flashdata(
				'attendance_notice_success',
				'Potongan gaji untuk '.$username.' berhasil diperbarui menjadi Rp '.number_format($salary_cut_amount, 0, ',', '.').'.'
			);
		}
		elseif ($date_time_changed)
		{
			$this->session->set_flashdata(
				'attendance_notice_success',
				'Data absensi '.$username.' berhasil diperbarui (tanggal/jam).'
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
		if (!$this->can_delete_attendance_records_feature())
		{
			$this->session->set_flashdata('attendance_notice_error', 'Akun login kamu belum punya izin untuk hapus data absensi karyawan.');
			redirect('home/employee_data');
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
		$current_record_version = isset($records[$record_index]['record_version'])
			? (int) $records[$record_index]['record_version']
			: 1;
		if ($current_record_version <= 0)
		{
			$current_record_version = 1;
		}
		$expected_record_version = (int) $this->input->post('expected_version', TRUE);
		if ($expected_record_version <= 0 || $expected_record_version !== $current_record_version)
		{
			$this->session->set_flashdata(
				'attendance_notice_error',
				'Konflik versi data absensi '.$username.' tanggal '.$date_key.'. Muat ulang tabel lalu coba lagi.'
			);
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

	public function delete_attendance_record_partial()
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
		if (!$this->can_partial_delete_attendance_records_feature())
		{
			$this->session->set_flashdata('attendance_notice_error', 'Hanya akun bos/developer yang bisa hapus absensi masuk/pulang secara terpisah.');
			redirect('home/employee_data');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST')
		{
			redirect('home/employee_data');
			return;
		}

		$username = trim((string) $this->input->post('username', TRUE));
		$date_key = trim((string) $this->input->post('date', TRUE));
		$delete_part = strtolower(trim((string) $this->input->post('delete_part', TRUE)));
		if ($delete_part !== 'masuk' && $delete_part !== 'pulang')
		{
			$this->session->set_flashdata('attendance_notice_error', 'Aksi hapus absensi parsial tidak valid.');
			redirect('home/employee_data');
			return;
		}

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
		$current_record_version = isset($records[$record_index]['record_version'])
			? (int) $records[$record_index]['record_version']
			: 1;
		if ($current_record_version <= 0)
		{
			$current_record_version = 1;
		}
		$expected_record_version = (int) $this->input->post('expected_version', TRUE);
		if ($expected_record_version <= 0 || $expected_record_version !== $current_record_version)
		{
			$this->session->set_flashdata(
				'attendance_notice_error',
				'Konflik versi data absensi '.$username.' tanggal '.$date_key.'. Muat ulang tabel lalu coba lagi.'
			);
			redirect('home/employee_data');
			return;
		}

		$record_row = isset($records[$record_index]) && is_array($records[$record_index])
			? $records[$record_index]
			: array();
		$check_in_before = isset($record_row['check_in_time']) ? trim((string) $record_row['check_in_time']) : '';
		$check_out_before = isset($record_row['check_out_time']) ? trim((string) $record_row['check_out_time']) : '';
		$has_check_in_before = $this->has_real_attendance_time($check_in_before);
		$has_check_out_before = $this->has_real_attendance_time($check_out_before);
		if ($delete_part === 'masuk' && !$has_check_in_before)
		{
			$this->session->set_flashdata('attendance_notice_error', 'Absensi masuk untuk '.$username.' tanggal '.$date_key.' sudah kosong.');
			redirect('home/employee_data');
			return;
		}
		if ($delete_part === 'pulang' && !$has_check_out_before)
		{
			$this->session->set_flashdata('attendance_notice_error', 'Absensi pulang untuk '.$username.' tanggal '.$date_key.' sudah kosong.');
			redirect('home/employee_data');
			return;
		}

		if ($delete_part === 'masuk')
		{
			$record_row['check_in_time'] = '';
			$record_row['check_in_late'] = '00:00:00';
			$record_row['check_in_photo'] = '';
			$record_row['check_in_lat'] = '';
			$record_row['check_in_lng'] = '';
			$record_row['check_in_accuracy_m'] = '';
			$record_row['check_in_distance_m'] = '';
			$record_row['jenis_masuk'] = '';
			$record_row['late_reason'] = '';
			$record_row['salary_cut_amount'] = '0';
			$record_row['salary_cut_rule'] = 'Disesuaikan admin (hapus absen masuk)';
			$record_row['salary_cut_category'] = '';
		}
		else
		{
			$record_row['check_out_time'] = '';
			$record_row['check_out_photo'] = '';
			$record_row['check_out_lat'] = '';
			$record_row['check_out_lng'] = '';
			$record_row['check_out_accuracy_m'] = '';
			$record_row['check_out_distance_m'] = '';
			$record_row['jenis_pulang'] = '';
		}

		$check_in_after = isset($record_row['check_in_time']) ? trim((string) $record_row['check_in_time']) : '';
		$check_out_after = isset($record_row['check_out_time']) ? trim((string) $record_row['check_out_time']) : '';
		$record_row['work_duration'] = $this->calculate_work_duration($check_in_after, $check_out_after);
		if (trim((string) $record_row['work_duration']) === '')
		{
			$record_row['work_duration'] = '00:00:00';
		}
		$has_check_in_after = $this->has_real_attendance_time($check_in_after);
		$has_check_out_after = $this->has_real_attendance_time($check_out_after);

		$partial_label = $delete_part === 'masuk' ? 'masuk' : 'pulang';
		$action_key = $delete_part === 'masuk' ? 'delete_attendance_check_in' : 'delete_attendance_check_out';
		if (!$has_check_in_after && !$has_check_out_after)
		{
			unset($records[$record_index]);
			$this->save_attendance_records(array_values($records));
			$this->clear_admin_dashboard_live_summary_cache();
			$this->log_activity_event(
				$action_key,
				'web_data',
				$username,
				$username,
				'Hapus absensi '.$partial_label.' lalu data harian menjadi kosong (record dihapus). Tanggal '.$date_key.'.',
				array(
					'target_id' => strtolower($username).'|'.$date_key,
					'old_value' => 'masuk='.($check_in_before !== '' ? $check_in_before : '-').',pulang='.($check_out_before !== '' ? $check_out_before : '-'),
					'new_value' => 'masuk=-,pulang=-'
				)
			);
			$this->session->set_flashdata(
				'attendance_notice_success',
				'Absensi '.$partial_label.' untuk '.$username.' tanggal '.$date_key.' berhasil dihapus. Data harian kosong sehingga record ikut dihapus.'
			);
			redirect('home/employee_data');
			return;
		}

		$record_row['updated_at'] = date('Y-m-d H:i:s');
		$record_row['record_version'] = $current_record_version + 1;
		$records[$record_index] = $record_row;
		$this->save_attendance_records($records);
		$this->clear_admin_dashboard_live_summary_cache();

		$this->log_activity_event(
			$action_key,
			'web_data',
			$username,
			$username,
			'Hapus absensi '.$partial_label.' pada data web. Tanggal '.$date_key.'.',
			array(
				'target_id' => strtolower($username).'|'.$date_key,
				'old_value' => 'masuk='.($check_in_before !== '' ? $check_in_before : '-').',pulang='.($check_out_before !== '' ? $check_out_before : '-'),
				'new_value' => 'masuk='.($check_in_after !== '' ? $check_in_after : '-').',pulang='.($check_out_after !== '' ? $check_out_after : '-')
			)
		);

		$this->session->set_flashdata(
			'attendance_notice_success',
			'Absensi '.$partial_label.' untuk '.$username.' tanggal '.$date_key.' berhasil dihapus.'
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
		$latest_leave_request = count($request_records) > 0
			? $request_records[count($request_records) - 1]
			: array();
		$admin_notify_result = $this->notify_admin_new_submission('leave', $latest_leave_request);
		if (!isset($admin_notify_result['success']) || $admin_notify_result['success'] !== TRUE)
		{
			$notify_reason = isset($admin_notify_result['message']) ? (string) $admin_notify_result['message'] : 'unknown error';
			log_message('error', 'Notifikasi WA pengajuan leave gagal: '.$notify_reason);
		}

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
		$latest_loan_request = count($loan_records) > 0
			? $loan_records[count($loan_records) - 1]
			: array();
		$admin_notify_result = $this->notify_admin_new_submission('loan', $latest_loan_request);
		if (!isset($admin_notify_result['success']) || $admin_notify_result['success'] !== TRUE)
		{
			$notify_reason = isset($admin_notify_result['message']) ? (string) $admin_notify_result['message'] : 'unknown error';
			log_message('error', 'Notifikasi WA pengajuan pinjaman gagal: '.$notify_reason);
		}

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
		if (!$this->can_process_leave_requests_feature())
		{
			$this->session->set_flashdata('leave_notice_error', 'Akun login kamu belum punya izin untuk proses pengajuan cuti/izin.');
			redirect('home/leave_requests');
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
		if (!$this->can_process_loan_requests_feature())
		{
			$this->session->set_flashdata('loan_notice_error', 'Akun login kamu belum punya izin untuk proses pengajuan pinjaman.');
			redirect('home/loan_requests');
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

		if (!$this->can_delete_leave_requests_feature())
		{
			$this->session->set_flashdata('leave_notice_error', 'Akun login kamu belum punya izin untuk hapus data pengajuan cuti/izin.');
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

		if (!$this->can_delete_loan_requests_feature())
		{
			$this->session->set_flashdata('loan_notice_error', 'Akun login kamu belum punya izin untuk hapus data pengajuan pinjaman.');
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
			'is_developer_actor' => $this->is_developer_actor(),
			'can_process_leave_requests' => $this->can_process_leave_requests_feature(),
			'can_delete_leave_requests' => $this->can_delete_leave_requests_feature()
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
			'is_developer_actor' => $this->is_developer_actor(),
			'can_process_loan_requests' => $this->can_process_loan_requests_feature(),
			'can_delete_loan_requests' => $this->can_delete_loan_requests_feature()
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
		$all_logs_by_date = array();

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
			$row_shift_name = isset($records[$i]['shift_name']) ? trim((string) $records[$i]['shift_name']) : '';
			$row_shift_time = isset($records[$i]['shift_time']) ? trim((string) $records[$i]['shift_time']) : '';
			if ($row_shift_time === '')
			{
				$row_shift_time = $shift_time;
			}

			$late_duration = isset($records[$i]['check_in_late']) ? trim((string) $records[$i]['check_in_late']) : '';
			if ($late_duration === '' && $row_check_in !== '')
			{
				$late_duration = $this->calculate_late_duration($row_check_in, $row_shift_time, $row_shift_name);
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

			$log_payload = $this->build_attendance_log_payload(
				$records[$i],
				$row_check_in,
				$row_check_out,
				$late_duration,
				TRUE
			);
			$sort_meta = $this->build_attendance_record_sort_meta($records[$i], $row_date, $row_check_in, $row_check_out);
			$log_row = array(
				'date' => $row_date,
				'sort_key' => isset($sort_meta['sort_key']) ? (string) $sort_meta['sort_key'] : ($row_date.' 00:00:00'),
				'sort_ts' => isset($sort_meta['sort_ts']) ? (int) $sort_meta['sort_ts'] : 0,
				'updated_ts' => isset($sort_meta['updated_ts']) ? (int) $sort_meta['updated_ts'] : 0,
				'masuk' => $this->format_user_dashboard_time_hhmm($row_check_in),
				'pulang' => $this->format_user_dashboard_time_hhmm($row_check_out),
				'status' => isset($log_payload['status']) ? (string) $log_payload['status'] : '-',
				'catatan' => isset($log_payload['catatan']) ? (string) $log_payload['catatan'] : '-',
				'potongan' => isset($log_payload['potongan']) ? (string) $log_payload['potongan'] : '-'
			);
			$existing_log = isset($all_logs_by_date[$row_date]) && is_array($all_logs_by_date[$row_date])
				? $all_logs_by_date[$row_date]
				: NULL;
			if (!is_array($existing_log) || $this->is_newer_attendance_row($log_row, $existing_log))
			{
				$all_logs_by_date[$row_date] = $log_row;
			}
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

		$all_logs = array_values($all_logs_by_date);
		usort($all_logs, function ($a, $b) {
			$left_sort = isset($a['sort_ts']) ? (int) $a['sort_ts'] : 0;
			$right_sort = isset($b['sort_ts']) ? (int) $b['sort_ts'] : 0;
			if ($left_sort !== $right_sort)
			{
				return $right_sort <=> $left_sort;
			}
			$left_updated = isset($a['updated_ts']) ? (int) $a['updated_ts'] : 0;
			$right_updated = isset($b['updated_ts']) ? (int) $b['updated_ts'] : 0;
			if ($left_updated !== $right_updated)
			{
				return $right_updated <=> $left_updated;
			}
			$left_key = isset($a['sort_key']) ? (string) $a['sort_key'] : '';
			$right_key = isset($b['sort_key']) ? (string) $b['sort_key'] : '';
			return strcmp($right_key, $left_key);
		});

		$recent_logs_limit = 10;
		for ($i = 0; $i < count($all_logs) && $i < $recent_logs_limit; $i += 1)
		{
			$recent_logs[] = array(
				'tanggal' => $this->format_user_dashboard_date_label(isset($all_logs[$i]['date']) ? (string) $all_logs[$i]['date'] : ''),
				'masuk' => isset($all_logs[$i]['masuk']) ? (string) $all_logs[$i]['masuk'] : '-',
				'pulang' => isset($all_logs[$i]['pulang']) ? (string) $all_logs[$i]['pulang'] : '-',
				'status' => isset($all_logs[$i]['status']) ? (string) $all_logs[$i]['status'] : '-',
				'catatan' => isset($all_logs[$i]['catatan']) ? (string) $all_logs[$i]['catatan'] : '-',
				'potongan' => isset($all_logs[$i]['potongan']) ? (string) $all_logs[$i]['potongan'] : '-'
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

	private function build_attendance_record_sort_meta($record_row, $date_key, $check_in_time, $check_out_time)
	{
		$date_value = trim((string) $date_key);
		if (!$this->is_valid_date_format($date_value))
		{
			$date_value = date('Y-m-d');
		}

		$in_seconds = $this->has_real_attendance_time($check_in_time) ? max(0, (int) $this->time_to_seconds($check_in_time)) : 0;
		$out_seconds = $this->has_real_attendance_time($check_out_time) ? max(0, (int) $this->time_to_seconds($check_out_time)) : 0;
		$sort_seconds = max($in_seconds, $out_seconds);
		if ($sort_seconds > 86399)
		{
			$sort_seconds = 86399;
		}
		$sort_time = gmdate('H:i:s', $sort_seconds);
		$sort_key = $date_value.' '.$sort_time;

		$sort_ts = strtotime($sort_key);
		if ($sort_ts === FALSE)
		{
			$sort_ts = 0;
		}

		$updated_at = isset($record_row['updated_at']) ? trim((string) $record_row['updated_at']) : '';
		$updated_ts = 0;
		if ($updated_at !== '')
		{
			$updated_parsed = strtotime($updated_at);
			if ($updated_parsed !== FALSE)
			{
				$updated_ts = (int) $updated_parsed;
			}
		}

		return array(
			'sort_key' => $sort_key,
			'sort_ts' => (int) $sort_ts,
			'updated_ts' => (int) $updated_ts
		);
	}

	private function is_newer_attendance_row($candidate_row, $current_row)
	{
		$candidate_sort_ts = isset($candidate_row['sort_ts']) ? (int) $candidate_row['sort_ts'] : 0;
		$current_sort_ts = isset($current_row['sort_ts']) ? (int) $current_row['sort_ts'] : 0;
		if ($candidate_sort_ts !== $current_sort_ts)
		{
			return $candidate_sort_ts > $current_sort_ts;
		}

		$candidate_updated_ts = isset($candidate_row['updated_ts']) ? (int) $candidate_row['updated_ts'] : 0;
		$current_updated_ts = isset($current_row['updated_ts']) ? (int) $current_row['updated_ts'] : 0;
		if ($candidate_updated_ts !== $current_updated_ts)
		{
			return $candidate_updated_ts > $current_updated_ts;
		}

		$candidate_key = isset($candidate_row['sort_key']) ? (string) $candidate_row['sort_key'] : '';
		$current_key = isset($current_row['sort_key']) ? (string) $current_row['sort_key'] : '';
		return strcmp($candidate_key, $current_key) > 0;
	}

	private function build_attendance_log_payload($record_row, $check_in_time, $check_out_time, $late_duration = '', $include_deduction = FALSE)
	{
		$check_in = trim((string) $check_in_time);
		$check_out = trim((string) $check_out_time);
		$late_value = trim((string) $late_duration);
		if ($late_value === '')
		{
			$late_value = isset($record_row['check_in_late']) ? trim((string) $record_row['check_in_late']) : '';
		}
		if ($late_value === '' && $check_in !== '')
		{
			$row_shift_name = isset($record_row['shift_name']) ? (string) $record_row['shift_name'] : '';
			$row_shift_time = isset($record_row['shift_time']) ? (string) $record_row['shift_time'] : '';
			$late_value = $this->calculate_late_duration($check_in, $row_shift_time, $row_shift_name);
		}
		if ($late_value === '')
		{
			$late_value = '00:00:00';
		}

		$is_late = $check_in !== '' && $this->duration_to_seconds($late_value) > 0;
		$status_label = '-';
		if ($check_in !== '')
		{
			$status_label = $is_late ? 'Terlambat' : 'Hadir';
		}

		$note = '-';
		if ($status_label === 'Hadir')
		{
			$note = $check_out !== '' ? 'Selesai check out' : 'On time';
		}
		elseif ($status_label === 'Terlambat')
		{
			$note = $late_value !== '' && $late_value !== '00:00:00'
				? 'Terlambat '.$late_value
				: 'Terlambat';
		}
		$note = trim((string) $note);
		if ($note === '')
		{
			$note = '-';
		}

		$deduction_label = '-';
		if ($include_deduction === TRUE && $is_late)
		{
			$salary_cut_amount = $this->extract_attendance_salary_cut_amount($record_row);
			if ($salary_cut_amount <= 0)
			{
				$salary_cut_amount = $this->resolve_attendance_salary_cut_fallback($record_row, $late_value, $check_in);
			}
			if ($salary_cut_amount > 0)
			{
				$deduction_label = $this->format_currency_idr($salary_cut_amount);
			}
		}
		$deduction_label = trim((string) $deduction_label);
		if ($deduction_label === '')
		{
			$deduction_label = '-';
		}

		return array(
			'status' => $status_label,
			'catatan' => $note,
			'potongan' => $deduction_label,
			'late_duration' => $late_value,
			'is_late' => $is_late
		);
	}

	private function extract_attendance_salary_cut_amount($record_row)
	{
		$source_keys = array(
			'salary_cut_amount',
			'salary_cut',
			'potongan_gaji',
			'potongan',
			'deduction_amount'
		);
		for ($i = 0; $i < count($source_keys); $i += 1)
		{
			$key = (string) $source_keys[$i];
			if (!isset($record_row[$key]))
			{
				continue;
			}
			$raw = trim((string) $record_row[$key]);
			if ($raw === '')
			{
				continue;
			}
			$digits = preg_replace('/\D+/', '', $raw);
			if ($digits === '')
			{
				continue;
			}
			return max(0, (int) $digits);
		}

		return 0;
	}

	private function resolve_attendance_salary_cut_fallback($record_row, $late_duration, $check_in_time = '')
	{
		$late_seconds = $this->duration_to_seconds((string) $late_duration);
		if ($late_seconds <= 0)
		{
			$check_in = trim((string) $check_in_time);
			$row_shift_name = isset($record_row['shift_name']) ? (string) $record_row['shift_name'] : '';
			$row_shift_time = isset($record_row['shift_time']) ? (string) $record_row['shift_time'] : '';
			if ($check_in !== '' && ($row_shift_time !== '' || $row_shift_name !== ''))
			{
				$late_seconds = $this->duration_to_seconds(
					$this->calculate_late_duration($check_in, $row_shift_time, $row_shift_name)
				);
			}
		}
		if ($late_seconds <= 0)
		{
			return 0;
		}

		$salary_cut_rule = isset($record_row['salary_cut_rule']) ? strtolower(trim((string) $record_row['salary_cut_rule'])) : '';
		if (strpos($salary_cut_rule, 'disesuaikan admin (potongan dihapus)') === 0)
		{
			return 0;
		}

		$salary_tier = isset($record_row['salary_tier']) ? (string) $record_row['salary_tier'] : '';
		$salary_monthly = isset($record_row['salary_monthly']) ? (float) $record_row['salary_monthly'] : 0;
		$work_days = isset($record_row['work_days_per_month']) && (int) $record_row['work_days_per_month'] > 0
			? (int) $record_row['work_days_per_month']
			: 24;
		$date_key = isset($record_row['date']) ? trim((string) $record_row['date']) : date('Y-m-d');
		$username = isset($record_row['username']) ? trim((string) $record_row['username']) : '';
		$weekly_day_off_override = isset($record_row['weekly_day_off']) ? $record_row['weekly_day_off'] : NULL;

		$deduction_result = $this->calculate_late_deduction(
			$salary_tier,
			$salary_monthly,
			$work_days,
			$late_seconds,
			$date_key,
			$username,
			$weekly_day_off_override
		);
		$amount = isset($deduction_result['amount']) ? (int) $deduction_result['amount'] : 0;
		return max(0, $amount);
	}

	private function format_currency_idr($amount)
	{
		$value = (int) round((float) $amount);
		if ($value < 0)
		{
			$value = 0;
		}

		return 'Rp '.number_format($value, 0, ',', '.');
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
		$today_alpha_total = 0;
		$summary = array(
			'status_hari_ini' => 'Monitoring Hari Ini',
			'jam_masuk' => '-',
			'jam_pulang' => '-',
			'total_hadir_bulan_ini' => 0,
			'total_terlambat_bulan_ini' => 0,
			'total_izin_bulan_ini' => 0,
			'total_alpha_bulan_ini' => 0,
			'total_hadir_hari_ini' => 0,
			'total_terlambat_hari_ini' => 0,
			'total_izin_hari_ini' => 0,
			'total_alpha_hari_ini' => 0,
			'total_karyawan_hari_ini' => 0,
			'total_terjadwal_hari_ini' => 0,
			'total_libur_hari_ini' => 0,
			'total_belum_masuk_masa_alpha_hari_ini' => 0,
			'total_sudah_masuk_masa_alpha_hari_ini' => 0
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
				if ($date_key === $today_key)
				{
					$today_alpha_total = isset($day_counts['alpha']) ? (int) $day_counts['alpha'] : 0;
					$summary['total_hadir_hari_ini'] = isset($day_counts['hadir']) ? (int) $day_counts['hadir'] : 0;
					$summary['total_terlambat_hari_ini'] = isset($day_counts['terlambat']) ? (int) $day_counts['terlambat'] : 0;
					$summary['total_izin_hari_ini'] = isset($day_counts['izin_cuti_valid']) ? (int) $day_counts['izin_cuti_valid'] : 0;
					$summary['total_alpha_hari_ini'] = isset($day_counts['alpha']) ? (int) $day_counts['alpha'] : 0;
					$summary['total_karyawan_hari_ini'] = isset($day_counts['employee_total']) ? (int) $day_counts['employee_total'] : 0;
					$summary['total_terjadwal_hari_ini'] = isset($day_counts['scheduled_total']) ? (int) $day_counts['scheduled_total'] : 0;
					$summary['total_libur_hari_ini'] = isset($day_counts['offday_total']) ? (int) $day_counts['offday_total'] : 0;
					$summary['total_belum_masuk_masa_alpha_hari_ini'] = isset($day_counts['pending_alpha_total']) ? (int) $day_counts['pending_alpha_total'] : 0;
					$summary['total_sudah_masuk_masa_alpha_hari_ini'] = isset($day_counts['alpha_target_total']) ? (int) $day_counts['alpha_target_total'] : 0;
				}
			}
		}
		$summary['total_alpha_bulan_ini'] = max(0, (int) $today_alpha_total);
		$sheet_summary_totals = $this->build_admin_sheet_month_summary_totals(date('Y-m'));
		if (isset($sheet_summary_totals['has_data']) && $sheet_summary_totals['has_data'] === TRUE)
		{
			$summary['total_hadir_bulan_ini'] = max(
				(int) $summary['total_hadir_bulan_ini'],
				isset($sheet_summary_totals['total_hadir']) ? (int) $sheet_summary_totals['total_hadir'] : 0
			);
			$summary['total_terlambat_bulan_ini'] = max(
				(int) $summary['total_terlambat_bulan_ini'],
				isset($sheet_summary_totals['total_terlambat']) ? (int) $sheet_summary_totals['total_terlambat'] : 0
			);
				$summary['total_izin_bulan_ini'] = max(0, (int) $summary['total_izin_bulan_ini']);
			$summary['total_alpha_bulan_ini'] = max(
				(int) $summary['total_alpha_bulan_ini'],
				isset($sheet_summary_totals['total_alpha']) ? (int) $sheet_summary_totals['total_alpha'] : 0
			);
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

		$branch_scope = '';
		if ($this->is_branch_scoped_admin())
		{
			$branch_scope = $this->current_actor_branch();
		}
		if (
			isset($this->absen_sheet_sync) &&
			is_object($this->absen_sheet_sync) &&
			method_exists($this->absen_sheet_sync, 'read_attendance_sheet_month_summary_totals')
		)
		{
			$sheet_live_totals = $this->absen_sheet_sync->read_attendance_sheet_month_summary_totals($month_key, $branch_scope);
			if (
				is_array($sheet_live_totals) &&
				isset($sheet_live_totals['success']) &&
				$sheet_live_totals['success'] === TRUE &&
				isset($sheet_live_totals['has_data']) &&
				$sheet_live_totals['has_data'] === TRUE
			)
			{
				return array(
					'has_data' => TRUE,
					'total_hadir' => isset($sheet_live_totals['total_hadir']) ? (int) $sheet_live_totals['total_hadir'] : 0,
					'total_terlambat' => isset($sheet_live_totals['total_terlambat']) ? (int) $sheet_live_totals['total_terlambat'] : 0,
					'total_izin' => isset($sheet_live_totals['total_izin']) ? (int) $sheet_live_totals['total_izin'] : 0,
					'total_alpha' => isset($sheet_live_totals['total_alpha']) ? (int) $sheet_live_totals['total_alpha'] : 0,
					'users' => isset($sheet_live_totals['users']) ? (int) $sheet_live_totals['users'] : 0
				);
			}
		}

		$account_book = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		$account_lookup = array();
		if (is_array($account_book))
		{
			foreach ($account_book as $account_username => $account_row)
			{
				$account_username_key = strtolower(trim((string) $account_username));
				if ($account_username_key === '')
				{
					continue;
				}
				$account_lookup[$account_username_key] = is_array($account_row) ? $account_row : array();
			}
		}

		$account_total_hadir = 0;
		$account_total_terlambat = 0;
		$account_total_izin = 0;
		$account_total_alpha = 0;
		$account_summary_users = 0;
		foreach ($employee_lookup as $username_key => $unused_flag)
		{
			if (!isset($account_lookup[$username_key]) || !is_array($account_lookup[$username_key]))
			{
				continue;
			}

			$account_row = $account_lookup[$username_key];
			$summary_row = array();
			if (
				isset($account_row['sheet_summary_by_month']) &&
				is_array($account_row['sheet_summary_by_month']) &&
				isset($account_row['sheet_summary_by_month'][$month_key]) &&
				is_array($account_row['sheet_summary_by_month'][$month_key])
			)
			{
				$summary_row = $account_row['sheet_summary_by_month'][$month_key];
			}
			elseif (isset($account_row['sheet_summary']) && is_array($account_row['sheet_summary']))
			{
				$candidate_summary = $account_row['sheet_summary'];
				$candidate_month = isset($candidate_summary['month']) ? trim((string) $candidate_summary['month']) : '';
				if ($candidate_month === '' || $candidate_month === $month_key)
				{
					$summary_row = $candidate_summary;
				}
			}

			if (empty($summary_row))
			{
				continue;
			}

			$hadir = isset($summary_row['total_hadir']) ? (int) $summary_row['total_hadir'] : 0;
			if ($hadir <= 0)
			{
				$hadir = isset($summary_row['sudah_berapa_absen']) ? (int) $summary_row['sudah_berapa_absen'] : 0;
			}
			$hadir = max(0, $hadir);
			$account_total_hadir += $hadir;

			$telat_1_30 = max(0, (int) (isset($summary_row['total_telat_1_30']) ? $summary_row['total_telat_1_30'] : 0));
			$telat_31_60 = max(0, (int) (isset($summary_row['total_telat_31_60']) ? $summary_row['total_telat_31_60'] : 0));
			$telat_1_3 = max(0, (int) (isset($summary_row['total_telat_1_3']) ? $summary_row['total_telat_1_3'] : 0));
			$telat_gt_4 = max(0, (int) (isset($summary_row['total_telat_gt_4']) ? $summary_row['total_telat_gt_4'] : 0));
			$account_total_terlambat += $telat_1_30 + $telat_31_60 + $telat_1_3 + $telat_gt_4;

			$izin_cuti = isset($summary_row['total_izin_cuti']) ? (int) $summary_row['total_izin_cuti'] : 0;
			if ($izin_cuti <= 0)
			{
				$total_izin_value = max(0, (int) (isset($summary_row['total_izin']) ? $summary_row['total_izin'] : 0));
				$total_cuti_value = max(0, (int) (isset($summary_row['total_cuti']) ? $summary_row['total_cuti'] : 0));
				$izin_cuti = $total_izin_value + $total_cuti_value;
			}
			$account_total_izin += max(0, $izin_cuti);
			$account_total_alpha += max(0, (int) (isset($summary_row['total_alpha']) ? $summary_row['total_alpha'] : 0));
			$account_summary_users += 1;
		}

		if ($account_summary_users > 0)
		{
			return array(
				'has_data' => TRUE,
				'total_hadir' => (int) $account_total_hadir,
				'total_terlambat' => (int) $account_total_terlambat,
				'total_izin' => (int) $account_total_izin,
				'total_alpha' => (int) $account_total_alpha,
				'users' => (int) $account_summary_users
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

	private function alpha_reset_state_file_path()
	{
		return APPPATH.'cache/alpha_reset_state.json';
	}

	private function load_alpha_reset_state()
	{
		$file_path = $this->alpha_reset_state_file_path();
		$state = array(
			'by_date' => array(),
			'updated_at' => ''
		);

		if (function_exists('absen_data_store_load_value'))
		{
			$loaded = absen_data_store_load_value(self::ALPHA_RESET_STATE_STORE_KEY, NULL, $file_path);
			if (is_array($loaded))
			{
				$state = array_merge($state, $loaded);
			}
		}
		elseif (is_file($file_path))
		{
			$content = @file_get_contents($file_path);
			if ($content !== FALSE && trim((string) $content) !== '')
			{
				$decoded = json_decode((string) $content, TRUE);
				if (is_array($decoded))
				{
					$state = array_merge($state, $decoded);
				}
			}
		}

		return $this->normalize_alpha_reset_state($state);
	}

	private function save_alpha_reset_state($state)
	{
		$file_path = $this->alpha_reset_state_file_path();
		$state = $this->normalize_alpha_reset_state($state);
		$state['updated_at'] = date('Y-m-d H:i:s');
		$saved_to_store = FALSE;

		if (function_exists('absen_data_store_save_value'))
		{
			$saved_to_store = absen_data_store_save_value(self::ALPHA_RESET_STATE_STORE_KEY, $state, $file_path);
		}

		$directory = dirname($file_path);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0777, TRUE);
		}

		$saved_to_file = FALSE;
		if (is_dir($directory))
		{
			$saved_to_file = (bool) @file_put_contents($file_path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
		}

		return $saved_to_store || $saved_to_file;
	}

	private function normalize_alpha_reset_state($state)
	{
		$state = is_array($state) ? $state : array();
		$raw_by_date = isset($state['by_date']) && is_array($state['by_date'])
			? $state['by_date']
			: array();
		$normalized_by_date = array();

		foreach ($raw_by_date as $date_key => $usernames)
		{
			$date_text = trim((string) $date_key);
			if (!$this->is_valid_date_format($date_text))
			{
				continue;
			}

			$list = is_array($usernames) ? $usernames : array();
			$normalized_users = array();
			for ($i = 0; $i < count($list); $i += 1)
			{
				$user_key = strtolower(trim((string) $list[$i]));
				if ($user_key === '')
				{
					continue;
				}
				$normalized_users[] = $user_key;
			}

			$normalized_users = array_values(array_unique($normalized_users));
			if (empty($normalized_users))
			{
				continue;
			}

			$normalized_by_date[$date_text] = $normalized_users;
		}

		ksort($normalized_by_date);
		return array(
			'by_date' => $normalized_by_date,
			'updated_at' => isset($state['updated_at']) ? trim((string) $state['updated_at']) : ''
		);
	}

	private function build_admin_dashboard_snapshot()
	{
		$metric_maps = $this->build_admin_metric_maps();
		$today_key = date('Y-m-d');
		$month_start_key = date('Y-m-01');
		$today_alpha_total = 0;
		$summary = array(
			'status_hari_ini' => 'Monitoring Hari Ini',
			'jam_masuk' => '-',
			'jam_pulang' => '-',
			'total_hadir_bulan_ini' => 0,
			'total_terlambat_bulan_ini' => 0,
			'total_izin_bulan_ini' => 0,
			'total_alpha_bulan_ini' => 0,
			'total_hadir_hari_ini' => 0,
			'total_terlambat_hari_ini' => 0,
			'total_izin_hari_ini' => 0,
			'total_alpha_hari_ini' => 0,
			'total_karyawan_hari_ini' => 0,
			'total_terjadwal_hari_ini' => 0,
			'total_libur_hari_ini' => 0,
			'total_belum_masuk_masa_alpha_hari_ini' => 0,
			'total_sudah_masuk_masa_alpha_hari_ini' => 0
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
				if ($date_key === $today_key)
				{
					$today_alpha_total = isset($day_counts['alpha']) ? (int) $day_counts['alpha'] : 0;
					$summary['total_hadir_hari_ini'] = isset($day_counts['hadir']) ? (int) $day_counts['hadir'] : 0;
					$summary['total_terlambat_hari_ini'] = isset($day_counts['terlambat']) ? (int) $day_counts['terlambat'] : 0;
					$summary['total_izin_hari_ini'] = isset($day_counts['izin_cuti_valid']) ? (int) $day_counts['izin_cuti_valid'] : 0;
					$summary['total_alpha_hari_ini'] = isset($day_counts['alpha']) ? (int) $day_counts['alpha'] : 0;
					$summary['total_karyawan_hari_ini'] = isset($day_counts['employee_total']) ? (int) $day_counts['employee_total'] : 0;
					$summary['total_terjadwal_hari_ini'] = isset($day_counts['scheduled_total']) ? (int) $day_counts['scheduled_total'] : 0;
					$summary['total_libur_hari_ini'] = isset($day_counts['offday_total']) ? (int) $day_counts['offday_total'] : 0;
					$summary['total_belum_masuk_masa_alpha_hari_ini'] = isset($day_counts['pending_alpha_total']) ? (int) $day_counts['pending_alpha_total'] : 0;
					$summary['total_sudah_masuk_masa_alpha_hari_ini'] = isset($day_counts['alpha_target_total']) ? (int) $day_counts['alpha_target_total'] : 0;
				}
			}
		}
		$summary['total_alpha_bulan_ini'] = max(0, (int) $today_alpha_total);
		$sheet_summary_totals = $this->build_admin_sheet_month_summary_totals(date('Y-m'));
		if (isset($sheet_summary_totals['has_data']) && $sheet_summary_totals['has_data'] === TRUE)
		{
			$summary['total_hadir_bulan_ini'] = max(
				(int) $summary['total_hadir_bulan_ini'],
				isset($sheet_summary_totals['total_hadir']) ? (int) $sheet_summary_totals['total_hadir'] : 0
			);
			$summary['total_terlambat_bulan_ini'] = max(
				(int) $summary['total_terlambat_bulan_ini'],
				isset($sheet_summary_totals['total_terlambat']) ? (int) $sheet_summary_totals['total_terlambat'] : 0
			);
				$summary['total_izin_bulan_ini'] = max(0, (int) $summary['total_izin_bulan_ini']);
			$summary['total_alpha_bulan_ini'] = max(
				(int) $summary['total_alpha_bulan_ini'],
				isset($sheet_summary_totals['total_alpha']) ? (int) $sheet_summary_totals['total_alpha'] : 0
			);
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

		$recent_rows_by_username = array();
		$records = $this->load_attendance_records();
		$employee_lookup = isset($metric_maps['employee_lookup']) && is_array($metric_maps['employee_lookup'])
			? $metric_maps['employee_lookup']
			: array();
		$employee_alias_lookup = isset($metric_maps['employee_alias_lookup']) && is_array($metric_maps['employee_alias_lookup'])
			? $metric_maps['employee_alias_lookup']
			: array();
		for ($i = 0; $i < count($records); $i += 1)
		{
			$row_username_raw = isset($records[$i]['username']) ? (string) $records[$i]['username'] : '';
			$username_key = $this->resolve_admin_metric_employee_username_key(
				$row_username_raw,
				$employee_lookup,
				$employee_alias_lookup
			);
			if ($username_key === '')
			{
				continue;
			}
			if (!$this->is_username_in_actor_scope($username_key))
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

			$log_payload = $this->build_attendance_log_payload($records[$i], $check_in, $check_out, '', FALSE);
			$sort_meta = $this->build_attendance_record_sort_meta($records[$i], $date_key, $check_in, $check_out);
			$recent_row = array(
				'sort_key' => isset($sort_meta['sort_key']) ? (string) $sort_meta['sort_key'] : ($date_key.' 00:00:00'),
				'sort_ts' => isset($sort_meta['sort_ts']) ? (int) $sort_meta['sort_ts'] : 0,
				'updated_ts' => isset($sort_meta['updated_ts']) ? (int) $sort_meta['updated_ts'] : 0,
				'username' => $username_key,
				'tanggal' => $this->format_user_dashboard_date_label($date_key),
				'masuk' => $this->format_user_dashboard_time_hhmm($check_in),
				'pulang' => $this->format_user_dashboard_time_hhmm($check_out),
				'status' => isset($log_payload['status']) ? (string) $log_payload['status'] : '-',
				'catatan' => isset($log_payload['catatan']) ? (string) $log_payload['catatan'] : '-'
			);
			$existing_row = isset($recent_rows_by_username[$username_key]) && is_array($recent_rows_by_username[$username_key])
				? $recent_rows_by_username[$username_key]
				: NULL;
			if (!is_array($existing_row) || $this->is_newer_attendance_row($recent_row, $existing_row))
			{
				$recent_rows_by_username[$username_key] = $recent_row;
			}
		}

		$recent_rows = array_values($recent_rows_by_username);
		usort($recent_rows, function ($a, $b) {
			$left_sort = isset($a['sort_ts']) ? (int) $a['sort_ts'] : 0;
			$right_sort = isset($b['sort_ts']) ? (int) $b['sort_ts'] : 0;
			if ($left_sort !== $right_sort)
			{
				return $right_sort <=> $left_sort;
			}
			$left_updated = isset($a['updated_ts']) ? (int) $a['updated_ts'] : 0;
			$right_updated = isset($b['updated_ts']) ? (int) $b['updated_ts'] : 0;
			if ($left_updated !== $right_updated)
			{
				return $right_updated <=> $left_updated;
			}
			$left_key = isset($a['sort_key']) ? (string) $a['sort_key'] : '';
			$right_key = isset($b['sort_key']) ? (string) $b['sort_key'] : '';
			return strcmp($right_key, $left_key);
		});

		$recent_logs_limit = 10;
		$recent_logs = array();
		for ($i = 0; $i < count($recent_rows) && $i < $recent_logs_limit; $i += 1)
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
		$employee_alias_lookup = array();
		$display_name_by_username = array();
		$employee_id_by_username = array();
		$weekly_day_off_by_username = array();
		$shift_key_by_username = array();
		$employee_profiles = $this->employee_profile_book();
		$employee_id_book = $this->employee_id_book();
		for ($i = 0; $i < count($employees); $i += 1)
		{
			$username_key = strtolower(trim((string) $employees[$i]));
			if ($username_key === '')
			{
				continue;
			}
			$employee_lookup[$username_key] = TRUE;
			$employee_alias_lookup[$username_key] = $username_key;
			$username_normalized = $this->normalize_username_key($username_key);
			if ($username_normalized !== '' && !isset($employee_alias_lookup[$username_normalized]))
			{
				$employee_alias_lookup[$username_normalized] = $username_key;
			}
			$profile = isset($employee_profiles[$username_key]) && is_array($employee_profiles[$username_key])
				? $employee_profiles[$username_key]
				: array();
			$display_name = isset($profile['display_name']) ? trim((string) $profile['display_name']) : '';
			if ($display_name === '')
			{
				$display_name = $username_key;
			}
			$display_name_key = strtolower(trim((string) $display_name));
			if ($display_name_key !== '' && !isset($employee_alias_lookup[$display_name_key]))
			{
				$employee_alias_lookup[$display_name_key] = $username_key;
			}
			$display_name_normalized = $this->normalize_username_key($display_name);
			if ($display_name_normalized !== '' && !isset($employee_alias_lookup[$display_name_normalized]))
			{
				$employee_alias_lookup[$display_name_normalized] = $username_key;
			}
			$employee_id = $this->resolve_employee_id_from_book($username_key, $employee_id_book);
			if ($employee_id !== '-' && $employee_id !== '' && !isset($employee_alias_lookup[$employee_id]))
			{
				$employee_alias_lookup[$employee_id] = $username_key;
			}
			$employee_id_by_username[$username_key] = $employee_id !== '' ? $employee_id : '-';
			$display_name_by_username[$username_key] = $display_name;
			$weekly_day_off_by_username[$username_key] = isset($profile['weekly_day_off'])
				? $this->resolve_employee_weekly_day_off($profile['weekly_day_off'])
				: $this->default_weekly_day_off();
			$shift_key_by_username[$username_key] = $this->resolve_shift_key_from_profile($profile);
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
			$row_username_raw = isset($records[$i]['username']) ? (string) $records[$i]['username'] : '';
			$username_key = $this->resolve_admin_metric_employee_username_key(
				$row_username_raw,
				$employee_lookup,
				$employee_alias_lookup
			);
			if ($username_key === '')
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
					$row_shift_name = isset($records[$i]['shift_name']) ? (string) $records[$i]['shift_name'] : '';
					$row_shift_time = isset($records[$i]['shift_time']) ? (string) $records[$i]['shift_time'] : '';
					$late_duration = $this->calculate_late_duration($check_in, $row_shift_time, $row_shift_name);
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

		$leave_result = $this->build_admin_leave_map($employee_lookup, $employee_alias_lookup);
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

		$alpha_reset_state = $this->load_alpha_reset_state();
		$alpha_reset_by_date = isset($alpha_reset_state['by_date']) && is_array($alpha_reset_state['by_date'])
			? $alpha_reset_state['by_date']
			: array();

		return array(
			'employees' => $employees,
			'employee_lookup' => $employee_lookup,
			'employee_alias_lookup' => $employee_alias_lookup,
			'display_name_by_username' => $display_name_by_username,
			'employee_id_by_username' => $employee_id_by_username,
			'employee_count' => count($employee_lookup),
			'has_any_activity' => $has_any_activity ? TRUE : FALSE,
			'weekly_day_off_by_username' => $weekly_day_off_by_username,
			'shift_key_by_username' => $shift_key_by_username,
			'checkin_seconds_by_date' => $checkin_seconds_by_date,
			'checkout_seconds_by_date' => $checkout_seconds_by_date,
			'late_by_date' => $late_by_date,
			'leave_by_date' => $leave_by_date,
			'leave_count_by_date' => $leave_count_by_date,
			'alpha_reset_by_date' => $alpha_reset_by_date,
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

	private function build_admin_leave_map($employee_lookup, $employee_alias_lookup = array())
	{
		$by_date = array();
		$count_by_date = array();
		$min_date = '';
		$max_date = '';
		$requests = $this->load_leave_requests();

		for ($i = 0; $i < count($requests); $i += 1)
		{
			$request_username_raw = isset($requests[$i]['username']) ? (string) $requests[$i]['username'] : '';
			$username_key = $this->resolve_admin_metric_employee_username_key(
				$request_username_raw,
				$employee_lookup,
				$employee_alias_lookup
			);
			if ($username_key === '')
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

	private function resolve_admin_metric_employee_username_key($candidate_username, $employee_lookup, $employee_alias_lookup = array())
	{
		if (!is_array($employee_lookup) || empty($employee_lookup))
		{
			return '';
		}

		$candidate_raw = trim((string) $candidate_username);
		if ($candidate_raw === '')
		{
			return '';
		}

		$candidate_key = strtolower($candidate_raw);
		if (isset($employee_lookup[$candidate_key]))
		{
			return $candidate_key;
		}
		if (is_array($employee_alias_lookup) && isset($employee_alias_lookup[$candidate_key]))
		{
			$alias_key = strtolower(trim((string) $employee_alias_lookup[$candidate_key]));
			if ($alias_key !== '' && isset($employee_lookup[$alias_key]))
			{
				return $alias_key;
			}
		}

		$candidate_normalized = $this->normalize_username_key($candidate_raw);
		if ($candidate_normalized !== '')
		{
			if (isset($employee_lookup[$candidate_normalized]))
			{
				return $candidate_normalized;
			}
			if (is_array($employee_alias_lookup) && isset($employee_alias_lookup[$candidate_normalized]))
			{
				$alias_normalized = strtolower(trim((string) $employee_alias_lookup[$candidate_normalized]));
				if ($alias_normalized !== '' && isset($employee_lookup[$alias_normalized]))
				{
					return $alias_normalized;
				}
			}
		}

		return '';
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
				'alpha' => 0,
				'employee_total' => 0,
				'scheduled_total' => 0,
				'offday_total' => 0,
				'alpha_target_total' => 0,
				'pending_alpha_total' => 0,
				'izin_cuti_valid' => 0,
				'izin_cuti_unknown_count' => 0
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

		$hadir_lookup = array();
		foreach ($day_checkins as $username => $seconds)
		{
			$username_key = strtolower(trim((string) $username));
			if ($username_key === '')
			{
				continue;
			}
			$seconds_value = max(0, (int) $seconds);
			if ($hour_cutoff === NULL || $seconds_value <= $hour_cutoff)
			{
				$hadir_lookup[$username_key] = TRUE;
			}
		}

		$terlambat_lookup = array();
		foreach ($day_late_users as $username => $is_late)
		{
			$username_key = strtolower(trim((string) $username));
			if ($username_key === '')
			{
				continue;
			}
			if (!$is_late || !isset($day_checkins[$username]))
			{
				continue;
			}
			$seconds_value = max(0, (int) $day_checkins[$username]);
			if ($hour_cutoff === NULL || $seconds_value <= $hour_cutoff)
			{
				$terlambat_lookup[$username_key] = TRUE;
			}
		}

		$izin_cuti = $day_leave_count;
		$alpha = 0;
		$employee_total = 0;
		$scheduled_total = 0;
		$offday_total = 0;
		$alpha_target_total = 0;
		$pending_alpha_total = 0;
		$izin_cuti_valid = 0;
		$izin_cuti_unknown_count = 0;

		$today_key = date('Y-m-d');
		$has_day_activity = !empty($day_checkins) || !empty($day_checkouts) || $day_leave_count > 0;
		$current_cutoff_seconds = $hour_cutoff !== NULL
			? (int) $hour_cutoff
			: $this->time_to_seconds(date('H:i:s'));
		$enforce_today_cutoff = ($date_key === $today_key);

		$employee_lookup = isset($metric_maps['employee_lookup']) && is_array($metric_maps['employee_lookup'])
			? $metric_maps['employee_lookup']
			: array();
		$weekly_day_off_by_username = isset($metric_maps['weekly_day_off_by_username']) && is_array($metric_maps['weekly_day_off_by_username'])
			? $metric_maps['weekly_day_off_by_username']
			: array();
		$shift_key_by_username = isset($metric_maps['shift_key_by_username']) && is_array($metric_maps['shift_key_by_username'])
			? $metric_maps['shift_key_by_username']
			: array();
		$alpha_reset_by_date = isset($metric_maps['alpha_reset_by_date']) && is_array($metric_maps['alpha_reset_by_date'])
			? $metric_maps['alpha_reset_by_date']
			: array();
		$alpha_reset_lookup = array();
		if (isset($alpha_reset_by_date[$date_key]) && is_array($alpha_reset_by_date[$date_key]))
		{
			$reset_users = $alpha_reset_by_date[$date_key];
			for ($reset_i = 0; $reset_i < count($reset_users); $reset_i += 1)
			{
				$reset_key = strtolower(trim((string) $reset_users[$reset_i]));
				if ($reset_key !== '')
				{
					$alpha_reset_lookup[$reset_key] = TRUE;
				}
			}
		}

		$izin_lookup = array();
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
			if (!$this->is_employee_scheduled_workday($leave_username_key, $date_key, $user_weekly_off))
			{
				continue;
			}
			if ($enforce_today_cutoff)
			{
				$user_shift_key = isset($shift_key_by_username[$leave_username_key])
					? (string) $shift_key_by_username[$leave_username_key]
					: 'pagi';
				$user_alpha_cutoff = $this->resolve_shift_alpha_cutoff_seconds($user_shift_key);
				if ($current_cutoff_seconds < $user_alpha_cutoff)
				{
					continue;
				}
			}
			$izin_lookup[$leave_username_key] = TRUE;
		}

		$alpha_lookup = array();
		foreach ($employee_lookup as $username_key => $is_active)
		{
			if (!$is_active)
			{
				continue;
			}
			$employee_total += 1;
			$user_weekly_off = isset($weekly_day_off_by_username[$username_key])
				? $this->resolve_employee_weekly_day_off($weekly_day_off_by_username[$username_key])
				: $this->default_weekly_day_off();
			if (!$this->is_employee_scheduled_workday($username_key, $date_key, $user_weekly_off))
			{
				$offday_total += 1;
				continue;
			}

			$scheduled_total += 1;
			if (isset($alpha_reset_lookup[$username_key]))
			{
				continue;
			}
			$is_alpha_window_open = TRUE;
			if ($enforce_today_cutoff)
			{
				$user_shift_key = isset($shift_key_by_username[$username_key])
					? (string) $shift_key_by_username[$username_key]
					: 'pagi';
				$user_alpha_cutoff = $this->resolve_shift_alpha_cutoff_seconds($user_shift_key);
				if ($current_cutoff_seconds < $user_alpha_cutoff)
				{
					$is_alpha_window_open = FALSE;
				}
			}

			if (!$is_alpha_window_open)
			{
				if (!isset($hadir_lookup[$username_key]) && !isset($izin_lookup[$username_key]))
				{
					$pending_alpha_total += 1;
				}
				continue;
			}

			$alpha_target_total += 1;
			if ($has_day_activity && !isset($hadir_lookup[$username_key]) && !isset($izin_lookup[$username_key]))
			{
				$alpha_lookup[$username_key] = TRUE;
			}
		}

		$izin_cuti_valid = count($izin_lookup);
		$izin_cuti_unknown_count = max(0, $day_leave_count - $izin_cuti_valid);
		$alpha = count($alpha_lookup);

		return array(
			'hadir' => (int) count($hadir_lookup),
			'terlambat' => (int) count($terlambat_lookup),
			'izin_cuti' => (int) $izin_cuti,
			'alpha' => (int) $alpha,
			'employee_total' => (int) $employee_total,
			'scheduled_total' => (int) $scheduled_total,
			'offday_total' => (int) $offday_total,
			'alpha_target_total' => (int) $alpha_target_total,
			'pending_alpha_total' => (int) $pending_alpha_total,
			'izin_cuti_valid' => (int) $izin_cuti_valid,
			'izin_cuti_unknown_count' => (int) $izin_cuti_unknown_count
		);
	}

	private function build_admin_day_metric_user_sets($date_key, $metric_maps, $hour_cutoff_seconds = NULL)
	{
		$date_key = trim((string) $date_key);
		if (!$this->is_valid_date_format($date_key))
		{
			return array(
				'hadir' => array(),
				'terlambat' => array(),
				'izin_cuti' => array(),
				'alpha' => array(),
				'izin_cuti_unknown_count' => 0
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

		$hadir_lookup = array();
		foreach ($day_checkins as $username => $seconds)
		{
			$username_key = strtolower(trim((string) $username));
			if ($username_key === '')
			{
				continue;
			}
			$seconds_value = max(0, (int) $seconds);
			if ($hour_cutoff === NULL || $seconds_value <= $hour_cutoff)
			{
				$hadir_lookup[$username_key] = TRUE;
			}
		}

		$terlambat_lookup = array();
		foreach ($day_late_users as $username => $is_late)
		{
			$username_key = strtolower(trim((string) $username));
			if ($username_key === '' || !$is_late || !isset($day_checkins[$username]))
			{
				continue;
			}
			$seconds_value = max(0, (int) $day_checkins[$username]);
			if ($hour_cutoff === NULL || $seconds_value <= $hour_cutoff)
			{
				$terlambat_lookup[$username_key] = TRUE;
			}
		}

		$today_key = date('Y-m-d');
		$current_cutoff_seconds = $hour_cutoff !== NULL
			? (int) $hour_cutoff
			: $this->time_to_seconds(date('H:i:s'));
		$enforce_today_cutoff = ($date_key === $today_key);
		$employee_lookup = isset($metric_maps['employee_lookup']) && is_array($metric_maps['employee_lookup'])
			? $metric_maps['employee_lookup']
			: array();
		$weekly_day_off_by_username = isset($metric_maps['weekly_day_off_by_username']) && is_array($metric_maps['weekly_day_off_by_username'])
			? $metric_maps['weekly_day_off_by_username']
			: array();
		$shift_key_by_username = isset($metric_maps['shift_key_by_username']) && is_array($metric_maps['shift_key_by_username'])
			? $metric_maps['shift_key_by_username']
			: array();
		$alpha_reset_by_date = isset($metric_maps['alpha_reset_by_date']) && is_array($metric_maps['alpha_reset_by_date'])
			? $metric_maps['alpha_reset_by_date']
			: array();
		$alpha_reset_lookup = array();
		if (isset($alpha_reset_by_date[$date_key]) && is_array($alpha_reset_by_date[$date_key]))
		{
			$reset_users = $alpha_reset_by_date[$date_key];
			for ($reset_i = 0; $reset_i < count($reset_users); $reset_i += 1)
			{
				$reset_key = strtolower(trim((string) $reset_users[$reset_i]));
				if ($reset_key !== '')
				{
					$alpha_reset_lookup[$reset_key] = TRUE;
				}
			}
		}

		$izin_lookup = array();
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
			if (!$this->is_employee_scheduled_workday($leave_username_key, $date_key, $user_weekly_off))
			{
				continue;
			}
			if ($enforce_today_cutoff)
			{
				$user_shift_key = isset($shift_key_by_username[$leave_username_key])
					? (string) $shift_key_by_username[$leave_username_key]
					: 'pagi';
				$user_alpha_cutoff = $this->resolve_shift_alpha_cutoff_seconds($user_shift_key);
				if ($current_cutoff_seconds < $user_alpha_cutoff)
				{
					continue;
				}
			}
			$izin_lookup[$leave_username_key] = TRUE;
		}

		$has_day_activity = !empty($day_checkins) || !empty($day_checkouts) || $day_leave_count > 0;
		$alpha_lookup = array();
		if ($has_day_activity)
		{
			foreach ($employee_lookup as $username_key => $is_active)
			{
				if (!$is_active)
				{
					continue;
				}
				if (isset($alpha_reset_lookup[$username_key]))
				{
					continue;
				}
				$user_weekly_off = isset($weekly_day_off_by_username[$username_key])
					? $this->resolve_employee_weekly_day_off($weekly_day_off_by_username[$username_key])
					: $this->default_weekly_day_off();
				if (!$this->is_employee_scheduled_workday($username_key, $date_key, $user_weekly_off))
				{
					continue;
				}
				if ($enforce_today_cutoff)
				{
					$user_shift_key = isset($shift_key_by_username[$username_key])
						? (string) $shift_key_by_username[$username_key]
						: 'pagi';
					$user_alpha_cutoff = $this->resolve_shift_alpha_cutoff_seconds($user_shift_key);
					if ($current_cutoff_seconds < $user_alpha_cutoff)
					{
						continue;
					}
				}
				if (isset($hadir_lookup[$username_key]) || isset($izin_lookup[$username_key]))
				{
					continue;
				}
				$alpha_lookup[$username_key] = TRUE;
			}
		}

		$izin_unknown_count = max(0, $day_leave_count - count($izin_lookup));

		$hadir_users = array_keys($hadir_lookup);
		$terlambat_users = array_keys($terlambat_lookup);
		$izin_users = array_keys($izin_lookup);
		$alpha_users = array_keys($alpha_lookup);
		sort($hadir_users);
		sort($terlambat_users);
		sort($izin_users);
		sort($alpha_users);

		return array(
			'hadir' => $hadir_users,
			'terlambat' => $terlambat_users,
			'izin_cuti' => $izin_users,
			'alpha' => $alpha_users,
			'izin_cuti_unknown_count' => (int) $izin_unknown_count
		);
	}

	private function resolve_admin_metric_display_name($username_key, $display_name_by_username)
	{
		$key = strtolower(trim((string) $username_key));
		if ($key === '')
		{
			return '';
		}
		$name = isset($display_name_by_username[$key]) ? trim((string) $display_name_by_username[$key]) : '';
		if ($name === '')
		{
			$name = $key;
		}
		return $name;
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
				'employee_names' => array(),
				'employee_details' => array(),
				'employee_count' => 0,
				'employee_unique_count' => 0,
				'employee_unknown_count' => 0,
				'generated_at' => date('Y-m-d H:i:s')
			);
		}
		$points = array();
		$member_entries = array();
		$unknown_count_by_date = array();
		$display_name_by_username = isset($metric_maps['display_name_by_username']) && is_array($metric_maps['display_name_by_username'])
			? $metric_maps['display_name_by_username']
			: array();
		$employee_id_by_username = isset($metric_maps['employee_id_by_username']) && is_array($metric_maps['employee_id_by_username'])
			? $metric_maps['employee_id_by_username']
			: array();
		$now_ts = time();
		$today_midnight_ts = strtotime(date('Y-m-d 00:00:00', $now_ts));
		$collect_metric_entries = function ($date_key, $day_users) use (&$member_entries, $metric_key, $display_name_by_username, $employee_id_by_username) {
			if (!$this->is_valid_date_format($date_key) || !isset($day_users[$metric_key]) || !is_array($day_users[$metric_key]))
			{
				return;
			}

			$date_label = date('d-m-Y', strtotime($date_key.' 00:00:00'));
			for ($user_i = 0; $user_i < count($day_users[$metric_key]); $user_i += 1)
			{
				$user_key = strtolower(trim((string) $day_users[$metric_key][$user_i]));
				if ($user_key === '')
				{
					continue;
				}

				$display_name = $this->resolve_admin_metric_display_name($user_key, $display_name_by_username);
				if ($display_name === '')
				{
					continue;
				}
				$employee_id = isset($employee_id_by_username[$user_key])
					? trim((string) $employee_id_by_username[$user_key])
					: '-';
				if ($employee_id === '')
				{
					$employee_id = '-';
				}

				$entry_key = $user_key.'|'.$date_key;
				$member_entries[$entry_key] = array(
					'username' => $user_key,
					'employee_id' => $employee_id,
					'name' => $display_name,
					'date' => $date_key,
					'date_label' => $date_label
				);
			}
		};
		$register_unknown_count = function ($date_key, $unknown_count) use (&$unknown_count_by_date) {
			$date_text = trim((string) $date_key);
			if ($date_text === '')
			{
				return;
			}
			$count_value = max(0, (int) $unknown_count);
			if ($count_value <= 0)
			{
				return;
			}
			if (!isset($unknown_count_by_date[$date_text]) || $count_value > (int) $unknown_count_by_date[$date_text])
			{
				$unknown_count_by_date[$date_text] = $count_value;
			}
		};

		if ($range_key === '1H')
		{
			$start_day_ts = $today_midnight_ts !== FALSE ? (int) $today_midnight_ts : strtotime(date('Y-m-d 00:00:00', $now_ts));
			$current_hour = (int) date('G', $now_ts);
			$current_seconds_today = ((int) date('G', $now_ts) * 3600) + ((int) date('i', $now_ts) * 60) + (int) date('s', $now_ts);
			for ($hour_i = 0; $hour_i <= $current_hour; $hour_i += 1)
			{
				$slot_ts = $start_day_ts + ($hour_i * 3600);
				$date_key = date('Y-m-d', $slot_ts);
				$hour_cutoff = $hour_i < $current_hour
					? (($hour_i * 3600) + 3599)
					: $current_seconds_today;
				$counts = $this->build_admin_day_counts($date_key, $metric_maps, $hour_cutoff);
				$day_users = $this->build_admin_day_metric_user_sets($date_key, $metric_maps, $hour_cutoff);
				$collect_metric_entries($date_key, $day_users);
				if ($metric_key === 'izin_cuti')
				{
					$register_unknown_count(
						$date_key,
						isset($day_users['izin_cuti_unknown_count']) ? (int) $day_users['izin_cuti_unknown_count'] : 0
					);
				}
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
				$day_users = $this->build_admin_day_metric_user_sets($date_key, $metric_maps);
				$collect_metric_entries($date_key, $day_users);
				if ($metric_key === 'izin_cuti')
				{
					$register_unknown_count(
						$date_key,
						isset($day_users['izin_cuti_unknown_count']) ? (int) $day_users['izin_cuti_unknown_count'] : 0
					);
				}
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
					$day_users = $this->build_admin_day_metric_user_sets($date_key, $metric_maps);
					$collect_metric_entries($date_key, $day_users);
					if ($metric_key === 'izin_cuti')
					{
						$register_unknown_count(
							$date_key,
							isset($day_users['izin_cuti_unknown_count']) ? (int) $day_users['izin_cuti_unknown_count'] : 0
						);
					}
					$points[] = array(
						'ts' => date('c', $slot_ts),
						'label' => date('d M', $slot_ts),
						'value' => isset($counts[$metric_key]) ? (int) $counts[$metric_key] : 0
					);
				}
			}
		}
		elseif ($range_key === '1T')
		{
			$start_month_ts = strtotime(date('Y-m-01 00:00:00', $now_ts).' -11 month');
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
					$day_users = $this->build_admin_day_metric_user_sets($date_key, $metric_maps);
					$collect_metric_entries($date_key, $day_users);
					if ($metric_key === 'izin_cuti')
					{
						$register_unknown_count(
							$date_key,
							isset($day_users['izin_cuti_unknown_count']) ? (int) $day_users['izin_cuti_unknown_count'] : 0
						);
					}
				}

				$points[] = array(
					'ts' => date('c', $month_ts),
					'label' => date('M Y', $month_ts),
					'value' => (int) $month_total
				);
			}
		}
		else
		{
			$start_date_key = isset($metric_maps['min_date']) ? trim((string) $metric_maps['min_date']) : '';
			if (!$this->is_valid_date_format($start_date_key))
			{
				$start_date_key = date('Y-m-d', $now_ts);
			}
			$start_day_ts = strtotime($start_date_key.' 00:00:00');
			if ($start_day_ts === FALSE || $start_day_ts > $today_midnight_ts)
			{
				$start_day_ts = $today_midnight_ts;
			}

			for ($slot_ts = $start_day_ts; $slot_ts <= $today_midnight_ts; $slot_ts = strtotime('+1 day', $slot_ts))
			{
				$date_key = date('Y-m-d', $slot_ts);
				$counts = $this->build_admin_day_counts($date_key, $metric_maps);
				$day_users = $this->build_admin_day_metric_user_sets($date_key, $metric_maps);
				$collect_metric_entries($date_key, $day_users);
				if ($metric_key === 'izin_cuti')
				{
					$register_unknown_count(
						$date_key,
						isset($day_users['izin_cuti_unknown_count']) ? (int) $day_users['izin_cuti_unknown_count'] : 0
					);
				}
				$points[] = array(
					'ts' => date('c', $slot_ts),
					'label' => date('d M Y', $slot_ts),
					'value' => isset($counts[$metric_key]) ? (int) $counts[$metric_key] : 0
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

		$employee_details = array_values($member_entries);
		usort($employee_details, static function ($left, $right) {
			$left_date = isset($left['date']) ? (string) $left['date'] : '';
			$right_date = isset($right['date']) ? (string) $right['date'] : '';
			if ($left_date !== $right_date)
			{
				return strcmp($right_date, $left_date);
			}
			$left_name = isset($left['name']) ? (string) $left['name'] : '';
			$right_name = isset($right['name']) ? (string) $right['name'] : '';
			return strcasecmp($left_name, $right_name);
		});

		$employee_names_lookup = array();
		for ($entry_i = 0; $entry_i < count($employee_details); $entry_i += 1)
		{
			$display_name = isset($employee_details[$entry_i]['name']) ? trim((string) $employee_details[$entry_i]['name']) : '';
			if ($display_name === '')
			{
				continue;
			}
			$employee_names_lookup[strtolower($display_name)] = $display_name;
		}
		$employee_names = array_values($employee_names_lookup);
		usort($employee_names, static function ($left, $right) {
			return strcasecmp((string) $left, (string) $right);
		});

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
			'employee_names' => $employee_names,
			'employee_details' => $employee_details,
			'employee_count' => count($employee_details),
			'employee_unique_count' => count($employee_names),
			'employee_unknown_count' => array_sum($unknown_count_by_date),
			'generated_at' => date('Y-m-d H:i:s')
		);
	}

	private function attendance_file_path()
	{
		return APPPATH.'cache/attendance_records.json';
	}

	private function load_attendance_records()
	{
		if ($this->attendance_records_cache_loaded === TRUE && is_array($this->attendance_records_cache))
		{
			return $this->attendance_records_cache;
		}

		$finalize_rows = function ($rows, $persist_if_photo_changed = TRUE) {
			$normalized_rows = $this->normalize_attendance_record_versions(
				array_values(is_array($rows) ? $rows : array())
			);
			$migration_result = $this->migrate_attendance_record_photo_paths($normalized_rows);
			$final_rows = isset($migration_result['records']) && is_array($migration_result['records'])
				? array_values($migration_result['records'])
				: $normalized_rows;
			$photo_changed = isset($migration_result['changed']) && $migration_result['changed'] === TRUE;

			$this->attendance_records_cache = $final_rows;
			$this->attendance_records_cache_loaded = TRUE;

			if ($photo_changed && $persist_if_photo_changed)
			{
				$this->save_attendance_records($final_rows);
				if ($this->attendance_records_cache_loaded === TRUE && is_array($this->attendance_records_cache))
				{
					return $this->attendance_records_cache;
				}
			}

			return $final_rows;
		};

		$file_path = $this->attendance_file_path();
		$primary_rows = NULL;
		if (function_exists('absen_data_store_load_value'))
		{
			$rows = absen_data_store_load_value('attendance_records', NULL, $file_path);
			if (is_array($rows))
			{
				$primary_rows = array_values($rows);
				if (!empty($primary_rows))
				{
					return $finalize_rows($primary_rows, TRUE);
				}
			}
		}

		if (!is_file($file_path))
		{
			$file_rows = array();
		}
		else
		{
			$content = @file_get_contents($file_path);
			if ($content === FALSE || trim($content) === '')
			{
				$file_rows = array();
			}
			else
			{
				// Handle UTF-8 BOM that can make json_decode fail on Windows-generated files.
				if (substr($content, 0, 3) === "\xEF\xBB\xBF")
				{
					$content = substr($content, 3);
				}

				$data = json_decode($content, TRUE);
				$file_rows = is_array($data) ? array_values($data) : array();
			}
		}

		if (!empty($file_rows))
		{
			return $finalize_rows($file_rows, TRUE);
		}

		$mirror_rows = array();
		if (function_exists('attendance_mirror_load_all'))
		{
			$mirror_error = '';
			$loaded_mirror_rows = attendance_mirror_load_all($mirror_error);
			if (is_array($loaded_mirror_rows) && !empty($loaded_mirror_rows))
			{
				$mirror_rows = array_values($loaded_mirror_rows);
			}
			if ($mirror_error !== '')
			{
				log_message('error', '[AttendanceMirror] '.$mirror_error);
			}
		}

		if (!empty($mirror_rows))
		{
			$normalized_mirror = $finalize_rows($mirror_rows, TRUE);
			if (function_exists('absen_data_store_save_value'))
			{
				absen_data_store_save_value('attendance_records', $normalized_mirror, $file_path);
			}
			return $normalized_mirror;
		}

		if (is_array($primary_rows))
		{
			return $finalize_rows($primary_rows, TRUE);
		}

		$this->attendance_records_cache = array();
		$this->attendance_records_cache_loaded = TRUE;
		return $this->attendance_records_cache;
	}

	private function save_attendance_records($records)
	{
		$file_path = $this->attendance_file_path();
		$normalized = $this->normalize_attendance_record_versions(array_values(is_array($records) ? $records : array()));
		$migration_result = $this->migrate_attendance_record_photo_paths($normalized);
		if (isset($migration_result['records']) && is_array($migration_result['records']))
		{
			$normalized = array_values($migration_result['records']);
		}
		$saved_primary = FALSE;
		if (function_exists('absen_data_store_save_value'))
		{
			$saved_primary = absen_data_store_save_value('attendance_records', $normalized, $file_path) ? TRUE : FALSE;
		}

		if (!$saved_primary)
		{
			$directory = dirname($file_path);
			if (!is_dir($directory))
			{
				@mkdir($directory, 0755, TRUE);
			}
			$payload = json_encode($normalized, JSON_PRETTY_PRINT);
			$saved_primary = @file_put_contents($file_path, $payload) !== FALSE;
		}

		if (!$saved_primary)
		{
			log_message('error', '[AttendanceStore] Gagal menyimpan attendance_records ke storage utama.');
		}

		if (function_exists('attendance_mirror_save_by_date'))
		{
			$mirror_error = '';
			$mirror_saved = attendance_mirror_save_by_date($normalized, TRUE, $mirror_error);
			if (!$mirror_saved || $mirror_error !== '')
			{
				log_message('error', '[AttendanceMirror] '.($mirror_error !== '' ? $mirror_error : 'Gagal sinkron mirror per tanggal.'));
			}
		}

		$this->attendance_records_cache = $normalized;
		$this->attendance_records_cache_loaded = TRUE;
	}

	private function normalize_attendance_record_versions($records)
	{
		$rows = is_array($records) ? array_values($records) : array();
		$normalized_rows = array();
		for ($i = 0; $i < count($rows); $i += 1)
		{
			if (!isset($rows[$i]) || !is_array($rows[$i]))
			{
				$rows[$i] = array();
			}
			if ($this->is_attendance_sheet_snapshot_row($rows[$i]))
			{
				continue;
			}
			$current_version = isset($rows[$i]['record_version']) ? (int) $rows[$i]['record_version'] : 1;
			if ($current_version <= 0)
			{
				$current_version = 1;
			}
			$rows[$i]['record_version'] = $current_version;
			$normalized_rows[] = $rows[$i];
		}

		return $normalized_rows;
	}

	private function is_attendance_sheet_snapshot_row($row)
	{
		if (!is_array($row))
		{
			return FALSE;
		}

		$row_source = strtolower(trim((string) (isset($row['sheet_sync_source']) ? $row['sheet_sync_source'] : '')));
		if ($row_source !== 'google_sheet_attendance')
		{
			return FALSE;
		}

		if (isset($row['sheet_summary_only']) && (int) $row['sheet_summary_only'] === 1)
		{
			return TRUE;
		}

		$row_sheet_date_text = trim((string) (isset($row['sheet_tanggal_absen']) ? $row['sheet_tanggal_absen'] : ''));
		if ($row_sheet_date_text === '')
		{
			return FALSE;
		}

		$row_sheet_date_matches = array();
		preg_match_all('/\d{4}-\d{2}-\d{2}/', $row_sheet_date_text, $row_sheet_date_matches);
		$row_sheet_dates = isset($row_sheet_date_matches[0]) && is_array($row_sheet_date_matches[0])
			? array_values(array_unique($row_sheet_date_matches[0]))
			: array();
		$is_range_row = stripos($row_sheet_date_text, 's/d') !== FALSE || count($row_sheet_dates) > 1;
		if (!$is_range_row)
		{
			return FALSE;
		}

		// Range date dari sheet bisa juga berisi data absensi riil (jam/foto),
		// jadi hanya anggap snapshot jika memang murni baris rekap.
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

		$has_presence_data =
			$this->has_real_attendance_time(isset($row['check_in_time']) ? $row['check_in_time'] : '') ||
			$this->has_real_attendance_time(isset($row['check_out_time']) ? $row['check_out_time'] : '') ||
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

	private function day_off_swap_file_path()
	{
		return APPPATH.'cache/day_off_swaps.json';
	}

	private function day_off_swap_book($force_refresh = FALSE)
	{
		static $swap_cache = NULL;
		if (!$force_refresh && is_array($swap_cache))
		{
			return $swap_cache;
		}

		$file_path = $this->day_off_swap_file_path();
		$rows = NULL;
		if (function_exists('absen_data_store_load_value'))
		{
			$rows = absen_data_store_load_value('day_off_swaps', NULL, $file_path);
		}
		if (!is_array($rows))
		{
			if (is_file($file_path))
			{
				$content = @file_get_contents($file_path);
				if ($content !== FALSE && trim((string) $content) !== '')
				{
					if (substr($content, 0, 3) === "\xEF\xBB\xBF")
					{
						$content = substr($content, 3);
					}
					$decoded = json_decode((string) $content, TRUE);
					$rows = is_array($decoded) ? $decoded : array();
				}
				else
				{
					$rows = array();
				}
			}
			else
			{
				$rows = array();
			}
		}

		$swap_cache = $this->normalize_day_off_swap_rows($rows);
		return $swap_cache;
	}

	private function normalize_day_off_swap_rows($rows)
	{
		$list = is_array($rows) ? array_values($rows) : array();
		$normalized = array();
		for ($i = 0; $i < count($list); $i += 1)
		{
			$row = isset($list[$i]) && is_array($list[$i]) ? $list[$i] : array();
			$username_key = $this->normalize_username_key(isset($row['username']) ? (string) $row['username'] : '');
			if ($username_key === '')
			{
				continue;
			}

			$workday_date = isset($row['workday_date']) ? trim((string) $row['workday_date']) : '';
			if ($workday_date === '' && isset($row['work_date']))
			{
				$workday_date = trim((string) $row['work_date']);
			}
			if (!$this->is_valid_date_format($workday_date))
			{
				continue;
			}

			$offday_date = isset($row['offday_date']) ? trim((string) $row['offday_date']) : '';
			if ($offday_date === '' && isset($row['off_date']))
			{
				$offday_date = trim((string) $row['off_date']);
			}
			if (!$this->is_valid_date_format($offday_date))
			{
				continue;
			}

			if ($workday_date === $offday_date)
			{
				continue;
			}

			$swap_id = isset($row['swap_id']) ? trim((string) $row['swap_id']) : '';
			if ($swap_id === '')
			{
				$swap_id = 'swap_'.substr(sha1($username_key.'|'.$workday_date.'|'.$offday_date.'|'.$i), 0, 20);
			}

			$created_at = isset($row['created_at']) ? trim((string) $row['created_at']) : '';
			$created_ts = $created_at !== '' ? strtotime($created_at) : FALSE;
			if ($created_ts === FALSE)
			{
				$created_at = date('Y-m-d H:i:s');
			}

			$created_by = $this->normalize_username_key(isset($row['created_by']) ? (string) $row['created_by'] : '');
			if ($created_by === '')
			{
				$created_by = 'admin';
			}

			$note = isset($row['note']) ? trim((string) $row['note']) : '';
			if (strlen($note) > 200)
			{
				$note = substr($note, 0, 200);
			}

			$normalized[] = array(
				'swap_id' => $swap_id,
				'username' => $username_key,
				'workday_date' => $workday_date,
				'offday_date' => $offday_date,
				'note' => $note,
				'created_by' => $created_by,
				'created_at' => $created_at
			);
		}

		usort($normalized, function ($a, $b) {
			$left_created = isset($a['created_at']) ? strtotime((string) $a['created_at']) : FALSE;
			$right_created = isset($b['created_at']) ? strtotime((string) $b['created_at']) : FALSE;
			$left_ts = $left_created === FALSE ? 0 : (int) $left_created;
			$right_ts = $right_created === FALSE ? 0 : (int) $right_created;
			if ($left_ts !== $right_ts)
			{
				return $left_ts <=> $right_ts;
			}

			$left_id = isset($a['swap_id']) ? (string) $a['swap_id'] : '';
			$right_id = isset($b['swap_id']) ? (string) $b['swap_id'] : '';
			return strcmp($left_id, $right_id);
		});

		return $normalized;
	}

	private function save_day_off_swap_book($rows)
	{
		$normalized = $this->normalize_day_off_swap_rows($rows);
		$file_path = $this->day_off_swap_file_path();
		$saved = FALSE;
		if (function_exists('absen_data_store_save_value'))
		{
			$saved = absen_data_store_save_value('day_off_swaps', $normalized, $file_path) ? TRUE : FALSE;
		}

		if (!$saved)
		{
			$directory = dirname($file_path);
			if (!is_dir($directory))
			{
				@mkdir($directory, 0755, TRUE);
			}
			$payload = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
			$saved = @file_put_contents($file_path, $payload) !== FALSE;
		}

		$this->day_off_swap_book(TRUE);
		return $saved;
	}

	private function day_off_swap_request_file_path()
	{
		return APPPATH.'cache/day_off_swap_requests.json';
	}

	private function day_off_swap_request_book($force_refresh = FALSE)
	{
		static $request_cache = NULL;
		if (!$force_refresh && is_array($request_cache))
		{
			return $request_cache;
		}

		$file_path = $this->day_off_swap_request_file_path();
		$rows = NULL;
		if (function_exists('absen_data_store_load_value'))
		{
			$rows = absen_data_store_load_value('day_off_swap_requests', NULL, $file_path);
		}
		if (!is_array($rows))
		{
			if (is_file($file_path))
			{
				$content = @file_get_contents($file_path);
				if ($content !== FALSE && trim((string) $content) !== '')
				{
					if (substr($content, 0, 3) === "\xEF\xBB\xBF")
					{
						$content = substr($content, 3);
					}
					$decoded = json_decode((string) $content, TRUE);
					$rows = is_array($decoded) ? $decoded : array();
				}
				else
				{
					$rows = array();
				}
			}
			else
			{
				$rows = array();
			}
		}

		$request_cache = $this->normalize_day_off_swap_request_rows($rows);
		return $request_cache;
	}

	private function normalize_day_off_swap_request_rows($rows)
	{
		$list = is_array($rows) ? array_values($rows) : array();
		$normalized = array();
		for ($i = 0; $i < count($list); $i += 1)
		{
			$row = isset($list[$i]) && is_array($list[$i]) ? $list[$i] : array();
			$username_key = $this->normalize_username_key(isset($row['username']) ? (string) $row['username'] : '');
			if ($username_key === '')
			{
				continue;
			}

			$workday_date = isset($row['workday_date']) ? trim((string) $row['workday_date']) : '';
			if ($workday_date === '' && isset($row['work_date']))
			{
				$workday_date = trim((string) $row['work_date']);
			}
			if (!$this->is_valid_date_format($workday_date))
			{
				continue;
			}

			$offday_date = isset($row['offday_date']) ? trim((string) $row['offday_date']) : '';
			if ($offday_date === '' && isset($row['off_date']))
			{
				$offday_date = trim((string) $row['off_date']);
			}
			if (!$this->is_valid_date_format($offday_date))
			{
				continue;
			}
			if ($workday_date === $offday_date)
			{
				continue;
			}

			$request_id = isset($row['request_id']) ? trim((string) $row['request_id']) : '';
			if ($request_id === '' && isset($row['id']))
			{
				$request_id = trim((string) $row['id']);
			}
			if ($request_id === '')
			{
				$request_id = 'swap_req_'.substr(sha1($username_key.'|'.$workday_date.'|'.$offday_date.'|'.$i), 0, 20);
			}

			$requested_at = isset($row['requested_at']) ? trim((string) $row['requested_at']) : '';
			if ($requested_at === '' && isset($row['created_at']))
			{
				$requested_at = trim((string) $row['created_at']);
			}
			$requested_ts = $requested_at !== '' ? strtotime($requested_at) : FALSE;
			if ($requested_ts === FALSE)
			{
				$requested_at = date('Y-m-d H:i:s');
			}

			$requested_by = $this->normalize_username_key(isset($row['requested_by']) ? (string) $row['requested_by'] : '');
			if ($requested_by === '')
			{
				$requested_by = $username_key;
			}

			$status_raw = strtolower(trim((string) (isset($row['status']) ? $row['status'] : 'pending')));
			$status = 'pending';
			if (in_array($status_raw, array('approved', 'disetujui', 'diterima'), TRUE))
			{
				$status = 'approved';
			}
			elseif (in_array($status_raw, array('rejected', 'ditolak'), TRUE))
			{
				$status = 'rejected';
			}

			$reviewed_by = $this->normalize_username_key(isset($row['reviewed_by']) ? (string) $row['reviewed_by'] : '');
			if ($reviewed_by === '' && isset($row['approved_by']))
			{
				$reviewed_by = $this->normalize_username_key((string) $row['approved_by']);
			}

			$reviewed_at = isset($row['reviewed_at']) ? trim((string) $row['reviewed_at']) : '';
			if ($reviewed_at === '' && isset($row['approved_at']))
			{
				$reviewed_at = trim((string) $row['approved_at']);
			}
			$reviewed_ts = $reviewed_at !== '' ? strtotime($reviewed_at) : FALSE;
			if ($reviewed_ts === FALSE)
			{
				$reviewed_at = '';
			}

			$review_note = isset($row['review_note']) ? trim((string) $row['review_note']) : '';
			if ($review_note === '' && isset($row['decision_note']))
			{
				$review_note = trim((string) $row['decision_note']);
			}
			if ($review_note === '' && isset($row['status_note']))
			{
				$review_note = trim((string) $row['status_note']);
			}
			if (strlen($review_note) > 200)
			{
				$review_note = substr($review_note, 0, 200);
			}

			$note = isset($row['note']) ? trim((string) $row['note']) : '';
			if ($note !== '')
			{
				$note = preg_replace('/\s+/', ' ', $note);
				if ($note === NULL)
				{
					$note = '';
				}
			}
			if (strlen($note) > 200)
			{
				$note = substr($note, 0, 200);
			}

			if ($status === 'pending')
			{
				$reviewed_by = '';
				$reviewed_at = '';
				$review_note = '';
			}

			$branch_value = $this->resolve_employee_branch(isset($row['branch']) ? (string) $row['branch'] : '');
			if ($branch_value === '')
			{
				$profile = $this->get_employee_profile($username_key);
				$branch_value = $this->resolve_employee_branch(isset($profile['branch']) ? (string) $profile['branch'] : '');
				if ($branch_value === '')
				{
					$branch_value = $this->default_employee_branch();
				}
			}

			$normalized[] = array(
				'request_id' => $request_id,
				'username' => $username_key,
				'branch' => $branch_value,
				'workday_date' => $workday_date,
				'offday_date' => $offday_date,
				'note' => $note,
				'status' => $status,
				'requested_by' => $requested_by,
				'requested_at' => $requested_at,
				'reviewed_by' => $reviewed_by,
				'reviewed_at' => $reviewed_at,
				'review_note' => $review_note,
				'swap_id' => isset($row['swap_id']) ? trim((string) $row['swap_id']) : ''
			);
		}

		usort($normalized, function ($a, $b) {
			$left_requested = isset($a['requested_at']) ? strtotime((string) $a['requested_at']) : FALSE;
			$right_requested = isset($b['requested_at']) ? strtotime((string) $b['requested_at']) : FALSE;
			$left_ts = $left_requested === FALSE ? 0 : (int) $left_requested;
			$right_ts = $right_requested === FALSE ? 0 : (int) $right_requested;
			if ($left_ts !== $right_ts)
			{
				return $left_ts <=> $right_ts;
			}

			$left_id = isset($a['request_id']) ? (string) $a['request_id'] : '';
			$right_id = isset($b['request_id']) ? (string) $b['request_id'] : '';
			return strcmp($left_id, $right_id);
		});

		return $normalized;
	}

	private function save_day_off_swap_request_book($rows)
	{
		$normalized = $this->normalize_day_off_swap_request_rows($rows);
		$file_path = $this->day_off_swap_request_file_path();
		$saved = FALSE;
		if (function_exists('absen_data_store_save_value'))
		{
			$saved = absen_data_store_save_value('day_off_swap_requests', $normalized, $file_path) ? TRUE : FALSE;
		}

		if (!$saved)
		{
			$directory = dirname($file_path);
			if (!is_dir($directory))
			{
				@mkdir($directory, 0755, TRUE);
			}
			$payload = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
			$saved = @file_put_contents($file_path, $payload) !== FALSE;
		}

		$this->day_off_swap_request_book(TRUE);
		return $saved;
	}

	private function generate_day_off_swap_request_id($username_key = '', $workday_date = '', $offday_date = '')
	{
		$seed = strtolower(trim((string) $username_key)).'|'.trim((string) $workday_date).'|'.trim((string) $offday_date).'|'.microtime(TRUE).'|'.mt_rand(1000, 999999);
		return 'swap_req_'.substr(sha1($seed), 0, 20);
	}

	private function day_off_swap_request_status_label($status)
	{
		$status_key = strtolower(trim((string) $status));
		if ($status_key === 'approved')
		{
			return 'Disetujui';
		}
		if ($status_key === 'rejected')
		{
			return 'Ditolak';
		}
		return 'Menunggu';
	}

	private function day_off_swap_request_status_badge_class($status)
	{
		$status_key = strtolower(trim((string) $status));
		if ($status_key === 'approved')
		{
			return 'diterima';
		}
		if ($status_key === 'rejected')
		{
			return 'ditolak';
		}
		return 'menunggu';
	}

	private function is_day_off_swap_request_in_actor_scope($request_row)
	{
		$row = is_array($request_row) ? $request_row : array();
		if (!$this->is_branch_scoped_admin())
		{
			return TRUE;
		}

		$request_branch = $this->resolve_employee_branch(isset($row['branch']) ? (string) $row['branch'] : '');
		$scope_branch = $this->current_actor_branch();
		if ($scope_branch === '')
		{
			return FALSE;
		}
		if ($request_branch !== '')
		{
			return strcasecmp($request_branch, $scope_branch) === 0;
		}

		$username_key = $this->normalize_username_key(isset($row['username']) ? (string) $row['username'] : '');
		if ($username_key !== '')
		{
			return $this->is_username_in_actor_scope($username_key);
		}

		return FALSE;
	}

	private function build_day_off_swap_request_management_rows($status_filter = '')
	{
		if (!$this->can_process_day_off_swap_requests_feature())
		{
			return array();
		}

		$filter = strtolower(trim((string) $status_filter));
		if (!in_array($filter, array('', 'pending', 'approved', 'rejected'), TRUE))
		{
			$filter = '';
		}

		$request_rows = $this->day_off_swap_request_book();
		if (empty($request_rows))
		{
			return array();
		}

		$id_book = $this->employee_id_book();
		$profiles = $this->employee_profile_book();
		$result = array();
		for ($i = count($request_rows) - 1; $i >= 0; $i -= 1)
		{
			$row = isset($request_rows[$i]) && is_array($request_rows[$i]) ? $request_rows[$i] : array();
			if (!$this->is_day_off_swap_request_in_actor_scope($row))
			{
				continue;
			}

			$status_key = strtolower(trim((string) (isset($row['status']) ? $row['status'] : 'pending')));
			if ($status_key !== 'approved' && $status_key !== 'rejected')
			{
				$status_key = 'pending';
			}
			if ($filter !== '' && $status_key !== $filter)
			{
				continue;
			}

			$username_key = $this->normalize_username_key(isset($row['username']) ? (string) $row['username'] : '');
			if ($username_key === '')
			{
				continue;
			}

			$profile = isset($profiles[$username_key]) && is_array($profiles[$username_key])
				? $profiles[$username_key]
				: $this->get_employee_profile($username_key);
			$display_name = isset($profile['display_name']) && trim((string) $profile['display_name']) !== ''
				? (string) $profile['display_name']
				: $username_key;
			$profile_photo = isset($profile['profile_photo']) && trim((string) $profile['profile_photo']) !== ''
				? (string) $profile['profile_photo']
				: $this->default_employee_profile_photo();
			$job_title = isset($profile['job_title']) && trim((string) $profile['job_title']) !== ''
				? (string) $profile['job_title']
				: $this->default_employee_job_title();
			$branch = $this->resolve_employee_branch(isset($row['branch']) ? (string) $row['branch'] : '');
			if ($branch === '')
			{
				$branch = $this->resolve_employee_branch(isset($profile['branch']) ? (string) $profile['branch'] : '');
			}
			if ($branch === '')
			{
				$branch = $this->default_employee_branch();
			}

			$workday_date = isset($row['workday_date']) ? trim((string) $row['workday_date']) : '';
			$offday_date = isset($row['offday_date']) ? trim((string) $row['offday_date']) : '';
			$result[] = array(
				'request_id' => isset($row['request_id']) ? (string) $row['request_id'] : '',
				'username' => $username_key,
				'employee_id' => $this->resolve_employee_id_from_book($username_key, $id_book),
				'display_name' => $display_name,
				'profile_photo' => $profile_photo,
				'job_title' => $job_title,
				'branch' => $branch,
				'workday_date' => $workday_date,
				'workday_label' => $this->format_user_dashboard_date_label($workday_date),
				'offday_date' => $offday_date,
				'offday_label' => $this->format_user_dashboard_date_label($offday_date),
				'note' => isset($row['note']) ? (string) $row['note'] : '',
				'status' => $status_key,
				'status_label' => $this->day_off_swap_request_status_label($status_key),
				'status_badge_class' => $this->day_off_swap_request_status_badge_class($status_key),
				'requested_by' => isset($row['requested_by']) ? (string) $row['requested_by'] : '',
				'requested_at' => isset($row['requested_at']) ? (string) $row['requested_at'] : '',
				'reviewed_by' => isset($row['reviewed_by']) ? (string) $row['reviewed_by'] : '',
				'reviewed_at' => isset($row['reviewed_at']) ? (string) $row['reviewed_at'] : '',
				'review_note' => isset($row['review_note']) ? (string) $row['review_note'] : '',
				'swap_id' => isset($row['swap_id']) ? (string) $row['swap_id'] : ''
			);
		}

		return $result;
	}

	private function build_user_day_off_swap_request_rows($username, $limit = 10)
	{
		$username_key = $this->normalize_username_key($username);
		if ($username_key === '')
		{
			return array();
		}

		$request_rows = $this->day_off_swap_request_book();
		if (empty($request_rows))
		{
			return array();
		}

		$max_items = (int) $limit;
		if ($max_items <= 0)
		{
			$max_items = 10;
		}

		$result = array();
		for ($i = count($request_rows) - 1; $i >= 0; $i -= 1)
		{
			$row = isset($request_rows[$i]) && is_array($request_rows[$i]) ? $request_rows[$i] : array();
			$row_username = $this->normalize_username_key(isset($row['username']) ? (string) $row['username'] : '');
			if ($row_username !== $username_key)
			{
				continue;
			}

			$status_key = strtolower(trim((string) (isset($row['status']) ? $row['status'] : 'pending')));
			if ($status_key !== 'approved' && $status_key !== 'rejected')
			{
				$status_key = 'pending';
			}
			$workday_date = isset($row['workday_date']) ? trim((string) $row['workday_date']) : '';
			$offday_date = isset($row['offday_date']) ? trim((string) $row['offday_date']) : '';
			$result[] = array(
				'request_id' => isset($row['request_id']) ? (string) $row['request_id'] : '',
				'workday_date' => $workday_date,
				'workday_label' => $this->format_user_dashboard_date_label($workday_date),
				'offday_date' => $offday_date,
				'offday_label' => $this->format_user_dashboard_date_label($offday_date),
				'note' => isset($row['note']) ? (string) $row['note'] : '',
				'status' => $status_key,
				'status_label' => $this->day_off_swap_request_status_label($status_key),
				'status_badge_class' => $this->day_off_swap_request_status_badge_class($status_key),
				'requested_at' => isset($row['requested_at']) ? (string) $row['requested_at'] : '',
				'reviewed_at' => isset($row['reviewed_at']) ? (string) $row['reviewed_at'] : '',
				'review_note' => isset($row['review_note']) ? (string) $row['review_note'] : ''
			);
			if (count($result) >= $max_items)
			{
				break;
			}
		}

		return $result;
	}

	private function find_conflicting_day_off_swap_date($username_key, $workday_date, $offday_date, $exclude_swap_id = '')
	{
		$username_value = $this->normalize_username_key($username_key);
		$exclude_id = trim((string) $exclude_swap_id);
		if ($username_value === '' || !$this->is_valid_date_format($workday_date) || !$this->is_valid_date_format($offday_date))
		{
			return '';
		}

		$swap_rows = $this->day_off_swap_book(TRUE);
		for ($i = 0; $i < count($swap_rows); $i += 1)
		{
			$row = isset($swap_rows[$i]) && is_array($swap_rows[$i]) ? $swap_rows[$i] : array();
			$row_swap_id = isset($row['swap_id']) ? trim((string) $row['swap_id']) : '';
			if ($exclude_id !== '' && $row_swap_id === $exclude_id)
			{
				continue;
			}

			$row_username = $this->normalize_username_key(isset($row['username']) ? (string) $row['username'] : '');
			if ($row_username !== $username_value)
			{
				continue;
			}

			$row_workday_date = isset($row['workday_date']) ? trim((string) $row['workday_date']) : '';
			$row_offday_date = isset($row['offday_date']) ? trim((string) $row['offday_date']) : '';
			if (
				$workday_date === $row_workday_date ||
				$workday_date === $row_offday_date ||
				$offday_date === $row_workday_date ||
				$offday_date === $row_offday_date
			)
			{
				return $workday_date === $row_workday_date || $workday_date === $row_offday_date
					? $workday_date
					: $offday_date;
			}
		}

		return '';
	}

	private function find_conflicting_pending_day_off_swap_request_date($username_key, $workday_date, $offday_date, $exclude_request_id = '')
	{
		$username_value = $this->normalize_username_key($username_key);
		$exclude_id = trim((string) $exclude_request_id);
		if ($username_value === '' || !$this->is_valid_date_format($workday_date) || !$this->is_valid_date_format($offday_date))
		{
			return '';
		}

		$request_rows = $this->day_off_swap_request_book(TRUE);
		for ($i = 0; $i < count($request_rows); $i += 1)
		{
			$row = isset($request_rows[$i]) && is_array($request_rows[$i]) ? $request_rows[$i] : array();
			$row_request_id = isset($row['request_id']) ? trim((string) $row['request_id']) : '';
			if ($exclude_id !== '' && $row_request_id === $exclude_id)
			{
				continue;
			}

			$row_status = strtolower(trim((string) (isset($row['status']) ? $row['status'] : 'pending')));
			if ($row_status !== 'pending')
			{
				continue;
			}

			$row_username = $this->normalize_username_key(isset($row['username']) ? (string) $row['username'] : '');
			if ($row_username !== $username_value)
			{
				continue;
			}

			$row_workday_date = isset($row['workday_date']) ? trim((string) $row['workday_date']) : '';
			$row_offday_date = isset($row['offday_date']) ? trim((string) $row['offday_date']) : '';
			if (
				$workday_date === $row_workday_date ||
				$workday_date === $row_offday_date ||
				$offday_date === $row_workday_date ||
				$offday_date === $row_offday_date
			)
			{
				return $workday_date === $row_workday_date || $workday_date === $row_offday_date
					? $workday_date
					: $offday_date;
			}
		}

		return '';
	}

	private function validate_day_off_swap_candidate($username_key, $workday_date, $offday_date, $exclude_swap_id = '', $exclude_request_id = '')
	{
		$username_value = $this->normalize_username_key($username_key);
		$workday_value = trim((string) $workday_date);
		$offday_value = trim((string) $offday_date);
		if ($username_value === '')
		{
			return array('success' => FALSE, 'message' => 'Akun karyawan tidak valid.');
		}
		if (!$this->is_valid_date_format($workday_value) || !$this->is_valid_date_format($offday_value))
		{
			return array('success' => FALSE, 'message' => 'Format tanggal tukar libur tidak valid. Gunakan YYYY-MM-DD.');
		}
		if ($workday_value === $offday_value)
		{
			return array('success' => FALSE, 'message' => 'Tanggal kerja pengganti dan tanggal libur pengganti tidak boleh sama.');
		}

		$account_book = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (!isset($account_book[$username_value]) || !is_array($account_book[$username_value]))
		{
			return array('success' => FALSE, 'message' => 'Akun karyawan tidak ditemukan.');
		}
		$account_row = $account_book[$username_value];
		$role = strtolower(trim((string) (isset($account_row['role']) ? $account_row['role'] : 'user')));
		if ($role !== 'user')
		{
			return array('success' => FALSE, 'message' => 'Fitur tukar hari libur hanya untuk akun karyawan.');
		}

		$profile_weekly_day_off = isset($account_row['weekly_day_off'])
			? $this->resolve_employee_weekly_day_off($account_row['weekly_day_off'])
			: $this->default_weekly_day_off();
		$is_workday_date_regular_workday = $this->is_employee_regular_workday_without_swap($username_value, $workday_value, $profile_weekly_day_off);
		if ($is_workday_date_regular_workday)
		{
			return array(
				'success' => FALSE,
				'message' => 'Tanggal '.$this->format_user_dashboard_date_label($workday_value).' bukan hari libur normal akun '.$username_value.'.'
			);
		}

		$is_offday_date_regular_workday = $this->is_employee_regular_workday_without_swap($username_value, $offday_value, $profile_weekly_day_off);
		if (!$is_offday_date_regular_workday)
		{
			return array(
				'success' => FALSE,
				'message' => 'Tanggal '.$this->format_user_dashboard_date_label($offday_value).' bukan hari kerja normal akun '.$username_value.'.'
			);
		}

		if ($this->has_employee_attendance_record_on_date($username_value, $offday_value))
		{
			return array(
				'success' => FALSE,
				'message' => 'Tanggal '.$this->format_user_dashboard_date_label($offday_value).' sudah memiliki data absensi. Hapus data absensi tanggal tersebut dulu sebelum dijadikan libur.'
			);
		}

		$conflict_date = $this->find_conflicting_day_off_swap_date($username_value, $workday_value, $offday_value, $exclude_swap_id);
		if ($conflict_date !== '')
		{
			return array(
				'success' => FALSE,
				'message' => 'Akun '.$username_value.' sudah punya tukar hari libur aktif yang memakai tanggal '.$this->format_user_dashboard_date_label($conflict_date).'.'
			);
		}

		$request_conflict_date = $this->find_conflicting_pending_day_off_swap_request_date($username_value, $workday_value, $offday_value, $exclude_request_id);
		if ($request_conflict_date !== '')
		{
			return array(
				'success' => FALSE,
				'message' => 'Akun '.$username_value.' sudah punya pengajuan tukar hari libur menunggu pada tanggal '.$this->format_user_dashboard_date_label($request_conflict_date).'.'
			);
		}

		$profile = $this->get_employee_profile($username_value);
		$branch = $this->resolve_employee_branch(isset($profile['branch']) ? (string) $profile['branch'] : '');
		if ($branch === '')
		{
			$branch = $this->default_employee_branch();
		}
		$display_name = isset($profile['display_name']) && trim((string) $profile['display_name']) !== ''
			? trim((string) $profile['display_name'])
			: $username_value;

		return array(
			'success' => TRUE,
			'username' => $username_value,
			'workday_date' => $workday_value,
			'offday_date' => $offday_value,
			'branch' => $branch,
			'display_name' => $display_name
		);
	}

	private function append_day_off_swap_entry($username_key, $workday_date, $offday_date, $swap_note, $created_by, &$error_message = '')
	{
		$error_message = '';
		$username_value = $this->normalize_username_key($username_key);
		$workday_value = trim((string) $workday_date);
		$offday_value = trim((string) $offday_date);
		if ($username_value === '' || !$this->is_valid_date_format($workday_value) || !$this->is_valid_date_format($offday_value))
		{
			$error_message = 'Data swap tidak valid.';
			return array('success' => FALSE);
		}

		$note = trim((string) $swap_note);
		if ($note !== '')
		{
			$note = preg_replace('/\s+/', ' ', $note);
			if ($note === NULL)
			{
				$note = '';
			}
		}
		if (strlen($note) > 200)
		{
			$note = substr($note, 0, 200);
		}

		$conflict_date = $this->find_conflicting_day_off_swap_date($username_value, $workday_value, $offday_value);
		if ($conflict_date !== '')
		{
			$error_message = 'Akun '.$username_value.' sudah punya tukar hari libur aktif pada tanggal '.$this->format_user_dashboard_date_label($conflict_date).'.';
			return array('success' => FALSE);
		}
		if ($this->has_employee_attendance_record_on_date($username_value, $offday_value))
		{
			$error_message = 'Tanggal '.$this->format_user_dashboard_date_label($offday_value).' sudah memiliki data absensi. Pengajuan tidak bisa disetujui.';
			return array('success' => FALSE);
		}

		$swap_id = $this->generate_day_off_swap_id($username_value, $workday_value, $offday_value);
		$swap_rows = $this->day_off_swap_book(TRUE);
		$swap_rows[] = array(
			'swap_id' => $swap_id,
			'username' => $username_value,
			'workday_date' => $workday_value,
			'offday_date' => $offday_value,
			'note' => $note,
			'created_by' => $this->normalize_username_key($created_by) !== '' ? $this->normalize_username_key($created_by) : 'admin',
			'created_at' => date('Y-m-d H:i:s')
		);
		$saved_swap = $this->save_day_off_swap_book($swap_rows);
		if (!$saved_swap)
		{
			$error_message = 'Gagal menyimpan data tukar hari libur.';
			return array('success' => FALSE);
		}

		return array(
			'success' => TRUE,
			'swap_id' => $swap_id,
			'note' => $note
		);
	}

	private function generate_day_off_swap_id($username_key = '', $workday_date = '', $offday_date = '')
	{
		$seed = strtolower(trim((string) $username_key)).'|'.trim((string) $workday_date).'|'.trim((string) $offday_date).'|'.microtime(TRUE).'|'.mt_rand(1000, 999999);
		return substr(sha1($seed), 0, 24);
	}

	private function build_day_off_swap_management_rows()
	{
		$swap_rows = $this->day_off_swap_book();
		if (empty($swap_rows))
		{
			return array();
		}

		$id_book = $this->employee_id_book();
		$profiles = $this->employee_profile_book();
		$result = array();
		for ($i = count($swap_rows) - 1; $i >= 0; $i -= 1)
		{
			$row = isset($swap_rows[$i]) && is_array($swap_rows[$i]) ? $swap_rows[$i] : array();
			$username_key = isset($row['username']) ? strtolower(trim((string) $row['username'])) : '';
			if ($username_key === '' || !$this->is_username_in_actor_scope($username_key))
			{
				continue;
			}

			$profile = isset($profiles[$username_key]) && is_array($profiles[$username_key])
				? $profiles[$username_key]
				: $this->get_employee_profile($username_key);
			$display_name = isset($profile['display_name']) && trim((string) $profile['display_name']) !== ''
				? (string) $profile['display_name']
				: $username_key;
			$branch = isset($profile['branch']) ? (string) $profile['branch'] : $this->default_employee_branch();
			$employee_id = $this->resolve_employee_id_from_book($username_key, $id_book);
			$workday_date = isset($row['workday_date']) ? trim((string) $row['workday_date']) : '';
			$offday_date = isset($row['offday_date']) ? trim((string) $row['offday_date']) : '';
			$result[] = array(
				'swap_id' => isset($row['swap_id']) ? (string) $row['swap_id'] : '',
				'username' => $username_key,
				'employee_id' => $employee_id,
				'display_name' => $display_name,
				'branch' => $branch,
				'workday_date' => $workday_date,
				'workday_label' => $this->format_user_dashboard_date_label($workday_date),
				'offday_date' => $offday_date,
				'offday_label' => $this->format_user_dashboard_date_label($offday_date),
				'note' => isset($row['note']) ? (string) $row['note'] : '',
				'created_by' => isset($row['created_by']) ? (string) $row['created_by'] : '',
				'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : ''
			);
		}

		return $result;
	}

	private function has_employee_attendance_record_on_date($username, $date_key)
	{
		$username_key = strtolower(trim((string) $username));
		$date_value = trim((string) $date_key);
		if ($username_key === '' || !$this->is_valid_date_format($date_value))
		{
			return FALSE;
		}

		$records = $this->load_attendance_records();
		for ($i = 0; $i < count($records); $i += 1)
		{
			$row = isset($records[$i]) && is_array($records[$i]) ? $records[$i] : array();
			$row_username = isset($row['username']) ? strtolower(trim((string) $row['username'])) : '';
			$row_date = isset($row['date']) ? trim((string) $row['date']) : '';
			if ($row_username !== $username_key || $row_date !== $date_value)
			{
				continue;
			}
			$row_check_in = isset($row['check_in_time']) ? trim((string) $row['check_in_time']) : '';
			$row_check_out = isset($row['check_out_time']) ? trim((string) $row['check_out_time']) : '';
			if ($row_check_in !== '' || $row_check_out !== '')
			{
				return TRUE;
			}
		}

		return FALSE;
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
			$month_policy_current = $this->calculate_employee_month_work_policy($username, date('Y-m-d'), $weekly_day_off_current);
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
				'weekly_day_off' => $weekly_day_off_current,
				'custom_allowed_weekdays' => $this->normalize_weekday_list(
					isset($profile['custom_allowed_weekdays']) ? $profile['custom_allowed_weekdays'] : array()
				),
				'custom_off_ranges' => $this->normalize_schedule_date_ranges(
					isset($profile['custom_off_ranges']) ? $profile['custom_off_ranges'] : array()
				),
				'custom_work_ranges' => $this->normalize_schedule_date_ranges(
					isset($profile['custom_work_ranges']) ? $profile['custom_work_ranges'] : array()
				),
				'record_version' => isset($profile['record_version']) ? max(1, (int) $profile['record_version']) : 1
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
		if (
			strpos($shift_name, 'multi') !== FALSE ||
			(
				(strpos($shift_time, '06:30') !== FALSE || strpos($shift_time, '07:00') !== FALSE || strpos($shift_time, '08:00') !== FALSE) &&
				(strpos($shift_time, '23:59') !== FALSE || strpos($shift_time, '23:00') !== FALSE)
			)
		)
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
				'end' => self::MULTISHIFT_CHECK_IN_MAX_TIME,
				'start_label' => '07:30',
				'end_label' => '23:00'
			);
		}

		return array(
			'start' => self::CHECK_IN_MIN_TIME,
			'end' => self::CHECK_IN_MAX_TIME,
			'start_label' => '07:30',
			'end_label' => '17:00'
		);
	}

	private function resolve_shift_check_out_window($shift_key = '')
	{
		$shift_key = strtolower(trim((string) $shift_key));
		if ($shift_key === 'multishift' || $shift_key === 'siang' || $shift_key === 'pagi')
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

	private function resolve_shift_alpha_cutoff_seconds($shift_key = '')
	{
		$shift_key = strtolower(trim((string) $shift_key));
		if ($shift_key === 'siang' || $shift_key === 'multishift')
		{
			return $this->time_to_seconds(self::MULTISHIFT_CHECK_IN_MAX_TIME);
		}

		return $this->time_to_seconds(self::CHECK_IN_MAX_TIME);
	}

	private function resolve_attendance_branch_for_user($username = '', $profile = array())
	{
		$username_key = strtolower(trim((string) $username));
		$branch_value = '';

		if ($username_key !== '')
		{
			$account_book = function_exists('absen_load_account_book')
				? absen_load_account_book()
				: array();
			if (is_array($account_book) && isset($account_book[$username_key]) && is_array($account_book[$username_key]))
			{
				$branch_value = isset($account_book[$username_key]['branch'])
					? (string) $account_book[$username_key]['branch']
					: '';
			}
		}

		if (is_array($profile) && isset($profile['branch']))
		{
			$profile_branch = (string) $profile['branch'];
			if ($branch_value === '' && $profile_branch !== '')
			{
				$branch_value = $profile_branch;
			}
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

		if ($username_key !== '')
		{
			$session_username = strtolower(trim((string) $this->session->userdata('absen_username')));
			if ($session_username !== '' && $session_username === $username_key)
			{
				$session_branch = $this->resolve_employee_branch((string) $this->session->userdata('absen_branch'));
				if ($session_branch === '' || strcasecmp($session_branch, $resolved_branch) !== 0)
				{
					$this->session->set_userdata('absen_branch', $resolved_branch);
				}
			}
		}

		return $resolved_branch;
	}

	private function resolve_cross_branch_for_user($username = '', $profile = array())
	{
		$username_key = strtolower(trim((string) $username));
		$cross_branch_enabled = 0;

		if ($username_key !== '')
		{
			$account_book = function_exists('absen_load_account_book')
				? absen_load_account_book()
				: array();
			if (is_array($account_book) && isset($account_book[$username_key]) && is_array($account_book[$username_key]))
			{
				$cross_branch_enabled = $this->resolve_cross_branch_enabled_value(
					isset($account_book[$username_key]['cross_branch_enabled'])
						? $account_book[$username_key]['cross_branch_enabled']
						: 0
				);
			}
		}

		if ($cross_branch_enabled !== 1 && is_array($profile) && isset($profile['cross_branch_enabled']))
		{
			$cross_branch_enabled = $this->resolve_cross_branch_enabled_value($profile['cross_branch_enabled']);
		}

		if ($cross_branch_enabled !== 1 && $username_key !== '')
		{
			$session_username = strtolower(trim((string) $this->session->userdata('absen_username')));
			if ($session_username !== '' && $session_username === $username_key)
			{
				$cross_branch_enabled = $this->resolve_cross_branch_enabled_value(
					$this->session->userdata('absen_cross_branch_enabled')
				);
			}
		}

		if ($cross_branch_enabled !== 1 && $username_key !== '')
		{
			$profile_lookup = $this->get_employee_profile($username_key);
			if (is_array($profile_lookup) && isset($profile_lookup['cross_branch_enabled']))
			{
				$cross_branch_enabled = $this->resolve_cross_branch_enabled_value(
					$profile_lookup['cross_branch_enabled']
				);
			}
		}

		$cross_branch_enabled = $cross_branch_enabled === 1 ? 1 : 0;
		if ($username_key !== '')
		{
			$session_username = strtolower(trim((string) $this->session->userdata('absen_username')));
			if ($session_username !== '' && $session_username === $username_key)
			{
				$session_cross_branch = $this->resolve_cross_branch_enabled_value(
					$this->session->userdata('absen_cross_branch_enabled')
				);
				if ($session_cross_branch !== $cross_branch_enabled)
				{
					$this->session->set_userdata('absen_cross_branch_enabled', $cross_branch_enabled);
				}
			}
		}

		return $cross_branch_enabled;
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

	private function attendance_office_points_for_shift($shift_key = '', $branch = '', $allow_cross_branch = 0)
	{
		$shift_key = strtolower(trim((string) $shift_key));
		$allow_cross_branch = $this->resolve_cross_branch_enabled_value($allow_cross_branch);
		$resolved_branch = $this->resolve_employee_branch($branch);
		if ($resolved_branch === '')
		{
			$resolved_branch = $this->default_employee_branch();
		}

		if ($allow_cross_branch === 1)
		{
			if (strcasecmp($resolved_branch, 'Cadasari') === 0)
			{
				return array(
					array(
						'label' => 'Kantor 2',
						'lat' => (float) self::OFFICE_ALT_LAT,
						'lng' => (float) self::OFFICE_ALT_LNG
					),
					array(
						'label' => 'Kantor 1',
						'lat' => (float) self::OFFICE_LAT,
						'lng' => (float) self::OFFICE_LNG
					)
				);
			}

			return array(
				array(
					'label' => 'Kantor 1',
					'lat' => (float) self::OFFICE_LAT,
					'lng' => (float) self::OFFICE_LNG
				),
				array(
					'label' => 'Kantor 2',
					'lat' => (float) self::OFFICE_ALT_LAT,
					'lng' => (float) self::OFFICE_ALT_LNG
				)
			);
		}

		if ($shift_key === 'multishift')
		{
			return $this->attendance_office_points_for_branch($resolved_branch);
		}

		return $this->attendance_office_points_for_branch($resolved_branch);
	}

	private function nearest_attendance_office($latitude, $longitude, $branch = '', $shift_key = '', $allow_cross_branch = 0)
	{
		$points = $this->attendance_office_points_for_shift($shift_key, $branch, $allow_cross_branch);
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

	private function resolve_attendance_branch_from_nearest_office($nearest_office = array(), $fallback_branch = '')
	{
		$fallback_resolved = $this->resolve_employee_branch($fallback_branch);
		if ($fallback_resolved === '')
		{
			$fallback_resolved = $this->default_employee_branch();
		}

		$baros_branch = $this->resolve_employee_branch('Baros');
		if ($baros_branch === '')
		{
			$baros_branch = $fallback_resolved;
		}

		$cadasari_branch = $this->resolve_employee_branch('Cadasari');
		if ($cadasari_branch === '')
		{
			$cadasari_branch = $fallback_resolved;
		}

		$office_label = is_array($nearest_office) && isset($nearest_office['label'])
			? strtolower(trim((string) $nearest_office['label']))
			: '';
		if ($office_label !== '')
		{
			if (strpos($office_label, 'kantor 2') !== FALSE || strpos($office_label, 'cadasari') !== FALSE)
			{
				return $cadasari_branch;
			}
			if (strpos($office_label, 'kantor 1') !== FALSE || strpos($office_label, 'baros') !== FALSE)
			{
				return $baros_branch;
			}
		}

		$office_lat = is_array($nearest_office) && isset($nearest_office['lat']) ? (float) $nearest_office['lat'] : NULL;
		$office_lng = is_array($nearest_office) && isset($nearest_office['lng']) ? (float) $nearest_office['lng'] : NULL;
		if ($office_lat !== NULL && $office_lng !== NULL)
		{
			$distance_to_cadasari = $this->calculate_distance_meter(
				$office_lat,
				$office_lng,
				(float) self::OFFICE_ALT_LAT,
				(float) self::OFFICE_ALT_LNG
			);
			if ($distance_to_cadasari <= 1.0)
			{
				return $cadasari_branch;
			}

			$distance_to_baros = $this->calculate_distance_meter(
				$office_lat,
				$office_lng,
				(float) self::OFFICE_LAT,
				(float) self::OFFICE_LNG
			);
			if ($distance_to_baros <= 1.0)
			{
				return $baros_branch;
			}
		}

		return $fallback_resolved;
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
			'overtime' => 0,
			'day_off_swap' => 0,
			'day_off_swap_request' => 0
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

		$day_off_swap_rows = $this->day_off_swap_book(TRUE);
		$day_off_swap_renamed = $this->rename_username_in_rows($day_off_swap_rows, $old_key, $new_key);
		$result['day_off_swap'] = isset($day_off_swap_renamed[1]) ? (int) $day_off_swap_renamed[1] : 0;
		if ($result['day_off_swap'] > 0)
		{
			$this->save_day_off_swap_book($day_off_swap_renamed[0]);
		}

		$day_off_swap_request_rows = $this->day_off_swap_request_book(TRUE);
		$day_off_swap_request_renamed = $this->rename_username_in_rows($day_off_swap_request_rows, $old_key, $new_key);
		$result['day_off_swap_request'] = isset($day_off_swap_request_renamed[1]) ? (int) $day_off_swap_request_renamed[1] : 0;
		if ($result['day_off_swap_request'] > 0)
		{
			$this->save_day_off_swap_request_book($day_off_swap_request_renamed[0]);
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
				'overtime' => 0,
				'day_off_swap' => 0,
				'day_off_swap_request' => 0
			);
		}

		$result = array(
			'attendance' => 0,
			'leave' => 0,
			'loan' => 0,
			'overtime' => 0,
			'day_off_swap' => 0,
			'day_off_swap_request' => 0
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

		$day_off_swap_records = $this->day_off_swap_book(TRUE);
		$day_off_swap_filtered = $filter_by_username($day_off_swap_records);
		$result['day_off_swap'] = (int) $day_off_swap_filtered[1];
		if ($result['day_off_swap'] > 0)
		{
			$this->save_day_off_swap_book($day_off_swap_filtered[0]);
		}

		$day_off_swap_request_records = $this->day_off_swap_request_book(TRUE);
		$day_off_swap_request_filtered = $filter_by_username($day_off_swap_request_records);
		$result['day_off_swap_request'] = (int) $day_off_swap_request_filtered[1];
		if ($result['day_off_swap_request'] > 0)
		{
			$this->save_day_off_swap_request_book($day_off_swap_request_filtered[0]);
		}

		return $result;
	}

	private function employee_username_list($force_refresh = FALSE)
	{
		$profiles = $this->employee_profile_book($force_refresh);
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

	private function normalize_employee_id_value($value)
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

		return $this->format_employee_id($sequence);
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

	private function employee_id_book($force_refresh = FALSE)
	{
		$profiles = $this->employee_profile_book($force_refresh);
		$id_book = array(
			'admin' => $this->admin_account_id()
		);
		$used_ids = array(
			$this->admin_account_id() => TRUE
		);
		$missing_usernames = array();

		foreach ($profiles as $username => $profile)
		{
			$username_key = strtolower(trim((string) $username));
			if ($username_key === '')
			{
				continue;
			}

			$stored_id = '';
			if (is_array($profile))
			{
				$stored_id = $this->normalize_employee_id_value(isset($profile['employee_id']) ? $profile['employee_id'] : '');
			}
			if ($stored_id !== '' && !isset($used_ids[$stored_id]))
			{
				$id_book[$username_key] = $stored_id;
				$used_ids[$stored_id] = TRUE;
				continue;
			}

			$missing_usernames[] = $username_key;
		}

		sort($missing_usernames, SORT_STRING);
		$sequence = 1;
		for ($i = 0; $i < count($missing_usernames); $i += 1)
		{
			$username_key = (string) $missing_usernames[$i];
			$candidate_id = '';
			while ($sequence <= 9999)
			{
				$next_id = $this->format_employee_id($sequence);
				$sequence += 1;
				if ($next_id === '-' || isset($used_ids[$next_id]))
				{
					continue;
				}
				$candidate_id = $next_id;
				break;
			}
			if ($candidate_id === '')
			{
				$candidate_id = '-';
			}

			$id_book[$username_key] = $candidate_id;
			if ($candidate_id !== '-')
			{
				$used_ids[$candidate_id] = TRUE;
			}
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

	private function attendance_photo_upload_dir()
	{
		return 'uploads/attendance_photo';
	}

	private function encode_binary_image_to_webp_file($binary_payload, $output_path, $max_width = 1280, $max_height = 1280, $webp_quality = 76)
	{
		if (!$this->can_process_profile_photo_image() || !function_exists('imagecreatefromstring'))
		{
			return FALSE;
		}

		$binary = is_string($binary_payload) ? $binary_payload : '';
		$target_path = trim((string) $output_path);
		if ($binary === '' || $target_path === '')
		{
			return FALSE;
		}

		$source_image = @imagecreatefromstring($binary);
		if (!$source_image)
		{
			return FALSE;
		}
		$orientation = 1;
		if (is_string($binary) && strlen($binary) >= 2 && substr($binary, 0, 2) === "\xFF\xD8")
		{
			$temp_file = @tempnam(sys_get_temp_dir(), 'att_exif_');
			if (is_string($temp_file) && $temp_file !== '')
			{
				if (@file_put_contents($temp_file, $binary) !== FALSE)
				{
					$orientation = $this->read_image_exif_orientation($temp_file);
				}
				@unlink($temp_file);
			}
		}
		$source_image = $this->apply_exif_orientation_to_image($source_image, $orientation);

		$source_width = (int) @imagesx($source_image);
		$source_height = (int) @imagesy($source_image);
		if ($source_width <= 0 || $source_height <= 0)
		{
			@imagedestroy($source_image);
			return FALSE;
		}

		$max_w = max(256, (int) $max_width);
		$max_h = max(256, (int) $max_height);
		$scale = min(1, $max_w / $source_width, $max_h / $source_height);
		$target_width = max(1, (int) round($source_width * $scale));
		$target_height = max(1, (int) round($source_height * $scale));

		$target_image = @imagecreatetruecolor($target_width, $target_height);
		if (!$target_image)
		{
			@imagedestroy($source_image);
			return FALSE;
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

		$quality = max(55, min(90, (int) $webp_quality));
		$saved = @imagewebp($target_image, $target_path, $quality);
		@imagedestroy($target_image);
		@imagedestroy($source_image);

		return $saved && is_file($target_path);
	}

	private function convert_local_image_to_webp($source_path, $source_ext = '', $max_width = 1280, $max_height = 1280, $webp_quality = 76, $delete_source = FALSE)
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

		$output_path = preg_replace('/\.[^.]+$/', '.webp', $path);
		if (!is_string($output_path) || trim($output_path) === '')
		{
			$output_path = $path.'.webp';
		}
		$quality = max(55, min(90, (int) $webp_quality));
		$saved = @imagewebp($target_image, $output_path, $quality);
		@imagedestroy($target_image);
		@imagedestroy($source_image);
		if (!$saved || !is_file($output_path))
		{
			return array('success' => FALSE, 'output_path' => $path);
		}

		if ($delete_source && $output_path !== $path && is_file($path))
		{
			@unlink($path);
		}

		return array(
			'success' => TRUE,
			'output_path' => $output_path
		);
	}

	private function normalize_attendance_photo_reference($photo_value, $username_key = '', $date_key = '', $photo_side = '')
	{
		$photo_text = trim((string) $photo_value);
		if ($photo_text === '')
		{
			return '';
		}

		$upload_directory_relative = trim($this->attendance_photo_upload_dir(), '/\\');
		$upload_directory_absolute = rtrim((string) FCPATH, '/\\').DIRECTORY_SEPARATOR.
			str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $upload_directory_relative);
		if (!is_dir($upload_directory_absolute))
		{
			@mkdir($upload_directory_absolute, 0755, TRUE);
		}
		if (!is_dir($upload_directory_absolute) || !is_writable($upload_directory_absolute))
		{
			return $photo_text;
		}

		$username_safe = preg_replace('/[^a-z0-9_]+/i', '', strtolower(trim((string) $username_key)));
		if ($username_safe === '')
		{
			$username_safe = 'user';
		}
		$date_safe = preg_replace('/[^0-9]+/', '', trim((string) $date_key));
		if ($date_safe === '')
		{
			$date_safe = date('Ymd');
		}
		$photo_side_safe = strtolower(trim((string) $photo_side));
		if ($photo_side_safe !== 'in' && $photo_side_safe !== 'out')
		{
			$photo_side_safe = 'photo';
		}

		if (strpos($photo_text, 'data:image/') !== 0)
		{
			$relative_photo_path = '';
			if (preg_match('/^https?:\/\//i', $photo_text) === 1)
			{
				$url_path = parse_url($photo_text, PHP_URL_PATH);
				$url_path = is_string($url_path) ? trim((string) $url_path) : '';
				if ($url_path === '')
				{
					return $photo_text;
				}
				$relative_photo_path = '/'.ltrim(str_replace('\\', '/', $url_path), '/\\');
			}
			else
			{
				$relative_photo_path = '/'.ltrim(str_replace('\\', '/', $photo_text), '/\\');
			}
			if (strpos($relative_photo_path, '/uploads/attendance_photo/') !== 0)
			{
				return $photo_text;
			}

			$absolute_photo_path = rtrim((string) FCPATH, '/\\').DIRECTORY_SEPARATOR.
				str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, ltrim($relative_photo_path, '/\\'));
			if (!is_file($absolute_photo_path))
			{
				return $relative_photo_path;
			}

			$current_ext = strtolower(pathinfo($absolute_photo_path, PATHINFO_EXTENSION));
			if ($current_ext === 'webp')
			{
				return $relative_photo_path;
			}

			$convert_result = $this->convert_local_image_to_webp(
				$absolute_photo_path,
				$current_ext,
				self::ATTENDANCE_PHOTO_MAX_WIDTH,
				self::ATTENDANCE_PHOTO_MAX_HEIGHT,
				self::ATTENDANCE_PHOTO_WEBP_QUALITY,
				TRUE
			);
			if (!isset($convert_result['success']) || $convert_result['success'] !== TRUE ||
				!isset($convert_result['output_path']) || !is_file((string) $convert_result['output_path']))
			{
				return $relative_photo_path;
			}

			$output_path_normalized = str_replace('\\', '/', (string) $convert_result['output_path']);
			$fcp_root = rtrim(str_replace('\\', '/', (string) FCPATH), '/');
			if ($fcp_root !== '' && strpos($output_path_normalized, $fcp_root.'/') === 0)
			{
				return '/'.ltrim(substr($output_path_normalized, strlen($fcp_root) + 1), '/');
			}

			return $relative_photo_path;
		}

		$matches = array();
		if (!preg_match('/^data:image\/([a-z0-9.+-]+);base64,(.+)$/is', $photo_text, $matches))
		{
			return $photo_text;
		}

		$mime_ext = strtolower(trim((string) (isset($matches[1]) ? $matches[1] : '')));
		$extension_map = array(
			'jpg' => 'jpg',
			'jpeg' => 'jpg',
			'png' => 'png',
			'webp' => 'webp'
		);
		if (!isset($extension_map[$mime_ext]))
		{
			return $photo_text;
		}

		$base64_payload = isset($matches[2]) ? preg_replace('/\s+/', '', (string) $matches[2]) : '';
		if (!is_string($base64_payload) || $base64_payload === '')
		{
			return $photo_text;
		}
		$binary = base64_decode($base64_payload, TRUE);
		if (!is_string($binary) || $binary === '')
		{
			return $photo_text;
		}

		$binary_size = strlen($binary);
		$max_bytes = 8 * 1024 * 1024;
		if ($binary_size <= 0 || $binary_size > $max_bytes)
		{
			return $photo_text;
		}

		$photo_hash = substr(sha1($binary), 0, 20);
		$file_name = 'attendance_'.$username_safe.'_'.$date_safe.'_'.$photo_side_safe.'_'.$photo_hash.'.webp';
		$target_path = rtrim($upload_directory_absolute, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file_name;
		if (!is_file($target_path))
		{
			$saved_webp = $this->encode_binary_image_to_webp_file(
				$binary,
				$target_path,
				self::ATTENDANCE_PHOTO_MAX_WIDTH,
				self::ATTENDANCE_PHOTO_MAX_HEIGHT,
				self::ATTENDANCE_PHOTO_WEBP_QUALITY
			);
			if (!$saved_webp)
			{
				return $photo_text;
			}
		}

		return '/'.str_replace('\\', '/', $upload_directory_relative.'/'.$file_name);
	}

	private function migrate_attendance_record_photo_paths($records)
	{
		$rows = is_array($records) ? array_values($records) : array();
		$has_changes = FALSE;
		for ($i = 0; $i < count($rows); $i += 1)
		{
			if (!isset($rows[$i]) || !is_array($rows[$i]))
			{
				$rows[$i] = array();
			}
			$row_username = isset($rows[$i]['username']) ? (string) $rows[$i]['username'] : '';
			$row_date = isset($rows[$i]['date']) ? (string) $rows[$i]['date'] : '';

			$check_in_photo_before = isset($rows[$i]['check_in_photo']) ? (string) $rows[$i]['check_in_photo'] : '';
			$check_in_photo_after = $this->normalize_attendance_photo_reference(
				$check_in_photo_before,
				$row_username,
				$row_date,
				'in'
			);
			if ($check_in_photo_after !== $check_in_photo_before)
			{
				$rows[$i]['check_in_photo'] = $check_in_photo_after;
				$has_changes = TRUE;
			}

			$check_out_photo_before = isset($rows[$i]['check_out_photo']) ? (string) $rows[$i]['check_out_photo'] : '';
			$check_out_photo_after = $this->normalize_attendance_photo_reference(
				$check_out_photo_before,
				$row_username,
				$row_date,
				'out'
			);
			if ($check_out_photo_after !== $check_out_photo_before)
			{
				$rows[$i]['check_out_photo'] = $check_out_photo_after;
				$has_changes = TRUE;
			}
		}

		return array(
			'records' => $rows,
			'changed' => $has_changes
		);
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
		if ($file_ext === 'jfif' || $file_ext === 'jpe')
		{
			$file_ext = 'jpeg';
		}
		$allowed_extensions = array('png', 'jpg', 'jpeg', 'webp');
		if ($file_ext === '' || !in_array($file_ext, $allowed_extensions, TRUE))
		{
			$detected_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
			$detected_ext = trim((string) $detected_ext);
			$ext_label = $detected_ext !== '' ? $detected_ext : 'tanpa ekstensi';
			return array(
				'success' => FALSE,
				'message' => 'Format PP tidak didukung ('.$ext_label.'). Gunakan png, jpg, jpeg, atau webp.'
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
			self::PROFILE_PHOTO_WEBP_QUALITY
		);
		if (isset($optimize_result['success']) && $optimize_result['success'] === TRUE &&
			isset($optimize_result['output_path']) && is_file((string) $optimize_result['output_path']))
		{
			$final_path = (string) $optimize_result['output_path'];
		}
		else
		{
			if (is_file($target_path))
			{
				@unlink($target_path);
			}
			return array(
				'success' => FALSE,
				'message' => 'Server belum mendukung konversi gambar ke webp. Hubungi developer untuk mengaktifkan GD imagewebp.'
			);
		}
		$this->create_profile_photo_thumbnail(
			$final_path,
			self::PROFILE_PHOTO_THUMB_SIZE,
			self::PROFILE_PHOTO_THUMB_WEBP_QUALITY
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
			function_exists('imagewebp');
	}

	private function read_image_exif_orientation($file_path)
	{
		$path = trim((string) $file_path);
		if ($path === '' || !is_file($path) || !function_exists('exif_read_data'))
		{
			return 1;
		}

		$exif_data = @exif_read_data($path);
		$orientation = isset($exif_data['Orientation']) ? (int) $exif_data['Orientation'] : 1;
		if ($orientation < 1 || $orientation > 8)
		{
			return 1;
		}

		return $orientation;
	}

	private function rotate_image_resource($image_resource, $angle)
	{
		if (!$image_resource || !function_exists('imagerotate'))
		{
			return $image_resource;
		}

		$rotated = @imagerotate($image_resource, (float) $angle, 0);
		if (!$rotated)
		{
			return $image_resource;
		}

		@imagedestroy($image_resource);
		return $rotated;
	}

	private function apply_exif_orientation_to_image($image_resource, $orientation)
	{
		if (!$image_resource)
		{
			return $image_resource;
		}

		$orientation_value = (int) $orientation;
		if ($orientation_value <= 1 || $orientation_value > 8)
		{
			return $image_resource;
		}

		$can_flip = function_exists('imageflip');
		switch ($orientation_value)
		{
			case 2:
				if ($can_flip)
				{
					@imageflip($image_resource, IMG_FLIP_HORIZONTAL);
				}
				break;
			case 3:
				$image_resource = $this->rotate_image_resource($image_resource, 180);
				break;
			case 4:
				if ($can_flip)
				{
					@imageflip($image_resource, IMG_FLIP_VERTICAL);
				}
				break;
			case 5:
				$image_resource = $this->rotate_image_resource($image_resource, -90);
				if ($can_flip)
				{
					@imageflip($image_resource, IMG_FLIP_HORIZONTAL);
				}
				break;
			case 6:
				$image_resource = $this->rotate_image_resource($image_resource, -90);
				break;
			case 7:
				$image_resource = $this->rotate_image_resource($image_resource, 90);
				if ($can_flip)
				{
					@imageflip($image_resource, IMG_FLIP_HORIZONTAL);
				}
				break;
			case 8:
				$image_resource = $this->rotate_image_resource($image_resource, 90);
				break;
		}

		return $image_resource;
	}

	private function apply_image_orientation_if_needed($image_resource, $file_path, $file_ext = '')
	{
		if (!$image_resource)
		{
			return $image_resource;
		}

		$ext = strtolower(trim((string) $file_ext));
		if ($ext === 'jfif' || $ext === 'jpe')
		{
			$ext = 'jpeg';
		}

		if ($ext !== 'jpg' && $ext !== 'jpeg')
		{
			return $image_resource;
		}

		$orientation = $this->read_image_exif_orientation($file_path);
		return $this->apply_exif_orientation_to_image($image_resource, $orientation);
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
		if ($ext === 'jfif' || $ext === 'jpe')
		{
			$ext = 'jpeg';
		}

		if (($ext === 'jpg' || $ext === 'jpeg') && function_exists('imagecreatefromjpeg'))
		{
			$image_resource = @imagecreatefromjpeg($path);
			return $this->apply_image_orientation_if_needed($image_resource, $path, $ext);
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
			$image_resource = @imagecreatefromjpeg($path);
			return $this->apply_image_orientation_if_needed($image_resource, $path, 'jpeg');
		}
		if ($mime === 'image/jfif' && function_exists('imagecreatefromjpeg'))
		{
			$image_resource = @imagecreatefromjpeg($path);
			return $this->apply_image_orientation_if_needed($image_resource, $path, 'jpeg');
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

	private function optimize_profile_photo_image($source_path, $source_ext = '', $max_width = 512, $max_height = 512, $webp_quality = 82)
	{
		return $this->convert_local_image_to_webp(
			$source_path,
			$source_ext,
			$max_width,
			$max_height,
			$webp_quality,
			TRUE
		);
	}

	private function create_profile_photo_thumbnail($source_path, $thumb_size = 160, $webp_quality = 76)
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
		$thumb_path = $directory.DIRECTORY_SEPARATOR.$base_name.'_thumb.webp';
		$quality = max(50, min(90, (int) $webp_quality));
		$saved = @imagewebp($thumb_image, $thumb_path, $quality);
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
			$thumb_webp_path = $directory.DIRECTORY_SEPARATOR.$base_name.'_thumb.webp';
			if (is_file($thumb_webp_path))
			{
				@unlink($thumb_webp_path);
			}
			$thumb_jpg_path = $directory.DIRECTORY_SEPARATOR.$base_name.'_thumb.jpg';
			if (is_file($thumb_jpg_path))
			{
				@unlink($thumb_jpg_path);
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
		$fcp_root = rtrim(str_replace('\\', '/', (string) FCPATH), '/');
		$webp_file = 'src/assets/fotoku.webp';
		$webp_abs = rtrim((string) FCPATH, '/\\').DIRECTORY_SEPARATOR.str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $webp_file);
		if (is_file($webp_abs))
		{
			return '/'.$webp_file;
		}

		$source_files = array(
			'src/assets/fotoku.JPG',
			'src/assets/fotoku.jpg',
			'src/assets/fotoku.jpeg',
			'src/assets/fotoku.png'
		);
		for ($i = 0; $i < count($source_files); $i += 1)
		{
			$source_file = (string) $source_files[$i];
			$source_abs = rtrim((string) FCPATH, '/\\').DIRECTORY_SEPARATOR.
				str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $source_file);
			if (!is_file($source_abs))
			{
				continue;
			}

			if ($this->can_process_profile_photo_image())
			{
				$source_ext = strtolower(pathinfo($source_abs, PATHINFO_EXTENSION));
				$convert_result = $this->convert_local_image_to_webp(
					$source_abs,
					$source_ext,
					self::PROFILE_PHOTO_MAX_WIDTH,
					self::PROFILE_PHOTO_MAX_HEIGHT,
					self::PROFILE_PHOTO_WEBP_QUALITY,
					FALSE
				);
				if (isset($convert_result['success']) && $convert_result['success'] === TRUE &&
					isset($convert_result['output_path']) && is_file((string) $convert_result['output_path']))
				{
					$output_path = str_replace('\\', '/', (string) $convert_result['output_path']);
					if ($fcp_root !== '' && strpos($output_path, $fcp_root.'/') === 0)
					{
						return '/'.ltrim(substr($output_path, strlen($fcp_root) + 1), '/');
					}
				}
			}

			return '/'.$source_file;
		}

		return '/'.$webp_file;
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

	private function normalize_weekday_list($days)
	{
		$list = is_array($days) ? $days : array();
		$normalized = array();
		for ($i = 0; $i < count($list); $i += 1)
		{
			$weekday_n = (int) $list[$i];
			if ($weekday_n < 1 || $weekday_n > 7)
			{
				continue;
			}
			if (!in_array($weekday_n, $normalized, TRUE))
			{
				$normalized[] = $weekday_n;
			}
		}
		sort($normalized);
		return $normalized;
	}

	private function normalize_schedule_date_ranges($ranges)
	{
		$list = array();
		if (is_array($ranges))
		{
			if (isset($ranges['start_date']) || isset($ranges['end_date']) || isset($ranges['start']) || isset($ranges['end']))
			{
				$list[] = $ranges;
			}
			else
			{
				$list = $ranges;
			}
		}

		$normalized = array();
		$seen = array();
		for ($i = 0; $i < count($list); $i += 1)
		{
			$row = isset($list[$i]) && is_array($list[$i]) ? $list[$i] : array();
			$start_date = isset($row['start_date'])
				? trim((string) $row['start_date'])
				: (isset($row['start']) ? trim((string) $row['start']) : '');
			$end_date = isset($row['end_date'])
				? trim((string) $row['end_date'])
				: (isset($row['end']) ? trim((string) $row['end']) : '');
			if (!$this->is_valid_date_format($start_date) || !$this->is_valid_date_format($end_date))
			{
				continue;
			}
			if (strcmp($start_date, $end_date) > 0)
			{
				$temp_date = $start_date;
				$start_date = $end_date;
				$end_date = $temp_date;
			}
			$range_key = $start_date.'|'.$end_date;
			if (isset($seen[$range_key]))
			{
				continue;
			}
			$seen[$range_key] = TRUE;
			$normalized[] = array(
				'start_date' => $start_date,
				'end_date' => $end_date
			);
		}

		usort($normalized, function ($a, $b) {
			$a_start = isset($a['start_date']) ? (string) $a['start_date'] : '';
			$b_start = isset($b['start_date']) ? (string) $b['start_date'] : '';
			$start_compare = strcmp($a_start, $b_start);
			if ($start_compare !== 0)
			{
				return $start_compare;
			}
			$a_end = isset($a['end_date']) ? (string) $a['end_date'] : '';
			$b_end = isset($b['end_date']) ? (string) $b['end_date'] : '';
			return strcmp($a_end, $b_end);
		});

		return $normalized;
	}

	private function normalize_employee_custom_schedule($custom_schedule = NULL)
	{
		$schedule = is_array($custom_schedule) ? $custom_schedule : array();
		$allowed_days_source = isset($schedule['custom_allowed_weekdays'])
			? $schedule['custom_allowed_weekdays']
			: (isset($schedule['allowed_days']) ? $schedule['allowed_days'] : array());
		$off_ranges_source = isset($schedule['custom_off_ranges'])
			? $schedule['custom_off_ranges']
			: (isset($schedule['off_ranges']) ? $schedule['off_ranges'] : array());
		$work_ranges_source = isset($schedule['custom_work_ranges'])
			? $schedule['custom_work_ranges']
			: (isset($schedule['work_ranges']) ? $schedule['work_ranges'] : array());

		return array(
			'custom_allowed_weekdays' => $this->normalize_weekday_list($allowed_days_source),
			'custom_off_ranges' => $this->normalize_schedule_date_ranges($off_ranges_source),
			'custom_work_ranges' => $this->normalize_schedule_date_ranges($work_ranges_source)
		);
	}

	private function resolve_employee_custom_schedule($username = '', $custom_schedule_override = NULL)
	{
		if (is_array($custom_schedule_override))
		{
			return $this->normalize_employee_custom_schedule($custom_schedule_override);
		}

		$username_key = strtolower(trim((string) $username));
		if ($username_key === '')
		{
			return $this->normalize_employee_custom_schedule(array());
		}

		$profile = $this->get_employee_profile($username_key);
		if (!is_array($profile))
		{
			return $this->normalize_employee_custom_schedule(array());
		}

		return $this->normalize_employee_custom_schedule(array(
			'custom_allowed_weekdays' => isset($profile['custom_allowed_weekdays']) ? $profile['custom_allowed_weekdays'] : array(),
			'custom_off_ranges' => isset($profile['custom_off_ranges']) ? $profile['custom_off_ranges'] : array(),
			'custom_work_ranges' => isset($profile['custom_work_ranges']) ? $profile['custom_work_ranges'] : array()
		));
	}

	private function employee_has_custom_schedule($username = '', $custom_schedule_override = NULL)
	{
		$custom_schedule = $this->resolve_employee_custom_schedule($username, $custom_schedule_override);
		$allowed_days = $this->resolve_allowed_attendance_weekdays_for_username($username, $custom_schedule);
		return !empty($allowed_days)
			|| !empty($custom_schedule['custom_off_ranges'])
			|| !empty($custom_schedule['custom_work_ranges']);
	}

	private function is_date_in_schedule_ranges($date_key, $ranges)
	{
		$date_value = trim((string) $date_key);
		if (!$this->is_valid_date_format($date_value))
		{
			return FALSE;
		}

		$normalized_ranges = $this->normalize_schedule_date_ranges($ranges);
		for ($i = 0; $i < count($normalized_ranges); $i += 1)
		{
			$range_row = isset($normalized_ranges[$i]) && is_array($normalized_ranges[$i]) ? $normalized_ranges[$i] : array();
			$start_date = isset($range_row['start_date']) ? trim((string) $range_row['start_date']) : '';
			$end_date = isset($range_row['end_date']) ? trim((string) $range_row['end_date']) : '';
			if ($start_date === '' || $end_date === '')
			{
				continue;
			}
			if (strcmp($date_value, $start_date) < 0)
			{
				continue;
			}
			if (strcmp($date_value, $end_date) > 0)
			{
				continue;
			}
			return TRUE;
		}

		return FALSE;
	}

	private function parse_schedule_range_input_from_post($start_field_name, $end_field_name, $label)
	{
		$start_value = trim((string) $this->input->post($start_field_name, TRUE));
		$end_value = trim((string) $this->input->post($end_field_name, TRUE));
		if ($start_value === '' && $end_value === '')
		{
			return array(
				'success' => TRUE,
				'range' => array()
			);
		}
		if ($start_value === '' || $end_value === '')
		{
			return array(
				'success' => FALSE,
				'message' => $label.' wajib diisi lengkap: tanggal awal dan tanggal akhir.'
			);
		}
		if (!$this->is_valid_date_format($start_value) || !$this->is_valid_date_format($end_value))
		{
			return array(
				'success' => FALSE,
				'message' => $label.' harus menggunakan format tanggal valid (YYYY-MM-DD).'
			);
		}

		$normalized_ranges = $this->normalize_schedule_date_ranges(array(
			array(
				'start_date' => $start_value,
				'end_date' => $end_value
			)
		));
		if (empty($normalized_ranges))
		{
			return array(
				'success' => FALSE,
				'message' => $label.' tidak valid.'
			);
		}

		return array(
			'success' => TRUE,
			'range' => $normalized_ranges[0]
		);
	}

	private function resolve_custom_schedule_payload_from_post($field_prefix = 'edit_')
	{
		$prefix = trim((string) $field_prefix);
		$provided = $this->input->post($prefix.'custom_schedule_present', FALSE) !== NULL;
		$payload = array(
			'success' => TRUE,
			'provided' => $provided,
			'custom_allowed_weekdays' => array(),
			'custom_off_ranges' => array(),
			'custom_work_ranges' => array()
		);
		if (!$provided)
		{
			return $payload;
		}

		$allowed_days_raw = $this->input->post($prefix.'custom_allowed_weekdays', FALSE);
		if (!is_array($allowed_days_raw))
		{
			$allowed_days_raw = $allowed_days_raw === NULL ? array() : array($allowed_days_raw);
		}
		$payload['custom_allowed_weekdays'] = $this->normalize_weekday_list($allowed_days_raw);

		$off_range_result = $this->parse_schedule_range_input_from_post(
			$prefix.'custom_off_start_date',
			$prefix.'custom_off_end_date',
			'Rentang libur khusus'
		);
		if (!isset($off_range_result['success']) || $off_range_result['success'] !== TRUE)
		{
			$payload['success'] = FALSE;
			$payload['message'] = isset($off_range_result['message']) ? (string) $off_range_result['message'] : 'Rentang libur khusus tidak valid.';
			return $payload;
		}
		if (isset($off_range_result['range']) && is_array($off_range_result['range']) && !empty($off_range_result['range']))
		{
			$payload['custom_off_ranges'][] = $off_range_result['range'];
		}

		$work_range_result = $this->parse_schedule_range_input_from_post(
			$prefix.'custom_work_start_date',
			$prefix.'custom_work_end_date',
			'Rentang masuk khusus'
		);
		if (!isset($work_range_result['success']) || $work_range_result['success'] !== TRUE)
		{
			$payload['success'] = FALSE;
			$payload['message'] = isset($work_range_result['message']) ? (string) $work_range_result['message'] : 'Rentang masuk khusus tidak valid.';
			return $payload;
		}
		if (isset($work_range_result['range']) && is_array($work_range_result['range']) && !empty($work_range_result['range']))
		{
			$payload['custom_work_ranges'][] = $work_range_result['range'];
		}

		$payload['custom_off_ranges'] = $this->normalize_schedule_date_ranges($payload['custom_off_ranges']);
		$payload['custom_work_ranges'] = $this->normalize_schedule_date_ranges($payload['custom_work_ranges']);
		return $payload;
	}

	private function resolve_allowed_attendance_weekdays_for_username($username = '', $custom_schedule_override = NULL)
	{
		$username_key = strtolower(trim((string) $username));
		if ($username_key === '')
		{
			return array();
		}

		$custom_schedule = $this->resolve_employee_custom_schedule($username_key, $custom_schedule_override);
		$custom_allowed_days = isset($custom_schedule['custom_allowed_weekdays']) && is_array($custom_schedule['custom_allowed_weekdays'])
			? $custom_schedule['custom_allowed_weekdays']
			: array();
		if (!empty($custom_allowed_days))
		{
			return $custom_allowed_days;
		}

		$allowed_map = self::CUSTOM_ALLOWED_ATTENDANCE_DAYS_BY_USERNAME;
		if (isset($allowed_map[$username_key]))
		{
			$allowed_days = $this->normalize_weekday_list($allowed_map[$username_key]);
			if (!empty($allowed_days))
			{
				return $allowed_days;
			}
		}

		$off_map = self::CUSTOM_WEEKDAY_OFF_BY_USERNAME;
		if (!isset($off_map[$username_key]))
		{
			return array();
		}

		$off_days = $this->normalize_weekday_list($off_map[$username_key]);
		if (empty($off_days))
		{
			return array();
		}

		$allowed_days = array();
		for ($weekday_n = 1; $weekday_n <= 7; $weekday_n += 1)
		{
			if (!in_array($weekday_n, $off_days, TRUE))
			{
				$allowed_days[] = $weekday_n;
			}
		}

		return $allowed_days;
	}

	private function weekday_label($weekday_n)
	{
		$options = $this->weekly_day_off_options();
		$weekday_key = (int) $weekday_n;
		return isset($options[$weekday_key]) ? (string) $options[$weekday_key] : 'Hari kerja';
	}

	private function build_weekday_label_list($days)
	{
		$list = $this->normalize_weekday_list($days);
		$labels = array();
		for ($i = 0; $i < count($list); $i += 1)
		{
			$labels[] = $this->weekday_label($list[$i]);
		}
		return $labels;
	}

	private function resolve_day_off_swap_override_for_date($username, $date_key)
	{
		$username_key = strtolower(trim((string) $username));
		$date_value = trim((string) $date_key);
		if ($username_key === '' || !$this->is_valid_date_format($date_value))
		{
			return array();
		}

		$swap_rows = $this->day_off_swap_book();
		$matched_row = array();
		$matched_type = '';
		$matched_timestamp = 0;
		$matched_index = -1;
		for ($i = 0; $i < count($swap_rows); $i += 1)
		{
			$row = isset($swap_rows[$i]) && is_array($swap_rows[$i]) ? $swap_rows[$i] : array();
			$row_username = isset($row['username']) ? strtolower(trim((string) $row['username'])) : '';
			if ($row_username !== $username_key)
			{
				continue;
			}

			$row_workday_date = isset($row['workday_date']) ? trim((string) $row['workday_date']) : '';
			$row_offday_date = isset($row['offday_date']) ? trim((string) $row['offday_date']) : '';
			$type = '';
			if ($date_value === $row_workday_date)
			{
				$type = 'workday';
			}
			elseif ($date_value === $row_offday_date)
			{
				$type = 'offday';
			}
			if ($type === '')
			{
				continue;
			}

			$row_created_at = isset($row['created_at']) ? (string) $row['created_at'] : '';
			$row_ts = strtotime($row_created_at);
			$row_ts_n = $row_ts === FALSE ? 0 : (int) $row_ts;
			if ($matched_type === '' || $row_ts_n > $matched_timestamp || ($row_ts_n === $matched_timestamp && $i > $matched_index))
			{
				$matched_row = $row;
				$matched_type = $type;
				$matched_timestamp = $row_ts_n;
				$matched_index = $i;
			}
		}

		if ($matched_type === '')
		{
			return array();
		}

		return array(
			'type' => $matched_type,
			'swap' => $matched_row
		);
	}

	private function is_employee_regular_workday_without_swap($username, $date_key, $weekly_day_off = NULL, $custom_schedule_override = NULL)
	{
		$date_value = trim((string) $date_key);
		if (!$this->is_valid_date_format($date_value))
		{
			return TRUE;
		}

		$date_timestamp = strtotime($date_value.' 00:00:00');
		if ($date_timestamp === FALSE)
		{
			return TRUE;
		}
		$weekday_n = (int) date('N', $date_timestamp);
		$custom_schedule = $this->resolve_employee_custom_schedule($username, $custom_schedule_override);
		$custom_work_ranges = isset($custom_schedule['custom_work_ranges']) ? $custom_schedule['custom_work_ranges'] : array();
		if ($this->is_date_in_schedule_ranges($date_value, $custom_work_ranges))
		{
			return TRUE;
		}
		$custom_off_ranges = isset($custom_schedule['custom_off_ranges']) ? $custom_schedule['custom_off_ranges'] : array();
		if ($this->is_date_in_schedule_ranges($date_value, $custom_off_ranges))
		{
			return FALSE;
		}

		$allowed_days = $this->resolve_allowed_attendance_weekdays_for_username($username, $custom_schedule);
		if (!empty($allowed_days))
		{
			return in_array($weekday_n, $allowed_days, TRUE);
		}

		$weekly_off_n = $weekly_day_off === NULL
			? $this->default_weekly_day_off()
			: $this->resolve_employee_weekly_day_off($weekly_day_off);
		return $weekday_n !== $weekly_off_n;
	}

	private function resolve_employee_schedule_status($username, $date_key, $weekly_day_off = NULL, $custom_schedule_override = NULL)
	{
		$date_value = trim((string) $date_key);
		$custom_schedule = $this->resolve_employee_custom_schedule($username, $custom_schedule_override);
		$custom_off_ranges = isset($custom_schedule['custom_off_ranges']) ? $custom_schedule['custom_off_ranges'] : array();
		$custom_work_ranges = isset($custom_schedule['custom_work_ranges']) ? $custom_schedule['custom_work_ranges'] : array();
		if (!$this->is_valid_date_format($date_value))
		{
			return array(
				'is_workday' => TRUE,
				'reason' => 'invalid_date',
				'date_key' => $date_value,
				'weekday_n' => 0,
				'weekly_day_off' => $weekly_day_off === NULL ? $this->default_weekly_day_off() : $this->resolve_employee_weekly_day_off($weekly_day_off),
				'allowed_days' => array(),
				'custom_off_ranges' => $custom_off_ranges,
				'custom_work_ranges' => $custom_work_ranges,
				'swap' => array()
			);
		}

		$date_timestamp = strtotime($date_value.' 00:00:00');
		if ($date_timestamp === FALSE)
		{
			return array(
				'is_workday' => TRUE,
				'reason' => 'invalid_date',
				'date_key' => $date_value,
				'weekday_n' => 0,
				'weekly_day_off' => $weekly_day_off === NULL ? $this->default_weekly_day_off() : $this->resolve_employee_weekly_day_off($weekly_day_off),
				'allowed_days' => array(),
				'custom_off_ranges' => $custom_off_ranges,
				'custom_work_ranges' => $custom_work_ranges,
				'swap' => array()
			);
		}

		$weekday_n = (int) date('N', $date_timestamp);
		$weekly_off_n = $weekly_day_off === NULL
			? $this->default_weekly_day_off()
			: $this->resolve_employee_weekly_day_off($weekly_day_off);

		if ($this->is_date_in_schedule_ranges($date_value, $custom_work_ranges))
		{
			return array(
				'is_workday' => TRUE,
				'reason' => 'custom_range_workday',
				'date_key' => $date_value,
				'weekday_n' => $weekday_n,
				'weekly_day_off' => $weekly_off_n,
				'allowed_days' => array(),
				'custom_off_ranges' => $custom_off_ranges,
				'custom_work_ranges' => $custom_work_ranges,
				'swap' => array()
			);
		}
		if ($this->is_date_in_schedule_ranges($date_value, $custom_off_ranges))
		{
			return array(
				'is_workday' => FALSE,
				'reason' => 'custom_range_offday',
				'date_key' => $date_value,
				'weekday_n' => $weekday_n,
				'weekly_day_off' => $weekly_off_n,
				'allowed_days' => array(),
				'custom_off_ranges' => $custom_off_ranges,
				'custom_work_ranges' => $custom_work_ranges,
				'swap' => array()
			);
		}

		$swap_override = $this->resolve_day_off_swap_override_for_date($username, $date_value);
		if (!empty($swap_override))
		{
			$swap_type = isset($swap_override['type']) ? (string) $swap_override['type'] : '';
			return array(
				'is_workday' => $swap_type === 'workday',
				'reason' => $swap_type === 'workday' ? 'swap_workday' : 'swap_offday',
				'date_key' => $date_value,
				'weekday_n' => $weekday_n,
				'weekly_day_off' => $weekly_off_n,
				'allowed_days' => array(),
				'custom_off_ranges' => $custom_off_ranges,
				'custom_work_ranges' => $custom_work_ranges,
				'swap' => isset($swap_override['swap']) && is_array($swap_override['swap']) ? $swap_override['swap'] : array()
			);
		}

		$allowed_days = $this->resolve_allowed_attendance_weekdays_for_username($username, $custom_schedule);
		if (!empty($allowed_days))
		{
			$is_workday = in_array($weekday_n, $allowed_days, TRUE);
			return array(
				'is_workday' => $is_workday,
				'reason' => $is_workday ? 'custom_allowed_workday' : 'custom_allowed_offday',
				'date_key' => $date_value,
				'weekday_n' => $weekday_n,
				'weekly_day_off' => $weekly_off_n,
				'allowed_days' => $allowed_days,
				'custom_off_ranges' => $custom_off_ranges,
				'custom_work_ranges' => $custom_work_ranges,
				'swap' => array()
			);
		}

		$is_workday = $weekday_n !== $weekly_off_n;
		return array(
			'is_workday' => $is_workday,
			'reason' => $is_workday ? 'weekly_workday' : 'weekly_offday',
			'date_key' => $date_value,
			'weekday_n' => $weekday_n,
			'weekly_day_off' => $weekly_off_n,
			'allowed_days' => array(),
			'custom_off_ranges' => $custom_off_ranges,
			'custom_work_ranges' => $custom_work_ranges,
			'swap' => array()
		);
	}

	private function is_employee_scheduled_workday($username, $date_key, $weekly_day_off = NULL, $custom_schedule_override = NULL)
	{
		$schedule_status = $this->resolve_employee_schedule_status($username, $date_key, $weekly_day_off, $custom_schedule_override);
		return isset($schedule_status['is_workday']) ? $schedule_status['is_workday'] === TRUE : TRUE;
	}

	private function attendance_schedule_block_message($username, $date_key, $weekly_day_off = NULL, $custom_schedule_override = NULL)
	{
		$schedule_status = $this->resolve_employee_schedule_status($username, $date_key, $weekly_day_off, $custom_schedule_override);
		$is_workday = isset($schedule_status['is_workday']) ? $schedule_status['is_workday'] === TRUE : TRUE;
		if ($is_workday)
		{
			return '';
		}

		$username_value = trim((string) $username);
		if ($username_value === '')
		{
			$username_value = 'Akun ini';
		}

		$date_value = isset($schedule_status['date_key']) ? trim((string) $schedule_status['date_key']) : trim((string) $date_key);
		$weekday_n = isset($schedule_status['weekday_n']) ? (int) $schedule_status['weekday_n'] : 0;
		$today_label = $this->weekday_label($weekday_n);
		$reason = isset($schedule_status['reason']) ? trim((string) $schedule_status['reason']) : '';
		if ($reason === 'custom_range_offday')
		{
			$today_date_label = $this->format_user_dashboard_date_label($date_value);
			return $username_value.' libur pada jadwal periode khusus tanggal '.$today_date_label.'. Absensi ditutup.';
		}
		$allowed_days = isset($schedule_status['allowed_days']) && is_array($schedule_status['allowed_days'])
			? $schedule_status['allowed_days']
			: $this->resolve_allowed_attendance_weekdays_for_username($username_value, $custom_schedule_override);
		if (!empty($allowed_days))
		{
			$allowed_labels = $this->build_weekday_label_list($allowed_days);
			return $username_value.' hanya bisa absen pada hari '.implode(' dan ', $allowed_labels).'. Hari ini '.$today_label.' tidak termasuk jadwal absensi.';
		}

		if ($reason === 'swap_offday')
		{
			$swap_row = isset($schedule_status['swap']) && is_array($schedule_status['swap'])
				? $schedule_status['swap']
				: array();
			$swap_workday_date = isset($swap_row['workday_date']) ? trim((string) $swap_row['workday_date']) : '';
			$today_date_label = $this->format_user_dashboard_date_label($date_value);
			$workday_date_label = $this->format_user_dashboard_date_label($swap_workday_date);
			return $username_value.' libur pengganti pada '.$today_date_label.' (tukar dari '.$workday_date_label.'). Absensi ditutup.';
		}

		$weekly_off_n = isset($schedule_status['weekly_day_off'])
			? $this->resolve_employee_weekly_day_off($schedule_status['weekly_day_off'])
			: ($weekly_day_off === NULL ? $this->default_weekly_day_off() : $this->resolve_employee_weekly_day_off($weekly_day_off));
		return $username_value.' libur pada hari '.$this->weekday_label($weekly_off_n).'. Hari ini '.$today_label.', jadi absensi ditutup.';
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
			'view_log_data' => 'Akses log data',
			'edit_attendance_records' => 'Edit data absensi karyawan',
			'delete_attendance_records' => 'Hapus data absensi karyawan',
			'process_day_off_swap_requests' => 'Proses pengajuan tukar hari libur',
			'process_leave_requests' => 'Proses pengajuan cuti / izin',
			'delete_leave_requests' => 'Hapus pengajuan cuti / izin',
			'process_loan_requests' => 'Proses pengajuan pinjaman',
			'delete_loan_requests' => 'Hapus pengajuan pinjaman'
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

	private function can_edit_attendance_records_feature()
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

		if ($this->can_access_super_admin_features())
		{
			return TRUE;
		}

		$actor = $this->current_actor_username();
		if (in_array($actor, array('adminbaros', 'admin_cadasari'), TRUE))
		{
			return TRUE;
		}

		return $this->actor_has_admin_feature('edit_attendance_records');
	}

	private function can_delete_attendance_records_feature()
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

		if ($this->can_access_super_admin_features())
		{
			return TRUE;
		}

		$actor = $this->current_actor_username();
		if (in_array($actor, array('adminbaros', 'admin_cadasari'), TRUE))
		{
			return TRUE;
		}

		return $this->actor_has_admin_feature('delete_attendance_records');
	}

	private function can_partial_delete_attendance_records_feature()
	{
		return $this->can_access_super_admin_features();
	}

	private function can_process_leave_requests_feature()
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

		if ($this->can_access_super_admin_features())
		{
			return TRUE;
		}

		$actor = $this->current_actor_username();
		if (in_array($actor, array('adminbaros', 'admin_cadasari'), TRUE))
		{
			return TRUE;
		}

		return $this->actor_has_admin_feature('process_leave_requests');
	}

	private function can_process_day_off_swap_requests_feature()
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

		if ($this->can_access_super_admin_features())
		{
			return TRUE;
		}

		$actor = $this->current_actor_username();
		if (in_array($actor, array('admin', 'adminbaros', 'admin_cadasari'), TRUE))
		{
			return TRUE;
		}

		return $this->actor_has_admin_feature('process_day_off_swap_requests')
			|| $this->actor_has_admin_feature('manage_accounts');
	}

	private function can_delete_day_off_swap_requests_feature()
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

		return $this->can_access_super_admin_features();
	}

	private function can_delete_leave_requests_feature()
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

		return $this->actor_has_admin_feature('delete_leave_requests');
	}

	private function can_process_loan_requests_feature()
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

		if ($this->can_access_super_admin_features())
		{
			return TRUE;
		}

		$actor = $this->current_actor_username();
		if (in_array($actor, array('adminbaros', 'admin_cadasari'), TRUE))
		{
			return TRUE;
		}

		return $this->actor_has_admin_feature('process_loan_requests');
	}

	private function can_delete_loan_requests_feature()
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

		return $this->actor_has_admin_feature('delete_loan_requests');
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
		if ($role_value === 'admin' && $branch_value === '')
		{
			if ($username_key === 'admin_cadasari')
			{
				$branch_value = 'Cadasari';
			}
			elseif ($username_key === 'adminbaros' || $username_key === 'admin')
			{
				$branch_value = $this->default_employee_branch();
			}
		}

		$requires_password_change = function_exists('absen_account_requires_password_change')
			? absen_account_requires_password_change($account)
			: FALSE;
		$cross_branch_enabled_value = $this->resolve_cross_branch_enabled_value(
			isset($account['cross_branch_enabled']) ? $account['cross_branch_enabled'] : 0
		);
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
			'absen_cross_branch_enabled' => $cross_branch_enabled_value,
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

	private function can_edit_attendance_datetime()
	{
		if (!$this->can_edit_attendance_records_feature())
		{
			return FALSE;
		}

		if ($this->can_access_super_admin_features())
		{
			return TRUE;
		}

		$actor = $this->current_actor_username();
		if (in_array($actor, array('adminbaros', 'admin_cadasari'), TRUE))
		{
			return TRUE;
		}

		return $this->actor_has_admin_feature('edit_attendance_records');
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

		if ($this->can_access_super_admin_features())
		{
			return FALSE;
		}

		$actor = $this->current_actor_username();
		return in_array($actor, array('admin', 'adminbaros', 'admin_cadasari'), TRUE);
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
		if ($actor === 'admin_cadasari')
		{
			return 'Cadasari';
		}

		return ($actor === 'admin' || $actor === 'adminbaros') ? $this->default_employee_branch() : '';
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

	private function scoped_employee_lookup($force_refresh = FALSE)
	{
		$profiles = $this->employee_profile_book($force_refresh);
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
				'employee_id' => $this->normalize_employee_id_value(isset($account['employee_id']) ? $account['employee_id'] : ''),
				'branch' => $this->resolve_employee_branch(isset($account['branch']) ? (string) $account['branch'] : ''),
				'cross_branch_enabled' => $this->resolve_cross_branch_enabled_value(
					isset($account['cross_branch_enabled']) ? $account['cross_branch_enabled'] : 0
				),
				'phone' => isset($account['phone']) ? (string) $account['phone'] : '',
				'shift_name' => isset($account['shift_name']) ? (string) $account['shift_name'] : 'Shift Pagi - Sore',
				'shift_time' => isset($account['shift_time']) ? (string) $account['shift_time'] : '08:00 - 17:00',
				'job_title' => $job_title,
				'salary_tier' => isset($account['salary_tier']) ? (string) $account['salary_tier'] : 'A',
				'salary_monthly' => isset($account['salary_monthly']) ? (int) $account['salary_monthly'] : 0,
				'work_days' => isset($account['work_days']) ? (int) $account['work_days'] : 28,
				'weekly_day_off' => $this->resolve_employee_weekly_day_off(isset($account['weekly_day_off']) ? $account['weekly_day_off'] : NULL),
				'custom_allowed_weekdays' => $this->normalize_weekday_list(
					isset($account['custom_allowed_weekdays']) ? $account['custom_allowed_weekdays'] : array()
				),
				'custom_off_ranges' => $this->normalize_schedule_date_ranges(
					isset($account['custom_off_ranges']) ? $account['custom_off_ranges'] : array()
				),
				'custom_work_ranges' => $this->normalize_schedule_date_ranges(
					isset($account['custom_work_ranges']) ? $account['custom_work_ranges'] : array()
				),
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
			$profile['custom_allowed_weekdays'] = $this->normalize_weekday_list(
				isset($profile['custom_allowed_weekdays']) ? $profile['custom_allowed_weekdays'] : array()
			);
			$profile['custom_off_ranges'] = $this->normalize_schedule_date_ranges(
				isset($profile['custom_off_ranges']) ? $profile['custom_off_ranges'] : array()
			);
			$profile['custom_work_ranges'] = $this->normalize_schedule_date_ranges(
				isset($profile['custom_work_ranges']) ? $profile['custom_work_ranges'] : array()
			);

			return $profile;
		}

		return array(
			'profile_photo' => $this->default_employee_profile_photo(),
			'address' => $this->default_employee_address(),
			'job_title' => $this->default_employee_job_title(),
			'cross_branch_enabled' => 0,
			'coordinate_point' => '',
			'weekly_day_off' => $this->default_weekly_day_off(),
			'custom_allowed_weekdays' => array(),
			'custom_off_ranges' => array(),
			'custom_work_ranges' => array()
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

	private function submission_notify_enabled()
	{
		$raw = strtolower(trim((string) getenv('WA_SUBMISSION_NOTIFY_ENABLED')));
		if ($raw === '')
		{
			return TRUE;
		}

		return in_array($raw, array('1', 'true', 'yes', 'on'), TRUE);
	}

	private function resolve_submission_notify_admin_phone()
	{
		$target = trim((string) getenv('WA_SUBMISSION_NOTIFY_TARGET'));
		if ($target === '')
		{
			$target = trim((string) getenv('WA_ADMIN_SUBMISSION_NOTIFY_TARGET'));
		}
		if ($target === '')
		{
			$target = self::SUBMISSION_NOTIFY_ADMIN_PHONE;
		}

		return $target;
	}

	private function resolve_requestor_display_name($username = '')
	{
		$username_key = strtolower(trim((string) $username));
		if ($username_key === '')
		{
			return 'Karyawan';
		}

		$profile = $this->get_employee_profile($username_key);
		if (is_array($profile))
		{
			$display_name = isset($profile['display_name']) ? trim((string) $profile['display_name']) : '';
			if ($display_name !== '')
			{
				return $display_name;
			}
		}

		return $username_key;
	}

	private function build_new_leave_submission_admin_whatsapp_message($request_row)
	{
		$request_row = is_array($request_row) ? $request_row : array();
		$username = isset($request_row['username']) ? trim((string) $request_row['username']) : '';
		$display_name = $this->resolve_requestor_display_name($username);
		$request_id = isset($request_row['id']) ? trim((string) $request_row['id']) : '-';
		$type_label = isset($request_row['request_type_label']) ? trim((string) $request_row['request_type_label']) : '';
		if ($type_label === '')
		{
			$type_label = 'Cuti / Izin';
		}
		$start_date_label = isset($request_row['start_date_label']) ? trim((string) $request_row['start_date_label']) : '-';
		$end_date_label = isset($request_row['end_date_label']) ? trim((string) $request_row['end_date_label']) : '-';
		$duration_days = isset($request_row['duration_days']) ? max(1, (int) $request_row['duration_days']) : 1;
		$reason = isset($request_row['reason']) ? trim((string) $request_row['reason']) : '';
		if ($reason === '')
		{
			$reason = '-';
		}

		return "Notifikasi Pengajuan Baru\n".
			"Jenis: ".$type_label."\n".
			"ID: ".$request_id."\n".
			"Karyawan: ".$display_name." (".$username.")\n".
			"Periode: ".$start_date_label." s/d ".$end_date_label." (".$duration_days." hari)\n".
			"Alasan: ".$reason."\n".
			"Waktu submit: ".date('d-m-Y H:i:s')." WIB\n".
			"Silakan buka web admin untuk proses pengajuan.\n".
			"https://absenpanha.com/home/leave_requests";
	}

	private function build_new_loan_submission_admin_whatsapp_message($request_row)
	{
		$request_row = is_array($request_row) ? $request_row : array();
		$username = isset($request_row['username']) ? trim((string) $request_row['username']) : '';
		$display_name = $this->resolve_requestor_display_name($username);
		$request_id = isset($request_row['id']) ? trim((string) $request_row['id']) : '-';
		$request_date_label = isset($request_row['request_date_label']) ? trim((string) $request_row['request_date_label']) : date('d-m-Y');
		$amount = isset($request_row['amount']) ? (int) $request_row['amount'] : 0;
		$amount_label = 'Rp '.number_format(max(0, $amount), 0, ',', '.');
		$tenor_months = isset($request_row['tenor_months']) ? max(1, (int) $request_row['tenor_months']) : 1;
		$reason = isset($request_row['reason']) ? trim((string) $request_row['reason']) : '';
		if ($reason === '')
		{
			$reason = '-';
		}

		return "Notifikasi Pengajuan Baru\n".
			"Jenis: Pinjaman\n".
			"ID: ".$request_id."\n".
			"Karyawan: ".$display_name." (".$username.")\n".
			"Tanggal: ".$request_date_label."\n".
			"Nominal: ".$amount_label."\n".
			"Tenor: ".$tenor_months." bulan\n".
			"Alasan: ".$reason."\n".
			"Waktu submit: ".date('d-m-Y H:i:s')." WIB\n".
			"Silakan buka web admin untuk proses pengajuan.\n".
			"https://absenpanha.com/home/loan_requests";
	}

	private function build_new_day_off_swap_submission_admin_whatsapp_message($request_row)
	{
		$request_row = is_array($request_row) ? $request_row : array();
		$username = isset($request_row['username']) ? trim((string) $request_row['username']) : '';
		$display_name = $this->resolve_requestor_display_name($username);
		$request_id = isset($request_row['request_id']) ? trim((string) $request_row['request_id']) : '-';
		$workday_date = isset($request_row['workday_date']) ? trim((string) $request_row['workday_date']) : '';
		$offday_date = isset($request_row['offday_date']) ? trim((string) $request_row['offday_date']) : '';
		$workday_label = $workday_date !== '' ? $this->format_user_dashboard_date_label($workday_date) : '-';
		$offday_label = $offday_date !== '' ? $this->format_user_dashboard_date_label($offday_date) : '-';
		$request_branch = isset($request_row['branch']) ? trim((string) $request_row['branch']) : '';
		if ($request_branch === '')
		{
			$request_branch = $this->resolve_employee_branch_from_username($username);
		}
		if ($request_branch === '')
		{
			$request_branch = '-';
		}
		$note = isset($request_row['note']) ? trim((string) $request_row['note']) : '';
		if ($note === '')
		{
			$note = '-';
		}
		$requested_at = isset($request_row['requested_at']) ? trim((string) $request_row['requested_at']) : '';
		$requested_at_ts = $requested_at !== '' ? strtotime($requested_at) : FALSE;
		$requested_at_label = $requested_at_ts !== FALSE ? date('d-m-Y H:i:s', $requested_at_ts) : date('d-m-Y H:i:s');

		return "Notifikasi Pengajuan Baru\n".
			"Jenis: Tukar Hari Libur (1x)\n".
			"ID: ".$request_id."\n".
			"Karyawan: ".$display_name." (".$username.")\n".
			"Cabang: ".$request_branch."\n".
			"Libur asli (jadi masuk): ".$workday_label."\n".
			"Libur pengganti (jadi libur): ".$offday_label."\n".
			"Alasan: ".$note."\n".
			"Waktu submit: ".$requested_at_label." WIB\n".
			"Silakan buka web admin untuk proses pengajuan.\n".
			"https://absenpanha.com/home/day_off_swap_requests";
	}

	private function notify_admin_new_submission($submission_type, $request_row)
	{
		if ($this->submission_notify_enabled() !== TRUE)
		{
			return array(
				'success' => FALSE,
				'message' => 'Notifikasi pengajuan baru dinonaktifkan.'
			);
		}

		$target_phone = $this->resolve_submission_notify_admin_phone();
		if ($target_phone === '')
		{
			return array(
				'success' => FALSE,
				'message' => 'Nomor WA tujuan notifikasi pengajuan belum diatur.'
			);
		}

		$type_key = strtolower(trim((string) $submission_type));
		if ($type_key === 'loan')
		{
			$message = $this->build_new_loan_submission_admin_whatsapp_message($request_row);
		}
		elseif ($type_key === 'day_off_swap' || $type_key === 'swap_day_off' || $type_key === 'day_off_swap_request')
		{
			$message = $this->build_new_day_off_swap_submission_admin_whatsapp_message($request_row);
		}
		else
		{
			$message = $this->build_new_leave_submission_admin_whatsapp_message($request_row);
		}

		return $this->send_whatsapp_notification($target_phone, $message);
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

	private function build_day_off_swap_status_whatsapp_message($request_row)
	{
		$request_row = is_array($request_row) ? $request_row : array();
		$username_key = $this->normalize_username_key(isset($request_row['username']) ? (string) $request_row['username'] : '');
		$display_name = $this->resolve_requestor_display_name($username_key);
		$status_key = strtolower(trim((string) (isset($request_row['status']) ? $request_row['status'] : 'pending')));
		$status_label = strtoupper($this->day_off_swap_request_status_label($status_key));
		$workday_date = isset($request_row['workday_date']) ? trim((string) $request_row['workday_date']) : '';
		$offday_date = isset($request_row['offday_date']) ? trim((string) $request_row['offday_date']) : '';
		$workday_label = $workday_date !== '' ? $this->format_user_dashboard_date_label($workday_date) : '-';
		$offday_label = $offday_date !== '' ? $this->format_user_dashboard_date_label($offday_date) : '-';
		$note = isset($request_row['note']) ? trim((string) $request_row['note']) : '';
		$review_note = isset($request_row['review_note']) ? trim((string) $request_row['review_note']) : '';
		if ($note === '')
		{
			$note = '-';
		}
		if ($review_note === '')
		{
			$review_note = '-';
		}

		return "Halo ".$display_name.", pengajuan TUKAR HARI LIBUR kamu ".$status_label." oleh admin.\n".
			"Libur asli (jadi masuk): ".$workday_label."\n".
			"Libur pengganti (jadi libur): ".$offday_label."\n".
			"Alasan/catatan: ".$note."\n".
			"Catatan admin: ".$review_note."\n".
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
	
	    // ===== Override manual (CLI argument) =====
	    if ($slot_text !== '')
	    {
	        $slot_text = str_replace(array('.', '-', '_'), ':', $slot_text);
	
	        // format 1100 -> 11:00
	        if (preg_match('/^\d{4}$/', $slot_text))
	        {
	            $slot_text = substr($slot_text, 0, 2) . ':' . substr($slot_text, 2, 2);
	        }
	
	        // normalisasi 9:5 -> 09:05
	        if (preg_match('/^\d{1,2}\:\d{2}$/', $slot_text))
	        {
	            $parts = explode(':', $slot_text);
	            $slot_text =
	                str_pad((string)((int)$parts[0]), 2, '0', STR_PAD_LEFT)
	                . ':' .
	                str_pad((string)((int)$parts[1]), 2, '0', STR_PAD_LEFT);
	        }
	
	        return in_array($slot_text, $allowed_slots, TRUE)
	            ? $slot_text
	            : '';
	    }
	
	    // ===== Auto berdasarkan jam sekarang =====
	    date_default_timezone_set('Asia/Jakarta');
	    $current_slot = date('H:i');
	
	    return in_array($current_slot, $allowed_slots, TRUE)
	        ? $current_slot
	        : '';
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

	private function attendance_reminder_group_definitions()
	{
		$config_rows = self::ATTENDANCE_REMINDER_GROUPS;
		if (!is_array($config_rows))
		{
			return array();
		}

		$profiles = $this->employee_profile_book(TRUE);
		$active_user_lookup = $this->attendance_reminder_active_user_lookup();
		$active_profiles = array();
		foreach ($profiles as $username_key => $profile_row)
		{
			$username_value = strtolower(trim((string) $username_key));
			if ($username_value === '')
			{
				continue;
			}
			if (!empty($active_user_lookup) && !isset($active_user_lookup[$username_value]))
			{
				continue;
			}

			$active_profiles[$username_value] = is_array($profile_row) ? $profile_row : array();
		}

		$job_title_map = $this->attendance_reminder_group_job_title_map();
		$explicit_member_group_map = array();
		for ($i = 0; $i < count($config_rows); $i += 1)
		{
			$explicit_row = isset($config_rows[$i]) && is_array($config_rows[$i]) ? $config_rows[$i] : array();
			$explicit_key = $this->normalize_attendance_reminder_group_key(
				isset($explicit_row['key']) ? (string) $explicit_row['key'] : ''
			);
			if ($explicit_key === '')
			{
				$explicit_key = 'group_'.($i + 1);
			}
			$explicit_members = isset($explicit_row['members']) && is_array($explicit_row['members'])
				? $explicit_row['members']
				: array();
			for ($member_index = 0; $member_index < count($explicit_members); $member_index += 1)
			{
				$member_key = $this->attendance_reminder_display_name_key((string) $explicit_members[$member_index]);
				if ($member_key === '' || isset($explicit_member_group_map[$member_key]))
				{
					continue;
				}
				$explicit_member_group_map[$member_key] = $explicit_key;
			}
		}

		$groups = array();
		for ($i = 0; $i < count($config_rows); $i += 1)
		{
			$row = isset($config_rows[$i]) && is_array($config_rows[$i]) ? $config_rows[$i] : array();
			$raw_key = isset($row['key']) ? (string) $row['key'] : '';
			$group_key = $this->normalize_attendance_reminder_group_key($raw_key);
			if ($group_key === '')
			{
				$group_key = 'group_'.($i + 1);
			}
			$group_name = isset($row['name']) ? trim((string) $row['name']) : '';
			if ($group_name === '')
			{
				$group_name = 'Group '.($i + 1);
			}
			$group_target = isset($row['target']) ? trim((string) $row['target']) : '';
			$group_target_env_raw = getenv('WA_ATTENDANCE_GROUP_'.($i + 1).'_TARGET');
			if ($group_target_env_raw !== FALSE)
			{
				$group_target = trim((string) $group_target_env_raw);
			}
			$members_raw = isset($row['members']) && is_array($row['members']) ? $row['members'] : array();
			$members = array();
			$member_lookup = array();
			for ($member_index = 0; $member_index < count($members_raw); $member_index += 1)
			{
				$member_name = trim((string) $members_raw[$member_index]);
				if ($member_name === '')
				{
					continue;
				}

				$member_key = $this->attendance_reminder_display_name_key($member_name);
				if ($member_key === '')
				{
					$member_key = strtolower($member_name);
				}
				if (isset($member_lookup[$member_key]))
				{
					continue;
				}
				$member_lookup[$member_key] = TRUE;
				$members[] = $member_name;
			}

			$group_job_titles = isset($job_title_map[$group_key]) && is_array($job_title_map[$group_key])
				? $job_title_map[$group_key]
				: array();
			if (!empty($group_job_titles) && !empty($active_profiles))
			{
				foreach ($active_profiles as $profile_row)
				{
					$profile = is_array($profile_row) ? $profile_row : array();
					$profile_name = isset($profile['display_name']) ? trim((string) $profile['display_name']) : '';
					if ($profile_name === '')
					{
						continue;
					}
					$profile_name_key = $this->attendance_reminder_display_name_key($profile_name);
					if ($profile_name_key === '')
					{
						continue;
					}
					if (
						isset($explicit_member_group_map[$profile_name_key]) &&
						$explicit_member_group_map[$profile_name_key] !== $group_key
					)
					{
						continue;
					}

					$profile_job_title_raw = isset($profile['job_title']) ? (string) $profile['job_title'] : '';
					$profile_job_title = $this->resolve_employee_job_title($profile_job_title_raw);
					if ($profile_job_title === '')
					{
						$profile_job_title = trim((string) $profile_job_title_raw);
					}
					if ($profile_job_title === '')
					{
						continue;
					}

					$matched_title = FALSE;
					for ($title_index = 0; $title_index < count($group_job_titles); $title_index += 1)
					{
						$title_candidate = trim((string) $group_job_titles[$title_index]);
						if ($title_candidate === '')
						{
							continue;
						}
						if (strcasecmp($profile_job_title, $title_candidate) === 0)
						{
							$matched_title = TRUE;
							break;
						}
					}
					if (!$matched_title)
					{
						continue;
					}

					if (!isset($member_lookup[$profile_name_key]))
					{
						$member_lookup[$profile_name_key] = TRUE;
						$members[] = $profile_name;
					}
				}
			}
			if (empty($members))
			{
				continue;
			}
			if ($group_target === '')
			{
				continue;
			}

			$groups[] = array(
				'key' => $group_key,
				'name' => $group_name,
				'target' => $group_target,
				'members' => $members
			);
		}

		return $groups;
	}

	private function attendance_reminder_group_job_title_map()
	{
		$raw_map = self::ATTENDANCE_REMINDER_GROUP_JOB_TITLE_MAP;
		if (!is_array($raw_map) || empty($raw_map))
		{
			return array();
		}

		$normalized_map = array();
		$group_keys = array_keys($raw_map);
		for ($i = 0; $i < count($group_keys); $i += 1)
		{
			$raw_group_key = isset($group_keys[$i]) ? (string) $group_keys[$i] : '';
			$group_key = $this->normalize_attendance_reminder_group_key($raw_group_key);
			if ($group_key === '')
			{
				continue;
			}

			$titles_raw = isset($raw_map[$group_keys[$i]]) ? $raw_map[$group_keys[$i]] : array();
			if (!is_array($titles_raw))
			{
				$titles_raw = array($titles_raw);
			}

			$titles = array();
			for ($title_index = 0; $title_index < count($titles_raw); $title_index += 1)
			{
				$title_raw = trim((string) $titles_raw[$title_index]);
				if ($title_raw === '')
				{
					continue;
				}
				$title_value = $this->resolve_employee_job_title($title_raw);
				if ($title_value === '')
				{
					$title_value = $title_raw;
				}
				if ($title_value === '')
				{
					continue;
				}

				$exists = FALSE;
				for ($existing_index = 0; $existing_index < count($titles); $existing_index += 1)
				{
					if (strcasecmp($title_value, (string) $titles[$existing_index]) === 0)
					{
						$exists = TRUE;
						break;
					}
				}
				if (!$exists)
				{
					$titles[] = $title_value;
				}
			}

			if (!empty($titles))
			{
				$normalized_map[$group_key] = $titles;
			}
		}

		return $normalized_map;
	}

	private function normalize_attendance_reminder_group_key($value)
	{
		$key = strtolower(trim((string) $value));
		if ($key === '')
		{
			return '';
		}
		$key = preg_replace('/[^a-z0-9_]+/', '_', $key);
		$key = trim((string) $key, '_');
		return $key;
	}

	private function attendance_reminder_group_state_key($date_key, $slot, $group_key)
	{
		$date_value = trim((string) $date_key);
		if (!$this->is_valid_date_format($date_value))
		{
			$date_value = date('Y-m-d');
		}
		$slot_value = trim((string) $slot);
		$group_value = $this->normalize_attendance_reminder_group_key($group_key);
		if ($group_value === '')
		{
			$group_value = 'group';
		}

		return $date_value.'|'.$slot_value.'|'.$group_value;
	}

	private function attendance_reminder_name_tokens($value)
	{
		$text = strtolower(trim((string) $value));
		if ($text === '')
		{
			return array();
		}

		$text = preg_replace('/[^a-z0-9]+/', ' ', $text);
		$text = trim((string) $text);
		if ($text === '')
		{
			return array();
		}

		$parts = explode(' ', $text);
		$tokens = array();
		for ($i = 0; $i < count($parts); $i += 1)
		{
			$token = trim((string) $parts[$i]);
			if ($token === '')
			{
				continue;
			}
			if (!in_array($token, $tokens, TRUE))
			{
				$tokens[] = $token;
			}
		}

		return $tokens;
	}

	private function attendance_reminder_display_name_key($value)
	{
		$tokens = $this->attendance_reminder_name_tokens($value);
		if (empty($tokens))
		{
			return '';
		}

		return implode(' ', $tokens);
	}

	private function attendance_reminder_pick_best_row($rows)
	{
		$candidates = is_array($rows) ? array_values($rows) : array();
		if (empty($candidates))
		{
			return array();
		}

		usort($candidates, function ($left, $right) {
			$left_present = isset($left['is_present']) && $left['is_present'] === TRUE ? 1 : 0;
			$right_present = isset($right['is_present']) && $right['is_present'] === TRUE ? 1 : 0;
			if ($left_present !== $right_present)
			{
				return $right_present - $left_present;
			}

			$left_leave = isset($left['is_leave_today']) && $left['is_leave_today'] === TRUE ? 1 : 0;
			$right_leave = isset($right['is_leave_today']) && $right['is_leave_today'] === TRUE ? 1 : 0;
			if ($left_leave !== $right_leave)
			{
				return $left_leave - $right_leave;
			}

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

		return isset($candidates[0]) && is_array($candidates[0]) ? $candidates[0] : array();
	}

	private function attendance_reminder_record_identity_values($row)
	{
		$row = is_array($row) ? $row : array();
		$candidate_keys = array(
			'username',
			'display_name',
			'employee_name',
			'name',
			'full_name',
			'employee_id'
		);
		$identities = array();
		for ($i = 0; $i < count($candidate_keys); $i += 1)
		{
			$key = (string) $candidate_keys[$i];
			$value = isset($row[$key]) ? trim((string) $row[$key]) : '';
			if ($value === '' || $value === '-' || in_array($value, $identities, TRUE))
			{
				continue;
			}
			$identities[] = $value;
		}

		return $identities;
	}

	private function attendance_reminder_active_user_lookup()
	{
		$lookup = array();
		$accounts = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (!is_array($accounts) || empty($accounts))
		{
			return $lookup;
		}

		foreach ($accounts as $username_key => $account_row)
		{
			$username = strtolower(trim((string) $username_key));
			if ($username === '' || !is_array($account_row) || $this->is_reserved_system_username($username))
			{
				continue;
			}

			$role = strtolower(trim((string) (isset($account_row['role']) ? $account_row['role'] : 'user')));
			if ($role !== 'user')
			{
				continue;
			}

			$status_raw = strtolower(trim((string) (isset($account_row['employee_status']) ? $account_row['employee_status'] : 'aktif')));
			$is_inactive = $status_raw !== '' && (
				strpos($status_raw, 'nonaktif') !== FALSE ||
				strpos($status_raw, 'inactive') !== FALSE ||
				strpos($status_raw, 'inaktif') !== FALSE ||
				strpos($status_raw, 'disable') !== FALSE
			);
			if ($is_inactive)
			{
				continue;
			}

			$lookup[$username] = TRUE;
		}

		return $lookup;
	}

	private function attendance_reminder_member_username_override($member_name = '')
	{
		$member_key = $this->normalize_username_key($member_name);
		if ($member_key === '')
		{
			return '';
		}

		$map = self::ATTENDANCE_REMINDER_MEMBER_USERNAME_OVERRIDES;
		if (!is_array($map) || empty($map))
		{
			return '';
		}

		if (isset($map[$member_key]))
		{
			$override_username = $this->normalize_username_key((string) $map[$member_key]);
			return $override_username;
		}

		$keys = array_keys($map);
		for ($i = 0; $i < count($keys); $i += 1)
		{
			$map_key = $this->normalize_username_key((string) $keys[$i]);
			if ($map_key === '' || $map_key !== $member_key)
			{
				continue;
			}
			$override_username = $this->normalize_username_key((string) $map[$keys[$i]]);
			if ($override_username !== '')
			{
				return $override_username;
			}
		}

		return '';
	}

	private function attendance_reminder_group_row_identity_key($row, $fallback_name = '')
	{
		$row = is_array($row) ? $row : array();

		$row_username = isset($row['username']) ? $this->normalize_username_key((string) $row['username']) : '';
		if ($row_username !== '')
		{
			return 'username:'.$row_username;
		}

		$row_employee_id = isset($row['employee_id']) ? trim((string) $row['employee_id']) : '';
		if ($row_employee_id !== '' && $row_employee_id !== '-')
		{
			return 'employee_id:'.strtolower($row_employee_id);
		}

		$row_display_name = isset($row['display_name']) ? (string) $row['display_name'] : '';
		$row_display_name_key = $this->attendance_reminder_display_name_key($row_display_name);
		if ($row_display_name_key !== '')
		{
			return 'display_name:'.$row_display_name_key;
		}

		$fallback_name_key = $this->attendance_reminder_display_name_key($fallback_name);
		if ($fallback_name_key !== '')
		{
			return 'fallback_name:'.$fallback_name_key;
		}

		return '';
	}

	private function attendance_reminder_group_row_quality_score($row)
	{
		$row = is_array($row) ? $row : array();
		$score = 0;

		$row_username = isset($row['username']) ? $this->normalize_username_key((string) $row['username']) : '';
		if ($row_username !== '')
		{
			$score += 8;
		}

		$row_employee_id = isset($row['employee_id']) ? trim((string) $row['employee_id']) : '';
		if ($row_employee_id !== '' && $row_employee_id !== '-')
		{
			$score += 4;
		}

		$row_display_name = isset($row['display_name']) ? trim((string) $row['display_name']) : '';
		if ($row_display_name !== '')
		{
			$score += 2;
		}

		$row_phone = isset($row['phone']) ? trim((string) $row['phone']) : '';
		if ($row_phone !== '')
		{
			$score += 1;
		}

		return $score;
	}

	private function build_attendance_reminder_group_payload($base_payload, $members, $group_name = '')
	{
		$base_payload = is_array($base_payload) ? $base_payload : array();
		$base_rows = isset($base_payload['rows']) && is_array($base_payload['rows'])
			? $base_payload['rows']
			: array();
		$members = is_array($members) ? $members : array();

		$rows_by_display_name = array();
		$rows_by_username = array();
		$rows_by_employee_id = array();
		for ($i = 0; $i < count($base_rows); $i += 1)
		{
			$row = isset($base_rows[$i]) && is_array($base_rows[$i]) ? $base_rows[$i] : array();
			$row_username = strtolower(trim((string) (isset($row['username']) ? $row['username'] : '')));
			if ($row_username !== '')
			{
				if (!isset($rows_by_username[$row_username]) || !is_array($rows_by_username[$row_username]))
				{
					$rows_by_username[$row_username] = array();
				}
				$rows_by_username[$row_username][] = $row;

				$username_normalized = $this->normalize_username_key($row_username);
				if ($username_normalized !== '')
				{
					if (!isset($rows_by_username[$username_normalized]) || !is_array($rows_by_username[$username_normalized]))
					{
						$rows_by_username[$username_normalized] = array();
					}
					$rows_by_username[$username_normalized][] = $row;
				}
			}

			$row_display_name = trim((string) (isset($row['display_name']) ? $row['display_name'] : ''));
			if ($row_display_name !== '')
			{
				$row_display_name_key = $this->attendance_reminder_display_name_key($row_display_name);
				if ($row_display_name_key !== '')
				{
					if (!isset($rows_by_display_name[$row_display_name_key]) || !is_array($rows_by_display_name[$row_display_name_key]))
					{
						$rows_by_display_name[$row_display_name_key] = array();
					}
					$rows_by_display_name[$row_display_name_key][] = $row;
				}
			}

			$row_employee_id = trim((string) (isset($row['employee_id']) ? $row['employee_id'] : ''));
			if ($row_employee_id !== '' && $row_employee_id !== '-')
			{
				$row_employee_id_key = strtolower($row_employee_id);
				if ($row_employee_id_key === '')
				{
					continue;
				}
				if (!isset($rows_by_employee_id[$row_employee_id_key]) || !is_array($rows_by_employee_id[$row_employee_id_key]))
				{
					$rows_by_employee_id[$row_employee_id_key] = array();
				}
				$rows_by_employee_id[$row_employee_id_key][] = $row;
			}
		}

		$rows = array();
		$rows_by_identity = array();
		for ($i = 0; $i < count($members); $i += 1)
		{
			$member_name = trim((string) $members[$i]);
			if ($member_name === '')
			{
				continue;
			}

			$matched_row = array();
			$member_name_key = $this->attendance_reminder_display_name_key($member_name);
			if ($member_name_key !== '' && isset($rows_by_display_name[$member_name_key]) && is_array($rows_by_display_name[$member_name_key]))
			{
				$matched_row = $this->attendance_reminder_pick_best_row($rows_by_display_name[$member_name_key]);
			}

			if (empty($matched_row))
			{
				$member_employee_id_key = strtolower(trim((string) $member_name));
				if (
					$member_employee_id_key !== '' &&
					$member_employee_id_key !== '-' &&
					preg_match('/^\d+$/', $member_employee_id_key) === 1 &&
					isset($rows_by_employee_id[$member_employee_id_key]) &&
					is_array($rows_by_employee_id[$member_employee_id_key])
				)
				{
					$matched_row = $this->attendance_reminder_pick_best_row($rows_by_employee_id[$member_employee_id_key]);
				}
			}

			if (empty($matched_row))
			{
				$member_tokens = $this->attendance_reminder_name_tokens($member_name);
				if (!empty($member_tokens))
				{
					$best_rows = array();
					$best_score = 0;
					$best_total_gap = 9999;
					for ($row_index = 0; $row_index < count($base_rows); $row_index += 1)
					{
						$candidate_row = isset($base_rows[$row_index]) && is_array($base_rows[$row_index]) ? $base_rows[$row_index] : array();
						if (empty($candidate_row))
						{
							continue;
						}

						$candidate_display_name = isset($candidate_row['display_name']) ? (string) $candidate_row['display_name'] : '';
						$candidate_tokens = $this->attendance_reminder_name_tokens($candidate_display_name);
						if (empty($candidate_tokens))
						{
							continue;
						}

						$common_tokens = array_intersect($member_tokens, $candidate_tokens);
						$common_score = count($common_tokens);
						if ($common_score <= 0)
						{
							continue;
						}

						$total_gap = abs(count($member_tokens) - count($candidate_tokens));
						if (
							$common_score > $best_score ||
							($common_score === $best_score && $total_gap < $best_total_gap)
						)
						{
							$best_rows = array($candidate_row);
							$best_score = $common_score;
							$best_total_gap = $total_gap;
						}
						elseif ($common_score === $best_score && $total_gap === $best_total_gap)
						{
							$best_rows[] = $candidate_row;
						}
					}

					if ($best_score >= 1)
					{
						$matched_row = $this->attendance_reminder_pick_best_row($best_rows);
					}
				}
			}

			if (empty($matched_row))
			{
				$member_override_username = $this->attendance_reminder_member_username_override($member_name);
				if ($member_override_username !== '')
				{
					$override_aliases = array(
						strtolower($member_override_username),
						$this->normalize_username_key($member_override_username)
					);
					for ($alias_index = 0; $alias_index < count($override_aliases); $alias_index += 1)
					{
						$alias = trim((string) $override_aliases[$alias_index]);
						if ($alias === '' || !isset($rows_by_username[$alias]) || !is_array($rows_by_username[$alias]))
						{
							continue;
						}
						$matched_row = $this->attendance_reminder_pick_best_row($rows_by_username[$alias]);
						if (!empty($matched_row))
						{
							break;
						}
					}
				}
			}

			$has_matched_row = !empty($matched_row);

			$row_username = isset($matched_row['username']) ? strtolower(trim((string) $matched_row['username'])) : '';
			$row_phone = isset($matched_row['phone']) ? trim((string) $matched_row['phone']) : '';
			$row_employee_id = isset($matched_row['employee_id']) ? (string) $matched_row['employee_id'] : '-';
			$row_sort_sequence = isset($matched_row['sort_sequence']) ? (int) $matched_row['sort_sequence'] : (9999 + $i);
			$row_is_present = isset($matched_row['is_present']) && $matched_row['is_present'] === TRUE;
			$row_is_leave_today = $has_matched_row
				? (isset($matched_row['is_leave_today']) && $matched_row['is_leave_today'] === TRUE)
				: FALSE;
			$row_leave_type = $has_matched_row
				? (isset($matched_row['leave_type']) ? strtolower(trim((string) $matched_row['leave_type'])) : '')
				: '';
			$row_attendance_branch = isset($matched_row['attendance_branch'])
				? $this->resolve_employee_branch((string) $matched_row['attendance_branch'])
				: '';
			$row_display_name = $member_name;
			if ($has_matched_row)
			{
				$matched_display_name = isset($matched_row['display_name']) ? trim((string) $matched_row['display_name']) : '';
				if ($matched_display_name !== '')
				{
					$row_display_name = $matched_display_name;
				}
			}

			$row_payload = array(
				'username' => $row_username,
				'display_name' => $row_display_name,
				'phone' => $row_phone,
				'employee_id' => $row_employee_id,
				'sort_sequence' => $row_sort_sequence,
				'is_present' => $row_is_present ? TRUE : FALSE,
				'is_leave_today' => $row_is_leave_today ? TRUE : FALSE,
				'leave_type' => $row_leave_type,
				'attendance_branch' => $row_attendance_branch
			);

			$row_identity_key = $this->attendance_reminder_group_row_identity_key($row_payload, $member_name);
			if ($row_identity_key !== '' && isset($rows_by_identity[$row_identity_key]))
			{
				$existing_index = (int) $rows_by_identity[$row_identity_key];
				$existing_row = isset($rows[$existing_index]) && is_array($rows[$existing_index])
					? $rows[$existing_index]
					: array();
				$existing_score = $this->attendance_reminder_group_row_quality_score($existing_row);
				$current_score = $this->attendance_reminder_group_row_quality_score($row_payload);
				if ($current_score > $existing_score)
				{
					$rows[$existing_index] = $row_payload;
				}
				elseif ($current_score === $existing_score)
				{
					$rows[$existing_index] = $this->attendance_reminder_pick_best_row(array($existing_row, $row_payload));
				}
				continue;
			}

			$rows[] = $row_payload;
			if ($row_identity_key !== '')
			{
				$rows_by_identity[$row_identity_key] = count($rows) - 1;
			}

		}

		$present_count = 0;
		$missing_count = 0;
		$alpha_names = array();
		for ($i = 0; $i < count($rows); $i += 1)
		{
			$row = isset($rows[$i]) && is_array($rows[$i]) ? $rows[$i] : array();
			$row_is_present = isset($row['is_present']) && $row['is_present'] === TRUE;
			$row_is_leave_today = isset($row['is_leave_today']) && $row['is_leave_today'] === TRUE;
			if ($row_is_present)
			{
				$present_count += 1;
				continue;
			}
			if ($row_is_leave_today)
			{
				continue;
			}

			$missing_count += 1;
			$alpha_names[] = isset($row['display_name']) ? (string) $row['display_name'] : '-';
		}

		return array(
			'group_name' => trim((string) $group_name),
			'date_key' => isset($base_payload['date_key']) ? (string) $base_payload['date_key'] : date('Y-m-d'),
			'date_label' => isset($base_payload['date_label']) ? (string) $base_payload['date_label'] : date('d-m-Y'),
			'rows' => $rows,
			'total_employees' => count($rows),
			'present_count' => $present_count,
			'missing_count' => $missing_count,
			'alpha_names' => $alpha_names,
			'alpha_count' => count($alpha_names)
		);
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

		$profiles = $this->employee_profile_book(TRUE);
		$active_user_lookup = $this->attendance_reminder_active_user_lookup();
		if (!empty($active_user_lookup))
		{
			$filtered_profiles = array();
			foreach ($profiles as $username_key => $profile_row)
			{
				$username = strtolower(trim((string) $username_key));
				if ($username === '' || !isset($active_user_lookup[$username]))
				{
					continue;
				}
				$filtered_profiles[$username] = is_array($profile_row) ? $profile_row : array();
			}
			$profiles = $filtered_profiles;
		}

		$employee_lookup = array();
		$employee_alias_lookup = array();
		$employee_id_book = $this->employee_id_book();
		foreach ($profiles as $username_key => $profile)
		{
			$normalized_username = strtolower(trim((string) $username_key));
			if ($normalized_username === '')
			{
				continue;
			}
			$employee_lookup[$normalized_username] = TRUE;
			$employee_alias_lookup[$normalized_username] = $normalized_username;
			$username_normalized_key = $this->normalize_username_key($normalized_username);
			if ($username_normalized_key !== '' && !isset($employee_alias_lookup[$username_normalized_key]))
			{
				$employee_alias_lookup[$username_normalized_key] = $normalized_username;
			}
			$display_name = isset($profile['display_name']) ? trim((string) $profile['display_name']) : '';
			$display_name_key = strtolower($display_name);
			if ($display_name_key !== '' && !isset($employee_alias_lookup[$display_name_key]))
			{
				$employee_alias_lookup[$display_name_key] = $normalized_username;
			}
			$display_name_normalized = $this->normalize_username_key($display_name);
			if ($display_name_normalized !== '' && !isset($employee_alias_lookup[$display_name_normalized]))
			{
				$employee_alias_lookup[$display_name_normalized] = $normalized_username;
			}
			$employee_id = $this->resolve_employee_id_from_book($normalized_username, $employee_id_book);
			if ($employee_id !== '-' && $employee_id !== '' && !isset($employee_alias_lookup[$employee_id]))
			{
				$employee_alias_lookup[$employee_id] = $normalized_username;
			}
		}

		$present_by_username = array();
		$attendance_branch_by_username = array();
		$records = $this->load_attendance_records();
		for ($i = 0; $i < count($records); $i += 1)
		{
			$row = isset($records[$i]) && is_array($records[$i]) ? $records[$i] : array();
			$row_username = '';
			$identity_values = $this->attendance_reminder_record_identity_values($row);
			for ($identity_index = 0; $identity_index < count($identity_values); $identity_index += 1)
			{
				$identity_value = isset($identity_values[$identity_index]) ? (string) $identity_values[$identity_index] : '';
				if ($identity_value === '')
				{
					continue;
				}
				$resolved_username = $this->resolve_admin_metric_employee_username_key(
					$identity_value,
					$employee_lookup,
					$employee_alias_lookup
				);
				if ($resolved_username !== '')
				{
					$row_username = $resolved_username;
					break;
				}
			}
			if ($row_username === '')
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
				$row_branch = '';
				$branch_candidates = array(
					isset($row['branch']) ? (string) $row['branch'] : '',
					isset($row['branch_attendance']) ? (string) $row['branch_attendance'] : '',
					isset($row['branch_origin']) ? (string) $row['branch_origin'] : ''
				);
				for ($branch_index = 0; $branch_index < count($branch_candidates); $branch_index += 1)
				{
					$resolved_branch = $this->resolve_employee_branch($branch_candidates[$branch_index]);
					if ($resolved_branch !== '')
					{
						$row_branch = $resolved_branch;
						break;
					}
				}
				if ($row_branch !== '')
				{
					$attendance_branch_by_username[$row_username] = $row_branch;
				}
			}
		}

		$leave_result = $this->build_admin_leave_map($employee_lookup, $employee_alias_lookup);
		$leave_by_date = isset($leave_result['by_date']) && is_array($leave_result['by_date'])
			? $leave_result['by_date']
			: array();
		$day_leave_map = isset($leave_by_date[$date_value]) && is_array($leave_by_date[$date_value])
			? $leave_by_date[$date_value]
			: array();
		$rows = array();
		foreach ($profiles as $username_key => $profile)
		{
			$username_value = strtolower(trim((string) $username_key));
			if ($username_value === '')
			{
				continue;
			}
			$user_weekly_off = isset($profile['weekly_day_off'])
				? $this->resolve_employee_weekly_day_off($profile['weekly_day_off'])
				: $this->default_weekly_day_off();
			$is_scheduled_workday = $this->is_employee_scheduled_workday($username_value, $date_value, $user_weekly_off);

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
			$is_leave_today = $leave_type === 'izin' || $leave_type === 'cuti' || !$is_scheduled_workday;
			if (!$is_scheduled_workday && $leave_type === '')
			{
				$leave_type = 'offday';
			}
			$is_present = isset($present_by_username[$username_value]);
			$attendance_branch = isset($attendance_branch_by_username[$username_value])
				? (string) $attendance_branch_by_username[$username_value]
				: '';
			$rows[] = array(
				'username' => $username_value,
				'display_name' => $display_name,
				'phone' => $phone_value,
				'employee_id' => $employee_id,
				'sort_sequence' => $sort_sequence,
				'is_present' => $is_present ? TRUE : FALSE,
				'is_leave_today' => $is_leave_today ? TRUE : FALSE,
				'leave_type' => $leave_type,
				'attendance_branch' => $attendance_branch
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
			elseif ($is_leave_today)
			{
				continue;
			}
			else
			{
				$missing_count += 1;
				$alpha_names[] = isset($rows[$i]['display_name']) ? (string) $rows[$i]['display_name'] : '-';
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

	private function build_attendance_reminder_group_message($payload, $slot, $group_name = '')
	{
		$slot_label = trim((string) $slot);
		$rows = isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : array();
		$resolved_group_name = trim((string) $group_name);
		if ($resolved_group_name === '')
		{
			$resolved_group_name = isset($payload['group_name']) ? trim((string) $payload['group_name']) : '';
		}

		$present_names = array();
		$alpha_names = array();
		$leave_names = array();
		for ($i = 0; $i < count($rows); $i += 1)
		{
			$row = isset($rows[$i]) && is_array($rows[$i]) ? $rows[$i] : array();
			$row_name = isset($row['display_name']) ? trim((string) $row['display_name']) : '';
			if ($row_name === '')
			{
				continue;
			}

			$row_is_present = isset($row['is_present']) && $row['is_present'] === TRUE;
			$row_is_leave_today = isset($row['is_leave_today']) && $row['is_leave_today'] === TRUE;
			if ($row_is_present)
			{
				$present_names[] = $row_name;
				continue;
			}
			if ($row_is_leave_today)
			{
				$leave_names[] = $row_name;
				continue;
			}

			$alpha_names[] = $row_name;
		}

		$total_employees = count($rows);
		$total_present = count($present_names);
		$total_alpha = count($alpha_names);
		$total_leave = count($leave_names);

		$render_name_list = function ($list) {
			$names = is_array($list) ? array_values($list) : array();
			if (empty($names))
			{
				return array('- Tidak ada');
			}

			$rendered = array();
			for ($index = 0; $index < count($names); $index += 1)
			{
				$rendered[] = ($index + 1).'. '.(string) $names[$index];
			}
			return $rendered;
		};

		$lines = array();
		$lines[] = 'LAPORAN ABSENSI HARI INI';
		if ($resolved_group_name !== '')
		{
			$lines[] = strtoupper($resolved_group_name);
		}
		$lines[] = '';
		$lines[] = 'Jam Cek: '.$slot_label.' WIB';
		$lines[] = '';
		$lines[] = '====================================';
		$lines[] = '';
		$lines[] = 'Ringkasan:';
		$lines[] = 'Total Karyawan Aktif : '.$total_employees;
		$lines[] = 'Sudah Absen          : '.$total_present;
		$lines[] = 'Belum Absen (Alpha)  : '.$total_alpha;
		$lines[] = 'Libur                : '.$total_leave;
		$lines[] = '';
		$lines[] = '====================================';
		$lines[] = '';
		$lines[] = 'Belum Absen:';
		$lines = array_merge($lines, $render_name_list($alpha_names));
		$lines[] = '';
		$lines[] = '====================================';
		$lines[] = '';
		$lines[] = 'Sudah Absen:';
		$lines = array_merge($lines, $render_name_list($present_names));
		$lines[] = '';
		$lines[] = '====================================';
		$lines[] = '';
		$lines[] = 'Libur:';
		$lines = array_merge($lines, $render_name_list($leave_names));

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
		if ($this->is_final_attendance_reminder_slot($slot_label))
		{
			return "Halo ".$name.",\nSampai last call jam ".$slot_label." WIB kamu belum absen masuk hari ini.\nMohon segera konfirmasi ke admin jika ada kendala.\nTerima kasih.";
		}

		return "Halo ".$name.",\nSampai jam ".$slot_label." WIB kamu belum melakukan absen masuk hari ini.\nMohon segera absen dari dashboard.\nTerima kasih.";
	}

	private function is_final_attendance_reminder_slot($slot_label)
	{
		$target_slot = trim((string) $slot_label);
		if (!preg_match('/^\d{2}\:\d{2}$/', $target_slot))
		{
			return FALSE;
		}

		$slots = self::ATTENDANCE_REMINDER_SLOTS;
		$final_slot = '';
		$final_slot_minutes = -1;
		for ($i = 0; $i < count($slots); $i += 1)
		{
			$slot_value = isset($slots[$i]) ? trim((string) $slots[$i]) : '';
			if (!preg_match('/^\d{2}\:\d{2}$/', $slot_value))
			{
				continue;
			}

			$parts = explode(':', $slot_value);
			$slot_minutes = ((int) $parts[0]) * 60 + ((int) $parts[1]);
			if ($slot_minutes > $final_slot_minutes)
			{
				$final_slot_minutes = $slot_minutes;
				$final_slot = $slot_value;
			}
		}

		return $final_slot !== '' && $target_slot === $final_slot;
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

	private function calculate_late_duration($check_in_time, $shift_time, $shift_name = '')
	{
		$check_in_time = trim((string) $check_in_time);
		$shift_time = trim((string) $shift_time);
		$shift_name = trim((string) $shift_name);
		if ($check_in_time === '')
		{
			return '00:00:00';
		}
		$shift_key = $this->resolve_shift_key_from_shift_values($shift_name, $shift_time);
		if ($shift_key === 'multishift')
		{
			return $this->calculate_multishift_late_duration($check_in_time);
		}
		$check_seconds = strtotime('1970-01-01 '.$check_in_time);
		if ($check_seconds === FALSE)
		{
			return '00:00:00';
		}

		$late_anchor_time = '';
		if ($shift_key === 'pagi')
		{
			// Shift pagi: belum telat sampai 08:00, telat mulai 08:01.
			$late_anchor_time = '08:00:00';
		}
		elseif ($shift_key === 'siang')
		{
			// Shift siang: belum telat sampai 14:00, telat mulai 14:01.
			$late_anchor_time = '14:00:00';
		}
		else
		{
			if ($shift_time === '')
			{
				return '00:00:00';
			}
			if (preg_match('/(\d{2}:\d{2})/', $shift_time, $matches))
			{
				$late_anchor_time = $matches[1].':00';
			}
		}

		if ($late_anchor_time === '')
		{
			return '00:00:00';
		}

		$late_anchor_seconds = strtotime('1970-01-01 '.$late_anchor_time);
		if ($late_anchor_seconds === FALSE || $check_seconds <= $late_anchor_seconds)
		{
			return '00:00:00';
		}

		$diff = $check_seconds - $late_anchor_seconds;
		return gmdate('H:i:s', $diff);
	}

	private function calculate_multishift_late_duration($check_in_time)
	{
		$check_in_seconds = $this->time_to_seconds($check_in_time);
		if ($check_in_seconds <= 0)
		{
			return '00:00:00';
		}

		$morning_ontime_end = $this->time_to_seconds(self::MULTISHIFT_ONTIME_MORNING_END);
		$afternoon_ontime_start = $this->time_to_seconds(self::MULTISHIFT_ONTIME_AFTERNOON_START);
		$afternoon_ontime_end = $this->time_to_seconds(self::MULTISHIFT_ONTIME_AFTERNOON_END);

		if ($check_in_seconds <= $morning_ontime_end)
		{
			return '00:00:00';
		}
		if ($check_in_seconds >= $afternoon_ontime_start && $check_in_seconds <= $afternoon_ontime_end)
		{
			return '00:00:00';
		}

		$late_seconds = $check_in_seconds < $afternoon_ontime_start
			? ($check_in_seconds - $morning_ontime_end)
			: ($check_in_seconds - $afternoon_ontime_end);
		if ($late_seconds <= 0)
		{
			return '00:00:00';
		}

		return gmdate('H:i:s', $late_seconds);
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
		$username_key = strtolower(trim((string) $username));
		$weekly_day_off_n = $this->default_weekly_day_off();
		if ($weekly_day_off_override !== NULL)
		{
			$weekly_day_off_n = $this->resolve_employee_weekly_day_off($weekly_day_off_override);
		}
		else
		{
			if ($username_key !== '')
			{
				$user_profile = $this->get_employee_profile($username_key);
				$weekly_day_off_n = isset($user_profile['weekly_day_off'])
					? $this->resolve_employee_weekly_day_off($user_profile['weekly_day_off'])
					: $weekly_day_off_n;
			}
		}
		$month_policy = $this->calculate_employee_month_work_policy($username_key, $date_key, $weekly_day_off_n);
		$year = isset($month_policy['year']) ? (int) $month_policy['year'] : (int) date('Y');
		$month = isset($month_policy['month']) ? (int) $month_policy['month'] : (int) date('n');
		$weekly_leave_taken = isset($month_policy['weekly_off_days']) ? (int) $month_policy['weekly_off_days'] : 0;
		$forced_hari_effective = isset($month_policy['work_days']) ? (int) $month_policy['work_days'] : 0;
		$cache_key = implode('|', array(
			number_format((float) $salary_monthly, 2, '.', ''),
			(string) $year,
			(string) $month,
			(string) $weekly_day_off_n,
			(string) $weekly_leave_taken,
			(string) $forced_hari_effective
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
				0,
				$forced_hari_effective > 0 ? $forced_hari_effective : NULL,
				$weekly_leave_taken
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
		$total_telat_gt_4_jam = 0,
		$forced_hari_effective = NULL,
		$forced_weekly_quota = NULL
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
		if ($forced_weekly_quota !== NULL)
		{
			$weekly_quota = max(0, min($days_in_month, (int) $forced_weekly_quota));
		}
		$forced_hari_effective = $forced_hari_effective === NULL ? NULL : max(1, min($days_in_month, (int) $forced_hari_effective));
		if ($forced_hari_effective !== NULL)
		{
			$weekly_quota = max(0, $days_in_month - $forced_hari_effective);
		}
		$weekly_excess = max(0, $weekly_leave_taken - $weekly_quota);

		$annual_quota = 12;
		$remaining_leave = max(0, $annual_quota - $leave_used_before_period);
		$annual_excess = max(0, $leave_taken_this_month - $remaining_leave);

		$alpha_final = $total_alpha + $weekly_excess + $annual_excess;
		$hari_effective = $forced_hari_effective !== NULL
			? $forced_hari_effective
			: max($days_in_month - $weekly_quota, 1);
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

	private function calculate_employee_month_work_policy($username = '', $date_key = '', $weekly_day_off = NULL, $custom_schedule_override = NULL)
	{
		$policy = $this->calculate_month_work_policy($date_key, $weekly_day_off);
		$username_key = strtolower(trim((string) $username));
		if ($username_key === '')
		{
			return $policy;
		}

		$custom_schedule = $this->resolve_employee_custom_schedule($username_key, $custom_schedule_override);
		$allowed_days = $this->resolve_allowed_attendance_weekdays_for_username($username_key, $custom_schedule);
		$custom_off_ranges = isset($custom_schedule['custom_off_ranges']) ? $custom_schedule['custom_off_ranges'] : array();
		$custom_work_ranges = isset($custom_schedule['custom_work_ranges']) ? $custom_schedule['custom_work_ranges'] : array();
		if (empty($allowed_days) && empty($custom_off_ranges) && empty($custom_work_ranges))
		{
			return $policy;
		}

		static $employee_policy_cache = array();
		$year = isset($policy['year']) ? (int) $policy['year'] : (int) date('Y');
		$month = isset($policy['month']) ? (int) $policy['month'] : (int) date('n');
		$weekly_day_off_n = isset($policy['weekly_day_off'])
			? $this->resolve_employee_weekly_day_off($policy['weekly_day_off'])
			: $this->default_weekly_day_off();
		$cache_key = implode('|', array(
			$username_key,
			(string) $year,
			(string) $month,
			(string) $weekly_day_off_n,
			md5(json_encode(array(
				'allowed_days' => $allowed_days,
				'off_ranges' => $custom_off_ranges,
				'work_ranges' => $custom_work_ranges
			)))
		));
		if (isset($employee_policy_cache[$cache_key]) && is_array($employee_policy_cache[$cache_key]))
		{
			return $employee_policy_cache[$cache_key];
		}

		$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		$work_days = 0;
		for ($day = 1; $day <= $days_in_month; $day += 1)
		{
			$date_value = sprintf('%04d-%02d-%02d', $year, $month, $day);
			if ($this->is_employee_scheduled_workday($username_key, $date_value, $weekly_day_off_n, $custom_schedule))
			{
				$work_days += 1;
			}
		}

		$work_days = max($work_days, 1);
		$weekly_off_days = max(0, $days_in_month - $work_days);
		$policy['days_in_month'] = $days_in_month;
		$policy['weekly_off_days'] = $weekly_off_days;
		$policy['work_days'] = $work_days;
		$policy['custom_schedule'] = TRUE;

		$employee_policy_cache[$cache_key] = $policy;
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
		$renamed_total = (int) $renamed_related['attendance'] + (int) $renamed_related['leave'] + (int) $renamed_related['loan'] + (int) $renamed_related['overtime'] + (int) $renamed_related['day_off_swap'] + (int) $renamed_related['day_off_swap_request'];
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
		$this->collab_register_activity_event($entry);
	}

	private function collab_state_file_path()
	{
		return APPPATH.'cache/admin_collab_state.json';
	}

	private function collab_default_state()
	{
		return array(
			'revision' => 0,
			'last_event_id' => 0,
			'events' => array(),
			'last_synced_revision' => 0,
			'pending_sync' => array(),
			'sync_lock' => array(
				'active' => FALSE,
				'owner' => '',
				'context' => '',
				'token' => '',
				'started_at' => '',
				'expires_at' => ''
			)
		);
	}

	private function collab_load_state()
	{
		$file_path = $this->collab_state_file_path();
		$loaded = NULL;
		if (function_exists('absen_data_store_load_value'))
		{
			$loaded = absen_data_store_load_value(self::COLLAB_STATE_STORE_KEY, NULL, $file_path);
		}
		elseif (is_file($file_path))
		{
			$content = @file_get_contents($file_path);
			if ($content !== FALSE && trim((string) $content) !== '')
			{
				$decoded = json_decode((string) $content, TRUE);
				if (is_array($decoded))
				{
					$loaded = $decoded;
				}
			}
		}

		$state = is_array($loaded) ? $loaded : $this->collab_default_state();
		$state['revision'] = isset($state['revision']) ? max(0, (int) $state['revision']) : 0;
		$state['last_event_id'] = isset($state['last_event_id']) ? max(0, (int) $state['last_event_id']) : 0;
		$state['last_synced_revision'] = isset($state['last_synced_revision']) ? max(0, (int) $state['last_synced_revision']) : 0;
		$state['events'] = isset($state['events']) && is_array($state['events']) ? array_values($state['events']) : array();
		$state['pending_sync'] = isset($state['pending_sync']) && is_array($state['pending_sync']) ? $state['pending_sync'] : array();
		$state['sync_lock'] = isset($state['sync_lock']) && is_array($state['sync_lock']) ? $state['sync_lock'] : array();

		$this->collab_cleanup_expired_sync_lock($state, FALSE);
		return $state;
	}

	private function collab_save_state($state)
	{
		$normalized = is_array($state) ? $state : $this->collab_default_state();
		$file_path = $this->collab_state_file_path();
		if (function_exists('absen_data_store_save_value'))
		{
			$saved = absen_data_store_save_value(self::COLLAB_STATE_STORE_KEY, $normalized, $file_path);
			if ($saved)
			{
				return TRUE;
			}
		}

		$directory = dirname($file_path);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0755, TRUE);
		}
		$payload = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if (!is_string($payload) || $payload === '')
		{
			return FALSE;
		}

		return @file_put_contents($file_path, $payload) !== FALSE;
	}

	private function collab_current_revision()
	{
		$state = $this->collab_load_state();
		return isset($state['revision']) ? (int) $state['revision'] : 0;
	}

	private function collab_cleanup_expired_sync_lock(&$state, $persist = FALSE)
	{
		if (!is_array($state))
		{
			$state = $this->collab_default_state();
			return FALSE;
		}
		$lock = isset($state['sync_lock']) && is_array($state['sync_lock']) ? $state['sync_lock'] : array();
		$active = isset($lock['active']) ? (bool) $lock['active'] : FALSE;
		if (!$active)
		{
			$state['sync_lock'] = array(
				'active' => FALSE,
				'owner' => '',
				'context' => '',
				'token' => '',
				'started_at' => '',
				'expires_at' => ''
			);
			return FALSE;
		}

		$expires_at = isset($lock['expires_at']) ? trim((string) $lock['expires_at']) : '';
		$expires_ts = $expires_at !== '' ? strtotime($expires_at) : 0;
		if ($expires_ts > 0 && $expires_ts > time())
		{
			return FALSE;
		}

		$state['sync_lock'] = array(
			'active' => FALSE,
			'owner' => '',
			'context' => '',
			'token' => '',
			'started_at' => '',
			'expires_at' => ''
		);
		if ($persist)
		{
			$this->collab_save_state($state);
		}

		return TRUE;
	}

	private function collab_sync_lock_info_from_state($state)
	{
		$payload = array(
			'active' => FALSE,
			'owner' => '',
			'context' => '',
			'started_at' => '',
			'expires_at' => '',
			'remaining_seconds' => 0
		);
		if (!is_array($state))
		{
			return $payload;
		}
		$lock = isset($state['sync_lock']) && is_array($state['sync_lock']) ? $state['sync_lock'] : array();
		$active = isset($lock['active']) ? (bool) $lock['active'] : FALSE;
		if (!$active)
		{
			return $payload;
		}

		$expires_at = isset($lock['expires_at']) ? trim((string) $lock['expires_at']) : '';
		$expires_ts = $expires_at !== '' ? strtotime($expires_at) : 0;
		if ($expires_ts <= 0 || $expires_ts <= time())
		{
			return $payload;
		}

		$payload['active'] = TRUE;
		$payload['owner'] = isset($lock['owner']) ? trim((string) $lock['owner']) : '';
		$payload['context'] = isset($lock['context']) ? trim((string) $lock['context']) : '';
		$payload['started_at'] = isset($lock['started_at']) ? trim((string) $lock['started_at']) : '';
		$payload['expires_at'] = $expires_at;
		$payload['remaining_seconds'] = max(1, $expires_ts - time());
		return $payload;
	}

	private function collab_try_acquire_sync_lock($actor, $context = '')
	{
		$actor_key = strtolower(trim((string) $actor));
		if ($actor_key === '')
		{
			$actor_key = 'admin';
		}
		$state = $this->collab_load_state();
		$this->collab_cleanup_expired_sync_lock($state, FALSE);
		$lock_info = $this->collab_sync_lock_info_from_state($state);
		if (isset($lock_info['active']) && $lock_info['active'] === TRUE)
		{
			$current_owner = isset($lock_info['owner']) ? strtolower(trim((string) $lock_info['owner'])) : '';
			if ($current_owner !== '' && $current_owner !== $actor_key)
			{
				return array(
					'success' => FALSE,
					'owner' => $current_owner,
					'remaining_seconds' => isset($lock_info['remaining_seconds']) ? (int) $lock_info['remaining_seconds'] : 1
				);
			}
		}

		$token = md5($actor_key.'|'.microtime(TRUE).'|'.mt_rand(1000, 9999));
		$started_at = date('Y-m-d H:i:s');
		$expires_at = date('Y-m-d H:i:s', time() + max(30, (int) self::COLLAB_SYNC_LOCK_TTL_SECONDS));
		$state['sync_lock'] = array(
			'active' => TRUE,
			'owner' => $actor_key,
			'context' => trim((string) $context),
			'token' => $token,
			'started_at' => $started_at,
			'expires_at' => $expires_at
		);
		$this->collab_save_state($state);

		return array(
			'success' => TRUE,
			'token' => $token,
			'owner' => $actor_key,
			'started_at' => $started_at,
			'expires_at' => $expires_at
		);
	}

	private function collab_release_sync_lock($token = '', $actor = '')
	{
		$state = $this->collab_load_state();
		$lock = isset($state['sync_lock']) && is_array($state['sync_lock']) ? $state['sync_lock'] : array();
		$active = isset($lock['active']) ? (bool) $lock['active'] : FALSE;
		if (!$active)
		{
			return TRUE;
		}

		$lock_owner = isset($lock['owner']) ? strtolower(trim((string) $lock['owner'])) : '';
		$actor_key = strtolower(trim((string) $actor));
		$token_key = trim((string) $token);
		$lock_token = isset($lock['token']) ? trim((string) $lock['token']) : '';
		if ($token_key !== '' && $lock_token !== '' && !hash_equals($lock_token, $token_key))
		{
			if ($actor_key !== '' && $actor_key !== $lock_owner)
			{
				return FALSE;
			}
		}
		elseif ($token_key === '' && $actor_key !== '' && $lock_owner !== '' && $actor_key !== $lock_owner)
		{
			return FALSE;
		}

		$state['sync_lock'] = array(
			'active' => FALSE,
			'owner' => '',
			'context' => '',
			'token' => '',
			'started_at' => '',
			'expires_at' => ''
		);
		return $this->collab_save_state($state);
	}

	private function collab_should_publish_event($entry)
	{
		$entry = is_array($entry) ? $entry : array();
		$actor = strtolower(trim((string) (isset($entry['actor']) ? $entry['actor'] : '')));
		if ($actor === '' || $actor === 'system' || $actor === 'cli')
		{
			return FALSE;
		}
		$account_book = function_exists('absen_load_account_book')
			? absen_load_account_book()
			: array();
		if (!is_array($account_book) || !isset($account_book[$actor]) || !is_array($account_book[$actor]))
		{
			return FALSE;
		}
		$role = strtolower(trim((string) (isset($account_book[$actor]['role']) ? $account_book[$actor]['role'] : 'user')));
		return $role === 'admin';
	}

	private function collab_event_requires_sync($action, $source)
	{
		$action_key = strtolower(trim((string) $action));
		$source_key = strtolower(trim((string) $source));
		if ($action_key === '')
		{
			return FALSE;
		}
		if (strpos($action_key, 'sync_') === 0)
		{
			return FALSE;
		}
		return $source_key === 'web_data' || $source_key === 'account_data';
	}

	private function collab_register_activity_event($entry)
	{
		$entry = is_array($entry) ? $entry : array();
		if (!$this->collab_should_publish_event($entry))
		{
			return;
		}

		$state = $this->collab_load_state();
		$this->collab_cleanup_expired_sync_lock($state, FALSE);
		$state['revision'] = isset($state['revision']) ? max(0, (int) $state['revision']) + 1 : 1;
		$state['last_event_id'] = isset($state['last_event_id']) ? max(0, (int) $state['last_event_id']) + 1 : 1;

		$action = isset($entry['action']) ? trim((string) $entry['action']) : '';
		$source = isset($entry['source']) ? trim((string) $entry['source']) : '';
		$actor = strtolower(trim((string) (isset($entry['actor']) ? $entry['actor'] : 'admin')));
		$requires_sync = $this->collab_event_requires_sync($action, $source);
		$event_type = strpos(strtolower($action), 'sync_') === 0 ? 'sync' : 'change';

		$event = array(
			'id' => (int) $state['last_event_id'],
			'revision' => (int) $state['revision'],
			'created_at' => isset($entry['logged_at']) && trim((string) $entry['logged_at']) !== ''
				? trim((string) $entry['logged_at'])
				: date('Y-m-d H:i:s'),
			'actor' => $actor,
			'action' => $action,
			'source' => $source,
			'note' => isset($entry['note']) ? trim((string) $entry['note']) : '',
			'target_username' => isset($entry['username']) ? trim((string) $entry['username']) : '',
			'target_display_name' => isset($entry['display_name']) ? trim((string) $entry['display_name']) : '',
			'requires_sync' => $requires_sync,
			'event_type' => $event_type
		);
		$state['events'][] = $event;
		$max_events = (int) self::COLLAB_STATE_EVENT_LIMIT;
		if ($max_events < 120)
		{
			$max_events = 120;
		}
		if (count($state['events']) > $max_events)
		{
			$state['events'] = array_slice($state['events'], count($state['events']) - $max_events);
		}

		if (!isset($state['pending_sync']) || !is_array($state['pending_sync']))
		{
			$state['pending_sync'] = array();
		}
		if ($event_type === 'sync' && strtolower($action) === 'sync_web_to_sheet')
		{
			$state['last_synced_revision'] = (int) $state['revision'];
			$state['pending_sync'] = array();
		}
		elseif ($requires_sync)
		{
			$state['pending_sync'][$actor] = array(
				'revision' => (int) $state['revision'],
				'updated_at' => $event['created_at'],
				'action' => $action,
				'source' => $source
			);
		}

		$this->collab_save_state($state);
	}

	private function collab_state_feed_events($state, $since_id = 0, $limit = 25, $bootstrap = FALSE)
	{
		$events = isset($state['events']) && is_array($state['events']) ? array_values($state['events']) : array();
		$since_id = max(0, (int) $since_id);
		$limit = (int) $limit;
		if ($limit <= 0)
		{
			$limit = 25;
		}
		if ($limit > 120)
		{
			$limit = 120;
		}

		$selected = array();
		if ($since_id > 0)
		{
			for ($i = 0; $i < count($events); $i += 1)
			{
				$event_id = isset($events[$i]['id']) ? (int) $events[$i]['id'] : 0;
				if ($event_id > $since_id)
				{
					$selected[] = $events[$i];
				}
			}
		}
		elseif ($bootstrap)
		{
			$selected = $events;
		}

		if (count($selected) > $limit)
		{
			$selected = array_slice($selected, count($selected) - $limit);
		}

		$result = array();
		for ($i = 0; $i < count($selected); $i += 1)
		{
			$row = is_array($selected[$i]) ? $selected[$i] : array();
			$result[] = array(
				'id' => isset($row['id']) ? (int) $row['id'] : 0,
				'revision' => isset($row['revision']) ? (int) $row['revision'] : 0,
				'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
				'actor' => isset($row['actor']) ? (string) $row['actor'] : '',
				'action' => isset($row['action']) ? (string) $row['action'] : '',
				'source' => isset($row['source']) ? (string) $row['source'] : '',
				'note' => isset($row['note']) ? (string) $row['note'] : '',
				'target_username' => isset($row['target_username']) ? (string) $row['target_username'] : '',
				'target_display_name' => isset($row['target_display_name']) ? (string) $row['target_display_name'] : '',
				'requires_sync' => isset($row['requires_sync']) && $row['requires_sync'] ? TRUE : FALSE,
				'event_type' => isset($row['event_type']) ? (string) $row['event_type'] : 'change'
			);
		}

		return $result;
	}

	private function collab_actor_pending_sync_status($state, $actor)
	{
		$actor_key = strtolower(trim((string) $actor));
		$pending_map = isset($state['pending_sync']) && is_array($state['pending_sync']) ? $state['pending_sync'] : array();
		$last_synced_revision = isset($state['last_synced_revision']) ? (int) $state['last_synced_revision'] : 0;
		$pending_revision = 0;
		$pending_by_actor = 0;
		foreach ($pending_map as $pending_actor => $pending_row)
		{
			$pending_item = is_array($pending_row) ? $pending_row : array();
			$pending_item_revision = isset($pending_item['revision']) ? (int) $pending_item['revision'] : 0;
			if ($pending_item_revision <= $last_synced_revision)
			{
				continue;
			}
			$pending_by_actor += 1;
			if (strtolower(trim((string) $pending_actor)) === $actor_key && $pending_item_revision > $pending_revision)
			{
				$pending_revision = $pending_item_revision;
			}
		}

		return array(
			'has_pending' => $pending_revision > $last_synced_revision,
			'pending_revision' => $pending_revision,
			'last_synced_revision' => $last_synced_revision,
			'pending_actor_count' => $pending_by_actor
		);
	}

	private function assert_expected_revision_or_redirect($redirect_url = 'home', $flash_key = 'account_notice_error')
	{
		$expected_raw = $this->input->post('expected_revision', TRUE);
		if ($expected_raw === NULL || trim((string) $expected_raw) === '')
		{
			return TRUE;
		}
		$expected_revision = (int) $expected_raw;
		if ($expected_revision < 0)
		{
			$expected_revision = 0;
		}
		$current_revision = $this->collab_current_revision();
		if ($expected_revision === $current_revision)
		{
			return TRUE;
		}

		$this->session->set_flashdata(
			$flash_key,
			'Konflik versi data. Data sudah diperbarui admin lain. Muat ulang halaman lalu ulangi proses simpan/sync.'
		);
		redirect($redirect_url);
		return FALSE;
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
		$max_accuracy_m = (float) self::MAX_GPS_ACCURACY_M;
		if (stripos($office_label, 'kantor 2') !== FALSE)
		{
			$max_accuracy_m = (float) self::MAX_GPS_ACCURACY_ALT_M;
		}

		if ($distance_m <= $radius_m && $accuracy_m <= $max_accuracy_m)
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
