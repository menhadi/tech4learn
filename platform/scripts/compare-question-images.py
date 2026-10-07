#!/usr/bin/env python3
"""Compare a stored question image with an image extracted from its source PDF."""
import base64
import io
import json
import sys
from PIL import Image, ImageChops, ImageOps, ImageStat


def prepared(path):
    image = Image.open(path).convert("RGB")
    # Ignore minor crop margins while retaining the actual diagram structure.
    background = Image.new("RGB", (96, 96), "white")
    contained = ImageOps.contain(image, (92, 92), Image.Resampling.LANCZOS)
    background.paste(contained, ((96-contained.width)//2, (96-contained.height)//2))
    return background.convert("L")


def dhash(image):
    tiny = image.resize((17, 16), Image.Resampling.LANCZOS)
    pixels = list(tiny.getdata())
    return [pixels[y*17+x] > pixels[y*17+x+1] for y in range(16) for x in range(16)]


def main():
    if len(sys.argv) != 3:
        raise ValueError("Usage: compare-question-images.py STORED SOURCE")
    stored, source = prepared(sys.argv[1]), prepared(sys.argv[2])
    pixel_similarity = 1 - (ImageStat.Stat(ImageChops.difference(stored, source)).mean[0] / 255)
    left, right = dhash(stored), dhash(source)
    hash_similarity = sum(a == b for a, b in zip(left, right)) / len(left)
    similarity = round(pixel_similarity * 0.45 + hash_similarity * 0.55, 5)
    print(json.dumps({"ok": True, "similarity": similarity, "pixel_similarity": round(pixel_similarity, 5),
                      "hash_similarity": round(hash_similarity, 5), "matches": similarity >= 0.82}))
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as error:
        print(json.dumps({"ok": False, "error": str(error)}))
        sys.exit(2)