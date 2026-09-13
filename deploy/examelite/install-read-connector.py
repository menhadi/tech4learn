#!/usr/bin/env python3
"""User-run, additive ExamElite route installation; never loads Laravel or secrets."""
import os, pathlib, pwd, shutil, subprocess, datetime

if os.geteuid() != 0:
    raise SystemExit('Run as root.')
root = pathlib.Path('/home/examelite/public_html')
source = pathlib.Path(__file__).resolve().parent
account = pwd.getpwnam('examelite')
route = root / 'routes/api.php'
marker = "require __DIR__.'/tech4learn-routes.php';"
if not route.is_file() or route.is_symlink():
    raise SystemExit('Expected ExamElite routes/api.php was not found.')
backup = pathlib.Path('/root/tech4learn-backups') / ('examelite-connector-' + datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S%f'))
backup.mkdir(parents=True, mode=0o700)
targets = [(source/'Tech4LearnReadController.php',root/'app/Http/Controllers/Tech4LearnReadController.php'),(source/'tech4learn-routes.php',root/'routes/tech4learn-routes.php')]
for src, dest in targets:
    if dest.is_symlink(): raise SystemExit('Refusing symlink target.')
    subprocess.run(['php','-l',str(src)],check=True)
    if dest.exists(): shutil.copy2(dest,backup/dest.name)
shutil.copy2(route,backup/'api.php')
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
except BaseException:
    route.write_bytes(original)
    for src,dest in targets:
        saved=backup/dest.name
        if saved.exists(): shutil.copyfile(saved,dest)
        elif dest.exists(): dest.unlink()
    raise
print('Connector source installed. Backup:',backup)
print('Next: configure explicit grants, clear the ExamElite route cache as its owner, and deploy Tech4Learn.')
