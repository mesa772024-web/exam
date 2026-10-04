#!/usr/bin/env python3
"""Build the agenda draft for the Iraqi investment & business delegation meeting.

One layout spec (in mm) drives two outputs:
  * out/agenda.html  -> rendered to PDF/PNG with Chromium (render.mjs)
  * out/Agenda_Draft.idml -> opens in Adobe InDesign as an editable document

Content is taken verbatim from the client's Word file; the only change requested
in the brief is the start time (11:30).
"""
import math
import re
import zipfile
from pathlib import Path
from xml.sax.saxutils import escape

from fontTools.pens.recordingPen import RecordingPen
from fontTools.pens.transformPen import TransformPen
from fontTools.svgLib.path import parse_path

ROOT = Path(__file__).parent
OUT = ROOT / "out"
MM = 72 / 25.4
W, H, BLEED = 210.0, 297.0, 3.0

# ---------------------------------------------------------------- colours
# name: (hex for screen, CMYK for InDesign)
COLORS = {
    "Maroon":      ("#8A1538", "0 100 60 40"),
    "MaroonLight": ("#A3304F", "0 82 52 30"),
    "Gold":        ("#B8975A", "0 18 51 28"),
    "Sand":        ("#F5EFE6", "0 2 6 4"),
    "Line":        ("#E2D7C6", "0 5 12 11"),
    "Ink":         ("#222222", "0 0 0 87"),
    "Gray":        ("#6B6B6B", "0 0 0 58"),
    "IraqRed":     ("#CE1126", "0 92 82 19"),
    "IraqGreen":   ("#007A3D", "100 0 50 52"),
    "IraqBlack":   ("#000001", "0 0 0 100"),
    "QatarMaroon": ("#8D1B3D", "0 81 57 45"),
    "Paper":       ("#FFFFFF", "0 0 0 0"),
}

# ---------------------------------------------------------------- text styles
# weight -> InDesign FontStyle
WEIGHTS = {400: "Regular", 500: "Medium", 600: "SemiBold", 700: "Bold"}
STYLES = {
    #  name          weight size leading colour   align     dir    tracking tint
    "Kicker":      (500, 10,  14, "Gold",   "center", "ltr", 200, 100),
    "Badge":       (600, 9,   12, "Gold",   "center", "rtl", 0,   100),
    "Title":       (700, 27,  38, "Paper",  "center", "rtl", 0,   100),
    "Date":        (500, 15,  22, "Sand",   "center", "rtl", 0,   100),
    "Time":        (700, 17,  22, "Paper",  "center", "ltr", 20,  100),
    "Section":     (700, 15,  22, "Maroon", "right",  "rtl", 0,   100),
    "Item":        (600, 13.5, 20, "Maroon", "right", "rtl", 0,   100),
    "Name":        (700, 13,  19, "Ink",    "right",  "rtl", 0,   100),
    "Role":        (400, 11,  16, "Gray",   "right",  "rtl", 0,   100),
    "Placeholder": (500, 8.5, 12, "Gray",   "center", "rtl", 0,   100),
    "Watermark":   (700, 130, 150, "Maroon", "center", "rtl", 0,  6),
}

# ---------------------------------------------------------------- content
CONTENT = {
    "kicker": "Agenda (TBC)",
    "badge": "مسودة",
    "title": "لقاء وفد الإستثمار والأعمال العراقي",
    "date": "الأربعاء 7 اكتوبر 2026",
    "slot1_time": "11:30",
    "slot1_title": "كلمات ترحيبية",
    "speakers": [
        ("سعادة الشيخ/ خليفه بن جاسم آل ثاني", "رئيس مجلس الإدارة – غرفة قطر"),
        ("سعادة السيد/ عادل داخل محمد الياسري", "رئيس هيئة الإستثمار – جمهورية العراق"),
        ("سعادة السيد/ عامر خلف علاوي", "رئيس اتحاد الغرف التجارية العراقية"),
    ],
    "presentation": "عرض تقديمي – هيئة الاستثمار، جمهورية العراق",
    "slot2_time": "12:00",
    "slot2_title": "لقاءات ثنائية - غداء",
    "ph_qc": "شعار غرفة قطر",
    "ph_fed": "شعار اتحاد الغرف التجارية العراقية",
}


