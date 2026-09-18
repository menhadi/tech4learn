# ExamElite integration: current delivery status

Updated 19 September 2026. This is the current summary; the central-content and
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
| Feature controls | Exams module and five native feature groups; revisioned organisation controls, native plan permissions, superadmin plan creation, editing and assignment. Billing and provider management remain unfinished. |
| Central distribution | Share questions to organisations and pull organisation questions into central ownership as independent copies. No student/result sharing. |
| Questions and classifications | Native question creation/editing, taxonomy, central language creation/editing, organisation language enabling, free packages and guarded category deletion. |
| Source media and wording | Raster upload/replacement/removal, protected previews, supported MathML replacement and literal surrounding-text edits/appends. Arbitrary native markup is not fully supported. |
| Translated wording | Review, edit, refresh request and approval; retain existing opaque images while editing question text/model answers and exam instructions/syllabus. Saved question and exam translations support raster upload, replacement and reference removal. |
| Exam management | Native settings, question assembly, sections, subject timers, activation and result visibility for organisation and central owners. |
| Student and marking pilot | Same-domain scoped entry, staff-issued grants, start/resume, answer save/retry, submit, staff marking and published result history. |
| Documents | Native PDF request/status/approved download adapters and screens. Actual rendering/worker completion is not yet verified. |

Latest validation: `npm run check` passed with 87 tests, all workspace
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
   Student display now accepts legacy MathJax SVG/CHTML wrappers when they have
   a recoverable MathML source in the native generated-wrapper shape. ExamElite's
   normaliser converts the formula; generated graphics never enter the payload.
   Arbitrary vector diagrams and interactive/media elements remain unsupported.
   Native `needs_review` outcomes now stop delivery instead of passing through
   an unconverted formula. Tests cover question/options/hints/passages, bounded
   wrapper nesting, surrounding text, protected images and rejected shapes.
   The continuous native exam journey confirms unsupported formula delivery
   rolls back attempted student/attempt provisioning. This is display support;
   arbitrary formula authoring and browser visual parity remain unfinished.
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
   The fixture now also invokes Laravel's actual Worker processing policy:
   failed jobs are automatically released with attempt-specific backoff, the
   native three-attempt limit exhausts the job, one failure event is emitted,
   and an explicit new request recovers after translation approval is restored.
   This is in-process worker execution; daemon operation, timeout signals and
   production failed-job persistence remain unverified. Artifact bytes remain
   synthetic; actual rendering and production queue configuration remain open.
   A missing native build was found to throw before the acquired lock's cleanup
   scope, leaving it held until expiry. The guarded installer now moves lookup
   inside that scope and tolerates the missing row in failure reporting. The
   regression fails against the original source and passes against the patched
   isolated copy, confirming release without changing the prior artifact.
   Installer checks cover repeatability, modified/unknown layouts and stopping
   deployment before migrations if the guard cannot be verified.
   Source review found that the native renderer treated failed image loads as
   successful waits and could publish a paper missing diagrams. The repeatable
   installer now adds a pre-print completeness/dimension check while preserving
   the native renderer. Legacy cache version 2 advances to 3; the audited newer
   content-only cache is preserved as described below. Contract checks cover good,
   broken and incomplete images, HTTP/math readiness failures and cleanup.
   The deployment runs these checks before migrations. Actual rendered output
   is still unverified; these checks do not launch Chromium.
   A fresh read-only source audit found a newer deployed renderer, cache and
   worker than the original local fixture. The image guard now also rejects
   retained native image warnings after broken images have been replaced or
   removed. The worker patch accepts the newer ready-artifact shortcut and
   preserves its runtime retries while putting lookup under lock cleanup.
   Both source generations pass installer repeatability/rejection checks;
   transformed current PHP/JavaScript also pass syntax checks. The renderer contract double now runs
   both complete source generations, including the current runtime handshake,
   version-mismatch recovery/exhaustion, native image replacement, pre-existing
   missing-source markers, MathJax errors and print failures. The unpatched
   current renderer fails the missing-diagram regression; both patched versions
   pass. No browser is launched and no real PDF is rendered by these checks.
   Cache compatibility now recognises the audited content-only schema 1 and
   runtime version 23, preserving its fingerprint function byte-for-byte.
   Changing that runtime version to invalidate content would conflict with
   native approved-artifact retention and PHP/queue handshake semantics.
   Existing approved PDFs are retained under native publication policy; they
   are not retrospectively certified by the new image guard. Subsequent actual
   renders use the completeness guard. Unknown schemas, versions or fingerprint
   layouts still stop installation. Both native cache generations and the
   deployment-flow tests pass. The fresh native worker, cache and lifecycle now
   also pass isolated cached-artifact execution and actual Laravel database
   queue/Worker checks. Fixtures explicitly queue replacements and reseed
   synthetic artifacts after the current engine removes superseded versions;
   accidental real rendering is forbidden. Tests preserve the native contention
   policies: the legacy worker delays a duplicate; the current worker acknowledges
   it while the lock owner continues. Both versions cover approval failure,
   retry exhaustion/recovery and missing-build lock cleanup. The unpatched
   current worker fails that cleanup regression and the patched copy passes.
   The user-run deployment now executes this isolated worker/queue suite against
   installed native definitions after source installation and before migrations,
   cache clearing or Tech4Learn UI deployment. Seven mocked deployment-ordering
   checks include stopping at a failed worker execution test. This verifies
   ordering locally; it does not mean the deployment has been run on live.
   **Do not deploy yet:** actual rendering and production worker configuration
   still need verification, alongside the remaining scope.
   Native translation completion now also has isolated checks for ready state,
   manual versus automatic approval, repeat completion, contended locks and
   injected feature denial. Provider access is explicitly forbidden in that
   fixture; AI generation, real plan evaluation and queue transport remain
   unverified by it.
   A separate native generation fixture now runs the real translator against a
   synthetic provider boundary. It verifies five-question batching and remaining
   work, transactional rollback for provider failure, invalid JSON and omitted
   wording, owner-specific configuration selection, question/model-answer
   persistence, source fingerprints, changed-field-only refresh and no duplicate
   provider work after completion. Foreign/unlinked language and revoked-feature
   checks stop before provider selection. Manual review stays unapproved after
   completion. The user-run deployment gates migration on this fixture, and
   eight isolated deployment-flow checks pass. This covers the native generation
   pipeline; real provider output quality, plan evaluation and queue/daemon
   operation still require verification.
