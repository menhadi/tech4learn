import os
import sys
import argparse
import io

for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8", errors="replace")
    except (AttributeError, ValueError):
        pass
import re
import csv
import json
import time
import math
import base64
import subprocess
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

# ============================================================
# Install dependencies
# ============================================================

REQUIRED_PACKAGES = {
    "anthropic": "anthropic",
    "pdf2image": "pdf2image",
    "PIL": "pillow",
    "numpy": "numpy",
}


def ensure_dependencies() -> None:
    for import_name, package_name in REQUIRED_PACKAGES.items():
        try:
            __import__(import_name)
        except ImportError:
            print(f"📦 Installing {package_name}...")
            subprocess.check_call(
                [sys.executable, "-m", "pip", "install", "--upgrade", package_name]
            )


ensure_dependencies()

import numpy as np
from PIL import Image, ImageFilter, ImageOps
from pdf2image import convert_from_path
from anthropic import Anthropic
from anthropic.types.message_create_params import MessageCreateParamsNonStreaming
from anthropic.types.messages.batch_create_params import Request

SUPPORT_DIR = Path(__file__).resolve().parent / "claude_support"
sys.path.insert(0, str(SUPPORT_DIR))
from examelite_contract import (
    application_question_rows,
    checkpoint_and_attach_answers,
    extract_question_images,
    merge_question_fragments,
)

# ============================================================
# Configuration
# ============================================================

DEFAULT_INPUT_FOLDER = str(Path.home() / "Downloads")
DEFAULT_OUTPUT_FOLDER = "anthropic_extracted_ordered"

PDF_DPI = int(os.getenv("PDF_DPI", "180"))
MAX_IMAGE_TOKENS_PER_PAGE = int(os.getenv("MAX_IMAGE_TOKENS_PER_PAGE", "1500"))
SPARSE_PAGE_TOKENS = int(os.getenv("SPARSE_PAGE_TOKENS", "1300"))
JPEG_QUALITY = int(os.getenv("JPEG_QUALITY", "88"))

# Two pages is safer for sequence, JSON completeness, and continuation handling.
# Dense or two-column pages are sent alone automatically.
NORMAL_PAGES_PER_REQUEST = int(os.getenv("NORMAL_PAGES_PER_REQUEST", "2"))
MAX_OUTPUT_TOKENS = int(os.getenv("MAX_OUTPUT_TOKENS", "16000"))

RECURSIVE = os.getenv("RECURSIVE", "0") == "1"
SKIP_EXISTING = os.getenv("SKIP_EXISTING", "1") == "1"
SAVE_PREVIEWS = os.getenv("SAVE_PREVIEWS", "1") == "1"
PREVIEW_MAX_PAGES = int(os.getenv("PREVIEW_MAX_PAGES", "8"))

AUTO_CONTRAST = os.getenv("AUTO_CONTRAST", "1") == "1"
LIGHT_SHARPEN = os.getenv("LIGHT_SHARPEN", "1") == "1"
WHITE_THRESHOLD = int(os.getenv("WHITE_THRESHOLD", "245"))
CROP_SAFETY_PADDING = int(os.getenv("CROP_SAFETY_PADDING", "24"))
MAX_LEFT_CROP_FRACTION = float(os.getenv("MAX_LEFT_CROP_FRACTION", "0.08"))
MAX_RIGHT_CROP_FRACTION = float(os.getenv("MAX_RIGHT_CROP_FRACTION", "0.08"))
MAX_TOP_CROP_FRACTION = float(os.getenv("MAX_TOP_CROP_FRACTION", "0.05"))
MAX_BOTTOM_CROP_FRACTION = float(os.getenv("MAX_BOTTOM_CROP_FRACTION", "0.05"))

SKIP_NEAR_BLANK_PAGES = os.getenv("SKIP_NEAR_BLANK_PAGES", "1") == "1"
BLANK_PAGE_INK_RATIO = float(os.getenv("BLANK_PAGE_INK_RATIO", "0.00025"))
DENSE_PAGE_INK_RATIO = float(os.getenv("DENSE_PAGE_INK_RATIO", "0.065"))

POLL_SECONDS = int(os.getenv("BATCH_POLL_SECONDS", "30"))
MAX_BATCH_PAYLOAD_MB = int(os.getenv("MAX_BATCH_PAYLOAD_MB", "220"))

ANTHROPIC_API_KEY = os.getenv("ANTHROPIC_API_KEY", "").strip()
CLAUDE_MODEL = (os.getenv("CLAUDE_MODEL") or os.getenv("ANTHROPIC_MODEL") or "").strip()

# Batch pricing can be overridden when Anthropic pricing changes.
BATCH_INPUT_PRICE_PER_MTOK = float(os.getenv("BATCH_INPUT_PRICE_PER_MTOK", "1.0"))
BATCH_OUTPUT_PRICE_PER_MTOK = float(os.getenv("BATCH_OUTPUT_PRICE_PER_MTOK", "5.0"))

# ============================================================
# Structured output schema
# ============================================================

