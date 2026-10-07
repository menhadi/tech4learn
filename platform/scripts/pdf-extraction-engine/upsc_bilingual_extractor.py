"""Extractor for paired Hindi/English UPSC 2025 preliminary papers."""

import argparse
import base64
import io
import json
import re
import urllib.request
from pathlib import Path

import fitz
import pytesseract
from PIL import Image
from pytesseract import Output

from ai_math import _env, _mathpix, normalize_ckeditor_html
from extraction_contract import (
    ExtractionContext,
    ImageManifest,
    image_cell,
    image_list_cell,
    write_extraction_manifest,
    write_standard_questions_csv,
)


PAPER_LAYOUTS = {
    "I": {
        "expected_questions": 100,
        "pairs": (
            (2, 3, 1, 6), (4, 5, 7, 10), (6, 7, 11, 15),
            (8, 9, 16, 20), (10, 11, 21, 26), (12, 13, 27, 30),
            (14, 15, 31, 33), (16, 17, 34, 37), (18, 19, 38, 40),
            (20, 21, 41, 46), (22, 23, 47, 50), (24, 25, 51, 55),
            (26, 27, 56, 60), (28, 29, 61, 65), (30, 31, 66, 70),
            (32, 33, 71, 76), (34, 35, 77, 80), (36, 37, 81, 86),
            (38, 39, 87, 90), (40, 41, 91, 96), (42, 43, 97, 100),
        ),
    },
    "II": {
        "expected_questions": 80,
        "pairs": (
            (2, 3, 1, 2), (4, 5, 3, 7), (6, 7, 8, 11),
            (8, 9, 12, 14), (10, 11, 15, 20), (12, 13, 21, 22),
            (14, 15, 23, 27), (16, 17, 28, 31), (18, 19, 32, 34),
            (20, 21, 35, 40), (22, 23, 41, 42), (24, 25, 43, 48),
            (26, 27, 49, 51), (28, 29, 52, 54), (30, 31, 55, 60),
            (32, 33, 61, 63), (34, 35, 64, 68), (36, 37, 69, 70),
            (38, 39, 71, 72), (40, 41, 73, 75), (42, 43, 76, 80),
        ),
    },
}

CONTENT_FIELDS = (
    "passage", "passage_hindi", "question", "question_hindi",
    "option1", "option2", "option3", "option4",
    "option1_hindi", "option2_hindi", "option3_hindi", "option4_hindi",
)
QUESTION_TOKEN_RE = re.compile(r"^[^A-Za-z0-9]{0,3}(\d{1,3})\s*[.,)]$")

VISION_PROMPT = r"""
The first image is one complete English UPSC question region and the second image
is the matching Hindi region for the same question number. Return one JSON object
with exactly these keys: passage, passage_hindi, question, question_hindi,
option1, option2, option3, option4, option1_hindi, option2_hindi,
option3_hindi, option4_hindi, visual_regions.

Transcribe only visible source content. Never solve, translate, infer, or invent.
Do not include the question number. Separate a shared directions/comprehension
passage from the question when it is visible. All four printed choices must be
placed in their corresponding option fields.

Each content value must be a CKEditor 4 HTML fragment. Use Unicode for simple,
unambiguous one-line symbols and mathematics. Use MathJax for fractions, roots
with grouped expressions, matrices, determinants, cases, integrals, summations,
differential equations, or other two-dimensional structure. Inline MathJax must
be exactly <span class="math-tex">\( ... \)</span>. Display MathJax must be in
its own paragraph as <p><span class="math-tex">\[ ... \]</span></p>. Use HTML
tables only for genuine tabular data, never for a mathematical matrix.

Do not transcribe text that belongs inside a genuine diagram, map, graph, plot,
geometric figure, apparatus, chemical structure, or pictorial option. Instead,
report a tight box for that visual. visual_regions must map each content field
to {"english": [[x0,y0,x1,y1]], "hindi": [[x0,y0,x1,y1]]}; coordinates are
normalized from 0 to 1000 relative to the corresponding supplied image. Return
empty arrays when a field has no genuine non-text visual. Exclude text, equations,
tables, option labels, borders, page headers, page footers, logos, and watermarks.
"""


