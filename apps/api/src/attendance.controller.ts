import {
  Body,
  Controller,
  Get,
  Headers,
  Param,
  Patch,
  Post,
  Query,
  StreamableFile,
} from "@nestjs/common";
import { AttendanceService } from "./attendance.service.js";
import { IdentityService } from "./identity.service.js";
import { session } from "./identity.controller.js";

@Controller("organisations/:org/attendance")
export class AttendanceController {
  @Get("ai/providers") async providers(
    @Param("org") org: string,
    @Headers("cookie") c?: string,
  ) {
    return this.service.providers(await this.user(c), org);
  }
  @Get(":id/analyses") async analyses(
    @Param("org") org: string,
    @Param("id") id: string,
    @Headers("cookie") c?: string,
  ) {
    return this.service.analyses(await this.user(c), org, id);
  }
  @Post(":id/analyse") async analyse(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") c?: string,
  ) {
    return this.service.analyse(await this.user(c), org, id, b ?? {});
  }
  constructor(
    private readonly service: AttendanceService,
    private readonly identity: IdentityService,
  ) {}
  private user(cookie?: string) {
    return this.identity.account(session(cookie));
  }
  @Get("policy") async policy(
    @Param("org") org: string,
    @Headers("cookie") c?: string,
  ) {
    return this.service.getPolicy(await this.user(c), org);
  }
  @Patch("policy") async savePolicy(
    @Param("org") org: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") c?: string,
  ) {
    return this.service.savePolicy(await this.user(c), org, b ?? {});
  }
  @Post("captures") async start(
    @Param("org") org: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") c?: string,
  ) {
    return this.service.start(await this.user(c), org, b ?? {});
  }
  @Post("captures/:id/submit") async submit(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") c?: string,
  ) {
    return this.service.submit(await this.user(c), org, id, b ?? {});
  }
  @Get() async list(
    @Param("org") org: string,
    @Query("date") date: string,
    @Query("offset") offset: string,
    @Headers("cookie") c?: string,
  ) {
    return this.service.list(
      await this.user(c),
      org,
      date,
      Number(offset || 0),
    );
  }
  @Get(":id/photo") async photo(
    @Param("org") org: string,
    @Param("id") id: string,
    @Headers("cookie") c?: string,
  ) {
    return new StreamableFile(
      await this.service.photo(await this.user(c), org, id),
      { type: "image/jpeg", disposition: 'inline; filename="attendance.jpg"' },
    );
  }
  @Get(":id") async detail(
    @Param("org") org: string,
    @Param("id") id: string,
    @Headers("cookie") c?: string,
  ) {
    return this.service.detail(await this.user(c), org, id);
  }
  @Post(":id/review") async review(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") c?: string,
  ) {
    return this.service.review(await this.user(c), org, id, b ?? {});
  }
}
