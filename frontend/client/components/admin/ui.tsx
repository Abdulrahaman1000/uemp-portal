import { useEffect, type ReactNode } from "react";
import { AlertTriangle, Search, ShieldCheck, X, type LucideIcon } from "lucide-react";
import { ApiError } from "../../lib/api";

export const inputClass = "w-full rounded-lg border border-[#e5e7e9] bg-white px-3.5 py-2.5 text-sm text-[#262c36] outline-none transition focus:border-[#3268c5] focus:ring-2 focus:ring-[#d9e1ee]";
export const buttonClass = "inline-flex items-center justify-center gap-2 rounded-lg border border-[#e5e7e9] bg-white px-3.5 py-2.5 text-sm font-semibold text-[#434953] transition hover:bg-[#f7f8fa] disabled:opacity-60";
export const primaryClass = "inline-flex items-center justify-center gap-2 rounded-lg bg-[#2454b8] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1d4294] disabled:opacity-60";

export function formatDate(value: string | null | undefined): string {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

export function titleCase(value: string): string {
  return value ? value.charAt(0).toUpperCase() + value.slice(1).replace(/_/g, " ") : value;
}

/** Page wrapper that matches the spacing of the admin shell. */
export function AdminPage({ children }: { children: ReactNode }) {
  return <main className="mx-auto max-w-[1600px] px-4 py-7 sm:px-6 lg:px-9 lg:py-9">{children}</main>;
}

export function PageHeader({ eyebrow, title, description, actions }: { eyebrow?: string; title: ReactNode; description?: string; actions?: ReactNode }) {
  return <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
    <div>
      {eyebrow && <div className="mb-2 text-xs font-semibold uppercase tracking-[.1em] text-[#9199a4]">{eyebrow}</div>}
      <h1 className="text-[28px] font-bold tracking-[-.045em] text-[#202937] sm:text-[30px]">{title}</h1>
      {description && <p className="mt-2 text-sm text-[#8a909a]">{description}</p>}
    </div>
    {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
  </div>;
}

const positive = ["active", "approved", "current", "completed", "ready", "open"];
const warning = ["submitted", "pending", "reopened", "invited", "in progress"];
const danger = ["rejected", "suspended"];

export function StatusBadge({ value }: { value: string }) {
  const key = value.toLowerCase();
  const tone = positive.includes(key) ? "good" : warning.includes(key) ? "warn" : danger.includes(key) ? "bad" : "idle";
  const styles = {
    good: ["bg-[#e8edf5] text-[#254b84]", "bg-[#3664a8]"],
    warn: ["bg-[#fff4de] text-[#ad771b]", "bg-[#e5a735]"],
    bad: ["bg-[#fdeaea] text-[#b23b3b]", "bg-[#d95c5c]"],
    idle: ["bg-[#f0f1f2] text-[#747981]", "bg-[#9a9fa6]"],
  }[tone];
  return <span className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ${styles[0]}`}><span className={`h-1.5 w-1.5 rounded-full ${styles[1]}`} />{titleCase(value)}</span>;
}

const avatarColors = ["bg-[#e8ebf0] text-[#435776]", "bg-[#f8e9df] text-[#b36f4c]", "bg-[#edeaf6] text-[#7968a0]", "bg-[#e6eff7] text-[#6085a4]"];
export function Initials({ name, index = 0 }: { name: string; index?: number }) {
  const letters = name.split(" ").filter((part) => part && !["Dr.", "Prof."].includes(part)).slice(0, 2).map((part) => part[0]).join("");
  return <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold ${avatarColors[index % avatarColors.length]}`}>{letters}</span>;
}

export function StatCard({ label, value, icon: Icon, note }: { label: string; value: ReactNode; icon: LucideIcon; note?: string }) {
  return <div className="rounded-xl border border-[#e9ebee] bg-white p-5 shadow-[0_2px_12px_rgba(31,54,91,0.025)]">
    <div className="flex items-center justify-between"><span className="text-[13px] font-medium text-[#848b95]">{label}</span><span className="flex h-10 w-10 items-center justify-center rounded-lg bg-[#edf1f6] text-[#425f8b]"><Icon size={19} /></span></div>
    <div className="mt-3 text-[28px] font-bold tracking-[-.05em]">{value}</div>
    {note && <div className="mt-2 text-[11px] text-[#a6abb3]">{note}</div>}
  </div>;
}

export function LoadingBlock({ label = "Loading…" }: { label?: string }) {
  return <div role="status" className="flex items-center justify-center gap-3 rounded-xl border border-[#e9ebee] bg-white py-16 text-sm text-[#8a909a]"><span className="h-4 w-4 animate-spin rounded-full border-2 border-[#d9e1ee] border-t-[#2454b8]" />{label}</div>;
}

export function ErrorState({ error, onRetry }: { error: Error; onRetry?: () => void }) {
  const forbidden = error instanceof ApiError && error.isForbidden;
  const Icon = forbidden ? ShieldCheck : AlertTriangle;
  return <div role="alert" className="flex flex-col items-center rounded-xl border border-[#f1d5d5] bg-white px-6 py-14 text-center">
    <Icon size={32} className="text-[#c0504d]" />
    <h2 className="mt-4 text-base font-bold">{forbidden ? "You don't have access to this" : "We couldn't load this page"}</h2>
    <p className="mt-1 max-w-md text-sm text-[#8a909a]">{error.message}</p>
    {onRetry && !forbidden && <button className={`${buttonClass} mt-5`} onClick={onRetry}>Try again</button>}
  </div>;
}

export function SearchInput({ value, onChange, label }: { value: string; onChange: (value: string) => void; label: string }) {
  return <div className="relative min-w-[190px] flex-1 sm:flex-none">
    <Search size={15} className="absolute left-3 top-3 text-[#9ea4ac]" />
    <input aria-label={label} className={`${inputClass} pl-9`} placeholder="Search…" value={value} onChange={(event) => onChange(event.target.value)} />
  </div>;
}

export function Notice({ message, onDismiss }: { message: string; onDismiss: () => void }) {
  return <div role="status" className="mb-4 flex items-center gap-2 rounded-lg border border-[#d6dfed] bg-[#edf1f8] px-4 py-3 text-sm text-[#28497b]">{message}<button className="ml-auto" aria-label="Dismiss notification" onClick={onDismiss}><X size={15} /></button></div>;
}

export function Modal({ title, description, onClose, children }: { title: string; description?: string; onClose: () => void; children: ReactNode }) {
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => { if (event.key === "Escape") onClose(); };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);
  return <div className="fixed inset-0 z-50 flex items-center justify-center bg-[#101926]/45 p-4" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
    <div role="dialog" aria-modal="true" aria-label={title} className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
      <div className="flex items-start justify-between gap-4">
        <div><h2 className="text-lg font-bold">{title}</h2>{description && <p className="mt-1 text-xs text-[#979ea8]">{description}</p>}</div>
        <button onClick={onClose} aria-label="Close dialog" className="rounded-lg p-1 text-[#8b939e] hover:bg-[#f2f4f6]"><X size={19} /></button>
      </div>
      {children}
    </div>
  </div>;
}

