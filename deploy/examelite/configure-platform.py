#!/usr/bin/env python3
"""Explicit user-run central credential setup; preserve reviewed pilot mappings."""
import argparse, datetime, hashlib, json, os, pathlib, pwd, secrets, shutil
p=argparse.ArgumentParser()
p.add_argument('--exam-organisation',type=int,required=True)
args=p.parse_args()
if os.geteuid()!=0 or args.exam_organisation<=0: raise SystemExit('Run as root with a positive ExamElite organisation ID.')
ee=pathlib.Path('/etc/examelite/tech4learn-read.json')
t4l=pathlib.Path('/etc/tech4learn/examelite.json')
env=pathlib.Path('/etc/tech4learn/api.env')
for path in [ee,t4l,env]:
    if not path.is_file() or path.is_symlink() or path.parent.is_symlink():
        raise SystemExit('Existing regular connector configuration is required. Run the original connector setup first.')
ec,tc=json.loads(ee.read_text()),json.loads(t4l.read_text())
if not isinstance(ec,dict) or not isinstance(tc,dict): raise SystemExit('Invalid connector configuration.')
for conf in [ec,tc]:
    if '_platform' in conf and conf['_platform'].get('organization_id')!=args.exam_organisation:
        raise SystemExit('Changing the central ExamElite organisation requires a reviewed identity migration.')
token=secrets.token_hex(32)
ec['_platform']={'enabled':True,'organization_id':args.exam_organisation,'token_hash':hashlib.sha256(token.encode()).hexdigest()}
tc['_platform']={'enabled':True,'organization_id':args.exam_organisation,'token':token}
backup=pathlib.Path('/root/tech4learn-backups')/('examelite-platform-'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S%f'))
backup.mkdir(parents=True,mode=0o700)
for path in [ee,t4l,env]: shutil.copy2(path,backup/(path.parent.name+'-'+path.name))
def write(path,content,group):
    temp=path.with_name(path.name+'.'+secrets.token_hex(8)+'.tmp')
    fd=os.open(temp,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'w') as f: f.write(content)
    os.chown(temp,0,pwd.getpwnam(group).pw_gid);os.chmod(temp,0o640)
    os.replace(temp,path)
try:
    write(ee,json.dumps(ec)+'\n','examelite')
    write(t4l,json.dumps(tc)+'\n','tech4learn')
    lines=[line for line in env.read_text().splitlines() if not line.startswith('T4L_EXAMELITE_CONFIG=')]
    write(env,'\n'.join(lines)+'\nT4L_EXAMELITE_CONFIG=/etc/tech4learn/examelite.json\n','tech4learn')
except BaseException:
    for path in [ee,t4l,env]: shutil.copy2(backup/(path.parent.name+'-'+path.name),path)
    raise
print('Central credential configured. Private backup:',backup)
print('Restart Tech4Learn, then use System & account > ExamElite connection to assign organisation access.')
