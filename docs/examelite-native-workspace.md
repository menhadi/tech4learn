# Native ExamElite workspace

Historical implementation record. The external browser workspace described below is superseded by the [same-domain integration](examelite-central-content.md). Current local code rejects its launch endpoint and old workspace-host requests, including existing sessions. Private organisation/staff provisioning remains in use, without launch tickets or automatic all-group student access. These changes still require user deployment; assistant production access is read-only.

## Original behaviour (retired browser interface)

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

## Current user-run deployment

The current deployer installs the same-domain integration. It no longer requests a wildcard certificate, installs workspace subdomain vhosts or checks a retired workspace launch URL. Staff and student browsers stay on Tech4Learn; the private engine connection still uses `https://examelite.com` with full TLS verification. Existing main-domain certificates and the central credential remain required. Legacy vhost and certificate files are left in place; their removal is not part of this deployment. Installed middleware rejects the retired browser hosts.

When a checked release is ready and the user elects to deploy it, run as root after pulling that commit. This is a deployment procedure, not a statement that all requested features are complete:

```bash
bash /home/tech4learn/tech4learn-app/deploy/examelite/deploy-native-workspace.sh
```

This installs additive files in both ExamElite and T4L on the same server. It requires a clean checkout, preflights the central credential, runs isolated native tests, backs up replaced source files, installs provider files and applies the explicit workspace schema. It verifies route dispatch, compiled views, schema and authenticated health before running T4L's existing backup/build/migrate/health procedure. A native test or engine health failure stops before the T4L update. No workspace DNS or wildcard certificate is needed by this path.

Source backups are printed under `/root/tech4learn-backups/`. A failure can leave the additive ExamElite installation in place even if T4L has not been updated. The T4L deployer restores its previous built files on failure; there is no whole-deployment database rollback. Retain additive tables and copied content during recovery.

After deployment: open the organisation's internal exam screens, create or copy a question and exam, edit the owned version, issue a student link, complete an attempt, mark it and publish its result. For an OMR-enabled exam, print a blank sheet, upload a completed JPEG, PNG or PDF for an authorised student, open it and save a manual answer review. Confirm the browser stays on Tech4Learn, the shared original and other organisations remain unchanged, and a revoked student link cannot reopen the attempt. OMR scan review does not automatically score or create a paper attempt. Follow the [current implementation status](examelite-central-content.md) for remaining functionality and verification limits. No production write is performed by the assistant.

The local `test-deploy-workspace.py` check replaces every installer command with an inert stub and verifies clean-checkout enforcement, native-test failure, engine-health failure and successful sequencing without a wildcard certificate. It does not execute a production deployment or validate the host's PHP, network or service configuration.
