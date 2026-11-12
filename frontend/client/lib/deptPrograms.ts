import { apiRequest } from "./api";
import type { Program } from "../types/portal";

/** The department is never sent: the server always uses the department admin's own. */
export interface DeptProgramBody {
  name: string;
  award: string;
  durationYears: number;
}

export const deptPrograms = {
  create: (body: DeptProgramBody) => apiRequest<Program>("/dept/programs", { method: "POST", body }),
  update: (id: string, body: DeptProgramBody) => apiRequest<Program>(`/dept/programs/${id}`, { method: "PUT", body }),
  remove: (id: string) => apiRequest<void>(`/dept/programs/${id}`, { method: "DELETE" }),
};
