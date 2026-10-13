<?php
/**
 * samurai_connect.php — STANDALONE endpoint for the SAMURAI mod ONLY.
 *
 * Upload to:  htdocs/samurai_connect.php        (root, one file, nothing else)
 * App URL  :  https://domi74.page.gd/samurai_connect.php   (already patched in libsamurai.so)
 *
 * 100% SEPARATE from the PUBG/8BP panel:
 *   - does NOT include lib.php / config.php
 *   - does NOT read main.txt (PUBG keys)
 *   - has its OWN key file :  htdocs/samurai_keys.txt
 *   - has its OWN log file :  htdocs/data/samurai_logs.txt
 *
 * Key file format (one per line, created automatically):
 *   key|days|status|hwid|activated|expires|note
 *   days 0 = lifetime · status active|banned
 *
 * TAKEOVER MODE (default): $STRICT = false  ->  ANY key your friend types works.
 * Set $STRICT = true to only allow keys listed in samurai_keys.txt.
 * Banned keys in the file ALWAYS fail, even in takeover mode.
 */
declare(strict_types=1);

$STRICT     = false;                                    // false = any key passes
$KEYS_FILE  = __DIR__ . '/samurai_keys.txt';            // SAMURAI's own keys
$LOG_FILE   = __DIR__ . '/data/samurai_logs.txt';       // SAMURAI's own log
$RATE_LIMIT = 60;                                       // requests/min/IP

header('Content-Type: application/json; charset=utf-8');

/* ---------- self-contained storage ---------- */

