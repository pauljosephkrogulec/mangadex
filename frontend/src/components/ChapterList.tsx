"use client";

import Link from "next/link";
import type { Chapter } from "@/lib/types";
import Pagination from "@/components/Pagination";

export type SortField = "volume" | "chapterNumber" | "title" | "createdAt";

interface ChapterListProps {
  chapters: Chapter[];
  totalItems: number;
  currentPage: number;
  totalPages: number;
  loading: boolean;
  error: string | null;
  sortField: SortField;
  sortDir: "asc" | "desc";
  chapterFrom: string;
  chapterTo: string;
  mangaId?: string;
  onSortChange: (field: SortField) => void;
  onPageChange: (page: number) => void;
  onRetry: () => void;
  onFilterChange: (from: string, to: string) => void;
}

const COLUMNS: { key: SortField | null; label: string; className: string }[] = [
  { key: "volume", label: "Vol.", className: "w-16" },
  { key: "chapterNumber", label: "Ch.", className: "w-20" },
  { key: "title", label: "Title", className: "flex-1" },
  { key: null, label: "Group", className: "w-36 hidden md:table-cell" },
  { key: "createdAt", label: "Date", className: "w-28 hidden sm:table-cell" },
];

function SortIcon({ active, direction }: { active: boolean; direction: "asc" | "desc" }) {
  return (
    <span className="inline-block ml-1.5 align-middle" aria-hidden="true">
      {active ? (
        direction === "asc" ? (
          <svg className="w-3 h-3" viewBox="0 0 12 12" fill="currentColor">
            <path d="M6 2l4 6H2z" />
          </svg>
        ) : (
          <svg className="w-3 h-3" viewBox="0 0 12 12" fill="currentColor">
            <path d="M6 10l4-6H2z" />
          </svg>
        )
      ) : (
        <svg className="w-3 h-3 opacity-30" viewBox="0 0 12 12" fill="currentColor">
          <path d="M6 2l4 6H2z" />
          <path d="M6 10l4-6H2z" className="hidden" />
        </svg>
      )}
    </span>
  );
}

function SkeletonRow() {
  return (
    <tr className="animate-pulse">
      <td className="px-3 py-3"><div className="h-4 w-10 bg-md-surface-hover rounded" /></td>
      <td className="px-3 py-3"><div className="h-4 w-12 bg-md-surface-hover rounded" /></td>
      <td className="px-3 py-3"><div className="h-4 w-40 bg-md-surface-hover rounded" /></td>
      <td className="px-3 py-3 hidden md:table-cell"><div className="h-4 w-24 bg-md-surface-hover rounded" /></td>
      <td className="px-3 py-3 hidden sm:table-cell"><div className="h-4 w-20 bg-md-surface-hover rounded" /></td>
    </tr>
  );
}

function formatDate(isoString: string): string {
  const date = new Date(isoString);
  return new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(date);
}

