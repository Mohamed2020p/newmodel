<?php
/**
 * ============================================================================
 *  SAMURAI LICENSE ENDPOINT — STANDALONE, ONE FILE, LIVES AT HOSTING ROOT
 * ============================================================================
 *  Upload to:   htdocs/connect.php        (replace the file's content)
 *  App calls :  https://domi74.page.gd/connect.php   (already inside libsamurai.so)
 *
 *  100% SEPARATE from the PUBG/8BP panel:
 *    - includes NOTHING (no lib.php, no config.php, no database)
 *    - does NOT read or write main.txt
 *    - SAMURAI keys file : htdocs/samurai_keys.php   (auto-created, web-blocked)
 *    - SAMURAI log  file : htdocs/samurai_logs.php   (auto-created, web-blocked)
 *
 *  Reply format — VERIFIED FROM libsamurai.so — the app reads EXACTLY:
 *      {"success":true/false, "message":"...", 
 *       "expires_at":"2099-12-31T23:59:59Z", "session_id":"sid-..."}
 *
 *  TAKEOVER MODE (default): ANY key passes as LIFETIME.
 *  Keys listed in samurai_keys.php are honored (days / hwid lock / ban).
 *  Format per line:  key|days|status|hwid|activated|expires|note
 *  To ban a key even in takeover mode, add it with status "banned".
 * ============================================================================
 */

error_reporting(0);
ini_set('display_errors', '0');
@ob_start();                       // swallow any stray output (warnings/ads) so the JSON stays clean

$STRICT    = false;                // false = any key passes (takeover). true = only keys in the file
$KEYS_FILE = __DIR__ . '/samurai_keys.php';
$LOG_FILE  = __DIR__ . '/samurai_logs.php';
$GUARD     = "<?php header('HTTP/1.0 403 Forbidden'); exit; ?>\n";
$NEVER     = 2524607999;           // 2049-12-31 epoch fallback, far future

/* ---------------- helpers ---------------- */

function sam_read_lines($f)
{
    if (!file_exists($f)) return array();
    $d = @file_get_contents($f);
    if ($d === false || $d === '') return array();
    $lines = explode("\n", $d);
    if (isset($lines[0]) && strpos($lines[0], '<?php') === 0) array_shift($lines);
    return $lines;
}

function sam_write_lines($f, $lines, $guard)
{
    @file_put_contents($f, $guard . implode("\n", $lines), LOCK_EX);
}

function sam_ensure()
{
    global $KEYS_FILE, $LOG_FILE, $GUARD;
    if (!file_exists($KEYS_FILE)) {
        sam_write_lines($KEYS_FILE, array(
            '# SAMURAI keys ONLY (standalone - NOT the PUBG main.txt)',
            '# key|days|status|hwid|activated|expires|note      days 0 = lifetime',
            '# example: SAMURAI-7K3M9-QW2XB-VNT4J|30|active||||my friend',
        ), $GUARD);
    }
    if (!file_exists($LOG_FILE)) {
        sam_write_lines($LOG_FILE, array(), $GUARD);
    }
}

function sam_norm($s)
{
    $s = preg_replace('/\s+/u', '', (string)$s);
    return $s === null ? '' : $s;
}

function sam_keys()
{
    global $KEYS_FILE;
    $rows = array();
    foreach (sam_read_lines($KEYS_FILE) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $c = array_map('trim', explode('|', $line));
        if ($c[0] === '') continue;
        $rows[] = array(
            'key'       => $c[0],
            'days'      => (int)(isset($c[1]) ? $c[1] : 0),
            'status'    => strtolower(isset($c[2]) && $c[2] !== '' ? $c[2] : 'active'),
            'hwid'      => isset($c[3]) ? $c[3] : '',
            'activated' => (int)(isset($c[4]) ? $c[4] : 0),
            'expires'   => (int)(isset($c[5]) ? $c[5] : 0),
            'note'      => isset($c[6]) ? $c[6] : '',
        );
    }
    return $rows;
}

function sam_find($rows, $key)
{
    $want = strtolower(sam_norm($key));
    foreach ($rows as $i => $r) {
        if (strtolower(sam_norm($r['key'])) === $want) return $i;
    }
    return -1;
}

function sam_save_keys($rows)
{
    global $KEYS_FILE, $GUARD;
    $out = array(
        '# SAMURAI keys ONLY (standalone - NOT the PUBG main.txt)',
        '# key|days|status|hwid|activated|expires|note      days 0 = lifetime',
    );
    foreach ($rows as $r) {
        $out[] = implode('|', array(
            $r['key'], (string)$r['days'], $r['status'], (string)$r['hwid'],
            (string)$r['activated'], (string)$r['expires'],
            str_replace('|', ' ', (string)$r['note']),
        ));
    }
    sam_write_lines($KEYS_FILE, $out, $GUARD);
}

function sam_log($line)
{
    global $LOG_FILE, $GUARD;
    $lines = sam_read_lines($LOG_FILE);
    $lines[] = $line;
    if (count($lines) > 800) $lines = array_slice($lines, -800);
    sam_write_lines($LOG_FILE, $lines, $GUARD);
}

/* ---------------- collect everything the client sent ---------------- */

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '?';
$uri    = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '?';
$ip     = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-';

// headers (getallheaders may be missing on some hosts -> emulate)
$HEADERS = array();
if (function_exists('getallheaders')) {
    $HEADERS = getallheaders();
} else {
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $HEADERS[$name] = $v;
        }
    }
}

