"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { useAuth } from "@/contexts/AuthContext";

function validateEmail(email: string): string | null {
  if (!email) return "Email is required";
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return "Enter a valid email address";
  return null;
}

function validateUsername(username: string): string | null {
  if (!username) return "Username is required";
  if (username.length < 3) return "Username must be at least 3 characters";
  if (!/^[a-zA-Z0-9_]+$/.test(username)) return "Username can only contain letters, numbers, and underscores";
  return null;
}

function validatePassword(password: string): string | null {
  if (!password) return "Password is required";
  if (password.length < 8) return "Password must be at least 8 characters";
  return null;
}

function validateConfirmPassword(password: string, confirmPassword: string): string | null {
  if (!confirmPassword) return "Please confirm your password";
  if (password !== confirmPassword) return "Passwords do not match";
  return null;
}

export default function RegisterPage() {
  const router = useRouter();
  const { user, loading: authLoading, register, clearError } = useAuth();
  const emailRef = useRef<HTMLInputElement>(null);
  const [email, setEmail] = useState("");
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [serverError, setServerError] = useState<string | null>(null);

  useEffect(() => {
    emailRef.current?.focus();
  }, []);

  useEffect(() => {
    if (user && !authLoading) {
      router.replace("/");
    }
  }, [user, authLoading, router]);

  if (authLoading) {
    return (
      <div className="flex min-h-[calc(100vh-3.5rem)] items-center justify-center">
        <div className="animate-spin w-6 h-6 border-2 border-md-accent border-t-transparent rounded-full" />
      </div>
    );
  }

  if (user) {
    return null;
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setServerError(null);
    clearError();

    const emailErr = validateEmail(email);
    const usernameErr = validateUsername(username);
    const passwordErr = validatePassword(password);
    const confirmErr = validateConfirmPassword(password, confirmPassword);

    if (emailErr || usernameErr || passwordErr || confirmErr) {
      setFieldErrors({
        email: emailErr ?? "",
        username: usernameErr ?? "",
        password: passwordErr ?? "",
        confirmPassword: confirmErr ?? "",
      });
      return;
    }
    setFieldErrors({});

    setSubmitting(true);
    try {
      await register({ email, username, password });
      router.replace("/");
    } catch (err) {
      setServerError(err instanceof Error ? err.message : "Registration failed");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-[calc(100vh-3.5rem)] items-center justify-center px-4">
      <div className="w-full max-w-sm">
        <div className="rounded-xl bg-md-surface border border-md-border p-8">
          <h1 className="text-2xl font-bold text-center mb-2">Create Account</h1>
          <p className="text-sm text-md-text-secondary text-center mb-8">
            Join the MangaDex community
          </p>

          {serverError && (
            <div className="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-sm text-red-400">
              {serverError}
            </div>
          )}

          <form onSubmit={handleSubmit} noValidate className="space-y-4">
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-md-text-secondary mb-1.5">
                Email
              </label>
              <input
                ref={emailRef}
                id="email"
                type="email"
                autoComplete="email"
                required
                value={email}
                onChange={(e) => { setEmail(e.target.value); setFieldErrors((p) => ({ ...p, email: "" })); }}
                className={`w-full px-3 py-2 bg-md-background border rounded-lg text-sm text-md-text-primary placeholder-md-text-secondary/50 focus:outline-none transition-colors ${
                  fieldErrors.email ? "border-red-500 focus:border-red-500" : "border-md-border focus:border-md-accent"
                }`}
                placeholder="you@example.com"
              />
              {fieldErrors.email && <p className="mt-1 text-xs text-red-400">{fieldErrors.email}</p>}
            </div>

            <div>
              <label htmlFor="username" className="block text-sm font-medium text-md-text-secondary mb-1.5">
                Username
              </label>
              <input
                id="username"
                type="text"
                autoComplete="username"
                required
                value={username}
                onChange={(e) => { setUsername(e.target.value); setFieldErrors((p) => ({ ...p, username: "" })); }}
                className={`w-full px-3 py-2 bg-md-background border rounded-lg text-sm text-md-text-primary placeholder-md-text-secondary/50 focus:outline-none transition-colors ${
                  fieldErrors.username ? "border-red-500 focus:border-red-500" : "border-md-border focus:border-md-accent"
                }`}
                placeholder="your_username"
              />
              {fieldErrors.username && <p className="mt-1 text-xs text-red-400">{fieldErrors.username}</p>}
            </div>

            <div>
              <label htmlFor="password" className="block text-sm font-medium text-md-text-secondary mb-1.5">
                Password
              </label>
              <div className="relative">
                <input
                  id="password"
                  type={showPassword ? "text" : "password"}
                  autoComplete="new-password"
                  required
                  value={password}
                  onChange={(e) => { setPassword(e.target.value); setFieldErrors((p) => ({ ...p, password: "", confirmPassword: "" })); }}
                  className={`w-full px-3 py-2 pr-10 bg-md-background border rounded-lg text-sm text-md-text-primary placeholder-md-text-secondary/50 focus:outline-none transition-colors ${
                    fieldErrors.password ? "border-red-500 focus:border-red-500" : "border-md-border focus:border-md-accent"
                  }`}
                  placeholder="••••••••"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((p) => !p)}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-md-text-secondary hover:text-md-text-primary transition-colors p-1"
                  aria-label={showPassword ? "Hide password" : "Show password"}
                >
                  {showPassword ? (
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94" />
                      <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19" />
                      <line x1="1" y1="1" x2="23" y2="23" />
                    </svg>
                  ) : (
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                  )}
                </button>
              </div>
              {fieldErrors.password && <p className="mt-1 text-xs text-red-400">{fieldErrors.password}</p>}
            </div>

            <div>
              <label htmlFor="confirmPassword" className="block text-sm font-medium text-md-text-secondary mb-1.5">
                Confirm Password
              </label>
              <input
                id="confirmPassword"
                type={showPassword ? "text" : "password"}
                autoComplete="new-password"
                required
                value={confirmPassword}
                onChange={(e) => { setConfirmPassword(e.target.value); setFieldErrors((p) => ({ ...p, confirmPassword: "" })); }}
                className={`w-full px-3 py-2 bg-md-background border rounded-lg text-sm text-md-text-primary placeholder-md-text-secondary/50 focus:outline-none transition-colors ${
                  fieldErrors.confirmPassword ? "border-red-500 focus:border-red-500" : "border-md-border focus:border-md-accent"
                }`}
                placeholder="••••••••"
              />
              {fieldErrors.confirmPassword && <p className="mt-1 text-xs text-red-400">{fieldErrors.confirmPassword}</p>}
            </div>

            <button
              type="submit"
              disabled={submitting}
              className="w-full py-2.5 rounded-lg bg-md-accent text-white font-medium text-sm hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center justify-center gap-2"
            >
              {submitting && (
                <span className="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full" />
              )}
              {submitting ? "Creating account..." : "Create Account"}
            </button>
          </form>

          <p className="mt-6 text-center text-sm text-md-text-secondary">
            Already have an account?{" "}
            <Link href="/login" className="text-md-accent hover:underline">
              Sign in
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
