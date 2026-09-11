import {
  BadRequestException,
  ConflictException,
  ForbiddenException,
  HttpException,
  Injectable,
  NotFoundException,
  UnauthorizedException,
} from "@nestjs/common";
import { randomUUID } from "node:crypto";
import { Database, type SqlClient } from "./database.js";
import {
  digest,
  dummyHash,
  emailValue,
  field,
  hashPassword,
  loginPassword,
  passwordValue,
  token,
  uuid,
  verifyPassword,
} from "./security.js";

export interface Account {
  id: string;
  email: string;
  name: string;
  is_superadmin: boolean;
}
interface LoginAccount extends Account {
  password_hash: string;
}
export interface Organisation {
  id: string;
  name: string;
  slug: string;
  colour: string;
  centre_label: string;
}
const accountColumns = "u.id, u.email, u.name, u.is_superadmin";
const orgColumns = "o.id, o.name, o.slug, o.colour, o.centre_label";

@Injectable()
export class IdentityService {
  constructor(private readonly db: Database) {}
  async audit(
    sql: SqlClient,
    actor: string,
    org: string | null,
    action: string,
  ) {
    await sql.query(
      "INSERT INTO audit_events(id, actor_id, organisation_id, action) VALUES ($1,$2,$3,$4)",
      [randomUUID(), actor, org, action],
    );
  }
  async limit(key: string, max: number, seconds = 900) {
    if (key === "login-global")
      await this.db.query("DELETE FROM auth_limits WHERE expires_at <= now()");
    const { rows } = await this.db.query<{ count: number }>(
      `INSERT INTO auth_limits(key,count,expires_at) VALUES ($1,1,now() + $2 * interval '1 second')
      ON CONFLICT(key) DO UPDATE SET count = CASE WHEN auth_limits.expires_at <= now() THEN 1 ELSE auth_limits.count + 1 END,
      expires_at = CASE WHEN auth_limits.expires_at <= now() THEN now() + $2 * interval '1 second' ELSE auth_limits.expires_at END RETURNING count`,
      [digest(key), seconds],
    );
    if (rows[0].count > max)
      throw new HttpException(
        "Too many attempts. Please try again later.",
        429,
      );
  }
  async login(body: Record<string, unknown>) {
    const email = emailValue(body.email);
    const password = loginPassword(body.password);
    // Global limit bounds password-hashing work even with many invented emails; persisted across restarts.
    await this.limit("login-global", 60, 60);
    await this.limit(`login:${email}`, 10);
    const { rows } = await this.db.query<LoginAccount>(
      `SELECT ${accountColumns}, u.password_hash FROM users u WHERE email=$1`,
      [email],
    );
    const user = rows[0];
    if (
      !(await verifyPassword(password, user?.password_hash ?? dummyHash)) ||
      !user
    )
      throw new UnauthorizedException("Email or password is incorrect.");
    return this.db.transaction(async (sql) => {
      // Serialize against password changes so a revoked credential cannot create a later session.
      const locked = await sql.query<LoginAccount>(
        "SELECT * FROM users WHERE id=$1 FOR UPDATE",
        [user.id],
      );
      if (locked.rows[0].password_hash !== user.password_hash)
        throw new UnauthorizedException("Please sign in again.");
      await sql.query("DELETE FROM sessions WHERE expires_at <= now()");
      const raw = token();
      await sql.query(
        "INSERT INTO sessions(token_hash,user_id,expires_at) VALUES ($1,$2,now() + interval '12 hours')",
        [digest(raw), user.id],
      );
      await this.audit(sql, user.id, null, "auth.login");
      return raw;
    });
  }
  async account(raw?: string): Promise<Account> {
    if (!raw || !/^[a-f0-9]{64}$/.test(raw))
      throw new UnauthorizedException("Please sign in.");
    const { rows } = await this.db.query<Account>(
      `SELECT ${accountColumns} FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=$1 AND s.expires_at > now()`,
      [digest(raw)],
    );
    if (!rows[0])
      throw new UnauthorizedException(
        "Your session has expired. Please sign in again.",
      );
    return rows[0];
  }
  async logout(raw?: string) {
    if (raw)
      await this.db.query("DELETE FROM sessions WHERE token_hash=$1", [
        digest(raw),
      ]);
  }
  async organisations(user: Account) {
    if (user.is_superadmin) {
      await this.audit(this.db, user.id, null, "organisations.list");
      return (
        await this.db.query<Organisation>(
          `SELECT ${orgColumns} FROM organisations o ORDER BY o.name LIMIT 200`,
        )
      ).rows;
    }
    return (
      await this.db.query<Organisation>(
        `SELECT ${orgColumns} FROM organisations o JOIN memberships m ON m.organisation_id=o.id WHERE m.user_id=$1 ORDER BY o.name LIMIT 200`,
        [user.id],
      )
    ).rows;
  }
  async organisation(
    user: Account,
    id: string,
    sql: SqlClient = this.db,
  ): Promise<Organisation> {
    uuid(id);
    const { rows } = await sql.query<Organisation>(
      `SELECT ${orgColumns} FROM organisations o WHERE o.id=$1 AND ($2::boolean OR EXISTS (SELECT 1 FROM memberships m WHERE m.organisation_id=o.id AND m.user_id=$3 AND m.role='organisation_admin'))`,
      [id, user.is_superadmin, user.id],
    );
    if (!rows[0]) throw new NotFoundException("Organisation not found.");
    return rows[0];
  }
  async readOrganisation(user: Account, id: string) {
    const org = await this.organisation(user, id);
    if (user.is_superadmin)
      await this.audit(this.db, user.id, id, "organisation.viewed");
    return org;
  }
  async create(user: Account, body: Record<string, unknown>) {
    if (!user.is_superadmin)
      throw new ForbiddenException(
        "Only superadmins can create organisations.",
      );
    const name = field(body.name, "Organisation name");
    const slug = field(body.slug, "Organisation address", 60).toLowerCase();
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug))
      throw new BadRequestException(
        "Use lowercase letters, numbers and single hyphens for the address.",
      );
    const email = emailValue(body.adminEmail);
    return this.db.transaction(async (sql) => {
      const org: Organisation = {
        id: randomUUID(),
        name,
        slug,
        colour: "#175d50",
        centre_label: "Centre",
      };
      const result = await sql.query(
        "INSERT INTO organisations(id,name,slug) VALUES ($1,$2,$3) ON CONFLICT(slug) DO NOTHING RETURNING id",
        [org.id, name, slug],
      );
      if (!result.rows.length)
        throw new ConflictException(
          "That organisation address is already in use.",
        );
      const invitation = await this.invite(sql, user, org.id, email);
      await this.audit(sql, user.id, org.id, "organisation.created");
      return { organisation: org, invitation };
    });
  }
  private async invite(
    sql: SqlClient,
    user: Account,
    orgId: string,
    email: string,
  ) {
    // Reissuing invalidates older unused links for the same organisation and email.
    await sql.query(
      "DELETE FROM invitations WHERE organisation_id=$1 AND email=$2 AND accepted_at IS NULL",
      [orgId, email],
    );
    const raw = token();
    const { rows } = await sql.query<{ expires_at: Date }>(
      "INSERT INTO invitations(id,organisation_id,email,token_hash,expires_at,created_by) VALUES ($1,$2,$3,$4,now() + interval '3 days',$5) RETURNING expires_at",
      [randomUUID(), orgId, email, digest(raw), user.id],
    );
    await this.audit(sql, user.id, orgId, "invitation.created");
    return { token: raw, email, expiresAt: rows[0].expires_at };
  }
  async addInvitation(
    user: Account,
    id: string,
    body: Record<string, unknown>,
  ) {
    return this.db.transaction(async (sql) => {
      await this.organisation(user, id, sql);
      // Only platform administrators appoint organisation administrators in this release.
      if (!user.is_superadmin)
        throw new ForbiddenException(
          "Only superadmins can appoint organisation admins.",
        );
      return this.invite(sql, user, id, emailValue(body.email));
    });
  }
  async invitation(raw: string) {
    if (!/^[a-f0-9]{64}$/.test(raw))
      throw new NotFoundException("Invitation is invalid or expired.");
    const { rows } = await this.db.query<{
      organisationName: string;
      email: string;
      existingAccount: boolean;
    }>(
      `SELECT o.name AS "organisationName", i.email, EXISTS(SELECT 1 FROM users u WHERE u.email=i.email) AS "existingAccount" FROM invitations i JOIN organisations o ON o.id=i.organisation_id WHERE token_hash=$1 AND accepted_at IS NULL AND expires_at > now()`,
      [digest(raw)],
    );
    if (!rows[0])
      throw new NotFoundException("Invitation is invalid or expired.");
    return rows[0];
  }
  async accept(body: Record<string, unknown>, session?: string) {
    const raw = field(body.token, "Invitation", 64);
    await this.limit("invitation-global", 30, 60);
    const details = await this.invitation(raw);
    let account: Account | undefined;
    let passwordHash: string | undefined;
    let name: string | undefined;
    if (details.existingAccount) {
      account = await this.account(session);
      if (account.email !== details.email)
        throw new ForbiddenException("Sign in with the invited email address.");
    } else {
      name = field(body.name, "Your name");
      passwordHash = await hashPassword(passwordValue(body.password));
    }
    await this.db.transaction(async (sql) => {
      const { rows } = await sql.query<{
        id: string;
        email: string;
        organisation_id: string;
      }>(
        "SELECT * FROM invitations WHERE token_hash=$1 AND accepted_at IS NULL AND expires_at > now() FOR UPDATE",
        [digest(raw)],
      );
      const invitation = rows[0];
      if (!invitation)
        throw new NotFoundException("Invitation is invalid or expired.");
      let userId = account?.id;
      if (!userId) {
        userId = randomUUID();
        const created = await sql.query(
          "INSERT INTO users(id,email,name,password_hash) VALUES ($1,$2,$3,$4) ON CONFLICT(email) DO NOTHING RETURNING id",
          [userId, invitation.email, name, passwordHash],
        );
        if (!created.rows.length)
          throw new ConflictException(
            "An account now exists for this email. Sign in and open the invitation again.",
          );
      }
      await sql.query(
        "INSERT INTO memberships(user_id,organisation_id,role) VALUES ($1,$2,'organisation_admin') ON CONFLICT DO NOTHING",
        [userId, invitation.organisation_id],
      );
      await sql.query("UPDATE invitations SET accepted_at=now() WHERE id=$1", [
        invitation.id,
      ]);
      await this.audit(
        sql,
        userId,
        invitation.organisation_id,
        "invitation.accepted",
      );
    });
    return { ok: true };
  }
  async update(user: Account, id: string, body: Record<string, unknown>) {
    const name = field(body.name, "Organisation name");
    const colour = field(body.colour, "Brand colour", 7);
    if (!/^#[a-f0-9]{6}$/i.test(colour))
      throw new BadRequestException("Choose a valid brand colour.");
    const label = field(body.centre_label, "Centre label", 40);
    return this.db.transaction(async (sql) => {
      await this.organisation(user, id, sql);
      const result = await sql.query<Organisation>(
        "UPDATE organisations SET name=$2,colour=$3,centre_label=$4 WHERE id=$1 RETURNING id,name,slug,colour,centre_label",
        [id, name, colour, label],
      );
      await this.audit(sql, user.id, id, "organisation.updated");
      return result.rows[0];
    });
  }
  async changePassword(user: Account, body: Record<string, unknown>) {
    await this.limit(`password:${user.id}`, 5);
    const current = loginPassword(body.currentPassword);
    const password = passwordValue(body.password);
    const { rows } = await this.db.query<LoginAccount>(
      "SELECT * FROM users WHERE id=$1",
      [user.id],
    );
    if (!(await verifyPassword(current, rows[0].password_hash)))
      throw new UnauthorizedException("Current password is incorrect.");
    const hash = await hashPassword(password);
    await this.db.transaction(async (sql) => {
      const locked = await sql.query<LoginAccount>(
        "SELECT * FROM users WHERE id=$1 FOR UPDATE",
        [user.id],
      );
      if (locked.rows[0].password_hash !== rows[0].password_hash)
        throw new ConflictException("Password changed. Sign in again.");
      await sql.query("UPDATE users SET password_hash=$2 WHERE id=$1", [
        user.id,
        hash,
      ]);
      await sql.query("DELETE FROM sessions WHERE user_id=$1", [user.id]);
      await this.audit(sql, user.id, null, "auth.password_changed");
    });
    return { ok: true };
  }
}
