import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MockAdapter from "axios-mock-adapter";
import api from "@/lib/api";
import ListsContent from "../ListsContent";
import type { CustomList } from "@/lib/types";

// ── Mock router — stable object to avoid infinite re-render loop ─────────────

const mockRouter = vi.hoisted(() => ({ replace: vi.fn() }));

vi.mock("next/navigation", () => ({
  useRouter: () => mockRouter,
}));

// ── Mock next/link ───────────────────────────────────────────────────────────

vi.mock("next/link", () => ({
  default: ({ href, children }: { href: string; children: React.ReactNode }) => (
    <a href={href}>{children}</a>
  ),
}));

// ── Mock AuthContext ─────────────────────────────────────────────────────────

const mockAuthUser = vi.hoisted(() => ({
  current: null as null | { id: string; email: string; username: string },
}));

vi.mock("@/contexts/AuthContext", () => ({
  useAuth: () => ({
    user: mockAuthUser.current,
    loading: false,
  }),
}));

const TEST_USER = { id: "u-1", email: "test@example.com", username: "tester" };

// ── Fixtures ─────────────────────────────────────────────────────────────────

function makeList(overrides: Partial<CustomList> = {}): CustomList {
  return {
    "@context": "/api/contexts/CustomList",
    "@id": "/api/custom_lists/cl-1",
    "@type": "CustomList",
    id: "cl-1",
    createdAt: "2024-01-01T00:00:00+00:00",
    name: "My Reading List",
    visibility: "private",
    user: "/api/users/u-1",
    mangas: [],
    ...overrides,
  };
}

// ── Tests ─────────────────────────────────────────────────────────────────────

