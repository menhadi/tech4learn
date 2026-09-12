#!/usr/bin/env bash
set -euo pipefail
test "$(id -u)" = 0
repo=/home/tech4learn/tech4learn-app
test -z "$(runuser -u tech4learn -- git -C "$repo" status --porcelain)"
test -x /usr/bin/docker
test -x /usr/bin/python3
docker inspect tech4learn-face >/dev/null
install -d -o root -g root -m 0755 /usr/local/lib/tech4learn-face-control
install -o root -g root -m 0644 "$repo/deploy/virtualmin/face-control.py" /usr/local/lib/tech4learn-face-control/controller.py
install -o root -g root -m 0644 "$repo/deploy/virtualmin/tech4learn-face-control.service" /etc/systemd/system/tech4learn-face-control.service
systemctl daemon-reload
systemctl enable tech4learn-face-control
systemctl restart tech4learn-face-control
runuser -u tech4learn -- curl --fail --show-error --retry 5 --retry-connrefused --retry-delay 1 --max-time 30 \
  --unix-socket /run/tech4learn-face-control/control.sock \
  -H 'Content-Type: application/json' --data '{"action":"status"}' http://localhost/
printf '\nFace controller installed. Deploy the application and migration 10 before using its page.\n'
