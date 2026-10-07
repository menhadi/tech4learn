"""Shared ExamElite contract helpers for the Claude PDF extractors."""

from __future__ import annotations

import html
import json
import math
import re
from pathlib import Path
from typing import Any, Callable, Dict, List, Optional, Tuple


OPTION_FIELDS = ("option_a", "option_b", "option_c", "option_d", "option_e")
CONTENT_FIELDS = ("question_text",) + OPTION_FIELDS


def safe_name(value: str) -> str:
    cleaned = re.sub(r"[^A-Za-z0-9._-]+", "_", str(value)).strip("._")
    return cleaned or "unknown"


def normalize_section(value: str) -> str:
    return re.sub(r"[^a-z0-9]+", "", str(value).lower())


def normalize_number(value: str) -> str:
    return re.sub(r"\s+", "", str(value).lower()).rstrip(".")


def _integer(value: Any, default: int) -> int:
    try:
        return int(value)
    except (TypeError, ValueError):
        return default


def visual_order_key(item: Dict[str, Any]) -> Tuple[int, int, int, int]:
    pages = item.get("source_pages") or []
    first_page = min(pages) if pages else 999999
    return (
        _integer(item.get("source_page_start"), first_page),
        _integer(item.get("order_on_page"), 999999),
        _integer(item.get("_batch_number"), 999999),
        _integer(item.get("_extraction_index"), 999999),
    )


def _merge_text(existing: Any, incoming: Any) -> str:
    left = str(existing or "").strip()
    right = str(incoming or "").strip()
    if not left:
        return right
    if not right:
        return left
    compact_left = re.sub(r"\s+", " ", left)
    compact_right = re.sub(r"\s+", " ", right)
    if compact_left == compact_right:
        return left if len(left) >= len(right) else right
    if compact_left in compact_right:
        return right
    if compact_right in compact_left:
        return left

    maximum = min(len(left), len(right), 300)
    for size in range(maximum, 19, -1):
        if left[-size:].casefold() == right[:size].casefold():
            return left + right[size:]
    return left + "\n" + right


def _prefer_complete_text(existing: Any, incoming: Any) -> str:
    """Choose the richer duplicate transcription without concatenating it."""
    left = str(existing or "").strip()
    right = str(incoming or "").strip()
    if not left:
        return right
    if not right:
        return left
    marker = re.compile(r"\[CONTINUES (?:FROM PREVIOUS|ON NEXT) PAGE\]", re.I)
    left_score = (0 if marker.search(left) else 1, len(left))
    right_score = (0 if marker.search(right) else 1, len(right))
    return right if right_score > left_score else left


def _merge_regions(existing: Any, incoming: Any) -> List[Dict[str, Any]]:
    output = []
    seen = set()
    for region in list(existing or []) + list(incoming or []):
        if not isinstance(region, dict):
            continue
        identity = json.dumps(region, sort_keys=True, ensure_ascii=False)
        if identity not in seen:
            seen.add(identity)
            output.append(region)
    return output


def merge_question_fragments(
    questions: List[Dict[str, Any]],
) -> List[Dict[str, Any]]:
    """Merge repeated adjacent-page fragments by section and printed number."""
    output: List[Dict[str, Any]] = []
    by_identity: Dict[Tuple[str, str], Dict[str, Any]] = {}

    for question in sorted(questions, key=visual_order_key):
        number = normalize_number(question.get("number", ""))
        section = normalize_section(question.get("section", ""))
        if not number:
            output.append(dict(question))
            continue
        identity = (section, number)
        current = by_identity.get(identity)
        if current is None:
            current = dict(question)
            current["source_pages"] = sorted(set(question.get("source_pages") or []))
            current["image_regions"] = _merge_regions([], question.get("image_regions"))
            by_identity[identity] = current
            output.append(current)
            continue

        current_pages = set(current.get("source_pages") or [])
        incoming_pages = set(question.get("source_pages") or [])
        overlapping_duplicate = bool(current_pages & incoming_pages)
        for field in CONTENT_FIELDS:
            if overlapping_duplicate:
                current[field] = _prefer_complete_text(
                    current.get(field), question.get(field)
                )
            else:
                current[field] = _merge_text(
                    current.get(field), question.get(field)
                )
        current["diagram_description"] = _merge_text(
            current.get("diagram_description"), question.get("diagram_description")
        )
        current["diagram_required"] = bool(
            current.get("diagram_required") or question.get("diagram_required")
        )
        current["contains_html_table"] = bool(
            current.get("contains_html_table")
            or question.get("contains_html_table")
        )
        if current.get("marks") in ("", None) and question.get("marks") not in ("", None):
            current["marks"] = question.get("marks")
        if str(current.get("question_type", "")).lower() in ("", "unknown"):
            current["question_type"] = question.get("question_type", "unknown")
        current["source_pages"] = sorted(
            set(current.get("source_pages") or [])
            | set(question.get("source_pages") or [])
        )
        current["source_page_start"] = min(
            _integer(current.get("source_page_start"), 999999),
            _integer(question.get("source_page_start"), 999999),
        )
        current["order_on_page"] = min(
            _integer(current.get("order_on_page"), 999999),
            _integer(question.get("order_on_page"), 999999),
        )
        current["image_regions"] = _merge_regions(
            current.get("image_regions"), question.get("image_regions")
        )

    return output


