"""Layout specification for the Iraqi CSR & Business Integrity Forum 2026 profile.

One spec, two renderers: render_html.py (-> PDF via Chromium) and
render_idml.py (-> InDesign IDML). All geometry is in points, origin at the
top-left of the trim box. Every string below is copied verbatim from the final
Word file; tools/verify_text.py checks that nothing was added or changed.
"""
from dataclasses import dataclass, field
import math

import fonts

W = 841.8897637795276   # A4 landscape
H = 595.2755905511812
BLEED = 8.503937007874017  # 3 mm
M = 42.0
R = W - M

# ---------------------------------------------------------------- colours --
COLORS = {
    "Deep":      "06241B",  # brand deep green (headlines, dark panels)
    "Ink":       "091E17",  # body text
    "Green":     "12664A",  # English titles
    "Gold":      "C9A24A",
    "GoldDark":  "967026",
    "GoldLight": "EAD294",
    "Cream":     "F8F3E8",
    "Sand":      "EAE3CB",
    "Muted":     "5C7064",
    "Line":      "E3D9BF",  # hairlines on white
    "DeepLine":  "1F4A3B",  # hairlines on deep green
    "White":     "FFFFFF",
}


def _mix(a, b, t):
    pa = [int(a[i:i + 2], 16) for i in (0, 2, 4)]
    pb = [int(b[i:i + 2], 16) for i in (0, 2, 4)]
    return "".join(f"{round(x + (y - x) * t):02X}" for x, y in zip(pa, pb))


# Light lattice pattern: four fade steps from the paper colour toward a line colour.
PATTERN = {
    "White": ("FFFFFF", "DCCFAF"),
    "Cream": ("F8F3E8", "E0D2AE"),
    "Deep":  ("06241B", "1E5240"),
}
for _bg, (_a, _b) in PATTERN.items():
    for _k in range(1, 5):
        COLORS[f"Pattern {_bg} {_k}"] = _mix(_a, _b, _k / 4)

# ------------------------------------------------------------------ fonts --
DISPLAY = "Thmanyah Serif Display"   # headlines, figures
SANS = "Thmanyah Sans"               # labels, sub-heads, UI text
TEXT = "IBM Plex Sans Arabic"        # running text and English lines

# InDesign style name -> CSS weight / italic
FONT_STYLES = {
    "Light": (300, False), "Regular": (400, False), "Medium": (500, False),
    "SemiBold": (600, False), "Bold": (700, False), "Black": (900, False),
    "Italic": (400, True),
}


@dataclass
class PStyle:
    name: str
    family: str
    style: str
    size: float
    lead: float
    color: str
    align: str = "right"          # right | left | center | justify
    rtl: bool = True
    tracking: int = 0             # 1/1000 em (Latin only)
    caps: bool = False
    pad_top: float = 0            # spacing inside the paragraph, above
    pad_bottom: float = 0         # spacing inside the paragraph, below
    rule_above: tuple = None      # (color, weight)
    no_break: bool = False

    @property
    def delta(self):
        """Distance from line-box centre to baseline in CSS layout."""
        a, d = fonts.metrics(self.family)
        return (a - d) / 2 * self.size


S = {}


def ps(*args, **kw):
    s = PStyle(*args, **kw)
    S[s.name] = s
    return s


# running elements
ps("Running Head", SANS, "Medium", 8.5, 12, "Deep")
ps("Folio", TEXT, "Medium", 9, 12, "GoldDark", align="left", rtl=False)
# section openers
ps("Kicker", SANS, "Bold", 9.5, 13, "GoldDark")
ps("Kicker Centre", SANS, "Bold", 9.5, 13, "GoldDark", align="center")
ps("Section Title", DISPLAY, "Bold", 34, 46, "Deep")
ps("Section Title Centre", DISPLAY, "Bold", 34, 46, "Deep", align="center")
ps("Body", TEXT, "Regular", 12, 22.5, "Ink", align="justify")
ps("Intro", SANS, "Medium", 13, 22, "Deep")
ps("Sub Head", SANS, "Bold", 14, 20, "Deep")
# figures
ps("Stat Number", DISPLAY, "Bold", 36, 42, "Gold", align="center")
ps("Stat Label", SANS, "Medium", 10, 14, "Deep", align="center")
# cards
ps("Card Number", DISPLAY, "Bold", 26, 30, "Gold")
ps("Card Number Centre", DISPLAY, "Bold", 26, 30, "Gold", align="center")
ps("Card Title", SANS, "Bold", 13.5, 19, "Deep")
ps("Card Title Centre", SANS, "Bold", 13, 18, "Deep", align="center")
ps("Card Text", TEXT, "Regular", 10, 17, "Ink")
ps("Label", SANS, "Medium", 9, 13, "GoldDark")
ps("Label On Deep", SANS, "Medium", 9, 13, "GoldLight")
ps("List Centre", TEXT, "Regular", 10.5, 17, "Ink", align="center",
   pad_top=5.5, pad_bottom=5.5, rule_above=("Line", 0.6))
