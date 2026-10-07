#!/usr/bin/env python3
import json
import sys
from pathlib import Path

try:
    import fitz
except ImportError:
    print(json.dumps({"ok": False, "error": "PyMuPDF is not installed. Run: python3 -m pip install pymupdf"}))
    sys.exit(2)

if len(sys.argv) not in (8, 9):
    print(json.dumps({"ok": False, "error": "Usage: extract-pdf-region.py PDF OUTPUT PAGE X0 Y0 X1 Y1 [white|transparent]"}))
    sys.exit(2)

pdf_path, output_path = sys.argv[1], sys.argv[2]
page_number = int(sys.argv[3])
coords = [float(value) for value in sys.argv[4:8]]
background_mode = sys.argv[8] if len(sys.argv) == 9 else "white"
if background_mode not in ("white", "transparent"):
    print(json.dumps({"ok": False, "error": "Background mode must be white or transparent."}))
    sys.exit(2)
if page_number < 1 or any(value < 0 or value > 1 for value in coords) or coords[0] >= coords[2] or coords[1] >= coords[3]:
    print(json.dumps({"ok": False, "error": "Invalid page or normalized crop coordinates."}))
    sys.exit(2)

doc = fitz.open(pdf_path)
if page_number > len(doc):
    print(json.dumps({"ok": False, "error": "Source page is outside the PDF."}))
    sys.exit(2)
page = doc[page_number - 1]
rect = page.rect
clip = fitz.Rect(coords[0] * rect.width, coords[1] * rect.height, coords[2] * rect.width, coords[3] * rect.height)
pix = page.get_pixmap(matrix=fitz.Matrix(2.5, 2.5), clip=clip, alpha=False)
# Scanned papers contain rasterized grey/off-white paper. Normalize that paper
# while retaining dark text, diagrams, and coloured marks. A short transition
# avoids jagged edges and white halos around transparent strokes.
rgb = pix.samples
try:
    import numpy as np

    source = np.frombuffer(rgb, dtype=np.uint8).reshape(pix.height, pix.width, pix.n)[:, :, :3].astype(np.float32)
    ink = 255 - source.min(axis=2)
    strength = np.clip((ink - 30) / 60, 0, 1)
    if background_mode == "white":
        output = np.rint(255 - (255 - source) * strength[:, :, None]).astype(np.uint8)
        pix = fitz.Pixmap(fitz.csRGB, pix.width, pix.height, output.tobytes(), False)
    else:
        alpha = np.rint(strength * 255).astype(np.uint8)
        divisor = np.maximum(alpha.astype(np.float32), 1)[:, :, None]
        foreground = np.rint(255 - (255 - source) * 255 / divisor)
        foreground = np.clip(foreground, 0, 255).astype(np.uint8)
        foreground[alpha == 0] = 0
        output = np.dstack((foreground, alpha))
        pix = fitz.Pixmap(fitz.csRGB, pix.width, pix.height, output.tobytes(), True)
except ImportError:
    if background_mode == "white":
        cleaned = bytearray(rgb)
        for pixel in range(pix.width * pix.height):
            offset = pixel * pix.n
            ink = 255 - min(rgb[offset], rgb[offset + 1], rgb[offset + 2])
            strength = 0 if ink <= 30 else 1 if ink >= 90 else (ink - 30) / 60
            for channel in range(3):
                cleaned[offset + channel] = round(255 - (255 - rgb[offset + channel]) * strength)
        pix = fitz.Pixmap(fitz.csRGB, pix.width, pix.height, bytes(cleaned), False)
    else:
        rgba = bytearray(pix.width * pix.height * 4)
        for pixel in range(pix.width * pix.height):
            source = pixel * pix.n
            target = pixel * 4
            ink = 255 - min(rgb[source], rgb[source + 1], rgb[source + 2])
            alpha = 0 if ink <= 30 else 255 if ink >= 90 else round((ink - 30) * 255 / 60)
            for channel in range(3):
                rgba[target + channel] = 0 if alpha == 0 else max(0, min(255, round(255 - (255 - rgb[source + channel]) * 255 / alpha)))
            rgba[target + 3] = alpha
        pix = fitz.Pixmap(fitz.csRGB, pix.width, pix.height, bytes(rgba), True)
Path(output_path).parent.mkdir(parents=True, exist_ok=True)
pix.save(output_path)
print(json.dumps({"ok": True, "width": pix.width, "height": pix.height, "page": page_number, "background_mode": background_mode}))
