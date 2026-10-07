"""Dedicated GATE extractor for scanned, selectable, and portal-export papers."""

import csv
import io
import json
import re
from collections import defaultdict
from pathlib import Path

import fitz
import pytesseract
from PIL import Image
from pytesseract import Output

from extraction_contract import (
    ExtractionContext, ImageManifest, image_list_cell,
    write_extraction_manifest, write_standard_questions_csv,
)
from jee_standard_extractor import extract_jee_standard
from pdf_standard_extractor import extract_pdf_standard

PORTAL_RE = re.compile(r"Question\s+Number\s*:\s*\d+", re.I)
SCAN_Q_RE = re.compile(r"^\s*[^A-Za-z0-9]{0,5}Q\s*\.?\s*(\d{1,3})(?!\d)[_\s]*", re.I)
RANGE_RE = re.compile(r"\d+\s*[-\u2013\u2014]\s*(?:Q\s*\.?)?\s*\d+|carry\s+(?:one|two|\d+)\s+mark", re.I)
OPTION_RE = re.compile(r"(?<!\w)\(([A-D])\)\s*", re.I)
LINKED_PASSAGE_RE = re.compile(
    r"(?:Statement\s+for\s+Linked\s+Answer\s+Questions?|Common\s+Data\s+for\s+Questions?)"
    r"\s+(\d{1,3})\s*(?:&|and)\s*(\d{1,3})",
    re.I,
)


def detect_gate_layout(pdf_path):
    with fitz.open(pdf_path) as doc:
        if not doc.page_count:
            raise ValueError("PDF contains zero readable pages; replace or repair the source file")
        sample = "\n".join(doc[i].get_text("text") for i in range(min(12, doc.page_count)))
        text_pages = sum(len(page.get_text("text").strip()) > 80 for page in doc)
        page_count = doc.page_count
    if len(PORTAL_RE.findall(sample)) >= 5:
        return "gate-portal-image"
    if text_pages < max(2, int(page_count * 0.25)):
        return "gate-scanned-page"
    return "gate-selectable"


def gate_content_page_limit(pdf_path):
    with fitz.open(pdf_path) as doc:
        for page_index, page in enumerate(doc):
            text = page.get_text("text").strip()
            first_lines = "\n".join(text.splitlines()[:8])
            if re.search(r"\bAnswer\s+Key\b", first_lines, re.IGNORECASE):
                return page_index
        return doc.page_count

def _enrich_gate_rows(csv_path, source_name, layout):
    csv_path = Path(csv_path)
    with csv_path.open(encoding="utf-8-sig", newline="") as source:
        reader = csv.DictReader(source)
        rows = list(reader)
    year = (re.search(r"(20\d{2})", source_name) or [""])[0]
    for row in rows:
        row.update({
            "exam_name": "GATE", "exam_year": year, "paper": "AE",
            "subject": "Aerospace Engineering", "language": "English",
        })
        try:
            metadata = json.loads(row.get("metadata_json") or "{}")
        except json.JSONDecodeError:
            metadata = {}
        metadata["layout"] = layout
        row["metadata_json"] = metadata
    write_standard_questions_csv(rows, csv_path)


def _ocr_lines(image):
    data = pytesseract.image_to_data(image, output_type=Output.DICT, config="--psm 6")
    grouped = defaultdict(list)
    for i, text in enumerate(data["text"]):
        text = text.strip()
        if not text:
            continue
        grouped[(data["block_num"][i], data["par_num"][i], data["line_num"][i])].append({
            "text": text, "x0": data["left"][i], "y0": data["top"][i],
            "x1": data["left"][i] + data["width"][i],
            "y1": data["top"][i] + data["height"][i],
        })
    lines = []
    for words in grouped.values():
        words.sort(key=lambda word: word["x0"])
        lines.append({
            "text": " ".join(word["text"] for word in words),
            "x0": min(word["x0"] for word in words), "y0": min(word["y0"] for word in words),
            "x1": max(word["x1"] for word in words), "y1": max(word["y1"] for word in words),
        })
    return sorted(lines, key=lambda line: (line["y0"], line["x0"]))


def _split_ocr_text(lines):
    question_parts, option_parts = [], defaultdict(list)
    current = None
    for line in lines:
        text = line["text"].strip()
        qmatch = SCAN_Q_RE.match(text)
        if qmatch:
            text = text[qmatch.end():].strip(" .:-")
        markers = list(OPTION_RE.finditer(text))
        if not markers:
            if current:
                option_parts[current].append(text)
            elif text and not RANGE_RE.search(text):
                question_parts.append(text)
            continue
        prefix = text[:markers[0].start()].strip()
        if prefix:
            (option_parts[current] if current else question_parts).append(prefix)
        for index, marker in enumerate(markers):
            current = marker.group(1).upper()
            finish = markers[index + 1].start() if index + 1 < len(markers) else len(text)
            value = text[marker.end():finish].strip()
            if value:
                option_parts[current].append(value)
    return "\n".join(question_parts).strip(), {label: "\n".join(option_parts[label]).strip() for label in "ABCD"}


