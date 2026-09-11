import { BadRequestException } from "@nestjs/common";
import { field } from "./security.js";
export interface FieldDefinition {
  id: string;
  key: string;
  label: string;
  kind: string;
  required: boolean;
  options: string[];
  archived: boolean;
}
export function customValues(
  defs: FieldDefinition[],
  input: unknown,
  before: Record<string, unknown> = {},
) {
  if (!input || typeof input !== "object" || Array.isArray(input))
    throw new BadRequestException("Expected custom field values.");
  const values = input as Record<string, unknown>,
    result = { ...before };
  for (const key of Object.keys(values))
    if (!defs.some((f) => f.key === key))
      throw new BadRequestException(`Unknown custom field: ${key}`);
  for (const f of defs) {
    let v = values[f.key];
    if (f.archived) {
      if (
        v !== undefined &&
        JSON.stringify(v) !== JSON.stringify(before[f.key])
      )
        throw new BadRequestException(
          `Archived field ${f.label} cannot be changed.`,
        );
      continue;
    }
    if (v === undefined || v === null || v === "") {
      if (f.required) throw new BadRequestException(`${f.label} is required.`);
      delete result[f.key];
      continue;
    }
    if (f.kind === "text") v = field(v, f.label, 500);
    if (f.kind === "number" && (typeof v !== "number" || !Number.isFinite(v)))
      throw new BadRequestException(`${f.label} must be a number.`);
    if (f.kind === "boolean" && typeof v !== "boolean")
      throw new BadRequestException(`${f.label} must be true or false.`);
    if (
      f.kind === "choice" &&
      (typeof v !== "string" || !f.options.includes(v))
    )
      throw new BadRequestException(`Choose an option for ${f.label}.`);
    if (
      f.kind === "date" &&
      (typeof v !== "string" ||
        !/^\d{4}-\d{2}-\d{2}$/.test(v) ||
        !Number.isFinite(new Date(v).getTime()) ||
        new Date(v).toISOString().slice(0, 10) !== v)
    )
      throw new BadRequestException(
        `${f.label} needs a valid YYYY-MM-DD date.`,
      );
    result[f.key] = v;
  }
  return result;
}
