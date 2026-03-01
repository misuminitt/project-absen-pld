<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Login extends CI_Controller {
	const COLLAB_STATE_STORE_KEY = 'admin_collab_state';
	const WEB_MAINTENANCE_BYPASS_SESSION_KEY = 'web_maintenance_bypass_access';
	const WEB_MAINTENANCE_BYPASS_SESSION_TTL_SECONDS = 43200;
	const WEB_MAINTENANCE_BYPASS_QUERY_KEY = 'devgate';

	public function __construct()
	{
		parent::__construct();
		$this->load->library('session');
		$this->load->library('absen_sheet_sync');
		$this->load->helper('absen_account_store');
		$this->load->helper('absen_data_store');
		$web_maintenance_helper = APPPATH.'helpers/absen_web_maintenance_helper.php';
		if (is_file($web_maintenance_helper) && !is_readable($web_maintenance_helper))
		{
			@chmod($web_maintenance_helper, 0644);
			clearstatcache(TRUE, $web_maintenance_helper);
		}
		if (is_file($web_maintenance_helper) && is_readable($web_maintenance_helper))
		{
			$this->load->helper('absen_web_maintenance');
		}
		$this->enforce_web_maintenance_mode();
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
						'absen_weekly_day_off' => isset($account['weekly_day_off']) ? (int) $account['weekly_day_off'] : 1,
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
		$is_logged_in = $this->session->userdata('absen_logged_in') === TRUE;
		$role = strtolower(trim((string) $this->session->userdata('absen_role')));
		$actor = strtolower(trim((string) $this->session->userdata('absen_username')));
		if ($is_logged_in && $role === 'admin' && $actor !== '' && $this->collab_actor_has_pending_sync($actor))
		{
			$this->session->set_flashdata(
				'account_notice_error',
				'Logout ditolak. Perubahan kamu belum sync ke sheet. Jalankan "Sync Data Web ke Sheet" dulu, lalu logout.'
			);
			redirect('home');
			return;
		}

		$this->session->sess_destroy();
		redirect('login');
	}

	public function logo()
	{
		$this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		$this->output->set_header('Pragma: no-cache');

		$svg_path = FCPATH.'src/assets/pns_new.svg';
		if (is_file($svg_path))
		{
			$svg_content = @file_get_contents($svg_path);
			if ($svg_content !== FALSE && trim((string) $svg_content) !== '')
			{
				$this->output
					->set_content_type('image/svg+xml')
					->set_output($svg_content);
				return;
			}
		}

		$png_candidates = array(
			FCPATH.'src/assets/pns_new_login.png',
			FCPATH.'src/assets/pns_new.png',
			FCPATH.'src/assets/pns_logo_nav.png'
		);
		for ($i = 0; $i < count($png_candidates); $i += 1)
		{
			$png_path = (string) $png_candidates[$i];
			if (!is_file($png_path))
			{
				continue;
			}
			$png_content = @file_get_contents($png_path);
			if ($png_content === FALSE || $png_content === '')
			{
				continue;
			}
			$this->output
				->set_content_type('image/png')
				->set_output($png_content);
			return;
		}

		show_404();
	}

	private function collab_state_file_path()
	{
		return APPPATH.'cache/admin_collab_state.json';
	}

	private function web_maintenance_bypass_query_key()
	{
		return self::WEB_MAINTENANCE_BYPASS_QUERY_KEY;
	}

	private function web_maintenance_bypass_session_data()
	{
		$state = $this->session->userdata(self::WEB_MAINTENANCE_BYPASS_SESSION_KEY);
		return is_array($state) ? $state : array();
	}

	private function clear_web_maintenance_bypass_session()
	{
		$this->session->unset_userdata(self::WEB_MAINTENANCE_BYPASS_SESSION_KEY);
	}

	private function grant_web_maintenance_bypass_session($source = '')
	{
		$actor = strtolower(trim((string) $this->session->userdata('absen_username')));
		$state = array(
			'granted' => TRUE,
			'source' => trim((string) $source),
			'actor' => $actor,
			'granted_at' => date('Y-m-d H:i:s'),
			'granted_ts' => time()
		);
		$this->session->set_userdata(self::WEB_MAINTENANCE_BYPASS_SESSION_KEY, $state);
	}

	private function has_web_maintenance_bypass_session()
	{
		$state = $this->web_maintenance_bypass_session_data();
		$granted = isset($state['granted']) && $state['granted'] ? TRUE : FALSE;
		if (!$granted)
		{
			return FALSE;
		}

		$granted_ts = isset($state['granted_ts']) ? (int) $state['granted_ts'] : 0;
		$ttl = (int) self::WEB_MAINTENANCE_BYPASS_SESSION_TTL_SECONDS;
		if ($granted_ts <= 0 || $ttl <= 0)
		{
			return TRUE;
		}
		if ((time() - $granted_ts) > $ttl)
		{
			$this->clear_web_maintenance_bypass_session();
			return FALSE;
		}

		return TRUE;
	}

	private function read_web_maintenance_bypass_token_from_request()
	{
		$query_key = $this->web_maintenance_bypass_query_key();
		$token = $this->input->get($query_key, TRUE);
		if ($token !== NULL && trim((string) $token) !== '')
		{
			return trim((string) $token);
		}

		$token = $this->input->post($query_key, TRUE);
		if ($token !== NULL && trim((string) $token) !== '')
		{
			return trim((string) $token);
		}

		return '';
	}

	private function apply_web_maintenance_bypass_from_request()
	{
		$token = $this->read_web_maintenance_bypass_token_from_request();
		if ($token === '')
		{
			return FALSE;
		}
		if (!function_exists('absen_web_maintenance_verify_bypass_token'))
		{
			return FALSE;
		}
		if (!absen_web_maintenance_verify_bypass_token($token))
		{
			return FALSE;
		}

		$this->grant_web_maintenance_bypass_session('query');
		return TRUE;
	}

	private function render_web_maintenance_page()
	{
		$image_relative = 'src/assets/maintenance.png';
		$image_file_path = FCPATH.$image_relative;
		$image_url = is_file($image_file_path)
			? base_url($image_relative).'?v='.rawurlencode((string) @filemtime($image_file_path))
			: '';
		$image_data_uri = '';
		if (is_file($image_file_path) && is_readable($image_file_path))
		{
			$binary = @file_get_contents($image_file_path);
			if ($binary !== FALSE && $binary !== '')
			{
				$image_data_uri = 'data:image/png;base64,'.base64_encode($binary);
			}
		}
		$image_src = $image_data_uri !== '' ? $image_data_uri : $image_url;
		$image_html = '';
		if ($image_src !== '')
		{
			$image_html = '<img class="maintenance-image" src="'.htmlspecialchars($image_src, ENT_QUOTES, 'UTF-8').'" alt="Website Under Development">';
		}
		else
		{
			$image_html = '<div class="maintenance-fallback"><h1>Website Under Development</h1><p>Maintenance mode aktif. Mohon bersabar yaa.</p></div>';
		}
		$html = '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Website Sedang Maintenance</title><style>'
			.'html,body{margin:0;padding:0;width:100%;height:100%;background:#0d3ea8;}'
			.'body{display:flex;align-items:center;justify-content:center;font-family:Segoe UI,Arial,sans-serif;color:#fff;}'
			.'.maintenance-wrap{width:100%;height:100%;display:flex;align-items:center;justify-content:center;padding:18px;box-sizing:border-box;}'
			.'.maintenance-image{display:block;max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;user-select:none;-webkit-user-drag:none;}'
			.'.maintenance-fallback{text-align:center;max-width:560px;}'
			.'.maintenance-fallback h1{margin:0 0 12px;font-size:clamp(1.6rem,3.5vw,2.4rem);}'
			.'.maintenance-fallback p{margin:0;font-size:clamp(1rem,2.3vw,1.2rem);opacity:.92;}'
			.'</style></head><body><div class="maintenance-wrap">'.$image_html.'</div></body></html>';

		// Use 200 to avoid host/server-level 503 override pages replacing this custom maintenance view.
		$this->output->set_status_header(200);
		$this->output->set_content_type('text/html', 'utf-8');
		$this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		$this->output->set_header('Pragma: no-cache');
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		$this->output->set_output($html);
	}

	private function enforce_web_maintenance_mode()
	{
		if (is_cli())
		{
			return;
		}

		if (!function_exists('absen_web_maintenance_enabled') || !absen_web_maintenance_enabled())
		{
			return;
		}

		$this->apply_web_maintenance_bypass_from_request();
		if ($this->has_web_maintenance_bypass_session())
		{
			return;
		}

		$this->render_web_maintenance_page();
		if (isset($this->output) && is_object($this->output) && method_exists($this->output, '_display'))
		{
			$this->output->_display();
		}
		exit;
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

		$state = is_array($loaded) ? $loaded : array();
		$state['last_synced_revision'] = isset($state['last_synced_revision']) ? max(0, (int) $state['last_synced_revision']) : 0;
		$state['pending_sync'] = isset($state['pending_sync']) && is_array($state['pending_sync']) ? $state['pending_sync'] : array();
		return $state;
	}

	private function collab_actor_has_pending_sync($actor)
	{
		$actor_key = strtolower(trim((string) $actor));
		if ($actor_key === '')
		{
			return FALSE;
		}
		$state = $this->collab_load_state();
		$last_synced_revision = isset($state['last_synced_revision']) ? (int) $state['last_synced_revision'] : 0;
		$pending_map = isset($state['pending_sync']) && is_array($state['pending_sync']) ? $state['pending_sync'] : array();
		if (empty($pending_map))
		{
			return FALSE;
		}

		foreach ($pending_map as $pending_actor => $pending_row)
		{
			if (strtolower(trim((string) $pending_actor)) !== $actor_key)
			{
				continue;
			}
			$pending_item = is_array($pending_row) ? $pending_row : array();
			$pending_revision = isset($pending_item['revision']) ? (int) $pending_item['revision'] : 0;
			if ($pending_revision > $last_synced_revision)
			{
				return TRUE;
			}
		}

		return FALSE;
	}
}
