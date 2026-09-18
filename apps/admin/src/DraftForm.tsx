import {
  createContext,
  useContext,
  useEffect,
  useRef,
  useState,
  type FormHTMLAttributes,
  type ReactNode,
  type Ref,
} from "react";
import {
  draftPrefix,
  readDraft,
  writeDraft,
  submitWithDraft,
} from "./form-drafts";
const Scope = createContext<string | null>(null);
export function DraftScope({
  user,
  org,
  children,
}: {
  user: string;
  org: string;
  children: ReactNode;
}) {
  return (
    <Scope.Provider value={JSON.stringify([user, org])}>
      {children}
    </Scope.Provider>
  );
}
export function useDraftKey(id: string) {
  const scope = useContext(Scope);
  return scope ? draftPrefix + scope + ":" + id : null;
}
type Value = { value: string; checked?: boolean };
function controls(form: HTMLFormElement) {
  const seen = new Map<string, number>();
  return [
    ...form.querySelectorAll<
      HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
    >("input,select,textarea"),
  ].flatMap((el) => {
    const label =
      el.getAttribute("aria-label") || el.closest("label")?.textContent || "";
    if (
      el.closest('[data-no-draft="true"]') ||
      ["password", "file", "hidden", "submit", "button"].includes(el.type) ||
      ("readOnly" in el && el.readOnly) ||
      /password|token|secret|api.?key|attest|confirm|duplicate|withdraw|acknowledge|consent|I have recorded|I have reviewed/i.test(
        el.name + " " + label,
      )
    )
      return [];
    const base = el.name || el.id || label.trim().slice(0, 180);
    if (!base) return [];
    const n = seen.get(base) || 0;
    seen.set(base, n + 1);
    return [{ key: base + ":" + n, el }];
  });
}
function snapshot(form: HTMLFormElement) {
  return Object.fromEntries(
    controls(form).map(({ key, el }) => [
      key,
      {
        value: el.value,
        ...(el instanceof HTMLInputElement &&
        ["checkbox", "radio"].includes(el.type)
          ? { checked: el.checked }
          : {}),
      },
    ]),
  ) as Record<string, Value>;
}
export function DraftForm({
  draftKey,
  title = "Details",
  draftEnabled = true,
  formRef,
  draftState,
  restoreState,
  children,
  onSubmit,
  ...props
}: FormHTMLAttributes<HTMLFormElement> & {
  draftKey: string;
  title?: string;
  draftEnabled?: boolean;
  formRef?: Ref<HTMLFormElement>;
  draftState?: unknown;
  restoreState?: (state: any) => void;
}) {
  const storageKey = useDraftKey(draftKey),
    form = useRef<HTMLFormElement>(null),
    restoring = useRef(false);
  const latest = useRef(""),
    submitted = useRef(""),
    dirty = useRef(false);
  const currentDraftState = useRef(draftState);
  currentDraftState.current = draftState;
  const [status, setStatus] = useState(
      "Changes are saved as a draft on this device. Submit to save the record. Drafts expire after 7 days and are cleared on sign out.",
    ),
    [saved, setSaved] =
      useState<
        ReturnType<
          typeof readDraft<{ fields: Record<string, Value>; state?: unknown }>
        >
      >(null);
  useEffect(() => {
    if (storageKey && draftEnabled) setSaved(readDraft(storageKey));
  }, [storageKey, draftEnabled]);
  useEffect(() => {
    function complete(event: Event) {
      const detail = (event as CustomEvent).detail;
      if (detail?.draftKey !== storageKey || !submitted.current) return;
      if (latest.current === submitted.current) {
        try {
          localStorage.removeItem(storageKey!);
          dirty.current = false;
          setSaved(null);
          setStatus("Saved to the server.");
        } catch {}
        submitted.current = "";
      }
    }
    window.addEventListener("t4l:write-saved", complete);
    return () => window.removeEventListener("t4l:write-saved", complete);
  }, [storageKey]);
  function persist() {
    if (!storageKey || !draftEnabled || restoring.current || !form.current)
      return;
    try {
      const values = {
        fields: snapshot(form.current),
        state: currentDraftState.current,
      };
      latest.current = JSON.stringify(values);
      writeDraft(storageKey, values);
      setStatus(
        `Draft saved on this device at ${new Date().toLocaleTimeString()}.`,
      );
    } catch {
      setStatus(
        "Draft storage is unavailable or full. Keep this page open until you save.",
      );
    }
  }
  useEffect(() => {
    if (dirty.current) persist();
  }, [draftState]);
  async function restore() {
    if (!saved?.values?.fields || !form.current) return;
    restoring.current = true;
    try {
      if (restoreState && saved.values.state !== undefined) {
        restoreState(saved.values.state);
        await new Promise((r) => setTimeout(r, 0));
      }
      for (const key of Object.keys(saved.values.fields)) {
        const entry = controls(form.current).find((c) => c.key === key);
        if (!entry || entry.el.matches(":disabled")) continue;
        const { el } = entry;
        const v = saved.values.fields[key];
        if (el instanceof HTMLInputElement && typeof v.checked === "boolean") {
          if (el.checked !== v.checked) el.click();
        } else {
          const prototype =
            el instanceof HTMLSelectElement
              ? HTMLSelectElement.prototype
              : el instanceof HTMLTextAreaElement
                ? HTMLTextAreaElement.prototype
                : HTMLInputElement.prototype;
          Object.getOwnPropertyDescriptor(prototype, "value")!.set!.call(
            el,
            v.value,
          );
          el.dispatchEvent(new Event("input", { bubbles: true }));
          el.dispatchEvent(new Event("change", { bubbles: true }));
        }
        await new Promise((resolve) => setTimeout(resolve, 0));
      }
      setStatus("Draft restored. Review the sections before submitting.");
      dirty.current = true;
      setSaved(null);
    } finally {
      restoring.current = false;
      persist();
    }
  }
  return (
    <form
      {...props}
      ref={(node) => {
        form.current = node;
        if (typeof formRef === "function") formRef(node);
        else if (formRef) formRef.current = node;
      }}
      className={`sectioned-form ${props.className || ""}`}
      onInputCapture={() => {
        dirty.current = true;
        persist();
      }}
      onChangeCapture={() => {
        dirty.current = true;
        persist();
      }}
      onSubmit={(event) => {
        if (storageKey && draftEnabled) {
          persist();
          submitted.current = latest.current;
          submitWithDraft(storageKey, () => onSubmit?.(event));
        } else onSubmit?.(event);
      }}
    >
      <header className="form-heading">
        <h3>{title}</h3>
        {storageKey && draftEnabled && (
          <>
            <p role="status">
              {status} {!navigator.onLine && "You are offline."}
            </p>
            {saved && (
              <div className="draft-recovery">
                <strong>
                  Unfinished draft from{" "}
                  {new Date(saved.savedAt).toLocaleString()}
                </strong>
                <button type="button" onClick={() => void restore()}>
                  Restore draft
                </button>
              </div>
            )}
            <button
              type="button"
              className="secondary"
              onClick={() => {
                try {
                  localStorage.removeItem(storageKey);
                } catch {
                  setStatus(
                    "Browser storage is unavailable. The draft could not be discarded.",
                  );
                  return;
                }
                dirty.current = false;
                setSaved(null);
                setStatus(
                  "Stored draft discarded. Current entries remain on screen.",
                );
              }}
            >
              Discard saved draft
            </button>
          </>
        )}
      </header>
      {children}
    </form>
  );
}
