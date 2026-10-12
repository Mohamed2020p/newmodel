<?php
/**
 * samurai/index.php — admin console (Night Byte style: dark terminal, neon green).
 * Password-only login. Keys file: main.txt (or data/main.txt). Logs: data/logs.txt.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$msg = '';
$err = '';

if (($_POST['action'] ?? '') === 'logout') {
    check_csrf();
    session_destroy();
    header('Location: .');
    exit;
}

if (!is_logged()) {
    if (($_POST['action'] ?? '') === 'login') {
        check_csrf();
        if (pass_ok((string)($_POST['pass'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['samurai_ok'] = true;
            header('Location: .');
            exit;
        }
        $err = 'ACCESS DENIED — wrong secret key';
    }
    render_login($err);
    exit;
}

$action = (string)($_POST['action'] ?? '');

if ($action === 'generate') {
    check_csrf();
    $planKey = (string)($_POST['plan'] ?? 'month');
    $qty     = max(1, min(100, (int)($_POST['qty'] ?? 1)));
    $note    = trim((string)($_POST['note'] ?? ''));
    $custom  = (int)($_POST['custom_days'] ?? 0);
    if ($custom > 0) {
        $days = min(999, $custom);
    } elseif (isset(PLANS[$planKey])) {
        $days = PLANS[$planKey][0];
    } else {
        $days = 30;
    }
    $made = [];
    for ($i = 0; $i < $qty; $i++) {
        $made[] = create_key($days, $note);
    }
    $msg = 'Generated ' . count($made) . ' key(s): ' . implode(', ', $made);
} elseif ($action === 'addkey') {
    check_csrf();
    $k = normalize_key(trim((string)($_POST['newkey'] ?? '')));
    $d = max(0, min(999, (int)($_POST['newdays'] ?? 0)));
    if ($k === '') {
        $err = 'Type the key first';
    } else {
        $rows = keys_load();
        if (keys_find($rows, $k) >= 0) {
            $err = 'That key already exists';
        } else {
            $rows[] = ['key' => $k, 'days' => $d, 'status' => 'active',
                       'hwid' => '', 'activated' => 0, 'expires' => 0, 'note' => 'manual'];
            keys_save($rows);
            $msg = 'Key added: ' . $k;
        }
    }
} elseif ($action === 'reset') {
    check_csrf();
    $k    = (string)($_POST['k'] ?? '');
    $rows = keys_load();
    if (($idx = keys_find($rows, $k)) >= 0) {
        $rows[$idx]['hwid'] = '';
        $rows[$idx]['activated'] = 0;
        $rows[$idx]['expires'] = 0;
        $rows[$idx]['status'] = 'active';
        keys_save($rows);
        $msg = 'HWID reset — ' . $k;
    }
} elseif ($action === 'toggle') {
    check_csrf();
    $k    = (string)($_POST['k'] ?? '');
    $rows = keys_load();
    if (($idx = keys_find($rows, $k)) >= 0) {
        $rows[$idx]['status'] = strtolower($rows[$idx]['status']) === 'active' ? 'banned' : 'active';
        keys_save($rows);
        $msg = $k . ' is now ' . $rows[$idx]['status'];
    }
} elseif ($action === 'delete') {
    check_csrf();
    $k    = (string)($_POST['k'] ?? '');
    $rows = keys_load();
    if (($idx = keys_find($rows, $k)) >= 0) {
        array_splice($rows, $idx, 1);
        keys_save($rows);
        $msg = 'Key deleted';
    }
} elseif ($action === 'clearlogs') {
    check_csrf();
    txt_write(log_file(), []);
    $msg = 'Logs cleared';
}

render_dashboard($msg, $err);

/* ================= views ================= */

