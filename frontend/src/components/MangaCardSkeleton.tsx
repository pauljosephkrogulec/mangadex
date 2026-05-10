"use client";

export default function MangaCardSkeleton() {
  return (
    <div className="rounded-lg bg-md-surface border border-md-border overflow-hidden animate-pulse">
      <div className="aspect-[3/4] bg-md-surface-hover" />
      <div className="p-3 space-y-2">
        <div className="h-4 bg-md-surface-hover rounded w-3/4" />
        <div className="h-5 bg-md-surface-hover rounded-full w-16" />
      </div>
    </div>
  );
}
