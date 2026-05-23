"use client";

import { useEffect, useState } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { mangaApi, handleResponse } from "@/lib/api";
import type { RatingResponse } from "@/lib/types";

interface RatingStarsProps {
  mangaId: string;
}

export default function RatingStars({ mangaId }: RatingStarsProps) {
  const { user } = useAuth();
  const [rating, setRating] = useState<RatingResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [hover, setHover] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    async function doFetch() {
      setLoading(true);
      const result = await handleResponse(mangaApi.getRating(mangaId));
      if (cancelled) return;
      if (result.success) {
        setRating(result.data);
      } else {
        setError(result.error);
      }
      setLoading(false);
    }
    doFetch();
    return () => {
      cancelled = true;
    };
  }, [mangaId]);

  async function handleRate(score: number) {
    /* v8 ignore next */
    if (!user || submitting) return;
    setSubmitting(true);
    setError(null);
    const result = await handleResponse(mangaApi.rate(mangaId, score));
    if (result.success) {
      setRating(result.data);
    } else {
      setError(result.error);
    }
    setSubmitting(false);
  }

  const activeScore = hover ?? rating?.userRating ?? 0;

  if (loading) {
    return (
      <div className="flex items-center gap-2">
        <div className="flex gap-0.5">
          {Array.from({ length: 10 }, (_, i) => (
            <div key={i} className="h-4 w-4 rounded bg-md-surface animate-pulse" />
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-1">
      <div className="flex items-center gap-2">
        <div
          className="flex gap-0.5"
          onMouseLeave={() => setHover(null)}
        >
          {Array.from({ length: 10 }, (_, i) => {
            const star = i + 1;
            const filled = star <= activeScore;
            return (
              <button
                key={star}
                type="button"
                disabled={!user || submitting}
                aria-label={`Rate ${star} out of 10`}
                className={[
                  "text-xl leading-none transition-colors",
                  filled ? "text-md-accent" : "text-md-text-secondary",
                  user && !submitting ? "cursor-pointer hover:text-md-accent" : "cursor-default",
                  submitting ? "opacity-50" : "",
                ].join(" ")}
                onMouseEnter={() => user && setHover(star)}
                onClick={() => handleRate(star)}
              >
                ★
              </button>
            );
          })}
        </div>
        <div className="flex items-center gap-1 text-sm text-md-text-secondary">
          {rating && rating.ratingCount > 0 ? (
            <>
              <span className="font-medium text-md-text-primary">
                {rating.averageRating?.toFixed(1)}
              </span>
              <span>/ 10</span>
              <span>({rating.ratingCount} {rating.ratingCount === 1 ? "rating" : "ratings"})</span>
            </>
          ) : (
            <span>No ratings yet</span>
          )}
        </div>
      </div>
      {!user && (
        <p className="text-xs text-md-text-secondary">Log in to rate this manga</p>
      )}
      {error && (
        <p className="text-xs text-red-400">{error}</p>
      )}
    </div>
  );
}
