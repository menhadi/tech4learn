# Native production setup

For the local fixture that reuses installed upstream vendor libraries, run
`php tests/run-native-local-tests.php` (optionally with PHPUnit filters).
The guarded launcher loads the copied application's helpers and verifies source
paths before starting PHPUnit. Calling the upstream PHPUnit executable directly
loads upstream global helper functions first and cannot verify helper changes;
do not use that launcher for this fixture. The original installation is unchanged.

## Current acceptance boundary — 8 October 2026

Fresh organisation creation now saves a durable pending attendance onboarding
request atomically with native configuration/audit. Migration `2026_10_08_000005`
is checked locally and has not been applied live. Targeted creation checks passed
9 tests / 37 assertions, including rollback on request-write failure. Requests
contain no credentials or learner payload. The local delivery consumer is wired
to fresh creation and a protected retry action, as described below.
Existing organisations are not automatically adopted or queued by this migration.
The API now exposes `POST /api/v1/platform/foundation/attendance-onboarding`
for a canonical authenticated platform session, accepting a native organisation
ID and display name. It rechecks stored superadmin authority and reuses the
fresh-companion transaction, including realm/non-adoption checks and exact retry.
`npm run check` passed all workspace typechecks, 111 API tests and API/admin/
embedded attendance builds. The native delivery/retry consumer and HTTP connected
onboarding acceptance was subsequently checked over local HTTP: canonical password
login, fresh creation, exact retry, anonymous denial, realm/mapping conflict denial
and stored-superadmin revocation all passed. The connected attendance workflow
also passed again, including scoped enrolment, capture/review/history and logout.
A native delivery service locks and processes one pending request, retains failed
attempts without private error text, and skips completed retries. Local failure/
retry checks passed. Fresh creation triggers delivery after commit, and active
pending rows expose a CSRF-protected, throttled retry action. Connected PHP-to-API
delivery passed: a fresh companion was created, the native request completed with
one attempt, and a completed retry was not resent. Live deployment remains pending.

The latest complete copied-source native run passed 342 tests / 1,528 assertions,
including answer-key withholding, scoped print 404s, unsigned solution denial
and unpublished-paper protection on the guest print route.
Student API exam details now withhold question content/options/translations and
explanations before attempt start, retaining subject metadata and total marks.
Details and attempt-count requests recheck active package access. Targeted checks
passed 10 tests / 95 assertions, including package revocation on API endpoints
and web details/attempt-count denial before activation and access after activation.
The web My Exams controller now shares the same course-access policy.
Subsequent API course-list checks passed 10 tests / 101 assertions. Purchased
courses now require completed orders in the student's organisation and active
packages; their nested exams are explicitly scoped to that organisation.
The list regression denies unscoped orders and excludes disabled packages.
Registered student and guest activation now recognise only completed orders.
Local authenticated HTTP checks verify that a pending paid order cannot satisfy
activation and that free activation creates one scoped completed student order
across retries. Direct student web instructions and web/API start or resume now
require an active same-organisation package and the student's completed scoped
order for packaged exams. Targeted HTTP checks deny pending, unscoped and guest
orders, deny disabled packages, permit completed owned orders and block resume
after package revocation. Payment provider and production acceptance remain pending.
Follow-up targeted checks passed 9 tests / 78 assertions: actual web start is
denied before activation, resumes the existing API-created attempt after activation,
and is denied after package revocation. API practice entry denies another student's
paper without creating an attempt and permits the paper's creating student.
Local browser acceptance after the package-access change passed an unpackaged
MCQ journey and a packaged MCQ journey, including sign-in, answer save, submission
and the two-mark result. A packaged subjective TXT journey also passed private
upload/extraction, submission, teacher marking and student-visible updated marks.
The guarded fixture accepts `--package` to seed a synthetic completed scoped
order; this verifies exam access with an existing order, not payment processing.
Query-only checks confirmed persisted completion, score and written evidence.
Subsequent targeted exam journey checks passed 12 tests / 89 assertions,
including package omission/invalid-package denial and actual signed draft/solution
rendering with tampered-link denial. Signed numeric print URLs render directly;
the public slug redirect otherwise discarded their signature and solution/render
parameters.
The question language endpoint now requires a matching open student/guest attempt
and withholds original/translated explanations. Fresh-schema guest attempt
identity fields are completed by `2026_10_08_000003`; this migration has not been
run live. Local HTTP checks verify matching identity, closed/unassigned-question
denial and foreign-language denial. Complete guest exam browser acceptance remains
unverified. Local guest MCQ save/completion HTTP checks pass, including matching
guest ownership, foreign-tenant denial and post-completion answer denial. Requests
without guest identity cannot read feedback or mutate a registered student's
attempt through guest routes. No guest schema or controller change is live yet.
Local HTTP acceptance also covers completed-order/active-package guest entry,
fresh attempt/stat creation, answer save and successful MCQ completion. Migration
`2026_10_08_000004` completes missing checkout order fields and optional billing
snapshots locally; it has not been run live. Pending orders do not grant access.
Subsequent targeted checks passed six guest/language HTTP tests / 52 assertions.
The actual free guest checkout route creates a tenant-scoped completed order,
opens instructions after its normal redirect, and keeps one activation on retry.
This verifies local free enrolment; paid provider and guest browser acceptance
remain separate unverified checks.
The scaffold `npm run check` passed 111 API tests, workspace typechecks and API,
admin and embedded-attendance builds. These are local synthetic checks.

| Area | Verified locally | Remaining acceptance |
| --- | --- | --- |
| Exams | Student browser MCQ submission/result; API resume, expiry, attempt limits and withheld answer keys | Authenticated production journeys, concurrent MySQL starts and broader exam feature acceptance |
| Written answers | TXT, DOCX, text PDF and printed English PNG upload/extraction; teacher publication and refreshed student score; private evidence retained | Handwriting, scanned PDFs, other languages, legacy DOC and production extractor runtime |
| AI marking | Simulated provider score bounds, retry exclusion and intervening teacher-grade preservation | Real provider quality, credentials and production concurrency |
| Attendance | Browser enrolment, virtual-camera capture, explicit confirmation and correction history; scoped HTTP tests | Physical camera/GPS, recognition calibration and authenticated production journeys |
| Fresh organisations | Independent configuration, required creation audit, failure rollback and staff without global admin grants | Automatic companion/staff/learner lifecycle and reviewed live identity setup |

Production completion still requires the retained administrator's explicit native
identity, checked schema updates, fresh organisation/staff/learner mappings,
verified private backup restoration and authorised legacy cleanup, bounded worker
configuration/scheduling, and reviewed Apache installation/rollback. The public
Node service remains active. Current `AGENTS.md` restricts assistant live access
to read-only, so production writes remain user-run. No local test or Git push
constitutes deployment or production acceptance. FLN/mobile remain later work.

The private PHP 8.4 pool, production environment and dedicated MySQL schema
are verified. Native accounts, identity mappings and Apache cutover remain
pending. The existing PostgreSQL attendance service remains authoritative
and operational. See the checked database milestone below.

`deploy/virtualmin/native-production.env.example` is a secret-free configuration
template. The completed environment belongs on the server outside Git, with
access restricted to the application account. Generate a fresh application key
and database password on the server; do not reuse the original ExamElite secrets.
The native database/account names are `tech4learn_exams`. Grant that account
access only to this database. Keep the existing PostgreSQL database unchanged.

The native attendance gateway requires HTTPS in production. Route `/api/v1/`
to the existing Node service before routing other requests to Laravel; use
`https://tech4learn.com/api/v1` as the gateway destination. A loopback HTTP
destination is allowed only in local development.

Before migration, verify the dedicated MySQL database is empty and retain a
private backup. Run native migrations explicitly as the application account,
never through a public endpoint. A fresh database does not gain a working
administrator from `DatabaseSeeder`; it also initially receives the upstream
`examelite.com` organisation domain. Both require reviewed native setup before
cutover. Do not promote an existing account or merge identities by email.

After environment/database setup, run:

```bash
php8.4 deploy/virtualmin/check-native-readiness.php /absolute/checked/release/platform
```

This CLI check parses the private environment without booting Laravel, refuses
unexpected database targets, and uses a read-only transaction to check applied
migrations, the canonical domain and an active native platform administrator.
It prints neither secrets nor learner records. Exit zero indicates these
prerequisites passed, not that the complete website is ready.

Still required before cutover: durable writable storage, reviewed organisation
and staff identity mappings, an appropriate platform sign-in path, background
worker/scheduler configuration, and actual exam and attendance acceptance checks.
Learner links must refer to verified native students and existing canonical
learners at the same organisation. Never copy production records into local
fixtures or infer links from names/emails. Retain the existing Node routing and
release for rollback until the replacement workflows pass.

Local verification of the readiness checker: PHP syntax passed, a missing
installation failed closed, and the local SQLite development installation was
rejected before any database connection. Its MySQL success path still requires
verification against the separately prepared native database.

## Restricted server access

