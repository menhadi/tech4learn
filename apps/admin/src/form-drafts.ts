export const draftPrefix = "t4l:draft:v1:";
export let submittingDraft: string | null = null;
export function submitWithDraft(key: string, work: () => void) {
  const previous = submittingDraft;
  submittingDraft = key;
  try {
    work();
  } finally {
    submittingDraft = previous;
  }
}
export function clearDrafts() {
  try {
    for (const key of Object.keys(localStorage))
      if (key.startsWith(draftPrefix)) localStorage.removeItem(key);
  } catch { /* Unavailable browser storage must not prevent sign out. */ }
}
export function readDraft<T>(
  key: string,
): { savedAt: number; values: T } | null {
  try {
    const raw = localStorage.getItem(key);
    if (!raw) return null;
    const d = JSON.parse(raw);
    if (!d.savedAt || Date.now() - d.savedAt > 7 * 86400000) {
      localStorage.removeItem(key);
      return null;
    }
    return d;
  } catch {
    return null;
  }
}
export function writeDraft(key: string, values: unknown) {
  localStorage.setItem(key, JSON.stringify({ savedAt: Date.now(), values }));
}
export function removeDraft(key: string) {
  try { localStorage.removeItem(key); } catch { /* Browser storage must not turn a confirmed server save into a failed save. */ }
}
