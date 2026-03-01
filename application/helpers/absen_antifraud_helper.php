<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Anti-fraud helper for attendance submissions.
 * Provides risk scoring, rate limiting, impossible-travel detection,
 * audit logging, and client signal processing.
 */

// ---------------------------------------------------------------------------
// Config loader – all thresholds come from env/dotenv with safe defaults
// ---------------------------------------------------------------------------

function antifraud_config($key = NULL, $default = NULL)
{
	static $cfg = NULL;
	if ($cfg === NULL)
	{
		$env = function ($name, $fallback) {
			$v = getenv($name);
			if ($v === FALSE || $v === '')
			{
				return $fallback;
			}
			return $v;
		};

		$cfg = array(
			// Risk score weights
			'weight_geofence_fail'        => (float) $env('ANTIFRAUD_WEIGHT_GEOFENCE_FAIL', 40),
			'weight_accuracy_suspicious'  => (float) $env('ANTIFRAUD_WEIGHT_ACCURACY_SUSPICIOUS', 15),
			'weight_accuracy_perfect'     => (float) $env('ANTIFRAUD_WEIGHT_ACCURACY_PERFECT', 20),
			'weight_impossible_travel'    => (float) $env('ANTIFRAUD_WEIGHT_IMPOSSIBLE_TRAVEL', 35),
			'weight_jump_distance'        => (float) $env('ANTIFRAUD_WEIGHT_JUMP_DISTANCE', 25),
			'weight_rate_limit'           => (float) $env('ANTIFRAUD_WEIGHT_RATE_LIMIT', 30),
			'weight_mock_suspected'       => (float) $env('ANTIFRAUD_WEIGHT_MOCK_SUSPECTED', 30),
			'weight_dev_options'          => (float) $env('ANTIFRAUD_WEIGHT_DEV_OPTIONS', 10),
			'weight_rooted'               => (float) $env('ANTIFRAUD_WEIGHT_ROOTED', 15),
			'weight_attestation_fail'     => (float) $env('ANTIFRAUD_WEIGHT_ATTESTATION_FAIL', 40),
			'weight_client_time_drift'    => (float) $env('ANTIFRAUD_WEIGHT_CLIENT_TIME_DRIFT', 10),

			// Thresholds
			'risk_threshold'              => (float) $env('ANTIFRAUD_RISK_THRESHOLD', 40),
			'accuracy_poor_m'             => (float) $env('ANTIFRAUD_ACCURACY_POOR_M', 200),
			'accuracy_perfect_m'          => (float) $env('ANTIFRAUD_ACCURACY_PERFECT_M', 1.0),
			'max_travel_speed_kmh'        => (float) $env('ANTIFRAUD_MAX_TRAVEL_SPEED_KMH', 120),
			'jump_distance_m'             => (float) $env('ANTIFRAUD_JUMP_DISTANCE_M', 50000),
			'jump_time_window_s'          => (int) $env('ANTIFRAUD_JUMP_TIME_WINDOW_S', 600),
			'client_time_drift_s'         => (int) $env('ANTIFRAUD_CLIENT_TIME_DRIFT_S', 300),

			// Rate limiting
			'rate_limit_window_s'         => (int) $env('ANTIFRAUD_RATE_LIMIT_WINDOW_S', 60),
			'rate_limit_max_per_user'     => (int) $env('ANTIFRAUD_RATE_LIMIT_MAX_PER_USER', 5),
			'rate_limit_max_per_ip'       => (int) $env('ANTIFRAUD_RATE_LIMIT_MAX_PER_IP', 10),

			// Nonce TTL
			'nonce_ttl_s'                 => (int) $env('ANTIFRAUD_NONCE_TTL_S', 120),

			// Enable/disable
			'enabled'                     => antifraud_truthy($env('ANTIFRAUD_ENABLED', 'true')),
			'log_only_mode'               => antifraud_truthy($env('ANTIFRAUD_LOG_ONLY_MODE', 'false')),

			// Audit log path
			'audit_log_dir'               => rtrim((string) $env('ANTIFRAUD_AUDIT_LOG_DIR', APPPATH.'cache/antifraud_logs'), '/\\'),
		);
	}

	if ($key === NULL)
	{
		return $cfg;
	}

	return isset($cfg[$key]) ? $cfg[$key] : $default;
}

