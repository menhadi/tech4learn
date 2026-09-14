# Internal student attempts: implementation status

The full student portal is not ready for deployment. Separate student sign-in and a basic same-domain attempt screen are implemented locally, with authenticated start/resume, answer saving and submission through ExamElite. Raster images, bounded TeX/MathML, native calculator and shuffled options are integrated locally. Section timers are also integrated locally. Camera proctoring and production paper verification remain incomplete.

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

Workspace and attempt locks serialise lifecycle mutations. Submission retries return the existing completed attempt without grading it again. Start retries rebuild the current view/countdown. First-load answer revisions use persisted rows including database defaults. Completed scores obey both the native publication setting and the current Results restriction. An existing timed-out or closed-paper attempt enters native finalisation rather than failing the start-availability check. A duration edit during an attempt currently prevents resume with an explicit error; automatic reconciliation of changed timing is not implemented. Camera proctoring remains unavailable through this adapter.

`test-student-attempts.php` exercises the current native controller, grouping service, language service and answer evaluator using isolated synthetic records. It verifies numerical marking, first saves, resumed countdowns, duplicate submissions, attempt limits, cross-student/paper denial, result restrictions, timeout completion and restoration of request/session/identity after errors. Activity tracking and UI-language infrastructure are test doubles. Deployment scripts run this suite, which includes the preceding authoring and answer tests, after installing the private components. These checks do not establish the complete browser workflow or production concurrency.

## Remaining integration

- Finish the advanced exam controls before enabling staff link issuance. The current native transaction rejects SVG, interactive media and proctoring, rolling back a newly created attempt rather than showing an incomplete paper.
- Replace the older launch path's automatic membership in every native exam group with explicit exam access. Existing external launch/session behaviour is not changed by these private components.
- Verify the supported raster/formula formats against representative native papers before deployment; SVG and interactive media remain unsupported.
- Integrate camera proctoring, result release and manual marking before claiming those modes work in Tech4Learn.

## Local verification

`test-attempt-answers.php` extends the existing synthetic native-authoring fixture and calls the installed native answer persistence service. Tests cover tenant/student mismatches, forged type, malformed input, stale edits, changed retry IDs, newer-answer preservation, native locks, captured deadline, submitted attempts, revoked restrictions, supported native answer shapes and payload minimisation. A read-only snapshot of the current native source is also used locally. The SQLite fixture and request-context doubles do not prove production database concurrency or the complete student UI.

`exam-student-access.test.mjs` exercises real HTTP routes against isolated PostgreSQL-compatible storage: token hashing, one-use and concurrent exchanges, session/grant expiry, hostile origins, cross-organisation and staff access denial, reissue/revoke, archived students and module/restriction changes. A synthetic browser check uses the real application entry to verify token removal, explicit sign-in, student-only display and sign-out. No live learner or exam records are used.

The native endpoint uses the existing central credential and a separate prefixed 6,000/minute limiter, excluding the inherited shared-IP 60/minute API bucket for this route only. Tech4Learn also limits each authenticated grant to 120/minute. Limits need production load verification. Raw JSON preserves blank answers across Laravel request normalisation. Native errors return only allowlisted status codes; backend exception details are not forwarded.

HTTP tests verify trusted grant-derived learner/paper values, hostile-origin and identity-override denial, private error suppression, mismatched-paper responses and access revocation during the engine call. The synthetic browser test verifies HTML sanitisation, saving after a lost response with unchanged request identity, unsaved navigation blocking and explicit submission. This is local integration verification, not a deployed student attempt.

## Question images and formulas

Implemented locally: native question/option/passage images become opaque references. The student image route checks the current grant before and after the engine request; ExamElite checks the assigned question, learner, paper and unsubmitted attempt. Only sources referenced by the current question translation or passage are eligible; explanation images are excluded. Images are served from the organisation domain with no-store and nosniff headers. The engine accepts bounded raster data images and files under its question-image/upload roots, plus its existing bounded non-redirecting ExamElite CDN cache. It checks actual image MIME, a 10 MiB file limit and a 40-million-pixel limit. SVG, arbitrary remote hosts, traversal and unrelated storage roots are rejected.

