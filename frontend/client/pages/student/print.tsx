import { useEffect, useRef, type ReactNode } from "react";

/** Calls window.print() after the print-only sheet for `key` has rendered. */
export function usePrintTrigger(key: string | null, done: () => void) {
  const doneRef = useRef(done);
  doneRef.current = done;
  useEffect(() => {
    if (!key) return;
    const timer = window.setTimeout(() => { window.print(); doneRef.current(); }, 80);
    return () => window.clearTimeout(timer);
  }, [key]);
}

/** Letterhead for printed documents. Hidden on screen, shown on paper. */
export function PrintSheet({ title, subtitle, children }: { title: string; subtitle?: string; children: ReactNode }) {
  return <div className="print-only text-[#111] [font-family:Georgia,serif]">
    <div className="border-b-2 border-[#111] pb-3 text-center">
      <div className="text-xl font-bold tracking-wide">NORTHSTAR UNIVERSITY</div>
      <div className="mt-1 text-sm uppercase tracking-[.15em]">{title}</div>
      {subtitle && <div className="mt-1 text-xs">{subtitle}</div>}
    </div>
    <div className="mt-4">{children}</div>
    <div className="mt-10 flex justify-between text-xs">
      <span>Printed on {new Date().toLocaleDateString("en-US", { year: "numeric", month: "long", day: "numeric" })}</span>
      <span>Computer-generated document. Not valid without the registrar's stamp.</span>
    </div>
  </div>;
}

export function PrintFacts({ rows }: { rows: [string, string | number][] }) {
  return <dl className="grid grid-cols-2 gap-x-8 gap-y-1 text-sm">
    {rows.map(([label, value]) => <div key={label} className="flex gap-2"><dt className="w-32 shrink-0 font-semibold">{label}:</dt><dd>{value}</dd></div>)}
  </dl>;
}

export const printTable = "mt-4 w-full border-collapse text-sm [&_td]:border [&_td]:border-[#444] [&_td]:px-2 [&_td]:py-1 [&_th]:border [&_th]:border-[#444] [&_th]:bg-[#eee] [&_th]:px-2 [&_th]:py-1 [&_th]:text-left";
