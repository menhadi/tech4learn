#!/usr/bin/env python3
"""User-run, additive ExamElite route installation; never loads Laravel or secrets."""
import os, pathlib, pwd, shutil, subprocess, datetime
from connector_rewrite import add_connector_rewrite

if os.geteuid() != 0:
    raise SystemExit('Run as root.')
root = pathlib.Path('/home/examelite/public_html')
source = pathlib.Path(__file__).resolve().parent
account = pwd.getpwnam('examelite')
route = root / 'routes/api.php'
htaccess = root / '.htaccess'
marker = "require __DIR__.'/tech4learn-routes.php';"
if not route.is_file() or route.is_symlink():
    raise SystemExit('Expected ExamElite routes/api.php was not found.')
if not htaccess.is_file() or htaccess.is_symlink():
    raise SystemExit('Expected regular ExamElite .htaccess was not found.')
rewrite_original = htaccess.read_bytes()
rewrite_updated = add_connector_rewrite(rewrite_original.decode('utf-8')).encode('utf-8')
backup = pathlib.Path('/root/tech4learn-backups') / ('examelite-connector-' + datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S%f'))
backup.mkdir(parents=True, mode=0o700)
targets = [(source/'Tech4LearnReadController.php',root/'app/Http/Controllers/Tech4LearnReadController.php'),(source/'tech4learn-routes.php',root/'routes/tech4learn-routes.php')]
targets.append((source/'Tech4LearnPlatformController.php',root/'app/Http/Controllers/Tech4LearnPlatformController.php'))
for src, dest in targets:
    if dest.is_symlink(): raise SystemExit('Refusing symlink target.')
    subprocess.run(['php','-l',str(src)],check=True)
    if dest.exists(): shutil.copy2(dest,backup/dest.name)
shutil.copy2(route,backup/'api.php')
shutil.copy2(htaccess,backup/'root.htaccess')
original = route.read_bytes()
try:
    for src,dest in targets:
        shutil.copyfile(src,dest)
        os.chown(dest,account.pw_uid,account.pw_gid)
        os.chmod(dest,0o644)
    content=original.decode('utf-8')
    if marker not in content:
        # PHP routes may end with a closing tag; append inside PHP in either case.
        suffix='\n'+(' <?php\n' if content.rstrip().endswith('?>') else '')+marker+'\n'
        route.write_bytes(original+suffix.encode('utf-8'))
    subprocess.run(['php','-l',str(route)],check=True)
    htaccess.write_bytes(rewrite_updated)
except BaseException:
    route.write_bytes(original)
    htaccess.write_bytes(rewrite_original)
    for src,dest in targets:
        saved=backup/dest.name
        if saved.exists(): shutil.copyfile(saved,dest)
        elif dest.exists(): dest.unlink()
    raise
print('Connector source installed. Backup:',backup)
print('Next: configure explicit grants, clear the ExamElite route cache as its owner, and deploy Tech4Learn.')
