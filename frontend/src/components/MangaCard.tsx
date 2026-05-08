"use client";

import { useState } from "react";
import type { Manga } from "@/lib/types";

const STATUS_STYLES: Record<string, string> = {
  ongoing: "bg-green-600/20 text-green-400 border-green-600/30",
  completed: "bg-blue-600/20 text-blue-400 border-blue-600/30",
  hiatus: "bg-yellow-600/20 text-yellow-400 border-yellow-600/30",
  cancelled: "bg-red-600/20 text-red-400 border-red-600/30",
};

function PlaceholderIcon() {
  return (
    <div className="w-full h-full flex items-center justify-center text-md-text-secondary">
      <svg
        className="w-12 h-12"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={1}
          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
        />
      </svg>
    </div>
  );
}

export default function MangaCard({ manga }: { manga: Manga }) {
  const [imgError, setImgError] = useState(false);
  const primaryCover =
    manga.coverArts?.find((c) => c.isPrimary) ?? manga.coverArts?.[0];
  const coverUrl = primaryCover ? `/uploads${primaryCover.imagePath}` : null;
  const showPlaceholder = !coverUrl || imgError;

  return (
    <a
      href={`/manga/${manga.id}`}
      className="group block rounded-lg bg-md-surface border border-md-border overflow-hidden transition-colors hover:bg-md-surface-hover"
    >
      <div className="aspect-[3/4] bg-md-surface-hover overflow-hidden">
        {showPlaceholder ? (
          <PlaceholderIcon />
        ) : (
          <img
            src={coverUrl}
            alt={manga.title}
            onError={() => setImgError(true)}
            className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
          />
        )}
      </div>
      <div className="p-3 space-y-2">
        <h3 className="text-sm font-medium text-md-text-primary line-clamp-2 leading-tight">
          {manga.title}
        </h3>
        <span
          className={`inline-block text-xs px-2 py-0.5 rounded-full border capitalize ${
            STATUS_STYLES[manga.status] ??
            "bg-gray-600/20 text-gray-400 border-gray-600/30"
          }`}
        >
          {manga.status}
        </span>
      </div>
    </a>
  );
}
