import {
  Body,
  Controller,
  Get,
  Headers,
  Param,
  Patch,
  Post,
  Query,
  BadRequestException,
} from "@nestjs/common";
import { ConfigurationService } from "./configuration.service.js";
import { IdentityService } from "./identity.service.js";
import { session } from "./identity.controller.js";
@Controller()
export class ConfigurationController {
  constructor(
    private readonly config: ConfigurationService,
    private readonly identity: IdentityService,
  ) {}
  @Get("public/branding") async branding(
    @Query("slug") slug?: string,
    @Headers("host") host?: string,
  ) {
    const bound = host ? await this.config.host(host) : null;
    return bound
      ? this.config.branding(bound.slug)
      : slug
        ? this.config.branding(slug)
        : null;
  }
  @Get("organisations/:org/configuration") async read(
    @Param("org") org: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.config.read(await this.identity.account(session(cookie)), org);
  }
  @Patch("organisations/:org/configuration") async save(
    @Param("org") org: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.config.save(
      await this.identity.account(session(cookie)),
      org,
      b ?? {},
    );
  }
  @Post("organisations/:org/domain") async domain(
    @Param("org") org: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.config.domain(
      await this.identity.account(session(cookie)),
      org,
      b ?? {},
    );
  }
  @Post("organisations/:org/domain/verify") async verify(
    @Param("org") org: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.config.verify(
      await this.identity.account(session(cookie)),
      org,
    );
  }
  @Post("organisations/:org/domain/activate") async activate(
    @Param("org") org: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    if (b?.httpsConfigured !== true)
      throw new BadRequestException(
        "Confirm that DNS routing, HTTPS and the host-preserving proxy are configured.",
      );
    return this.config.verify(
      await this.identity.account(session(cookie)),
      org,
      true,
    );
  }
  @Post("organisations/:org/domain/remove") async remove(
    @Param("org") org: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.config.removeDomain(
      await this.identity.account(session(cookie)),
      org,
    );
  }
  @Get("organisations/:org/fields/:module") async definitions(
    @Param("org") org: string,
    @Param("module") module: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.config.definitions(
      await this.identity.account(session(cookie)),
      org,
      module,
    );
  }
  @Post("organisations/:org/fields/:module") async field(
    @Param("org") org: string,
    @Param("module") module: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.config.saveField(
      await this.identity.account(session(cookie)),
      org,
      module,
      b ?? {},
    );
  }
  @Patch("organisations/:org/fields/:module/:id") async editField(
    @Param("org") org: string,
    @Param("module") module: string,
    @Param("id") id: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.config.saveField(
      await this.identity.account(session(cookie)),
      org,
      module,
      b ?? {},
      id,
    );
  }
  @Get("organisations/:org/field-values/:module/:id") async values(
    @Param("org") org: string,
    @Param("module") module: string,
    @Param("id") id: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.config.values(
      await this.identity.account(session(cookie)),
      org,
      module,
      id,
    );
  }
  @Patch("organisations/:org/field-values/:module/:id") async saveValues(
    @Param("org") org: string,
    @Param("module") module: string,
    @Param("id") id: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.config.saveValues(
      await this.identity.account(session(cookie)),
      org,
      module,
      id,
      b ?? {},
    );
  }
}
