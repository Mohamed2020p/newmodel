#!/usr/bin/env python3
"""
recolor_samurai.py — "Royal Obsidian" theme port for libsamurai.so.

Data-only recolor of the float4 color literals that SAMURAI's code actually
references (extracted by scanning all 1,063,113 instructions for adrp+ldr q
into .rodata float4s — 180 referenced colors, mapped by hand):

  0x735028-0x735230  global ImGui style function  (stock blue theme)
  0x59809c-0x598128  ESP / aimbot default cycle   (green/red/yellow/pink/orange/cyan)
  0x59a8xx-0x59exxx  SAMURAI custom red/dark UI accents
  0x5abd54-0x5cd748  gold-tinted panel accents
  0x7a3axx-0x7a3cxx  ColorEdit swatch palette

Verify-then-write with 1-ULP-tolerant rounded RGBA compare. Idempotent.
"""
import struct
import sys

# (offset, current_rgba_hex, new_rgba_hex, what)
RECOLOR = [
    # ---- global ImGui style (fn 0x735028+) ----
    (0x0E6F90, "0F0F0FF0", "0A0F1AF7", "WindowBg -> obsidian navy"),
    (0x0E6880, "141414F0", "0C1220FA", "PopupBg -> midnight glass"),
    (0x0E6A60, "0A0A0AFF", "070C16FF", "TitleBg -> abyss"),
    (0x0E66B0, "294A7AFF", "1A2740FF", "TitleBgActive -> royal navy"),
    (0x0E61C0, "00000082", "00000066", "TitleBgCollapsed"),
    (0x0E7C80, "242424FF", "0D1424FF", "MenuBarBg -> navy"),
    (0x0E6CD0, "6E6E8080", "C79E3D66", "Border -> soft gold"),
    (0x0E7BB0, "294A7A8A", "141F358C", "FrameBg -> gunmetal navy"),
    (0x0E7140, "4296FA66", "C79E3D4D", "FrameBgHovered/Button -> gold 30%"),
    (0x0E6CE0, "4296FAAB", "E0B54D99", "FrameBgActive -> champagne 60%"),
    (0x0E6540, "05050587", "060B148C", "ScrollbarBg"),
    (0x0E6C10, "4F4F4FFF", "22304DFF", "ScrollbarGrab -> steel navy"),
    (0x0E6DA0, "696969FF", "C79E3DCC", "ScrollbarGrabHovered -> gold"),
    (0x0E61D0, "828282FF", "F2D15CFF", "ScrollbarGrabActive -> bright gold"),
    (0x0E6390, "4296FAFF", "F2D15CFF", "CheckMark/HeaderActive -> GOLD"),
    (0x0E6790, "3D85E0FF", "E0B54DFF", "SliderGrab -> champagne"),
    (0x0E7840, "0F87FAFF", "F7E08BFF", "SliderGrabActive/ButtonActive"),
    (0x0E6550, "4296FA4F", "C79E3D8C", "Button/TabUnfocusedActive -> gold"),
    (0x0E7250, "4296FA33", "C79E3D26", "Header -> gold 15%"),
    (0x0E7C90, "4296FACC", "E0B54DCC", "ButtonHovered/HeaderHovered"),
    (0x0E7CA0, "4296FAF2", "E0B54DF2", "TabActive -> champagne"),
    (0x0E7380, "4296FAC7", "E0B54DCC", "ResizeGripHovered"),
    (0x0E6DC0, "4296FA59", "C79E3D66", "TextSelectedBg -> gold"),
    (0x0E65E0, "1A66BFC7", "C79E3DA6", "SeparatorHovered -> gold"),
    (0x0E6C20, "1A66BFFF", "F2D15CFF", "SeparatorActive -> gold"),
    (0x0E7050, "FFFF00E5", "F2D15CE5", "DragDropTarget -> gold"),
    (0x0E7CB0, "FFFFFFB2", "C79E3DB2", "NavHighlight -> gold"),
    (0x0E6FA0, "CCCCCC33", "1018284D", "TableRowBg -> navy stripe"),
    (0x0E63B0, "CCCCCC59", "10182859", "TableRowBgAlt"),
    (0x0E65F0, "111A26F8", "0A0F1AF7", "style-fn navy surface -> obsidian"),
    (0x0E6E70, "3369ADFF", "8C6B29FF", "style-fn blue accent -> deep gold"),
    (0x0E62D0, "33FF4F4F", "39FF6A4F", "style green tint -> neon mint"),
    # ---- ESP / aimbot default cycle ----
    (0x0E7100, "00FF00FF", "39FF6AFF", "ESP skeleton -> neon mint"),
    (0x0E7C50, "FF0000FF", "FF3B5CFF", "Snapline -> crimson rose"),
    (0x0E7440, "FFFF00FF", "FFD60AFF", "Snapline(bot) -> rich yellow"),
    (0x0E7A10, "FFC0CBFF", "FF5CE1FF", "Pink slot -> neon magenta"),
    (0x0E7B70, "FFA500FF", "FF9F1CFF", "Distance text -> amber"),
    (0x0E7110, "00FFFFFF", "00E5FFFF", "Accent -> electric cyan"),
    (0x0E6030, "22D640FF", "39FF6AFF", "Status green -> neon mint"),
    # ---- SAMURAI custom red/dark UI cluster -> royal navy/gold ----
    (0x0E6460, "8C1A24FF", "8C6B29FF", "deep red accent -> deepest gold"),
    (0x0E7240, "FF3847FF", "E0B54DFF", "bright red accent -> champagne"),
    (0x0E7930, "290A0DFF", "141F35FF", "dark red panel -> royal navy"),
    (0x0E7B80, "0A0405F2", "0A0F1AF2", "near-black red bg -> obsidian"),
    (0x0E61A0, "120508F2", "0A0F1AF7", "dark plum bg -> obsidian"),
    (0x0E7320, "1F1F1FFF", "101828FF", "charcoal -> navy"),
    (0x0E7200, "0A0A12E0", "0A0F1AE0", "panel bg -> obsidian"),
    (0x0E6440, "262433FF", "141C2FFF", "plum panel -> midnight"),
    (0x0E6950, "661F2400", "141F3500", "red tint 0-alpha -> navy tint"),
    (0x0E7010, "D9A6408C", "C79E3D8C", "muted gold -> brand gold"),
    # ---- teal/green ESP tints -> electric cyan / mint family ----
    (0x0E62B0, "FEFF5D2C", "F2D15C2C", "yellow tint -> gold tint"),
    (0x0E6530, "12FFFF25", "00E5FF25", "cyan tint"),
    (0x0E6C00, "4AFFFFC8", "00E5FFC8", "cyan solid"),
    (0x0E63A0, "59FFE5B2", "00E5FFB2", "mint -> cyan"),
    (0x0E6DB0, "59FF3B3B", "39FF6A3B", "green tint -> mint tint"),
    (0x0E7560, "6CFF9C9C", "39FF6A9C", "green soft -> mint"),
    (0x0E7850, "9CFFFF6E", "00E5FF6E", "cyan soft"),
    (0x0E6E80, "00FFFF99", "00E5FF99", "cyan 60%"),
    (0x0E7A70, "03FFE5CC", "00E5FFCC", "teal -> cyan"),
    # ---- orange/red accents ----
    (0x0E68B0, "FF7300FF", "FF9F1CFF", "orange -> amber"),
    (0x0E6C50, "FF9900FF", "FF9F1CFF", "orange -> amber"),
    (0x0E7290, "E5B200FF", "E0B54DFF", "gold-orange -> champagne"),
    (0x0E63F0, "FF6E59FF", "F2D15CFF", "salmon -> gold"),
    (0x0E7CD0, "FF6666FF", "FF7070FF", "alert red (softer)"),
]