// ---------------------------------------------------------------------------
// Risk scoring engine
// ---------------------------------------------------------------------------

/**
 * Evaluate risk for an attendance submission.
 *
 * @param array $params Keys:
 *   username, latitude, longitude, accuracy_m, distance_m, ip,
 *   client_signals (optional array with device_id, is_mock_suspected, etc.)
 * @return array [score => float, flags => string[], details => assoc[]]
 */
function antifraud_evaluate_risk($params)
{
	$score = 0.0;
	$flags = array();
	$details = array();

	$username   = isset($params['username']) ? (string) $params['username'] : '';
	$lat        = isset($params['latitude']) ? (float) $params['latitude'] : 0;
	$lng        = isset($params['longitude']) ? (float) $params['longitude'] : 0;
	$accuracy   = isset($params['accuracy_m']) ? (float) $params['accuracy_m'] : 0;
	$distance   = isset($params['distance_m']) ? (float) $params['distance_m'] : 0;
	$ip         = isset($params['ip']) ? (string) $params['ip'] : '';
	$geofence_inside = isset($params['geofence_inside']) ? (bool) $params['geofence_inside'] : TRUE;
	$client     = isset($params['client_signals']) && is_array($params['client_signals'])
		? $params['client_signals'] : array();

	$cfg = antifraud_config();

	// 1. Geofence strict failure
	if (!$geofence_inside)
	{
		$score += $cfg['weight_geofence_fail'];
		$flags[] = 'geofence_fail';
		$details['geofence_fail'] = array(
			'distance_m' => round($distance, 2),
			'weight' => $cfg['weight_geofence_fail']
		);
	}

	// 2. Suspicious GPS accuracy
	if ($accuracy > $cfg['accuracy_poor_m'])
	{
		$score += $cfg['weight_accuracy_suspicious'];
		$flags[] = 'accuracy_poor';
		$details['accuracy_poor'] = array(
			'accuracy_m' => round($accuracy, 2),
			'threshold_m' => $cfg['accuracy_poor_m'],
			'weight' => $cfg['weight_accuracy_suspicious']
		);
	}
	elseif ($accuracy > 0 && $accuracy < $cfg['accuracy_perfect_m'])
	{
		$score += $cfg['weight_accuracy_perfect'];
		$flags[] = 'accuracy_suspiciously_perfect';
		$details['accuracy_suspiciously_perfect'] = array(
			'accuracy_m' => round($accuracy, 4),
			'threshold_m' => $cfg['accuracy_perfect_m'],
			'weight' => $cfg['weight_accuracy_perfect']
		);
	}

	// 3. Impossible travel speed vs previous trusted point
	$travel_result = antifraud_check_impossible_travel($username, $lat, $lng);
	if ($travel_result['flagged'])
	{
		$score += $cfg['weight_impossible_travel'];
		$flags[] = 'impossible_travel';
		$details['impossible_travel'] = $travel_result;
		$details['impossible_travel']['weight'] = $cfg['weight_impossible_travel'];
	}

	// 4. Abnormal jump distance in short time
	$jump_result = antifraud_check_jump_distance($username, $lat, $lng);
	if ($jump_result['flagged'])
	{
		$score += $cfg['weight_jump_distance'];
		$flags[] = 'jump_distance';
		$details['jump_distance'] = $jump_result;
		$details['jump_distance']['weight'] = $cfg['weight_jump_distance'];
	}

	// 5. Rate limit check
	$rate_result = antifraud_check_rate_limit($username, $ip);
	if ($rate_result['flagged'])
	{
		$score += $cfg['weight_rate_limit'];
		$flags[] = 'rate_limit';
		$details['rate_limit'] = $rate_result;
		$details['rate_limit']['weight'] = $cfg['weight_rate_limit'];
	}

	// 6. Client-reported signals
	if (!empty($client['is_mock_suspected']) && antifraud_truthy($client['is_mock_suspected']))
	{
		$score += $cfg['weight_mock_suspected'];
		$flags[] = 'mock_location_suspected';
		$details['mock_location_suspected'] = array('weight' => $cfg['weight_mock_suspected']);
	}

	if (!empty($client['is_dev_options_on']) && antifraud_truthy($client['is_dev_options_on']))
	{
		$score += $cfg['weight_dev_options'];
		$flags[] = 'dev_options_on';
		$details['dev_options_on'] = array('weight' => $cfg['weight_dev_options']);
	}

	if (!empty($client['is_rooted_suspected']) && antifraud_truthy($client['is_rooted_suspected']))
	{
		$score += $cfg['weight_rooted'];
		$flags[] = 'rooted_suspected';
		$details['rooted_suspected'] = array('weight' => $cfg['weight_rooted']);
	}

	// 7. Client timestamp drift
	if (!empty($client['client_timestamp']))
	{
		$client_ts = (int) $client['client_timestamp'];
		$server_ts = time();
		$drift = abs($server_ts - $client_ts);
		if ($drift > $cfg['client_time_drift_s'])
		{
			$score += $cfg['weight_client_time_drift'];
			$flags[] = 'client_time_drift';
			$details['client_time_drift'] = array(
				'drift_seconds' => $drift,
				'threshold' => $cfg['client_time_drift_s'],
				'weight' => $cfg['weight_client_time_drift']
			);
		}
	}

	// 8. Attestation verdict (if available)
	if (isset($client['attestation_verdict']))
	{
		$verdict = strtoupper(trim((string) $client['attestation_verdict']));
		if ($verdict === 'FAIL')
		{
			$score += $cfg['weight_attestation_fail'];
			$flags[] = 'attestation_fail';
			$details['attestation_fail'] = array('weight' => $cfg['weight_attestation_fail']);
		}
	}

	return array(
		'score'     => round($score, 2),
		'threshold' => $cfg['risk_threshold'],
		'flagged'   => $score >= $cfg['risk_threshold'],
		'flags'     => $flags,
		'details'   => $details
	);
}

