import argparse
import csv
import json
from io import BytesIO
import re
from pathlib import Path

import fitz
from PIL import Image


QUESTION_MARKER_RE = re.compile(r"Question\s+Number\s*:\s*(\d+)", re.IGNORECASE)
UGC_QUESTION_MARKER_RE = re.compile(r"Sl\.\s*No\.\s*(\d+)\s+QBID\s*:\s*(\d+)", re.IGNORECASE)
OPTIONS_MARKER_RE = re.compile(r"Options\s*:", re.IGNORECASE)
UGC_OPTION_RE = re.compile(r"^\s*\((\d)\)\s*$")
OPTION_LABELS = ["A", "B", "C", "D"]


def clean_name(value):
    value = str(value).strip()
    value = re.sub(r"[^A-Za-z0-9]+", "_", value)
    return value.strip("_")


def make_image_filename(paper_code, question_no, role, index=1, ext="png"):
    paper_code = clean_name(paper_code).upper()
    question_no = clean_name(question_no)
    role = clean_name(role).lower()
    ext = clean_name(ext).lower() or "png"
    return f"{paper_code}_q{question_no}_{role}_img{index}.{ext}"


def make_ugc_image_filename(question_no, role, index=1, ext="png"):
    question_no = clean_name(question_no)
    role = clean_name(role).lower()
    ext = clean_name(ext).lower() or "png"
    return f"q{question_no}_{role}_img{index}.{ext}"


def make_single_image_filename(paper_code, question_no, role, ext="png"):
    paper_code = clean_name(paper_code).upper()
    question_no = clean_name(question_no)
    role = clean_name(role).lower()
    ext = clean_name(ext).lower() or "png"
    return f"{paper_code}_q{question_no}_{role}_img1.{ext}"


def block_text(block):
    if block.get("type") != 0:
        return ""
    parts = []
    for line in block.get("lines", []):
        parts.append("".join(span.get("text", "") for span in line.get("spans", [])))
    return "\n".join(parts).strip()


def image_bytes_and_ext(block):
    return block.get("image"), block.get("ext") or "png"


def image_cell(files):
    return "\n".join(f"[image: {name}]" for name in files)


def ensure_jee_question(questions, question_no):
    if question_no not in questions:
        questions[question_no] = {
            "question_no": question_no,
            "raw_question_images": [],
            "passage_image_files": [],
            "question_image_files": [],
            "options": {label: [] for label in OPTION_LABELS},
            "source_pages": [],
        }
    return questions[question_no]


def finalize_question_images(question):
    raw = question["raw_question_images"]
    if not raw:
        return
    if len(raw) == 1:
        question["question_image_files"] = raw
        return
    question["passage_image_files"] = raw[:-1]
    question["question_image_files"] = raw[-1:]


def extract_jee_image_pdf(pdf_path, output_dir, paper_code, keep_duplicates=False):
    pdf_path = Path(pdf_path)
    output_dir = Path(output_dir)
    images_dir = output_dir / "images"
    images_dir.mkdir(parents=True, exist_ok=True)

    doc = fitz.open(pdf_path)
    questions = {}
    current_question = None
    current_section = None
    option_index = 0
    image_counts = {}
    duplicate_mode = False

    for page_index, page in enumerate(doc):
        page_no = page_index + 1
        blocks = sorted(page.get_text("dict")["blocks"], key=lambda b: (b["bbox"][1], b["bbox"][0]))
        for block in blocks:
            text = block_text(block)
            question_match = QUESTION_MARKER_RE.search(text)
            if question_match:
                question_no = question_match.group(1)
                duplicate_mode = False
                if question_no in questions:
                    finalize_question_images(questions[question_no])
                    complete = len(questions[question_no]["options"]) >= 4 and questions[question_no]["question_image_files"]
                    if complete and not keep_duplicates:
                        duplicate_mode = True
                        current_question = None
                        current_section = None
                        option_index = 0
                        continue
                current_question = ensure_jee_question(questions, question_no)
                if page_no not in current_question["source_pages"]:
                    current_question["source_pages"].append(page_no)
                current_section = "question"
                option_index = 0
                continue

            if duplicate_mode:
                continue
            if OPTIONS_MARKER_RE.search(text) and current_question is not None:
                current_section = "options"
                option_index = 0
                continue
            if block.get("type") != 1 or current_question is None:
                continue

            image_data, ext = image_bytes_and_ext(block)
            if not image_data:
                continue
            if page_no not in current_question["source_pages"]:
                current_question["source_pages"].append(page_no)

            question_no = current_question["question_no"]
            if current_section == "options":
                if option_index >= len(OPTION_LABELS):
                    continue
                label = OPTION_LABELS[option_index]
                role = f"option{option_index + 1}"
                key = (question_no, role)
                image_counts[key] = image_counts.get(key, 0) + 1
                filename = make_image_filename(paper_code, question_no, role, image_counts[key], ext)
                (images_dir / filename).write_bytes(image_data)
                current_question["options"][label].append(filename)
                option_index += 1
            else:
                key = (question_no, "question")
                image_counts[key] = image_counts.get(key, 0) + 1
                filename = make_image_filename(paper_code, question_no, "question", image_counts[key], ext)
                (images_dir / filename).write_bytes(image_data)
                current_question["raw_question_images"].append(filename)

    for question in questions.values():
        finalize_question_images(question)

    ordered = sorted(questions.values(), key=lambda q: int(q["question_no"]))
    result = {
        "paper_code": paper_code,
        "source_pdf": str(pdf_path),
        "page_count": doc.page_count,
        "layout": "jee",
        "question_count": len(ordered),
        "questions": ordered,
    }
    json_path = output_dir / f"{clean_name(paper_code).upper()}_questions.json"
    csv_path = output_dir / f"{clean_name(paper_code).upper()}_questions.csv"
    json_path.write_text(json.dumps(result, indent=2, ensure_ascii=False), encoding="utf-8")
    write_jee_csv(result, csv_path)
    return json_path, csv_path, images_dir, len(ordered)


