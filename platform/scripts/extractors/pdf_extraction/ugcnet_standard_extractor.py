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
from nta_image_pdf_extractor import extract_ugc_net_image_pdf


OPTION_LABELS = ["A", "B", "C", "D"]


def extract_ugcnet_standard(pdf_path, input_root, output_root, paper_code):
    context = ExtractionContext.create(
        pdf_path, input_root, output_root, paper_code, "ugcnet_bilingual"
    )
    legacy_json, legacy_csv, legacy_images, question_count = extract_ugc_net_image_pdf(
        context.source_path, context.output_dir, context.paper_code
    )
    legacy_result = json.loads(Path(legacy_json).read_text(encoding="utf-8"))
    adapter = LegacyImageAdapter(context, legacy_images)
    rows = []
    questions = []

    for question in legacy_result["questions"]:
        qno = question["question_no"]
        pages = question.get("source_pages", [])
        options = question.get("options", {})
        options_hindi = question.get("options_hindi", {})
        passage = adapter.adopt(
            qno, "passage", question.get("passage_image_files", []), source_pages=pages
        )
        passage_hindi = adapter.adopt(
            qno,
            "passage_hindi",
            question.get("passage_hindi_image_files", []),
            source_pages=pages,
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
        question_hindi = adapter.adopt(
            qno,
            "question_hindi",
            question.get("question_hindi_image_files", []),
            source_pages=pages,
        )
        adopted_options_hindi = {
            label: adapter.adopt(
                qno,
                f"option{index}_hindi",
                options_hindi.get(label, []),
                source_pages=pages,
            )
            for index, label in enumerate(OPTION_LABELS, start=1)
        }

        qbid = question.get("qbid", "")
        normalized = {
            "question_no": qno,
            "question_id": qbid,
            "question_type": "MCQ",
            "passage_image_files": passage,
            "passage_hindi_image_files": passage_hindi,
            "question_image_files": question_images,
            "question_hindi_image_files": question_hindi,
            "options": adopted_options,
            "options_hindi": adopted_options_hindi,
            "source_pages": pages,
        }
        questions.append(normalized)

        row = context.base_question_row()
        row.update({
            "language": "English/Hindi",
            "question_no": qno,
            "question_id": qbid,
            "question_type": "MCQ",
            "passage": image_cell(passage),
            "passage_hindi": image_cell(passage_hindi),
            "question": image_cell(question_images),
            "question_hindi": image_cell(question_hindi),
            "source_pages": ";".join(str(page) for page in pages),
            "metadata_json": {"qbid": qbid, "layout": "ugc-net-bilingual"},
        })
        for index, label in enumerate(OPTION_LABELS, start=1):
            row[f"option{index}"] = image_cell(adopted_options[label])
            row[f"option{index}_images"] = image_list_cell(adopted_options[label])
            row[f"option{index}_hindi"] = image_cell(adopted_options_hindi[label])
            row[f"option{index}_hindi_images"] = image_list_cell(adopted_options_hindi[label])
        row.update({
            "passage_images": image_list_cell(passage),
            "passage_hindi_images": image_list_cell(passage_hindi),
            "question_images": image_list_cell(question_images),
            "question_hindi_images": image_list_cell(question_hindi),
        })
        rows.append(row)

    metadata = {"language": "English/Hindi", "layout": "ugc-net-bilingual"}
    result = {
        "document": context.contract_metadata(),
        "paper_code": context.paper_code,
        "source_pdf": str(context.source_path),
        "layout": "ugc-net-bilingual",
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
        description="Extract bilingual UGC-NET PDFs using the standard output contract."
    )
    parser.add_argument("--pdf", required=True)
    parser.add_argument("--input-root", required=True)
    parser.add_argument("--output-root", required=True)
    parser.add_argument("--paper-code", required=True)
    args = parser.parse_args()
    outputs = extract_ugcnet_standard(
        args.pdf, args.input_root, args.output_root, args.paper_code
    )
    labels = ["JSON", "CSV", "Images", "Image manifest", "Extraction manifest"]
    print(f"Done. Extracted {outputs[-1]} questions.")
    for label, path in zip(labels, outputs[:5]):
        print(f"{label}: {path}")


if __name__ == "__main__":
    main()
