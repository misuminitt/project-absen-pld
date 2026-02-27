<?php
defined('BASEPATH') OR exit('No direct script access allowed');
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
?><!DOCTYPE html>
<html lang="en"<?php echo $_home_theme_html_class; ?><?php echo $_home_theme_html_data; ?>>
<head>
<meta charset="utf-8">
<title>PHP Error</title>
<link rel="icon" type="image/svg+xml" href="/src/assets/sinyal.svg">
<link rel="shortcut icon" type="image/svg+xml" href="/src/assets/sinyal.svg">
<style type="text/css">
body {
	background-color: #fff;
	margin: 24px;
	font: 13px/20px normal Helvetica, Arial, sans-serif;
	color: #4F5155;
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

p {
	margin: 12px 15px 12px 15px;
}

#container {
	margin: 10px;
	border: 1px solid #D0D0D0;
	box-shadow: 0 0 8px #D0D0D0;
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

html.theme-dark body {
	background: #0d1a28;
	color: #d7e5f4;
}

html.theme-dark #container {
	border-color: #324a61;
	box-shadow: none;
	background: #122436;
}

html.theme-dark h1 {
	color: #eaf2fb;
	border-bottom-color: #35516b;
}

html.theme-dark code {
	background: #0f2436;
	border-color: #35516b;
	color: #d7e5f4;
}
</style>
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
<script src="<?php echo htmlspecialchars('/src/assets/js/theme-global-init.js?v=20260225f', ENT_QUOTES, 'UTF-8'); ?>"></script>
<link rel="stylesheet" href="<?php echo htmlspecialchars('/src/assets/css/theme-global.css?v=20260225k', ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body<?php echo $_home_theme_body_class; ?>>
<div id="container">
	<h1>A PHP Error was encountered</h1>
	<p>Severity: <?php echo $severity; ?></p>
	<p>Message: <?php echo $message; ?></p>
	<p>Filename: <?php echo $filepath; ?></p>
	<p>Line Number: <?php echo $line; ?></p>
	<?php if (defined('SHOW_DEBUG_BACKTRACE') && SHOW_DEBUG_BACKTRACE === TRUE): ?>
		<p>Backtrace:</p>
		<?php foreach (debug_backtrace() as $error): ?>
			<?php if (isset($error['file']) && strpos($error['file'], realpath(BASEPATH)) !== 0): ?>
				<p style="margin-left:10px">
					File: <?php echo $error['file']; ?><br />
					Line: <?php echo $error['line']; ?><br />
					Function: <?php echo $error['function']; ?>
				</p>
			<?php endif; ?>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
</body>
</html>
