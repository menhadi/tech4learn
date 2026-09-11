import { Module, type DynamicModule } from "@nestjs/common";
import { HealthController } from "./health.controller.js";
import { Database } from "./database.js";
import { IdentityService } from "./identity.service.js";
import { IdentityController } from "./identity.controller.js";
import { AccessService } from "./access.service.js";
import { RecordsService } from "./records.service.js";
import { AccessController } from "./access.controller.js";
import { LearnersController } from "./learners.controller.js";
import { LearnersService } from "./learners.service.js";

@Module({})
export class AppModule {
  static configure(database?: Database): DynamicModule {
    return {
      module: AppModule,
      controllers: [
        HealthController,
        IdentityController,
        AccessController,
        LearnersController,
      ],
      providers: [
        database ? { provide: Database, useValue: database } : Database,
        IdentityService,
        AccessService,
        RecordsService,
        LearnersService,
      ],
    };
  }
}
