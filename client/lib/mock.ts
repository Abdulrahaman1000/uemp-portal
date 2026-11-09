import type {
  Announcement, AuthSession, Course, DashboardSummary, Enrollment, Faculty,
  LoginCredentials, PortalEvent, Student, User,
} from "../types/portal";

/** Demo accounts are for mock mode only; never use these credentials in production. */
export const MOCK_PASSWORD = "demo123";
export const mockUsers: User[] = [
  { id: "u-super-admin", name: "Morgan Ellis", email: "superadmin@northstar.edu", role: "super_admin", department_id: null, department_name: null, status: "active" },
  { id: "u-dept-admin", name: "Jordan Brooks", email: "deptadmin@northstar.edu", role: "dept_admin", department_id: "d-cs", department_name: "Computer Science", status: "active" },
  { id: "u-lecturer", name: "Dr. Maya Chen", email: "lecturer@northstar.edu", role: "lecturer", department_id: "d-cs", department_name: "Computer Science", status: "active" },
  { id: "u-student", name: "Avery Johnson", email: "student@northstar.edu", role: "student", department_id: "d-cs", department_name: "Computer Science", status: "active", studentId: "s-1001" },
];

export const mockStudents: Student[] = [
  { id: "s-1001", studentNumber: "N2401001", name: "Avery Johnson", email: "student@northstar.edu", program: "BSc Computer Science", year: 3, status: "active", advisor: "Dr. Maya Chen" },
  { id: "s-1002", studentNumber: "N2501002", name: "Leila Ahmed", email: "leila.ahmed@northstar.edu", program: "BSc Computer Science", year: 2, status: "active", advisor: "Dr. Maya Chen" },
  { id: "s-1003", studentNumber: "N2301003", name: "Noah Williams", email: "noah.williams@northstar.edu", program: "BA Economics", year: 4, status: "active", advisor: "Prof. Daniel Reyes" },
  { id: "s-1004", studentNumber: "N2401004", name: "Sofia Martinez", email: "sofia.martinez@northstar.edu", program: "BSc Biology", year: 3, status: "on-leave", advisor: "Dr. Priya Shah" },
  { id: "s-1005", studentNumber: "N2501005", name: "Ethan Okafor", email: "ethan.okafor@northstar.edu", program: "BA Economics", year: 2, status: "active", advisor: "Prof. Daniel Reyes" },
];

export const mockFaculty: Faculty[] = [
  { id: "f-1", name: "Dr. Maya Chen", email: "lecturer@northstar.edu", department: "Computer Science", title: "Associate Professor" },
  { id: "f-2", name: "Prof. Daniel Reyes", email: "daniel.reyes@northstar.edu", department: "Economics", title: "Professor" },
  { id: "f-3", name: "Dr. Priya Shah", email: "priya.shah@northstar.edu", department: "Biology", title: "Assistant Professor" },
];

export const mockCourses: Course[] = [
  { id: "c-101", code: "CS301", title: "Data Structures and Algorithms", department: "Computer Science", credits: 4, instructorId: "f-1", instructorName: "Dr. Maya Chen", term: "Fall 2026", schedule: "Mon & Wed, 10:00–11:30", location: "Engineering 204", capacity: 40, enrolled: 2 },
  { id: "c-102", code: "CS320", title: "Database Systems", department: "Computer Science", credits: 3, instructorId: "f-1", instructorName: "Dr. Maya Chen", term: "Fall 2026", schedule: "Tue & Thu, 13:00–14:15", location: "Engineering 110", capacity: 35, enrolled: 2 },
  { id: "c-103", code: "ECON210", title: "Intermediate Microeconomics", department: "Economics", credits: 3, instructorId: "f-2", instructorName: "Prof. Daniel Reyes", term: "Fall 2026", schedule: "Mon & Wed, 14:00–15:15", location: "Liberal Arts 302", capacity: 45, enrolled: 2 },
  { id: "c-104", code: "BIO205", title: "Cell Biology", department: "Biology", credits: 4, instructorId: "f-3", instructorName: "Dr. Priya Shah", term: "Fall 2026", schedule: "Tue & Thu, 09:00–10:30", location: "Science Hall 125", capacity: 30, enrolled: 0 },
];

export const mockEnrollments: Enrollment[] = [
  { id: "e-1", studentId: "s-1001", courseId: "c-101", status: "enrolled" },
  { id: "e-2", studentId: "s-1001", courseId: "c-102", status: "enrolled" },
  { id: "e-3", studentId: "s-1002", courseId: "c-101", status: "enrolled" },
  { id: "e-4", studentId: "s-1002", courseId: "c-102", status: "enrolled" },
  { id: "e-5", studentId: "s-1003", courseId: "c-103", status: "enrolled" },
  { id: "e-6", studentId: "s-1005", courseId: "c-103", status: "enrolled" },
];