`install-tech4learn-admin.sh` installs a root-owned Python helper and an exact
sudo allowlist for `tech4learn`: `status`, `prepare-database`, `restart-api`.
The helper accepts exactly one whitelisted action. It executes fixed commands
without a shell, ignores caller environment/Python imports, and accepts no
paths, SQL, credentials, configuration content or service names. Install only
checksum-verified copies in a root-only temporary directory.

Database preparation refuses an existing database, account or private setup.
It creates only `tech4learn_exams` and a local MySQL account restricted to that
database, plus a fresh encryption key and private environment at
`/etc/tech4learn-native/native.env` (root-owned, group-readable by Tech4Learn).
Secrets are neither printed nor committed. A partial database setup must be
reviewed by a server administrator; the helper never drops or resets databases.
Migrations run later as the application user. No production data or routing is
changed by permission installation.

This permission set cannot edit Apache, run arbitrary PHP/shell commands as
root, alter other sites or grant additional privileges. Apache cutover therefore
still needs a separately reviewed, user-run root installation after acceptance.
The original root-SSH authorization command should not be used.

Four local tests passed for rejected/extra actions, existing-database refusal,
existing-configuration refusal and dedicated SQL/configuration targets. Bash
installer syntax passed. Root installation and MySQL execution remain unverified
until the server administrator installs the checked helper.

### MySQL administrator authentication

The initial live preparation failed before any private environment or database
was created: the server rejects passwordless MySQL root authentication. The
restricted helper now reads only fixed administrator credential sources: a
root-owned `0600` `/etc/tech4learn-native-mysql.cnf`, or the root login/password
inside an equally protected `/etc/webmin/mysql/config`. Symlinks, non-root files
and group/public-accessible files are rejected. MySQL administrator secrets
remain in root-controlled storage and the fixed child-process environment;
they are never printed, passed as command arguments, or written to application
configuration. Caller environment variables are ignored. Missing/unusable
credentials stop setup without adopting existing databases or resetting passwords.
The sudo action allowlist is unchanged. Seven local boundary tests passed; the
updated helper must be installed by root before live preparation can be retried.

## Checked native database milestone — 7 October 2026

The restricted helper successfully created the dedicated database/account and
root-owned private environment after root installed its protected MySQL
credential file. No MySQL administrator password was exposed or copied into
application configuration. The native account connected to an initially empty
`tech4learn_exams` database; PostgreSQL attendance data was not changed.

All 241 native migrations completed in pinned release
`22e35a3e9f74846907f71be68cf1c5f13de895b4`. Fresh MySQL setup exposed and required
fixes for unscheduled exam timestamps, an AI settings ordering dependency on
a missing currency column, optional legacy organisation phone, the missing
student email-verification timestamp, exam-monitor timestamps/partial retry,
and admission foreign-key names exceeding MySQL's identifier limit. The
admission retry rebuilds only its empty, unconstrained partial resource table
and refuses to drop a table containing records. Each code fix was checked
locally and pushed before its pinned schema retry. No SQL mode relaxation or
original ExamElite database changes were made.

Validated private backups were retained before initial installation and schema
retries, starting at
`/home/tech4learn/private-backups/native-initial-20261007T104014Z`. The final
pre-retry backup/logs are in
`/home/tech4learn/private-backups/native-retry-20261007T105129Z`. Backups and
credentials remain on the server outside Git. Production storage is in
`/home/tech4learn/native-shared/storage`; a mode-0600 shared native environment
points there and the pinned release references it through `.env`.

The single initial native platform organisation was branded Tech4Learn and
bound to `tech4learn.com` in a guarded transaction that required zero users,
students, memberships and configuration records. Its previous configuration
was saved privately. Initial presentation settings use Asia/Kolkata. No native
staff/student identities, API mappings or question content were copied or seeded.

The full native suite passed 267 tests / 1,171 assertions. Fresh-schema
regressions cover setup/retry, retained source records, unverified existing
students and refusal to drop populated partial admission tables. Private
Laravel HTTP-kernel checks returned 200 for `/login` and sign-in redirects for
`/exams` and `/attendance`. Readiness checks passed production configuration,
all migrations and canonical domain; they still block on the missing native
platform administrator. Public API health remained `ok`. These kernel checks
do not establish an Apache/FPM browser cutover or authenticated acceptance.

Next: implement and verify an explicit platform-administrator sign-in mapping
without binding the global platform organisation to a real attendance tenant;
then reviewed tenant/staff/student provisioning, scheduled jobs and actual
exam/attendance acceptance. Apache remains on the existing Node website.
The restricted helper has no Apache edit privileges; the eventual checked
cutover still requires a separately scoped root installation.

## Administrator boundary hardening

The upstream platform-flag migration previously promoted a user by a hardcoded
email address. That promotion has been removed from the copied Tech4Learn
source. Installing or retrying the flag migration now grants no authority and
preserves explicitly assigned administrator flags. Regression tests cover the
former matching email, an ordinary account and an explicitly assigned admin.
This changes fresh setup behavior; it does not revoke or alter existing live
accounts, and it does not complete administrator provisioning or sign-in.

The next sign-in implementation needs a distinct global platform identity
realm. Do not map native platform organisation 1 to one of the actual attendance
tenants just to enable login. Proposed implementation, still pending: an explicit
immutable link between a verified native platform administrator ID and a stored
canonical superadmin UUID, with revocable/versioned activation; API password
verification followed by stored-role and link checks; native stored-admin checks;
and current API-role/link/session validation on every privileged native request.
The platform realm must be mutually exclusive with tenant organisation mappings,
including concurrent creation, and must grant no implicit attendance tenant
context. Rejected sign-in must revoke its provisional API session. Tests must
cover ordinary-user denial, cross-realm links, revocation/demotion after sign-in,
version conflicts, immutable mapping and private session handling. All of this
is planned until implemented and checked.

## Platform identity API — local implementation

API migration 19 adds a native realm registry and explicit platform-staff links.
Existing native organisation mappings register as organisation realms. Database
triggers serialize realm registration and reject platform/organisation aliasing,
even when creation is concurrent or SQL bypasses the service. Registered realm
kinds cannot change. The migration copies no accounts, learners or exam data and
must be applied explicitly by the management CLI before deploying this API slice.

Stored canonical superadmins can list/create links at
`/api/v1/platform/foundation/platforms/:native/staff`, and change active status at
`/:user` using the current version. A target must itself be a stored superadmin.
The native IDs require prior verification in the intended installation. Account
links cannot be reassigned; duplicate identities conflict. Changes are audited
without attributing the global platform to a real tenant. Role reads take shared
locks inside transactions so demotion cannot race an in-progress authority check.

`POST /api/v1/foundation/auth/platform/login` verifies the existing API password,
then the exact account's active global mapping and current stored superadmin role.
A successful reply contains only native organisation/user IDs, canonical user UUID,
link version and `realm: platform`; the API session uses the existing private
HttpOnly cookie. Failed mapping/role checks revoke the provisional session. Client
role/native-user claims never select the returned identity.

`GET /api/v1/foundation/platforms/:native/staff/:user/identity` requires the exact
mapped account, active link and current stored superadmin role. Role demotion or
link deactivation immediately denies subsequent reads using existing sessions.
No attendance tenant context is created by this mapping. Native Laravel login,
privileged-request revalidation and reviewed provisioning still need to consume
this API before administrator sign-in is a completed feature. Migration 19,
production global mappings and this API slice have not yet been activated live.

Verification for the local API slice: `npm run check` passed all typechecks,
workspace builds and 106 API tests. The separate realm-upgrade test passed,
preserving existing tenant link versions and disabled states and rejecting
realm reassignment/orphaning. The real connected PHP-to-Nest synthetic test
also passed mapped login, scoped attendance capture/review/history, learner
identity resolution and logout with migration 19 present. No production
accounts, mappings, migrations or routing were changed by this verification.

## Native platform sessions — checked locally

The copied Laravel primary site's staff sign-in now uses the separate platform
login endpoint. It requires both an active canonical superadmin link and an
existing active native user with an explicitly assigned platform-admin flag.
Tenant sign-in cannot use that native global-admin account as ordinary staff.
Identity metadata is validated and reduced to native IDs, canonical UUID,
version and realm before it is stored in the private native session. Invalid
metadata or a rejected native user revokes the provisional API session. No
password is copied to Laravel and no email-based account matching occurs.

The web middleware checks global administrator identity before and after
controller execution against the live API session/link/role and fresh native
user/primary-organisation records. Missing metadata, changed versions, role
demotion, deactivation or expired sessions prevent release of the protected
response. Responses use no-store. POST logout remains available after native
role revocation, allowing both sessions to be cleared. Student sign-in and
ordinary tenant staff workflows retain their existing separate boundaries.
Native API keys and non-web authentication channels still require their own
review; this milestone verifies the web administrator session boundary.

All 278 native tests / 1,195 assertions passed, including ten new platform
session checks. The connected local PHP-to-Nest fixture passed platform password
sign-in, private session metadata, protected response checks, mid-controller
link revocation and API logout. The same fixture still passed ordinary tenant
attendance capture/submission/review/history and learner identity resolution.
Fixtures are synthetic, isolated and ephemeral. Production native users and
platform links have not been created, API migration 19 has not been applied
live, and public routing remains unchanged.

