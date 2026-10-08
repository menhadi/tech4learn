# Architecture decisions

## Stack and boundaries

### Fresh organisation transition — 7 October 2026

The user has clarified that the previous application contributes its existing
platform administrator and attendance module only. New organisations belong to
the copied Laravel application; previous PostgreSQL organisations must not be
automatically mapped or imported. Retire the previous React product shell and
remote exam connector from the replacement user flow after native acceptance.
Keep the dependencies attendance actually uses: canonical authentication,
permission checks, scoped centre/section/learner references, media and audit.
Provision a fresh attendance-side organisation record only as an explicit,
idempotent companion to a new native organisation, never by matching old names
or emails. This companion is an internal attendance boundary rather than a
second organisation-management interface. An explicit trusted-operator companion
provisioning command and native creation/retry delivery are implemented locally.
Updates, deactivation and staff/learner lifecycle integration remain pending.
Existing mappings are explicit reviewed controls.
Fresh organisation creation now records one pending attendance onboarding request
in the same native transaction. It contains the native organisation reference and
delivery state only, with no password, learner data or copied private configuration.
Request failure rolls back organisation/configuration creation. A canonical
platform-session endpoint can provision a fresh companion using the existing
idempotent transaction, with stored superadmin checks. The native retry consumer
now has a delivery service that locks one pending request, retains failed attempts
without remote error text, and completes only after a validated companion response.
Completed retries are not resent. The bridge rechecks native and canonical platform
authority before/after delivery. Fresh creation attempts delivery after native commit
for the signed-in platform administrator; failures retain the organisation and pending
request. The organisation table offers a CSRF-protected, throttled retry action for
active pending requests. Staff/learner lifecycle synchronization remains pending;
companion creation alone does not grant staff attendance access.

Fresh Laravel organisation creation uses independent configuration defaults and
the new organisation's own contact fields. It must never replicate another
organisation's configuration, provider credentials or private settings. The
organisation, configuration and creation audit are saved in one transaction.
The creation audit uses a required write rather than the optional general audit
helper; an audit failure rolls back both the organisation and its configuration.
Local regression tests verify credential isolation and rollback on configuration
failure. After commit, the authenticated platform flow attempts attendance companion
delivery; failed delivery retains a pending request for explicit retry.

The platform's new-account form grants the global native `admin` role only for
organisation owners/admins, never for a selected `staff` membership. Account,
role and membership writes are transactional. This fixes fresh provisioning;
existing role assignments are not automatically revoked or altered.

Native web and student API start/resume paths lock the current active student's
tenant-scoped row inside a transaction before attempt checks and creation. Both
paths use the same lock so overlapping requests serialize without relying on
`firstOrCreate` alone. Local SQLite regressions verify resume, expiry and attempt
limits; true concurrent MySQL acceptance remains a separate unverified check.

Shared AI provider configuration is selected only from the unique active realm
explicitly marked `is_primary_platform`. Missing or ambiguous platform realms,
or missing platform configuration, yield no shared provider. Never fall back to
the first tenant's configuration or infer the platform solely from its slug.

The general tenant configuration loader likewise returns neutral, unsaved
defaults when a scoped configuration or recognised host is missing. It does not
fall back to platform or another tenant's settings. The legacy unscoped lookup
is retained only for schemas without the organisation column.

Attendance context responses recheck stored native organisation, account and
membership access after the remote API returns. Suspension or membership
revocation during that request blocks release of the context. Local regression
and connected PHP/API checks cover this boundary.

Unsigned guest exam print requests apply the public paper publication and package
download gates before rendering or creating a slug. An unpublished draft cannot
be exposed through the print route. Signed internal document rendering and native
staff previews retain their existing access paths.

Question language switching returns question text/options only for a question
in the current student's or guest's open tenant-scoped attempt. It never returns
original or translated explanations. Languages and passage content remain scoped
to the same organisation. The guest engine also needs nullable registered-student
references on attempts/stats and guest identity on stats; a checked migration
completes those missing fresh-schema fields without dropping existing evidence.

Guest answer/submission requests require a non-empty guest identity and match
both the tenant-scoped attempt and its stats, with no registered student owner.
Missing guest identity must never match the null guest fields of registered
student records. Guest feedback/contact/proctor/tolerance/report operations also
require guest identity; proctor storage occurs only after open-attempt checks.
Local HTTP checks cover guest MCQ save/completion, foreign-tenant and wrong-guest
denial, post-completion answer denial and missing-identity student protection.

Guest exam entry requires a completed package order and an active package;
pending orders cannot grant access. Fresh order schema now supports the fields
the copied checkout writes: guest identity, payment status and discount, nullable
registered-student identity, and optional legacy billing snapshots. The checked
migration preserves existing values. Local HTTP acceptance covers order-gated
guest start, generated attempt/stats, MCQ save and completed result. Payment
provider integration and guest browser/production acceptance remain unverified.

