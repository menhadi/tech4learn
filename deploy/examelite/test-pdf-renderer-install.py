"""Guard the native renderer without bootstrapping ExamElite or launching a browser."""
import pathlib
import json
import sys
import subprocess
import unittest
from workspace_install import require_pdf_images, refresh_pdf_image_cache

native = pathlib.Path(sys.argv.pop(1)).read_text() if len(sys.argv) > 1 else None
cache = pathlib.Path(sys.argv.pop(1)).read_text() if len(sys.argv) > 1 else None

class PdfRendererInstallTest(unittest.TestCase):
    def test_cache_invalidation(self):
        original = 'public const TEMPLATE_VERSION = 2;'
        patched = refresh_pdf_image_cache(original)
        self.assertIn('TEMPLATE_VERSION = 3;', patched)
        self.assertEqual(refresh_pdf_image_cache(patched), patched)
        with self.assertRaises(ValueError):
            refresh_pdf_image_cache('public const TEMPLATE_VERSION = 99;')

    def test_installed_cache(self):
        if cache is None:
            self.skipTest('Supply the native cache service to check its deployed schema.')
        patched = refresh_pdf_image_cache(cache)
        self.assertEqual(refresh_pdf_image_cache(patched), patched)
        if 'FINGERPRINT_SCHEMA_VERSION' in cache:
            self.assertEqual(patched, cache, 'Preserve native content fingerprints and approved artifacts')
            for changed in [cache.replace('SCHEMA_VERSION = 1;', 'SCHEMA_VERSION = 2;'),
                            cache.replace('TEMPLATE_VERSION = 23;', 'TEMPLATE_VERSION = 24;'),
                            cache.replace("'solution' => $solution,", "'template' => self::TEMPLATE_VERSION,"),
                            cache + cache]:
                with self.assertRaises(ValueError):
                    refresh_pdf_image_cache(changed)

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

    def test_native_removed_images_still_prevent_printing(self):
        patched = require_pdf_images(native)
        start = patched.index('  // Tech4Learn: incomplete diagrams')
        end = patched.index("  await page.emulateMedia({ media: 'print' });", start)
        guard = patched[start:end]
        # The newer native renderer removes broken images before this guard.
        # Its retained warnings must still prevent an incomplete exam paper.
        script = "const assert = require('node:assert/strict');\n" + \
            "const run = new Function('imageWarnings', 'page', 'document', " + \
            json.dumps('return (async () => {\n' + guard + '\n})();') + \
            ");\n(async () => {\n" + \
            "const page = { evaluate: async fn => fn() };\n" + \
            "await run([], page, { images: [] });\n" + \
            "await run(undefined, page, { images: [] });\n" + \
            "await assert.rejects(run(['synthetic missing diagram'], page, { images: [] }), /print image could not be loaded/);\n" + \
            "})().catch(error => { console.error(error); process.exitCode = 1; });"
        subprocess.run(['node', '-e', script], check=True)

    def test_upgrade_previous_guard(self):
        patched = require_pdf_images(native)
        previous = patched.replace("  if (typeof imageWarnings !== 'undefined' && imageWarnings.length) {\n    throw new Error('A print image could not be loaded. Retry after restoring the source image.');\n  }\n", '')
        self.assertEqual(require_pdf_images(previous), patched)

if __name__ == '__main__':
    unittest.main()
