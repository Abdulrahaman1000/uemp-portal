import { ApiError } from "../../lib/api";
import type { UserStatus } from "../../types/portal";
import { inputClass } from "../../components/admin/ui";

export const LEVELS = [100, 200, 300, 400, 500, 600];

/** First message per field from a 422 response. */
export function firstErrors(err: unknown): Record<string, string> {
  if (!(err instanceof ApiError) || !err.isValidation) return {};
  return Object.fromEntries(Object.entries(err.fieldErrors).map(([key, messages]) => [key, messages[0]]));
}

export function StatusSelect({ value, disabled, onChange, label }: { value: string; disabled?: boolean; onChange: (status: UserStatus) => void; label: string }) {
  // "graduated" is a student state set outside this screen, so it is shown but not selectable.
  return <select aria-label={label} className={`${inputClass} !w-auto !py-1.5 text-xs`} value={value} disabled={disabled || value === "graduated"} onChange={(e) => onChange(e.target.value as UserStatus)}>
    {value === "graduated" && <option value="graduated">Graduated</option>}
    <option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option>
  </select>;
}
