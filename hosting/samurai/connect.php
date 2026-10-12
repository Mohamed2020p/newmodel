<?php
/**
 * samurai/connect.php — THE endpoint the SAMURAI app calls (PWF Auth protocol).
 *
 * App URL (patched inside libsamurai.so): http://domi74.page.gd/samurai/connect.php
 *
 * VERIFIED FROM THE BINARY (libsamurai.so):
 *   - the only client-side key check is NON-EMPTY ("The license key is required.")
 *   - request JSON field:  "license_key"  (+ "hwid")
 *   - reply JSON fields:   "success", "message", "expires_at" (ISO-8601 UTC),
 *                          "session_id"   → heartbeat uses the session
 *   - errors the app shows: INVALID_KEY, "The login reply did not contain a
 *     valid expiry.", "... a session ID.", "The license server rejected..." etc.
 *
 * STRICT_KEYS=false (takeover): unknown keys are accepted as LIFETIME.
 * STRICT_KEYS=true: only keys from main.txt (banned keys always fail).
 */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');

if (rate_limited()) {
    echo json_encode(['success' => false, 'message' => 'Too many attempts, wait a minute']);
    exit;
}

// ---- log EVERYTHING the client sent ----
$line  = (string)time() . ' | ' . ($_SERVER['REMOTE_ADDR'] ?? '-');
$line .= ' | ' . ($_SERVER['REQUEST_METHOD'] ?? '?') . ' ' . ($_SERVER['REQUEST_URI'] ?? '?');
$params = array_merge($_GET, $_POST);
$body = file_get_contents('php://input');
$body_json = null;
if ($body !== false && $body !== '') {
    $line .= ' | BODY: ' . substr($body, 0, 600);
    $trim = ltrim($body);
    if ($trim !== '' && $trim[0] === '{') {
        $body_json = json_decode($body, true);
    }
}
app_log($line);
if (is_array($body_json)) {
    $params = array_merge($params, $body_json);
}

// ---- extract the key ----
$key = '';
foreach (['license_key', 'key', 'user_key', 'license', 'auth', 'code', 'token'] as $f) {
    if (isset($params[$f]) && is_string($params[$f]) && trim($params[$f]) !== '') {
        $key = normalize_key(trim($params[$f]));
        break;
    }
}
if ($key === '' && $body !== false) {
    // raw body might BE the key
    $cand = normalize_key(trim($body));
    if ($cand !== '' && strlen($cand) <= 64 && preg_match('/^[A-Za-z0-9_-]+$/', $cand)) {
        $key = $cand;
    }
}
// best-effort hwid
$hwid_in = '';
foreach (['hwid', 'device', 'device_id', 'serial', 'android_id'] as $f) {
    if (isset($params[$f]) && is_string($params[$f]) && trim($params[$f]) !== '') {
        $hwid_in = preg_replace('/[^A-Za-z0-9._-]/', '', substr(trim($params[$f]), 0, 64)) ?: '';
        break;
    }
}

/**
 * Final answer. The app reads expires_at (ISO UTC) + session_id — always send them.
 */
$respond = function (bool $ok, string $msg, string $plan = 'LIFETIME', int $expires_ts = 0) {
    echo json_encode([
        'success'    => $ok,
        'message'    => $msg,
        'expires_at' => gmdate('Y-m-d\\TH:i:s\\Z', $ok && $expires_ts > 0 ? $expires_ts : strtotime('2099-12-31T23:59:59Z')),
        'session_id' => gen_session_id(),
        'expiry'     => $ok ? gmdate('Y-m-d', $expires_ts > 0 ? $expires_ts : strtotime('2099-12-31T23:59:59Z')) : '',
        'plan'       => $ok ? $plan : '',
    ]);
    exit;
};

$rows = keys_load();
$idx  = $key === '' ? -1 : keys_find($rows, $key);

if ($idx < 0) {
    // unknown / empty key
    if (STRICT_KEYS) {
        $respond(false, $key === '' ? 'The license key is required.' : 'INVALID_KEY');
    }
    $respond(true, 'License valid — welcome');           // takeover mode
}

$row = $rows[$idx];

if ($row['status'] !== 'active') {
    $respond(false, 'The license server rejected the session.');
}

$now = time();
if ((string)$row['hwid'] === '') {
    // first activation — bind hwid + start the clock
    $row['hwid']      = $hwid_in;
    $row['activated'] = $now;
    $row['expires']   = (int)$row['days'] > 0 ? $now + ((int)$row['days']) * 86400 : 0;
    $rows[$idx]       = $row;
    keys_save($rows);
} elseif ($hwid_in !== '' && !hash_equals((string)$row['hwid'], $hwid_in)) {
    $respond(false, 'The license session is no longer valid.');   // different device
}
if ((int)$row['expires'] > 0 && (int)$row['expires'] < $now) {
    $respond(false, 'Your key has expired.');
}

$plan = 'LIFETIME';
foreach (PLANS as $p) {
    if ($p[0] === (int)$row['days']) {
        $plan = strtoupper($p[1]);
        break;
    }
}
if ((int)$row['days'] > 0 && $plan === 'LIFETIME') {
    $plan = (int)$row['days'] . ' DAYS';
}

$respond(true, 'License valid — welcome', $plan, (int)$row['expires']);
