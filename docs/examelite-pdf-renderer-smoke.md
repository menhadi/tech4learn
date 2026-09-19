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

This is not a complete PDF release gate. Native Blade pages, real MathJax
loading, signed print requests, queued worker-to-renderer execution, template
revision changes and production worker setup still need connected acceptance.

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
and directory are synthetic and the lock is tracked in memory. Native Blade
rendering, signed requests, real shared locks, MathJax loading and daemon
operation still need separate acceptance.
