"""Guarded native upload patch; no deployed bootstrap or files are changed."""
import pathlib
import sys
import unittest
from workspace_install import isolate_subjective_upload_names, protect_subjective_upload_state

source = pathlib.Path(sys.argv.pop(1)).read_text() if len(sys.argv) > 1 else ''

class SubjectiveUploadInstallTest(unittest.TestCase):
    def test_state_guard_is_repeatable_and_fails_closed(self):
        patched = protect_subjective_upload_state(isolate_subjective_upload_names(source))
        self.assertEqual(protect_subjective_upload_state(patched), patched)
        self.assertEqual(isolate_subjective_upload_names(patched), patched)
        for bad in [patched.replace('$examResult->end_time', 'false'), patched.replace('->lockForUpdate()', ''), patched + patched]:
            with self.assertRaises(ValueError):
                protect_subjective_upload_state(bad)
    def test_repeatable(self):
        patched = isolate_subjective_upload_names(source)
        self.assertEqual(isolate_subjective_upload_names(patched), patched)
        self.assertNotIn("$fileName = time()", patched)
        self.assertIn('bin2hex(random_bytes(20))', patched)

    def test_unknown_or_modified_fails(self):
        patched = isolate_subjective_upload_names(source)
        for bad in ['unknown', source + source, patched.replace('random_bytes(20)', 'random_bytes(1)'), patched.replace("$path === ''", "$path === null")]:
            with self.assertRaises(ValueError):
                isolate_subjective_upload_names(bad)

if __name__ == '__main__':
    unittest.main()
