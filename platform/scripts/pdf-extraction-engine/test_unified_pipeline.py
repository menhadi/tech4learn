import csv
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import fitz
from PIL import Image

from ai_math import _valid, api_env_status, apply_visual_regions, normalize_ckeditor_html
from batch_extract import write_master_answer_keys_csv, write_master_questions_csv
from extraction_contract import (
    DELIVERY_IMAGE_FIELDS, DELIVERY_QUESTION_FIELDS, STANDARD_QUESTION_FIELDS,
    write_standard_questions_csv,
)
from pdf_detector import detect_pdf


class UnifiedPipelineTests(unittest.TestCase):
    def make_pdf(self, root, text):
        path = Path(root) / "paper.pdf"
        doc = fitz.open()
        page = doc.new_page()
        page.insert_text((50, 70), text)
        doc.save(path)
        doc.close()
        return path

    def test_detects_answer_key_from_pdf_content(self):
        with tempfile.TemporaryDirectory() as temp:
            path = self.make_pdf(
                temp,
                "Answer Key for Aerospace Engineering (AE)\n"
                "Q. No.\nSession\nQ. Type\nSection\nKey/Range\nMarks\n"
                "1\n5\nMCQ\nGA\nA\n1",
            )
            result = detect_pdf(path)
            self.assertEqual(result.extractor, "answer_key_pdf")
            self.assertFalse(result.uncertain)
    def test_answer_key_title_without_table_is_not_enough(self):
        with tempfile.TemporaryDirectory() as temp:
            path = self.make_pdf(
                temp,
                "Appendix: Answer Key\nQ.1 Choose A\n(A) one\n(B) two",
            )
            self.assertNotEqual(detect_pdf(path).extractor, "answer_key_pdf")
    def test_detects_ugcnet_from_pdf_content(self):
        with tempfile.TemporaryDirectory() as temp:
            path = self.make_pdf(temp, "Sl. No.2\nQBID:1511002\n(1) One\n(2) Two\n(3) Three\n(4) Four")
            self.assertEqual(detect_pdf(path).extractor, "ugcnet_bilingual")

    def test_detects_cuet_from_pdf_content(self):
        with tempfile.TemporaryDirectory() as temp:
            path = self.make_pdf(temp, "Item No. 1\nQuestion ID: 99\nQuestion Type: MCQ\nA: one\nB: two")
            self.assertEqual(detect_pdf(path).extractor, "cuetug")

    def test_detects_upsc_paired_bilingual_scan_from_filename_and_page_count(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / "General Studies Paper - II.pdf"
            doc = fitz.open()
            for _ in range(48):
                doc.new_page()
            doc.save(path)
            doc.close()
            result = detect_pdf(path)
            self.assertEqual(result.extractor, "upsc_bilingual")
            self.assertFalse(result.uncertain)

    def test_controlled_image_text_can_replace_marker_but_other_fields_cannot(self):
        original = {
            "question": "[image: paper/images/q1.png]",
            "option1": "A", "option2": "B", "option3": "C", "option4": "D",
        }
        candidate = {
            "question": "<p>Transcribed question text</p>",
            "option1": "<p>A</p>", "option2": "<p>B</p>",
            "option3": "<p>C</p>", "option4": "<p>D</p>",
        }
        self.assertFalse(_valid(original, candidate)[0])
        self.assertTrue(_valid(
            original, candidate,
            marker_change_fields={"question"},
            content_recovery_fields={"question"},
        )[0])

    def test_visual_regions_remove_verification_crop_or_make_tight_crop(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            images = root / "images"
            images.mkdir()
            source = images / "paper__q0001__question__img01.png"
            Image.new("RGB", (200, 100), "white").save(source)
            row = {
                "question_images": "paper/images/paper__q0001__question__img01.png",
                "option1_images": "", "option2_images": "",
                "option3_images": "", "option4_images": "",
            }
            candidate = {"question": "<p>Text only</p>"}
            apply_visual_regions(row, candidate, {}, root, {"question"}, {"question"})
            self.assertEqual(row["question_images"], "")
            self.assertNotIn("[image:", candidate["question"])

            row["question_images"] = "paper/images/paper__q0001__question__img01.png"
            candidate = {"question": "<p>Includes a diagram</p>"}
            apply_visual_regions(
                row, candidate, {"question": [[250, 200, 750, 800]]},
                root, {"question"}, {"question"},
            )
            cropped = images / "paper__q0001__question__img01.png"
            with Image.open(cropped) as cropped_image:
                self.assertEqual(cropped_image.size, (100, 60))
            self.assertIn("[image:", candidate["question"])
    def test_ai_validation_preserves_image_markers(self):
        original = {"question": "Find x [image: a.png]", "option1": "1", "option2": "2", "option3": "3", "option4": "4"}
        candidate = {
            "question": '<p>Find <span class="math-tex">\\(x\\)</span> [image: a.png]</p>',
            "option1": "<p>1</p>", "option2": "<p>2</p>", "option3": "<p>3</p>", "option4": "<p>4</p>",
        }
        self.assertTrue(_valid(original, candidate)[0])
        candidate["question"] = '<p>Find <span class="math-tex">\\(x\\)</span></p>'
        self.assertFalse(_valid(original, candidate)[0])

    def test_ai_validation_rejects_invented_differential_command(self):
        original = {"question": "Solve dy/dx", "option1": "1", "option2": "2", "option3": "3", "option4": "4"}
        candidate = {
            "question": '<p>Solve <span class="math-tex">\\(dy\\dx\\)</span></p>',
            "option1": "<p>1</p>", "option2": "<p>2</p>", "option3": "<p>3</p>", "option4": "<p>4</p>",
        }
        self.assertFalse(_valid(original, candidate)[0])
        candidate["question"] = '<p>Solve <span class="math-tex">\\(\\frac{dy}{dx}\\)</span></p>'
        self.assertTrue(_valid(original, candidate)[0])

    def test_ai_validation_accepts_dot_notation_for_differential_equation(self):
        original = {
            "question": "A first order nonlinear differential equation is x dot = f(x)",
            "option1": "1", "option2": "2", "option3": "3", "option4": "4",
        }
        candidate = {
            "question": '<p>A first order nonlinear differential equation is '
            '<span class="math-tex">\\(\\dot{x}=f(x)\\)</span></p>',
            "option1": "<p>1</p>", "option2": "<p>2</p>",
            "option3": "<p>3</p>", "option4": "<p>4</p>",
        }
        self.assertTrue(_valid(original, candidate)[0])
    def test_ai_validation_preserves_html_table_count(self):
        original = {"question": "Prompt <table><tr><td>x</td></tr></table>", "option1": "1", "option2": "2", "option3": "3", "option4": "4"}
        candidate = {
            "question": "<p>Prompt</p><table><tr><td>x</td></tr></table>",
            "option1": "<p>1</p>", "option2": "<p>2</p>", "option3": "<p>3</p>", "option4": "<p>4</p>",
        }
        self.assertTrue(_valid(original, candidate)[0])
        candidate["question"] = "<p>Prompt x</p>"
        self.assertFalse(_valid(original, candidate)[0])

    def test_ai_validation_allows_one_html_table_from_scanned_rows(self):
        original = {
            "question": "X Boundary condition\nX1 pinned\nY Critical load\nY1 pi squared EI over L squared",
            "option1": "X1-Y1", "option2": "X1-Y2", "option3": "X1-Y3", "option4": "X1-Y4",
        }
        candidate = {
            "question": (
                "<p>Match the columns.</p><table><tr><td>X1 pinned</td>"
                "<td>Y1 pi squared EI over L squared</td></tr></table>"
            ),
            "option1": "<p>X1-Y1</p>", "option2": "<p>X1-Y2</p>",
            "option3": "<p>X1-Y3</p>", "option4": "<p>X1-Y4</p>",
        }
        self.assertTrue(_valid(original, candidate)[0])
    def test_ai_validation_rejects_tex_outside_math_span(self):
        original = {"question": "The answer is 72 pi", "option1": "1", "option2": "2", "option3": "3", "option4": "4"}
        candidate = {
            "question": r"<p>The answer is 72\pi</p>",
            "option1": "<p>1</p>", "option2": "<p>2</p>", "option3": "<p>3</p>", "option4": "<p>4</p>",
        }
        self.assertFalse(_valid(original, candidate)[0])

    def test_ai_validation_rejects_unbalanced_tex_and_nested_paragraphs(self):
        original = {"question": "A matrix A", "option1": "1", "option2": "2", "option3": "3", "option4": "4"}
        candidate = {
            "question": '<p>A matrix <span class="math-tex">\\(A=[a_{ij}\\)</span></p>',
            "option1": "<p>1</p>", "option2": "<p>2</p>", "option3": "<p>3</p>", "option4": "<p>4</p>",
        }
        self.assertFalse(_valid(original, candidate)[0])
        candidate["question"] = '<p>A matrix<p><span class="math-tex">\\(A\\)</span></p></p>'
        self.assertFalse(_valid(original, candidate)[0])

    def test_ai_validation_accepts_ckeditor_mathjax_fragment(self):
        original = {"question": "A 2n x 2n matrix A has elements", "option1": "1", "option2": "2", "option3": "3", "option4": "4"}
        candidate = {
            "question": '<p>A <span class="math-tex">\\(2n\\times 2n\\)</span> matrix <span class="math-tex">\\(A=[a_{ij}]\\)</span> has elements</p>',
            "option1": "<p>1</p>", "option2": "<p>2</p>", "option3": "<p>3</p>", "option4": "<p>4</p>",
        }
        self.assertTrue(_valid(original, candidate)[0])

    def test_ai_validation_allows_missing_options_only_for_verified_mcq_recovery(self):
        original = {
            "question": "Choose the correct expression", "option1": "", "option2": "B",
            "option3": "C", "option4": "D",
        }
        candidate = {
            "question": "<p>Choose the correct expression</p>", "option1": "<p>A</p>",
            "option2": "<p>B</p>", "option3": "<p>C</p>", "option4": "<p>D</p>",
        }
        self.assertFalse(_valid(original, candidate)[0])
        self.assertTrue(_valid(original, candidate, allow_option_recovery=True)[0])
    def test_ai_validation_rejects_options_invented_for_nat_question(self):
        original = {
            "question": "Find x ______ (in integer).",
            "option1": "", "option2": "", "option3": "", "option4": "",
        }
        candidate = {
            "question": "<p>Find x ______ (in integer).</p>",
            "option1": "<p>1</p>", "option2": "<p>2</p>",
            "option3": "<p>3</p>", "option4": "<p>4</p>",
        }
        valid, reason = _valid(original, candidate)
        self.assertFalse(valid)
        self.assertIn("invented", reason)

    def test_ai_validation_requires_structure_for_stacked_fraction(self):
        original = {
            "question": "Consider the function\n2\n2\n( )\n1\nz\nf z\nz\nz\n+\n=\n\u2212",
            "option1": "", "option2": "", "option3": "", "option4": "",
        }
        candidate = {
            "question": "<p>Consider the function f(z) = (2z + 1)/(z\u00b2 \u2212 z).</p>",
            "option1": "", "option2": "", "option3": "", "option4": "",
        }
        self.assertFalse(_valid(original, candidate)[0])
        candidate["question"] = "<p>Consider the function f(z) = (2z + 1)\u2044(z\u00b2 \u2212 z).</p>"
        self.assertFalse(_valid(original, candidate)[0])
        candidate["question"] = (
            '<p>Consider the function <span class="math-tex">'
            r'\(f(z)=\frac{2z+1}{z^{2}-z}\)'
            "</span>.</p>"
        )
        self.assertTrue(_valid(original, candidate)[0])

    def test_ai_validation_rejects_flat_grouped_fraction(self):
        original = {
            "question": "Find the equivalent torsional stiffness.",
            "option1": "1", "option2": "2", "option3": "3", "option4": "4",
        }
        candidate = dict(original)
        candidate.update({f"option{index}": f"<p>{index}</p>" for index in range(1, 5)})
        candidate["question"] = (
            "<p>(G_A J_A / L)(G_B J_B / L) / "
            "(G_A J_A / L + G_B J_B / L)</p>"
        )
        self.assertFalse(_valid(original, candidate)[0])
        candidate["question"] = (
            '<p><span class="math-tex">'
            r'\(\frac{(G_AJ_A/L)(G_BJ_B/L)}{G_AJ_A/L+G_BJ_B/L}\)'
            "</span></p>"
        )
        self.assertTrue(_valid(original, candidate)[0])

    def test_ai_validation_rejects_html_table_used_as_matrix(self):
        original = {
            "question": "The matrix A has two rows and two columns.",
            "option1": "", "option2": "", "option3": "", "option4": "",
        }
        candidate = dict(original)
        candidate["question"] = (
            "<p>The matrix A is</p><table><tr><td>1</td><td>2</td></tr>"
            "<tr><td>3</td><td>4</td></tr></table>"
        )
        self.assertFalse(_valid(original, candidate)[0])
        candidate["question"] = (
            '<p>The matrix A is <span class="math-tex">'
            r'\(\begin{bmatrix}1&2\\3&4\end{bmatrix}\)'
            "</span>.</p>"
        )
        self.assertTrue(_valid(original, candidate)[0])

    def test_ai_validation_allows_repeated_underscore_answer_blank(self):
        original = {
            "question": "Thousands die _____ accidents every year.",
            "option1": "in", "option2": "from", "option3": "during", "option4": "of",
        }
        candidate = {
            "question": "<p>Thousands die _____ accidents every year.</p>",
            "option1": "<p>in</p>", "option2": "<p>from</p>",
            "option3": "<p>during</p>", "option4": "<p>of</p>",
        }
        self.assertTrue(_valid(original, candidate)[0])

    def test_ai_validation_rejects_display_math_inside_prose_paragraph(self):
        original = {
            "question": "A matrix A = [1 0; 0 1] is given.",
            "option1": "", "option2": "", "option3": "", "option4": "",
        }
        candidate = {
            "question": '<p>A matrix <span class="math-tex">\\[A=I\\]</span> is given.</p>',
            "option1": "", "option2": "", "option3": "", "option4": "",
        }
        self.assertFalse(_valid(original, candidate)[0])
        candidate["question"] = (
            '<p>A matrix is given.</p><p><span class="math-tex">'
            r'\[A=\begin{bmatrix}1&0\\0&1\end{bmatrix}\]'
            "</span></p>"
        )
        self.assertTrue(_valid(original, candidate)[0])
    def test_ai_validation_requires_mathjax_for_matrix_structure(self):
        original = {
            "question": "The matrix A = [ -1 -1 ; x -4 ]",
            "option1": "", "option2": "", "option3": "", "option4": "",
        }
        candidate = {
            "question": "<p>The matrix is [?1 ?1; x ?4]</p>",
            "option1": "", "option2": "", "option3": "", "option4": "",
        }
        self.assertFalse(_valid(original, candidate)[0])
        original["question"] = "The matrix A uses \uf0e9 bracket pieces"
        self.assertFalse(_valid(original, candidate)[0])
        candidate["question"] = (
            '<p>The matrix is <span class="math-tex">'
            r'\(\begin{bmatrix}-1 & -1 \\ x & -4\end{bmatrix}\)'
            "</span></p>"
        )
        self.assertTrue(_valid(original, candidate)[0])
    def test_image_marker_does_not_make_matrix_algebra_a_matrix_layout(self):
        original = {
            "question": (
                "Let P and Q be two square matrices. PQ = I^2 implies P = Q^-1. "
                "[image: paper_name/images/q_22.png]"
            ),
            "option1": "True", "option2": "False", "option3": "Both", "option4": "Neither",
        }
        candidate = {
            "question": (
                "<p>Let P and Q be two square matrices. PQ = IÂ² implies P = Qâ»Â¹. "
                "[image: paper_name/images/q_22.png]</p>"
            ),
            "option1": "<p>True</p>", "option2": "<p>False</p>",
            "option3": "<p>Both</p>", "option4": "<p>Neither</p>",
        }
        self.assertTrue(_valid(original, candidate)[0])

    def test_normalizer_repairs_reversible_utf8_mojibake(self):
        value = "<p>PQ = IÂ² and P = Qâ»Â¹ with P âˆ’ Q.</p>"
        self.assertEqual(
            normalize_ckeditor_html(value),
            "<p>PQ = I² and P = Q⁻¹ with P − Q.</p>",
        )
    def test_normalizer_wraps_simple_bare_tex_scripts(self):
        value = "<p>d_A, J_B and x^{-1}; [image: paper_name/q_44.png]</p>"
        normalized = normalize_ckeditor_html(value)
        self.assertIn(r'\(d_{A}\)', normalized)
        self.assertIn(r'\(J_{B}\)', normalized)
        self.assertIn(r'\(x^{-1}\)', normalized)
        self.assertIn("paper_name/q_44.png", normalized)
    def test_ai_validation_rejects_tex_scripts_outside_mathjax(self):
        original = {
            "question": "P = Q^-1",
            "option1": "", "option2": "", "option3": "", "option4": "",
        }
        candidate = {
            "question": "<p>P = Q^{-1}</p>",
            "option1": "", "option2": "", "option3": "", "option4": "",
        }
        self.assertFalse(_valid(original, candidate)[0])
    @patch.dict("os.environ", {"OPENAI_API_KEY": "test", "MATHPIX_APP_ID": "id", "MATHPIX_APP_KEY": "key"}, clear=True)
    def test_env_status_never_returns_keys(self):
        status = api_env_status()
        self.assertTrue(status["openai_api_key"])
        self.assertTrue(status["mathpix_app_id"])
        self.assertTrue(status["mathpix_app_key"])
        self.assertNotIn("test", str(status))


    def test_master_answer_keys_csv_is_separate_from_questions(self):
        with tempfile.TemporaryDirectory() as temp:
            output_root = Path(temp)
            paper_dir = output_root / "Keys" / "AE2025"
            paper_dir.mkdir(parents=True)
            fields = [
                "document_id", "source_filename", "source_sha256", "extractor_type",
                "exam_name", "exam_year", "paper_code", "session", "question_no",
                "question_id", "question_type", "section", "correct_option",
                "correct_options", "answer_min", "answer_max", "key_or_range_raw",
                "marks", "source_page", "metadata_json",
            ]
            with (paper_dir / "answer_keys.csv").open(
                "w", encoding="utf-8-sig", newline=""
            ) as target:
                writer = csv.DictWriter(target, fieldnames=fields)
                writer.writeheader()
                writer.writerow({
                    "extractor_type": "answer_key_pdf", "exam_name": "GATE",
                    "exam_year": "2025", "paper_code": "AE", "session": "5",
                    "question_no": "1", "question_type": "MCQ",
                    "section": "GA", "correct_option": "A",
                    "key_or_range_raw": "A", "marks": "1",
                })
            results = [{
                "status": "SUCCESS", "extractor": "answer_key_pdf",
                "source_relative_path": "Keys/AE_Keys.pdf",
                "output_relative_path": "Keys/AE2025",
            }]
            answer_master, answer_count = write_master_answer_keys_csv(output_root, results)
            question_master, question_count = write_master_questions_csv(output_root, results)
            with answer_master.open(encoding="utf-8-sig", newline="") as source:
                answers = list(csv.DictReader(source))
            with question_master.open(encoding="utf-8-sig", newline="") as source:
                questions = list(csv.DictReader(source))
        self.assertEqual(answer_count, 1)
        self.assertEqual(question_count, 0)
        self.assertEqual(answers[0]["correct_option"], "A")
        self.assertEqual(questions, [])
    def test_master_csv_is_only_csv_and_preserves_hierarchy_order(self):
        with tempfile.TemporaryDirectory() as temp:
            output_root = Path(temp)
            paper_a = output_root / "Exam" / "A"
            paper_b = output_root / "Exam" / "B"
            for paper_dir, source, questions in (
                (paper_a, "Exam/A.pdf", ("10", "2")),
                (paper_b, "Exam/B.pdf", ("1",)),
            ):
                paper_dir.mkdir(parents=True)
                rows = []
                for number in questions:
                    row = {field: "" for field in STANDARD_QUESTION_FIELDS}
                    row.update({
                        "source_relative_path": source,
                        "question_no": number,
                        "question": f"Question {number}",
                    })
                    rows.append(row)
                write_standard_questions_csv(rows, paper_dir / "questions.csv")
                with (paper_dir / "images_manifest.csv").open("w", encoding="utf-8-sig", newline="") as target:
                    writer = csv.DictWriter(target, fieldnames=["filename"])
                    writer.writeheader()
            results = [
                {"status": "SUCCESS", "source_relative_path": "Exam/B.pdf", "output_relative_path": "Exam/B"},
                {"status": "SUCCESS", "source_relative_path": "Exam/A.pdf", "output_relative_path": "Exam/A"},
            ]
            master, count = write_master_questions_csv(output_root, results)
            self.assertEqual(count, 3)
            with master.open(encoding="utf-8-sig", newline="") as source:
                rows = list(csv.DictReader(source))
            self.assertEqual(
                [(row["source_relative_path"], row["question_no"]) for row in rows],
                [("Exam/A.pdf", "2"), ("Exam/A.pdf", "10"), ("Exam/B.pdf", "1")],
            )
            self.assertEqual(list(output_root.rglob("*.csv")), [master])
            self.assertEqual(list(rows[0]), DELIVERY_QUESTION_FIELDS)
            self.assertFalse(set(DELIVERY_IMAGE_FIELDS) & set(rows[0]))
            self.assertTrue((paper_a / "questions_rows.json").exists())
            self.assertTrue((paper_a / "images_manifest.json").exists())


if __name__ == "__main__":
    unittest.main()
