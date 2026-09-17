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

def fix_exam_creation_validation(text):
    """Keep the native validated pass threshold instead of casting an undefined value."""
    start = text.find('public function store(Request $request)')
    end = text.find('public function edit(', start)
    if start < 0 or end < 0:
        raise ValueError('Unsupported native exam controller; no files changed.')
    block = text[start:end]
    if "$validated['passing_percentage']" not in block:
        return text
    if '$validated = $request->validate([' in block:
        return text
    if block.count('$request->validate([') != 1 or '$validated =' in block:
        raise ValueError('Review native exam creation validation before installing.')
    return text[:start] + block.replace('$request->validate([', '$validated = $request->validate([', 1) + text[end:]

def allow_scoped_language_controller(text):
    """Expose two native context hooks; preserve native language writes/validation."""
    for method, result in [('isPlatformAdmin', 'bool'), ('platformOrganizationId', '?int')]:
        private = f'private function {method}(): {result}'
        protected = f'protected function {method}(): {result}'
        if text.count(private) + text.count(protected) != 1:
            raise ValueError('Unsupported native language controller; no files changed.')
        text = text.replace(private, protected)
    return text


def add_translated_model_answer(text):
    """Extend native translated wording without clearing omitted legacy answers."""
    rule = "'fill_blank' => 'nullable|string',"
    write = "'fill_blank' => $request->fill_blank,"
    answer_rule = "'si_answer1' => 'sometimes|nullable|string',"
    answer_write = "...($request->has('si_answer1') ? ['si_answer1' => $request->input('si_answer1')] : []),"
    if text.count(rule) != 2 or text.count(write) != 2:
        raise ValueError('Unsupported native question-language controller; no files changed.')
    if text.count(answer_rule) == 2 and text.count(answer_write) == 2:
        return text
    if 'si_answer1' in text:
        raise ValueError('Review existing native model-answer handling before installing.')
    return text.replace(rule, rule + '\n            ' + answer_rule).replace(write, write + '\n            ' + answer_write)
