#!/usr/bin/env python3
"""
recolor_theme.py — "Royal Obsidian" premium theme for the mod menu.

Data-only recolor: swaps the float4 color literals in .rodata that the ImGui
style function (0x23D0D0), the Gold Edition login/license windows, and the
ESP defaults loader (0x239E48) read. NO code is modified - zero crash risk.

Design: deep obsidian-navy glass surfaces, champagne->royal gold accents and
borders, gold checkmarks/sliders/buttons, refined neon ESP cycle
(mint / crimson-rose / rich yellow / magenta / amber / electric cyan).

Every entry verifies the CURRENT bytes before writing (safe, idempotent).
"""
import sys
import struct

# (file_offset, expected_current_rgba_hex, new_rgba_hex, what)
RECOLOR = [
    # ---- global ImGui theme (fn 0x23D0D0) ----
    (0x0E7370, "FFFFFFFA", "F5F7FCFA", "Text -> soft platinum white"),
    (0x0E74E0, "808080FF", "6B7690FF", "TextDisabled -> blue-gray"),
    (0x0E7700, "0F0F0FF0", "0A0F1AF7", "WindowBg -> obsidian navy 97%"),
    (0x0E87E0, "141414F0", "0C1220FA", "PopupBg -> midnight glass"),
    (0x0E83A0, "0A0A0AFF", "070C16FF", "TitleBg -> abyss"),
    (0x0E7F30, "294A7AFF", "1A2740FF", "TitleBgActive -> royal navy"),
    (0x0E77D0, "00000082", "00000066", "TitleBgCollapsed"),
    (0x0E8310, "242424FF", "0D1424FF", "MenuBarBg -> navy"),
    (0x0E71C0, "6E6E8080", "C79E3D66", "Border -> soft gold"),
    (0x0E8560, "294A7A8A", "141F358C", "FrameBg -> gunmetal navy"),
    (0x0E7E90, "4296FA66", "C79E3D4D", "FrameBgHovered/Button/TabHovered -> gold 30%"),
    (0x0E7410, "4296FAAB", "E0B54D99", "FrameBgActive/ResizeGripActive -> champagne 60%"),
    (0x0E7940, "05050587", "060B148C", "ScrollbarBg"),
    (0x0E84F0, "4F4F4FFF", "22304DFF", "ScrollbarGrab -> steel navy"),
    (0x0E7F40, "696969FF", "C79E3DCC", "ScrollbarGrabHovered -> gold"),
    (0x0E8200, "828282FF", "F2D15CFF", "ScrollbarGrabActive -> bright gold"),
    (0x0E83B0, "4296FAFF", "F2D15CFF", "CheckMark/HeaderActive -> GOLD check"),
    (0x0E74F0, "3D85E0FF", "E0B54DFF", "SliderGrab -> champagne"),
    (0x0E7A40, "0F87FAFF", "F7E08BFF", "SliderGrabActive/ButtonActive -> light gold"),
    (0x0E8620, "4296FACC", "E0B54DCC", "ButtonHovered/HeaderHovered -> champagne 80%"),
    (0x0E8210, "4296FA33", "C79E3D26", "Header/ResizeGrip/TabUnfocused -> gold 15%"),
    (0x0E7DD0, "3B3B40FF", "22304AFF", "Separator/TableBorderLight"),
    (0x0E7CF0, "1A66BFC7", "C79E3DA6", "SeparatorHovered -> gold 65%"),
    (0x0E7EA0, "1A66BFFF", "F2D15CFF", "SeparatorActive -> gold"),
    (0x0E7C50, "1C1C24EB", "101828EB", "Tab -> navy glass"),
    (0x0E8500, "4296FAF2", "E0B54DF2", "TabActive -> champagne"),
    (0x0E87F0, "4296FA4F", "C79E3D8C", "TabUnfocusedActive -> gold 55%"),
    (0x0E8010, "999999FF", "8FA3C8FF", "PlotLines -> steel blue"),
    (0x0E8080, "FF6E59FF", "F2D15CFF", "PlotLinesHovered -> gold"),
    (0x0E7290, "E5B200FF", "E0B54DFF", "PlotHistogram -> champagne"),
    (0x0E7950, "FF9900FF", "F2D15CFF", "PlotHistogramHovered"),
    (0x0E7590, "303033FF", "141C2FFF", "TableHeaderBg -> navy"),
    (0x0E7A50, "4F4F59FF", "2A364FFF", "TableBorderStrong"),
    (0x0E8250, "33333359", "1018284D", "TableRowBgAlt -> navy stripe"),
    (0x0E8770, "1C1C1CB4", "1018284D", "TableRowBgAlt 2"),
    (0x0E7710, "4296FA59", "C79E3D66", "TextSelectedBg -> gold selection"),
    (0x0E7650, "FFFF00E5", "F2D15CE5", "DragDropTarget -> gold"),
    (0x0E8190, "FFFFFFB2", "C79E3DB2", "NavHighlight -> gold"),
    # ---- Gold Edition login/license window brand colors ----
    (0x0E7920, "F2D15CFF", "F7E08BFF", "Brand gold headings -> champagne glow"),
    (0x0E77C0, "C79E3DFF", "D9AF49FF", "Rich gold -> royal champagne"),
    (0x0E82E0, "D4AD4AFF", "E5C063FF", "Gold mid"),
    (0x0E7640, "A07828FF", "B08530FF", "Gold dark"),
    (0x0E7790, "8C6B29FF", "9A752EFF", "Gold deepest"),
    (0x0E7E60, "080D17F5", "070C16F8", "Overlay window bg -> obsidian"),
    (0x0E8150, "0D1421FA", "0A0F1BFB", "Section bg"),
    (0x0E77B0, "0F1A2EFF", "0D1626FF", "Panel bg"),
    (0x0E8300, "33425CFF", "1E2C47FF", "Sub-panel -> royal slate"),
    (0x0E7B70, "B2BFD9FF", "C9D4E8FF", "Silver text -> platinum"),
    (0x0E8060, "9EA8BFFF", "AEB9D2FF", "Muted text"),
    (0x0E8160, "FF6666FF", "FF7070FF", "Alert red (softer)"),
    (0x0E7840, "BF4040FF", "C94B4BFF", "Error red"),
    (0x0E84C0, "D95959FF", "E06060FF", "Danger red"),
    # ---- ESP / aimbot default cycle (best-visible neon set) ----
    (0x0E86F0, "00FF00FF", "39FF6AFF", "ESP skeleton -> neon mint"),
    (0x0E87C0, "FF0000FF", "FF3B5CFF", "Aim snapline -> crimson rose"),
    (0x0E87D0, "FFFF00FF", "FFD60AFF", "Snapline (bot) -> rich yellow"),
    (0x0E7A30, "FFC0CBFF", "FF5CE1FF", "ESP pink slot -> neon magenta"),
    (0x0E7F20, "FFA500FF", "FF9F1CFF", "Distance text -> amber"),
    (0x0E7E80, "00FFFFFF", "00E5FFFF", "Accent -> electric cyan"),
]


