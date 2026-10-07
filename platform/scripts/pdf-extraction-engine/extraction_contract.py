import csv
import hashlib
import json
import re
from dataclasses import dataclass, field
from pathlib import Path


STANDARD_QUESTION_FIELDS = [
    "document_id",
    "document_key",
    "source_relative_path",
    "source_filename",
    "source_sha256",
    "extractor_type",
    "paper_code",
    "exam_name",
    "exam_year",
    "paper",
    "subject",
    "set_name",
    "exam_date",
    "exam_shift",
    "language",
    "section",
    "question_no",
    "question_id",
    "question_type",
    "marks",
    "negative_marks",
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
    "passage_images",
    "passage_hindi_images",
    "question_images",
    "question_hindi_images",
    "option1_images",
    "option2_images",
    "option3_images",
    "option4_images",
    "option1_hindi_images",
    "option2_hindi_images",
    "option3_hindi_images",
    "option4_hindi_images",
    "source_pages",
    "metadata_json",
    "warning_count",
]

DELIVERY_IMAGE_FIELDS = [
    "passage_images",
    "passage_hindi_images",
    "question_images",
    "question_hindi_images",
    "option1_images",
    "option2_images",
    "option3_images",
    "option4_images",
    "option1_hindi_images",
    "option2_hindi_images",
    "option3_hindi_images",
    "option4_hindi_images",
]
DELIVERY_QUESTION_FIELDS = [
    field for field in STANDARD_QUESTION_FIELDS if field not in DELIVERY_IMAGE_FIELDS
]

IMAGE_MANIFEST_FIELDS = [
    "document_id",
    "document_key",
    "source_relative_path",
    "question_no",
    "source_order",
    "display_order",
    "role",
    "language",
    "image_index",
    "filename",
    "relative_path",
    "source_page",
    "bbox_x0",
    "bbox_y0",
    "bbox_x1",
    "bbox_y1",
    "width",
    "height",
    "sha256",
]

ROLE_DISPLAY_ORDER = {
    "passage": 10,
    "passage_hindi": 11,
    "question": 20,
    "option1": 30,
    "option2": 31,
    "option3": 32,
    "option4": 33,
    "question_hindi": 40,
    "option1_hindi": 50,
    "option2_hindi": 51,
    "option3_hindi": 52,
    "option4_hindi": 53,
}


def slug(value, lowercase=False):
    cleaned = re.sub(r"[^A-Za-z0-9]+", "_", str(value).strip()).strip("_")
    cleaned = cleaned or "unknown"
    return cleaned.lower() if lowercase else cleaned


def file_sha256(path, chunk_size=1024 * 1024):
    digest = hashlib.sha256()
    with Path(path).open("rb") as source:
        for chunk in iter(lambda: source.read(chunk_size), b""):
            digest.update(chunk)
    return digest.hexdigest()


def bytes_sha256(data):
    return hashlib.sha256(data).hexdigest()


def numbered_question(value):
    text = slug(value, lowercase=True)
    return text.zfill(4) if text.isdigit() else text


def bounded_document_key(relative_source, max_length=170):
    hierarchy = list(relative_source.parent.parts) + [relative_source.stem]
    full_key = "__".join(slug(part) for part in hierarchy)
    if len(full_key) <= max_length:
        return full_key
    suffix = hashlib.sha256(relative_source.as_posix().encode("utf-8")).hexdigest()[:12]
    return f"{full_key[:max_length - 14].rstrip('_')}__{suffix}"


@dataclass
class ExtractionContext:
    source_path: Path
    input_root: Path
    output_root: Path
    paper_code: str
    extractor_type: str
    source_relative_path: Path
    source_sha256: str
    document_id: str
    document_key: str
    output_dir: Path
    images_dir: Path

    @classmethod
    def create(cls, source_path, input_root, output_root, paper_code, extractor_type):
        source_path = Path(source_path).resolve()
        input_root = Path(input_root).resolve()
        output_root = Path(output_root).resolve()
        try:
            relative_source = source_path.relative_to(input_root)
        except ValueError as exc:
            raise ValueError(
                f"Source file '{source_path}' is not inside input root '{input_root}'"
            ) from exc

        source_hash = file_sha256(source_path)
        document_key = bounded_document_key(relative_source)
        output_dir = output_root / relative_source.parent / source_path.stem
        images_dir = output_dir / "images"
        images_dir.mkdir(parents=True, exist_ok=True)
        return cls(
            source_path=source_path,
            input_root=input_root,
            output_root=output_root,
            paper_code=slug(paper_code).upper(),
            extractor_type=slug(extractor_type, lowercase=True),
            source_relative_path=relative_source,
            source_sha256=source_hash,
            document_id=f"doc_{source_hash[:20]}",
            document_key=document_key,
            output_dir=output_dir,
            images_dir=images_dir,
        )

    def image_filename(self, question_no, role, image_index, extension):
        return (
            f"{self.document_key}__q{numbered_question(question_no)}__"
            f"{slug(role, lowercase=True)}__img{int(image_index):02d}."
            f"{slug(extension, lowercase=True)}"
        )

    def image_relative_path(self, filename):
        return (self.images_dir / filename).relative_to(self.output_root).as_posix()

    def base_question_row(self):
        row = {field: "" for field in STANDARD_QUESTION_FIELDS}
        row.update({
            "document_id": self.document_id,
            "document_key": self.document_key,
            "source_relative_path": self.source_relative_path.as_posix(),
            "source_filename": self.source_path.name,
            "source_sha256": self.source_sha256,
            "extractor_type": self.extractor_type,
            "paper_code": self.paper_code,
        })
        return row

    def contract_metadata(self):
        return {
            "document_id": self.document_id,
            "document_key": self.document_key,
            "source_path": str(self.source_path),
            "source_relative_path": self.source_relative_path.as_posix(),
            "source_filename": self.source_path.name,
            "source_sha256": self.source_sha256,
            "extractor_type": self.extractor_type,
            "paper_code": self.paper_code,
            "output_relative_path": self.output_dir.relative_to(self.output_root).as_posix(),
        }


