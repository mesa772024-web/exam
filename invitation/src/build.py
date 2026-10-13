#!/usr/bin/env python3
"""IUSBC opening-ceremony invitation (A5 landscape).

One layout spec (mm) -> HTML (-> PDF/PNG via Chromium)  and  IDML (InDesign).
Run:  python3 build.py && NODE_PATH=<global node_modules> node render.js
(needs: qrcode, PIL; node + playwright for the PDF/PNG step)
"""
import base64, os, re, shutil, zipfile
from xml.sax.saxutils import escape

import qrcode
from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.abspath(os.path.join(HERE, ".."))
TEMPLATE = os.path.join(HERE, "idml_template")
MAPS_URL = "https://maps.app.goo.gl/6UkNFgEYdHkM9V8R8"
NAME = "IUSBC_Invitation"
FONT = "IBM Plex Sans Arabic"
FONT_FILES = {400: "IBMPlexSansArabic-Regular.ttf", 500: "IBMPlexSansArabic-Medium.ttf", 700: "IBMPlexSansArabic-Bold.ttf"}
FONT_STYLE = {400: "Regular", 500: "Medium", 700: "Bold"}

# ----------------------------------------------------------------- page / colours
W, H = 210.0, 148.0           # mm  (A5 landscape)
BLEED = 3.0
PT = 72 / 25.4                # mm -> pt
NAVY, GOLD, GOLD_DEEP = (11, 42, 85), (190, 150, 58), (136, 98, 18)
PAPER, WHITE = (252, 249, 240), (255, 255, 255)
COLORS = {"Navy": NAVY, "Gold": GOLD, "GoldDeep": GOLD_DEEP, "Paper": PAPER, "White": WHITE}
hexc = lambda c: "#%02x%02x%02x" % c

HEAD_H = 32.0                 # white header with the two logos
CX = W / 2

# ----------------------------------------------------------------- shapes
shapes = []   # kind=rect|line|poly ; mm ; fill/stroke = colour names ; sw = pt ; op = %

def rect(x, y, w, h, fill=None, stroke=None, sw=0.5, op=100, r=0, link=None):
    shapes.append(dict(kind="rect", x=x, y=y, w=w, h=h, fill=fill, stroke=stroke, sw=sw, op=op, r=r, link=link))

def line(x1, y1, x2, y2, stroke="Gold", sw=0.5, op=100):
    shapes.append(dict(kind="line", pts=[(x1, y1), (x2, y2)], stroke=stroke, sw=sw, op=op))

def poly(paths, fill=None, stroke=None, sw=0.5, op=100):
    shapes.append(dict(kind="poly", paths=paths, fill=fill, stroke=stroke, sw=sw, op=op))

def diamond(cx, cy, r, fill="Gold"):
    poly([[(cx, cy - r), (cx + r, cy), (cx, cy + r), (cx - r, cy)]], fill=fill)

# light body (bleeds left / right / bottom)
rect(-BLEED, HEAD_H, W + 2 * BLEED, H - HEAD_H + BLEED, fill="Paper")

# frame geometry (inside the navy body)
FX0, FX1, FY0, FY1 = 5.0, W - 5.0, HEAD_H + 4.4, H - 3.2

# double gold frame
rect(FX0, FY0, FX1 - FX0, FY1 - FY0, stroke="Gold", sw=0.9)
rect(FX0 + 1.3, FY0 + 1.3, FX1 - FX0 - 2.6, FY1 - FY0 - 2.6, stroke="Gold", sw=0.35)

# header: two logos side by side
LOGO_S = 28.0
IUSBC_BOX = (CX + 4.0, 2.0, LOGO_S)       # council logo, on the right (RTL start)
FICC_BOX = (CX - 4.0 - LOGO_S, 2.0, LOGO_S)
HLY = 17.0
line(CX, 6, CX, HEAD_H - 6, sw=0.5)



# info row : date | time | place(+button) | QR
IX0, IX1 = FX0 + 10, FX1 - 10
IT, IB = 94.4, 122.4
COL1 = (IX1 - 44, IX1)            # التاريخ
COL2 = (COL1[0] - 30, COL1[0])    # الوقت
COL3 = (COL2[0] - 61, COL2[0])    # المكان
COL4 = (IX0, COL3[0])             # QR
line(IX0, IT, IX1, IT, sw=0.6); line(IX0, IB, IX1, IB, sw=0.6)
for c in (COL1, COL2, COL3):
    line(c[0], IT + 4, c[0], IB - 4, sw=0.4)

