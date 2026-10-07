import csv
import tempfile
import unittest
from pathlib import Path

from contract_adapter import LegacyImageAdapter
from extraction_contract import (
    ExtractionContext,
    ImageManifest,
    write_standard_questions_csv,
)


class ExtractionContractTests(unittest.TestCase):
    def test_hierarchy_identity_filenames_and_manifests(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            input_root = root / "input"
            source = input_root / "PYQ" / "NTA" / "CUETUG" / "Paper 01.pdf"
            source.parent.mkdir(parents=True)
            source.write_bytes(b"sample-pdf")
            output_root = root / "output"

            context = ExtractionContext.create(
                source,
                input_root,
                output_root,
                "CUETUG2023",
                "cuetug",
            )
            self.assertEqual(
                context.document_key,
                "PYQ__NTA__CUETUG__Paper_01",
            )
            self.assertEqual(
                context.output_dir,
                output_root / "PYQ" / "NTA" / "CUETUG" / "Paper 01",
            )

            filename = context.image_filename("1", "question", 1, "jpeg")
            self.assertEqual(
                filename,
                "PYQ__NTA__CUETUG__Paper_01__q0001__question__img01.jpeg",
            )
            image_bytes = b"image"
            (context.images_dir / filename).write_bytes(image_bytes)

            manifest = ImageManifest(context)
            image_row = manifest.add(
                question_no="1",
                role="question",
                image_index=1,
                filename=filename,
                source_order=1,
                source_page=2,
                bbox=(10, 20, 30, 40),
                width=100,
                height=50,
                image_bytes=image_bytes,
            )
            manifest_path = manifest.write()
            self.assertTrue(manifest_path.exists())
            self.assertEqual(
                image_row["relative_path"],
                "PYQ/NTA/CUETUG/Paper 01/images/" + filename,
            )

            question_row = context.base_question_row()
            question_row.update({
                "question_no": "1",
                "question_images": image_row["relative_path"],
            })
            csv_path = write_standard_questions_csv(
                [question_row],
                context.output_dir / "questions.csv",
            )
            with csv_path.open(encoding="utf-8-sig", newline="") as source_csv:
                rows = list(csv.DictReader(source_csv))
            self.assertEqual(len(rows), 1)
            self.assertEqual(rows[0]["document_key"], context.document_key)
            self.assertEqual(rows[0]["question_images"], image_row["relative_path"])


    def test_legacy_image_adoption_renames_and_rewrites(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            input_root = root / "input"
            source = input_root / "Exam" / "paper.pdf"
            source.parent.mkdir(parents=True)
            source.write_bytes(b"pdf")
            context = ExtractionContext.create(
                source, input_root, root / "output", "PAPER", "legacy"
            )
            legacy_dir = context.output_dir / "legacy-images"
            legacy_dir.mkdir()
            old_name = "PAPER_q1_img1.png"
            (legacy_dir / old_name).write_bytes(b"image")

            adapter = LegacyImageAdapter(context, legacy_dir)
            adopted = adapter.adopt("1", "question", [old_name], source_pages=[3])
            self.assertEqual(len(adopted), 1)
            self.assertIn("__q0001__question__img01.png", adopted[0])
            self.assertFalse((legacy_dir / old_name).exists())
            self.assertEqual(
                adapter.rewrite_text_references(f"[image: {old_name}]"),
                f"[image: {adopted[0]}]",
            )
            self.assertEqual(adapter.manifest.rows[0]["source_page"], 3)
if __name__ == "__main__":
    unittest.main()
