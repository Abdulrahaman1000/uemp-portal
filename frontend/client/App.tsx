import "./global.css";
import { useState, type FormEvent } from "react";
import { createRoot } from "react-dom/client";
import { BrowserRouter, Link, Navigate, Route, Routes, useNavigate, useParams } from "react-router-dom";
import { Activity, ArrowRight, Bell, BookOpen, Building2, CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, ClipboardCheck, FileBarChart2, FileCheck2, GraduationCap, LayoutDashboard, Library, LogOut, Menu, PanelLeftClose, Search, ShieldCheck, UserRound, Users, X, Eye, EyeOff } from "lucide-react";
import { Toaster } from "sonner";
import { AuthProvider, useAuth } from "./lib/auth";
import { MOCK_PASSWORD, mockUsers } from "./lib/mock";
import type { User, UserRole } from "./types/portal";
import AdminScreens, { type AdminSection } from "./components/AdminScreens";
import RoleScreens, { type RoleSection } from "./components/RoleScreens";

type MenuItem = { id: string; label: string; icon: typeof LayoutDashboard };
const menus: Record<UserRole, MenuItem[]> = {
  student: [
    { id: "overview", label: "Dashboard", icon: LayoutDashboard }, { id: "profile", label: "My profile", icon: UserRound }, { id: "registration", label: "Course registration", icon: ClipboardCheck }, { id: "courses", label: "My courses", icon: BookOpen }, { id: "results", label: "Results", icon: FileCheck2 }, { id: "history", label: "Academic history", icon: Activity },
  ],
  lecturer: [
    { id: "overview", label: "Dashboard", icon: LayoutDashboard }, { id: "courses", label: "My courses", icon: BookOpen }, { id: "results", label: "Result entry", icon: FileCheck2 }, { id: "submissions", label: "Submissions", icon: ClipboardCheck },
  ],
  dept_admin: [
    { id: "overview", label: "Dashboard", icon: LayoutDashboard }, { id: "students", label: "Students", icon: GraduationCap }, { id: "lecturers", label: "Lecturers", icon: Users }, { id: "courses", label: "Courses", icon: BookOpen }, { id: "assignments", label: "Course assignments", icon: ClipboardCheck }, { id: "programs", label: "Levels & programs", icon: Library }, { id: "registrations", label: "Registrations", icon: FileCheck2 }, { id: "approvals", label: "Result approvals", icon: ShieldCheck }, { id: "reports", label: "Reports", icon: FileBarChart2 },
  ],
  super_admin: [
    { id: "overview", label: "Dashboard", icon: LayoutDashboard }, { id: "faculties", label: "Faculties", icon: Building2 }, { id: "departments", label: "Departments", icon: Library }, { id: "programs", label: "Programs", icon: BookOpen }, { id: "sessions", label: "Sessions & semesters", icon: CalendarDays }, { id: "users", label: "Users", icon: Users }, { id: "roles", label: "Roles & permissions", icon: ShieldCheck }, { id: "courses", label: "Courses & allocations", icon: BookOpen }, { id: "registrations", label: "Registrations", icon: ClipboardCheck }, { id: "oversight", label: "Result oversight", icon: FileCheck2 }, { id: "reports", label: "Reports", icon: FileBarChart2 }, { id: "activity-log", label: "Activity log", icon: Activity },
  ],
};
const rolePath: Record<UserRole, string> = { student: "/student", lecturer: "/lecturer", dept_admin: "/dept", super_admin: "/admin" };
const roleName: Record<UserRole, string> = { student: "Student", lecturer: "Lecturer", dept_admin: "Department admin", super_admin: "Super admin" };

