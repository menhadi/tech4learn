#!/bin/bash
set -euo pipefail
test "$(id -u)" = 0 || { echo 'Run from the root terminal.' >&2; exit 1; }
test "$#" = 0
here=$(cd -- "$(dirname -- "$0")" && pwd)
# This installer and helper must first be copied and checksum-verified in a
# root-only temporary directory, as shown in the installation command.
test "$(stat -c '%u:%a' "$here")" = '0:700'
test ! -e /usr/local/sbin/tech4learn-admin
test ! -L /usr/local/sbin/tech4learn-admin
test ! -e /etc/sudoers.d/tech4learn-native-admin
test ! -L /etc/sudoers.d/tech4learn-native-admin
/usr/bin/python3 -I -c 'import ast,sys; ast.parse(open(sys.argv[1]).read())' "$here/tech4learn-admin.py"
rule=$(mktemp)
trap 'rm -f -- "$rule"' EXIT
cat > "$rule" <<'RULE'
tech4learn ALL=(root) NOPASSWD: /usr/local/sbin/tech4learn-admin status, /usr/local/sbin/tech4learn-admin prepare-database, /usr/local/sbin/tech4learn-admin restart-api
RULE
/usr/sbin/visudo -cf "$rule"
# -I ignores user Python modules, environment and the current working directory.
install -o root -g root -m 755 "$here/tech4learn-admin.py" /usr/local/sbin/tech4learn-admin.py
printf '#!/bin/sh\nexec /usr/bin/python3 -I /usr/local/sbin/tech4learn-admin.py "$@"\n' > /usr/local/sbin/tech4learn-admin
chown root:root /usr/local/sbin/tech4learn-admin
chmod 755 /usr/local/sbin/tech4learn-admin
install -o root -g root -m 440 "$rule" /etc/sudoers.d/tech4learn-native-admin
echo 'Tech4Learn-only database preparation, status and API restart permissions installed.'
