"""Render the spec to an InDesign IDML package.

Structure (designmap, preferences, default styles) comes from a real
InDesign-exported IDML in src/idml-template; this script replaces the
template's pages, stories, swatches and styles with the profile's own.

Vertical text placement mirrors the HTML/PDF renderer exactly:
  * each frame's first baseline is fixed (FirstBaselineOffset=FixedHeight);
  * paragraph spacing is converted from the CSS box model into InDesign's
    baseline-to-baseline model (SpaceBefore override per paragraph).
"""
import re
import shutil
import zipfile
from pathlib import Path
from xml.sax.saxutils import escape, quoteattr

import spec
from spec import W, H, BLEED, S, C, COLORS, Rect, Line, Poly, Img, Text
from render_html import place

ROOT = Path(__file__).resolve().parents[1]
TPL = ROOT / "src" / "idml-template"
BUILD = ROOT / "build"
STAGE = BUILD / "idml"

PKG = 'xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" DOMVersion="10.0"'
HDR = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
LAYER = "ua4"
SECTION = "u9b"
MASTER = "ua5"


class Ids:
    def __init__(self):
        self.n = 0x2000

    def __call__(self):
        self.n += 1
        return f"u{self.n:x}"


uid = Ids()


def f(v):
    return f"{v:.4f}".rstrip("0").rstrip(".")


def swatch(name):
    return f"Color/CSR {name}" if name else "Swatch/None"


def path_xml(points, closed=True, curves=None):
    pts = []
    for i, (x, y) in enumerate(points):
        if curves:
            (lx, ly), (rx, ry) = curves[i]
        else:
            lx, ly, rx, ry = x, y, x, y
        pts.append(f'<PathPointType Anchor="{f(x)} {f(y)}" LeftDirection="{f(lx)} {f(ly)}" '
                   f'RightDirection="{f(rx)} {f(ry)}"/>')
    return (f'<Properties><PathGeometry><GeometryPathType PathOpen="{"false" if closed else "true"}">'
            f'<PathPointArray>{"".join(pts)}</PathPointArray></GeometryPathType></PathGeometry></Properties>')


def to_spread(x, y):
    # Non-facing documents: InDesign centres each single-page spread on the spread origin.
    return x - W / 2, y - H / 2


def rect_pts(x, y, w, h):
    (x1, y1), (x2, y2) = to_spread(x, y), to_spread(x + w, y + h)
    return [(x1, y1), (x1, y2), (x2, y2), (x2, y1)]


def oval_pts(x, y, w, h):
    k = 0.5522847498
    cx, cy = to_spread(x + w / 2, y + h / 2)
    rx, ry = w / 2, h / 2
    pts = [(cx - rx, cy), (cx, cy + ry), (cx + rx, cy), (cx, cy - ry)]
    curves = [
        ((cx - rx, cy - k * ry), (cx - rx, cy + k * ry)),
        ((cx - k * rx, cy + ry), (cx + k * rx, cy + ry)),
        ((cx + rx, cy + k * ry), (cx + rx, cy - k * ry)),
        ((cx + k * rx, cy - ry), (cx - k * rx, cy - ry)),
    ]
    return pts, curves


def common(name):
    return (f'ItemLayer="{LAYER}" Locked="false" Visible="true" Name={quoteattr(name or "$ID/")} '
            f'AppliedObjectStyle="ObjectStyle/$ID/[None]" ItemTransform="1 0 0 1 0 0"')


# ---------------------------------------------------------------- items ---
def rect_xml(r):
    stroke = (f'StrokeColor="{swatch(r.stroke)}" StrokeWeight="{f(r.sw)}" StrokeAlignment="InsideAlignment"'
              if r.stroke else 'StrokeColor="Swatch/None" StrokeWeight="0"')
    return (f'<Rectangle Self="{uid()}" ContentType="Unassigned" {common(r.name)} '
            f'FillColor="{swatch(r.fill)}" {stroke}>{path_xml(rect_pts(r.x, r.y, r.w, r.h))}</Rectangle>')


