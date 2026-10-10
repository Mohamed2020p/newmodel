# License API contract — venomkey.com/connect
Reconstructed from the mod's own parser (lib/arm64-v8a/library.so). This is what the
key server must return for the mod menu to accept a key.

## Request
POST https://venomkey.com/connect
Content-Type: application/x-www-form-urlencoded (implied)
Timeout 15 s, TLS peer/host verification DISABLED (CURLOPT_SSL_VERIFYPEER=0, VERIFYHOST=0).

Body (all three values Base64-encoded, standard alphabet, no auth header):
    game=<b64(gameId)>&user_key=<b64(license key)>&serial=<b64(ANDROID_ID HWID)>

- gameId / endpoint URL / "exp" handling: ciphertext at .rodata 0xE7C20 (16B) + 0xE6D08 (8B)
  + imm32 0x19566079 + imm16 0x0107, XOR-decrypted with the 8-byte key
  17 05 35 6D 07 D5 33 95 (@0xE76E0) -> global 0x502770 (url) / 0x502788 (gameId).
- The UI cosmetically shows "Server: watan-keys.cu.ma" (see strings), the real POST
  target built at runtime is venomkey.com/connect.
- HWID = android.provider.Settings.Secure.ANDROID_ID, cached in /data/.../.hwid.

## Valid-key response (parser: 0x1B6830-0x1B6D40 + helper 0x1B5E34)
1. Success sentinel: body must CONTAIN the substring `"status":true`
   (the parser also tolerates `"status": true` — both literals are present @0xEF0C5 / 0xE94A0).
2. Fields then extracted by scanning for quoted keys (keys built inline, SSO-immediates):
   - `"token"` (key @0xF1F9D)  -> session token string, stored in global 0x5027B8 region
   - `"data"`  (6-byte xor-compare `22 64 61 74 61 22` @0x1B6DB8) -> payload string
   - `"exp"`   (3-char key built @0x1B6C20) -> expiry value; rendered as `EXPIRY TIME: <exp>`
     (@0x1B6CC4) and fed to the Java fake class `getExpTime` for display/formatting
   - `"reason"` (@0xEF571) -> error/failure message shown on the login window
   - `"subtype"` (@0xEFD21 / xref 0x218E94) -> key tier/subscription subtype; shown as `Tier %s`
3. The response is also parsed as JSON (nlohmann::json — `[json.exception.` strings present),
   so values must be properly quoted JSON strings.

### Expected success shape
```json
{
  "status": true,
  "token":  "<session token>",
  "data":   "<license data payload>",
  "exp":    "<expiry: date string or epoch>",
  "subtype":"<tier name>",
  "reason": ""
}
```

### Failure shapes
- Missing/absent sentinel -> login fails: "Login failed: " + reason, or
  "No response from server" when the HTTP body is empty.
- JSON/parse failures surface as `Unknown error type` / `Unknown error %d`.
- Key empty -> "Key is empty". HWID failure -> "Failed to get Android ID".

## Live endpoints observed (2026-10-10)
- https://venomkey.com/ — "VENOM VIP PANEL" login page, Cloudflare Turnstile protected,
  free registration, key bot @venompanelbot (Telegram), updates @venomfreepanel.
  GET /connect -> HTTP 500 (endpoint is POST-only; panel is PHP).
- https://watan-keys.cu.ma/ — "3BS WATAN PANEL", same panel family (@HIMA35bot),
  only displayed cosmetically inside the mod UI.
