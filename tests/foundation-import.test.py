"""Synthetic source-import checks: credentials/data exclusions and required glyphs."""
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest

WORKSPACE = Path(__file__).resolve().parents[1]
LOCAL = (WORKSPACE / ".local").resolve()

class FoundationImport(unittest.TestCase):
    def test_sanitises_defaults_preserves_glyphs_and_refuses_overwrite(self):
        root = Path(tempfile.mkdtemp(prefix="foundation-import-test-", dir=LOCAL)).resolve()
        self.assertIn(LOCAL, root.parents)
        try:
            source = root / ".local/tech4learn-foundation"
            scripts = root / "scripts"
            scripts.mkdir(parents=True)
            shutil.copyfile(WORKSPACE / "scripts/import-foundation-source.py", scripts / "import-foundation-source.py")
            fixtures = {
                "app/Providers/AppServiceProvider.php": b"<?php $auth_tkn = base64_decode('c3ludGhldGlj');",
                "public/extract_file.php": b"<?php $apiKey = getenv('OCR_SPACE_API_KEY') ?: 'SYNTHETIC_DEFAULT';",
                "public/MathJax/fonts/HTML-CSS/TeX/png/synthetic.png": b"synthetic glyph asset",
                "public/build/images/users/synthetic.jpg": b"synthetic excluded media",
                "api/db.php": b"synthetic excluded database tool",
                "database/synthetic.sqlite": b"synthetic excluded database",
                ".env": b"SYNTHETIC_SENTINEL=must-not-import",
            }
            rows = []
            for name, data in fixtures.items():
                path = source / name
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_bytes(data)
                rows.append({"path": name, "sha256": hashlib.sha256(data).hexdigest()})
            (source / "foundation-source-manifest.json").write_text(json.dumps({"source_commit": "0" * 40, "files": rows}))
            command = [sys.executable, str(scripts / "import-foundation-source.py")]
            result = subprocess.run(command, capture_output=True, text=True)
            self.assertEqual(result.returncode, 0, result.stderr)
            destination = root / "platform"
            self.assertIn("FRAMEWORK_SYNC_TOKEN", (destination / "app/Providers/AppServiceProvider.php").read_text())
            self.assertNotIn("base64_decode", (destination / "app/Providers/AppServiceProvider.php").read_text())
            self.assertNotIn("SYNTHETIC_DEFAULT", (destination / "public/extract_file.php").read_text())
            self.assertTrue((destination / "public/MathJax/fonts/HTML-CSS/TeX/png/synthetic.png").is_file())
            for name in [".env", "api/db.php", "database/synthetic.sqlite", "public/build/images/users/synthetic.jpg"]:
                self.assertFalse((destination / name).exists(), name)
            self.assertNotEqual(subprocess.run(command, capture_output=True).returncode, 0)
        finally:
            if LOCAL in root.resolve().parents and root.name.startswith("foundation-import-test-"):
                shutil.rmtree(root)

if __name__ == "__main__":
    unittest.main()
