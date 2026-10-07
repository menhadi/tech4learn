import argparse
import csv
import hashlib
import html
import json
import os
import re
import unicodedata
from collections import Counter, defaultdict
from pathlib import Path

import fitz


QUESTION_RE = re.compile(r"^\s*Q(?:uestion)?\s*\.?\s*(?:No\s*\.?\s*)?(\d+)\b", re.IGNORECASE)
OPTION_RE = re.compile(r"^\s*\(?([A-D])\)\s*(.*)$", re.IGNORECASE)
QUESTION_RANGE_RE = re.compile(r"^\s*Q[.\s]*\d+\s*[-\u2013\u2014]\s*Q?[.\s]*\d+\b|Carry\s+(ONE|TWO|\d+)\s+mark|linked\s+answer\s+questions", re.IGNORECASE)
MATH_HINT_RE = re.compile(r"[∫∑√≤≥≠≈∞πθλμσΩΓΔ]|\b(dy/dx|d2y|d\^2y|matrix|det|eigen|laplace|fourier)\b|[=][^\n]{0,80}[+\-*/^]", re.IGNORECASE)



def looks_like_math(text):
    return bool(text and MATH_HINT_RE.search(text))


def render_block_png(page, block, scale=3):
    rect = fitz.Rect(block["x0"], block["y0"], block["x1"], block["y1"])
    rect = fitz.Rect(
        max(rect.x0 - 4, 0),
        max(rect.y0 - 4, 0),
        min(rect.x1 + 4, page.rect.x1),
        min(rect.y1 + 4, page.rect.y1),
    )
    pix = page.get_pixmap(matrix=fitz.Matrix(scale, scale), clip=rect, alpha=False)
    return pix.tobytes("png")


def get_windows_env_var(name):
    if os.name != "nt":
        return None, None

    try:
        import winreg
    except ImportError:
        return None, None

    registry_locations = [
        (winreg.HKEY_CURRENT_USER, r"Environment", "User"),
        (winreg.HKEY_LOCAL_MACHINE, r"SYSTEM\CurrentControlSet\Control\Session Manager\Environment", "Machine"),
    ]

    for root, key_path, scope in registry_locations:
        try:
            with winreg.OpenKey(root, key_path) as key:
                value, _ = winreg.QueryValueEx(key, name)
        except OSError:
            continue

        if value and str(value).strip():
            return str(value).strip(), scope

    return None, None


def get_env_first(*names):
    for name in names:
        value = os.environ.get(name)
        if value and value.strip():
            return value.strip(), f"Process:{name}"

    for name in names:
        value, scope = get_windows_env_var(name)
        if value:
            return value, f"{scope}:{name}"

    return None, None

def print_api_env_status():
    checks = {
        "DeepSeek API Key": ["DEEPSEEK_API_KEY"],
        "OpenAI API Key": ["OPENAI_API_KEY"],
    }

    for label, names in checks.items():
        value, found_name = get_env_first(*names)
        if value:
            print(f"{label}: found in {found_name} (length {len(value)})")
        else:
            print(f"{label}: NOT FOUND. Checked: {', '.join(names)}")

def clean_name(value):
    value = str(value).strip()
    value = re.sub(r"[^A-Za-z0-9]+", "_", value)
    return value.strip("_")


def make_image_filename(paper_code, question_no, image_index, option_label=None, ext="png"):
    paper_code = clean_name(paper_code).upper()
    question_no = clean_name(question_no)
    ext = clean_name(ext).lower() or "png"

    if option_label:
        option_label = clean_name(option_label).upper()
        return f"{paper_code}_q{question_no}_option{option_label}_img{image_index}.{ext}"

    return f"{paper_code}_q{question_no}_img{image_index}.{ext}"


def rect_tuple(rect):
    return tuple(round(float(v), 2) for v in rect)


def rect_center_y(rect):
    return (float(rect.y0) + float(rect.y1)) / 2


def in_body(rect, header_y, footer_y):
    return rect.y1 > header_y and rect.y0 < footer_y


def normalize_text(text):
    lines = []
    for line in text.splitlines():
        line = re.sub(r"\s+", " ", line).strip()
        if line:
            lines.append(line)
    return "\n".join(lines)