describe("ListsContent", () => {
  let mock: MockAdapter;
  const user = userEvent.setup();

  beforeEach(() => {
    mock = new MockAdapter(api);
    mockAuthUser.current = TEST_USER;
    mockRouter.replace.mockReset();
  });

  afterEach(() => {
    mock.restore();
    mockAuthUser.current = null;
  });

  it("changes visibility in the create form via VisibilitySelect", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });
    const newList = makeList({ name: "Test", visibility: "public" });
    mock.onPost("/custom_lists").reply(201, newList);

    render(<ListsContent />);
    await waitFor(() => screen.getByText(/No lists yet/));

    await user.click(screen.getByRole("button", { name: /new list/i }));
    const select = await screen.findByRole("combobox");
    await user.selectOptions(select, "public");

    const input = screen.getByPlaceholderText("List name");
    await user.type(input, "Test");
    await user.click(screen.getByRole("button", { name: /^create$/i }));

    await waitFor(() => {
      expect(screen.getByText("Test")).toBeInTheDocument();
    });
  });

  it("redirects to /login when unauthenticated", async () => {
    mockAuthUser.current = null;
    render(<ListsContent />);

    await waitFor(() => {
      expect(mockRouter.replace).toHaveBeenCalledWith("/login");
    });
  });

  it("shows skeleton while loading", () => {
    mock.onGet("/users/u-1/custom_lists").reply(() => new Promise(() => {}));
    render(<ListsContent />);

    const pulses = document.querySelectorAll(".animate-pulse");
    expect(pulses.length).toBeGreaterThan(0);
  });

  it("renders list of custom lists", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, {
      member: [makeList()],
      totalItems: 1,
    });

    render(<ListsContent />);

    await waitFor(() => {
      expect(screen.getByText("My Reading List")).toBeInTheDocument();
    });
    expect(screen.getByText("private")).toBeInTheDocument();
  });

  it("shows empty state when no lists", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });

    render(<ListsContent />);

    await waitFor(() => {
      expect(screen.getByText(/No lists yet/)).toBeInTheDocument();
    });
  });

  it("shows error state on fetch failure", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(500, { detail: "Server error" });

    render(<ListsContent />);

    await waitFor(() => {
      expect(screen.getByText("Server error")).toBeInTheDocument();
    });
    expect(screen.getByRole("button", { name: /try again/i })).toBeInTheDocument();
  });

  it("opens create form when 'New List' is clicked", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });

    render(<ListsContent />);
    await waitFor(() => screen.getByText(/No lists yet/));

    await user.click(screen.getByRole("button", { name: /new list/i }));

    await waitFor(() => {
      expect(screen.getByPlaceholderText("List name")).toBeInTheDocument();
    });
  });

  it("creates a list and appends it to the list", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });
    const newList = makeList({ name: "Action Manga" });
    mock.onPost("/custom_lists").reply(201, newList);

    render(<ListsContent />);
    await waitFor(() => screen.getByText(/No lists yet/));

    await user.click(screen.getByRole("button", { name: /new list/i }));
    const input = await screen.findByPlaceholderText("List name");
    await user.type(input, "Action Manga");
    await user.click(screen.getByRole("button", { name: /^create$/i }));

    await waitFor(() => {
      expect(screen.getByText("Action Manga")).toBeInTheDocument();
    });
  });

  it("enters edit mode for a list", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, {
      member: [makeList()],
      totalItems: 1,
    });

    render(<ListsContent />);
    await waitFor(() => screen.getByText("My Reading List"));

    await user.click(screen.getByRole("button", { name: /edit list/i }));

    await waitFor(() => {
      expect(screen.getByDisplayValue("My Reading List")).toBeInTheDocument();
    });
  });

  it("shows delete confirm prompt when delete is clicked", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, {
      member: [makeList()],
      totalItems: 1,
    });

    render(<ListsContent />);
    await waitFor(() => screen.getByText("My Reading List"));

    await user.click(screen.getByRole("button", { name: /delete list/i }));

    // Confirm prompt replaces the row — Cancel + Delete buttons appear
    await waitFor(() => {
      expect(screen.getByRole("button", { name: /cancel/i })).toBeInTheDocument();
    });
    expect(screen.getByRole("button", { name: /^delete$/i })).toBeInTheDocument();
  });

  it("deletes a list after confirming", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, {
      member: [makeList()],
      totalItems: 1,
    });
    mock.onDelete("/custom_lists/cl-1").reply(204);

    render(<ListsContent />);
    await waitFor(() => screen.getByText("My Reading List"));

    await user.click(screen.getByRole("button", { name: /delete list/i }));
    const confirmBtn = await screen.findByRole("button", { name: /^delete$/i });
    await user.click(confirmBtn);

    await waitFor(() => {
      expect(screen.queryByText("My Reading List")).not.toBeInTheDocument();
    });
  });

  it("submits edit form and updates the list", async () => {
    const updatedList = makeList({ name: "Updated Name", visibility: "public" });
    mock.onGet("/users/u-1/custom_lists").reply(200, {
      member: [makeList()],
      totalItems: 1,
    });
    mock.onPut("/custom_lists/cl-1").reply(200, updatedList);

    render(<ListsContent />);
    await waitFor(() => screen.getByText("My Reading List"));

    await user.click(screen.getByRole("button", { name: /edit list/i }));
    const input = await screen.findByDisplayValue("My Reading List");
    await user.clear(input);
    await user.type(input, "Updated Name");
    await user.click(screen.getByRole("button", { name: /^save$/i }));

    await waitFor(() => {
      expect(screen.getByText("Updated Name")).toBeInTheDocument();
    });
    expect(screen.queryByDisplayValue("Updated Name")).not.toBeInTheDocument();
  });

  it("shows edit error when update API fails", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, {
      member: [makeList()],
      totalItems: 1,
    });
    mock.onPut("/custom_lists/cl-1").reply(422, { detail: "Validation failed" });

    render(<ListsContent />);
    await waitFor(() => screen.getByText("My Reading List"));

    await user.click(screen.getByRole("button", { name: /edit list/i }));
    await screen.findByDisplayValue("My Reading List");
    await user.click(screen.getByRole("button", { name: /^save$/i }));

    await waitFor(() => {
      expect(screen.getByText("Validation failed")).toBeInTheDocument();
    });
  });

  it("cancels the edit form", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, {
      member: [makeList()],
      totalItems: 1,
    });

    render(<ListsContent />);
    await waitFor(() => screen.getByText("My Reading List"));

    await user.click(screen.getByRole("button", { name: /edit list/i }));
    await screen.findByDisplayValue("My Reading List");

    await user.click(screen.getByRole("button", { name: /cancel/i }));

    await waitFor(() => {
      expect(screen.queryByDisplayValue("My Reading List")).not.toBeInTheDocument();
    });
    expect(screen.getByText("My Reading List")).toBeInTheDocument();
  });

  it("shows create error when list creation API fails", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });
    mock.onPost("/custom_lists").reply(422, { detail: "Name too long" });

    render(<ListsContent />);
    await waitFor(() => screen.getByText(/No lists yet/));

    await user.click(screen.getByRole("button", { name: /new list/i }));
    const input = await screen.findByPlaceholderText("List name");
    await user.type(input, "Bad Name");
    await user.click(screen.getByRole("button", { name: /^create$/i }));

    await waitFor(() => {
      expect(screen.getByText("Name too long")).toBeInTheDocument();
    });
  });

  it("cancels the create form", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });

    render(<ListsContent />);
    await waitFor(() => screen.getByText(/No lists yet/));

    await user.click(screen.getByRole("button", { name: /new list/i }));
    await screen.findByPlaceholderText("List name");

    await user.click(screen.getByRole("button", { name: /^cancel$/i }));

    await waitFor(() => {
      expect(screen.queryByPlaceholderText("List name")).not.toBeInTheDocument();
    });
  });

  it("retries fetch when try again is clicked", async () => {
    let callCount = 0;
    mock.onGet("/users/u-1/custom_lists").reply(() => {
      callCount++;
      if (callCount === 1) return [500, { detail: "Server error" }];
      return [200, { member: [makeList()], totalItems: 1 }];
    });

    render(<ListsContent />);
    await waitFor(() => screen.getByText("Server error"));

    await user.click(screen.getByRole("button", { name: /try again/i }));

    await waitFor(() => {
      expect(screen.getByText("My Reading List")).toBeInTheDocument();
    });
  });

  it("cancels the delete prompt", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, {
      member: [makeList()],
      totalItems: 1,
    });

    render(<ListsContent />);
    await waitFor(() => screen.getByText("My Reading List"));

    await user.click(screen.getByRole("button", { name: /delete list/i }));
    const cancelBtn = await screen.findByRole("button", { name: /cancel/i });
    await user.click(cancelBtn);

    expect(screen.getByText("My Reading List")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /^delete$/i })).not.toBeInTheDocument();
  });

  it("does not submit edit when name is empty or whitespace", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, {
      member: [makeList()],
      totalItems: 1,
    });

    render(<ListsContent />);
    await waitFor(() => screen.getByText("My Reading List"));

    await user.click(screen.getByRole("button", { name: /edit list/i }));
    const input = await screen.findByDisplayValue("My Reading List");
    await user.clear(input);

    // Button should be disabled when name is empty
    const saveBtn = screen.getByRole("button", { name: /^save$/i });
    expect(saveBtn).toBeDisabled();
  });

  it("preserves unedited list when updating one of multiple lists", async () => {
    const list1 = makeList({ id: "cl-1", name: "List One" });
    const list2 = makeList({ id: "cl-2", name: "List Two" });
    const updated = makeList({ id: "cl-1", name: "Updated One" });
    mock.onGet("/users/u-1/custom_lists").reply(200, {
      member: [list1, list2],
      totalItems: 2,
    });
    mock.onPut("/custom_lists/cl-1").reply(200, updated);

    render(<ListsContent />);
    await waitFor(() => screen.getByText("List One"));

    // Find the edit button for "List One" specifically
    const editBtns = screen.getAllByRole("button", { name: /edit list/i });
    await user.click(editBtns[0]);
    const input = await screen.findByDisplayValue("List One");
    await user.clear(input);
    await user.type(input, "Updated One");
    await user.click(screen.getByRole("button", { name: /^save$/i }));

    await waitFor(() => {
      expect(screen.getByText("Updated One")).toBeInTheDocument();
    });
    expect(screen.getByText("List Two")).toBeInTheDocument();
  });

  it("keeps list when deletion fails", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, {
      member: [makeList()],
      totalItems: 1,
    });
    mock.onDelete("/custom_lists/cl-1").reply(500, { detail: "Delete error" });

    render(<ListsContent />);
    await waitFor(() => screen.getByText("My Reading List"));

    await user.click(screen.getByRole("button", { name: /delete list/i }));
    const confirmBtn = await screen.findByRole("button", { name: /^delete$/i });
    await user.click(confirmBtn);

    await waitFor(() => {
      expect(screen.getByText("My Reading List")).toBeInTheDocument();
    });
  });

  it("shows singular manga label when list has exactly one manga", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, {
      member: [makeList({ mangas: [{ id: "m-1", title: "Solo Manga", status: "ongoing", contentRating: "safe", demographic: "seinen", year: 2020 }] })],
      totalItems: 1,
    });

    render(<ListsContent />);

    await waitFor(() => {
      expect(screen.getByText("1 manga")).toBeInTheDocument();
    });
  });

  it("shows zero manga count when mangas field is undefined", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, {
      member: [makeList({ mangas: undefined })],
      totalItems: 1,
    });

    render(<ListsContent />);

    await waitFor(() => {
      expect(screen.getByText("0 mangas")).toBeInTheDocument();
    });
  });
});
