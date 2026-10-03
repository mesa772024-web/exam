# -*- coding: utf-8 -*-
"""Rebrand a copy of the Al Hayat website package into Baghdad Al Hayat.

Content stays exactly as in the original; only the office name, contact
email and the visual identity (colour palette) change:
  * every company-name mention -> Baghdad Al Hayat / بغداد الحياة
  * every green colour in CSS / inline styles / scripts -> the matching
    burgundy (hue rotated onto the Baghdad Al Hayat leaf colour, same
    saturation & lightness so contrast is preserved)

usage: python3 tools/rebrand_baghdad.py baghdad-alhayat
"""
import colorsys
import os
import re
import sys

ROOT = sys.argv[1]
TEXT_EXT = ('.php', '.js', '.css', '.html', '.md', '.json', '.txt', '.svg')
SKIP_DIRS = {'fonts'}

# ---------------------------------------------------------------- names ----
NAME_RULES = [
    # contact details belonging to the other office
    (re.compile(r'info@alhayatso\.com'), 'info@baghdadalhayat.com'),
    # Latin
    (re.compile(r'(?<!BAGHDAD )\bAL[ -]HAYAT\b'), 'BAGHDAD AL HAYAT'),
    (re.compile(r'(?<!Baghdad )\bAl[ -]Hayat\b'), 'Baghdad Al Hayat'),
    (re.compile(r'\b([Aa])n Baghdad Al Hayat'), r'\1 Baghdad Al Hayat'),
    # Arabic — every company mention of «الحياة» (not the phrase «جودة الحياة» = quality of life)
    (re.compile(r'(?<!بغداد )(?<!جودة )(?<![؀-ۿ])الحياة'), 'بغداد الحياة'),
]
# visible social links of the other office are dropped (Baghdad Al Hayat accounts unknown)
SOCIAL_RE = re.compile(r'\s*<a href="https://www\.(linkedin\.com/company/alhayatso|instagram\.com/alhayat_scientific_office|facebook\.com/alhayatso)/">[^<]*</a>')

# --------------------------------------------------------------- colours ---
TARGET_HUE = 334 / 360.0          # Baghdad Al Hayat leaf  #A00B4C
GREEN_LO, GREEN_HI = 70 / 360.0, 190 / 360.0


def remap(r, g, b):
    h, l, s = colorsys.rgb_to_hls(r / 255, g / 255, b / 255)
    if s < 0.07 or not (GREEN_LO <= h <= GREEN_HI):
        return None
    # keep relative hue variation, compressed, around the brand hue
    nh = (TARGET_HUE + (h - 150 / 360.0) * 0.25) % 1.0
    nr, ng, nb = colorsys.hls_to_rgb(nh, l, s)
    return round(nr * 255), round(ng * 255), round(nb * 255)


HEX_RE = re.compile(r'(?<!&)#([0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})\b')
RGB_RE = re.compile(r'rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(\s*,\s*[\d.]+%?)?\s*\)')
RGB_SPACE_RE = re.compile(r'rgba?\(\s*(\d{1,3})\s+(\d{1,3})\s+(\d{1,3})(\s*/\s*[\d.]+%?)?\s*\)')


def hex_sub(m):
    v = m.group(1)
    if len(v) in (3, 4):
        r, g, b = (int(c * 2, 16) for c in v[:3])
        alpha = v[3] * 2 if len(v) == 4 else ''
    else:
        r, g, b = int(v[0:2], 16), int(v[2:4], 16), int(v[4:6], 16)
        alpha = v[6:8] if len(v) == 8 else ''
    n = remap(r, g, b)
    if not n:
        return m.group(0)
    out = '#%02x%02x%02x%s' % (*n, alpha)
    return out.upper() if v.isupper() else out


def rgb_sub(m):
    r, g, b = (int(m.group(i)) for i in (1, 2, 3))
    n = remap(r, g, b)
    if not n:
        return m.group(0)
    rest = m.group(4) or ''
    fn = 'rgba' if m.group(0).startswith('rgba') else 'rgb'
    return f'{fn}({n[0]},{n[1]},{n[2]}{rest})'


def rgb_space_sub(m):
    r, g, b = (int(m.group(i)) for i in (1, 2, 3))
    n = remap(r, g, b)
    if not n:
        return m.group(0)
    fn = 'rgba' if m.group(0).startswith('rgba') else 'rgb'
    return f'{fn}({n[0]} {n[1]} {n[2]}{m.group(4) or ""})'


def recolor(text):
    # only touch colour-looking tokens; ids/anchors like href="#top" are not hex colours
    text = HEX_RE.sub(lambda m: hex_sub(m) if re.fullmatch(r'[0-9a-fA-F]+', m.group(1)) else m.group(0), text)
    text = RGB_RE.sub(rgb_sub, text)
    text = RGB_SPACE_RE.sub(rgb_space_sub, text)
    return text


stats = {'files': 0, 'names': 0, 'colours': 0}
for base, dirs, files in os.walk(ROOT):
    dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
    for fn in files:
        if not fn.endswith(TEXT_EXT):
            continue
        p = os.path.join(base, fn)
        src = open(p, encoding='utf8', errors='surrogateescape').read()
        out = src
        if fn.endswith(('.php', '.js', '.html', '.md', '.json', '.txt')):
            out = SOCIAL_RE.sub('', out)
            for rx, rep in NAME_RULES:
                out, n = rx.subn(rep, out)
                stats['names'] += n
        if fn.endswith(('.css', '.php', '.js', '.html', '.svg')):
            before = out
            out = recolor(out)
            stats['colours'] += sum(1 for a, b in zip(HEX_RE.findall(before), HEX_RE.findall(out)) if a != b)
        if out != src:
            open(p, 'w', encoding='utf8', errors='surrogateescape').write(out)
            stats['files'] += 1
print(stats)
