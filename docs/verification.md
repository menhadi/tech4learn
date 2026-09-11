# Initial scaffold verification — 2026-09-11

Historical results below are retained for the initial scaffold. The current identity release results are at the end of this document.

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
