"""ExamElite contract adapter for the final menhadi/pdf-extraction scripts."""

from __future__ import annotations

import argparse
import csv
import html
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path
from urllib.parse import urlparse



LAYOUTS = {
    "auto": {
        "label": "Unified automatic PDF layout detection",
        "detector": None,
        "extract": None,
    },
    "text_math_pdf": {
        "label": "Standard selectable text/math PDF",
        "detector": "text_math_pdf",
        "extract": "pdf_standard_extractor:extract_pdf_standard",
    },
    "jee_nta_image": {
        "label": "JEE/NTA image PDF",
        "detector": "jee_nta_image",
        "extract": "jee_standard_extractor:extract_jee_standard",
    },
    "cuetug": {
        "label": "CUET-UG image PDF",
        "detector": "cuetug",
        "extract": "cuetug_image_pdf_extractor:extract_cuetug_pdf",
    },
    "ugcnet_bilingual": {
        "label": "UGC-NET bilingual PDF",
        "detector": "ugcnet_bilingual",
        "extract": "ugcnet_standard_extractor:extract_ugcnet_standard",
    },
    "gate_pdf": {
        "label": "GATE PDF",
        "detector": "gate_pdf",
        "extract": "gate_standard_extractor:extract_gate_standard",
    },
    "upsc_bilingual": {
        "label": "UPSC 2025 bilingual scan",
        "detector": None,
        "extract": "upsc_bilingual_extractor:extract_upsc_bilingual",
    },
}

IMAGE_MARKER = re.compile(r"\[image:\s*([^\]]+)\]", re.IGNORECASE)
IMAGE_TAG = re.compile(
    r"<img\b[^>]*\bsrc=[\"']([^\"']+)[\"'][^>]*>",
    re.IGNORECASE,
)
IMAGE_SUFFIXES = {
    ".avif", ".bmp", ".gif", ".jpeg", ".jpg", ".png", ".svg", ".tif",
    ".tiff", ".webp",
}
ENGINE_ROOT = Path(__file__).resolve().parents[2] / "pdf-extraction-engine"


def _probe(pdf_path: Path, layout_key: str) -> dict:
    if pdf_path.suffix.lower() != ".pdf":
        raise RuntimeError(f"{LAYOUTS[layout_key]['label']} accepts PDF files only.")

    if layout_key == "auto":
        return {
            "extractor": "auto",
            "selection": "automatic",
            "reasons": ["layout detection is delegated to the pinned repository"],
        }

    return {
        "extractor": layout_key,
        "selection": "manual",
        "reasons": ["running the administrator-selected extractor without substitution"],
    }


def _read_csv(path: Path) -> list[dict]:
    with path.open("r", encoding="utf-8-sig", newline="") as source:
        return list(csv.DictReader(source))


def _copy_images(images_dir: Path, output_dir: Path) -> dict[str, str]:
    output_dir.mkdir(parents=True, exist_ok=True)
    copied = {}
    if not images_dir.is_dir():
        return copied
    for source in images_dir.rglob("*"):
        if not source.is_file() or source.suffix.lower() not in IMAGE_SUFFIXES:
            continue
        if source.name in copied and copied[source.name] != str(source):
            raise RuntimeError(
                f"The tested extraction engine returned duplicate image filename "
                f"'{source.name}'. No drafts were created."
            )
        target = output_dir / source.name
        shutil.copy2(source, target)
        copied[source.name] = str(source)
    return copied


def _image_html(filename: str, public_prefix: str, paper_code: str) -> str:
    prefix = public_prefix.rstrip("/")
    src = f"{prefix}/{filename}" if prefix else filename
    alt = f"{paper_code} extracted question image"
    return (
        f'<img src="{html.escape(src, quote=True)}" '
        f'alt="{html.escape(alt, quote=True)}" class="img-fluid" loading="lazy">'
    )


