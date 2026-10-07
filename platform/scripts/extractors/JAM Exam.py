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
# Install missing packages
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
from PIL import Image, ImageEnhance, ImageFilter, ImageOps
from pdf2image import convert_from_path
from anthropic import Anthropic
from anthropic.types.message_create_params import MessageCreateParamsNonStreaming
from anthropic.types.messages.batch_create_params import Request

SUPPORT_DIR = Path(__file__).resolve().parent / "claude_support"
sys.path.insert(0, str(SUPPORT_DIR))
from examelite_contract import (
    application_question_rows,
    extract_question_images,
    merge_question_fragments,
)


# ============================================================
# Configuration
# ============================================================

DEFAULT_INPUT_FOLDER = str(Path.home() / "Downloads")
DEFAULT_OUTPUT_FOLDER = "anthropic_extracted"

PDF_DPI = int(os.getenv("PDF_DPI", "180"))
PAGE_BATCH_SIZE = int(os.getenv("PAGE_BATCH_SIZE", "5"))
MAX_IMAGE_TOKENS_PER_PAGE = int(
    os.getenv("MAX_IMAGE_TOKENS_PER_PAGE", "1500")
)
SPARSE_PAGE_TOKENS = int(os.getenv("SPARSE_PAGE_TOKENS", "1300"))
JPEG_QUALITY = int(os.getenv("JPEG_QUALITY", "88"))
MAX_OUTPUT_TOKENS = int(os.getenv("MAX_OUTPUT_TOKENS", "6500"))

RECURSIVE = os.getenv("RECURSIVE", "0") == "1"
SKIP_EXISTING = os.getenv("SKIP_EXISTING", "1") == "1"
SAVE_PREVIEWS = os.getenv("SAVE_PREVIEWS", "1") == "1"
PREVIEW_MAX_PAGES = int(os.getenv("PREVIEW_MAX_PAGES", "5"))

AUTO_CONTRAST = os.getenv("AUTO_CONTRAST", "1") == "1"
LIGHT_SHARPEN = os.getenv("LIGHT_SHARPEN", "1") == "1"
WHITE_THRESHOLD = int(os.getenv("WHITE_THRESHOLD", "245"))
CROP_SAFETY_PADDING = int(os.getenv("CROP_SAFETY_PADDING", "24"))

MAX_LEFT_CROP_FRACTION = float(os.getenv("MAX_LEFT_CROP_FRACTION", "0.08"))
MAX_RIGHT_CROP_FRACTION = float(os.getenv("MAX_RIGHT_CROP_FRACTION", "0.08"))
MAX_TOP_CROP_FRACTION = float(os.getenv("MAX_TOP_CROP_FRACTION", "0.06"))
MAX_BOTTOM_CROP_FRACTION = float(os.getenv("MAX_BOTTOM_CROP_FRACTION", "0.06"))

SKIP_NEAR_BLANK_PAGES = os.getenv("SKIP_NEAR_BLANK_PAGES", "1") == "1"
BLANK_PAGE_INK_RATIO = float(os.getenv("BLANK_PAGE_INK_RATIO", "0.00035"))

POLL_SECONDS = int(os.getenv("BATCH_POLL_SECONDS", "30"))
MAX_BATCH_PAYLOAD_MB = int(os.getenv("MAX_BATCH_PAYLOAD_MB", "220"))

ANTHROPIC_API_KEY = os.getenv("ANTHROPIC_API_KEY", "").strip()
CLAUDE_MODEL = (os.getenv("CLAUDE_MODEL") or os.getenv("ANTHROPIC_MODEL") or "").strip()

# Current official Message Batches price for Claude Sonnet 5 through
# 31 August 2026. Override through environment variables when prices change.
BATCH_INPUT_PRICE_PER_MTOK = float(
    os.getenv("BATCH_INPUT_PRICE_PER_MTOK", "1.0")
)
BATCH_OUTPUT_PRICE_PER_MTOK = float(
    os.getenv("BATCH_OUTPUT_PRICE_PER_MTOK", "5.0")
)


# ============================================================
# Cached extraction prompt
# ============================================================

