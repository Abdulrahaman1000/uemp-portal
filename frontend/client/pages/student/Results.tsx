import { Link } from "react-router-dom";
import { api } from "../../lib/api";
import { useAsyncData } from "../../hooks/useAsyncData";
import type { StudentResultRow } from "../../types/portal";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ErrorState, PageHeader } from "../../components/admin/ui";

const show = (v: number | null) => (v === null ? "—" : v);

export default function Results() {
  const { data, loading, error, reload } = useAsyncData(() => api.student.results(), []);
  return <AdminPage>
    <PageHeader eyebrow="Academic record" title="My results" description="Only results approved by your department are shown here."
      actions={<Link to="/student/history" className="inline-flex items-center rounded-lg border border-[#e5e7e9] bg-white px-3.5 py-2.5 text-sm font-semibold text-[#434953] hover:bg-[#f7f8fa]">Academic history and CGPA</Link>} />
    {error ? <ErrorState error={error} onRetry={reload} /> : <DataTable<StudentResultRow>
      title="Approved results" subtitle={data ? `${data.meta.total} ${data.meta.total === 1 ? "result" : "results"}` : undefined}
      rows={data?.data ?? []} rowKey={(r) => r.id} loading={loading && !data}
      emptyMessage="No results have been approved yet."
      columns={[
        { key: "course", header: "Course", render: (r) => <div><div className="font-semibold text-[#343c48]">{r.courseCode}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{r.courseTitle}</div></div> },
        { key: "term", header: "Session", render: (r) => `${r.sessionName} · ${r.semester}` },
        { key: "units", header: "Units", render: (r) => r.units },
        { key: "ca", header: "CA", render: (r) => show(r.caScore) },
        { key: "exam", header: "Exam", render: (r) => show(r.examScore) },
        { key: "total", header: "Total", render: (r) => <span className="font-semibold text-[#343c48]">{show(r.totalScore)}</span> },
        { key: "grade", header: "Grade", render: (r) => <span className="font-bold text-[#254b84]">{r.grade ?? "—"}</span> },
        { key: "point", header: "Point", render: (r) => show(r.gradePoint) },
      ]} />}
  </AdminPage>;
}