# map button (pill) under the place text
BTN_W, BTN_H = 43.0, 5.4
BTN_X = (COL3[0] + COL3[1]) / 2 - BTN_W / 2
BTN_Y = 116.0
rect(BTN_X, BTN_Y, BTN_W, BTN_H, fill="Navy", r=BTN_H / 2, link=MAPS_URL)

# QR on a white rounded card
QR_CARD = 20.0
QCX = (COL4[0] + COL4[1]) / 2
QCY0 = 96.6
rect(QCX - QR_CARD / 2, QCY0, QR_CARD, QR_CARD, fill="White", stroke="Gold", sw=0.7, r=1.6, link=MAPS_URL)
qr = qrcode.QRCode(error_correction=qrcode.constants.ERROR_CORRECT_M, box_size=1, border=0)
qr.add_data(MAPS_URL); qr.make(fit=True)
M = qr.get_matrix(); n = len(M)
QR_MOD = 17.0 / n
QRX, QRY = QCX - n * QR_MOD / 2, QCY0 + QR_CARD / 2 - n * QR_MOD / 2
qr_paths = []
for r_, row in enumerate(M):          # merge horizontal runs into rectangles
    c = 0
    while c < n:
        if row[c]:
            c0 = c
            while c < n and row[c]: c += 1
            x0, x1 = QRX + c0 * QR_MOD, QRX + c * QR_MOD
            y0, y1 = QRY + r_ * QR_MOD, QRY + (r_ + 1) * QR_MOD
            qr_paths.append([(x0, y0), (x1, y0), (x1, y1), (x0, y1)])
        else:
            c += 1
poly(qr_paths, fill="Navy")

IMAGES = [  # file, box (x, y, size mm), name
    ("iusbc_logo_clean.png", IUSBC_BOX, "IUSBC logo"),
    ("ficc_logo_clean.png", FICC_BOX, "FICC logo"),
]

# ----------------------------------------------------------------- text blocks (live text)
# baseline = first-line baseline (mm); size/lead in pt; each entry of `lines` is a forced line break.
texts = []
def T(id, lines, weight, size, lead, color, baseline, x=None, w=190.0, link=None):
    texts.append(dict(id=id, lines=lines, weight=weight, size=size, lead=lead, color=color, baseline=baseline,
                      x=(CX - w / 2) if x is None else x, w=w, link=link))

T("label", ["دعوة حضور"], 700, 15, 19, "GoldDeep", 44.6, w=44)
T("addressee", ["السادة أعضاء مجلس الأعمال العراقي–الأمريكي"], 700, 12.5, 16, "Navy", 52.4)
T("greet", ["يهديكم مجلس الأعمال العراقي–الأمريكي أطيب تحياته، ويتشرّف بدعوتكم لحضور"], 500, 10.8, 15, "Navy", 59.8)
T("title", ["حفل افتتاح مقر مجلس الأعمال العراقي–الأمريكي"], 700, 22, 27, "Navy", 71.0)
T("date", ["وذلك يوم الأربعاء الموافق ٢١ تشرين الأول ٢٠٢٦"], 500, 11.2, 16, "Navy", 79.0)
T("desc", ["يمثّل افتتاح المقر خطوةً نحو تعزيز التعاون الاقتصادي والتجاري بين العراق والولايات المتحدة الأمريكية،",
           "وتوسيع التواصل والشراكات بين مجتمعي الأعمال في البلدين."], 500, 9.6, 13.2, "Navy", 86.8)
LBL_B = 100.2
for cid, c, t in (("c1", COL1, "التاريخ"), ("c2", COL2, "الوقت"), ("c3", COL3, "المكان")):
    T("lbl_" + cid, [t], 700, 9.4, 12, "GoldDeep", LBL_B, x=c[0], w=c[1] - c[0])
T("v1", ["الأربعاء", "٢١ تشرين الأول ٢٠٢٦"], 700, 10.2, 14, "Navy", 107.0, x=COL1[0], w=COL1[1] - COL1[0])
T("v2", ["٦:٣٠ مساءً"], 700, 11.2, 14, "Navy", 111.0, x=COL2[0], w=COL2[1] - COL2[0])
T("v3", ["المنصور – قرب الأقواس", "من جهة سيد الحبيب", "بناية أديداس"], 700, 9.6, 12.2, "Navy", 105.4,
  x=COL3[0], w=COL3[1] - COL3[0])
