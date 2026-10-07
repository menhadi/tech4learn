import csv
import tempfile
import unittest
from pathlib import Path

from answer_key_extractor import normalize_answer, write_answer_key_outputs


SOURCE = Path(r"C:\Users\menha\Downloads\AE_Keys.pdf")


class AnswerKeyExtractorTests(unittest.TestCase):
    def test_normalizes_each_answer_type(self):
        self.assertEqual(normalize_answer("MCQ", "B")["correct_option"], "B")
        self.assertEqual(normalize_answer("MSQ", "A;B;C")["correct_options"], "A;B;C")
        nat = normalize_answer("NAT", "0.16 to 0.17")
        self.assertEqual((nat["answer_min"], nat["answer_max"]), ("0.16", "0.17"))

    @unittest.skipUnless(SOURCE.exists(), "AE_Keys.pdf fixture is unavailable")
    def test_extracts_complete_ae2025_answer_key(self):
        with tempfile.TemporaryDirectory() as temp:
            csv_path, json_path, report_path, report = write_answer_key_outputs(SOURCE, temp)
            with csv_path.open(encoding="utf-8-sig", newline="") as source:
                rows = list(csv.DictReader(source))
            self.assertTrue(json_path.exists())
            self.assertTrue(report_path.exists())
        self.assertEqual(len(rows), 65)
        self.assertEqual(report["question_types"], {"MCQ": 35, "MSQ": 2, "NAT": 28})
        self.assertEqual(rows[0]["correct_option"], "A")
        self.assertEqual(rows[37]["correct_options"], "A;B;C")
        self.assertEqual((rows[33]["answer_min"], rows[33]["answer_max"]), ("0.16", "0.17"))
        self.assertEqual(rows[-1]["question_no"], "65")
        self.assertEqual(rows[-1]["paper_code"], "AE")
        self.assertEqual(rows[-1]["exam_year"], "2025")
        self.assertEqual(rows[-1]["exam_name"], "GATE")


if __name__ == "__main__":
    unittest.main()