import { ServiceUnavailableException } from "@nestjs/common";

/** Project native paper metadata; persistence and exam rules remain in ExamElite. */
export function centralExamMetadata(
  record: Record<string, any>,
  draft: boolean,
) {
  const invalid = () =>
    new ServiceUnavailableException("Unable to verify central exam settings.");
  const id = (value: unknown): value is number =>
    Number.isSafeInteger(value) && Number(value) > 0 && Number(value) < 1e15;
  const text = (value: unknown, max: number): value is string =>
    typeof value === "string" && Array.from(value).length <= max;
  const number = (value: unknown) => {
    if (
      !(
        typeof value === "number" ||
        (typeof value === "string" && /^\d+(?:\.\d+)?$/.test(value))
      ) ||
      !Number.isFinite(Number(value)) ||
      Number(value) < 0 ||
      Number(value) > 1e9
    )
      throw invalid();
    return Number(value);
  };
  if (
    !record.test_types ||
    typeof record.test_types !== "object" ||
    Array.isArray(record.test_types) ||
    Object.keys(record.test_types).length < 1 ||
    Object.keys(record.test_types).length > 100 ||
    Object.entries(record.test_types).some(
      ([key, value]) =>
        !/^[a-z][a-z0-9_]{0,99}$/.test(key) || !text(value, 200),
    ) ||
    !text(record.timezone, 100) ||
    !record.timezone
  )
    throw invalid();
  try {
    new Intl.DateTimeFormat("en", { timeZone: record.timezone });
  } catch {
    throw invalid();
  }
  const base = {
    test_types: { ...record.test_types } as Record<string, string>,
    timezone: record.timezone as string,
  };
  if (draft) {
    if (
      ["status", "sections", "subject_durations", "paper_subjects"].some(
        (key) => record[key] !== undefined,
      )
    )
      throw invalid();
    return base;
  }
  if (!["Active", "Inactive"].includes(record.status)) throw invalid();
  const list = (key: string) => {
    if (!Array.isArray(record[key]) || record[key].length > 1000)
      throw invalid();
    return record[key] as Record<string, any>[];
  };
  const ordered = (rows: Record<string, any>[], key: string) => {
    let previous = 0;
    for (const row of rows) {
      if (!row || !id(row[key]) || row[key] <= previous) throw invalid();
      previous = row[key];
    }
    return rows;
  };
  const sections = ordered(list("sections"), "id").map((row) => {
    if (!text(row.name, 1000) || row.exam_id !== record.id) throw invalid();
    return {
      id: row.id as number,
      name: row.name,
      display_order: number(row.display_order),
      duration: row.duration === null ? null : number(row.duration),
    };
  });
  const paper_subjects = ordered(list("paper_subjects"), "id").map((row) => {
    if (!text(row.name, 1000)) throw invalid();
    return { id: row.id as number, name: row.name };
  });
  const subject_durations = ordered(
    list("subject_durations"),
    "subject_id",
  ).map((row) => {
    if (row.exam_id !== record.id) throw invalid();
    return {
      subject_id: row.subject_id as number,
      duration: number(row.duration),
    };
  });
  return {
    ...base,
    status: record.status as string,
    sections,
    paper_subjects,
    subject_durations,
  };
}
