# First identity and organisation release

Historical version-one guide. Existing installations should use [the access upgrade guide](access-release.md); its migration-2 recovery restrictions supersede the rollback advice below.

This release is developed locally. The user runs all server commands. Do not change Webmin's global ACME client, system Python, other websites, PostgreSQL cluster settings, or the existing Tech4Learn Cloudflare Origin certificate/proxy.

## What is included

- Login/logout, fixed 12-hour database sessions and password changes that sign out all sessions.
- First superadmin bootstrap in an interactive server terminal, with a hidden password prompt.
- Superadmin organisation creation and one-time invitations for organisation admins (three-day validity).
- Organisation admins view/update only their memberships' organisations. Brand colour and centre terminology are stored.
- Audit events and persisted login throttling.

No public signup, email sending, MFA, password recovery, learners, attendance, FLN or ExamElite implementation is included. The mobile app is unchanged. Initial directory responses are capped at 200 organisations; server-side pagination is required before exceeding that pilot limit.

## 1. Read-only database preflight

Run in the server's root terminal and inspect the output before creating anything:

```bash
pg_isready -h 127.0.0.1 -p 5432
systemctl status postgresql --no-pager
```

Only the PostgreSQL client was previously confirmed. If PostgreSQL is not accepting connections, stop and review installation/service readiness; do not install/reconfigure shared infrastructure blindly.

## 2. Create a dedicated database (once, after preflight)

These commands create a new limited login and a new database only. If either name exists, stop and inspect ownership/use rather than overwriting it. Do not use another website's database or credentials.

```bash
runuser -u postgres -- createuser --pwprompt --no-superuser --no-createdb --no-createrole tech4learn_app
runuser -u postgres -- createdb --owner=tech4learn_app tech4learn_app
runuser -u postgres -- psql -v ON_ERROR_STOP=1 -d tech4learn_app -c 'REVOKE ALL ON DATABASE tech4learn_app FROM PUBLIC; REVOKE CREATE ON SCHEMA public FROM PUBLIC; GRANT USAGE, CREATE ON SCHEMA public TO tech4learn_app;'
```

Choose the database password privately at the prompt. Set the following in `/etc/tech4learn/api.env` with a server editor; URL-encode special characters in the password. Do not send the file or password in chat. Retain the existing HOST, PORT, ADMIN_ORIGIN, NODE_ENV and ADMIN_DIST_PATH settings.

```dotenv
DATABASE_URL=postgresql://tech4learn_app:URL_ENCODED_PASSWORD@127.0.0.1:5432/tech4learn_app
```

Keep the file owned by root, group tech4learn, mode 640. This grants the service account access without making credentials public. PostgreSQL is reached over loopback; don't expose port 5432 publicly. Back up the new database and environment file using the server's private backup process.

## 3. Fetch and build the exact approved release

Replace `APPROVED_COMMIT` with the supplied release SHA. Record the current commit and back up the database before migrations. The initial deployment uses one checkout; perform this update in a maintenance window.

```bash
runuser -u tech4learn -- git -C /home/tech4learn/tech4learn-app rev-parse HEAD
runuser -u tech4learn -- git -C /home/tech4learn/tech4learn-app status --short
runuser -u tech4learn -- git -C /home/tech4learn/tech4learn-app fetch origin
runuser -u tech4learn -- git -C /home/tech4learn/tech4learn-app checkout --detach APPROVED_COMMIT
cd /home/tech4learn/tech4learn-app
runuser -u tech4learn -- env PATH=/opt/tech4learn/node-v24.19.0/bin:/usr/bin:/bin npm ci --workspace @tech4learn/api --workspace @tech4learn/admin --workspace @tech4learn/contracts --include-workspace-root --include=dev
runuser -u tech4learn -- env PATH=/opt/tech4learn/node-v24.19.0/bin:/usr/bin:/bin VITE_API_URL=/api/v1 npm run build
runuser -u tech4learn -- env PATH=/opt/tech4learn/node-v24.19.0/bin:/usr/bin:/bin npm test
```

Stop on any error. If git status shows edits, reconcile them first. These shell lines are sequential instructions, not an error-handling script.

## 4. Migrate, bootstrap, then restart Tech4Learn only

```bash
runuser -u tech4learn -- /opt/tech4learn/node-v24.19.0/bin/node --env-file=/etc/tech4learn/api.env /home/tech4learn/tech4learn-app/apps/api/dist/manage.js migrate
runuser -u tech4learn -- /opt/tech4learn/node-v24.19.0/bin/node --env-file=/etc/tech4learn/api.env /home/tech4learn/tech4learn-app/apps/api/dist/manage.js bootstrap
```

Enter your own superadmin email, name and a password of at least 15 characters. There are no default credentials. Do not run bootstrap again once an administrator exists. Migrate is repeatable and versioned; it never drops existing tables. Neither command touches Apache or other domains.

Only after both succeed:

```bash
systemctl restart tech4learn
curl --fail --show-error http://127.0.0.1:3101/api/v1/health
curl --fail --show-error https://tech4learn.com/api/v1/health
```

Open https://tech4learn.com, sign in, create a test organisation, accept its invitation in another browser session and confirm the organisation admin sees only that organisation. Refresh to verify persistence; sign out and confirm protected pages require sign-in. Health is liveness only and does not prove database readiness; successful sign-in and a saved organisation verify the database connection.

The already-working routing is `virtualmin create-proxy --domain tech4learn.com --path / --url http://127.0.0.1:3101/`. Do not rerun it or change certificates during this release.

## Rollback and limits

To roll back, check out the recorded prior commit, reinstall/build its locked dependencies and restart only tech4learn. Keep the new database and a backup; do not drop identity tables as an automatic rollback. The older scaffold won't use them.

The current release is for initial organisation administration. Before wider use, add recovery/MFA, account/membership revocation, directory pagination, retention/cleanup jobs and operational alerting/backups. Review production login limits before increasing traffic. Cloudflare must bypass caching for `/api/*` and must not use a Cache Everything rule for authenticated responses.
