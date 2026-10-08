import { test } from 'node:test';
import assert from 'node:assert/strict';
import { passwordValue, hashPassword, verifyPassword } from '../dist/security.js';

test('short passwords can be created and verified without a composition rule', async () => {
  const password = passwordValue('a');
  const stored = await hashPassword(password);
  assert.equal(await verifyPassword(password, stored), true);
  assert.equal(await verifyPassword('b', stored), false);
  assert.throws(() => passwordValue(''));
  assert.throws(() => passwordValue('a'.repeat(129)));
});