function Login() {
  const { login, user } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  if (user) return <Navigate to={rolePath[user.role]} replace />;
  async function submit(event: FormEvent) {
    event.preventDefault(); setError(""); setLoading(true);
    try { const next = await login({ email, password }); navigate(rolePath[next.role], { replace: true }); }
    catch (err) { setError(err instanceof Error ? err.message : "Unable to sign in. Please try again."); }
    finally { setLoading(false); }
  }
  return <div className="min-h-screen bg-[#f5f7fc] text-[#182745] lg:grid lg:grid-cols-[1fr_1fr]">
    <div className="relative hidden overflow-hidden bg-[#112756] px-14 py-12 text-white lg:flex lg:flex-col xl:px-20"><Link to="/" className="flex items-center gap-3"><span className="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-[#244fc1]"><GraduationCap size={25} /></span><span className="text-xl font-bold tracking-tight">northstar<span className="text-[#88a6ff]">.</span></span></Link><div className="relative z-10 my-auto max-w-lg"><p className="mb-5 text-xs font-bold uppercase tracking-[.2em] text-[#9db7ff]">A better way to learn, together</p><h1 className="text-5xl font-semibold leading-[1.13] tracking-[-.04em]">A brighter future starts here.</h1><p className="mt-6 max-w-md text-base leading-7 text-[#c3cee8]">One connected place for your university community to learn, teach, and achieve more.</p><div className="mt-10 flex items-center gap-3 text-sm text-[#d3dcf1]"><CheckCircle2 size={18} className="text-[#a7bcff]" /> Your academic journey, all in one place</div></div><div className="relative z-10 text-xs text-[#a9b9d8]">© Northstar University · Education management portal</div><div className="absolute -bottom-52 -right-36 h-[600px] w-[600px] rounded-full border border-white/10" /><div className="absolute -bottom-32 -right-16 h-[440px] w-[440px] rounded-full border border-white/10" /><div className="absolute -bottom-12 right-4 h-[280px] w-[280px] rounded-full border border-white/10" /></div>
    <div className="flex items-center justify-center px-5 py-12"><div className="w-full max-w-[430px]"><Link to="/" className="mb-10 flex items-center gap-2 text-lg font-bold lg:hidden"><GraduationCap className="text-[#315dd2]" /> northstar.</Link><div className="mb-8"><p className="mb-2 text-xs font-bold uppercase tracking-[.16em] text-[#5575cf]">Welcome back</p><h1 className="text-3xl font-semibold tracking-tight">Sign in to your portal</h1><p className="mt-2 text-sm text-[#8290a9]">Enter your details to continue to Northstar.</p></div><form onSubmit={submit} className="space-y-5"><label className="block text-sm font-semibold">Email address<input required type="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="you@northstar.edu" className="mt-2 h-12 w-full rounded-lg border border-[#dce3ef] bg-white px-4 text-sm outline-none focus:border-[#5477db] focus:ring-2 focus:ring-[#e4ebff]" /></label><label className="block text-sm font-semibold">Password<div className="relative mt-2"><input required type={showPassword ? "text" : "password"} value={password} onChange={e => setPassword(e.target.value)} placeholder="Enter your password" className="h-12 w-full rounded-lg border border-[#dce3ef] bg-white px-4 pr-12 text-sm outline-none focus:border-[#5477db] focus:ring-2 focus:ring-[#e4ebff]" /><button type="button" onClick={() => setShowPassword(!showPassword)} aria-label={showPassword ? "Hide password" : "Show password"} className="absolute right-4 top-3.5 text-[#8c9ab2]">{showPassword ? <EyeOff size={19} /> : <Eye size={19} />}</button></div></label>{error && <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}<button disabled={loading} className="flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-[#315ed0] text-sm font-semibold text-white hover:bg-[#234db8] disabled:opacity-60">{loading ? "Signing in…" : "Sign in"} {!loading && <ArrowRight size={16} />}</button></form><div className="mt-8 rounded-xl border border-[#dfe7f6] bg-white p-5"><div className="flex items-center justify-between"><h2 className="text-sm font-semibold">Demo credentials</h2><span className="rounded-full bg-[#eef3ff] px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-[#5373c8]">Demo mode</span></div><p className="mt-1 text-xs text-[#91a0b7]">Choose an account to fill in the form.</p><div className="mt-4 space-y-2">{mockUsers.map(account => <button key={account.id} type="button" onClick={() => { setEmail(account.email); setPassword(MOCK_PASSWORD); }} className="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-xs hover:bg-[#f4f7ff]"><span className="font-semibold text-[#536581]">{roleName[account.role]}</span><span className="text-[#8c9bb2]">{account.email} / {MOCK_PASSWORD}</span></button>)}</div></div><Link to="/" className="mt-6 inline-flex items-center gap-1 text-xs font-semibold text-[#5876c7]"><ChevronLeft size={14} /> Back to dashboard preview</Link></div></div>
  </div>;
}

function RoleGuard({ role, children }: { role: UserRole; children: React.ReactNode }) {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (user.role !== role) return <Navigate to={rolePath[user.role]} replace />;
  return <>{children}</>;
}

function Dashboard({ role, demo = false }: { role: UserRole; demo?: boolean }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const { section: sectionParam } = useParams();
  const [drawer, setDrawer] = useState(false);
  const [collapsed, setCollapsed] = useState(false);
  const currentUser: User = user ?? mockUsers[0];
  const section = menus[role].some(item => item.id === sectionParam) ? sectionParam! : "overview";
  const base = demo ? "/" : rolePath[role];
  const pathFor = (id: string) => id === "overview" ? base : `${base === "/" ? "" : base}/${id}`;
  const changeSection = (id: string) => { navigate(pathFor(id)); setDrawer(false); };
  const initial = currentUser.name.split(" ").filter(part => !part.endsWith(".")).slice(0, 2).map(part => part[0]).join("");
  return <div className="min-h-screen bg-[#f6f8fc] font-sans text-[#1f2c45]">
    {drawer && <button aria-label="Close navigation" onClick={() => setDrawer(false)} className="fixed inset-0 z-30 bg-[#142343]/40 lg:hidden" />}
    <aside className={`fixed inset-y-0 left-0 z-40 flex flex-col border-r border-[#e8edf5] bg-white transition-all duration-200 ${collapsed ? "lg:w-[76px]" : "lg:w-[250px]"} w-[250px] ${drawer ? "translate-x-0" : "-translate-x-full lg:translate-x-0"}`}>
      <Link to={base} onClick={() => setDrawer(false)} className="flex h-[78px] shrink-0 items-center gap-3 border-b border-[#edf1f7] px-5"><span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#315ed0] text-white shadow-[0_5px_12px_rgba(49,94,208,.22)]"><GraduationCap size={23} /></span><span className={collapsed ? "lg:hidden" : ""}><span className="block text-lg font-bold leading-5 tracking-[-.05em] text-[#172a4b]">northstar<span className="text-[#4c74df]">.</span></span><span className="mt-1 block text-[9px] font-bold uppercase tracking-[.16em] text-[#a3afc1]">University portal</span></span></Link>
      <div className="flex-1 overflow-y-auto px-3 py-6"><p className={`px-3 pb-3 text-[10px] font-bold uppercase tracking-[.15em] text-[#a7b3c5] ${collapsed ? "lg:hidden" : ""}`}>Main menu</p><nav aria-label="Main navigation" className="space-y-1">{menus[role].map(item => <Link key={item.id} title={item.label} to={pathFor(item.id)} onClick={() => setDrawer(false)} aria-current={section === item.id ? "page" : undefined} className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-[13px] font-medium transition ${section === item.id ? "bg-[#edf3ff] font-semibold text-[#315ed0]" : "text-[#8290a7] hover:bg-[#f5f7fc] hover:text-[#315ed0]"}`}><item.icon size={17} className="shrink-0" /><span className={collapsed ? "lg:hidden" : ""}>{item.label}</span>{item.id === "approvals" && <span className={`ml-auto rounded-md bg-[#e6edff] px-1.5 py-0.5 text-[10px] font-bold text-[#5273c6] ${collapsed ? "lg:hidden" : ""}`}>3</span>}</Link>)}</nav></div>
      <div className="border-t border-[#edf1f7] p-4"><div className={`rounded-xl bg-[#f5f8ff] p-3 ${collapsed ? "lg:hidden" : ""}`}><p className="text-xs font-semibold text-[#3858a5]">Need a hand?</p><p className="mt-1 text-[11px] leading-4 text-[#94a3ba]">Your academic workspace, all in one place.</p></div><button onClick={() => setCollapsed(!collapsed)} className="mt-3 hidden w-full items-center gap-2 rounded-lg px-2 py-2 text-xs text-[#9aa8bd] hover:bg-[#f5f7fc] lg:flex" aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}>{collapsed ? <ChevronRight size={16} /> : <PanelLeftClose size={16} />}{!collapsed && "Collapse sidebar"}</button></div>
    </aside>
    <div className={`transition-all duration-200 ${collapsed ? "lg:pl-[76px]" : "lg:pl-[250px]"}`}><header className="sticky top-0 z-20 flex h-[78px] items-center justify-between border-b border-[#e8edf5] bg-white/95 px-4 backdrop-blur sm:px-7 lg:px-9"><div className="flex items-center gap-3"><button onClick={() => setDrawer(true)} aria-label="Open navigation" className="rounded-lg p-2 text-[#536786] lg:hidden"><Menu size={21} /></button><div className="hidden text-xs text-[#9ba9bd] sm:block">{role === "super_admin" ? "University" : role === "dept_admin" ? currentUser.department_name || "Department" : "My portal"} <span className="mx-2 text-[#d0d9e6]">/</span><span className="font-semibold text-[#526683]">{menus[role].find(item => item.id === section)?.label}</span></div></div><div className="flex items-center gap-3 sm:gap-5"><div className="hidden items-center gap-2 rounded-lg border border-[#e8edf5] px-3 py-2 text-xs font-semibold text-[#60718b] md:flex"><CalendarDays size={14} className="text-[#7590ca]" /> 2024 / 2025 <span className="font-normal text-[#a5b1c2]">· First semester</span></div><span className="hidden h-8 w-px bg-[#edf1f7] sm:block" /><div className="flex h-9 w-9 items-center justify-center rounded-full bg-[#e9efff] text-xs font-bold text-[#5472b8]">{initial}</div><div className="hidden sm:block"><div className="text-xs font-bold">{currentUser.name}</div><div className="mt-1 text-[10px] font-semibold text-[#95a3b8]">{demo ? "Preview mode" : roleName[role]}</div></div>{demo ? <Link to="/login" className="rounded-lg bg-[#315ed0] px-3 py-2 text-xs font-semibold text-white">Sign in</Link> : <button title="Log out" aria-label="Log out" onClick={async () => { await logout(); navigate("/login"); }} className="rounded-lg p-2 text-[#9aa8bb] hover:bg-[#f3f6fc] hover:text-[#315ed0]"><LogOut size={17} /></button>}</div></header>
      {demo && <div className="flex items-center justify-between gap-3 border-b border-[#dfe8fb] bg-[#f0f5ff] px-5 py-2 text-xs text-[#5e75a3] sm:px-9"><span>You're viewing the university dashboard in demo mode.</span><Link to="/login" className="shrink-0 font-semibold text-[#315ed0]">Explore all roles <ArrowRight size={13} className="inline" /></Link></div>}
      {role === "student" || role === "lecturer" ? <RoleScreens role={role} section={section as RoleSection} onSectionChange={changeSection} hideNavigation /> : <AdminScreens role={role === "super_admin" ? "super-admin" : "department"} section={section as AdminSection} onSectionChange={changeSection} hideNavigation />}
    </div>
  </div>;
}

function App() {
  return <AuthProvider><BrowserRouter><Toaster richColors position="top-right" /><Routes><Route path="/login" element={<Login />} /><Route path="/forbidden" element={<div className="flex min-h-screen flex-col items-center justify-center bg-[#f6f8fc] text-[#263b60]"><ShieldCheck size={40} className="text-[#6384d8]" /><h1 className="mt-5 text-3xl font-bold">Forbidden</h1><p className="mt-2 text-sm text-slate-500">You don't have permission to view this page.</p><Link to="/" className="mt-6 text-sm font-semibold text-blue-600">Back to dashboard</Link></div>} /><Route path="/" element={<Dashboard role="super_admin" demo />} /><Route path="/:section" element={<Dashboard role="super_admin" demo />} />{(Object.entries(rolePath) as [UserRole, string][]).map(([role, path]) => <Route key={role} path={`${path}/*`} element={<RoleGuard role={role}><Routes><Route path="/" element={<Dashboard role={role} />} /><Route path=":section" element={<Dashboard role={role} />} /><Route path="*" element={<Navigate to={path} replace />} /></Routes></RoleGuard>} />)}</Routes></BrowserRouter></AuthProvider>;
}

createRoot(document.getElementById("root")!).render(<App />);
