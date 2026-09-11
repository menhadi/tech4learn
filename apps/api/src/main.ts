import { createApp } from './bootstrap.js';

async function main() {
  const port = Number(process.env.PORT || '3000');
  if (!Number.isInteger(port) || port < 1 || port > 65535) {
    throw new Error('PORT must be an integer between 1 and 65535');
  }
  const app = await createApp();
  await app.listen(port, process.env.HOST || '127.0.0.1');
}

void main().catch((error: unknown) => {
  console.error(error);
  process.exitCode = 1;
});
