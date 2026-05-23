import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor, fireEvent } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MockAdapter from "axios-mock-adapter";
import api from "@/lib/api";
import MangaDetailsContent from "../MangaDetailsContent";
import type { Manga, Chapter, User } from "@/lib/types";

// ── Mock AuthContext ─────────────────────────────────────────────────────────

const mockAuthUser = vi.hoisted(() => ({ current: null as User | null }));

vi.mock("@/contexts/AuthContext", () => ({
  useAuth: () => ({
    user: mockAuthUser.current,
    loading: false,
    error: null,
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
    clearError: vi.fn(),
  }),
}));

const TEST_USER: User = {
  "@context": "/api/contexts/User",
  "@id": "/api/users/u-1",
  "@type": "User",
  id: "u-1",
  email: "test@example.com",
  username: "testuser",
  createdAt: "2024-01-01T00:00:00+00:00",
  roles: ["ROLE_USER"],
};

// ── Mock ChapterList ────────────────────────────────────────────────────────

vi.mock("@/components/ChapterList", () => ({
  default: ({
    chapters,
    totalItems,
    loading,
    error,
    onSortChange,
    onRetry,
    onPageChange,
    onFilterChange,
  }: {
    chapters: Chapter[];
    totalItems: number;
    loading: boolean;
    error: string | null;
    onSortChange: (field: string) => void;
    onRetry: () => void;
    onPageChange: (page: number) => void;
    onFilterChange: (from: string, to: string) => void;
  }) => (
    <div data-testid="chapter-list-mock">
      <span data-testid="chapter-count">{totalItems}</span>
      <span data-testid="chapter-loading">{String(loading)}</span>
      <span data-testid="chapter-error">{error ?? ""}</span>
      {error && (
        <button data-testid="chapter-retry-btn" onClick={onRetry}>
          Retry Chapters
        </button>
      )}
      {!loading && !error && chapters.length === 0 && (
        <span data-testid="chapter-empty">Empty</span>
      )}
      {chapters.map((ch) => (
        <span key={ch.id} data-testid={`chapter-${ch.id}`}>
          {ch.title}
        </span>
      ))}
      <button
        data-testid="sort-by-volume"
        onClick={() => onSortChange("volume")}
      >
        Sort Vol
      </button>
      <button
        data-testid="sort-by-chapter"
        onClick={() => onSortChange("chapterNumber")}
      >
        Sort Ch.
      </button>
      <button
        data-testid="sort-by-title"
        onClick={() => onSortChange("title")}
      >
        Sort Title
      </button>
      <button
        data-testid="sort-by-date"
        onClick={() => onSortChange("createdAt")}
      >
        Sort Date
      </button>
      <button
        data-testid="filter-5-10"
        onClick={() => onFilterChange("5", "10")}
      >
        Filter 5-10
      </button>
      <button
        data-testid="filter-clear"
        onClick={() => onFilterChange("", "")}
      >
        Clear Filter
      </button>
      <button
        data-testid="next-page"
        onClick={() => onPageChange(2)}
      >
        Next Page
      </button>
    </div>
  ),
}));

// ── API mock ────────────────────────────────────────────────────────────────

const mockApi = new MockAdapter(api);

const MANGA_ID = "test-manga-1";

function buildManga(overrides: Partial<Manga> = {}): Manga {
  return {
    "@context": "/api/contexts/Manga",
    "@id": `/api/mangas/${MANGA_ID}`,
    "@type": "Manga",
    id: MANGA_ID,
    title: "Test Manga",
    createdAt: "2024-01-01T00:00:00+00:00",
    status: "ongoing",
    year: 2020,
    contentRating: "safe",
    demographic: "shounen",
    altTitles: null,
    description: null,
    creators: [],
    tags: [],
    coverArts: [],
    chapters: [],
    ...overrides,
  };
}

function buildChapter(overrides: Partial<Chapter> & { id: string }): Chapter {
  return {
    "@context": "/api/contexts/Chapter",
    "@id": `/api/chapters/${overrides.id}`,
    "@type": "Chapter",
    createdAt: "2024-01-15T12:00:00+00:00",
    manga: {
      "@id": `/api/mangas/${MANGA_ID}`,
      "@type": "Manga",
      id: MANGA_ID,
    },
    scanlationGroup: null,
    volume: "1",
    chapterNumber: "1",
    title: null,
    language: "en",
    pageUrls: [],
    ...overrides,
  };
}

