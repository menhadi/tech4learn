import assert from 'node:assert/strict';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { resolve, join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { createServer } from 'node:http';
import { spawn } from 'node:child_process';

// Runs ExamElite's own PDF command against synthetic loopback print pages.
// No deployed app bootstrap, database, credentials or remote assets are used.
const [rendererPath, playwrightEntry, outputDirectory] = process.argv.slice(2);
if (!rendererPath || !playwrightEntry || !outputDirectory || process.env.NODE_ENV === 'production')
  throw Error('Use node test-pdf-renderer-smoke.mjs RENDERER PLAYWRIGHT_ENTRY OUTPUT_DIRECTORY locally');
const directory = resolve(outputDirectory);
await mkdir(directory, { recursive: true });
const source = await readFile(rendererPath, 'utf8');
const importLine = "import { chromium } from 'playwright';";
assert.equal(source.split(importLine).length, 2, 'Known native renderer dependency import');
// Only dependency resolution changes in this copied entrypoint, never renderer logic.
const executable = join(directory, 'native-renderer.mjs');
await writeFile(executable, source.replace(importLine,
  `import { chromium } from ${JSON.stringify(pathToFileURL(resolve(playwrightEntry)).href)};`));
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAIAAAABACAIAAABdtOgoAAABNElEQVR4nO3ZMa7CMBQFUYJYCouiY210LIq9hIIGISX4BsfzJOZ0X/jbT5oEQZjmeT6Ic6QH+HcGgBkAZgCYAWAGgBkAZgCYAWAGgBkAZgCYAWAGgBkAZgCYAWAGgBkAdtr2b+fr/f3Px+3C7kOd+/s+U/qj/MeRm4/vtU+q2vzZW9DKqV9f3WOfVMH5gwAt+45ck6o5f2uA8VdH3wZl5/dTEKwpQHoxLq3vtU+q8vzeATADwAwAMwCsKUD61XRpfa99UpXn9w6AtQZoj7++stc+qbLzB3dAy44j16Rqzp+9BY25OvZ7Glpw/vhx9Eud5+nsucDvAerLT0EwA8AMADMAzAAwA8AMADMAzAAwA8AMADMAzAAwA8AMADMAzAAwA8AMADMAzAAwA8AMADMAzAAwA8CedQ6Qc0CpL/YAAAAASUVORK5CYII=', 'base64');
const requests = [];
const server = createServer((req, res) => {
  requests.push(req.url);
  if(req.url === '/diagram.png') { res.writeHead(200, {'Content-Type':'image/png'}); res.end(png); return; }
  if(!['/healthy','/broken','/math-error'].includes(req.url)) {res.writeHead(404);res.end();return;}
  const image = req.url === '/broken' ? '/missing.png' : '/diagram.png';
  res.writeHead(200, {'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store',
    'Content-Security-Policy':"default-src 'none'; img-src 'self'; style-src 'unsafe-inline'"});
  res.end(`<!doctype html><html data-mathjax-ready="1"><head><meta charset="utf-8"><meta name="exam-pdf-template-version" content="23"><style>
  @page {size:A4;margin:16mm} body{font:16px Arial;color:#111} h1{font-size:24px} .question-text{line-height:1.6} img{width:128px;height:64px;border:1px solid #333} .page{break-before:page}
  </style></head><body><h1>Synthetic exam renderer check</h1>
  <p>Question paper - isolated local fixture</p><div class="question-text"><p>1. Two pairs make four. Explain the diagram.</p><img src="${image}" alt="Synthetic diagram"></div>
  <p>Formula: <math><msup><mi>x</mi><mn>2</mn></msup><mo>+</mo><mn>2</mn><mi>x</mi><mo>+</mo><mn>1</mn></math></p>
  ${req.url === '/math-error' ? '<div class="MathJax_Error">Synthetic math failure</div>' : ''}
  <section class="page"><h1>Solutions</h1><p>Two groups of two contain four items.</p><p>End of synthetic fixture.</p></section></body></html>`);
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
try {
  for(const scenario of ['healthy','broken','math-error']) {
    const output = join(directory, `${scenario}.pdf`);
    // Refuse existing output: a stale artifact must not make a failed render pass.
    await assert.rejects(readFile(output), {code:'ENOENT'});
    const result = await new Promise((resolve, reject) => {
      const child = spawn(process.execPath, [executable, `http://127.0.0.1:${server.address().port}/${scenario}`, output, '23'], {
        env: {...process.env, PDF_RENDER_BYPASS_EDGE:'0'}, stdio:['ignore','pipe','pipe'],
      });
      let stdout='',stderr='';
      child.stdout.on('data', chunk => stdout += chunk);
      child.stderr.on('data', chunk => stderr += chunk);
      const deadline = setTimeout(() => {child.kill();reject(Error('Native renderer timed out'));}, 180000);
      child.on('error', error => {clearTimeout(deadline);reject(error);});
      child.on('exit', code => {clearTimeout(deadline);resolve({code,stdout,stderr});});
    });
    if(scenario === 'healthy') {
      assert.equal(result.code, 0, result.stderr);
      const bytes = await readFile(output);
      assert.equal(bytes.subarray(0,5).toString(), '%PDF-');
      assert.ok(bytes.length > 1000);
    } else {
      assert.notEqual(result.code, 0, 'Invalid content must not publish a PDF');
      assert.match(result.stderr, scenario === 'broken' ? /print image could not be loaded/ : /PDF_MATHJAX_RENDER_FAILED/);
      await assert.rejects(readFile(output), {code:'ENOENT'});
    }
    console.log(`PASS native renderer: ${scenario}`);
  }
  assert.ok(requests.includes('/diagram.png'));
  console.log(`PDF renderer smoke passed; inspect ${join(directory,'healthy.pdf')}. Synthetic print HTML only; native Blade/worker acceptance is separate.`);
} finally { await new Promise(resolve => server.close(resolve)); }
