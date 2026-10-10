#!/usr/bin/env python3
"""
patch_url.py — point the mod menu's license check at YOUR website.

The app's real endpoint is XOR-encrypted inside library.so (key:
17 05 35 6D 07 D5 33 95). Stock value: https://venomkey.com/connect (28 chars).

This script re-encrypts YOUR url and patches all 4 pieces:

  piece                    file offset in library.so   stock bytes (hex)
  ---------------------------------------------------------------------
  url chars  0-15          0x00E7C20                   7f71411d74ef1cba61605b026abe56ec
  url chars 16-23          0x00E6D08                   39665a0028b65cfb
  url chars 24-27 (imm)    0x023C064 + 0x023C074       movz/movk w10 (2a0f8c52 / ca2aa372)
  url chars 28-29 (NULs)   0x023C078                   mov w8, #0x107 (e8208052)

Usage:
  python3 patch_url.py library.so https://xxxxx.rf.gd/connect
  python3 patch_url.py library.so --show            # show current values only
  python3 patch_url.py library.so <url> --label mydomain --brand free4us

Rules:
  - max 29 characters (https:// adds 1 char over http://, so https URLs
    effectively max at 28). The app disables TLS verification anyway,
    plain http works fine.
    Example:  http://domi74.page.gd/connect   (29 chars, fits exactly)
  - patch the SAME library.so that is inside your APK
    (lib/arm64-v8a/library.so — extract it, patch it, put it back, re-sign).
  - also optional: --label changes the cosmetic "Server:" text shown in the
    login window (stock: watan-keys.cu.ma, max 16 chars).
  - also optional: --brand NAME rebrands the vendor handle in the menu
    (5 places: contact button, developer credits x2, key hint, about).
    The handle slot is 8 bytes ("@bum7md8"), so NAME must be <= 7 chars.
    '@free4us' fits EXACTLY (8 chars) and keeps every sentence identical
    in length - recommended. Keys on the panel should use the same prefix.
"""
import sys

XKEY = bytes([0x17, 0x05, 0x35, 0x6D, 0x07, 0xD5, 0x33, 0x95])
MAX_URL = 29   # 30-byte slot minus the mandatory NUL terminator
MAX_LABEL = 16

# (file_offset, stock_hex, what)
RODATA_PIECES = [
    (0x00E7C20, "7f71411d74ef1cba61605b026abe56ec", "url chars 0-15"),
    (0x00E6D08, "39665a0028b65cfb",               "url chars 16-23"),
]
IMM32_MOVZ = (0x023C064, 0x52800000, 10)   # movz w10, #imm16      (url chars 24-27 low)
IMM32_MOVK = (0x023C074, 0x72800000, 10)   # movk w10, #imm16,lsl16(url chars 24-27 high)
IMM16_TERM = (0x023C078, 0x52800000, 8)    # mov  w8,  #imm16      (NUL terminator)
STOCK_URL = "https://venomkey.com/connect"

LABEL_OFF = 0x00E953E
STOCK_LABEL = "watan-keys.cu.ma"

VENDOR_HANDLE = b"@bum7md8"   # 5 occurrences in .rodata, always 8 bytes
BRAND_SLOT = 8                # bytes available for "@brand"


def xor_enc(plain: bytes) -> bytes:
    return bytes(b ^ XKEY[i % 8] for i, b in enumerate(plain))


def enc_movz(reg: int, imm16: int) -> int:
    return 0x52800000 | (imm16 << 5) | reg


def enc_movk(reg: int, imm16: int) -> int:
    return 0x72800000 | (imm16 << 5) | reg


