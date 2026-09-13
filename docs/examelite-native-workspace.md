# Native ExamElite workspace

Implemented locally. Production installation and an end-to-end live exam attempt still need verification after user deployment. Assistant production access is read-only.

## Behaviour

Tech4Learn opens ExamElite's existing subject, topic, subtopic, section, question, exam, student and result interfaces. It does not duplicate the engine. Five capability groups (curriculum, questions, exams, taking and results) are enabled by default. Superadmin can restrict them per organisation with revision checks. Staff need organisation-wide `exams.manage`; branding access alone is insufficient.

Each organisation gets its own ExamElite organisation and `t4l-<organisation UUID without hyphens>.examelite.com` host. Staff receive membership there without a global administrator role. Student launch transfers only a name and opaque local identifier; no attendance, photographs, phone or email. Existing ExamElite students are not imported into T4L. Pilot mappings/results remain available under the legacy results section.

The shared library creates an organisation-owned copy for editing. Reopening it preserves local edits. Copies remap subjects, groups, topics, subtopics, sections, categories, passages, languages, translations, question taxonomy, exam question assignments and timers. Active source files on native local/public storage are copied independently; failed copies roll back owned records and files. Provider file handles and translation approver identities are not inherited. Personal student practice, attempts, results, purchases and processing/repair jobs are excluded. Unpublished repair drafts are not published by copying. Translations need receiving-organisation approval before delivery.

Native authoring, import/export, printing, language preparation, question AI entry points, subjective evaluation and delivery continue in ExamElite. Provider-dependent operations require its configured providers and workers. Enabling a capability does not create AI credentials or paper/OMR integrations. Every native mode has not yet been demonstrated end to end.

## Session boundary

Random hashed single-use tickets last two minutes, travel in URL fragments and are consumed by CSRF-protected POST. Host-only Secure/HttpOnly cookies separate organisations and existing main-domain sessions. Native sessions last at most four hours. Each request checks workspace, mapped identity, native membership and current feature restrictions. T4L role changes prevent the next launch but do not immediately revoke an already-open native session. Feature restrictions take effect on the next native request.

Native tenant-aware controllers enforce record access. Global SaaS management, independent student administration, messaging and site administration are outside the workspace. Only the central superadmin edits shared originals.

## Tests

Run `npm run check` for API tests, workspace typechecks and production builds. PHP policy, copy, provisioning, ticket and gate tests use isolated fixtures and actual locally installed ExamElite models. They cover ownership, original preservation, retry, foreign-tenant denial, translations/timers, source-file independence and rollback, private-practice exclusion, circular relationships, minimal identities, expiry/replay, host cookies and restrictions. The provisioning test stubs the credential reader; deployment checks authenticated provider HTTP separately.

Python installer tests check additive registration, provider ordering before native catch-all routes, repeat installs and scoped vhosts. Synthetic browser checks cover T4L buttons, learner selection, expiring links and saved restrictions. They are not a production exam attempt.

## User-run deployment

The existing origin certificate does not cover workspace hosts; a read-only request returned Cloudflare 526. Keep full TLS verification enabled. If the wildcard certificate is not present, run:

```bash
sudo certbot certonly --manual --preferred-challenges dns \
  --cert-name examelite-workspaces -d '*.examelite.com'
```

Add Certbot's requested DNS TXT record before continuing. Wildcard DNS must route workspace hosts to the existing origin. Manual DNS certificates need renewal before expiry; this does not configure unattended renewal.

After pulling the checked commit, run as root:

```bash
bash /home/tech4learn/tech4learn-app/deploy/examelite/deploy-native-workspace.sh
```

This installs additive files in both ExamElite and T4L on the same server. It preflights the wildcard certificate/key, PHP socket and central credential; runs isolated tests; backs up replaced source files; installs provider files and four additive tables; checks actual route dispatch, CSRF, compiled views and authenticated health; installs a dedicated vhost; then runs T4L's existing backup/build/migrate/health procedure for migration 14. Main ExamElite vhosts/certificates and central credentials are preserved.

Source backups are printed under `/root/tech4learn-backups/`. A failure can leave the additive ExamElite installation in place even if T4L has not been updated. The T4L deployer restores its previous built files on failure; there is no whole-deployment database rollback. Retain additive tables and copied content during recovery.

After deployment: open exam management, copy an exam from Shared library, edit the owned version, create a student link, complete an attempt and verify its result. Confirm the shared original and other organisations are unchanged. No production write is performed by the assistant.
