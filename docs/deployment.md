# User-operated deployment

Local changes → checked Git commit/push → user deploys staging → verification → user deploys the same approved commit to live → assistant reviews live read-only.

## Current state

Repository: https://github.com/menhadi/tech4learn (public), with `main` as the initial branch. The user confirms tech4learn.com is hosted through Virtualmin and existing hosting/DNS are configured. Server access, operating system, runtime availability and staging addresses still need confirmation. No staging/live files or DNS have changed. This scaffold is not an operational education service.

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