Next: prepare and check explicit, idempotent initial native administrator
provisioning against the existing canonical superadmin UUID, then stage the
checked API/native releases and configure real tenant mappings. Do not attach
the global platform realm to an attendance tenant for convenience. Production
browser acceptance and the separately scoped Apache cutover remain outstanding.

## Read-only platform identity deployment gate

Before cutover, run `deploy/virtualmin/check-platform-identity.mjs` with the
reviewed native primary organisation ID as its sole argument, using Node 24
and the existing private API environment. It uses a read-only repeatable-read
transaction with a five-second statement timeout. It checks migration 19,
an explicit platform realm, absence of an attendance-tenant alias, and an active
mapping to a currently stored canonical superadmin. Missing provisioning,
deactivated links and demoted accounts block readiness. Output contains only
check labels; no account identifiers or credentials. This command performs no
provisioning, migrations or repairs.

The native read-only readiness script additionally requires exactly one active
primary platform realm and verifies that it belongs to tech4learn.com. Run both
gates; neither proves matching native staff identity or browser acceptance.
The isolated readiness test covers missing schema, unprovisioned platforms,
tenant-realm confusion, link deactivation and account demotion, including a
successful read inside a read-only transaction. These gates are checked locally;
production provisioning and cutover remain outstanding.

The repeatable local API browser pilot is now in
`tests/foundation-browser-pilot.mjs`. It reads only ignored synthetic local
credentials and creates an ephemeral in-memory database. Platform realm/user 1
is separate from attendance realm/user 2; it no longer assigns attendance to
the native primary platform. `--check` uses an ephemeral loopback port and
verifies both password sign-ins, tenant attendance context and denial of a
global attendance alias before exiting. Without that flag it serves the local
pilot on port 8010. The native synthetic database must contain matching reviewed
IDs, and old browser sessions must be signed out before using the new setup.
This fixture does not provision production accounts or change native records.

## Explicit platform administrator link command

The checked API management CLI now supports:

```text
node dist/manage.js foundation-platform-admin CANONICAL_SUPERADMIN_UUID NATIVE_PRIMARY_ORG_ID NATIVE_USER_ID --confirm-reviewed-native-identity
```

Run only through the trusted deployment account with its existing private API
environment, after backing up the database and independently reviewing the
native primary organisation and active native platform-admin user. The command
requires migration 19 and an account that is already a stored superadmin. It
does not promote an account, create a native user, copy a password or match email.
The native numeric IDs must belong to this Tech4Learn installation; the API
database cannot verify records in the separate MySQL database.

Creation and audit are atomic under a provisioning lock. Retrying the exact
active link leaves its version and audit history unchanged. A revoked link,
reassigned account, duplicate account identity or attendance tenant realm blocks
the command; it never silently reactivates or repairs a mapping. Output contains
only the outcome. The audit identifies the selected existing superadmin and
marks the source as server_cli; this is a trusted server operation, not evidence
of a browser password sign-in. Existing authenticated management endpoints remain
available for later reviewed status changes. No production provisioning has
been performed by implementing this command.

Verification: `npm run check` passed workspace typechecks/builds and all 108 API
tests, including explicit provisioning and the existing realm-upgrade checks.

## Initial native administrator provisioning

The additive native migration
`2026_08_01_000000_create_foundation_platform_administrators` records the reviewed
canonical UUID, native primary organisation and native user together. Native
schema now includes 242 migration files; the live installation still has its
previous 241 applied migrations until an explicit checked deployment.

```text
php artisan foundation:platform-admin CANONICAL_SUPERADMIN_UUID --confirm-reviewed-canonical-superadmin
```

This trusted operator command requires the configured API bridge, exactly one
active primary platform and, for initial creation, an empty native users table.
It locks the primary organisation, creates one active native platform admin with
an unshared random password hash, and inserts the identity ledger atomically.
The generated native email is an internal placeholder; it is not used for API
account matching or password authentication. No existing native user is adopted
or promoted. An exact retry preserves the native user and password; a different
canonical UUID or revoked native user is rejected without repair.

Review the existing canonical superadmin UUID before running this command: the
native database does not verify roles in PostgreSQL. Then use the returned
native organisation/user IDs with the API `foundation-platform-admin` command,
which verifies the current canonical role. These are two explicit transactions
in separate databases. If the second step fails, the native record remains for
an exact retry and cannot sign in through the bridge without the API mapping.
Do not delete and recreate it or enable native password fallback to bypass the
mapping. Both readiness gates and actual authenticated acceptance remain
required. This command is implemented locally; no live migration or account
creation has been performed in this milestone.

Verification: all 282 native tests / 1,210 assertions passed, including initial
creation, unchanged retry, UUID conflict, native-role revocation, missing
confirmation/bridge and refusal to adopt an existing account.

## Native scheduler preparation

The production environment template explicitly selects `/usr/bin/php8.4` for
detached paper jobs. Merely launching `artisan schedule:run` with PHP 8.4 does
not select that binary for workers; the copied engine reads `PHP_CLI_BINARY`.
The initial template bounds paper workers, parallel papers and heavy OCR/browser
work to one. This retains native queue/claim logic and overlap safeguards.

Question imports, document reconciliation, quality/repair, AI answers, source
extraction and image cleanup remain scheduled. Scheduling a worker does not
establish that its OCR/browser/AI dependencies are configured or verified.
The new `native_schedule` configuration allows lifecycle email and Search
Console jobs to be enabled separately. Copied application defaults preserve
those jobs; the Tech4Learn production template disables both until delivery and
provider acceptance. `MAIL_MAILER=log` does not deliver student email, so its
lifecycle worker must not run and mark logged messages as delivered.

Scheduler installation is still pending. After checked deployment and identity
acceptance, install a single per-minute cron invocation as the Tech4Learn user
against the exact accepted release's `platform/artisan`, using PHP 8.4 and private
shared logs. Review `artisan schedule:list` first, avoid running the original
ExamElite scheduler against this database, and remove the prior Tech4Learn cron
entry when switching releases. Do not activate multiple pinned releases in
parallel. Provider job gates and worker settings must be added to the private
environment explicitly; changing this public template does not update live.

Verification: all 284 native tests / 1,221 assertions passed. Schedule tests
confirm core exam workers remain, provider jobs can be enabled independently
and the initial one-worker schedule does not create a second worker slot.

## Read-only live inventory — 7 October, 13:50 UTC

The restricted status helper reported the API active, the native PHP socket
present and private native environment present. A read-only PostgreSQL inventory
confirmed schema 18, one existing canonical superadmin and zero active native
organisation, staff or learner mappings. The pinned native readiness check
passed environment/schema/domain checks and blocked on the missing native
platform administrator. No migrations, account provisioning, website routing
or scheduler activation were performed by this inventory.

The current checked native readiness script additionally requires the explicit
administrator ledger and an active administrator linked to the canonical primary
realm. Merely setting an arbitrary user's platform-admin flag no longer passes
that gate. After deployment, compare the reviewed canonical UUID in the private
native ledger with the API mapping separately; the native database check cannot
verify a PostgreSQL identity. The new code and local tests do not make the
currently deployed schema-18 website ready for native cutover.

## Authorized previous-organisation cleanup inventory

The later human instruction retains only the previous platform administrator
and attendance implementation, uses fresh organisations in Laravel, and
explicitly authorizes permanent deletion of previous organisations and linked
records. This supersedes preserving their historical attendance during migration.
No old organisation should be linked into the new native product.

`deploy/virtualmin/inventory-legacy-organisations.mjs` enumerates direct and
indirect foreign-key dependencies of `public.organisations`, using catalog
identifiers safely quoted for whole-table counts. Its CLI uses a read-only
repeatable-read transaction and ten-second statement timeout, prints no record
values and contains no deletion operation. The synthetic test passed indirect
dependency discovery inside a read-only transaction and preserved both tenant
records and the administrator password hash.

The read-only live inventory found four organisations, 16 learners, 10 attendance
sessions and 34 organisation-dependent tables, with one canonical superadmin.
Whole-table counts are not a deletion manifest: audit tables include global
records, users/sessions can be shared, and media files require a separately
bounded path manifest. Next prepare the private verified backup, dependency-aware
transactional cleanup, session revocation and private-media cleanup, with checks
preserving the existing administrator UUID/password and excluding the original
ExamElite installation and new Laravel database. No deletion has run.

Media boundary review: legacy attendance photos, additional attendance photos,
learner portraits and OMR scan images use bounded PostgreSQL `bytea` columns.
The inventory now lists byte-array column metadata without reading image content.
Their private backup belongs in the PostgreSQL backup, and their deletion belongs
in the organisation-scoped database cleanup. Do not invent a filesystem upload
deletion step for these database-backed photos. Native Laravel uploads, original
ExamElite uploads, private backups and other sites remain outside cleanup scope.
Any separately discovered external media must still have its own reviewed,
bounded manifest before removal.