ps("List", TEXT, "Regular", 10.5, 17, "Ink",
   pad_top=5.5, pad_bottom=5.5, rule_above=("Line", 0.6))
ps("List On Deep", TEXT, "Regular", 10.5, 17, "Cream",
   pad_top=5.5, pad_bottom=5.5, rule_above=("DeepLine", 0.6))
ps("Pill", SANS, "Medium", 10.5, 14, "Deep", align="center")
ps("Tier Title", DISPLAY, "Bold", 24, 32, "Deep")
ps("Tier Title On Deep", DISPLAY, "Bold", 24, 32, "Cream")
ps("Note", SANS, "Medium", 10.5, 14, "Deep", align="center")
ps("Logo Label", SANS, "Medium", 9, 13, "Deep", align="center")
ps("Logo Label Latin", TEXT, "Medium", 9, 13, "Deep", align="center", rtl=False)
ps("Highlight Title", SANS, "Bold", 14.5, 21, "GoldLight")
ps("Highlight Text", TEXT, "Regular", 10.5, 18, "Cream", align="justify")
ps("Date Block", SANS, "Bold", 12, 16, "GoldLight")
# cover
ps("Cover Year", SANS, "Bold", 9.5, 14, "GoldDark", align="center")
ps("Cover Title", DISPLAY, "Bold", 32, 44, "Deep")
ps("Cover English", TEXT, "Light", 13, 18, "Green", rtl=False, align="right")
ps("Cover Theme", DISPLAY, "Medium", 16, 24, "GoldDark")
ps("Cover Theme English", TEXT, "Regular", 9.5, 13, "Green", rtl=False, align="right")
ps("Cover Day", DISPLAY, "Bold", 48, 54, "Gold", align="center")
ps("Cover Date", SANS, "Bold", 12, 16, "Deep")
ps("Cover Date Sub", SANS, "Regular", 9.5, 14, "Muted")
ps("Caps Latin", TEXT, "Medium", 6.5, 11, "GoldDark", rtl=False, align="right",
   tracking=150, caps=True)
ps("Caps Latin Centre", TEXT, "Medium", 7, 12, "GoldLight", rtl=False, align="center",
   tracking=220, caps=True)
ps("Cover Label", SANS, "Bold", 9, 13, "GoldDark")
# back cover
ps("Back Title", DISPLAY, "Bold", 30, 42, "Cream", align="center")
ps("Back English", TEXT, "Light", 12, 17, "GoldLight", align="center", rtl=False)
ps("Back Theme", DISPLAY, "Medium", 25, 34, "GoldLight", align="center")
ps("Back Theme English", TEXT, "Light", 12, 17, "Cream", align="center", rtl=False)
ps("Back Date", SANS, "Medium", 11, 16, "Cream", align="center")

# character styles
C = {
    "Gold Accent": {"color": "Gold"},
    "Emphasis": {"family": TEXT, "style": "SemiBold", "color": "Deep"},
    "Emphasis Gold": {"family": TEXT, "style": "SemiBold", "color": "GoldDark"},
    # U+25C6 is in none of the text fonts; Word/Chrome substituted it silently,
    # InDesign would show a missing glyph. Pin it to a free symbol font.
    "Ornament": {"family": "Noto Sans Symbols 2", "style": "Regular"},
}
ORNAMENT = "\u25c6"


# ----------------------------------------------------------- primitives ---
@dataclass
class Para:
    style: str
    runs: list            # [(text, char_style_or_None)]
    space_before: float = 0
    space_after: float = 0


@dataclass
class Rect:
    x: float
    y: float
    w: float
    h: float
    fill: str = None
    stroke: str = None
    sw: float = 0
    name: str = ""


@dataclass
class Line:
    x1: float
    y1: float
    x2: float
    y2: float
    color: str = "Line"
    sw: float = 0.75
    name: str = ""


@dataclass
class Poly:
    points: list
    fill: str = None
    stroke: str = None
    sw: float = 0
    name: str = ""


