import type { ReactNode } from "react";
import { buttonClass } from "./ui";

export interface Column<T> {
  key: string;
  header: string;
  render: (row: T) => ReactNode;
  className?: string;
}

interface DataTableProps<T> {
  title: string;
  subtitle?: string;
  rows: T[];
  columns: Column<T>[];
  rowKey: (row: T) => string;
  loading?: boolean;
  emptyMessage?: string;
  /** Search box, filters and similar controls shown in the header. */
  toolbar?: ReactNode;
  /** A "View all" link or similar, shown in the header. */
  action?: ReactNode;
  /** Server-side pagination; omit for short, unpaged tables. */
  page?: number;
  lastPage?: number;
  total?: number;
  onPageChange?: (page: number) => void;
}

export function DataTable<T>({ title, subtitle, rows, columns, rowKey, loading = false, emptyMessage = "No records found.", toolbar, action, page = 1, lastPage = 1, total, onPageChange }: DataTableProps<T>) {
  return <section className="overflow-hidden rounded-xl border border-[#e9ebee] bg-white">
    <div className="flex flex-wrap items-center justify-between gap-4 p-5">
      <div><h2 className="text-base font-bold tracking-tight">{title}</h2>{subtitle && <p className="mt-1 text-xs text-[#9aa1ab]">{subtitle}</p>}</div>
      <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">{toolbar}{action}</div>
    </div>
    <div className="overflow-x-auto">
      <table className="w-full min-w-[640px] text-left">
        <thead className="bg-[#f8f9fa] text-[10px] font-semibold uppercase tracking-[.09em] text-[#9ca2ab]">
          <tr>{columns.map((column) => <th key={column.key} className={`px-5 py-3.5 font-semibold ${column.className ?? ""}`}>{column.header}</th>)}</tr>
        </thead>
        <tbody>
          {loading
            ? Array.from({ length: 4 }, (_, index) => <tr key={index} className="border-t border-[#f1f2f4]"><td colSpan={columns.length} className="px-5 py-4"><div className="h-4 animate-pulse rounded bg-[#f0f2f5]" /></td></tr>)
            : rows.map((row) => <tr key={rowKey(row)} className="border-t border-[#f1f2f4] text-xs hover:bg-[#fbfbfc]">{columns.map((column) => <td key={column.key} className={`px-5 py-4 text-[#737b87] ${column.className ?? ""}`}>{column.render(row)}</td>)}</tr>)}
        </tbody>
      </table>
      {!loading && rows.length === 0 && <div className="px-5 py-14 text-center text-sm text-[#959ca6]">{emptyMessage}</div>}
    </div>
    {onPageChange && <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[#f0f1f3] px-5 py-4 text-xs text-[#989ea7]">
      <span>{total !== undefined ? `${total} ${total === 1 ? "record" : "records"} · ` : ""}Page {page} of {Math.max(lastPage, 1)}</span>
      <div className="flex gap-2">
        <button className={buttonClass} disabled={page <= 1 || loading} onClick={() => onPageChange(page - 1)}>Previous</button>
        <button className={buttonClass} disabled={page >= lastPage || loading} onClick={() => onPageChange(page + 1)}>Next</button>
      </div>
    </div>}
  </section>;
}
