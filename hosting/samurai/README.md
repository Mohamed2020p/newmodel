# SAMURAI Console — panel for the SAMURAI mod (libsamurai.so)

Design: "Night Byte / Private Key Console" style — dark terminal, neon green,
JetBrains Mono, scanlines. **Password-only login** (no username).

## Upload — everything in this folder → `htdocs/samurai/`

```
htdocs/samurai/index.php      admin console (this design)
htdocs/samurai/connect.php    THE endpoint the app calls (PWF protocol)
htdocs/samurai/lib.php        storage + auth
htdocs/samurai/config.php     settings — CHANGE THE PASSWORD HERE
htdocs/samurai/.htaccess      blocks data/ (keys + logs)
htdocs/samurai/README.md      this file
htdocs/samurai/assets/logo.png
```

CURRENT LIVE SETUP (what libsamurai.so v5 calls):
  app endpoint : https://domi74.page.gd/connect.php          <- root connect.php (already uploaded, verified live)
  console      : upload index.php + lib.php + config.php wherever you want the admin UI
  logging      : connect.php writes to data/logs.txt (same data/ folder as the k.php panel)

If you prefer the folder layout instead: upload this WHOLE folder to htdocs/samurai/
and ask to repoint the .so to https://domi74.page.gd/samurai/connect.php (needs https!).

## Login

Password only: **changeme123** → change `ADMIN_PASSWORD` in `config.php`
(or set `ADMIN_PASSWORD_HASH` with `password_hash('...', PASSWORD_DEFAULT)`).

## Keys

- Keys file: `samurai/main.txt` (auto-created; format `key|days|status|hwid|activated|expires|note`)
- Generate from the console, or add lines by hand. Prefix: `samurai-30-Xk2P9`.

## Takeover mode (default)

`STRICT_KEYS = false` in config.php → **every login succeeds** even with unknown
keys (the .so is under your control anyway). Set it to `true` to enforce the key list.

## Tuning the response

The app's requests are logged to `samurai/data/logs.txt` (PARAMS + BODY).
If the app displays the `message` field you'll see your text in its window.
If it needs different field names, paste me a log line and `connect.php`'s
response gets adjusted in one edit.
