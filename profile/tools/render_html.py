"""Render the spec to HTML and print it to PDF with Chromium."""
import html
import json
from pathlib import Path

from PIL import Image

import spec
from spec import W, H, S, C, COLORS, FONT_STYLES, Rect, Line, Poly, Img, Text

ROOT = Path(__file__).resolve().parents[1]
BUILD = ROOT / "build"
CHROMIUM = "/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell"

_img_cache = {}


def img_size(name):
    if name not in _img_cache:
        _img_cache[name] = Image.open(BUILD / "Links" / name).size
    return _img_cache[name]


def place(img):
    """Scale and offset of an image inside its frame (shared with IDML)."""
    iw, ih = img_size(img.src)
    sx, sy = img.w / iw, img.h / ih
    s = max(sx, sy) if img.fit == "cover" else min(sx, sy)
    tx = img.x + (img.w - iw * s) * img.ax
    ty = img.y + (img.h - ih * s) * img.ay
    return s, tx, ty, iw, ih


def col(name):
    return "#" + COLORS[name]


TTF = {
    ("Readex Pro", 300): "ReadexPro_300Light", ("Readex Pro", 400): "ReadexPro_400Regular",
    ("Readex Pro", 500): "ReadexPro_500Medium", ("Readex Pro", 600): "ReadexPro_600SemiBold",
    ("Readex Pro", 700): "ReadexPro_700Bold",
    ("IBM Plex Sans Arabic", 300): "IBMPlexSansArabic_300Light",
    ("IBM Plex Sans Arabic", 400): "IBMPlexSansArabic_400Regular",
    ("IBM Plex Sans Arabic", 500): "IBMPlexSansArabic_500Medium",
    ("IBM Plex Sans Arabic", 600): "IBMPlexSansArabic_600SemiBold",
    ("IBM Plex Sans Arabic", 700): "IBMPlexSansArabic_700Bold",
    ("Amiri", 400): "Amiri_400Regular", ("Amiri", 700): "Amiri_700Bold",
    ("DM Serif Display", 400): "DMSerifDisplay_400Regular",
    ("DM Serif Display", 400, "italic"): "DMSerifDisplay_400Regular_Italic",
    ("Noto Sans Symbols 2", 400): "NotoSansSymbols2_400Regular",
}


def font_faces():
    """The same Google Fonts TTFs the designer installs for InDesign."""
    css = []
    for key, fn in TTF.items():
        fam, wgt = key[0], key[1]
        st = key[2] if len(key) > 2 else "normal"
        css.append(f"@font-face{{font-family:'{fam}';font-style:{st};font-weight:{wgt};"
                   f"src:url('fonts-ttf/{fn}.ttf') format('truetype');}}")
    return "\n".join(css)


def span_css(cs):
    d = C[cs]
    css = []
    if "color" in d:
        css.append(f"color:{col(d['color'])}")
    if "family" in d:
        wgt, ital = FONT_STYLES[d["style"]]
        css += [f"font-family:'{d['family']}'", f"font-weight:{wgt}", "line-height:0"]
    return ";".join(css)


def p_css(st, para, first):
    wgt, ital = FONT_STYLES[st.style]
    align = {"justify": "justify", "right": "right", "left": "left", "center": "center"}[st.align]
    css = [
        f"font-family:'{st.family}','Amiri',serif",
        f"font-weight:{wgt}", f"font-style:{'italic' if ital else 'normal'}",
        f"font-size:{st.size}pt", f"line-height:{st.lead}pt", f"color:{col(st.color)}",
        f"text-align:{align}", f"direction:{'rtl' if st.rtl else 'ltr'}",
        f"padding-top:{st.pad_top}pt", f"padding-bottom:{st.pad_bottom}pt",
        f"margin-top:{para.space_before}pt", f"margin-bottom:{para.space_after}pt",
    ]
    if st.align == "justify":
        css.append(f"text-align-last:{'right' if st.rtl else 'left'}")
    if st.tracking:
        css.append(f"letter-spacing:{st.tracking / 1000}em")
    if st.caps:
        css.append("text-transform:uppercase")
    if st.rule_above:
        c, wgt_ = st.rule_above
        css.append(f"border-top:{wgt_}pt solid {col(c)}")
    return ";".join(css)


def render_text(t, page_no, tid):
    out = [f'<div class="tf" id="{tid}" data-name="{html.escape(t.name)}" '
           f'style="left:{t.x}pt;top:{t.y}pt;width:{t.w}pt;height:{t.h}pt">']
    for i, para in enumerate(t.paras):
        st = S[para.style]
        runs = []
        for text, cs in para.runs:
            text = str(page_no) if text == "#" and para.style == "Folio" else text
            e = html.escape(text)
            runs.append(f'<span style="{span_css(cs)}">{e}</span>' if cs else e)
        out.append(f'<p data-style="{st.name}" style="{p_css(st, para, i == 0)}">{"".join(runs)}</p>')
    out.append("</div>")
    return "".join(out)


