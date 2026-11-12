import { useState } from "react";
import { toast } from "sonner";
import { CheckCircle2, Eye, RotateCcw, XCircle } from "lucide-react";
import { ApiError, api } from "../../lib/api";
import { useAsyncData, useDebouncedValue } from "../../hooks/useAsyncData";
import type { ResultStatus, ResultSubmission } from "../../types/portal";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ErrorState, Field, LoadingBlock, Modal, PageHeader, SearchInput, StatusBadge, buttonClass, dangerClass, errorMessage, formatDateTime, inputClass, primaryClass } from "../../components/admin/ui";

const show = (v: number | null) => (v === null ? "—" : v);
const FILTERS: (ResultStatus | "")[] = ["", "submitted", "approved", "rejected", "reopened", "draft"];

/** Review one submission: scores, audit trail and the approve / reject / reopen actions. */
function ReviewModal({ id, onClose, onChanged }: { id: string; onClose: () => void; onChanged: () => void }) {
  const submission = useAsyncData(() => api.dept.submission(id), [id]);
  const scores = useAsyncData(() => api.dept.submissionResults(id), [id]);
  const [mode, setMode] = useState<"reject" | "reopen" | null>(null);
  const [feedback, setFeedback] = useState("");
  const [fieldError, setFieldError] = useState("");
  const [busy, setBusy] = useState(false);
  const s = submission.data;

  async function run(action: "approve" | "reject" | "reopen") {
    setBusy(true); setFieldError("");
    try {
      if (action === "approve") await api.dept.approve(id);
      else if (action === "reject") await api.dept.reject(id, feedback.trim());
      else await api.dept.reopen(id, feedback.trim());
      toast.success(action === "approve" ? "Results approved. Students can now see them." : action === "reject" ? "Results returned to the lecturer." : "Results reopened for the lecturer.");
      onChanged(); onClose();
    } catch (err) {
      if (err instanceof ApiError && err.isValidation && err.fieldErrors.feedback) setFieldError(err.fieldErrors.feedback[0]);
      else { toast.error(errorMessage(err)); onChanged(); submission.reload(); setMode(null); }
    } finally { setBusy(false); }
  }

  return <Modal title={s ? `${s.courseCode} · ${s.courseTitle}` : "Result submission"} description={s ? `${s.sessionName} · ${s.semester} semester · ${s.lecturerName}` : undefined} onClose={onClose}>
    {!s ? (submission.error ? <ErrorState error={submission.error} /> : <div className="mt-5"><LoadingBlock /></div>) : <>
      <div className="mt-4 flex flex-wrap items-center gap-3 text-xs text-[#8a909a]"><StatusBadge value={s.status} /><span>{s.studentCount} students</span><span>Submitted {formatDateTime(s.submittedAt)}</span></div>
      {s.feedback && <p className="mt-3 rounded-lg bg-[#f7f8fa] p-3 text-xs text-[#59616d]"><strong>Feedback:</strong> {s.feedback}</p>}
      <h3 className="mt-5 text-xs font-bold uppercase tracking-[.1em] text-[#9199a4]">Scores</h3>
      {scores.error ? <ErrorState error={scores.error} onRetry={scores.reload} /> : !scores.data ? <div className="mt-3"><LoadingBlock /></div> : <div className="mt-2 overflow-x-auto rounded-lg border border-[#eef0f2]"><table className="w-full min-w-[520px] text-left text-xs">
        <thead className="bg-[#f8f9fa] text-[10px] uppercase tracking-[.08em] text-[#9ca2ab]"><tr>{["Matric no", "Student", "CA", "Exam", "Total", "Grade"].map((h) => <th key={h} className="px-3 py-2.5">{h}</th>)}</tr></thead>
        <tbody>{scores.data.data.map((r) => <tr key={r.id} className="border-t border-[#f1f2f4] text-[#737b87]"><td className="px-3 py-2.5">{r.matricNo}</td><td className="px-3 py-2.5 font-semibold text-[#343c48]">{r.studentName}</td><td className="px-3 py-2.5">{show(r.caScore)}</td><td className="px-3 py-2.5">{show(r.examScore)}</td><td className="px-3 py-2.5 font-semibold text-[#343c48]">{show(r.totalScore)}</td><td className="px-3 py-2.5 font-bold text-[#254b84]">{r.grade ?? "—"}</td></tr>)}</tbody>
      </table>{scores.data.data.length === 0 && <p className="p-4 text-center text-xs text-[#959ca6]">No scores recorded.</p>}</div>}
      <h3 className="mt-5 text-xs font-bold uppercase tracking-[.1em] text-[#9199a4]">Audit trail</h3>
      <ol className="mt-2 space-y-2 text-xs">{s.audit.map((a) => <li key={a.id} className="rounded-lg border border-[#eef0f2] p-2.5"><span className="font-semibold text-[#343c48]">{a.action.replace(/_/g, " ")}</span> <span className="text-[#8a909a]">by {a.actorName} · {formatDateTime(a.at)}</span>{a.note && <div className="mt-1 text-[#737b87]">{a.note}</div>}</li>)}{s.audit.length === 0 && <li className="text-[#959ca6]">No activity recorded.</li>}</ol>

      {mode && <div className="mt-5">
        <Field label={mode === "reject" ? "Feedback for the lecturer" : "Reason (optional)"} required={mode === "reject"} error={fieldError}>
          <textarea className={`${inputClass} mt-1 min-h-[84px]`} value={feedback} maxLength={1000} onChange={(e) => setFeedback(e.target.value)} placeholder={mode === "reject" ? "Explain what must be corrected" : "Why are the results reopened?"} />
        </Field>
      </div>}
      <div className="mt-6 flex flex-wrap justify-end gap-2">
        <button className={buttonClass} onClick={onClose} disabled={busy}>Close</button>
        {mode ? <>
          <button className={buttonClass} onClick={() => { setMode(null); setFieldError(""); }} disabled={busy}>Cancel</button>
          <button className={mode === "reject" ? dangerClass : primaryClass} disabled={busy || (mode === "reject" && feedback.trim().length < 3)} onClick={() => run(mode)}>{busy ? "Working…" : mode === "reject" ? "Return to lecturer" : "Reopen results"}</button>
        </> : <>
          {s.status === "submitted" && <>
            <button className={dangerClass} onClick={() => setMode("reject")}><XCircle size={15} /> Reject</button>
            <button className={primaryClass} onClick={() => run("approve")} disabled={busy}><CheckCircle2 size={15} /> Approve</button>
          </>}
          {s.status === "approved" && <button className={buttonClass} onClick={() => setMode("reopen")}><RotateCcw size={15} /> Reopen</button>}
        </>}
      </div>
    </>}
  </Modal>;
}

