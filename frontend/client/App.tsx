import "./global.css";
import type { ReactNode } from "react";
import { createRoot } from "react-dom/client";
import { BrowserRouter, Link, Navigate, Route, Routes } from "react-router-dom";
import { ShieldCheck } from "lucide-react";
import { Toaster } from "sonner";
import { AuthProvider, useAuth } from "./lib/auth";
import { rolePath } from "./lib/navigation";
import type { UserRole } from "./types/portal";
import Login from "./pages/Login";
import NotFound from "./pages/NotFound";
import DashboardShell from "./components/layout/DashboardShell";

/** Sends "/" to the signed-in user's own dashboard, or to the login page. */
function Home() {
  const { user } = useAuth();
  return <Navigate to={user ? rolePath[user.role] : "/login"} replace />;
}

/** Only the matching role may see these routes; other roles go to their own area. */
function RoleGuard({ role, children }: { role: UserRole; children: ReactNode }) {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (user.role !== role) return <Navigate to={rolePath[user.role]} replace />;
  return <>{children}</>;
}

function Forbidden() {
  return <div className="flex min-h-screen flex-col items-center justify-center bg-[#f6f8fc] text-[#263b60]">
    <ShieldCheck size={40} className="text-[#6384d8]" />
    <h1 className="mt-5 text-3xl font-bold">Forbidden</h1>
    <p className="mt-2 text-sm text-slate-500">You don't have permission to view this page.</p>
    <Link to="/" className="mt-6 text-sm font-semibold text-blue-600">Back to my dashboard</Link>
  </div>;
}

function App() {
  const roles = Object.entries(rolePath) as [UserRole, string][];
  return <AuthProvider>
    <BrowserRouter>
      <Toaster richColors position="top-right" />
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/login" element={<Login />} />
        <Route path="/forbidden" element={<Forbidden />} />
        {roles.map(([role, path]) => <Route key={role} path={`${path}/*`} element={
          <RoleGuard role={role}>
            <Routes>
              <Route path="/" element={<DashboardShell role={role} />} />
              <Route path=":section" element={<DashboardShell role={role} />} />
              <Route path="*" element={<Navigate to={path} replace />} />
            </Routes>
          </RoleGuard>
        } />)}
        <Route path="*" element={<NotFound />} />
      </Routes>
    </BrowserRouter>
  </AuthProvider>;
}

createRoot(document.getElementById("root")!).render(<App />);
