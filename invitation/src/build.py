#!/usr/bin/env python3
"""IUSBC opening-ceremony invitation (A5 landscape).

One layout spec (mm) -> HTML (-> PDF/PNG via Chromium)  and  IDML (InDesign).
Run:  python3 build.py   (needs: qrcode, PIL; node+playwright for the PDF/PNG step)
"""
import base64, os, re, shutil, subprocess, sys, zipfile
from xml.sax.saxutils import escape

import qrcode

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.abspath(os.path.join(HERE, ".."))
TEMPLATE = os.environ.get("IDML_TEMPLATE", os.path.join(HERE, "idml_template"))
LOGO = os.path.join(HERE, "iusbc_logo_clean.png")
MAPS_URL = "https://maps.app.goo.gl/6UkNFgEYdHkM9V8R8"
NAME = "IUSBC_Invitation"

# ----------------------------------------------------------------- page / colours
W, H = 210.0, 148.0           # mm  (A5 landscape)
BLEED = 3.0
PT = 72 / 25.4                # mm -> pt
NAVY, GOLD, GOLD_L = (11, 42, 85), (205, 164, 73), (232, 203, 124)
IVORY, WHITE = (245, 238, 220), (255, 255, 255)
COLORS = {"Navy": NAVY, "Gold": GOLD, "GoldLight": GOLD_L, "Ivory": IVORY, "White": WHITE}
hexc = lambda c: "#%02x%02x%02x" % c

PANEL_X = 60.0                # navy panel starts here
CX = (PANEL_X + W) / 2        # centre of text area (135)

# ----------------------------------------------------------------- shapes
shapes = []   # dicts: kind=rect|line|poly ; coords mm ; fill/stroke colour names ; stroke pt ; opacity %

def rect(x, y, w, h, fill=None, stroke=None, sw=0.5, op=100):
    shapes.append(dict(kind="rect", x=x, y=y, w=w, h=h, fill=fill, stroke=stroke, sw=sw, op=op))

def line(x1, y1, x2, y2, stroke="Gold", sw=0.5, op=100):
    shapes.append(dict(kind="line", pts=[(x1, y1), (x2, y2)], stroke=stroke, sw=sw, op=op))

def poly(paths, fill=None, stroke=None, sw=0.5, op=100):
    """paths: list of closed point lists (compound path)."""
    shapes.append(dict(kind="poly", paths=paths, fill=fill, stroke=stroke, sw=sw, op=op))

def diamond(cx, cy, r, fill="Gold"):
    poly([[(cx, cy - r), (cx + r, cy), (cx, cy + r), (cx - r, cy)]], fill=fill)

# navy panel (+ bleed on top / bottom / right) and gold edge strip
rect(PANEL_X, -BLEED, W - PANEL_X + BLEED, H + 2 * BLEED, fill="Navy")
rect(PANEL_X, -BLEED, 1.1, H + 2 * BLEED, fill="Gold")

# subtle 8-point-star lattice (khatam) on the panel
TILES, S = 12, 11.0
px0, py0 = CX - TILES * S / 2 + 3.0, 8.0 + 0.0
px0 = (PANEL_X + 1.1 + W) / 2 - TILES * S / 2
R = 0.62 * S
stars = []
for i in range(TILES):
    for j in range(TILES):
        cx, cy = px0 + (i + .5) * S, py0 + (j + .5) * S
        q = R * 0.70711
        stars.append([(cx - q, cy - q), (cx + q, cy - q), (cx + q, cy + q), (cx - q, cy + q)])
        stars.append([(cx, cy - R), (cx + R, cy), (cx, cy + R), (cx - R, cy)])
poly(stars, stroke="Gold", sw=0.45, op=11)

# double gold frame
FX0, FX1, FY0, FY1 = PANEL_X + 6.0, W - 5.0, 5.0, H - 5.0
rect(FX0, FY0, FX1 - FX0, FY1 - FY0, stroke="Gold", sw=0.9)
rect(FX0 + 1.4, FY0 + 1.4, FX1 - FX0 - 2.8, FY1 - FY0 - 2.8, stroke="Gold", sw=0.35)
for (dx, dy) in [(FX0, FY0), (FX1, FY0), (FX0, FY1), (FX1, FY1)]:
    diamond(dx, dy, 1.9)

