import { Link } from "react-router-dom";
import { ArrowRight, BookOpen, GraduationCap, Library, ShieldCheck, Users } from "lucide-react";
import { api } from "../../lib/api";
import { useAsyncData } from "../../hooks/useAsyncData";
import type { ResultStatus } from "../../types/portal";
import { AdminPage, ErrorState, LoadingBlock, PageHeader, StatCard, StatusBadge } from "../../components/admin/ui";

const STATUSES: ResultStatus[] = ["submitted", "approved", "rejected", "reopened", "draft"];

export default function Overview() {
  const { data, error, reload } = useAsyncData(() => api.dept.dashboard(), []);
  if (error) return <AdminPage><ErrorState error={error} onRetry={reload} /></AdminPage>;
  if (!data) return <AdminPage><LoadingBlock /></AdminPage>;

  return <AdminPage>
    <PageHeader eyebrow={`${data.currentSession} · ${data.currentSemester} semester`} title={`${data.departmentName} department`}
      description="People, courses and result submissions of your department."
      actions={<Link to="/dept/approvals" className="inline-flex items-center gap-2 rounded-lg bg-[#2454b8] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1d4294]"><ShieldCheck size={16} /> Review results ({data.pendingSubmissions})</Link>} />
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <StatCard label="Students" value={data.totalStudents} icon={GraduationCap} />
      <StatCard label="Lecturers" value={data.totalLecturers} icon={Users} />
      <StatCard label="Courses" value={data.totalCourses} icon={BookOpen} />
      <StatCard label="Programs" value={data.activePrograms} icon={Library} />
    </div>
    <section className="mt-6 max-w-xl rounded-xl border border-[#e9ebee] bg-white p-5 sm:p-6">
      <h2 className="text-base font-bold tracking-tight">Result submissions</h2>
      <p className="mt-1 text-xs text-[#999fa7]">Current session, by status.</p>
      <ul className="mt-5 space-y-3">
        {STATUSES.map((status) => <li key={status} className="flex items-center justify-between text-sm">
          <StatusBadge value={status} /><span className="text-lg font-bold tracking-tight">{data.submissionsByStatus[status]}</span>
        </li>)}
      </ul>
      <Link to="/dept/approvals" className="mt-5 inline-flex items-center gap-1 text-xs font-semibold text-[#274b82]">Open result approvals <ArrowRight size={14} /></Link>
    </section>
  </AdminPage>;
}
