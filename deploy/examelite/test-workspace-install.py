import unittest
from workspace_install import add_provider, add_navigation, hosting_config, fix_exam_creation_validation, allow_scoped_language_controller, add_translated_model_answer

class WorkspaceInstallTests(unittest.TestCase):
    def test_translated_model_answer_preserves_omitted_values(self):
        source = ("'fill_blank' => 'nullable|string',\n'fill_blank' => $request->fill_blank,\n") * 2
        updated = add_translated_model_answer(source)
        self.assertEqual(updated.count("$request->has('si_answer1')"), 2)
        self.assertEqual(updated.count("'sometimes|nullable|string'"), 2)
        self.assertEqual(add_translated_model_answer(updated), updated)
        for invalid in [source + source, 'unknown', source + 'si_answer1']:
            with self.assertRaises(ValueError): add_translated_model_answer(invalid)
    def test_scoped_language_hooks_preserve_native_logic(self):
        native = 'private function isPlatformAdmin(): bool { return SaasAccess::isPlatformAdmin(); }\nprivate function platformOrganizationId(): ?int { return $configured; }\npublic function store() { native_write(); }'
        changed = allow_scoped_language_controller(native)
        self.assertEqual(changed, native.replace('private function', 'protected function'))
        self.assertEqual(allow_scoped_language_controller(changed), changed)
        for invalid in ['unknown', native + native, native.replace('bool', 'mixed')]:
            with self.assertRaises(ValueError): allow_scoped_language_controller(invalid)

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

    def test_native_creation_keeps_validated_pass_threshold(self):
        source = "public function store(Request $request) { $request->validate(['passing_percentage'=>'numeric']); $data['passing_percentage'] = $validated['passing_percentage']; } public function edit(Exam $exam) {}"
        expected = source.replace("$request->validate([", "$validated = $request->validate([", 1)
        self.assertEqual(fix_exam_creation_validation(source), expected)
        self.assertEqual(fix_exam_creation_validation(expected), expected)
        with self.assertRaises(ValueError): fix_exam_creation_validation('unknown controller')
        with self.assertRaises(ValueError): fix_exam_creation_validation(source.replace("$request->validate([", "$request->validate([]); $request->validate(["))

    def test_scoped_host_and_injection_denial(self):
        config = hosting_config('/run/php/example.sock','/etc/letsencrypt/live/examelite-workspaces')
        self.assertIn('ServerAlias t4l-*.examelite.com',config)
        self.assertNotIn('ServerAlias *.examelite.com',config)
        self.assertNotIn('ServerName examelite.com',config)
        self.assertIn('SSLCertificateKeyFile /etc/letsencrypt/live/examelite-workspaces/privkey.pem',config)
        with self.assertRaises(ValueError): hosting_config('/run/php/x.sock\nInclude /tmp/x','/etc/letsencrypt/live/examelite-workspaces')

if __name__ == '__main__': unittest.main()
