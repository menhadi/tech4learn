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
request=urllib.request.Request('https://examelite.com/api/tech4learn/v1/workspace/status',headers={'Authorization':'Bearer '+token,'Accept':'application/json','User-Agent':'Tech4Learn/1.0'})
try:
    with urllib.request.build_opener(NoRedirect()).open(request,timeout=15) as response:
        data=json.loads(response.read(8192))
except urllib.error.HTTPError as error:
    hints={401:'The central credential was rejected.',403:'Check central organisation mapping and edge access rules.',404:'The workspace health route was not found.',429:'The endpoint is rate limited; wait before retrying.'}
    raise SystemExit('Native workspace health check failed: HTTP '+str(error.code)+'. '+hints.get(error.code,'Check ExamElite server logs.')) from None
except urllib.error.URLError:
    raise SystemExit('Native workspace health check could not connect. Check DNS, TLS and network access.') from None
except ValueError:
    raise SystemExit('Native workspace health check returned invalid JSON.') from None
if data.get('version')!=1 or data.get('organization_id')!=config.get('organization_id') or data.get('ready') is not True:
    raise SystemExit('ExamElite did not confirm the native workspace is ready.')
print('Authenticated native ExamElite connection verified.')
