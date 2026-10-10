<?php
/**
 * lib.php — shared bootstrap. NO DATABASE: everything lives in plain text files.
 *
 *   data/main.txt   the keys      (one per line, format below)
 *   data/logs.txt   attempt log   (auto-trimmed to ~500 lines)
 *
 * main.txt line format (pipe-separated, # lines are comments):
 *   key|days|status|hwid|activated|expires|note
 *   bum7md7-30-Xk2P9|30|active|a1b2c3d4e5f60718|1728568800|1731160800|vip friend
 *   bum7md7-00-Qw7Zn|0|active||||            <- lifetime key, not bound yet
 *   days: 0 = lifetime · status: active|banned · timestamps are unix time, 0 = never
 * You can also hand-edit / hand-add keys in main.txt — the panel reads it every time.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
date_default_timezone_set('UTC');

/* plan presets: key => [days, label] (days 0 = lifetime) */
const PLANS = [
    'day'    => [1,   'Day'],
    'week'   => [7,   'Week'],
    'month'  => [30,  'Month'],
    'season' => [90,  'Season'],
    'year'   => [365, 'Year'],
    'life'   => [0,   'Lifetime'],
];

/* ---------------- storage (plain text) ---------------- */

function data_dir(): string
{
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "Require all denied\n");
    }
    if (!file_exists($dir . '/index.html')) {
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

function key_file(): string { return data_dir() . '/main.txt'; }
function log_file(): string { return data_dir() . '/logs.txt'; }

/** Read a whole text file as array of lines (shared lock). */
function txt_read(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }
    $fh = fopen($file, 'r');
    if (!$fh) {
        return [];
    }
    flock($fh, LOCK_SH);
    $data = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $data === false ? [] : explode("\n", $data);
}

/** Atomically replace a text file with the given lines (exclusive lock). */
function txt_write(string $file, array $lines): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $fh = fopen($file, 'c+');
    if (!$fh) {
        return;
    }
    flock($fh, LOCK_EX);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, implode("\n", $lines));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}

function txt_append(string $file, string $line): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
}

/* ---------------- keys: load / save / find ---------------- */

/** Load all keys from main.txt as rows: key,days,status,hwid,activated,expires,note */
function keys_load(): array
{
    $rows = [];
    foreach (txt_read(key_file()) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $c = explode('|', $line);
        if (count($c) < 6) {
            continue;
        }
        $rows[] = [
            'key'       => $c[0],
            'days'      => (int)$c[1],
            'status'    => $c[2] !== '' ? $c[2] : 'active',
            'hwid'      => $c[3],
            'activated' => (int)$c[4],
            'expires'   => (int)$c[5],
            'note'      => isset($c[6]) ? $c[6] : '',
        ];
    }
    return $rows;
}

function keys_save(array $rows): void
{
    $lines = [
        '# license keys — one per line: key|days|status|hwid|activated|expires|note',
        '# days: 0 = lifetime · status: active|banned · timestamps unix, 0 = never',
    ];
    foreach ($rows as $r) {
        $lines[] = implode('|', [
            $r['key'], (string)$r['days'], $r['status'], (string)$r['hwid'],
            (string)$r['activated'], (string)$r['expires'], str_replace(['|', "\n", "\r"], ' ', (string)$r['note']),
        ]);
    }
    txt_write(key_file(), $lines);
}

function keys_find(array $rows, string $key)
{
    foreach ($rows as $i => $r) {
        if (hash_equals((string)$r['key'], $key)) {
            return $i;
        }
    }
    return -1;
}

/* ---------------- output: THE response the app expects ---------------- */

/**
 * Emit the EXACT response shape the mod's parser expects and exit.
 * PROTOCOL FACTS (from the binary):
 *  - success body MUST contain the literal substring "status":true  (no space)
 *  - failure body must NOT contain it, and must never be EMPTY
 *  - body must also be valid JSON (the app json-parses it too)
 *  - fields: status, token, data, exp, subtype, reason
 */