def deduplicate_answers(
    answers: List[Dict[str, Any]],
) -> List[Dict[str, Any]]:
    output = []
    by_identity: Dict[Tuple[str, str], Dict[str, Any]] = {}
    for answer in sorted(
        answers,
        key=lambda item: (
            _integer(item.get("source_page"), 999999),
            _integer(item.get("order_on_page"), 999999),
        ),
    ):
        identity = (
            normalize_section(answer.get("section", "")),
            normalize_number(answer.get("question_number", "")),
        )
        if not identity[1]:
            raise RuntimeError("Answer key contains an entry without a question number.")
        current = by_identity.get(identity)
        if current is None:
            current = dict(answer)
            by_identity[identity] = current
            output.append(current)
            continue
        first = str(current.get("correct_option", "")).strip().upper()
        second = str(answer.get("correct_option", "")).strip().upper()
        if first and second and first != second:
            raise RuntimeError(
                "Conflicting answer-key values for "
                f"{identity[0] or 'unsectioned'} question {identity[1]}: "
                f"{first} and {second}."
            )
        if not first:
            current["correct_option"] = answer.get("correct_option", "")
    return output


def checkpoint_and_attach_answers(
    questions: List[Dict[str, Any]],
    answers: List[Dict[str, Any]],
) -> List[Dict[str, Any]]:
    """Require an exact one-to-one section/number match before import."""
    if not questions:
        raise RuntimeError("No questions were extracted.")
    if not answers:
        raise RuntimeError(
            "No answer-key entries were extracted; question import was stopped."
        )

    question_map: Dict[Tuple[str, str], Dict[str, Any]] = {}
    for question in questions:
        identity = (
            normalize_section(question.get("section", "")),
            normalize_number(question.get("number", "")),
        )
        if not identity[1]:
            raise RuntimeError(
                "A question fragment has no printed question number; import was stopped."
            )
        if identity in question_map:
            raise RuntimeError(
                "Duplicate question remains after page-fragment merging: "
                f"{identity[0] or 'unsectioned'} question {identity[1]}."
            )
        question_map[identity] = question

    clean_answers = deduplicate_answers(answers)
    answer_map = {
        (
            normalize_section(answer.get("section", "")),
            normalize_number(answer.get("question_number", "")),
        ): answer
        for answer in clean_answers
    }
    missing_answers = sorted(set(question_map) - set(answer_map))
    missing_questions = sorted(set(answer_map) - set(question_map))
    if missing_answers or missing_questions:
        def labels(values):
            return [
                f"{section or 'unsectioned'}:{number}"
                for section, number in values[:20]
            ]

        raise RuntimeError(
            "Question/answer checkpoint failed. "
            f"Questions={len(question_map)}, answers={len(answer_map)}, "
            f"questions without answers={labels(missing_answers)}, "
            f"answers without questions={labels(missing_questions)}. "
            "No drafts were created."
        )

    for identity, question in question_map.items():
        question["correct_option"] = str(
            answer_map[identity].get("correct_option", "")
        ).strip()
    return clean_answers


def normalized_bbox(value: Any) -> Optional[Tuple[float, float, float, float]]:
    if not isinstance(value, (list, tuple)) or len(value) != 4:
        return None
    try:
        coordinates = [float(item) for item in value]
    except (TypeError, ValueError):
        return None
    if max(coordinates) > 1.0 and max(coordinates) <= 1000.0:
        coordinates = [item / 1000.0 for item in coordinates]
    x1, y1, x2, y2 = [max(0.0, min(1.0, item)) for item in coordinates]
    if x2 - x1 < 0.01 or y2 - y1 < 0.01:
        return None
    return x1, y1, x2, y2


