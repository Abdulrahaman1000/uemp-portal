import { useState } from "react";
import { toast } from "sonner";
import { ApiError, api } from "../../lib/api";
import type { FieldErrors } from "../../lib/api";
import { adminUsers } from "../../lib/adminUsers";
import type { NewUserBody } from "../../lib/adminUsers";
import { useAuth } from "../../lib/auth";
import { roleName } from "../../lib/navigation";
import { useAsyncData, useDebouncedValue } from "../../hooks/useAsyncData";
import type { User, UserRole, UserStatus } from "../../types/portal";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ErrorState, Field, Initials, Modal, PageHeader, SearchInput, StatusBadge, buttonClass, errorMessage, formatDate, inputClass, primaryClass } from "../../components/admin/ui";

const ROLES: UserRole[] = ["super_admin", "dept_admin", "lecturer", "student"];
const STATUSES: UserStatus[] = ["active", "inactive", "suspended"];
/** Student accounts are created by the department admin (they need a program and a matric number). */
const CREATABLE_ROLES: NewUserBody["role"][] = ["lecturer", "dept_admin", "super_admin"];

interface CreateForm {
  name: string;
  email: string;
  password: string;
  role: NewUserBody["role"];
  departmentId: string;
  specialization: string;
}

const EMPTY_FORM: CreateForm = { name: "", email: "", password: "", role: "lecturer", departmentId: "", specialization: "" };

function FieldError({ messages }: { messages?: string[] }) {
  return messages && messages.length > 0 ? <p className="mt-1 text-xs text-[#c0392b]">{messages[0]}</p> : null;
}