def _linked_passage_ranges(lines, starts):
    question_starts = sorted(index for index, _ in starts)
    passages = []
    for index, line in enumerate(lines):
        match = LINKED_PASSAGE_RE.search(line["text"])
        if not match:
            continue
        end_index = next((start for start in question_starts if start > index), len(lines))
        first, last = int(match.group(1)), int(match.group(2))
        if last < first:
            first, last = last, first
        first_tail = line["text"][match.end():].lstrip(" :-")
        parts = ([first_tail] if first_tail else []) + [
            item["text"].strip() for item in lines[index + 1:end_index] if item["text"].strip()
        ]
        passages.append({
            "start_index": index,
            "end_index": end_index,
            "targets": [str(number) for number in range(first, last + 1)],
            "text": "\n".join(parts).strip(),
            "relative": "",
        })
    return passages

def _next_number(raw, state):
    raw = int(raw)
    if state["last"] >= 8 and raw <= 3:
        state["offset"] = state["maximum"]
    number = state["offset"] + raw
    state["last"] = raw
    state["maximum"] = max(state["maximum"], number)
    return str(number)


def _write_checkpoint(path, payload):
    temporary = path.with_suffix(".tmp")
    temporary.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
    temporary.replace(path)


def _extract_scanned(pdf_path, input_root, output_root, paper_code, resume=False):
    context = ExtractionContext.create(pdf_path, input_root, output_root, paper_code, "gate_pdf")
    manifest = ImageManifest(context)
    checkpoint_path = context.output_dir / "scan_checkpoint.json"
    questions, rows, source_order = [], [], 0
    seen_numbers = set()
    numbering = {"offset": 0, "last": 0, "maximum": 0}
    completed_page = 0
    if resume and checkpoint_path.exists():
        try:
            checkpoint = json.loads(checkpoint_path.read_text(encoding="utf-8"))
            if checkpoint.get("source_sha256") == context.source_sha256 and checkpoint.get("version") == 4:
                questions = checkpoint.get("questions", [])
                rows = checkpoint.get("rows", [])
                source_order = int(checkpoint.get("source_order", 0))
                seen_numbers = set(checkpoint.get("seen_numbers", []))
                numbering = checkpoint.get("numbering", numbering)
                manifest.rows = checkpoint.get("manifest_rows", [])
                completed_page = int(checkpoint.get("completed_page", 0))
        except (OSError, ValueError, json.JSONDecodeError):
            completed_page = 0

    with fitz.open(context.source_path) as doc:
        for page_index, page in enumerate(doc):
            page_no = page_index + 1
            if page_no <= completed_page:
                continue
            scale = 3.0
            pix = page.get_pixmap(matrix=fitz.Matrix(scale, scale), alpha=False)
            image = Image.frombytes("RGB", (pix.width, pix.height), pix.samples)
            lines = _ocr_lines(image)
            starts = []
            for index, line in enumerate(lines):
                match = SCAN_Q_RE.match(line["text"])
                tail = line["text"][match.end():].lstrip() if match else ""
                if match and not tail.startswith((",", "to ")) and not RANGE_RE.search(line["text"]):
                    starts.append((index, match.group(1)))
            linked_passages = _linked_passage_ranges(lines, starts)
            linked_by_target = {
                target: passage for passage in linked_passages for target in passage["targets"]
            }
            for position, (line_index, raw_number) in enumerate(starts):
                finish_index = starts[position + 1][0] if position + 1 < len(starts) else len(lines)
                passage_cutoffs = [
                    passage["start_index"] for passage in linked_passages
                    if line_index < passage["start_index"] < finish_index
                ]
                content_finish_index = min(passage_cutoffs, default=finish_index)
                segment = lines[line_index:content_finish_index]
                if not segment:
                    continue
                next_y = (
                    lines[content_finish_index]["y0"]
                    if content_finish_index < len(lines) else int(image.height * 0.94)
                )
                qno = _next_number(raw_number, numbering)
                if qno in seen_numbers:
                    continue
                seen_numbers.add(qno)
                question_text, options = _split_ocr_text(segment)
                passage = linked_by_target.get(qno)
                if passage and not passage["relative"]:
                    passage_box = (
                        int(image.width * 0.04), max(0, lines[passage["start_index"]]["y0"] - 12),
                        int(image.width * 0.96),
                        min(image.height, (
                            lines[passage["end_index"]]["y0"] - 4
                            if passage["end_index"] < len(lines) else int(image.height * 0.94)
                        )),
                    )
                    passage_crop = image.crop(passage_box)
                    passage_buffer = io.BytesIO()
                    passage_crop.save(passage_buffer, format="PNG")
                    passage_bytes = passage_buffer.getvalue()
                    passage_filename = context.image_filename(passage["targets"][0], "passage", 1, "png")
                    (context.images_dir / passage_filename).write_bytes(passage_bytes)
                    passage["relative"] = context.image_relative_path(passage_filename)
                    source_order += 1
                    manifest.add(
                        question_no=passage["targets"][0], role="passage", image_index=1,
                        filename=passage_filename, source_order=source_order, source_page=page_no,
                        bbox=tuple(round(value / scale, 2) for value in passage_box),
                        width=passage_crop.width, height=passage_crop.height,
                        image_bytes=passage_bytes,
                    )
                crop_box = (
                    int(image.width * 0.04), max(0, segment[0]["y0"] - 12),
                    int(image.width * 0.96), min(image.height, next_y - 4),
                )
                crop = image.crop(crop_box)
                buffer = io.BytesIO()
                crop.save(buffer, format="PNG")
                image_bytes = buffer.getvalue()
                filename = context.image_filename(qno, "question", 1, "png")
                (context.images_dir / filename).write_bytes(image_bytes)
                relative = context.image_relative_path(filename)
                source_order += 1
                manifest.add(
                    question_no=qno, role="question", image_index=1, filename=filename,
                    source_order=source_order, source_page=page_no,
                    bbox=tuple(round(value / scale, 2) for value in crop_box),
                    width=crop.width, height=crop.height, image_bytes=image_bytes,
                )
                marker = f"[image: {relative}]"
                question_text = f"{question_text}\n{marker}".strip()
                row = context.base_question_row()
                year = (re.search(r"(20\d{2})", context.source_path.name) or [""])[0]
                legacy_mcq = bool(year and int(year) < 2014)
                row.update({
                    "exam_name": "GATE", "exam_year": year, "paper": "AE",
                    "subject": "Aerospace Engineering", "language": "English",
                    "question_no": qno, "question_type": "MCQ" if legacy_mcq or any(options.values()) else "NAT",
                    "passage": passage["text"] if passage else "",
                    "passage_images": passage["relative"] if passage else "",
                    "question": question_text, "question_images": relative,
                    "source_pages": str(page_no),
                    "metadata_json": {"layout": "gate-scanned-page", "ocr": "tesseract"},
                })
                for option_index, label in enumerate("ABCD", 1):
                    row[f"option{option_index}"] = options[label]
                rows.append(row)
                questions.append({
                    "question_no": qno, "question_type": row["question_type"],
                    "passage": passage["text"] if passage else "",
                    "passage_images": [passage["relative"]] if passage else [],
                    "question_text": question_text, "question_images": [relative],
                    "options": {label: {"text": options[label], "images": []} for label in "ABCD"},
                    "source_pages": [page_no],
                })
            _write_checkpoint(checkpoint_path, {
                "version": 4, "source_sha256": context.source_sha256,
                "completed_page": page_no, "questions": questions, "rows": rows,
                "source_order": source_order, "seen_numbers": sorted(seen_numbers),
                "numbering": numbering, "manifest_rows": manifest.rows,
            })

        numbers = [int(question["question_no"]) for question in questions]
        missing = sorted(set(range(1, max(numbers) + 1)) - set(numbers)) if numbers else []
        warnings = ["question text is local OCR; original question crops are preserved for verification"]
        if missing:
            warnings.append("missing question numbers: " + ", ".join(map(str, missing)))
        metadata = {"layout": "gate-scanned-page", "ocr_engine": "tesseract"}
        payload = {
            "document": context.contract_metadata(), "paper_code": context.paper_code,
            "source_pdf": str(context.source_path), "layout": "gate-scanned-page",
            "page_count": doc.page_count, "question_count": len(questions),
            "metadata": metadata, "warnings": warnings, "questions": questions,
        }
        json_path = context.output_dir / "questions.json"
        csv_path = context.output_dir / "questions.csv"
        json_path.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")
        write_standard_questions_csv(rows, csv_path)
        image_manifest_path = manifest.write()
        extraction_manifest_path = write_extraction_manifest(
            context, metadata,
            {"pages": doc.page_count, "questions": len(questions), "images": len(manifest.rows)},
            warnings,
        )
    return json_path, csv_path, context.images_dir, image_manifest_path, extraction_manifest_path, len(questions)


def extract_gate_standard(pdf_path, input_root, output_root, paper_code, resume=False):
    layout = detect_gate_layout(pdf_path)
    if layout == "gate-portal-image":
        outputs = extract_jee_standard(
            pdf_path, input_root, output_root, paper_code,
            extractor_type="gate_pdf", layout_name=layout,
        )
    elif layout == "gate-scanned-page":
        outputs = _extract_scanned(pdf_path, input_root, output_root, paper_code, resume=resume)
    else:
        outputs = extract_pdf_standard(
            pdf_path, input_root, output_root, paper_code,
            header_y=35, footer_y=810, include_vector_crops=True,
            line_mode=True, renumber_resets=True, extractor_type="gate_pdf",
            max_pages=gate_content_page_limit(pdf_path),
        )
    _enrich_gate_rows(outputs[1], Path(pdf_path).name, layout)
    return outputs
