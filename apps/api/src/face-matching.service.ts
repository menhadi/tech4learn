import {
  BadRequestException,
  ConflictException,
  Injectable,
} from "@nestjs/common";
import { Database } from "./database.js";
import { AccessService } from "./access.service.js";
import { AttendanceService } from "./attendance.service.js";
import { LearnerPhotosService } from "./learner-photos.service.js";
import type { Account } from "./identity.service.js";
import {
  faceConfig,
  verifyFace,
  sameFace,
  type Box,
} from "./face-verification.js";
type Reference = { id: string; learner_id: string; content: Buffer };
export function resolveFaces(
  candidates: { box: Box; scores: Record<string, number> }[],
  threshold: number,
  margin: number,
) {
  const rows = candidates.map((c) => {
    const rank = Object.entries(c.scores).sort((a, b) => b[1] - a[1]);
    const best = rank[0];
    return {
      box: c.box,
      learnerId:
        best &&
        best[1] >= threshold &&
        best[1] - (rank[1]?.[1] || 0) >= margin &&
        c.box.probability >= 0.9
          ? best[0]
          : null,
      similarity: best?.[1] || 0,
    };
  });
  const counts = new Map<string, number>();
  for (const r of rows)
    if (r.learnerId)
      counts.set(r.learnerId, (counts.get(r.learnerId) || 0) + 1);
  return rows.map((r) => ({
    ...r,
    learnerId:
      r.learnerId && counts.get(r.learnerId) === 1 ? r.learnerId : null,
  }));
}
@Injectable()
export class FaceMatchingService {
  private running = new Set<string>();
  constructor(
    private readonly db: Database,
    private readonly access: AccessService,
    private readonly attendance: AttendanceService,
    private readonly photos: LearnerPhotosService,
  ) {}
  async match(user: Account, org: string, id: string) {
    await this.access.require(user, org, "attendance.match");
    await this.access.require(user, org, "learners.photos");
    if (this.running.has(org))
      throw new ConflictException(
        "Another face check is running. Try again shortly.",
      );
    this.running.add(org);
    try {
      const config = faceConfig(org);
      if (!config.configured)
        throw new BadRequestException(
          "Private face verification is not configured for this organisation.",
        );
      const session = await this.attendance.detail(user, org, id);
      if (!["pending", "confirmed"].includes(session.status))
        throw new BadRequestException("Open a submitted attendance capture.");
      const image = await this.attendance.photo(user, org, id);
      const roster = session.snapshot.roster;
      const query = () =>
        this.db.query<Reference>(
          `SELECT DISTINCT ON (p.learner_id) p.id,p.learner_id,p.content FROM learner_photos p JOIN learners l ON l.id=p.learner_id AND l.organisation_id=p.organisation_id JOIN learner_photo_consent c ON c.organisation_id=p.organisation_id AND c.learner_id=p.learner_id AND c.purpose='reference' WHERE p.organisation_id=$1 AND l.group_id=$2 AND l.id=ANY($3::uuid[]) AND NOT l.archived AND NOT l.demo AND p.purpose='reference' AND p.checked AND p.check_engine=$4 AND c.granted ORDER BY p.learner_id,p.created_at DESC,p.id LIMIT 21`,
          [org, session.group_id, roster.map((l) => l.id), config.model],
        );
      const refs = (await query()).rows;
      if (!refs.length)
        throw new BadRequestException(
          "No current, checked and consented face references in this section.",
        );
      if (refs.length > 20)
        throw new BadRequestException(
          "This verification pilot supports up to 20 enrolled students per capture.",
        );
      for (const ref of refs)
        await this.photos.target(this.db, user, org, ref.learner_id);
      const candidates: { box: Box; scores: Record<string, number> }[] = [];
      // Stateless verification sends one current reference per student; no collection is created.
      for (let offset = 0; offset < refs.length; offset += 4) {
        await this.access.require(user, org, "attendance.match");
        await this.attendance.photo(user, org, id);
        const active = (await query()).rows;
        if (
          active.length !== refs.length ||
          active.some((r) => !refs.some((old) => old.id === r.id))
        )
          throw new ConflictException(
            "Consent or references changed. Run the check again.",
          );
        const batch = refs.slice(offset, offset + 4);
        const completed = await Promise.allSettled(
          batch.map((r) => verifyFace(org, Buffer.from(r.content), image)),
        );
        const failed = completed.find((r) => r.status === "rejected");
        if (failed?.status === "rejected") throw failed.reason;
        const results = completed.map((r) => {
          if (r.status === "rejected") throw r.reason;
          return r.value;
        });
        for (let n = 0; n < results.length; n++)
          for (const face of results[n].faces) {
            let candidate = candidates.find((c) => sameFace(c.box, face.box));
            if (!candidate) {
              candidate = { box: face.box, scores: {} };
              candidates.push(candidate);
            }
            candidate.scores[batch[n].learner_id] = Math.max(
              candidate.scores[batch[n].learner_id] || 0,
              face.similarity,
            );
          }
      }
      await this.access.require(user, org, "attendance.match");
      await this.attendance.photo(user, org, id);
      const current = (await query()).rows;
      if (
        current.length !== refs.length ||
        current.some((r) => !refs.some((old) => old.id === r.id))
      )
        throw new ConflictException(
          "References or consent changed during matching. Run the check again.",
        );
      for (const ref of current)
        await this.photos.target(this.db, user, org, ref.learner_id);
      const faces = resolveFaces(candidates, config.threshold, config.margin);
      await this.access.audit(this.db, user, org, "attendance.face_draft", {
        sessionId: id,
        faces: faces.length,
        matched: faces.filter((f) => f.learnerId).length,
        model: config.model,
      });
      return {
        faces,
        referenceCount: refs.length,
        rosterCount: roster.length,
        threshold: config.threshold,
        margin: config.margin,
        notice:
          "Suggestions only. Review each face. Unmatched faces do not indicate absence.",
      };
    } finally {
      this.running.delete(org);
    }
  }
}
