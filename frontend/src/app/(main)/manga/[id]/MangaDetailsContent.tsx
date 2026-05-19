"use client";

import { useEffect, useState, useCallback, useMemo } from "react";
import Image from "next/image";
import Link from "next/link";
import { mangaApi, handleResponse } from "@/lib/api";
import { useAuth } from "@/contexts/AuthContext";
import type { Manga, Chapter } from "@/lib/types";
import ChapterList from "@/components/ChapterList";
import type { SortField } from "@/components/ChapterList";

const ITEMS_PER_PAGE = 100;

const STATUS_STYLES: Record<string, string> = {
  ongoing: "bg-green-600/20 text-green-400 border-green-600/30",
  completed: "bg-blue-600/20 text-blue-400 border-blue-600/30",
  hiatus: "bg-yellow-600/20 text-yellow-400 border-yellow-600/30",
  cancelled: "bg-red-600/20 text-red-400 border-red-600/30",
};

const CONTENT_RATING_BADGE: Record<string, { label: string; style: string }> = {
  suggestive: { label: "16+", style: "bg-amber-500/80 text-white" },
  erotica: { label: "18+", style: "bg-red-500/80 text-white" },
  pornographic: { label: "18+", style: "bg-red-600/80 text-white" },
};

const DEMOGRAPHIC_LABELS: Record<string, string> = {
  shounen: "Shounen",
  shoujo: "Shoujo",
  seinen: "Seinen",
  josei: "Josei",
};

