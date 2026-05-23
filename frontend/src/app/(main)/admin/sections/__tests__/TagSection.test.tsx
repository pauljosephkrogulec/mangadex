import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor, fireEvent } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MockAdapter from "axios-mock-adapter";
import api from "@/lib/api";
import TagSection from "../TagSection";
import type { Tag } from "@/lib/types";

// ── Fixtures ──────────────────────────────────────────────────────────────────

const TAG_1: Tag = {
  "@context": "",
  "@id": "/api/tags/t-1",
  "@type": "Tag",
  id: "t-1",
  name: "Action",
  groupName: "genre",
  description: "Action manga",
  isPrimary: true,
  createdAt: "2024-01-01T00:00:00+00:00",
};

const TAG_2: Tag = {
  "@context": "",
  "@id": "/api/tags/t-2",
  "@type": "Tag",
  id: "t-2",
  name: "Comedy",
  groupName: "theme",
  description: null,
  isPrimary: false,
  createdAt: "2024-02-01T00:00:00+00:00",
};

function hydra<T>(member: T[], totalItems: number) {
  return {
    "@context": "",
    "@id": "",
    "@type": "hydra:Collection",
    totalItems,
    member,
  };
}

// ── Tests ─────────────────────────────────────────────────────────────────────

describe("TagSection", () => {
  let mock: MockAdapter;
  const user = userEvent.setup();

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  // ── 1. List / loading / empty / error ────────────────────────────────────

  it("shows loading state initially", () => {
    mock.onGet("/tags").reply(() => new Promise(() => {}));
    render(<TagSection />);
    expect(screen.getByText("Loading…")).toBeInTheDocument();
  });

  it("renders tag list after successful fetch", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1, TAG_2], 2));
    render(<TagSection />);

    await waitFor(() => screen.getByText("Action"));
    expect(screen.getByText("genre")).toBeInTheDocument();
    expect(screen.getByText("Yes")).toBeInTheDocument();
    expect(screen.getByText("Comedy")).toBeInTheDocument();
    expect(screen.getByText("theme")).toBeInTheDocument();
    expect(screen.getByText("No")).toBeInTheDocument();
  });

  it("shows empty state when no tags", async () => {
    mock.onGet("/tags").reply(200, hydra([], 0));
    render(<TagSection />);

    await waitFor(() => screen.getByText("No tags yet."));
  });

  it("shows fetch error on failure", async () => {
    mock.onGet("/tags").reply(500, { detail: "Server error" });
    render(<TagSection />);

    await waitFor(() => screen.getByText("Server error"));
  });

  it("renders header with tag count", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 1));
    render(<TagSection />);

    await waitFor(() => screen.getByText("Tags (1)"));
  });

  it("shows 'Yes' for isPrimary=true and 'No' for isPrimary=false", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1, TAG_2], 2));
    render(<TagSection />);

    await waitFor(() => screen.getByText("Action"));
    expect(screen.getByText("Yes")).toBeInTheDocument();
    expect(screen.getByText("No")).toBeInTheDocument();
  });

  // ── 2. Create form ───────────────────────────────────────────────────────

  it("toggles create form open with '+ New Tag'", async () => {
    mock.onGet("/tags").reply(200, hydra([], 0));
    render(<TagSection />);

    await waitFor(() => screen.getByText("No tags yet."));

    expect(screen.queryByText("New Tag")).not.toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "+ New Tag" }));
    expect(screen.getByText("New Tag")).toBeInTheDocument();
  });

  it("toggles create form closed with 'Cancel'", async () => {
    mock.onGet("/tags").reply(200, hydra([], 0));
    render(<TagSection />);

    await waitFor(() => screen.getByText("No tags yet."));

    await user.click(screen.getByRole("button", { name: "+ New Tag" }));
    expect(screen.getByText("New Tag")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Cancel" }));
    expect(screen.queryByText("New Tag")).not.toBeInTheDocument();
  });

  it("disables Create button when name is empty", async () => {
    mock.onGet("/tags").reply(200, hydra([], 0));
    render(<TagSection />);

    await waitFor(() => screen.getByText("No tags yet."));
    await user.click(screen.getByRole("button", { name: "+ New Tag" }));

    await user.type(screen.getByPlaceholderText("Group name (e.g. genre)"), "genre");
    // name is empty → disabled
    expect(screen.getByRole("button", { name: "Create" })).toBeDisabled();
  });

  it("disables Create button when groupName is empty", async () => {
    mock.onGet("/tags").reply(200, hydra([], 0));
    render(<TagSection />);

    await waitFor(() => screen.getByText("No tags yet."));
    await user.click(screen.getByRole("button", { name: "+ New Tag" }));

    await user.type(screen.getByPlaceholderText("Name"), "Action");
    // groupName is empty → disabled
    expect(screen.getByRole("button", { name: "Create" })).toBeDisabled();
  });

  it("creates tag on success, prepends to list, closes form", async () => {
    mock.onGet("/tags").reply(200, hydra([], 0));

    const NEW_TAG: Tag = { ...TAG_1, id: "t-new", name: "Horror" };
    mock.onPost("/tags").reply(201, NEW_TAG);

    render(<TagSection />);
    await waitFor(() => screen.getByText("No tags yet."));

    await user.click(screen.getByRole("button", { name: "+ New Tag" }));

    await user.type(screen.getByPlaceholderText("Name"), "Horror");
    await user.type(screen.getByPlaceholderText("Group name (e.g. genre)"), "genre");

    await user.click(screen.getByRole("button", { name: "Create" }));

    await waitFor(() => screen.getByText("Horror"));
    expect(screen.queryByText("New Tag")).not.toBeInTheDocument();
    expect(screen.getByText("Tags (1)")).toBeInTheDocument();
  });

  it("shows createError on POST failure", async () => {
    mock.onGet("/tags").reply(200, hydra([], 0));
    mock.onPost("/tags").reply(422, { detail: "Name already exists" });

    render(<TagSection />);
    await waitFor(() => screen.getByText("No tags yet."));

    await user.click(screen.getByRole("button", { name: "+ New Tag" }));
    await user.type(screen.getByPlaceholderText("Name"), "Action");
    await user.type(screen.getByPlaceholderText("Group name (e.g. genre)"), "genre");

    await user.click(screen.getByRole("button", { name: "Create" }));

    await waitFor(() => screen.getByText("Name already exists"));
  });

  it("handleCreate early-returns when required fields are empty (fireEvent.submit bypasses disabled button)", async () => {
    mock.onGet("/tags").reply(200, hydra([], 0));
    let postCalled = false;
    mock.onPost("/tags").reply(() => { postCalled = true; return [201, TAG_1]; });

    render(<TagSection />);
    await waitFor(() => screen.getByText("No tags yet."));

    await user.click(screen.getByRole("button", { name: "+ New Tag" }));

    const form = document.querySelector("form")!;
    fireEvent.submit(form);

    expect(postCalled).toBe(false);
    expect(screen.getByText("New Tag")).toBeInTheDocument();
  });

  it("typing in the description field in the create form updates the form", async () => {
    mock.onGet("/tags").reply(200, hydra([], 0));
    render(<TagSection />);

    await waitFor(() => screen.getByText("No tags yet."));
    await user.click(screen.getByRole("button", { name: "+ New Tag" }));

    const descInput = screen.getByPlaceholderText("Description (optional)") as HTMLInputElement;
    await user.type(descInput, "Great genre tag");
    expect(descInput.value).toBe("Great genre tag");
  });

  it("allows toggling isPrimary checkbox in create form", async () => {
    mock.onGet("/tags").reply(200, hydra([], 0));
    render(<TagSection />);

    await waitFor(() => screen.getByText("No tags yet."));
    await user.click(screen.getByRole("button", { name: "+ New Tag" }));

    const checkbox = screen.getAllByRole("checkbox")[0];
    expect(checkbox).not.toBeChecked();
    await user.click(checkbox);
    expect(checkbox).toBeChecked();
  });

  // ── 3. Edit inline ───────────────────────────────────────────────────────

  it("clicking Edit shows inline inputs with current values", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 1));
    render(<TagSection />);

    await waitFor(() => screen.getByText("Action"));

    await user.click(screen.getByRole("button", { name: "Edit" }));

    // name and groupName inputs should be populated
    expect(screen.getByDisplayValue("Action")).toBeInTheDocument();
    expect(screen.getByDisplayValue("genre")).toBeInTheDocument();
  });

  it("isPrimary checkbox reflects current value in edit mode", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 1));
    render(<TagSection />);

    await waitFor(() => screen.getByText("Action"));

    await user.click(screen.getByRole("button", { name: "Edit" }));

    const checkboxes = screen.getAllByRole("checkbox");
    // TAG_1 has isPrimary: true
    expect(checkboxes[0]).toBeChecked();
  });

  it("isPrimary checkbox is unchecked for isPrimary=false in edit mode", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_2], 1));
    render(<TagSection />);

    await waitFor(() => screen.getByText("Comedy"));

    await user.click(screen.getByRole("button", { name: "Edit" }));

    const checkboxes = screen.getAllByRole("checkbox");
    expect(checkboxes[0]).not.toBeChecked();
  });

  it("Cancel in edit mode exits inline editor without saving", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 1));
    render(<TagSection />);

    await waitFor(() => screen.getByText("Action"));

    await user.click(screen.getByRole("button", { name: "Edit" }));
    expect(screen.queryByText("Action")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Cancel" }));
    expect(screen.getByText("Action")).toBeInTheDocument();
  });

  it("updates only the saved tag when list has multiple tags", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1, TAG_2], 2));
    const UPDATED: Tag = { ...TAG_1, name: "Updated Action" };
    mock.onPut("/tags/t-1").reply(200, UPDATED);

    render(<TagSection />);
    await waitFor(() => screen.getByText("Action"));

    await user.click(screen.getAllByRole("button", { name: "Edit" })[0]);

    const nameInput = screen.getByDisplayValue("Action");
    await user.clear(nameInput);
    await user.type(nameInput, "Updated Action");

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Updated Action"));
    expect(screen.getByText("Comedy")).toBeInTheDocument();
  });

  it("Save success updates the row and exits edit mode", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 1));

    const UPDATED: Tag = { ...TAG_1, name: "Updated Action" };
    mock.onPut("/tags/t-1").reply(200, UPDATED);

    render(<TagSection />);
    await waitFor(() => screen.getByText("Action"));

    await user.click(screen.getByRole("button", { name: "Edit" }));

    const nameInput = screen.getByDisplayValue("Action");
    await user.clear(nameInput);
    await user.type(nameInput, "Updated Action");

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Updated Action"));
    expect(screen.queryByText("Action")).not.toBeInTheDocument();
  });

  it("Save error shows editError span next to buttons", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 1));
    mock.onPut("/tags/t-1").reply(500, { detail: "Update failed" });

    render(<TagSection />);
    await waitFor(() => screen.getByText("Action"));

    await user.click(screen.getByRole("button", { name: "Edit" }));
    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Update failed"));
    // still in edit mode
    expect(screen.getByRole("button", { name: "Save" })).toBeInTheDocument();
  });

  it("Save button is disabled when name is cleared in edit mode", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 1));
    render(<TagSection />);

    await waitFor(() => screen.getByText("Action"));

    await user.click(screen.getByRole("button", { name: "Edit" }));

    const nameInput = screen.getByDisplayValue("Action");
    await user.clear(nameInput);

    expect(screen.getByRole("button", { name: "Save" })).toBeDisabled();
  });

  it("Save button is disabled when groupName is cleared in edit mode", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 1));
    render(<TagSection />);

    await waitFor(() => screen.getByText("Action"));

    await user.click(screen.getByRole("button", { name: "Edit" }));

    const groupInput = screen.getByDisplayValue("genre");
    await user.clear(groupInput);

    expect(screen.getByRole("button", { name: "Save" })).toBeDisabled();
  });

  it("allows toggling isPrimary checkbox in edit mode", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 1));
    render(<TagSection />);

    await waitFor(() => screen.getByText("Action"));

    await user.click(screen.getByRole("button", { name: "Edit" }));

    const checkboxes = screen.getAllByRole("checkbox");
    expect(checkboxes[0]).toBeChecked();
    await user.click(checkboxes[0]);
    expect(checkboxes[0]).not.toBeChecked();
  });

  // ── 4. Delete ────────────────────────────────────────────────────────────

  it("dismiss confirm → item stays in list", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 1));
    vi.spyOn(window, "confirm").mockReturnValueOnce(false);

    render(<TagSection />);
    await waitFor(() => screen.getByText("Action"));

    await user.click(screen.getByRole("button", { name: "Delete" }));
    expect(screen.getByText("Action")).toBeInTheDocument();
  });

  it("confirm + 204 → removes item and decrements total", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 1));
    mock.onDelete("/tags/t-1").reply(204);
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<TagSection />);
    await waitFor(() => screen.getByText("Action"));

    await user.click(screen.getByRole("button", { name: "Delete" }));

    await waitFor(() => screen.getByText("No tags yet."));
    expect(screen.getByText("Tags (0)")).toBeInTheDocument();
  });

  it("confirm + 500 → item stays in list", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 1));
    mock.onDelete("/tags/t-1").reply(500, { detail: "Delete failed" });
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<TagSection />);
    await waitFor(() => screen.getByText("Action"));

    await user.click(screen.getByRole("button", { name: "Delete" }));

    await waitFor(() => {
      expect(screen.getByText("Action")).toBeInTheDocument();
    });
  });

  // ── 5. Pagination ────────────────────────────────────────────────────────

  it("shows no pagination when total <= 20", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 1));
    render(<TagSection />);

    await waitFor(() => screen.getByText("Action"));
    expect(screen.queryByText(/1 \/ /)).not.toBeInTheDocument();
  });

  it("shows pagination when total > 20", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 40));
    render(<TagSection />);

    await waitFor(() => screen.getByText("1 / 2"));
    expect(screen.getByText("1 / 2")).toBeInTheDocument();
  });

  it("disables prev button on first page and next on last page", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 40));
    render(<TagSection />);

    await waitFor(() => screen.getByText("1 / 2"));

    const [prevBtn, nextBtn] = screen.getAllByRole("button", { name: /←|→/ });
    expect(prevBtn).toBeDisabled();
    expect(nextBtn).not.toBeDisabled();
  });

  it("navigates to next page on → click", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 40));
    render(<TagSection />);

    await waitFor(() => screen.getByText("1 / 2"));

    const [, nextBtn] = screen.getAllByRole("button", { name: /←|→/ });
    await user.click(nextBtn);

    await waitFor(() => screen.getByText("2 / 2"));

    const [prevBtn, nextBtn2] = screen.getAllByRole("button", { name: /←|→/ });
    expect(prevBtn).not.toBeDisabled();
    expect(nextBtn2).toBeDisabled();
  });

  it("navigates back to previous page on ← click", async () => {
    mock.onGet("/tags").reply(200, hydra([TAG_1], 40));
    render(<TagSection />);

    await waitFor(() => screen.getByText("1 / 2"));

    const [, nextBtn] = screen.getAllByRole("button", { name: /←|→/ });
    await user.click(nextBtn);
    await waitFor(() => screen.getByText("2 / 2"));

    const [prevBtn] = screen.getAllByRole("button", { name: /←|→/ });
    await user.click(prevBtn);
    await waitFor(() => screen.getByText("1 / 2"));
  });
});
