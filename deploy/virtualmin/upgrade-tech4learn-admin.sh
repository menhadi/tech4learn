#!/bin/bash
# Root-run, reviewed helper upgrade only. Does not execute any deployment action.
set -euo pipefail
test "$(id -u)" = 0 || { echo 'Run the checked upgrade from the root terminal.' >&2; exit 1; }
test "$#" = 0
here=$(cd -- "$(dirname -- "$0")" && pwd)
test "$(stat -c '%u:%a' "$here")" = '0:700'
for source in "$here/tech4learn-admin.py" "$here/tech4learn-native-admin-maintenance.sudoers"; do
    test ! -L "$source"
    test "$(stat -c '%u:%a' "$source")" = '0:600'
done
helper=/usr/local/sbin/tech4learn-admin.py
wrapper=/usr/local/sbin/tech4learn-admin
policy=/etc/sudoers.d/tech4learn-native-admin
for target in "$helper" "$wrapper" "$policy"; do
    test -f "$target" && test ! -L "$target"
    test "$(stat -c '%u' "$target")" = 0
done
test "$(stat -c '%a' "$helper")" = 755
test "$(stat -c '%a' "$wrapper")" = 755
test "$(stat -c '%a' "$policy")" = 440
expected='tech4learn ALL=(root) NOPASSWD: /usr/local/sbin/tech4learn-admin status, /usr/local/sbin/tech4learn-admin prepare-database, /usr/local/sbin/tech4learn-admin restart-api'
test "$(cat "$policy")" = "$expected"
test "$(cat "$here/tech4learn-native-admin-maintenance.sudoers")" = "$expected, /usr/local/sbin/tech4learn-admin stop-api"
/usr/bin/python3 -I -c 'import ast,sys; ast.parse(open(sys.argv[1]).read())' "$here/tech4learn-admin.py"
/usr/sbin/visudo -cf "$here/tech4learn-native-admin-maintenance.sudoers"
umask 077
backup=$(mktemp -d /etc/tech4learn-admin-upgrade-XXXXXXXX)
cp -p -- "$helper" "$backup/helper.py"
cp -p -- "$policy" "$backup/sudoers"
chmod 600 "$backup/helper.py" "$backup/sudoers"
rollback() {
    trap - ERR
    install -o root -g root -m 755 "$backup/helper.py" "$helper"
    install -o root -g root -m 440 "$backup/sudoers" "$policy"
    echo 'Helper upgrade failed; previous helper and sudo rule restored.' >&2
}
trap rollback ERR
install -o root -g root -m 755 "$here/tech4learn-admin.py" "$helper"
install -o root -g root -m 440 "$here/tech4learn-native-admin-maintenance.sudoers" "$policy"
/usr/sbin/visudo -cf "$policy"
trap - ERR
echo 'Tech4Learn-only maintenance helper installed; no service or website action executed.'
echo "Private helper rollback backup: $backup"
