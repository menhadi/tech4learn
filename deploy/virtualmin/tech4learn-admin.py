#!/usr/bin/python3
"""Fixed privileged operations. No shell, caller-supplied paths, SQL or services."""
import os
import pwd
import secrets
import subprocess
import sys
import stat
from pathlib import Path

ENV = {"PATH": "/usr/sbin:/usr/bin:/sbin:/bin", "HOME": "/root", "LANG": "C.UTF-8"}
PRIVATE = Path('/etc/tech4learn-native')


def run(args, input=None, private_env=None):
    result = subprocess.run(args, input=input, text=True, capture_output=True, env=private_env or ENV, timeout=60)
    if result.returncode:
        # Database errors may contain credentials: never forward tool output.
        raise RuntimeError('Fixed operation failed; a server administrator must inspect it.')
    return result.stdout.strip()


def mysql_access():
    """Use only fixed, root-controlled credential sources; never expose values."""
    def trusted(path):
        try:
            info = path.lstat()
            return stat.S_ISREG(info.st_mode) and info.st_uid == 0 and not (info.st_mode & 0o077)
        except FileNotFoundError:
            return False

    config = Path('/etc/tech4learn-native-mysql.cnf')
    if trusted(config):
        return ['/usr/bin/mysql', '--defaults-file='+str(config), '--protocol=socket', '--user=root'], ENV.copy()
    # Virtualmin/Webmin commonly stores its existing MySQL administrator login
    # here. Read only internally as root; copy no administrator secret to app files.
    webmin = Path('/etc/webmin/mysql/config')
    if trusted(webmin):
        values = {}
        for line in webmin.read_text().splitlines():
            name, separator, value = line.partition('=')
            if separator and name in ('login', 'pass'):
                values[name] = value
        if values.get('login') == 'root' and values.get('pass'):
            env = ENV.copy()
            env['MYSQL_PWD'] = values['pass']
            return ['/usr/bin/mysql', '--no-defaults', '--protocol=socket', '--user=root'], env
    raise RuntimeError('No protected MySQL administrator credentials found. Root setup is required; no changes made.')


def prepare_database():
    # Root-owned parent prevents an application user replacing files/symlinks.
    if PRIVATE.is_symlink():
        raise RuntimeError('Unexpected private directory symlink.')
    if PRIVATE.exists():
        raise RuntimeError('Private setup already exists; refusing to overwrite credentials.')
    mysql, mysql_env = mysql_access()
    # Refuse to adopt or reset an existing database/account.
    existing = run(mysql + ['--batch', '--skip-column-names'],
                   "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='tech4learn_exams';\n"
                   "SELECT COUNT(*) FROM mysql.user WHERE User='tech4learn_exams';\n", private_env=mysql_env)
    if existing.splitlines() != ['0', '0']:
        raise RuntimeError('Dedicated database/account already exists; review required.')
    app = pwd.getpwnam('tech4learn')
    password = secrets.token_hex(32)
    import base64
    key = base64.b64encode(secrets.token_bytes(32)).decode('ascii')
    PRIVATE.mkdir(mode=0o750)
    os.chown(PRIVATE, 0, app.pw_gid)
    text = '\n'.join([
        'APP_NAME=Tech4Learn', 'APP_ENV=production', 'APP_KEY=base64:'+key,
        'APP_DEBUG=false', 'APP_URL=https://tech4learn.com',
        'ATTENDANCE_API_URL=https://tech4learn.com/api/v1', 'DB_CONNECTION=mysql',
        'DB_HOST=127.0.0.1', 'DB_PORT=3306', 'DB_DATABASE=tech4learn_exams',
        'DB_USERNAME=tech4learn_exams', 'DB_PASSWORD='+password, 'CACHE_DRIVER=file',
        'SESSION_DRIVER=file', 'SESSION_COOKIE=__Host-tech4learn_native_session',
        'SESSION_SECURE_COOKIE=true', 'QUEUE_CONNECTION=sync', 'MAIL_MAILER=log',
        'LOG_CHANNEL=single', '',
    ])
    environment = PRIVATE / 'native.env'
    with environment.open('x') as output:
        output.write(text)
    os.chown(environment, 0, app.pw_gid)
    os.chmod(environment, 0o640)
    # Generated hex is safe in these fixed SQL string literals. No user SQL.
    run(mysql,
        "CREATE DATABASE tech4learn_exams CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
        "CREATE USER 'tech4learn_exams'@'127.0.0.1' IDENTIFIED BY '"+password+"';\n"
        "GRANT ALL PRIVILEGES ON tech4learn_exams.* TO 'tech4learn_exams'@'127.0.0.1';\n", private_env=mysql_env)
    print('Dedicated exam database/account and private environment prepared. No migrations, records or routing changed.')


def main():
    if os.geteuid() != 0 or len(sys.argv) != 2:
        raise RuntimeError('One fixed action, executed through sudo, is required.')
    action = sys.argv[1]
    if action == 'prepare-database':
        prepare_database()
    elif action == 'stop-api':
        run(['/usr/bin/systemctl', 'stop', 'tech4learn.service'])
        print('Tech4Learn API stopped for the reviewed maintenance window.')
    elif action == 'restart-api':
        run(['/usr/bin/systemctl', 'restart', 'tech4learn.service'])
        print('Tech4Learn API restarted.')
    elif action == 'status':
        print('API: '+run(['/usr/bin/systemctl', 'is-active', 'tech4learn.service']))
        print('Native PHP socket: '+('present' if Path('/run/php/tech4learn-native.sock').exists() else 'missing'))
        print('Private native environment: '+('present' if (PRIVATE/'native.env').is_file() else 'missing'))
    else:
        raise RuntimeError('Action not allowed.')


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        # Only explicitly authored RuntimeError messages are safe to print.
        print(str(error) if isinstance(error, RuntimeError) else 'Operation failed; server review required.', file=sys.stderr)
        sys.exit(1)
