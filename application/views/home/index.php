<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$summary = isset($summary) && is_array($summary) ? $summary : array();
$recent_logs = isset($recent_logs) && is_array($recent_logs) ? $recent_logs : array();
$employee_accounts = isset($employee_accounts) && is_array($employee_accounts) ? $employee_accounts : array();
for ($employee_index = 0; $employee_index < count($employee_accounts); $employee_index += 1) {
	$employee_row = isset($employee_accounts[$employee_index]) && is_array($employee_accounts[$employee_index])
		? $employee_accounts[$employee_index]
		: array();
	$cross_branch_raw = isset($employee_row['cross_branch_enabled'])
		? $employee_row['cross_branch_enabled']
		: (isset($employee_row['lintas_cabang']) ? $employee_row['lintas_cabang'] : 0);
	if (function_exists('absen_resolve_cross_branch_enabled')) {
		$cross_branch_normalized = ((int) absen_resolve_cross_branch_enabled($cross_branch_raw)) === 1 ? 1 : 0;
	} else {
		$cross_branch_normalized = ((int) $cross_branch_raw) === 1 ? 1 : 0;
	}
	$employee_row['cross_branch_enabled'] = $cross_branch_normalized;
	$employee_row['lintas_cabang'] = $cross_branch_normalized;
	$employee_accounts[$employee_index] = $employee_row;
}
$day_off_swaps = isset($day_off_swaps) && is_array($day_off_swaps) ? array_values($day_off_swaps) : array();
$day_off_swap_requests = isset($day_off_swap_requests) && is_array($day_off_swap_requests) ? array_values($day_off_swap_requests) : array();
$username = isset($username) ? (string) $username : 'Pengguna';
$account_notice_success = isset($account_notice_success) ? trim((string) $account_notice_success) : '';
$account_notice_error = isset($account_notice_error) ? trim((string) $account_notice_error) : '';
$job_title_options = isset($job_title_options) && is_array($job_title_options) ? array_values($job_title_options) : array(
	'NOC',
	'Admin',
	'Koordinator',
	'Teknisi',
	'Marketing',
	'Debt Collector',
	'Magang'
);
$default_job_title = in_array('Teknisi', $job_title_options, TRUE)
	? 'Teknisi'
	: (isset($job_title_options[0]) ? (string) $job_title_options[0] : 'Teknisi');
$branch_options = isset($branch_options) && is_array($branch_options) ? array_values($branch_options) : array(
	'Baros',
	'Cadasari'
);
$default_branch = isset($default_branch) && trim((string) $default_branch) !== ''
	? trim((string) $default_branch)
	: (isset($branch_options[0]) ? (string) $branch_options[0] : 'Baros');
$weekly_day_off_options = isset($weekly_day_off_options) && is_array($weekly_day_off_options)
	? $weekly_day_off_options
	: array(
		1 => 'Senin',
		2 => 'Selasa',
		3 => 'Rabu',
		4 => 'Kamis',
		5 => 'Jumat',
		6 => 'Sabtu',
		7 => 'Minggu'
	);
$default_weekly_day_off = isset($default_weekly_day_off) ? (int) $default_weekly_day_off : 1;
if (!isset($weekly_day_off_options[$default_weekly_day_off])) {
	$default_weekly_day_off = 1;
}
$can_view_log_data = isset($can_view_log_data) && $can_view_log_data === TRUE;
$can_manage_accounts = isset($can_manage_accounts) && $can_manage_accounts === TRUE;
$can_process_day_off_swap_requests = isset($can_process_day_off_swap_requests) && $can_process_day_off_swap_requests === TRUE;
$can_sync_sheet_accounts = isset($can_sync_sheet_accounts) && $can_sync_sheet_accounts === TRUE;
$can_manage_feature_accounts = isset($can_manage_feature_accounts) && $can_manage_feature_accounts === TRUE;
$sync_backup_ready = isset($sync_backup_ready) && $sync_backup_ready === TRUE;
$sync_backup_status_text = isset($sync_backup_status_text) ? trim((string) $sync_backup_status_text) : '';
$sync_backup_required_directions = array(
	'sheet_to_web_attendance',
	'web_to_sheet',
	'web_to_sheet_loan',
	'sheet_loan_to_web'
);
$loan_sync_last_report = isset($loan_sync_last_report) && is_array($loan_sync_last_report)
	? $loan_sync_last_report
	: array();
$loan_sync_last_action = isset($loan_sync_last_report['action']) ? strtolower(trim((string) $loan_sync_last_report['action'])) : '';
$loan_sync_last_status = isset($loan_sync_last_report['status']) ? strtolower(trim((string) $loan_sync_last_report['status'])) : '';
$loan_sync_last_message = isset($loan_sync_last_report['message']) ? trim((string) $loan_sync_last_report['message']) : '';
$loan_sync_last_created_at = isset($loan_sync_last_report['created_at']) ? trim((string) $loan_sync_last_report['created_at']) : '';
$loan_sync_last_meta = isset($loan_sync_last_report['meta']) && is_array($loan_sync_last_report['meta'])
	? $loan_sync_last_report['meta']
	: array();
$loan_sync_last_action_label_map = array(
	'sync_web_to_loan_sheet' => 'Sync Data Web ke Pinjaman',
	'sync_loan_sheet_to_web' => 'Sync Data Pinjaman ke Web'
);
$loan_sync_last_action_label = isset($loan_sync_last_action_label_map[$loan_sync_last_action])
	? $loan_sync_last_action_label_map[$loan_sync_last_action]
	: ($loan_sync_last_action !== '' ? str_replace('_', ' ', $loan_sync_last_action) : '-');
$loan_sync_last_detail_text = '';
if (isset($loan_sync_last_meta['payload']) && is_array($loan_sync_last_meta['payload']))
{
	$payload_meta = $loan_sync_last_meta['payload'];
	$loan_sync_last_detail_text .= 'payload='.
		'prepared='.(int) (isset($payload_meta['prepared']) ? $payload_meta['prepared'] : 0).
		', status_skip='.(int) (isset($payload_meta['skipped_status_not_accepted']) ? $payload_meta['skipped_status_not_accepted'] : 0).
		', sheet_skip='.(int) (isset($payload_meta['skipped_sheet_origin']) ? $payload_meta['skipped_sheet_origin'] : 0).
		', synced_skip='.(int) (isset($payload_meta['skipped_already_synced']) ? $payload_meta['skipped_already_synced'] : 0).
		', scope_skip='.(int) (isset($payload_meta['skipped_out_of_scope']) ? $payload_meta['skipped_out_of_scope'] : 0).
		', nominal_skip='.(int) (isset($payload_meta['skipped_amount_invalid']) ? $payload_meta['skipped_amount_invalid'] : 0);
}
if (isset($loan_sync_last_meta['written_rows']) || isset($loan_sync_last_meta['skipped_rows']))
{
	$loan_sync_last_detail_text .= ($loan_sync_last_detail_text !== '' ? ' | ' : '').
		'result='.
		'written='.(int) (isset($loan_sync_last_meta['written_rows']) ? $loan_sync_last_meta['written_rows'] : 0).
		', skipped='.(int) (isset($loan_sync_last_meta['skipped_rows']) ? $loan_sync_last_meta['skipped_rows'] : 0);
}
if (isset($loan_sync_last_meta['skip_reasons']) && is_array($loan_sync_last_meta['skip_reasons']) && !empty($loan_sync_last_meta['skip_reasons']))
{
	$skip_reason_text_rows = array();
	foreach ($loan_sync_last_meta['skip_reasons'] as $skip_reason_key => $skip_reason_count)
	{
		$skip_reason_text_rows[] = str_replace('_', ' ', (string) $skip_reason_key).'='.(int) $skip_reason_count;
	}
	$loan_sync_last_detail_text .= ($loan_sync_last_detail_text !== '' ? ' | ' : '').'skip_reason=['.implode(', ', $skip_reason_text_rows).']';
}
if (isset($loan_sync_last_meta['spreadsheet_id']) || isset($loan_sync_last_meta['sheet_gid']) || isset($loan_sync_last_meta['sheet']))
{
	$loan_sync_last_detail_text .= ($loan_sync_last_detail_text !== '' ? ' | ' : '').
		'target='.
		'id='.(isset($loan_sync_last_meta['spreadsheet_id']) ? (string) $loan_sync_last_meta['spreadsheet_id'] : '-').
		', gid='.(isset($loan_sync_last_meta['sheet_gid']) ? (int) $loan_sync_last_meta['sheet_gid'] : 0).
		', title='.(isset($loan_sync_last_meta['sheet']) ? (string) $loan_sync_last_meta['sheet'] : '-');
}
$sync_backup_required_button_attrs = $sync_backup_ready
	? ''
	: ' aria-disabled="true" title="Wajib backup local dulu sebelum sync."';
$dashboard_navbar_title = isset($dashboard_navbar_title) && trim((string) $dashboard_navbar_title) !== ''
	? trim((string) $dashboard_navbar_title)
	: 'Dashboard Admin Absen';
$dashboard_role_label = isset($dashboard_role_label) && trim((string) $dashboard_role_label) !== ''
	? trim((string) $dashboard_role_label)
	: 'Admin';
$dashboard_status_label = isset($dashboard_status_label) && trim((string) $dashboard_status_label) !== ''
	? trim((string) $dashboard_status_label)
	: 'Ringkasan Operasional Harian';
$privileged_password_targets = isset($privileged_password_targets) && is_array($privileged_password_targets)
	? array_values($privileged_password_targets)
	: array(
		array('username' => 'developer', 'label' => 'Developer (developer)'),
		array('username' => 'admin', 'label' => 'Admin (admin)'),
		array('username' => 'bos', 'label' => 'Bos (bos)')
	);
$admin_feature_catalog = isset($admin_feature_catalog) && is_array($admin_feature_catalog)
	? $admin_feature_catalog
	: array();
$admin_feature_accounts = isset($admin_feature_accounts) && is_array($admin_feature_accounts)
	? array_values($admin_feature_accounts)
	: array();

$status_hari_ini = $dashboard_status_label;
$status_class = 'status-info';
$summary_hadir_hari_ini = isset($summary['total_hadir_hari_ini']) ? max(0, (int) $summary['total_hadir_hari_ini']) : max(0, (int) (isset($summary['total_hadir_bulan_ini']) ? $summary['total_hadir_bulan_ini'] : 0));
$summary_izin_hari_ini = isset($summary['total_izin_hari_ini']) ? max(0, (int) $summary['total_izin_hari_ini']) : max(0, (int) (isset($summary['total_izin_bulan_ini']) ? $summary['total_izin_bulan_ini'] : 0));
$summary_alpha_hari_ini = isset($summary['total_alpha_hari_ini']) ? max(0, (int) $summary['total_alpha_hari_ini']) : max(0, (int) (isset($summary['total_alpha_bulan_ini']) ? $summary['total_alpha_bulan_ini'] : 0));
$summary_libur_hari_ini = isset($summary['total_libur_hari_ini']) ? max(0, (int) $summary['total_libur_hari_ini']) : 0;
$summary_pending_alpha_hari_ini = isset($summary['total_belum_masuk_masa_alpha_hari_ini']) ? max(0, (int) $summary['total_belum_masuk_masa_alpha_hari_ini']) : 0;
$summary_karyawan_hari_ini = isset($summary['total_karyawan_hari_ini']) ? max(0, (int) $summary['total_karyawan_hari_ini']) : 0;
$summary_breakdown_total_hari_ini = $summary_hadir_hari_ini + $summary_izin_hari_ini + $summary_alpha_hari_ini + $summary_libur_hari_ini + $summary_pending_alpha_hari_ini;
$summary_breakdown_gap_hari_ini = max(0, $summary_karyawan_hari_ini - $summary_breakdown_total_hari_ini);

$script_name = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
$base_path = str_replace('\\', '/', dirname($script_name));
if ($base_path === '/' || $base_path === '.') {
	$base_path = '';
}

$navbar_logo_path = 'src/assets/pns_logo_nav.png';
$favicon_path = 'src/assets/sinyal.svg';
$favicon_type = 'image/svg+xml';

$navbar_logo_url = base_url($navbar_logo_path);
$navbar_logo_version = is_file(FCPATH.$navbar_logo_path) ? (string) @md5_file(FCPATH.$navbar_logo_path) : '';
if ($navbar_logo_version !== '')
{
	$navbar_logo_url .= '?v='.rawurlencode($navbar_logo_version);
}