export default function ChapterList({
  chapters,
  totalItems,
  currentPage,
  totalPages,
  loading,
  error,
  sortField,
  sortDir,
  chapterFrom = "",
  chapterTo = "",
  mangaId,
  onSortChange,
  onPageChange,
  onRetry,
  onFilterChange = () => {},
}: ChapterListProps) {
  function handleHeaderClick(field: SortField) {
    onSortChange(field);
  }

  function renderHeader(key: SortField | null, label: string, className: string) {
    const isSortable = key !== null;
    const isActive = key === sortField;

    if (!isSortable) {
      return (
        <th
          key={label}
          scope="col"
          className={`px-3 py-3 text-left text-xs font-semibold text-md-text-secondary uppercase tracking-wider ${className}`}
        >
          {label}
        </th>
      );
    }

    return (
      <th
        key={label}
        scope="col"
        className={`px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider ${className}`}
      >
        <button
          onClick={() => handleHeaderClick(key)}
          className="inline-flex items-center text-md-text-secondary hover:text-md-text-primary transition-colors"
          aria-label={`Sort by ${label}${isActive ? ` (${sortDir === "asc" ? "ascending" : "descending"})` : ""}`}
        >
          {label}
          <SortIcon active={isActive} direction={isActive ? sortDir : "desc"} />
        </button>
      </th>
    );
  }

  return (
    <div>
      {/* Section header */}
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-lg font-semibold text-md-text-primary">
          Chapters{!loading && ` (${totalItems})`}
        </h2>
        {!loading && !error && chapters.length > 0 && (
          <div className="flex items-center gap-3">
            {/* Chapter range filter */}
            <div className="hidden sm:flex items-center gap-1">
              <input
                type="number"
                value={chapterFrom}
                onChange={(e) => onFilterChange(e.target.value, chapterTo)}
                placeholder="From"
                className="w-16 text-xs bg-md-surface border border-md-border rounded px-1.5 py-1 text-md-text-primary placeholder:text-md-text-secondary/50 focus:outline-none focus:ring-1 focus:ring-md-accent [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                aria-label="Filter from chapter"
              />
              <span className="text-xs text-md-text-secondary" aria-hidden="true">–</span>
              <input
                type="number"
                value={chapterTo}
                onChange={(e) => onFilterChange(chapterFrom, e.target.value)}
                placeholder="To"
                className="w-16 text-xs bg-md-surface border border-md-border rounded px-1.5 py-1 text-md-text-primary placeholder:text-md-text-secondary/50 focus:outline-none focus:ring-1 focus:ring-md-accent [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                aria-label="Filter to chapter"
              />
            </div>
            {/* Sort controls */}
            <div className="flex items-center gap-1.5">
              <label htmlFor="sort-select" className="text-xs text-md-text-secondary">
                Sort by
              </label>
              <select
                id="sort-select"
                value={sortField}
                onChange={(e) => onSortChange(e.target.value as SortField)}
                className="text-xs bg-md-surface border border-md-border rounded px-2 py-1 text-md-text-primary focus:outline-none focus:ring-1 focus:ring-md-accent"
                aria-label="Sort chapters by"
              >
                <option value="chapterNumber">Chapter</option>
                <option value="volume">Volume</option>
                <option value="title">Title</option>
                <option value="createdAt">Date</option>
              </select>
            </div>
            <button
              onClick={() => onSortChange(sortField)}
              className="p-1.5 rounded-md hover:bg-md-surface-hover transition-colors text-md-text-secondary"
              aria-label={`Switch to ${sortDir === "asc" ? "descending" : "ascending"} order`}
            >
              <svg className="w-3 h-3" viewBox="0 0 12 12" fill="currentColor">
                {sortDir === "asc" ? (
                  <path d="M6 2l4 6H2z" />
                ) : (
                  <path d="M6 10l4-6H2z" />
                )}
              </svg>
            </button>
          </div>
        )}
      </div>

      {/* Error state (hidden while loading) */}
      {!loading && error && (
        <div className="flex flex-col items-center justify-center py-10 text-center rounded-lg bg-md-surface border border-md-border">
          <svg
            className="w-10 h-10 text-md-text-secondary mb-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"
            />
          </svg>
          <p className="text-sm text-md-text-secondary mb-3">{error}</p>
          <button
            onClick={onRetry}
            className="px-4 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 transition-colors"
          >
            Retry
          </button>
        </div>
      )}

      {/* Loading skeleton */}
      {loading && (
        <div className="rounded-lg border border-md-border overflow-hidden">
          <table className="w-full">
            <thead>
              <tr className="bg-md-surface border-b border-md-border">
                {COLUMNS.map((col) => renderHeader(col.key, col.label, col.className))}
              </tr>
            </thead>
            <tbody className="bg-md-surface divide-y divide-md-border">
              {Array.from({ length: 5 }).map((_, i) => (
                <SkeletonRow key={i} />
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Empty state */}
      {!loading && !error && chapters.length === 0 && (
        <div className="flex flex-col items-center justify-center py-14 text-center rounded-lg bg-md-surface border border-md-border">
          <svg
            className="w-12 h-12 text-md-text-secondary/40 mb-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1}
              d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"
            />
          </svg>
          <p className="text-md-text-secondary text-sm">
            No chapters available yet.
          </p>
        </div>
      )}

      {/* Table */}
      {!loading && !error && chapters.length > 0 && (
        <div className="rounded-lg border border-md-border overflow-hidden">
          <table className="w-full">
            <thead>
              <tr className="bg-md-surface border-b border-md-border">
                {COLUMNS.map((col) => renderHeader(col.key, col.label, col.className))}
              </tr>
            </thead>
            <tbody className="bg-md-surface divide-y divide-md-border">
              {chapters.map((chapter) => (
                <tr
                  key={chapter.id}
                  className="hover:bg-md-surface-hover transition-colors"
                >
                  <td className="px-3 py-3 text-sm text-md-text-secondary">
                    {chapter.volume ?? "—"}
                  </td>
                  <td className="px-3 py-3 text-sm text-md-text-primary font-mono">
                    {chapter.chapterNumber}
                  </td>
                  <td className="px-3 py-3 text-sm text-md-text-primary">
                    <Link
                      href={mangaId ? `/manga/${mangaId}/chapter/${chapter.id}` : `/chapter/${chapter.id}`}
                      className="hover:text-md-accent transition-colors"
                    >
                      {chapter.title || "—"}
                    </Link>
                  </td>
                  <td className="px-3 py-3 text-sm text-md-text-secondary hidden md:table-cell">
                    {chapter.scanlationGroup?.name ?? "—"}
                  </td>
                  <td className="px-3 py-3 text-sm text-md-text-secondary hidden sm:table-cell whitespace-nowrap">
                    {formatDate(chapter.createdAt)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination */}
      {!loading && !error && totalPages > 1 && (
        <div className="mt-6">
          <Pagination
            currentPage={currentPage}
            totalPages={totalPages}
            onPageChange={onPageChange}
          />
        </div>
      )}
    </div>
  );
}
