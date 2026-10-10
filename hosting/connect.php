<?php
/**
 * connect.php — THE validator endpoint. This is what the app POSTs to.
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
    exit('Method Not Allowed');
}

ensure_post();

$tryKey    = '';
$trySerial = '';

$game   = b64d($_POST['game'] ?? '');
$key    = b64d($_POST['user_key'] ?? '');
$serial = b64d($_POST['serial'] ?? '');

if (is_string($key))    { $tryKey    = $key; }
if (is_string($serial)) { $trySerial = $serial; }

$fail = function (string $reason) use ($tryKey, $trySerial): void {
    log_attempt($tryKey, $trySerial, false, $reason);
    respond(false, '', '', '', '', $reason);
};

if (rate_limited()) {
    $fail('Too many attempts, wait a minute');
}
if ($key === '' || $key === false) {
    $fail('Key is empty');
}
if ($serial === '' || $serial === false) {
    $fail('Failed to get Android ID');
}
if (GAMES_ALLOWED !== ['*'] && (!is_string($game) || !in_array($game, GAMES_ALLOWED, true))) {
    $fail('Invalid game');
}

$pdo = db();
$st  = $pdo->prepare('SELECT * FROM keys WHERE license_key = ?');
$st->execute([$key]);
$row = $st->fetch();
if (!$row) {
    $fail('Invalid key');
}
if ($row['status'] !== 'active') {
    $fail('Key is banned');
}

$now = time();

// HWID binding: first device wins
if ($row['hwid'] === null || $row['hwid'] === '') {
    $days       = (int)$row['days'];
    $expiresAt  = $days > 0 ? $now + $days * 86400 : null;
    $up = $pdo->prepare('UPDATE keys SET hwid = ?, activated_at = ?, expires_at = ? WHERE id = ?');
    $up->execute([$serial, $now, $expiresAt, (int)$row['id']]);
    $row['hwid']       = $serial;
    $row['activated_at'] = $now;
    $row['expires_at'] = $expiresAt;
} elseif (!hash_equals((string)$row['hwid'], $serial)) {
    $fail('Key is already used on another device');
}

if ($row['expires_at'] !== null && (int)$row['expires_at'] < $now) {
    $fail('Key has expired');
}

$label = plan_label((string)$row['plan']);
$exp   = $row['expires_at'] !== null ? date(EXP_FORMAT, (int)$row['expires_at']) : EXP_NEVER;

// session token (stored by the app in its config)
$token = bin2hex(random_bytes(16));
$tk = $pdo->prepare('INSERT INTO tokens (token, license_key, serial, created_at) VALUES (?, ?, ?, ?)');
$tk->execute([$token, $key, $serial, $now]);

log_attempt($key, $serial, true, 'OK');
respond(true, $token, $label, $exp, $label, '');
