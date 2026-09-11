import { Module, type DynamicModule } from "@nestjs/common";
import { HealthController } from "./health.controller.js";
import { Database } from "./database.js";
import { IdentityService } from "./identity.service.js";
import { IdentityController } from "./identity.controller.js";

@Module({})
export class AppModule {
  static configure(database?: Database): DynamicModule {
    return {
      module: AppModule,
      controllers: [HealthController, IdentityController],
      providers: [
        database ? { provide: Database, useValue: database } : Database,
        IdentityService,
      ],
    };
  }
}