def f4_bytes(hexrgba: str) -> bytes:
    r, g, b, a = (int(hexrgba[i:i + 2], 16) / 255.0 for i in (0, 2, 4, 6))
    return struct.pack("<4f", r, g, b, a)


def f4_hex(data: bytes, off: int) -> str:
    vals = struct.unpack_from("<4f", data, off)
    return "".join(f"{min(255, max(0, round(v * 255))):02X}" for v in vals)


def main() -> None:
    if len(sys.argv) < 2:
        print(__doc__)
        print("usage: python3 recolor_samurai.py libsamurai.so")
        sys.exit(1)
    path = sys.argv[1]
    data = bytearray(open(path, "rb").read())

    changed = skipped = 0
    for off, cur_hex, new_hex, what in RECOLOR:
        cur = f4_hex(bytes(data), off)
        if cur == new_hex:
            skipped += 1
            continue
        if cur != cur_hex:
            print(f"ERROR at 0x{off:X}: current #{cur} != expected #{cur_hex} ({what})")
            print("       wrong file or recolored with different values.")
            sys.exit(1)
        data[off:off + 16] = f4_bytes(new_hex)
        changed += 1
        print(f"  0x{off:06X}  #{cur_hex} -> #{new_hex}  {what}")

    open(path, "wb").write(bytes(data))
    print(f"\ndone. {changed} colors applied ({skipped} already set). SAMURAI theme: Royal Obsidian.")


if __name__ == "__main__":
    main()
