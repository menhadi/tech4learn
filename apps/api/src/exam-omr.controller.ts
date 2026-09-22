import { Body, Controller, Get, Headers, Param, Post } from "@nestjs/common";
import { IdentityService } from "./identity.service.js";
import { session } from "./identity.controller.js";
import { ExamOmrService } from "./exam-omr.service.js";
@Controller("organisations/:org/exam-content/exams")
export class ExamOmrController {
 constructor(private readonly service: ExamOmrService, private readonly identity: IdentityService) {}
 private user(cookie?: string) { return this.identity.account(session(cookie)); }
 @Get(":exam/omr-scans") async list(@Param("org") org:string,@Param("exam") exam:string,@Headers("cookie") cookie?:string) { return this.service.list(await this.user(cookie),org,exam); }
 @Post(":exam/omr-scans") async upload(@Param("org") org:string,@Param("exam") exam:string,@Body() body:Record<string,unknown>,@Headers("cookie") cookie?:string) { return this.service.upload(await this.user(cookie),org,exam,body||{}); }
 @Post("omr-scans/:scan/review") async review(@Param("org") org:string,@Param("scan") scan:string,@Body() body:Record<string,unknown>,@Headers("cookie") cookie?:string) { return this.service.review(await this.user(cookie),org,scan,body||{}); }
}
