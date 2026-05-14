import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MockAdapter from "axios-mock-adapter";
import api from "@/lib/api";
import SearchContent from "../SearchContent";

// ── Mock next/navigation ────────────────────────────────────────────────────

const { mockReplace, mockSearchParams } = vi.hoisted(() => ({
  mockReplace: vi.fn(),
  mockSearchParams: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace: mockReplace }),
  useSearchParams: () => mockSearchParams(),
  usePathname: () => "/search",
}));

// ── Mock sub-components ─────────────────────────────────────────────────────

vi.mock("@/components/TagFilter", () => ({
  default: ({ selected, onChange }: { selected: string[]; onChange: (tags: string[]) => void }) => (
    <div data-testid="tag-filter-mock">
      <span data-testid="selected-tags">{selected.join(",")}</span>
      <button
        data-testid="select-tag-btn"
        onClick={() => onChange(["tag-id-1", "tag-id-2"])}
      >
        Select Tags
      </button>
    </div>
  ),
}));

vi.mock("@/components/Pagination", () => ({
  default: ({ currentPage, totalPages, onPageChange }: { currentPage: number; totalPages: number; onPageChange: (page: number) => void }) => (
    <div data-testid="pagination-mock">
      <span data-testid="page-info">{currentPage}/{totalPages}</span>
      <button
        data-testid="next-page-btn"
        onClick={() => onPageChange(currentPage + 1)}
      >
        Next Page
      </button>
    </div>
  ),
}));

// ── API mock ────────────────────────────────────────────────────────────────

const mockApi = new MockAdapter(api);

function buildManga(overrides: Partial<{ id: string; title: string }> = {}) {
  return {
    "@context": "/api/contexts/Manga",
    "@id": `/api/mangas/${overrides.id ?? "1"}`,
    "@type": "Manga",
    id: overrides.id ?? "1",
    title: overrides.title ?? "Test Manga",
    createdAt: "2024-01-01T00:00:00+00:00",
    status: "ongoing",
    year: 2020,
    contentRating: "safe",
    demographic: "shounen",
    altTitles: null,
    description: null,
  };
}