# header ornament  — — ◆ دعوة حضور ◆ — —
HY = 15.8
line(FX0 + 12, HY, CX - 24, HY, sw=0.6)
line(CX + 24, HY, FX1 - 12, HY, sw=0.6)
diamond(CX - 26.5, HY, 1.2)
diamond(CX + 26.5, HY, 1.2)

# divider under the date sentence
DY = 73.4
line(CX - 30, DY, CX - 4, DY, sw=0.5)
line(CX + 4, DY, CX + 30, DY, sw=0.5)
diamond(CX, DY, 1.3)

# info row (date | time | place)
IX0, IX1 = FX0 + 9, FX1 - 9
IT, IB = 95.0, 119.5
COL1 = (IX1 - 41, IX1)            # right  : التاريخ
COL2 = (IX1 - 41 - 30, IX1 - 41)  # middle : الوقت
COL3 = (IX0, IX1 - 71)            # left   : المكان
line(IX0, IT, IX1, IT, sw=0.6)
line(IX0, IB, IX1, IB, sw=0.6)
line(COL1[0], IT + 4, COL1[0], IB - 4, sw=0.4)
line(COL2[0], IT + 4, COL2[0], IB - 4, sw=0.4)

# left white panel : divider, QR frame
LX = PANEL_X / 2
line(LX - 16, 68, LX - 3, 68, sw=0.5); line(LX + 3, 68, LX + 16, 68, sw=0.5); diamond(LX, 68, 1.1)
QR_BOX = 32.0
rect(LX - QR_BOX / 2, 76, QR_BOX, QR_BOX, stroke="Gold", sw=0.8)
qr = qrcode.QRCode(error_correction=qrcode.constants.ERROR_CORRECT_M, box_size=1, border=0)
qr.add_data(MAPS_URL); qr.make(fit=True)
M = qr.get_matrix(); n = len(M)
QR_MOD = 25.0 / n
QRX, QRY = LX - n * QR_MOD / 2, 76 + QR_BOX / 2 - n * QR_MOD / 2
qr_paths = []
for r, row in enumerate(M):          # merge horizontal runs into rectangles
    c = 0
    while c < n:
        if row[c]:
            c0 = c
            while c < n and row[c]: c += 1
            x0, x1 = QRX + c0 * QR_MOD, QRX + c * QR_MOD
            y0, y1 = QRY + r * QR_MOD, QRY + (r + 1) * QR_MOD
            qr_paths.append([(x0, y0), (x1, y0), (x1, y1), (x0, y1)])
        else:
            c += 1
poly(qr_paths, fill="Navy")

LOGO_BOX = (3.0, 7.0, 54.0)   # x, y, size (mm)

# ----------------------------------------------------------------- text blocks
# baseline = first-line baseline (mm); size/lead in pt; lines are forced breaks.
texts = []
def T(id, lines, font, bold, size, lead, color, baseline, x=None, w=124.0, rtl=True, link=None):
    texts.append(dict(id=id, lines=lines, font=font, bold=bold, size=size, lead=lead,
                      color=color, baseline=baseline, x=(CX - w / 2) if x is None else x, w=w, rtl=rtl, link=link))

T("label", ["دعوة حضور"], "Amiri", True, 16, 20, "Gold", 20.0, w=44)
T("greet", ["يهديكم مجلس الأعمال العراقي–الأمريكي أطيب تحياته،", "ويتشرّف بدعوتكم لحضور"],
  "Amiri", False, 10.5, 16, "Ivory", 29.6)
T("t1", ["حفل افتتاح مقر"], "Amiri", True, 21, 26, "White", 45.5)
T("t2", ["مجلس الأعمال العراقي–الأمريكي"], "Amiri", True, 25.5, 30, "GoldLight", 57.8)
T("date", ["وذلك يوم الأربعاء الموافق ٢١ تشرين الأول ٢٠٢٦"], "Amiri", False, 11, 16, "Ivory", 67.8)
T("desc", ["يمثّل افتتاح المقر خطوةً نحو تعزيز التعاون الاقتصادي والتجاري",
           "بين العراق والولايات المتحدة الأمريكية، وتوسيع التواصل",
           "والشراكات بين مجتمعي الأعمال في البلدين."], "Amiri", False, 9.4, 13.4, "Ivory", 80.4)