def _rewrite_images(
    content: str,
    image_list: str,
    copied: dict[str, str],
    public_prefix: str,
    paper_code: str,
) -> tuple[str, list[str]]:
    used = []

    def filename_for(value: str) -> str:
        parsed = urlparse(value.strip().replace("\\", "/"))
        return Path(parsed.path or value).name

    def replace(match):
        filename = filename_for(match.group(1))
        if filename not in copied:
            raise RuntimeError(f"Extracted image is missing: {filename}")
        used.append(filename)
        return _image_html(filename, public_prefix, paper_code)

    rewritten = IMAGE_TAG.sub(replace, content or "")
    rewritten = IMAGE_MARKER.sub(replace, rewritten)
    listed = [
        filename_for(value)
        for value in (image_list or "").split(";")
        if value.strip()
    ]
    for filename in listed:
        if filename not in copied:
            raise RuntimeError(f"Extracted image is missing: {filename}")
        if filename not in used:
            rewritten = (
                rewritten + "\n" + _image_html(filename, public_prefix, paper_code)
            ).strip()
            used.append(filename)
    return rewritten.strip(), used


def _join_content(*parts: str) -> str:
    return "\n".join(part.strip() for part in parts if part and part.strip()).strip()


def _bilingual(primary: str, secondary: str) -> str:
    if not secondary:
        return primary
    return _join_content(primary, f'<div lang="hi">{secondary}</div>')


def _answer_map(answer_pdf: str | None, work_dir: Path) -> dict[str, dict]:
    if not answer_pdf:
        return {}
    engine_script = ENGINE_ROOT / "answer_key_extractor.py"
    if not engine_script.is_file():
        raise RuntimeError(
            "The pinned pdf-extraction repository is unavailable. Run "
            "'git submodule update --init --recursive' on the server."
        )
    output_dir = work_dir / "answers"
    process = subprocess.run(
        [
            sys.executable,
            str(engine_script),
            "--pdf",
            str(Path(answer_pdf).resolve()),
            "--out",
            str(output_dir),
        ],
        cwd=ENGINE_ROOT,
        capture_output=True,
        text=True,
        timeout=1800,
        check=False,
    )
    if process.returncode != 0:
        diagnostics = "\n".join(
            value.strip() for value in (process.stdout, process.stderr) if value.strip()
        )
        raise RuntimeError(
            "The tested answer-key extractor failed."
            + (f"\n{diagnostics}" if diagnostics else "")
        )
    json_path = output_dir / "answer_keys.json"
    if not json_path.is_file():
        raise RuntimeError(
            "The tested answer-key extractor completed without answer_keys.json."
        )
    payload = json.loads(json_path.read_text(encoding="utf-8"))
    return {
        str(row.get("question_no", "")).strip(): row
        for row in payload.get("answers", [])
        if str(row.get("question_no", "")).strip()
    }


def _answer_fields(answer: dict | None) -> dict:
    if not answer:
        return {}
    question_type = str(answer.get("question_type", "")).upper()
    if question_type == "MSQ":
        values = [
            value.strip()
            for value in str(answer.get("correct_options", "")).split(";")
            if value.strip()
        ]
        return {"correct_answers": values}
    if question_type == "NAT":
        lower = str(answer.get("answer_min", "")).strip()
        upper = str(answer.get("answer_max", "")).strip()
        if lower and upper:
            if lower == upper:
                return {
                    "correct_answer": lower,
                    "nat_config": {"version": 1, "mode": "exact", "value": float(lower)},
                }
            return {
                "nat_config": {
                    "version": 1,
                    "mode": "range",
                    "min": float(lower),
                    "max": float(upper),
                }
            }
    correct = str(answer.get("correct_option", "")).strip()
    return {"correct_answer": correct} if correct else {}


def _extractor_metadata(row: dict) -> dict:
    consumed = {
        "question_no", "question_type", "marks", "negative_marks",
        "source_pages", "correct_answer", "correct_answers", "correct_options",
        "explanation",
    }
    for field in (
        "passage", "passage_hindi", "question", "question_hindi",
        "option1", "option2", "option3", "option4",
        "option1_hindi", "option2_hindi", "option3_hindi", "option4_hindi",
    ):
        consumed.add(field)
        consumed.add(f"{field}_images")

    raw_metadata = row.get("metadata_json")
    if isinstance(raw_metadata, str) and raw_metadata.strip():
        try:
            script_metadata = json.loads(raw_metadata)
        except json.JSONDecodeError:
            script_metadata = {"raw_metadata_json": raw_metadata}
    elif isinstance(raw_metadata, dict):
        script_metadata = dict(raw_metadata)
    elif raw_metadata not in (None, "", []):
        script_metadata = {"metadata_json": raw_metadata}
    else:
        script_metadata = {}

    extra_fields = {
        key: value
        for key, value in row.items()
        if key not in consumed
        and key != "metadata_json"
        and value not in (None, "", [], {})
    }
    if extra_fields:
        existing = script_metadata.get("extra_fields", {})
        script_metadata["extra_fields"] = {**existing, **extra_fields}
    return script_metadata


