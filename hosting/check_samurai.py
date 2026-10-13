#!/usr/bin/env python3
"""
check_samurai.py — verify a libsamurai.so has ALL patches applied.

Usage:
    python3 check_samurai.py libsamurai.so
    python3 check_samurai.py          (asks for the filename)
"""
import struct
import sys

EXPECTED_URL = b"https://domi74.page.gd/connect.php"
URL_OFF = 0x6360A0
STOCK_URL = b"https://night-byte-keys.khalidsafi0306953333.chatgpt.site"

# theme spot-checks (offset -> expected float4 rgba hex after recolor)
THEME_SPOTS = [
    (0x0E6F90, "0A0F1AF7", "WindowBg obsidian navy"),
    (0x0E6CD0, "C79E3D66", "Border soft gold"),
    (0x0E6390, "F2D15CFF", "CheckMark gold"),
    (0x0E7140, "C79E3D4D", "Button gold tint"),
    (0x0E6460, "8C6B29FF", "custom red -> deepest gold"),
    (0x0E7100, "39FF6AFF", "ESP neon mint"),
    (0x0E7C50, "FF3B5CFF", "Snapline crimson rose"),
    (0x0E7A10, "FF5CE1FF", "Pink slot magenta"),
]


def f4_hex(data: bytes, off: int) -> str:
    vals = struct.unpack_from("<4f", data, off)
    return "".join(f"{min(255, max(0, round(v * 255))):02X}" for v in vals)


def main() -> None:
    path = sys.argv[1] if len(sys.argv) > 1 else input("libsamurai.so filename: ").strip()
    try:
        d = open(path, "rb").read()
    except OSError as ex:
        print(f"cannot open {path}: {ex}")
        sys.exit(1)

    print(f"== checking {path} ({len(d)} bytes)")
    if d[:4] != b"\x7fELF":
        print("NOT an ELF/.so file!")
        sys.exit(1)

    results = []

    # 1 endpoint
    cur = d[URL_OFF:URL_OFF + 60].split(b"\x00")[0]
    results.append(("Endpoint -> https://domi74.page.gd/connect.php", cur == EXPECTED_URL,
                    cur.decode(errors="replace")))
    results.append(("Vendor URL removed", STOCK_URL not in d,
                    "absent" if STOCK_URL not in d else "STILL PRESENT"))

    # 1b OFFLINE BYPASS (any key -> LIFETIME, patched native getters)
    MOV_W0_1_RET = bytes.fromhex("2000805 2".replace(" ","") + "c0035fd6")
    FAR_FUTURE   = bytes.fromhex("e0ffbfd2" + "e0ffbff2" + "c0035fd6")
    for off, want, what in [
        (0x638C3C, MOV_W0_1_RET, "Bypass: isAuthed()=true"),
        (0x63BA40, MOV_W0_1_RET, "Bypass: hasSession()=true"),
        (0x620C24, MOV_W0_1_RET, "Bypass: session-valid()=true"),
        (0x63903C, FAR_FUTURE,   "Bypass: getExpiry=lifetime"),
        (0x639048, FAR_FUTURE,   "Bypass: remainingTime=lifetime"),
    ]:
        got = d[off:off+len(want)]
        results.append((what, got == want, got.hex() if got != want else "patched"))

    # 2 theme
    themed = 0
    for off, want, what in THEME_SPOTS:
        got = f4_hex(d, off)
        ok = got == want
        themed += ok
        results.append((f"Theme: {what}", ok, f"#{got}"))

    print()
    for name, ok, info in results:
        print(f"  [{'OK ' if ok else 'MISS'}] {name:42s} {info}")
    passed = sum(1 for _, ok, _ in results if ok)
    total = len(results)
    print(f"\n== {passed}/{total} checks passed")
    if passed == total:
        print("VERDICT: FULLY PATCHED (your panel + Royal Obsidian theme)")


if __name__ == "__main__":
    main()
