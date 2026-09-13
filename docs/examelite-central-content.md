# Central exam controls and question transfers

This is the first part of the revised integration. It is implemented locally and requires explicit user deployment. It does **not** complete the full in-page ExamElite authoring and exam-taking requirement.

## Confirmed product direction

Exam tools belong inside Tech4Learn's organisation navigation and domain. ExamElite remains the engine and data authority for questions, exams, attempts and marking. Neither external workspace launch links nor an iframe constitute the desired final interface. Reuse its terminology and workflow alongside the existing shared Tech4Learn form/table template.

Superadmin controls platform availability, organisation/plan entitlements, staff permissions and central content distribution. Platform availability and commercial plan configuration are separate from an organisation's enabled modules; a provider capability must not be labelled working before its adapter and interface are complete.

Central questions can be shared into selected organisations. Superadmin can also pull organisation questions into the central bank. Each operation creates an independently owned copy with provenance; it never transfers ownership or overwrites an original. Only superadmin changes central content. Student identity and programme records remain in Tech4Learn; no reverse import of ExamElite students is implied.

## Working in this change

- Superadmin can enable the organisation's Exams module using the existing versioned module settings. The toggle preserves other modules and branding. Exam permissions now enforce this module flag at the API boundary.
- Superadmin can search the central or selected organisation question bank, select up to 50 questions and share or pull them. Organisation users can view only their own question bank, with module, permission, organisation scope and question-feature checks.
- Questions are paged by stable ID, 50 at a time; search executes in ExamElite. Table filters apply to the explicitly labelled loaded collection. List snippets are escaped text, not executable provider HTML.
- Transfers validate the complete selection against the source tenant before copying. The existing additive ExamElite copy service preserves question configuration, translations and taxonomy, including numeric answer settings. Central pulls reuse existing central language identities without overwriting them.
- A per-workspace request ID makes a lost-response retry safe. A separate copy map preserves subsequent destination edits even when a new transfer request selects the same source again. Reusing a request ID with changed input is rejected.
- The ExamElite transfer table records direction, actor UUID, source/copy IDs and time. The superadmin screen exposes the latest 50 transfers. Tech4Learn also writes an audit event; remote success followed by a local audit failure can be retried safely.
- Sharing can provision an isolated exam organisation through the existing private identity bridge without issuing a launch ticket. Credentials and native session details never reach these screens.
- The organisation UI no longer displays external authoring/student launch buttons. Existing linked result reads remain available.
- Staff with exam management access can create and edit an organisation-owned question inside Tech4Learn: basic formatted text, options, answers, hints, explanations and marks. Changes invoke the installed ExamElite controller with a private mapped staff context. Native validation remains authoritative.
- Authoring uses a revision check, an atomic native transaction and a durable request ledger for safe retries. Unsupported fields cannot change ownership. The API derives the actor from the authenticated session and enforces module, organisation and question restrictions.
- Question types, language and classification use bounded, searchable native choices scoped to the organisation. Creation captures the model from the native event dispatcher rather than guessing a latest ID. Its request ledger prevents duplicate creation on retries.
- Classification create/update reuses native controllers and the same revision/transaction/retry controls. The Subjects feature restriction is enforced separately from the Questions feature restriction. Existing subject category assignments are preserved.
- The exam builder creates and updates organisation exams using the native ExamController: type/syllabus, groups/packages/languages, schedules, timing, passing percentage and delivery flags. It lists owned exams and adds/removes selected owned questions through the native paper actions. The adapter checks the entire question selection before writing and keeps durable retry IDs. Removing a question preserves the bank record.
- The editor uses the shared DraftForm and DOMPurify to sanitise native HTML. DOMPurify is added for this concrete rich-text rendering use case. Image/formula fields remain read-only and preserve their original content; they are not fully displayed in this editor.

## Still required before the full release

Central-original editing, the native media/formula editor, student sign-in/attempt/resume/submission, marking, native result screens, plan catalogue and per-provider module availability still require implementation. The current question editor supports organisation-owned questions. Classification screens support exam groups, subjects, topics, subtopics and question sections through native create/update actions; category assignment and language administration are not yet editable here. Feature restrictions for the existing native backend are retained, but are not evidence that each replacement internal screen is ready.

