import { Body, Controller, Get, Headers, HttpCode, Param, Patch, Post, Res } from "@nestjs/common";
import type { Response } from "express";
import { IdentityService } from "./identity.service.js";
import { session, setCookie } from "./identity.controller.js";
import { FoundationService } from "./foundation.service.js";

@Controller()
export class FoundationController {
  constructor(private readonly identity: IdentityService, private readonly links: FoundationService) {}
  private actor(cookie?: string) { return this.identity.account(session(cookie)); }
  @Post("platform/foundation/attendance-onboarding")
  @HttpCode(200)
  async provisionCompanion(@Body() body: Record<string, unknown>, @Headers("cookie") cookie?: string) {
    return this.links.provisionCompanion(await this.actor(cookie), body ?? {});
  }
  @Post("platform/foundation/attendance-administrators")
  @HttpCode(200)
  async provisionAttendanceAdmin(@Body() body: Record<string, unknown>, @Headers("cookie") cookie?: string) {
    return this.links.provisionAttendanceAdmin(await this.actor(cookie), body ?? {});
  }
  @Get("platform/foundation/platforms/:native/staff")
  async platformStaff(@Param("native") n:string,@Headers("cookie") c?:string) {return this.links.platformStaff(await this.actor(c),n);}
  @Post("platform/foundation/platforms/:native/staff")
  async addPlatformStaff(@Param("native") n:string,@Body() b:Record<string,unknown>,@Headers("cookie") c?:string) {return this.links.linkPlatformStaff(await this.actor(c),n,b??{});}
  @Patch("platform/foundation/platforms/:native/staff/:user")
  async updatePlatformStaff(@Param("native") n:string,@Param("user") u:string,@Body() b:Record<string,unknown>,@Headers("cookie") c?:string) {return this.links.setPlatformStaff(await this.actor(c),n,u,b??{});}
  @Get("foundation/platforms/:native/staff/:user/identity")
  async platformIdentity(@Param("native") n:string,@Param("user") u:string,@Headers("cookie") c?:string) {return this.links.platformIdentity(await this.actor(c),n,u);}
  @Post("foundation/auth/platform/login")
  @HttpCode(200)
  async platformLogin(@Body() b:Record<string,unknown>,@Res({passthrough:true}) response:Response) {
    const raw=await this.identity.login(b??{});
    try {
      const link=await this.links.platformIdentity(await this.identity.account(raw),b?.nativeOrganisationId);
      setCookie(response,raw);return link;
    } catch(error){await this.identity.logout(raw);throw error;}
  }
  @Get("platform/foundation/organisations/:native/learners")
  async learners(@Param("native") n:string,@Headers("cookie") c?:string) {return this.links.learnerLinks(await this.actor(c),n);}
  @Post("platform/foundation/organisations/:native/learners")
  async addLearner(@Param("native") n:string,@Body() b:Record<string,unknown>,@Headers("cookie") c?:string) {return this.links.linkLearner(await this.actor(c),n,b??{});}
  @Patch("platform/foundation/organisations/:native/learners/:student")
  async updateLearner(@Param("native") n:string,@Param("student") s:string,@Body() b:Record<string,unknown>,@Headers("cookie") c?:string) {return this.links.setLearner(await this.actor(c),n,s,b??{});}
  @Get("foundation/organisations/:native/staff/:user/students/:student/learner-identity")
  async learnerIdentity(@Param("native") n:string,@Param("user") u:string,@Param("student") s:string,@Headers("cookie") c?:string) {return this.links.learnerIdentity(await this.actor(c),n,u,s);}
  @Get("foundation/organisations/:native/staff/:user/enrolment-context")
  async enrolmentContext(@Param("native") n:string,@Param("user") u:string,@Headers("cookie") c?:string) {return this.links.context(await this.actor(c),n,u,"enrolment");}
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
  @Get("foundation/organisations/:native/staff/:user/student-deliveries/:learner")
  async studentDelivery(@Param("native") n:string,@Param("user") u:string,@Param("learner") l:string,
    @Headers("cookie") c:string|undefined,@Res({passthrough:true}) response:Response) {
    response.setHeader("Cache-Control","no-store");
    return this.links.studentDeliverySnapshot(await this.actor(c),n,u,l);
  }
}
