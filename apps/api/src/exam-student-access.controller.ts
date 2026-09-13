import {
  Body,
  Controller,
  Get,
  Headers,
  HttpCode,
  Param,
  Post,
  Query,
  Res,
} from "@nestjs/common";
import type { Response } from "express";
import { ExamStudentAccessService } from "./exam-student-access.service.js";
import { IdentityService } from "./identity.service.js";
import { session } from "./identity.controller.js";
const name = () =>
  process.env.NODE_ENV === "production" ? "__Host-t4l_exam" : "t4l_exam";
export const examSession = (cookie?: string) =>
  cookie
    ?.split(";")
    .map((p) => p.trim())
    .find((p) => p.startsWith(`${name()}=`))
    ?.slice(name().length + 1);
function cookie(res: Response, value: string) {
  res.cookie(name(), value, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: value ? 12 * 60 * 60 * 1000 : 0,
  });
}
@Controller("organisations/:org")
export class ExamStudentAccessController {
  constructor(
    private readonly service: ExamStudentAccessService,
    private readonly identity: IdentityService,
  ) {}
  @Post("exam-student-access") async issue(
    @Param("org") org: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookies?: string,
  ) {
    return this.service.issue(
      await this.identity.account(session(cookies)),
      org,
      body ?? {},
    );
  }
  @Get("exam-student-access") async list(
    @Param("org") org: string,
    @Query("learner") learner: string,
    @Headers("cookie") cookies?: string,
  ) {
    return this.service.list(
      await this.identity.account(session(cookies)),
      org,
      learner,
    );
  }
  @Post("exam-student-access/:id/revoke") async revoke(
    @Param("org") org: string,
    @Param("id") id: string,
    @Headers("cookie") cookies?: string,
  ) {
    return this.service.revoke(
      await this.identity.account(session(cookies)),
      org,
      id,
    );
  }
  @Post("student-exam/exchange") @HttpCode(200) async exchange(
    @Param("org") org: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookies: string | undefined,
    @Res({ passthrough: true }) response: Response,
  ) {
    const result = await this.service.exchange(
      org,
      body?.token,
      examSession(cookies),
    );
    cookie(response, result.secret);
    return { ok: true };
  }
  @Get("student-exam/me") async me(
    @Param("org") org: string,
    @Headers("cookie") cookies?: string,
  ) {
    const context = await this.service.context(org, examSession(cookies));
    return {
      organisation_id: context.organisation_id,
      organisation_name: context.organisation_name,
      student_name: context.student_name,
      exam_name: context.exam_name,
      expires_at: context.expires_at,
    };
  }
  @Post("student-exam/logout") @HttpCode(200) async logout(
    @Headers("cookie") cookies: string | undefined,
    @Res({ passthrough: true }) response: Response,
  ) {
    await this.service.logout(examSession(cookies));
    cookie(response, "");
    return { ok: true };
  }
}
