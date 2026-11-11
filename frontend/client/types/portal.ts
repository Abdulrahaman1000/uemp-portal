// Shared types for the UEMP frontend. They mirror the Laravel API contract.

export type UserRole = "student" | "lecturer" | "dept_admin" | "super_admin";
export type UserStatus = "active" | "inactive" | "suspended";

export interface User {
  id: string;
  name: string;
  email: string;
  role: UserRole;
  department_id: string | null;
  department_name: string | null;
  status: UserStatus;
  avatarUrl?: string;
  /** Present for student accounts and used to scope student records. */
  studentId?: string;
  lastActive?: string;
}

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface AuthSession {
  token: string;
  user: User;
}

/** List responses from the API: { data, meta }. */
export interface PageMeta {
  current_page: number;
  last_page: number;
  total: number;
}
export interface Paginated<T> {
  data: T[];
  meta: PageMeta;
}

// ---------- Academic structure ----------

export interface Faculty {
  id: string;
  name: string;
  code: string;
  dean: string;
  departmentCount: number;
}

export interface Department {
  id: string;
  name: string;
  code: string;
  facultyId: string;
  facultyName: string;
  head: string;
  programCount: number;
}

export interface Program {
  id: string;
  name: string;
  award: string; // e.g. B.Sc.
  departmentId: string;
  departmentName: string;
  durationYears: number;
  studentCount: number;
}

export type SemesterName = "First" | "Second";

export interface Semester {
  id: string;
  sessionId: string;
  name: SemesterName;
  startDate: string;
  endDate: string;
  isCurrent: boolean;
}

export interface AcademicSession {
  id: string;
  name: string; // e.g. 2026/2027
  startDate: string;
  endDate: string;
  isCurrent: boolean;
  semesters: Semester[];
}

// ---------- People ----------

export type StudentStatus = "active" | "inactive" | "suspended" | "graduated";

export interface Student {
  id: string;
  matricNo: string;
  name: string;
  email: string;
  departmentId: string;
  departmentName: string;
  facultyName: string;
  programId: string;
  programName: string;
  level: number; // 100, 200, ...
  status: StudentStatus;
}

export interface Lecturer {
  id: string;
  name: string;
  email: string;
  departmentId: string;
  departmentName: string;
  specialization: string;
  courseCount: number;
  status: UserStatus;
}

// ---------- Courses and registration ----------

export type CourseType = "core" | "elective";

export interface Course {
  id: string;
  code: string;
  title: string;
  units: number;
  level: number;
  semester: SemesterName;
  type: CourseType;
  departmentId: string;
  departmentName: string;
  programId: string;
  lecturerId: string | null;
  lecturerName: string | null;
}

export interface RegisteredCourse {
  courseId: string;
  code: string;
  title: string;
  units: number;
}

export interface RegistrationRecord {
  id: string;
  studentId: string;
  studentName: string;
  matricNo: string;
  departmentName: string;
  level: number;
  sessionName: string;
  semester: SemesterName;
  courses: RegisteredCourse[];
  totalUnits: number;
  registeredAt: string;
}

// ---------- Results ----------

export type ResultStatus = "draft" | "submitted" | "approved" | "rejected" | "reopened";

export interface AuditEntry {
  id: string;
  at: string;
  actorName: string;
  actorRole: UserRole;
  action: string;
  note?: string;
}

export interface ResultSubmission {
  id: string;
  courseId: string;
  courseCode: string;
  courseTitle: string;
  departmentId: string;
  departmentName: string;
  lecturerName: string;
  sessionName: string;
  semester: SemesterName;
  studentCount: number;
  status: ResultStatus;
  submittedAt: string | null;
  feedback?: string;
  audit: AuditEntry[];
}

export interface GradeBand {
  min: number;
  max: number;
  grade: string;
  point: number;
}

export interface GradingScale {
  caMax: number;
  examMax: number;
  bands: GradeBand[];
  gpaFormula: string;
}

// ---------- Administration ----------

export interface RolePermission {
  role: UserRole;
  label: string;
  description: string;
  userCount: number;
  permissions: string[];
}

export interface AuditLog {
  id: string;
  timestamp: string;
  userName: string;
  role: UserRole;
  action: string;
  entity: string;
  details: string;
  ip: string;
}