@dataclass
class Ellipse:
    x: float
    y: float
    w: float
    h: float
    fill: str = None
    stroke: str = None
    sw: float = 0
    name: str = ""


@dataclass
class Multi:
    """Compound path of closed sub-paths (one object in InDesign)."""
    paths: list
    stroke: str = None
    sw: float = 0.6
    name: str = ""


@dataclass
class Img:
    x: float
    y: float
    w: float
    h: float
    src: str
    fit: str = "cover"   # cover | contain
    ax: float = 0.5
    ay: float = 0.5
    oval: bool = False
    name: str = ""


@dataclass
class Text:
    x: float
    y: float
    w: float
    h: float
    paras: list
    name: str = ""


@dataclass
class Page:
    name: str
    bg: str = None
    master: bool = True
    items: list = field(default_factory=list)


def P(style, *runs, sb=0, sa=0):
    rr = []
    for r in runs:
        text, cs = (r, None) if isinstance(r, str) else r
        # split out the diamond ornament into its own run (text itself unchanged)
        for i, part in enumerate(text.split(ORNAMENT)):
            if i:
                rr.append((ORNAMENT, "Ornament"))
            if part:
                rr.append((part, cs))
    return Para(style, rr, sb, sa)


def star(cx, cy, r):
    """Eight-point star (two interlaced squares) - the forum mark's outline."""
    ri = r * math.cos(math.radians(45)) / math.cos(math.radians(22.5))
    pts = []
    for i in range(16):
        ang = math.radians(-90 + i * 22.5)
        rad = r if i % 2 == 0 else ri
        pts.append((cx + rad * math.cos(ang), cy + rad * math.sin(ang)))
    return pts


def diamond(cx, cy, s):
    return [(cx, cy - s), (cx + s, cy), (cx, cy + s), (cx - s, cy)]


# --------------------------------------------------------------- helpers ---
def title_block(items, x, w, kicker, title, centre=False, y=68):
    ks, ts = ("Kicker Centre", "Section Title Centre") if centre else ("Kicker", "Section Title")
    items.append(Text(x, y, w, 16, [P(ks, kicker)], name="kicker"))
    items.append(Text(x, y + 14, w, 56, [P(ts, title)], name="title"))
    bar_w = 38
    bx = x + (w - bar_w) / 2 if centre else x + w - bar_w
    items.append(Rect(bx, y + 76, bar_w, 2.5, fill="Gold", name="title rule"))


def framed_image(items, x, y, w, h, src, ax=0.5, ay=0.5, off=10):
    items.append(Rect(x + off, y + off, w, h, stroke="Gold", sw=0.75, name="image offset frame"))
    items.append(Img(x, y, w, h, src, ax=ax, ay=ay, name=src))


def lattice(zone, bg, anchor, fade, s=44, sw=0.6, away=False, inside=False):
    """Light eight-point-star lattice that fades with distance from `anchor`.

    zone: (x0, y0, x1, y1) area to tile; stars may overrun it unless inside=True.
    away=True makes the pattern strongest far from the anchor (keeps a centre clear).
    Returns up to four compound paths, one per fade step.
    """
    x0, y0, x1, y1 = zone
    r = s / 2
    buckets = {1: [], 2: [], 3: [], 4: []}
    ax, ay = anchor
    nx, ny = int((x1 - x0) / s) + 2, int((y1 - y0) / s) + 2
    for i in range(-1, nx):
        for j in range(-1, ny):
            cx, cy = x0 + i * s + s / 2, y0 + j * s + s / 2
            if inside and not (cx - r >= x0 and cx + r <= x1 and cy - r >= y0 and cy + r <= y1):
                continue
            d = math.hypot(cx - ax, cy - ay)
            t = (d - fade[0]) / fade[1] if away else 1 - d / fade
            if t <= 0:
                continue
            buckets[min(4, math.ceil(t * 4))].append(star(cx, cy, r))
    return [Multi(p, stroke=f"Pattern {bg} {k}", sw=sw, name=f"pattern {k}")
            for k, p in buckets.items() if p]


def node(items, cx, cy, ring=10, dot=4.5, bg="White"):
    """Gold ringed dot - a step marker on a timeline or connector."""
    items.append(Ellipse(cx - ring, cy - ring, 2 * ring, 2 * ring, fill=bg, stroke="Gold", sw=1.4, name="node ring"))
    items.append(Ellipse(cx - dot, cy - dot, 2 * dot, 2 * dot, fill="Gold", name="node dot"))


