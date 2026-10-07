# PDF extractor source

These structure-specific modules were imported from the private repository
`menhadi/pdf-extraction`, commit `7e2c992` on branch
`codex/bilingual-two-part-pdf-extractor`.

The imported repository test suite passed all 76 tests before integration.
`examelite_adapter.py` is the application-specific contract adapter. The
selectable wrapper scripts remain one directory above this library so only the
six intentional layouts appear in the administrator extractor selector.

The unified dispatcher, DOCX prototype, Google Sheets publisher, CDN publisher,
and Khan Academy scraper are intentionally not included.
