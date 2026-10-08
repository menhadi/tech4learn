# Tech4Learn replacement handoff

## Required result

Use `platform/` (the complete copied ExamElite Laravel application) as the
Tech4Learn website. Retain only attendance module code and the existing platform
administrator UUID/password from the previous application. Previous organisations,
learners, attendance records and private media are deletion targets, not imports.
Keep the original ExamElite installation and new native database untouched by
legacy cleanup. FLN follows working exams and attendance.

## What prevents the visible replacement today

The public domain still routes to the previous Node application. Local checked
changes are not the public release. Assistant server access is read-only under
AGENTS.md; release installation, database writes and Apache cutover are user-run.
Do not run the removed external ExamElite workspace deployment.

## Execution order

1. Pin a checked Git commit and prepare its native release, dependencies, private
   environment, shared storage and attendance-only API assets. Do not use the
   older prepared release as evidence that newer migrations are installed.
2. Verify a fresh private legacy backup by restoring into the isolated
   `tech4learn_cleanup_restore` database. Archive decoding alone is insufficient.
   Produce the exact dependency-aware record and bounded media manifests.
3. Install checked native/API migrations and the explicit platform identity
   ledger. Preserve the canonical administrator UUID/password. Run
   `check-native-readiness.php` against the actual prepared release.
4. Verify authenticated native sign-in and the attendance companion/account
   paths, then an actual exam attempt/submission/result and attendance
   capture/review/history using fresh synthetic acceptance records.
5. Freeze legacy writes and execute only the verified manifest cleanup, with
   administrator preservation and rollback evidence. Do not delete whole tables
   from aggregate counts or delete shared paths outside the media manifest.
6. Review the Tech4Learn-only Apache candidate, preserve TLS/ACME and attendance
   API routing, run the full Apache configuration test and prepare rollback.
   The renderer alone is not a root installation or a ready cutover command.
7. Switch the public root to the accepted native release, then repeat real
   authenticated acceptance at https://tech4learn.com. Retire the previous
   public shell only after that check.

## Verified server progress — 8 October 2026

The user created the isolated restore database and ran the checked verifier at
`48443b7e`. Actual restore completed with a private receipt, matching retained
administrator identity/password and no live database change. The old archive is
outside the one-hour cleanup window; a fresh backup command has been supplied.
Do not reuse the successful old restore as permission to bypass freshness.

A subsequent read-only scoped plan found four organisations, 34 dependent tables,
334 scoped rows and four database-media entries. This is an aggregate review of
actual predicates, not a frozen deletion manifest or deletion completion.

The prepared native release failed its read-only runtime gate because it lacks:

- `PHP_CLI_BINARY=/usr/bin/php8.4`
- `PAPER_PROCESSING_WORKERS=1`
- `PAPER_PROCESSING_MAX_PARALLEL=1`
- `PAPER_PROCESSING_MAX_HEAVY=1`
- `NATIVE_SCHEDULE_LIFECYCLE_EMAILS=false`
- `NATIVE_SCHEDULE_SEARCH_CONSOLE=false`

Production/debug/key/domain/session/database checks passed. Schema and identity
checks were not reached because the runtime gate failed first. Updating the
private environment and installing newer checked migrations remain user-run.
Never print or commit the private environment while applying these fixed values.

## Historical restore-database setup


The isolated restore-database setup below has now been completed by the user. From the server root terminal, the narrowly scoped step
is documented in [native production setup](native-production-setup.md). It creates
only `tech4learn_cleanup_restore` owned by `tech4learn_app` and removes public
connection permission; it grants no broad role privileges and changes no live data.
If it already exists, inspect it rather than recreate or reset it.

## Acceptance boundary

Checked locally: exam/student attempts and results, attendance HTTP integration,
fresh companion provisioning, administrator delivery/retry, stored membership
checks and password re-entry verification. Latest complete native suite: 345 tests
/ 1,551 assertions; subsequent targeted retry checks passed 15 / 88.

Still unverified: replacement deployment, fresh restored backup/deletion execution,
public native administrator sign-in, browser creation/retry, ordinary staff/learner
lifecycle, actual Apache installation and live exam/attendance acceptance.

See [native production setup](native-production-setup.md) for detailed evidence
and [architecture](architecture.md) for retained attendance dependencies.

## Prepared runtime profile step

`deploy/virtualmin/prepare-native-runtime-profile.py` is a user-run root script
that updates only the six fixed runtime settings above in the fixed private native
environment. It saves a private backup, preserves ownership/permissions and
unrelated values, rejects symlinks/unsafe permissions/concurrent changes, and
replaces the file atomically. It does not restart services, clear caches, migrate
data or change Apache. Local transformation checks passed duplicate removal,
private-value preservation and idempotence; Linux installation remains unexecuted.

## Deployment progress after scoped write authorization

Pinned release `551654c4e4feb1708d24f638e2da8068cee430d7` now has locked PHP/API
dependencies, checked attendance assets and applied native/API migrations. The
retained canonical administrator has an explicit native account and platform
mapping; canonical UUID/password were compared before/after and remain unchanged.
All read-only native readiness gates pass. The candidate attendance-only API
passes health on private loopback port 3181. Public routing remains the old site.

The fixed-site `switch-tech4learn-native.py` prepares only Tech4Learn Apache routing,
its service working directory/mode and www-data traversal ACLs for the release.
It requires the exact reviewed source/configuration hashes, native readiness, PHP
and Apache configuration tests, saves private rollback evidence and restores the
previous configuration/service on failed installation. Seven renderer tests and
script syntax passed. It requires a narrowly scoped user-run root invocation;
no legacy deletion or original ExamElite edit is part of this cutover. Public
authenticated acceptance is required after installation.
