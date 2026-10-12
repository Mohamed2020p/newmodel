<?php
/**
 * samurai/index.php — endpoint for the SAMURAI mod (PWF Auth protocol).
 *
 * App URL after patch:  http://domi74.page.gd/samurai/
 *
 * SAMURAI's client expects JSON:  {"success":true/false,"message":"..."}
 * (confirmed from its binary: PWF self-test literal at .rodata 0x2CF5CA and
 * the status builder reading the "success" key).
 *
 * Logs EVERYTHING the app sends (IP, method, query, body, headers) to
 * data/samurai_logs.txt, then answers success. If the app needs extra
 * fields, the log shows exactly what it asked for.
 */
declare(strict_types=1);

$dir = __DIR__ . '/data';   // logs at hosting/data/samurai_logs.txt
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

// ---- log everything the client sent ----
$line  = date('Y-m-d H:i:s') . ' | ' . ($_SERVER['REMOTE_ADDR'] ?? '-');
$line .= ' | ' . ($_SERVER['REQUEST_METHOD'] ?? '?') . ' ' . ($_SERVER['REQUEST_URI'] ?? '?');
$line .= ' | QUERY: ' . http_build_query($_GET);
$body  = file_get_contents('php://input');
$line .= ' | BODY: ' . ($body !== false ? $body : '');
if (!empty($_POST)) {
    $line .= ' | POST: ' . http_build_query($_POST);
}
$hdrs = [];
foreach (['HTTP_USER_AGENT', 'CONTENT_TYPE', 'HTTP_ACCEPT', 'HTTP_X_REQUESTED_WITH'] as $h) {
    if (isset($_SERVER[$h])) {
        $hdrs[] = $h . '=' . $_SERVER[$h];
    }
}
if ($hdrs) {
    $line .= ' | HDRS: ' . implode(', ', $hdrs);
}
@file_put_contents($dir . '/samurai_logs.txt', $line . "\n", FILE_APPEND | LOCK_EX);

// ---- respond in PWF format ----
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'License valid — welcome',
    'expiry'  => '2099-12-31',
    'plan'    => 'LIFETIME',
]);
