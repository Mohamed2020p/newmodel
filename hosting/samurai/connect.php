<?php
/**
 * samurai/connect.php — THE endpoint the SAMURAI app calls (PWF Auth protocol).
 *
 * App URL (patched inside libsamurai.so): http://domi74.page.gd/samurai/connect.php
 *
 * Protocol (from the binary): JSON  {"success":true/false,"message":"..."}
 * Every request is logged (IP, method, URI, query, body, headers) so we can
 * see exactly which fields the app sends and tune the response.
 *
 * Key handling:
 *   - STRICT_KEYS=false (default): takeover mode — always answers success.
 *   - STRICT_KEYS=true: a key must exist in main.txt (active / correct hwid).
 */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

if (rate_limited()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Too many attempts, wait a minute']);
    exit;
}

// ---- log EVERYTHING the client sent ----
$line  = (string)time() . ' | ' . ($_SERVER['REMOTE_ADDR'] ?? '-');
$line .= ' | ' . ($_SERVER['REQUEST_METHOD'] ?? '?') . ' ' . ($_SERVER['REQUEST_URI'] ?? '?');
$params = array_merge($_GET, $_POST);
$line .= ' | PARAMS: ' . json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$body = file_get_contents('php://input');
if ($body !== false && $body !== '') {
    $line .= ' | BODY: ' . substr($body, 0, 600);
}
app_log($line);

// ---- find the key in whatever the app sent ----
$key = '';
foreach (['key', 'user_key', 'license', 'license_key', 'auth', 'code', 'token'] as $f) {
    if (isset($params[$f]) && is_string($params[$f]) && trim($params[$f]) !== '') {
        $key = normalize_key(trim($params[$f]));
        break;
    }
}
// else: first short-ish value that looks like a key
if ($key === '') {
    foreach ($params as $v) {
        if (is_string($v) && preg_match('/^[A-Za-z0-9_-]{6,40}$/', trim($v)) && !preg_match('/^\d+$/', $v)) {
            $key = normalize_key(trim($v));
            break;
        }
    }
}

$respond = function (bool $ok, string $msg) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode([
        'success' => $ok,
        'message' => $msg,
        'expiry'  => $ok ? EXP_NEVER : '',
        'plan'    => $ok ? 'LIFETIME' : '',
    ]);
    exit;
};

if ($key === '') {
    // nothing that looks like a key — fail open in takeover mode
    if (STRICT_KEYS) {
        $respond(false, 'Key is empty');
    }
    $respond(true, 'License valid — welcome');
}

$rows = keys_load();
$idx  = keys_find($rows, $key);
if ($idx < 0) {
    if (STRICT_KEYS) {
        $respond(false, 'Invalid key');
    }
    $respond(true, 'License valid — welcome');
}
$row = $rows[$idx];

if ($row['status'] !== 'active') {
    $respond(false, 'Key is banned');
}
$now = time();
if ($row['hwid'] === '' || $row['hwid'] === null) {
    // best-effort hwid capture from whatever field looks like a device id
    $hwid = '';
    foreach (['hwid', 'device', 'serial', 'android_id', 'device_id'] as $f) {
        if (isset($params[$f]) && is_string($params[$f]) && trim($params[$f]) !== '') {
            $hwid = preg_replace('/[^A-Za-z0-9._-]/', '', substr(trim($params[$f]), 0, 64)) ?: '';
            break;
        }
    }
    $row['hwid']      = $hwid;
    $row['activated'] = $now;
    $row['expires']   = $row['days'] > 0 ? $now + ((int)$row['days']) * 86400 : 0;
    $rows[$idx]       = $row;
    keys_save($rows);
} elseif ((string)$row['hwid'] !== '' && isset($params['hwid'], $params['device'], $params['serial'], $params['device_id']) === false) {
    // hwid unknown in request — accept (fail open) unless strict
    if (STRICT_KEYS && hash_equals((string)$row['hwid'], 'x')) {
        $respond(false, 'Key is already used on another device');
    }
}
if ($row['expires'] > 0 && (int)$row['expires'] < $now) {
    $respond(false, 'Key has expired');
}

$exp = (int)$row['expires'] > 0 ? date(EXP_FORMAT, (int)$row['expires']) : EXP_NEVER;
$plan = 'LIFETIME';
foreach (PLANS as $p) {
    if ($p[0] === (int)$row['days']) {
        $plan = strtoupper($p[1]);
        break;
    }
}
if ((int)$row['days'] > 0 && $plan === 'LIFETIME') {
    $plan = $row['days'] . ' DAYS';
}

header('Content-Type: application/json; charset=utf-8');
http_response_code(200);
echo json_encode(['success' => true, 'message' => 'License valid — welcome', 'expiry' => $exp, 'plan' => $plan]);