SUPERSCRIPT_CHARS = {
    "0": "\u2070", "1": "\u00b9", "2": "\u00b2", "3": "\u00b3", "4": "\u2074",
    "5": "\u2075", "6": "\u2076", "7": "\u2077", "8": "\u2078", "9": "\u2079",
    "+": "\u207a", "-": "\u207b", "\u2212": "\u207b", "=": "\u207c",
    "(": "\u207d", ")": "\u207e", "a": "\u1d43", "b": "\u1d47",
    "c": "\u1d9c", "d": "\u1d48", "e": "\u1d49", "f": "\u1da0",
    "g": "\u1d4d", "h": "\u02b0", "i": "\u2071", "j": "\u02b2",
    "k": "\u1d4f", "l": "\u02e1", "m": "\u1d50", "n": "\u207f",
    "o": "\u1d52", "p": "\u1d56", "r": "\u02b3", "s": "\u02e2",
    "t": "\u1d57", "u": "\u1d58", "v": "\u1d5b", "w": "\u02b7",
    "x": "\u02e3", "y": "\u02b8", "z": "\u1dbb",
}
SUBSCRIPT_CHARS = {
    "0": "\u2080", "1": "\u2081", "2": "\u2082", "3": "\u2083", "4": "\u2084",
    "5": "\u2085", "6": "\u2086", "7": "\u2087", "8": "\u2088", "9": "\u2089",
    "+": "\u208a", "-": "\u208b", "\u2212": "\u208b", "=": "\u208c",
    "(": "\u208d", ")": "\u208e", "a": "\u2090", "e": "\u2091",
    "h": "\u2095", "i": "\u1d62", "j": "\u2c7c", "k": "\u2096",
    "l": "\u2097", "m": "\u2098", "n": "\u2099", "o": "\u2092",
    "p": "\u209a", "r": "\u1d63", "s": "\u209b", "t": "\u209c",
    "x": "\u2093",
}

def script_span_unicode(text, mapping):
    normalized = unicodedata.normalize("NFKC", str(text or ""))
    converted = []
    for character in normalized:
        if character.isspace():
            converted.append(character)
        elif character in mapping:
            converted.append(mapping[character])
        else:
            return text
    return "".join(converted)


def line_text_preserving_scripts(spans):
    spans = sorted(spans, key=lambda span: span.get("bbox", [0])[0])
    if not spans:
        return ""
    maximum_size = max(float(span.get("size", 0) or 0) for span in spans)
    normal_spans = [
        span for span in spans
        if float(span.get("size", 0) or 0) >= maximum_size * 0.90
    ] or spans
    baseline = max(float(span.get("origin", (0, 0))[1]) for span in normal_spans)
    output = []
    for span in spans:
        text = span.get("text", "")
        size = float(span.get("size", 0) or 0)
        origin_y = float(span.get("origin", (0, baseline))[1])
        if maximum_size and size <= maximum_size * 0.85:
            if origin_y < baseline - maximum_size * 0.12:
                text = script_span_unicode(text, SUPERSCRIPT_CHARS)
            elif origin_y > baseline + maximum_size * 0.12:
                text = script_span_unicode(text, SUBSCRIPT_CHARS)
        output.append(text)
    return "".join(output)

def get_image_signature(doc, xref):
    image_data = doc.extract_image(xref)
    digest = hashlib.sha1(image_data["image"]).hexdigest()
    return digest, image_data


def find_repeated_images(doc, min_page_ratio):
    image_pages = defaultdict(set)
    image_rects = defaultdict(list)

    for page_index, page in enumerate(doc):
        for image in page.get_images(full=True):
            xref = image[0]
            digest, _ = get_image_signature(doc, xref)
            for rect in page.get_image_rects(xref):
                image_pages[digest].add(page_index)
                image_rects[digest].append(rect_tuple(rect))

    min_pages = max(2, int(doc.page_count * min_page_ratio))
    repeated = {
        digest
        for digest, pages in image_pages.items()
        if len(pages) >= min_pages
    }

    return repeated



