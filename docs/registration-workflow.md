# Organisation-to-student workflow

User clarification, 12 September 2026. This is the target workflow; the status column distinguishes implementation from planned work.

| Step | Target behaviour | Current status |
| --- | --- | --- |
| Organisation | Superadmin creates an organisation, or sends an onboarding link for its administrator to complete details. Standard profile fields plus typed custom fields. | Superadmin creation and administrator invitations work; a separate organisation onboarding form is pending. |
| Structure | Organisation contains centres. Each centre has a type such as school, college, coaching centre or community centre. Centres contain classes, academic years and sections. | Centres and scoped learning groups work. Explicit centre type, class/year and section entities are pending. Preserve existing group IDs when introducing sections. |
| Student registration | Standard identity, admission, contact and enrolment details plus custom fields, one profile photo and separately managed face reference photos. | Learner forms, custom fields and enrolment history work. Photo enrolment and additional standard fields are pending. |
| Register import | Scan a physical admission register, extract student rows into an editable draft, complete missing fields and resolve duplicates before committing. | Reviewed CSV/JSON imports work. Image-based student registration is pending; attendance-register transcription does not create students. |
| Staff | Invite administrators and teachers, then configure additional roles, actions and organisation/centre/section scope. | Configurable roles, invitations, scope and module/action permission table work. Individual-field access policies are not implemented. |
| Exams | Start from a class/section, then open question-type and exam configuration supplied by ExamElite. Synchronise student identities and results. | Pending verified ExamElite APIs. Do not build another question bank or exam engine. |
| Attendance | Start from a class/section, capture a group photo, generate matches against enrolled references, review unknown/ambiguous faces and confirm attendance. | Group-based capture, location evidence and manual confirmation work. Vision analysis can assess images or transcribe register marks; face matching is pending. |
| FLN | Start assessments for selected students or a class/section; save evidence, competency results and follow-up activity. | Planned; source/API and scoring integration required. |
| Communication | Organisation email/message settings, templates, permitted recipients and delivery history. | Planned. Invitation links currently need manual sharing. |
| Student panel | Student signs in to view only their own authorised results, performance and activities. | Planned; current staff roles do not provide student login. |
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
