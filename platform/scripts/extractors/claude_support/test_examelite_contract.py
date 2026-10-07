import unittest
import tempfile
from pathlib import Path

from PIL import Image

from examelite_contract import (
    checkpoint_and_attach_answers,
    extract_question_images,
    merge_question_fragments,
)


class ClaudeContractTest(unittest.TestCase):
    def test_adjacent_page_fragments_merge_by_section_and_number(self):
        questions = [
            {
                "section": "Physics",
                "number": "12",
                "question_text": "A question starts here",
                "option_a": "",
                "source_pages": [4],
                "source_page_start": 4,
                "order_on_page": 8,
                "image_regions": [],
            },
            {
                "section": "Physics",
                "number": "12",
                "question_text": "and continues here.",
                "option_a": "First option",
                "source_pages": [5],
                "source_page_start": 5,
                "order_on_page": 1,
                "image_regions": [],
            },
        ]
        merged = merge_question_fragments(questions)
        self.assertEqual(len(merged), 1)
        self.assertEqual(merged[0]["source_pages"], [4, 5])
        self.assertIn("starts here", merged[0]["question_text"])
        self.assertIn("continues here", merged[0]["question_text"])
        self.assertEqual(merged[0]["option_a"], "First option")

    def test_same_number_in_different_sections_stays_separate(self):
        questions = [
            {
                "section": "Physics",
                "number": "1",
                "question_text": "P",
                "source_pages": [1],
            },
            {
                "section": "Chemistry",
                "number": "1",
                "question_text": "C",
                "source_pages": [2],
            },
        ]
        self.assertEqual(len(merge_question_fragments(questions)), 2)

    def test_checkpoint_attaches_exact_answers(self):
        questions = [
            {"section": "Physics", "number": "1"},
            {"section": "Chemistry", "number": "1"},
        ]
        answers = [
            {
                "section": "Physics",
                "question_number": "1",
                "correct_option": "B",
            },
            {
                "section": "Chemistry",
                "question_number": "1",
                "correct_option": "3",
            },
        ]
        checkpoint_and_attach_answers(questions, answers)
        self.assertEqual(questions[0]["correct_option"], "B")
        self.assertEqual(questions[1]["correct_option"], "3")

    def test_checkpoint_rejects_count_or_number_mismatch(self):
        questions = [{"section": "Physics", "number": "1"}]
        answers = [
            {
                "section": "Physics",
                "question_number": "2",
                "correct_option": "A",
            }
        ]
        with self.assertRaisesRegex(RuntimeError, "checkpoint failed"):
            checkpoint_and_attach_answers(questions, answers)

    def test_overlapping_bridge_prefers_complete_transcription(self):
        questions = [
            {
                "section": "Physics",
                "number": "12",
                "question_text": "A question starts [CONTINUES ON NEXT PAGE]",
                "source_pages": [4],
                "source_page_start": 4,
                "order_on_page": 8,
            },
            {
                "section": "Physics",
                "number": "12",
                "question_text": "A question starts and finishes on page five.",
                "source_pages": [4, 5],
                "source_page_start": 4,
                "order_on_page": 8,
            },
        ]
        merged = merge_question_fragments(questions)
        self.assertEqual(len(merged), 1)
        self.assertEqual(
            merged[0]["question_text"],
            "A question starts and finishes on page five.",
        )

    def test_image_crop_uses_question_and_option_filename(self):
        question = {
            "section": "Physics",
            "number": "7",
            "question_text": "Choose the diagram.",
            "option_b": "",
            "diagram_required": True,
            "image_regions": [{
                "target": "option_b",
                "page": 1,
                "bbox_normalized": [0.1, 0.1, 0.8, 0.8],
                "description": "Option B diagram",
            }],
        }
        with tempfile.TemporaryDirectory() as temporary:
            folder = Path(temporary)
            extract_question_images(
                Path("paper.pdf"),
                [question],
                folder,
                "/storage/group/category/subcategory/package/exam/images",
                None,
                lambda *_: [Image.new("RGB", (100, 100), "white")],
            )
            filename = "q_Physics_7_option2_image_1.png"
            self.assertTrue((folder / filename).is_file())
            self.assertIn(filename, question["option_b"])
            self.assertEqual(question["extracted_images"][0]["target"], "option2")

if __name__ == "__main__":
    unittest.main()
