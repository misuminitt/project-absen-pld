<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$script_name = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$base_path = str_replace('\\', '/', dirname($script_name));
if ($base_path === '/' || $base_path === '.') {
	$base_path = '';
}

$logo_path = 'src/assets/pns_new.png';
if (is_file(FCPATH.'src/assts/pns_new.png')) {
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
	<title><?php echo isset($title) ? $title : 'Login Absen Online'; ?></title>
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
			top: -230px;
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
			width: 340px;
			height: 340px;
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
				top: -178px;
			}

			h1 {
				font-size: 1.7rem;
			}
		}
	</style>
</head>
<body>
	<div class="bg-shape bg-shape-a"></div>
	<div class="bg-shape bg-shape-b"></div>

	<main class="shell">
		<div class="logo-badge">
			<img class="logo-image" src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="PNS Absen Logo">
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
