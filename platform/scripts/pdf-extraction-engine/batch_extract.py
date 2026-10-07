import argparse
import csv
import json
import logging
import shutil
import sys
import threading
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime, timezone
from pathlib import Path

from ai_math import api_env_status, enhance_math_output
from answer_key_extractor import ANSWER_KEY_FIELDS, write_answer_key_outputs
from cdn_output import DEFAULT_CDN_PREFIX, prepare_cdn_output, validate_cdn_output
from cuetug_image_pdf_extractor import extract_cuetug_pdf
from gate_standard_extractor import extract_gate_standard
from google_sheets_output import (
    DEFAULT_CREDENTIALS_FILE, DEFAULT_GOOGLE_SHEET_ID, DEFAULT_SHEET_TAB,
    publish_questions_csv,
)
from extraction_contract import (
    DELIVERY_QUESTION_FIELDS, file_sha256, numeric_sort_key, slug,
    write_delivery_questions_csv,
)
from jee_standard_extractor import extract_jee_standard
from output_validator import validate_output
from pdf_detector import detect_pdf
from pdf_standard_extractor import extract_pdf_standard
from ugcnet_standard_extractor import extract_ugcnet_standard
from upsc_bilingual_extractor import extract_upsc_bilingual


REPORT_FIELDS = ["source_relative_path", "source_sha256", "paper_code", "extractor", "confidence", "status", "question_count", "warning_count", "error", "output_relative_path"]
LOCK = threading.Lock()


def now():
    return datetime.now(timezone.utc).isoformat()


def output_dir(root, relative_pdf):
    return root / relative_pdf.parent / relative_pdf.stem


def dispatch(
    extractor, pdf, input_root, output_root, paper_code, *,
    resume=False, api_timeout=120,
):
    if extractor == "answer_key_pdf":
        return write_answer_key_outputs(
            pdf, output_dir(output_root, pdf.relative_to(input_root))
        )
    if extractor == "gate_pdf":
        return extract_gate_standard(pdf, input_root, output_root, paper_code, resume=resume)
    if extractor == "cuetug":
        return extract_cuetug_pdf(pdf, input_root, output_root, paper_code)
    if extractor == "ugcnet_bilingual":
        return extract_ugcnet_standard(pdf, input_root, output_root, paper_code)
    if extractor == "upsc_bilingual":
        return extract_upsc_bilingual(
            pdf, input_root, output_root, paper_code,
            transcribe=True, timeout=api_timeout, resume=resume,
        )
    if extractor == "jee_nta_image":
        return extract_jee_standard(pdf, input_root, output_root, paper_code)
    if extractor == "text_math_pdf":
        return extract_pdf_standard(pdf, input_root, output_root, paper_code, include_vector_crops=True, raw_embedded_images=False)
    raise ValueError(f"unknown extractor: {extractor}")


def publish(staged, final):
    final.parent.mkdir(parents=True, exist_ok=True)
    backup = final.with_name(final.name + ".previous")
    if backup.exists():
        shutil.rmtree(backup)
    if final.exists():
        final.rename(backup)
    try:
        staged.rename(final)
    except Exception:
        if backup.exists() and not final.exists():
            backup.rename(final)
        raise
    if backup.exists():
        shutil.rmtree(backup)


def append_jsonl(path, payload):
    with LOCK:
        with path.open("a", encoding="utf-8") as target:
            target.write(json.dumps(payload, ensure_ascii=False) + "\n")


def archive_csv(path, json_path):
    path, json_path = Path(path), Path(json_path)
    if path.exists():
        with path.open(encoding="utf-8-sig", newline="") as source:
            reader = csv.DictReader(source)
            payload = {"fieldnames": reader.fieldnames or [], "rows": list(reader)}
        json_path.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")
        path.unlink()
        return payload["rows"]
    if json_path.exists():
        return json.loads(json_path.read_text(encoding="utf-8")).get("rows", [])
    return []


