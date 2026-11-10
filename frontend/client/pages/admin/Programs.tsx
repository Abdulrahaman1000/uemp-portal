import { CrudPage } from "../../components/admin/CrudPage";
import { ErrorState, LoadingBlock, AdminPage } from "../../components/admin/ui";
import { useAsyncData } from "../../hooks/useAsyncData";
import { api } from "../../lib/api";
import type { Program } from "../../types/portal";

export default function Programs() {
  const departments = useAsyncData(async () => (await api.admin.list("departments", { per_page: 100 })).data.map((department) => ({ value: department.id, label: department.name })));
  if (departments.error) return <AdminPage><ErrorState error={departments.error} onRetry={departments.reload} /></AdminPage>;
  if (!departments.data) return <AdminPage><LoadingBlock /></AdminPage>;

  return <CrudPage<Program>
    resource="programs"
    eyebrow="University management"
    title="Programs"
    singular="program"
    description="Manage academic programs across all departments."
    fields={[
      { key: "name", label: "Program name", required: true },
      { key: "award", label: "Award (e.g. B.Sc.)", required: true },
      { key: "departmentId", label: "Department", type: "select", required: true, options: departments.data },
      { key: "durationYears", label: "Duration (years)", type: "number", required: true },
    ]}
    columns={[
      { key: "name", header: "Program", render: (row) => <span className="font-semibold text-[#343c48]">{row.name}</span> },
      { key: "award", header: "Award", render: (row) => row.award },
      { key: "department", header: "Department", render: (row) => row.departmentName },
      { key: "duration", header: "Duration", render: (row) => `${row.durationYears} ${row.durationYears === 1 ? "year" : "years"}` },
      { key: "students", header: "Students", render: (row) => row.studentCount },
    ]}
  />;
}
