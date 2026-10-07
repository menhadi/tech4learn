# Native attendance bridge — first read boundary

The Laravel foundation can resolve a linked attendance identity and read the
existing API's daily attendance directory. It does not duplicate the attendance
engine, copy learner records or change either database's existing identities.
There is no new attendance capture/review UI or single sign-on in this slice.

## Authority and mapping

Explicit migration 17 adds `foundation_organisations` and `foundation_staff` in
PostgreSQL. There is one immutable native organisation → attendance organisation
link and one native staff → existing account link per organisation. Link creation
and activation/deactivation require a stored Tech4Learn platform superadmin,
use version checks for status edits and record audit events. Existing links cannot
be silently reassigned. No account lookup or merge uses email or name.

Management endpoints under `/api/v1/platform/foundation/organisations` support
GET/POST, PATCH `/:native`, GET/POST `/:native/staff` and PATCH `/:native/staff/:user`.
Organisation creation accepts `nativeOrganisationId` (decimal string) and
`organisationId` (existing UUID). Staff creation accepts `nativeUserId` (decimal
string) and `userId` (existing UUID). PATCH accepts `active` and the current
`version`. All writes retain the existing session, exact-Origin and JSON/custom
header checks. Native IDs must be verified in the intended Laravel installation
before explicitly linking them; these routes do not inspect a remote native DB.

The attendance context endpoint requires a current existing API session belonging
to the exact mapped staff account. Even an API superadmin cannot substitute for
another mapped account. Active link, membership, attendance permission, module
availability and centre/section scope are resolved from stored API records.
Native admin roles and numeric IDs are never attendance authority.

## Laravel read endpoints

Authenticated staff endpoints `/attendance/context` and
`/attendance/records?date=YYYY-MM-DD` use the native organisation and actor resolved
by the server. Client organisation/user overrides are ignored. The records endpoint
reads the API's existing first directory page and its counts; pagination, detail,
private photos, capture, correction and review adapters remain future work.

This initial bridge requires both a native Laravel login and the explicitly linked
existing attendance account's valid API session on the same host. It is **not SSO**.
Laravel preserves the opaque `t4l_session`/`__Host-t4l_session` cookies, which the
API owns and validates; Laravel's own session cookie remains encrypted. The token
is neither stored by the bridge nor placed in a URL, response or audit event.
Native student accounts are not admitted by these staff endpoints.

`ATTENDANCE_API_URL` is a fixed server environment value ending in `/api/v1`.
It is empty/disabled by default. HTTPS is required outside local development;
local HTTP is restricted to loopback. URL credentials, query strings, fragments,
redirects and request-provided destinations are refused. Reads have timeouts and
a one-MiB response limit; upstream errors never expose bodies or credentials.
Responses use `no-store`. Native access and the mapped API context are rechecked
after the directory read; revoked links or changed permissions/scope withhold it.

## Verification and deployment status

The HTTP regression test `apps/api/test/foundation.test.mjs` uses an ephemeral
PostgreSQL engine with synthetic accounts and checks foreign identity denial,
immutable links, current superadmin authority, optimistic updates, membership and
module changes, scope changes, link revocation and session expiry/removal.

With the prepared ignored local dependency copy, run:

```powershell
php tests/foundation-attendance.php D:/tech4learn/.local/tech4learn-foundation
node tests/foundation-attendance-connected.mjs D:/tech4learn/.local/tech4learn-foundation
```

The PHP test checks cookie ownership, bounded transport, invalid dates/destinations,
identity-response mismatch and post-read revocation. The connected test starts an
ephemeral local Nest API, invokes the real PHP/Guzzle bridge and confirms it returns
only the linked organisation's synthetic attendance record, including when the
request supplies a foreign organisation UUID. No real learner data is used.

No production migration or deployment has been run. A checked user-run deployment
must apply migration 17 to the existing API database and configure the native app
and same-host routing/session setup before enabling this bridge. The native DB
continues to own exams; PostgreSQL continues to own learners and attendance.
Mapping administration UI, one-login session exchange, learner identity mapping,
full attendance UX, FLN and mobile integration remain unfinished.

On 7 October 2026, `npm run check` passed 98 tests with no failures, workspace
typechecks and builds. PHP bridge/tenant checks and the real PHP-to-Nest read check
also passed. The existing admin bundle-size warning remains. Mobile code was not
changed; these checks do not establish a mobile device release.

An unrelated inherited source gap was found during the native route-list check:
five large question-import routes reference `QuestionLargeImportController`, which
is absent in both the upstream working copy and the imported source. No replacement
exam engine or successful placeholder was added. Full native route/feature acceptance
must resolve this upstream gap; the two new attendance routes are checked separately.
