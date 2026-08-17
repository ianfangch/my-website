from pathlib import Path
from PIL import Image


PRODUCTS = (
    "glass-door-wardrobe-system",
    "wardrobe-living-room-cabinet-system",
    "glass-wardrobe-closet-system",
    "sliding-door-wardrobe-dressing-table",
    "modern-glass-door-bedroom-wardrobe",
)


root = Path(__file__).resolve().parents[1] / "img" / "products"

for product in PRODUCTS:
    folder = root / product
    for path in sorted(folder.glob("*.jpg")):
        with Image.open(path) as source:
            image = source.convert("RGB")
            width, height = image.size
            # Supplier marks occupy the upper-left ceiling/wall band. Removing
            # that band preserves the cabinetry itself and avoids fabricated pixels.
            top = round(height * 0.145)
            side = min(width, height - top)
            left = max(0, (width - side) // 2)
            cleaned = image.crop((left, top, left + side, top + side))
            cleaned = cleaned.resize((1200, 1200), Image.Resampling.LANCZOS)
            cleaned.save(path, "JPEG", quality=86, optimize=True, progressive=True)
        print(path.relative_to(root.parent.parent))