def badge(items, cx, cy, r, color="Gold", fill=None, sw=0.9):
    """Octagram badge drawn from the pattern's own star (replaces the plant logo)."""
    items.append(Poly(star(cx, cy, r), fill=fill, stroke=color, sw=sw, name="badge"))
    items.append(Poly(star(cx, cy, r * 0.62), stroke=color, sw=sw * 0.7, name="badge inner"))
    items.append(Poly(diamond(cx, cy, r * 0.16), fill=color, name="badge centre"))


# ================================================================= PAGES ===
def master_items():
    it = []
    it += lattice((W - 330, -BLEED, W + BLEED, 230), "White", (W, 0), 330, s=40)
    badge(it, R - 9, 34, 9)
    it.append(Text(R - 330, 28.5, 302, 14,
                   [P("Running Head", "منتدى المسؤولية الاجتماعية واستدامة الأعمال العراقي")],
                   name="running head"))
    it.append(Line(M, 52, R, 52, "Line", 0.75, name="head rule"))
    it.append(Text(M, H - 40, 40, 14, [P("Folio", "#")], name="folio"))
    it.append(Line(M + 26, H - 34.5, R, H - 34.5, "Line", 0.75, name="foot rule"))
    it.append(Poly(diamond(R, H - 34.5, 2.6), fill="Gold", name="foot diamond"))
    return it


def page_cover():
    pg = Page("Cover", bg="Cream", master=False)
    it = pg.items
    it.append(Img(-14, (H - 380) / 2 + 6, 444, 380, "cover-hands-flag.jpg", ax=0.5, name="cover photo"))
    zx, zw = 420, R - 420
    it[:0] = lattice((-BLEED, -BLEED, W + BLEED, H + BLEED), "Cream", (W, 0), 470, s=46)
    badge(it, R - 28, 64, 26, fill="Cream")
    it.append(Text(R - 88, 96, 120, 14, [P("Cover Year", "بغداد ٢٠٢٦")], name="year"))
    it.append(Text(zx, 126, zw, 92, [
        P("Cover Title", "منتدى المسؤولية"),
        P("Cover Title", "الاجتماعية واستدامة الأعمال ", ("العراقي", "Gold Accent")),
    ], name="cover title"))
    it.append(Text(zx, 222, zw, 40, [
        P("Cover English", "The Iraqi Forum for Corporate Social Responsibility "),
        P("Cover English", "and Business Integrity"),
    ], name="cover english"))
    it.append(Rect(R - 38, 272, 38, 2, fill="Gold", name="cover rule"))
    it.append(Text(zx, 282, zw, 24, [
        P("Cover Theme", "◆ الأعمال المسؤولة في العراق: شراكات من أجل النمو المستدام ◆")], name="theme"))
    it.append(Text(zx, 306, zw, 16, [
        P("Cover Theme English", "Responsible Business in Iraq: Partnerships for Sustainable Growth")],
        name="theme english"))
    # date / venue strip
    sy = 342
    it.append(Line(zx, sy, R, sy, "Line", 0.75))
    it.append(Line(zx, sy + 74, R, sy + 74, "Line", 0.75))
    it.append(Text(R - 44, sy + 10, 44, 58, [P("Cover Day", "١")], name="day"))
    it.append(Text(R - 172, sy + 13, 124, 52, [
        P("Cover Date", "تشرين الثاني ٢٠٢٦"),
        P("Cover Date Sub", "يوم الأحد"),
        P("Caps Latin", "Sunday · 01 November 2026", sb=1),
    ], name="date"))
    it.append(Line(R - 182, sy + 14, R - 182, sy + 60, "Line", 0.75))
    it.append(Text(zx + 52, sy + 13, R - 192 - (zx + 52), 52, [
        P("Cover Date", "بغداد"),
        P("Cover Date Sub", "جمهورية العراق"),
        P("Caps Latin", "Baghdad · Republic of Iraq", sb=1),
    ], name="venue"))
    it.append(Img(zx, sy + 15, 44, 44, "baghdad-thumb.png", fit="cover", oval=True, name="baghdad"))
    # organisers
    oy = 452
    it.append(Text(zx, oy, zw, 14, [P("Cover Label", "الجهات المنظمة")], name="organisers label"))
    logos = ["logo-ministry-of-trade.png", "logo-ficc.png", "logo-icc-iraq.png", "logo-undp.png"]
    lw, gap = (zw - 3 * 10) / 4, 10
    for i, lg in enumerate(logos):
        x = R - (i + 1) * lw - i * gap
        it.append(Rect(x, oy + 22, lw, 76, fill="White", stroke="Line", sw=0.75, name="logo tile"))
        it.append(Img(x + 8, oy + 28, lw - 16, 64, lg, fit="contain", name=lg))
    return pg


