export type ApiRuntimeMode = "legacy" | "attendance";

export function apiRuntimeMode(value = process.env.TECH4LEARN_API_MODE): ApiRuntimeMode {
  if (value === undefined || value === "legacy") return "legacy";
  if (value === "attendance") return "attendance";
  throw new Error("TECH4LEARN_API_MODE must be legacy or attendance.");
}
