#!/usr/bin/env bash
set -euo pipefail
umask 077
test "$(id -u)" = 0
repo=/home/tech4learn/tech4learn-app
source="$repo/deploy/examelite"
test -z "$(runuser -u tech4learn -- git -C "$repo" status --porcelain)"
test -f /home/examelite/public_html/app/Providers/Tech4LearnWorkspaceProvider.php
python3 -B "$source/check-workspace-connection.py" --configuration-only
php "$source/test-content-api.php" /home/examelite/public_html/vendor/autoload.php
php "$source/test-question-authoring.php" /home/examelite/public_html/vendor/autoload.php
php "$source/test-workspace-provision.php" /home/examelite/public_html/vendor/autoload.php
python3 -B "$source/install-read-connector.py"
python3 -B "$source/install-workspace.py"
php "$source/test-exam-authoring.php" /home/examelite/public_html/vendor/autoload.php
runuser -u examelite -- php < "$source/migrate-workspace.php"
runuser -u examelite -- php /home/examelite/public_html/artisan config:clear
runuser -u examelite -- php /home/examelite/public_html/artisan route:clear
runuser -u examelite -- php /home/examelite/public_html/artisan view:clear
runuser -u examelite -- php < "$source/verify-workspace.php"
python3 -B "$source/check-workspace-connection.py"
bash "$repo/deploy/virtualmin/update-ui-template.sh"
printf '\nCentral module controls and question sharing installed. Full in-page authoring and exam taking are still under development.\n'
