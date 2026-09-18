"""Exercise deployment ordering without running installers or touching a server.

Usage: python test-deploy-workspace.py [path-to-bash]
"""
import pathlib
import subprocess
import sys
import tempfile
import unittest

BASH = sys.argv.pop(1) if len(sys.argv) > 1 else "bash"
SCRIPT = pathlib.Path(__file__).with_name("deploy-native-workspace.sh").read_text()


class DeploymentFlowTests(unittest.TestCase):
    def run_flow(self, case):
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            source = root / "deploy" / "examelite"
            source.mkdir(parents=True)
            for name in ("migrate-workspace.php", "verify-workspace.php"):
                (source / name).touch()
            script = root / "deploy.sh"
            script.write_text(SCRIPT.replace(
                "repo=/home/tech4learn/tech4learn-app",
                'repo="' + root.as_posix() + '"', 1), newline="\n")
            # All effectful command families are shell functions. An unexpected
            # old certificate/HTTP command has no executable available in PATH.
            harness = r'''
id() { printf '0\n'; }
runuser() {
  printf 'runuser %s\n' "$*" >&2
  if [[ "$*" == *"status --porcelain"* && "$CASE" == dirty ]]; then printf ' M file\n'; fi
  return 0
}
php() {
  printf 'php %s\n' "$*" >&2
  [[ "$CASE" != runtime || "$*" != *test-pdf-worker.php* ]] || return 93
  [[ "$CASE" != translation || "$*" != *test-translation-generation.php* ]] || return 94
  [[ "$CASE" != translation_worker || "$*" != *test-translation-worker.php* ]] || return 96
  [[ "$CASE" != translation_journey || "$*" != *test-translation-journey.php* ]] || return 97
  [[ "$CASE" != tests || "$*" != *test-pilot-exam-workflow.php* ]]
}
node() { printf 'node %s\n' "$*" >&2; [[ "$CASE" != renderer ]]; }
python3() {
  printf 'python3 %s\n' "$*" >&2
  [[ "$*" != *install-workspace-hosts.py* ]] || return 91
  [[ "$CASE" != worker || "$*" != *test-pdf-worker-install.py* ]] || return 92
  [[ "$CASE" != translation_guard || "$*" != *test-translation-install.py* ]] || return 95
  [[ "$CASE" != health || "$*" != *check-workspace-connection.py || "$*" == *--configuration-only* ]]
}
bash() { printf 'bash %s\n' "$*" >&2; }
PATH=/nonexistent
CASE="$1"
source "$2"
'''
            return subprocess.run([BASH, "-c", harness, "test", case,
                                   script.as_posix()], text=True, capture_output=True)

    def test_same_domain_success_without_workspace_certificate(self):
        result = self.run_flow("success")
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("Same-domain ExamElite integration installed", result.stdout)
        self.assertNotIn("install-workspace-hosts", result.stderr)
        self.assertLess(result.stderr.rindex("check-workspace-connection.py"),
                        result.stderr.index("update-ui-template.sh"))

    def test_health_failure_stops_before_tech4learn_update(self):
        result = self.run_flow("health")
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn("update-ui-template.sh", result.stderr)
        self.assertNotIn("integration installed", result.stdout)

    def test_native_failure_stops_before_migration(self):
        result = self.run_flow("tests")
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn("artisan", result.stderr)
        self.assertNotIn("update-ui-template.sh", result.stderr)

    def test_renderer_failure_stops_before_migration(self):
        result = self.run_flow("renderer")
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn("artisan", result.stderr)
        self.assertNotIn("update-ui-template.sh", result.stderr)

    def test_dirty_checkout_stops_before_installers(self):
        result = self.run_flow("dirty")
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn("python3", result.stderr)
        self.assertNotIn("php", result.stderr)

    def test_worker_guard_failure_stops_before_migration(self):
        result = self.run_flow("worker")
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn("artisan", result.stderr)
        self.assertNotIn("update-ui-template.sh", result.stderr)

    def test_worker_execution_failure_stops_before_migration(self):
        result = self.run_flow("runtime")
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn("runuser -u examelite", result.stderr)
        self.assertNotIn("artisan", result.stderr)
        self.assertNotIn("update-ui-template.sh", result.stderr)

    def test_translation_generation_failure_stops_before_migration(self):
        result = self.run_flow("translation")
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("test-translation-generation.php", result.stderr)
        self.assertNotIn("runuser -u examelite", result.stderr)
        self.assertNotIn("update-ui-template.sh", result.stderr)

    def test_translation_guard_failure_stops_before_migration(self):
        result = self.run_flow("translation_guard")
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn("runuser -u examelite", result.stderr)
        self.assertNotIn("update-ui-template.sh", result.stderr)

    def test_translation_worker_failure_stops_before_migration(self):
        result = self.run_flow("translation_worker")
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("test-translation-worker.php", result.stderr)
        self.assertNotIn("runuser -u examelite", result.stderr)
        self.assertNotIn("update-ui-template.sh", result.stderr)

    def test_translation_journey_failure_stops_before_migration(self):
        result = self.run_flow("translation_journey")
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("test-translation-journey.php", result.stderr)
        self.assertNotIn("runuser -u examelite", result.stderr)
        self.assertNotIn("update-ui-template.sh", result.stderr)


if __name__ == "__main__":
    unittest.main()
