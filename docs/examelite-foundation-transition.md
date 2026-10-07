# Tech4Learn application foundation transition

Decision: 7 October 2026. The user explicitly requests the complete ExamElite
application code as Tech4Learn's foundation, integration of the existing
attendance implementation, then FLN and the mobile app. This replaces the
React exam-interface replication and separate-product connector strategies.

The user subsequently authorises direct checked deployment and asks for write
access setup. This supersedes the earlier read-only/user-run-only deployment
preference. Actual SSH write access is still pending installation of the new
public key. Never infer access from this authorisation or weaken the review
account. `deploy/virtualmin/install-deployment-access.sh` grants application
account access and the existing service restart only; privileged host/PHP
configuration requires an appropriate separately checked installation procedure.

## Repository and cleanup direction

Use `menhadi/tech4learn` as the combined product repository. Preserve the
ExamElite origin revision and reviewed local edits in import metadata. Fetching
`menhadi/examelite` must not reset those uncommitted edits or replace the
running local foundation automatically. Import native source into a dedicated
Laravel directory after secret/asset review, retaining `apps/api`,
`apps/mobile`, shared contracts and current attendance migrations/history.

Retire the React exam replication and remote ExamElite adapters only after
dependency inspection and native replacement acceptance. Historical installer
scripts are not suitable for this new product: some edit the original ExamElite
deployment. Do not execute them as the foundation deployer. Keep cleanup separate
from data migration; do not delete attendance evidence, credentials, question
content, originals or working source changes. The eventual production deployer
needs backups, two-database handling, a pinned revision and rollback checks.

## Current preparation

The work is isolated on `codex/examelite-foundation`. The local source copy is
`.local/tech4learn-foundation`; its manifest records source revision and hashes
of the current working-tree files, including local edits and new application
files. It excludes uploads, databases, environment files, runtime caches,
dependencies and unrelated server tools. Bundled frontend libraries are copied.
The source application's code is being preserved, not reduced to questions or
reimplemented. Copying source does not import the question bank.

Repeat in a new ignored directory:

```powershell
python scripts/create-examelite-foundation.py D:/examelite D:/tech4learn/.local/tech4learn-foundation
php scripts/prepare-foundation-local.php D:/examelite D:/tech4learn/.local/tech4learn-foundation
```

The second command creates a fresh local SQLite configuration and app key and
reuses installed vendor libraries with the same Composer lock. Application
classes and helpers resolve to the copy. This dependency loader is local-only;
production must install dependencies normally. It imports no source environment,
database or session. Neither command updates the source or deploys anything.
An interrupted unconfigured copy can use `--resume`; completed/configured copies
cannot be overwritten with that flag.

Then prepare and run the isolated pilot:

```powershell
Set-Location D:/tech4learn/.local/tech4learn-foundation
php artisan migrate --no-interaction
Set-Location D:/tech4learn
php scripts/seed-foundation-local.php D:/tech4learn/.local/tech4learn-foundation
Set-Location D:/tech4learn/.local/tech4learn-foundation
php -S 127.0.0.1:8001 -t public server.php
```

Open `http://127.0.0.1:8001/login`. The seeder refuses a non-empty user,
student or exam database and verifies that application classes resolve to the
copy. Random synthetic local credentials are stored only in the ignored pilot.
They are not production credentials. The copied source's platform slug remains
`examelite` internally because native platform checks depend on it; display
branding and the local hostname are Tech4Learn. A broader branding/identity
refactor requires separate regression checks.

## Verified preparation — 7 October 2026

- 36,589 application/runtime-source and bundled asset files copied from source
  revision `02f62b9e7553c7e05ae3601bd67f271061c152fc`, including working-tree
  edits and new runtime code. No uploaded content or database copied.
- Laravel 12.64.0 boots with isolated configuration; 237 native migrations pass
  against a fresh local SQLite database.