function respond(bool $ok, string $token, string $data, string $exp, string $subtype, string $reason): void
{
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode([
        'status'  => $ok,
        'token'   => $token,
        'data'    => $data,
        'exp'     => $exp,
        'subtype' => $subtype,
        'reason'  => $reason,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------------- request helpers ---------------- */

/** Strict-ish base64 decode with padding/space repair. */
function b64d(?string $s)
{
    $s = trim((string)$s);
    if ($s === '') {
        return '';
    }
    $s = str_replace(' ', '+', $s);   // some hosts mangle '+' into ' '
    $d = base64_decode($s, true);
    if ($d === false) {
        $padded = $s . str_repeat('=', (4 - strlen($s) % 4) % 4);
        $d = base64_decode($padded, true);
    }
    return $d === false ? false : trim($d);
}

/** Parse fallback for bodies PHP didn't turn into $_POST. */
function ensure_post(): void
{
    if (!empty($_POST)) {
        return;
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return;
    }
    foreach (explode('&', $raw) as $kv) {
        $p = explode('=', $kv, 2);
        if (count($p) === 2) {
            $_POST[rawurldecode($p[0])] = str_replace(' ', '+', rawurldecode($p[1]));
        }
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '-';
}

function clean_id(string $s): string
{
    $s = preg_replace('/[^A-Za-z0-9._-]/', '', substr($s, 0, 64));
    return $s === null ? '' : $s;
}

/* ---------------- logs (data/logs.txt) ---------------- */

function log_attempt(string $key, string $serial, bool $ok, string $reason): void
{
    $line = implode('|', [
        (string)time(), client_ip(), clean_id($key), clean_id($serial),
        $ok ? '1' : '0', str_replace(['|', "\n", "\r"], ' ', $reason),
    ]);
    txt_append(log_file(), $line);

    $lines = array_values(array_filter(array_map('trim', txt_read(log_file())), function ($l) {
        return $l !== '';
    }));
    if (count($lines) > 500) {
        txt_write(log_file(), array_slice($lines, -500));
    }
}

function logs_recent(int $n = 50): array
{
    $lines = array_values(array_filter(array_map('trim', txt_read(log_file())), function ($l) {
        return $l !== '';
    }));
    $lines = array_slice($lines, -$n);
    $out = [];
    foreach (array_reverse($lines) as $l) {
        $c = explode('|', $l);
        if (count($c) >= 6) {
            $out[] = ['ts' => (int)$c[0], 'ip' => $c[1], 'key' => $c[2], 'serial' => $c[3], 'ok' => (int)$c[4], 'reason' => $c[5]];
        }
    }
    return $out;
}

function rate_limited(): bool
{
    $now = time();
    $ip  = client_ip();
    $n   = 0;
    foreach (txt_read(log_file()) as $l) {
        $c = explode('|', $l);
        if (count($c) >= 2 && $c[1] === $ip && (int)$c[0] > $now - 60) {
            $n++;
            if ($n >= RATE_LIMIT) {
                return true;
            }
        }
    }
    return false;
}

/* ---------------- key generation (vendor-identical format) ---------------- */

/**
 * The vendor key example: bum7md7-12-gnMmz  =  16 chars total:
 *   7-char prefix + '-' + 2-digit days + '-' + 5 random chars.
 * This generator produces exactly that shape (days zero-padded to 2 digits,
 * lifetime = 00; durations over 99 days use the full number, e.g. 365).
 */
function random_suffix(int $len): string
{
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'; // no 0/O/1/l/I
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

function make_key(int $days): string
{
    $mid = $days > 0 ? ($days <= 99 ? sprintf('%02d', $days) : (string)$days) : '00';
    return KEY_PREFIX . '-' . $mid . '-' . random_suffix(KEY_SUFFIX_LEN);
}

/** Append a new key row to main.txt (loads+saves). Returns the key. */
function create_key(int $days, string $note = ''): string
{
    $rows = keys_load();
    $exists = function (string $k) use ($rows): bool {
        return keys_find($rows, $k) !== -1;
    };
    do {
        $key = make_key($days);
    } while ($exists($key));
    $rows[] = [
        'key' => $key, 'days' => $days, 'status' => 'active',
        'hwid' => '', 'activated' => 0, 'expires' => 0,
        'note' => str_replace(['|', "\n", "\r"], ' ', $note),
    ];
    keys_save($rows);
    return $key;
}

function plan_label(string $planKey): string
{
    return isset(PLANS[$planKey]) ? PLANS[$planKey][1] : ucfirst($planKey);
}

/* ---------------- admin auth / csrf ---------------- */

function is_logged(): bool
{
    return !empty($_SESSION['ok']);
}

function cred_ok(string $user, string $pass): bool
{
    if (!hash_equals(ADMIN_USER, $user)) {
        return false;
    }
    if (ADMIN_PASSWORD_HASH !== '') {
        return password_verify($pass, ADMIN_PASSWORD_HASH);
    }
    return hash_equals(ADMIN_PASS_PLAIN, $pass);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(403);
        exit('Bad CSRF token');
    }
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