def remove_repeated_images(doc, repeated_images):
    removed = 0

    for page in doc:
        for image in list(page.get_images(full=True)):
            xref = image[0]
            try:
                digest, _ = get_image_signature(doc, xref)
            except Exception:
                continue

            if digest not in repeated_images:
                continue

            try:
                page.delete_image(xref)
                removed += 1
            except Exception:
                pass

    return removed


def rect_contains_center(container, item):
    center_x = (item.x0 + item.x1) / 2
    center_y = (item.y0 + item.y1) / 2
    return container.x0 <= center_x <= container.x1 and container.y0 <= center_y <= container.y1


def table_to_html(table):
    rows = table.extract()
    html_rows = []
    for row in rows:
        cells = []
        for cell in row:
            value = "" if cell is None else str(cell).strip()
            value = html.escape(value).replace("\n", "<br>")
            cells.append(f"<td>{value}</td>")
        html_rows.append("<tr>" + "".join(cells) + "</tr>")
    return "<table>" + "".join(html_rows) + "</table>"


def get_page_tables(page, page_no, header_y, footer_y):
    tables = []
    try:
        found = page.find_tables()
    except Exception:
        return tables

    for index, table in enumerate(found.tables, start=1):
        rect = fitz.Rect(table.bbox)
        if not in_body(rect, header_y, footer_y):
            continue
        if rect.width < 30 or rect.height < 15:
            continue
        rows = table.extract()
        flat_text = "\n".join(" ".join("" if cell is None else str(cell) for cell in row) for row in rows)
        if len(rows) < 2 or max((len(row) for row in rows), default=0) < 2:
            continue
        cell_count = sum(len(row) for row in rows)
        non_empty_count = sum(1 for row in rows for cell in row if cell is not None and str(cell).strip())
        if cell_count and non_empty_count / cell_count < 0.35:
            continue
        if QUESTION_RE.search(flat_text):
            continue
        tables.append({
            "page": page_no,
            "index": index,
            "rect": rect,
            "html": table_to_html(table),
        })
    return tables


def collect_tables(doc, header_y, footer_y):
    by_page = defaultdict(list)
    for page_index, page in enumerate(doc):
        page_no = page_index + 1
        by_page[page_no] = get_page_tables(page, page_no, header_y, footer_y)
    return by_page

def rect_mostly_covered(rect, covers, minimum_ratio=0.60):
    if rect.get_area() <= 0:
        return False
    for cover in covers or []:
        intersection = rect & cover
        if not intersection.is_empty and intersection.get_area() / rect.get_area() >= minimum_ratio:
            return True
    return False

def page_text_blocks(page, page_no, header_y, footer_y, table_rects=None):
    blocks = []
    for block in page.get_text("dict").get("blocks", []):
        if block.get("type") != 0:
            continue
        rect = fitz.Rect(block.get("bbox", (0, 0, 0, 0)))
        if not in_body(rect, header_y, footer_y):
            continue
        if rect_mostly_covered(rect, table_rects):
            continue

        text = "\n".join(
            line_text_preserving_scripts(line.get("spans", []))
            for line in block.get("lines", [])
        )
        cleaned = normalize_text(text)
        if not cleaned:
            continue

        blocks.append({
            "page": page_no,
            "x0": rect.x0,
            "y0": rect.y0,
            "x1": rect.x1,
            "y1": rect.y1,
            "text": cleaned,
            "block_no": block.get("number", 0),
            "type": block.get("type", 0),
        })

    return sorted(blocks, key=lambda b: (b["y0"], b["x0"]))


def page_text_lines(page, page_no, header_y, footer_y, table_rects=None):
    lines = []
    for block in page.get_text("dict").get("blocks", []):
        if block.get("type") != 0:
            continue
        for line in block.get("lines", []):
            rect = fitz.Rect(line.get("bbox", (0, 0, 0, 0)))
            if not in_body(rect, header_y, footer_y) or rect_mostly_covered(rect, table_rects):
                continue
            text = normalize_text(line_text_preserving_scripts(line.get("spans", [])))
            if text:
                lines.append({"page": page_no, "x0": rect.x0, "y0": rect.y0,
                              "x1": rect.x1, "y1": rect.y1, "text": text,
                              "block_no": block.get("number", 0), "type": 0})
    return sorted(lines, key=lambda line: (round(line["y0"], 1), line["x0"]))


