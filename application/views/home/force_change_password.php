<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$username = isset($username) ? (string) $username : 'user';
$password_notice_success = isset($password_notice_success) ? trim((string) $password_notice_success) : '';
$password_notice_error = isset($password_notice_error) ? trim((string) $password_notice_error) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Ganti Password'; ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
	<style>
		* { box-sizing: border-box; }
		body {
			margin: 0;
			font-family: 'Plus Jakarta Sans', sans-serif;
			background: linear-gradient(180deg, #f0f8ff 0%, #ffffff 100%);
			color: #0d2238;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 1rem;
		}
		.card {
			width: min(560px, 100%);
			background: #ffffff;
			border: 1px solid #dbe7f3;
			border-radius: 16px;
			padding: 1.2rem;
			box-shadow: 0 14px 32px rgba(8, 60, 104, 0.12);
		}
		h1 {
			margin: 0 0 0.4rem;
			font-size: 1.24rem;
			font-weight: 800;
			line-height: 1.3;
		}
		p {
			margin: 0 0 1rem;
			font-size: 0.9rem;
			color: #4d637a;
			line-height: 1.5;
		}
		.notice {
			margin-bottom: 0.9rem;
			padding: 0.64rem 0.75rem;
			border-radius: 10px;
			font-size: 0.82rem;
			font-weight: 600;
		}
		.notice.success {
			background: #e9f8f0;
			border: 1px solid #bde8cf;
			color: #176e49;
		}
		.notice.error {
			background: #fff2f2;
			border: 1px solid #f3cccc;
			color: #a63c3c;
		}
		label {
			display: block;
			margin-bottom: 0.3rem;
			font-size: 0.82rem;
			font-weight: 700;
			color: #1b3f62;
		}
		input {
			width: 100%;
			border: 1px solid #c7d9ea;
			border-radius: 10px;
			padding: 0.68rem 0.78rem;
			font-size: 0.92rem;
			outline: none;
			margin-bottom: 0.8rem;
		}
		input:focus {
			border-color: #1f6fbd;
			box-shadow: 0 0 0 3px rgba(31, 111, 189, 0.14);
		}
		.actions {
			display: flex;
			gap: 0.6rem;
			flex-wrap: wrap;
			align-items: center;
		}
		.btn {
			border: 0;
			border-radius: 999px;
			padding: 0.65rem 1rem;
			font-size: 0.86rem;
			font-weight: 700;
			text-decoration: none;
			cursor: pointer;
		}
		.btn.primary {
			background: linear-gradient(180deg, #1f6fbd 0%, #0f5c93 100%);
			color: #ffffff;
		}
		.btn.secondary {
			background: #eff5fc;
			color: #1a4e7b;
			border: 1px solid #c8dbee;
		}
	</style>
</head>
<body>
	<section class="card">
		<h1>Halo, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>. Ganti password dulu.</h1>
		<p>Untuk keamanan akun, password wajib diganti sebelum melanjutkan ke dashboard.</p>
		<?php if ($password_notice_success !== ''): ?>
			<div class="notice success"><?php echo htmlspecialchars($password_notice_success, ENT_QUOTES, 'UTF-8'); ?></div>
		<?php endif; ?>
		<?php if ($password_notice_error !== ''): ?>
			<div class="notice error"><?php echo htmlspecialchars($password_notice_error, ENT_QUOTES, 'UTF-8'); ?></div>
		<?php endif; ?>

		<form method="post" action="<?php echo site_url('home/submit_force_change_password'); ?>">
			<label for="currentPasswordInput">Password Saat Ini</label>
			<input type="password" id="currentPasswordInput" name="current_password" autocomplete="current-password" required>

			<label for="newPasswordInput">Password Baru</label>
			<input type="password" id="newPasswordInput" name="new_password" autocomplete="new-password" minlength="3" required>

			<label for="confirmPasswordInput">Konfirmasi Password Baru</label>
			<input type="password" id="confirmPasswordInput" name="confirm_password" autocomplete="new-password" minlength="3" required>

			<div class="actions">
				<button type="submit" class="btn primary">Simpan Password Baru</button>
				<a href="<?php echo site_url('logout'); ?>" class="btn secondary">Logout</a>
			</div>
		</form>
	</section>
</body>
</html>
