import { useState } from "react";
import { Printer } from "lucide-react";
import { api } from "../../lib/api";
import { useAsyncData } from "../../hooks/useAsyncData";
import type { RegistrationRecord } from "../../types/portal";
import { AdminPage, ErrorState, LoadingBlock, PageHeader, buttonClass, formatDate } from "../../components/admin/ui";
import { PrintFacts, PrintSheet, printTable, usePrintTrigger } from "./print";

export default function MyCourses() {
  const { data, error, reload } = useAsyncData(() => api.student.registrations(), []);
  const [printId, setPrintId] = useState<string | null>(null);
  usePrintTrigger(printId, () => setPrintId(null));

  if (error) return <AdminPage><ErrorState error={error} onRetry={reload} /></AdminPage>;
  if (!data) return <AdminPage><LoadingBlock /></AdminPage>;

  const rows = data.data;
  const toPrint: RegistrationRecord | undefined = rows.find((r) => r.id === printId);

  return <AdminPage>
    <div className="no-print">
      <PageHeader eyebrow="Registration" title="My courses" description="Courses you have registered, by semester, with their units." />
      {rows.length === 0 && <div className="rounded-xl border border-[#e9ebee] bg-white p-6 text-sm text-[#8a909a]">You have not registered any course yet.</div>}
      <div className="space-y-5">
        {rows.map((r) => <section key={r.id} className="overflow-hidden rounded-xl border border-[#e9ebee] bg-white">
          <div className="flex flex-wrap items-center justify-between gap-3 p-5">
            <div><h2 className="text-base font-bold tracking-tight">{r.sessionName} · {r.semester} semester</h2><p className="mt-1 text-xs text-[#9aa1ab]">{r.courses.length} courses · {r.totalUnits} units · registered {formatDate(r.registeredAt)}</p></div>
            <button className={buttonClass} onClick={() => setPrintId(r.id)}><Printer size={15} /> Print summary</button>
          </div>
          <div className="overflow-x-auto"><table className="w-full min-w-[480px] text-left text-xs">
            <thead className="bg-[#f8f9fa] text-[10px] font-semibold uppercase tracking-[.09em] text-[#9ca2ab]"><tr><th className="px-5 py-3.5">Code</th><th className="px-5 py-3.5">Title</th><th className="px-5 py-3.5">Units</th></tr></thead>
            <tbody>{r.courses.map((c) => <tr key={c.courseId} className="border-t border-[#f1f2f4]"><td className="px-5 py-3.5 font-semibold text-[#343c48]">{c.code}</td><td className="px-5 py-3.5 text-[#737b87]">{c.title}</td><td className="px-5 py-3.5 text-[#737b87]">{c.units}</td></tr>)}</tbody>
          </table></div>
        </section>)}
      </div>
    </div>
    {toPrint && <PrintSheet title="Course registration summary" subtitle={`${toPrint.sessionName} session · ${toPrint.semester} semester`}>
      <PrintFacts rows={[["Name", toPrint.studentName], ["Matric no", toPrint.matricNo], ["Department", toPrint.departmentName], ["Level", toPrint.level], ["Registered", formatDate(toPrint.registeredAt)]]} />
      <table className={printTable}>
        <thead><tr><th>#</th><th>Code</th><th>Title</th><th>Units</th></tr></thead>
        <tbody>{toPrint.courses.map((c, i) => <tr key={c.courseId}><td>{i + 1}</td><td>{c.code}</td><td>{c.title}</td><td>{c.units}</td></tr>)}
          <tr><td colSpan={3} className="font-bold">Total units</td><td className="font-bold">{toPrint.totalUnits}</td></tr></tbody>
      </table>
    </PrintSheet>}
  </AdminPage>;
}
