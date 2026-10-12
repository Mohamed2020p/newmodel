<?php
/**
 * samurai/lib.php — storage (plain text), auth, helpers for the SAMURAI console.
 *   data/main.txt  keys   (key|days|status|hwid|activated|expires|note)
 *   data/logs.txt  app request log (auto-trimmed)
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
date_default_timezone_set('UTC');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const PLANS = [
    'day'    => [1,   'Day'],
    'week'   => [7,   'Week'],
    'month'  => [30,  'Month'],
    'season' => [90,  'Season'],
    'year'   => [365, 'Year'],
    'life'   => [0,   'Lifetime'],
];

/* ---------------- storage ---------------- */

function data_dir(): string
{
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!file_exists($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    if (!file_exists($dir . '/index.html')) {
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

function key_file(): string
{
    $root = __DIR__ . '/main.txt';
    if (file_exists($root)) {
        return $root;
    }
    $old = data_dir() . '/main.txt';
    return file_exists($old) ? $old : $root;
}

function log_file(): string { return data_dir() . '/logs.txt'; }

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
    if ($data === false) {
        return [];
    }
    if (strncmp($data, "\xEF\xBB\xBF", 3) === 0) {
        $data = substr($data, 3);
    }
    return explode("\n", $data);
}

function txt_write(string $file, array $lines): void
{
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
    file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
}

/* ---------------- keys ---------------- */

function keys_load(): array
{
    $rows = [];
    foreach (txt_read(key_file()) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $c = explode('|', $line);
        if (trim($c[0]) === '') {
            continue;
        }
        $g = function (int $i, string $def = '') use ($c): string {
            return isset($c[$i]) && trim($c[$i]) !== '' ? trim($c[$i]) : $def;
        };
        $rows[] = [
            'key'       => $g(0),
            'days'      => (int)$g(1, '0'),
            'status'    => strtolower($g(2, 'active')),
            'hwid'      => $g(3),
            'activated' => (int)$g(4, '0'),
            'expires'   => (int)$g(5, '0'),
            'note'      => $g(6),
        ];
    }
    return $rows;
}

function keys_save(array $rows): void
{
    $lines = [
        '# SAMURAI license keys — one per line: key|days|status|hwid|activated|expires|note',
        '# days: 0 = lifetime · status: active|banned · timestamps unix, 0 = never',
    ];
    foreach ($rows as $r) {
        $lines[] = implode('|', [
            $r['key'], (string)$r['days'], $r['status'], (string)$r['hwid'],
            (string)$r['activated'], (string)$r['expires'],
            str_replace(['|', "\n", "\r"], ' ', (string)$r['note']),
        ]);
    }
    txt_write(key_file(), $lines);
}

function keys_find(array $rows, string $key): int
{
    $want = strtolower(normalize_key($key));
    foreach ($rows as $i => $r) {
        if (hash_equals(strtolower(normalize_key((string)$r['key'])), $want)) {
            return $i;
        }
    }
    return -1;
}

function normalize_key(string $s): string
{
    $s = str_replace(
        ["\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}", "\u{2212}", "\u{00A0}"],
        ['-', '-', '-', '-', '-', '-', ''],
        $s
    );
    $s = preg_replace('/\s+/u', '', $s);
    return $s === null ? '' : $s;
}

function random_suffix(int $len): string
{
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

function create_key(int $days, string $note = ''): string
{
    $rows = keys_load();
    do {
        $key = KEY_PREFIX . '-' . ($days > 0 ? ($days <= 99 ? sprintf('%02d', $days) : (string)$days) : '00')
             . '-' . random_suffix(KEY_SUFFIX_LEN);
    } while (keys_find($rows, $key) !== -1);
    $rows[] = ['key' => $key, 'days' => $days, 'status' => 'active',
               'hwid' => '', 'activated' => 0, 'expires' => 0, 'note' => $note];
    keys_save($rows);
    return $key;
}

function key_status(array $k): string
{
    if (strtolower((string)$k['status']) !== 'active') {
        return 'banned';
    }
    if ((string)$k['hwid'] === '' || $k['hwid'] === null) {
        return 'unbound';
    }
    if ((int)$k['expires'] > 0 && (int)$k['expires'] < time()) {
        return 'expired';
    }
    return 'active';
}

/* ---------------- app request log ---------------- */

function app_log(string $line): void
{
    txt_append(log_file(), $line);
    $lines = array_values(array_filter(array_map('trim', txt_read(log_file())), function ($l) {
        return $l !== '';
    }));
    if (count($lines) > 800) {
        txt_write(log_file(), array_slice($lines, -800));
    }
}

function logs_recent(int $n = 60): array
{
    $lines = array_values(array_filter(array_map('trim', txt_read(log_file())), function ($l) {
        return $l !== '';
    }));
    $out = [];
    foreach (array_slice(array_reverse($lines), 0, $n) as $l) {
        $out[] = $l;
    }
    return $out;
}

function rate_limited(): bool
{
    $now = time();
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '-';
    $n = 0;
    foreach (txt_read(log_file()) as $l) {
        if (strpos($l, $ip) === 0 || strpos($l, '| ' . $ip . ' ') !== false) {
            $c = explode('|', $l);
            if (isset($c[0]) && ctype_digit(trim($c[0])) && (int)trim($c[0]) > $now - 60) {
                $n++;
                if ($n >= RATE_LIMIT) {
                    return true;
                }
            }
        }
    }
    return false;
}

/* ---------------- auth (password only) ---------------- */

function is_logged(): bool
{
    return !empty($_SESSION['samurai_ok']);
}

function pass_ok(string $pass): bool
{
    if (ADMIN_PASSWORD_HASH !== '') {
        return password_verify($pass, ADMIN_PASSWORD_HASH);
    }
    return hash_equals(ADMIN_PASSWORD, $pass);
}

function csrf_token(): string
{
    if (empty($_SESSION['samurai_csrf'])) {
        $_SESSION['samurai_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['samurai_csrf'];
}

function check_csrf(): void
{
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['samurai_csrf'] ?? '', $t)) {
        http_response_code(403);
        exit('Bad CSRF token');
    }
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function fmt_date($ts): string
{
    $ts = (int)$ts;
    return $ts > 0 ? gmdate('Y-m-d', $ts) : '—';
}
