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
| Feature controls | Exams module and five native feature groups; revisioned organisation controls and current stored permissions. Commercial plans remain unfinished. |
| Central distribution | Share questions to organisations and pull organisation questions into central ownership as independent copies. No student/result sharing. |
| Questions and classifications | Native question creation/editing, taxonomy, central language creation/editing, organisation language enabling, free packages and guarded category deletion. |
| Source media and wording | Raster upload/replacement/removal, protected previews, supported MathML replacement and literal surrounding-text edits/appends. Arbitrary native markup is not fully supported. |
| Translated wording | Review, edit, refresh request and approval; retain existing opaque images while editing text/formulas, including subjective model answers. Saved translated questions support raster upload, replacement and reference removal. |
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
   byte exclusion and missing-translation gating. Translated exam images remain
   outside these question controls.
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
   Artifact bytes remain synthetic; actual rendering and queue transport remain
   unverified.
   Native translation completion now also has isolated checks for ready state,
   manual versus automatic approval, repeat completion, contended locks and
   injected feature denial. Provider access is explicitly forbidden in that
   fixture; AI generation, real plan evaluation and queue transport remain
   unverified by it.
3. **Complete the agreed module coverage.** Finish platform module/provider
   catalogue and plan assignment, paid-package workflows, OMR, student answer
   file/media uploads and remaining reports/portal workflows. Inventory actual
   native capabilities before exposing controls; retain native engine ownership.
   A private native capability reader now inventories the installed engine's
   plan feature keys and evaluates the mapped organisation's entitlements using
   native `SaasAccess`. It reports the five workspace restrictions separately,
   omits unknown plan attributes and never creates an organisation or login
   ticket. Isolated checks cover plan changes, expired subscriptions and owner
   mapping. This is backend groundwork: a superadmin catalogue screen, plan
   assignment and provider management remain unfinished. A native entitlement
   must not be presented as an implemented Tech4Learn workflow.
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
