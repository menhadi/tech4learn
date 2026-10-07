import argparse
import csv
import json
import re
from collections import OrderedDict
from pathlib import Path

import fitz

from extraction_contract import (
    ExtractionContext,
    ImageManifest,
    image_cell,
    image_list_cell,
    write_extraction_manifest,
    write_standard_questions_csv,
)

ITEM_RE = re.compile(r"^Item\s*No\s*:\s*(\d+)\s*$", re.IGNORECASE)
QUESTION_ID_RE = re.compile(r"^Question\s*ID\s*:\s*(\d+)\s*$", re.IGNORECASE)
QUESTION_TYPE_RE = re.compile(r"^Question\s*Type\s*:\s*(.+?)\s*$", re.IGNORECASE)
SECTION_RE = re.compile(r"^Section\s*:\s*(.+?)\s*$", re.IGNORECASE)
PAPER_RE = re.compile(r"^Paper\s*:\s*(.+?)\s*$", re.IGNORECASE)
SET_RE = re.compile(r"^Set\s*Name\s*:\s*(.+?)\s*$", re.IGNORECASE)
EXAM_DATE_RE = re.compile(r"^Exam\s*Date\s*:\s*(.+?)\s*$", re.IGNORECASE)
EXAM_SHIFT_RE = re.compile(r"^Exam\s*Shift\s*:\s*(.+?)\s*$", re.IGNORECASE)
LANGUAGE_RE = re.compile(r"^(?:Language|Langauge)\s*:\s*(.+?)\s*$", re.IGNORECASE)
ROLE_RE = re.compile(r"^(Question|A|B|C|D)\s*:\s*$", re.IGNORECASE)

EXPECTED_ROLES = ["QUESTION", "A", "B", "C", "D"]
ROLE_FILENAMES = {
    "QUESTION": "question",
    "A": "option1",
    "B": "option2",
    "C": "option3",
    "D": "option4",
}


def clean_name(value):
    value = re.sub(r"[^A-Za-z0-9]+", "_", str(value).strip())
    return value.strip("_")


def block_text(block):
    if block.get("type") != 0:
        return ""
    lines = []
    for line in block.get("lines", []):
        lines.append("".join(span.get("text", "") for span in line.get("spans", [])))
    return "\n".join(lines).replace("\xa0", " ").strip()


def first_match(pattern, text):
    match = pattern.match(text)
    return match.group(1).strip() if match else None


def image_role(image_bbox, labels):
    matches = []
    for role, label_bbox in labels:
        vertical_overlap = min(image_bbox[3], label_bbox[3]) - max(image_bbox[1], label_bbox[1])
        horizontal_gap = image_bbox[0] - label_bbox[2]
        if vertical_overlap > 0 and 0 <= horizontal_gap <= 120:
            center_delta = abs((image_bbox[1] + image_bbox[3]) - (label_bbox[1] + label_bbox[3]))
            matches.append((center_delta, horizontal_gap, role))
    return min(matches)[2] if matches else None


def page_events(page):
    text_blocks = [
        block for block in page.get_text("dict").get("blocks", [])
        if block.get("type") == 0
    ]
    labels = []
    for block in text_blocks:
        match = ROLE_RE.match(block_text(block))
        if match:
            labels.append((match.group(1).upper(), tuple(block["bbox"])))

    events = []
    for block in text_blocks:
        events.append({
            "kind": "text",
            "bbox": tuple(block["bbox"]),
            "text": block_text(block),
        })

    for info in page.get_image_info(xrefs=True):
        bbox = tuple(info["bbox"])
        events.append({
            "kind": "image",
            "bbox": bbox,
            "xref": info.get("xref", 0),
            "width": info.get("width", 0),
            "height": info.get("height", 0),
            "role": image_role(bbox, labels),
        })

    return sorted(events, key=lambda event: (event["bbox"][1], event["bbox"][0], event["kind"] == "text"))


def new_question(question_no, section):
    return {
        "question_no": question_no,
        "question_id": "",
        "question_type": "",
        "section": section,
        "image_items": [],
        "source_pages": [],
    }


def passage_candidates(items, count):
    unresolved = [index for index, item in enumerate(items) if item["role"] is None]
    ranked = sorted(
        unresolved,
        key=lambda index: (
            items[index]["bbox"][3] - items[index]["bbox"][1],
            items[index]["width"] * items[index]["height"],
        ),
        reverse=True,
    )
    return set(ranked[:count])


