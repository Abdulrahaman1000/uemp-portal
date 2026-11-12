import { useState } from "react";
import { toast } from "sonner";
import { UserCog } from "lucide-react";
import { ApiError, api } from "../../lib/api";
import { useAsyncData, useDebouncedValue } from "../../hooks/useAsyncData";
import type { Course } from "../../types/portal";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ErrorState, Field, Modal, PageHeader, SearchInput, buttonClass, dangerClass, errorMessage, inputClass, primaryClass } from "../../components/admin/ui";

function AssignModal({ course, onClose, onSaved }: { course: Course; onClose: () => void; onSaved: () => void }) {
  const lecturers = useAsyncData(() => api.dept.lecturers({ per_page: 100, status: "active" }), []);
  const [lecturerId, setLecturerId] = useState(course.lecturerId ?? "");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function save(id: number | null) {
    setBusy(true); setError("");
    try {
      await api.dept.assignLecturer(course.id, id);
      toast.success(id === null ? `Lecturer removed from ${course.code}` : `Lecturer assigned to ${course.code}`);
      onSaved(); onClose();
    } catch (err) {
      if (err instanceof ApiError && err.isValidation) setError(err.fieldErrors.lecturerId?.[0] ?? err.message);
      else toast.error(errorMessage(err));
    } finally { setBusy(false); }
  }

  return <Modal title={`Assign lecturer · ${course.code}`} description={`${course.title} · assignment applies to the current session`} onClose={onClose}>
    <div className="mt-5"><Field label="Lecturer" error={error}>
      <select className={`${inputClass} mt-1`} value={lecturerId} onChange={(e) => setLecturerId(e.target.value)}>
        <option value="">Select a lecturer…</option>
        {lecturers.data?.data.map((l) => <option key={l.id} value={l.id}>{l.name}{l.specialization ? ` · ${l.specialization}` : ""}</option>)}
      </select>
    </Field></div>
    <div className="mt-6 flex flex-wrap justify-end gap-2">
      {course.lecturerId && <button className={dangerClass} disabled={busy} onClick={() => save(null)}>Remove lecturer</button>}
      <button className={buttonClass} onClick={onClose}>Cancel</button>
      <button className={primaryClass} disabled={busy || !lecturerId || lecturerId === course.lecturerId} onClick={() => save(Number(lecturerId))}>{busy ? "Saving…" : "Assign"}</button>
    </div>
  </Modal>;
}

export default function Assignments() {
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const query = useDebouncedValue(search);
  const [picked, setPicked] = useState<Course | null>(null);
  const { data, loading, error, reload } = useAsyncData(() => api.dept.courses({ page, search: query }), [page, query]);

  return <AdminPage>
    <PageHeader eyebrow="Academics" title="Course assignments" description="Choose which lecturer teaches each course this session. Only the assigned lecturer can enter its results." />
    {error ? <ErrorState error={error} onRetry={reload} /> : <DataTable<Course>
      title="Courses and lecturers" subtitle={data ? `${data.meta.total} courses` : undefined}
      rows={data?.data ?? []} rowKey={(r) => r.id} loading={loading}
      page={page} lastPage={data?.meta.last_page} total={data?.meta.total} onPageChange={setPage}
      toolbar={<SearchInput label="Search courses" value={search} onChange={(v) => { setSearch(v); setPage(1); }} />}
      columns={[
        { key: "course", header: "Course", render: (r) => <div><div className="font-semibold text-[#343c48]">{r.code}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{r.title}</div></div> },
        { key: "level", header: "Level · semester", render: (r) => `${r.level} · ${r.semester}` },
        { key: "lecturer", header: "Assigned lecturer", render: (r) => r.lecturerName ? <span className="font-semibold text-[#343c48]">{r.lecturerName}</span> : <span className="text-[#c0841f]">Not assigned</span> },
        { key: "actions", header: "", render: (r) => <button className={buttonClass} onClick={() => setPicked(r)}><UserCog size={14} /> {r.lecturerId ? "Change" : "Assign"}</button> },
      ]} />}
    {picked && <AssignModal course={picked} onClose={() => setPicked(null)} onSaved={reload} />}
  </AdminPage>;
}
