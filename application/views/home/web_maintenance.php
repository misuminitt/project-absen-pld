<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$maintenance_title = isset($maintenance_title) && trim((string) $maintenance_title) !== ''
	? trim((string) $maintenance_title)
	: 'Website Sedang Maintenance';
$maintenance_image_data_uri = isset($maintenance_image_data_uri) ? trim((string) $maintenance_image_data_uri) : '';
$maintenance_image_url = isset($maintenance_image_url) ? trim((string) $maintenance_image_url) : '';
$maintenance_image_src = $maintenance_image_data_uri !== '' ? $maintenance_image_data_uri : $maintenance_image_url;
?>
<!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo htmlspecialchars($maintenance_title, ENT_QUOTES, 'UTF-8'); ?></title>
	<style>
		html, body {
			margin: 0;
			padding: 0;
			width: 100%;
			height: 100%;
			background: #0d3ea8;
		}
		body {
			display: flex;
			align-items: center;
			justify-content: center;
			font-family: "Segoe UI", Arial, sans-serif;
			color: #ffffff;
		}
		.maintenance-wrap {
			width: 100%;
			height: 100%;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 18px;
			box-sizing: border-box;
		}
		.maintenance-image {
			display: block;
			max-width: 100%;
			max-height: 100%;
			width: auto;
			height: auto;
			object-fit: contain;
			user-select: none;
			-webkit-user-drag: none;
		}
		.maintenance-fallback {
			text-align: center;
			max-width: 560px;
		}
		.maintenance-fallback h1 {
			margin: 0 0 12px;
			font-size: clamp(1.6rem, 3.5vw, 2.4rem);
		}
		.maintenance-fallback p {
			margin: 0;
			font-size: clamp(1rem, 2.3vw, 1.2rem);
			opacity: 0.92;
		}
	</style>
</head>
<body>
	<div class="maintenance-wrap">
		<?php if ($maintenance_image_src !== ''): ?>
			<img
				class="maintenance-image"
				src="<?php echo htmlspecialchars($maintenance_image_src, ENT_QUOTES, 'UTF-8'); ?>"
				alt="Website Under Development"
			>
		<?php else: ?>
			<div class="maintenance-fallback">
				<h1>Website Under Development</h1>
				<p>Mohon bersabar yaa. Sistem sedang dalam perawatan.</p>
			</div>
		<?php endif; ?>
	</div>
</body>
</html>
