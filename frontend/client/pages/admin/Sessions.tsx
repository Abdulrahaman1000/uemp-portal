import { useState, type FormEvent } from "react";
import { CalendarDays, Plus } from "lucide-react";
import { toast } from "sonner";
import { api, ApiError } from "../../lib/api";
import { useAsyncData } from "../../hooks/useAsyncData";
import { AdminPage, ConfirmDialog, ErrorState, Field, LoadingBlock, Modal, PageHeader, StatusBadge, buttonClass, errorMessage, formatDate, inputClass, primaryClass } from "../../components/admin/ui";

type Pending = { kind: "session" | "semester"; id: string; label: string };

export default function Sessions() {
  const { data, loading, error, reload } = useAsyncData(() => api.admin.list("sessions", { per_page: 50 }));
  const [pending, setPending] = useState<Pending | null>(null);
  const [busy, setBusy] = useState(false);
  const [adding, setAdding] = useState(false);
  const [form, setForm] = useState({ name: "", startDate: "", endDate: "" });
  const [formError, setFormError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  async function activate() {
    if (!pending) return;
    setBusy(true);
    try {
      if (pending.kind === "session") await api.admin.activateSession(pending.id);
      else await api.admin.activateSemester(pending.id);
      toast.success(`${pending.label} is now current.`);
      setPending(null);
      reload();
    } catch (err) {
      toast.error(errorMessage(err));
      setPending(null);
    } finally {
      setBusy(false);
    }
  }

  async function addSession(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setFormError("");
    setFieldErrors({});
    try {
      await api.admin.create("sessions", { name: form.name.trim(), startDate: form.startDate, endDate: form.endDate });
      toast.success("Session added.");
      setAdding(false);
      setForm({ name: "", startDate: "", endDate: "" });
      reload();
    } catch (err) {
      if (err instanceof ApiError && err.isValidation) setFieldErrors(err.fieldErrors);
      setFormError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return <AdminPage>
    <PageHeader
      eyebrow="University management"
      title="Sessions & semesters"
      description="Only one session and one semester can be current at a time."
      actions={<button className={primaryClass} onClick={() => { setAdding(true); setFormError(""); setFieldErrors({}); }}><Plus size={16} /> Add session</button>}
    />
    {error ? <ErrorState error={error} onRetry={reload} /> : loading && !data ? <LoadingBlock /> : <div className="space-y-5">
      {data?.data.length === 0 && <div className="rounded-xl border border-[#e9ebee] bg-white py-14 text-center text-sm text-[#959ca6]">No sessions yet.</div>}
      {data?.data.map((session) => <section key={session.id} className="overflow-hidden rounded-xl border border-[#e9ebee] bg-white">
        <div className="flex flex-wrap items-center justify-between gap-3 p-5">
          <div className="flex items-center gap-3">
            <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-[#edf1f6] text-[#425f8b]"><CalendarDays size={18} /></span>
            <div>
              <h2 className="text-base font-bold tracking-tight">{session.name}</h2>
              <p className="mt-1 text-xs text-[#9aa1ab]">{formatDate(session.startDate)} – {formatDate(session.endDate)}</p>
            </div>
            {session.isCurrent && <StatusBadge value="Current" />}
          </div>
          {!session.isCurrent && <button className={buttonClass} onClick={() => setPending({ kind: "session", id: session.id, label: `Session ${session.name}` })}>Set as current</button>}
        </div>
        <ul className="border-t border-[#f1f2f4]">
          {session.semesters.map((semester) => <li key={semester.id} className="flex flex-wrap items-center justify-between gap-3 border-b border-[#f6f7f8] px-5 py-3.5 text-xs last:border-b-0">
            <div><span className="font-semibold text-[#343c48]">{semester.name} semester</span><span className="ml-3 text-[#a0a6ae]">{formatDate(semester.startDate)} – {formatDate(semester.endDate)}</span></div>
            <div className="flex items-center gap-3">
              {semester.isCurrent ? <StatusBadge value="Current" /> : <button
                className={buttonClass}
                disabled={!session.isCurrent}
                title={session.isCurrent ? undefined : "Make this session current first"}
                onClick={() => setPending({ kind: "semester", id: semester.id, label: `${semester.name} semester` })}
              >Set as current</button>}
            </div>
          </li>)}
          {session.semesters.length === 0 && <li className="px-5 py-4 text-xs text-[#959ca6]">No semesters in this session.</li>}
        </ul>
      </section>)}
    </div>}

    {pending && <ConfirmDialog title="Change the current period?" message={`${pending.label} will become the current ${pending.kind}. The previous one will stop being current.`} confirmLabel="Set as current" busy={busy} onConfirm={activate} onCancel={() => setPending(null)} />}

    {adding && <Modal title="Add session" description="Use the format 2027/2028 for the name." onClose={() => setAdding(false)}>
      <form className="mt-6 space-y-4" onSubmit={addSession}>
        {formError && <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{formError}</p>}
        <Field label="Session name" required error={fieldErrors.name?.[0]}><input required className={`${inputClass} mt-1.5`} placeholder="2027/2028" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></Field>
        <Field label="Start date" required error={fieldErrors.startDate?.[0]}><input required type="date" className={`${inputClass} mt-1.5`} value={form.startDate} onChange={(e) => setForm({ ...form, startDate: e.target.value })} /></Field>
        <Field label="End date" required error={fieldErrors.endDate?.[0]}><input required type="date" className={`${inputClass} mt-1.5`} value={form.endDate} onChange={(e) => setForm({ ...form, endDate: e.target.value })} /></Field>
        <div className="flex justify-end gap-2 pt-2">
          <button type="button" className={buttonClass} onClick={() => setAdding(false)}>Cancel</button>
          <button type="submit" className={primaryClass} disabled={busy}>{busy ? "Saving…" : "Save session"}</button>
        </div>
      </form>
    </Modal>}
  </AdminPage>;
}
