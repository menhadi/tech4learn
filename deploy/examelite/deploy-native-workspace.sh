#!/usr/bin/env bash
set -euo pipefail
umask 077
test "$(id -u)" = 0
repo=/home/tech4learn/tech4learn-app
source="$repo/deploy/examelite"
test -z "$(runuser -u tech4learn -- git -C "$repo" status --porcelain)"
test -f /etc/letsencrypt/live/examelite-workspaces/fullchain.pem || {
  printf '%s\n' "First obtain the wildcard certificate with: certbot certonly --manual --preferred-challenges dns --cert-name examelite-workspaces -d '*.examelite.com'" >&2
  exit 1
}
openssl x509 -in /etc/letsencrypt/live/examelite-workspaces/fullchain.pem -checkend 86400 -noout
python3 -B "$source/install-workspace-hosts.py" --preflight
python3 -B "$source/check-workspace-connection.py" --configuration-only
python3 -B "$source/test-workspace-install.py"
php "$source/test-workspace-policy.php"
php "$source/test-workspace-provision.php" /home/examelite/public_html/vendor/autoload.php
php "$source/test-workspace-views.php" /home/examelite/public_html/vendor/autoload.php
php "$source/test-content-copies.php" /home/examelite/public_html/vendor/autoload.php
php "$source/test-content-api.php" /home/examelite/public_html/vendor/autoload.php
php "$source/test-question-authoring.php" /home/examelite/public_html/vendor/autoload.php
php "$source/test-launch-tickets.php" /home/examelite/public_html/vendor/autoload.php
php "$source/test-workspace-gate.php" /home/examelite/public_html/vendor/autoload.php
python3 -B "$source/install-read-connector.py"
python3 -B "$source/install-workspace.py"
runuser -u examelite -- php < "$source/migrate-workspace.php"
runuser -u examelite -- php /home/examelite/public_html/artisan config:clear
runuser -u examelite -- php /home/examelite/public_html/artisan route:clear
runuser -u examelite -- php /home/examelite/public_html/artisan view:clear
runuser -u examelite -- php < "$source/verify-workspace.php"
python3 -B "$source/check-workspace-connection.py"
python3 -B "$source/install-workspace-hosts.py"
http_code=$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' https://t4l-00000000000000000000000000000000.examelite.com/tech4learn/launch)
test "$http_code" = 404 || {
  printf 'Workspace TLS/routing check returned HTTP %s; Tech4Learn has not been updated.\n' "$http_code" >&2
  exit 1
}
bash "$repo/deploy/virtualmin/update-ui-template.sh"
printf '\nNative ExamElite workspace installed. Refresh Tech4Learn and open Exams & results → ExamElite workspace.\n'
printf 'Superadmin: enable Exams for the pilot organisation and test question sharing and pulling. In-page authoring and exam taking are still under development.\n'
