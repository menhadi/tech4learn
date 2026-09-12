import { Body, Controller, Get, Headers, Post } from "@nestjs/common";
import { IdentityService } from "./identity.service.js";
import { session } from "./identity.controller.js";
import { FaceControlService } from "./face-control.service.js";
@Controller("platform/face-engine")
export class FaceControlController {
  constructor(
    private readonly identity: IdentityService,
    private readonly control: FaceControlService,
  ) {}
  @Get() async status(@Headers("cookie") cookie?: string) {
    return this.control.status(await this.identity.account(session(cookie)));
  }
  @Post() async change(
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.control.change(
      await this.identity.account(session(cookie)),
      body ?? {},
    );
  }
}
