import unittest

from PIL import Image

from upsc_bilingual_extractor import (
    PAPER_LAYOUTS,
    _combined_layout,
    _crop_questions,
    _field_allows_visual,
    _normalize_transcription,
    paper_variant,
)


class UpscBilingualExtractorTests(unittest.TestCase):
    def test_only_supported_upsc_paper_names_are_accepted(self):
        self.assertEqual(paper_variant("General Studies Paper - I.pdf"), "I")
        self.assertEqual(paper_variant("General Studies Paper - II.pdf"), "II")
        with self.assertRaises(ValueError):
            paper_variant("ADDITIONAL PRIVATE SECRETARY EXAM-2023.pdf")

    def test_layouts_cover_every_question_once(self):
        for variant, expected in (("I", 100), ("II", 80)):
            numbers = []
            for hindi_page, english_page, first, last in PAPER_LAYOUTS[variant]["pairs"]:
                self.assertEqual(english_page, hindi_page + 1)
                numbers.extend(range(first, last + 1))
            self.assertEqual(numbers, list(range(1, expected + 1)))

    def test_missing_language_anchor_uses_paired_page_layout(self):
        english = {
            1: {"column": 0, "y": 100},
            2: {"column": 0, "y": 300},
            3: {"column": 1, "y": 100},
            4: {"column": 1, "y": 300},
        }
        hindi = {
            1: {"column": 0, "y": 105},
            3: {"column": 1, "y": 110},
            4: {"column": 1, "y": 310},
        }
        combined = _combined_layout(english, hindi, 1, 4, 800)
        self.assertEqual(combined[2]["column"], 0)
        self.assertGreater(combined[2]["y"], combined[1]["y"])

    def test_question_crops_preserve_source_order(self):
        image = Image.new("RGB", (1000, 1200), "white")
        layout = {
            1: {"column": 0, "y": 100},
            2: {"column": 0, "y": 500},
            3: {"column": 1, "y": 120},
            4: {"column": 1, "y": 600},
        }
        crops = _crop_questions(image, layout)
        self.assertEqual(sorted(crops), [1, 2, 3, 4])
        self.assertGreater(crops[1]["image"].height, 100)
        self.assertGreater(crops[4]["image"].height, 100)

    def test_transcription_requires_all_printed_options_in_both_languages(self):
        candidate = {
            "passage": "", "passage_hindi": "",
            "question": "<p>English question</p>",
            "question_hindi": "<p>Hindi question</p>",
            **{f"option{i}": f"<p>E{i}</p>" for i in range(1, 5)},
            **{f"option{i}_hindi": f"<p>H{i}</p>" for i in range(1, 5)},
            "visual_regions": {},
        }
        self.assertEqual(
            _normalize_transcription(candidate)["option4"], "<p>E4</p>"
        )
        candidate["option4_hindi"] = ""
        with self.assertRaises(ValueError):
            _normalize_transcription(candidate)


    def test_visual_gate_rejects_text_and_tables_but_keeps_figures(self):
        self.assertFalse(_field_allows_visual("question", "<p>Find the answer.</p>"))
        self.assertFalse(_field_allows_visual("question", "<table><tr><td>x</td></tr></table>"))
        self.assertFalse(_field_allows_visual("option1", "<p>(a) SPRQ</p>"))
        self.assertTrue(_field_allows_visual("question", "<p>Refer to the diagram shown.</p>"))
        self.assertTrue(_field_allows_visual("option1", "<p>(a)</p>"))

if __name__ == "__main__":
    unittest.main()
