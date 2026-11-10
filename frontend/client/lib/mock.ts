import type {
  AcademicSession, AdminDashboard, AuditLog, AuthSession, Course, Department, Faculty,
  GradingScale, LoginCredentials, Lecturer, Paginated, Program, RegistrationRecord,
  ResultSubmission, RolePermission, Semester, Student, User, UserRole, UserStatus,
} from "../types/portal";

/** Demo accounts are for mock mode only; never use these credentials in production. */
export const MOCK_PASSWORD = "demo123";

export const mockUsers: User[] = [
  { id: "u-super-admin", name: "Morgan Ellis", email: "superadmin@northstar.edu", role: "super_admin", department_id: null, department_name: null, status: "active", lastActive: "2026-11-09" },
  { id: "u-dept-admin", name: "Jordan Brooks", email: "deptadmin@northstar.edu", role: "dept_admin", department_id: "d-cs", department_name: "Computer Science", status: "active", lastActive: "2026-11-09" },
  { id: "u-lecturer", name: "Dr. Maya Chen", email: "lecturer@northstar.edu", role: "lecturer", department_id: "d-cs", department_name: "Computer Science", status: "active", lastActive: "2026-11-08" },
  { id: "u-student", name: "Avery Johnson", email: "student@northstar.edu", role: "student", department_id: "d-cs", department_name: "Computer Science", status: "active", studentId: "s-1001", lastActive: "2026-11-09" },
];

/** Extra accounts shown in the Users page (they cannot log in to the mock). */
const extraUsers: User[] = [
  { id: "u-econ-admin", name: "Prof. Helen Cooper", email: "h.cooper@northstar.edu", role: "dept_admin", department_id: "d-econ", department_name: "Economics", status: "active", lastActive: "2026-11-07" },
  { id: "u-lec-2", name: "Prof. Daniel Reyes", email: "daniel.reyes@northstar.edu", role: "lecturer", department_id: "d-econ", department_name: "Economics", status: "active", lastActive: "2026-11-06" },
  { id: "u-lec-3", name: "Dr. Priya Shah", email: "priya.shah@northstar.edu", role: "lecturer", department_id: "d-math", department_name: "Mathematics", status: "inactive", lastActive: "2026-10-20" },
  { id: "u-stu-2", name: "Leila Ahmed", email: "leila.ahmed@northstar.edu", role: "student", department_id: "d-cs", department_name: "Computer Science", status: "active", studentId: "s-1002", lastActive: "2026-11-05" },
  { id: "u-stu-6", name: "Ethan Okafor", email: "ethan.okafor@northstar.edu", role: "student", department_id: "d-econ", department_name: "Economics", status: "suspended", studentId: "s-1006", lastActive: "2026-10-02" },
];

// ---------- Seed data (kept in memory so edits show during a demo) ----------

const faculties: Faculty[] = [
  { id: "f-sci", name: "Faculty of Science", code: "SCI", dean: "Prof. Robert Harris", departmentCount: 2 },
  { id: "f-soc", name: "Faculty of Social Sciences", code: "SOC", dean: "Prof. Grace Evans", departmentCount: 1 },
];

const departments: Department[] = [
  { id: "d-cs", name: "Computer Science", code: "CSC", facultyId: "f-sci", facultyName: "Faculty of Science", head: "Dr. Maya Chen", programCount: 2 },
  { id: "d-math", name: "Mathematics", code: "MTH", facultyId: "f-sci", facultyName: "Faculty of Science", head: "Dr. Priya Shah", programCount: 1 },
  { id: "d-econ", name: "Economics", code: "ECO", facultyId: "f-soc", facultyName: "Faculty of Social Sciences", head: "Prof. Daniel Reyes", programCount: 1 },
];