function PlaceholderIcon() {
  return (
    <div className="w-full h-full flex items-center justify-center text-md-text-secondary">
      <svg
        className="w-16 h-16"
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

function MangaSkeleton() {
  return (
    <div className="flex flex-col md:flex-row gap-8 animate-pulse">
      <div className="w-full md:w-72 shrink-0">
        <div className="aspect-[3/4] bg-md-surface rounded-lg" />
      </div>
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
  );
}

function TagsList({ tags }: { tags: NonNullable<Manga["tags"]> }) {
  // Group tags by groupName
  const grouped = tags.reduce<Record<string, typeof tags>>((acc, tag) => {
    const group = tag.groupName;
    if (!acc[group]) acc[group] = [];
    acc[group].push(tag);
    return acc;
  }, {});

  return (
    <div className="space-y-3">
      {Object.entries(grouped).map(([groupName, groupTags]) => (
        <div key={groupName}>
          <h4 className="text-xs font-semibold text-md-text-secondary uppercase tracking-wider mb-1.5">
            {groupName}
          </h4>
          <div className="flex flex-wrap gap-1.5">
            {groupTags.map((tag) => (
              <span
                key={tag.id}
                className="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-md-accent/10 text-md-accent border border-md-accent/20"
              >
                {tag.name}
              </span>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}

// ── Client-side sort helpers ─────────────────────────────────────────────

function compareNumeric(a: string, b: string): number {
  return parseFloat(a) - parseFloat(b);
}

function compareNullableNumeric(a: string | null, b: string | null): number {
  if (a === null && b === null) return 0;
  if (a === null) return 1;
  if (b === null) return -1;
  return compareNumeric(a, b);
}

function compareNullableString(a: string | null, b: string | null): number {
  if (a === null && b === null) return 0;
  if (a === null) return 1;
  if (b === null) return -1;
  return a.localeCompare(b);
}

function compareDate(a: string, b: string): number {
  return new Date(a).getTime() - new Date(b).getTime();
}

interface MangaDetailsContentProps {
  id: string;
}

export default function MangaDetailsContent({ id }: MangaDetailsContentProps) {
  const { user } = useAuth();

  // ── Manga state ──
  const [manga, setManga] = useState<Manga | null>(null);
  const [mangaLoading, setMangaLoading] = useState(true);
  const [mangaError, setMangaError] = useState<string | null>(null);
  const [mangaRetryCount, setMangaRetryCount] = useState(0);
  const [imgError, setImgError] = useState(false);

  // ── Follow state ──
  const [followed, setFollowed] = useState(false);
  const [followActionLoading, setFollowActionLoading] = useState(false);
  const [followError, setFollowError] = useState<string | null>(null);

  // ── Chapters state ──
  const [chapters, setChapters] = useState<Chapter[]>([]);
  const [totalChapters, setTotalChapters] = useState(0);
  const [chaptersLoading, setChaptersLoading] = useState(true);
  const [chaptersError, setChaptersError] = useState<string | null>(null);
  const [chaptersRetryCount, setChaptersRetryCount] = useState(0);
  const [chapterPage, setChapterPage] = useState(1);
  const [chapterFrom, setChapterFrom] = useState("");
  const [chapterTo, setChapterTo] = useState("");
  const [sort, setSort] = useState<{ field: SortField; dir: "asc" | "desc" }>({
    field: "chapterNumber",
    dir: "desc",
  });

  const totalChapterPages = Math.ceil(totalChapters / ITEMS_PER_PAGE);

  // ── Description expand ──
  const [descriptionExpanded, setDescriptionExpanded] = useState(false);

  // ── Handle filter change ──
  const handleFilterChange = useCallback((from: string, to: string) => {
    setChapterFrom(from);
    setChapterTo(to);
  }, []);

  // ── Handle sort change (client-side only) ──
  const handleSortChange = useCallback((field: SortField) => {
    setSort((prev) => {
      if (prev.field === field) {
        // Toggle direction
        return { field, dir: prev.dir === "asc" ? "desc" : "asc" };
      }
      // Different field: start with ascending for volume/chapter, descending for date
      return { field, dir: field === "createdAt" ? "desc" : "asc" };
    });
  }, []);

  // ── Fetch manga ──
  useEffect(() => {
    let cancelled = false;

    async function fetchManga() {
      setMangaLoading(true);
      setMangaError(null);
      setImgError(false);

      const result = await handleResponse(
        mangaApi.get(id, { include: "creators,tags,coverArt" }),
      );

      if (cancelled) return;

      if (result.success) {
        setManga(result.data);
      } else {
        setMangaError(result.error);
      }
      setMangaLoading(false);
    }

    fetchManga();
    return () => {
      cancelled = true;
    };
  }, [id, mangaRetryCount]);

  // ── Fetch follow status ──
  useEffect(() => {
    if (!user) return;

    let cancelled = false;

    async function fetchFollowStatus() {
      setFollowError(null);

      const result = await handleResponse(mangaApi.followStatus(id));

      if (cancelled) return;

      if (result.success) {
        setFollowed(result.data.following);
      } else {
        setFollowError(result.error);
      }
    }

    fetchFollowStatus();
    return () => {
      cancelled = true;
    };
  }, [id, user]);

  // ── Handle follow/unfollow ──
  const handleFollowToggle = useCallback(async () => {
    setFollowActionLoading(true);
    setFollowError(null);

    const result = followed
      ? await handleResponse(mangaApi.unfollow(id))
      : await handleResponse(mangaApi.follow(id));

    if (result.success) {
      setFollowed(!followed);
    } else {
      setFollowError(result.error);
    }
    setFollowActionLoading(false);
  }, [id, followed]);

  // ── Update document title ──
  useEffect(() => {
    if (manga) {
      document.title = `${manga.title} - MangaDex`;
    }
  }, [manga]);

  // ── Fetch chapters (no sort params — sort happens client-side) ──
  useEffect(() => {
    let cancelled = false;

    async function fetchChapters() {
      setChaptersLoading(true);
      setChaptersError(null);

      const params: Record<string, string | number> = {
        page: chapterPage,
        itemsPerPage: ITEMS_PER_PAGE,
      };

      const result = await handleResponse(
        mangaApi.feed(id, params),
      );

      if (cancelled) return;

      if (result.success) {
        setChapters(result.data.member);
        setTotalChapters(result.data.totalItems);
      } else {
        setChaptersError(result.error);
        setChapters([]);
        setTotalChapters(0);
      }
      setChaptersLoading(false);
    }

    fetchChapters();
    return () => {
      cancelled = true;
    };
  }, [id, chapterPage, chaptersRetryCount]);

  // ── Client-side sort + filter ──
  const sortedChapters = useMemo(() => {
    let result = chapters;

    // Apply range filter
    const from = chapterFrom !== "" ? parseFloat(chapterFrom) : null;
    const to = chapterTo !== "" ? parseFloat(chapterTo) : null;
    if (from !== null || to !== null) {
      result = result.filter((ch) => {
        const num = parseFloat(ch.chapterNumber);
        if (from !== null && num < from) return false;
        if (to !== null && num > to) return false;
        return true;
      });
    }

    // Sort
    const sorted = [...result];
    sorted.sort((a, b) => {
      let cmp = 0;
      switch (sort.field) {
        case "volume":
          cmp = compareNullableNumeric(a.volume, b.volume);
          break;
        case "chapterNumber":
          cmp = compareNumeric(a.chapterNumber, b.chapterNumber);
          break;
        case "title":
          cmp = compareNullableString(a.title, b.title);
          break;
        case "createdAt":
          cmp = compareDate(a.createdAt, b.createdAt);
          break;
      }
      return sort.dir === "desc" ? -cmp : cmp;
    });
    return sorted;
  }, [chapters, sort, chapterFrom, chapterTo]);

  const displayTotalItems = (chapterFrom !== "" || chapterTo !== "")
    ? sortedChapters.length
    : totalChapters;

  // ── Find primary cover ──
  const primaryCover =
    manga?.coverArts?.find((c) => c.isPrimary) ?? manga?.coverArts?.[0];
  const coverUrl = primaryCover ? `/uploads${primaryCover.imagePath}` : null;
  const showPlaceholder = !coverUrl || imgError;

  // ── Retry handlers ──
  const handleMangaRetry = () => setMangaRetryCount((c) => c + 1);
  const handleChaptersRetry = () => setChaptersRetryCount((c) => c + 1);
  const handleChapterPageChange = (page: number) => setChapterPage(page);

  // ── Render states ──

  // Manga error (no manga data at all)
  if (!mangaLoading && mangaError && !manga) {
    return (
      <div className="max-w-content mx-auto px-6 md:px-8 py-6">
        <div className="flex flex-col items-center justify-center min-h-[40vh] text-center">
          <svg
            className="w-16 h-16 text-md-text-secondary mb-4"
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
          <p className="text-lg text-md-text-secondary mb-1">
            {mangaError.includes("404") || mangaError.includes("Not Found")
              ? "Manga not found"
              : "Failed to load manga"}
          </p>
          <p className="text-sm text-md-text-secondary mb-4">{mangaError}</p>
          <button
            onClick={handleMangaRetry}
            className="px-5 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 transition-colors"
          >
            Try again
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-content mx-auto px-6 md:px-8 py-6">
      {/* ── Manga details ── */}
      {mangaLoading ? (
        <MangaSkeleton />
      ) : mangaError ? (
        // Manga error with retry (but we somehow have manga data from a previous render)
        <div className="flex flex-col items-center justify-center py-10 text-center">
          <p className="text-md-text-secondary mb-2">{mangaError}</p>
          <button
            onClick={handleMangaRetry}
            className="px-4 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 transition-colors"
          >
            Retry
          </button>
        </div>
      ) : manga ? (
        <>
          {/* ── Cover + Info layout ── */}
          <div className="flex flex-col md:flex-row gap-6 md:gap-8">
            {/* Cover */}
            <div className="w-full md:w-72 shrink-0">
              <div className="relative aspect-[3/4] bg-md-surface-hover rounded-lg overflow-hidden">
                {showPlaceholder ? (
                  <PlaceholderIcon />
                ) : (
                  <Image
                    src={coverUrl!}
                    alt={manga.title}
                    fill
                    unoptimized
                    sizes="(max-width: 768px) 100vw, 288px"
                    className="object-cover"
                    onError={() => setImgError(true)}
                  />
                )}
              </div>
            </div>

            {/* Info panel */}
            <div className="flex-1 min-w-0 space-y-4">
              {/* Title */}
              <h1 className="text-2xl md:text-3xl font-bold text-md-text-primary break-words">
                {manga.title}
              </h1>

              {/* Alt titles */}
              {manga.altTitles && manga.altTitles.length > 0 && (
                <p className="text-sm text-md-text-secondary italic">
                  {manga.altTitles.join(", ")}
                </p>
              )}

              {/* Badge row */}
              <div className="flex flex-wrap items-center gap-2">
                {/* Status */}
                <span
                  className={`text-xs font-medium px-2.5 py-1 rounded-full capitalize leading-tight border ${
                    STATUS_STYLES[manga.status] ??
                    "bg-gray-600/20 text-gray-400 border-gray-600/30"
                  }`}
                >
                  {manga.status}
                </span>

                {/* Year */}
                {manga.year && (
                  <span className="text-xs font-medium px-2.5 py-1 rounded-full bg-md-surface-hover text-md-text-secondary border border-md-border">
                    {manga.year}
                  </span>
                )}

                {/* Demographic */}
                {manga.demographic && manga.demographic !== "none" && (
                  <span className="text-xs font-medium px-2.5 py-1 rounded-full bg-white/10 text-white/80 border border-white/10">
                    {DEMOGRAPHIC_LABELS[manga.demographic] ??
                      manga.demographic}
                  </span>
                )}

                {/* Content rating */}
                {CONTENT_RATING_BADGE[manga.contentRating] && (
                  <span
                    className={`text-xs font-bold px-2.5 py-1 rounded-full leading-tight ${
                      CONTENT_RATING_BADGE[manga.contentRating].style
                    }`}
                  >
                    {CONTENT_RATING_BADGE[manga.contentRating].label}
                  </span>
                )}

                {/* Follow button */}
                {user ? (
                  <button
                    onClick={handleFollowToggle}
                    disabled={followActionLoading}
                    className={`group inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1 rounded-full leading-tight border transition-all ${
                      followed
                        ? "bg-red-600/20 text-red-400 border-red-600/30 hover:bg-red-600/30 hover:text-red-300"
                        : "bg-md-accent/10 text-md-accent border-md-accent/20 hover:bg-md-accent/20"
                    } disabled:opacity-50 disabled:cursor-not-allowed`}
                  >
                    {followActionLoading ? (
                      <span className="w-3 h-3 border border-current border-t-transparent rounded-full animate-spin" />
                    ) : followed ? (
                      <>
                        <svg className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                          <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
                        </svg>
                        <span className="group-hover:hidden">Following</span>
                        <span className="hidden group-hover:inline">Unfollow</span>
                      </>
                    ) : (
                      <>
                        <svg className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                          <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
                        </svg>
                        Follow
                      </>
                    )}
                  </button>
                ) : (
                  <Link
                    href="/login"
                    className="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1 rounded-full leading-tight border border-md-border bg-md-surface text-md-text-secondary hover:text-md-text-primary hover:bg-md-surface-hover transition-colors"
                  >
                    <svg className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
                    </svg>
                    Sign in to Follow
                  </Link>
                )}

                {/* Follow error */}
                {followError && (
                  <span className="text-xs text-red-400 ml-1">{followError}</span>
                )}
              </div>

              {/* Creators */}
              {manga.creators && manga.creators.length > 0 && (
                <div className="flex flex-wrap gap-x-6 gap-y-1 text-sm text-md-text-secondary">
                  {(["author", "artist"] as const).map((type) => {
                    const people = manga.creators!.filter(
                      (c) => c.type === type,
                    );
                    if (people.length === 0) return null;
                    return (
                      <span key={type}>
                        <span className="font-medium text-md-text-primary capitalize">
                          {type}
                          {people.length > 1 ? "s" : ""}:
                        </span>{" "}
                        {people.map((c) => c.name).join(", ")}
                      </span>
                    );
                  })}
                </div>
              )}

              {/* Description */}
              {manga.description ? (
                <div>
                  <p
                    className={`text-sm text-md-text-secondary leading-relaxed whitespace-pre-line ${
                      !descriptionExpanded ? "line-clamp-6" : ""
                    }`}
                  >
                    {manga.description}
                  </p>
                  {manga.description.length > 300 && (
                    <button
                      onClick={() =>
                        setDescriptionExpanded((v) => !v)
                      }
                      className="mt-1 text-xs text-md-accent hover:text-md-accent/80 transition-colors font-medium"
                    >
                      {descriptionExpanded
                        ? "Show less"
                        : "Show more"}
                    </button>
                  )}
                </div>
              ) : (
                <p className="text-sm text-md-text-secondary italic">
                  No description available.
                </p>
              )}

              {/* Tags */}
              {manga.tags && manga.tags.length > 0 && (
                <div className="pt-2">
                  <TagsList tags={manga.tags} />
                </div>
              )}
            </div>
          </div>

          {/* ── Divider ── */}
          <hr className="my-8 border-md-border" />

          {/* ── Chapter list (client-side sorted) ── */}
          <ChapterList
            chapters={sortedChapters}
            totalItems={displayTotalItems}
            currentPage={chapterPage}
            totalPages={totalChapterPages}
            loading={chaptersLoading}
            error={chaptersError}
            sortField={sort.field}
            sortDir={sort.dir}
            chapterFrom={chapterFrom}
            chapterTo={chapterTo}
            mangaId={id}
            onSortChange={handleSortChange}
            onPageChange={handleChapterPageChange}
            onRetry={handleChaptersRetry}
            onFilterChange={handleFilterChange}
          />
        </>
      ) : null}
    </div>
  );
}
