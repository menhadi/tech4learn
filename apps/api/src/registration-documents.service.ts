import { BadRequestException, ConflictException, Injectable, NotFoundException, ServiceUnavailableException } from "@nestjs/common";
import { randomUUID } from "node:crypto";
import { Database, type SqlClient } from "./database.js";
import { AccessService } from "./access.service.js";
import type { Account } from "./identity.service.js";
import { uuid } from "./security.js";
import { registrationDocument } from "./registration-document.js";
import { providerConfig, runRegistrationVision, visionProviders } from "./vision-providers.js";

/** Transient document processing. No image, extracted text or student is persisted. */
@Injectable()
export class RegistrationDocumentsService {
  constructor(private readonly db: Database, private readonly access: AccessService) {}
  async providers(user: Account, org: string, group: string) {
    await this.target(this.db,user,org,uuid(group));
    return visionProviders.map(p=>({id:p.id,label:p.label,configured:providerConfig(p.id).configured}));
  }
  private async target(sql: SqlClient, user: Account, org: string, group: string) {
    const a = await this.access.require(user, org, "learners.create", sql);
    await this.access.require(user, org, "learners.view", sql);
    // The entire page may contain contacts. Redaction after a provider call is
    // insufficient: require contact authority before transmitting any bytes.
    await this.access.require(user, org, "learners.contacts", sql);
    const row = (await sql.query(
      `SELECT g.id FROM learning_groups g JOIN centres c ON c.id=g.centre_id AND c.organisation_id=g.organisation_id
       WHERE g.organisation_id=$1 AND g.id=$2 AND NOT g.archived AND NOT c.archived
       AND ($3='organisation' OR ($3='centres' AND g.centre_id=ANY($4::uuid[])) OR ($3='groups' AND g.id=ANY($4::uuid[])))`,
      [org, group, a.scope_type, a.scope_ids])).rows[0];
    if (!row) throw new NotFoundException("Choose an active section in your scope.");
  }
  async extract(user: Account, org: string, b: Record<string, unknown>) {
    if (Object.keys(b).some(k => !["group_id", "provider", "document", "attested"].includes(k)) || b.attested !== true)
      throw new BadRequestException("Confirm this registration page may be processed by the selected provider.");
    const group = uuid(b.group_id as string), config = providerConfig(b.provider);
    await this.target(this.db, user, org, group);
    if (!config.configured) throw new BadRequestException("This registration provider is not configured.");
    const image = registrationDocument(b.document), id = randomUUID();
    await this.db.transaction(async sql => {
      await this.access.lock(sql, org);
      await this.target(sql, user, org, group);
      await sql.query("UPDATE registration_document_runs SET status='failed' WHERE organisation_id=$1 AND status='processing' AND created_at<now()-interval '2 minutes'", [org]);
      if ((await sql.query("SELECT id FROM registration_document_runs WHERE organisation_id=$1 AND status='processing'", [org])).rows.length)
        throw new ConflictException("A registration page is already being processed. Try again shortly.");
      const count = (await sql.query<{ n: string }>("SELECT count(*) AS n FROM registration_document_runs WHERE organisation_id=$1 AND created_at>=date_trunc('day',now() AT TIME ZONE 'UTC') AT TIME ZONE 'UTC'", [org])).rows[0];
      if (Number(count.n) >= 20) throw new ConflictException("The organisation has reached its daily limit of 20 registration pages.");
      await sql.query("INSERT INTO registration_document_runs(id,organisation_id,actor_id,group_id,provider,status) VALUES($1,$2,$3,$4,$5,'processing')", [id,org,user.id,group,config.id]);
      await this.access.audit(sql,user,org,"learners.registration_page_requested",{id,groupId:group,provider:config.id});
    });
    try {
      const draft = await runRegistrationVision(config.id, image);
      await this.db.transaction(async sql => {
        await this.access.lock(sql,org);
        await this.target(sql,user,org,group);
        const result=await sql.query("UPDATE registration_document_runs SET status='completed' WHERE organisation_id=$1 AND id=$2 AND status='processing' RETURNING id",[org,id]);
        if (!result.rows.length) throw new ConflictException("This page request expired. No student was saved.");
      });
      return { id, ...draft.result };
    } catch {
      await this.db.query("UPDATE registration_document_runs SET status='failed' WHERE organisation_id=$1 AND id=$2 AND status='processing'",[org,id]);
      throw new ServiceUnavailableException("Registration page processing could not complete. No student was saved.");
    } finally { image.fill(0); }
  }
}
