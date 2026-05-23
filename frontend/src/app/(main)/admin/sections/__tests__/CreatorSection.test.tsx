import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor, fireEvent } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MockAdapter from "axios-mock-adapter";
import api from "@/lib/api";
import CreatorSection from "../CreatorSection";
import type { Creator } from "@/lib/types";

// ── Fixtures ──────────────────────────────────────────────────────────────────

const CREATOR_1: Creator = {
  "@context": "",
  "@id": "/api/creators/cr-1",
  "@type": "Creator",
  id: "cr-1",
  name: "Oda Eiichiro",
  type: "author",
  createdAt: "2024-01-01T00:00:00+00:00",
};

const CREATOR_2: Creator = {
  "@context": "",
  "@id": "/api/creators/cr-2",
  "@type": "Creator",
  id: "cr-2",
  name: "Kishimoto Masashi",
  type: "artist",
  createdAt: "2024-01-02T00:00:00+00:00",
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

describe("CreatorSection", () => {
  let mock: MockAdapter;
  const user = userEvent.setup();

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  // ── 1. List / loading / empty / error ─────────────────────────────────────

  it("shows loading state while fetching", () => {
    mock.onGet("/creators").reply(() => new Promise(() => {}));
    render(<CreatorSection />);
    expect(screen.getByText("Loading…")).toBeInTheDocument();
  });

  it("renders creator list with name and capitalized type", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1, CREATOR_2], 2));
    render(<CreatorSection />);

    await waitFor(() => screen.getByText("Oda Eiichiro"));
    expect(screen.getByText("Kishimoto Masashi")).toBeInTheDocument();
    // types shown capitalized
    expect(screen.getByText("author")).toBeInTheDocument();
    expect(screen.getByText("artist")).toBeInTheDocument();
    expect(screen.getByText("Creators (2)")).toBeInTheDocument();
  });

  it("shows empty state when list is empty", async () => {
    mock.onGet("/creators").reply(200, hydra([], 0));
    render(<CreatorSection />);

    await waitFor(() => screen.getByText("No creators yet."));
  });

  it("shows fetch error message when list request fails", async () => {
    mock.onGet("/creators").reply(500, { detail: "Server exploded" });
    render(<CreatorSection />);

    await waitFor(() => screen.getByText("Server exploded"));
  });

  // ── 2. Create form ────────────────────────────────────────────────────────

  it("toggles create form with '+ New Creator' and 'Cancel' button", async () => {
    mock.onGet("/creators").reply(200, hydra([], 0));
    render(<CreatorSection />);

    await waitFor(() => screen.getByText("No creators yet."));

    // form is hidden initially
    expect(screen.queryByPlaceholderText("Name")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "+ New Creator" }));
    expect(screen.getByPlaceholderText("Name")).toBeInTheDocument();

    // header button now shows "Cancel"
    await user.click(screen.getByRole("button", { name: "Cancel" }));
    expect(screen.queryByPlaceholderText("Name")).not.toBeInTheDocument();
  });

  it("Submit button is disabled when name is empty", async () => {
    mock.onGet("/creators").reply(200, hydra([], 0));
    render(<CreatorSection />);

    await waitFor(() => screen.getByText("No creators yet."));
    await user.click(screen.getByRole("button", { name: "+ New Creator" }));

    const createBtn = screen.getByRole("button", { name: "Create" });
    expect(createBtn).toBeDisabled();
  });

  it("Submit button becomes enabled when name is filled", async () => {
    mock.onGet("/creators").reply(200, hydra([], 0));
    render(<CreatorSection />);

    await waitFor(() => screen.getByText("No creators yet."));
    await user.click(screen.getByRole("button", { name: "+ New Creator" }));

    await user.type(screen.getByPlaceholderText("Name"), "New Author");
    expect(screen.getByRole("button", { name: "Create" })).not.toBeDisabled();
  });

  it("creates creator, prepends to list, resets and closes form on success", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1], 1));

    const NEW_CREATOR: Creator = {
      "@context": "",
      "@id": "/api/creators/cr-new",
      "@type": "Creator",
      id: "cr-new",
      name: "Toriyama Akira",
      type: "author",
      createdAt: "2024-02-01T00:00:00+00:00",
    };
    mock.onPost("/creators").reply(201, NEW_CREATOR);

    render(<CreatorSection />);
    await waitFor(() => screen.getByText("Oda Eiichiro"));

    await user.click(screen.getByRole("button", { name: "+ New Creator" }));
    await user.type(screen.getByPlaceholderText("Name"), "Toriyama Akira");
    await user.click(screen.getByRole("button", { name: "Create" }));

    await waitFor(() => screen.getByText("Toriyama Akira"));
    // form is closed
    expect(screen.queryByPlaceholderText("Name")).not.toBeInTheDocument();
    // count updated
    expect(screen.getByText("Creators (2)")).toBeInTheDocument();
    // new item appears before old one (prepended)
    const rows = screen.getAllByRole("row");
    const newIdx = rows.findIndex((r) => r.textContent?.includes("Toriyama Akira"));
    const oldIdx = rows.findIndex((r) => r.textContent?.includes("Oda Eiichiro"));
    expect(newIdx).toBeLessThan(oldIdx);
  });

  it("shows POST error when create request fails", async () => {
    mock.onGet("/creators").reply(200, hydra([], 0));
    mock.onPost("/creators").reply(422, { detail: "Name already taken" });

    render(<CreatorSection />);
    await waitFor(() => screen.getByText("No creators yet."));

    await user.click(screen.getByRole("button", { name: "+ New Creator" }));
    await user.type(screen.getByPlaceholderText("Name"), "Duplicate");
    await user.click(screen.getByRole("button", { name: "Create" }));

    await waitFor(() => screen.getByText("Name already taken"));
    // form stays open
    expect(screen.getByPlaceholderText("Name")).toBeInTheDocument();
  });

  it("can change the type select in create form", async () => {
    mock.onGet("/creators").reply(200, hydra([], 0));
    render(<CreatorSection />);

    await waitFor(() => screen.getByText("No creators yet."));
    await user.click(screen.getByRole("button", { name: "+ New Creator" }));

    const typeSelect = screen.getAllByRole("combobox")[0];
    expect(typeSelect).toHaveValue("author");

    await user.selectOptions(typeSelect, "artist");
    expect(typeSelect).toHaveValue("artist");
  });

  // ── 3. Inline edit ────────────────────────────────────────────────────────

  it("clicking Edit shows name input and type select with current values", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1], 1));
    render(<CreatorSection />);

    await waitFor(() => screen.getByText("Oda Eiichiro"));

    await user.click(screen.getByRole("button", { name: "Edit" }));

    const nameInput = screen.getByDisplayValue("Oda Eiichiro");
    expect(nameInput).toBeInTheDocument();

    const typeSelect = screen.getByRole("combobox");
    expect(typeSelect).toHaveValue("author");
  });

  it("Cancel in edit row closes inline editor without changing item", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1], 1));
    render(<CreatorSection />);

    await waitFor(() => screen.getByText("Oda Eiichiro"));

    await user.click(screen.getByRole("button", { name: "Edit" }));
    expect(screen.getByDisplayValue("Oda Eiichiro")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Cancel" }));

    // back to display row
    expect(screen.queryByDisplayValue("Oda Eiichiro")).not.toBeInTheDocument();
    expect(screen.getByText("Oda Eiichiro")).toBeInTheDocument();
  });

  it("Save in edit row updates the row on success", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1], 1));

    const UPDATED: Creator = {
      ...CREATOR_1,
      name: "Oda E. (updated)",
      type: "artist",
    };
    mock.onPut("/creators/cr-1").reply(200, UPDATED);

    render(<CreatorSection />);
    await waitFor(() => screen.getByText("Oda Eiichiro"));

    await user.click(screen.getByRole("button", { name: "Edit" }));

    const nameInput = screen.getByDisplayValue("Oda Eiichiro");
    await user.clear(nameInput);
    await user.type(nameInput, "Oda E. (updated)");

    const typeSelect = screen.getByRole("combobox");
    await user.selectOptions(typeSelect, "artist");

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Oda E. (updated)"));
    expect(screen.queryByDisplayValue("Oda E. (updated)")).not.toBeInTheDocument();
    // the type in the display row
    expect(screen.getByText("artist")).toBeInTheDocument();
  });

  it("shows editError span when Save request fails", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1], 1));
    mock.onPut("/creators/cr-1").reply(500, { detail: "Update failed" });

    render(<CreatorSection />);
    await waitFor(() => screen.getByText("Oda Eiichiro"));

    await user.click(screen.getByRole("button", { name: "Edit" }));
    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Update failed"));
    // still in edit mode
    expect(screen.getByDisplayValue("Oda Eiichiro")).toBeInTheDocument();
  });

  it("can change the type select in the edit row", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1], 1));
    render(<CreatorSection />);

    await waitFor(() => screen.getByText("Oda Eiichiro"));
    await user.click(screen.getByRole("button", { name: "Edit" }));

    const typeSelect = screen.getByRole("combobox");
    expect(typeSelect).toHaveValue("author");

    await user.selectOptions(typeSelect, "artist");
    expect(typeSelect).toHaveValue("artist");
  });

  it("Save button is disabled when edit name is empty", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1], 1));
    render(<CreatorSection />);

    await waitFor(() => screen.getByText("Oda Eiichiro"));
    await user.click(screen.getByRole("button", { name: "Edit" }));

    const nameInput = screen.getByDisplayValue("Oda Eiichiro");
    await user.clear(nameInput);

    expect(screen.getByRole("button", { name: "Save" })).toBeDisabled();
  });

  // ── 4. Delete ─────────────────────────────────────────────────────────────

  it("keeps item when confirm is dismissed", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1], 1));
    vi.spyOn(window, "confirm").mockReturnValueOnce(false);

    render(<CreatorSection />);
    await waitFor(() => screen.getByText("Oda Eiichiro"));

    await user.click(screen.getByRole("button", { name: "Delete" }));

    expect(screen.getByText("Oda Eiichiro")).toBeInTheDocument();
    expect(screen.getByText("Creators (1)")).toBeInTheDocument();
  });

  it("removes item and decrements count when confirm + 204", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1], 1));
    mock.onDelete("/creators/cr-1").reply(204);
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<CreatorSection />);
    await waitFor(() => screen.getByText("Oda Eiichiro"));

    await user.click(screen.getByRole("button", { name: "Delete" }));

    await waitFor(() => {
      expect(screen.queryByText("Oda Eiichiro")).not.toBeInTheDocument();
    });
    expect(screen.getByText("Creators (0)")).toBeInTheDocument();
    expect(screen.getByText("No creators yet.")).toBeInTheDocument();
  });

  it("keeps item when confirm + 500 error", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1], 1));
    mock.onDelete("/creators/cr-1").reply(500, { detail: "Delete failed" });
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<CreatorSection />);
    await waitFor(() => screen.getByText("Oda Eiichiro"));

    await user.click(screen.getByRole("button", { name: "Delete" }));

    await waitFor(() => {
      expect(screen.getByText("Oda Eiichiro")).toBeInTheDocument();
    });
    expect(screen.getByText("Creators (1)")).toBeInTheDocument();
  });

  // ── 3b. Coverage: create-guard and map two-items ─────────────────────────

  it("handleCreate early-returns when name is empty (fireEvent.submit bypasses disabled button)", async () => {
    mock.onGet("/creators").reply(200, hydra([], 0));
    let postCalled = false;
    mock.onPost("/creators").reply(() => { postCalled = true; return [201, CREATOR_1]; });

    render(<CreatorSection />);
    await waitFor(() => screen.getByText("No creators yet."));

    await user.click(screen.getByRole("button", { name: "+ New Creator" }));

    const form = document.querySelector("form")!;
    fireEvent.submit(form);

    expect(postCalled).toBe(false);
    expect(screen.getByText("New Creator")).toBeInTheDocument();
  });

  it("updates only the saved creator when list has multiple items", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1, CREATOR_2], 2));
    const UPDATED: Creator = { ...CREATOR_1, name: "Oda E. (updated)" };
    mock.onPut("/creators/cr-1").reply(200, UPDATED);

    render(<CreatorSection />);
    await waitFor(() => screen.getByText("Oda Eiichiro"));

    await user.click(screen.getAllByRole("button", { name: "Edit" })[0]);

    const nameInput = screen.getByDisplayValue("Oda Eiichiro");
    await user.clear(nameInput);
    await user.type(nameInput, "Oda E. (updated)");

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Oda E. (updated)"));
    expect(screen.getByText("Kishimoto Masashi")).toBeInTheDocument();
  });

  // ── 5. Pagination ─────────────────────────────────────────────────────────

  it("does not show pagination when totalPages <= 1", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1], 1));
    render(<CreatorSection />);

    await waitFor(() => screen.getByText("Oda Eiichiro"));
    expect(screen.queryByText(/1 \/ 1/)).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "←" })).not.toBeInTheDocument();
  });

  it("shows pagination when totalPages > 1, prev disabled on first page", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1], 41));
    render(<CreatorSection />);

    await waitFor(() => screen.getByText("1 / 3"));

    const prevBtn = screen.getByRole("button", { name: "←" });
    const nextBtn = screen.getByRole("button", { name: "→" });
    expect(prevBtn).toBeDisabled();
    expect(nextBtn).not.toBeDisabled();
  });

  it("navigates to next page and disables next on last page", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1], 21));
    render(<CreatorSection />);

    await waitFor(() => screen.getByText("1 / 2"));

    const nextBtn = screen.getByRole("button", { name: "→" });
    await user.click(nextBtn);

    await waitFor(() => screen.getByText("2 / 2"));

    expect(screen.getByRole("button", { name: "←" })).not.toBeDisabled();
    expect(screen.getByRole("button", { name: "→" })).toBeDisabled();
  });

  it("navigates back to previous page", async () => {
    mock.onGet("/creators").reply(200, hydra([CREATOR_1], 21));
    render(<CreatorSection />);

    await waitFor(() => screen.getByText("1 / 2"));

    await user.click(screen.getByRole("button", { name: "→" }));
    await waitFor(() => screen.getByText("2 / 2"));

    await user.click(screen.getByRole("button", { name: "←" }));
    await waitFor(() => screen.getByText("1 / 2"));
  });
});
