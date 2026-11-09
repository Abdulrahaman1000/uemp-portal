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
}

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface AuthSession {
  token: string;
  user: User;
}

export interface Student {
  id: string;
  studentNumber: string;
  name: string;
  email: string;
  program: string;
  year: number;
  status: "active" | "on-leave" | "graduated";
  advisor: string;
}

export interface Faculty {
  id: string;
  name: string;
  email: string;
  department: string;
  title: string;
}

export type Lecturer = Faculty;

export interface Course {
  id: string;
  code: string;
  title: string;
  department: string;
  credits: number;
  instructorId: string;
  instructorName: string;
  term: string;
  schedule: string;
  location: string;
  capacity: number;
  enrolled: number;
}

export interface Enrollment {
  id: string;
  studentId: string;
  courseId: string;
  status: "enrolled" | "completed" | "withdrawn";
  grade?: string;
}

export interface Announcement {
  id: string;
  title: string;
  body: string;
  author: string;
  publishedAt: string;
  audience: "all" | UserRole;
}

export interface PortalEvent {
  id: string;
  title: string;
  startsAt: string;
  endsAt: string;
  location: string;
  category: "academic" | "campus" | "deadline";
}

export interface DashboardSummary {
  totalStudents: number;
  totalFaculty: number;
  activeCourses: number;
  totalEnrollments: number;
  announcements: Announcement[];
  upcomingEvents: PortalEvent[];
}
