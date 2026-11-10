import { CrudPage } from "../../components/admin/CrudPage";
import { ErrorState, LoadingBlock, AdminPage } from "../../components/admin/ui";
import { useAsyncData } from "../../hooks/useAsyncData";
import { api } from "../../lib/api";
import type { Department } from "../../types/portal";

export default function Departments() {
  const faculties = useAsyncData(async () => (await api.admin.list("faculties", { per_page: 100 })).data.map((faculty) => ({ value: faculty.id, label: faculty.name })));
  if (faculties.error) return <AdminPage><ErrorState error={faculties.error} onRetry={faculties.reload} /></AdminPage>;
  if (!faculties.data) return <AdminPage><LoadingBlock /></AdminPage>;

  return <CrudPage<Department>
    resource="departments"
    eyebrow="University management"
    title="Departments"
    singular="department"
    description="Manage departments and the faculty each one belongs to."
    fields={[
      { key: "name", label: "Department name", required: true },
      { key: "code", label: "Code", required: true },
      { key: "facultyId", label: "Faculty", type: "select", required: true, options: faculties.data },
      { key: "head", label: "Head of department", required: true },
    ]}
    columns={[
      { key: "name", header: "Department", render: (row) => <div><div className="font-semibold text-[#343c48]">{row.name}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{row.code}</div></div> },
      { key: "faculty", header: "Faculty", render: (row) => row.facultyName },
      { key: "head", header: "Head of department", render: (row) => row.head },
      { key: "programs", header: "Programs", render: (row) => row.programCount },
    ]}
  />;
}
