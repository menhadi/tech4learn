import { ConfigurationService } from "./configuration.service.js";
import { AttendanceService } from "./attendance.service.js";
import { AttendanceController } from "./attendance.controller.js";
import { ConfigurationController } from "./configuration.controller.js";
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
import { AcademicService } from "./academic.service.js";
import { AcademicController } from "./academic.controller.js";

@Module({})
export class AppModule {
  static configure(database?: Database): DynamicModule {
    return {
      module: AppModule,
      controllers: [
        AcademicController,
        AttendanceController,
        ConfigurationController,
        HealthController,
        IdentityController,
        AccessController,
        LearnersController,
      ],
      providers: [
        AcademicService,
        AttendanceService,
        database ? { provide: Database, useValue: database } : Database,
        ConfigurationService,
        IdentityService,
        AccessService,
        RecordsService,
        LearnersService,
      ],
    };
  }
}