const programs: Program[] = [
  { id: "p-cs", name: "Computer Science", award: "B.Sc.", departmentId: "d-cs", departmentName: "Computer Science", durationYears: 4, studentCount: 3 },
  { id: "p-se", name: "Software Engineering", award: "B.Sc.", departmentId: "d-cs", departmentName: "Computer Science", durationYears: 4, studentCount: 1 },
  { id: "p-math", name: "Mathematics", award: "B.Sc.", departmentId: "d-math", departmentName: "Mathematics", durationYears: 4, studentCount: 1 },
  { id: "p-econ", name: "Economics", award: "B.Sc.", departmentId: "d-econ", departmentName: "Economics", durationYears: 4, studentCount: 1 },
];

const sessions: AcademicSession[] = [
  {
    id: "ses-2627", name: "2026/2027", startDate: "2026-09-14", endDate: "2027-07-30", isCurrent: true,
    semesters: [
      { id: "sem-2627-1", sessionId: "ses-2627", name: "First", startDate: "2026-09-14", endDate: "2027-01-29", isCurrent: true },
      { id: "sem-2627-2", sessionId: "ses-2627", name: "Second", startDate: "2027-02-15", endDate: "2027-07-30", isCurrent: false },
    ],
  },
  {
    id: "ses-2526", name: "2025/2026", startDate: "2025-09-15", endDate: "2026-07-31", isCurrent: false,
    semesters: [
      { id: "sem-2526-1", sessionId: "ses-2526", name: "First", startDate: "2025-09-15", endDate: "2026-01-30", isCurrent: false },
      { id: "sem-2526-2", sessionId: "ses-2526", name: "Second", startDate: "2026-02-16", endDate: "2026-07-31", isCurrent: false },
    ],
  },
];

const students: Student[] = [
  { id: "s-1001", matricNo: "CSC/2023/001", name: "Avery Johnson", email: "student@northstar.edu", departmentId: "d-cs", departmentName: "Computer Science", facultyName: "Faculty of Science", programId: "p-cs", programName: "Computer Science", level: 300, status: "active" },
  { id: "s-1002", matricNo: "CSC/2024/014", name: "Leila Ahmed", email: "leila.ahmed@northstar.edu", departmentId: "d-cs", departmentName: "Computer Science", facultyName: "Faculty of Science", programId: "p-cs", programName: "Computer Science", level: 200, status: "active" },
  { id: "s-1003", matricNo: "CSC/2022/007", name: "Noah Williams", email: "noah.williams@northstar.edu", departmentId: "d-cs", departmentName: "Computer Science", facultyName: "Faculty of Science", programId: "p-se", programName: "Software Engineering", level: 400, status: "active" },
  { id: "s-1004", matricNo: "MTH/2023/003", name: "Sofia Martinez", email: "sofia.martinez@northstar.edu", departmentId: "d-math", departmentName: "Mathematics", facultyName: "Faculty of Science", programId: "p-math", programName: "Mathematics", level: 300, status: "active" },
  { id: "s-1005", matricNo: "CSC/2025/021", name: "Amina Yusuf", email: "amina.yusuf@northstar.edu", departmentId: "d-cs", departmentName: "Computer Science", facultyName: "Faculty of Science", programId: "p-cs", programName: "Computer Science", level: 100, status: "inactive" },
  { id: "s-1006", matricNo: "ECO/2024/009", name: "Ethan Okafor", email: "ethan.okafor@northstar.edu", departmentId: "d-econ", departmentName: "Economics", facultyName: "Faculty of Social Sciences", programId: "p-econ", programName: "Economics", level: 200, status: "suspended" },
];

const lecturers: Lecturer[] = [
  { id: "l-1", name: "Dr. Maya Chen", email: "lecturer@northstar.edu", departmentId: "d-cs", departmentName: "Computer Science", specialization: "Algorithms", courseCount: 3, status: "active" },
  { id: "l-2", name: "Prof. Daniel Reyes", email: "daniel.reyes@northstar.edu", departmentId: "d-econ", departmentName: "Economics", specialization: "Microeconomics", courseCount: 1, status: "active" },
  { id: "l-3", name: "Dr. Priya Shah", email: "priya.shah@northstar.edu", departmentId: "d-math", departmentName: "Mathematics", specialization: "Linear Algebra", courseCount: 1, status: "inactive" },
];

