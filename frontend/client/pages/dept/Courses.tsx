import { useState, type FormEvent } from "react";
import { toast } from "sonner";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { api } from "../../lib/api";
import { useAsyncData, useDebouncedValue } from "../../hooks/useAsyncData";
import type { Course, CourseType, SemesterName } from "../../types/portal";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ConfirmDialog, ErrorState, Field, Modal, PageHeader, SearchInput, buttonClass, errorMessage, inputClass, primaryClass } from "../../components/admin/ui";
import { LEVELS, firstErrors } from "./shared";

function CourseForm({ row, onClose, onSaved }: { row: Course | null; onClose: () => void; onSaved: () => void }) {
  const programs = useAsyncData(() => api.dept.programs({ per_page: 100 }), []);
  const [form, setForm] = useState({ code: row?.code ?? "", title: row?.title ?? "", units: String(row?.units ?? 3), level: String(row?.level ?? 100), semester: (row?.semester ?? "First") as SemesterName, type: (row?.type ?? "core") as CourseType, programId: row?.programId ?? "" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }));

  async function submit(event: FormEvent) {
    event.preventDefault();
    setSaving(true); setErrors({});
    const body = { code: form.code.trim(), title: form.title.trim(), units: Number(form.units), level: Number(form.level), semester: form.semester, type: form.type, programId: Number(form.programId) };
    try {
      if (row) await api.dept.updateCourse(row.id, body); else await api.dept.createCourse(body);
      toast.success(row ? "Course updated" : "Course added");
      onSaved(); onClose();
    } catch (err) {
      const found = firstErrors(err);
      if (Object.keys(found).length) setErrors(found); else toast.error(errorMessage(err));
    } finally { setSaving(false); }
  }

  return <Modal title={row ? `Edit ${row.code}` : "Add course"} description="The course is created in your department. Assign a lecturer on the Course assignments page." onClose={onClose}>
    <form onSubmit={submit} className="mt-5 space-y-4" noValidate>
      <div className="grid gap-4 sm:grid-cols-[1fr_2fr]">
        <Field label="Code" required error={errors.code}><input className={`${inputClass} mt-1`} value={form.code} onChange={(e) => set("code", e.target.value)} placeholder="CSC401" /></Field>
        <Field label="Title" required error={errors.title}><input className={`${inputClass} mt-1`} value={form.title} onChange={(e) => set("title", e.target.value)} /></Field>
      </div>
      <div className="grid gap-4 sm:grid-cols-3">
        <Field label="Units" required error={errors.units}><input type="number" min={1} max={6} className={`${inputClass} mt-1`} value={form.units} onChange={(e) => set("units", e.target.value)} /></Field>
        <Field label="Level" required error={errors.level}><select className={`${inputClass} mt-1`} value={form.level} onChange={(e) => set("level", e.target.value)}>{LEVELS.map((l) => <option key={l} value={l}>{l}</option>)}</select></Field>
        <Field label="Semester" required error={errors.semester}><select className={`${inputClass} mt-1`} value={form.semester} onChange={(e) => set("semester", e.target.value)}><option>First</option><option>Second</option></select></Field>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Type" required error={errors.type}><select className={`${inputClass} mt-1`} value={form.type} onChange={(e) => set("type", e.target.value)}><option value="core">Core</option><option value="elective">Elective</option></select></Field>
        <Field label="Program" required error={errors.programId}><select className={`${inputClass} mt-1`} value={form.programId} onChange={(e) => set("programId", e.target.value)}><option value="">Select…</option>{programs.data?.data.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</select></Field>
      </div>
      <div className="flex justify-end gap-2 pt-2"><button type="button" className={buttonClass} onClick={onClose}>Cancel</button><button className={primaryClass} disabled={saving}>{saving ? "Saving…" : "Save"}</button></div>
    </form>
  </Modal>;
}

export default function Courses() {
  const [search, setSearch] = useState("");
  const [level, setLevel] = useState("");
  const [page, setPage] = useState(1);
  const query = useDebouncedValue(search);
  const [dialog, setDialog] = useState<{ row: Course | null } | null>(null);
  const [removing, setRemoving] = useState<Course | null>(null);
  const [busy, setBusy] = useState(false);
  const { data, loading, error, reload } = useAsyncData(() => api.dept.courses({ page, search: query, level }), [page, query, level]);

  async function remove() {
    if (!removing) return;
    setBusy(true);
    try { await api.dept.deleteCourse(removing.id); toast.success(`${removing.code} deleted`); reload(); } catch (err) { toast.error(errorMessage(err)); }
    setBusy(false); setRemoving(null);
  }

  return <AdminPage>
    <PageHeader eyebrow="Academics" title="Courses" description="Courses of your department. Units, level and semester are locked once students register or results exist."
      actions={<button className={primaryClass} onClick={() => setDialog({ row: null })}><Plus size={16} /> Add course</button>} />
    {error ? <ErrorState error={error} onRetry={reload} /> : <DataTable<Course>
      title="Department courses" subtitle={data ? `${data.meta.total} found` : undefined}
      rows={data?.data ?? []} rowKey={(r) => r.id} loading={loading && !data}
      page={page} lastPage={data?.meta.last_page} total={data?.meta.total} onPageChange={setPage}
      toolbar={<>
        <select aria-label="Filter by level" className={`${inputClass} !w-auto`} value={level} onChange={(e) => { setLevel(e.target.value); setPage(1); }}><option value="">All levels</option>{LEVELS.map((l) => <option key={l} value={l}>{l} level</option>)}</select>
        <SearchInput label="Search courses" value={search} onChange={(v) => { setSearch(v); setPage(1); }} />
      </>}
      columns={[
        { key: "course", header: "Course", render: (r) => <div><div className="font-semibold text-[#343c48]">{r.code}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{r.title}</div></div> },
        { key: "units", header: "Units", render: (r) => r.units },
        { key: "level", header: "Level", render: (r) => r.level },
        { key: "semester", header: "Semester", render: (r) => r.semester },
        { key: "type", header: "Type", render: (r) => (r.type === "core" ? "Core" : "Elective") },
        { key: "lecturer", header: "Lecturer", render: (r) => r.lecturerName ?? "—" },
        { key: "actions", header: "", render: (r) => <div className="flex gap-2"><button className={buttonClass} onClick={() => setDialog({ row: r })}><Pencil size={14} /> Edit</button><button className={buttonClass} aria-label={`Delete ${r.code}`} onClick={() => setRemoving(r)}><Trash2 size={14} /></button></div> },
      ]} />}
    {dialog && <CourseForm row={dialog.row} onClose={() => setDialog(null)} onSaved={reload} />}
    {removing && <ConfirmDialog danger title="Delete course" message={`Delete ${removing.code} · ${removing.title}? This is blocked if students registered for it or results exist.`} confirmLabel="Delete" busy={busy} onConfirm={remove} onCancel={() => setRemoving(null)} />}
  </AdminPage>;
}
