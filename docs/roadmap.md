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

## M4 — FLN

Select language/resources, review items/rubrics, build digital activities, evidence capture, follow-up, teacher review and progress. Include paper/observation mode. Calibrate before claiming validity.

## Inputs needed

- Git remote is https://github.com/menhadi/tech4learn; initial branch is main. Further release/branch conventions remain open.
- Staging/live hosting capabilities, OS, process manager, directories and URLs.
- Read-only review access; credentials never go in repository files.
- Pilot partner, learners' age groups, language and concurrency.
- ExamElite API documentation/access and organisation mapping.