def line_xml(l):
    pts = [to_spread(l.x1, l.y1), to_spread(l.x2, l.y2)]
    return (f'<GraphicLine Self="{uid()}" ContentType="Unassigned" {common(l.name)} FillColor="Swatch/None" '
            f'StrokeColor="{swatch(l.color)}" StrokeWeight="{f(l.sw)}">{path_xml(pts, closed=False)}</GraphicLine>')


def poly_xml(p):
    stroke = (f'StrokeColor="{swatch(p.stroke)}" StrokeWeight="{f(p.sw)}"' if p.stroke
              else 'StrokeColor="Swatch/None" StrokeWeight="0"')
    pts = [to_spread(x, y) for x, y in p.points]
    return (f'<Polygon Self="{uid()}" ContentType="Unassigned" {common(p.name)} '
            f'FillColor="{swatch(p.fill)}" {stroke}>{path_xml(pts)}</Polygon>')


def img_xml(im):
    s, tx, ty, iw, ih = place(im)
    tx, ty = to_spread(tx, ty)
    png = im.src.endswith(".png")
    fmt = "$ID/Portable Network Graphics (PNG)" if png else "$ID/JPEG"
    eff = round(72 / s)
    image = (
        f'<Image Self="{uid()}" Space="$ID/#Links_RGB" ActualPpi="72 72" EffectivePpi="{eff} {eff}" '
        f'ImageRenderingIntent="UseColorSettings" LocalDisplaySetting="Default" ImageTypeName="{fmt}" '
        f'AppliedObjectStyle="ObjectStyle/$ID/[None]" ItemTransform="{f(s)} 0 0 {f(s)} {f(tx)} {f(ty)}" '
        f'Visible="true" Name="$ID/">'
        f'<Properties><Profile type="string">$ID/None</Profile>'
        f'<GraphicBounds Left="0" Top="0" Right="{iw}" Bottom="{ih}"/></Properties>'
        f'<Link Self="{uid()}" AssetURL="$ID/" AssetID="$ID/" LinkResourceURI="file:Links/{im.src}" '
        f'LinkResourceFormat="{fmt}" StoredState="Normal" LinkClassID="35906" LinkClientID="257" '
        f'LinkResourceModified="false" LinkObjectModified="false" ShowInUI="true" CanEmbed="true" '
        f'CanUnembed="true" CanPackage="true" ImportPolicy="NoAutoImport" ExportPolicy="NoAutoExport"/>'
        f'</Image>')
    if im.oval:
        pts, curves = oval_pts(im.x, im.y, im.w, im.h)
        geo = path_xml(pts, curves=curves)
        tag = "Oval"
    else:
        geo = path_xml(rect_pts(im.x, im.y, im.w, im.h))
        tag = "Rectangle"
    return (f'<{tag} Self="{uid()}" ContentType="GraphicType" {common(im.name)} FillColor="Swatch/None" '
            f'StrokeColor="Swatch/None" StrokeWeight="0">{geo}'
            f'<FrameFittingOption FittingOnEmptyFrame="FillProportionally"/>{image}</{tag}>')


def css_metrics(para):
    st = S[para.style]
    bw = st.rule_above[1] if st.rule_above else 0
    above = para.space_before + bw + st.pad_top + st.lead / 2 + st.delta      # box top -> first baseline
    below = st.lead / 2 - st.delta + st.pad_bottom + para.space_after         # last baseline -> box bottom
    return above, below


