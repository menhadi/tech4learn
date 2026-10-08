import { BadRequestException, ConflictException, ForbiddenException, Injectable, NotFoundException } from "@nestjs/common";
import { Database, type SqlClient } from "./database.js";
import { AccessService } from "./access.service.js";
import type { Account } from "./identity.service.js";
import { uuid } from "./security.js";
import { LearnersService } from "./learners.service.js";
import { randomUUID } from "node:crypto";
import { provisionAttendanceOrganisation } from "./foundation-organisation-provision.js";
import { provisionAttendanceAdministrator } from "./foundation-staff-provision.js";
import {verifyStudentDeliveryReceipt} from "./student-delivery-proof.js";

function nativeId(value: unknown): string {
  if (typeof value !== "string" || !/^[1-9][0-9]{0,14}$/.test(value))
    throw new BadRequestException("Use a positive native ID as a string.");
  return value;
}
function change(body: Record<string, unknown>) {
  if (typeof body.active !== "boolean" || !Number.isSafeInteger(body.version) || Number(body.version) < 1)
    throw new BadRequestException("Provide the current version and active status.");
}
function recordId(value: unknown): string {
  if (typeof value !== "string") throw new BadRequestException("Provide a record ID.");
  return uuid(value);
}
@Injectable()
export class FoundationService {
  constructor(private readonly db: Database, private readonly access: AccessService, private readonly learners: LearnersService) {}
  async provisionCompanion(actor: Account, body: Record<string, unknown>) {
    await this.platform(actor, this.db);
    const native = nativeId(body.nativeOrganisationId);
    if (typeof body.name !== "string") throw new BadRequestException("Provide the native organisation name.");
    return provisionAttendanceOrganisation(this.db, actor.id, native, body.name);
  }
  async provisionAttendanceAdmin(actor: Account, body: Record<string, unknown>) {
    await this.platform(actor, this.db);
    const native = nativeId(body.nativeOrganisationId), user = nativeId(body.nativeUserId);
    if (body.nativeRole !== "admin" || typeof body.email !== "string" || typeof body.name !== "string" || typeof body.password !== "string")
      throw new BadRequestException("Provide a reviewed native administrator and its account fields.");
    return provisionAttendanceAdministrator(this.db, actor.id, native, user, body.email, body.name, body.password);
  }
  async platformStaff(actor: Account, nativeValue: string) {
    await this.platform(actor,this.db);
    return (await this.db.query("SELECT native_user_id::text,user_id,active,version FROM foundation_platform_staff WHERE native_organisation_id=$1 ORDER BY native_user_id LIMIT 500",[nativeId(nativeValue)])).rows;
  }
  private async realm(sql: SqlClient,native: string) {
    const row=(await sql.query<{kind:string}>("SELECT kind FROM foundation_realms WHERE native_id=$1",[native])).rows[0];
    if(row && row.kind!=="platform") throw new ConflictException("An attendance organisation cannot become a platform realm.");
  }
  private async platformAudit(sql: SqlClient,actor: Account,action: string,details: unknown) {
    await sql.query("INSERT INTO audit_events(id,actor_id,organisation_id,action,details) VALUES($1,$2,NULL,$3,$4)",[randomUUID(),actor.id,action,JSON.stringify(details)]);
  }
  async linkPlatformStaff(actor: Account,nativeValue: string,body: Record<string,unknown>) {
    const native=nativeId(nativeValue),user=nativeId(body.nativeUserId),target=recordId(body.userId);
    return this.db.transaction(async sql=>{
      await this.platform(actor,sql);await this.platform({id:target},sql);
      await this.realm(sql,native);
      const result=await sql.query("INSERT INTO foundation_platform_staff(native_organisation_id,native_user_id,user_id) VALUES($1,$2,$3) ON CONFLICT DO NOTHING RETURNING native_user_id::text,user_id,active,version",[native,user,target]).catch(error=>{
        if(error.code==="23514")throw new ConflictException("Native identity realm conflicts with an attendance organisation.");
        throw error;
      });
      if(!result.rows.length) throw new ConflictException("Platform identities are immutable and already linked.");
      await this.platformAudit(sql,actor,"foundation.platform_staff_linked",{nativeOrganisationId:native,nativeUserId:user,userId:target});
      return result.rows[0];
    });
  }
  async setPlatformStaff(actor: Account,nativeValue: string,userValue: string,body: Record<string,unknown>) {
    const native=nativeId(nativeValue),user=nativeId(userValue);change(body);
    return this.db.transaction(async sql=>{
      await this.platform(actor,sql);
      const link=(await sql.query<{user_id:string}>("SELECT user_id FROM foundation_platform_staff WHERE native_organisation_id=$1 AND native_user_id=$2 FOR UPDATE",[native,user])).rows[0];
      if(!link)throw new NotFoundException("Platform identity is not linked.");
      if(body.active)await this.platform({id:link.user_id},sql);
      const result=await sql.query("UPDATE foundation_platform_staff SET active=$3,version=version+1 WHERE native_organisation_id=$1 AND native_user_id=$2 AND version=$4 RETURNING native_user_id::text,user_id,active,version",[native,user,body.active,body.version]);
      if(!result.rows.length)throw new ConflictException("The link changed; reload its current version.");
      await this.platformAudit(sql,actor,"foundation.platform_staff_status",{nativeOrganisationId:native,nativeUserId:user,active:body.active});
      return result.rows[0];
    });
  }
  async platformIdentity(actor: Account,nativeValue: unknown,userValue?: string) {
    const native=nativeId(nativeValue),user=userValue===undefined?undefined:nativeId(userValue);
    return this.db.transaction(async sql=>{
      await this.platform(actor,sql);
      const link=(await sql.query<{native_user_id:string;version:number}>("SELECT native_user_id::text,version FROM foundation_platform_staff WHERE native_organisation_id=$1 AND user_id=$2 AND active"+(user===undefined?"":" AND native_user_id=$3"),user===undefined?[native,actor.id]:[native,actor.id,user])).rows[0];
      if(!link)throw new NotFoundException("Platform identity is not linked.");
      return {nativeOrganisationId:native,nativeUserId:link.native_user_id,userId:actor.id,version:link.version,realm:"platform"};
    });
  }
  async learnerLinks(actor: Account, nativeValue: string) {
    await this.platform(actor, this.db);
    const native = nativeId(nativeValue);
    await this.organisation(this.db, native);
    return (await this.db.query("SELECT native_student_id::text,learner_id,active,version FROM foundation_learners WHERE native_organisation_id=$1 ORDER BY native_student_id LIMIT 500", [native])).rows;
  }
  async linkLearner(actor: Account, nativeValue: string, body: Record<string, unknown>) {
    const native = nativeId(nativeValue), student = nativeId(body.nativeStudentId), learner = recordId(body.learnerId);
    return this.db.transaction(async sql => {
      await this.platform(actor, sql);
      const organisation = await this.organisation(sql, native, true);
      if (!organisation.active) throw new ForbiddenException("Organisation link is inactive.");
      await this.access.lock(sql, organisation.organisation_id);
      await this.learners.linkedIdentity(actor, organisation.organisation_id, learner, sql);
      const stored=(await sql.query<{native_student_id:string;learner_id:string;active:boolean;version:number}>(
        `SELECT native_student_id::text,learner_id,active,version FROM foundation_learners
         WHERE native_organisation_id=$1 AND (native_student_id=$2 OR learner_id=$3) FOR UPDATE`,[native,student,learner])).rows[0];
      if(stored) {
        if(!stored.active || stored.native_student_id!==student || stored.learner_id!==learner)
          throw new ConflictException("Existing learner links cannot be reassigned or repaired by retry.");
        return stored;
      }
      // Serialise manual linking with queued delivery using the organisation
      // lock. A link cannot appear between a delivery snapshot and native apply.
      if((await sql.query(`SELECT learner_id FROM foundation_student_deliveries
        WHERE native_organisation_id=$1 AND organisation_id=$2 AND learner_id=$3 FOR SHARE`,
        [native,organisation.organisation_id,learner])).rows.length)
        throw new ConflictException("This student uses enrolment delivery. A new manual identity link requires review.");
      const result = await sql.query("INSERT INTO foundation_learners(native_organisation_id,native_student_id,organisation_id,learner_id) VALUES($1,$2,$3,$4) ON CONFLICT DO NOTHING RETURNING native_student_id::text,learner_id,active,version", [native,student,organisation.organisation_id,learner]);
      if (!result.rows.length) {
        const existing=(await sql.query<{native_student_id:string;learner_id:string;active:boolean;version:number}>("SELECT native_student_id::text,learner_id,active,version FROM foundation_learners WHERE native_organisation_id=$1 AND native_student_id=$2 FOR UPDATE",[native,student])).rows[0];
        if (!existing?.active || existing.learner_id!==learner) throw new ConflictException("A learner is already linked or revoked; existing links cannot be reassigned or repaired by retry.");
        return existing;
      }
      await this.access.audit(sql,actor,organisation.organisation_id,"foundation.learner_linked",{nativeOrganisationId:native,nativeStudentId:student,learnerId:learner});
      return result.rows[0];
    });
  }
  async setLearner(actor: Account, nativeValue: string, studentValue: string, body: Record<string, unknown>) {
    const native = nativeId(nativeValue), student = nativeId(studentValue); change(body);
    return this.db.transaction(async sql => {
      await this.platform(actor,sql);
      const organisation = await this.organisation(sql,native,true);
      const link = (await sql.query<{learner_id:string}>("SELECT learner_id FROM foundation_learners WHERE native_organisation_id=$1 AND native_student_id=$2",[native,student])).rows[0];
      if (!link) throw new NotFoundException("Learner link not found.");
      if (body.active) {
        if (!organisation.active) throw new ForbiddenException("Organisation link is inactive.");
        await this.access.lock(sql,organisation.organisation_id);
        await this.learners.linkedIdentity(actor,organisation.organisation_id,link.learner_id,sql);
      }
      const result=await sql.query("UPDATE foundation_learners SET active=$3,version=version+1 WHERE native_organisation_id=$1 AND native_student_id=$2 AND version=$4 RETURNING native_student_id::text,learner_id,active,version",[native,student,body.active,body.version]);
      if (!result.rows.length) throw new ConflictException("The link changed; reload its current version.");
      await this.access.audit(sql,actor,organisation.organisation_id,"foundation.learner_status",{nativeOrganisationId:native,nativeStudentId:student,active:body.active});
      return result.rows[0];
    });
  }
  async learnerIdentity(actor: Account, nativeValue: string, userValue: string, studentValue: string) {
    const native=nativeId(nativeValue), user=nativeId(userValue), student=nativeId(studentValue);
    return this.db.transaction(async sql => {
      const organisation=await this.organisation(sql,native,true);
      const mapping=(await sql.query<{learner_id:string;version:number}>(`SELECT l.learner_id,l.version FROM foundation_learners l JOIN foundation_staff s ON s.native_organisation_id=l.native_organisation_id WHERE l.native_organisation_id=$1 AND l.native_student_id=$2 AND s.native_user_id=$3 AND s.user_id=$4 AND l.active AND s.active`,[native,student,user,actor.id])).rows[0];
      if (!organisation.active || !mapping) throw new NotFoundException("Learner identity is not linked.");
      await this.access.lock(sql,organisation.organisation_id);
      const learner=await this.learners.linkedIdentity(actor,organisation.organisation_id,mapping.learner_id,sql);
      return {nativeOrganisationId:native,nativeUserId:user,nativeStudentId:student,organisationId:organisation.organisation_id,learnerId:learner,version:mapping.version};
    });
  }
  async studentDeliverySnapshot(actor: Account, nativeValue: string, userValue: string, learnerValue: string) {
    const native=nativeId(nativeValue), user=nativeId(userValue), learner=recordId(learnerValue);
    return this.db.transaction(async sql=>{
      const mapped=(await sql.query<{organisation_id:string}>(`SELECT f.organisation_id FROM foundation_organisations f
        JOIN foundation_staff s ON s.native_organisation_id=f.native_id
        WHERE f.native_id=$1 AND s.native_user_id=$2 AND s.user_id=$3 AND f.active AND s.active
        FOR SHARE OF f,s`,[native,user,actor.id])).rows[0];
      if(!mapped)throw new NotFoundException("Enrolment identity is not linked.");
      await this.access.lock(sql,mapped.organisation_id);
      const profile=await this.learners.nativeProfileSnapshot(actor,mapped.organisation_id,learner,sql);
      const delivery=(await sql.query<{revision:string;delivered_revision:string}>(`SELECT revision::text,delivered_revision::text FROM foundation_student_deliveries
        WHERE native_organisation_id=$1 AND organisation_id=$2 AND learner_id=$3 FOR SHARE`,
        [native,mapped.organisation_id,learner])).rows[0];
      if(!delivery)throw new NotFoundException("Student delivery has not been requested.");
      const revision=Number(delivery.revision);
      if(!Number.isSafeInteger(revision) || revision<1 || revision>=1000000000000000)
        throw new ConflictException("Student delivery revision cannot be processed.");
      const existing=(await sql.query<{active:boolean}>(`SELECT active FROM foundation_learners
        WHERE native_organisation_id=$1 AND organisation_id=$2 AND learner_id=$3 FOR SHARE`,
        [native,mapped.organisation_id,learner])).rows[0];
      // An operator's existing identity link is not permission to create/adopt a
      // second native profile. Only a previously acknowledged delivery owns it.
      const reviewRequired=!!existing && (Number(delivery.delivered_revision)===0 || (!existing.active&&!profile.archived));
      return {nativeOrganisationId:native,nativeUserId:user,organisationId:mapped.organisation_id,revision,reviewRequired,...profile};
    });
  }
  async acknowledgeStudentDelivery(actor:Account,nativeValue:string,userValue:string,learnerValue:string,body:Record<string,unknown>) {
    const native=nativeId(nativeValue),user=nativeId(userValue),learner=recordId(learnerValue);
    return this.db.transaction(async sql=>{
      const mapped=(await sql.query<{organisation_id:string}>(`SELECT f.organisation_id FROM foundation_organisations f
        JOIN foundation_staff s ON s.native_organisation_id=f.native_id
        WHERE f.native_id=$1 AND s.native_user_id=$2 AND s.user_id=$3 AND f.active AND s.active FOR SHARE OF f,s`,[native,user,actor.id])).rows[0];
      if(!mapped)throw new NotFoundException('Enrolment identity is not linked.');
      const org=mapped.organisation_id;await this.access.lock(sql,org);
      await this.access.require(actor,org,'learners.edit',sql);
      const profile=await this.learners.nativeProfileSnapshot(actor,org,learner,sql);
      if(typeof body.keyId!=='string' || !/^[a-f0-9]{64}$/.test(body.keyId))throw new BadRequestException('Provide a native delivery receipt.');
      const key=(await sql.query<{public_key:string}>('SELECT public_key FROM foundation_native_signers WHERE key_id=$1 AND active FOR SHARE',[body.keyId])).rows[0];
      if(!key)throw new ForbiddenException('Native delivery signer is unavailable.');
      const receipt=verifyStudentDeliveryReceipt(body,key.public_key);
      if(receipt.nativeOrganisationId!==native || receipt.nativeUserId!==user || receipt.learnerId!==learner)
        throw new ForbiddenException('Native delivery receipt does not match this actor and student.');
      const pending=(await sql.query<{revision:string}>(`SELECT revision::text FROM foundation_student_deliveries
        WHERE native_organisation_id=$1 AND organisation_id=$2 AND learner_id=$3 FOR UPDATE`,[native,org,learner])).rows[0];
      if(!pending || Number(pending.revision)!==receipt.revision)throw new ConflictException('Student delivery changed; retry the current revision.');
      await sql.query(`INSERT INTO foundation_learners(native_organisation_id,native_student_id,organisation_id,learner_id,active)
        VALUES($1,$2,$3,$4,$5) ON CONFLICT DO NOTHING`,[native,receipt.nativeStudentId,org,learner,!profile.archived]);
      const link=(await sql.query<{learner_id:string;native_student_id:string;active:boolean}>(`SELECT learner_id,native_student_id::text,active FROM foundation_learners
        WHERE native_organisation_id=$1 AND native_student_id=$2 FOR UPDATE`,[native,receipt.nativeStudentId])).rows[0];
      if(!link || link.learner_id!==learner || (!link.active && !profile.archived))
        throw new ConflictException('Student identity is already linked or revoked; delivery cannot reassign or reactivate it.');
      if(profile.archived && link.active)await sql.query(`UPDATE foundation_learners SET active=false,version=version+1
        WHERE native_organisation_id=$1 AND native_student_id=$2`,[native,receipt.nativeStudentId]);
      const changed=(await sql.query(`UPDATE foundation_student_deliveries SET delivered_revision=revision
        WHERE native_organisation_id=$1 AND learner_id=$2 AND delivered_revision<revision RETURNING revision`,[native,learner])).rows.length;
      if(changed)await this.access.audit(sql,actor,org,'foundation.student_delivered',{
        nativeOrganisationId:native,nativeStudentId:receipt.nativeStudentId,learnerId:learner,revision:receipt.revision});
      return {nativeStudentId:receipt.nativeStudentId,learnerId:learner,revision:receipt.revision,delivered:true};
    });
  }
  async studentDeliveryStatus(actor:Account,nativeValue:string,userValue:string,learnerValue:string) {
    const before=await this.studentDeliverySnapshot(actor,nativeValue,userValue,learnerValue);
    const row=(await this.db.query<{revision:string;delivered_revision:string;revoked:boolean}>(`SELECT d.revision::text,d.delivered_revision::text,
      EXISTS(SELECT 1 FROM foundation_learners l WHERE l.native_organisation_id=d.native_organisation_id
        AND l.organisation_id=d.organisation_id AND l.learner_id=d.learner_id AND NOT l.active) AS revoked
      FROM foundation_student_deliveries d WHERE d.native_organisation_id=$1 AND d.organisation_id=$2 AND d.learner_id=$3`,
      [before.nativeOrganisationId,before.organisationId,before.learnerId])).rows[0];
    const after=await this.studentDeliverySnapshot(actor,nativeValue,userValue,learnerValue);
    if(!row || before.revision!==after.revision || Number(row.revision)!==after.revision)
      throw new ConflictException('Student delivery changed; reload its status.');
    return {learnerId:after.learnerId,revision:after.revision,
      state:after.reviewRequired?'review_required':Number(row.delivered_revision)===after.revision?'delivered':'pending'};
  }
  private async platform(actor: Pick<Account,"id">, sql: SqlClient) {
    // Do not inherit platform authority from a native role or a stale caller object.
    const stored = await sql.query<{ is_superadmin: boolean }>("SELECT is_superadmin FROM users WHERE id=$1 FOR SHARE", [actor.id]);
    if (!stored.rows[0]?.is_superadmin) throw new ForbiddenException("Only platform superadmins can manage identity links.");
  }
  async list(actor: Account) {
    await this.platform(actor, this.db);
    return (await this.db.query("SELECT native_id::text, organisation_id, active, version FROM foundation_organisations ORDER BY native_id LIMIT 500")).rows;
  }
  async linkOrganisation(actor: Account, body: Record<string, unknown>) {
    const native = nativeId(body.nativeOrganisationId);
    const org = recordId(body.organisationId);
    return this.db.transaction(async sql => {
      await this.platform(actor, sql);
      await this.access.lock(sql, org);
      const result = await sql.query("INSERT INTO foundation_organisations(native_id,organisation_id) VALUES($1,$2) ON CONFLICT DO NOTHING RETURNING native_id::text,organisation_id,active,version", [native, org]).catch(error=>{
        if(error.code==="23514")throw new ConflictException("A platform identity realm cannot become an attendance organisation.");
        throw error;
      });
      if (!result.rows.length) throw new ConflictException("An identity is already linked; existing links cannot be reassigned.");
      await this.access.audit(sql, actor, org, "foundation.organisation_linked", { nativeOrganisationId: native });
      return result.rows[0];
    });
  }
  private async organisation(sql: SqlClient, native: string, lock = false) {
    const result = await sql.query<{ organisation_id: string; active: boolean; version: number }>(
      "SELECT organisation_id,active,version FROM foundation_organisations WHERE native_id=$1" + (lock ? " FOR UPDATE" : ""), [native]);
    if (!result.rows[0]) throw new NotFoundException("Organisation link not found.");
    return result.rows[0];
  }
  async setOrganisation(actor: Account, nativeValue: string, body: Record<string, unknown>) {
    const native = nativeId(nativeValue); change(body);
    return this.db.transaction(async sql => {
      await this.platform(actor, sql);
      const link = await this.organisation(sql, native, true);
      const result = await sql.query("UPDATE foundation_organisations SET active=$2,version=version+1 WHERE native_id=$1 AND version=$3 RETURNING native_id::text,organisation_id,active,version", [native, body.active, body.version]);
      if (!result.rows.length) throw new ConflictException("The link changed; reload its current version.");
      await this.access.audit(sql, actor, link.organisation_id, "foundation.organisation_status", { nativeOrganisationId: native, active: body.active });
      return result.rows[0];
    });
  }
  async staff(actor: Account, nativeValue: string) {
    await this.platform(actor, this.db);
    const native = nativeId(nativeValue);
    await this.organisation(this.db, native);
    return (await this.db.query("SELECT native_user_id::text,user_id,active,version FROM foundation_staff WHERE native_organisation_id=$1 ORDER BY native_user_id LIMIT 500", [native])).rows;
  }
  private async target(sql: SqlClient, id: string, org: string) {
    const result = await sql.query<Account>("SELECT id,email,name,is_superadmin FROM users WHERE id=$1", [id]);
    if (!result.rows[0]) throw new NotFoundException("Account not found.");
    await this.access.resolve(result.rows[0], org, sql);
  }
  async linkStaff(actor: Account, nativeValue: string, body: Record<string, unknown>) {
    const native = nativeId(nativeValue), user = nativeId(body.nativeUserId), target = recordId(body.userId);
    return this.db.transaction(async sql => {
      await this.platform(actor, sql);
      const link = await this.organisation(sql, native, true);
      if (!link.active) throw new ForbiddenException("Organisation link is inactive.");
      await this.target(sql, target, link.organisation_id);
      const result = await sql.query("INSERT INTO foundation_staff(native_organisation_id,native_user_id,user_id) VALUES($1,$2,$3) ON CONFLICT DO NOTHING RETURNING native_user_id::text,user_id,active,version", [native, user, target]);
      if (!result.rows.length) throw new ConflictException("An account is already linked; existing links cannot be reassigned.");
      await this.access.audit(sql, actor, link.organisation_id, "foundation.staff_linked", { nativeOrganisationId: native, nativeUserId: user, userId: target });
      return result.rows[0];
    });
  }
  async setStaff(actor: Account, nativeValue: string, userValue: string, body: Record<string, unknown>) {
    const native = nativeId(nativeValue), user = nativeId(userValue); change(body);
    return this.db.transaction(async sql => {
      await this.platform(actor, sql);
      const link = await this.organisation(sql, native, true);
      const stored = (await sql.query<{user_id: string}>("SELECT user_id FROM foundation_staff WHERE native_organisation_id=$1 AND native_user_id=$2", [native,user])).rows[0];
      if (!stored) throw new NotFoundException("Staff link not found.");
      if (body.active) {
        if (!link.active) throw new ForbiddenException("Organisation link is inactive.");
        await this.target(sql, stored.user_id, link.organisation_id);
      }
      const result = await sql.query("UPDATE foundation_staff SET active=$3,version=version+1 WHERE native_organisation_id=$1 AND native_user_id=$2 AND version=$4 RETURNING native_user_id::text,user_id,active,version", [native, user, body.active, body.version]);
      if (!result.rows.length) throw new ConflictException("The link changed; reload its current version.");
      await this.access.audit(sql, actor, link.organisation_id, "foundation.staff_status", { nativeOrganisationId: native, nativeUserId: user, active: body.active });
      return result.rows[0];
    });
  }
  async context(actor: Account, nativeValue: string, userValue: string, purpose: "attendance" | "enrolment" = "attendance") {
    const native = nativeId(nativeValue), user = nativeId(userValue);
    const mapped = (await this.db.query<{organisation_id: string; name: string}>(
      `SELECT o.id AS organisation_id,o.name FROM foundation_organisations f
       JOIN foundation_staff s ON s.native_organisation_id=f.native_id
       JOIN organisations o ON o.id=f.organisation_id
       WHERE f.native_id=$1 AND s.native_user_id=$2 AND s.user_id=$3 AND f.active AND s.active`, [native,user,actor.id])).rows[0];
    if (!mapped) throw new NotFoundException("Attendance identity is not linked.");
    const access = await this.access.require(actor, mapped.organisation_id, purpose === "enrolment" ? "learners.view" : "attendance.view");
    return { nativeOrganisationId: native, nativeUserId: user, userId: actor.id,
      organisation: { id: mapped.organisation_id, name: mapped.name },
      permissions: access.permissions.filter(p => p.startsWith("attendance.") || p.startsWith("learners.") || p.startsWith("centres.") || ["groups.view","groups.create","groups.edit","groups.archive","centres.view"].includes(p)),
      scope: { type: access.scope_type, ids: access.scope_ids } };
  }
  async loginIdentity(actor: Account, nativeValue: unknown) {
    const native = nativeId(nativeValue);
    const link = (await this.db.query<{organisation_id:string; native_user_id:string}>(
      `SELECT f.organisation_id,s.native_user_id::text FROM foundation_organisations f
       JOIN foundation_staff s ON s.native_organisation_id=f.native_id
       WHERE f.native_id=$1 AND s.user_id=$2 AND f.active AND s.active`, [native,actor.id])).rows[0];
    if (!link) throw new NotFoundException("Native account is not linked.");
    await this.access.resolve(actor,link.organisation_id);
    return {nativeOrganisationId:native,nativeUserId:link.native_user_id};
  }
}
