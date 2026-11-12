import { ArrowRight, BookOpen, Building2, CalendarDays, ClipboardCheck, GraduationCap, Users } from "lucide-react";
import { api } from "../../lib/api";
import { useAuth } from "../../lib/auth";
import { useAsyncData } from "../../hooks/useAsyncData";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ErrorState, Initials, LoadingBlock, PageHeader, StatCard, StatusBadge, formatDate } from "../../components/admin/ui";
import type { AdminSection } from "./sections";

function greeting(): string {
  const hour = new Date().getHours();
  return hour < 12 ? "Good morning" : hour < 18 ? "Good afternoon" : "Good evening";
}

export default function Overview({ go }: { go: (section: AdminSection) => void }) {
  const { user } = useAuth();
  const { data, loading, error, reload } = useAsyncData(async () => {
    const [dashboard, registrations, submissions] = await Promise.all([
      api.admin.dashboard(),
      api.admin.registrations({ page: 1 }),
      api.admin.submissions({ page: 1 }),
    ]);
    return { dashboard, registrations: registrations.data.slice(0, 5), pending: submissions.data.filter((item) => item.status === "submitted").slice(0, 5) };
  });

  const firstName = user?.name.split(" ").filter((part) => !part.endsWith("."))[0] ?? "";
  const dashboard = data?.dashboard;

  return <AdminPage>
    <PageHeader
      eyebrow="University overview"
      title={<>{greeting()}{firstName && `, ${firstName}`}</>}
      description="Here's what's happening across your university today."
      actions={dashboard && <div className="flex items-center gap-2 rounded-lg border border-[#e7e9ed] bg-white px-3.5 py-2.5 text-xs font-semibold text-[#606873]"><CalendarDays size={15} /> {dashboard.currentSession} · {dashboard.currentSemester} semester</div>}
    />
    {error ? <ErrorState error={error} onRetry={reload} /> : loading || !data || !dashboard ? <LoadingBlock /> : <>
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Total students" value={dashboard.totalStudents.toLocaleString()} icon={GraduationCap} />
        <StatCard label="Total lecturers" value={dashboard.totalLecturers.toLocaleString()} icon={Users} />
        <StatCard label="Faculties" value={dashboard.totalFaculties} icon={Building2} note={`${dashboard.totalDepartments} departments`} />
        <StatCard label="Active programs" value={dashboard.activePrograms} icon={BookOpen} />
      </div>
      <div className="mt-6 grid grid-cols-[minmax(0,1fr)] gap-5 xl:grid-cols-[1.65fr_1fr]">
        <DataTable
          title="Recent registrations"
          subtitle="Latest student course registrations"
          rows={data.registrations}
          rowKey={(row) => row.id}
          emptyMessage="No registrations yet."
          action={<button onClick={() => go("registrations")} className="flex items-center gap-1 text-xs font-semibold text-[#274b82]">View all <ArrowRight size={14} /></button>}
          columns={[
            { key: "student", header: "Student", render: (row) => <div className="flex items-center gap-3"><Initials name={row.studentName} /><div><div className="font-semibold text-[#343c48]">{row.studentName}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{row.matricNo}</div></div></div> },
            { key: "department", header: "Department", render: (row) => row.departmentName },
            { key: "units", header: "Units", render: (row) => row.totalUnits },
            { key: "date", header: "Registered", className: "whitespace-nowrap", render: (row) => formatDate(row.registeredAt) },
          ]}
        />
        <section className="rounded-xl border border-[#e9ebee] bg-white p-5 sm:p-6">
          <h2 className="text-base font-bold tracking-tight">Needs your attention</h2>
          <p className="mt-1 text-xs text-[#999fa7]">Result submissions waiting for review</p>
          <button onClick={() => go("oversight")} className="mt-5 flex w-full items-center gap-3 rounded-lg border border-[#edeff2] p-3 text-left hover:bg-[#f9fafc]">
            <span className="rounded-lg bg-[#fff4df] p-2 text-[#c6943f]"><ClipboardCheck size={17} /></span>
            <span className="flex-1"><span className="block text-xs font-semibold">Pending approvals</span><span className="mt-1 block text-[11px] text-[#9aa0a9]">{dashboard.pendingSubmissions} {dashboard.pendingSubmissions === 1 ? "submission" : "submissions"} awaiting review</span></span>
            <ArrowRight size={15} className="text-[#a7adb5]" />
          </button>
          <ul className="mt-4 space-y-3">
            {data.pending.map((item) => <li key={item.id} className="flex items-center justify-between gap-3 text-xs"><span className="min-w-0"><span className="block truncate font-semibold text-[#343c48]">{item.courseCode} · {item.courseTitle}</span><span className="mt-0.5 block text-[11px] text-[#a0a6ae]">{item.lecturerName} · {formatDate(item.submittedAt)}</span></span><StatusBadge value={item.status} /></li>)}
            {data.pending.length === 0 && <li className="text-xs text-[#959ca6]">Nothing is waiting for review.</li>}
          </ul>
        </section>
      </div>
    </>}
  </AdminPage>;
}
