# Pilot workflow verification

## Passage editor

Open `/tests/browser/exam-passages.html` through the local Vite fixture server.
The page uses synthetic responses and runs the shared organisation and central
classification editors. PASS verifies creation and editing, language switching,
changed-language-only writes, preservation of unsupported media in another
language, locked fields while a save is unconfirmed, and an identical retry
after a simulated committed save loses its acknowledgement. Creation also
remounts the form and restores its scoped pending draft before retrying.
It does not contact ExamElite or verify
native persistence, PDF regeneration or a complete student workflow.

`/tests/browser/question-passage.html` checks the passage picker in the question
editor for both owners, a minimal passage-reference update and identical retry.
It also checks locked editing after an unconfirmed question save, recovery of
a pending new-question draft, and correction using a new request after a
definitive validation rejection. Numerical questions use the native `NAT` type.
It uses synthetic responses; native attachment/foreign-owner rejection is
covered separately by `deploy/examelite/test-passage-authoring.php`.

## Plan permission catalogue

Open `/tests/browser/exam-plan-edit.html` for the shared plan editor and
catalogue with synthetic transport. It checks inactive/default plans, stored
settings, assigned-organisation impact, locked uncertain writes, identical
retries through repeated scoped draft recovery, stale revision recovery and
organisation isolation. The API suite separately checks bounded central plan
reads and updates with stored superadmin access before and after native calls.

Open `/tests/browser/exam-plan-create.html` for the real plan-creation form
with synthetic transport. It checks default capabilities, zero versus blank
limits, translated API fields, exact retries across repeated draft restores,
successful reset, organisation isolation and definitive permission rejection.
The plan-field endpoint and creation endpoint separately have stored-superadmin
HTTP checks; `plan-api-native.mjs` covers actual native persistence. These are
separate checks, not a complete browser-to-native production journey.

Open `/tests/browser/exam-capabilities.html` with Vite running. The real catalogue
component uses synthetic responses to check explicit loading, organisation
scope, permission labels, failed-refresh clearing and remount isolation. The
separate HTTP workspace suite checks stored superadmin access before and after
the native read, malformed responses and mismatched revisions. Native plan
evaluation is checked by `test-workspace-provision.php`.

The private native assignment helper is checked with
`php deploy/examelite/test-native-plan-assignment.php VENDOR_AUTOLOAD
MODELS_DIRECTORY QUESTION_CONTROLLER SAAS_CONTROLLER` (one command). It runs
the actual native organisation update against synthetic SQLite records, with
an isolated audit callback. No assignment route or live write is exercised.

`php deploy/examelite/test-native-plan-editor.php VENDOR_AUTOLOAD MODELS_DIRECTORY
QUESTION_CONTROLLER SAAS_CONTROLLER` additionally checks native plan creation:
default capability availability, explicit limits/restrictions, unique slugs,
invalid input, audit rollback, and unchanged previous plans/organisation
assignments. It also checks the private native creation controller and actor
coordinator: exact receipts, reordered-field replay, changed payload rejection,
revoked membership/user/mapping, bounded input and transactional rollback.
The gateway/interface are not connected yet, so the fixture does not establish
a complete superadmin creation journey. The native route registration is checked
by `test-workspace-provision.php`.

`test-native-plan-update.php` takes the same arguments and includes the creation
and assignment checks. It adds native partial editing, current snapshots and
assignment counts, active/inactive catalogue pages, receipt replay, stale edits,
revoked actors, audit rollback and rejection of unknown stored settings. The
deployment runs this combined suite once. Existing-plan editing still needs the
Tech4Learn gateway and interface; this does not establish production concurrency.

## Classification deletion

With Vite running, open `/tests/browser/classification-delete.html` and wait
for PASS/FAIL. The real shared editor exercises organisation/central category
and central language deletion against synthetic responses, including explicit
confirmation, unsaved-edit blocking, uncertain-write locking, identical retries,
list refresh and recovery when a record disappears. Native language reference
guards and private routes are checked by `test-central-languages.php`; native
category routes by `test-category-delete-routes.php`. This does not establish
production concurrency or live acceptance.

## Native translation completion

Run `php deploy/examelite/test-translation-completion.php VENDOR_AUTOLOAD
MODELS_DIRECTORY QUESTION_CONTROLLER EXAM_CONTROLLER` as one command.
The native service receives synthetic, already-complete translations in SQLite.
It checks ready state, explicit native approval policy, repeat completion and
lock contention. The fixture injects feature access and forbids AI-provider
access; it does not test actual AI generation, commercial plan evaluation or
queue transport. No application environment is bootstrapped.

`test-translation-generation.php` takes the same four arguments and exercises
the native translator's generation pipeline with a synthetic provider. It
checks batching, transactional rollback on failed or incomplete responses,
owner configuration selection, model answers, selective source refresh,
language scope and repeat completion. Provider calls are replaced in process;
it does not measure real AI translation quality or run a queue daemon. The
user-run deployment executes this isolated fixture before migrations.

