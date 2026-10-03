# -*- coding: utf-8 -*-
"""Install the ambient motion layer (animated backgrounds) into a site package (idempotent).

Copies tools/ambient/* to <pkg>/assets/ambient/ and links it from the home page
(index.php) and from the shared inner-page layout (includes/v2-layout.php).
With --recolor the CSS palette is mapped to Baghdad Al Hayat burgundy.
usage: python3 tools/apply_ambient.py alhayat
       python3 tools/apply_ambient.py baghdad-alhayat --recolor
"""
import importlib.util, os, re, shutil, sys

HERE = os.path.dirname(os.path.abspath(__file__))
PKG = sys.argv[1]
RECOLOR = '--recolor' in sys.argv
VER = '1.0.0'

dst = os.path.join(PKG, 'assets', 'ambient')
os.makedirs(dst, exist_ok=True)
shutil.copy2(os.path.join(HERE, 'ambient', 'ambient.js'), os.path.join(dst, 'ambient.js'))
css = open(os.path.join(HERE, 'ambient', 'ambient.css'), encoding='utf8').read()
if RECOLOR:
    spec = importlib.util.spec_from_file_location('rb', os.path.join(HERE, 'rebrand_baghdad.py'))
    rb = importlib.util.module_from_spec(spec)
    argv, sys.argv = sys.argv, [sys.argv[0], os.devnull]
    spec.loader.exec_module(rb)
    sys.argv = argv
    css = rb.recolor(css)
open(os.path.join(dst, 'ambient.css'), 'w', encoding='utf8').write(css)

LINK = '<link rel="stylesheet" href="<?= h(site_url(\'assets/ambient/ambient.css?v=%s\')) ?>">' % VER
SCRIPT = '<script src="<?= h(site_url(\'assets/ambient/ambient.js?v=%s\')) ?>" defer></script>' % VER


def inject(path, head_anchor, body_anchor):
    s = open(path, encoding='utf8').read()
    s = re.sub(r'\s*<link rel="stylesheet" href="<\?= h\(site_url\(\'assets/ambient/ambient\.css[^\n]*', '', s)
    s = re.sub(r'\s*<script src="<\?= h\(site_url\(\'assets/ambient/ambient\.js[^\n]*', '', s)
    assert head_anchor in s and body_anchor in s, path
    s = s.replace(head_anchor, head_anchor + '\n  ' + LINK, 1)
    i = s.rindex(body_anchor)
    s = s[:i] + SCRIPT + '\n' + s[i:]
    open(path, 'w', encoding='utf8').write(s)


inject(os.path.join(PKG, 'index.php'),
       '<link rel="stylesheet" href="<?= h(site_url(\'assets/css/landing-fonts.css\')) ?>">', '</body>')
inject(os.path.join(PKG, 'includes', 'v2-layout.php'),
       '<link rel="stylesheet" href="<?= h(site_url(\'assets/css/landing-pages.css?v=3.0.0\')) ?>">', '</body></html>')
print('ambient installed in', PKG, '(recolored)' if RECOLOR else '')
