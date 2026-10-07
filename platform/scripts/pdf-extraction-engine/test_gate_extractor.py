import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import fitz

from gate_standard_extractor import (SCAN_Q_RE, _extract_scanned, _linked_passage_ranges, _next_number, detect_gate_layout, gate_content_page_limit)
from pdf_detector import detect_pdf


class GateExtractorTests(unittest.TestCase):
    def test_scan_question_labels_accept_observed_ocr_punctuation(self):
        cases = {
            r"\ Q.40 Let": "40",
            "Q.47__ For": "47",
            "_Q.51 The": "51",
            "Q.53_ The": "53",
        }
        for text, expected in cases.items():
            self.assertEqual(SCAN_Q_RE.match(text).group(1), expected)
        self.assertIsNone(SCAN_Q_RE.match("0.2 and a natural frequency"))

    def test_linked_passage_is_separated_before_next_question(self):
        lines = [
            {"text": "Q.81 Prompt", "y0": 10},
            {"text": "(D) final option", "y0": 20},
            {"text": "Statement for Linked Answer Questions 82 & 83: Shared setup", "y0": 30},
            {"text": "with an equation", "y0": 40},
            {"text": "Q.82 First linked question", "y0": 50},
        ]
        passages = _linked_passage_ranges(lines, [(0, "81"), (4, "82")])
        self.assertEqual(passages[0]["targets"], ["82", "83"])
        self.assertEqual(passages[0]["start_index"], 2)
        self.assertEqual(passages[0]["end_index"], 4)
        self.assertEqual(passages[0]["text"], "Shared setup\nwith an equation")
    def test_common_data_passage_heading_is_recognized(self):
        lines = [
            {"text": "Q.73 Prompt", "y0": 10},
            {"text": "(D) 0.4c", "y0": 20},
            {"text": "Common Data for Questions 74 & 75: Consider the system", "y0": 30},
            {"text": "shown below", "y0": 40},
            {"text": "Q.74 First shared-data question", "y0": 50},
        ]
        passage = _linked_passage_ranges(lines, [(0, "73"), (4, "74")])[0]
        self.assertEqual(passage["targets"], ["74", "75"])
        self.assertEqual(passage["text"], "Consider the system\nshown below")
    def test_gate_section_question_numbers_continue_after_ga(self):
        state = {"offset": 0, "last": 0, "maximum": 0}
        values = [_next_number(value, state) for value in [1, 2, 9, 10, 1, 2, 55]]
        self.assertEqual(values, ["1", "2", "9", "10", "11", "12", "65"])

    def test_answer_key_pages_are_excluded_from_gate_content(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / "AE2020.pdf"
            doc = fitz.open()
            doc.new_page().insert_text((50, 70), "Q.No. 54")
            doc.new_page().insert_text((50, 70), "Q.No. 55")
            doc.new_page().insert_text((50, 70), "Answer Key - AE: Aerospace Engineering")
            doc.new_page().insert_text((50, 70), "34 4 MCQ AE B 2")
            doc.save(path)
            doc.close()
            self.assertEqual(gate_content_page_limit(path), 2)
    def test_portal_layout_uses_repeated_question_number_markers(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / "Aerospace Engineering2017 (AE2017).pdf"
            doc = fitz.open()
            page = doc.new_page()
            page.insert_text((50, 70), "Graduate Aptitude Test in Engineering 2017")
            for index in range(1, 7):
                page.insert_text((50, 80 + index * 20), f"Question Number : {index}")
            doc.save(path)
            doc.close()
            self.assertEqual(detect_gate_layout(path), "gate-portal-image")
            self.assertEqual(detect_pdf(path).extractor, "gate_pdf")

    def test_scanned_extraction_resumes_after_completed_page(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            pdf = root / "AE2007.pdf"
            output = root / "output"
            doc = fitz.open()
            doc.new_page()
            doc.new_page()
            doc.save(pdf)
            doc.close()
            first = [
                {"text": "Q.1 First", "x0": 10, "y0": 20, "x1": 100, "y1": 30},
                {"text": "(A) a (B) b (C) c (D) d", "x0": 10, "y0": 40, "x1": 180, "y1": 50},
            ]
            second = [
                {"text": "Q.2 Second", "x0": 10, "y0": 20, "x1": 100, "y1": 30},
                {"text": "(A) e (B) f (C) g (D) h", "x0": 10, "y0": 40, "x1": 180, "y1": 50},
            ]
            with patch("gate_standard_extractor._ocr_lines", side_effect=[first, RuntimeError("interrupted")]):
                with self.assertRaises(RuntimeError):
                    _extract_scanned(pdf, root, output, "AE2007", resume=False)
            checkpoint = output / "AE2007" / "scan_checkpoint.json"
            self.assertTrue(checkpoint.exists())
            with patch("gate_standard_extractor._ocr_lines", return_value=second) as ocr:
                result = _extract_scanned(pdf, root, output, "AE2007", resume=True)
            self.assertEqual(result[-1], 2)
            self.assertEqual(ocr.call_count, 1)


if __name__ == "__main__":
    unittest.main()
