"""Fail if any character would hit a missing glyph in the font InDesign will use."""
import sys
import fonts
import spec
from spec import S, C

_cm = {}


def cmap(fam, style):
    if (fam, style) not in _cm:
        _cm[(fam, style)] = fonts.cmap(fam, style)
    return _cm[(fam, style)], f"{fam} {style}"


def main():
    bad = 0
    items = spec.master_items() + [i for p in spec.pages() for i in p.items]
    for it in items:
        if not isinstance(it, spec.Text):
            continue
        for p in it.paras:
            st = S[p.style]
            for txt, cs in p.runs:
                fam, style = (C[cs]["family"], C[cs]["style"]) if cs and "family" in C[cs] else (st.family, st.style)
                cm, fn = cmap(fam, style)
                for ch in set(txt):
                    if not (ch == "#" and p.style == "Folio") and ord(ch) not in cm:
                        bad += 1
                        print("MISSING", fn, repr(ch), hex(ord(ch)), p.style)
    print("missing glyphs:", bad)
    sys.exit(1 if bad else 0)


if __name__ == "__main__":
    main()
