"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useRouter, useSearchParams, usePathname } from "next/navigation";
import api, { handleResponse } from "@/lib/api";
import type { HydraCollection, Manga } from "@/lib/types";
import MangaCard from "@/components/MangaCard";
import MangaCardSkeleton from "@/components/MangaCardSkeleton";
import TagFilter from "@/components/TagFilter";
import Pagination from "@/components/Pagination";

const ITEMS_PER_PAGE = 20;

const STATUS_OPTIONS = [
  { value: "", label: "All" },
  { value: "ongoing", label: "Ongoing" },
  { value: "completed", label: "Completed" },
  { value: "hiatus", label: "Hiatus" },
  { value: "cancelled", label: "Cancelled" },
] as const;

const DEMOGRAPHIC_OPTIONS = [
  { value: "", label: "All" },
  { value: "shounen", label: "Shounen" },
  { value: "shoujo", label: "Shoujo" },
  { value: "seinen", label: "Seinen" },
  { value: "josei", label: "Josei" },
] as const;

export default function SearchContent() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  // ── Derive filter values from URL search params ──
  const query = searchParams.get("q") || "";
  const selectedTags = useMemo(
    () => [...searchParams.getAll("tags")].sort(),
    [searchParams],
  );
  const status = searchParams.get("status") || "";
  const demographic = searchParams.get("demographic") || "";
  const page = Math.max(1, parseInt(searchParams.get("page") || "1", 10) || 1);

  // Input field state (local, committed on submit)
  const [inputValue, setInputValue] = useState(query);

  // ── Data fetching state ──
  const [mangas, setMangas] = useState<Manga[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [retryCount, setRetryCount] = useState(0);

  const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE);

  // ── Build query string helper ──
  const buildUrl = useCallback(
    (overrides: Record<string, string | undefined>) => {
      const params = new URLSearchParams();

      const q = overrides.q !== undefined ? overrides.q : query;
      const tags =
        overrides.tags !== undefined
          ? overrides.tags
          : selectedTags.join(",");
      const s = overrides.status !== undefined ? overrides.status : status;
      const d =
        overrides.demographic !== undefined ? overrides.demographic : demographic;
      /* v8 ignore next */
      const p = overrides.page !== undefined ? overrides.page : String(page);

      if (q) params.set("q", q);
      if (s) params.set("status", s);
      if (d) params.set("demographic", d);
      if (p && p !== "1") params.set("page", p);

      // Tags: split string back to array for consistent appending
      const tagArray = tags ? tags.split(",").filter(Boolean) : [];
      tagArray.forEach((tag) => params.append("tags", tag));

      const qs = params.toString();
      return qs ? `${pathname}?${qs}` : pathname;
    },
    [pathname, query, selectedTags, status, demographic, page],
  );

  // ── Fetch manga ──
  useEffect(() => {
    let cancelled = false;

    async function fetchMangas() {
      setLoading(true);
      setError(null);

      const params = new URLSearchParams();
      params.set("include", "coverArt");
      params.set("itemsPerPage", String(ITEMS_PER_PAGE));
      params.set("page", String(page));
      params.set("order[createdAt]", "desc");

      if (query) params.set("title", query);
      if (status) params.set("status", status);
      if (demographic) params.set("demographic", demographic);
      selectedTags.forEach((tagId) => params.append("tags.id", tagId));

      const result = await handleResponse(
        api.get<HydraCollection<Manga>>(`/mangas?${params.toString()}`),
      );

      if (cancelled) return;

      if (result.success) {
        setMangas(result.data.member);
        setTotalItems(result.data.totalItems);
      } else {
        setError(result.error);
        setMangas([]);
        setTotalItems(0);
      }
      setLoading(false);
    }

    fetchMangas();
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [query, selectedTags.join(","), status, demographic, page, retryCount]);

  // ── Event handlers ──

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    const url = buildUrl({ q: inputValue, page: "1" });
    router.replace(url, { scroll: false });
  };

  const handleTagChange = (tags: string[]) => {
    const url = buildUrl({ tags: tags.join(","), page: "1" });
    router.replace(url, { scroll: false });
  };

  const handleStatusChange = (value: string) => {
    const url = buildUrl({ status: value || undefined, page: "1" });
    router.replace(url, { scroll: false });
  };

  const handleDemographicChange = (value: string) => {
    const url = buildUrl({
      demographic: value || undefined,
      page: "1",
    });
    router.replace(url, { scroll: false });
  };

  const handlePageChange = (newPage: number) => {
    const url = buildUrl({ page: String(newPage) });
    router.replace(url, { scroll: false });
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const handleClearFilters = () => {
    router.replace(pathname, { scroll: false });
  };

  const handleRetry = () => {
    setRetryCount((c) => c + 1);
  };

  // ── Mobile filter toggle ──

  const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);

  const hasActiveFilters = query || selectedTags.length > 0 || status || demographic;
  const activeFilterCount =
    (query ? 1 : 0) +
    selectedTags.length +
    (status ? 1 : 0) +
    (demographic ? 1 : 0);

  return (
    <div className="max-w-content mx-auto px-4 sm:px-6 md:px-8 py-6">
      {/* ── Search header ── */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-md-text-primary mb-4">
          Search Manga
        </h1>
        <form onSubmit={handleSearch} className="flex gap-2 sm:gap-3">
          <input
            type="text"
            value={inputValue}
            onChange={(e) => setInputValue(e.target.value)}
            placeholder="Search by title..."
            className="flex-1 min-w-0 px-4 py-2.5 rounded-lg bg-md-surface border border-md-border text-md-text-primary placeholder:text-md-text-secondary/50 focus:outline-none focus:border-md-accent focus:ring-1 focus:ring-md-accent/30 transition-colors"
            aria-label="Search manga by title"
          />
          <button
            type="submit"
            className="px-5 sm:px-6 py-2.5 rounded-lg bg-md-accent text-white font-medium hover:bg-md-accent/90 transition-colors disabled:opacity-50 shrink-0"
          >
            Search
          </button>
        </form>
      </div>

      {/* ── Mobile filter bar ── */}
      <div className="flex flex-wrap items-center gap-2 mb-4 lg:hidden">
        <select
          value={status}
          onChange={(e) => handleStatusChange(e.target.value)}
          className="px-3 py-1.5 rounded-lg bg-md-surface border border-md-border text-sm text-md-text-primary focus:outline-none focus:border-md-accent"
          aria-label="Filter by status"
        >
          {STATUS_OPTIONS.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>

        <select
          value={demographic}
          onChange={(e) => handleDemographicChange(e.target.value)}
          className="px-3 py-1.5 rounded-lg bg-md-surface border border-md-border text-sm text-md-text-primary focus:outline-none focus:border-md-accent"
          aria-label="Filter by demographic"
        >
          {DEMOGRAPHIC_OPTIONS.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>

        <button
          onClick={() => setMobileFiltersOpen((v) => !v)}
          className={`px-3 py-1.5 rounded-lg border text-sm font-medium transition-colors ${
            mobileFiltersOpen || selectedTags.length > 0
              ? "bg-md-accent/10 text-md-accent border-md-accent/30"
              : "bg-md-surface text-md-text-secondary border-md-border hover:border-md-accent/30"
          }`}
        >
          Tags
          {selectedTags.length > 0 && (
            <span className="ml-1.5 text-xs">({selectedTags.length})</span>
          )}
        </button>

        {hasActiveFilters && (
          <button
            onClick={handleClearFilters}
            className="px-3 py-1.5 rounded-lg text-sm text-md-text-secondary hover:text-md-accent transition-colors"
          >
            Clear
          </button>
        )}
      </div>

      {/* ── Mobile TagFilter (collapsible) ── */}
      {mobileFiltersOpen && (
        <div className="mb-4 lg:hidden p-4 rounded-lg bg-md-surface border border-md-border">
          <TagFilter selected={selectedTags} onChange={handleTagChange} />
        </div>
      )}

      {/* ── Main layout: sidebar + results ── */}
      <div className="flex gap-8">
        {/* ── Desktop sidebar filters ── */}
        <aside className="w-64 shrink-0 hidden lg:block space-y-6">
          <TagFilter selected={selectedTags} onChange={handleTagChange} />

          {/* Status filter */}
          <div>
            <h3 className="text-sm font-semibold text-md-text-primary uppercase tracking-wider mb-3">
              Status
            </h3>
            <div className="space-y-1">
              {STATUS_OPTIONS.map((opt) => (
                <button
                  key={opt.value}
                  onClick={() => handleStatusChange(opt.value)}
                  className={`w-full text-left px-3 py-1.5 rounded-md text-sm transition-colors ${
                    status === opt.value
                      ? "bg-md-accent/10 text-md-accent font-medium"
                      : "text-md-text-secondary hover:text-md-text-primary hover:bg-md-surface-hover"
                  }`}
                >
                  {opt.label}
                </button>
              ))}
            </div>
          </div>

          {/* Demographic filter */}
          <div>
            <h3 className="text-sm font-semibold text-md-text-primary uppercase tracking-wider mb-3">
              Demographic
            </h3>
            <div className="space-y-1">
              {DEMOGRAPHIC_OPTIONS.map((opt) => (
                <button
                  key={opt.value}
                  onClick={() => handleDemographicChange(opt.value)}
                  className={`w-full text-left px-3 py-1.5 rounded-md text-sm transition-colors ${
                    demographic === opt.value
                      ? "bg-md-accent/10 text-md-accent font-medium"
                      : "text-md-text-secondary hover:text-md-text-primary hover:bg-md-surface-hover"
                  }`}
                >
                  {opt.label}
                </button>
              ))}
            </div>
          </div>

          {/* Clear all filters */}
          {hasActiveFilters && (
            <button
              onClick={handleClearFilters}
              className="w-full px-3 py-2 text-sm text-md-text-secondary hover:text-md-accent transition-colors border border-md-border rounded-lg hover:border-md-accent/30"
            >
              Clear all filters
            </button>
          )}
        </aside>

        {/* ── Results area ── */}
        <div className="flex-1 min-w-0">
          {/* Results header */}
          <div className="flex items-center justify-between mb-4">
            <p className="text-sm text-md-text-secondary">
              {loading
                ? "Searching..."
                : error
                  ? "Something went wrong"
                  : `${totalItems} result${totalItems !== 1 ? "s" : ""}`}
            </p>

            {hasActiveFilters && (
              <p className="text-xs text-md-text-secondary hidden sm:block">
                {activeFilterCount} filter{activeFilterCount !== 1 ? "s" : ""} active
              </p>
            )}
          </div>

          {/* ── Error state ── */}
          {error && (
            <div className="flex flex-col items-center justify-center py-20 text-center">
              <svg
                className="w-12 h-12 text-md-text-secondary mb-4"
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
              <p className="text-md-text-secondary mb-4">{error}</p>
              <button
                onClick={handleRetry}
                className="px-5 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 transition-colors"
              >
                Try again
              </button>
            </div>
          )}

          {/* ── Results grid ── */}
          {!error && (
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-4 mb-8">
              {loading
                ? Array.from({ length: ITEMS_PER_PAGE }).map((_, i) => (
                    <MangaCardSkeleton key={i} />
                  ))
                : mangas.map((manga) => (
                    <MangaCard key={manga.id} manga={manga} />
                  ))}
            </div>
          )}

          {/* ── Empty state ── */}
          {!loading && !error && mangas.length === 0 && (
            <div className="flex flex-col items-center justify-center py-20 text-center">
              <svg
                className="w-16 h-16 text-md-text-secondary/40 mb-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={1}
                  d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"
                />
              </svg>
              <p className="text-lg text-md-text-secondary">
                No manga found
              </p>
              <p className="text-sm text-md-text-secondary mt-1.5 max-w-sm">
                Try adjusting your filters or search terms to find what you are
                looking for.
              </p>
              {hasActiveFilters && (
                <button
                  onClick={handleClearFilters}
                  className="mt-4 px-5 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 transition-colors"
                >
                  Clear all filters
                </button>
              )}
            </div>
          )}

          {/* ── Pagination ── */}
          {!loading && !error && totalPages > 1 && (
            <Pagination
              currentPage={page}
              totalPages={totalPages}
              onPageChange={handlePageChange}
            />
          )}
        </div>
      </div>
    </div>
  );
}
