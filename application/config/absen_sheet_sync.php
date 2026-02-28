<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$sheet_sync_enabled_env = strtolower(trim((string) getenv('ABSEN_SHEET_SYNC_ENABLED')));
$sheet_sync_enabled = TRUE;
if ($sheet_sync_enabled_env === '0' || $sheet_sync_enabled_env === 'false' || $sheet_sync_enabled_env === 'off')
{
	$sheet_sync_enabled = FALSE;
}

$spreadsheet_id = trim((string) getenv('ABSEN_SHEET_ID'));
if ($spreadsheet_id === '')
{
	$spreadsheet_id = '1qsHNLyx8lw89jC9YFvz3thqaxCkK2XiQNfwosPiTfZ4';
}

$sheet_gid_raw = trim((string) getenv('ABSEN_SHEET_GID'));
$sheet_gid = $sheet_gid_raw !== '' ? (int) $sheet_gid_raw : 113385665;

$sheet_title = trim((string) getenv('ABSEN_SHEET_TITLE'));

$credential_json_path = trim((string) getenv('ABSEN_GOOGLE_CREDENTIALS'));
if ($credential_json_path === '')
{
	$credential_json_path = FCPATH.'src/secrets/panha-database-spreedsheet-e7201f6cbb11.json';
	if (!is_file($credential_json_path))
	{
		$credential_json_path = 'C:/Users/ASUS TUF GAMING/Downloads/panha_chan/panha-database-spreedsheet-e7201f6cbb11.json';
	}
}

$attendance_sheet_gid_raw = trim((string) getenv('ABSEN_ATTENDANCE_SHEET_GID'));
$attendance_sheet_gid = $attendance_sheet_gid_raw !== '' ? (int) $attendance_sheet_gid_raw : 905628679;
$attendance_sheet_title = trim((string) getenv('ABSEN_ATTENDANCE_SHEET_TITLE'));
if ($attendance_sheet_title === '')
{
	$attendance_sheet_title = 'Data Absen';
}
$attendance_sync_enabled_env = strtolower(trim((string) getenv('ABSEN_ATTENDANCE_SYNC_ENABLED')));
$attendance_sync_enabled = TRUE;
if ($attendance_sync_enabled_env === '0' || $attendance_sync_enabled_env === 'false' || $attendance_sync_enabled_env === 'off')
{
	$attendance_sync_enabled = FALSE;
}
$attendance_push_enabled_env = strtolower(trim((string) getenv('ABSEN_ATTENDANCE_PUSH_ENABLED')));
$attendance_push_enabled = TRUE;
if ($attendance_push_enabled_env === '0' || $attendance_push_enabled_env === 'false' || $attendance_push_enabled_env === 'off')
{
	$attendance_push_enabled = FALSE;
}
$attendance_pull_interval_raw = trim((string) getenv('ABSEN_ATTENDANCE_SYNC_INTERVAL_SECONDS'));
$attendance_pull_interval_seconds = $attendance_pull_interval_raw !== '' ? (int) $attendance_pull_interval_raw : 60;
if ($attendance_pull_interval_seconds < 0)
{
	$attendance_pull_interval_seconds = 0;
}
$attendance_push_interval_raw = trim((string) getenv('ABSEN_ATTENDANCE_PUSH_INTERVAL_SECONDS'));
$attendance_push_interval_seconds = $attendance_push_interval_raw !== '' ? (int) $attendance_push_interval_raw : 300;
if ($attendance_push_interval_seconds < 0)
{
	$attendance_push_interval_seconds = 0;
}

$request_timeout_raw = trim((string) getenv('ABSEN_SHEET_REQUEST_TIMEOUT_SECONDS'));
$request_timeout_seconds = $request_timeout_raw !== '' ? (int) $request_timeout_raw : 45;
if ($request_timeout_seconds < 5)
{
	$request_timeout_seconds = 5;
}
if ($request_timeout_seconds > 180)
{
	$request_timeout_seconds = 180;
}

$batch_update_chunk_size_raw = trim((string) getenv('ABSEN_SHEET_BATCH_UPDATE_CHUNK_SIZE'));
$batch_update_chunk_size = $batch_update_chunk_size_raw !== '' ? (int) $batch_update_chunk_size_raw : 500;
if ($batch_update_chunk_size < 0)
{
	$batch_update_chunk_size = 0;
}
if ($batch_update_chunk_size > 0 && $batch_update_chunk_size < 50)
{
	$batch_update_chunk_size = 50;
}
if ($batch_update_chunk_size > 1000)
{
	$batch_update_chunk_size = 1000;
}

$batch_update_chunk_delay_raw = trim((string) getenv('ABSEN_SHEET_BATCH_UPDATE_CHUNK_DELAY_MS'));
$batch_update_chunk_delay_ms = $batch_update_chunk_delay_raw !== '' ? (int) $batch_update_chunk_delay_raw : 120;
if ($batch_update_chunk_delay_ms < 0)
{
	$batch_update_chunk_delay_ms = 0;
}
if ($batch_update_chunk_delay_ms > 3000)
{
	$batch_update_chunk_delay_ms = 3000;
}

$http_retry_max_raw = trim((string) getenv('ABSEN_SHEET_HTTP_RETRY_MAX'));
$http_retry_max = $http_retry_max_raw !== '' ? (int) $http_retry_max_raw : 1;
if ($http_retry_max < 0)
{
	$http_retry_max = 0;
}
if ($http_retry_max > 3)
{
	$http_retry_max = 3;
}

