import csv
import tempfile
import unittest
from pathlib import Path

from google_sheets_output import load_csv_values, publish_questions_csv


class FakeRequest:
    def __init__(self, result=None):
        self.result = result or {}

    def execute(self):
        return self.result


class FakeValues:
    def __init__(self):
        self.clears = []
        self.updates = []

    def clear(self, **kwargs):
        self.clears.append(kwargs)
        return FakeRequest()

    def update(self, **kwargs):
        self.updates.append(kwargs)
        return FakeRequest()


class FakeSpreadsheets:
    def __init__(self, titles=None):
        self.titles = titles or ["data"]
        self.values_api = FakeValues()
        self.batch_updates = []

    def get(self, **kwargs):
        return FakeRequest({"sheets": [{"properties": {"title": title}} for title in self.titles]})

    def batchUpdate(self, **kwargs):
        self.batch_updates.append(kwargs)
        return FakeRequest()

    def values(self):
        return self.values_api


class FakeService:
    def __init__(self, titles=None):
        self.sheets = FakeSpreadsheets(titles)

    def spreadsheets(self):
        return self.sheets


class GoogleSheetsOutputTests(unittest.TestCase):
    def make_csv(self, root):
        path = Path(root) / "questions.csv"
        with path.open("w", encoding="utf-8-sig", newline="") as target:
            writer = csv.writer(target)
            writer.writerow(["question_no", "question"])
            writer.writerow(["1", '<p><span class="math-tex">\\(\\frac{a}{b}\\)</span></p>'])
            writer.writerow(["2", "=not-a-formula"])
        return path

    def test_load_csv_preserves_mathjax_and_html(self):
        with tempfile.TemporaryDirectory() as temp:
            values = load_csv_values(self.make_csv(temp))
        self.assertIn(r"\frac{a}{b}", values[1][1])
        self.assertEqual(values[2][1], "=not-a-formula")

    def test_load_csv_removes_redundant_image_columns(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / "questions.csv"
            with path.open("w", encoding="utf-8-sig", newline="") as target:
                writer = csv.writer(target)
                writer.writerow(["question_no", "question", "question_images", "option1_images"])
                writer.writerow([
                    "1", '<p><img src="https://cdn.examelite.com/q1.png"/></p>',
                    "https://cdn.examelite.com/q1.png",
                    "https://cdn.examelite.com/a.png",
                ])
            values = load_csv_values(path)
        self.assertEqual(values[0], ["question_no", "question"])
        self.assertEqual(len(values[1]), 2)
    def test_publish_clears_tab_and_writes_raw_chunks(self):
        with tempfile.TemporaryDirectory() as temp:
            csv_path = self.make_csv(temp)
            service = FakeService()
            result = publish_questions_csv(
                csv_path, spreadsheet_id="sheet-id", tab="data",
                chunk_rows=2, service=service,
            )
        self.assertEqual(result["question_rows"], 2)
        self.assertEqual(len(service.sheets.values_api.clears), 1)
        self.assertEqual(len(service.sheets.values_api.updates), 2)
        self.assertEqual(service.sheets.values_api.updates[0]["valueInputOption"], "RAW")
        self.assertEqual(service.sheets.values_api.updates[1]["range"], "'data'!A3")

    def test_publish_rejects_relative_image_reference(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / "questions.csv"
            with path.open("w", encoding="utf-8-sig", newline="") as target:
                writer = csv.writer(target)
                writer.writerow(["question_no", "question", "question_images"])
                writer.writerow(["1", "[image: local/q1.png]", "local/q1.png"])
            with self.assertRaisesRegex(ValueError, "CDN delivery validation failed"):
                publish_questions_csv(path, service=FakeService())

    def test_missing_tab_is_created(self):
        with tempfile.TemporaryDirectory() as temp:
            service = FakeService(["other"])
            publish_questions_csv(self.make_csv(temp), service=service)
        self.assertEqual(len(service.sheets.batch_updates), 1)


if __name__ == "__main__":
    unittest.main()
