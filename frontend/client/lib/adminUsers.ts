import { apiRequest } from "./api";
import type { User, UserRole } from "../types/portal";

/** Body for creating a staff account. Student accounts are created by the department admin. */
export interface NewUserBody {
  name: string;
  email: string;
  password: string;
  role: Exclude<UserRole, "student">;
  /** Required for lecturers and department admins. */
  departmentId?: number | null;
  specialization?: string | null;
}

/** Super admin calls that were added after the first release of lib/api.ts. */
export const adminUsers = {
  create: (body: NewUserBody) => apiRequest<User>("/admin/users", { method: "POST", body }),
  setDepartment: (id: string, departmentId: number) =>
    apiRequest<User>(`/admin/users/${id}/department`, { method: "PATCH", body: { departmentId } }),
};
