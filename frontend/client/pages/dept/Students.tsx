import { useState, type FormEvent } from "react";
import { toast } from "sonner";
import { Pencil, Plus } from "lucide-react";
import { api } from "../../lib/api";
import { useAsyncData, useDebouncedValue } from "../../hooks/useAsyncData";
import type { Student } from "../../types/portal";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ErrorState, Field, Modal, PageHeader, SearchInput, buttonClass, errorMessage, inputClass, primaryClass } from "../../components/admin/ui";
import { LEVELS, StatusSelect, firstErrors } from "./shared";

type Form = { name: string; email: string; matricNo: string; password: string; programId: string; level: string; phone: string };
const empty: Form = { name: "", email: "", matricNo: "", password: "", programId: "", level: "100", phone: "" };

function StudentForm({ row, onClose, onSaved }: { row: Student | null; onClose: () => void; onSaved: () => void }) {
  const programs = useAsyncData(() => api.dept.programs({ per_page: 100 }), []);
  const [form, setForm] = useState<Form>(row ? { ...empty, name: row.name, email: row.email, programId: row.programId, level: String(row.level) } : empty);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const set = (key: keyof Form, value: string) => setForm((f) => ({ ...f, [key]: value }));

  async function submit(event: FormEvent) {
    event.preventDefault();
    setSaving(true); setErrors({});
    try {
      const base = { name: form.name.trim(), email: form.email.trim(), programId: Number(form.programId), level: Number(form.level), phone: form.phone.trim() || null };
      if (row) await api.dept.updateStudent(row.id, base);
      else await api.dept.createStudent({ ...base, matricNo: form.matricNo.trim(), password: form.password });
      toast.success(row ? "Student updated" : "Student added");
      onSaved(); onClose();
    } catch (err) {
      const found = firstErrors(err);
      if (Object.keys(found).length) setErrors(found); else toast.error(errorMessage(err));
    } finally { setSaving(false); }
  }

  return <Modal title={row ? "Edit student" : "Add student"} description={row ? row.matricNo : "The student can log in with the email and password you set."} onClose={onClose}>
    <form onSubmit={submit} className="mt-5 space-y-4" noValidate>
      <Field label="Full name" required error={errors.name}><input className={`${inputClass} mt-1`} value={form.name} onChange={(e) => set("name", e.target.value)} /></Field>
      <Field label="Email" required error={errors.email}><input type="email" className={`${inputClass} mt-1`} value={form.email} onChange={(e) => set("email", e.target.value)} /></Field>
      {!row && <>
        <Field label="Matric number" required error={errors.matricNo}><input className={`${inputClass} mt-1`} value={form.matricNo} onChange={(e) => set("matricNo", e.target.value)} placeholder="CSC/2026/001" /></Field>
        <Field label="Initial password" required error={errors.password}><input type="password" autoComplete="new-password" className={`${inputClass} mt-1`} value={form.password} onChange={(e) => set("password", e.target.value)} placeholder="At least 8 characters" /></Field>
      </>}
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Program" required error={errors.programId}>
          <select className={`${inputClass} mt-1`} value={form.programId} onChange={(e) => set("programId", e.target.value)}><option value="">Select…</option>{programs.data?.data.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</select>
        </Field>
        <Field label="Level" required error={errors.level}><select className={`${inputClass} mt-1`} value={form.level} onChange={(e) => set("level", e.target.value)}>{LEVELS.map((l) => <option key={l} value={l}>{l}</option>)}</select></Field>
      </div>
      <Field label="Phone" error={errors.phone}><input className={`${inputClass} mt-1`} value={form.phone} onChange={(e) => set("phone", e.target.value)} /></Field>
      <div className="flex justify-end gap-2 pt-2"><button type="button" className={buttonClass} onClick={onClose}>Cancel</button><button className={primaryClass} disabled={saving}>{saving ? "Saving…" : "Save"}</button></div>
    </form>
  </Modal>;
}

export default function Students() {
  const [search, setSearch] = useState("");
  const [level, setLevel] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const query = useDebouncedValue(search);
  const [dialog, setDialog] = useState<{ row: Student | null } | null>(null);
  const { data, loading, error, reload } = useAsyncData(() => api.dept.students({ page, search: query, level, status }), [page, query, level, status]);

  async function changeStatus(row: Student, next: "active" | "inactive" | "suspended") {
    try { await api.dept.setStudentStatus(row.id, next); toast.success(`${row.name} is now ${next}`); } catch (err) { toast.error(errorMessage(err)); }
    reload();
  }

  return <AdminPage>
    <PageHeader eyebrow="People" title="Students" description="Students of your department. Inactive or suspended students cannot sign in."
      actions={<button className={primaryClass} onClick={() => setDialog({ row: null })}><Plus size={16} /> Add student</button>} />
    {error ? <ErrorState error={error} onRetry={reload} /> : <DataTable<Student>
      title="Department students" subtitle={data ? `${data.meta.total} found` : undefined}
      rows={data?.data ?? []} rowKey={(r) => r.id} loading={loading}
      page={page} lastPage={data?.meta.last_page} total={data?.meta.total} onPageChange={setPage}
      toolbar={<>
        <select aria-label="Filter by level" className={`${inputClass} !w-auto`} value={level} onChange={(e) => { setLevel(e.target.value); setPage(1); }}><option value="">All levels</option>{LEVELS.map((l) => <option key={l} value={l}>{l} level</option>)}</select>
        <select aria-label="Filter by status" className={`${inputClass} !w-auto`} value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }}><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option><option value="graduated">Graduated</option></select>
        <SearchInput label="Search students" value={search} onChange={(v) => { setSearch(v); setPage(1); }} />
      </>}
      columns={[
        { key: "name", header: "Student", render: (r) => <div><div className="font-semibold text-[#343c48]">{r.name}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{r.email}</div></div> },
        { key: "matric", header: "Matric no", render: (r) => r.matricNo },
        { key: "program", header: "Program", render: (r) => r.programName },
        { key: "level", header: "Level", render: (r) => r.level },
        { key: "status", header: "Status", render: (r) => <StatusSelect label={`Status of ${r.name}`} value={r.status} onChange={(s) => changeStatus(r, s)} /> },
        { key: "actions", header: "", render: (r) => <button className={buttonClass} onClick={() => setDialog({ row: r })}><Pencil size={14} /> Edit</button> },
      ]} />}
    {dialog && <StudentForm row={dialog.row} onClose={() => setDialog(null)} onSaved={reload} />}
  </AdminPage>;
}
