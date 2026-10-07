#!/usr/bin/env python3
"""Extract the real visual nearest an AI-supplied PDF crop hint.

Unlike a blind rectangular crop, this detects embedded images and vector
drawings, groups layered/nearby objects, constrains them to the requested
question band when possible, and renders the PDF appearance at high quality.
"""

import json
import hashlib
import math
import re
import sys
from collections import Counter
from pathlib import Path

try:
    import fitz
except ImportError:
    print(json.dumps({"ok": False, "error": "PyMuPDF is not installed. Run: python3 -m pip install pymupdf"}))
    sys.exit(2)

try:
    import cv2
    import numpy as np
except ImportError:
    cv2 = None
    np = None


QUESTION_PATTERNS = (
    re.compile(r"^\s*Question\s+(\d+)\b", re.I),
    re.compile(r"^\s*Q(?:uestion)?\.?\s*(\d+)\s*(?:[.):\-]\s*|$)", re.I),
    re.compile(r"^\s*(\d+)\s*[.)]\s+"),
)


def expanded(rect, gap):
    return fitz.Rect(rect.x0 - gap, rect.y0 - gap, rect.x1 + gap, rect.y1 + gap)


def cluster_rects(rects, gap=20):
    groups = []
    for source in sorted(rects, key=lambda r: (r.y0, r.x0)):
        rect = fitz.Rect(source)
        matches = [i for i, existing in enumerate(groups) if expanded(existing, gap).intersects(rect)]
        if not matches:
            groups.append(rect)
            continue
        for index in reversed(matches):
            rect |= groups.pop(index)
        groups.append(rect)

    changed = True
    while changed:
        changed = False
        stable = []
        for rect in groups:
            for index, existing in enumerate(stable):
                if expanded(existing, gap).intersects(rect):
                    stable[index] = existing | rect
                    changed = True
                    break
            else:
                stable.append(rect)
        groups = stable
    return sorted(groups, key=lambda r: (r.y0, r.x0))


def image_signature(doc, xref):
    try:
        payload = doc.extract_image(xref).get("image", b"")
    except Exception:
        payload = b""
    return hashlib.sha256(payload).hexdigest() if payload else f"xref:{xref}"


def repeated_image_signatures(doc, minimum_pages=2, minimum_ratio=0.35):
    if len(doc) < 2:
        return set()
    counts = Counter()
    for page in doc:
        counts.update({image_signature(doc, image[0]) for image in page.get_images(full=True)})
    threshold = max(minimum_pages if len(doc) > 1 else 1, math.ceil(len(doc) * minimum_ratio))
    return {signature for signature, count in counts.items() if count >= threshold}


def remove_repeated_images(doc, repeated):
    removed = 0
    for page in doc:
        for image in list(page.get_images(full=True)):
            if image_signature(doc, image[0]) not in repeated:
                continue
            try:
                page.delete_image(image[0])
                removed += 1
            except Exception:
                pass
    return removed


def embedded_rects(doc, page, repeated):
    rects = []
    for image in page.get_images(full=True):
        if image_signature(doc, image[0]) in repeated:
            continue
        for rect in page.get_image_rects(image[0]):
            if rect.width >= page.rect.width * 0.90 and rect.height >= page.rect.height * 0.90:
                continue
            if rect.width >= 5 and rect.height >= 5:
                rects.append(fitz.Rect(rect))
    return cluster_rects(rects, gap=20)


def detected_table_rects(page):
    try:
        tables = page.find_tables().tables
    except Exception:
        return []
    accepted = []
    for table in tables:
        rect = fitz.Rect(table.bbox)
        rows = table.extract()
        cell_count = sum(len(row) for row in rows)
        non_empty = sum(1 for row in rows for cell in row if cell is not None and str(cell).strip())
        if rect.width < 30 or rect.height < 15 or len(rows) < 2:
            continue
        # Exam page/question borders are often reported as one giant table.
        if rect.get_area() > page.rect.get_area() * 0.30:
            continue
        if max((len(row) for row in rows), default=0) < 2:
            continue
        if not cell_count or non_empty / cell_count < 0.35:
            continue
        accepted.append(rect)
    return accepted


def visual_type(rect, tables):
    for table in tables:
        intersection = rect & table
        if intersection.is_empty:
            continue
        visual_coverage = intersection.get_area() / max(1, rect.get_area())
        relative_size = table.get_area() / max(1, rect.get_area())
        # A page-spanning border can be misidentified as a table. A genuine
        # extracted table closely matches the selected visual bounds.
        if visual_coverage >= 0.75 and relative_size <= 1.8:
            return "table"
    return "diagram"

