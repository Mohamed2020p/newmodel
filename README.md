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
- **`large/`** — the original split APK parts (source data, unmodified).

Nothing else is tracked: game assets, decompiled sources, and encrypted mod blobs
(`j1O1pP4cpnaLPxs2xoSf`, fake `.so` files) live only inside `large/app.apk` / the APK itself.
