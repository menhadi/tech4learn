#!/usr/bin/env python3
"""Run on the shared host as root. Keys stay in private configuration files."""
import argparse, datetime, hashlib, json, os, pathlib, pwd, secrets, shutil, uuid

p=argparse.ArgumentParser()
p.add_argument('--organisation',required=True,help='Tech4Learn organisation UUID')
p.add_argument('--exam-organisation',required=True,type=int)
p.add_argument('--exams',default='',help='Comma-separated explicitly shared exam IDs')
p.add_argument('--learner',action='append',default=[],help='Tech4Learn learner UUID:ExamElite student ID (repeatable)')
p.add_argument('--disable',action='store_true')
args=p.parse_args()
if os.geteuid()!=0: raise SystemExit('Run as root.')
org=str(uuid.UUID(args.organisation))
if args.exam_organisation<=0: raise SystemExit('Invalid ExamElite organisation.')
examids=list(dict.fromkeys(int(x) for x in args.exams.split(',') if x))
learners={}
for pair in args.learner:
    key,value=pair.split(':',1)
    key=str(uuid.UUID(key))
    if key in learners: raise SystemExit('Duplicate learner mapping.')
    learners[key]=int(value)
if len(examids)>10000 or len(learners)>1000 or any(x<=0 for x in [*examids,*learners.values()]) or len(set(learners.values()))!=len(learners):
    raise SystemExit('Invalid or duplicate mapping.')
ee=pathlib.Path('/etc/examelite/tech4learn-read.json')
t4l=pathlib.Path('/etc/tech4learn/examelite.json')
env=pathlib.Path('/etc/tech4learn/api.env')
if not env.is_file() or env.is_symlink(): raise SystemExit('Expected regular Tech4Learn API environment file is missing.')
def read(path):
    if path.is_symlink(): raise SystemExit('Refusing symlink configuration.')
    value=json.loads(path.read_text()) if path.exists() else {}
    if not isinstance(value,dict): raise SystemExit('Invalid configuration.')
    return value
ec,tc=read(ee),read(t4l)
for key,g in ec.items():
    if key!=org and g.get('organization_id')==args.exam_organisation and g.get('enabled'):
        if set(g.get('learners',{}).values()) & set(learners.values()):
            raise SystemExit('A student is already granted to another Tech4Learn organisation.')
token=secrets.token_hex(32)
ec[org]={'enabled':not args.disable,'organization_id':args.exam_organisation,'token_hash':hashlib.sha256(token.encode()).hexdigest(),'exam_ids':examids,'learners':learners}
tc[org]={'enabled':not args.disable,'organization_id':args.exam_organisation,'token':token}
stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S%f')
backup=pathlib.Path('/root/tech4learn-backups')/('examelite-credentials-'+stamp)
backup.mkdir(parents=True,mode=0o700)
for path in [ee,t4l,env]:
    if path.exists(): shutil.copy2(path,backup/(path.parent.name+'-'+path.name))
def write(path,content,user):
    account=pwd.getpwnam(user)
    path.parent.mkdir(parents=True,exist_ok=True,mode=0o750)
    if path.parent.is_symlink(): raise SystemExit('Refusing symlink directory.')
    if path.parent==ee.parent:
        os.chown(path.parent,0,account.pw_gid);os.chmod(path.parent,0o750)
    temp=path.with_name(path.name+'.'+secrets.token_hex(8)+'.tmp')
    fd=os.open(temp,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'w') as f: f.write(content)
    os.chown(temp,0,account.pw_gid);os.chmod(temp,0o640)
    os.replace(temp,path)
try:
    write(ee,json.dumps(ec)+'\n','examelite')
    write(t4l,json.dumps(tc)+'\n','tech4learn')
    lines=[line for line in env.read_text().splitlines() if not line.startswith('T4L_EXAMELITE_CONFIG=')]
    write(env,'\n'.join(lines)+ '\nT4L_EXAMELITE_CONFIG=/etc/tech4learn/examelite.json\n','tech4learn')
except BaseException:
    for path in [ee,t4l,env]:
        saved=backup/(path.parent.name+'-'+path.name)
        if saved.exists(): shutil.copy2(saved,path)
        elif path.exists(): path.unlink()
    raise
print('Private connector configuration saved; previous files backed up:',backup)
print('Shared exams:',len(examids),'Linked students:',len(learners))
print('Restart Tech4Learn after deployment. No student records were created or changed.')