OUTPUT_SCHEMA: Dict[str, Any] = {
    "type": "object",
    "additionalProperties": False,
    "properties": {
        "questions": {
            "type": "array",
            "items": {
                "type": "object",
                "additionalProperties": False,
                "properties": {
                    "section": {"type": "string"},
                    "number": {"type": "string"},
                    "question_type": {
                        "type": "string",
                        "enum": ["MCQ", "MSQ", "NAT", "descriptive", "matching", "unknown"],
                    },
                    "question_text": {"type": "string"},
                    "marks": {"type": ["number", "null"]},
                    "option_a": {"type": "string"},
                    "option_b": {"type": "string"},
                    "option_c": {"type": "string"},
                    "option_d": {"type": "string"},
                    "option_e": {"type": "string"},
                    "diagram_required": {"type": "boolean"},
                    "diagram_description": {"type": "string"},
                    "contains_html_table": {"type": "boolean"},
                    "image_regions": {
                        "type": "array",
                        "items": {
                            "type": "object",
                            "additionalProperties": False,
                            "properties": {
                                "target": {
                                    "type": "string",
                                    "enum": [
                                        "question", "option_a", "option_b",
                                        "option_c", "option_d", "option_e",
                                    ],
                                },
                                "page": {"type": "integer"},
                                "bbox_normalized": {
                                    "type": "array",
                                    "items": {"type": "number"},
                                    "minItems": 4,
                                    "maxItems": 4,
                                },
                                "description": {"type": "string"},
                            },
                            "required": [
                                "target", "page", "bbox_normalized", "description",
                            ],
                        },
                    },
                    "source_pages": {
                        "type": "array",
                        "items": {"type": "integer"},
                    },
                    "source_page_start": {"type": "integer"},
                    "order_on_page": {"type": "integer"},
                },
                "required": [
                    "section", "number", "question_type", "question_text", "marks",
                    "option_a", "option_b", "option_c", "option_d", "option_e",
                    "diagram_required", "diagram_description", "contains_html_table",
                    "image_regions", "source_pages", "source_page_start", "order_on_page",
                ],
            },
        },
        "instructions": {
            "type": "array",
            "items": {
                "type": "object",
                "additionalProperties": False,
                "properties": {
                    "title": {"type": "string"},
                    "instruction_text": {"type": "string"},
                    "source_page": {"type": "integer"},
                    "order_on_page": {"type": "integer"},
                },
                "required": ["title", "instruction_text", "source_page", "order_on_page"],
            },
        },
        "answer_keys": {
            "type": "array",
            "items": {
                "type": "object",
                "additionalProperties": False,
                "properties": {
                    "section": {"type": "string"},
                    "question_number": {"type": "string"},
                    "correct_option": {"type": "string"},
                    "source_page": {"type": "integer"},
                    "order_on_page": {"type": "integer"},
                },
                "required": [
                    "section", "question_number", "correct_option",
                    "source_page", "order_on_page",
                ],
            },
        },
    },
    "required": ["questions", "instructions", "answer_keys"],
}

# ============================================================
# Extraction prompt
# ============================================================

SYSTEM_PROMPT = r"""
You are a high-precision exam-paper transcription engine.

Extract ALL relevant content from the supplied PDF page images:
1. exam questions;
2. instruction pages or instruction blocks;
3. answer-key pages, including subject headings and every question-answer pair.

Never solve questions. Never infer missing answers. Never paraphrase. Never omit a
page because it is not a question page.

READING ORDER — CRITICAL

For each page, preserve the exact visual reading sequence of the PDF.

First divide the page into horizontal reading bands. A full-width title, subject
heading, rule, or section divider starts a new band. Within each band:

Single-column band:
- read from top to bottom.

Two-column band:
- read the entire LEFT column from top to bottom;
- then read the entire RIGHT column from top to bottom;
- do not alternate between columns row by row.

This is critical for mixed pages. A page may contain one subject in two columns
at the top, a full-width subject heading in the middle, and another subject in
two columns below it. Finish the upper band first, then the heading, then the
lower band. Preserve printed sequence even if numbering looks unusual.

Answer-key page arranged as a grid:
- read each printed row from LEFT to RIGHT;
- then move to the next row from top to bottom;
- preserve section headings such as PHYSICS, CHEMISTRY, BOTANY, or ZOOLOGY.

For every question return source_page_start and order_on_page. order_on_page starts
at 1 independently on each page. These fields must describe visual PDF order, not
numerical question order.

MATCHING / LIST QUESTIONS — CRITICAL

When a question contains List I and List II, preserve both lists SIDE BY SIDE.
Do not write all of List I first and all of List II afterward.

Inside question_text, use a compact safe HTML table containing only these tags:
<table>, <thead>, <tbody>, <tr>, <th>, <td>, <br>, <strong>, <em>.

Example shape:
<table><thead><tr><th>List I</th><th>List II</th></tr></thead><tbody>
<tr><td>A. Custard apple</td><td>(i) Annonaceae</td></tr>
<tr><td>B. Guava</td><td>(ii) Bromeliaceae</td></tr>
</tbody></table>

Preserve the answer-code options exactly in option_a, option_b, option_c, etc.
Set contains_html_table=true for such questions.

MATHEMATICS AND CHEMISTRY

Use plain text wherever special formatting is unnecessary. Use MathJax-compatible
TeX source only when required for subscripts, superscripts, fractions, roots,
powers, Greek symbols, matrices, integrals, sums, vectors, equations, or chemical
formulae/reactions.

Use \( ... \) for inline mathematics and \[ ... \] for display mathematics.
Write simple values and units as plain text, such as 220 V, 5 kg, and 20 cm.
Never output rendered MathJax HTML, SVG, MathML, or mjx-container tags.

QUESTIONS

- Preserve the printed number exactly, including parts such as 12(a).
- Preserve all text and every option.
- For every diagram, graph, circuit, map, visual table, or pictorial option,
  return one tight image_regions entry. Use the original PDF page number and
  bbox_normalized=[left, top, right, bottom] from 0 to 1. Set target to question
  or the exact option field. Do not create regions for ordinary printed text.
- Combine a question that continues onto the next supplied page.
- If the beginning or ending is outside the supplied pages, preserve visible text
  and use [CONTINUES FROM PREVIOUS PAGE] or [CONTINUES ON NEXT PAGE].
- Use [UNREADABLE] rather than guessing.
- section should contain the nearest printed subject/section heading, or an empty string.

INSTRUCTIONS

Extract complete instruction text, including duration, marks, negative marking,
section rules, permitted question counts, and special directions. Do not put an
instruction page into questions unless it actually contains numbered questions.

ANSWER KEYS

Extract every answer pair. correct_option must contain only the printed answer,
for example 1, 2, 3, 4, A, B, C, D, or a printed numeric answer. Do not calculate it.

Return one compact valid JSON object only, without Markdown fences, with exactly
three top-level arrays named questions, instructions, and answer_keys. Escape
every JSON backslash correctly. Do not pretty-print.
"""

SYSTEM_PROMPT += "\n\nSTRICT OUTPUT JSON SCHEMA:\n" + json.dumps(
    OUTPUT_SCHEMA, separators=(",", ":")
)

# ============================================================
# General helpers
# ============================================================


def clean_secret(value: str) -> str:
    return value.strip().strip('"').strip("'")


