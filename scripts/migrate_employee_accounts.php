#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Migrate employee usernames to first-name format and reset employee passwords to 123.
 *
 * - Admin account (role=admin or username=admin) is kept unchanged.
 * - Related username references in cache JSON files are updated.
 */

date_default_timezone_set('Asia/Jakarta');

$root = dirname(__DIR__);
$cacheDir = $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'cache';
$accountsPath = $cacheDir . DIRECTORY_SEPARATOR . 'accounts.json';

if (!is_file($accountsPath)) {
	fwrite(STDERR, "File tidak ditemukan: {$accountsPath}\n");
	exit(1);
}

$raw = @file_get_contents($accountsPath);
if ($raw === false || trim($raw) === '') {
	fwrite(STDERR, "Gagal membaca accounts.json atau file kosong.\n");
	exit(1);
}

if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
	$raw = substr($raw, 3);
}

$accounts = json_decode($raw, true);
if (!is_array($accounts)) {
	fwrite(STDERR, "Format JSON accounts.json tidak valid.\n");
	exit(1);
}

function username_base_from_name(string $name): string
{
	$name = trim($name);
	if ($name === '') {
		return 'user';
	}

	if (function_exists('iconv')) {
		$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
		if ($converted !== false && trim((string) $converted) !== '') {
			$name = (string) $converted;
		}
	}

	$parts = preg_split('/\s+/', $name) ?: array();
	$selected = '';
	foreach ($parts as $part) {
		$part = strtolower(trim((string) $part));
		if ($part === '') {
			continue;
		}
		$part = preg_replace('/[^a-z0-9]+/', '', $part);
		if ($part === '') {
			continue;
		}
		if (strlen($part) >= 2) {
			$selected = $part;
			break;
		}
		if ($selected === '') {
			$selected = $part;
		}
	}

	if ($selected === '') {
		$selected = strtolower($name);
		$selected = preg_replace('/[^a-z0-9]+/', '', $selected);
	}
	if ($selected === '') {
		$selected = 'user';
	}
	if (strlen($selected) > 30) {
		$selected = substr($selected, 0, 30);
	}
	if (strlen($selected) < 3) {
		$selected = str_pad($selected, 3, 'x');
	}

	return $selected;
}

function build_unique_username(string $base, array &$used): string
{
	$base = strtolower(trim($base));
	if ($base === '') {
		$base = 'user';
	}
	if (!isset($used[$base])) {
		$used[$base] = true;
		return $base;
	}

	for ($i = 2; $i < 10000; $i += 1) {
		$suffix = '_' . $i;
		$maxLength = 30 - strlen($suffix);
		if ($maxLength < 1) {
			$maxLength = 1;
		}
		$candidate = substr($base, 0, $maxLength) . $suffix;
		if (!isset($used[$candidate])) {
			$used[$candidate] = true;
			return $candidate;
		}
	}

	return $base . '_' . time();
}

function write_json_file(string $path, $payload): bool
{
	$encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	if ($encoded === false) {
		return false;
	}
	return @file_put_contents($path, $encoded) !== false;
}

$timestamp = date('Ymd_His');
$backupAccountsPath = $accountsPath . '.bak_' . $timestamp;
@copy($accountsPath, $backupAccountsPath);

$usedUsernames = array();
foreach ($accounts as $username => $row) {
	$key = strtolower(trim((string) $username));
	if ($key !== '') {
		$usedUsernames[$key] = true;
	}
}

$usernameMap = array();
$migratedAccounts = array();
$updatedUserCount = 0;

// Preserve true admin account as-is.
if (isset($accounts['admin']) && is_array($accounts['admin'])) {
	$migratedAccounts['admin'] = $accounts['admin'];
	$usedUsernames['admin'] = true;
}

foreach ($accounts as $username => $row) {
	$oldUsername = strtolower(trim((string) $username));
	if ($oldUsername === '' || !is_array($row)) {
		continue;
	}

	$role = strtolower(trim((string) ($row['role'] ?? 'user')));
	$isAdmin = $oldUsername === 'admin' || $role === 'admin';
	if ($isAdmin) {
		if (!isset($migratedAccounts[$oldUsername])) {
			$migratedAccounts[$oldUsername] = $row;
		}
		continue;
	}

	$displayName = trim((string) ($row['display_name'] ?? ''));
	if ($displayName === '') {
		$displayName = $oldUsername;
	}

	$base = username_base_from_name($displayName);
	$newUsername = build_unique_username($base, $usedUsernames);
	$usernameMap[$oldUsername] = $newUsername;

	$row['password'] = '123';
	$migratedAccounts[$newUsername] = $row;
	$updatedUserCount += 1;
}

ksort($migratedAccounts);
if (!write_json_file($accountsPath, $migratedAccounts)) {
	fwrite(STDERR, "Gagal menyimpan accounts.json\n");
	exit(1);
}

$jsonArrayFiles = array(
	$cacheDir . DIRECTORY_SEPARATOR . 'attendance_records.json',
	$cacheDir . DIRECTORY_SEPARATOR . 'leave_requests.json',
	$cacheDir . DIRECTORY_SEPARATOR . 'loan_requests.json',
	$cacheDir . DIRECTORY_SEPARATOR . 'overtime_records.json',
);

$updatedReferenceRows = 0;
foreach ($jsonArrayFiles as $path) {
	if (!is_file($path)) {
		continue;
	}

	$rawFile = @file_get_contents($path);
	if ($rawFile === false || trim($rawFile) === '') {
		continue;
	}
	if (strncmp($rawFile, "\xEF\xBB\xBF", 3) === 0) {
		$rawFile = substr($rawFile, 3);
	}

	$data = json_decode($rawFile, true);
	if (!is_array($data)) {
		continue;
	}

	$changed = false;
	for ($i = 0; $i < count($data); $i += 1) {
		if (!is_array($data[$i]) || !isset($data[$i]['username'])) {
			continue;
		}
		$current = strtolower(trim((string) $data[$i]['username']));
		if ($current === '' || !isset($usernameMap[$current])) {
			continue;
		}
		$data[$i]['username'] = $usernameMap[$current];
		$changed = true;
		$updatedReferenceRows += 1;
	}

	if ($changed) {
		@copy($path, $path . '.bak_' . $timestamp);
		if (!write_json_file($path, $data)) {
			fwrite(STDERR, "Gagal menyimpan {$path}\n");
			exit(1);
		}
	}
}

echo "Migrasi selesai.\n";
echo "User karyawan diproses: {$updatedUserCount}\n";
echo "Referensi username diperbarui: {$updatedReferenceRows}\n";
echo "Backup timestamp: {$timestamp}\n";
