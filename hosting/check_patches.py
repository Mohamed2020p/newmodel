#!/usr/bin/env python3
"""
check_patches.py — verify a library.so has ALL the patches applied.

Usage:
    python3 check_patches.py library.so
    python3 check_patches.py            (it will ask for the filename)
"""
import sys
import struct

XKEY = bytes([0x17, 0x05, 0x35, 0x6D, 0x07, 0xD5, 0x33, 0x95])

EXPECTED_URL = b"http://domi74.page.gd/k.php"
EXPECTED_LABEL = b"domi74.page.gd"
BRAND = b"@free4us"
VENDOR = b"@bum7md8"

BYPASS = [
    (0x01B778C, 0x52800033, "mov w19, #1   (login verdict forced SUCCESS)"),
    (0x01B7790, 0xD503201F, "nop           (failure branch removed)"),
    (0x023398C, 0xD503201F, "nop           (autologin failure branch removed)"),
]
STOCK_BYPASS = [(0x01B778C, 0x2A0003F3), (0x01B7790, 0x36000840), (0x023398C, 0x36000840)]

# spot-checks of the Royal Obsidian theme (offset -> expected float4 rgba hex)
THEME_SPOTS = [
    (0x0E7700, "0A0F1AF7", "WindowBg obsidian navy"),
    (0x0E71C0, "C79E3D66", "Border soft gold"),
    (0x0E83B0, "F2D15CFF", "CheckMark gold"),
    (0x0E7E90, "C79E3D4D", "Button gold tint"),
    (0x0E7920, "F7E08BFF", "Brand champagne"),
    (0x0E86F0, "39FF6AFF", "ESP neon mint"),
    (0x0E87C0, "FF3B5CFF", "Snapline crimson rose"),
]


def dec(data: bytes, off: int, n: int) -> bytes:
    return bytes(b ^ XKEY[(off + i) % 8] for i, b in enumerate(data[off:off + n]))


def imm16(data: bytes, off: int) -> int:
    return (struct.unpack_from("<I", data, off)[0] >> 5) & 0xFFFF


def f4_hex(data: bytes, off: int) -> str:
    vals = struct.unpack_from("<4f", data, off)
    return "".join(f"{min(255, max(0, round(v * 255))):02X}" for v in vals)


def main() -> None:
    path = sys.argv[1] if len(sys.argv) > 1 else input("library.so filename: ").strip()
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
    url = (dec(d, 0xE7C20, 16) + dec(d, 0xE6D08, 8) +
           bytes(b ^ XKEY[(24 + i) % 8] for i, b in enumerate(struct.pack("<HH", imm16(d, 0x23C064), imm16(d, 0x23C074)))) +
           bytes(b ^ XKEY[(28 + i) % 8] for i, b in enumerate(struct.pack("<H", imm16(d, 0x23C078)))))
    url = url.split(b"\x00")[0]
    results.append(("Endpoint (domi74.page.gd/k.php)", url == EXPECTED_URL, url.decode(errors="replace")))

    # 2 cosmetic label
    lab = d[0xE953E:0xE953E + len(EXPECTED_LABEL)]
    results.append(("Server: label (domi74.page.gd)", lab == EXPECTED_LABEL, lab.decode(errors="replace")))

    # 3 brand
    n_new, n_old = d.count(BRAND), d.count(VENDOR)
    results.append((f"Rebrand @free4us x5 (found {n_new}, vendor leftovers {n_old})",
                    n_new == 5 and n_old == 0, f"{n_new}/5"))

    # 4 bypass
    for off, want, what in BYYPASS if False else BYPASS:
        cur = struct.unpack_from("<I", d, off)[0]
        stock = dict(STOCK_BYPASS).get(off)
        if cur == want:
            results.append((f"Bypass 0x{off:06X}: {what}", True, f"{cur:08x}"))
        elif cur == stock:
            results.append((f"Bypass 0x{off:06X}: {what}", False, "STOCK (not patched)"))
        else:
            results.append((f"Bypass 0x{off:06X}: {what}", False, f"unknown {cur:08x}"))

    # 5 theme spot checks
    themed = 0
    for off, want, what in THEME_SPOTS:
        cur = f4_hex(d, off)
        good = cur == want
        themed += good
        results.append((f"Theme: {what}", good, f"#{cur}"))

    print()
    for name, ok, info in results:
        print(f"  [{'OK ' if ok else 'MISS'}] {name:48s} {info}")
    total = len(results)
    passed = sum(1 for _, ok, _ in results if ok)
    print(f"\n== {passed}/{total} checks passed")
    if passed == total:
        print("VERDICT: FULLY PATCHED (url + rebrand + offline key bypass + Royal Obsidian theme)")
    elif all(ok for name, ok, _ in results if name.startswith(("Endpoint", "Server", "Rebrand", "Bypass"))):
        print("VERDICT: functional patches OK — theme missing/incomplete (run recolor_theme.py)")
    else:
        print("VERDICT: NOT fully patched — run patch_url.py / make_bypass.py / recolor_theme.py")


if __name__ == "__main__":
    main()
