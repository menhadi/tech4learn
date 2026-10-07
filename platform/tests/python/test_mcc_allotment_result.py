import importlib.util
import tempfile
import unittest
from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle


MODULE_PATH = Path(__file__).parents[2] / "scripts" / "admission_prediction" / "extract_mcc_allotment_result.py"
SPEC = importlib.util.spec_from_file_location("mcc_allotment", MODULE_PATH)
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class MccAllotmentResultExtractorTest(unittest.TestCase):
    def test_it_aggregates_opening_and_closing_ranks_across_rows(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "mcc-result.pdf"
            rows = [
                ["SNo", "Rank", "Allotted Quota", "Allotted Institute", "Course", "Alloted Category", "Candidate Category", "Remarks"],
                ["1", "10", "All India", "Example Medical College", "MBBS", "Open", "General", "Allotted"],
                ["2", "25", "All India", "Example Medical College", "MBBS", "Open", "OBC", "Allotted"],
                ["3", "30", "All India", "Other College", "BDS", "OBC", "OBC", "Allotted"],
            ]
            table = Table(rows, colWidths=[25, 30, 55, 120, 40, 55, 55, 45], repeatRows=1)
            table.setStyle(TableStyle([
                ("GRID", (0, 0), (-1, -1), 0.5, colors.black),
                ("FONTSIZE", (0, 0), (-1, -1), 6),
            ]))
            SimpleDocTemplate(str(path), pagesize=A4).build([table])

            records = MODULE.extract(path)
            example = next(row for row in records if row["institute_name"] == "Example Medical College")

            self.assertEqual(10, example["opening_rank"])
            self.assertEqual(25, example["closing_rank"])
            self.assertEqual(2, example["allotment_count"])
            self.assertEqual({"General": 1, "OBC": 1}, example["candidate_category_counts"])


if __name__ == "__main__":
    unittest.main()
