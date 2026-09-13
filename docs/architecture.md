# Architecture decisions

## Stack and boundaries

One TypeScript repository using npm workspaces: React Native/Expo mobile, React/Vite admin, NestJS API and shared types. PostgreSQL now stores identity, sessions, organisations, invitations and audit events. Redis jobs and S3-compatible storage remain planned.

Start with a modular backend: identity, organisations, configuration, learners, attendance, learning assessment, integrations and reporting. Separate compute-heavy services only when needed. AI provider keys stay server-side; track per-organisation usage/cost when calls are introduced.

## Tenancy

Every tenant-owned record and job needs organisation scope. Resolve authorisation from authenticated membership, never a client organisation ID alone. Scope queries, exports, media URLs, caches and jobs. Audit superadmin access and partner grants. Test cross-organisation denial before exposing learner records.

A shared PostgreSQL schema currently uses explicit membership predicates on organisation reads and transactions on writes. Superadmin status is read from the stored account on every authenticated request. Client role/organisation claims are not trusted. Tests cover cross-organisation read/write denial. PostgreSQL row-level security is not implemented; future learner/media tables require their own tenant checks and a separate RLS review.

## Identity release

Passwords use asynchronous Node scrypt (N=32768, r=8, p=3) with random salts. Random 256-bit session tokens are stored only as SHA-256 hashes, with fixed 12-hour expiry. Production cookies are Secure, HttpOnly, SameSite=Lax and use a __Host- prefix. Password changes revoke every session. Browser writes require an exact allowed Origin, JSON and a custom header; CORS only allows the configured admin origin with credentials. API responses use no-store.

Superadmins create organisations and appoint admins using one-time, three-day invitations. Token hashes are stored in PostgreSQL; browser links put the token in the fragment, not server access logs. Existing accounts must authenticate as the invited email before accepting. Email delivery is not wired; the administrator shares the link. Migration 2 adds configurable roles, staff invitations and membership suspension with organisation/centre/group scope. See [the access release](access-release.md) for enforcement, safety rules and deployment. MFA and self-service password recovery remain later work.

Schema migration and first-superadmin bootstrap are explicit CLI actions, never public endpoints or automatic startup side effects. Bootstrap is guarded by a database lock and closes after a superadmin exists. Login limits are persisted and apply per email and globally; tune with usage data and add per-client edge limits before a broad rollout. Audit events record login, invitations and profile mutations. No password or token is stored in audit records.

PGlite is a development-only embedded PostgreSQL engine for the integration tests. The production adapter always uses the pg driver. Tests also inject the embedded adapter for an HTTP-level end-to-end run; production database connectivity needs a separate deployment check.

## Configuration

Migration 3 adds learner profiles, single-current-group enrolment history, typed field definitions and expiring import previews. Learner access uses current membership plus the current group's centre/group scope. Guardian contacts require a separate permission. Imports are actor-owned, revalidated and committed atomically under the organisation lock. See [learner release details](learner-release.md) for constraints, retention and the application-isolation/RLS boundary.

Use stable IDs and typed fields; keep display labels separate from meaning. Version templates, forms and rubrics with draft/preview/publication. Archived fields remain readable in historical records. Do not execute arbitrary organisation-supplied code as workflow configuration.

Migration 4 adds organisation presentation settings, superadmin module availability, verified custom-host bindings and reusable per-module field definitions/values. Learner field IDs and values are preserved. Additional-details forms for other records enforce their parent record scope and version checks. See [configuration release](configuration-release.md) for domain activation, retention, deployment and operational boundaries.

## Attendance pilot

Migration 9 adds bounded extra attendance images and a PostgreSQL-backed face job queue with one leased worker slot. Each image retains its own location evidence; combined suggestions never write marks. Queued jobs survive restarts; interrupted processing requires an explicit retry. See [bulk attendance](bulk-attendance-release.md).

Migration 8 adds purpose-specific consent and bounded private learner photos. A stateless CompreFace verification adapter produces scoped attendance suggestions without storing remote face collections or writing marks. Engine installation and calibration are separate; see [student photos](student-photos-release.md).

Migration 7 adds the academic hierarchy while retaining learning group IDs as section IDs. Composite keys bind each class to its organisation, year and centre, and each section to a class at its own centre. Existing records remain unassigned until explicitly linked; new capture labels include year/class/section. See [academic release](academic-release.md) for scope, archive and promotion rules.

Migration 6 adds photo analysis history and an explicit server-side integration boundary for four vision providers. Keys remain private environment configuration for now; central editable API settings are planned. No analysis operation changes attendance marks. See [workspace release](workspace-ai-release.md) and [self-hosted recognition evaluation](self-hosted-recognition.md). The revised hierarchy and student portal are specified in [registration workflow](registration-workflow.md); current learning group IDs must remain stable when explicit sections are introduced.

Migration 5 introduces scoped attendance intents, immutable capture snapshots/evidence, private bounded JPEG storage and append-only review history. The online admin browser can capture and review; native camera work remains planned. See [attendance release](attendance-release.md) for permission checks, idempotency, uncertainty rules, storage limits and field verification.

