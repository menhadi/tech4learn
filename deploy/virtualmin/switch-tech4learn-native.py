#!/usr/bin/env python3
"""One fixed Tech4Learn cutover. User-run root only; no database deletion."""
import datetime
import hashlib
import os
from pathlib import Path
import runpy
import subprocess

REVISION = "551654c4e4feb1708d24f638e2da8068cee430d7"
RELEASE = Path("/home/tech4learn/releases") / REVISION
APACHE = Path("/etc/apache2/sites-available/tech4learn.com.conf")
SOURCE_HASH = "daf10d159e50626ee877b09aa31e23f4c42614005821333e58a4c5066b670696"
RENDERER_HASH = "82187e714bf1a5d1d84a8af0fb6db36ef70a39d5cf8ad2579f7e7e0c754bbc2e"
OVERRIDE = Path("/etc/systemd/system/tech4learn.service.d/native-release.conf")
ENV = {"PATH":"/usr/sbin:/usr/bin:/sbin:/bin", "HOME":"/root", "LANG":"C.UTF-8"}

def run(args, **kwargs):
    return subprocess.run(args, check=True, capture_output=True, timeout=60, env=ENV, **kwargs)

def main():
    if os.geteuid()!=0 or APACHE.is_symlink() or OVERRIDE.exists():
        raise RuntimeError("Cutover precondition failed; no changes.")
    source=APACHE.read_bytes()
    renderer=RELEASE / "deploy/virtualmin/render-native-apache.py"
    if hashlib.sha256(source).hexdigest()!=SOURCE_HASH or hashlib.sha256(renderer.read_bytes()).hexdigest()!=RENDERER_HASH:
        raise RuntimeError("Reviewed configuration/source changed; no changes.")
    run(["/usr/sbin/apache2ctl","configtest"])
    run(["/usr/sbin/php-fpm8.4","-t"])
    run(["/usr/sbin/runuser","-u","tech4learn","--","/usr/bin/php8.4",str(RELEASE / "deploy/virtualmin/check-native-readiness.php"),str(RELEASE / "platform")])
    module=runpy.run_path(str(renderer))
    candidate=module["render"](module["retire_reviewed_legacy_routes"](source.decode()),REVISION)
    paths=["/home/tech4learn","/home/tech4learn/releases",str(RELEASE),str(RELEASE / "platform")]
    acl=run(["/usr/bin/getfacl","-p",*paths]).stdout
    backup=Path("/etc/tech4learn-native") / ("cutover-"+datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%S%fZ"))
    backup.mkdir(mode=0o700)
    (backup / "apache.conf").write_bytes(source)
    (backup / "traversal.acl").write_bytes(acl)
    os.chmod(backup / "apache.conf",0o600)
    os.chmod(backup / "traversal.acl",0o600)
    changed=False
    try:
        run(["/usr/bin/setfacl","-m","u:www-data:--x",*paths])
        run(["/usr/sbin/runuser","-u","www-data","--","/usr/bin/test","-r",str(RELEASE / "platform/public/index.php")])
        OVERRIDE.parent.mkdir(exist_ok=True)
        with OVERRIDE.open("x") as output:
            changed=True
            output.write("[Service]\nWorkingDirectory="+str(RELEASE / "apps/api")+"\nEnvironment=TECH4LEARN_API_MODE=attendance\nEnvironment=PORT=3101\n")
        APACHE.write_text(candidate)
        run(["/usr/sbin/apache2ctl","configtest"])
        run(["/usr/bin/systemctl","daemon-reload"])
        run(["/usr/bin/systemctl","restart","tech4learn.service"])
        # Retry only the bounded health probe while the process starts.
        run(["/usr/bin/curl","--retry","8","--retry-delay","1","--retry-connrefused","--fail","--silent","--max-time","5","-H","Host: tech4learn.com","http://127.0.0.1:3101/api/v1/health"])
        run(["/usr/bin/systemctl","reload","apache2"])
        print("Tech4Learn native routing installed. Private rollback backup: "+str(backup))
        print("Now verify the public administrator login, exams and attendance; no legacy data was deleted.")
    except Exception:
        APACHE.write_bytes(source)
        if changed:
            OVERRIDE.unlink(missing_ok=True)
        run(["/usr/bin/setfacl","--restore="+str(backup / "traversal.acl")])
        run(["/usr/bin/systemctl","daemon-reload"])
        run(["/usr/bin/systemctl","restart","tech4learn.service"])
        run(["/usr/sbin/apache2ctl","configtest"])
        run(["/usr/bin/systemctl","reload","apache2"])
        raise RuntimeError("Cutover failed; previous routing/service restored. Inspect private state.")

if __name__=="__main__":
    try:
        main()
    except Exception as error:
        print(str(error) if isinstance(error,RuntimeError) else "Cutover blocked; root review required. No credentials printed.")
        raise SystemExit(1)