def ensure_ugc_question(questions, question_no, qbid=None):
    if question_no not in questions:
        questions[question_no] = {
            "question_no": question_no,
            "qbid": qbid or "",
            "image_items": [],
            "passage_image_files": [],
            "passage_hindi_image_files": [],
            "question_image_files": [],
            "question_hindi_image_files": [],
            "options": {label: [] for label in OPTION_LABELS},
            "options_hindi": {label: [] for label in OPTION_LABELS},
            "source_pages": [],
            "images_finalized": False,
        }
    elif qbid and not questions[question_no].get("qbid"):
        questions[question_no]["qbid"] = qbid
    return questions[question_no]


def save_named_image(images_dir, paper_code, question_no, role, index, item):
    filename = make_ugc_image_filename(question_no, role, index, item["ext"])
    (images_dir / filename).write_bytes(item["data"])
    return filename


def same_row(label_bbox, image_bbox):
    label_mid = (label_bbox[1] + label_bbox[3]) / 2
    image_mid = (image_bbox[1] + image_bbox[3]) / 2
    image_height = max(image_bbox[3] - image_bbox[1], 1)
    return abs(label_mid - image_mid) <= max(25, image_height * 0.75)


def nearest_option_number(image_bbox, option_labels):
    image_height = image_bbox[3] - image_bbox[1]
    if image_height > 80:
        return None

    matches = []
    for option_number, label_bbox in option_labels:
        vertical_overlap = min(label_bbox[3], image_bbox[3]) - max(label_bbox[1], image_bbox[1])
        horizontal_gap = image_bbox[0] - label_bbox[2]
        if vertical_overlap > 0 and 0 <= horizontal_gap <= 120:
            matches.append((horizontal_gap, option_number))
    return min(matches)[1] if matches else None


def finalize_ugc_images(question, images_dir, paper_code):
    if question is None or question.get("images_finalized"):
        return

    items = question.get("image_items", [])
    qno = question["question_no"]
    option_occurrences = {number: 0 for number in range(1, 5)}
    first_option_index = None
    first_hindi_option_index = None

    for index, item in enumerate(items):
        option_number = item.get("option_number")
        if option_number is None:
            continue
        option_occurrences[option_number] += 1
        occurrence = option_occurrences[option_number]
        label = OPTION_LABELS[option_number - 1]
        if occurrence == 1:
            first_option_index = index if first_option_index is None else min(first_option_index, index)
            filename = save_named_image(images_dir, paper_code, qno, f"option{option_number}", 1, item)
            question["options"][label] = [filename]
        elif occurrence == 2:
            first_hindi_option_index = index if first_hindi_option_index is None else min(first_hindi_option_index, index)
            filename = save_named_image(images_dir, paper_code, qno, f"option{option_number}_hindi", 1, item)
            question["options_hindi"][label] = [filename]

    before_english = [
        item for index, item in enumerate(items)
        if item.get("option_number") is None
        and (first_option_index is None or index < first_option_index)
    ]
    before_hindi = [
        item for index, item in enumerate(items)
        if item.get("option_number") is None
        and first_option_index is not None
        and index > first_option_index
        and (first_hindi_option_index is None or index < first_hindi_option_index)
    ]

    if before_english:
        question_item = before_english[-1]
        filename = save_named_image(images_dir, paper_code, qno, "question", 1, question_item)
        question["question_image_files"] = [filename]
        for passage_index, item in enumerate(before_english[:-1], start=1):
            if passage_index == 1:
                filename = save_named_image(images_dir, paper_code, qno, "passage", 1, item)
                question["passage_image_files"].append(filename)
            else:
                filename = save_named_image(images_dir, paper_code, qno, "passage_hindi", passage_index - 1, item)
                question["passage_hindi_image_files"].append(filename)

    if before_hindi:
        hindi_question_item = before_hindi[-1]
        filename = save_named_image(images_dir, paper_code, qno, "question_hindi", 1, hindi_question_item)
        question["question_hindi_image_files"] = [filename]

    question["image_items"] = []
    question["images_finalized"] = True


