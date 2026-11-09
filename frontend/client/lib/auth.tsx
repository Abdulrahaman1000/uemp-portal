import { createContext, useCallback, useContext, useEffect, useRef, useState } from "react";
import type { ReactNode } from "react";
import type { AuthSession, LoginCredentials, User } from "../types/portal";
import { api, getSession, setSession, subscribeToSession } from "./api";

export interface AuthContextValue {
  user: User | null;
  token: string | null;
  session: AuthSession | null;
  isAuthenticated: boolean;
  login: (credentials: LoginCredentials) => Promise<User>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

/** Wrap the app to share a persisted session. Authentication errors are exposed to callers. */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [session, updateSession] = useState<AuthSession | null>(getSession);
  const revision = useRef(0);

  useEffect(() => {
    const sync = () => {
      revision.current += 1;
      updateSession(getSession());
    };
    const unsubscribe = subscribeToSession(sync);
    // Catch a change that happened between render and subscription.
    updateSession(getSession());
    return unsubscribe;
  }, []);

  const login = useCallback(async (credentials: LoginCredentials) => {
    const requestRevision = ++revision.current;
    const next = await api.login(credentials);
    // A newer login, logout or session change supersedes this response.
    if (revision.current === requestRevision) setSession(next);
    return next.user;
  }, []);

  const logout = useCallback(async () => {
    revision.current += 1;
    // Start the remote request with the old token, but clear local state immediately.
    const request = api.logout();
    setSession(null);
    try { await request; } catch { /* Local logout must work offline as well. */ }
  }, []);

  return (
    <AuthContext.Provider value={{
      user: session?.user ?? null,
      token: session?.token ?? null,
      session,
      isAuthenticated: !!session,
      login,
      logout,
    }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) throw new Error("useAuth must be used within an AuthProvider");
  return context;
}
