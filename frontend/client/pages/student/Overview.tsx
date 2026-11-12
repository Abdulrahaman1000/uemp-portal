import { Link } from "react-router-dom";
import { ArrowRight, Award, BookOpen, ClipboardCheck, FileCheck2 } from "lucide-react";
import { api } from "../../lib/api";
import { useAsyncData } from "../../hooks/useAsyncData";
import { AdminPage, ErrorState, LoadingBlock, PageHeader, StatCard, StatusBadge } from "../../components/admin/ui";

export default function Overview() {
  const profile = useAsyncData(() => api.student.profile(), []);
  const registrations = useAsyncData(() => api.student.registrations(), []);
  const history = useAsyncData(() => api.student.history(), []);

  const error = profile.error || registrations.error || history.error;
  if (error) return <AdminPage><ErrorState error={error} onRetry={() => { profile.reload(); registrations.reload(); history.reload(); }} /></AdminPage>;
  if (!profile.data || !registrations.data || !history.data) return <AdminPage><LoadingBlock /></AdminPage>;

  const p = profile.data;
  const h = history.data;
  const latest = registrations.data.data[0];

  return <AdminPage>
    <PageHeader eyebrow={`${p.departmentName} · ${p.level} level`} title={`Welcome, ${p.name}`} description="Your registration, results and academic record at a glance."
      actions={<Link to="/student/registration" className="inline-flex items-center gap-2 rounded-lg bg-[#2454b8] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1d4294]"><ClipboardCheck size={16} /> Register courses</Link>} />
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <StatCard label="CGPA" value={h.cgpa === null ? "—" : h.cgpa.toFixed(2)} icon={Award} note="From approved results only" />
      <StatCard label="Current level" value={p.level} icon={BookOpen} note={p.programName} />
      <StatCard label="Registered units" value={latest?.totalUnits ?? 0} icon={ClipboardCheck} note={latest ? `${latest.sessionName} · ${latest.semester} semester` : "No registration yet"} />
      <StatCard label="Approved results" value={h.totalCourses} icon={FileCheck2} note={`${h.totalUnits} units earned`} />
    </div>
    <div className="mt-6 grid grid-cols-[minmax(0,1fr)] gap-5 xl:grid-cols-[1fr_1.2fr]">
      <section className="rounded-xl border border-[#e9ebee] bg-white p-5 sm:p-6">
        <h2 className="text-base font-bold tracking-tight">My details</h2>
        <dl className="mt-4 space-y-3 text-sm">
          {([["Matric number", p.matricNo], ["Department", p.departmentName], ["Faculty", p.facultyName], ["Program", p.programName], ["Email", p.email]] as const).map(([label, value]) => <div key={label} className="flex justify-between gap-4"><dt className="text-[#8a909a]">{label}</dt><dd className="text-right font-semibold text-[#343c48]">{value}</dd></div>)}
          <div className="flex justify-between gap-4"><dt className="text-[#8a909a]">Status</dt><dd><StatusBadge value={p.status} /></dd></div>
        </dl>
        <Link to="/student/profile" className="mt-5 inline-flex items-center gap-1 text-xs font-semibold text-[#274b82]">Update contact details <ArrowRight size={14} /></Link>
      </section>
      <section className="rounded-xl border border-[#e9ebee] bg-white p-5 sm:p-6">
        <h2 className="text-base font-bold tracking-tight">Current registration</h2>
        {latest ? <>
          <p className="mt-1 text-xs text-[#999fa7]">{latest.sessionName} · {latest.semester} semester · {latest.totalUnits} units</p>
          <ul className="mt-4 divide-y divide-[#f1f2f4] text-sm">
            {latest.courses.map((c) => <li key={c.courseId} className="flex items-center justify-between py-2.5"><span><span className="font-semibold text-[#343c48]">{c.code}</span> <span className="text-[#8a909a]">{c.title}</span></span><span className="text-xs text-[#8a909a]">{c.units} units</span></li>)}
          </ul>
        </> : <p className="mt-4 rounded-lg bg-[#f7f9fc] p-4 text-sm text-[#8a909a]">You have not registered any course yet.</p>}
        <Link to="/student/courses" className="mt-5 inline-flex items-center gap-1 text-xs font-semibold text-[#274b82]">View my courses <ArrowRight size={14} /></Link>
      </section>
    </div>
  </AdminPage>;
}
