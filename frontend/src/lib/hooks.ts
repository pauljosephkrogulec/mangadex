import useSWR from "swr";
import api from "@/lib/api";
import type { HydraCollection, Manga, Chapter } from "@/lib/types";

const fetcher = (url: string) =>
  api.get(url).then((res) => res.data);

const SWR_CONFIG = {
  revalidateOnFocus: false,
  dedupingInterval: 60_000,
} as const;

export function useMangaList(params: Record<string, string | string[]>) {
  const query = new URLSearchParams();
  query.set("include", "coverArt");
  for (const [key, value] of Object.entries(params)) {
    if (Array.isArray(value)) {
      value.forEach((v) => query.append(key, v));
    } else {
      query.set(key, value);
    }
  }
  const key = `/mangas?${query.toString()}`;
  const { data, error, isLoading, mutate } = useSWR<HydraCollection<Manga>>(
    key,
    fetcher,
    SWR_CONFIG,
  );
  return {
    mangas: data?.member ?? [],
    totalItems: data?.totalItems ?? 0,
    isLoading,
    error: error?.message ?? null,
    refresh: () => mutate(),
  };
}

export function useManga(id: string | null) {
  const key = id ? `/mangas/${id}` : null;
  const { data, error, isLoading } = useSWR<Manga>(key, fetcher, SWR_CONFIG);
  return { manga: data ?? null, isLoading, error: error?.message ?? null };
}

export function useMangaFeed(
  id: string | null,
  params: Record<string, string> = {},
) {
  const query = new URLSearchParams(params);
  const key = id ? `/mangas/${id}/feed?${query.toString()}` : null;
  const { data, error, isLoading } = useSWR<HydraCollection<Chapter>>(
    key,
    fetcher,
    SWR_CONFIG,
  );
  return {
    chapters: data?.member ?? [],
    totalItems: data?.totalItems ?? 0,
    isLoading,
    error: error?.message ?? null,
  };
}
