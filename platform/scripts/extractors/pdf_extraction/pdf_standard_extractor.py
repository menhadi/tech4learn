import argparse
import json
from pathlib import Path

from contract_adapter import LegacyImageAdapter
from extraction_contract import (
    ExtractionContext,
    image_cell,
    image_list_cell,
    write_extraction_manifest,
    write_standard_questions_csv,
)
from pdf_question_extractor import extract_pdf


OPTION_LABELS = ["A", "B", "C", "D"]


def content_with_images(adapter, text, image_paths):
    content = adapter.rewrite_text_references(text or "")
    missing = [path for path in image_paths if path not in content]
    if missing:
        markers = image_cell(missing)
        content = f"{content}\n{markers}".strip() if content else markers
    return content


def extract_pdf_standard(
    pdf_path,
    input_root,
    output_root,
    paper_code,
    *,
    header_y=70,
    footer_y=760,
    repeated_ratio=0.70,
    include_vector_crops=True,
    raw_embedded_images=False,
    line_mode=False,
    renumber_resets=False,
    extractor_type="text_math_pdf",
    max_pages=None,
):
    context = ExtractionContext.create(
        pdf_path, input_root, output_root, paper_code, extractor_type
    )
    legacy_json, legacy_csv, legacy_images, question_count = extract_pdf(
        context.source_path,
        context.output_dir,
        context.paper_code,
        header_y,
        footer_y,
        repeated_ratio,
        include_vector_crops,
        raw_embedded_images=raw_embedded_images,
        line_mode=line_mode,
        renumber_resets=renumber_resets,
        max_pages=max_pages,
    )
    legacy_result = json.loads(Path(legacy_json).read_text(encoding="utf-8"))
    adapter = LegacyImageAdapter(context, legacy_images)
    rows = []
    questions = []

    for question in legacy_result["questions"]:
        qno = question["question_no"]
        pages = question.get("source_pages", [])
        options = question.get("options", {})
        image_metadata = {
            item["file"]: item for item in question.get("image_objects", [])
            if item.get("file")
        }
        question_images = adapter.adopt(
            qno,
            "question",
            question.get("question_images", []),
            source_pages=pages,
            metadata_by_filename=image_metadata,
        )
        adopted_options = {}
        for index, label in enumerate(OPTION_LABELS, start=1):
            option = options.get(label, {})
            adopted_options[label] = adapter.adopt(
                qno,
                f"option{index}",
                option.get("images", []),
                source_pages=pages,
                metadata_by_filename=image_metadata,
            )

        question_text = content_with_images(
            adapter, question.get("question_text", ""), question_images
        )
        normalized_options = {}
        for label in OPTION_LABELS:
            normalized_options[label] = {
                "text": content_with_images(
                    adapter,
                    options.get(label, {}).get("text", ""),
                    adopted_options[label],
                ),
                "images": adopted_options[label],
            }
        questions.append({
            "question_no": qno,
            "question_type": "MCQ" if options else "NAT",
            "question_text": question_text,
            "question_images": question_images,
            "options": normalized_options,
            "source_pages": pages,
        })

        row = context.base_question_row()
        row.update({
            "question_no": qno,
            "question_type": "MCQ" if options else "NAT",
            "question": question_text,
            "question_images": image_list_cell(question_images),
            "source_pages": ";".join(str(page) for page in pages),
            "metadata_json": {
                "layout": "text-math-pdf",
                "ai_math": "handled by unified batch post-processor",
            },
        })
        for index, label in enumerate(OPTION_LABELS, start=1):
            row[f"option{index}"] = normalized_options[label]["text"]
            row[f"option{index}_images"] = image_list_cell(adopted_options[label])
        rows.append(row)

    metadata = {
        "layout": "text-math-pdf",
        "header_y": header_y,
        "footer_y": footer_y,
        "table_count": legacy_result.get("table_count", 0),
    }
    warnings = []
    result = {
        "document": context.contract_metadata(),
        "paper_code": context.paper_code,
        "source_pdf": str(context.source_path),
        "layout": "text-math-pdf",
        "page_count": legacy_result.get("page_count", 0),
        "question_count": len(questions),
        "metadata": metadata,
        "warnings": warnings,
        "questions": questions,
    }
    json_path = context.output_dir / "questions.json"
    csv_path = context.output_dir / "questions.csv"
    image_manifest_path = adapter.manifest.write()
    extraction_manifest_path = write_extraction_manifest(
        context,
        metadata,
        {
            "pages": result["page_count"],
            "questions": len(questions),
            "images": len(adapter.manifest.rows),
            "tables": metadata["table_count"],
        },
        warnings,
    )
    json_path.write_text(json.dumps(result, indent=2, ensure_ascii=False), encoding="utf-8")
    write_standard_questions_csv(rows, csv_path)
    for legacy_path in (Path(legacy_json), Path(legacy_csv)):
        if legacy_path != json_path and legacy_path != csv_path and legacy_path.exists():
            legacy_path.unlink()
    return (
        json_path,
        csv_path,
        context.images_dir,
        image_manifest_path,
        extraction_manifest_path,
        question_count,
    )


def main():
    parser = argparse.ArgumentParser(
        description="Extract text/math exam PDFs using the standard output contract."
    )
    parser.add_argument("--pdf", required=True)
    parser.add_argument("--input-root", required=True)
    parser.add_argument("--output-root", required=True)
    parser.add_argument("--paper-code", required=True)
    parser.add_argument("--header-y", type=float, default=70)
    parser.add_argument("--footer-y", type=float, default=760)
    parser.add_argument("--repeated-ratio", type=float, default=0.70)
    parser.add_argument(
        "--include-vector-crops",
        dest="include_vector_crops",
        action="store_true",
        default=True,
        help="Extract vector drawings (enabled by default for text/math papers).",
    )
    parser.add_argument(
        "--no-vector-crops",
        dest="include_vector_crops",
        action="store_false",
        help="Disable vector drawing extraction for this paper.",
    )
    parser.add_argument("--extract-raw-embedded-images", action="store_true")
    args = parser.parse_args()
    outputs = extract_pdf_standard(
        args.pdf,
        args.input_root,
        args.output_root,
        args.paper_code,
        header_y=args.header_y,
        footer_y=args.footer_y,
        repeated_ratio=args.repeated_ratio,
        include_vector_crops=args.include_vector_crops,
        raw_embedded_images=args.extract_raw_embedded_images,
    )
    labels = ["JSON", "CSV", "Images", "Image manifest", "Extraction manifest"]
    print(f"Done. Extracted {outputs[-1]} questions.")
    for label, path in zip(labels, outputs[:5]):
        print(f"{label}: {path}")


if __name__ == "__main__":
    main()
