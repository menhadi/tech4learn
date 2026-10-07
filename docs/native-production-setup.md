# Native production setup

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