$favicon_url = site_url('home/favicon');
$favicon_version = is_file(FCPATH.$favicon_path) ? (string) @md5_file(FCPATH.$favicon_path) : '';
if ($favicon_version !== '')
{
	$favicon_url .= '?v='.rawurlencode($favicon_version);
}
$home_index_css_file = 'src/assets/css/home-index.css';
$home_index_js_file = 'src/assets/js/home-index.js';
$home_index_collab_js_file = 'src/assets/js/home-index-collab.js';
$home_index_css_version = is_file(FCPATH.$home_index_css_file) ? (string) filemtime(FCPATH.$home_index_css_file) : '1';
$home_index_js_version = is_file(FCPATH.$home_index_js_file) ? (string) filemtime(FCPATH.$home_index_js_file) : '1';
$home_index_collab_js_version = is_file(FCPATH.$home_index_collab_js_file) ? (string) filemtime(FCPATH.$home_index_collab_js_file) : '1';
$collab_revision = isset($collab_revision) ? (int) $collab_revision : 0;
if ($collab_revision < 0) {
	$collab_revision = 0;
}
$collab_feed_url = isset($collab_feed_url) ? trim((string) $collab_feed_url) : site_url('home/admin_change_feed');
$collab_sync_lock_url = isset($collab_sync_lock_url) ? trim((string) $collab_sync_lock_url) : site_url('home/sync_lock_status');
$collab_actor = isset($collab_actor) ? trim((string) $collab_actor) : '';
$collab_poll_ms = isset($collab_poll_ms) ? (int) $collab_poll_ms : 10000;
if ($collab_poll_ms < 3000) {
	$collab_poll_ms = 3000;
}
$collab_lock_wait_refresh_seconds = isset($collab_lock_wait_refresh_seconds) ? (int) $collab_lock_wait_refresh_seconds : 5;
if ($collab_lock_wait_refresh_seconds < 3) {
	$collab_lock_wait_refresh_seconds = 3;
}
$home_index_config_json = json_encode(array(
	'accountRows' => $employee_accounts,
	'defaultJobTitle' => $default_job_title,
	'defaultBranch' => $default_branch,
	'defaultWeeklyDayOff' => (int) $default_weekly_day_off,
	'featureAccounts' => $admin_feature_accounts,
	'summaryUrl' => site_url('home/admin_dashboard_live_summary'),
	'statusLabelFixed' => $dashboard_status_label,
	'chartEndpoint' => site_url('home/admin_metric_chart_data'),
	'collabRevision' => $collab_revision,
	'collabFeedUrl' => $collab_feed_url,
	'collabSyncLockUrl' => $collab_sync_lock_url,
	'collabActor' => $collab_actor,
	'collabPollMs' => $collab_poll_ms,
	'collabLockWaitRefreshSeconds' => $collab_lock_wait_refresh_seconds,
	'syncBackupReady' => $sync_backup_ready ? TRUE : FALSE,
	'syncBackupRequiredDirections' => $sync_backup_required_directions
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($home_index_config_json === FALSE) {
	$home_index_config_json = '{}';
}
?>
<?php
$_home_theme_cookie_value = isset($_COOKIE['home_index_theme']) ? strtolower(trim((string) $_COOKIE['home_index_theme'])) : '';
$_home_theme_session_value = '';
if (isset($this) && isset($this->session) && method_exists($this->session, 'userdata'))
{
	$_home_theme_session_value = strtolower(trim((string) $this->session->userdata('home_index_theme')));
}
if ($_home_theme_cookie_value === 'dark' || $_home_theme_cookie_value === 'light')
{
	$_home_theme_value = $_home_theme_cookie_value;
}
elseif ($_home_theme_session_value === 'dark' || $_home_theme_session_value === 'light')
{
	$_home_theme_value = $_home_theme_session_value;
}
else
{
	$_home_theme_value = '';
}
$_home_theme_is_dark = $_home_theme_value === 'dark';
$_home_theme_html_class = $_home_theme_is_dark ? ' class="theme-dark"' : '';
$_home_theme_html_data = ' data-theme="' . ($_home_theme_is_dark ? 'dark' : 'light') . '"';
$_home_theme_body_class = $_home_theme_is_dark ? ' class="theme-dark"' : '';
?>
<!DOCTYPE html>
<html lang="id"<?php echo $_home_theme_html_class; ?><?php echo $_home_theme_html_data; ?>>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Dashboard Absen Online'; ?></title>
	<link rel="icon" type="<?php echo htmlspecialchars($favicon_type, ENT_QUOTES, 'UTF-8'); ?>" href="/src/assets/sinyal.svg">
	<link rel="shortcut icon" type="<?php echo htmlspecialchars($favicon_type, ENT_QUOTES, 'UTF-8'); ?>" href="/src/assets/sinyal.svg">
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
		<link rel="stylesheet" href="<?php echo htmlspecialchars($base_path.'/'.$home_index_css_file, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo rawurlencode($home_index_css_version); ?>">
			<script>
				(function () {
					var themeValue = '';
					try {
						themeValue = String(window.localStorage.getItem('home_index_theme') || '').toLowerCase();
					} catch (error) {}
					if (themeValue !== 'dark' && themeValue !== 'light') {
						var cookieMatch = document.cookie.match(/(?:^|;\s*)home_index_theme=(dark|light)\b/i);
						if (cookieMatch && cookieMatch[1]) {
							themeValue = String(cookieMatch[1]).toLowerCase();
						}
					}
					if (themeValue === 'dark') {
						document.documentElement.classList.add('theme-dark');
						document.documentElement.setAttribute('data-theme', 'dark');
					} else if (themeValue === 'light') {
						document.documentElement.classList.remove('theme-dark');
						document.documentElement.setAttribute('data-theme', 'light');
					}
				})();
			</script>
		<style>
			.theme-toggle-btn {
				border: 0;
				background: transparent;
				padding: 0;
				margin: 0;
				cursor: pointer;
				display: inline-flex;
				align-items: center;
				justify-content: center;
			}
			.theme-toggle-track {
				position: relative;
				width: 62px;
				height: 34px;
				border-radius: 999px;
				border: 2px solid rgba(255, 255, 255, 0.48);
				background: rgba(6, 24, 42, 0.34);
				box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
				display: inline-flex;
				align-items: center;
				justify-content: space-between;
				padding: 0 9px;
				transition: background-color 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
			}
			.theme-toggle-icon {
				font-size: 15px;
				line-height: 1;
				transition: transform 0.25s ease, opacity 0.25s ease, color 0.25s ease;
			}
			.theme-toggle-icon.sun {
				color: #f5bf53;
				opacity: 1;
				transform: scale(1);
			}
			.theme-toggle-icon.moon {
				color: #dfebf8;
				opacity: 0.4;
				transform: scale(0.88);
			}
			.theme-toggle-knob {
				position: absolute;
				top: 2px;
				left: 2px;
				width: 26px;
				height: 26px;
				border-radius: 999px;
				background: #ffffff;
				box-shadow: 0 3px 8px rgba(0, 0, 0, 0.28);
				transition: transform 0.25s ease, background-color 0.25s ease, box-shadow 0.25s ease;
			}
			.theme-toggle-btn:focus-visible .theme-toggle-track {
				outline: none;
				box-shadow: 0 0 0 3px rgba(160, 210, 255, 0.35), inset 0 0 0 1px rgba(255, 255, 255, 0.08);
			}
			html.theme-dark body,
			body.theme-dark {
				--brand-dark: #0b2f53;
				--brand-main: #175a94;
				--brand-soft: #15283b;
				--text-main: #e8f0fa;
				--text-soft: #adc0d4;
				--line-soft: #2d4258;
				--surface: #102030;
				--surface-alt: #0f1b29;
				--muted: #8ca3bb;
				background: #0d1a28 !important;
				background-image: none !important;
				color: var(--text-main);
			}
			html.theme-dark .theme-toggle-track,
			body.theme-dark .theme-toggle-track {
				background: rgba(3, 11, 20, 0.82);
				border-color: rgba(174, 207, 238, 0.6);
				box-shadow: inset 0 0 10px rgba(115, 176, 228, 0.2);
			}
			html.theme-dark .theme-toggle-knob,
			body.theme-dark .theme-toggle-knob {
				transform: translateX(28px);
				background: #dbe9f6;
				box-shadow: 0 3px 8px rgba(0, 0, 0, 0.45);
			}
			html.theme-dark .theme-toggle-icon.sun,
			body.theme-dark .theme-toggle-icon.sun {
				opacity: 0.4;
				transform: scale(0.88);
			}
			html.theme-dark .theme-toggle-icon.moon,
			body.theme-dark .theme-toggle-icon.moon {
				opacity: 1;
				color: #ffffff;
				transform: scale(1);
			}
			html.theme-dark .hero-card,
			body.theme-dark .hero-card {
				background: linear-gradient(145deg, #13263a 0, #0f1f31 100%);
				border-color: #2f455a;
				box-shadow: none !important;
			}
			html.theme-dark .clock-box,
			body.theme-dark .clock-box {
				border-color: #3a5872;
				background: #12283a;
			}
			html.theme-dark .clock-box-link:hover,
			html.theme-dark .clock-box-link:focus-visible,
			body.theme-dark .clock-box-link:hover,
			body.theme-dark .clock-box-link:focus-visible {
				border-color: #4f7597;
				box-shadow: 0 10px 20px rgba(0, 0, 0, 0.28);
			}
			html.theme-dark .mini-hint,
			html.theme-dark .clock-help-text,
			html.theme-dark .account-help,
			html.theme-dark .footer-note,
			body.theme-dark .mini-hint,
			body.theme-dark .clock-help-text,
			body.theme-dark .account-help,
			body.theme-dark .footer-note {
				color: #9cb1c7;
			}
			html.theme-dark .account-card,
			body.theme-dark .account-card {
				background: linear-gradient(180deg, #102133 0, #0f1b2a 100%);
				border-color: #30475d;
				box-shadow: 0 12px 24px rgba(0, 0, 0, 0.32);
			}
			html.theme-dark .account-card h3,
			body.theme-dark .account-card h3 {
				color: #dbe8f6;
			}
			html.theme-dark .account-card p,
			body.theme-dark .account-card p {
				color: #b2c3d5;
			}
			html.theme-dark .account-input,
			html.theme-dark .account-search-input,
			body.theme-dark .account-input,
			body.theme-dark .account-search-input {
				background: #0f2436;
				border-color: #3b5771;
				color: #e4edf8;
			}
			html.theme-dark .account-input:focus,
			html.theme-dark .account-search-input:focus,
			body.theme-dark .account-input:focus,
			body.theme-dark .account-search-input:focus {
				border-color: #66a7e4;
				box-shadow: 0 0 0 3px rgba(78, 141, 204, 0.2);
			}
			html.theme-dark .table-wrap,
			body.theme-dark .table-wrap {
				box-shadow: none !important;
			}
			html.theme-dark .main-shell,
			body.theme-dark .main-shell {
				background: #0d1a28 !important;
				background-image: none !important;
			}
			html.theme-dark header.hero,
			html.theme-dark body.theme-dark header.hero {
				background: transparent !important;
				background-image: none !important;
				border: 0 !important;
				box-shadow: none !important;
			}
			html.theme-dark main.pb-4,
			html.theme-dark body.theme-dark main.pb-4 {
				background: transparent !important;
				background-image: none !important;
				border: 0 !important;
				box-shadow: none !important;
			}
			html.theme-dark header.hero::before,
			html.theme-dark header.hero::after,
			html.theme-dark main.pb-4::before,
			html.theme-dark main.pb-4::after {
				content: none !important;
				background: none !important;
				box-shadow: none !important;
			}
			html.theme-dark .mini-card,
			html.theme-dark .action-card,
			html.theme-dark .account-card,
			body.theme-dark .mini-card,
			body.theme-dark .action-card,
			body.theme-dark .account-card {
				box-shadow: none !important;
			}
			html.theme-dark [class*="card"],
			html.theme-dark [class*="box"],
			html.theme-dark [class*="panel"],
			body.theme-dark [class*="card"],
			body.theme-dark [class*="box"],
			body.theme-dark [class*="panel"] {
				box-shadow: none !important;
			}
			html.theme-dark .table-custom thead th,
			body.theme-dark .table-custom thead th {
				color: #9bb2ca;
				border-color: #2d4559;
			}
			html.theme-dark .table-custom tbody td,
			body.theme-dark .table-custom tbody td {
				color: #c8d7e8;
				border-color: #24384b;
			}
			html.theme-dark .metric-modal-card,
			body.theme-dark .metric-modal-card {
				background: #0f1f30;
				border-color: #2d4257;
			}
			html.theme-dark .metric-legend,
			body.theme-dark .metric-legend {
				color: #aec0d5;
			}
			html.theme-dark .metric-legend .label,
			body.theme-dark .metric-legend .label {
				color: #d5e2f0;
			}
			html.theme-dark .metric-chart-wrap,
			body.theme-dark .metric-chart-wrap {
				background: linear-gradient(180deg, #132638 0, #101f2e 100%);
				border-color: #2f465c;
			}
			html.theme-dark .metric-chart-canvas,
			body.theme-dark .metric-chart-canvas {
				background: #0f1d2a;
			}
			html.theme-dark .metric-range-btn,
			body.theme-dark .metric-range-btn {
				background: #12283b;
				border-color: #3a5570;
				color: #d5e4f4;
			}
			html.theme-dark .metric-range-btn.active,
			body.theme-dark .metric-range-btn.active {
				background: linear-gradient(120deg, #2f79c1 0, #1b588f 100%);
				border-color: #4f8ac4;
				color: #ffffff;
			}
			html.theme-dark .account-notice.success,
			body.theme-dark .account-notice.success {
				background: #113026;
				border-color: #2d7359;
				color: #c5f3df;
			}
			html.theme-dark .account-notice.error,
			body.theme-dark .account-notice.error {
				background: #381f25;
				border-color: #8b4653;
				color: #ffd8de;
			}
			html.theme-dark .manage-modal-card,
			body.theme-dark .manage-modal-card {
				background: linear-gradient(180deg, #102030 0, #0f1b29 100%);
				border-color: #355067;
			}
			html.theme-dark .manage-modal-head,
			body.theme-dark .manage-modal-head {
				background: rgba(14, 45, 74, 0.95);
			}
			@media (max-width: 575.98px) {
				.theme-toggle-track {
					width: 56px;
					height: 32px;
				}
				.theme-toggle-knob {
					width: 24px;
					height: 24px;
				}
				html.theme-dark .theme-toggle-knob,
				body.theme-dark .theme-toggle-knob {
					transform: translateX(24px);
				}
			}
		</style>
		<script>
		(function () {
			var themeValue = "";
			try {
				themeValue = String(window.localStorage.getItem("home_index_theme") || "").toLowerCase();
			} catch (error) {}
			if (themeValue !== "dark" && themeValue !== "light") {
				var cookieMatch = document.cookie.match(/(?:^|;\s*)home_index_theme=(dark|light)\b/i);
				if (cookieMatch && cookieMatch[1]) {
					themeValue = String(cookieMatch[1]).toLowerCase();
				}
			}
			if (themeValue === "dark" || themeValue === "light") {
				try {
					window.localStorage.setItem("home_index_theme", themeValue);
				} catch (error) {}
				try {
					document.cookie = "home_index_theme=" + encodeURIComponent(themeValue) + ";path=/;max-age=31536000;SameSite=Lax";
				} catch (error) {}
			}
			if (themeValue === "dark") {
				document.documentElement.classList.add("theme-dark");
				document.documentElement.setAttribute("data-theme", "dark");
			} else if (themeValue === "light") {
				document.documentElement.classList.remove("theme-dark");
				document.documentElement.setAttribute("data-theme", "light");
			}
		})();
	</script>
	<script src="<?php echo htmlspecialchars('/src/assets/js/theme-global-init.js?v=20260225f', ENT_QUOTES, 'UTF-8'); ?>"></script>
		<link rel="stylesheet" href="<?php echo htmlspecialchars('/src/assets/css/theme-global.css?v=20260225k', ENT_QUOTES, 'UTF-8'); ?>">
</head>
	<body<?php echo $_home_theme_body_class; ?>>
	<div class="main-shell">
		<nav class="topbar">
			<div class="topbar-container">
				<div class="topbar-inner">
					<a href="<?php echo site_url('home'); ?>" class="brand">
						<img class="brand-logo" src="<?php echo htmlspecialchars($navbar_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Absen Online">
						<span class="brand-text"><?php echo htmlspecialchars($dashboard_navbar_title, ENT_QUOTES, 'UTF-8'); ?></span>
						</a>
						<div class="nav-right">
							<button type="button" class="theme-toggle-btn" id="themeToggleButton" aria-label="Aktifkan mode malam" aria-pressed="false" title="Ganti ke mode malam">
								<span class="theme-toggle-track" aria-hidden="true">
									<i class="fa fa-sun-o theme-toggle-icon sun"></i>
									<i class="fa fa-moon-o theme-toggle-icon moon"></i>
									<span class="theme-toggle-knob"></span>
								</span>
							</button>
							<?php if ($can_view_log_data): ?>
								<a href="<?php echo site_url('home/log_data'); ?>" class="logout">Log Data</a>
							<?php endif; ?>
							<a href="<?php echo site_url('logout'); ?>" class="logout" id="adminLogoutLink">Logout</a>
						</div>
				</div>
			</div>
		</nav>

		<header class="hero">
			<div class="container-xl">
				<div class="hero-card">
					<div class="row g-3 align-items-center">
						<div class="col-lg-7">
							<p class="status-pill <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>" id="summaryStatusPill"><?php echo htmlspecialchars($status_hari_ini, ENT_QUOTES, 'UTF-8'); ?></p>
							<h1 class="hero-title">Halo, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>. Selamat datang di Dashboard Absen.</h1>
							<p class="hero-subtitle">
								Kelola data kehadiran tim, sinkronisasi spreadsheet, dan operasional administrasi absensi dalam satu halaman kerja.
							</p>
						</div>
						<div class="col-lg-5">
							<div class="row g-2">
								<div class="col-sm-6">
									<div class="clock-box">
										<p class="clock-label">Tanggal Hari Ini</p>
										<p class="clock-value" id="currentDate">-</p>
									</div>
								</div>
								<div class="col-sm-6">
									<div class="clock-box">
										<p class="clock-label">Jam Sekarang</p>
										<p class="clock-value" id="currentTime">-</p>
									</div>
								</div>
								<div class="col-12">
									<a href="<?php echo site_url('home/cara_pakai'); ?>" class="clock-box clock-box-link" aria-label="Buka halaman Cara Pakai Dashboard">
										<p class="clock-label">Cara Pakai Dashboard</p>
										<p class="clock-value">Buka Panduan Lengkap</p>
										<p class="clock-help-text">Klik untuk lihat fungsi tombol, aturan potongan, dan alur sinkronisasi.</p>
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</header>

		<main class="pb-4">
			<div class="container-xl">
				<section id="ringkasan-bulan" class="mb-4">
					<h2 class="section-title">Ringkasan Bulan Ini</h2>
					<div class="row g-3">
						<div class="col-sm-6 col-xl-3">
							<article class="mini-card is-clickable" role="button" tabindex="0" data-metric-card="hadir" aria-label="Lihat grafik Total Hadir">
								<p class="mini-label">Total Hadir</p>
								<p class="mini-value"><span id="summaryTotalHadir"><?php echo htmlspecialchars((string) (isset($summary['total_hadir_bulan_ini']) ? $summary['total_hadir_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Orang</p>
								<p class="mini-hint">Klik untuk lihat grafik realtime</p>
							</article>
						</div>
						<div class="col-sm-6 col-xl-3">
							<article class="mini-card is-clickable" role="button" tabindex="0" data-metric-card="terlambat" aria-label="Lihat grafik Total Terlambat">
								<p class="mini-label">Total Terlambat</p>
								<p class="mini-value"><span id="summaryTotalTerlambat"><?php echo htmlspecialchars((string) (isset($summary['total_terlambat_bulan_ini']) ? $summary['total_terlambat_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Orang</p>
								<p class="mini-hint">Klik untuk lihat grafik realtime</p>
							</article>
						</div>
						<div class="col-sm-6 col-xl-3">
							<article class="mini-card is-clickable" role="button" tabindex="0" data-metric-card="izin_cuti" aria-label="Lihat grafik Total Izin/Cuti">
								<p class="mini-label">Total Izin/Cuti</p>
								<p class="mini-value"><span id="summaryTotalIzin"><?php echo htmlspecialchars((string) (isset($summary['total_izin_bulan_ini']) ? $summary['total_izin_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Orang</p>
								<p class="mini-hint">Klik untuk lihat grafik realtime</p>
							</article>
						</div>
						<div class="col-sm-6 col-xl-3">
							<article class="mini-card is-clickable" role="button" tabindex="0" data-metric-card="alpha" aria-label="Lihat grafik Total Alpha">
								<p class="mini-label">Total Alpha</p>
								<p class="mini-value"><span id="summaryTotalAlpha"><?php echo htmlspecialchars((string) (isset($summary['total_alpha_bulan_ini']) ? $summary['total_alpha_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Orang</p>
								<p class="mini-hint">Klik untuk lihat grafik realtime</p>
							</article>
						</div>
					</div>
				</section>

				<section id="aksi-cepat" class="mb-4">
					<h2 class="section-title">Aksi Cepat</h2>
					<div class="action-grid">
						<article class="action-card">
							<h3 class="action-title">Data Lembur</h3>
							<p class="action-text">Input manual data lembur karyawan: nama, tanggal, jam lembur, nominal, dan alasan.</p>
							<a href="<?php echo site_url('home/overtime_data'); ?>" class="action-btn">Buka Data Lembur</a>
						</article>
						<article class="action-card">
							<h3 class="action-title">Pengajuan Pinjaman</h3>
							<p class="action-text">Lihat data pengajuan pinjaman yang dikirim oleh karyawan.</p>
							<a href="<?php echo site_url('home/loan_requests'); ?>" class="action-btn secondary">Buka Pinjaman</a>
						</article>
						<article class="action-card">
							<h3 class="action-title">Pengajuan Cuti / Izin</h3>
							<p class="action-text">Lihat seluruh data pengajuan cuti dan izin yang dikirim oleh karyawan.</p>
							<a href="<?php echo site_url('home/leave_requests'); ?>" class="action-btn">Buka Pengajuan</a>
						</article>
						<?php if ($can_process_day_off_swap_requests): ?>
							<article class="action-card">
								<h3 class="action-title">Pengajuan Tukar Hari Libur</h3>
								<p class="action-text">Proses pengajuan tukar hari libur 1x dari karyawan (terima/tolak, dan hapus untuk Bos/Developer).</p>
								<a href="<?php echo site_url('home/day_off_swap_requests'); ?>" class="action-btn secondary">Buka Tukar Libur</a>
							</article>
						<?php endif; ?>
						<article class="action-card">
							<h3 class="action-title">Cek Absensi Karyawan</h3>
							<p class="action-text">Lihat rekap absensi lengkap karyawan (masuk, pulang, telat, foto, dan jarak dari titik kantor).</p>
							<a href="<?php echo site_url('home/employee_data'); ?>" class="action-btn secondary">Buka Data Absen</a>
						</article>
					</div>
				</section>

				<section id="manajemen-karyawan" class="mb-4">
					<?php if ($can_manage_accounts): ?>
						<h2 class="section-title">Manajemen Akun Karyawan</h2>
					<?php else: ?>
						<h2 class="section-title">Sinkronisasi Data Absen</h2>
					<?php endif; ?>

					<?php if ($account_notice_success !== ''): ?>
						<div class="account-notice success"><?php echo htmlspecialchars($account_notice_success, ENT_QUOTES, 'UTF-8'); ?></div>
					<?php endif; ?>
					<?php if ($account_notice_error !== ''): ?>
						<div class="account-notice error"><?php echo htmlspecialchars($account_notice_error, ENT_QUOTES, 'UTF-8'); ?></div>
					<?php endif; ?>
					<div class="account-grid mb-3">
						<article class="account-card">
							<h3>Sinkronisasi Spreadsheet</h3>
							<?php if ($can_sync_sheet_accounts): ?>
								<p>Tarik data terbaru dari Google Sheet ke web (akun + Data Absen).</p>
							<?php else: ?>
								<p>Tarik data terbaru dari Google Sheet ke web (Data Absen).</p>
							<?php endif; ?>
							<div class="d-flex flex-wrap gap-2">
								<form method="post" action="<?php echo site_url('home/prepare_sync_local_backup_now'); ?>" class="sync-backup-control-form" data-sync-backup-form="1" data-sync-label="Backup Local Dulu (Wajib)">
									<button type="submit" class="account-submit">Backup Local Dulu (Wajib)</button>
								</form>
								<?php if ($can_sync_sheet_accounts): ?>
									<form method="post" action="<?php echo site_url('home/sync_sheet_accounts_now'); ?>" class="sync-control-form" data-sync-direction="sheet_to_web_account" data-sync-label="Sync Akun dari Sheet">
										<button type="submit" class="account-submit">Sync Akun dari Sheet</button>
									</form>
								<?php endif; ?>
								<form method="post" action="<?php echo site_url('home/sync_sheet_attendance_now'); ?>" class="sync-control-form" data-sync-direction="sheet_to_web_attendance" data-sync-label="Sync Data Absen dari Sheet" data-requires-backup="1">
									<button type="submit" class="account-submit"<?php echo $sync_backup_required_button_attrs; ?>>Sync Data Absen dari Sheet</button>
								</form>
								<form method="post" action="<?php echo site_url('home/sync_web_attendance_to_sheet_now'); ?>" class="sync-control-form" data-sync-direction="web_to_sheet" data-sync-label="Sync Data Web ke Sheet" data-requires-backup="1">
									<button type="submit" class="account-submit"<?php echo $sync_backup_required_button_attrs; ?>>Sync Data Web ke Sheet</button>
								</form>
								<form method="post" action="<?php echo site_url('home/sync_web_loan_to_sheet_now'); ?>" class="sync-control-form" data-sync-direction="web_to_sheet_loan" data-sync-label="Sync Data Web ke Pinjaman" data-requires-backup="1">
									<button type="submit" class="account-submit"<?php echo $sync_backup_required_button_attrs; ?>>Sync Data Web ke Pinjaman</button>
								</form>
								<form method="post" action="<?php echo site_url('home/sync_sheet_loan_to_web_now'); ?>" class="sync-control-form" data-sync-direction="sheet_loan_to_web" data-sync-label="Sync Data Pinjaman ke Web" data-requires-backup="1">
									<button type="submit" class="account-submit"<?php echo $sync_backup_required_button_attrs; ?>>Sync Data Pinjaman ke Web</button>
								</form>
								<form
									method="post"
									action="<?php echo site_url('home/reset_total_alpha_now'); ?>"
									class="sync-control-form"
									data-sync-direction="reset_total_alpha"
									data-sync-label="Reset Total Alpha"
									onsubmit="return window.confirm('Reset total alpha bulan berjalan? Aksi ini akan mengosongkan hitungan alpha di dashboard/grafik untuk tanggal yang sudah berjalan bulan ini.');"
								>
									<button type="submit" class="account-submit delete">Reset Total Alpha</button>
								</form>
							</div>
							<p class="text-secondary mb-0 mt-2"><?php echo htmlspecialchars($sync_backup_status_text !== '' ? $sync_backup_status_text : 'Belum ada backup lokal aktif. Klik "Backup Local Dulu (Wajib)" sebelum sync.', ENT_QUOTES, 'UTF-8'); ?></p>
							<?php if ($loan_sync_last_message !== '' || $loan_sync_last_action !== ''): ?>
								<div class="account-divider"></div>
								<p class="account-help mb-1"><strong>Log Sync Pinjaman Terakhir</strong></p>
								<p class="account-help mb-0">
									<?php echo htmlspecialchars('Waktu: '.($loan_sync_last_created_at !== '' ? $loan_sync_last_created_at : '-'), ENT_QUOTES, 'UTF-8'); ?><br>
									<?php echo htmlspecialchars('Aksi: '.$loan_sync_last_action_label, ENT_QUOTES, 'UTF-8'); ?><br>
									<?php echo htmlspecialchars('Status: '.($loan_sync_last_status !== '' ? strtoupper($loan_sync_last_status) : '-'), ENT_QUOTES, 'UTF-8'); ?><br>
									<?php echo htmlspecialchars('Pesan: '.($loan_sync_last_message !== '' ? $loan_sync_last_message : '-'), ENT_QUOTES, 'UTF-8'); ?>
									<?php if ($loan_sync_last_detail_text !== ''): ?><br><?php echo htmlspecialchars('Detail: '.$loan_sync_last_detail_text, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
								</p>
							<?php endif; ?>
						</article>
					</div>

					<?php if (!$can_manage_accounts && $can_process_day_off_swap_requests): ?>
						<div class="account-grid mb-3">
							<article class="account-card">
								<h3>Pengajuan Tukar Hari Libur (1x)</h3>
								<p>Pengajuan dibuat dari dashboard karyawan. Kamu bisa setujui/tolak sesuai cakupan cabang akun login.</p>
								<?php if (empty($day_off_swap_requests)): ?>
									<p class="text-secondary mb-0">Belum ada pengajuan tukar hari libur yang menunggu.</p>
								<?php else: ?>
									<div class="table-wrap mt-2">
										<div class="table-responsive">
											<table class="table table-custom">
												<thead>
													<tr>
														<th>Karyawan</th>
														<th>Tanggal Masuk (hasil tukar)</th>
														<th>Tanggal Libur (pengganti)</th>
														<th>Catatan</th>
														<th>Aksi</th>
													</tr>
												</thead>
												<tbody>
													<?php foreach ($day_off_swap_requests as $request_row): ?>
														<?php
														$request_id = isset($request_row['request_id']) ? trim((string) $request_row['request_id']) : '';
														if ($request_id === '') {
															continue;
														}
														$request_username = isset($request_row['username']) ? (string) $request_row['username'] : '-';
														$request_employee_id = isset($request_row['employee_id']) ? (string) $request_row['employee_id'] : '-';
														$request_display_name = isset($request_row['display_name']) ? trim((string) $request_row['display_name']) : '';
														$request_worker_label = $request_employee_id.' - '.$request_username;
														if ($request_display_name !== '' && strcasecmp($request_display_name, $request_username) !== 0) {
															$request_worker_label .= ' ('.$request_display_name.')';
														}
														$request_note = isset($request_row['note']) ? trim((string) $request_row['note']) : '';
														?>
														<tr>
															<td><?php echo htmlspecialchars($request_worker_label, ENT_QUOTES, 'UTF-8'); ?></td>
															<td><?php echo htmlspecialchars(isset($request_row['workday_label']) ? (string) $request_row['workday_label'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
															<td><?php echo htmlspecialchars(isset($request_row['offday_label']) ? (string) $request_row['offday_label'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
															<td><?php echo htmlspecialchars($request_note !== '' ? $request_note : '-', ENT_QUOTES, 'UTF-8'); ?></td>
															<td>
																<form method="post" action="<?php echo site_url('home/update_day_off_swap_request_status'); ?>" onsubmit="return window.confirm('Proses pengajuan tukar hari libur ini?');">
																	<input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request_id, ENT_QUOTES, 'UTF-8'); ?>">
																	<input type="text" name="review_note" class="account-input" placeholder="Catatan admin (opsional)" style="min-width:220px; margin-bottom:.35rem;">
																	<div style="display:flex; gap:.32rem;">
																		<button type="submit" name="status" value="approved" class="account-submit" style="width:auto; display:inline-flex; padding:.34rem .6rem; border-radius:9px; font-size:.76rem;">Setujui</button>
																		<button type="submit" name="status" value="rejected" class="account-submit delete" style="width:auto; display:inline-flex; padding:.34rem .6rem; border-radius:9px; font-size:.76rem;">Tolak</button>
																	</div>
																</form>
															</td>
														</tr>
													<?php endforeach; ?>
												</tbody>
											</table>
										</div>
									</div>
								<?php endif; ?>
							</article>
						</div>
					<?php endif; ?>

					<?php if ($can_manage_accounts): ?>
						<div class="account-grid mb-3">
							<article class="account-card">
								<h3>Aksi Manajemen Akun</h3>
								<p>Pilih tombol untuk membuka pop up form sesuai kebutuhan.</p>
								<div class="account-action-grid">
									<button type="button" class="account-action-btn" data-manage-modal-open="employeeCreateModal">Buat Akun Karyawan Baru</button>
									<button type="button" class="account-action-btn secondary" data-manage-modal-open="employeeManageModal">Hapus / Edit Akun Karyawan</button>
									<?php if ($can_manage_feature_accounts): ?>
										<button type="button" class="account-action-btn" data-manage-modal-open="privilegedManageModal">Kelola Akun Privileged</button>
									<?php endif; ?>
								</div>
							</article>
						</div>

						<div class="manage-modal" id="employeeCreateModal" data-manage-modal aria-hidden="true" role="dialog" aria-labelledby="employeeCreateModalTitle">
							<div class="manage-modal-card">
								<div class="manage-modal-head">
									<h3 class="manage-modal-title" id="employeeCreateModalTitle">Buat Akun Karyawan Baru</h3>
									<button type="button" class="manage-modal-close" data-manage-modal-close aria-label="Tutup popup">&times;</button>
								</div>
								<div class="manage-modal-body" id="employeeCreateModalBody"></div>
							</div>
						</div>

						<div class="manage-modal" id="employeeManageModal" data-manage-modal aria-hidden="true" role="dialog" aria-labelledby="employeeManageModalTitle">
							<div class="manage-modal-card">
								<div class="manage-modal-head">
									<h3 class="manage-modal-title" id="employeeManageModalTitle">Hapus / Edit Akun Karyawan</h3>
									<button type="button" class="manage-modal-close" data-manage-modal-close aria-label="Tutup popup">&times;</button>
								</div>
								<div class="manage-modal-body manage-modal-grid two-col" id="employeeManageModalBody"></div>
							</div>
						</div>

						<?php if ($can_manage_feature_accounts): ?>
							<div class="manage-modal" id="privilegedManageModal" data-manage-modal aria-hidden="true" role="dialog" aria-labelledby="privilegedManageModalTitle">
								<div class="manage-modal-card">
									<div class="manage-modal-head">
										<h3 class="manage-modal-title" id="privilegedManageModalTitle">Kelola Akun Privileged</h3>
										<button type="button" class="manage-modal-close" data-manage-modal-close aria-label="Tutup popup">&times;</button>
									</div>
									<div class="manage-modal-body manage-modal-grid two-col" id="privilegedManageModalBody"></div>
								</div>
							</div>
						<?php endif; ?>

						<div class="account-grid mb-3 manage-source-block" id="manageAccountSourceWrap">
							<div class="account-column-stack">
								<article class="account-card" id="createEmployeeSourceCard">
									<h3>Buat Akun Karyawan Baru</h3>
									<p>Akun dengan izin kelola akun bisa menambahkan akun login karyawan langsung dari dashboard.</p>
									<form method="post" action="<?php echo site_url('home/create_employee_account'); ?>" class="account-form" enctype="multipart/form-data">
									<div>
										<p class="account-label">Username</p>
										<input type="text" name="new_username" id="newUsernameInput" class="account-input" placeholder="contoh: userbaru" autocomplete="off" autocapitalize="off" spellcheck="false" required>
									</div>
									<div>
										<p class="account-label">Nama Lengkap</p>
										<input type="text" name="new_display_name" class="account-input" placeholder="contoh: Muhammad Ridwan K." autocomplete="off" required>
									</div>
									<div class="account-form-row two">
										<div>
											<p class="account-label">Password</p>
											<input type="text" name="new_password" class="account-input" placeholder="minimal 3 karakter" required>
										</div>
										<div>
											<p class="account-label">No Telp</p>
											<input type="text" name="new_phone" class="account-input" placeholder="08xxxxxxxxxx" required>
										</div>
									</div>
									<div class="account-form-row two">
										<div>
											<p class="account-label">Cabang</p>
											<select name="new_branch" class="account-input" required>
												<?php foreach ($branch_options as $branch_option): ?>
													<?php $branch_option_value = trim((string) $branch_option); ?>
													<?php if ($branch_option_value === ''): ?>
														<?php continue; ?>
													<?php endif; ?>
													<option value="<?php echo htmlspecialchars($branch_option_value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo strcasecmp($branch_option_value, $default_branch) === 0 ? ' selected' : ''; ?>>
														<?php echo htmlspecialchars($branch_option_value, ENT_QUOTES, 'UTF-8'); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</div>
										<div>
											<p class="account-label">Shift</p>
											<select name="new_shift" class="account-input" required>
												<option value="pagi">Shift Pagi - Sore (08:00 - 17:00)</option>
												<option value="siang">Shift Siang - Malam (14:00 - 23:00)</option>
												<option value="multishift">Multi Shift (08:00 - 23:59)</option>
											</select>
										</div>
									</div>
									<div>
										<p class="account-label">Lintas Cabang</p>
										<select name="new_cross_branch_enabled" class="account-input" required>
											<option value="0" selected>Tidak</option>
											<option value="1">Iya</option>
										</select>
									</div>
									<div class="account-form-row two">
										<div>
											<p class="account-label">Gaji Pokok (Rp)</p>
											<input type="text" name="new_salary_monthly" class="account-input" placeholder="contoh: 2500000" required>
										</div>
										<div>
											<p class="account-label">Hari Libur Mingguan</p>
											<select name="new_weekly_day_off" class="account-input" required>
												<?php foreach ($weekly_day_off_options as $weekly_day_off_value => $weekly_day_off_label): ?>
													<?php
													$weekly_day_off_n = (int) $weekly_day_off_value;
													if ($weekly_day_off_n < 1 || $weekly_day_off_n > 7) {
														continue;
													}
													?>
													<option value="<?php echo $weekly_day_off_n; ?>"<?php echo $weekly_day_off_n === $default_weekly_day_off ? ' selected' : ''; ?>>
														<?php echo htmlspecialchars((string) $weekly_day_off_label, ENT_QUOTES, 'UTF-8'); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</div>
									</div>
									<div>
										<p class="account-label">Jabatan</p>
										<select name="new_job_title" class="account-input" required>
											<?php foreach ($job_title_options as $job_title_option): ?>
												<?php $job_title_option_value = trim((string) $job_title_option); ?>
												<?php if ($job_title_option_value === ''): ?>
													<?php continue; ?>
												<?php endif; ?>
												<option value="<?php echo htmlspecialchars($job_title_option_value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $job_title_option_value === $default_job_title ? ' selected' : ''; ?>>
													<?php echo htmlspecialchars($job_title_option_value, ENT_QUOTES, 'UTF-8'); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div>
										<p class="account-label">Alamat</p>
										<input type="text" name="new_address" class="account-input" placeholder="Kp. Kesekian Kalinya, Pandenglang, Banten">
									</div>
									<div class="account-form-row two">
										<div>
											<p class="account-label">Titik Koordinat</p>
											<input type="text" name="new_coordinate_point" class="account-input" placeholder="-6.217076, 106.132128" required>
										</div>
										<div>
											<p class="account-label">Upload PP (Wajib)</p>
											<input type="file" name="new_profile_photo" class="account-input" accept=".png,.jpg,.jpeg,.webp,.jfif,.jpe,.heic,.heif" required>
										</div>
									</div>
										<button type="submit" class="account-submit">Simpan Akun Baru</button>
									</form>
								</article>

								<?php if ($can_manage_feature_accounts): ?>
									<article class="account-card" id="privilegedRenameSourceCard">
										<h3>Ganti Nama Akun Admin</h3>
										<p>Khusus Developer/Bos. Ubah nama tampilan akun admin untuk login dashboard.</p>
										<form method="post" action="<?php echo site_url('home/update_privileged_account_display_name'); ?>" class="account-form">
											<input type="hidden" name="target_account" value="admin">
											<div>
												<p class="account-label">Nama Baru Akun Admin</p>
												<input type="text" name="new_display_name" class="account-input" placeholder="contoh: Admin Operasional" autocomplete="off" required>
											</div>
											<button type="submit" class="account-submit">Simpan Nama Admin</button>
										</form>
									</article>

									<article class="account-card" id="privilegedPasswordSourceCard">
										<h3>Ganti Informasi Akun Privileged</h3>
										<p>Khusus Developer/Bos. Bisa ubah username login admin, nama akun, dan/atau password akun admin. Username akun developer/bos tetap tidak bisa diubah. Bos tidak bisa mengubah akun developer.</p>
										<form method="post" action="<?php echo site_url('home/update_privileged_account_password'); ?>" class="account-form">
											<div>
												<p class="account-label">Target Akun</p>
												<select name="target_account" class="account-input" required>
													<?php foreach ($privileged_password_targets as $target_account_row): ?>
														<?php
														$target_account_username = '';
														$target_account_option_label = '';
														if (is_array($target_account_row)) {
															$target_account_username = strtolower(trim((string) (isset($target_account_row['username']) ? $target_account_row['username'] : '')));
															$target_account_option_label = trim((string) (isset($target_account_row['label']) ? $target_account_row['label'] : ''));
														} else {
															$target_account_username = strtolower(trim((string) $target_account_row));
														}
														if ($target_account_username === '') {
															continue;
														}
														if ($target_account_option_label === '') {
															$target_account_option_label = $target_account_username;
														}
														?>
														<option value="<?php echo htmlspecialchars($target_account_username, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($target_account_option_label, ENT_QUOTES, 'UTF-8'); ?></option>
													<?php endforeach; ?>
												</select>
											</div>
											<div>
												<p class="account-label">Username Baru (Opsional)</p>
												<input type="text" name="new_username" id="privilegedNewUsernameInput" class="account-input" placeholder="contoh: admin_ops_baru" autocomplete="off" autocapitalize="off" spellcheck="false">
											</div>
											<div>
												<p class="account-label">Nama Baru (Opsional)</p>
												<input type="text" name="new_display_name" class="account-input" placeholder="Kosongkan jika tidak diubah" autocomplete="off">
											</div>
											<div class="account-form-row two">
												<div>
													<p class="account-label">Password Baru (Opsional)</p>
													<input type="text" name="new_password" class="account-input" placeholder="Kosongkan jika tidak diubah">
												</div>
												<div>
													<p class="account-label">Konfirmasi Password</p>
													<input type="text" name="confirm_password" class="account-input" placeholder="Isi jika password diubah">
												</div>
											</div>
											<button type="submit" class="account-submit">Simpan Informasi</button>
										</form>
									</article>

									<article class="account-card" id="privilegedCreateSourceCard">
										<h3>Buat Akun Admin Fitur</h3>
										<p>Khusus Developer/Bos. Buat akun admin custom dan pilih fitur yang diizinkan.</p>
										<form method="post" action="<?php echo site_url('home/create_feature_admin_account'); ?>" class="account-form">
											<div>
												<p class="account-label">Username</p>
												<input type="text" name="feature_admin_username" class="account-input" placeholder="contoh: admin_ops" autocomplete="off" autocapitalize="off" spellcheck="false" required>
											</div>
											<div>
												<p class="account-label">Nama Lengkap</p>
												<input type="text" name="feature_admin_display_name" class="account-input" placeholder="contoh: Admin Operasional" autocomplete="off" required>
											</div>
											<div>
												<p class="account-label">Password</p>
												<input type="text" name="feature_admin_password" class="account-input" placeholder="minimal 3 karakter" required>
											</div>
											<div>
												<p class="account-label">Fitur Akses</p>
												<div class="d-flex flex-column gap-2">
													<?php foreach ($admin_feature_catalog as $feature_key => $feature_label): ?>
														<?php $feature_key_value = trim((string) $feature_key); ?>
														<?php if ($feature_key_value === ''): ?>
															<?php continue; ?>
														<?php endif; ?>
														<label class="d-flex align-items-center gap-2">
															<input type="checkbox" name="feature_permissions[]" value="<?php echo htmlspecialchars($feature_key_value, ENT_QUOTES, 'UTF-8'); ?>">
															<span><?php echo htmlspecialchars((string) $feature_label, ENT_QUOTES, 'UTF-8'); ?></span>
														</label>
													<?php endforeach; ?>
												</div>
											</div>
											<button type="submit" class="account-submit">Simpan Akun Fitur</button>
										</form>
									</article>
								<?php endif; ?>
							</div>

							<div class="account-column-stack">
								<article class="account-card" id="employeeDeleteSourceCard">
									<h3>Hapus Akun Karyawan</h3>
									<p>Pilih akun yang ingin dihapus. Data absen, cuti/izin, pinjaman, dan lembur karyawan tersebut juga akan dibersihkan.</p>
									<form method="post" action="<?php echo site_url('home/delete_employee_account'); ?>" class="account-form" id="deleteEmployeeForm">
									<div>
										<p class="account-label">Pilih Karyawan</p>
										<input
											type="text"
											name="delete_username"
											id="deleteUsernameInput"
											class="account-input"
											list="deleteEmployeeUsernameList"
											placeholder="Cari ID atau username karyawan"
											autocomplete="off"
											required
										>
										<datalist id="deleteEmployeeUsernameList">
											<?php foreach ($employee_accounts as $employee): ?>
												<?php
												$employee_username_value = isset($employee['username']) ? trim((string) $employee['username']) : '';
												if ($employee_username_value === '') {
													continue;
												}
												$employee_id_value = isset($employee['employee_id']) ? trim((string) $employee['employee_id']) : '-';
												?>
												<option value="<?php echo htmlspecialchars($employee_username_value, ENT_QUOTES, 'UTF-8'); ?>" label="<?php echo htmlspecialchars($employee_id_value.' - '.$employee_username_value, ENT_QUOTES, 'UTF-8'); ?>"></option>
												<?php if ($employee_id_value !== '' && $employee_id_value !== '-'): ?>
													<option value="<?php echo htmlspecialchars($employee_id_value.' - '.$employee_username_value, ENT_QUOTES, 'UTF-8'); ?>"></option>
												<?php endif; ?>
											<?php endforeach; ?>
										</datalist>
									</div>
										<input type="hidden" name="expected_version" id="deleteExpectedVersionInput" value="1">
										<button type="submit" class="account-submit delete">Hapus Akun</button>
									</form>
								</article>

								<article class="account-card" id="employeeEditSourceCard">
									<h3>Edit Akun Karyawan</h3>
									<p>Ubah data akun karyawan terpilih. Password boleh dikosongkan jika tidak ingin diubah.</p>
									<form method="post" action="<?php echo site_url('home/update_employee_account'); ?>" class="account-form" id="editEmployeeForm" enctype="multipart/form-data">
									<div>
										<p class="account-label">Pilih Karyawan</p>
										<input
											type="text"
											name="edit_username"
											id="editUsernameInput"
											class="account-input"
											list="editEmployeeUsernameList"
											placeholder="Cari ID atau username karyawan"
											autocomplete="off"
											required
										>
										<datalist id="editEmployeeUsernameList">
											<?php foreach ($employee_accounts as $employee): ?>
												<?php
												$employee_username_value = isset($employee['username']) ? trim((string) $employee['username']) : '';
												if ($employee_username_value === '') {
													continue;
												}
												$employee_id_value = isset($employee['employee_id']) ? trim((string) $employee['employee_id']) : '-';
												?>
												<option value="<?php echo htmlspecialchars($employee_username_value, ENT_QUOTES, 'UTF-8'); ?>" label="<?php echo htmlspecialchars($employee_id_value.' - '.$employee_username_value, ENT_QUOTES, 'UTF-8'); ?>"></option>
												<?php if ($employee_id_value !== '' && $employee_id_value !== '-'): ?>
													<option value="<?php echo htmlspecialchars($employee_id_value.' - '.$employee_username_value, ENT_QUOTES, 'UTF-8'); ?>"></option>
												<?php endif; ?>
											<?php endforeach; ?>
										</datalist>
									</div>
									<div>
										<p class="account-label">Username</p>
										<input type="text" name="edit_new_username" id="editNewUsernameInput" class="account-input" placeholder="contoh: userbaru" autocomplete="off" autocapitalize="off" spellcheck="false" required>
									</div>
									<div>
										<p class="account-label">Nama Lengkap</p>
										<input type="text" name="edit_display_name" id="editDisplayNameInput" class="account-input" placeholder="contoh: Muhammad Ridwan K." autocomplete="off" required>
									</div>
									<div class="account-form-row two">
										<div>
											<p class="account-label">Password Baru (Opsional)</p>
											<input type="text" name="edit_password" id="editPasswordInput" class="account-input" placeholder="Kosongkan jika tidak diubah">
										</div>
										<div>
											<p class="account-label">No Telp</p>
											<input type="text" name="edit_phone" id="editPhoneInput" class="account-input" placeholder="08xxxxxxxxxx" required>
										</div>
									</div>
									<div class="account-form-row two">
										<div>
											<p class="account-label">Cabang</p>
											<select name="edit_branch" id="editBranchInput" class="account-input" required>
												<?php foreach ($branch_options as $branch_option): ?>
													<?php $branch_option_value = trim((string) $branch_option); ?>
													<?php if ($branch_option_value === ''): ?>
														<?php continue; ?>
													<?php endif; ?>
													<option value="<?php echo htmlspecialchars($branch_option_value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo strcasecmp($branch_option_value, $default_branch) === 0 ? ' selected' : ''; ?>>
														<?php echo htmlspecialchars($branch_option_value, ENT_QUOTES, 'UTF-8'); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</div>
										<div>
											<p class="account-label">Shift</p>
											<select name="edit_shift" id="editShiftInput" class="account-input" required>
												<option value="pagi">Shift Pagi - Sore (08:00 - 17:00)</option>
												<option value="siang">Shift Siang - Malam (14:00 - 23:00)</option>
												<option value="multishift">Multi Shift (08:00 - 23:59)</option>
											</select>
										</div>
									</div>
									<div>
										<p class="account-label">Lintas Cabang</p>
										<select name="edit_cross_branch_enabled" id="editCrossBranchInput" class="account-input" required>
											<option value="0">Tidak</option>
											<option value="1">Iya</option>
										</select>
									</div>
									<div>
										<p class="account-label">Gaji Pokok (Rp)</p>
										<input type="text" name="edit_salary_monthly" id="editSalaryMonthlyInput" class="account-input" placeholder="contoh: 2500000" required>
									</div>
									<div>
										<p class="account-label">Jabatan</p>
										<select name="edit_job_title" id="editJobTitleInput" class="account-input" required>
											<?php foreach ($job_title_options as $job_title_option): ?>
												<?php $job_title_option_value = trim((string) $job_title_option); ?>
												<?php if ($job_title_option_value === ''): ?>
													<?php continue; ?>
												<?php endif; ?>
												<option value="<?php echo htmlspecialchars($job_title_option_value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $job_title_option_value === $default_job_title ? ' selected' : ''; ?>>
													<?php echo htmlspecialchars($job_title_option_value, ENT_QUOTES, 'UTF-8'); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div>
										<p class="account-label">Hari Libur Mingguan</p>
										<select name="edit_weekly_day_off" id="editWeeklyDayOffInput" class="account-input" required>
											<?php foreach ($weekly_day_off_options as $weekly_day_off_value => $weekly_day_off_label): ?>
												<?php
												$weekly_day_off_n = (int) $weekly_day_off_value;
												if ($weekly_day_off_n < 1 || $weekly_day_off_n > 7) {
													continue;
												}
												?>
												<option value="<?php echo $weekly_day_off_n; ?>"<?php echo $weekly_day_off_n === $default_weekly_day_off ? ' selected' : ''; ?>>
													<?php echo htmlspecialchars((string) $weekly_day_off_label, ENT_QUOTES, 'UTF-8'); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<div>
										<p class="account-label">Hari Kerja Khusus (Opsional)</p>
										<div class="d-flex flex-wrap gap-2">
											<?php foreach ($weekly_day_off_options as $weekday_value => $weekday_label): ?>
												<?php
												$weekday_n = (int) $weekday_value;
												if ($weekday_n < 1 || $weekday_n > 7) {
													continue;
												}
												?>
												<label class="d-inline-flex align-items-center gap-1 px-2 py-1 border rounded-2 bg-white">
													<input
														type="checkbox"
														name="edit_custom_allowed_weekdays[]"
														id="editCustomAllowedWeekday<?php echo $weekday_n; ?>"
														value="<?php echo $weekday_n; ?>"
														data-edit-custom-weekday
													>
													<span class="small"><?php echo htmlspecialchars((string) $weekday_label, ENT_QUOTES, 'UTF-8'); ?></span>
												</label>
											<?php endforeach; ?>
										</div>
										<p class="account-help mb-0">Jika dipilih, karyawan hanya bisa absen di hari yang dicentang.</p>
									</div>
									<div class="account-form-row two">
										<div>
											<p class="account-label">Libur Khusus (Dari)</p>
											<input type="date" name="edit_custom_off_start_date" id="editCustomOffStartDateInput" class="account-input">
										</div>
										<div>
											<p class="account-label">Libur Khusus (Sampai)</p>
											<input type="date" name="edit_custom_off_end_date" id="editCustomOffEndDateInput" class="account-input">
										</div>
									</div>
									<div class="account-form-row two">
										<div>
											<p class="account-label">Masuk Khusus (Dari)</p>
											<input type="date" name="edit_custom_work_start_date" id="editCustomWorkStartDateInput" class="account-input">
										</div>
										<div>
											<p class="account-label">Masuk Khusus (Sampai)</p>
											<input type="date" name="edit_custom_work_end_date" id="editCustomWorkEndDateInput" class="account-input">
										</div>
									</div>
									<div>
										<p class="account-label">Alamat</p>
										<input type="text" name="edit_address" id="editAddressInput" class="account-input" placeholder="Kp. Kesekian Kalinya, Pandenglang, Banten">
									</div>
									<div>
										<p class="account-label">Ganti PP (Opsional)</p>
										<input type="file" name="edit_profile_photo" id="editProfilePhotoInput" class="account-input" accept=".png,.jpg,.jpeg,.webp,.jfif,.jpe,.heic,.heif">
									</div>
										<input type="hidden" name="edit_custom_schedule_present" value="1">
										<input type="hidden" name="expected_version" id="editExpectedVersionInput" value="1">
										<button type="submit" class="account-submit">Simpan Perubahan Akun</button>
									</form>
								</article>
								<?php if ($can_manage_feature_accounts): ?>
									<article class="account-card" id="privilegedFeatureSourceCard">
										<h3>Edit Fitur Akun</h3>
										<p>Khusus Developer/Bos. Developer bisa mengubah fitur akun bos/admin. Bos tidak bisa mengubah fitur akun developer.</p>
										<?php if (empty($admin_feature_accounts)): ?>
											<p class="text-secondary mb-0">Belum ada akun admin yang bisa diubah fiturnya.</p>
										<?php else: ?>
											<form method="post" action="<?php echo site_url('home/update_feature_admin_account_permissions'); ?>" class="account-form" id="editFeatureAdminForm">
												<div>
													<p class="account-label">Target Akun</p>
													<select name="feature_target_account" id="featureTargetAccountInput" class="account-input" required>
														<?php foreach ($admin_feature_accounts as $feature_account): ?>
															<?php
															$feature_username = isset($feature_account['username']) ? trim((string) $feature_account['username']) : '';
															$feature_display_name = isset($feature_account['display_name']) ? trim((string) $feature_account['display_name']) : '';
															if ($feature_username === '') {
																continue;
															}
															$feature_option_label = $feature_display_name !== '' ? $feature_display_name.' ('.$feature_username.')' : $feature_username;
															?>
															<option value="<?php echo htmlspecialchars($feature_username, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($feature_option_label, ENT_QUOTES, 'UTF-8'); ?></option>
														<?php endforeach; ?>
													</select>
												</div>
												<div>
													<p class="account-label">Fitur Akses</p>
													<div class="d-flex flex-column gap-2">
														<?php foreach ($admin_feature_catalog as $feature_key => $feature_label): ?>
															<?php $feature_key_value = trim((string) $feature_key); ?>
															<?php if ($feature_key_value === ''): ?>
																<?php continue; ?>
															<?php endif; ?>
															<label class="d-flex align-items-center gap-2">
																<input type="checkbox" name="feature_permissions[]" value="<?php echo htmlspecialchars($feature_key_value, ENT_QUOTES, 'UTF-8'); ?>" data-feature-permission-checkbox>
																<span><?php echo htmlspecialchars((string) $feature_label, ENT_QUOTES, 'UTF-8'); ?></span>
															</label>
														<?php endforeach; ?>
													</div>
												</div>
												<button type="submit" class="account-submit">Simpan Fitur</button>
											</form>
										<?php endif; ?>
									</article>
								<?php endif; ?>
							</div>
						</div>

						<div class="table-wrap">
							<?php if (!empty($employee_accounts)): ?>
								<div class="account-table-toolbar">
									<input
										type="text"
										id="employeeAccountSearchInput"
										class="account-search-input"
										placeholder="Cari ID atau nama karyawan"
										autocomplete="off"
									>
								</div>
							<?php endif; ?>
							<div class="table-responsive">
								<table class="table table-custom account-table">
									<thead>
										<tr>
											<th>ID</th>
											<th>Username</th>
											<th>Jabatan</th>
											<th>Telp</th>
											<th>Cabang</th>
											<th>Shift</th>
											<th>Gaji Pokok</th>
										</tr>
									</thead>
									<tbody id="employeeAccountTableBody">
										<?php if (empty($employee_accounts)): ?>
											<tr class="employee-account-empty">
												<td colspan="7" class="text-center py-4 text-secondary">Belum ada akun karyawan.</td>
											</tr>
										<?php else: ?>
											<?php foreach ($employee_accounts as $employee): ?>
												<tr class="employee-account-row">
													<td><?php echo htmlspecialchars(isset($employee['employee_id']) ? (string) $employee['employee_id'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo htmlspecialchars(isset($employee['username']) ? (string) $employee['username'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo htmlspecialchars(isset($employee['job_title']) ? (string) $employee['job_title'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo htmlspecialchars(isset($employee['phone']) ? (string) $employee['phone'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo htmlspecialchars(isset($employee['branch']) ? (string) $employee['branch'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
													<td><?php echo htmlspecialchars(isset($employee['shift_name']) ? (string) $employee['shift_name'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
													<td>
														<?php
														$salary_monthly_value = isset($employee['salary_monthly']) ? (int) $employee['salary_monthly'] : 0;
														echo htmlspecialchars($salary_monthly_value > 0 ? 'Rp '.number_format($salary_monthly_value, 0, ',', '.') : '-', ENT_QUOTES, 'UTF-8');
														?>
													</td>
												</tr>
											<?php endforeach; ?>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
							<div class="account-pagination-wrap" id="employeeAccountPaginationWrap"></div>
						</div>
					<?php endif; ?>
				</section>

				<section id="riwayat-absen" class="mb-4">
					<h2 class="section-title">Riwayat Absen Terbaru</h2>
					<div class="table-wrap">
						<div class="table-responsive">
							<table class="table table-custom">
								<thead>
									<tr>
										<th>Tanggal</th>
										<th>Masuk</th>
										<th>Pulang</th>
										<th>Status</th>
										<th>Catatan</th>
									</tr>
								</thead>
								<tbody>
									<?php if (empty($recent_logs)): ?>
										<tr>
											<td colspan="5" class="text-center py-4 text-secondary">Belum ada data riwayat absen.</td>
										</tr>
									<?php else: ?>
										<?php foreach ($recent_logs as $log): ?>
											<?php
											$status = isset($log['status']) ? strtolower((string) $log['status']) : '';
											$chip_class = 'chip-hadir';
											if (strpos($status, 'terlambat') !== FALSE) {
												$chip_class = 'chip-terlambat';
											}
											elseif (strpos($status, 'izin') !== FALSE || strpos($status, 'cuti') !== FALSE) {
												$chip_class = 'chip-izin';
											}
											?>
											<tr>
												<td><?php echo htmlspecialchars(isset($log['tanggal']) ? (string) $log['tanggal'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
												<td><?php echo htmlspecialchars(isset($log['masuk']) ? (string) $log['masuk'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
												<td><?php echo htmlspecialchars(isset($log['pulang']) ? (string) $log['pulang'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
												<td><span class="status-chip <?php echo htmlspecialchars($chip_class, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(isset($log['status']) ? (string) $log['status'] : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
												<td><?php echo htmlspecialchars(isset($log['catatan']) ? (string) $log['catatan'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</section>

				<div class="metric-modal" id="metricModal" aria-hidden="true" role="dialog" aria-labelledby="metricModalTitle">
					<div class="metric-modal-card">
						<div class="metric-modal-head">
							<div class="metric-modal-title-wrap">
								<h3 class="metric-modal-title" id="metricModalTitle">Grafik Ringkasan</h3>
								<p class="metric-modal-subtitle" id="metricModalSubtitle">Realtime data</p>
							</div>
							<button type="button" class="metric-modal-close" id="metricModalClose" aria-label="Tutup popup grafik">&times;</button>
						</div>
						<div class="metric-modal-body">
							<div class="metric-legend" id="metricChartLegend">
								<span class="label">Memuat data grafik...</span>
							</div>
							<div class="metric-chart-wrap">
								<div class="metric-chart-canvas" id="metricChartCanvas"></div>
							</div>
							<div class="metric-range-row">
								<button type="button" class="metric-range-btn" data-metric-range="1H">1H</button>
								<button type="button" class="metric-range-btn" data-metric-range="1M">1M</button>
								<button type="button" class="metric-range-btn active" data-metric-range="1B">1B</button>
								<button type="button" class="metric-range-btn" data-metric-range="1T">1T</button>
								<button type="button" class="metric-range-btn" data-metric-range="ALL">Semuanya</button>
								<span class="metric-range-note">Gunakan scroll mouse untuk zoom dekat/jauh.</span>
							</div>
							<div class="metric-member-wrap" id="metricMemberWrap">
								<p class="metric-member-title" id="metricMemberTitle">Daftar Karyawan</p>
								<p class="metric-member-note" id="metricMemberNote">Pilih metrik untuk memuat daftar karyawan.</p>
								<div class="metric-member-toolbar">
									<input
										type="search"
										id="metricMemberSearchInput"
										class="metric-member-search"
										placeholder="Cari ID atau nama karyawan..."
										autocomplete="off"
									>
									<span class="metric-member-search-meta" id="metricMemberSearchMeta"></span>
								</div>
								<div class="metric-member-table-wrap">
									<table class="metric-member-table" aria-label="Daftar karyawan pada metrik grafik">
										<thead>
											<tr>
												<th>No</th>
												<th>ID</th>
												<th>Nama Karyawan</th>
												<th>Tanggal</th>
												<th class="metric-count-col" id="metricMemberCountHead">Jumlah</th>
											</tr>
										</thead>
										<tbody id="metricMemberList">
											<tr>
												<td colspan="5" class="metric-member-empty">Memuat daftar karyawan...</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
				</div>

				<p class="footer-note mb-0">Absen Online PNS - monitoring kehadiran lebih cepat dan rapi.</p>
			</div>
		</main>
	</div>

	<script>
		window.__HOME_INDEX_CONFIG = <?php echo $home_index_config_json; ?>;
	</script>
	<script>
		(function () {
			var config = window.__HOME_INDEX_CONFIG || {};
			var accountRows = Array.isArray(config.accountRows) ? config.accountRows : [];
			if (!accountRows.length) {
				return;
			}

			var editUsernameInput = document.getElementById('editUsernameInput');
			var editCrossBranchInput = document.getElementById('editCrossBranchInput');
			var customWeekdayCheckboxes = document.querySelectorAll('[data-edit-custom-weekday]');
			var editCustomOffStartDateInput = document.getElementById('editCustomOffStartDateInput');
			var editCustomOffEndDateInput = document.getElementById('editCustomOffEndDateInput');
			var editCustomWorkStartDateInput = document.getElementById('editCustomWorkStartDateInput');
			var editCustomWorkEndDateInput = document.getElementById('editCustomWorkEndDateInput');
			if (!editUsernameInput || !editCrossBranchInput) {
				return;
			}

			var byKey = {};
			var rows = [];
			var normalize = function (value) {
				return String(value || '').trim().toLowerCase();
			};
			var resolveCrossBranchValue = function (value) {
				if (typeof value === 'boolean') {
					return value ? 1 : 0;
				}
				if (typeof value === 'number') {
					return value === 1 ? 1 : 0;
				}
				var text = normalize(value);
				if (text === '1' || text === 'ya' || text === 'iya' || text === 'yes' || text === 'true' || text === 'aktif' || text === 'enabled' || text === 'on') {
					return 1;
				}
				return 0;
			};
			var normalizeWeekdayList = function (days) {
				var source = Array.isArray(days) ? days : [];
				var normalized = [];
				for (var dayIndex = 0; dayIndex < source.length; dayIndex += 1) {
					var weekdayValue = parseInt(source[dayIndex], 10);
					if (!isFinite(weekdayValue) || weekdayValue < 1 || weekdayValue > 7) {
						continue;
					}
					if (normalized.indexOf(weekdayValue) === -1) {
						normalized.push(weekdayValue);
					}
				}
				normalized.sort(function (a, b) {
					return a - b;
				});
				return normalized;
			};
			var normalizeDateRangeList = function (ranges) {
				var source = Array.isArray(ranges) ? ranges : [];
				var normalized = [];
				for (var rangeIndex = 0; rangeIndex < source.length; rangeIndex += 1) {
					var rangeRow = source[rangeIndex] || {};
					var startDate = String(rangeRow.start_date || rangeRow.start || '').trim();
					var endDate = String(rangeRow.end_date || rangeRow.end || '').trim();
					if (!startDate || !endDate) {
						continue;
					}
					if (startDate > endDate) {
						var tempDate = startDate;
						startDate = endDate;
						endDate = tempDate;
					}
					normalized.push({
						start_date: startDate,
						end_date: endDate
					});
				}
				return normalized;
			};
			var applyCustomScheduleFields = function (row) {
				var weekdayValues = normalizeWeekdayList(row && row.custom_allowed_weekdays);
				for (var checkboxIndex = 0; checkboxIndex < customWeekdayCheckboxes.length; checkboxIndex += 1) {
					var checkbox = customWeekdayCheckboxes[checkboxIndex];
					var checkboxValue = parseInt(checkbox.value, 10);
					checkbox.checked = weekdayValues.indexOf(checkboxValue) !== -1;
				}

				var offRanges = normalizeDateRangeList(row && row.custom_off_ranges);
				var workRanges = normalizeDateRangeList(row && row.custom_work_ranges);
				var firstOff = offRanges.length ? offRanges[0] : null;
				var firstWork = workRanges.length ? workRanges[0] : null;

				if (editCustomOffStartDateInput) {
					editCustomOffStartDateInput.value = firstOff ? firstOff.start_date : '';
				}
				if (editCustomOffEndDateInput) {
					editCustomOffEndDateInput.value = firstOff ? firstOff.end_date : '';
				}
				if (editCustomWorkStartDateInput) {
					editCustomWorkStartDateInput.value = firstWork ? firstWork.start_date : '';
				}
				if (editCustomWorkEndDateInput) {
					editCustomWorkEndDateInput.value = firstWork ? firstWork.end_date : '';
				}
			};

			for (var i = 0; i < accountRows.length; i += 1) {
				var row = accountRows[i] || {};
				var username = normalize(row.username);
				var employeeId = normalize(row.employee_id);
				if (username !== '') {
					byKey[username] = row;
				}
				if (employeeId !== '' && employeeId !== '-') {
					byKey[employeeId] = row;
					byKey[employeeId + ' - ' + username] = row;
				}
				rows.push(row);
			}

			var resolveAccount = function (rawValue, allowFuzzy) {
				var key = normalize(rawValue);
				if (key === '') {
					return null;
				}
				if (Object.prototype.hasOwnProperty.call(byKey, key)) {
					return byKey[key];
				}
				if (key.indexOf(' - ') !== -1) {
					var parts = key.split(' - ');
					var usernamePart = normalize(parts[parts.length - 1]);
					if (usernamePart !== '' && Object.prototype.hasOwnProperty.call(byKey, usernamePart)) {
						return byKey[usernamePart];
					}
				}
				if (!allowFuzzy) {
					return null;
				}

				var matches = [];
				for (var idx = 0; idx < rows.length; idx += 1) {
					var current = rows[idx] || {};
					var currentUsername = normalize(current.username);
					var currentEmployeeId = normalize(current.employee_id);
					var composite = currentEmployeeId !== '' && currentEmployeeId !== '-' ? currentEmployeeId + ' - ' + currentUsername : currentUsername;
					if (
						(currentUsername !== '' && currentUsername.indexOf(key) !== -1) ||
						(currentEmployeeId !== '' && currentEmployeeId !== '-' && currentEmployeeId.indexOf(key) !== -1) ||
						(composite.indexOf(key) !== -1)
					) {
						matches.push(current);
					}
				}

				return matches.length === 1 ? matches[0] : null;
			};

			var applyCrossBranchBySelectedUser = function () {
				var row = resolveAccount(editUsernameInput.value, true);
				if (!row) {
					if (normalize(editUsernameInput.value) === '') {
						editCrossBranchInput.value = '0';
						applyCustomScheduleFields(null);
					}
					return;
				}
				var crossBranchRaw = Object.prototype.hasOwnProperty.call(row, 'cross_branch_enabled')
					? row.cross_branch_enabled
					: row.lintas_cabang;
				editCrossBranchInput.value = resolveCrossBranchValue(crossBranchRaw) === 1 ? '1' : '0';
				applyCustomScheduleFields(row);
			};

			var scheduleApply = function () {
				window.setTimeout(applyCrossBranchBySelectedUser, 0);
			};

			editUsernameInput.addEventListener('input', scheduleApply);
			editUsernameInput.addEventListener('change', scheduleApply);
			editUsernameInput.addEventListener('blur', scheduleApply);

			var modalOpenButtons = document.querySelectorAll('[data-manage-modal-open="employeeManageModal"]');
			for (var buttonIndex = 0; buttonIndex < modalOpenButtons.length; buttonIndex += 1) {
				modalOpenButtons[buttonIndex].addEventListener('click', function () {
					window.setTimeout(applyCrossBranchBySelectedUser, 40);
				});
			}

			scheduleApply();
		})();
	</script>
	<script>
		(function () {
			if (window.__metricMemberWidgetInit) {
				return;
			}
			window.__metricMemberWidgetInit = true;

			var cfg = window.__HOME_INDEX_CONFIG || {};
			var chartEndpoint = String(cfg.chartEndpoint || '').trim();
			var modal = document.getElementById('metricModal');
			var modalTitle = document.getElementById('metricModalTitle');
			var memberWrap = document.getElementById('metricMemberWrap');
			var memberTitle = document.getElementById('metricMemberTitle');
			var memberNote = document.getElementById('metricMemberNote');
			var memberList = document.getElementById('metricMemberList');
			var memberSearchInput = document.getElementById('metricMemberSearchInput');
			var memberSearchMeta = document.getElementById('metricMemberSearchMeta');
			var memberTableWrap = modal.querySelector('.metric-member-table-wrap');
			var rangeButtons = document.querySelectorAll('.metric-range-btn[data-metric-range]');
			var metricCards = document.querySelectorAll('[data-metric-card]');
			if (!chartEndpoint || !modal || !memberWrap || !memberTitle || !memberNote || !memberList) {
				return;
			}

			var metricLabels = {
				hadir: 'Total Hadir',
				terlambat: 'Total Terlambat',
				izin_cuti: 'Total Izin/Cuti',
				alpha: 'Total Alpha'
			};
			var currentMetricKey = 'hadir';
			var pollingId = null;
			var requestSeq = 0;
			var pending = false;
			var memberRows = [];
			var memberCountByKey = {};
			var currentRangeKey = '1B';

			var parseMetricFromTitle = function () {
				var text = String((modalTitle && modalTitle.textContent) || '').toLowerCase();
				if (text.indexOf('terlambat') !== -1) { return 'terlambat'; }
				if (text.indexOf('izin') !== -1 || text.indexOf('cuti') !== -1) { return 'izin_cuti'; }
				if (text.indexOf('alpha') !== -1) { return 'alpha'; }
				return 'hadir';
			};

			var activeRange = function () {
				for (var i = 0; i < rangeButtons.length; i += 1) {
					if (rangeButtons[i].classList.contains('active')) {
						return String(rangeButtons[i].getAttribute('data-metric-range') || '1B').toUpperCase();
					}
				}
				return '1B';
			};

			var normalizeSearchText = function (value) {
				return String(value || '').toLowerCase().trim();
			};

			var buildRowCountKey = function (row) {
				var idPart = String((row && row.employee_id) || '').trim().toLowerCase();
				var userPart = String((row && row.username) || '').trim().toLowerCase();
				var namePart = String((row && row.name) || '').trim().toLowerCase();
				if (userPart !== '') {
					return 'u:' + userPart;
				}
				if (idPart !== '' && idPart !== '-') {
					return 'i:' + idPart;
				}
				return 'n:' + namePart;
			};

			var buildMemberCountMap = function (rows) {
				var counter = {};
				var i;
				for (i = 0; i < rows.length; i += 1) {
					var row = rows[i] || {};
					var key = buildRowCountKey(row) + '|d:' + normalizeSearchText(row.date || row.date_label || '-');
					if (!counter[key]) {
						counter[key] = 0;
					}
					counter[key] += 1;
				}
				return counter;
			};

			var buildMemberRows = function (details, names, unknownCount) {
				var rows = [];
				var i;
				if (Array.isArray(details) && details.length > 0) {
					for (i = 0; i < details.length; i += 1) {
						var detail = details[i] || {};
						var detailName = String(detail.name || '').trim();
						if (detailName === '') {
							continue;
						}
						var detailId = String(detail.employee_id || '-').trim();
						if (detailId === '') {
							detailId = '-';
						}
						rows.push({
							username: String(detail.username || '').trim(),
							employee_id: detailId,
							name: detailName,
							date: String(detail.date || detail.date_label || '-').trim() || '-',
							date_label: String(detail.date_label || detail.date || '-').trim() || '-'
						});
					}
				} else if (Array.isArray(names)) {
					for (i = 0; i < names.length; i += 1) {
						var fallbackName = String(names[i] || '').trim();
						if (fallbackName === '') {
							continue;
						}
						rows.push({
							username: '',
							employee_id: '-',
							name: fallbackName,
							date: '-',
							date_label: '-'
						});
					}
				}

				var unknownTotal = Number(unknownCount || 0);
				if (isFinite(unknownTotal) && unknownTotal > 0) {
					for (i = 0; i < unknownTotal; i += 1) {
						rows.push({
							username: '',
							employee_id: '-',
							name: 'Data tanpa nama',
							date: '-',
							date_label: '-'
						});
					}
				}

				return rows;
			};

			var toggleCountColumnByRange = function (rangeKey) {
				var rangeUpper = String(rangeKey || '').toUpperCase();
				var hide = (rangeUpper === '1H');
				if (memberTableWrap) {
					memberTableWrap.classList.toggle('hide-count-col', hide);
				}
			};

			var renderTableRows = function () {
				var filterText = normalizeSearchText(memberSearchInput ? memberSearchInput.value : '');
				memberList.innerHTML = '';

				var filteredRows = [];
				for (var i = 0; i < memberRows.length; i += 1) {
					var row = memberRows[i];
					var searchKey = normalizeSearchText((row.employee_id || '') + ' ' + (row.name || ''));
					if (filterText !== '' && searchKey.indexOf(filterText) === -1) {
						continue;
					}
					filteredRows.push(row);
				}

				if (memberSearchMeta) {
					memberSearchMeta.textContent = filteredRows.length + ' dari ' + memberRows.length + ' data';
				}

				if (filteredRows.length === 0) {
					var emptyRow = document.createElement('tr');
					var emptyCell = document.createElement('td');
					emptyCell.colSpan = String(currentRangeKey).toUpperCase() === '1H' ? 4 : 5;
					emptyCell.className = 'metric-member-empty';
					emptyCell.textContent = memberRows.length > 0
						? 'Tidak ada data yang cocok dengan pencarian.'
						: 'Tidak ada data karyawan pada metrik ini.';
					emptyRow.appendChild(emptyCell);
					memberList.appendChild(emptyRow);
					return;
				}

				for (var rowIndex = 0; rowIndex < filteredRows.length; rowIndex += 1) {
					var rowData = filteredRows[rowIndex];
					var tr = document.createElement('tr');

					var tdNo = document.createElement('td');
					tdNo.textContent = String(rowIndex + 1);
					tr.appendChild(tdNo);

					var tdId = document.createElement('td');
					tdId.textContent = String(rowData.employee_id || '-');
					tr.appendChild(tdId);

					var tdName = document.createElement('td');
					tdName.textContent = String(rowData.name || '-');
					tr.appendChild(tdName);

					var tdDate = document.createElement('td');
					tdDate.textContent = String(rowData.date_label || '-');
					tr.appendChild(tdDate);

					var tdCount = document.createElement('td');
					tdCount.className = 'metric-count-col';
					var countKey = buildRowCountKey(rowData) + '|d:' + normalizeSearchText(rowData.date || rowData.date_label || '-');
					var countValue = Number(memberCountByKey[countKey] || 0);
					if (!isFinite(countValue) || countValue <= 0) {
						countValue = 1;
					}
					tdCount.textContent = String(countValue) + 'x';
					tr.appendChild(tdCount);

					memberList.appendChild(tr);
				}
			};

			var render = function (title, note, details, names, unknownCount, rangeKey) {
				memberTitle.textContent = title;
				memberNote.textContent = note;
				memberWrap.style.display = 'grid';
				currentRangeKey = String(rangeKey || activeRange() || '1B').toUpperCase();
				memberRows = buildMemberRows(details, names, unknownCount);
				memberCountByKey = buildMemberCountMap(memberRows);
				toggleCountColumnByRange(currentRangeKey);
				if (memberSearchInput) {
					memberSearchInput.value = '';
				}
				renderTableRows();
			};

			var renderLoading = function () {
				var metricLabel = metricLabels[currentMetricKey] || 'Total Hadir';
				render('Daftar Karyawan - ' + metricLabel, 'Memuat daftar karyawan...', [], [], 0, currentRangeKey);
			};

			var renderError = function () {
				var metricLabel = metricLabels[currentMetricKey] || 'Total Hadir';
				render('Daftar Karyawan - ' + metricLabel, 'Gagal memuat daftar karyawan.', [], [], 0, currentRangeKey);
			};

			var fetchMembers = function () {
				if (!modal.classList.contains('show') || pending) {
					return;
				}
				currentMetricKey = parseMetricFromTitle();
				var range = activeRange();
				currentRangeKey = range;
				var requestId = requestSeq + 1;
				requestSeq = requestId;
				pending = true;
				renderLoading();

				var url = chartEndpoint
					+ '?metric=' + encodeURIComponent(currentMetricKey)
					+ '&range=' + encodeURIComponent(range)
					+ '&_members=' + String(Date.now());

				fetch(url, {
					credentials: 'same-origin',
					headers: { 'X-Requested-With': 'XMLHttpRequest' }
				})
					.then(function (resp) {
						if (!resp.ok) { throw new Error('HTTP ' + String(resp.status)); }
						return resp.json();
					})
					.then(function (json) {
						if (requestId !== requestSeq) { return; }
						if (!json || json.success !== true) { throw new Error('Invalid payload'); }
						var names = Array.isArray(json.employee_names) ? json.employee_names : [];
						var detailsRaw = Array.isArray(json.employee_details) ? json.employee_details : [];
						var details = [];
						for (var detailIndex = 0; detailIndex < detailsRaw.length; detailIndex += 1) {
							var row = detailsRaw[detailIndex];
							if (!row || typeof row !== 'object') {
								continue;
							}
							var rowName = String(row.name || '').trim();
							if (!rowName) {
								continue;
							}
							details.push({
								username: String(row.username || '').trim(),
								name: rowName,
								employee_id: String(row.employee_id || '-').trim() || '-',
								date: String(row.date || '').trim(),
								date_label: String(row.date_label || '').trim()
							});
						}
						var unknownCount = Number(json.employee_unknown_count || 0);
						if (!isFinite(unknownCount) || unknownCount < 0) { unknownCount = 0; }
						var total = Number(json.employee_count || 0);
						if (!isFinite(total) || total < 0) {
							total = 0;
						}
						if (total <= 0) {
							total = (details.length > 0 ? details.length : names.length) + unknownCount;
						}
						var uniqueCount = Number(json.employee_unique_count || 0);
						if (!isFinite(uniqueCount) || uniqueCount < 0) { uniqueCount = 0; }
						if (uniqueCount <= 0) {
							uniqueCount = names.length;
						}
						var rangeLabel = String(json.range_label || range);
						var metricLabel = String(json.metric_label || (metricLabels[currentMetricKey] || 'Total Hadir'));
						var note = total > 0
							? (rangeLabel + ' - total ' + String(total) + ' data')
							: (rangeLabel + ' - tidak ada data karyawan pada metrik ini.');
						if (total > 0 && uniqueCount > 0) {
							note += ' (' + String(uniqueCount) + ' karyawan unik)';
						}
						if (total > 0) {
							note += '.';
						}
						render('Daftar Karyawan - ' + metricLabel, note, details, names, unknownCount, range);
					})
					.catch(function () {
						if (requestId !== requestSeq) { return; }
						renderError();
					})
					.then(function () {
						if (requestId === requestSeq) {
							pending = false;
						}
					});
			};

			var startPolling = function () {
				if (pollingId !== null) {
					window.clearInterval(pollingId);
				}
				pollingId = window.setInterval(function () {
					if (modal.classList.contains('show')) {
						fetchMembers();
					}
				}, 20000);
			};

			var stopPolling = function () {
				if (pollingId !== null) {
					window.clearInterval(pollingId);
					pollingId = null;
				}
			};

			for (var i = 0; i < metricCards.length; i += 1) {
				(function (card) {
					card.addEventListener('click', function () {
						var key = String(card.getAttribute('data-metric-card') || '').trim();
						if (key) { currentMetricKey = key; }
						window.setTimeout(fetchMembers, 160);
					});
				})(metricCards[i]);
			}

			for (var j = 0; j < rangeButtons.length; j += 1) {
				rangeButtons[j].addEventListener('click', function () {
					window.setTimeout(fetchMembers, 160);
				});
			}

			if (memberSearchInput) {
				memberSearchInput.addEventListener('input', renderTableRows);
			}

			var observer = new MutationObserver(function () {
				if (modal.classList.contains('show')) {
					fetchMembers();
					startPolling();
				} else {
					stopPolling();
				}
			});
			observer.observe(modal, { attributes: true, attributeFilter: ['class'] });

			var chartCanvas = document.getElementById('metricChartCanvas');
			if (chartCanvas) {
				chartCanvas.addEventListener('wheel', function (event) {
					if (!modal.classList.contains('show')) {
						return;
					}
					if (event.ctrlKey || event.metaKey) {
						return;
					}
					var modalBody = modal.querySelector('.metric-modal-body');
					if (!modalBody) {
						return;
					}
					event.preventDefault();
					event.stopPropagation();
					modalBody.scrollTop += event.deltaY;
				}, { passive: false, capture: true });
			}

			var metricRangeNote = modal.querySelector('.metric-range-note');
			if (metricRangeNote) {
				metricRangeNote.textContent = 'Scroll untuk turun/naik daftar. Gunakan Ctrl + scroll untuk zoom grafik.';
			}
		})();
	</script>
			<script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.2/dist/lightweight-charts.standalone.production.js"></script>
			<script>
				(function () {
					var patchChartFactory = function () {
						if (!window.LightweightCharts || typeof window.LightweightCharts.createChart !== 'function') {
							return false;
						}
						if (window.__homeChartThemePatchApplied === true) {
							return true;
						}
						var originalCreateChart = window.LightweightCharts.createChart;
						var resolveDarkModeState = function () {
							var root = document.documentElement;
							var body = document.body;
							var rootTheme = root ? String(root.getAttribute('data-theme') || '').toLowerCase() : '';
							return !!(
								(root && root.classList.contains('theme-dark')) ||
								(body && body.classList.contains('theme-dark')) ||
								rootTheme === 'dark'
							);
						};
							var resolveThemePatch = function (isDarkMode) {
								if (isDarkMode) {
									return {
										layout: {
											background: { type: 'solid', color: '#0f1d2a' },
											textColor: '#d7e5f4'
										},
									grid: {
										vertLines: { color: '#274158' },
										horzLines: { color: '#274158' }
									},
									crosshair: {
										vertLine: {
											color: 'rgba(121, 161, 201, 0.45)',
											width: 1,
											style: 2,
											labelBackgroundColor: '#244d72'
										},
										horzLine: {
											color: 'rgba(121, 161, 201, 0.35)',
											width: 1,
											style: 2,
											labelBackgroundColor: '#244d72'
										}
									},
									rightPriceScale: { borderColor: '#385a78' },
									timeScale: { borderColor: '#385a78' }
								};
							}

								return {
									layout: {
										background: { type: 'solid', color: '#f9fcff' },
										textColor: '#4a627b'
									},
								grid: {
									vertLines: { color: '#e8f1fa' },
									horzLines: { color: '#e8f1fa' }
								},
								crosshair: {
									vertLine: {
										color: 'rgba(20, 79, 134, 0.45)',
										width: 1,
										style: 2,
										labelBackgroundColor: '#0f5c93'
									},
									horzLine: {
										color: 'rgba(20, 79, 134, 0.35)',
										width: 1,
										style: 2,
										labelBackgroundColor: '#0f5c93'
									}
								},
								rightPriceScale: { borderColor: '#c6d8ea' },
								timeScale: { borderColor: '#c6d8ea' }
							};
						};
							var applyThemeToChart = function (chartInstance) {
								if (!chartInstance || typeof chartInstance.applyOptions !== 'function') {
									return;
								}
								var isDarkMode = resolveDarkModeState();
								var themePatch = resolveThemePatch(isDarkMode);
								try {
									chartInstance.applyOptions({
										layout: themePatch.layout,
										grid: themePatch.grid,
										crosshair: themePatch.crosshair,
										rightPriceScale: themePatch.rightPriceScale,
										timeScale: themePatch.timeScale
									});
								} catch (error) {}
								var chartHost = document.getElementById('metricChartCanvas');
								if (chartHost && chartHost.style) {
									chartHost.style.backgroundColor = isDarkMode ? '#0f1d2a' : '#f9fcff';
								}
							};
						if (!Array.isArray(window.__homeChartThemeInstances)) {
							window.__homeChartThemeInstances = [];
						}
						if (window.__homeChartThemeListenerBound !== true) {
							window.addEventListener('home-theme-changed', function () {
								var instances = Array.isArray(window.__homeChartThemeInstances)
									? window.__homeChartThemeInstances
									: [];
								for (var i = 0; i < instances.length; i += 1) {
									applyThemeToChart(instances[i]);
								}
							});
							window.__homeChartThemeListenerBound = true;
						}
						window.LightweightCharts.createChart = function (container, options) {
							var resolved = options && typeof options === 'object' ? options : {};
							var layout = resolved.layout && typeof resolved.layout === 'object' ? resolved.layout : {};
							var grid = resolved.grid && typeof resolved.grid === 'object' ? resolved.grid : {};
							var crosshair = resolved.crosshair && typeof resolved.crosshair === 'object' ? resolved.crosshair : {};
							var vertLines = grid.vertLines && typeof grid.vertLines === 'object' ? grid.vertLines : {};
							var horzLines = grid.horzLines && typeof grid.horzLines === 'object' ? grid.horzLines : {};
							var vertLine = crosshair.vertLine && typeof crosshair.vertLine === 'object' ? crosshair.vertLine : {};
							var horzLine = crosshair.horzLine && typeof crosshair.horzLine === 'object' ? crosshair.horzLine : {};
							var rightScale = resolved.rightPriceScale && typeof resolved.rightPriceScale === 'object' ? resolved.rightPriceScale : {};
							var timeScale = resolved.timeScale && typeof resolved.timeScale === 'object' ? resolved.timeScale : {};
							var themePatch = resolveThemePatch(resolveDarkModeState());
							resolved = Object.assign({}, resolved, {
								layout: Object.assign({}, layout, themePatch.layout),
								grid: Object.assign({}, grid, {
									vertLines: Object.assign({}, vertLines, themePatch.grid.vertLines),
									horzLines: Object.assign({}, horzLines, themePatch.grid.horzLines)
								}),
								crosshair: Object.assign({}, crosshair, {
									vertLine: Object.assign({}, vertLine, themePatch.crosshair.vertLine),
									horzLine: Object.assign({}, horzLine, themePatch.crosshair.horzLine)
								}),
								rightPriceScale: Object.assign({}, rightScale, themePatch.rightPriceScale),
								timeScale: Object.assign({}, timeScale, themePatch.timeScale)
							});
							var chartInstance = originalCreateChart.call(window.LightweightCharts, container, resolved);
							if (chartInstance) {
								window.__homeChartThemeInstances.push(chartInstance);
								applyThemeToChart(chartInstance);
							}
							return chartInstance;
						};
						window.__homeChartThemePatchApplied = true;
						return true;
					};

					if (patchChartFactory()) {
						return;
					}
					var tries = 0;
					var timer = window.setInterval(function () {
						tries += 1;
						if (patchChartFactory() || tries >= 120) {
							window.clearInterval(timer);
						}
					}, 50);
				})();
			</script>
			<script defer src="<?php echo htmlspecialchars($base_path.'/'.$home_index_js_file, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo rawurlencode($home_index_js_version); ?>"></script>
		<script defer src="<?php echo htmlspecialchars($base_path.'/'.$home_index_collab_js_file, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo rawurlencode($home_index_collab_js_version); ?>"></script>
		<script>
			(function () {
				var storageKey = 'home_index_theme';
				var root = document.documentElement;
				var body = document.body;
				var toggle = document.getElementById('themeToggleButton');
				if (!root || !body || !toggle) {
					return;
				}

					var writeTheme = function (theme) {
						if (theme !== 'dark' && theme !== 'light') {
							return;
						}
						var manager = window.__homeThemeManager;
						if (manager && typeof manager.persistTheme === 'function') {
							manager.persistTheme(theme);
							return;
						}
						try {
							window.localStorage.setItem(storageKey, theme);
						} catch (error) {}
						try {
							document.cookie = storageKey + '=' + encodeURIComponent(theme) + ';path=/;max-age=31536000;SameSite=Lax';
						} catch (error) {}
					};
					var readTheme = function () {
						var saved = '';
						try {
							saved = String(window.localStorage.getItem(storageKey) || '').toLowerCase();
						} catch (error) {}
						if (saved === 'dark' || saved === 'light') {
							return saved;
						}
						var cookieMatch = document.cookie.match(/(?:^|;\s*)home_index_theme=(dark|light)\b/i);
						if (cookieMatch && cookieMatch[1]) {
							return String(cookieMatch[1]).toLowerCase();
						}
						return '';
					};
					var applyTheme = function (theme, persist) {
						var isDark = theme === 'dark';
						root.classList.toggle('theme-dark', isDark);
						body.classList.toggle('theme-dark', isDark);
						root.setAttribute('data-theme', isDark ? 'dark' : 'light');
						toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
						toggle.setAttribute('aria-label', isDark ? 'Aktifkan mode siang' : 'Aktifkan mode malam');
						toggle.title = isDark ? 'Ganti ke mode siang' : 'Ganti ke mode malam';
					if (persist === true) {
						writeTheme(isDark ? 'dark' : 'light');
					}
					try {
						window.dispatchEvent(new CustomEvent('home-theme-changed', {
							detail: {
								isDark: isDark,
								theme: isDark ? 'dark' : 'light'
							}
						}));
					} catch (error) {}
				};

					var saved = readTheme();
					if (saved === 'dark' || saved === 'light') {
						applyTheme(saved, false);
					} else {
						var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
						applyTheme(prefersDark ? 'dark' : 'light', true);
					}

				toggle.addEventListener('click', function () {
					var activeDark = body.classList.contains('theme-dark') || root.classList.contains('theme-dark');
					applyTheme(activeDark ? 'light' : 'dark', true);
				});
			})();
		</script>
	</body>
	</html>
