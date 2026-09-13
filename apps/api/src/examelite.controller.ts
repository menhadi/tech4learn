import { Controller, Get, Headers, Param, Query } from "@nestjs/common";
import { IdentityService } from "./identity.service.js";
import { session } from "./identity.controller.js";
import { ExamEliteService } from "./examelite.service.js";

@Controller("organisations/:org/examelite")
export class ExamEliteController {
  constructor(
    private readonly identity: IdentityService,
    private readonly connector: ExamEliteService,
  ) {}
  @Get("status") async status(
    @Param("org") org: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.connector.status(
      await this.identity.account(session(cookie)),
      org,
    );
  }
  @Get("exams") async exams(
    @Param("org") org: string,
    @Query("after") after?: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.connector.list(
      await this.identity.account(session(cookie)),
      org,
      after,
    );
  }
  @Get("learners/:learner/results") async results(
    @Param("org") org: string,
    @Param("learner") learner: string,
    @Query("after") after?: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.connector.list(
      await this.identity.account(session(cookie)),
      org,
      after,
      learner,
    );
  }
}