// ---------------------------------------------------------------------------
// Impossible travel detection
// ---------------------------------------------------------------------------

function antifraud_check_impossible_travel($username, $lat, $lng)
{
	$cfg = antifraud_config();
	$history = antifraud_load_user_location_history($username);

	if (empty($history))
	{
		return array('flagged' => FALSE);
	}

	// Get most recent trusted point.
	$last = NULL;
	for ($i = count($history) - 1; $i >= 0; $i -= 1)
	{
		$is_trusted = isset($history[$i]['trusted']) ? (bool) $history[$i]['trusted'] : FALSE;
		if ($is_trusted)
		{
			$last = $history[$i];
			break;
		}
	}
	if (!is_array($last))
	{
		return array('flagged' => FALSE);
	}

	$prev_lat = (float) $last['lat'];
	$prev_lng = (float) $last['lng'];
	$prev_time = (int) $last['timestamp'];
	$now = time();
	$elapsed_s = $now - $prev_time;

	if ($elapsed_s <= 0)
	{
		return array('flagged' => FALSE);
	}

	$distance_m = antifraud_haversine($prev_lat, $prev_lng, $lat, $lng);
	$speed_kmh = ($distance_m / 1000.0) / ($elapsed_s / 3600.0);

	if ($speed_kmh > $cfg['max_travel_speed_kmh'] && $distance_m > 1000)
	{
		return array(
			'flagged' => TRUE,
			'speed_kmh' => round($speed_kmh, 2),
			'max_kmh' => $cfg['max_travel_speed_kmh'],
			'distance_m' => round($distance_m, 2),
			'elapsed_s' => $elapsed_s,
			'prev_lat' => $prev_lat,
			'prev_lng' => $prev_lng
		);
	}

	return array('flagged' => FALSE);
}

// ---------------------------------------------------------------------------
// Jump distance detection (large distance in very short window)
// ---------------------------------------------------------------------------

