"use client";

interface ReaderControlsProps {
  currentPage: number;
  totalPages: number;
  onPrevPage: () => void;
  onNextPage: () => void;
  onPrevChapter: (() => void) | null;
  onNextChapter: (() => void) | null;
  chapterTitle?: string | null;
  chapterNumber?: string;
}

export default function ReaderControls({
  currentPage,
  totalPages,
  onPrevPage,
  onNextPage,
  onPrevChapter,
  onNextChapter,
  chapterTitle,
  chapterNumber,
}: ReaderControlsProps) {
  return (
    <div className="sticky bottom-0 left-0 right-0 z-50 bg-black/80 backdrop-blur-sm border-t border-white/10">
      <div className="max-w-screen-xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
        {/* Prev chapter */}
        <div className="w-28 shrink-0">
          {onPrevChapter ? (
            <button
              onClick={onPrevChapter}
              className="flex items-center gap-1.5 text-sm text-md-text-secondary hover:text-md-accent transition-colors"
              aria-label="Previous chapter"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
              </svg>
              <span className="hidden sm:inline">Prev</span>
            </button>
          ) : (
            <span className="flex items-center gap-1.5 text-sm text-white/20 cursor-not-allowed">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
              </svg>
              <span className="hidden sm:inline">Prev</span>
            </span>
          )}
        </div>

        {/* Chapter info + page nav */}
        <div className="flex items-center gap-3">
          <span className="text-xs text-white/40 hidden md:block truncate max-w-40">
            Ch. {chapterNumber}{chapterTitle ? ` - ${chapterTitle}` : ""}
          </span>

          <button
            onClick={onPrevPage}
            disabled={currentPage <= 1}
            className="p-1.5 rounded-md text-white/60 hover:text-white hover:bg-white/10 transition-colors disabled:opacity-20 disabled:cursor-not-allowed disabled:hover:bg-transparent"
            aria-label="Previous page"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
          </button>

          <span className="text-sm text-white/80 font-medium tabular-nums min-w-20 text-center select-none">
            {currentPage} / {totalPages}
          </span>

          <button
            onClick={onNextPage}
            disabled={currentPage >= totalPages}
            className="p-1.5 rounded-md text-white/60 hover:text-white hover:bg-white/10 transition-colors disabled:opacity-20 disabled:cursor-not-allowed disabled:hover:bg-transparent"
            aria-label="Next page"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 5l7 7-7 7M5 5l7 7-7 7" />
            </svg>
          </button>
        </div>

        {/* Next chapter */}
        <div className="w-28 shrink-0 flex justify-end">
          {onNextChapter ? (
            <button
              onClick={onNextChapter}
              className="flex items-center gap-1.5 text-sm text-md-text-secondary hover:text-md-accent transition-colors"
              aria-label="Next chapter"
            >
              <span className="hidden sm:inline">Next</span>
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </button>
          ) : (
            <span className="flex items-center gap-1.5 text-sm text-white/20 cursor-not-allowed">
              <span className="hidden sm:inline">Next</span>
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </span>
          )}
        </div>
      </div>
    </div>
  );
}