const courses: Course[] = [
  { id: "c-1", code: "CSC301", title: "Data Structures and Algorithms", units: 3, level: 300, semester: "First", type: "core", departmentId: "d-cs", departmentName: "Computer Science", programId: "p-cs", lecturerId: "l-1", lecturerName: "Dr. Maya Chen" },
  { id: "c-2", code: "CSC305", title: "Database Systems", units: 3, level: 300, semester: "First", type: "core", departmentId: "d-cs", departmentName: "Computer Science", programId: "p-cs", lecturerId: "l-1", lecturerName: "Dr. Maya Chen" },
  { id: "c-3", code: "CSC311", title: "Human-Computer Interaction", units: 2, level: 300, semester: "First", type: "elective", departmentId: "d-cs", departmentName: "Computer Science", programId: "p-cs", lecturerId: null, lecturerName: null },
  { id: "c-4", code: "CSC202", title: "Discrete Structures", units: 3, level: 200, semester: "First", type: "core", departmentId: "d-cs", departmentName: "Computer Science", programId: "p-cs", lecturerId: "l-1", lecturerName: "Dr. Maya Chen" },
  { id: "c-5", code: "CSC312", title: "Operating Systems", units: 3, level: 300, semester: "Second", type: "core", departmentId: "d-cs", departmentName: "Computer Science", programId: "p-cs", lecturerId: null, lecturerName: null },
  { id: "c-6", code: "MTH301", title: "Linear Algebra II", units: 3, level: 300, semester: "First", type: "core", departmentId: "d-math", departmentName: "Mathematics", programId: "p-math", lecturerId: "l-3", lecturerName: "Dr. Priya Shah" },
  { id: "c-7", code: "ECO201", title: "Intermediate Microeconomics", units: 3, level: 200, semester: "First", type: "core", departmentId: "d-econ", departmentName: "Economics", programId: "p-econ", lecturerId: "l-2", lecturerName: "Prof. Daniel Reyes" },
  { id: "c-8", code: "CSC101", title: "Introduction to Programming", units: 3, level: 100, semester: "First", type: "core", departmentId: "d-cs", departmentName: "Computer Science", programId: "p-cs", lecturerId: "l-1", lecturerName: "Dr. Maya Chen" },
];

const registrations: RegistrationRecord[] = [
  { id: "reg-1", studentId: "s-1001", studentName: "Avery Johnson", matricNo: "CSC/2023/001", departmentName: "Computer Science", level: 300, sessionName: "2026/2027", semester: "First", courses: [{ courseId: "c-1", code: "CSC301", title: "Data Structures and Algorithms", units: 3 }, { courseId: "c-2", code: "CSC305", title: "Database Systems", units: 3 }], totalUnits: 6, registeredAt: "2026-10-02" },
  { id: "reg-2", studentId: "s-1002", studentName: "Leila Ahmed", matricNo: "CSC/2024/014", departmentName: "Computer Science", level: 200, sessionName: "2026/2027", semester: "First", courses: [{ courseId: "c-4", code: "CSC202", title: "Discrete Structures", units: 3 }], totalUnits: 3, registeredAt: "2026-10-03" },
  { id: "reg-3", studentId: "s-1006", studentName: "Ethan Okafor", matricNo: "ECO/2024/009", departmentName: "Economics", level: 200, sessionName: "2026/2027", semester: "First", courses: [{ courseId: "c-7", code: "ECO201", title: "Intermediate Microeconomics", units: 3 }], totalUnits: 3, registeredAt: "2026-10-04" },
];