3. **Complete the agreed module coverage.** Finish platform module/provider
   catalogue and full plan management, paid-package workflows, OMR, student answer
   file/media uploads and remaining reports/portal workflows. Inventory actual
   native capabilities before exposing controls; retain native engine ownership.
   Passage authoring now has a native service foundation using the inspected
   PassageController for creation and language-specific edits. Its snapshots
   include wording in concurrency revisions; bounded text/formula input rejects
   arbitrary new image references. Organisation fixtures cover native saves,
   exact retry, stale/foreign denial, feature revocation and invocation of native
   document invalidation for referenced questions. Central native authoring and
   private read/write/catalogue routes now also pass checks for ownership,
   independent language edits, exact replay and revoked actor membership.
   Passage choices honour the questions restriction even when other features
   remain available. Deployment includes these isolated native checks.
   The Tech4Learn gateway now connects passage reads/writes/catalogues for both
   owners, validates bounded language-keyed wording and derives native actors
   from the signed-in user. Passage access uses the questions permission group
   and is checked again after remote reads/writes. Service-level gateway tests
   cover invalid owner/actor overrides, multibyte size limits and revocation
   during remote work. Both owners now use the shared classification form to
   create/edit passages with a language picker, formatted wording and scoped
   draft recovery. Only changed language versions are submitted; unsupported
   media versions remain read-only and preserved. A synthetic React/browser
   fixture passes for both owners, language switching, media preservation and
   identical retry after a lost acknowledgement. Question authoring now includes
   the owned passage picker for both owners. Native checks attach an owned
   passage, reject a foreign reference without changing the question, and
   invalidate both referring questions after a wording edit. The picker browser
   fixture now passes for both owners, including scoped choices, minimal field
   updates and an identical retry after an unconfirmed save.
   Passage saves now freeze wording, language, navigation and draft restoration
   while a write is unconfirmed, keeping the original request for retry.
   Validation/conflict responses reopen editing; connection/server failures and
   access changes retain the pending request. The extended browser fixture now
   passes for creation and editing under both owners. It simulates a committed
   save whose acknowledgement is lost, verifies the edit locks and identical
   retry, and remounts a new-passage form to restore its scoped pending draft
   before retrying. These checks use synthetic transport, not a live write.
   Question authoring now also locks wording, answer controls, classifications,
   image actions, navigation and draft replacement after an unconfirmed save.
   Retrying uses the original request; validation/conflict errors allow edits
   with a new request. The browser fixture passes for organisation and central
   editing, creation with pending-draft restoration, identical lost-response
   retries, and correction after a definitive validation rejection.
   Connected browser acceptance, passage media and actual PDF regeneration
   verification remain pending.
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
   A private plan-creation persistence helper now delegates to the native SaaS
   controller. It defaults native capabilities to available, supports explicit
   restrictions and limits, and preserves existing plans, the global default
   and all organisation assignments. Isolated native tests cover validation,
   unique slugs, commercial metadata and rollback after audit failure. This
   helper now has a private central-credential route and transactional actor
   coordinator. It provisions only a central-scoped author, rechecks active
   native membership before writes/replays, binds receipts to the actor and
   fields, and restores native context after failure. Native checks cover
   duplicate prevention, changed-payload rejection, revocation, bounded input
   and joint plan/receipt rollback. Deployment verifies route registration and
   native checks. The Tech4Learn gateway now accepts bounded creation settings
   only from stored superadmins, derives the actor from the session, retains
   request IDs, rechecks access after the native response and projects only the
   created plan identity/revision. HTTP checks cover invalid settings, injected
   actors, identical retries, inconsistent responses and access revocation.
   The same-domain superadmin form now loads supported fields, defaults native
   capabilities to available and accepts plan details, restrictions and usage
   limits. Its scoped draft retains the exact request after an uncertain save;
   retries lock editing and successful creation resets the form. Synthetic
   browser checks cover repeated draft restore, field conversion, organisation
   isolation and permission rejection. Creation leaves assignments unchanged;
   the existing selector assigns active plans separately. Billing integration
   and live concurrency remain unverified/unimplemented.
   Existing-plan editing now has private native catalogue/detail/update routes.
   The native controller owns partial updates reconstructed from stored values;
   optimistic revisions and central actor receipts guard retries. Unknown
   stored features/limits block editing rather than being discarded. Native
   checks cover inactive plans, pagination, assignment counts, unchanged
   assignment IDs/slug/default, stale edits, actor revocation and audit rollback.
   These editing routes now connect through the stored-superadmin Tech4Learn
   gateway and shared plan form. The catalogue includes inactive plans, and the
   editor shows the number of assigned organisations affected by a change.
   Scoped drafts retain the original revision and exact pending request; stale
   edits require reopening, while uncertain writes lock editing until retried.
   HTTP checks cover response projection, malformed settings, injected actors,
   repeated requests and revoked access. Browser fixtures cover stored values,
   repeated draft recovery, stale recovery and organisation isolation; creation
   remains covered by its regression fixture. Native plan status controls catalogue availability; it is not an
   organisation access-revocation mechanism.
   The connected Nest/native fixture also now creates a plan through both real
   controllers, retries without a duplicate or second audit invocation, finds
   it in the catalogue and verifies that existing plans and organisation
   assignments remain unchanged. It also reads and updates the created plan,
   checks exact retry produces one audit invocation, preserves untouched fields
   and the default flag, rejects stale revisions and lists the inactive result.
   Its transport is isolated PHP CLI with
   synthetic databases, not production HTTP middleware.
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
   Multilingual passage delivery now resolves the selected exam language before
   the source question language, with a shared fallback used by both the student
   payload and protected image reader. Previously the adapter always selected
   the source language for passages. Native normalisation now handles passage
   formulas consistently with question wording. Isolated tests cover translated
   wording/diagrams, denial of non-displayed language images, source and legacy
   fallback, foreign passage denial and submitted-attempt media closure.
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
