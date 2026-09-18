import {
  Body,
  Controller,
  Get,
  Headers,
  Param,
  Post,
  Query,
  Res,
} from "@nestjs/common";
import type { Response } from "express";
import { IdentityService } from "./identity.service.js";
import { session } from "./identity.controller.js";
import { ExamContentService } from "./exam-content.service.js";
@Controller()
export class ExamContentController {
  @Get(
    "platform/exam-content/:org/central/exams/:id/translations/:language/media/:question/:revision/:asset",
  )
  async centralTranslationImage(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("language") language: string,
    @Param("question") question: string,
    @Param("revision") revision: string,
    @Param("asset") asset: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie: string | undefined,
    @Res() response: Response,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-central-translation-media:${account.id}:${org}`,
      180,
      60,
    );
    const image = await this.content.translationMedia(
      account,
      org,
      id,
      language,
      question,
      revision,
      asset,
      query,
      true,
    );
    response.setHeader("Content-Type", image.mime);
    response.setHeader("Cache-Control", "no-store");
    response.setHeader("X-Content-Type-Options", "nosniff");
    response.send(image.buffer);
  }
  @Get("platform/exam-content/:org/central/exams/:id/translations/:language")
  async centralReviewTranslation(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("language") language: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie: string | undefined,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-central-translation-review:${account.id}:${org}`,
      30,
      60,
    );
    return this.content.reviewTranslation(
      account,
      org,
      id,
      language,
      query,
      true,
    );
  }
  @Get(
    "organisations/:org/exam-content/exams/:id/translations/:language/media/:question/:revision/:asset",
  )
  async translationImage(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("language") language: string,
    @Param("question") question: string,
    @Param("revision") revision: string,
    @Param("asset") asset: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie: string | undefined,
    @Res() response: Response,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-translation-media:${account.id}:${org}`,
      180,
      60,
    );
    const image = await this.content.translationMedia(
      account,
      org,
      id,
      language,
      question,
      revision,
      asset,
      query,
    );
    response.setHeader("Content-Type", image.mime);
    response.setHeader("Cache-Control", "no-store");
    response.setHeader("X-Content-Type-Options", "nosniff");
    response.send(image.buffer);
  }
  @Get("organisations/:org/exam-content/exams/:id/translations/:language")
  async reviewTranslation(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("language") language: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie: string | undefined,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-translation-review:${account.id}:${org}`,
      30,
      60,
    );
    return this.content.reviewTranslation(account, org, id, language, query);
  }
  @Get("platform/exam-content/:org/central/exams/:id/documents/:type/status")
  async centralDocumentStatus(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("type") type: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie: string | undefined,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-central-document-status:${account.id}:${org}`,
      60,
      60,
    );
    return this.content.documentStatus(account, org, id, type, query, true);
  }
  @Get("platform/exam-content/:org/central/exams/:id/documents/:type")
  async centralExamDocument(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("type") type: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie: string | undefined,
    @Res() response: Response,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-central-documents:${account.id}:${org}`,
      20,
      60,
    );
    const document = await this.content.examDocument(
      account,
      org,
      id,
      type,
      query,
      true,
    );
    response.setHeader("Content-Type", "application/pdf");
    response.setHeader(
      "Content-Disposition",
      `attachment; filename="${document.filename}"`,
    );
    response.setHeader("Cache-Control", "no-store");
    response.setHeader("X-Content-Type-Options", "nosniff");
    response.send(document.buffer);
  }
  @Get("organisations/:org/exam-content/exams/:id/documents/:type/status")
  async documentStatus(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("type") type: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie: string | undefined,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-document-status:${account.id}:${org}`,
      60,
      60,
    );
    return this.content.documentStatus(account, org, id, type, query);
  }
  @Get("organisations/:org/exam-content/exams/:id/documents/:type")
  async examDocument(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("type") type: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie: string | undefined,
    @Res() response: Response,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-documents:${account.id}:${org}`, 20, 60);
    const document = await this.content.examDocument(
      account,
      org,
      id,
      type,
      query,
    );
    response.setHeader("Content-Type", "application/pdf");
    response.setHeader(
      "Content-Disposition",
      `attachment; filename="${document.filename}"`,
    );
    response.setHeader("Cache-Control", "no-store");
    response.setHeader("X-Content-Type-Options", "nosniff");
    response.send(document.buffer);
  }
  @Get("platform/exam-content/:org/central/packages/:id/media/:asset")
  async centralPackageImage(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("asset") asset: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie: string | undefined,
    @Res() response: Response,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-central-package-media:${account.id}`,
      180,
      60,
    );
    const image = await this.content.packageMedia(
      account,
      org,
      id,
      asset,
      query,
      true,
    );
    response.setHeader("Content-Type", image.mime);
    response.setHeader("Cache-Control", "no-store");
    response.setHeader("X-Content-Type-Options", "nosniff");
    response.send(image.buffer);
  }
  @Post("platform/exam-content/:org/central/packages/:id/image")
  async centralPackageImageWrite(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-central-package-image:${account.id}`,
      30,
      60,
    );
    return this.content.centralTaxonomy(
      account,
      org,
      "packages",
      id,
      query,
      body ?? {},
      true,
    );
  }
  @Get("organisations/:org/exam-content/packages/:id/media/:asset")
  async packageImage(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("asset") asset: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie: string | undefined,
    @Res() response: Response,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-package-media:${account.id}:${org}`,
      180,
      60,
    );
    const image = await this.content.packageMedia(
      account,
      org,
      id,
      asset,
      query,
    );
    response.setHeader("Content-Type", image.mime);
    response.setHeader("Cache-Control", "no-store");
    response.setHeader("X-Content-Type-Options", "nosniff");
    response.send(image.buffer);
  }
  @Get(
    "organisations/:org/exam-content/passages/:id/languages/:language/media/:asset",
  )
  async passageImage(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("language") language: string,
    @Param("asset") asset: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie: string | undefined,
    @Res() response: Response,
  ) {
    return this.sendPassageImage(
      org,
      id,
      language,
      asset,
      query,
      cookie,
      response,
      false,
    );
  }
  @Get(
    "platform/exam-content/:org/central/passages/:id/languages/:language/media/:asset",
  )
  async centralPassageImage(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("language") language: string,
    @Param("asset") asset: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie: string | undefined,
    @Res() response: Response,
  ) {
    return this.sendPassageImage(
      org,
      id,
      language,
      asset,
      query,
      cookie,
      response,
      true,
    );
  }
  private async sendPassageImage(
    org: string,
    id: string,
    language: string,
    asset: string,
    query: Record<string, unknown>,
    cookie: string | undefined,
    response: Response,
    central: boolean,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-${central ? "central-" : ""}passage-media:${account.id}:${org}`,
      180,
      60,
    );
    const image = await this.content.passageMedia(
      account,
      org,
      id,
      language,
      asset,
      query,
      central,
    );
    response.setHeader("Content-Type", image.mime);
    response.setHeader("Cache-Control", "no-store");
    response.setHeader("X-Content-Type-Options", "nosniff");
    response.send(image.buffer);
  }
  @Get("organisations/:org/exam-content/questions/:id/media/:asset")
  async questionImage(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("asset") asset: string,
    @Headers("cookie") cookie: string | undefined,
    @Res() response: Response,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-question-media:${account.id}:${org}`,
      180,
      60,
    );
    const image = await this.content.questionMedia(account, org, id, asset);
    response.setHeader("Content-Type", image.mime);
    response.setHeader("Cache-Control", "no-store");
    response.setHeader("X-Content-Type-Options", "nosniff");
    response.send(image.buffer);
  }
  @Get(
    "organisations/:org/exam-results/:learner/attempts/:attempt/media/:stat/:asset",
  )
  async resultImage(
    @Param("org") org: string,
    @Param("learner") learner: string,
    @Param("attempt") attempt: string,
    @Param("stat") stat: string,
    @Param("asset") asset: string,
    @Headers("cookie") cookie: string | undefined,
    @Res() response: Response,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-result-media:${account.id}:${org}`,
      180,
      60,
    );
    const image = await this.content.resultMedia(
      account,
      org,
      learner,
      attempt,
      stat,
      asset,
    );
    response.setHeader("Content-Type", image.mime);
    response.setHeader("Cache-Control", "no-store");
    response.setHeader("X-Content-Type-Options", "nosniff");
    response.send(image.buffer);
  }
  @Get("organisations/:org/exam-results/:learner/attempts")
  async resultAttempts(
    @Param("org") org: string,
    @Param("learner") learner: string,
    @Query("after") after = "0",
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.resultReview(
      await this.identity.account(session(cookie)),
      org,
      learner,
      undefined,
      after,
    );
  }
  @Get("organisations/:org/exam-results/:learner/attempts/:attempt")
  async resultReview(
    @Param("org") org: string,
    @Param("learner") learner: string,
    @Param("attempt") attempt: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.resultReview(
      await this.identity.account(session(cookie)),
      org,
      learner,
      attempt,
    );
  }
  @Post("organisations/:org/exam-results/:learner/attempts/:attempt")
  async markResult(
    @Param("org") org: string,
    @Param("learner") learner: string,
    @Param("attempt") attempt: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.resultReview(
      await this.identity.account(session(cookie)),
      org,
      learner,
      attempt,
      "0",
      body,
    );
  }
  @Get("organisations/:org/exam-proctor/:learner/attempts")
  async proctorAttempts(
    @Param("org") org: string,
    @Param("learner") learner: string,
    @Query("after") after = "0",
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.proctorReview(
      await this.identity.account(session(cookie)),
      org,
      learner,
      undefined,
      undefined,
      after,
    );
  }
  @Get("organisations/:org/exam-proctor/:learner/attempts/:attempt/captures")
  async proctorCaptures(
    @Param("org") org: string,
    @Param("learner") learner: string,
    @Param("attempt") attempt: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.proctorReview(
      await this.identity.account(session(cookie)),
      org,
      learner,
      attempt,
    );
  }
  @Get(
    "organisations/:org/exam-proctor/:learner/attempts/:attempt/captures/:capture",
  )
  async proctorImage(
    @Param("org") org: string,
    @Param("learner") learner: string,
    @Param("attempt") attempt: string,
    @Param("capture") capture: string,
    @Headers("cookie") cookie: string | undefined,
    @Res() response: Response,
  ) {
    const image = await this.content.proctorReview(
      await this.identity.account(session(cookie)),
      org,
      learner,
      attempt,
      capture,
    );
    response.setHeader("Content-Type", "image/jpeg");
    response.setHeader("Cache-Control", "no-store");
    response.setHeader("X-Content-Type-Options", "nosniff");
    response.send("buffer" in image ? image.buffer : undefined);
  }
  @Get("organisations/:org/exam-content/exams/:id/questions")
  async examQuestions(
    @Param("org") org: string,
    @Param("id") id: string,
    @Query("after") after = "0",
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.examQuestions(
      await this.identity.account(session(cookie)),
      org,
      id,
      after,
    );
  }
  @Post("organisations/:org/exam-content/exams/:id/actions/:action")
  async examAction(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("action") action: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.saveQuestion(
      await this.identity.account(session(cookie)),
      org,
      id,
      body ?? {},
      "exams",
      action,
    );
  }

  @Get("organisations/:org/exam-content/taxonomy/:kind/:id")
  async taxonomy(
    @Param("org") org: string,
    @Param("kind") kind: string,
    @Param("id") id: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.taxonomy(
      await this.identity.account(session(cookie)),
      org,
      kind,
      id,
    );
  }
  @Post("organisations/:org/exam-content/taxonomy/:kind/:id")
  async saveTaxonomy(
    @Param("org") org: string,
    @Param("kind") kind: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.saveQuestion(
      await this.identity.account(session(cookie)),
      org,
      id,
      body ?? {},
      kind,
    );
  }
  @Post("organisations/:org/exam-content/taxonomy/languages/:id/disable")
  async disableLanguage(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.saveQuestion(
      await this.identity.account(session(cookie)),
      org,
      id,
      body ?? {},
      "languages",
      "disable-language",
    );
  }

  @Post("organisations/:org/exam-content/taxonomy/:kind/:id/delete")
  async deleteCategory(
    @Param("org") org: string,
    @Param("kind") kind: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-category-delete:${account.id}`, 30, 60);
    return this.content.deleteCategory(
      account,
      org,
      kind,
      id,
      body ?? {},
      query,
    );
  }

  @Post("platform/exam-content/:org/central/taxonomy/:kind/:id/delete")
  async centralCategoryDelete(
    @Param("org") org: string,
    @Param("kind") kind: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-category-delete:${account.id}`, 30, 60);
    return this.content.deleteCategory(
      account,
      org,
      kind,
      id,
      body ?? {},
      query,
      true,
    );
  }

  @Get("organisations/:org/exam-content/choices/:kind")
  async questionChoices(
    @Param("org") org: string,
    @Param("kind") kind: string,
    @Query("search") search = "",
    @Query("after") after = "0",
    @Query("parent_id") parent = "",
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.questionChoices(
      await this.identity.account(session(cookie)),
      org,
      kind,
      search,
      after,
      parent,
    );
  }
  @Post("organisations/:org/exam-content/packages/:id/image")
  async uploadPackageImage(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-package-image-write:${account.id}:${org}`,
      30,
      60,
    );
    return this.content.saveQuestion(
      account,
      org,
      id,
      body ?? {},
      "packages",
      "set-image",
    );
  }
  @Post("organisations/:org/exam-content/questions/:id/image")
  async uploadQuestionImage(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-image-write:${account.id}:${org}`, 30, 60);
    return this.content.saveQuestion(
      account,
      org,
      id,
      body ?? {},
      "questions",
      "set-image",
    );
  }
  @Post("organisations/:org/exam-content/questions")
  async createQuestion(
    @Param("org") org: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.saveQuestion(
      await this.identity.account(session(cookie)),
      org,
      "new",
      body ?? {},
    );
  }

  @Get("organisations/:org/exam-content/questions/:id")
  async question(
    @Param("org") org: string,
    @Param("id") id: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.question(
      await this.identity.account(session(cookie)),
      org,
      id,
    );
  }
  @Post("organisations/:org/exam-content/questions/:id")
  async updateQuestion(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.saveQuestion(
      await this.identity.account(session(cookie)),
      org,
      id,
      body ?? {},
    );
  }
  @Get("platform/exam-content/:org/transfers")
  async history(@Param("org") org: string, @Headers("cookie") cookie?: string) {
    return this.content.history(
      await this.identity.account(session(cookie)),
      org,
    );
  }
  constructor(
    private readonly identity: IdentityService,
    private readonly content: ExamContentService,
  ) {}
  @Get("platform/exam-content/:org/modules") async modules(
    @Param("org") org: string,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.modules(
      await this.identity.account(session(cookie)),
      org,
    );
  }
  @Post("platform/exam-content/:org/modules") async save(
    @Param("org") org: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.setModules(
      await this.identity.account(session(cookie)),
      org,
      body ?? {},
    );
  }
  @Post("platform/exam-content/:org/central/questions")
  async centralCreate(
    @Param("org") org: string,
    @Body() body: Record<string, unknown>,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.centralSave(org, "new", body, query, cookie);
  }
  @Post("platform/exam-content/:org/central/questions/:id/image")
  async centralImageWrite(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-central-image:${account.id}`, 30, 60);
    return this.content.saveCentralQuestion(
      account,
      org,
      id,
      body ?? {},
      query,
      true,
    );
  }
  @Post("platform/exam-content/:org/central/questions/:id")
  async centralSave(
    @Param("org") org: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-central-write:${account.id}`, 30, 60);
    return this.content.saveCentralQuestion(
      account,
      org,
      id,
      body ?? {},
      query,
    );
  }
  @Get("platform/exam-content/:org/central/taxonomy/:kind/:id")
  async centralTaxonomy(
    @Param("org") org: string,
    @Param("kind") kind: string,
    @Param("id") id: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-central-classification-read:${account.id}`,
      120,
      60,
    );
    return this.content.centralTaxonomy(account, org, kind, id, query);
  }
  @Post("platform/exam-content/:org/central/taxonomy/:kind/:id")
  async centralTaxonomySave(
    @Param("org") org: string,
    @Param("kind") kind: string,
    @Param("id") id: string,
    @Body() body: Record<string, unknown>,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(
      `exam-central-classification-write:${account.id}`,
      30,
      60,
    );
    return this.content.centralTaxonomy(
      account,
      org,
      kind,
      id,
      query,
      body ?? {},
    );
  }
  @Post("platform/exam-content/:org/central/exams/:id/actions/:action")
  async centralExamAction(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("action") action: string,
    @Body() body: Record<string, unknown>,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-central-paper-write:${account.id}`, 30, 60);
    return this.content.centralTaxonomy(
      account,
      org,
      "exams",
      id,
      query,
      body ?? {},
      false,
      action,
    );
  }
  @Get("platform/exam-content/:org/central/exams/:id/questions")
  async centralExamQuestions(
    @Param("org") org: string,
    @Param("id") id: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-central-paper-read:${account.id}`, 120, 60);
    return this.content.centralExamQuestions(account, org, id, query);
  }
  @Get("platform/exam-content/:org/central/choices/:kind")
  async centralChoices(
    @Param("org") org: string,
    @Param("kind") kind: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-central-choices:${account.id}`, 120, 60);
    return this.content.centralChoices(account, org, kind, query);
  }
  @Get("platform/exam-content/:org/central/questions/:id")
  async centralDetail(
    @Param("org") org: string,
    @Param("id") id: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-central-preview:${account.id}`, 60, 60);
    return this.content.centralDetail(account, org, id, query);
  }
  @Get(
    "platform/exam-content/:org/central/questions/:id/:revision/media/:asset",
  )
  async centralMedia(
    @Param("org") org: string,
    @Param("id") id: string,
    @Param("revision") revision: string,
    @Param("asset") asset: string,
    @Query() query: Record<string, unknown>,
    @Headers("cookie") cookie: string | undefined,
    @Res() response: Response,
  ) {
    const account = await this.identity.account(session(cookie));
    await this.identity.limit(`exam-central-media:${account.id}`, 180, 60);
    const image = await this.content.centralMedia(
      account,
      org,
      id,
      revision,
      asset,
      query,
    );
    response.setHeader("Content-Type", image.mime);
    response.setHeader("Cache-Control", "no-store");
    response.setHeader("X-Content-Type-Options", "nosniff");
    response.send(image.buffer);
  }
  @Get("platform/exam-content/:org/questions") async central(
    @Param("org") org: string,
    @Query("source") source = "central",
    @Query("search") search = "",
    @Query("after") after = "0",
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.questions(
      await this.identity.account(session(cookie)),
      org,
      source,
      search,
      after,
      true,
    );
  }
  @Get("organisations/:org/exam-content/questions") async own(
    @Param("org") org: string,
    @Query("search") search = "",
    @Query("after") after = "0",
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.questions(
      await this.identity.account(session(cookie)),
      org,
      "organisation",
      search,
      after,
    );
  }
  @Post("platform/exam-content/:org/transfer") async transfer(
    @Param("org") org: string,
    @Body() body: Record<string, unknown>,
    @Headers("cookie") cookie?: string,
  ) {
    return this.content.transfer(
      await this.identity.account(session(cookie)),
      org,
      body ?? {},
    );
  }
}
