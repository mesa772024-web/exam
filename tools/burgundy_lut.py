# -*- coding: utf-8 -*-
"""Write a 3D LUT (.cube) that turns green tones into Baghdad Al Hayat burgundy.

Only greens move (with a soft falloff at the edges of the green band so there
is no banding); greys, skin tones, blues and reds are untouched.
Apply with:  ffmpeg -i in.mp4 -vf lut3d=burgundy.cube out.mp4
usage: python3 tools/burgundy_lut.py out.cube
"""
import colorsys
import sys

N = 33
TARGET = 334 / 360.0                 # leaf colour #A00B4C
CORE = (60 / 360.0, 170 / 360.0)     # fully converted
FEATHER = 20 / 360.0                 # soft edge on both sides


def weight(h, chroma):
    lo, hi = CORE
    if lo <= h <= hi:
        w = 1.0
    elif lo - FEATHER < h < lo:
        w = (h - (lo - FEATHER)) / FEATHER
    elif hi < h < hi + FEATHER:
        w = ((hi + FEATHER) - h) / FEATHER
    else:
        return 0.0
    w = w * w * (3 - 2 * w)                     # smoothstep
    return w * min(1.0, max(0.0, chroma - 0.04) / 0.14)   # near-greys / near-whites stay as they are


def convert(r, g, b):
    h, l, s = colorsys.rgb_to_hls(r, g, b)
    chroma = max(r, g, b) - min(r, g, b)
    w = weight(h, chroma)
    if w <= 0:
        return r, g, b
    nh = (TARGET + (h - 125 / 360.0) * 0.2) % 1.0
    nl = l * (0.95 - 0.45 * chroma)             # deeper for vivid greens, so they land on burgundy, not pink
    nr, ng, nb = colorsys.hls_to_rgb(nh, nl, min(1.0, s * 1.05))
    return r + (nr - r) * w, g + (ng - g) * w, b + (nb - b) * w


with open(sys.argv[1], 'w') as f:
    f.write('TITLE "green to Baghdad Al Hayat burgundy"\nLUT_3D_SIZE %d\n' % N)
    for bi in range(N):
        for gi in range(N):
            for ri in range(N):
                r, g, b = convert(ri / (N - 1), gi / (N - 1), bi / (N - 1))
                f.write('%.6f %.6f %.6f\n' % (r, g, b))