The browser sanitises text/MathML and renders TeX using the locally bundled MathJax 4 input parser, including fractions, matrices and chemistry. This follows the [MathJax direct input interface](https://docs.mathjax.org/en/stable/server/direct.html). Each expression receives a fresh bounded parser without external loaders or URL commands; no CDN scripts or font requests are required. Answer controls wait for formulas and images to finish loading. A failed image or formula shows a retry action without discarding unsaved answers silently.

Native synthetic tests cover image projection, authorised bytes, cross-student denial and excluded explanation/protocol/path sources. HTTP tests cover scoped image bytes, private response headers, mismatched references and revocation in flight. A local browser fixture verifies actual MathML fractions/chemistry, loaded raster content and unsafe markup rejection. Production paper compatibility remains unverified.

## Native calculator and option ordering

Calculator-enabled papers now expose the native scientific calculator keypad inside the student screen. Its small expression parser is adapted from the audited ExamElite student template: radians, arithmetic precedence, sin/cos/tan, log/ln/square root and native rounding. There is no dynamic code evaluation; an input bound and finite-result checks prevent runaway expressions. Disabled papers do not show the calculator.

The native payload uses the same collection shuffle as the ExamElite template and returns original option IDs in display order. Empty choices are excluded. The browser sends original IDs, never display positions. A resume may reshuffle as the native page does; persisted selections retain their original identities. Synthetic native tests verify start/save/resume with these flags, and a browser fixture checks shuffled selection, calculator interaction, lost-response retry and submission. Camera proctoring remains blocked until its private capture/review workflow is integrated.

## Captured section and subject timing

The additive native `tech4learn_attempt_clocks` table stores the native timer mode, ordered question groups and each group's native allocation when a new attempt is created. The schedule is derived from ExamElite's persisted first-start stats and grouping service. It is never supplied by the browser. Reloading or changing configured allocations cannot restart the captured schedule. Changing timer mode mid-attempt is rejected; older timed attempts without a captured schedule require a new attempt. Existing untimed attempts can still resume.

New answer writes must belong to the current group according to server time, in addition to existing overall-duration and closing-time checks. Future and expired groups reject new answers. Previously accepted request retries still acknowledge the saved write after its section expires. The native controller finalises the attempt when all allocated section time has elapsed. No marking algorithm is copied into Tech4Learn.

The student screen shows the active section and its countdown, prevents navigation into other sections, and refreshes server state at boundaries. Unsaved edits are not submitted after expiry; the screen explicitly says that only saved answers were kept. Failed boundary requests stay retryable and keep answers locked. The last boundary reaches native finalisation. Native tests cover exact boundaries, reloads, allocation changes, future/expired writes, retry acknowledgements and finalisation; a short synthetic browser exam checks navigation, the unsaved-expiry notice and automatic transition/submission.

## Browser tolerance events

The student screen records tab-hidden events when native browser tolerance is enabled with a positive limit. Events wait behind an in-flight save and retain their request identity on retry. Pending event failures keep answers frozen and cannot be discarded through the answer-reload button. A successful non-final event preserves unsaved answer text. The configured limit and recorded count are shown on the exam screen.

The native adapter checks the assigned attempt and increments the persisted native count under its existing transaction/attempt lock. It calls ExamElite's own counter update and finalisation methods. Duplicate requests do not increment twice; aggregate browser counts are rejected. Reaching the configured limit submits through the native engine, and ended attempts cannot be reopened by event retries. Start/resume also finalises an attempt already at its limit; new answers are rejected there.

This is the native browser-visibility rule, not a claim of tamper-proof proctoring: a modified client can suppress events, and closing a page can interrupt delivery of a pending event. Camera capture remains unavailable. Synthetic native, HTTP and browser checks cover identity scope, forged counters, duplicate delivery, unsaved answer preservation and finalisation. No real student events were generated.

## Private camera evidence foundation (not enabled)

The native add-on now has an isolated evidence store and a same-domain student capture API. The student camera component is implemented locally, but proctored-paper start remains blocked pending authorised staff review and pre-start camera checks. The existing proctored-paper start gate remains in place until camera UI and authorised review are complete. ExamElite's legacy public-image upload is not used.

A capture is bound to the platform workspace, active mapped student and unsubmitted native attempt. The paper must enable proctoring and permit online delivery. New captures obey the attempt deadline, exam close time and captured section schedule. The store accepts only canonical base64 JPEGs up to 256 KiB and 1280×960, with a 25-second minimum interval and 1,200 captures per attempt. A request UUID and image hash make retries idempotent; receipts contain no image bytes or public path. Taking revocation also rejects receipt retries.

Images are stored transactionally in the native database's additive `tech4learn_proctor_evidence` table, not a public filesystem directory. Records have a server receipt timestamp and 30-day expiry. `purge-proctor-evidence.php` provides bounded CLI cleanup. Both user-run deployment scripts install an hourly systemd timer after schema verification and require an initial cleanup run to succeed. The task runs as the ExamElite OS user with a five-minute timeout, catches up after downtime and deletes at most 10,000 expired captures per run. Review-time expiry checks are still required before enabling camera UI. This foundation does not claim that expiry alone removes records without running maintenance, nor that a submitted image proves camera liveness or identity.

`test-proctor-evidence.php` runs the preceding native integration suite and tests a locally generated solid-colour JPEG: scope, disabled mode, size/type bounds, capture interval, one-record retries, revoked access, ended attempts and expiry deletion. Deployment validation now includes this suite. No live images or learner records were used.

The capture API derives the learner and exam from the active student grant, rejects client identity fields, rechecks access after the native call, and returns only a matching receipt. Its bounded image payload allowance does not enlarge other native student actions. Native and HTTP checks cover wrong-exam attempts, forged identities, mismatched receipts, private-byte stripping and interval errors.

Retention deployment installs root-owned service/timer/CLI files with backups. Operators can inspect failures with `systemctl status tech4learn-proctor-cleanup.service` and `journalctl -u tech4learn-proctor-cleanup.service`; the task emits a deletion count, not image contents. The timer has not been installed or executed on live by this assistant.

## Student camera component (launch still gated)

For camera-enabled attempt payloads the student screen requests video only after an explicit button press. It shows a small preview and sends a bounded JPEG about every 30 seconds. Permission denial, stopped tracks and failed uploads freeze new answer edits/saves while the server timer continues. Students can still submit saved answers. A lost upload response retains the same image and request UUID in memory for retry; camera frames never enter form drafts or browser storage. Stop, completion and unmount release camera tracks, including a permission request that resolves after leaving the screen.

The component has passed a local synthetic browser check using a generated canvas stream: permission denial, lost upload response, exact-image/UUID retry, answer freezing, stopping and submission. This is not a real-device camera or live exam validation. The native proctored-paper start gate is deliberately unchanged; authorised private staff review and camera readiness before the attempt starts remain prerequisites.

## Private staff review API and screen

A separate staff API lists a mapped learner's native attempts with unexpired evidence, then bounded capture metadata and individual private JPEGs. It requires organisation-wide `exams.manage`, the enabled Exams module and unrestricted Results access. Access is checked again after the native response. The credential adapter independently verifies the source workspace, organisation, learner mapping, attempt and image ownership. Student exam cookies do not grant staff access.

Image responses use no-store and nosniff headers. Each successful image view creates an audit entry containing the actor and scoped record IDs, never image bytes. Metadata projections strip image fields, and expired images are rejected before the hourly deletion task runs. History is paged at 50 attempts; capture metadata is bounded by the 1,200-capture attempt limit. Submitted attempts remain reviewable while evidence is retained. The staff review screen is now implemented locally. Pre-start camera readiness remains unfinished, so proctored starts are still blocked.

The organisation Results area includes a private camera review screen using the shared student directory and evidence tables. Student lookup follows existing learner-view permissions; evidence reads additionally require organisation-wide exam management. Switching student or attempt unmounts the previous image. Failed refreshes clear stale results, and an open image is removed at its expiry time. Learners without an exam identity show an empty history rather than a connection error. A synthetic browser check covers the table flow, private image rendering, automatic expiry and student-change cleanup. No live learner images were viewed.
