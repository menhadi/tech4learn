"""Pure additive transformations, shared by the installer and local tests."""
def add_provider(text):
    marker = 'App\\Providers\\Tech4LearnWorkspaceProvider::class,'
    anchor = 'App\\Providers\\RouteServiceProvider::class,'
    if text.count(anchor) != 1:
        raise ValueError('Unsupported ExamElite provider list; no files changed.')
    if marker in text:
        if text.count(marker)!=1: raise ValueError('Duplicate workspace provider.')
        if text.index(marker)<text.index(anchor): return text
        text=text.replace(marker,'')
    # Register before the existing route provider and its catch-all website route.
    return text.replace(anchor, marker + '\n        ' + anchor)

def add_navigation(text):
    marker = "@includeIf('tech4learn::navigation')"
    if marker in text:
        return text
    anchor = "@yield('content')"
    if text.count(anchor) != 1:
        raise ValueError('Unsupported ExamElite layout; no files changed.')
    return text.replace(anchor, marker + '\n' + anchor)

def hosting_config(socket, certificate_root):
    import re
    if not re.fullmatch(r'/run/php/[a-zA-Z0-9_.-]+\.sock', socket):
        raise ValueError('Invalid PHP socket.')
    if certificate_root != '/etc/letsencrypt/live/examelite-workspaces':
        raise ValueError('Unexpected certificate directory.')
    return f'''# Tech4Learn isolated native ExamElite hosts
<VirtualHost *:80>
    ServerName t4l-workspaces.examelite.com
    ServerAlias t4l-*.examelite.com
    RewriteEngine on
    RewriteCond %{{HTTP_HOST}} ^(t4l-[a-z0-9-]+\\.examelite\\.com)(?::80)?$ [NC]
    RewriteRule ^ https://%1%{{REQUEST_URI}} [R=301,L]
</VirtualHost>
<VirtualHost *:443>
    ServerName t4l-workspaces.examelite.com
    ServerAlias t4l-*.examelite.com
    DocumentRoot /home/examelite/public_html
    SSLEngine on
    SSLCertificateFile {certificate_root}/fullchain.pem
    SSLCertificateKeyFile {certificate_root}/privkey.pem
    SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
    ErrorLog /var/log/apache2/tech4learn-exams-error.log
    CustomLog /var/log/apache2/tech4learn-exams-access.log combined
    DirectoryIndex index.php
    <Directory /home/examelite/public_html>
        Options -Indexes +SymLinksIfOwnerMatch
        AllowOverride All
        Require all granted
    </Directory>
    <FilesMatch \\.php$>
        SetHandler "proxy:unix:{socket}|fcgi://127.0.0.1"
    </FilesMatch>
</VirtualHost>
'''
