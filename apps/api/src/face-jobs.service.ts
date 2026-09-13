import {
  Injectable,
  BadRequestException,
  ServiceUnavailableException,
  ForbiddenException,
  ConflictException,
  NotFoundException,
} from "@nestjs/common";
import { randomUUID } from "node:crypto";
import { Database } from "./database.js";
import { FaceMatchingService } from "./face-matching.service.js";
import type { Account } from "./identity.service.js";
import { uuid } from "./security.js";
export function faceJobError(error: unknown): string {
  // Only expose application-authored HTTP errors, never raw provider/DB errors.
  if (error instanceof ConflictException || error instanceof BadRequestException || error instanceof ServiceUnavailableException) return error.message;
  if (error instanceof ForbiddenException) return "Your access to face matching or student photos changed. Ask an administrator to check your permissions.";
  if (error instanceof NotFoundException) return "The attendance, account or reference is no longer accessible. Reopen attendance and retry.";
  return "An internal comparison error occurred. No attendance marks changed. Ask the administrator to check the comparison service.";
}

type Job = {
  id: string;
  organisation_id: string;
  session_id: string;
  actor_id: string;
  signature: string;
  status: string;
  completed: number;
  total: number;
  result: unknown;
  error: string | null;
  created_at: string;
};
type Match = Awaited<ReturnType<FaceMatchingService["match"]>>;
export function combinePhotoMatches(
  photos: { photoId: string; faces: Match["faces"] }[],
  roster: { id: string; name: string; code: string }[],
) {
  const present = new Map<string, Set<string>>(),
    review = new Set<string>();
  for (const photo of photos)
    for (const face of photo.faces) {
      if (face.learnerId) {
        const seen = present.get(face.learnerId) || new Set<string>();
        seen.add(photo.photoId);
        present.set(face.learnerId, seen);
      } else for (const id of face.reviewIds) review.add(id);
    }
  return roster.map((l) => ({
    ...l,
    status: review.has(l.id)
      ? "needs_review"
      : present.has(l.id)
        ? "suggested_present"
        : "not_identified",
    photoIds: [...(present.get(l.id) || [])],
  }));
}
@Injectable()
export class FaceJobsService {
  private timer?: ReturnType<typeof setInterval>;
  private active?: Promise<void>;
  private stopping = false;
  constructor(
    private readonly db: Database,
    private readonly matching: FaceMatchingService,
  ) {}
  onModuleInit() {
    this.timer = setInterval(() => this.wake(), 2000);
    this.timer.unref();
  }
  async onModuleDestroy() {
    this.stopping = true;
    clearInterval(this.timer);
    await this.active;
  }
  private wake() {
    if (!this.active && !this.stopping) {
      this.active = this.tick()
        .catch(() => {})
        .finally(() => {
          this.active = undefined;
        });
    }
  }
  async enqueue(user: Account, org: string, id: string) {
    const ctx = await this.matching.context(user, org, id);
    const job = await this.db.transaction(async (sql) => {
      await sql.query("SELECT id FROM organisations WHERE id=$1 FOR UPDATE", [
        org,
      ]);
      await sql.query(
        "DELETE FROM attendance_face_jobs WHERE status IN ('completed','failed') AND created_at<now()-interval '1 day'",
      );
      const existing = (
        await sql.query<Job>(
          "SELECT * FROM attendance_face_jobs WHERE organisation_id=$1 AND session_id=$2 AND status IN ('queued','processing')",
          [org, id],
        )
      ).rows[0];
      if (existing) return existing;
      const count = (
        await sql.query<{ n: string }>(
          "SELECT count(*) AS n FROM attendance_face_jobs WHERE organisation_id=$1 AND created_at>now()-interval '1 day'",
          [org],
        )
      ).rows[0];
      if (Number(count.n) >= 20)
        throw new ConflictException(
          "Daily pilot limit of 20 matching jobs reached.",
        );
      return (
        await sql.query<Job>(
          "INSERT INTO attendance_face_jobs(id,organisation_id,session_id,actor_id,status,signature,total) VALUES($1,$2,$3,$4,'queued',$5,$6) RETURNING *",
          [
            randomUUID(),
            org,
            id,
            user.id,
            ctx.signature,
            ctx.referenceCount * ctx.photoIds.length,
          ],
        )
      ).rows[0];
    });
    this.wake();
    return {
      id: job.id,
      status: job.status,
      completed: job.completed,
      total: job.total,
    };
  }
  async get(user: Account, org: string, id: string, jobId?: string) {
    const ctx = await this.matching.context(user, org, id);
    const job = (
      await this.db.query<Job>(
        `SELECT * FROM attendance_face_jobs WHERE organisation_id=$1 AND session_id=$2 ${jobId ? "AND id=$3" : ""} ORDER BY created_at DESC LIMIT 1`,
        jobId ? [org, id, uuid(jobId)] : [org, id],
      )
    ).rows[0];
    if (!job) {
      if (jobId) throw new NotFoundException("Matching job not found.");
      return null;
    }
    const expired = Date.now() - new Date(job.created_at).getTime() > 86400000;
    if (ctx.signature !== job.signature || expired)
      return {
        id: job.id,
        status: "stale",
        completed: job.completed,
        total: job.total,
        result: null,
        error:
          "Photos, references or attendance changed. Start a new comparison.",
      };
    return {
      id: job.id,
      status: job.status,
      completed: job.completed,
      total: job.total,
      result: job.result,
      error: job.error,
    };
  }
  private async tick() {
    const ready = (
      await this.db.query("SELECT version FROM schema_versions WHERE version=9")
    ).rows.length;
    if (!ready) return;
    await this.db.query(
      "DELETE FROM attendance_face_jobs WHERE status IN ('completed','failed','queued') AND created_at<now()-interval '1 day'",
    );
    const job = await this.db.transaction(async (sql) => {
      const slot = (
        await sql.query<{
          job_id: string | null;
          lease_until: string | null;
          paused?: boolean;
        }>("SELECT * FROM attendance_face_worker WHERE id=1 FOR UPDATE")
      ).rows[0];
      if (slot?.paused) return;
      if (
        slot?.lease_until &&
        new Date(slot.lease_until).getTime() > Date.now()
      )
        return;
      if (slot?.job_id)
        await sql.query(
          "UPDATE attendance_face_jobs SET status='failed',result=NULL,error='Processing interrupted. Retry the comparison.' WHERE id=$1 AND status='processing'",
          [slot.job_id],
        );
      const next = (
        await sql.query<Job>(
          "SELECT * FROM attendance_face_jobs WHERE status='queued' ORDER BY created_at,id LIMIT 1 FOR UPDATE",
        )
      ).rows[0];
      if (!next) {
        await sql.query(
          "UPDATE attendance_face_worker SET job_id=NULL,lease_until=NULL WHERE id=1",
        );
        return;
      }
      await sql.query(
        "UPDATE attendance_face_worker SET job_id=$1,lease_until=now()+interval '60 seconds' WHERE id=1",
        [next.id],
      );
      await sql.query(
        "UPDATE attendance_face_jobs SET status='processing' WHERE id=$1",
        [next.id],
      );
      return next;
    });
    if (!job) return;
    try {
      const user = (
        await this.db.query<Account>(
          "SELECT id,email,name,is_superadmin FROM users WHERE id=$1",
          [job.actor_id],
        )
      ).rows[0];
      if (!user) throw new NotFoundException("Account unavailable.");
      const ctx = await this.matching.context(
        user,
        job.organisation_id,
        job.session_id,
      );
      const assertCurrent = async (done: number) => {
        if (this.stopping)
          throw new ConflictException(
            "Processing interrupted. Retry after restart.",
          );
        const latest = await this.matching.context(
          user,
          job.organisation_id,
          job.session_id,
        );
        if (latest.signature !== job.signature)
          throw new ConflictException(
            "Photos, references or attendance changed. Start a new comparison.",
          );
        const renewed = await this.db.query(
          "UPDATE attendance_face_worker SET lease_until=now()+interval '60 seconds' WHERE id=1 AND job_id=$1 AND lease_until>now() RETURNING id",
          [job.id],
        );
        if (!renewed.rows.length)
          throw new ConflictException(
            "Processing lease expired. Retry the comparison.",
          );
        await this.db.query(
          "UPDATE attendance_face_jobs SET completed=$2 WHERE id=$1 AND status='processing'",
          [job.id, done],
        );
      };
      await assertCurrent(0);
      const photos: { photoId: string; faces: Match["faces"] }[] = [];
      for (let i = 0; i < ctx.photoIds.length; i++) {
        const match = await this.matching.match(
          user,
          job.organisation_id,
          job.session_id,
          ctx.photoIds[i],
          async (done) => assertCurrent(i * ctx.referenceCount + done),
        );
        photos.push({ photoId: ctx.photoIds[i], faces: match.faces });
      }
      await assertCurrent(job.total);
      const result = {
        photos,
        students: combinePhotoMatches(photos, ctx.roster),
      };
      await this.db.query(
        "UPDATE attendance_face_jobs SET status='completed',result=$2 WHERE id=$1 AND status='processing'",
        [job.id, JSON.stringify(result)],
      );
    } catch (e) {
      const message = faceJobError(e);
      await this.db.query(
        "UPDATE attendance_face_jobs SET status='failed',result=NULL,error=$2 WHERE id=$1 AND status='processing'",
        [job.id, message],
      );
    } finally {
      await this.db.query(
        "UPDATE attendance_face_worker SET job_id=NULL,lease_until=NULL WHERE id=1 AND job_id=$1",
        [job.id],
      );
    }
  }
}