LBL_B = 101.2
for cid, (a, b), t in (("c1", COL1, "التاريخ"), ("c2", COL2, "الوقت"), ("c3", COL3, "المكان")):
    T("lbl_" + cid, [t], "Amiri", True, 9.5, 12, "Gold", LBL_B, x=a, w=b - a)
T("v1", ["الأربعاء", "٢١ تشرين الأول ٢٠٢٦"], "Amiri", True, 10.5, 13.6, "White", 109.6, x=COL1[0], w=COL1[1] - COL1[0])
T("v2", ["٦:٣٠ مساءً"], "Amiri", True, 11.5, 15.5, "White", 112.6, x=COL2[0], w=COL2[1] - COL2[0])
T("v3", ["المنصور – قرب الأقواس", "من جهة سيد الحبيب", "بناية أديداس"], "Amiri", True, 10, 13,
  "White", 107.6, x=COL3[0], w=COL3[1] - COL3[0], link=MAPS_URL)
T("close", ["إن حضوركم الكريم يُسعدنا ويُضفي على هذه المناسبة", "مزيداً من الاعتزاز والتقدير.",
            "وتفضّلوا بقبول فائق الاحترام والتقدير."], "Amiri", False, 9.2, 13, "Ivory", 127.4)
# white panel
T("qr1", ["امسح الرمز", "للوصول إلى الموقع"], "Amiri", True, 9, 12, "Navy", 116.6, x=2, w=PANEL_X - 4)
T("web", ["www.iusbc.com"], "Cairo", True, 8.5, 12, "Navy", 136.2, x=2, w=PANEL_X - 4, rtl=False)

# ----------------------------------------------------------------- HTML (-> PDF / PNG)
def svg_shapes():
    out = []
    for s in shapes:
        fill = hexc(COLORS[s["fill"]]) if s.get("fill") else "none"
        stroke = hexc(COLORS[s["stroke"]]) if s.get("stroke") else "none"
        sw = s.get("sw", 0.5) * 25.4 / 72
        op = s["op"] / 100
        attrs = f'fill="{fill}" stroke="{stroke}" stroke-width="{sw:.4f}" stroke-linejoin="miter"'
        if op < 1: attrs += f' opacity="{op}"'
        if s["kind"] == "rect":
            out.append(f'<rect x="{s["x"]:.3f}" y="{s["y"]:.3f}" width="{s["w"]:.3f}" height="{s["h"]:.3f}" {attrs}/>')
        elif s["kind"] == "line":
            (x1, y1), (x2, y2) = s["pts"]
            out.append(f'<line x1="{x1:.3f}" y1="{y1:.3f}" x2="{x2:.3f}" y2="{y2:.3f}" {attrs}/>')
        else:
            d = " ".join("M" + " L".join(f"{x:.3f} {y:.3f}" for x, y in p) + " Z" for p in s["paths"])
            out.append(f'<path d="{d}" {attrs}/>')
    return "\n".join(out)

