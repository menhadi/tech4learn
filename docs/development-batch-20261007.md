# Admin and exam development batch — 7 October 2026

The requested 15 work items were completed locally. The scope follows the
[product blueprint](product-blueprint.md), [academic workflow](registration-workflow.md)
and [shared UI template](ui-template.md): reduce teacher/field-staff effort,
retain organisation isolation and scoped drafts, and use ExamElite for exam
delivery/marking. No new infrastructure, dependencies, engine or live migration
was introduced. Full exam release completion is not claimed.

| Item | Completed change |
| --- | --- |
| 1 | Organisation page search matches only the permitted menu entries supplied to the shared component. |
| 2 | Clear search and a useful no-match state; selection clears the search without changing saved form drafts. |
| 3 | Search reveals matching submenus immediately, including group-name matches. |
| 4 | Distinct line icons for academics, exams and learning. |
| 5 | Keyboard Skip to workspace link and focusable main content. |
| 6 | Platform/organisation menu selection moves focus to the current page heading. |
| 7 | Semantic breadcrumb navigation with the current page announced. |
| 8 | Mobile Escape closes the portal-rendered menu and restores focus to its toggle. |
| 9 | Platform navigation and organisation changes close the mobile menu; organisation changes reset page/search state. |
| 10 | Failed exam-access reads offer an explicit reload action. |
| 11 | The exam workspace opens a permitted tool instead of a restricted question bank; taking still requires exam access. |
| 12 | Exam tool buttons wrap on narrow screens and announce the selected page. |
| 13 | Coverage and programme copy distinguish implemented file answers/manual OMR/certificates from unfinished release work. |
| 14 | Exam-link catalogue retry, loading/empty states and deduplicated pagination; failed lookup blocks button and submit-handler writes; expired grants have no revoke action. |
| 15 | Student exam-link selection reuses centre/year/class/section controls and the existing authorised learner directory, resetting pagination and clearing old rows during loading. |

## Verification

- `npm run check`: all workspace typechecks, 94 tests and production builds passed.
- Final admin production build passed after the portal keyboard fix; the existing bundle-size warning remains.
- `admin-exam-usability.html` passed with synthetic transport. Its first run exposed a fixture mount-timing race; the fixture now waits for the new screen before selecting a student.
- The real shell preview passed desktop and 390px mobile checks: submenu expansion stays open, Escape closes/returns focus, page selection focuses the heading, organisation switching clears search/page state, and the skip link focuses main content. Mobile page width equalled content width (375px excluding the scrollbar).
- The formerly blocked `exam-answer-files.html` passed upload/download-URL, extraction/language/retry, explicit save and lock checks. A stale unsaved notice after successful saving was fixed; the fixture now checks the saved notice too.

The browser fixtures use synthetic records and mocked HTTP, not live learners.
Native integration checks and gateway isolation tests remain separate evidence.
The connected browser/native answer-file journey, production middleware/TLS,
real OCR documents/languages, representative devices, media reconciliation and
the final deployment handoff remain open. Live deployment access is still
unavailable: the existing review account is read-only, and deployment SSH was
denied during the preceding push request.
