"""Guard the native renderer without bootstrapping ExamElite or launching a browser."""
import pathlib
import sys
import unittest
from workspace_install import require_pdf_images, refresh_pdf_image_cache

native = pathlib.Path(sys.argv.pop(1)).read_text() if len(sys.argv) > 1 else None

class PdfRendererInstallTest(unittest.TestCase):
    def test_cache_invalidation(self):
        original = 'public const TEMPLATE_VERSION = 2;'
        patched = refresh_pdf_image_cache(original)
        self.assertIn('TEMPLATE_VERSION = 3;', patched)
        self.assertEqual(refresh_pdf_image_cache(patched), patched)
        with self.assertRaises(ValueError):
            refresh_pdf_image_cache('public const TEMPLATE_VERSION = 99;')

    def test_checked_native_patch(self):
        self.assertIsNotNone(native, 'Supply the native renderer path.')
        patched = require_pdf_images(native)
        self.assertEqual(require_pdf_images(patched), patched)
        self.assertIn('image.naturalWidth <= 0', patched)
        self.assertLess(patched.index('image.naturalWidth <= 0'), patched.index('await page.pdf({'))
        with self.assertRaises(ValueError):
            require_pdf_images(patched.replace('image.naturalWidth <= 0', 'false'))

    def test_unknown_or_duplicate_layout(self):
        with self.assertRaises(ValueError):
            require_pdf_images('unrecognised renderer')
        with self.assertRaises(ValueError):
            require_pdf_images(native + native)

if __name__ == '__main__':
    unittest.main()