function antifraud_check_jump_distance($username, $lat, $lng)
{
	$cfg = antifraud_config();
	$history = antifraud_load_user_location_history($username);

	if (empty($history))
	{
		return array('flagged' => FALSE);
	}

	$now = time();
	$window_start = $now - $cfg['jump_time_window_s'];

	// Check recent points within short time window
	foreach (array_reverse($history) as $point)
	{
		$is_trusted = isset($point['trusted']) ? (bool) $point['trusted'] : FALSE;
		if (!$is_trusted)
		{
			continue;
		}

		$pt_time = (int) $point['timestamp'];
		if ($pt_time < $window_start)
		{
			break;
		}

		$distance_m = antifraud_haversine((float) $point['lat'], (float) $point['lng'], $lat, $lng);
		if ($distance_m > $cfg['jump_distance_m'])
		{
			return array(
				'flagged' => TRUE,
				'distance_m' => round($distance_m, 2),
				'threshold_m' => $cfg['jump_distance_m'],
				'time_window_s' => $cfg['jump_time_window_s'],
				'elapsed_s' => $now - $pt_time
			);
		}
	}

	return array('flagged' => FALSE);
}

// ---------------------------------------------------------------------------
// Rate limiting (per-user + per-IP)
// ---------------------------------------------------------------------------

function antifraud_check_rate_limit($username, $ip)
{
	$cfg = antifraud_config();
	$username = strtolower(trim((string) $username));
	if ($username === '')
	{
		$username = '__anonymous__';
	}

	$now = time();
	$window = $cfg['rate_limit_window_s'];
	$state = antifraud_load_rate_limit_state();

	// Clean expired entries
	$cutoff = $now - $window;
	$changed = FALSE;

	if (isset($state['users'][$username]))
	{
		$state['users'][$username] = array_values(array_filter(
			$state['users'][$username],
			function ($ts) use ($cutoff) { return $ts > $cutoff; }
		));
		$changed = TRUE;
	}

	if ($ip !== '' && isset($state['ips'][$ip]))
	{
		$state['ips'][$ip] = array_values(array_filter(
			$state['ips'][$ip],
			function ($ts) use ($cutoff) { return $ts > $cutoff; }
		));
		$changed = TRUE;
	}

	$user_count = isset($state['users'][$username]) ? count($state['users'][$username]) : 0;
	$ip_count = ($ip !== '' && isset($state['ips'][$ip])) ? count($state['ips'][$ip]) : 0;

	$flagged = $user_count >= $cfg['rate_limit_max_per_user']
		|| $ip_count >= $cfg['rate_limit_max_per_ip'];

	return array(
		'flagged' => $flagged,
		'user_count' => $user_count,
		'user_max' => $cfg['rate_limit_max_per_user'],
		'ip_count' => $ip_count,
		'ip_max' => $cfg['rate_limit_max_per_ip'],
		'window_s' => $window
	);
}

/**
 * Record a submission attempt for rate limiting.
 * Call this AFTER validation passes (even if risk was flagged).
 */
function antifraud_record_rate_limit_hit($username, $ip)
{
	$username = strtolower(trim((string) $username));
	if ($username === '')
	{
		$username = '__anonymous__';
	}

	$now = time();
	$state = antifraud_load_rate_limit_state();

	if (!isset($state['users'][$username]))
	{
		$state['users'][$username] = array();
	}
	$state['users'][$username][] = $now;

	if ($ip !== '')
	{
		if (!isset($state['ips'][$ip]))
		{
			$state['ips'][$ip] = array();
		}
		$state['ips'][$ip][] = $now;
	}

	antifraud_save_rate_limit_state($state);
}

// ---------------------------------------------------------------------------
// Location history (for travel/jump checks)
// ---------------------------------------------------------------------------

function antifraud_record_location($username, $lat, $lng, $trusted = TRUE)
{
	$username = strtolower(trim((string) $username));
	if ($username === '')
	{
		return;
	}

	$history = antifraud_load_user_location_history($username);

	$history[] = array(
		'lat' => round((float) $lat, 7),
		'lng' => round((float) $lng, 7),
		'timestamp' => time(),
		'trusted' => $trusted
	);

	// Keep only last 50 entries per user
	if (count($history) > 50)
	{
		$history = array_slice($history, -50);
	}

	antifraud_save_user_location_history($username, $history);
}

function antifraud_load_user_location_history($username)
{
	$username = strtolower(trim((string) $username));
	if ($username === '')
	{
		return array();
	}

	$dir = antifraud_config('audit_log_dir');
	$file = $dir.'/location_history/'.preg_replace('/[^a-zA-Z0-9_\-]/', '_', $username).'.json';

	if (!is_file($file))
	{
		return array();
	}

	$data = @json_decode(@file_get_contents($file), TRUE);
	return is_array($data) ? $data : array();
}