def text_xml(t, stories):
    sid = uid()
    ranges = []
    first_offset = css_metrics(t.paras[0])[0]
    prev_below = None
    for i, para in enumerate(t.paras):
        st = S[para.style]
        above, below = css_metrics(para)
        attrs = f'AppliedParagraphStyle="ParagraphStyle/{escape(st.name)}"'
        if i:
            sb = prev_below + above - st.lead
            if sb < -0.01:
                print(f"  warn: negative space before in {t.name!r} para {i}: {sb:.2f}")
            attrs += f' SpaceBefore="{f(max(sb, 0))}" SpaceAfter="0"'
        else:
            attrs += ' SpaceBefore="0" SpaceAfter="0"'
        prev_below = below
        runs = []
        for k, (txt, cs) in enumerate(para.runs):
            cs_ref = f"CharacterStyle/{cs}" if cs else "CharacterStyle/$ID/[No character style]"
            body = "<Content><?ACE 18?></Content>" if (txt == "#" and st.name == "Folio") else f"<Content>{escape(txt)}</Content>"
            if k == len(para.runs) - 1 and i < len(t.paras) - 1:
                body += "<Br/>"
            runs.append(f'<CharacterStyleRange AppliedCharacterStyle="{cs_ref}">{body}</CharacterStyleRange>')
        ranges.append(f"<ParagraphStyleRange {attrs}>{''.join(runs)}</ParagraphStyleRange>")
    rtl = S[t.paras[0].style].rtl
    stories[sid] = (
        f'{HDR}<idPkg:Story {PKG}><Story Self="{sid}" AppliedTOCStyle="n" TrackChanges="false" '
        f'StoryTitle={quoteattr(t.name or "$ID/")} AppliedNamedGrid="n">'
        f'<StoryPreference OpticalMarginAlignment="false" OpticalMarginSize="12" FrameType="TextFrameType" '
        f'StoryOrientation="Horizontal" StoryDirection="{"RightToLeftDirection" if rtl else "LeftToRightDirection"}"/>'
        f'<InCopyExportOption IncludeGraphicProxies="true" IncludeAllResources="false"/>'
        f'{"".join(ranges)}</Story></idPkg:Story>')
    return (f'<TextFrame Self="{uid()}" ParentStory="{sid}" PreviousTextFrame="n" NextTextFrame="n" '
            f'ContentType="TextType" {common(t.name)} FillColor="Swatch/None" StrokeColor="Swatch/None" StrokeWeight="0">'
            f'{path_xml(rect_pts(t.x, t.y, t.w, t.h))}'
            f'<TextFramePreference TextColumnCount="1" TextColumnFixedWidth="{f(t.w)}" UseFixedColumnWidth="false" '
            f'FirstBaselineOffset="FixedHeight" MinimumFirstBaselineOffset="{f(first_offset)}" '
            f'VerticalJustification="TopAlign" AutoSizingType="Off" IgnoreWrap="true"/></TextFrame>')


def item_xml(it, stories):
    if isinstance(it, Rect):
        return rect_xml(it)
    if isinstance(it, Line):
        return line_xml(it)
    if isinstance(it, Poly):
        return poly_xml(it)
    if isinstance(it, Img):
        return img_xml(it)
    if isinstance(it, Text):
        return text_xml(it, stories)
    raise TypeError(it)


def page_xml(pid, name, master, alt=SECTION):
    return (f'<Page Self="{pid}" AppliedAlternateLayout="{alt}" LayoutRule="Off" '
            f'SnapshotBlendingMode="IgnoreLayoutSnapshots" OptionalPage="false" '
            f'GeometricBounds="0 0 {f(H)} {f(W)}" ItemTransform="1 0 0 1 {f(-W / 2)} {f(-H / 2)}" Name="{name}" '
            f'AppliedTrapPreset="TrapPreset/$ID/kDefaultTrapStyleName" OverrideList="" AppliedMaster="{master}" '
            f'MasterPageTransform="1 0 0 1 0 0" TabOrder="" GridStartingPoint="TopOutside" UseMasterGrid="true">'
            f'<Properties><PageColor type="enumeration">UseMasterColor</PageColor></Properties>'
            f'<MarginPreference ColumnCount="1" ColumnGutter="12" Top="68" Bottom="62" Left="{f(spec.M)}" '
            f'Right="{f(spec.M)}" ColumnDirection="Horizontal" ColumnsPositions="0 {f(W - 2 * spec.M)}"/></Page>')


