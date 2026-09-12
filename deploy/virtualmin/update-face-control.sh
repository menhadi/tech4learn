#!/usr/bin/env bash
set -euo pipefail
umask 077
test "$(id -u)" = 0
repo=/home/tech4learn/tech4learn-app
runtime=/opt/tech4learn/node-v24.19.0/bin
test -z "$(runuser -u tech4learn -- git -C "$repo" status --porcelain)"
mkdir -p /root/tech4learn-backups
backup="/root/tech4learn-backups/before-face-control-$(date -u +%Y%m%dT%H%M%S).dump"
cd /tmp
runuser -u postgres -- pg_dump --port=5432 --format=custom tech4learn_app > "$backup"
test -s "$backup"
pg_restore --list "$backup" >/dev/null
printf 'Private backup: %s\n' "$backup"
systemctl stop tech4learn
cd "$repo"
runuser -u tech4learn -- env PATH="$runtime:/usr/bin:/bin" npm ci --workspace @tech4learn/api --workspace @tech4learn/admin --workspace @tech4learn/contracts --include-workspace-root --include=dev
runuser -u tech4learn -- env PATH="$runtime:/usr/bin:/bin" VITE_API_URL=/api/v1 npm run build
runuser -u tech4learn -- "$runtime/node" --env-file=/etc/tech4learn/api.env "$repo/apps/api/dist/manage.js" migrate
bash "$repo/deploy/virtualmin/install-face-control.sh"
systemctl start tech4learn
curl --fail --show-error --retry 8 --retry-connrefused --retry-delay 2 --max-time 15 http://127.0.0.1:3101/api/v1/health
curl --fail --show-error --retry 5 --retry-delay 2 --max-time 15 https://tech4learn.com/api/v1/health
printf '\nFace-engine controls deployed. Sign in as superadmin and open Face engine.\n'
