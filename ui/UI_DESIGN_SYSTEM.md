# UI DESIGN SYSTEM — 8 Ball Pool (modded APK) complete visual breakdown

The UI exists in **three layers**:

1. **Cocos2d-x game UI** (the real game) — binary `.ccbi` CocosBuilder layouts + texture atlases
   (`-hd.png`/`.png` pairs) + `.plist` sprite frames. This is 100% of the in-game menus.
2. **Android/native res** — notification UI, dialogs, splash, icons (`res/`, extracted to
   `assets/apk_res/`).
3. **ImGui mod-menu overlay** (`library.so`) — the floating "Premium 8 Ball Pool Assistant" window,
   rendered with Dear ImGui 1.85 on an OpenGL ES overlay.

---

## 1. Typography (extracted → `assets/fonts/`)

| File | Usage |
|---|---|
| `AdoraPro.ttf`, `AdoraFresh.ttf` | Display/decorative faces (headers, logos) |
| `DroidSans.ttf`, `DroidSans-Bold.ttf` | Base Latin UI font (regular + bold weights) |
| `DroidNaskh-Regular.ttf` | Arabic UI text |
| `DroidSansThai-Regular.ttf` | Thai text |
| `NotoSans-Regular.ttf` | Fallback Latin |
| `NotoSansDevanagari-Regular.ttf`, `NotoSansDevanagariUI-SemiBold.ttf` | Hindi |
| `DroidSansFallback.ttf` | Generic fallback |

Mod menu fonts (loaded from device at runtime, not bundled): `Roboto-Regular`,
`NotoNaskhArabic-Regular`, `NotoNaskhArabicUI-Regular` + ImGui's `ProggyClean.ttf` (debug).

Resolution variants: every texture ships as `Name.png` (SD, 1x) and `Name-hd.png` (2x) per
`resourcePacksConfig.json`:
```
SD  baseScale 1.0          HD  baseScale 2.0 (suffix "-hd")
iPad scale 0.9365          iPhone6 scale … (full table in the json)
```
Design in points; textures loaded at `baseScale * point size`.

## 2. Color system

### 2.1 Android res palette (`res/values/colors.xml` → game-relevant entries)
| Token | HEX |
|---|---|
| `default_notification_color` (accent) | `#FFA000` amber |
| `error_message` | `#C00000` red |
| `countdown_bg` | `#40000000` 25% black |
| `black_transparent` | `#36000000` 21% black |
| `dtx_store_promo_bg_fade` | `#77000000` 47% black |
| `com_smart_login_code` | `#3C6499` facebook blue |
| cardview light/dark | `#FFFFFF` / `#424242` |

### 2.2 Game UI palette
The game's colors live **inside the texture atlases and .ccbi files** (Cocos `ccColor3B`/`ccColor4B`
properties), not in Android resources. Anchor colors sampled from the atlas set (pool table felt,
wood, chrome UI):
- Felt green family, wood browns, gold/amber accents (`#FFA000` family), white text with black
  outlines, blue CTA (`#4285F4` family from ad chrome), red error `#C00000`.
- The ImGui menu uses **ImGui default theme** (`style.json` in `/no_backup` overrides at runtime):
  `WindowBg`, `TitleBg`, `ModalWindowDimBg`, `ScrollbarGrab`, `SliderGrabActive`, `TextSelectedBg`,
  `HeaderHovered`, `CheckMark`, `PlotLinesHovered` etc. — default values: dark gray `#0F0F0F~#W`
  window, `#29A3F5`-style accent when user-styled. Colors settable via
  `Java_android_service_SurfaceView_MenuColor`.

### 2.3 Notification (system) rendering — exact values from code
`NotificationLayoutUtils.java`:
- Level text: 14.0f, fill `#FFFFFF`, stroke `#000000`, stroke-width 1.5f
- Name text: 21.0f, fill `#FFFFFF`, stroke `#FF0F7D`-family (`-16773971` = `#FF0F7D` in ARGB int),
  width 1.5f
