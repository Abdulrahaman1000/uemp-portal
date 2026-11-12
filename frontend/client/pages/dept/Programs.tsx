import { useState } from "react";
import { toast } from "sonner";
import { ApiError, api } from "../../lib/api";
import type { FieldErrors } from "../../lib/api";
import { deptPrograms } from "../../lib/deptPrograms";
import { useAsyncData } from "../../hooks/useAsyncData";
import type { Program } from "../../types/portal";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ErrorState, Field, LoadingBlock, Modal, PageHeader, buttonClass, errorMessage, inputClass, primaryClass } from "../../components/admin/ui";

interface ProgramForm { name: string; award: string; durationYears: string }

const EMPTY_FORM: ProgramForm = { name: "", award: "B.Sc.", durationYears: "4" };

function FieldError({ messages }: { messages?: string[] }) {
  return messages && messages.length > 0 ? <p className="mt-1 text-xs text-[#c0392b]">{messages[0]}</p> : null;
}

export default function Programs() {
  const programs = useAsyncData(() => api.dept.programs({ per_page: 100 }), []);
  const levels = useAsyncData(() => api.dept.report("students"), []);
  const max = Math.max(1, ...(levels.data ?? []).map((r) => r.value));

  const [editing, setEditing] = useState<Program | "new" | null>(null);
  const [form, setForm] = useState<ProgramForm>(EMPTY_FORM);
  const [formErrors, setFormErrors] = useState<FieldErrors>({});
  const [deleting, setDeleting] = useState<Program | null>(null);
  const [saving, setSaving] = useState(false);

  function openCreate() { setForm(EMPTY_FORM); setFormErrors({}); setEditing("new"); }
  function openEdit(program: Program) {
    setForm({ name: program.name, award: program.award, durationYears: String(program.durationYears) });
    setFormErrors({});
    setEditing(program);
  }
  function setField<K extends keyof ProgramForm>(key: K, value: ProgramForm[K]) { setForm((current) => ({ ...current, [key]: value })); }

  async function save() {
    if (!editing) return;
    setSaving(true);
    setFormErrors({});
    const body = { name: form.name, award: form.award, durationYears: Number(form.durationYears) };
    try {
      if (editing === "new") await deptPrograms.create(body);
      else await deptPrograms.update(editing.id, body);
      toast.success(editing === "new" ? "Program created." : "Program updated.");
      setEditing(null);
      programs.reload();
    } catch (err) {
      if (err instanceof ApiError && err.isValidation) setFormErrors(err.fieldErrors);
      toast.error(errorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  async function remove() {
    if (!deleting) return;
    setSaving(true);
    try {
      await deptPrograms.remove(deleting.id);
      toast.success("Program deleted.");
      setDeleting(null);
      programs.reload();
    } catch (err) {
      toast.error(errorMessage(err));
      setDeleting(null);
    } finally {
      setSaving(false);
    }
  }

  return <AdminPage>
    <PageHeader eyebrow="Academics" title="Levels & programs" description="Create and manage the programs of your department, and see how many students are at each level." />
    <div className="grid grid-cols-[minmax(0,1fr)] gap-5 xl:grid-cols-[1.5fr_1fr]">
      {programs.error ? <ErrorState error={programs.error} onRetry={programs.reload} /> : <DataTable<Program>
        title="Programs" subtitle={programs.data ? `${programs.data.meta.total} programs` : undefined}
        action={<button className={primaryClass} onClick={openCreate}>Add program</button>}
        rows={programs.data?.data ?? []} rowKey={(r) => r.id} loading={programs.loading && !programs.data}
        emptyMessage="No programs yet."
        columns={[
          { key: "name", header: "Program", render: (r) => <span className="font-semibold text-[#343c48]">{r.name}</span> },
          { key: "award", header: "Award", render: (r) => r.award },
          { key: "years", header: "Duration", render: (r) => `${r.durationYears} ${r.durationYears === 1 ? "year" : "years"}` },
          { key: "students", header: "Students", render: (r) => r.studentCount },
          { key: "actions", header: "Actions", render: (r) => <div className="flex gap-2"><button className={buttonClass} onClick={() => openEdit(r)}>Edit</button><button className={buttonClass} onClick={() => setDeleting(r)}>Delete</button></div> },
        ]} />}
      <section className="rounded-xl border border-[#e9ebee] bg-white p-5 sm:p-6">
        <h2 className="text-base font-bold tracking-tight">Students by level</h2>
        {levels.error ? <ErrorState error={levels.error} onRetry={levels.reload} /> : !levels.data ? <div className="mt-4"><LoadingBlock /></div> : levels.data.length === 0 ? <p className="py-8 text-center text-sm text-[#959ca6]">No students yet.</p> : <ul className="mt-5 space-y-4">
          {levels.data.map((row) => <li key={row.label}>
            <div className="mb-1.5 flex justify-between text-xs"><span className="font-medium text-[#343c48]">{row.label}</span><span className="font-bold">{row.value}</span></div>
            <div className="h-2.5 overflow-hidden rounded-full bg-[#edf1f6]"><div className="h-full rounded-full bg-[#2454b8]" style={{ width: `${(row.value / max) * 100}%` }} /></div>
          </li>)}
        </ul>}
      </section>
    </div>

    {editing && <Modal title={editing === "new" ? "Add program" : "Edit program"} description="The program is created in your own department." onClose={() => setEditing(null)}>
      <div className="mt-6 space-y-4">
        <Field label="Program name"><input className={`${inputClass} mt-1.5`} value={form.name} onChange={(e) => setField("name", e.target.value)} /><FieldError messages={formErrors.name} /></Field>
        <Field label="Award (e.g. B.Sc.)"><input className={`${inputClass} mt-1.5`} value={form.award} onChange={(e) => setField("award", e.target.value)} /><FieldError messages={formErrors.award} /></Field>
        <Field label="Duration (years)"><input type="number" min={1} max={7} className={`${inputClass} mt-1.5`} value={form.durationYears} onChange={(e) => setField("durationYears", e.target.value)} /><FieldError messages={formErrors.durationYears} /></Field>
        <div className="flex justify-end gap-2 pt-2">
          <button className={buttonClass} onClick={() => setEditing(null)}>Cancel</button>
          <button className={primaryClass} disabled={saving} onClick={save}>{saving ? "Saving…" : editing === "new" ? "Create program" : "Save changes"}</button>
        </div>
      </div>
    </Modal>}

    {deleting && <Modal title="Delete program" description={`${deleting.name} (${deleting.award})`} onClose={() => setDeleting(null)}>
      <div className="mt-6 space-y-4">
        <p className="text-sm text-[#606873]">This cannot be undone. A program that still has students or courses cannot be deleted.</p>
        <div className="flex justify-end gap-2 pt-2">
          <button className={buttonClass} onClick={() => setDeleting(null)}>Cancel</button>
          <button className={primaryClass} disabled={saving} onClick={remove}>{saving ? "Deleting…" : "Delete program"}</button>
        </div>
      </div>
    </Modal>}
  </AdminPage>;
}
