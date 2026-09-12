"""Exercise deployment failure paths without contacting systemd, Docker or PostgreSQL."""
import os
from pathlib import Path
import shutil
import subprocess
import sys
import unittest

HERE = Path(__file__).resolve().parent
BASH = 'C:/Program Files/Git/bin/bash.exe' if sys.platform == 'win32' else shutil.which('bash')
ROOT = HERE.parents[1]
FIXTURES = ROOT / '.local' / 'face-deploy-tests'

def shell_path(path):
    value = path.as_posix()
    return '/' + value[0].lower() + value[2:] if sys.platform == 'win32' else value

class Deployment(unittest.TestCase):
    def run_case(self, name, script, mocks):
        case = FIXTURES / name
        case.mkdir(parents=True, exist_ok=True)
        (case / 'repo').mkdir(exist_ok=True)
        event = case / 'events'
        event.write_text('', encoding='utf-8')
        source = (HERE / script).read_text(encoding='utf-8')
        source = source.replace('/home/tech4learn/tech4learn-app', shell_path(case / 'repo'))
        source = source.replace('/root/tech4learn-backups', shell_path(case / 'backups'))
        source = source.replace('cd /tmp', 'cd "$CASE_DIR"')
        source = source.replace('test -x /usr/bin/docker', 'true').replace('test -x /usr/bin/python3', 'true')
        (case / 'script.sh').write_text(source, encoding='utf-8', newline='\n')
        harness = 'id() { printf 0; }\n' + mocks + '\nsource "$CASE_DIR/script.sh"\n'
        result = subprocess.run([BASH, '-c', harness], env={**os.environ,'CASE_DIR':shell_path(case)}, capture_output=True, text=True, timeout=10)
        return result, event.read_text(encoding='utf-8').splitlines()

    def test_failed_controller_cannot_prevent_api_restart(self):
        result, events = self.run_case('failed-controller', 'update-face-control.sh', r"""
runuser() { case "$*" in *pg_dump*) printf synthetic-backup;; esac; return 0; }
pg_restore() { return 0; }
systemctl() { printf '%s\n' "$*" >> "$CASE_DIR/events"; }
curl() { printf 'health\n' >> "$CASE_DIR/events"; }
bash() { printf 'controller\n' >> "$CASE_DIR/events"; return 1; }
""")
        self.assertEqual(result.returncode, 1, result.stderr)
        self.assertEqual(events, ['stop tech4learn','start tech4learn','health','health','controller'])
        self.assertIn('app remains running', result.stderr)

    def photo_release(self, name, fail_migration=0, fail_health=0):
        return self.run_case(name, 'update-student-photos.sh', r"""
runuser() {
  case "$*" in
    *pg_dump*) printf synthetic-backup;;
    *'npm run build'*) printf 'build\n' >> "$CASE_DIR/events";;
    *' migrate') printf 'migrate\n' >> "$CASE_DIR/events"; return FAIL_MIGRATION;;
  esac
  return 0
}
cp() { printf 'copy\n' >> "$CASE_DIR/events"; }
pg_restore() { return 0; }
systemctl() { printf '%s\n' "$*" >> "$CASE_DIR/events"; }
curl() { printf 'health\n' >> "$CASE_DIR/events"; return FAIL_HEALTH; }
""".replace('FAIL_MIGRATION',str(fail_migration)).replace('FAIL_HEALTH',str(fail_health)))

    def test_photo_migration_failure_restores_files_and_restarts_api(self):
        result, events = self.photo_release('photo-migration-fails',1)
        self.assertEqual(result.returncode,1,result.stderr)
        self.assertEqual(events,['copy','copy','build','stop tech4learn','migrate','copy','copy','restart tech4learn'])

    def test_photo_health_failure_restarts_restored_application(self):
        result, events = self.photo_release('photo-health-fails',0,1)
        self.assertEqual(result.returncode,1,result.stderr)
        self.assertEqual(events[-4:],['health','copy','copy','restart tech4learn'])

    def test_photo_release_builds_before_stopping_and_keeps_new_files(self):
        result, events = self.photo_release('photo-release-success')
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertEqual(events,['copy','copy','build','stop tech4learn','migrate','start tech4learn','health','health'])

    def installer(self, name, ready):
        return self.run_case(name, 'install-face-control.sh', r"""
probes=0
install() { return 0; }
docker() { return 0; }
systemctl() { return 0; }
sleep() { printf 'wait\n' >> "$CASE_DIR/events"; }
runuser() {
  case "$*" in
    *' -- test -S '*) probes=$((probes+1)); (( probes >= READY ));;
    *' -- test -w '*) return 0;;
    *' -- curl '*) printf 'controller-check\n' >> "$CASE_DIR/events";;
    *) return 0;;
  esac
}
""".replace('READY', str(ready)))

    def test_delayed_socket_is_checked_after_becoming_ready(self):
        result, events = self.installer('delayed-socket', 3)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(events, ['wait','wait','controller-check'])

    def test_missing_socket_has_bounded_wait_and_clear_failure(self):
        result, events = self.installer('missing-socket', 999)
        self.assertEqual(result.returncode, 1)
        self.assertEqual(events, ['wait']*20)
        self.assertIn('socket is not ready', result.stderr)

if __name__ == '__main__': unittest.main()
