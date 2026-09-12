import {
  BadRequestException,
  Body,
  Controller,
  Get,
  Headers,
  Param,
  Patch,
  Post,
  Query,
} from "@nestjs/common";
import { LearnersService } from "./learners.service.js";
import { IdentityService } from "./identity.service.js";
import { session } from "./identity.controller.js";
@Controller("organisations/:org")
export class LearnersController {
  constructor(
    private readonly service: LearnersService,
    private readonly identity: IdentityService,
  ) {}
  private user(cookie?: string) {
    return this.identity.account(session(cookie));
  }
  @Get("learners") async list(
    @Param("org") org: string,
    @Query("search") search: string,
    @Query("offset") offset: string,
    @Query("group_id") groupId: string,
    @Query("limit") limit: string,
    @Query("sort") sort: string,
    @Query("direction") direction: string,
    @Query("filters") filters: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.list(
      await this.user(cookie),
      org,
      search || "",
      Number(offset || 0),
      groupId || "",
      {limit:Number(limit||50),sort,direction,filters:this.parseFilters(filters)},
    );
  }
  private parseFilters(value?:string):Record<string,string> {
    if(!value)return {};
    try {if(value.length>20000)throw new Error();return JSON.parse(value);} catch {throw new BadRequestException("Invalid filters.");}
  }
  @Get("learners/:id") async detail(
    @Param("org") org: string,
    @Param("id") id: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.detail(await this.user(cookie), org, id);
  }
  @Post("demo-student") async demo(
    @Param("org") org: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.demoStudent(await this.user(cookie), org, b ?? {});
  }
  @Post("learners") async create(
    @Param("org") org: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.save(await this.user(cookie), org, b ?? {});
  }
  @Patch("learners/:id") async save(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.save(await this.user(cookie), org, b ?? {}, id);
  }
  @Post("learners/:id/transfer") async transfer(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.transfer(await this.user(cookie), org, id, b ?? {});
  }
  @Post("learners/:id/archive") async archive(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.archive(await this.user(cookie), org, id, b ?? {});
  }
  @Get("learner-fields") async fields(
    @Param("org") org: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.fields(await this.user(cookie), org);
  }
  @Post("learner-fields") async createField(
    @Param("org") org: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.saveField(await this.user(cookie), org, b ?? {});
  }
  @Patch("learner-fields/:id") async field(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.saveField(await this.user(cookie), org, b ?? {}, id);
  }
  @Post("learner-imports/preview") async preview(
    @Param("org") org: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.preview(await this.user(cookie), org, b ?? {});
  }
  @Post("learner-imports/:id/commit") async commit(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.commit(await this.user(cookie), org, id, b ?? {});
  }
}
