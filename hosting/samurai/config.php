<?php
/**
 * samurai/config.php — SAMURAI console settings. Edit this file only.
 */

// Console title shown in the UI
define('PANEL_NAME', 'SAMURAI');

// ---------- Admin login (PASSWORD ONLY) ----------
// Generate a hash:  php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
define('ADMIN_PASSWORD_HASH', '');
// Fallback plain password used ONLY while ADMIN_PASSWORD_HASH is empty. CHANGE IT.
define('ADMIN_PASSWORD', 'changeme123');

// ---------- Key generation ----------
// Format: SAMURAI-XXXXX-XXXXX-XXXXX  (25 chars — the app enforces NO fixed
// length, verified: its only check is non-empty. This is just a clean look.)
define('KEY_PREFIX', 'SAMURAI');
define('KEY_GROUPS', 3);
define('KEY_GROUP_LEN', 5);

// ---------- App endpoint (connect.php) ----------
// fail-open: even keys we don't know are accepted (pure takeover mode).
// true = only keys present in main.txt are accepted.
define('STRICT_KEYS', false);
// Decoded game/app field accepted when present. ['*'] = anything.
define('GAMES_ALLOWED', ['*']);
define('RATE_LIMIT', 60);

// Expiry format returned to the app
define('EXP_FORMAT', 'Y-m-d');
define('EXP_NEVER', '2099-12-31');
