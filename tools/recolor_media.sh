#!/bin/bash
# Recolour the intro leaves video green → burgundy (see burgundy_lut.py).
# Videos keep their size, frame rate and keyframe spacing (the cinematic intro
# scrubs through the video frame by frame, so short GOPs must be preserved).
# usage: tools/recolor_media.sh baghdad-alhayat
set -euo pipefail
PKG=${1:?package dir}
HERE=$(cd "$(dirname "$0")" && pwd)
LUT=$(mktemp --suffix=.cube)
python3 "$HERE/burgundy_lut.py" "$LUT"
cd "$PKG/assets"

video() { # file gop crf
  local f=$1 g=$2 crf=$3 tmp; tmp="${f%.mp4}.tmp.mp4"
  ffmpeg -loglevel error -y -i "$f" -an -vf "lut3d=$LUT" -c:v libx264 -profile:v high -pix_fmt yuv420p \
    -preset slow -crf "$crf" -g "$g" -keyint_min "$g" -sc_threshold 0 -movflags +faststart "$tmp"
  mv "$tmp" "$f"; echo "video  $f"
}
image() { # kept for one-off use
  local f=$1 tmp; tmp="${f%.*}.tmp.${f##*.}"
  ffmpeg -loglevel error -y -i "$f" -vf "lut3d=$LUT" -q:v 3 "$tmp"; mv "$tmp" "$f"; echo "image  $f"
}

# only the leaves overlay of the intro turns burgundy; every other photo/video keeps its original colours
video leaves.mp4                 240 22
rm -f "$LUT"

# Media that shows the other office's name (building sign, certificates, flag
# close-ups) is swapped for neutral footage from the same shoot.
cp images/building-1.jpg      images/headquarters.jpg
cp images/warehouse-boxes.jpg images/gsdp-certificate.jpg
cp vid/products.mp4           vid/office.mp4
rm -f images/flag.jpg
echo "replaced headquarters.jpg, gsdp-certificate.jpg, vid/office.mp4; removed flag.jpg"