export default function Users() {
  const { user: me } = useAuth();
  const [search, setSearch] = useState("");
  const [role, setRole] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const query = useDebouncedValue(search);
  const { data, loading, error, reload } = useAsyncData(() => api.admin.users({ page, search: query, role, status }), [page, query, role, status]);
  const departments = useAsyncData(async () => (await api.admin.list("departments", { per_page: 100 })).data, []);

  const [managing, setManaging] = useState<User | null>(null);
  const [nextRole, setNextRole] = useState<UserRole>("student");
  const [nextStatus, setNextStatus] = useState<UserStatus>("active");
  const [nextDepartment, setNextDepartment] = useState("");
  const [saving, setSaving] = useState(false);

  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState<CreateForm>(EMPTY_FORM);
  const [formErrors, setFormErrors] = useState<FieldErrors>({});

  const needle = query.trim().toLowerCase();
  // The server filters; this also filters the loaded page so the mock behaves the same.
  const rows = (data?.data ?? []).filter((row) =>
    (!needle || row.name.toLowerCase().includes(needle) || row.email.toLowerCase().includes(needle)) &&
    (!role || row.role === role) && (!status || row.status === status));

  function manage(row: User) { setManaging(row); setNextRole(row.role); setNextStatus(row.status); setNextDepartment(row.department_id ?? ""); }
  const isSelf = managing !== null && managing.id === me?.id;
  // Only lecturers and department admins belong to a department that can be changed.
  const canMoveDepartment = managing !== null && (managing.role === "lecturer" || managing.role === "dept_admin") && nextRole === managing.role;
  const departmentChanged = canMoveDepartment && nextDepartment !== "" && nextDepartment !== (managing?.department_id ?? "");

  async function save() {
    if (!managing) return;
    setSaving(true);
    try {
      if (nextRole !== managing.role) await api.admin.setUserRole(managing.id, nextRole);
      if (nextStatus !== managing.status) await api.admin.setUserStatus(managing.id, nextStatus);
      if (departmentChanged) await adminUsers.setDepartment(managing.id, Number(nextDepartment));
      toast.success(`${managing.name} updated.`);
      setManaging(null);
      reload();
    } catch (err) {
      toast.error(errorMessage(err));
      reload();
    } finally {
      setSaving(false);
    }
  }

  function openCreate() { setForm(EMPTY_FORM); setFormErrors({}); setCreating(true); }
  function setField<K extends keyof CreateForm>(key: K, value: CreateForm[K]) { setForm((current) => ({ ...current, [key]: value })); }

  async function create() {
    setSaving(true);
    setFormErrors({});
    try {
      await adminUsers.create({
        name: form.name,
        email: form.email,
        password: form.password,
        role: form.role,
        departmentId: form.role === "super_admin" ? null : Number(form.departmentId) || null,
        specialization: form.role === "lecturer" ? form.specialization || null : null,
      });
      toast.success(`${form.name} was created.`);
      setCreating(false);
      reload();
    } catch (err) {
      if (err instanceof ApiError && err.isValidation) setFormErrors(err.fieldErrors);
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
    <PageHeader
      eyebrow="University management"
      title="Users"
      description="Create staff accounts, assign users to departments, change roles and activate, deactivate or suspend accounts."
      actions={<button className={primaryClass} onClick={openCreate}>Create user</button>}
    />
    {error ? <ErrorState error={error} onRetry={reload} /> : <DataTable
      title="All users"
      rows={rows}
      rowKey={(row) => row.id}
      loading={loading}
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
        {canMoveDepartment && <Field label="Department">
          <select disabled={isSelf} className={`${inputClass} mt-1.5`} value={nextDepartment} onChange={(e) => setNextDepartment(e.target.value)}>
            {nextDepartment === "" && <option value="">Choose a department</option>}
            {(departments.data ?? []).map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}
          </select>
          {managing.role === "lecturer" && <p className="mt-1.5 text-[11px] text-[#9aa0a9]">A lecturer with courses in the current session or unapproved results cannot be moved yet.</p>}
        </Field>}
        <Field label="Status"><select disabled={isSelf} className={`${inputClass} mt-1.5`} value={nextStatus} onChange={(e) => setNextStatus(e.target.value as UserStatus)}>{STATUSES.map((value) => <option key={value} value={value}>{value[0].toUpperCase() + value.slice(1)}</option>)}</select></Field>
        <div className="flex justify-end gap-2 pt-2">
          <button className={buttonClass} onClick={() => setManaging(null)}>Cancel</button>
          <button className={primaryClass} disabled={saving || isSelf || (nextRole === managing.role && nextStatus === managing.status && !departmentChanged)} onClick={save}>{saving ? "Saving…" : "Save changes"}</button>
        </div>
      </div>
    </Modal>}

    {creating && <Modal title="Create user" description="Staff accounts only. Student accounts are created by the department admin." onClose={() => setCreating(false)}>
      <div className="mt-6 space-y-4">
        <Field label="Full name"><input className={`${inputClass} mt-1.5`} value={form.name} onChange={(e) => setField("name", e.target.value)} /><FieldError messages={formErrors.name} /></Field>
        <Field label="Email"><input type="email" className={`${inputClass} mt-1.5`} value={form.email} onChange={(e) => setField("email", e.target.value)} /><FieldError messages={formErrors.email} /></Field>
        <Field label="Temporary password"><input type="password" autoComplete="new-password" className={`${inputClass} mt-1.5`} value={form.password} onChange={(e) => setField("password", e.target.value)} /><FieldError messages={formErrors.password} /></Field>
        <Field label="Role">
          <select className={`${inputClass} mt-1.5`} value={form.role} onChange={(e) => setField("role", e.target.value as NewUserBody["role"])}>
            {CREATABLE_ROLES.map((value) => <option key={value} value={value}>{roleName[value]}</option>)}
          </select>
          <FieldError messages={formErrors.role} />
        </Field>
        {form.role !== "super_admin" && <Field label="Department">
          <select className={`${inputClass} mt-1.5`} value={form.departmentId} onChange={(e) => setField("departmentId", e.target.value)}>
            <option value="">Choose a department</option>
            {(departments.data ?? []).map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}
          </select>
          <FieldError messages={formErrors.departmentId} />
        </Field>}
        {form.role === "lecturer" && <Field label="Specialization (optional)"><input className={`${inputClass} mt-1.5`} value={form.specialization} onChange={(e) => setField("specialization", e.target.value)} /><FieldError messages={formErrors.specialization} /></Field>}
        <div className="flex justify-end gap-2 pt-2">
          <button className={buttonClass} onClick={() => setCreating(false)}>Cancel</button>
          <button className={primaryClass} disabled={saving} onClick={create}>{saving ? "Creating…" : "Create user"}</button>
        </div>
      </div>
    </Modal>}
  </AdminPage>;
}
