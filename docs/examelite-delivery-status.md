# ExamElite integration: current delivery status

Updated 17 September 2026. This is the current summary; the central-content and
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
| Translated wording | Review, edit, refresh request and approval; retain existing opaque images while editing text/formulas. New translated-image upload remains unfinished. |
| Exam management | Native settings, question assembly, sections, subject timers, activation and result visibility for organisation and central owners. |
| Student and marking pilot | Same-domain scoped entry, staff-issued grants, start/resume, answer save/retry, submit, staff marking and published result history. |
| Documents | Native PDF request/status/approved download adapters and screens. Actual rendering/worker completion is not yet verified. |

Latest full scaffold check: 86 tests passed, typechecks and builds passed.
Native adapter suites and synthetic React checks provide additional coverage.
The connected pilot exercises Nest HTTP and isolated native controllers. It
does not establish production Laravel middleware/TLS, concurrency, device
compatibility or live worker operation.

## Remaining work and completion evidence

These are outstanding deliverables, not enabled or completed features. Work
should proceed in coherent workflow batches rather than treating each small
adapter milestone as a release.

1. **Complete question media workflows.** Add translated-image upload and
   replacement through native ownership/revision checks, address upload orphan
   reconciliation, and inventory remaining native formula/markup formats.
   Verify organisation and central authoring, private previews, retries and
   unchanged copies together.
   The native translated-image action now uses the owned paper's saved
   translation, source-owner file namespace, exam/review revisions and request
   ledger. Upload, replacement and reference removal invoke the native
   question-language controller and invalidate translation approval/documents.
   Organisation/central tests cover private review references, repeat requests,
   stale/foreign denial and cleanup after failed native saves. Private route,
   gateway and UI wiring still remain; this is not an exposed upload workflow.
2. **Verify native background processing.** Exercise PDF rendering and AI
   translation completion in isolated local worker fixtures, including failure,
   approval invalidation and repeat requests. A queued request alone is not a
   completed document or translation.
3. **Complete the agreed module coverage.** Finish platform module/provider
   catalogue and plan assignment, paid-package workflows, OMR, student answer
   file/media uploads and remaining reports/portal workflows. Inventory actual
   native capabilities before exposing controls; retain native engine ownership.
   Central language deletion needs reference protection before exposure.
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
