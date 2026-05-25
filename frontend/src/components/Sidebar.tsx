"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useCallback, useEffect, useRef } from "react";
import { useAuth } from "@/contexts/AuthContext";

export default function Sidebar({ open, onClose }: { open: boolean; onClose: () => void }) {
  return <SidebarInner open={open} onClose={onClose} />;
}

// ── Icons ─────────────────────────────────────────────────────────────────────

function IconHome() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
      <polyline points="9 22 9 12 15 12 15 22" />
    </svg>
  );
}

function IconSearch() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <circle cx="11" cy="11" r="8" />
      <line x1="21" y1="21" x2="16.65" y2="16.65" />
    </svg>
  );
}

function IconList() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <line x1="8" y1="6" x2="21" y2="6" />
      <line x1="8" y1="12" x2="21" y2="12" />
      <line x1="8" y1="18" x2="21" y2="18" />
      <line x1="3" y1="6" x2="3.01" y2="6" />
      <line x1="3" y1="12" x2="3.01" y2="12" />
      <line x1="3" y1="18" x2="3.01" y2="18" />
    </svg>
  );
}

function IconHeart() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
    </svg>
  );
}

function IconClock() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <circle cx="12" cy="12" r="10" />
      <polyline points="12 6 12 12 16 14" />
    </svg>
  );
}

function IconShield() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
    </svg>
  );
}

function IconLogOut() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" />
      <polyline points="16 17 21 12 16 7" />
      <line x1="21" y1="12" x2="9" y2="12" />
    </svg>
  );
}

function IconLogIn() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4" />
      <polyline points="10 17 15 12 10 7" />
      <line x1="15" y1="12" x2="3" y2="12" />
    </svg>
  );
}

function IconUserPlus() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <line x1="19" y1="8" x2="19" y2="14" />
      <line x1="22" y1="11" x2="16" y2="11" />
    </svg>
  );
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function NavLink({ href, icon, label, pathname }: { href: string; icon: React.ReactNode; label: string; pathname: string }) {
  const isActive = pathname === href;
  return (
    <Link
      href={href}
      className={`flex items-center gap-3 px-3 py-2 text-sm rounded-lg transition-colors ${
        isActive
          ? "bg-md-accent/10 text-md-accent"
          : "text-md-text-secondary hover:bg-md-surface-hover hover:text-md-text-primary"
      }`}
    >
      <span className={`shrink-0 ${isActive ? "text-md-accent" : "text-md-text-secondary"}`}>{icon}</span>
      {label}
    </Link>
  );
}

function NavGroup({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="space-y-0.5">
      <h3 className="px-3 pb-1 text-xs font-semibold text-md-text-secondary/60 uppercase tracking-widest">
        {title}
      </h3>
      {children}
    </div>
  );
}

// ── Sidebar ───────────────────────────────────────────────────────────────────

function SidebarInner({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { user, loading, logout } = useAuth();
  const pathname = usePathname();
  const sidebarRef = useRef<HTMLDivElement>(null);

  const handleLogout = useCallback(async () => {
    await logout();
  }, [logout]);

  useEffect(() => {
    const handleEsc = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    if (open) {
      document.addEventListener("keydown", handleEsc);
      document.body.style.overflow = "hidden";
    }
    return () => {
      document.removeEventListener("keydown", handleEsc);
      document.body.style.overflow = "";
    };
  }, [open, onClose]);

  useEffect(() => {
    onClose();
  }, [pathname, onClose]);

  return (
    <>
      {open && (
        <div
          className="fixed inset-0 bg-black/60 z-40 transition-opacity"
          onClick={onClose}
        />
      )}

      <aside
        ref={sidebarRef}
        className={`fixed top-0 left-0 h-full w-64 bg-md-background border-r border-md-border z-50 flex flex-col transform transition-transform duration-200 ease-in-out ${
          open ? "translate-x-0" : "-translate-x-full"
        }`}
      >
        {/* Header */}
        <div className="flex items-center gap-3 h-14 px-4 border-b border-md-border shrink-0">
          <Image src="/favicon.svg" alt="MangaDex" width={24} height={24} className="size-6" />
          <span className="font-bold text-lg text-md-text-primary">MangaDex</span>
        </div>

        {/* Nav */}
        <nav className="flex-1 overflow-y-auto p-3 space-y-4">
          <NavGroup title="Discover">
            <NavLink href="/" icon={<IconHome />} label="Home" pathname={pathname} />
            <NavLink href="/search" icon={<IconSearch />} label="Search" pathname={pathname} />
          </NavGroup>

          {!loading && user && (
            <NavGroup title="My Library">
              <NavLink href="/lists" icon={<IconList />} label="My Lists" pathname={pathname} />
              <NavLink href="/follows" icon={<IconHeart />} label="Follows" pathname={pathname} />
              <NavLink href="/history" icon={<IconClock />} label="Reading History" pathname={pathname} />
            </NavGroup>
          )}

          {!loading && user?.roles?.includes("ROLE_ADMIN") && (
            <NavGroup title="Admin">
              <NavLink href="/admin" icon={<IconShield />} label="Admin Panel" pathname={pathname} />
            </NavGroup>
          )}
        </nav>

        {/* Footer: auth */}
        <div className="shrink-0 border-t border-md-border p-3">
          {loading ? (
            <div className="h-10 rounded-lg bg-md-surface animate-pulse" />
          ) : user ? (
            <div className="space-y-0.5">
              <div className="flex items-center gap-3 px-3 py-2">
                <span className="size-7 shrink-0 rounded-full bg-md-accent flex items-center justify-center text-xs font-bold text-white">
                  {(user.username ?? "?").charAt(0).toUpperCase()}
                </span>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-md-text-primary truncate">{user.username}</p>
                  <p className="text-xs text-md-text-secondary truncate">{user.email}</p>
                </div>
              </div>
              <button
                onClick={handleLogout}
                className="w-full flex items-center gap-3 px-3 py-2 text-sm text-red-400 rounded-lg hover:bg-red-400/5 transition-colors"
              >
                <span className="shrink-0"><IconLogOut /></span>
                Sign Out
              </button>
            </div>
          ) : (
            <div className="space-y-0.5">
              <NavLink href="/login" icon={<IconLogIn />} label="Sign In" pathname={pathname} />
              <NavLink href="/register" icon={<IconUserPlus />} label="Create Account" pathname={pathname} />
            </div>
          )}
        </div>

        {/* Version */}
        <div className="shrink-0 px-4 py-2 border-t border-md-border">
          <p className="text-xs text-md-text-secondary/40 text-center">v1.0.0</p>
        </div>
      </aside>
    </>
  );
}
