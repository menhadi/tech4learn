# Pilot workflow verification

## Native PDF worker state check

Run `php deploy/examelite/test-pdf-worker.php VENDOR_AUTOLOAD MODELS_DIRECTORY
QUESTION_CONTROLLER EXAM_CONTROLLER GENERATE_EXAM_PDF_JOB` from the repository
root (as one command). Paths refer to local native source snapshots. The isolated
fixture invokes the actual worker against SQLite and a temporary synthetic
cached artifact, checking activation, repeat execution and failed replacement
after translation approval is removed. It does not bootstrap the application,
run its queue transport, render a PDF or use live environment settings. The
fingerprint service is a fixture; real renderer/fingerprint verification remains
outstanding. The random temporary directory is removed after the check.

## Translated question images

With the browser Vite configuration below running, open
`http://127.0.0.1:5195/tests/browser/translation-image.html` and wait for PASS/FAIL.
The actual translation editor/shared image form runs against synthetic fetch
responses. It checks organisation/central routes, current review identity,
upload/replacement/removal, an identical lost-response retry, editor locking,
file-byte exclusion from drafts and hidden controls for missing translations.
The separate `test-translated-image-routes.php` checks real native persistence.
This browser fixture does not contact a live server or verify media downloads.

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
