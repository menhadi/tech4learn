# Removable demo data

This CLI fixture exercises the working administration foundation. It does not implement or simulate working learners, photo/CCTV/register attendance, FLN, AI or ExamElite exams. No schema migration or new dependencies are required beyond the deployed access release (migration 2).

## Contents

- **DEMO — Community Learning NGO** and **DEMO — Skills Coaching Academy**, with different branding/centre terminology and distinct IDs.
- Five centres: approved coordinates, coordinates awaiting approval, missing coordinates, an archived centre and a centre in the second organisation. Coordinates are synthetic examples, not verified teaching sites.
- Six groups: two in North, one each in South and Remote, one archived batch and a separate Academy group.
- Existing role templates plus a custom **Demo report reviewer** role with read/export access to history.
- Ten login accounts with independently generated passwords and reserved `example.test` email addresses. None is a platform superadmin.
- A valid group-scoped invitation (three days) and an expired invitation. These addresses do not receive email; use the supplied invitation links.
- Explicitly labelled `demo.records_seeded` history entries. Subsequent actions through the website generate normal audit history.

| Account suffix | Expected access |
| --- | --- |
| admin | Full NGO administration |
| backup-admin | Second NGO admin for testing access changes |
| manager | Manage all NGO centres/groups; no Team or Roles |
| coordinator | North centre and both North groups; manage those groups |
| teacher | View only North Morning group |
| assessor | View all NGO centres/groups; assessments remain planned |
| viewer | View only South centre/group |
| reviewer | View NGO records and view/export history; no editing |
| suspended | Sign in succeeds, but organisation access is suspended |
| academy-admin | Full Academy access; no NGO access |

## Load once (user runs on the server)

After fetching/checking out the supplied checked commit, build the API with the existing dedicated Node runtime:

```bash
cd /home/tech4learn/tech4learn-app
runuser -u tech4learn -- env PATH=/opt/tech4learn/node-v24.19.0/bin:/usr/bin:/bin npm run build --workspace @tech4learn/api
```

In a root terminal, save the generated login details to a private report. Never save this file in the repository, web root or a shared folder, or send its contents in chat:

```bash
bash <<'BASH'
set -euo pipefail
umask 077
mkdir -p /root/tech4learn-backups
report="/root/tech4learn-backups/demo-access-$(date -u +%Y%m%dT%H%M%S).json"
runuser -u tech4learn -- /opt/tech4learn/node-v24.19.0/bin/node --env-file=/etc/tech4learn/api.env /home/tech4learn/tech4learn-app/apps/api/dist/manage.js demo-create > "$report"
printf 'Demo created. Private account details: %s\n' "$report"
BASH
```

Open the printed report file privately in the server file manager or terminal. It contains the dataset ID, each account's email/password, expected access and invitation links. Your real superadmin sees both demo organisations after refreshing. No service restart is required to see inserted records. Your real organisation is not modified.

Creation is atomic. A failed create rolls back the database. A repeated create refuses if an active demo dataset already exists: it does not overwrite test edits or reset passwords. If output was lost, the repeat command identifies the existing dataset; passwords cannot be recovered from the database. Inspect/remove that dataset before creating a fresh one if needed.

## Suggested test sequence

1. Sign in as your real superadmin. Open the NGO demo; change its display name, colour and centre label. Refresh to check persistence.
2. Sign in as `admin` in a separate/incognito browser. Approve South's coordinates. Edit its radius and verify approval clears. Add coordinates to Remote. Create a centre and group.
3. Sign in as `coordinator`: only North appears; add/edit a North group. Sign in as `teacher`: only North Morning appears and there are no edit controls. The Academy must not appear for either account.
4. In Team as `admin`, change Teacher's scope/role. Refresh the Teacher session to check the change. Reactivate Suspended, then suspend it again; subsequent organisation requests are denied.
5. Create/edit a custom role and assign it. Protected Organisation admin and your own role cannot be edited. Change the backup admin to Viewer, then use your real superadmin to try suspending the remaining admin: the last-admin safeguard must refuse. Restore the backup admin afterwards if desired.
6. Open the valid invitation in an incognito window and create its synthetic account using a private password. Reuse should fail. The expired link should fail. Team can issue another link for a new reserved test email; the app does not send email.
7. Archive a test group, then its centre. Archiving a centre with active groups must fail. Archived records stay visible but cannot be edited.
8. Sign in as `reviewer` to view/export history. It cannot edit records. Sign in as `academy-admin` to verify the NGO is inaccessible.
9. Use Account security on a demo account to change its password. Existing sessions should be signed out. Keep any new test password privately; the initial report is not updated automatically.

Use the demo organisations only for synthetic testing. Do not invite demo identities into real organisations or create real records in the demo organisations.

## Inspect and remove later

Replace DATASET_ID with the ID from the private report. First inspect exactly what will be removed:

```bash
runuser -u tech4learn -- /opt/tech4learn/node-v24.19.0/bin/node --env-file=/etc/tech4learn/api.env /home/tech4learn/tech4learn-app/apps/api/dist/manage.js demo-inspect DATASET_ID
```

When you are ready to permanently delete those demo organisations and their test records:

```bash
runuser -u tech4learn -- /opt/tech4learn/node-v24.19.0/bin/node --env-file=/etc/tech4learn/api.env /home/tech4learn/tech4learn-app/apps/api/dist/manage.js demo-remove DATASET_ID --confirm=DATASET_ID
```

Removal uses a stored ID manifest, never a name prefix. It removes all records inside the two recorded organisations, including centres/groups/roles/invitations added while testing, plus generated demo accounts and accounts accepted from the two reserved invitation emails. It revokes their sessions. Other accounts that joined these organisations are retained, with only their demo membership removed; untracked test accounts you create yourself are also retained. A platform removal event and anonymised login audit entries remain.

Removal refuses if a generated identity has become a superadmin or has membership/activity outside the demo organisations. Review such use instead of deleting records from a real organisation. Removal is transactional and refuses unknown/already-removed IDs. A real organisation whose name starts with DEMO is not a removal target.

After removal, delete the private demo-access report through your normal private file-management process. It contains obsolete credentials.
