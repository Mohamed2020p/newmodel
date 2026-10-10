#!/usr/bin/env python3
"""
make_bypass.py — OFFLINE KEY BYPASS for the 8 Ball Pool mod menu.

Makes the app accept ANY key instantly, no server needed:

  - NOPs the two `tbz w0,#0` branches (0x1B7790, 0x23398C) that jump to the
    "Login failed" path when the license validator returns false. With the
    branch gone, execution ALWAYS falls into the success block:
    logged-in flag=1, licensed flags=1, key/token/expiry saved, config saved.
  - Forces the verdict registers: `mov w19, w0` -> `mov w19, #1`
    (0x1B778C) so the caller always sees "success".

Behavior after patch:
  * type ANY key in the login window -> LOGIN -> instantly accepted
  * works with no internet / no server at all
  * the key is persisted, auto-login succeeds on every future start
  * "Key is empty" still requires typing something (normal UX)

Run it on an already-URL-patched library.so (patch_url.py) or a stock one.
Idempotent: safe to run twice.
"""
import sys

PATCHES = [
    # (offset, stock_word, new_word, what)
    (0x01B778C, 0x2A0003F3, 0x52800033, "mov w19, w0 -> mov w19, #1  (login verdict = success)"),
    (0x01B7790, 0x36000840, 0xD503201F, "tbz w0,#0,fail -> NOP  (never take failure branch)"),
    (0x023398C, 0x36000840, 0xD503201F, "tbz w0,#0,fail -> NOP  (autologin copy, same)"),
]


def main() -> None:
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(1)
    path = sys.argv[1]
    data = bytearray(open(path, "rb").read())
    if data[:4] != b"\x7fELF":
        print("ERROR: not an ELF file")
        sys.exit(1)

    changed = 0
    for off, stock, new, what in PATCHES:
        cur = int.from_bytes(data[off:off + 4], "little")
        if cur == new:
            print(f"  0x{off:06X}  already patched — {what}")
            continue
        if cur != stock:
            print(f"ERROR: unexpected bytes at 0x{off:X}: {cur:08x} (expected {stock:08x}). Wrong library.so?")
            sys.exit(1)
        data[off:off + 4] = new.to_bytes(4, "little")
        changed += 1
        print(f"  0x{off:06X}  {stock:08x} -> {new:08x}  {what}")

    open(path, "wb").write(bytes(data))
    print(f"\ndone. {changed} instruction(s) patched. ANY key is now accepted (offline bypass).")

    # verify by disassembling the patched sites
    try:
        import capstone
        md = capstone.Cs(capstone.CS_ARCH_ARM64, capstone.CS_MODE_LITTLE_ENDIAN)
        for off, _, _, what in PATCHES:
            ins = next(md.disasm(bytes(data[off:off + 4]), off))
            print(f"  verify 0x{off:06X}: {ins.mnemonic} {ins.op_str}")
    except ImportError:
        pass


if __name__ == "__main__":
    main()
