# ExamElite integration discovery

Status: local source inspection completed on 13 September 2026. No live endpoint was called, no ExamElite file was edited, and no integration is enabled in Tech4Learn.

Source: local ExamElite checkout, HEAD `02f62b9e7553c7e05ae3601bd67f271061c152fc`. `app/Http/Controllers/Students/ApiMyExamController.php` has existing uncommitted changes; its observed behaviour must be checked against the deployed version before relying on it. Source inspection does not prove live compatibility.

## Existing student API

Paths have the `/api` prefix from `app/Providers/RouteServiceProvider.php`. Routes below are declared in `routes/api.php`.

| Operation | Method and path | Observed contract |
| --- | --- | --- |
| Sign in | POST `/student/signin` | `login` and `password`; returns `token` and `student`. Resolves organisation from the request host. Updates last login and revokes previous student tokens. |
| Student profile | GET `/student/me` | Authenticated student profile controller; not an organisation directory. |
| Available exams | GET `/student/my-exams` | `success`, `data.pendingExam`, `data.performanceStats`, `data.purchasedPackages`, `data.generalExams`. Student-specific access, not an admin catalogue. |
| Exam detail | GET `/student/exam-details/{id}` | `exam`, `total_marks`; uses the controller's accessible-exam query. |
| Remaining attempts | GET `/student/check-attempts/{id}` | `attempts_left` can be a number or the string `Unlimited`. |
| Start/resume | POST `/student/exam/start/{id}` | Existing ExamElite operation; request/response validation still needs inspection. |
| Save answer / submit | POST `/student/exam/save-answer`, `/student/exam/submit` | Existing ExamElite operations; do not reimplement their scoring or lifecycle in Tech4Learn. |
| Results list | GET `/student/results` | `success`, aggregate `stats`, Laravel-paginated `results` (20 per page); optional `status=passed` or `failed`. Lists finished attempts belonging to the authenticated student and tenant. |
| Result detail | GET `/student/results/{id}` | `success`, `data.result`, subject/question reports and computed statistics. May write a missing computed report; not a strictly read-only inspection endpoint. |

Authentication uses the Sanctum `student-api` guard (`config/auth.php`). Protected API routes also use `EnsureStudentTenant`, which requires the authenticated student's organisation to match `Tenant::hostId(request host)`. A Tech4Learn organisation ID cannot be substituted for this relationship.

Detailed results call `ResultHelper::computeResultDetails` when no report exists; that helper calls `ExamResultDetail::create`. Do not probe this GET under read-only live access. Sign-in, sign-up, start, submit and finalise are also mutations.

## Missing connector contract

The inspected student API route file does not provide an organisation-level service credential, external learner-ID mapping, admin exam creation API, or incremental result synchronisation contract. The web administration routes are not a substitute for that contract. Other integrations may exist elsewhere; these capabilities are not yet verified.

The next implementation slice is a tenant-scoped ExamElite connector contract for one section's assigned exams and summary results. Before activating it:

1. Bind each Tech4Learn organisation to an explicitly authorised ExamElite organisation and host. Keep host configuration server-owned.
2. Establish a limited integration credential or delegated session flow supported by ExamElite. Do not collect student passwords to impersonate a whole class; current sign-in would also revoke their other tokens.
3. Map stable learner UUIDs to ExamElite student IDs within that organisation. Names, photos, email matches and sequential IDs are not authorisation.
4. Verify assigned exam listing and a strictly read-only summary-result feed. Specify stable attempt IDs, pagination, result revisions, corrections and deletion behaviour before synchronisation.
5. Have ExamElite handle exam construction, delivery and marking. Tech4Learn stores authorised mapping and synchronisation metadata only.
6. Validate wrong-host credentials, cross-organisation IDs, revoked access, duplicate delivery, corrected results and interrupted pagination using synthetic local fixtures before user deployment.

Do not add guessed remote routes or show a working connection in the UI until this contract is implemented and checked. Group-photo attendance testing remains deferred by the user and is separate from this integration work.
