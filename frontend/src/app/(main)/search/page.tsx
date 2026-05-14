import type { Metadata } from "next";
import { Suspense } from "react";
import SearchContent from "./SearchContent";

export const metadata: Metadata = {
  title: "Search Manga - MangaDex",
  description: "Search and filter manga by title, tags, status, and demographic",
};

function SearchFallback() {
  return (
    <div className="max-w-content mx-auto px-6 md:px-8 py-6">
      {/* Search bar skeleton */}
      <div className="mb-8">
        <div className="h-8 w-48 bg-md-surface rounded animate-pulse mb-4" />
        <div className="h-11 w-full bg-md-surface rounded-lg animate-pulse" />
      </div>

      <div className="flex gap-8">
        {/* Sidebar filters skeleton (hidden on mobile) */}
        <div className="w-64 shrink-0 hidden lg:block space-y-6">
          {Array.from({ length: 3 }).map((_, i) => (
            <div key={i} className="space-y-2">
              <div className="h-4 w-24 bg-md-surface rounded animate-pulse" />
              {Array.from({ length: 4 }).map((_, j) => (
                <div key={j} className="h-3.5 w-32 bg-md-surface rounded animate-pulse" />
              ))}
            </div>
          ))}
        </div>

        {/* Results grid skeleton */}
        <div className="flex-1">
          <div className="h-4 w-32 bg-md-surface rounded animate-pulse mb-4" />
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-4">
            {Array.from({ length: 20 }).map((_, i) => (
              <div key={i} className="aspect-[3/4] bg-md-surface rounded-lg animate-pulse" />
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

export default function SearchPage() {
  return (
    <Suspense fallback={<SearchFallback />}>
      <SearchContent />
    </Suspense>
  );
}