def deduplicate_page_edge_images(items):
    deduplicated = []
    for item in items:
        duplicate = None
        for existing in reversed(deduplicated):
            if existing["xref"] != item["xref"]:
                continue
            if item["page"] - existing["page"] != 1:
                continue
            crosses_bottom = existing["bbox"][3] > existing["page_height"]
            crosses_top = item["bbox"][1] < 0
            if crosses_bottom or crosses_top:
                duplicate = existing
                break

        if duplicate is None:
            deduplicated.append(item)
            continue
        if duplicate["role"] is None and item["role"] is not None:
            duplicate["role"] = item["role"]

    return deduplicated


def classify_question_images(question, warnings):
    items = deduplicate_page_edge_images(question["image_items"])
    question["image_items"] = items
    extra_count = max(0, len(items) - len(EXPECTED_ROLES))
    passage_indexes = passage_candidates(items, extra_count)
    for index in passage_indexes:
        items[index]["role"] = "PASSAGE"

    assigned = {item["role"] for item in items if item["role"] in EXPECTED_ROLES}
    missing_roles = [role for role in EXPECTED_ROLES if role not in assigned]
    unresolved = [item for item in items if item["role"] is None]
    for item, role in zip(unresolved, missing_roles):
        item["role"] = role
        warnings.append(
            f"Q{question['question_no']}: inferred {role} for a page-boundary image on page {item['page']}"
        )

    for item in items:
        if item["role"] is None:
            item["role"] = "PASSAGE"
            warnings.append(
                f"Q{question['question_no']}: treated an additional unlabelled image on page {item['page']} as passage"
            )

    assigned = {item["role"] for item in items}
    for role in EXPECTED_ROLES:
        if role not in assigned:
            warnings.append(f"Q{question['question_no']}: no {role} image found")


def save_question_images(doc, question, context, manifest, warnings):
    files = {
        "passage_image_files": [],
        "question_image_files": [],
        "options": {"A": [], "B": [], "C": [], "D": []},
    }
    role_counts = {}

    for source_order, item in enumerate(question["image_items"], start=1):
        role = item["role"]
        xref = item["xref"]
        if not xref:
            warnings.append(
                f"Q{question['question_no']}: skipped an inline image without an extractable PDF reference"
            )
            continue
        extracted = doc.extract_image(xref)
        image_bytes = extracted.get("image")
        if not image_bytes:
            warnings.append(f"Q{question['question_no']}: could not extract image reference {xref}")
            continue

        role_name = "passage" if role == "PASSAGE" else ROLE_FILENAMES[role]
        role_counts[role_name] = role_counts.get(role_name, 0) + 1
        image_index = role_counts[role_name]
        extension = clean_name(extracted.get("ext") or "png").lower()
        filename = context.image_filename(
            question["question_no"], role_name, image_index, extension
        )
        (context.images_dir / filename).write_bytes(image_bytes)
        manifest_row = manifest.add(
            question_no=question["question_no"],
            role=role_name,
            image_index=image_index,
            filename=filename,
            source_order=source_order,
            source_page=item["page"],
            bbox=item["bbox"],
            width=item["width"],
            height=item["height"],
            image_bytes=image_bytes,
        )
        image_key = manifest_row["relative_path"]

        if role == "PASSAGE":
            files["passage_image_files"].append(image_key)
        elif role == "QUESTION":
            files["question_image_files"].append(image_key)
        else:
            files["options"][role].append(image_key)

    return files


def write_csv(result, context, csv_path):
    rows = []
    metadata = result["metadata"]
    for question in result["questions"]:
        options = question["options"]
        row = context.base_question_row()
        row.update({
            "paper": metadata["paper"],
            "subject": metadata["paper"],
            "set_name": metadata["set_name"],
            "exam_date": metadata["exam_date"],
            "exam_shift": metadata["exam_shift"],
            "language": metadata["language"],
            "section": question["section"],
            "question_no": question["question_no"],
            "question_id": question["question_id"],
            "question_type": question["question_type"],
            "passage": image_cell(question["passage_image_files"]),
            "question": image_cell(question["question_image_files"]),
            "option1": image_cell(options["A"]),
            "option2": image_cell(options["B"]),
            "option3": image_cell(options["C"]),
            "option4": image_cell(options["D"]),
            "passage_images": image_list_cell(question["passage_image_files"]),
            "question_images": image_list_cell(question["question_image_files"]),
            "option1_images": image_list_cell(options["A"]),
            "option2_images": image_list_cell(options["B"]),
            "option3_images": image_list_cell(options["C"]),
            "option4_images": image_list_cell(options["D"]),
            "source_pages": ";".join(str(page) for page in question["source_pages"]),
            "metadata_json": metadata,
            "warning_count": len(result["warnings"]),
        })
        rows.append(row)
    return write_standard_questions_csv(rows, csv_path)