def get_api_key() -> str:
    key = clean_secret(ANTHROPIC_API_KEY)
    if not key and "--questions" not in sys.argv:
        key = clean_secret(input("🔑 Enter Anthropic API key: "))
    if not key.startswith("sk-ant-"):
        raise ValueError("ANTHROPIC_API_KEY is missing or invalid-looking.")
    print(f"🔐 API key detected: {key[:7]}...{key[-4:]}")
    return key


def safe_name(value: str) -> str:
    cleaned = re.sub(r"[^A-Za-z0-9._-]+", "_", value).strip("._")
    return cleaned or "paper"


def find_pdfs(folder: Path) -> List[Path]:
    pattern = "**/*.pdf" if RECURSIVE else "*.pdf"
    return sorted(
        [path for path in folder.glob(pattern) if path.is_file()],
        key=lambda p: str(p).lower(),
    )


def choose_model(client: Anthropic) -> str:
    models = [
        item.id for item in client.models.list(limit=100).data
        if getattr(item, "id", None)
    ]
    if CLAUDE_MODEL:
        if CLAUDE_MODEL not in models:
            raise ValueError(f"Model is unavailable: {CLAUDE_MODEL}")
        return CLAUDE_MODEL
    for preferred in (
        "claude-sonnet-5",
        "claude-sonnet-4-6",
        "claude-sonnet-4-5-20250929",
    ):
        if preferred in models:
            return preferred
    sonnets = [m for m in models if "sonnet" in m.lower()]
    if sonnets:
        return sonnets[0]
    if not models:
        raise RuntimeError("No models returned for this API key.")
    return models[0]

# ============================================================
# PDF and image processing
# ============================================================


def pdf_to_images(pdf_path: Path, poppler_path: Optional[str]) -> List[Image.Image]:
    print(f"   📄 Converting {pdf_path.name} at {PDF_DPI} DPI")
    images = convert_from_path(
        str(pdf_path),
        dpi=PDF_DPI,
        poppler_path=poppler_path,
        fmt="jpeg",
        jpegopt={"quality": 94, "optimize": True},
        thread_count=max(1, min(4, os.cpu_count() or 1)),
    )
    if not images:
        raise RuntimeError("No pages were produced.")
    return images


def ink_mask(image: Image.Image) -> np.ndarray:
    gray = np.asarray(image.convert("L"), dtype=np.uint8)
    return gray < WHITE_THRESHOLD


def ink_ratio(image: Image.Image) -> float:
    return float(ink_mask(image).mean())


def content_bbox(image: Image.Image) -> Optional[Tuple[int, int, int, int]]:
    mask = ink_mask(image)
    height, width = mask.shape
    row_counts = mask.sum(axis=1)
    col_counts = mask.sum(axis=0)
    min_row_ink = max(2, int(width * 0.0012))
    min_col_ink = max(2, int(height * 0.0012))
    rows = np.flatnonzero(row_counts >= min_row_ink)
    cols = np.flatnonzero(col_counts >= min_col_ink)
    if len(rows) == 0 or len(cols) == 0:
        return None
    return int(cols[0]), int(rows[0]), int(cols[-1]) + 1, int(rows[-1]) + 1


def conservative_crop(image: Image.Image) -> Tuple[Image.Image, Dict[str, int]]:
    width, height = image.size
    bbox = content_bbox(image)
    crop = {"left": 0, "top": 0, "right": 0, "bottom": 0}
    if bbox is None:
        return image, crop
    left, top, right, bottom = bbox
    crop["left"] = min(
        max(0, left - CROP_SAFETY_PADDING), int(width * MAX_LEFT_CROP_FRACTION)
    )
    crop["right"] = min(
        max(0, width - right - CROP_SAFETY_PADDING), int(width * MAX_RIGHT_CROP_FRACTION)
    )
    crop["top"] = min(
        max(0, top - CROP_SAFETY_PADDING), int(height * MAX_TOP_CROP_FRACTION)
    )
    crop["bottom"] = min(
        max(0, height - bottom - CROP_SAFETY_PADDING), int(height * MAX_BOTTOM_CROP_FRACTION)
    )
    if crop["left"] + crop["right"] >= width - 200:
        crop["left"] = crop["right"] = 0
    if crop["top"] + crop["bottom"] >= height - 200:
        crop["top"] = crop["bottom"] = 0
    return image.crop((
        crop["left"], crop["top"], width - crop["right"], height - crop["bottom"]
    )), crop


def visual_tokens(image: Image.Image) -> int:
    width, height = image.size
    return math.ceil(width / 28) * math.ceil(height / 28)


def resize_to_token_cap(image: Image.Image, cap: int) -> Image.Image:
    current = visual_tokens(image)
    if current <= cap:
        return image
    width, height = image.size
    scale = math.sqrt(cap / current) * 0.98
    resized = image.resize(
        (max(300, int(width * scale)), max(300, int(height * scale))),
        Image.Resampling.LANCZOS,
    )
    while visual_tokens(resized) > cap:
        width, height = resized.size
        resized = resized.resize(
            (max(300, int(width * 0.98)), max(300, int(height * 0.98))),
            Image.Resampling.LANCZOS,
        )
    return resized


def detect_two_column(image: Image.Image) -> bool:
    """Conservative visual detector for a central whitespace gutter."""
    mask = ink_mask(image)
    height, width = mask.shape
    if width < 700:
        return False
    # Ignore top/bottom margins where headings can distort the test.
    body = mask[int(height * 0.10):int(height * 0.92), :]
    if body.size == 0:
        return False
    centre_start = int(width * 0.47)
    centre_end = int(width * 0.53)
    left = body[:, int(width * 0.08):int(width * 0.44)]
    centre = body[:, centre_start:centre_end]
    right = body[:, int(width * 0.56):int(width * 0.92)]
    left_ink = float(left.mean()) if left.size else 0.0
    right_ink = float(right.mean()) if right.size else 0.0
    centre_ink = float(centre.mean()) if centre.size else 1.0
    return (
        left_ink > 0.012
        and right_ink > 0.012
        and centre_ink < min(left_ink, right_ink) * 0.45
    )


