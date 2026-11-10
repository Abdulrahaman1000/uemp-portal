import { useState } from "react";
import { Download } from "lucide-react";
import { api, type ReportType } from "../../lib/api";
import { downloadCsv } from "../../lib/csv";
import { useAsyncData } from "../../hooks/useAsyncData";
import { AdminPage, ErrorState, LoadingBlock, PageHeader, buttonClass } from "../../components/admin/ui";

const TABS: { type: ReportType; label: string; description: string }[] = [
  { type: "students", label: "Students", description: "Student numbers across the university." },
  { type: "registrations", label: "Registrations", description: "Course registrations for the current session." },
  { type: "results", label: "Results", description: "Result submissions by status." },
];

export default function Reports() {
  const [type, setType] = useState<ReportType>("students");
  const { data, loading, error, reload } = useAsyncData(() => api.admin.report(type), [type]);
  const tab = TABS.find((item) => item.type === type) ?? TABS[0];
  const max = Math.max(1, ...(data ?? []).map((row) => row.value));

  return <AdminPage>
    <PageHeader
      eyebrow="University management"
      title="Reports"
      description="University-wide summaries. Department reports are available to department admins."
      actions={<button className={buttonClass} disabled={!data?.length} onClick={() => data && downloadCsv(`${type}-report.csv`, [["Label", "Value"], ...data.map((row) => [row.label, row.value])])}><Download size={15} /> Export CSV</button>}
    />
    <div role="tablist" aria-label="Report type" className="mb-5 inline-flex rounded-lg border border-[#e5e7e9] bg-white p-1">
      {TABS.map((item) => <button key={item.type} role="tab" aria-selected={type === item.type} onClick={() => setType(item.type)} className={`rounded-md px-4 py-2 text-sm font-semibold transition ${type === item.type ? "bg-[#e9edf4] text-[#2454b8]" : "text-[#737a84] hover:text-[#273d5d]"}`}>{item.label}</button>)}
    </div>
    {error ? <ErrorState error={error} onRetry={reload} /> : loading && !data ? <LoadingBlock /> : <section className="rounded-xl border border-[#e9ebee] bg-white p-5 sm:p-6">
      <h2 className="text-base font-bold tracking-tight">{tab.label} report</h2>
      <p className="mt-1 text-xs text-[#9aa1ab]">{tab.description}</p>
      {data?.length === 0 ? <p className="py-10 text-center text-sm text-[#959ca6]">No data for this report yet.</p> : <ul className="mt-6 space-y-4">
        {data?.map((row) => <li key={row.label}>
          <div className="mb-1.5 flex justify-between text-xs"><span className="font-medium text-[#343c48]">{row.label}</span><span className="font-bold">{row.value.toLocaleString()}</span></div>
          <div className="h-2.5 overflow-hidden rounded-full bg-[#edf1f6]"><div className="h-full rounded-full bg-[#2454b8]" style={{ width: `${(row.value / max) * 100}%` }} /></div>
        </li>)}
      </ul>}
    </section>}
  </AdminPage>;
}
