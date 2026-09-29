import { BadRequestException, ConflictException, Injectable, NotFoundException } from "@nestjs/common";
import { createHash, randomUUID } from "node:crypto";
import { AccessService } from "./access.service.js";
import { Database, type SqlClient } from "./database.js";
import { ExamContentService } from "./exam-content.service.js";
import type { Account } from "./identity.service.js";
import { uuid } from "./security.js";

type Scan = { id: string; learner_id: string; content_type: string; answers: Record<string, string>; status: "uploaded" | "reviewed"; revision: number; created_at: string; updated_at: string };
@Injectable()
export class ExamOmrService {
  constructor(private readonly db: Database, private readonly access: AccessService, private readonly exams: ExamContentService) {}
  private async permitted(user: Account, org: string, learner: string, sql: SqlClient = this.db) {
    await this.access.require(user, org, "exams.manage", sql);
    const scope = await this.access.require(user, org, "learners.view", sql);
    const row = (await sql.query<{ id: string }>(`SELECT l.id FROM learners l JOIN learning_groups g ON g.id=l.group_id AND g.organisation_id=l.organisation_id WHERE l.organisation_id=$1 AND l.id=$2 AND NOT l.archived AND ($3='organisation' OR ($3='centres' AND g.centre_id=ANY($4::uuid[])) OR ($3='groups' AND g.id=ANY($4::uuid[])))`, [org, uuid(learner), scope.scope_type, scope.scope_ids])).rows[0];
    if (!row) throw new NotFoundException("Student not found.");
  }
  private file(body: Record<string, unknown>) {
    if (typeof body.file !== "string" || typeof body.content_type !== "string") throw new BadRequestException("Choose a JPEG, PNG or PDF scan.");
    if (!["image/jpeg", "image/png", "application/pdf"].includes(body.content_type)) throw new BadRequestException("Choose a JPEG, PNG or PDF scan.");
    const prefix = `data:${body.content_type};base64,`;
    if (!body.file.startsWith(prefix)) throw new BadRequestException("Invalid scan format.");
    const source = body.file.slice(prefix.length);
    if (!source.length || source.length > 13981016 || !/^[A-Za-z0-9+/]+={0,2}$/.test(source)) throw new BadRequestException("Invalid scan.");
    const content = Buffer.from(source, "base64");
    if (!content.length || content.length > 10 * 1024 * 1024 || content.toString("base64") !== source) throw new BadRequestException("Use a scan up to 10 MB.");
    if ((body.content_type === "image/jpeg" && !content.subarray(0, 3).equals(Buffer.from([255, 216, 255]))) || (body.content_type === "image/png" && !content.subarray(0, 8).equals(Buffer.from([137,80,78,71,13,10,26,10]))) || (body.content_type === "application/pdf" && content.subarray(0, 5).toString("ascii") !== "%PDF-")) throw new BadRequestException("Invalid scan content.");
    return { content, type: body.content_type };
  }
  private async ownedExam(user: Account, org: string, examId: string) {
    if (!/^[1-9][0-9]{0,14}$/.test(examId) || !Number.isSafeInteger(Number(examId))) throw new BadRequestException("Choose a valid exam.");
    const exam = await this.exams.taxonomy(user, org, "exams", examId);
    if (Number(exam?.id) !== Number(examId)) throw new NotFoundException("Exam not found.");
    return Number(examId);
  }
  async list(user: Account, org: string, examId: string) {
    const id = await this.ownedExam(user, org, examId);
    const scope = await this.access.require(user, org, "learners.view");
    return (await this.db.query<Scan>(`SELECT s.id,s.learner_id,s.content_type,s.answers,s.status,s.revision,s.created_at,s.updated_at FROM exam_omr_scans s JOIN learners l ON l.organisation_id=s.organisation_id AND l.id=s.learner_id JOIN learning_groups g ON g.id=l.group_id AND g.organisation_id=l.organisation_id WHERE s.organisation_id=$1 AND s.exam_id=$2 AND NOT l.archived AND ($3='organisation' OR ($3='centres' AND g.centre_id=ANY($4::uuid[])) OR ($3='groups' AND g.id=ANY($4::uuid[]))) ORDER BY s.created_at DESC LIMIT 200`, [org, id, scope.scope_type, scope.scope_ids])).rows;
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
    const file = this.file(body); const exam = await this.ownedExam(user, org, examId);
    const id = randomUUID();
    await this.db.transaction(async sql => { await this.access.lock(sql, org); await this.permitted(user, org, learnerId, sql); await sql.query("INSERT INTO exam_omr_scans(id,organisation_id,exam_id,learner_id,content,content_type,content_hash,uploaded_by) VALUES($1,$2,$3,$4,$5,$6,$7,$8)", [id, org, exam, uuid(learnerId), file.content, file.type, createHash("sha256").update(file.content).digest("hex"), user.id]); await this.access.audit(sql,user,org,"exam.omr_scan_uploaded",{examId:exam,scanId:id,learnerId}); }); return { id };
  }
  async review(user: Account, org: string, scan: string, body: Record<string, unknown>) {
    if (!Number.isInteger(body.revision) || !body.answers || typeof body.answers !== "object" || Array.isArray(body.answers)) throw new BadRequestException("Reload and enter the reviewed answers.");
    const entries = Object.entries(body.answers as Record<string, unknown>);
    if (entries.length > 200) throw new BadRequestException("Review up to 200 answers.");
    const answers = Object.fromEntries(entries.map(([q,a]) => { if (!/^(?:[1-9]|[1-9][0-9]|1[0-9]{2}|200)$/.test(q) || typeof a !== "string" || !/^[A-F]$/.test(a)) throw new BadRequestException("Answers must use question numbers 1–200 and A–F choices."); return [q,a]; }));
    const scanId = uuid(scan);
    const updated = await this.db.transaction(async sql => { await this.access.lock(sql,org); const row = (await sql.query<{learner_id:string}>("SELECT learner_id FROM exam_omr_scans WHERE id=$1 AND organisation_id=$2 FOR UPDATE", [scanId,org])).rows[0]; if (!row) throw new NotFoundException("OMR scan not found."); await this.permitted(user,org,row.learner_id,sql); const result = await sql.query<{revision:number}>("UPDATE exam_omr_scans SET answers=$4,status='reviewed',reviewed_by=$5,revision=revision+1,updated_at=now() WHERE id=$1 AND organisation_id=$2 AND revision=$3 RETURNING revision", [scanId,org,body.revision,JSON.stringify(answers),user.id]); if (!result.rows[0]) throw new ConflictException("Scan changed. Reload before saving."); await this.access.audit(sql,user,org,"exam.omr_scan_reviewed",{scanId}); return result.rows[0]; }); return { saved:true, revision:updated.revision };
  }
}
