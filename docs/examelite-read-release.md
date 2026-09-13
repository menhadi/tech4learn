# ExamElite read connector

Implemented locally: dedicated revocable credentials, explicit organisation/exam/student grants, connection status, a shared exam catalogue and finished result summaries. Requires user deployment on the shared host. No production records have been changed by the assistant.

This first slice does not provision students, create or launch exams, import questions, provide SSO, or maintain a local result archive. Exam creation, delivery and marking continue in ExamElite. Refresh starts at the first page so corrected summaries can be re-read; cursors are pagination, not incremental synchronisation tokens.

## Access

Tech4Learn requires organisation-wide `configuration.view` for the connector. Student results additionally use the existing learner-detail permission and centre/group scope check. The server returns only selected summary fields. Secrets are never returned to the browser. Remote requests use the fixed HTTPS ExamElite origin, disallow redirects, time out after ten seconds and cap responses at 512 KB.

ExamElite receives a dedicated random bearer token and Tech4Learn organisation UUID. A private server grant maps that UUID and token hash to the expected ExamElite organisation, an explicit list of exam IDs and explicit learner UUID/student ID pairs. The request cannot choose an ExamElite organisation or student ID. A grant with no exam IDs shares nothing. The user chose superadmin to configure these grants; no superadmin password/session is stored by the connector.

Read operations use bounded database SELECT queries, including result summaries, without the existing result-detail helper's write-on-read behaviour. Only finished attempts for a mapped student, correct organisation and explicitly shared exam are returned. Student registration and enrolment are separate work; do not invent a student match by name.

## User deployment

Use an exact checked Tech4Learn commit. The ExamElite installation is additive: it copies a new controller and route file and appends one require to `routes/api.php`, retaining existing controllers and local/live differences. It backs up affected files and performs PHP syntax checks. No Composer installation or database migration is needed for these new files.

After fetching/checking out the release in `/home/tech4learn/tech4learn-app`, run as root:

```bash
python3 /home/tech4learn/tech4learn-app/deploy/examelite/install-read-connector.py
runuser -u examelite -- php /home/examelite/public_html/artisan route:clear
```

Provision the intended organisation with `configure-read-connector.py --organisation TECH4LEARN_UUID --exam-organisation EXAMELITE_ID`. The live superadmin screen showed ExamElite organisation ID 1 on examelite.com. Resolve the Tech4Learn UUID from the intended organisation, not a display-name match. Without optional grants this creates a connection that shares no exams or results.

Add `--exams 12,34` only for exam IDs the operator explicitly intends to share, and repeat `--learner TECH4LEARN_LEARNER_UUID:EXAMELITE_STUDENT_ID` for reviewed student matches. The example IDs are placeholders, not real mappings. Every invocation replaces that organisation's entire grant and rotates its token; supply its complete desired exam/student set. It preserves other organisation grants, rejects cross-organisation reuse of student IDs within the same ExamElite organisation, and backs up private files. Use `--disable` with the same organisation arguments to revoke its credential. No token is printed.

Configuration is stored in `/etc/examelite/tech4learn-read.json` (hashes and grants) and `/etc/tech4learn/examelite.json` (outbound credential). The helper adds the private path to the Tech4Learn API environment. Files are root-owned and readable only by the relevant application's group. Backups remain in a root-private directory. Never commit or share these files.

Run `bash /home/tech4learn/tech4learn-app/deploy/virtualmin/update-ui-template.sh` to build/restart Tech4Learn, then refresh the browser and open Exams & results. Use Check connection, Load shared exams, or select a linked student and Load results. A successful empty connection is not evidence that exams or student mappings have been configured.

## Checks and rollback

Run `npm run check`. The connector tests check fail-closed private configuration, local access checks before network calls, tenant/learner response matching, redacted errors, response limits and pagination. The isolated PHP SQLite test uses the existing local ExamElite vendor autoloader: `php deploy/examelite/test-read-connector.php /path/to/examelite/vendor/autoload.php`. It never boots the application or reads its environment. It exercises real SELECT queries for cross-tenant rows, unshared exams, other/unmapped students, unfinished attempts, bad credentials, revoked grants and cursor pages.

For rollback, restore the backed-up `routes/api.php` and connector files, clear ExamElite's route cache, and restore the prior Tech4Learn build. Disable/revoke the grant when removing the connection. No schema/data rollback is required by this connector. Live operation, a real mapped result and full exam delivery remain separate validation steps.