export const dangerClass = "inline-flex items-center justify-center gap-2 rounded-lg bg-[#c0392b] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#a93226] disabled:opacity-60";

export function errorMessage(error: unknown): string {
  return error instanceof Error ? error.message : "Something went wrong. Please try again.";
}

/** A labelled form control with an optional validation message (e.g. from a 422). */
export function Field({ label, error, required, children }: { label: string; error?: string; required?: boolean; children: ReactNode }) {
  return <label className="block text-xs font-semibold text-[#606874]">
    {label}{required && " *"}
    {children}
    {error && <span role="alert" className="mt-1 block text-[11px] font-normal text-[#c0392b]">{error}</span>}
  </label>;
}

export function ConfirmDialog({ title, message, confirmLabel = "Confirm", danger = false, busy = false, onConfirm, onCancel }: { title: string; message: string; confirmLabel?: string; danger?: boolean; busy?: boolean; onConfirm: () => void; onCancel: () => void }) {
  return <Modal title={title} onClose={onCancel}>
    <p className="mt-4 text-sm text-[#59616d]">{message}</p>
    <div className="mt-6 flex justify-end gap-2">
      <button className={buttonClass} onClick={onCancel} disabled={busy}>Cancel</button>
      <button className={danger ? dangerClass : primaryClass} onClick={onConfirm} disabled={busy}>{busy ? "Working…" : confirmLabel}</button>
    </div>
  </Modal>;
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString("en-US", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" });
}
