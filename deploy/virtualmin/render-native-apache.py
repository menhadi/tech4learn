"""Pure candidate renderer. Never reads/writes Apache files or reloads services."""
import re


def render(source, revision):
    if not re.fullmatch(r'[a-f0-9]{40}', revision):
        raise ValueError('An exact checked Git revision is required')
    hosts = re.findall(r'<VirtualHost\s+\*:([0-9]+)>', source)
    if sorted(hosts) != ['443', '80'] or source.count('</VirtualHost>') != 2:
        raise ValueError('Unexpected virtual host layout')
    names = re.findall(r'^\s*ServerName\s+(\S+)\s*$', source, re.M)
    if names != ['tech4learn.com', 'tech4learn.com'] or re.search(r'^\s*Include', source, re.M):
        raise ValueError('Unexpected site identity or include')
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
        <FilesMatch "^(?!index\\.php$).*\\.php$">
            Require all denied
        </FilesMatch>
    </Directory>
'''
    candidate = candidate.replace('</VirtualHost>', directory + '</VirtualHost>')
    return candidate