- Play button label: 20.0f, fill `#FFFFFF`, stroke `-15367423` (`#FF1559` family `#159499`… computed
  int `FF159499`), width 1.5f
- Avatar corners: rounded radius **25 px**

## 3. Layout system

### 3.1 CocosBuilder (.ccbi) — 643 layouts (extracted → `assets/ccbi_layouts/`)
- Magic `ibcc`, binary key/value nodes: position, contentSize, anchorPoint, color,
  cascadeColor/Opacity, layoutType (horizontal/vertical), paddings (`leftPadding`,
  `rightPadding`, `bottomPadding`, `xSpacing`, `ySpacing`), `priorityAxis`,
  `horizontalResizeType`/`verticalResizeType`, `minimumSize`, sprites reference atlas paths like
  `HGameAssets/RTPGenerated/Atlas/Bundled/UI/generic/Generic_Apple_White.png`.
- Widget classes: `MCLayout`, `CCNodeRGBA`, `CCSprite`, `CCLabelBMFont`, `CCControlButton`,
  `ActionButtonWithCustomImage`, `ActionButtonLoaderLayout` — Miniclip's extended Cocos GUI kit.
- Screen inventory → `ui/SCREEN_INVENTORY.md`.

### 3.2 Grid/spacing conventions observed in .ccbi
- Buttons cluster in vertical stacks with uniform `ySpacing`, edge padding via `leftPadding/rightPadding`.
- Popups: `...Popup.ccbi` full-screen dim + centered modal; menus: `Menu*.ccbi` docks.
- Scale tiers: SD 1x / HD 2x / iPad 0.9365x — same layout, swapped textures.

### 3.3 ImGui overlay (mod menu)
- Main menu bar `##MainMenuBar` + collapsible `##Menu_%02d` sections; `#CLOSE` button;
  `##ContextMenu` right-click menu; modal confirmations (`Approve the shot?`) with
  `ModalWindowDimBg`; color pickers (`##picker`, `##hex`, R/G/B/A bars `R:%3d G:%3d B:%0.3f`);
  tables (`SettingsTables`, `Size all columns to fit###SizeAll`); tooltips; FPS overlay
  (`Application average %.3f ms/frame (%.1f FPS)`).
- Inputs: sliders/checkboxes for all `b*`/`f*`/`i*`/`c*` settings (bool/float/int/color naming scheme).

## 4. Effects & animation pipeline
- **Spine-style skeletal anims**: `.skel` + `.atlas` (+ `.png`) — 20 rigs (Daily Spin wheel/lights,
  Champion Medals, Avatar Frames, Cue Mastery, prize glows…).
- **Cocos particle systems**: `.plist` emitters (box explosions per rarity: Rare/Epic/Legendary,
  sparkles, smoke, background particles).
- **Custom GLSL shaders** (`assets/animations/*.fsh`): `purchasePopupShader.fsh`,
  `verticalCutOffShader.fsh`.
- **Curve animations**: `.json` (Cocos action/curve exports) — 27 files.
- Audio: 142 UI/game SFX (`.mp3`, `assets/audio/`).

## 5. Redesign guide (how to modify safely)
1. **Textures/icons**: edit `assets/game_ui/*.png` (keep `-hd` pair, keep sprite-frame regions
   matching the `.plist` atlas definitions; plist frames are in `assets/unpack` coordinates).
2. **Layouts**: `assets/ccbi_layouts/*.ccbi` are binary CocosBuilder files — edit with CocosBuilder/
   Cocos Studio or the `ccbi` text converters; node names must stay (game looks them up by name).
3. **Fonts**: drop-in replacement TTFs with same filenames; `strings` files define BMFont glyph sets.
4. **Colors (Android layer)**: `res/values/colors.xml`; notification text paints are hardcoded in
   `NotificationLayoutUtils` (see §2.3).
5. **Mod menu look**: `/no_backup/style.json` on device overrides ImGui style (colors/radius/spacing);
   `MenuColor` JNI entry sets the accent; all menu strings are compiled into `library.so`.
6. **Localization**: `assets/localization/*.strings` (17 languages) — plain UTF-16 key-value.