The old native workspace backend and its expiring sessions are retained for compatibility. Disabling the Tech4Learn module prevents new exam API use in Tech4Learn; it does not immediately revoke a previously issued native session. Existing native session expiry and feature restrictions still apply. Full same-domain delivery must replace that session path before broad use.

## Deployment and verification

Development and tests run locally. Live access by the assistant is read-only. The user runs `deploy/examelite/deploy-central-content.sh` from a checked Git revision. The script requires the earlier native provider setup, preserves private credentials, installs additive sources, adds `tech4learn_content_transfers` and `tech4learn_authoring_requests`, verifies routes/schema and updates Tech4Learn. It does not install certificates or create DNS records.

`npm run check` covers the scaffold. `test-content-api.php` runs against an isolated SQLite database using ExamElite model definitions and tests complete-selection tenant checks, no partial copy on a foreign selection, request replay and pull provenance. `test-content-copies.php` additionally tests destination ownership, original preservation, native settings/translations and retry preservation. The local browser preview uses synthetic questions and checks enable/share/pull controls; it is not a live data or full exam workflow test.

`test-question-authoring.php` invokes the installed native question controller against synthetic SQLite records. It checks answer preservation across multiple-choice, true/false, blanks and numerical rules, native validation, foreign references, stale edits, replay, rejected ownership/markup changes and request-context restoration. Local checks also use a read-only snapshot of the current live controller/models. Host resolution and identity infrastructure are test doubles; this is not a live authoring test. A synthetic browser check covers patch-only saving, revision/request identity, validation draft retention and HTML sanitisation.

Creation/classification verification additionally covers native question translation creation, create retries, all five taxonomy kinds, subject edit scope preservation, foreign taxonomy rejection and restricted writes. The synthetic browser creates a numerical question and a subject through the internal forms. These are local checks, not proof of a live deployment.

The installer also fixes the verified native ExamController create-action bug where `passing_percentage` was read from an unassigned `$validated` variable. The transformation assigns the existing native validation result, is idempotent, rejects an unrecognised controller shape, and participates in the installer backup/rollback. This does not make the internal exam builder complete.

The builder verification runs the current native ExamController against isolated SQLite records, including exact fractional pass thresholds, owned group/language scope, repeat creates, settings edits, question add/remove and mixed-tenant selection rejection. Queue dispatch, paper cache invalidation and the native permission helper are isolated test doubles; Tech4Learn permission enforcement has separate HTTP tests. The synthetic browser creates an exam with a fractional threshold and adds/removes a question using refreshed revisions. Native delivery flags are stored engine settings, not evidence that the corresponding student workflow is available inside Tech4Learn yet.

Paper controls now include native section create/update/removal, assignment through organisation question-section definitions, per-subject durations, and desired Active/Inactive status. Complete subject selections are validated against the owned paper; negative, missing and foreign timers are rejected before native writes. Revisions include question-section pivots and saved timers. Status requests use desired state plus the request ledger, so repeat activation cannot toggle an exam off. These controls do not yet expose student attempts.

Native tests cover section duration overflow, foreign sections, assignment revision changes, publication retries, timer scope/overflow and empty-section removal. The synthetic browser verifies activation, section creation/editing, subject timer values, fresh revisions and successful draft clearing. The full scaffold check remains 82 passing tests.

The [student attempt adapter components](examelite-student-attempts.md) now have local native tests for answer persistence, submission/deadline rejection, retries and student-safe payloads. They are private components with no new public/student route; student authentication, explicit exam grants and the full attempt UI remain outstanding.

Migration 15 and the student entry now implement separate exam-scoped sign-in, expiring grants and revocation without staff membership. The real HTTP and synthetic browser checks pass; full project verification now has 83 passing tests. The student page explicitly marks exam delivery unavailable until native start/resume/submission is connected, and grant-issuing controls are not yet exposed in the staff UI.