def _convert_rows(
    rows: list[dict],
    answers: dict[str, dict],
    copied: dict[str, str],
    public_prefix: str,
    paper_code: str,
    solution_available: bool,
) -> list[dict]:
    converted = []
    seen_numbers = set()
    for index, row in enumerate(rows, start=1):
        number = str(row.get("question_no") or index).strip()
        if number in seen_numbers:
            raise RuntimeError(
                f"The selected extractor returned duplicate question number {number}. "
                "No drafts were created."
            )
        seen_numbers.add(number)
        content = {}
        images = []
        for field in (
            "passage",
            "passage_hindi",
            "question",
            "question_hindi",
            "option1",
            "option2",
            "option3",
            "option4",
            "option1_hindi",
            "option2_hindi",
            "option3_hindi",
            "option4_hindi",
        ):
            rewritten, field_images = _rewrite_images(
                row.get(field, ""),
                row.get(f"{field}_images", ""),
                copied,
                public_prefix,
                paper_code,
            )
            content[field] = rewritten
            images.extend(field_images)

        question = _bilingual(
            _join_content(content["passage"], content["question"]),
            _join_content(content["passage_hindi"], content["question_hindi"]),
        )
        options = [
            _bilingual(content[f"option{option}"], content[f"option{option}_hindi"])
            for option in range(1, 5)
        ]
        options = [option for option in options if option]
        page_values = [
            value.strip()
            for value in re.split(r"[;,]", row.get("source_pages", ""))
            if value.strip()
        ]
        pages = [int(value) if value.isdigit() else value for value in page_values]
        question_type = str(row.get("question_type", "")).strip() or (
            "MCQ" if len(options) >= 2 else "NAT"
        )
        if not question:
            raise RuntimeError(
                f"The selected extractor returned no question content for question "
                f"{number}. No drafts were created."
            )
        item = {
            "paper_question_number": index,
            "printed_question_number": number,
            "question": question,
            "options": options,
            "question_type": question_type,
            "marks": row.get("marks") or None,
            "negative_marks": row.get("negative_marks") or None,
            "explanation": row.get("explanation") or None,
            "source_pages": pages,
            "solution_source_available": solution_available,
            "extracted_images": [
                f"{public_prefix.rstrip('/')}/{filename}"
                if public_prefix
                else filename
                for filename in sorted(set(images))
            ],
            "extractor_metadata": _extractor_metadata(row),
        }
        if row.get("correct_answer"):
            item["correct_answer"] = row["correct_answer"]
        if row.get("correct_answers") or row.get("correct_options"):
            item["correct_answers"] = row.get("correct_answers") or row.get("correct_options")
        item.update(_answer_fields(answers.get(number)))
        converted.append(item)
    return converted



def _absolute_delivery_prefix(public_prefix: str) -> str:
    value = public_prefix.strip()
    if value.lower().startswith("https://"):
        return value.rstrip("/") + "/"
    origin = os.environ.get("EXAMELITE_PUBLIC_ORIGIN", "").strip().rstrip("/")
    if not origin.lower().startswith("https://"):
        raise RuntimeError(
            "EXAMELITE_PUBLIC_ORIGIN must be an HTTPS URL when running the "
            "tested pdf-extraction repository."
        )
    return origin + "/" + value.lstrip("/").rstrip("/") + "/"