def pdf_text_part(text, block):
    return {
        "text": text,
        "page": block["page"],
        "bbox": (block["x0"], block["y0"], block["x1"], block["y1"]),
        "source": "pdf_text",
    }


def part_text(part):
    return part.get("text", "") if isinstance(part, dict) else str(part)

def parse_questions(doc, header_y, footer_y, tables_by_page=None, line_mode=False, renumber_resets=False):
    questions = []
    current = None
    current_option = None
    number_offset = 0
    last_raw_number = 0
    maximum_number = 0

    for page_index, page in enumerate(doc):
        page_no = page_index + 1
        table_rects = [item["rect"] for item in (tables_by_page or {}).get(page_no, [])]
        items = page_text_lines(page, page_no, header_y, footer_y, table_rects) if line_mode else page_text_blocks(page, page_no, header_y, footer_y, table_rects=table_rects)
        for block in items:
            text = block["text"]
            first_line = text.splitlines()[0] if text else ""
            question_match = None if QUESTION_RANGE_RE.search(first_line) else QUESTION_RE.match(first_line)

            if question_match:
                if current:
                    questions.append(current)
                raw_number = int(question_match.group(1))
                if renumber_resets and last_raw_number >= 8 and raw_number <= 3:
                    number_offset = maximum_number
                question_no = str(number_offset + raw_number)
                last_raw_number = raw_number
                maximum_number = max(maximum_number, int(question_no))
                current = {
                    "question_no": question_no, "source_pages": [page_no],
                    "text_parts": [], "options": {}, "question_images": [],
                    "image_objects": [], "start_page": page_no, "start_y": block["y0"],
                    "option_positions": [],
                }
                current_option = None
                text = QUESTION_RE.sub("", text, count=1).strip()

            if not current:
                continue
            if page_no not in current["source_pages"]:
                current["source_pages"].append(page_no)
            if QUESTION_RANGE_RE.search(first_line):
                current_option = None
                continue

            markers = list(re.finditer(r"(?<!\w)\(([A-D])\)\s*", text, re.IGNORECASE))
            if markers:
                prefix = text[:markers[0].start()].strip()
                if prefix:
                    add_cell_part(current, current_option, pdf_text_part(prefix, block))
                for index, marker in enumerate(markers):
                    label = marker.group(1).upper()
                    current_option = label
                    current["options"].setdefault(label, {"text_parts": [], "images": []})
                    marker_x = block["x0"] + (block["x1"] - block["x0"]) * marker.start() / max(1, len(text))
                    current["option_positions"].append({"page": page_no, "label": label, "y": block["y0"], "x": marker_x})
                    finish = markers[index + 1].start() if index + 1 < len(markers) else len(text)
                    option_text = text[marker.end():finish].strip()
                    if option_text:
                        current["options"][label]["text_parts"].append(pdf_text_part(option_text, block))
                continue

            if text:
                add_cell_part(current, current_option, pdf_text_part(text, block))

    if current:
        questions.append(current)
    return questions


def choose_question_for_y(questions, page_no, y):
    candidates = [
        q for q in questions
        if q["start_page"] < page_no or (q["start_page"] == page_no and q["start_y"] <= y)
    ]
    if not candidates:
        return None
    return candidates[-1]



def add_cell_part(question, option_label, value):
    if option_label:
        question["options"].setdefault(option_label, {
            "text_parts": [],
            "images": [],
        })
        question["options"][option_label]["text_parts"].append(value)
    else:
        question["text_parts"].append(value)

def choose_option_for_y(question, page_no, y):
    positions = sorted(
        (p for p in question["option_positions"] if p["page"] == page_no),
        key=lambda p: p["y"],
    )
    if not positions:
        return None
    if len(positions) == 1:
        return positions[0]["label"] if y >= positions[0]["y"] else None

    first_boundary = positions[0]["y"] - (positions[1]["y"] - positions[0]["y"]) / 2
    if y < first_boundary:
        previous = sorted(
            (p for p in question["option_positions"] if p["page"] < page_no),
            key=lambda p: (p["page"], p["y"]),
        )
        return previous[-1]["label"] if previous else None
    for index, position in enumerate(positions[:-1]):
        upper = (position["y"] + positions[index + 1]["y"]) / 2
        if y < upper:
            return position["label"]
    return positions[-1]["label"]