def extract_question_images(
    pdf_path: Path,
    questions: List[Dict[str, Any]],
    image_folder: Path,
    public_prefix: str,
    poppler_path: Optional[str],
    render_pdf: Callable[..., List[Any]],
) -> None:
    requested = any(question.get("image_regions") for question in questions)
    required = any(question.get("diagram_required") for question in questions)
    if not requested and not required:
        return
    if required and not requested:
        raise RuntimeError(
            "Claude reported required diagrams but returned no image regions."
        )

    page_images = render_pdf(pdf_path, poppler_path)
    image_folder.mkdir(parents=True, exist_ok=True)
    target_map = {
        "question": ("question_text", "question"),
        "option_a": ("option_a", "option1"),
        "option_b": ("option_b", "option2"),
        "option_c": ("option_c", "option3"),
        "option_d": ("option_d", "option4"),
        "option_e": ("option_e", "option5"),
        "option1": ("option_a", "option1"),
        "option2": ("option_b", "option2"),
        "option3": ("option_c", "option3"),
        "option4": ("option_d", "option4"),
        "option5": ("option_e", "option5"),
    }
    url_root = public_prefix.rstrip("/")

    for ordinal, question in enumerate(questions, start=1):
        section = safe_name(question.get("section") or "section")
        printed = safe_name(question.get("number") or ordinal)
        counts: Dict[str, int] = {}
        extracted = []
        invalid_regions = 0
        for region in question.get("image_regions") or []:
            if not isinstance(region, dict):
                invalid_regions += 1
                continue
            target = str(region.get("target", "question")).strip().lower()
            mapping = target_map.get(target)
            bbox = normalized_bbox(region.get("bbox_normalized"))
            try:
                page_number = int(region.get("page"))
            except (TypeError, ValueError):
                page_number = 0
            if (
                not mapping
                or not bbox
                or page_number < 1
                or page_number > len(page_images)
            ):
                invalid_regions += 1
                continue

            field, filename_target = mapping
            source = page_images[page_number - 1].convert("RGB")
            width, height = source.size
            x1, y1, x2, y2 = bbox
            padding = max(8, int(min(width, height) * 0.004))
            pixel_box = (
                max(0, int(x1 * width) - padding),
                max(0, int(y1 * height) - padding),
                min(width, int(math.ceil(x2 * width)) + padding),
                min(height, int(math.ceil(y2 * height)) + padding),
            )
            if pixel_box[2] - pixel_box[0] < 20 or pixel_box[3] - pixel_box[1] < 20:
                invalid_regions += 1
                continue

            counts[filename_target] = counts.get(filename_target, 0) + 1
            filename = (
                f"q_{section}_{printed}_{filename_target}_image_"
                f"{counts[filename_target]}.png"
            )
            source.crop(pixel_box).save(
                image_folder / filename, "PNG", optimize=True
            )
            image_url = f"{url_root}/{filename}" if url_root else filename
            description = str(region.get("description") or "Source image").strip()
            alt = html.escape(
                re.sub(r"[\"<>]", "", description)[:180] or "Source image",
                quote=True,
            )
            image_html = (
                f'<p><img src="{html.escape(image_url, quote=True)}" '
                f'alt="{alt}" class="img-fluid" loading="lazy"></p>'
            )
            question[field] = (
                str(question.get(field) or "") + image_html
            ).strip()
            extracted.append(
                {
                    "target": filename_target,
                    "page": page_number,
                    "bbox_normalized": list(bbox),
                    "filename": filename,
                    "url": image_url,
                }
            )
        if invalid_regions:
            raise RuntimeError(
                f"Question {question.get('number') or ordinal} contains "
                f"{invalid_regions} invalid image region(s)."
            )
        if question.get("diagram_required") and not extracted:
            raise RuntimeError(
                f"Question {question.get('number') or ordinal} requires an image, "
                "but no valid crop was produced."
            )
        question["extracted_images"] = extracted


def application_question_rows(
    questions: List[Dict[str, Any]],
) -> List[Dict[str, Any]]:
    rows = []
    for ordinal, question in enumerate(questions, start=1):
        rows.append(
            {
                "paper_question_number": ordinal,
                "printed_question_number": str(
                    question.get("number") or ordinal
                ).strip(),
                "question": question.get("question_text", ""),
                "options": [
                    question.get(field, "") for field in OPTION_FIELDS
                ],
                "question_type": question.get("question_type"),
                "correct_answer": question.get("correct_option") or None,
                "marks": question.get("marks"),
                "explanation": None,
                "source_pages": question.get("source_pages", []),
                "diagram_required": bool(
                    question.get("diagram_required", False)
                ),
                "diagram_description": question.get(
                    "diagram_description", ""
                ),
                "extracted_images": question.get("extracted_images", []),
                "section": question.get("section", ""),
            }
        )
    return rows
