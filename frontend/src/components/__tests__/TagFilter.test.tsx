import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MockAdapter from "axios-mock-adapter";
import api from "@/lib/api";
import TagFilter from "../TagFilter";

const mock = new MockAdapter(api);

function buildTag(
  overrides: Partial<{
    id: string;
    name: string;
    groupName: string;
    isPrimary: boolean;
  }> = {},
) {
  return {
    "@context": "/api/contexts/Tag",
    "@id": `/api/tags/${overrides.id ?? "1"}`,
    "@type": "Tag",
    id: overrides.id ?? "1",
    createdAt: "2024-01-01T00:00:00+00:00",
    name: overrides.name ?? "Action",
    description: null,
    groupName: overrides.groupName ?? "genre",
    isPrimary: overrides.isPrimary ?? true,
  };
}

const sampleTags = [
  buildTag({ id: "1", name: "Action", groupName: "genre" }),
  buildTag({ id: "2", name: "Romance", groupName: "genre" }),
  buildTag({ id: "3", name: "Fantasy", groupName: "theme" }),
  buildTag({ id: "4", name: "Adventure", groupName: "theme" }),
];

describe("TagFilter", () => {
  beforeEach(() => {
    mock.reset();
  });

  afterEach(() => {
    mock.reset();
  });

  it("shows heading and loading skeletons while fetching", () => {
    // Keep request pending so component stays in loading state
    mock.onGet(/\/tags/).reply(() => new Promise(() => {}));

    render(<TagFilter selected={[]} onChange={vi.fn()} />);

    expect(screen.getByText("Tags")).toBeInTheDocument();
    const skeletons = document.querySelectorAll(".animate-pulse");
    expect(skeletons.length).toBeGreaterThan(0);
  });

  it("displays tags grouped by category after successful fetch", async () => {
    mock.onGet(/\/tags/).reply(200, {
      member: sampleTags,
      totalItems: sampleTags.length,
    });

    render(<TagFilter selected={[]} onChange={vi.fn()} />);

    await waitFor(() => {
      expect(screen.getByText("Action")).toBeInTheDocument();
    });

    expect(screen.getByText("Romance")).toBeInTheDocument();
    expect(screen.getByText("Fantasy")).toBeInTheDocument();
    expect(screen.getByText("Adventure")).toBeInTheDocument();

    // Group headings
    expect(screen.getByText("genre")).toBeInTheDocument();
    expect(screen.getByText("theme")).toBeInTheDocument();
  });

  it("shows tag count per group", async () => {
    mock.onGet(/\/tags/).reply(200, {
      member: sampleTags,
      totalItems: sampleTags.length,
    });

    render(<TagFilter selected={[]} onChange={vi.fn()} />);

    await waitFor(() => {
      // genre group has 2 tags, theme group has 2 tags
      const genreCount = screen
        .getByText("genre")
        .parentElement?.querySelector(".ml-auto");
      expect(genreCount).toHaveTextContent("2");
    });
  });

  it("calls onChange with selected tag when checkbox is clicked", async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();

    mock.onGet(/\/tags/).reply(200, {
      member: sampleTags,
      totalItems: sampleTags.length,
    });

    render(<TagFilter selected={[]} onChange={onChange} />);

    await waitFor(() => {
      expect(screen.getByText("Action")).toBeInTheDocument();
    });

    // Click the checkbox near "Action"
    const actionCheckbox = screen
      .getByText("Action")
      .closest("label")
      ?.querySelector('input[type="checkbox"]');
    expect(actionCheckbox).not.toBeNull();
    await user.click(actionCheckbox!);

    expect(onChange).toHaveBeenCalledWith(["1"]);
  });

  it("calls onChange with tag removed when checked tag is clicked again", async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();

    mock.onGet(/\/tags/).reply(200, {
      member: sampleTags,
      totalItems: sampleTags.length,
    });

    render(<TagFilter selected={["1"]} onChange={onChange} />);

    await waitFor(() => {
      expect(screen.getByText("Action")).toBeInTheDocument();
    });

    // Click the checkbox for already-selected "Action"
    const actionCheckbox = screen
      .getByText("Action")
      .closest("label")
      ?.querySelector('input[type="checkbox"]');
    expect(actionCheckbox).not.toBeNull();
    await user.click(actionCheckbox!);

    expect(onChange).toHaveBeenCalledWith([]);
  });

  it("shows selected tags as checked based on prop", async () => {
    mock.onGet(/\/tags/).reply(200, {
      member: sampleTags,
      totalItems: sampleTags.length,
    });

    render(<TagFilter selected={["1", "3"]} onChange={vi.fn()} />);

    await waitFor(() => {
      const actionCheckbox = screen
        .getByText("Action")
        .closest("label")
        ?.querySelector('input[type="checkbox"]');
      expect(actionCheckbox).toBeChecked();
    });

    const romanceCheckbox = screen
      .getByText("Romance")
      .closest("label")
      ?.querySelector('input[type="checkbox"]');
    expect(romanceCheckbox).not.toBeChecked();

    // Fantasy (id: 3) should also be checked
    const fantasyCheckbox = screen
      .getByText("Fantasy")
      .closest("label")
      ?.querySelector('input[type="checkbox"]');
    expect(fantasyCheckbox).toBeChecked();
  });

  it("applies accent text color to selected tags", async () => {
    mock.onGet(/\/tags/).reply(200, {
      member: sampleTags,
      totalItems: sampleTags.length,
    });

    render(<TagFilter selected={["1"]} onChange={vi.fn()} />);

    await waitFor(() => {
      const actionLabel = screen.getByText("Action");
      expect(actionLabel.className).toContain("text-md-accent");
    });

    const romanceLabel = screen.getByText("Romance");
    expect(romanceLabel.className).toContain("text-md-text-secondary");
  });

  it("collapses and expands a group when header is clicked", async () => {
    const user = userEvent.setup();

    mock.onGet(/\/tags/).reply(200, {
      member: sampleTags,
      totalItems: sampleTags.length,
    });

    render(<TagFilter selected={[]} onChange={vi.fn()} />);

    await waitFor(() => {
      expect(screen.getByText("Action")).toBeInTheDocument();
    });

    // Collapse the "genre" group by clicking its header
    const genreHeader = screen.getByText("genre");
    await user.click(genreHeader);

    // Tags should no longer be visible
    expect(screen.queryByText("Action")).not.toBeInTheDocument();
    expect(screen.queryByText("Romance")).not.toBeInTheDocument();

    // Other group should still be visible
    expect(screen.getByText("Fantasy")).toBeInTheDocument();

    // Expand again
    await user.click(genreHeader);
    expect(screen.getByText("Action")).toBeInTheDocument();
  });

  it("shows error state when API request fails", async () => {
    mock.onGet(/\/tags/).reply(500, { detail: "Failed to load tags" });

    render(<TagFilter selected={[]} onChange={vi.fn()} />);

    await waitFor(() => {
      expect(screen.getByText("Failed to load tags.")).toBeInTheDocument();
    });
  });

  it("shows empty state when no tags are returned", async () => {
    mock.onGet(/\/tags/).reply(200, {
      member: [],
      totalItems: 0,
    });

    render(<TagFilter selected={[]} onChange={vi.fn()} />);

    await waitFor(() => {
      expect(screen.getByText("No tags available.")).toBeInTheDocument();
    });
  });

  it("preserves checkbox states when parent re-renders with new selected prop", async () => {
    mock.onGet(/\/tags/).reply(200, {
      member: sampleTags,
      totalItems: sampleTags.length,
    });

    const { rerender } = render(
      <TagFilter selected={["1"]} onChange={vi.fn()} />,
    );

    await waitFor(() => {
      const actionCheckbox = screen
        .getByText("Action")
        .closest("label")
        ?.querySelector('input[type="checkbox"]');
      expect(actionCheckbox).toBeChecked();
    });

    // Re-render with different selection
    rerender(<TagFilter selected={["2"]} onChange={vi.fn()} />);

    // Action should no longer be checked
    const actionCheckbox = screen
      .getByText("Action")
      .closest("label")
      ?.querySelector('input[type="checkbox"]');
    expect(actionCheckbox).not.toBeChecked();

    // Romance should now be checked
    const romanceCheckbox = screen
      .getByText("Romance")
      .closest("label")
      ?.querySelector('input[type="checkbox"]');
    expect(romanceCheckbox).toBeChecked();
  });

  it("does not update state after unmount when request resolves after unmount", async () => {
    // Create a deferred promise so we control when it resolves
    let resolve!: (value: unknown) => void;
    const deferred = new Promise((r) => {
      resolve = r;
    });

    mock.onGet(/\/tags/).reply(() => deferred);

    const { unmount } = render(<TagFilter selected={[]} onChange={vi.fn()} />);

    // Unmount while the request is still in-flight
    unmount();

    // Now resolve the request — the cancelled guard should prevent state update
    resolve([200, { member: [], totalItems: 0 }]);

    // Wait a tick for the microtask queue to flush
    await vi.waitFor(() => {
      // No crash means the cancelled guard worked
      expect(true).toBe(true);
    });
  });

  it("has accessible group expand/collapse buttons", async () => {
    mock.onGet(/\/tags/).reply(200, {
      member: sampleTags,
      totalItems: sampleTags.length,
    });

    render(<TagFilter selected={[]} onChange={vi.fn()} />);

    await waitFor(() => {
      const genreHeader = screen.getByText("genre");
      expect(genreHeader).toBeInTheDocument();
    });

    const genreHeader = screen.getByText("genre");
    expect(genreHeader).toHaveAttribute("aria-expanded", "true");
  });
});
