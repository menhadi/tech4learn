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
  @Get("plans") async plans(
    @Param("org") org: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-plans:${account.id}`, 60, 60);
    return this.service.plans(account, org, query);
  }
  @Get("plan-fields") async planFields(
    @Param("org") org: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-plan-fields:${account.id}`, 30, 60);
    return this.service.planFields(account, org, query);
  }
  @Post("plans") async createPlan(
    @Param("org") org: string,
    @Body() body: Record<string, unknown>,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-plan-create:${account.id}`, 30, 60);
    return this.service.createPlan(account, org, body ?? {}, query);
  }
  @Get("central-plans") async centralPlans(
    @Param("org") org: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-central-plans:${account.id}`, 60, 60);
    return this.service.centralPlans(account, org, query);
  }
  @Get("central-plans/:id") async centralPlan(
    @Param("org") org: string,
    @Param("id") id: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-central-plan:${account.id}`, 60, 60);
    return this.service.centralPlan(account, org, id, query);
  }
  @Post("central-plans/:id") async updatePlan(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-plan-update:${account.id}`, 30, 60);
    return this.service.updatePlan(account, org, id, body ?? {}, query);
  }
  @Post("plan") async assignPlan(
    @Param("org") org: string,
    @Body() body: Record<string, unknown>,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-plan-write:${account.id}`, 30, 60);
    return this.service.assignPlan(account, org, body ?? {}, query);
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
