"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/contexts/AuthContext";
import { mangaApi, userApi, handleResponse } from "@/lib/api";
import type { Manga, MangaFollow } from "@/lib/types";
import MangaCard from "@/components/MangaCard";
import MangaCardSkeleton from "@/components/MangaCardSkeleton";

function extractIdFromIri(iri: string): string {
  const parts = iri.split("/");
  return parts[parts.length - 1];
}

export default function ProfileContent() {
  const router = useRouter();
  const { user, loading: authLoading } = useAuth();
  const [follows, setFollows] = useState<MangaFollow[]>([]);
  const [mangas, setMangas] = useState<Manga[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [retryCount, setRetryCount] = useState(0);

  useEffect(() => {
    if (authLoading) return;
    if (!user) {
      router.replace("/login");
      return;
    }

    const currentUser = user;
    let cancelled = false;

    async function fetchProfile() {
      setLoading(true);
      setError(null);

      const followsResult = await handleResponse(userApi.follows(currentUser.id));

      if (cancelled) return;

      if (!followsResult.success) {
        setError(followsResult.error);
        setLoading(false);
        return;
      }

      const followList = followsResult.data.member;
      setFollows(followList);

      if (followList.length === 0) {
        setMangas([]);
        setLoading(false);
        return;
      }

      const mangaIds = followList.map((f) => extractIdFromIri(f.manga));
      const mangaResults = await Promise.all(
        mangaIds.map((mangaId) =>
          handleResponse(mangaApi.get(mangaId, { include: "coverArt" })),
        ),
      );

      if (cancelled) return;

      const fetchedMangas: Manga[] = [];
      for (const result of mangaResults) {
        if (result.success) {
          fetchedMangas.push(result.data);
        }
      }

      setMangas(fetchedMangas);
      setLoading(false);
    }

    fetchProfile();
    return () => {
      cancelled = true;
    };
  }, [user, authLoading, router, retryCount]);

  if (authLoading) {
    return (
      <div className="max-w-content mx-auto px-6 md:px-8 py-6">
        <div className="animate-pulse space-y-4">
          <div className="flex items-center gap-4">
            <div className="w-16 h-16 rounded-full bg-md-surface" />
            <div className="space-y-2">
              <div className="h-6 w-40 bg-md-surface rounded" />
              <div className="h-4 w-56 bg-md-surface rounded" />
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (!user) return null;

  if (error) {
    return (
      <div className="max-w-content mx-auto px-6 md:px-8 py-6">
        <div className="flex flex-col items-center justify-center min-h-[40vh] text-center">
          <svg className="w-16 h-16 text-md-text-secondary mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
          </svg>
          <p className="text-lg text-md-text-secondary mb-1">Failed to load profile</p>
          <p className="text-sm text-md-text-secondary mb-4">{error}</p>
          <button
            onClick={() => setRetryCount((c) => c + 1)}
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
      {/* User info */}
      <div className="flex items-center gap-4 mb-8 pb-6 border-b border-md-border">
        <div className="w-16 h-16 rounded-full bg-md-accent flex items-center justify-center text-2xl font-bold text-white shrink-0">
          {(user.username ?? "?").charAt(0).toUpperCase()}
        </div>
        <div className="min-w-0">
          <h1 className="text-2xl font-bold text-md-text-primary">{user.username}</h1>
          <p className="text-sm text-md-text-secondary">{user.email}</p>
          <p className="text-xs text-md-text-secondary/60 mt-0.5">
            Member since {new Date(user.createdAt).toLocaleDateString("en-US", { year: "numeric", month: "long", day: "numeric" })}
          </p>
        </div>
      </div>

      {/* Followed manga */}
      <div className="mb-4">
        <h2 className="text-lg font-semibold text-md-text-primary">
          Followed Manga{!loading && ` (${follows.length})`}
        </h2>
      </div>

      {loading ? (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 md:gap-4">
          {Array.from({ length: 10 }).map((_, i) => (
            <MangaCardSkeleton key={i} />
          ))}
        </div>
      ) : mangas.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-14 text-center rounded-lg bg-md-surface border border-md-border">
          <svg className="w-12 h-12 text-md-text-secondary/40 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
          </svg>
          <p className="text-md-text-secondary text-sm">
            You haven&apos;t followed any manga yet.
          </p>
          <a
            href="/search"
            className="mt-3 px-4 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 transition-colors"
          >
            Browse Manga
          </a>
        </div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 md:gap-4">
          {mangas.map((manga) => (
            <MangaCard key={manga.id} manga={manga} />
          ))}
        </div>
      )}
    </div>
  );
}