- Synthetic local sign-in, the native dashboard and Exams Management screen
  load in the right-panel browser with Tech4Learn display branding.
- The native tenant selector's MySQL `FIELD` ordering failed on SQLite after
  login. Local preparation replaces it with equivalent `CASE` ordering,
  preserving the original role ranks including the default rank. The source
  checkout is unchanged. The copied selector passes PHP lint and browser login.
- `python tests/foundation-copy.test.py` passes working-tree preservation,
  environment/upload/database/cache exclusion, and refusal to overwrite a
  completed copy or write outside `.local`.
- The existing TypeScript application passes `npm run check`: 94 tests, zero
  failures, typechecks and builds. The existing admin bundle warning remains.

These initial checks do not establish a complete native exam journey, production
middleware acceptance, question-bank import, attendance integration, FLN or mobile
completion. The raw source copy, environment, database, sessions and credentials
remain ignored.

Before versioning the copied application, review embedded credentials, public
assets and included scripts. The raw copy remains ignored during that review.
Provider configuration, commercial integrations, queues, question-bank content
and full feature readiness require separate checks.

### Versioned application import

The sanitized application now resides under `platform/`, including Laravel
controllers, models, migrations, Blade screens and required MathJax/CKEditor
assets. `source-origin.json` records the upstream commit, working-tree source
hashes, exclusions and Tech4Learn patches. Legacy standalone unscoped database
scripts, embedded provider tokens, data stores and uploaded media are excluded.
The original ExamElite checkout is unchanged.

A synthetic in-memory fixture reproduced a tenant admin resolving a foreign
host. The fork now requires stored platform authority or active membership in
the resolved organisation, checks students against their own organisation and
rechecks revocation before releasing the web response. The regression fixture
covers membership, account and organisation revocation as well as foreign tenant
denial. This is central-boundary evidence, not acceptance of every native route.

The latest workspace instructions retain read-only assistant live access.
Git source publication does not activate Laravel, create its production database
or migrate the deployed attendance records. Identity mapping and a checked
production transition remain required.

## Attendance integration boundary

Keep the existing attendance API, PostgreSQL records, private photos, consent,
capture evidence, corrections and job leases intact during the transition.
Relevant existing implementation: `apps/api/src/attendance.controller.ts`,
`attendance.service.ts`, `attendance-evidence.ts`, `face-jobs.service.ts` and
the academic/learner modules. Reuse those modules rather than duplicating their
business rules in Blade controllers.

Laravel uses numeric organisation/user/student IDs; the existing API uses UUIDs
and stored memberships. Add explicit, reviewed mappings, not email matching or
client-provided organisation overrides. Define authenticated session exchange
with current membership and module checks, revocation, audit and denied foreign
organisation access before exposing attendance records. An organisation's
ExamElite admin role is not automatically an attendance permission.

Choose one authoritative student/enrolment workflow and preserve existing IDs,
photos, consent and history before synchronising or migrating records. Do not
move production data merely to make the development UI load.

## Acceptance sequence

1. Boot the copied native application against a fresh synthetic database and
   establish the actual migration/runtime requirements.
2. Apply Tech4Learn branding and verify native staff question/exam authoring,
   student delivery, evaluation, results and PDF generation in the copy.
3. Verify two synthetic organisations and revocation across native screens and
   the attendance boundary. Preserve shared admin draft/table standards.
4. Integrate attendance capture/review/history and private photo access using
   the existing service and explicit identity mappings.
5. Implement the FLN evidence/activity/reassessment workflow from the product
   blueprint; keep planned functionality clearly labelled.
6. Adapt the mobile scaffold with scoped login, attendance/FLN and exam access;
   run native device builds and representative camera/upload/resume checks.
7. Produce checked commits and a user-run production transition/rollback
   procedure. Assistant live access stays read-only.

The existing attendance deployment continues until the combined application
and a reversible data transition are accepted. This document is a development
record; it does not claim a combined release, full feature parity or mobile/FLN
completion.
