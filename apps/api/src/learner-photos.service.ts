import {
  BadRequestException,
  ConflictException,
  Injectable,
  NotFoundException,
} from "@nestjs/common";
import { createHash, randomUUID } from "node:crypto";
import { Database, type SqlClient } from "./database.js";
import { AccessService } from "./access.service.js";
import type { Account } from "./identity.service.js";
import { uuid } from "./security.js";
import { jpeg } from "./attendance-evidence.js";
import { faceConfig, verifyFace } from "./face-verification.js";
type Purpose = "profile" | "reference";
export function photoFile(input: unknown) {
  const bytes = jpeg(input);
  let pos = 2,
    width = 0,
    height = 0;
  const parts = [bytes.subarray(0, 2)];
  while (pos + 4 <= bytes.length) {
    const start = pos;
    if (bytes[pos++] !== 255) throw new BadRequestException("Invalid photo.");
    while (bytes[pos] === 255) pos++;
    const marker = bytes[pos++];
    if (marker === 0xda) {
      parts.push(bytes.subarray(start));
      break;
    }
    const length = bytes.readUInt16BE(pos);
    if (length < 2 || pos + length > bytes.length)
      throw new BadRequestException("Invalid photo.");
    if ([0xc0, 0xc1, 0xc2].includes(marker)) {
      height = bytes.readUInt16BE(pos + 3);
      width = bytes.readUInt16BE(pos + 5);
    }
    if (!(marker >= 0xe0 && marker <= 0xef) && marker !== 0xfe)
      parts.push(bytes.subarray(start, pos + length));
    pos += length;
  }
  if (width < 160 || height < 160)
    throw new BadRequestException("Use a photo at least 160 by 160 pixels.");
  return { content: Buffer.concat(parts), width, height };
}
@Injectable()
export class LearnerPhotosService {
  constructor(
    private readonly db: Database,
    private readonly access: AccessService,
  ) {}
  private purpose(v: unknown): Purpose {
    if (v !== "profile" && v !== "reference")
      throw new BadRequestException("Choose profile or reference.");
    return v;
  }
  async target(
    sql: SqlClient,
    user: Account,
    org: string,
    id: string,
    manage = false,
  ) {
    const a = await this.access.require(
      user,
      org,
      manage ? "learners.photo_manage" : "learners.photos",
      sql,
    );
    await this.access.require(user, org, "learners.view", sql);
    const row = (
      await sql.query<{ id: string; archived: boolean; demo: boolean }>(
        `SELECT l.id,l.archived,l.demo FROM learners l JOIN learning_groups g ON g.id=l.group_id AND g.organisation_id=l.organisation_id WHERE l.organisation_id=$1 AND l.id=$2 AND ($3='organisation' OR ($3='centres' AND g.centre_id=ANY($4::uuid[])) OR ($3='groups' AND g.id=ANY($4::uuid[])))`,
        [org, uuid(id), a.scope_type, a.scope_ids],
      )
    ).rows[0];
    if (!row) throw new NotFoundException("Student not found.");
    return row;
  }
  async list(user: Account, org: string, id: string) {
    await this.target(this.db, user, org, id);
    return {
      consents: (
        await this.db.query(
          "SELECT purpose,granted,version,recorded_at FROM learner_photo_consent WHERE organisation_id=$1 AND learner_id=$2",
          [org, id],
        )
      ).rows,
      photos: (
        await this.db.query(
          "SELECT id,purpose,name,content_hash,width,height,checked,check_engine,created_at FROM learner_photos WHERE organisation_id=$1 AND learner_id=$2 ORDER BY created_at DESC",
          [org, id],
        )
      ).rows,
      verificationConfigured: faceConfig(org).configured,
    };
  }
  async consent(
    user: Account,
    org: string,
    id: string,
    b: Record<string, unknown>,
  ) {
    const purpose = this.purpose(b.purpose);
    if (
      typeof b.granted !== "boolean" ||
      !Number.isInteger(b.version) ||
      b.attested !== true
    )
      throw new BadRequestException(
        "Confirm the consent decision and reload the current record.",
      );
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      await this.target(sql, user, org, id, true);
      const old = (
        await sql.query<{ version: number }>(
          "SELECT version FROM learner_photo_consent WHERE organisation_id=$1 AND learner_id=$2 AND purpose=$3",
          [org, id, purpose],
        )
      ).rows[0];
      if ((old?.version || 0) !== b.version)
        throw new ConflictException(
          "Consent changed. Reload before continuing.",
        );
      await sql.query(
        "INSERT INTO learner_photo_consent(organisation_id,learner_id,purpose,granted,actor_id) VALUES($1,$2,$3,$4,$5) ON CONFLICT(organisation_id,learner_id,purpose) DO UPDATE SET granted=$4,version=learner_photo_consent.version+1,actor_id=$5,recorded_at=now()",
        [org, id, purpose, b.granted, user.id],
      );
      if (!b.granted)
        await sql.query(
          "DELETE FROM learner_photos WHERE organisation_id=$1 AND learner_id=$2 AND purpose=$3",
          [org, id, purpose],
        );
      await this.access.audit(sql, user, org, "learner.photo_consent", {
        learnerId: id,
        purpose,
        granted: b.granted,
      });
      return { saved: true };
    });
  }
  async upload(
    user: Account,
    org: string,
    id: string,
    b: Record<string, unknown>,
  ) {
    const purpose = this.purpose(b.purpose),
      file = photoFile(b.photo);
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      return this.storePhoto(sql, user, org, id, b, purpose, file);
    });
  }
  private async storePhoto(
    sql: SqlClient,
    user: Account,
    org: string,
    id: string,
    b: Record<string, unknown>,
    purpose: Purpose,
    file: ReturnType<typeof photoFile>,
  ) {
    const name =
      b.name === undefined
        ? purpose === "profile"
          ? "Profile picture"
          : "Attendance portrait"
        : b.name;
    if (typeof name !== "string" || !name.trim() || name.trim().length > 80)
      throw new BadRequestException(
        "Give the photo a name of 1–80 characters.",
      );

    const l = await this.target(sql, user, org, id, true);
    if (l.archived)
      throw new ConflictException(
        "Archived students cannot receive new photos.",
      );
    if (purpose === "reference" && l.demo)
      throw new BadRequestException(
        "Use a separately registered, consented test student for real face references. Dummy records must not identify real people.",
      );
    const consent = (
      await sql.query<{ version: number }>(
        "SELECT version FROM learner_photo_consent WHERE organisation_id=$1 AND learner_id=$2 AND purpose=$3 AND granted",
        [org, id, purpose],
      )
    ).rows[0];
    if (!consent || consent.version !== b.consentVersion)
      throw new ConflictException("Record current consent before uploading.");
    const hash = createHash("sha256").update(file.content).digest("hex");
    const existing = (
      await sql.query<{ id: string }>(
        "SELECT id FROM learner_photos WHERE organisation_id=$1 AND learner_id=$2 AND purpose=$3 AND content_hash=$4",
        [org, id, purpose, hash],
      )
    ).rows[0];
    if (existing) {
      if (b.name !== undefined) {
        await sql.query(
          "UPDATE learner_photos SET name=$4 WHERE organisation_id=$1 AND learner_id=$2 AND id=$3",
          [org, id, existing.id, name.trim()],
        );
        await this.access.audit(sql, user, org, "learner.photo_named", {
          learnerId: id,
          photoId: existing.id,
          purpose,
        });
      }
      return existing;
    }
    if (
      purpose === "reference" &&
      (
        await sql.query(
          "SELECT id FROM learner_photos WHERE organisation_id=$1 AND learner_id=$2 AND purpose=$3",
          [org, id, purpose],
        )
      ).rows.length >= 3
    )
      throw new BadRequestException(
        "Keep up to three reference photos. Remove an older reference first.",
      );
    if (purpose === "profile")
      await sql.query(
        "DELETE FROM learner_photos WHERE organisation_id=$1 AND learner_id=$2 AND purpose='profile'",
        [org, id],
      );
    const photoId = randomUUID();
    await sql.query(
      "INSERT INTO learner_photos(id,organisation_id,learner_id,purpose,content,content_hash,width,height,actor_id,name) VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)",
      [
        photoId,
        org,
        id,
        purpose,
        file.content,
        hash,
        file.width,
        file.height,
        user.id,
        name.trim(),
      ],
    );
    await this.access.audit(sql, user, org, "learner.photo_uploaded", {
      learnerId: id,
      photoId,
      purpose,
    });
    return { id: photoId };
  }
  async setup(
    user: Account,
    org: string,
    id: string,
    b: Record<string, unknown>,
  ) {
    if (
      typeof b.profile !== "boolean" ||
      typeof b.reference !== "boolean" ||
      (!b.profile && !b.reference) ||
      b.attested !== true
    )
      throw new BadRequestException(
        "Choose how to use the photo and confirm permission for those uses.",
      );
    const versions = b.consentVersions as Record<string, unknown> | undefined;
    const purposes: Purpose[] = [];
    if (b.profile) purposes.push("profile");
    if (b.reference) purposes.push("reference");
    const file = photoFile(b.photo);
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      await this.target(sql, user, org, id, true);
      const photos = [];
      for (const purpose of purposes) {
        const old = (
          await sql.query<{ version: number; granted: boolean }>(
            "SELECT version,granted FROM learner_photo_consent WHERE organisation_id=$1 AND learner_id=$2 AND purpose=$3",
            [org, id, purpose],
          )
        ).rows[0];
        if (
          !Number.isInteger(versions?.[purpose]) ||
          versions?.[purpose] !== (old?.version || 0)
        )
          throw new ConflictException(
            "Permission changed. Reload the student before saving.",
          );
        let version = old?.version || 0;
        if (!old?.granted) {
          version = (
            await sql.query<{ version: number }>(
              "INSERT INTO learner_photo_consent(organisation_id,learner_id,purpose,granted,actor_id) VALUES($1,$2,$3,true,$4) ON CONFLICT(organisation_id,learner_id,purpose) DO UPDATE SET granted=true,version=learner_photo_consent.version+1,actor_id=$4,recorded_at=now() RETURNING version",
              [org, id, purpose, user.id],
            )
          ).rows[0].version;
          await this.access.audit(sql, user, org, "learner.photo_consent", {
            learnerId: id,
            purpose,
            granted: true,
          });
        }
        photos.push({
          ...(await this.storePhoto(
            sql,
            user,
            org,
            id,
            { ...b, consentVersion: version },
            purpose,
            file,
          )),
          purpose,
        });
      }
      return { photos };
    });
  }
  async photo(user: Account, org: string, id: string, photoId: string) {
    await this.target(this.db, user, org, id);
    const row = (
      await this.db.query<{ content: Buffer }>(
        "SELECT p.content FROM learner_photos p JOIN learner_photo_consent c ON c.organisation_id=p.organisation_id AND c.learner_id=p.learner_id AND c.purpose=p.purpose WHERE p.organisation_id=$1 AND p.learner_id=$2 AND p.id=$3 AND c.granted",
        [org, id, uuid(photoId)],
      )
    ).rows[0];
    if (!row) throw new NotFoundException("Photo not found.");
    return Buffer.from(row.content);
  }
  async remove(user: Account, org: string, id: string, photoId: string) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      await this.target(sql, user, org, id, true);
      await sql.query(
        "DELETE FROM learner_photos WHERE organisation_id=$1 AND learner_id=$2 AND id=$3",
        [org, id, uuid(photoId)],
      );
      await this.access.audit(sql, user, org, "learner.photo_removed", {
        learnerId: id,
        photoId,
      });
      return { removed: true };
    });
  }
  async check(user: Account, org: string, id: string, photoId: string) {
    const learner = await this.target(this.db, user, org, id, true);
    const reference = (
      await this.db.query<{ width: number; height: number }>(
        "SELECT width,height FROM learner_photos WHERE organisation_id=$1 AND learner_id=$2 AND id=$3 AND purpose='reference'",
        [org, id, uuid(photoId)],
      )
    ).rows[0];
    if (!reference || learner.demo || learner.archived)
      throw new BadRequestException(
        "Only an active student's consented attendance reference can be face-checked.",
      );
    const image = await this.photo(user, org, id, photoId);
    const result = await verifyFace(org, image, image);
    if (
      result.faces.length !== 1 ||
      result.sourceBox.x_max > reference.width ||
      result.sourceBox.y_max > reference.height ||
      result.sourceBox.probability < 0.9 ||
      result.sourceBox.x_max - result.sourceBox.x_min < 80 ||
      result.sourceBox.y_max - result.sourceBox.y_min < 80
    )
      throw new BadRequestException(
        "Use a closer, clear photo containing one face.",
      );
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const l = await this.target(sql, user, org, id, true);
      if (l.archived) throw new ConflictException("Student is archived.");
      const row = (
        await sql.query(
          "UPDATE learner_photos SET checked=true,check_engine=$4 WHERE organisation_id=$1 AND learner_id=$2 AND id=$3 RETURNING id",
          [org, id, photoId, result.model],
        )
      ).rows[0];
      if (!row)
        throw new ConflictException("Photo was removed while checking.");
      await this.access.audit(sql, user, org, "learner.photo_checked", {
        learnerId: id,
        photoId,
      });
      return { checked: true };
    });
  }
}
