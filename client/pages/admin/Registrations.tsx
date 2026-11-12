import { useState } from "react";
import { api } from "../../lib/api";
import { useAsyncData, useDebouncedValue } from "../../hooks/useAsyncData";
import type { RegistrationRecord } from "../../types/portal";
import { DataTable } from "../../components/admin/DataTable";
import { AdminPage, ErrorState, Initials, Modal, PageHeader, SearchInput, buttonClass, formatDate, inputClass } from "../../components/admin/ui";

const LEVELS = [100, 200, 300, 400, 500, 600];

export default function Registrations() {
  const [search, setSearch] = useState("");
  const [level, setLevel] = useState("");
  const [page, setPage] = useState(1);
  const query = useDebouncedValue(search);
  const { data, loading, error, reload } = useAsyncData(() => api.admin.registrations({ page, search: query, level }), [page, query, level]);
  const [viewing, setViewing] = useState<RegistrationRecord | null>(null);

  const needle = query.trim().toLowerCase();
  // The server filters; this also filters the loaded page so the mock behaves the same.
  const rows = (data?.data ?? []).filter((row) =>
    (!needle || [row.studentName, row.matricNo, row.departmentName].some((value) => value.toLowerCase().includes(needle))) &&
    (!level || String(row.level) === level));

  return <AdminPage>
    <PageHeader eyebrow="University management" title="Registrations" description="Course registrations across the university. This view is read-only." />
    {error ? <ErrorState error={error} onRetry={reload} /> : <DataTable
      title="All registrations"
      rows={rows}
      rowKey={(row) => row.id}
      loading={loading}
      emptyMessage="No registrations match your filters."
      toolbar={<>
        <SearchInput label="Search registrations" value={search} onChange={(value) => { setSearch(value); setPage(1); }} />
        <select aria-label="Filter by level" className={`${inputClass} w-auto min-w-[130px]`} value={level} onChange={(e) => { setLevel(e.target.value); setPage(1); }}>
          <option value="">All levels</option>{LEVELS.map((value) => <option key={value} value={value}>{value} level</option>)}
        </select>
      </>}
      page={data?.meta.current_page ?? page}
      lastPage={data?.meta.last_page ?? 1}
      total={data?.meta.total}
      onPageChange={setPage}
      columns={[
        { key: "student", header: "Student", render: (row) => <div className="flex items-center gap-3"><Initials name={row.studentName} /><div><div className="font-semibold text-[#343c48]">{row.studentName}</div><div className="mt-1 text-[11px] text-[#a0a6ae]">{row.matricNo}</div></div></div> },
        { key: "department", header: "Department", render: (row) => row.departmentName },
        { key: "level", header: "Level", render: (row) => `${row.level} level` },
        { key: "period", header: "Session", className: "whitespace-nowrap", render: (row) => `${row.sessionName} · ${row.semester}` },
        { key: "courses", header: "Courses", render: (row) => `${row.courses.length} (${row.totalUnits} units)` },
        { key: "date", header: "Registered", className: "whitespace-nowrap", render: (row) => formatDate(row.registeredAt) },
        { key: "actions", header: "Actions", render: (row) => <button className={buttonClass} onClick={() => setViewing(row)}>View courses</button> },
      ]}
    />}

    {viewing && <Modal title="Registered courses" description={`${viewing.studentName} · ${viewing.matricNo} · ${viewing.sessionName}, ${viewing.semester} semester`} onClose={() => setViewing(null)}>
      <ul className="mt-5 divide-y divide-[#f1f2f4] text-sm">
        {viewing.courses.map((course) => <li key={course.courseId} className="flex items-center justify-between gap-3 py-2.5"><span><span className="font-semibold">{course.code}</span><span className="ml-2 text-[#8a909a]">{course.title}</span></span><span className="text-xs text-[#8a909a]">{course.units} {course.units === 1 ? "unit" : "units"}</span></li>)}
      </ul>
      <div className="mt-4 flex justify-between border-t border-[#f1f2f4] pt-3 text-sm font-semibold"><span>Total units</span><span>{viewing.totalUnits}</span></div>
      <div className="mt-5 flex justify-end"><button className={buttonClass} onClick={() => setViewing(null)}>Close</button></div>
    </Modal>}
  </AdminPage>;
}
