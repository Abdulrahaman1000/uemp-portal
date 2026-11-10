import { CrudPage } from "../../components/admin/CrudPage";
import type { Faculty } from "../../types/portal";

export default function Faculties() {
  return <CrudPage<Faculty>
    resource="faculties"
    eyebrow="University management"
    title="Faculties"
    singular="faculty"
    description="Manage the university's faculties and their deans."
    fields={[
      { key: "name", label: "Faculty name", required: true },
      { key: "code", label: "Code", required: true },
      { key: "dean", label: "Dean", required: true },
    ]}
    columns={[
      { key: "name", header: "Faculty", render: (row) => <span className="font-semibold text-[#343c48]">{row.name}</span> },
      { key: "code", header: "Code", render: (row) => row.code },
      { key: "dean", header: "Dean", render: (row) => row.dean },
      { key: "departments", header: "Departments", render: (row) => row.departmentCount },
    ]}
  />;
}
