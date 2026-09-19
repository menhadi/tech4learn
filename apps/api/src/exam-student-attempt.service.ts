import {
  Injectable,
  BadRequestException,
  HttpException,
  ServiceUnavailableException,
} from "@nestjs/common";
import { ExamStudentAccessService } from "./exam-student-access.service.js";
import { ExamEliteService } from "./examelite.service.js";
import { IdentityService } from "./identity.service.js";
import { Database } from "./database.js";
import { uuid } from "./security.js";
import { randomUUID } from "node:crypto";

@Injectable()
export class ExamStudentAttemptService {
  async attachment(
    org: string,
    cookie: string | undefined,
    action: "upload" | "read" | "extract",
    body: Record<string, unknown>,
  ) {
    const context = await this.access.context(org, cookie);
    const allowed = action === "upload"
      ? ["attempt_id", "question_id", "request_id", "revision", "base64"]
      : ["attempt_id", "question_id", "asset"];
    if (!body || Array.isArray(body) || Object.keys(body).some(key => !allowed.includes(key)) ||
      !Number.isSafeInteger(body.attempt_id) || Number(body.attempt_id) <= 0 ||
      !Number.isSafeInteger(body.question_id) || Number(body.question_id) <= 0)
      throw new BadRequestException("Invalid answer attachment.");
    const hash = (value: unknown) => typeof value === "string" && /^[a-f0-9]{64}$/.test(value);
    if (action === "upload") {
      if (typeof body.request_id !== "string" || !hash(body.revision) ||
        typeof body.base64 !== "string" || body.base64.length > 13981016 ||
        !/^[a-zA-Z0-9+/]*={0,2}$/.test(body.base64))
        throw new BadRequestException("Invalid answer attachment.");
      uuid(body.request_id);
      const bytes = Buffer.from(body.base64, "base64");
      if (!bytes.length || bytes.length > 10485760 || bytes.toString("base64") !== body.base64)
        throw new BadRequestException("Upload a file no larger than 10 MB.");
    } else if (!hash(body.asset)) throw new BadRequestException("Invalid answer attachment.");
    await this.identity.limit(`exam-attachment:${context.grant_id}`, 30, 60);
    const config = await this.remote.configuration("_platform");
    if (!config?.central) throw new ServiceUnavailableException("Answer attachments are unavailable.");
    const exam = Number(context.external_exam_id);
    const response = await this.remote.request(config, org, `attachments/${org}/${action}`, {
      ...body, learner_id: context.learner_id, exam_id: exam,
    }, action === "read" ? 14000000 : action === "extract" ? 128000 : 4096, 30000);
    const current = await this.access.context(org, cookie);
    if (current.grant_id !== context.grant_id) throw new HttpException("Exam access changed.", 403);
    if (response.error) {
      const status = response.error.status;
      const messages: Record<number, string> = {
        403: "This answer attachment is no longer available.",
        404: "This answer attachment was not found.",
        409: "The answer changed. Resume saved answers before uploading again.",
        422: "The file or attempt does not allow this upload.",
        429: "Please wait before retrying the attachment.",
      };
      if (Object.hasOwn(messages, status)) throw new HttpException(messages[status], status);
      throw new ServiceUnavailableException("Answer attachments are unavailable.");
    }
    const data = response.data;
    const invalid = () => new ServiceUnavailableException("Invalid answer attachment response.");
    if (!data || data.exam_id !== exam || data.attempt_id !== body.attempt_id ||
      data.question_id !== body.question_id || !hash(data.asset)) throw invalid();
    if (action === "extract") {
      if (data.asset !== body.asset || typeof data.text !== "string" || !data.text.trim() ||
        Buffer.byteLength(data.text, "utf8") > 20000 || data.text.includes("\0")) throw invalid();
      return { attempt_id: data.attempt_id, question_id: data.question_id, asset: data.asset, text: data.text };
    }
    if (action === "upload") {
      if (data.success !== true || data.saved !== true || !hash(data.revision)) throw invalid();
      return { saved: true, attempt_id: data.attempt_id, question_id: data.question_id,
        asset: data.asset, revision: data.revision };
    }
    if (data.asset !== body.asset || !["text/plain", "application/pdf", "image/jpeg", "image/png",
      "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      "application/octet-stream"].includes(data.mime) || typeof data.base64 !== "string" ||
      data.base64.length > 13981016 || !/^[a-zA-Z0-9+/]*={0,2}$/.test(data.base64)) throw invalid();
    const buffer = Buffer.from(data.base64, "base64");
    if (!buffer.length || buffer.length > 10485760 || buffer.toString("base64") !== data.base64) throw invalid();
    return { buffer, mime: data.mime };
  }
  async media(
    org: string,
    cookie: string | undefined,
    attempt: string,
    question: string,
    asset: string,
  ) {
    const context = await this.access.context(org, cookie);
    if (
      !/^[1-9][0-9]{0,14}$/.test(attempt) ||
      !/^[1-9][0-9]{0,14}$/.test(question) ||
      !/^[a-f0-9]{64}$/.test(asset)
    )
      throw new BadRequestException("Invalid question image.");
    await this.identity.limit(`exam-media:${context.grant_id}`, 180, 60);
    const config = await this.remote.configuration("_platform");
    if (!config?.central)
      throw new ServiceUnavailableException("Question images are unavailable.");
    const response = await this.remote.request(
      config,
      org,
      `student/${org}/media`,
      {
        learner_id: context.learner_id,
        name: context.student_name,
        exam_id: Number(context.external_exam_id),
        fields: {
          request_id: randomUUID(),
          attempt_id: Number(attempt),
          question_id: Number(question),
          asset,
        },
      },
      14000000,
      30000,
    );
    const current = await this.access.context(org, cookie);
    if (current.grant_id !== context.grant_id)
      throw new HttpException("Exam access changed.", 403);
    const data = response.data;
    if (
      !data ||
      data.asset !== asset ||
      data.question_id !== Number(question) ||
      data.attempt_id !== Number(attempt) ||
      ![
        "image/png",
        "image/jpeg",
        "image/gif",
        "image/webp",
        "image/avif",
      ].includes(data.mime) ||
      typeof data.base64 !== "string" ||
      data.base64.length > 13981016 ||
      !/^[a-zA-Z0-9+/]*={0,2}$/.test(data.base64)
    )
      throw new ServiceUnavailableException(
        "This question image could not be loaded.",
      );
    const buffer = Buffer.from(data.base64, "base64");
    if (
      !buffer.length ||
      buffer.length > 10485760 ||
      buffer.toString("base64") !== data.base64
    )
      throw new ServiceUnavailableException("Invalid question image.");
    return { buffer, mime: data.mime };
  }
  constructor(
    private readonly access: ExamStudentAccessService,
    private readonly remote: ExamEliteService,
    private readonly identity: IdentityService,
    private readonly db: Database,
  ) {}
  async run(
    org: string,
    cookie: string | undefined,
    action: string,
    body: Record<string, unknown>,
  ) {
    const context = await this.access.context(org, cookie);
    const allowed: Record<string, string[]> = {
      prepare: ["request_id"],
      history: ["request_id"],
      start: ["request_id", "language_id", "camera_ready"],
      answer: ["request_id", "attempt_id", "question_id", "fields", "revision"],
      submit: ["request_id", "attempt_id"],
      result: ["request_id", "attempt_id"],
      visibility: ["request_id", "attempt_id", "event"],
      proctor: ["request_id", "attempt_id", "image"],
    };
    if (
      !Object.hasOwn(allowed, action) ||
      !body ||
      Array.isArray(body) ||
      Object.keys(body).some((k) => !allowed[action].includes(k)) ||
      typeof body.request_id !== "string"
    )
      throw new BadRequestException("Invalid exam request.");
    uuid(body.request_id);
    if (action === "visibility" && body.event !== "hidden")
      throw new BadRequestException("Invalid visibility event.");
    const id = (value: unknown) =>
      typeof value === "number" && Number.isSafeInteger(value) && value > 0;
    if (
      (!["start", "prepare", "history"].includes(action) &&
        !id(body.attempt_id)) ||
      (body.language_id !== undefined && !id(body.language_id))
    )
      throw new BadRequestException("Invalid exam request.");
    if (
      action === "answer" &&
      (!id(body.question_id) ||
        typeof body.revision !== "string" ||
        !/^[a-f0-9]{64}$/.test(body.revision) ||
        !body.fields ||
        typeof body.fields !== "object" ||
        Array.isArray(body.fields) ||
        JSON.stringify(body.fields).length > 25000)
    )
      throw new BadRequestException("Invalid answer request.");
    if (
      action === "proctor" &&
      (typeof body.image !== "string" ||
        !body.image.length ||
        body.image.length > 349528 ||
        !/^[a-zA-Z0-9+/]*={0,2}$/.test(body.image))
    )
      throw new BadRequestException("Invalid camera capture.");
    if (
      body.camera_ready !== undefined &&
      typeof body.camera_ready !== "boolean"
    )
      throw new BadRequestException("Invalid camera readiness.");
    await this.identity.limit(`exam-attempt:${context.grant_id}`, 120, 60);
    const config = await this.remote.configuration("_platform");
    if (!config?.central)
      throw new ServiceUnavailableException(
        "Student exams are temporarily unavailable.",
      );
    const response = await this.remote.request(
      config,
      org,
      `student/${org}/${action}`,
      {
        learner_id: context.learner_id,
        name: context.student_name,
        exam_id: Number(context.external_exam_id),
        fields: body,
      },
    );
    // Recheck access after the engine call, before returning any student content.
    const current = await this.access.context(org, cookie);
    if (current.grant_id !== context.grant_id)
      throw new HttpException("Exam access changed. Sign in again.", 403);
    if (response.error) {
      const status = response.error.status;
      const known: Record<string, string> = {
        display_unavailable:
          "This paper contains a media format that is not supported yet. No new attempt was started.",
        controls_unavailable:
          "This paper requires exam controls that are still being integrated. Ask exam staff for a supported paper.",
        duration_changed:
          "The paper duration changed after you started. Ask exam staff to restore it before resuming.",
        camera_required:
          "Check camera access before starting or resuming this exam.",
        capture_interval: "Please wait before sending the next camera capture.",
        capture_limit:
          "The capture limit for this attempt has been reached. Contact exam staff.",
        attempts_exhausted: "You have used all allowed attempts for this exam.",
        section_ended:
          "This question is outside the current section time. Resume saved answers to continue.",
        schedule_missing:
          "This older attempt has no saved section schedule. Ask exam staff for a new attempt.",
        timer_changed:
          "The paper timer mode changed after you started. Ask exam staff to restore it before resuming.",
      };
      const messages: Record<number, string> = {
        403: "Exam access is no longer available.",
        404: "This assigned exam or attempt is unavailable.",
        409: "This attempt changed or cannot continue. Resume to refresh it; contact exam staff if this persists.",
        422: "This exam request or delivery mode is not supported yet.",
        429: "Please wait before retrying this exam request.",
      };
      if (messages[status])
        throw new HttpException(
          Object.hasOwn(known, response.error.code)
            ? known[response.error.code]
            : messages[status],
          status,
        );
      throw new ServiceUnavailableException(
        "Student exams are temporarily unavailable.",
      );
    }
    const data = response.data;
    if (action === "history") {
      const invalid = () =>
        new ServiceUnavailableException("Previous results are unavailable.");
      if (
        !data ||
        data.exam_id !== Number(context.external_exam_id) ||
        !Array.isArray(data.items) ||
        data.items.length > 50
      )
        throw invalid();
      const rules = (
        await this.db.query<{ restrictions: string[] }>(
          "SELECT restrictions FROM examelite_workspaces WHERE organisation_id=$1",
          [org],
        )
      ).rows[0];
      const seen = new Set<number>();
      return {
        exam_id: data.exam_id,
        items: data.items.map((row: any) => {
          if (
            !row ||
            !id(row.attempt_id) ||
            seen.has(row.attempt_id) ||
            row.exam_id !== data.exam_id ||
            row.completed !== true ||
            typeof row.finished_at !== "string" ||
            !Number.isFinite(Date.parse(row.finished_at)) ||
            (row.result !== null &&
              (!row.result ||
                typeof row.result.status !== "string" ||
                row.result.status.length > 80 ||
                typeof row.result.score_percent !== "number" ||
                !Number.isFinite(row.result.score_percent)))
          )
            throw invalid();
          seen.add(row.attempt_id);
          return {
            attempt_id: row.attempt_id,
            finished_at: row.finished_at,
            result:
              !row.result || rules?.restrictions?.includes("results")
                ? null
                : {
                    status: row.result.status,
                    score_percent: row.result.score_percent,
                  },
          };
        }),
      };
    }
    if (action === "prepare") {
      if (
        !data ||
        data.exam_id !== Number(context.external_exam_id) ||
        typeof data.proctor !== "boolean"
      )
        throw new ServiceUnavailableException("Exam setup is unavailable.");
      return { exam_id: data.exam_id, proctor: data.proctor };
    }
    if (
      (action === "submit" ||
        action === "result" ||
        action === "visibility" ||
        action === "proctor") &&
      data?.attempt_id !== body.attempt_id
    )
      throw new ServiceUnavailableException(
        "The exam service returned a different attempt.",
      );
    if (
      !data ||
      typeof data !== "object" ||
      (action === "answer"
        ? data.saved !== true
        : !id(data.attempt_id) ||
          data.exam_id !== Number(context.external_exam_id))
    )
      throw new ServiceUnavailableException(
        "The exam service returned an invalid response.",
      );
    if (action === "result" && data.completed !== true)
      throw new ServiceUnavailableException("Submitted result is unavailable.");
    if (action === "answer") {
      if (
        data.question_id !== body.question_id ||
        typeof data.revision !== "string" ||
        !/^[a-f0-9]{64}$/.test(data.revision) ||
        typeof data.answer_locked !== "boolean"
      )
        throw new ServiceUnavailableException(
          "The exam service returned an invalid acknowledgement.",
        );
      return {
        saved: true,
        question_id: data.question_id,
        revision: data.revision,
        answer_locked: data.answer_locked,
      };
    }
    if (action === "proctor") {
      if (
        data.saved !== true ||
        data.capture_id !== body.request_id ||
        typeof data.received_at !== "string" ||
        !Number.isFinite(Date.parse(data.received_at)) ||
        typeof data.expires_at !== "string" ||
        !Number.isFinite(Date.parse(data.expires_at))
      )
        throw new ServiceUnavailableException(
          "Invalid camera capture receipt.",
        );
      return {
        saved: true,
        capture_id: data.capture_id,
        attempt_id: data.attempt_id,
        received_at: data.received_at,
        expires_at: data.expires_at,
      };
    }
    const rules = (
      await this.db.query<{ restrictions: string[] }>(
        "SELECT restrictions FROM examelite_workspaces WHERE organisation_id=$1",
        [org],
      )
    ).rows[0];
    if (data.completed)
      return {
        attempt_id: data.attempt_id,
        exam_id: data.exam_id,
        completed: true,
        result: rules?.restrictions?.includes("results") ? null : data.result,
      };
    if (action === "visibility") {
      if (
        !Number.isSafeInteger(data.tolerance_count) ||
        data.tolerance_count < 0 ||
        !Number.isSafeInteger(data.tolerance_limit) ||
        data.tolerance_limit <= data.tolerance_count
      )
        throw new ServiceUnavailableException(
          "Invalid exam event acknowledgement.",
        );
      return {
        attempt_id: data.attempt_id,
        exam_id: data.exam_id,
        completed: false,
        tolerance_count: data.tolerance_count,
        tolerance_limit: data.tolerance_limit,
      };
    }
    return data;
  }
}
