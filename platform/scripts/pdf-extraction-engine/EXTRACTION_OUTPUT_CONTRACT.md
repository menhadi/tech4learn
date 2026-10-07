# Extraction Output Contract

Every extractor receives four identity arguments:

```text
--pdf or --docx <source file>
--input-root <common source root>
--output-root <common extraction root>
--paper-code <paper identifier>
```

The source file must be inside `input-root`. The output directory mirrors the
source hierarchy and adds a final directory named after the source file:

```text
<output-root>/<relative source folders>/<source stem>/
```

Each paper output contains:

```text
questions.csv
questions.json
images_manifest.csv
extraction_manifest.json
images/
```

Image filenames contain a normalized relative hierarchy, padded question
number, semantic role, and image index:

```text
<document-key>__q0001__question__img01.jpeg
<document-key>__q0001__option1__img01.jpeg
```

Internal per-paper extraction rows use `STANDARD_QUESTION_FIELDS`, including image-reference columns needed for validation. The final master `questions.csv` and Google Sheet use `DELIVERY_QUESTION_FIELDS`; the 12 redundant `*_images` columns are omitted because their CDN `<img>` tags are already embedded in the corresponding passage, question, and option content cells.
Unavailable metadata stays blank. Extractor-specific metadata is preserved in
`metadata_json`.

`images_manifest.csv` is authoritative for upload ordering and contains both
the original source order and standardized display order. Image references in
`questions.csv` are paths relative to `output-root`, using `/` separators.
