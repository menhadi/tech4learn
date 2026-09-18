#!/usr/bin/env bash
set -euo pipefail
umask 077
test "$(id -u)" = 0
repo=/home/tech4learn/tech4learn-app
source="$repo/deploy/examelite"
test -z "$(runuser -u tech4learn -- git -C "$repo" status --porcelain)"
# Browser delivery stays on Tech4Learn. The private engine uses examelite.com;
# retired workspace subdomains do not require certificates or new vhosts.
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
php "$source/test-central-languages.php" /home/examelite/public_html/vendor/autoload.php
php "$source/test-native-plan-assignment.php" /home/examelite/public_html/vendor/autoload.php
php "$source/test-translated-model-answer.php" /home/examelite/public_html/vendor/autoload.php
php "$source/test-retained-exam-translation-images.php" /home/examelite/public_html/vendor/autoload.php
php "$source/test-translated-image-routes.php" /home/examelite/public_html/vendor/autoload.php
php "$source/test-proctor-evidence.php" /home/examelite/public_html/vendor/autoload.php
php "$source/test-pilot-exam-workflow.php" /home/examelite/public_html/vendor/autoload.php
runuser -u examelite -- php < "$source/migrate-workspace.php"
runuser -u examelite -- php /home/examelite/public_html/artisan config:clear
runuser -u examelite -- php /home/examelite/public_html/artisan route:clear
runuser -u examelite -- php /home/examelite/public_html/artisan view:clear
runuser -u examelite -- php < "$source/verify-workspace.php"
bash "$source/install-proctor-retention.sh"
python3 -B "$source/check-workspace-connection.py"
bash "$repo/deploy/virtualmin/update-ui-template.sh"
printf '\nSame-domain ExamElite integration installed. Refresh Tech4Learn and open Exams & results.\n'
printf 'Pilot check: enable Exams for the organisation, create or copy a question and exam, issue a student link, submit an attempt, mark it and publish its result.\n'
printf 'Keep staff and students on the Tech4Learn domain. Full feature parity and production workflow verification remain unfinished; see docs/examelite-central-content.md.\n'