$http_retry_delay_raw = trim((string) getenv('ABSEN_SHEET_HTTP_RETRY_DELAY_MS'));
$http_retry_delay_ms = $http_retry_delay_raw !== '' ? (int) $http_retry_delay_raw : 700;
if ($http_retry_delay_ms < 0)
{
	$http_retry_delay_ms = 0;
}
if ($http_retry_delay_ms > 10000)
{
	$http_retry_delay_ms = 10000;
}

$loan_sheet_enabled_env = strtolower(trim((string) getenv('ABSEN_LOAN_SHEET_ENABLED')));
$loan_sheet_enabled = TRUE;
if ($loan_sheet_enabled_env === '0' || $loan_sheet_enabled_env === 'false' || $loan_sheet_enabled_env === 'off')
{
	$loan_sheet_enabled = FALSE;
}

$loan_spreadsheet_id = trim((string) getenv('ABSEN_LOAN_SHEET_ID'));
if ($loan_spreadsheet_id === '')
{
	$loan_spreadsheet_id = '1FYY_5FjhZCrYN7huU34fwTkwzL5sWLjeTtzfwaI4fcI';
}

$loan_sheet_gid_raw = trim((string) getenv('ABSEN_LOAN_SHEET_GID'));
$loan_sheet_gid = $loan_sheet_gid_raw !== '' ? (int) $loan_sheet_gid_raw : 1429162529;

$loan_sheet_title = trim((string) getenv('ABSEN_LOAN_SHEET_TITLE'));
if ($loan_sheet_title === '')
{
	$loan_sheet_title = 'KASBON';
}

$loan_credential_json_path = trim((string) getenv('ABSEN_LOAN_GOOGLE_CREDENTIALS'));
if ($loan_credential_json_path === '')
{
	$loan_credential_json_path = FCPATH.'src/secrets/panha-sheet-kasbon-da17149bbbeb.json';
	if (!is_file($loan_credential_json_path))
	{
		$loan_credential_json_path = 'C:/Users/ASUS TUF GAMING/Downloads/panha_chan/panha-sheet-kasbon-da17149bbbeb.json';
	}
}

$config['absen_sheet_sync'] = array(
	'enabled' => $sheet_sync_enabled,
	'spreadsheet_id' => $spreadsheet_id,
	'sheet_gid' => $sheet_gid,
	'sheet_title' => $sheet_title,
	'credential_json_path' => $credential_json_path,
	'credential_json_raw' => trim((string) getenv('ABSEN_GOOGLE_CREDENTIALS_JSON')),
	'sync_interval_seconds' => 60,
	'request_timeout_seconds' => $request_timeout_seconds,
	'batch_update_chunk_size' => $batch_update_chunk_size,
	'batch_update_chunk_delay_ms' => $batch_update_chunk_delay_ms,
	'http_retry_max' => $http_retry_max,
	'http_retry_delay_ms' => $http_retry_delay_ms,
	'default_user_password' => '123',
	'writeback_on_web_change' => FALSE,
	'prune_missing_sheet_users' => TRUE,
	'fixed_account_sheet_rows' => array(
		'supriatna' => 25
	),
	'attendance_sync_enabled' => $attendance_sync_enabled,
	'attendance_push_enabled' => $attendance_push_enabled,
	'attendance_sheet_gid' => $attendance_sheet_gid,
	'attendance_sheet_title' => $attendance_sheet_title,
	'attendance_sync_interval_seconds' => $attendance_pull_interval_seconds,
	'attendance_push_interval_seconds' => $attendance_push_interval_seconds,
	'loan_sheet_enabled' => $loan_sheet_enabled,
	'loan_spreadsheet_id' => $loan_spreadsheet_id,
	'loan_sheet_gid' => $loan_sheet_gid,
	'loan_sheet_title' => $loan_sheet_title,
	'loan_credential_json_path' => $loan_credential_json_path,
	'loan_credential_json_raw' => trim((string) getenv('ABSEN_LOAN_GOOGLE_CREDENTIALS_JSON')),
	'state_file' => APPPATH.'cache/sheet_sync_state.json',
	'log_prefix' => '[SheetSync] ',
	'field_labels' => array(
		'name' => 'Nama',
		'job_title' => 'Jabatan',
		'status' => 'Status',
		'address' => 'Alamat',
		'phone' => 'Tlp',
		'branch' => 'Cabang',
		'salary' => 'Gaji Pokok'
	),
	'header_aliases' => array(
		'nama' => 'name',
		'name' => 'name',
		'namalengkap' => 'name',
		'fullname' => 'name',
		'fullnama' => 'name',
		'jabatan' => 'job_title',
		'jobtitle' => 'job_title',
		'posisi' => 'job_title',
		'status' => 'status',
		'statuskaryawan' => 'status',
		'alamat' => 'address',
		'address' => 'address',
		'tlp' => 'phone',
		'telp' => 'phone',
		'telepon' => 'phone',
		'notelepon' => 'phone',
		'notelp' => 'phone',
		'phone' => 'phone',
		'cabang' => 'branch',
		'branch' => 'branch',
		'gajipokok' => 'salary',
		'gajidasar' => 'salary',
		'basicsalary' => 'salary',
		'salarybasic' => 'salary'
	)
);
