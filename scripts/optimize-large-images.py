from pathlib import Path
from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
IMAGE_ROOT = ROOT / "img"
MIN_BYTES = 500 * 1024
TEXT_SUFFIXES = {".html", ".js", ".css", ".xml"}

candidates = [p for p in IMAGE_ROOT.rglob("*") if p.suffix.lower() in {".jpg", ".jpeg", ".png"} and p.stat().st_size > MIN_BYTES]
replacements = {}

for source in candidates:
    target = source.with_suffix(".webp")
    with Image.open(source) as image:
        if image.mode not in {"RGB", "RGBA"}:
            image = image.convert("RGBA" if "transparency" in image.info else "RGB")
        image.save(target, "WEBP", quality=82, method=6)
    if target.stat().st_size >= source.stat().st_size:
        target.unlink()
        continue
    old = source.relative_to(ROOT).as_posix()
    new = target.relative_to(ROOT).as_posix()
    replacements[old] = new

changed_files = 0
for path in ROOT.rglob("*"):
    if not path.is_file() or path.suffix.lower() not in TEXT_SUFFIXES or ".git" in path.parts:
        continue
    text = path.read_text(encoding="utf-8")
    updated = text
    for old, new in replacements.items():
        updated = updated.replace(old, new)
        updated = updated.replace("https://ianproject.com/" + old, "https://ianproject.com/" + new)
    if updated != text:
        path.write_text(updated, encoding="utf-8", newline="\n")
        changed_files += 1

before = sum((ROOT / old).stat().st_size for old in replacements)
after = sum((ROOT / new).stat().st_size for new in replacements.values())
print(f"Converted {len(replacements)} images; updated {changed_files} files; referenced bytes {before} -> {after}.")
