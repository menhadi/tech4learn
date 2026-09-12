import {
  Injectable,
  ForbiddenException,
  BadRequestException,
  ConflictException,
  ServiceUnavailableException,
} from "@nestjs/common";
import { request } from "node:http";
import { randomUUID } from "node:crypto";
import { Database } from "./database.js";
import type { Account } from "./identity.service.js";

export function controlRequest(body: Record<string, unknown>) {
  const action = body.action;
  if (
    !["start", "stop", "restart", "limits"].includes(String(action)) ||
    body.confirm !== true
  )
    throw new BadRequestException("Choose and confirm a face-engine action.");
  if (
    action === "limits" &&
    (!Number.isFinite(body.cpus) || !Number.isFinite(body.memoryGiB))
  )
    throw new BadRequestException("Enter numeric CPU and RAM limits.");
  return action === "limits"
    ? { action, cpus: body.cpus, memoryGiB: body.memoryGiB }
    : { action };
}
@Injectable()
export class FaceControlClient {
  call(body: unknown): Promise<Record<string, any>> {
    const payload = JSON.stringify(body);
    return new Promise((resolve, reject) => {
      const req = request(
        {
          socketPath: "/run/tech4learn-face-control/control.sock",
          path: "/",
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Content-Length": Buffer.byteLength(payload),
          },
        },
        (res) => {
          res.on("error", reject);
          let data = "";
          res.on("data", (chunk) => {
            data += chunk;
            if (data.length > 32768)
              req.destroy(new Error("Oversized controller response"));
          });
          res.on("end", () => {
            try {
              const value = JSON.parse(data);
              if (res.statusCode !== 200)
                reject(
                  res.statusCode === 400
                    ? new BadRequestException(String(value.error).slice(0, 250))
                    : new Error("Controller rejected the operation."),
                );
              else resolve(value);
            } catch (e) {
              reject(e);
            }
          });
        },
      );
      req.setTimeout(65000, () =>
        req.destroy(
          new Error("Controller timeout. Refresh status before retrying."),
        ),
      );
      req.on("error", reject);
      req.end(payload);
    });
  }
}
@Injectable()
export class FaceControlService {
  constructor(
    private readonly db: Database,
    private readonly client: FaceControlClient,
  ) {}
  private guard(user: Account) {
    if (!user.is_superadmin)
      throw new ForbiddenException(
        "Only platform superadmins can manage the face engine.",
      );
  }
  async status(user: Account) {
    this.guard(user);
    const queue = (
      await this.db.query(
        "SELECT paused FROM attendance_face_worker WHERE id=1",
      )
    ).rows[0];
    try {
      return {
        available: true,
        ...(await this.client.call({ action: "status" })),
        paused: queue?.paused,
      };
    } catch {
      return {
        available: false,
        paused: queue?.paused,
        message:
          "The local face controller is unavailable. Install or check the Tech4Learn face-control service.",
      };
    }
  }
  async change(user: Account, body: Record<string, unknown>) {
    this.guard(user);
    const input = controlRequest(body);
    const outcome = await this.db
      .transaction(async (sql) => {
        const slot = (
          await sql.query<{ lease_until: string | null }>(
            "SELECT * FROM attendance_face_worker WHERE id=1 FOR UPDATE NOWAIT",
          )
        ).rows[0];
        if (!slot)
          throw new ServiceUnavailableException(
            "Apply the face-control migration first.",
          );
        if (
          slot.lease_until &&
          new Date(slot.lease_until).getTime() > Date.now()
        )
          throw new ConflictException(
            "An attendance comparison is processing. Wait for it to finish.",
          );
        await sql.query(
          "UPDATE attendance_face_worker SET paused=true WHERE id=1",
        );
        await sql.query(
          "INSERT INTO audit_events(id,actor_id,action,details) VALUES($1,$2,'face_engine.requested',$3::jsonb)",
          [randomUUID(), user.id, JSON.stringify(input)],
        );
        try {
          const result = await this.client.call(input);
          // Leave comparisons paused after stop or while the engine is still warming up.
          const paused = result.running !== true || result.ready !== true;
          await sql.query(
            "UPDATE attendance_face_worker SET paused=$1 WHERE id=1",
            [paused],
          );
          await sql.query(
            "INSERT INTO audit_events(id,actor_id,action,details) VALUES($1,$2,'face_engine.completed',$3::jsonb)",
            [randomUUID(), user.id, JSON.stringify({ ...input, paused })],
          );
          return { result: { ...result, available: true, paused } };
        } catch (error) {
          await sql.query(
            "INSERT INTO audit_events(id,actor_id,action) VALUES($1,$2,'face_engine.failed')",
            [randomUUID(), user.id],
          );
          return {
            error:
              error instanceof BadRequestException
                ? error.message
                : "Operation failed or timed out.",
          };
        }
      })
      .catch((error: unknown) => {
        if ((error as { code?: string })?.code === "55P03")
          throw new ConflictException(
            "Another attendance or engine operation is active. Refresh and retry.",
          );
        throw error;
      });
    if (outcome.error)
      throw new ServiceUnavailableException(
        outcome.error +
          " Comparisons remain paused. Refresh status, then use Start / resume when ready.",
      );
    return outcome.result;
  }
}
