import assert from 'node:assert/strict';
import { readFile, writeFile, mkdir, realpath, stat } from 'node:fs/promises';
import { resolve, join, sep, extname } from 'node:path';
import { pathToFileURL } from 'node:url';
import { createServer } from 'node:http';
import { spawn } from 'node:child_process';

// Exercise the application's native PDF command, not a browser automation test.
const [renderer, playwright, htmlPath, publicPath, outputPath] = process.argv.slice(2);
if (!outputPath || process.env.NODE_ENV === 'production')
  throw Error('Supply RENDERER PLAYWRIGHT_ENTRY GENERATED_HTML NATIVE_PUBLIC NEW_OUTPUT_DIRECTORY');
const directory = resolve(outputPath);
await assert.rejects(stat(directory), { code: 'ENOENT' });
const publicRoot = await realpath(publicPath);
const html = await readFile(htmlPath, 'utf8');
const version = html.match(/name="exam-pdf-template-version" content="(\d+)"/)?.[1];
assert.ok(version, 'Native print template version required');
assert.match(html, /MathJax\/MathJax\.js/);
const source = await readFile(renderer, 'utf8');
const importLine = "import { chromium } from 'playwright';";
assert.equal(source.split(importLine).length, 2);
await mkdir(directory);
const executable = join(directory, 'native-renderer.mjs');
await writeFile(executable, source.replace(importLine,
  `import { chromium } from ${JSON.stringify(pathToFileURL(resolve(playwright)).href)};`));
const requests = [], missing = [];
const server = createServer(async (req, res) => {
  try {
    const pathname = decodeURIComponent(new URL(req.url, 'http://127.0.0.1').pathname);
    if (pathname === '/paper') {
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8',
        'Content-Security-Policy': "default-src 'none'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:" });
      res.end(html); return;
    }
    assert.ok(pathname.startsWith('/MathJax/') || pathname.startsWith('/fonts/'));
    const asset = await realpath(join(publicRoot, pathname));
    assert.ok(asset.startsWith(publicRoot + sep), 'Asset must remain inside native public directory');
    const bytes = await readFile(asset);
    requests.push(pathname);
    const mime = { '.js': 'application/javascript', '.css': 'text/css', '.woff': 'font/woff', '.woff2': 'font/woff2', '.otf': 'font/otf' }[extname(asset)] || 'application/octet-stream';
    res.writeHead(200, { 'Content-Type': mime }); res.end(bytes);
  } catch {
    missing.push(req.url); res.writeHead(404); res.end();
  }
});
await new Promise(done => server.listen(0, '127.0.0.1', done));
try {
  const output = join(directory, 'native-paper.pdf');
  const result = await new Promise((done, reject) => {
    const child = spawn(process.execPath, [executable, `http://127.0.0.1:${server.address().port}/paper`, output, version],
      { env: { ...process.env, PDF_RENDER_BYPASS_EDGE: '0' }, stdio: ['ignore', 'pipe', 'pipe'] });
    let stderr = '';
    child.stdout.resume(); child.stderr.on('data', chunk => stderr += chunk);
    const timer = setTimeout(() => { child.kill(); reject(Error('Native renderer timed out')); }, 180000);
    child.on('error', error => { clearTimeout(timer); reject(error); });
    child.on('exit', code => { clearTimeout(timer); done({ code, stderr }); });
  });
  assert.equal(result.code, 0, result.stderr);
  assert.equal((await readFile(output)).subarray(0, 5).toString(), '%PDF-');
  assert.ok(requests.includes('/MathJax/MathJax.js'), 'Actual MathJax loader used');
  assert.ok(requests.some(path => path.includes('/jax/output/')), 'Actual maths output processor used');
  assert.deepEqual(missing.filter(path => path !== '/favicon.ico'), [], 'All requested print assets exist');
  await writeFile(join(directory, 'assets.json'), JSON.stringify(requests, null, 2));
  console.log(`PASS: native print HTML and real MathJax assets rendered to ${output}`);
} finally { await new Promise(done => server.close(done)); }
