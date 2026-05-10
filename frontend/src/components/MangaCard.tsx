"use client";

import { useState } from "react";
import Image from "next/image";
import Link from "next/link";
import type { Manga } from "@/lib/types";

const STATUS_STYLES: Record<string, string> = {
  ongoing: "bg-green-600/20 text-green-400 border-green-600/30",
  completed: "bg-blue-600/20 text-blue-400 border-blue-600/30",
  hiatus: "bg-yellow-600/20 text-yellow-400 border-yellow-600/30",
  cancelled: "bg-red-600/20 text-red-400 border-red-600/30",
};

const DEMOGRAPHIC_LABELS: Record<string, string> = {
  shounen: "Shounen",
  shoujo: "Shoujo",
  seinen: "Seinen",
  josei: "Josei",
};

const CONTENT_RATING_BADGE: Record<string, { label: string; style: string }> = {
  suggestive: { label: "16+", style: "bg-amber-500/80 text-white" },
  erotica: { label: "18+", style: "bg-red-500/80 text-white" },
  pornographic: { label: "18+", style: "bg-red-600/80 text-white" },
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

  const ratingBadge = CONTENT_RATING_BADGE[manga.contentRating];

  return (
    <Link
      href={`/manga/${manga.id}`}
      className="group block rounded-lg bg-md-surface border border-md-border overflow-hidden transition-all duration-200 hover:bg-md-surface-hover hover:border-md-accent/30 hover:shadow-lg hover:shadow-md-accent/5"
    >
      <div className="aspect-[3/4] bg-md-surface-hover overflow-hidden relative">
        {showPlaceholder ? (
          <PlaceholderIcon />
        ) : (
          <>
            <Image
              src={coverUrl}
              alt={manga.title}
              fill
              unoptimized
              sizes="(max-width: 640px) 50vw, (max-width: 1024px) 25vw, 200px"
              className="object-cover transition-transform duration-300 group-hover:scale-105"
              onError={() => setImgError(true)}
            />

            {/* Year badge - top left */}
            {manga.year && (
              <span className="absolute top-2 left-2 bg-black/70 text-white text-[11px] font-medium px-1.5 py-0.5 rounded leading-tight backdrop-blur-sm">
                {manga.year}
              </span>
            )}

            {/* Content rating badge - top right */}
            {ratingBadge && (
              <span className={`absolute top-2 right-2 text-[11px] font-bold px-1.5 py-0.5 rounded leading-tight ${ratingBadge.style}`}>
                {ratingBadge.label}
              </span>
            )}
          </>
        )}

        {/* Title overlay at bottom of cover */}
        <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/85 via-black/40 to-transparent pt-8 pb-1.5 px-2">
          <h3 className="text-xs sm:text-sm font-semibold text-white leading-snug line-clamp-2 group-hover:text-md-accent transition-colors drop-shadow-sm">
            {manga.title}
          </h3>
          <div className="flex items-center gap-1.5 mt-1">
            <span
              className={`text-[10px] font-medium px-1.5 py-0.5 rounded capitalize leading-tight ${
                STATUS_STYLES[manga.status] ?? "bg-gray-600/60 text-gray-200"
              }`}
            >
              {manga.status}
            </span>
            {manga.demographic && manga.demographic !== "none" && (
              <span className="text-[10px] font-medium px-1.5 py-0.5 rounded bg-white/15 text-white/90 capitalize leading-tight">
                {DEMOGRAPHIC_LABELS[manga.demographic] ?? manga.demographic}
              </span>
            )}
          </div>
        </div>
      </div>
    </Link>
  );
}