const submissions: ResultSubmission[] = [
  { id: "rs-1", courseId: "c-1", courseCode: "CSC301", courseTitle: "Data Structures and Algorithms", departmentId: "d-cs", departmentName: "Computer Science", lecturerName: "Dr. Maya Chen", sessionName: "2026/2027", semester: "First", studentCount: 2, status: "submitted", submittedAt: "2026-11-05",
    audit: [{ id: "a1", at: "2026-11-05T09:12:00", actorName: "Dr. Maya Chen", actorRole: "lecturer", action: "Submitted results for review" }] },
  { id: "rs-2", courseId: "c-2", courseCode: "CSC305", courseTitle: "Database Systems", departmentId: "d-cs", departmentName: "Computer Science", lecturerName: "Dr. Maya Chen", sessionName: "2026/2027", semester: "First", studentCount: 2, status: "approved", submittedAt: "2026-11-01",
    audit: [{ id: "a2", at: "2026-11-01T10:00:00", actorName: "Dr. Maya Chen", actorRole: "lecturer", action: "Submitted results for review" }, { id: "a3", at: "2026-11-02T14:30:00", actorName: "Jordan Brooks", actorRole: "dept_admin", action: "Approved results" }] },
  { id: "rs-3", courseId: "c-4", courseCode: "CSC202", courseTitle: "Discrete Structures", departmentId: "d-cs", departmentName: "Computer Science", lecturerName: "Dr. Maya Chen", sessionName: "2026/2027", semester: "First", studentCount: 1, status: "rejected", submittedAt: "2026-11-03", feedback: "Exam scores for two students look swapped. Please check and resubmit.",
    audit: [{ id: "a4", at: "2026-11-03T08:45:00", actorName: "Dr. Maya Chen", actorRole: "lecturer", action: "Submitted results for review" }, { id: "a5", at: "2026-11-04T11:05:00", actorName: "Jordan Brooks", actorRole: "dept_admin", action: "Rejected results", note: "Exam scores for two students look swapped." }] },
  { id: "rs-4", courseId: "c-6", courseCode: "MTH301", courseTitle: "Linear Algebra II", departmentId: "d-math", departmentName: "Mathematics", lecturerName: "Dr. Priya Shah", sessionName: "2026/2027", semester: "First", studentCount: 1, status: "reopened", submittedAt: "2026-10-28", feedback: "Reopened to correct a missing CA score.",
    audit: [{ id: "a6", at: "2026-10-28T09:00:00", actorName: "Dr. Priya Shah", actorRole: "lecturer", action: "Submitted results for review" }, { id: "a7", at: "2026-10-29T16:20:00", actorName: "Morgan Ellis", actorRole: "super_admin", action: "Reopened results", note: "Missing CA score." }] },
  { id: "rs-5", courseId: "c-7", courseCode: "ECO201", courseTitle: "Intermediate Microeconomics", departmentId: "d-econ", departmentName: "Economics", lecturerName: "Prof. Daniel Reyes", sessionName: "2026/2027", semester: "First", studentCount: 1, status: "draft", submittedAt: null,
    audit: [{ id: "a8", at: "2026-11-06T13:00:00", actorName: "Prof. Daniel Reyes", actorRole: "lecturer", action: "Saved draft" }] },
];

const auditLogs: AuditLog[] = [
  { id: "log-8", timestamp: "2026-11-09T09:30:00", userName: "Morgan Ellis", role: "super_admin", action: "SESSION_ACTIVATED", entity: "Session 2026/2027", details: "Set as current session", ip: "102.89.34.10" },
  { id: "log-7", timestamp: "2026-11-05T09:12:00", userName: "Dr. Maya Chen", role: "lecturer", action: "RESULT_SUBMITTED", entity: "CSC301", details: "Submitted 2 results for review", ip: "102.89.34.21" },
  { id: "log-6", timestamp: "2026-11-04T11:05:00", userName: "Jordan Brooks", role: "dept_admin", action: "RESULT_REJECTED", entity: "CSC202", details: "Feedback: exam scores look swapped", ip: "102.89.40.5" },
  { id: "log-5", timestamp: "2026-11-02T14:30:00", userName: "Jordan Brooks", role: "dept_admin", action: "RESULT_APPROVED", entity: "CSC305", details: "Approved 2 results", ip: "102.89.40.5" },
  { id: "log-4", timestamp: "2026-10-29T16:20:00", userName: "Morgan Ellis", role: "super_admin", action: "RESULT_REOPENED", entity: "MTH301", details: "Reopened to correct a missing CA score", ip: "102.89.34.10" },
  { id: "log-3", timestamp: "2026-10-04T10:15:00", userName: "Ethan Okafor", role: "student", action: "COURSE_REGISTERED", entity: "ECO201", details: "Registered 1 course (3 units)", ip: "197.210.8.77" },
  { id: "log-2", timestamp: "2026-10-02T09:40:00", userName: "Avery Johnson", role: "student", action: "COURSE_REGISTERED", entity: "CSC301, CSC305", details: "Registered 2 courses (6 units)", ip: "197.210.8.12" },
  { id: "log-1", timestamp: "2026-09-14T08:00:00", userName: "Morgan Ellis", role: "super_admin", action: "USER_STATUS_CHANGED", entity: "Ethan Okafor", details: "Status set to suspended", ip: "102.89.34.10" },
];

