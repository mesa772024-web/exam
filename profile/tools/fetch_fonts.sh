#!/usr/bin/env bash
# Fetch the Google Fonts TTFs (OFL) used by the profile into build/fonts-ttf.
set -euo pipefail
cd "$(dirname "$0")/.."
tmp=$(mktemp -d)
for p in amiri readex-pro ibm-plex-sans-arabic dm-serif-display noto-sans-symbols-2; do
  (cd "$tmp" && npm pack -q "@expo-google-fonts/$p" >/dev/null && tar xzf expo-google-fonts-$p-*.tgz && mv package "$p")
done
mkdir -p build/fonts-ttf
find "$tmp" -name '*.ttf' -exec cp {} build/fonts-ttf/ \;
rm -rf "$tmp"
ls build/fonts-ttf
