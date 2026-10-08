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

## First outstanding server prerequisite

The previously prepared isolated restore-database setup remains the prerequisite
for destructive cleanup. From the server root terminal, the narrowly scoped step
is documented in [native production setup](native-production-setup.md). It creates
only `tech4learn_cleanup_restore` owned by `tech4learn_app` and removes public
connection permission; it grants no broad role privileges and changes no live data.
If it already exists, inspect it rather than recreate or reset it.

## Acceptance boundary

Checked locally: exam/student attempts and results, attendance HTTP integration,
fresh companion provisioning, administrator delivery/retry, stored membership
checks and password re-entry verification. Latest complete native suite: 345 tests
/ 1,551 assertions; subsequent targeted retry checks passed 15 / 88.

Still unverified: replacement deployment, restored backup/deletion execution,
public native administrator sign-in, browser creation/retry, ordinary staff/learner
lifecycle, actual Apache installation and live exam/attendance acceptance.

See [native production setup](native-production-setup.md) for detailed evidence
and [architecture](architecture.md) for retained attendance dependencies.