SYSTEM_PROMPT = r"""
You are a high-precision exam-paper transcription engine.

Extract every visible exam question from the supplied page images. Do not solve
questions, infer answers, explain, paraphrase, summarise, or add commentary.

Preserve:
- printed question numbers;
- complete wording;
- all options;
- printed marks;
- page order;
- mathematical and chemical notation;
- source page numbers.

Combine a question that continues onto another supplied page. Never guess
unreadable content; write [UNREADABLE].

Use normal plain text whenever special formatting is unnecessary. Use
MathJax-compatible TeX source only where formatting is necessary:
subscripts, superscripts, fractions, roots, powers, Greek symbols, matrices,
integrals, sums, vectors, complex equations, and chemical formulae/reactions.

Use \( ... \) for inline mathematics and \[ ... \] for display mathematics.
Write simple values and units as plain text, for example 220 V, 5 kg and 20 cm.
Use TeX for expressions such as \(P_1\), \(x^2\), \(\frac{a}{b}\), and
\(\mathrm{2H_2 + O_2 \rightarrow 2H_2O}\).

Never output rendered MathJax HTML, SVG, MathML, or mjx-container tags.

Return valid compact JSON only, without Markdown fences, using exactly:

{"questions":[{"number":"1","question_type":"MCQ","question_text":"",
"marks":1,"option_a":"","option_b":"","option_c":"","option_d":"",
"option_e":"","diagram_required":false,"diagram_description":"",
"image_regions":[{"target":"question|option_a|option_b|option_c|option_d|option_e","page":1,"bbox_normalized":[0.1,0.2,0.9,0.7],"description":"diagram/graph/table/circuit"}],
"source_pages":[1]}]}

For every diagram, graph, table, circuit, map, photograph, chemical structure or
pictorial option required by a question, return one tight image_regions entry.
Use the ORIGINAL PDF page number and bbox_normalized [x1,y1,x2,y2] from 0 to 1.
Set target to question or the exact option_a through option_e. Do not crop ordinary
text, headers, footers, logos, or watermarks. Set diagram_required=true whenever
the question cannot be represented completely without the source visual.

Use null when marks are not printed and empty strings for absent options.
Keep JSON compact: do not pretty-print it.
"""


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


def list_models(client: Anthropic) -> List[str]:
    return [
        item.id
        for item in client.models.list(limit=100).data
        if getattr(item, "id", None)
    ]


def choose_model(client: Anthropic) -> str:
    models = list_models(client)

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

    sonnets = [model for model in models if "sonnet" in model.lower()]
    if sonnets:
        return sonnets[0]

    if not models:
        raise RuntimeError("No models returned for this API key.")

    return models[0]


# ============================================================
# PDF and image processing
# ============================================================

def pdf_to_images(
    pdf_path: Path,
    poppler_path: Optional[str],
) -> List[Image.Image]:
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

    return (
        int(cols[0]),
        int(rows[0]),
        int(cols[-1]) + 1,
        int(rows[-1]) + 1,
    )


def conservative_crop(image: Image.Image) -> Tuple[Image.Image, Dict[str, int]]:
    """
    Conservatively removes only blank left/right/top/bottom margins.
    It never removes more than 8% from a side or 6% from top/bottom.
    """
    width, height = image.size
    bbox = content_bbox(image)

    crop = {"left": 0, "top": 0, "right": 0, "bottom": 0}

    if bbox is None:
        return image, crop

    left, top, right, bottom = bbox

    desired_left = max(0, left - CROP_SAFETY_PADDING)
    desired_top = max(0, top - CROP_SAFETY_PADDING)
    desired_right = max(0, width - right - CROP_SAFETY_PADDING)
    desired_bottom = max(0, height - bottom - CROP_SAFETY_PADDING)

    crop["left"] = min(desired_left, int(width * MAX_LEFT_CROP_FRACTION))
    crop["right"] = min(desired_right, int(width * MAX_RIGHT_CROP_FRACTION))
    crop["top"] = min(desired_top, int(height * MAX_TOP_CROP_FRACTION))
    crop["bottom"] = min(
        desired_bottom,
        int(height * MAX_BOTTOM_CROP_FRACTION),
    )

    if crop["left"] + crop["right"] >= width - 200:
        crop["left"] = crop["right"] = 0

    if crop["top"] + crop["bottom"] >= height - 200:
        crop["top"] = crop["bottom"] = 0

    return image.crop(
        (
            crop["left"],
            crop["top"],
            width - crop["right"],
            height - crop["bottom"],
        )
    ), crop


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
        (
            max(300, int(width * scale)),
            max(300, int(height * scale)),
        ),
        Image.Resampling.LANCZOS,
    )

    while visual_tokens(resized) > cap:
        width, height = resized.size
        resized = resized.resize(
            (
                max(300, int(width * 0.98)),
                max(300, int(height * 0.98)),
            ),
            Image.Resampling.LANCZOS,
        )

    return resized


