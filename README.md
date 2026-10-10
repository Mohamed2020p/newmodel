# 8 Ball Pool Mod Menu — RE Workspace

Reverse-engineering workspace for the **8 Ball Pool 5.14.5** mod menu
(**“MAJISK v2.0 — Gold Edition”** / “Premium 8 Ball Pool Assistant”, vendor Telegram **@bum7md8**).

The split APK parts in `large/` are joined into `large/app.apk` (gitignored, 123 MB) and verified.

## Contents

- **`analysis/MOD_MENU_REPORT.md`** — the full report: identity, injection chain, license
  protocol, UI, complete color system, features, key offset index.
- **`assets/mod_menu/`** — everything extracted from the mod:
  - `logo_8ball_57x57.png` — menu logo carved from `library.so`
  - `mask_white_69x69.png` — 69×69 luminance-alpha glow mask
  - `palette.json` — all colors (ImGui theme, Gold Edition brand, ESP defaults, picker swatches)
  - `license_api_contract.md` — venomkey.com/connect request + valid-key response contract
  - `strings_mod_menu.txt` — 1,381 decoded mod strings
  - `encrypted_payload/` + `encrypted_overlay_libs/` — still-encrypted container assets
- **`large/`** — original split APK parts (source of truth, unmodified).

The original APK remains untouched; all game assets (Miniclip side) were intentionally removed
from the repo to keep it mod-menu-only.
