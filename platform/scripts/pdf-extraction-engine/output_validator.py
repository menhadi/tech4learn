import csv
import json
import re
from pathlib import Path

from extraction_contract import file_sha256


IMAGE_FIELDS = [
    "passage_images", "passage_hindi_images", "question_images", "question_hindi_images",
    "option1_images", "option2_images", "option3_images", "option4_images",
    "option1_hindi_images", "option2_hindi_images", "option3_hindi_images", "option4_hindi_images",
]


def _refs(value):
    return [item.strip() for item in (value or "").split(";") if item.strip()]


def validate_output(output_dir, output_root, extractor_type, strict_accuracy=False):
    output_dir = Path(output_dir)
    output_root = Path(output_root)
    errors, warnings = [], []
    csv_path = output_dir / "questions.csv"
    json_path = output_dir / "questions.json"
    image_manifest_path = output_dir / "images_manifest.csv"
    for required in (csv_path, json_path, image_manifest_path, output_dir / "extraction_manifest.json"):
        if not required.exists():
            errors.append(f"missing required file: {required.name}")
    if errors:
        return _write_report(output_dir, extractor_type, 0, errors, warnings)

    with csv_path.open(encoding="utf-8-sig", newline="") as source:
        rows = list(csv.DictReader(source))
    payload = json.loads(json_path.read_text(encoding="utf-8"))
    extraction_warnings = [str(item) for item in payload.get("warnings", []) if item]
    warnings.extend(extraction_warnings)
    if strict_accuracy:
        errors.extend(item for item in extraction_warnings if item.lower().startswith("missing question numbers"))
    if len(rows) != len(payload.get("questions", [])):
        errors.append("questions.csv row count does not match questions.json")
    if strict_accuracy and extractor_type == "gate_pdf":
        source_name = str(payload.get("document", {}).get("source_filename", ""))
        year_match = re.search(r"(20\d{2})", source_name)
        if year_match:
            year = int(year_match.group(1))
            expected = 85 if year <= 2008 else 60 if year == 2009 else 65
            if len(rows) != expected:
                errors.append(f"expected {expected} GATE questions for {year}, extracted {len(rows)}")
    question_ids = [row["question_id"] for row in rows if row.get("question_id")]
    if len(question_ids) != len(set(question_ids)):
        errors.append("duplicate question_id values")

    referenced = set()
    for row in rows:
        for field in IMAGE_FIELDS:
            for ref in _refs(row.get(field, "")):
                if Path(ref).is_absolute():
                    errors.append(f"absolute image path in CSV: {ref}")
                    continue
                referenced.add(ref)
                if not (output_root / Path(ref)).exists():
                    errors.append(f"missing referenced image: {ref}")

    with image_manifest_path.open(encoding="utf-8-sig", newline="") as source:
        images = list(csv.DictReader(source))
    names = [row["filename"] for row in images]
    if len(names) != len(set(names)):
        errors.append("duplicate image filenames in manifest")
    for image in images:
        path = output_root / Path(image["relative_path"])
        if not path.exists():
            errors.append(f"manifest image missing: {image['relative_path']}")
        elif image.get("sha256") and file_sha256(path) != image["sha256"]:
            errors.append(f"image hash mismatch: {image['relative_path']}")
    manifest_refs = {row["relative_path"] for row in images}
    for ref in sorted(referenced - manifest_refs):
        errors.append(f"CSV image absent from manifest: {ref}")

    if not rows:
        errors.append("no questions extracted")
    if extractor_type in {"cuetug", "jee_nta_image"}:
        for row in rows:
            missing = [f"option{i}" for i in range(1, 5) if not row.get(f"option{i}")]
            if missing:
                warnings.append(f"question {row.get('question_no')}: missing {', '.join(missing)}")
    if extractor_type == "ugcnet_bilingual":
        for row in rows:
            if not row.get("question") or not row.get("question_hindi"):
                warnings.append(f"question {row.get('question_no')}: incomplete bilingual question pair")
    if extractor_type == "upsc_bilingual":
        source_name = str(payload.get("document", {}).get("source_filename", ""))
        expected = 80 if re.search(r"Paper\s*[- ]*II\b", source_name, re.I) else 100
        if len(rows) != expected:
            errors.append(
                f"expected {expected} UPSC questions, extracted {len(rows)}"
            )
        for row in rows:
            missing = [
                field for field in (
                    "question", "question_hindi",
                    "option1", "option2", "option3", "option4",
                    "option1_hindi", "option2_hindi",
                    "option3_hindi", "option4_hindi",
                )
                if not row.get(field)
            ]
            if missing:
                errors.append(
                    f"question {row.get('question_no')}: missing "
                    + ", ".join(missing)
                )
    ai_report_path = output_dir / "ai_math_report.json"
    if ai_report_path.exists():
        ai_report = json.loads(ai_report_path.read_text(encoding="utf-8"))
        for event in ai_report.get("events", []):
            if event.get("status") in {"vision_verification_failed", "limit_reached"}:
                message = f"question {event.get('question_no')}: AI vision verification {event.get('status').replace('_', ' ')}"
                if strict_accuracy:
                    errors.append(message)
                else:
                    warnings.append(message)
    return _write_report(output_dir, extractor_type, len(rows), errors, warnings)


def _write_report(output_dir, extractor_type, question_count, errors, warnings):
    result = {
        "valid": not errors,
        "extractor_type": extractor_type,
        "question_count": question_count,
        "error_count": len(errors),
        "warning_count": len(warnings),
        "errors": errors,
        "warnings": warnings,
    }
    output_dir.mkdir(parents=True, exist_ok=True)
    (output_dir / "validation_report.json").write_text(
        json.dumps(result, indent=2, ensure_ascii=False), encoding="utf-8"
    )
    return result
