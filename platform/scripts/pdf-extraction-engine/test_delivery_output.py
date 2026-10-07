import csv
import json
import tempfile
import unittest
from pathlib import Path

from ai_math import vision_verification_reasons
from cdn_output import (
    DEFAULT_CDN_PREFIX, cdn_url, prepare_cdn_output, validate_cdn_output,
)
from extraction_contract import STANDARD_QUESTION_FIELDS, write_standard_questions_csv
from pdf_question_extractor import line_text_preserving_scripts


class DeliveryOutputTests(unittest.TestCase):
    def test_pdf_span_geometry_preserves_simple_unicode_superscripts(self):
        spans = [
            {"text": "\U0001d443\U0001d452", "size": 12, "origin": (0, 96), "bbox": (0, 85, 10, 99)},
            {"text": "\U0001d465", "size": 8.52, "origin": (11, 91.5), "bbox": (11, 84, 15, 94)},
            {"text": "= \U0001d444\U0001d452", "size": 12, "origin": (16, 96), "bbox": (16, 85, 30, 99)},
            {"text": "\u2212\U0001d465", "size": 8.52, "origin": (31, 91.5), "bbox": (31, 84, 38, 94)},
        ]
        self.assertEqual(
            line_text_preserving_scripts(spans),
            "\U0001d443\U0001d452\u02e3= \U0001d444\U0001d452\u207b\u02e3",
        )

    def test_ambiguous_styled_run_uses_vision_but_preserved_unicode_does_not(self):
        broken = {
            "question": "If \U0001d443\U0001d452\U0001d465 = \U0001d444\U0001d452\u2212\U0001d465",
            "option1": "", "option2": "", "option3": "", "option4": "",
        }
        preserved = dict(
            broken,
            question="If \U0001d443\U0001d452\u02e3 = \U0001d444\U0001d452\u207b\u02e3",
        )
        self.assertIn(
            "ambiguous styled math run may contain flattened scripts",
            vision_verification_reasons(broken),
        )
        self.assertNotIn(
            "ambiguous styled math run may contain flattened scripts",
            vision_verification_reasons(preserved),
        )
    def test_stacked_fraction_layout_uses_vision(self):
        fields = {
            "question": "Choose the correct statement",
            "option1": "", "option2": "", "option3": "",
            "option4": "\U0001d443\n\U0001d444=0",
        }
        self.assertIn(
            "stacked mathematical layout may contain a flattened fraction",
            vision_verification_reasons(fields),
        )
    def test_ocr_word_fragments_do_not_trigger_stacked_math(self):
        fields = {
            "question": "velocities\nSj\nstay\nflow\ncay\n3\nsteady",
            "option1": "", "option2": "", "option3": "", "option4": "",
        }
        self.assertNotIn(
            "stacked mathematical layout may contain a flattened fraction",
            vision_verification_reasons(fields),
        )
    def test_cdn_rejects_external_image_url(self):
        with self.assertRaisesRegex(ValueError, "outside the configured CDN"):
            cdn_url("https://example.com/question.png")

    def test_future_image_role_is_converted_and_validated(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            relative = "future/paper/images/q1_formula.png"
            fields = list(STANDARD_QUESTION_FIELDS) + ["formula", "formula_images"]
            row = {field: "" for field in fields}
            row.update({
                "paper_code": "FUTURE",
                "question_no": "1",
                "question": "<p>Review the formula.</p>",
                "formula": f"[image: {relative}]",
                "formula_images": relative,
            })
            with (root / "questions.csv").open("w", encoding="utf-8-sig", newline="") as target:
                writer = csv.DictWriter(target, fieldnames=fields)
                writer.writeheader()
                writer.writerow(row)
            prepare_cdn_output(root)
            self.assertTrue(validate_cdn_output(root)["valid"])
            with (root / "questions.csv").open(encoding="utf-8-sig", newline="") as source:
                result = next(csv.DictReader(source))
            self.assertTrue(result["formula_images"].startswith(DEFAULT_CDN_PREFIX))
            self.assertIn(f'src="{DEFAULT_CDN_PREFIX}', result["formula"])

    def test_cdn_validation_rejects_unresolved_relative_reference(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            row = {field: "" for field in STANDARD_QUESTION_FIELDS}
            row.update({
                "question_no": "1",
                "question": "[image: local/q1.png]",
                "question_images": "local/q1.png",
            })
            write_standard_questions_csv([row], root / "questions.csv")
            with self.assertRaisesRegex(ValueError, "CDN delivery validation failed"):
                validate_cdn_output(root)
    def test_cdn_postprocessor_writes_tags_urls_json_and_manifest(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            row = {field: "" for field in STANDARD_QUESTION_FIELDS}
            relative = "IN2025/images/IN2025__q0004__question__img01.png"
            row.update({
                "paper_code": "IN2025",
                "question_no": "4",
                "question": f"Choose the cube.\n[image: {relative}]",
                "question_images": relative,
            })
            write_standard_questions_csv([row], root / "questions.csv")
            (root / "questions.json").write_text(json.dumps({
                "paper_code": "IN2025",
                "questions": [{
                    "question_no": "4",
                    "question_text": row["question"],
                    "question_images": [relative],
                }],
            }), encoding="utf-8")
            with (root / "images_manifest.csv").open("w", encoding="utf-8-sig", newline="") as target:
                writer = csv.DictWriter(target, fieldnames=["relative_path"])
                writer.writeheader()
                writer.writerow({"relative_path": relative})
            (root / "extraction_manifest.json").write_text("{}", encoding="utf-8")

            prepare_cdn_output(root)
            self.assertTrue(validate_cdn_output(root)["valid"])

            with (root / "questions.csv").open(encoding="utf-8-sig", newline="") as source:
                result = next(csv.DictReader(source))
            expected_url = "https://cdn.examelite.com/" + relative
            self.assertIn(
                f'<img alt="IN2025 Question 4 English" src="{expected_url}"/>',
                result["question"],
            )
            self.assertNotIn("class=", result["question"])
            self.assertNotIn("loading=", result["question"])
            self.assertEqual(result["question_images"], expected_url)
            payload = json.loads((root / "questions.json").read_text(encoding="utf-8"))
            self.assertEqual(payload["questions"][0]["question_images"], [expected_url])
            self.assertIn("<img alt=", payload["questions"][0]["question_text"])
            with (root / "images_manifest.csv").open(encoding="utf-8-sig", newline="") as source:
                manifest = next(csv.DictReader(source))
            self.assertEqual(manifest["relative_path"], relative)
            self.assertEqual(manifest["cdn_url"], expected_url)


if __name__ == "__main__":
    unittest.main()
