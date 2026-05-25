import { describe, it, expect, beforeEach, afterEach } from "vitest";
import { renderHook, waitFor } from "@testing-library/react";
import MockAdapter from "axios-mock-adapter";
import { SWRConfig } from "swr";
import React from "react";
import api from "@/lib/api";
import { useManga, useMangaFeed, useMangaList } from "@/lib/hooks";

const mockApi = new MockAdapter(api);

function wrapper({ children }: { children: React.ReactNode }) {
  return (
    <SWRConfig value={{ provider: () => new Map() }}>{children}</SWRConfig>
  );
}

function buildManga(id = "1", title = "Test Manga") {
  return {
    "@context": "/api/contexts/Manga",
    "@id": `/api/mangas/${id}`,
    "@type": "Manga",
    id,
    title,
    createdAt: "2024-01-01T00:00:00+00:00",
    status: "ongoing",
    year: 2020,
    contentRating: "safe",
    demographic: "shounen",
  };
}

function buildChapter(id = "c1") {
  return {
    "@context": "/api/contexts/Chapter",
    "@id": `/api/chapters/${id}`,
    "@type": "Chapter",
    id,
    chapterNumber: "1",
    title: "Chapter 1",
    language: "en",
    createdAt: "2024-01-01T00:00:00+00:00",
    manga: { "@id": "/api/mangas/1", "@type": "Manga", id: "1" },
    scanlationGroup: null,
    volume: null,
    pageUrls: [],
  };
}

describe("useMangaList", () => {
  beforeEach(() => mockApi.reset());
  afterEach(() => mockApi.reset());

  it("returns loading state initially", () => {
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    const { result } = renderHook(() => useMangaList({}), { wrapper });

    expect(result.current.isLoading).toBe(true);
    expect(result.current.mangas).toEqual([]);
    expect(result.current.totalItems).toBe(0);
  });

  it("returns mangas and totalItems after successful fetch", async () => {
    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga("1", "Berserk")],
      totalItems: 1,
    });

    const { result } = renderHook(() => useMangaList({}), { wrapper });

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.mangas).toHaveLength(1);
    expect(result.current.mangas[0].title).toBe("Berserk");
    expect(result.current.totalItems).toBe(1);
    expect(result.current.error).toBeNull();
  });

  it("sets error message on API failure", async () => {
    mockApi.onGet(/\/mangas/).reply(500, { detail: "Server error" });

    const { result } = renderHook(() => useMangaList({}), { wrapper });

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.error).toBe("Server error");
    expect(result.current.mangas).toEqual([]);
  });

  it("builds URL with array params as repeated keys", async () => {
    mockApi.onGet(/\/mangas/).reply(200, { member: [], totalItems: 0 });

    renderHook(() => useMangaList({ "tags.id": ["t1", "t2"] }), { wrapper });

    await waitFor(() => expect(mockApi.history.get.length).toBeGreaterThan(0));

    const url = mockApi.history.get[0].url!;
    expect(url).toContain("tags.id=t1");
    expect(url).toContain("tags.id=t2");
  });

  it("refresh triggers a re-fetch", async () => {
    let callCount = 0;
    mockApi.onGet(/\/mangas/).reply(() => {
      callCount++;
      return [200, { member: [], totalItems: callCount }];
    });

    const { result } = renderHook(() => useMangaList({}), { wrapper });

    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(callCount).toBe(1);

    result.current.refresh();

    await waitFor(() => expect(callCount).toBe(2));
  });
});

describe("useManga", () => {
  beforeEach(() => mockApi.reset());
  afterEach(() => mockApi.reset());

  it("returns null manga and loading true initially", () => {
    mockApi.onGet(/\/mangas\/1/).reply(() => new Promise(() => {}));

    const { result } = renderHook(() => useManga("1"), { wrapper });

    expect(result.current.isLoading).toBe(true);
    expect(result.current.manga).toBeNull();
  });

  it("returns manga after successful fetch", async () => {
    mockApi.onGet("/mangas/1").reply(200, buildManga("1", "One Piece"));

    const { result } = renderHook(() => useManga("1"), { wrapper });

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.manga?.title).toBe("One Piece");
    expect(result.current.error).toBeNull();
  });

  it("sets error on fetch failure", async () => {
    mockApi.onGet("/mangas/1").reply(404, { detail: "Not found" });

    const { result } = renderHook(() => useManga("1"), { wrapper });

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.error).toBe("Not found");
    expect(result.current.manga).toBeNull();
  });

  it("does not fetch when id is null", () => {
    const { result } = renderHook(() => useManga(null), { wrapper });

    expect(result.current.isLoading).toBe(false);
    expect(result.current.manga).toBeNull();
    expect(mockApi.history.get).toHaveLength(0);
  });
});

describe("useMangaFeed", () => {
  beforeEach(() => mockApi.reset());
  afterEach(() => mockApi.reset());

  it("returns loading true and empty chapters initially", () => {
    mockApi.onGet(/\/mangas\/1\/feed/).reply(() => new Promise(() => {}));

    const { result } = renderHook(() => useMangaFeed("1"), { wrapper });

    expect(result.current.isLoading).toBe(true);
    expect(result.current.chapters).toEqual([]);
    expect(result.current.totalItems).toBe(0);
  });

  it("returns chapters after successful fetch", async () => {
    mockApi.onGet(/\/mangas\/1\/feed/).reply(200, {
      member: [buildChapter("c1")],
      totalItems: 1,
    });

    const { result } = renderHook(() => useMangaFeed("1"), { wrapper });

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.chapters).toHaveLength(1);
    expect(result.current.chapters[0].id).toBe("c1");
    expect(result.current.totalItems).toBe(1);
    expect(result.current.error).toBeNull();
  });

  it("sets error on fetch failure", async () => {
    mockApi.onGet(/\/mangas\/1\/feed/).reply(500, { detail: "Feed error" });

    const { result } = renderHook(() => useMangaFeed("1"), { wrapper });

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.error).toBe("Feed error");
    expect(result.current.chapters).toEqual([]);
  });

  it("does not fetch when id is null", () => {
    const { result } = renderHook(() => useMangaFeed(null), { wrapper });

    expect(result.current.isLoading).toBe(false);
    expect(result.current.chapters).toEqual([]);
    expect(mockApi.history.get).toHaveLength(0);
  });

  it("appends query params to the feed URL", async () => {
    mockApi.onGet(/\/mangas\/1\/feed/).reply(200, { member: [], totalItems: 0 });

    renderHook(() => useMangaFeed("1", { language: "en", page: "2" }), {
      wrapper,
    });

    await waitFor(() => expect(mockApi.history.get.length).toBeGreaterThan(0));

    const url = mockApi.history.get[0].url!;
    expect(url).toContain("language=en");
    expect(url).toContain("page=2");
  });
});
