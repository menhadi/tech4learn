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
import { ExamWorkspaceService } from "./exam-workspace.service.js";
@Controller("organisations/:org/exam-workspace")
export class ExamWorkspaceController {
  constructor(
    private readonly identity: IdentityService,
    private readonly service: ExamWorkspaceService,
  ) {}
  @Get("capabilities") async capabilities(
    @Param("org") org: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-capabilities:${account.id}`, 30, 60);
    return this.service.capabilities(account, org, query);
  }
  @Get() async status(
    @Param("org") org: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.status(
      await this.identity.account(session(cookie)),
      org,
    );
  }
  @Get("students") async students(
    @Param("org") org: string,
    @Query("search") search = "",
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.students(
      await this.identity.account(session(cookie)),
      org,
      search,
    );
  }
  @Post("restrictions") async restrict(
    @Param("org") org: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.restrict(
      await this.identity.account(session(cookie)),
      org,
      b ?? {},
    );
  }
  @Post("launch") async launch(
    @Param("org") org: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.launch(
      await this.identity.account(session(cookie)),
      org,
      b ?? {},
    );
  }
}
