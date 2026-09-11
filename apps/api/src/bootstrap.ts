import 'reflect-metadata';
import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module.js';
import type { NestExpressApplication } from '@nestjs/platform-express';
import { existsSync } from 'node:fs';
import { isAbsolute, join } from 'node:path';

export async function createApp(adminDirectory = process.env.ADMIN_DIST_PATH) {
  if (adminDirectory && (!isAbsolute(adminDirectory) || !existsSync(join(adminDirectory, 'index.html')))) {
    throw new Error('ADMIN_DIST_PATH must be an absolute directory containing the built admin index.html');
  }
  const app = await NestFactory.create<NestExpressApplication>(AppModule, { logger: ['error', 'warn', 'log'] });
  app.setGlobalPrefix('api/v1');
  app.enableCors({ origin: process.env.ADMIN_ORIGIN || 'http://localhost:5173' });
  app.enableShutdownHooks();
  if (adminDirectory) {
    app.useStaticAssets(adminDirectory, { dotfiles: 'deny', index: 'index.html' });
  }
  return app;
}
