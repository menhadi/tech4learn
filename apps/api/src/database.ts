import {
  Injectable,
  OnModuleDestroy,
  ServiceUnavailableException,
} from "@nestjs/common";
import { Pool } from "pg";

export interface SqlClient {
  query<T = Record<string, unknown>>(
    sql: string,
    params?: unknown[],
  ): Promise<{ rows: T[] }>;
}

@Injectable()
export class Database implements SqlClient, OnModuleDestroy {
  private pool = process.env.DATABASE_URL
    ? new Pool({
        connectionString: process.env.DATABASE_URL,
        max: 10,
        connectionTimeoutMillis: 5000,
      })
    : undefined;
  constructor() {
    this.pool?.on("error", () =>
      console.error("Idle database connection failed"),
    );
  }
  async query<T = Record<string, unknown>>(
    sql: string,
    params: unknown[] = [],
  ): Promise<{ rows: T[] }> {
    if (!this.pool)
      throw new ServiceUnavailableException(
        "Organisation services are not configured yet.",
      );
    const result = await this.pool.query(sql, params);
    return { rows: result.rows as T[] };
  }
  async transaction<T>(run: (client: SqlClient) => Promise<T>): Promise<T> {
    if (!this.pool)
      throw new ServiceUnavailableException(
        "Organisation services are not configured yet.",
      );
    const client = await this.pool.connect();
    try {
      await client.query("BEGIN");
      const result = await run(client);
      await client.query("COMMIT");
      return result;
    } catch (error) {
      await client.query("ROLLBACK");
      throw error;
    } finally {
      client.release();
    }
  }
  async onModuleDestroy() {
    await this.pool?.end();
  }
}