export const mockAnnouncements: Announcement[] = [
  { id: "a-1", title: "Fall registration is open", body: "Review your degree plan and register for fall courses through the student portal.", author: "Office of the Registrar", publishedAt: "2026-07-15T09:00:00Z", audience: "all" },
  { id: "a-2", title: "Academic advising appointments", body: "Book an appointment with your advisor before the add/drop deadline.", author: "Student Success Center", publishedAt: "2026-07-21T14:00:00Z", audience: "student" },
  { id: "a-3", title: "Submit course syllabi", body: "Please upload fall syllabi to your department by August 21.", author: "Academic Affairs", publishedAt: "2026-07-23T10:00:00Z", audience: "lecturer" },
];

export const mockEvents: PortalEvent[] = [
  { id: "ev-1", title: "Fall semester begins", startsAt: "2026-08-31T08:00:00-04:00", endsAt: "2026-08-31T17:00:00-04:00", location: "Campus-wide", category: "academic" },
  { id: "ev-2", title: "Student organizations fair", startsAt: "2026-09-02T11:00:00-04:00", endsAt: "2026-09-02T15:00:00-04:00", location: "University Quad", category: "campus" },
  { id: "ev-3", title: "Add/drop deadline", startsAt: "2026-09-11T09:00:00-04:00", endsAt: "2026-09-11T17:00:00-04:00", location: "Online", category: "deadline" },
];

export class MockApiError extends Error {
  constructor(public status: number, message: string) {
    super(message);
    this.name = "MockApiError";
  }
}

function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T;
}

export function mockLogin(credentials: LoginCredentials): AuthSession {
  const user = mockUsers.find((item) => item.email.toLowerCase() === credentials.email.trim().toLowerCase());
  if (!user || credentials.password !== MOCK_PASSWORD) throw new MockApiError(401, "Invalid email or password");
  return clone({ token: `mock-token-${user.id}`, user });
}

function requireUser(token?: string | null): User {
  const user = mockUsers.find((item) => token === `mock-token-${item.id}`);
  if (!user) throw new MockApiError(401, "Authentication required");
  return user;
}

function visibleAnnouncements(user: User): Announcement[] {
  return mockAnnouncements.filter((item) => item.audience === "all" || item.audience === user.role);
}

/** Implements the same HTTP paths as the real API. Mock tokens are not secure. */
export function mockRequest(path: string, options: { method?: string; token?: string | null; body?: unknown } = {}): unknown {
  const url = new URL(path, "http://mock.local");
  const method = (options.method || "GET").toUpperCase();
  const pathname = url.pathname.replace(/\/+$/, "") || "/";
  if (pathname === "/login" && method === "POST") {
    const credentials = options.body as LoginCredentials | undefined;
    if (typeof credentials?.email !== "string" || typeof credentials?.password !== "string") throw new MockApiError(400, "Email and password are required");
    return mockLogin(credentials);
  }
  const user = requireUser(options.token);
  if (pathname === "/me" && method === "GET") return clone(user);
  if (pathname === "/logout" && method === "POST") return undefined;
  if (method !== "GET") throw new MockApiError(404, "Mock endpoint not found");

  switch (pathname) {
    case "/dashboard": {
      const summary: DashboardSummary = {
        totalStudents: mockStudents.filter((item) => item.status === "active").length,
        totalFaculty: mockFaculty.length,
        activeCourses: mockCourses.length,
        totalEnrollments: mockEnrollments.filter((item) => item.status === "enrolled").length,
        announcements: visibleAnnouncements(user),
        upcomingEvents: mockEvents,
      };
      return clone(summary);
    }
    case "/students":
      if (user.role === "student") return clone(mockStudents.filter((item) => item.id === user.studentId));
      return clone(mockStudents);
    case "/faculty":
    case "/lecturers": return clone(mockFaculty);
    case "/courses": return clone(mockCourses);
    case "/enrollments": {
      const studentId = url.searchParams.get("studentId");
      const courseId = url.searchParams.get("courseId");
      if (user.role === "student" && studentId && studentId !== user.studentId) throw new MockApiError(403, "You cannot view another student's enrollments");
      return clone(mockEnrollments.filter((item) =>
        (user.role !== "student" || item.studentId === user.studentId) &&
        (!studentId || item.studentId === studentId) && (!courseId || item.courseId === courseId)));
    }
    case "/announcements": return clone(visibleAnnouncements(user));
    case "/events": return clone(mockEvents);
    default: throw new MockApiError(404, "Mock endpoint not found");
  }
}
