# Source exam extractors

Every selectable extraction script lives in this directory, including:

- `default_source_exam_extractor.py` - the application's deterministic default.
- `PDF - Unified Auto.py` - detects a supported PDF layout and runs the
  matching structure-specific extractor.
- Any administrator-approved custom `.py` extractor.
- Six final, manually selected PDF-layout extractors imported from
  `menhadi/pdf-extraction`: standard text/math, JEE/NTA image, CUET-UG image,
  UGC-NET bilingual, GATE, and UPSC 2025 bilingual scans.

The selector always stores and runs the exact filename chosen by the
administrator. There is no hidden application extractor and the application
does not add an AI completion pass after a script finishes.

`PDF - Unified Auto.py` is the explicit automatic option. It detects standard
selectable text/math, JEE/NTA image, CUET-UG image, UGC-NET bilingual, or GATE
PDFs before extraction. Uncertain, unknown, and answer-key-only inputs stop
without replacing drafts. Manual extractor selections remain authoritative and
are never substituted.

The six `PDF - ...` scripts run a local layout probe before extraction. A
mismatched or uncertain PDF stops before drafts are replaced and before any
paid AI request. The answer-key parser is a companion module: when an answer
PDF is attached, supported structured MCQ/MSQ/NAT answers are mapped by printed
question number.

The tested extraction engine is pinned as the `scripts/pdf-extraction-engine`
vendored snapshot of `menhadi/pdf-extraction` commit `65af308`. The `PDF - ...`
application adapters launch that repository's original `batch_extract.py`
process; they do not replace its extractor algorithms. `pdf_extraction/`
contains only the ExamElite output contract and database/image mapping adapter.

The engine is included in the normal ExamElite checkout. Install its Python
requirements after deployment:

```text
python3 -m pip install -r scripts/pdf-extraction-engine/requirements.txt
```

Extraction completes and validates in a temporary local workspace first. Only
then does ExamElite map the resulting CSV/JSON and images into review drafts.

GATE scanned papers and UPSC bilingual scans also require the `tesseract`
system executable. The UPSC extractor is intentionally limited to the original
48-page 2025 General Studies Paper I/II paired bilingual structure.

## Default extractor

`default_source_exam_extractor.py` does not call an AI API. It reads PDF or DOCX
question papers, optionally matches answer and solution sources, extracts
question text, options, images and question-type hints, and writes reviewable
question drafts. Its shared deterministic PDF parser remains in
`scripts/source_pdf_extractor_core.py`.

## Recommended command contract

New extractors should accept:

```text
--questions PATH
--answers PATH             (optional)
--solutions PATH           (optional)
--out DIRECTORY
--public-prefix URL_PREFIX
--paper-code CODE
--profile JSON
--result-file PATH
```

The result file must contain:

```json
{
  "ok": true,
  "questions": [
    {
      "paper_question_number": 1,
      "printed_question_number": "1",
      "question": "Question text",
      "options": ["A", "B", "C", "D"],
      "question_type": "MCQ",
      "correct_answer": "A",
      "explanation": "Optional solution"
    }
  ]
}
```

Legacy interactive scripts are also supported. Each legacy run receives its own
isolated input and working directories, so four papers can run independently
without sharing output files.

## API behavior

API keys, models and priorities from Admin AI Settings are supplied to each
process as environment variables. A script that reads those variables uses the
admin configuration. A script with a hard-coded provider continues to use its
own hard-coded behavior. The selected Python script is solely responsible for
any API call made during extraction.

## Image storage contract

Every current and future Python extractor must download or extract its question
and option images into the directory supplied by `--out`, and must build image
URLs from `--public-prefix`. The application never stores a CDN image URL. If a
script returns an external URL, the importer rewrites it to this exam's local
`/storage/question-images/.../images/` URL only when a matching downloaded file
exists there; otherwise it stops the import. Normal non-image source hyperlinks
remain unchanged.

## Extractor isolation

The extractor selected by the administrator is authoritative. The application
does not reroute a selected paper to another PDF parser. Shared integration code
only maps the selected script's CSV/JSON fields and copies its extracted images.

The application does not retry a zero-result extraction with different OCR,
detection or parsing logic. A zero-result or failed selected script stops the
import without creating drafts, so the administrator can select or upload the
correct tested script. Additional fields returned by a script are retained as
extractor metadata for numbering, ordering and answer matching.

`PDF - CUET UG Image.py` remains limited to its own Item-No layout. An NTA
`Question Number` / `Options` paper must use `PDF - JEE NTA Image.py`; selecting
CUET never silently invokes the JEE parser.

## Claude structure-specific extractors

`JAM Exam.py` and `Study inovation two parts.py` use the Anthropic Message
Batches API. They read `ANTHROPIC_API_KEY` and `ANTHROPIC_MODEL` from Admin AI
Settings (with `CLAUDE_MODEL` retained as a standalone override). They never
silently switch providers.

Both scripts implement the application command contract and return MCQ, MSQ,
NAT, descriptive and matching question fields in the current database shape.
Claude-provided diagram and pictorial-option regions are cropped from the
original PDF. The application supplies the destination, so images are written
to `group/category/subcategory/package/exam/images`; filenames include the
printed question number and `question` or `optionN`, and the saved image URL is
embedded in the corresponding question/option HTML.

`Study inovation two parts.py` also submits boundary-page bridge groups, merges
continuations by section plus printed question number, and enforces an exact
one-to-one checkpoint between extracted questions and answer-key entries. A
missing, extra, duplicate or conflicting answer stops the import before drafts
are replaced. Both scripts require Poppler plus the Python packages `anthropic`,
`pdf2image`, `pillow`, and `numpy`.
