import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor, fireEvent, act } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MockAdapter from "axios-mock-adapter";
import api from "@/lib/api";
import ChapterReaderContent from "../ChapterReaderContent";
import type { Chapter } from "@/lib/types";

// ── Mock useReadingHistory ───────────────────────────────────────────────────

vi.mock("@/hooks/useReadingHistory", () => ({
  useReadingHistory: () => ({
    history: [],
    markAsRead: vi.fn(),
    clearHistory: vi.fn(),
  }),
}));

// ── Mock useRouter ──────────────────────────────────────────────────────────

const mockPush = vi.fn();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: mockPush }),
}));

// ── Helpers ─────────────────────────────────────────────────────────────────

function buildChapter(overrides: Partial<Chapter> = {}): Chapter {
  return {
    "@context": "/api/contexts/Chapter",
    "@id": "/api/chapters/ch-1",
    "@type": "Chapter",
    createdAt: "2024-01-15T12:00:00+00:00",
    manga: {
      "@id": "/api/mangas/manga-1",
      "@type": "Manga",
      id: "manga-1",
    },
    scanlationGroup: null,
    volume: "1",
    chapterNumber: "5",
    title: "The Battle Begins",
    language: "en",
    pageUrls: [
      "/api/chapters/ch-1/pages/1",
      "/api/chapters/ch-1/pages/2",
      "/api/chapters/ch-1/pages/3",
    ],
    ...overrides,
  };
}

const CHAPTER_ID = "ch-1";

// ── API mock ────────────────────────────────────────────────────────────────

const mockApi = new MockAdapter(api);

