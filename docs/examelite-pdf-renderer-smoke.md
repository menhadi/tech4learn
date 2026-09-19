# Native renderer smoke check

This is an opt-in local check of ExamElite's existing PDF rendering command.
It does not load the deployed Laravel application, use its database, or fetch
remote assets. It requires the already installed native Playwright dependency
and its Chromium executable; the check installs nothing.

```text
node deploy/examelite/test-pdf-renderer-smoke.mjs RENDERER_FILE PLAYWRIGHT_INDEX_MJS NEW_OUTPUT_DIRECTORY
```

Use a fresh output directory. The script copies the supplied native renderer
there, changes only the Playwright import to the supplied absolute module path,
and runs the resulting command against a loopback HTTP fixture. It disables the
renderer edge bypass for this local run. A content security policy prohibits
remote assets. The synthetic page uses a native MathML formula and an explicit
ready marker; it does not exercise the application's MathJax loader.

Expected outcomes:

- A healthy page creates `healthy.pdf` containing a question page, a raster
  diagram and a second solutions page.
- A missing image fails without publishing `broken.pdf`.
- An explicit math-render error fails without publishing `math-error.pdf`.

Reopen the healthy PDF with a PDF reader or rasterizer and check both pages,
text, diagram and page size. On 19 September 2026 the current patched native
renderer passed all three cases. PyMuPDF confirmed two A4 pages, question and
solution text and an embedded raster; both pages were rendered and inspected.
Generated artifacts remain in ignored local storage and are not committed.

This is not a complete PDF release gate. The additional checks below cover
the worker/render boundary and actual native print HTML with MathJax.
Signed print requests, shared locks and production worker setup still need
connected acceptance.

## Queue-to-renderer journey

`test-pdf-worker-render.mjs` runs the real native job and renderer together:

```text
node deploy/examelite/test-pdf-worker-render.mjs RENDERER PLAYWRIGHT_ENTRY NEW_OUTPUT_DIRECTORY VENDOR MODELS QUESTION_CONTROLLER EXAM_CONTROLLER JOB CACHE LIFECYCLE
```

The final seven arguments are the same explicit local dependency/source paths
used by the native worker fixture. Use the current compatible renderer, job,
cache and lifecycle versions. The synthetic print handshake uses version 23.
The output directory must not exist. No dependency is installed or production
configuration loaded.

The PHP fixture first runs existing native worker/queue regressions, then uses
Laravel's actual database queue and Worker to invoke the native job. That job
starts its normal renderer subprocess, publishes a real PDF and records size
and fingerprint. Ready replay does not render again. A changed source with a
broken diagram fails without replacing the published PDF; lock release,
temporary-file cleanup and explicit successful recovery are verified. On
19 September 2026 all checks passed, and `output/current.pdf` was reopened as
two A4 pages and visually inspected.

This advances the worker/render boundary, but the print page, lifecycle URL
and directory are synthetic and the lock is tracked in memory. Signed requests,
real shared locks and daemon operation still need separate acceptance.

## Actual native print template and MathJax

The current native `ExamPrintController::print` generates HTML directly; this
path does not use a Blade template. An additional opt-in check invokes that
unchanged method against isolated SQLite records:

```text
php deploy/examelite/test-native-print-page.php VENDOR MODELS QUESTION_CONTROLLER EXAM_CONTROLLER CACHE LIFECYCLE IMAGE_RESOLVER NEW_HTML_PATH
node deploy/examelite/test-native-print-render.mjs RENDERER PLAYWRIGHT_ENTRY GENERATED_HTML NATIVE_PUBLIC NEW_OUTPUT_DIRECTORY
```

Place compatible native `ExamPrintController`, `ExamGroupingService`,
`MathContentNormalizer` and `ExamPdfImageEmbedder` source definitions beside
the question-controller snapshot. The first command reuses the native authoring
fixture. It checks a question paper, language/grouping integration, a TeX
polynomial, preservation of option `0`, absence of solutions and denial of the
paper to the other synthetic organisation. It bypasses constructor injection
only: the unused download helper is not needed by the print method. Native
tenant context uses the existing isolated fixture double.

The second command runs the native renderer against that generated HTML and
serves only local native MathJax/font assets. A restrictive content security
policy blocks remote assets. It requires actual MathJax loader and output
processor requests and rejects missing assets. Fresh output paths prevent
stale artifacts from passing. No production database or application environment
is loaded; the native public directory is read-only.

On 19 September 2026 these checks passed. The one-page PDF was reopened,
its question/options/formula text checked, and the page visually inspected.
This covers an English MCQ with a TeX formula, not every language, question
type, solution signature, image source or template variation.

## Native solution signatures

```text
php deploy/examelite/test-native-print-signatures.php VENDOR MODELS QUESTION_CONTROLLER EXAM_CONTROLLER CACHE LIFECYCLE IMAGE_RESOLVER NEW_QUESTION_HTML NEW_SOLUTION_HTML
```

This extends the print fixture with the actual lifecycle `printUrl` method,
Laravel URL signing and Laravel's request-signature registration. Only the key
and domain are synthetic. `QuestionAnswerEvaluator.php` is also required beside
the question-controller snapshot. A valid signature renders the native correct
option and explanation; unsigned, expired, changed-host, changed-language and
changed-signature requests are rejected. Even a valid signature for a foreign
organisation cannot expose the paper. The generated solution HTML can be passed
to `test-native-print-render.mjs` for the same actual MathJax rendering check.
On 19 September 2026 the signature cases passed, and the generated solution
paper rendered successfully with real MathJax. The one-page PDF was reopened
and visually inspected, including the correct option and explanation formula.

These checks exercise the native signing and controller boundary directly.
They do not establish deployed route middleware, reverse-proxy host handling,
TLS, production keys, shared worker locks or a running production daemon.
