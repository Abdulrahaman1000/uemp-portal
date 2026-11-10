import { useState } from "react";
import { toast } from "sonner";
import { api } from "../../lib/api";
import { useAuth } from "../../lib/auth";
import { roleName } from "../../lib/navigation";
import { useAsyncData, useDebouncedValue } from "../../hooks/useAsyncData";
import type { User, UserRole, UserStatus } from "../../types/portal";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ErrorState, Field, Initials, Modal, PageHeader, SearchInput, StatusBadge, buttonClass, errorMessage, formatDate, inputClass, primaryClass } from "../../components/admin/ui";

const ROLES: UserRole[] = ["super_admin", "dept_admin", "lecturer", "student"];
const STATUSES: UserStatus[] = ["active", "inactive", "suspended"];

export default function Users() {
  const { user: me } = useAuth();
  const [search, setSearch] = useState("");
  const [role, setRole] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const query = useDebouncedValue(search);
  const { data, loading, error, reload } = useAsyncData(() => api.admin.users({ page, search: query, role, status }), [page, query, role, status]);

  const [managing, setManaging] = useState<User | null>(null);
  const [nextRole, setNextRole] = useState<UserRole>("student");
  const [nextStatus, setNextStatus] = useState<UserStatus>("active");
  const [saving, setSaving] = useState(false);

  const needle = query.trim().toLowerCase();
  // The server filters; this also filters the loaded page so the mock behaves the same.
  const rows = (data?.data ?? []).filter((row) =>
    (!needle || row.name.toLowerCase().includes(needle) || row.email.toLowerCase().includes(needle)) &&
    (!role || row.role === role) && (!status || row.status === status));

  function manage(row: User) { setManaging(row); setNextRole(row.role); setNextStatus(row.status); }
  const isSelf = managing !== null && managing.id === me?.id;

  async function save() {
    if (!managing) return;
    setSaving(true);
    try {
      if (nextRole !== managing.role) await api.admin.setUserRole(managing.id, nextRole);
      if (nextStatus !== managing.status) await api.admin.setUserStatus(managing.id, nextStatus);
      toast.success(`${managing.name} updated.`);
      setManaging(null);
      reload();
    } catch (err) {
      toast.error(errorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  const filters = <>
    <SearchInput label="Search users" value={search} onChange={(value) => { setSearch(value); setPage(1); }} />
    <select aria-label="Filter by role" className={`${inputClass} w-auto min-w-[140px]`} value={role} onChange={(e) => { setRole(e.target.value); setPage(1); }}>
      <option value="">All roles</option>{ROLES.map((value) => <option key={value} value={value}>{roleName[value]}</option>)}
    </select>
    <select aria-label="Filter by status" className={`${inputClass} w-auto min-w-[140px]`} value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }}>
      <option value="">All statuses</option>{STATUSES.map((value) => <option key={value} value={value}>{value[0].toUpperCase() + value.slice(1)}</option>)}
    </select>
  </>;

  return <AdminPage>
    <PageHeader eyebrow="University management" title="Users" description="Activate, deactivate or suspend accounts and change a user's role." />
    {error ? <ErrorState error={error} onRetry={reload} /> : <DataTable
      title="All users"
      rows={rows}
      rowKey={(row) => row.id}
      loading={loading && !data}
      emptyMessage="No users match your filters."
      toolbar={filters}
      page={data?.meta.current_page ?? page}
      lastPage={data?.meta.last_page ?? 1}
      total={data?.meta.total}
      onPageChange={setPage}
      columns={[
        { key: "user", header: "User", render: (row) => <div className="flex items-center gap-3"><Initials name={row.name} /><div><div className="font-semibold text-[#343c48]">{row.name}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{row.email}</div></div></div> },
        { key: "role", header: "Role", render: (row) => roleName[row.role] },
        { key: "department", header: "Department", render: (row) => row.department_name ?? "—" },
        { key: "status", header: "Status", render: (row) => <StatusBadge value={row.status} /> },
        { key: "active", header: "Last active", className: "whitespace-nowrap", render: (row) => formatDate(row.lastActive) },
        { key: "actions", header: "Actions", render: (row) => <button className={buttonClass} onClick={() => manage(row)}>Manage</button> },
      ]}
    />}

    {managing && <Modal title="Manage user" description={`${managing.name} · ${managing.email}`} onClose={() => setManaging(null)}>
      <div className="mt-6 space-y-4">
        {isSelf && <p className="rounded-lg bg-[#fff4de] p-3 text-xs text-[#8a5e12]">You can't change your own role or status.</p>}
        <Field label="Role"><select disabled={isSelf} className={`${inputClass} mt-1.5`} value={nextRole} onChange={(e) => setNextRole(e.target.value as UserRole)}>{ROLES.map((value) => <option key={value} value={value}>{roleName[value]}</option>)}</select></Field>
        <Field label="Status"><select disabled={isSelf} className={`${inputClass} mt-1.5`} value={nextStatus} onChange={(e) => setNextStatus(e.target.value as UserStatus)}>{STATUSES.map((value) => <option key={value} value={value}>{value[0].toUpperCase() + value.slice(1)}</option>)}</select></Field>
        <div className="flex justify-end gap-2 pt-2">
          <button className={buttonClass} onClick={() => setManaging(null)}>Cancel</button>
          <button className={primaryClass} disabled={saving || isSelf || (nextRole === managing.role && nextStatus === managing.status)} onClick={save}>{saving ? "Saving…" : "Save changes"}</button>
        </div>
      </div>
    </Modal>}
  </AdminPage>;
}
