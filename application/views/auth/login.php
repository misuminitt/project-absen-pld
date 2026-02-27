<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$script_name = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$base_path = str_replace('\\', '/', dirname($script_name));
if ($base_path === '/' || $base_path === '.') {
	$base_path = '';
}

$logo_url = '';
$logo_sources = array(
	array('path' => 'src/assets/pns_new.svg', 'mime' => 'image/svg+xml'),
	array('path' => 'src/assets/pns_logo_nav.svg', 'mime' => 'image/svg+xml'),
	array('path' => 'src/assets/pns_new_login.png', 'mime' => 'image/png'),
	array('path' => 'src/assets/pns_new.png', 'mime' => 'image/png'),
	array('path' => 'src/assets/pns_logo_nav.png', 'mime' => 'image/png'),
	array('path' => 'src/assets/pns_dashboard.png', 'mime' => 'image/png'),
);
for ($i = 0; $i < count($logo_sources); $i += 1)
{
	$source = isset($logo_sources[$i]) && is_array($logo_sources[$i]) ? $logo_sources[$i] : array();
	$relative_path = isset($source['path']) ? trim((string) $source['path']) : '';
	$mime_type = isset($source['mime']) ? trim((string) $source['mime']) : '';
	if ($relative_path === '' || $mime_type === '')
	{
		continue;
	}
	$absolute_path = FCPATH.$relative_path;
	if (!is_file($absolute_path))
	{
		continue;
	}
	if (!is_readable($absolute_path))
	{
		@chmod($absolute_path, 0644);
		clearstatcache(TRUE, $absolute_path);
	}
	$file_contents = @file_get_contents($absolute_path);
	if ($file_contents === FALSE || $file_contents === '')
	{
		continue;
	}
	$logo_url = 'data:'.$mime_type.';base64,'.base64_encode($file_contents);
	break;
}
if ($logo_url === '')
{
	$logo_url = 'data:image/gif;base64,R0lGODlhAQABAAAAACw=';
}
$favicon_path = 'src/assets/sinyal.svg';
$favicon_url = site_url('home/favicon');
if (is_file(FCPATH.$favicon_path)) {
	$favicon_url .= '?v='.rawurlencode((string) @filemtime(FCPATH.$favicon_path));
}
$theme_global_css_file = 'src/assets/css/theme-global.css';
$theme_global_js_file = 'src/assets/js/theme-global-init.js';
$theme_global_css_path = FCPATH.$theme_global_css_file;
$theme_global_js_path = FCPATH.$theme_global_js_file;
$theme_global_css_version = is_file($theme_global_css_path) ? (string) @filemtime($theme_global_css_path) : '1';
$theme_global_js_version = is_file($theme_global_js_path) ? (string) @filemtime($theme_global_js_path) : '1';
if ($theme_global_css_version === '')
{
	$theme_global_css_version = '1';
}
if ($theme_global_js_version === '')
{
	$theme_global_js_version = '1';
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
	<title><?php echo isset($title) ? $title : 'Login Absen Online'; ?></title>
	<link rel="icon" href="<?php echo htmlspecialchars($favicon_url, ENT_QUOTES, 'UTF-8'); ?>">
	<link rel="shortcut icon" href="<?php echo htmlspecialchars($favicon_url, ENT_QUOTES, 'UTF-8'); ?>">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<style>
		:root {
			--ink-900: #0a1d34;
			--ink-700: #1f3d5f;
			--ink-500: #4a6786;
			--surface-100: rgba(255, 255, 255, 0.62);
			--surface-80: rgba(255, 255, 255, 0.4);
			--surface-strong: rgba(255, 255, 255, 0.86);
			--line-soft: rgba(255, 255, 255, 0.48);
			--brand: #0e335f;
			--brand-hover: #0a2749;
			--danger: #c63f3f;
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
			font-family: 'Manrope', sans-serif;
			color: var(--ink-900);
			background:
				radial-gradient(circle at 12% 18%, rgba(123, 215, 255, 0.45) 0%, transparent 45%),
				radial-gradient(circle at 85% 14%, rgba(255, 228, 186, 0.5) 0%, transparent 43%),
				radial-gradient(circle at 76% 86%, rgba(137, 255, 213, 0.34) 0%, transparent 42%),
				linear-gradient(145deg, #f2f7ff 0%, #ddeeff 52%, #f5fbff 100%);
			min-height: 100dvh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: clamp(26px, 4vh, 44px) 24px;
			overflow: hidden;
		}

		@media (min-width: 992px) {
			/* Desktop login: place the native theme switch at bottom-right like mobile. */
			#themeToggleButton.theme-navbar-toggle {
				position: fixed;
				right: 16px;
				bottom: calc(66px + env(safe-area-inset-bottom));
				z-index: 1201;
				margin: 0;
			}
		}

		.bg-shape {
			position: fixed;
			pointer-events: none;
			border-radius: 9999px;
			filter: blur(0.5px);
			opacity: 0.72;
			z-index: 0;
		}

		.bg-shape-a {
			width: 240px;
			height: 240px;
			top: 10%;
			left: -70px;
			background: linear-gradient(160deg, rgba(255, 255, 255, 0.85), rgba(123, 215, 255, 0.72));
			box-shadow: inset -18px -18px 40px rgba(0, 0, 0, 0.06);
			animation: floatShape 10s ease-in-out infinite;
		}

		.bg-shape-b {
			width: 210px;
			height: 210px;
			bottom: -40px;
			right: -50px;
			background: linear-gradient(210deg, rgba(255, 255, 255, 0.84), rgba(137, 255, 213, 0.68));
			box-shadow: inset -16px -14px 36px rgba(0, 0, 0, 0.06);
			animation: floatShape 12s ease-in-out infinite reverse;
		}

		.shell {
			width: 100%;
			max-width: 460px;
			margin: 0 auto;
			position: relative;
			z-index: 1;
		}

		.login-card {
			position: relative;
			border-radius: 28px;
			padding: 25px 34px 38px;
			background: linear-gradient(150deg, var(--surface-100) 0%, rgba(255, 255, 255, 0.28) 100%);
			border: 1px solid var(--line-soft);
			backdrop-filter: blur(22px) saturate(150%);
			-webkit-backdrop-filter: blur(22px) saturate(150%);
			box-shadow:
				0 32px 65px rgba(15, 47, 86, 0.2),
				inset 1px 1px 0 rgba(255, 255, 255, 0.55),
				inset -1px -1px 0 rgba(255, 255, 255, 0.28);
			overflow: hidden;
			will-change: transform;
		}

		.login-card::before,
		.login-card::after {
			content: '';
			position: absolute;
			border-radius: 50%;
			pointer-events: none;
		}

		.login-card::before {
			width: 220px;
			height: 220px;
			top: -140px;
			right: -58px;
			background: radial-gradient(circle, rgba(255, 255, 255, 0.6) 0%, transparent 68%);
		}

		.login-card::after {
			width: 170px;
			height: 170px;
			left: -95px;
			bottom: -95px;
			background: radial-gradient(circle, rgba(123, 215, 255, 0.26) 0%, transparent 72%);
		}

		.login-card.shake {
			animation: shakeCard 0.56s cubic-bezier(0.33, 0.9, 0.32, 1);
		}

		.login-card.has-error {
			border-color: rgba(198, 63, 63, 0.42);
		}

		.logo-badge {
			position: absolute;
			top: -220px;
			left: 50%;
			transform: translateX(-50%);
			z-index: 3;
			display: flex;
			justify-content: center;
			align-items: center;
			margin: 0;
			pointer-events: none;
		}

		.logo-image {
			width: 300px;
			height: 300px;
			object-fit: contain;
			filter: drop-shadow(0 17px 10px rgba(8, 37, 72, 0.7));
		}

		h1 {
			margin: 0;
			text-align: center;
			font-size: 2rem;
			font-weight: 800;
			letter-spacing: -0.02em;
			color: var(--ink-900);
		}

		.subtitle {
			margin: 12px 0 32px;
			text-align: center;
			font-size: 0.95rem;
			font-weight: 500;
			color: var(--ink-700);
		}

		.form-row {
			margin-bottom: 20px;
		}

		.label-line {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 10px;
			gap: 12px;
		}

		label {
			font-size: 0.76rem;
			font-weight: 700;
			letter-spacing: 0.1em;
			text-transform: uppercase;
			color: var(--ink-700);
		}

		.reset-link {
			font-size: 0.78rem;
			font-weight: 700;
			color: #1a56a1;
			text-decoration: none;
			transition: color 0.18s ease;
		}

		.reset-link:hover,
		.reset-link:focus-visible {
			color: #7a38d8;
			text-decoration: none;
		}

		.input-field {
			width: 100%;
			height: 50px;
			border-radius: 14px;
			border: 1px solid rgba(255, 255, 255, 0.78);
			background: var(--surface-strong);
			padding: 0 14px;
			font-family: inherit;
			font-size: 0.95rem;
			font-weight: 600;
			color: var(--ink-900);
			outline: none;
			transition: border-color 0.2s ease, box-shadow 0.2s ease;
		}

		.input-field::placeholder {
			color: #8ca0b5;
			font-weight: 500;
		}

		.input-field:focus {
			border-color: rgba(14, 51, 95, 0.55);
			box-shadow: 0 0 0 3px rgba(80, 143, 221, 0.24);
		}

		.error-box {
			margin: 0 0 14px;
			padding: 11px 12px;
			border-radius: 12px;
			background: rgba(198, 63, 63, 0.14);
			border: 1px solid rgba(198, 63, 63, 0.32);
			color: var(--danger);
			font-size: 0.86rem;
			font-weight: 700;
		}

		.signin-btn {
			width: 100%;
			height: 52px;
			margin-top: 8px;
			border: none;
			border-radius: 14px;
			background: linear-gradient(160deg, var(--brand) 0%, #123e74 100%);
			color: #ffffff;
			font-size: 1rem;
			font-weight: 800;
			cursor: pointer;
			transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease;
			box-shadow: 0 12px 22px rgba(14, 51, 95, 0.3);
		}

		.signin-btn:hover {
			background: linear-gradient(160deg, var(--brand-hover) 0%, #102f57 100%);
			box-shadow: 0 14px 22px rgba(14, 51, 95, 0.34);
		}

		.signin-btn:active {
			transform: translateY(1px);
		}

		/* Fallback dark mode khusus login agar teks tetap kontras walau cache global belum update */
		html.theme-dark body,
		body.theme-dark {
			background:
				radial-gradient(circle at 12% 18%, rgba(78, 145, 196, 0.35) 0%, transparent 45%),
				radial-gradient(circle at 85% 14%, rgba(95, 122, 148, 0.32) 0%, transparent 43%),
				radial-gradient(circle at 76% 86%, rgba(77, 132, 124, 0.26) 0%, transparent 42%),
				linear-gradient(145deg, #0b2034 0%, #081a2a 52%, #071526 100%) !important;
			color: #e8f2ff !important;
		}

		html.theme-dark .login-card,
		body.theme-dark .login-card {
			background: linear-gradient(150deg, rgba(20, 42, 64, 0.86) 0%, rgba(12, 28, 45, 0.74) 100%) !important;
			border-color: rgba(120, 162, 199, 0.35) !important;
			box-shadow:
				0 32px 65px rgba(0, 0, 0, 0.42),
				inset 1px 1px 0 rgba(202, 224, 246, 0.12),
				inset -1px -1px 0 rgba(18, 40, 62, 0.2) !important;
		}

		html.theme-dark .login-card h1,
		body.theme-dark .login-card h1 {
			color: #edf6ff !important;
			text-shadow: 0 1px 0 rgba(0, 0, 0, 0.26);
		}

		html.theme-dark .login-card .subtitle,
		body.theme-dark .login-card .subtitle {
			color: #c7d8ea !important;
		}

		html.theme-dark .login-card label,
		body.theme-dark .login-card label {
			color: #d9e7f6 !important;
		}

		html.theme-dark .login-card .reset-link,
		body.theme-dark .login-card .reset-link {
			color: #9fcbff !important;
		}

		html.theme-dark .login-card .input-field,
		body.theme-dark .login-card .input-field {
			background: #0f2436 !important;
			border-color: #3e5974 !important;
			color: #e6eef8 !important;
		}

		html.theme-dark .login-card .input-field::placeholder,
		body.theme-dark .login-card .input-field::placeholder {
			color: #9fb5cb !important;
		}

		@keyframes shakeCard {
			0%,
			100% { transform: translateX(0); }
			16% { transform: translateX(-10px); }
			32% { transform: translateX(9px); }
			48% { transform: translateX(-7px); }
			64% { transform: translateX(5px); }
			80% { transform: translateX(-3px); }
		}

		@keyframes floatShape {
			0%,
			100% { transform: translateY(0) translateX(0) scale(1); }
			50% { transform: translateY(-12px) translateX(8px) scale(1.04); }
		}

		@media (max-width: 520px) {
			body {
				padding: 22px 16px;
			}

			.shell {
				max-width: 100%;
			}

			.login-card {
				margin-top: -52px;
				padding: 108px 22px 26px;
				border-radius: 24px;
			}

			.logo-badge {
				top: -174px;
			}

			h1 {
				font-size: 1.7rem;
			}
		}
	
		/* mobile-fix-20260219 */
		@media (max-width: 760px) {
			body {
				overflow: auto;
				padding: 20px 14px;
			}

			.login-card {
				margin-top: -48px;
				padding: 102px 20px 24px;
				border-radius: 22px;
			}

			.logo-badge {
				top: -164px;
			}

			.logo-image {
				width: 240px;
				height: 240px;
			}

			.subtitle {
				margin: 10px 0 22px;
				font-size: 0.9rem;
			}
		}

		@media (max-width: 420px) {
			.login-card {
				margin-top: -40px;
				padding: 96px 18px 22px;
			}

			h1 {
				font-size: 1.45rem;
			}

			.label-line {
				flex-direction: column;
				align-items: flex-start;
				gap: 6px;
			}

			.input-field {
				height: 46px;
				font-size: 0.9rem;
			}
		}

		/* mobile-fix-logo-card-spacing */
		@media (max-width: 760px) {
			body {
				padding: 18px 12px;
				overflow: auto;
				display: block;
			}

			.shell {
				max-width: 460px;
				width: 100%;
				min-height: calc(100dvh - 36px);
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: flex-start;
				padding-top: clamp(10px, 2.4vh, 20px);
			}

			.logo-badge {
				position: relative;
				top: calc(clamp(4px, 1.2vh, 12px) - 50px);
				left: auto;
				transform: none;
				z-index: 5;
				margin-top: 0;
				/* Negative margin keeps logo visually layered above the card. */
				margin-bottom: clamp(-52px, -10vw, -34px);
			}

			.logo-image {
				width: clamp(220px, 62vw, 270px);
				height: clamp(220px, 62vw, 270px);
			}

			.login-card {
				margin-top: calc(clamp(10px, 2.2vh, 18px) - 50px);
				width: 100%;
				padding: 24px 18px 22px;
				border-radius: 22px;
			}
		}

		@media (max-width: 420px) {
			.shell {
				min-height: calc(100dvh - 28px);
			}

			.logo-badge {
				top: calc(clamp(2px, 1vh, 8px) - 50px);
				margin-top: 0;
				margin-bottom: clamp(-44px, -11vw, -28px);
			}

			.logo-image {
				width: clamp(210px, 64vw, 250px);
				height: clamp(210px, 64vw, 250px);
			}

			.login-card {
				margin-top: calc(clamp(8px, 1.8vh, 14px) - 50px);
				padding: 20px 16px;
			}
		}

		/* iOS short-screen tweak (e.g. iPhone 16): move logo + card up 17px */
		@supports (-webkit-touch-callout: none) {
			@media (max-width: 760px) and (max-height: 875px) {
				.logo-badge {
					top: calc(clamp(4px, 1.2vh, 12px) - 83px);
				}

				.login-card {
					margin-top: calc(clamp(10px, 2.2vh, 18px) - 83px);
				}
			}

			@media (max-width: 420px) and (max-height: 875px) {
				.logo-badge {
					top: calc(clamp(2px, 1vh, 8px) - 83px);
				}

				.login-card {
					margin-top: calc(clamp(8px, 1.8vh, 14px) - 83px);
				}
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
	<script src="<?php echo htmlspecialchars(base_url($theme_global_js_file), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo rawurlencode($theme_global_js_version); ?>"></script>
		<link rel="stylesheet" href="<?php echo htmlspecialchars(base_url($theme_global_css_file), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo rawurlencode($theme_global_css_version); ?>">
</head>
<body<?php echo $_home_theme_body_class; ?> data-theme-mobile-toggle="1" data-theme-native-toggle="1">
	<div class="bg-shape bg-shape-a"></div>
	<div class="bg-shape bg-shape-b"></div>
	<button type="button" class="theme-navbar-toggle" id="themeToggleButton" aria-label="Aktifkan mode malam" aria-pressed="false" title="Ganti ke mode malam">
		<span class="theme-navbar-toggle-track" aria-hidden="true">
			<span class="theme-navbar-toggle-icon sun">&#9728;</span>
			<span class="theme-navbar-toggle-icon moon">&#9790;</span>
			<span class="theme-navbar-toggle-knob"></span>
		</span>
	</button>

	<main class="shell">
		<div class="logo-badge">
			<img
				class="logo-image"
				src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>"
				alt="PNS Absen Logo">
		</div>

		<section class="login-card<?php echo !empty($login_error) ? ' has-error' : ''; ?>" data-login-card data-error="<?php echo !empty($login_error) ? '1' : '0'; ?>">
			<h1>Masuk Sistem Absen</h1>
			<p class="subtitle">Silakan masuk dengan akun yang sudah dimiliki untuk mengakses dashboard absensi online.</p>

			<?php if (!empty($login_error)): ?>
				<p class="error-box" role="alert"><?php echo htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8'); ?></p>
			<?php endif; ?>

			<form id="login-form" method="post" action="">
				<div class="form-row">
					<label for="username">Username</label>
					<input
						class="input-field"
						type="text"
						id="username"
						name="username"
						placeholder="Masukkan username"
						value="<?php echo htmlspecialchars(isset($old_username) ? $old_username : '', ENT_QUOTES, 'UTF-8'); ?>"
						autocomplete="username"
						required
					>
				</div>

				<div class="form-row">
					<div class="label-line">
						<label for="password">Password</label>
						<a
							class="reset-link"
							href="https://wa.me/6281315442392?text=mau%20reset%20password%20akun%20absen"
							target="_blank"
							rel="noopener noreferrer"
						>Lupa password?</a>
					</div>
					<input
						class="input-field"
						type="password"
						id="password"
						name="password"
						placeholder="Masukkan password"
						autocomplete="current-password"
						required
					>
				</div>

				<button class="signin-btn" type="submit">Masuk Dashboard</button>
			</form>

		</section>
	</main>

	<script>
		(function () {
			var form = document.getElementById('login-form');
			if (!form) {
				return;
			}

			form.addEventListener('keydown', function (event) {
				var isEnter = event.key === 'Enter' || event.keyCode === 13;
				if (!isEnter) {
					return;
				}

				var target = event.target;
				var isInput = target && target.classList && target.classList.contains('input-field');
				if (!isInput) {
					return;
				}

				event.preventDefault();
				if (typeof form.requestSubmit === 'function') {
					form.requestSubmit();
					return;
				}

				form.submit();
			});
		})();

		(function () {
			var card = document.querySelector('[data-login-card]');
			if (!card || card.getAttribute('data-error') !== '1') {
				return;
			}

			window.setTimeout(function () {
				card.classList.add('shake');
			}, 90);

			card.addEventListener('animationend', function () {
				card.classList.remove('shake');
			}, { once: true });
		})();
	</script>
</body>
</html>
