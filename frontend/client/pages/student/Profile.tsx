import { useEffect, useState, type FormEvent } from "react";
import { toast } from "sonner";
import { Lock } from "lucide-react";
import { ApiError, api } from "../../lib/api";
import { useAsyncData } from "../../hooks/useAsyncData";
import { AdminPage, ErrorState, Field, LoadingBlock, PageHeader, StatusBadge, errorMessage, inputClass, primaryClass } from "../../components/admin/ui";

export default function Profile() {
  const { data, error, reload } = useAsyncData(() => api.student.profile(), []);
  const [form, setForm] = useState({ phone: "", address: "", dateOfBirth: "" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (data) setForm({ phone: data.phone ?? "", address: data.address ?? "", dateOfBirth: data.dateOfBirth ?? "" });
  }, [data]);

  if (error) return <AdminPage><ErrorState error={error} onRetry={reload} /></AdminPage>;
  if (!data) return <AdminPage><LoadingBlock /></AdminPage>;

  const dirty = form.phone !== (data.phone ?? "") || form.address !== (data.address ?? "") || form.dateOfBirth !== (data.dateOfBirth ?? "");

  async function save(event: FormEvent) {
    event.preventDefault();
    setSaving(true);
    setErrors({});
    try {
      await api.student.updateProfile({ phone: form.phone.trim() || null, address: form.address.trim() || null, dateOfBirth: form.dateOfBirth || null });
      toast.success("Profile updated");
      reload();
    } catch (err) {
      if (err instanceof ApiError && err.isValidation) {
        setErrors(Object.fromEntries(Object.entries(err.fieldErrors).map(([key, messages]) => [key, messages[0]])));
      } else toast.error(errorMessage(err));
    } finally { setSaving(false); }
  }

  const locked: [string, string | number][] = [["Full name", data.name], ["Matric number", data.matricNo], ["Email", data.email], ["Faculty", data.facultyName], ["Department", data.departmentName], ["Program", data.programName], ["Level", data.level]];

  return <AdminPage>
    <PageHeader eyebrow="Account" title="My profile" description="You can update your phone, address and date of birth. Academic details are managed by the department." />
    <div className="grid grid-cols-[minmax(0,1fr)] gap-5 xl:grid-cols-2">
      <section className="rounded-xl border border-[#e9ebee] bg-white p-5 sm:p-6">
        <div className="flex items-center gap-2"><h2 className="text-base font-bold tracking-tight">Academic details</h2><Lock size={14} className="text-[#a0a6ae]" /></div>
        <dl className="mt-4 space-y-3 text-sm">
          {locked.map(([label, value]) => <div key={label} className="flex justify-between gap-4 border-b border-[#f1f2f4] pb-3"><dt className="text-[#8a909a]">{label}</dt><dd className="text-right font-semibold text-[#343c48]">{value}</dd></div>)}
          <div className="flex justify-between gap-4"><dt className="text-[#8a909a]">Status</dt><dd><StatusBadge value={data.status} /></dd></div>
        </dl>
      </section>
      <form onSubmit={save} className="rounded-xl border border-[#e9ebee] bg-white p-5 sm:p-6" noValidate>
        <h2 className="text-base font-bold tracking-tight">Contact details</h2>
        <div className="mt-4 space-y-4">
          <Field label="Phone" error={errors.phone}><input className={`${inputClass} mt-1`} value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="+234 801 234 5678" inputMode="tel" /></Field>
          <Field label="Address" error={errors.address}><textarea className={`${inputClass} mt-1 min-h-[84px]`} value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} maxLength={255} /></Field>
          <Field label="Date of birth" error={errors.dateOfBirth}><input type="date" className={`${inputClass} mt-1`} value={form.dateOfBirth} onChange={(e) => setForm({ ...form, dateOfBirth: e.target.value })} /></Field>
        </div>
        <div className="mt-6 flex justify-end"><button type="submit" className={primaryClass} disabled={!dirty || saving}>{saving ? "Saving…" : "Save changes"}</button></div>
      </form>
    </div>
  </AdminPage>;
}