def extract_ugc_net_image_pdf(pdf_path, output_dir, paper_code):
    pdf_path = Path(pdf_path)
    output_dir = Path(output_dir)
    images_dir = output_dir / "images"
    images_dir.mkdir(parents=True, exist_ok=True)

    doc = fitz.open(pdf_path)
    questions = {}
    current_question = None

    for page_index, page in enumerate(doc):
        page_no = page_index + 1
        blocks = sorted(page.get_text("dict")["blocks"], key=lambda b: (b["bbox"][1], b["bbox"][0]))
        option_labels = []
        for block in blocks:
            option_match = UGC_OPTION_RE.match(block_text(block))
            if option_match:
                option_number = int(option_match.group(1))
                if 1 <= option_number <= 4:
                    option_labels.append((option_number, block["bbox"]))

        for block in blocks:
            text = block_text(block)
            marker = UGC_QUESTION_MARKER_RE.search(text)
            if marker:
                finalize_ugc_images(current_question, images_dir, paper_code)
                question_no, qbid = marker.group(1), marker.group(2)
                current_question = ensure_ugc_question(questions, question_no, qbid)
                if page_no not in current_question["source_pages"]:
                    current_question["source_pages"].append(page_no)
                continue

            if UGC_OPTION_RE.match(text):
                continue

            if block.get("type") != 1 or current_question is None:
                continue

            image_data, ext = image_bytes_and_ext(block)
            if not image_data:
                continue
            if page_no not in current_question["source_pages"]:
                current_question["source_pages"].append(page_no)

            current_question["image_items"].append({
                "data": image_data,
                "ext": ext,
                "bbox": block["bbox"],
                "option_number": nearest_option_number(block["bbox"], option_labels),
            })

    finalize_ugc_images(current_question, images_dir, paper_code)

    ordered = sorted(questions.values(), key=lambda q: int(q["question_no"]))
    for question in ordered:
        question.pop("image_items", None)
        question.pop("images_finalized", None)
    result = {
        "paper_code": paper_code,
        "source_pdf": str(pdf_path),
        "page_count": doc.page_count,
        "layout": "ugc-net",
        "question_count": len(ordered),
        "questions": ordered,
    }
    json_path = output_dir / f"{clean_name(paper_code).upper()}_questions.json"
    csv_path = output_dir / f"{clean_name(paper_code).upper()}_questions.csv"
    json_path.write_text(json.dumps(result, indent=2, ensure_ascii=False), encoding="utf-8")
    write_ugc_csv(result, csv_path)
    return json_path, csv_path, images_dir, len(ordered)

def write_jee_csv(result, csv_path):
    fieldnames = [
        "paper_code", "question_no", "passage", "question", "option1", "option2", "option3", "option4",
        "passage_images", "question_images", "option1_images", "option2_images", "option3_images", "option4_images", "source_pages",
    ]
    with csv_path.open("w", newline="", encoding="utf-8-sig") as csv_file:
        writer = csv.DictWriter(csv_file, fieldnames=fieldnames)
        writer.writeheader()
        for question in result["questions"]:
            options = question["options"]
            writer.writerow({
                "paper_code": result["paper_code"],
                "question_no": question["question_no"],
                "passage": image_cell(question["passage_image_files"]),
                "question": image_cell(question["question_image_files"]),
                "option1": image_cell(options["A"]),
                "option2": image_cell(options["B"]),
                "option3": image_cell(options["C"]),
                "option4": image_cell(options["D"]),
                "passage_images": ";".join(question["passage_image_files"]),
                "question_images": ";".join(question["question_image_files"]),
                "option1_images": ";".join(options["A"]),
                "option2_images": ";".join(options["B"]),
                "option3_images": ";".join(options["C"]),
                "option4_images": ";".join(options["D"]),
                "source_pages": ";".join(str(page) for page in question["source_pages"]),
            })


