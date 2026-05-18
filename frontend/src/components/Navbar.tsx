"use client";

import Image from "next/image";
import Link from "next/link";
import { useCallback, useEffect, useRef, useState } from "react";
import { useAuth } from "@/contexts/AuthContext";

export default function Navbar({ onToggleSidebar }: { onToggleSidebar: () => void }) {
  const { user, logout } = useAuth();
  const searchRef = useRef<HTMLInputElement>(null);
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key === "k") {
        e.preventDefault();
        searchRef.current?.focus();
      }
    };
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, []);

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setDropdownOpen(false);
      }
    };
    if (dropdownOpen) {
      document.addEventListener("mousedown", handleClickOutside);
    }
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [dropdownOpen]);

  const handleLogout = useCallback(async () => {
    setDropdownOpen(false);
    await logout();
  }, [logout]);

  return (
    <nav className="fixed top-0 left-0 right-0 z-50 bg-md-background/80 backdrop-blur-md border-b border-md-border">
      <div className="max-w-content mx-auto flex items-center h-14 px-4 gap-4">
        <button
          onClick={onToggleSidebar}
          className="p-2 -ml-2 text-md-text-secondary hover:text-md-text-primary transition-colors"
          aria-label="Toggle navigation"
        >
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M2 5h16M2 10h16M2 15h16" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
          </svg>
        </button>

        <Link href="/" className="flex items-center gap-2 shrink-0">
          <Image src="/favicon.svg" alt="MangaDex" width={24} height={24} className="size-6" />
          <span className="font-bold text-lg hidden sm:inline">MangaDex</span>
        </Link>

        <div className="flex-1 flex justify-end items-center gap-3">
          <div className="relative w-full max-w-xs">
            <svg
              className="absolute left-3 top-1/2 -translate-y-1/2 text-md-text-secondary"
              width="14" height="14" viewBox="0 0 14 14" fill="none"
            >
              <circle cx="6.5" cy="6.5" r="4.75" stroke="currentColor" strokeWidth="1.5"/>
              <path d="M10 10l3 3" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"/>
            </svg>
            <input
              ref={searchRef}
              type="text"
              placeholder="Search"
              className="w-full pl-9 pr-16 py-1.5 bg-md-surface border border-md-border rounded-lg text-sm text-md-text-primary placeholder-md-text-secondary/50 focus:outline-none focus:border-md-accent"
            />
            <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-md-text-secondary/50 bg-md-surface-hover px-1.5 py-0.5 rounded">
              Ctrl+K
            </span>
          </div>

          <button aria-label="Notifications" className="p-2 text-md-text-secondary hover:text-md-text-primary transition-colors">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
              <path d="M9 1.5a5.5 5.5 0 00-5.5 5.5v2.5l-1.5 3h14l-1.5-3V7A5.5 5.5 0 009 1.5z" stroke="currentColor" strokeWidth="1.5"/>
              <path d="M6.5 13a2.5 2.5 0 005 0" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"/>
            </svg>
          </button>

          {user ? (
            <div ref={dropdownRef} className="relative">
              <button
                onClick={() => setDropdownOpen((p) => !p)}
                className="w-8 h-8 rounded-full bg-md-accent flex items-center justify-center text-sm font-bold text-white hover:opacity-90 transition-opacity"
                aria-label="User profile"
              >
                {user.username.charAt(0).toUpperCase()}
              </button>

              {dropdownOpen && (
                <div className="absolute right-0 top-full mt-2 w-48 rounded-xl bg-md-surface border border-md-border shadow-xl py-2">
                  <div className="px-4 py-2 border-b border-md-border">
                    <p className="text-sm font-medium text-md-text-primary">{user.username}</p>
                    <p className="text-xs text-md-text-secondary truncate">{user.email}</p>
                  </div>
                  <Link
                    href="/profile"
                    className="block px-4 py-2 text-sm text-md-text-secondary hover:text-md-text-primary hover:bg-md-surface-hover transition-colors"
                    onClick={() => setDropdownOpen(false)}
                  >
                    Profile
                  </Link>
                  <button
                    onClick={handleLogout}
                    className="w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-md-surface-hover transition-colors"
                  >
                    Sign Out
                  </button>
                </div>
              )}
            </div>
          ) : (
            <div className="flex items-center gap-2">
              <Link
                href="/login"
                className="px-3 py-1.5 text-sm text-md-text-secondary hover:text-md-text-primary transition-colors"
              >
                Sign In
              </Link>
              <Link
                href="/register"
                className="px-3 py-1.5 text-sm bg-md-accent text-white rounded-lg hover:opacity-90 transition-opacity"
              >
                Sign Up
              </Link>
            </div>
          )}
        </div>
      </div>
    </nav>
  );
}
