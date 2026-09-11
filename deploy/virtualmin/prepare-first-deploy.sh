#!/usr/bin/env bash
# Run manually as root on the confirmed Ubuntu/Virtualmin server only.
# Does not alter Apache, DNS, system Node, existing websites, or databases.
set -euo pipefail
umask 022

fail() { printf 'STOP: %s\n' "$*" >&2; exit 1; }
[[ $EUID -eq 0 ]] || fail 'Run from the root terminal.'
repo=/home/tech4learn/tech4learn-app
runtime=/opt/tech4learn/node-v24.19.0
unit=/etc/systemd/system/tech4learn.service
[[ $(realpath "$(dirname "$0")/../..") == "$repo" ]] || fail "Clone the repository at $repo first."
for tool in curl tar xz sha256sum runuser git systemctl ss; do
  command -v "$tool" >/dev/null || fail "Required command missing: $tool"
done
id tech4learn >/dev/null
[[ -z $(git -C "$repo" -c safe.directory="$repo" status --porcelain) ]] || fail 'Checkout has changes; preserve them and use a clean checkout.'
[[ ! -e "$unit" ]] || fail 'Service already exists; use the update procedure, not first-install.'
[[ ! -e /etc/tech4learn/api.env ]] || fail 'Environment file already exists; review before retrying.'
[[ -z $(ss -H -ltn 'sport = :3101') ]] || fail 'Port 3101 is already in use; do not stop its owner.'

case $(uname -m) in
  x86_64) architecture=x64; checksum=14b342e71204f811bde6153be8e04b62aef63c236fef92b55f9c83154b409647 ;;
  aarch64) architecture=arm64; checksum=01443c1e1a29e531ccad5a46fefa6df490d2189c49f7955904aecdbb0fe86fdc ;;
  *) fail 'Only Linux x64 and arm64 are supported by this installer.' ;;
esac

if [[ ! -e "$runtime" ]]; then
  install -d -m 755 /opt/tech4learn
  temporary=$(mktemp -d /opt/tech4learn/install.XXXXXX)
  archive="node-v24.19.0-linux-$architecture.tar.xz"
  curl --fail --show-error --location --proto '=https' --tlsv1.2 \
    "https://nodejs.org/dist/v24.19.0/$archive" -o "$temporary/$archive"
  printf '%s  %s\n' "$checksum" "$temporary/$archive" | sha256sum --check -
  tar --extract --xz --file "$temporary/$archive" --directory "$temporary" --no-same-owner
  mv "$temporary/node-v24.19.0-linux-$architecture" "$runtime"
  # Retain the verified archive for review; no recursive removal.
fi
[[ $("$runtime/bin/node" --version) == v24.19.0 ]] || fail 'Unexpected Node version at dedicated runtime path.'

printf '\nBuilding as tech4learn (not root)…\n'
runuser -u tech4learn -- bash -c '
  set -euo pipefail
  cd /home/tech4learn/tech4learn-app
  export PATH=/opt/tech4learn/node-v24.19.0/bin:$PATH
  npm ci --include=dev --workspace @tech4learn/api --workspace @tech4learn/admin --workspace @tech4learn/contracts --include-workspace-root
  VITE_API_URL=/api/v1 npm run build
  npm test
'

install -d -m 750 -o root -g tech4learn /etc/tech4learn
cat > /etc/tech4learn/api.env <<'ENV'
NODE_ENV=production
HOST=127.0.0.1
PORT=3101
ADMIN_ORIGIN=https://tech4learn.com
ADMIN_DIST_PATH=/home/tech4learn/tech4learn-app/apps/admin/dist
ENV
chown root:tech4learn /etc/tech4learn/api.env
chmod 640 /etc/tech4learn/api.env
install -m 644 "$repo/deploy/virtualmin/tech4learn.service" "$unit"
systemctl daemon-reload
systemctl enable --now tech4learn.service

healthy=false
for attempt in {1..15}; do
  if curl --fail --silent http://127.0.0.1:3101/api/v1/health; then
    healthy=true
    break
  fi
  sleep 1
done
[[ "$healthy" == true ]] || fail 'Service health check failed. Inspect: journalctl -u tech4learn -n 40 --no-pager'
curl --fail --silent --show-error --output /dev/null http://127.0.0.1:3101/
printf '\nREADY: local service is running. Apache and the public website are unchanged.\n'
printf 'Deployed source: '
git -C "$repo" -c safe.directory="$repo" rev-parse HEAD
printf 'Next: follow docs/virtualmin-first-deployment.md to connect the domain.\n'
