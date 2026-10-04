"""Prepare the profile's images from the original Word media.

Every output goes to build/Links/ at 72 ppi (so InDesign GraphicBounds equal
pixel dimensions). The originals are untouched; only crops, levels and a brand
tone are applied.
"""
from pathlib import Path

import numpy as np
from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "src" / "media"
OUT = ROOT / "build" / "Links"

CREAM = np.array([0xF8, 0xF3, 0xE8], dtype=float)
DEEP = np.array([0x06, 0x24, 0x1B], dtype=float)
GREEN = np.array([0x12, 0x66, 0x4A], dtype=float)


def save(im, name, **kw):
    OUT.mkdir(parents=True, exist_ok=True)
    path = OUT / name
    if name.endswith(".jpg"):
        im.convert("RGB").save(path, quality=93, dpi=(72, 72), **kw)
    else:
        im.save(path, dpi=(72, 72), **kw)
    return path


def cover():
    # The Word cover photo was washed out (min level ~185). Restore contrast in
    # the centre, keep its soft vignette, and multiply onto the cream paper so
    # the edges dissolve into the page.
    a = np.asarray(Image.open(SRC / "image7.jpg").convert("RGB")).astype(float)
    lo = 138
    a = np.clip((a - lo) / (255 - lo) * 255, 0, 255)
    a = a * CREAM / 255
    # feather top, bottom and right edges into the paper colour
    h, w = a.shape[:2]
    ys = np.linspace(0, 1, h)[:, None]
    xs = np.linspace(0, 1, w)[None, :]
    smooth = lambda t: t * t * (3 - 2 * t)
    fy = smooth(np.clip(ys / 0.22, 0, 1)) * smooth(np.clip((1 - ys) / 0.22, 0, 1))
    fx = smooth(np.clip((1 - xs) / 0.2, 0, 1))
    m = (fy * fx)[..., None]
    a = a * m + CREAM * (1 - m)
    save(Image.fromarray(a.astype("uint8")), "cover-hands-flag.jpg")


def duotone(src, name, lift=0.0):
    g = np.asarray(Image.open(SRC / src).convert("L")).astype(float) / 255
    g = np.clip(g * (1 - lift) + lift, 0, 1)[..., None]
    lo = np.clip((g / 0.55), 0, 1)
    hi = np.clip((g - 0.55) / 0.45, 0, 1)
    rgb = np.where(g < 0.55, DEEP + (GREEN - DEEP) * lo, GREEN + (CREAM - GREEN) * hi)
    save(Image.fromarray(rgb.astype("uint8")), name)


def circle(src, name):
    im = Image.open(SRC / src).convert("RGBA")
    # inner photo spans x 32..237 on a 271 px canvas; crop inside the baked ring
    crop = im.crop((38, 38, 232, 232))
    bg = Image.new("RGB", crop.size, (255, 255, 255))
    bg.paste(crop, mask=crop.getchannel("A"))
    save(bg, name)


def copy(src, name, crop=None):
    im = Image.open(SRC / src)
    if crop:
        im = im.crop(crop)
    if name.endswith(".jpg"):
        im = im.convert("RGB")
    save(im, name)


def main():
    cover()
    copy("image8.jpg", "about-baghdad-tigris.jpg")
    copy("image9.jpg", "objectives-hands-seedling.jpg")
    duotone("image10.jpg", "participants-banner.jpg", lift=0.06)
    copy("image16.jpg", "programme-audience.jpg")
    circle("image11.png", "cat-1-government.jpg")
    circle("image12.png", "cat-2-private.jpg")
    circle("image13.png", "cat-3-international.jpg")
    circle("image14.png", "cat-4-academic.jpg")
    circle("image15.png", "cat-5-civil.jpg")
    copy("image6.png", "baghdad-thumb.png")
    copy("image17.png", "logo-ministry-of-trade.png")
    copy("image18.png", "logo-ficc.png")
    copy("image19.png", "logo-icc-iraq.png")
    copy("image20.png", "logo-undp.png")
    copy("image21.png", "logo-union-arab-chambers.png")
    copy("image22.png", "logo-iusr.png")
    copy("image23.png", "logo-unido.png")
    # Same crop as the Word file: logo mark only, without the tagline strip.
    copy("image24.png", "logo-csr-accreditation.png", crop=(0, 0, 364, 186))
    copy("image25.png", "logo-eu.png")


if __name__ == "__main__":
    main()
