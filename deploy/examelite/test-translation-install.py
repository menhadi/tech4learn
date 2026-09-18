"""Guard native source without bootstrapping an application or changing it."""
import pathlib
import sys
import unittest
from workspace_install import protect_translation_inputs

SOURCE = pathlib.Path(sys.argv.pop(1)).read_text() if len(sys.argv) > 1 else None

class TranslationInstallTests(unittest.TestCase):
    def test_native_source_and_repeat(self):
        self.assertIsNotNone(SOURCE, 'Supply the native ExamTranslationService.php source')
        patched = protect_translation_inputs(SOURCE)
        self.assertEqual(protect_translation_inputs(patched), patched)
        self.assertEqual(patched.count('// Tech4Learn: provider latency'), 1)
        self.assertIn('lockForUpdate()', patched)

    def test_modified_guard_is_rejected(self):
        patched = protect_translation_inputs(SOURCE)
        with self.assertRaises(ValueError):
            protect_translation_inputs(patched.replace('Translation inputs changed.', 'Changed.'))

    def test_unknown_or_duplicate_transaction_is_rejected(self):
        with self.assertRaises(ValueError):
            protect_translation_inputs('<?php class Unknown {}')
        with self.assertRaises(ValueError):
            protect_translation_inputs(SOURCE + SOURCE)

if __name__ == '__main__':
    unittest.main()