Registered student web instructions, language preparation and web/API start or
resume share an exam access check. Packaged papers require an active package in
the exam's organisation and that student's completed order in the same organisation.
Pending, guest and unscoped orders cannot grant this access. Unpackaged papers
retain native organisation access, and student practice ownership is enforced
on both entry paths. This does not add package expiry semantics or verify payment
providers; local checks cover direct entry and package revocation on resume.
Student API exam details load only question identifiers, subject metadata and
marks, withholding question text, options, translations and explanations before
an attempt starts. Details and attempt-count endpoints recheck the shared access
policy, including active package status, after their existing discovery scope.
The web My Exams details and attempt-count endpoints use that same policy,
preserving their metadata response while requiring completed course activation.

The user explicitly authorized permanent deletion of all previous organisations
and linked records. Prepare the private backup, scoped cleanup and post-cleanup
checks before executing it. No production cleanup has been executed. Preserve the existing administrator's
canonical UUID and password; the native ledger and API global realm remain
separate from all attendance tenant mappings.

Cleanup must include old memberships/invitations, academic and learner records,
attendance evidence/reviews/jobs, private media and old exam connector ledgers.
Revoke old organisation access sessions; preserve the canonical platform admin
UUID and password hash. Shared/global dependencies require review before removal.
Database deletion and private-media cleanup are distinct operations; neither
must target the original ExamElite installation or the copied native database.

### Revised application foundation — 7 October 2026

The agreed direction is to copy the complete ExamElite Laravel application into
the Tech4Learn product and preserve its native SaaS/exam screens and engine.
The TypeScript repository below contains the existing attendance implementation
and mobile scaffold; it must remain available while integration is developed.
Do not replace the deployed service, merge databases or migrate identities as an
automatic consequence of the source copy. Laravel numeric identities and
Tech4Learn UUIDs need explicit organisation/user/student mapping and permission
checks before attendance access is exposed. No email-based automatic merging.

The [attendance bridge](foundation-attendance-bridge.md) adds explicit, revocable
organisation/staff mappings in API migration 17. When configured, native staff
sign-in verifies the existing API password and opens both mapped sessions. The
native attendance page mounts the existing React attendance interface through a
protected same-origin gateway, retaining API permissions and location scopes.
The [learner identity ledger](foundation-learner-identity.md) in API migration 18
adds explicit learner/native-student links and scoped native resolution.
The locally implemented platform identity API in migration 19 separates global
administrator mappings from attendance tenants using an immutable realm registry.
Native primary-site sign-in and web administrator request revalidation are
implemented and checked locally; reviewed provisioning and production activation
of that boundary remain pending.
Account provisioning, enrolment synchronization and mobile integration remain pending.

Initial source preparation is isolated beneath ignored `.local`; source origin,
working-tree edits and copied-file hashes are recorded there. Dependencies,
credentials, uploads, databases, compiled server caches and learner data are not
part of the source import. Audit copied code for embedded credentials before
versioning it. A local boot is not complete exam, attendance, FLN or mobile
acceptance. The former remote-connector architecture is historical during this
transition.

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

## Historical ExamElite connector implementation

The previous delivery target used same-domain Tech4Learn screens backed by remote ExamElite APIs. The 7 October foundation decision above supersedes that target. Existing connector code and transfer ledgers remain during migration until their replacement is verified. [Central content](examelite-central-content.md) records the implemented historical portion, its permission checks and remaining authoring/attempt adapters.

Connect through authenticated, versioned APIs. Its Laravel application owns its database and engine. No direct database writes from Tech4Learn. The [read connector](examelite-read-release.md) adds private credentials, explicit organisation/exam/student grants, exam catalogue reads and summary-result reads. User screenshots confirm live catalogue and completed-result retrieval for the pilot. Exam launch, student provisioning, result revisions and durable reconciliation are not implemented by this slice.

Tech4Learn is the source of truth for its own students and organisation/enrolment/attendance records. Future provisioning sends only required exam identity data to an ExamElite-owned API; exam attempts and marking remain authoritative in ExamElite. Use stable organisation and learner IDs with idempotent provisioning and an explicit external identity mapping. Email alone must not automatically merge accounts or grant access; existing-account linking requires verified ownership or an authorised reviewed mapping. Retry must not create duplicate students.

The central connection is controlled by Tech4Learn superadmin. Organisation grants determine which ExamElite content and capabilities are available; a shared catalogue never authorises cross-organisation student or result reads. Match each result to the organisation-scoped learner mapping. Existing ExamElite accounts remain independent and receive no Tech4Learn membership or attendance features automatically. Attendance photos, location evidence and programme records are outside exam identity provisioning. Secrets remain server-side, and a superadmin browser session is not an integration credential.

The [central sharing release](examelite-platform-release.md) implements these controls locally in migration 13 and a dedicated ExamElite identity bridge. Provisioning uses stable organisation/learner keys and a transactional remote mapping; reviewed pilot links are adopted explicitly, never inferred from email. Migration 15 adds a separate, exam-scoped one-use student sign-in boundary and revocable grants. Staff can issue same-domain links; authenticated native adapters now handle start/resume, answer saving, submission, published result history and staff marking. See [student access and delivery status](examelite-student-attempts.md) for tested scope and outstanding workflows. Creating an ExamElite identity alone does not grant student access.

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
