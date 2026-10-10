<?php
/**
 * k.php — short endpoint alias for connect.php.
 *
 * Why: the app's URL slot holds max 29 chars. "http://domi74.page.gd/k.php"
 * fits (27). "/connect.php" (33) can never fit, and the /connect rewrite
 * depends on .htaccess — this alias depends on nothing.
 *
 * The app must POST to:  http://domi74.page.gd/k.php
 */
require __DIR__ . '/connect.php';