def prepare_pages(images: List[Image.Image]) -> Tuple[List[Dict[str, Any]], List[Dict[str, Any]]]:
    pages: List[Dict[str, Any]] = []
    log: List[Dict[str, Any]] = []
    for page_number, original in enumerate(images, start=1):
        image = original.convert("RGB")
        cropped, crop = conservative_crop(image)
        if AUTO_CONTRAST:
            cropped = ImageOps.autocontrast(cropped, cutoff=0.4)
        if LIGHT_SHARPEN:
            cropped = cropped.filter(
                ImageFilter.UnsharpMask(radius=0.7, percent=45, threshold=3)
            )
        ratio = ink_ratio(cropped)
        if SKIP_NEAR_BLANK_PAGES and ratio < BLANK_PAGE_INK_RATIO:
            log.append({
                "page": page_number, "skipped": True,
                "ink_ratio": ratio, "crop": crop,
            })
            continue
        token_cap = SPARSE_PAGE_TOKENS if ratio < 0.025 else MAX_IMAGE_TOKENS_PER_PAGE
        resized = resize_to_token_cap(cropped, token_cap)
        two_column = detect_two_column(resized)
        dense = ratio >= DENSE_PAGE_INK_RATIO
        pages.append({
            "page_number": page_number,
            "image": resized,
            "estimated_visual_tokens": visual_tokens(resized),
            "two_column": two_column,
            "dense": dense,
        })
        log.append({
            "page": page_number, "skipped": False,
            "ink_ratio": ratio, "crop": crop,
            "original_size": list(original.size),
            "processed_size": list(resized.size),
            "estimated_visual_tokens": visual_tokens(resized),
            "two_column": two_column, "dense": dense,
        })
    if not pages:
        raise RuntimeError("All pages were considered blank.")
    return pages, log


def image_to_base64(image: Image.Image) -> str:
    buffer = io.BytesIO()
    image.save(
        buffer, format="JPEG", quality=JPEG_QUALITY,
        optimize=True, progressive=True,
    )
    return base64.b64encode(buffer.getvalue()).decode("utf-8")


def save_previews(pages: List[Dict[str, Any]], preview_folder: Path) -> None:
    if not SAVE_PREVIEWS:
        return
    preview_folder.mkdir(parents=True, exist_ok=True)
    for page in pages[:PREVIEW_MAX_PAGES]:
        image = page["image"].copy()
        image.thumbnail((1000, 1400), Image.Resampling.LANCZOS)
        suffix = "_2col" if page["two_column"] else ""
        image.save(
            preview_folder / f"page_{page['page_number']:04d}{suffix}.jpg",
            quality=90,
        )


def make_page_groups(pages: List[Dict[str, Any]]) -> List[List[Dict[str, Any]]]:
    """
    Dense/two-column pages are sent alone to avoid truncation and sequence loss.
    Other pages are paired, preserving PDF order.
    """
    groups: List[List[Dict[str, Any]]] = []
    index = 0
    while index < len(pages):
        page = pages[index]
        if page["two_column"] or page["dense"]:
            groups.append([page])
            index += 1
            continue
        group = [page]
        index += 1
        while index < len(pages) and len(group) < NORMAL_PAGES_PER_REQUEST:
            candidate = pages[index]
            if candidate["two_column"] or candidate["dense"]:
                break
            # Only combine consecutive original pages.
            if candidate["page_number"] != group[-1]["page_number"] + 1:
                break
            group.append(candidate)
            index += 1
        groups.append(group)
    # Boundary bridges expose questions split across two primary request groups.
    # Duplicate fragments are merged later by section and printed number.
    bridges: List[List[Dict[str, Any]]] = []
    for left, right in zip(groups, groups[1:]):
        if left[-1]["page_number"] + 1 == right[0]["page_number"]:
            bridges.append([left[-1], right[0]])
    return groups + bridges

# ============================================================
# Batch request building
# ============================================================


def create_request_params(
    model: str,
    pages: List[Dict[str, Any]],
) -> MessageCreateParamsNonStreaming:
    page_numbers = [p["page_number"] for p in pages]
    layout_notes = [
        f"page {p['page_number']}: "
        + ("two-column" if p["two_column"] else "single-column/other")
        + (", dense" if p["dense"] else "")
        for p in pages
    ]
    content: List[Dict[str, Any]] = [{
        "type": "text",
        "text": (
            f"Original PDF pages: {page_numbers}. "
            f"Layout hints: {'; '.join(layout_notes)}. "
            "Extract questions, instructions, and answer keys. "
            "Preserve exact visual reading order."
        ),
    }]
    for page in pages:
        content.append({
            "type": "text",
            "text": f"ORIGINAL PDF PAGE {page['page_number']}",
        })
        content.append({
            "type": "image",
            "source": {
                "type": "base64",
                "media_type": "image/jpeg",
                "data": image_to_base64(page["image"]),
            },
        })
    # Message Batches use ordinary compact JSON text output here.
    # Invalid JSON is repaired later using a small text-only request; images are
    # never resent for repair.
    return MessageCreateParamsNonStreaming(
        model=model,
        max_tokens=MAX_OUTPUT_TOKENS,
        system=[{
            "type": "text",
            "text": SYSTEM_PROMPT,
            "cache_control": {"type": "ephemeral"},
        }],
        messages=[{"role": "user", "content": content}],
    )


def request_payload_size(params: MessageCreateParamsNonStreaming) -> int:
    try:
        return len(json.dumps(params, default=lambda value: value.__dict__))
    except Exception:
        return 0


def split_request_chunks(requests: List[Request], sizes: List[int]) -> List[List[Request]]:
    limit = MAX_BATCH_PAYLOAD_MB * 1024 * 1024
    chunks: List[List[Request]] = []
    current: List[Request] = []
    current_size = 0
    for request, size in zip(requests, sizes):
        if current and current_size + size > limit:
            chunks.append(current)
            current = []
            current_size = 0
        current.append(request)
        current_size += size
    if current:
        chunks.append(current)
    return chunks