def add_tables_to_questions(questions, tables_by_page):
    for page_no, tables in tables_by_page.items():
        for table in tables:
            owner = choose_question_for_y(questions, page_no, rect_center_y(table["rect"]))
            if not owner:
                continue
            option_label = choose_option_for_y(owner, page_no, rect_center_y(table["rect"]))
            add_cell_part(owner, option_label, table["html"])

def group_visual_occurrences(records, gap=20):
    groups = []
    for record in records:
        rect = record["rect"]
        matches = []
        for index, group in enumerate(groups):
            expanded = fitz.Rect(
                group["rect"].x0 - gap,
                group["rect"].y0 - gap,
                group["rect"].x1 + gap,
                group["rect"].y1 + gap,
            )
            if expanded.intersects(rect):
                matches.append(index)
        if not matches:
            groups.append({"rect": fitz.Rect(rect), "records": [record]})
            continue
        merged = {"rect": fitz.Rect(rect), "records": [record]}
        for index in reversed(matches):
            group = groups.pop(index)
            merged["rect"] |= group["rect"]
            merged["records"].extend(group["records"])
        groups.append(merged)

    changed = True
    while changed:
        changed = False
        stable = []
        for group in groups:
            for existing in stable:
                expanded = fitz.Rect(
                    existing["rect"].x0 - gap,
                    existing["rect"].y0 - gap,
                    existing["rect"].x1 + gap,
                    existing["rect"].y1 + gap,
                )
                if expanded.intersects(group["rect"]):
                    existing["rect"] |= group["rect"]
                    existing["records"].extend(group["records"])
                    changed = True
                    break
            else:
                stable.append(group)
        groups = stable
    return sorted(groups, key=lambda group: (group["rect"].y0, group["rect"].x0))


def rect_covered(rect, covers, minimum_ratio=0.60):
    if rect.get_area() <= 0:
        return False
    for cover in covers or []:
        intersection = rect & cover
        if not intersection.is_empty and intersection.get_area() / rect.get_area() >= minimum_ratio:
            return True
    return False

def is_tiny_visual_component(rect, maximum_dimension=18):
    return max(rect.width, rect.height) < maximum_dimension

def keep_embedded_visual(rect, option_label):
    return bool(option_label) or not is_tiny_visual_component(rect)



def save_embedded_images(doc, render_doc, questions, output_dir, paper_code, header_y, footer_y, repeated_images, raw_embedded_images=False):
    image_counts = Counter()
    covered_rects = defaultdict(list)

    for page_index, page in enumerate(doc):
        page_no = page_index + 1
        render_page = render_doc[page_index]
        owned_records = defaultdict(list)

        for image in page.get_images(full=True):
            xref = image[0]
            digest, image_data = get_image_signature(doc, xref)
            if digest in repeated_images:
                continue
            for rect in page.get_image_rects(xref):
                if not in_body(rect, header_y, footer_y):
                    continue
                owner = choose_question_for_y(questions, page_no, rect_center_y(rect))
                if not owner:
                    continue
                option_label = choose_option_for_y(owner, page_no, rect_center_y(rect))
                if not keep_embedded_visual(rect, option_label):
                    continue
                owned_records[(owner["question_no"], option_label or "")].append({
                    "owner": owner,
                    "option_label": option_label,
                    "rect": fitz.Rect(rect),
                    "image_data": image_data,
                })

        for (_, _), records in owned_records.items():
            for group in group_visual_occurrences(records):
                owner = group["records"][0]["owner"]
                option_label = group["records"][0]["option_label"]
                rect = group["rect"]
                key = (owner["question_no"], option_label or "")
                image_counts[key] += 1
                is_composite = len(group["records"]) > 1
                representative = max(group["records"], key=lambda item: item["rect"].get_area())
                image_data = representative["image_data"]
                ext = image_data["ext"] if raw_embedded_images and not is_composite else "png"
                filename = make_image_filename(
                    paper_code, owner["question_no"], image_counts[key],
                    option_label=option_label, ext=ext,
                )
                path = output_dir / filename

                if raw_embedded_images and not is_composite:
                    path.write_bytes(image_data["image"])
                    image_type = "embedded_raw"
                else:
                    padded = fitz.Rect(
                        max(rect.x0 - 3, 0), max(rect.y0 - 3, header_y),
                        min(rect.x1 + 3, page.rect.x1), min(rect.y1 + 3, footer_y),
                    )
                    pix = render_page.get_pixmap(matrix=fitz.Matrix(3, 3), clip=padded, alpha=False)
                    pix.save(str(path))
                    rect = padded
                    image_type = "embedded_composite" if is_composite else "embedded_rendered"

                add_cell_part(owner, option_label, f"[image: {filename}]")
                image_record = {
                    "file": filename, "type": image_type,
                    "page": page_no, "bbox": rect_tuple(rect),
                }
                if option_label:
                    owner["options"].setdefault(option_label, {"text_parts": [], "images": []})
                    owner["options"][option_label]["images"].append(filename)
                else:
                    owner["question_images"].append(filename)
                owner["image_objects"].append(image_record)
                covered_rects[page_no].append(rect)

    return covered_rects
