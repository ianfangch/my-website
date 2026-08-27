from pathlib import Path
from statistics import median

from PIL import Image, ImageChops


ROOT = Path(__file__).resolve().parents[1]
SERIES = (
    "sparkle-series-quartz-collection",
    "classic-series-quartz-collection",
    "exotic-series-quartz-collection",
)


def border_colour(image: Image.Image) -> tuple[int, int, int]:
    rgb = image.convert("RGB")
    width, height = rgb.size
    sample_step = max(1, width // 400)
    pixels = []
    for x in range(0, width, sample_step):
        pixels.append(rgb.getpixel((x, 0)))
        pixels.append(rgb.getpixel((x, height - 1)))
    for y in range(0, height, sample_step):
        pixels.append(rgb.getpixel((0, y)))
        pixels.append(rgb.getpixel((width - 1, y)))
    return tuple(int(median(channel)) for channel in zip(*pixels))


def make_square(source_path: Path, output_path: Path) -> None:
    with Image.open(source_path) as opened:
        source = opened.convert("RGB")
    side = max(source.size)
    offset = ((side - source.width) // 2, (side - source.height) // 2)
    square = Image.new("RGB", (side, side), border_colour(source))
    square.paste(source, offset)
    square.save(output_path, "WEBP", lossless=True, method=6, exact=True)

    with Image.open(output_path) as saved:
        restored = saved.convert("RGB").crop(
            (offset[0], offset[1], offset[0] + source.width, offset[1] + source.height)
        )
    if ImageChops.difference(source, restored).getbbox() is not None:
        raise RuntimeError(f"Source pixels changed in {output_path}")
    print(f"{source_path.name}: {source.size} -> {output_path.name}: {square.size}")


for series in SERIES:
    directory = ROOT / "img" / "products" / series
    make_square(directory / "card.jpg", directory / "card-square.webp")
    make_square(directory / "1.jpg", directory / "1-square.webp")