def write_ugc_csv(result, csv_path):
    fieldnames = [
        "passage", "passage_hindi", "question", "option1", "option2", "option3", "option4",
        "question_hindi", "option1_hindi", "option2_hindi", "option3_hindi", "option4_hindi",
        "question_no", "qbid", "paper_code",
        "passage_images", "passage_hindi_images", "question_images", "option1_images", "option2_images", "option3_images", "option4_images",
        "question_hindi_images", "option1_hindi_images", "option2_hindi_images", "option3_hindi_images", "option4_hindi_images",
        "source_pages",
    ]
    with csv_path.open("w", newline="", encoding="utf-8-sig") as csv_file:
        writer = csv.DictWriter(csv_file, fieldnames=fieldnames)
        writer.writeheader()
        for question in result["questions"]:
            options = question["options"]
            options_hindi = question.get("options_hindi", {label: [] for label in OPTION_LABELS})
            passage_hindi_images = question.get("passage_hindi_image_files", [])
            writer.writerow({
                "passage": image_cell(question["passage_image_files"]),
                "passage_hindi": image_cell(passage_hindi_images),
                "question": image_cell(question["question_image_files"]),
                "option1": image_cell(options["A"]),
                "option2": image_cell(options["B"]),
                "option3": image_cell(options["C"]),
                "option4": image_cell(options["D"]),
                "question_hindi": image_cell(question.get("question_hindi_image_files", [])),
                "option1_hindi": image_cell(options_hindi["A"]),
                "option2_hindi": image_cell(options_hindi["B"]),
                "option3_hindi": image_cell(options_hindi["C"]),
                "option4_hindi": image_cell(options_hindi["D"]),
                "question_no": question["question_no"],
                "qbid": question.get("qbid", ""),
                "paper_code": result["paper_code"],
                "passage_images": ";".join(question["passage_image_files"]),
                "passage_hindi_images": ";".join(passage_hindi_images),
                "question_images": ";".join(question["question_image_files"]),
                "option1_images": ";".join(options["A"]),
                "option2_images": ";".join(options["B"]),
                "option3_images": ";".join(options["C"]),
                "option4_images": ";".join(options["D"]),
                "question_hindi_images": ";".join(question.get("question_hindi_image_files", [])),
                "option1_hindi_images": ";".join(options_hindi["A"]),
                "option2_hindi_images": ";".join(options_hindi["B"]),
                "option3_hindi_images": ";".join(options_hindi["C"]),
                "option4_hindi_images": ";".join(options_hindi["D"]),
                "source_pages": ";".join(str(page) for page in question["source_pages"]),
            })

def render_clip_to_image(page, clip, zoom=2):
    pix = page.get_pixmap(matrix=fitz.Matrix(zoom, zoom), clip=clip, alpha=False)
    return Image.open(BytesIO(pix.tobytes("png"))).convert("RGB")


def stitch_images_vertically(images, background=(255, 255, 255), padding=8):
    if not images:
        return None
    width = max(image.width for image in images)
    height = sum(image.height for image in images) + padding * (len(images) - 1)
    combined = Image.new("RGB", (width, height), background)
    y = 0
    for image in images:
        combined.paste(image, (0, y))
        y += image.height + padding
    return combined