## Native multilingual passage delivery

The separate `test-passage-media.php` suite takes `VENDOR_AUTOLOAD
MODELS_DIRECTORY QUESTION_CONTROLLER EXAM_CONTROLLER` as arguments. It checks
that multilingual student passages and their protected images resolve the same
selected translation, with source/legacy fallback, native formula normalisation
and foreign/submitted denial. It uses the native models and an isolated database;
images are synthetic in-memory data. Deployment runs this before migrations.

`test-student-formulas.php` takes the same arguments and includes the passage
suite. It exercises the native normaliser with MathML and recoverable MathJax
SVG/CHTML wrappers across questions, options, hints and passages. Unsupported
vectors, missing/ambiguous MathML, excessive nesting and native `needs_review`
results must stop delivery. The student lifecycle suite separately checks that
such a failure rolls back new attempt/student provisioning. Deployment runs the
combined formula suite. These are payload checks, not browser visual parity.

## Native PDF worker state check

Run `php deploy/examelite/test-pdf-worker.php VENDOR_AUTOLOAD MODELS_DIRECTORY
QUESTION_CONTROLLER EXAM_CONTROLLER GENERATE_EXAM_PDF_JOB EXAM_PDF_CACHE_SERVICE` from the repository
root (as one command). Paths refer to local native source snapshots. The isolated
fixture invokes the actual worker against SQLite and a temporary synthetic
cached artifact, checking activation, repeat execution and failed replacement
after translation approval is removed. It also runs Laravel's database queue,
serialized native job and queued handler against that synthetic database:
reservation excludes a second consumer, completion acknowledges the job,
lock contention delays it, and approval failure remains unacknowledged until
an explicitly released retry completes. The same fixture additionally invokes
Laravel's actual Worker processing policy: automatic attempt-specific backoff,
native three-attempt exhaustion, terminal failure events and recovery through
an explicit new request. Native timeout/attempt settings and retry counts are
checked. This does not run a daemon, test timeout signals or failed-job storage,
render a PDF or use live environment settings. The
native fingerprint service is loaded from the supplied source path too; an
attached source edit must select a different cached version. Real rendering
remains outstanding. The random temporary directory is removed after the check.

Use a native job copy patched with `workspace_install.protect_pdf_worker_lookup`.
The fixture verifies that a missing build releases the already acquired lock;
the unpatched job fails this regression. Run
`python deploy/examelite/test-pdf-worker-install.py NATIVE_JOB` to check the
repeatable transformation. User-run installation backs up and lints the native
job with the other changes; deployment verifies the installed guard before
migrations. No native source repository or live files are changed by local tests.

The native renderer's image-load failure guard is installed by
`workspace_install.require_pdf_images`. Run
`python deploy/examelite/test-pdf-renderer-install.py NATIVE_RENDERER` to check
the guarded, repeatable transformation. After applying that transformation to
an isolated copy, run `node deploy/examelite/test-pdf-renderer.mjs PATCHED_RENDERER`.
This executes the renderer with a browser double, checking that failed/incomplete
images never reach PDF output and that failure closes the browser. It does not
launch a browser, render a PDF or prove visual correctness. User-run deployment
applies the guard with the normal backup/rollback and runs the contract check.

## Translated question images

With the browser Vite configuration below running, open
`http://127.0.0.1:5195/tests/browser/translation-image.html` and wait for PASS/FAIL.
The actual translation editor/shared image form runs against synthetic fetch
responses. It checks organisation/central routes, current review identity,
upload/replacement/removal, an identical lost-response retry, editor locking,
file-byte exclusion from drafts and hidden controls for missing translations.
It exercises model-answer image destinations and saves new model-answer text.
The separate `test-translated-image-routes.php` checks real native persistence.
This browser fixture does not contact a live server or verify media downloads.

For native model answers, run `php deploy/examelite/test-translated-model-answer.php
VENDOR_AUTOLOAD MODELS_DIRECTORY QUESTION_CONTROLLER EXAM_CONTROLLER
PATCHED_QUESTION_LANGUAGE_CONTROLLER` as one command. The last path must contain
the installer extension from `add_translated_model_answer`. This checks native
text/formula and image saving, legacy omission preservation, clearing, source
isolation and retries. The deployment script runs it against the installed
controller; never point local fixtures at a server environment file.

## Question text beside retained media

Run `npm exec vite -- --config tests/browser/vite.config.mjs` and open
`http://127.0.0.1:5195/tests/browser/question-text.html`. Wait for PASS/FAIL.
This synthetic fixture mounts the real shared question field and scoped form.
It appends literal text to source-image, opaque translated-image, formula-only
and mixed fields; verifies the original markup survives; and checks disabled
editing. It does not call the native engine or verify image downloading.
The retained-question-images PHP suite separately checks native persistence.
No learner data or credentials are used.

## Connected API and native engine check

