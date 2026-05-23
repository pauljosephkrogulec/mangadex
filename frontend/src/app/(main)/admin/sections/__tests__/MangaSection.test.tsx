import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor, fireEvent } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MockAdapter from "axios-mock-adapter";
import api from "@/lib/api";
import MangaSection from "../MangaSection";
import type { Manga } from "@/lib/types";

// ── Fixtures ──────────────────────────────────────────────────────────────────

const MANGA_1: Manga = {
  "@context": "",
  "@id": "",
  "@type": "Manga",
  id: "m-1",
  title: "Berserk",
  status: "ongoing",
  contentRating: "safe",
  demographic: "seinen",
  year: 1989,
  createdAt: "2024-01-01T00:00:00+00:00",
  description: "A dark fantasy manga.",
};

const MANGA_2: Manga = {
  "@context": "",
  "@id": "",
  "@type": "Manga",
  id: "m-2",
  title: "One Piece",
  status: "ongoing",
  contentRating: "safe",
  demographic: "shounen",
  year: 1997,
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

describe("MangaSection", () => {
  let mock: MockAdapter;
  const user = userEvent.setup();

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
    vi.restoreAllMocks();
  });

  // ── Rendering: list, loading, empty, error ────────────────────────────────

  it("shows Loading… while the fetch is in flight", () => {
    mock.onGet("/mangas").reply(() => new Promise(() => {}));
    render(<MangaSection />);
    expect(screen.getByText("Loading…")).toBeInTheDocument();
  });

  it("renders the manga list after a successful fetch", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1, MANGA_2], 2));

    render(<MangaSection />);

    await waitFor(() => screen.getByText("Berserk"));
    expect(screen.getByText("One Piece")).toBeInTheDocument();
    expect(screen.getByText("Manga (2)")).toBeInTheDocument();
  });

  it("shows 'No manga yet.' when the list is empty", async () => {
    mock.onGet("/mangas").reply(200, hydra([], 0));

    render(<MangaSection />);

    await waitFor(() => screen.getByText("No manga yet."));
    expect(screen.queryByText("Loading…")).not.toBeInTheDocument();
  });

  it("shows the error message when the fetch fails", async () => {
    mock.onGet("/mangas").reply(500, { detail: "Server exploded" });

    render(<MangaSection />);

    await waitFor(() => screen.getByText("Server exploded"));
    const errorEl = screen.getByText("Server exploded");
    expect(errorEl.tagName).toBe("P");
    expect(errorEl.className).toContain("text-red-400");
  });

  it("displays '—' for manga with null year", async () => {
    const noYear: Manga = { ...MANGA_1, year: null };
    mock.onGet("/mangas").reply(200, hydra([noYear], 1));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    expect(screen.getByText("—")).toBeInTheDocument();
  });

  // ── Create: toggle, disabled state, success, error ────────────────────────

  it("shows the create form when '+ New Manga' is clicked", async () => {
    mock.onGet("/mangas").reply(200, hydra([], 0));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("No manga yet."));

    await user.click(screen.getByRole("button", { name: "+ New Manga" }));

    expect(screen.getByText("New Manga")).toBeInTheDocument();
    expect(screen.getByPlaceholderText("Title")).toBeInTheDocument();
  });

  it("hides the create form and resets it when 'Cancel' is clicked from header", async () => {
    mock.onGet("/mangas").reply(200, hydra([], 0));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("No manga yet."));

    await user.click(screen.getByRole("button", { name: "+ New Manga" }));
    expect(screen.getByPlaceholderText("Title")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Cancel" }));

    expect(screen.queryByPlaceholderText("Title")).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: "+ New Manga" })).toBeInTheDocument();
  });

  it("disables the Create button when the title is empty", async () => {
    mock.onGet("/mangas").reply(200, hydra([], 0));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("No manga yet."));

    await user.click(screen.getByRole("button", { name: "+ New Manga" }));

    const createBtn = screen.getByRole("button", { name: "Create" });
    expect(createBtn).toBeDisabled();
  });

  it("enables the Create button once a title is typed", async () => {
    mock.onGet("/mangas").reply(200, hydra([], 0));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("No manga yet."));

    await user.click(screen.getByRole("button", { name: "+ New Manga" }));
    await user.type(screen.getByPlaceholderText("Title"), "New Series");

    expect(screen.getByRole("button", { name: "Create" })).not.toBeDisabled();
  });

  it("prepends the new manga and closes the form on successful create", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_2], 1));

    const CREATED: Manga = {
      "@context": "",
      "@id": "",
      "@type": "Manga",
      id: "m-new",
      title: "New Series",
      status: "ongoing",
      contentRating: "safe",
      demographic: "none",
      year: null,
      createdAt: "2024-03-01T00:00:00+00:00",
    };
    mock.onPost("/mangas").reply(201, CREATED);

    render(<MangaSection />);
    await waitFor(() => screen.getByText("One Piece"));

    await user.click(screen.getByRole("button", { name: "+ New Manga" }));
    await user.type(screen.getByPlaceholderText("Title"), "New Series");
    await user.click(screen.getByRole("button", { name: "Create" }));

    await waitFor(() => screen.getByText("New Series"));

    // Form is closed
    expect(screen.queryByText("New Manga")).not.toBeInTheDocument();
    // Total incremented
    expect(screen.getByText("Manga (2)")).toBeInTheDocument();
    // New item appears before the existing one
    const rows = screen.getAllByRole("row");
    const newRow = rows.find((r) => r.textContent?.includes("New Series"))!;
    const existingRow = rows.find((r) => r.textContent?.includes("One Piece"))!;
    expect(rows.indexOf(newRow)).toBeLessThan(rows.indexOf(existingRow));
  });

  it("shows create error and keeps the form open when POST fails", async () => {
    mock.onGet("/mangas").reply(200, hydra([], 0));
    mock.onPost("/mangas").reply(422, { detail: "Title already taken" });

    render(<MangaSection />);
    await waitFor(() => screen.getByText("No manga yet."));

    await user.click(screen.getByRole("button", { name: "+ New Manga" }));
    await user.type(screen.getByPlaceholderText("Title"), "Duplicate");
    await user.click(screen.getByRole("button", { name: "Create" }));

    await waitFor(() => screen.getByText("Title already taken"));
    expect(screen.getByPlaceholderText("Title")).toBeInTheDocument();
  });

  // ── Edit: panel, cancel, save success, save error, current values ─────────

  it("shows the edit panel with current values when Edit is clicked", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1], 1));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    const row = screen.getByText("Berserk").closest("tr")!;
    const editBtn = row.querySelector("button")!;
    await user.click(editBtn);

    expect(screen.getByText("Edit Manga")).toBeInTheDocument();
    // The edit form's title input should be populated
    const titleInput = screen.getAllByPlaceholderText("Title").find(
      (el) => (el as HTMLInputElement).value === "Berserk",
    );
    expect(titleInput).toBeInTheDocument();
  });

  it("populates the description in the edit form", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1], 1));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    const row = screen.getByText("Berserk").closest("tr")!;
    await user.click(row.querySelector("button")!);

    const textarea = screen.getByPlaceholderText("Description (optional)") as HTMLTextAreaElement;
    expect(textarea.value).toBe("A dark fantasy manga.");
  });

  it("populates the year in the edit form", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1], 1));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    const row = screen.getByText("Berserk").closest("tr")!;
    await user.click(row.querySelector("button")!);

    const yearInput = screen.getByPlaceholderText("Year (optional)") as HTMLInputElement;
    expect(yearInput.value).toBe("1989");
  });

  it("closes the edit panel when panel Cancel is clicked", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1], 1));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    const row = screen.getByText("Berserk").closest("tr")!;
    await user.click(row.querySelector("button")!);

    expect(screen.getByText("Edit Manga")).toBeInTheDocument();

    // The edit panel has a Cancel button; use the first one (panel's Cancel)
    const cancelBtns = screen.getAllByRole("button", { name: "Cancel" });
    await user.click(cancelBtns[0]);

    expect(screen.queryByText("Edit Manga")).not.toBeInTheDocument();
  });

  it("the row Edit button turns to 'Cancel' while editing", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1], 1));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    const row = screen.getByText("Berserk").closest("tr")!;
    const editBtn = row.querySelector("button")!;
    await user.click(editBtn);

    // Row button now says Cancel
    expect(row.querySelector("button")?.textContent).toBe("Cancel");
  });

  it("closes the edit panel when the row Cancel button is clicked", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1], 1));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    const row = screen.getByText("Berserk").closest("tr")!;
    await user.click(row.querySelector("button")!);
    expect(screen.getByText("Edit Manga")).toBeInTheDocument();

    // Click the row's Cancel button (last Cancel button in the DOM)
    const cancelBtns = screen.getAllByRole("button", { name: "Cancel" });
    await user.click(cancelBtns[cancelBtns.length - 1]);

    expect(screen.queryByText("Edit Manga")).not.toBeInTheDocument();
  });

  it("updates the item in the list on successful Save", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1], 1));

    const UPDATED: Manga = { ...MANGA_1, title: "Berserk – Deluxe Edition" };
    mock.onPut("/mangas/m-1").reply(200, UPDATED);

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    const row = screen.getByText("Berserk").closest("tr")!;
    await user.click(row.querySelector("button")!);

    // Clear and re-type the title
    const titleInputs = screen.getAllByPlaceholderText("Title");
    const editTitleInput = titleInputs.find(
      (el) => (el as HTMLInputElement).value === "Berserk",
    )!;
    await user.clear(editTitleInput);
    await user.type(editTitleInput, "Berserk – Deluxe Edition");

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Berserk – Deluxe Edition"));
    expect(screen.queryByText("Edit Manga")).not.toBeInTheDocument();
  });

  it("shows edit error and keeps panel open when PUT fails", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1], 1));
    mock.onPut("/mangas/m-1").reply(500, { detail: "Save failed" });

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    const row = screen.getByText("Berserk").closest("tr")!;
    await user.click(row.querySelector("button")!);

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Save failed"));
    expect(screen.getByText("Edit Manga")).toBeInTheDocument();
  });

  it("Save button is disabled when the edit title is cleared", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1], 1));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    const row = screen.getByText("Berserk").closest("tr")!;
    await user.click(row.querySelector("button")!);

    const titleInputs = screen.getAllByPlaceholderText("Title");
    const editTitleInput = titleInputs.find(
      (el) => (el as HTMLInputElement).value === "Berserk",
    )!;
    await user.clear(editTitleInput);

    expect(screen.getByRole("button", { name: "Save" })).toBeDisabled();
  });

  // ── Delete ────────────────────────────────────────────────────────────────

  it("does not remove manga when confirm is dismissed", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1], 1));
    vi.spyOn(window, "confirm").mockReturnValueOnce(false);

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    const mangaRow = screen.getByText("Berserk").closest("tr")!;
    const deleteBtn = mangaRow.querySelector("button[class*='red']")!;
    await user.click(deleteBtn);

    expect(screen.getByText("Berserk")).toBeInTheDocument();
  });

  it("removes manga and decrements total when DELETE succeeds", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1, MANGA_2], 2));
    mock.onDelete("/mangas/m-1").reply(204);
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    const mangaRow = screen.getByText("Berserk").closest("tr")!;
    const deleteBtn = mangaRow.querySelector("button[class*='red']")!;
    await user.click(deleteBtn);

    await waitFor(() => {
      expect(screen.queryByText("Berserk")).not.toBeInTheDocument();
    });
    expect(screen.getByText("One Piece")).toBeInTheDocument();
    expect(screen.getByText("Manga (1)")).toBeInTheDocument();
  });

  it("keeps manga in list when DELETE fails", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1], 1));
    mock.onDelete("/mangas/m-1").reply(500, { detail: "Cannot delete" });
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    const mangaRow = screen.getByText("Berserk").closest("tr")!;
    const deleteBtn = mangaRow.querySelector("button[class*='red']")!;
    await user.click(deleteBtn);

    await waitFor(() => {
      expect(screen.getByText("Berserk")).toBeInTheDocument();
    });
    expect(screen.getByText("Manga (1)")).toBeInTheDocument();
  });

  // ── MangaFormFields interactions ──────────────────────────────────────────

  it("typing in the year input updates the form", async () => {
    mock.onGet("/mangas").reply(200, hydra([], 0));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("No manga yet."));

    await user.click(screen.getByRole("button", { name: "+ New Manga" }));

    const yearInput = screen.getByPlaceholderText("Year (optional)") as HTMLInputElement;
    await user.type(yearInput, "2023");
    expect(yearInput.value).toBe("2023");
  });

  it("typing in the description textarea updates the form", async () => {
    mock.onGet("/mangas").reply(200, hydra([], 0));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("No manga yet."));

    await user.click(screen.getByRole("button", { name: "+ New Manga" }));

    const textarea = screen.getByPlaceholderText("Description (optional)") as HTMLTextAreaElement;
    await user.type(textarea, "An epic tale");
    expect(textarea.value).toBe("An epic tale");
  });

  it("changing the status select updates the form", async () => {
    mock.onGet("/mangas").reply(200, hydra([], 0));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("No manga yet."));

    await user.click(screen.getByRole("button", { name: "+ New Manga" }));

    const selects = screen.getAllByRole("combobox");
    // Status is the first select
    await user.selectOptions(selects[0], "completed");
    expect((selects[0] as HTMLSelectElement).value).toBe("completed");
  });

  it("changing the content rating select updates the form", async () => {
    mock.onGet("/mangas").reply(200, hydra([], 0));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("No manga yet."));

    await user.click(screen.getByRole("button", { name: "+ New Manga" }));

    const selects = screen.getAllByRole("combobox");
    // Content rating is the second select
    await user.selectOptions(selects[1], "suggestive");
    expect((selects[1] as HTMLSelectElement).value).toBe("suggestive");
  });

  it("changing the demographic select updates the form", async () => {
    mock.onGet("/mangas").reply(200, hydra([], 0));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("No manga yet."));

    await user.click(screen.getByRole("button", { name: "+ New Manga" }));

    const selects = screen.getAllByRole("combobox");
    // Demographic is the third select
    await user.selectOptions(selects[2], "shounen");
    expect((selects[2] as HTMLSelectElement).value).toBe("shounen");
  });

  // ── Pagination ────────────────────────────────────────────────────────────

  it("shows pagination when totalItems > 20", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1], 40));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("1 / 2"));

    expect(screen.getByText("1 / 2")).toBeInTheDocument();

    const prevBtn = screen.getByRole("button", { name: "←" });
    const nextBtn = screen.getByRole("button", { name: "→" });
    expect(prevBtn).toBeDisabled();
    expect(nextBtn).not.toBeDisabled();
  });

  it("does not show pagination when totalItems <= 20", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1], 10));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    expect(screen.queryByText(/\d+ \/ \d+/)).not.toBeInTheDocument();
  });

  it("navigates to next page when → is clicked", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1], 40));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("1 / 2"));

    await user.click(screen.getByRole("button", { name: "→" }));

    await waitFor(() => screen.getByText("2 / 2"));

    expect(screen.getByRole("button", { name: "←" })).not.toBeDisabled();
    expect(screen.getByRole("button", { name: "→" })).toBeDisabled();
  });

  it("navigates back to previous page when ← is clicked", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1], 40));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("1 / 2"));

    await user.click(screen.getByRole("button", { name: "→" }));
    await waitFor(() => screen.getByText("2 / 2"));

    await user.click(screen.getByRole("button", { name: "←" }));
    await waitFor(() => screen.getByText("1 / 2"));
  });

  // ── fromManga null/undefined branches ────────────────────────────────────

  it("populates empty year string when manga year is null in edit form", async () => {
    const nullYearManga: Manga = { ...MANGA_1, year: null };
    mock.onGet("/mangas").reply(200, hydra([nullYearManga], 1));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    const row = screen.getByText("Berserk").closest("tr")!;
    await user.click(row.querySelector("button")!);

    const yearInput = screen.getByPlaceholderText("Year (optional)") as HTMLInputElement;
    expect(yearInput.value).toBe("");
  });

  it("populates empty description when manga description is undefined in edit form", async () => {
    const noDescManga: Manga = { ...MANGA_2 };
    mock.onGet("/mangas").reply(200, hydra([noDescManga], 1));

    render(<MangaSection />);
    await waitFor(() => screen.getByText("One Piece"));

    const row = screen.getByText("One Piece").closest("tr")!;
    await user.click(row.querySelector("button")!);

    const textarea = screen.getByPlaceholderText("Description (optional)") as HTMLTextAreaElement;
    expect(textarea.value).toBe("");
  });

  it("handleCreate early-returns when title is empty (fireEvent.submit bypasses disabled button)", async () => {
    mock.onGet("/mangas").reply(200, hydra([], 0));
    let postCalled = false;
    mock.onPost("/mangas").reply(() => { postCalled = true; return [201, MANGA_1]; });

    render(<MangaSection />);
    await waitFor(() => screen.getByText("No manga yet."));

    await user.click(screen.getByRole("button", { name: "+ New Manga" }));

    const form = document.querySelector("form")!;
    fireEvent.submit(form);

    expect(postCalled).toBe(false);
    expect(screen.getByText("New Manga")).toBeInTheDocument();
  });

  it("updates only the saved manga when list has multiple items", async () => {
    mock.onGet("/mangas").reply(200, hydra([MANGA_1, MANGA_2], 2));
    const UPDATED: Manga = { ...MANGA_1, title: "Berserk – Remastered" };
    mock.onPut("/mangas/m-1").reply(200, UPDATED);

    render(<MangaSection />);
    await waitFor(() => screen.getByText("Berserk"));

    const row = screen.getByText("Berserk").closest("tr")!;
    await user.click(row.querySelector("button")!);

    const titleInputs = screen.getAllByPlaceholderText("Title");
    const editInput = titleInputs.find((el) => (el as HTMLInputElement).value === "Berserk")!;
    await user.clear(editInput);
    await user.type(editInput, "Berserk – Remastered");

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Berserk – Remastered"));
    expect(screen.getByText("One Piece")).toBeInTheDocument();
  });

  // ── Re-fetch on page change ───────────────────────────────────────────────

  it("re-fetches manga when the page changes", async () => {
    let callCount = 0;
    mock.onGet("/mangas").reply(() => {
      callCount++;
      return [200, hydra([MANGA_1], 40)];
    });

    render(<MangaSection />);
    await waitFor(() => screen.getByText("1 / 2"));

    await user.click(screen.getByRole("button", { name: "→" }));
    await waitFor(() => screen.getByText("2 / 2"));

    expect(callCount).toBe(2);
  });
});
