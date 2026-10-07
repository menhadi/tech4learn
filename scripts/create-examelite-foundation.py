"""Copy the current ExamElite application into an ignored, isolated local pilot.

No databases, uploads, environment files, server caches or Git metadata are copied.
The source working tree is never modified. This is not a deployment command.
"""
import argparse
from concurrent.futures import ThreadPoolExecutor
import hashlib
import json
from pathlib import Path
import subprocess
from threading import Lock

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("source", type=Path)
parser.add_argument("destination", type=Path)
parser.add_argument("--resume", action="store_true", help="Resume an interrupted, unconfigured copy")
args = parser.parse_args()
source = args.source.resolve(strict=True)
destination = args.destination.resolve()
workspace = Path(__file__).resolve().parents[1]
local = (workspace / ".local").resolve()
if local not in destination.parents or (destination.exists() and not args.resume):
    raise SystemExit("Destination must be a new directory beneath this workspace's .local.")
if args.resume and any((destination / name).exists() for name in [".env", "foundation-source-manifest.json"]):
    raise SystemExit("Refusing to overwrite a completed or configured foundation.")
roots = {"app", "bootstrap", "config", "database", "lang", "resources", "routes", "tests", "stubs", "scripts", "api", "icon"}
root_files = {"artisan", "composer.json", "composer.lock", "package.json", "package-lock.json", "vite.config.js", "phpunit.xml", "server.php", ".editorconfig", ".gitattributes", "exam_pdf.php", "ai_assess.php"}
public_roots = {"MathJax", "build", "ckeditor", "assets", "fonts", "js", "templates"}
names = subprocess.check_output([
    "git", "-C", str(source), "ls-files", "-z", "--cached", "--others", "--exclude-standard", "--",
    *sorted(roots), *sorted(root_files),
    *("public/" + name for name in sorted(public_roots)),
    "public/index.php", "public/.htaccess", "public/extract_file.php",
]).decode().split("\0")
records, excluded, selected = [], [], []
for name in sorted(set(filter(None, names))):
    relative = Path(name)
    parts = relative.parts
    allowed = parts[0] in roots or name in root_files
    if parts[0] == "public":
        allowed = (len(parts) > 2 and parts[1] in public_roots) or name in {"public/index.php", "public/.htaccess", "public/extract_file.php"}
    blocked = (
        any(part.startswith(".env") or part in {"__pycache__", "node_modules", "vendor"} for part in parts)
        or (name.startswith("bootstrap/cache/") and relative.name != ".gitignore")
        or relative.suffix.lower() in {".sql", ".sqlite", ".db", ".pem", ".key", ".log", ".bak", ".backup", ".pyc"}
        or "google-credentials" in name.lower()
    )
    if not allowed or blocked:
        excluded.append(name)
        continue
    selected.append(name)

directory_lock = Lock()
prepared_directories = set()

def copy_application_file(name):
    original = source / name
    if not original.is_file():
        return None  # Locally deleted tracked files are not resurrected.
    if original.is_symlink():
        raise SystemExit(f"Refusing linked source: {name}")
    target = destination / name
    with directory_lock:
        if target.parent not in prepared_directories:
            resolved_parent = original.parent.resolve()
            if resolved_parent != source and source not in resolved_parent.parents:
                raise SystemExit(f"Refusing linked source directory: {name}")
            if original.parent != resolved_parent:
                raise SystemExit(f"Refusing linked source directory: {name}")
            target.parent.mkdir(parents=True, exist_ok=True)
            if target.parent != target.parent.resolve():
                raise SystemExit(f"Refusing linked destination directory: {name}")
            prepared_directories.add(target.parent)
    content = original.read_bytes()
    target.write_bytes(content)
    return {"path": name, "sha256": hashlib.sha256(content).hexdigest()}

with ThreadPoolExecutor(max_workers=12) as pool:
    records = []
    # Prepare runtime code before the large bundled font/library inventory.
    selected.sort(key=lambda name: (name.startswith("public/MathJax/"), name))
    for record in pool.map(copy_application_file, selected):
        if record:
            records.append(record)
            if len(records) % 5000 == 0:
                print(f"Copied {len(records)} application/asset files", flush=True)
for name in ["storage/app/private", "storage/framework/cache/data", "storage/framework/sessions", "storage/framework/views", "storage/logs", "bootstrap/cache"]:
    (destination / name).mkdir(parents=True, exist_ok=True)
manifest = {
    "source_commit": subprocess.check_output(["git", "-C", str(source), "rev-parse", "HEAD"]).decode().strip(),
    "includes_working_tree_edits": True,
    "purpose": "Local application foundation; not production-ready or approved for commit",
    "files": records,
    "excluded_paths": excluded,
}
(destination / "foundation-source-manifest.json").write_text(json.dumps(manifest, indent=2), encoding="utf-8")
print(json.dumps({"destination": str(destination), "application_files": len(records), "excluded_paths": len(excluded), "source_commit": manifest["source_commit"]}))
