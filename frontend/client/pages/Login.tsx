import { useState, type FormEvent } from "react";
import { Navigate, useNavigate } from "react-router-dom";
import { ArrowRight, CheckCircle2, Eye, EyeOff, GraduationCap } from "lucide-react";
import { useAuth } from "../lib/auth";
import { USE_MOCK } from "../lib/api";
import { MOCK_PASSWORD, mockUsers } from "../lib/mock";
import { roleName, rolePath } from "../lib/navigation";

const fieldClass = "w-full rounded-lg border border-[#dce3ef] bg-white px-4 text-sm outline-none focus:border-[#5477db] focus:ring-2 focus:ring-[#e4ebff]";

export default function Login() {
  const { login, user } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  if (user) return <Navigate to={rolePath[user.role]} replace />;

  async function submit(event: FormEvent) {
    event.preventDefault();
    setError("");
    setLoading(true);
    try {
      const next = await login({ email, password });
      navigate(rolePath[next.role], { replace: true });
    } catch (err) {
      setError(err instanceof Error ? err.message : "Unable to sign in. Please try again.");
    } finally {
      setLoading(false);
    }
  }

  return <div className="min-h-screen bg-[#f5f7fc] text-[#182745] lg:grid lg:grid-cols-[1fr_1fr]">
    <div className="relative hidden overflow-hidden bg-[#112756] px-14 py-12 text-white lg:flex lg:flex-col xl:px-20">
      <div className="flex items-center gap-3">
        <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-[#244fc1]"><GraduationCap size={25} /></span>
        <span className="text-xl font-bold tracking-tight">northstar<span className="text-[#88a6ff]">.</span></span>
      </div>
      <div className="relative z-10 my-auto max-w-lg">
        <p className="mb-5 text-xs font-bold uppercase tracking-[.2em] text-[#9db7ff]">A better way to learn, together</p>
        <h1 className="text-5xl font-semibold leading-[1.13] tracking-[-.04em]">A brighter future starts here.</h1>
        <p className="mt-6 max-w-md text-base leading-7 text-[#c3cee8]">One connected place for your university community to learn, teach, and achieve more.</p>
        <div className="mt-10 flex items-center gap-3 text-sm text-[#d3dcf1]"><CheckCircle2 size={18} className="text-[#a7bcff]" /> Your academic journey, all in one place</div>
      </div>
      <div className="relative z-10 text-xs text-[#a9b9d8]">© Northstar University · Education management portal</div>
      <div className="absolute -bottom-52 -right-36 h-[600px] w-[600px] rounded-full border border-white/10" />
      <div className="absolute -bottom-32 -right-16 h-[440px] w-[440px] rounded-full border border-white/10" />
      <div className="absolute -bottom-12 right-4 h-[280px] w-[280px] rounded-full border border-white/10" />
    </div>

    <div className="flex items-center justify-center px-5 py-12">
      <div className="w-full max-w-[430px]">
        <div className="mb-10 flex items-center gap-2 text-lg font-bold lg:hidden"><GraduationCap className="text-[#315dd2]" /> northstar.</div>
        <div className="mb-8">
          <p className="mb-2 text-xs font-bold uppercase tracking-[.16em] text-[#5575cf]">Welcome back</p>
          <h1 className="text-3xl font-semibold tracking-tight">Sign in to your portal</h1>
          <p className="mt-2 text-sm text-[#8290a9]">Enter your details to continue to Northstar.</p>
        </div>
        <form onSubmit={submit} className="space-y-5">
          <label className="block text-sm font-semibold">Email address
            <input required type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="you@northstar.edu" className={`${fieldClass} mt-2 h-12`} />
          </label>
          <label className="block text-sm font-semibold">Password
            <div className="relative mt-2">
              <input required type={showPassword ? "text" : "password"} autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="Enter your password" className={`${fieldClass} h-12 pr-12`} />
              <button type="button" onClick={() => setShowPassword(!showPassword)} aria-label={showPassword ? "Hide password" : "Show password"} className="absolute right-4 top-3.5 text-[#8c9ab2]">{showPassword ? <EyeOff size={19} /> : <Eye size={19} />}</button>
            </div>
          </label>
          {error && <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}
          <button disabled={loading} className="flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-[#315ed0] text-sm font-semibold text-white hover:bg-[#234db8] disabled:opacity-60">
            {loading ? "Signing in…" : "Sign in"} {!loading && <ArrowRight size={16} />}
          </button>
        </form>

        {/* Demo accounts exist only in mock mode, never against the real API. */}
        {USE_MOCK && <div className="mt-8 rounded-xl border border-[#dfe7f6] bg-white p-5">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold">Demo credentials</h2>
            <span className="rounded-full bg-[#eef3ff] px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-[#5373c8]">Demo mode</span>
          </div>
          <p className="mt-1 text-xs text-[#91a0b7]">Choose an account to fill in the form.</p>
          <div className="mt-4 space-y-2">
            {mockUsers.map((account) => <button key={account.id} type="button" onClick={() => { setEmail(account.email); setPassword(MOCK_PASSWORD); }} className="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-xs hover:bg-[#f4f7ff]">
              <span className="font-semibold text-[#536581]">{roleName[account.role]}</span>
              <span className="text-[#8c9bb2]">{account.email} / {MOCK_PASSWORD}</span>
            </button>)}
          </div>
        </div>}
      </div>
    </div>
  </div>;
}
