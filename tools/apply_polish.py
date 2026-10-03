# -*- coding: utf-8 -*-
"""Add the CSS-only visual polish to a site package (idempotent).

  * home page  : tools/polish-home.css  → end of the main <style> block in index.php
  * inner pages: tools/polish-inner.css → end of assets/css/landing-pages.css
With --recolor the polish colours are mapped to the Baghdad Al Hayat burgundy
(same mapping as rebrand_baghdad.py).

usage: python3 tools/apply_polish.py alhayat
       python3 tools/apply_polish.py baghdad-alhayat --recolor
"""
import importlib.util
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
PKG = sys.argv[1]
RECOLOR = '--recolor' in sys.argv
START, END = '/* >>> polish */', '/* <<< polish */'


def load(name):
    css = open(os.path.join(HERE, name), encoding='utf8').read()
    if RECOLOR:
        spec = importlib.util.spec_from_file_location('rb', os.path.join(HERE, 'rebrand_baghdad.py'))
        rb = importlib.util.module_from_spec(spec)
        sys.argv, argv = [sys.argv[0], os.devnull], sys.argv   # module walks argv[1]; point it at nothing
        spec.loader.exec_module(rb)
        sys.argv = argv
        css = rb.recolor(css)
    return f'{START}{css}{END}'


def strip(text):
    return re.sub(re.escape(START) + r'.*?' + re.escape(END), '', text, flags=re.S)


p = os.path.join(PKG, 'index.php')
s = strip(open(p, encoding='utf8').read())
i = s.index('</style>')
s = s[:i] + load('polish-home.css') + '\n  ' + s[i:]
open(p, 'w', encoding='utf8').write(s)

p = os.path.join(PKG, 'assets', 'css', 'landing-pages.css')
s = strip(open(p, encoding='utf8').read()).rstrip('\n')
open(p, 'w', encoding='utf8').write(s + '\n' + load('polish-inner.css') + '\n')
print('polish applied to', PKG, '(recolored)' if RECOLOR else '')
