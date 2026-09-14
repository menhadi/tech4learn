import { useRef, useState } from "react";
import { api } from "./api";
import { DraftForm } from "./DraftForm";
import type { Snapshot } from "./ExamQuestionEditor";

export function QuestionImageUpload({
  base,
  record,
  disabled,
  onPending,
  onSaved,
  onReload,
}: {
  base: string;
  record: Snapshot;
  disabled: boolean;
  onPending: (pending: boolean) => void;
  onSaved: (record: Snapshot) => void;
  onReload: () => void;
}) {
  const [field, setField] = useState("question"),
    [asset, setAsset] = useState("");
  const [image, setImage] = useState(""),
    [preview, setPreview] = useState(""),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  const [pending, setPending] = useState<{
    request_id: string;
    revision: string;
    fields: { field: string; image?: string; asset?: string; remove?: true };
  } | null>(null);
  const choosing = useRef(0);
  const removing = asset.startsWith("remove:");
  const fields = [
    ["question", "Question"],
    ["hint", "Hint"],
    ["explanation", "Explanation"],
    ...(record.type === "S" ? [["si_answer1", "Model answer"]] : []),
    ...(record.type === "M"
      ? [1, 2, 3, 4, 5, 6].map((n) => ["option" + n, "Option " + n])
      : []),
  ];
  const assets = [
    ...new Set(
      [
        ...(record.preview_fields?.[field] ?? "").matchAll(
          /t4l-media:([a-f0-9]{64})/g,
        ),
      ].map((m) => m[1]),
    ),
  ];
  return (
    <DraftForm
      draftKey={`question-image-${record.id}-${record.revision}`}
      title="Question image"
      draftState={{ field, asset }}
      restoreState={(state) => {
        if (pending) return;
        if (fields.some(([key]) => key === state?.field)) {
          setField(state.field);
          setAsset(typeof state.asset === "string" ? state.asset : "");
        }
      }}
      onSubmit={async (event) => {
        event.preventDefault();
        if (busy || disabled || (!pending && !image && !removing)) return;
        const request: NonNullable<typeof pending> = pending ?? {
          request_id: crypto.randomUUID(),
          revision: record.revision,
          fields: removing
            ? { field, asset: asset.slice(7), remove: true }
            : { field, image, ...(asset ? { asset } : {}) },
        };
        setPending(request);
        onPending(true);
        setBusy(true);
        setError("");
        try {
          const saved = await api<Snapshot>(
            base + "/image",
            "POST",
            request,
            30000,
          );
          onPending(false);
          setPending(null);
          setImage("");
          setPreview("");
          onSaved(saved);
        } catch (cause) {
          setError(
            (cause instanceof Error
              ? cause.message
              : "Image could not be saved.") +
              " Retry the same upload or reload the saved question before changing it.",
          );
          throw cause;
        } finally {
          setBusy(false);
        }
      }}
    >
      <p>
        PNG, JPEG or WebP, up to 512 KB. Save other question changes first. The
        selected file stays in this tab and is not stored in a recovered draft.
      </p>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      <fieldset disabled={disabled || busy || !!pending}>
        <legend>Image destination</legend>
        <label>
          Field
          <select
            value={field}
            onChange={(e) => {
              setField(e.target.value);
              setAsset("");
            }}
          >
            {fields.map(([key, label]) => (
              <option key={key} value={key}>
                {label}
              </option>
            ))}
          </select>
        </label>
        <label>
          Action
          <select value={asset} onChange={(e) => setAsset(e.target.value)}>
            <option value="">Append new image</option>
            {assets.map((key, i) => (
              <option key={key} value={key}>
                Replace image {i + 1}
              </option>
            ))}
            {assets.map((key, i) => (
              <option key={"remove:" + key} value={"remove:" + key}>
                Remove image {i + 1}
              </option>
            ))}
          </select>
        </label>
        <label>
          Image file
          <input
            type="file"
            disabled={removing}
            accept="image/png,image/jpeg,image/webp"
            onChange={async (e) => {
              const file = e.target.files?.[0];
              const generation = ++choosing.current;
              setImage("");
              setPreview("");
              setError("");
              if (!file) return;
              if (
                !["image/png", "image/jpeg", "image/webp"].includes(
                  file.type,
                ) ||
                file.size === 0 ||
                file.size > 524288
              ) {
                setError("Choose a PNG, JPEG or WebP image up to 512 KB.");
                e.target.value = "";
                return;
              }
              try {
                const value = await new Promise<string>((resolve, reject) => {
                  const reader = new FileReader();
                  reader.onload = () => resolve(String(reader.result));
                  reader.onerror = () =>
                    reject(new Error("The image could not be read."));
                  reader.readAsDataURL(file);
                });
                if (generation !== choosing.current) return;
                setImage(value.slice(value.indexOf(",") + 1));
                setPreview(value);
              } catch {
                if (generation === choosing.current)
                  setError("The image could not be read. Choose it again.");
              }
            }}
          />
        </label>
      </fieldset>
      {removing && (
        <p>
          The selected image will be removed from this field. Other questions
          using the stored file are unchanged.
        </p>
      )}
      {preview && !removing && (
        <img
          src={preview}
          alt="Selected question image"
          style={{ maxWidth: "100%", maxHeight: 240 }}
        />
      )}
      <button disabled={disabled || busy || (!pending && !image && !removing)}>
        {busy
          ? "Saving image…"
          : pending
            ? pending.fields.remove
              ? "Retry image removal"
              : "Retry image upload"
            : removing
              ? "Remove image"
              : "Save image"}
      </button>
      {pending && (
        <button type="button" disabled={busy} onClick={onReload}>
          Reload saved question
        </button>
      )}
    </DraftForm>
  );
}
