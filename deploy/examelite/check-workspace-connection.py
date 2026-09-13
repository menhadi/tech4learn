#!/usr/bin/env python3
"""Read-only authenticated health check. Credentials never appear in output or argv."""
import json,pathlib,re,urllib.request,urllib.error,sys
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs): return None
config=json.loads(pathlib.Path('/etc/tech4learn/examelite.json').read_text()).get('_platform',{})
token=config.get('token','')
if config.get('enabled') is not True or not re.fullmatch('[a-f0-9]{64}',token):
    raise SystemExit('The central ExamElite connection is not configured.')
if '--configuration-only' in sys.argv:
    print('Central credential configuration is present.')
    raise SystemExit(0)
request=urllib.request.Request('https://examelite.com/api/tech4learn/v1/workspace/status',headers={'Authorization':'Bearer '+token,'Accept':'application/json'})
try:
    with urllib.request.build_opener(NoRedirect()).open(request,timeout=15) as response:
        data=json.loads(response.read(8192))
except (urllib.error.URLError,ValueError):
    raise SystemExit('Native workspace health check failed. Check the ExamElite add-on installation and central credential.') from None
if data.get('version')!=1 or data.get('organization_id')!=config.get('organization_id') or data.get('ready') is not True:
    raise SystemExit('ExamElite did not confirm the native workspace is ready.')
print('Authenticated native ExamElite connection verified.')
