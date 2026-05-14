"use client";

import { useCallback, useState } from "react";
import type { Chapter } from "@/lib/types";

interface ReaderSidebarProps {
  open: boolean;
  pinned: boolean;
  onClose: () => void;
  onPinToggle: () => void;
  onPageChange: (page: number) => void;
  onChapterChange: (chapterId: string) => void;
  chapters: Chapter[];
  currentChapterId: string;
  chapterNumber?: string;
  chapterTitle?: string | null;
  volume?: string | null;
  language?: string;
  scanlationGroup?: string | null;
  currentPage: number;
  totalPages: number;
}

export default function ReaderSidebar({
  open,
  pinned,
  onClose,
  onPinToggle,
  onPageChange,
  onChapterChange,
  chapters,
  currentChapterId,
  chapterNumber,
  chapterTitle,
  volume,
  language,
  scanlationGroup,
  currentPage,
  totalPages,
}: ReaderSidebarProps) {
  const [fitMode, setFitMode] = useState<"width" | "height" | "both">("width");
  const [headerShown, setHeaderShown] = useState(false);

  const handleChapterSelect = useCallback(
    (e: React.ChangeEvent<HTMLSelectElement>) => {
      onChapterChange(e.target.value);
    },
    [onChapterChange],
  );

  const handlePageSelect = useCallback(
    (e: React.ChangeEvent<HTMLSelectElement>) => {
      onPageChange(Number(e.target.value));
    },
    [onPageChange],
  );

  const handleProgressClick = useCallback(
    (e: React.MouseEvent<HTMLDivElement>) => {
      const rect = e.currentTarget.getBoundingClientRect();
      const ratio = (e.clientX - rect.left) / rect.width;
      const page = Math.max(1, Math.min(totalPages, Math.round(ratio * totalPages)));
      onPageChange(page);
    },
    [totalPages, onPageChange],
  );

  const progressPercent = totalPages > 0 ? (currentPage / totalPages) * 100 : 0;

  return (
    <>
      {open && !pinned && (
        <div className="fixed inset-0 z-40" onClick={onClose} />
      )}

      {open && (
        <div
          className={`fixed top-0 right-0 z-50 h-full w-80 bg-[#1a1a1a] border-l border-white/[0.08] flex flex-col ${
            pinned ? "" : "shadow-2xl shadow-black/60"
          }`}
        >
          {/* Header */}
          <div className="flex items-center justify-between px-4 h-12 border-b border-white/[0.08] shrink-0">
            <span className="text-xs text-white/40 uppercase tracking-wider font-medium">
              Menu
            </span>
            <div className="flex items-center gap-0.5">
              <button
                onClick={onPinToggle}
                className={`p-1.5 rounded-md transition-colors ${
                  pinned
                    ? "text-white bg-white/[0.1]"
                    : "text-white/30 hover:text-white hover:bg-white/[0.06]"
                }`}
                aria-label={pinned ? "Unpin sidebar" : "Pin sidebar"}
              >
                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z" />
                </svg>
              </button>
              <button
                onClick={onClose}
                className="p-1.5 rounded-md text-white/30 hover:text-white hover:bg-white/[0.06] transition-colors"
                aria-label="Close sidebar"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          {/* Body */}
          <div className="flex-1 overflow-y-auto">
            {/* Chapter info */}
            <div className="px-4 pt-4 pb-3 border-b border-white/[0.08]">
              <h2 className="text-sm text-white/90 font-medium leading-tight">
                {chapterTitle
                  ? `Ch. ${chapterNumber} - ${chapterTitle}`
                  : chapterNumber
                    ? `Chapter ${chapterNumber}`
                    : ""}
              </h2>
              {volume && (
                <p className="text-xs text-white/40 mt-1">Vol. {volume}</p>
              )}
              <div className="flex items-center gap-2 text-xs text-white/30 mt-1">
                {language && <span>{language}</span>}
                {scanlationGroup && (
                  <>
                    <span>·</span>
                    <span>{scanlationGroup}</span>
                  </>
                )}
              </div>
            </div>

            {/* Chapter selector */}
            {chapters.length > 1 && (
              <div className="px-4 pt-4 pb-3 border-b border-white/[0.08]">
                <div className="text-[11px] text-white/30 uppercase tracking-wider mb-1.5 font-medium">
                  Chapter
                </div>
                <select
                  aria-label="Chapter"
                  value={currentChapterId}
                  onChange={handleChapterSelect}
                  className="w-full bg-black/30 border border-white/[0.1] rounded px-2.5 py-1.5 text-sm text-white/80 focus:outline-none focus:border-white/30 appearance-none cursor-pointer"
                >
                  {chapters.map((ch) => (
                    <option key={ch.id} value={ch.id}>
                      {ch.volume ? `Vol.${ch.volume} ` : ""}Ch. {ch.chapterNumber}
                      {ch.title ? ` - ${ch.title}` : ""}
                    </option>
                  ))}
                </select>
              </div>
            )}

            {/* Page selector + progress */}
            <div className="px-4 pt-4 pb-3 border-b border-white/[0.08]">
              <div className="text-[11px] text-white/30 uppercase tracking-wider mb-1.5 font-medium">
                Page
              </div>
              <select
                aria-label="Page"
                value={currentPage}
                onChange={handlePageSelect}
                className="w-full bg-black/30 border border-white/[0.1] rounded px-2.5 py-1.5 text-sm text-white/80 focus:outline-none focus:border-white/30 appearance-none cursor-pointer"
              >
                {Array.from({ length: totalPages }, (_, i) => i + 1).map(
                  (p) => (
                    <option key={p} value={p}>
                      {p}
                    </option>
                  ),
                )}
              </select>

              <div
                className="relative h-2 bg-black/40 rounded-full mt-3 cursor-pointer group"
                onClick={handleProgressClick}
              >
                <div
                  className="absolute top-0 left-0 h-full bg-white/20 rounded-full transition-all pointer-events-none"
                  style={{ width: `${progressPercent}%` }}
                />
                <div
                  className="absolute top-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full shadow-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"
                  style={{ left: `calc(${progressPercent}% - 6px)` }}
                />
              </div>
              <div className="flex justify-between text-[11px] text-white/20 mt-1 tabular-nums">
                <span>1</span>
                <span data-testid="progress-current-page">{currentPage}</span>
                <span>{totalPages}</span>
              </div>
            </div>

            {/* Quick settings */}
            <div className="px-4 pt-4 pb-3 border-b border-white/[0.08]">
              <div className="text-[11px] text-white/30 uppercase tracking-wider mb-3 font-medium">
                Quick Settings
              </div>

              {/* Page fit */}
              <div className="mb-3">
                <div className="text-xs text-white/50 mb-2">Image Fit</div>
                <div className="flex gap-1">
                  {(["width", "height", "both"] as const).map((mode) => (
                    <button
                      key={mode}
                      onClick={() => setFitMode(mode)}
                      className={`flex-1 text-xs py-1.5 rounded border transition-colors ${
                        fitMode === mode
                          ? "bg-white/[0.1] border-white/20 text-white/80"
                          : "bg-black/30 border-white/[0.06] text-white/40 hover:text-white/60 hover:border-white/10"
                      }`}
                    >
                      {mode === "width" ? "Fit Width" : mode === "height" ? "Fit Height" : "Fit Both"}
                    </button>
                  ))}
                </div>
              </div>

              {/* Header toggle */}
              <div className="flex items-center justify-between py-1">
                <span className="text-xs text-white/50">Show Header</span>
                <button
                  role="switch"
                  aria-label="Show Header"
                  aria-checked={headerShown}
                  onClick={() => setHeaderShown((v) => !v)}
                  className={`relative w-8 h-4 rounded-full transition-colors ${
                    headerShown ? "bg-white/30" : "bg-white/10"
                  }`}
                >
                  <span
                    className={`absolute top-0.5 left-0.5 w-3 h-3 bg-white rounded-full transition-transform ${
                      headerShown ? "translate-x-4" : "translate-x-0"
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* Reader Settings link */}
            <div className="px-4 py-3">
              <button className="w-full text-xs text-white/30 hover:text-white/60 transition-colors text-left">
                Reader Settings
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
