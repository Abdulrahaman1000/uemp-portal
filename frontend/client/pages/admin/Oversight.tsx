import { useState } from "react";
import { api } from "../../lib/api";
import { roleName } from "../../lib/navigation";
import { useAsyncData } from "../../hooks/useAsyncData";
import type { ResultStatus, ResultSubmission } from "../../types/portal";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ErrorState, Modal, PageHeader, StatusBadge, buttonClass, formatDate, formatDateTime, inputClass, titleCase } from "../../components/admin/ui";

const STATUSES: ResultStatus[] = ["draft", "submitted", "approved", "rejected", "reopened"];

export default function Oversight() {
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const { data, loading, error, reload } = useAsyncData(() => api.admin.submissions({ page, status }), [page, status]);
  const [viewing, setViewing] = useState<ResultSubmission | null>(null);
  // The server filters by status; this also filters the loaded page so the mock behaves the same.
  const rows = (data?.data ?? []).filter((row) => !status || row.status === status);

  return <AdminPage>
    <PageHeader eyebrow="University management" title="Result oversight" description="Monitor result submissions from every department. Approval is done by each department's admin." />
    {error ? <ErrorState error={error} onRetry={reload} /> : <DataTable
      title="Result submissions"
      rows={rows}
      rowKey={(row) => row.id}
      loading={loading && !data}
      emptyMessage="No result submissions match this status."
      toolbar={<select aria-label="Filter by status" className={`${inputClass} w-auto min-w-[150px]`} value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }}>
        <option value="">All statuses</option>{STATUSES.map((value) => <option key={value} value={value}>{titleCase(value)}</option>)}
      </select>}
      page={data?.meta.current_page ?? page}
      lastPage={data?.meta.last_page ?? 1}
      total={data?.meta.total}
      onPageChange={setPage}
      columns={[
        { key: "course", header: "Course", render: (row) => <div><div className="font-semibold text-[#343c48]">{row.courseCode}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{row.courseTitle}</div></div> },
        { key: "department", header: "Department", render: (row) => row.departmentName },
        { key: "lecturer", header: "Lecturer", render: (row) => row.lecturerName },
        { key: "period", header: "Session", className: "whitespace-nowrap", render: (row) => `${row.sessionName} · ${row.semester}` },
        { key: "students", header: "Students", render: (row) => row.studentCount },
        { key: "submitted", header: "Submitted", className: "whitespace-nowrap", render: (row) => formatDate(row.submittedAt) },
        { key: "status", header: "Status", render: (row) => <StatusBadge value={row.status} /> },
        { key: "actions", header: "Actions", render: (row) => <button className={buttonClass} onClick={() => setViewing(row)}>Audit trail</button> },
      ]}
    />}

    {viewing && <Modal title={`${viewing.courseCode} · audit trail`} description={`${viewing.courseTitle} · ${viewing.departmentName}`} onClose={() => setViewing(null)}>
      <div className="mt-4 flex items-center gap-3 text-xs text-[#8a909a]"><StatusBadge value={viewing.status} /><span>{viewing.lecturerName} · {viewing.studentCount} students</span></div>
      {viewing.feedback && <p className="mt-4 rounded-lg bg-[#fff4de] p-3 text-xs text-[#8a5e12]"><span className="font-semibold">Feedback: </span>{viewing.feedback}</p>}
      <ol className="mt-5 space-y-4 border-l border-[#e9ebee] pl-5">
        {viewing.audit.map((entry) => <li key={entry.id} className="relative text-xs">
          <span className="absolute -left-[26px] top-1 h-2 w-2 rounded-full bg-[#4269a3]" />
          <div className="font-semibold text-[#343c48]">{entry.action}</div>
          <div className="mt-0.5 text-[#8a909a]">{entry.actorName} · {roleName[entry.actorRole]} · {formatDateTime(entry.at)}</div>
          {entry.note && <div className="mt-1 text-[#59616d]">{entry.note}</div>}
        </li>)}
        {viewing.audit.length === 0 && <li className="text-xs text-[#959ca6]">No activity recorded yet.</li>}
      </ol>
      <div className="mt-6 flex justify-end"><button className={buttonClass} onClick={() => setViewing(null)}>Close</button></div>
    </Modal>}
  </AdminPage>;
}
