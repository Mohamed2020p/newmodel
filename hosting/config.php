<?php
/**
 * config.php — panel settings. Edit this file only.
 */

// Panel name shown in the admin UI
define('PANEL_NAME', 'License Panel');

// ---------- Key generation ----------
// Keys look like:  KEY_PREFIX-<days>-<5 random chars>   e.g. bum7md7-30-Xk2P9
define('KEY_PREFIX', 'bum7md7');
define('KEY_SUFFIX_LEN', 5);

// ---------- /connect validator ----------
// Decoded game= values that are accepted. Use ['*'] to accept anything.
// (The mod sends game=PUBG, base64 "UFVCRw==")
define('GAMES_ALLOWED', ['PUBG']);

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
