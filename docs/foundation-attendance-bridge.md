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
attendance and academic setup paths and their allowed methods are forwarded. Native CSRF and
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

The checked API/admin release and API migration 17 are deployed under the user's
subsequent explicit authorization; see the [live release record](live-foundation-release-20261007.md).
Laravel remains inactive: reviewed native migrations, its production environment
and database, and same-host routing must be configured before activation.
Canonical learner-to-native-student mapping,
student/mobile integration, browser/device acceptance, face calibration and
physical OMR recognition remain unfinished. These synthetic checks do not certify
all imported ExamElite features or production readiness. FLN remains deferred.

On 7 October 2026, npm run check passed 99 API tests, workspace typechecks and builds. The complete native PHPUnit suite passed 261 tests / 1,153 assertions. PHP boundary checks, the connected capture/review test, and a real local Laravel HTTP login/workspace/directory check passed. A synthetic local exam is available in the ignored pilot database. Initial browser interaction checks now pass as detailed below; broader acceptance and mobile device checks are still pending. Ordinary gateway timeouts are eight seconds; optional analysis gets 45 seconds to accommodate the existing bounded provider call.

Native API result summaries follow the stored result_after_finish setting. Hidden results omit scores and outcomes. Native web/guest finalization locks the parent attempt and answer records, preserves existing finalized grades on retry, and sends result notifications outside the grading transaction. The exam regression covers scheduled-start denial, hidden API summaries and delayed browser submission after a teacher correction.

## Browser verification — 7 October 2026

The right-panel browser signed in through the mapped native login and loaded both native exams and attendance. The attendance directory, synthetic learner review, confirmation, saved draft clearing, daily totals and history count were verified in the UI. A deliberately incomplete synthetic record exposed a rendering crash; review now rejects missing evidence/snapshot collections with a clear error while retaining the directory. No real learner photo/location or browser camera/location permission was used. The native active-exam counter now includes exams without an end date while excluding expired and foreign-tenant exams. Six focused native regression tests (48 assertions) and npm run check (99 API tests, typechecks/builds) passed for these changes. This is initial browser acceptance, not a complete mobile or production release.

## Embedded academic setup

The attendance workspace now includes a permission-filtered Classes and sections
view using the existing AcademicStructure, GroupedMenu, DraftForm and table
components. It can create and archive years/classes, and create, edit and archive
sections for accessible existing centres. Section actions return to attendance
with that section selected. Scoped drafts remain inside the mapped user and
organisation boundary. Archived sections remain visible through the academic
view filter while capture options exclude them.

The native gateway allows centre reads/creation, exact centre edits and
location/approval/archive actions, plus exact academic-year/class/section
paths with bounded methods. API permission, scope and version checks remain
authoritative, and both mappings are rechecked before returning a response.
It does not expose platform management or arbitrary API routes. Explicit native
student links still require the separately managed setup;
automatic onboarding remains pending.

Synthetic connected checks cover scoped academic reads and a year creation through
the PHP gateway followed by attendance capture, review and history. Production
activation and actual browser acceptance remain pending. The matching scaffold
check passed all 111 API tests, workspace typechecks and both admin builds;
native gateway boundary and connected PHP-to-Nest checks also passed.

## Embedded centre setup

A permission-filtered Centres view now supports creating/editing centres,
approving locations separately and archiving while retaining history. It reuses
DraftForm, DirectoryTable, RecordStatus and CentreLocation, with drafts scoped
to the mapped user/organisation. Group-scoped staff have no centre write controls;
the API independently enforces centre permissions and current scope.

New or changed coordinates remain unapproved until the explicit approval action.
The gateway forwards only exact centre paths and their allowed HTTP methods.
Synthetic connected checks passed creation, approval and approval invalidation
after a location change, followed by the attendance capture/review journey.
These checks do not establish production or browser/device acceptance.

The centre setup change passed `npm run check` (111 API tests, workspace
typechecks and builds), native gateway boundary checks, and the connected
PHP-to-Nest setup/attendance journey. No mobile sources or live configuration
were changed. Actual browser/device and production acceptance remain pending.

## Embedded attendance learner setup

The Attendance learners view reuses the existing Learners and StudentPhotos
components for scoped enrolment, profile edits, custom fields/imports, transfer,
archive and photo permission/setup controls. Academic section actions can open
that section's learner directory. These remain canonical attendance records;
native exam students are not created or matched automatically. Reviewed explicit
links remain a separate platform operation.

The gateway permits exact learner, field, import and photo paths with bounded
methods. Private photo responses retain no-store and current identity/scope
checks. Face checks use a bounded extended timeout; configuring and calibrating
the actual engine remains separate from displaying these controls.

Synthetic connected checks cover learner creation, enrolment, scoped directory
and detail, photo metadata and archive, followed by capture/review. Gateway tests
cover foreign-organisation denial, unsupported methods/paths and private photo
transport. Actual camera/photo/device and production acceptance remain pending.

The learner interface change passed `npm run check` (111 API tests, workspace
typechecks and builds), native gateway boundary checks and the connected
PHP-to-Nest setup/attendance journey. Original ExamElite records, production
learner data and live configuration were not changed.

## Synthetic browser milestone

The ignored local SQLite fixture now has a separate synthetic native tenant and
staff matching the synthetic API pilot. `php tests/prepare-native-browser-pilot.php`
is CLI-only, requires the fixed ignored local SQLite installation and local
environment, refuses occupied conflicting IDs, and does not repair existing
identities. No source or production ExamElite database is involved.

`node tests/foundation-browser-pilot.mjs --tenant-browser` serves the matching
API on loopback port 8011 with origin `http://two.localhost:8001`. Configure that
ignored native fixture's attendance API URL accordingly. Resolve `two.localhost`
to loopback in the owned test browser; the .localhost name provides the secure
context needed by browser crypto/camera APIs. This does not relax production
HTTPS requirements or change a user's normal browser settings.

Actual headless Chromium checks passed synthetic tenant sign-in, the attendance
workspace, centre form save through the native gateway, academic setup view and
populated learner directory without runtime errors. Both default and tenant
pilot identity checks passed. Camera/photo capture, native device checks and
production authenticated acceptance remain pending.

`node tests/foundation-browser-setup.mjs` repeats the owned headless Chromium
check against the fixed synthetic `two.localhost:8001` fixture, using the ignored
local pilot credentials and prepared local Playwright dependency. It checks
secure context, tenant sign-in, academic view, populated learner directory and
saving a fresh enrolment through the native gateway, and rejects runtime errors.
It writes its synthetic screenshot only under ignored `.local`. The matching
API pilot must already run on loopback port 8011. Browser enrolment save passed;
this does not create a native exam student or perform automatic identity linking.

The browser runner's optional `--capture` mode uses Chromium's virtual camera
and a synthetic geolocation at the fixture centre. It passed opening the live
preview, taking a JPEG with location and submitting through the native gateway
for teacher review. It does not open a real camera, prove device GPS accuracy or
confirm learner marks. Repeated runs use the fixture's normal daily-session and
photo-count rules; start a fresh synthetic API pilot for a fresh capture run.
Real camera, recognition calibration, native devices and live acceptance remain
pending.
