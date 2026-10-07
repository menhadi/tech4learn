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
    def reviewed_source(self):
        legacy='''    ScriptAlias /cgi-bin/ /home/tech4learn/cgi-bin/
    ScriptAlias /awstats /home/tech4learn/cgi-bin/awstats.pl
    DirectoryIndex index.php index.htm index.html
    <Directory /home/tech4learn/public_html>
        Options -Indexes +IncludesNOEXEC +SymLinksIfOwnerMatch +ExecCGI
        Require all granted
        AllowOverride All Options=ExecCGI,Includes,IncludesNOEXEC,Indexes,MultiViews,SymLinksIfOwnerMatch
        AddHandler fcgid-script .php
        AddHandler fcgid-script .php8.1
        FCGIWrapper /home/tech4learn/fcgi-bin/php8.1.fcgi .php
        FCGIWrapper /home/tech4learn/fcgi-bin/php8.1.fcgi .php8.1
    </Directory>
    <Directory /home/tech4learn/cgi-bin>
        Require all granted
        AllowOverride All Options=ExecCGI,Includes,IncludesNOEXEC,Indexes,MultiViews,SymLinksIfOwnerMatch
    </Directory>
    RemoveHandler .php
    RemoveHandler .php8.1
    FcgidMaxRequestLen 1073741824
    RedirectMatch ^/awstats$ /awstats/
    <Files awstats.pl>
        AuthName "tech4learn.com statistics"
        AuthType Basic
        AuthUserFile /home/tech4learn/.awstats-htpasswd
        require valid-user
    </Files>
'''
        return current().replace('</VirtualHost>', legacy+renderer.ADMIN_REDIRECTS+'</VirtualHost>')

    def test_reviewed_retirement_preserves_tls_proxy_and_admin_services(self):
        candidate=renderer.render(renderer.retire_reviewed_legacy_routes(self.reviewed_source()),'a'*40)
        self.assertEqual(candidate.count(renderer.ADMIN_REDIRECTS),2)
        self.assertEqual(candidate.count('SSLCertificateFile /etc/ssl/virtualmin/synthetic/ssl.cert'),2)
        self.assertNotIn('ScriptAlias',candidate)
        self.assertNotIn('FCGIWrapper',candidate)
        self.assertNotIn('awstats',candidate)
        self.assertEqual(candidate.count('Alias /.well-known/'),2)
        self.assertEqual(candidate.count('<Directory /home/tech4learn/public_html/.well-known>'),2)

    def test_changed_legacy_blocks_or_admin_redirects_are_not_retired(self):
        for source in [self.reviewed_source().replace('php8.1','php8.2',1),
                       self.reviewed_source().replace(':10000/',':9999/',1),
                       self.reviewed_source().replace('Require all granted','Require all denied',1),
                       self.reviewed_source().replace('</VirtualHost>','    RewriteRule ^ /other [L]\n</VirtualHost>',1)]:
            with self.assertRaises(ValueError):
                renderer.render(renderer.retire_reviewed_legacy_routes(source),'a'*40)

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
    def test_extra_proxy_routes_and_options_require_review(self):
        for directive in ['ProxyPass /api/ http://127.0.0.1:9999/',
                          'ProxyPassMatch ^/(.*)$ http://127.0.0.1:9999/$1',
                          'ProxyPassReverse /elsewhere/ http://127.0.0.1:3101/',
                          'ProxyPreserveHost Off']:
            source=current().replace('</VirtualHost>', '    '+directive+'\n</VirtualHost>', 1)
            with self.assertRaises(ValueError):renderer.render(source,'a'*40)
        with self.assertRaises(ValueError):
            renderer.render(current().replace('3101/', '3101/ retry=0'),'a'*40)
    def test_unknown_site_routing_or_revision_is_rejected(self):
        for source in [current().replace('tech4learn.com','other.example'),current().replace('3101','9999'),current()+'\nInclude other.conf',current().replace('*:80','*:8080')]:
            with self.assertRaises(ValueError):renderer.render(source,'a'*40)
        with self.assertRaises(ValueError):renderer.render(current(),'../../other-site')

    def test_legacy_executable_and_rewrite_routes_require_review(self):
        for directive in ['ScriptAlias /cgi-bin/ /home/tech4learn/cgi-bin/',
                          'ScriptAliasMatch ^/legacy/(.*) /home/tech4learn/$1',
                          'RewriteRule ^legacy/(.*) /old/$1 [L]',
                          'RedirectMatch ^/old /legacy',
                          'SetHandler application/x-httpd-php',
                          'AddHandler fcgid-script .php',
                          'FCGIWrapper /home/tech4learn/fcgi-bin/php.fcgi .php',
                          'Action application/x-httpd-php /old-handler',
                          'AliasMatch ^/legacy/(.*) /home/tech4learn/$1']:
            with self.subTest(directive=directive), self.assertRaises(ValueError):
                renderer.render(current().replace('</VirtualHost>', '    '+directive+'\n</VirtualHost>', 1),'a'*40)

if __name__=='__main__':unittest.main()
