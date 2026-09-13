# Central ExamElite sharing and identities

Implemented locally, pending user deployment: superadmin can browse/search the central ExamElite catalogue, assign an explicit exam set to each organisation, enable/disable access, and connect Tech4Learn students without entering external IDs. The organisation results screen selects the only linked student automatically and distinguishes an unloaded list from no completed attempts.

## Ownership and scope

Tech4Learn remains authoritative for its learners and enrolment. The new dedicated ExamElite endpoint receives only the Tech4Learn organisation UUID, learner UUID and name. It creates an exam identity with a random unusable-to-the-caller password and no email or phone, or reuses the reviewed legacy pilot link. It does not match by name/email, send welcome emails, return login credentials, change an existing student, import ExamElite students into Tech4Learn, or transfer attendance/photos/location records.

Newly provisioned identities are not yet a student login flow. Student SSO/launch, group/package enrolment, subject/question browsing and authoring workflows, and a durable local result archive remain later work. Existing linked students can continue using their existing ExamElite login and read their shared finished results through Tech4Learn. The shared catalogue is metadata, not a copy of the exam engine.

The central credential is stored server-side as `_platform` in the existing private connector files. It can read the selected ExamElite organisation's catalogue and operate the dedicated identity bridge. The Tech4Learn API checks persisted superadmin identity for sharing and provisioning; organisation reads still require local membership/permissions and the saved organisation exam grant. Revoking an organisation's sharing disables its exam reads without deleting historical identities or results. Client requests cannot provide an external student ID or an external tenant ID.

Migration 13 adds `examelite_sharing` and `examelite_students`. Grant writes are versioned to reject stale saves and audited. Local identity links have a composite learner/organisation foreign key and a unique external identity constraint. The owning ExamElite API uses its `tech4learn_student_links` table and a transaction with an organisation row lock to serialize first creation and plan-limit checks. Lost-response retries reuse the stable mapping; failed inserts roll back the student and mapping together. A local failure after remote success is recoverable by retrying. No student records are created by GET requests.

## User deployment

Use the exact checked Git commit. Live access for the assistant remains read-only. The existing add-on installer backs up/replaces only its controller/routes and scoped rewrite block. The explicit ExamElite migration creates one new mapping table; it does not alter the engine's student/exam tables. Run it as the ExamElite application owner. The existing Tech4Learn update script takes a PostgreSQL backup and applies migration 13.

After fetching/checking out the release, run as root:

```bash
repo=/home/tech4learn/tech4learn-app
python3 -B "$repo/deploy/examelite/install-read-connector.py"
runuser -u examelite -- php < "$repo/deploy/examelite/migrate-platform.php"
runuser -u examelite -- php /home/examelite/public_html/artisan route:clear
python3 -B "$repo/deploy/examelite/configure-platform.py" --exam-organisation 1
bash "$repo/deploy/virtualmin/update-ui-template.sh"
```

The setup helper requires the existing pilot connector files, preserves all legacy grants, backs up private configuration, rotates the central credential without printing it and refuses a change of the external organisation for an existing central connection. Switching to central mode intentionally stops falling back to legacy per-organisation grants. Organisations start disabled until explicitly shared in the new screen.

In **System & account → ExamElite connection**, choose the organisation, enable access, search/select exams and save sharing. Find the Tech4Learn learner and click **Connect student**. This automatically adopts an existing reviewed pilot link when present. Then use that organisation's ExamElite workspace/results screens. No credential, student email or real student mapping is committed to Git.

## Verification and rollback

Run `npm run check`, the isolated `test-platform.php` and `test-read-connector.php` using an existing ExamElite vendor autoloader, and `python -B deploy/examelite/test-connector-rewrite.py`. The platform PHP test uses in-memory SQLite and test identity/plan services; it does not boot the live application. Local HTTP tests use PGlite, real session authorization and fake provider responses. Local browser verification uses synthetic data. Live end-to-end provisioning is a separate user-run check after deployment; SQLite tests do not emulate MySQL contention or the hosting environment.

Rollback: restore the backed-up private configuration and add-on files/routes/rewrite, clear the ExamElite route cache and restore the prior Tech4Learn release. Leave the additive mapping tables in place to preserve identity keys for retries/redeployment. Do not delete created students or mapping history as a routine rollback.