The read-only planner in `deploy/virtualmin/plan-legacy-cleanup.mjs` computes
child-before-parent order from the actual foreign-key graph. Cycles, duplicate
table entries and unclassified scope block planning. Direct organisation rows
use the current previous-organisation ID snapshot; native staff use its explicit
native mappings, and old student sessions use grants owned by those organisations.
Tenant audit rows are counted separately from global audit rows. Counts reveal
no UUIDs, credentials or records. The two isolated planner tests passed, including
indirect grants, global audit exclusion, cycle rejection and unchanged admin/data.
This module intentionally has no deletion executor. A private frozen manifest,
verified backup/restore, paused writes and reviewed user/session/global-connector
cleanup are still required before execution.

## Private pre-cleanup backup — 7 October 2026

The checked `deploy/virtualmin/backup-legacy-database.mjs` was run through the
existing deployment account. It created
`/home/tech4learn/private-backups/legacy-cleanup-bftBtx/database.dump` with a
private archive list and checksum manifest. The directory is account-owned 0700;
the archive and metadata are 0600. The script rejects a symlinked, differently
owned or publicly accessible backup root and never places credentials in command
arguments/output. PostgreSQL custom-format backup includes database-backed media.

`pg_restore --list` succeeded and `pg_restore --file=/dev/null` decoded all
archive entries without executing SQL. SHA-256 and size remain in the private
manifest. This establishes archive readability, not a successful database
restore. The manifest explicitly records `restoreTested: false`. Actual isolated
restore verification is still required before destructive cleanup. No database,
application configuration, scheduler or website routing was changed by backup.

The deployment role has neither superuser nor CREATEDB permission. The narrowly
scoped user-run root setup is:

```bash
runuser -u postgres -- createdb --port=5432 --owner=tech4learn_app tech4learn_cleanup_restore && runuser -u postgres -- psql --port=5432 -X -v ON_ERROR_STOP=1 -d postgres -c 'REVOKE CONNECT ON DATABASE tech4learn_cleanup_restore FROM PUBLIC;'
```

It creates only the fixed isolated database; it grants no role-wide privileges,
does not modify the live database and refuses an already-existing destination.
After setup, the deployment account can use `verify-legacy-restore.mjs` with the
private backup directory as its sole argument. The verifier checks private file
ownership/permissions and SHA-256, refuses a populated destination, restores only
to `tech4learn_cleanup_restore` with no owner/privilege restoration, then checks
restored counts and one superadmin. A separate private receipt records success;
the original backup manifest remains unchanged. A partial restore is retained
for diagnosis and cannot be silently overwritten. Never run against the live
database. Source database/account and fixed-target tests passed; actual isolated
restore is pending the database setup above.

## Organisation cleanup transaction — local preparation

`cleanup-legacy-organisation-records.mjs` provides an internal transactional core
with no live CLI. Its private manifest freezes organisation UUIDs, scoped table
counts and the retained administrator UUID/password digest. It locks affected
tables, recounts after locks, refuses stale manifests and checks the stored
administrator before and after deletion. Deletes follow the reviewed child-first
order with bound organisation IDs, explicit native staff/grant scopes and no
unconditional table clearing. It revokes sessions for old members and the admin;
global audit history and newly created organisations outside the snapshot remain.

Local tests cover stale-manifest refusal, isolated scoped deletion, fresh tenant
preservation, unchanged admin password, session revocation and transaction rollback
after a synthetic mid-deletion failure. This prepares the organisation-record
phase only. Non-admin account retirement, global old-connector records and optional
realm-registry cleanup require separate review. It is not connected to a live
execution command; verified restore, private manifest persistence and paused
application writes remain prerequisites. No production deletion has run.

The account phase now freezes non-admin UUIDs belonging to previous organisations
in the same private manifest. After scoped organisation deletion, it retires only
those accounts with no remaining memberships. It revokes their sessions, retains
global audit events with a null actor reference, and checks every incoming user
foreign key before deletion. Remaining shared/global account records block the
entire transaction for explicit review rather than being silently deleted. The
retained superadmin and accounts outside the frozen snapshot are excluded.
The local transaction test passed retirement and rollback when an unexpected
retained user dependency was present, alongside the existing administrator and
fresh-organisation preservation checks. A read-only live count found 11 non-admin
accounts, all members of previous organisations; no account values were printed.
The isolated restore database was still absent at this check, so live cleanup
remains pending its setup and actual restore verification.

Restore verification now also compares a private digest of the sole canonical
administrator's UUID, email, name and password hash between the live read-only
source and isolated restored database. A mismatch or ambiguous administrator
set prevents a success receipt. No identity values or password hashes are printed.
The receipt records only `administratorPreserved: true`. Pure tests passed exact
identity preservation and rejection of each changed field, missing identities
and duplicate administrators. If the administrator changes after backup, create
and verify a fresh backup rather than accepting a mismatched restore.

## Fresh native organisation attendance companions — local implementation

API migration 20 adds a mapping-origin marker; previous mappings remain
`reviewed`. The explicit management command is:

```text
node dist/manage.js foundation-attendance-org CANONICAL_SUPERADMIN_UUID NATIVE_ORG_ID "DISPLAY NAME" --confirm-reviewed-new-native-organisation
```

It verifies a currently stored superadmin and requires migration 20. It creates
a fresh attendance-side UUID, reserved native-ID-derived slug, default permission
roles and learner/attendance settings, then records the explicit native mapping
and audit atomically. It does not issue invitations, create users or import old
organisations. Native/global platform realms are rejected. Existing manually
reviewed mappings and occupied slugs cannot be adopted, even when names match.
Exact retries return the same active companion without new audit/role records;
revoked mappings or changed names block rather than being repaired.

This command requires prior independent review of the new native organisation:
PostgreSQL cannot verify ownership/status in the separate Laravel database.
Automatic native create/update/deactivate hooks, staff and learner provisioning,
centres/sections and authenticated acceptance are still pending. No migration 20
or companion creation has been performed live. Legacy cleanup remains separately
blocked on the pending isolated restore-database setup.

Verification for fresh attendance provisioning: `npm run check` passed workspace
typechecks/builds and all 109 API tests. The new test covers atomic fresh creation,
permission initialization, exact retry/audit preservation, revoked links, occupied
legacy slugs, ordinary staff denial and rejection of the global platform realm.

## Attendance-only Node runtime — local preparation

`TECH4LEARN_API_MODE=attendance` selects the supporting Node runtime for the new
Laravel product. It omits all previous exam connector/content/workspace/student
delivery/OMR controllers and their providers. The React admin directory is ignored
in this mode, so the Node process no longer serves the previous product shell.
Authentication, native identity bridges, permissions, academics, learner/photo
records, attendance, configuration, face-engine controls and health APIs remain
available with their existing checks. These support attendance; they do not
establish a second exam product.

The default is `legacy`, preserving the currently deployed website until the
reviewed cutover. Unknown values fail startup rather than selecting a fallback.
Activate attendance mode only with Laravel website routing: Node `/` returns 404
in attendance mode. This switch does not delete any organisations or data.
Old source remains in Git for rollback pending native acceptance; runtime removal
is separate from physical source cleanup. No live environment was changed.

Verification: `npm run check` passed all workspace typechecks/builds and 111 API
tests, including attendance runtime startup, missing retired routes/shell,
retained health/protected identity and attendance routes, and invalid-mode refusal.

## Verified platform page permissions — local correction

The initial native platform administrator has no legacy role/page-right records.
The copied page-right middleware and sidebar previously relied on those records,
so a valid platform sign-in could still be denied exam administration screens.
The platform identity middleware now grants a server-only, request-scoped actor
marker after canonical identity verification. Page rights and sidebar page
selection accept that marker only with a fresh active, undeleted native admin
record. No legacy role is assigned and ordinary native staff cannot use this
path. The marker is removed after successful responses and controller failures;
the existing canonical pre/post identity checks remain in force.

All 286 native tests / 1,228 assertions passed. New checks verify role-free native
page access only within a verified request, marker cleanup on failure and refusal
to promote ordinary staff. This correction is local; production authenticated
page/menu acceptance remains pending provisioning and routing.

The connected real PHP-to-Nest fixture now exercises `CheckPageRights` inside the
verified platform middleware after password sign-in, then checks request-marker
cleanup, link revocation during a controller and logout. Ordinary tenant attendance
capture/review/history and learner identity checks remain in the same connected
run. A read-only native production menu inventory found 46 menu entries, including
all three core exam/question/student entries; no menu reseeding was performed.
The native readiness gate now requires those three entries before cutover.
This verifies the connected middleware path and menu configuration, not actual
authenticated production browser acceptance.

## Native Apache routing candidate — local preparation

`render-native-apache.py` is a pure renderer with no installer or reload action.
It accepts only the inspected two-vhost Tech4Learn layout, exact current Node
root proxy and a full checked Git revision. Unexpected site names, includes,
ports or proxy destinations block rendering. It changes the document root to
that pinned release's native public directory and routes `/api/v1/` to the
existing Node port with the original Host preserved. Certificate paths, other
directives and ACME proxy exclusion remain; an explicit alias retains the
original ACME challenge directory.

