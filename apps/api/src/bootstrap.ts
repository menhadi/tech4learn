import 'reflect-metadata';
import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module.js';

export async function createApp() {
  const app = await NestFactory.create(AppModule, { logger: ['error', 'warn', 'log'] });
  app.setGlobalPrefix('api/v1');
  app.enableCors({ origin: process.env.ADMIN_ORIGIN || 'http://localhost:5173' });
  app.enableShutdownHooks();
  return app;
}
