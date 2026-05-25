"use client";

import { useEffect, useRef, useState } from "react";
import { useMangaList } from "@/lib/hooks";
import MangaCard from "./MangaCard";
import MangaCardSkeleton from "./MangaCardSkeleton";

interface MangaSectionProps {
  title: string;
  subtitle?: string;
  apiParams: Record<string, string | number>;
  cols?: 2 | 3 | 4 | 5 | 6;
  limit?: number;
  variant?: "grid" | "scroll";
}

const gridCols: Record<number, string> = {
  2: "grid-cols-2",
  3: "grid-cols-2 sm:grid-cols-3",
  4: "grid-cols-2 sm:grid-cols-3 lg:grid-cols-4",
  5: "grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5",
  6: "grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6",
};

const scrollCardWidth: Record<number, string> = {
  2: "w-[45vw] sm:w-[30vw] md:w-[22vw] lg:w-[18vw] xl:w-[15vw]",
  3: "w-[40vw] sm:w-[28vw] md:w-[20vw] lg:w-[16vw] xl:w-[13vw]",
  4: "w-[40vw] sm:w-[25vw] md:w-[18vw] lg:w-[14vw] xl:w-[12vw]",
  5: "w-[38vw] sm:w-[22vw] md:w-[16vw] lg:w-[13vw] xl:w-[11vw]",
  6: "w-[35vw] sm:w-[20vw] md:w-[15vw] lg:w-[12vw] xl:w-[10vw]",
};

export default function MangaSection({
  title,
  subtitle,
  apiParams,
  cols = 5,
  limit = 10,
  variant = "grid",
}: MangaSectionProps) {
  const params = Object.fromEntries(
    Object.entries(apiParams).map(([k, v]) => [k, String(v)]),
  );
  const { mangas: allMangas, isLoading: loading, error } = useMangaList(params);
  const mangas = allMangas.slice(0, limit);
  const scrollRef = useRef<HTMLDivElement>(null);
  const [canScrollLeft, setCanScrollLeft] = useState(false);
  const [canScrollRight, setCanScrollRight] = useState(false);
  const [, setHasOverflow] = useState(false);

  // ── Scroll arrow visibility ──
  const updateScrollButtons = () => {
    const el = scrollRef.current;
    if (!el) return;
    const overflow = el.scrollWidth > el.clientWidth + 4;
    setHasOverflow(overflow);
    setCanScrollLeft(el.scrollLeft > 4);
    setCanScrollRight(el.scrollLeft < el.scrollWidth - el.clientWidth - 4);
  };

  useEffect(() => {
    const el = scrollRef.current;
    if (!el || variant !== "scroll") return;

    requestAnimationFrame(updateScrollButtons);

    el.addEventListener("scroll", updateScrollButtons);
    window.addEventListener("resize", updateScrollButtons);

    const ro = new ResizeObserver(updateScrollButtons);
    ro.observe(el);

    return () => {
      el.removeEventListener("scroll", updateScrollButtons);
      window.removeEventListener("resize", updateScrollButtons);
      ro.disconnect();
    };
  }, [mangas, variant]);

  const scrollBy = (direction: "left" | "right") => {
    const el = scrollRef.current;
    /* v8 ignore next */
    if (!el) return;
    const amount = el.clientWidth * 0.75;
    el.scrollBy({ left: direction === "left" ? -amount : amount, behavior: "smooth" });
  };

  return (
    <section className="py-8">
      {/* ── Constrained header + grid ── */}
      <div className="max-w-content mx-auto px-6 md:px-8">
        <div className="flex items-end justify-between mb-5">
          <div>
            <h2 className="text-xl font-bold text-md-text-primary">{title}</h2>
            {subtitle && (
              <p className="text-sm text-md-text-secondary mt-0.5">{subtitle}</p>
            )}
          </div>

          <div className="flex items-center gap-2">
            <a
              href="/search"
              className="text-sm text-md-accent hover:text-md-accent/80 transition-colors font-medium"
            >
              View More &rarr;
            </a>
          </div>
        </div>

        {error && (
          <p className="text-md-text-secondary py-8 text-center">
            Failed to load. Please try again later.
          </p>
        )}

        {/* ── Grid layout ── */}
        {variant === "grid" && (
          <div className={`grid ${gridCols[cols]} gap-3 md:gap-4`}>
            {loading
              ? Array.from({ length: Math.min(limit, 10) }).map((_, i) => (
                  <MangaCardSkeleton key={i} />
                ))
              : mangas.map((manga) => (
                  <MangaCard key={manga.id} manga={manga} />
                ))}
          </div>
        )}
      </div>

      {/* ── Full-width horizontal scroll layout ── */}
      {variant === "scroll" && (
        <div className="relative">
          {/* Left arrow at viewport left edge */}
          {!loading && (
            <button
              onClick={() => scrollBy("left")}
              disabled={!canScrollLeft}
              className="absolute left-0 top-1/2 -translate-y-1/2 z-20 w-11 h-11 flex items-center justify-center rounded-r-xl bg-black/60 border-r border-t border-b border-white/10 text-white hover:bg-black/80 disabled:opacity-30 disabled:cursor-not-allowed transition-all shadow-lg"
              aria-label="Scroll left"
            >
              <span className="text-2xl font-bold leading-none -ml-0.5">&lt;</span>
            </button>
          )}

          {/* Right arrow at viewport right edge */}
          {!loading && (
            <button
              onClick={() => scrollBy("right")}
              disabled={!canScrollRight}
              className="absolute right-0 top-1/2 -translate-y-1/2 z-20 w-11 h-11 flex items-center justify-center rounded-l-xl bg-black/60 border-l border-t border-b border-white/10 text-white hover:bg-black/80 disabled:opacity-30 disabled:cursor-not-allowed transition-all shadow-lg"
              aria-label="Scroll right"
            >
              <span className="text-2xl font-bold leading-none -mr-0.5">&gt;</span>
            </button>
          )}

          <div
            ref={scrollRef}
            data-testid="scroll-container"
            className="flex gap-3 md:gap-4 overflow-x-auto px-6 md:px-8 pb-2 -mb-2 scroll-smooth snap-x snap-mandatory scrollbar-hide"
            style={{ scrollbarWidth: "none", msOverflowStyle: "none" }}
          >
            {loading
              ? Array.from({ length: Math.min(limit, 10) }).map((_, i) => (
                  <div key={i} className={`${scrollCardWidth[cols]} shrink-0 snap-start`}>
                    <MangaCardSkeleton />
                  </div>
                ))
              : mangas.map((manga) => (
                  <div key={manga.id} className={`${scrollCardWidth[cols]} shrink-0 snap-start`}>
                    <MangaCard manga={manga} />
                  </div>
                ))}
          </div>
        </div>
      )}
    </section>
  );
}
