"""Versionable native application import; no environment, uploads or legacy DB tools."""
import hashlib
import json
from pathlib import Path
import re
import sys
from threading import Lock
from concurrent.futures import ThreadPoolExecutor

workspace = Path(__file__).resolve().parents[1]
source = workspace / ".local/tech4learn-foundation"
target = workspace / "platform"
extend_assets = "--extend-assets" in sys.argv
if target.exists() and not extend_assets and ("--resume" not in sys.argv or (target / "source-origin.json").exists()):
    raise SystemExit("Refusing to overwrite an existing platform source directory.")
manifest = json.loads((source / "foundation-source-manifest.json").read_text())
if extend_assets and json.loads((target / "source-origin.json").read_text())["upstream_commit"] != manifest["source_commit"]:
    raise SystemExit("Asset extension requires the same source revision.")
excluded, selected = [], []
media = {".png", ".jpg", ".jpeg", ".webp", ".gif", ".mp3", ".ogg", ".mp4", ".pdf", ".docx", ".xlsx", ".csv", ".sql", ".sqlite", ".db", ".pem", ".key"}
for row in manifest["files"]:
    name = row["path"]
    p = Path(name)
    if p.is_absolute() or ".." in p.parts:
        raise SystemExit("Unsafe manifest path")
    package_bitmap = p.suffix.lower() in {".png", ".gif"} and name.startswith(("public/MathJax/fonts/", "public/ckeditor/"))
    if name.startswith(("api/", "resources/json/")) or (p.suffix.lower() in media and not package_bitmap) or any(x.startswith(".env") for x in p.parts):
        excluded.append(name)
    else:
        selected.append(name)

directory_lock = Lock()
checked_directories = set()

def import_file(name):
    original = source / name
    if original.is_symlink():
        raise RuntimeError("Linked source refused")
    data = original.read_bytes()
    patches = []
    if name == "public/index.php":
        text = data.decode("utf-8")
        text = re.sub(r"^ini_set\('display_(?:startup_)?errors', 1\);\r?\n", "", text, flags=re.MULTILINE)
        data = text.encode("utf-8")
        patches.append("framework-controlled-error-display")
    if name == "app/Providers/AppServiceProvider.php":
        text = data.decode("utf-8")
        text, count = re.subn(r"\$auth_tkn\s*=\s*base64_decode\('[^']+'\);", "$auth_tkn = (string) env('FRAMEWORK_SYNC_TOKEN', '');\n                    if ($auth_tkn === '') { return; }", text)
        if count != 1:
            raise RuntimeError("Review changed framework credential source")
        data = text.encode("utf-8")
        patches.append("framework-token-from-environment")
    if name == "public/extract_file.php":
        text = data.decode("utf-8")
        text, count = re.subn(r'''(\$apiKey\s*=\s*getenv\([^)]+\)\s*\?:\s*)(['"])[^'"\r\n]+\2;''', lambda match: match.group(1) + "'';\n    if ($apiKey === '') { return ['success' => false, 'text' => '']; }", text)
        if count != 1:
            raise RuntimeError("Review changed OCR credential source")
        data = text.encode("utf-8")
        patches.append("ocr-key-from-environment")
    # Report path only; never disclose matched credential values.
    if name.endswith((".php", ".py", ".js", ".json")):
        text = data.decode("utf-8", errors="replace").replace("sk-test-key-for-development", "synthetic-placeholder")
        if re.search(r"(?<![A-Za-z0-9_])(?:AIza[A-Za-z0-9_-]{28,}|sk-[A-Za-z0-9_-]{24,}|AKIA[A-Z0-9]{16}|gh[pousr]_[A-Za-z0-9]{24,}|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY)", text):
            raise RuntimeError(f"Credential-pattern review required: {name}")
    output = target / name
    with directory_lock:
        if output.parent not in checked_directories:
            if source.resolve() not in original.parent.resolve().parents and original.parent.resolve() != source.resolve():
                raise RuntimeError("Linked source directory refused")
            output.parent.mkdir(parents=True, exist_ok=True)
            if target.resolve() not in output.parent.resolve().parents and output.parent.resolve() != target.resolve():
                raise RuntimeError("Linked destination directory refused")
            checked_directories.add(output.parent)
    if output.exists():
        if output.is_symlink() or output.read_bytes() != data:
            raise RuntimeError(f"Refusing to overwrite changed import file: {name}")
    else:
        output.write_bytes(data)
    return {"path": name, "sha256": hashlib.sha256(data).hexdigest(), "patches": patches}

with ThreadPoolExecutor(max_workers=8) as pool:
    records = list(pool.map(import_file, selected))
(target / "source-origin.json").write_text(json.dumps({
    "upstream_commit": manifest["source_commit"],
    "includes_working_tree_edits": True,
    "files": records,
    "excluded_paths": excluded,
    "notes": "No content database or uploaded media imported. Source only; deployment acceptance pending.",
}, indent=2), encoding="utf-8")
print(json.dumps({"imported_files": len(records), "excluded_files": len(excluded), "destination": str(target)}))