def submit_and_wait(
    client: Anthropic,
    requests: List[Request],
    chunk_number: int,
    total_chunks: int,
) -> List[Any]:
    print(
        f"\n🚀 Submitting API batch {chunk_number}/{total_chunks} "
        f"with {len(requests)} request(s)..."
    )
    batch = client.messages.batches.create(requests=requests)
    print(f"   Batch ID: {batch.id}")
    while True:
        status = client.messages.batches.retrieve(batch.id)
        counts = status.request_counts
        print(
            "   ⏳ "
            f"processing={counts.processing}, succeeded={counts.succeeded}, "
            f"errored={counts.errored}, expired={counts.expired}",
            end="\r",
        )
        if status.processing_status == "ended":
            print()
            break
        time.sleep(POLL_SECONDS)
    return list(client.messages.batches.results(batch.id))

# ============================================================
# Parsing and ordering
# ============================================================


def response_text(message: Any) -> str:
    return "\n".join(
        getattr(block, "text", "")
        for block in message.content
        if getattr(block, "type", "") == "text" and getattr(block, "text", "")
    ).strip()


def parse_json_object(text: str) -> Dict[str, Any]:
    """Parse a JSON object while tolerating code fences or brief stray text."""
    cleaned = text.strip()
    cleaned = re.sub(r"^```(?:json)?\s*", "", cleaned, flags=re.I)
    cleaned = re.sub(r"\s*```$", "", cleaned)
    start = cleaned.find("{")
    end = cleaned.rfind("}")
    if start == -1 or end == -1 or end <= start:
        raise ValueError("No complete JSON object found in response.")
    return json.loads(cleaned[start:end + 1])


def response_usage(message: Any) -> Dict[str, int]:
    usage = getattr(message, "usage", None)
    return {
        "input_tokens": int(getattr(usage, "input_tokens", 0) or 0),
        "output_tokens": int(getattr(usage, "output_tokens", 0) or 0),
    }


def repair_json_text(
    client: Anthropic,
    model: str,
    invalid_text: str,
) -> Tuple[str, Dict[str, int]]:
    """Repair malformed JSON using text only; no page images are resent."""
    repair_instruction = (
        "Repair the malformed JSON below. Preserve every extracted word, "
        "number, TeX expression, HTML table, question, instruction and answer "
        "key exactly. Do not add, delete, solve, reorder or paraphrase content. "
        "Return one compact valid JSON object only with top-level arrays "
        "questions, instructions and answer_keys. Escape JSON backslashes "
        "correctly. No Markdown fences.\n\n"
    )
    response = client.messages.create(
        model=model,
        max_tokens=min(MAX_OUTPUT_TOKENS, 16000),
        messages=[{
            "role": "user",
            "content": repair_instruction + invalid_text,
        }],
    )
    return response_text(response), response_usage(response)


def integer_or_default(value: Any, default: int) -> int:
    try:
        return int(value)
    except (TypeError, ValueError):
        return default


def normalize_question(
    question: Dict[str, Any],
    batch_number: int,
    extraction_index: int,
    fallback_page: int,
) -> Dict[str, Any]:
    pages = question.get("source_pages", [])
    if not isinstance(pages, list):
        pages = [pages]
    normalized_pages = []
    for page in pages:
        try:
            normalized_pages.append(int(page))
        except (TypeError, ValueError):
            pass
    if not normalized_pages:
        normalized_pages = [fallback_page]
    start_page = integer_or_default(
        question.get("source_page_start"), min(normalized_pages)
    )
    order_on_page = integer_or_default(question.get("order_on_page"), extraction_index + 1)
    marks = question.get("marks")
    if marks == "":
        marks = None
    return {
        "section": str(question.get("section", "") or "").strip(),
        "number": str(question.get("number", "") or "").strip(),
        "question_type": str(question.get("question_type", "unknown") or "unknown").strip(),
        "question_text": str(question.get("question_text", "") or "").strip(),
        "marks": marks,
        "option_a": str(question.get("option_a", "") or "").strip(),
        "option_b": str(question.get("option_b", "") or "").strip(),
        "option_c": str(question.get("option_c", "") or "").strip(),
        "option_d": str(question.get("option_d", "") or "").strip(),
        "option_e": str(question.get("option_e", "") or "").strip(),
        "correct_option": "",
        "diagram_required": bool(question.get("diagram_required", False)),
        "diagram_description": str(question.get("diagram_description", "") or "").strip(),
        "contains_html_table": bool(question.get("contains_html_table", False)),
        "image_regions": question.get("image_regions", [])
        if isinstance(question.get("image_regions", []), list)
        else [],
        "source_pages": normalized_pages,
        "source_page_start": start_page,
        "order_on_page": order_on_page,
        "_batch_number": batch_number,
        "_extraction_index": extraction_index,
    }


def normalize_instruction(item: Dict[str, Any], batch_number: int, index: int) -> Dict[str, Any]:
    return {
        "title": str(item.get("title", "") or "").strip(),
        "instruction_text": str(item.get("instruction_text", "") or "").strip(),
        "source_page": integer_or_default(item.get("source_page"), 0),
        "order_on_page": integer_or_default(item.get("order_on_page"), index + 1),
        "_batch_number": batch_number,
        "_extraction_index": index,
    }


def normalize_answer(item: Dict[str, Any], batch_number: int, index: int) -> Dict[str, Any]:
    return {
        "section": str(item.get("section", "") or "").strip(),
        "question_number": str(item.get("question_number", "") or "").strip(),
        "correct_option": str(item.get("correct_option", "") or "").strip(),
        "source_page": integer_or_default(item.get("source_page"), 0),
        "order_on_page": integer_or_default(item.get("order_on_page"), index + 1),
        "_batch_number": batch_number,
        "_extraction_index": index,
    }


def visual_order_key(item: Dict[str, Any], page_field: str = "source_page_start") -> Tuple[int, int, int, int]:
    return (
        integer_or_default(item.get(page_field), 999999),
        integer_or_default(item.get("order_on_page"), 999999),
        integer_or_default(item.get("_batch_number"), 999999),
        integer_or_default(item.get("_extraction_index"), 999999),
    )


def identity_text(value: str) -> str:
    return re.sub(r"\s+", " ", value.strip().lower())


