import { Body, Controller, Get, Headers, Param, Post, StreamableFile } from "@nestjs/common";
import { IdentityService } from "./identity.service.js";
import { session } from "./identity.controller.js";
import { ExamOmrService } from "./exam-omr.service.js";
@Controller("organisations/:org/exam-content/exams")
export class ExamOmrController {
 constructor(private readonly service: ExamOmrService, private readonly identity: IdentityService) {}
 private user(cookie?: string) { return this.identity.account(session(cookie)); }
 @Get("omr-scans/:scan") async content(@Param("org") org:string,@Param("scan") scan:string,@Headers("cookie") cookie?:string) { const file = await this.service.content(await this.user(cookie),org,scan); return new StreamableFile(file.content,{type:file.content_type, disposition:'inline; filename="omr-scan"'}); }
 @Get(":exam/omr-scans") async list(@Param("org") org:string,@Param("exam") exam:string,@Headers("cookie") cookie?:string) { return this.service.list(await this.user(cookie),org,exam); }
 @Post(":exam/omr-scans") async upload(@Param("org") org:string,@Param("exam") exam:string,@Body() body:Record<string,unknown>,@Headers("cookie") cookie?:string) { return this.service.upload(await this.user(cookie),org,exam,body||{}); }
 @Post("omr-scans/:scan/review") async review(@Param("org") org:string,@Param("scan") scan:string,@Body() body:Record<string,unknown>,@Headers("cookie") cookie?:string) { return this.service.review(await this.user(cookie),org,scan,body||{}); }
}