export default function Approvals() {
  const [status, setStatus] = useState<ResultStatus | "">("submitted");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const query = useDebouncedValue(search);
  const [open, setOpen] = useState<string | null>(null);
  const { data, loading, error, reload } = useAsyncData(() => api.dept.submissions({ page, search: query, status }), [page, query, status]);

  return <AdminPage>
    <PageHeader eyebrow="Results" title="Result approvals" description="Review the results your lecturers submitted. Approved results become visible to students; a rejection needs feedback." />
    {error ? <ErrorState error={error} onRetry={reload} /> : <DataTable<ResultSubmission>
      title="Submissions" subtitle={data ? `${data.meta.total} found` : undefined}
      rows={data?.data ?? []} rowKey={(r) => r.id} loading={loading}
      page={page} lastPage={data?.meta.last_page} total={data?.meta.total} onPageChange={setPage}
      emptyMessage="No submissions match this filter."
      toolbar={<>
        <select aria-label="Filter by status" className={`${inputClass} !w-auto`} value={status} onChange={(e) => { setStatus(e.target.value as ResultStatus | ""); setPage(1); }}>
          {FILTERS.map((f) => <option key={f} value={f}>{f ? f[0].toUpperCase() + f.slice(1) : "All statuses"}</option>)}
        </select>
        <SearchInput label="Search submissions" value={search} onChange={(v) => { setSearch(v); setPage(1); }} />
      </>}
      columns={[
        { key: "course", header: "Course", render: (r) => <div><div className="font-semibold text-[#343c48]">{r.courseCode}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{r.courseTitle}</div></div> },
        { key: "lecturer", header: "Lecturer", render: (r) => r.lecturerName },
        { key: "term", header: "Session", render: (r) => `${r.sessionName} · ${r.semester}` },
        { key: "count", header: "Students", render: (r) => r.studentCount },
        { key: "status", header: "Status", render: (r) => <StatusBadge value={r.status} /> },
        { key: "at", header: "Submitted", render: (r) => formatDateTime(r.submittedAt) },
        { key: "actions", header: "", render: (r) => <button className={buttonClass} onClick={() => setOpen(r.id)}><Eye size={15} /> Review</button> },
      ]} />}
    {open && <ReviewModal id={open} onClose={() => setOpen(null)} onChanged={reload} />}
  </AdminPage>;
}
