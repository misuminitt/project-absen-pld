<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$summary = isset($summary) && is_array($summary) ? $summary : array();
$recent_logs = isset($recent_logs) && is_array($recent_logs) ? $recent_logs : array();
$recent_loans = isset($recent_loans) && is_array($recent_loans) ? $recent_loans : array();
$username = isset($username) && $username !== '' ? (string) $username : 'user';
$job_title = isset($job_title) && $job_title !== '' ? (string) $job_title : 'Teknisi';
$shift_name = isset($shift_name) && $shift_name !== '' ? (string) $shift_name : 'Shift Pagi - Sore';
$shift_time = isset($shift_time) && $shift_time !== '' ? (string) $shift_time : '08:00 - 17:00';
$geofence = isset($geofence) && is_array($geofence) ? $geofence : array();
$loan_config = isset($loan_config) && is_array($loan_config) ? $loan_config : array();
$loan_min_principal = isset($loan_config['min_principal']) ? (int) $loan_config['min_principal'] : 500000;
$loan_max_principal = isset($loan_config['max_principal']) ? (int) $loan_config['max_principal'] : 10000000;
$password_notice_success = isset($password_notice_success) ? trim((string) $password_notice_success) : '';
$password_notice_error = isset($password_notice_error) ? trim((string) $password_notice_error) : '';
$office_lat = isset($geofence['office_lat']) ? (float) $geofence['office_lat'] : -6.217062;
$office_lng = isset($geofence['office_lng']) ? (float) $geofence['office_lng'] : 106.1321109;
$office_radius_m = isset($geofence['radius_m']) ? (float) $geofence['radius_m'] : 100.0;
$max_accuracy_m = isset($geofence['max_accuracy_m']) ? (float) $geofence['max_accuracy_m'] : 50.0;
$logo_file = 'src/assets/pns_dashboard.png';
if (is_file(FCPATH.'src/assets/pns_dashboard.png'))
{
	$logo_file = 'src/assets/pns_dashboard.png';
}
elseif (is_file(FCPATH.'src/assts/pns_dashboard.png'))
{
	$logo_file = 'src/assts/pns_dashboard.png';
}
elseif (is_file(FCPATH.'src/assets/pns_logo_nav.png'))
{
	$logo_file = 'src/assets/pns_logo_nav.png';
}
elseif (is_file(FCPATH.'src/assts/pns_logo_nav.png'))
{
	$logo_file = 'src/assts/pns_logo_nav.png';
}
elseif (is_file(FCPATH.'src/assets/pns_new.png'))
{
	$logo_file = 'src/assets/pns_new.png';
}
elseif (is_file(FCPATH.'src/assts/pns_new.png'))
{
	$logo_file = 'src/assts/pns_new.png';
}
$logo_url = base_url($logo_file);
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Dashboard Absen - User'; ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<style>
		:root {
			--blue-900: #0d2c55;
			--blue-700: #1d5ea2;
			--blue-500: #2b82d5;
			--sky-100: #ebf6ff;
			--mint-100: #e5f7ef;
			--mint-700: #2b8f60;
			--amber-100: #fff4df;
			--amber-700: #b6781e;
			--ink-900: #10243a;
			--ink-600: #4f6278;
			--line: #d6e4f2;
			--surface: #ffffff;
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
			min-height: 100%;
			margin: 0;
		}

		body {
			font-family: 'Outfit', sans-serif;
			color: var(--ink-900);
			background:
				radial-gradient(circle at 15% 12%, rgba(64, 165, 255, 0.22) 0%, transparent 35%),
				radial-gradient(circle at 86% 0%, rgba(255, 214, 141, 0.2) 0%, transparent 40%),
				linear-gradient(180deg, #edf7ff 0%, #ffffff 42%);
		}

		.topbar {
			position: sticky;
			top: 0;
			z-index: 20;
			background: linear-gradient(120deg, var(--blue-900) 0%, var(--blue-700) 100%);
			box-shadow: 0 10px 24px rgba(8, 35, 72, 0.24);
		}

		.topbar-container {
			width: 100%;
			padding-top: 0.9rem;
			padding-bottom: 0.9rem;
			padding-left: 1rem;
			padding-right: 1rem;
		}

		@media (min-width: 576px) {
			.topbar-container {
				padding-left: 1.5rem;
				padding-right: 1.5rem;
			}
		}

		@media (min-width: 768px) {
			.topbar-container {
				padding-left: 2rem;
				padding-right: 2rem;
			}
		}

		@media (min-width: 992px) {
			.topbar-container {
				padding-left: 2.5rem;
				padding-right: 2.5rem;
			}
		}

		.topbar-inner {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.8rem;
			width: 100%;
		}

		.brand {
			display: inline-flex;
			align-items: center;
			gap: 0.56rem;
			color: #ffffff;
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
			margin-left: 1rem;
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

		.container {
			max-width: 1160px;
			margin: 0 auto;
			padding: 1.3rem 1rem 2rem;
		}

		.hero {
			background: var(--surface);
			border: 1px solid var(--line);
			border-radius: 18px;
			padding: 1rem;
			box-shadow: 0 14px 30px rgba(11, 51, 93, 0.08);
		}

		.hero-title {
			margin: 0;
			font-size: 1.45rem;
			font-weight: 800;
			letter-spacing: -0.02em;
		}

		.hero-subtitle {
			margin: 0.45rem 0 0;
			color: var(--ink-600);
			font-size: 0.94rem;
		}

		.hero-note {
			margin: 0.42rem 0 0;
			color: #31557a;
			font-size: 0.8rem;
			font-weight: 600;
		}

		.pill {
			display: inline-flex;
			align-items: center;
			border-radius: 999px;
			padding: 0.3rem 0.72rem;
			font-size: 0.75rem;
			font-weight: 700;
			letter-spacing: 0.06em;
			text-transform: uppercase;
			background: var(--mint-100);
			color: var(--mint-700);
			margin-bottom: 0.7rem;
		}

		.summary-grid {
			margin-top: 1rem;
			display: grid;
			gap: 0.7rem;
			grid-template-columns: repeat(3, minmax(0, 1fr));
		}

		.summary-item {
			border: 1px solid var(--line);
			border-radius: 14px;
			padding: 0.72rem 0.8rem;
			background: #fbfdff;
		}

		.summary-label {
			margin: 0;
			font-size: 0.72rem;
			font-weight: 700;
			letter-spacing: 0.07em;
			text-transform: uppercase;
			color: #607691;
		}

		.summary-value {
			margin: 0.3rem 0 0;
			font-size: 1.2rem;
			font-weight: 800;
			color: var(--blue-900);
		}

		.action-wrap {
			margin-top: 1rem;
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 0.85rem;
		}

		.action-column {
			display: grid;
			gap: 0.7rem;
		}

		.action-btn {
			border: 0;
			border-radius: 16px;
			padding: 1rem;
			cursor: pointer;
			display: flex;
			flex-direction: column;
			align-items: flex-start;
			gap: 0.42rem;
			box-shadow: 0 12px 24px rgba(9, 54, 100, 0.15);
			transition: transform 0.18s ease, box-shadow 0.2s ease;
		}

		.action-btn:hover {
			transform: translateY(-2px);
			box-shadow: 0 14px 28px rgba(9, 54, 100, 0.2);
		}

		.action-btn:active {
			transform: translateY(0);
		}

		.action-btn.checkin {
			background: linear-gradient(145deg, #1d5ea2 0%, #2b82d5 100%);
			color: #ffffff;
		}

		.action-btn.checkout {
			background: linear-gradient(145deg, #123c6e 0%, #1f6dbd 100%);
			color: #ffffff;
		}

		.action-btn.leave {
			background: linear-gradient(145deg, #2f8b67 0%, #44ae82 100%);
			color: #ffffff;
		}

		.action-btn.permit {
			background: linear-gradient(145deg, #6b55b8 0%, #8d73d8 100%);
			color: #ffffff;
		}

		.action-btn.loan {
			background: linear-gradient(145deg, #8b5c24 0%, #c18836 100%);
			color: #ffffff;
		}

		.action-title {
			margin: 0;
			font-size: 1.05rem;
			font-weight: 800;
		}

		.action-text {
			margin: 0;
			font-size: 0.84rem;
			opacity: 0.94;
		}

		.meta {
			margin-top: 1rem;
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 0.75rem;
		}

		.meta-box {
			border: 1px dashed #bfd5ea;
			border-radius: 13px;
			padding: 0.72rem 0.8rem;
			background: var(--sky-100);
		}

		.meta-box.warning {
			background: var(--amber-100);
			border-color: #edd6ad;
		}

		.meta-label {
			margin: 0;
			font-size: 0.7rem;
			font-weight: 700;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			color: #5d7591;
		}

		.meta-value {
			margin: 0.33rem 0 0;
			font-size: 1.08rem;
			font-weight: 800;
			color: var(--blue-900);
		}

		.history {
			margin-top: 1rem;
			background: var(--surface);
			border: 1px solid var(--line);
			border-radius: 16px;
			padding: 0.8rem;
			box-shadow: 0 10px 24px rgba(11, 51, 93, 0.06);
		}

		.history-title {
			margin: 0 0 0.55rem;
			font-size: 1rem;
			font-weight: 800;
		}

		.password-alert {
			border-radius: 12px;
			padding: 0.68rem 0.78rem;
			font-size: 0.84rem;
			font-weight: 600;
			margin-bottom: 0.72rem;
		}

		.password-alert.success {
			background: #e7f7ee;
			border: 1px solid #bde8ce;
			color: #1f7a4c;
		}

		.password-alert.error {
			background: #fdeeee;
			border: 1px solid #f3c7c7;
			color: #9f2b2b;
		}

		.password-form {
			display: grid;
			gap: 0.72rem;
		}

		.password-grid {
			display: grid;
			grid-template-columns: repeat(3, minmax(0, 1fr));
			gap: 0.7rem;
		}

		.password-field {
			display: grid;
			gap: 0.36rem;
		}

		.password-label {
			font-size: 0.72rem;
			font-weight: 700;
			letter-spacing: 0.04em;
			text-transform: uppercase;
			color: #516984;
		}

		.password-input {
			width: 100%;
			border: 1px solid #c9daeb;
			border-radius: 10px;
			padding: 0.58rem 0.7rem;
			font-family: inherit;
			font-size: 0.86rem;
			color: #173a5f;
			background: #ffffff;
		}

		.password-input:focus {
			outline: none;
			border-color: #78aee6;
			box-shadow: 0 0 0 3px rgba(58, 138, 216, 0.16);
		}

		.password-submit {
			border: 0;
			border-radius: 11px;
			background: linear-gradient(120deg, #1b5e9f, #2f8add);
			color: #ffffff;
			font-size: 0.86rem;
			font-weight: 700;
			padding: 0.62rem 1rem;
			cursor: pointer;
			justify-self: start;
		}

		.password-submit:hover {
			filter: brightness(1.03);
		}

		.table-wrap {
			overflow-x: auto;
		}

		table {
			width: 100%;
			border-collapse: collapse;
			min-width: 560px;
		}

		th,
		td {
			padding: 0.56rem 0.5rem;
			border-bottom: 1px solid #edf3f9;
			text-align: left;
		}

		th {
			font-size: 0.72rem;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			color: #60748a;
		}

		td {
			font-size: 0.86rem;
			color: #23374e;
		}

		.badge {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 0.25rem 0.58rem;
			border-radius: 999px;
			font-size: 0.7rem;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.05em;
		}

		.badge.hadir {
			background: var(--mint-100);
			color: var(--mint-700);
		}

		.badge.terlambat {
			background: var(--amber-100);
			color: var(--amber-700);
		}

		.badge.menunggu {
			background: #e4eef9;
			color: #375a82;
		}

		.badge.diterima {
			background: var(--mint-100);
			color: var(--mint-700);
		}

		.badge.ditolak {
			background: #fde9e9;
			color: #b44141;
		}

		.toast {
			position: fixed;
			left: 50%;
			top: 1rem;
			z-index: 120;
			background: #0f2f58;
			color: #ffffff;
			padding: 0.72rem 1rem;
			border-radius: 11px;
			font-size: 0.84rem;
			font-weight: 600;
			line-height: 1.35;
			text-align: center;
			width: min(92vw, 560px);
			box-shadow: 0 12px 22px rgba(7, 35, 67, 0.28);
			opacity: 0;
			transform: translate(-50%, -8px);
			pointer-events: none;
			transition: opacity 0.2s ease, transform 0.2s ease;
		}

		.toast.show {
			opacity: 1;
			transform: translate(-50%, 0);
		}

		.shift-badge {
			margin-top: 0.6rem;
			display: inline-flex;
			align-items: center;
			gap: 0.4rem;
			padding: 0.34rem 0.7rem;
			border-radius: 999px;
			font-size: 0.76rem;
			font-weight: 700;
			background: #e4f1ff;
			color: #1b5f9f;
		}

		.attendance-modal {
			position: fixed;
			inset: 0;
			z-index: 90;
			display: none;
			overflow-y: auto;
		}

		.attendance-modal.show {
			display: block;
		}

		.modal-overlay {
			position: fixed;
			inset: 0;
			z-index: 0;
			background: rgba(7, 20, 36, 0.6);
		}

		.modal-panel {
			position: relative;
			z-index: 1;
			width: min(100%, 760px);
			max-width: 760px;
			margin: min(6vh, 40px) auto;
			background: #ffffff;
			border-radius: 18px;
			box-shadow: 0 28px 46px rgba(4, 24, 50, 0.28);
			overflow: hidden;
			max-height: calc(100dvh - min(12vh, 80px));
			display: flex;
			flex-direction: column;
		}

		.modal-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.8rem;
			padding: 0.95rem 1rem;
			background: linear-gradient(120deg, var(--blue-900) 0%, var(--blue-700) 100%);
			color: #ffffff;
		}

		.modal-title {
			margin: 0;
			font-size: 1rem;
			font-weight: 800;
		}

		.modal-close {
			width: 36px;
			height: 36px;
			border-radius: 999px;
			border: 0;
			background: rgba(255, 255, 255, 0.16);
			color: #ffffff;
			font-size: 1.3rem;
			line-height: 1;
			cursor: pointer;
		}

		.modal-body {
			padding: 0.9rem 1rem 1rem;
			overflow-y: auto;
			overscroll-behavior: contain;
			-webkit-overflow-scrolling: touch;
		}

		.request-modal {
			position: fixed;
			inset: 0;
			z-index: 95;
			display: none;
			overflow-y: auto;
		}

		.request-modal.show {
			display: block;
		}

		.request-panel {
			position: relative;
			z-index: 1;
			width: min(100%, 580px);
			max-width: 580px;
			margin: min(8vh, 52px) auto;
			background: #ffffff;
			border-radius: 16px;
			box-shadow: 0 24px 42px rgba(4, 24, 50, 0.28);
			overflow: hidden;
			display: flex;
			flex-direction: column;
		}

		.request-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.8rem;
			padding: 0.9rem 1rem;
			background: linear-gradient(120deg, var(--blue-900) 0%, var(--blue-700) 100%);
			color: #ffffff;
		}

		.request-title {
			margin: 0;
			font-size: 1rem;
			font-weight: 800;
		}

		.request-body {
			padding: 1rem;
		}

		.request-form {
			display: grid;
			gap: 0.8rem;
		}

		.request-kind {
			margin: 0;
			font-size: 0.9rem;
			font-weight: 700;
			color: #1b3857;
		}

		.request-kind strong {
			color: var(--blue-900);
		}

		.request-grid {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 0.75rem;
		}

		.request-field {
			display: grid;
			gap: 0.38rem;
		}

		.request-field.is-hidden {
			display: none;
		}

		.request-label {
			margin: 0;
			font-size: 0.72rem;
			font-weight: 700;
			letter-spacing: 0.06em;
			text-transform: uppercase;
			color: #5f7591;
		}

		.request-label-row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.6rem;
		}

		.request-label-note {
			margin: 0;
			font-size: 0.7rem;
			font-weight: 700;
			color: #5f7591;
			white-space: nowrap;
		}

		.request-input,
		.request-textarea {
			width: 100%;
			border: 1px solid #bfd2e6;
			border-radius: 10px;
			padding: 0.58rem 0.64rem;
			font-family: inherit;
			font-size: 0.84rem;
			color: #16334f;
			background: #ffffff;
		}

		.request-textarea {
			min-height: 110px;
			resize: vertical;
		}

		.request-input:focus,
		.request-textarea:focus {
			outline: none;
			border-color: #2b82d5;
			box-shadow: 0 0 0 3px rgba(43, 130, 213, 0.14);
		}

		.request-hint {
			margin: 0;
			font-size: 0.74rem;
			color: #617991;
			font-weight: 600;
		}

		.tenor-picker {
			display: grid;
			gap: 0.48rem;
		}

		.tenor-grid {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 0.5rem;
		}

		.tenor-grid.is-hidden {
			display: none;
		}

		.tenor-btn {
			border: 1px solid #bfd2e6;
			background: #ffffff;
			border-radius: 10px;
			padding: 0.56rem 0.64rem;
			font-family: inherit;
			font-size: 0.82rem;
			font-weight: 700;
			color: #1f3a56;
			cursor: pointer;
			text-align: center;
			transition: all 0.18s ease;
		}

		.tenor-btn:hover {
			border-color: #2b82d5;
			color: #1d5ea2;
		}

		.tenor-btn.is-active {
			background: linear-gradient(145deg, #1d5ea2 0%, #2b82d5 100%);
			border-color: #1f6dbd;
			color: #ffffff;
			box-shadow: 0 8px 16px rgba(29, 94, 162, 0.2);
		}

		.tenor-more-toggle {
			border: 0;
			background: transparent;
			color: #2b82d5;
			font-family: inherit;
			font-size: 0.74rem;
			font-weight: 700;
			padding: 0;
			width: fit-content;
			cursor: pointer;
			text-decoration: underline;
			text-underline-offset: 2px;
		}

		.request-submit {
			border: 0;
			border-radius: 10px;
			padding: 0.66rem 0.9rem;
			background: linear-gradient(145deg, #1d5ea2 0%, #2b82d5 100%);
			color: #ffffff;
			font-size: 0.84rem;
			font-weight: 700;
			cursor: pointer;
		}

		.request-submit:disabled {
			opacity: 0.6;
			cursor: not-allowed;
		}

		.loan-detail-card {
			border: 1px solid #d8e3ef;
			border-radius: 12px;
			background: #fdfefe;
			padding: 0.72rem 0.78rem;
		}

		.loan-detail-empty {
			margin: 0;
			font-size: 0.8rem;
			color: #5d7390;
			font-weight: 600;
		}

		.loan-detail-content {
			display: grid;
			gap: 0.22rem;
		}

		.loan-detail-row {
			display: grid;
			grid-template-columns: minmax(0, 1fr) auto;
			gap: 0.6rem;
			align-items: baseline;
			padding: 0.08rem 0;
		}

		.loan-detail-row span {
			font-size: 0.8rem;
			color: #1f334a;
		}

		.loan-detail-row strong {
			font-size: 0.84rem;
			font-weight: 700;
			color: #21374f;
			text-align: right;
		}

		.loan-detail-status strong {
			color: #1d5ea2;
		}

		.loan-detail-row-highlight span,
		.loan-detail-row-highlight strong {
			color: #15935f;
			font-weight: 700;
		}

		.loan-rate {
			font-weight: 800;
		}

		.loan-detail-divider {
			margin-top: 0.2rem;
			border-top: 1px dashed #d8e3ef;
		}

		.loan-detail-total {
			padding-top: 0.35rem;
		}

		.loan-detail-total span {
			font-size: 0.86rem;
			color: #1f334a;
		}

		.loan-detail-total strong {
			font-size: 0.95rem;
			color: #132f4b;
		}

		.modal-grid {
			display: grid;
			grid-template-columns: 1.3fr 1fr;
			gap: 0.9rem;
		}

		.preview-card,
		.info-card {
			border: 1px solid var(--line);
			border-radius: 14px;
			padding: 0.75rem;
			background: #fbfdff;
		}

		.card-label {
			margin: 0 0 0.55rem;
			font-size: 0.74rem;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			color: #607691;
			font-weight: 700;
		}

		.video-wrap {
			position: relative;
			background: #132b4a;
			border-radius: 10px;
			overflow: hidden;
			aspect-ratio: 1 / 1;
		}

		.camera-video {
			width: 100%;
			height: 100%;
			object-fit: contain;
			object-position: center;
			display: block;
		}

		.camera-placeholder {
			position: absolute;
			inset: 0;
			display: flex;
			align-items: center;
			justify-content: center;
			text-align: center;
			padding: 0.8rem;
			font-size: 0.82rem;
			font-weight: 600;
			color: rgba(255, 255, 255, 0.9);
			background: rgba(7, 22, 42, 0.55);
		}

		.control-row {
			margin-top: 0.7rem;
			display: grid;
			grid-template-columns: 1fr auto;
			gap: 0.6rem;
			align-items: center;
		}

		.camera-select {
			width: 100%;
			border: 1px solid #bfd2e6;
			border-radius: 10px;
			padding: 0.56rem 0.62rem;
			font-family: inherit;
			font-size: 0.84rem;
			color: #1b3552;
			background: #ffffff;
		}

		.capture-btn {
			border: 0;
			border-radius: 10px;
			padding: 0.6rem 0.9rem;
			background: linear-gradient(145deg, #1d5ea2 0%, #2b82d5 100%);
			color: #ffffff;
			font-size: 0.82rem;
			font-weight: 700;
			cursor: pointer;
			min-width: 106px;
		}

		.capture-btn:disabled {
			opacity: 0.5;
			cursor: not-allowed;
		}

		.info-item {
			border: 1px dashed #c4d8ec;
			border-radius: 10px;
			padding: 0.62rem 0.7rem;
			background: #f5faff;
			margin-bottom: 0.55rem;
		}

		.info-item:last-child {
			margin-bottom: 0;
		}

		.info-title {
			margin: 0;
			font-size: 0.68rem;
			font-weight: 700;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			color: #5f7591;
		}

		.info-value {
			margin: 0.28rem 0 0;
			font-size: 0.9rem;
			font-weight: 700;
			color: #0e3158;
			word-break: break-word;
		}

		.late-reason-wrap {
			display: none;
		}

		.late-reason-wrap.show {
			display: block;
		}

		.late-reason-input {
			margin-top: 0.35rem;
			width: 100%;
			min-height: 84px;
			resize: vertical;
			border: 1px solid #b8cee4;
			border-radius: 10px;
			padding: 0.55rem 0.62rem;
			font-family: inherit;
			font-size: 0.84rem;
			color: #16334f;
			background: #ffffff;
		}

		.late-reason-input:focus {
			outline: none;
			border-color: #2b82d5;
			box-shadow: 0 0 0 3px rgba(43, 130, 213, 0.14);
		}

		.capture-result {
			margin-top: 0.9rem;
			border: 1px solid #d8e6f4;
			border-radius: 12px;
			background: #ffffff;
			padding: 0.7rem;
			display: none;
		}

		.capture-result.show {
			display: block;
		}

		.capture-result h3 {
			margin: 0 0 0.5rem;
			font-size: 0.9rem;
			font-weight: 800;
		}

		.capture-image {
			width: 100%;
			border-radius: 10px;
			border: 1px solid #d6e3f2;
			aspect-ratio: 1 / 1;
			max-height: 360px;
			object-fit: contain;
			background: #132b4a;
			display: block;
		}

		.result-meta {
			margin-top: 0.55rem;
			font-size: 0.8rem;
			color: #3e546d;
			line-height: 1.45;
		}

		@media (max-width: 840px) {
			.brand-text {
				font-size: 0.96rem;
			}

			.summary-grid,
			.action-wrap,
			.meta {
				grid-template-columns: 1fr;
			}

			.password-grid {
				grid-template-columns: 1fr;
			}

			.hero-title {
				font-size: 1.3rem;
			}

			.modal-panel {
				margin: 1rem auto;
				max-height: calc(100dvh - 2rem);
			}

			.modal-grid {
				grid-template-columns: 1fr;
			}

			.control-row {
				grid-template-columns: 1fr;
			}

			.request-panel {
				margin: 1rem auto;
			}

			.request-grid {
				grid-template-columns: 1fr;
			}
		}

		@media (min-width: 768px) {
			.brand-logo {
				height: 40px;
			}
		}
	</style>
</head>
<body>
	<nav class="topbar">
		<div class="topbar-container">
			<div class="topbar-inner">
				<a href="<?php echo site_url('home'); ?>" class="brand">
					<img class="brand-logo" src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Absen Online">
					<span class="brand-text">Dashboard User Absen</span>
				</a>
				<a href="<?php echo site_url('logout'); ?>" class="logout">Logout</a>
			</div>
		</div>
	</nav>

	<main class="container">
		<section class="hero">
			<p class="pill" id="summaryStatusPill"><?php echo htmlspecialchars(isset($summary['status_hari_ini']) ? (string) $summary['status_hari_ini'] : 'Belum Absen', ENT_QUOTES, 'UTF-8'); ?></p>
			<h1 class="hero-title">Halo, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>. Siap absen hari ini?</h1>
			<p class="hero-subtitle">Setiap absen wajib kamera dan GPS aktif. Ambil foto langsung dari popup absensi sebelum menyimpan.</p>
			<p class="hero-note">Absen masuk dibuka mulai 06:30 WIB. Batas maksimal absen pulang adalah 23:59 WIB.</p>
			<p class="shift-badge">Jabatan: <?php echo htmlspecialchars($job_title, ENT_QUOTES, 'UTF-8'); ?></p>
			<p class="shift-badge"><?php echo htmlspecialchars($shift_name, ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($shift_time, ENT_QUOTES, 'UTF-8'); ?></p>

			<div class="action-wrap">
				<div class="action-column">
					<button type="button" class="action-btn checkin" data-attendance-open="true">
						<p class="action-title">Absen</p>
						<p class="action-text">Tap untuk pilih absen masuk atau pulang.</p>
					</button>
					<button type="button" class="action-btn leave" data-request-type="cuti">
						<p class="action-title">Pengajuan Cuti</p>
						<p class="action-text">Ajukan cuti jika kamu akan libur kerja.</p>
					</button>
				</div>
				<div class="action-column">
					<button type="button" class="action-btn permit" data-request-type="izin">
						<p class="action-title">Pengajuan Izin</p>
						<p class="action-text">Ajukan izin untuk keperluan tertentu.</p>
					</button>
					<button type="button" class="action-btn loan" data-loan-open="true">
						<p class="action-title">Pengajuan Pinjaman</p>
						<p class="action-text">Ajukan pinjaman dengan nominal, tenor, alasan, dan rincian otomatis.</p>
					</button>
				</div>
			</div>

			<div class="meta">
				<div class="meta-box">
					<p class="meta-label">Jam Masuk / Pulang</p>
					<p class="meta-value">
						<span id="summaryJamMasuk"><?php echo htmlspecialchars(isset($summary['jam_masuk']) ? (string) $summary['jam_masuk'] : '-', ENT_QUOTES, 'UTF-8'); ?></span>
						/
						<span id="summaryJamPulang"><?php echo htmlspecialchars(isset($summary['jam_pulang']) ? (string) $summary['jam_pulang'] : '-', ENT_QUOTES, 'UTF-8'); ?></span>
					</p>
				</div>
				<div class="meta-box warning">
					<p class="meta-label">Target Jam Pulang</p>
					<p class="meta-value"><span id="summaryTargetPulang"><?php echo htmlspecialchars(isset($summary['target_pulang']) ? (string) $summary['target_pulang'] : '17:00', ENT_QUOTES, 'UTF-8'); ?></span> WIB</p>
				</div>
			</div>

			<div class="summary-grid">
				<div class="summary-item">
					<p class="summary-label">Hadir Bulan Ini</p>
					<p class="summary-value"><span id="summaryHadirBulan"><?php echo htmlspecialchars((string) (isset($summary['total_hadir_bulan_ini']) ? $summary['total_hadir_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Hari</p>
				</div>
				<div class="summary-item">
					<p class="summary-label">Terlambat</p>
					<p class="summary-value"><span id="summaryTerlambatBulan"><?php echo htmlspecialchars((string) (isset($summary['total_terlambat_bulan_ini']) ? $summary['total_terlambat_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Hari</p>
				</div>
				<div class="summary-item">
					<p class="summary-label">Izin/Cuti</p>
					<p class="summary-value"><span id="summaryIzinBulan"><?php echo htmlspecialchars((string) (isset($summary['total_izin_bulan_ini']) ? $summary['total_izin_bulan_ini'] : 0), ENT_QUOTES, 'UTF-8'); ?></span> Hari</p>
				</div>
			</div>
		</section>

		<section class="history" id="ubah-password">
			<h2 class="history-title">Ganti Password Akun</h2>
			<?php if ($password_notice_success !== ''): ?>
				<div class="password-alert success"><?php echo htmlspecialchars($password_notice_success, ENT_QUOTES, 'UTF-8'); ?></div>
			<?php endif; ?>
			<?php if ($password_notice_error !== ''): ?>
				<div class="password-alert error"><?php echo htmlspecialchars($password_notice_error, ENT_QUOTES, 'UTF-8'); ?></div>
			<?php endif; ?>
			<form class="password-form" method="post" action="<?php echo site_url('home/update_my_password'); ?>" autocomplete="off">
				<div class="password-grid">
					<div class="password-field">
						<label class="password-label" for="currentPassword">Password Saat Ini</label>
						<input class="password-input" type="password" id="currentPassword" name="current_password" required>
					</div>
					<div class="password-field">
						<label class="password-label" for="newPassword">Password Baru</label>
						<input class="password-input" type="password" id="newPassword" name="new_password" minlength="3" required>
					</div>
					<div class="password-field">
						<label class="password-label" for="confirmPassword">Konfirmasi Password Baru</label>
						<input class="password-input" type="password" id="confirmPassword" name="confirm_password" minlength="3" required>
					</div>
				</div>
				<button type="submit" class="password-submit">Simpan Password Baru</button>
			</form>
		</section>

		<section class="history">
			<h2 class="history-title">Riwayat Absensi Terbaru</h2>
			<div class="table-wrap">
				<table>
					<thead>
						<tr>
							<th>Tanggal</th>
							<th>Masuk</th>
							<th>Pulang</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody id="historyTableBody">
						<?php if (empty($recent_logs)): ?>
							<tr>
								<td colspan="4">Belum ada riwayat absensi.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($recent_logs as $log): ?>
								<?php
								$status = isset($log['status']) ? strtolower((string) $log['status']) : '';
								$status_class = strpos($status, 'terlambat') !== FALSE ? 'terlambat' : 'hadir';
								?>
								<tr>
									<td><?php echo htmlspecialchars(isset($log['tanggal']) ? (string) $log['tanggal'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($log['masuk']) ? (string) $log['masuk'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($log['pulang']) ? (string) $log['pulang'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><span class="badge <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(isset($log['status']) ? (string) $log['status'] : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>

		<section class="history">
			<h2 class="history-title">Riwayat Pinjaman</h2>
			<div class="table-wrap">
				<table>
					<thead>
						<tr>
							<th>Tanggal</th>
							<th>Nominal</th>
							<th>Tenor</th>
							<th>Cicilan/Bulan</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody id="loanHistoryTableBody">
						<?php if (empty($recent_loans)): ?>
							<tr>
								<td colspan="5">Belum ada riwayat pinjaman.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($recent_loans as $loan): ?>
								<?php
								$loan_status = isset($loan['status']) ? strtolower(trim((string) $loan['status'])) : '';
								$loan_status_class = 'menunggu';
								if (strpos($loan_status, 'terima') !== FALSE)
								{
									$loan_status_class = 'diterima';
								}
								elseif (strpos($loan_status, 'tolak') !== FALSE)
								{
									$loan_status_class = 'ditolak';
								}
								?>
								<tr>
									<td><?php echo htmlspecialchars(isset($loan['tanggal']) ? (string) $loan['tanggal'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($loan['nominal']) ? (string) $loan['nominal'] : 'Rp 0', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($loan['tenor']) ? (string) $loan['tenor'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars(isset($loan['cicilan_bulanan']) ? (string) $loan['cicilan_bulanan'] : 'Rp 0', ENT_QUOTES, 'UTF-8'); ?></td>
									<td><span class="badge <?php echo htmlspecialchars($loan_status_class, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(isset($loan['status']) ? (string) $loan['status'] : 'Menunggu', ENT_QUOTES, 'UTF-8'); ?></span></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</main>

	<div id="attendanceModal" class="attendance-modal" aria-hidden="true">
		<div class="modal-overlay" data-modal-close></div>
		<section class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="attendanceTitle">
			<div class="modal-head">
				<h2 id="attendanceTitle" class="modal-title">Proses Absensi</h2>
				<button type="button" class="modal-close" id="closeAttendanceModal" aria-label="Tutup popup">&times;</button>
			</div>
			<div class="modal-body">
				<div class="modal-grid">
					<div class="preview-card">
						<p class="card-label">Preview Kamera</p>
						<div class="video-wrap">
							<video id="cameraPreview" class="camera-video" autoplay muted playsinline></video>
							<div id="cameraPlaceholder" class="camera-placeholder">Meminta akses kamera...</div>
						</div>
						<div class="control-row">
							<select id="cameraSelect" class="camera-select" aria-label="Pilih kamera">
								<option value="">Memuat daftar kamera...</option>
							</select>
							<button type="button" id="capturePhotoButton" class="capture-btn" disabled>Ambil Foto</button>
						</div>
						<canvas id="captureCanvas" width="1280" height="720" hidden></canvas>
					</div>
					<div class="info-card">
						<p class="card-label">Informasi Absensi</p>
						<div class="info-item">
							<p class="info-title">Jenis Absensi (Wajib Pilih)</p>
							<select id="attendanceTypeSelect" class="request-input" aria-label="Pilih jenis absensi">
								<option value="masuk">Absen Masuk</option>
								<option value="pulang">Absen Pulang</option>
							</select>
						</div>
							<div class="info-item">
								<p class="info-title">Shift</p>
								<p id="shiftValue" class="info-value"><?php echo htmlspecialchars($shift_name.' ('.$shift_time.')', ENT_QUOTES, 'UTF-8'); ?></p>
							</div>
							<div class="info-item">
								<p class="info-title">Aturan Waktu</p>
								<p class="info-value">Masuk: mulai 06:30 WIB | Pulang: maksimal 23:59 WIB</p>
							</div>
							<div class="info-item">
								<p class="info-title">GPS</p>
								<p id="gpsValue" class="info-value">Meminta lokasi...</p>
							</div>
						<div class="info-item">
							<p class="info-title">Waktu</p>
							<p id="timeValue" class="info-value">-</p>
						</div>
						<div class="info-item late-reason-wrap" id="lateReasonWrap">
							<p class="info-title">Alasan Telat (Wajib Jika Telat)</p>
							<textarea id="lateReasonInput" class="late-reason-input" placeholder="Tulis alasan keterlambatan saat absen masuk..."></textarea>
						</div>
					</div>
				</div>

				<div id="captureResult" class="capture-result">
					<h3>Hasil Foto Absensi</h3>
					<img id="capturedImage" class="capture-image" src="" alt="Hasil foto absensi">
					<p id="resultMeta" class="result-meta"></p>
				</div>
			</div>
		</section>
	</div>

	<div id="requestModal" class="request-modal" aria-hidden="true">
		<div class="modal-overlay" data-request-close></div>
		<section class="request-panel" role="dialog" aria-modal="true" aria-labelledby="requestTitle">
			<div class="request-head">
				<h2 id="requestTitle" class="request-title">Form Pengajuan</h2>
				<button type="button" class="modal-close" id="closeRequestModal" aria-label="Tutup popup pengajuan">&times;</button>
			</div>
			<div class="request-body">
				<form id="leaveRequestForm" class="request-form" novalidate>
					<input type="hidden" id="requestTypeInput" name="request_type" value="">
					<p class="request-kind">Jenis pengajuan: <strong id="requestTypeLabel">-</strong></p>
					<div class="request-field is-hidden" id="izinTypeWrap">
						<label for="izinTypeInput" class="request-label">Jenis Izin</label>
						<select id="izinTypeInput" class="request-input" name="izin_type">
							<option value="sakit">Sakit</option>
							<option value="darurat">Izin Darurat</option>
						</select>
					</div>
					<div class="request-grid">
						<div class="request-field">
							<label for="requestStartDate" class="request-label">Tanggal Mulai</label>
							<input id="requestStartDate" class="request-input" type="date" name="start_date" required>
						</div>
						<div class="request-field">
							<label for="requestEndDate" class="request-label">Tanggal Selesai</label>
							<input id="requestEndDate" class="request-input" type="date" name="end_date" required>
						</div>
					</div>
					<div class="request-field">
						<label for="requestReason" class="request-label" id="requestReasonLabel">Alasan Izin</label>
						<textarea id="requestReason" class="request-textarea" name="reason" placeholder="Contoh: ada keperluan keluarga / urusan administrasi penting..." required></textarea>
					</div>
					<div class="request-field">
						<label for="requestSupportFile" class="request-label" id="requestSupportLabel">Bukti (Opsional)</label>
						<input id="requestSupportFile" class="request-input" type="file" name="support_file" accept=".pdf,.png,.jpg,.heic">
						<p class="request-hint" id="requestSupportHint">Format yang diizinkan: .pdf, .png, .jpg, .heic</p>
					</div>
					<button type="submit" id="submitLeaveRequestButton" class="request-submit">Kirim Pengajuan</button>
				</form>
			</div>
		</section>
	</div>

	<div id="loanModal" class="request-modal" aria-hidden="true">
		<div class="modal-overlay" data-loan-close></div>
		<section class="request-panel" role="dialog" aria-modal="true" aria-labelledby="loanTitle">
			<div class="request-head">
				<h2 id="loanTitle" class="request-title">Form Pengajuan Pinjaman</h2>
				<button type="button" class="modal-close" id="closeLoanModal" aria-label="Tutup popup pinjaman">&times;</button>
			</div>
			<div class="request-body">
				<form id="loanRequestForm" class="request-form" novalidate>
					<div class="request-field">
						<div class="request-label-row">
							<label for="loanAmount" class="request-label">Nominal Pinjaman</label>
							<p class="request-label-note">Min Rp <?php echo number_format($loan_min_principal, 0, ',', '.'); ?> | Max Rp <?php echo number_format($loan_max_principal, 0, ',', '.'); ?></p>
						</div>
						<input id="loanAmount" class="request-input" type="text" inputmode="numeric" autocomplete="off" placeholder="Contoh: 1500000" required>
					</div>
					<div class="request-field">
						<label for="loanTenor" class="request-label">Tenor (Bulan)</label>
						<input id="loanTenor" type="hidden" value="">
						<div class="tenor-picker" id="loanTenorPicker">
							<div class="tenor-grid">
								<?php for ($tenor_option = 1; $tenor_option <= 4; $tenor_option += 1): ?>
									<button type="button" class="tenor-btn" data-loan-tenor="<?php echo $tenor_option; ?>" aria-pressed="false"><?php echo $tenor_option; ?> bulan</button>
								<?php endfor; ?>
							</div>
							<button type="button" id="toggleMoreTenorButton" class="tenor-more-toggle">Lihat lainnya...</button>
							<div id="tenorMoreWrap" class="tenor-grid is-hidden">
								<?php for ($tenor_option = 5; $tenor_option <= 12; $tenor_option += 1): ?>
									<button type="button" class="tenor-btn" data-loan-tenor="<?php echo $tenor_option; ?>" aria-pressed="false"><?php echo $tenor_option; ?> bulan</button>
								<?php endfor; ?>
							</div>
						</div>
					</div>
					<div class="request-field">
						<label for="loanReason" class="request-label">Alasan Pinjaman</label>
						<textarea id="loanReason" class="request-textarea" placeholder="Jelaskan alasan pengajuan pinjaman..." required></textarea>
					</div>
					<div class="request-field">
						<label class="request-label">Rincian Pinjaman</label>
						<div class="loan-detail-card" id="loanDetailCard" hidden>
							<div id="loanDetailContent" class="loan-detail-content" hidden>
								<div class="loan-detail-row">
									<span>Nominal pinjaman</span>
									<strong id="loanNominalValue">Rp 0</strong>
								</div>
								<div class="loan-detail-row">
									<span>Tenor</span>
									<strong id="loanTenorValue">0 bulan</strong>
								</div>
								<div class="loan-detail-row loan-detail-status">
									<span>Status</span>
									<strong id="loanStatusValue">-</strong>
								</div>
								<div class="loan-detail-row loan-detail-row-highlight">
									<span>Bunga <span class="loan-rate" id="loanRateValue">0,00% per bulan</span></span>
									<strong id="loanInterestValue">Rp 0</strong>
								</div>
								<div class="loan-detail-row">
									<span>Cicilan per bulan</span>
									<strong id="loanMonthlyInstallmentValue">Rp 0</strong>
								</div>
								<div class="loan-detail-divider"></div>
								<div class="loan-detail-row loan-detail-total">
									<span>Total cicilan</span>
									<strong id="loanTotalValue">Rp 0</strong>
								</div>
							</div>
						</div>
					</div>
					<button type="submit" id="submitLoanRequestButton" class="request-submit">Kirim Pengajuan Pinjaman</button>
				</form>
			</div>
		</section>
	</div>

	<div id="toastMessage" class="toast" role="status" aria-live="polite"></div>

	<script>
		(function () {
			var toast = document.getElementById('toastMessage');
			var modal = document.getElementById('attendanceModal');
			var overlayClose = modal ? modal.querySelector('[data-modal-close]') : null;
			var closeModalButton = document.getElementById('closeAttendanceModal');
			var actionButtons = document.querySelectorAll('[data-attendance-open]');
			var requestModal = document.getElementById('requestModal');
			var requestOverlayClose = requestModal ? requestModal.querySelector('[data-request-close]') : null;
			var closeRequestModalButton = document.getElementById('closeRequestModal');
			var requestButtons = document.querySelectorAll('[data-request-type]');
			var loanModal = document.getElementById('loanModal');
			var loanOverlayClose = loanModal ? loanModal.querySelector('[data-loan-close]') : null;
			var closeLoanModalButton = document.getElementById('closeLoanModal');
			var loanButtons = document.querySelectorAll('[data-loan-open]');
			var leaveRequestForm = document.getElementById('leaveRequestForm');
			var requestTypeInput = document.getElementById('requestTypeInput');
			var requestTypeLabel = document.getElementById('requestTypeLabel');
			var izinTypeWrap = document.getElementById('izinTypeWrap');
			var izinTypeInput = document.getElementById('izinTypeInput');
			var requestStartDate = document.getElementById('requestStartDate');
			var requestEndDate = document.getElementById('requestEndDate');
			var requestReason = document.getElementById('requestReason');
			var requestReasonLabel = document.getElementById('requestReasonLabel');
			var requestSupportFile = document.getElementById('requestSupportFile');
			var requestSupportLabel = document.getElementById('requestSupportLabel');
			var requestSupportHint = document.getElementById('requestSupportHint');
			var submitLeaveRequestButton = document.getElementById('submitLeaveRequestButton');
			var loanRequestForm = document.getElementById('loanRequestForm');
			var loanAmount = document.getElementById('loanAmount');
			var loanTenor = document.getElementById('loanTenor');
			var loanTenorButtons = document.querySelectorAll('[data-loan-tenor]');
			var toggleMoreTenorButton = document.getElementById('toggleMoreTenorButton');
			var tenorMoreWrap = document.getElementById('tenorMoreWrap');
			var loanReason = document.getElementById('loanReason');
			var loanDetailCard = document.getElementById('loanDetailCard');
			var loanDetailContent = document.getElementById('loanDetailContent');
			var loanNominalValue = document.getElementById('loanNominalValue');
			var loanTenorValue = document.getElementById('loanTenorValue');
			var loanStatusValue = document.getElementById('loanStatusValue');
			var loanRateValue = document.getElementById('loanRateValue');
			var loanInterestValue = document.getElementById('loanInterestValue');
			var loanMonthlyInstallmentValue = document.getElementById('loanMonthlyInstallmentValue');
			var loanTotalValue = document.getElementById('loanTotalValue');
			var submitLoanRequestButton = document.getElementById('submitLoanRequestButton');
			var cameraPreview = document.getElementById('cameraPreview');
			var cameraPlaceholder = document.getElementById('cameraPlaceholder');
			var cameraSelect = document.getElementById('cameraSelect');
			var capturePhotoButton = document.getElementById('capturePhotoButton');
			var captureCanvas = document.getElementById('captureCanvas');
			var attendanceTypeSelect = document.getElementById('attendanceTypeSelect');
			var gpsValue = document.getElementById('gpsValue');
			var timeValue = document.getElementById('timeValue');
			var shiftValue = document.getElementById('shiftValue');
			var lateReasonWrap = document.getElementById('lateReasonWrap');
			var lateReasonInput = document.getElementById('lateReasonInput');
			var captureResult = document.getElementById('captureResult');
			var capturedImage = document.getElementById('capturedImage');
			var resultMeta = document.getElementById('resultMeta');
			var summaryStatusPill = document.getElementById('summaryStatusPill');
			var summaryJamMasuk = document.getElementById('summaryJamMasuk');
			var summaryJamPulang = document.getElementById('summaryJamPulang');
			var summaryTargetPulang = document.getElementById('summaryTargetPulang');
			var summaryHadirBulan = document.getElementById('summaryHadirBulan');
			var summaryTerlambatBulan = document.getElementById('summaryTerlambatBulan');
			var summaryIzinBulan = document.getElementById('summaryIzinBulan');
			var historyTableBody = document.getElementById('historyTableBody');
			var loanHistoryTableBody = document.getElementById('loanHistoryTableBody');
			var submitEndpoint = <?php echo json_encode(parse_url(site_url('home/submit_attendance'), PHP_URL_PATH)); ?>;
			var leaveRequestEndpoint = <?php echo json_encode(parse_url(site_url('home/submit_leave_request'), PHP_URL_PATH)); ?>;
			var loanRequestEndpoint = <?php echo json_encode(parse_url(site_url('home/submit_loan_request'), PHP_URL_PATH)); ?>;
			var dashboardSummaryEndpoint = <?php echo json_encode(parse_url(site_url('home/user_dashboard_live_data'), PHP_URL_PATH)); ?>;
			var loanConfig = <?php echo json_encode(array(
				'minPrincipal' => isset($loan_config['min_principal']) ? (int) $loan_config['min_principal'] : 500000,
				'maxPrincipal' => isset($loan_config['max_principal']) ? (int) $loan_config['max_principal'] : 10000000,
				'minTenorMonths' => isset($loan_config['min_tenor_months']) ? (int) $loan_config['min_tenor_months'] : 1,
				'maxTenorMonths' => isset($loan_config['max_tenor_months']) ? (int) $loan_config['max_tenor_months'] : 12,
				'isFirstLoan' => isset($loan_config['is_first_loan']) ? (bool) $loan_config['is_first_loan'] : TRUE
			)); ?>;
			var shiftTimeText = <?php echo json_encode($shift_time); ?>;
			var officeLat = <?php echo json_encode($office_lat); ?>;
			var officeLng = <?php echo json_encode($office_lng); ?>;
			var officeRadiusM = <?php echo json_encode($office_radius_m); ?>;
			var maxAccuracyM = <?php echo json_encode($max_accuracy_m); ?>;
			var shouldUnmirrorPreview = true;
			var shouldUnmirrorCapture = true;
			var defaultLeaveSubmitText = submitLeaveRequestButton ? submitLeaveRequestButton.textContent : 'Kirim Pengajuan';
			var defaultLoanSubmitText = submitLoanRequestButton ? submitLoanRequestButton.textContent : 'Kirim Pengajuan Pinjaman';
			var allowedSupportExtensions = {
				pdf: true,
				png: true,
				jpg: true,
				heic: true
			};

			if (!toast || !modal || !requestModal || !actionButtons.length) {
				return;
			}

			var mediaStream = null;
			var currentPosition = null;
			var currentAction = '';
			var selectedCameraId = '';
			var gpsRefreshTimer = null;
			var gpsRequestInFlight = false;
			var modalClockTimer = null;
			var dashboardSummaryTimer = null;
			var dashboardSummaryRequestInFlight = false;
			var hideTimer = null;
			var showToast = function (message) {
				toast.textContent = message;
				toast.classList.add('show');
				if (hideTimer) {
					window.clearTimeout(hideTimer);
				}
				hideTimer = window.setTimeout(function () {
					toast.classList.remove('show');
				}, 1600);
			};

			var normalizeSummaryCount = function (value) {
				var countValue = parseInt(String(value || '0'), 10);
				if (!isFinite(countValue) || countValue < 0) {
					return 0;
				}
				return countValue;
			};

			var applyDashboardSummary = function (summaryData) {
				var summary = summaryData && typeof summaryData === 'object' ? summaryData : {};
				if (summaryStatusPill) {
					summaryStatusPill.textContent = String(summary.status_hari_ini || 'Siap Absen');
				}
				if (summaryJamMasuk) {
					summaryJamMasuk.textContent = String(summary.jam_masuk || '-');
				}
				if (summaryJamPulang) {
					summaryJamPulang.textContent = String(summary.jam_pulang || '-');
				}
				if (summaryTargetPulang) {
					summaryTargetPulang.textContent = String(summary.target_pulang || '17:00');
				}
				if (summaryHadirBulan) {
					summaryHadirBulan.textContent = String(normalizeSummaryCount(summary.total_hadir_bulan_ini));
				}
				if (summaryTerlambatBulan) {
					summaryTerlambatBulan.textContent = String(normalizeSummaryCount(summary.total_terlambat_bulan_ini));
				}
				if (summaryIzinBulan) {
					summaryIzinBulan.textContent = String(normalizeSummaryCount(summary.total_izin_bulan_ini));
				}
			};

			var createHistoryCell = function (text) {
				var cell = document.createElement('td');
				cell.textContent = String(text || '-');
				return cell;
			};

			var renderHistoryRows = function (logs) {
				if (!historyTableBody) {
					return;
				}

				historyTableBody.innerHTML = '';
				if (!Array.isArray(logs) || logs.length === 0) {
					var emptyRow = document.createElement('tr');
					var emptyCell = document.createElement('td');
					emptyCell.colSpan = 4;
					emptyCell.textContent = 'Belum ada riwayat absensi.';
					emptyRow.appendChild(emptyCell);
					historyTableBody.appendChild(emptyRow);
					return;
				}

				for (var logIndex = 0; logIndex < logs.length; logIndex += 1) {
					var log = logs[logIndex] && typeof logs[logIndex] === 'object' ? logs[logIndex] : {};
					var row = document.createElement('tr');
					row.appendChild(createHistoryCell(log.tanggal || '-'));
					row.appendChild(createHistoryCell(log.masuk || '-'));
					row.appendChild(createHistoryCell(log.pulang || '-'));

					var statusCell = document.createElement('td');
					var statusBadge = document.createElement('span');
					var statusText = String(log.status || '-');
					var statusNormalized = statusText.toLowerCase();
					statusBadge.className = 'badge ' + (statusNormalized.indexOf('terlambat') !== -1 ? 'terlambat' : 'hadir');
					statusBadge.textContent = statusText;
					statusCell.appendChild(statusBadge);
					row.appendChild(statusCell);

					historyTableBody.appendChild(row);
				}
			};

			var resolveLoanStatusBadgeClass = function (statusText) {
				var normalized = String(statusText || '').toLowerCase();
				if (normalized.indexOf('terima') !== -1) {
					return 'diterima';
				}
				if (normalized.indexOf('tolak') !== -1) {
					return 'ditolak';
				}
				return 'menunggu';
			};

			var renderLoanRows = function (loans) {
				if (!loanHistoryTableBody) {
					return;
				}

				loanHistoryTableBody.innerHTML = '';
				if (!Array.isArray(loans) || loans.length === 0) {
					var emptyRow = document.createElement('tr');
					var emptyCell = document.createElement('td');
					emptyCell.colSpan = 5;
					emptyCell.textContent = 'Belum ada riwayat pinjaman.';
					emptyRow.appendChild(emptyCell);
					loanHistoryTableBody.appendChild(emptyRow);
					return;
				}

				for (var loanIndex = 0; loanIndex < loans.length; loanIndex += 1) {
					var loan = loans[loanIndex] && typeof loans[loanIndex] === 'object' ? loans[loanIndex] : {};
					var row = document.createElement('tr');
					row.appendChild(createHistoryCell(loan.tanggal || '-'));
					row.appendChild(createHistoryCell(loan.nominal || 'Rp 0'));
					row.appendChild(createHistoryCell(loan.tenor || '-'));
					row.appendChild(createHistoryCell(loan.cicilan_bulanan || 'Rp 0'));

					var statusCell = document.createElement('td');
					var statusBadge = document.createElement('span');
					var statusText = String(loan.status || 'Menunggu');
					statusBadge.className = 'badge ' + resolveLoanStatusBadgeClass(statusText);
					statusBadge.textContent = statusText;
					statusCell.appendChild(statusBadge);
					row.appendChild(statusCell);

					loanHistoryTableBody.appendChild(row);
				}
			};

			var fetchDashboardSummary = function () {
				return fetch(dashboardSummaryEndpoint, {
					method: 'GET',
					headers: {
						'X-Requested-With': 'XMLHttpRequest',
						'Cache-Control': 'no-cache'
					},
					cache: 'no-store'
				}).then(function (response) {
					return response.json().then(function (json) {
						if (!response.ok || !json || json.success !== true) {
							throw new Error(json && json.message ? json.message : 'Gagal memuat data dashboard.');
						}
						return json;
					});
				});
			};

			var refreshDashboardSummary = function (silent) {
				if (dashboardSummaryRequestInFlight) {
					return Promise.resolve(null);
				}

				dashboardSummaryRequestInFlight = true;
				return fetchDashboardSummary().then(function (result) {
					applyDashboardSummary(result.summary || {});
					renderHistoryRows(result.recent_logs || []);
					renderLoanRows(result.recent_loans || []);
					dashboardSummaryRequestInFlight = false;
					return result;
				}).catch(function (error) {
					dashboardSummaryRequestInFlight = false;
					if (silent !== true) {
						showToast(error.message || 'Gagal memuat data dashboard.');
					}
					return null;
				});
			};

			var stopDashboardSummaryPolling = function () {
				if (dashboardSummaryTimer !== null) {
					window.clearInterval(dashboardSummaryTimer);
					dashboardSummaryTimer = null;
				}
				dashboardSummaryRequestInFlight = false;
			};

			var startDashboardSummaryPolling = function () {
				stopDashboardSummaryPolling();
				refreshDashboardSummary(true);
				dashboardSummaryTimer = window.setInterval(function () {
					refreshDashboardSummary(true);
				}, 10000);
			};

			var updateCaptureAvailability = function () {
				if (!capturePhotoButton) {
					return;
				}
				var isValidAction = currentAction === 'masuk' || currentAction === 'pulang';
				var canCapture = !!mediaStream && !!currentPosition && currentPosition.geofenceInside === true && isValidAction;
				capturePhotoButton.disabled = !canCapture;
			};

			var syncBodyScrollLock = function () {
				var attendanceOpen = modal && modal.classList.contains('show');
				var requestOpen = requestModal && requestModal.classList.contains('show');
				var loanOpen = loanModal && loanModal.classList.contains('show');
				document.body.style.overflow = (attendanceOpen || requestOpen || loanOpen) ? 'hidden' : '';
			};

			var setModalState = function (isOpen) {
				if (isOpen) {
					modal.classList.add('show');
					modal.setAttribute('aria-hidden', 'false');
					syncBodyScrollLock();
					return;
				}

				modal.classList.remove('show');
				modal.setAttribute('aria-hidden', 'true');
				syncBodyScrollLock();
			};

			var setRequestModalState = function (isOpen) {
				if (isOpen) {
					requestModal.classList.add('show');
					requestModal.setAttribute('aria-hidden', 'false');
					syncBodyScrollLock();
					return;
				}

				requestModal.classList.remove('show');
				requestModal.setAttribute('aria-hidden', 'true');
				syncBodyScrollLock();
			};

			var setLoanModalState = function (isOpen) {
				if (!loanModal) {
					return;
				}
				if (isOpen) {
					loanModal.classList.add('show');
					loanModal.setAttribute('aria-hidden', 'false');
					syncBodyScrollLock();
					return;
				}

				loanModal.classList.remove('show');
				loanModal.setAttribute('aria-hidden', 'true');
				syncBodyScrollLock();
			};

			var formatAction = function (action) {
				if (action === 'masuk') {
					return 'Absen Masuk';
				}
				if (action === 'pulang') {
					return 'Absen Pulang';
				}
				return '-';
			};

			var setAttendanceAction = function (action) {
				var nextAction = String(action || '').toLowerCase();
				if (nextAction !== 'masuk' && nextAction !== 'pulang') {
					nextAction = '';
				}
				currentAction = nextAction;
				if (attendanceTypeSelect) {
					if (nextAction === '') {
						attendanceTypeSelect.selectedIndex = -1;
					}
					else {
						attendanceTypeSelect.value = nextAction;
					}
				}
				refreshLateReasonVisibility();
				updateCaptureAvailability();
			};

			var formatRequestType = function (requestType) {
				return requestType === 'cuti' ? 'Cuti' : 'Izin';
			};

			var getSupportFileExtension = function (fileName) {
				var name = String(fileName || '').toLowerCase().trim();
				var dotIndex = name.lastIndexOf('.');
				if (dotIndex <= 0 || dotIndex === name.length - 1) {
					return '';
				}
				return name.slice(dotIndex + 1);
			};

			var refreshRequestSupportConfig = function () {
				var requestTypeValue = requestTypeInput ? String(requestTypeInput.value || '').toLowerCase() : '';
				var izinTypeValue = izinTypeInput ? String(izinTypeInput.value || '').toLowerCase() : '';
				var isIzinType = requestTypeValue === 'izin';
				var isIzinSakit = isIzinType && izinTypeValue === 'sakit';
				var supportLabelText = 'Bukti (Opsional)';
				var supportHintText = 'Format yang diizinkan: .pdf, .png, .jpg, .heic';
				var reasonLabelText = isIzinType ? 'Alasan Izin' : 'Alasan Cuti';

				if (izinTypeWrap) {
					izinTypeWrap.classList.toggle('is-hidden', !isIzinType);
				}
				if (izinTypeInput) {
					izinTypeInput.required = isIzinType;
				}
				if (requestReasonLabel) {
					requestReasonLabel.textContent = reasonLabelText;
				}

				if (isIzinSakit) {
					supportLabelText = 'Surat Izin Sakit (Wajib)';
					supportHintText = 'Wajib upload surat izin sakit. Format: .pdf, .png, .jpg, .heic';
				}
				else if (isIzinType && izinTypeValue === 'darurat') {
					supportLabelText = 'Bukti Darurat (Opsional)';
					supportHintText = 'Bukti darurat opsional. Format: .pdf, .png, .jpg, .heic';
				}

				if (requestSupportLabel) {
					requestSupportLabel.textContent = supportLabelText;
				}
				if (requestSupportHint) {
					requestSupportHint.textContent = supportHintText;
				}
				if (requestSupportFile) {
					requestSupportFile.required = isIzinSakit;
				}
			};

			var formatDateForInput = function (dateObject) {
				var year = dateObject.getFullYear();
				var month = String(dateObject.getMonth() + 1).padStart(2, '0');
				var date = String(dateObject.getDate()).padStart(2, '0');
				return year + '-' + month + '-' + date;
			};

			var onlyDigits = function (value) {
				return String(value || '').replace(/\D+/g, '');
			};

			var formatNominal = function (value) {
				var digits = onlyDigits(value);
				if (digits === '') {
					return '';
				}
				return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
			};

			var normalizeLoanPrincipal = function (value) {
				var principalValue = parseInt(onlyDigits(value), 10);
				if (!isFinite(principalValue) || principalValue < 0) {
					return 0;
				}
				return principalValue;
			};

			var enforceLoanAmountLimit = function (forceMin) {
				if (!loanAmount) {
					return;
				}
				var shouldForceMin = !!forceMin;
				var minPrincipal = loanConfig && isFinite(Number(loanConfig.minPrincipal))
					? Number(loanConfig.minPrincipal)
					: 500000;
				var maxPrincipal = loanConfig && isFinite(Number(loanConfig.maxPrincipal))
					? Number(loanConfig.maxPrincipal)
					: 10000000;
				var principalValue = normalizeLoanPrincipal(loanAmount.value);
				if (principalValue <= 0) {
					loanAmount.value = '';
					return;
				}
				if (principalValue > maxPrincipal) {
					principalValue = maxPrincipal;
				}
				if (shouldForceMin && principalValue < minPrincipal) {
					principalValue = minPrincipal;
				}
				loanAmount.value = formatNominal(String(principalValue));
			};

			var formatRupiah = function (value) {
				var numericValue = Number(value || 0);
				if (!isFinite(numericValue)) {
					numericValue = 0;
				}
				return 'Rp ' + Math.round(numericValue).toLocaleString('id-ID');
			};

			var normalizeLoanTenor = function (value) {
				var tenorValue = parseInt(String(value || ''), 10);
				if (!isFinite(tenorValue)) {
					return 0;
				}
				return tenorValue;
			};

			var setLoanTenorMoreVisibility = function (show) {
				if (!tenorMoreWrap) {
					return;
				}
				var shouldShow = !!show;
				tenorMoreWrap.classList.toggle('is-hidden', !shouldShow);
				if (toggleMoreTenorButton) {
					toggleMoreTenorButton.hidden = shouldShow;
					toggleMoreTenorButton.setAttribute('aria-hidden', shouldShow ? 'true' : 'false');
					if (!shouldShow) {
						toggleMoreTenorButton.textContent = 'Lihat lainnya...';
					}
				}
			};

			var setSelectedLoanTenor = function (tenorValue) {
				var normalizedTenor = normalizeLoanTenor(tenorValue);
				if (!loanTenor) {
					return;
				}
				if (normalizedTenor < 1 || normalizedTenor > 12) {
					loanTenor.value = '';
				}
				else {
					loanTenor.value = String(normalizedTenor);
				}

				for (var buttonIndex = 0; buttonIndex < loanTenorButtons.length; buttonIndex += 1) {
					var tenorButton = loanTenorButtons[buttonIndex];
					var buttonTenor = normalizeLoanTenor(tenorButton.getAttribute('data-loan-tenor'));
					var isActive = normalizedTenor > 0 && buttonTenor === normalizedTenor;
					tenorButton.classList.toggle('is-active', isActive);
					tenorButton.setAttribute('aria-pressed', isActive ? 'true' : 'false');
				}

				if (normalizedTenor >= 5) {
					setLoanTenorMoreVisibility(true);
				}
				renderLoanDetail();
			};

			var calculateFlatLoanPreview = function (principal, tenorMonths, isFirstLoan) {
				if (!isFinite(principal) || principal <= 0 || !isFinite(tenorMonths) || tenorMonths <= 0) {
					return null;
				}
				var monthlyRatePercent = isFirstLoan ? 0 : 2.95;
				var monthlyInterestAmount = Math.round(principal * monthlyRatePercent / 100);
				var totalInterestAmount = monthlyInterestAmount * tenorMonths;
				var totalPayment = principal + totalInterestAmount;
				var monthlyInstallmentEstimate = Math.round(totalPayment / tenorMonths);
				var baseInstallment = Math.floor(totalPayment / tenorMonths);
				var remainder = totalPayment - (baseInstallment * tenorMonths);
				var installments = [];
				for (var monthIndex = 1; monthIndex <= tenorMonths; monthIndex += 1) {
					var installmentAmount = baseInstallment;
					if (monthIndex === tenorMonths && remainder > 0) {
						installmentAmount += remainder;
					}
					installments.push({
						month: monthIndex,
						amount: installmentAmount
					});
				}

				return {
					principal: principal,
					tenorMonths: tenorMonths,
					isFirstLoan: !!isFirstLoan,
					monthlyRatePercent: monthlyRatePercent,
					monthlyInterestAmount: monthlyInterestAmount,
					totalInterestAmount: totalInterestAmount,
					totalPayment: totalPayment,
					monthlyInstallmentEstimate: monthlyInstallmentEstimate,
					installments: installments
				};
			};

			var renderLoanDetail = function () {
				if (!loanDetailCard || !loanDetailContent) {
					return;
				}

				var minPrincipal = loanConfig && isFinite(Number(loanConfig.minPrincipal)) ? Number(loanConfig.minPrincipal) : 500000;
				var maxPrincipal = loanConfig && isFinite(Number(loanConfig.maxPrincipal)) ? Number(loanConfig.maxPrincipal) : 10000000;
				var minTenor = loanConfig && isFinite(Number(loanConfig.minTenorMonths)) ? Number(loanConfig.minTenorMonths) : 1;
				var maxTenor = loanConfig && isFinite(Number(loanConfig.maxTenorMonths)) ? Number(loanConfig.maxTenorMonths) : 12;
				var isFirstLoan = !!(loanConfig && loanConfig.isFirstLoan);
				var amountDigits = loanAmount ? onlyDigits(loanAmount.value) : '';
				var principal = amountDigits === '' ? 0 : parseInt(amountDigits, 10);
				var tenorMonths = loanTenor ? normalizeLoanTenor(loanTenor.value) : 0;

				if (principal < minPrincipal || principal > maxPrincipal || tenorMonths < minTenor || tenorMonths > maxTenor) {
					loanDetailCard.hidden = true;
					loanDetailContent.hidden = true;
					return;
				}

				var loanPreview = calculateFlatLoanPreview(principal, tenorMonths, isFirstLoan);
				if (!loanPreview) {
					loanDetailCard.hidden = true;
					loanDetailContent.hidden = true;
					return;
				}

				loanDetailCard.hidden = false;
				loanDetailContent.hidden = false;

				if (loanNominalValue) {
					loanNominalValue.textContent = formatRupiah(loanPreview.principal);
				}
				if (loanTenorValue) {
					loanTenorValue.textContent = loanPreview.tenorMonths + ' bulan';
				}
				if (loanStatusValue) {
					loanStatusValue.textContent = loanPreview.isFirstLoan
						? 'Pinjaman pertama akun (0% bunga)'
						: 'Pinjaman lanjutan akun (bunga berlaku)';
				}
				if (loanRateValue) {
					loanRateValue.textContent = loanPreview.monthlyRatePercent.toFixed(2).replace('.', ',') + '% per bulan';
				}
				if (loanInterestValue) {
					loanInterestValue.textContent = formatRupiah(loanPreview.monthlyInterestAmount);
				}
				if (loanMonthlyInstallmentValue) {
					loanMonthlyInstallmentValue.textContent = formatRupiah(loanPreview.monthlyInstallmentEstimate);
				}
				if (loanTotalValue) {
					loanTotalValue.textContent = formatRupiah(loanPreview.totalPayment);
				}
			};

			var resetLoanDetailCard = function () {
				if (loanDetailCard) {
					loanDetailCard.hidden = true;
				}
				if (loanDetailContent) {
					loanDetailContent.hidden = true;
				}
				if (loanNominalValue) {
					loanNominalValue.textContent = 'Rp 0';
				}
				if (loanTenorValue) {
					loanTenorValue.textContent = '0 bulan';
				}
				if (loanStatusValue) {
					loanStatusValue.textContent = '-';
				}
				if (loanRateValue) {
					loanRateValue.textContent = '0,00% per bulan';
				}
				if (loanInterestValue) {
					loanInterestValue.textContent = 'Rp 0';
				}
				if (loanMonthlyInstallmentValue) {
					loanMonthlyInstallmentValue.textContent = 'Rp 0';
				}
				if (loanTotalValue) {
					loanTotalValue.textContent = 'Rp 0';
				}
			};

			var formatNow = function () {
				return new Intl.DateTimeFormat('id-ID', {
					weekday: 'long',
					day: '2-digit',
					month: 'long',
					year: 'numeric',
					hour: '2-digit',
					minute: '2-digit',
					second: '2-digit',
					hour12: false
				}).format(new Date());
			};

			var calculateDistanceMeter = function (lat1, lng1, lat2, lng2) {
				var earthRadius = 6371000;
				var toRad = function (deg) {
					return deg * (Math.PI / 180);
				};
				var dLat = toRad(lat2 - lat1);
				var dLng = toRad(lng2 - lng1);
				var lat1Rad = toRad(lat1);
				var lat2Rad = toRad(lat2);

				var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
					Math.cos(lat1Rad) * Math.cos(lat2Rad) *
					Math.sin(dLng / 2) * Math.sin(dLng / 2);
				var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
				return earthRadius * c;
			};

			var evaluateGeofence = function (distanceM, accuracyM) {
				if (distanceM <= officeRadiusM && accuracyM <= maxAccuracyM) {
					return {
						inside: true,
						label: 'Valid (akurasi baik)',
						message: ''
					};
				}

				if ((distanceM + accuracyM) <= officeRadiusM) {
					return {
						inside: true,
						label: 'Valid (toleransi akurasi)',
						message: ''
					};
				}

				if ((distanceM - accuracyM) > officeRadiusM) {
					return {
						inside: false,
						label: 'Di luar radius',
						message: 'Lokasi di luar radius kantor. Jarak kamu ' + distanceM.toFixed(2) + 'm (maks ' + officeRadiusM + 'm).'
					};
				}

				return {
					inside: false,
					label: 'Akurasi belum cukup',
					message: 'Posisi GPS belum cukup akurat (jarak ' + distanceM.toFixed(2) + 'm, akurasi ' + Math.round(accuracyM) + 'm). Coba aktifkan lokasi akurat lalu ulangi.'
				};
			};

			var extractShiftStartSeconds = function () {
				var matches = String(shiftTimeText || '').match(/(\d{2}):(\d{2})/);
				if (!matches) {
					return 8 * 3600;
				}
				return (parseInt(matches[1], 10) * 3600) + (parseInt(matches[2], 10) * 60);
			};

			var getCurrentLateSeconds = function () {
				if (currentAction !== 'masuk') {
					return 0;
				}
				var nowSeconds = 0;
				try {
					var parts = new Intl.DateTimeFormat('en-GB', {
						timeZone: 'Asia/Jakarta',
						hour: '2-digit',
						minute: '2-digit',
						second: '2-digit',
						hour12: false
					}).formatToParts(new Date());
					var hour = 0;
					var minute = 0;
					var second = 0;
					for (var i = 0; i < parts.length; i += 1) {
						if (parts[i].type === 'hour') {
							hour = parseInt(parts[i].value, 10);
						}
						else if (parts[i].type === 'minute') {
							minute = parseInt(parts[i].value, 10);
						}
						else if (parts[i].type === 'second') {
							second = parseInt(parts[i].value, 10);
						}
					}
					nowSeconds = (hour * 3600) + (minute * 60) + second;
				}
				catch (error) {
					var now = new Date();
					nowSeconds = (now.getHours() * 3600) + (now.getMinutes() * 60) + now.getSeconds();
				}
				var shiftStart = extractShiftStartSeconds();
				if (nowSeconds <= shiftStart) {
					return 0;
				}
				return nowSeconds - shiftStart;
			};

			var refreshLateReasonVisibility = function () {
				if (!lateReasonWrap || !lateReasonInput) {
					return;
				}
				var lateSeconds = getCurrentLateSeconds();
				var shouldShow = currentAction === 'masuk' && lateSeconds > 0;
				lateReasonWrap.classList.toggle('show', shouldShow);
				lateReasonInput.required = shouldShow;
				if (!shouldShow) {
					lateReasonInput.value = '';
				}
			};

			var stopCamera = function () {
				if (!mediaStream) {
					return;
				}
				var tracks = mediaStream.getTracks();
				for (var i = 0; i < tracks.length; i += 1) {
					tracks[i].stop();
				}
				mediaStream = null;
				cameraPreview.srcObject = null;
				updateCaptureAvailability();
			};

			var stopGpsPolling = function () {
				if (gpsRefreshTimer !== null) {
					window.clearInterval(gpsRefreshTimer);
					gpsRefreshTimer = null;
				}
				gpsRequestInFlight = false;
			};

			var stopModalClock = function () {
				if (modalClockTimer !== null) {
					window.clearInterval(modalClockTimer);
					modalClockTimer = null;
				}
			};

			var startModalClock = function () {
				stopModalClock();
				timeValue.textContent = formatNow();
				refreshLateReasonVisibility();
				modalClockTimer = window.setInterval(function () {
					timeValue.textContent = formatNow();
					refreshLateReasonVisibility();
				}, 1000);
			};

			var setCameraPlaceholder = function (message, isVisible) {
				if (!cameraPlaceholder) {
					return;
				}
				cameraPlaceholder.textContent = message;
				cameraPlaceholder.style.display = isVisible ? 'flex' : 'none';
			};

			var loadCameraDevices = function () {
				if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices || !cameraSelect) {
					return Promise.resolve();
				}

				return navigator.mediaDevices.enumerateDevices().then(function (devices) {
					var cameras = devices.filter(function (device) {
						return device.kind === 'videoinput';
					});

					cameraSelect.innerHTML = '';
					if (!cameras.length) {
						var noOption = document.createElement('option');
						noOption.value = '';
						noOption.textContent = 'Kamera tidak ditemukan';
						cameraSelect.appendChild(noOption);
						return;
					}

					for (var index = 0; index < cameras.length; index += 1) {
						var option = document.createElement('option');
						option.value = cameras[index].deviceId;
						option.textContent = cameras[index].label || ('Camera ' + (index + 1));
						cameraSelect.appendChild(option);
					}

					if (selectedCameraId !== '') {
						cameraSelect.value = selectedCameraId;
					}
					else {
						selectedCameraId = cameraSelect.value;
					}
				});
			};

			var startCamera = function (deviceId) {
				if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
					return Promise.reject(new Error('Browser tidak mendukung akses kamera.'));
				}

				stopCamera();
				setCameraPlaceholder('Meminta akses kamera...', true);
				capturePhotoButton.disabled = true;

				var constraints = {
					video: deviceId ? { deviceId: { exact: deviceId } } : { facingMode: 'user' },
					audio: false
				};

				return navigator.mediaDevices.getUserMedia(constraints).then(function (stream) {
					mediaStream = stream;
					cameraPreview.srcObject = stream;
					cameraPreview.style.transform = shouldUnmirrorPreview ? 'scaleX(-1)' : 'none';
					setCameraPlaceholder('Kamera aktif.', false);
					updateCaptureAvailability();

					var tracks = stream.getVideoTracks();
					if (tracks.length && tracks[0].getSettings().deviceId) {
						selectedCameraId = tracks[0].getSettings().deviceId;
					}

					return loadCameraDevices();
				});
			};

			var applyGpsPosition = function (position) {
				var distanceM = calculateDistanceMeter(position.coords.latitude, position.coords.longitude, officeLat, officeLng);
				var geofenceStatus = evaluateGeofence(distanceM, position.coords.accuracy);
				currentPosition = {
					lat: position.coords.latitude,
					lng: position.coords.longitude,
					accuracy: position.coords.accuracy,
					distance: distanceM,
					geofenceInside: geofenceStatus.inside,
					geofenceMessage: geofenceStatus.message
				};

				gpsValue.textContent = currentPosition.lat.toFixed(6) + ', ' + currentPosition.lng.toFixed(6) + ' | Jarak ' + distanceM.toFixed(2) + 'm dari titik kantor | Akurasi ' + Math.round(currentPosition.accuracy) + 'm | ' + geofenceStatus.label;
				updateCaptureAvailability();
				return geofenceStatus;
			};

			var mapGpsError = function (error) {
				if (!error || typeof error.code !== 'number') {
					return 'Gagal membaca GPS.';
				}
				if (error.code === error.TIMEOUT) {
					return 'Permintaan GPS timeout.';
				}
				if (error.code === error.POSITION_UNAVAILABLE) {
					return 'Lokasi tidak tersedia.';
				}
				if (error.code === error.PERMISSION_DENIED) {
					return 'Izin lokasi ditolak.';
				}
				return 'Gagal membaca GPS.';
			};

			var fetchGpsSnapshot = function () {
				if (!navigator.geolocation) {
					return Promise.reject(new Error('Browser tidak mendukung GPS.'));
				}
				if (gpsRequestInFlight) {
					return Promise.resolve(null);
				}

				gpsRequestInFlight = true;
				return new Promise(function (resolve, reject) {
					navigator.geolocation.getCurrentPosition(function (position) {
						gpsRequestInFlight = false;
						resolve(applyGpsPosition(position));
					}, function (error) {
						gpsRequestInFlight = false;
						currentPosition = null;
						updateCaptureAvailability();
						var message = mapGpsError(error);
						gpsValue.textContent = message;
						reject(new Error(message));
					}, {
						enableHighAccuracy: true,
						timeout: 10000,
						maximumAge: 0
					});
				});
			};

			var startGpsPolling = function () {
				if (!navigator.geolocation) {
					return Promise.reject(new Error('Browser tidak mendukung GPS.'));
				}

				stopGpsPolling();
				currentPosition = null;
				updateCaptureAvailability();
				gpsValue.textContent = 'Memulai GPS realtime (refresh 1 detik)...';

				return new Promise(function (resolve, reject) {
					var firstFixHandled = false;

					fetchGpsSnapshot().then(function () {
						if (!firstFixHandled) {
							firstFixHandled = true;
							resolve();
						}
					}).catch(function (error) {
						if (!firstFixHandled) {
							firstFixHandled = true;
							reject(error);
						}
					});

					gpsRefreshTimer = window.setInterval(function () {
						fetchGpsSnapshot().catch(function () {
							// Keep polling even when one request fails.
						});
					}, 1000);
				});
			};

			var resetModalState = function () {
				currentPosition = null;
				setAttendanceAction('');
				if (captureResult) {
					captureResult.classList.remove('show');
				}
				if (capturedImage) {
					capturedImage.src = '';
				}
				if (resultMeta) {
					resultMeta.textContent = '';
				}
				gpsValue.textContent = 'Meminta lokasi...';
				timeValue.textContent = '-';
			};

			var openAttendanceModal = function () {
				if (requestModal.classList.contains('show')) {
					setRequestModalState(false);
				}
				if (loanModal && loanModal.classList.contains('show')) {
					setLoanModalState(false);
				}
				resetModalState();
				setModalState(true);
				startModalClock();

				Promise.allSettled([startGpsPolling(), startCamera(selectedCameraId)]).then(function (results) {
					if (results[0].status === 'rejected') {
						capturePhotoButton.disabled = true;
						showToast(results[0].reason.message);
					}
					else if (currentPosition && currentPosition.geofenceInside !== true && currentPosition.geofenceMessage) {
						showToast(currentPosition.geofenceMessage);
					}
					if (results[1].status === 'rejected') {
						setCameraPlaceholder(results[1].reason.message, true);
						showToast(results[1].reason.message);
					}
					updateCaptureAvailability();
				});
			};

			var closeAttendanceModal = function () {
				stopGpsPolling();
				stopModalClock();
				stopCamera();
				setCameraPlaceholder('Meminta akses kamera...', true);
				capturePhotoButton.disabled = true;
				setModalState(false);
			};

			var openRequestModal = function (requestType) {
				var typeValue = String(requestType || '').toLowerCase();
				if (typeValue !== 'cuti' && typeValue !== 'izin') {
					showToast('Jenis pengajuan tidak valid.');
					return;
				}

				if (modal.classList.contains('show')) {
					closeAttendanceModal();
				}
				if (loanModal && loanModal.classList.contains('show')) {
					closeLoanModal();
				}

				var today = formatDateForInput(new Date());
				if (requestTypeInput) {
					requestTypeInput.value = typeValue;
				}
				if (requestTypeLabel) {
					requestTypeLabel.textContent = formatRequestType(typeValue);
				}
				if (izinTypeInput) {
					izinTypeInput.selectedIndex = -1;
				}
				if (requestStartDate) {
					requestStartDate.value = today;
				}
				if (requestEndDate) {
					requestEndDate.value = today;
				}
				if (requestReason) {
					requestReason.value = '';
				}
				if (requestSupportFile) {
					requestSupportFile.value = '';
				}
				refreshRequestSupportConfig();
				if (submitLeaveRequestButton) {
					submitLeaveRequestButton.disabled = false;
					submitLeaveRequestButton.textContent = defaultLeaveSubmitText;
				}

				setRequestModalState(true);
				if (typeValue === 'izin' && izinTypeInput) {
					izinTypeInput.focus();
				}
				else if (requestReason) {
					requestReason.focus();
				}
			};

			var openLoanModal = function () {
				if (modal.classList.contains('show')) {
					closeAttendanceModal();
				}
				if (requestModal.classList.contains('show')) {
					closeRequestModal();
				}

				if (loanAmount) {
					loanAmount.value = '';
				}
				if (loanTenor) {
					loanTenor.value = '';
				}
				setLoanTenorMoreVisibility(false);
				setSelectedLoanTenor('');
				if (loanReason) {
					loanReason.value = '';
				}
				resetLoanDetailCard();
				if (submitLoanRequestButton) {
					submitLoanRequestButton.disabled = false;
					submitLoanRequestButton.textContent = defaultLoanSubmitText;
				}
				renderLoanDetail();

				setLoanModalState(true);
				if (loanAmount) {
					loanAmount.focus();
				}
			};

			var closeRequestModal = function () {
				if (leaveRequestForm) {
					leaveRequestForm.reset();
				}
				if (requestTypeInput) {
					requestTypeInput.value = '';
				}
				if (requestTypeLabel) {
					requestTypeLabel.textContent = '-';
				}
				if (izinTypeInput) {
					izinTypeInput.selectedIndex = -1;
				}
				if (requestSupportFile) {
					requestSupportFile.value = '';
				}
				refreshRequestSupportConfig();
				if (submitLeaveRequestButton) {
					submitLeaveRequestButton.disabled = false;
					submitLeaveRequestButton.textContent = defaultLeaveSubmitText;
				}
				setRequestModalState(false);
			};

			var closeLoanModal = function () {
				if (loanRequestForm) {
					loanRequestForm.reset();
				}
				if (loanAmount) {
					loanAmount.value = '';
				}
				if (loanTenor) {
					loanTenor.value = '';
				}
				setLoanTenorMoreVisibility(false);
				setSelectedLoanTenor('');
				if (loanReason) {
					loanReason.value = '';
				}
				resetLoanDetailCard();
				if (submitLoanRequestButton) {
					submitLoanRequestButton.disabled = false;
					submitLoanRequestButton.textContent = defaultLoanSubmitText;
				}
				setLoanModalState(false);
			};

			var submitLeaveRequest = function (formData) {
				return fetch(leaveRequestEndpoint, {
					method: 'POST',
					body: formData
				}).then(function (response) {
					return response.json().then(function (json) {
						if (!response.ok || !json.success) {
							throw new Error(json.message || 'Gagal mengirim pengajuan.');
						}
						return json;
					});
				});
			};

			var submitLoanRequest = function (formData) {
				return fetch(loanRequestEndpoint, {
					method: 'POST',
					body: formData
				}).then(function (response) {
					return response.json().then(function (json) {
						if (!response.ok || !json.success) {
							throw new Error(json.message || 'Gagal mengirim pengajuan pinjaman.');
						}
						return json;
					});
				});
			};

			var submitAttendance = function (photoData) {
				if (currentAction !== 'masuk' && currentAction !== 'pulang') {
					return Promise.reject(new Error('Pilih jenis absensi (masuk/pulang) terlebih dahulu.'));
				}
				var formData = new FormData();
				var lateReasonValue = lateReasonInput ? lateReasonInput.value.trim() : '';
				formData.append('action', currentAction);
				formData.append('photo', photoData);
				formData.append('latitude', String(currentPosition.lat));
				formData.append('longitude', String(currentPosition.lng));
				formData.append('accuracy', String(currentPosition.accuracy));
				formData.append('late_reason', lateReasonValue);

				return fetch(submitEndpoint, {
					method: 'POST',
					body: formData
				}).then(function (response) {
					return response.json().then(function (json) {
						if (!response.ok || !json.success) {
							throw new Error(json.message || 'Gagal menyimpan absensi.');
						}
						return json;
					});
				});
			};

			for (var i = 0; i < actionButtons.length; i += 1) {
				actionButtons[i].addEventListener('click', function () {
					openAttendanceModal();
				});
			}

			for (var requestIndex = 0; requestIndex < requestButtons.length; requestIndex += 1) {
				requestButtons[requestIndex].addEventListener('click', function () {
					openRequestModal(this.getAttribute('data-request-type'));
				});
			}

			for (var loanIndex = 0; loanIndex < loanButtons.length; loanIndex += 1) {
				loanButtons[loanIndex].addEventListener('click', function () {
					openLoanModal();
				});
			}

			if (overlayClose) {
				overlayClose.addEventListener('click', closeAttendanceModal);
			}

			if (closeModalButton) {
				closeModalButton.addEventListener('click', closeAttendanceModal);
			}

			if (requestOverlayClose) {
				requestOverlayClose.addEventListener('click', closeRequestModal);
			}

			if (closeRequestModalButton) {
				closeRequestModalButton.addEventListener('click', closeRequestModal);
			}

			if (loanOverlayClose) {
				loanOverlayClose.addEventListener('click', closeLoanModal);
			}

			if (closeLoanModalButton) {
				closeLoanModalButton.addEventListener('click', closeLoanModal);
			}

			if (cameraSelect) {
				cameraSelect.addEventListener('change', function () {
					selectedCameraId = cameraSelect.value;
					startCamera(selectedCameraId).catch(function (error) {
						setCameraPlaceholder(error.message, true);
						showToast(error.message);
					});
				});
			}

			if (attendanceTypeSelect) {
				attendanceTypeSelect.addEventListener('change', function () {
					setAttendanceAction(attendanceTypeSelect.value);
				});
			}

			if (lateReasonInput) {
				lateReasonInput.addEventListener('input', function () {
					updateCaptureAvailability();
				});
			}

			if (loanAmount) {
				loanAmount.addEventListener('input', function () {
					enforceLoanAmountLimit(false);
					renderLoanDetail();
				});
				loanAmount.addEventListener('blur', function () {
					enforceLoanAmountLimit(true);
					renderLoanDetail();
				});
			}

			if (toggleMoreTenorButton) {
				toggleMoreTenorButton.addEventListener('click', function () {
					var isHidden = tenorMoreWrap ? tenorMoreWrap.classList.contains('is-hidden') : true;
					setLoanTenorMoreVisibility(isHidden);
				});
			}

			for (var tenorButtonIndex = 0; tenorButtonIndex < loanTenorButtons.length; tenorButtonIndex += 1) {
				(function (buttonElement) {
					buttonElement.addEventListener('click', function () {
						setSelectedLoanTenor(buttonElement.getAttribute('data-loan-tenor'));
					});
				})(loanTenorButtons[tenorButtonIndex]);
			}

			if (izinTypeInput) {
				izinTypeInput.addEventListener('change', function () {
					refreshRequestSupportConfig();
				});
			}

			if (capturePhotoButton) {
				capturePhotoButton.addEventListener('click', function () {
					if (currentAction !== 'masuk' && currentAction !== 'pulang') {
						showToast('Pilih jenis absensi (masuk/pulang) terlebih dahulu.');
						if (attendanceTypeSelect) {
							attendanceTypeSelect.focus();
						}
						return;
					}
					if (!mediaStream) {
						showToast('Kamera belum aktif. Izinkan akses kamera terlebih dahulu.');
						return;
					}
					if (!currentPosition) {
						showToast('Lokasi GPS belum tersedia. Izinkan akses lokasi terlebih dahulu.');
						return;
					}
					var currentGeofenceStatus = evaluateGeofence(currentPosition.distance, currentPosition.accuracy);
					if (!currentGeofenceStatus.inside) {
						showToast(currentGeofenceStatus.message);
						return;
					}
					var lateSecondsNow = getCurrentLateSeconds();
					var reasonValue = lateReasonInput ? lateReasonInput.value.trim() : '';
					if (currentAction === 'masuk' && lateSecondsNow > 0 && reasonValue === '') {
						showToast('Kamu telat masuk. Alasan keterlambatan wajib diisi.');
						if (lateReasonInput) {
							lateReasonInput.focus();
						}
						return;
					}
					if (!cameraPreview.videoWidth || !cameraPreview.videoHeight) {
						showToast('Preview kamera belum siap.');
						return;
					}

					var sourceWidth = cameraPreview.videoWidth;
					var sourceHeight = cameraPreview.videoHeight;
					var targetSize = Math.max(sourceWidth, sourceHeight);
					var drawWidth = sourceWidth;
					var drawHeight = sourceHeight;
					var offsetX = (targetSize - drawWidth) / 2;
					var offsetY = (targetSize - drawHeight) / 2;

					captureCanvas.width = targetSize;
					captureCanvas.height = targetSize;
					var context = captureCanvas.getContext('2d');
					context.fillStyle = '#132b4a';
					context.fillRect(0, 0, targetSize, targetSize);

					if (shouldUnmirrorCapture) {
						context.save();
						context.translate(offsetX + drawWidth, offsetY);
						context.scale(-1, 1);
						context.drawImage(cameraPreview, 0, 0, sourceWidth, sourceHeight, 0, 0, drawWidth, drawHeight);
						context.restore();
					}
					else {
						context.drawImage(cameraPreview, 0, 0, sourceWidth, sourceHeight, offsetX, offsetY, drawWidth, drawHeight);
					}
					var imageData = captureCanvas.toDataURL('image/jpeg', 0.92);
					capturedImage.src = imageData;
					captureResult.classList.add('show');
					var shiftText = shiftValue ? shiftValue.textContent : '-';
					resultMeta.textContent = formatAction(currentAction) + ' | ' + shiftText + ' | Jarak ' + currentPosition.distance.toFixed(2) + 'm | ' + currentPosition.lat.toFixed(6) + ', ' + currentPosition.lng.toFixed(6) + ' | ' + formatNow();

					submitAttendance(imageData).then(function (result) {
						resultMeta.textContent = resultMeta.textContent + ' | Tersimpan';
						refreshDashboardSummary(true);
						showToast(result.message || 'Absensi berhasil disimpan.');
					}).catch(function (error) {
						showToast(error.message);
					});
				});
			}

			if (leaveRequestForm) {
				leaveRequestForm.addEventListener('submit', function (event) {
					event.preventDefault();

					var requestTypeValue = requestTypeInput ? String(requestTypeInput.value || '').toLowerCase() : '';
					var izinTypeValue = izinTypeInput ? String(izinTypeInput.value || '').toLowerCase() : '';
					var startDateValue = requestStartDate ? String(requestStartDate.value || '') : '';
					var endDateValue = requestEndDate ? String(requestEndDate.value || '') : '';
					var reasonValue = requestReason ? requestReason.value.trim() : '';
					var supportFile = requestSupportFile && requestSupportFile.files && requestSupportFile.files.length
						? requestSupportFile.files[0]
						: null;

					if (requestTypeValue !== 'cuti' && requestTypeValue !== 'izin') {
						showToast('Jenis pengajuan tidak valid.');
						return;
					}
					if (requestTypeValue === 'izin' && izinTypeValue !== 'sakit' && izinTypeValue !== 'darurat') {
						showToast('Pilih jenis izin terlebih dahulu.');
						if (izinTypeInput) {
							izinTypeInput.focus();
						}
						return;
					}
					if (startDateValue === '' || endDateValue === '') {
						showToast('Tanggal mulai dan selesai wajib diisi.');
						return;
					}
					if (endDateValue < startDateValue) {
						showToast('Tanggal selesai tidak boleh lebih kecil dari tanggal mulai.');
						return;
					}
					if (reasonValue === '') {
						showToast('Alasan pengajuan wajib diisi.');
						if (requestReason) {
							requestReason.focus();
						}
						return;
					}

					if (supportFile) {
						var supportExt = getSupportFileExtension(supportFile.name);
						if (!allowedSupportExtensions[supportExt]) {
							showToast('Format bukti harus .pdf, .png, .jpg, atau .heic.');
							return;
						}
					}

					if (requestTypeValue === 'izin' && izinTypeValue === 'sakit' && !supportFile) {
						showToast('Surat izin sakit wajib diupload.');
						if (requestSupportFile) {
							requestSupportFile.focus();
						}
						return;
					}

					var formData = new FormData();
					formData.append('request_type', requestTypeValue);
					formData.append('izin_type', izinTypeValue);
					formData.append('start_date', startDateValue);
					formData.append('end_date', endDateValue);
					formData.append('reason', reasonValue);
					if (supportFile) {
						formData.append('support_file', supportFile);
					}

					if (submitLeaveRequestButton) {
						submitLeaveRequestButton.disabled = true;
						submitLeaveRequestButton.textContent = 'Mengirim...';
					}

					submitLeaveRequest(formData).then(function (result) {
						showToast(result.message || 'Pengajuan berhasil dikirim.');
						closeRequestModal();
					}).catch(function (error) {
						showToast(error.message);
						if (submitLeaveRequestButton) {
							submitLeaveRequestButton.disabled = false;
							submitLeaveRequestButton.textContent = defaultLeaveSubmitText;
						}
					});
				});
			}

			if (loanRequestForm) {
				loanRequestForm.addEventListener('submit', function (event) {
					event.preventDefault();

					enforceLoanAmountLimit(true);
					var amountDigits = loanAmount ? onlyDigits(loanAmount.value) : '';
					var amountValue = amountDigits === '' ? 0 : parseInt(amountDigits, 10);
					var tenorValue = loanTenor ? normalizeLoanTenor(loanTenor.value) : 0;
					var reasonValue = loanReason ? loanReason.value.trim() : '';
					var minPrincipal = loanConfig && isFinite(Number(loanConfig.minPrincipal)) ? Number(loanConfig.minPrincipal) : 500000;
					var maxPrincipal = loanConfig && isFinite(Number(loanConfig.maxPrincipal)) ? Number(loanConfig.maxPrincipal) : 10000000;

					if (amountValue < minPrincipal || amountValue > maxPrincipal) {
						showToast('Nominal pinjaman harus antara Rp 500.000 sampai Rp 10.000.000.');
						if (loanAmount) {
							loanAmount.focus();
						}
						return;
					}
					if (tenorValue < 1 || tenorValue > 12) {
						showToast('Tenor pinjaman harus antara 1 sampai 12 bulan.');
						if (loanTenorButtons.length > 0) {
							loanTenorButtons[0].focus();
						}
						else if (toggleMoreTenorButton) {
							toggleMoreTenorButton.focus();
						}
						return;
					}
					if (reasonValue === '') {
						showToast('Alasan pinjaman wajib diisi.');
						if (loanReason) {
							loanReason.focus();
						}
						return;
					}

					var formData = new FormData();
					formData.append('amount', String(amountValue));
					formData.append('tenor_months', String(tenorValue));
					formData.append('reason', reasonValue);

					if (submitLoanRequestButton) {
						submitLoanRequestButton.disabled = true;
						submitLoanRequestButton.textContent = 'Mengirim...';
					}

					submitLoanRequest(formData).then(function (result) {
						if (loanConfig) {
							loanConfig.isFirstLoan = false;
						}
						showToast(result.message || 'Pengajuan pinjaman berhasil dikirim.');
						closeLoanModal();
					}).catch(function (error) {
						showToast(error.message);
						if (submitLoanRequestButton) {
							submitLoanRequestButton.disabled = false;
							submitLoanRequestButton.textContent = defaultLoanSubmitText;
						}
					});
				});
			}

			startDashboardSummaryPolling();

			document.addEventListener('keydown', function (event) {
				if (event.key !== 'Escape') {
					return;
				}

				if (requestModal.classList.contains('show')) {
					closeRequestModal();
				}

				if (loanModal && loanModal.classList.contains('show')) {
					closeLoanModal();
				}

				if (modal.classList.contains('show')) {
					closeAttendanceModal();
				}
			});

			window.addEventListener('beforeunload', function () {
				stopDashboardSummaryPolling();
				stopGpsPolling();
				stopModalClock();
				stopCamera();
			});
		})();
	</script>
</body>
</html>
