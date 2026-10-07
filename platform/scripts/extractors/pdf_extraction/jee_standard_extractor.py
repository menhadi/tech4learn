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
from nta_image_pdf_extractor import extract_jee_image_pdf


OPTION_LABELS = ["A", "B", "C", "D"]


def extract_jee_standard(
    pdf_path, input_root, output_root, paper_code, keep_duplicates=False,
    extractor_type="jee_nta_image", layout_name="jee-nta-image",
):
    context = ExtractionContext.create(
        pdf_path, input_root, output_root, paper_code, extractor_type
    )
    legacy_json, legacy_csv, legacy_images, question_count = extract_jee_image_pdf(
        context.source_path,
        context.output_dir,
        context.paper_code,
        keep_duplicates=keep_duplicates,
    )
    legacy_result = json.loads(Path(legacy_json).read_text(encoding="utf-8"))
    adapter = LegacyImageAdapter(context, legacy_images)
    rows = []
    questions = []

    for question in legacy_result["questions"]:
        qno = question["question_no"]
        pages = question.get("source_pages", [])
        options = question.get("options", {})
        passage = adapter.adopt(
            qno, "passage", question.get("passage_image_files", []), source_pages=pages
        )
        question_images = adapter.adopt(
            qno, "question", question.get("question_image_files", []), source_pages=pages
        )
        adopted_options = {
            label: adapter.adopt(
                qno,
                f"option{index}",
                options.get(label, []),
                source_pages=pages,
            )
            for index, label in enumerate(OPTION_LABELS, start=1)
        }
        questions.append({
            "question_no": qno,
            "question_type": "MCQ",
            "passage_image_files": passage,
            "question_image_files": question_images,
            "options": adopted_options,
            "source_pages": pages,
        })

        row = context.base_question_row()
        row.update({
            "question_no": qno,
            "question_type": "MCQ",
            "passage": image_cell(passage),
            "question": image_cell(question_images),
            "passage_images": image_list_cell(passage),
            "question_images": image_list_cell(question_images),
            "source_pages": ";".join(str(page) for page in pages),
            "metadata_json": {"layout": layout_name},
        })
        for index, label in enumerate(OPTION_LABELS, start=1):
            row[f"option{index}"] = image_cell(adopted_options[label])
            row[f"option{index}_images"] = image_list_cell(adopted_options[label])
        rows.append(row)

    metadata = {"layout": layout_name}
    result = {
        "document": context.contract_metadata(),
        "paper_code": context.paper_code,
        "source_pdf": str(context.source_path),
        "layout": layout_name,
        "page_count": legacy_result.get("page_count", 0),
        "question_count": len(questions),
        "metadata": metadata,
        "warnings": [],
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
        },
        [],
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
        description="Extract JEE/NTA image PDFs using the standard output contract."
    )
    parser.add_argument("--pdf", required=True)
    parser.add_argument("--input-root", required=True)
    parser.add_argument("--output-root", required=True)
    parser.add_argument("--paper-code", required=True)
    parser.add_argument("--keep-duplicates", action="store_true")
    args = parser.parse_args()
    outputs = extract_jee_standard(
        args.pdf,
        args.input_root,
        args.output_root,
        args.paper_code,
        keep_duplicates=args.keep_duplicates,
    )
    labels = ["JSON", "CSV", "Images", "Image manifest", "Extraction manifest"]
    print(f"Done. Extracted {outputs[-1]} questions.")
    for label, path in zip(labels, outputs[:5]):
        print(f"{label}: {path}")


if __name__ == "__main__":
    main()