export interface AdminDashboard {
  totalStudents: number;
  totalLecturers: number;
  totalFaculties: number;
  totalDepartments: number;
  activePrograms: number;
  pendingSubmissions: number;
  currentSession: string;
  currentSemester: SemesterName;
}

// ---------- Lecturer (matches /api/lecturer/*) ----------

export interface LecturerDashboard {
  lecturerName: string;
  departmentName: string;
  assignedCourses: number;
  totalStudents: number;
  /** Assigned courses of the current semester that have no result sheet yet. */
  notStarted: number;
  submissionsByStatus: Record<ResultStatus, number>;
  currentSession: string;
  currentSemester: SemesterName;
}

export interface LecturerCourse extends Course {
  studentCount: number;
  submissionStatus: ResultStatus;
  feedback: string | null;
}

export interface ClassRow {
  studentId: string;
  matricNo: string;
  studentName: string;
  level: number;
  caScore: number | null;
  examScore: number | null;
  totalScore: number | null;
  grade: string | null;
  gradePoint: number | null;
}

/** The class list of one course with its scores, status and the grading limits. */
export interface ClassSheet {
  courseId: string;
  courseCode: string;
  courseTitle: string;
  units: number;
  sessionName: string;
  semester: SemesterName;
  status: ResultStatus;
  feedback: string | null;
  canEdit: boolean;
  caMax: number;
  examMax: number;
  students: ClassRow[];
}

export interface ScoreInput {
  studentId: string;
  caScore: number | null;
  examScore: number | null;
}

export interface CourseSummary {
  courseId: string;
  courseCode: string;
  courseTitle: string;
  status: ResultStatus;
  totalStudents: number;
  scoredStudents: number;
  average: number | null;
  highest: number | null;
  lowest: number | null;
  passRate: number | null;
  gradeDistribution: Record<string, number>;
}

// ---------- Student (matches /api/student/*) ----------

/** The signed-in student's own profile. Only phone, address and dateOfBirth can be edited. */
export interface StudentProfile extends Student {
  phone: string | null;
  address: string | null;
  /** YYYY-MM-DD */
  dateOfBirth: string | null;
}

export interface ProfileUpdate {
  phone: string | null;
  address: string | null;
  dateOfBirth: string | null;
}

export interface AvailableCourse extends Course {
  registered: boolean;
}

export interface AvailableCourses {
  data: AvailableCourse[];
  meta: PageMeta;
  sessionName: string;
  semester: SemesterName | "";
}

/** One approved result of the signed-in student. */
export interface StudentResultRow {
  id: string;
  courseId: string;
  courseCode: string;
  courseTitle: string;
  units: number;
  sessionName: string;
  semester: SemesterName;
  caScore: number | null;
  examScore: number | null;
  totalScore: number | null;
  grade: string | null;
  gradePoint: number | null;
}

export interface HistorySemester {
  sessionName: string;
  semester: SemesterName;
  totalUnits: number;
  gpa: number | null;
  courses: StudentResultRow[];
}

export interface AcademicHistory {
  matricNo: string;
  studentName: string;
  departmentName: string;
  programName: string;
  level: number;
  cgpa: number | null;
  totalUnits: number;
  totalCourses: number;
  semesters: HistorySemester[];
}

// ---------- Department admin (matches /api/dept/*) ----------

export interface DeptDashboard {
  departmentName: string;
  totalStudents: number;
  totalLecturers: number;
  totalCourses: number;
  activePrograms: number;
  pendingSubmissions: number;
  submissionsByStatus: Record<ResultStatus, number>;
  currentSession: string;
  currentSemester: SemesterName;
}

/** One student's scores inside a submission the department admin is reviewing. */
export interface SubmissionScoreRow {
  id: string;
  studentId: string;
  matricNo: string;
  studentName: string;
  caScore: number | null;
  examScore: number | null;
  totalScore: number | null;
  grade: string | null;
  gradePoint: number | null;
}

export interface DeptStudentBody {
  name: string;
  email: string;
  matricNo?: string; // create only
  password?: string; // create only
  programId: number;
  level: number;
  phone?: string | null;
}

export interface DeptLecturerBody {
  name: string;
  email: string;
  password?: string; // create only
  specialization?: string | null;
}

export interface DeptCourseBody {
  code: string;
  title: string;
  units: number;
  level: number;
  semester: SemesterName;
  type: CourseType;
  programId: number;
  lecturerId?: number | null;
}