def html():
    blocks = []
    for t in texts:
        wt = 700 if t["bold"] else 400
        lines = "<br>".join(escape(l) for l in t["lines"])
        inner = f'<span class="mk"></span>{lines}'
        style = (f'left:{t["x"]:.3f}mm;width:{t["w"]:.3f}mm;font-family:\'{t["font"]}\';font-weight:{wt};'
                 f'font-size:{t["size"]}pt;line-height:{t["lead"]}pt;color:{hexc(COLORS[t["color"]])};'
                 f'direction:{"rtl" if t["rtl"] else "ltr"};')
        blocks.append(f'<div class="blk" data-bl="{t["baseline"]}" style="{style}">{inner}</div>')
    lx, ly, ls = LOGO_BOX
    links = (f'<a class="lnk" href="{MAPS_URL}" style="left:{COL3[0]:.2f}mm;top:{IT:.2f}mm;'
             f'width:{COL3[1]-COL3[0]:.2f}mm;height:{IB-IT:.2f}mm"></a>'
             f'<a class="lnk" href="{MAPS_URL}" style="left:{LX-QR_BOX/2:.2f}mm;top:76mm;width:{QR_BOX}mm;height:{QR_BOX}mm"></a>')
    return f"""<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
<title>دعوة حضور – افتتاح مقر مجلس الأعمال العراقي–الأمريكي</title>
<style>
@font-face{{font-family:'Amiri';font-weight:400;src:url('../Fonts/Amiri-Regular.ttf')}}
@font-face{{font-family:'Amiri';font-weight:700;src:url('../Fonts/Amiri-Bold.ttf')}}
@font-face{{font-family:'Cairo';font-weight:400;src:url('../Fonts/Cairo-Regular.ttf')}}
@font-face{{font-family:'Cairo';font-weight:700;src:url('../Fonts/Cairo-Bold.ttf')}}
@page{{size:{W}mm {H}mm;margin:0}}
*{{box-sizing:border-box;margin:0;padding:0}}
html,body{{width:{W}mm;height:{H}mm;background:#fff;overflow:hidden}}
#page{{position:relative;width:{W}mm;height:{H}mm;overflow:hidden;background:#fff}}
svg.bg{{position:absolute;left:0;top:0;width:{W}mm;height:{H}mm}}
img.logo{{position:absolute;left:{lx}mm;top:{ly}mm;width:{ls}mm;height:{ls}mm}}
.blk{{position:absolute;text-align:center;white-space:nowrap;font-kerning:normal;font-feature-settings:"liga","calt","kern"}}
.mk{{display:inline-block;width:0;height:0}}
.lnk{{position:absolute;display:block}}
</style></head><body><div id="page">
<svg class="bg" viewBox="0 0 {W} {H}" xmlns="http://www.w3.org/2000/svg">
{svg_shapes()}
</svg>
<img class="logo" src="iusbc_logo_clean.png" alt="IUSBC">
{chr(10).join(blocks)}
{links}
</div>
<script>
document.fonts.ready.then(()=>{{
  const mm=96/25.4;
  document.querySelectorAll('.blk').forEach(b=>{{
    const mk=b.querySelector('.mk'); b.style.top='0px';
    const base=mk.getBoundingClientRect().bottom - b.getBoundingClientRect().top;
    b.style.top=(parseFloat(b.dataset.bl)*mm-base)+'px';
  }});
  document.body.setAttribute('data-ready','1');
}});
</script></body></html>"""

# ----------------------------------------------------------------- IDML
class Ids:
    def __init__(self): self.n = 0x2000
    def __call__(self):
        self.n += 1
        return "u%x" % self.n
ids = Ids()
f2 = lambda v: ("%.4f" % v).rstrip("0").rstrip(".")
LAYER = "ub8"
PW, PH = W * PT, H * PT

def path_xml(paths, open_=False):
    g = []
    for p in paths:
        pts = "".join(f'<PathPointType Anchor="{f2(x*PT-PW/2)} {f2(y*PT-PH/2)}" LeftDirection="{f2(x*PT-PW/2)} {f2(y*PT-PH/2)}" RightDirection="{f2(x*PT-PW/2)} {f2(y*PT-PH/2)}" />' for x, y in p)
        g.append(f'<GeometryPathType PathOpen="{"true" if open_ else "false"}"><PathPointArray>{pts}</PathPointArray></GeometryPathType>')
    return "<Properties><PathGeometry>" + "".join(g) + "</PathGeometry></Properties>"

def item_attrs(s):
    fill = f"Color/{s['fill']}" if s.get("fill") else "Swatch/None"
    stroke = f"Color/{s['stroke']}" if s.get("stroke") else "Swatch/None"
    sw = f2(s.get("sw", 0.5)) if s.get("stroke") else "0"
    return (f'ItemLayer="{LAYER}" AppliedObjectStyle="ObjectStyle/$ID/[None]" ItemTransform="1 0 0 1 0 0" '
            f'FillColor="{fill}" StrokeColor="{stroke}" StrokeWeight="{sw}" Name="$ID/"')

def transp(s):
    return (f'<TransparencySetting><BlendingSetting Opacity="{s["op"]}" /></TransparencySetting>'
            if s["op"] < 100 else "")