def page_about():
    pg = Page("About")
    it = pg.items
    zx = 430
    zw = R - zx
    title_block(it, zx, zw, "◆  عن المنتدى", "نبذة عامة")
    framed_image(it, M, 68, 350, 462, "about-baghdad-tigris.jpg", ax=0.62)
    it.append(Text(zx, 164, zw, 290, [
        P("Body", "يُعدّ\xa0", ("منتدى المسؤولية الاجتماعية واستدامة الأعمال العراقي", "Emphasis"), "\xa0منصة وطنية بأبعاد دولية، تهدف إلى تعزيز دور القطاع الخاص العراقي كشريك فاعل في تحقيق التنمية المستدامة، من خلال ترسيخ مبادئ النزاهة والحوكمة وممارسات الأعمال المسؤولة، وتعزيز المسؤولية الاجتماعية والبيئية، وبناء شراكات مستدامة تسهم في رفع التنافسية وتحقيق أثر إيجابي على الاقتصاد والمجتمع والبيئة. كما يسعى المنتدى إلى الانتقال بالمسؤولية الاجتماعية من مبادرات متفرقة إلى برامج استراتيجية مستدامة ذات أثر قابل للقياس.", sa=9),
        P("Body", "وسيجمع المنتدى القيادات الحكومية ومؤسسات القطاع الخاص والمنظمات الدولية والجامعات والخبراء وممثلي المجتمع المدني تحت سقف واحد، وسيُعقد في بغداد ", ("يوم الأحد، الأول من تشرين الثاني ٢٠٢٦", "Emphasis Gold"), "، في إطار توجه يهدف إلى تعزيز التعاون بين مختلف القطاعات، ودعم ممارسات الاستدامة والحوكمة في مجتمع الأعمال العراقي."),
    ], name="about body"))
    sy = 462
    it.append(Rect(zx, sy, zw, 78, fill="Cream", name="stats band"))
    it.append(Rect(zx, sy, zw, 2, fill="Gold", name="stats rule"))
    stats = [("٣", "جلسات حوارية"), ("٤", "جهات منظّمة"), ("٥", "فئات مشاركة")]
    cw = zw / 3
    for i, (n, lab) in enumerate(stats):
        x = R - (i + 1) * cw
        it.append(Text(x, sy + 8, cw, 44, [P("Stat Number", n)], name="stat number"))
        it.append(Text(x, sy + 50, cw, 16, [P("Stat Label", lab)], name="stat label"))
        if i:
            it.append(Line(x + cw, sy + 16, x + cw, sy + 64, "Sand", 0.75))
    return pg


def page_objectives():
    pg = Page("Objectives")
    it = pg.items
    zx = 322
    zw = R - zx
    title_block(it, zx, zw, "◆  أهداف المنتدى", "الأهداف الاستراتيجية")
    framed_image(it, M, 68, 246, 462, "objectives-hands-seedling.jpg", ax=0.5)
    goals = [
        ("١", "ترسيخ النزاهة والحوكمة المسؤولة",
         "إبراز دور النزاهة والحوكمة والممارسات البيئية والاجتماعية في تعزيز استدامة الشركات العراقية، وبناء الثقة، ورفع التنافسية وتحسين فرص الوصول إلى الاستثمار والأسواق."),
        ("٢", "ترجمة الالتزامات إلى ممارسات عملية",
         "دعم الشركات العراقية في تبني وتطبيق الأطر والمعايير الوطنية والدولية ذات الصلة، ودمج مبادئ النزاهة والحوكمة والممارسات البيئية والاجتماعية في سياساتها وعملياتها اليومية."),
        ("٣", "تعزيز المسؤولية الاجتماعية والأثر الإيجابي",
         "ترسيخ ثقافة المسؤولية الاجتماعية وتشجيع الشركات على الإسهام الفاعل في التنمية المستدامة وتحقيق أثر إيجابي للمجتمعات."),
        ("٤", "بناء شراكات مستدامة",
         "تعزيز التعاون بين المؤسسات الحكومية والقطاع الخاص والمجتمع المدني، وبناء أطر وشراكات تدعم تبادل الخبرات والعمل الجماعي."),
    ]
    gap = 16
    cw = (zw - gap) / 2
    ch = (532 - 160 - gap) / 2
    for i, (n, t, d) in enumerate(goals):
        col, row = i % 2, i // 2
        x = R - (col + 1) * cw - col * gap
        y = 160 + row * (ch + gap)
        it.append(Rect(x, y, cw, ch, fill="Cream", name="goal card"))
        it.append(Rect(x, y, cw, 2, fill="Gold", name="goal rule"))
        it.append(Text(x + 18, y + 12, cw - 36, ch - 20, [
            P("Card Number", n),
            P("Card Title", t, sb=1),
            P("Card Text", d, sb=6),
        ], name="goal text"))
    return pg


