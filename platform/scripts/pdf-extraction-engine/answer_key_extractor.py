"""Extract structured answer keys from tabular exam answer-key PDFs."""

import argparse
import csv
import hashlib
import json
import re
from datetime import datetime, timezone
from decimal import Decimal, InvalidOperation
from pathlib import Path

import fitz


ANSWER_KEY_FIELDS = [
    "document_id",
    "source_filename",
    "source_sha256",
    "extractor_type",
    "exam_name",
    "exam_year",
    "paper_code",
    "session",
    "question_no",
    "question_id",
    "question_type",
    "section",
    "correct_option",
    "correct_options",
    "answer_min",
    "answer_max",
    "key_or_range_raw",
    "marks",
    "source_page",
    "metadata_json",
]

HEADER_MAP = {
    "qno": "Q. No.",
    "session": "Session",
    "question_type": "Q. Type",
    "section": "Section",
    "key": "Key/Range",
    "marks": "Marks",
}
REQUIRED_HEADER = list(HEADER_MAP.values())
RANGE_RE = re.compile(
    r"^\s*([+\-−]?\d+(?:\.\d+)?)\s+to\s+([+\-−]?\d+(?:\.\d+)?)\s*$",
    re.IGNORECASE,
)


def file_sha256(path, chunk_size=1024 * 1024):
    digest = hashlib.sha256()
    with Path(path).open("rb") as source:
        for chunk in iter(lambda: source.read(chunk_size), b""):
            digest.update(chunk)
    return digest.hexdigest()


def infer_document_metadata(pdf_path, pdf_metadata, page_text):
    combined = " ".join([
        str(pdf_metadata.get("title", "")),
        str(pdf_metadata.get("subject", "")),
        str(page_text or ""),
        Path(pdf_path).stem,
    ])
    year_match = re.search(r"\b(?:GATE\s*)?(20\d{2})\b", combined, re.IGNORECASE)
    paper_match = re.search(
        r"Answer\s+Key\s+for\s+.+?\(([A-Z][A-Z0-9]{1,5})\)",
        combined,
        re.IGNORECASE,
    )
    return {
        "exam_name": "GATE" if re.search(r"\bGATE(?:\s*20\d{2})?\b", combined, re.IGNORECASE) else "",
        "exam_year": year_match.group(1) if year_match else "",
        "paper_code": paper_match.group(1).upper() if paper_match else "",
    }


def decimal_text(value):
    value = str(value or "").strip().replace("−", "-")
    try:
        Decimal(value)
    except InvalidOperation as exc:
        raise ValueError(f"Invalid numerical answer value: {value}") from exc
    return value


def normalize_answer(question_type, raw_key):
    question_type = str(question_type or "").strip().upper()
    raw_key = str(raw_key or "").strip()
    result = {
        "correct_option": "",
        "correct_options": "",
        "answer_min": "",
        "answer_max": "",
    }
    if question_type == "MCQ":
        option = raw_key.upper()
        if not re.fullmatch(r"[A-D]", option):
            raise ValueError(f"Invalid MCQ key: {raw_key}")
        result["correct_option"] = option
    elif question_type == "MSQ":
        options = [item.strip().upper() for item in re.split(r"[;,]+", raw_key) if item.strip()]
        if not options or any(not re.fullmatch(r"[A-D]", item) for item in options):
            raise ValueError(f"Invalid MSQ key: {raw_key}")
        if len(options) != len(set(options)):
            raise ValueError(f"Duplicate option in MSQ key: {raw_key}")
        result["correct_options"] = ";".join(options)
    elif question_type == "NAT":
        match = RANGE_RE.fullmatch(raw_key)
        if not match:
            raise ValueError(f"Invalid NAT range: {raw_key}")
        lower, upper = decimal_text(match.group(1)), decimal_text(match.group(2))
        if Decimal(lower) > Decimal(upper):
            raise ValueError(f"NAT range is reversed: {raw_key}")
        result["answer_min"] = lower
        result["answer_max"] = upper
    else:
        raise ValueError(f"Unsupported question type: {question_type}")
    return result


def table_rows(page):
    tables = page.find_tables().tables
    if not tables:
        raise ValueError(f"No answer-key table found on page {page.number + 1}")
    extracted = []
    for table in tables:
        extracted.extend(table.extract())
    return extracted


def column_indexes(first_row):
    normalized = [re.sub(r"\s+", " ", str(value or "")).strip() for value in first_row]
    if normalized[: len(REQUIRED_HEADER)] != REQUIRED_HEADER:
        return None
    return {key: normalized.index(label) for key, label in HEADER_MAP.items()}


