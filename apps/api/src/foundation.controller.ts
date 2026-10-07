import { Body, Controller, Get, Headers, HttpCode, Param, Patch, Post, Res } from "@nestjs/common";
import type { Response } from "express";
import { IdentityService } from "./identity.service.js";
import { session, setCookie } from "./identity.controller.js";
import { FoundationService } from "./foundation.service.js";

@Controller()
export class FoundationController {
  constructor(private readonly identity: IdentityService, private readonly links: FoundationService) {}
  private actor(cookie?: string) { return this.identity.account(session(cookie)); }
  @Post("foundation/auth/login")
  @HttpCode(200)
  async login(@Body() b: Record<string,unknown>, @Res({passthrough:true}) response:Response) {
    const raw=await this.identity.login(b??{});
    try {
      const link=await this.links.loginIdentity(await this.identity.account(raw),b?.nativeOrganisationId);
      setCookie(response,raw);
      return link;
    } catch(error) { await this.identity.logout(raw);throw error; }
  }
  @Get("platform/foundation/organisations")
  async list(@Headers("cookie") c?: string) { return this.links.list(await this.actor(c)); }
  @Post("platform/foundation/organisations")
  async link(@Body() b: Record<string, unknown>, @Headers("cookie") c?: string) { return this.links.linkOrganisation(await this.actor(c), b ?? {}); }
  @Patch("platform/foundation/organisations/:native")
  async update(@Param("native") n: string, @Body() b: Record<string, unknown>, @Headers("cookie") c?: string) { return this.links.setOrganisation(await this.actor(c), n, b ?? {}); }
  @Get("platform/foundation/organisations/:native/staff")
  async staff(@Param("native") n: string, @Headers("cookie") c?: string) { return this.links.staff(await this.actor(c), n); }
  @Post("platform/foundation/organisations/:native/staff")
  async addStaff(@Param("native") n: string, @Body() b: Record<string, unknown>, @Headers("cookie") c?: string) { return this.links.linkStaff(await this.actor(c), n, b ?? {}); }
  @Patch("platform/foundation/organisations/:native/staff/:user")
  async updateStaff(@Param("native") n: string, @Param("user") u: string, @Body() b: Record<string, unknown>, @Headers("cookie") c?: string) { return this.links.setStaff(await this.actor(c), n, u, b ?? {}); }
  @Get("foundation/organisations/:native/staff/:user/attendance-context")
  async context(@Param("native") n: string, @Param("user") u: string, @Headers("cookie") c?: string) { return this.links.context(await this.actor(c), n, u); }
}