def _run_repository_batch(
    question_path: Path,
    layout_key: str,
    work_dir: Path,
    public_prefix: str,
) -> tuple[Path, Path, dict]:
    engine_script = ENGINE_ROOT / "batch_extract.py"
    if not engine_script.is_file():
        raise RuntimeError(
            "The pinned pdf-extraction repository is unavailable. Run "
            "'git submodule update --init --recursive' on the server."
        )

    input_root = work_dir / "input"
    output_root = work_dir / "output"
    input_root.mkdir(parents=True, exist_ok=True)
    staged_pdf = input_root / question_path.name
    shutil.copy2(question_path, staged_pdf)
    process = subprocess.run(
        [
            sys.executable,
            str(engine_script),
            "--input-root",
            str(input_root),
            "--output-root",
            str(output_root),
            "--workers",
            "1",
            "--extractor",
            layout_key,
            "--ai-math",
            "auto",
            "--cdn-prefix",
            _absolute_delivery_prefix(public_prefix),
        ],
        cwd=ENGINE_ROOT,
        capture_output=True,
        text=True,
        timeout=1800,
        check=False,
    )
    report_path = output_root / "_batch" / "batch_report.json"
    report = {}
    if report_path.is_file():
        report = json.loads(report_path.read_text(encoding="utf-8"))
    if process.returncode != 0:
        error = ""
        results = report.get("results", []) if isinstance(report, dict) else []
        if results:
            error = str(results[0].get("error", "")).strip()
        diagnostics = "\n".join(
            value.strip() for value in (error, process.stdout, process.stderr)
            if value and value.strip()
        )
        raise RuntimeError(
            "The tested pdf-extraction repository failed."
            + (f"\n{diagnostics}" if diagnostics else "")
        )

    csv_path = output_root / "questions.csv"
    if not csv_path.is_file():
        raise RuntimeError(
            "The tested pdf-extraction repository completed without questions.csv."
        )
    return csv_path, output_root, report

def run(layout_key: str, argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=LAYOUTS[layout_key]["label"])
    parser.add_argument("--questions", required=True)
    parser.add_argument("--answers")
    parser.add_argument("--solutions")
    parser.add_argument("--out", required=True)
    parser.add_argument("--public-prefix", default="")
    parser.add_argument("--paper-code", required=True)
    parser.add_argument("--profile", default="{}")
    parser.add_argument("--result-file", required=True)
    parser.add_argument("--probe", action="store_true")
    args = parser.parse_args(argv)

    result_path = Path(args.result_file).resolve()
    result_path.parent.mkdir(parents=True, exist_ok=True)
    try:
        question_path = Path(args.questions).resolve()
        probe = _probe(question_path, layout_key)
        if args.probe:
            result_path.write_text(
                json.dumps({"ok": True, "probe": probe}, ensure_ascii=False, indent=2),
                encoding="utf-8",
            )
            return 0

        output_dir = Path(args.out).resolve()
        with tempfile.TemporaryDirectory(prefix="examelite-pdf-extractor-") as temporary:
            work_dir = Path(temporary)
            answers = _answer_map(args.answers, work_dir)
            csv_path, extraction_root, batch_report = _run_repository_batch(
                question_path,
                layout_key,
                work_dir,
                args.public_prefix,
            )
            rows = _read_csv(csv_path)
            copied = _copy_images(extraction_root, output_dir)
            questions = _convert_rows(
                rows,
                answers,
                copied,
                args.public_prefix,
                args.paper_code,
                bool(args.solutions),
            )
            if not questions:
                raise RuntimeError(
                    "The selected extractor returned zero questions. No drafts were created."
                )
            batch_results = batch_report.get("results", [])
            selected_layout = (
                batch_results[0].get("extractor")
                if batch_results
                else layout_key
            )
            result = {
                "ok": True,
                "extractor": selected_layout,
                "requested_extractor": layout_key,
                "probe": probe,
                "engine": {
                    "repository": "menhadi/pdf-extraction",
                    "commit": "65af308",
                    "entrypoint": "batch_extract.py",
                },
                "questions": questions,
            }
            result_path.write_text(
                json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8"
            )
        return 0
    except Exception as error:
        result_path.write_text(
            json.dumps({"ok": False, "error": str(error)}, ensure_ascii=False, indent=2),
            encoding="utf-8",
        )
        raise
