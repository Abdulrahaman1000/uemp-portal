import { useState } from "react";
import { Download } from "lucide-react";
import { api, type ReportType } from "../../lib/api";
import { downloadCsv } from "../../lib/csv";
import { useAsyncData } from "../../hooks/useAsyncData";
import { AdminPage, ErrorState, LoadingBlock, PageHeader, buttonClass } from "../../components/admin/ui";

const TABS: { type: ReportType; label: string; description: string }[] = [
  { type: "students", label: "Students", description: "Students of your department by level." },
  { type: "registrations", label: "Registrations", description: "Registrations of the current session by level." },
  { type: "results", label: "Results", description: "Result submissions of your department by status." },
];

export default function Reports() {
  const [type, setType] = useState<ReportType>("students");
  const { data, loading, error, reload } = useAsyncData(() => api.dept.report(type), [type]);
  const tab = TABS.find((t) => t.type === type) ?? TABS[0];
  const max = Math.max(1, ...(data ?? []).map((r) => r.value));

  return <AdminPage>
    <PageHeader eyebrow="Department" title="Reports" description="Summaries for your department only."
      actions={<button className={buttonClass} disabled={!data?.length} onClick={() => data && downloadCsv(`department-${type}-report.csv`, [["Label", "Value"], ...data.map((r) => [r.label, r.value])])}><Download size={15} /> Export CSV</button>} />
    <div role="tablist" aria-label="Report type" className="mb-5 inline-flex rounded-lg border border-[#e5e7e9] bg-white p-1">
      {TABS.map((t) => <button key={t.type} role="tab" aria-selected={type === t.type} onClick={() => setType(t.type)} className={`rounded-md px-4 py-2 text-sm font-semibold transition ${type === t.type ? "bg-[#e9edf4] text-[#2454b8]" : "text-[#737a84] hover:text-[#273d5d]"}`}>{t.label}</button>)}
    </div>
    {error ? <ErrorState error={error} onRetry={reload} /> : loading && !data ? <LoadingBlock /> : <section className="rounded-xl border border-[#e9ebee] bg-white p-5 sm:p-6">
      <h2 className="text-base font-bold tracking-tight">{tab.label} report</h2>
      <p className="mt-1 text-xs text-[#9aa1ab]">{tab.description}</p>
      {data?.length === 0 ? <p className="py-10 text-center text-sm text-[#959ca6]">No data for this report yet.</p> : <ul className="mt-6 space-y-4">
        {data?.map((r) => <li key={r.label}>
          <div className="mb-1.5 flex justify-between text-xs"><span className="font-medium text-[#343c48]">{r.label}</span><span className="font-bold">{r.value.toLocaleString()}</span></div>
          <div className="h-2.5 overflow-hidden rounded-full bg-[#edf1f6]"><div className="h-full rounded-full bg-[#2454b8]" style={{ width: `${(r.value / max) * 100}%` }} /></div>
        </li>)}
      </ul>}
    </section>}
  </AdminPage>;
}
