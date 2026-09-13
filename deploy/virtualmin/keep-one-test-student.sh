#!/bin/bash
set -euo pipefail
umask 077
[ "$(id -u)" = 0 ]
[ "$#" = 3 ] || { echo 'Usage: keep-one-test-student.sh ORGANISATION_SLUG KEEP_STUDENT_CODE KEEP_EXACT_NAME'; exit 1; }
# This destructive test cleanup is deliberately limited to the agreed pilot.
[ "$1" = vector-academy ] || { echo 'Only vector-academy test cleanup is supported.'; exit 1; }
here=$(cd -- "$(dirname -- "$0")" && pwd)
mkdir -p /root/tech4learn-backups
backup=$(mktemp /root/tech4learn-backups/before-student-cleanup.XXXXXX.dump)
cd /tmp
runuser -u postgres -- pg_dump --port=5432 --format=custom tech4learn_app > "$backup"
test -s "$backup"
pg_restore --list "$backup" >/dev/null
printf 'Private database backup: %s\n' "$backup"
runuser -u postgres -- psql --port=5432 -X -v ON_ERROR_STOP=1 -v org="$1" -v code="$2" -v name="$3" -d tech4learn_app < "$here/keep-one-test-student.sql"
echo 'Student cleanup complete. Start a NEW attendance capture; old attendance snapshots remain historical records.'
