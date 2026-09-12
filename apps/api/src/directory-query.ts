import { BadRequestException } from "@nestjs/common";
import type { SqlClient } from "./database.js";
export type DirectoryQuery = {
  search?: string;
  filters?: Record<string, string>;
  sort?: string;
  direction?: string;
  offset?: number;
  limit?: number;
};
export function parseDirectoryQuery(raw?: string): DirectoryQuery {
  try {
    if (!raw) return {};
    if (raw.length > 20000) throw new Error();
    const q = JSON.parse(raw);
    if (!q || typeof q !== "object" || Array.isArray(q)) throw new Error();
    return q;
  } catch {
    throw new BadRequestException("Invalid table query.");
  }
}
export async function directoryPage(
  sql: SqlClient,
  config: {
    select: string;
    from: string;
    scope: string;
    params: unknown[];
    columns: Record<string, string>;
    sort: string;
    id: string;
  },
  query: DirectoryQuery = {},
) {
  const {
    search = "",
    filters = {},
    sort = config.sort,
    direction = "asc",
    offset = 0,
    limit = 50,
  } = query;
  if (
    typeof search !== "string" ||
    search.length > 120 ||
    !filters ||
    typeof filters !== "object" ||
    Array.isArray(filters) ||
    Object.keys(filters).length > 100 ||
    !Number.isSafeInteger(offset) ||
    offset < 0 ||
    ![50, 100, 500].includes(limit) ||
    !Object.hasOwn(config.columns, sort) ||
    !["asc", "desc"].includes(direction)
  )
    throw new BadRequestException("Invalid filters, sort or page size.");
  const params = [...config.params],
    bind = (v: unknown) => {
      params.push(v);
      return `$${params.length}`;
    };
  const clauses = [config.scope];
  if (search) {
    const p = bind(search);
    clauses.push(
      `(${Object.values(config.columns)
        .map((c) => `strpos(lower(COALESCE((${c})::text,'')),lower(${p}))>0`)
        .join(" OR ")})`,
    );
  }
  for (const [k, v] of Object.entries(filters)) {
    if (!Object.hasOwn(config.columns, k) || typeof v !== "string" || v.length > 120)
      throw new BadRequestException("Invalid filter field.");
    if (v)
      clauses.push(
        `strpos(lower(COALESCE((${config.columns[k]})::text,'')),lower(${bind(v)}))>0`,
      );
  }
  const where = clauses.join(" AND ");
  const statement = `WITH page AS (SELECT ${config.select} ${config.from} WHERE ${where} ORDER BY ${config.columns[sort]} ${direction === "desc" ? "DESC" : "ASC"} NULLS LAST,${config.id} LIMIT ${bind(limit)} OFFSET ${bind(offset)}) SELECT (SELECT count(*)::int ${config.from} WHERE ${config.scope}) AS total,(SELECT count(*)::int ${config.from} WHERE ${where}) AS filtered,COALESCE((SELECT json_agg(page) FROM page),'[]'::json) AS rows`;
  return (
    await sql.query<{
      total: number;
      filtered: number;
      rows: Record<string, unknown>[];
    }>(statement, params)
  ).rows[0];
}