function antifraud_save_user_location_history($username, $history)
{
	$username = strtolower(trim((string) $username));
	if ($username === '')
	{
		return;
	}

	$dir = antifraud_config('audit_log_dir').'/location_history';
	antifraud_ensure_dir($dir);

	$file = $dir.'/'.preg_replace('/[^a-zA-Z0-9_\-]/', '_', $username).'.json';
	@file_put_contents($file, json_encode($history, JSON_PRETTY_PRINT), LOCK_EX);
}

// ---------------------------------------------------------------------------
// Rate limit state persistence
// ---------------------------------------------------------------------------

function antifraud_load_rate_limit_state()
{
	$file = antifraud_config('audit_log_dir').'/rate_limit_state.json';
	if (!is_file($file))
	{
		return array('users' => array(), 'ips' => array());
	}

	$data = @json_decode(@file_get_contents($file), TRUE);
	if (!is_array($data))
	{
		return array('users' => array(), 'ips' => array());
	}

	if (!isset($data['users']) || !is_array($data['users']))
	{
		$data['users'] = array();
	}
	if (!isset($data['ips']) || !is_array($data['ips']))
	{
		$data['ips'] = array();
	}

	return $data;
}

function antifraud_save_rate_limit_state($state)
{
	$dir = antifraud_config('audit_log_dir');
	antifraud_ensure_dir($dir);

	$file = $dir.'/rate_limit_state.json';
	@file_put_contents($file, json_encode($state), LOCK_EX);
}

// ---------------------------------------------------------------------------
// Audit logging
// ---------------------------------------------------------------------------

/**
 * Write an anti-fraud audit log entry.
 */
function antifraud_audit_log($entry)
{
	$dir = antifraud_config('audit_log_dir');
	antifraud_ensure_dir($dir);

	$date_key = date('Y-m-d');
	$file = $dir.'/audit_'.$date_key.'.jsonl';

	$row = array_merge(array(
		'logged_at' => date('Y-m-d H:i:s'),
		'timestamp' => time()
	), $entry);

	@file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND | LOCK_EX);
}

// ---------------------------------------------------------------------------
// Nonce management (Phase 3)
// ---------------------------------------------------------------------------

function antifraud_generate_nonce()
{
	$nonce = bin2hex(random_bytes(16));
	$ttl = antifraud_config('nonce_ttl_s', 120);

	$store = antifraud_load_nonce_store();
	// Prune expired
	$now = time();
	foreach ($store as $k => $v)
	{
		if ($v['expires_at'] < $now)
		{
			unset($store[$k]);
		}
	}

	$store[$nonce] = array(
		'created_at' => $now,
		'expires_at' => $now + $ttl,
		'used' => FALSE
	);

	antifraud_save_nonce_store($store);

	return array(
		'nonce' => $nonce,
		'expires_at' => $now + $ttl,
		'ttl_s' => $ttl
	);
}

/**
 * Validate and consume a nonce (single-use).
 * @return array [valid => bool, reason => string]
 */
function antifraud_validate_nonce($nonce)
{
	$nonce = trim((string) $nonce);
	if ($nonce === '')
	{
		return array('valid' => FALSE, 'reason' => 'empty');
	}

	$store = antifraud_load_nonce_store();
	$now = time();

	if (!isset($store[$nonce]))
	{
		return array('valid' => FALSE, 'reason' => 'unknown');
	}

	$entry = $store[$nonce];

	if ($entry['expires_at'] < $now)
	{
		unset($store[$nonce]);
		antifraud_save_nonce_store($store);
		return array('valid' => FALSE, 'reason' => 'expired');
	}

	if ($entry['used'] === TRUE)
	{
		return array('valid' => FALSE, 'reason' => 'already_used');
	}

	// Mark as used and remove
	unset($store[$nonce]);
	antifraud_save_nonce_store($store);

	return array('valid' => TRUE, 'reason' => 'ok');
}

function antifraud_load_nonce_store()
{
	$file = antifraud_config('audit_log_dir').'/nonce_store.json';
	if (!is_file($file))
	{
		return array();
	}
	$data = @json_decode(@file_get_contents($file), TRUE);
	return is_array($data) ? $data : array();
}

