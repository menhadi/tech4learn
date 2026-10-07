import csv
import html
import json
import re
from pathlib import Path
from urllib.parse import quote


DEFAULT_CDN_PREFIX = "https://cdn.examelite.com/"
IMAGE_MARKER_RE = re.compile(r"\[image:\s*([^\]]+?)\s*\]", re.IGNORECASE)
IMG_SRC_RE = re.compile(r'<img\b[^>]*\bsrc=["\']([^"\']+)["\']', re.IGNORECASE)
CONTENT_FIELDS = (
    "passage", "passage_hindi", "question", "question_hindi",
    "option1", "option2", "option3", "option4",
    "option1_hindi", "option2_hindi", "option3_hindi", "option4_hindi",
)
IMAGE_FIELDS = (
    "passage_images", "passage_hindi_images", "question_images",
    "question_hindi_images", "option1_images", "option2_images",
    "option3_images", "option4_images", "option1_hindi_images",
    "option2_hindi_images", "option3_hindi_images", "option4_hindi_images",
)


def normalized_prefix(prefix=DEFAULT_CDN_PREFIX):
    value = str(prefix or DEFAULT_CDN_PREFIX).strip()
    if not value.lower().startswith("https://"):
        raise ValueError("CDN prefix must use HTTPS")
    return value.rstrip("/") + "/"


def cdn_url(relative_path, prefix=DEFAULT_CDN_PREFIX):
    value = str(relative_path or "").strip()
    if not value:
        return value
    prefix = normalized_prefix(prefix)
    if value.startswith(prefix):
        return value
    if value.lower().startswith(("https://", "http://")):
        raise ValueError(f"Image URL is outside the configured CDN: {value}")
    path = value.replace("\\", "/").lstrip("/")
    return prefix + quote(path, safe="/._-")


def image_alt(row, language="English"):
    primary = str(row.get("exam_name") or row.get("paper_code") or "Exam").strip()
    parts = [primary]
    for value in (row.get("exam_year"), row.get("subject")):
        value = str(value or "").strip()
        if value and value.lower() not in " ".join(parts).lower():
            parts.append(value)
    parts.extend(("Question", str(row.get("question_no") or "").strip(), language))
    return " ".join(part for part in parts if part)


def image_tag(relative_path, row, prefix=DEFAULT_CDN_PREFIX, language="English"):
    alt = html.escape(image_alt(row, language), quote=True)
    source = html.escape(cdn_url(relative_path, prefix), quote=True)
    return f'<img alt="{alt}" src="{source}"/>'


def replace_image_markers(value, row, prefix=DEFAULT_CDN_PREFIX, language="English"):
    return IMAGE_MARKER_RE.sub(
        lambda match: image_tag(match.group(1), row, prefix, language),
        str(value or ""),
    )


def image_urls(value, prefix=DEFAULT_CDN_PREFIX):
    if isinstance(value, list):
        return [cdn_url(item, prefix) for item in value]
    items = [item.strip() for item in re.split(r"[;\n]+", str(value or "")) if item.strip()]
    return ";".join(cdn_url(item, prefix) for item in items)


def transform_question_row(row, prefix=DEFAULT_CDN_PREFIX):
    transformed = dict(row)
    default_language = "Hindi" if str(row.get("language", "")).lower() in {"hi", "hindi"} else "English"
    image_fields = {field for field in row if field.endswith("_images")} | set(IMAGE_FIELDS)
    content_fields = set(CONTENT_FIELDS) | {
        field[:-7] for field in image_fields if field[:-7] in row
    }
    for field in content_fields:
        language = "Hindi" if "hindi" in field else default_language
        transformed[field] = replace_image_markers(row.get(field, ""), row, prefix, language)
    for field in image_fields:
        transformed[field] = image_urls(row.get(field, ""), prefix)
    return transformed


def _write_csv(path, fieldnames, rows):
    temporary = path.with_suffix(path.suffix + ".tmp")
    with temporary.open("w", encoding="utf-8-sig", newline="") as target:
        writer = csv.DictWriter(target, fieldnames=fieldnames, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)
    temporary.replace(path)


def _transform_json_value(value, row, prefix, language="English", image_collection=False):
    if image_collection:
        return image_urls(value, prefix)
    if isinstance(value, dict):
        transformed = {}
        for key, child in value.items():
            key_language = "Hindi" if "hindi" in key.lower() else language
            is_images = key == "images" or key.endswith("_images")
            transformed[key] = _transform_json_value(
                child, row, prefix, key_language, image_collection=is_images
            )
        return transformed
    if isinstance(value, list):
        return [_transform_json_value(item, row, prefix, language) for item in value]
    if isinstance(value, str):
        return replace_image_markers(value, row, prefix, language)
    return value


