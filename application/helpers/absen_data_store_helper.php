<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('absen_storage_config'))
{
	function absen_storage_config()
	{
		static $config_cache = NULL;
		if ($config_cache !== NULL)
		{
			return $config_cache;
		}

		$config_cache = array(
			'db_enabled' => FALSE,
			'mirror_json_backup' => TRUE,
			'data_store_table' => 'absen_data_store',
			'accounts_store_key' => 'accounts_book'
		);

		if (!function_exists('get_instance'))
		{
			return $config_cache;
		}

		$CI =& get_instance();
		if (!is_object($CI) || !isset($CI->load) || !is_object($CI->load))
		{
			return $config_cache;
		}

		$CI->load->config('absen_storage', TRUE);
		$config_row = $CI->config->item('absen_storage', 'absen_storage');
		if (is_array($config_row))
		{
			$config_cache = array_merge($config_cache, $config_row);
		}

		return $config_cache;
	}
}

if (!function_exists('absen_storage_bool_flag'))
{
	function absen_storage_bool_flag($value, $default = FALSE)
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

if (!function_exists('absen_db_storage_enabled'))
{
	function absen_db_storage_enabled()
	{
		$config = absen_storage_config();
		return absen_storage_bool_flag(isset($config['db_enabled']) ? $config['db_enabled'] : FALSE, FALSE);
	}
}

if (!function_exists('absen_db_storage_mirror_json'))
{
	function absen_db_storage_mirror_json()
	{
		$config = absen_storage_config();
		return absen_storage_bool_flag(isset($config['mirror_json_backup']) ? $config['mirror_json_backup'] : TRUE, TRUE);
	}
}

if (!function_exists('absen_data_store_safe_table_name'))
{
	function absen_data_store_safe_table_name()
	{
		$config = absen_storage_config();
		$table_name = strtolower(trim((string) (isset($config['data_store_table']) ? $config['data_store_table'] : '')));
		if ($table_name === '' || !preg_match('/^[a-z0-9_]+$/', $table_name))
		{
			$table_name = 'absen_data_store';
		}

		return $table_name;
	}
}

if (!function_exists('absen_accounts_store_key'))
{
	function absen_accounts_store_key()
	{
		$config = absen_storage_config();
		$store_key = strtolower(trim((string) (isset($config['accounts_store_key']) ? $config['accounts_store_key'] : '')));
		if ($store_key === '' || !preg_match('/^[a-z0-9_.-]+$/', $store_key))
		{
			$store_key = 'accounts_book';
		}

		return $store_key;
	}
}

if (!function_exists('absen_data_store_key_normalize'))
{
	function absen_data_store_key_normalize($store_key)
	{
		$key = strtolower(trim((string) $store_key));
		if ($key === '' || !preg_match('/^[a-z0-9_.-]+$/', $key))
		{
			return '';
		}

		return $key;
	}
}

if (!function_exists('absen_data_store_db_instance'))
{
	function absen_data_store_db_instance()
	{
		static $attempted = FALSE;
		static $db_instance = NULL;

		if ($attempted)
		{
			return $db_instance;
		}
		$attempted = TRUE;

		if (!absen_db_storage_enabled())
		{
			return NULL;
		}
		if (!function_exists('get_instance'))
		{
			return NULL;
		}

		$CI =& get_instance();
		if (!is_object($CI) || !isset($CI->load) || !is_object($CI->load))
		{
			return NULL;
		}

		if (!isset($CI->db) || !is_object($CI->db))
		{
			@$CI->load->database();
		}
		if (!isset($CI->db) || !is_object($CI->db))
		{
			return NULL;
		}

		if ((!isset($CI->db->conn_id) || !$CI->db->conn_id) && method_exists($CI->db, 'initialize'))
		{
			@$CI->db->initialize();
		}
		if (!isset($CI->db->conn_id) || !$CI->db->conn_id)
		{
			return NULL;
		}

		$db_instance = $CI->db;
		return $db_instance;
	}
}

if (!function_exists('absen_data_store_ensure_table'))
{
	function absen_data_store_ensure_table($db)
	{
		static $table_ready = FALSE;
		static $table_ready_name = '';

		if (!is_object($db))
		{
			return FALSE;
		}

		$table_name = absen_data_store_safe_table_name();
		if ($table_ready && $table_ready_name === $table_name)
		{
			return TRUE;
		}

		$sql = "CREATE TABLE IF NOT EXISTS `".$table_name."` (
			`store_key` VARCHAR(100) NOT NULL,
			`payload_json` LONGTEXT NOT NULL,
			`updated_at` DATETIME NOT NULL,
			PRIMARY KEY (`store_key`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
		$created = @$db->query($sql);
		if ($created === FALSE)
		{
			return FALSE;
		}

		$table_ready = TRUE;
		$table_ready_name = $table_name;
		return TRUE;
	}
}

if (!function_exists('absen_data_store_read_json_file'))
{
	function absen_data_store_read_json_file($file_path, $default = NULL)
	{
		$file = trim((string) $file_path);
		if ($file === '' || !is_file($file))
		{
			return $default;
		}

		$content = @file_get_contents($file);
		if ($content === FALSE || trim((string) $content) === '')
		{
			return $default;
		}
		if (substr($content, 0, 3) === "\xEF\xBB\xBF")
		{
			$content = substr($content, 3);
		}

		$decoded = json_decode((string) $content, TRUE);
		if (json_last_error() !== JSON_ERROR_NONE)
		{
			return $default;
		}

		return $decoded;
	}
}

if (!function_exists('absen_data_store_write_json_file'))
{
	function absen_data_store_write_json_file($file_path, $value)
	{
		$file = trim((string) $file_path);
		if ($file === '')
		{
			return FALSE;
		}

		$directory = dirname($file);
		if (!is_dir($directory))
		{
			@mkdir($directory, 0755, TRUE);
		}
		if (!is_dir($directory))
		{
			return FALSE;
		}

		$payload = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($payload === FALSE)
		{
			return FALSE;
		}

		return @file_put_contents($file, $payload) !== FALSE;
	}
}

if (!function_exists('absen_data_store_load_value'))
{
	function absen_data_store_load_value($store_key, $default = array(), $fallback_file = '')
	{
		$key = absen_data_store_key_normalize($store_key);
		if ($key === '')
		{
			return $default;
		}

		$db = absen_data_store_db_instance();
		if ($db !== NULL && absen_data_store_ensure_table($db))
		{
			$table_name = absen_data_store_safe_table_name();
			$query = $db->select('payload_json')
				->from($table_name)
				->where('store_key', $key)
				->limit(1)
				->get();
			if ($query && $query->num_rows() > 0)
			{
				$row = $query->row_array();
				$payload = isset($row['payload_json']) ? (string) $row['payload_json'] : '';
				if (trim($payload) !== '')
				{
					$decoded = json_decode($payload, TRUE);
					if (json_last_error() === JSON_ERROR_NONE)
					{
						return $decoded;
					}
				}
			}
		}

		$file_value = absen_data_store_read_json_file($fallback_file, NULL);
		if ($file_value !== NULL)
		{
			if ($db !== NULL && absen_data_store_ensure_table($db))
			{
				absen_data_store_save_value($key, $file_value, '');
			}
			return $file_value;
		}

		return $default;
	}
}

if (!function_exists('absen_data_store_save_value'))
{
	function absen_data_store_save_value($store_key, $value, $fallback_file = '')
	{
		$key = absen_data_store_key_normalize($store_key);
		if ($key === '')
		{
			return FALSE;
		}

		$payload = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($payload === FALSE)
		{
			return FALSE;
		}

		$saved_to_db = FALSE;
		$db = absen_data_store_db_instance();
		if ($db !== NULL && absen_data_store_ensure_table($db))
		{
			$table_name = absen_data_store_safe_table_name();
			$saved_to_db = (bool) $db->replace($table_name, array(
				'store_key' => $key,
				'payload_json' => $payload,
				'updated_at' => date('Y-m-d H:i:s')
			));
		}

		if ($saved_to_db)
		{
			if ($fallback_file !== '' && absen_db_storage_mirror_json())
			{
				absen_data_store_write_json_file($fallback_file, $value);
			}
			return TRUE;
		}

		if ($fallback_file !== '')
		{
			return absen_data_store_write_json_file($fallback_file, $value);
		}

		return FALSE;
	}
}

if (!function_exists('absen_data_store_load_array'))
{
	function absen_data_store_load_array($store_key, $fallback_file = '')
	{
		$value = absen_data_store_load_value($store_key, array(), $fallback_file);
		return is_array($value) ? $value : array();
	}
}

if (!function_exists('absen_data_store_save_array'))
{
	function absen_data_store_save_array($store_key, $rows, $fallback_file = '')
	{
		return absen_data_store_save_value($store_key, is_array($rows) ? $rows : array(), $fallback_file);
	}
}
