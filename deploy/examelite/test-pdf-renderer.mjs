// Native renderer contract check with a browser double, never a browser launch.
// Usage: node test-pdf-renderer.mjs PATH_TO_PATCHED_NATIVE_RENDERER
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';
const source = readFileSync(process.argv[2], 'utf8');
assert.equal((source.match(/import \{ chromium \} from 'playwright';/g) || []).length, 1);
const modern = source.includes('expectedTemplateVersion');
const run = new Function('chromium', 'process', 'document', 'setTimeout', `return (async () => {
${source.replace("import { chromium } from 'playwright';", '')}
})();`);
const image = (width = 12, height = 12) => ({ complete: true, naturalWidth: width, naturalHeight: height });
async function scenario(images, { status = 200, readinessError = false, versions = ['23'], missingSource = false, mathError = false, printError = false } = {}) {
  let printed = 0, closed = 0, navigations = 0, removed = 0;
  const document = {
    images: [...images], fonts: { ready: Promise.resolve() },
    querySelector: () => mathError ? { textContent: 'Math processing error' } : null,
    querySelectorAll: selector => selector === '[data-pdf-missing-image]' && missingSource
      ? [{ getAttribute: () => 'synthetic missing source' }] : [],
    createElement: () => ({ style: {} }),
  };
  for (const item of document.images) {
    item.src = 'https://synthetic.invalid/diagram.png';
    item.closest = () => ({});
    item.remove = item.replaceWith = () => {
      document.images = document.images.filter(candidate => candidate !== item);
      removed++;
    };
  }
  const page = {
    goto: async () => { navigations++; return { ok: () => status === 200, status: () => status, text: async () => 'synthetic response' }; },
    setExtraHTTPHeaders: async () => {},
    locator: () => ({ getAttribute: async () => versions[Math.min(navigations - 1, versions.length - 1)], innerText: async () => 'Synthetic exam' }),
    close: async () => {},
    waitForFunction: async () => { if (readinessError) throw Error('Math readiness timeout'); },
    evaluate: async fn => fn(),
    emulateMedia: async () => {},
    pdf: async options => { assert.equal(options.format, 'A4'); if (printError) throw Error('Synthetic print failure'); printed++; },
  };
  const context = { close: async () => {}, newPage: async () => page, newCDPSession: async () => ({ send: async () => {} }) };
  const chromium = { launch: async () => ({ ...context, newContext: async () => context, close: async () => { closed++; } }) };
  let error;
  try {
    // Clock double avoids native retry/image-wait delays; no network or browser.
    await run(chromium, { argv: ['node', 'renderer', 'https://synthetic.invalid/print', 'synthetic.pdf', '23'], env: {} }, document, callback => callback());
  } catch (caught) { error = caught; }
  assert.equal(closed, 1, `Renderer always closes its browser (${error?.message ?? 'no failure'})`);
  return { printed, error, navigations, removed };
}
for (const images of [[], [image()], [image(), image()]]) {
  const result = await scenario(images);
  assert.equal(result.error, undefined);
  assert.equal(result.printed, 1, 'Healthy images permit the native print');
}
for (const images of [[image(0)], [image(1, 0)], [image(), image(0, 0)], [{ ...image(), complete: false, addEventListener: (_event, callback) => callback() }]]) {
  const result = await scenario(images);
  assert.match(result.error?.message ?? '', /print image could not be loaded/);
  assert.equal(result.printed, 0, 'Broken or incomplete images never reach PDF output');
}
for (const options of [{ status: 403 }, { readinessError: true }, { printError: true }]) {
  const result = await scenario([image()], options);
  assert.ok(result.error);
  assert.equal(result.printed, 0);
}
if (modern) {
  for (const options of [{ missingSource: true }, { mathError: true }]) {
    const result = await scenario([], options);
    assert.ok(result.error);
    assert.equal(result.printed, 0);
  }
  const replaced = await scenario([image(0)]);
  assert.equal(replaced.removed, 1, 'Native placeholder handling actually removed the broken image');
  assert.match(replaced.error?.message ?? '', /print image could not be loaded/);
  const recovered = await scenario([], { versions: ['22', '23'] });
  assert.equal(recovered.error, undefined);
  assert.equal(recovered.navigations, 2);
  assert.equal(recovered.printed, 1);
  const mismatch = await scenario([], { versions: ['22'] });
  assert.match(mismatch.error?.message ?? '', /PDF_RENDER_TRANSIENT_VERSION_MISMATCH/);
  assert.equal(mismatch.navigations, 4);
  assert.equal(mismatch.printed, 0);
}
console.log('Native PDF renderer contract: healthy/broken/incomplete images, HTTP/readiness failure and browser cleanup passed. No browser was launched or PDF rendered.');