The native directory uses PHP 8.4's existing private FPM socket and explicit
front-controller fallback rather than app-controlled Apache overrides. Only the
release-root index.php is executable; direct extra PHP utilities and nested PHP
index files are denied. The original imported public/extract_file.php is outside
the permitted PHP entry point. Two renderer tests passed fixed routing, preserved
certificate/ACME directives and refusal of unexpected configurations.

This is a review candidate, not an Apache-validated or installed configuration.
Before the narrowly scoped user-run root cutover, stage the accepted release,
pass identity/schema/API/native checks, validate modules and Apache syntax,
back up only the Tech4Learn site file and prepare automatic rollback if reload or
health checks fail. No routing change has been made; the existing helper cannot
edit Apache. The pending isolated restore-database setup remains the earlier
required user action.

## Latest checked native source prepared — 7 October 2026

Pinned release `e1aae9521a27ead522ffff75e785a0d29a031bf4` is now prepared privately
under `/home/tech4learn/releases/`, preserving the previously prepared `22e35a3e`
release. It contains the checked provisioning, scheduling and verified native
administrator page-right changes. Locked native dependencies and unchanged built
attendance assets were copied from the previous release; PHP 8.4 Composer
platform requirements passed. Its private environment points to the existing
shared native configuration/storage. No migrations or administrator provisioning
were run, and the public Node runtime was not switched.

The actual private production-environment HTTP kernel returned `/login` 200,
`/exams` 302 and `/attendance` 302 for unauthenticated requests. Current readiness
passes environment, database account, core menu and primary-organisation checks;
it correctly blocks on the pending native migration and absent administrator
ledger. These guest checks do not constitute authenticated exam/attendance
acceptance. PostgreSQL remains schema 18 and native MySQL remains at the prior
241 migrations. Apache routing, API activation and scheduled jobs remain unchanged.

The same pinned release now has its API-only locked npm dependencies installed
and API TypeScript build completed using the dedicated Node 24 runtime. The
contracts workspace is consumed directly and has no separate build script.
Compiled management CLI and migration 20 files were verified present. Private
install/build logs are in the existing pre-cleanup backup directory. No API
process was started against the live database from this prepared release, avoiding
duplicate background workers. The primary API dist symlink and service remain
on the previous checked runtime; the restricted status helper confirmed it active.
The install reported npm audit findings, which require dependency-specific review
before treating the new release as production-ready; no automatic dependency
updates were applied. No migration, cleanup, account provisioning or routing
change was performed while preparing this build.

## API dependency audit remediation — local patch