def archive_paper_csvs(paper_dir):
    paper_dir = Path(paper_dir)
    rows = archive_csv(paper_dir / "questions.csv", paper_dir / "questions_rows.json")
    archive_csv(paper_dir / "images_manifest.csv", paper_dir / "images_manifest.json")
    return rows


def archive_answer_key_csv(paper_dir):
    paper_dir = Path(paper_dir)
    return archive_csv(
        paper_dir / "answer_keys.csv", paper_dir / "answer_keys_rows.json"
    )

def write_master_questions_csv(output_root, results):
    rows = []
    successful = {"SUCCESS", "SUCCESS_WITH_WARNINGS", "SKIPPED_UNCHANGED"}
    for result in sorted(results, key=lambda item: item["source_relative_path"].lower()):
        if result["status"] not in successful or result.get("extractor") == "answer_key_pdf":
            continue
        paper_dir = output_root / Path(result["output_relative_path"])
        for row in archive_paper_csvs(paper_dir):
            standardized = {field: row.get(field, "") for field in DELIVERY_QUESTION_FIELDS}
            rows.append(standardized)
    rows.sort(key=lambda row: (
        row.get("source_relative_path", "").lower(),
        numeric_sort_key(row.get("question_no", "")),
    ))
    master_path = output_root / "questions.csv"
    temporary = output_root / "questions.tmp.csv"
    write_delivery_questions_csv(rows, temporary)
    temporary.replace(master_path)
    legacy_report = output_root / "_batch" / "batch_report.csv"
    if legacy_report.exists():
        legacy_report.unlink()
    return master_path, len(rows)


def write_master_answer_keys_csv(output_root, results):
    rows = []
    successful = {"SUCCESS", "SUCCESS_WITH_WARNINGS", "SKIPPED_UNCHANGED"}
    for result in sorted(results, key=lambda item: item["source_relative_path"].lower()):
        if result["status"] not in successful or result.get("extractor") != "answer_key_pdf":
            continue
        paper_dir = output_root / Path(result["output_relative_path"])
        rows.extend(archive_answer_key_csv(paper_dir))
    rows.sort(key=lambda row: (
        row.get("exam_name", "").lower(), row.get("exam_year", ""),
        row.get("paper_code", "").lower(), row.get("session", ""),
        numeric_sort_key(row.get("question_no", "")),
    ))
    master_path = output_root / "answer_keys.csv"
    temporary = output_root / "answer_keys.tmp.csv"
    with temporary.open("w", encoding="utf-8-sig", newline="") as target:
        writer = csv.DictWriter(target, fieldnames=ANSWER_KEY_FIELDS, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)
    temporary.replace(master_path)
    return master_path, len(rows)

