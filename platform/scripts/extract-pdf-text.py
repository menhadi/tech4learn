#!/usr/bin/env python3
"""Extract page-labelled PDF text, with optional local OCR for scanned pages."""

import json
import sys

try:
    import fitz
except ImportError:
    print(json.dumps({"ok": False, "error": "PyMuPDF is not installed."}))
    sys.exit(2)


def ocr_page(page):
    try:
        import pytesseract
        from PIL import Image
    except ImportError as error:
        raise RuntimeError("Local OCR requires Pillow and pytesseract.") from error

    pix = page.get_pixmap(matrix=fitz.Matrix(2.2, 2.2), alpha=False)
    image = Image.frombytes("RGB", (pix.width, pix.height), pix.samples)
    return pytesseract.image_to_string(image, config="--psm 6").strip()


def main():
    if len(sys.argv) not in (2, 3):
        raise ValueError("Usage: extract-pdf-text.py PDF [ENABLE_OCR]")

    enable_ocr = len(sys.argv) == 3 and sys.argv[2].lower() in ("1", "true", "yes")
    pages = []
    ocr_pages = 0

    with fitz.open(sys.argv[1]) as document:
        for index, page in enumerate(document):
            text = page.get_text("text").strip()
            used_ocr = False
            if enable_ocr and len("".join(text.split())) < 40:
                text = ocr_page(page)
                used_ocr = True
                ocr_pages += 1
            pages.append({
                "page": index + 1,
                "text": text,
                "ocr": used_ocr,
            })

    print(json.dumps({
        "ok": True,
        "page_count": len(pages),
        "ocr_pages": ocr_pages,
        "pages": pages,
    }, ensure_ascii=False))


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print(json.dumps({"ok": False, "error": str(error)}))
        sys.exit(2)
