import {
  Body,
  Controller,
  Get,
  Headers,
  Param,
  Post,
  Query,
} from "@nestjs/common";
import { IdentityService } from "./identity.service.js";
import { session } from "./identity.controller.js";
import { ExamEliteService } from "./examelite.service.js";

@Controller("platform/examelite")
export class ExamElitePlatformController {
  constructor(
    private readonly identity: IdentityService,
    private readonly connector: ExamEliteService,
  ) {}
  @Get() async status(@Headers("cookie") cookie?: string) {
    return this.connector.platformStatus(
      await this.identity.account(session(cookie)),
    );
  }
  @Get("exams") async exams(
    @Query("after") after: string = "0",
    @Query("search") search: string = "",
    @Headers("cookie") cookie?: string,
  ) {
    return this.connector.platformExams(
      await this.identity.account(session(cookie)),
      after,
      search,
    );
  }
  @Get("organisations/:org") async organisation(
    @Param("org") org: string,
    @Query("search") search: string = "",
    @Headers("cookie") cookie?: string,
  ) {
    return this.connector.platformOrganisation(
      await this.identity.account(session(cookie)),
      org,
      search,
    );
  }
  @Post("organisations/:org") async share(
    @Param("org") org: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.connector.platformShare(
      await this.identity.account(session(cookie)),
      org,
      body ?? {},
    );
  }
  @Post("organisations/:org/learners/:learner") async connect(
    @Param("org") org: string,
    @Param("learner") learner: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.connector.platformConnect(
      await this.identity.account(session(cookie)),
      org,
      learner,
    );
  }
}
