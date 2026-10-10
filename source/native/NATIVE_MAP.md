# Native code map — `library.so` (mod menu core) and boot chain

All offsets are file/virtual addresses in `lib/arm64-v8a/library.so` (unstripped ELF, 5.0 MB,
arm64-v8a, statically links libcurl + OpenSSL).

## Boot chain (who loads what)
```
EightBallPoolApplication.onCreate()
  └─ loQPY7.ajSoJ9.jLKPr9.<clinit>            (string-decrypt runtime)
       └─ loQPY7.ldv373.oQLNW6.xT9Oe5()
            ├─ System.loadLibrary("rary")      -> library.so   [MOD CORE]
            └─ System.loadLibrary("loader")    -> libloader.so [game unpacker]
cocojava.loadNativeLibrary()
  └─ loQPY7.eNneC6.wMLhN2.nkZ9d4("game")       -> libgame-*.so [encrypted, decrypted in memory]
```

## library.so function map
| Address | Function |
|---|---|
| `0x239E48` | Static-init: decrypts config strings (XOR key `17 05 35 6D 07 D5 33 95` from `.rodata:0xE76E0`); builds URL `https://venomkey.com/connect` (enc at `.rodata:0xE7C20` + `0xE6D08` + imm `0x19566079`,`0x107`) into BSS `0x5028C0`; copies to `0x502770` |
| `0x1B5548` | `http_post(url, body)` — libcurl: `CURLOPT_URL/POST/POSTFIELDS/HTTPHEADER("Content-Type: application/x-www-form-urlencoded")/WRITEFUNCTION/WRITEDATA/TIMEOUT=15/SSL_VERIFYPEER=0/SSL_VERIFYHOST=0`; error prefix `"HTTP request failed: "` |
| `0x1B5400` | POST-value encoder: input std::string → AES/transform (sub-routine `0x2B4908`, finalize `0x2B4D20`) |
| `0x1B57F8` | HWID: `fopen(".hwid","rb")` read, else create; ANDROID_ID via JNI (`0x1A895C`) |
| `0x1B63D8` | `validate_key(key, out)`: body = `"game=" + enc(gameid@0x502788) + "&user_key=" + enc(key) + "&serial=" + enc(hwid)`; logs `Connecting to server:`/`GAME: (PUBG)`/`License Key:`; POST via `0x1B5548`; logs `Server Response:` |
| `0x1B6980` | Empty-response branch (`No response from server`) |
| `0x1B6CC0` | `EXPIRY TIME:` renderer; `0x1B4C80` `Tier %s` |
| `0x1B7884` | Login result: success → store key to `0x5027E8/0x502800/0x5027B8`, flag `0x5027DC/D8`, map key `"key"`; failure → `Key is empty` / `Failed to get Android ID` / `Login failed: <global 0x502830>` |
| `0x1C91AC` | ImGui License window: `License Information`, `Device HWID:`, `Developer: @bum7md8`, `Server: watan-keys.cu.ma`, `Telegram:` |
| `0x1CD6B0`, `0x1D5A20` | Login button UI (`LOGIN (paste key from clipboard)`) |
| `0x233A84` | Duplicate login/validate path (second copy) |

## Global BSS slots (license state)
| Address | Content |
|---|---|
| `0x502770` | Server URL string (`https://venomkey.com/connect`) |
| `0x502788` | Game ID string (POST `game=`) |
| `0x5027B8`, `0x5027E8`, `0x502800` | Stored license key / tier / expiry |
| `0x5027D8/DC` | Logged-in flags |
| `0x502830` | Last error string (`Key is empty`, `Login failed: …`) |
| `0x5028C0` | Decryption-target buffer for URL (guarded by flag at `0x5028DD`/`0x5028E0`) |

## Strings
- `library_own_strings.txt` — all 19,612 non-symbol strings with section+address (module's own data after filtering OpenSSL/curl symbol table).

## Other natives
- `libloader.so` — in-memory crypter/unpacker for the packed `.so` set; JNI registration for the `loQPY7`/`qwerty.asdfgh` obfuscation runtime.
- `libprotect.so` — adjoe anti-tamper (`DeviceUtils_drmInfo/isEmulator/registerToken/socName`).
- `library.so` also exports `Java_com_qiyi_xhook_NativeHandler_*` (PLT hook engine) and `Java_android_service_SurfaceView_{MenuColor,getExpTime,onCanvasDraw,onSendConfig}` (camouflage names; bound via RegisterNatives).
