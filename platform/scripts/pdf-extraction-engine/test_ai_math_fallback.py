import csv
import json
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import fitz
from PIL import Image

from ai_math import _question_clip, enhance_math_output, normalize_ckeditor_html, vision_verification_reasons


class AIMathFallbackTests(unittest.TestCase):
    @patch("ai_math._mathpix")
    @patch("ai_math._openai")
    def test_invalid_mini_result_uses_mathpix_reference(self, openai, mathpix):
        mathpix.return_value = ("A 2n by 2n matrix", {"requests": 1})
        openai.side_effect = [
            ({"question": "lost"}, "openai_vision"),
            ({
                "question": '<p>Find <span class="math-tex">\\(x^2\\)</span></p>',
                "option1": "<p>1</p>", "option2": "<p>2</p>", "option3": "<p>3</p>", "option4": "<p>4</p>",
            }, "openai_vision_mathpix_reference"),
        ]
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            pdf = root / "paper.pdf"
            doc = fitz.open()
            doc.new_page().insert_text((50, 70), "Q.1 Find x^2")
            doc.save(pdf)
            doc.close()
            fields = ["question_no", "question", "option1", "option2", "option3", "option4", "source_pages"]
            with (root / "questions.csv").open("w", encoding="utf-8-sig", newline="") as target:
                writer = csv.DictWriter(target, fieldnames=fields)
                writer.writeheader()
                writer.writerow({"question_no": "1", "question": "Find x^2", "option1": "1", "option2": "2", "option3": "3", "option4": "4", "source_pages": "1"})
            (root / "questions.json").write_text(json.dumps({"questions": [{"question_no": "1", "question_text": "Find x^2", "options": {label: {"text": str(index), "images": []} for index, label in enumerate("ABCD", 1)}}]}), encoding="utf-8")
            report = enhance_math_output(pdf, root, mode="always", max_calls=3)
            self.assertEqual(report["enhanced"], 1)
            self.assertEqual(report["calls"], 3)
            self.assertEqual(openai.call_count, 2)
            self.assertTrue(mathpix.called)
            with (root / "questions.csv").open(encoding="utf-8-sig", newline="") as source:
                row = next(csv.DictReader(source))
            self.assertEqual(row["question"], '<p>Find <span class="math-tex">\\(x^2\\)</span></p>')
            openai.reset_mock()
            mathpix.reset_mock()
            resumed = enhance_math_output(pdf, root, mode="always", max_calls=3)
            self.assertEqual(resumed["enhanced"], 1)
            openai.assert_not_called()
            mathpix.assert_not_called()

    @patch("ai_math._mathpix")
    @patch("ai_math._openai")
    def test_fragmented_matrix_uses_mini_without_fallback(self, openai, mathpix):
        openai.return_value = ({
            "question": '<p>A <span class="math-tex">\\(2n \\times 2n\\)</span> matrix <span class="math-tex">\\(A=[a_{ij}]\\)</span></p>',
            "option1": "<p>1</p>", "option2": "<p>2</p>", "option3": "<p>3</p>", "option4": "<p>4</p>",
        }, "openai_vision")
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            pdf = root / "paper.pdf"
            doc = fitz.open()
            doc.new_page().insert_text((50, 70), "Q.11 matrix")
            doc.save(pdf)
            doc.close()
            fields = ["question_no", "question", "option1", "option2", "option3", "option4", "source_pages"]
            with (root / "questions.csv").open("w", encoding="utf-8-sig", newline="") as target:
                writer = csv.DictWriter(target, fieldnames=fields)
                writer.writeheader()
                writer.writerow({"question_no": "11", "question": "A 2\n2\nn\nn\n\uf0d7 matrix", "option1": "1", "option2": "2", "option3": "3", "option4": "4", "source_pages": "1"})
            (root / "questions.json").write_text(json.dumps({"questions": [{"question_no": "11", "question_text": "broken matrix", "options": {label: {"text": str(index), "images": []} for index, label in enumerate("ABCD", 1)}}]}), encoding="utf-8")
            report = enhance_math_output(pdf, root, mode="always", max_calls=3)
            self.assertEqual(report["calls"], 1)
            self.assertTrue(report["events"][0]["vision_reasons"])
            mathpix.assert_not_called()
            with (root / "questions.csv").open(encoding="utf-8-sig", newline="") as source:
                row = next(csv.DictReader(source))
            self.assertIn(r"2n \times 2n", row["question"])

    @patch("ai_math._mathpix")
    @patch("ai_math._openai")
    def test_ai_updates_only_fields_that_require_vision(self, openai, mathpix):
        original_question = "If \U0001d443\U0001d452\u02e3 = \U0001d444\U0001d452\u207b\u02e3, choose the statement."
        openai.return_value = ({
            "question": "<p>If Peˣ = Qe⁻ˣ, choose the statement.</p>",
            "option1": "<p>P = Q = 0</p>",
            "option2": "<p>P = Q = 1</p>",
            "option3": "<p>P = 1; Q = -1</p>",
            "option4": "<p>P\u2044Q = 0</p>",
        }, "openai_vision", {})
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            pdf = root / "paper.pdf"
            doc = fitz.open()
            doc.new_page().insert_text((50, 70), "Q.3")
            doc.save(pdf)
            doc.close()
            fields = ["question_no", "question", "option1", "option2", "option3", "option4", "source_pages"]
            with (root / "questions.csv").open("w", encoding="utf-8-sig", newline="") as target:
                writer = csv.DictWriter(target, fieldnames=fields)
                writer.writeheader()
                writer.writerow({
                    "question_no": "3", "question": original_question,
                    "option1": "P = Q = 0", "option2": "P = Q = 1",
                    "option3": "P = 1; Q = -1", "option4": "P\nQ=0",
                    "source_pages": "1",
                })
            (root / "questions.json").write_text(json.dumps({"questions": [{
                "question_no": "3", "question_text": original_question,
                "options": {label: {"text": "", "images": []} for label in "ABCD"},
            }]}), encoding="utf-8")

            report = enhance_math_output(pdf, root, mode="auto", max_calls=1)

            self.assertEqual(report["enhanced"], 1)
            with (root / "questions.csv").open(encoding="utf-8-sig", newline="") as source:
                row = next(csv.DictReader(source))
            self.assertEqual(row["question"], original_question)
            self.assertEqual(row["option1"], "P = Q = 0")
            self.assertEqual(row["option4"], "<p>P\u2044Q = 0</p>")
            mathpix.assert_not_called()
    @patch("ai_math._mathpix")
    @patch("ai_math._openai")
    def test_auto_mode_writes_image_only_transcription_and_removes_source_image(self, openai, mathpix):
        openai.return_value = ({
            "question": "<p>Transcribed embedded question</p>",
            "option1": "<p>A</p>", "option2": "<p>B</p>",
            "option3": "<p>C</p>", "option4": "<p>D</p>",
            "visual_regions": {"question": []},
        }, "openai_vision", {})
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            (root / "images").mkdir()
            Image.new("RGB", (120, 40), "white").save(root / "images" / "q1.png")
            pdf = root / "paper.pdf"
            doc = fitz.open(); doc.new_page(); doc.save(pdf); doc.close()
            fields = [
                "question_no", "question_type", "question", "option1", "option2", "option3", "option4",
                "question_images", "option1_images", "option2_images", "option3_images", "option4_images",
                "source_pages", "metadata_json",
            ]
            row = {field: "" for field in fields}
            row.update({
                "question_no": "1", "question_type": "MCQ",
                "question": "<p>[image: q1.png]</p>", "question_images": "q1.png",
                "option1": "A", "option2": "B", "option3": "C", "option4": "D",
                "source_pages": "1", "metadata_json": json.dumps({"layout": "gate-selectable"}),
            })
            with (root / "questions.csv").open("w", encoding="utf-8-sig", newline="") as target:
                writer = csv.DictWriter(target, fieldnames=fields); writer.writeheader(); writer.writerow(row)
            (root / "questions.json").write_text(json.dumps({"questions": [{
                "question_no": "1", "question_text": row["question"],
                "question_images": ["q1.png"],
                "options": {label: {"text": value, "images": []} for label, value in zip("ABCD", "ABCD")},
            }]}), encoding="utf-8")

            report = enhance_math_output(pdf, root, mode="auto", max_calls=1)
            self.assertEqual(report["enhanced"], 1)
            with (root / "questions.csv").open(encoding="utf-8-sig", newline="") as source:
                result = next(csv.DictReader(source))
            self.assertEqual(result["question"], "<p>Transcribed embedded question</p>")
            self.assertEqual(result["question_images"], "")
            mathpix.assert_not_called()
    def test_question_crop_ignores_section_range_header(self):
        with tempfile.TemporaryDirectory() as temp:
            pdf = Path(temp) / "crop.pdf"
            doc = fitz.open()
            page = doc.new_page()
            page.insert_text((50, 70), "Q.11 - Q.35 Carry ONE mark Each")
            page.insert_text((50, 110), "Q.11")
            page.insert_text((50, 140), "A 2n x 2n matrix")
            page.insert_text((50, 300), "Q.12")
            page.insert_text((50, 330), "Next question")
            clip = _question_clip(page, "11")
            doc.close()
            self.assertGreater(clip.y0, 80)
            self.assertLess(clip.y0, 120)
            self.assertGreater(clip.y1, 250)
            self.assertLess(clip.y1, 310)

    def test_normalizer_wraps_only_undelimited_math_spans(self):
        missing = '<p><span class="math-tex">R_{1}+R_{3}</span></p>'
        partial = '<p><span class="math-tex">\\(R_{1}+R_{3}</span></p>'
        self.assertEqual(
            normalize_ckeditor_html(missing),
            '<p><span class="math-tex">\\(R_{1}+R_{3}\\)</span></p>',
        )
        self.assertEqual(normalize_ckeditor_html(partial), partial)

    def test_normalizer_splits_display_math_and_wraps_bare_inline_math(self):
        supplied = (
            '<p>Matrix <span class="math-tex">'
            r'\[A=\begin{pmatrix}1&0\\0&1\end{pmatrix}\]'
            r'</span>, where \(x\) is real.</p>'
        )
        normalized = normalize_ckeditor_html(supplied)
        self.assertEqual(
            normalized,
            '<p>Matrix</p><p><span class="math-tex">'
            r'\[A=\begin{pmatrix}1&0\\0&1\end{pmatrix}\]'
            '</span></p><p>, where <span class="math-tex">'
            r'\(x\)</span> is real.</p>',
        )
    def test_detector_keeps_plain_unicode_and_preserved_tex_local(self):
        plain = {"question": "Who wrote this book?", "option1": "A", "option2": "B", "option3": "C", "option4": "D"}
        unicode_text = dict(plain, question="If \u03b2 \u00d7 2 \u2264 \u221a9, balance H2O + CO2 \u2192 H2CO3")
        preserved = dict(plain, question=r"A 2n \times 2n matrix with a_{ij}=\beta")
        self.assertEqual(vision_verification_reasons(plain), [])
        self.assertEqual(vision_verification_reasons(unicode_text), [])
        self.assertEqual(vision_verification_reasons(preserved), [])

    def test_detector_flags_broken_and_two_dimensional_math(self):
        broken = {"question": "A 2\n2\nn\nn\n\uf0d7 matrix", "option1": "", "option2": "", "option3": "", "option4": ""}
        integral = {"question": "Evaluate \u222b f(x) dx", "option1": "", "option2": "", "option3": "", "option4": ""}
        self.assertTrue(vision_verification_reasons(broken))
        self.assertIn("two-dimensional operator layout", vision_verification_reasons(integral))


if __name__ == "__main__":
    unittest.main()
