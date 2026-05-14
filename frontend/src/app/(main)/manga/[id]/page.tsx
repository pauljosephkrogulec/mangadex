import type { Metadata } from "next";
import { Suspense } from "react";
import MangaDetailsContent from "./MangaDetailsContent";

export const metadata: Metadata = {
  title: "Manga - MangaDex",
  description: "View manga details and chapter list",
};

function DetailsSkeleton() {
  return (
    <div className="max-w-content mx-auto px-6 md:px-8 py-6 animate-pulse">
      {/* Two-column layout skeleton */}
      <div className="flex flex-col md:flex-row gap-8">
        {/* Cover skeleton */}
        <div className="w-full md:w-72 shrink-0">
          <div className="aspect-[3/4] bg-md-surface rounded-lg" />
        </div>

        {/* Details skeleton */}
        <div className="flex-1 space-y-4">
          <div className="h-8 w-3/4 bg-md-surface rounded" />
          <div className="h-4 w-1/2 bg-md-surface rounded" />
          <div className="flex gap-2">
            <div className="h-6 w-20 bg-md-surface rounded-full" />
            <div className="h-6 w-16 bg-md-surface rounded-full" />
            <div className="h-6 w-24 bg-md-surface rounded-full" />
          </div>
          <div className="h-4 w-32 bg-md-surface rounded" />
          <div className="space-y-2 pt-4">
            <div className="h-4 w-full bg-md-surface rounded" />
            <div className="h-4 w-5/6 bg-md-surface rounded" />
            <div className="h-4 w-4/6 bg-md-surface rounded" />
          </div>
          <div className="flex flex-wrap gap-2 pt-4">
            {Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="h-6 w-16 bg-md-surface rounded-full" />
            ))}
          </div>
        </div>
      </div>

      {/* Chapter section skeleton */}
      <div className="mt-10">
        <div className="h-6 w-40 bg-md-surface rounded mb-4" />
        <div className="rounded-lg border border-md-border overflow-hidden">
          <table className="w-full">
            <thead>
              <tr className="bg-md-surface border-b border-md-border">
                {Array.from({ length: 5 }).map((_, i) => (
                  <th key={i} className="px-3 py-3">
                    <div className="h-4 w-16 bg-md-surface-hover rounded" />
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="bg-md-surface divide-y divide-md-border">
              {Array.from({ length: 5 }).map((_, i) => (
                <tr key={i}>
                  {Array.from({ length: 5 }).map((_, j) => (
                    <td key={j} className="px-3 py-3">
                      <div className="h-4 w-full bg-md-surface-hover rounded" />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

export default async function MangaPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return (
    <Suspense fallback={<DetailsSkeleton />}>
      <MangaDetailsContent id={id} />
    </Suspense>
  );
}
