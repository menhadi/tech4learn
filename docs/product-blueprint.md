# Agreed product blueprint

## Purpose

Make educational work easy, accurate and technology-enabled, saving teachers and field staff time in attendance, assessments, exam preparation, marking and reporting. Prioritise low-income learners, government-school programmes and NGOs, with CSR partners as potential funders. Also support coaching centres and other learning organisations.

Multi-tenant SaaS: superadmin manages the platform; organisation admins manage their own branded operations. The clarified target flow is organisation → centre → class/academic year → section → student, with configurable terminology for other programmes. See [registration workflow](registration-workflow.md) for all eleven requested steps and their implementation status.

Live dummy data is requested only in **Vecotrial Career Academy** (`vector-academy`), preserving other organisations. See [targeted demo setup](academy-demo.md). This does not restrict isolated local tenant-isolation test fixtures.

## Interfaces and configuration

- Mobile app for daily work and learner activities.
- Web administration for superadmins, organisation admins and authorised programme staff.
- One/two-page public site at tech4learn.com; hosting and DNS already exist.
- Configurable branding, terminology, structures, forms, typed custom fields, roles, workflows, reports and enabled modules.
- Custom fields apply across organisation, centre, group, staff, learner and future module records; they are not restricted to learners.
- Editable templates are starting points. Changes must preserve historical records.
- One shared app loading organisation branding is the initial recommendation. Separate app-store apps are not agreed.
- Organisation data stays separate. CSR/NGO partnerships can be many-to-many, with explicitly authorised, scoped sharing.
- Simple screens, speed, automatic saving, recovery and cost control are core requirements.

## Attendance

The target is a section of 20–50 students photographed in multiple group images, not individual daily student portraits. [Bulk attendance](bulk-attendance-release.md) implements one section/day record with up to five photos, queued comparisons, combined per-student suggestions and explicit teacher review. The engine has started on the pilot host; API-key integration and calibration remain pending.

Working browser pilot: live camera capture, automatic time/location, explicit learner review, correction history and daily totals. See [attendance release](attendance-release.md) for its limits. Native capture, recognition, register scanning, CCTV and offline capture remain planned.

Support classroom/group photos, physical-register photos, manual correction and future CCTV connectors. The user selected self-hosted face recognition as the direction; A bounded CompreFace verification adapter is implemented; the engine has started on the pilot host and real-image calibration is pending. See [student photos](student-photos-release.md). Four general vision adapters provide optional quality/transcription analysis, not identity matching. See [workspace and vision release](workspace-ai-release.md).

1. Register and approve centre coordinates separately from map village/address labels.
2. Assign authorised staff, groups and learners.
3. Capture a fresh in-app photo and contemporaneous device location.
4. Record coordinates, reported accuracy, location-reading time, capture time and server receipt time. Staff cannot edit captured evidence.
5. Compare distance with the centre's permitted area; do not require coordinate equality.
6. Acceptable readings within range are location-verified. Outside-range submissions are accepted with a warning for review. Missing/inaccurate readings request retry or review.
7. Keep location verification separate from attendance recognition. GPS does not prove every learner's presence.
8. Preserve evidence and a history of review/correction.

Organisations configure distance and review policies. Pilot testing establishes thresholds, accuracy and freshness limits. Prevent editing in the app without claiming device GPS is tamper-proof. Distinguish fresh capture, gallery uploads, registers and CCTV sources. Capture location for the workflow, not continuous tracking.

## Interactive learning assessment

Primarily digital: speaking, reading aloud, listening, visual interaction, numerical solving and explaining reasoning. Use evidence and adaptive follow-ups to investigate understanding. Present possible gaps for review rather than diagnosing from a single wrong answer.

Use NIPUN, ASER and appropriate international resources through APIs where available and documents otherwise. Do not assume frameworks offer assessment APIs. Imports create reviewable drafts containing source, version, language, competencies, instructions and rubrics. Verify reuse rights and label adaptations. Preserve assessment versions used for past results.

Paper/pencil and teacher observations remain available for remote contexts and feed the same learner profile with administration mode recorded. Primary direction is online, technology-led use with safe retry/resume; full offline AI operation is not assumed.

Connect findings to activities, intervention, reassessment and progress. Start with available resources; improve with field evidence and teacher input. Pilot grades, language, size and cadence are not fixed.

## ExamElite

The user confirms ExamElite provides complete exam capabilities and millions of questions. Integrate question selection, exam creation/delivery, online/paper/OMR workflows, subjective exams, evaluation and results. Do not rebuild its engine or question bank.

Verify actual APIs, permissions, stable learner identity mapping, question/version references and result synchronisation. An existing web interface does not establish external API availability. Present integrated workflows within Tech4Learn with appropriate branding.

## Future options

CCTV, parent messaging, community volunteers, real-life concept videos, resource sharing, broader assessments, automation rules, sponsor reporting and additional providers. These are expansion options, not all first-release commitments.

## Success measures

Teacher time saved, review/correction rates, agreement with qualified assessment review, successful tasks on inexpensive devices, useful follow-up actions and cost per active learner. Establish targets with the pilot partner; no measured outcomes are claimed yet.

## Current usability and hosting direction

Student profiles separate details, photo setup and enrolment/history. A single named portrait can be captured with the camera or uploaded, then saved for profile, attendance, or both with explicit permission for the selected uses. Attendance suggestions use a review table. The user requests using the existing host for the initial engine pilot, subject to measured spare capacity; buying another server is not a prerequisite. Photos already reside on the existing Tech4Learn database.

## Superadmin engine management

[Face-engine controls](face-control-release.md) add a restricted local controller, status and suggested CPU/RAM limits, stop/start/restart, and a durable queue pause in migration 10. The engine is installed per the user’s server screenshot; control deployment, API-key connection and real-image calibration remain separate checks. Larger host capacity can be detected by refreshing the superadmin page.

Photo setup has a direct student-directory action and one flow: take/upload → name and choose uses → confirm permission → save and check. Both uses save atomically; attendance references are checked after saving when configured. Camera preview, retake and upload are alternatives. Classroom attendance remains a separate group-photo capture and teacher-review task.

## Shared admin UI

The working shared form/table pattern is documented in [UI template](ui-template.md). Learner enrolment now keeps details, placement, custom fields and photo setup together, with recoverable device drafts. Directories share automatic filtering, sorting, counts and 50/100/500 row choices.

The admin navigation follows ExamElite's grouped vertical sidebar: academics/students, attendance, exams/results, FLN, staff/permissions, email/messaging, settings and audit history. This is a navigation foundation; planned integration entries are explicitly labelled and do not imply working exam, FLN or message delivery functionality.
