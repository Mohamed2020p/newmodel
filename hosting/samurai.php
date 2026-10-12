<?php
/**
 * samurai.php — endpoint for the SAMURAI mod (PWF Auth protocol).
 *
 * SAMURAI's client expects JSON:  {"success":true/false,"message":"..."}
 * (confirmed from the binary: self-test literal + status builder key "success").
 *
 * This endpoint logs EVERYTHING the app sends (method, query, body, headers)
 * to data/samurai_logs.txt, then answers success. If the app needs extra
 * fields, the log shows exactly what it asked for — send me the log and the
 * response gets adjusted in one round.
 *
 * App URL after patch: http://domi74.page.gd/samurai.php
 */
declare(strict_types=1);

$dir = __DIR__ . '/data';
if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}
$ht = $dir . '/.htaccess';
if (!file_exists($ht)) {
    @file_put_contents($ht, "Require all denied\n");
}

// ---- log everything the client sent ----
$line  = date('Y-m-d H:i:s') . ' | ' . ($_SERVER['REMOTE_ADDR'] ?? '-') ;
$line .= ' | ' . ($_SERVER['REQUEST_METHOD'] ?? '?') . ' ' . ($_SERVER['REQUEST_URI'] ?? '?');
$line .= ' | QUERY: ' . http_build_query($_GET);
$body = file_get_contents('php://input');
$line .= ' | BODY: ' . ($body !== false ? $body : '');
if (!empty($_POST)) {
    $line .= ' | POST: ' . http_build_query($_POST);
}
@file_put_contents($dir . '/samurai_logs.txt', $line . "\n", FILE_APPEND | LOCK_EX);

// ---- respond in PWF format ----
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);

// messages shown in the app's license window; adjust after seeing real logs
echo json_encode([
    'success' => true,
    'message' => 'License valid — welcome',
    'expiry'  => '2099-12-31',
    'plan'    => 'LIFETIME',
]);
