import unittest
from workspace_install import add_provider, add_navigation, hosting_config

class WorkspaceInstallTests(unittest.TestCase):
    def test_additive_and_repeatable(self):
        source = "before App\\Providers\\RouteServiceProvider::class, after"
        changed = add_provider(source)
        self.assertEqual(add_provider(changed), changed)
        self.assertIn('App\\Providers\\RouteServiceProvider::class,', changed)
        self.assertLess(changed.index('Tech4LearnWorkspaceProvider'),changed.index('RouteServiceProvider'))
        self.assertTrue(changed.endswith(' after'))
        layout = "before @yield('content') after"
        updated = add_navigation(layout)
        self.assertEqual(add_navigation(updated), updated)
        self.assertTrue(updated.endswith("@yield('content') after"))

    def test_unknown_layouts_fail_before_mutation(self):
        for fn in (add_provider, add_navigation):
            with self.assertRaises(ValueError): fn('unknown source')
        with self.assertRaises(ValueError): add_navigation("@yield('content') @yield('content')")

    def test_scoped_host_and_injection_denial(self):
        config = hosting_config('/run/php/example.sock','/etc/letsencrypt/live/examelite-workspaces')
        self.assertIn('ServerAlias t4l-*.examelite.com',config)
        self.assertNotIn('ServerAlias *.examelite.com',config)
        self.assertNotIn('ServerName examelite.com',config)
        self.assertIn('SSLCertificateKeyFile /etc/letsencrypt/live/examelite-workspaces/privkey.pem',config)
        with self.assertRaises(ValueError): hosting_config('/run/php/x.sock\nInclude /tmp/x','/etc/letsencrypt/live/examelite-workspaces')

if __name__ == '__main__': unittest.main()
