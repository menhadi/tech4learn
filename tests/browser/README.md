# Native pilot payload browser check

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
