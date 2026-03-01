<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('absen_web_maintenance_state_store_key'))
{
	function absen_web_maintenance_state_store_key()
	{
		return 'web_maintenance_state';
	}
}

if (!function_exists('absen_web_maintenance_state_file_path'))
{
	function absen_web_maintenance_state_file_path()
	{
		return APPPATH.'cache/web_maintenance_state.json';
	}
}

if (!function_exists('absen_web_maintenance_bool_flag'))
{
	function absen_web_maintenance_bool_flag($value, $default = FALSE)
	{
		if (is_bool($value))
		{
			return $value;
		}
		$text = strtolower(trim((string) $value));
		if ($text === '')
		{
			return (bool) $default;
		}
		if (in_array($text, array('1', 'true', 'on', 'yes'), TRUE))
		{
			return TRUE;
		}
		if (in_array($text, array('0', 'false', 'off', 'no'), TRUE))
		{
			return FALSE;
		}

		return (bool) $default;
	}
}

if (!function_exists('absen_web_maintenance_default_state'))
{
	function absen_web_maintenance_default_state()
	{
		return array(
			'enabled' => FALSE,
			'updated_at' => '',
			'updated_by' => ''
		);
	}
}

if (!function_exists('absen_web_maintenance_normalize_state'))
{
	function absen_web_maintenance_normalize_state($state)
	{
		$row = is_array($state) ? $state : array();
		return array(
			'enabled' => isset($row['enabled']) ? absen_web_maintenance_bool_flag($row['enabled'], FALSE) : FALSE,
			'updated_at' => isset($row['updated_at']) ? trim((string) $row['updated_at']) : '',
			'updated_by' => isset($row['updated_by']) ? strtolower(trim((string) $row['updated_by'])) : ''
		);
	}
}

if (!function_exists('absen_web_maintenance_load_state'))
{
	function absen_web_maintenance_load_state()
	{
		$state = absen_web_maintenance_default_state();
		$file_path = absen_web_maintenance_state_file_path();
		$store_key = absen_web_maintenance_state_store_key();

		if (function_exists('absen_data_store_load_value'))
		{
			$loaded = absen_data_store_load_value($store_key, NULL, $file_path);
			if (is_array($loaded))
			{
				$state = array_merge($state, $loaded);
			}
		}
		elseif (is_file($file_path))
		{
			$content = @file_get_contents($file_path);
			if ($content !== FALSE && trim((string) $content) !== '')
			{
				$decoded = json_decode((string) $content, TRUE);
				if (is_array($decoded))
				{
					$state = array_merge($state, $decoded);
				}
			}
		}

		return absen_web_maintenance_normalize_state($state);
	}
}

if (!function_exists('absen_web_maintenance_save_state'))
{
	function absen_web_maintenance_save_state($state)
	{
		$file_path = absen_web_maintenance_state_file_path();
		$store_key = absen_web_maintenance_state_store_key();
		$normalized = absen_web_maintenance_normalize_state($state);
		$normalized['updated_at'] = date('Y-m-d H:i:s');

		$saved_to_store = FALSE;
		if (function_exists('absen_data_store_save_value'))
		{
			$saved_to_store = absen_data_store_save_value($store_key, $normalized, $file_path);
		}

		$directory = dirname($file_path);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0777, TRUE);
		}

		$saved_to_file = FALSE;
		if (is_dir($directory))
		{
			$saved_to_file = (bool) @file_put_contents(
				$file_path,
				json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
			);
		}

		return $saved_to_store || $saved_to_file;
	}
}

if (!function_exists('absen_web_maintenance_enabled'))
{
	function absen_web_maintenance_enabled()
	{
		$state = absen_web_maintenance_load_state();
		return isset($state['enabled']) && $state['enabled'] === TRUE;
	}
}

if (!function_exists('absen_web_maintenance_bypass_secret'))
{
	function absen_web_maintenance_bypass_secret()
	{
		static $cached_secret = NULL;
		if ($cached_secret !== NULL)
		{
			return $cached_secret;
		}

		$env_keys = array(
			'ABSEN_WEB_MAINTENANCE_BYPASS_KEY',
			'APP_WEB_MAINTENANCE_BYPASS_KEY',
			'WEB_MAINTENANCE_BYPASS_KEY'
		);
		for ($i = 0; $i < count($env_keys); $i += 1)
		{
			$env_value = getenv($env_keys[$i]);
			if ($env_value !== FALSE)
			{
				$env_value = trim((string) $env_value);
				if ($env_value !== '')
				{
					$cached_secret = $env_value;
					return $cached_secret;
				}
			}
		}

		$config_secret = function_exists('config_item')
			? trim((string) config_item('web_maintenance_bypass_key'))
			: '';
		if ($config_secret !== '')
		{
			$cached_secret = $config_secret;
			return $cached_secret;
		}

		$encryption_key = function_exists('config_item')
			? trim((string) config_item('encryption_key'))
			: '';
		if ($encryption_key === '')
		{
			$encryption_key = md5(__FILE__.'|'.(defined('FCPATH') ? FCPATH : 'absen'));
		}

		$cached_secret = 'wm.'.$encryption_key;
		return $cached_secret;
	}
}

if (!function_exists('absen_web_maintenance_bypass_token'))
{
	function absen_web_maintenance_bypass_token()
	{
		$secret = absen_web_maintenance_bypass_secret();
		$digest = hash_hmac('sha256', 'absen-web-maintenance-bypass-v1', $secret);
		if (!is_string($digest) || trim($digest) === '')
		{
			return '';
		}

		return strtolower(substr($digest, 0, 40));
	}
}

if (!function_exists('absen_web_maintenance_verify_bypass_token'))
{
	function absen_web_maintenance_verify_bypass_token($token)
	{
		$provided = strtolower(trim((string) $token));
		$expected = absen_web_maintenance_bypass_token();
		if ($provided === '' || $expected === '')
		{
			return FALSE;
		}

		return hash_equals($expected, $provided);
	}
}
