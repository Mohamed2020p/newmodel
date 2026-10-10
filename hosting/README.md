# License Panel — key generator + validator for the mod menu

Self-hosted replacement for the vendor's key server. Written from the reverse-engineered
contract of the mod (`lib/arm64-v8a/library.so`, "MAJISK v2.0 — Gold Edition") so the app
accepts it **without any modification of the request/response logic**.

## Files

| File | Purpose |
|---|---|
| `connect.php` | **the validator** — the endpoint the app POSTs to |
| `index.php` | admin panel — login, generate keys, ban/reset/delete, view logs |
| `lib.php` | database (SQLite, auto-created), helpers, response builder |
| `config.php` | settings: key prefix, plans, admin password, rate limit |
| `.htaccess` | blocks access to `data/` and db files (Apache) |

Requirements: any PHP 7.4+ / 8.x host with `pdo_sqlite` (standard on shared hosting).
No MySQL needed — the database is a single file at `data/keys.db`.

## Deploy (5 minutes)

1. Upload the `hosting/` folder contents to your web root (or a subfolder).
2. Edit `config.php`:
   - set `ADMIN_PASS_PLAIN` (or better, generate a hash:
     `php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"` and put it in `ADMIN_PASSWORD_HASH`),
   - set `KEY_PREFIX` (default `bum7md7` — keys look like `bum7md7-30-Xk2P9`),
   - `GAMES_ALLOWED` stays `['PUBG']` (that's what the app sends — verified).
3. Make sure `data/` is writable (the panel creates it automatically; on some hosts
   `chmod 775 data` once).
4. Open `https://your-domain/` → login → **Generate keys**.
5. The app must be pointed at `https://your-domain/connect` — see "Pointing the app at
   this panel" below.

## THE CONTRACT — everything the website needs (do not lose this)

### 1. Request

```
POST /connect
Content-Type: application/x-www-form-urlencoded

game=<base64(gameId)>&user_key=<base64(license key)>&serial=<base64(ANDROID_ID)>
```

- ALL THREE values are Base64-encoded. The app sends exactly:
  - `game`   → `PUBG`          → `UFVCRw==` (crypto-verified from the binary — template leftover)
  - `user_key`→ the key the user typed, e.g. `bum7md7-12-gnMmz` → `YnVtN21kNy0xMi1nbk1teg==`
  - `serial` → the device HWID = `Settings.Secure.ANDROID_ID`
- Timeout on the app side: **15 seconds**. TLS certificate verification is DISABLED in the
  app, so a plain Let's Encrypt cert (or even self-signed) works.
- HTTP method GET → the app can't use it; original vendor returns **HTTP 500** (this panel
  mimics that).
- The app never sends headers/auth — identification is purely the three form fields.

### 2. Response — exact shape

**Success (200, JSON, compact):**
```json
{"status":true,"token":"a1b2...","data":"Month","exp":"2026-11-09 18:20","subtype":"Month","reason":""}
```

**Failure (200, JSON):**
```json
{"status":false,"token":"","data":"","exp":"","subtype":"","reason":"Invalid key"}
```

Hard rules (the app's parser checks these — from the disassembly):

| Rule | Why |
|---|---|
| Success body MUST contain the literal substring `"status":true` (no space) | the parser substring-scans for it BEFORE json-parsing; `"status": true` (spaced) is also tolerated |
| Failure body must NOT contain `"status":true` | otherwise the app would log the user in |
| Body must be valid JSON (properly quoted strings) | the app also parses it with nlohmann::json |
| Body must never be EMPTY | empty → the app shows "No response from server" |
| All six fields should always be present | `token`, `data`, `exp`, `subtype`, `reason` are extracted by scanning for their quoted names |

### 3. What the app does with each field

| Field | Meaning in the app |
|---|---|
| `token` | session token; stored in the app's config; any non-empty string (we return 32 hex chars) |
| `data` | license payload string (stored; we return the plan label) |
| `exp` | rendered as **`EXPIRY TIME: <exp>`** on the License Information panel and handed to Java `getExpTime` — a readable date string like `2026-11-09 18:20` works; we return `Never` for lifetime keys |
| `subtype` | rendered as **`Tier <subtype>`** — the plan/tier name (we return the plan label, e.g. `Month`) |
| `reason` | shown on the login window when `status` is false (e.g. `Invalid key`, `Key has expired`, `Key is banned`) |

### 4. Behavior rules implemented by this panel

- **HWID lock**: the first device (`serial`) that uses a key binds it; other devices get
  `Key is already used on another device`. Admin can unbind with the **Reset** button.
- Expiry is computed **at first activation** (activation time + plan days), not at generation.
- `Key is empty`, `Failed to get Android ID`, `Invalid key`, `Key is banned`,
  `Key has expired` — mirrors the strings the app itself expects to display.
- Rate limit: `RATE_LIMIT` attempts per IP per minute → `Too many attempts, wait a minute`.
- Every attempt is logged (time, IP, key, serial, result, reason) and visible in the admin.

### 5. Key format

`<prefix>-<days>-<5 random chars>` — e.g. `bum7md7-30-Xk2P9` (matches the observed vendor
format `bum7md7-12-gnMmz`). Lifetime keys use `9999` in the middle. The app treats the key
as an opaque string — only this panel interprets it.

## Pointing the app at this panel

The endpoint URL is XOR-encrypted inside `library.so` with the key
`17 05 35 6D 07 D5 33 95`:

- ciphertext: `.rodata` 0xE7C20 (16 B) + 0xE6D08 (8 B), then 4-byte imm `0x19566079`
  (= XOR of `nect`) and 2-byte imm `0x0107`, assembled at runtime into global 0x502770.
- To redirect to your host, encrypt your URL with the same key and patch the bytes:

```bash
python3 - <<'EOF'
key = bytes([0x17,0x05,0x35,0x6d,0x07,0xd5,0x33,0x95])
url = b"https://your-domain/connect\x00"
enc = bytes(b ^ key[i % 8] for i, b in enumerate(url))
print(enc.hex())
EOF
```

Write those bytes over 0xE7C20… (lengths must fit; the URL must stay ≤ the same total
length, so keep your domain short). The `game=` value can stay `PUBG` — the panel accepts it.

## Original vendor endpoints (for reference)

- `https://venomkey.com/connect` — the real endpoint the unmodified app POSTs to
  (crypto-verified; GET → HTTP 500, POST-only PHP).
- `https://watan-keys.cu.ma` — **cosmetic only**: it appears just once in the binary, as the
  "Server: watan-keys.cu.ma" text label in the login window. It never receives anything.
