import { useState } from "react";
import { toast } from "sonner";
import { CheckCircle2 } from "lucide-react";
import { ApiError, api } from "../../lib/api";
import { useAsyncData } from "../../hooks/useAsyncData";
import { AdminPage, ConfirmDialog, ErrorState, LoadingBlock, PageHeader, StatusBadge, errorMessage } from "../../components/admin/ui";

export default function Registration() {
  const { data, error, reload } = useAsyncData(() => api.student.availableCourses(), []);
  const [picked, setPicked] = useState<string[]>([]);
  const [confirming, setConfirming] = useState(false);
  const [busy, setBusy] = useState(false);
  const [courseErrors, setCourseErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState("");

  if (error) return <AdminPage><ErrorState error={error} onRetry={reload} /></AdminPage>;
  if (!data) return <AdminPage><LoadingBlock /></AdminPage>;

  const courses = data.data;
  const open = courses.filter((c) => !c.registered);
  const selected = open.filter((c) => picked.includes(c.id));
  const units = selected.reduce((sum, c) => sum + c.units, 0);
  const toggle = (id: string) => setPicked((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]));

  async function submit() {
    setBusy(true); setCourseErrors({}); setFormError("");
    const ids = selected.map((c) => c.id);
    try {
      await api.student.register(ids);
      toast.success(`${ids.length} course${ids.length === 1 ? "" : "s"} registered`);
      setPicked([]); setConfirming(false); reload();
    } catch (err) {
      setConfirming(false);
      if (err instanceof ApiError && err.isValidation) {
        // Errors come back keyed courseIds.N, where N is the position in the request.
        const byCourse: Record<string, string> = {};
        Object.entries(err.fieldErrors).forEach(([key, messages]) => {
          const match = /^courseIds\.(\d+)$/.exec(key);
          const course = match ? selected[Number(match[1])] : undefined;
          if (course) byCourse[course.id] = messages[0]; else setFormError(messages[0]);
        });
        setCourseErrors(byCourse);
        if (!Object.keys(byCourse).length && !Object.keys(err.fieldErrors).length) setFormError(err.message);
        if (err.message && !Object.keys(byCourse).length) setFormError((cur) => cur || err.message);
        reload();
      } else toast.error(errorMessage(err));
    } finally { setBusy(false); }
  }

  return <AdminPage>
    <PageHeader eyebrow={data.sessionName ? `${data.sessionName} · ${data.semester} semester` : "Registration"} title="Course registration"
      description="Courses for your department, program, level and the current semester. A course can only be registered once per semester." />
    {!data.sessionName ? <div role="status" className="rounded-xl border border-[#e9ebee] bg-white p-6 text-sm text-[#8a909a]">Registration is closed: there is no current semester.</div> : <>
      {formError && <div role="alert" className="mb-4 rounded-lg border border-[#f1d5d5] bg-[#fdf3f3] px-4 py-3 text-sm text-[#9b3131]">{formError}</div>}
      <section className="overflow-hidden rounded-xl border border-[#e9ebee] bg-white">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[640px] text-left">
            <thead className="bg-[#f8f9fa] text-[10px] font-semibold uppercase tracking-[.09em] text-[#9ca2ab]"><tr>
              {["", "Course", "Units", "Type", "Lecturer", "Status"].map((h) => <th key={h} className="px-5 py-3.5 font-semibold">{h}</th>)}</tr></thead>
            <tbody>
              {courses.map((c) => <tr key={c.id} className="border-t border-[#f1f2f4] text-xs">
                <td className="px-5 py-4"><input type="checkbox" aria-label={`Select ${c.code}`} className="h-4 w-4 accent-[#2454b8]" disabled={c.registered} checked={c.registered || picked.includes(c.id)} onChange={() => toggle(c.id)} /></td>
                <td className="px-5 py-4"><div className="font-semibold text-[#343c48]">{c.code}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{c.title}</div>{courseErrors[c.id] && <div role="alert" className="mt-1 text-[11px] text-[#c0392b]">{courseErrors[c.id]}</div>}</td>
                <td className="px-5 py-4 text-[#737b87]">{c.units}</td>
                <td className="px-5 py-4 text-[#737b87]">{c.type === "core" ? "Core" : "Elective"}</td>
                <td className="px-5 py-4 text-[#737b87]">{c.lecturerName ?? "—"}</td>
                <td className="px-5 py-4">{c.registered ? <span className="inline-flex items-center gap-1 text-[#25623c]"><CheckCircle2 size={14} /> Registered</span> : <StatusBadge value="open" />}</td>
              </tr>)}
            </tbody>
          </table>
          {courses.length === 0 && <div className="px-5 py-14 text-center text-sm text-[#959ca6]">No courses are available for your department, program and level this semester.</div>}
        </div>
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[#f0f1f3] px-5 py-4">
          <span className="text-sm text-[#737b87]">{selected.length} selected · <strong className="text-[#343c48]">{units} units</strong></span>
          <button className="inline-flex items-center justify-center gap-2 rounded-lg bg-[#2454b8] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1d4294] disabled:opacity-60" disabled={selected.length === 0} onClick={() => setConfirming(true)}>Register selected courses</button>
        </div>
      </section>
    </>}
    {confirming && <ConfirmDialog title="Confirm registration" message={`Register ${selected.length} course${selected.length === 1 ? "" : "s"} (${units} units) for the ${data.semester} semester? ${selected.map((c) => c.code).join(", ")}`} confirmLabel="Register" busy={busy} onConfirm={submit} onCancel={() => setConfirming(false)} />}
  </AdminPage>;
}
