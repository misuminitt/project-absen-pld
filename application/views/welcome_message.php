<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?><?php
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
<html lang="en"<?php echo $_home_theme_html_class; ?><?php echo $_home_theme_html_data; ?>>
<head>
	<meta charset="utf-8">
	<title>Welcome to CodeIgniter</title>
	<link rel="icon" type="image/svg+xml" href="/src/assets/sinyal.svg">
	<link rel="shortcut icon" type="image/svg+xml" href="/src/assets/sinyal.svg">

	<style type="text/css">

	::selection { background-color: #E13300; color: white; }
	::-moz-selection { background-color: #E13300; color: white; }

	body {
		background-color: #fff;
		margin: 40px;
		font: 13px/20px normal Helvetica, Arial, sans-serif;
		color: #4F5155;
	}

	a {
		color: #003399;
		background-color: transparent;
		font-weight: normal;
		text-decoration: none;
	}

	a:hover {
		color: #97310e;
	}

	h1 {
		color: #444;
		background-color: transparent;
		border-bottom: 1px solid #D0D0D0;
		font-size: 19px;
		font-weight: normal;
		margin: 0 0 14px 0;
		padding: 14px 15px 10px 15px;
	}

	code {
		font-family: Consolas, Monaco, Courier New, Courier, monospace;
		font-size: 12px;
		background-color: #f9f9f9;
		border: 1px solid #D0D0D0;
		color: #002166;
		display: block;
		margin: 14px 0 14px 0;
		padding: 12px 10px 12px 10px;
	}

	#body {
		margin: 0 15px 0 15px;
		min-height: 96px;
	}

	p {
		margin: 0 0 10px;
		padding:0;
	}

	p.footer {
		text-align: right;
		font-size: 11px;
		border-top: 1px solid #D0D0D0;
		line-height: 32px;
		padding: 0 10px 0 10px;
		margin: 20px 0 0 0;
	}

	#container {
		margin: 10px;
		border: 1px solid #D0D0D0;
		box-shadow: 0 0 8px #D0D0D0;
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

<div id="container">
	<h1>Welcome to CodeIgniter!</h1>

	<div id="body">
		<p>The page you are looking at is being generated dynamically by CodeIgniter.</p>

		<p>If you would like to edit this page you'll find it located at:</p>
		<code>application/views/welcome_message.php</code>

		<p>The corresponding controller for this page is found at:</p>
		<code>application/controllers/Welcome.php</code>

		<p>If you are exploring CodeIgniter for the very first time, you should start by reading the <a href="userguide3/">User Guide</a>.</p>
	</div>

	<p class="footer">Page rendered in <strong>{elapsed_time}</strong> seconds. <?php echo  (ENVIRONMENT === 'development') ?  'CodeIgniter Version <strong>' . CI_VERSION . '</strong>' : '' ?></p>
</div>

</body>
</html>
