import { BadRequestException, ConflictException, Injectable, NotFoundException } from "@nestjs/common";
import { createHash, randomUUID } from "node:crypto";
import { AccessService } from "./access.service.js";
import { Database } from "./database.js";
import type { Account } from "./identity.service.js";
import { uuid } from "./security.js";

type Scan = { id: string; learner_id: string; content_type: string; answers: Record<string, string>; status: "uploaded" | "reviewed"; revision: number; created_at: string; updated_at: string };
@Injectable()
export class ExamOmrService {
  constructor(private readonly db: Database, private readonly access: AccessService) {}
  private async permitted(user: Account, org: string, learner: string) {
    const scope = await this.access.require(user, org, "exams.manage");
    await this.access.require(user, org, "learners.view");
    const row = (await this.db.query<{ id: string }>(`SELECT l.id FROM learners l JOIN learning_groups g ON g.id=l.group_id AND g.organisation_id=l.organisation_id WHERE l.organisation_id=$1 AND l.id=$2 AND ($3='organisation' OR ($3='centres' AND g.centre_id=ANY($4::uuid[])) OR ($3='groups' AND g.id=ANY($4::uuid[])))`, [org, uuid(learner), scope.scope_type, scope.scope_ids])).rows[0];
    if (!row) throw new NotFoundException("Student not found.");
  }
  private file(body: Record<string, unknown>) {
    if (typeof body.file !== "string" || typeof body.content_type !== "string") throw new BadRequestException("Choose a JPEG, PNG or PDF scan.");
    if (!["image/jpeg", "image/png", "application/pdf"].includes(body.content_type)) throw new BadRequestException("Choose a JPEG, PNG or PDF scan.");
    const source = body.file.replace(/^data:[^;]+;base64,/, "");
    if (!/^[A-Za-z0-9+/=]+$/.test(source)) throw new BadRequestException("Invalid scan.");
    const content = Buffer.from(source, "base64");
    if (!content.length || content.length > 10 * 1024 * 1024) throw new BadRequestException("Use a scan up to 10 MB.");
    return { content, type: body.content_type };
  }
  async list(user: Account, org: string, examId: string) {
    await this.access.require(user, org, "exams.manage");
    return (await this.db.query<Scan>("SELECT id,learner_id,content_type,answers,status,revision,created_at,updated_at FROM exam_omr_scans WHERE organisation_id=$1 AND exam_id=$2 ORDER BY created_at DESC", [org, Number(examId)])).rows;
  }
  async content(user: Account, org: string, scan: string) {
    const row = (await this.db.query<{ learner_id: string; content: Buffer; content_type: string }>("SELECT learner_id,content,content_type FROM exam_omr_scans WHERE id=$1 AND organisation_id=$2", [uuid(scan), org])).rows[0];
    if (!row) throw new NotFoundException("OMR scan not found.");
    await this.permitted(user, org, row.learner_id);
    return row;
  }
  async upload(user: Account, org: string, examId: string, body: Record<string, unknown>) {
    if (!Number.isSafeInteger(Number(examId)) || Number(examId) < 1 || typeof body.learner_id !== "string") throw new BadRequestException("Choose an exam and student.");
    const learnerId = body.learner_id;
    const file = this.file(body); await this.permitted(user, org, learnerId);
    const id = randomUUID();
    await this.db.transaction(async sql => { await this.access.lock(sql, org); await sql.query("INSERT INTO exam_omr_scans(id,organisation_id,exam_id,learner_id,content,content_type,content_hash,uploaded_by) VALUES($1,$2,$3,$4,$5,$6,$7,$8)", [id, org, Number(examId), uuid(learnerId), file.content, file.type, createHash("sha256").update(file.content).digest("hex"), user.id]); await this.access.audit(sql,user,org,"exam.omr_scan_uploaded",{examId:Number(examId),scanId:id,learnerId}); }); return { id };
  }
  async review(user: Account, org: string, scan: string, body: Record<string, unknown>) {
    if (!Number.isInteger(body.revision) || !body.answers || typeof body.answers !== "object" || Array.isArray(body.answers)) throw new BadRequestException("Reload and enter the reviewed answers.");
    const answers = Object.fromEntries(Object.entries(body.answers as Record<string, unknown>).map(([q,a]) => { if (!/^\d{1,4}$/.test(q) || typeof a !== "string" || !/^[A-F]$/.test(a)) throw new BadRequestException("Answers must use question numbers and A–F choices."); return [q,a]; }));
    await this.access.require(user, org, "exams.manage");
    const updated = await this.db.transaction(async sql => { await this.access.lock(sql,org); const result = await sql.query("UPDATE exam_omr_scans SET answers=$4,status='reviewed',reviewed_by=$5,revision=revision+1,updated_at=now() WHERE id=$1 AND organisation_id=$2 AND revision=$3 RETURNING revision", [uuid(scan),org,body.revision,JSON.stringify(answers),user.id]); if (!result.rows[0]) throw new ConflictException("Scan changed. Reload before saving."); await this.access.audit(sql,user,org,"exam.omr_scan_reviewed",{scanId:scan}); return result.rows[0]; }); return { saved:true, revision:updated.revision };
  }
}
