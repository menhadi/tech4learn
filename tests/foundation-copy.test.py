"""Check source import boundaries with disposable synthetic files, not live data."""
import json
from pathlib import Path
import shutil
import stat
import subprocess
import sys
import tempfile
import unittest

WORKSPACE = Path(__file__).resolve().parents[1]
LOCAL = (WORKSPACE / ".local").resolve()
SCRIPT = WORKSPACE / "scripts/create-examelite-foundation.py"


class FoundationCopyBoundaries(unittest.TestCase):
    def test_import_preserves_edits_and_excludes_runtime_data(self):
        LOCAL.mkdir(exist_ok=True)
        root = Path(tempfile.mkdtemp(prefix="foundation-copy-test-", dir=LOCAL)).resolve()
        self.assertIn(LOCAL, root.parents)
        try:
            source = root / "source"
            source.mkdir()
            fixture = {
                "app/Example.php": "<?php // original synthetic application\n",
                "public/index.php": "<?php // synthetic entry point\n",
                "scripts/worker.py": "# synthetic worker\n",
                ".env": "SYNTHETIC_SENTINEL=do-not-copy\n",
                "database/data.sqlite": "synthetic database sentinel",
                "public/uploads/synthetic.txt": "synthetic upload sentinel",
                "bootstrap/cache/config.php": "<?php // synthetic cached config\n",
                "composer.json": "{}",
                "composer.lock": "{}",
            }
            for name, content in fixture.items():
                path = source / name
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text(content)
            def git(*args):
                subprocess.run(["git", "-C", str(source), *args], check=True, capture_output=True)
            git("init")
            git("add", ".")
            git("-c", "user.name=Synthetic fixture", "-c", "user.email=fixture@example.invalid", "commit", "-m", "Synthetic import fixture")
            (source / "app/Example.php").write_text("<?php // current working-tree edit\n")
            (source / "app/New.php").write_text("<?php // new working-tree code\n")
            destination = root / "copy"
            command = [sys.executable, str(SCRIPT), str(source), str(destination)]
            copied = subprocess.run(command, capture_output=True, text=True)
            self.assertEqual(copied.returncode, 0, copied.stderr)
            self.assertIn("working-tree edit", (destination / "app/Example.php").read_text())
            self.assertTrue((destination / "app/New.php").is_file())
            self.assertTrue((destination / "scripts/worker.py").is_file())
            for name in [".env", "database/data.sqlite", "public/uploads/synthetic.txt", "bootstrap/cache/config.php"]:
                self.assertFalse((destination / name).exists(), name)
            manifest = json.loads((destination / "foundation-source-manifest.json").read_text())
            self.assertEqual(len(manifest["source_commit"]), 40)
            refused = subprocess.run(command + ["--resume"], capture_output=True, text=True)
            self.assertNotEqual(refused.returncode, 0)
            outside = WORKSPACE / "foundation-copy-test-outside"
            self.assertFalse(outside.exists())
            refused = subprocess.run([sys.executable, str(SCRIPT), str(source), str(outside)], capture_output=True, text=True)
            self.assertNotEqual(refused.returncode, 0)
            self.assertFalse(outside.exists())
        finally:
            # Delete only this verified, generated fixture directory.
            if LOCAL in root.resolve().parents and root.name.startswith("foundation-copy-test-"):
                def remove_readonly(function, name, error):
                    path = Path(name).resolve()
                    if root != path and root not in path.parents:
                        raise RuntimeError("Cleanup escaped the generated fixture")
                    path.chmod(stat.S_IWRITE | stat.S_IREAD)
                    function(name)
                shutil.rmtree(root, onerror=remove_readonly)


if __name__ == "__main__":
    unittest.main()