def shape_xml(s):
    sid = ids()
    if s["kind"] == "rect":
        p = [[(s["x"], s["y"]), (s["x"] + s["w"], s["y"]), (s["x"] + s["w"], s["y"] + s["h"]), (s["x"], s["y"] + s["h"])]]
        return f'<Rectangle Self="{sid}" ContentType="Unassigned" {item_attrs(s)}>{path_xml(p)}{transp(s)}</Rectangle>'
    if s["kind"] == "line":
        return f'<GraphicLine Self="{sid}" {item_attrs(s)}>{path_xml([s["pts"]], True)}{transp(s)}</GraphicLine>'
    return f'<Polygon Self="{sid}" ContentType="Unassigned" {item_attrs(s)}>{path_xml(s["paths"])}{transp(s)}</Polygon>'

def logo_xml():
    lx, ly, ls = LOGO_BOX
    p = [[(lx, ly), (lx + ls, ly), (lx + ls, ly + ls), (lx, ly + ls)]]
    native = 1254 * 72 / 300.0
    sc = ls * PT / native
    data = base64.b64encode(open(LOGO, "rb").read()).decode()
    return (f'<Rectangle Self="{ids()}" ContentType="GraphicType" ItemLayer="{LAYER}" '
            f'AppliedObjectStyle="ObjectStyle/$ID/[Normal Graphics Frame]" ItemTransform="1 0 0 1 0 0" '
            f'FillColor="Swatch/None" StrokeColor="Swatch/None" StrokeWeight="0" Name="IUSBC logo">{path_xml(p)}'
            f'<Image Self="{ids()}" ItemTransform="{f2(sc)} 0 0 {f2(sc)} {f2(lx*PT-PW/2)} {f2(ly*PT-PH/2)}" '
            f'AppliedObjectStyle="ObjectStyle/$ID/[None]" Space="RGB" ActualPpi="300 300" '
            f'EffectivePpi="{f2(300/sc)} {f2(300/sc)}" ImageRenderingIntent="UseColorSettings" '
            f'ImageTypeName="$ID/Portable Network Graphics (PNG)">'
            f'<Properties><Profile type="string">$ID/Embedded</Profile>'
            f'<Contents><![CDATA[{data}]]></Contents>'
            f'<GraphicBounds Left="0" Top="0" Right="{f2(native)}" Bottom="{f2(native)}" /></Properties></Image></Rectangle>')

def text_xml(t):
    """returns (frame_xml, story_file_name, story_xml, story_id)"""
    sid, fid = ids(), ids()
    asc = t["size"]
    x0, x1 = t["x"] * PT, (t["x"] + t["w"]) * PT
    y0 = t["baseline"] * PT - asc
    y1 = y0 + len(t["lines"]) * t["lead"] + asc + t["size"]
    p = [[(x0 / PT, y0 / PT), (x1 / PT, y0 / PT), (x1 / PT, y1 / PT), (x0 / PT, y1 / PT)]]
    frame = (f'<TextFrame Self="{fid}" ParentStory="{sid}" PreviousTextFrame="n" NextTextFrame="n" ContentType="TextType" '
             f'ItemLayer="{LAYER}" AppliedObjectStyle="ObjectStyle/$ID/[Normal Text Frame]" ItemTransform="1 0 0 1 0 0" '
             f'FillColor="Swatch/None" StrokeColor="Swatch/None" StrokeWeight="0" Name="{t["id"]}">'
             f'{path_xml(p)}'
             f'<TextFramePreference TextColumnCount="1" TextColumnGutter="12" FirstBaselineOffset="FixedHeight" '
             f'MinimumFirstBaselineOffset="{f2(asc)}" VerticalJustification="TopAlign" IgnoreWrap="false" AutoSizingType="Off">'
             f'<Properties><InsetSpacing type="list"><ListItem type="unit">0</ListItem><ListItem type="unit">0</ListItem>'
             f'<ListItem type="unit">0</ListItem><ListItem type="unit">0</ListItem></InsetSpacing></Properties>'
             f'</TextFramePreference></TextFrame>')
    # story: gaps between lines are forced line breaks (U+2028) inside one paragraph
    d = "RightToLeftDirection" if t["rtl"] else "LeftToRightDirection"
    content = "&#x2028;".join(escape(l) for l in t["lines"])
    story = (f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
             f'<idPkg:Story xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" DOMVersion="20.0">'
             f'<Story Self="{sid}" AppliedTOCStyle="n" TrackChanges="false" StoryTitle="$ID/" AppliedNamedGrid="n">'
             f'<StoryPreference OpticalMarginAlignment="false" OpticalMarginSize="12" FrameType="TextFrameType" '
             f'StoryOrientation="Horizontal" StoryDirection="{d}" />'
             f'<InCopyExportOption IncludeGraphicProxies="true" IncludeAllResources="false" />'
             f'<ParagraphStyleRange AppliedParagraphStyle="ParagraphStyle/$ID/NormalParagraphStyle" '
             f'Justification="CenterAlign" ParagraphDirection="{d}" Hyphenation="false">'
             f'<CharacterStyleRange AppliedCharacterStyle="CharacterStyle/$ID/[No character style]" '
             f'FillColor="Color/{t["color"]}" FontStyle="{"Bold" if t["bold"] else "Regular"}" PointSize="{t["size"]}">'
             f'<Properties><Leading type="unit">{t["lead"]}</Leading><AppliedFont type="string">{t["font"]}</AppliedFont></Properties>'
             f'<Content>{content}</Content></CharacterStyleRange></ParagraphStyleRange></Story></idPkg:Story>')
    return frame, f"Stories/Story_{sid}.xml", story, sid

