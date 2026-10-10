<?php
/**
 * config.php — panel settings. Edit this file only.
 */

// Panel name shown in the admin UI
define('PANEL_NAME', '8 Ball Pool Panel');

// ---------- Key generation ----------
// Same shape as the vendor key (bum7md7-12-gnMmz = 16 chars):
//   7-char prefix + '-' + 2-digit days + '-' + 5 random chars
// Lifetime keys use 00 in the middle:  free4us-00-Qw7Zn
define('KEY_PREFIX', 'free4us');
define('KEY_SUFFIX_LEN', 5);

// ---------- /connect validator ----------
// This panel IS for the 8 Ball Pool mod menu. NOTE: the stock mod app sends
// game=PUBG (base64 "UFVCRw==") — the mod author reused a PUBG template and the
// game id "PUBG" is hardcoded in library.so (crypto-verified). So:
//   'PUBG'  → accepted (what the UNMODIFIED app sends)
//   '8BALL' → accepted (what the app sends AFTER the optional game-id patch,
//              see README "Pointing the app at this panel")
// Use ['*'] to accept anything.
define('GAMES_ALLOWED', ['PUBG', '8BALL']);

// Max /connect attempts per IP per 60 seconds (anti-bruteforce)
define('RATE_LIMIT', 30);

// Expiry string format returned to the app in "exp" (shown as "EXPIRY TIME: ...")
define('EXP_FORMAT', 'Y-m-d H:i');
// String returned for lifetime keys
define('EXP_NEVER', 'Never');

// ---------- Admin login ----------
define('ADMIN_USER', 'admin');
// BEST: generate a hash and paste it here:
//   php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
define('ADMIN_PASSWORD_HASH', '');
// Fallback plain password used ONLY while ADMIN_PASSWORD_HASH is empty. CHANGE IT.
define('ADMIN_PASS_PLAIN', 'changeme123');

// ---------- Storage ----------
// NO DATABASE. Keys live in data/main.txt (created automatically), logs in
// data/logs.txt. You can hand-edit main.txt:
//   key|days|status|hwid|activated|expires|note
//   bum7md7-30-Xk2P9|30|active|a1b2c3d4e5f60718|1728568800|1731160800|vip
//   bum7md7-00-Qw7Zn|0|active||||          <- lifetime, not bound yet