def page_participants():
    pg = Page("Participants")
    it = pg.items
    zx = 430
    title_block(it, zx, R - zx, "◆  من سيشارك؟", "الفئات المشاركة")
    it.append(Img(M, 68, 366, 80, "participants-banner.jpg", ay=0.35, name="participants banner"))
    cats = [
        ("١", "الجهات الحكومية", ["الوزارات", "الهيئات الرسمية", "المؤسسات الحكومية"], "cat-1-government.jpg"),
        ("٢", "القطاع الخاص", ["الشركات الكبرى", "المصارف", "شركات الطاقة", "شركات الاتصالات", "المؤسسات الصناعية"], "cat-2-private.jpg"),
        ("٣", "المؤسسات الدولية", ["منظمات الأمم المتحدة", "المنظمات التنموية", "الغرف التجارية الدولية"], "cat-3-international.jpg"),
        ("٤", "القطاع الأكاديمي", ["الجامعات", "مراكز البحوث", "الخبراء"], "cat-4-academic.jpg"),
        ("٥", "المجتمع المدني والإعلام", ["منظمات المجتمع المدني", "المبادرات التطوعية", "وسائل الإعلام"], "cat-5-civil.jpg"),
    ]
    gap = 12
    cw = (R - M - 4 * gap) / 5
    cy, chh = 214, 312
    it.append(Line(M + cw / 2, cy, R - cw / 2, cy, "Gold", 0.9, name="connector"))
    for i, (n, t, items, img) in enumerate(cats):
        x = R - (i + 1) * cw - i * gap
        it.append(Rect(x, cy, cw, chh, fill="White", stroke="GoldLight", sw=0.75, name="category card"))
        it.append(Rect(x, cy + chh - 3, cw, 3, fill="Gold", name="category rule"))
        d = 70
        it.append(Poly(star(x + cw / 2, cy, 44), fill="White", stroke="GoldLight", sw=0.75, name="photo star"))
        it.append(Img(x + cw / 2 - d / 2, cy - d / 2, d, d, img, oval=True, name=img))
        paras = [P("Card Number Centre", n), P("Card Title Centre", t)]
        for k, s in enumerate(items):
            paras.append(P("List Centre", s, sb=10 if k == 0 else 0))
        it.append(Text(x + 10, cy + 50, cw - 20, chh - 62, paras, name="category text"))
    return pg


