import { Check, Minus } from "lucide-react";
import { api } from "../../lib/api";
import { useAsyncData } from "../../hooks/useAsyncData";
import { AdminPage, ErrorState, LoadingBlock, PageHeader } from "../../components/admin/ui";

const card = "overflow-hidden rounded-xl border border-[#e9ebee] bg-white";

export default function Roles() {
  const roles = useAsyncData(() => api.admin.roles());
  const scale = useAsyncData(() => api.admin.gradingScale());
  const permissions = Array.from(new Set((roles.data ?? []).flatMap((role) => role.permissions)));

  return <AdminPage>
    <PageHeader eyebrow="University management" title="Roles & permissions" description="The four roles that control access across the portal. To change someone's role, use the Users page." />

    {roles.error ? <ErrorState error={roles.error} onRetry={roles.reload} /> : !roles.data ? <LoadingBlock /> : <>
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {roles.data.map((role) => <div key={role.role} className="rounded-xl border border-[#e9ebee] bg-white p-5">
          <div className="flex items-center justify-between"><h2 className="text-sm font-bold">{role.label}</h2><span className="rounded-full bg-[#eef3ff] px-2 py-1 text-[10px] font-bold text-[#5373c8]">{role.userCount} {role.userCount === 1 ? "user" : "users"}</span></div>
          <p className="mt-2 text-xs leading-5 text-[#8a909a]">{role.description}</p>
        </div>)}
      </div>
      <section className={`${card} mt-6`}>
        <div className="p-5"><h2 className="text-base font-bold tracking-tight">Permission matrix</h2><p className="mt-1 text-xs text-[#9aa1ab]">Read-only. Permissions are enforced by the API, not just hidden in the interface.</p></div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[640px] text-left text-xs">
            <thead className="bg-[#f8f9fa] text-[10px] font-semibold uppercase tracking-[.09em] text-[#9ca2ab]"><tr><th className="px-5 py-3.5">Permission</th>{roles.data.map((role) => <th key={role.role} className="px-5 py-3.5 text-center">{role.label}</th>)}</tr></thead>
            <tbody>{permissions.map((permission) => <tr key={permission} className="border-t border-[#f1f2f4]">
              <td className="px-5 py-3.5 font-medium text-[#343c48]">{permission}</td>
              {roles.data!.map((role) => <td key={role.role} className="px-5 py-3.5 text-center">{role.permissions.includes(permission)
                ? <Check size={16} className="inline text-[#2f6b3f]" aria-label="Allowed" />
                : <Minus size={16} className="inline text-[#c3c8cf]" aria-label="Not allowed" />}</td>)}
            </tr>)}</tbody>
          </table>
        </div>
      </section>
    </>}

    <section className={`${card} mt-6`}>
      <div className="p-5"><h2 className="text-base font-bold tracking-tight">Grading scale</h2><p className="mt-1 text-xs text-[#9aa1ab]">Read-only. This is the scale the backend uses to compute grades and GPA.</p></div>
      {scale.error ? <div className="p-5"><ErrorState error={scale.error} onRetry={scale.reload} /></div> : !scale.data ? <div className="p-5"><LoadingBlock /></div> : <div className="grid gap-6 border-t border-[#f1f2f4] p-5 lg:grid-cols-[1fr_1.4fr]">
        <div className="space-y-3 text-sm">
          <div className="flex justify-between"><span className="text-[#8a909a]">Continuous assessment (max)</span><span className="font-bold">{scale.data.caMax}</span></div>
          <div className="flex justify-between"><span className="text-[#8a909a]">Examination (max)</span><span className="font-bold">{scale.data.examMax}</span></div>
          <div className="flex justify-between border-t border-[#f1f2f4] pt-3"><span className="text-[#8a909a]">Total</span><span className="font-bold">{scale.data.caMax + scale.data.examMax}</span></div>
          <div className="rounded-lg bg-[#f5f8ff] p-3 text-xs text-[#3858a5]"><span className="font-semibold">GPA: </span>{scale.data.gpaFormula}</div>
        </div>
        <table className="w-full text-left text-xs">
          <thead className="text-[10px] font-semibold uppercase tracking-[.09em] text-[#9ca2ab]"><tr><th className="py-2">Score</th><th className="py-2">Grade</th><th className="py-2">Points</th></tr></thead>
          <tbody>{scale.data.bands.map((band) => <tr key={band.grade} className="border-t border-[#f1f2f4]"><td className="py-2.5">{band.min} – {band.max}</td><td className="py-2.5 font-bold">{band.grade}</td><td className="py-2.5">{band.point}</td></tr>)}</tbody>
        </table>
      </div>}
    </section>
  </AdminPage>;
}