function head(string $title): void
{
    echo <<<HTML
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>$title</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script>tailwind.config={theme:{extend:{fontFamily:{mono:['"JetBrains Mono"','monospace']},colors:{void:'#050807',term:'#0a0f0d',edge:'#123324',neon:'#00ff88',dimm:'#0e5c3a',amber:'#ffc857',red:'#ff5566'}}}}</script>
<style>
body{font-family:'JetBrains Mono',monospace;background:#050807;}
.scan::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:40;
 background:repeating-linear-gradient(0deg,rgba(0,255,136,.025) 0 1px,transparent 1px 3px);}
.card{background:#0a0f0d;border:1px solid #123324;border-radius:.6rem;box-shadow:0 0 32px rgba(0,255,136,.05) inset;}
.glow{text-shadow:0 0 12px rgba(0,255,136,.55)}
.btn{transition:all .15s ease}.btn:active{transform:scale(.97)}
input:focus,select:focus{border-color:#00ff88 !important;box-shadow:0 0 0 1px #00ff8855}
::-webkit-scrollbar{width:9px;height:9px}::-webkit-scrollbar-thumb{background:#123324;border-radius:9px}
.blink{animation:bl 1.1s steps(2) infinite}@keyframes bl{50%{opacity:0}}
tbody tr:hover{background:rgba(0,255,136,.04)}
</style></head><body class="min-h-screen text-emerald-50 scan">
HTML;
}

function term_head(): void
{
    echo '<div class="flex items-center gap-2 px-4 py-2.5 border-b border-edge bg-black/40 rounded-t-[.6rem]">';
    echo '<span class="w-2.5 h-2.5 rounded-full bg-red-500/70"></span>';
    echo '<span class="w-2.5 h-2.5 rounded-full bg-amber-400/70"></span>';
    echo '<span class="w-2.5 h-2.5 rounded-full bg-neon/80"></span>';
    echo '<span class="ml-3 text-[11px] tracking-[.25em] text-dimm">' . e(PANEL_NAME) . ' / PRIVATE CONSOLE</span>';
    echo '<span class="ml-auto text-[10px] text-dimm">PROTECTED SESSION</span></div>';
}

function toast(string $msg, string $err): void
{
    if ($msg === '' && $err === '') {
        return;
    }
    $ok = $msg !== '';
    $txt = $ok ? $msg : $err;
    echo '<div id="toast" class="fixed top-5 left-1/2 -translate-x-1/2 z-50 px-5 py-3 rounded-lg border font-mono text-sm shadow-2xl '
        . ($ok ? 'bg-[#03130b]/95 border-neon/60 text-neon' : 'bg-[#160507]/95 border-red/60 text-red')
        . '">&gt; ' . e($txt) . '</div>
<script>setTimeout(()=>{const t=document.getElementById("toast");if(t)t.remove()},4500)</script>';
}

function render_login(string $err): void
{
    $name = PANEL_NAME;
    head($name . ' — Login');
    toast('', $err);
    echo '<div class="min-h-screen flex items-center justify-center p-4">';
    echo '<div class="w-full max-w-md"><div class="card overflow-hidden">';
    term_head();
    echo '<div class="px-8 py-10">';
    echo '<div class="flex items-center gap-4 mb-8">';
    echo '<img src="assets/logo.png" alt="" class="w-14 drop-shadow-[0_0_16px_rgba(0,255,136,.5)]">';
    echo '<div><h1 class="text-2xl font-extrabold tracking-widest text-neon glow">' . e($name) . ' / PRIVATE CONSOLE</h1>';
    echo '<p class="text-[11px] text-dimm mt-1">license workspace · v3</p></div></div>';
    echo '<p class="text-sm text-emerald-100/80 mb-2">Welcome back.</p>';
    echo '<p class="text-xs text-dimm mb-7">Enter your secret key to open your license workspace.</p>';
    echo '<form method="post" class="space-y-4">';
    echo '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '"><input type="hidden" name="action" value="login">';
    echo '<label class="block text-[11px] uppercase tracking-widest text-dimm mb-1">Secret key</label>';
    echo '<input name="pass" type="password" autofocus placeholder="••••••••••••" class="w-full bg-void border border-edge rounded-lg px-4 py-3 text-sm outline-none text-neon tracking-widest">';
    echo '<button class="btn w-full rounded-lg py-3 font-bold tracking-[.2em] text-void bg-neon hover:brightness-110 shadow-[0_0_28px_rgba(0,255,136,.35)]">UNLOCK PANEL</button>';
    echo '</form>';
    echo '<p class="text-[10px] text-dimm mt-6 flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-neon blink"></span> encrypted session · private key management</p>';
    echo '</div></div></div></div></body></html>';
}

function badge_html(string $st): string
{
    $map = [
        'active'  => 'border-neon/50 text-neon bg-neon/10',
        'unbound' => 'border-sky-400/50 text-sky-300 bg-sky-400/10',
        'expired' => 'border-amber/50 text-amber bg-amber/10',
        'banned'  => 'border-red/50 text-red bg-red/10',
        'OK'      => 'border-neon/50 text-neon bg-neon/10',
        'FAIL'    => 'border-red/50 text-red bg-red/10',
    ];
    $c = $map[$st] ?? 'border-slate-600 text-slate-400';
    return '<span class="inline-block px-2 py-0.5 rounded border text-[10px] font-bold tracking-wider ' . $c . '">' . e($st) . '</span>';
}

function render_dashboard(string $msg, string $err): void
{
    $csrf = e(csrf_token());
    $rows = keys_load();
    $logs = logs_recent(60);

    $stats = ['total' => 0, 'active' => 0, 'unbound' => 0, 'expired' => 0, 'banned' => 0];
    foreach ($rows as $k) {
        $stats['total']++;
        $stats[key_status($k)]++;
    }

    head(PANEL_NAME . ' — Console');
    toast($msg, $err);
    echo '<div class="max-w-6xl mx-auto px-4 py-8">';
    echo '<div class="card overflow-hidden mb-6">';
    term_head();
    echo '<div class="px-5 py-4 flex items-center justify-between flex-wrap gap-3">';
    echo '<div class="flex items-center gap-3">';
    echo '<img src="assets/logo.png" alt="" class="w-9 drop-shadow-[0_0_12px_rgba(0,255,136,.5)]">';
    echo '<div><span class="text-neon font-bold tracking-widest glow">' . e(PANEL_NAME) . ' / PRIVATE CONSOLE</span>';
    echo '<p class="text-[10px] text-dimm">keys: <span class="text-emerald-200/70">' . e(key_file()) . '</span></p></div></div>';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '">';
    echo '<button name="action" value="logout" class="btn text-[11px] px-4 py-2 rounded border border-edge text-dimm hover:text-red hover:border-red/60">LOGOUT</button></form>';
    echo '</div>';
    echo '<div class="px-5 pb-4 flex flex-wrap gap-x-6 gap-y-1 text-[11px]">';
    foreach ($stats as $label => $val) {
        echo '<span class="text-dimm">' . e($label) . ': <b class="' . ($label === 'active' ? 'text-neon' : 'text-emerald-100') . '">' . (int)$val . '</b></span>';
    }
    echo '</div></div>';

    // generate
    echo '<div class="card p-5 mb-5"><h2 class="text-[11px] uppercase tracking-[.25em] text-dimm mb-4">&gt; generate keys</h2>';
    echo '<form method="post" class="flex flex-wrap gap-3 items-center">';
    echo '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="generate">';
    echo '<select name="plan" class="bg-void border border-edge rounded px-3 py-2 text-sm outline-none">';
    foreach (PLANS as $pk => $p) {
        echo '<option value="' . e($pk) . '">' . e($p[1]) . ($p[0] ? ' — ' . sprintf('%02d', $p[0]) . 'd' : ' — lifetime') . '</option>';
    }
    echo '</select>';
    echo '<input type="number" name="qty" value="1" min="1" max="100" class="w-20 bg-void border border-edge rounded px-3 py-2 text-sm outline-none">';
    echo '<input type="number" name="custom_days" placeholder="custom days" min="1" max="999" class="w-32 bg-void border border-edge rounded px-3 py-2 text-sm outline-none">';
    echo '<input name="note" placeholder="note (optional)" class="flex-1 min-w-[150px] bg-void border border-edge rounded px-3 py-2 text-sm outline-none">';
    echo '<button class="btn rounded px-5 py-2 font-bold tracking-widest text-void bg-neon hover:brightness-110 text-sm">CREATE</button></form>';
    echo '<form method="post" class="flex flex-wrap gap-3 items-center mt-3 pt-3 border-t border-edge/60">';
    echo '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="addkey">';
    echo '<input name="newkey" placeholder="add existing key manually" class="flex-1 min-w-[200px] bg-void border border-edge rounded px-3 py-2 text-sm outline-none">';
    echo '<input type="number" name="newdays" placeholder="days (0=lifetime)" min="0" max="999" class="w-40 bg-void border border-edge rounded px-3 py-2 text-sm outline-none">';
    echo '<button class="btn rounded px-5 py-2 text-sm font-bold border border-neon/60 text-neon hover:bg-neon/10">ADD</button></form></div>';

    // keys
    echo '<div class="card p-5 mb-5 overflow-x-auto"><h2 class="text-[11px] uppercase tracking-[.25em] text-dimm mb-4">&gt; keys</h2><table class="w-full text-sm">';
    echo '<thead><tr class="text-left text-[10px] uppercase tracking-wider text-dimm border-b border-edge">';
    echo '<th class="py-2 pr-3">Key</th><th class="py-2 pr-3">Days</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3">HWID</th><th class="py-2 pr-3">Activated</th><th class="py-2 pr-3">Expires</th><th class="py-2 pr-3">Note</th><th class="py-2">Actions</th></tr></thead><tbody>';
    foreach ($rows as $k) {
        $st = key_status($k);
        echo '<tr class="border-b border-edge/40"><td class="py-2 pr-3 text-neon whitespace-nowrap">' . e((string)$k['key'])
           . ' <button class="btn align-middle ml-1 text-[10px] px-1.5 border border-edge rounded text-dimm hover:text-neon hover:border-neon/50" onclick="navigator.clipboard.writeText(\'' . addslashes(e((string)$k['key'])) . '\');this.textContent=\'ok\'">copy</button></td>';
        echo '<td class="py-2 pr-3 text-emerald-100/70">' . ($k['days'] > 0 ? (int)$k['days'] . 'd' : '∞') . '</td>';
        echo '<td class="py-2 pr-3">' . badge_html($st) . '</td>';
        echo '<td class="py-2 pr-3 text-[11px] text-dimm">' . ((string)$k['hwid'] !== '' ? e((string)$k['hwid']) : '—') . '</td>';
        echo '<td class="py-2 pr-3 text-xs text-emerald-100/70">' . fmt_date($k['activated']) . '</td>';
        echo '<td class="py-2 pr-3 text-xs text-emerald-100/70">' . ((int)$k['expires'] > 0 ? fmt_date($k['expires']) : ($k['activated'] ? 'Never' : '—')) . '</td>';
        echo '<td class="py-2 pr-3 text-xs text-dimm">' . e((string)$k['note']) . '</td><td class="py-2">';
        echo '<form method="post" class="flex gap-1.5"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="k" value="' . e((string)$k['key']) . '">';
        echo '<button name="action" value="reset" class="btn px-2 py-1 rounded text-[10px] border border-edge text-emerald-100/80 hover:border-sky-400 hover:text-sky-300">RESET</button>';
        echo '<button name="action" value="toggle" class="btn px-2 py-1 rounded text-[10px] border ' . ($st === 'banned' ? 'border-neon/50 text-neon' : 'border-red/50 text-red') . '">' . ($st === 'banned' ? 'ENABLE' : 'BAN') . '</button>';
        echo '<button name="action" value="delete" onclick="return confirm(\'Delete ' . addslashes(e((string)$k['key'])) . '?\')" class="btn px-2 py-1 rounded text-[10px] border border-edge text-dimm hover:border-red hover:text-red">DEL</button>';
        echo '</form></td></tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="8" class="py-6 text-center text-dimm text-sm">no keys yet — create one above <span class="blink">_</span></td></tr>';
    }
    echo '</tbody></table></div>';

    // logs
    echo '<div class="card p-5 mb-5 overflow-x-auto"><div class="flex items-center justify-between mb-4">';
    echo '<h2 class="text-[11px] uppercase tracking-[.25em] text-dimm">&gt; app requests (connect.php)</h2>';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '">';
    echo '<button name="action" value="clearlogs" onclick="return confirm(\'Clear all logs?\')" class="btn text-[10px] px-3 py-1.5 rounded border border-edge text-dimm hover:border-red/60 hover:text-red">CLEAR LOGS</button></form></div>';
    echo '<div class="bg-void rounded p-4 font-mono text-[11px] leading-relaxed max-h-96 overflow-auto">';
    foreach ($logs as $l) {
        echo '<div class="whitespace-pre-wrap break-all"><span class="text-dimm">$</span> ' . e($l) . '</div>';
    }
    if (!$logs) {
        echo '<div class="text-dimm">no requests yet <span class="blink">_</span></div>';
    }
    echo '</div></div>';

    echo '<p class="text-center text-[10px] text-dimm pb-6">app endpoint: <span class="text-emerald-200/70">http://domi74.page.gd/samurai/connect.php</span> · ' . e(PANEL_NAME) . ' console</p>';
    echo '</div></body></html>';
}
