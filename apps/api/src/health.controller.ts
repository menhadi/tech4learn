import { Controller, Get } from '@nestjs/common';
import type { HealthResponse } from '@tech4learn/contracts';

@Controller('health')
export class HealthController {
  @Get()
  getHealth(): HealthResponse {
    return { status: 'ok', service: 'tech4learn-api', timestamp: new Date().toISOString() };
  }
}
