import {
  Injectable,
  ForbiddenException,
  BadRequestException,
  NotFoundException,
  ConflictException,
  ServiceUnavailableException,
} from "@nestjs/common";
import { uuid } from "./security.js";
import { Database } from "./database.js";
import { AccessService } from "./access.service.js";
import { ExamEliteService } from "./examelite.service.js";
import { ExamWorkspaceService } from "./exam-workspace.service.js";
import type { Account } from "./identity.service.js";
import { translationReview } from "./exam-translation-review.js";

@Injectable()
export class ExamContentService {
  private async centralAccess(
    user: Account,
    org: string,
    id: string,
    allowCreate = false,
  ) {
    this.admin(user);
    await this.organisation(org);
    if (!(allowCreate && id === "new") && !/^[1-9][0-9]{0,14}$/.test(id))
      throw new BadRequestException("Invalid central question.");
    const current = await this.db.query<{ is_superadmin: boolean }>(
      "SELECT is_superadmin FROM users WHERE id=$1",
      [user.id],
    );
    if (current.rows[0]?.is_superadmin !== true) throw new ForbiddenException();
  }
  async centralChoices(
    user: Account,
    org: string,
    kind: string,
    query: Record<string, unknown>,
  ) {
    await this.centralAccess(user, org, "new", true);
    const search = query.search ?? "";
    const after = query.after ?? "0";
    if (
      ![
        "groups",
        "subjects",
        "sections",
        "topics",
        "subtopics",
        "languages",
        "types",
        "difficulties",
      ].includes(kind) ||
      Object.keys(query).some((key) => !["search", "after"].includes(key)) ||
      typeof search !== "string" ||
      search.length > 120 ||
      typeof after !== "string" ||
      !/^[0-9]{1,15}$/.test(after)
    )
      throw new BadRequestException("Invalid central classification lookup.");
    const response = await this.remote.request(
      await this.config(),
      org,
      `central/choices/${kind}?search=${encodeURIComponent(search)}&after=${after}`,
    );
    await this.centralAccess(user, org, "new", true);
    const invalid = () =>
      new ServiceUnavailableException(
        "Unable to verify central classification choices.",
      );
    const items = response.items;
    if (!Array.isArray(items) || items.length > 100) throw invalid();
    let previous = Number(after);
    const checked = items.map((item: any) => {
      if (
        !item ||
        !Number.isSafeInteger(item.id) ||
        item.id <= previous ||
        item.id >= 1e15 ||
        typeof item.label !== "string" ||
        item.label.length > 1000
      )
        throw invalid();
      previous = item.id;
      const result: Record<string, unknown> = {
        id: item.id,
        label: item.label,
      };
      if (kind === "types" && item.type !== undefined) {
        if (typeof item.type !== "string" || item.type.length > 100)
          throw invalid();
        result.type = item.type;
      }
      for (const key of kind === "subtopics"
        ? ["subject_id", "group_id", "topic_id"]
        : kind === "topics"
          ? ["subject_id", "group_id"]
          : []) {
        if (item[key] !== undefined) {
          const value = item[key];
          if (
            !(
              typeof value === "number" ||
              (typeof value === "string" && /^[1-9][0-9]{0,14}$/.test(value))
            ) ||
            !Number.isSafeInteger(Number(value)) ||
            Number(value) <= 0 ||
            Number(value) >= 1e15
          )
            throw invalid();
          result[key] = Number(value);
        }
      }
      return result;
    });
    if (
      response.next !== null &&
      (items.length !== 100 || response.next !== previous)
    )
      throw invalid();
    await this.access.audit(
      this.db,
      user,
      org,
      "exams.central.choices.viewed",
      { kind },
    );
    return { items: checked, next: response.next };
  }
  async saveCentralQuestion(
    user: Account,
    org: string,
    id: string,
    body: Record<string, unknown>,
    query: Record<string, unknown>,
  ) {
    await this.centralAccess(user, org, id, true);
    const fields = body.fields;
    const allowed = [
      "qtype_id",
      "subject_id",
      "question_section_id",
      "topic_id",
      "stopic_id",
      "diff_id",
      "passage_id",
      "language_id",
      "question",
      "option1",
      "option2",
      "option3",
      "option4",
      "option5",
      "option6",
      "marks",
      "negative_marks",
      "scoring_policy",
      "hint",
      "explanation",
      "answer",
      "true_false",
      "fill_blank",
      "fill_blank_answers",
      "nat_mode",
      "nat_value",
      "nat_min",
      "nat_max",
      "nat_tolerance",
      "status",
      "correct_answers",
      "si_answer1",
      "group_ids",
      "tag_ids",
    ];
    if (
      Object.keys(query).length ||
      Object.keys(body).some(
        (key) => !["fields", "revision", "request_id"].includes(key),
      ) ||
      !fields ||
      typeof fields !== "object" ||
      Array.isArray(fields) ||
      !Object.keys(fields).length ||
      Object.keys(fields).some((key) => !allowed.includes(key)) ||
      Buffer.byteLength(JSON.stringify(fields), "utf8") > 250000 ||
      typeof body.revision !== "string" ||
      (id === "new"
        ? body.revision !== "new"
        : !/^[a-f0-9]{64}$/.test(body.revision)) ||
      typeof body.request_id !== "string" ||
      !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/.test(
        body.request_id,
      )
    )
      throw new BadRequestException("Invalid central question changes.");
    const response = await this.remote.request(
      await this.config(),
      org,
      `central/questions${id === "new" ? "" : "/" + id}`,
      {
        actor_id: user.id,
        fields,
        revision: body.revision,
        request_id: body.request_id,
      },
      4000000,
      30000,
    );
    await this.centralAccess(user, org, id, true);
    if (response.saved === false && response.conflict === true)
      throw new ConflictException(
        "The central question or request changed. Reload before saving again.",
      );
    if (
      response.saved === false &&
      response.errors &&
      typeof response.errors === "object" &&
      !Array.isArray(response.errors)
    ) {
      const messages = Object.values(response.errors)
        .flat()
        .filter((value): value is string => typeof value === "string")
        .slice(0, 12)
        .map((value) => value.slice(0, 300));
      throw new BadRequestException(
        messages.join(" ") || "Check the central question fields.",
      );
    }
    const record = response.question;
    if (
      response.saved !== true ||
      !record ||
      !Number.isSafeInteger(record.id) ||
      record.id <= 0 ||
      record.id >= 1e15 ||
      (id !== "new" && record.id !== Number(id)) ||
      typeof record.revision !== "string" ||
      !/^[a-f0-9]{64}$/.test(record.revision) ||
      !record.fields ||
      typeof record.fields !== "object" ||
      Array.isArray(record.fields) ||
      !record.preview_fields ||
      typeof record.preview_fields !== "object" ||
      Array.isArray(record.preview_fields)
    )
      throw new ServiceUnavailableException(
        "Unable to verify the saved central question. Retry the same request or reload.",
      );
    await this.access.audit(
      this.db,
      user,
      org,
      `exams.central.question.${id === "new" ? "created" : "updated"}`,
      { questionId: record.id, requestId: body.request_id },
    );
    return record;
  }
  async centralDetail(
    user: Account,
    org: string,
    id: string,
    query: Record<string, unknown>,
  ) {
    await this.centralAccess(user, org, id, true);
    if (Object.keys(query).length)
      throw new BadRequestException("Invalid central preview.");
    if (id === "new") return this.newQuestion();
    const data = await this.remote.request(
      await this.config(),
      org,
      `central/questions/${id}`,
      undefined,
      4000000,
      30000,
    );
    await this.centralAccess(user, org, id);
    if (
      data.id !== Number(id) ||
      typeof data.revision !== "string" ||
      !/^[a-f0-9]{64}$/.test(data.revision) ||
      !data.fields ||
      typeof data.fields !== "object" ||
      Array.isArray(data.fields) ||
      !data.preview_fields ||
      typeof data.preview_fields !== "object" ||
      Array.isArray(data.preview_fields)
    )
      throw new ServiceUnavailableException(
        "The central question could not be loaded.",
      );
    await this.access.audit(
      this.db,
      user,
      org,
      "exams.central.question.viewed",
      { questionId: Number(id) },
    );
    return data;
  }
  async centralMedia(
    user: Account,
    org: string,
    id: string,
    revision: string,
    asset: string,
    query: Record<string, unknown>,
  ) {
    await this.centralAccess(user, org, id);
    if (
      Object.keys(query).length ||
      !/^[a-f0-9]{64}$/.test(revision) ||
      !/^[a-f0-9]{64}$/.test(asset)
    )
      throw new BadRequestException("Invalid central image.");
    const data = await this.remote.request(
      await this.config(),
      org,
      `central/questions/${id}/media/${asset}?revision=${revision}`,
      undefined,
      14000000,
      30000,
    );
    await this.centralAccess(user, org, id);
    const invalid = () =>
      new ServiceUnavailableException("The central image could not be loaded.");
    if (
      data.question_id !== Number(id) ||
      data.revision !== revision ||
      data.asset !== asset ||
      ![
        "image/png",
        "image/jpeg",
        "image/gif",
        "image/webp",
        "image/avif",
      ].includes(data.mime) ||
      typeof data.base64 !== "string" ||
      data.base64.length > 13981016
    )
      throw invalid();
    const buffer = Buffer.from(data.base64, "base64");
    if (
      !buffer.length ||
      buffer.length > 10485760 ||
      buffer.toString("base64") !== data.base64
    )
      throw invalid();
    await this.access.audit(
      this.db,
      user,
      org,
      "exams.central.question.image.viewed",
      { questionId: Number(id), asset },
    );
    return { buffer, mime: data.mime };
  }
  async translationMedia(
    user: Account,
    org: string,
    id: string,
    language: string,
    question: string,
    revision: string,
    asset: string,
    query: Record<string, unknown>,
  ) {
    await this.questionAccess(user, org, id, "exams");
    if (
      id === "new" ||
      !/^[1-9][0-9]{0,14}$/.test(language) ||
      !/^(0|[1-9][0-9]{0,14})$/.test(question) ||
      !/^[a-f0-9]{64}$/.test(revision) ||
      !/^[a-f0-9]{64}$/.test(asset) ||
      Object.keys(query).length
    )
      throw new BadRequestException("Invalid translation image selection.");
    const params = new URLSearchParams({ actor_id: user.id, revision });
    const response = await this.remote.request(
      await this.config(),
      org,
      `translations/${org}/exams/${id}/languages/${language}/media/${question}/${asset}?${params}`,
      undefined,
      14000000,
      30000,
    );
    await this.questionAccess(user, org, id, "exams");
    const data = response.data;
    const invalid = () =>
      new ServiceUnavailableException(
        "The translation image could not be loaded.",
      );
    if (
      !data ||
      data.exam_id !== Number(id) ||
      data.language_id !== Number(language) ||
      data.question_id !== Number(question) ||
      data.revision !== revision ||
      data.asset !== asset ||
      ![
        "image/png",
        "image/jpeg",
        "image/gif",
        "image/webp",
        "image/avif",
      ].includes(data.mime) ||
      typeof data.base64 !== "string" ||
      data.base64.length > 13981016
    )
      throw invalid();
    const buffer = Buffer.from(data.base64, "base64");
    if (
      !buffer.length ||
      buffer.length > 10485760 ||
      buffer.toString("base64") !== data.base64
    )
      throw invalid();
    await this.access.audit(
      this.db,
      user,
      org,
      "exams.translation.image.viewed",
      {
        examId: Number(id),
        languageId: Number(language),
        questionId: Number(question),
        asset,
      },
    );
    return { buffer, mime: data.mime as string };
  }
  async reviewTranslation(
    user: Account,
    org: string,
    id: string,
    language: string,
    query: Record<string, unknown>,
  ) {
    await this.questionAccess(user, org, id, "exams");
    const after = query.after ?? "0";
    const revision = query.revision;
    if (
      id === "new" ||
      !/^[1-9][0-9]{0,14}$/.test(language) ||
      Object.keys(query).some((key) => !["after", "revision"].includes(key)) ||
      typeof after !== "string" ||
      !/^(0|[1-9][0-9]{0,14})$/.test(after) ||
      (revision !== undefined &&
        (typeof revision !== "string" || !/^[a-f0-9]{64}$/.test(revision))) ||
      (after !== "0" && revision === undefined)
    )
      throw new BadRequestException("Invalid translation review selection.");
    const params = new URLSearchParams({ actor_id: user.id, after });
    if (typeof revision === "string") params.set("revision", revision);
    const result = await this.remote.request(
      await this.config(),
      org,
      `translations/${org}/exams/${id}/languages/${language}?${params}`,
      undefined,
      2100000,
      30000,
    );
    await this.questionAccess(user, org, id, "exams");
    return translationReview(
      result.data,
      Number(id),
      Number(language),
      Number(after),
      revision as string | undefined,
    );
  }
  async documentStatus(
    user: Account,
    org: string,
    id: string,
    type: string,
    query: Record<string, unknown>,
  ) {
    await this.questionAccess(user, org, id, "exams");
    if (
      id === "new" ||
      !["questions", "solutions"].includes(type) ||
      Object.keys(query).some(
        (key) => !["package_id", "language_id"].includes(key),
      )
    )
      throw new BadRequestException("Invalid document selection.");
    const params = new URLSearchParams({ actor_id: user.id });
    for (const key of ["package_id", "language_id"]) {
      if (query[key] === undefined) continue;
      if (
        typeof query[key] !== "string" ||
        !/^[1-9][0-9]{0,14}$/.test(query[key])
      )
        throw new BadRequestException("Invalid document selection.");
      params.set(key, query[key]);
    }
    const response = await this.remote.request(
      await this.config(),
      org,
      `documents/${org}/exams/${id}/${type}/status?${params}`,
      undefined,
      10000,
      15000,
    );
    await this.questionAccess(user, org, id, "exams");
    const data = response.data;
    if (
      !data ||
      data.exam_id !== Number(id) ||
      data.document_type !== type ||
      data.package_id !==
        (query.package_id === undefined ? null : Number(query.package_id)) ||
      data.language_id !==
        (query.language_id === undefined ? null : Number(query.language_id)) ||
      ![
        "not_built",
        "queued",
        "processing",
        "ready",
        "stale",
        "failed",
      ].includes(data.status) ||
      typeof data.approved_available !== "boolean" ||
      (data.status === "not_built"
        ? data.build_id !== null || data.approved_available
        : !Number.isSafeInteger(data.build_id) || data.build_id <= 0)
    )
      throw new ServiceUnavailableException(
        "The PDF status could not be loaded.",
      );
    return {
      document_type: type,
      status: data.status as string,
      approved_available: data.approved_available as boolean,
    };
  }
  async examDocument(
    user: Account,
    org: string,
    id: string,
    type: string,
    query: Record<string, unknown>,
  ) {
    await this.questionAccess(user, org, id, "exams");
    if (
      id === "new" ||
      !["questions", "solutions"].includes(type) ||
      Object.keys(query).some((k) => !["package_id", "language_id"].includes(k))
    )
      throw new BadRequestException("Invalid document selection.");
    const params = new URLSearchParams({ actor_id: user.id });
    for (const key of ["package_id", "language_id"]) {
      const value = query[key];
      if (value === undefined) continue;
      if (typeof value !== "string" || !/^[1-9][0-9]{0,14}$/.test(value))
        throw new BadRequestException("Invalid document selection.");
      params.set(key, value);
    }
    const response = await this.remote.request(
      await this.config(),
      org,
      `documents/${org}/exams/${id}/${type}?${params}`,
      undefined,
      14000000,
      30000,
    );
    await this.questionAccess(user, org, id, "exams");
    const data = response.data;
    const invalid = () =>
      new ServiceUnavailableException("The approved PDF could not be loaded.");
    if (
      !data ||
      data.exam_id !== Number(id) ||
      data.document_type !== type ||
      data.package_id !==
        (query.package_id === undefined ? null : Number(query.package_id)) ||
      data.language_id !==
        (query.language_id === undefined ? null : Number(query.language_id)) ||
      !Number.isSafeInteger(data.build_id) ||
      data.build_id <= 0 ||
      data.mime !== "application/pdf" ||
      typeof data.base64 !== "string" ||
      data.base64.length > 13981016
    )
      throw invalid();
    const buffer = Buffer.from(data.base64, "base64");
    if (
      buffer.length > 10485760 ||
      buffer.toString("base64") !== data.base64 ||
      buffer.subarray(0, 5).toString("ascii") !== "%PDF-"
    )
      throw invalid();
    await this.access.audit(this.db, user, org, "exams.document.downloaded", {
      examId: Number(id),
      buildId: data.build_id,
      documentType: type,
    });
    return { buffer, filename: `exam-${id}-${type}.pdf` };
  }
  async questionMedia(user: Account, org: string, id: string, asset: string) {
    return this.authoringMedia(user, org, id, asset, "questions");
  }
  async packageMedia(
    user: Account,
    org: string,
    id: string,
    asset: string,
    query: Record<string, unknown>,
  ) {
    if (Object.keys(query).length)
      throw new BadRequestException(
        "Package images do not accept query overrides.",
      );
    return this.authoringMedia(user, org, id, asset, "packages");
  }
  private async authoringMedia(
    user: Account,
    org: string,
    id: string,
    asset: string,
    kind: "questions" | "packages",
  ) {
    await this.questionAccess(
      user,
      org,
      id,
      kind === "packages" ? "subjects" : "questions",
    );
    if (id === "new" || !/^[a-f0-9]{64}$/.test(asset))
      throw new BadRequestException("Invalid authoring image.");
    const response = await this.remote.request(
      await this.config(),
      org,
      `authoring/${org}/${kind}/${id}/media/${asset}`,
      undefined,
      14000000,
      30000,
    );
    await this.questionAccess(
      user,
      org,
      id,
      kind === "packages" ? "subjects" : "questions",
    );
    const data = response.data;
    const invalid = () =>
      new ServiceUnavailableException("This image could not be loaded.");
    if (
      !data ||
      data[kind === "packages" ? "package_id" : "question_id"] !== Number(id) ||
      data.asset !== asset ||
      ![
        "image/png",
        "image/jpeg",
        "image/gif",
        "image/webp",
        "image/avif",
      ].includes(data.mime) ||
      typeof data.base64 !== "string" ||
      data.base64.length > 13981016
    )
      throw invalid();
    const buffer = Buffer.from(data.base64, "base64");
    if (
      !buffer.length ||
      buffer.length > 10485760 ||
      buffer.toString("base64") !== data.base64
    )
      throw invalid();
    await this.access.audit(
      this.db,
      user,
      org,
      `exams.${kind === "packages" ? "package" : "question"}.image.viewed`,
      {
        [kind === "packages" ? "packageId" : "questionId"]: Number(id),
        asset,
      },
    );
    return { buffer, mime: data.mime };
  }
  async resultMedia(
    user: Account,
    org: string,
    learner: string,
    attempt: string,
    stat: string,
    asset: string,
  ) {
    await this.proctorAccess(user, org);
    uuid(learner);
    if (
      ![attempt, stat].every((value) => /^[1-9][0-9]{0,14}$/.test(value)) ||
      !/^[a-f0-9]{64}$/.test(asset)
    )
      throw new BadRequestException("Invalid result image.");
    const response = await this.remote.request(
      await this.config(),
      org,
      `results/${org}/learners/${learner}/attempts/${attempt}/media/${stat}/${asset}?actor_id=${user.id}`,
      undefined,
      14000000,
      30000,
    );
    await this.proctorAccess(user, org);
    const data = response.data;
    const invalid = () =>
      new ServiceUnavailableException("This result image could not be loaded.");
    if (
      !data ||
      data.attempt_id !== Number(attempt) ||
      data.stat_id !== Number(stat) ||
      data.asset !== asset ||
      ![
        "image/png",
        "image/jpeg",
        "image/gif",
        "image/webp",
        "image/avif",
      ].includes(data.mime) ||
      typeof data.base64 !== "string" ||
      data.base64.length > 13981016
    )
      throw invalid();
    const buffer = Buffer.from(data.base64, "base64");
    if (
      !buffer.length ||
      buffer.length > 10485760 ||
      buffer.toString("base64") !== data.base64
    )
      throw invalid();
    await this.access.audit(this.db, user, org, "exams.result.image.viewed", {
      learnerId: learner,
      attemptId: Number(attempt),
      statId: Number(stat),
      asset,
    });
    return { buffer, mime: data.mime };
  }
  async resultReview(
    user: Account,
    org: string,
    learner: string,
    attempt?: string,
    after = "0",
    body?: Record<string, unknown>,
  ) {
    await this.proctorAccess(user, org);
    uuid(learner);
    if (
      !/^[0-9]{1,15}$/.test(after) ||
      (attempt !== undefined && !/^[1-9][0-9]{0,14}$/.test(attempt))
    )
      throw new BadRequestException("Invalid result request.");
    if (body !== undefined) {
      if (
        !attempt ||
        !body ||
        Object.keys(body).some(
          (key) => !["marks", "revision", "request_id"].includes(key),
        ) ||
        typeof body.revision !== "string" ||
        !/^[a-f0-9]{64}$/.test(body.revision) ||
        typeof body.request_id !== "string"
      )
        throw new BadRequestException("Invalid marking request.");
      uuid(body.request_id);
      const marks = body.marks;
      if (
        !marks ||
        typeof marks !== "object" ||
        Array.isArray(marks) ||
        Object.keys(marks).length < 1 ||
        Object.keys(marks).length > 500 ||
        Object.entries(marks).some(
          ([key, value]) =>
            !/^[1-9][0-9]{0,14}$/.test(key) ||
            typeof value !== "number" ||
            !Number.isFinite(value) ||
            value < 0,
        )
      )
        throw new BadRequestException(
          "Enter valid marks for every pending answer.",
        );
    }
    await this.workspace.launch(user, org, { feature: "results" }, true);
    await this.proctorAccess(user, org);
    const path = `results/${org}/learners/${learner}/attempts${attempt ? "/" + attempt : ""}`;
    const data = await this.remote.request(
      await this.config(),
      org,
      path + (body ? "" : `?actor_id=${user.id}&after=${after}`),
      body ? { ...body, actor_id: user.id } : undefined,
    );
    await this.proctorAccess(user, org);
    const invalid = () =>
      new ServiceUnavailableException(
        "Exam results are temporarily unavailable.",
      );
    const positive = (n: unknown) =>
      typeof n === "number" && Number.isSafeInteger(n) && n > 0;
    const finite = (n: unknown) => typeof n === "number" && Number.isFinite(n);
    const summary = (row: any) => {
      if (
        !row ||
        !positive(row.attempt_id) ||
        (attempt && row.attempt_id !== Number(attempt)) ||
        typeof row.result !== "string" ||
        row.result.length > 80 ||
        !finite(row.score_percent) ||
        !finite(row.obtained_marks) ||
        !finite(row.total_marks) ||
        row.total_marks < 0
      )
        throw invalid();
      return {
        attempt_id: row.attempt_id,
        result: row.result,
        score_percent: row.score_percent,
        obtained_marks: row.obtained_marks,
        total_marks: row.total_marks,
      };
    };
    if (body) {
      if (data.conflict === true)
        throw new ConflictException(
          "This attempt changed. Reload it before marking again.",
        );
      if (data.saved === false)
        throw new BadRequestException(
          "Check the marks for every pending answer.",
        );
      if (data.saved !== true) throw invalid();
      const result = summary(data.result);
      await this.access.audit(this.db, user, org, "exams.result.marked", {
        learnerId: learner,
        attemptId: Number(attempt),
        requestId: body.request_id,
      });
      return result;
    }
    if (attempt) {
      if (
        typeof data.revision !== "string" ||
        !/^[a-f0-9]{64}$/.test(data.revision) ||
        !Array.isArray(data.questions) ||
        data.questions.length > 500
      )
        throw invalid();
      const seen = new Set<number>();
      return {
        revision: data.revision,
        summary: summary(data.summary),
        questions: data.questions.map((row: any) => {
          if (
            !row ||
            !positive(row.stat_id) ||
            seen.has(row.stat_id) ||
            !positive(row.question_id) ||
            typeof row.question_html !== "string" ||
            row.question_html.length > 100000 ||
            typeof row.answer_html !== "string" ||
            row.answer_html.length > 100000 ||
            typeof row.reference_html !== "string" ||
            row.reference_html.length > 100000 ||
            typeof row.review_supported !== "boolean" ||
            (row.passage !== null &&
              (!row.passage ||
                typeof row.passage.name !== "string" ||
                row.passage.name.length > 250 ||
                typeof row.passage.html !== "string" ||
                row.passage.html.length > 100000)) ||
            !finite(row.maximum_marks) ||
            row.maximum_marks < 0
          )
            throw invalid();
          seen.add(row.stat_id);
          return {
            stat_id: row.stat_id,
            question_id: row.question_id,
            question_html: row.question_html,
            answer_html: row.answer_html,
            reference_html: row.reference_html,
            review_supported: row.review_supported,
            passage:
              row.passage === null
                ? null
                : { name: row.passage.name, html: row.passage.html },
            maximum_marks: row.maximum_marks,
          };
        }),
      };
    }
    if (
      !Array.isArray(data.items) ||
      data.items.length > 50 ||
      (data.next !== null && !positive(data.next))
    )
      throw invalid();
    return {
      next: data.next,
      items: data.items.map((row: any) => {
        const result = summary(row);
        if (
          !positive(row.exam_id) ||
          typeof row.exam_name !== "string" ||
          row.exam_name.length > 250 ||
          typeof row.finished_at !== "string" ||
          !Number.isFinite(Date.parse(row.finished_at)) ||
          !Number.isSafeInteger(row.pending_count) ||
          row.pending_count < 0
        )
          throw invalid();
        return {
          ...result,
          exam_id: row.exam_id,
          exam_name: row.exam_name,
          finished_at: row.finished_at,
          pending_count: row.pending_count,
        };
      }),
    };
  }
  private async proctorAccess(user: Account, org: string) {
    await this.access.require(user, org, "exams.manage");
    const rules = await this.workspace.status(user, org);
    if (rules.restrictions.includes("results"))
      throw new ForbiddenException("Exam review is restricted.");
  }
  async proctorReview(
    user: Account,
    org: string,
    learner: string,
    attempt?: string,
    capture?: string,
    after = "0",
  ) {
    await this.proctorAccess(user, org);
    uuid(learner);
    if (
      !/^[0-9]{1,15}$/.test(after) ||
      (attempt !== undefined && !/^[1-9][0-9]{0,14}$/.test(attempt))
    )
      throw new BadRequestException("Invalid exam review request.");
    if (capture !== undefined) uuid(capture);
    const path =
      `review/${org}/learners/${learner}/attempts` +
      (attempt
        ? `/${attempt}/captures${capture ? `/${capture}` : ""}`
        : `?after=${after}`);
    const data = await this.remote.request(await this.config(), org, path);
    await this.proctorAccess(user, org);
    const invalid = () =>
      new ServiceUnavailableException("Private exam evidence is unavailable.");
    const positive = (n: unknown) =>
      typeof n === "number" && Number.isSafeInteger(n) && n > 0;
    const date = (s: unknown) =>
      typeof s === "string" && Number.isFinite(Date.parse(s));
    if (attempt && data.attempt_id !== Number(attempt)) throw invalid();
    if (capture) {
      if (
        data.capture_id !== capture ||
        data.mime !== "image/jpeg" ||
        typeof data.base64 !== "string" ||
        data.base64.length > 349528 ||
        !date(data.expires_at) ||
        Date.parse(data.expires_at) <= Date.now()
      )
        throw invalid();
      const buffer = Buffer.from(data.base64, "base64");
      if (
        buffer.length < 3 ||
        buffer.length > 262144 ||
        buffer.toString("base64") !== data.base64 ||
        buffer[0] !== 255 ||
        buffer[1] !== 216 ||
        buffer[2] !== 255
      )
        throw invalid();
      await this.access.audit(this.db, user, org, "exams.camera.viewed", {
        learnerId: learner,
        attemptId: Number(attempt),
        captureId: capture,
      });
      return { buffer };
    }
    if (!Array.isArray(data.items) || data.items.length > (attempt ? 1200 : 50))
      throw invalid();
    if (attempt)
      return {
        attempt_id: Number(attempt),
        items: data.items
          .map((row: any) => {
            if (
              row.attempt_id !== Number(attempt) ||
              typeof row.capture_id !== "string" ||
              !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/.test(
                row.capture_id,
              ) ||
              !date(row.received_at) ||
              !date(row.expires_at)
            )
              throw invalid();
            return {
              capture_id: row.capture_id,
              received_at: row.received_at,
              expires_at: row.expires_at,
            };
          })
          .filter((row: any) => Date.parse(row.expires_at) > Date.now()),
      };
    if (data.next !== null && !positive(data.next)) throw invalid();
    return {
      next: data.next,
      items: data.items.map((row: any) => {
        if (
          !positive(row.attempt_id) ||
          !positive(row.exam_id) ||
          typeof row.exam_name !== "string" ||
          row.exam_name.length > 250 ||
          (row.started_at !== null && !date(row.started_at)) ||
          (row.finished_at !== null && !date(row.finished_at))
        )
          throw invalid();
        return {
          attempt_id: row.attempt_id,
          exam_id: row.exam_id,
          exam_name: row.exam_name,
          started_at: row.started_at,
          finished_at: row.finished_at,
        };
      }),
    };
  }
  private async questionAccess(
    user: Account,
    org: string,
    id: string,
    feature = "questions",
  ) {
    await this.access.require(user, org, "exams.manage");
    if (id !== "new" && !/^[1-9][0-9]{0,14}$/.test(id))
      throw new BadRequestException("Invalid question.");
    const rules = await this.workspace.status(user, org);
    if (rules.restrictions.includes(feature))
      throw new ForbiddenException("Question bank is restricted.");
  }
  async questionChoices(
    user: Account,
    org: string,
    kind: string,
    search: string,
    after: string,
    parent = "",
  ) {
    await this.access.require(user, org, "exams.manage");
    const rules = await this.workspace.status(user, org);
    if (
      rules.restrictions.includes("questions") &&
      rules.restrictions.includes("subjects") &&
      rules.restrictions.includes("exams")
    )
      throw new ForbiddenException("Exam authoring is restricted.");
    if (
      ![
        "exams",
        "packages",
        "categories",
        "subcategories",
        "groups",
        "subjects",
        "sections",
        "topics",
        "subtopics",
        "languages",
        "platform-languages",
        "package-tags",
        "types",
        "difficulties",
      ].includes(kind) ||
      typeof search !== "string" ||
      search.length > 120 ||
      !/^[0-9]{1,15}$/.test(after) ||
      typeof parent !== "string" ||
      (parent !== "" &&
        (kind !== "subcategories" || !/^[1-9][0-9]{0,14}$/.test(parent)))
    )
      throw new BadRequestException("Invalid question lookup.");
    if (kind === "exams" && rules.restrictions.includes("exams"))
      throw new ForbiddenException("Exams are restricted.");
    return this.remote.request(
      await this.config(),
      org,
      `authoring/${org}/choices/${kind}?search=${encodeURIComponent(search)}&after=${after}${parent ? `&parent_id=${parent}` : ""}`,
    );
  }
  async question(user: Account, org: string, id: string) {
    await this.questionAccess(user, org, id);
    if (id === "new") {
      await this.workspace.launch(user, org, { feature: "questions" }, true);
      return this.newQuestion();
    }
    return this.remote.request(
      await this.config(),
      org,
      `authoring/${org}/questions/${id}`,
    );
  }
  private newQuestion() {
    return {
      id: 0,
      revision: "new",
      fields: {
        question: "",
        marks: 1,
        negative_marks: 0,
        status: "Yes",
        group_ids: [],
        tag_ids: [],
        correct_answers: [],
        nat_mode: "exact",
        fill_blank_answers: [{ accepted_answers: "" }],
      },
    };
  }
  async examQuestions(user: Account, org: string, id: string, after: string) {
    await this.questionAccess(user, org, id, "exams");
    if (id === "new" || !/^[0-9]{1,15}$/.test(after))
      throw new BadRequestException("Invalid exam page.");
    return this.remote.request(
      await this.config(),
      org,
      `authoring/${org}/exams/${id}/questions?after=${after}`,
    );
  }
  private taxonomyKind(kind: string) {
    if (
      ![
        "exams",
        "languages",
        "packages",
        "categories",
        "subcategories",
        "groups",
        "subjects",
        "topics",
        "subtopics",
        "sections",
      ].includes(kind)
    )
      throw new BadRequestException("Invalid exam classification.");
  }
  async taxonomy(user: Account, org: string, kind: string, id: string) {
    this.taxonomyKind(kind);
    await this.questionAccess(
      user,
      org,
      id,
      kind === "exams" ? "exams" : "subjects",
    );
    if (id === "new")
      await this.workspace.launch(
        user,
        org,
        { feature: kind === "exams" ? "exams" : "subjects" },
        true,
      );
    return this.remote.request(
      await this.config(),
      org,
      `authoring/${org}/taxonomy/${kind}/${id}`,
    );
  }
  async saveQuestion(
    user: Account,
    org: string,
    id: string,
    b: Record<string, unknown>,
    kind = "questions",
    action?: string,
  ) {
    const imageAction =
      ["questions", "packages"].includes(kind) &&
      action === "set-image" &&
      id !== "new";
    const languageDisable =
      kind === "languages" && action === "disable-language" && id !== "new";
    if (
      action &&
      !imageAction &&
      !languageDisable &&
      (kind !== "exams" ||
        id === "new" ||
        ![
          "add-questions",
          "remove-questions",
          "create-section",
          "update-section",
          "remove-section",
          "assign-section",
          "subject-timers",
          "set-status",
          "set-result-status",
          "generate-document",
          "approve-translation",
          "refresh-translation",
          "save-question-translation",
          "save-exam-translation",
        ].includes(action))
    )
      throw new BadRequestException("Invalid exam action.");
    if (kind !== "questions") this.taxonomyKind(kind);
    const feature =
      kind === "questions"
        ? "questions"
        : kind === "exams"
          ? "exams"
          : "subjects";
    await this.questionAccess(user, org, id, feature);
    if (
      !b.fields ||
      typeof b.fields !== "object" ||
      Array.isArray(b.fields) ||
      JSON.stringify(b.fields).length > (imageAction ? 750000 : 250000) ||
      typeof b.revision !== "string" ||
      (id === "new"
        ? b.revision !== "new"
        : !/^[a-f0-9]{64}$/.test(b.revision)) ||
      typeof b.request_id !== "string" ||
      !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/.test(
        b.request_id,
      )
    )
      throw new BadRequestException("Invalid question changes.");
    if (
      action === "save-question-translation" ||
      action === "save-exam-translation"
    ) {
      const fields = b.fields as Record<string, unknown>;
      const wording = fields.wording;
      const examWording = action === "save-exam-translation";
      if (
        Object.keys(fields).some(
          (key) =>
            ![
              "language_id",
              "translation_revision",
              ...(examWording ? [] : ["question_id"]),
              "wording",
            ].includes(key),
        ) ||
        ![
          fields.language_id,
          ...(examWording ? [] : [fields.question_id]),
        ].every(
          (value) =>
            Number.isSafeInteger(value) &&
            Number(value) > 0 &&
            Number(value) < 1e15,
        ) ||
        typeof fields.translation_revision !== "string" ||
        !/^[a-f0-9]{64}$/.test(fields.translation_revision) ||
        !wording ||
        typeof wording !== "object" ||
        Array.isArray(wording) ||
        !Object.keys(wording).length ||
        Object.entries(wording).some(
          ([key, value]) =>
            !(
              examWording
                ? ["name", "instruction", "syllabus"]
                : [
                    "question",
                    "option1",
                    "option2",
                    "option3",
                    "option4",
                    "option5",
                    "option6",
                    "hint",
                    "explanation",
                    "fill_blank",
                  ]
            ).includes(key) ||
            (value !== null &&
              (typeof value !== "string" || value.length > 200000)),
        )
      )
        throw new BadRequestException("Invalid translated wording changes.");
    }
    if (action === "approve-translation" || action === "refresh-translation") {
      const fields = b.fields as Record<string, unknown>;
      if (
        Object.keys(fields).some(
          (key) => !["language_id", "translation_revision"].includes(key),
        ) ||
        !Number.isSafeInteger(fields.language_id) ||
        Number(fields.language_id) <= 0 ||
        Number(fields.language_id) >= 1e15 ||
        typeof fields.translation_revision !== "string" ||
        !/^[a-f0-9]{64}$/.test(fields.translation_revision)
      )
        throw new BadRequestException(
          "Review the current assigned language before approving it.",
        );
    }
    if (action === "generate-document") {
      const fields = b.fields as Record<string, unknown>;
      if (
        Object.keys(fields).some(
          (key) =>
            !["package_id", "language_id", "document_type"].includes(key),
        ) ||
        !["package_id", "language_id"].every(
          (key) =>
            Number.isSafeInteger(fields[key]) &&
            Number(fields[key]) > 0 &&
            Number(fields[key]) < 1e15,
        ) ||
        typeof fields.document_type !== "string" ||
        !["questions", "solutions"].includes(fields.document_type)
      )
        throw new BadRequestException(
          "Select an assigned package, language and document type.",
        );
    }
    if (
      languageDisable &&
      (Object.keys(b.fields as object).length ||
        Object.keys(b).some(
          (key) => !["fields", "revision", "request_id"].includes(key),
        ))
    )
      throw new BadRequestException("Invalid language disable request.");
    if (imageAction) {
      const fields = b.fields as Record<string, unknown>;
      if (
        Object.keys(b).some(
          (key) => !["fields", "revision", "request_id"].includes(key),
        ) ||
        Object.keys(fields).some(
          (key) =>
            !(
              kind === "packages"
                ? ["image", "asset", "remove"]
                : ["field", "image", "asset", "remove"]
            ).includes(key),
        ) ||
        (kind === "questions" &&
          (typeof fields.field !== "string" ||
            ![
              "question",
              "option1",
              "option2",
              "option3",
              "option4",
              "option5",
              "option6",
              "hint",
              "explanation",
              "si_answer1",
            ].includes(fields.field))) ||
        (fields.remove !== undefined && fields.remove !== true) ||
        (fields.remove === true
          ? fields.image !== undefined || fields.asset === undefined
          : typeof fields.image !== "string" ||
            !fields.image.length ||
            fields.image.length > 699052) ||
        (fields.asset !== undefined &&
          (typeof fields.asset !== "string" ||
            !/^[a-f0-9]{64}$/.test(fields.asset)))
      )
        throw new BadRequestException(
          "Invalid image. Use PNG, JPEG or WebP up to 512 KB.",
        );
    }
    await this.workspace.launch(user, org, { feature }, true);
    const response = await this.remote.request(
      await this.config(),
      org,
      imageAction
        ? `authoring/${org}/${kind}/${id}/image`
        : languageDisable
          ? `authoring/${org}/taxonomy/languages/${id}/disable`
          : action
            ? `authoring/${org}/exams/${id}/actions/${action}`
            : kind === "questions"
              ? `authoring/${org}/questions${id === "new" ? "" : "/" + id}`
              : `authoring/${org}/taxonomy/${kind}/${id}`,
      {
        fields: b.fields,
        revision: b.revision,
        request_id: b.request_id,
        actor_id: user.id,
      },
    );
    if (imageAction || languageDisable)
      await this.questionAccess(user, org, id, feature);
    if (response.conflict === true)
      throw new ConflictException(
        "Question changed. Reload it before saving again.",
      );
    if (response.saved !== true) {
      const messages = Object.values(response.errors ?? {})
        .flat()
        .filter((v): v is string => typeof v === "string")
        .slice(0, 12)
        .map((v) => v.slice(0, 300));
      throw new BadRequestException(
        messages.join(" ") || "ExamElite could not save this question.",
      );
    }
    if (
      imageAction &&
      kind === "packages" &&
      (response.question?.id !== Number(id) ||
        typeof response.question?.revision !== "string" ||
        !/^[a-f0-9]{64}$/.test(response.question.revision) ||
        !response.question?.fields ||
        typeof response.question.fields !== "object" ||
        Array.isArray(response.question.fields) ||
        ((b.fields as Record<string, unknown>).remove === true
          ? response.question.photo_asset !== null
          : typeof response.question.photo_asset !== "string" ||
            !/^[a-f0-9]{64}$/.test(response.question.photo_asset)))
    )
      throw new ServiceUnavailableException(
        "Unable to verify the saved package image. Retry or reload the package.",
      );
    if (
      languageDisable &&
      (response.question?.id !== Number(id) ||
        response.question?.fields?.is_enabled !== false ||
        typeof response.question?.revision !== "string" ||
        !/^[a-f0-9]{64}$/.test(response.question.revision))
    )
      throw new ServiceUnavailableException(
        "Unable to verify the language state. Reload before continuing.",
      );
    await this.access.audit(
      this.db,
      user,
      org,
      `exams.${kind}.${action ?? (id === "new" ? "created" : "updated")}`,
      {
        questionId: response.question?.id,
        requestId: b.request_id,
      },
    );
    return response.question;
  }
  constructor(
    private readonly db: Database,
    private readonly access: AccessService,
    private readonly remote: ExamEliteService,
    private readonly workspace: ExamWorkspaceService,
  ) {}
  private async organisation(org: string) {
    if (
      !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/.test(
        org,
      )
    )
      throw new BadRequestException("Invalid organisation.");
    if (
      !(await this.db.query("SELECT id FROM organisations WHERE id=$1", [org]))
        .rows.length
    )
      throw new NotFoundException();
  }
  private admin(user: Account) {
    if (!user.is_superadmin)
      throw new ForbiddenException(
        "Only superadmin can manage central question sharing.",
      );
  }
  private async config() {
    const c = await this.remote.configuration("_platform");
    if (!c?.central)
      throw new BadRequestException(
        "Configure the central ExamElite connection first.",
      );
    return c;
  }
  async modules(user: Account, org: string) {
    this.admin(user);
    await this.organisation(org);
    return (
      (
        await this.db.query<{
          enabled_modules: Record<string, boolean>;
          version: number;
        }>(
          "SELECT enabled_modules,version FROM organisation_settings WHERE organisation_id=$1",
          [org],
        )
      ).rows[0] ?? {
        enabled_modules: {
          learners: true,
          attendance: false,
          exams: false,
          fln: false,
        },
        version: 0,
      }
    );
  }
  async setModules(user: Account, org: string, b: Record<string, unknown>) {
    this.admin(user);
    await this.organisation(org);
    if (typeof b.exams !== "boolean" || !Number.isSafeInteger(b.version))
      throw new BadRequestException("Invalid module settings.");
    await this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const old = (
        await sql.query<any>(
          "SELECT enabled_modules,version FROM organisation_settings WHERE organisation_id=$1",
          [org],
        )
      ).rows[0];
      if ((old?.version ?? 0) !== b.version)
        throw new ConflictException(
          "Module settings changed. Reload before saving.",
        );
      const modules = {
        learners: true,
        attendance: false,
        fln: false,
        ...old?.enabled_modules,
        exams: b.exams,
      };
      await sql.query(
        `INSERT INTO organisation_settings(organisation_id,enabled_modules) VALUES($1,$2) ON CONFLICT(organisation_id) DO UPDATE SET enabled_modules=$2,version=organisation_settings.version+1`,
        [org, JSON.stringify(modules)],
      );
      await this.access.audit(sql, user, org, "exams.module.changed", {
        enabled: b.exams,
      });
    });
    return this.modules(user, org);
  }
  async questions(
    user: Account,
    org: string,
    source: string,
    search: string,
    after: string,
    platform = false,
  ) {
    if (platform) {
      this.admin(user);
      await this.organisation(org);
    } else {
      await this.access.require(user, org, "exams.manage");
      const settings = (
        await this.db.query<any>(
          "SELECT enabled_modules FROM organisation_settings WHERE organisation_id=$1",
          [org],
        )
      ).rows[0];
      if (settings?.enabled_modules.exams !== true)
        throw new ForbiddenException(
          "Superadmin has not enabled exams for this organisation.",
        );
      if (source !== "organisation") throw new ForbiddenException();
      const rules = await this.workspace.status(user, org);
      if (rules.restrictions.includes("questions"))
        throw new ForbiddenException(
          "Question bank is restricted for this organisation.",
        );
    }
    if (
      !["central", "organisation"].includes(source) ||
      typeof search !== "string" ||
      search.length > 120 ||
      !/^\d{1,15}$/.test(after)
    )
      throw new BadRequestException("Invalid question search.");
    return this.remote.request(
      await this.config(),
      org,
      `content/${org}/questions?source=${source}&search=${encodeURIComponent(search)}&after=${after}`,
    );
  }
  async transfer(user: Account, org: string, b: Record<string, unknown>) {
    this.admin(user);
    await this.organisation(org);
    if (
      !["share", "pull"].includes(String(b.direction)) ||
      !Array.isArray(b.question_ids) ||
      b.question_ids.length < 1 ||
      b.question_ids.length > 50 ||
      b.question_ids.some(
        (id) => !Number.isSafeInteger(id) || Number(id) <= 0,
      ) ||
      typeof b.request_id !== "string" ||
      !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/.test(
        b.request_id,
      )
    )
      throw new BadRequestException(
        "Select up to 50 questions and a valid request.",
      );
    const settings = await this.modules(user, org);
    if (b.direction === "share" && settings.enabled_modules.exams !== true)
      throw new ForbiddenException(
        "Enable the Exams module before sharing questions.",
      );
    if (b.direction === "share")
      await this.workspace.launch(user, org, { feature: "questions" }, true);
    const result = await this.remote.request(
      await this.config(),
      org,
      `content/${org}/transfer`,
      {
        direction: b.direction,
        question_ids: [...new Set(b.question_ids)].sort(
          (a, b) => Number(a) - Number(b),
        ),
        request_id: b.request_id,
        actor_id: user.id,
      },
    );
    await this.access.audit(
      this.db,
      user,
      org,
      "exams.questions." + b.direction,
      { requestId: b.request_id, count: result.count },
    );
    return result;
  }
  async history(user: Account, org: string) {
    this.admin(user);
    await this.organisation(org);
    return this.remote.request(
      await this.config(),
      org,
      `content/${org}/transfers`,
    );
  }
}