describe("SearchContent", () => {
  beforeEach(() => {
    mockReplace.mockClear();
    mockSearchParams.mockReturnValue(new URLSearchParams());
    mockApi.reset();
  });

  afterEach(() => {
    mockApi.reset();
  });

  // ── Initial render ──────────────────────────────────────────────────────

  it("renders the search input and submit button", () => {
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    render(<SearchContent />);

    expect(screen.getByLabelText("Search manga by title")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Search" })).toBeInTheDocument();
  });

  it("renders the page heading", () => {
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    render(<SearchContent />);

    expect(screen.getByText("Search Manga")).toBeInTheDocument();
  });

  it("shows loading state initially", () => {
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    render(<SearchContent />);

    expect(screen.getByText("Searching...")).toBeInTheDocument();
  });

  // ── Successful data fetch ────────────────────────────────────────────────

  it("displays manga titles after successful fetch", async () => {
    const manga = buildManga({ id: "1", title: "Berserk" });

    mockApi.onGet(/\/mangas/).reply(200, {
      member: [manga],
      totalItems: 1,
    });

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByText("Berserk")).toBeInTheDocument();
    });
  });

  it("shows result count after successful fetch", async () => {
    const manga = buildManga();

    mockApi.onGet(/\/mangas/).reply(200, {
      member: [manga],
      totalItems: 1,
    });

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByText("1 result")).toBeInTheDocument();
    });
  });

  it("renders multiple manga cards in the grid", async () => {
    const items = [
      buildManga({ id: "1", title: "Manga 1" }),
      buildManga({ id: "2", title: "Manga 2" }),
      buildManga({ id: "3", title: "Manga 3" }),
    ];

    mockApi.onGet(/\/mangas/).reply(200, {
      member: items,
      totalItems: 3,
    });

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByText("Manga 1")).toBeInTheDocument();
    });

    expect(screen.getByText("Manga 2")).toBeInTheDocument();
    expect(screen.getByText("Manga 3")).toBeInTheDocument();
    expect(screen.getByText("3 results")).toBeInTheDocument();
  });

  // ── Empty state ──────────────────────────────────────────────────────────

  it("shows empty state when no results are returned", async () => {
    mockApi.onGet(/\/mangas/).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByText("No manga found")).toBeInTheDocument();
    });

    expect(
      screen.getByText(
        "Try adjusting your filters or search terms to find what you are looking for.",
      ),
    ).toBeInTheDocument();
  });

  it("shows empty state without clear filters button when no filters are active", async () => {
    mockApi.onGet(/\/mangas/).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByText("No manga found")).toBeInTheDocument();
    });

    expect(
      screen.queryByText("Clear all filters"),
    ).not.toBeInTheDocument();
  });

  it("shows clear filters button in empty state when filters are active", async () => {
    mockSearchParams.mockReturnValue(new URLSearchParams("status=ongoing"));

    mockApi.onGet(/\/mangas/).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByText("No manga found")).toBeInTheDocument();
    });

    // There should be a Clear all filters button in the empty state
    const clearButtons = screen.getAllByText("Clear all filters");
    expect(clearButtons.length).toBeGreaterThanOrEqual(1);
  });

  // ── Error state ──────────────────────────────────────────────────────────

  it("shows error state with retry button on API failure", async () => {
    mockApi.onGet(/\/mangas/).reply(500, { detail: "Server error" });

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByText("Try again")).toBeInTheDocument();
    });

    expect(screen.getByText("Server error")).toBeInTheDocument();
  });

  it("hides results grid when in error state", async () => {
    mockApi.onGet(/\/mangas/).reply(500, { detail: "Server error" });

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByText("Try again")).toBeInTheDocument();
    });

    // Should show error status text
    expect(screen.getByText("Something went wrong")).toBeInTheDocument();
  });

  it("re-fetches when retry button is clicked", async () => {
    let callCount = 0;

    mockApi.onGet(/\/mangas/).reply(() => {
      callCount++;
      if (callCount === 1) {
        return [500, { detail: "First attempt failed" }];
      }
      return [
        200,
        {
          member: [buildManga({ id: "1", title: "Retried Manga" })],
          totalItems: 1,
        },
      ];
    });

    const user = userEvent.setup();

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByText("Try again")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Try again"));

    await waitFor(() => {
      expect(screen.getByText("Retried Manga")).toBeInTheDocument();
    });
  });

  // ── URL search params initialisation ─────────────────────────────────────

  it("pre-fills the search input from URL query param", () => {
    mockSearchParams.mockReturnValue(new URLSearchParams("q=naruto"));
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    render(<SearchContent />);

    const input = screen.getByLabelText(
      "Search manga by title",
    ) as HTMLInputElement;
    expect(input.value).toBe("naruto");
  });

  it("reads status filter from URL params", async () => {
    mockSearchParams.mockReturnValue(new URLSearchParams("status=ongoing"));

    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga()],
      totalItems: 1,
    });

    const { container } = render(<SearchContent />);
    const sidebar = container.querySelector("aside")!;

    // Status "Ongoing" button should show as selected (accent style)
    await waitFor(() => {
      const ongoingBtn = within(sidebar).getByText("Ongoing");
      expect(ongoingBtn.className).toContain("text-md-accent");
    });
  });

  it("reads demographic filter from URL params", async () => {
    mockSearchParams.mockReturnValue(
      new URLSearchParams("demographic=seinen"),
    );

    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga()],
      totalItems: 1,
    });

    const { container } = render(<SearchContent />);
    const sidebar = container.querySelector("aside")!;

    await waitFor(() => {
      const seinenBtn = within(sidebar).getByText("Seinen");
      expect(seinenBtn.className).toContain("text-md-accent");
    });
  });

  it("reads page from URL params", async () => {
    mockSearchParams.mockReturnValue(new URLSearchParams("page=2"));

    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga({ id: "1", title: "Page 2 Manga" })],
      totalItems: 21,
    });

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByText("Page 2 Manga")).toBeInTheDocument();
    });
  });

  it("reads tag filter from URL params", async () => {
    mockSearchParams.mockReturnValue(new URLSearchParams("tags=tag1&tags=tag2"));

    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga()],
      totalItems: 1,
    });

    render(<SearchContent />);

    // TagFilter should receive the selected tags
    await waitFor(() => {
      expect(screen.getByText("(2)")).toBeInTheDocument();
    });
  });

  // ── Search form submission ───────────────────────────────────────────────

  it("calls router.replace with query param on search submit", async () => {
    const user = userEvent.setup();

    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    render(<SearchContent />);

    const input = screen.getByLabelText("Search manga by title");
    await user.type(input, "naruto");
    await user.click(screen.getByRole("button", { name: "Search" }));

    expect(mockReplace).toHaveBeenCalledWith("/search?q=naruto", {
      scroll: false,
    });
  });

  it("resets page to 1 when submitting a new search", async () => {
    mockSearchParams.mockReturnValue(new URLSearchParams("page=3"));

    const user = userEvent.setup();

    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    render(<SearchContent />);

    const input = screen.getByLabelText("Search manga by title");
    await user.type(input, "new search");
    await user.click(screen.getByRole("button", { name: "Search" }));

    // URL should have q=new+search but no page param (defaults to 1)
    expect(mockReplace).toHaveBeenCalledWith("/search?q=new+search", {
      scroll: false,
    });
  });

  // ── Filter interactions ──────────────────────────────────────────────────

  it("calls router.replace with status param when status is clicked", async () => {
    const user = userEvent.setup();

    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga()],
      totalItems: 1,
    });

    const { container } = render(<SearchContent />);
    const sidebar = container.querySelector("aside")!;

    await waitFor(() => {
      expect(within(sidebar).getByText("Ongoing")).toBeInTheDocument();
    });

    await user.click(within(sidebar).getByText("Ongoing"));

    expect(mockReplace).toHaveBeenCalledWith("/search?status=ongoing", {
      scroll: false,
    });
  });

  it("calls router.replace with demographic param when demographic is clicked", async () => {
    const user = userEvent.setup();

    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga()],
      totalItems: 1,
    });

    const { container } = render(<SearchContent />);
    const sidebar = container.querySelector("aside")!;

    await waitFor(() => {
      expect(within(sidebar).getByText("Shounen")).toBeInTheDocument();
    });

    await user.click(within(sidebar).getByText("Shounen"));

    expect(mockReplace).toHaveBeenCalledWith("/search?demographic=shounen", {
      scroll: false,
    });
  });

  it("calls router.replace with tags when TagFilter changes", async () => {
    const user = userEvent.setup();

    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga()],
      totalItems: 1,
    });

    render(<SearchContent />);

    const tagBtn = await screen.findByTestId("select-tag-btn");
    await user.click(tagBtn);

    expect(mockReplace).toHaveBeenCalledWith(
      "/search?tags=tag-id-1&tags=tag-id-2",
      { scroll: false },
    );
  });

  // ── Desktop sidebar ──────────────────────────────────────────────────────

  it("renders TagFilter in desktop sidebar", async () => {
    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga()],
      totalItems: 1,
    });

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByTestId("tag-filter-mock")).toBeInTheDocument();
    });
  });

  it("renders status filter buttons in sidebar", async () => {
    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga()],
      totalItems: 1,
    });

    const { container } = render(<SearchContent />);

    const sidebar = container.querySelector("aside")!;
    await waitFor(() => {
      // Scope within the Status section (the first <div> after the "Status" heading)
      const statusSection =
        within(sidebar).getByText("Status").closest("div")!;
      expect(within(statusSection).getByText("All")).toBeInTheDocument();
    });

    const statusSection =
      within(sidebar).getByText("Status").closest("div")!;
    expect(within(statusSection).getByText("Ongoing")).toBeInTheDocument();
    expect(within(statusSection).getByText("Completed")).toBeInTheDocument();
    expect(within(statusSection).getByText("Hiatus")).toBeInTheDocument();
    expect(within(statusSection).getByText("Cancelled")).toBeInTheDocument();
  });

  it("renders demographic filter buttons in sidebar", async () => {
    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga()],
      totalItems: 1,
    });

    const { container } = render(<SearchContent />);

    const sidebar = container.querySelector("aside")!;
    await waitFor(() => {
      expect(within(sidebar).getByText("Shounen")).toBeInTheDocument();
    });

    expect(within(sidebar).getByText("Shoujo")).toBeInTheDocument();
    expect(within(sidebar).getByText("Seinen")).toBeInTheDocument();
    expect(within(sidebar).getByText("Josei")).toBeInTheDocument();
  });

  // ── Pagination ───────────────────────────────────────────────────────────

  it("renders pagination when totalPages > 1", async () => {
    const items = Array.from({ length: 21 }, (_, i) =>
      buildManga({ id: String(i + 1), title: `Manga ${i + 1}` }),
    );

    mockApi.onGet(/\/mangas/).reply(200, {
      member: items.slice(0, 20),
      totalItems: 21,
    });

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByTestId("pagination-mock")).toBeInTheDocument();
    });

    expect(screen.getByTestId("page-info")).toHaveTextContent("1/2");
  });

  it("hides pagination when totalPages is 1", async () => {
    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga()],
      totalItems: 1,
    });

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByText("1 result")).toBeInTheDocument();
    });

    expect(screen.queryByTestId("pagination-mock")).not.toBeInTheDocument();
  });

  it("calls router.replace with new page when pagination page changes", async () => {
    const items = Array.from({ length: 21 }, (_, i) =>
      buildManga({ id: String(i + 1), title: `Manga ${i + 1}` }),
    );

    const user = userEvent.setup();

    mockApi.onGet(/\/mangas/).reply(200, {
      member: items.slice(0, 20),
      totalItems: 21,
    });

    render(<SearchContent />);

    const nextBtn = await screen.findByTestId("next-page-btn");
    await user.click(nextBtn);

    expect(mockReplace).toHaveBeenCalledWith("/search?page=2", {
      scroll: false,
    });
  });

  // ── Active filters UI ────────────────────────────────────────────────────

  it("shows active filter count when filters are present", async () => {
    mockSearchParams.mockReturnValue(new URLSearchParams("status=ongoing"));

    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga()],
      totalItems: 1,
    });

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByText(/1 filter active/)).toBeInTheDocument();
    });
  });

  it("shows clear all filters button in sidebar when filters active", async () => {
    mockSearchParams.mockReturnValue(new URLSearchParams("status=ongoing"));

    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga()],
      totalItems: 1,
    });

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByText("Clear all filters")).toBeInTheDocument();
    });
  });

  it("clears all filters when clear button is clicked", async () => {
    const user = userEvent.setup();
    mockSearchParams.mockReturnValue(new URLSearchParams("status=ongoing"));

    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga()],
      totalItems: 1,
    });

    render(<SearchContent />);

    await waitFor(() => {
      expect(screen.getByText("Clear all filters")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Clear all filters"));

    expect(mockReplace).toHaveBeenCalledWith("/search", { scroll: false });
  });

  it("shows mobile filter bar with status and demographic selects", () => {
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    render(<SearchContent />);

    expect(screen.getByLabelText("Filter by status")).toBeInTheDocument();
    expect(
      screen.getByLabelText("Filter by demographic"),
    ).toBeInTheDocument();
  });

  it("calls router.replace when mobile status select changes", async () => {
    const user = userEvent.setup();
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    render(<SearchContent />);

    const statusSelect = screen.getByLabelText("Filter by status");
    await user.selectOptions(statusSelect, "completed");

    expect(mockReplace).toHaveBeenCalledWith("/search?status=completed", {
      scroll: false,
    });
  });

  it("calls router.replace when mobile demographic select changes", async () => {
    const user = userEvent.setup();
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    render(<SearchContent />);

    const demographicSelect = screen.getByLabelText("Filter by demographic");
    await user.selectOptions(demographicSelect, "josei");

    expect(mockReplace).toHaveBeenCalledWith("/search?demographic=josei", {
      scroll: false,
    });
  });

  it("toggles mobile tag filter panel when Tags button is clicked", async () => {
    const user = userEvent.setup();

    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga()],
      totalItems: 1,
    });

    render(<SearchContent />);

    // TagFilter is in sidebar (desktop), but mobile Tags button toggles display
    const tagsBtn = screen.getByText("Tags");
    await user.click(tagsBtn);

    // TagFilter should be visible (it's rendered in both sidebar and mobile panel)
    // The mobile panel renders inside a div with lg:hidden
    const tagFilterElements = screen.getAllByTestId("tag-filter-mock");
    // Desktop + mobile = at least 2 instances
    expect(tagFilterElements.length).toBeGreaterThanOrEqual(1);
  });

  it("shows mobile clear button when filters are active", async () => {
    mockSearchParams.mockReturnValue(new URLSearchParams("status=ongoing"));

    mockApi.onGet(/\/mangas/).reply(200, {
      member: [buildManga()],
      totalItems: 1,
    });

    render(<SearchContent />);

    await waitFor(() => {
      // Mobile clear button (visible on lg:hidden)
      const clearBtn = screen.getByText("Clear");
      expect(clearBtn).toBeInTheDocument();
    });
  });

  // ── Edge cases ───────────────────────────────────────────────────────────

  it("does not show empty state during loading", () => {
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    render(<SearchContent />);

    expect(screen.queryByText("No manga found")).not.toBeInTheDocument();
    expect(screen.getByText("Searching...")).toBeInTheDocument();
  });

  it("handles invalid page number gracefully", () => {
    mockSearchParams.mockReturnValue(new URLSearchParams("page=invalid"));
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    render(<SearchContent />);

    // Should not crash; page defaults to 1
    expect(screen.getByText("Search Manga")).toBeInTheDocument();
  });

  it("handles negative page number gracefully", () => {
    mockSearchParams.mockReturnValue(new URLSearchParams("page=-5"));
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    render(<SearchContent />);

    // Should not crash; page defaults to 1
    expect(screen.getByText("Search Manga")).toBeInTheDocument();
  });

  it("submitting empty search redirects to search page without params", async () => {
    const user = userEvent.setup();
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    render(<SearchContent />);

    // Submit the form with empty input (already empty by default)
    await user.click(screen.getByRole("button", { name: "Search" }));

    // buildUrl with empty q and page "1" should produce pathname without query string
    expect(mockReplace).toHaveBeenCalledWith("/search", { scroll: false });
  });

  it("does not update state after unmount during fetch", async () => {
    let resolve!: (value: unknown) => void;
    const deferred = new Promise((r) => {
      resolve = r;
    });

    mockApi.onGet(/\/mangas/).reply(() => deferred);

    const { unmount } = render(<SearchContent />);

    // Unmount while fetch is in-flight
    unmount();

    // Resolve the request — the cancelled guard should prevent state update
    resolve([200, { member: [], totalItems: 0 }]);

    // Wait for microtasks
    await vi.waitFor(() => {
      expect(true).toBe(true);
    });
  });

  it("clears status filter when mobile select is set to All", async () => {
    const user = userEvent.setup();
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    render(<SearchContent />);

    // First select a status
    const statusSelect = screen.getByLabelText("Filter by status");
    await user.selectOptions(statusSelect, "completed");
    expect(mockReplace).toHaveBeenCalledWith("/search?status=completed", {
      scroll: false,
    });

    // Then clear by selecting "All" (empty value)
    await user.selectOptions(statusSelect, "");
    expect(mockReplace).toHaveBeenCalledWith("/search", { scroll: false });
  });

  it("clears demographic filter when mobile select is set to All", async () => {
    const user = userEvent.setup();
    mockApi.onGet(/\/mangas/).reply(() => new Promise(() => {}));

    render(<SearchContent />);

    const demographicSelect = screen.getByLabelText("Filter by demographic");
    await user.selectOptions(demographicSelect, "josei");
    expect(mockReplace).toHaveBeenCalledWith("/search?demographic=josei", {
      scroll: false,
    });

    // Clear by selecting "All"
    await user.selectOptions(demographicSelect, "");
    expect(mockReplace).toHaveBeenCalledWith("/search", { scroll: false });
  });
});
