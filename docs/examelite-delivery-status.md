# ExamElite integration: current delivery status

Updated 18 September 2026. This is the current summary; the central-content and
student-attempt documents also contain historical milestone notes. A historical
“pending” statement does not override a later implemented milestone.

**The full requested integration is not complete.** The same-domain core pilot
works in isolated local checks. No complete live release or full ExamElite
feature parity has been verified. Development remains local; the user deploys
checked commits when the release is ready.

## Implemented and checked locally

| Workflow | Current boundary |
| --- | --- |
| Organisation and superadmin navigation | Internal Tech4Learn screens; ExamElite remains the backend engine. |
| Feature controls | Exams module and five native feature groups; revisioned organisation controls, native plan permissions and superadmin assignment of existing plans. Plan creation, billing and provider management remain unfinished. |
| Central distribution | Share questions to organisations and pull organisation questions into central ownership as independent copies. No student/result sharing. |
| Questions and classifications | Native question creation/editing, taxonomy, central language creation/editing, organisation language enabling, free packages and guarded category deletion. |
| Source media and wording | Raster upload/replacement/removal, protected previews, supported MathML replacement and literal surrounding-text edits/appends. Arbitrary native markup is not fully supported. |
| Translated wording | Review, edit, refresh request and approval; retain existing opaque images while editing question text/model answers and exam instructions/syllabus. Saved question and exam translations support raster upload, replacement and reference removal. |
| Exam management | Native settings, question assembly, sections, subject timers, activation and result visibility for organisation and central owners. |
| Student and marking pilot | Same-domain scoped entry, staff-issued grants, start/resume, answer save/retry, submit, staff marking and published result history. |
| Documents | Native PDF request/status/approved download adapters and screens. Actual rendering/worker completion is not yet verified. |

Latest validation: `npm run check` passed with 86 tests, all workspace
typechecks and production builds. The existing large-bundle warning remains.
Native adapter suites and synthetic React checks provide additional coverage.
The connected pilot exercises Nest HTTP and isolated native controllers. It
does not establish production Laravel middleware/TLS, concurrency, device
compatibility or live worker operation.

## Remaining work and completion evidence

These are outstanding deliverables, not enabled or completed features. Work
should proceed in coherent workflow batches rather than treating each small
adapter milestone as a release.

1. **Complete question media workflows.** Address upload orphan
   reconciliation, and inventory remaining native formula/markup formats.
   Verify organisation and central authoring, private previews, retries and
   unchanged copies together.
   The native translated-image action now uses the owned paper's saved
   translation, source-owner file namespace, exam/review revisions and request
   ledger. Upload, replacement and reference removal invoke the native
   question-language controller and invalidate translation approval/documents.
   Organisation/central tests cover private review references, repeat requests,
   stale/foreign denial and cleanup after failed native saves. Private action
   routes now also have bounded envelopes and controller/registration tests;
   the user-run deployment script includes that check. The Tech4Learn gateway
   now forwards bounded translated-image actions for organisation and central
   owners, derives actors from current sessions and rechecks access after the
   native response. Organisation writes return only the saved exam ID/revision,
   so the interface reloads the reviewed translation after a save. The shared
   translated question editor now provides those controls for saved translations,
   locks wording while an image write is uncertain and preserves identical retry
   requests. Drafts contain destination choices only, not file bytes. Synthetic
   browser checks cover both owners, upload/replacement/removal, retry, locks,
   byte exclusion and missing-translation gating. Existing images in translated
   exam instructions and syllabus now also survive text/formula edits: opaque
   identities resolve only against the same locked translated field. Native
   tests cover both owners, source/other-field preservation, invalid references,
   stale review, missing targets and approval invalidation. The shared editor
   enables these retained-image edits, with browser checks for both owner paths
   and identical retries. Translated exam instructions and syllabus now support
   the same upload/replace/remove workflow through exam media identity zero.
   Native checks cover both owners, protected reading of uploaded bytes,
   unchanged source/other fields, exact retries, invalid destinations and cleanup
   after an oversized native save fails. The gateway distinguishes question and
   exam destinations, and the shared editor locks wording during uncertain
   image writes. Browser checks cover both owner endpoints, default destination
   and identical retry. Removed references preserve old shared bytes; orphan
   reconciliation and production concurrency remain unfinished.
   A further native gap was confirmed in the inspected controller snapshot:
   `QuestionLang` and native translation fingerprints include `si_answer1`
   (the subjective model answer), but `QuestionLangController::store/update`
   neither validates nor saves it. A guarded, repeatable installer extension now
   adds native validation and conditional writes. Isolated central/organisation
   checks cover model-answer formulas and images, preservation of source and
   other wording, legacy native forms that omit the answer, retries and explicit
   clearing. The gateway and shared editor now accept the model-answer field.
   The user-run deployment checks this extension after installation. Native,
   gateway and synthetic browser fixtures remain separate local checks, not live
   acceptance of this workflow.
2. **Verify native background processing.** Exercise PDF rendering and AI
   translation completion in isolated local worker fixtures, including failure,
   approval invalidation and repeat requests. A queued request alone is not a
   completed document or translation.
   The native PDF worker has an isolated cached-artifact check covering ready
   activation, repeat execution, approval failure, lock release and preservation
   of the previous artifact. It now uses the native fingerprint service too,
   verifying that an attached source edit selects a different cached version.
   An isolated Laravel database queue now serializes the native PDF job,
   reserves it, invokes its queued handler and acknowledges completion. Checks
   cover reservation exclusion, contended-lock delayed release, retained retry
   counts and approval failure/recovery without replacing the prior artifact.
   Failure retry is explicitly released by the fixture, not a background worker
   loop. Artifact bytes remain synthetic; actual rendering, production queue
   configuration and worker backoff/exhaustion remain unverified.
   Native translation completion now also has isolated checks for ready state,
   manual versus automatic approval, repeat completion, contended locks and
   injected feature denial. Provider access is explicitly forbidden in that
   fixture; AI generation, real plan evaluation and queue transport remain
   unverified by it.