# --------------------------------------------------------------- styles ---
def para_style_xml(st):
    just = {"right": "RightAlign", "left": "LeftAlign", "center": "CenterAlign",
            "justify": "RightJustified" if st.rtl else "LeftJustified"}[st.align]
    a = [
        f'Self="ParagraphStyle/{escape(st.name)}"', f'Name={quoteattr(st.name)}', 'Imported="false"',
        f'NextStyle="ParagraphStyle/{escape(st.name)}"', 'KeyboardShortcut="0 0"',
        f'FontStyle="{st.style}"', f'PointSize="{f(st.size)}"', f'FillColor="{swatch(st.color)}"',
        f'Justification="{just}"', 'Hyphenation="false"', f'Tracking="{st.tracking}"',
        f'Capitalization="{"AllCaps" if st.caps else "Normal"}"', 'SpaceBefore="0"', 'SpaceAfter="0"',
        f'ParagraphDirection="{"RightToLeftDirection" if st.rtl else "LeftToRightDirection"}"',
        f'Composer="{"HL Composer Optyca" if st.rtl else "HL Composer"}"',
        'KeepLinesTogether="false"', 'KeepFirstLines="1"', 'KeepLastLines="1"',
    ]
    props = [
        '<BasedOn type="string">$ID/[No paragraph style]</BasedOn>',
        '<PreviewColor type="enumeration">Nothing</PreviewColor>',
        f'<AppliedFont type="string">{escape(st.family)}</AppliedFont>',
        f'<Leading type="unit">{f(st.lead)}</Leading>',
    ]
    if st.rule_above:
        c, wgt = st.rule_above
        a += ['RuleAbove="true"', f'RuleAboveLineWeight="{f(wgt)}"',
              f'RuleAboveOffset="{f(st.pad_top + st.lead / 2 + st.delta)}"', 'RuleAboveWidth="ColumnWidth"',
              'RuleAboveTint="100"']
        props.append(f'<RuleAboveColor type="object">{swatch(c)}</RuleAboveColor>')
    return f'<ParagraphStyle {" ".join(a)}><Properties>{"".join(props)}</Properties></ParagraphStyle>'


def char_style_xml(name, d):
    attrs = f' FillColor="{swatch(d["color"])}"' if "color" in d else ""
    props = ""
    if "family" in d:
        attrs += f' FontStyle="{d["style"]}"'
        props = f'<AppliedFont type="string">{escape(d["family"])}</AppliedFont>'
    return (f'<CharacterStyle Self="CharacterStyle/{escape(name)}" Imported="false" KeyboardShortcut="0 0" '
            f'Name={quoteattr(name)}{attrs}><Properties>'
            f'<BasedOn type="string">$ID/[No character style]</BasedOn>'
            f'<PreviewColor type="enumeration">Nothing</PreviewColor>{props}</Properties></CharacterStyle>')


def color_xml(name, hexv):
    r, g, b = (int(hexv[i:i + 2], 16) for i in (0, 2, 4))
    return (f'<Color Self="Color/CSR {name}" Model="Process" Space="RGB" ColorValue="{r} {g} {b}" '
            f'ColorOverride="Normal" AlternateSpace="NoAlternateColor" AlternateColorValue="" '
            f'Name="CSR {name}" ColorEditable="true" ColorRemovable="true" Visible="true" SwatchCreatorID="7937"/>')