describe("ChapterReaderContent", () => {
  beforeEach(() => {
    mockApi.reset();
    mockPush.mockClear();
    // Default: both endpoints stay pending
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(() => new Promise(() => {}));
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(() => new Promise(() => {}));
  });

  afterEach(() => {
    mockApi.reset();
  });

  it("shows loading spinner initially", () => {
    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    expect(document.querySelector(".animate-spin")).toBeInTheDocument();
  });

  it("shows error message on fetch failure", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(404, { detail: "Chapter not found" });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Chapter not found")).toBeInTheDocument();
    });
  });

  it("shows retry button on error", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(500, { detail: "Server error" });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Retry")).toBeInTheDocument();
    });
  });

  it("renders page image on successful fetch", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      const img = document.querySelector("img");
      expect(img).toBeInTheDocument();
      expect(img).toHaveAttribute("src", "/api/chapters/ch-1/pages/1");
    });
  });

  it("renders ReaderControls with page indicator", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 3")).toBeInTheDocument();
    });
  });

  it("renders chapter info in ReaderControls", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText(/Ch\. 5 - The Battle Begins/)).toBeInTheDocument();
    });
  });

  it("accepts optional mangaId prop for navigation", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent mangaId="manga-1" chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 3")).toBeInTheDocument();
    });
  });

  it("retries fetch when retry button is clicked", async () => {
    const userEvent = await import("@testing-library/user-event").then(
      (m) => m.default,
    );
    const user = userEvent.setup();

    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(500, { detail: "Server error" });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("Retry")).toBeInTheDocument();
    });

    // Reconfigure mock to succeed on retry
    mockApi.reset();
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    await user.click(screen.getByText("Retry"));

    await waitFor(() => {
      const img = document.querySelector("img");
      expect(img).toBeInTheDocument();
      expect(img).toHaveAttribute("src", "/api/chapters/ch-1/pages/1");
    });
  });

  it("returns null when API returns null data", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, null);

    const { container } = render(
      <ChapterReaderContent chapterId={CHAPTER_ID} />,
    );

    await waitFor(() => {
      expect(container.innerHTML).toBe("");
    });
  });

  it("sets document title with chapter title", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(document.title).toBe("5 - The Battle Begins - MangaDex");
    });
  });

  it("sets document title without chapter title", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter({ title: "" }));
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(document.title).toBe("5 - MangaDex");
    });
  });

  // ── Keyboard navigation ──────────────────────────────────────────────────────

  it("navigates to next page on ArrowRight", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 3")).toBeInTheDocument();
    });

    fireEvent.keyDown(window, { key: "ArrowRight" });

    await waitFor(() => {
      expect(screen.getByText("2 / 3")).toBeInTheDocument();
    });
  });

  it("navigates to previous page on ArrowLeft", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 3")).toBeInTheDocument();
    });

    fireEvent.keyDown(window, { key: "ArrowRight" });
    await waitFor(() => {
      expect(screen.getByText("2 / 3")).toBeInTheDocument();
    });

    fireEvent.keyDown(window, { key: "ArrowLeft" });
    await waitFor(() => {
      expect(screen.getByText("1 / 3")).toBeInTheDocument();
    });
  });

  it("navigates to next chapter on ArrowRight at last page", async () => {
    const nextChapter = buildChapter({
      id: "ch-next",
      chapterNumber: "6",
      title: "Next Chapter",
      pageUrls: ["/api/chapters/ch-next/pages/1"],
    });
    const current = buildChapter({
      id: CHAPTER_ID,
      pageUrls: ["/api/chapters/ch-1/pages/1"],
    });

    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, current);
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [current, nextChapter], totalItems: 2 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 1")).toBeInTheDocument();
    });
    await waitFor(() => {
      expect(screen.getByLabelText("Next chapter")).toBeInTheDocument();
    });

    fireEvent.keyDown(window, { key: "ArrowRight" });

    await waitFor(() => {
      expect(mockPush).toHaveBeenCalledWith("/manga/manga-1/chapter/ch-next");
    });
  });

  it("navigates to previous chapter on ArrowLeft at first page", async () => {
    const prevChapter = buildChapter({
      id: "ch-prev",
      chapterNumber: "4",
      title: "Prev Chapter",
      pageUrls: ["/api/chapters/ch-prev/pages/1"],
    });
    const current = buildChapter({
      id: CHAPTER_ID,
      pageUrls: ["/api/chapters/ch-1/pages/1"],
    });

    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, current);
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [prevChapter, current], totalItems: 2 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 1")).toBeInTheDocument();
    });
    await waitFor(() => {
      expect(screen.getByLabelText("Previous chapter")).toBeInTheDocument();
    });

    fireEvent.keyDown(window, { key: "ArrowLeft" });

    await waitFor(() => {
      expect(mockPush).toHaveBeenCalledWith("/manga/manga-1/chapter/ch-prev");
    });
  });

  // ── Chapter navigation buttons ───────────────────────────────────────────────

  it("navigates to next chapter via button click", async () => {
    const nextChapter = buildChapter({
      id: "ch-next",
      chapterNumber: "6",
      title: "Next Chapter",
      pageUrls: ["/api/chapters/ch-next/pages/1"],
    });
    const current = buildChapter({
      id: CHAPTER_ID,
      pageUrls: ["/api/chapters/ch-1/pages/1"],
    });

    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, current);
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [current, nextChapter], totalItems: 2 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByLabelText("Next chapter")).toBeInTheDocument();
    });

    await userEvent.click(screen.getByLabelText("Next chapter"));

    expect(mockPush).toHaveBeenCalledWith("/manga/manga-1/chapter/ch-next");
  });

  it("navigates to previous chapter via button click", async () => {
    const prevChapter = buildChapter({
      id: "ch-prev",
      chapterNumber: "4",
      title: "Prev Chapter",
      pageUrls: ["/api/chapters/ch-prev/pages/1"],
    });
    const current = buildChapter({
      id: CHAPTER_ID,
      pageUrls: ["/api/chapters/ch-1/pages/1"],
    });

    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, current);
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [prevChapter, current], totalItems: 2 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByLabelText("Previous chapter")).toBeInTheDocument();
    });

    await userEvent.click(screen.getByLabelText("Previous chapter"));

    expect(mockPush).toHaveBeenCalledWith("/manga/manga-1/chapter/ch-prev");
  });

  // ── Sidebar interactions ──────────────────────────────────────────────────────

  it("toggles sidebar with m key", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 3")).toBeInTheDocument();
    });

    expect(screen.queryByText("Menu")).not.toBeInTheDocument();

    fireEvent.keyDown(window, { key: "m" });
    await waitFor(() => {
      expect(screen.getByText("Menu")).toBeInTheDocument();
    });

    fireEvent.keyDown(window, { key: "m" });
    await waitFor(() => {
      expect(screen.queryByText("Menu")).not.toBeInTheDocument();
    });
  });

  it("toggles sidebar with menu button click", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 3")).toBeInTheDocument();
    });

    await userEvent.click(screen.getByLabelText("Toggle reader menu"));
    await waitFor(() => {
      expect(screen.getByText("Menu")).toBeInTheDocument();
    });
  });

  it("closes sidebar via overlay click", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 3")).toBeInTheDocument();
    });

    await userEvent.click(screen.getByLabelText("Toggle reader menu"));
    await waitFor(() => {
      expect(screen.getByText("Menu")).toBeInTheDocument();
    });

    const overlay = document.querySelector(".fixed.inset-0.z-40");
    if (overlay) {
      await userEvent.click(overlay);
      await waitFor(() => {
        expect(screen.queryByText("Menu")).not.toBeInTheDocument();
      });
    }
  });

  it("toggles sidebar by clicking main content area", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    const img = await screen.findByAltText("Page 1");

    expect(screen.queryByText("Menu")).not.toBeInTheDocument();

    await userEvent.click(img);
    await waitFor(() => {
      expect(screen.getByText("Menu")).toBeInTheDocument();
    });
  });

  it("pins sidebar via pin button", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 3")).toBeInTheDocument();
    });

    await userEvent.click(screen.getByLabelText("Toggle reader menu"));
    await waitFor(() => {
      expect(screen.getByLabelText("Pin sidebar")).toBeInTheDocument();
    });

    await userEvent.click(screen.getByLabelText("Pin sidebar"));
    await waitFor(() => {
      expect(screen.getByLabelText("Unpin sidebar")).toBeInTheDocument();
    });
  });

  it("changes page via sidebar page selector", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 3")).toBeInTheDocument();
    });

    await userEvent.click(screen.getByLabelText("Toggle reader menu"));
    await waitFor(() => {
      expect(screen.getByLabelText("Page")).toBeInTheDocument();
    });

    await userEvent.selectOptions(screen.getByLabelText("Page"), "3");

    await waitFor(() => {
      expect(screen.getByText("3 / 3")).toBeInTheDocument();
    });
  });

  it("changes chapter via sidebar chapter selector", async () => {
    const nextChapter = buildChapter({
      id: "ch-next",
      chapterNumber: "6",
      title: "Next Chapter",
      pageUrls: ["/api/chapters/ch-next/pages/1"],
    });
    const current = buildChapter({
      id: CHAPTER_ID,
      pageUrls: ["/api/chapters/ch-1/pages/1"],
    });

    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, current);
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [current, nextChapter], totalItems: 2 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 1")).toBeInTheDocument();
    });

    await userEvent.click(screen.getByLabelText("Toggle reader menu"));
    await waitFor(() => {
      expect(screen.getByLabelText("Chapter")).toBeInTheDocument();
    });

    await userEvent.selectOptions(screen.getByLabelText("Chapter"), "ch-next");

    expect(mockPush).toHaveBeenCalledWith("/manga/manga-1/chapter/ch-next");
  });

  it("handles cancelled fetch on unmount", async () => {
    let resolveChapter: ((value: [number, Chapter]) => void) | undefined;

    const fetchPromise = new Promise<[number, Chapter]>((resolve) => {
      resolveChapter = resolve;
    });

    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(() => fetchPromise);
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    const { unmount } = render(
      <ChapterReaderContent chapterId={CHAPTER_ID} />,
    );

    expect(resolveChapter).toBeDefined();
    unmount();

    await act(async () => {
      resolveChapter!([200, buildChapter()]);
    });
  });

  it("toggles sidebar with uppercase M key", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 3")).toBeInTheDocument();
    });

    expect(screen.queryByText("Menu")).not.toBeInTheDocument();

    fireEvent.keyDown(window, { key: "M" });
    await waitFor(() => {
      expect(screen.getByText("Menu")).toBeInTheDocument();
    });
  });

  it("does nothing on ArrowRight at last page without next chapter", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 3")).toBeInTheDocument();
    });

    // Navigate to last page
    fireEvent.keyDown(window, { key: "ArrowRight" });
    await waitFor(() => {
      expect(screen.getByText("2 / 3")).toBeInTheDocument();
    });
    fireEvent.keyDown(window, { key: "ArrowRight" });
    await waitFor(() => {
      expect(screen.getByText("3 / 3")).toBeInTheDocument();
    });

    // No next chapter — pressing ArrowRight should do nothing
    fireEvent.keyDown(window, { key: "ArrowRight" });
    expect(screen.getByText("3 / 3")).toBeInTheDocument();
  });

  it("does nothing on ArrowLeft at first page without prev chapter", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(200, { member: [], totalItems: 0 });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 3")).toBeInTheDocument();
    });

    // Already at page 1, no prev chapter — pressing ArrowLeft should do nothing
    fireEvent.keyDown(window, { key: "ArrowLeft" });
    expect(screen.getByText("1 / 3")).toBeInTheDocument();
  });

  it("handles feed fetch failure silently", async () => {
    mockApi
      .onGet(new RegExp(`/chapters/${CHAPTER_ID}$`))
      .reply(200, buildChapter());
    mockApi
      .onGet(new RegExp(`/mangas/manga-1/feed`))
      .reply(500, { detail: "Feed error" });

    render(<ChapterReaderContent chapterId={CHAPTER_ID} />);

    await waitFor(() => {
      expect(screen.getByText("1 / 3")).toBeInTheDocument();
    });

    // Feed failure is silent — no error shown, chapter still renders
    expect(mockPush).not.toHaveBeenCalled();
  });
});
