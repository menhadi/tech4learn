#!/usr/bin/env python3
"""Extract normalized cutoff groups from an official MCC UG allotment-result PDF."""

from __future__ import annotations

import gc
import json
import re
import sys
from collections import Counter
from pathlib import Path

import pdfplumber


def clean(value: object) -> str:
    return re.sub(r"\s+", " ", str(value or "")).strip()


def integer(value: object) -> int | None:
    digits = re.sub(r"[^0-9]", "", clean(value))
    return int(digits) if digits else None


def header_index(rows: list[list[object]]) -> int | None:
    for index, row in enumerate(rows[:4]):
        normalized = [clean(cell).lower().replace("\n", " ") for cell in row]
        if "rank" in normalized and any("institute" in cell for cell in normalized):
            return index
    return None


def extract(path: Path, max_pages: int | None = None) -> list[dict]:
    groups: dict[tuple[str, str, str, str], dict] = {}

    with pdfplumber.open(path) as pdf:
        pages = pdf.pages[:max_pages] if max_pages else pdf.pages
        for page_number, page in enumerate(pages, start=1):
            if page_number % 100 == 0:
                print(f"Processed {page_number} pages", file=sys.stderr, flush=True)
            for table in page.extract_tables():
                if not table:
                    continue
                start = header_index(table)
                rows = table[start + 1 :] if start is not None else table
                for row in rows:
                    if len(row) < 8:
                        continue
                    serial = integer(row[0])
                    rank = integer(row[1])
                    quota = clean(row[2])
                    institute = clean(row[3])
                    course = clean(row[4])
                    allotted_category = clean(row[5])
                    candidate_category = clean(row[6])
                    remarks = clean(row[7])
                    if serial is None or rank is None or not institute or not course:
                        continue
                    if remarks and "allot" not in remarks.lower():
                        continue

                    key = (quota, institute, course, allotted_category)
                    group = groups.setdefault(
                        key,
                        {
                            "record_type": "opening_closing_rank",
                            "counselling_body": "MCC",
                            "quota": quota,
                            "institute_name": institute,
                            "program_name": course,
                            "category": allotted_category,
                            "opening_rank": rank,
                            "closing_rank": rank,
                            "allotment_count": 0,
                            "candidate_category_counts": Counter(),
                            "provenance": {"page_first": page_number, "page_last": page_number},
                        },
                    )
                    group["opening_rank"] = min(group["opening_rank"], rank)
                    group["closing_rank"] = max(group["closing_rank"], rank)
                    group["allotment_count"] += 1
                    group["candidate_category_counts"][candidate_category or "Unknown"] += 1
                    group["provenance"]["page_last"] = page_number

            # Release pdfplumber's cached page layout before advancing. Without
            # this, large official PDFs retain every page cache until close.
            page.close()
            if page_number % 25 == 0:
                gc.collect()

    records = []
    for group in groups.values():
        group["candidate_category_counts"] = dict(sorted(group["candidate_category_counts"].items()))
        records.append(group)

    records.sort(
        key=lambda row: (
            row["closing_rank"],
            row["institute_name"],
            row["program_name"],
            row["quota"],
            row["category"],
        )
    )
    return records


def main() -> int:
    if len(sys.argv) not in (2, 3):
        print("Usage: extract_mcc_allotment_result.py <official-result.pdf> [max-pages]", file=sys.stderr)
        return 2

    path = Path(sys.argv[1]).resolve()
    if not path.is_file():
        print(f"PDF is not readable: {path}", file=sys.stderr)
        return 2

    max_pages = int(sys.argv[2]) if len(sys.argv) == 3 else None
    records = extract(path, max_pages)
    if not records:
        print("No MCC allotment rows were extracted.", file=sys.stderr)
        return 1

    for record in records:
        print(json.dumps(record, ensure_ascii=True, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