def paper_variant(path):
    name = Path(path).stem
    if re.search(r"Paper\s*[- ]*II\b", name, re.IGNORECASE):
        return "II"
    if re.search(r"Paper\s*[- ]*I\b", name, re.IGNORECASE):
        return "I"
    raise ValueError("UPSC extractor supports only General Studies Paper I or Paper II")


def _render_page(page, zoom=2.0):
    pix = page.get_pixmap(matrix=fitz.Matrix(zoom, zoom), alpha=False)
    return Image.frombytes("RGB", (pix.width, pix.height), pix.samples)


def _question_anchors(image, first, last):
    data = pytesseract.image_to_data(
        image, output_type=Output.DICT, config="--psm 11"
    )
    candidates = {}
    for index, raw in enumerate(data["text"]):
        match = QUESTION_TOKEN_RE.match(str(raw).strip())
        if not match:
            continue
        number = int(match.group(1))
        if not first <= number <= last:
            continue
        x = int(data["left"][index])
        y = int(data["top"][index])
        if x <= image.width * 0.16:
            column = 0
        elif image.width * 0.43 <= x <= image.width * 0.60:
            column = 1
        else:
            continue
        current = candidates.get(number)
        if current is None or y < current["y"]:
            candidates[number] = {"column": column, "y": y, "x": x}
    return candidates


