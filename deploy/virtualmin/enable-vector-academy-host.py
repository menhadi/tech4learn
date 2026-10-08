#!/usr/bin/env python3
"""User-run root step: add only the fresh Vector Academy host to Tech4Learn."""
from datetime import datetime, timezone
from pathlib import Path
import hashlib
import os
import subprocess

CONFIG = Path('/etc/apache2/sites-available/tech4learn.com.conf')
REVIEWED_HASH = 'af3379ef74c0efa38e2d7c6e3536f47e08c6c291bca4b2f1ce40f3031f4095f3'

def run(*args):
    subprocess.run(args, check=True, capture_output=True, timeout=30,
                   env={'PATH': '/usr/sbin:/usr/bin:/sbin:/bin'})

def main():
    if os.geteuid() != 0 or CONFIG.is_symlink():
        raise RuntimeError('Root and a regular fixed configuration are required.')
    source = CONFIG.read_bytes()
    if hashlib.sha256(source).hexdigest() != REVIEWED_HASH:
        raise RuntimeError('Reviewed configuration changed; no changes made.')
    text = source.decode()
    anchor = '    ServerName tech4learn.com\n'
    if text.count(anchor) != 2:
        raise RuntimeError('Expected exactly two Tech4Learn virtual hosts.')
    candidate = text.replace(anchor, anchor + '    ServerAlias vector-academy.tech4learn.com\n')
    run('/usr/sbin/apache2ctl', 'configtest')
    backup = Path('/etc/tech4learn-native') / ('before-vector-host-' + datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%S%fZ'))
    with backup.open('xb') as output:
        os.chmod(backup, 0o600)
        output.write(source)
    try:
        CONFIG.write_text(candidate)
        run('/usr/sbin/apache2ctl', 'configtest')
        run('/usr/bin/systemctl', 'reload', 'apache2')
    except Exception:
        CONFIG.write_bytes(source)
        run('/usr/sbin/apache2ctl', 'configtest')
        run('/usr/bin/systemctl', 'reload', 'apache2')
        raise RuntimeError('Installation failed; previous configuration restored.')
    print('Vector Academy host enabled. Private backup: ' + str(backup))
    print('DNS and organisation administrator login still require verification.')

if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print(str(error) if isinstance(error, RuntimeError) else 'Host installation failed; inspect private server diagnostics.')
        raise SystemExit(1)
