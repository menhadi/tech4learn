import { readFile } from "node:fs/promises";
import {
  Injectable,
  BadRequestException,
  ServiceUnavailableException,
} from "@nestjs/common";
import { AccessService } from "./access.service.js";
import { LearnersService } from "./learners.service.js";
import type { Account } from "./identity.service.js";
import { Database } from "./database.js";

type Connection = { token: string; organization_id: number };
const unavailable = () =>
  new ServiceUnavailableException(
    "ExamElite connection unavailable. Check the dedicated credential and organisation mapping.",
  );

export function connectionFor(config: unknown, org: string): Connection | null {
  if (!config || typeof config !== "object" || Array.isArray(config))
    throw unavailable();
  const c = Object.hasOwn(config, org)
    ? (config as Record<string, any>)[org]
    : null;
  if (!c || c.enabled === false) return null;
  if (
    c.enabled !== true ||
    !/^[a-f0-9]{64}$/.test(c.token || "") ||
    !Number.isSafeInteger(c.organization_id) ||
    c.organization_id <= 0
  )
    throw unavailable();
  return { token: c.token, organization_id: c.organization_id };
}

@Injectable()
export class ExamEliteService {
  constructor(
    private readonly access: AccessService,
    private readonly learners: LearnersService,
    private readonly db: Database,
  ) {}

  async configuration(org: string) {
    try {
      const path = process.env.T4L_EXAMELITE_CONFIG;
      if (!path) return null;
      return connectionFor(JSON.parse(await readFile(path, "utf8")), org);
    } catch {
      throw unavailable();
    }
  }

  async request(c: Connection, org: string, path: string) {
    try {
      const response = await fetch(
        "https://examelite.com/api/tech4learn/v1/" + path,
        {
          headers: {
            Authorization: `Bearer ${c.token}`,
            "X-Tech4Learn-Organisation": org,
            Accept: "application/json",
          },
          redirect: "error",
          signal: AbortSignal.timeout(10000),
        },
      );
      if (!response.ok || !response.body) throw unavailable();
      const reader = response.body.getReader();
      let size = 0;
      const chunks: Uint8Array[] = [];
      try {
        while (true) {
          const part = await reader.read();
          if (part.done) break;
          size += part.value.length;
          if (size > 512000) throw unavailable();
          chunks.push(part.value);
        }
      } finally {
        await reader.cancel();
      }
      const body = JSON.parse(Buffer.concat(chunks).toString("utf8"));
      if (body.version !== 1 || body.organization_id !== c.organization_id)
        throw unavailable();
      return body;
    } catch {
      throw unavailable();
    }
  }

  async status(user: Account, org: string) {
    await this.access.require(user, org, "configuration.view");
    const c = await this.configuration(org);
    if (!c)
      return {
        connected: false,
        message:
          "Dedicated ExamElite access has not been configured for this organisation.",
      };
    const body = await this.request(c, org, "status");
    if (
      !Array.isArray(body.linked_learners) ||
      body.linked_learners.length > 1000 ||
      body.linked_learners.some(
        (id: unknown) =>
          typeof id !== "string" ||
          !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/.test(
            id,
          ),
      )
    )
      throw unavailable();
    const a = await this.access.resolve(user, org);
    if (a.permissions.includes("learners.view"))
      await this.access.require(user, org, "learners.view");
    const linkedLearners =
      a.permissions.includes("learners.view") && a.scope_type === "organisation"
        ? (
            await this.db.query(
              "SELECT id,name,code FROM learners WHERE organisation_id=$1 AND id=ANY($2::uuid[]) AND NOT archived ORDER BY name,id",
              [org, body.linked_learners],
            )
          ).rows
        : [];
    return { connected: true, linkedLearners };
  }

  async list(
    user: Account,
    org: string,
    after: string = "0",
    learner?: string,
  ) {
    await this.access.require(user, org, "configuration.view");
    if (!/^\d{1,15}$/.test(after))
      throw new BadRequestException("Invalid page cursor.");
    if (learner) await this.learners.detail(user, org, learner);
    const c = await this.configuration(org);
    if (!c) throw unavailable();
    const data = await this.request(
      c,
      org,
      learner
        ? `learners/${encodeURIComponent(learner)}/results?after=${after}`
        : `exams?after=${after}`,
    );
    if (
      !Array.isArray(data.items) ||
      data.items.length > 50 ||
      (data.next !== null &&
        (!Number.isSafeInteger(data.next) || data.next <= Number(after))) ||
      (learner && data.learner_id !== learner)
    )
      throw unavailable();
    // Allowlist response fields; never forward arbitrary provider data to the browser.
    const items = data.items.map((r: any) => {
      if (!r || !Number.isSafeInteger(r.id) || r.id <= Number(after))
        throw unavailable();
      if (learner) {
        if (
          !Number.isSafeInteger(r.exam_id) ||
          typeof r.exam_name !== "string" ||
          r.exam_name.length > 1000 ||
          !["Pass", "Fail"].includes(r.result) ||
          !Number.isFinite(Number(r.percent)) ||
          typeof r.end_time !== "string"
        )
          throw unavailable();
        return {
          id: r.id,
          exam_id: r.exam_id,
          exam_name: r.exam_name,
          percent: Number(r.percent),
          result: r.result,
          end_time: r.end_time,
        };
      }
      if (
        typeof r.name !== "string" ||
        r.name.length > 1000 ||
        !Number.isFinite(Number(r.duration)) ||
        ![r.start_date, r.end_date].every(
          (v) => v === null || typeof v === "string",
        )
      )
        throw unavailable();
      return {
        id: r.id,
        name: r.name,
        duration: Number(r.duration),
        start_date: r.start_date,
        end_date: r.end_date,
      };
    });
    return { items, next: data.next };
  }
}
