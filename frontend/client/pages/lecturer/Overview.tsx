import { Link } from "react-router-dom";
import { AlertCircle, ArrowRight, BookOpen, CalendarDays, ClipboardCheck, FileCheck2, Users } from "lucide-react";
import { api } from "../../lib/api";
import { useAuth } from "../../lib/auth";
import { useAsyncData } from "../../hooks/useAsyncData";
import type { ResultStatus } from "../../types/portal";
import { AdminPage, ErrorState, LoadingBlock, PageHeader, StatCard, StatusBadge } from "../../components/admin/ui";

const STATUSES: ResultStatus[] = ["draft", "submitted", "approved", "rejected", "reopened"];

export default function Overview() {
  const { user } = useAuth();
  const dashboard = useAsyncData(() => api.lecturer.dashboard(), []);
  const courses = useAsyncData(() => api.lecturer.courses(), []);

  if (dashboard.error) return <AdminPage><ErrorState error={dashboard.error} onRetry={dashboard.reload} /></AdminPage>;
  if (!dashboard.data) return <AdminPage><LoadingBlock /></AdminPage>;

  const d = dashboard.data;
  const needAction = (courses.data?.data ?? []).filter((course) => ["rejected", "reopened"].includes(course.submissionStatus));

  return <AdminPage>
    <PageHeader
      eyebrow={`${d.departmentName || "Lecturer"} · ${d.currentSession} · ${d.currentSemester} semester`}
      title={`Welcome, ${user?.name ?? d.lecturerName}`}
      description="Your assigned courses and the progress of your result submissions."
      actions={<Link to="/lecturer/results" className="inline-flex items-center gap-2 rounded-lg bg-[#2454b8] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1d4294]"><FileCheck2 size={16} /> Enter results</Link>}
    />

    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <StatCard label="Assigned courses" value={d.assignedCourses} icon={BookOpen} note="This session" />
      <StatCard label="Students taught" value={d.totalStudents} icon={Users} note="Registered this semester" />
      <StatCard label="Not started" value={d.notStarted} icon={ClipboardCheck} note="Courses with no result sheet yet" />
      <StatCard label="Awaiting review" value={d.submissionsByStatus.submitted} icon={CalendarDays} note="Submitted to the department" />
    </div>

    <div className="mt-6 grid gap-5 xl:grid-cols-[1.4fr_1fr]">
      <section className="rounded-xl border border-[#e9ebee] bg-white p-5 sm:p-6">
        <h2 className="text-base font-bold tracking-tight">Needs your attention</h2>
        <p className="mt-1 text-xs text-[#999fa7]">Results sent back for correction or reopened by the department.</p>
        <div className="mt-5 space-y-3">
          {needAction.map((course) => <Link key={course.id} to={`/lecturer/results?course=${course.id}`} className="flex items-center gap-3 rounded-lg border border-[#edeff2] p-3 hover:bg-[#f9fafc]">
            <span className="rounded-lg bg-[#fff4df] p-2 text-[#c6943f]"><AlertCircle size={17} /></span>
            <span className="flex-1">
              <span className="block text-xs font-semibold">{course.code} · {course.title}</span>
              <span className="mt-1 block text-[11px] text-[#9aa0a9]">{course.feedback || "Open the result sheet to correct and resubmit."}</span>
            </span>
            <StatusBadge value={course.submissionStatus} />
            <ArrowRight size={15} className="text-[#a7adb5]" />
          </Link>)}
          {needAction.length === 0 && <p className="rounded-lg bg-[#f7f9fc] p-4 text-sm text-[#8a909a]">Nothing needs correction right now.</p>}
        </div>
      </section>

      <section className="rounded-xl border border-[#e9ebee] bg-white p-5 sm:p-6">
        <h2 className="text-base font-bold tracking-tight">My submissions</h2>
        <p className="mt-1 text-xs text-[#999fa7]">Result sheets of the current session by status.</p>
        <ul className="mt-5 space-y-3">
          {STATUSES.map((status) => <li key={status} className="flex items-center justify-between text-sm">
            <StatusBadge value={status} />
            <span className="text-lg font-bold tracking-tight">{d.submissionsByStatus[status]}</span>
          </li>)}
        </ul>
        <Link to="/lecturer/submissions" className="mt-5 inline-flex items-center gap-1 text-xs font-semibold text-[#274b82]">View all submissions <ArrowRight size={14} /></Link>
      </section>
    </div>
  </AdminPage>;
}
