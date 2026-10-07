"""Pure candidate renderer. Never reads/writes Apache files or reloads services."""
import re


ADMIN_REDIRECTS = '''    RewriteEngine on
    RewriteCond %{HTTP_HOST} =webmail.tech4learn.com
    RewriteRule ^/(?!\\.well-known)(.*)$ https://tech4learn.com:20000/ [R=301,L]
    RewriteCond %{HTTP_HOST} =admin.tech4learn.com
    RewriteRule ^/(?!\\.well-known)(.*)$ https://tech4learn.com:10000/ [R=301,L]
'''


def retire_reviewed_legacy_routes(source):
    """Pure removal of the exact reviewed Virtualmin legacy blocks; no IO."""
    override = 'AllowOverride All Options=ExecCGI,Includes,IncludesNOEXEC,Indexes,MultiViews,SymLinksIfOwnerMatch'
    blocks = [
        '    ScriptAlias /cgi-bin/ /home/tech4learn/cgi-bin/\n',
        '    ScriptAlias /awstats /home/tech4learn/cgi-bin/awstats.pl\n',
        '    DirectoryIndex index.php index.htm index.html\n',
        f'''    <Directory /home/tech4learn/public_html>
        Options -Indexes +IncludesNOEXEC +SymLinksIfOwnerMatch +ExecCGI
        Require all granted
        {override}
        AddHandler fcgid-script .php
        AddHandler fcgid-script .php8.1
        FCGIWrapper /home/tech4learn/fcgi-bin/php8.1.fcgi .php
        FCGIWrapper /home/tech4learn/fcgi-bin/php8.1.fcgi .php8.1
    </Directory>
''',
        f'''    <Directory /home/tech4learn/cgi-bin>
        Require all granted
        {override}
    </Directory>
''',
        '    RemoveHandler .php\n',
        '    RemoveHandler .php8.1\n',
        '    FcgidMaxRequestLen 1073741824\n',
        '''    RedirectMatch ^/awstats$ /awstats/
    <Files awstats.pl>
        AuthName "tech4learn.com statistics"
        AuthType Basic
        AuthUserFile /home/tech4learn/.awstats-htpasswd
        require valid-user
    </Files>
''',
    ]
    candidate = source
    for block in blocks:
        if candidate.count(block) != 2:
            raise ValueError('Legacy site blocks changed; review exact configuration')
        candidate = candidate.replace(block, '')
    # Do not remove or modify the existing administration-service redirects.
    if candidate.count(ADMIN_REDIRECTS) != 2:
        raise ValueError('Administration redirects differ; review required')
    return candidate


def render(source, revision):
    if not re.fullmatch(r'[a-f0-9]{40}', revision):
        raise ValueError('An exact checked Git revision is required')
    hosts = re.findall(r'<VirtualHost\s+\*:([0-9]+)>', source)
    if sorted(hosts) != ['443', '80'] or source.count('</VirtualHost>') != 2:
        raise ValueError('Unexpected virtual host layout')
    names = re.findall(r'^\s*ServerName\s+(\S+)\s*$', source, re.M)
    if names != ['tech4learn.com', 'tech4learn.com'] or re.search(r'^\s*Include', source, re.M):
        raise ValueError('Unexpected site identity or include')
    # Old CGI aliases and rewrite rules can still serve legacy application paths
    # after the root proxy is removed. Require their separate removal/review.
    routing_check = source.replace(ADMIN_REDIRECTS, '')
    if source.count(ADMIN_REDIRECTS) not in [0, 2]:
        raise ValueError('Administration redirects differ; review required')
    if re.search(r'^\s*(?:ScriptAlias\S*|AliasMatch|Rewrite\S*|RedirectMatch|SetHandler|AddHandler|FCGIWrapper|Action)\s+', routing_check, re.M | re.I):
        raise ValueError('Legacy executable or rewrite routing requires review')
    # Refuse additional routing directives rather than silently preserving them.
    # Their ordering can override the intended API/native boundary.
    proxies = re.findall(r'^\s*(ProxyPass\S*|ProxyPreserveHost)\s+(.+?)\s*$', source, re.M | re.I)
    expected = [('ProxyPass', '/.well-known !'),
                ('ProxyPass', '/ http://127.0.0.1:3101/'),
                ('ProxyPassReverse', '/ http://127.0.0.1:3101/')]
    if sorted(proxies) != sorted(expected * 2):
        raise ValueError('Additional or changed proxy routing requires review')
    public = f'/home/tech4learn/releases/{revision}/platform/public'
    candidate = source
    replacements = [
        (r'^([ \t]*)DocumentRoot /home/tech4learn/public_html[ \t]*$', rf'\1DocumentRoot {public}'),
        (r'^([ \t]*)ProxyPass / http://127\.0\.0\.1:3101/[ \t]*$',
         r'\1ProxyPreserveHost On\n\1ProxyPass /api/v1/ http://127.0.0.1:3101/api/v1/'),
        (r'^([ \t]*)ProxyPassReverse / http://127\.0\.0\.1:3101/[ \t]*$',
         r'\1ProxyPassReverse /api/v1/ http://127.0.0.1:3101/api/v1/'),
    ]
    for pattern, replacement in replacements:
        candidate, count = re.subn(pattern, replacement, candidate, flags=re.M)
        if count != 2:
            raise ValueError('Current routing differs; review before rendering')
    directory = f'''    Alias /.well-known/ /home/tech4learn/public_html/.well-known/
    <Directory /home/tech4learn/public_html/.well-known>
        Options -Indexes -ExecCGI
        AllowOverride None
        Require all granted
        <FilesMatch "(?i)\\.(?:php(?:[0-9]+(?:\\.[0-9]+)?)?|phtml|phar)$">
            Require all denied
        </FilesMatch>
    </Directory>
    <Directory {public}>
        Options -Indexes -MultiViews -ExecCGI +FollowSymLinks
        Require all granted
        AllowOverride None
        DirectoryIndex index.php
        FallbackResource /index.php
        SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
        <Files "index.php">
            Require expr "%{{REQUEST_FILENAME}} == '{public}/index.php'"
            SetHandler "proxy:unix:/run/php/tech4learn-native.sock|fcgi://localhost/"
        </Files>
        <FilesMatch "^(?!index\\.php$).*(?i:\\.(?:php(?:[0-9]+(?:\\.[0-9]+)?)?|phtml|phar))$">
            Require all denied
        </FilesMatch>
        <FilesMatch "^\\.">
            Require all denied
        </FilesMatch>
    </Directory>
'''
    candidate = candidate.replace('</VirtualHost>', directory + '</VirtualHost>')
    return candidate