# ---------------------------------------------------------------- flag geometry
def iraq_flag_paths():
    """Takbir paths from the Iraq flag SVG, flattened to cubic subpaths in a
    720x480 (3:2) box whose x runs -40..680 in the source coordinates."""
    src = (ROOT / "assets/flags/iq-source.svg").read_text()
    group = re.search(r'<g fill="#007a3d" transform="translate\(([-\d.]+) ([-\d.]+)\)scale\(([\d.]+)\)">(.*?)</g>', src, re.S)
    tx, ty, sc = map(float, group.group(1, 2, 3))
    ds = re.findall(r' d="([^"]+)"', group.group(4))
    subpaths = []
    for d in ds:
        rec = RecordingPen()
        pen = TransformPen(rec, (sc, 0, 0, sc, tx + 40, ty))  # +40 shifts into 0..720
        parse_path(d, pen)
        start = last = None
        segs = []
        for op, pts in rec.value:
            if op == "moveTo":
                start = last = pts[0]
                segs = []
            elif op == "lineTo":
                segs.append(("L", pts[0]))
                last = pts[0]
            elif op == "curveTo":
                segs.append(("C", pts))
                last = pts[-1]
            elif op == "qCurveTo":
                (q, p), p0 = pts, last
                c1 = (p0[0] + 2 / 3 * (q[0] - p0[0]), p0[1] + 2 / 3 * (q[1] - p0[1]))
                c2 = (p[0] + 2 / 3 * (q[0] - p[0]), p[1] + 2 / 3 * (q[1] - p[1]))
                segs.append(("C", (c1, c2, p)))
                last = p
            elif op in ("closePath", "endPath"):
                subpaths.append(to_points(start, segs))
    return subpaths


def to_points(start, segs):
    """Turn a closed segment list into [(anchor, left, right)] for InDesign."""
    pts = [[start, start, start]]
    for kind, data in segs:
        if kind == "L":
            pts.append([data, data, data])
        else:
            c1, c2, p = data
            pts[-1][2] = c1          # outgoing handle of previous anchor
            pts.append([p, c2, p])   # incoming handle of this anchor
    # drop duplicated closing point, carrying its incoming handle to the start
    if len(pts) > 1 and math.dist(pts[-1][0], pts[0][0]) < 1e-6:
        pts[0][1] = pts[-1][1]
        pts.pop()
    return pts


def qatar_band(x, y, w, h):
    """White serrated band (9 points) for a 3:2 Qatar flag, in page mm."""
    inner, outer = 0.33 * h, 0.5335 * h
    pts = [(x, y), (x + inner, y)]
    for i in range(9):
        pts.append((x + outer, y + (i + 0.5) * h / 9))
        pts.append((x + inner, y + (i + 1) * h / 9))
    pts.append((x, y + h))
    return pts


def star_squares(cx, cy, r):
    """Two squares forming an eight-point star (Rub el Hizb motif)."""
    sq = []
    for rot in (45, 0):
        sq.append([(cx + r * math.cos(math.radians(rot + 90 * k)),
                    cy + r * math.sin(math.radians(rot + 90 * k))) for k in range(4)])
    return sq


