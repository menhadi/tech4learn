import {
  Body,
  Controller,
  Get,
  Headers,
  Param,
  Post,
  StreamableFile,
} from "@nestjs/common";
import { LearnerPhotosService } from "./learner-photos.service.js";
import { FaceMatchingService } from "./face-matching.service.js";
import { IdentityService } from "./identity.service.js";
import { session } from "./identity.controller.js";
@Controller("organisations/:org")
export class LearnerPhotosController {
  constructor(
    private readonly service: LearnerPhotosService,
    private readonly matching: FaceMatchingService,
    private readonly identity: IdentityService,
  ) {}
  private user(c?: string) {
    return this.identity.account(session(c));
  }
  @Get("learners/:id/photos") async list(
    @Param("org") o: string,
    @Param("id") id: string,
    @Headers("cookie") c?: string,
  ) {
    return this.service.list(await this.user(c), o, id);
  }
  @Get("learners/:id/photos/:photo") async photo(
    @Param("org") o: string,
    @Param("id") id: string,
    @Param("photo") p: string,
    @Headers("cookie") c?: string,
  ) {
    return new StreamableFile(
      await this.service.photo(await this.user(c), o, id, p),
      { type: "image/jpeg", disposition: 'inline; filename="student.jpg"' },
    );
  }
  @Post("learners/:id/photo-consent") async consent(
    @Param("org") o: string,
    @Param("id") id: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") c?: string,
  ) {
    return this.service.consent(await this.user(c), o, id, b || {});
  }
  @Post("learners/:id/photos") async upload(
    @Param("org") o: string,
    @Param("id") id: string,
    @Body() b: Record<string, unknown>,
    @Headers("cookie") c?: string,
  ) {
    return this.service.upload(await this.user(c), o, id, b || {});
  }
  @Post("learners/:id/photos/:photo/remove") async remove(
    @Param("org") o: string,
    @Param("id") id: string,
    @Param("photo") p: string,
    @Headers("cookie") c?: string,
  ) {
    return this.service.remove(await this.user(c), o, id, p);
  }
  @Post("learners/:id/photos/:photo/check") async check(
    @Param("org") o: string,
    @Param("id") id: string,
    @Param("photo") p: string,
    @Headers("cookie") c?: string,
  ) {
    return this.service.check(await this.user(c), o, id, p);
  }
  @Post("attendance/:id/face-match") async match(
    @Param("org") o: string,
    @Param("id") id: string,
    @Headers("cookie") c?: string,
  ) {
    return this.matching.match(await this.user(c), o, id);
  }
}
