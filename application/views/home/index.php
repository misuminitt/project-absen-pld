<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$summary = isset($summary) && is_array($summary) ? $summary : array();
$recent_logs = isset($recent_logs) && is_array($recent_logs) ? $recent_logs : array();
$employee_accounts = isset($employee_accounts) && is_array($employee_accounts) ? $employee_accounts : array();
$employee_accounts_json = json_encode($employee_accounts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($employee_accounts_json === FALSE) {
	$employee_accounts_json = '[]';
}
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

$status_hari_ini = isset($summary['status_hari_ini']) ? (string) $summary['status_hari_ini'] : 'Belum Check In';
$status_key = strtolower($status_hari_ini);
$status_class = 'status-default';
if (strpos($status_key, 'hadir') !== FALSE || strpos($status_key, 'check in') !== FALSE) {
	$status_class = 'status-success';
}
elseif (strpos($status_key, 'terlambat') !== FALSE) {
	$status_class = 'status-warning';
}
elseif (strpos($status_key, 'izin') !== FALSE || strpos($status_key, 'cuti') !== FALSE) {
	$status_class = 'status-info';
}

$script_name = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
$base_path = str_replace('\\', '/', dirname($script_name));
if ($base_path === '/' || $base_path === '.') {
	$base_path = '';
}

$logo_path = 'src/assets/pns_dashboard.png';
if (is_file(FCPATH.'src/assets/pns_dashboard.png')) {
	$logo_path = 'src/assets/pns_dashboard.png';
}
elseif (is_file(FCPATH.'src/assts/pns_dashboard.png')) {
	$logo_path = 'src/assts/pns_dashboard.png';
}
elseif (is_file(FCPATH.'src/assets/pns_logo_nav.png')) {
	$logo_path = 'src/assets/pns_logo_nav.png';
}
elseif (is_file(FCPATH.'src/assts/pns_logo_nav.png')) {
	$logo_path = 'src/assts/pns_logo_nav.png';
}
elseif (is_file(FCPATH.'src/assts/pns_new.png')) {
	$logo_path = 'src/assts/pns_new.png';
}
elseif (is_file(FCPATH.'src/assets/pns_new.png')) {
	$logo_path = 'src/assets/pns_new.png';
}

$logo_url = $base_path.'/'.$logo_path;
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Dashboard Absen Online'; ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
	<style>
		:root {
			--brand-dark: #083c68;
			--brand-main: #0f5c93;
			--brand-soft: #e8f4ff;
			--text-main: #0d2238;
			--text-soft: #4d637a;
			--line-soft: #dbe7f3;
			--surface: #ffffff;
			--surface-alt: #f7fbff;
			--success: #228b5f;
			--warning: #be7a13;
			--info: #1d6599;
			--muted: #7b8fa3;
		}

		* {
			box-sizing: border-box;
			scrollbar-width: none;
			-ms-overflow-style: none;
		}

		*::-webkit-scrollbar {
			width: 0;
			height: 0;
		}

		html,
		body {
			margin: 0;
			padding: 0;
			min-height: 100%;
		}

		body {
			font-family: 'Plus Jakarta Sans', sans-serif;
			color: var(--text-main);
			background:
				radial-gradient(circle at 12% 10%, rgba(73, 172, 255, 0.16) 0%, transparent 36%),
				radial-gradient(circle at 90% 5%, rgba(255, 216, 165, 0.18) 0%, transparent 32%),
				linear-gradient(180deg, #f0f8ff 0%, #ffffff 44%);
		}

		.main-shell {
			min-height: 100dvh;
		}

		.topbar {
			background: linear-gradient(120deg, var(--brand-dark) 0%, var(--brand-main) 100%);
			color: #ffffff;
			box-shadow: 0 10px 24px rgba(8, 60, 104, 0.22);
			position: sticky;
			top: 0;
			z-index: 60;
		}

		.topbar-container {
			width: 100%;
			padding-top: 1rem;
			padding-bottom: 1rem;
			padding-left: 1rem !important;
			padding-right: 1rem !important;
		}

		@media (min-width: 576px) {
			.topbar-container {
				padding-left: 1.5rem !important;
				padding-right: 1.5rem !important;
			}
		}

		@media (min-width: 768px) {
			.topbar-container {
				padding-left: 2rem !important;
				padding-right: 2rem !important;
			}
		}

		@media (min-width: 992px) {
			.topbar-container {
				padding-left: 2.5rem !important;
				padding-right: 2.5rem !important;
			}
		}

		.topbar-inner {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 1rem;
			width: 100%;
		}

		.brand-block {
			display: inline-flex;
			align-items: center;
			gap: 0.56rem;
			text-decoration: none;
		}

		.brand-logo {
			height: 48px;
			width: auto;
			display: block;
			object-fit: contain;
		}

		.brand-text {
			font-size: 1.04rem;
			font-weight: 700;
			letter-spacing: 0.02em;
			color: #ffffff;
		}

		.logout {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			text-decoration: none;
			padding: 0.52rem 0.9rem;
			border-radius: 999px;
			background: rgba(255, 255, 255, 0.16);
			color: #ffffff;
			font-size: 0.83rem;
			font-weight: 700;
			transition: background-color 0.2s ease;
		}

		.logout:hover {
			background: rgba(255, 255, 255, 0.24);
		}

		.nav-right {
			display: flex;
			align-items: center;
			gap: 0.45rem;
		}

		.desktop-nav {
			display: none;
			align-items: center;
		}

		.nav-link-custom,
		.help-toggle {
			display: inline-block;
			padding: 0.5rem 1.05rem;
			font-size: 0.84rem;
			font-weight: 400;
			letter-spacing: 0.04em;
			text-transform: uppercase;
			color: #ffffff;
			position: relative;
			text-decoration: none;
			transition: color 0.2s ease;
			background: transparent;
			border: 0;
			white-space: nowrap;
		}

		.nav-link-custom:hover,
		.nav-link-custom:focus-visible,
		.help-toggle:hover,
		.help-toggle:focus-visible {
			color: #ffffff;
		}

		.nav-link-custom.active::after {
			content: '';
			position: absolute;
			left: 0.98rem;
			right: 0.98rem;
			bottom: 0.06rem;
			height: 0.14rem;
			border-radius: 999px;
			background: #ffd06a;
		}

		.help-menu {
			position: relative;
		}

		.help-toggle {
			display: inline-flex;
			align-items: center;
			gap: 0.45rem;
			cursor: pointer;
		}

		.help-indicator {
			display: inline-block;
			width: 14px;
			text-align: center;
			font-weight: 700;
		}

		.help-dropdown {
			position: absolute;
			left: 50%;
			top: calc(100% + 10px);
			min-width: 220px;
			background: #ffffff;
			border-radius: 10px;
			overflow: hidden;
			box-shadow: 0 16px 38px rgba(5, 20, 41, 0.2);
			transform: translate(-50%, -8px);
			opacity: 0;
			pointer-events: none;
			transition: opacity 0.25s ease, transform 0.25s ease;
			z-index: 80;
		}

		.help-menu:hover .help-dropdown,
		.help-menu.open .help-dropdown {
			opacity: 1;
			pointer-events: auto;
			transform: translate(-50%, 0);
		}

		.help-dropdown a {
			display: block;
			text-decoration: none;
			padding: 0.74rem 0.96rem;
			color: var(--text-main);
			font-size: 0.83rem;
			font-weight: 600;
			transition: background-color 0.2s ease, color 0.2s ease;
		}

		.help-dropdown a:hover,
		.help-dropdown a:focus-visible {
			background: var(--brand-main);
			color: #ffffff;
		}

		.menu-toggle {
			width: 40px;
			height: 40px;
			border: 0;
			border-radius: 12px;
			background: transparent;
			color: #ffffff;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			transition: background-color 0.2s ease, transform 0.2s ease;
		}

		.menu-toggle:hover {
			background: rgba(255, 255, 255, 0.12);
		}

		.menu-toggle:active {
			transform: scale(0.96);
		}

		.menu-icon {
			width: 25px;
			height: 25px;
			display: block;
			fill: none;
			stroke: currentColor;
			stroke-width: 2;
			stroke-linecap: round;
		}

		.mobile-backdrop {
			position: fixed;
			inset: 0;
			background: rgba(8, 17, 28, 0.5);
			opacity: 0;
			pointer-events: none;
			transition: opacity 0.28s ease;
			z-index: 70;
		}

		.mobile-drawer {
			position: fixed;
			top: 0;
			right: 0;
			width: 85%;
			max-width: 24rem;
			height: 100dvh;
			background: #ffffff;
			transform: translateX(100%);
			transition: transform 0.3s ease;
			z-index: 80;
			box-shadow: -16px 0 40px rgba(4, 16, 30, 0.26);
		}

		.mobile-drawer-inner {
			display: flex;
			flex-direction: column;
			height: 100%;
		}

		.mobile-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 1.25rem 1.5rem;
			border-bottom: 1px solid #edf2f7;
			flex-shrink: 0;
		}

		.mobile-logo {
			height: 48px;
			width: auto;
			object-fit: contain;
		}

		.mobile-close {
			width: 40px;
			height: 40px;
			border: 0;
			border-radius: 999px;
			background-color: var(--brand-dark);
			color: #ffffff;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 0;
			font-size: 1.25rem;
			font-weight: 600;
			line-height: 1;
			font-family: 'Plus Jakarta Sans', sans-serif;
			cursor: pointer;
			box-shadow: 0 8px 18px rgba(8, 60, 104, 0.22);
			transition: background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
		}

		.mobile-close:hover {
			background-color: var(--brand-main);
			box-shadow: 0 10px 20px rgba(15, 92, 147, 0.28);
		}

		.mobile-close:active {
			transform: scale(0.96);
		}

		.mobile-close-icon {
			display: block;
			line-height: 1;
			transform: translateY(-2px);
		}

		.mobile-nav {
			padding: 0.6rem 1.5rem 1.4rem;
			overflow-y: auto;
		}

		.mobile-link,
		.mobile-help-toggle {
			display: flex;
			align-items: center;
			justify-content: space-between;
			width: 100%;
			padding: 1rem 0;
			border-bottom: 1px solid #e1e7ef;
			color: #0f172a;
			text-decoration: none;
			font-size: 1.05rem;
			font-weight: 700;
			background: transparent;
			border-left: 0;
			border-right: 0;
			border-top: 0;
			text-transform: none;
		}

		.mobile-help-toggle span {
			font-size: 1.5rem;
			line-height: 1;
		}

		.mobile-help-list {
			max-height: 0;
			opacity: 0;
			overflow: hidden;
			transition: max-height 0.32s ease, opacity 0.22s ease;
		}

		.mobile-help-list.is-open {
			max-height: 220px;
			opacity: 1;
		}

		.mobile-help-list a {
			display: block;
			text-decoration: none;
			color: #5d6b80;
			font-size: 0.95rem;
			padding: 0.52rem 0.72rem 0.52rem 0.82rem;
		}

		.mobile-help-list a:hover {
			color: var(--brand-main);
		}

		.mobile-member-area {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 100%;
			margin-top: 1.5rem;
			padding: 0.9rem 1rem;
			border-radius: 11px;
			text-decoration: none;
			font-size: 0.92rem;
			font-weight: 700;
			color: #ffffff;
			background: #2c73cf;
			transition: background-color 0.2s ease;
		}

		.mobile-member-area:hover {
			background: #1f60b5;
		}

		.mobile-contact {
			margin-top: 1.65rem;
		}

		.mobile-contact-title {
			margin: 0;
			font-size: 1.125rem;
			font-weight: 800;
			color: #111827;
		}

		.mobile-contact-list {
			list-style: none;
			margin: 0.7rem 0 0;
			padding: 0;
			display: grid;
			gap: 0.72rem;
		}

		.mobile-contact-item {
			display: flex;
			gap: 0.62rem;
			color: #4b5a6f;
			font-size: 0.9rem;
			line-height: 1.45;
		}

		.mobile-contact-item.inline {
			align-items: center;
		}

		.mobile-contact-icon {
			flex: 0 0 20px;
			width: 20px;
			height: 20px;
			margin-top: 0.18rem;
			color: #1f5ca7;
		}

		.mobile-contact-item.inline .mobile-contact-icon {
			margin-top: 0;
		}

		.mobile-contact-link {
			color: #111827;
			text-decoration: none;
			transition: color 0.2s ease;
		}

		.mobile-contact-link:hover {
			color: #1f6fd6;
		}

		.mobile-logout {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 100%;
			margin-top: 1.1rem;
			padding: 0.72rem 1rem;
			border-radius: 10px;
			text-decoration: none;
			font-size: 0.88rem;
			font-weight: 700;
			color: #1b4b80;
			background: #eaf2fb;
		}

		body.nav-open {
			overflow: hidden;
		}

		body.nav-open .mobile-backdrop {
			opacity: 1;
			pointer-events: auto;
		}

		body.nav-open .mobile-drawer {
			transform: translateX(0);
		}

		.hero {
			padding-top: 1.75rem;
			padding-bottom: 1.1rem;
		}

		.hero-card {
			background: linear-gradient(145deg, #ffffff 0%, #f4faff 100%);
			border: 1px solid var(--line-soft);
			border-radius: 20px;
			padding: 1.2rem;
			box-shadow: 0 14px 36px rgba(7, 49, 84, 0.08);
		}

		.hero-title {
			margin: 0;
			font-size: 1.4rem;
			font-weight: 800;
			letter-spacing: -0.02em;
		}

		.hero-subtitle {
			margin: 0.5rem 0 0;
			color: var(--text-soft);
			font-weight: 500;
			font-size: 0.92rem;
		}

		.clock-box {
			border: 1px dashed #b7cee3;
			border-radius: 14px;
			padding: 0.7rem 0.9rem;
			background: #fbfeff;
		}

		.clock-label {
			margin: 0;
			font-size: 0.68rem;
			font-weight: 700;
			letter-spacing: 0.09em;
			text-transform: uppercase;
			color: #4d7495;
		}

		.clock-value {
			margin: 0.25rem 0 0;
			font-size: 1.02rem;
			font-weight: 800;
			color: var(--brand-dark);
		}

		.status-pill {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			border-radius: 999px;
			padding: 0.35rem 0.76rem;
			font-size: 0.74rem;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.06em;
		}

		.status-default {
			background: #edf2f8;
			color: #4e6478;
		}

		.status-success {
			background: #e2f5ec;
			color: var(--success);
		}

		.status-warning {
			background: #fff5e3;
			color: var(--warning);
		}

		.status-info {
			background: #e6f4ff;
			color: var(--info);
		}

		.section-title {
			font-size: 1.03rem;
			font-weight: 800;
			margin-bottom: 0.9rem;
			letter-spacing: -0.01em;
		}

		.mini-card {
			height: 100%;
			background: var(--surface);
			border: 1px solid var(--line-soft);
			border-radius: 16px;
			padding: 1rem;
			box-shadow: 0 8px 20px rgba(7, 49, 84, 0.06);
			position: relative;
			overflow: hidden;
			transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
		}

		.mini-card.is-clickable {
			cursor: pointer;
		}

		.mini-card.is-clickable:hover {
			transform: translateY(-2px);
			border-color: #8fc0e7;
			box-shadow: 0 14px 28px rgba(12, 61, 102, 0.12);
		}

		.mini-card.is-clickable:focus-visible {
			outline: 0;
			border-color: #1f78cf;
			box-shadow: 0 0 0 3px rgba(37, 125, 209, 0.2);
		}

		.mini-label {
			margin: 0;
			font-size: 0.72rem;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			color: var(--muted);
		}

		.mini-value {
			margin: 0.34rem 0 0;
			font-size: 1.3rem;
			font-weight: 800;
			color: var(--brand-dark);
		}

		.mini-hint {
			margin: 0.42rem 0 0;
			font-size: 0.72rem;
			font-weight: 700;
			color: #4e6b89;
			opacity: 0.86;
		}

		.metric-modal {
			position: fixed;
			inset: 0;
			display: none;
			align-items: center;
			justify-content: center;
			padding: 1rem;
			background: rgba(9, 20, 36, 0.72);
			z-index: 140;
		}

		.metric-modal.show {
			display: flex;
		}

		.metric-modal-card {
			width: min(1180px, 96vw);
			max-height: min(92vh, 820px);
			background: #ffffff;
			border: 1px solid #cde1f3;
			border-radius: 18px;
			box-shadow: 0 30px 64px rgba(4, 18, 35, 0.4);
			display: flex;
			flex-direction: column;
			overflow: hidden;
		}

		.metric-modal-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.8rem;
			padding: 0.9rem 1rem;
			background: linear-gradient(120deg, var(--brand-dark) 0%, var(--brand-main) 100%);
			color: #ffffff;
		}

		.metric-modal-title-wrap {
			min-width: 0;
			flex: 1;
		}

		.metric-modal-title {
			margin: 0;
			font-size: 1rem;
			font-weight: 800;
			letter-spacing: 0.01em;
		}

		.metric-modal-subtitle {
			margin: 0.2rem 0 0;
			font-size: 0.76rem;
			opacity: 0.86;
			font-weight: 600;
		}

		.metric-modal-close {
			border: 0;
			width: 34px;
			height: 34px;
			border-radius: 999px;
			background: rgba(255, 255, 255, 0.2);
			color: #ffffff;
			font-size: 1.15rem;
			font-weight: 700;
			line-height: 1;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			flex-shrink: 0;
			transition: background-color 0.2s ease;
		}

		.metric-modal-close:hover {
			background: rgba(255, 255, 255, 0.32);
		}

		.metric-modal-body {
			padding: 0.9rem 1rem 1rem;
			display: flex;
			flex-direction: column;
			gap: 0.78rem;
			min-height: 0;
		}

		.metric-legend {
			display: flex;
			align-items: center;
			gap: 0.52rem;
			font-size: 0.76rem;
			font-weight: 700;
			color: #45617e;
			min-height: 1.05rem;
			overflow-x: auto;
			white-space: nowrap;
		}

		.metric-legend .label {
			font-weight: 800;
			color: #2f4f6e;
		}

		.metric-legend .value-up {
			color: #158a5c;
		}

		.metric-legend .value-down {
			color: #cf3e4d;
		}

		.metric-chart-wrap {
			border: 1px solid #cfe1f1;
			border-radius: 14px;
			background: linear-gradient(180deg, #fcfeff 0%, #f4f9ff 100%);
			padding: 0.58rem;
			height: min(62vh, 520px);
			min-height: 320px;
			overflow: hidden;
		}

		.metric-chart-canvas {
			width: 100%;
			height: 100%;
			border-radius: 10px;
			background: #f9fcff;
		}

		.metric-chart-canvas a[href*="tradingview"],
		.metric-chart-canvas [href*="tradingview.com"],
		.metric-chart-canvas .tv-lightweight-charts-attribution-logo,
		.metric-chart-canvas [class*="attribution"],
		.metric-chart-canvas [id*="tradingview"] {
			display: none !important;
			opacity: 0 !important;
			visibility: hidden !important;
			pointer-events: none !important;
		}

		.metric-range-row {
			display: flex;
			align-items: center;
			flex-wrap: wrap;
			gap: 0.48rem;
		}

		.metric-range-btn {
			border: 1px solid #bfd8ee;
			background: #ffffff;
			color: #2f506f;
			font-size: 0.75rem;
			font-weight: 800;
			padding: 0.38rem 0.86rem;
			border-radius: 999px;
			cursor: pointer;
			transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
		}

		.metric-range-btn.active {
			background: linear-gradient(120deg, #1d6fbf 0%, #135190 100%);
			border-color: #1e6fbe;
			color: #ffffff;
		}

		.metric-range-note {
			font-size: 0.73rem;
			font-weight: 700;
			color: #587493;
		}

		.action-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
			gap: 0.9rem;
		}

		.action-card {
			background: var(--surface);
			border: 1px solid var(--line-soft);
			border-radius: 16px;
			padding: 1rem;
			box-shadow: 0 8px 20px rgba(7, 49, 84, 0.06);
			display: flex;
			flex-direction: column;
		}

		.action-title {
			margin: 0;
			font-size: 0.93rem;
			font-weight: 800;
		}

		.action-text {
			margin: 0.4rem 0 0.9rem;
			font-size: 0.83rem;
			color: var(--text-soft);
		}

		.action-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			border: none;
			border-radius: 11px;
			padding: 0.54rem 0.9rem;
			font-size: 0.8rem;
			font-weight: 700;
			color: #ffffff;
			background: linear-gradient(140deg, var(--brand-main) 0%, var(--brand-dark) 100%);
			text-decoration: none;
			margin-top: auto;
			align-self: flex-start;
		}

		.action-btn.secondary {
			background: linear-gradient(140deg, #4f6c87 0%, #365269 100%);
		}

		.account-notice {
			border-radius: 12px;
			padding: 0.72rem 0.85rem;
			font-size: 0.82rem;
			font-weight: 600;
			margin-bottom: 0.82rem;
			border: 1px solid transparent;
		}

		.account-notice.success {
			background: #e5f8ee;
			color: #1b6f4d;
			border-color: #b5e9cb;
		}

		.account-notice.error {
			background: #fff0f0;
			color: #a33a3a;
			border-color: #ffd0d0;
		}

		.account-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 1rem;
			align-items: start;
		}

		.account-card {
			background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
			border: 1px solid #d5e6f3;
			border-radius: 18px;
			padding: 1.05rem;
			box-shadow: 0 12px 24px rgba(7, 49, 84, 0.08);
		}

		.account-card h3 {
			margin: 0;
			font-size: 1rem;
			font-weight: 800;
			color: #163e63;
		}

		.account-card p {
			margin: 0.38rem 0 0.86rem;
			font-size: 0.84rem;
			line-height: 1.45;
			color: #4f6983;
		}

		.account-form {
			display: grid;
			gap: 0.68rem;
		}

		.account-form-row {
			display: grid;
			gap: 0.68rem;
		}

		@media (min-width: 576px) {
			.account-form-row.two {
				grid-template-columns: 1fr 1fr;
			}
		}

		.account-label {
			margin: 0;
			font-size: 0.73rem;
			font-weight: 700;
			letter-spacing: 0.07em;
			text-transform: uppercase;
			color: #577694;
		}

		.account-input {
			width: 100%;
			border: 1px solid #c4d9ec;
			border-radius: 11px;
			padding: 0.56rem 0.68rem;
			min-height: 42px;
			font-size: 0.86rem;
			font-family: inherit;
			color: #1b3653;
			background: #ffffff;
			transition: border-color 0.2s ease, box-shadow 0.2s ease;
		}

		.account-input:focus {
			outline: none;
			border-color: #2a81d5;
			box-shadow: 0 0 0 3px rgba(43, 130, 213, 0.14);
		}

		.account-submit {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 100%;
			border: none;
			border-radius: 12px;
			padding: 0.54rem 0.9rem;
			font-size: 0.87rem;
			font-weight: 800;
			color: #ffffff;
			background: linear-gradient(140deg, var(--brand-main) 0%, var(--brand-dark) 100%);
			cursor: pointer;
			transition: transform 0.16s ease, filter 0.16s ease;
		}

		.account-submit:hover {
			filter: brightness(1.03);
			transform: translateY(-1px);
		}

		.account-submit.delete {
			background: linear-gradient(140deg, #b94040 0%, #8f2727 100%);
		}

		.account-divider {
			height: 1px;
			background: #deebf7;
			margin: 0.72rem 0 0.78rem;
		}

		.account-subtitle {
			margin: 0;
			font-size: 0.88rem;
			font-weight: 800;
			color: #1b4368;
		}

		.account-help {
			margin: 0.3rem 0 0.76rem;
			font-size: 0.78rem;
			color: #5a7490;
		}

		.account-table {
			min-width: 560px;
		}

		.account-table-toolbar {
			display: flex;
			justify-content: flex-end;
			margin-bottom: 0.62rem;
		}

		.account-search-input {
			width: min(100%, 320px);
			border: 1px solid #c7dbed;
			border-radius: 10px;
			padding: 0.5rem 0.72rem;
			font-size: 0.8rem;
			color: #23405c;
			background: #ffffff;
			outline: none;
		}

		.account-search-input:focus {
			border-color: #2f7fd0;
			box-shadow: 0 0 0 3px rgba(47, 127, 208, 0.12);
		}

		.account-table th {
			font-size: 0.7rem;
			color: #5a748f;
		}

		.account-table td {
			font-size: 0.8rem;
		}

		.account-pagination-wrap {
			display: flex;
			align-items: center;
			flex-wrap: wrap;
			gap: 0.45rem;
			margin-top: 0.78rem;
		}

		.account-page-btn {
			min-width: 38px;
			height: 36px;
			padding: 0 0.72rem;
			border: 1px solid #c7dbed;
			border-radius: 10px;
			background: #ffffff;
			color: #345674;
			font-size: 0.8rem;
			font-weight: 700;
			font-family: inherit;
			line-height: 1;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			transition: all 0.2s ease;
		}

		.account-page-btn:hover {
			border-color: #2f7fd0;
			color: #1f5f9f;
		}

		.account-page-btn.active {
			background: linear-gradient(140deg, #1f77cd 0%, #125698 100%);
			border-color: #1a6dbc;
			color: #ffffff;
		}

		.account-page-ellipsis {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: 28px;
			height: 36px;
			font-size: 0.9rem;
			font-weight: 800;
			color: #6a85a2;
		}

		.account-pagination-info {
			margin-left: auto;
			font-size: 0.74rem;
			font-weight: 700;
			color: #5f7893;
		}

		.table-wrap {
			background: var(--surface);
			border: 1px solid var(--line-soft);
			border-radius: 16px;
			padding: 0.7rem;
			overflow-x: auto;
			box-shadow: 0 8px 20px rgba(7, 49, 84, 0.06);
		}

		.table-wrap.is-dragging {
			user-select: none;
		}

		@media (pointer: fine) {
			.table-wrap {
				cursor: grab;
			}

			.table-wrap.is-dragging,
			.table-wrap.is-dragging * {
				cursor: grabbing !important;
			}
		}

		.table-custom {
			margin: 0;
			--bs-table-bg: transparent;
		}

		.table-custom thead th {
			font-size: 0.74rem;
			letter-spacing: 0.07em;
			text-transform: uppercase;
			color: #60778f;
			font-weight: 700;
			border-bottom-width: 1px;
			border-color: var(--line-soft);
		}

		.table-custom tbody td {
			vertical-align: middle;
			font-size: 0.83rem;
			color: #233a52;
			border-color: #edf4fa;
			padding-top: 0.68rem;
			padding-bottom: 0.68rem;
		}

		.status-chip {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 0.28rem 0.65rem;
			border-radius: 999px;
			font-size: 0.68rem;
			font-weight: 700;
			letter-spacing: 0.05em;
			text-transform: uppercase;
		}

		.chip-hadir {
			background: #ddf4e8;
			color: #1d7b52;
		}

		.chip-terlambat {
			background: #fff2db;
			color: #b47512;
		}

		.chip-izin {
			background: #e8f4ff;
			color: #1f679b;
		}

		.footer-note {
			font-size: 0.74rem;
			color: #6f86a0;
			padding: 1.2rem 0 1.6rem;
		}

		@media (max-width: 991.98px) {
			.hero-title {
				font-size: 1.2rem;
			}

			.account-table-toolbar {
				justify-content: stretch;
			}

			.account-search-input {
				width: 100%;
			}
		}

		@media (min-width: 768px) {
			.desktop-nav {
				display: flex;
			}

			.brand-logo {
				height: 40px;
			}
		}

		@media (max-width: 767.98px) {
			.menu-toggle {
				width: 40px;
				height: 40px;
			}

			.clock-value {
				font-size: 0.95rem;
			}

			.metric-modal {
				padding: 0.55rem;
			}

			.metric-modal-card {
				width: 100%;
				max-height: 95vh;
				border-radius: 14px;
			}

			.metric-modal-head {
				padding: 0.75rem 0.82rem;
			}

			.metric-modal-body {
				padding: 0.72rem 0.78rem 0.8rem;
			}

			.metric-chart-wrap {
				height: 47vh;
				min-height: 250px;
				padding: 0.45rem;
			}

			.metric-range-note {
				width: 100%;
			}
		}
	</style>
</head>
<body>
	<div class="main-shell">
		<nav class="topbar">
			<div class="topbar-container">
				<div class="topbar-inner">
					<a href="<?php echo site_url('home'); ?>" class="brand-block">
						<img class="brand-logo" src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Absen Online">
						<span class="brand-text">Dashboard Admin Absen</span>
					</a>
					<a href="<?php echo site_url('logout'); ?>" class="logout">Logout</a>
				</div>
			</div>
		</nav>

		<header class="hero">
			<div class="container-xl">
				<div class="hero-card">
					<div class="row g-3 align-items-center">
						<div class="col-lg-7">
							<p class="status-pill <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>" id="summaryStatusPill"><?php echo htmlspecialchars($status_hari_ini, ENT_QUOTES, 'UTF-8'); ?></p>
							<h1 class="hero-title">Halo, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>. Selamat datang di Dashboard Absen Online.</h1>
							<p class="hero-subtitle">
								Pantau kehadiran harian, lakukan check in/check out, dan lihat riwayat absensi dalam satu halaman.
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
									<div class="clock-box">
										<p class="clock-label">Waktu Check In / Check Out</p>
										<p class="clock-value">
											<span id="summaryCheckInTime"><?php echo htmlspecialchars(isset($summary['jam_masuk']) ? (string) $summary['jam_masuk'] : '-', ENT_QUOTES, 'UTF-8'); ?></span>
											/
											<span id="summaryCheckOutTime"><?php echo htmlspecialchars(isset($summary['jam_pulang']) ? (string) $summary['jam_pulang'] : '-', ENT_QUOTES, 'UTF-8'); ?></span>
										</p>
									</div>
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
								<p class="mini-value"><span id="summaryTotalHadir"><?php echo htmlspecialchars((string) (isset($summary['total_hadir_bulan_ini']) ? $summary['total_hadir_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Hari</p>
								<p class="mini-hint">Klik untuk lihat grafik realtime</p>
							</article>
						</div>
						<div class="col-sm-6 col-xl-3">
							<article class="mini-card is-clickable" role="button" tabindex="0" data-metric-card="terlambat" aria-label="Lihat grafik Total Terlambat">
								<p class="mini-label">Total Terlambat</p>
								<p class="mini-value"><span id="summaryTotalTerlambat"><?php echo htmlspecialchars((string) (isset($summary['total_terlambat_bulan_ini']) ? $summary['total_terlambat_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Hari</p>
								<p class="mini-hint">Klik untuk lihat grafik realtime</p>
							</article>
						</div>
						<div class="col-sm-6 col-xl-3">
							<article class="mini-card is-clickable" role="button" tabindex="0" data-metric-card="izin_cuti" aria-label="Lihat grafik Total Izin/Cuti">
								<p class="mini-label">Total Izin/Cuti</p>
								<p class="mini-value"><span id="summaryTotalIzin"><?php echo htmlspecialchars((string) (isset($summary['total_izin_bulan_ini']) ? $summary['total_izin_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Hari</p>
								<p class="mini-hint">Klik untuk lihat grafik realtime</p>
							</article>
						</div>
						<div class="col-sm-6 col-xl-3">
							<article class="mini-card is-clickable" role="button" tabindex="0" data-metric-card="alpha" aria-label="Lihat grafik Total Alpha">
								<p class="mini-label">Total Alpha</p>
								<p class="mini-value"><span id="summaryTotalAlpha"><?php echo htmlspecialchars((string) (isset($summary['total_alpha_bulan_ini']) ? $summary['total_alpha_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Hari</p>
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
						<article class="action-card">
							<h3 class="action-title">Cek Absensi Karyawan</h3>
							<p class="action-text">Lihat rekap absensi lengkap karyawan (masuk, pulang, telat, foto, dan jarak dari titik kantor).</p>
							<a href="<?php echo site_url('home/employee_data'); ?>" class="action-btn secondary">Buka Data Absen</a>
						</article>
					</div>
				</section>

				<section id="manajemen-karyawan" class="mb-4">
					<h2 class="section-title">Manajemen Akun Karyawan</h2>

					<?php if ($account_notice_success !== ''): ?>
						<div class="account-notice success"><?php echo htmlspecialchars($account_notice_success, ENT_QUOTES, 'UTF-8'); ?></div>
					<?php endif; ?>
					<?php if ($account_notice_error !== ''): ?>
						<div class="account-notice error"><?php echo htmlspecialchars($account_notice_error, ENT_QUOTES, 'UTF-8'); ?></div>
					<?php endif; ?>
					<div class="account-grid mb-3">
						<article class="account-card">
							<h3>Sinkronisasi Spreadsheet</h3>
							<p>Tarik data terbaru dari Google Sheet ke web (akun + Data Absen).</p>
							<div class="d-flex flex-wrap gap-2">
								<form method="post" action="<?php echo site_url('home/sync_sheet_accounts_now'); ?>">
									<button type="submit" class="account-submit">Sync Akun dari Sheet</button>
								</form>
								<form method="post" action="<?php echo site_url('home/sync_sheet_attendance_now'); ?>">
									<button type="submit" class="account-submit">Sync Data Absen dari Sheet</button>
								</form>
								<form method="post" action="<?php echo site_url('home/sync_web_attendance_to_sheet_now'); ?>">
									<button type="submit" class="account-submit">Sync Data Web ke Sheet</button>
								</form>
							</div>
						</article>
					</div>

					<div class="account-grid mb-3">
						<article class="account-card">
							<h3>Buat Akun Karyawan Baru</h3>
							<p>Admin bisa menambahkan akun login karyawan langsung dari dashboard.</p>
							<form method="post" action="<?php echo site_url('home/create_employee_account'); ?>" class="account-form">
								<div>
									<p class="account-label">Username</p>
									<input type="text" name="new_username" id="newUsernameInput" class="account-input" placeholder="contoh: userbaru" autocomplete="off" autocapitalize="off" spellcheck="false" required>
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
											<option value="siang">Shift Siang - Malam (12:00 - 23:00)</option>
										</select>
									</div>
								</div>
								<div class="account-form-row two">
									<div>
										<p class="account-label">Gaji Pokok (Rp)</p>
										<input type="text" name="new_salary_monthly" class="account-input" placeholder="contoh: 2500000" required>
									</div>
									<div>
										<p class="account-label">Hari Masuk / Bulan</p>
										<input type="number" min="1" max="31" name="new_work_days" class="account-input" value="28">
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
								<button type="submit" class="account-submit">Simpan Akun Baru</button>
							</form>
						</article>

						<article class="account-card">
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
								<button type="submit" class="account-submit delete">Hapus Akun</button>
							</form>

							<div class="account-divider"></div>

							<h4 class="account-subtitle">Edit Akun Karyawan</h4>
							<p class="account-help">Ubah data akun karyawan terpilih. Password boleh dikosongkan jika tidak ingin diubah.</p>
							<form method="post" action="<?php echo site_url('home/update_employee_account'); ?>" class="account-form" id="editEmployeeForm">
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
											<option value="siang">Shift Siang - Malam (12:00 - 23:00)</option>
										</select>
									</div>
								</div>
								<div>
									<p class="account-label">Gaji Pokok (Rp)</p>
									<input type="text" name="edit_salary_monthly" id="editSalaryMonthlyInput" class="account-input" placeholder="contoh: 2500000" required>
								</div>
								<div class="account-form-row two">
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
										<p class="account-label">Hari Masuk / Bulan</p>
										<input type="number" min="1" max="31" name="edit_work_days" id="editWorkDaysInput" class="account-input" value="28">
									</div>
								</div>
								<div>
									<p class="account-label">Alamat</p>
									<input type="text" name="edit_address" id="editAddressInput" class="account-input" placeholder="Kp. Kesekian Kalinya, Pandenglang, Banten">
								</div>
								<button type="submit" class="account-submit">Simpan Perubahan Akun</button>
							</form>
						</article>
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
						</div>
					</div>
				</div>

				<p class="footer-note mb-0">Absen Online PNS - monitoring kehadiran lebih cepat dan rapi.</p>
			</div>
		</main>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.2/dist/lightweight-charts.standalone.production.js"></script>
	<script>
		(function () {
			var dateEl = document.getElementById('currentDate');
			var timeEl = document.getElementById('currentTime');
			if (!dateEl || !timeEl) {
				return;
			}

			var formatterDate = new Intl.DateTimeFormat('id-ID', {
				weekday: 'long',
				day: '2-digit',
				month: 'long',
				year: 'numeric'
			});

			var formatterTime = new Intl.DateTimeFormat('id-ID', {
				hour: '2-digit',
				minute: '2-digit',
				second: '2-digit',
				hour12: false
			});

			var updateClock = function () {
				var now = new Date();
				dateEl.textContent = formatterDate.format(now);
				timeEl.textContent = formatterTime.format(now) + ' WIB';
			};

			updateClock();
			window.setInterval(updateClock, 1000);
		})();

		(function () {
			var body = document.body;
			var openMobileNav = document.getElementById('openMobileNav');
			var closeMobileNav = document.getElementById('closeMobileNav');
			var mobileNav = document.getElementById('mobileNav');
			var mobileNavBackdrop = document.getElementById('mobileNavBackdrop');
			var helpMenu = document.getElementById('helpMenu');
			var helpToggle = document.getElementById('helpToggle');
			var mobileHelpToggle = document.getElementById('mobileHelpToggle');
			var mobileHelpList = document.getElementById('mobileHelpList');

			var setMobileNavState = function (isOpen) {
				if (!body || !mobileNav || !openMobileNav) {
					return;
				}

				if (isOpen) {
					body.classList.add('nav-open');
					mobileNav.setAttribute('aria-hidden', 'false');
					openMobileNav.setAttribute('aria-expanded', 'true');
					return;
				}

				body.classList.remove('nav-open');
				mobileNav.setAttribute('aria-hidden', 'true');
				openMobileNav.setAttribute('aria-expanded', 'false');
			};

			if (openMobileNav && closeMobileNav && mobileNav && mobileNavBackdrop) {
				openMobileNav.addEventListener('click', function () {
					setMobileNavState(true);
				});

				closeMobileNav.addEventListener('click', function () {
					setMobileNavState(false);
				});

				mobileNavBackdrop.addEventListener('click', function () {
					setMobileNavState(false);
				});

				var mobileCloseLinks = mobileNav.querySelectorAll('a.mobile-link, .mobile-help-list a, a.mobile-member-area, a.mobile-logout');
				for (var i = 0; i < mobileCloseLinks.length; i += 1) {
					mobileCloseLinks[i].addEventListener('click', function () {
						setMobileNavState(false);
					});
				}

				window.addEventListener('resize', function () {
					if (window.innerWidth >= 992) {
						setMobileNavState(false);
					}
				});
			}

			if (helpMenu && helpToggle) {
				helpToggle.addEventListener('click', function (event) {
					event.preventDefault();
					var isOpen = helpMenu.classList.toggle('open');
					helpToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
				});

				document.addEventListener('click', function (event) {
					if (!helpMenu.contains(event.target)) {
						helpMenu.classList.remove('open');
						helpToggle.setAttribute('aria-expanded', 'false');
					}
				});
			}

			if (mobileHelpToggle && mobileHelpList) {
				mobileHelpToggle.addEventListener('click', function () {
					var isOpen = mobileHelpList.classList.toggle('is-open');
					var indicator = mobileHelpToggle.querySelector('span');
					mobileHelpToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
					if (indicator) {
						indicator.textContent = isOpen ? '-' : '+';
					}
				});
			}

			document.addEventListener('keydown', function (event) {
				if (event.key !== 'Escape') {
					return;
				}

				setMobileNavState(false);
				if (helpMenu && helpToggle) {
					helpMenu.classList.remove('open');
					helpToggle.setAttribute('aria-expanded', 'false');
				}
			});
		})();

		(function () {
			var deleteForm = document.getElementById('deleteEmployeeForm');
			var deleteUserInput = document.getElementById('deleteUsernameInput');
			var editForm = document.getElementById('editEmployeeForm');
			var newUsernameInput = document.getElementById('newUsernameInput');
			var editUserInput = document.getElementById('editUsernameInput');
			var editNewUsernameInput = document.getElementById('editNewUsernameInput');
			var editPasswordInput = document.getElementById('editPasswordInput');
			var editPhoneInput = document.getElementById('editPhoneInput');
			var editBranchInput = document.getElementById('editBranchInput');
			var editShiftInput = document.getElementById('editShiftInput');
			var editSalaryMonthlyInput = document.getElementById('editSalaryMonthlyInput');
			var editJobTitleInput = document.getElementById('editJobTitleInput');
			var editWorkDaysInput = document.getElementById('editWorkDaysInput');
			var editAddressInput = document.getElementById('editAddressInput');
			var accountRows = <?php echo $employee_accounts_json; ?>;
			var defaultJobTitle = <?php echo json_encode($default_job_title, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
			var defaultBranch = <?php echo json_encode($default_branch, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

			var accountMap = {};
			var accountLookupRows = [];
			if (Array.isArray(accountRows)) {
				for (var i = 0; i < accountRows.length; i += 1) {
					var row = accountRows[i] || {};
					var usernameValue = String(row.username || '').trim();
					var usernameKey = usernameValue.toLowerCase();
					if (usernameKey !== '') {
						accountMap[usernameKey] = row;
						accountLookupRows.push(row);
					}
				}
			}

			var normalizeLookup = function (value) {
				return String(value || '').trim().toLowerCase();
			};

			var resolveAccountByQuery = function (queryValue, allowContains) {
				var query = normalizeLookup(queryValue);
				if (query === '') {
					return null;
				}

				if (accountMap[query]) {
					return accountMap[query];
				}

				var containsMatches = [];
				for (var i = 0; i < accountLookupRows.length; i += 1) {
					var row = accountLookupRows[i] || {};
					var usernameKey = normalizeLookup(row.username || '');
					var employeeIdKey = normalizeLookup(row.employee_id || '');
					var labelKey = employeeIdKey !== '' && employeeIdKey !== '-'
						? employeeIdKey + ' - ' + usernameKey
						: usernameKey;
					if (query === employeeIdKey || query === labelKey) {
						return row;
					}
					if (!allowContains) {
						continue;
					}
					if (usernameKey.indexOf(query) !== -1 || (employeeIdKey !== '' && employeeIdKey !== '-' && employeeIdKey.indexOf(query) !== -1) || labelKey.indexOf(query) !== -1) {
						containsMatches.push(row);
					}
				}

				if (allowContains && containsMatches.length === 1) {
					return containsMatches[0];
				}

				return null;
			};

			if (deleteForm && deleteUserInput) {
				deleteForm.addEventListener('submit', function (event) {
					var query = String(deleteUserInput.value || '').trim();
					if (query === '') {
						return;
					}
					var resolvedRow = resolveAccountByQuery(query, true);
					if (!resolvedRow) {
						event.preventDefault();
						window.alert('Akun karyawan tidak ditemukan. Ketik username atau ID yang valid.');
						deleteUserInput.focus();
						return;
					}

					var username = String(resolvedRow.username || '').trim();
					if (username === '') {
						event.preventDefault();
						window.alert('Akun karyawan tidak ditemukan. Coba pilih ulang dari hasil pencarian.');
						deleteUserInput.focus();
						return;
					}
					deleteUserInput.value = username;

					var confirmed = window.confirm('Hapus akun "' + username + '"? Data absensi, cuti/izin, pinjaman, dan lembur akun ini juga akan dihapus.');
					if (!confirmed) {
						event.preventDefault();
					}
				});
			}

			if (!editForm || !editUserInput || !editNewUsernameInput || !editPasswordInput || !editPhoneInput || !editBranchInput || !editShiftInput || !editSalaryMonthlyInput || !editJobTitleInput || !editWorkDaysInput || !editAddressInput) {
				return;
			}

			var toPositiveInt = function (value, fallbackValue) {
				var parsed = parseInt(value, 10);
				if (!isFinite(parsed) || parsed <= 0) {
					return fallbackValue;
				}
				return parsed;
			};

			var normalizeUsername = function (value) {
				var text = String(value || '').toLowerCase();
				text = text.replace(/[^a-z0-9_]+/g, '_');
				text = text.replace(/_+/g, '_').replace(/^_+|_+$/g, '');
				if (text.length > 30) {
					text = text.substring(0, 30).replace(/_+$/g, '');
				}
				return text;
			};

			if (newUsernameInput) {
				newUsernameInput.addEventListener('input', function () {
					newUsernameInput.value = normalizeUsername(newUsernameInput.value);
				});
			}
			if (editNewUsernameInput) {
				editNewUsernameInput.addEventListener('input', function () {
					editNewUsernameInput.value = normalizeUsername(editNewUsernameInput.value);
				});
			}

			var fillEditForm = function (usernameValue) {
				var usernameKey = normalizeLookup(usernameValue);
				var row = usernameKey !== '' && accountMap[usernameKey] ? accountMap[usernameKey] : null;
				if (!row) {
					editNewUsernameInput.value = '';
					editPasswordInput.value = '';
					editPhoneInput.value = '';
					editBranchInput.value = defaultBranch;
					editShiftInput.value = 'pagi';
					editSalaryMonthlyInput.value = '';
					editJobTitleInput.value = defaultJobTitle;
					editWorkDaysInput.value = '28';
					editAddressInput.value = '';
					return;
				}

				editNewUsernameInput.value = String(row.username || '');
				editPasswordInput.value = '';
				editPhoneInput.value = String(row.phone || '');
				editBranchInput.value = String(row.branch || defaultBranch || '');
				editShiftInput.value = String(row.shift_key || 'pagi').toLowerCase() === 'siang' ? 'siang' : 'pagi';
				editSalaryMonthlyInput.value = String(row.salary_monthly || '');
				editJobTitleInput.value = String(row.job_title || '');
				if (String(editJobTitleInput.value || '').trim() === '') {
					editJobTitleInput.value = defaultJobTitle;
				}
				editWorkDaysInput.value = String(toPositiveInt(row.work_days, 28));
				editAddressInput.value = String(row.address || '');
			};

			var syncEditSelection = function (allowContains, canonicalizeInput, refreshForm) {
				var shouldRefreshForm = refreshForm !== false;
				var query = String(editUserInput.value || '').trim();
				if (query === '') {
					if (shouldRefreshForm) {
						fillEditForm('');
					}
					if (typeof editUserInput.setCustomValidity === 'function') {
						editUserInput.setCustomValidity('');
					}
					return false;
				}

				var resolvedRow = resolveAccountByQuery(query, allowContains);
				if (!resolvedRow || String(resolvedRow.username || '').trim() === '') {
					if (shouldRefreshForm) {
						fillEditForm('');
					}
					if (typeof editUserInput.setCustomValidity === 'function') {
						editUserInput.setCustomValidity('Akun karyawan tidak ditemukan.');
					}
					return false;
				}

				var resolvedUsername = String(resolvedRow.username || '').trim();
				if (canonicalizeInput) {
					editUserInput.value = resolvedUsername;
				}
				if (typeof editUserInput.setCustomValidity === 'function') {
					editUserInput.setCustomValidity('');
				}
				if (shouldRefreshForm) {
					fillEditForm(resolvedUsername);
				}
				return true;
			};

			editUserInput.addEventListener('input', function () {
				if (String(editUserInput.value || '').trim() === '') {
					fillEditForm('');
					if (typeof editUserInput.setCustomValidity === 'function') {
						editUserInput.setCustomValidity('');
					}
					return;
				}
				syncEditSelection(false, false);
			});

			editUserInput.addEventListener('change', function () {
				syncEditSelection(true, true);
			});

			editForm.addEventListener('submit', function (event) {
				var validSelection = syncEditSelection(true, true, false);
				if (validSelection) {
					return;
				}
				event.preventDefault();
				window.alert('Akun karyawan tidak ditemukan. Ketik username atau ID yang valid.');
				editUserInput.focus();
			});

			if (String(editUserInput.value || '').trim() !== '') {
				syncEditSelection(true, true);
			}
		})();

		(function () {
			var tableBody = document.getElementById('employeeAccountTableBody');
			var paginationWrap = document.getElementById('employeeAccountPaginationWrap');
			var searchInput = document.getElementById('employeeAccountSearchInput');
			if (!tableBody || !paginationWrap) {
				return;
			}

			var rows = Array.prototype.slice.call(tableBody.querySelectorAll('tr.employee-account-row'));
			if (!rows.length) {
				paginationWrap.style.display = 'none';
				return;
			}
			for (var r = 0; r < rows.length; r += 1) {
				var rowCells = rows[r].cells || [];
				var idText = rowCells.length > 0 ? String(rowCells[0].textContent || '') : '';
				var nameText = rowCells.length > 1 ? String(rowCells[1].textContent || '') : '';
				rows[r].setAttribute('data-search-key', (idText + ' ' + nameText).toLowerCase());
			}

			var perPage = 10;
			var filteredRows = rows.slice();
			var totalRows = filteredRows.length;
			var totalPages = Math.ceil(totalRows / perPage);
			var currentPage = 1;
			var emptySearchRow = null;

			var removeEmptySearchRow = function () {
				if (emptySearchRow && emptySearchRow.parentNode) {
					emptySearchRow.parentNode.removeChild(emptySearchRow);
				}
				emptySearchRow = null;
			};

			var showEmptySearchRow = function (keywordText) {
				if (!emptySearchRow) {
					emptySearchRow = document.createElement('tr');
					emptySearchRow.className = 'employee-account-empty';
					var emptyCell = document.createElement('td');
					emptyCell.colSpan = 7;
					emptyCell.className = 'text-center py-4 text-secondary';
					emptySearchRow.appendChild(emptyCell);
				}
				var infoText = 'Tidak ada karyawan yang cocok.';
				if (String(keywordText || '').trim() !== '') {
					infoText = 'Tidak ada karyawan untuk pencarian "' + String(keywordText).trim() + '".';
				}
				emptySearchRow.children[0].textContent = infoText;
				tableBody.appendChild(emptySearchRow);
			};

			var buildPageTokens = function (pageValue, pagesTotal) {
				if (pagesTotal <= 5) {
					var allPages = [];
					for (var p = 1; p <= pagesTotal; p += 1) {
						allPages.push(p);
					}
					return allPages;
				}

				if (pageValue <= 2) {
					return [1, 2, 3, '...', pagesTotal];
				}

				if (pageValue >= pagesTotal - 1) {
					return [1, '...', pagesTotal - 2, pagesTotal - 1, pagesTotal];
				}

				return [1, '...', pageValue - 1, pageValue, pageValue + 1, '...', pagesTotal];
			};

			var renderRows = function () {
				removeEmptySearchRow();
				for (var i = 0; i < rows.length; i += 1) {
					rows[i].style.display = 'none';
				}
				if (!filteredRows.length) {
					showEmptySearchRow(searchInput ? String(searchInput.value || '') : '');
					return;
				}

				var start = (currentPage - 1) * perPage;
				var end = start + perPage;
				for (var j = 0; j < filteredRows.length; j += 1) {
					filteredRows[j].style.display = j >= start && j < end ? '' : 'none';
				}
			};

			var createPageButton = function (label, pageValue, isActive) {
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'account-page-btn' + (isActive ? ' active' : '');
				btn.textContent = String(label);
				btn.setAttribute('aria-label', 'Halaman ' + String(label));
				btn.addEventListener('click', function () {
					if (pageValue === currentPage) {
						return;
					}
					currentPage = pageValue;
					renderRows();
					renderPagination();
				});
				return btn;
			};

			var renderPagination = function () {
				paginationWrap.innerHTML = '';
				if (totalRows <= 0) {
					paginationWrap.style.display = 'none';
					return;
				}
				if (totalPages <= 1) {
					paginationWrap.style.display = 'flex';
					var singleInfo = document.createElement('span');
					singleInfo.className = 'account-pagination-info';
					singleInfo.textContent = 'Menampilkan 1 - ' + totalRows + ' dari ' + totalRows + ' karyawan';
					paginationWrap.appendChild(singleInfo);
					return;
				}
				paginationWrap.style.display = 'flex';

				if (currentPage > 1) {
					var prevBtn = document.createElement('button');
					prevBtn.type = 'button';
					prevBtn.className = 'account-page-btn';
					prevBtn.textContent = 'Sebelumnya';
					prevBtn.setAttribute('aria-label', 'Halaman sebelumnya');
					prevBtn.addEventListener('click', function () {
						if (currentPage <= 1) {
							return;
						}
						currentPage -= 1;
						renderRows();
						renderPagination();
					});
					paginationWrap.appendChild(prevBtn);
				}

				var tokens = buildPageTokens(currentPage, totalPages);
				for (var i = 0; i < tokens.length; i += 1) {
					if (tokens[i] === '...') {
						var dots = document.createElement('span');
						dots.className = 'account-page-ellipsis';
						dots.textContent = '...';
						paginationWrap.appendChild(dots);
						continue;
					}
					paginationWrap.appendChild(createPageButton(tokens[i], tokens[i], tokens[i] === currentPage));
				}

				if (currentPage < totalPages) {
					var nextBtn = document.createElement('button');
					nextBtn.type = 'button';
					nextBtn.className = 'account-page-btn';
					nextBtn.textContent = 'Selanjutnya';
					nextBtn.setAttribute('aria-label', 'Halaman selanjutnya');
					nextBtn.addEventListener('click', function () {
						if (currentPage >= totalPages) {
							return;
						}
						currentPage += 1;
						renderRows();
						renderPagination();
					});
					paginationWrap.appendChild(nextBtn);
				}

				var startRow = ((currentPage - 1) * perPage) + 1;
				var endRow = Math.min(currentPage * perPage, totalRows);
				var info = document.createElement('span');
				info.className = 'account-pagination-info';
				info.textContent = 'Menampilkan ' + startRow + ' - ' + endRow + ' dari ' + totalRows + ' karyawan';
				paginationWrap.appendChild(info);
			};

			var applySearch = function () {
				var keyword = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
				filteredRows = [];
				for (var i = 0; i < rows.length; i += 1) {
					var searchKey = String(rows[i].getAttribute('data-search-key') || '').toLowerCase();
					if (keyword === '' || searchKey.indexOf(keyword) !== -1) {
						filteredRows.push(rows[i]);
					}
				}

				totalRows = filteredRows.length;
				totalPages = Math.ceil(totalRows / perPage);
				currentPage = 1;
				renderRows();
				renderPagination();
			};

			if (searchInput) {
				searchInput.addEventListener('input', applySearch);
			}

			applySearch();
		})();

		(function () {
			var wraps = document.querySelectorAll('.table-wrap');
			if (!wraps.length) {
				return;
			}

			var dragThreshold = 6;
			var clickBlockMs = 220;
			var noDragSelector = 'a, button, input, select, textarea, label, [role="button"]';

			for (var i = 0; i < wraps.length; i += 1) {
				(function (wrap) {
					var pointerDown = false;
					var dragging = false;
					var startX = 0;
					var startScrollLeft = 0;
					var lastDragEndedAt = 0;

					var onMouseMove = function (event) {
						if (!pointerDown) {
							return;
						}

						var deltaX = event.clientX - startX;
						if (!dragging && Math.abs(deltaX) >= dragThreshold) {
							dragging = true;
						}

						if (!dragging) {
							return;
						}

						wrap.scrollLeft = startScrollLeft - deltaX;
						event.preventDefault();
					};

					var stopDrag = function () {
						if (!pointerDown) {
							return;
						}

						pointerDown = false;
						window.removeEventListener('mousemove', onMouseMove);
						window.removeEventListener('mouseup', stopDrag);
						wrap.classList.remove('is-dragging');

						if (dragging) {
							lastDragEndedAt = Date.now();
						}
						dragging = false;
					};

					wrap.addEventListener('mousedown', function (event) {
						if (event.button !== 0) {
							return;
						}

						if (wrap.scrollWidth <= wrap.clientWidth) {
							return;
						}

						if (event.target.closest(noDragSelector)) {
							return;
						}

						pointerDown = true;
						dragging = false;
						startX = event.clientX;
						startScrollLeft = wrap.scrollLeft;
						wrap.classList.add('is-dragging');
						window.addEventListener('mousemove', onMouseMove);
						window.addEventListener('mouseup', stopDrag);
						event.preventDefault();
					});

					wrap.addEventListener('mouseleave', stopDrag);

					wrap.addEventListener('click', function (event) {
						if (Date.now() - lastDragEndedAt > clickBlockMs) {
							return;
						}

						if (!event.target.closest(noDragSelector)) {
							return;
						}

						event.preventDefault();
						event.stopPropagation();
					}, true);
				})(wraps[i]);
			}
		})();

		(function () {
			var summaryUrl = <?php echo json_encode(site_url('home/admin_dashboard_live_summary')); ?>;
			var statusPill = document.getElementById('summaryStatusPill');
			var checkInEl = document.getElementById('summaryCheckInTime');
			var checkOutEl = document.getElementById('summaryCheckOutTime');
			var hadirEl = document.getElementById('summaryTotalHadir');
			var terlambatEl = document.getElementById('summaryTotalTerlambat');
			var izinEl = document.getElementById('summaryTotalIzin');
			var alphaEl = document.getElementById('summaryTotalAlpha');

			if (!summaryUrl || !hadirEl || !terlambatEl || !izinEl || !alphaEl) {
				return;
			}

			var setStatusClass = function (statusText) {
				if (!statusPill) {
					return;
				}
				var text = String(statusText || '').toLowerCase();
				statusPill.classList.remove('status-default', 'status-success', 'status-warning', 'status-info');
				if (text.indexOf('hadir') !== -1 || text.indexOf('check in') !== -1) {
					statusPill.classList.add('status-success');
					return;
				}
				if (text.indexOf('terlambat') !== -1) {
					statusPill.classList.add('status-warning');
					return;
				}
				if (text.indexOf('izin') !== -1 || text.indexOf('cuti') !== -1) {
					statusPill.classList.add('status-info');
					return;
				}
				statusPill.classList.add('status-default');
			};

			var toInt = function (value) {
				var parsed = Number(value);
				if (!isFinite(parsed)) {
					return 0;
				}
				return Math.max(0, Math.floor(parsed));
			};

			var updateSummary = function (summary) {
				if (!summary || typeof summary !== 'object') {
					return;
				}

				if (statusPill) {
					var statusValue = String(summary.status_hari_ini || 'Monitoring Hari Ini');
					statusPill.textContent = statusValue;
					setStatusClass(statusValue);
				}
				if (checkInEl) {
					checkInEl.textContent = String(summary.jam_masuk || '-');
				}
				if (checkOutEl) {
					checkOutEl.textContent = String(summary.jam_pulang || '-');
				}
				hadirEl.textContent = String(toInt(summary.total_hadir_bulan_ini));
				terlambatEl.textContent = String(toInt(summary.total_terlambat_bulan_ini));
				izinEl.textContent = String(toInt(summary.total_izin_bulan_ini));
				alphaEl.textContent = String(toInt(summary.total_alpha_bulan_ini));
			};

			var pullSummary = function () {
				fetch(summaryUrl + '?_=' + Date.now(), {
					credentials: 'same-origin',
					headers: {
						'X-Requested-With': 'XMLHttpRequest'
					}
				})
					.then(function (res) {
						if (!res.ok) {
							throw new Error('HTTP ' + res.status);
						}
						return res.json();
					})
					.then(function (payload) {
						if (!payload || payload.success !== true) {
							return;
						}
						updateSummary(payload.summary || {});
					})
					.catch(function () {
						// Silent fail to avoid UI spam.
					});
			};

			pullSummary();
			window.setInterval(pullSummary, 12000);
		})();

		(function () {
			var metricCards = document.querySelectorAll('[data-metric-card]');
			var modal = document.getElementById('metricModal');
			var modalClose = document.getElementById('metricModalClose');
			var modalTitle = document.getElementById('metricModalTitle');
			var modalSubtitle = document.getElementById('metricModalSubtitle');
			var legendEl = document.getElementById('metricChartLegend');
			var chartHost = document.getElementById('metricChartCanvas');
			var rangeButtons = document.querySelectorAll('.metric-range-btn[data-metric-range]');
			var chartEndpoint = <?php echo json_encode(site_url('home/admin_metric_chart_data')); ?>;

			if (!modal || !chartHost || !metricCards.length) {
				return;
			}

			var metricNameMap = {
				hadir: 'Total Hadir',
				terlambat: 'Total Terlambat',
				izin_cuti: 'Total Izin/Cuti',
				alpha: 'Total Alpha'
			};

			var rangeNameMap = {
				'1H': '1 Hari',
				'1M': '1 Minggu',
				'1B': '1 Bulan',
				'1T': '1 Tahun',
				'ALL': 'Semuanya'
			};

			var chartApi = null;
			var candleSeries = null;
			var volumeSeries = null;
			var activeMetric = 'hadir';
			var activeRange = '1B';
			var pollTimer = null;
			var resizeTimer = null;
			var lastBars = [];
			var lastPoints = [];
			var timeToLabel = {};
			var attributionObserver = null;

			var formatNumber = function (value) {
				var numeric = Number(value);
				if (!isFinite(numeric)) {
					numeric = 0;
				}
				return new Intl.NumberFormat('id-ID').format(Math.round(numeric));
			};

			var setRangeActive = function (rangeValue) {
				activeRange = String(rangeValue || '1B').toUpperCase();
				for (var i = 0; i < rangeButtons.length; i += 1) {
					var btn = rangeButtons[i];
					btn.classList.toggle('active', String(btn.getAttribute('data-metric-range') || '').toUpperCase() === activeRange);
				}
			};

			var setLegendLoading = function (text) {
				if (!legendEl) {
					return;
				}
				legendEl.innerHTML = '<span class="label">' + String(text || 'Memuat data grafik...') + '</span>';
			};

			var buildLegendHtml = function (bar, prevClose, label) {
				var open = Number(bar && bar.open ? bar.open : 0);
				var high = Number(bar && bar.high ? bar.high : 0);
				var low = Number(bar && bar.low ? bar.low : 0);
				var close = Number(bar && bar.close ? bar.close : 0);
				var base = isFinite(prevClose) ? Number(prevClose) : open;
				var change = close - base;
				var pct = base > 0 ? (change / base) * 100 : 0;
				var trendClass = change > 0 ? 'value-up' : (change < 0 ? 'value-down' : '');
				var sign = change > 0 ? '+' : '';
				var formattedChange = sign + formatNumber(change);
				var formattedPct = sign + pct.toFixed(2).replace('.', ',') + '%';

				return ''
					+ '<span class="label">' + (label || '-') + '</span>'
					+ '<span>O ' + formatNumber(open) + '</span>'
					+ '<span>H ' + formatNumber(high) + '</span>'
					+ '<span>L ' + formatNumber(low) + '</span>'
					+ '<span>C ' + formatNumber(close) + '</span>'
					+ '<span class="' + trendClass + '">' + formattedChange + ' (' + formattedPct + ')</span>';
			};

			var toUnixTime = function (isoString) {
				var dateObj = new Date(String(isoString || ''));
				if (!isFinite(dateObj.getTime())) {
					return null;
				}
				return Math.floor(dateObj.getTime() / 1000);
			};

			var transformPointsToCandles = function (points) {
				var candles = [];
				var volumes = [];
				timeToLabel = {};
				for (var i = 0; i < points.length; i += 1) {
					var point = points[i] || {};
					var currentValue = Number(point.value || 0);
					if (!isFinite(currentValue)) {
						currentValue = 0;
					}
					var prevValue = i > 0 ? Number(points[i - 1].value || 0) : currentValue;
					if (!isFinite(prevValue)) {
						prevValue = currentValue;
					}
					var openValue = prevValue;
					var closeValue = currentValue;
					var highValue = Math.max(openValue, closeValue);
					var lowValue = Math.min(openValue, closeValue);
					if (highValue === lowValue) {
						highValue += 0.15;
						lowValue = Math.max(0, lowValue - 0.15);
					}
					var unixTime = toUnixTime(point.ts);
					if (unixTime === null) {
						continue;
					}
					candles.push({
						time: unixTime,
						open: openValue,
						high: highValue,
						low: lowValue,
						close: closeValue
					});
					volumes.push({
						time: unixTime,
						value: Math.max(1, Math.round(Math.abs(closeValue - openValue) * 10)),
						color: closeValue >= openValue ? 'rgba(18, 160, 102, 0.32)' : 'rgba(225, 66, 82, 0.32)'
					});
					timeToLabel[String(unixTime)] = String(point.label || '');
				}
				return {
					candles: candles,
					volumes: volumes
				};
			};

			var stripTradingViewMarks = function () {
				if (!chartHost) {
					return;
				}
				var selectors = [
					'a[href*="tradingview"]',
					'a[href*="tradingview.com"]',
					'.tv-lightweight-charts-attribution-logo',
					'[class*="attribution"]',
					'[id*="tradingview"]'
				];
				var nodes = chartHost.querySelectorAll(selectors.join(','));
				for (var i = 0; i < nodes.length; i += 1) {
					nodes[i].style.display = 'none';
					nodes[i].style.opacity = '0';
					nodes[i].style.visibility = 'hidden';
					nodes[i].style.pointerEvents = 'none';
				}
			};

			var initAttributionObserver = function () {
				if (!chartHost || typeof MutationObserver === 'undefined') {
					return;
				}
				if (attributionObserver) {
					return;
				}
				attributionObserver = new MutationObserver(function () {
					stripTradingViewMarks();
				});
				attributionObserver.observe(chartHost, {
					childList: true,
					subtree: true
				});
			};

			var ensureChart = function () {
				if (chartApi) {
					return true;
				}
				if (!window.LightweightCharts || typeof window.LightweightCharts.createChart !== 'function') {
					setLegendLoading('Library chart gagal dimuat. Coba refresh halaman.');
					return false;
				}

				chartApi = window.LightweightCharts.createChart(chartHost, {
					width: chartHost.clientWidth || 960,
					height: chartHost.clientHeight || 420,
					layout: {
						background: { color: '#f9fcff' },
						textColor: '#4a627b',
						fontFamily: "'Plus Jakarta Sans', sans-serif",
						attributionLogo: false
					},
					grid: {
						vertLines: { color: '#e8f1fa' },
						horzLines: { color: '#e8f1fa' }
					},
					crosshair: {
						mode: window.LightweightCharts.CrosshairMode.Normal,
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
					rightPriceScale: {
						borderColor: '#c6d8ea'
					},
					timeScale: {
						borderColor: '#c6d8ea',
						timeVisible: true,
						secondsVisible: false,
						rightOffset: 2
					},
					handleScroll: {
						mouseWheel: true,
						pressedMouseMove: true,
						horzTouchDrag: true,
						vertTouchDrag: true
					},
					handleScale: {
						mouseWheel: true,
						pinch: true,
						axisPressedMouseMove: true
					}
				});

				initAttributionObserver();
				stripTradingViewMarks();

				candleSeries = chartApi.addCandlestickSeries({
					upColor: '#11a069',
					downColor: '#e03b50',
					borderVisible: false,
					wickUpColor: '#11a069',
					wickDownColor: '#e03b50',
					priceLineVisible: true,
					lastValueVisible: true
				});

				volumeSeries = chartApi.addHistogramSeries({
					color: 'rgba(50, 112, 172, 0.28)',
					priceFormat: {
						type: 'volume'
					},
					priceScaleId: ''
				});
				volumeSeries.priceScale().applyOptions({
					scaleMargins: {
						top: 0.78,
						bottom: 0
					}
				});

				chartApi.subscribeCrosshairMove(function (param) {
					if (!legendEl || !lastBars.length) {
						return;
					}

					var priceMap = param && param.seriesData ? param.seriesData : null;
					var candle = null;
					if (priceMap && priceMap.get) {
						candle = priceMap.get(candleSeries) || null;
					}

					if (!candle) {
						candle = lastBars[lastBars.length - 1];
					}
					var candleTime = candle && candle.time ? String(candle.time) : '';
					var label = candleTime && timeToLabel[candleTime] ? timeToLabel[candleTime] : (lastPoints.length ? String(lastPoints[lastPoints.length - 1].label || '-') : '-');

					var idx = -1;
					for (var i = 0; i < lastBars.length; i += 1) {
						if (String(lastBars[i].time) === candleTime) {
							idx = i;
							break;
						}
					}
					var prevClose = idx > 0 ? Number(lastBars[idx - 1].close || 0) : Number(candle.open || 0);
					legendEl.innerHTML = buildLegendHtml(candle, prevClose, label);
				});

				window.addEventListener('resize', function () {
					if (!chartApi || !chartHost) {
						return;
					}
					window.clearTimeout(resizeTimer);
					resizeTimer = window.setTimeout(function () {
						chartApi.applyOptions({
							width: chartHost.clientWidth || 960,
							height: chartHost.clientHeight || 420
						});
					}, 80);
				});

				return true;
			};

			var refreshSubtitle = function (payload) {
				if (!modalSubtitle) {
					return;
				}
				var rangeLabel = payload && payload.range_label ? String(payload.range_label) : (rangeNameMap[activeRange] || activeRange);
				var lastValue = payload && typeof payload.last_value !== 'undefined' ? Number(payload.last_value || 0) : 0;
				var changeValue = payload && typeof payload.change_value !== 'undefined' ? Number(payload.change_value || 0) : 0;
				var trendText = changeValue > 0 ? 'Naik' : (changeValue < 0 ? 'Turun' : 'Stabil');
				modalSubtitle.textContent = rangeLabel + ' | Nilai sekarang: ' + formatNumber(lastValue) + ' | ' + trendText;
				modalSubtitle.style.color = changeValue > 0 ? '#84ffc8' : (changeValue < 0 ? '#ffc2cb' : '#e2eefb');
			};

			var fetchChartData = function () {
				if (!ensureChart()) {
					return;
				}

				if (modalTitle) {
					modalTitle.textContent = 'Grafik ' + (metricNameMap[activeMetric] || 'Ringkasan');
				}
				setLegendLoading('Memuat grafik realtime...');

				var url = chartEndpoint + '?metric=' + encodeURIComponent(activeMetric) + '&range=' + encodeURIComponent(activeRange) + '&_=' + Date.now();
				fetch(url, {
					credentials: 'same-origin',
					headers: {
						'X-Requested-With': 'XMLHttpRequest'
					}
				})
					.then(function (res) {
						if (!res.ok) {
							throw new Error('HTTP ' + res.status);
						}
						return res.json();
					})
					.then(function (payload) {
						if (!payload || payload.success !== true || !Array.isArray(payload.points)) {
							throw new Error(payload && payload.message ? payload.message : 'Data grafik kosong.');
						}

						var transformed = transformPointsToCandles(payload.points);
						lastBars = transformed.candles;
						lastPoints = payload.points.slice();
						candleSeries.setData(transformed.candles);
						volumeSeries.setData(transformed.volumes);
						chartApi.timeScale().fitContent();
						refreshSubtitle(payload);

						if (!transformed.candles.length) {
							setLegendLoading('Belum ada data untuk rentang ini.');
							return;
						}

						var lastIndex = transformed.candles.length - 1;
						var lastBar = transformed.candles[lastIndex];
						var prevClose = lastIndex > 0 ? Number(transformed.candles[lastIndex - 1].close || 0) : Number(lastBar.open || 0);
						var lastLabel = payload.points[lastIndex] && payload.points[lastIndex].label ? String(payload.points[lastIndex].label) : '-';
						legendEl.innerHTML = buildLegendHtml(lastBar, prevClose, lastLabel);
					})
					.catch(function (error) {
						setLegendLoading('Gagal memuat grafik: ' + String(error && error.message ? error.message : 'Unknown error'));
					});
			};

			var stopPolling = function () {
				if (pollTimer !== null) {
					window.clearInterval(pollTimer);
					pollTimer = null;
				}
			};

			var startPolling = function () {
				stopPolling();
				pollTimer = window.setInterval(function () {
					if (!modal.classList.contains('show')) {
						stopPolling();
						return;
					}
					fetchChartData();
				}, 10000);
			};

			var openModalForMetric = function (metric) {
				var normalized = String(metric || '').toLowerCase();
				if (normalized === 'izin') {
					normalized = 'izin_cuti';
				}
				if (!metricNameMap[normalized]) {
					normalized = 'hadir';
				}
				activeMetric = normalized;
				setRangeActive(activeRange);

				modal.classList.add('show');
				modal.setAttribute('aria-hidden', 'false');
				document.body.style.overflow = 'hidden';

				fetchChartData();
				startPolling();
			};

			var closeModal = function () {
				modal.classList.remove('show');
				modal.setAttribute('aria-hidden', 'true');
				document.body.style.overflow = '';
				stopPolling();
			};

			for (var i = 0; i < metricCards.length; i += 1) {
				(function (card) {
					var metricKey = String(card.getAttribute('data-metric-card') || '');
					card.addEventListener('click', function () {
						openModalForMetric(metricKey);
					});
					card.addEventListener('keydown', function (event) {
						if (event.key === 'Enter' || event.key === ' ') {
							event.preventDefault();
							openModalForMetric(metricKey);
						}
					});
				})(metricCards[i]);
			}

			for (var j = 0; j < rangeButtons.length; j += 1) {
				rangeButtons[j].addEventListener('click', function () {
					setRangeActive(this.getAttribute('data-metric-range'));
					fetchChartData();
				});
			}

			if (modalClose) {
				modalClose.addEventListener('click', closeModal);
			}

			modal.addEventListener('click', function (event) {
				if (event.target === modal) {
					closeModal();
				}
			});

			document.addEventListener('keydown', function (event) {
				if (event.key === 'Escape' && modal.classList.contains('show')) {
					closeModal();
				}
			});
		})();
	</script>
</body>
</html>
