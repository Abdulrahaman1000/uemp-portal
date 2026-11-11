import { useState } from "react";
import { Link, Navigate, useNavigate, useParams } from "react-router-dom";
import { ChevronRight, GraduationCap, LogOut, Menu, PanelLeftClose } from "lucide-react";
import { useAuth } from "../../lib/auth";
import { menus, roleName, rolePath } from "../../lib/navigation";
import type { UserRole } from "../../types/portal";
import AdminPages, { type AdminSection } from "../../pages/admin";
import DeptPages from "../../pages/dept";
import LecturerPages from "../../pages/lecturer";
import StudentPages from "../../pages/student";

/** Sidebar, header and the page area for one signed-in role. */
export default function DashboardShell({ role }: { role: UserRole }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const { section: sectionParam } = useParams();
  const [drawer, setDrawer] = useState(false);
  const [collapsed, setCollapsed] = useState(false);

  if (!user) return <Navigate to="/login" replace />;

  const base = rolePath[role];
  const items = menus[role];
  const section = items.find((item) => item.id === sectionParam)?.id ?? "overview";
  const pathFor = (id: string) => (id === "overview" ? base : `${base}/${id}`);
  const changeSection = (id: string) => { navigate(pathFor(id)); setDrawer(false); };
  const initial = user.name.split(" ").filter((part) => part && !part.endsWith(".")).slice(0, 2).map((part) => part[0]).join("");
  const scope = role === "super_admin" ? "University" : role === "dept_admin" ? user.department_name || "Department" : "My portal";

  return <div className="min-h-screen bg-[#f6f8fc] font-sans text-[#1f2c45]">
    {drawer && <button aria-label="Close navigation" onClick={() => setDrawer(false)} className="fixed inset-0 z-30 bg-[#142343]/40 lg:hidden" />}
    <aside className={`fixed inset-y-0 left-0 z-40 flex flex-col border-r border-[#e8edf5] bg-white transition-all duration-200 ${collapsed ? "lg:w-[76px]" : "lg:w-[250px]"} w-[250px] ${drawer ? "translate-x-0" : "-translate-x-full lg:translate-x-0"}`}>
      <Link to={base} onClick={() => setDrawer(false)} className="flex h-[78px] shrink-0 items-center gap-3 border-b border-[#edf1f7] px-5">
        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#315ed0] text-white shadow-[0_5px_12px_rgba(49,94,208,.22)]"><GraduationCap size={23} /></span>
        <span className={collapsed ? "lg:hidden" : ""}>
          <span className="block text-lg font-bold leading-5 tracking-[-.05em] text-[#172a4b]">northstar<span className="text-[#4c74df]">.</span></span>
          <span className="mt-1 block text-[9px] font-bold uppercase tracking-[.16em] text-[#a3afc1]">University portal</span>
        </span>
      </Link>
      <div className="flex-1 overflow-y-auto px-3 py-6">
        <p className={`px-3 pb-3 text-[10px] font-bold uppercase tracking-[.15em] text-[#a7b3c5] ${collapsed ? "lg:hidden" : ""}`}>Main menu</p>
        <nav aria-label="Main navigation" className="space-y-1">
          {items.map((item) => <Link key={item.id} title={item.label} to={pathFor(item.id)} onClick={() => setDrawer(false)} aria-current={section === item.id ? "page" : undefined} className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-[13px] font-medium transition ${section === item.id ? "bg-[#edf3ff] font-semibold text-[#315ed0]" : "text-[#8290a7] hover:bg-[#f5f7fc] hover:text-[#315ed0]"}`}>
            <item.icon size={17} className="shrink-0" />
            <span className={collapsed ? "lg:hidden" : ""}>{item.label}</span>
          </Link>)}
        </nav>
      </div>
      <div className="border-t border-[#edf1f7] p-4">
        <button onClick={() => setCollapsed(!collapsed)} className="hidden w-full items-center gap-2 rounded-lg px-2 py-2 text-xs text-[#9aa8bd] hover:bg-[#f5f7fc] lg:flex" aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}>
          {collapsed ? <ChevronRight size={16} /> : <PanelLeftClose size={16} />}{!collapsed && "Collapse sidebar"}
        </button>
      </div>
    </aside>

    <div className={`transition-all duration-200 ${collapsed ? "lg:pl-[76px]" : "lg:pl-[250px]"}`}>
      <header className="sticky top-0 z-20 flex h-[78px] items-center justify-between border-b border-[#e8edf5] bg-white/95 px-4 backdrop-blur sm:px-7 lg:px-9">
        <div className="flex items-center gap-3">
          <button onClick={() => setDrawer(true)} aria-label="Open navigation" className="rounded-lg p-2 text-[#536786] lg:hidden"><Menu size={21} /></button>
          <div className="hidden text-xs text-[#9ba9bd] sm:block">{scope} <span className="mx-2 text-[#d0d9e6]">/</span><span className="font-semibold text-[#526683]">{items.find((item) => item.id === section)?.label}</span></div>
        </div>
        <div className="flex items-center gap-3 sm:gap-5">
          <div className="flex h-9 w-9 items-center justify-center rounded-full bg-[#e9efff] text-xs font-bold text-[#5472b8]">{initial}</div>
          <div className="hidden sm:block">
            <div className="text-xs font-bold">{user.name}</div>
            <div className="mt-1 text-[10px] font-semibold text-[#95a3b8]">{roleName[role]}</div>
          </div>
          <button title="Log out" aria-label="Log out" onClick={async () => { await logout(); navigate("/login"); }} className="rounded-lg p-2 text-[#9aa8bb] hover:bg-[#f3f6fc] hover:text-[#315ed0]"><LogOut size={17} /></button>
        </div>
      </header>
      {role === "lecturer" ? <LecturerPages section={section} />
        : role === "student" ? <StudentPages section={section} />
        : role === "dept_admin" ? <DeptPages section={section} />
        : <AdminPages section={section as AdminSection} onSectionChange={changeSection} />}
    </div>
  </div>;
}
