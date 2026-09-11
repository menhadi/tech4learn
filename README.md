# Tech4Learn

Configurable education SaaS for NGOs, government-school programmes, coaching centres, schools and CSR partners. First modules: attendance, interactive learning assessment and complete ExamElite integration.

## Current status

Initial scaffold only: Expo mobile shell, React admin shell with an API connection check, NestJS liveness endpoint, shared TypeScript contracts and documentation.

Authentication, tenant isolation, database persistence, camera/location capture, FLN, AI and ExamElite integration are **not implemented**. Do not onboard learners or expose this as an operational production service.

## Local setup

Use Node.js 24 and npm 11. From the repository root:

```sh
npm ci
```

Copy `apps/api/.env.example` to `apps/api/.env` and `apps/admin/.env.example` to `apps/admin/.env`. Run these in separate terminals:

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

## Structure

| Directory | Responsibility |
| --- | --- |
| apps/mobile | React Native / Expo app |
| apps/admin | React / Vite web administration |
| apps/api | NestJS API |
| packages/contracts | Shared compile-time API definitions |
| docs | Product decisions, architecture, milestones and deployment |

Start with [the blueprint](docs/product-blueprint.md), [architecture](docs/architecture.md), [roadmap](docs/roadmap.md) and [deployment workflow](docs/deployment.md). The public one/two-page site is a later deliverable. Existing DNS and hosting remain unchanged.
