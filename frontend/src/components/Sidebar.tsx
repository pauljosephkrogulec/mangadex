"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useCallback, useEffect, useRef } from "react";
import { useAuth } from "@/contexts/AuthContext";

export default function Sidebar({ open, onClose }: { open: boolean; onClose: () => void }) {
  return <SidebarInner open={open} onClose={onClose} />;
}

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

  const navGroups = [
    {
      title: "Main",
      links: [
        { href: "/", label: "Home", icon: "M" },
        { href: "/feed", label: "Feed", icon: "F" },
        { href: "/updates", label: "Updates", icon: "U" },
      ],
    },
    {
      title: "Account",
      links: [
        { href: "/follows", label: "Follows", icon: "H" },
        { href: "/library", label: "Library", icon: "L" },
        { href: "/history", label: "History", icon: "H" },
      ],
    },
    {
      title: "Titles",
      links: [
        { href: "/search", label: "Search", icon: "S" },
        { href: "/recent", label: "Recently Added", icon: "R" },
        { href: "/popular", label: "Popular", icon: "P" },
      ],
    },
    {
      title: "Community",
      links: [
        { href: "/forums", label: "Forums", icon: "F" },
        { href: "/groups", label: "Groups", icon: "G" },
        { href: "/users", label: "Users", icon: "U" },
      ],
    },
    {
      title: "Info",
      links: [
        { href: "/about", label: "About", icon: "I" },
        { href: "/support", label: "Support Us", icon: "S" },
        { href: "/rules", label: "Rules", icon: "R" },
      ],
    },
  ];

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
        className={`fixed top-0 left-0 h-full w-64 bg-md-background border-r border-md-border z-50 transform transition-transform duration-200 ease-in-out ${
          open ? "translate-x-0" : "-translate-x-full"
        }`}
      >
        <div className="flex items-center gap-3 h-14 px-4 border-b border-md-border">
          <Image src="/favicon.svg" alt="MangaDex" width={24} height={24} className="size-6" />
          <span className="font-bold text-lg text-md-text-primary">MangaDex</span>
        </div>

        <nav className="flex-1 overflow-y-auto p-3 space-y-5">
          {navGroups.map((group) => (
            <div key={group.title} className="space-y-1">
              <h3 className="px-3 text-xs font-semibold text-md-text-secondary uppercase tracking-widest">
                {group.title}
              </h3>
              {group.links.map((link) => {
                const isActive = pathname === link.href;
                return (
                  <Link
                    key={link.href}
                    href={link.href}
                    className={`flex items-center gap-3 px-3 py-2 text-sm rounded-lg transition-colors ${
                      isActive
                        ? "bg-md-accent/10 text-md-accent"
                        : "text-md-text-secondary hover:bg-md-surface-hover hover:text-md-text-primary"
                    }`}
                  >
                    <span className="w-6 h-6 rounded-md bg-md-surface border border-md-border flex items-center justify-center text-xs font-medium">
                      {link.icon}
                    </span>
                    {link.label}
                  </Link>
                );
              })}
            </div>
          ))}

          {!loading && (
            <div className="border-t border-md-border pt-3">
              {user ? (
                <div className="space-y-1">
                  <div className="px-3 py-2 flex items-center gap-3">
                    <span className="w-6 h-6 rounded-full bg-md-accent flex items-center justify-center text-xs font-bold text-white">
                      {user.username.charAt(0).toUpperCase()}
                    </span>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm text-md-text-primary truncate">{user.username}</p>
                      <p className="text-xs text-md-text-secondary truncate">{user.email}</p>
                    </div>
                  </div>
                  <button
                    onClick={handleLogout}
                    className="w-full flex items-center gap-3 px-3 py-2 text-sm text-red-400 rounded-lg hover:bg-md-surface-hover transition-colors"
                  >
                    <span className="w-6 h-6 rounded-md bg-md-surface border border-md-border flex items-center justify-center text-xs font-medium">L</span>
                    Sign Out
                  </button>
                </div>
              ) : (
                <div className="space-y-1">
                  <Link
                    href="/login"
                    className={`flex items-center gap-3 px-3 py-2 text-sm rounded-lg transition-colors ${
                      pathname === "/login"
                        ? "bg-md-accent/10 text-md-accent"
                        : "text-md-text-secondary hover:bg-md-surface-hover hover:text-md-text-primary"
                    }`}
                  >
                    <span className="w-6 h-6 rounded-md bg-md-surface border border-md-border flex items-center justify-center text-xs font-medium">L</span>
                    Sign In
                  </Link>
                  <Link
                    href="/register"
                    className={`flex items-center gap-3 px-3 py-2 text-sm rounded-lg transition-colors ${
                      pathname === "/register"
                        ? "bg-md-accent/10 text-md-accent"
                        : "text-md-text-secondary hover:bg-md-surface-hover hover:text-md-text-primary"
                    }`}
                  >
                    <span className="w-6 h-6 rounded-md bg-md-surface border border-md-border flex items-center justify-center text-xs font-medium">R</span>
                    Sign Up
                  </Link>
                </div>
              )}
            </div>
          )}
        </nav>

        <div className="p-4 border-t border-md-border space-y-3">
          <div className="flex justify-center gap-3">
            <a href="#" className="text-md-text-secondary hover:text-md-text-primary transition-colors p-1.5">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            </a>
            <a href="#" className="text-md-text-secondary hover:text-md-text-primary transition-colors p-1.5">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg>
            </a>
            <a href="#" className="text-md-text-secondary hover:text-md-text-primary transition-colors p-1.5">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.167 6.839 9.49.5.09.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.604-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.462-1.11-1.462-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.577.688.48C19.138 20.163 22 16.418 22 12c0-5.523-4.477-10-10-10z"/></svg>
            </a>
          </div>
          <p className="text-xs text-md-text-secondary/50 text-center">v1.0.0</p>
        </div>
      </aside>
    </>
  );
}
