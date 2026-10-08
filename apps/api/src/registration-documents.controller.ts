import {Body,Controller,Get,Headers,Param,Post,Query} from "@nestjs/common";
import {RegistrationDocumentsService} from "./registration-documents.service.js";
import {IdentityService} from "./identity.service.js";
import {session} from "./identity.controller.js";

@Controller("organisations/:org/registration-documents")
export class RegistrationDocumentsController {
  constructor(private readonly service:RegistrationDocumentsService,private readonly identity:IdentityService) {}
  @Get("providers") async providers(@Param("org") org:string,@Query("group_id") group:string,@Headers("cookie") cookie?:string) {
    return this.service.providers(await this.identity.account(session(cookie)),org,group);
  }
  @Post("draft") async draft(@Param("org") org:string,@Body() body:Record<string,unknown>,@Headers("cookie") cookie?:string) {
    return this.service.extract(await this.identity.account(session(cookie)),org,body ?? {});
  }
}
