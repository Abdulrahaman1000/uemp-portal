import {
  Activity, BookOpen, Building2, CalendarDays, ClipboardCheck, FileBarChart2, FileCheck2,
  GraduationCap, LayoutDashboard, Library, ShieldCheck, UserRound, Users, type LucideIcon,
} from "lucide-react";
import type { UserRole } from "../types/portal";

export interface MenuItem { id: string; label: string; icon: LucideIcon }

export const menus: Record<UserRole, MenuItem[]> = {
  student: [
    { id: "overview", label: "Dashboard", icon: LayoutDashboard },
    { id: "profile", label: "My profile", icon: UserRound },
    { id: "registration", label: "Course registration", icon: ClipboardCheck },
    { id: "courses", label: "My courses", icon: BookOpen },
    { id: "results", label: "Results", icon: FileCheck2 },
    { id: "history", label: "Academic history", icon: Activity },
  ],
  lecturer: [
    { id: "overview", label: "Dashboard", icon: LayoutDashboard },
    { id: "courses", label: "My courses", icon: BookOpen },
    { id: "results", label: "Result entry", icon: FileCheck2 },
    { id: "submissions", label: "Submissions", icon: ClipboardCheck },
  ],
  dept_admin: [
    { id: "overview", label: "Dashboard", icon: LayoutDashboard },
    { id: "students", label: "Students", icon: GraduationCap },
    { id: "lecturers", label: "Lecturers", icon: Users },
    { id: "courses", label: "Courses", icon: BookOpen },
    { id: "assignments", label: "Course assignments", icon: ClipboardCheck },
    { id: "programs", label: "Levels & programs", icon: Library },
    { id: "registrations", label: "Registrations", icon: FileCheck2 },
    { id: "approvals", label: "Result approvals", icon: ShieldCheck },
    { id: "reports", label: "Reports", icon: FileBarChart2 },
  ],
  super_admin: [
    { id: "overview", label: "Dashboard", icon: LayoutDashboard },
    { id: "faculties", label: "Faculties", icon: Building2 },
    { id: "departments", label: "Departments", icon: Library },
    { id: "programs", label: "Programs", icon: BookOpen },
    { id: "sessions", label: "Sessions & semesters", icon: CalendarDays },
    { id: "users", label: "Users", icon: Users },
    { id: "roles", label: "Roles & permissions", icon: ShieldCheck },
    { id: "courses", label: "Courses & allocations", icon: BookOpen },
    { id: "registrations", label: "Registrations", icon: ClipboardCheck },
    { id: "oversight", label: "Result oversight", icon: FileCheck2 },
    { id: "reports", label: "Reports", icon: FileBarChart2 },
    { id: "activity-log", label: "Activity log", icon: Activity },
  ],
};

export const rolePath: Record<UserRole, string> = { student: "/student", lecturer: "/lecturer", dept_admin: "/dept", super_admin: "/admin" };
export const roleName: Record<UserRole, string> = { student: "Student", lecturer: "Lecturer", dept_admin: "Department admin", super_admin: "Super admin" };