function antifraud_save_nonce_store($store)
{
	$dir = antifraud_config('audit_log_dir');
	antifraud_ensure_dir($dir);

	$file = $dir.'/nonce_store.json';
	@file_put_contents($file, json_encode($store), LOCK_EX);
}

// ---------------------------------------------------------------------------
// Attestation verification stub (Phase 3)
// ---------------------------------------------------------------------------

/**
 * Verify an attestation payload.
 * Currently a structured stub – returns verdict based on nonce validation.
 * When real Play Integrity / SafetyNet is integrated, extend this.
 *
 * @param array $payload Keys: nonce, integrity_token, payload_signature
 * @return array [verdict => PASS|FAIL|UNKNOWN, reason => string]
 */
function antifraud_verify_attestation($payload)
{
	$nonce = isset($payload['nonce']) ? trim((string) $payload['nonce']) : '';
	$integrity_token = isset($payload['integrity_token']) ? trim((string) $payload['integrity_token']) : '';

	// Step 1: Validate nonce
	if ($nonce === '')
	{
		return array('verdict' => 'UNKNOWN', 'reason' => 'no_nonce_provided');
	}

	$nonce_result = antifraud_validate_nonce($nonce);
	if (!$nonce_result['valid'])
	{
		return array('verdict' => 'FAIL', 'reason' => 'nonce_'.$nonce_result['reason']);
	}

	// Step 2: Integrity token verification (stub)
	// TODO: When Play Integrity API is integrated, decode and verify
	// the integrity_token using Google's API with server-side key.
	if ($integrity_token === '')
	{
		return array('verdict' => 'UNKNOWN', 'reason' => 'no_integrity_token');
	}

	// Placeholder: token present but cannot verify yet
	return array('verdict' => 'UNKNOWN', 'reason' => 'verification_not_implemented');
}

// ---------------------------------------------------------------------------
// Utility functions
// ---------------------------------------------------------------------------

function antifraud_haversine($lat1, $lng1, $lat2, $lng2)
{
	$R = 6371000.0;
	$lat1_rad = deg2rad($lat1);
	$lat2_rad = deg2rad($lat2);
	$dlat = deg2rad($lat2 - $lat1);
	$dlng = deg2rad($lng2 - $lng1);

	$a = sin($dlat / 2) * sin($dlat / 2)
		+ cos($lat1_rad) * cos($lat2_rad)
		* sin($dlng / 2) * sin($dlng / 2);

	return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function antifraud_truthy($val)
{
	if (is_bool($val)) return $val;
	if (is_numeric($val)) return (int) $val > 0;
	$s = strtolower(trim((string) $val));
	return $s === 'true' || $s === '1' || $s === 'yes';
}

function antifraud_ensure_dir($dir)
{
	if (!is_dir($dir))
	{
		@mkdir($dir, 0755, TRUE);
	}
}

function antifraud_client_ip()
{
	$CI =& get_instance();
	$ip = $CI->input->ip_address();
	if ($ip === '0.0.0.0' || $ip === '')
	{
		$ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
	}
	return $ip;
}

/**
 * Extract client security signals from POST data.
 * Returns safe defaults if fields are absent (backward-compatible).
 */
function antifraud_extract_client_signals($input)
{
	return array(
		'device_id'            => trim((string) $input->post('device_id', TRUE)),
		'attestation_nonce'    => trim((string) $input->post('attestation_nonce', TRUE)),
		'attestation_verdict'  => trim((string) $input->post('attestation_verdict', TRUE)),
		'attestation_reason'   => trim((string) $input->post('attestation_reason', TRUE)),
		'is_mock_suspected'    => trim((string) $input->post('is_mock_suspected', TRUE)),
		'is_dev_options_on'    => trim((string) $input->post('is_dev_options_on', TRUE)),
		'is_rooted_suspected'  => trim((string) $input->post('is_rooted_suspected', TRUE)),
		'client_timestamp'     => trim((string) $input->post('client_timestamp', TRUE)),
		'integrity_token'      => trim((string) $input->post('integrity_token', TRUE)),
		'payload_signature'    => trim((string) $input->post('payload_signature', TRUE)),
	);
}
