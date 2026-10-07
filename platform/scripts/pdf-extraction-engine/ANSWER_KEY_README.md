# Answer-key PDF extractor

The answer-key extractor can run directly or through the unified `batch_extract.py` dispatcher. Unified runs produce `answer_keys.csv` and publish it to a separate `answer_keys` Google Sheet tab; answer rows are never mixed with `questions.csv` or the `data` tab. It does not create question text, option content, images, or CDN fields.

## Output schema

`answer_keys.csv` and `answer_keys.json` include common document identity plus answer-specific fields:

- `exam_name`, `exam_year`, `paper_code`, `session`
- `question_no`, `question_id`, `question_type`, `section`
- `correct_option` for MCQ
- `correct_options` for MSQ, separated by semicolons
- `answer_min` and `answer_max` for NAT
- `key_or_range_raw` preserving the source value
- `marks`, `source_page`, and `metadata_json`

The extractor validates supported answer types, option letters, numeric range order, duplicate question numbers, and missing numbers.

## Run

```powershell
python answer_key_extractor.py `
  --pdf "C:\Users\menha\Downloads\AE_Keys.pdf" `
  --out "C:\Users\menha\Documents\New project\answer_key_output"
```

No OCR or AI API is used when the answer key contains a detectable PDF table.