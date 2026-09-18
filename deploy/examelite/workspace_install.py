"""Pure additive transformations, shared by the installer and local tests."""

def require_pdf_images(text):
    """Keep the native renderer, but never publish a paper with failed images."""
    anchor = "  await page.emulateMedia({ media: 'print' });"
    guard = """  // Tech4Learn: incomplete diagrams must fail the build, not disappear silently.
  await page.evaluate(() => {
    const images = Array.from(document.images);
    if (images.some((image) => !image.complete || image.naturalWidth <= 0 || image.naturalHeight <= 0)) {
      throw new Error('A print image could not be loaded. Retry after restoring the source image.');
    }
  });
"""
    marker = '// Tech4Learn: incomplete diagrams'
    if text.count(anchor) != 1:
        raise ValueError('Unsupported native PDF renderer; no files changed.')
    if marker in text:
        if text.count(guard + anchor) != 1 or text.count(marker) != 1:
            raise ValueError('Modified PDF image guard; no files changed.')
        return text
    if "image.addEventListener('error', resolve" not in text or 'await page.pdf({' not in text:
        raise ValueError('Unsupported native PDF image wait; no files changed.')
    return text.replace(anchor, guard + anchor)

def refresh_pdf_image_cache(text):
    """Do not reuse a PDF produced before image completeness was checked."""
    before = 'public const TEMPLATE_VERSION = 2;'
    after = 'public const TEMPLATE_VERSION = 3; // Tech4Learn checked print images'
    if text.count(after) == 1 and before not in text:
        return text
    if text.count(before) != 1 or 'Tech4Learn checked print images' in text:
        raise ValueError('Unsupported native PDF cache version; no files changed.')
    return text.replace(before, after)
def protect_pdf_worker_lookup(text):
    """Release the acquired native PDF lock even when the build lookup fails."""
    lookup = "$build = ExamPdfBuild::with(['exam.organization', 'package', 'language'])->findOrFail($this->buildId);"
    before = "        " + lookup + "\n        $temporary = null;\n        $next = null;\n\n        try {"
    after = "        // Tech4Learn: build lookup belongs inside the acquired lock's cleanup scope.\n        $build = null;\n        $temporary = null;\n        $next = null;\n\n        try {\n            " + lookup
    catch_before = "$build->update(['status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 2000)]);"
    catch_after = "$build?->update(['status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 2000)]);"
    marker = '// Tech4Learn: build lookup belongs inside'
    cleanup = "        } finally {\n            if ($temporary && is_file($temporary)) File::delete($temporary);\n            if ($next && is_file($next)) File::delete($next);\n            $lock->release();\n        }"
    if text.count(cleanup) != 1:
        raise ValueError('Unsupported native PDF lock cleanup; no files changed.')
    if marker in text:
        if text.count(after) != 1 or text.count(catch_after) != 1 or text.count(marker) != 1:
            raise ValueError('Modified PDF worker cleanup guard; no files changed.')
        return text
    if text.count(before) != 1 or text.count(catch_before) != 1:
        raise ValueError('Unsupported native PDF worker; no files changed.')
    return text.replace(before, after).replace(catch_before, catch_after)

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
