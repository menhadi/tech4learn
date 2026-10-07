#!/usr/bin/env python3
"""Select one canonical question from a document-level PDF extraction."""
import json
import re
import shutil
import sys
from pathlib import Path
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')
import source_pdf_extractor_core as core

SCHEMA_VERSION = 6
IMAGE_MARKER_RE = re.compile(r"\s*\[image:\s*[^\]]+\]\s*", re.I)

def clean_text(value):
    return IMAGE_MARKER_RE.sub(" ", value or "").strip()

def canonical_fields(question):
    options = question.get("options", {})
    fields = {"question": {"text": clean_text(question.get("question_text", "")), "evidence": []}}
    for index, label in enumerate("ABCD", start=1):
        fields[f"option{index}"] = {"text": clean_text(options.get(label, {}).get("text", "")), "evidence": []}
    return fields

def copy_visual(source_root, output_dir, source_file, ordinal, target, index):
    source = source_root / source_file
    if not source.is_file():
        source = source_root / Path(source_file).name
    if not source.is_file():
        raise RuntimeError(f"Extracted visual is missing: {source_file}")
    suffix = f"option{target[6:]}_image{index}" if target.startswith("option") else f"img{index}"
    destination = output_dir / f"q_{ordinal}_{suffix}.png"
    if source.resolve() != destination.resolve():
        shutil.copy2(source, destination)
    return destination

def build_document(pdf_path, output_dir, paper_code, profile=None):
    document_path = output_dir / "document.json"
    if document_path.is_file():
        payload = json.loads(document_path.read_text(encoding="utf-8"))
        if payload.get("schema_version") == SCHEMA_VERSION:
            return payload
    extraction_dir = output_dir / "document"
    legacy_json, _, images_dir, _ = core.extract_pdf(
        pdf_path=pdf_path, output_dir=extraction_dir, paper_code=paper_code,
        header_y=35, footer_y=810, repeated_ratio=0.70,
        include_vector_crops=True, raw_embedded_images=False,
        line_mode=(profile or {}).get("reading_order", "auto") != "rows",
        renumber_resets=(profile or {}).get("question_numbering", "auto") != "continuous",
    )
    result = json.loads(Path(legacy_json).read_text(encoding="utf-8"))
    payload = {
        "schema_version": SCHEMA_VERSION, "paper_code": paper_code,
        "page_count": result.get("page_count"),
        "ignored_repeated_image_count": result.get("ignored_repeated_image_count", 0),
        "removed_repeated_image_occurrences_for_rendering": result.get("removed_repeated_image_occurrences_for_rendering", 0),
        "images_dir": str(images_dir), "questions": result.get("questions", []),
    }
    document_path.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
    return payload

def select_question(document, output_dir, ordinal):
    questions = document.get("questions", [])
    if ordinal < 1 or ordinal > len(questions):
        raise ValueError(f"Question position {ordinal} was not located in the source PDF.")
    question = questions[ordinal - 1]
    source_root = Path(document["images_dir"])
    visuals = []
    assignments = [("question", question.get("question_images", []))]
    options = question.get("options", {})
    assignments.extend((f"option{index}", options.get(label, {}).get("images", []))
                       for index, label in enumerate("ABCD", start=1))
    metadata = {item.get("file"): item for item in question.get("image_objects", [])}
    for target, files in assignments:
        for index, source_file in enumerate(files, start=1):
            destination = copy_visual(source_root, output_dir, source_file, ordinal, target, index)
            evidence = metadata.get(Path(source_file).name, metadata.get(source_file, {}))
            visuals.append({
                "target_field": target, "index": index, "file": destination.name,
                "path": str(destination), "page": evidence.get("page"),
                "bbox": evidence.get("bbox"), "visual_type": evidence.get("type", "source_crop"),
            })
    source_values = {"question": question.get("question_text", "")}
    source_values.update({f"option{index}": options.get(label, {}).get("text", "")
                          for index, label in enumerate("ABCD", start=1)})
    source_html_table_targets = [target for target, value in source_values.items()
                                 if "<table" in (value or "").lower()]
    return {
        "ok": True, "schema_version": SCHEMA_VERSION,
        "paper_code": document.get("paper_code"), "paper_question_number": ordinal,
        "printed_question_number": question.get("question_no"),
        "page": (question.get("source_pages") or [None])[0],
        "source_pages": question.get("source_pages", []),
        "question_band_detected": True, "canonical_fields": canonical_fields(question),
        "source_html_table_targets": source_html_table_targets,
        "visuals": visuals,
        "removed_repeated_occurrences": document.get("removed_repeated_image_occurrences_for_rendering", 0),
        "repeated_layers": document.get("ignored_repeated_image_count", 0),
        "source_profile": {"page_count": document.get("page_count"), "document_type": "document_sequence"},
    }

def main():
    if len(sys.argv) not in (5, 6):
        raise ValueError("Usage: extract-pdf-question-visuals.py PDF OUTPUT_DIR QUESTION_ORDINAL PAPER_CODE [PROFILE_JSON]")
    pdf_path, output_dir, ordinal, paper_code = sys.argv[1], Path(sys.argv[2]), int(sys.argv[3]), sys.argv[4]
    profile = json.loads(sys.argv[5]) if len(sys.argv) == 6 and sys.argv[5] else {}
    output_dir.mkdir(parents=True, exist_ok=True)
    print(json.dumps(select_question(build_document(pdf_path, output_dir, paper_code, profile), output_dir, ordinal), ensure_ascii=False))

if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print(json.dumps({"ok": False, "error": str(error)}))
        sys.exit(2)
