# Organisation-to-student workflow

## Latest clarification — 8 October 2026

Organisations should see only features enabled for them, with the same checks on
direct routes and API requests. Disabled features should not remain as locked
navigation links in the organisation workspace.

Maintain one student identity and directory for exams and attendance, whether
registration starts in either module. Current native students and attendance
learners have explicit identity links; automatic shared registration is not yet
implemented. Do not create duplicates by matching names or emails, or silently
merge existing records.

Improve the shared registration form with required identity and enrolment fields,
organisation custom fields, portrait camera capture and upload. A separate photo
of a registration/text page should produce an editable draft: review extracted
fields, complete missing values and manually correct them before saving. Portraits
and scanned documents have distinct purposes. Image extraction remains planned;
existing portrait capture/upload and manual field entry must remain usable.

The independent `/enrolment` workspace is live and uses `learners.view` without
requiring attendance access. Its gateway rejects attendance resources. Native
exam students and attendance learners still require explicit links; this entry
point does not yet synchronise their profiles or enrolments.

The shared-identity delivery foundation now records an ID-only pending revision
in the same PostgreSQL transaction as a mapped organisation's student creation,
profile change, transfer or archive. Repeated edits coalesce into one delivery
record; rollback removes the intent together with the student change. Unmapped
organisations and existing records are not automatically adopted or backfilled.
Migration 21 and its rollback/isolation checks are local only. The native delivery
consumer, explicit section-to-exam-group mapping and live acceptance are pending;
this queue does not create accounts, enable student login or synchronise records
by itself.

A local native profile consumer can now transactionally create/update an explicitly
UUID-linked exam profile from a trusted canonical snapshot. It checks the host
tenant and stored administrator membership, rejects stale/changed retries and
unlinked code collisions, preserves existing passwords/contacts, and creates new
profiles suspended with no invented email/phone or lifecycle messages. Archive
delivery suspends a profile; a later edit does not reactivate its login. Its
mapping migration and synthetic checks are local only. No public route invokes
this consumer yet: server-verified acknowledgement
and delivery retry are required before exposing or deploying this integration.

Canonical snapshot fetching is now implemented and checked locally: the API
derives the organisation from the active native staff mapping, checks learner
scope and returns only the mirror's identity fields and queued revision. The
native bridge rechecks enrolment authority and the exact snapshot after remote
work, discards extra fields and refuses stale delivery. This read-only boundary
does not acknowledge delivery, assign exam groups or enable the consumer's
public invocation; those integration steps and live acceptance remain pending.

The next local acknowledgement boundary accepts an ID-only RSA-signed native
receipt, with installation public keys registered only through a trusted operator
command using stored platform authority. It checks the current mapped staff,
learner scope and queued revision, rejects revoked or conflicting identity links,
and records delivery once on exact retries. A local native signer derives the
student ID and revision from its stored UUID profile, rechecks current host/admin
membership and signs only IDs using a private installation key outside public
storage. Browser-supplied native student IDs
cannot establish ownership. Synthetic checks cover tampering, expiry, stale
delivery, tenant mismatch and revoked keys/links. This is not deployed: native
The local `foundation:student-signing-key --confirm-native-installation` command
now prepares private installation storage and a separate public key file for
trusted registration. Exact retries retain the key; malformed or conflicting
existing files require operator review and are never rotated automatically.
An internal native delivery method now requires editable canonical enrolment
authority before profile writes, checks signing setup, persists the UUID profile,
rechecks the snapshot and posts its signed receipt to canonical acknowledgement.
Synthetic transport tests prove that remote failure retries retain the same native
profile/password and send only signed IDs. The operator switch defaults off.
An authenticated, CSRF-protected POST now exposes explicit delivery for one
canonical UUID and rejects browser profile/identity/key fields. Its operator
switch remains off. Local directory controls now show scoped pending/delivered
status or an identity-review requirement, with an explicit delivery action only
for editors. Status refresh never queues or mirrors a student. These controls
appear only when the installation enables delivery. Section mapping and live
acceptance remain pending; no existing live learner is automatically mirrored.

Local section-to-native-group delivery now has tenant-bound foreign keys,
explicit versioned mappings and a ledger of the exact membership rows it owns.
The internal consumer preserves manual memberships, removes only its own rows
on transfer/archive and refuses to restore deleted owned or previously observed
manual memberships without review. Mapping edits require the current host's
stored owner/admin and reject stale versions, foreign groups and revoked maps.
The local bridge now fetches the section from the scoped canonical directory,
checks section-edit authority and rechecks the same source/context before mapping.
Student delivery invokes owned membership assignment before signing its receipt.
Local mapping readback/options and authenticated POST controls now expose only
the scoped section and tenant exam groups. The enrolment academic workspace has
a shared draft form with version checks and revoked-map protection. Saving a
mapping does not deliver students; editors explicitly refresh an already delivered
profile to apply its current group mapping. These migrations and controls were
deployed on 8 October 2026. Existing-owner login, the enrolment workspace and
context, and scoped section mapping options passed authenticated live HTTP checks.
Browser form acceptance and actual profile/group delivery remain pending. No
existing learner was automatically mirrored and no new live student account was
created during deployment. Registration-page extraction and authenticated exam
attempt/results acceptance remain unfinished.

