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
			return antifraud_env_value($name, $fallback);
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
			'weight_timezone_mismatch'    => (float) $env('ANTIFRAUD_WEIGHT_TIMEZONE_MISMATCH', 20),
			'weight_gps_static_pattern'   => (float) $env('ANTIFRAUD_WEIGHT_GPS_STATIC_PATTERN', 25),
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
			'office_timezone'             => trim((string) $env('ANTIFRAUD_OFFICE_TIMEZONE', 'Asia/Jakarta')),
			'timezone_offset_tolerance_min' => (int) $env('ANTIFRAUD_TIMEZONE_OFFSET_TOLERANCE_MIN', 30),
			'gps_static_min_samples'      => (int) $env('ANTIFRAUD_GPS_STATIC_MIN_SAMPLES', 3),
			'gps_static_window_s'         => (int) $env('ANTIFRAUD_GPS_STATIC_WINDOW_S', 30),
			'gps_static_min_span_s'       => (int) $env('ANTIFRAUD_GPS_STATIC_MIN_SPAN_S', 6),
			'gps_static_coord_delta_deg'  => (float) $env('ANTIFRAUD_GPS_STATIC_COORD_DELTA_DEG', 0.0000005),
			'gps_static_accuracy_delta_m' => (float) $env('ANTIFRAUD_GPS_STATIC_ACCURACY_DELTA_M', 0.5),

			// Rate limiting
			'rate_limit_window_s'         => (int) $env('ANTIFRAUD_RATE_LIMIT_WINDOW_S', 60),
			'rate_limit_max_per_user'     => (int) $env('ANTIFRAUD_RATE_LIMIT_MAX_PER_USER', 5),
			'rate_limit_max_per_ip'       => (int) $env('ANTIFRAUD_RATE_LIMIT_MAX_PER_IP', 10),

			// Nonce TTL
			'nonce_ttl_s'                 => (int) $env('ANTIFRAUD_NONCE_TTL_S', 120),

			// Enable/disable
			'enabled'                     => antifraud_truthy($env('ANTIFRAUD_ENABLED', 'true')),
			'log_only_mode'               => antifraud_truthy($env('ANTIFRAUD_LOG_ONLY_MODE', 'false')),
			// Secure-by-default: when env key is missing, strict attestation is ON.
			'require_attestation_pass'    => antifraud_truthy($env('ANTIFRAUD_REQUIRE_ATTESTATION_PASS', 'true')),

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

