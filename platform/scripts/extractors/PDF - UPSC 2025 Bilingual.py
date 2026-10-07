"""ExamElite contract: --questions PATH --result-file PATH."""

import sys
from pathlib import Path

LIBRARY = Path(__file__).resolve().parent / "pdf_extraction"
sys.path.insert(0, str(LIBRARY))

from examelite_adapter import run

if __name__ == "__main__":
    raise SystemExit(run("upsc_bilingual"))
