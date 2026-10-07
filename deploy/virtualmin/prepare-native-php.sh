#!/usr/bin/env bash
# User-run root preparation only: one private PHP pool; no website cutover or DB edits.
set -euo pipefail
umask 077
test "$(id -u)" = 0 || { echo 'Run this preparation from the server root terminal.' >&2; exit 1; }
test "$#" = 1 && [[ "$1" =~ ^[a-f0-9]{40}$ ]] || { echo 'Pass one checked full Git revision.' >&2; exit 1; }
release=/home/tech4learn/releases/$1
test -d "$release" && test ! -L "$release"
test "$(runuser -u tech4learn -- git -C "$release" rev-parse HEAD)" = "$1"
test "$(stat -c %U "$release")" = tech4learn
test -f "$release/platform/composer.lock"
test -f "$release/platform/public/attendance-ui/manifest.json"
test -x /usr/sbin/php-fpm8.4
systemctl is-active --quiet php8.4-fpm
/usr/bin/php8.4 -r 'foreach(["pdo_mysql","mbstring","dom","fileinfo","curl","gd","zip"] as $extension)if(!extension_loaded($extension)){fwrite(STDERR,"Missing PHP extension: ".$extension."\n");exit(1);}'
pool=/etc/php/8.4/fpm/pool.d/tech4learn-native.conf
{ test ! -e "$pool" && test ! -L "$pool"; } || { echo 'Native pool already exists; inspect it instead of overwriting.' >&2; exit 1; }
shared=/home/tech4learn/native-shared
test ! -L "$shared"
test ! -L "$shared/logs"
install -d -o tech4learn -g tech4learn -m 700 "$shared" "$shared/logs"
temporary=$(mktemp)
installed=false
cleanup() {
  result=$?
  rm -f -- "$temporary"
  if test "$result" -ne 0 && test "$installed" = true; then
    rm -f -- /etc/php/8.4/fpm/pool.d/tech4learn-native.conf
    systemctl reload php8.4-fpm || true
    echo 'Preparation failed; removed only the new Tech4Learn pool.' >&2
  fi
  exit "$result"
}
trap cleanup EXIT
cat > "$temporary" <<'POOL'
[tech4learn-native]
user = tech4learn
group = tech4learn
listen = /run/php/tech4learn-native.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = ondemand
pm.max_children = 6
pm.process_idle_timeout = 20s
pm.max_requests = 500
clear_env = yes
security.limit_extensions = .php
request_terminate_timeout = 180s
php_admin_flag[display_errors] = off
php_admin_flag[log_errors] = on
php_admin_value[error_log] = /home/tech4learn/native-shared/logs/php-error.log
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 64M
php_admin_value[post_max_size] = 80M
POOL
install -o root -g root -m 644 "$temporary" "$pool"
installed=true
/usr/sbin/php-fpm8.4 -t
systemctl reload php8.4-fpm
for attempt in $(seq 1 10); do test -S /run/php/tech4learn-native.sock && break; sleep 1; done
test -S /run/php/tech4learn-native.sock
test "$(stat -c '%U:%G:%a' /run/php/tech4learn-native.sock)" = www-data:www-data:660
installed=false
printf 'Private PHP 8.4 pool prepared. Website routing, databases, credentials, identities and original ExamElite files are unchanged.\n'