# ---------------------------------------------------------------- layout
def layout():
    L = []  # back-to-front
    rect = lambda x, y, w, h, **k: L.append(dict(kind="rect", x=x, y=y, w=w, h=h, **k))
    text = lambda x, y, w, h, paras, **k: L.append(dict(kind="text", x=x, y=y, w=w, h=h, paras=paras, **k))
    poly = lambda pts, **k: L.append(dict(kind="poly", pts=pts, **k))

    # Header band (full bleed) with star motifs
    rect(-BLEED, 45, W + 2 * BLEED, 70, fill="Maroon", name="Header band")
    for cx in (0, W):
        for r in (27, 18):
            for sq in star_squares(cx, 80, r):
                poly(sq, stroke="MaroonLight", sw=0.75, name="Star motif")
    rect(-BLEED, 115, W + 2 * BLEED, 1.2, fill="Gold", name="Header gold line")

    # Draft watermark (behind the programme)
    text(105 - 85, 200 - 32, 170, 64, [("Watermark", CONTENT["badge"])], rotate=-25,
         valign="center", name="Draft watermark")

    # Logo row (RTL order: Iraq flag, Federation, Qatar flag, Qatar Chamber)
    for x, label, nm in ((16, CONTENT["ph_qc"], "Logo – Qatar Chamber"),
                         (114, CONTENT["ph_fed"], "Logo – Federation of Iraqi Chambers")):
        rect(x, 13, 44, 24, fill="Sand", stroke="Line", sw=0.5, radius=2, name=nm + " (placeholder)")
        text(x + 3, 15, 38, 20, [("Placeholder", label)], valign="center", name=nm + " label")
    L.append(dict(kind="flag", which="qa", x=66, y=15, w=30, h=20, name="Flag – Qatar"))
    L.append(dict(kind="flag", which="iq", x=164, y=15, w=30, h=20, name="Flag – Iraq"))
    rect(104.85, 17, 0.3, 16, fill="Gold", name="Logo divider")

    # Header text
    rect(93, 51, 24, 7, stroke="Gold", sw=0.6, radius=3.5, name="Draft badge")
    text(93, 51, 24, 7, [("Badge", CONTENT["badge"])], valign="center", name="Draft badge text")
    text(16, 61, 178, 6.5, [("Kicker", CONTENT["kicker"])], valign="center")
    text(16, 69, 178, 15, [("Title", CONTENT["title"])], valign="center")
    rect(93, 88.5, 24, 0.6, fill="Gold", name="Title rule")
    text(16, 92, 178, 9, [("Date", CONTENT["date"])], valign="center")

    # Timeline
    PILL_X, PILL_W, PILL_H = 162, 32, 12
    TEXT_X, TEXT_R = 16, 148          # programme column (right edge 148)
    BAR_X = 151.5
    y1, y2 = 128, 245
    rect(PILL_X + PILL_W / 2 - 0.25, y1 + PILL_H, 0.5, y2 - y1 - PILL_H, fill="Gold", name="Timeline")
    for y, t, title in ((y1, CONTENT["slot1_time"], CONTENT["slot1_title"]),
                        (y2, CONTENT["slot2_time"], CONTENT["slot2_title"])):
        rect(PILL_X, y, PILL_W, PILL_H, fill="Maroon", radius=PILL_H / 2, name="Time pill " + t)
        text(PILL_X, y, PILL_W, PILL_H, [("Time", t)], valign="center")
        text(TEXT_X, y, TEXT_R - TEXT_X + 6, PILL_H, [("Section", title)], valign="center")

    sy = 147
    for name, role in CONTENT["speakers"]:
        rect(BAR_X, sy + 0.5, 1, 11.5, fill="Maroon", name="Speaker bar")
        text(TEXT_X, sy, TEXT_R - TEXT_X, 14, [("Name", name), ("Role", role)])
        sy += 22
    rect(TEXT_X, 213, BAR_X + 1 - TEXT_X, 0.3, fill="Line", name="Divider")
    rect(BAR_X, 219, 1, 6.5, fill="Gold", name="Presentation bar")
    text(TEXT_X, 217.5, TEXT_R - TEXT_X, 9.5, [("Item", CONTENT["presentation"])], valign="center")
    rect(TEXT_X, 236, BAR_X + 1 - TEXT_X, 0.3, fill="Line", name="Divider")

    # Footer
    rect(-BLEED, 283.8, W + 2 * BLEED, 1.2, fill="Gold", name="Footer gold line")
    rect(-BLEED, 285, W + 2 * BLEED, H - 285 + BLEED, fill="Maroon", name="Footer band")
    return L


