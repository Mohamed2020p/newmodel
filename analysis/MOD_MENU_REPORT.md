# 8 Ball Pool Mod Menu — Full Reverse-Engineering Report

**Target:** `8bp_5.14.5_stable_release.apk` (split APKs joined from `large/`)
**Mod core:** `lib/arm64-v8a/library.so` (the menu itself — everything below is this library)
**Identity:** **“MAJISK v2.0 — Gold Edition”** (@0x1C9834) · a.k.a. **“Premium 8 Ball Pool Assistant”** (@0x1C9864)
**Vendor:** Telegram **@bum7md8** (rebranded PUBG-mod template: leftover strings “GAME: (PUBG)”, “AutoAim Enhanced”)
**Status:** fully mapped — injection chain, license protocol, UI, complete color system, assets.

> All addresses are file virtual addresses inside `library.so` (arm64-v8a), PIE base 0.

---

## 1. What ships inside the APK

| Component | Path | Role |
|---|---|---|
| Mod core (ImGui overlay) | `lib/arm64-v8a/library.so` | the menu, license client, ESP/aimbot logic |
| Boot stub | `classes*.dex` → `oQLNW6.xT9Oe5` | `System.loadLibrary("rary")` then `("loader")` |
| Java string vault | class `jLKPr9.hY1Z05` (+ native tables `ouc9R2`) | obfuscated strings |
| Encrypted payload container | `assets/j1O1pP4cpnaLPxs2xoSf` | 65 files, header `[0x10FF1CE1,1,60,2,…]`, still encrypted (ChaCha-constant footprint is the only cipher trace) |
| Fake “.so” blobs | `assets/a0rjgdfbjd8fhfglkew6/2de1a92e00ace60a/<abi>/` (13 files) | non-ELF encrypted modules for the same container format |
| JNI fake class | `android.service.SurfaceView` | natives `MenuColor`, `getExpTime`, `onCanvasDraw`, `onSendConfig` |
| Hooking engines | qiyi **xHook** + **Dobby** `A64HookFunctionV` + custom fake-dlopen | PLT & inline hooking, hidden library loading |

Boot order: dex stub → `loadLibrary("rary")` (suffix trick → `library.so`) → `loadLibrary("loader")`
(`libloader.so`, loads/decrypts remaining modules, checks JNI package name in `JNI_OnLoad`).
The menu self-verifies its own presence by scanning `/proc/self/maps` for itself (“Wolf founded”
check for `libwolf.so`, root/anti-detection regexes from the PUBG template).

---

## 2. License system (venomkey.com)

### 2.1 Key format
`bum7md7-12-gnMmz` — `<prefix>-<duration/plan>-<suffix>`; entered in the **License** window.

### 2.2 HWID
`Settings.Secure.ANDROID_ID` (JNI), cached to `.hwid`; shown in UI as `Device HWID: …`.

### 2.3 Request
```
POST https://venomkey.com/connect
game=<base64(gameId)>&user_key=<base64(key)>&serial=<base64(ANDROID_ID)>
```
- URL + gameId are **XOR-encrypted at rest** (key `17 05 35 6D 07 D5 33 95` @0xE76E0;
  ciphertext 0xE7C20/0xE6D08 + immediates) → globals 0x502770 / 0x502788.
- base64 helper 0x1B5400, HTTP helper 0x1B5548 (timeout 15 s, `SSL_VERIFYPEER/HOST = 0`).
- The UI **cosmetically displays** `Server: watan-keys.cu.ma`; the runtime POST goes to
  **venomkey.com/connect** (live: GET returns HTTP 500 — POST-only PHP endpoint behind Cloudflare Turnstile).

### 2.4 Valid-key response — exact contract
Success **sentinel**: body contains `"status":true` (or `"status": true`). Then, by quoted-key scan:

| Field | Meaning in the mod |
|---|---|
| `token` | session token → stored (global 0x5027B8 region); “Login successful.” |
| `data` | license payload string (searched by XOR-compare of literal `"data"`) |
| `exp` | expiry → rendered `EXPIRY TIME: <exp>` (0x1B6CC4), passed to Java `getExpTime` |
| `subtype` | tier name → shown as `Tier %s` in the License Information panel |
| `reason` | failure/error message shown on the login window |

```json
{ "status": true, "token": "…", "data": "…", "exp": "…", "subtype": "…", "reason": "" }
```
Failures: empty body → “No response from server”; empty key → “Key is empty”;
missing HWID → “Failed to get Android ID”; bad JSON → “Unknown error type” / “Unknown error %d”.
Full details: [`assets/mod_menu/license_api_contract.md`](../assets/mod_menu/license_api_contract.md).
Logs: `Connecting to server:`, `License Key:`, `Server Response:`, `Login failed: `, `Login successful.`
Globals: err 0x502830 · url 0x502770 · gameId 0x502788 · key/tier/expiry 0x5027B8/E8/800 · flags 0x5027D8/DC.
Key parser functions: validator 0x1B63D8 · parse helper 0x1B5E34 (×2 call sites) · success handler 0x1B7518.

---

## 3. UI structure (ImGui 1.85, OpenGL ES 3 overlay)

