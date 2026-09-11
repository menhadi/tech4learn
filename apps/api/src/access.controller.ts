import {
  Body,
  Controller,
  Get,
  Headers,
  Param,
  Patch,
  Post,
  Query,
} from "@nestjs/common";
import { AccessService } from "./access.service.js";
import { IdentityService } from "./identity.service.js";
import { RecordsService } from "./records.service.js";
import { session } from "./identity.controller.js";
import { ConfigurationService } from "./configuration.service.js";
import { permissionCatalogue } from "./access-model.js";
@Controller("organisations/:org")
export class AccessController {
  constructor(
    private readonly access: AccessService,
    private readonly identity: IdentityService,
    private readonly records: RecordsService,
    private readonly configuration: ConfigurationService,
  ) {}
  private user(cookie?: string) {
    return this.identity.account(session(cookie));
  }
  @Get("access") async current(
    @Param("org") org: string,
    @Headers("cookie") cookie?: string,
  ) {
    return {
      access: await this.access.resolve(await this.user(cookie), org),
      modules: (await this.configuration.settings(org)).enabled_modules,
      catalogue: permissionCatalogue.map(([key, label]) => ({ key, label })),
    };
  }
  @Get("roles") async roles(
    @Param("org") org: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.access.roles(await this.user(cookie), org);
  }
  @Post("roles") async createRole(
    @Param("org") org: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.access.saveRole(await this.user(cookie), org, body ?? {});
  }
  @Patch("roles/:id") async updateRole(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.access.saveRole(await this.user(cookie), org, body ?? {}, id);
  }
  @Get("members") async members(
    @Param("org") org: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.access.members(await this.user(cookie), org);
  }
  @Patch("members/:id") async member(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.access.saveMember(await this.user(cookie), org, id, body ?? {});
  }
  @Get("audit") async history(
    @Param("org") org: string,
    @Query("offset") offset: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.access.history(
      await this.user(cookie),
      org,
      Number(offset || 0),
    );
  }
  @Get("audit/export") async export(
    @Param("org") org: string,
    @Query("offset") offset: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.access.history(
      await this.user(cookie),
      org,
      Number(offset || 0),
      true,
    );
  }
  @Get("centres") async centres(
    @Param("org") org: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.records.centres(await this.user(cookie), org);
  }
  @Post("centres") async createCentre(
    @Param("org") org: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.records.saveCentre(await this.user(cookie), org, body ?? {});
  }
  @Patch("centres/:id") async centre(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.records.saveCentre(
      await this.user(cookie),
      org,
      body ?? {},
      id,
    );
  }
  @Post("centres/:id/archive") async archiveCentre(
    @Param("org") org: string,
    @Param("id") id: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.records.centreAction(
      await this.user(cookie),
      org,
      id,
      "archive",
    );
  }
  @Post("centres/:id/approve") async approveCentre(
    @Param("org") org: string,
    @Param("id") id: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.records.centreAction(
      await this.user(cookie),
      org,
      id,
      "approve",
    );
  }
  @Get("groups") async groups(
    @Param("org") org: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.records.groups(await this.user(cookie), org);
  }
  @Post("groups") async createGroup(
    @Param("org") org: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.records.saveGroup(await this.user(cookie), org, body ?? {});
  }
  @Patch("groups/:id") async group(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.records.saveGroup(await this.user(cookie), org, body ?? {}, id);
  }
  @Post("groups/:id/archive") async archiveGroup(
    @Param("org") org: string,
    @Param("id") id: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.records.archiveGroup(await this.user(cookie), org, id);
  }
}