$params = array_merge($_GET, $_POST);
$body   = file_get_contents('php://input');
$body_json = null;
if ($body !== false && $body !== '') {
    $t = ltrim($body);
    if ($t !== '' && $t[0] === '{') {
        $j = json_decode($body, true);
        if (is_array($j)) { $body_json = $j; $params = array_merge($params, $j); }
    }
}

$sam_log(((string)time()) . ' | ' . $ip . ' | ' . $method . ' ' . $uri
      . ' | HDRS: ' . json_encode($HEADERS)
      . ' | PARAMS: ' . json_encode($params)
      . ' | BODY: ' . substr($body === false ? '' : $body, 0, 600));

/* ---------------- locate the key + hwid (query / form / json / raw / headers) */

$key = '';
foreach (array('license_key', 'key', 'user_key', 'license', 'auth', 'code', 'token') as $f) {
    if (isset($params[$f]) && is_string($params[$f]) && trim($params[$f]) !== '') {
        $key = sam_norm(trim($params[$f])); break;
    }
}
if ($key === '') {
    foreach (array('HTTP_X_LICENSE_KEY', 'HTTP_LICENSE_KEY', 'HTTP_X_KEY', 'HTTP_KEY') as $h) {
        if (isset($_SERVER[$h]) && trim($_SERVER[$h]) !== '') { $key = sam_norm(trim($_SERVER[$h])); break; }
    }
}
if ($key === '' && isset($_SERVER['HTTP_AUTHORIZATION']) && $_SERVER['HTTP_AUTHORIZATION'] !== '') {
    $a = trim(preg_replace('/^Bearer\s+/i', '', trim($_SERVER['HTTP_AUTHORIZATION'])));
    if ($a !== '') $key = sam_norm($a);
}
if ($key === '' && $body !== false) {
    $cand = sam_norm(trim($body));
    if ($cand !== '' && strlen($cand) <= 64 && preg_match('/^[A-Za-z0-9_.-]+$/', $cand)) $key = $cand;
}

$hwid = '';
foreach (array('hwid', 'device', 'device_id', 'serial', 'android_id') as $f) {
    if (isset($params[$f]) && is_string($params[$f]) && trim($params[$f]) !== '') {
        $hwid = preg_replace('/[^A-Za-z0-9._-]/', '', substr(trim($params[$f]), 0, 64)); break;
    }
}
if ($hwid === '' && isset($_SERVER['HTTP_X_HWID'])) {
    $hwid = preg_replace('/[^A-Za-z0-9._-]/', '', substr(trim($_SERVER['HTTP_X_HWID']), 0, 64));
}

/* ---------------- the ONLY reply the app ever gets ---------------- */

function sam_reply($ok, $msg, $expires_ts, $plan)
{
    while (ob_get_level() > 0) { @ob_end_clean(); }   // drop any junk, output pure JSON
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(array(
        'success'    => $ok,
        'message'    => $msg,
        'expires_at' => gmdate('Y-m-d\\TH:i:s\\Z', $expires_ts > 0 ? $expires_ts : 2524607999),
        'session_id' => 'sid-' . bin2hex(random_bytes(16)),
        'plan'       => $plan,
    ));
    exit;
}

/* ---------------- browser self-test: /connect.php?test=1 ---------------- */
if (isset($_GET['test'])) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Content-Type: text/plain; charset=utf-8');
    echo "SAMURAI endpoint OK\n";
    echo "keys file : " . $KEYS_FILE . (file_exists($KEYS_FILE) ? " (writable)" : " MISSING") . "\n";
    echo "log file  : " . $LOG_FILE  . (file_exists($LOG_FILE) ? " (writable)" : " MISSING") . "\n";
    echo "mode      : " . ($STRICT ? "STRICT (only listed keys)" : "TAKEOVER (any key passes)") . "\n";
    echo "reply to a key would be:\n";
    echo json_encode(array('success' => true, 'message' => 'License valid - welcome',
        'expires_at' => gmdate('Y-m-d\\TH:i:s\\Z', 2524607999),
        'session_id' => 'sid-test', 'plan' => 'LIFETIME')) . "\n";
    exit;
}

/* ---------------- decision ---------------- */

sam_ensure();
$rows = sam_keys();
$idx  = ($key === '') ? -1 : sam_find($rows, $key);

if ($idx < 0) {
    if ($STRICT) {
        sam_reply(false, $key === '' ? 'The license key is required.' : 'Invalid or expired key', 0, '');
    }
    sam_reply(true, 'License valid - welcome', 0, 'LIFETIME');   // takeover: anything passes
}

$row = $rows[$idx];
if ($row['status'] !== 'active') {
    sam_reply(false, 'Invalid or expired key', 0, '');
}

$now = time();
if ((string)$row['hwid'] === '') {
    $row['hwid']      = $hwid;
    $row['activated'] = $now;
    $row['expires']   = $row['days'] > 0 ? $now + $row['days'] * 86400 : 0;
    $rows[$idx] = $row;
    sam_save_keys($rows);
} elseif ($hwid !== '' && !hash_equals((string)$row['hwid'], $hwid)) {
    sam_reply(false, 'Invalid or expired key', 0, '');
}
if ((int)$row['expires'] > 0 && (int)$row['expires'] < $now) {
    sam_reply(false, 'Invalid or expired key', 0, '');
}

$plan = 'LIFETIME';
if ($row['days'] > 0) $plan = $row['days'] . ' DAYS';
sam_reply(true, 'License valid - welcome', (int)$row['expires'], $plan);
