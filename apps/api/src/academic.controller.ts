import { Body, Controller, Get, Headers, Param, Post } from "@nestjs/common";
import { AcademicService } from "./academic.service.js";
import { IdentityService } from "./identity.service.js";
import { session } from "./identity.controller.js";
@Controller("organisations/:org")
export class AcademicController {
  constructor(
    private readonly service: AcademicService,
    private readonly identity: IdentityService,
  ) {}
  private user(cookie?: string) {
    return this.identity.account(session(cookie));
  }
  @Get("academic-years") async years(
    @Param("org") org: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.years(await this.user(cookie), org);
  }
  @Post("academic-years") async year(
    @Param("org") org: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.createYear(await this.user(cookie), org, b ?? {});
  }
  @Get("classes") async classes(
    @Param("org") org: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.classes(await this.user(cookie), org);
  }
  @Post("classes") async create(
    @Param("org") org: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.createClass(await this.user(cookie), org, b ?? {});
  }
  @Post("classes/:id/archive") async archiveClass(
    @Param("org") org: string,
    @Param("id") id: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.archive(await this.user(cookie), org, id, "class");
  }
  @Post("academic-years/:id/archive") async archiveYear(
    @Param("org") org: string,
    @Param("id") id: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.service.archive(await this.user(cookie), org, id, "year");
  }
}
