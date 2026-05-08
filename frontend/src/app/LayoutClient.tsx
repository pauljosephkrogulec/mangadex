"use client";

import { useCallback, useState } from "react";
import Navbar from "@/components/Navbar";
import Sidebar from "@/components/Sidebar";

export default function LayoutClient({ children }: { children: React.ReactNode }) {
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const toggleSidebar = useCallback(() => setSidebarOpen((p) => !p), []);
  const closeSidebar = useCallback(() => setSidebarOpen(false), []);

  return (
    <>
      <Navbar onToggleSidebar={toggleSidebar} />
      <Sidebar open={sidebarOpen} onClose={closeSidebar} />
      <main className="pt-14 min-h-screen">
        {children}
      </main>
    </>
  );
}