def render_item(it, page_no, tid):
    if isinstance(it, Rect):
        css = [f"left:{it.x}pt", f"top:{it.y}pt", f"width:{it.w}pt", f"height:{it.h}pt"]
        if it.fill:
            css.append(f"background:{col(it.fill)}")
        if it.stroke:
            css.append(f"border:{it.sw}pt solid {col(it.stroke)}")
        return f'<div class="box" style="{";".join(css)}"></div>'
    if isinstance(it, Line):
        return (f'<svg class="vec" viewBox="0 0 {W} {H}"><line x1="{it.x1}" y1="{it.y1}" x2="{it.x2}" '
                f'y2="{it.y2}" stroke="{col(it.color)}" stroke-width="{it.sw}"/></svg>')
    if isinstance(it, Poly):
        pts = " ".join(f"{x:.3f},{y:.3f}" for x, y in it.points)
        fill = col(it.fill) if it.fill else "none"
        stroke = f'stroke="{col(it.stroke)}" stroke-width="{it.sw}"' if it.stroke else ""
        return f'<svg class="vec" viewBox="0 0 {W} {H}"><polygon points="{pts}" fill="{fill}" {stroke}/></svg>'
    if isinstance(it, Img):
        s, tx, ty, iw, ih = place(it)
        rad = "border-radius:50%;" if it.oval else ""
        return (f'<div class="imgf" style="{rad}left:{it.x}pt;top:{it.y}pt;width:{it.w}pt;height:{it.h}pt">'
                f'<img src="Links/{it.src}" style="left:{tx - it.x}pt;top:{ty - it.y}pt;'
                f'width:{iw * s}pt;height:{ih * s}pt"></div>')
    if isinstance(it, Text):
        return render_text(it, page_no, tid)
    raise TypeError(it)


def build_html(pages):
    master = spec.master_items()
    body = []
    n = 0
    for i, pg in enumerate(pages, start=1):
        bg = f"background:{col(pg.bg)};" if pg.bg else "background:#fff;"
        body.append(f'<section class="page" data-page="{i}" style="{bg}">')
        items = (master if pg.master else []) + pg.items
        for it in items:
            n += 1
            body.append(render_item(it, i, f"t{i}_{n}"))
        body.append("</section>")
    return f"""<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
<title>Iraqi CSR Forum 2026 — Profile</title>
<style>
{font_faces()}
@page {{ size: {W}pt {H}pt; margin: 0; }}
* {{ box-sizing: border-box; }}
html, body {{ margin: 0; padding: 0; background: #888; }}
.page {{ position: relative; width: {W}pt; height: {H}pt; overflow: hidden; page-break-after: always;
         margin: 0 auto; -webkit-print-color-adjust: exact; print-color-adjust: exact; }}
.page > * {{ position: absolute; }}
.vec {{ left: 0; top: 0; width: {W}pt; height: {H}pt; overflow: visible; }}
.imgf {{ overflow: hidden; }}
.imgf img {{ position: absolute; max-width: none; }}
.tf {{ display: flex; flex-direction: column; overflow: hidden; }}
.tf p {{ margin: 0; font-kerning: normal; font-feature-settings: "kern"; }}
@media screen {{ .page {{ margin: 20px auto; box-shadow: 0 4px 30px rgba(0,0,0,.25); }} }}
</style></head><body>
{''.join(body)}
</body></html>"""


CHECK_JS = """
() => [...document.querySelectorAll('.tf')].map(el => {
  const ps = [...el.children];
  const top = el.getBoundingClientRect().top;
  const last = ps.length ? ps[ps.length-1].getBoundingClientRect().bottom : top;
  const lines = ps.map(p => {
     const cs = getComputedStyle(p);
     const h = p.getBoundingClientRect().height - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom) - parseFloat(cs.borderTopWidth);
     return Math.round(h / parseFloat(cs.lineHeight));
  });
  return {id: el.id, name: el.dataset.name, page: el.closest('.page').dataset.page,
          over: false, slack: (el.clientHeight - (last - top)) * 0.75,
          lines, maxLead: Math.max(...ps.map(p => parseFloat(getComputedStyle(p).lineHeight)*0.75))};
})
"""


def render(pages, pdf_path, png_dir=None, scale=1.5):
    from playwright.sync_api import sync_playwright
    out = BUILD / "profile.html"
    out.write_text(build_html(pages), encoding="utf-8")
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path=CHROMIUM)
        pg = b.new_page(viewport={"width": int(W * 4 / 3) + 40, "height": 900}, device_scale_factor=scale)
        pg.goto(out.as_uri())
        pg.evaluate("document.fonts.ready")
        pg.wait_for_timeout(400)
        report = pg.evaluate(CHECK_JS)
        if png_dir:
            Path(png_dir).mkdir(parents=True, exist_ok=True)
            for el in pg.query_selector_all(".page"):
                el.screenshot(path=str(Path(png_dir) / f"page-{el.get_attribute('data-page')}.png"))
        pg.emulate_media(media="print")
        pg.pdf(path=str(pdf_path), prefer_css_page_size=True, print_background=True)
        b.close()
    return report


if __name__ == "__main__":
    import sys
    rep = render(spec.pages(), BUILD / "profile.pdf", BUILD / "png")
    for r in rep:
        flag = "OVERFLOW" if r["slack"] < -0.5 else ("tight" if (r["slack"] < r["maxLead"] and sum(r["lines"]) > len(r["lines"])) else "")
        if flag or "-v" in sys.argv:
            print(r["page"], r["name"], flag, round(r["slack"], 1), r["lines"])
    json.dump(rep, open(BUILD / "fit-report.json", "w"), ensure_ascii=False, indent=1)
