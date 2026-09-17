import { useEffect, useRef, useState } from "react";
import { api, apiBase, ApiError } from "./api";
import { QuestionChoiceField } from "./QuestionChoiceField";
import type { Exam } from "./ExamBuilder";

export function ExamDocuments({
  org,
  central = false,
  record,
  disabled,
  onSaved,
}: {
  org: string;
  central?: boolean;
  record: Exam;
  disabled: boolean;
  onSaved: (record: Exam) => void;
}) {
  const base = central
    ? `/platform/exam-content/${org}/central`
    : `/organisations/${org}/exam-content`;
  const [packageId, setPackage] = useState<number | null>(null);
  const [languageId, setLanguage] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [statuses, setStatuses] = useState<
    { document_type: string; status: string; approved_available: boolean }[]
  >([]);
  async function checkStatus() {
    if (busy || disabled) return;
    setBusy(true);
    setMessage("");
    setStatuses([]);
    const params = new URLSearchParams();
    if (packageId !== null) params.set("package_id", String(packageId));
    if (languageId !== null) params.set("language_id", String(languageId));
    try {
      setStatuses(
        await Promise.all(
          ["questions", "solutions"].map((type) =>
            api<{
              document_type: string;
              status: string;
              approved_available: boolean;
            }>(`${base}/exams/${record.id}/documents/${type}/status?${params}`),
          ),
        ),
      );
    } catch (error) {
      setMessage(
        error instanceof Error ? error.message : "Unable to load PDF status.",
      );
    } finally {
      setBusy(false);
    }
  }
  const [pending, setPending] = useState<{
    fields: {
      package_id: number;
      language_id: number;
      document_type: "questions" | "solutions";
    };
    revision: string;
    request_id: string;
  } | null>(null);
  const active = useRef<AbortController | null>(null);
  async function generate(type: "questions" | "solutions") {
    if (
      busy ||
      disabled ||
      packageId === null ||
      languageId === null ||
      (pending && pending.fields.document_type !== type)
    )
      return;
    const request = pending ?? {
      fields: {
        package_id: packageId,
        language_id: languageId,
        document_type: type,
      },
      revision: record.revision,
      request_id: crypto.randomUUID(),
    };
    setPending(request);
    setStatuses([]);
    setBusy(true);
    setMessage("");
    try {
      await api<Exam>(
        `${base}/exams/${record.id}/actions/generate-document`,
        "POST",
        request,
        35000,
      );
      setPending(null);
      setMessage(
        "PDF generation requested. The native worker must finish before a new download is available.",
      );
    } catch (error) {
      if (error instanceof ApiError && [400, 409].includes(error.status))
        setPending(null);
      setMessage(
        error instanceof Error
          ? error.message
          : "Generation could not be confirmed. Retry the same request.",
      );
    } finally {
      setBusy(false);
    }
  }
  async function reload() {
    setBusy(true);
    setMessage("");
    try {
      const saved = await api<Exam>(`${base}/taxonomy/exams/${record.id}`);
      setPending(null);
      onSaved(saved);
      setMessage(
        "Saved exam reloaded. Check the approved download before requesting another generation.",
      );
    } catch (error) {
      setMessage(
        error instanceof Error ? error.message : "Unable to reload the exam.",
      );
    } finally {
      setBusy(false);
    }
  }
  useEffect(
    () => () => {
      active.current?.abort();
      active.current = null;
    },
    [],
  );
  async function download(type: "questions" | "solutions") {
    if (busy || disabled || pending) return;
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
        `${apiBase}${base}/exams/${record.id}/documents/${type}?${params}`,
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
        Generation requires an assigned package and language. Approve the
        selected translation in the translation review below before generating
        its PDF.
      </p>
      <QuestionChoiceField
        central={central}
        org={org}
        kind="packages"
        label="PDF package"
        value={packageId}
        allowedIds={(record.fields.packages ?? []).map(Number)}
        disabled={disabled || busy || Boolean(pending)}
        onChange={(value) => {
          setPackage(value);
          setStatuses([]);
        }}
      />
      <QuestionChoiceField
        central={central}
        org={org}
        kind="languages"
        label="PDF language"
        value={languageId}
        allowedIds={(record.fields.language_ids ?? []).map(Number)}
        disabled={disabled || busy || Boolean(pending)}
        onChange={(value) => {
          setLanguage(value);
          setStatuses([]);
        }}
      />
      <button
        type="button"
        disabled={disabled || busy || Boolean(pending)}
        onClick={() => void download("questions")}
      >
        Download approved paper
      </button>
      <button
        type="button"
        className="secondary"
        disabled={disabled || busy || Boolean(pending)}
        onClick={() => void download("solutions")}
      >
        Download approved solutions
      </button>
      <button
        type="button"
        className="secondary"
        disabled={busy || disabled}
        onClick={() => void checkStatus()}
      >
        Check PDF status
      </button>
      {statuses.length > 0 && (
        <ul aria-label="PDF build status">
          {statuses.map((item) => (
            <li key={item.document_type}>
              {item.document_type === "questions"
                ? "Question paper"
                : "Solutions"}
              : {item.status.replaceAll("_", " ")}.{" "}
              {item.approved_available
                ? "An approved PDF is available."
                : "No approved PDF is available."}
            </li>
          ))}
        </ul>
      )}
      <details>
        <summary>Generate a replacement PDF</summary>
        <p>
          The last approved document remains downloadable while its replacement
          is processed. This does not change exam answers or student marks.
        </p>
        {(["questions", "solutions"] as const).map((type) => (
          <button
            type="button"
            key={type}
            disabled={
              disabled ||
              busy ||
              packageId === null ||
              languageId === null ||
              Boolean(pending && pending.fields.document_type !== type)
            }
            onClick={() => void generate(type)}
          >
            {pending?.fields.document_type === type ? "Retry" : "Generate"}{" "}
            {type === "questions" ? "paper" : "solutions"} PDF
          </button>
        ))}
        {pending && (
          <p>
            This generation request is awaiting confirmation. Retry keeps the
            same request and selections.
          </p>
        )}
        <button
          type="button"
          className="secondary"
          disabled={disabled || busy}
          onClick={() => void reload()}
        >
          Reload document selections
        </button>
      </details>
      {message && <p role="status">{message}</p>}
    </section>
  );
}
