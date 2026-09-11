export const apiBase = (
  import.meta.env.VITE_API_URL ||
  (import.meta.env.DEV ? "http://localhost:3000/api/v1" : "/api/v1")
).replace(/\/$/, "");
export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
  ) {
    super(message);
  }
}
export async function api<T>(
  path: string,
  method = "GET",
  body?: unknown,
): Promise<T> {
  const response = await fetch(`${apiBase}${path}`, {
    method,
    credentials: "include",
    signal: AbortSignal.timeout(15000),
    headers:
      body === undefined
        ? {}
        : { "Content-Type": "application/json", "X-Tech4Learn-Request": "1" },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const result = await response.json().catch(() => null);
  if (!response.ok)
    throw new ApiError(
      typeof result?.message === "string"
        ? result.message
        : "Something went wrong. Please try again.",
      response.status,
    );
  return result;
}
