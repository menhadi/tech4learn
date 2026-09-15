import { ServiceUnavailableException } from "@nestjs/common";

const questionFields = [
  "question",
  "option1",
  "option2",
  "option3",
  "option4",
  "option5",
  "option6",
  "hint",
  "explanation",
  "fill_blank",
  "si_answer1",
];
const examFields = ["name", "instruction", "syllabus"];
const invalid = (): never => {
  throw new ServiceUnavailableException(
    "The translation review could not be loaded.",
  );
};
function wording(
  value: unknown,
  fields: string[],
  nullable = false,
): Record<string, string | null> | null {
  if (value === null && nullable) return null;
  if (!value || typeof value !== "object" || Array.isArray(value))
    return invalid();
  const data = value as Record<string, unknown>;
  return Object.fromEntries(
    fields.map((field) => {
      const text = data[field];
      if (text !== null && (typeof text !== "string" || text.length > 200000))
        return invalid();
      return [field, text as string | null];
    }),
  );
}

// Preserve native wording as data. Consumers must use the controlled rich-text
// preview, never render this markup or its storage URLs directly.
export function translationReview(
  value: any,
  exam: number,
  language: number,
  after: number,
  revision?: string,
) {
  if (
    !value ||
    value.exam_id !== exam ||
    value.language_id !== language ||
    typeof value.language_name !== "string" ||
    value.language_name.length > 200 ||
    typeof value.revision !== "string" ||
    !/^[a-f0-9]{64}$/.test(value.revision) ||
    (revision !== undefined && revision !== value.revision) ||
    typeof value.approved !== "boolean" ||
    !Array.isArray(value.items) ||
    value.items.length > 50
  )
    return invalid();
  const p = value.progress;
  if (
    !p ||
    typeof p.status !== "string" ||
    p.status.length > 40 ||
    typeof p.exam_content_ready !== "boolean" ||
    ![p.total, p.remaining, p.translated].every(
      (n) => Number.isSafeInteger(n) && n >= 0 && n <= 2000,
    ) ||
    p.remaining + p.translated !== p.total
  )
    return invalid();
  let previous = after;
  const items = value.items.map((item: any) => {
    if (
      !item ||
      !Number.isSafeInteger(item.question_id) ||
      item.question_id <= previous ||
      item.question_id >= 1e15 ||
      !Array.isArray(item.stale_fields) ||
      item.stale_fields.length > questionFields.length ||
      item.stale_fields.some(
        (field: unknown) =>
          typeof field !== "string" || !questionFields.includes(field),
      ) ||
      new Set(item.stale_fields).size !== item.stale_fields.length
    )
      return invalid();
    previous = item.question_id;
    return {
      question_id: item.question_id as number,
      source: wording(item.source, questionFields),
      translation: wording(item.translation, questionFields, true),
      stale_fields: item.stale_fields as string[],
    };
  });
  if (
    items.length > p.total ||
    (value.next !== null && (items.length !== 50 || value.next !== previous))
  )
    return invalid();
  return {
    exam_id: exam,
    language_id: language,
    language_name: value.language_name as string,
    revision: value.revision as string,
    approved: value.approved as boolean,
    progress: {
      status: p.status as string,
      translated: p.translated as number,
      remaining: p.remaining as number,
      total: p.total as number,
      exam_content_ready: p.exam_content_ready as boolean,
    },
    source: wording(value.source, examFields),
    translation: wording(value.translation, examFields, true),
    items,
    next: value.next as number | null,
  };
}