# ---------------------------------------------------------------- HTML
def flag_svg(which):
    if which == "qa":
        band = " ".join(f"{px:.2f},{py:.2f}" for px, py in qatar_band(0, 0, 720, 480))
        return (f'<svg viewBox="0 0 720 480" preserveAspectRatio="none"><rect width="720" height="480" fill="{COLORS["QatarMaroon"][0]}"/>'
                f'<polygon points="{band}" fill="#fff"/></svg>')
    d = []
    for sp in iraq_flag_paths():
        a0 = sp[0][0]
        s = f"M{a0[0]:.3f} {a0[1]:.3f}"
        n = len(sp)
        for i in range(1, n + 1):
            prev, cur = sp[i - 1], sp[i % n]
            s += f"C{prev[2][0]:.3f} {prev[2][1]:.3f} {cur[1][0]:.3f} {cur[1][1]:.3f} {cur[0][0]:.3f} {cur[0][1]:.3f}"
        d.append(s + "Z")
    c = {k: COLORS[k][0] for k in ("IraqRed", "IraqBlack", "IraqGreen")}
    return (f'<svg viewBox="0 0 720 480" preserveAspectRatio="none"><rect width="720" height="160" fill="{c["IraqRed"]}"/>'
            f'<rect y="160" width="720" height="160" fill="#fff"/><rect y="320" width="720" height="160" fill="{c["IraqBlack"]}"/>'
            f'<path d="{"".join(d)}" fill="{c["IraqGreen"]}"/></svg>')


def build_html(items):
    css_styles = []
    for n, (wt, size, lead, col, align, dr, trk, tint) in STYLES.items():
        color = COLORS[col][0]
        op = f"opacity:{tint / 100};" if tint < 100 else ""
        css_styles.append(f".s-{n}{{font-weight:{wt};font-size:{size}pt;line-height:{lead}pt;color:{color};"
                          f"text-align:{align};direction:{dr};letter-spacing:{trk / 1000}em;{op}}}")
    els = []
    for it in items:
        box = f"left:{it.get('x', 0)}mm;top:{it.get('y', 0)}mm;width:{it.get('w', 0)}mm;height:{it.get('h', 0)}mm;"
        if it["kind"] == "rect":
            st = box + f"background:{COLORS[it['fill']][0] if it.get('fill') else 'transparent'};"
            if it.get("stroke"):
                st += f"box-shadow:inset 0 0 0 {it['sw']}pt {COLORS[it['stroke']][0]};"
            if it.get("radius"):
                st += f"border-radius:{it['radius']}mm;"
            els.append(f'<div class="r" style="{st}"></div>')
        elif it["kind"] == "poly":
            pts = " ".join(f"{x:.3f},{y:.3f}" for x, y in it["pts"])
            els.append(f'<svg class="r" style="left:0;top:0;width:{W}mm;height:{H}mm;overflow:visible" viewBox="0 0 {W} {H}">'
                       f'<polygon points="{pts}" fill="none" stroke="{COLORS[it["stroke"]][0]}" stroke-width="{it["sw"] / MM:.3f}"/></svg>')
        elif it["kind"] == "flag":
            els.append(f'<div class="r flag" style="{box}">{flag_svg(it["which"])}</div>')
        elif it["kind"] == "text":
            st = box
            if it.get("rotate"):
                st += f"transform:rotate({it['rotate']}deg);"
            va = "center" if it.get("valign") == "center" else "flex-start"
            paras = "".join(f'<p class="s-{s}">{escape(t)}</p>' for s, t in it["paras"])
            els.append(f'<div class="t" style="{st}justify-content:{va}">{paras}</div>')
    fonts = "".join(
        f"@font-face{{font-family:'IBM Plex Sans Arabic';font-weight:{wt};src:url('../assets/fonts/IBMPlexSansArabic-{nm}.ttf')}}"
        for wt, nm in WEIGHTS.items())
    return f"""<!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8">
<title>Agenda Draft</title>
<style>
{fonts}
@page{{size:210mm 297mm;margin:0}}
*{{margin:0;padding:0;box-sizing:border-box}}
html,body{{background:#fff}}
.page{{position:relative;width:{W}mm;height:{H}mm;overflow:hidden;background:#fff;font-family:'IBM Plex Sans Arabic',sans-serif}}
.r,.t{{position:absolute}}
.t{{display:flex;flex-direction:column}}
.flag{{box-shadow:0 0 0 .25pt {COLORS['Line'][0]}}}
.flag svg{{display:block;width:100%;height:100%}}
{''.join(css_styles)}
</style></head><body><div class="page">
{chr(10).join(els)}
</div></body></html>"""


# ---------------------------------------------------------------- IDML
def f(v):
    return f"{v:.4f}".rstrip("0").rstrip(".")


