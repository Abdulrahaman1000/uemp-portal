import { useState } from "react";
import { Eye } from "lucide-react";
import { api } from "../../lib/api";
import { useAsyncData, useDebouncedValue } from "../../hooks/useAsyncData";
import type { RegistrationRecord } from "../../types/portal";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ErrorState, Modal, PageHeader, SearchInput, buttonClass, formatDate, inputClass } from "../../components/admin/ui";
import { LEVELS } from "./shared";

export default function Registrations() {
  const [search, setSearch] = useState("");
  const [level, setLevel] = useState("");
  const [page, setPage] = useState(1);
  const query = useDebouncedValue(search);
  const [open, setOpen] = useState<RegistrationRecord | null>(null);
  const { data, loading, error, reload } = useAsyncData(() => api.dept.registrations({ page, search: query, level }), [page, query, level]);

  return <AdminPage>
    <PageHeader eyebrow="Registration" title="Course registrations" description="What your students registered, by semester. Students register only courses of their own department, program, level and the current semester." />
    {error ? <ErrorState error={error} onRetry={reload} /> : <DataTable<RegistrationRecord>
      title="Registrations" subtitle={data ? `${data.meta.total} found` : undefined}
      rows={data?.data ?? []} rowKey={(r) => r.id} loading={loading}
      page={page} lastPage={data?.meta.last_page} total={data?.meta.total} onPageChange={setPage}
      toolbar={<>
        <select aria-label="Filter by level" className={`${inputClass} !w-auto`} value={level} onChange={(e) => { setLevel(e.target.value); setPage(1); }}><option value="">All levels</option>{LEVELS.map((l) => <option key={l} value={l}>{l} level</option>)}</select>
        <SearchInput label="Search registrations" value={search} onChange={(v) => { setSearch(v); setPage(1); }} />
      </>}
      columns={[
        { key: "student", header: "Student", render: (r) => <div><div className="font-semibold text-[#343c48]">{r.studentName}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{r.matricNo}</div></div> },
        { key: "level", header: "Level", render: (r) => r.level },
        { key: "term", header: "Session", render: (r) => `${r.sessionName} · ${r.semester}` },
        { key: "courses", header: "Courses", render: (r) => r.courses.length },
        { key: "units", header: "Units", render: (r) => r.totalUnits },
        { key: "at", header: "Registered", render: (r) => formatDate(r.registeredAt) },
        { key: "actions", header: "", render: (r) => <button className={buttonClass} onClick={() => setOpen(r)}><Eye size={14} /> View</button> },
      ]} />}
    {open && <Modal title={`${open.studentName} · ${open.matricNo}`} description={`${open.sessionName} · ${open.semester} semester · ${open.totalUnits} units`} onClose={() => setOpen(null)}>
      <ul className="mt-4 divide-y divide-[#f1f2f4] text-sm">{open.courses.map((c) => <li key={c.courseId} className="flex justify-between py-2.5"><span><span className="font-semibold text-[#343c48]">{c.code}</span> <span className="text-[#8a909a]">{c.title}</span></span><span className="text-xs text-[#8a909a]">{c.units} units</span></li>)}</ul>
      <div className="mt-5 flex justify-end"><button className={buttonClass} onClick={() => setOpen(null)}>Close</button></div>
    </Modal>}
  </AdminPage>;
}
