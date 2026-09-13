#!/usr/bin/env bash
# Explicit user-run deployment only, after the workspace schema is installed.
set -euo pipefail
umask 077
test "$(id -u)" = 0
source=$(cd -- "$(dirname -- "$0")" && pwd)
test -d /run/systemd/system
test -x /usr/bin/php
id examelite >/dev/null
test -f /home/examelite/public_html/app/Services/Tech4LearnProctorEvidence.php
php -l "$source/purge-proctor-evidence.php"
for target in /usr/local/lib/tech4learn /usr/local/lib/tech4learn/purge-proctor-evidence.php /etc/systemd/system/tech4learn-proctor-cleanup.service /etc/systemd/system/tech4learn-proctor-cleanup.timer; do
  test ! -L "$target" || { printf 'Refusing symlink: %s\n' "$target" >&2; exit 1; }
done
backup=$(mktemp -d /root/t4l-proctor-retention.XXXXXX)
for name in tech4learn-proctor-cleanup.service tech4learn-proctor-cleanup.timer; do
  if test -f "/etc/systemd/system/$name"; then cp -p "/etc/systemd/system/$name" "$backup/$name"; fi
done
if test -f /usr/local/lib/tech4learn/purge-proctor-evidence.php; then
  cp -p /usr/local/lib/tech4learn/purge-proctor-evidence.php "$backup/purge-proctor-evidence.php"
fi
install -d -o root -g root -m 0755 /usr/local/lib/tech4learn
install -o root -g root -m 0644 "$source/purge-proctor-evidence.php" /usr/local/lib/tech4learn/purge-proctor-evidence.php
install -o root -g root -m 0644 "$source/tech4learn-proctor-cleanup.service" /etc/systemd/system/tech4learn-proctor-cleanup.service
install -o root -g root -m 0644 "$source/tech4learn-proctor-cleanup.timer" /etc/systemd/system/tech4learn-proctor-cleanup.timer
systemctl daemon-reload
# Fail deployment if the installed schema or maintenance command cannot run.
systemctl start tech4learn-proctor-cleanup.service
systemctl enable --now tech4learn-proctor-cleanup.timer
systemctl is-active --quiet tech4learn-proctor-cleanup.timer
printf 'Private evidence cleanup checked and scheduled hourly. Backup: %s\n' "$backup"
