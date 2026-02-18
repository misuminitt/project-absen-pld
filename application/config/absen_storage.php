<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$db_storage_enabled_raw = strtolower(trim((string) getenv('ABSEN_DB_STORAGE_ENABLED')));
$db_storage_enabled = FALSE;
if (
	$db_storage_enabled_raw === '1' ||
	$db_storage_enabled_raw === 'true' ||
	$db_storage_enabled_raw === 'on' ||
	$db_storage_enabled_raw === 'yes'
)
{
	$db_storage_enabled = TRUE;
}
elseif ($db_storage_enabled_raw === '')
{
	$db_name_env = trim((string) getenv('DB_NAME'));
	$db_user_env = trim((string) getenv('DB_USER'));
	if ($db_name_env !== '' && $db_user_env !== '')
	{
		$db_storage_enabled = TRUE;
	}
}

$mirror_json_raw = strtolower(trim((string) getenv('ABSEN_DB_STORAGE_MIRROR_JSON')));
$mirror_json_backup = TRUE;
if (
	$mirror_json_raw === '0' ||
	$mirror_json_raw === 'false' ||
	$mirror_json_raw === 'off' ||
	$mirror_json_raw === 'no'
)
{
	$mirror_json_backup = FALSE;
}

$data_store_table = trim((string) getenv('ABSEN_DB_DATA_STORE_TABLE'));
if ($data_store_table === '')
{
	$data_store_table = 'absen_data_store';
}

$config['absen_storage'] = array(
	'db_enabled' => $db_storage_enabled,
	'mirror_json_backup' => $mirror_json_backup,
	'data_store_table' => $data_store_table,
	'accounts_store_key' => 'accounts_book'
);
