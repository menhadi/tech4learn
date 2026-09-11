# Learner management release

This release adds scoped learner profiles, enrolment transfers/history, typed custom fields, archive actions, reviewed CSV/Excel imports and synthetic demo learners. It requires explicit migration 3. The assistant develops locally; the user performs deployment.

## Behaviour and boundaries

- Each learner has a stable UUID and a unique organisation-specific, case-normalised code. Name is required; age, class/level and guardian details are optional. No photos, birth dates or government IDs are required.
- This first enrolment model has one current group per learner. Its parent centre determines centre scope. Transfer retains the UUID and closes the earlier enrolment; the next enrolment records time, actor and reason. A transferring user needs permission and access to both the current and destination group. Users who lose scope cannot continue reading that learner. History shows only groups within the viewer's current scope.
- Guardian name/phone require `learners.contacts`, separately from learner read/edit permission. List responses omit contacts and custom values. Audits record IDs/actions, not profile/guardian values. Custom fields share the learner's access policy; do not use them as a substitute for separately protected guardian contacts.
- Profiles use version checks to reject stale edits. Learner archival retains profile/history and closes the active enrolment. Groups with active learners cannot be archived. Restoration and simultaneous enrolment in multiple groups remain future work.
- Custom fields support text, finite number, date, choice and boolean. There are up to 30 definitions per organisation. Stable keys/types and existing choices cannot be changed, protecting historical meaning. Labels, required status and archive status can change; choices can be appended. Required fields apply when a profile is created/edited. Archived values remain readable and cannot be overwritten.
- Protected organisation admins receive the new permissions in migration 3. Existing non-protected roles are not silently expanded. Grant learner permissions through Roles; learner access also needs groups.view and import needs learners.create. Field management requires whole-organisation scope. Server methods enforce current role and record scope; client organisation/group IDs are not authorisation.
- Queries apply tenant predicates and composite foreign keys. This is application-enforced isolation, not PostgreSQL RLS. HTTP tests cover cross-tenant/scoped denial; RLS remains a separate review before introducing broad storage or external query access.

## Imports

The browser reads CSV or Excel `.xlsx` (first sheet), up to 1 MB and 100 data rows. Old `.xls` is not supported. Download the current CSV header template from Learners; all imported rows are assigned to the selected accessible group. Header mapping is explicit: code/name, optional age/class_label/guardian fields, and `custom_<stable_key>`. Use text-formatted phone/code cells to preserve leading zeros. Unknown columns and duplicate headers are rejected. Dates use YYYY-MM-DD and booleans use true/false.

Excel reading uses `read-excel-file` 9.3.10, a concrete dependency for the requested `.xlsx` input ([upstream documentation](https://github.com/catamphetamine/read-excel-file)). Parsing does not execute workbook formulas or send files to an external provider. Raw files stay in the browser; submitted rows go only to the Tech4Learn API.

Preview reports per-row errors and possible duplicate names/ages. Codes must be unique, including archived learners. Duplicate names are warnings, not proof of identity; users must explicitly acknowledge them. There is no automatic merge or update of existing learners. Only a fully valid preview can be confirmed. A saved server-side preview belongs to its creator and expires after 15 minutes. Commit rechecks roles, groups, definitions and duplicates under the organisation lock, then inserts the whole batch atomically. Repeated successful confirmation returns the same result during preview validity. Successful imports clear the staged profile rows. Expired previews are removed when that organisation next creates a valid preview; scheduled retention cleanup remains future operational work. API JSON bodies are limited to 1 MB.

## Deployment

Back up the dedicated `tech4learn_app` database and record the current commit. Fetch/check out the supplied checked commit, then run `bash deploy/virtualmin/update-learners.sh` as root. The script checks the checkout, makes a private backup, stops only Tech4Learn, installs locked API/admin dependencies, builds, applies migration 3 and starts only Tech4Learn. It does not change Apache, certificates, Python, system Node or other websites.

The service stays stopped if build/migration fails. Stop and inspect the error. Do not run bootstrap again. Do not revert to older application code after using learner data: older archival/access workflows do not understand these records. Use a reviewed forward fix or recovery procedure with the private backup.

## Demo learners and acceptance checks

After deployment, take DATASET_ID from the existing private demo-access JSON report. Run:

```bash
runuser -u tech4learn -- /opt/tech4learn/node-v24.19.0/bin/node --env-file=/etc/tech4learn/api.env /home/tech4learn/tech4learn-app/apps/api/dist/manage.js demo-learners DATASET_ID
```

This adds three synthetic learners per active demo group (15 for the original demo fixture), a demo language field and learner-view permission for demo roles that can already view groups. It preserves staff scope/status/passwords. It refuses repeat seeding and changes only the recorded demo organisations. Existing demo cleanup now includes learners, enrolment history, fields and previews. For a fresh installation first create the demo dataset, then run this command with its returned ID.

Verify as admin: open a learner; save a profile; create/require/archive a custom field; transfer between groups; inspect history; try stale edits; preview a CSV and `.xlsx`; confirm a valid batch. Verify as the group-scoped demo teacher: only assigned learners appear, guardian contacts are absent, and a learner transferred out becomes inaccessible. Verify a different organisation cannot read the learner. Archive test learners before archiving their group. No real learner records are used by the automated tests.

Attendance capture, FLN activities, ExamElite integration, mobile learner workflows, programme scopes and field-level custom-data policies remain planned. This release does not claim a complete education platform.
