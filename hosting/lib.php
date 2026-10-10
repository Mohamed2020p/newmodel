<?php
/**
 * lib.php — shared bootstrap: config, database (SQLite), helpers.
 * The database self-creates at hosting/data/keys.db on first use.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
date_default_timezone_set('UTC');

const PLANS = [
    // key      => [days, label]   (days 0 = lifetime)
    'day'    => [1,    'Day'],
    'week'   => [7,    'Week'],
    'month'  => [30,   'Month'],
    'season' => [90,   'Season'],
    'year'   => [365,  'Year'],
    'life'   => [0,    'Lifetime'],
];

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "Require all denied\n");
    }
    $dbIdx = $dir . '/.htindex.html';
    if (!file_exists($dbIdx)) {
        @file_put_contents($dbIdx, '');
    }

    $pdo = new PDO('sqlite:' . $dir . '/keys.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode = WAL');

    $pdo->exec('CREATE TABLE IF NOT EXISTS keys (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        license_key  TEXT UNIQUE NOT NULL,
        plan         TEXT NOT NULL,
        days         INTEGER NOT NULL,
        status       TEXT NOT NULL DEFAULT "active",
        hwid         TEXT,
        activated_at INTEGER,
        expires_at   INTEGER,
        created_at   INTEGER NOT NULL,
        note         TEXT DEFAULT ""
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS tokens (
        token     TEXT PRIMARY KEY,
        license_key TEXT NOT NULL,
        serial    TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS logs (
        id      INTEGER PRIMARY KEY AUTOINCREMENT,
        ts      INTEGER NOT NULL,
        ip      TEXT NOT NULL,
        license_key TEXT NOT NULL,
        serial  TEXT NOT NULL,
        ok      INTEGER NOT NULL,
        reason  TEXT NOT NULL
    )');
    return $pdo;
}

/* ---------------- output ---------------- */

/**
 * Emit the EXACT response shape the mod's parser expects and exit.
 * IMPORTANT PROTOCOL FACTS (from the binary):
 *  - success body MUST contain the literal substring "status":true
 *  - failure body MUST NOT contain it
 *  - body must also be valid JSON (the app json-parses it too)
 *  - empty body would show the user "No response from server" — never send empty
 */
function respond(bool $ok, string $token, string $data, string $exp, string $subtype, string $reason): void
{
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }
    $payload = [
        'status'  => $ok,          // bool → json "status":true / "status":false (no space)
        'token'   => $token,       // session token (stored by the app)
        'data'    => $data,        // license payload/plan info
        'exp'     => $exp,         // expiry, rendered as "EXPIRY TIME: <exp>"
        'subtype' => $subtype,     // tier, rendered as "Tier <subtype>"
        'reason'  => $reason,      // shown on the login window when status=false
    ];
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------------- request helpers ---------------- */

/** Strict-ish base64 decode with padding/space repair. Returns string or false. */
function b64d(?string $s)
{
    $s = trim((string)$s);
    if ($s === '') {
        return '';
    }
    $s = str_replace(' ', '+', $s);      // hosts that mangle '+' into ' '
    $d = base64_decode($s, true);
    if ($d === false) {
        $padded = $s . str_repeat('=', (4 - strlen($s) % 4) % 4);
        $d = base64_decode($padded, true);
    }
    return $d === false ? false : trim($d);
}

/** Parse fallback for bodies PHP didn't turn into $_POST (odd content-types). */
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
            $name  = rawurldecode($p[0]);
            $value = str_replace(' ', '+', rawurldecode($p[1]));
            $_POST[$name] = $value;
        }
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '-';
}

function log_attempt(string $key, string $serial, bool $ok, string $reason): void
{
    try {
        $st = db()->prepare('INSERT INTO logs (ts, ip, license_key, serial, ok, reason)
                             VALUES (?, ?, ?, ?, ?, ?)');
        $st->execute([time(), client_ip(), $key, $serial, $ok ? 1 : 0, $reason]);
    } catch (Throwable $e) {
        // never let logging break the response
    }
}

function rate_limited(): bool
{
    try {
        $st = db()->prepare('SELECT COUNT(*) AS c FROM logs WHERE ip = ? AND ts > ?');
        $st->execute([client_ip(), time() - 60]);
        $row = $st->fetch();
        return (int)$row['c'] >= RATE_LIMIT;
    } catch (Throwable $e) {
        return false;
    }
}

/* ---------------- key generation ---------------- */

function random_suffix(int $len): string
{
    // no visually-confusing chars (0/O, 1/l/I)
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

/** Generate a unique key like bum7md7-30-Xk2P9 and INSERT it. Returns the key. */
function create_key(int $days, string $planKey, string $note = ''): string
{
    $pdo = db();
    $attempts = 0;
    do {
        $key  = KEY_PREFIX . '-' . ($days > 0 ? $days : 9999) . '-' . random_suffix(KEY_SUFFIX_LEN);
        $dup  = $pdo->prepare('SELECT 1 FROM keys WHERE license_key = ?');
        $dup->execute([$key]);
        $attempts++;
    } while ($dup->fetch() && $attempts < 20);

    $st = $pdo->prepare('INSERT INTO keys (license_key, plan, days, status, created_at, note)
                         VALUES (?, ?, ?, "active", ?, ?)');
    $st->execute([$key, $planKey, $days, time(), $note]);
    return $key;
}

function plan_label(string $planKey): string
{
    return isset(PLANS[$planKey]) ? PLANS[$planKey][1] : ucfirst($planKey);
}

/* ---------------- admin auth ---------------- */

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
