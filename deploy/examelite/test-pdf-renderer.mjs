// Native renderer contract check with a browser double, never a browser launch.
// Usage: node test-pdf-renderer.mjs PATH_TO_PATCHED_NATIVE_RENDERER
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';
const source = readFileSync(process.argv[2], 'utf8');
assert.equal((source.match(/import \{ chromium \} from 'playwright';/g) || []).length, 1);
const run = new Function('chromium', 'process', 'document', `return (async () => {
${source.replace("import { chromium } from 'playwright';", '')}
})();`);
const image = (width = 12, height = 12) => ({ complete: true, naturalWidth: width, naturalHeight: height });
async function scenario(images, { status = 200, readinessError = false } = {}) {
  let printed = 0, closed = 0;
  const page = {
    goto: async () => ({ ok: () => status === 200, status: () => status }),
    waitForFunction: async () => { if (readinessError) throw Error('Math readiness timeout'); },
    evaluate: async fn => fn(),
    emulateMedia: async () => {},
    pdf: async options => { assert.equal(options.format, 'A4'); printed++; },
  };
  const chromium = { launch: async () => ({ newPage: async () => page, close: async () => { closed++; } }) };
  let error;
  try {
    await run(chromium, { argv: ['node', 'renderer', 'https://synthetic.invalid/print', 'synthetic.pdf'], env: {} }, { images, fonts: { ready: Promise.resolve() } });
  } catch (caught) { error = caught; }
  assert.equal(closed, 1, 'Renderer always closes its browser');
  return { printed, error };
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
for (const options of [{ status: 403 }, { readinessError: true }]) {
  const result = await scenario([image()], options);
  assert.ok(result.error);
  assert.equal(result.printed, 0);
}
console.log('Native PDF renderer contract: healthy/broken/incomplete images, HTTP/readiness failure and browser cleanup passed. No browser was launched or PDF rendered.');
