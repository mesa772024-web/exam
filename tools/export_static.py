# -*- coding: utf-8 -*-
"""Export a running copy of a site package as a static English preview
(for GitHub Pages, which cannot run PHP).

Every public page reachable from the home page is saved as .html, links are
made relative (so the preview works from any sub-folder) and the assets the
pages use are copied. Forms and the chat need the real PHP hosting.

usage: python3 tools/export_static.py http://127.0.0.1:8301 baghdad-alhayat out/
"""
import os
import re
import shutil
import sys
import urllib.request

BASE, PKG, OUT = sys.argv[1].rstrip('/'), sys.argv[2], sys.argv[3]
PAGE_RE = re.compile(r'(href|action)="(?:\./|/)?([a-z0-9_-]+)\.php(\?[^"#]*)?(#[^"]*)?"')
os.makedirs(OUT, exist_ok=True)


def fetch(path):
    with urllib.request.urlopen(BASE + '/' + path) as r:
        return r.read().decode('utf8')


def rewrite(html):
    html = PAGE_RE.sub(lambda m: f'{m.group(1)}="{m.group(2)}.html{m.group(4) or ""}"', html)
    html = re.sub(r'(href|src|poster|data-source)="/(?!/)', r'\1="', html)
    html = re.sub(r'url\((["\']?)/(?!/)', r'url(\1', html)
    html = html.replace('href="index.html#', 'href="index.html#').replace('href="/"', 'href="index.html"')
    html = re.sub(r'\sdata-customization-api="[^"]*"', '', html)
    return html


queue, seen, used = ['index'], set(), set()
while queue:
    name = queue.pop(0)
    if name in seen:
        continue
    seen.add(name)
    try:
        html = fetch(name + '.php')
    except Exception as e:  # pages that need a slug etc.
        print('skip', name, e)
        continue
    for m in PAGE_RE.finditer(html):
        if m.group(1) == 'href' and m.group(2) not in seen and not m.group(2).startswith(('media', 'sandbox')):
            queue.append(m.group(2))
    out = rewrite(html)
    used.update(re.findall(r'(?:src|href|poster|data-source)="(assets/[^"?#]+)', out))
    used.update(re.findall(r'url\(["\']?(assets/[^"\')?#]+)', out))
    open(os.path.join(OUT, name + '.html'), 'w', encoding='utf8').write(out)
    print('page', name)

# assets: everything except the duplicate cinematic sources the pages never load
for base, dirs, files in os.walk(os.path.join(PKG, 'assets')):
    rel = os.path.relpath(base, PKG)
    if rel.startswith(os.path.join('assets', 'cinematic')) and not any(u.startswith('assets/cinematic') for u in used):
        continue
    for f in files:
        dst = os.path.join(OUT, rel, f)
        os.makedirs(os.path.dirname(dst), exist_ok=True)
        shutil.copy2(os.path.join(base, f), dst)
open(os.path.join(OUT, '.nojekyll'), 'w').close()
print('assets copied;', len(seen), 'pages')