Targeted API/contracts production audit identified Multer 2.3.0, proxy-addr 2.0.7
and shell-quote 1.9.0. The lockfile now resolves Multer 2.4.0, proxy-addr 2.0.8 and
shell-quote 1.11.0 through scoped overrides, matching their published fixes:
[Multer advisory](https://github.com/advisories/GHSA-3pph-fpjx-jg34),
[proxy-addr advisory](https://github.com/advisories/GHSA-jqcg-44mw-7w3h) and
[shell-quote advisory](https://github.com/advisories/GHSA-pqg4-j6r4-53mv).
No framework major version changed. Multer's obsolete stream dependencies were
removed by the targeted update; unrelated workspace package versions were kept.

Current requests use JSON rather than Multer disk upload interceptors; the API
does not configure the affected proxy trust subnet, and concurrently is a
development launcher. These observations are not claims of exploitation or a
reason to retain vulnerable packages. The targeted API/contracts production
audit now reports zero findings. Whole-workspace/mobile audit has separate
findings and has not been declared clean. The prepared server release and live
runtime still contain the earlier lockfile until a new checked pinned release
is prepared; no server package was updated in place.

Verification: API/contracts audits with and without development dependencies both
reported zero findings. `npm run check` passed workspace typechecks/builds and all
111 API tests against the updated lockfile. Mobile package versions were unchanged;
no Expo/React Native upgrade or mobile feature work was performed.


## Scoped cleanup completion checks — local preparation

The internal cleanup transaction now recounts each bounded predicate immediately
after deletion. If any scoped row remains, including a trigger-suppressed audit
or media deletion, it throws and rolls back the entire transaction. Administrator
preservation and final organisation checks remain in place. A PostgreSQL-compatible
trigger test verified that a silently suppressed deletion restores sessions,
scoped history and identity links on rollback. All six cleanup-plan, transaction
and restore-target tests passed. This changes deployment tooling only; no live
cleanup, migration or routing action was performed. Actual isolated backup restore
verification remains required before production deletion.


## Bounded database media manifest — local preparation

The read-only cleanup planner now reports non-null media object counts and byte
totals for every bytea column in the scoped dependency graph. It uses the same
frozen organisation predicates as deletion, excluding fresh organisations and
global rows; it never returns image bytes, learner identifiers or file paths.
These summaries are frozen in the private cleanup manifest and checked again
after child-table locks. Changed summaries block the transaction. Existing
media is database-owned; this does not authorize filesystem deletion. Tests cover
nullable media, fresh/global exclusions, read-only planning and stale-manifest
refusal. No production media or records were deleted.


## Legacy connector retirement boundary — source review

The old remote ExamElite connector reads a private JSON file selected by
`T4L_EXAMELITE_CONFIG`; its optional `_platform` entry is global configuration,
not an organisation-owned database record. Tenant connector ledgers
(`examelite_sharing`, `examelite_students`, `examelite_workspaces` and student
grants/sessions) are included through the organisation dependency graph.
Deleting those ledgers must never call the remote original ExamElite service or
delete its students/questions. Attendance runtime mode excludes the connector
provider and controllers, so it does not read that private configuration.
The configuration file and environment reference require separate bounded
retirement review after cutover; the database cleanup core does not unlink files
or infer their ownership from a configured path. No credentials were inspected
or removed. The combined inventory, plan, transaction and restore-target suite
passed all seven tests after the bounded-media changes.


## Apache candidate proxy guard — checked locally

The renderer now rejects additional proxy routes, ProxyPassMatch directives,
changed proxy options and pre-existing ProxyPreserveHost settings rather than
silently retaining routing that could override the native/API boundary. Three
renderer tests passed. A read-only retrieval of the actual Tech4Learn site
configuration matched these guards and rendered successfully in memory. No
configuration file was saved or installed, and this is not an Apache syntax
check or cutover acceptance. The candidate still requires a prepared accepted
release, native/API identity readiness and the narrowly scoped user-run root
installation with rollback.


## Initial native runtime profile gate — checked read-only

Native readiness now checks the explicit PHP 8.4 detached-worker binary, all
three initial paper-worker limits of one, disabled lifecycle email/Search Console
provider jobs and fixed private shared storage path. Missing values block rather
than falling back to copied engine defaults. The pure profile test passed the
accepted profile and missing/changed settings, including unaccepted provider
enablement; the readiness script passed PHP syntax checking.

A read-only check of the prepared release's actual private environment confirmed
shared storage but blocked the worker binary, limits and both provider gates.
These template values have not been installed in the private environment. Do
not activate the scheduler until the approved settings are installed and this
gate passes. No private values were printed, environment files edited or jobs
started. This gate covers the conservative initial release profile; later
provider enablement requires its own verified deployment review.


## Pinned scheduler candidate — local preparation

`render-native-cron.py` renders a per-user crontab candidate without reading
server files, installing cron or executing jobs. The per-minute command uses
PHP 8.4, a full checked Git revision, a fixed shared non-blocking flock and a
private shared log. Umask 077 keeps newly created lock/log files private.
It replaces only an exact previously generated managed block, preserves all
other cron jobs and rejects unmanaged Tech4Learn schedulers, duplicate blocks
and altered commands. The shared flock serializes scheduler invocations across
release changes; native per-job overlap locks still handle background workers.

Three renderer tests passed, including exact retries, release replacement and
unknown/duplicate refusal. Read-only inspection of the existing Tech4Learn user
crontab passed the guards; no crontab contents or credentials were printed.
Before installation, verify the accepted pinned release, runtime profile and
identity/schema checks, review schedule:list, check PHP/flock availability and
private ownership/permissions of shared storage/framework and storage/logs, and
back up the user crontab. Previously existing log/lock files also need private
permissions; umask does not change them. Only then install as the Tech4Learn user
and verify a bounded run. No scheduler was activated.


## Connected acceptance in attendance-only mode — local verification

Both the connected PHP-to-Nest fixture and repeatable synthetic browser pilot
now explicitly select `TECH4LEARN_API_MODE=attendance`. Their previous default
used legacy mode, so the updated checks exercise the actual replacement support
runtime. Connected platform password sign-in, native page permissions, explicit
learner resolution, attendance capture/submission/review/history, revocation and
logout passed with the old exam connector/provider routes omitted. The synthetic
pilot check passed separate global/tenant identity login and attendance context.
The native FoundationExamJourneyTest passed six tests / 48 assertions covering
the local native exam journey. These are local synthetic checks, not authenticated
production browser acceptance, provider/OCR delivery or mobile device acceptance.
No live identities, mappings or records were created.


## Fresh attendance administrator provisioning — local implementation

The API also supports `POST /api/v1/platform/foundation/attendance-administrators`
under the canonical platform session. Stored superadmin authority is checked before
credential hashing; native IDs/account fields must be valid and the explicitly
requested native role must be `admin`. Ordinary staff are not granted administrator
authority by this endpoint. The existing provisioning transaction prevents email
adoption, requires an active fresh companion and preserves passwords on exact retry.
Local HTTP checks verified creation without superadmin authority and a retry with
a different supplied password retaining the original canonical password. The full
`npm run check` passed 111 API tests, workspace typechecks and builds. Native account
creation/retry wiring, ordinary staff provisioning and live acceptance remain pending.

The trusted operator command is:

```text
node dist/manage.js foundation-attendance-admin ROOT_UUID NATIVE_ORG_ID NATIVE_USER_ID EMAIL NAME --confirm-reviewed-new-native-staff
```

It prompts for the canonical password privately in an interactive terminal;
passwords are never accepted as command arguments or copied from native hashes.
Independently review that the native user is active, belongs to the intended new
tenant and is not a platform administrator before executing. The API verifies
the stored canonical superadmin and requires an active `native_companion` map.
It creates a new ordinary canonical account, organisation-wide administrator
membership, explicit native staff link and audit in one transaction. Existing
email accounts are rejected rather than adopted. Exact active retries preserve
the canonical password and avoid duplicate audits. Revoked/suspended identities,
changed identity details or changed membership authority fail without repair.

This is reviewed provisioning tooling, not automatic SaaS onboarding. Native
create/update/deactivate hooks, staff lifecycle synchronization, centres/sections
and student/learner provisioning remain pending. No live account was created.


Verification for fresh attendance administrator provisioning: `npm run check`
passed workspace typechecks/builds and all 111 API tests. The provisioning test
covers ordinary-operator/global-realm denial, existing-email non-adoption, new
account creation without superadmin authority, exact retry preserving password
and audit count, changed identity rejection and revoked/suspended access refusal.
Mobile and native Laravel application code were unchanged by this operator CLI
addition; it does not establish mobile device or authenticated live acceptance.


The fresh staff provisioning test also injects a failure at identity-link
insertion after creating the account and membership. PostgreSQL transaction
rollback leaves no new account, membership or provisioning audit behind. This
targeted test passed without application-code changes after the full 111-test
workspace check. GitHub temporarily rejected pushes with internal server errors;
checked commits remain local with an ignored verified recovery bundle until
pushing succeeds. No deployment was attempted from an unpushed revision.


## Fresh-organisation attendance acceptance prerequisites

Source review confirms the embedded attendance page loads explicit tenant
context and authorised sections, then reuses capture/review/history. Its native
gateway permits section reads and attendance operations only. It currently
provides no centre, section or learner creation flow. Therefore a fresh companion
and staff identity alone cannot make attendance operational.

Before claiming fresh-organisation acceptance, complete and verify: an approved
attendance centre location/policy; active class/section references; canonical
learner records enrolled in the intended section; explicit native student links;
and current staff membership/scopes. The native exam Group model must not be
assumed to be an attendance section or centre by numeric ID/name matching.
The existing connected fixture supplies synthetic centre/section/enrolment data
explicitly; its success does not prove automatic production onboarding.

The implementation still needs native onboarding/lifecycle integration for those
references and reviewed organisation/staff deactivation propagation. Keep the
current gateway allowlist narrow while implementing that integration; widening
it alone would recreate a separate administration product without synchronising
native identities. No live setup or production acceptance was performed by this
source review.


## Read-only native attendance administrator review

Before the fresh attendance administrator provisioning command, run:

```text
php8.4 deploy/virtualmin/check-native-attendance-staff.php /home/tech4learn/releases/CHECKED_FULL_REVISION/platform NATIVE_ORG_ID NATIVE_USER_ID
```

The CLI accepts only a pinned Tech4Learn native release and positive numeric
IDs, parses private configuration without booting application providers, and
uses the dedicated native MySQL account in a read-only transaction. It requires
one active non-primary organisation membership, an active undeleted ordinary
native user and an owner/admin role. Missing, ambiguous, platform, inactive or
revoked identities block. It prints only pass/block status, never names, emails
or credentials. It creates no canonical account or mapping and does not replace
review of canonical companion origin or intended native ownership. Recheck at
provisioning time; a read-only report does not lock authority across databases.

Pure boundary checks and CLI PHP syntax validation passed. No production
identity review/provisioning or native application behaviour was changed.


## Frozen cleanup account set — local correction

The internal cleanup transaction now rechecks the exact non-admin account UUID
set belonging to the frozen previous organisations after all dependency locks
are held. Replacing a membership can preserve table row counts while changing
which account would be retired; that now blocks cleanup for manifest review.
Duplicate account IDs also fail manifest validation. The regression test replaces
an organisation member without changing counts and verifies refusal before
session or data deletion, then restores the synthetic fixture and checks the
normal cleanup path. All six plan/transaction/restore-target tests passed. No
production cleanup was executed; isolated restore verification remains pending.


## Cleanup query timeout bounds

The internal cleanup transaction now sets transaction-local 60-second statement
and 15-second idle-transaction timeouts, alongside its five-second lock timeout.
A slow statement aborts the transaction for rollback/review instead of waiting
indefinitely; these are per-statement/idle limits, not a total transaction timer.
No automatic retry is introduced. The targeted cleanup transaction test passed
its stale-manifest and rollback checks and confirmed timeout settings revert to
the connection defaults after completion. No live cleanup or database setting
was changed.


## Cleanup backup receipt validation — local preparation

`cleanup-backup-proof.mjs` validates matching backup/isolated-restore metadata
for the future operator executor. It requires the exact archive SHA-256, full
archive list/decode flags, unchanged live database, a preserved sole administrator
and valid aggregate restore counts. Backup creation must precede verification;
future timestamps, malformed values or a backup older than one hour block.
This conservative freshness window requires a new backup/restore when the
cutover window is delayed. Two pure boundary tests passed.

This validator is not yet wired to a live cleanup command. The executor must
also check actual private file ownership/permissions and hash the archive, pause
writes, capture the frozen deletion manifest and use the transactional core.
Metadata alone is not proof of a restored database or authorization to delete.
The earlier preserved backup remains a recovery artifact; no receipt was
invented and no production deletion or isolated restore occurred.


## Restore gate connected to cleanup core

The internal cleanup entry point now requires validated backup/restore evidence
and a deletion manifest bound to that exact archive SHA-256. Missing proof or
a different archive blocks before entering the database transaction. Frozen
manifest capture accepts the reviewed archive hash explicitly. The transaction
test uses labelled synthetic receipt data, verifies missing/mismatched proof
leaves sessions intact, and still passes stale membership/media, trigger failure,
shared dependency rollback and administrator preservation checks. Three targeted
receipt/transaction tests passed.

There remains no live cleanup CLI. Its trusted file-loading layer must verify
private ownership, permissions and actual archive bytes, and enforce paused
writes before supplying evidence. Binding metadata is an internal precondition,
not a substitute for actual isolated restore. No production receipt was created
or destructive operation performed.


## Private cleanup evidence loader — local implementation

`load-cleanup-evidence.mjs` reads only the fixed Tech4Learn backup directory
pattern. It rejects root execution, resolved paths outside the named directory,
symlinks, hard-linked files, wrong ownership, shared permissions and oversized
JSON metadata. It validates the actual receipt, streams the archive SHA-256
without loading media into memory, checks size and detects archive inode/time
or permission changes during reading. No private file contents are printed.
Four receipt/filesystem-boundary tests passed, including changed bytes and
replacement during hashing. Synthetic IO fixtures isolate these Unix metadata
checks from the Windows host.

This is a read-only loader with no live CLI. Paused-write enforcement and private
frozen-manifest persistence still need to be connected before a production
executor can invoke the guarded cleanup transaction. No real backup/receipt was
modified, actual isolated restore performed or organisation deleted.


## Paused-write gate connected to cleanup core

Before acquiring cleanup locks, the internal transaction now checks the fixed
Tech4Learn systemd service reports exactly `inactive` and no other client backend
is connected to the source database. Active, failed, unknown or timed-out service
observations and remaining database clients block. The guard performs only
read-only status/connection queries; it does not stop services, kill sessions or
modify systemd. Two targeted guard/transaction tests passed, with synthetic
service observation for the Windows fixture and actual PostgreSQL-compatible
connection discovery in the cleanup test.

The eventual operator must maintain the reviewed maintenance window and prevent
service restarts/other writers while cleanup runs. Restricted deployment
permissions do not currently include stopping the API, so preparation alone
does not authorize or perform that stop. Private frozen-manifest persistence
and the live execution entry point remain pending; no production service was
stopped or organisation deleted.


## Explicit previous-organisation manifest capture

Manifest capture now requires a reviewed UUID list and archive SHA-256. It no
longer selects every organisation automatically. Missing/duplicate/invalid IDs
or IDs absent from the source block capture. The transaction fixture now creates
a fresh organisation before capturing the old-ID manifest, confirms it is
excluded, and preserves it through successful cleanup. Three targeted plan and
transaction tests passed. Previous organisation UUIDs belong in a private review
file, never a Git commit or user-facing log. No production manifest was captured
or organisation deleted.


## Private frozen-manifest preparation command

`prepare-legacy-cleanup-manifest.mjs PRIVATE_BACKUP_DIRECTORY` is an operator
preparation CLI, not a deletion command. It requires Linux, private freshly
restored backup evidence and `previous-organisations.json` in that same backup
directory, owned by the deployment account with no shared permission bits.
The review file contains only an explicit `organisationIds` UUID array. Never
commit its actual contents. It validates the fixed source database/account,
requires paused writes and captures scoped IDs/counts/media/admin proof inside
a repeatable-read read-only transaction. It then saves `cleanup-manifest.json`
with mode 0600 and exclusive creation; existing review artifacts are not replaced.

All 12 targeted receipt, filesystem, manifest-input, paused-write, plan,
transaction and restore-target tests passed, and the CLI passed syntax checking.
The actual Linux success path remains unexecuted pending fresh restore and the
maintenance window. No production manifest was saved or database record changed.
The eventual deletion entry point must load/revalidate this private manifest
and current evidence, then invoke the guarded transactional core; it remains
unimplemented and must not infer old organisation IDs from current table counts.


## Guarded cleanup operator entry point — not executed

`execute-legacy-cleanup.mjs PRIVATE_BACKUP_DIRECTORY
--confirm-authorized-previous-organisation-deletion` is now prepared. It accepts
only a clean pinned release under `/home/tech4learn/releases/FULL_REVISION`,
verifies Git HEAD, private restored backup evidence and a fresh private versioned
manifest, rejects an existing completion receipt, validates the fixed source
database/account and invokes the guarded transactional core. The core enforces
paused API/database clients, frozen scope, administrator preservation and
dependency checks. Completion metadata is written privately with exclusive
creation. No identities, passwords or media are printed.

A failure before commit reports blocked/rolled back. Lost commit acknowledgement
or failure to save a receipt after commit reports uncertain/committed status
and explicitly prohibits a blind retry; inspect the database first. Eight
targeted proof/input/filesystem/pause/transaction tests passed, followed by the
executor input test and syntax check after commit-state handling was tightened.
The actual Linux operator success path remains unexecuted.

Do not run until a fresh isolated restore is verified, explicit old UUIDs are
reviewed privately, the maintenance window is authorised and API writes stopped,
and the captured manifest is checked. Current restricted helper permissions
do not stop the API. No live data was deleted and no site/service was modified.
The earlier documentation describing the absence of an executor records the
previous implementation state; this entry supersedes that limitation only.


## Narrow maintenance stop action — local proposal

The helper source now includes `stop-api`, which invokes only
`/usr/bin/systemctl stop tech4learn.service` with no caller-supplied paths, service
names or shell. Eight helper boundary tests passed, including rejection of other
service names, extra arguments and unknown stop actions. Service execution was
mocked; no production API was stopped.

This is not an installed permission. The current installer/sudo action allowlist
and deployed helper remain unchanged, so the deployment account still cannot
use this action. A separately reviewed root-owned helper upgrade and exact
Tech4Learn-only sudoers addition must be prepared before requesting the narrow
maintenance permission. It does not authorize Apache changes, arbitrary root
commands, other-site edits or original ExamElite service control.


## Narrow helper upgrade prepared — not installed

`upgrade-tech4learn-admin.sh` is a root-run upgrade candidate for the existing
Tech4Learn helper only. Its source directory must be root-owned mode 0700 and
its checksum-reviewed helper/policy files root-owned mode 0600. It verifies the
existing fixed helper paths, root ownership, file modes and exact old sudo rule,
validates Python source and sudo syntax, privately backs up the helper/policy,
and installs only those two files. Failure restores their previous contents.
It neither changes the wrapper nor runs stop/restart/database/routing actions.

The new sudo policy adds only `/usr/local/sbin/tech4learn-admin stop-api` to the
three existing exact actions. Shell syntax and all eight helper boundary tests
passed. The candidate policy also passed the real server's `visudo -cf /dev/stdin`
read-only syntax check with LF bytes; no policy file was written. The complete
root installation/rollback path remains unexecuted.

A user-run checksum-pinned root setup is still required to install this narrow
permission. Do not use the upgrade candidate as permission to run arbitrary
root commands or touch other websites. Public service and installed sudo rules
remain unchanged; the isolated restore database is an independent prerequisite.


## Native exam-processing dependency inventory — read-only

The deployment account's system Python can resolve PyMuPDF (`fitz`), Pillow
(`PIL`) and pytesseract; python3, pdftotext and tesseract executables are present.
These checks establish availability only, not calibrated OCR or successful
processing of actual learner/exam content. The copied Python requirements for
core exam quality use those three modules. Admission allotment/seat-matrix
imports additionally use pdfplumber, which is absent from the checked system
Python; that optional workflow is not verified.

The prepared `e1aae952` native browser renderer cannot resolve Playwright from
its script location. API-only npm installation does not install native platform
package dependencies. Native question visual/browser review is therefore not
ready; Playwright and its browser runtime require a checked native dependency
installation and bounded synthetic rendering test before acceptance. Do not
reuse another site's node_modules/browser credentials or claim the complete
engine is operational merely because PHP boot and ordinary exam tests pass.
No dependencies were installed, original ExamElite paths accessed or production
processing jobs started during these read-only checks.


## Native browser renderer synthetic smoke check

`node tests/native-browser-smoke.mjs PREPARED_NATIVE_ROOT` starts a temporary
loopback-only fixture server and invokes the actual native browser renderer.
It verifies a visible synthetic question has no findings, screenshot PNGs are
created, and a deliberately broken image is detected without browser exceptions.
It uses no application sign-in, provider, database or learner content. Evidence
is stored under ignored `.local/native-browser-smoke-*`.

The local run passed. Local Playwright/playwright-core library copies match the
native lockfile version 1.61.1 and were copied as code into the ignored local
foundation without modifying the original installation; the existing local
browser runtime was used. This is not evidence that the prepared Linux release
has its own native Node/browser dependencies installed. Its earlier Playwright
resolution check remains blocked. Do not link production dependencies to another
site or copy its application data. Actual native question-preview integration
and Linux browser/runtime acceptance remain separate checks.

## Local integrated verification — 8 October 2026

Against checked source through `f016821f`, the complete native suite passed
289 tests / 1,239 assertions. The ignored local foundation's PHP application,
route and test files were compared with the checked copy; three stale files
were refreshed before the reported full-suite run. The student browser journey
then passed again against that refreshed source, including sign-in, answer
saving, submission and the two-mark result page. A query-only SQLite check
confirmed the saved score and completion time.

The attendance browser journey separately passed enrolment, virtual-camera
submission, explicit teacher confirmation and correction history. Reproduction
commands and fixture boundaries are in [the integration checks](foundation-attendance-bridge.md).
These are synthetic local checks. They do not establish production cutover,
real-device location/camera accuracy, recognition calibration or complete exam
feature acceptance. Production identity provisioning, the authorized previous
organisation cleanup with verified recovery, runtime/scheduled-job checks and
the reviewed website cutover remain outstanding. Current project instructions
permit the assistant to read live state only; live changes remain user-run.

## Legacy Apache routing gate — 8 October 2026

A read-only inventory of the actual Tech4Learn virtual host found legacy
ScriptAlias, CGI handlers/wrappers and rewrite/redirect rules alongside the
current root Node proxy. The candidate renderer now refuses these directives
instead of preserving potentially reachable legacy execution paths after the
proxy is removed. Four renderer tests passed, including nine executable/rewrite
directive cases. The actual live configuration is rejected by this new gate;
the earlier in-memory rendering milestone does not satisfy the stricter gate.

Before cutover, review a narrowly scoped candidate that removes or replaces
these Tech4Learn-only legacy routes, preserves TLS and ACME handling, and passes
the full Apache configuration test with backup and rollback. This gate is local
code only: no live configuration was written, removed or reloaded. The renderer
output remains a candidate, not an installation command or a ready cutover.

The subsequent pure `retire_reviewed_legacy_routes` preparation accepts only
the exact twice-repeated Tech4Learn legacy blocks retrieved read-only. It removes
the old public-root/CGI directory grants, PHP 8.1 CGI handlers, CGI/AWStats aliases
and statistics authorization block. It preserves the exact existing admin and
webmail service redirects; no service, credential file or other virtual host is
changed. The final renderer grants only the existing ACME directory separately
from the pinned native public directory. Changed blocks, administration targets
or extra rewrite routes require review. Six tests passed, and the actual ignored
read-only configuration snapshot produced a candidate in memory. Apache syntax,
root installation and rollback are still unexecuted; no cutover command is ready
for the user yet.

The public-tree review identified the legacy `extract_file.php` OCR endpoint,
still referenced by the native subjective-answer upload screen. The candidate
blocks this standalone executable; an authenticated, tenant/result-scoped OCR
integration is therefore required before subjective-file acceptance can pass.
The verified MCQ browser journey does not cover that feature. No endpoint was
enabled or removed on the live website.

The candidate now also denies alternate/case-varied PHP, versioned PHP, PHTML
and PHAR filenames, plus hidden public files. ACME's directory independently
denies executable extensions. Only the exact root `index.php` gets the PHP-FPM
handler. Seven renderer tests passed, including extension cases; these remain
configuration-generation checks rather than Apache runtime acceptance.

## Subjective answer evidence — local implementation

New authenticated answer-file uploads now use private local storage with random
filenames beneath the owning organisation and attempt. The result row is locked
in the same order as exam finalization; completed attempts reject uploads with
409, foreign student results with 403 and questions outside the attempt with
404. File validation returns normal validation errors, and failed database
writes remove only the newly created file. The response no longer returns a
public file path. Existing public answer evidence is not deleted or migrated.
The existing assessment reader supports the new private paths and rejects path
traversal; existing legacy answer paths remain readable.

The copied migrations lacked `uploaded_answer_path`, `extracted_answer_text` and
`ai_assessed`, despite controllers using them. The new native migration adds
only missing columns and preserves them on code rollback to retain evidence.
It has not run live. Twenty-one targeted tests / 102 assertions passed,
including private text readback, traversal rejection, ownership, attempt scope
and completed-attempt protection. The standalone student OCR endpoint still
requires authenticated integration; these upload checks do not claim OCR or
complete subjective-answer browser acceptance.
The full native suite subsequently passed 293 tests / 1,256 assertions against
the updated local source and migration.

## Authenticated answer extraction — local implementation

The subjective upload screen now sends CSRF, attempt and question identifiers
to the authenticated, rate-limited Laravel `student.answer-extraction` route.
It validates the file and owning student/organisation/question, checks an open
attempt before processing and rechecks submission state afterward. Text responses
use private no-store caching. The former standalone public extractor is retired
with HTTP 410; original ExamElite source is unchanged.

The bounded extractor uses existing PHP ZIP support for DOCX, existing native
process support for PDF/Word/image tools and UTF-8 text reading. External tools
have 20-second total / 10-second idle limits and a 512 KiB combined output limit;
DOCX XML is size-bounded and rejects document-type declarations. Image OCR
currently uses the existing Tesseract English language path. Additional-language,
real-image accuracy and Linux binary/provider acceptance remain unverified.
No external provider is silently required or configured by this change.

Seven upload/extraction tests passed, including private text extraction,
unauthorized processing rejection and submission during extraction. The complete
native suite passed 296 tests / 1,265 assertions. These are local synthetic tests;
production OCR/runtime checks remain.

The local browser runner also supports a fresh `--subjective` synthetic fixture.
It verified student sign-in, TXT upload through authenticated extraction, insertion
into the answer field, private evidence storage, answer save, submission and result
display. The scoped database verifier confirmed the submitted answer text, private
file and pending marking status. Six separate extraction boundary tests passed for
line endings, invalid encoding, empty text, oversized text, DOCX expansion and
external entity declarations. This is local TXT acceptance; image/PDF/DOC OCR,
authenticated production acceptance remain unverified.

Manual grading now validates numeric non-negative marks against each pending
answer's maximum on the server. It rejects unknown/already-marked answers and
open attempts, validates the whole batch before updates, and locks the submitted
result and pending answers in a transaction. Partial grading retains `Pending`
until every pending answer has been marked. Four local HTTP regression tests
passed (13 assertions). The complete native suite then passed 306 tests / 1,286
assertions.

The `foundation-student-browser.mjs --grade` local journey uses separate student
and teacher browser sessions. It verified the teacher's evaluation screen shows
the uploaded explanation, saves two marks through the publish button, and the
student's refreshed result shows 2.00. The scoped database verifier confirmed a
`Pass`, two obtained marks and retained private evidence. This is synthetic local
manual marking acceptance, without AI assessment or production sign-off.

The same student/teacher browser journey passed with a synthetic DOCX file
(`prepare-native-exam-browser-pilot.php --subjective --docx`, then
`foundation-student-browser.mjs --docx --grade`). It exercised actual document
MIME validation, authenticated extraction, private upload, submission, teacher
publication and the student's updated score. The database verifier confirmed
the two-mark pass with evidence retained. TXT and DOCX are locally verified;
image/PDF/legacy DOC processing and production acceptance remain pending.

Text-based PDF acceptance also passed locally using `--subjective --pdf` and
the browser's `--pdf --grade` mode. The real `pdftotext` process extracted the
synthetic PDF; page-break characters are now normalized to line breaks before
trimming. Upload, submission, teacher publication and student score refresh all
passed, and the database verifier confirmed the two-mark pass with retained
evidence. This does not verify scanned-image PDFs, handwriting or Linux
production runtime availability.

Printed English PNG acceptance passed through the same local student and
teacher journey (`--subjective --png`, then `--png --grade`). The actual local
Tesseract executable extracted the synthetic printed answer. Submission,
teacher publication, student score refresh and the private-evidence database
check passed. This establishes a controlled printed-text example only;
handwriting, other languages, physical camera quality and production OCR
runtime acceptance remain unverified. The PNG fixture uses local GD and a
Windows system font, stays ignored, and contains no learner data.

The AI assessment evidence reader now reuses `StudentAnswerTextExtractor` for
retained private and bounded legacy paths, replacing unbounded shell commands.
This supports DOCX readback and the same process timeout, output and encoding
limits as student extraction. Eight upload/access/readback tests passed, including
retained DOCX extraction and oversized text rejection. Actual AI provider marking
and production acceptance are still unverified.

The copied AI controller also writes `ai_score` and `ai_providers_used`, which
were absent from the migration history. Migration
`2026_10_08_000002_add_ai_assessment_metadata_to_exam_stats` adds nullable score
and provider metadata fields only when missing. Its rollback preserves evidence.
A local regression test verified existing metadata survives repeat application
and rollback. This migration is checked locally and has not run in production;
actual AI provider marking and attempt-state/concurrency acceptance remain pending.

AI assessment now rejects open, non-pending or already AI-assessed answers
before extraction/provider calls. Bulk assessment excludes open attempts and
manually marked answers. After provider work, saving locks the result and answer
and rechecks their states, preventing an intervening teacher grade from being
overwritten. Extracted evidence is saved inside that transaction. A local HTTP
regression verified bulk exclusion and retained manual marks; provider-call
concurrency and real provider assessment acceptance remain pending.

A deterministic local provider simulation now verifies the intervening-teacher
case: during the fake HTTP response the teacher's one-mark grade is persisted,
then AI saving returns HTTP 409 and leaves that grade and total unchanged.
The test blocks stray HTTP requests and uses no real provider credential or
learner data. Real provider and production concurrency acceptance remain pending.

A second simulated provider test verified an out-of-range score of 999 is capped
at the question's two-mark maximum, remains pending teacher publication, and is
not reassessed on a bulk retry. The teacher then overrides the suggestion and
publishes the final total. All eleven local marking tests passed (51 assertions);
these tests fake provider HTTP responses and establish no real AI quality claim.

## Fresh attendance onboarding creation/retry wiring — local only

Fresh organisation creation now attempts companion delivery after committing the
native organisation, configuration, audit and pending request. Failed delivery
retains the created organisation and reports pending setup. Active pending rows
have a CSRF-protected, throttled Retry attendance setup action in the existing
organisation table. Completed delivery explicitly reports that staff setup is
still required. Local regression verifies persistence on failed delivery; the
complete native suite passed 340 tests / 1,514 assertions. Actual creation/retry
browser acceptance and staff/learner lifecycle synchronization remain pending.
No production migration, onboarding or routing change was performed.

Onboarding authority regressions passed 2 tests / 14 assertions: a modified
caller object cannot elevate an ordinary stored account, missing pending requests
cannot adopt arbitrary organisations, and suspended/platform organisations are
rejected before any remote call or attempt increment. These are local delivery
service checks; they do not establish creation/retry browser acceptance.

Native HTTP/render checks passed 3 onboarding tests / 22 assertions. Ordinary
staff cannot reach the retry route; a native platform administrator can render
the pending action and submit a retry. Completed requests remove the action.
The route test uses an injected delivery result and no configured remote API;
connected delivery is checked separately. Full browser acceptance remains pending.

## Native attendance administrator bridge — local only

The bridge can now call the authenticated attendance administrator endpoint using
stored native account details and an active owner/admin membership. It rejects
ordinary staff, inactive memberships, platform accounts and inactive/primary
organisations, rechecks platform identity before and after delivery, and rechecks
target membership and identity after the response. Passwords remain in the request
only. Targeted checks cover stored identity, ordinary staff denial and membership
revocation during delivery. Creation-form wiring and password-reentry retry remain
pending; this bridge method alone does not grant a working native account flow.
