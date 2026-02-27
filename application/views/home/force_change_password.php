<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$username = isset($username) ? (string) $username : 'user';
$password_notice_success = isset($password_notice_success) ? trim((string) $password_notice_success) : '';
$password_notice_error = isset($password_notice_error) ? trim((string) $password_notice_error) : '';
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
	<title><?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Ganti Password'; ?></title>
	<link rel="icon" type="image/svg+xml" href="/src/assets/sinyal.svg">
	<link rel="shortcut icon" type="image/svg+xml" href="/src/assets/sinyal.svg">
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
	
		/* mobile-fix-20260219 */
		@media (max-width: 560px) {
			body {
				align-items: flex-start;
				padding: calc(env(safe-area-inset-top, 0px) + 0.72rem) 0.72rem 0.9rem;
			}

			.card {
				border-radius: 14px;
				padding: 0.92rem;
			}

			h1 {
				font-size: 1.05rem;
			}

			p {
				font-size: 0.82rem;
				line-height: 1.42;
			}

			.actions {
				flex-direction: column;
				align-items: stretch;
			}

			.btn {
				width: 100%;
				text-align: center;
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
