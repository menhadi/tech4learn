# Unified PDF extraction

batch_extract.py detects each PDF layout and dispatches the matching CUET-UG, UGC-NET bilingual, JEE/NTA image, selectable text/math, GATE, or structured answer-key extractor. DOCX is intentionally excluded. Answer-key PDFs run in the same unified batch but remain separate from question data.

## Install and check keys

PowerShell:

    cd "C:\Users\menha\Documents\New project"
    python -m pip install -r requirements.txt
    python batch_extract.py --input-root "D:\Contents\PYQ" --output-root "D:\ExamElite\Extracted" --check-api-env

The key check prints booleans and model names only. Equation OCR uses OPENAI_API_KEY with OPENAI_VISION_MODEL (default gpt-5-mini). Scanned GATE questions are verified from their preserved question crops. If Mini returns invalid structured math, Mathpix is used through MATHPIX_APP_ID and MATHPIX_APP_KEY as an OCR reference, then Mini structures the corrected result into the standard fields.

## Run all PDFs

    python batch_extract.py --input-root "D:\Contents\PYQ" --output-root "D:\ExamElite\Extracted_Final" --recursive --workers 1 --resume --ai-math auto --max-ai-calls 500 --api-timeout 180

Strict accuracy is enabled by default: papers with missing scanned question numbers, exhausted AI verification, or rejected Vision output are not published into the master CSV. Use `--allow-review-warnings` only to create intentionally partial review output.

Numerical-answer questions with no source options are marked `NAT`. Empty option fields are immutable: AI output that invents an answer choice for an empty source option is rejected. In automatic mode, only fields that require Vision are replaced; already-correct Unicode question or option fields remain unchanged.

All detected questions in scanned GATE papers are Vision-verified because scan OCR is not treated as authoritative. For selectable PDFs, the ai-math auto mode keeps clean locally extracted Unicode text, symbols, and chemical formulas without API calls. It sends only questions with broken glyphs, heavily fragmented equations, matrices, differential notation, piecewise layout, or two-dimensional operators such as integrals and summations. Each question is rendered as one bounded crop containing its question and options. A question spanning two pages can produce two crops. The call limit is per PDF.

The CSV stores MathJax source, not the generated `<mjx-container>`/MathML/SVG browser DOM. MathJax creates that rendered DOM at display time; storing it would be bulky and renderer-version dependent.

Complex AI-transcribed cells use CKEditor 4 HTML. Prose is stored in `<p>` tags, inline math as `<span class="math-tex">\( ... \)</span>`, and display math as `<p><span class="math-tex">\[ ... \]</span></p>`. Import these cells as HTML/source content. Tables remain a single HTML `<table>` and are not duplicated as plain text. Question numbers stay only in the `question_no` column.

Math is Unicode-first. The local extractor uses PDF span size and baseline geometry to preserve simple superscripts and subscripts without an API call. Vision is used when a stacked fraction, broken glyph, matrix, cases expression, differential equation, or other two-dimensional structure cannot be represented safely as local Unicode. Matrices, determinants, cases, and complex equations must pass strict MathJax structure validation.

## Output

The output root contains separate import files for question papers and answer keys:

    D:\ExamElite\Extracted_Final\questions.csv
    D:\ExamElite\Extracted_Final\answer_keys.csv

Rows are ordered by input folder hierarchy and then question number. The final CSV and Google Sheet omit all 12 redundant `*_images` columns; image tags remain embedded in the matching passage, question, and option cells. Internal manifests retain relative paths and CDN URLs for upload verification. Every paper keeps its images in the corresponding hierarchy:

    D:\ExamElite\Extracted_Final\<source folders>\<paper>\images\

Final question, option, and passage cells replace local image markers with `<img alt="<exam> Question <number> <language>" src="https://cdn.examelite.com/<existing hierarchy>"/>`. Image columns contain the full CDN URLs. Image manifests preserve `relative_path` for uploading and add a `cdn_url` field. The default prefix is `https://cdn.examelite.com/`; override it with `--cdn-prefix` when required. The CDN finalizer applies dynamically to every current or future column ending in `_images` and its matching content field. Publication fails if any relative image marker, non-CDN URL, or missing manifest CDN URL remains; the same validation runs before Google Sheets upload.

