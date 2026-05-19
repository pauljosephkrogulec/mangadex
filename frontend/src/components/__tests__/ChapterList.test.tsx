import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, fireEvent } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import ChapterList from "../ChapterList";
import type { Chapter } from "@/lib/types";
import type { SortField } from "../ChapterList";

// ── Mock Pagination ─────────────────────────────────────────────────────────

vi.mock("@/components/Pagination", () => ({
  default: ({
    currentPage,
    totalPages,
    onPageChange,
  }: {
    currentPage: number;
    totalPages: number;
    onPageChange: (page: number) => void;
  }) => (
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

// ── Helpers ─────────────────────────────────────────────────────────────────

function buildChapter(
  overrides: Partial<Chapter> & { id: string },
): Chapter {
  return {
    "@context": "/api/contexts/Chapter",
    "@id": `/api/chapters/${overrides.id}`,
    "@type": "Chapter",
    createdAt: "2024-01-15T12:00:00+00:00",
    manga: {
      "@id": "/api/mangas/manga-1",
      "@type": "Manga",
      id: "manga-1",
    },
    scanlationGroup: {
      "@id": "/api/scanlation_groups/group-1",
      "@type": "ScanlationGroup",
      id: "group-1",
      name: "Test Group",
      website: null,
    },
    volume: "1",
    chapterNumber: "1",
    title: "Chapter 1",
    language: "en",
    pageUrls: ["/pages/1.jpg"],
    ...overrides,
  };
}

// ── Default props ───────────────────────────────────────────────────────────

const defaultProps = {
  chapters: [],
  totalItems: 0,
  currentPage: 1,
  totalPages: 1,
  loading: false,
  error: null,
  sortField: "createdAt" as SortField,
  sortDir: "desc" as "asc" | "desc",
  onSortChange: vi.fn(),
  onPageChange: vi.fn(),
  onRetry: vi.fn(),
};

describe("ChapterList", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ── Loading ───────────────────────────────────────────────────────────────

  it("shows 5 skeleton rows when loading", () => {
    render(<ChapterList {...defaultProps} loading={true} />);

    // Skeleton rows have animate-pulse class — check number of rows
    const rows = document.querySelectorAll("tbody tr.animate-pulse");
    expect(rows.length).toBe(5);
  });

  it("does not show empty state while loading", () => {
    render(<ChapterList {...defaultProps} loading={true} />);

    expect(screen.queryByText("No chapters available yet.")).not.toBeInTheDocument();
  });

  it("does not render table body rows while loading", () => {
    render(<ChapterList {...defaultProps} loading={true} />);

    // Should be 5 skeleton rows, no actual chapter rows
    const rows = document.querySelectorAll("tbody tr");
    expect(rows.length).toBe(5);
    rows.forEach((row) => {
      expect(row.classList.contains("animate-pulse")).toBe(true);
    });
  });

  // ── Empty state ───────────────────────────────────────────────────────────

  it("shows empty state when no chapters", () => {
    render(<ChapterList {...defaultProps} chapters={[]} totalItems={0} />);

    expect(screen.getByText("No chapters available yet.")).toBeInTheDocument();
  });

  it("does not render table when empty", () => {
    render(<ChapterList {...defaultProps} chapters={[]} totalItems={0} />);

    expect(screen.queryByRole("table")).not.toBeInTheDocument();
  });

  it("does not render pagination when empty", () => {
    render(<ChapterList {...defaultProps} chapters={[]} totalItems={0} />);

    expect(screen.queryByTestId("pagination-mock")).not.toBeInTheDocument();
  });

  // ── Error state ───────────────────────────────────────────────────────────

  it("shows error state with retry button", () => {
    render(
      <ChapterList
        {...defaultProps}
        error="Failed to load chapters"
      />,
    );

    expect(screen.getByText("Failed to load chapters")).toBeInTheDocument();
    expect(screen.getByText("Retry")).toBeInTheDocument();
  });

  it("calls onRetry when retry button clicked", async () => {
    const onRetry = vi.fn();
    const user = userEvent.setup();

    render(
      <ChapterList
        {...defaultProps}
        error="Failed"
        onRetry={onRetry}
      />,
    );

    await user.click(screen.getByText("Retry"));
    expect(onRetry).toHaveBeenCalledOnce();
  });

  it("does not show table when error", () => {
    render(
      <ChapterList
        {...defaultProps}
        error="Failed"
      />,
    );

    expect(screen.queryByRole("table")).not.toBeInTheDocument();
  });

  it("does not show pagination when error", () => {
    render(
      <ChapterList
        {...defaultProps}
        error="Failed"
        totalPages={5}
      />,
    );

    expect(screen.queryByTestId("pagination-mock")).not.toBeInTheDocument();
  });

  // ── Success state ─────────────────────────────────────────────────────────

  it("renders chapter rows with correct values", () => {
    const chapters = [
      buildChapter({ id: "ch-1", volume: "1", chapterNumber: "1", title: "First Chapter" }),
      buildChapter({ id: "ch-2", volume: "1", chapterNumber: "2", title: "Second Chapter" }),
    ];

    render(
      <ChapterList
        {...defaultProps}
        chapters={chapters}
        totalItems={2}
      />,
    );

    expect(screen.getByText("First Chapter")).toBeInTheDocument();
    expect(screen.getByText("Second Chapter")).toBeInTheDocument();
    // Volume "1" appears in both rows, chapter "1" in first row = 3 total
    expect(screen.getAllByText("1").length).toBe(3);
    expect(screen.getByText("2")).toBeInTheDocument();
  });

  it("shows chapter count in heading", () => {
    const chapters = [buildChapter({ id: "ch-1" })];

    render(
      <ChapterList
        {...defaultProps}
        chapters={chapters}
        totalItems={1}
      />,
    );

    expect(screen.getByText("Chapters (1)")).toBeInTheDocument();
  });

  it("renders the chapter table with proper structure", () => {
    const chapters = [buildChapter({ id: "ch-1" })];

    render(
      <ChapterList
        {...defaultProps}
        chapters={chapters}
        totalItems={1}
      />,
    );

    const table = screen.getByRole("table");
    expect(table).toBeInTheDocument();
  });

  // ── Date formatting ───────────────────────────────────────────────────────

  it("formats date correctly", () => {
    const chapters = [
      buildChapter({ id: "ch-1", createdAt: "2024-01-15T12:00:00+00:00" }),
    ];

    render(
      <ChapterList
        {...defaultProps}
        chapters={chapters}
        totalItems={1}
      />,
    );

    expect(screen.getByText("Jan 15, 2024")).toBeInTheDocument();
  });

  // ── Null values ───────────────────────────────────────────────────────────

  it("shows — for null volume", () => {
    const chapters = [buildChapter({ id: "ch-1", volume: null })];

    render(
      <ChapterList
        {...defaultProps}
        chapters={chapters}
        totalItems={1}
      />,
    );

    const dashes = screen.getAllByText("—");
    expect(dashes.length).toBeGreaterThanOrEqual(1);
  });

  it("shows — for null title", () => {
    const chapters = [buildChapter({ id: "ch-1", title: null })];

    render(
      <ChapterList
        {...defaultProps}
        chapters={chapters}
        totalItems={1}
      />,
    );

    const dashes = screen.getAllByText("—");
    expect(dashes.length).toBeGreaterThanOrEqual(1);
  });

  it("shows — for null scanlation group", () => {
    const chapters = [
      buildChapter({ id: "ch-1", scanlationGroup: null }),
    ];

    render(
      <ChapterList
        {...defaultProps}
        chapters={chapters}
        totalItems={1}
      />,
    );

    const dashes = screen.getAllByText("—");
    expect(dashes.length).toBeGreaterThanOrEqual(1);
  });

  // ── Sort interaction ─────────────────────────────────────────────────────

  it("calls onSortChange with correct field when sortable header clicked", async () => {
    const onSortChange = vi.fn();
    const user = userEvent.setup();

    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
        onSortChange={onSortChange}
      />,
    );

    // Click "Vol." header
    await user.click(screen.getByText("Vol."));
    expect(onSortChange).toHaveBeenCalledWith("volume");
  });

  it("calls onSortChange with chapterNumber when Ch. header clicked", async () => {
    const onSortChange = vi.fn();
    const user = userEvent.setup();

    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
        onSortChange={onSortChange}
      />,
    );

    await user.click(screen.getByText("Ch."));
    expect(onSortChange).toHaveBeenCalledWith("chapterNumber");
  });

  it("calls onSortChange with createdAt when Date header clicked", async () => {
    const onSortChange = vi.fn();
    const user = userEvent.setup();

    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
        onSortChange={onSortChange}
      />,
    );

    await user.click(screen.getByLabelText(/Sort by Date/));
    expect(onSortChange).toHaveBeenCalledWith("createdAt");
  });

  // ── Sort dropdown ──────────────────────────────────────────────────────

  it("shows sort dropdown when chapters are present", () => {
    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
      />,
    );

    expect(screen.getByLabelText("Sort chapters by")).toBeInTheDocument();
    expect(screen.getByLabelText(/Switch to/)).toBeInTheDocument();
  });

  it("hides sort dropdown while loading", () => {
    render(
      <ChapterList
        {...defaultProps}
        loading={true}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
      />,
    );

    expect(screen.queryByLabelText("Sort chapters by")).not.toBeInTheDocument();
  });

  it("hides sort dropdown on error", () => {
    render(
      <ChapterList
        {...defaultProps}
        error="Error"
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
      />,
    );

    expect(screen.queryByLabelText("Sort chapters by")).not.toBeInTheDocument();
  });

  it("hides sort dropdown when no chapters", () => {
    render(<ChapterList {...defaultProps} chapters={[]} totalItems={0} />);

    expect(screen.queryByLabelText("Sort chapters by")).not.toBeInTheDocument();
  });

  it("calls onSortChange when dropdown value changes", async () => {
    const onSortChange = vi.fn();
    const user = userEvent.setup();

    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
        onSortChange={onSortChange}
      />,
    );

    await user.selectOptions(screen.getByLabelText("Sort chapters by"), "volume");
    expect(onSortChange).toHaveBeenCalledWith("volume");
  });

  it("calls onSortChange with current field when direction toggle clicked", async () => {
    const onSortChange = vi.fn();
    const user = userEvent.setup();

    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
        sortField="chapterNumber"
        sortDir="asc"
        onSortChange={onSortChange}
      />,
    );

    await user.click(screen.getByLabelText(/Switch to descending order/));
    expect(onSortChange).toHaveBeenCalledWith("chapterNumber");
  });

  it("direction toggle label reflects current direction", () => {
    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
        sortField="chapterNumber"
        sortDir="desc"
      />,
    );

    expect(screen.getByLabelText(/Switch to ascending order/)).toBeInTheDocument();
  });

  // ── Filter inputs ───────────────────────────────────────────────────────

  it("calls onFilterChange when from input changes", async () => {
    const onFilterChange = vi.fn();
    const user = userEvent.setup();

    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1", chapterNumber: "5" })]}
        totalItems={1}
        onFilterChange={onFilterChange}
      />,
    );

    const fromInput = screen.getByLabelText("Filter from chapter");
    await user.type(fromInput, "3");
    expect(onFilterChange).toHaveBeenCalledWith("3", "");
  });

  it("calls onFilterChange when to input changes", async () => {
    const onFilterChange = vi.fn();
    const user = userEvent.setup();

    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1", chapterNumber: "5" })]}
        totalItems={1}
        onFilterChange={onFilterChange}
      />,
    );

    const toInput = screen.getByLabelText("Filter to chapter");
    fireEvent.change(toInput, { target: { value: "10" } });
    expect(onFilterChange).toHaveBeenLastCalledWith("", "10");
  });

  it("shows filter inputs when chapters are present", () => {
    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
      />,
    );

    expect(screen.getByLabelText("Filter from chapter")).toBeInTheDocument();
    expect(screen.getByLabelText("Filter to chapter")).toBeInTheDocument();
  });

  it("hides filter inputs while loading", () => {
    render(
      <ChapterList
        {...defaultProps}
        loading={true}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
      />,
    );

    expect(screen.queryByLabelText("Filter from chapter")).not.toBeInTheDocument();
  });

  it("hides filter inputs on error", () => {
    render(
      <ChapterList
        {...defaultProps}
        error="Error"
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
      />,
    );

    expect(screen.queryByLabelText("Filter from chapter")).not.toBeInTheDocument();
  });

  it("hides filter inputs when no chapters", () => {
    render(<ChapterList {...defaultProps} chapters={[]} totalItems={0} />);

    expect(screen.queryByLabelText("Filter from chapter")).not.toBeInTheDocument();
  });

  it("shows sort indicator on active sort column", () => {
    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
        sortField="volume"
        sortDir="asc"
      />,
    );

    // The Vol. header should have an active sort indicator (ascending triangle)
    const volButton = screen.getByLabelText(/Sort by Vol\./);
    expect(volButton).toBeInTheDocument();
    // Check aria label includes ascending
    expect(volButton.getAttribute("aria-label")).toContain("ascending");
  });

  it("shows correct aria label for sort direction", () => {
    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
        sortField="volume"
        sortDir="desc"
      />,
    );

    const volButton = screen.getByLabelText(/Sort by Vol\./);
    expect(volButton.getAttribute("aria-label")).toContain("descending");
  });

  // ── Row links ─────────────────────────────────────────────────────────────

  it("each chapter row links to /chapter/{id} when no mangaId", () => {
    const chapters = [
      buildChapter({ id: "ch-42", title: "Awesome Chapter" }),
    ];

    render(
      <ChapterList
        {...defaultProps}
        chapters={chapters}
        totalItems={1}
      />,
    );

    const link = screen.getByText("Awesome Chapter").closest("a");
    expect(link).toHaveAttribute("href", "/chapter/ch-42");
  });

  it("each chapter row links to /manga/{mangaId}/chapter/{id} when mangaId provided", () => {
    const chapters = [
      buildChapter({ id: "ch-42", title: "Awesome Chapter" }),
    ];

    render(
      <ChapterList
        {...defaultProps}
        chapters={chapters}
        totalItems={1}
        mangaId="manga-99"
      />,
    );

    const link = screen.getByText("Awesome Chapter").closest("a");
    expect(link).toHaveAttribute("href", "/manga/manga-99/chapter/ch-42");
  });

  // ── Pagination ────────────────────────────────────────────────────────────

  it("shows pagination when totalPages > 1", () => {
    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={21}
        totalPages={2}
      />,
    );

    expect(screen.getByTestId("pagination-mock")).toBeInTheDocument();
    expect(screen.getByTestId("page-info")).toHaveTextContent("1/2");
  });

  it("hides pagination when totalPages is 1", () => {
    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
        totalPages={1}
      />,
    );

    expect(screen.queryByTestId("pagination-mock")).not.toBeInTheDocument();
  });

  it("calls onPageChange when pagination page changes", async () => {
    const onPageChange = vi.fn();
    const user = userEvent.setup();

    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={21}
        totalPages={2}
        onPageChange={onPageChange}
      />,
    );

    await user.click(screen.getByTestId("next-page-btn"));
    expect(onPageChange).toHaveBeenCalledWith(2);
  });

  // ── Edge cases ────────────────────────────────────────────────────────────

  it("handles single chapter correctly", () => {
    const chapters = [buildChapter({ id: "ch-1", chapterNumber: "1" })];

    render(
      <ChapterList
        {...defaultProps}
        chapters={chapters}
        totalItems={1}
      />,
    );

    expect(screen.getByText("Chapters (1)")).toBeInTheDocument();
    // Volume "1" and chapter "1" = 2 elements
    expect(screen.getAllByText("1").length).toBe(2);
  });

  it("does not show error when loading is true even if error is set", () => {
    // Loading takes precedence
    render(
      <ChapterList
        {...defaultProps}
        loading={true}
        error="Some error"
      />,
    );

    expect(screen.queryByText("Retry")).not.toBeInTheDocument();
    // Should show skeleton instead
    const rows = document.querySelectorAll("tbody tr.animate-pulse");
    expect(rows.length).toBe(5);
  });

  it("default onFilterChange does not throw when filters are used", () => {
    render(
      <ChapterList
        {...defaultProps}
        chapters={[buildChapter({ id: "ch-1" })]}
        totalItems={1}
      />,
    );

    const fromInput = screen.getByLabelText("Filter from chapter");
    expect(() => fireEvent.change(fromInput, { target: { value: "3" } })).not.toThrow();
  });
});
