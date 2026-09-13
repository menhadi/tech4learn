# Internal student attempts: implementation status

The full student portal is not ready for deployment. Separate student sign-in and a basic same-domain attempt screen are implemented locally, with authenticated start/resume, answer saving and submission through ExamElite. Advanced delivery and media/formula rendering remain incomplete.

## Student sign-in and exam grants

Migration 15 adds exam-specific learner grants and separate student sessions. Authorised exam staff can issue, list and revoke grants through the organisation API. Issuance verifies the active Tech4Learn learner and the organisation-owned native exam. A grant lasts 1–168 hours; reissuing for the same student and paper revokes the previous grant and sessions. Email does not merge accounts, and granting exam access creates no staff user or organisation membership.

The response contains a one-use URL fragment, never a token in the query string. The student entry screen captures and removes it from the address before making API requests, then exchanges it only after the student clicks Sign in. Tokens remain in memory, are not placed in drafts/browser storage, and are stored only as hashes on the server. Successful exchange sets a separate HttpOnly SameSite cookie with a maximum 12-hour session; the server also enforces the earlier grant expiry. The normal staff application does not mount in this student entry.

Every student access check revalidates the grant, expiry, active learner, organisation Exams module and Taking restriction. The session resolves one student and one exam server-side. It cannot authenticate to staff APIs or another organisation's student endpoint. Concurrent first exchanges have one winner; a same-browser retry can reuse its already-issued cookie. A consumed link on another browser requires staff to issue a new link.

The sign-in screen displays the assigned exam and a Start or resume action. The basic attempt screen has question navigation, explicit Save answer, review flags, clearing, answer locks, countdown and submission confirmation. Unsaved edits prevent navigation. A failed save freezes editing until the same request is retried or the student explicitly reloads saved work. Exam answers stay in memory until the server acknowledges saving; browser storage is not used. Staff link-issuing controls are still withheld while the full delivery workflow is unfinished.

## Implemented components

`Tech4LearnAttemptAnswers` maps the Tech4Learn learner UUID through the existing organisation-scoped native student mapping. It validates the source platform, organisation, active student, attempt, question and Taking restriction. Staff mappings do not count as learner mappings.

The adapter locks the attempt and answer rows, rejects new writes after submission or the attempt's captured duration, and respects the exam close time. Increasing the exam duration later cannot extend an already-started attempt through this answer path. The native ExamAnswerPersistenceService continues to store the answer and enforce its answer-lock rule; the integration does not grade questions.

Question type is resolved from the native question, never from browser input. The bounded input contains the selected answer and review/bookmark/lock/time flags only. Numerical, true/false, single/multiple-choice and fill-blank values have isolated native persistence tests. Subjective text is accepted by the private adapter but a complete manual-marking workflow is still outstanding.

An answer revision rejects stale edits. `tech4learn_attempt_requests` records a request fingerprint and minimal acknowledgement, without storing answer keys. An identical lost-response retry is acknowledged without applying the answer again, even if a newer answer exists. A changed request with the same ID is rejected. Revoked Taking access also prevents retry acknowledgements. A previously accepted request can be acknowledged after submission; it cannot change that submission.

`Tech4LearnAttemptPayload` explicitly projects the native start-view data into question content, options, selected translation, the student's prefilled answer, navigation state and timing/display settings. It does not serialise Eloquent models. Correct answers, explanations, numerical answer rules, private configuration and model metadata are excluded. It checks the attempt/student/question ownership before projecting. The native math normaliser remains in use.

The installer copies these classes and the explicit migration creates the request table. The credential-authenticated native student controller now connects these components to the Tech4Learn student API. No live files or records were changed during development.

## Private native lifecycle

The private lifecycle service now invokes ExamElite's actual student controller for start/resume and finalisation, and its existing answer persistence service for saves. It creates only an explicitly mapped native student, without enrolling that student in all exam groups. The server caller must supply the identity and paper from the stored Tech4Learn grant; the browser cannot supply learner or exam identity to the attempt endpoint. Tech4Learn checks the grant before and after each native call, including revocation during an in-flight request.

