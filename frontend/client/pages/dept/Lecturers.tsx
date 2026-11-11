import { useState, type FormEvent } from "react";
import { toast } from "sonner";
import { Pencil, Plus } from "lucide-react";
import { api } from "../../lib/api";
import { useAsyncData, useDebouncedValue } from "../../hooks/useAsyncData";
import type { Lecturer } from "../../types/portal";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ErrorState, Field, Modal, PageHeader, SearchInput, buttonClass, errorMessage, inputClass, primaryClass } from "../../components/admin/ui";
import { StatusSelect, firstErrors } from "./shared";

function LecturerForm({ row, onClose, onSaved }: { row: Lecturer | null; onClose: () => void; onSaved: () => void }) {
  const [form, setForm] = useState({ name: row?.name ?? "", email: row?.email ?? "", password: "", specialization: row?.specialization ?? "" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }));

  async function submit(event: FormEvent) {
    event.preventDefault();
    setSaving(true); setErrors({});
    try {
      const base = { name: form.name.trim(), email: form.email.trim(), specialization: form.specialization.trim() || null };
      if (row) await api.dept.updateLecturer(row.id, base); else await api.dept.createLecturer({ ...base, password: form.password });
      toast.success(row ? "Lecturer updated" : "Lecturer added");
      onSaved(); onClose();
    } catch (err) {
      const found = firstErrors(err);
      if (Object.keys(found).length) setErrors(found); else toast.error(errorMessage(err));
    } finally { setSaving(false); }
  }

  return <Modal title={row ? "Edit lecturer" : "Add lecturer"} onClose={onClose}>
    <form onSubmit={submit} className="mt-5 space-y-4" noValidate>
      <Field label="Full name" required error={errors.name}><input className={`${inputClass} mt-1`} value={form.name} onChange={(e) => set("name", e.target.value)} /></Field>
      <Field label="Email" required error={errors.email}><input type="email" className={`${inputClass} mt-1`} value={form.email} onChange={(e) => set("email", e.target.value)} /></Field>
      {!row && <Field label="Initial password" required error={errors.password}><input type="password" autoComplete="new-password" className={`${inputClass} mt-1`} value={form.password} onChange={(e) => set("password", e.target.value)} placeholder="At least 8 characters" /></Field>}
      <Field label="Specialization" error={errors.specialization}><input className={`${inputClass} mt-1`} value={form.specialization} onChange={(e) => set("specialization", e.target.value)} /></Field>
      <div className="flex justify-end gap-2 pt-2"><button type="button" className={buttonClass} onClick={onClose}>Cancel</button><button className={primaryClass} disabled={saving}>{saving ? "Saving…" : "Save"}</button></div>
    </form>
  </Modal>;
}

export default function Lecturers() {
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const query = useDebouncedValue(search);
  const [dialog, setDialog] = useState<{ row: Lecturer | null } | null>(null);
  const { data, loading, error, reload } = useAsyncData(() => api.dept.lecturers({ page, search: query }), [page, query]);

  async function changeStatus(row: Lecturer, next: "active" | "inactive" | "suspended") {
    try { await api.dept.setLecturerStatus(row.id, next); toast.success(`${row.name} is now ${next}`); } catch (err) { toast.error(errorMessage(err)); }
    reload();
  }

  return <AdminPage>
    <PageHeader eyebrow="People" title="Lecturers" description="Lecturers of your department and their course load this session."
      actions={<button className={primaryClass} onClick={() => setDialog({ row: null })}><Plus size={16} /> Add lecturer</button>} />
    {error ? <ErrorState error={error} onRetry={reload} /> : <DataTable<Lecturer>
      title="Department lecturers" subtitle={data ? `${data.meta.total} found` : undefined}
      rows={data?.data ?? []} rowKey={(r) => r.id} loading={loading && !data}
      page={page} lastPage={data?.meta.last_page} total={data?.meta.total} onPageChange={setPage}
      toolbar={<SearchInput label="Search lecturers" value={search} onChange={(v) => { setSearch(v); setPage(1); }} />}
      columns={[
        { key: "name", header: "Lecturer", render: (r) => <div><div className="font-semibold text-[#343c48]">{r.name}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{r.email}</div></div> },
        { key: "spec", header: "Specialization", render: (r) => r.specialization || "—" },
        { key: "courses", header: "Courses", render: (r) => r.courseCount },
        { key: "status", header: "Status", render: (r) => <StatusSelect label={`Status of ${r.name}`} value={r.status} onChange={(s) => changeStatus(r, s)} /> },
        { key: "actions", header: "", render: (r) => <button className={buttonClass} onClick={() => setDialog({ row: r })}><Pencil size={14} /> Edit</button> },
      ]} />}
    {dialog && <LecturerForm row={dialog.row} onClose={() => setDialog(null)} onSaved={reload} />}
  </AdminPage>;
}
