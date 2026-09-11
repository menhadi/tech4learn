import {
  Body,
  Controller,
  Get,
  Headers,
  HttpCode,
  Param,
  Patch,
  Post,
  Res,
} from "@nestjs/common";
import type { Response } from "express";
import { IdentityService } from "./identity.service.js";

const cookieName = () =>
  process.env.NODE_ENV === "production" ? "__Host-t4l_session" : "t4l_session";
export function session(cookie?: string) {
  return cookie
    ?.split(";")
    .map((part) => part.trim())
    .find((part) => part.startsWith(`${cookieName()}=`))
    ?.slice(cookieName().length + 1);
}
function setCookie(
  response: Response,
  value: string,
  maxAge = 12 * 60 * 60 * 1000,
) {
  response.cookie(cookieName(), value, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge,
  });
}
@Controller()
export class IdentityController {
  constructor(private readonly identity: IdentityService) {}
  @Post("auth/login")
  @HttpCode(200)
  async login(
    @Body() body: Record<string, unknown>,
    @Res({ passthrough: true }) response: Response,
  ) {
    setCookie(response, await this.identity.login(body ?? {}));
    return { ok: true };
  }
  @Get("auth/me")
  async me(@Headers("cookie") cookie?: string) {
    const user = await this.identity.account(session(cookie));
    return { user, organisations: await this.identity.organisations(user) };
  }
  @Post("auth/logout")
  @HttpCode(200)
  async logout(
    @Res({ passthrough: true }) response: Response,
    @Headers("cookie") cookie?: string,
  ) {
    await this.identity.logout(session(cookie));
    setCookie(response, "", 0);
    return { ok: true };
  }
  @Post("auth/password")
  @HttpCode(200)
  async password(
    @Body() body: Record<string, unknown>,
    @Res({ passthrough: true }) response: Response,
    @Headers("cookie") cookie?: string,
  ) {
    const result = await this.identity.changePassword(
      await this.identity.account(session(cookie)),
      body ?? {},
    );
    setCookie(response, "", 0);
    return result;
  }
  @Get("organisations")
  async list(@Headers("cookie") cookie?: string) {
    return this.identity.organisations(
      await this.identity.account(session(cookie)),
    );
  }
  @Post("organisations")
  async create(
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.identity.create(
      await this.identity.account(session(cookie)),
      body ?? {},
    );
  }
  @Get("organisations/:id")
  async get(@Param("id") id: string, @Headers("cookie") cookie?: string) {
    const user = await this.identity.account(session(cookie));
    const org = await this.identity.readOrganisation(user, id);
    return org;
  }
  @Patch("organisations/:id")
  async update(
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.identity.update(
      await this.identity.account(session(cookie)),
      id,
      body ?? {},
    );
  }
  @Post("organisations/:id/invitations")
  async invite(
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.identity.addInvitation(
      await this.identity.account(session(cookie)),
      id,
      body ?? {},
    );
  }
  // Token is in the request body, never a URL path that would enter access logs.
  @Post("invitations/preview")
  @HttpCode(200)
  async preview(@Body() body: Record<string, unknown>) {
    return this.identity.invitation(
      typeof body?.token === "string" ? body.token : "",
    );
  }
  @Post("invitations/accept")
  @HttpCode(200)
  async accept(
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.identity.accept(body ?? {}, session(cookie));
  }
}
