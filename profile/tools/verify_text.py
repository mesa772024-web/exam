"""Prove the layout uses exactly the Word file's text: no word added, removed or changed.

Compares the multiset of paragraphs (whitespace-trimmed) in the .docx body and
header against the multiset of paragraphs in the spec. Exit code 1 on any
difference.
"""
import sys
import zipfile
from collections import Counter
from pathlib import Path

from lxml import etree

import spec

ROOT = Path(__file__).resolve().parents[1]
DOCX = ROOT / "src" / "Iraqi_CSR_and_Business_Integrity_Forum_2026_Profile.docx"
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"


def norm(s):
    return " ".join(s.replace("​", "").split())


def docx_paragraphs():
    z = zipfile.ZipFile(DOCX)
    out = []
    for part in ("word/document.xml", "word/header1.xml"):
        root = etree.fromstring(z.read(part))
        for p in root.iter(W + "p"):
            # text of this paragraph only (nested text-box paragraphs are visited on their own)
            txt = "".join(t.text or "" for t in p.iter(W + "t")
                          if next(a for a in t.iterancestors(W + "p")) is p)
            if norm(txt):
                out.append(norm(txt))
    return out


def spec_paragraphs():
    out = []
    items = spec.master_items() + [i for pg in spec.pages() for i in pg.items]
    for it in items:
        if isinstance(it, spec.Text):
            for p in it.paras:
                if p.style == "Folio":
                    continue
                out.append(norm("".join(t for t, _ in p.runs)))
    return out


def main():
    d, s = Counter(docx_paragraphs()), Counter(spec_paragraphs())
    # the running head appears once in the Word header and once on the master page
    missing, extra = d - s, s - d
    print(f"Word paragraphs: {sum(d.values())}  layout paragraphs: {sum(s.values())}")
    for k, v in missing.items():
        print("MISSING from layout:", v, repr(k))
    for k, v in extra.items():
        print("NOT in Word file:  ", v, repr(k))
    if missing or extra:
        sys.exit(1)
    print("OK - every paragraph matches the Word file exactly (words and order within paragraphs).")


if __name__ == "__main__":
    main()
