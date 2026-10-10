# License Panel — key generator + validator for the mod menu (TXT edition, no database)

Self-hosted replacement for the vendor's key server. Written from the reverse-engineered
contract of the mod (`lib/arm64-v8a/library.so`, "MAJISK v2.0 — Gold Edition") so the app
accepts it **without any modification of the request/response logic**.

**No database.** Keys live in ONE plain text file: `data/main.txt` (created automatically).
You can hand-edit it, back it up, or generate keys by writing lines into it yourself.

## Files

| File | Purpose |
|---|---|
| `connect.php` | **the validator** — the endpoint the app POSTs to; reads `main.txt` |
| `index.php` | admin panel — login, generate keys, ban/reset/delete, view logs |
| `lib.php` | text-file storage + helpers + the response builder |
| `config.php` | settings: key prefix, plans, admin password, rate limit |
| `.htaccess` | blocks web access to `data/`, `*.txt`, db files (Apache) |

Requirements: any PHP 7.4+ / 8.x host. `data/` must be writable (auto-created).

## `main.txt` format

```
# license keys — one per line: key|days|status|hwid|activated|expires|note
bum7md7-30-Xk2P9|30|active|a1b2c3d4e5f60718|1728568800|1731160800|vip friend
bum7md7-00-Qw7Zn|0|active||||        <- lifetime key, not bound to a device yet
bum7md7-07-mR4tY|7|banned||||        <- banned, cannot login
```
- `days`: 0 = lifetime · `status`: `active` or `banned`
- `hwid/activated/expires`: filled automatically on first use (0/empty = not yet)
- timestamps are unix time; `expires = 0` = never expires

## Deploy (5 minutes)

1. Upload the `hosting/` folder contents to your web root (or a subfolder).
2. Edit `config.php`: set `ADMIN_PASS_PLAIN` (or a `password_hash()`), keep
   `KEY_PREFIX` = `bum7md7`, keep `GAMES_ALLOWED = ['PUBG']`.
3. Open `https://your-domain/` → login → **Generate keys**.
4. Point the app at `https://your-domain/connect` (see "Pointing the app" below).

---

## THE CONTRACT — everything the website needs (do not lose this)

### 0. Which server actually checks the keys

**`https://venomkey.com/connect`** — proven by decrypting the XOR-encrypted URL inside
`library.so` (key `17 05 35 6D 07 D5 33 95`): the ciphertext decrypts byte-for-byte to
`https://venomkey.com/connect`. The string `watan-keys.cu.ma` appears exactly once in the
whole binary — as the cosmetic text label "Server: watan-keys.cu.ma" in the login window
(xref 0x1C92A0). **It never receives anything.**

### 1. Request

```
POST /connect
Content-Type: application/x-www-form-urlencoded

game=<base64(gameId)>&user_key=<base64(license key)>&serial=<base64(ANDROID_ID)>
```
- The app sends exactly: `game` → `PUBG` (`UFVCRw==`), `user_key` → the typed key,
  `serial` → device HWID (ANDROID_ID).
- Timeout 15 s · TLS verification disabled in the app (any cert works) ·
  GET must fail (original returns HTTP 500 — this panel mimics it).

### 2. Response — exact shape

**Success (200, JSON):**
```json
{"status":true,"token":"32hexchars","data":"Month","exp":"2026-11-09 18:20","subtype":"Month","reason":""}
```
**Failure (200, JSON):**
```json
{"status":false,"token":"","data":"","exp":"","subtype":"","reason":"Invalid key"}
```

Hard rules (the app's parser checks these — from the disassembly):

| Rule | Why |
|---|---|
| Success body MUST contain the literal substring `"status":true` (no space) | parser substring-scans BEFORE json-parsing; spaced `"status": true` also tolerated |
| Failure body must NOT contain `"status":true` | otherwise the app logs the user in |
| Body must be valid JSON | the app also parses it with nlohmann::json |
| Body must never be EMPTY | empty → "No response from server" |
| Fields `token` `data` `exp` `subtype` `reason` all scanned by quoted name | missing field = empty string |

### 3. What the app does with each field

| Field | Meaning in the app |
|---|---|
| `token` | session token, stored by the app; any non-empty string |
| `data` | license payload string (we return the plan label) |
| `exp` | rendered **`EXPIRY TIME: <exp>`**; readable date `Y-m-d H:i`; `Never` for lifetime |
| `subtype` | rendered **`Tier <subtype>`** — the plan name |
| `reason` | shown on the login window when status=false |

### 4. Behavior rules implemented

- **HWID lock**: first device binds the key; others get
  `Key is already used on another device`. Admin **Reset** unbinds.
- Expiry computed at **first activation** (activation + plan days).
- Mirrors the app's own error strings: `Key is empty`, `Failed to get Android ID`,
  `Invalid key`, `Key is banned`, `Key has expired`.
- Rate limit per IP → `Too many attempts, wait a minute`.
- Every attempt logged to `data/logs.txt` and shown in the admin.

### 5. Key format — identical to the vendor

Vendor example: **`bum7md7-12-gnMmz`** = **16 characters**:

```
b u m 7 m d 7  -  1 2  -  g n M m z
└─ prefix 7 ─┘    └ days 2 ┘   └ random 5 ┘
```
This generator produces exactly that: `KEY_PREFIX` (7 chars) + `-` + **zero-padded
2-digit days** (`01`–`99`, lifetime = `00`) + `-` + 5 random chars (no 0/O/1/l/I).
Durations over 99 days use the full number (e.g. `-365-`); the app treats the key as an
opaque string, so length beyond the 16-char standard is only a display thing.

## Pointing the app at this panel

The endpoint URL is XOR-encrypted inside `library.so` with key `17 05 35 6D 07 D5 33 95`:
ciphertext at `.rodata` 0xE7C20 (16 B) + 0xE6D08 (8 B) + imm32 `0x19566079` (`nect`) +
imm16 `0x0107` → assembled into global 0x502770. Patch by encrypting your URL with the
same key and writing it over 0xE7C20… (keep the total length ≤ the original):

```bash
python3 - <<'EOF'
key = bytes([0x17,0x05,0x35,0x6d,0x07,0xd5,0x33,0x95])
url = b"https://your-domain/connect\x00"
print(bytes(b ^ key[i % 8] for i, b in enumerate(url)).hex())
EOF
```

`game=` stays `PUBG` — the panel accepts it.

## Original vendor endpoints (reference)

- `https://venomkey.com/connect` — the real endpoint (crypto-verified; POST-only PHP,
  GET → HTTP 500, behind Cloudflare).
- `https://watan-keys.cu.ma` — cosmetic UI string only, never contacted.
