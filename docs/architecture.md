# Architecture decisions

## Stack and boundaries

One TypeScript repository using npm workspaces: React Native/Expo mobile, React/Vite admin, NestJS API and shared types. PostgreSQL, Redis jobs and S3-compatible storage are planned, not connected by this scaffold.

Start with a modular backend: identity, organisations, configuration, learners, attendance, learning assessment, integrations and reporting. Separate compute-heavy services only when needed. AI provider keys stay server-side; track per-organisation usage/cost when calls are introduced.

## Tenancy

Every tenant-owned record and job needs organisation scope. Resolve authorisation from authenticated membership, never a client organisation ID alone. Scope queries, exports, media URLs, caches and jobs. Audit superadmin access and partner grants. Test cross-organisation denial before exposing learner records.

A shared PostgreSQL schema with enforced tenant scope is proposed. Evaluate row-level policies and transaction context during persistence design. No tenant isolation is implemented yet.

## Configuration

Use stable IDs and typed fields; keep display labels separate from meaning. Version templates, forms and rubrics with draft/preview/publication. Archived fields remain readable in historical records. Do not execute arbitrary organisation-supplied code as workflow configuration.

## ExamElite

Connect through authenticated, versioned APIs. Its Laravel application owns its database and engine. No direct database writes. Define permissions, learner mapping, exam IDs, result revisions, retries and reconciliation after inspecting real contracts. This scaffold does not contact ExamElite.

## Reliability

Use idempotent submission identifiers, durable media uploads and background processing. Acknowledge saved data only after persistence. Preserve capture and receipt timestamps separately. Keep large media out of list/report endpoints. Safe retry/resume does not imply full offline assessments.

## Environments

The user hosts tech4learn.com through Virtualmin on Ubuntu 22.04.5. Start on this domain now; staging is deferred. Initially the NestJS process serves the built admin shell at / and API at /api/v1 behind Apache. A dedicated Node 24 runtime avoids changing system Node 20. Separate admin/API subdomains remain optional later. PostgreSQL/Redis executables exist, but service readiness still needs checking when those modules are implemented.

Separate local/staging/live secrets, databases and storage. VITE_ and EXPO_PUBLIC_ values are public. Process manager, reverse proxy, database versions, backups and storage provider remain open until hosting details are provided.

## Versions

Use the official Expo SDK 57 template's compatible React Native/React versions. Admin React matches mobile React to avoid workspace conflicts. Node.js 24, NestJS 12 and Vite 8 form the baseline; the npm lockfile is authoritative. Upgrade deliberately with compatibility checks.

References consulted: https://docs.expo.dev/versions/v57.0.0/ and https://docs.nestjs.com/first-steps .
