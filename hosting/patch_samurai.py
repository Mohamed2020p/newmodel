#!/usr/bin/env python3
"""
patch_samurai.py — point the SAMURAI mod (libsamurai.so) at YOUR panel.

Unlike the other mod, SAMURAI stores its key-server URL as PLAIN TEXT
(verified: loaded via strlen + std::string copy at 0x636348, then handed
to the HTTP layer):

    offset 0x6360A0 : "https://night-byte-keys.khalidsafi0306953333.chatgpt.site"
                      (57 characters max — your URL must be <= 57)

Usage:
    python3 patch_samurai.py libsamurai.so http://domi74.page.gd/samurai.php
    python3 patch_samurai.py libsamurai.so --show

The matching endpoint for your panel is hosting/samurai.php (PWF protocol:
answers {"success":true,"message":...} and logs everything the app sends).
"""
import sys

URL_OFF = 0x6360A0
URL_MAX = 57
STOCK_URL = b"https://night-byte-keys.khalidsafi0306953333.chatgpt.site"


def main() -> None:
    if len(sys.argv) < 3:
        print(__doc__)
        sys.exit(1)
    path = sys.argv[1]
    arg = sys.argv[2]

    data = bytearray(open(path, "rb").read())
    if data[:4] != b"\x7fELF":
        print("ERROR: not an ELF/.so file")
        sys.exit(1)

    if arg == "--show":
        cur = data[URL_OFF:URL_OFF + URL_MAX + 4].split(b"\x00")[0]
        print("current URL:", cur.decode(errors="replace"))
        sys.exit(0)

    url = arg.strip().encode()
    if len(url) > URL_MAX:
        print(f"ERROR: url is {len(url)} chars, max {URL_MAX}")
        sys.exit(1)

    cur = data[URL_OFF:URL_OFF + len(STOCK_URL)]
    if cur == url + b"\x00" * (URL_MAX - len(url)):
        print("already patched")
        sys.exit(0)
    if cur != STOCK_URL and not cur.startswith(b"http"):
        print(f"ERROR: unexpected bytes at 0x{URL_OFF:X}: {cur[:40]!r}")
        sys.exit(1)

    new = url + b"\x00" * (URL_MAX + 1 - len(url))   # NUL-terminate + pad
    data[URL_OFF:URL_OFF + URL_MAX + 1] = new
    open(path, "wb").write(bytes(data))
    print(f"patched {path}:")
    print(f"  0x{URL_OFF:X}: {STOCK_URL.decode()}")
    print(f"           -> {url.decode()}")
    print("done. The app will now contact YOUR panel (samurai.php answers in PWF format).")


if __name__ == "__main__":
    main()
