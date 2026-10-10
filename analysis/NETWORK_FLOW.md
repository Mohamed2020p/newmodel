# NETWORK FLOW — every request path recovered from the APK

App: `com.miniclip.eightballpool` v56.31.1 — modded build ("Premium 8 Ball Pool Assistant")

---

## A. MOD MENU network traffic (the injected layer)

### A1. License validation — **primary C2/licensing endpoint**
- **URL:** `POST https://venomkey.com/connect`
- **Headers:** `Content-Type: application/x-www-form-urlencoded`
- **TLS:** certificate verification disabled (`CURLOPT_SSL_VERIFYPEER=0`, `CURLOPT_SSL_VERIFYHOST=0`)
- **Timeout:** 15 s
- **Body:** `game=<encoded>&user_key=<encoded license key>&serial=<encoded HWID>`
  - all three values pass through an AES/base64-style encoder (`library.so!0x1B5400`) before sending
  - HWID = `Settings.Secure.ANDROID_ID`, cached in `.hwid`
- **Trigger:** tapping `LOGIN` in the mod menu, and on later startups (cached key in `persistence.json`)
- **Success response:** body containing `"status":true` → `Login successful.`; parser then reads
  `token`, `data`, `expiry` (menu shows `EXPIRY TIME:` and `Tier %s`)
- **Failure responses:** `"status":false`-style bodies with `reason`/`subtype` →
  `Login failed: <reason>` / `unknown token`; transport errors →
  `HTTP request failed: <curl_easy_strerror>`; empty body → `No response from server`
- **Displayed but unused:** the UI shows `Server: watan-keys.cu.ma` (reseller panel); the code dials `venomkey.com`.

### A2. Companion module
- `libwolf.so` is referenced (`Wolf founded` log on load) — not shipped in the APK; expected to be
  dropped/fetched at runtime by the mod loader.

### A3. Transport library
- Statically linked **libcurl + OpenSSL** inside `library.so` (all `CURLOPT_*` option numbers
  observed: 10002 URL, 47 POST, 10015 POSTFIELDS, 10023 HTTPHEADER, 20011 WRITEFUNCTION, 10001
  WRITEDATA, 13 TIMEOUT, 64/81 SSL-VERIFY, 2712-style constants decoded).

---

## B. ORIGINAL GAME traffic (Miniclip layer)

### B1. Transport stack (Java, `com.miniclip.network`)
`HttpConnection.java` — all game HTTP goes through it, driven from native code via JNI callbacks
(`downloadComplete/downloadFailed`):
- Default **HTTP/3 (QUIC)** via Google **Cronet** (`CronetProviderInstaller`, `enableQuic(true)`,
  `enableHttp2(true)`, brotli off); falls back to `HttpURLConnection` (HTTP/1.1) when Cronet is absent.
- Verbs: GET/POST/PUT; POST/PUT set `Content-Type: application/x-www-form-urlencoded`
  (`ShareTarget.ENCODING_TYPE_URL_ENCODED`) + `Content-Length`.
- Manual redirect following (301/302/303) in the fallback path; Cronet follows redirects natively.
- All request/response bytes cross the JNI boundary to the native game — **endpoint strings are not
  in Java**; they are constructed inside the encrypted native game lib at runtime.

### B2. Endpoints recoverable statically (Java)
| Endpoint | Method | Use |
|---|---|---|
| `https://avatars-live.pool.miniclippt.com/avatars/pool/v2/<uid>-<size>x<size>.png?s=1&u=1` | GET | Player avatars for rich notifications (`NotificationSupportData.DEFAULT_AVATARS_URL`; override via `NotificationSupport.json` key `avatarsUrlForLastSelectedEnvironment`) |
| `https://api-project-697261581904.firebaseio.com` | — | Firebase DB (config), project `api-project-697261581904` |
| `AIzaSyC-2ZGpnyIC9SSDgxBAFLb9hRfXUEYyTMw` | — | Google/Firebase API key (strings.xml) |
| `https://app-measurement.com/s/d`, `/a` | POST | Firebase Analytics |
| `https://prod.adjoe.zone` | — | adjoe SDK (playtime ads) |

### B3. Game servers (runtime-decrypted)
The match/auth servers are inside the encrypted `libgame-*.so` (only decrypted in memory on-device).
Known surface from loader regexes and certificates: Miniclip game-service hosts under
`*.miniclippt.com` / `*.miniclip.com` (e.g. the `pool` service family seen with the avatars host).
Precise paths require a runtime dump (Frida hook on `HttpConnection.newConnection` /
`startConnection` prints every URL).

### B4. Ad-SDK traffic (bundled SDKs, not game logic)
AppLovin, Google AdMob/DoubleClick (`googleads.g.doubleclick.net`, `pagead2.googlesyndication.com`),
Unity Ads, ironSource, Tapjoy, Moloco, BidMachine (`host` = `8bp.adx.android`), Kidoz, Mintegral
(mbridge), Facebook Audience Network (`audience_network.dex`), AppsFlyer (`assets/com/appsflyer`).

---

## C. How to trace live (recommended next step)
1. Patch `network_security_config` or use a user CA + magisk module (the mod disables its own TLS
   verify, so `venomkey.com/connect` is trivially interceptable).
2. Frida hooks:
   - `com.miniclip.network.HttpConnection.newConnection(...)` (private static) → log URL/method/headers.
   - `library.so!0x1B5548` (curl wrapper) → log license URL/body/response.
   - `SSL_read`/`SSL_write` exports in the decrypted game lib (dumped from memory) for game protocol.
3. The game lib can be dumped from a rooted device/emulator memory (`/proc/<pid>/maps` region of
   `libgame-*.so`) and re-analyzed statically.
