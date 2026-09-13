import {
  Injectable,
  ForbiddenException,
  BadRequestException,
  NotFoundException,
  ConflictException,
} from "@nestjs/common";
import { Database } from "./database.js";
import { AccessService } from "./access.service.js";
import { ExamEliteService } from "./examelite.service.js";
import { ExamWorkspaceService } from "./exam-workspace.service.js";
import type { Account } from "./identity.service.js";

@Injectable()
export class ExamContentService {
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