- **Windows / tabs** (`##Menu_%02d`): **Table · Account · General · Auto Play · Notifications · About**
  + separate **Login** window, **License Information** panel (@0x1C91AC), `##ContextMenu` (aim-mode picker),
  FPS/status overlay, modals (`#CLOSE`, “Approve the shot?”).
- **Main function 0x1C0B28** (~25k instructions): every window, tab, game logic, persistence, shader.
- **Fonts:** ImGui `ProggyClean.ttf 13px` default + `Roboto-Regular.ttf`; **Arabic UI support** via
  device fonts `/system/fonts/NotoNaskhArabicUI-Regular.ttf` & `/system/fonts/NotoNaskhArabic-Regular.ttf`.
  Font-size ramp used by layout code: **12/13/14/18/21/22/28/30 px** (cluster fn 0x21A438).
- **Geometry:** `SetNextWindowSize/Pos` floats in 0x21A438 — paddings 2/2.5/4/4.5/6.5,
  window rounding 4–8, `Style.WindowPadding≈8×8`, item spacing ≈ 4–6.5.
- **Custom shader** embedded at 0x1C8894: `uniform sampler2D Texture; …` (overlay rendering).
- **Menu logo:** 8-ball sprite 57×57 RGBA (extracted → `assets/mod_menu/logo_8ball_57x57.png`),
  plus a 69×69 luminance-alpha white mask (`mask_white_69x69.png`) used as glow/backdrop.
- On-disk state: `/no_backup/persistence.json` (settings map incl. `key`),
  `/no_backup/style.json` (theme overrides), `imgui_log.txt`.
- Languages: English / Arabic toggle (`iLang`, “Language”, font swap to Naskh).

---

## 4. Color system — complete

Data files: **`assets/mod_menu/palette.json`** (machine-readable) · `assets/mod_menu/strings_mod_menu.txt`.
Colors live as `.rodata` float4 literals loaded with `ldr qN` (no immediates). Five clusters:

### 4.1 Global ImGui theme — fn 0x23D0D0 (applied to `ImGuiStyle.Colors`)
Base: **ImGui Dark** kept almost stock, blue `#4296FA` accent family:

| Role | Hex | | Role | Hex |
|---|---|---|---|---|
| Text | `#FFFFFFFF` | | CheckMark | `#4296FAFF` |
| TextDisabled | `#808080FF` | | SliderGrab | `#3D85E0FF` |
| WindowBg | `#0F0F0FF0` | | Button | `#4296FA66` |
| ChildBg | `#00000000` | | ButtonHovered | `#4296FAFF` |
| PopupBg | `#141414F0` | | ButtonActive | `#0F87FAFF` |
| Border | `#6E6E8080` | | Header | `#4296FA33` |
| FrameBg | `#294A7A8A` | | HeaderHovered | `#4296FACC` |
| FrameBgHovered | `#4296FA66` | | HeaderActive | `#4296FAFF` |
| FrameBgActive | `#4296FAAB` | | Separator | `#3B3B40FF` |
| TitleBg | `#0A0A0AFF` | | SeparatorHovered | `#1A66BFC7` |
| TitleBgActive | `#294A7AFF` | | TitleBgCollapsed | `#00000082` |
| MenuBarBg | `#242424FF` | | ScrollbarBg | `#05050587` |
| ScrollbarGrab | `#4F4F4FFF` / hover `#696969FF` / active `#828282FF` | | | |

### 4.2 “Gold Edition” brand palette (login + License Information windows)
Fns 0x1BAF00–0x1BB010, 0x1C8C00–0x1CA620, 0x1CD400–0x1D8100:

- Golds: `#F2D15CFF` (headings, 6 uses) · `#C79E3DFF` · `#D4AD4AFF` · `#A07828FF` · `#8C6B29FF`
- Navy backgrounds: `#080D17F5` (main overlay window) · `#0D1421FA` · `#0F1A2EFF` · `#33425CFF`
- Silver-blue text: `#B2BFD9FF` · `#9EA8BFFF`
- Reds (logout/error/fail): `#FF6666FF` · `#BF4040FF` · `#D95959FF` · `#9E2E2EFF` · `#C73D3DFF` · `#732121FF` · `#B23838FF` · `#8C2E2EFF`

### 4.3 ESP / Aimbot overlay defaults (settings init 0x239E48–0x23A260)
Registered right beside their setting names (`cESP_*`, `cAIM_*`):

| Setting | Default |
|---|---|
| `cESP_Line` etc. | `#00FF00FF` green |
| `cAIM_SnapLine` | `#FF0000FF` red |
| `cAIM_SnapLineBot` | `#FFFF00FF` yellow |
| `cESP_Distance` | `#FFA500FF` orange |
| 4th slot | `#FFC0CBFF` pink |
| accents | `#00FFFFFF` cyan · `#000000FF` black · `#008080FF` teal · `#800080FF` purple · `#008000FF` green |

