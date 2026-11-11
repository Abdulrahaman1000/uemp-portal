import type {
  AcademicSession, AdminDashboard, AuditLog, AuthSession, ClassSheet, Course, CourseSummary, Department,
  Faculty, GradingScale, Lecturer, LecturerCourse, LecturerDashboard, LoginCredentials, PageMeta, Paginated,
  Program, RegistrationRecord, ResultSubmission, RolePermission, ScoreInput, Student, User, UserRole,
  UserStatus,
} from "../types/portal";
import { MockApiError, mockRequest } from "./mock";

/** Vite exposes only VITE_ variables. VITE_API_URL is the Laravel API base URL. */
const env = import.meta.env;
export const API_URL = String(env.VITE_API_URL || "").trim().replace(/\/+$/, "");
const mockSetting = env.VITE_USE_MOCK;
/** An explicit true/false override wins; otherwise an empty API URL enables mock mode. */
export const USE_MOCK = mockSetting == null || mockSetting === ""
  ? !API_URL
  : ["true", "1"].includes(String(mockSetting).toLowerCase());

export const AUTH_STORAGE_KEY = "uemp.session";
const SESSION_EVENT = "uemp:session-change";
const ROLES: UserRole[] = ["student", "lecturer", "dept_admin", "super_admin"];
const STATUSES: UserStatus[] = ["active", "inactive", "suspended"];

let memorySession: AuthSession | null = null;
let memoryOnly = false;

export function isSession(value: unknown): value is AuthSession {
  if (!value || typeof value !== "object") return false;
  const session = value as AuthSession;
  const user = session.user;
  return typeof session.token === "string" && !!session.token.trim() && !!user && typeof user === "object" &&
    typeof user.id === "string" && !!user.id.trim() && typeof user.name === "string" &&
    typeof user.email === "string" && ROLES.includes(user.role) &&
    (user.department_id === null || typeof user.department_id === "string") &&
    (user.department_name === null || typeof user.department_name === "string") &&
    STATUSES.includes(user.status) &&
    (user.studentId === undefined || typeof user.studentId === "string") &&
    (user.avatarUrl === undefined || typeof user.avatarUrl === "string");
}

/** Reads the current session, including sessions changed by another tab. */
export function getSession(): AuthSession | null {
  if (typeof window === "undefined" || memoryOnly) return memorySession;
  let raw: string | null;
  try {
    raw = window.localStorage.getItem(AUTH_STORAGE_KEY);
  } catch {
    // Storage may be disabled; the in-memory session still works for this page.
    return memorySession;
  }
  if (!raw) return (memorySession = null);
  try {
    const parsed: unknown = JSON.parse(raw);
    return (memorySession = isSession(parsed) ? parsed : null);
  } catch {
    return (memorySession = null);
  }
}

export function setSession(session: AuthSession | null): void {
  if (session !== null && !isSession(session)) throw new Error("Invalid authentication session");
  memorySession = session;
  if (typeof window === "undefined") return;
  try {
    if (session) window.localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(session));
    else window.localStorage.removeItem(AUTH_STORAGE_KEY);
    memoryOnly = false;
  } catch {
    // Keep the in-memory session when storage is unavailable.
    memoryOnly = true;
  }
  window.dispatchEvent(new Event(SESSION_EVENT));
}

export function getToken(): string | null {
  return getSession()?.token ?? null;
}

/** Subscribe to both same-tab and cross-tab session changes. */
export function subscribeToSession(listener: () => void): () => void {
  if (typeof window === "undefined") return () => {};
  const onStorage = (event: StorageEvent) => {
    if (event.key === AUTH_STORAGE_KEY || event.key === null) {
      memoryOnly = false;
      listener();
    }
  };
  window.addEventListener(SESSION_EVENT, listener);
  window.addEventListener("storage", onStorage);
  return () => {
    window.removeEventListener(SESSION_EVENT, listener);
    window.removeEventListener("storage", onStorage);
  };
}