def vector_rects(page):
    raw = []
    for drawing in page.get_drawings():
        rect = drawing.get("rect")
        if not rect or rect.width < 6 or rect.height < 6:
            continue
        # Page/table rules otherwise become enormous false diagrams.
        if rect.width > page.rect.width * 0.72 and rect.height < 20:
            continue
        if rect.height > page.rect.height * 0.72 and rect.width < 20:
            continue
        raw.append(fitz.Rect(rect))
    local_objects = cluster_rects(raw, gap=8)
    return [rect for rect in cluster_rects(local_objects, gap=42)
            if rect.width >= 35 and rect.height >= 25
            and rect.width <= page.rect.width * 0.90
            and rect.height <= page.rect.height * 0.70]


def _dark_runs(values, minimum, maximum_gap=2, threshold=210):
    runs = []
    start = None
    gap = 0
    for index, value in enumerate(values):
        if value < threshold:
            if start is None:
                start = index
            gap = 0
        elif start is not None:
            gap += 1
            if gap > maximum_gap:
                end = index - gap + 1
                if end - start >= minimum:
                    runs.append((start, end))
                start = None
                gap = 0
    if start is not None and len(values) - start - gap >= minimum:
        runs.append((start, len(values) - gap))
    return runs


def scanned_visual_rects_without_opencv(page):
    """Dependency-free long-line detection for production PDF workers."""
    scale = 2
    pix = page.get_pixmap(matrix=fitz.Matrix(scale, scale), alpha=False, colorspace=fitz.csGRAY)
    width, height = pix.width, pix.height
    samples = bytes(pix.samples)
    raw = []
    minimum_horizontal = max(18, width // 35)
    minimum_vertical = max(18, height // 50)
    for y in range(height):
        row = samples[y * width:(y + 1) * width]
        for x0, x1 in _dark_runs(row, minimum_horizontal):
            raw.append(fitz.Rect(x0 / scale, y / scale, x1 / scale, (y + 1) / scale))
    for x in range(width):
        column = samples[x::width]
        for y0, y1 in _dark_runs(column, minimum_vertical):
            raw.append(fitz.Rect(x / scale, y0 / scale, (x + 1) / scale, y1 / scale))
    local = cluster_rects(raw, gap=8)
    candidates = []
    for rect in local:
        nw, nh = rect.width / page.rect.width, rect.height / page.rect.height
        center_y = ((rect.y0 + rect.y1) / 2) / page.rect.height
        if nw < 0.05 or nh < 0.012 or nw > 0.90 or nh > 0.65:
            continue
        if center_y < 0.085 or center_y > 0.92:
            continue
        candidates.append(rect)
    return [rect for rect in cluster_rects(candidates, gap=24)
            if rect.width <= page.rect.width * 0.92
            and rect.height <= page.rect.height * 0.68]


def scanned_visual_rects(page):
    """Detect line-art diagrams inside a full-page scanned bitmap.

    Full-page scans have no separate PDF image/vector objects. Long horizontal
    and vertical strokes provide a deterministic way to snap an approximate AI
    box to the real diagram while ignoring ordinary question and option text.
    """
    if cv2 is None or np is None:
        try:
            return scanned_visual_rects_without_opencv(page)
        except Exception:
            return []
    try:
        scale = 2
        pix = page.get_pixmap(matrix=fitz.Matrix(scale, scale), alpha=False, colorspace=fitz.csGRAY)
        gray = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width)
        binary = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)[1]
        horizontal = cv2.morphologyEx(
            binary, cv2.MORPH_OPEN,
            cv2.getStructuringElement(cv2.MORPH_RECT, (max(18, pix.width // 35), 1)),
        )
        vertical = cv2.morphologyEx(
            binary, cv2.MORPH_OPEN,
            cv2.getStructuringElement(cv2.MORPH_RECT, (1, max(18, pix.height // 50))),
        )
        lines = cv2.bitwise_or(horizontal, vertical)
        grouped = cv2.dilate(
            lines,
            cv2.getStructuringElement(cv2.MORPH_RECT, (max(7, pix.width // 120), max(7, pix.height // 140))),
            iterations=1,
        )
        contours, _ = cv2.findContours(grouped, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        rects = []
        for contour in contours:
            x, y, width, height = cv2.boundingRect(contour)
            nx0, ny0 = x / pix.width, y / pix.height
            nx1, ny1 = (x + width) / pix.width, (y + height) / pix.height
            normalized_width, normalized_height = nx1 - nx0, ny1 - ny0
            center_y = (ny0 + ny1) / 2
            if normalized_width < 0.05 or normalized_height < 0.012:
                continue
            if normalized_width > 0.90 or normalized_height > 0.65:
                continue
            if center_y < 0.085 or center_y > 0.92:
                continue
            line_pixels = cv2.countNonZero(lines[y:y + height, x:x + width])
            if line_pixels < max(30, int(width * height * 0.003)):
                continue
            rects.append(fitz.Rect(
                nx0 * page.rect.width, ny0 * page.rect.height,
                nx1 * page.rect.width, ny1 * page.rect.height,
            ))

        # Curves, airfoils and circular cross-sections can have no long straight
        # stroke. Recover large connected ink shapes, while rejecting ordinary
        # glyph-sized text and thin fraction/option rules.
        shape_contours, _ = cv2.findContours(binary, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        for contour in shape_contours:
            x, y, width, height = cv2.boundingRect(contour)
            normalized_width = width / pix.width
            normalized_height = height / pix.height
            center_y = (y + height / 2) / pix.height
            if normalized_width < 0.04 or normalized_height < 0.02:
                continue
            if normalized_width > 0.90 or normalized_height > 0.65:
                continue
            if center_y < 0.085 or center_y > 0.92:
                continue
            rects.append(fitz.Rect(
                x / pix.width * page.rect.width, y / pix.height * page.rect.height,
                (x + width) / pix.width * page.rect.width, (y + height) / pix.height * page.rect.height,
            ))
        # A single diagram can contain separated spars, arrows and dimension
        # lines. Merge only line-art candidates, never OCR/text blocks.
        return [rect for rect in cluster_rects(rects, gap=24)
                if rect.width <= page.rect.width * 0.92
                and rect.height <= page.rect.height * 0.68]
    except Exception:
        return []


def question_markers(page):
    markers = []
    data = page.get_text("dict")
    for block in data.get("blocks", []):
        if block.get("type") != 0:
            continue
        for line in block.get("lines", []):
            text = "".join(span.get("text", "") for span in line.get("spans", [])).strip()
            for pattern in QUESTION_PATTERNS:
                match = pattern.match(text)
                if match:
                    markers.append((int(match.group(1)), fitz.Rect(line["bbox"]).y0))
                    break
    return sorted(set(markers), key=lambda item: item[1])


def question_band(page, number):
    if not number:
        return None
    markers = question_markers(page)
    for index, (candidate, y0) in enumerate(markers):
        if candidate == number:
            y1 = markers[index + 1][1] if index + 1 < len(markers) else page.rect.y1
            return fitz.Rect(page.rect.x0, max(page.rect.y0, y0), page.rect.x1, min(page.rect.y1, y1))
    return None


def overlap_ratio(a, b):
    intersection = a & b
    return 0.0 if intersection.is_empty or a.get_area() <= 0 else intersection.get_area() / a.get_area()


def center_distance(a, b, page):
    ax, ay = (a.x0 + a.x1) / 2, (a.y0 + a.y1) / 2
    bx, by = (b.x0 + b.x1) / 2, (b.y0 + b.y1) / 2
    return math.hypot((ax - bx) / page.rect.width, (ay - by) / page.rect.height)


def axis_overlap_ratio(a, b, axis):
    if axis == "x":
        overlap = max(0, min(a.x1, b.x1) - max(a.x0, b.x0))
        return overlap / max(1, min(a.width, b.width))
    overlap = max(0, min(a.y1, b.y1) - max(a.y0, b.y0))
    return overlap / max(1, min(a.height, b.height))


def choose_visual(candidates, hint, band, page):
    if band:
        in_band = [rect for rect in candidates if band.contains(fitz.Point((rect.x0 + rect.x1) / 2, (rect.y0 + rect.y1) / 2))]
        if in_band:
            candidates = in_band
    if not candidates:
        return None

    overlapping = [rect for rect in candidates if rect.intersects(hint) or overlap_ratio(rect, hint) >= 0.15]
    pool = overlapping or candidates
    selected = min(pool, key=lambda rect: (
        0 if rect.intersects(hint) else 1,
        center_distance(rect, hint, page),
        -rect.get_area(),
    ))
    # Never snap a reviewer hint to an unrelated diagram elsewhere on the page.
    if not overlapping and center_distance(selected, hint, page) > 0.18:
        return None

    # Scanned engineering figures often contain separated components (for
    # example a side view and cross-section). Merge all components covered by
    # the reviewer hint when they share a row/column, but do not absorb answer
    # formulas or neighboring question text above and below the figure.
    hint_context = expanded(hint, min(page.rect.width, page.rect.height) * 0.018)
    related = []
    for rect in pool:
        center = fitz.Point((rect.x0 + rect.x1) / 2, (rect.y0 + rect.y1) / 2)
        if not hint_context.contains(center):
            continue
        shares_row = axis_overlap_ratio(selected, rect, "y") >= 0.20
        shares_column = axis_overlap_ratio(selected, rect, "x") >= 0.35
        if rect == selected or shares_row or shares_column or expanded(selected, 24).intersects(rect):
            related.append(rect)
    result = fitz.Rect(selected)
    for rect in related:
        result |= rect
    return result


def normalized(rect, page):
    return [
        round(rect.x0 / page.rect.width, 6), round(rect.y0 / page.rect.height, 6),
        round(rect.x1 / page.rect.width, 6), round(rect.y1 / page.rect.height, 6),
    ]


def main():
    if len(sys.argv) not in (8, 9):
        raise ValueError("Usage: extract-pdf-visual.py PDF OUTPUT PAGE X0 Y0 X1 Y1 [QUESTION_NUMBER]")
    pdf_path, output_path = sys.argv[1], sys.argv[2]
    page_number = int(sys.argv[3])
    coords = [float(value) for value in sys.argv[4:8]]
    question_number = int(sys.argv[8]) if len(sys.argv) == 9 and int(sys.argv[8]) > 0 else None
    if page_number < 0 or any(v < 0 or v > 1 for v in coords) or coords[0] >= coords[2] or coords[1] >= coords[3]:
        raise ValueError("Invalid page or normalized crop coordinates.")

    doc = fitz.open(pdf_path)
    if page_number == 0:
        if not question_number:
            raise ValueError("Automatic page lookup requires the paper question number.")
        page_number = next((index + 1 for index, candidate_page in enumerate(doc)
                            if any(number == question_number for number, _ in question_markers(candidate_page))), 0)
        if page_number == 0:
            raise ValueError(f"Question {question_number} was not located in the source PDF.")
    if page_number > len(doc):
        raise ValueError("Source page is outside the PDF.")
    page = doc[page_number - 1]
    repeated = repeated_image_signatures(doc)
    render_doc = fitz.open(pdf_path)
    removed_repeated = remove_repeated_images(render_doc, repeated)
    render_page = render_doc[page_number - 1]
    hint = fitz.Rect(coords[0] * page.rect.width, coords[1] * page.rect.height,
                     coords[2] * page.rect.width, coords[3] * page.rect.height)
    embedded = embedded_rects(doc, page, repeated)
    vectors = vector_rects(page)
    tables = detected_table_rects(page)
    scanned = scanned_visual_rects(page) if not embedded and not vectors else []
    candidates = cluster_rects(embedded + vectors + tables + scanned, gap=10)
    band = question_band(page, question_number)
    visual = choose_visual(candidates, hint, band, page)
    used_authoritative_bbox = visual is None
    used_scanned_refinement = visual is not None and bool(scanned)
    if used_authoritative_bbox:
        # Scanned PDFs commonly expose the entire page as one bitmap, which is
        # intentionally excluded from embedded candidates. The vision reviewer's
        # tight PDF coordinates are authoritative, so render that region directly.
        visual = hint

    # Keep a narrow safety margin. The previous 4.5% scan padding routinely
    # pulled answer choices and the next question into otherwise correct crops.
    pad_ratio = 0.012 if used_scanned_refinement else 0.008
    pad = max(4, min(page.rect.width, page.rect.height) * pad_ratio)
    visual = fitz.Rect(max(visual.x0 - pad, page.rect.x0), max(visual.y0 - pad, page.rect.y0),
                       min(visual.x1 + pad, page.rect.x1), min(visual.y1 + pad, page.rect.y1))
    if band:
        visual &= band
    source_visual_type = visual_type(visual, tables)
    pix = render_page.get_pixmap(matrix=fitz.Matrix(3, 3), clip=visual, alpha=False)
    Path(output_path).parent.mkdir(parents=True, exist_ok=True)
    pix.save(output_path)
    print(json.dumps({
        "ok": True, "width": pix.width, "height": pix.height, "page": page_number,
        "bbox_normalized": normalized(visual, page), "question_band_detected": band is not None,
        "embedded_candidates": len(embedded), "vector_candidates": len(vectors), "table_candidates": len(tables),
        "scanned_candidates": len(scanned),
        "mode": ("authoritative_bbox_crop" if used_authoritative_bbox else
                 "scanned_line_art_refinement" if used_scanned_refinement else "visual_object_detection"),
        "visual_type": source_visual_type,
        "repeated_layers": len(repeated), "removed_repeated_occurrences": removed_repeated,
    }))
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as error:
        print(json.dumps({"ok": False, "error": str(error)}))
        sys.exit(2)