def expanded_rect(rect, gap):
    return fitz.Rect(rect.x0 - gap, rect.y0 - gap, rect.x1 + gap, rect.y1 + gap)


def rects_touch(a, b, gap):
    return expanded_rect(a, gap).intersects(b)


def cluster_rects(rects, gap=42):
    """Merge nearby vector drawing boxes into figure-level crops."""
    candidates = []
    for rect in rects:
        if rect.width < 6 or rect.height < 6:
            continue
        # Ignore page/table border lines; they otherwise become giant fake figures.
        if rect.width > 430 and rect.height < 20:
            continue
        if rect.height > 620 and rect.width < 20:
            continue
        candidates.append(rect)

    clusters = []
    for rect in sorted(candidates, key=lambda r: (r.y0, r.x0)):
        matched = []
        for index, cluster in enumerate(clusters):
            if rects_touch(cluster, rect, gap):
                matched.append(index)

        if not matched:
            clusters.append(rect)
            continue

        merged = rect
        for index in reversed(matched):
            merged = merged | clusters.pop(index)
        clusters.append(merged)

    stable = False
    while not stable:
        stable = True
        merged_clusters = []
        for rect in clusters:
            for index, existing in enumerate(merged_clusters):
                if rects_touch(existing, rect, gap):
                    merged_clusters[index] = existing | rect
                    stable = False
                    break
            else:
                merged_clusters.append(rect)
        clusters = merged_clusters

    return sorted(clusters, key=lambda r: (r.y0, r.x0))

def save_vector_crops(doc, questions, output_dir, paper_code, header_y, footer_y, covered_rects_by_page=None):
    image_counts = Counter()

    for page_index, page in enumerate(doc):
        page_no = page_index + 1
        owned_drawings = defaultdict(lambda: {"owner": None, "option_label": None, "rects": []})

        drawing_rects = []
        for drawing in page.get_drawings():
            rect = drawing.get("rect")
            if rect and in_body(rect, header_y, footer_y):
                drawing_rects.append(rect)

        # Build complete local objects before assigning them to option bands.
        for rect in cluster_rects(drawing_rects, gap=8):
            owner = choose_question_for_y(questions, page_no, rect_center_y(rect))
            if not owner:
                continue
            option_label = choose_option_for_y(owner, page_no, rect_center_y(rect))
            key = (owner["question_no"], option_label or "")
            owned_drawings[key]["owner"] = owner
            owned_drawings[key]["option_label"] = option_label
            owned_drawings[key]["rects"].append(rect)

        for key, drawing_group in owned_drawings.items():
            owner = drawing_group["owner"]
            option_label = drawing_group["option_label"]
            for rect in cluster_rects(drawing_group["rects"]):
                if rect.width < 35 or rect.height < 25:
                    continue
                if rect.width > page.rect.width * 0.85 or rect.height > page.rect.height * 0.65:
                    continue
                if rect_covered(rect, (covered_rects_by_page or {}).get(page_no, [])):
                    continue

                existing_count = len(owner["options"].get(option_label, {}).get("images", [])) if option_label else len(owner["question_images"])
                image_counts[key] = max(image_counts[key], existing_count) + 1
                filename = make_image_filename(
                    paper_code, owner["question_no"], image_counts[key],
                    option_label=option_label, ext="png",
                )
                path = output_dir / filename
                padded = fitz.Rect(
                    max(rect.x0 - 8, 0), max(rect.y0 - 8, header_y),
                    min(rect.x1 + 8, page.rect.x1), min(rect.y1 + 8, footer_y),
                )
                pix = page.get_pixmap(matrix=fitz.Matrix(3, 3), clip=padded, alpha=False)
                pix.save(str(path))
                add_cell_part(owner, option_label, f"[image: {filename}]")
                image_record = {
                    "file": filename, "type": "vector_crop",
                    "page": page_no, "bbox": rect_tuple(padded),
                }
                if option_label:
                    owner["options"].setdefault(option_label, {"text_parts": [], "images": []})
                    owner["options"][option_label]["images"].append(filename)
                else:
                    owner["question_images"].append(filename)
                owner["image_objects"].append(image_record)