### 4.4 ColorEdit custom swatches (color pickers, fn 0x2A8430–0x2A8620)
~37 preset swatches: whites `#FFFFFFD9/#FFFFFF99/#FFFFFFB2/#F0F0F0FF/#DBDBDBFF/#ADADBDFF`, grays
`#9C9C9C/#808080/#636363/#4F4F4F/#37373C/#2D2D2D/#282828/#242424/#1C1C1C/#181818/#101010/#0D0D1A99`,
golds `#F2D15C/#E5B200/#FF9900/#B29E4D4D/#A67300/#8C828F8F`, greens `#00D400/#00DE6666/#05CC3340/#8FFF8F8F/#2E3800FF`,
cyans/teals `#03FFE5CC/#8FE8E5E5/#4D998C87`, reds `#D95959/#C73D3D`, orange `#FF7300`, lavender `#E5D4B2B2`, violet `#CC66E5A6`.

### 4.5 Secondary theme cluster (fn 0x266B20–0x266F70)
Alternate/legacy violet-blue skin: `#6666CC`/`#758ACC`/`#59669C`/`#2470CC`/`#B2B2E5`/`#C7D1FF`/`#FF7300` alpha variants —
the pre-Gold palette retained in the binary.

---

## 5. Features (settings keys persisted in persistence.json)

| Group | Keys |
|---|---|
| Auto Play | `bAutoPlay`, `iAutoPlaySpeed`, `iAutoPlayStyle`, `fShotPower`, `fShotDelay`, `fSpeed`, `fHeight`, “Enable Auto Queue”, “Queue selection mode” |
| Table | `fTableBottom`, `fTableRight`, AI-learn (“AI Learn: recorded failure ball=%d pocket=%d angle=%.3f power=%.1f”) |
| Aimbot | `fAIM_AngleStep`, `fAIM_MaxSnapPerFrame`, `fAIM_NoRecoilMultiplier`, `fAIM_SnapLineThickness`, `cAIM_SnapLine`, `cAIM_SnapLineBot` |
| ESP | `fESP_HiddenAlpha`, `fESP_SkeletonThickness`, `fESP_Line`, `fESP_Distance`, `fESP_HealthHidden`, `fESP_NameHidden`, `fESP_SkeletonHidden`, `fESP_DrawPockets`, `cESP_*`, `iLineStyle`, `iXray` |
| Debug | `bEnableDebug`, `fBulletTrack_Probability` |
| Hunted game classes | `GameManager`, `CCDirector`, `MainManager`, `MenuManager`, `StateManager`, `VisualCue` |
| Account | auto-bet: “Selected mode %s with bet %lld (50%% of %lld coins)” |

Notifications tab polls a menu-notifications feed (parsed with the same `],"subtype":` scanner @0x1C86C4);
About tab: “Developer: @bum7md8”, “Contact @bum7md8 on Telegram”.

---

## 6. Extracted assets

| File | What it is |
|---|---|
| `assets/mod_menu/logo_8ball_57x57.png` | menu logo (8-ball), carved from library.so @0x4BD364 |
| `assets/mod_menu/mask_white_69x69.png` | 69×69 luminance-alpha white mask (glow/backdrop) |
| `assets/mod_menu/palette.json` | complete color system (5 clusters, machine-readable) |
| `assets/mod_menu/license_api_contract.md` | key-server request/response contract |
| `assets/mod_menu/strings_mod_menu.txt` | 1,381 decoded mod strings (`.rodata` 0xE9000–0xF9FFF) |
| `assets/mod_menu/encrypted_payload/` | `j1O1pP4cpnaLPxs2xoSf` — 65 files, container magic 0x10FF1CE1, encrypted |
| `assets/mod_menu/encrypted_overlay_libs/` | the 13 fake `.so` blobs, encrypted |

The 4.0 MB data blob at file offset 0xE7048 contains a duplicate of the logo + ~4 MB of float
tables (style/vertex/font data); not a separate image.

---

## 7. Anti-analysis notes

- Strings: `.rodata` chunks + `movz/movk` immediates assembled in `.bss` behind `__cxa_guard`
  blocks; XOR key `17 05 35 6D 07 D5 33 95`; decrypt stub 0x23B9C8.
- Java strings hidden in vault class `jLKPr9.hY1Z05`; natives in `ouc9R2` tables.
- Fake-dlopen + xHook + Dobby; self-check via `/proc/self/maps` regexes (root + own-lib presence).
- No AES/SHA crypto constants in the mod ELFs; only ChaCha constant → payload container cipher.

## 8. Key offset index

| Item | Address |
|---|---|
| Main UI fn | 0x1C0B28 |
| base64 / HTTP / parse-helper / success handler | 0x1B5400 / 0x1B5548 / 0x1B5E34 / 0x1B7518 |
| XOR decrypt stub | 0x23B9C8 |
| Settings init (colors+defaults) | 0x239E48–0x23A260 |
| ImGui style fn | 0x23D0D0 |
| ColorEdit swatches fn | 0x2A8430–0x2A8620 |
| Alternate theme fn | 0x266B20–0x266F70 |
| Layout/geometry cluster | 0x21A438 |
| License globals | 0x502770–0x502830 |
| Config map | 0x4CF8C8 |
| “MAJISK v2.0 — Gold Edition” | 0x1C9834 |
| Embedded shader | 0x1C8894 |
