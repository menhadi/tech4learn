import { usePassagePreview } from "./usePassagePreview";
import { apiBase } from "./api";
import { FormattedField } from "./ExamQuestionEditor";

/** Keep saved image URLs inert; only scoped, versioned API URLs reach the preview. */
export function ExamPassageWording({
  org,
  central,
  id,
  revision,
  language,
  original,
  value,
  disabled,
  onChange,
}: {
  org: string;
  central: boolean;
  id: number;
  revision: string;
  language: number;
  original: string;
  value: string;
  disabled: boolean;
  onChange: (value: string) => void;
}) {
  const preview = usePassagePreview(original);
  if (/<(?:svg|math-field)\b/i.test(value))
    return (
      <p role="status">
        This version contains media that the passage editor does not support
        yet. Its saved wording will be preserved.
      </p>
    );
  const hasImages = /<img\b/i.test(value);
  if (hasImages && (!id || preview === undefined))
    return (
      <p role="status">
        Passage image preview is unavailable or loading. Saved wording is
        preserved; reload if it does not appear.
      </p>
    );
  const base = central
    ? `/platform/exam-content/${org}/central`
    : `/organisations/${org}/exam-content`;
  return (
    <FormattedField
      label="Passage wording"
      value={value}
      disabled={disabled}
      onChange={onChange}
      originalImageWording={original}
      previewValue={preview}
      mediaBase={`${apiBase}${base}/passages/${id}/languages/${language}/media?revision=${encodeURIComponent(revision)}`}
    />
  );
}