def build_idml(path):
    work = os.path.join(HERE, "_idml_work")
    shutil.rmtree(work, ignore_errors=True)
    shutil.copytree(TEMPLATE, work)
    rd = lambda p: open(os.path.join(work, p), encoding="utf-8").read()
    def wr(p, s):
        full = os.path.join(work, p); os.makedirs(os.path.dirname(full), exist_ok=True)
        open(full, "w", encoding="utf-8").write(s)

    # colours
    gfx = rd("Resources/Graphic.xml"); cols = ""; grp = ""
    for i, (nm, (r, g, b)) in enumerate(COLORS.items()):
        ref = f"u12ColorGroupSwatchx{i}"
        cols += (f'<Color Self="Color/{nm}" Model="Process" Space="RGB" ColorValue="{r} {g} {b}" ColorOverride="Normal" '
                 f'ConvertToHsb="false" AlternateSpace="NoAlternateColor" AlternateColorValue="" Name="{nm}" '
                 f'ColorEditable="true" ColorRemovable="true" Visible="true" SwatchCreatorID="7937" SwatchColorGroupReference="{ref}" />\n\t')
        grp += f'<ColorGroupSwatch Self="{ref}" SwatchItemRef="Color/{nm}" />\n\t\t'
    gfx = gfx.replace("<Ink Self=", cols + "<Ink Self=", 1)
    wr("Resources/Graphic.xml", gfx)

    # page size, bleed, print intent
    pref = rd("Resources/Preferences.xml")
    pref = re.sub(r'(<DocumentPreference )PageHeight="[^"]*" PageWidth="[^"]*"',
                  rf'\1PageHeight="{f2(PH)}" PageWidth="{f2(PW)}"', pref, count=1)
    b = f2(BLEED * PT)
    for k in ("DocumentBleedTopOffset", "DocumentBleedBottomOffset", "DocumentBleedInsideOrLeftOffset", "DocumentBleedOutsideOrRightOffset"):
        pref = re.sub(rf'{k}="[^"]*"', f'{k}="{b}"', pref, count=1)
    pref = pref.replace('Intent="WebIntent"', 'Intent="PrintIntent"', 1)
    wr("Resources/Preferences.xml", pref)

    # master spread page geometry
    mp = [f for f in os.listdir(os.path.join(work, "MasterSpreads"))][0]
    ms = rd("MasterSpreads/" + mp)
    ms = re.sub(r'GeometricBounds="[^"]*" ItemTransform="[^"]*"',
                f'GeometricBounds="0 0 {f2(PH)} {f2(PW)}" ItemTransform="1 0 0 1 {f2(-PW/2)} {f2(-PH/2)}"', ms, count=1)
    ms = re.sub(r'Top="36" Bottom="36" Left="36" Right="36" ColumnDirection="Horizontal" ColumnsPositions="[^"]*"',
                f'Top="14" Bottom="14" Left="14" Right="14" ColumnDirection="Horizontal" ColumnsPositions="0 {f2(PW-28)}"', ms, count=1)
    wr("MasterSpreads/" + mp, ms)

    # content
    items = [shape_xml(s) for s in shapes[:2]]       # navy panel + gold strip first (behind everything)
    items += [shape_xml(s) for s in shapes[2:]]
    items.append(logo_xml())
    stories = []
    for t in texts:
        frame, fn, sx, sid = text_xml(t)
        items.append(frame); wr(fn, sx); stories.append((fn, sid))
    spread_id, page_id = ids(), ids()
    spread = (f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
              f'<idPkg:Spread xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" DOMVersion="20.0">'
              f'<Spread Self="{spread_id}" FlattenerOverride="Default" AllowPageShuffle="true" ItemTransform="1 0 0 1 0 0" '
              f'ShowMasterItems="true" PageCount="1" BindingLocation="0" SpreadHidden="false">'
              f'<Page Self="{page_id}" TabOrder="" AppliedMaster="ub9" OverrideList="" MasterPageTransform="1 0 0 1 0 0" Name="1" '
              f'GeometricBounds="0 0 {f2(PH)} {f2(PW)}" ItemTransform="1 0 0 1 {f2(-PW/2)} {f2(-PH/2)}" '
              f'AppliedAlternateLayout="n" GridStartingPoint="TopOutside" UseMasterGrid="true">'
              f'<MarginPreference ColumnCount="1" ColumnGutter="12" Top="14" Bottom="14" Left="14" Right="14" '
              f'ColumnDirection="Horizontal" ColumnsPositions="0 {f2(PW-28)}" /></Page>'
              + "".join(items) + '</Spread></idPkg:Spread>')
    wr(f"Spreads/Spread_{spread_id}.xml", spread)

    # designmap
    dm = rd("designmap.xml")
    dm = dm.replace('Name="demo"', f'Name="{NAME}"', 1)
    dm = re.sub(r'StoryList="[^"]*"', 'StoryList="u98 ' + " ".join(s for _, s in stories) + '"', dm, count=1)
    dm = dm.replace('<idPkg:MasterSpread src="MasterSpreads/' + mp + '" />',
                    f'<idPkg:MasterSpread src="MasterSpreads/{mp}" />\n\t<idPkg:Spread src="Spreads/Spread_{spread_id}.xml" />', 1)
    dm = re.sub(r'(<Section [^>]*?)Length="\d+"', r'\1Length="1"', dm, count=1)
    dm = re.sub(r'PageStart="[^"]*"', f'PageStart="{page_id}"', dm, count=1)
    dm = re.sub(r'AlternateLayoutLength="\d+"', 'AlternateLayoutLength="1"', dm, count=1)
    dm = dm.replace("</ColorGroup>", grp + "</ColorGroup>", 1)
    dm = dm.replace('<idPkg:BackingStory src="XML/BackingStory.xml" />',
                    '<idPkg:BackingStory src="XML/BackingStory.xml" />\n\t' +
                    "\n\t".join(f'<idPkg:Story src="{fn}" />' for fn, _ in stories), 1)
    wr("designmap.xml", dm)

    # zip (mimetype first, stored)
    if os.path.exists(path): os.remove(path)
    with zipfile.ZipFile(path, "w") as z:
        z.write(os.path.join(work, "mimetype"), "mimetype", compress_type=zipfile.ZIP_STORED)
        for root, _, files in os.walk(work):
            for f in sorted(files):
                full = os.path.join(root, f); rel = os.path.relpath(full, work)
                if rel == "mimetype": continue
                z.write(full, rel, compress_type=zipfile.ZIP_DEFLATED)
    shutil.rmtree(work)

# ----------------------------------------------------------------- main
if __name__ == "__main__":
    shutil.copy(LOGO, os.path.join(HERE, "iusbc_logo.png")) if False else None
    open(os.path.join(HERE, "invitation.html"), "w", encoding="utf-8").write(html())
    build_idml(os.path.join(OUT, NAME + ".idml"))
    print("html + idml written")
