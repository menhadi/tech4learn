# Native attendance integration

The Laravel `/attendance` page mounts the existing React attendance interface;
Nest/PostgreSQL remains the owner of attendance, canonical learners, evidence and
review history. Laravel owns native exams. No databases or real identities have
been merged.

## Authority and sign-in

API migration 17 adds explicit, revocable `foundation_organisations` and
`foundation_staff` mappings. A stored API platform superadmin creates links under
`/api/v1/platform/foundation/organisations`; organisation/staff activation edits
require the current version and are audited. Native IDs must be verified in the
intended installation. Links are immutable, with no email/name merging.

When `ATTENDANCE_API_URL` is configured, native staff login verifies the mapped
API account's email/password and establishes both sessions. Native membership
and API membership remain separate stored checks. Unmapped accounts cannot use
this mode; verify mappings before activation. With the connection disabled, native
login retains its original behavior. Native student sign-in is unchanged.

The API session remains a host-only HttpOnly cookie. Laravel preserves the opaque
API cookie while encrypting its own cookie. Logout revokes the API session and
clears both browser sessions; an unavailable API still clears the browser cookie.
A token is never returned to frontend JavaScript or placed in URLs.

## Attendance interface and gateway

`/attendance/context` and `/attendance/records?date=YYYY-MM-DD` remain available.
The mounted interface uses `/attendance/api/organisations/:uuid/attendance/...`
for directories, private photos, capture, extra photos, review, correction,
history, policy and optional analysis. Groups retain their separate permission.
The shared attendance components and user/organisation draft scope are reused.
Build with the admin workspace build; generated assets are ignored under
`platform/public/attendance-ui` and must be included in deployment packaging.

Every gateway request checks current native authority and the exact mapped API
account. Client organisation UUIDs must match the resolved link. Only approved
attendance/group paths and GET/POST/PATCH methods are forwarded. Native CSRF and
existing API Origin/JSON/custom-header checks protect writes. JSON values including
empty objects are preserved. Both systems' current module, permission and location
scope checks remain in force. Context is rechecked after the remote operation;
changed access withholds responses. Writes can already have committed before that
recheck; existing idempotency and review versions protect retries.

The API destination is fixed server configuration ending `/api/v1`, disabled by
default. HTTPS is required outside local development; local HTTP is loopback-only.
Redirects, URL credentials and request-supplied destinations are refused. Requests
and responses are bounded to one MiB, with connection/response timeouts. Private
photos and JSON use no-store. Unexpected upstream failures are sanitized.

## Local verification and remaining acceptance

`npm run check` checks the scaffold. With the prepared ignored dependency copy:

```powershell
php tests/foundation-attendance.php D:/tech4learn/.local/tech4learn-foundation
node tests/foundation-attendance-connected.mjs D:/tech4learn/.local/tech4learn-foundation
php D:/examelite/vendor/bin/phpunit -c D:/tech4learn/.local/tech4learn-foundation/phpunit.xml --filter FoundationExamJourneyTest
```

The connected synthetic test verifies mapped password login, foreign-organisation
exclusion, capture, submission, review/history and session revocation against a
real local Nest API. Native exam tests cover CSV import, translation storage,
manual authoring, student attempt/submission, answer-key hiding, submitted-answer
locking, subjective pending grading and native membership revocation. Memory-only
PHPUnit database settings prevent these tests touching a persistent database.

Missing large-import and content-normalization code was recovered from original
ExamElite Git history; `platform/recovered-source.json` records provenance. Matching
normalization routes/command options are restored. The import worker runs in its
submitting tenant and rechecks native rights before writing each batch.

No production deployment or migration has been performed. A user-run release must
apply API migration 17, reviewed native migrations, build assets and configure the
same-host gateway before activation. Canonical learner-to-native-student mapping,
student/mobile integration, browser/device acceptance, face calibration and
physical OMR recognition remain unfinished. These synthetic checks do not certify
all imported ExamElite features or production readiness. FLN remains deferred.

On 7 October 2026, npm run check passed 99 API tests, workspace typechecks and builds. The complete native PHPUnit suite passed 261 tests / 1,153 assertions. PHP boundary checks, the connected capture/review test, and a real local Laravel HTTP login/workspace/directory check passed. A synthetic local exam is available in the ignored pilot database. Initial browser interaction checks now pass as detailed below; broader acceptance and mobile device checks are still pending. Ordinary gateway timeouts are eight seconds; optional analysis gets 45 seconds to accommodate the existing bounded provider call.

Native API result summaries follow the stored result_after_finish setting. Hidden results omit scores and outcomes. Native web/guest finalization locks the parent attempt and answer records, preserves existing finalized grades on retry, and sends result notifications outside the grading transaction. The exam regression covers scheduled-start denial, hidden API summaries and delayed browser submission after a teacher correction.

## Browser verification — 7 October 2026

The right-panel browser signed in through the mapped native login and loaded both native exams and attendance. The attendance directory, synthetic learner review, confirmation, saved draft clearing, daily totals and history count were verified in the UI. A deliberately incomplete synthetic record exposed a rendering crash; review now rejects missing evidence/snapshot collections with a clear error while retaining the directory. No real learner photo/location or browser camera/location permission was used. The native active-exam counter now includes exams without an end date while excluding expired and foreign-tenant exams. Six focused native regression tests (48 assertions) and npm run check (99 API tests, typechecks/builds) passed for these changes. This is initial browser acceptance, not a complete mobile or production release.
