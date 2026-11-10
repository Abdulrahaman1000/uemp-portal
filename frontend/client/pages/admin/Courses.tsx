import { CrudPage } from "../../components/admin/CrudPage";
import { AdminPage, ErrorState, LoadingBlock } from "../../components/admin/ui";
import { useAsyncData } from "../../hooks/useAsyncData";
import { api } from "../../lib/api";
import type { Course } from "../../types/portal";

const LEVELS = [100, 200, 300, 400, 500, 600].map((level) => ({ value: String(level), label: `${level} level` }));
const SEMESTERS = [{ value: "First", label: "First semester" }, { value: "Second", label: "Second semester" }];
const TYPES = [{ value: "core", label: "Core" }, { value: "elective", label: "Elective" }];

export default function Courses() {
  const options = useAsyncData(async () => {
    const [departments, programs, lecturers] = await Promise.all([
      api.admin.list("departments", { per_page: 100 }),
      api.admin.list("programs", { per_page: 100 }),
      api.admin.lecturers({ per_page: 100 }),
    ]);
    return {
      departments: departments.data.map((item) => ({ value: item.id, label: item.name })),
      programs: programs.data.map((item) => ({ value: item.id, label: item.name })),
      lecturers: lecturers.data.map((item) => ({ value: item.id, label: item.name })),
    };
  });
  if (options.error) return <AdminPage><ErrorState error={options.error} onRetry={options.reload} /></AdminPage>;
  if (!options.data) return <AdminPage><LoadingBlock /></AdminPage>;

  return <CrudPage<Course>
    resource="courses"
    eyebrow="University management"
    title="Courses"
    singular="course"
    description="Manage courses and allocate each one to a lecturer."
    fields={[
      { key: "code", label: "Course code", required: true },
      { key: "title", label: "Course title", required: true },
      { key: "units", label: "Units", type: "number", required: true },
      { key: "level", label: "Level", type: "select", numeric: true, required: true, options: LEVELS },
      { key: "semester", label: "Semester", type: "select", required: true, options: SEMESTERS },
      { key: "type", label: "Type", type: "select", required: true, options: TYPES },
      { key: "departmentId", label: "Department", type: "select", required: true, options: options.data.departments },
      { key: "programId", label: "Program", type: "select", required: true, options: options.data.programs },
      { key: "lecturerId", label: "Lecturer (allocation)", type: "select", options: options.data.lecturers },
    ]}
    columns={[
      { key: "course", header: "Course", render: (row) => <div><div className="font-semibold text-[#343c48]">{row.code}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{row.title}</div></div> },
      { key: "units", header: "Units", render: (row) => row.units },
      { key: "level", header: "Level", render: (row) => `${row.level} level` },
      { key: "semester", header: "Semester", render: (row) => row.semester },
      { key: "type", header: "Type", render: (row) => row.type === "core" ? "Core" : "Elective" },
      { key: "department", header: "Department", render: (row) => row.departmentName },
      { key: "lecturer", header: "Lecturer", render: (row) => row.lecturerName ?? <span className="text-[#c0842a]">Unassigned</span> },
    ]}
  />;
}