3. **Complete the agreed module coverage.** Finish platform module/provider
   catalogue and full plan management, paid-package workflows, OMR, student answer
   file/media uploads and remaining reports/portal workflows. Inventory actual
   native capabilities before exposing controls; retain native engine ownership.
   A private native capability reader now inventories the installed engine's
   plan feature keys and evaluates the mapped organisation's entitlements using
   native `SaasAccess`. It reports the five workspace restrictions separately,
   omits unknown plan attributes and never creates an organisation or login
   ticket. Isolated checks cover plan changes, expired subscriptions and owner
   mapping. A superadmin-only plan-permission catalogue is now connected to this
   reader. Its gateway validates bounded feature flags, rechecks stored admin
   access after the read and rejects stale or inconsistent workspace rules.
   The screen clears old results on failed refresh and organisation changes,
   and distinguishes plan permissions from implemented Tech4Learn tools.
   Provider management and full commercial-plan management remain unfinished.
   Native active-plan options now have a private, paginated reader with the
   current assignment and opaque plan revisions. Its output excludes prices,
   configuration and feature payloads. Isolated checks cover active-only pages,
   changed plans and workspace ownership. Reading options changes no subscription.
   A private persistence helper now invokes the native SaaS organisation
   controller to assign an active plan after checking workspace ownership and
   both organisation/plan revisions. It preserves contact, domain, status and
   expiry values and does not edit shared plans. Isolated checks cover native
   validation, audit invocation and rollback after a native failure.
   An assignment coordinator now checks the central actor's
   active owner/admin membership and workspace mapping before writes or receipt
   replay, binds receipts to the exact request and restores native request/auth
   context after success or failure. Native checks cover revoked membership,
   changed retry payloads, stale revisions, rollback and no duplicate writes.
   The reader supplies an organisation revision for optimistic concurrency.
   A private credential-protected action route now validates exact bounded input
   and supports first-use provisioning of a central-only author without a global
   admin role. Tests cover input overrides and missing actor mappings on replay.
   The deployment script includes the isolated assignment suite and route check.
   The Tech4Learn gateway now exposes bounded plan reads and assignment writes
   only to current stored superadmins, derives the actor from the authenticated
   account, and rechecks access after the native response. It preserves request
   IDs for retry, strips extra response fields and returns stale assignments as
   conflicts so the interface can request a reload. HTTP tests cover injected
   actors, malformed plans, revoked access and identical retry forwarding.
   The superadmin screen now loads plan choices and assigns an existing plan
   without changing subscription dates. It keeps uncertain writes locked to the
   same request, offers a scoped draft restore and reloads the permission view
   after success. Synthetic browser checks cover selection, identical retry
   through repeated draft restores, stale conflict recovery and organisation
   switching. This exposed and fixed a shared-form restore issue: persisting
   after an asynchronous controlled-state restore used the earlier state.
   Draft persistence now uses current controlled state and continues saving
   restored edits. Plan creation, billing, provider controls and a live complete
   plan-assignment journey remain outside this milestone.
   A connected local plan check now sends real Nest HTTP requests through the
   registered native routes and SaaS organisation controller. It verifies
   assignment, replay without a second audit invocation, selected-plan refresh,
   stale conflicts and stored-access revocation, with unchanged organisation
   details and shared plans. This uses synthetic databases and CLI transport to
   PHP; native HTTP middleware, database audit persistence and production
   concurrency remain unverified.
   Central language deletion now has a native service guard for enabled copies,
   source questions, question/passage translations, exam language links and
   translations, results, PDF builds and official-source rules. English is
   protected. Isolated native checks cover these references, foreign ownership,
   stale revisions and retry after deletion, including a disabled actor.
   The private route, superadmin-only gateway and confirmation UI are implemented
   locally. The gateway rejects organisation deletion and rechecks permissions
   after the native response. Native route checks cover bounded requests, owner
   overrides, stale versions and receipt replay; synthetic browser checks cover
   confirmation, unsaved edits, identical retry and missing-record recovery.
   Production concurrency and live acceptance remain unverified.
4. **Run representative complete journeys.** Cover superadmin distribution,
   organisation-owned editing, student entry through results, multiple question
   types/papers, concurrent saves and representative device layouts. Check
   revoked access and foreign-organisation denial across each journey.
5. **Prepare the final deployment handoff.** Run release checks, verify a clean
   pushed revision and produce one checked copy-paste deployment procedure with
   post-deployment acceptance checks. Live installation and migrations remain
   user-run; this assistant's live access stays read-only.

The remaining scope includes substantial workflows, so no reliable completion
date or percentage is established by the passing core pilot. Report completion
against these deliverables, and identify any external dependency explicitly.

## Detailed evidence

- [Central content and authoring](examelite-central-content.md)
- [Student delivery and marking](examelite-student-attempts.md)
- [Repeatable connected/browser checks](../tests/browser/README.md)
- [Current deployment procedure](examelite-native-workspace.md#current-user-run-deployment)
