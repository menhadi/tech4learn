import { FaceControlController } from "./face-control.controller.js";
import { ExamEliteController } from "./examelite.controller.js";
import { ExamElitePlatformController } from "./examelite-platform.controller.js";
import { ExamEliteService } from "./examelite.service.js";
import { ExamWorkspaceService } from "./exam-workspace.service.js";
import { ExamWorkspaceController } from "./exam-workspace.controller.js";
import {
  FaceControlService,
  FaceControlClient,
} from "./face-control.service.js";
import { FaceJobsService } from "./face-jobs.service.js";
import { ConfigurationService } from "./configuration.service.js";
import { LearnerPhotosService } from "./learner-photos.service.js";
import { LearnerPhotosController } from "./learner-photos.controller.js";
import { FaceMatchingService } from "./face-matching.service.js";
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
        ExamEliteController,
        ExamElitePlatformController,
        ExamWorkspaceController,
        FaceControlController,
        LearnerPhotosController,
        AcademicController,
        AttendanceController,
        ConfigurationController,
        HealthController,
        IdentityController,
        AccessController,
        LearnersController,
      ],
      providers: [
        ExamEliteService,
        ExamWorkspaceService,
        FaceControlService,
        FaceControlClient,
        LearnerPhotosService,
        FaceMatchingService,
        FaceJobsService,
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