def prepare_cdn_output(output_dir, prefix=DEFAULT_CDN_PREFIX):
    output_dir = Path(output_dir)
    prefix = normalized_prefix(prefix)
    csv_path = output_dir / "questions.csv"
    with csv_path.open(encoding="utf-8-sig", newline="") as source:
        reader = csv.DictReader(source)
        fieldnames = reader.fieldnames or []
        rows = [transform_question_row(row, prefix) for row in reader]
    _write_csv(csv_path, fieldnames, rows)

    row_by_number = {str(row.get("question_no")): row for row in rows}
    json_path = output_dir / "questions.json"
    if json_path.exists():
        payload = json.loads(json_path.read_text(encoding="utf-8"))
        base_row = {
            "paper_code": payload.get("paper_code")
            or payload.get("document", {}).get("paper_code", "")
        }
        questions = payload.get("questions", [])
        for index, question in enumerate(questions):
            number = str(question.get("question_no", ""))
            row = row_by_number.get(number, dict(base_row, question_no=number))
            questions[index] = _transform_json_value(question, row, prefix)
        payload.setdefault("metadata", {})["cdn_prefix"] = prefix
        temporary = json_path.with_suffix(".tmp")
        temporary.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")
        temporary.replace(json_path)

    manifest_path = output_dir / "images_manifest.csv"
    if manifest_path.exists():
        with manifest_path.open(encoding="utf-8-sig", newline="") as source:
            reader = csv.DictReader(source)
            manifest_fields = list(reader.fieldnames or [])
            manifest_rows = list(reader)
        if "cdn_url" not in manifest_fields:
            manifest_fields.append("cdn_url")
        for manifest_row in manifest_rows:
            manifest_row["cdn_url"] = cdn_url(manifest_row.get("relative_path", ""), prefix)
        _write_csv(manifest_path, manifest_fields, manifest_rows)

    extraction_manifest = output_dir / "extraction_manifest.json"
    if extraction_manifest.exists():
        payload = json.loads(extraction_manifest.read_text(encoding="utf-8"))
        payload["cdn_prefix"] = prefix
        temporary = extraction_manifest.with_suffix(".tmp")
        temporary.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")
        temporary.replace(extraction_manifest)
    return {"questions": len(rows), "cdn_prefix": prefix}


def _validate_cdn_value(value, prefix, location, errors):
    text = str(value or "")
    if IMAGE_MARKER_RE.search(text):
        errors.append(f"{location}: unresolved image marker")
    for source in IMG_SRC_RE.findall(text):
        if not source.startswith(prefix):
            errors.append(f"{location}: img src is outside CDN: {source}")


def validate_questions_csv_cdn(csv_path, prefix=DEFAULT_CDN_PREFIX):
    csv_path = Path(csv_path)
    prefix = normalized_prefix(prefix)
    errors = []
    with csv_path.open(encoding="utf-8-sig", newline="") as source:
        rows = list(csv.DictReader(source))
    for row_index, row in enumerate(rows, start=2):
        for field, value in row.items():
            location = f"{csv_path.name} row {row_index} field {field}"
            if field.endswith("_images"):
                urls = [item.strip() for item in re.split(r"[;\n]+", str(value or "")) if item.strip()]
                for url in urls:
                    if not url.startswith(prefix):
                        errors.append(f"{location}: image URL is outside CDN: {url}")
            else:
                _validate_cdn_value(value, prefix, location, errors)
    if errors:
        raise ValueError("CDN delivery validation failed: " + "; ".join(errors[:20]))
    return {"valid": True, "questions": len(rows), "cdn_prefix": prefix}

def validate_cdn_output(output_dir, prefix=DEFAULT_CDN_PREFIX):
    output_dir = Path(output_dir)
    prefix = normalized_prefix(prefix)
    csv_result = validate_questions_csv_cdn(output_dir / "questions.csv", prefix)
    errors = []
    rows = [None] * csv_result["questions"]

    json_path = output_dir / "questions.json"
    if json_path.exists():
        payload = json.loads(json_path.read_text(encoding="utf-8"))

        def walk(value, location, image_collection=False):
            if isinstance(value, dict):
                for key, child in value.items():
                    walk(child, f"{location}.{key}", key == "images" or key.endswith("_images"))
            elif isinstance(value, list):
                for index, child in enumerate(value):
                    walk(child, f"{location}[{index}]", image_collection)
            elif image_collection and value:
                urls = [item.strip() for item in re.split(r"[;\n]+", str(value)) if item.strip()]
                for url in urls:
                    if not url.startswith(prefix):
                        errors.append(f"{location}: image URL is outside CDN: {url}")
            elif isinstance(value, str):
                _validate_cdn_value(value, prefix, location, errors)

        walk(payload, "questions.json")

    manifest_path = output_dir / "images_manifest.csv"
    if manifest_path.exists():
        with manifest_path.open(encoding="utf-8-sig", newline="") as source:
            for row_index, row in enumerate(csv.DictReader(source), start=2):
                url = str(row.get("cdn_url", "")).strip()
                if row.get("relative_path") and not url.startswith(prefix):
                    errors.append(f"images_manifest.csv row {row_index}: missing configured CDN URL")
    if errors:
        raise ValueError("CDN delivery validation failed: " + "; ".join(errors[:20]))
    return {"valid": True, "questions": len(rows), "cdn_prefix": prefix}