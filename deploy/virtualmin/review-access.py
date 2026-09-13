#!/usr/bin/python3
"""Fixed read-only diagnostic commands for the dedicated SSH review account."""
import os
from pathlib import Path
import subprocess
import sys

ROOT = Path("/home/tech4learn/tech4learn-app")
ALLOWED = ("apps/api/src/", "apps/admin/src/", "packages/contracts/src/", "docs/", "deploy/virtualmin/")
EXTENSIONS = {".ts", ".tsx", ".js", ".mjs", ".md", ".sh", ".py", ".css"}

def source_path(name):
    if not name.startswith(ALLOWED) or ".." in Path(name).parts or Path(name).suffix not in EXTENSIONS:
        raise ValueError("Only application source and documentation are available.")
    if ROOT.resolve() != ROOT or ROOT.is_symlink():
        raise ValueError("Unexpected repository location.")
    path = ROOT / name
    if path.is_symlink() or not path.resolve().is_relative_to(ROOT.resolve()):
        raise ValueError("Path unavailable.")
    if path.stat().st_size > 500_000:
        raise ValueError("File exceeds review size limit.")
    return path

def main(command):
    if command == "status":
        subprocess.run(["/usr/bin/systemctl", "is-active", "tech4learn", "tech4learn-face-control"], check=False)
        subprocess.run(["/usr/bin/docker", "ps", "--filter", "name=tech4learn-face", "--format", "{{.Names}} {{.Status}}"], check=False)
    elif command == "files":
        for prefix in ALLOWED:
            for path in sorted((ROOT / prefix).rglob("*")):
                if path.is_file() and path.suffix in EXTENSIONS:
                    try:
                        name = path.relative_to(ROOT).as_posix()
                        source_path(name)
                        print(name)
                    except (ValueError, OSError):
                        pass
    elif command.startswith("read "):
        print(source_path(command[5:]).read_text(encoding="utf-8"))
    else:
        raise ValueError("Available commands: status, files, read <source path>. No shell or file writes.")

if __name__ == "__main__":
    try:
        main(os.environ.get("SSH_ORIGINAL_COMMAND", ""))
    except (ValueError, OSError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
