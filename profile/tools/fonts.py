"""Font discovery: map (family, style) -> font file, read vertical metrics.

Fonts are looked up by the names inside the files, so the official Thmanyah
download can be dropped into src/fonts-private/ (git-ignored: its licence
forbids redistribution) under any file names.
"""
import os
from functools import lru_cache
from pathlib import Path

from fontTools.ttLib import TTFont

ROOT = Path(__file__).resolve().parents[1]
# PROFILE_PREVIEW=1 lets a layout preview render before Thmanyah is installed.
PREVIEW = os.environ.get("PROFILE_PREVIEW") == "1"
DIRS = [ROOT / "build" / "fonts-ttf", ROOT / "src" / "fonts-private"]

WEIGHTS = {"Thin": 100, "ExtraLight": 200, "Light": 300, "Regular": 400, "Medium": 500,
           "SemiBold": 600, "Bold": 700, "ExtraBold": 800, "Black": 900, "Heavy": 900}

# Used only for an explicitly requested --preview build when Thmanyah is not installed.
PREVIEW_FALLBACK = {
    "thmanyah serif display": "Amiri",
    "thmanyah sans": "Readex Pro",
}


def _norm(style):
    s = style.replace(" ", "").replace("Semibold", "SemiBold").replace("Extrabold", "ExtraBold")
    return {"Book": "Regular", "Normal": "Regular", "Italic": "Italic"}.get(s, s)


@lru_cache(None)
def catalog():
    out = {}
    for d in DIRS:
        if not d.exists():
            continue
        for p in sorted(d.rglob("*")):
            if p.suffix.lower() not in (".ttf", ".otf", ".woff2", ".woff"):
                continue
            try:
                n = TTFont(p, lazy=True)["name"]
            except Exception:
                continue
            fam = n.getDebugName(16) or n.getDebugName(1)
            sty = n.getDebugName(17) or n.getDebugName(2)
            out.setdefault((fam, _norm(sty)), p)
    return out


def has_family(family):
    return any(f == family for f, _ in catalog())


def resolve(family, style, preview=None):
    """Return (path, family_actually_used)."""
    preview = PREVIEW if preview is None else preview
    p = catalog().get((family, _norm(style)))
    if p:
        return p, family
    if preview and family in PREVIEW_FALLBACK:
        fb = PREVIEW_FALLBACK[family]
        w = WEIGHTS.get(_norm(style), 400)
        # nearest available weight of the fallback family
        cands = [(abs(WEIGHTS.get(s, 400) - w), s) for f, s in catalog() if f == fb]
        if cands:
            return catalog()[(fb, min(cands)[1])], fb
    raise FileNotFoundError(f"font not installed: {family} {style}")


@lru_cache(None)
def metrics(family):
    """(ascent, descent) per em as Chromium uses them (typo if USE_TYPO_METRICS else hhea)."""
    styles = [s for f, s in catalog() if f == family]
    path, _ = resolve(family, "Regular" if (not styles or "Regular" in styles) else styles[0])
    t = TTFont(path, lazy=True)
    upm = t["head"].unitsPerEm
    os2, hhea = t["OS/2"], t["hhea"]
    if os2.fsSelection & (1 << 7):
        a, d = os2.sTypoAscender, -os2.sTypoDescender
    else:
        a, d = hhea.ascent, -hhea.descent
    return a / upm, d / upm


def cmap(family, style):
    path, _ = resolve(family, style)
    return TTFont(path, lazy=True).getBestCmap()
