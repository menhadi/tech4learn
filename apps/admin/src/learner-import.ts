import { readSheet } from "read-excel-file/universal";
export function csvRows(text: string): string[][] {
  const rows: string[][] = [];
  let row: string[] = [],
    value = "",
    quoted = false,
    closed = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (quoted) {
      if (c === '"') {
        if (text[i + 1] === '"') {
          value += '"';
          i++;
        } else {
          quoted = false;
          closed = true;
        }
      } else value += c;
      continue;
    }
    if (c === '"') {
      if (value || closed) throw new Error("Unexpected quote in CSV.");
      quoted = true;
    } else if (c === "," || c === "\n" || c === "\r") {
      row.push(value);
      value = "";
      closed = false;
      if (c !== ",") {
        rows.push(row);
        row = [];
        if (c === "\r" && text[i + 1] === "\n") i++;
      }
    } else {
      if (closed) throw new Error("Unexpected text after CSV quote.");
      value += c;
    }
  }
  if (quoted) throw new Error("Unclosed CSV quote.");
  if (value || row.length || closed) {
    row.push(value);
    rows.push(row);
  }
  return rows;
}
export async function readLearnerFile(file: File) {
  if (file.size > 1024 * 1024)
    throw new Error("Use a file under 1 MB and at most 100 learners.");
  let rows: unknown[][];
  if (/\.csv$/i.test(file.name))
    rows = csvRows((await file.text()).replace(/^\uFEFF/, ""));
  else if (/\.xlsx$/i.test(file.name)) rows = await readSheet(file);
  else throw new Error("Choose CSV or Excel .xlsx (not .xls).");
  if (rows.length < 2 || rows.length > 101)
    throw new Error("Use a header and 1–100 learner rows in the first sheet.");
  const headers = rows[0].map((v) => String(v ?? "").trim());
  if (headers.some((h) => !h) || new Set(headers).size !== headers.length)
    throw new Error("Headers must be nonempty and unique.");
  return rows
    .slice(1)
    .map((row) =>
      Object.fromEntries(
        headers.map((h, i) => [
          h,
          row[i] instanceof Date
            ? (row[i] as Date).toISOString().slice(0, 10)
            : (row[i] ?? ""),
        ]),
      ),
    );
}
