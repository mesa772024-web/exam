"""Build the profile: images -> text check -> PDF -> IDML -> dist package.

    python3 tools/build.py
"""
import shutil
import subprocess
import sys
import zipfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent
BUILD = ROOT / "build"
DIST = ROOT / "dist"
NAME = "Iraqi_CSR_Forum_2026_Profile"


def run(script, *args):
    subprocess.run([sys.executable, str(HERE / script), *args], check=True, cwd=HERE)


def main():
    if not (BUILD / "fonts-ttf").exists():
        subprocess.run(["bash", str(HERE / "fetch_fonts.sh")], check=True)
    sys.path.insert(0, str(HERE))
    import fonts
    missing = [f for f in ("thmanyah serif display", "thmanyah sans") if not fonts.has_family(f)]
    if missing and not fonts.PREVIEW:
        sys.exit("Thmanyah is not installed: put the official font files (font.thmanyah.com) in "
                 "src/fonts-private/, or run with PROFILE_PREVIEW=1 for a layout preview.")
    run("prep_images.py")
    run("verify_text.py")
    run("check_glyphs.py")
    run("render_html.py")
    run("render_idml.py")

    if fonts.PREVIEW:
        print("preview build: build/profile.pdf and build/profile.idml (dist/ left untouched)")
        return
    DIST.mkdir(exist_ok=True)
    shutil.copy(BUILD / "profile.pdf", DIST / f"{NAME}.pdf")

    pkg = DIST / f"{NAME}_IDML.zip"
    with zipfile.ZipFile(pkg, "w", zipfile.ZIP_DEFLATED) as z:
        z.write(BUILD / "profile.idml", f"{NAME}/{NAME}.idml")
        z.write(BUILD / "profile.pdf", f"{NAME}/{NAME}.pdf")
        for p in sorted((BUILD / "Links").iterdir()):
            z.write(p, f"{NAME}/Links/{p.name}")
        z.write(ROOT / "PACKAGE_README.md", f"{NAME}/README.md")
    print("dist:", *sorted(p.name for p in DIST.iterdir()))


if __name__ == "__main__":
    main()
