<?php
/**
 * connect.php — THE validator endpoint. This is what the app POSTs to.
 * Reads keys from the plain text file data/main.txt (no database).
 *
 * Protocol (reverse-engineered from the mod, must not change):
 *   POST form-urlencoded:  game=<b64>&user_key=<b64>&serial=<b64(ANDROID_ID)>
 *   Success body must contain the literal  "status":true  and be valid JSON:
 *     {"status":true,"token":"..","data":"..","exp":"..","subtype":"..","reason":""}
 *   Failure body: {"status":false,...,"reason":"<message>"}   (never empty!)
 *   Wrong HTTP method → HTTP 500 (same as the original vendor panel).
 */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('LICENSE ENDPOINT v3 — makhdamach build. Method Not Allowed (use POST).');
}

ensure_post();

$game   = b64d($_POST['game'] ?? '');
$key    = b64d($_POST['user_key'] ?? '');
$serial = b64d($_POST['serial'] ?? '');
$key    = is_string($key) ? normalize_key(clean_id($key)) : '';
$serial = is_string($serial) ? clean_id($serial) : '';

$fail = function (string $reason) use ($key, $serial): void {
    log_attempt($key, $serial, false, $reason);
    respond(false, '', '', '', '', $reason);
};

if (rate_limited()) {
    $fail('Too many attempts, wait a minute');
}
if ($key === '') {
    $fail('Key is empty');
}
if ($serial === '') {
    $fail('Failed to get Android ID');
}
if (GAMES_ALLOWED !== ['*'] && (!is_string($game) || !in_array($game, GAMES_ALLOWED, true))) {
    $fail('Invalid game');
}

$rows = keys_load();
$idx  = keys_find($rows, $key);
if ($idx < 0) {
    // diagnostic: the admin Logs table will show exactly what arrived
    $fail('makhdamach');
}
$row = $rows[$idx];

if ($row['status'] !== 'active') {
    $fail('Key is banned');
}

$now = time();

// HWID binding: first device wins
if ($row['hwid'] === '' || $row['hwid'] === null) {
    $row['activated'] = $now;
    $row['expires']   = $row['days'] > 0 ? $now + ((int)$row['days']) * 86400 : 0;
    $row['hwid']      = $serial;
    $rows[$idx]       = $row;
    keys_save($rows);
} elseif (!hash_equals((string)$row['hwid'], $serial)) {
    $fail('Key is already used on another device');
}

if ($row['expires'] > 0 && (int)$row['expires'] < $now) {
    $fail('Key has expired');
}

$label = 'License';
foreach (PLANS as $pk => $p) {
    if ($p[0] === (int)$row['days']) {
        $label = $p[1];
        break;
    }
}
if ((int)$row['days'] > 0 && $label === 'License') {
    $label = $row['days'] . ' Days';
}

$exp = (int)$row['expires'] > 0 ? date(EXP_FORMAT, (int)$row['expires']) : EXP_NEVER;
$token = bin2hex(random_bytes(16));

log_attempt($key, $serial, true, 'OK');
respond(true, $token, $label, $exp, $label, '');
