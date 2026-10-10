# ENCRYPTED ASSETS & PROTECTIONS — status after static analysis

## 1. Reassembled APK
- `large/app.apk.part01` + `large/app.apk.part02` → `app.apk` (129,767,025 bytes)
- SHA-256 `13e4a72efb336c40df1d69fe56254ea2b6dd4eec16a50fac9ccc5d56bd1bf5da` — **matches** `app_README.txt`.
- Zip check: 5,489 entries, no CRC errors.

## 2. Encrypted native libraries (`lib/arm64-v8a/`)
| Library | Header | Status |
|---|---|---|
| `libgame-BPM-GooglePlay-Gold-Release-Module-3902.so` (81 MB) | `42 bc dd 4d ...` | **Encrypted** — custom crypter, decrypted in-memory by `libloader.so` on device |
| `libanybrainSDK.so`, `libapminsighta/b.so`, `libbuffer_pgl.so`, `libfile_lock_pgl.so`, `libgdtflex.so`, `libgdtqjs.so`, `libnms.so`, `libpglarmor.so`, `libtapjoy.so`, `libtt_ugen_layout.so`, `libdatastore_shared_counter.so` | various non-ELF | **Encrypted** (same crypter family) |
| `library.so`, `libloader.so`, `libprotect.so`, `libc++_shared.so`, `libcrashlytics*.so`, `libapplovin-native-crash-reporter.so`, `libtensorflowlite_jni_gms_client.so` | `7f 45 4c 46` | Valid ELF — analyzed |

`libloader.so` (6.3 MB) is the in-memory unpacker: it locates, decrypts and maps the packed
libraries, then registers natives (`qwerty.asdfgh.zxcvbn.lkjhgf()` triggers registration;
`loQPY7.ldv373.ouc9R2` is the JNI bridge). ChaCha20 constants (`expand 32-byte k`) are present in
both `libloader.so` and `library.so`.

## 3. Mod menu payload container (`assets/j1O1pP4cpnaLPxs2xoSf/`)
65 files (64 UUID names + `uTPEauDexK34zwVRiCRp`), 417,449 bytes total.

Header (60 bytes, little-endian):
```
+0x00  u32  magic  = 0x10FF1CE1
+0x04  u32  version= 1
+0x08  u32  0x3C   (header/data offset marker)
+0x0C  u32  2,  u32 0,  u32 0
+0x18  u32  2,  u32 16, u32 <random>
+0x20  16 B      random (IV / salt)
+0x30  u32  3,  u32 <len-related>, u32 <len-related>
+0x3C  payload (high entropy)
```
- Structure indicates an AES-family container (16-byte IV field, block-aligned payloads).
- The decryption key/derivation lives inside the packed runtime → **files remain encrypted
  statically**. They are the mod menu's private data (configs/images), loaded at runtime.

## 4. Encrypted overlay libs (`assets/a0rjgdfbjd8fhfglkew6/2de1a92e00ace60a/<abi>/`)
13 small files (75 B – 67 KB) mirroring the names of the packed `lib/` set — encrypted staging
copies used by the mod loader (per-build folder name `2de1a92e00ace60a`).

## 5. Game config plists (Miniclip layer)
- `GameConfiguration.plist` (4.0 MB), `EnvironmentsConfiguration.plist`, `FeaturesSystemConfiguration.plist`,
  `ExtraButtons/Inbox/Interstitial/Promotion/Promo/AppsflyerConfig .plist` — **encrypted** with the
  game's own scheme (identical MD5 for several = identical placeholder content).
- `deviceCategoriesConfig.plist` is a plain binary plist (`bplist00`) — readable.
- Particle plists are standard binary plists (`bplist00`) — readable by `plistlib`.

## 6. Java-layer obfuscation (original app)
- String vault: `loQPY7.ajSoJ9.jLKPr9.hY1Z05("...")` — custom alphabet/base-N frames decrypted by
  natives registered from `libloader.so` (`ouc9R2.uGifl7()` supplies the parameters at runtime).
- Control-flow flattening wrapper classes (`lPs483`, per-call lambdas).
- Boot chain: `EightBallPoolApplication` → `jLKPr9.<clinit>` → `oQLNW6.xT9Oe5()` →
  `loadLibrary("rary")` (mod) → `loadLibrary("loader")` (unpacker) → `wMLhN2.nkZ9d4("game")` →
  encrypted game lib load.

## 7. Mod menu string obfuscation — **CRACKED**
See `analysis/MOD_MENU_ANALYSIS.md` §4: XOR key `17 05 35 6D 07 D5 33 95`,
endpoint decrypted to `https://venomkey.com/connect`.
