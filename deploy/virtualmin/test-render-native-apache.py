import importlib.util
from pathlib import Path
import unittest

spec=importlib.util.spec_from_file_location('renderer',Path(__file__).with_name('render-native-apache.py'))
renderer=importlib.util.module_from_spec(spec)
spec.loader.exec_module(renderer)

def current():
    return '\n'.join(f'''<VirtualHost *:{port}>
    ServerName tech4learn.com
    DocumentRoot /home/tech4learn/public_html
    ProxyPass /.well-known !
    ProxyPass / http://127.0.0.1:3101/
    ProxyPassReverse / http://127.0.0.1:3101/
    SSLCertificateFile /etc/ssl/virtualmin/synthetic/ssl.cert
</VirtualHost>''' for port in [80,443])

class CandidateTests(unittest.TestCase):
    def test_only_reviewed_site_routes_change(self):
        candidate=renderer.render(current(),'a'*40)
        self.assertEqual(candidate.count('ProxyPass /api/v1/ http://127.0.0.1:3101/api/v1/'),2)
        self.assertNotIn('ProxyPass / http:',candidate)
        self.assertEqual(candidate.count('SSLCertificateFile /etc/ssl/virtualmin/synthetic/ssl.cert'),2)
        self.assertEqual(candidate.count('ProxyPass /.well-known !'),2)
        self.assertEqual(candidate.count('Alias /.well-known/ /home/tech4learn/public_html/.well-known/'),2)
        self.assertIn('FallbackResource /index.php',candidate)
        self.assertIn('^(?!index\\.php$).*\\.php$',candidate)
        self.assertIn('/run/php/tech4learn-native.sock',candidate)
        self.assertIn("Require expr \"%{REQUEST_FILENAME} == '/home/tech4learn/releases/"+'a'*40+"/platform/public/index.php'\"",candidate)
    def test_unknown_site_routing_or_revision_is_rejected(self):
        for source in [current().replace('tech4learn.com','other.example'),current().replace('3101','9999'),current()+'\nInclude other.conf',current().replace('*:80','*:8080')]:
            with self.assertRaises(ValueError):renderer.render(source,'a'*40)
        with self.assertRaises(ValueError):renderer.render(current(),'../../other-site')

if __name__=='__main__':unittest.main()