def process_one(pdf, input_root, output_root, staging_root, args, state, event_path):
    relative = pdf.relative_to(input_root)
    source_hash = file_sha256(pdf)
    previous = state.get(relative.as_posix(), {})
    final_dir = output_dir(output_root, relative)
    if (
        args.resume
        and previous.get("sha256") == source_hash
        and previous.get("status") in {"SUCCESS", "SUCCESS_WITH_WARNINGS"}
        and previous.get("processing_profile") == args.processing_profile
        and final_dir.exists()
    ):
        if previous.get("extractor") == "answer_key_pdf":
            archive_answer_key_csv(final_dir)
        else:
            archive_paper_csvs(final_dir)
        return {"source_relative_path": relative.as_posix(), "source_sha256": source_hash, "paper_code": previous.get("paper_code", slug(pdf.stem).upper()), "extractor": previous.get("extractor", ""), "confidence": previous.get("confidence", ""), "status": "SKIPPED_UNCHANGED", "question_count": previous.get("question_count", ""), "warning_count": previous.get("warning_count", 0), "error": "", "output_relative_path": final_dir.relative_to(output_root).as_posix()}
    paper_code = slug(pdf.stem).upper()
    staged_dir = output_dir(staging_root, relative)
    extractor, confidence, detection = args.extractor, 0.0, None
    try:
        detection = detect_pdf(pdf) if args.extractor == "auto" else None
        extractor = detection.extractor if detection else args.extractor
        confidence = detection.confidence if detection else 1.0
        resume_context = {
            "source_sha256": source_hash,
            "processing_profile": args.processing_profile,
            "extractor": extractor,
        }
        resume_path = staged_dir / ".resume_context.json"
        keep_staging = False
        if args.resume and resume_path.exists():
            try:
                keep_staging = json.loads(resume_path.read_text(encoding="utf-8")) == resume_context
            except (OSError, json.JSONDecodeError):
                keep_staging = False
        if staged_dir.exists() and not keep_staging:
            shutil.rmtree(staged_dir)
        staged_dir.mkdir(parents=True, exist_ok=True)
        temporary_resume = resume_path.with_suffix(".tmp")
        temporary_resume.write_text(json.dumps(resume_context, indent=2), encoding="utf-8")
        temporary_resume.replace(resume_path)
        append_jsonl(event_path, {"time": now(), "event": "started", "source": relative.as_posix(), "extractor": extractor, "confidence": confidence, "detector_scores": detection.scores if detection else {}, "detector_reasons": detection.reasons if detection else []})
        outputs = dispatch(
            extractor, pdf, input_root, staging_root, paper_code,
            resume=keep_staging, api_timeout=args.api_timeout,
        )
        ai_report = None
        if extractor == "answer_key_pdf":
            question_count = int(outputs[-1]["answer_count"])
            validation = {
                "valid": bool(outputs[-1].get("valid")),
                "warning_count": 0,
                "errors": [],
            }
        else:
            question_count = int(outputs[-2] if extractor == "cuetug" else outputs[-1])
            if extractor in {"text_math_pdf", "gate_pdf"} and args.ai_math != "off":
                ai_report = enhance_math_output(
                    pdf, staged_dir, mode=args.ai_math,
                    max_calls=args.max_ai_calls, timeout=args.api_timeout,
                )
            validation = validate_output(
                staged_dir, staging_root, extractor,
                strict_accuracy=args.strict_accuracy,
            )
        warning_count = validation["warning_count"] + (1 if detection and detection.uncertain else 0)
        if validation["valid"]:
            if extractor == "answer_key_pdf":
                archive_answer_key_csv(staged_dir)
            else:
                prepare_cdn_output(staged_dir, args.cdn_prefix)
                validate_cdn_output(staged_dir, args.cdn_prefix)
                archive_paper_csvs(staged_dir)
            for checkpoint_name in (".resume_context.json", "scan_checkpoint.json", "ai_math_checkpoint.json"):
                checkpoint = staged_dir / checkpoint_name
                if checkpoint.exists():
                    checkpoint.unlink()
            publish(staged_dir, final_dir)
            status = "SUCCESS_WITH_WARNINGS" if warning_count else "SUCCESS"
            error = ""
        else:
            status = "VALIDATION_FAILED"
            error = "; ".join(validation["errors"][:5])
        result = {"source_relative_path": relative.as_posix(), "source_sha256": source_hash, "paper_code": paper_code, "extractor": extractor, "confidence": confidence, "status": status, "question_count": question_count, "warning_count": warning_count, "error": error, "output_relative_path": final_dir.relative_to(output_root).as_posix()}
        append_jsonl(event_path, {"time": now(), "event": "finished", **result, "ai_math": ai_report})
        return result
    except Exception as exc:
        logging.exception("Extraction failed for %s", relative)
        result = {"source_relative_path": relative.as_posix(), "source_sha256": source_hash, "paper_code": paper_code, "extractor": extractor, "confidence": confidence, "status": "EXTRACTION_FAILED", "question_count": 0, "warning_count": 0, "error": str(exc)[:1000], "output_relative_path": final_dir.relative_to(output_root).as_posix()}
        append_jsonl(event_path, {"time": now(), "event": "failed", **result})
        return result


