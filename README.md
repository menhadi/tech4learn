# Tech4Learn

Configurable education SaaS for NGOs, government-school programmes, coaching centres, schools and CSR partners. First modules: attendance, interactive learning assessment and complete ExamElite integration.

## Current status

The first administration release implements PostgreSQL-backed login/logout, password changes, superadmin organisation creation, single-use organisation-admin invitations, tenant-scoped organisation profiles, and audit events. React administration supports these workflows. The Expo mobile app remains a shell.

Camera/location capture, learners, FLN, AI, ExamElite, custom roles, configurable forms, email delivery, self-service password recovery and MFA are **not implemented**. Organisation access checks cover the current identity/profile endpoints; future modules must implement their own scope checks. Do not onboard learners yet.

## Local setup

Use Node.js 24 and npm 11. From the repository root:

```sh
npm ci
```

Copy `apps/api/.env.example` to `apps/api/.env` and `apps/admin/.env.example` to `apps/admin/.env`. Run these in separate terminals:

For login, first provision a dedicated local PostgreSQL database and set `DATABASE_URL` in `apps/api/.env`. Build and initialise it (bootstrap prompts for the first superadmin; there are no default credentials):

```sh
npm run build --workspace @tech4learn/api
node --env-file=apps/api/.env apps/api/dist/manage.js migrate
node --env-file=apps/api/.env apps/api/dist/manage.js bootstrap
```

Then start the required clients:

```sh
npm run dev:api
npm run dev:admin
npm run dev:mobile
```

- Admin: http://localhost:5173
- API liveness: http://localhost:3000/api/v1/health
- Mobile: follow Expo's terminal instructions. Camera/location work will require development builds and physical device validation.
- Physical phones cannot reach your computer through `localhost`. Mobile API settings will be introduced with the authenticated client.

## Verification

```sh
npm run check
```

This typechecks all workspaces, tests the HTTP API and builds API/admin. It does not produce an APK or verify native hardware.

The identity integration tests use PGlite's embedded PostgreSQL engine with synthetic, disposable data. Production uses `pg` and a separate PostgreSQL service. These tests do not substitute for deployment validation against the live database/server.

See [identity deployment](docs/identity-deployment.md) for database creation, migration, initial superadmin and rollback. Do not rerun the first-install script on an existing deployment.

## Structure

| Directory | Responsibility |
| --- | --- |
| apps/mobile | React Native / Expo app |
| apps/admin | React / Vite web administration |
| apps/api | NestJS API |
| packages/contracts | Shared compile-time API definitions |
| docs | Product decisions, architecture, milestones and deployment |

Start with [the blueprint](docs/product-blueprint.md), [architecture](docs/architecture.md), [roadmap](docs/roadmap.md) and [deployment workflow](docs/deployment.md). The [Virtualmin first-deployment guide](docs/virtualmin-first-deployment.md) covers user-run installation. The public one/two-page site is a later deliverable. No server changes are performed by local scripts unless the user explicitly runs the server installer there.
