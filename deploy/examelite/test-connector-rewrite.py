import unittest
from connector_rewrite import BLOCK, add_connector_rewrite


class ConnectorRewriteTest(unittest.TestCase):
    def test_legacy_exclusion_is_preserved_after_connector_exception(self):
        legacy = ('<IfModule mod_rewrite.c>\nRewriteEngine On\n'
                  '# DO NOT REWRITE API CALLS\n'
                  'RewriteCond %{REQUEST_URI} ^/api/ [NC]\n'
                  'RewriteRule ^ - [L]\n</IfModule>\n')
        updated = add_connector_rewrite(legacy)
        self.assertEqual(updated, BLOCK + legacy)
        self.assertIn('public/index.php [END]', updated)
        self.assertIn('HTTP_AUTHORIZATION:%{HTTP:Authorization}', updated)
        self.assertEqual(add_connector_rewrite(updated), updated)
        self.assertEqual(add_connector_rewrite(updated.replace('\n', '\r\n')),
                         updated.replace('\n', '\r\n'))

    def test_only_connector_prefix_matches(self):
        import re
        pattern = re.compile(r'^api/tech4learn/v1(?:/|$)')
        for path in ['api/tech4learn/v1/status', 'api/tech4learn/v1/exams',
                     'api/tech4learn/v1/learners/abc/results']:
            self.assertIsNotNone(pattern.search(path))
        for path in ['api/push.php', 'api/getData.php', 'api/tech4learn/v10/status',
                     'dashboard', 'students']:
            self.assertIsNone(pattern.search(path))

    def test_unexpected_configuration_fails_before_writing(self):
        with self.assertRaises(ValueError):
            add_connector_rewrite('Options -Indexes\n')
        with self.assertRaises(ValueError):
            add_connector_rewrite('# Tech4Learn connector routing\nRewriteEngine On\n')


if __name__ == '__main__':
    unittest.main()
