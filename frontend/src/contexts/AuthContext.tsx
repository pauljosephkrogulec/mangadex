"use client";

import { createContext, useCallback, useContext, useEffect, useRef, useState } from "react";
import api, { authApi, registerLogoutCallback } from "@/lib/api";
import type { LoginRequest, LoginResponse, User, UserRegistrationRequest } from "@/lib/types";

interface AuthContextType {
  user: User | null;
  loading: boolean;
  error: string | null;
  login: (data: LoginRequest) => Promise<LoginResponse>;
  register: (data: UserRegistrationRequest) => Promise<User>;
  logout: () => Promise<void>;
  clearError: () => void;
}

const AuthContext = createContext<AuthContextType | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const initialCheckDone = useRef(false);

  useEffect(() => {
    registerLogoutCallback(() => {
      setUser(null);
      window.location.href = "/login";
    });
  }, []);

  useEffect(() => {
    if (initialCheckDone.current) return;
    initialCheckDone.current = true;

    authApi.me()
      .then(({ data }) => setUser(data))
      .catch(() => setUser(null))
      .finally(() => setLoading(false));
  }, []);

  const fetchUser = useCallback(async () => {
    try {
      const { data } = await authApi.me();
      setUser(data);
      return data;
    } catch {
      setUser(null);
      return null;
    }
  }, []);

  const login = useCallback(async (data: LoginRequest) => {
    const response = await authApi.login(data);
    if (response.data.user) {
      setUser(response.data.user);
    } else {
      await fetchUser();
    }
    setError(null);
    return response.data;
  }, [fetchUser]);

  const register = useCallback(async (data: UserRegistrationRequest) => {
    const createdUser = await authApi.register(data);
    await login({ email: data.email, password: data.password });
    return createdUser.data;
  }, [login]);

  const logout = useCallback(async () => {
    try {
      await api.post("/logout");
    } catch {
      // ignore
    }
    setUser(null);
  }, []);

  const clearError = useCallback(() => setError(null), []);

  return (
    <AuthContext.Provider value={{ user, loading, error, login, register, logout, clearError }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextType {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}
