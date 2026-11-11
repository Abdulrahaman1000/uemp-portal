import { useState } from "react";
import { Printer } from "lucide-react";
import { api } from "../../lib/api";
import { useAsyncData } from "../../hooks/useAsyncData";
import { AdminPage, ErrorState, LoadingBlock, PageHeader, StatCard, buttonClass } from "../../components/admin/ui";
import { Award, BookOpen, ClipboardCheck } from "lucide-react";
import { PrintFacts, PrintSheet, printTable, usePrintTrigger } from "./print";

const gpa = (v: number | null) => (v === null ? "—" : v.toFixed(2));
const show = (v: number | null) => (v === null ? "—" : v);

export default function History() {
  const { data, error, reload } = useAsyncData(() => api.student.history(), []);
  const [printing, setPrinting] = useState<string | null>(null);
  usePrintTrigger(printing, () => setPrinting(null));

  if (error) return <AdminPage><ErrorState error={error} onRetry={reload} /></AdminPage>;
  if (!data) return <AdminPage><LoadingBlock /></AdminPage>;

  return <AdminPage>
    <div className="no-print">
      <PageHeader eyebrow="Academic record" title="Academic history" description="Approved results by semester, with your semester GPA and cumulative GPA. GPA = sum(grade point × units) ÷ sum(units)."
        actions={<button className={buttonClass} onClick={() => setPrinting("statement")} disabled={data.totalCourses === 0}><Printer size={15} /> Print result statement</button>} />
      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard label="CGPA" value={gpa(data.cgpa)} icon={Award} note="Out of 5.00" />
        <StatCard label="Courses passed or taken" value={data.totalCourses} icon={BookOpen} note="Approved results" />
        <StatCard label="Total units" value={data.totalUnits} icon={ClipboardCheck} />
      </div>
      <div className="mt-6 space-y-5">
        {data.semesters.length === 0 && <div className="rounded-xl border border-[#e9ebee] bg-white p-6 text-sm text-[#8a909a]">No approved results yet. Results appear here once your department approves them.</div>}
        {data.semesters.map((s) => <section key={`${s.sessionName}-${s.semester}`} className="overflow-hidden rounded-xl border border-[#e9ebee] bg-white">
          <div className="flex flex-wrap items-center justify-between gap-3 p-5">
            <h2 className="text-base font-bold tracking-tight">{s.sessionName} · {s.semester} semester</h2>
            <p className="text-xs text-[#9aa1ab]">{s.totalUnits} units · GPA <strong className="text-[#343c48]">{gpa(s.gpa)}</strong></p>
          </div>
          <div className="overflow-x-auto"><table className="w-full min-w-[560px] text-left text-xs">
            <thead className="bg-[#f8f9fa] text-[10px] font-semibold uppercase tracking-[.09em] text-[#9ca2ab]"><tr>{["Course", "Units", "CA", "Exam", "Total", "Grade", "Point"].map((h) => <th key={h} className="px-5 py-3.5">{h}</th>)}</tr></thead>
            <tbody>{s.courses.map((r) => <tr key={r.id} className="border-t border-[#f1f2f4] text-[#737b87]">
              <td className="px-5 py-3.5"><span className="font-semibold text-[#343c48]">{r.courseCode}</span> <span className="text-[#a0a6ae]">{r.courseTitle}</span></td>
              <td className="px-5 py-3.5">{r.units}</td><td className="px-5 py-3.5">{show(r.caScore)}</td><td className="px-5 py-3.5">{show(r.examScore)}</td>
              <td className="px-5 py-3.5 font-semibold text-[#343c48]">{show(r.totalScore)}</td><td className="px-5 py-3.5 font-bold text-[#254b84]">{r.grade ?? "—"}</td><td className="px-5 py-3.5">{show(r.gradePoint)}</td>
            </tr>)}</tbody>
          </table></div>
        </section>)}
      </div>
    </div>
    {printing && <PrintSheet title="Statement of results" subtitle="Approved results only">
      <PrintFacts rows={[["Name", data.studentName], ["Matric no", data.matricNo], ["Department", data.departmentName], ["Program", data.programName], ["Level", data.level]]} />
      {data.semesters.map((s) => <div key={`${s.sessionName}-${s.semester}`} className="mt-5 [break-inside:avoid]">
        <div className="text-sm font-bold">{s.sessionName} · {s.semester} semester</div>
        <table className={printTable}>
          <thead><tr><th>Code</th><th>Title</th><th>Units</th><th>CA</th><th>Exam</th><th>Total</th><th>Grade</th><th>Point</th></tr></thead>
          <tbody>{s.courses.map((r) => <tr key={r.id}><td>{r.courseCode}</td><td>{r.courseTitle}</td><td>{r.units}</td><td>{show(r.caScore)}</td><td>{show(r.examScore)}</td><td>{show(r.totalScore)}</td><td>{r.grade ?? "—"}</td><td>{show(r.gradePoint)}</td></tr>)}
            <tr><td colSpan={2} className="font-bold">Semester total</td><td className="font-bold">{s.totalUnits}</td><td colSpan={5} className="font-bold">GPA {gpa(s.gpa)}</td></tr></tbody>
        </table>
      </div>)}
      <p className="mt-6 text-base font-bold">Cumulative GPA: {gpa(data.cgpa)} / 5.00 · Total units: {data.totalUnits}</p>
    </PrintSheet>}
  </AdminPage>;
}