def prepare_pages(
    images: List[Image.Image],
) -> Tuple[List[Dict[str, Any]], List[Dict[str, Any]]]:
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
                "page": page_number,
                "skipped": True,
                "ink_ratio": ratio,
                "crop": crop,
            })
            continue

        token_cap = (
            SPARSE_PAGE_TOKENS
            if ratio < 0.025
            else MAX_IMAGE_TOKENS_PER_PAGE
        )

        resized = resize_to_token_cap(cropped, token_cap)

        pages.append({
            "page_number": page_number,
            "image": resized,
            "estimated_visual_tokens": visual_tokens(resized),
        })

        log.append({
            "page": page_number,
            "skipped": False,
            "ink_ratio": ratio,
            "crop": crop,
            "original_size": list(original.size),
            "processed_size": list(resized.size),
            "estimated_visual_tokens": visual_tokens(resized),
        })

    if not pages:
        raise RuntimeError("All pages were considered blank.")

    return pages, log


def image_to_base64(image: Image.Image) -> str:
    buffer = io.BytesIO()
    image.save(
        buffer,
        format="JPEG",
        quality=JPEG_QUALITY,
        optimize=True,
        progressive=True,
    )
    return base64.b64encode(buffer.getvalue()).decode("utf-8")


def save_previews(
    pages: List[Dict[str, Any]],
    preview_folder: Path,
) -> None:
    if not SAVE_PREVIEWS:
        return

    preview_folder.mkdir(parents=True, exist_ok=True)

    for page in pages[:PREVIEW_MAX_PAGES]:
        image = page["image"].copy()
        image.thumbnail((1000, 1400), Image.Resampling.LANCZOS)
        image.save(
            preview_folder / f"page_{page['page_number']:04d}.jpg",
            quality=90,
        )


# ============================================================
# Build Message Batch requests
# ============================================================

