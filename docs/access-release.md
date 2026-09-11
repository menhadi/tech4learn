# Roles, staff access and centre/group release

## Working functionality

Organisation admins and platform superadmins can manage role templates, create custom roles, invite colleagues, change staff roles/scopes and suspend/reactivate a membership. Existing accounts and organisation-admin memberships are preserved by migration 2. Pending version-one admin invitations retain their intended role.

The web workspace has Profile, Centres, Groups, Roles, Team and History sections, shown according to permissions. Centres hold address labels, coordinates, a permitted radius and explicit location approval. Changing coordinates or radius clears approval. Groups belong to one centre; moving a group between centres is intentionally unsupported because that could change another staff member's scope. Archival retains records and history. Active groups must be archived before their centre.

The protected Organisation admin role has all current permissions. Programme manager, Centre coordinator, Teacher, Assessor and Viewer are editable starting templates. Teacher and Assessor currently have view access to the foundation records; those names do not imply working assessment or exam features.

Each membership combines a role with whole-organisation, selected-centre or selected-group scope. Whole-organisation permissions (profile editing, centre creation, roles, team and audit) cannot be assigned to a restricted scope. Group-scoped staff may edit their assigned groups if permitted, but cannot create new groups or edit the parent centre. Managing staff requires view permissions for roles, centres and groups; managing groups requires centres.view.

Backend checks resolve current membership and role on each request. Organisation IDs, role IDs, scope IDs and client-supplied superadmin/approval flags are never sufficient authorisation. Tenant-composite foreign keys also prevent cross-organisation role and group relationships. Mutation transactions lock the organisation before checking access, serialising access changes against writes.

An admin cannot edit their own role or membership through these controls, grant more permissions than they hold, modify a stronger role/member, modify the protected safety role, or remove the last active organisation admin. Changing a role affects its existing members. Suspension blocks subsequent organisation requests from existing sessions; membership in other organisations is retained. Pending invitation acceptance rechecks the issuer's current authority and active scope records. Existing memberships must be edited through Team instead of re-invited.

Role, membership, invitation, centre and group mutations are recorded in organisation history. Role and membership changes include before/after values. History is paged in batches of 50, with JSON export of a page. Invitation links must still be shared privately; email sending is not connected.

## User-run deployment

Only update Tech4Learn. Do not change Apache, certificates, Webmin's ACME settings, system Node/Python or other databases. No bootstrap or database creation is needed again. The assistant performs local development only; the user runs these steps on the server.

Use a maintenance window because the checkout and service are shared between builds. First inspect the current checkout and obtain the supplied checked release SHA:

```bash
runuser -u tech4learn -- git -C /home/tech4learn/tech4learn-app status --short
runuser -u tech4learn -- git -C /home/tech4learn/tech4learn-app rev-parse HEAD
```

Stop if there are local changes. Back up only the Tech4Learn database to a private root directory before updating. Run this block in the root terminal; it stops if a command fails:

```bash
bash <<'BASH'
set -euo pipefail
umask 077
mkdir -p /root/tech4learn-backups
backup_file="/root/tech4learn-backups/before-access-$(date -u +%Y%m%dT%H%M%S).dump"
cd /tmp
runuser -u postgres -- pg_dump --port=5432 --format=custom tech4learn_app > "$backup_file"
test -s "$backup_file"
pg_restore --list "$backup_file" >/dev/null
printf 'Backup saved: %s\n' "$backup_file"
BASH
```

Replace APPROVED_COMMIT in the following block with the checked release SHA. Building/tests use synthetic embedded PostgreSQL, not live data. The service stays stopped if the build, tests or migration fail; resolve the error before starting it.

```bash
bash <<'BASH'
set -euo pipefail
repo=/home/tech4learn/tech4learn-app
test -z "$(runuser -u tech4learn -- git -C "$repo" status --porcelain)"
runuser -u tech4learn -- git -C "$repo" fetch origin
runuser -u tech4learn -- git -C "$repo" cat-file -e APPROVED_COMMIT^{commit}
systemctl stop tech4learn
runuser -u tech4learn -- git -C "$repo" checkout --detach APPROVED_COMMIT
cd "$repo"
runuser -u tech4learn -- env PATH=/opt/tech4learn/node-v24.19.0/bin:/usr/bin:/bin npm ci --workspace @tech4learn/api --workspace @tech4learn/admin --workspace @tech4learn/contracts --include-workspace-root --include=dev
runuser -u tech4learn -- env PATH=/opt/tech4learn/node-v24.19.0/bin:/usr/bin:/bin VITE_API_URL=/api/v1 npm run build
runuser -u tech4learn -- env PATH=/opt/tech4learn/node-v24.19.0/bin:/usr/bin:/bin npm test
runuser -u tech4learn -- /opt/tech4learn/node-v24.19.0/bin/node --env-file=/etc/tech4learn/api.env "$repo/apps/api/dist/manage.js" migrate
systemctl start tech4learn
curl --fail --show-error --retry 8 --retry-connrefused --retry-delay 2 --max-time 15 http://127.0.0.1:3101/api/v1/health
curl --fail --show-error --retry 5 --retry-delay 2 --max-time 15 https://tech4learn.com/api/v1/health
BASH
```

Sign in with the existing accounts, verify the existing organisation, open Roles, create a pilot centre/group, and invite a Viewer restricted to that group. In a separate browser session, verify that Viewer cannot edit settings or open Team, Roles or History. Sign in again after refresh to verify persistence. Health alone only proves the process is responding.

## Recovery and current limits

Migration 2 is transactional and version-guarded. **Do not run version-one application code after migration 2**: older authorisation code does not understand suspension and scoped roles. Keep the service stopped and apply a forward fix if this release fails. A database restore requires an explicitly reviewed recovery procedure and may discard changes made since the backup; it is not an automatic rollback step.

Programme-level scope, learners, custom fields, photo/register/CCTV attendance, interactive FLN, ExamElite integration, automated email, MFA/recovery and native workflows remain planned. No new dependencies or infrastructure were introduced. Centre/group/team directories currently load the organisation's accessible records; add pagination/search before large-scale onboarding. Organisation listing retains the previous 200-record pilot cap. Audit pagination uses offsets, so concurrent new events may shift page boundaries. Archived records are retained; restoration UI and retention policy remain future work.
