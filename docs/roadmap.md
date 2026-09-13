# Milestones

## M0 — This scaffold

Requirements and architecture; local Git and workspaces; API liveness, web connection check, mobile shell, shared contracts and automated checks. No domain operations or infrastructure changes.

## M1 — Organisation foundation

Authentication, memberships, permissions, branding, configurable centres/groups, learners and basic typed custom fields. Learner profiles, transfer history, typed fields and reviewed imports are implemented in migration 3; user deployment is required. Validate isolation with negative access tests.

Identity, organisation creation, branding, configurable roles, centre/group scopes, suspension and audit history are deployed by the user. The learner release adds profiles, typed fields, transfers and import preview locally. Remaining M1 work includes programme-level scopes, recovery/MFA, general configurable forms and pagination for larger centre/team directories.

## M2 — Attendance

Approved coordinates, staff assignment, capture, upload, configurable geofence decision, recognition/review and final records. Validate on inexpensive Android devices: denied permissions, weak GPS, duplicate submissions and interrupted uploads. Select recognition after representative sample evaluation.

## M3 — ExamElite

Read-only contract inspection; one end-to-end exam path with identity/result mapping, then complete agreed functionality. No duplicate exam engine.

Student-API and read-only live source inspection are recorded in [ExamElite integration discovery](examelite-integration.md). The pilot connector now reads the selected exam and a completed mapped result, confirmed in user screenshots. Full learner provisioning and result synchronisation remain unimplemented.

The [first read connector](examelite-read-release.md) is deployed for the pilot with dedicated credentials, explicit grants and working exam/result screens. Next: central superadmin connection and organisation sharing controls, then idempotent Tech4Learn-to-ExamElite student provisioning/verified linking and exam access. Follow ExamElite's subject/question/exam interface and workflow without rebuilding its engine. Existing ExamElite students remain independent of Tech4Learn. Student provisioning, exam launch and durable result synchronisation remain future slices.

## M4 — FLN

Select language/resources, review items/rubrics, build digital activities, evidence capture, follow-up, teacher review and progress. Include paper/observation mode. Calibrate before claiming validity.

## Inputs needed

- Git remote is https://github.com/menhadi/tech4learn; initial branch is main. Further release/branch conventions remain open.
- Staging/live hosting capabilities, OS, process manager, directories and URLs.
- Read-only review access; credentials never go in repository files.
- Pilot partner, learners' age groups, language and concurrency.
- ExamElite API documentation/access and organisation mapping.

## Organisation configuration release

Implemented locally: shared branded organisation links, editable presentation settings, domain ownership/activation workflow, module availability controls and reusable custom fields. See configuration-release.md. Live deployment and real-domain hosting verification are user-run. Future modules currently support field definitions only; their operational workflows are not implemented.