def extract_ugc_net_full_image_pdf(pdf_path, output_dir, paper_code):
    pdf_path = Path(pdf_path)
    output_dir = Path(output_dir)
    images_dir = output_dir / "images"
    images_dir.mkdir(parents=True, exist_ok=True)

    doc = fitz.open(pdf_path)
    markers = []
    for page_index, page in enumerate(doc):
        blocks = sorted(page.get_text("dict")["blocks"], key=lambda b: (b["bbox"][1], b["bbox"][0]))
        for block in blocks:
            text = block_text(block)
            match = UGC_QUESTION_MARKER_RE.search(text)
            if match:
                markers.append({
                    "question_no": match.group(1),
                    "qbid": match.group(2),
                    "page_index": page_index,
                    "y": block["bbox"][1],
                })

    questions = []
    page_bottom_margin = 18
    page_top_margin = 20
    x0 = 28
    x1 = 535

    for index, marker in enumerate(markers):
        next_marker = markers[index + 1] if index + 1 < len(markers) else None
        start_page = marker["page_index"]
        end_page = next_marker["page_index"] if next_marker else doc.page_count - 1
        segment_images = []
        source_pages = []

        for page_index in range(start_page, end_page + 1):
            page = doc[page_index]
            if page_index == start_page:
                y0 = max(marker["y"] - 6, 0)
            else:
                y0 = page_top_margin

            if next_marker and page_index == next_marker["page_index"]:
                y1 = max(next_marker["y"] - 8, y0 + 1)
            else:
                y1 = page.rect.y1 - page_bottom_margin

            if y1 <= y0 + 5:
                continue

            clip = fitz.Rect(x0, y0, min(x1, page.rect.x1), min(y1, page.rect.y1))
            segment_images.append(render_clip_to_image(page, clip))
            source_pages.append(page_index + 1)

        stitched = stitch_images_vertically(segment_images)
        if stitched is None:
            continue

        filename = make_image_filename(paper_code, marker["question_no"], "full_question", 1, "png")
        stitched.save(images_dir / filename)
        questions.append({
            "question_no": marker["question_no"],
            "qbid": marker["qbid"],
            "full_question_image": filename,
            "source_pages": source_pages,
        })

    result = {
        "paper_code": paper_code,
        "source_pdf": str(pdf_path),
        "page_count": doc.page_count,
        "layout": "ugc-net-full",
        "question_count": len(questions),
        "questions": questions,
    }
    json_path = output_dir / f"{clean_name(paper_code).upper()}_questions.json"
    csv_path = output_dir / f"{clean_name(paper_code).upper()}_questions.csv"
    json_path.write_text(json.dumps(result, indent=2, ensure_ascii=False), encoding="utf-8")
    write_ugc_full_csv(result, csv_path)
    return json_path, csv_path, images_dir, len(questions)


def write_ugc_full_csv(result, csv_path):
    fieldnames = [
        "paper_code", "question_no", "qbid", "question", "option1", "option2", "option3", "option4",
        "question_images", "source_pages",
    ]
    with csv_path.open("w", newline="", encoding="utf-8-sig") as csv_file:
        writer = csv.DictWriter(csv_file, fieldnames=fieldnames)
        writer.writeheader()
        for question in result["questions"]:
            filename = question["full_question_image"]
            writer.writerow({
                "paper_code": result["paper_code"],
                "question_no": question["question_no"],
                "qbid": question.get("qbid", ""),
                "question": f"[image: {filename}]",
                "option1": "",
                "option2": "",
                "option3": "",
                "option4": "",
                "question_images": filename,
                "source_pages": ";".join(str(page) for page in question["source_pages"]),
            })

def main():
    parser = argparse.ArgumentParser(description="Extract NTA image-based PDF questions/options into CSV.")
    parser.add_argument("--pdf", required=True, help="Input NTA PDF path")
    parser.add_argument("--paper-code", required=True, help="Example: JEE2020SHIFT1")
    parser.add_argument("--out", default="nta_image_pdf_output", help="Output folder")
    parser.add_argument("--layout", choices=["jee", "ugc-net"], default="jee", help="NTA image PDF layout type")
    parser.add_argument("--ugc-mode", choices=["embedded", "split", "full"], default="embedded", help="For --layout ugc-net: embedded/split extracts existing PDF image objects as-is; full renders one cropped image per whole question.")
    parser.add_argument("--keep-duplicates", action="store_true", help="Keep repeated bilingual/duplicate JEE question occurrences")
    args = parser.parse_args()

    if args.layout == "ugc-net":
        if args.ugc_mode == "full":
            json_path, csv_path, images_dir, question_count = extract_ugc_net_full_image_pdf(args.pdf, args.out, args.paper_code)
        else:
            json_path, csv_path, images_dir, question_count = extract_ugc_net_image_pdf(args.pdf, args.out, args.paper_code)
    else:
        json_path, csv_path, images_dir, question_count = extract_jee_image_pdf(
            args.pdf, args.out, args.paper_code, keep_duplicates=args.keep_duplicates
        )

    print(f"Done. Extracted {question_count} questions.")
    print(f"JSON: {json_path}")
    print(f"CSV: {csv_path}")
    print(f"Images: {images_dir}")


if __name__ == "__main__":
    main()
