import importlib.util
import tempfile
import sys
import os
import types
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch

if os.name == 'nt':
    sys.modules['pwd'] = types.SimpleNamespace(getpwnam=lambda name: None)

spec = importlib.util.spec_from_file_location('helper', Path(__file__).with_name('tech4learn-admin.py'))
helper = importlib.util.module_from_spec(spec)
spec.loader.exec_module(helper)


class RestrictedAdminTests(unittest.TestCase):
    def test_unknown_and_extra_arguments_never_execute(self):
        for args in [['helper', 'shell'], ['helper', 'status', 'anything']]:
            with patch.object(helper.sys, 'argv', args), patch.object(helper.os, 'geteuid', return_value=0, create=True), patch.object(helper, 'run') as run:
                with self.assertRaises(RuntimeError):
                    helper.main()
                run.assert_not_called()

    def test_existing_database_is_not_adopted(self):
        with tempfile.TemporaryDirectory() as directory:
            private = Path(directory)/'private'
            with patch.object(helper, 'PRIVATE', private), patch.object(helper, 'run', return_value='1\n0') as run:
                with self.assertRaises(RuntimeError):
                    helper.prepare_database()
                self.assertFalse(private.exists())
                self.assertEqual(run.call_count, 1)
                self.assertNotIn('CREATE', run.call_args.args[1])

    def test_existing_environment_is_not_overwritten(self):
        with tempfile.TemporaryDirectory() as directory:
            with patch.object(helper, 'PRIVATE', Path(directory)), patch.object(helper, 'run') as run:
                with self.assertRaises(RuntimeError):
                    helper.prepare_database()
                run.assert_not_called()

    def test_preparation_targets_only_dedicated_database(self):
        with tempfile.TemporaryDirectory() as directory:
            private = Path(directory)/'private'
            with patch.object(helper, 'PRIVATE', private), patch.object(helper, 'run', side_effect=['0\n0', '']) as run, patch.object(helper.pwd, 'getpwnam', return_value=SimpleNamespace(pw_gid=123)), patch.object(helper.os, 'chown', create=True), patch('builtins.print'):
                helper.prepare_database()
                sql = run.call_args.args[1]
                self.assertIn('GRANT ALL PRIVILEGES ON tech4learn_exams.*', sql)
                self.assertNotIn('ON *.*', sql)
                self.assertNotIn('DROP ', sql)
                self.assertNotIn('GRANT OPTION', sql)
                content = (private/'native.env').read_text()
                self.assertIn('APP_DEBUG=false', content)
                self.assertIn('ATTENDANCE_API_URL=https://tech4learn.com/api/v1', content)
                if os.name != 'nt':
                    self.assertEqual((private/'native.env').stat().st_mode & 0o777, 0o640)


if __name__ == '__main__':
    unittest.main()