Registration-page extraction now has a locally checked, bounded draft parser
and transcription prompt. The parser accepts only editable identity/contact
text and an explicitly written age; it discards model-provided tenant, section,
student IDs, credentials and consent. The four existing provider transports now
support this draft purpose with bounded input and validated output in local
synthetic checks. Scoped document upload and the editable draft form are still
pending. This adapter is not a live OCR feature
and never saves a student.

User clarification, 12 September 2026. This is the target workflow; the status column distinguishes implementation from planned work.

Status reviewed 6 October 2026 against the product blueprint and current exam
delivery evidence. The objective remains reducing teachers' and field staff's
work through the organisation → centre → class/academic year → section → student
structure. Exam development integrates ExamElite; it does not replace its engine.
Local checks and live deployment acceptance are separate milestones.

| Step | Target behaviour | Current status |
| --- | --- | --- |
| Organisation | Superadmin creates an organisation, or sends an onboarding link for its administrator to complete details. Standard profile fields plus typed custom fields. | Superadmin creation and administrator invitations work; a separate organisation onboarding form is pending. |
| Structure | Organisation contains centres. Each centre has a type such as school, college, coaching centre or community centre. Centres contain classes, academic years and sections. | Centre types, academic years, centre classes and linked sections work. Existing groups retain their IDs and can be linked explicitly. See [academic release](academic-release.md). |
| Student registration | Standard identity, admission, contact and enrolment details plus custom fields, one profile photo and separately managed face reference photos. | Learner forms, custom fields, profile/reference photo storage and consent management work. See [student photos](student-photos-release.md). Reference checks need the private engine. |
| Register import | Scan a physical admission register, extract student rows into an editable draft, complete missing fields and resolve duplicates before committing. | Reviewed CSV/JSON imports work. Image-based student registration is pending; attendance-register transcription does not create students. |
| Staff | Invite administrators and teachers, then configure additional roles, actions and organisation/centre/section scope. | Configurable roles, invitations, scope and module/action permission table work. Individual-field access policies are not implemented. |
| Exams | Start from a class/section, then open question-type and exam configuration supplied by ExamElite. Synchronise student identities and results. | Same-domain authoring, explicit student identity mapping, grant-based delivery, marking and published results are implemented and checked locally. Complete class/section-to-exam journeys, full parity and live acceptance remain unfinished; see [current exam status](examelite-delivery-status.md). Do not build another question bank or exam engine. |
| Attendance | Start from a class/section, capture a group photo, generate matches against enrolled references, review unknown/ambiguous faces and confirm attendance. | Capture, location evidence and manual confirmation work. A bounded face-verification adapter and review UI are implemented; engine deployment and real-image calibration remain pending. |
| FLN | Start assessments for selected students or a class/section; save evidence, competency results and follow-up activity. | Planned; source/API and scoring integration required. |
| Communication | Organisation email/message settings, templates, permitted recipients and delivery history. | Planned. Invitation links currently need manual sharing. |
| Student panel | Student signs in to view only their own authorised results, performance and activities. | Separate exam-scoped student sign-in, attempts and published result history work in local checks. Access expires with the exam grant. A general student dashboard, permanent performance access and learning activities remain planned; staff membership does not grant student access. |
| API settings | One settings area for provider credentials, model selection, enabled use cases and connection tests; all calls go through the server integration layer. | Four vision adapters and read-only readiness page implemented. Editable encrypted credential management is pending; server environment is the current configuration source. |

## Decisions

- Keep centre type independent from organisation type. An NGO may run schools and community centres.
- Use academic year and stable section IDs; promotion creates an enrolment change instead of rewriting past results.
- Standard fields cover common operations. Organisations add typed custom fields instead of requiring every possible field for everyone.
- Profile photo and face references have separate purposes, access and deletion controls. Never enrol a dummy avatar as a real face.
- A recognised face suggests presence; an unmatched face does not establish absence. Review remains necessary for unclear images and location warnings.
- Scanned admission registers, attendance registers and classroom photos are separate workflows with explicit source types.
- API settings are intended to become the sole operator-facing configuration location. Centralise secrets server-side and never return saved keys to the browser. Do not create arbitrary client-selected proxy endpoints.

## Test student

In an organisation, open Learners → Test with a dummy student, choose an active group, and create/open the sample. The API creates one `DEMO-STUDENT-001` per organisation and reopens it on repeat requests for the same group. It is labelled synthetic, has no real contacts or face images, and fills required custom fields with sample values. Normal tenant and group scope checks apply. Archive it after testing; permanent deletion of learners with evidence/history is not implemented.

The assistant does not seed production directly. The user runs the checked release and uses this action in the intended organisation.
