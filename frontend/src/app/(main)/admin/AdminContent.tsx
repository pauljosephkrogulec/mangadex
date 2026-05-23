"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/contexts/AuthContext";
import { adminApi } from "@/lib/api";
import MangaSection from "./sections/MangaSection";
import ChapterSection from "./sections/ChapterSection";
import UserSection from "./sections/UserSection";
import TagSection from "./sections/TagSection";
import CreatorSection from "./sections/CreatorSection";
import ScanlationGroupSection from "./sections/ScanlationGroupSection";

interface Stats {
  mangaCount: number;
  userCount: number;
  chapterCount: number;
}

const TABS = ["Manga", "Chapters", "Users", "Tags", "Creators", "Scanlation Groups"] as const;
type Tab = (typeof TABS)[number];

export default function AdminContent() {
  const router = useRouter();
  const { user, loading: authLoading } = useAuth();

  const [stats, setStats] = useState<Stats>({ mangaCount: 0, userCount: 0, chapterCount: 0 });
  const [statsLoading, setStatsLoading] = useState(true);
  const [statsError, setStatsError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<Tab>("Manga");

  useEffect(() => {
    if (authLoading) return;
    if (!user) { router.replace("/login"); return; }
    if (!user.roles.includes("ROLE_ADMIN")) { router.replace("/"); return; }

    async function fetchStats() {
      setStatsLoading(true);
      setStatsError(null);
      try {
        const data = await adminApi.stats();
        setStats(data);
      } catch (e) {
        setStatsError((e as Error).message);
      } finally {
        setStatsLoading(false);
      }
    }

    fetchStats();
  }, [user, authLoading, router]);

  if (authLoading || statsLoading) {
    return (
      <div className="max-w-content mx-auto px-6 md:px-8 py-6">
        <div className="animate-pulse space-y-6">
          <div className="h-8 w-48 bg-md-surface rounded" />
          <div className="grid grid-cols-3 gap-4">
            {[0, 1, 2].map((i) => <div key={i} className="h-24 bg-md-surface rounded-lg" />)}
          </div>
          <div className="h-64 bg-md-surface rounded-lg" />
        </div>
      </div>
    );
  }

  if (!user || !user.roles.includes("ROLE_ADMIN")) return null;

  if (statsError) {
    return (
      <div className="max-w-content mx-auto px-6 md:px-8 py-6">
        <p className="text-red-400">{statsError}</p>
      </div>
    );
  }

  return (
    <div className="max-w-content mx-auto px-6 md:px-8 py-6 space-y-6">
      <h1 className="text-2xl font-bold text-md-text-primary">Admin Panel</h1>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <StatCard label="Total Manga" value={stats.mangaCount} />
        <StatCard label="Total Users" value={stats.userCount} />
        <StatCard label="Total Chapters" value={stats.chapterCount} />
      </div>

      <div className="border-b border-md-border">
        <nav className="flex overflow-x-auto">
          {TABS.map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${
                activeTab === tab
                  ? "border-md-accent text-md-accent"
                  : "border-transparent text-md-text-secondary hover:text-md-text-primary hover:border-md-border"
              }`}
            >
              {tab}
            </button>
          ))}
        </nav>
      </div>

      {activeTab === "Manga" && <MangaSection />}
      {activeTab === "Chapters" && <ChapterSection />}
      {activeTab === "Users" && <UserSection />}
      {activeTab === "Tags" && <TagSection />}
      {activeTab === "Creators" && <CreatorSection />}
      {activeTab === "Scanlation Groups" && <ScanlationGroupSection />}
    </div>
  );
}

function StatCard({ label, value }: { label: string; value: number }) {
  return (
    <div className="bg-md-surface border border-md-border rounded-lg px-5 py-4">
      <p className="text-xs text-md-text-secondary uppercase tracking-widest mb-1">{label}</p>
      <p className="text-3xl font-bold text-md-text-primary">{value.toLocaleString()}</p>
    </div>
  );
}