T("btn", ["افتح الموقع على الخريطة"], 700, 7.8, 10, "White", BTN_Y + BTN_H / 2 + 1.0, x=BTN_X, w=BTN_W, link=MAPS_URL)
T("qrcap", ["امسح الرمز للوصول إلى الموقع"], 700, 7.4, 9.5, "Navy", 120.4, x=COL4[0], w=COL4[1] - COL4[0])
T("close", ["إن حضوركم الكريم يُسعدنا ويُضفي على هذه المناسبة مزيداً من الاعتزاز والتقدير.",
            "وتفضّلوا بقبول فائق الاحترام والتقدير."], 500, 9.2, 12.4, "Navy", 128.4)
T("sign", ["مجلس الأعمال العراقي–الأمريكي"], 700, 9.4, 12, "GoldDeep", 137.4)

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
            rx = f' rx="{s["r"]:.3f}"' if s.get("r") else ""
            out.append(f'<rect x="{s["x"]:.3f}" y="{s["y"]:.3f}" width="{s["w"]:.3f}" height="{s["h"]:.3f}"{rx} {attrs}/>')
        elif s["kind"] == "line":
            (x1, y1), (x2, y2) = s["pts"]
            out.append(f'<line x1="{x1:.3f}" y1="{y1:.3f}" x2="{x2:.3f}" y2="{y2:.3f}" {attrs}/>')
        else:
            d = " ".join("M" + " L".join(f"{x:.3f} {y:.3f}" for x, y in p) + " Z" for p in s["paths"])
            out.append(f'<path d="{d}" {attrs}/>')
    return "\n".join(out)

