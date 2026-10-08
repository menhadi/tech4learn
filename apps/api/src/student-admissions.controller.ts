import {Body,Controller,Get,Header,Headers,Param,Post} from "@nestjs/common";
import {IdentityService} from "./identity.service.js";
import {session} from "./identity.controller.js";
import {StudentAdmissionsService} from "./student-admissions.service.js";
@Controller("foundation/organisations/:native/staff/:user/admissions")
export class StudentAdmissionsController {
 constructor(private readonly identity:IdentityService,private readonly admissions:StudentAdmissionsService){}
 @Post("review")
 @Header("Cache-Control","no-store")
 async review(@Param("native") native:string,@Param("user") user:string,@Body() body:Record<string,unknown>,@Headers("cookie") cookie?:string){
  return this.admissions.review(await this.identity.account(session(cookie)),native,user,body??{});
 }
 @Get(":admission/binding-snapshot")
 @Header("Cache-Control","no-store")
 async snapshot(@Param("native") native:string,@Param("user") user:string,@Param("admission") admission:string,@Headers("cookie") cookie?:string){
  return this.admissions.bindingSnapshot(await this.identity.account(session(cookie)),native,user,admission);
 }
}
