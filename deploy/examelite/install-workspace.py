#!/usr/bin/env python3
"""Run explicitly as root on the server; no migrations or service restarts here."""
import os, pathlib, pwd, shutil, subprocess, datetime
from workspace_install import add_provider, add_navigation

if os.geteuid()!=0: raise SystemExit('Run as root.')
root=pathlib.Path('/home/examelite/public_html')
source=pathlib.Path(__file__).resolve().parent
owner=pwd.getpwnam('examelite')
targets={
 'Tech4LearnAuthoringController.php':'app/Http/Controllers/Tech4LearnAuthoringController.php',
 'Tech4LearnQuestionAuthoring.php':'app/Services/Tech4LearnQuestionAuthoring.php',
 'Tech4LearnContentController.php':'app/Http/Controllers/Tech4LearnContentController.php',
 'Tech4LearnWorkspaceController.php':'app/Http/Controllers/Tech4LearnWorkspaceController.php',
 'Tech4LearnNativeController.php':'app/Http/Controllers/Tech4LearnNativeController.php',
 'Tech4LearnLibraryController.php':'app/Http/Controllers/Tech4LearnLibraryController.php',
 'Tech4LearnContentCopies.php':'app/Services/Tech4LearnContentCopies.php',
 'Tech4LearnLaunchTickets.php':'app/Services/Tech4LearnLaunchTickets.php',
 'Tech4LearnWorkspacePolicy.php':'app/Support/Tech4LearnWorkspacePolicy.php',
 'Tech4LearnWorkspaceContext.php':'app/Http/Middleware/Tech4LearnWorkspaceContext.php',
 'Tech4LearnWorkspaceGate.php':'app/Http/Middleware/Tech4LearnWorkspaceGate.php',
 'Tech4LearnWorkspaceProvider.php':'app/Providers/Tech4LearnWorkspaceProvider.php',
 'tech4learn-workspace.php':'routes/tech4learn-workspace.php',
 'launch.blade.php':'resources/views/tech4learn/launch.blade.php',
 'library.blade.php':'resources/views/tech4learn/library.blade.php',
 'navigation.blade.php':'resources/views/tech4learn/navigation.blade.php',
}
for src,dest in targets.items():
    if (root/dest).is_symlink(): raise SystemExit('Refusing symlink target: '+dest)
    subprocess.run(['php','-l',str(source/src)],check=True)
app=root/'config/app.php';layout=root/'resources/views/layouts/master.blade.php'
for p in (app,layout):
    if not p.is_file() or p.is_symlink(): raise SystemExit('Expected regular source: '+str(p))
updates={app:add_provider(app.read_text()),layout:add_navigation(layout.read_text())}
backup=pathlib.Path('/root/tech4learn-backups')/('native-workspace-'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S%f'))
backup.mkdir(parents=True,mode=0o700)
originals={}
for dest in [root/d for d in targets.values()]+list(updates):
    originals[dest]=dest.read_bytes() if dest.exists() else None
    if dest.exists():
        saved=backup/dest.relative_to(root);saved.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(dest,saved)
try:
    for src,relative in targets.items():
        dest=root/relative
        if not dest.parent.exists():
            dest.parent.mkdir(parents=True,exist_ok=True)
            os.chown(dest.parent,owner.pw_uid,owner.pw_gid);os.chmod(dest.parent,0o755)
        shutil.copyfile(source/src,dest);os.chown(dest,owner.pw_uid,owner.pw_gid);os.chmod(dest,0o644)
    for dest,content in updates.items(): dest.write_text(content)
    subprocess.run(['php','-l',str(app)],check=True)
except BaseException:
    for dest,content in originals.items():
        if content is None: dest.unlink(missing_ok=True)
        else: dest.write_bytes(content)
    raise
print('Native workspace source installed. Backup:',backup)
print('Apply the explicit workspace migration, then clear Laravel caches as examelite.')
