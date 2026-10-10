<?php
/**
 * index.php — admin panel (Tailwind CSS + Poppins).
 * Keys file: main.txt next to the PHP files. Logs: data/logs.txt.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$err = '';
$msg = '';

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
        $days = min(999, $custom);
    } elseif (isset(PLANS[$planKey])) {
        $days = PLANS[$planKey][0];
    } else {
        $days = 30;
    }
    try {
        $made = [];
        for ($i = 0; $i < $qty; $i++) {
            $made[] = create_key($days, $note);
        }
        $msg = 'Generated ' . count($made) . ' key' . (count($made) > 1 ? 's' : '');
    } catch (Throwable $ex) {
        $err = 'Generation failed: ' . e($ex->getMessage());
    }
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
            $msg = 'Key added: ' . $k . ' (' . ($d > 0 ? $d . ' days' : 'lifetime') . ')';
        }
    }
} elseif ($action === 'reset') {
    check_csrf();
    $k     = (string)($_POST['k'] ?? '');
    $rows  = keys_load();
    $idx   = keys_find($rows, $k);
    if ($idx >= 0) {
        $rows[$idx]['hwid']      = '';
        $rows[$idx]['activated'] = 0;
        $rows[$idx]['expires']   = 0;
        $rows[$idx]['status']    = 'active';
        keys_save($rows);
        $msg = 'HWID reset — ' . $k . ' can be activated again';
    }
} elseif ($action === 'toggle') {
    check_csrf();
    $k    = (string)($_POST['k'] ?? '');
    $rows = keys_load();
    $idx  = keys_find($rows, $k);
    if ($idx >= 0) {
        $rows[$idx]['status'] = strtolower($rows[$idx]['status']) === 'active' ? 'banned' : 'active';
        keys_save($rows);
        $msg = $k . ' is now ' . $rows[$idx]['status'];
    }
} elseif ($action === 'delete') {
    check_csrf();
    $k    = (string)($_POST['k'] ?? '');
    $rows = keys_load();
    $idx  = keys_find($rows, $k);
    if ($idx >= 0) {
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

function head(string $title, bool $center = false): void
{
    $bg = 'assets/bg.jpg';
    echo <<<HTML
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>$title</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script>tailwind.config={theme:{extend:{fontFamily:{poppins:['Poppins','sans-serif']},colors:{night:'#0d1421',panel:'#111a2b',edge:'#223250',gold:'#f2d15c',goldd:'#c79e3d'}}}}</script>
<style>
body{font-family:'Poppins',sans-serif;background:#0d1421 url('$bg') center/cover fixed;}
.card{background:rgba(17,26,43,.92);backdrop-filter:blur(10px);border:1px solid #223250;border-radius:1rem;}
.gold-text{background:linear-gradient(135deg,#f7e08b,#f2d15c 45%,#c79e3d);-webkit-background-clip:text;background-clip:text;color:transparent;}
.btn{transition:all .15s ease}.btn:active{transform:scale(.97)}
::-webkit-scrollbar{width:9px;height:9px}::-webkit-scrollbar-thumb{background:#223250;border-radius:9px}
table tbody tr:hover{background:rgba(66,150,250,.05)}
</style></head><body class="min-h-screen text-slate-200">
HTML;
}

function logo(int $size = 72): void
{
    echo '<img src="assets/logo.png" alt="" style="width:' . $size . 'px;height:auto" class="drop-shadow-[0_0_18px_rgba(242,209,92,.25)]">';
}

function toast(string $msg, string $err): void
{
    if ($msg === '' && $err === '') {
        return;
    }
    $ok  = ($msg !== '');
    $txt = $ok ? $msg : $err;
    echo '<div id="toast" class="fixed top-5 left-1/2 -translate-x-1/2 z-50 px-5 py-3 rounded-xl shadow-2xl text-sm font-medium border '
        . ($ok ? 'bg-emerald-950/95 border-emerald-600 text-emerald-200'
               : 'bg-rose-950/95 border-rose-600 text-rose-200') . '">'
        . e($txt) . '</div>
<script>setTimeout(()=>{const t=document.getElementById("toast");if(t)t.remove()},4000)</script>';
}

function render_login(string $err): void
{
    $name = PANEL_NAME;
    head($name . ' — Login', true);
    toast('', $err);
    echo '<div class="min-h-screen flex items-center justify-center p-4">';
    echo '<div class="card w-full max-w-sm px-8 py-10 text-center">';
    logo(96);
    echo '<h1 class="mt-4 text-2xl font-bold gold-text tracking-wide">' . e($name) . '</h1>';
    echo '<p class="text-slate-400 text-xs mt-1 mb-8">key generator &amp; validator</p>';
    echo '<form method="post" class="space-y-4 text-left">';
    echo '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '"><input type="hidden" name="action" value="login">';
    echo '<input name="user" placeholder="Username" autofocus class="w-full bg-night/80 border border-edge rounded-xl px-4 py-3 text-sm outline-none focus:border-gold transition">';
    echo '<input name="pass" type="password" placeholder="Password" class="w-full bg-night/80 border border-edge rounded-xl px-4 py-3 text-sm outline-none focus:border-gold transition">';
    echo '<button class="btn w-full rounded-xl py-3 font-semibold text-night bg-gradient-to-r from-gold to-goldd hover:brightness-110 shadow-lg shadow-gold/20">LOGIN</button>';
    echo '</form></div></div></body></html>';
}

function badge_html(string $st): string
{
    $map = [
        'active'  => ['bg-emerald-500/15', 'text-emerald-400', 'border-emerald-600/40'],
        'unbound' => ['bg-sky-500/15',     'text-sky-400',     'border-sky-600/40'],
        'expired' => ['bg-amber-500/15',   'text-amber-400',   'border-amber-600/40'],
        'banned'  => ['bg-rose-500/15',    'text-rose-400',    'border-rose-600/40'],
        'OK'      => ['bg-emerald-500/15', 'text-emerald-400', 'border-emerald-600/40'],
        'FAIL'    => ['bg-rose-500/15',    'text-rose-400',    'border-rose-600/40'],
    ];
    $c = $map[$st] ?? ['bg-slate-500/15', 'text-slate-400', 'border-slate-600/40'];
    return '<span class="inline-block px-2.5 py-0.5 rounded-full border text-[11px] font-semibold '
         . $c[0] . ' ' . $c[1] . ' ' . $c[2] . '">' . e($st) . '</span>';
}

function render_dashboard(string $msg, string $err): void
{
    $name = PANEL_NAME;
    $csrf = e(csrf_token());
    $rows = keys_load();
    $logs = logs_recent(60);

    $stats = ['total' => 0, 'active' => 0, 'unbound' => 0, 'expired' => 0, 'banned' => 0];
    foreach ($rows as $k) {
        $stats['total']++;
        $stats[key_status($k)]++;
    }

    head($name);
    toast($msg, $err);
    ?>
<div class="max-w-6xl mx-auto px-4 py-8">
  <header class="flex items-center justify-between mb-8">
    <div class="flex items-center gap-4">
      <?php logo(58); ?>
      <div>
        <h1 class="text-xl font-bold gold-text tracking-wide"><?= e($name) ?></h1>
        <p class="text-[11px] text-slate-500">keys file: <span class="text-slate-400"><?= e(key_file()) ?></span></p>
      </div>
    </div>
    <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>">
      <button name="action" value="logout" class="btn text-xs px-4 py-2 rounded-lg border border-edge text-slate-400 hover:text-white hover:border-rose-500/60">Logout</button>
    </form>
  </header>

  <section class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-8">
<?php foreach ($stats as $label => $val): ?>
    <div class="card px-4 py-3 text-center">
      <div class="text-2xl font-bold <?= $label === 'active' ? 'text-emerald-400' : 'gold-text' ?>"><?= (int)$val ?></div>
      <div class="text-[11px] uppercase tracking-wider text-slate-500"><?= e($label) ?></div>
    </div>
<?php endforeach; ?>
  </section>

  <section class="card p-5 mb-6">
    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-4">Generate keys</h2>
    <form method="post" class="flex flex-wrap gap-3 items-center">
      <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="generate">
      <select name="plan" class="bg-night/80 border border-edge rounded-lg px-3 py-2.5 text-sm outline-none focus:border-gold">
<?php foreach (PLANS as $pk => $p): ?>
        <option value="<?= e($pk) ?>"><?= e($p[1]) ?><?= $p[0] ? ' — ' . sprintf('%02d', $p[0]) . 'd' : ' — lifetime' ?></option>
<?php endforeach; ?>
      </select>
      <input type="number" name="qty" value="1" min="1" max="100" class="w-20 bg-night/80 border border-edge rounded-lg px-3 py-2.5 text-sm outline-none focus:border-gold">
      <input type="number" name="custom_days" placeholder="custom days" min="1" max="999" class="w-32 bg-night/80 border border-edge rounded-lg px-3 py-2.5 text-sm outline-none focus:border-gold">
      <input name="note" placeholder="note (optional)" class="flex-1 min-w-[160px] bg-night/80 border border-edge rounded-lg px-3 py-2.5 text-sm outline-none focus:border-gold">
      <button class="btn rounded-lg px-5 py-2.5 font-semibold text-night bg-gradient-to-r from-gold to-goldd hover:brightness-110 text-sm">Create</button>
    </form>
    <form method="post" class="flex flex-wrap gap-3 items-center mt-3 pt-3 border-t border-edge/60">
      <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="addkey">
      <input name="newkey" placeholder="add an existing key manually (e.g. bum7md7-365-2nHVj)" class="flex-1 min-w-[220px] bg-night/80 border border-edge rounded-lg px-3 py-2.5 text-sm outline-none focus:border-gold">
      <input type="number" name="newdays" placeholder="days (0=lifetime)" min="0" max="999" class="w-40 bg-night/80 border border-edge rounded-lg px-3 py-2.5 text-sm outline-none focus:border-gold">
      <button class="btn rounded-lg px-5 py-2.5 font-semibold text-sm border border-gold/60 text-gold hover:bg-gold/10">Add key</button>
    </form>
  </section>

  <section class="card p-5 mb-6 overflow-x-auto">
    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-4">Keys</h2>
    <table class="w-full text-sm">
      <thead><tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 border-b border-edge">
        <th class="py-2 pr-3">Key</th><th class="py-2 pr-3">Days</th><th class="py-2 pr-3">Status</th>
        <th class="py-2 pr-3">Device (HWID)</th><th class="py-2 pr-3">Activated</th><th class="py-2 pr-3">Expires</th>
        <th class="py-2 pr-3">Note</th><th class="py-2">Actions</th></tr></thead>
      <tbody>
<?php foreach ($rows as $k): $st = key_status($k); ?>
        <tr class="border-b border-edge/40">
          <td class="py-2.5 pr-3 font-mono text-[13px] text-white whitespace-nowrap"><?= e((string)$k['key']) ?>
            <button class="btn align-middle ml-1 text-[10px] px-1.5 py-0.5 rounded border border-edge text-slate-500 hover:text-gold hover:border-gold/50"
              onclick="navigator.clipboard.writeText('<?= addslashes(e((string)$k['key'])) ?>');this.textContent='✓'" >copy</button></td>
          <td class="py-2.5 pr-3 text-slate-400"><?= $k['days'] > 0 ? (int)$k['days'] . 'd' : '∞' ?></td>
          <td class="py-2.5 pr-3"><?= badge_html($st) ?></td>
          <td class="py-2.5 pr-3 font-mono text-[11px] text-slate-500"><?= e((string)$k['hwid']) !== '' ? e((string)$k['hwid']) : '—' ?></td>
          <td class="py-2.5 pr-3 text-slate-400 text-xs"><?= fmt_date($k['activated']) ?></td>
          <td class="py-2.5 pr-3 text-slate-400 text-xs"><?= (int)$k['expires'] > 0 ? fmt_date($k['expires']) : ($k['activated'] ? 'Never' : '—') ?></td>
          <td class="py-2.5 pr-3 text-slate-500 text-xs"><?= e((string)$k['note']) ?></td>
          <td class="py-2.5">
            <form method="post" class="flex gap-1.5">
              <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="k" value="<?= e((string)$k['key']) ?>">
              <button name="action" value="reset"  title="unbind device" class="btn px-2.5 py-1 rounded-md text-[11px] border border-edge text-slate-300 hover:border-sky-500 hover:text-sky-400">Reset</button>
              <button name="action" value="toggle" class="btn px-2.5 py-1 rounded-md text-[11px] border <?= $st === 'banned' ? 'border-emerald-600/50 text-emerald-400 hover:bg-emerald-500/10' : 'border-rose-600/50 text-rose-400 hover:bg-rose-500/10' ?>"><?= $st === 'banned' ? 'Enable' : 'Ban' ?></button>
              <button name="action" value="delete" onclick="return confirm('Delete <?= addslashes(e((string)$k['key'])) ?>?')" class="btn px-2.5 py-1 rounded-md text-[11px] border border-edge text-slate-500 hover:border-rose-600 hover:text-rose-400">Del</button>
            </form>
          </td>
        </tr>
<?php endforeach; ?>
<?php if (!$rows): ?>
        <tr><td colspan="8" class="py-6 text-center text-slate-500 text-sm">No keys yet — create one above.</td></tr>
<?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="card p-5 mb-6 overflow-x-auto">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Recent /connect attempts</h2>
      <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>">
        <button name="action" value="clearlogs" onclick="return confirm('Clear all logs?')" class="btn text-[11px] px-3 py-1.5 rounded-md border border-edge text-slate-400 hover:border-rose-600/60 hover:text-rose-400">Clear logs</button>
      </form>
    </div>
    <table class="w-full text-sm">
      <thead><tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 border-b border-edge">
        <th class="py-2 pr-3">Time (UTC)</th><th class="py-2 pr-3">IP</th><th class="py-2 pr-3">Key</th>
        <th class="py-2 pr-3">Device</th><th class="py-2 pr-3">Result</th><th class="py-2">Reason</th></tr></thead>
      <tbody>
<?php foreach ($logs as $l): ?>
        <tr class="border-b border-edge/40">
          <td class="py-2 pr-3 text-slate-500 text-xs"><?= e(gmdate('m-d H:i:s', $l['ts'])) ?></td>
          <td class="py-2 pr-3 text-slate-400 text-xs"><?= e($l['ip']) ?></td>
          <td class="py-2 pr-3 font-mono text-[12px] text-slate-300"><?= e($l['key']) ?></td>
          <td class="py-2 pr-3 font-mono text-[11px] text-slate-500"><?= e($l['serial']) ?></td>
          <td class="py-2 pr-3"><?= badge_html($l['ok'] === 1 ? 'OK' : 'FAIL') ?></td>
          <td class="py-2 text-slate-400 text-xs"><?= e($l['reason']) ?></td>
        </tr>
<?php endforeach; ?>
<?php if (!$logs): ?>
        <tr><td colspan="6" class="py-6 text-center text-slate-500 text-sm">No attempts yet.</td></tr>
<?php endif; ?>
      </tbody>
    </table>
  </section>

  <p class="text-center text-[11px] text-slate-600 pb-6">app endpoint: <span class="font-mono text-slate-500">http://domi74.page.gd/k.php</span> · <?= e(PANEL_NAME) ?></p>
</div>
</body></html>
<?php
}
