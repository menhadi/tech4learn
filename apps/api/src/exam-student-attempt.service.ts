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
      start: ["request_id", "language_id"],
      answer: ["request_id", "attempt_id", "question_id", "fields", "revision"],
      submit: ["request_id", "attempt_id"],
      visibility: ["request_id", "attempt_id", "event"],
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
      (action !== "start" && !id(body.attempt_id)) ||
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
    if (
      (action === "submit" || action === "visibility") &&
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
