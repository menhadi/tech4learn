# Internal student attempts: implementation status

The full student portal is not ready for deployment. Private native answer/payload adapters and a Tech4Learn student sign-in/access boundary are implemented and tested locally. Start, resume and submission are not yet wired into the student screen.

## Student sign-in and exam grants

Migration 15 adds exam-specific learner grants and separate student sessions. Authorised exam staff can issue, list and revoke grants through the organisation API. Issuance verifies the active Tech4Learn learner and the organisation-owned native exam. A grant lasts 1–168 hours; reissuing for the same student and paper revokes the previous grant and sessions. Email does not merge accounts, and granting exam access creates no staff user or organisation membership.

The response contains a one-use URL fragment, never a token in the query string. The student entry screen captures and removes it from the address before making API requests, then exchanges it only after the student clicks Sign in. Tokens remain in memory, are not placed in drafts/browser storage, and are stored only as hashes on the server. Successful exchange sets a separate HttpOnly SameSite cookie with a maximum 12-hour session; the server also enforces the earlier grant expiry. The normal staff application does not mount in this student entry.

Every student access check revalidates the grant, expiry, active learner, organisation Exams module and Taking restriction. The session resolves one student and one exam server-side. It cannot authenticate to staff APIs or another organisation's student endpoint. Concurrent first exchanges have one winner; a same-browser retry can reuse its already-issued cookie. A consumed link on another browser requires staff to issue a new link.

The sign-in screen currently displays the assigned exam and an explicit unavailable-delivery notice. There is no staff button issuing these links yet, to avoid offering incomplete exam delivery in the organisation workflow. Backend issue/list/revoke and student exchange/me/logout routes are implemented. Native student provisioning and the private attempt adapters still need to be connected to this authenticated context.

## Implemented components

`Tech4LearnAttemptAnswers` maps the Tech4Learn learner UUID through the existing organisation-scoped native student mapping. It validates the source platform, organisation, active student, attempt, question and Taking restriction. Staff mappings do not count as learner mappings.

The adapter locks the attempt and answer rows, rejects new writes after submission or the attempt's captured duration, and respects the exam close time. Increasing the exam duration later cannot extend an already-started attempt through this answer path. The native ExamAnswerPersistenceService continues to store the answer and enforce its answer-lock rule; the integration does not grade questions.

Question type is resolved from the native question, never from browser input. The bounded input contains the selected answer and review/bookmark/lock/time flags only. Numerical, true/false, single/multiple-choice and fill-blank values have isolated native persistence tests. Subjective text is accepted by the private adapter but a complete manual-marking workflow is still outstanding.

An answer revision rejects stale edits. `tech4learn_attempt_requests` records a request fingerprint and minimal acknowledgement, without storing answer keys. An identical lost-response retry is acknowledged without applying the answer again, even if a newer answer exists. A changed request with the same ID is rejected. Revoked Taking access also prevents retry acknowledgements. A previously accepted request can be acknowledged after submission; it cannot change that submission.

`Tech4LearnAttemptPayload` explicitly projects the native start-view data into question content, options, selected translation, the student's prefilled answer, navigation state and timing/display settings. It does not serialise Eloquent models. Correct answers, explanations, numerical answer rules, private configuration and model metadata are excluded. It checks the attempt/student/question ownership before projecting. The native math normaliser remains in use.

The installer copies these classes and the explicit migration creates the request table. Neither component is exposed by a new route yet. No live files or records were changed during development.

## Private native lifecycle

The private lifecycle service now invokes ExamElite's actual student controller for start/resume and finalisation, and its existing answer persistence service for saves. It creates only an explicitly mapped native student, without enrolling that student in all exam groups. The server caller must supply the identity and paper from the stored Tech4Learn grant; there is still no public route to this service.

Workspace and attempt locks serialise lifecycle mutations. Submission retries return the existing completed attempt without grading it again. Start retries rebuild the current view/countdown. First-load answer revisions use persisted rows including database defaults. Completed scores obey both the native publication setting and the current Results restriction. An existing timed-out or closed-paper attempt enters native finalisation rather than failing the start-availability check. A duration edit during an attempt currently prevents resume with an explicit error; automatic reconciliation of changed timing is not implemented. Advanced timer/proctor modes remain unavailable through this adapter.

`test-student-attempts.php` exercises the current native controller, grouping service, language service and answer evaluator using isolated synthetic records. It verifies numerical marking, first saves, resumed countdowns, duplicate submissions, attempt limits, cross-student/paper denial, result restrictions, timeout completion and restoration of request/session/identity after errors. Activity tracking and UI-language infrastructure are test doubles. Deployment scripts run this suite, which includes the preceding authoring and answer tests, after installing the private components. These checks do not establish the complete browser workflow or production concurrency.

## Remaining integration

- Connect the authenticated Tech4Learn student/grant context to each native call. Never accept a browser-selected learner ID or exam ID as authority.
- Replace the older launch path's automatic membership in every native exam group with explicit exam access. Existing external launch/session behaviour is not changed by these private components.
- Invoke native start/resume and submit under a private student context. Start and submit must coordinate with the same attempt locking, enforce attempt limits, preserve the selected language and avoid duplicate attempts/submissions.
- Build the same-domain student screen, navigation, server save status, retry/resume, countdown and final submission flow.
- Route authorised question media through the same domain and sanitise HTML at rendering. The payload currently carries native content; it is not itself an HTML sanitizer or media authorisation mechanism.
- Integrate section timers, browser/proctor requirements, result release and manual marking before claiming those modes work in Tech4Learn.

## Local verification

`test-attempt-answers.php` extends the existing synthetic native-authoring fixture and calls the installed native answer persistence service. Tests cover tenant/student mismatches, forged type, malformed input, stale edits, changed retry IDs, newer-answer preservation, native locks, captured deadline, submitted attempts, revoked restrictions, supported native answer shapes and payload minimisation. A read-only snapshot of the current native source is also used locally. The SQLite fixture and request-context doubles do not prove production database concurrency or the complete student UI.

`exam-student-access.test.mjs` exercises real HTTP routes against isolated PostgreSQL-compatible storage: token hashing, one-use and concurrent exchanges, session/grant expiry, hostile origins, cross-organisation and staff access denial, reissue/revoke, archived students and module/restriction changes. A synthetic browser check uses the real application entry to verify token removal, explicit sign-in, student-only display and sign-out. No live learner or exam records are used.
