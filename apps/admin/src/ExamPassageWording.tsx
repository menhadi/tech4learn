import { useEffect, useState } from "react";
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
  const [preview, setPreview] = useState<{
    original: string;
    html: string;
  } | null>(null);
  useEffect(() => {
    let active = true;
    setPreview(null);
    if (!/<img\b/i.test(original) || original.length > 200000) return;
    const prepare = async () => {
      const template = document.createElement("template");
      template.innerHTML = original;
      const images = [...template.content.querySelectorAll("img")];
      if (images.length > 50) throw Error("Too many passage images");
      for (const image of images) {
        const source = image.getAttribute("src")?.trim();
        if (!source) throw Error("Missing passage image");
        const digest = await crypto.subtle.digest(
          "SHA-256",
          new TextEncoder().encode(source),
        );
        const asset = [...new Uint8Array(digest)]
          .map((byte) => byte.toString(16).padStart(2, "0"))
          .join("");
        for (const attribute of [...image.attributes])
          image.removeAttribute(attribute.name);
        image.setAttribute("src", `t4l-media:${asset}`);
      }
      if (active) setPreview({ original, html: template.innerHTML });
    };
    void prepare().catch(() => {
      if (active) setPreview(null);
    });
    return () => {
      active = false;
    };
  }, [original]);
  if (/<(?:svg|math-field)\b/i.test(value))
    return (
      <p role="status">
        This version contains media that the passage editor does not support
        yet. Its saved wording will be preserved.
      </p>
    );
  const hasImages = /<img\b/i.test(value);
  if (hasImages && (!id || preview?.original !== original))
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
      previewValue={preview?.original === original ? preview.html : undefined}
      mediaBase={`${apiBase}${base}/passages/${id}/languages/${language}/media?revision=${encodeURIComponent(revision)}`}
    />
  );
}
