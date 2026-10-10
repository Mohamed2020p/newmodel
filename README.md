# newmodel — 8 Ball Pool (modded) reverse-engineering workspace

Reassembled and analyzed `com.miniclip.eightballpool` **v56.31.1 (4039)** from `large/app.apk.part01+02`
(SHA-256 verified: `13e4a72e…bf5da`), including its injected mod menu **“Premium 8 Ball Pool Assistant”**.

## Layout
| Path | Contents |
|---|---|
| `large/` | original split APK parts + reassembly README (untouched) |
| `assets/game_ui/` | 1,228 game UI textures/atlases (SD+HD PNG) |
| `assets/fonts/` | 10 TTFs (Droid/Adora/Noto families) |
| `assets/ccbi_layouts/` | 643 CocosBuilder binary UI layouts (all screens) |
| `assets/animations/` | Spine `.skel/.atlas`, curve `.json`, GLSL `.fsh` |
| `assets/audio/` | 142 UI/game SFX (MP3) |
| `assets/localization/` | 17 language string tables |
| `assets/mod_menu/` | mod's encrypted payload (65 files, magic `0x10FF1CE1`) + encrypted overlay libs |
| `assets/apk_res/` | Android res drawables/icons |
| `analysis/` | MOD_MENU_ANALYSIS · NETWORK_FLOW · ENCRYPTED_ASSETS |
| `ui/` | UI_DESIGN_SYSTEM · SCREEN_INVENTORY |
| `source/java/` | key recovered Java sources (network, crypto, loaders, notification UI) |
| `source/native/` | native function/global map + full string dump |
| `ASSET_MANIFEST.md` | every extracted asset: file, format, size, dimensions, screen |

## Headline findings
- **License backend (decrypted):** `POST https://venomkey.com/connect` — body
  `game=<enc>&user_key=<enc>&serial=<enc HWID>`; success = `"status":true`; UI displays reseller host
  `watan-keys.cu.ma`; vendor Telegram `@bum7md8`. Key format `<tag>-<days>-<random>` (e.g. `bum7md7-12-gnMmz`).
- Mod string encryption cracked: XOR key `17 05 35 6D 07 D5 33 95`.
- Mod menu = Dear ImGui 1.85 overlay in `library.so` (aimbot/ESP/AutoPlay AI/Auto Queue).
- Original game code (`libgame-*.so`, 81 MB) + 12 third-party libs remain **runtime-encrypted**
  (unpacked in memory only by `libloader.so` on-device).
- Game endpoints recoverable statically: avatars CDN `avatars-live.pool.miniclippt.com`, Firebase
  project `api-project-697261581904`; all match/auth endpoints live inside the encrypted game lib
  (transport: Cronet HTTP/3 via `com.miniclip.network.HttpConnection`).

## Reproduce / continue
- Decompiled trees (not committed): jadx → `sources/` (52,835 java files), apktool → `res/`, `smali/`, assets.
- Full asset library incl. encrypted config plists stays in the APK; only UI-relevant assets are committed here (see `ASSET_MANIFEST.md`).
