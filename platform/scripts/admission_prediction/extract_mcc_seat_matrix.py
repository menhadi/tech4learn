#!/usr/bin/env python3
import json
import re
import sys
from pathlib import Path

import pdfplumber

FIELDS = [
    "state_name",
    "institution_type",
    "institution_name",
    "quota",
    "program_name",
    "category",
    "seat_count",
    "seat_gender",
]


def clean(value):
    return re.sub(r"\s+", " ", str(value or "")).strip()


def main():
    if len(sys.argv) != 2:
        raise SystemExit("Usage: extract_mcc_seat_matrix.py <seat-matrix.pdf>")

    path = Path(sys.argv[1]).resolve()
    if not path.is_file():
        raise SystemExit(f"PDF not found: {path}")

    with pdfplumber.open(path) as document:
        for page_number, page in enumerate(document.pages, 1):
            for table in page.extract_tables():
                header_seen = False
                for raw_row in table:
                    row = [clean(value) for value in (raw_row or [])]
                    if "StateName" in row and "TotalSeats" in row:
                        header_seen = True
                        continue
                    if not header_seen or len(row) < len(FIELDS):
                        continue

                    row = row[: len(FIELDS)]
                    if not row[6].isdigit():
                        continue

                    record = dict(zip(FIELDS, row))
                    match = re.search(r"\((\d{6})\)\s*$", record["institution_name"])
                    record.update(
                        {
                            "record_type": "seat_matrix",
                            "institution_code": match.group(1) if match else None,
                            "seat_count": int(record["seat_count"]),
                            "provenance": {"page": page_number},
                        }
                    )
                    print(json.dumps(record, ensure_ascii=True, separators=(",", ":")))


if __name__ == "__main__":
    main()
