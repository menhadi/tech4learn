#!/usr/bin/env python3
"""User-run root preparation: fixed Tech4Learn runtime settings only."""
import datetime
import os
from pathlib import Path
import re
import stat
import tempfile

TARGET = Path("/etc/tech4learn-native/native.env")
VALUES = {
    "PHP_CLI_BINARY": "/usr/bin/php8.4",
    "PAPER_PROCESSING_WORKERS": "1",
    "PAPER_PROCESSING_MAX_PARALLEL": "1",
    "PAPER_PROCESSING_MAX_HEAVY": "1",
    "NATIVE_SCHEDULE_LIFECYCLE_EMAILS": "false",
    "NATIVE_SCHEDULE_SEARCH_CONSOLE": "false",
}

def transform_environment(original):
    text = original.decode("utf-8")
    lines = []
    for line in text.splitlines():
        match = re.match(r"^\s*(?:export\s+)?([A-Z_][A-Z0-9_]*)\s*=", line)
        if not match or match.group(1) not in VALUES:
            lines.append(line)
    return ("\n".join(lines) + "\n" + "\n".join(f"{key}={value}" for key, value in VALUES.items()) + "\n").encode()

def main():
    if os.geteuid() != 0:
        raise RuntimeError()
    original_stat = TARGET.lstat()
    if not stat.S_ISREG(original_stat.st_mode) or original_stat.st_nlink != 1 or TARGET.resolve() != TARGET:
        raise RuntimeError()
    if original_stat.st_mode & 0o027:
        raise RuntimeError()
    original = TARGET.read_bytes()
    updated = transform_environment(original)
    if updated == original:
        print("Native runtime profile already prepared; no changes.")
        return
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%S%fZ")
    backup = TARGET.with_name("native.env.before-runtime-profile-" + stamp)
    fd = os.open(backup, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, "wb") as output:
        output.write(original)
        output.flush()
        os.fsync(output.fileno())
    fd, temporary = tempfile.mkstemp(prefix=".native-runtime-", dir=TARGET.parent)
    try:
        with os.fdopen(fd, "wb") as output:
            os.fchmod(output.fileno(), stat.S_IMODE(original_stat.st_mode))
            os.fchown(output.fileno(), original_stat.st_uid, original_stat.st_gid)
            output.write(updated)
            output.flush()
            os.fsync(output.fileno())
        current = TARGET.lstat()
        if (current.st_ino, current.st_mtime_ns, current.st_size) != (original_stat.st_ino, original_stat.st_mtime_ns, original_stat.st_size):
            raise RuntimeError()
        os.replace(temporary, TARGET)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)
    print("Six fixed native runtime settings prepared; private backup saved. No service or routing changes.")

if __name__ == "__main__":
    try:
        main()
    except Exception:
        raise SystemExit("Native runtime preparation blocked; inspect private state. No secrets printed.")