@dataclass
class ImageManifest:
    context: ExtractionContext
    rows: list = field(default_factory=list)

    def add(
        self,
        *,
        question_no,
        role,
        image_index,
        filename,
        source_order,
        source_page="",
        bbox=None,
        width="",
        height="",
        image_bytes=None,
        language="",
    ):
        bbox = tuple(bbox) if bbox else ("", "", "", "")
        row = {
            "document_id": self.context.document_id,
            "document_key": self.context.document_key,
            "source_relative_path": self.context.source_relative_path.as_posix(),
            "question_no": str(question_no),
            "source_order": int(source_order),
            "display_order": ROLE_DISPLAY_ORDER.get(role, 999),
            "role": role,
            "language": language or ("hi" if role.endswith("_hindi") else ""),
            "image_index": int(image_index),
            "filename": filename,
            "relative_path": self.context.image_relative_path(filename),
            "source_page": source_page,
            "bbox_x0": bbox[0],
            "bbox_y0": bbox[1],
            "bbox_x1": bbox[2],
            "bbox_y1": bbox[3],
            "width": width,
            "height": height,
            "sha256": bytes_sha256(image_bytes) if image_bytes is not None else "",
        }
        self.rows.append(row)
        return row

    def write(self, path=None):
        path = Path(path) if path else self.context.output_dir / "images_manifest.csv"
        with path.open("w", newline="", encoding="utf-8-sig") as output:
            writer = csv.DictWriter(output, fieldnames=IMAGE_MANIFEST_FIELDS)
            writer.writeheader()
            writer.writerows(sorted(
                self.rows,
                key=lambda row: (
                    numeric_sort_key(row["question_no"]),
                    int(row["source_order"]),
                    int(row["display_order"]),
                ),
            ))
        return path


def numeric_sort_key(value):
    text = str(value)
    return (0, int(text)) if text.isdigit() else (1, text.lower())


def image_cell(relative_paths):
    return "\n".join(f"[image: {path}]" for path in relative_paths)


def image_list_cell(relative_paths):
    return ";".join(relative_paths)


def write_standard_questions_csv(rows, path):
    path = Path(path)
    with path.open("w", newline="", encoding="utf-8-sig") as output:
        writer = csv.DictWriter(output, fieldnames=STANDARD_QUESTION_FIELDS, extrasaction="ignore")
        writer.writeheader()
        for supplied in rows:
            row = {field: "" for field in STANDARD_QUESTION_FIELDS}
            row.update(supplied)
            if isinstance(row.get("metadata_json"), (dict, list)):
                row["metadata_json"] = json.dumps(row["metadata_json"], ensure_ascii=False, sort_keys=True)
            writer.writerow(row)
    return path


def write_delivery_questions_csv(rows, path):
    path = Path(path)
    with path.open("w", newline="", encoding="utf-8-sig") as output:
        writer = csv.DictWriter(output, fieldnames=DELIVERY_QUESTION_FIELDS, extrasaction="ignore")
        writer.writeheader()
        for supplied in rows:
            row = {field: "" for field in DELIVERY_QUESTION_FIELDS}
            row.update({field: supplied.get(field, "") for field in DELIVERY_QUESTION_FIELDS})
            if isinstance(row.get("metadata_json"), (dict, list)):
                row["metadata_json"] = json.dumps(
                    row["metadata_json"], ensure_ascii=False, sort_keys=True
                )
            writer.writerow(row)
    return path

def write_extraction_manifest(context, metadata, counts, warnings, path=None):
    path = Path(path) if path else context.output_dir / "extraction_manifest.json"
    payload = context.contract_metadata()
    payload.update({
        "metadata": metadata,
        "counts": counts,
        "warnings": warnings,
    })
    path.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")
    return path
