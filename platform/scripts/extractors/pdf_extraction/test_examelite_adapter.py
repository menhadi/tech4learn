import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch

from examelite_adapter import (
    _absolute_delivery_prefix,
    _answer_fields,
    _convert_rows,
    _probe,
    _rewrite_images,
    _run_repository_batch,
)


class ExamEliteAdapterTest(unittest.TestCase):
    def test_unified_auto_delegates_detection_to_pinned_repository(self):
        probe = _probe(Path("paper.pdf"), "auto")
        self.assertEqual(probe["extractor"], "auto")
        self.assertEqual(probe["selection"], "automatic")
        self.assertIn("pinned repository", probe["reasons"][0])

    def test_probe_preserves_manual_extractor_selection(self):
        probe = _probe(Path("scan.pdf"), "cuetug")
        self.assertEqual(probe["extractor"], "cuetug")
        self.assertEqual(probe["selection"], "manual")


    def test_image_marker_is_rewritten_and_reported(self):
        with tempfile.TemporaryDirectory() as temporary:
            image = Path(temporary) / "paper__q0001__question__img01.png"
            image.write_bytes(b"image")
            content, images = _rewrite_images(
                f"[image: nested/{image.name}]",
                "",
                {image.name: image.name},
                "/storage/group/category/package/exam/images",
                "AE",
            )
        self.assertIn("/storage/group/category/package/exam/images/", content)
        self.assertEqual(images, [image.name])

    def test_msq_and_nat_answers_preserve_their_types(self):
        self.assertEqual(
            _answer_fields({"question_type": "MSQ", "correct_options": "A;C"}),
            {"correct_answers": ["A", "C"]},
        )
        self.assertEqual(
            _answer_fields(
                {"question_type": "NAT", "answer_min": "1.25", "answer_max": "1.50"}
            ),
            {
                "nat_config": {
                    "version": 1,
                    "mode": "range",
                    "min": 1.25,
                    "max": 1.5,
                }
            },
        )

    def test_extra_script_metadata_is_retained(self):
        rows = [{
            "question_no": "7",
            "question_type": "MCQ",
            "question": "<p>Choose.</p>",
            "option1": "<p>One</p>",
            "option2": "<p>Two</p>",
            "source_pages": "2;3",
            "question_id": "source-7001",
            "section": "Language",
            "metadata_json": '{"sequence_key":"7-en-hi"}',
        }]
        question = _convert_rows(
            rows, {}, {}, "/storage/images", "UPSC", False
        )[0]
        self.assertEqual(question["printed_question_number"], "7")
        self.assertEqual(question["source_pages"], [2, 3])
        self.assertEqual(len(question["options"]), 2)
        self.assertEqual(
            question["extractor_metadata"]["sequence_key"], "7-en-hi"
        )
        self.assertEqual(
            question["extractor_metadata"]["extra_fields"]["question_id"],
            "source-7001",
        )

    def test_relative_storage_prefix_uses_configured_https_origin(self):
        with patch.dict(
            "os.environ",
            {"EXAMELITE_PUBLIC_ORIGIN": "https://examways.org"},
            clear=False,
        ):
            prefix = _absolute_delivery_prefix("/storage/exams/paper/images")
        self.assertEqual(
            prefix,
            "https://examways.org/storage/exams/paper/images/",
        )

    def test_repository_batch_runs_the_pinned_entrypoint(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            engine = root / "engine"
            engine.mkdir()
            (engine / "batch_extract.py").write_text("# pinned engine\n")
            question = root / "paper.pdf"
            question.write_bytes(b"%PDF fixture")

            def fake_run(command, **kwargs):
                output_root = Path(command[command.index("--output-root") + 1])
                output_root.mkdir(parents=True)
                (output_root / "questions.csv").write_text(
                    "question_no,question\n1,Fixture question\n",
                    encoding="utf-8",
                )
                batch_dir = output_root / "_batch"
                batch_dir.mkdir()
                (batch_dir / "batch_report.json").write_text(
                    '{"results":[{"extractor":"jee_nta_image"}]}',
                    encoding="utf-8",
                )
                return SimpleNamespace(returncode=0, stdout="", stderr="")

            with patch("examelite_adapter.ENGINE_ROOT", engine), patch(
                "examelite_adapter.subprocess.run", side_effect=fake_run
            ) as runner:
                csv_path, output_root, report = _run_repository_batch(
                    question,
                    "jee_nta_image",
                    root / "work",
                    "https://examways.org/storage/exam/images",
                )

        self.assertTrue(csv_path.name == "questions.csv")
        self.assertEqual(report["results"][0]["extractor"], "jee_nta_image")
        command = runner.call_args.args[0]
        self.assertIn("batch_extract.py", command[1])
        self.assertEqual(command[command.index("--extractor") + 1], "jee_nta_image")

    def test_duplicate_extracted_numbers_stop_before_draft_creation(self):
        rows = [
            {"question_no": "1", "question": "First"},
            {"question_no": "1", "question": "Duplicate"},
        ]
        with self.assertRaisesRegex(RuntimeError, "duplicate question number 1"):
            _convert_rows(rows, {}, {}, "", "TEST", False)


if __name__ == "__main__":
    unittest.main()