/** Field errors from a Laravel 422 response: { errors: { field: ["message"] } }. */
export type FieldErrors = Record<string, string[]>;

export class ApiError extends Error {
  constructor(public status: number, message: string, public details?: unknown) {
    super(message);
    this.name = "ApiError";
  }
  get isUnauthorized(): boolean { return this.status === 401; }
  get isForbidden(): boolean { return this.status === 403; }
  get isValidation(): boolean { return this.status === 422; }
  /** Per-field messages for a 422 response, or an empty object. */
  get fieldErrors(): FieldErrors {
    const errors = (this.details as { errors?: unknown } | undefined)?.errors;
    return errors && typeof errors === "object" ? (errors as FieldErrors) : {};
  }
}

export interface ApiRequestOptions extends Omit<RequestInit, "body"> {
  /** JSON-serializable request body. */
  body?: unknown;
  /** Override the stored token; pass null for an unauthenticated request. */
  token?: string | null;
}

/** Generic JSON request. A 401 clears the matching session; a 403 keeps it. */
export async function apiRequest<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
  const { body, token: tokenOverride, ...init } = options;
  const token = tokenOverride === undefined ? getToken() : tokenOverride;
  const normalizedPath = path.startsWith("/") ? path : `/${path}`;
  const clearExpiredSession = (status: number) => {
    // Do not erase a newer login if an older request completes late.
    if (status === 401 && token && getToken() === token) setSession(null);
  };

  if (USE_MOCK) {
    try {
      return mockRequest(normalizedPath, { method: init.method, token, body }) as T;
    } catch (error) {
      if (error instanceof MockApiError) {
        clearExpiredSession(error.status);
        throw new ApiError(error.status, error.message);
      }
      throw error;
    }
  }
  if (!API_URL) throw new ApiError(0, "API URL is not configured and mock mode is disabled");
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  if (body !== undefined && !headers.has("Content-Type")) headers.set("Content-Type", "application/json");
  if (token) headers.set("Authorization", `Bearer ${token}`);
  let response: Response;
  try {
    response = await fetch(`${API_URL}${normalizedPath}`, {
      ...init,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch {
    throw new ApiError(0, "Cannot reach the server. Check your connection and try again.");
  }
  const text = await response.text();
  let data: unknown;
  if (text) {
    try { data = JSON.parse(text); } catch { data = text; }
  }
  if (!response.ok) {
    clearExpiredSession(response.status);
    const message = typeof data === "object" && data !== null && "message" in data && typeof data.message === "string"
      ? data.message : typeof data === "string" && data ? data : response.statusText || `Request failed (${response.status})`;
    throw new ApiError(response.status, message, data);
  }
  return data as T;
}

/** Builds a query string, skipping empty values. */
export function toQuery(params: Record<string, string | number | undefined | null> = {}): string {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") search.set(key, String(value));
  });
  const query = search.toString();
  return query ? `?${query}` : "";
}

export type ListParams = Record<string, string | number | undefined | null>;
export type AdminResource = "faculties" | "departments" | "programs" | "courses" | "sessions";
export interface ReportRow { label: string; value: number }
export type ReportType = "students" | "registrations" | "results";
export type AdminSubmissionStatus = ResultSubmission["status"];

interface AdminListTypes {
  faculties: Faculty;
  departments: Department;
  programs: Program;
  courses: Course;
  sessions: AcademicSession;
}

