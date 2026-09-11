import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createApp } from '../dist/bootstrap.js';

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
    assert.equal((await fetch(`${base}/api/v1/organisations`)).status, 404);
  } finally {
    await app.close();
  }
});
