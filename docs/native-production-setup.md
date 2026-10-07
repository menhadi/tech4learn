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
