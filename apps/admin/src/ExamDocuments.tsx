import { useEffect, useRef, useState } from "react";
import { apiBase } from "./api";
import { QuestionChoiceField } from "./QuestionChoiceField";
import type { Exam } from "./ExamBuilder";

export function ExamDocuments({
  org,
  record,
  disabled,
}: {
  org: string;
  record: Exam;
  disabled: boolean;
}) {
  const [packageId, setPackage] = useState<number | null>(null);
  const [languageId, setLanguage] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const active = useRef<AbortController | null>(null);
  useEffect(
    () => () => {
      active.current?.abort();
      active.current = null;
    },
    [],
  );
  async function download(type: "questions" | "solutions") {
    if (busy || disabled) return;
    const controller = new AbortController();
    active.current = controller;
    const timer = window.setTimeout(() => controller.abort(), 35000);
    setBusy(true);
    setMessage("");
    try {
      const params = new URLSearchParams();
      if (packageId !== null) params.set("package_id", String(packageId));
      if (languageId !== null) params.set("language_id", String(languageId));
      const response = await fetch(
        `${apiBase}/organisations/${org}/exam-content/exams/${record.id}/documents/${type}?${params}`,
        {
          credentials: "include",
          signal: controller.signal,
          redirect: "error",
        },
      );
      if (!response.ok) {
        const result = await response.json().catch(() => null);
        throw new Error(
          typeof result?.message === "string"
            ? result.message
            : "The approved PDF is unavailable for this selection.",
        );
      }
      if (
        response.headers.get("content-type")?.split(";")[0] !==
        "application/pdf"
      )
        throw new Error("The server did not return a PDF.");
      const blob = await response.blob();
      if (controller.signal.aborted) return;
      if (blob.size > 10485760 || (await blob.slice(0, 5).text()) !== "%PDF-")
        throw new Error("The PDF is invalid or too large.");
      if (controller.signal.aborted || active.current !== controller) return;
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `exam-${record.id}-${type}.pdf`;
      document.body.append(link);
      link.click();
      link.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 60000);
      setMessage("PDF download requested. Check your browser downloads.");
    } catch (error) {
      if (active.current !== controller) return;
      if (!controller.signal.aborted)
        setMessage(
          error instanceof Error ? error.message : "Unable to download PDF.",
        );
      else setMessage("The download was cancelled or timed out. Try again.");
    } finally {
      window.clearTimeout(timer);
      if (active.current === controller) {
        active.current = null;
        setBusy(false);
      }
    }
  }
  return (
    <section className="panel">
      <h3>Approved PDF downloads</h3>
      <p>
        Download the last approved paper or solutions. Choose the package and
        language used for that document; None selects an artifact without that
        variant. Changes to exam settings do not regenerate an approved PDF.
      </p>
      <p>
        PDF generation and approval inside Tech4Learn are still being
        integrated.
      </p>
      <QuestionChoiceField
        org={org}
        kind="packages"
        label="PDF package"
        value={packageId}
        allowedIds={(record.fields.packages ?? []).map(Number)}
        disabled={disabled || busy}
        onChange={setPackage}
      />
      <QuestionChoiceField
        org={org}
        kind="languages"
        label="PDF language"
        value={languageId}
        allowedIds={(record.fields.language_ids ?? []).map(Number)}
        disabled={disabled || busy}
        onChange={setLanguage}
      />
      <button
        type="button"
        disabled={disabled || busy}
        onClick={() => void download("questions")}
      >
        Download approved paper
      </button>
      <button
        type="button"
        className="secondary"
        disabled={disabled || busy}
        onClick={() => void download("solutions")}
      >
        Download approved solutions
      </button>
      {message && <p role="status">{message}</p>}
    </section>
  );
}