function antifraud_env_value($name, $fallback = '')
{
	$key = trim((string) $name);
	if ($key === '')
	{
		return $fallback;
	}

	$v = getenv($key);
	if ($v !== FALSE && $v !== '')
	{
		return $v;
	}

	if (isset($_ENV[$key]) && $_ENV[$key] !== '')
	{
		return $_ENV[$key];
	}

	if (isset($_SERVER[$key]) && $_SERVER[$key] !== '')
	{
		return $_SERVER[$key];
	}

	return $fallback;
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

	// 6b. Office geofence but timezone offset mismatch (strong spoof signal for browser sensors)
	$timezone_result = antifraud_check_timezone_mismatch_in_office($client, $geofence_inside, $cfg);
	if ($timezone_result['flagged'])
	{
		$score += $cfg['weight_timezone_mismatch'];
		$flags[] = 'timezone_mismatch_in_office';
		$details['timezone_mismatch_in_office'] = $timezone_result;
		$details['timezone_mismatch_in_office']['weight'] = $cfg['weight_timezone_mismatch'];
	}

	// 6c. Static GPS samples over time (common for DevTools/fake provider)
	$gps_static_result = antifraud_check_gps_static_pattern($client, $cfg);
	if ($gps_static_result['flagged'])
	{
		$score += $cfg['weight_gps_static_pattern'];
		$flags[] = 'gps_static_pattern';
		$details['gps_static_pattern'] = $gps_static_result;
		$details['gps_static_pattern']['weight'] = $cfg['weight_gps_static_pattern'];
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

	// 8. Attestation verdict handling
	$attestation_verdict = isset($client['attestation_verdict'])
		? strtoupper(trim((string) $client['attestation_verdict']))
		: '';
	$attestation_reason = isset($client['attestation_reason'])
		? trim((string) $client['attestation_reason'])
		: '';

	// Strict mode: normal attendance requires PASS verdict.
	if (!empty($cfg['require_attestation_pass']))
	{
		if ($attestation_verdict !== 'PASS')
		{
			$score += $cfg['weight_attestation_fail'];
			$flags[] = 'attestation_required_not_pass';
			$details['attestation_required_not_pass'] = array(
				'weight' => $cfg['weight_attestation_fail'],
				'verdict' => $attestation_verdict !== '' ? $attestation_verdict : 'NONE',
				'reason' => $attestation_reason
			);
		}
	}
	else
	{
		// Non-strict mode: only explicit FAIL adds risk.
		if ($attestation_verdict === 'FAIL')
		{
			$score += $cfg['weight_attestation_fail'];
			$flags[] = 'attestation_fail';
			$details['attestation_fail'] = array(
				'weight' => $cfg['weight_attestation_fail'],
				'reason' => $attestation_reason
			);
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

function antifraud_check_timezone_mismatch_in_office($client, $geofence_inside, $cfg)
{
	if (!$geofence_inside)
	{
		return array('flagged' => FALSE);
	}

	$office_timezone = isset($cfg['office_timezone']) ? trim((string) $cfg['office_timezone']) : '';
	if ($office_timezone === '')
	{
		return array('flagged' => FALSE);
	}

	$client_timezone = isset($client['client_timezone']) ? trim((string) $client['client_timezone']) : '';
	$client_offset_raw = isset($client['client_tz_offset_min']) ? trim((string) $client['client_tz_offset_min']) : '';
	if ($client_timezone === '' && $client_offset_raw === '')
	{
		return array('flagged' => FALSE);
	}

	try
	{
		$office_tz = new DateTimeZone($office_timezone);
		$office_now = new DateTime('now', $office_tz);
		// JS getTimezoneOffset uses opposite sign from UTC offset.
		$expected_js_offset_min = (int) round(-1 * ($office_now->getOffset() / 60));
	}
	catch (Exception $e)
	{
		return array('flagged' => FALSE);
	}

	$client_offset_min = NULL;
	if ($client_offset_raw !== '' && is_numeric($client_offset_raw))
	{
		$client_offset_min = (int) round((float) $client_offset_raw);
	}

	if ($client_offset_min === NULL)
	{
		return array('flagged' => FALSE);
	}

	$tolerance_min = isset($cfg['timezone_offset_tolerance_min']) ? (int) $cfg['timezone_offset_tolerance_min'] : 30;
	if ($tolerance_min < 0)
	{
		$tolerance_min = 0;
	}
	$offset_diff_min = abs($client_offset_min - $expected_js_offset_min);
	if ($offset_diff_min <= $tolerance_min)
	{
		return array('flagged' => FALSE);
	}

	return array(
		'flagged' => TRUE,
		'office_timezone' => $office_timezone,
		'client_timezone' => $client_timezone,
		'client_offset_min' => $client_offset_min,
		'expected_offset_min' => $expected_js_offset_min,
		'offset_diff_min' => $offset_diff_min,
		'tolerance_min' => $tolerance_min
	);
}

function antifraud_check_gps_static_pattern($client, $cfg)
{
	$raw = isset($client['gps_samples_json']) ? trim((string) $client['gps_samples_json']) : '';
	if ($raw === '')
	{
		return array('flagged' => FALSE);
	}

	$decoded = @json_decode($raw, TRUE);
	if (!is_array($decoded) || empty($decoded))
	{
		return array('flagged' => FALSE);
	}

	$samples = array();
	for ($i = 0; $i < count($decoded); $i += 1)
	{
		$row = $decoded[$i];
		if (!is_array($row))
		{
			continue;
		}
		$lat = isset($row['lat']) ? $row['lat'] : NULL;
		$lng = isset($row['lng']) ? $row['lng'] : NULL;
		$accuracy = isset($row['accuracy']) ? $row['accuracy'] : NULL;
		$timestamp = isset($row['timestamp']) ? $row['timestamp'] : NULL;
		if (!is_numeric($lat) || !is_numeric($lng) || !is_numeric($accuracy) || !is_numeric($timestamp))
		{
			continue;
		}
		$ts = (int) round((float) $timestamp);
		if ($ts > 9999999999)
		{
			$ts = (int) round($ts / 1000);
		}
		$samples[] = array(
			'lat' => (float) $lat,
			'lng' => (float) $lng,
			'accuracy' => (float) $accuracy,
			'timestamp' => $ts
		);
	}

	$min_samples = isset($cfg['gps_static_min_samples']) ? (int) $cfg['gps_static_min_samples'] : 3;
	if ($min_samples < 2)
	{
		$min_samples = 2;
	}
	if (count($samples) < $min_samples)
	{
		return array('flagged' => FALSE);
	}

	usort($samples, function ($a, $b) {
		$at = isset($a['timestamp']) ? (int) $a['timestamp'] : 0;
		$bt = isset($b['timestamp']) ? (int) $b['timestamp'] : 0;
		return $at <=> $bt;
	});

	$window_s = isset($cfg['gps_static_window_s']) ? (int) $cfg['gps_static_window_s'] : 30;
	if ($window_s <= 0)
	{
		$window_s = 30;
	}
	$latest_ts = (int) $samples[count($samples) - 1]['timestamp'];
	$window_start = $latest_ts - $window_s;
	$window_samples = array();
	for ($i = 0; $i < count($samples); $i += 1)
	{
		if ((int) $samples[$i]['timestamp'] >= $window_start)
		{
			$window_samples[] = $samples[$i];
		}
	}
	if (count($window_samples) < $min_samples)
	{
		return array('flagged' => FALSE);
	}

	$first_ts = (int) $window_samples[0]['timestamp'];
	$span_s = $latest_ts - $first_ts;
	$min_span_s = isset($cfg['gps_static_min_span_s']) ? (int) $cfg['gps_static_min_span_s'] : 6;
	if ($span_s < $min_span_s)
	{
		return array('flagged' => FALSE);
	}

	$lat_min = $window_samples[0]['lat'];
	$lat_max = $window_samples[0]['lat'];
	$lng_min = $window_samples[0]['lng'];
	$lng_max = $window_samples[0]['lng'];
	$acc_min = $window_samples[0]['accuracy'];
	$acc_max = $window_samples[0]['accuracy'];
	$unique_6dp = array();

	for ($i = 0; $i < count($window_samples); $i += 1)
	{
		$sample = $window_samples[$i];
		$lat = (float) $sample['lat'];
		$lng = (float) $sample['lng'];
		$acc = (float) $sample['accuracy'];
		if ($lat < $lat_min) $lat_min = $lat;
		if ($lat > $lat_max) $lat_max = $lat;
		if ($lng < $lng_min) $lng_min = $lng;
		if ($lng > $lng_max) $lng_max = $lng;
		if ($acc < $acc_min) $acc_min = $acc;
		if ($acc > $acc_max) $acc_max = $acc;
		$key = number_format($lat, 6, '.', '').','.number_format($lng, 6, '.', '');
		$unique_6dp[$key] = TRUE;
	}

	$coord_delta = isset($cfg['gps_static_coord_delta_deg']) ? (float) $cfg['gps_static_coord_delta_deg'] : 0.0000005;
	$acc_delta = isset($cfg['gps_static_accuracy_delta_m']) ? (float) $cfg['gps_static_accuracy_delta_m'] : 0.5;
	$lat_span = $lat_max - $lat_min;
	$lng_span = $lng_max - $lng_min;
	$acc_span = $acc_max - $acc_min;
	$is_static = $lat_span <= $coord_delta
		&& $lng_span <= $coord_delta
		&& $acc_span <= $acc_delta
		&& count($unique_6dp) <= 1;

	if (!$is_static)
	{
		return array('flagged' => FALSE);
	}

	return array(
		'flagged' => TRUE,
		'samples_count' => count($window_samples),
		'span_s' => $span_s,
		'lat_span' => $lat_span,
		'lng_span' => $lng_span,
		'accuracy_span' => $acc_span,
		'unique_6dp' => count($unique_6dp),
		'window_s' => $window_s
	);
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
		'client_timezone'      => trim((string) $input->post('client_timezone', TRUE)),
		'client_locale'        => trim((string) $input->post('client_locale', TRUE)),
		'client_tz_offset_min' => trim((string) $input->post('client_tz_offset_min', TRUE)),
		'gps_samples_json'     => trim((string) $input->post('gps_samples_json', TRUE)),
		'client_timestamp'     => trim((string) $input->post('client_timestamp', TRUE)),
		'integrity_token'      => trim((string) $input->post('integrity_token', TRUE)),
		'payload_signature'    => trim((string) $input->post('payload_signature', TRUE)),
	);
}