def page_programme():
    pg = Page("Programme")
    it = pg.items
    zx = 322
    zw = R - zx
    title_block(it, zx, zw, "◆  جدول الأعمال", "برنامج المنتدى")
    framed_image(it, M, 68, 246, 462, "programme-audience.jpg", ax=0.5)
    it.append(Rect(M, 474, 246, 56, fill="Deep", name="date block"))
    it.append(Rect(M, 474, 246, 2, fill="Gold", name="date rule"))
    it.append(Text(M + 18, 494, 210, 16, [P("Date Block", "الأحد  ١ تشرين الثاني ٢٠٢٦")], name="date"))

    # The day's order as a timeline: opening -> dialogue sessions -> alliance launch.
    tx = R - 11                 # timeline axis
    cr = R - 34                 # content right edge
    cw = cr - zx
    gap = 10
    pw = (cw - 2 * gap) / 3
    by, bh = 418, 112
    steps = [175, 253, by + 25]
    it.append(Line(tx, steps[0], tx, steps[-1], "Gold", 1.1, name="timeline"))
    for y in steps:
        node(it, tx, y)

    it.append(Text(zx, 164, cw, 22, [P("Sub Head", "الجلسة الافتتاحية")], name="opening head"))
    pills = ["كلمات الجهات المنظّمة", "كلمات الجهات الداعمة", "الكلمة الرئيسية"]
    for i, s in enumerate(pills):
        x = cr - (i + 1) * pw - i * gap
        it.append(Rect(x, 192, pw, 34, fill="Cream", stroke="GoldLight", sw=0.75, name="pill"))
        it.append(Text(x + 6, 202, pw - 12, 14, [P("Pill", s)], name="pill text"))

    it.append(Text(zx, 242, cw, 22, [P("Sub Head", "الجلسات الحوارية")], name="sessions head"))
    sessions = [
        ("١", "الجلسة الأولى", "الجدوى الاقتصادية للنزاهة في عالم سريع التغيّر."),
        ("٢", "الجلسة الثانية", "من المبادئ إلى الممارسة – إدماج النزاهة والممارسات البيئية والاجتماعية والحوكمة في الأعمال."),
        ("٣", "الجلسة الثالثة", "من البيئة التمكينية إلى الأثر –  تعزيز الأعمال المستدامة والمسؤولية الاجتماعية للشركات في العراق."),
    ]
    for i, (n, lab, d) in enumerate(sessions):
        x = cr - (i + 1) * pw - i * gap
        it.append(Rect(x, 270, pw, 132, fill="White", stroke="GoldLight", sw=0.75, name="session card"))
        it.append(Rect(x, 270, pw, 2, fill="Gold", name="session rule"))
        it.append(Text(x + 13, 280, pw - 26, 118, [
            P("Card Number", n),
            P("Label", lab),
            P("Card Text", d, sb=3),
        ], name="session text"))

    it.append(Rect(zx, by, cw, bh, fill="Deep", name="alliance panel"))
    it.append(Rect(zx, by, cw, 2, fill="Gold", name="alliance rule"))
    it += lattice((zx, by, zx + 120, by + bh), "Deep", (zx, by + bh), 150, s=28, inside=True)
    badge(it, zx + 52, by + bh / 2, 30, fill="Deep")
    it.append(Text(zx + 104, by + 14, cw - 104 - 20, bh - 20, [
        P("Highlight Title", "إطلاق التحالف الأخضر لنزاهة الأعمال"),
        P("Highlight Text", "الإطلاق الرسمي لمنصة تتيح للشركات العراقية ترجمة مبادئ المنتدى إلى التزامات مستدامة في مجالات النزاهة، والممارسات البيئية والاجتماعية والحوكمة.", sb=4),
    ], name="alliance text"))
    return pg


def page_partnership():
    pg = Page("Partnership")
    it = pg.items
    zx = 450
    title_block(it, zx, R - zx, "◆  كن شريكاً في الأثر", "الشراكات والرعاية")
    it.append(Rect(M + 352, 84, 2.5, 60, fill="Gold", name="intro rule"))
    it.append(Text(M, 80, 340, 74, [
        P("Intro", "يقدّم المنتدى فرصاً للمؤسسات الراغبة في دعم المسؤولية الاجتماعية وتعزيز حضورها المؤسسي أمام نخبة من صنّاع القرار والشركاء.")],
        name="intro"))
    tiers = [
        ("الفئة الأولى", "الشريك الاستراتيجي",
         ["ظهور رئيسي للعلامة التجارية في جميع مواد المنتدى",
          "مشاركة قيادية في الجلسة الافتتاحية والجلسات الحوارية",
          "جناح خاص في معرض المنتدى",
          "تغطية إعلامية موسّعة",
          "دعوات حضور لكبار الشخصيات (VIP)"], "deep"),
        ("الفئة الثانية", "الراعي البلاتيني",
         ["شعار رئيسي في المواد الرسمية",
          "مشاركة متحدث في جلسات المنتدى",
          "مساحة عرض في المعرض"], "cream"),
        ("الفئة الثالثة", "الراعي الذهبي",
         ["ظهور إعلامي",
          "شعار في المواد الرسمية",
          "دعوات خاصة لحضور المنتدى"], "white"),
    ]
    gap = 14
    cw = (R - M - 2 * gap) / 3
    for i, (lab, title, items, tone) in enumerate(tiers):
        x = R - (i + 1) * cw - i * gap
        ty = 168 + i * 36
        th = 486 - ty
        if tone == "deep":
            it.append(Rect(x, ty, cw, th, fill="Deep", name="tier card"))
        elif tone == "cream":
            it.append(Rect(x, ty, cw, th, fill="Cream", name="tier card"))
        else:
            it.append(Rect(x, ty, cw, th, fill="White", stroke="GoldLight", sw=0.75, name="tier card"))
        it.append(Rect(x, ty, cw, 3, fill="Gold", name="tier rule"))
        dark = tone == "deep"
        paras = [
            P("Label On Deep" if dark else "Label", lab),
            P("Tier Title On Deep" if dark else "Tier Title", title, sb=8),
            P("Label On Deep" if dark else "Label", "المزايا", sb=8),
        ]
        for k, s in enumerate(items):
            paras.append(P("List On Deep" if dark else "List", s, sb=6 if k == 0 else 0))
        it.append(Text(x + 20, ty + 20, cw - 40, th - 30, paras, name="tier text"))
    it.append(Rect(M, 500, R - M, 36, fill="Sand", name="note band"))
    it.append(Text(M + 20, 511, R - M - 40, 14, [
        P("Note", "تُصمَّم باقات مخصّصة وفق احتياجات الشركاء، وللاستفسار يُرجى التواصل مع اللجنة المنظمة للمنتدى.")],
        name="note"))
    return pg


