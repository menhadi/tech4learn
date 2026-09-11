/** Public liveness contract. Does not imply database or integration readiness. */
export interface HealthResponse {
  status: "ok";
  service: "tech4learn-api";
  timestamp: string;
}

/** Planned education module vocabulary; modules are not implemented yet. */
export type ModuleKey = "attendance" | "learning-assessment" | "exams";
export type LocationVerification =
  "verified" | "pending-review" | "unavailable";

export interface Account {
  id: string;
  email: string;
  name: string;
  is_superadmin: boolean;
}
export interface Organisation {
  id: string;
  name: string;
  slug: string;
  colour: string;
  centre_label: string;
}
export interface SessionResponse {
  user: Account;
  organisations: Organisation[];
}
export interface Invitation {
  token: string;
  email: string;
  expiresAt: string;
}
export interface InvitationPreview {
  organisationName: string;
  roleName: string;
  scopeType: "organisation" | "centres" | "groups";
  email: string;
  existingAccount: boolean;
}