# ---------------------------------------------------------------- build ---
def build(pages, out_path):
    if STAGE.exists():
        shutil.rmtree(STAGE)
    shutil.copytree(TPL, STAGE)
    for d in ("Spreads", "Stories", "MasterSpreads"):
        shutil.rmtree(STAGE / d)
        (STAGE / d).mkdir()

    stories = {}

    # master spread with running head and folio
    mpage = uid()
    mitems = "".join(item_xml(it, stories) for it in spec.master_items())
    (STAGE / "MasterSpreads" / f"MasterSpread_{MASTER}.xml").write_text(
        f'{HDR}<idPkg:MasterSpread {PKG}><MasterSpread Self="{MASTER}" ItemTransform="1 0 0 1 0 0" '
        f'OverriddenPageItemProps="" Name="A-Inner" NamePrefix="A" BaseName="Inner" ShowMasterItems="true" '
        f'PageCount="1" PrimaryTextFrame="n"><Properties><PageColor type="enumeration">UseMasterColor</PageColor>'
        f'</Properties>{page_xml(mpage, "A", "n", alt="n")}{mitems}</MasterSpread></idPkg:MasterSpread>',
        encoding="utf-8")

    spread_ids, first_page = [], None
    for n, pg in enumerate(pages, start=1):
        sp, pid = uid(), uid()
        first_page = first_page or pid
        items = []
        if pg.bg:
            items.append(rect_xml(Rect(-BLEED, -BLEED, W + 2 * BLEED, H + 2 * BLEED, fill=pg.bg, name="paper")))
        items += [item_xml(it, stories) for it in pg.items]
        (STAGE / "Spreads" / f"Spread_{sp}.xml").write_text(
            f'{HDR}<idPkg:Spread {PKG}><Spread Self="{sp}" FlattenerOverride="Default" AllowPageShuffle="true" '
            f'ItemTransform="1 0 0 1 0 0" ShowMasterItems="true" PageCount="1" BindingLocation="0" '
            f'PageTransitionType="None" PageTransitionDirection="NotApplicable" PageTransitionDuration="Medium">'
            f'{page_xml(pid, str(n), MASTER if pg.master else "n")}{"".join(items)}</Spread></idPkg:Spread>',
            encoding="utf-8")
        spread_ids.append(sp)

    for sid, xml in stories.items():
        (STAGE / "Stories" / f"Story_{sid}.xml").write_text(xml, encoding="utf-8")

    # backing story without the template's XML structure links
    (STAGE / "XML" / "BackingStory.xml").write_text(
        f'{HDR}<idPkg:BackingStory {PKG}><XmlStory Self="u83" AppliedTOCStyle="n" TrackChanges="false" '
        f'StoryTitle="$ID/" AppliedNamedGrid="n"><ParagraphStyleRange '
        f'AppliedParagraphStyle="ParagraphStyle/$ID/NormalParagraphStyle"><CharacterStyleRange '
        f'AppliedCharacterStyle="CharacterStyle/$ID/[No character style]"><XMLElement Self="di3" '
        f'MarkupTag="XMLTag/Root"/></CharacterStyleRange></ParagraphStyleRange></XmlStory></idPkg:BackingStory>',
        encoding="utf-8")

    # swatches
    g = (STAGE / "Resources" / "Graphic.xml").read_text(encoding="utf-8")
    colors = "".join(color_xml(k, v) for k, v in COLORS.items())
    g = g.replace('\t<Ink Self="Ink/$ID/Process Cyan"', colors + '\n\t<Ink Self="Ink/$ID/Process Cyan"', 1)
    (STAGE / "Resources" / "Graphic.xml").write_text(g, encoding="utf-8")

    # styles
    st = (STAGE / "Resources" / "Styles.xml").read_text(encoding="utf-8")
    pxml = "".join(para_style_xml(s) for s in S.values())
    cxml = "".join(char_style_xml(k, v) for k, v in C.items())
    st = st.replace("\t</RootParagraphStyleGroup>", pxml + "\n\t</RootParagraphStyleGroup>", 1)
    st = st.replace("\t</RootCharacterStyleGroup>", cxml + "\n\t</RootCharacterStyleGroup>", 1)
    (STAGE / "Resources" / "Styles.xml").write_text(st, encoding="utf-8")

    # document preferences: A4 landscape, single pages, 3 mm bleed
    pr = (STAGE / "Resources" / "Preferences.xml").read_text(encoding="utf-8")

    def setattr_(tag, **kv):
        nonlocal pr
        m = re.search(rf"<{tag} [^>]*>", pr)
        el = m.group(0)
        for k, v in kv.items():
            el = re.sub(rf'{k}="[^"]*"', f'{k}="{v}"', el)
        pr = pr.replace(m.group(0), el, 1)

    setattr_("DocumentPreference", PageHeight=f(H), PageWidth=f(W), PagesPerDocument=str(len(pages)),
             FacingPages="false", DocumentBleedTopOffset=f(BLEED), DocumentBleedBottomOffset=f(BLEED),
             DocumentBleedInsideOrLeftOffset=f(BLEED), DocumentBleedOutsideOrRightOffset=f(BLEED))
    setattr_("MarginPreference", Top="68", Bottom="62", Left=f(spec.M), Right=f(spec.M))
    (STAGE / "Resources" / "Preferences.xml").write_text(pr, encoding="utf-8")

    # designmap
    dm = (TPL / "designmap.xml").read_text(encoding="utf-8")
    story_ids = list(stories) + ["u83"]
    dm = re.sub(r'StoryList="[^"]*"', f'StoryList="{" ".join(story_ids)}"', dm, count=1)
    dm = re.sub(r"\t<idPkg:(Spread|MasterSpread|Story) src=\"[^\"]*\" />\n", "", dm)
    dm = re.sub(r"\t<Section Self=.*?</Section>\n", "", dm, flags=re.S)
    refs = (f'\t<idPkg:MasterSpread src="MasterSpreads/MasterSpread_{MASTER}.xml" />\n'
            + "".join(f'\t<idPkg:Spread src="Spreads/Spread_{s}.xml" />\n' for s in spread_ids)
            + f'\t<Section Self="{SECTION}" Length="{len(pages)}" AlternateLayoutLength="{len(pages)}" AlternateLayout="A4 H" Name="" ContinueNumbering="false" '
              f'IncludeSectionPrefix="false" PageNumberStart="1" Marker="" PageStart="{first_page}" SectionPrefix="">'
              f'<Properties><PageNumberStyle type="enumeration">Arabic</PageNumberStyle></Properties></Section>\n')
    dm = dm.replace("\t<DocumentUser ", refs + "\t<DocumentUser ", 1)
    dm = dm.replace('\t<idPkg:BackingStory src="XML/BackingStory.xml" />\n',
                    '\t<idPkg:BackingStory src="XML/BackingStory.xml" />\n'
                    + "".join(f'\t<idPkg:Story src="Stories/Story_{s}.xml" />\n' for s in stories), 1)
    n0 = dm.count("ColorGroupSwatch Self=")
    cg = "".join(f'\t\t<ColorGroupSwatch Self="u207ColorGroupSwatch{n0 + i}" SwatchItemRef="Color/CSR {k}" />\n'
                 for i, k in enumerate(COLORS))
    dm = dm.replace("\t</ColorGroup>", cg + "\t</ColorGroup>", 1)
    (STAGE / "designmap.xml").write_text(dm, encoding="utf-8")

    # zip: mimetype first, stored
    out_path = Path(out_path)
    with zipfile.ZipFile(out_path, "w") as z:
        z.write(STAGE / "mimetype", "mimetype", compress_type=zipfile.ZIP_STORED)
        for p in sorted(STAGE.rglob("*")):
            if p.is_file() and p.name != "mimetype":
                z.write(p, p.relative_to(STAGE).as_posix(), compress_type=zipfile.ZIP_DEFLATED)
    return out_path


if __name__ == "__main__":
    out = build(spec.pages(), BUILD / "profile.idml")
    print("wrote", out)