function sam_read(string $f): array
{
    if (!file_exists($f)) return [];
    $d = @file_get_contents($f);
    return $d === false ? [] : explode("\n", $d);
}
function sam_write(string $f, array $lines): void
{
    @file_put_contents($f, implode("\n", $lines), LOCK_EX);
}
function sam_ensure_files(): void
{
    $dir = dirname($GLOBALS['LOG_FILE']);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!file_exists($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    if (!file_exists($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
    if (!file_exists($GLOBALS['KEYS_FILE'])) {
        sam_write($GLOBALS['KEYS_FILE'], [
            '# SAMURAI keys ONLY - separate from the PUBG panel (main.txt)',
            '# key|days|status|hwid|activated|expires|note     (days 0 = lifetime)',
            '# example: SAMURAI-7K3M9-QW2XB-VNT4J|30|active||||my friend',
        ]);
    }
}
function sam_keys(): array
{
    $rows = [];
    foreach (sam_read($GLOBALS['KEYS_FILE']) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $c = array_map('trim', explode('|', $line));
        if ($c[0] === '') continue;
        $rows[] = [
            'key'       => $c[0],
            'days'      => (int)($c[1] ?? 0),
            'status'    => strtolower($c[2] ?? 'active'),
            'hwid'      => $c[3] ?? '',
            'activated' => (int)($c[4] ?? 0),
            'expires'   => (int)($c[5] ?? 0),
            'note'      => $c[6] ?? '',
        ];
    }
    return $rows;
}
function sam_norm(string $s): string
{
    $s = preg_replace('/\s+/u', '', $s);
    return $s === null ? '' : $s;
}
function sam_find(array $rows, string $key): int
{
    $want = strtolower(sam_norm($key));
    foreach ($rows as $i => $r) {
        if (strtolower(sam_norm($r['key'])) === $want) return $i;
    }
    return -1;
}
function sam_log(string $line): void
{
    @file_put_contents($GLOBALS['LOG_FILE'], $line . "\n", FILE_APPEND | LOCK_EX);
    $lines = array_values(array_filter(array_map('trim', sam_read($GLOBALS['LOG_FILE']))));
    if (count($lines) > 800) sam_write($GLOBALS['LOG_FILE'], array_slice($lines, -800));
}
function sam_limited(): bool
{
    $cut = time() - 60;
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '-';
    $n = 0;
    foreach (sam_read($GLOBALS['LOG_FILE']) as $l) {
        $c = explode('|', $l, 3);
        if (isset($c[1]) && trim($c[1]) === $ip && ctype_digit(trim($c[0])) && (int)trim($c[0]) > $cut) {
            if (++$n >= $GLOBALS['RATE_LIMIT']) return true;
        }
    }
    return false;
}

/* ---------- handle request ---------- */

sam_ensure_files();

if (sam_limited()) {
    echo json_encode(['success' => false, 'message' => 'Too many attempts, wait a minute']);
    exit;
}

$line  = (string)time() . ' | ' . ($_SERVER['REMOTE_ADDR'] ?? '-');
$line .= ' | ' . ($_SERVER['REQUEST_METHOD'] ?? '?') . ' ' . ($_SERVER['REQUEST_URI'] ?? '?');
$params = array_merge($_GET, $_POST);
$body = file_get_contents('php://input');
$body_json = null;
if ($body !== false && $body !== '') {
    $line .= ' | BODY: ' . substr($body, 0, 600);
    $t = ltrim($body);
    if ($t !== '' && $t[0] === '{') $body_json = json_decode($body, true);
}
sam_log($line);
if (is_array($body_json)) $params = array_merge($params, $body_json);

// extract key + hwid
$key = '';
foreach (['license_key', 'key', 'user_key', 'license', 'auth', 'code', 'token'] as $f) {
    if (isset($params[$f]) && is_string($params[$f]) && trim($params[$f]) !== '') {
        $key = sam_norm(trim($params[$f])); break;
    }
}
if ($key === '' && $body !== false) {
    $cand = sam_norm(trim($body));
    if ($cand !== '' && strlen($cand) <= 64 && preg_match('/^[A-Za-z0-9_-]+$/', $cand)) $key = $cand;
}
$hwid = '';
foreach (['hwid', 'device', 'device_id', 'serial', 'android_id'] as $f) {
    if (isset($params[$f]) && is_string($params[$f]) && trim($params[$f]) !== '') {
        $hwid = preg_replace('/[^A-Za-z0-9._-]/', '', substr(trim($params[$f]), 0, 64)) ?: ''; break;
    }
}

$respond = function (bool $ok, string $msg, string $plan = 'LIFETIME', int $exp_ts = 0) {
    echo json_encode([
        'success'    => $ok,
        'message'    => $msg,
        'expires_at' => gmdate('Y-m-d\\TH:i:s\\Z', $ok && $exp_ts > 0 ? $exp_ts : strtotime('2099-12-31T23:59:59Z')),
        'session_id' => 'sid-' . bin2hex(random_bytes(16)),
        'expiry'     => $ok ? gmdate('Y-m-d', $exp_ts > 0 ? $exp_ts : strtotime('2099-12-31T23:59:59Z')) : '',
        'plan'       => $ok ? $plan : '',
    ]);
    exit;
};

$rows = sam_keys();
$idx  = $key === '' ? -1 : sam_find($rows, $key);

if ($idx < 0) {
    if ($STRICT) $respond(false, $key === '' ? 'The license key is required.' : 'INVALID_KEY');
    $respond(true, 'License valid — welcome');            // takeover: any key
}
$row = $rows[$idx];

if ($row['status'] !== 'active') $respond(false, 'The license server rejected the session.');

$now = time();
if ($row['hwid'] === '') {
    $row['hwid']      = $hwid;
    $row['activated'] = $now;
    $row['expires']   = $row['days'] > 0 ? $now + $row['days'] * 86400 : 0;
    $rows[$idx] = $row;
    $out = ['# SAMURAI keys ONLY - separate from the PUBG panel (main.txt)',
            '# key|days|status|hwid|activated|expires|note     (days 0 = lifetime)'];
    foreach ($rows as $r) {
        $out[] = implode('|', [$r['key'], $r['days'], $r['status'], $r['hwid'],
                               $r['activated'], $r['expires'], str_replace('|', ' ', $r['note'])]);
    }
    sam_write($GLOBALS['KEYS_FILE'], $out);
} elseif ($hwid !== '' && !hash_equals((string)$row['hwid'], $hwid)) {
    $respond(false, 'The license session is no longer valid.');
}
if ((int)$row['expires'] > 0 && (int)$row['expires'] < $now) $respond(false, 'Your key has expired.');

$plan = 'LIFETIME';
if ($row['days'] > 0) $plan = $row['days'] . ' DAYS';
$respond(true, 'License valid — welcome', $plan, (int)$row['expires']);
