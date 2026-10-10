# Deploy on InfinityFree (free) — step by step

This panel works on InfinityFree because it needs **no database and no MySQL** —
just PHP + one writable folder. Follow exactly:

## 1. Create the account / site
1. Register at infinityfree.com → **Create Account** (hosting account).
2. Pick a **free subdomain** — this matters! The app's endpoint URL is limited to
   **28 characters**, so choose the shortest available domain, ideally `xxxxx.rf.gd`.
   - fits:  `http://ab7mx.rf.gd/connect` (26) · `https://ab7mx.rf.gd/connect` (28)
   - too long: `xxxxx.infinityfreeapp.com`, long names in general
   - If your URL ends up over 28 chars: use `http://` instead of `https://`
     (the app disables TLS verification — plain http works), or pick a shorter name.

## 2. Upload the files
1. Open the InfinityFree **control panel → File Manager** (or FTP).
2. Go into the **`htdocs`** folder.
3. Upload these 5 files from `hosting/`:
   `index.php`, `connect.php`, `lib.php`, `config.php`, `.htaccess`
   (if the file manager hides `.htaccess`, it still uploaded — check with "show hidden files".
   No `.htaccess` = less protection, panel still works.)
4. Do NOT upload `README.md` / `patch_url.py` — those are for you, not the web.

## 3. Set PHP version
Control panel → **PHP Settings** (or "PHP Configuration") → pick **PHP 8.0 / 8.1 / 8.2**.

## 4. Test in your browser
| Open | Expected |
|---|---|
| `https://yourdomain/` | admin login page (gold/navy) |
| `https://yourdomain/connect` | `Method Not Allowed` + **HTTP 500** ← this is CORRECT (the app only uses POST) |

Then login (`admin` / `changeme123` — **change it in config.php first!**) and generate a test key.

## 5. Test the validator like the app does
Browser console (F12) on your site, paste:

```js
fetch('/connect',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
body:'game=UFVCRw%3D%3D&user_key=' + btoa('PASTE-A-KEY-HERE') + '&serial=TESTDEVICE123'})
.then(r=>r.text()).then(t=>console.log(t))
```

Valid key → `{"status":true,"token":"...","exp":"...","subtype":"...","reason":""}`
Bad key → `{"status":false,...,"reason":"Invalid key"}`

## 6. Point the app at YOUR site (instead of venomkey)
Run the patcher from this folder against the mod's `library.so`
(extract `lib/arm64-v8a/library.so` from the APK first):

```bash
python3 patch_url.py library.so http://yourdomain.rf.gd/connect --label yourdomain.rf.gd
```

- it verifies the stock bytes, patches all 4 encrypted pieces, and prints hex
  find/replace pairs (usable in MT Manager / any hex editor on Android too)
- `--label` also replaces the cosmetic "Server: watan-keys.cu.ma" text in the login window
- put the patched `library.so` back into the APK and **re-sign** the APK
  (apktool/MT Manager re-sign, or `uber-apk-signer`), then install.

## 7. If logins fail on the app (InfinityFree quirk)
InfinityFree sometimes shows a JavaScript "browser check" page to suspicious traffic.
A browser passes it; the app (no JS engine) can't. If `fetch` in step 5 suddenly returns
HTML instead of JSON, that's the challenge — the app would show
"No response from server". Test again later / from another network, or move the panel to
an API-friendly free host (e.g. a Cloudflare Worker with KV storage — I can convert the
panel to a Worker if needed).

## Notes
- `data/main.txt` is your keys file — back it up now and then (File Manager → download).
- Every login attempt is in `data/logs.txt` and visible in the admin.
- The free SSL cert (if issued) is fine; the app ignores certificates anyway.