const gradingScale: GradingScale = {
  caMax: 30,
  examMax: 70,
  gpaFormula: "GPA = sum(grade point x course units) / sum(course units)",
  bands: [
    { min: 70, max: 100, grade: "A", point: 5 },
    { min: 60, max: 69, grade: "B", point: 4 },
    { min: 50, max: 59, grade: "C", point: 3 },
    { min: 45, max: 49, grade: "D", point: 2 },
    { min: 40, max: 44, grade: "E", point: 1 },
    { min: 0, max: 39, grade: "F", point: 0 },
  ],
};

const rolePermissions: Omit<RolePermission, "userCount">[] = [
  { role: "student", label: "Student", description: "Own academic records only.", permissions: ["View and edit own limited profile", "View courses for own department, program, level and semester", "Register courses (no duplicates)", "View approved results only", "View academic history and CGPA", "Print registration summary and result statement"] },
  { role: "lecturer", label: "Lecturer", description: "Assigned courses only.", permissions: ["View assigned courses and class lists", "Enter CA and exam scores", "Save drafts and submit results for review", "View submission status and feedback", "View course summaries"] },
  { role: "dept_admin", label: "Department Admin", description: "Own department only.", permissions: ["Manage department students, lecturers and courses", "Assign lecturers to courses", "View department registrations", "Approve, reject (with feedback), return or reopen results", "View department reports"] },
  { role: "super_admin", label: "Super Admin", description: "University-wide access.", permissions: ["Manage faculties, departments and programs", "Manage sessions and semesters (one current)", "Manage users, status and roles", "Manage courses and allocations", "Oversee registrations and results", "View reports and the activity log"] },
];

// ---------- Helpers ----------

export class MockApiError extends Error {
  constructor(public status: number, message: string) {
    super(message);
    this.name = "MockApiError";
  }
}

function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T;
}

function allUsers(): User[] {
  return [...mockUsers, ...extraUsers];
}

function paginate<T>(items: T[], url: URL): Paginated<T> {
  const perPage = Math.max(1, Number(url.searchParams.get("per_page")) || 100);
  const page = Math.max(1, Number(url.searchParams.get("page")) || 1);
  const q = (url.searchParams.get("q") || "").trim().toLowerCase();
  const filtered = q ? items.filter((item) => JSON.stringify(item).toLowerCase().includes(q)) : items;
  return clone({
    data: filtered.slice((page - 1) * perPage, page * perPage),
    meta: { current_page: page, last_page: Math.max(1, Math.ceil(filtered.length / perPage)), total: filtered.length },
  });
}

function logAction(actor: User, action: string, entity: string, details: string) {
  auditLogs.unshift({ id: `log-${Date.now()}`, timestamp: new Date().toISOString(), userName: actor.name, role: actor.role, action, entity, details, ip: "127.0.0.1" });
}

