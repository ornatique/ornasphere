"""Local transparent cutout worker. Does not generate or replace jewellery."""
import os
import sys
from pathlib import Path

os.environ.setdefault("U2NET_HOME", str(Path(__file__).resolve().parents[2] / "storage/app/stock-gallery-models"))

from PIL import Image, ImageOps
from rembg import new_session, remove


def main():
    source, destination = sys.argv[1:3]
    image = ImageOps.exif_transpose(Image.open(source)).convert("RGBA")
    image.thumbnail((1600, 1600), Image.Resampling.LANCZOS)
    # Preserve uploaded transparency rather than segmenting it a second time.
    if image.getchannel("A").getextrema()[0] < 250:
        result = image
    else:
        result = remove(image, session=new_session("u2netp"))
    bounds = result.getchannel("A").getbbox()
    if not bounds:
        raise ValueError("No product detected. Try a clearer photograph.")
    if result.getchannel("A").getextrema()[0] >= 250:
        raise ValueError("Background could not be separated. Try a plain background.")
    result.crop(bounds).save(destination, "PNG")


if __name__ == "__main__":
    main()