/** Real backend contract: paths are relative to API_URL and responses are unwrapped JSON. */
export const api = {
  login: async (credentials: LoginCredentials): Promise<AuthSession> => {
    const session = await apiRequest<unknown>("/login", { method: "POST", body: credentials, token: null });
    if (!isSession(session)) throw new ApiError(0, "Invalid authentication response", session);
    return session;
  },
  logout: () => apiRequest<void>("/logout", { method: "POST" }),
  me: () => apiRequest<User>("/me"),

  admin: {
    dashboard: () => apiRequest<AdminDashboard>("/admin/dashboard"),
    gradingScale: () => apiRequest<GradingScale>("/admin/grading-scale"),
    roles: () => apiRequest<RolePermission[]>("/admin/roles"),

    // Collections managed by the super admin.
    list: <R extends AdminResource>(resource: R, params: ListParams = {}) =>
      apiRequest<Paginated<AdminListTypes[R]>>(`/admin/${resource}${toQuery(params)}`),
    create: <R extends AdminResource>(resource: R, body: Partial<AdminListTypes[R]>) =>
      apiRequest<AdminListTypes[R]>(`/admin/${resource}`, { method: "POST", body }),
    update: <R extends AdminResource>(resource: R, id: string, body: Partial<AdminListTypes[R]>) =>
      apiRequest<AdminListTypes[R]>(`/admin/${resource}/${id}`, { method: "PUT", body }),
    remove: (resource: AdminResource, id: string) =>
      apiRequest<void>(`/admin/${resource}/${id}`, { method: "DELETE" }),

    // Sessions and semesters: only one is current at a time.
    activateSession: (id: string) => apiRequest<AcademicSession>(`/admin/sessions/${id}/activate`, { method: "POST" }),
    activateSemester: (id: string) => apiRequest<unknown>(`/admin/semesters/${id}/activate`, { method: "POST" }),

    // Users.
    users: (params: ListParams = {}) => apiRequest<Paginated<User>>(`/admin/users${toQuery(params)}`),
    setUserStatus: (id: string, status: UserStatus) =>
      apiRequest<User>(`/admin/users/${id}/status`, { method: "PATCH", body: { status } }),
    setUserRole: (id: string, role: UserRole) =>
      apiRequest<User>(`/admin/users/${id}/role`, { method: "PATCH", body: { role } }),

    // Read-only oversight.
    students: (params: ListParams = {}) => apiRequest<Paginated<Student>>(`/admin/students${toQuery(params)}`),
    lecturers: (params: ListParams = {}) => apiRequest<Paginated<Lecturer>>(`/admin/lecturers${toQuery(params)}`),
    registrations: (params: ListParams = {}) =>
      apiRequest<Paginated<RegistrationRecord>>(`/admin/registrations${toQuery(params)}`),
    submissions: (params: ListParams = {}) =>
      apiRequest<Paginated<ResultSubmission>>(`/admin/submissions${toQuery(params)}`),
    activityLogs: (params: ListParams = {}) =>
      apiRequest<Paginated<AuditLog>>(`/admin/activity-logs${toQuery(params)}`),
    report: (type: ReportType) => apiRequest<ReportRow[]>(`/admin/reports/${type}`),
  },

  // Lecturer: only the courses assigned to the signed-in lecturer in the current session.
  lecturer: {
    dashboard: () => apiRequest<LecturerDashboard>("/lecturer/dashboard"),
    courses: () => apiRequest<{ data: LecturerCourse[]; meta: PageMeta }>("/lecturer/courses"),
    students: (courseId: string) => apiRequest<ClassSheet>(`/lecturer/courses/${courseId}/students`),
    saveResults: (courseId: string, scores: ScoreInput[]) =>
      apiRequest<ClassSheet>(`/lecturer/courses/${courseId}/results`, { method: "PUT", body: { scores } }),
    submitResults: (courseId: string) =>
      apiRequest<ClassSheet>(`/lecturer/courses/${courseId}/results/submit`, { method: "POST" }),
    summary: (courseId: string) => apiRequest<CourseSummary>(`/lecturer/courses/${courseId}/summary`),
    submissions: (params: ListParams = {}) =>
      apiRequest<Paginated<ResultSubmission>>(`/lecturer/submissions${toQuery(params)}`),
  },
};