def page_partners():
    pg = Page("Partners")
    it = pg.items
    title_block(it, M, R - M, "بالتنسيق والتعاون مع", "الجهات المنظمة والداعمة وضيوف المنتدى", centre=True)
    rows = [
        [("وزارة التجارة", "logo-ministry-of-trade.png"),
         ("اتحاد الغرف التجارية العراقية", "logo-ficc.png"),
         ("غرفة التجارة الدولية – العراق", "logo-icc-iraq.png"),
         ("برنامج الأمم المتحدة الإنمائي", "logo-undp.png")],
        [("اتحاد الغرف العربية", "logo-union-arab-chambers.png"),
         ("الاتحاد الدولي للمسؤولية الاجتماعية", "logo-iusr.png"),
         ("منظمة الأمم المتحدة للتنمية الصناعية", "logo-unido.png"),
         ("CSR Accreditation", "logo-csr-accreditation.png")],
    ]
    gap = 14
    cw = (R - M - 3 * gap) / 4
    ch = 130
    for r, row in enumerate(rows):
        y = 164 + r * (ch + gap)
        for i, (lab, lg) in enumerate(row):
            x = R - (i + 1) * cw - i * gap
            it.append(Rect(x, y, cw, ch, fill="White", stroke="GoldLight", sw=0.75, name="logo card"))
            it.append(Img(x + 20, y + 12, cw - 40, 74, lg, fit="contain", name=lg))
            it.append(Line(x + cw / 2 - 16, y + 95, x + cw / 2 + 16, y + 95, "Gold", 1))
            latin = lab.isascii()
            it.append(Text(x + 8, y + 102, cw - 16, 26,
                           [P("Logo Label Latin" if latin else "Logo Label", lab)], name="logo label"))
    ew, eh = 300, 72
    ex, ey = (W - ew) / 2, 462
    it.append(Rect(ex, ey, ew, eh, fill="Cream", stroke="GoldLight", sw=0.75, name="eu card"))
    it.append(Img(ex + 16, ey + 12, 72, 48, "logo-eu.png", fit="contain", name="logo-eu.png"))
    it.append(Text(ex + 100, ey + 25, ew - 116, 22, [P("Sub Head", "وبدعم من الاتحاد الأوروبي")], name="eu label"))
    return pg


def page_back():
    pg = Page("Back Cover", bg="Deep", master=False)
    it = pg.items
    cx = W / 2
    it += lattice((-BLEED, -BLEED, W + BLEED, H + BLEED), "Deep", (cx, 300), (230, 300), s=46, away=True)
    it.append(Rect(22, 22, W - 44, H - 44, stroke="Gold", sw=0.5, name="inner frame"))
    badge(it, cx, 172, 44, fill="Deep", sw=1.1)
    it.append(Text(110, 242, W - 220, 230, [
        P("Back Title", "منتدى نزاهة الأعمال والمسؤولية الاجتماعية العراقي"),
        P("Back English", "The Iraqi Forum for Business Integrity and Social Responsibility", sb=2),
        P("Back Theme", "من المسؤولية إلى الأثر المستدام", sb=34),
        P("Back Theme English", "From Responsibility to Sustainable Impact", sb=2),
        P("Back Date", "بغداد – جمهورية العراق   ·   الأحد ١ تشرين الثاني ٢٠٢٦", sb=26),
        P("Caps Latin Centre", "Baghdad · Republic of IRAQ · 01 November 2026", sb=4),
    ], name="back text"))
    dy = 242 + 42 + 2 + 18 + 17
    it.append(Poly(diamond(cx, dy, 3), fill="Gold", name="divider diamond"))
    it.append(Line(cx - 60, dy, cx - 8, dy, "Gold", 0.75))
    it.append(Line(cx + 8, dy, cx + 60, dy, "Gold", 0.75))
    return pg


def pages():
    return [page_cover(), page_about(), page_objectives(), page_participants(),
            page_programme(), page_partnership(), page_partners(), page_back()]
