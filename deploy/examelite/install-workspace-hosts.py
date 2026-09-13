#!/usr/bin/env python3
"""User-run dedicated vhost installer. Existing ExamElite vhosts/certificates stay intact."""
import datetime,os,pathlib,re,subprocess,sys
from workspace_install import hosting_config
if os.geteuid()!=0: raise SystemExit('Run as root.')
certroot='/etc/letsencrypt/live/examelite-workspaces'
cert=pathlib.Path(certroot)/'fullchain.pem';key=pathlib.Path(certroot)/'privkey.pem'
if not cert.is_file() or not key.is_file():
    raise SystemExit("Obtain the workspace certificate first: certbot certonly --manual --preferred-challenges dns --cert-name examelite-workspaces -d '*.examelite.com'")
details=subprocess.check_output(['openssl','x509','-in',str(cert),'-noout','-ext','subjectAltName'],text=True)
if not re.search(r'DNS:\*\.examelite\.com(?:,|\s|$)',details): raise SystemExit('Certificate must cover *.examelite.com.')
subprocess.run(['openssl','x509','-in',str(cert),'-checkend','86400','-noout'],check=True)
certpub=subprocess.check_output(['openssl','x509','-in',str(cert),'-pubkey','-noout'])
keypub=subprocess.check_output(['openssl','pkey','-in',str(key),'-pubout'],stderr=subprocess.DEVNULL)
if certpub!=keypub: raise SystemExit('Certificate and key do not match.')
sockets=set()
for p in pathlib.Path('/etc/apache2/sites-enabled').glob('*.conf'):
    if p.name=='tech4learn-examelite-workspaces.conf' or not p.is_file(): continue
    text=p.read_text()
    for block in re.findall(r'<VirtualHost\b[^>]*>(.*?)</VirtualHost>',text,re.S|re.I):
        if re.search(r'^\s*ServerName\s+examelite\.com\s*$',block,re.M|re.I):
            sockets.update(re.findall(r'proxy:unix:(/run/php/[a-zA-Z0-9_.-]+\.sock)\|',block))
if len(sockets)!=1: raise SystemExit('Cannot identify one ExamElite PHP socket; no vhost changed.')
socket=next(iter(sockets))
if not pathlib.Path(socket).exists(): raise SystemExit('ExamElite PHP socket is unavailable.')
if '--preflight' in sys.argv:
    print('Wildcard certificate, matching private key and existing PHP socket verified.')
    raise SystemExit(0)
dest=pathlib.Path('/etc/apache2/sites-available/tech4learn-examelite-workspaces.conf')
link=pathlib.Path('/etc/apache2/sites-enabled/tech4learn-examelite-workspaces.conf')
if dest.is_symlink() or (link.is_symlink() and link.resolve()!=dest) or (link.exists() and not link.is_symlink()):
    raise SystemExit('Unexpected existing workspace vhost target.')
original=dest.read_bytes() if dest.exists() else None
had_link=link.is_symlink()
backup=pathlib.Path('/root/tech4learn-backups')/('workspace-hosting-'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S%f'))
backup.mkdir(parents=True,mode=0o700)
if original is not None:(backup/dest.name).write_bytes(original)
try:
    dest.write_text(hosting_config(socket,certroot));os.chmod(dest,0o644)
    if not had_link:link.symlink_to(dest)
    subprocess.run(['apache2ctl','configtest'],check=True)
    subprocess.run(['systemctl','reload','apache2'],check=True)
except BaseException:
    if original is None:dest.unlink(missing_ok=True)
    else:dest.write_bytes(original)
    if not had_link:link.unlink(missing_ok=True)
    subprocess.run(['apache2ctl','configtest'],check=False)
    subprocess.run(['systemctl','reload','apache2'],check=False)
    raise
print('Workspace hosts configured. Existing main-domain certificates were not replaced. Backup:',backup)
