import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createApp } from '../dist/bootstrap.js';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

test('HTTP liveness contract and unknown routes', async () => {
  const app = await createApp();
  try {
    await app.listen(0, '127.0.0.1');
    const base = await app.getUrl();
    const response = await fetch(`${base}/api/v1/health`);
    assert.equal(response.status, 200);
    const body = await response.json();
    assert.equal(body.status, 'ok');
    assert.equal(body.service, 'tech4learn-api');
    assert.ok(Number.isFinite(Date.parse(body.timestamp)));
    assert.equal((await fetch(`${base}/api/v1/organisations`)).status, 401);
  } finally {
    await app.close();
  }
});

test('same-domain deployment serves only built assets and preserves API 404s', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'tech4learn-admin-'));
  let app;
  try {
    await writeFile(join(directory, 'index.html'), '<h1>Tech4Learn scaffold</h1>');
    await writeFile(join(directory, '.env'), 'PRIVATE_TEST_VALUE');
    app = await createApp(directory);
    await app.listen(0, '127.0.0.1');
    const base = await app.getUrl();
    const page = await fetch(base);
    assert.equal(page.status, 200);
    assert.match(await page.text(), /Tech4Learn scaffold/);
    assert.equal((await fetch(`${base}/api/v1/health`)).status, 200);
    assert.equal((await fetch(`${base}/api/v1/missing`)).status, 404);
    assert.equal((await fetch(`${base}/.env`)).status, 404);
    assert.equal((await fetch(`${base}/package.json`)).status, 404);
  } finally {
    if (app) await app.close();
    await rm(directory, { recursive: true, force: true });
  }
});

test('invalid static directory fails before starting a server', async () => {
  await assert.rejects(createApp('relative-path'), /ADMIN_DIST_PATH/);
});