Per-paper standardized rows and image manifests are retained as JSON, not CSV. The _batch folder contains batch_report.json, append-only batch_events.jsonl, run_<timestamp>.log, and processing_state.json. Every paper also has validation_report.json and, when AI is used, ai_math_report.json with provider attempts and estimated OpenAI/Mathpix cost.

Use `--resume` for every production run. Completed PDFs are skipped and the master `questions.csv` is rebuilt deterministically. Interrupted scan OCR resumes after its last completed page, and interrupted AI processing resumes at its next unverified question. Active checkpoints are stored under `_batch/staging/active`; do not delete that folder while a batch is incomplete. Resume with the same input root, output root, extractor, AI mode, model, call limit, and accuracy policy.

Changing AI mode, vision model, extractor selection, call budget, CDN prefix, or processing profile automatically invalidates resume state and reprocesses the affected PDFs.

## GATE Aerospace papers

The `gate_pdf` extractor is auto-selected from the PDF content and source identity. It has three internal modes:

- Selectable/vector papers use coordinate-aware question and option parsing and preserve embedded/vector figures.
- GATE portal exports preserve their native question and option images.
- Image-only scans use local Tesseract OCR and preserve one original question crop for every detected question. Install the Tesseract executable separately and ensure `tesseract --version` works in PowerShell.

Run only this family when reviewing a new batch:

    python batch_extract.py --input-root "D:\Contents\PYQ\GATE\Aerospace Engineering (AE)" --output-root "D:\ExamElite\GATE_AE_Review" --workers 1 --resume --extractor gate_pdf --ai-math auto --max-ai-calls 500 --api-timeout 180

Review `extraction_manifest.json` warnings for scanned papers before import. The supplied AE2013 file contains zero readable PDF pages and must be repaired or replaced. Low-quality scans can also report missing question numbers; their original crops remain available for verification.
## Google Sheets publishing

The pipeline keeps one fixed header for every PDF layout. Use the `extractor_type` column and `metadata_json` layout value to identify the parser that produced a row. Separate headers are intentionally avoided because they would break one-table database imports.

Place `service-account.json` in the project folder. The file is excluded from Git. Share the target spreadsheet with the service account's `client_email` as an Editor, and ensure the Google Sheets API is enabled for its Google Cloud project.

Answer-key PDFs are published to a separate Google Sheet tab. The defaults are `data` for questions and `answer_keys` for answers. Override them with `--google-sheet-tab` and `--google-answer-sheet-tab`.
Publish automatically after a successful extraction batch:

    python batch_extract.py --input-root "D:\Contents\PYQ" --output-root "D:\ExamElite\Extracted_Final" --recursive --workers 1 --resume --ai-math auto --publish-google-sheet --google-sheet-id "1ZMpRmB1Kd1X7PXZKEshWUzGcqZAUZ4BMG4pVpclgkQo" --google-credentials "service-account.json" --google-sheet-tab "data"

Publish an already generated master CSV without re-running extraction:

    python google_sheets_output.py --csv "D:\ExamElite\Extracted_Final\questions.csv" --google-sheet-id "1ZMpRmB1Kd1X7PXZKEshWUzGcqZAUZ4BMG4pVpclgkQo" --google-credentials "service-account.json" --google-sheet-tab "data"

The publisher replaces only the selected tab and writes with `RAW` value handling. HTML, image tags, Unicode, and MathJax backslashes therefore remain literal strings. Google Sheets stores these source strings but does not render HTML or MathJax. Publishing is skipped if any PDF fails extraction or validation, protecting the existing production tab from partial batch output. Upload status or failure is recorded in `batch_report.json` and the run log.

## Math display size

Do not put font-size markup into extracted question data. Keep MathJax source portable and control its rendered size in ExamElite/CKEditor CSS. A modest increase is appropriate if equations look smaller than surrounding prose:

    .cke_editable mjx-container,
    .question-content mjx-container {
        font-size: 108% !important;
    }

Start at `108%`; values around `105%` to `112%` are normally comfortable. Apply the rule in both CKEditor content CSS and the exam question frontend so editing and delivery match.