def sp(x, y):
    """page mm -> spread pt (page sits at x 0..W, y centred on the spread)."""
    return f(x * MM), f(y * MM - H * MM / 2)


def path_xml(subpaths, to_xy=sp):
    out = ["<Properties><PathGeometry>"]
    for pts in subpaths:
        out.append('<GeometryPathType PathOpen="false"><PathPointArray>')
        for a, l, r in pts:
            ax, ay = to_xy(*a); lx, ly = to_xy(*l); rx, ry = to_xy(*r)
            out.append(f'<PathPointType Anchor="{ax} {ay}" LeftDirection="{lx} {ly}" RightDirection="{rx} {ry}"/>')
        out.append("</PathPointArray></GeometryPathType>")
    out.append("</PathGeometry></Properties>")
    return "".join(out)


def corners(pts):
    return [[p, p, p] for p in pts]


def rect_pts(x, y, w, h):
    return corners([(x, y), (x, y + h), (x + w, y + h), (x + w, y)])


class Ids:
    def __init__(self):
        self.n = 0x100

    def __call__(self, p="u"):
        self.n += 1
        return f"{p}{self.n:x}"


def build_idml(items, path):
    uid = Ids()
    layer = "uLayer1"
    spread_items, stories = [], []

    def rect_xml(x, y, w, h, fill=None, stroke=None, sw=0, radius=0, name="", content="Unassigned"):
        corner = ""
        if radius:
            r = f(radius * MM)
            corner = "".join(f' {c}CornerOption="RoundedCorner" {c}CornerRadius="{r}"'
                             for c in ("TopLeft", "TopRight", "BottomLeft", "BottomRight"))
        return (f'<Rectangle Self="{uid()}" Name="{escape(name, {chr(34): "&quot;"})}" ContentType="{content}" ItemLayer="{layer}" Locked="false" '
                f'FillColor="{"Color/" + fill if fill else "Swatch/None"}" StrokeColor="{"Color/" + stroke if stroke else "Swatch/None"}" '
                f'StrokeWeight="{f(sw)}" StrokeAlignment="InsideAlignment"{corner} ItemTransform="1 0 0 1 0 0">'
                f'{path_xml([rect_pts(x, y, w, h)])}</Rectangle>')

    def poly_xml(subpaths, fill=None, stroke=None, sw=0, name=""):
        return (f'<Polygon Self="{uid()}" Name="{escape(name)}" ContentType="Unassigned" ItemLayer="{layer}" Locked="false" '
                f'FillColor="{"Color/" + fill if fill else "Swatch/None"}" StrokeColor="{"Color/" + stroke if stroke else "Swatch/None"}" '
                f'StrokeWeight="{f(sw)}" ItemTransform="1 0 0 1 0 0">{path_xml(subpaths)}</Polygon>')

    for it in items:
        k = it["kind"]
        if k == "rect":
            spread_items.append(rect_xml(it["x"], it["y"], it["w"], it["h"], it.get("fill"), it.get("stroke"),
                                         it.get("sw", 0), it.get("radius", 0), it.get("name", "")))
        elif k == "poly":
            spread_items.append(poly_xml([corners(it["pts"])], stroke=it["stroke"], sw=it["sw"], name=it.get("name", "")))
        elif k == "flag":
            x, y, w, h = it["x"], it["y"], it["w"], it["h"]
            if it["which"] == "qa":
                spread_items.append(rect_xml(x, y, w, h, "QatarMaroon", name="Flag Qatar – field"))
                spread_items.append(poly_xml([corners(qatar_band(x, y, w, h))], fill="Paper", name="Flag Qatar – band"))
            else:
                for i, c in enumerate(("IraqRed", "Paper", "IraqBlack")):
                    spread_items.append(rect_xml(x, y + i * h / 3, w, h / 3, c, name="Flag Iraq – stripe"))
                scale = (w / 720, h / 480)
                subs = [[[(x + px * scale[0], y + py * scale[1]) for px, py in pt] for pt in s] for s in iraq_flag_paths()]
                spread_items.append(poly_xml(subs, fill="IraqGreen", name="Flag Iraq – Takbir"))
            spread_items.append(rect_xml(x, y, w, h, stroke="Line", sw=0.25, name=it["name"] + " – keyline"))
        elif k == "text":
            st_id, tf_id = uid("st"), uid("tf")
            x, y, w, h = it["x"], it["y"], it["w"], it["h"]
            if it.get("rotate"):
                a = math.radians(it["rotate"])
                cx, cy = sp(x + w / 2, y + h / 2)
                xf = f"{f(math.cos(a))} {f(math.sin(a))} {f(-math.sin(a))} {f(math.cos(a))} {cx} {cy}"
                geo = path_xml([rect_pts(-w / 2, -h / 2, w, h)], to_xy=lambda px, py: (f(px * MM), f(py * MM)))
            else:
                xf = "1 0 0 1 0 0"
                geo = path_xml([rect_pts(x, y, w, h)])
            vj = "CenterAlign" if it.get("valign") == "center" else "TopAlign"
            spread_items.append(
                f'<TextFrame Self="{tf_id}" Name="{escape(it.get("name", ""))}" ParentStory="{st_id}" PreviousTextFrame="n" NextTextFrame="n" '
                f'ContentType="TextType" ItemLayer="{layer}" Locked="false" FillColor="Swatch/None" StrokeColor="Swatch/None" '
                f'StrokeWeight="0" ItemTransform="{xf}">{geo}'
                f'<TextFramePreference TextColumnCount="1" TextColumnFixedWidth="{f(w * MM)}" VerticalJustification="{vj}" '
                f'FirstBaselineOffset="AscentOffset" AutoSizingType="Off"/></TextFrame>')
            paras = []
            for i, (style, t) in enumerate(it["paras"]):
                br = "<Br/>" if i < len(it["paras"]) - 1 else ""
                paras.append(f'<ParagraphStyleRange AppliedParagraphStyle="ParagraphStyle/{style}">'
                             f'<CharacterStyleRange AppliedCharacterStyle="CharacterStyle/$ID/[No character style]">'
                             f'<Content>{escape(t)}</Content>{br}</CharacterStyleRange></ParagraphStyleRange>')
            direction = "RightToLeftDirection" if STYLES[it["paras"][0][0]][5] == "rtl" else "LeftToRightDirection"
            stories.append((st_id, f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<idPkg:Story xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" DOMVersion="13.0">
<Story Self="{st_id}" AppliedTOCStyle="n" TrackChanges="false" StoryTitle="$ID/" AppliedNamedGrid="n">
<StoryPreference OpticalMarginAlignment="false" OpticalMarginSize="12" FrameType="TextFrameType" StoryOrientation="Horizontal" StoryDirection="{direction}"/>
{"".join(paras)}
</Story>
</idPkg:Story>'''))

    pw, ph = f(W * MM), f(H * MM)
    m = f(16 * MM)
    margins = f'<MarginPreference ColumnCount="1" ColumnGutter="12" Top="{m}" Bottom="{m}" Left="{m}" Right="{m}" ColumnDirection="Horizontal"/>'
    page_xf = f"1 0 0 1 0 {f(-H * MM / 2)}"
    ns = 'xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" DOMVersion="13.0"'
    head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'

    spread = (head + f'<idPkg:Spread {ns}>\n<Spread Self="uSpread1" PageCount="1" BindingLocation="0" AllowPageShuffle="true" '
              f'ItemTransform="1 0 0 1 0 0" ShowMasterItems="true" FlattenerOverride="Default">\n'
              f'<Page Self="uPage1" Name="1" AppliedMaster="uMaster1" GeometricBounds="0 0 {ph} {pw}" ItemTransform="{page_xf}" '
              f'MasterPageTransform="1 0 0 1 0 0" OverrideList="">{margins}</Page>\n'
              + "\n".join(spread_items) + "\n</Spread>\n</idPkg:Spread>")
    master = (head + f'<idPkg:MasterSpread {ns}>\n<MasterSpread Self="uMaster1" Name="A-Parent" NamePrefix="A" BaseName="Parent" '
              f'ShowMasterItems="true" PageCount="1" ItemTransform="1 0 0 1 0 0">\n'
              f'<Page Self="uMasterPage1" Name="A" AppliedMaster="n" GeometricBounds="0 0 {ph} {pw}" ItemTransform="{page_xf}" '
              f'MasterPageTransform="1 0 0 1 0 0" OverrideList="">{margins}</Page>\n</MasterSpread>\n</idPkg:MasterSpread>')

    colors = [
        '<Color Self="Color/Black" Model="Process" Space="CMYK" ColorValue="0 0 0 100" ColorOverride="Specialblack" '
        'AlternateSpace="NoAlternateColor" AlternateColorValue="" Name="Black" ColorEditable="false" ColorRemovable="false" Visible="true" SwatchCreatorID="7937"/>',
        '<Color Self="Color/Registration" Model="Registration" Space="CMYK" ColorValue="100 100 100 100" ColorOverride="Specialregistration" '
        'AlternateSpace="NoAlternateColor" AlternateColorValue="" Name="Registration" ColorEditable="false" ColorRemovable="false" Visible="true" SwatchCreatorID="7937"/>',
        '<Color Self="Color/Paper" Model="Process" Space="CMYK" ColorValue="0 0 0 0" ColorOverride="Specialpaper" '
        'AlternateSpace="NoAlternateColor" AlternateColorValue="" Name="Paper" ColorEditable="true" ColorRemovable="false" Visible="true" SwatchCreatorID="7937"/>',
    ]
    for n, (_, cmyk) in COLORS.items():
        if n == "Paper":
            continue
        colors.append(f'<Color Self="Color/{n}" Model="Process" Space="CMYK" ColorValue="{cmyk}" ColorOverride="Normal" '
                      f'AlternateSpace="NoAlternateColor" AlternateColorValue="" Name="{n}" ColorEditable="true" ColorRemovable="true" Visible="true" SwatchCreatorID="7937"/>')
    graphic = (head + f'<idPkg:Graphic {ns}>\n' + "\n".join(colors) +
               '\n<Swatch Self="Swatch/None" Name="None" ColorEditable="false" ColorRemovable="false" Visible="true" SwatchCreatorID="7937"/>'
               '\n<StrokeStyle Self="StrokeStyle/$ID/Solid" Name="$ID/Solid"/>\n</idPkg:Graphic>')

    pstyles = []
    for n, (wt, size, lead, col, align, dr, trk, tint) in STYLES.items():
        just = {"center": "CenterAlign", "right": "RightAlign", "left": "LeftAlign"}[align]
        pdir = "RightToLeftDirection" if dr == "rtl" else "LeftToRightDirection"
        pstyles.append(
            f'<ParagraphStyle Self="ParagraphStyle/{n}" Name="{n}" Imported="false" NextStyle="ParagraphStyle/{n}" '
            f'FontStyle="{WEIGHTS[wt]}" PointSize="{f(size)}" FillColor="Color/{col}" FillTint="{tint if tint < 100 else -1}" '
            f'Justification="{just}" ParagraphDirection="{pdir}" Composer="$ID/HL Composer Optyca" Tracking="{trk}" Hyphenation="false">'
            f'<Properties><BasedOn type="object">ParagraphStyle/$ID/NormalParagraphStyle</BasedOn>'
            f'<AppliedFont type="string">IBM Plex Sans Arabic</AppliedFont><Leading type="unit">{f(lead)}</Leading></Properties>'
            f'</ParagraphStyle>')
    styles = (head + f'<idPkg:Styles {ns}>\n'
              '<RootCharacterStyleGroup Self="uCharRoot">\n'
              '<CharacterStyle Self="CharacterStyle/$ID/[No character style]" Imported="false" Name="$ID/[No character style]"/>\n'
              '</RootCharacterStyleGroup>\n'
              '<RootParagraphStyleGroup Self="uParaRoot">\n'
              '<ParagraphStyle Self="ParagraphStyle/$ID/[No paragraph style]" Name="$ID/[No paragraph style]" Imported="false"/>\n'
              '<ParagraphStyle Self="ParagraphStyle/$ID/NormalParagraphStyle" Name="$ID/NormalParagraphStyle" Imported="false" '
              'NextStyle="ParagraphStyle/$ID/NormalParagraphStyle" FontStyle="Regular" PointSize="11" '
              'ParagraphDirection="RightToLeftDirection" Composer="$ID/HL Composer Optyca">'
              '<Properties><BasedOn type="string">$ID/[No paragraph style]</BasedOn>'
              '<AppliedFont type="string">IBM Plex Sans Arabic</AppliedFont></Properties></ParagraphStyle>\n'
              + "\n".join(pstyles) + "\n</RootParagraphStyleGroup>\n</idPkg:Styles>")

    b = f(BLEED * MM)
    prefs = (head + f'<idPkg:Preferences {ns}>\n'
             f'<DocumentPreference PageHeight="{ph}" PageWidth="{pw}" PagesPerDocument="1" FacingPages="false" '
             f'DocumentBleedTopOffset="{b}" DocumentBleedBottomOffset="{b}" DocumentBleedInsideOrLeftOffset="{b}" '
             f'DocumentBleedOutsideOrRightOffset="{b}" DocumentBleedUniformSize="true" AllowPageShuffle="true" '
             f'PageBinding="LeftToRight" ColumnDirection="Horizontal" Intent="PrintIntent"/>\n'
             '<ViewPreference HorizontalMeasurementUnits="Millimeters" VerticalMeasurementUnits="Millimeters"/>\n'
             '</idPkg:Preferences>')

    story_ids = " ".join(s for s, _ in stories)
    designmap = (head + '<?aid style="50" type="document" readerVersion="6.0" featureSet="257" product="13.1(201)" ?>\n'
                 f'<Document xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" DOMVersion="13.0" Self="d" '
                 f'StoryList="{story_ids}" ZeroPoint="0 0" ActiveLayer="{layer}" CMYKProfile="Coated FOGRA39 (ISO 12647-2:2004)" '
                 f'RGBProfile="sRGB IEC61966-2.1" SolidColorIntent="UseColorSettings" AfterBlendingIntent="UseColorSettings" '
                 f'DefaultImageIntent="UseColorSettings" RGBPolicy="PreserveEmbeddedProfiles" CMYKPolicy="CombinationOfPreserveAndSafeCmyk" '
                 f'AccurateLABSpots="false">\n'
                 '<idPkg:Graphic src="Resources/Graphic.xml"/>\n'
                 '<idPkg:Styles src="Resources/Styles.xml"/>\n'
                 '<idPkg:Preferences src="Resources/Preferences.xml"/>\n'
                 f'<Layer Self="{layer}" Name="Design" Visible="true" Locked="false" IgnoreWrap="false" ShowGuides="true" '
                 'LockGuides="false" UI="true" Expendable="true" Printable="true">'
                 '<Properties><LayerColor type="enumeration">LightBlue</LayerColor></Properties></Layer>\n'
                 '<idPkg:MasterSpread src="MasterSpreads/MasterSpread_uMaster1.xml"/>\n'
                 '<idPkg:Spread src="Spreads/Spread_uSpread1.xml"/>\n'
                 + "".join(f'<idPkg:Story src="Stories/Story_{s}.xml"/>\n' for s, _ in stories)
                 + '</Document>')

    container = (head + '<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">'
                 '<rootfiles><rootfile full-path="designmap.xml" media-type="text/xml"/></rootfiles></container>')

    with zipfile.ZipFile(path, "w") as z:
        z.writestr(zipfile.ZipInfo("mimetype"), "application/vnd.adobe.indesign-idml-package", compress_type=zipfile.ZIP_STORED)
        files = {
            "designmap.xml": designmap,
            "META-INF/container.xml": container,
            "Resources/Graphic.xml": graphic,
            "Resources/Styles.xml": styles,
            "Resources/Preferences.xml": prefs,
            "MasterSpreads/MasterSpread_uMaster1.xml": master,
            "Spreads/Spread_uSpread1.xml": spread,
        }
        files.update({f"Stories/Story_{s}.xml": x for s, x in stories})
        for name, data in files.items():
            z.writestr(name, data, compress_type=zipfile.ZIP_DEFLATED)


if __name__ == "__main__":
    OUT.mkdir(exist_ok=True)
    items = layout()
    (OUT / "agenda.html").write_text(build_html(items), encoding="utf-8")
    build_idml(items, OUT / "Agenda_Draft.idml")
    print("wrote", OUT / "agenda.html", "and", OUT / "Agenda_Draft.idml")