export function mockLogin(credentials: LoginCredentials): AuthSession {
  const user = mockUsers.find((item) => item.email.toLowerCase() === credentials.email.trim().toLowerCase());
  if (!user || credentials.password !== MOCK_PASSWORD) throw new MockApiError(401, "Invalid email or password");
  if (user.status !== "active") throw new MockApiError(403, "This account is not active");
  return clone({ token: `mock-token-${user.id}`, user });
}

function requireUser(token?: string | null): User {
  const user = mockUsers.find((item) => token === `mock-token-${item.id}`);
  if (!user) throw new MockApiError(401, "Authentication required");
  return user;
}

type Body = Record<string, unknown>;
type Named = { id: string; name?: string; code?: string };

/** Generic in-memory CRUD for super admin collections. */
const collections: Record<string, Named[]> = {
  faculties: faculties as Named[],
  departments: departments as Named[],
  programs: programs as Named[],
  courses: courses as Named[],
  sessions: sessions as Named[],
};

function adminRequest(user: User, segments: string[], method: string, url: URL, body: Body): unknown {
  if (user.role !== "super_admin") throw new MockApiError(403, "Super admin access required");
  const [resource, id, action] = segments;

  if (resource === "dashboard" && method === "GET") {
    const currentSession = sessions.find((s) => s.isCurrent);
    const dashboard: AdminDashboard = {
      totalStudents: students.length,
      totalLecturers: lecturers.length,
      totalFaculties: faculties.length,
      totalDepartments: departments.length,
      activePrograms: programs.length,
      pendingSubmissions: submissions.filter((s) => s.status === "submitted").length,
      currentSession: currentSession?.name ?? "None",
      currentSemester: currentSession?.semesters.find((s) => s.isCurrent)?.name ?? "First",
    };
    return clone(dashboard);
  }

  if (resource === "grading-scale" && method === "GET") return clone(gradingScale);

  if (resource === "roles" && method === "GET") {
    const counts = allUsers().reduce<Record<string, number>>((acc, u) => ({ ...acc, [u.role]: (acc[u.role] ?? 0) + 1 }), {});
    return clone(rolePermissions.map((r) => ({ ...r, userCount: counts[r.role] ?? 0 })));
  }

  if (resource === "students" && method === "GET") return paginate(students, url);
  if (resource === "lecturers" && method === "GET") return paginate(lecturers, url);
  if (resource === "registrations" && method === "GET") return paginate(registrations, url);

  if (resource === "submissions" && method === "GET") {
    const status = url.searchParams.get("status");
    return paginate(status ? submissions.filter((s) => s.status === status) : submissions, url);
  }

  if (resource === "activity-logs" && method === "GET") {
    const u = url.searchParams.get("user")?.toLowerCase();
    const a = url.searchParams.get("action");
    const from = url.searchParams.get("from");
    const to = url.searchParams.get("to");
    return paginate(auditLogs.filter((l) =>
      (!u || l.userName.toLowerCase().includes(u)) && (!a || l.action === a) &&
      (!from || l.timestamp.slice(0, 10) >= from) && (!to || l.timestamp.slice(0, 10) <= to)), url);
  }

  if (resource === "reports" && method === "GET") {
    const type = id;
    if (type === "registrations") return clone(departments.map((d) => ({ label: d.name, value: registrations.filter((r) => r.departmentName === d.name).length })));
    if (type === "results") return clone((["draft", "submitted", "approved", "rejected", "reopened"] as const).map((s) => ({ label: s, value: submissions.filter((x) => x.status === s).length })));
    return clone(departments.map((d) => ({ label: d.name, value: students.filter((s) => s.departmentId === d.id).length })));
  }

  // Sessions: activate a session or a semester (only one current at a time).
  if (resource === "sessions" && id && action === "activate" && method === "POST") {
    const target = sessions.find((s) => s.id === id);
    if (!target) throw new MockApiError(404, "Session not found");
    sessions.forEach((s) => { s.isCurrent = s.id === id; s.semesters.forEach((sem) => { sem.isCurrent = false; }); });
    target.semesters[0].isCurrent = true;
    logAction(user, "SESSION_ACTIVATED", `Session ${target.name}`, "Set as current session");
    return clone(target);
  }
  if (resource === "semesters" && id && action === "activate" && method === "POST") {
    const parent = sessions.find((s) => s.semesters.some((sem) => sem.id === id));
    if (!parent) throw new MockApiError(404, "Semester not found");
    sessions.forEach((s) => { s.isCurrent = s.id === parent.id; s.semesters.forEach((sem) => { sem.isCurrent = sem.id === id; }); });
    const sem = parent.semesters.find((x) => x.id === id) as Semester;
    logAction(user, "SEMESTER_ACTIVATED", `${parent.name} ${sem.name} semester`, "Set as current semester");
    return clone(sem);
  }

  // Users: list, status, role.
  if (resource === "users") {
    if (method === "GET" && !id) return paginate(allUsers(), url);
    const target = allUsers().find((u) => u.id === id);
    if (!target) throw new MockApiError(404, "User not found");
    if (method === "PATCH" && action === "status") {
      const status = body.status as UserStatus;
      if (!["active", "inactive", "suspended"].includes(status)) throw new MockApiError(422, "Invalid status");
      if (target.id === user.id) throw new MockApiError(422, "You cannot change your own status");
      target.status = status;
      logAction(user, "USER_STATUS_CHANGED", target.name, `Status set to ${status}`);
      return clone(target);
    }
    if (method === "PATCH" && action === "role") {
      const role = body.role as UserRole;
      if (!["student", "lecturer", "dept_admin", "super_admin"].includes(role)) throw new MockApiError(422, "Invalid role");
      if (target.id === user.id) throw new MockApiError(422, "You cannot change your own role");
      target.role = role;
      logAction(user, "USER_ROLE_CHANGED", target.name, `Role set to ${role}`);
      return clone(target);
    }
  }

  // Generic create / update / delete for the other collections.
  const list = resource ? collections[resource] : undefined;
  if (list) {
    if (method === "GET" && !id) return paginate(list, url);
    if (method === "POST" && !id) {
      const record = { ...body, id: `${resource.slice(0, 3)}-${Date.now()}` } as Named;
      list.unshift(record);
      logAction(user, "RECORD_CREATED", `${resource}`, record.name ?? record.code ?? record.id);
      return clone(record);
    }
    const index = list.findIndex((item) => item.id === id);
    if (index < 0) throw new MockApiError(404, "Record not found");
    if (method === "PUT") {
      list[index] = { ...list[index], ...body, id: list[index].id };
      logAction(user, "RECORD_UPDATED", `${resource}`, list[index].name ?? list[index].code ?? list[index].id);
      return clone(list[index]);
    }
    if (method === "DELETE") {
      const [removed] = list.splice(index, 1);
      logAction(user, "RECORD_DELETED", `${resource}`, removed.name ?? removed.code ?? removed.id);
      return undefined;
    }
  }

  throw new MockApiError(404, "Mock endpoint not found");
}

/** Implements the same HTTP paths as the real API. Mock tokens are not secure. */
export function mockRequest(path: string, options: { method?: string; token?: string | null; body?: unknown } = {}): unknown {
  const url = new URL(path, "http://mock.local");
  const method = (options.method || "GET").toUpperCase();
  const pathname = url.pathname.replace(/\/+$/, "") || "/";
  if (pathname === "/login" && method === "POST") {
    const credentials = options.body as LoginCredentials | undefined;
    if (typeof credentials?.email !== "string" || typeof credentials?.password !== "string") throw new MockApiError(422, "Email and password are required");
    return mockLogin(credentials);
  }
  const user = requireUser(options.token);
  if (pathname === "/me" && method === "GET") return clone(user);
  if (pathname === "/logout" && method === "POST") return undefined;

  const segments = pathname.split("/").filter(Boolean);
  if (segments[0] === "admin") return adminRequest(user, segments.slice(1), method, url, (options.body ?? {}) as Body);

  // Student, lecturer and dept routes are added in T1.2 to T1.4.
  throw new MockApiError(404, "Mock endpoint not found");
}