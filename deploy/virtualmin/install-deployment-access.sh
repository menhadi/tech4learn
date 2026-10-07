#!/bin/bash
# User-run root setup: application-account SSH and a single service restart.
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo 'Run as root.'; exit 1; }
[ "$#" = 1 ] || { echo 'Pass the deployment public key as one quoted argument.'; exit 1; }
key=$1
[[ "$key" =~ ^ssh-ed25519\ [A-Za-z0-9+/]+=*(\ [A-Za-z0-9._-]+)?$ ]] || { echo 'Invalid Ed25519 public key.'; exit 1; }
account=tech4learn
home=$(getent passwd "$account" | cut -d: -f6)
shell=$(getent passwd "$account" | cut -d: -f7)
[ "$home" = /home/tech4learn ] || { echo 'Unexpected or missing application account; stopped.'; exit 1; }
case "$shell" in /bin/bash|/bin/sh|/bin/dash|/usr/bin/bash) ;; *) echo 'Application account needs a login shell; stopped.'; exit 1;; esac
for path in "$home" "$home/.ssh" "$home/.ssh/authorized_keys"; do
  [ ! -L "$path" ] || { echo 'Linked SSH path; stopped.'; exit 1; }
done
install -d -o "$account" -g "$(id -gn "$account")" -m 700 "$home/.ssh"
touch "$home/.ssh/authorized_keys"
entry="restrict $key"
grep -qxF "$entry" "$home/.ssh/authorized_keys" || printf '%s\n' "$entry" >> "$home/.ssh/authorized_keys"
chown "$account:$(id -gn "$account")" "$home/.ssh/authorized_keys"
chmod 600 "$home/.ssh/authorized_keys"
rules=$(mktemp)
trap 'rm -f -- "$rules"' EXIT
printf '%s\n' 'tech4learn ALL=(root) NOPASSWD: /usr/bin/systemctl restart tech4learn' > "$rules"
visudo -cf "$rules"
install -o root -g root -m 440 "$rules" /etc/sudoers.d/tech4learn-deploy
echo 'Deployment access ready: tech4learn SSH; application writes and tech4learn service restart. Review account unchanged.'
