/** Public liveness contract. Does not imply database or integration readiness. */
export interface HealthResponse {
  status: 'ok';
  service: 'tech4learn-api';
  timestamp: string;
}

/** Planned domain vocabulary; no persistence or authorisation is implemented yet. */
export type ModuleKey = 'attendance' | 'learning-assessment' | 'exams';
export type LocationVerification = 'verified' | 'pending-review' | 'unavailable';
