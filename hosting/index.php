<?php
/**
 * index.php — admin panel: login, key generator, key management, logs.
 */
declare(strict_types=1);
session_start();
require_once __DIR__ . '/lib.php';

$msg = '';
$err = '';

// ---------- logout ----------
if (($_POST['action'] ?? '') === 'logout') {
    check_csrf();
    session_destroy();
    header('Location: .');
    exit;
}

// ---------- login ----------
if (!is_logged()) {
    if (($_POST['action'] ?? '') === 'login') {
        check_csrf();
        if (cred_ok(trim((string)($_POST['user'] ?? '')), (string)($_POST['pass'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['ok'] = true;
            header('Location: .');
            exit;
        }
        $err = 'Wrong username or password';
    }
    render_login($err);
    exit;
}

// ---------- actions ----------
$action = (string)($_POST['action'] ?? '');

if ($action === 'generate') {
    check_csrf();
    $planKey = (string)($_POST['plan'] ?? 'month');
    $qty     = max(1, min(100, (int)($_POST['qty'] ?? 1)));
    $note    = trim((string)($_POST['note'] ?? ''));
    $custom  = (int)($_POST['custom_days'] ?? 0);
    if ($custom > 0) {
        $days = min(9999, $custom);
        $planKey = $days . 'd';
    } elseif (!isset(PLANS[$planKey])) {
        $planKey = 'month';
    }
    $days = $custom > 0 ? $days : PLANS[$planKey][0];
    $made = [];
    try {
        for ($i = 0; $i < $qty; $i++) {
            $made[] = create_key($days, $planKey, $note);
        }
        $msg = 'Generated ' . count($made) . ' key(s):<br>' .
               implode('<br>', array_map('e', $made));
    } catch (Throwable $ex) {
        $err = 'Generation failed: ' . e($ex->getMessage());
    }
} elseif ($action === 'reset') {
    check_csrf();
    db()->prepare('UPDATE keys SET hwid = NULL, activated_at = NULL, expires_at = NULL, status = "active" WHERE id = ?')
        ->execute([(int)($_POST['id'] ?? 0)]);
    $msg = 'HWID reset — key can be activated again on any device';
} elseif ($action === 'toggle') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $st = db()->prepare('SELECT status FROM keys WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if ($row) {
        $new = $row['status'] === 'active' ? 'disabled' : 'active';
        db()->prepare('UPDATE keys SET status = ? WHERE id = ?')->execute([$new, $id]);
        $msg = 'Key ' . ($new === 'active' ? 'enabled' : 'banned');
    }
} elseif ($action === 'delete') {
    check_csrf();
    db()->prepare('DELETE FROM keys WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
    $msg = 'Key deleted';
}

render_dashboard($msg, $err);

/* ================= views ================= */

function render_login(string $err): void
{
    $name = PANEL_NAME;
    echo <<<HTML
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>$name — Login</title><style>
body{background:#0d1421;color:#e8eefc;font-family:system-ui,Segoe UI,Arial,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
.card{background:#0f1a2e;border:1px solid #294a7a;border-radius:12px;padding:32px;width:340px}
h1{color:#f2d15c;font-size:20px;margin:0 0 18px;text-align:center}
input{width:100%;box-sizing:border-box;background:#0a1220;border:1px solid #294a7a;color:#fff;border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:14px}
button{width:100%;background:#4296fa;border:0;border-radius:8px;color:#fff;font-weight:600;padding:11px;font-size:15px;cursor:pointer}
button:hover{background:#0f87fa}
.err{background:#3a1420;border:1px solid #bf4040;color:#ff9c9c;border-radius:8px;padding:9px 12px;margin-bottom:12px;font-size:13px}
</style></head><body><div class="card"><h1>$name</h1>
HTML;
    if ($err !== '') {
        echo '<div class="err">' . e($err) . '</div>';
    }
    echo '<form method="post"><input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
    echo '<input type="hidden" name="action" value="login">';
    echo '<input name="user" placeholder="Username" autofocus>';
    echo '<input name="pass" type="password" placeholder="Password">';
    echo '<button>Login</button></form></div></body></html>';
}

function status_of(array $k): string
{
    if ($k['status'] !== 'active') {
        return 'banned';
    }
    if ($k['hwid'] === null || $k['hwid'] === '') {
        return 'unbound';
    }
    if ($k['expires_at'] !== null && (int)$k['expires_at'] < time()) {
        return 'expired';
    }
    return 'active';
}

function render_dashboard(string $msg, string $err): void
{
    $name  = PANEL_NAME;
    $csrf  = e(csrf_token());
    $pdo   = db();

    $stats = ['total' => 0, 'active' => 0, 'unbound' => 0, 'expired' => 0, 'banned' => 0];
    foreach ($pdo->query('SELECT * FROM keys')->fetchAll() as $k) {
        $stats['total']++;
        $stats[status_of($k)]++;
    }

    $plansHtml = '';
    foreach (PLANS as $pk => $p) {
        $plansHtml .= '<option value="' . e($pk) . '">' . e($p[1]) . ($p[0] ? ' (' . $p[0] . 'd)' : '') . '</option>';
    }

    echo <<<HTML
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>$name</title><style>
body{background:#0d1421;color:#dbe4f5;font-family:system-ui,Segoe UI,Arial,sans-serif;margin:0}
.wrap{max-width:1050px;margin:0 auto;padding:24px}
h1{color:#f2d15c;font-size:22px}h2{color:#9ea8bf;font-size:14px;text-transform:uppercase;letter-spacing:1px;margin:28px 0 10px}
.card{background:#0f1a2e;border:1px solid #294a7a;border-radius:12px;padding:18px;margin-bottom:18px}
.grid{display:flex;gap:12px;flex-wrap:wrap}
.stat{flex:1;min-width:110px;background:#0a1220;border:1px solid #1c2f4d;border-radius:10px;padding:12px;text-align:center}
.stat b{display:block;font-size:22px;color:#f2d15c}
.stat span{font-size:12px;color:#9ea8bf}
input,select,textarea{background:#0a1220;border:1px solid #294a7a;color:#fff;border-radius:8px;padding:9px 11px;font-size:14px}
button{background:#4296fa;border:0;border-radius:8px;color:#fff;font-weight:600;padding:9px 16px;cursor:pointer}
button:hover{background:#0f87fa}
button.warn{background:#bf4040}button.gray{background:#37373c}button.ok{background:#1f7a3d}
table{width:100%;border-collapse:collapse;font-size:13px}
th{color:#9ea8bf;text-align:left;padding:8px 10px;border-bottom:1px solid #294a7a}
td{padding:8px 10px;border-bottom:1px solid #16233c}
td form{display:inline}
.tag{padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700}
.tag.active{background:#123a22;color:#5fe08a}.tag.unbound{background:#243a5c;color:#7fb2ff}
.tag.expired{background:#3a2d10;color:#f2c15c}.tag.banned{background:#3a1420;color:#ff8a8a}
.key{font-family:ui-monospace,Consolas,monospace;color:#fff}
.msg{background:#12331e;border:1px solid #1f7a3d;color:#9fe8b8;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:14px}
.err{background:#3a1420;border:1px solid #bf4040;color:#ff9c9c;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:14px}
form.inline{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.small{color:#8a93a8;font-size:12px}
a{color:#4296fa}
</style></head><body><div class="wrap">
<h1>$name</h1>
HTML;

    if ($msg !== '') { echo '<div class="msg">' . $msg . '</div>'; }
    if ($err !== '') { echo '<div class="err">' . e($err) . '</div>'; }

    echo '<div class="grid">';
    foreach ($stats as $label => $val) {
        echo '<div class="stat"><b>' . $val . '</b><span>' . e($label) . '</span></div>';
    }
    echo '</div>';

    // generator
    echo '<h2>Generate keys</h2><div class="card"><form method="post" class="inline">';
    echo '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="generate">';
    echo '<select name="plan">' . $plansHtml . '</select>';
    echo '<input type="number" name="qty" value="1" min="1" max="100" style="width:80px" title="Quantity">';
    echo '<input type="number" name="custom_days" placeholder="custom days" min="1" max="9999" style="width:130px">';
    echo '<input name="note" placeholder="note (optional)" style="flex:1;min-width:160px">';
    echo '<button>Create</button></form>';
    echo '<p class="small">Format: ' . e(KEY_PREFIX) . '-&lt;days&gt;-&lt;random&gt; &nbsp;e.g.&nbsp; ' .
         e(KEY_PREFIX) . '-30-Xk2P9 &nbsp;·&nbsp; keys bind to the first device that uses them (HWID lock)</p></div>';

    // keys table
    echo '<h2>Keys</h2><div class="card" style="overflow-x:auto"><table><tr>';
    echo '<th>Key</th><th>Plan</th><th>Status</th><th>Device (HWID)</th><th>Activated</th><th>Expires</th><th>Note</th><th>Actions</th></tr>';
    $rows = $pdo->query('SELECT * FROM keys ORDER BY id DESC LIMIT 500')->fetchAll();
    foreach ($rows as $k) {
        $st = status_of($k);
        echo '<tr><td class="key">' . e($k['license_key']) . '</td>';
        echo '<td>' . e(plan_label((string)$k['plan'])) . ($k['days'] > 0 ? ' (' . (int)$k['days'] . 'd)' : '') . '</td>';
        echo '<td><span class="tag ' . $st . '">' . $st . '</span></td>';
        echo '<td class="key" style="font-size:11px">' . e((string)($k['hwid'] ?? '')) . '</td>';
        echo '<td>' . ($k['activated_at'] ? e(gmdate('Y-m-d H:i', (int)$k['activated_at'])) : '—') . '</td>';
        echo '<td>' . ($k['expires_at'] ? e(gmdate('Y-m-d H:i', (int)$k['expires_at'])) : ($k['activated_at'] ? 'Never' : '—')) . '</td>';
        echo '<td>' . e((string)$k['note']) . '</td><td>';
        echo '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="id" value="' . (int)$k['id'] . '">';
        echo '<button class="gray" name="action" value="reset" title="unbind device">Reset</button> ';
        echo '<button class="' . ($st === 'banned' ? 'ok' : 'warn') . '" name="action" value="toggle">' . ($st === 'banned' ? 'Enable' : 'Ban') . '</button> ';
        echo '<button class="gray" name="action" value="delete" onclick="return confirm(\'Delete this key?\')">Del</button></form></td></tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="8" class="small">No keys yet — generate the first one above.</td></tr>';
    }
    echo '</table></div>';

    // logs
    echo '<h2>Recent /connect attempts</h2><div class="card" style="overflow-x:auto"><table><tr>';
    echo '<th>Time (UTC)</th><th>IP</th><th>Key</th><th>Serial</th><th>Result</th><th>Reason</th></tr>';
    $logs = $pdo->query('SELECT * FROM logs ORDER BY id DESC LIMIT 50')->fetchAll();
    foreach ($logs as $l) {
        echo '<tr><td>' . e(gmdate('m-d H:i:s', (int)$l['ts'])) . '</td><td>' . e($l['ip']) . '</td>';
        echo '<td class="key">' . e($l['license_key']) . '</td><td class="key" style="font-size:11px">' . e($l['serial']) . '</td>';
        echo '<td>' . ((int)$l['ok'] === 1 ? '<span class="tag active">OK</span>' : '<span class="tag banned">FAIL</span>') . '</td>';
        echo '<td>' . e($l['reason']) . '</td></tr>';
    }
    if (!$logs) {
        echo '<tr><td colspan="6" class="small">No attempts yet.</td></tr>';
    }
    echo '</table></div>';

    echo '<p class="small">Validator endpoint for the app: <span class="key">https://your-domain/connect</span> (POST) · ';
    echo '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="' . $csrf . '">';
    echo '<button class="gray" name="action" value="logout">Logout</button></form></p>';
    echo '</div></body></html>';
}
