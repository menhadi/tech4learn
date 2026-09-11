# Initial scaffold verification — 2026-09-11

 Historical results below are retained for earlier releases. Current results are at the end of this document.

- npm run check: passed workspace typechecks, HTTP API test and API/admin builds.
- HTTP test: liveness contract passes; unimplemented organisation endpoint returns 404.
- Browser inspection: admin shell renders and its connection button reports the running API successfully.
- Expo Android export: passed (580 modules; Hermes bundle produced). This is a JavaScript bundle export, not an APK build or physical-device test.
- Dependency review: patched multer 2.3.0 override installed; npm reports 10 moderate Expo-related dependency findings and no high findings. See dependency-notes.md.
- No database, learner records, authentication, attendance, ExamElite or FLN functionality tested: these are not implemented yet.
- No remote repository push or staging/live deployment performed.

Next validation: organisation/authentication isolation when implemented; native Android build and hardware checks when camera/location capture is added.

## Virtualmin preparation follow-up

- npm run check passed after adding optional built-admin serving: all workspace typechecks, three HTTP/configuration tests and both builds.
- Tests cover static index serving, API liveness, missing API paths, blocking dotfiles/source files, and rejecting an invalid asset path.
- bash -n passed for the first-deployment installer. No systemd installation or Virtualmin configuration was executed by the assistant; the user must run and verify those steps on the server.

## Identity and organisation release — 2026-09-11

- `npm run check` passed: all workspace typechecks, 13 reported tests including nested identity checks, and API/admin builds.
- Identity tests run the real SQL schema on PGlite's embedded PostgreSQL engine and exercise Nest HTTP endpoints. Production pg-driver/network connectivity is not established by these tests.
- Covered: credential rejection, Origin/custom-header CSRF checks, hashed single-use invitations, expiry/reissue, existing-account proof, role spoofing, cross-organisation read/write denial, atomic duplicate-slug rejection, settings validation, persistent sessions across API restart, logout/expiry, password changes invalidating all sessions, exact password whitespace, login throttling, audit events and production cookie flags.
- Local browser checks used disposable synthetic records: sign-in, empty directory, organisation creation with invitation, profile save and reload persistence. Desktop 1280px and narrow 390px layouts inspected; no horizontal document overflow at 390px.
- Existing public deployment was verified before this development work: HTTPS homepage serves the admin scaffold and public health returns status ok. This new identity release has not been deployed or migrated live.
- Full workspace dependency audit: 10 existing moderate Expo findings, zero high/critical findings; see dependency-notes.md.
- Native app code was not changed. No native build/hardware claim is made.
- Deployment still requires the user to confirm PostgreSQL service readiness, create a dedicated database, run migration 1 and bootstrap their own superadmin. No live writes were performed by the assistant.

## Access release — 11 September 2026

- `npm run check` passed: workspace typechecks, 22 reported tests (including nested tests), API and admin builds.
- PostgreSQL-engine HTTP tests cover migration of existing admin memberships/invitations; editable roles; centre/group-scoped lists and writes; cross-tenant IDs; invalid permission keys; protected roles, self-promotion and last-admin safeguards; issuer revocation; immediate role/suspension enforcement; coordinate approval invalidation; archive retention; and audited access changes.
- Local browser smoke checks with disposable synthetic data: sign in, create an organisation, save centre/group/custom role, prepare a group-scoped invitation, and sign in as that member. Restricted account shows only Profile/Centres/Groups with profile editing disabled and no group mutation controls. Desktop and 390px-wide layout inspected.
- No native changes or device build. No live changes or migrations performed by the assistant. User deployment and real PostgreSQL upgrade verification remain required; use `docs/access-release.md`.
- No new dependencies or infrastructure. All test accounts and preview data are synthetic and excluded from Git.

## Removable demo dataset — 11 September 2026

- `npm run check`: 30 reported tests, all workspace typechecks and API/admin production builds.
- Demo tests run the actual fixture against disposable PostgreSQL, sign in through HTTP as all ten generated accounts, verify restricted and suspended access, exercise valid/expired invitation acceptance, refuse duplicate creation, and remove seeded/test-added data while preserving an unrelated organisation and account.
- Injected seed failure rolls back all partial inserts. Removal refuses unknown IDs, demo superadmins, external memberships and external invitations. Generated credentials/tokens are absent from database audit metadata. No real records, passwords or live environment files were used.
- Demo operations are explicit server CLI actions for the user. No new schema, dependency, native changes or assistant live writes. The existing web UI is unchanged.

## Learner management — 12 September 2026

- `npm run check` passed: all workspace typechecks, 38 reported tests and API/admin production builds. API test files run serially to bound embedded PostgreSQL memory use.
- HTTP tests cover migration 3, organisation/group isolation, guardian contact redaction, permissions, field validation and preservation, stale edits, transfer history and loss of scope, archival safeguards, import ownership/expiry/revalidation/atomicity/idempotency, duplicate handling, and removable synthetic learners. CSV parsing checks include quotes, newlines, headers and limits.
- Local browser checks with synthetic data passed: sign-in, demo learner listing/profile, Excel `.xlsx` upload and preview, confirmed import, code search, profile save and group transfer with retained history. Desktop layout inspected. No native code changed; no mobile hardware claim is made.
- The locked Excel parser is the only new dependency. No real learner data or credentials are committed. `bash -n` passed for the new deployment script.
- No live changes or migrations performed by the assistant. The user must deploy the checked release, apply migration 3 and verify against the server's PostgreSQL using `docs/learner-release.md`.

## Organisation configuration — 12 September 2026

- `npm run check` passed: workspace typechecks, 43 reported tests, API/admin production builds. The new server deployment script also passes `bash -n`.
- Migration tests seed learner fields/data before migration 4 and verify the existing definitions survive. HTTP coverage includes scoped settings, stale configuration, invalid logo formats, superadmin-only module switches, learner API denial while disabled, module-specific field keys and values, typed/required/archived fields, scoped read/write denial, value version conflicts and demo cleanup.
- Domain tests use stubbed DNS TXT responses to verify missing/wrong proof, unique hostname assignment, activation permission and hosting confirmation, exact Host/Origin checks, bound-host organisation isolation and revocation. Native HTTP requests exercise Host handling. These tests do not prove live DNS, HTTPS or certificate renewal.
- Browser smoke checks on a disposable local database: sign-in, organisation Setup save, template/welcome preview on its branded sign-in link, creation of a required centre field, selection of an existing centre and saving its additional details. Desktop layouts inspected. All records and credentials were synthetic.
- No new application dependency, mobile change, native build or assistant live write. User deployment, migration 4 and real-domain hosting checks remain required; see configuration-release.md.

## Organisation type dropdown — 12 September 2026

- Added School to creation and configuration validation. A shared web selector supplies readable labels, an explicit initial choice and consistent full-width styling in both forms.
- `npm run check` passed: 43 reported tests, workspace typechecks and API/admin builds. Existing configuration HTTP coverage now checks school creation, settings persistence and rejection of an unknown type.
- Local browser check confirmed the initial placeholder, School option and corrected desktop form alignment using a disposable synthetic account. No live edits or schema change.
- Read-only inspection of the public homepage found the previous configuration release assets (`index-DBJrCGMN.js` and `index-DIC2fqV6.css`) with Cloudflare DYNAMIC status. The earlier release was being served; its main features are inside Setup and Custom fields.