def remove_text_covered_by_images(questions):
    for question in questions:
        covers_by_page = defaultdict(list)
        for image in question.get("image_objects", []):
            bbox = image.get("bbox")
            if bbox:
                covers_by_page[image.get("page")].append(fitz.Rect(bbox))

        def visible(part):
            if not isinstance(part, dict) or part.get("source") != "pdf_text":
                return True
            rect = fitz.Rect(part["bbox"])
            return not rect_covered(rect, covers_by_page.get(part["page"], []), minimum_ratio=0.60)

        question["text_parts"] = [part for part in question["text_parts"] if visible(part)]
        for option in question["options"].values():
            option["text_parts"] = [part for part in option["text_parts"] if visible(part)]


def strip_question_number(text):
    return QUESTION_RE.sub("", text or "", count=1).strip()

def finalize_questions(questions):
    finalized = []

    for question in questions:
        options = {}
        for label in ["A", "B", "C", "D"]:
            if label not in question["options"]:
                continue
            option = question["options"][label]
            options[label] = {
                "text": "\n".join(part_text(part) for part in option["text_parts"]).strip(),
                "images": option["images"],
            }

        finalized.append({
            "question_no": question["question_no"],
            "source_pages": question["source_pages"],
            "question_text": strip_question_number("\n".join(part_text(part) for part in question["text_parts"])),
            "options": options,
            "question_images": question["question_images"],
            "image_objects": question["image_objects"],
        })

    return finalized



def write_csv(result, csv_path):
    fieldnames = [
        "paper_code",
        "question_no",
        "question",
        "option1",
        "option2",
        "option3",
        "option4",
        "question_images",
        "option1_images",
        "option2_images",
        "option3_images",
        "option4_images",
        "source_pages",
    ]

    with csv_path.open("w", newline="", encoding="utf-8-sig") as csv_file:
        writer = csv.DictWriter(csv_file, fieldnames=fieldnames)
        writer.writeheader()

        for question in result["questions"]:
            options = question.get("options", {})
            row = {
                "paper_code": result["paper_code"],
                "question_no": question["question_no"],
                "question": question.get("question_text", ""),
                "option1": options.get("A", {}).get("text", ""),
                "option2": options.get("B", {}).get("text", ""),
                "option3": options.get("C", {}).get("text", ""),
                "option4": options.get("D", {}).get("text", ""),
                "question_images": ";".join(question.get("question_images", [])),
                "option1_images": ";".join(options.get("A", {}).get("images", [])),
                "option2_images": ";".join(options.get("B", {}).get("images", [])),
                "option3_images": ";".join(options.get("C", {}).get("images", [])),
                "option4_images": ";".join(options.get("D", {}).get("images", [])),
                "source_pages": ";".join(str(page) for page in question.get("source_pages", [])),
            }
            writer.writerow(row)

