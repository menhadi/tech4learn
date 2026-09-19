import assert from "node:assert/strict";
import { readFile, writeFile, mkdir, stat } from "node:fs/promises";
import { resolve, join } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";
import { createServer } from "node:http";
import { spawn } from "node:child_process";
// Existing native dependencies only. Never bootstrap the real application.
const [renderer, playwright, output, ...native] = process.argv.slice(2);
if (
  !renderer ||
  !playwright ||
  !output ||
  native.length !== 7 ||
  process.env.NODE_ENV === "production"
)
  throw Error(
    "Use node test-pdf-worker-render.mjs RENDERER PLAYWRIGHT_ENTRY NEW_OUTPUT_DIRECTORY VENDOR MODELS QUESTION_CONTROLLER EXAM_CONTROLLER JOB CACHE LIFECYCLE",
  );
const runtime = resolve(output);
await assert.rejects(stat(runtime), { code: "ENOENT" });
await mkdir(join(runtime, "scripts"), { recursive: true });
const source = await readFile(renderer, "utf8"),
  marker = "import { chromium } from 'playwright';";
assert.equal(source.split(marker).length, 2);
await writeFile(
  join(runtime, "scripts", "render-exam-pdf.mjs"),
  source.replace(
    marker,
    `import { chromium } from ${JSON.stringify(pathToFileURL(resolve(playwright)).href)};`,
  ),
);
let healthy = 0,
  broken = 0;
const server = createServer((req, res) => {
  if (!["/healthy", "/broken"].includes(req.url)) {
    res.writeHead(404);
    res.end();
    return;
  }
  if (req.url === "/healthy") healthy++;
  else broken++;
  res.writeHead(200, {
    "Content-Type": "text/html; charset=utf-8",
    "Content-Security-Policy":
      "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
  });
  res.end(
    `<!doctype html><html data-mathjax-ready="1"><head><meta charset="utf-8"><meta name="exam-pdf-template-version" content="23"><style>@page{size:A4;margin:16mm}body{font:16px Arial}section{break-before:page}</style></head><body><h1>Queued native PDF fixture</h1><p>Explain why two pairs make four.</p><p><math><msup><mi>x</mi><mn>2</mn></msup><mo>+</mo><mn>1</mn></math></p>${req.url === "/broken" ? '<div class="question-text"><img src="/missing.png"></div>' : ""}<section><h1>Solutions</h1><p>Two groups of two make four.</p></section></body></html>`,
  );
});
await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve));
try {
  const result = await new Promise((resolve, reject) => {
    const child = spawn(
      "php",
      [
        fileURLToPath(new URL("./test-pdf-worker-render.php", import.meta.url)),
        ...native,
        runtime,
        `http://127.0.0.1:${server.address().port}`,
      ],
      { stdio: ["ignore", "pipe", "pipe"] },
    );
    let stdout = "",
      stderr = "";
    child.stdout.on("data", (part) => (stdout += part));
    child.stderr.on("data", (part) => (stderr += part));
    const timer = setTimeout(() => {
      child.kill();
      reject(Error("Native worker render timed out"));
    }, 300000);
    child.on("error", (error) => {
      clearTimeout(timer);
      reject(error);
    });
    child.on("exit", (code) => {
      clearTimeout(timer);
      resolve({ code, stdout, stderr });
    });
  });
  assert.equal(result.code, 0, result.stderr + "\n" + result.stdout);
  assert.equal(healthy, 2);
  assert.equal(broken, 1);
  console.log(result.stdout);
  console.log(
    "PASS: renderer invoked twice successfully and once with a missing image. Inspect output/current.pdf; synthetic print HTML only.",
  );
} finally {
  await new Promise((resolve) => server.close(resolve));
}
