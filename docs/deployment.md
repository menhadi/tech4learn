# User-operated deployment

Initial workflow: local changes → checks → Git commit/push → user deploys to tech4learn.com → assistant reviews live read-only. The user has deferred staging. Once created, staging verification will precede promotion of the same approved commit to live.

## Current state

Repository: https://github.com/menhadi/tech4learn (public), with `main` as the initial branch. Read-only inventory confirmed Ubuntu 22.04.5, Node 20.20.2, Apache/systemd, PostgreSQL client 14.24 and Redis executable 6.0.16. Existing hosting/DNS use Virtualmin. The dedicated Node 24 deployment is documented in [the first-deployment guide](virtualmin-first-deployment.md). The assistant has not changed live files or DNS. This scaffold is not an operational education service.

The user deploys. The assistant develops locally and provides release-specific commands once hosting is known. Live access is inspection-only; fixes follow the local/Git workflow.

## Verification and outputs

```sh
npm ci
npm run check
```

API output: apps/api/dist/main.js. Admin static output: apps/admin/dist. These commands do not build an APK. Android signing/build/distribution will be configured separately.

## Hosting prerequisites

- Node.js 24 and a persistent process manager; confirm hosting compatibility.
- HTTPS, explicit admin CORS origin and environment-specific public API address.
- Secrets outside Git; VITE_ and EXPO_PUBLIC_ are public configuration.
- Separate data/storage/credentials for staging and live once added.
- Restrict staging and use synthetic data.
- Liveness currently checks only the API process, not database readiness.
- Do not serve live admin traffic using Vite's development server.

## Release handoff

Supply commit ID, changes, checks, configuration changes, host-specific install/build/restart commands, migrations, verification and rollback considerations. Deploy the approved commit, not an unreviewed moving branch tip. Admin assets need the correct environment's public API URL at build time.

## Rollback

Record the previous commit and back up data before migrations. Application and database rollback are separate: a migration may need forward repair or restore. This scaffold has no migrations. Restore previous code/assets and restart the configured service if needed.
