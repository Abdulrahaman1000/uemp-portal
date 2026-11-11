import { api } from "../../lib/api";
import { useAsyncData } from "../../hooks/useAsyncData";
import type { Program } from "../../types/portal";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ErrorState, LoadingBlock, PageHeader } from "../../components/admin/ui";

export default function Programs() {
  const programs = useAsyncData(() => api.dept.programs({ per_page: 100 }), []);
  const levels = useAsyncData(() => api.dept.report("students"), []);
  const max = Math.max(1, ...(levels.data ?? []).map((r) => r.value));

  return <AdminPage>
    <PageHeader eyebrow="Academics" title="Levels & programs" description="Programs offered by your department and how many students are at each level. New programs are created by the university administrator." />
    <div className="grid gap-5 xl:grid-cols-[1.5fr_1fr]">
      {programs.error ? <ErrorState error={programs.error} onRetry={programs.reload} /> : <DataTable<Program>
        title="Programs" subtitle={programs.data ? `${programs.data.meta.total} programs` : undefined}
        rows={programs.data?.data ?? []} rowKey={(r) => r.id} loading={programs.loading && !programs.data}
        columns={[
          { key: "name", header: "Program", render: (r) => <span className="font-semibold text-[#343c48]">{r.name}</span> },
          { key: "award", header: "Award", render: (r) => r.award },
          { key: "years", header: "Duration", render: (r) => `${r.durationYears} years` },
          { key: "students", header: "Students", render: (r) => r.studentCount },
        ]} />}
      <section className="rounded-xl border border-[#e9ebee] bg-white p-5 sm:p-6">
        <h2 className="text-base font-bold tracking-tight">Students by level</h2>
        {levels.error ? <ErrorState error={levels.error} onRetry={levels.reload} /> : !levels.data ? <div className="mt-4"><LoadingBlock /></div> : levels.data.length === 0 ? <p className="py-8 text-center text-sm text-[#959ca6]">No students yet.</p> : <ul className="mt-5 space-y-4">
          {levels.data.map((row) => <li key={row.label}>
            <div className="mb-1.5 flex justify-between text-xs"><span className="font-medium text-[#343c48]">{row.label}</span><span className="font-bold">{row.value}</span></div>
            <div className="h-2.5 overflow-hidden rounded-full bg-[#edf1f6]"><div className="h-full rounded-full bg-[#2454b8]" style={{ width: `${(row.value / max) * 100}%` }} /></div>
          </li>)}
        </ul>}
      </section>
    </div>
  </AdminPage>;
}
