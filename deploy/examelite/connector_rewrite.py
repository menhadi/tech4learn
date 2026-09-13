"""Narrow Apache exception for a host with legacy /api PHP scripts."""
import re

MARKER = '# Tech4Learn connector routing'
BLOCK = '''<IfModule mod_rewrite.c>
    # Tech4Learn connector routing
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule ^api/tech4learn/v1(?:/|$) - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteRule ^api/tech4learn/v1(?:/|$) public/index.php [END]
</IfModule>

'''


def add_connector_rewrite(content):
    """Prepend before existing /api exclusions; retain all original rules."""
    normalized = content.replace('\r\n', '\n')
    if MARKER in normalized:
        if not normalized.startswith(BLOCK):
            raise ValueError('Existing connector rewrite differs; review it before replacing.')
        return content
    if not re.search(r'^\s*RewriteEngine\s+On\s*$', normalized, re.I | re.M):
        raise ValueError('Expected Apache rewrite configuration is missing.')
    return BLOCK + content