def f4_bytes(hexrgba: str) -> bytes:
    import struct
    r, g, b, a = (int(hexrgba[i:i + 2], 16) / 255.0 for i in (0, 2, 4, 6))
    return struct.pack("<4f", r, g, b, a)


def f4_hex(data: bytes, off: int) -> str:
    """float4 -> rounded RGBA hex (absorbs 1-ULP float differences in stock data)."""
    vals = struct.unpack_from("<4f", data, off)
    return "".join(f"{min(255, max(0, round(v * 255))):02X}" for v in vals)


def main() -> None:
    if len(sys.argv) < 2:
        print(__doc__)
        print("usage: python3 recolor_theme.py library.so")
        sys.exit(1)
    path = sys.argv[1]
    data = bytearray(open(path, "rb").read())

    ok = skipped = changed = 0
    for off, cur_hex, new_hex, what in RECOLOR:
        cur = f4_hex(bytes(data), off)
        if cur == new_hex:
            skipped += 1
            continue
        if cur != cur_hex:
            print(f"ERROR at 0x{off:X}: current #{cur} != expected #{cur_hex} ({what})")
            print("       wrong library.so or already recolored with other values.")
            sys.exit(1)
        data[off:off + 16] = f4_bytes(new_hex)
        changed += 1
        print(f"  0x{off:06X}  #{cur_hex} -> #{new_hex}  {what}")

    open(path, "wb").write(bytes(data))
    print(f"\ndone. {changed} colors applied ({skipped} already set). Theme: Royal Obsidian.")


if __name__ == "__main__":
    main()