`pilot-api-native.mjs` runs the real Nest HTTP handlers against PGlite and sends
provider requests to the real registered native PHP controllers in an isolated
SQLite fixture. It creates a question and paper through the API, issues and
exchanges a one-use student grant, answers/resumes/submits, marks, publishes,
reads history and revokes access. Answer/mark/submission retries are verified.
Unauthenticated staff cookies and client-supplied learner IDs are rejected
before the native call. Workspace/staff provisioning is preseeded by the fixture.

Build the API first (`npm run build --workspace @tech4learn/api`), then run from
the repository root, supplying the same local native snapshot paths used by the
PHP tests:

```text
node tests/browser/pilot-api-native.mjs VENDOR_AUTOLOAD MODELS_DIRECTORY QUESTION_CONTROLLER EXAM_CONTROLLER
```

Append `--browser` to launch the connected browser check on
`http://127.0.0.1:5195/tests/browser/pilot-api-native.html`. Close any existing test
server on port 5195 first. This mounts the actual student and staff marking React
screens and uses real HTTP requests throughout, including student-token exchange,
student selection, answer saving, submission, marking, publication and result
refresh. Wait for the PASS/FAIL heading. Restart the harness before rerunning
because the student grant is single-use. Stop with Ctrl+C when finished.

Both checks passed locally. These use synthetic records only and never bootstrap
the deployed application or read its environment. The dev-only loopback fixture
sets a synthetic staff cookie; it is not a login-screen test. Exam creation and
publication are API actions, not authoring-button interactions in this browser
check. Native HTTP/TLS transport, Laravel middleware, production databases,
concurrent sessions, representative devices and PDF/AI workers are outside this
check. No fixture endpoint is part of the production app.

## Retained translated exam images

`exam-translation-images.html` exercises the real shared translation editor for
organisation and central exam instructions containing opaque images. It edits
surrounding text, verifies the image identity stays in the submitted wording,
and repeats an uncertain save with an identical request. Transport is synthetic;
this fixture does not verify actual image bytes or uploads. Run the corresponding
native check with:

```text
php deploy/examelite/test-retained-exam-translation-images.php VENDOR_AUTOLOAD MODELS_DIRECTORY QUESTION_CONTROLLER EXAM_CONTROLLER
```

That suite also covers formula edits, same-field image resolution, source and
other-field preservation, approval invalidation, stale revisions and missing
translations. Both checks passed locally. `exam-image-upload.html` separately
checks exam upload controls, scoped endpoints and retry locks; the native
`test-exam-translation-image-upload.php` suite covers upload/read/replace/remove
and failed-save file cleanup with the same arguments. Orphan reconciliation and
production concurrency remain unverified.

## Connected plan creation, editing and assignment check

`plan-api-native.mjs` connects the real Nest plan endpoints to the registered
native PHP plan routes and `SaasController` using isolated PGlite/SQLite records.
After building the API, run from the repository root:

```text
node tests/browser/plan-api-native.mjs VENDOR_AUTOLOAD MODELS_DIRECTORY QUESTION_CONTROLLER SAAS_CONTROLLER
```

It verifies reading choices, assigning a different plan, replaying the exact
request without another native audit invocation, reloading the selected plan,
creating and discovering a new plan without duplicate creation on retry,
reading and editing that plan with preserved untouched fields/default flag,
one audit invocation on an identical edit retry, listing the inactive result,
stale conflicts, injected actor denial and revoked Tech4Learn superadmin access.
Native fixture assertions also verify unchanged organisation details and shared
plans. It passed locally. The native audit helper is a recording test double;
database audit persistence, native HTTP/TLS middleware, concurrency and browser
interaction are outside this connected check. The separate
`exam-plan-assignment.html` fixture checks the real React selector with synthetic
transport, including pagination and repeated scoped draft restoration.

## Recorded native payload browser check

This fixture uses the real student and marking React components with payloads
produced by the continuous native pilot test. It replaces HTTP transport with
recorded synthetic responses. It is **not** a live end-to-end test and does not
test login, grant issuance, the API gateway or production concurrency.

1. Run `deploy/examelite/test-pilot-exam-workflow.php` with its vendor, model,
   question-controller and exam-controller arguments. Supply a fifth argument
   pointing to `.local/pilot-native-transcript.json` in this repository.
   The producer creates isolated synthetic records, never learner records from
   the configured production application.
2. Run `npm exec vite -- --config tests/browser/vite.config.mjs` from the repository.
3. Open `http://127.0.0.1:5195/tests/browser/pilot-native-contract.html`.
4. Wait for the PASS/FAIL heading. The check verifies preflight, written-answer
   saving and retry, submission, pending-answer rendering, marking and retry,
   hidden-result preservation, and published-result refresh.

Publication itself is executed and verified by the native producer. This browser
fixture switches to that recorded response after marking; it does not exercise
the staff publication button. It uses synthetic scoped browser drafts and keeps
generated transcripts in the ignored `.local` directory. Do not use real learner
data for this fixture.