def html(tx=None):
    tx = texts if tx is None else tx
    blocks = []
    for t in tx:
        lines = "<br>".join(escape(l) for l in t["lines"])
        style = (f'left:{t["x"]:.3f}mm;width:{t["w"]:.3f}mm;font-weight:{t["weight"]};'
                 f'font-size:{t["size"]}pt;line-height:{t["lead"]}pt;color:{hexc(COLORS[t["color"]])};')
        blocks.append(f'<div class="blk" data-bl="{t["baseline"]}" style="{style}"><span class="mk"></span>{lines}</div>')
    imgs = "\n".join(f'<img class="logo" src="{f}" alt="{n}" style="left:{b[0]}mm;top:{b[1]}mm;width:{b[2]}mm;height:{b[2]}mm">'
                     for f, b, n in IMAGES)
    links = []
    for s in shapes:                                   # clickable areas = shapes flagged with a link
        if s.get("link"):
            links.append(f'<a class="lnk" href="{s["link"]}" style="left:{s["x"]:.2f}mm;top:{s["y"]:.2f}mm;'
                         f'width:{s["w"]:.2f}mm;height:{s["h"]:.2f}mm;border-radius:{s["r"]:.2f}mm"></a>')
    faces = "\n".join(f"@font-face{{font-family:'{FONT}';font-weight:{w};src:url('../Fonts/{f}')}}" for w, f in FONT_FILES.items())
    return f"""<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
<title>دعوة حضور – افتتاح مقر مجلس الأعمال العراقي–الأمريكي</title>
<style>
{faces}
@page{{size:{W}mm {H}mm;margin:0}}
*{{box-sizing:border-box;margin:0;padding:0}}
html,body{{width:{W}mm;height:{H}mm;background:#fff;overflow:hidden}}
#page{{position:relative;width:{W}mm;height:{H}mm;overflow:hidden;background:#fff;font-family:'{FONT}'}}
svg.bg{{position:absolute;left:0;top:0;width:{W}mm;height:{H}mm}}
img.logo{{position:absolute}}
.blk{{position:absolute;text-align:center;white-space:nowrap;direction:rtl;font-kerning:normal}}
.mk{{display:inline-block;width:0;height:0}}
.lnk{{position:absolute;display:block;cursor:pointer}}
</style></head><body><div id="page">
<svg class="bg" viewBox="0 0 {W} {H}" xmlns="http://www.w3.org/2000/svg">
{svg_shapes()}
</svg>
{imgs}
{chr(10).join(blocks)}
{chr(10).join(links)}
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

def item_attrs(s, name="$ID/"):
    fill = f"Color/{s['fill']}" if s.get("fill") else "Swatch/None"
    stroke = f"Color/{s['stroke']}" if s.get("stroke") else "Swatch/None"
    sw = f2(s.get("sw", 0.5)) if s.get("stroke") else "0"
    return (f'ItemLayer="{LAYER}" AppliedObjectStyle="ObjectStyle/$ID/[None]" ItemTransform="1 0 0 1 0 0" '
            f'FillColor="{fill}" StrokeColor="{stroke}" StrokeWeight="{sw}" Name="{name}"')

def transp(s):
    return (f'<TransparencySetting><BlendingSetting Opacity="{s["op"]}" /></TransparencySetting>'
            if s["op"] < 100 else "")

def shape_xml(s):
    sid = ids()
    if s["kind"] == "rect":
        p = [[(s["x"], s["y"]), (s["x"] + s["w"], s["y"]), (s["x"] + s["w"], s["y"] + s["h"]), (s["x"], s["y"] + s["h"])]]
        name = "Map link (Button / QR)" if s.get("link") else "$ID/"
        corner = ""
        if s.get("r"):
            rr = f2(s["r"] * PT)
            corner = "".join(f' {c}CornerOption="RoundedCorner" {c}CornerRadius="{rr}"'
                             for c in ("TopLeft", "TopRight", "BottomLeft", "BottomRight"))
        return f'<Rectangle Self="{sid}" ContentType="Unassigned" {item_attrs(s, name)}{corner}>{path_xml(p)}{transp(s)}</Rectangle>'
    if s["kind"] == "line":
        return f'<GraphicLine Self="{sid}" {item_attrs(s)}>{path_xml([s["pts"]], True)}{transp(s)}</GraphicLine>'
    return f'<Polygon Self="{sid}" ContentType="Unassigned" {item_attrs(s)}>{path_xml(s["paths"])}{transp(s)}</Polygon>'

def image_xml(fname, box, name):
    lx, ly, ls = box
    p = [[(lx, ly), (lx + ls, ly), (lx + ls, ly + ls), (lx, ly + ls)]]
    px = Image.open(os.path.join(HERE, fname)).size[0]
    native = px * 72 / 300.0
    sc = ls * PT / native
    data = base64.b64encode(open(os.path.join(HERE, fname), "rb").read()).decode()
    return (f'<Rectangle Self="{ids()}" ContentType="GraphicType" ItemLayer="{LAYER}" '
            f'AppliedObjectStyle="ObjectStyle/$ID/[Normal Graphics Frame]" ItemTransform="1 0 0 1 0 0" '
            f'FillColor="Swatch/None" StrokeColor="Swatch/None" StrokeWeight="0" Name="{name}">{path_xml(p)}'
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
    d = "RightToLeftDirection"
    content = "&#x2028;".join(escape(l) for l in t["lines"])     # forced line breaks inside one paragraph
    story = (f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
             f'<idPkg:Story xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" DOMVersion="20.0">'
             f'<Story Self="{sid}" AppliedTOCStyle="n" TrackChanges="false" StoryTitle="$ID/" AppliedNamedGrid="n">'
             f'<StoryPreference OpticalMarginAlignment="false" OpticalMarginSize="12" FrameType="TextFrameType" '
             f'StoryOrientation="Horizontal" StoryDirection="{d}" />'
             f'<InCopyExportOption IncludeGraphicProxies="true" IncludeAllResources="false" />'
             f'<ParagraphStyleRange AppliedParagraphStyle="ParagraphStyle/$ID/NormalParagraphStyle" '
             f'Justification="CenterAlign" ParagraphDirection="{d}" Hyphenation="false">'
             f'<CharacterStyleRange AppliedCharacterStyle="CharacterStyle/$ID/[No character style]" '
             f'FillColor="Color/{t["color"]}" FontStyle="{FONT_STYLE[t["weight"]]}" PointSize="{t["size"]}">'
             f'<Properties><Leading type="unit">{t["lead"]}</Leading><AppliedFont type="string">{FONT}</AppliedFont></Properties>'
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

    gfx = rd("Resources/Graphic.xml"); cols = ""; grp = ""
    for i, (nm, (r, g, b)) in enumerate(COLORS.items()):
        ref = f"u12ColorGroupSwatchx{i}"
        cols += (f'<Color Self="Color/{nm}" Model="Process" Space="RGB" ColorValue="{r} {g} {b}" ColorOverride="Normal" '
                 f'ConvertToHsb="false" AlternateSpace="NoAlternateColor" AlternateColorValue="" Name="{nm}" '
                 f'ColorEditable="true" ColorRemovable="true" Visible="true" SwatchCreatorID="7937" SwatchColorGroupReference="{ref}" />\n\t')
        grp += f'<ColorGroupSwatch Self="{ref}" SwatchItemRef="Color/{nm}" />\n\t\t'
    wr("Resources/Graphic.xml", gfx.replace("<Ink Self=", cols + "<Ink Self=", 1))

    pref = rd("Resources/Preferences.xml")
    pref = re.sub(r'(<DocumentPreference )PageHeight="[^"]*" PageWidth="[^"]*"',
                  rf'\1PageHeight="{f2(PH)}" PageWidth="{f2(PW)}"', pref, count=1)
    b = f2(BLEED * PT)
    for k in ("DocumentBleedTopOffset", "DocumentBleedBottomOffset", "DocumentBleedInsideOrLeftOffset", "DocumentBleedOutsideOrRightOffset"):
        pref = re.sub(rf'{k}="[^"]*"', f'{k}="{b}"', pref, count=1)
    wr("Resources/Preferences.xml", pref.replace('Intent="WebIntent"', 'Intent="PrintIntent"', 1))

    mp = os.listdir(os.path.join(work, "MasterSpreads"))[0]
    ms = rd("MasterSpreads/" + mp)
    ms = re.sub(r'GeometricBounds="[^"]*" ItemTransform="[^"]*"',
                f'GeometricBounds="0 0 {f2(PH)} {f2(PW)}" ItemTransform="1 0 0 1 {f2(-PW/2)} {f2(-PH/2)}"', ms, count=1)
    ms = re.sub(r'Top="36" Bottom="36" Left="36" Right="36" ColumnDirection="Horizontal" ColumnsPositions="[^"]*"',
                f'Top="14" Bottom="14" Left="14" Right="14" ColumnDirection="Horizontal" ColumnsPositions="0 {f2(PW-28)}"', ms, count=1)
    wr("MasterSpreads/" + mp, ms)

    items = [shape_xml(s) for s in shapes]
    items += [image_xml(f, b_, n_) for f, b_, n_ in IMAGES]
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

    if os.path.exists(path): os.remove(path)
    with zipfile.ZipFile(path, "w") as z:
        z.write(os.path.join(work, "mimetype"), "mimetype", compress_type=zipfile.ZIP_STORED)
        for root, _, files in os.walk(work):
            for f in sorted(files):
                full = os.path.join(root, f); rel = os.path.relpath(full, work)
                if rel != "mimetype":
                    z.write(full, rel, compress_type=zipfile.ZIP_DEFLATED)
    shutil.rmtree(work)

def personalised(addressee):
    """Copy of the text blocks with the addressee line replaced + 'please confirm attendance' line."""
    import copy
    tx = copy.deepcopy(texts)
    for t in tx:
        if t["id"] == "addressee":
            t["lines"] = [addressee]
    for t in tx:                                   # make room for the extra line at the bottom
        if t["id"] == "close": t["baseline"] = 127.2
        if t["id"] == "sign": t["baseline"] = 135.8
    tx.append(dict(id="rsvp", lines=["يرجى تأكيد الحضور"], weight=700, size=10.4, lead=12, color="GoldDeep",
                   baseline=140.8, x=CX - 40, w=80.0, link=None))
    return tx

def safe(name):
    return re.sub(r'[\\/:*?"<>|\s]+', "_", name).strip("_")

if __name__ == "__main__":
    import json, sys
    open(os.path.join(HERE, "invitation.html"), "w", encoding="utf-8").write(html())
    build_idml(os.path.join(OUT, NAME + ".idml"))
    jobs = [{"html": "invitation.html", "pdf": os.path.join(OUT, NAME + ".pdf"), "png": os.path.join(OUT, NAME + ".png")}]
    names_file = os.path.join(HERE, "names.txt")
    if os.path.exists(names_file):
        names = [l.strip() for l in open(names_file, encoding="utf-8") if l.strip() and not l.startswith("#")]
        cards = os.path.join(OUT, "named_cards"); os.makedirs(cards, exist_ok=True)
        tmp = os.path.join(HERE, "_cards"); os.makedirs(tmp, exist_ok=True)
        for k, nm in enumerate(names, 1):
            h = os.path.join(tmp, f"card_{k:02d}.html")
            open(h, "w", encoding="utf-8").write(html(personalised(nm)).replace("src=\"", "src=\"../").replace("../Fonts", "../../Fonts").replace("url('../../Fonts", "url('../../Fonts"))
            jobs.append({"html": os.path.relpath(h, HERE), "pdf": os.path.join(cards, f"{k:02d}_{safe(nm)}.pdf")})
    json.dump(jobs, open(os.path.join(HERE, "jobs.json"), "w"))
    print("html + idml written;", len(jobs) - 1, "named cards queued")
