import type {
  Announcement, AuthSession, Course, DashboardSummary, Enrollment, Faculty,
  Lecturer, LoginCredentials, PortalEvent, Student, User,
} from "../types/portal";
import { MockApiError, mockRequest } from "./mock";

/** Supports both exposed NEXT_PUBLIC_ settings and Vite-prefixed equivalents. */
const env = import.meta.env;
export const API_URL = (env.VITE_NEXT_PUBLIC_API_URL || env.NEXT_PUBLIC_API_URL || env.VITE_API_URL || "").trim().replace(/\/+$/, "");
const mockSetting = env.VITE_NEXT_PUBLIC_USE_MOCK ?? env.NEXT_PUBLIC_USE_MOCK ?? env.VITE_USE_MOCK;
/** An explicit true/false override takes precedence; otherwise an absent API URL enables mocks. */
export const USE_MOCK = mockSetting == null || mockSetting === "" ? !API_URL : ["true", "1"].includes(String(mockSetting).toLowerCase());
export const AUTH_STORAGE_KEY = "northstar.portal.session";
const SESSION_EVENT = "northstar:session-change";
let memorySession: AuthSession | null = null;
let memoryOnly = false;

export function isSession(value: unknown): value is AuthSession {
  if (!value || typeof value !== "object") return false;
  const session = value as AuthSession;
  const user = session.user;
  return typeof session.token === "string" && !!session.token.trim() && !!user && typeof user === "object" &&
    typeof user.id === "string" && !!user.id.trim() && typeof user.name === "string" &&
    typeof user.email === "string" && ["student", "lecturer", "dept_admin", "super_admin"].includes(user.role) &&
    (user.department_id === null || typeof user.department_id === "string") &&
    (user.department_name === null || typeof user.department_name === "string") &&
    ["active", "inactive", "suspended"].includes(user.status) &&
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

export class ApiError extends Error {
  constructor(public status: number, message: string, public details?: unknown) {
    super(message);
    this.name = "ApiError";
  }
  get isUnauthorized(): boolean { return this.status === 401; }
  get isForbidden(): boolean { return this.status === 403; }
}

export interface ApiRequestOptions extends Omit<RequestInit, "body"> {
  /** JSON-serializable request body. */
  body?: unknown;
  /** Override the stored token; pass null for an unauthenticated request. */
  token?: string | null;
}

/** Generic JSON request. A 401 clears the matching session; a 403 preserves it. */
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
  const response = await fetch(`${API_URL}${normalizedPath}`, {
    ...init,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  });
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

export interface EnrollmentFilters { studentId?: string; courseId?: string }

/** Real backend contract: paths are relative to API_URL and responses are unwrapped JSON. */
export const api = {
  login: async (credentials: LoginCredentials): Promise<AuthSession> => {
    const session = await apiRequest<unknown>("/login", { method: "POST", body: credentials, token: null });
    if (!isSession(session)) throw new ApiError(0, "Invalid authentication response", session);
    return session;
  },
  logout: () => apiRequest<void>("/logout", { method: "POST" }),
  me: () => apiRequest<User>("/me"),
  getDashboard: () => apiRequest<DashboardSummary>("/dashboard"),
  getStudents: () => apiRequest<Student[]>("/students"),
  getFaculty: () => apiRequest<Faculty[]>("/faculty"),
  getLecturers: () => apiRequest<Lecturer[]>("/lecturers"),
  getCourses: () => apiRequest<Course[]>("/courses"),
  getEnrollments: (filters: EnrollmentFilters = {}) => {
    const params = new URLSearchParams();
    if (filters.studentId) params.set("studentId", filters.studentId);
    if (filters.courseId) params.set("courseId", filters.courseId);
    const query = params.toString();
    return apiRequest<Enrollment[]>(`/enrollments${query ? `?${query}` : ""}`);
  },
  getAnnouncements: () => apiRequest<Announcement[]>("/announcements"),
  getEvents: () => apiRequest<PortalEvent[]>("/events"),
};
