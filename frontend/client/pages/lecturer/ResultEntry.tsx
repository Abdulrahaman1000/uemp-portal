import { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import { AlertCircle, Lock, Save, Send } from "lucide-react";
import { ApiError, api } from "../../lib/api";
import { useAsyncData } from "../../hooks/useAsyncData";
import type { ClassRow, ClassSheet } from "../../types/portal";
import { AdminPage, ConfirmDialog, ErrorState, LoadingBlock, PageHeader, StatusBadge, errorMessage, formatDateTime, inputClass, primaryClass, buttonClass } from "../../components/admin/ui";

/** What the lecturer has typed, per student. Text so a half-typed number is never lost. */
type Draft = Record<string, { ca: string; exam: string }>;
type Parsed = { value: number | null; error?: string };

const toText = (value: number | null) => (value === null ? "" : String(value));
const toDraft = (sheet: ClassSheet): Draft =>
  Object.fromEntries(sheet.students.map((row) => [row.studentId, { ca: toText(row.caScore), exam: toText(row.examScore) }]));

/** Empty = not entered yet. Otherwise a number from 0 to max with at most 2 decimals. */
function parseScore(text: string, max: number): Parsed {
  const trimmed = text.trim();
  if (trimmed === "") return { value: null };
  if (!/^\d+(\.\d{1,2})?$/.test(trimmed)) return { value: null, error: "Enter a number, up to 2 decimals" };
  const value = Number(trimmed);
  if (value > max) return { value: null, error: `Cannot be more than ${max}` };
  return { value };
}

function Banner({ sheet }: { sheet: ClassSheet }) {
  const editableStatus = ["draft", "rejected", "reopened"].includes(sheet.status);
  if (sheet.status === "rejected" || sheet.status === "reopened") {
    return <div role="status" className={`mb-5 flex gap-3 rounded-xl border p-4 text-sm ${sheet.status === "rejected" ? "border-[#f1d5d5] bg-[#fdf3f3] text-[#9b3131]" : "border-[#f3e2bd] bg-[#fff8e8] text-[#8a5e12]"}`}>
      <AlertCircle size={18} className="mt-0.5 shrink-0" />
      <div>
        <p className="font-semibold">{sheet.status === "rejected" ? "The department sent these results back for correction." : "The department reopened these results for correction."}</p>
        <p className="mt-1">{sheet.feedback || "No feedback was given."}</p>
        {!sheet.canEdit && <p className="mt-1 text-xs">Editing is not available now: this course is not in the current semester.</p>}
      </div>
    </div>;
  }
  if (sheet.status === "submitted") return <div role="status" className="mb-5 flex gap-3 rounded-xl border border-[#d6dfed] bg-[#edf1f8] p-4 text-sm text-[#28497b]"><Lock size={18} className="mt-0.5 shrink-0" /><p>Submitted for review. Editing is locked until the department approves, rejects or reopens these results.</p></div>;
  if (sheet.status === "approved") return <div role="status" className="mb-5 flex gap-3 rounded-xl border border-[#cfe3d6] bg-[#f0f8f3] p-4 text-sm text-[#25623c]"><Lock size={18} className="mt-0.5 shrink-0" /><p>Approved by the department. These results are locked and visible to the students.</p></div>;
  if (editableStatus && !sheet.canEdit) return <div role="status" className="mb-5 flex gap-3 rounded-xl border border-[#e9ebee] bg-white p-4 text-sm text-[#8a909a]"><Lock size={18} className="mt-0.5 shrink-0" /><p>Scores cannot be entered now: {sheet.courseCode} runs in the {sheet.semester} semester, which is not the current semester.</p></div>;
  return <div role="status" className="mb-5 rounded-xl border border-[#e9ebee] bg-white p-4 text-sm text-[#8a909a]">Draft. Save as often as you like; the department only sees the results after you submit them.</div>;
}

export default function ResultEntry() {
  const [params, setParams] = useSearchParams();
  const courses = useAsyncData(() => api.lecturer.courses(), []);
  const list = courses.data?.data ?? [];
  const wanted = params.get("course");
  const courseId = list.some((course) => course.id === wanted) ? (wanted as string) : list[0]?.id ?? "";

  const loaded = useAsyncData<ClassSheet | null>(() => (courseId ? api.lecturer.students(courseId) : Promise.resolve(null)), [courseId]);
  const [sheet, setSheet] = useState<ClassSheet | null>(null);
  const [draft, setDraft] = useState<Draft>({});
  const [rowErrors, setRowErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState<"save" | "submit" | null>(null);
  const [confirming, setConfirming] = useState(false);

  useEffect(() => {
    setSheet(loaded.data);
    setDraft(loaded.data ? toDraft(loaded.data) : {});
    setRowErrors({});
  }, [loaded.data]);

  if (courses.error) return <AdminPage><ErrorState error={courses.error} onRetry={courses.reload} /></AdminPage>;
  if (!courses.data) return <AdminPage><LoadingBlock /></AdminPage>;

  const picker = <select aria-label="Choose a course" className={`${inputClass} w-auto min-w-[260px]`} value={courseId} onChange={(event) => setParams({ course: event.target.value })}>
    {list.map((course) => <option key={course.id} value={course.id}>{course.code} · {course.title}</option>)}
  </select>;

  if (list.length === 0) return <AdminPage>
    <PageHeader eyebrow="Results" title="Result entry" description="Enter CA and exam scores for your courses." />
    <p className="rounded-xl border border-[#e9ebee] bg-white p-10 text-center text-sm text-[#8a909a]">No courses are assigned to you in the current session.</p>
  </AdminPage>;

  const ready = sheet && sheet.courseId === courseId && !loaded.loading;
  if (loaded.error && !ready) return <AdminPage><PageHeader eyebrow="Results" title="Result entry" actions={picker} /><ErrorState error={loaded.error} onRetry={loaded.reload} /></AdminPage>;
  if (!ready || !sheet) return <AdminPage><PageHeader eyebrow="Results" title="Result entry" actions={picker} /><LoadingBlock label="Loading the class list…" /></AdminPage>;

  const parsed = sheet.students.map((row) => {
    const text = draft[row.studentId] ?? { ca: "", exam: "" };
    return { row, ca: parseScore(text.ca, sheet.caMax), exam: parseScore(text.exam, sheet.examMax) };
  });
  const isDirty = (p: (typeof parsed)[number]) => !!p.ca.error || !!p.exam.error || p.ca.value !== p.row.caScore || p.exam.value !== p.row.examScore;
  const dirtyCount = parsed.filter(isDirty).length;
  const invalid = parsed.some((p) => p.ca.error || p.exam.error);
  const incomplete = parsed.filter((p) => p.ca.value === null || p.exam.value === null).length;
  const locked = !sheet.canEdit;

  const setCell = (row: ClassRow, field: "ca" | "exam", value: string) => {
    setDraft((previous) => ({ ...previous, [row.studentId]: { ...(previous[row.studentId] ?? { ca: "", exam: "" }), [field]: value } }));
    setRowErrors((previous) => { const next = { ...previous }; delete next[row.studentId]; return next; });
  };

  /** Shows 422 field errors under the right student, anything else as a message. */
  const handleError = (error: unknown) => {
    if (error instanceof ApiError && error.isValidation) {
      const next: Record<string, string> = {};
      Object.entries(error.fieldErrors).forEach(([key, messages]) => {
        const match = /^scores\.(\d+)\./.exec(key);
        const target = match ? sheet.students[Number(match[1])] : undefined;
        if (target && messages[0]) next[target.studentId] = messages[0];
      });
      setRowErrors(next);
    }
    toast.error(errorMessage(error));
    // A locked or changed sheet is refreshed so the screen shows the real state.
    if (error instanceof ApiError && (error.isForbidden || (error.isValidation && Object.keys(error.fieldErrors).length === 0))) loaded.reload();
  };

  const persist = async (): Promise<boolean> => {
    const scores = parsed.map((p) => ({ studentId: p.row.studentId, caScore: p.ca.value, examScore: p.exam.value }));
    try {
      const next = await api.lecturer.saveResults(sheet.courseId, scores);
      setSheet(next);
      setDraft(toDraft(next));
      setRowErrors({});
      return true;
    } catch (error) {
      handleError(error);
      return false;
    }
  };

  const saveDraft = async () => {
    setBusy("save");
    if (await persist()) toast.success("Draft saved.");
    setBusy(null);
  };

  const submit = async () => {
    setBusy("submit");
    try {
      if (dirtyCount > 0 && !(await persist())) { setConfirming(false); return; }
      const next = await api.lecturer.submitResults(sheet.courseId);
      setSheet(next);
      setDraft(toDraft(next));
      setConfirming(false);
      toast.success("Results submitted for review.");
      courses.reload();
    } catch (error) {
      setConfirming(false);
      handleError(error);
    } finally {
      setBusy(null);
    }
  };

  const cellClass = (error?: string) => `${inputClass} w-24 py-2 text-right tabular-nums ${error ? "border-[#d95c5c] focus:border-[#d95c5c] focus:ring-[#f6d5d5]" : ""} disabled:bg-[#f6f7f9] disabled:text-[#8a909a]`;

  return <AdminPage>
    <PageHeader
      eyebrow={`${sheet.sessionName} · ${sheet.semester} semester`}
      title={<span className="flex flex-wrap items-center gap-3">{sheet.courseCode} <StatusBadge value={sheet.status} /></span>}
      description={`${sheet.courseTitle} · ${sheet.units} units · CA out of ${sheet.caMax}, exam out of ${sheet.examMax}`}
      actions={picker}
    />
    <Banner sheet={sheet} />

    <section className="overflow-hidden rounded-xl border border-[#e9ebee] bg-white">
      <div className="flex flex-wrap items-center justify-between gap-3 p-5">
        <div>
          <h2 className="text-base font-bold tracking-tight">Class list</h2>
          <p className="mt-1 text-xs text-[#9aa1ab]">
            {sheet.students.length} {sheet.students.length === 1 ? "student" : "students"} · {sheet.students.length - incomplete} complete
            {dirtyCount > 0 && <span className="ml-2 font-semibold text-[#ad771b]">· {dirtyCount} unsaved</span>}
          </p>
        </div>
        {!locked && <div className="flex flex-wrap gap-2">
          <button className={buttonClass} disabled={busy !== null || invalid || dirtyCount === 0} onClick={saveDraft}><Save size={15} /> {busy === "save" ? "Saving…" : "Save draft"}</button>
          <button className={primaryClass} disabled={busy !== null || invalid || incomplete > 0 || sheet.students.length === 0} onClick={() => setConfirming(true)} title={incomplete > 0 ? "Enter both scores for every student first" : undefined}><Send size={15} /> Submit for review</button>
        </div>}
      </div>

      <div className="overflow-x-auto">
        <table className="w-full min-w-[760px] text-left">
          <thead className="bg-[#f8f9fa] text-[10px] font-semibold uppercase tracking-[.09em] text-[#9ca2ab]">
            <tr>
              <th className="px-5 py-3.5">Student</th>
              <th className="px-5 py-3.5 text-right">CA (/{sheet.caMax})</th>
              <th className="px-5 py-3.5 text-right">Exam (/{sheet.examMax})</th>
              <th className="px-5 py-3.5 text-right">Total</th>
              <th className="px-5 py-3.5">Grade</th>
              <th className="px-5 py-3.5 text-right">Point</th>
            </tr>
          </thead>
          <tbody>
            {parsed.map((p) => {
              const total = p.ca.value !== null && p.exam.value !== null ? Math.round((p.ca.value + p.exam.value) * 100) / 100 : null;
              const dirty = isDirty(p);
              const serverError = rowErrors[p.row.studentId];
              return <tr key={p.row.studentId} className="border-t border-[#f1f2f4] align-top text-xs hover:bg-[#fbfbfc]">
                <td className="px-5 py-3"><div className="font-semibold text-[#343c48]">{p.row.studentName}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{p.row.matricNo} · {p.row.level} level</div></td>
                <td className="px-5 py-3 text-right">
                  <input inputMode="decimal" autoComplete="off" aria-label={`CA score for ${p.row.studentName}`} aria-invalid={!!(p.ca.error || serverError)} disabled={locked || busy !== null} className={cellClass(p.ca.error || serverError)} value={draft[p.row.studentId]?.ca ?? ""} onChange={(event) => setCell(p.row, "ca", event.target.value)} />
                  {p.ca.error && <div role="alert" className="mt-1 text-[11px] text-[#c0392b]">{p.ca.error}</div>}
                </td>
                <td className="px-5 py-3 text-right">
                  <input inputMode="decimal" autoComplete="off" aria-label={`Exam score for ${p.row.studentName}`} aria-invalid={!!(p.exam.error || serverError)} disabled={locked || busy !== null} className={cellClass(p.exam.error || serverError)} value={draft[p.row.studentId]?.exam ?? ""} onChange={(event) => setCell(p.row, "exam", event.target.value)} />
                  {p.exam.error && <div role="alert" className="mt-1 text-[11px] text-[#c0392b]">{p.exam.error}</div>}
                </td>
                <td className="px-5 py-3 text-right text-sm font-semibold tabular-nums text-[#343c48]">{total === null ? "—" : total}{serverError && <div role="alert" className="mt-1 text-[11px] font-normal text-[#c0392b]">{serverError}</div>}</td>
                <td className="px-5 py-3">{dirty ? <span className="text-[11px] font-semibold text-[#ad771b]">unsaved</span> : <span className="text-sm font-bold text-[#254b84]">{p.row.grade ?? "—"}</span>}</td>
                <td className="px-5 py-3 text-right tabular-nums">{dirty ? "" : p.row.gradePoint ?? "—"}</td>
              </tr>;
            })}
          </tbody>
        </table>
        {sheet.students.length === 0 && <div className="px-5 py-14 text-center text-sm text-[#959ca6]">No students have registered for this course in this semester yet.</div>}
      </div>
      <div className="border-t border-[#f0f1f3] px-5 py-3 text-[11px] text-[#989ea7]">
        Grades and grade points are calculated by the server from the grading scale when you save. Leave a score empty if it is not available yet.
      </div>
    </section>

    {confirming && <ConfirmDialog
      title={`Submit ${sheet.courseCode} for review?`}
      message={`You are submitting the results of ${sheet.students.length} students to the department admin. You will not be able to edit them unless they are rejected or reopened.${dirtyCount > 0 ? " Your unsaved changes will be saved first." : ""}`}
      confirmLabel="Submit results"
      busy={busy === "submit"}
      onConfirm={submit}
      onCancel={() => setConfirming(false)}
    />}
  </AdminPage>;
}
