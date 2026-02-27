<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('attendance_mirror_dir_path'))
{
	function attendance_mirror_dir_path()
	{
		return APPPATH.'cache/attendance_by_date';
	}
}

if (!function_exists('attendance_mirror_is_valid_date'))
{
	function attendance_mirror_is_valid_date($date_key)
	{
		$date_key = trim((string) $date_key);
		if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $date_key))
		{
			return FALSE;
		}

		$year = (int) substr($date_key, 0, 4);
		$month = (int) substr($date_key, 5, 2);
		$day = (int) substr($date_key, 8, 2);
		return checkdate($month, $day, $year);
	}
}

if (!function_exists('attendance_mirror_decode_json'))
{
	function attendance_mirror_decode_json($json_text, $default = array())
	{
		$text = (string) $json_text;
		if ($text === '')
		{
			return $default;
		}

		if (substr($text, 0, 3) === "\xEF\xBB\xBF")
		{
			$text = substr($text, 3);
		}

		$decoded = json_decode($text, TRUE);
		return is_array($decoded) ? $decoded : $default;
	}
}

if (!function_exists('attendance_mirror_load_all'))
{
	function attendance_mirror_load_all(&$error = '')
	{
		$error = '';
		$dir_path = attendance_mirror_dir_path();
		if (!is_dir($dir_path))
		{
			return array();
		}

		$pattern = $dir_path.DIRECTORY_SEPARATOR.'*.json';
		$files = glob($pattern);
		if (!is_array($files))
		{
			return array();
		}

		sort($files, SORT_STRING);
		$rows = array();
		for ($i = 0; $i < count($files); $i += 1)
		{
			$file_path = (string) $files[$i];
			$file_name = strtolower(trim((string) basename($file_path)));
			if ($file_name === '' || $file_name === '_index.json')
			{
				continue;
			}

			$file_date = substr($file_name, 0, 10);
			if (!attendance_mirror_is_valid_date($file_date))
			{
				continue;
			}

			$content = @file_get_contents($file_path);
			if ($content === FALSE)
			{
				if ($error === '')
				{
					$error = 'Gagal membaca mirror absensi: '.$file_path;
				}
				continue;
			}

			$decoded_rows = attendance_mirror_decode_json($content, array());
			for ($row_i = 0; $row_i < count($decoded_rows); $row_i += 1)
			{
				$row = isset($decoded_rows[$row_i]) && is_array($decoded_rows[$row_i])
					? $decoded_rows[$row_i]
					: array();
				$row_date = isset($row['date']) ? trim((string) $row['date']) : '';
				if (!attendance_mirror_is_valid_date($row_date))
				{
					$row['date'] = $file_date;
				}
				$rows[] = $row;
			}
		}

		return array_values($rows);
	}
}

if (!function_exists('attendance_mirror_save_by_date'))
{
	function attendance_mirror_save_by_date($records, $cleanup_stale = TRUE, &$error = '')
	{
		$error = '';
		$rows = is_array($records) ? array_values($records) : array();
		$grouped = array();
		for ($i = 0; $i < count($rows); $i += 1)
		{
			$row = isset($rows[$i]) && is_array($rows[$i]) ? $rows[$i] : array();
			$date_key = isset($row['date']) ? trim((string) $row['date']) : '';
			if (!attendance_mirror_is_valid_date($date_key))
			{
				continue;
			}
			if (!isset($grouped[$date_key]))
			{
				$grouped[$date_key] = array();
			}
			$grouped[$date_key][] = $row;
		}

		$dir_path = attendance_mirror_dir_path();
		if (!is_dir($dir_path))
		{
			if (!@mkdir($dir_path, 0755, TRUE) && !is_dir($dir_path))
			{
				$error = 'Gagal membuat folder mirror absensi: '.$dir_path;
				return FALSE;
			}
		}

		ksort($grouped, SORT_STRING);
		$target_files = array();
		$index_rows = array();
		foreach ($grouped as $date_key => $date_rows)
		{
			$file_path = $dir_path.DIRECTORY_SEPARATOR.$date_key.'.json';
			$payload = json_encode(array_values($date_rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
			if ($payload === FALSE)
			{
				$error = 'Gagal encode mirror absensi tanggal '.$date_key.'.';
				return FALSE;
			}
			if (@file_put_contents($file_path, $payload, LOCK_EX) === FALSE)
			{
				$error = 'Gagal menulis mirror absensi: '.$file_path;
				return FALSE;
			}

			$target_files[strtolower((string) basename($file_path))] = TRUE;
			$index_rows[] = array(
				'date' => $date_key,
				'rows' => count($date_rows),
				'file' => basename($file_path)
			);
		}

		if ($cleanup_stale)
		{
			$existing_files = glob($dir_path.DIRECTORY_SEPARATOR.'*.json');
			if (is_array($existing_files))
			{
				for ($i = 0; $i < count($existing_files); $i += 1)
				{
					$existing_file_path = (string) $existing_files[$i];
					$existing_name = strtolower(trim((string) basename($existing_file_path)));
					if ($existing_name === '' || $existing_name === '_index.json')
					{
						continue;
					}
					if (!isset($target_files[$existing_name]))
					{
						@unlink($existing_file_path);
					}
				}
			}
		}

		$index_payload = json_encode(array(
			'updated_at' => date('Y-m-d H:i:s'),
			'total_dates' => count($index_rows),
			'dates' => $index_rows
		), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($index_payload !== FALSE)
		{
			@file_put_contents($dir_path.DIRECTORY_SEPARATOR.'_index.json', $index_payload, LOCK_EX);
		}

		return TRUE;
	}
}