def load_state(path):
    try:
        return json.loads(path.read_text(encoding="utf-8")) if path.exists() else {}
    except (json.JSONDecodeError, OSError):
        return {}


def save_state(path, state):
    temporary = path.with_suffix(".tmp")
    temporary.write_text(json.dumps(state, indent=2, ensure_ascii=False), encoding="utf-8")
    temporary.replace(path)


def main():
    parser = argparse.ArgumentParser(description="Auto-detect and extract mixed exam PDF collections.")
    parser.add_argument("--input-root", required=True)
    parser.add_argument("--output-root", required=True)
    parser.add_argument("--recursive", action="store_true")
    parser.add_argument("--workers", type=int, default=1)
    parser.add_argument("--resume", action="store_true")
    parser.add_argument(
        "--extractor",
        choices=[
            "auto", "answer_key_pdf", "gate_pdf", "cuetug",
            "ugcnet_bilingual", "upsc_bilingual", "jee_nta_image",
            "text_math_pdf",
        ],
        default="auto",
    )
    parser.add_argument("--ai-math", choices=["off", "auto", "always"], default="auto")
    parser.add_argument("--max-ai-calls", type=int, default=500)
    parser.add_argument("--api-timeout", type=int, default=90)
    parser.add_argument("--cdn-prefix", default=DEFAULT_CDN_PREFIX)
    parser.add_argument("--publish-google-sheet", action="store_true")
    parser.add_argument("--google-sheet-id", default=DEFAULT_GOOGLE_SHEET_ID)
    parser.add_argument("--google-credentials", default=DEFAULT_CREDENTIALS_FILE)
    parser.add_argument("--google-sheet-tab", default=DEFAULT_SHEET_TAB)
    parser.add_argument("--google-answer-sheet-tab", default="answer_keys")
    parser.add_argument("--google-chunk-rows", type=int, default=500)
    parser.add_argument("--allow-review-warnings", dest="strict_accuracy", action="store_false", default=True)
    parser.add_argument("--check-api-env", action="store_true")
    args = parser.parse_args()
    api_status = api_env_status()
    if args.check_api_env:
        print(json.dumps(api_status, indent=2))
        return
    args.processing_profile = {
        "version": "unified-checkpoint-v17",
        "extractor": args.extractor,
        "ai_math": args.ai_math,
        "openai_vision_model": api_status["openai_vision_model"],
        "max_ai_calls": args.max_ai_calls,
        "cdn_prefix": args.cdn_prefix.rstrip("/") + "/",
        "strict_accuracy": args.strict_accuracy,
    }
    input_root, output_root = Path(args.input_root).resolve(), Path(args.output_root).resolve()
    output_root.mkdir(parents=True, exist_ok=True)
    batch_dir = output_root / "_batch"
    batch_dir.mkdir(parents=True, exist_ok=True)
    run_id = datetime.now().strftime("%Y%m%d_%H%M%S")
    log_path = batch_dir / f"run_{run_id}.log"
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s", handlers=[logging.FileHandler(log_path, encoding="utf-8"), logging.StreamHandler(sys.stdout)])
    event_path, report_path, state_path = batch_dir / "batch_events.jsonl", batch_dir / "batch_report.json", batch_dir / "processing_state.json"
    staging_root = batch_dir / "staging" / "active"
    staging_root.mkdir(parents=True, exist_ok=True)
    pdfs = sorted(input_root.glob("**/*.pdf" if args.recursive else "*.pdf"))
    state, results = load_state(state_path), []
    logging.info("Found %d PDF files", len(pdfs))
    with ThreadPoolExecutor(max_workers=max(1, args.workers)) as executor:
        futures = [executor.submit(process_one, pdf, input_root, output_root, staging_root, args, state, event_path) for pdf in pdfs]
        for future in as_completed(futures):
            result = future.result()
            results.append(result)
            if result["status"] != "SKIPPED_UNCHANGED":
                state[result["source_relative_path"]] = {"sha256": result["source_sha256"], "status": result["status"], "paper_code": result["paper_code"], "extractor": result["extractor"], "confidence": result["confidence"], "question_count": result["question_count"], "warning_count": result["warning_count"], "processing_profile": args.processing_profile, "updated_at": now()}
                with LOCK:
                    save_state(state_path, state)
            logging.info("%s: %s (%s)", result["status"], result["source_relative_path"], result["extractor"])
    sorted_results = sorted(results, key=lambda row: row["source_relative_path"])
    master_csv, master_count = write_master_questions_csv(output_root, sorted_results)
    answer_master_csv, answer_count = write_master_answer_keys_csv(output_root, sorted_results)
    google_sheet = None
    google_answer_sheet = None
    google_sheet_error = ""
    failed_pdfs = sum(
        row["status"] in {"EXTRACTION_FAILED", "VALIDATION_FAILED"}
        for row in results
    )
    if args.publish_google_sheet and failed_pdfs:
        google_sheet_error = (
            f"Google Sheet publishing skipped because {failed_pdfs} PDF(s) "
            "failed validation or extraction"
        )
        logging.error(google_sheet_error)
    elif args.publish_google_sheet:
        try:
            if master_count:
                google_sheet = publish_questions_csv(
                    master_csv,
                    spreadsheet_id=args.google_sheet_id,
                    credentials_file=args.google_credentials,
                    tab=args.google_sheet_tab,
                    chunk_rows=args.google_chunk_rows,
                    cdn_prefix=args.cdn_prefix,
                )
                logging.info(
                    "Published %d questions to Google Sheet %s tab %s",
                    master_count, google_sheet["spreadsheet_id"], google_sheet["tab"],
                )
            if answer_count:
                google_answer_sheet = publish_questions_csv(
                    answer_master_csv,
                    spreadsheet_id=args.google_sheet_id,
                    credentials_file=args.google_credentials,
                    tab=args.google_answer_sheet_tab,
                    chunk_rows=args.google_chunk_rows,
                    cdn_prefix=args.cdn_prefix,
                )
                logging.info(
                    "Published %d answers to Google Sheet %s tab %s",
                    answer_count, google_answer_sheet["spreadsheet_id"],
                    google_answer_sheet["tab"],
                )
        except Exception as exc:
            google_sheet_error = str(exc)
            logging.exception("Google Sheets publishing failed")

    report_path.write_text(json.dumps({
        "run_id": run_id,
        "generated_at": now(),
        "results": sorted_results,
        "questions": {"path": str(master_csv), "count": master_count},
        "answer_keys": {"path": str(answer_master_csv), "count": answer_count},
        "google_sheet": google_sheet,
        "google_answer_sheet": google_answer_sheet,
        "google_sheet_error": google_sheet_error,
    }, indent=2, ensure_ascii=False), encoding="utf-8")
    failed = failed_pdfs
    if google_sheet_error and not failed_pdfs:
        failed += 1
    print(
        f"Done. PDFs: {len(results)}, questions: {master_count}, "
        f"answers: {answer_count}, failed: {failed}"
    )
    print(
        f"Questions: {master_csv}\nAnswer keys: {answer_master_csv}\n"
        f"Report: {report_path}\nEvents: {event_path}\nLog: {log_path}"
    )
    for sheet in (google_sheet, google_answer_sheet):
        if sheet:
            print(
                f"Google Sheet: https://docs.google.com/spreadsheets/d/"
                f"{sheet['spreadsheet_id']}/edit (tab: {sheet['tab']})"
            )
    if google_sheet_error:
        print(f"Google Sheet error: {google_sheet_error}")
    sys.exit(1 if failed else 0)


if __name__ == "__main__":
    main()
