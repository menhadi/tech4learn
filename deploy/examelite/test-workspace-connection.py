"""Exercise the deployed checker without network access or real credentials."""
import io,json,pathlib,runpy,unittest,urllib.error
from unittest.mock import patch

SCRIPT=pathlib.Path(__file__).with_name('check-workspace-connection.py')
TOKEN='a'*64
class Response(io.BytesIO):
    pass
class ConnectionTests(unittest.TestCase):
    def run_check(self, data=None, error=None):
        seen=[]
        class Opener:
            def open(self,request,timeout):
                seen.append(request)
                if error: raise error
                return Response(json.dumps(data).encode())
        config={'_platform':{'enabled':True,'token':TOKEN,'organization_id':1}}
        with patch('pathlib.Path.read_text',return_value=json.dumps(config)),patch('urllib.request.build_opener',return_value=Opener()),patch('sys.argv',[str(SCRIPT)]),patch('sys.stdout',new_callable=io.StringIO):
            runpy.run_path(str(SCRIPT),run_name='__main__')
        return seen[0]
    def test_identifies_application_and_authenticates(self):
        request=self.run_check({'version':1,'organization_id':1,'ready':True})
        self.assertEqual(request.get_header('User-agent'),'Tech4Learn/1.0')
        self.assertEqual(request.get_header('Authorization'),'Bearer '+TOKEN)
        self.assertEqual(request.full_url,'https://examelite.com/api/tech4learn/v1/workspace/status')
    def test_http_failure_is_actionable_without_response_or_token(self):
        error=urllib.error.HTTPError('https://examelite.com/',403,'blocked',{},io.BytesIO(TOKEN.encode()))
        with self.assertRaises(SystemExit) as caught:self.run_check(error=error)
        self.assertIn('HTTP 403',str(caught.exception))
        self.assertNotIn(TOKEN,str(caught.exception))
    def test_foreign_tenant_or_unready_provider_fails(self):
        for data in [{'version':1,'organization_id':2,'ready':True},{'version':1,'organization_id':1,'ready':False}]:
            with self.assertRaises(SystemExit):self.run_check(data)

if __name__=='__main__':unittest.main()