Workspace and attempt locks serialise lifecycle mutations. Submission retries return the existing completed attempt without grading it again. Start retries rebuild the current view/countdown. First-load answer revisions use persisted rows including database defaults. Completed scores obey both the native publication setting and the current Results restriction. An existing timed-out or closed-paper attempt enters native finalisation rather than failing the start-availability check. A duration edit during an attempt currently prevents resume with an explicit error; automatic reconciliation of changed timing is not implemented. Advanced timer/proctor modes remain unavailable through this adapter.

`test-student-attempts.php` exercises the current native controller, grouping service, language service and answer evaluator using isolated synthetic records. It verifies numerical marking, first saves, resumed countdowns, duplicate submissions, attempt limits, cross-student/paper denial, result restrictions, timeout completion and restoration of request/session/identity after errors. Activity tracking and UI-language infrastructure are test doubles. Deployment scripts run this suite, which includes the preceding authoring and answer tests, after installing the private components. These checks do not establish the complete browser workflow or production concurrency.

## Remaining integration

- Finish the advanced exam controls before enabling staff link issuance. The current native transaction rejects SVG, interactive media, proctoring, group timers, shuffled options and calculator modes, rolling back a newly created attempt rather than showing an incomplete paper.
- Replace the older launch path's automatic membership in every native exam group with explicit exam access. Existing external launch/session behaviour is not changed by these private components.
- Verify the supported raster/formula formats against representative native papers before deployment; SVG and interactive media remain unsupported.
- Integrate section timers, browser/proctor requirements, result release and manual marking before claiming those modes work in Tech4Learn.

## Local verification

`test-attempt-answers.php` extends the existing synthetic native-authoring fixture and calls the installed native answer persistence service. Tests cover tenant/student mismatches, forged type, malformed input, stale edits, changed retry IDs, newer-answer preservation, native locks, captured deadline, submitted attempts, revoked restrictions, supported native answer shapes and payload minimisation. A read-only snapshot of the current native source is also used locally. The SQLite fixture and request-context doubles do not prove production database concurrency or the complete student UI.

`exam-student-access.test.mjs` exercises real HTTP routes against isolated PostgreSQL-compatible storage: token hashing, one-use and concurrent exchanges, session/grant expiry, hostile origins, cross-organisation and staff access denial, reissue/revoke, archived students and module/restriction changes. A synthetic browser check uses the real application entry to verify token removal, explicit sign-in, student-only display and sign-out. No live learner or exam records are used.

The native endpoint uses the existing central credential and a separate prefixed 6,000/minute limiter, excluding the inherited shared-IP 60/minute API bucket for this route only. Tech4Learn also limits each authenticated grant to 120/minute. Limits need production load verification. Raw JSON preserves blank answers across Laravel request normalisation. Native errors return only allowlisted status codes; backend exception details are not forwarded.

HTTP tests verify trusted grant-derived learner/paper values, hostile-origin and identity-override denial, private error suppression, mismatched-paper responses and access revocation during the engine call. The synthetic browser test verifies HTML sanitisation, saving after a lost response with unchanged request identity, unsaved navigation blocking and explicit submission. This is local integration verification, not a deployed student attempt.

## Question images and formulas

Implemented locally: native question/option/passage images become opaque references. The student image route checks the current grant before and after the engine request; ExamElite checks the assigned question, learner, paper and unsubmitted attempt. Only sources referenced by the current question translation or passage are eligible; explanation images are excluded. Images are served from the organisation domain with no-store and nosniff headers. The engine accepts bounded raster data images and files under its question-image/upload roots, plus its existing bounded non-redirecting ExamElite CDN cache. It checks actual image MIME, a 10 MiB file limit and a 40-million-pixel limit. SVG, arbitrary remote hosts, traversal and unrelated storage roots are rejected.

The browser sanitises text/MathML and renders TeX using the locally bundled MathJax 4 input parser, including fractions, matrices and chemistry. This follows the [MathJax direct input interface](https://docs.mathjax.org/en/stable/server/direct.html). Each expression receives a fresh bounded parser without external loaders or URL commands; no CDN scripts or font requests are required. Answer controls wait for formulas and images to finish loading. A failed image or formula shows a retry action without discarding unsaved answers silently.

Native synthetic tests cover image projection, authorised bytes, cross-student denial and excluded explanation/protocol/path sources. HTTP tests cover scoped image bytes, private response headers, mismatched references and revocation in flight. A local browser fixture verifies actual MathML fractions/chemistry, loaded raster content and unsafe markup rejection. Production paper compatibility remains unverified.
