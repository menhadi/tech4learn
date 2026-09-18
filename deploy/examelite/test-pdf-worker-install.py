"""Validate the additive native PDF worker patch without application bootstrapping."""
import pathlib
import sys
import unittest
from workspace_install import protect_pdf_worker_lookup

native = pathlib.Path(sys.argv.pop(1)).read_text() if len(sys.argv) > 1 else None

class PdfWorkerInstallTest(unittest.TestCase):
    def test_native_patch_is_repeatable(self):
        self.assertIsNotNone(native, 'Supply the native GenerateExamPdfJob.php path.')
        patched = protect_pdf_worker_lookup(native)
        self.assertEqual(protect_pdf_worker_lookup(patched), patched)
        self.assertLess(patched.index('try {'), patched.index('->findOrFail($this->buildId)'))
        self.assertIn('$build?->update', patched)
        self.assertEqual(patched.count('$lock->release();'), 1)

    def test_modified_guard_is_rejected(self):
        patched = protect_pdf_worker_lookup(native)
        with self.assertRaises(ValueError):
            protect_pdf_worker_lookup(patched.replace('$build?->update', '$build->update'))
        with self.assertRaises(ValueError):
            protect_pdf_worker_lookup(patched + patched)
        with self.assertRaises(ValueError):
            protect_pdf_worker_lookup(patched.replace('$lock->release();', ''))

    def test_unknown_layout_is_rejected(self):
        with self.assertRaises(ValueError):
            protect_pdf_worker_lookup('unknown worker')

if __name__ == '__main__':
    unittest.main()
