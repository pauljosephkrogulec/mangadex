"use client";

import Link from "next/link";
import { useReadingHistory } from "@/hooks/useReadingHistory";
import { useAuth } from "@/contexts/AuthContext";

function formatDate(isoString: string): string {
  const date = new Date(isoString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

  if (diffDays === 0) {
    return `Today at ${date.toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit" })}`;
  }
  if (diffDays === 1) {
    return `Yesterday at ${date.toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit" })}`;
  }
  if (diffDays < 7) {
    return `${diffDays} days ago`;
  }
  return date.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: date.getFullYear() !== now.getFullYear() ? "numeric" : undefined,
  });
}

export default function HistoryContent() {
  const { user } = useAuth();
  const { history, clearHistory } = useReadingHistory();

  if (!user) {
    return (
      <div className="max-w-content mx-auto px-6 md:px-8 py-6">
        <div className="flex flex-col items-center justify-center min-h-[40vh] text-center">
          <p className="text-md-text-secondary text-lg mb-2">Sign in to view your reading history</p>
          <Link
            href="/login"
            className="px-5 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 transition-colors"
          >
            Sign In
          </Link>
        </div>
      </div>
    );
  }

  if (history.length === 0) {
    return (
      <div className="max-w-content mx-auto px-6 md:px-8 py-6">
        <h1 className="text-2xl font-bold text-md-text-primary mb-6">Reading History</h1>
        <div className="flex flex-col items-center justify-center py-14 text-center rounded-lg bg-md-surface border border-md-border">
          <svg className="w-12 h-12 text-md-text-secondary/40 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
          </svg>
          <p className="text-md-text-secondary text-sm">No reading history yet.</p>
          <Link
            href="/search"
            className="mt-3 px-4 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 transition-colors"
          >
            Browse Manga
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-content mx-auto px-6 md:px-8 py-6">
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-md-text-primary">Reading History</h1>
        <button
          onClick={clearHistory}
          className="text-sm text-red-400 hover:text-red-300 transition-colors px-3 py-1.5 rounded-lg border border-red-400/30 hover:bg-red-600/10"
        >
          Clear History
        </button>
      </div>

      <div className="rounded-lg border border-md-border overflow-hidden">
        <table className="w-full">
          <thead>
            <tr className="bg-md-surface border-b border-md-border">
              <th scope="col" className="px-4 py-3 text-left text-xs font-semibold text-md-text-secondary uppercase tracking-wider">Manga</th>
              <th scope="col" className="px-4 py-3 text-left text-xs font-semibold text-md-text-secondary uppercase tracking-wider">Chapter</th>
              <th scope="col" className="px-4 py-3 text-left text-xs font-semibold text-md-text-secondary uppercase tracking-wider w-36 hidden sm:table-cell">Read At</th>
            </tr>
          </thead>
          <tbody className="bg-md-surface divide-y divide-md-border">
            {history.map((entry) => (
              <tr key={entry.chapterId} className="hover:bg-md-surface-hover transition-colors">
                <td className="px-4 py-3 text-sm">
                  <Link
                    href={`/manga/${entry.mangaId}`}
                    className="text-md-text-primary hover:text-md-accent transition-colors font-medium"
                  >
                    {entry.mangaTitle || "Untitled"}
                  </Link>
                </td>
                <td className="px-4 py-3 text-sm">
                  <Link
                    href={`/manga/${entry.mangaId}/chapter/${entry.chapterId}`}
                    className="text-md-text-secondary hover:text-md-accent transition-colors"
                  >
                    {entry.mangaTitle ? `Ch. ${entry.chapterNumber}` : `Ch. ${entry.chapterNumber}`}
                  </Link>
                </td>
                <td className="px-4 py-3 text-sm text-md-text-secondary hidden sm:table-cell whitespace-nowrap">
                  {formatDate(entry.readAt)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
