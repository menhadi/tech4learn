# Internal student attempts: implementation status

The full student portal is not ready for deployment. This increment implements and locally tests two private native adapter components. It creates no student login, exam launch link or public answer endpoint.

## Implemented components

`Tech4LearnAttemptAnswers` maps the Tech4Learn learner UUID through the existing organisation-scoped native student mapping. It validates the source platform, organisation, active student, attempt, question and Taking restriction. Staff mappings do not count as learner mappings.

The adapter locks the attempt and answer rows, rejects new writes after submission or the attempt's captured duration, and respects the exam close time. Increasing the exam duration later cannot extend an already-started attempt through this answer path. The native ExamAnswerPersistenceService continues to store the answer and enforce its answer-lock rule; the integration does not grade questions.

Question type is resolved from the native question, never from browser input. The bounded input contains the selected answer and review/bookmark/lock/time flags only. Numerical, true/false, single/multiple-choice and fill-blank values have isolated native persistence tests. Subjective text is accepted by the private adapter but a complete manual-marking workflow is still outstanding.

An answer revision rejects stale edits. `tech4learn_attempt_requests` records a request fingerprint and minimal acknowledgement, without storing answer keys. An identical lost-response retry is acknowledged without applying the answer again, even if a newer answer exists. A changed request with the same ID is rejected. Revoked Taking access also prevents retry acknowledgements. A previously accepted request can be acknowledged after submission; it cannot change that submission.

`Tech4LearnAttemptPayload` explicitly projects the native start-view data into question content, options, selected translation, the student's prefilled answer, navigation state and timing/display settings. It does not serialise Eloquent models. Correct answers, explanations, numerical answer rules, private configuration and model metadata are excluded. It checks the attempt/student/question ownership before projecting. The native math normaliser remains in use.

The installer copies these classes and the explicit migration creates the request table. Neither component is exposed by a new route yet. No live files or records were changed during development.

## Remaining integration

- Authenticate the Tech4Learn student and resolve an explicit organisation/exam grant server-side before calling either adapter. Never accept a browser-selected learner ID as authority.
- Replace the older launch path's automatic membership in every native exam group with explicit exam access. Existing external launch/session behaviour is not changed by these private components.
- Invoke native start/resume and submit under a private student context. Start and submit must coordinate with the same attempt locking, enforce attempt limits, preserve the selected language and avoid duplicate attempts/submissions.
- Build the same-domain student screen, navigation, server save status, retry/resume, countdown and final submission flow.
- Route authorised question media through the same domain and sanitise HTML at rendering. The payload currently carries native content; it is not itself an HTML sanitizer or media authorisation mechanism.
- Integrate section timers, browser/proctor requirements, result release and manual marking before claiming those modes work in Tech4Learn.

## Local verification

`test-attempt-answers.php` extends the existing synthetic native-authoring fixture and calls the installed native answer persistence service. Tests cover tenant/student mismatches, forged type, malformed input, stale edits, changed retry IDs, newer-answer preservation, native locks, captured deadline, submitted attempts, revoked restrictions, supported native answer shapes and payload minimisation. A read-only snapshot of the current native source is also used locally. The SQLite fixture and request-context doubles do not prove production database concurrency or the complete student UI.