def create_request_params(
    model: str,
    pages: List[Dict[str, Any]],
) -> MessageCreateParamsNonStreaming:
    page_numbers = [page["page_number"] for page in pages]

    content: List[Dict[str, Any]] = [{
        "type": "text",
        "text": (
            f"Extract all questions. Original PDF pages: {page_numbers}. "
            "Return compact JSON only."
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

    return MessageCreateParamsNonStreaming(
        model=model,
        max_tokens=MAX_OUTPUT_TOKENS,
        system=[{
            "type": "text",
            "text": SYSTEM_PROMPT,
            "cache_control": {"type": "ephemeral"},
        }],
        messages=[{
            "role": "user",
            "content": content,
        }],
    )


def request_payload_size(params: MessageCreateParamsNonStreaming) -> int:
    """
    Approximate JSON payload size, mostly driven by base64 image strings.
    """
    try:
        return len(json.dumps(params, default=lambda value: value.__dict__))
    except Exception:
        return 0


def split_request_chunks(
    requests: List[Request],
    request_sizes: List[int],
) -> List[List[Request]]:
    limit = MAX_BATCH_PAYLOAD_MB * 1024 * 1024
    chunks: List[List[Request]] = []
    current: List[Request] = []
    current_size = 0

    for request, size in zip(requests, request_sizes):
        if current and current_size + size > limit:
            chunks.append(current)
            current = []
            current_size = 0

        current.append(request)
        current_size += size

    if current:
        chunks.append(current)

    return chunks


# ============================================================
# Batch submission and result retrieval
# ============================================================

def submit_and_wait(
    client: Anthropic,
    requests: List[Request],
    chunk_number: int,
    total_chunks: int,
) -> List[Any]:
    print(
        f"\n🚀 Submitting API batch {chunk_number}/{total_chunks} "
        f"with {len(requests)} requests..."
    )

    batch = client.messages.batches.create(requests=requests)
    print(f"   Batch ID: {batch.id}")

    while True:
        status = client.messages.batches.retrieve(batch.id)

        counts = status.request_counts
        print(
            "   ⏳ "
            f"processing={counts.processing}, "
            f"succeeded={counts.succeeded}, "
            f"errored={counts.errored}, "
            f"expired={counts.expired}",
            end="\r",
        )

        if status.processing_status == "ended":
            print()
            break

        time.sleep(POLL_SECONDS)

    return list(client.messages.batches.results(batch.id))


# ============================================================
# Parsing
# ============================================================

def response_text(message: Any) -> str:
    return "\n".join(
        getattr(block, "text", "")
        for block in message.content
        if getattr(block, "type", "") == "text"
        and getattr(block, "text", "")
    ).strip()


def parse_json_object(text: str) -> Dict[str, Any]:
    cleaned = text.strip()
    cleaned = re.sub(r"^```(?:json)?\s*", "", cleaned, flags=re.I)
    cleaned = re.sub(r"\s*```$", "", cleaned)

    start = cleaned.find("{")
    end = cleaned.rfind("}")

    if start == -1 or end == -1 or end <= start:
        raise ValueError("No JSON object found.")

    return json.loads(cleaned[start:end + 1])


def normalize_question(question: Dict[str, Any]) -> Dict[str, Any]:
    pages = question.get("source_pages", [])
    if not isinstance(pages, list):
        pages = [pages] if pages not in ("", None) else []

    normalized_pages = []
    for value in pages:
        try:
            normalized_pages.append(int(value))
        except (ValueError, TypeError):
            pass

    marks = question.get("marks")
    if marks == "":
        marks = None

    return {
        "number": str(question.get("number", "")).strip(),
        "question_type": str(
            question.get("question_type", "unknown")
        ).strip(),
        "question_text": str(
            question.get("question_text", "") or ""
        ).strip(),
        "marks": marks,
        "option_a": str(question.get("option_a", "") or "").strip(),
        "option_b": str(question.get("option_b", "") or "").strip(),
        "option_c": str(question.get("option_c", "") or "").strip(),
        "option_d": str(question.get("option_d", "") or "").strip(),
        "option_e": str(question.get("option_e", "") or "").strip(),
        "diagram_required": bool(question.get("diagram_required", False)),
        "diagram_description": str(
            question.get("diagram_description", "") or ""
        ).strip(),
        "image_regions": question.get("image_regions", [])
        if isinstance(question.get("image_regions", []), list)
        else [],
        "source_pages": normalized_pages,
    }


def question_sort_key(question: Dict[str, Any]) -> Tuple[int, int, str]:
    number = str(question.get("number", ""))
    match = re.search(r"\d+", number)

    if match:
        return (0, int(match.group()), number)

    pages = question.get("source_pages", [])
    first_page = pages[0] if pages else 999999
    return (1, first_page, number)


def deduplicate_questions(
    questions: List[Dict[str, Any]],
) -> List[Dict[str, Any]]:
    return merge_question_fragments(questions)


# ============================================================
# Output
# ============================================================

CSV_FIELDS = [
    "number",
    "question_type",
    "question_text",
    "marks",
    "option_a",
    "option_b",
    "option_c",
    "option_d",
    "option_e",
    "diagram_required",
    "diagram_description",
    "source_pages",
]


def save_csv(questions: List[Dict[str, Any]], path: Path) -> None:
    with path.open("w", encoding="utf-8-sig", newline="") as file:
        writer = csv.DictWriter(file, fieldnames=CSV_FIELDS)
        writer.writeheader()

        for question in questions:
            row = {field: question.get(field, "") for field in CSV_FIELDS}
            row["source_pages"] = ",".join(
                str(page) for page in question["source_pages"]
            )
            writer.writerow(row)


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
    image_folder = output_root if contract_mode else paper_dir / "images"
    image_prefix = public_prefix if contract_mode else "images"
    extract_question_images(
        paper["pdf_path"], questions, image_folder, image_prefix,
        poppler_path, pdf_to_images,
    )
    paper["final_questions"] = questions
    usage = paper["usage"]
    estimated_cost = (
        usage["input_tokens"] / 1_000_000 * BATCH_INPUT_PRICE_PER_MTOK
        + usage["output_tokens"] / 1_000_000 * BATCH_OUTPUT_PRICE_PER_MTOK
    )
    result = {
        "source_file": str(paper["pdf_path"].resolve()),
        "provider": "Anthropic Message Batches API",
        "model": model,
        "math_format": "MathJax-compatible TeX source",
        "total_pages": paper["total_pages"],
        "processed_pages": paper["processed_pages"],
        "total_questions": len(questions),
        "usage": usage,
        "estimated_batch_cost_usd": round(estimated_cost, 6),
        "questions": questions,
        "errors": paper["errors"],
    }

    if not contract_mode:
        (paper_dir / "exam_questions.json").write_text(
            json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8"
        )
        save_csv(questions, paper_dir / "exam_questions.csv")
        (paper_dir / "raw_outputs.txt").write_text(
            "\n\n".join(paper["raw_outputs"]), encoding="utf-8"
        )
        (paper_dir / "processing_report.json").write_text(
            json.dumps({
                "source_file": str(paper["pdf_path"].resolve()),
                "page_processing": paper["processing_log"],
                "usage": usage,
                "estimated_batch_cost_usd": round(estimated_cost, 6),
                "errors": paper["errors"],
            }, ensure_ascii=False, indent=2),
            encoding="utf-8",
        )

    return {
        "file": paper["pdf_path"].name,
        "questions": len(questions),
        "input_tokens": usage["input_tokens"],
        "output_tokens": usage["output_tokens"],
        "estimated_cost_usd": round(estimated_cost, 6),
        "errors": len(paper["errors"]),
        "output_folder": str(image_folder if contract_mode else paper_dir),
    }


# ============================================================
# Main folder workflow
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
    print("💰 Using Message Batches API: standard model quality, 50% API discount")

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
        final_json = output_folder / output_name / "exam_questions.json"

        if result_file is None and SKIP_EXISTING and final_json.exists():
            print(f"⏭️ Skipping existing output: {pdf_path.name}")
            continue

        print(f"\n[{file_index}/{len(pdfs)}] {pdf_path.name}")

        try:
            original_images = pdf_to_images(pdf_path, poppler_path)
            pages, processing_log = prepare_pages(original_images)

            paper_key = f"f{file_index:04d}"
            papers[paper_key] = {
                "pdf_path": pdf_path,
                "output_name": output_name,
                "total_pages": len(original_images),
                "processed_pages": len(pages),
                "processing_log": processing_log,
                "questions": [],
                "raw_outputs": [],
                "errors": [],
                "usage": {"input_tokens": 0, "output_tokens": 0},
            }

            if result_file is None:
                save_previews(
                    pages,
                    output_folder / output_name / "processed_page_previews",
                )

            for batch_index in range(0, len(pages), PAGE_BATCH_SIZE):
                page_batch = pages[batch_index:batch_index + PAGE_BATCH_SIZE]
                batch_number = batch_index // PAGE_BATCH_SIZE + 1
                custom_id = f"{paper_key}_b{batch_number:04d}"

                params = create_request_params(model, page_batch)
                request = Request(custom_id=custom_id, params=params)

                requests.append(request)
                request_sizes.append(request_payload_size(params))
                request_map[custom_id] = {
                    "paper_key": paper_key,
                    "batch_number": batch_number,
                    "page_numbers": [
                        page["page_number"] for page in page_batch
                    ],
                }

        except Exception as exc:
            print(f"❌ Could not prepare {pdf_path.name}: {exc}")

    if not requests:
        print("No new requests to process.")
        return

    request_chunks = split_request_chunks(requests, request_sizes)
    all_results: List[Any] = []

    for chunk_number, chunk in enumerate(request_chunks, start=1):
        all_results.extend(
            submit_and_wait(
                client,
                chunk,
                chunk_number,
                len(request_chunks),
            )
        )

    for result in all_results:
        custom_id = result.custom_id
        metadata = request_map.get(custom_id)

        if not metadata:
            continue

        paper = papers[metadata["paper_key"]]

        if result.result.type != "succeeded":
            paper["errors"].append({
                "batch": metadata["batch_number"],
                "pages": metadata["page_numbers"],
                "error_type": result.result.type,
                "details": str(result.result),
            })
            continue

        message = result.result.message
        text = response_text(message)
        paper["raw_outputs"].append(
            f"===== BATCH {metadata['batch_number']} "
            f"PAGES {metadata['page_numbers']} =====\n{text}"
        )

        usage = getattr(message, "usage", None)
        paper["usage"]["input_tokens"] += int(
            getattr(usage, "input_tokens", 0) or 0
        )
        paper["usage"]["output_tokens"] += int(
            getattr(usage, "output_tokens", 0) or 0
        )

        try:
            parsed = parse_json_object(text)
            batch_questions = parsed.get("questions", [])

            if not isinstance(batch_questions, list):
                raise ValueError("'questions' is not a list.")

            paper["questions"].extend(
                normalize_question(question)
                for question in batch_questions
                if isinstance(question, dict)
            )
        except Exception as exc:
            paper["errors"].append({
                "batch": metadata["batch_number"],
                "pages": metadata["page_numbers"],
                "error_type": "json_parse_error",
                "details": str(exc),
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
        questions = []
        errors = []
        for paper in papers.values():
            questions.extend(
                application_question_rows(paper.get("final_questions", []))
            )
            errors.extend(paper.get("errors", []))
        result_file.parent.mkdir(parents=True, exist_ok=True)
        temporary = result_file.with_suffix(result_file.suffix + ".tmp")
        temporary.write_text(json.dumps({
            "ok": True,
            "questions": questions,
            "errors": errors,
            "provider": "Anthropic Message Batches API",
            "model": model,
        }, ensure_ascii=False), encoding="utf-8")
        temporary.replace(result_file)
        return

    summary_path = output_folder / "folder_summary.csv"
    summary_fields = [
        "file",
        "questions",
        "input_tokens",
        "output_tokens",
        "estimated_cost_usd",
        "errors",
        "output_folder",
    ]

    with summary_path.open("w", encoding="utf-8-sig", newline="") as file:
        writer = csv.DictWriter(file, fieldnames=summary_fields)
        writer.writeheader()
        writer.writerows(summary_rows)

    totals = {
        "files": len(summary_rows),
        "questions": sum(row["questions"] for row in summary_rows),
        "input_tokens": sum(row["input_tokens"] for row in summary_rows),
        "output_tokens": sum(row["output_tokens"] for row in summary_rows),
        "estimated_cost_usd": round(
            sum(row["estimated_cost_usd"] for row in summary_rows),
            6,
        ),
        "errors": sum(row["errors"] for row in summary_rows),
        "model": model,
        "pricing": {
            "batch_input_per_million_usd": BATCH_INPUT_PRICE_PER_MTOK,
            "batch_output_per_million_usd": BATCH_OUTPUT_PRICE_PER_MTOK,
        },
    }

    (output_folder / "folder_summary.json").write_text(
        json.dumps(totals, indent=2),
        encoding="utf-8",
    )

    print("\n✅ Folder extraction completed")
    print(f"📄 Files: {totals['files']}")
    print(f"📊 Questions: {totals['questions']}")
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
                    result_path = Path(
                        sys.argv[sys.argv.index("--result-file") + 1]
                    )
                    result_path.parent.mkdir(parents=True, exist_ok=True)
                    result_path.write_text(
                        json.dumps(error_payload, ensure_ascii=False),
                        encoding="utf-8",
                    )
                except Exception:
                    pass
            print(json.dumps(error_payload, ensure_ascii=False))
            sys.exit(2)
    else:
        print("=" * 76)
        print("📚 ANTHROPIC FOLDER EXAM EXTRACTOR")
        print("MESSAGE BATCHES + CSV/JSON + QUESTION/OPTION IMAGES")
        print("=" * 76)
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