def parse_answer_key_pdf(pdf_path):
    pdf_path = Path(pdf_path).resolve()
    source_hash = file_sha256(pdf_path)
    document_id = f"answer_key_{source_hash[:20]}"
    rows = []
    indexes = None
    with fitz.open(pdf_path) as document:
        first_text = document[0].get_text("text") if document.page_count else ""
        metadata = infer_document_metadata(pdf_path, document.metadata, first_text)
        for page in document:
            raw_rows = table_rows(page)
            if not raw_rows:
                continue
            page_indexes = column_indexes(raw_rows[0])
            if page_indexes:
                indexes = page_indexes
                raw_rows = raw_rows[1:]
            if indexes is None:
                raise ValueError(
                    f"Answer-key table header was not found before page {page.number + 1}"
                )
            for raw_row in raw_rows:
                values = [str(value or "").strip() for value in raw_row]
                if not values or not values[indexes["qno"]].isdigit():
                    continue
                question_no = str(int(values[indexes["qno"]]))
                question_type = values[indexes["question_type"]].upper()
                raw_key = values[indexes["key"]]
                normalized_answer = normalize_answer(question_type, raw_key)
                row = {
                    "document_id": document_id,
                    "source_filename": pdf_path.name,
                    "source_sha256": source_hash,
                    "extractor_type": "answer_key_pdf",
                    **metadata,
                    "session": values[indexes["session"]],
                    "question_no": question_no,
                    "question_id": "",
                    "question_type": question_type,
                    "section": values[indexes["section"]],
                    **normalized_answer,
                    "key_or_range_raw": raw_key,
                    "marks": values[indexes["marks"]],
                    "source_page": str(page.number + 1),
                    "metadata_json": json.dumps({
                        "pdf_title": document.metadata.get("title", ""),
                        "pdf_author": document.metadata.get("author", ""),
                        "pdf_subject": document.metadata.get("subject", ""),
                    }, ensure_ascii=False, sort_keys=True),
                }
                rows.append(row)

    numbers = [int(row["question_no"]) for row in rows]
    if not rows:
        raise ValueError("No answer-key rows were extracted")
    if len(numbers) != len(set(numbers)):
        raise ValueError("Duplicate question numbers in answer key")
    missing = sorted(set(range(min(numbers), max(numbers) + 1)) - set(numbers))
    if missing:
        raise ValueError("Missing answer-key question numbers: " + ", ".join(map(str, missing)))
    rows.sort(key=lambda row: int(row["question_no"]))
    return rows


def write_answer_key_outputs(pdf_path, output_dir):
    pdf_path = Path(pdf_path).resolve()
    output_dir = Path(output_dir).resolve()
    output_dir.mkdir(parents=True, exist_ok=True)
    rows = parse_answer_key_pdf(pdf_path)
    csv_path = output_dir / "answer_keys.csv"
    with csv_path.open("w", encoding="utf-8-sig", newline="") as target:
        writer = csv.DictWriter(target, fieldnames=ANSWER_KEY_FIELDS, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)
    payload = {
        "source_pdf": str(pdf_path),
        "source_sha256": rows[0]["source_sha256"],
        "extractor_type": "answer_key_pdf",
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "answer_count": len(rows),
        "metadata": {
            "exam_name": rows[0]["exam_name"],
            "exam_year": rows[0]["exam_year"],
            "paper_code": rows[0]["paper_code"],
        },
        "answers": rows,
    }
    json_path = output_dir / "answer_keys.json"
    json_path.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")
    report = {
        "valid": True,
        "answer_count": len(rows),
        "question_types": {
            question_type: sum(row["question_type"] == question_type for row in rows)
            for question_type in ("MCQ", "MSQ", "NAT")
        },
        "sections": sorted(set(row["section"] for row in rows)),
    }
    report_path = output_dir / "answer_key_report.json"
    report_path.write_text(json.dumps(report, indent=2), encoding="utf-8")
    return csv_path, json_path, report_path, report


def main():
    parser = argparse.ArgumentParser(description="Extract a structured exam answer-key PDF.")
    parser.add_argument("--pdf", required=True)
    parser.add_argument("--out", required=True)
    args = parser.parse_args()
    csv_path, json_path, report_path, report = write_answer_key_outputs(args.pdf, args.out)
    print(f"Done. Extracted {report['answer_count']} answers.")
    print(f"CSV: {csv_path}")
    print(f"JSON: {json_path}")
    print(f"Report: {report_path}")


if __name__ == "__main__":
    main()