def extract_cuetug_pdf(pdf_path, input_root, output_root, paper_code):
    context = ExtractionContext.create(
        pdf_path, input_root, output_root, paper_code, "cuetug"
    )
    doc = fitz.open(context.source_path)
    questions = OrderedDict()
    metadata = {
        "paper": "",
        "set_name": "",
        "exam_date": "",
        "exam_shift": "",
        "language": "",
    }
    current_question = None
    current_section = ""

    for page_index, page in enumerate(doc):
        page_no = page_index + 1
        for event in page_events(page):
            if event["kind"] == "text":
                text = event["text"]
                section = first_match(SECTION_RE, text)
                if section is not None:
                    current_section = section
                    if current_question is not None and not current_question["section"]:
                        current_question["section"] = section
                    continue

                item_no = first_match(ITEM_RE, text)
                if item_no is not None:
                    current_question = questions.setdefault(item_no, new_question(item_no, current_section))
                    if page_no not in current_question["source_pages"]:
                        current_question["source_pages"].append(page_no)
                    continue

                question_id = first_match(QUESTION_ID_RE, text)
                if question_id is not None and current_question is not None:
                    current_question["question_id"] = question_id
                    continue

                question_type = first_match(QUESTION_TYPE_RE, text)
                if question_type is not None and current_question is not None:
                    current_question["question_type"] = question_type
                    continue

                for key, pattern in (
                    ("paper", PAPER_RE),
                    ("set_name", SET_RE),
                    ("exam_date", EXAM_DATE_RE),
                    ("exam_shift", EXAM_SHIFT_RE),
                    ("language", LANGUAGE_RE),
                ):
                    value = first_match(pattern, text)
                    if value is not None:
                        metadata[key] = value
                        break
                continue

            if current_question is None:
                continue
            if page_no not in current_question["source_pages"]:
                current_question["source_pages"].append(page_no)
            current_question["image_items"].append({
                "xref": event["xref"],
                "bbox": event["bbox"],
                "width": event["width"],
                "height": event["height"],
                "role": event["role"],
                "page": page_no,
                "page_height": page.rect.height,
            })

    warnings = []
    manifest = ImageManifest(context)
    output_questions = []
    for question in questions.values():
        classify_question_images(question, warnings)
        files = save_question_images(doc, question, context, manifest, warnings)
        output_questions.append({
            "question_no": question["question_no"],
            "question_id": question["question_id"],
            "question_type": question["question_type"],
            "section": question["section"],
            "passage_image_files": files["passage_image_files"],
            "question_image_files": files["question_image_files"],
            "options": files["options"],
            "source_pages": question["source_pages"],
        })

    result = {
        "document": context.contract_metadata(),
        "paper_code": context.paper_code,
        "source_pdf": str(context.source_path),
        "layout": "cuetug",
        "page_count": doc.page_count,
        "question_count": len(output_questions),
        "metadata": metadata,
        "warnings": warnings,
        "questions": output_questions,
    }
    json_path = context.output_dir / "questions.json"
    csv_path = context.output_dir / "questions.csv"
    images_manifest_path = manifest.write()
    extraction_manifest_path = write_extraction_manifest(
        context,
        metadata,
        {"pages": doc.page_count, "questions": len(output_questions), "images": len(manifest.rows)},
        warnings,
    )
    json_path.write_text(json.dumps(result, indent=2, ensure_ascii=False), encoding="utf-8")
    write_csv(result, context, csv_path)
    return (
        json_path,
        csv_path,
        context.images_dir,
        images_manifest_path,
        extraction_manifest_path,
        len(output_questions),
        warnings,
    )

def main():
    parser = argparse.ArgumentParser(
        description="Extract CUET-UG images using the standard hierarchy-aware output contract."
    )
    parser.add_argument("--pdf", required=True, help="Input CUET-UG PDF path")
    parser.add_argument("--input-root", required=True, help="Root used to derive source hierarchy")
    parser.add_argument("--output-root", required=True, help="Root for mirrored extraction output")
    parser.add_argument("--paper-code", required=True, help="Paper identifier")
    args = parser.parse_args()

    (
        json_path,
        csv_path,
        images_dir,
        images_manifest_path,
        extraction_manifest_path,
        question_count,
        warnings,
    ) = extract_cuetug_pdf(args.pdf, args.input_root, args.output_root, args.paper_code)
    print(f"Done. Extracted {question_count} questions.")
    print(f"JSON: {json_path}")
    print(f"CSV: {csv_path}")
    print(f"Images: {images_dir}")
    print(f"Image manifest: {images_manifest_path}")
    print(f"Extraction manifest: {extraction_manifest_path}")
    print(f"Warnings: {len(warnings)}")


if __name__ == "__main__":
    main()