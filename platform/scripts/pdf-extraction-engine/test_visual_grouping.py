import unittest

import fitz

from pdf_question_extractor import (
    choose_option_for_y,
    keep_embedded_visual,
    group_visual_occurrences,
    is_tiny_visual_component,
    rect_covered,
    rect_mostly_covered,
    remove_text_covered_by_images,
    strip_question_number,
)


class VisualGroupingTests(unittest.TestCase):
    def test_tiny_explicit_option_image_is_preserved(self):
        self.assertTrue(keep_embedded_visual(fitz.Rect(0, 0, 5, 14), "B"))
        self.assertFalse(keep_embedded_visual(fitz.Rect(0, 0, 5, 14), None))

    def test_option_continues_across_page_until_next_marker_band(self):
        question = {
            "option_positions": [
                {"page": 10, "label": "A", "y": 770},
                {"page": 11, "label": "B", "y": 134},
                {"page": 11, "label": "C", "y": 241},
                {"page": 11, "label": "D", "y": 355},
            ]
        }
        self.assertEqual(choose_option_for_y(question, 11, 78), "A")
        self.assertEqual(choose_option_for_y(question, 11, 180), "B")

    def test_nested_image_layers_become_one_composite(self):
        records = [
            {"rect": fitz.Rect(100, 100, 300, 300), "id": "full"},
            {"rect": fitz.Rect(120, 120, 145, 145), "id": "label"},
            {"rect": fitz.Rect(280, 270, 310, 305), "id": "edge"},
        ]
        groups = group_visual_occurrences(records)
        self.assertEqual(len(groups), 1)
        self.assertEqual(groups[0]["rect"], fitz.Rect(100, 100, 310, 305))
        self.assertEqual(len(groups[0]["records"]), 3)

    def test_separate_option_images_stay_separate(self):
        records = [
            {"rect": fitz.Rect(10, 10, 60, 60)},
            {"rect": fitz.Rect(150, 10, 200, 60)},
        ]
        self.assertEqual(len(group_visual_occurrences(records)), 2)

    def test_tiny_mask_component_is_ignored(self):
        self.assertTrue(is_tiny_visual_component(fitz.Rect(10, 10, 23, 15)))
        self.assertFalse(is_tiny_visual_component(fitz.Rect(10, 10, 50, 15)))

    def test_question_number_and_text_inside_image_are_removed(self):
        question = {
            "text_parts": [
                {"text": "outside", "page": 1, "bbox": (10, 10, 80, 30), "source": "pdf_text"},
                {"text": "diagram label", "page": 1, "bbox": (120, 120, 180, 140), "source": "pdf_text"},
            ],
            "options": {},
            "image_objects": [{"page": 1, "bbox": (100, 100, 200, 200)}],
        }
        remove_text_covered_by_images([question])
        self.assertEqual(len(question["text_parts"]), 1)
        self.assertEqual(strip_question_number("Q.11 A matrix"), "A matrix")
    def test_table_text_block_coverage(self):
        table = fitz.Rect(100, 100, 300, 300)
        self.assertTrue(rect_mostly_covered(fitz.Rect(120, 120, 180, 140), [table]))
        self.assertFalse(rect_mostly_covered(fitz.Rect(20, 20, 180, 140), [table]))
    def test_covered_vector_is_suppressed(self):
        covers = [fitz.Rect(100, 100, 300, 300)]
        self.assertTrue(rect_covered(fitz.Rect(120, 120, 180, 180), covers))
        self.assertFalse(rect_covered(fitz.Rect(320, 120, 380, 180), covers))


if __name__ == "__main__":
    unittest.main()
