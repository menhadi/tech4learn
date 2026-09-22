import {
  Injectable,
  ForbiddenException,
  BadRequestException,
  NotFoundException,
  ConflictException,
  ServiceUnavailableException,
  GoneException,
} from "@nestjs/common";
import { randomUUID } from "node:crypto";
import { examPlanFeatures, examPlanLimits } from "./exam-plan-fields.js";
import { Database } from "./database.js";
import { AccessService } from "./access.service.js";
import { ExamEliteService } from "./examelite.service.js";
import type { Account } from "./identity.service.js";

export const workspaceFeatures = [
  "subjects",
  "questions",
  "exams",
  "taking",
  "results",
] as const;
const entries = {
  subjects: "subjects",
  questions: "questions",
  exams: "exams",
  results: "results",
} as const;
@Injectable()
export class ExamWorkspaceService {
  constructor(
    private readonly db: Database,
    private readonly access: AccessService,
    private readonly remote: ExamEliteService,
  ) {}
  private id(id: string) {
    if (
      !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/.test(id)
    )
      throw new BadRequestException("Invalid record.");
  }
  private admin(user: Account) {
    if (!user.is_superadmin)
      throw new ForbiddenException(
        "Only superadmin can restrict exam features.",
      );
  }
  private async config() {
    const c = await this.remote.configuration("_platform");
    if (!c?.central)
      throw new ServiceUnavailableException(
        "Central ExamElite setup is required.",
      );
    return c;
  }
  private async organisation(org: string) {
    this.id(org);
    const row = (
      await this.db.query<any>(
        "SELECT id,name FROM organisations WHERE id=$1",
        [org],
      )
    ).rows[0];
    if (!row) throw new NotFoundException();
    return row;
  }
  private async rules(org: string) {
    return (
      (
        await this.db.query<any>(
          "SELECT restrictions,revision FROM examelite_workspaces WHERE organisation_id=$1",
          [org],
        )
      ).rows[0] ?? { restrictions: [], revision: 0 }
    );
  }
  async status(user: Account, org: string) {
    if (user.is_superadmin) await this.organisation(org);
    else await this.access.require(user, org, "exams.manage");
    const rules = await this.rules(org);
    return { ...rules, features: workspaceFeatures, defaults: "all" };
  }
  async capabilities(
    user: Account,
    org: string,
    query: Record<string, unknown>,
  ) {
    const authorize = async () => {
      this.admin(user);
      await this.organisation(org);
      const current = await this.db.query<{ is_superadmin: boolean }>(
        "SELECT is_superadmin FROM users WHERE id=$1",
        [user.id],
      );
      if (!current.rows[0]?.is_superadmin) throw new ForbiddenException();
    };
    await authorize();
    if (Object.keys(query).length)
      throw new BadRequestException("Unexpected catalogue filters.");
    const before = await this.rules(org);
    const response = await this.remote.request(
      await this.config(),
      org,
      `workspace/${org}/capabilities`,
    );
    await authorize();
    const after = await this.rules(org);
    if (
      before.revision !== after.revision ||
      response.revision !== after.revision
    )
      throw new ConflictException("Exam access changed. Reload the catalogue.");
    const flags = (items: unknown, expected?: readonly string[]) => {
      if (!Array.isArray(items) || !items.length || items.length > 100)
        throw new ServiceUnavailableException(
          "Invalid exam capability catalogue.",
        );
      const seen = new Set<string>();
      const result = items.map((item) => {
        if (
          !item ||
          typeof item.key !== "string" ||
          !/^[a-z][a-z0-9_]{0,63}$/.test(item.key) ||
          typeof item.enabled !== "boolean" ||
          seen.has(item.key) ||
          (expected && !expected.includes(item.key))
        )
          throw new ServiceUnavailableException(
            "Invalid exam capability catalogue.",
          );
        seen.add(item.key);
        return { key: item.key as string, enabled: item.enabled as boolean };
      });
      if (
        expected &&
        (seen.size !== expected.length ||
          result.some(
            (item) => item.enabled === after.restrictions.includes(item.key),
          ))
      )
        throw new ConflictException(
          "Exam access is not synchronised. Reload its saved settings.",
        );
      return result;
    };
    return {
      revision: after.revision,
      native_features: flags(response.native_features),
      workspace_features: flags(response.workspace_features, workspaceFeatures),
    };
  }
  private async planAdmin(user: Account, org: string) {
    this.admin(user);
    await this.organisation(org);
    const current = await this.db.query<{ is_superadmin: boolean }>(
      "SELECT is_superadmin FROM users WHERE id=$1",
      [user.id],
    );
    if (!current.rows[0]?.is_superadmin) throw new ForbiddenException();
  }
  async plans(user: Account, org: string, query: Record<string, unknown>) {
    await this.planAdmin(user, org);
    const after = query.after ?? "0";
    if (
      Object.keys(query).some((key) => key !== "after") ||
      typeof after !== "string" ||
      !/^(0|[1-9][0-9]{0,14})$/.test(after)
    )
      throw new BadRequestException("Invalid plan cursor.");
    const response = await this.remote.request(
      await this.config(),
      org,
      `workspace/${org}/plans?after=${after}`,
    );
    await this.planAdmin(user, org);
    const invalid = () =>
      new ServiceUnavailableException("Invalid exam plan catalogue.");
    if (
      typeof response.assignment_revision !== "string" ||
      !/^[a-f0-9]{64}$/.test(response.assignment_revision) ||
      !Array.isArray(response.items) ||
      response.items.length > 50
    )
      throw invalid();
    let previous = Number(after),
      selected = 0;
    const items = response.items.map((item: any) => {
      if (
        !item ||
        !Number.isSafeInteger(item.id) ||
        item.id <= previous ||
        item.id > 999999999999999 ||
        typeof item.name !== "string" ||
        !item.name.trim() ||
        item.name.length > 255 ||
        typeof item.selected !== "boolean" ||
        typeof item.revision !== "string" ||
        !/^[a-f0-9]{64}$/.test(item.revision)
      )
        throw invalid();
      previous = item.id;
      if (item.selected && ++selected > 1) throw invalid();
      return {
        id: item.id as number,
        name: item.name as string,
        selected: item.selected as boolean,
        revision: item.revision as string,
      };
    });
    if (
      response.next !== null &&
      (items.length !== 50 || response.next !== String(previous))
    )
      throw invalid();
    return {
      assignment_revision: response.assignment_revision as string,
      items,
      next: response.next as string | null,
    };
  }
  async planFields(user: Account, org: string, query: Record<string, unknown>) {
    await this.planAdmin(user, org);
    if (Object.keys(query).length)
      throw new BadRequestException("Invalid plan field request.");
    return { features: [...examPlanFeatures], limits: [...examPlanLimits] };
  }
  async createPlan(
    user: Account,
    org: string,
    body: Record<string, unknown>,
    query: Record<string, unknown>,
  ) {
    await this.planAdmin(user, org);
    const invalid = () =>
      new BadRequestException("Choose valid plan settings.");
    if (
      Object.keys(query).length ||
      Object.keys(body).length !== 2 ||
      Object.keys(body).some(
        (key) => !["request_id", "fields"].includes(key),
      ) ||
      typeof body.request_id !== "string" ||
      !body.fields ||
      typeof body.fields !== "object" ||
      Array.isArray(body.fields)
    )
      throw invalid();
    this.id(body.request_id);
    const fields = this.planValues(body.fields, true);
    const config = await this.config();
    await this.planAdmin(user, org);
    const response = await this.remote.request(config, org, "central/plans", {
      request_id: body.request_id,
      actor_id: user.id,
      fields,
    });
    await this.planAdmin(user, org);
    if (response.saved === false && response.conflict === true)
      throw new ConflictException(
        "Plan request changed. Reload before creating another plan.",
      );
    if (
      response.saved !== true ||
      !Number.isSafeInteger(response.plan_id) ||
      response.plan_id < 1 ||
      response.plan_id > 999999999999999 ||
      response.name !== fields.name ||
      typeof response.revision !== "string" ||
      !/^[a-f0-9]{64}$/.test(response.revision)
    )
      throw new ServiceUnavailableException(
        "Plan creation was not confirmed. Retry the same request.",
      );
    return {
      saved: true,
      plan_id: response.plan_id as number,
      name: response.name as string,
      revision: response.revision as string,
    };
  }
  private planValues(input: unknown, requireName: boolean) {
    const invalid = () =>
      new BadRequestException("Choose valid plan settings.");
    if (!input || typeof input !== "object" || Array.isArray(input))
      throw invalid();
    const fields = input as Record<string, unknown>;
    const featureKeys = examPlanFeatures.map((key) => `feature_${key}`);
    const limitKeys = examPlanLimits.map((key) => `limit_${key}`);
    const allowed = [
      "name",
      "price",
      "billing_cycle",
      "status",
      ...featureKeys,
      ...limitKeys,
    ];
    if (
      Object.keys(fields).some((key) => !allowed.includes(key)) ||
      !Object.keys(fields).length ||
      ((requireName || fields.name !== undefined) &&
        (typeof fields.name !== "string" ||
          !fields.name.trim() ||
          fields.name !== fields.name.trim() ||
          fields.name.length > 255)) ||
      JSON.stringify(fields).length > 16384
    )
      throw invalid();
    for (const [key, value] of Object.entries(fields)) {
      if (
        (featureKeys.includes(key) || key === "status") &&
        typeof value !== "boolean"
      )
        throw invalid();
      if (
        limitKeys.includes(key) &&
        value !== null &&
        (!Number.isSafeInteger(value) ||
          Number(value) < 0 ||
          Number(value) > 1000000000)
      )
        throw invalid();
      if (
        key === "price" &&
        (typeof value !== "string" ||
          !/^(0|[1-9][0-9]{0,7})(\.[0-9]{1,2})?$/.test(value))
      )
        throw invalid();
      if (
        key === "billing_cycle" &&
        (typeof value !== "string" ||
          !["monthly", "yearly", "lifetime"].includes(value))
      )
        throw invalid();
    }
    return { ...fields };
  }
  async centralAiSettings(user: Account, org: string, query: Record<string, unknown>) {
    await this.planAdmin(user, org);
    if (Object.keys(query).length) throw new BadRequestException("Invalid AI settings request.");
    const response = await this.remote.request(await this.config(), org, "central/ai-settings");
    await this.planAdmin(user, org);
    const invalid = () => new ServiceUnavailableException("Invalid central AI settings response.");
    const codes = ["google", "openai", "deepseek", "anthropic"];
    const tasks = ["translation", "academic_review", "source_text_audit", "image_audit", "answer_explanation", "question_generation", "question_regeneration", "content_seo", "subjective_assessment"];
    const priority = (value: unknown): string[] => {
      if (!Array.isArray(value) || !value.length || value.length > 4 || value.some(code => !codes.includes(code)) || new Set(value).size !== value.length) throw invalid();
      return value;
    };
    const model = (value: unknown): string | null => {
      if (value === null) return null;
      if (typeof value !== "string" || !value.length || Buffer.byteLength(value, "utf8") > 120 || /[\x00-\x1f\x7f]/.test(value)) throw invalid();
      return value;
    };
    if (!response || !Array.isArray(response.providers) || response.providers.length !== 4) throw invalid();
    const seen = new Set<string>();
    const providers = response.providers.map((provider: any) => {
      if (!provider || !codes.includes(provider.code) || seen.has(provider.code) || typeof provider.credential_saved !== "boolean") throw invalid();
      seen.add(provider.code);
      return { code: provider.code, credential_saved: provider.credential_saved, configured_model: model(provider.configured_model), configured_vision_model: model(provider.configured_vision_model) };
    });
    if (!response.task_priorities || typeof response.task_priorities !== "object" || Array.isArray(response.task_priorities)) throw invalid();
    return { providers, priority: priority(response.priority), task_priorities: Object.fromEntries(tasks.map(task => [task, priority(response.task_priorities[task])])) };
  }
  async centralPlans(
    user: Account,
    org: string,
    query: Record<string, unknown>,
  ) {
    await this.planAdmin(user, org);
    const after = query.after ?? "0";
    if (
      Object.keys(query).some((key) => key !== "after") ||
      typeof after !== "string" ||
      !/^(0|[1-9][0-9]{0,14})$/.test(after)
    )
      throw new BadRequestException("Invalid plan cursor.");
    const response = await this.remote.request(
      await this.config(),
      org,
      `central/plans?after=${after}`,
    );
    await this.planAdmin(user, org);
    const invalid = () =>
      new ServiceUnavailableException("Invalid central plan catalogue.");
    if (!Array.isArray(response.items) || response.items.length > 50)
      throw invalid();
    let previous = Number(after);
    const items = response.items.map((p: any) => {
      if (
        !p ||
        !Number.isSafeInteger(p.id) ||
        p.id <= previous ||
        p.id > 999999999999999 ||
        typeof p.name !== "string" ||
        !p.name.trim() ||
        p.name.length > 255 ||
        typeof p.active !== "boolean" ||
        typeof p.is_default !== "boolean" ||
        typeof p.revision !== "string" ||
        !/^[a-f0-9]{64}$/.test(p.revision)
      )
        throw invalid();
      previous = p.id;
      return {
        id: p.id as number,
        name: p.name as string,
        active: p.active as boolean,
        is_default: p.is_default as boolean,
        revision: p.revision as string,
      };
    });
    if (
      response.next !== null &&
      (items.length !== 50 || response.next !== String(previous))
    )
      throw invalid();
    return { items, next: response.next as string | null };
  }
  async centralPlan(
    user: Account,
    org: string,
    id: string,
    query: Record<string, unknown>,
  ) {
    await this.planAdmin(user, org);
    if (!/^[1-9][0-9]{0,14}$/.test(id) || Object.keys(query).length)
      throw new BadRequestException("Invalid plan.");
    const response = await this.remote.request(
      await this.config(),
      org,
      `central/plans/${id}`,
    );
    await this.planAdmin(user, org);
    const invalid = () =>
      new ServiceUnavailableException("Invalid native plan details.");
    if (
      response.plan_id !== Number(id) ||
      typeof response.revision !== "string" ||
      !/^[a-f0-9]{64}$/.test(response.revision) ||
      typeof response.is_default !== "boolean" ||
      !Number.isSafeInteger(response.assigned_organisations) ||
      response.assigned_organisations < 0 ||
      response.assigned_organisations > 1000000000
    )
      throw invalid();
    let fields;
    try {
      fields = this.planValues(response.fields, true);
    } catch {
      throw invalid();
    }
    if (
      Object.keys(fields).length !==
      4 + examPlanFeatures.length + examPlanLimits.length
    )
      throw invalid();
    return {
      plan_id: Number(id),
      revision: response.revision as string,
      fields,
      is_default: response.is_default as boolean,
      assigned_organisations: response.assigned_organisations as number,
    };
  }
  async updatePlan(
    user: Account,
    org: string,
    id: string,
    body: Record<string, unknown>,
    query: Record<string, unknown>,
  ) {
    await this.planAdmin(user, org);
    if (
      !/^[1-9][0-9]{0,14}$/.test(id) ||
      Object.keys(query).length ||
      Object.keys(body).length !== 3 ||
      Object.keys(body).some(
        (key) => !["request_id", "revision", "fields"].includes(key),
      ) ||
      typeof body.request_id !== "string" ||
      typeof body.revision !== "string" ||
      !/^[a-f0-9]{64}$/.test(body.revision)
    )
      throw new BadRequestException("Reload the plan before editing.");
    this.id(body.request_id);
    const fields = this.planValues(body.fields, false);
    const config = await this.config();
    await this.planAdmin(user, org);
    const response = await this.remote.request(
      config,
      org,
      `central/plans/${id}`,
      {
        request_id: body.request_id,
        revision: body.revision,
        actor_id: user.id,
        fields,
      },
    );
    await this.planAdmin(user, org);
    if (response.saved === false && response.conflict === true)
      throw new ConflictException("Plan changed. Reload before editing.");
    if (
      response.saved !== true ||
      response.plan_id !== Number(id) ||
      typeof response.name !== "string" ||
      !response.name.trim() ||
      response.name.length > 255 ||
      (fields.name !== undefined && response.name !== fields.name) ||
      typeof response.revision !== "string" ||
      !/^[a-f0-9]{64}$/.test(response.revision)
    )
      throw new ServiceUnavailableException(
        "Plan update was not confirmed. Retry the same request.",
      );
    return {
      saved: true,
      plan_id: Number(id),
      name: response.name as string,
      revision: response.revision as string,
    };
  }
  async assignPlan(
    user: Account,
    org: string,
    body: Record<string, unknown>,
    query: Record<string, unknown>,
  ) {
    await this.planAdmin(user, org);
    const keys = [
      "request_id",
      "plan_id",
      "assignment_revision",
      "plan_revision",
    ];
    if (
      Object.keys(query).length ||
      Object.keys(body).length !== keys.length ||
      Object.keys(body).some((key) => !keys.includes(key)) ||
      !Number.isSafeInteger(body.plan_id) ||
      (body.plan_id as number) < 1 ||
      (body.plan_id as number) > 999999999999999 ||
      typeof body.request_id !== "string"
    )
      throw new BadRequestException("Invalid plan assignment.");
    this.id(body.request_id);
    for (const key of ["assignment_revision", "plan_revision"])
      if (
        typeof body[key] !== "string" ||
        !/^[a-f0-9]{64}$/.test(body[key] as string)
      )
        throw new BadRequestException("Reload the plan before assigning it.");
    const config = await this.config();
    await this.planAdmin(user, org);
    const response = await this.remote.request(
      config,
      org,
      `workspace/${org}/plan`,
      { ...body, actor_id: user.id },
    );
    await this.planAdmin(user, org);
    if (response.saved === false && response.conflict === true)
      throw new ConflictException(
        "Organisation or plan changed. Reload before assigning.",
      );
    if (
      response.saved !== true ||
      response.plan_id !== body.plan_id ||
      typeof response.assignment_revision !== "string" ||
      !/^[a-f0-9]{64}$/.test(response.assignment_revision)
    )
      throw new ServiceUnavailableException(
        "Plan assignment was not confirmed. Retry the same request.",
      );
    return {
      saved: true,
      plan_id: response.plan_id as number,
      assignment_revision: response.assignment_revision as string,
    };
  }
  async students(user: Account, org: string, search: string) {
    await this.access.require(user, org, "exams.manage");
    await this.access.require(user, org, "learners.view");
    if (typeof search !== "string" || search.length > 120)
      throw new BadRequestException("Search is too long.");
    return (
      await this.db.query(
        "SELECT id,name,code FROM learners WHERE organisation_id=$1 AND NOT archived AND (name ILIKE $2 OR code ILIKE $2) ORDER BY name,id LIMIT 50",
        [org, "%" + search.replace(/[\\%_]/g, "\\$&") + "%"],
      )
    ).rows;
  }
  async restrict(user: Account, org: string, b: Record<string, unknown>) {
    this.admin(user);
    await this.organisation(org);
    if (
      !Array.isArray(b.restrictions) ||
      b.restrictions.length > workspaceFeatures.length ||
      b.restrictions.some((x) => !workspaceFeatures.includes(x as any)) ||
      !Number.isSafeInteger(b.revision)
    )
      throw new BadRequestException("Choose valid feature restrictions.");
    const restrictions = [...new Set(b.restrictions as string[])].sort();
    const c = await this.config();
    await this.db.transaction(async (sql) => {
      await sql.query("SELECT id FROM organisations WHERE id=$1 FOR UPDATE", [
        org,
      ]);
      const prior = (
        await sql.query<any>(
          "SELECT revision FROM examelite_workspaces WHERE organisation_id=$1",
          [org],
        )
      ).rows[0];
      if ((prior?.revision ?? 0) !== b.revision)
        throw new ConflictException(
          "Restrictions changed. Reload before saving.",
        );
      // Provider applies restrictions to existing sessions. On a transport failure, do not claim a saved update.
      await this.remote.request(c, org, `workspace/${org}/restrictions`, {
        restrictions,
        revision: Number(b.revision) + 1,
      });
      await sql.query(
        `INSERT INTO examelite_workspaces(organisation_id,restrictions,revision) VALUES($1,$2,1)
        ON CONFLICT(organisation_id) DO UPDATE SET restrictions=$2,revision=examelite_workspaces.revision+1,updated_at=now()`,
        [org, restrictions],
      );
      await sql.query(
        "INSERT INTO audit_events(id,actor_id,organisation_id,action) VALUES($1,$2,$3,$4)",
        [randomUUID(), user.id, org, "examelite.workspace.restricted"],
      );
    });
    return this.status(user, org);
  }
  async launch(
    user: Account,
    org: string,
    b: Record<string, unknown>,
    provisionOnly = false,
  ) {
    await this.access.require(user, org, "exams.manage");
    const organisation = await this.organisation(org),
      rules = await this.rules(org);
    const feature = String(b.feature);
    if (
      !workspaceFeatures.includes(feature as any) ||
      rules.restrictions.includes(feature)
    )
      throw new ForbiddenException("This exam feature is restricted.");
    let learner: Record<string, unknown> | undefined;
    if (feature === "taking") {
      this.id(String(b.learner));
      await this.access.require(user, org, "learners.view");
      learner = (
        await this.db.query(
          "SELECT id,name FROM learners WHERE organisation_id=$1 AND id=$2 AND NOT archived",
          [org, b.learner],
        )
      ).rows[0];
      if (!learner) throw new NotFoundException("Student not found.");
    }
    if (!provisionOnly)
      throw new GoneException(
        "External exam workspaces have been retired. Open Exams & results inside your organisation.",
      );
    const c = await this.config();
    const response = await this.remote.request(
      c,
      org,
      `workspace/${org}/launch`,
      {
        organisation_name: organisation.name,
        actor_id: user.id,
        actor_name: user.name,
        feature,
        entry:
          feature === "taking"
            ? "student/dashboard"
            : entries[feature as keyof typeof entries],
        restrictions: rules.restrictions,
        revision: rules.revision,
        learner,
        provision_only: provisionOnly,
      },
    );
    if (response.ready !== true)
      throw new ServiceUnavailableException(
        "Exam workspace provisioning failed.",
      );
    return { ready: true };
  }
}
