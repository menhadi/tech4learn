#!/bin/bash
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "Run as root."; exit 1; }
account=t4l-review
key='ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIBmaZynjhzaddUYfbGfudO/Bn+hLEi8ZJxzuMbyrQj+d tech4learn-readonly-review'
here=$(cd -- "$(dirname -- "$0")" && pwd)
if ! id "$account" >/dev/null 2>&1; then
  useradd --system --create-home --home-dir /var/lib/t4l-review --shell /bin/sh "$account"
fi
[ "$(getent passwd "$account" | cut -d: -f6)" = /var/lib/t4l-review ] || { echo "Unexpected existing account home; stopped."; exit 1; }
install -o root -g root -m 755 "$here/review-access.py" /usr/local/sbin/t4l-read-review
# Root owns the forced command and key; this account cannot replace either.
install -d -o root -g root -m 755 /var/lib/t4l-review /var/lib/t4l-review/.ssh
# Use a root wrapper instead of allowing env or Python arguments through sudo.
cat > /usr/local/sbin/t4l-review-entry <<'WRAPPER'
#!/bin/sh
exec /usr/bin/python3 -I /usr/local/sbin/t4l-read-review
WRAPPER
chown root:root /usr/local/sbin/t4l-review-entry
chmod 755 /usr/local/sbin/t4l-review-entry
printf 'restrict,command="sudo -n /usr/local/sbin/t4l-review-entry" %s\n' "$key" > /var/lib/t4l-review/.ssh/authorized_keys
chmod 644 /var/lib/t4l-review/.ssh/authorized_keys
chown root:root /var/lib/t4l-review/.ssh/authorized_keys
printf 'Defaults:t4l-review env_keep += "SSH_ORIGINAL_COMMAND"\nt4l-review ALL=(root) NOPASSWD: /usr/local/sbin/t4l-review-entry ""\n' > /etc/sudoers.d/t4l-review
chmod 440 /etc/sudoers.d/t4l-review
visudo -cf /etc/sudoers.d/t4l-review
printf 'Read-only SSH account ready: t4l-review. Commands: status, files, read <source path>.\n'