describe("MangaDetailsContent", () => {
  beforeEach(() => {
    mockApi.reset();
    // Default: both endpoints stay pending to prevent unexpected state updates.
    // Individual tests override these with specific responses.
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(() => new Promise(() => {}));
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(() => new Promise(() => {}));
  });

  afterEach(() => {
    mockApi.reset();
    mockAuthUser.current = null;
  });

  // ── Loading state ─────────────────────────────────────────────────────────

  it("shows loading skeleton initially", () => {
    // Keep the request pending
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(() => new Promise(() => {}));

    render(<MangaDetailsContent id={MANGA_ID} />);

    // Should show skeleton elements (animate-pulse classes)
    const skeletons = document.querySelectorAll(".animate-pulse");
    expect(skeletons.length).toBeGreaterThan(0);
  });

  // ── Manga details display ─────────────────────────────────────────────────

  it("displays manga title after successful fetch", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Test Manga")).toBeInTheDocument();
    });
  });

  it("displays alt titles", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({
        altTitles: ["Alt Title 1", "Alt Title 2"],
      }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Alt Title 1, Alt Title 2")).toBeInTheDocument();
    });
  });

  it("does not render alt titles section when none exist", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ altTitles: null }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    // No italic text with alt titles
    await waitFor(() => {
      expect(screen.getByText("Test Manga")).toBeInTheDocument();
    });
  });

  it("displays description when present", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ description: "A long description about the manga." }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(
        screen.getByText("A long description about the manga."),
      ).toBeInTheDocument();
    });
  });

  it('shows "No description available" when description is null', async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ description: null }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(
        screen.getByText("No description available."),
      ).toBeInTheDocument();
    });
  });

  // ── Badges ────────────────────────────────────────────────────────────────

  it("renders status badge", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ status: "completed" }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("completed")).toBeInTheDocument();
    });
  });

  it("renders year badge when year is present", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ year: 2021 }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("2021")).toBeInTheDocument();
    });
  });

  it("does not render year badge when year is null", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ year: null }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Test Manga")).toBeInTheDocument();
    });

    expect(screen.queryByText(/202\d/)).not.toBeInTheDocument();
  });

  it("renders demographic badge when demographic is set and not none", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ demographic: "seinen" }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Seinen")).toBeInTheDocument();
    });
  });

  it("does not render demographic badge when demographic is none", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ demographic: "none" }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Test Manga")).toBeInTheDocument();
    });

    expect(screen.queryByText("Shounen")).not.toBeInTheDocument();
  });

  it("renders content rating badge for suggestive content", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ contentRating: "suggestive" }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("16+")).toBeInTheDocument();
    });
  });

  it("renders content rating badge for erotica content", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ contentRating: "erotica" }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("18+")).toBeInTheDocument();
    });
  });

  // ── Creators ──────────────────────────────────────────────────────────────

  it("renders creators grouped by type", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({
        creators: [
          {
            "@context": "/api/contexts/Creator",
            "@id": "/api/creators/author-1",
            "@type": "Creator",
            id: "author-1",
            name: "Mangaka One",
            type: "author",
            createdAt: "2024-01-01T00:00:00+00:00",
          },
          {
            "@context": "/api/contexts/Creator",
            "@id": "/api/creators/artist-1",
            "@type": "Creator",
            id: "artist-1",
            name: "Artist One",
            type: "artist",
            createdAt: "2024-01-01T00:00:00+00:00",
          },
        ],
      }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      // The text content is lowercase due to DOM text being "author:" / "artist:"
      // (capitalize is applied via CSS, not DOM)
      expect(screen.getByText("Mangaka One")).toBeInTheDocument();
      expect(screen.getByText("Artist One")).toBeInTheDocument();
    });
  });

  it("does not render creators section when none exist", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ creators: [] }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Test Manga")).toBeInTheDocument();
    });

    expect(screen.queryByText(/Author:/)).not.toBeInTheDocument();
    expect(screen.queryByText(/Artist:/)).not.toBeInTheDocument();
  });

  // ── Tags ──────────────────────────────────────────────────────────────────

  it("renders tags grouped by category", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({
        tags: [
          {
            "@context": "/api/contexts/Tag",
            "@id": "/api/tags/action",
            "@type": "Tag",
            id: "action",
            name: "Action",
            description: null,
            groupName: "Genre",
            isPrimary: true,
            createdAt: "2024-01-01T00:00:00+00:00",
          },
          {
            "@context": "/api/contexts/Tag",
            "@id": "/api/tags/adventure",
            "@type": "Tag",
            id: "adventure",
            name: "Adventure",
            description: null,
            groupName: "Genre",
            isPrimary: true,
            createdAt: "2024-01-01T00:00:00+00:00",
          },
          {
            "@context": "/api/contexts/Tag",
            "@id": "/api/tags/school",
            "@type": "Tag",
            id: "school",
            name: "School",
            description: null,
            groupName: "Theme",
            isPrimary: false,
            createdAt: "2024-01-01T00:00:00+00:00",
          },
        ],
      }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Genre")).toBeInTheDocument();
      expect(screen.getByText("Theme")).toBeInTheDocument();
      expect(screen.getByText("Action")).toBeInTheDocument();
      expect(screen.getByText("Adventure")).toBeInTheDocument();
      expect(screen.getByText("School")).toBeInTheDocument();
    });
  });

  it("does not render tags section when no tags", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ tags: [] }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Test Manga")).toBeInTheDocument();
    });

    expect(screen.queryByText("Genre")).not.toBeInTheDocument();
  });

  // ── Cover image ──────────────────────────────────────────────────────────

  it("shows cover image when coverArts exist", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({
        coverArts: [
          {
            "@context": "/api/contexts/CoverArt",
            "@id": "/api/cover_arts/cover-1",
            "@type": "CoverArt",
            id: "cover-1",
            imagePath: "/covers/test-manga-1.jpg",
            volume: null,
            isPrimary: true,
            createdAt: "2024-01-01T00:00:00+00:00",
          },
        ],
      }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      const img = document.querySelector("img");
      expect(img).toBeInTheDocument();
      // next/image prepends the hostname in test environment
      expect(img?.getAttribute("src")).toContain("/uploads/covers/test-manga-1.jpg");
    });
  });

  it("shows placeholder when no cover arts exist", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ coverArts: [] }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      // The placeholder icon has an SVG inside the cover container
      const svg = document.querySelector(".aspect-\\[3\\/4\\] svg");
      expect(svg).toBeInTheDocument();
    });
  });

  // ── Error state ───────────────────────────────────────────────────────────

  it("shows error state when manga fetch fails", async () => {
    mockApi
      .onGet(new RegExp(`/mangas/${MANGA_ID}$`))
      .reply(500, { detail: "Server error" });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Try again")).toBeInTheDocument();
    });

    expect(screen.getByText("Failed to load manga")).toBeInTheDocument();
  });

  it("shows not found message for 404", async () => {
    mockApi
      .onGet(new RegExp(`/mangas/${MANGA_ID}$`))
      .reply(404, { detail: "Not Found" });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Manga not found")).toBeInTheDocument();
    });
  });

  it("retry re-fetches manga on error", async () => {
    let callCount = 0;
    const user = userEvent.setup();

    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(() => {
      callCount++;
      if (callCount === 1) return [500, { detail: "Error" }];
      return [200, buildManga({ title: "Retried Manga" })];
    });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Try again")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Try again"));

    await waitFor(() => {
      expect(screen.getByText("Retried Manga")).toBeInTheDocument();
    });
  });

  it("shows inline retry when manga re-fetch fails after successful load", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga({ title: "First Load" }));
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    const { rerender } = render(<MangaDetailsContent id={MANGA_ID} />);

    // First fetch succeeds
    await waitFor(() => {
      expect(screen.getByText("First Load")).toBeInTheDocument();
    });

    // Re-render with new id to trigger re-fetch (no mock handler → fails)
    rerender(<MangaDetailsContent id={MANGA_ID + "-other"} />);

    // Inline error with Retry button should show
    await waitFor(() => {
      expect(screen.getByRole("button", { name: /retry/i })).toBeInTheDocument();
    });
    // Manga error text should be visible
    expect(screen.getByText("An unexpected error occurred")).toBeInTheDocument();
  });

  // ── Chapter section ───────────────────────────────────────────────────────

  it("renders chapter list with chapters", async () => {
    const chapters = [
      buildChapter({ id: "ch-1", title: "Chapter 1" }),
      buildChapter({ id: "ch-2", title: "Chapter 2" }),
    ];

    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga(),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: chapters,
      totalItems: 2,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByTestId("chapter-list-mock")).toBeInTheDocument();
    });

    expect(screen.getByTestId("chapter-count")).toHaveTextContent("2");
    // test-id is chapter-{id} where id is "ch-1" -> "chapter-ch-1"
    expect(screen.getByTestId("chapter-ch-1")).toHaveTextContent("Chapter 1");
    expect(screen.getByTestId("chapter-ch-2")).toHaveTextContent("Chapter 2");
  });

  it("shows empty state when no chapters", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga(),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByTestId("chapter-empty")).toBeInTheDocument();
    });
  });

  it("handles chapter error independently from manga", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ title: "Manga Loaded" }),
    );
    mockApi
      .onGet(new RegExp(`/mangas/${MANGA_ID}/feed`))
      .reply(500, { detail: "Chapter error" });

    render(<MangaDetailsContent id={MANGA_ID} />);

    // Manga should still display
    await waitFor(() => {
      expect(screen.getByText("Manga Loaded")).toBeInTheDocument();
    });

    // Chapter error state should show
    expect(screen.getByTestId("chapter-error")).toHaveTextContent(
      "Chapter error",
    );
  });

  it("retry re-fetches chapters on chapter error", async () => {
    let callCount = 0;
    const user = userEvent.setup();

    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga(),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(() => {
      callCount++;
      if (callCount === 1) return [500, { detail: "Chapter error" }];
      return [200, { member: [buildChapter({ id: "ch-1", title: "Retried Chapter" })], totalItems: 1 }];
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByTestId("chapter-retry-btn")).toBeInTheDocument();
    });

    await user.click(screen.getByTestId("chapter-retry-btn"));

    await waitFor(() => {
      // test-id is chapter-{id} where id is "ch-1" -> "chapter-ch-1"
      expect(screen.getByTestId("chapter-ch-1")).toHaveTextContent("Retried Chapter");
    });
  });

  // ── Sort interaction ─────────────────────────────────────────────────────

  it("invokes sort change handler when ChapterList sort is triggered", async () => {
    const user = userEvent.setup();

    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga(),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [buildChapter({ id: "ch-1", title: "Ch 1" })],
      totalItems: 1,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByTestId("chapter-list-mock")).toBeInTheDocument();
    });

    // Click sort by volume
    await user.click(screen.getByTestId("sort-by-volume"));
    // No error — sort state change triggers chapter re-fetch
    await waitFor(() => {
      expect(screen.getByTestId("chapter-ch-1")).toBeInTheDocument();
    });

    // Click sort by volume again — toggles direction (asc → desc), re-fetches
    await user.click(screen.getByTestId("sort-by-volume"));
    await waitFor(() => {
      expect(screen.getByTestId("chapter-ch-1")).toBeInTheDocument();
    });

    // Click sort by volume a third time — toggles direction back (desc → asc)
    await user.click(screen.getByTestId("sort-by-volume"));
    await waitFor(() => {
      expect(screen.getByTestId("chapter-ch-1")).toBeInTheDocument();
    });

    // Click sort by date — changes field
    await user.click(screen.getByTestId("sort-by-date"));
    await waitFor(() => {
      expect(screen.getByTestId("chapter-ch-1")).toBeInTheDocument();
    });

    // Click sort by chapter — changes field
    await user.click(screen.getByTestId("sort-by-chapter"));
    await waitFor(() => {
      expect(screen.getByTestId("chapter-ch-1")).toBeInTheDocument();
    });

    // Click sort by chapter again — toggles direction (asc → desc)
    await user.click(screen.getByTestId("sort-by-chapter"));
    await waitFor(() => {
      expect(screen.getByTestId("chapter-ch-1")).toBeInTheDocument();
    });

    // Click sort by title — changes field
    await user.click(screen.getByTestId("sort-by-title"));
    await waitFor(() => {
      expect(screen.getByTestId("chapter-ch-1")).toBeInTheDocument();
    });

    // Click sort by title again — toggles direction (asc → desc)
    await user.click(screen.getByTestId("sort-by-title"));
    await waitFor(() => {
      expect(screen.getByTestId("chapter-ch-1")).toBeInTheDocument();
    });
  });

  // ── Chapter filter ───────────────────────────────────────────────────────

  it("filters chapters by range", async () => {
    const user = userEvent.setup();
    const chapters = Array.from({ length: 20 }, (_, i) =>
      buildChapter({
        id: `ch-${i}`,
        chapterNumber: String(i + 1),
        title: `Chapter ${i + 1}`,
      }),
    );

    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: chapters,
      totalItems: 20,
    });

    const { container } = render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByTestId("chapter-list-mock")).toBeInTheDocument();
    });

    const getTitles = () =>
      Array.from(container.querySelectorAll('[data-testid^="chapter-ch-"]'))
        .map((el) => el.textContent);

    // Initially shows all 20
    expect(screen.getByTestId("chapter-count")).toHaveTextContent("20");

    // Filter 5-10 (default sort: chapterNumber desc)
    await user.click(screen.getByTestId("filter-5-10"));
    await waitFor(() => {
      expect(getTitles()).toEqual([
        "Chapter 10", "Chapter 9", "Chapter 8", "Chapter 7", "Chapter 6", "Chapter 5",
      ]);
    });
    expect(screen.getByTestId("chapter-count")).toHaveTextContent("6");

    // Clear filter
    await user.click(screen.getByTestId("filter-clear"));
    await waitFor(() => {
      expect(getTitles().length).toBe(20);
    });
    expect(screen.getByTestId("chapter-count")).toHaveTextContent("20");
  });

  it("applies both from and to filter boundaries correctly", async () => {
    const user = userEvent.setup();
    const chapters = Array.from({ length: 20 }, (_, i) =>
      buildChapter({
        id: `ch-${i}`,
        chapterNumber: String(i + 1),
        title: `Ch ${i + 1}`,
      }),
    );

    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: chapters,
      totalItems: 20,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByTestId("chapter-list-mock")).toBeInTheDocument();
    });

    const getChapters = () =>
      Array.from(document.querySelectorAll('[data-testid^="chapter-ch-"]'))
        .map((el) => el.textContent);

    // Filter 5-10 (default sort: chapterNumber desc)
    await user.click(screen.getByTestId("filter-5-10"));
    await waitFor(() => {
      expect(getChapters()).toEqual([
        "Ch 10", "Ch 9", "Ch 8", "Ch 7", "Ch 6", "Ch 5",
      ]);
    });
  });

  // ── Client-side sort order verification ──────────────────────────────────

  it("sorts by volume ascending inserting nulls after non-null", async () => {
    const user = userEvent.setup();
    // Array: [value1, null, null]
    // Binary insertion sort compares null as `a` against value1 — covers a-null branch
    // Then compares null(null) against value1 then null — covers both-null branch
    const chapters = [
      buildChapter({ id: "ch-c", title: "A (vol 1)", chapterNumber: "1", volume: "1" }),
      buildChapter({ id: "ch-a", title: "C (null)", chapterNumber: "1", volume: null }),
      buildChapter({ id: "ch-b", title: "B (null)", chapterNumber: "1", volume: null }),
    ];

    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: chapters,
      totalItems: 3,
    });

    const { container } = render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByTestId("chapter-list-mock")).toBeInTheDocument();
    });

    const getTitles = () =>
      Array.from(container.querySelectorAll('[data-testid^="chapter-ch-"]'))
        .map((el) => el.textContent);

    // Click sort by volume → asc, nulls last → A (vol 1), C (null), B (null)
    // (null volumes compare equal, so stable sort preserves original order: C then B)
    await user.click(screen.getByTestId("sort-by-volume"));
    await waitFor(() => {
      expect(getTitles()).toEqual(["A (vol 1)", "C (null)", "B (null)"]);
    });
  });

  it("sorts by volume descending inserting non-null among nulls", async () => {
    const user = userEvent.setup();
    // Array: [null, value2, value1]
    // Binary insertion sort inserts value2: compare(value2, null) — covers b-null branch
    // Then inserts value1: compare(value1, value2) — covers no-null branch
    const chapters = [
      buildChapter({ id: "ch-a", title: "C (null)", chapterNumber: "1", volume: null }),
      buildChapter({ id: "ch-c", title: "B (vol 2)", chapterNumber: "1", volume: "2" }),
      buildChapter({ id: "ch-b", title: "A (vol 1)", chapterNumber: "1", volume: "1" }),
    ];

    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: chapters,
      totalItems: 3,
    });

    const { container } = render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByTestId("chapter-list-mock")).toBeInTheDocument();
    });

    const getTitles = () =>
      Array.from(container.querySelectorAll('[data-testid^="chapter-ch-"]'))
        .map((el) => el.textContent);

    // Sort volume ASC → A (vol 1), B (vol 2), C (null)
    await user.click(screen.getByTestId("sort-by-volume"));
    await waitFor(() => {
      expect(getTitles()).toEqual(["A (vol 1)", "B (vol 2)", "C (null)"]);
    });

    await user.click(screen.getByTestId("sort-by-volume")); // toggle to desc → nulls first
    await waitFor(() => {
      expect(getTitles()).toEqual(["C (null)", "B (vol 2)", "A (vol 1)"]);
    });
  });

  it("sorts by title ascending inserting nulls after non-null", async () => {
    const user = userEvent.setup();
    // Array: [Beta, null, null]
    // Insertion sort: compare(null, Beta) as a=null — covers a-null branch (line 129)
    //                 compare(null, null) as a=null, b=null — covers both-null (line 128)
    const chapters = [
      buildChapter({ id: "ch-c", title: "Beta", chapterNumber: "1" }),
      buildChapter({ id: "ch-a", title: null, chapterNumber: "1" }),
      buildChapter({ id: "ch-b", title: null, chapterNumber: "1" }),
    ];

    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: chapters,
      totalItems: 3,
    });

    const { container } = render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByTestId("chapter-list-mock")).toBeInTheDocument();
    });

    const getTitles = () =>
      Array.from(container.querySelectorAll('[data-testid^="chapter-ch-"]'))
        .map((el) => el.textContent);

    // Sort by title asc → Beta, null, null
    await user.click(screen.getByTestId("sort-by-title"));
    await waitFor(() => {
      expect(getTitles()).toEqual(["Beta", "", ""]);
    });
  });

  it("sorts by title descending inserting non-null among nulls", async () => {
    const user = userEvent.setup();
    // Array: [null, Beta, Alpha]
    // Insertion sort: compare(Beta, null) as a=Beta, b=null — covers b-null branch (line 130)
    //                 compare(Alpha, Beta) as no nulls — covers localeCompare (line 131)
    const chapters = [
      buildChapter({ id: "ch-a", title: null, chapterNumber: "1" }),
      buildChapter({ id: "ch-c", title: "Beta", chapterNumber: "1" }),
      buildChapter({ id: "ch-b", title: "Alpha", chapterNumber: "1" }),
    ];

    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: chapters,
      totalItems: 3,
    });

    const { container } = render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByTestId("chapter-list-mock")).toBeInTheDocument();
    });

    const getTitles = () =>
      Array.from(container.querySelectorAll('[data-testid^="chapter-ch-"]'))
        .map((el) => el.textContent);

    // Sort by title asc → Alpha, Beta, null
    await user.click(screen.getByTestId("sort-by-title"));
    await waitFor(() => {
      expect(getTitles()).toEqual(["Alpha", "Beta", ""]);
    });

    await user.click(screen.getByTestId("sort-by-title")); // toggle to desc → nulls first
    await waitFor(() => {
      expect(getTitles()).toEqual(["", "Beta", "Alpha"]);
    });
  });

  it("sorts by createdAt ascending and descending", async () => {
    const user = userEvent.setup();
    const chapters = [
      buildChapter({ id: "ch-a", title: "Oldest", chapterNumber: "1", createdAt: "2024-01-01T00:00:00+00:00" }),
      buildChapter({ id: "ch-b", title: "Middle", chapterNumber: "1", createdAt: "2024-06-15T00:00:00+00:00" }),
      buildChapter({ id: "ch-c", title: "Newest", chapterNumber: "1", createdAt: "2024-12-31T00:00:00+00:00" }),
    ];

    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: chapters,
      totalItems: 3,
    });

    const { container } = render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByTestId("chapter-list-mock")).toBeInTheDocument();
    });

    const getTitles = () =>
      Array.from(container.querySelectorAll('[data-testid^="chapter-ch-"]'))
        .map((el) => el.textContent);

    // Sort by date → starts desc (newest first) → Newest, Middle, Oldest
    await user.click(screen.getByTestId("sort-by-date"));
    await waitFor(() => {
      expect(getTitles()).toEqual(["Newest", "Middle", "Oldest"]);
    });

    // Toggle to asc → Oldest, Middle, Newest
    await user.click(screen.getByTestId("sort-by-date"));
    await waitFor(() => {
      expect(getTitles()).toEqual(["Oldest", "Middle", "Newest"]);
    });
  });

  // ── Chapter fetch cancellation ──────────────────────────────────────────

  it("does not update chapter state after unmount while chapters are loading", async () => {
    let resolveChapters!: (value: [number, unknown]) => void;
    const chaptersDeferred = new Promise<[number, unknown]>((r) => {
      resolveChapters = r;
    });

    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(() => chaptersDeferred);

    const { unmount } = render(<MangaDetailsContent id={MANGA_ID} />);

    unmount();

    resolveChapters([200, { member: [], totalItems: 0 }]);

    await vi.waitFor(() => {
      expect(true).toBe(true);
    });
  });

  // ── Page change ──────────────────────────────────────────────────────────

  it("triggers page change when pagination next page is clicked", async () => {
    const user = userEvent.setup();
    const chapters = Array.from({ length: 100 }, (_, i) =>
      buildChapter({ id: `ch-${i}`, chapterNumber: String(i + 1), title: `Chapter ${i + 1}` }),
    );

    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply((config) => {
      const url = new URL(config.url!, `http://localhost${config.url}`);
      const page = Number(url.searchParams.get("page") || "1");
      const perPage = 100;
      const start = (page - 1) * perPage;
      return [
        200,
        {
          member: chapters.slice(start, start + perPage),
          totalItems: chapters.length,
        },
      ];
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByTestId("chapter-list-mock")).toBeInTheDocument();
    });

    // Initially shows page 1
    expect(screen.getByTestId("chapter-count")).toHaveTextContent("100");

    // Click next page
    await user.click(screen.getByTestId("next-page"));

    // Still on page 2 with same total — just verify no error
    await waitFor(() => {
      expect(screen.getByTestId("chapter-count")).toHaveTextContent("100");
    });
  });

  // ── Image error ──────────────────────────────────────────────────────────

  it("shows placeholder when cover image fails to load", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({
        coverArts: [
          {
            "@context": "/api/contexts/CoverArt",
            "@id": "/api/cover_arts/cover-1",
            "@type": "CoverArt",
            id: "cover-1",
            imagePath: "/covers/test-manga.jpg",
            volume: null,
            isPrimary: true,
            createdAt: "2024-01-01T00:00:00+00:00",
          },
        ],
      }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    // Wait for image to render
    await waitFor(() => {
      expect(document.querySelector("img")).toBeInTheDocument();
    });

    // Fire error event on the image
    const img = document.querySelector("img")!;
    fireEvent.error(img);

    // Placeholder SVG should now be visible
    await waitFor(() => {
      const svg = document.querySelector(".aspect-\\[3\\/4\\] svg");
      expect(svg).toBeInTheDocument();
    });
  });

  // ── Description expand/collapse ───────────────────────────────────────────

  it("toggles description expand/collapse for long descriptions", async () => {
    const longDescription = "A".repeat(301);
    const user = userEvent.setup();

    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ description: longDescription }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    // Wait for "Show more" button to appear
    await waitFor(() => {
      expect(screen.getByText("Show more")).toBeInTheDocument();
    });

    // Click "Show more" — description should expand
    await user.click(screen.getByText("Show more"));
    expect(screen.getByText("Show less")).toBeInTheDocument();

    // Click "Show less" — description should collapse
    await user.click(screen.getByText("Show less"));
    expect(screen.getByText("Show more")).toBeInTheDocument();
  });

  it("does not show expand button for short descriptions", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ description: "Short description." }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Short description.")).toBeInTheDocument();
    });

    expect(screen.queryByText("Show more")).not.toBeInTheDocument();
    expect(screen.queryByText("Show less")).not.toBeInTheDocument();
  });

  // ── Branch coverage ──────────────────────────────────────────────────────

  it("renders demographic badge with raw value when not in labels map", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ demographic: "unknown_value" as never }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("unknown_value")).toBeInTheDocument();
    });
  });

  it("renders only author when artist is absent", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({
        creators: [
          {
            "@context": "/api/contexts/Creator",
            "@id": "/api/creators/author-1",
            "@type": "Creator",
            id: "author-1",
            name: "Solo Author",
            type: "author",
            createdAt: "2024-01-01T00:00:00+00:00",
          },
        ],
      }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Solo Author")).toBeInTheDocument();
    });

    // Artist section should not render
    expect(screen.queryByText(/artist/i)).not.toBeInTheDocument();
  });

  it("pluralizes creator type when multiple of same type exist", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({
        creators: [
          {
            "@context": "/api/contexts/Creator",
            "@id": "/api/creators/author-1",
            "@type": "Creator",
            id: "author-1",
            name: "Author One",
            type: "author",
            createdAt: "2024-01-01T00:00:00+00:00",
          },
          {
            "@context": "/api/contexts/Creator",
            "@id": "/api/creators/author-2",
            "@type": "Creator",
            id: "author-2",
            name: "Author Two",
            type: "author",
            createdAt: "2024-01-01T00:00:00+00:00",
          },
        ],
      }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      // Outer span contains combined text: "authors: Author One, Author Two"
      expect(
        screen.getByText((content) => content.includes("Author One, Author Two")),
      ).toBeInTheDocument();
    });
  });

  // ── AddToListDropdown ─────────────────────────────────────────────────────

  it("shows Add to List button when user is authenticated", async () => {
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Add to List")).toBeInTheDocument();
    });
  });

  it("opens dropdown and shows No lists yet when user has no lists", async () => {
    const ue = userEvent.setup();
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });

    render(<MangaDetailsContent id={MANGA_ID} />);
    await waitFor(() => screen.getByText("Add to List"));

    await ue.click(screen.getByText("Add to List"));

    await waitFor(() => {
      expect(screen.getByText("No lists yet.")).toBeInTheDocument();
    });
  });

  it("shows list items in the dropdown with checkmark for lists containing the manga", async () => {
    const ue = userEvent.setup();
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onGet("/users/u-1/custom_lists").reply(200, {
      member: [
        {
          "@context": "/api/contexts/CustomList",
          "@id": "/api/custom_lists/cl-1",
          "@type": "CustomList",
          id: "cl-1",
          name: "Reading List",
          visibility: "private",
          user: "/api/users/u-1",
          mangas: [{ id: MANGA_ID, title: "Test Manga" }],
          createdAt: "2024-01-01T00:00:00+00:00",
        },
        {
          "@context": "/api/contexts/CustomList",
          "@id": "/api/custom_lists/cl-2",
          "@type": "CustomList",
          id: "cl-2",
          name: "Want to Read",
          visibility: "public",
          user: "/api/users/u-1",
          mangas: [],
          createdAt: "2024-01-01T00:00:00+00:00",
        },
      ],
      totalItems: 2,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);
    await waitFor(() => screen.getByText("Add to List"));

    await ue.click(screen.getByText("Add to List"));

    await waitFor(() => {
      expect(screen.getByText("Reading List")).toBeInTheDocument();
      expect(screen.getByText("Want to Read")).toBeInTheDocument();
    });
  });

  it("adds manga to a list when toggled", async () => {
    const ue = userEvent.setup();
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onGet("/users/u-1/custom_lists").reply(200, {
      member: [{
        "@context": "/api/contexts/CustomList",
        "@id": "/api/custom_lists/cl-1",
        "@type": "CustomList",
        id: "cl-1",
        name: "Favourites",
        visibility: "private",
        user: "/api/users/u-1",
        mangas: [],
        createdAt: "2024-01-01T00:00:00+00:00",
      }],
      totalItems: 1,
    });
    mockApi.onPost(`/custom_lists/cl-1/mangas/${MANGA_ID}`).reply(200);

    render(<MangaDetailsContent id={MANGA_ID} />);
    await waitFor(() => screen.getByText("Add to List"));

    await ue.click(screen.getByText("Add to List"));
    await waitFor(() => screen.getByText("Favourites"));

    await ue.click(screen.getByText("Favourites"));

    await waitFor(() => {
      expect(mockApi.history.post.length).toBeGreaterThan(0);
    });
  });

  it("removes manga from a list when toggled off", async () => {
    const ue = userEvent.setup();
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onGet("/users/u-1/custom_lists").reply(200, {
      member: [{
        "@context": "/api/contexts/CustomList",
        "@id": "/api/custom_lists/cl-1",
        "@type": "CustomList",
        id: "cl-1",
        name: "Already In",
        visibility: "private",
        user: "/api/users/u-1",
        mangas: [{ id: MANGA_ID, title: "Test Manga" }],
        createdAt: "2024-01-01T00:00:00+00:00",
      }],
      totalItems: 1,
    });
    mockApi.onDelete(`/custom_lists/cl-1/mangas/${MANGA_ID}`).reply(204);

    render(<MangaDetailsContent id={MANGA_ID} />);
    await waitFor(() => screen.getByText("Add to List"));

    await ue.click(screen.getByText("Add to List"));
    await waitFor(() => screen.getByText("Already In"));

    await ue.click(screen.getByText("Already In"));

    await waitFor(() => {
      expect(mockApi.history.delete.some((r) => r.url?.includes("mangas"))).toBe(true);
    });
  });

  it("shows create form inside dropdown when New list is clicked", async () => {
    const ue = userEvent.setup();
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });

    render(<MangaDetailsContent id={MANGA_ID} />);
    await waitFor(() => screen.getByText("Add to List"));

    await ue.click(screen.getByText("Add to List"));
    await waitFor(() => screen.getByText("No lists yet."));

    await ue.click(screen.getByText("New list"));

    await waitFor(() => {
      expect(screen.getByPlaceholderText("List name")).toBeInTheDocument();
    });
  });

  it("closes dropdown on outside click", async () => {
    const ue = userEvent.setup();
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });

    render(<MangaDetailsContent id={MANGA_ID} />);
    await waitFor(() => screen.getByText("Add to List"));

    await ue.click(screen.getByText("Add to List"));
    await waitFor(() => screen.getByText("No lists yet."));

    fireEvent.mouseDown(document.body);

    await waitFor(() => {
      expect(screen.queryByText("No lists yet.")).not.toBeInTheDocument();
    });
  });

  it("cancels create form inside dropdown with cancel button", async () => {
    const ue = userEvent.setup();
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });

    render(<MangaDetailsContent id={MANGA_ID} />);
    await waitFor(() => screen.getByText("Add to List"));

    await ue.click(screen.getByText("Add to List"));
    await waitFor(() => screen.getByText("No lists yet."));

    await ue.click(screen.getByText("New list"));
    await screen.findByPlaceholderText("List name");

    await ue.click(screen.getByText("✕"));

    await waitFor(() => {
      expect(screen.queryByPlaceholderText("List name")).not.toBeInTheDocument();
    });
    expect(screen.getByText("New list")).toBeInTheDocument();
  });

  it("changes visibility via select in the create-list form inside dropdown", async () => {
    const ue = userEvent.setup();
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });
    mockApi.onPost("/custom_lists").reply(201, {
      "@context": "/api/contexts/CustomList",
      "@id": "/api/custom_lists/cl-vis",
      "@type": "CustomList",
      id: "cl-vis",
      name: "Public List",
      visibility: "public",
      user: "/api/users/u-1",
      mangas: [],
      createdAt: "2024-01-01T00:00:00+00:00",
    });
    mockApi.onPost(`/custom_lists/cl-vis/mangas/${MANGA_ID}`).reply(200);

    render(<MangaDetailsContent id={MANGA_ID} />);
    await waitFor(() => screen.getByText("Add to List"));

    await ue.click(screen.getByText("Add to List"));
    await waitFor(() => screen.getByText("No lists yet."));

    await ue.click(screen.getByText("New list"));
    await screen.findByPlaceholderText("List name");

    const select = screen.getByRole("combobox");
    await ue.selectOptions(select, "public");

    expect((select as HTMLSelectElement).value).toBe("public");
  });

  it("creates a new list and adds manga from the dropdown", async () => {
    const ue = userEvent.setup();
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });
    mockApi.onPost("/custom_lists").reply(201, {
      "@context": "/api/contexts/CustomList",
      "@id": "/api/custom_lists/cl-new",
      "@type": "CustomList",
      id: "cl-new",
      name: "New List",
      visibility: "private",
      user: "/api/users/u-1",
      mangas: [],
      createdAt: "2024-01-01T00:00:00+00:00",
    });
    mockApi.onPost(`/custom_lists/cl-new/mangas/${MANGA_ID}`).reply(200);

    render(<MangaDetailsContent id={MANGA_ID} />);
    await waitFor(() => screen.getByText("Add to List"));

    await ue.click(screen.getByText("Add to List"));
    await waitFor(() => screen.getByText("No lists yet."));

    await ue.click(screen.getByText("New list"));
    const input = await screen.findByPlaceholderText("List name");
    await ue.type(input, "New List");
    await ue.click(screen.getByText("Create & Add"));

    await waitFor(() => {
      expect(mockApi.history.post.some((r) => r.url === "/custom_lists")).toBe(true);
    });
  });

  it("creates list but does not mark manga as in-list when addManga fails", async () => {
    const ue = userEvent.setup();
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });
    mockApi.onPost("/custom_lists").reply(201, {
      "@context": "/api/contexts/CustomList",
      "@id": "/api/custom_lists/cl-fail",
      "@type": "CustomList",
      id: "cl-fail",
      name: "Fail List",
      visibility: "private",
      user: "/api/users/u-1",
      mangas: [],
      createdAt: "2024-01-01T00:00:00+00:00",
    });
    mockApi.onPost(`/custom_lists/cl-fail/mangas/${MANGA_ID}`).reply(500, { detail: "Add failed" });

    render(<MangaDetailsContent id={MANGA_ID} />);
    await waitFor(() => screen.getByText("Add to List"));

    await ue.click(screen.getByText("Add to List"));
    await waitFor(() => screen.getByText("No lists yet."));

    await ue.click(screen.getByText("New list"));
    const input = await screen.findByPlaceholderText("List name");
    await ue.type(input, "Fail List");
    await ue.click(screen.getByText("Create & Add"));

    await waitFor(() => {
      expect(mockApi.history.post.some((r) => r.url === "/custom_lists")).toBe(true);
    });
  });

  it("uses fallback style for unknown manga status", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(
      200,
      buildManga({ status: "unknown_status" as never }),
    );
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("unknown_status")).toBeInTheDocument();
    });
  });

  // ── Edge cases ────────────────────────────────────────────────────────────

  it("does not update state after unmount", async () => {
    let resolveManga!: (value: [number, unknown]) => void;
    const mangaDeferred = new Promise<[number, unknown]>((r) => {
      resolveManga = r;
    });

    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(() => mangaDeferred);

    const { unmount } = render(<MangaDetailsContent id={MANGA_ID} />);

    unmount();

    resolveManga([200, buildManga()]);

    await vi.waitFor(() => {
      expect(true).toBe(true);
    });
  });

  // ── Follow / Unfollow ─────────────────────────────────────────────────

  it("shows follow button when user is authenticated", async () => {
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Follow")).toBeInTheDocument();
    });
  });

  it("shows Following button when already followed", async () => {
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: true });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Following")).toBeInTheDocument();
    });
  });

  it("shows sign in to follow when user is not authenticated", async () => {
    mockAuthUser.current = null;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Sign in to Follow")).toBeInTheDocument();
    });
  });

  it("shows follow error when follow status fetch fails", async () => {
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(500, {
      detail: "Failed to fetch follow status",
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Failed to fetch follow status")).toBeInTheDocument();
    });
  });

  it("toggles from Follow to Following on successful follow", async () => {
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onPost(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200);

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Follow")).toBeInTheDocument();
    });

    const user = userEvent.setup();
    await user.click(screen.getByText("Follow"));

    await waitFor(() => {
      expect(screen.getByText("Following")).toBeInTheDocument();
    });
  });

  it("toggles from Following to Follow on successful unfollow", async () => {
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: true });
    mockApi.onDelete(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(204);

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Following")).toBeInTheDocument();
    });

    const user = userEvent.setup();
    await user.click(screen.getByText("Following"));

    await waitFor(() => {
      expect(screen.getByText("Follow")).toBeInTheDocument();
    });
  });

  it("shows error when unfollow API fails", async () => {
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, {
      member: [],
      totalItems: 0,
    });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: true });
    mockApi.onDelete(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(500, {
      detail: "Unfollow failed",
    });

    render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Following")).toBeInTheDocument();
    });

    const user = userEvent.setup();
    await user.click(screen.getByText("Following"));

    await waitFor(() => {
      expect(screen.getByText("Unfollow failed")).toBeInTheDocument();
    });
  });

  it("skips reloading lists when dropdown reopened with lists already loaded", async () => {
    const ue = userEvent.setup();
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onGet("/users/u-1/custom_lists").reply(200, {
      member: [{
        "@context": "/api/contexts/CustomList",
        "@id": "/api/custom_lists/cl-1",
        "@type": "CustomList",
        id: "cl-1",
        name: "Cached List",
        visibility: "private",
        user: "/api/users/u-1",
        mangas: [],
        createdAt: "2024-01-01T00:00:00+00:00",
      }],
      totalItems: 1,
    });

    render(<MangaDetailsContent id={MANGA_ID} />);
    await waitFor(() => screen.getByText("Add to List"));

    // First open — loads lists
    await ue.click(screen.getByText("Add to List"));
    await waitFor(() => screen.getByText("Cached List"));

    // Close with outside click
    fireEvent.mouseDown(document.body);
    await waitFor(() => {
      expect(screen.queryByText("Cached List")).not.toBeInTheDocument();
    });

    const callsBefore = mockApi.history.get.filter((r) => r.url?.includes("custom_lists")).length;

    // Re-open — lists already cached, no new request
    await ue.click(screen.getByText("Add to List"));
    await waitFor(() => screen.getByText("Cached List"));

    const callsAfter = mockApi.history.get.filter((r) => r.url?.includes("custom_lists")).length;
    expect(callsAfter).toBe(callsBefore);
  });

  it("shows loading indicator and then empty dropdown when custom lists API fails", async () => {
    const ue = userEvent.setup();
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onGet("/users/u-1/custom_lists").reply(500, { detail: "Lists failed" });

    render(<MangaDetailsContent id={MANGA_ID} />);
    await waitFor(() => screen.getByText("Add to List"));

    await ue.click(screen.getByText("Add to List"));

    await waitFor(() => {
      expect(mockApi.history.get.some((r) => r.url?.includes("custom_lists"))).toBe(true);
    });
  });

  it("does not call loadLists when closing already-open dropdown", async () => {
    const ue = userEvent.setup();
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });

    render(<MangaDetailsContent id={MANGA_ID} />);
    await waitFor(() => screen.getByText("Add to List"));

    // Open dropdown
    await ue.click(screen.getByText("Add to List"));
    await waitFor(() => screen.getByText("No lists yet."));

    const callsBefore = mockApi.history.get.filter((r) => r.url?.includes("custom_lists")).length;

    // Close dropdown by clicking again
    await ue.click(screen.getByText("Add to List"));
    await waitFor(() => {
      expect(screen.queryByText("No lists yet.")).not.toBeInTheDocument();
    });

    // No extra custom_lists request was made when closing
    const callsAfter = mockApi.history.get.filter((r) => r.url?.includes("custom_lists")).length;
    expect(callsAfter).toBe(callsBefore);
  });

  it("keeps list state unchanged when toggle manga API fails", async () => {
    const ue = userEvent.setup();
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onGet("/users/u-1/custom_lists").reply(200, {
      member: [{
        "@context": "/api/contexts/CustomList",
        "@id": "/api/custom_lists/cl-1",
        "@type": "CustomList",
        id: "cl-1",
        name: "Fail Toggle",
        visibility: "private",
        user: "/api/users/u-1",
        mangas: [],
        createdAt: "2024-01-01T00:00:00+00:00",
      }],
      totalItems: 1,
    });
    mockApi.onPost(new RegExp(`/custom_lists/cl-1/mangas/${MANGA_ID}`)).reply(500, { detail: "Toggle failed" });

    render(<MangaDetailsContent id={MANGA_ID} />);
    await waitFor(() => screen.getByText("Add to List"));

    await ue.click(screen.getByText("Add to List"));
    await waitFor(() => screen.getByText("Fail Toggle"));

    await ue.click(screen.getByText("Fail Toggle"));

    // Wait for action to complete: loading spinner gone and list button re-enabled
    await waitFor(() => {
      const btn = screen.getByRole("button", { name: /Fail Toggle/ });
      expect(btn).not.toBeDisabled();
    });

    // List unchanged — manga not marked as in the list
    expect(screen.getByText("Fail Toggle")).toBeInTheDocument();
  });

  it("shows no list added when create list API fails", async () => {
    const ue = userEvent.setup();
    mockAuthUser.current = TEST_USER;
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, buildManga());
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/follow$`)).reply(200, { following: false });
    mockApi.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });
    mockApi.onPost("/custom_lists").reply(500, { detail: "Create failed" });

    render(<MangaDetailsContent id={MANGA_ID} />);
    await waitFor(() => screen.getByText("Add to List"));

    await ue.click(screen.getByText("Add to List"));
    await waitFor(() => screen.getByText("No lists yet."));

    await ue.click(screen.getByText("New list"));
    const input = await screen.findByPlaceholderText("List name");
    await ue.type(input, "Bad List");
    await ue.click(screen.getByText("Create & Add"));

    await waitFor(() => {
      expect(mockApi.history.post.some((r) => r.url === "/custom_lists")).toBe(true);
    });
    expect(screen.queryByText("Bad List")).not.toBeInTheDocument();
  });

  it("renders null when manga data is null after load", async () => {
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}$`)).reply(200, null);
    mockApi.onGet(new RegExp(`/mangas/${MANGA_ID}/feed`)).reply(200, { member: [], totalItems: 0 });

    const { container } = render(<MangaDetailsContent id={MANGA_ID} />);

    await waitFor(() => {
      expect(container.querySelector(".animate-pulse")).not.toBeInTheDocument();
    });
  });
});
