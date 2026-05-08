"use client";

import { useEffect, useState } from "react";
import api, { handleResponse } from "@/lib/api";
import type { HydraCollection, Manga } from "@/lib/types";
import MangaCard from "./MangaCard";
import MangaCardSkeleton from "./MangaCardSkeleton";

export default function MangaGrid() {
  const [mangas, setMangas] = useState<Manga[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    async function fetchMangas() {
      const result = await handleResponse(
        api.get<HydraCollection<Manga>>(
          "/mangas?order[createdAt]=desc&include=coverArt",
        ),
      );
      if (result.success) {
        setMangas(result.data.member);
      } else {
        setError(result.error);
      }
      setLoading(false);
    }
    fetchMangas();
  }, []);

  if (error) {
    return (
      <section className="max-w-content mx-auto px-4 py-6">
        <h2 className="text-2xl font-bold text-md-text-primary mb-6">
          Latest Manga
        </h2>
        <p className="text-md-text-secondary">
          Failed to load manga. Please try again later.
        </p>
      </section>
    );
  }

  return (
    <section className="max-w-content mx-auto px-4 py-6">
      <h2 className="text-2xl font-bold text-md-text-primary mb-6">
        Latest Manga
      </h2>
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {loading
          ? Array.from({ length: 8 }).map((_, i) => (
              <MangaCardSkeleton key={i} />
            ))
          : mangas.map((manga) => (
              <MangaCard key={manga.id} manga={manga} />
            ))}
      </div>
    </section>
  );
}
