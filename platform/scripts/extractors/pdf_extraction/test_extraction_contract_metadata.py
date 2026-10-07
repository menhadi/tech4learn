import csv
import json
import tempfile
import unittest
from pathlib import Path

from extraction_contract import write_standard_questions_csv


class ExtractionContractMetadataTest(unittest.TestCase):
    def test_unsupported_script_fields_are_preserved_as_metadata(self):
        with tempfile.TemporaryDirectory() as temporary:
            path = Path(temporary) / "questions.csv"
            write_standard_questions_csv(
                [{
                    "question_no": "12",
                    "question": "Question",
                    "metadata_json": {"sequence_key": "part-a-12"},
                    "source_option_ids": ["a1", "a2", "a3", "a4"],
                }],
                path,
            )
            with path.open("r", encoding="utf-8-sig", newline="") as source:
                row = next(csv.DictReader(source))

        metadata = json.loads(row["metadata_json"])
        self.assertEqual(metadata["sequence_key"], "part-a-12")
        self.assertEqual(
            metadata["extra_fields"]["source_option_ids"],
            ["a1", "a2", "a3", "a4"],
        )


if __name__ == "__main__":
    unittest.main()