def _combined_layout(english, hindi, first, last, height):
    combined = {}
    for number in range(first, last + 1):
        candidates = [
            anchors[number] for anchors in (english, hindi) if number in anchors
        ]
        if candidates:
            combined[number] = {
                "column": round(
                    sum(item["column"] for item in candidates) / len(candidates)
                ),
                "y": round(sum(item["y"] for item in candidates) / len(candidates)),
            }

    right_numbers = sorted(
        number for number, anchor in combined.items() if anchor["column"] == 1
    )
    balanced_split = first + ((last - first + 2) // 2)
    split = min(right_numbers[0], balanced_split) if right_numbers else balanced_split
    split = max(first + 1, min(last, split))

    left_numbers = list(range(first, split))
    right_sequence = list(range(split, last + 1))
    peer_y = {}
    for sequence, peer in ((left_numbers, right_sequence), (right_sequence, left_numbers)):
        for position, number in enumerate(sequence):
            if not peer:
                continue
            peer_position = round(position * (len(peer) - 1) / max(1, len(sequence) - 1))
            peer_number = peer[peer_position]
            if peer_number in combined:
                peer_y[number] = combined[peer_number]["y"]

    for number in range(first, last + 1):
        if number in combined:
            continue
        column = 0 if number < split else 1
        same_column = sorted(
            (known, item["y"]) for known, item in combined.items()
            if item["column"] == column
        )
        lower = [(known, y) for known, y in same_column if known < number]
        upper = [(known, y) for known, y in same_column if known > number]
        if lower and upper:
            low_number, low_y = lower[-1]
            high_number, high_y = upper[0]
            fraction = (number - low_number) / (high_number - low_number)
            y = round(low_y + (high_y - low_y) * fraction)
        elif len(lower) >= 2:
            y = min(height - 100, lower[-1][1] + (lower[-1][1] - lower[-2][1]))
        elif number in peer_y:
            y = peer_y[number]
        elif lower:
            y = min(height - 100, lower[-1][1] + 260)
        elif upper:
            y = max(70, upper[0][1] - 260)
        else:
            count = split - first if column == 0 else last - split + 1
            position = number - (first if column == 0 else split)
            y = round(70 + position * ((height - 140) / max(1, count)))
        combined[number] = {"column": column, "y": y}
    return combined


def _language_layout(anchors, combined, first, last, height):
    layout = {
        number: dict(anchors.get(number, combined[number]))
        for number in range(first, last + 1)
    }
    for column in (0, 1):
        previous = 45
        for number in sorted(
            item for item in layout if layout[item]["column"] == column
        ):
            layout[number]["y"] = max(
                previous, min(height - 60, layout[number]["y"])
            )
            previous = layout[number]["y"] + 25
    return layout


def _column_split(image, anchors):
    right_x = [
        item["x"] for item in anchors.values()
        if item["column"] == 1 and "x" in item
    ]
    if right_x:
        return max(round(image.width * 0.40), min(right_x) - 18)
    return round(image.width * 0.46)


def _crop_questions(image, layout, split_x=None):
    crops = {}
    midpoint = split_x or image.width // 2
    for column in (0, 1):
        numbers = sorted(
            number for number, anchor in layout.items() if anchor["column"] == column
        )
        for position, number in enumerate(numbers):
            anchor_y = layout[number]["y"]
            top = 35 if position == 0 else max(35, anchor_y - 18)
            bottom = (
                max(top + 80, layout[numbers[position + 1]]["y"] - 12)
                if position + 1 < len(numbers)
                else image.height - 45
            )
            left = 10 if column == 0 else midpoint + 5
            right = midpoint - 5 if column == 0 else image.width - 10
            crops[number] = {
                "image": image.crop((left, top, right, bottom)),
                "pixel_box": (left, top, right, bottom),
            }
    return crops


def _image_bytes(image):
    buffer = io.BytesIO()
    image.save(buffer, format="PNG")
    return buffer.getvalue()


def _response_text(result):
    if result.get("output_text"):
        return result["output_text"]
    for item in result.get("output", []):
        for part in item.get("content", []):
            if part.get("type") == "output_text":
                return part.get("text", "")
    return ""


def _openai_transcribe(english_bytes, hindi_bytes, timeout, ocr_reference=""):
    key = _env("OPENAI_API_KEY")
    if not key:
        raise RuntimeError("OPENAI_API_KEY is required for UPSC bilingual transcription")
    prompt = VISION_PROMPT
    if ocr_reference:
        prompt += "\nMathpix English OCR reference; use only to resolve symbols:\n" + ocr_reference
    content = [{"type": "input_text", "text": prompt}]
    for image_bytes in (english_bytes, hindi_bytes):
        encoded = base64.b64encode(image_bytes).decode("ascii")
        content.append({
            "type": "input_image",
            "image_url": "data:image/png;base64," + encoded,
            "detail": "high",
        })
    payload = {
        "model": _env("OPENAI_VISION_MODEL", "gpt-5-mini"),
        "input": [{"role": "user", "content": content}],
        "text": {"format": {"type": "json_object"}},
        "reasoning": {"effort": "minimal"},
        "max_output_tokens": 6000,
    }
    request = urllib.request.Request(
        "https://api.openai.com/v1/responses",
        data=json.dumps(payload).encode("utf-8"),
        headers={"Authorization": f"Bearer {key}", "Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(request, timeout=timeout) as response:
        result = json.loads(response.read().decode("utf-8"))
    text = _response_text(result).strip()
    if text.startswith("```"):
        text = re.sub(r"^```(?:json)?\s*|\s*```$", "", text, flags=re.IGNORECASE)
    return json.loads(text), result.get("usage", {})


def _normalize_transcription(candidate):
    normalized = {}
    for field in CONTENT_FIELDS:
        normalized[field] = normalize_ckeditor_html(
            candidate.get(field, ""),
            strip_number=field in {"question", "question_hindi"},
        )
    for field in ("question", "question_hindi"):
        if not normalized[field]:
            raise ValueError(f"vision response omitted {field}")
    for suffix in ("", "_hindi"):
        missing = [
            f"option{index}{suffix}" for index in range(1, 5)
            if not normalized[f"option{index}{suffix}"]
        ]
        if missing:
            raise ValueError(
                "vision response omitted printed options: " + ", ".join(missing)
            )
    normalized["visual_regions"] = candidate.get("visual_regions", {})
    return normalized


def _transcribe_pair(english_bytes, hindi_bytes, timeout):
    try:
        candidate, usage = _openai_transcribe(english_bytes, hindi_bytes, timeout)
        return _normalize_transcription(candidate), {
            "provider": "openai_vision", "usage": usage
        }
    except Exception as first_error:
        if not (_env("MATHPIX_APP_ID") and _env("MATHPIX_APP_KEY")):
            raise
        reference, mathpix_usage = _mathpix([english_bytes], timeout)
        candidate, usage = _openai_transcribe(
            english_bytes, hindi_bytes, timeout, ocr_reference=reference
        )
        return _normalize_transcription(candidate), {
            "provider": "openai_vision_mathpix_reference",
            "usage": usage,
            "mathpix_usage": mathpix_usage,
            "first_error": str(first_error)[:300],
        }


def _visible_text(value):
    text = re.sub(r"<[^>]+>", " ", str(value or ""))
    text = re.sub(r"\s+", " ", text).strip()
    return text


def _field_allows_visual(field, value):
    html = str(value or "")
    if "<table" in html.lower():
        return False
    text = _visible_text(html)
    cues = re.compile(
        r"\b(?:figure|diagram|graph|chart|map|image|plot|apparatus|circuit|"
        r"waveform|structure|shown|depicted|illustrated)\b|"
        r"चित्र|आरेख|ग्राफ|मानचित्र|संरचना|परिपथ",
        re.IGNORECASE,
    )
    if cues.search(text):
        return True
    if field.startswith("option"):
        text = re.sub(r"^\s*\(?[a-d1-4]\)?[.):\s-]*", "", text, flags=re.IGNORECASE)
        return not text
    return not text


def _valid_boxes(value):
    if not isinstance(value, list):
        return []
    boxes = []
    for box in value:
        if not isinstance(box, list) or len(box) != 4:
            continue
        try:
            x0, y0, x1, y1 = [
                max(0, min(1000, float(item))) for item in box
            ]
        except (TypeError, ValueError):
            continue
        if x1 - x0 >= 8 and y1 - y0 >= 8:
            boxes.append((x0, y0, x1, y1))
    return boxes


def _save_visuals(
    context, manifest, row, question_no, candidate, source_crops,
    source_pages, source_boxes, zoom, source_order,
):
    regions = candidate.pop("visual_regions", {})
    for field in CONTENT_FIELDS:
        field_regions = regions.get(field, {}) if isinstance(regions, dict) else {}
        language = "hindi" if field.endswith("_hindi") else "english"
        boxes = _valid_boxes(
            field_regions.get(language, [])
            if isinstance(field_regions, dict) else []
        ) if _field_allows_visual(field, candidate[field]) else []
        relative_paths = []
        for image_index, normalized_box in enumerate(boxes, start=1):
            source = source_crops[language]
            x0, y0, x1, y1 = normalized_box
            pixel_box = (
                round(source.width * x0 / 1000),
                round(source.height * y0 / 1000),
                round(source.width * x1 / 1000),
                round(source.height * y1 / 1000),
            )
            visual = source.crop(pixel_box)
            image_bytes = _image_bytes(visual)
            filename = context.image_filename(
                question_no, field, image_index, "png"
            )
            (context.images_dir / filename).write_bytes(image_bytes)
            relative = context.image_relative_path(filename)
            relative_paths.append(relative)
            source_order += 1
            source_crop_box = source_boxes[language]
            pdf_box = (
                (source_crop_box[0] + pixel_box[0]) / zoom,
                (source_crop_box[1] + pixel_box[1]) / zoom,
                (source_crop_box[0] + pixel_box[2]) / zoom,
                (source_crop_box[1] + pixel_box[3]) / zoom,
            )
            manifest.add(
                question_no=question_no, role=field, image_index=image_index,
                filename=filename, source_order=source_order,
                source_page=source_pages[language], bbox=pdf_box,
                width=visual.width, height=visual.height,
                image_bytes=image_bytes,
                language="hi" if language == "hindi" else "en",
            )
        row[field] = (candidate[field] + image_cell(relative_paths)).strip()
        row[f"{field}_images"] = image_list_cell(relative_paths)
    return source_order


def extract_upsc_bilingual(
    pdf_path, input_root, output_root, paper_code, *,
    transcribe=True, timeout=120, resume=False,
):
    variant = paper_variant(pdf_path)
    layout = PAPER_LAYOUTS[variant]
    context = ExtractionContext.create(
        pdf_path, input_root, output_root, paper_code, "upsc_bilingual"
    )
    manifest = ImageManifest(context)
    rows, questions, source_order, calls = [], [], 0, []
    checkpoint_path = context.output_dir / "upsc_checkpoint.json"
    completed_question = 0
    if resume and checkpoint_path.exists():
        try:
            checkpoint = json.loads(checkpoint_path.read_text(encoding="utf-8"))
            if (
                checkpoint.get("source_sha256") == context.source_sha256
                and checkpoint.get("version") == 1
                and checkpoint.get("transcribe") == transcribe
            ):
                rows = checkpoint.get("rows", [])
                questions = checkpoint.get("questions", [])
                manifest.rows = checkpoint.get("manifest_rows", [])
                source_order = int(checkpoint.get("source_order", 0))
                calls = checkpoint.get("calls", [])
                completed_question = int(
                    checkpoint.get("completed_question", 0)
                )
        except (OSError, ValueError, json.JSONDecodeError):
            completed_question = 0

    zoom = 2.0
    with fitz.open(context.source_path) as doc:
        if doc.page_count != 48:
            raise ValueError(f"expected 48 UPSC pages, found {doc.page_count}")
        for hindi_page, english_page, first, last in layout["pairs"]:
            if last <= completed_question:
                continue
            hindi_image = _render_page(doc[hindi_page - 1], zoom)
            english_image = _render_page(doc[english_page - 1], zoom)
            hindi_anchors = _question_anchors(hindi_image, first, last)
            english_anchors = _question_anchors(english_image, first, last)
            combined = _combined_layout(
                english_anchors, hindi_anchors, first, last, english_image.height
            )
            english_layout = _language_layout(
                english_anchors, combined, first, last, english_image.height
            )
            hindi_layout = _language_layout(
                hindi_anchors, combined, first, last, hindi_image.height
            )
            english_crops = _crop_questions(
                english_image, english_layout,
                _column_split(english_image, english_anchors),
            )
            hindi_crops = _crop_questions(
                hindi_image, hindi_layout,
                _column_split(hindi_image, hindi_anchors),
            )
            for number in range(first, last + 1):
                if number <= completed_question:
                    continue
                english_crop = english_crops[number]
                hindi_crop = hindi_crops[number]
                english_bytes = _image_bytes(english_crop["image"])
                hindi_bytes = _image_bytes(hindi_crop["image"])
                row = context.base_question_row()
                row.update({
                    "exam_name": "UPSC Civil Services (Preliminary) Examination",
                    "exam_year": "2025",
                    "paper": f"General Studies Paper {variant}",
                    "subject": f"General Studies Paper {variant}",
                    "language": "English;Hindi",
                    "section": "General Studies" if variant == "I" else "CSAT",
                    "question_no": str(number),
                    "question_type": "MCQ",
                    "source_pages": f"{english_page};{hindi_page}",
                    "metadata_json": {
                        "layout": "upsc-2025-paired-bilingual-scan",
                        "english_page": english_page,
                        "hindi_page": hindi_page,
                        "ocr_usage": "question-boundary detection only",
                    },
                })
                if transcribe:
                    candidate, call = _transcribe_pair(
                        english_bytes, hindi_bytes, timeout
                    )
                    call["question_no"] = str(number)
                    calls.append(call)
                    source_order = _save_visuals(
                        context, manifest, row, str(number), candidate,
                        {
                            "english": english_crop["image"],
                            "hindi": hindi_crop["image"],
                        },
                        {"english": english_page, "hindi": hindi_page},
                        {
                            "english": english_crop["pixel_box"],
                            "hindi": hindi_crop["pixel_box"],
                        },
                        zoom, source_order,
                    )
                else:
                    for language, crop, page_no, role, image_bytes in (
                        ("en", english_crop, english_page, "question", english_bytes),
                        ("hi", hindi_crop, hindi_page, "question_hindi", hindi_bytes),
                    ):
                        filename = context.image_filename(number, role, 1, "png")
                        (context.images_dir / filename).write_bytes(image_bytes)
                        relative = context.image_relative_path(filename)
                        source_order += 1
                        manifest.add(
                            question_no=number, role=role, image_index=1,
                            filename=filename, source_order=source_order,
                            source_page=page_no,
                            bbox=tuple(
                                value / zoom for value in crop["pixel_box"]
                            ),
                            width=crop["image"].width,
                            height=crop["image"].height,
                            image_bytes=image_bytes, language=language,
                        )
                        row[role] = image_cell([relative])
                        row[f"{role}_images"] = relative
                rows.append(row)
                questions.append({
                    "question_no": str(number),
                    **{field: row.get(field, "") for field in CONTENT_FIELDS},
                    "source_pages": [english_page, hindi_page],
                })
                completed_question = number
                temporary = checkpoint_path.with_suffix(".tmp")
                temporary.write_text(json.dumps({
                    "version": 1,
                    "source_sha256": context.source_sha256,
                    "transcribe": transcribe,
                    "completed_question": completed_question,
                    "rows": rows,
                    "questions": questions,
                    "manifest_rows": manifest.rows,
                    "source_order": source_order,
                    "calls": calls,
                }, ensure_ascii=False), encoding="utf-8")
                temporary.replace(checkpoint_path)

        expected = layout["expected_questions"]
        if len(rows) != expected:
            raise ValueError(
                f"expected {expected} questions, extracted {len(rows)}"
            )
        metadata = {
            "layout": "upsc-2025-paired-bilingual-scan",
            "paper_variant": variant,
            "page_pairs": len(layout["pairs"]),
            "transcription": (
                "openai-vision-with-mathpix-reference-fallback"
                if transcribe else "disabled"
            ),
            "api_calls": calls,
        }
        warnings = [] if transcribe else [
            "transcription disabled; complete question crops are preserved as images"
        ]
        payload = {
            "document": context.contract_metadata(),
            "paper_code": context.paper_code,
            "source_pdf": str(context.source_path),
            "layout": metadata["layout"],
            "page_count": doc.page_count,
            "question_count": len(questions),
            "metadata": metadata,
            "warnings": warnings,
            "questions": questions,
        }
        json_path = context.output_dir / "questions.json"
        csv_path = context.output_dir / "questions.csv"
        json_path.write_text(
            json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8"
        )
        write_standard_questions_csv(rows, csv_path)
        image_manifest_path = manifest.write()
        extraction_manifest_path = write_extraction_manifest(
            context, metadata,
            {
                "pages": doc.page_count,
                "questions": len(rows),
                "images": len(manifest.rows),
            },
            warnings,
        )
    if checkpoint_path.exists():
        checkpoint_path.unlink()
    return (
        json_path, csv_path, context.images_dir, image_manifest_path,
        extraction_manifest_path, len(rows),
    )


def main():
    parser = argparse.ArgumentParser(
        description="Extract paired Hindi/English UPSC 2025 preliminary papers."
    )
    parser.add_argument("--pdf", required=True)
    parser.add_argument("--input-root", required=True)
    parser.add_argument("--output-root", required=True)
    parser.add_argument("--paper-code")
    parser.add_argument("--no-transcribe", action="store_true")
    parser.add_argument("--resume", action="store_true")
    parser.add_argument("--api-timeout", type=int, default=120)
    args = parser.parse_args()
    paper_code = args.paper_code or Path(args.pdf).stem
    outputs = extract_upsc_bilingual(
        args.pdf, args.input_root, args.output_root, paper_code,
        transcribe=not args.no_transcribe,
        timeout=args.api_timeout,
        resume=args.resume,
    )
    print(f"Done. Extracted {outputs[-1]} questions.")
    print(f"CSV: {outputs[1]}")
    print(f"Images: {outputs[2]}")


if __name__ == "__main__":
    main()