def deduplicate_questions(questions: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    return merge_question_fragments(questions)


def normalize_section(value: str) -> str:
    return re.sub(r"[^a-z0-9]+", "", value.lower())


def normalize_number(value: str) -> str:
    return re.sub(r"\s+", "", value.lower()).rstrip(".")


def attach_answer_keys(
    questions: List[Dict[str, Any]],
    answers: List[Dict[str, Any]],
) -> None:
    exact: Dict[Tuple[str, str], str] = {}
    by_number: Dict[str, List[str]] = {}
    for answer in answers:
        number = normalize_number(answer["question_number"])
        section = normalize_section(answer["section"])
        if number:
            exact[(section, number)] = answer["correct_option"]
            by_number.setdefault(number, []).append(answer["correct_option"])
    for question in questions:
        number = normalize_number(question["number"])
        section = normalize_section(question["section"])
        correct = exact.get((section, number), "")
        if not correct:
            candidates = list(dict.fromkeys(by_number.get(number, [])))
            if len(candidates) == 1:
                correct = candidates[0]
        question["correct_option"] = correct


def strip_internal_fields(items: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    return [
        {key: value for key, value in item.items() if not key.startswith("_")}
        for item in items
    ]

# ============================================================
# CSV and JSON output
# ============================================================

QUESTION_FIELDS = [
    "sequence", "section", "number", "question_type", "question_text", "marks",
    "option_a", "option_b", "option_c", "option_d", "option_e", "correct_option",
    "diagram_required", "diagram_description", "contains_html_table",
    "source_pages", "source_page_start", "order_on_page",
]
ANSWER_FIELDS = [
    "section", "question_number", "correct_option", "source_page", "order_on_page",
]
INSTRUCTION_FIELDS = ["title", "instruction_text", "source_page", "order_on_page"]


def save_questions_csv(questions: List[Dict[str, Any]], path: Path) -> None:
    with path.open("w", encoding="utf-8-sig", newline="") as file:
        writer = csv.DictWriter(file, fieldnames=QUESTION_FIELDS)
        writer.writeheader()
        for sequence, question in enumerate(questions, start=1):
            row = {field: question.get(field, "") for field in QUESTION_FIELDS}
            row["sequence"] = sequence
            row["source_pages"] = ",".join(str(p) for p in question.get("source_pages", []))
            writer.writerow(row)


def save_simple_csv(items: List[Dict[str, Any]], fields: List[str], path: Path) -> None:
    with path.open("w", encoding="utf-8-sig", newline="") as file:
        writer = csv.DictWriter(file, fieldnames=fields)
        writer.writeheader()
        for item in items:
            writer.writerow({field: item.get(field, "") for field in fields})


def save_paper_outputs(
    paper: Dict[str, Any],
    output_root: Path,
    model: str,
    poppler_path: Optional[str],
    contract_mode: bool = False,
    public_prefix: str = "",
) -> Dict[str, Any]:
    if paper["errors"]:
        raise RuntimeError(
            "One or more Claude page batches failed; partial question import was stopped. "
            + json.dumps(paper["errors"][:5], ensure_ascii=False)
        )

    paper_dir = output_root / paper["output_name"]
    if not contract_mode:
        paper_dir.mkdir(parents=True, exist_ok=True)

    questions = merge_question_fragments(paper["questions"])
    instructions = sorted(
        paper["instructions"], key=lambda x: visual_order_key(x, "source_page")
    )
    answers = checkpoint_and_attach_answers(questions, paper["answer_keys"])

    image_folder = output_root if contract_mode else paper_dir / "images"
    image_prefix = public_prefix if contract_mode else "images"
    extract_question_images(
        paper["pdf_path"], questions, image_folder, image_prefix,
        poppler_path, pdf_to_images,
    )

    clean_questions = strip_internal_fields(questions)
    clean_instructions = strip_internal_fields(instructions)
    clean_answers = strip_internal_fields(answers)
    paper["final_questions"] = clean_questions
    paper["final_answers"] = clean_answers

    usage = paper["usage"]
    estimated_cost = (
        usage["input_tokens"] / 1_000_000 * BATCH_INPUT_PRICE_PER_MTOK
        + usage["output_tokens"] / 1_000_000 * BATCH_OUTPUT_PRICE_PER_MTOK
    )
    result = {
        "source_file": str(paper["pdf_path"].resolve()),
        "provider": "Anthropic Message Batches API with JSON repair",
        "model": model,
        "math_format": "MathJax-compatible TeX source",
        "question_order": "PDF visual order: page then order_on_page",
        "total_pages": paper["total_pages"],
        "processed_pages": paper["processed_pages"],
        "total_questions": len(clean_questions),
        "total_instructions": len(clean_instructions),
        "total_answer_keys": len(clean_answers),
        "answer_checkpoint": "exact section and printed-number match passed",
        "usage": usage,
        "estimated_batch_cost_usd": round(estimated_cost, 6),
        "questions": clean_questions,
        "instructions": clean_instructions,
        "answer_keys": clean_answers,
        "errors": paper["errors"],
    }

    if not contract_mode:
        (paper_dir / "exam_content.json").write_text(
            json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8"
        )
        (paper_dir / "exam_questions.json").write_text(
            json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8"
        )
        save_questions_csv(clean_questions, paper_dir / "exam_questions.csv")
        save_simple_csv(clean_answers, ANSWER_FIELDS, paper_dir / "answer_keys.csv")
        save_simple_csv(
            clean_instructions, INSTRUCTION_FIELDS, paper_dir / "instructions.csv"
        )
        (paper_dir / "raw_outputs.txt").write_text(
            "\n\n".join(paper["raw_outputs"]), encoding="utf-8"
        )
        (paper_dir / "processing_report.json").write_text(
            json.dumps({
                "source_file": str(paper["pdf_path"].resolve()),
                "page_processing": paper["processing_log"],
                "request_groups": paper["request_groups"],
                "usage": usage,
                "estimated_batch_cost_usd": round(estimated_cost, 6),
                "answer_checkpoint": {
                    "questions": len(clean_questions),
                    "answers": len(clean_answers),
                    "passed": True,
                },
                "errors": paper["errors"],
            }, ensure_ascii=False, indent=2),
            encoding="utf-8",
        )

    return {
        "file": paper["pdf_path"].name,
        "questions": len(clean_questions),
        "instructions": len(clean_instructions),
        "answer_keys": len(clean_answers),
        "input_tokens": usage["input_tokens"],
        "output_tokens": usage["output_tokens"],
        "estimated_cost_usd": round(estimated_cost, 6),
        "errors": len(paper["errors"]),
        "output_folder": str(image_folder if contract_mode else paper_dir),
    }
# ============================================================
# Folder workflow
# ============================================================


def run_folder(
    input_folder: Path,
    output_folder: Path,
    poppler_path: Optional[str],
    pdfs_override: Optional[List[Path]] = None,
    result_file: Optional[Path] = None,
    public_prefix: str = "",
) -> None:
    client = Anthropic(api_key=get_api_key())
    model = choose_model(client)
    print(f"🤖 Model: {model}")
    print("💰 Message Batches API enabled")
    print("🧾 Compact JSON output with automatic text-only repair")

    pdfs = pdfs_override if pdfs_override is not None else find_pdfs(input_folder)
    if not pdfs:
        raise FileNotFoundError(f"No PDF files found in {input_folder}")
    print(f"📚 Found {len(pdfs)} PDF file(s)")
    output_folder.mkdir(parents=True, exist_ok=True)

    papers: Dict[str, Dict[str, Any]] = {}
    requests: List[Request] = []
    request_sizes: List[int] = []
    request_map: Dict[str, Dict[str, Any]] = {}

    for file_index, pdf_path in enumerate(pdfs, start=1):
        output_name = safe_name(pdf_path.stem)
        final_json = output_folder / output_name / "exam_content.json"
        if result_file is None and SKIP_EXISTING and final_json.exists():
            print(f"⏭️ Skipping existing output: {pdf_path.name}")
            continue
        print(f"\n[{file_index}/{len(pdfs)}] {pdf_path.name}")
        try:
            original_images = pdf_to_images(pdf_path, poppler_path)
            pages, processing_log = prepare_pages(original_images)
            groups = make_page_groups(pages)
            paper_key = f"f{file_index:04d}"
            papers[paper_key] = {
                "pdf_path": pdf_path,
                "output_name": output_name,
                "total_pages": len(original_images),
                "processed_pages": len(pages),
                "processing_log": processing_log,
                "request_groups": [
                    [p["page_number"] for p in group] for group in groups
                ],
                "questions": [],
                "instructions": [],
                "answer_keys": [],
                "raw_outputs": [],
                "errors": [],
                "usage": {"input_tokens": 0, "output_tokens": 0},
            }
            if result_file is None:
                save_previews(
                    pages, output_folder / output_name / "processed_page_previews"
                )
            for group_index, page_group in enumerate(groups, start=1):
                custom_id = f"{paper_key}_g{group_index:04d}"
                params = create_request_params(model, page_group)
                request = Request(custom_id=custom_id, params=params)
                requests.append(request)
                request_sizes.append(request_payload_size(params))
                request_map[custom_id] = {
                    "paper_key": paper_key,
                    "group_number": group_index,
                    "page_numbers": [p["page_number"] for p in page_group],
                }
        except Exception as exc:
            if result_file is not None:
                raise RuntimeError(f"Could not prepare {pdf_path.name}: {exc}") from exc
            print(f"❌ Could not prepare {pdf_path.name}: {exc}")

    if not requests:
        if result_file is not None:
            raise RuntimeError("No extractor requests were created; no drafts were created.")
        print("No new requests to process.")
        return

    chunks = split_request_chunks(requests, request_sizes)
    all_results: List[Any] = []
    for chunk_index, chunk in enumerate(chunks, start=1):
        all_results.extend(submit_and_wait(client, chunk, chunk_index, len(chunks)))

    for result in all_results:
        metadata = request_map.get(result.custom_id)
        if not metadata:
            continue
        paper = papers[metadata["paper_key"]]
        if result.result.type != "succeeded":
            details = str(result.result)
            print(
                f"❌ API group {metadata['group_number']} pages "
                f"{metadata['page_numbers']} failed: {details}"
            )
            paper["errors"].append({
                "group": metadata["group_number"],
                "pages": metadata["page_numbers"],
                "error_type": result.result.type,
                "details": details,
            })
            continue
        message = result.result.message
        text = response_text(message)
        stop_reason = getattr(message, "stop_reason", None)
        paper["raw_outputs"].append(
            f"===== GROUP {metadata['group_number']} "
            f"PAGES {metadata['page_numbers']} STOP={stop_reason} =====\n{text}"
        )
        usage = getattr(message, "usage", None)
        paper["usage"]["input_tokens"] += int(getattr(usage, "input_tokens", 0) or 0)
        paper["usage"]["output_tokens"] += int(getattr(usage, "output_tokens", 0) or 0)
        if stop_reason == "max_tokens":
            paper["errors"].append({
                "group": metadata["group_number"],
                "pages": metadata["page_numbers"],
                "error_type": "max_tokens",
                "details": "Response reached MAX_OUTPUT_TOKENS; output may be incomplete.",
            })
        try:
            parsed = parse_json_object(text)
            fallback_page = metadata["page_numbers"][0]
            for index, item in enumerate(parsed.get("questions", [])):
                if isinstance(item, dict):
                    paper["questions"].append(normalize_question(
                        item, metadata["group_number"], index, fallback_page
                    ))
            for index, item in enumerate(parsed.get("instructions", [])):
                if isinstance(item, dict):
                    paper["instructions"].append(normalize_instruction(
                        item, metadata["group_number"], index
                    ))
            for index, item in enumerate(parsed.get("answer_keys", [])):
                if isinstance(item, dict):
                    paper["answer_keys"].append(normalize_answer(
                        item, metadata["group_number"], index
                    ))
        except Exception as exc:
            print(
                f"🛠️ Repairing JSON for group {metadata['group_number']} "
                f"pages {metadata['page_numbers']} (text only)..."
            )
            try:
                repaired_text, repair_usage = repair_json_text(
                    client, model, text
                )
                paper["usage"]["input_tokens"] += repair_usage["input_tokens"]
                paper["usage"]["output_tokens"] += repair_usage["output_tokens"]
                paper["raw_outputs"].append(
                    f"===== REPAIRED GROUP {metadata['group_number']} "
                    f"PAGES {metadata['page_numbers']} =====\n{repaired_text}"
                )
                parsed = parse_json_object(repaired_text)
                fallback_page = metadata["page_numbers"][0]
                for index, item in enumerate(parsed.get("questions", [])):
                    if isinstance(item, dict):
                        paper["questions"].append(normalize_question(
                            item, metadata["group_number"], index, fallback_page
                        ))
                for index, item in enumerate(parsed.get("instructions", [])):
                    if isinstance(item, dict):
                        paper["instructions"].append(normalize_instruction(
                            item, metadata["group_number"], index
                        ))
                for index, item in enumerate(parsed.get("answer_keys", [])):
                    if isinstance(item, dict):
                        paper["answer_keys"].append(normalize_answer(
                            item, metadata["group_number"], index
                        ))
            except Exception as repair_exc:
                details = f"Initial parse: {exc}; repair: {repair_exc}"
                print(
                    f"❌ JSON repair failed for group "
                    f"{metadata['group_number']}: {details}"
                )
                paper["errors"].append({
                    "group": metadata["group_number"],
                    "pages": metadata["page_numbers"],
                    "error_type": "json_parse_error",
                    "details": details,
                })

    summary_rows: List[Dict[str, Any]] = []
    for paper in papers.values():
        summary_rows.append(save_paper_outputs(
            paper,
            output_folder,
            model,
            poppler_path,
            contract_mode=result_file is not None,
            public_prefix=public_prefix,
        ))

    if result_file is not None:
        questions: List[Dict[str, Any]] = []
        for paper in papers.values():
            questions.extend(
                application_question_rows(paper.get("final_questions", []))
            )
        result_file.parent.mkdir(parents=True, exist_ok=True)
        temporary = result_file.with_suffix(result_file.suffix + ".tmp")
        temporary.write_text(json.dumps({
            "ok": True,
            "questions": questions,
            "provider": "Anthropic Message Batches API",
            "model": model,
            "answer_checkpoint": "passed",
        }, ensure_ascii=False), encoding="utf-8")
        temporary.replace(result_file)
        return
    summary_fields = [
        "file", "questions", "instructions", "answer_keys", "input_tokens",
        "output_tokens", "estimated_cost_usd", "errors", "output_folder",
    ]
    with (output_folder / "folder_summary.csv").open(
        "w", encoding="utf-8-sig", newline=""
    ) as file:
        writer = csv.DictWriter(file, fieldnames=summary_fields)
        writer.writeheader()
        writer.writerows(summary_rows)

    totals = {
        "files": len(summary_rows),
        "questions": sum(row["questions"] for row in summary_rows),
        "instructions": sum(row["instructions"] for row in summary_rows),
        "answer_keys": sum(row["answer_keys"] for row in summary_rows),
        "input_tokens": sum(row["input_tokens"] for row in summary_rows),
        "output_tokens": sum(row["output_tokens"] for row in summary_rows),
        "estimated_cost_usd": round(sum(row["estimated_cost_usd"] for row in summary_rows), 6),
        "errors": sum(row["errors"] for row in summary_rows),
        "model": model,
    }
    (output_folder / "folder_summary.json").write_text(
        json.dumps(totals, ensure_ascii=False, indent=2), encoding="utf-8"
    )
    print("\n✅ Folder extraction completed")
    print(f"📄 Files: {totals['files']}")
    print(f"📊 Questions: {totals['questions']}")
    print(f"📋 Instructions: {totals['instructions']}")
    print(f"🔑 Answer keys: {totals['answer_keys']}")
    print(
        f"🧮 Tokens — input: {totals['input_tokens']:,}, "
        f"output: {totals['output_tokens']:,}"
    )
    print(f"💵 Estimated batch cost: ${totals['estimated_cost_usd']:.4f}")
    print(f"📁 Outputs: {output_folder.resolve()}")


def run_contract() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--questions", required=True)
    parser.add_argument("--answers")
    parser.add_argument("--solutions")
    parser.add_argument("--out", required=True)
    parser.add_argument("--public-prefix", required=True)
    parser.add_argument("--paper-code", required=True)
    parser.add_argument("--profile", default="{}")
    parser.add_argument("--result-file", required=True)
    args = parser.parse_args()
    question_path = Path(args.questions).resolve()
    if not question_path.is_file():
        raise FileNotFoundError(f"Question PDF was not found: {question_path}")
    run_folder(
        input_folder=question_path.parent,
        output_folder=Path(args.out),
        poppler_path=os.getenv("POPPLER_PATH") or None,
        pdfs_override=[question_path],
        result_file=Path(args.result_file),
        public_prefix=args.public_prefix,
    )


if __name__ == "__main__":
    if "--questions" in sys.argv:
        try:
            run_contract()
        except Exception as exc:
            error_payload = {"ok": False, "error": str(exc)}
            if "--result-file" in sys.argv:
                try:
                    result_path = Path(sys.argv[sys.argv.index("--result-file") + 1])
                    result_path.parent.mkdir(parents=True, exist_ok=True)
                    result_path.write_text(
                        json.dumps(error_payload, ensure_ascii=False), encoding="utf-8"
                    )
                except Exception:
                    pass
            print(json.dumps(error_payload, ensure_ascii=False))
            sys.exit(2)
    else:
        print("=" * 80)
        print("📚 ANTHROPIC ORDERED EXAM EXTRACTOR V2")
        print("QUESTIONS + ANSWERS + STRICT CHECKPOINT + QUESTION/OPTION IMAGES")
        print("=" * 80)
        entered_input = input(
            f"\n📁 Input folder (default: {DEFAULT_INPUT_FOLDER}): "
        ).strip()
        input_folder = Path(entered_input or DEFAULT_INPUT_FOLDER)
        entered_output = input(
            f"📁 Output folder (default: {DEFAULT_OUTPUT_FOLDER}): "
        ).strip()
        output_folder = Path(entered_output or DEFAULT_OUTPUT_FOLDER)
        poppler_input = input(
            "🧰 Poppler bin path, or Enter when already in PATH: "
        ).strip()
        try:
            run_folder(input_folder, output_folder, poppler_input or None)
        except KeyboardInterrupt:
            print("\nCancelled.")
            sys.exit(130)
        except Exception as exc:
            print(f"\n❌ Error: {exc}")
            sys.exit(1)
        input("\nPress Enter to exit...")
