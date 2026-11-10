import { useState, type FormEvent } from "react";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { api, ApiError, type AdminResource } from "../../lib/api";
import { useAsyncData, useDebouncedValue } from "../../hooks/useAsyncData";
import type { Paginated } from "../../types/portal";
import { DataTable, type Column } from "./DataTable";
import { AdminPage, ConfirmDialog, ErrorState, Field, Modal, PageHeader, SearchInput, buttonClass, errorMessage, inputClass, primaryClass } from "./ui";

export interface FieldDef {
  key: string;
  label: string;
  type?: "text" | "number" | "select";
  /** Send a select's value as a number (e.g. level 100, 200). */
  numeric?: boolean;
  required?: boolean;
  options?: { value: string; label: string }[];
}

interface CrudPageProps<T extends { id: string }> {
  resource: AdminResource;
  eyebrow: string;
  title: string;
  singular: string;
  description: string;
  columns: Column<T>[];
  fields: FieldDef[];
}

const asRecord = (value: unknown) => value as Record<string, unknown>;

/** A list page with search, pagination, add, edit and delete for one admin resource. */
export function CrudPage<R extends { id: string }>({ resource, eyebrow, title, singular, description, columns, fields }: CrudPageProps<R>) {
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const query = useDebouncedValue(search);
  const { data, loading, error, reload } = useAsyncData(
    () => api.admin.list(resource, { page, search: query }) as unknown as Promise<Paginated<R>>,
    [resource, page, query],
  );

  const [dialog, setDialog] = useState<{ row: R | null } | null>(null);
  const [form, setForm] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [removing, setRemoving] = useState<R | null>(null);

  const needle = query.trim().toLowerCase();
  // The server filters by `search`; this also filters the loaded page so the mock behaves the same.
  const rows = (data?.data ?? []).filter((row) => !needle || Object.values(asRecord(row)).some((value) => (typeof value === "string" || typeof value === "number") && String(value).toLowerCase().includes(needle)));
  const labelOf = (row: R) => String(asRecord(row)[fields[0].key] ?? singular);

  function openForm(row: R | null) {
    setForm(Object.fromEntries(fields.map((field) => [field.key, row ? String(asRecord(row)[field.key] ?? "") : ""])));
    setFormError("");
    setFieldErrors({});
    setDialog({ row });
  }

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (!dialog) return;
    const body: Record<string, unknown> = {};
    fields.forEach((field) => {
      const value = form[field.key] ?? "";
      if (field.type === "number" || field.numeric) body[field.key] = value === "" ? undefined : Number(value);
      else body[field.key] = value === "" && !field.required ? null : value.trim();
    });
    setSaving(true);
    setFormError("");
    setFieldErrors({});
    try {
      if (dialog.row) await api.admin.update(resource, dialog.row.id, body as never);
      else await api.admin.create(resource, body as never);
      toast.success(`${singular[0].toUpperCase()}${singular.slice(1)} ${dialog.row ? "updated" : "added"}.`);
      setDialog(null);
      reload();
    } catch (err) {
      if (err instanceof ApiError && err.isValidation) setFieldErrors(err.fieldErrors);
      setFormError(errorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  async function confirmRemove() {
    if (!removing) return;
    setSaving(true);
    try {
      await api.admin.remove(resource, removing.id);
      toast.success(`${labelOf(removing)} deleted.`);
      setRemoving(null);
      reload();
    } catch (err) {
      toast.error(errorMessage(err));
      setRemoving(null);
    } finally {
      setSaving(false);
    }
  }

  const allColumns: Column<R>[] = [...columns, {
    key: "actions", header: "Actions", className: "w-28",
    render: (row) => <div className="flex gap-1">
      <button onClick={() => openForm(row)} aria-label={`Edit ${labelOf(row)}`} className="rounded-md p-1.5 text-[#9198a2] hover:bg-[#eff1f5]"><Pencil size={16} /></button>
      <button onClick={() => setRemoving(row)} aria-label={`Delete ${labelOf(row)}`} className="rounded-md p-1.5 text-[#9198a2] hover:bg-[#fdeaea] hover:text-[#b23b3b]"><Trash2 size={16} /></button>
    </div>,
  }];

  return <AdminPage>
    <PageHeader eyebrow={eyebrow} title={title} description={description} actions={<button className={primaryClass} onClick={() => openForm(null)}><Plus size={16} /> Add {singular}</button>} />
    {error ? <ErrorState error={error} onRetry={reload} /> : <DataTable
      title={`All ${title.toLowerCase()}`}
      rows={rows}
      columns={allColumns}
      rowKey={(row) => row.id}
      loading={loading && !data}
      emptyMessage={needle ? "No records match your search." : `No ${title.toLowerCase()} yet.`}
      toolbar={<SearchInput label={`Search ${title}`} value={search} onChange={(value) => { setSearch(value); setPage(1); }} />}
      page={data?.meta.current_page ?? page}
      lastPage={data?.meta.last_page ?? 1}
      total={data?.meta.total}
      onPageChange={setPage}
    />}

    {dialog && <Modal title={`${dialog.row ? "Edit" : "Add"} ${singular}`} description="Fields marked * are required." onClose={() => setDialog(null)}>
      <form className="mt-6 space-y-4" onSubmit={submit}>
        {formError && <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{formError}</p>}
        {fields.map((field) => <Field key={field.key} label={field.label} required={field.required} error={fieldErrors[field.key]?.[0]}>
          {field.type === "select"
            ? <select required={field.required} className={`${inputClass} mt-1.5`} value={form[field.key] ?? ""} onChange={(e) => setForm({ ...form, [field.key]: e.target.value })}>
                <option value="">Select…</option>
                {(field.options ?? []).map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
              </select>
            : <input required={field.required} type={field.type === "number" ? "number" : "text"} min={field.type === "number" ? 1 : undefined} className={`${inputClass} mt-1.5`} value={form[field.key] ?? ""} onChange={(e) => setForm({ ...form, [field.key]: e.target.value })} />}
        </Field>)}
        <div className="flex justify-end gap-2 pt-2">
          <button type="button" className={buttonClass} onClick={() => setDialog(null)}>Cancel</button>
          <button type="submit" className={primaryClass} disabled={saving}>{saving ? "Saving…" : `Save ${singular}`}</button>
        </div>
      </form>
    </Modal>}

    {removing && <ConfirmDialog title={`Delete ${singular}?`} message={`"${labelOf(removing)}" will be removed. This can't be undone.`} confirmLabel="Delete" danger busy={saving} onConfirm={confirmRemove} onCancel={() => setRemoving(null)} />}
  </AdminPage>;
}