def extract_pdf(pdf_path, output_dir, paper_code, header_y, footer_y, repeated_ratio, include_vector_crops, raw_embedded_images=False, line_mode=False, renumber_resets=False, max_pages=None):
    pdf_path = Path(pdf_path)
    output_dir = Path(output_dir)
    images_dir = output_dir / "images"
    images_dir.mkdir(parents=True, exist_ok=True)

    doc = fitz.open(pdf_path)
    if max_pages is not None and doc.page_count > int(max_pages):
        doc.select(range(int(max_pages)))
    repeated_images = find_repeated_images(doc, repeated_ratio)
    tables_by_page = collect_tables(doc, header_y, footer_y)
    render_doc = fitz.open(pdf_path)
    if max_pages is not None and render_doc.page_count > int(max_pages):
        render_doc.select(range(int(max_pages)))
    removed_repeated_image_count = remove_repeated_images(render_doc, repeated_images)
    questions = parse_questions(doc, header_y, footer_y, tables_by_page=tables_by_page, line_mode=line_mode, renumber_resets=renumber_resets)
    add_tables_to_questions(questions, tables_by_page)

    covered_rects_by_page = save_embedded_images(
        doc=doc,
        render_doc=render_doc,
        questions=questions,
        output_dir=images_dir,
        paper_code=paper_code,
        header_y=header_y,
        footer_y=footer_y,
        repeated_images=repeated_images,
        raw_embedded_images=raw_embedded_images,
    )

    if include_vector_crops:
        save_vector_crops(
            doc=render_doc,
            questions=questions,
            output_dir=images_dir,
            paper_code=paper_code,
            header_y=header_y,
            footer_y=footer_y,
            covered_rects_by_page=covered_rects_by_page,
        )

    remove_text_covered_by_images(questions)

    result = {
        "paper_code": paper_code,
        "source_pdf": str(pdf_path),
        "page_count": doc.page_count,
        "ignored_repeated_image_count": len(repeated_images),
        "removed_repeated_image_occurrences_for_rendering": removed_repeated_image_count,
        "header_y": header_y,
        "footer_y": footer_y,
        "table_count": sum(len(items) for items in tables_by_page.values()),
        "questions": finalize_questions(questions),
    }

    json_path = output_dir / f"{clean_name(paper_code).upper()}_questions.json"
    json_path.write_text(json.dumps(result, indent=2, ensure_ascii=False), encoding="utf-8")

    csv_path = output_dir / f"{clean_name(paper_code).upper()}_questions.csv"
    write_csv(result, csv_path)

    return json_path, csv_path, images_dir, len(result["questions"])


def main():
    parser = argparse.ArgumentParser(
        description="Extract question text and images from exam PDFs into JSON + image files."
    )
    parser.add_argument("--check-api-env", action="store_true", help="Check whether DeepSeek/OpenAI environment variables are visible, without printing secret values.")
    parser.add_argument("--pdf", help="Input PDF path")
    parser.add_argument("--paper-code", help="Example: AE2025")
    parser.add_argument("--out", default="extracted_output", help="Output folder")
    parser.add_argument("--header-y", type=float, default=70, help="Ignore content above this y-coordinate")
    parser.add_argument("--footer-y", type=float, default=760, help="Ignore content below this y-coordinate")
    parser.add_argument("--repeated-ratio", type=float, default=0.70, help="Ignore images appearing on this ratio of pages")
    parser.add_argument("--extract-raw-embedded-images", action="store_true", help="Save raw embedded image bytes instead of rendered PDF appearance.")
    parser.add_argument(
        "--include-vector-crops",
        action="store_true",
        help="Also crop PDF vector drawings. Useful for non-embedded diagrams, but review output because it can over-crop.",
    )

    args = parser.parse_args()
    if args.check_api_env:
        print_api_env_status()
        return
    if not args.pdf:
        parser.error("--pdf is required unless --check-api-env is used")
    if not args.paper_code:
        parser.error("--paper-code is required unless --check-api-env is used")

    json_path, csv_path, images_dir, question_count = extract_pdf(
        pdf_path=args.pdf,
        output_dir=args.out,
        paper_code=args.paper_code,
        header_y=args.header_y,
        footer_y=args.footer_y,
        repeated_ratio=args.repeated_ratio,
        include_vector_crops=args.include_vector_crops,
        raw_embedded_images=args.extract_raw_embedded_images,
    )

    print(f"Done. Extracted {question_count} questions.")
    print(f"JSON: {json_path}")
    print(f"CSV: {csv_path}")
    print(f"Images: {images_dir}")


if __name__ == "__main__":
    main()

























