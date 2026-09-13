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
import { ExamContentService } from "./exam-content.service.js";
@Controller()
export class ExamContentController {
  @Get("platform/exam-content/:org/transfers")
  async history(@Param("org") org: string, @Headers("cookie") cookie?: string) {
    return this.content.history(
      await this.identity.account(session(cookie)),
      org,
    );
  }
  constructor(
    private readonly identity: IdentityService,
    private readonly content: ExamContentService,
  ) {}
  @Get("platform/exam-content/:org/modules") async modules(
    @Param("org") org: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.modules(
      await this.identity.account(session(cookie)),
      org,
    );
  }
  @Post("platform/exam-content/:org/modules") async save(
    @Param("org") org: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.setModules(
      await this.identity.account(session(cookie)),
      org,
      body ?? {},
    );
  }
  @Get("platform/exam-content/:org/questions") async central(
    @Param("org") org: string,
    @Query("source") source = "central",
    @Query("search") search = "",
    @Query("after") after = "0",
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.questions(
      await this.identity.account(session(cookie)),
      org,
      source,
      search,
      after,
      true,
    );
  }
  @Get("organisations/:org/exam-content/questions") async own(
    @Param("org") org: string,
    @Query("search") search = "",
    @Query("after") after = "0",
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.questions(
      await this.identity.account(session(cookie)),
      org,
      "organisation",
      search,
      after,
    );
  }
  @Post("platform/exam-content/:org/transfer") async transfer(
    @Param("org") org: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.transfer(
      await this.identity.account(session(cookie)),
      org,
      body ?? {},
    );
  }
}