## ExamElite integration

The revised delivery target is same-domain Tech4Learn screens backed by ExamElite APIs, with central module controls and organisation-owned copies in both sharing directions. [Central content](examelite-central-content.md) documents the first implemented portion, its additive remote transfer ledger, permission checks and remaining authoring/attempt adapters. The external native workspace described below is legacy infrastructure, not the accepted final interface.

Connect through authenticated, versioned APIs. Its Laravel application owns its database and engine. No direct database writes from Tech4Learn. The [read connector](examelite-read-release.md) adds private credentials, explicit organisation/exam/student grants, exam catalogue reads and summary-result reads. User screenshots confirm live catalogue and completed-result retrieval for the pilot. Exam launch, student provisioning, result revisions and durable reconciliation are not implemented by this slice.

Tech4Learn is the source of truth for its own students and organisation/enrolment/attendance records. Future provisioning sends only required exam identity data to an ExamElite-owned API; exam attempts and marking remain authoritative in ExamElite. Use stable organisation and learner IDs with idempotent provisioning and an explicit external identity mapping. Email alone must not automatically merge accounts or grant access; existing-account linking requires verified ownership or an authorised reviewed mapping. Retry must not create duplicate students.

The central connection is controlled by Tech4Learn superadmin. Organisation grants determine which ExamElite content and capabilities are available; a shared catalogue never authorises cross-organisation student or result reads. Match each result to the organisation-scoped learner mapping. Existing ExamElite accounts remain independent and receive no Tech4Learn membership or attendance features automatically. Attendance photos, location evidence and programme records are outside exam identity provisioning. Secrets remain server-side, and a superadmin browser session is not an integration credential.

The [central sharing release](examelite-platform-release.md) implements these controls locally in migration 13 and a dedicated ExamElite identity bridge. Provisioning uses stable organisation/learner keys and a transactional remote mapping; reviewed pilot links are adopted explicitly, never inferred from email. The initial provisioning operation is triggered by superadmin's Connect student action. New student sign-in/SSO and online exam launch remain unimplemented; creating an exam identity does not yet expose a login to the learner.

The [native ExamElite workspace](examelite-native-workspace.md) is implemented locally with default-enabled capabilities, revisioned superadmin restrictions, isolated native sessions and organisation-owned copies. ExamElite supplies authoring and student templates and owns the engine. Migration 14 and the additive provider require user-run deployment; live end-to-end verification remains outstanding. The release document records session revocation limits and provider-dependent functionality.

## Reliability

Use idempotent submission identifiers, durable media uploads and background processing. Acknowledge saved data only after persistence. Preserve capture and receipt timestamps separately. Keep large media out of list/report endpoints. Safe retry/resume does not imply full offline assessments.

## Environments

The user hosts tech4learn.com through Virtualmin on Ubuntu 22.04.5. Start on this domain now; staging is deferred. Initially the NestJS process serves the built admin shell at / and API at /api/v1 behind Apache. A dedicated Node 24 runtime avoids changing system Node 20. Separate admin/API subdomains remain optional later. PostgreSQL/Redis executables exist, but service readiness still needs checking when those modules are implemented.

Separate local/staging/live secrets, databases and storage. VITE_ and EXPO_PUBLIC_ values are public. Process manager, reverse proxy, database versions, backups and storage provider remain open until hosting details are provided.

## Versions

Use the official Expo SDK 57 template's compatible React Native/React versions. Admin React matches mobile React to avoid workspace conflicts. Node.js 24, NestJS 12 and Vite 8 form the baseline; the npm lockfile is authoritative. Upgrade deliberately with compatibility checks.

References consulted: https://docs.expo.dev/versions/v57.0.0/ and https://docs.nestjs.com/first-steps .

## Superadmin engine management

[Face-engine controls](face-control-release.md) add a restricted local controller, status and suggested CPU/RAM limits, stop/start/restart, and a durable queue pause in migration 10. The engine is installed per the user’s server screenshot; control deployment, API-key connection and real-image calibration remain separate checks. Larger host capacity can be detected by refreshing the superadmin page.

## Unified student portrait setup

Migration 11 adds an 80-character photo name, preserving existing photos and reference checks. The authenticated photo-setup endpoint records selected purpose permissions and saves one portrait for one or both uses in a single organisation-locked transaction. Consent versions are checked independently; both copies roll back if either use fails. Images retain separate purpose ownership so withdrawing attendance consent does not delete the profile image. The UI groups identical content and runs the existing face check after a successful save; engine failure leaves the saved reference unchecked. Browser camera streams stop on capture, cancellation and unmount; camera data remains local until saving.

## Form drafts and directory queries

See [UI template](ui-template.md). Device drafts are scoped to authenticated user, organisation and record; they exclude credentials and confirmations. Learner, attendance and audit directory APIs apply authorised scope before filtering/counting/pagination, using whitelisted expressions and bound parameters. Draft restoration never changes server authorisation.
