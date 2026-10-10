# MOD MENU ANALYSIS — "Premium 8 Ball Pool Assistant"

**Host APK:** `com.miniclip.eightballpool` (8 Ball Pool) v56.31.1 (4039), arm64-v8a
**Mod core:** `lib/arm64-v8a/library.so` (loaded as `System.loadLibrary("rary")`)
**Vendor contact:** Telegram `@bum7md8` (displayed in-menu as *Developer / Seller / Telegram*)
**Menu product name (from binary):** `Premium 8 Ball Pool Assistant` (logs also reference `AutoAim Enhanced`)
**ImGui version embedded:** `1.85 WIP` (OpenGL ES backend, FreeType font loading incl. Arabic fonts)

---

## 1. What was injected into the original app

| Component | Location | Role |
|---|---|---|
| `library.so` | `lib/arm64-v8a/library.so` (5.0 MB, unobfuscated ELF, statically links **libcurl + OpenSSL + libcrypto**) | The entire mod menu: ImGui overlay, aimbot/ESP, AutoPlay AI, license client, anti-tamper |
| `libloader.so` | `lib/arm64-v8a/libloader.so` | Runtime unpacker (decrypts Miniclip's real game lib `libgame-*.so` at runtime — original game protection) |
| Boot hook | `loQPY7.ldv373.oQLNW6.xT9Oe5()` → `System.loadLibrary("rary")` then `System.loadLibrary("loader")` | Mod lib loads **first**, then the game loader |
| Encrypted payload | `assets/j1O1pP4cpnaLPxs2xoSf/` (65 files, magic `0x10FF1CE1`) | Mod menu encrypted data files (not yet decryptable — key lives inside obfuscated native code) |
| Encrypted overlay libs | `assets/a0rjgdfbjd8fhfglkew6/2de1a92e00ace60a/<abi>/` | Tiny encrypted copies of the protected `.so` set (re-injection / update staging) |

### JNI surface of `library.so` (only 9 exports — everything else registered at runtime)
```
JNI_OnLoad
Java_android_service_SurfaceView_MenuColor      <- floating menu theme color
Java_android_service_SurfaceView_getExpTime     <- license expiry timestamp
Java_android_service_SurfaceView_onCanvasDraw   <- overlay canvas drawing
Java_android_service_SurfaceView_onSendConfig   <- config push into menu
Java_com_qiyi_xhook_NativeHandler_*             <- PLT hook engine (qiyi xhook)
```
(The `android.service.SurfaceView` class does **not** exist in the APK — these names are camouflage; real binding happens via `RegisterNatives`.)

---

## 2. License / key system (fully reversed)

### Key format
`bum7md7-12-gnMmz` = `<account/series tag>-<duration (days/tier)>-<random suffix>`
- Vendor tag prefix (`bum7md8` is the seller's Telegram handle; keys are issued per-reseller).
- Middle segment is the duration/tier (`Tier %s` is displayed from the server response).
- Keys are validated **server-side only**; there is no offline key check (a `.hwid` cache file is the only local state).

### HWID
- HWID = **Android `Settings.Secure.ANDROID_ID`** (fetched via JNI: `android/provider/Settings$Secure` → `getString(..., ANDROID_ID)`).
- Cached to a file named `.hwid` in the app data dir (`fopen(".hwid","rb")` on start, `"wb"` after first generation).
- Shown in the menu as `Device HWID: <id>`.

### Request (reconstructed from native code at `library.so!0x1b5548` / `0x1b63d8`)

```
POST https://venomkey.com/connect        <- real endpoint (XOR-obfuscated in .rodata, see §4)
Host: venomkey.com
Content-Type: application/x-www-form-urlencoded
SSL certificate verification: DISABLED (CURLOPT_SSL_VERIFYPEER=0, CURLOPT_SSL_VERIFYHOST=0)
Timeout: 15 s

game=<base64/AES-encoded game id>&user_key=<encoded license key>&serial=<encoded HWID>
```
- Encoding helper at `0x1b5400` (AES/transform pipeline, keys resident in obfuscated memory) — the three POST values are *not* sent as plaintext.
- Log lines emitted around the request: `Connecting to server: <url>`, `GAME:<id> (PUBG)` (the license panel is a multi-game panel reused from a PUBG mod), `License Key: <key>`, `Server Response: <resp>`.
- The menu's *License Information* panel displays `Server: watan-keys.cu.ma` (reseller panel host) while the code actually calls `venomkey.com/connect`.

### Response handling
- Success sentinel checked in the body: `"status":true` (also tolerant of `"status": true`).
- On success: `Login successful.`, the key/tier/expiry are stored (`EXPIRY TIME:` is rendered from the response; helper prints `Tier %s`), and menu flags flip on.
- Failure paths produce exactly one of: `Key is empty`, `Failed to get Android ID`, `unknown token`, `Login failed: <reason>`, `No response from server`, `HTTP request failed: <curl error>`.
- JSON fields referenced by the parser: `status`, `token`, `data`, `reason`, `subtype`, `expiry`.

### Both protocol endpoints
| Host | Role |
|---|---|
| `https://venomkey.com/connect` | **Actual license validation API** (hidden, XOR-obfuscated) |
| `watan-keys.cu.ma` | Reseller/panel host displayed in the UI ("Server:") |

---

## 3. Mod menu feature set (recovered strings → features)

**Aim / assistance**
- `fAIM_MaxSnapPerFrame`, `fAIM_AngleStep`, `fAIM_NoRecoilMultiplier`, `fAIM_SnapLineThickness`
- Snap lines: `cAIM_SnapLine`, `cAIM_SnapLineBot`, styles `SOLID` / `STRIPE`, `iLineStyle`
- `VisualCue`, `iXray` (see-through table), `Draw Pockets`, `bESP_DrawPockets`

**ESP / overlay**
- `cESP_Line`, `cESP_Distance`, `cESP_HealthHidden`, `cESP_NameHidden`, `cESP_SkeletonHidden`
- `fESP_SkeletonThickness`, `fESP_HiddenAlpha`, `ESP::STATES`

**AutoPlay (AI)**
- `Auto Play`, `Enable Auto Play`, `bAutoPlay`, `fShotPower`, `fShotDelay`, `fSpeed`, `Delay between shots`
- `Approve the shot?`, `Approval before launch`, `Auto-accept in`
- AI decision logs: `AutoPlay AI: PICK rank=%d ball=%d pocket=%d score=%.1f cut=%.1f power=%.1f`,
  `AutoPlay SLOW: ball=%d pocket=%d angle=%.3f power=%.1f`
- Self-learning: `AI Learn: recorded failure ball=%d pocket=%d angle=%.3f power=%.1f (mem=%zu)`,
  `AI Learn: avoiding past failure ball=%d pocket=%d`

**Auto Queue / betting**
- `Auto Queue`, `Enable Auto Queue`, `AUTO::QUEUE`, `Queue selection mode`, `StartLastMatch`
- `Selected mode %s with bet %lld (50% of %lld coins)` — auto-enters matches betting half the coins
- Result telemetry: `Potted: %d balls (score: %.0f)`

**Game-memory access**
- Hooks into the Cocos2d-x game via symbols: `CCDirector`, `MainManager`, `MenuManager`, `StateManager`
- `Searching 8BP base until found...`, `Library found! Exiting loop...`, `Wolf founded` (+ optional companion `libwolf.so`), `F0/FMAX/F1000/I1000/LOGS/TRUE/VOID: %i` debug counters
- Emulator/root trips: `MAJISK` (Magisk) detection string, regexes on `/proc/self/maps`:
  - `/data/.*./com\.miniclip\.eightballpool/[0-9A-Z]{32}/[0-9a-z]{12}/[0-9a-z]{20}/`
  - `.*/com.miniclip.eightballpool/.*`

**Menu/UX**
- ImGui windows: `##Menu_%02d`, `##ContextMenu`, `##MainMenuBar`, `#CLOSE`, `Modal`, popups, color pickers
- Settings persistence: `/no_backup/persistence.json`, `/no_backup/style.json` (ImGui style), `imgui_log.txt`, `Log To File`, `Settings unpacked data (.ini): %d bytes`
- Clipboard login: `LOGIN (paste key from clipboard)`, `Paste your license key then tap LOGIN`
- Fonts loaded from device: `/system/fonts/Roboto-Regular.ttf`, `NotoNaskhArabic-Regular.ttf`, `NotoNaskhArabicUI-Regular.ttf` (Arabic UI support)
- Strings: `Premium 8 Ball Pool Assistant`, `License Information`, `License Key:`, `Developer: @bum7md8`, `SELLER: `, `Telegram: `, `To get a key, contact Telegram @bum7md8`, `Contact @bum7md8 on Telegram`, `Language`, `Notifications`, `No new notifications`, `Logout`, `Tools`, `Fonts`, `Table`, `Default Depth`

---

## 4. String obfuscation (cracked)

Mod config strings are XOR-encrypted at rest and materialized by a static initializer at
`library.so!0x239e48` (single-instance guard at `0x5028e0`):

- XOR key (8 bytes, repeating): **`17 05 35 6D 07 D5 33 95`** (stored in `.rodata` at `0xE76E0`)
- Encrypted URL blob at `.rodata:0xE7C20` (16 B) + `0xE6D08` (8 B) + immediates `0x19566079`, `0x107`
- Decrypted result:

```
https://venomkey.com/connect
```

Byte-wise immediates in `0x23B9C8-0x23BAA8` (`[0x10]^0x17, [0x11]^0x05, [0x12]^0x35, [0x13]^0x6D,
[0x14]^0x07, [0x15]^0xD5, [0x16]^0x33, [0x17]^0x95, [0x18]^0x17, [0x19]^0x05, [0x1A]^0x35,
[0x1B]^0x6D, [0x1C]^0x07, [0x1D]=NUL`) are the same key applied to the tail.

### Key native offsets (library.so)
| Addr | What |
|---|---|
| `0x1B5548` | HTTP POST wrapper (libcurl): URL + body args; disables TLS verify; 15 s timeout |
| `0x1B57F8` | HWID loader/saver (`.hwid` file) |
| `0x1B5400` | POST-value encoder (AES/base64 pipeline) |
| `0x1B63D8` | License validation: builds `game=&user_key=&serial=`, calls server, parses response |
| `0x1B7884` | Login outcome: success store (`0x5027E8/0x502800/0x5027B8`) vs `Key is empty` / `Login failed:` |
| `0x1B4C80` | `Tier %s` renderer |
| `0x1A895C` | `ANDROID_ID` retrieval |
| `0x239E48` | Config/string decrypt init (XOR key application) |
| `0x1C91AC` | ImGui `License Information` window (`Server: watan-keys.cu.ma`, `Telegram: @bum7md8`) |

---

## 5. What remains encrypted / inaccessible (static analysis)

1. **The real game code** — `libgame-BPM-...-3902.so` (81 MB) and 10 other third-party `.so` are
   packed with a custom crypter (bad ELF magic). They are decrypted in-memory only by `libloader.so`
   on a genuine device. No static keys were present.
2. **The 65 payload files** (`assets/j1O1pP4cpnaLPxs2xoSf/`, container magic `0x10FF1CE1`, 60-byte
   header, 16-byte IV field at offset 32) — AES-family container; key derived inside the packed
   runtime. Structure documented in `analysis/ENCRYPTED_ASSETS.md`.
3. **The exact POST encoder** — values are transformed (AES/base64 hybrid at `0x1B5400`) with keys
   that live only inside the obfuscated runtime; the wire format can be confirmed by observing a
   live login (mitmproxy on the device).
4. Miniclip's own Java string vault (`jLKPr9.hY1Z05`) — backed by native `ouc9R2` calls; runtime-only.