def main() -> None:
    if len(sys.argv) < 3:
        print(__doc__)
        sys.exit(1)
    path = sys.argv[1]
    arg = sys.argv[2]

    data = bytearray(open(path, "rb").read())

    if arg == "--show":
        p1 = bytes(b ^ XKEY[i % 8] for i, b in enumerate(data[0x00E7C20:0x00E7C20 + 16]))
        p2 = bytes(b ^ XKEY[(16 + i) % 8] for i, b in enumerate(data[0x00E6D08:0x00E6D08 + 8]))
        ia = int.from_bytes(data[IMM32_MOVZ[0]:IMM32_MOVZ[0] + 4], "little")
        ib = int.from_bytes(data[IMM32_MOVK[0]:IMM32_MOVK[0] + 4], "little")
        tail = bytes(b ^ XKEY[(24 + i) % 8] for i, b in enumerate(
            bytes([(ia >> 5) & 0xFF, ((ia >> 5) >> 8) & 0xFF, (ib >> 5) & 0xFF, ((ib >> 5) >> 8) & 0xFF])))
        it = int.from_bytes(data[IMM16_TERM[0]:IMM16_TERM[0] + 4], "little")
        tail += bytes(b ^ XKEY[(28 + i) % 8] for i, b in enumerate(
            bytes([(it >> 5) & 0xFF, ((it >> 5) >> 8) & 0xFF])))
        label = data[LABEL_OFF:LABEL_OFF + MAX_LABEL + 2].split(b"\x00")[0]
        print("current endpoint:", (p1 + p2 + tail).split(b"\x00")[0].decode(errors="replace"))
        print("cosmetic Server: label:", label.decode(errors="replace"))
        sys.exit(0)

    url = arg.strip()
    label = None
    if "--label" in sys.argv:
        label = sys.argv[sys.argv.index("--label") + 1]
    brand = None
    if "--brand" in sys.argv:
        brand = sys.argv[sys.argv.index("--brand") + 1]
        if len(brand) + 1 > BRAND_SLOT:
            print(f"ERROR: brand '@{brand}' is {len(brand)+1} chars, the slot holds {BRAND_SLOT}. Use a name of {BRAND_SLOT-1} chars or less (e.g. free4us).")
            sys.exit(1)

    if len(url) > MAX_URL:
        print(f"ERROR: url is {len(url)} chars, max is {MAX_URL}.")
        print("Tip: use http:// instead of https:// (the app does not verify TLS anyway).")
        sys.exit(1)
    if label and len(label) > MAX_LABEL:
        print(f"ERROR: label is {len(label)} chars, max is {MAX_LABEL}.")
        sys.exit(1)

    # sanity: file must currently contain the stock bytes
    for off, stock, what in RODATA_PIECES:
        if data[off:off + len(stock) // 2].hex() != stock:
            print(f"ERROR: stock bytes for {what} not found at 0x{off:X} — wrong library.so?")
            sys.exit(1)
    if data[LABEL_OFF:LABEL_OFF + len(STOCK_LABEL)] != STOCK_LABEL.encode():
        print("note: cosmetic label already patched or missing (continuing).")

    # build the 30-byte padded plaintext: url + NULs
    plain = url.encode() + b"\x00" * (30 - len(url))
    enc = xor_enc(plain)

    changes = []  # (offset, oldhex, newhex, what)

    # piece 1: chars 0-15
    changes.append((0x00E7C20, enc[0:16], "url chars 0-15"))
    # piece 2: chars 16-23
    changes.append((0x00E6D08, enc[16:24], "url chars 16-23"))
    # piece 3: chars 24-27 as two instruction immediates (w10)
    w32 = enc[24] | enc[25] << 8 | enc[26] << 16 | enc[27] << 24
    changes.append((IMM32_MOVZ[0], enc_movz(IMM32_MOVZ[2], w32 & 0xFFFF).to_bytes(4, "little"), "movz imm (url 24-25)"))
    changes.append((IMM32_MOVK[0], enc_movk(IMM32_MOVK[2], w32 >> 16).to_bytes(4, "little"), "movk imm (url 26-27)"))
    # piece 4: imm16 for plaintext bytes 28-29 (key phase = key[(28+i)%8] via global enc)
    t16 = enc[28] | enc[29] << 8
    changes.append((IMM16_TERM[0], enc_movz(IMM16_TERM[2], t16).to_bytes(4, "little"), "mov imm (url 28-29 / terminator)"))
    if label:
        # label + ONE NUL terminator only — never touch the byte after
        # (it belongs to the next .rodata string: json error "invalid string...")
        lb = label.encode() + b"\x00"
        changes.append((LABEL_OFF, lb, "cosmetic Server: label"))

    if brand:
        nb = ("@" + brand).encode()
        nb = nb + b"\x00" * (BRAND_SLOT - len(nb)) if len(nb) < BRAND_SLOT else nb
        count = 0
        pos = 0
        while True:
            i = data.find(VENDOR_HANDLE, pos)
            if i < 0:
                break
            data[i:i + BRAND_SLOT] = nb[:BRAND_SLOT] if len(nb) == BRAND_SLOT else (nb + b"\x00" * BRAND_SLOT)[:BRAND_SLOT]
            count += 1
            pos = i + BRAND_SLOT
        if count == 0:
            print("note: vendor handle not found (already rebranded?)")
        else:
            print(f"  rebranded {count} x '@bum7md8' -> '@{brand}'")

    print(f"patching {path} -> {url}\n")
    for off, new, what in changes:
        n = len(new)
        oldhex = data[off:off + n].hex()
        newhex = new.hex()
        data[off:off + n] = new
        print(f"  0x{off:06X}  {what}\n           find:    {oldhex}\n           replace: {newhex}")

    if label:
        pass  # already in changes list above

    open(path, "wb").write(bytes(data))

    # verify by simulating the runtime assembly (all 4 pieces)
    v = bytes(b ^ XKEY[i % 8] for i, b in enumerate(data[0x00E7C20:0x00E7C20 + 16]))
    v += bytes(b ^ XKEY[(16 + i) % 8] for i, b in enumerate(data[0x00E6D08:0x00E6D08 + 8]))
    ia = int.from_bytes(data[IMM32_MOVZ[0]:IMM32_MOVZ[0] + 4], "little")
    ib = int.from_bytes(data[IMM32_MOVK[0]:IMM32_MOVK[0] + 4], "little")
    v += bytes(b ^ XKEY[(24 + i) % 8] for i, b in enumerate(
        bytes([(ia >> 5) & 0xFF, ((ia >> 5) >> 8) & 0xFF, (ib >> 5) & 0xFF, ((ib >> 5) >> 8) & 0xFF])))
    it = int.from_bytes(data[IMM16_TERM[0]:IMM16_TERM[0] + 4], "little")
    v += bytes(b ^ XKEY[(28 + i) % 8] for i, b in enumerate(
        bytes([(it >> 5) & 0xFF, ((it >> 5) >> 8) & 0xFF])))
    final = v.split(b"\x00")[0].decode()
    print(f"\ndone. runtime endpoint is now: {final}")
    if final != url:
        print("ERROR: verification mismatch!"); sys.exit(1)
    print("verification: OK")


if __name__ == "__main__":
    main()
