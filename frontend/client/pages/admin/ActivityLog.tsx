import { useState } from "react";
import { api } from "../../lib/api";
import { roleName } from "../../lib/navigation";
import { useAsyncData, useDebouncedValue } from "../../hooks/useAsyncData";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ErrorState, PageHeader, formatDateTime, inputClass } from "../../components/admin/ui";

export default function ActivityLog() {
  const [user, setUser] = useState("");
  const [action, setAction] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [page, setPage] = useState(1);
  const userQuery = useDebouncedValue(user);
  const actionQuery = useDebouncedValue(action);
  const { data, loading, error, reload } = useAsyncData(
    () => api.admin.activityLogs({ page, user: userQuery, action: actionQuery, from, to }),
    [page, userQuery, actionQuery, from, to],
  );

  // The server filters; this also filters the loaded page so the mock behaves the same.
  const userNeedle = userQuery.trim().toLowerCase();
  const actionNeedle = actionQuery.trim().toLowerCase();
  const start = from ? new Date(`${from}T00:00:00`).getTime() : null;
  const end = to ? new Date(`${to}T23:59:59`).getTime() : null;
  const rows = (data?.data ?? []).filter((row) => {
    const time = new Date(row.timestamp).getTime();
    return (!userNeedle || row.userName.toLowerCase().includes(userNeedle)) &&
      (!actionNeedle || row.action.toLowerCase().includes(actionNeedle)) &&
      (start === null || Number.isNaN(time) || time >= start) &&
      (end === null || Number.isNaN(time) || time <= end);
  });
  const reset = (setter: (value: string) => void) => (value: string) => { setter(value); setPage(1); };

  return <AdminPage>
    <PageHeader eyebrow="University management" title="Activity log" description="A record of submissions, approvals, rejections, reopenings and changes made across the portal." />
    {error ? <ErrorState error={error} onRetry={reload} /> : <DataTable
      title="Recent activity"
      rows={rows}
      rowKey={(row) => row.id}
      loading={loading}
      emptyMessage="No activity matches your filters."
      toolbar={<>
        <input aria-label="Filter by user" placeholder="User" className={`${inputClass} w-auto min-w-[140px]`} value={user} onChange={(e) => reset(setUser)(e.target.value)} />
        <input aria-label="Filter by action" placeholder="Action" className={`${inputClass} w-auto min-w-[140px]`} value={action} onChange={(e) => reset(setAction)(e.target.value)} />
        <input aria-label="From date" type="date" className={`${inputClass} w-auto`} value={from} onChange={(e) => reset(setFrom)(e.target.value)} />
        <input aria-label="To date" type="date" className={`${inputClass} w-auto`} value={to} onChange={(e) => reset(setTo)(e.target.value)} />
      </>}
      page={data?.meta.current_page ?? page}
      lastPage={data?.meta.last_page ?? 1}
      total={data?.meta.total}
      onPageChange={setPage}
      columns={[
        { key: "time", header: "Timestamp", className: "whitespace-nowrap", render: (row) => formatDateTime(row.timestamp) },
        { key: "user", header: "User", render: (row) => <span className="font-semibold text-[#343c48]">{row.userName}</span> },
        { key: "role", header: "Role", render: (row) => roleName[row.role] },
        { key: "action", header: "Action", render: (row) => row.action },
        { key: "entity", header: "Entity", render: (row) => row.entity },
        { key: "details", header: "Details", className: "min-w-[220px]", render: (row) => row.details },
        { key: "ip", header: "IP", className: "whitespace-nowrap", render: (row) => row.ip },
      ]}
    />}
  </AdminPage>;
}
