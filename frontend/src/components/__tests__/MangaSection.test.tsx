import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor, fireEvent } from "@testing-library/react";
import MockAdapter from "axios-mock-adapter";
import { SWRConfig } from "swr";
import api from "@/lib/api";
import MangaSection from "../MangaSection";

vi.mock("@/components/MangaCard", () => ({
  default: ({ manga }: { manga: { title: string } }) => (
    <div data-testid="manga-card">{manga.title}</div>
  ),
}));

vi.mock("@/components/MangaCardSkeleton", () => ({
  default: () => <div data-testid="manga-card-skeleton" />,
}));

const mockApi = new MockAdapter(api);

// ResizeObserver is not available in jsdom
global.ResizeObserver = class {
  observe() {}
  unobserve() {}
  disconnect() {}
};

// scrollBy is not implemented in jsdom
HTMLElement.prototype.scrollBy = vi.fn();

function renderWithSWR(ui: React.ReactElement) {
  return render(
    <SWRConfig value={{ provider: () => new Map() }}>{ui}</SWRConfig>,
  );
}

function buildManga(id: string, title: string) {
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

describe("MangaSection", () => {
  beforeEach(() => {
    mockApi.reset();
  });

  afterEach(() => {
    mockApi.reset();
  });

  it("renders the section title", () => {
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    renderWithSWR(
      <MangaSection title="Latest Updates" apiParams={{}} />,
    );

    expect(screen.getByText("Latest Updates")).toBeInTheDocument();
  });

  it("renders skeleton cards while loading", () => {
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    renderWithSWR(
      <MangaSection title="Test" apiParams={{}} limit={5} />,
    );

    expect(screen.getAllByTestId("manga-card-skeleton")).toHaveLength(5);
  });

  it("renders manga cards after successful fetch", async () => {
    mockApi.onGet(/\/mangas/).reply(200, {
      member: [
        buildManga("1", "Berserk"),
        buildManga("2", "One Piece"),
      ],
      totalItems: 2,
    });

    renderWithSWR(
      <MangaSection title="Test" apiParams={{}} />,
    );

    await waitFor(() => {
      expect(screen.getByText("Berserk")).toBeInTheDocument();
    });

    expect(screen.getByText("One Piece")).toBeInTheDocument();
  });

  it("respects the limit prop and shows at most that many cards", async () => {
    const members = Array.from({ length: 10 }, (_, i) =>
      buildManga(String(i + 1), `Manga ${i + 1}`),
    );

    mockApi.onGet(/\/mangas/).reply(200, {
      member: members,
      totalItems: 10,
    });

    renderWithSWR(
      <MangaSection title="Test" apiParams={{}} limit={3} />,
    );

    await waitFor(() => {
      expect(screen.getByText("Manga 1")).toBeInTheDocument();
    });

    expect(screen.getAllByTestId("manga-card")).toHaveLength(3);
  });

  it("renders error message on API failure", async () => {
    mockApi.onGet(/\/mangas/).reply(500, { detail: "Server error" });

    renderWithSWR(
      <MangaSection title="Test" apiParams={{}} />,
    );

    await waitFor(() => {
      expect(
        screen.getByText("Failed to load. Please try again later."),
      ).toBeInTheDocument();
    });
  });

  it("renders subtitle when provided", () => {
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    renderWithSWR(
      <MangaSection title="Test" subtitle="A subtitle" apiParams={{}} />,
    );

    expect(screen.getByText("A subtitle")).toBeInTheDocument();
  });

  it("does not render subtitle element when not provided", () => {
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    renderWithSWR(<MangaSection title="Test" apiParams={{}} />);

    expect(screen.queryByText("A subtitle")).not.toBeInTheDocument();
  });

  // ── Scroll variant ──────────────────────────────────────────────────────────

  it("renders scroll variant with skeleton cards while loading", () => {
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    renderWithSWR(
      <MangaSection title="Test" apiParams={{}} limit={4} variant="scroll" />,
    );

    expect(screen.getAllByTestId("manga-card-skeleton")).toHaveLength(4);
    // scroll arrows are hidden during loading
    expect(screen.queryByLabelText("Scroll left")).not.toBeInTheDocument();
    expect(screen.queryByLabelText("Scroll right")).not.toBeInTheDocument();
  });

  it("renders scroll variant manga cards after fetch", async () => {
    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga("1", "Scroll Manga")],
      totalItems: 1,
    });

    renderWithSWR(
      <MangaSection title="Test" apiParams={{}} variant="scroll" />,
    );

    await waitFor(() => {
      expect(screen.getByText("Scroll Manga")).toBeInTheDocument();
    });
  });

  it("renders scroll arrows after data loads", async () => {
    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga("1", "Manga A"), buildManga("2", "Manga B")],
      totalItems: 2,
    });

    renderWithSWR(
      <MangaSection title="Test" apiParams={{}} variant="scroll" />,
    );

    await waitFor(() => {
      expect(screen.getByLabelText("Scroll left")).toBeInTheDocument();
    });

    expect(screen.getByLabelText("Scroll right")).toBeInTheDocument();
  });

  it("scroll buttons are disabled when scroll position is at limits", async () => {
    // In jsdom scrollLeft/scrollWidth are 0, so both buttons start disabled
    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga("1", "Manga A"), buildManga("2", "Manga B")],
      totalItems: 2,
    });

    renderWithSWR(
      <MangaSection title="Test" apiParams={{}} variant="scroll" />,
    );

    await waitFor(() => {
      expect(screen.getByLabelText("Scroll left")).toBeInTheDocument();
    });

    expect(screen.getByLabelText("Scroll left")).toBeDisabled();
    expect(screen.getByLabelText("Scroll right")).toBeDisabled();
  });

  it("clicking scroll right button calls scrollBy with positive left", async () => {
    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga("1", "Manga A"), buildManga("2", "Manga B")],
      totalItems: 2,
    });

    renderWithSWR(
      <MangaSection title="Test" apiParams={{}} variant="scroll" />,
    );

    await waitFor(() => {
      expect(screen.getByLabelText("Scroll right")).toBeInTheDocument();
    });

    const container = screen.getByTestId("scroll-container");
    Object.defineProperty(container, "scrollWidth", { value: 1000, configurable: true });
    Object.defineProperty(container, "clientWidth", { value: 200, configurable: true });
    fireEvent.scroll(container);

    await waitFor(() => {
      expect(screen.getByLabelText("Scroll right")).not.toBeDisabled();
    });

    fireEvent.click(screen.getByLabelText("Scroll right"));

    expect(HTMLElement.prototype.scrollBy).toHaveBeenCalledWith(
      expect.objectContaining({ left: expect.any(Number) }),
    );
    expect((HTMLElement.prototype.scrollBy as ReturnType<typeof vi.fn>).mock.calls.at(-1)![0].left).toBeGreaterThan(0);
  });

  it("clicking scroll left button calls scrollBy with negative left", async () => {
    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga("1", "Manga A"), buildManga("2", "Manga B")],
      totalItems: 2,
    });

    renderWithSWR(
      <MangaSection title="Test" apiParams={{}} variant="scroll" />,
    );

    await waitFor(() => {
      expect(screen.getByLabelText("Scroll left")).toBeInTheDocument();
    });

    const container = screen.getByTestId("scroll-container");
    Object.defineProperty(container, "scrollLeft", { value: 100, configurable: true });
    Object.defineProperty(container, "scrollWidth", { value: 1000, configurable: true });
    Object.defineProperty(container, "clientWidth", { value: 200, configurable: true });
    fireEvent.scroll(container);

    await waitFor(() => {
      expect(screen.getByLabelText("Scroll left")).not.toBeDisabled();
    });

    fireEvent.click(screen.getByLabelText("Scroll left"));

    expect(HTMLElement.prototype.scrollBy).toHaveBeenCalledWith(
      expect.objectContaining({ left: expect.any(Number) }),
    );
    expect((HTMLElement.prototype.scrollBy as ReturnType<typeof vi.fn>).mock.calls.at(-1)![0].left).toBeLessThan(0);
  });

  it("deduplicates requests: two instances with the same apiParams fire only one network call", async () => {
    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga("1", "Shared Manga")],
      totalItems: 1,
    });

    // Wrap both in a single shared SWR cache so deduplication works across instances
    render(
      <SWRConfig value={{ provider: () => new Map() }}>
        <MangaSection title="Section A" apiParams={{ "order[createdAt]": "desc" }} />
        <MangaSection title="Section B" apiParams={{ "order[createdAt]": "desc" }} />
      </SWRConfig>,
    );

    await waitFor(() => {
      expect(screen.getAllByText("Shared Manga")).toHaveLength(2);
    });

    expect(mockApi.history.get.length).toBe(1);
  });
});
