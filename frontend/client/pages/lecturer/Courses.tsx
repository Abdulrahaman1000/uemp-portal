import { useState } from "react";
import { Link } from "react-router-dom";
import { BarChart3, FileCheck2 } from "lucide-react";
import { api } from "../../lib/api";
import { useAsyncData } from "../../hooks/useAsyncData";
import type { LecturerCourse } from "../../types/portal";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ErrorState, LoadingBlock, Modal, PageHeader, StatusBadge, buttonClass, primaryClass } from "../../components/admin/ui";

function SummaryModal({ course, onClose }: { course: LecturerCourse; onClose: () => void }) {
  const { data, loading, error, reload } = useAsyncData(() => api.lecturer.summary(course.id), [course.id]);
  const max = data ? Math.max(1, ...Object.values(data.gradeDistribution)) : 1;
  const show = (value: number | null, suffix = "") => (value === null ? "—" : `${value}${suffix}`);

  return <Modal title={`${course.code} · course summary`} description={`${course.title} · ${course.units} units`} onClose={onClose}>
    {loading && !data ? <div className="mt-5"><LoadingBlock /></div> : error ? <div className="mt-5"><ErrorState error={error} onRetry={reload} /></div> : data && <>
      <div className="mt-4 flex items-center gap-3 text-xs text-[#8a909a]"><StatusBadge value={data.status} /><span>{data.scoredStudents} of {data.totalStudents} students scored</span></div>
      <dl className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        {[["Average", show(data.average)], ["Highest", show(data.highest)], ["Lowest", show(data.lowest)], ["Pass rate", show(data.passRate, "%")]].map(([label, value]) => <div key={label} className="rounded-lg bg-[#f7f8fa] p-3">
          <dt className="text-[11px] text-[#9aa0aa]">{label}</dt><dd className="mt-1 text-lg font-bold">{value}</dd>
        </div>)}
      </dl>
      <h3 className="mt-6 text-xs font-bold uppercase tracking-[.1em] text-[#9199a4]">Grade distribution</h3>
      <div className="mt-3 space-y-2">
        {Object.entries(data.gradeDistribution).map(([grade, count]) => <div key={grade} className="flex items-center gap-3 text-xs">
          <span className="w-5 font-bold text-[#343c48]">{grade}</span>
          <div className="h-3 flex-1 overflow-hidden rounded-full bg-[#eef1f6]"><div className="h-full rounded-full bg-[#2454b8]" style={{ width: `${(count / max) * 100}%` }} /></div>
          <span className="w-6 text-right text-[#737b87]">{count}</span>
        </div>)}
      </div>
      {data.scoredStudents === 0 && <p className="mt-4 text-xs text-[#8a909a]">No scores have been entered yet, so the statistics are empty.</p>}
    </>}
    <div className="mt-6 flex justify-end"><button className={buttonClass} onClick={onClose}>Close</button></div>
  </Modal>;
}

export default function Courses() {
  const { data, loading, error, reload } = useAsyncData(() => api.lecturer.courses(), []);
  const [summary, setSummary] = useState<LecturerCourse | null>(null);

  return <AdminPage>
    <PageHeader eyebrow="Teaching" title="My courses" description="Courses assigned to you in the current session. You can only enter results for these." />
    {error ? <ErrorState error={error} onRetry={reload} /> : <DataTable
      title="Assigned courses"
      subtitle={data ? `${data.meta.total} ${data.meta.total === 1 ? "course" : "courses"}` : undefined}
      rows={data?.data ?? []}
      rowKey={(row) => row.id}
      loading={loading && !data}
      emptyMessage="No courses are assigned to you in the current session."
      columns={[
        { key: "course", header: "Course", render: (row) => <div><div className="font-semibold text-[#343c48]">{row.code}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{row.title}</div></div> },
        { key: "units", header: "Units", render: (row) => row.units },
        { key: "level", header: "Level", render: (row) => row.level },
        { key: "semester", header: "Semester", render: (row) => row.semester },
        { key: "students", header: "Students", render: (row) => row.studentCount },
        { key: "status", header: "Results", render: (row) => <StatusBadge value={row.submissionStatus} /> },
        { key: "actions", header: "Actions", render: (row) => <div className="flex flex-wrap gap-2">
          <Link to={`/lecturer/results?course=${row.id}`} className={primaryClass}><FileCheck2 size={15} /> Results</Link>
          <button className={buttonClass} onClick={() => setSummary(row)}><BarChart3 size={15} /> Summary</button>
        </div> },
      ]}
    />}
    {summary && <SummaryModal course={summary} onClose={() => setSummary(null)} />}
  </AdminPage>;
}
