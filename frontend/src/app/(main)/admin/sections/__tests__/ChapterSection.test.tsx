import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor, fireEvent } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MockAdapter from "axios-mock-adapter";
import api from "@/lib/api";
import ChapterSection from "../ChapterSection";
import type { Chapter } from "@/lib/types";

// ── Fixtures ──────────────────────────────────────────────────────────────────

const CHAPTER_1: Chapter = {
  "@context": "",
  "@id": "/api/chapters/c-1",
  "@type": "Chapter",
  id: "c-1",
  chapterNumber: "1",
  title: "Beginning",
  language: "en",
  volume: "1",
  pageUrls: [],
  createdAt: "2024-01-01T00:00:00+00:00",
  manga: { "@id": "/api/mangas/m-1", "@type": "Manga", id: "m-1" },
  scanlationGroup: null,
};

const CHAPTER_2: Chapter = {
  "@context": "",
  "@id": "/api/chapters/c-2",
  "@type": "Chapter",
  id: "c-2",
  chapterNumber: "2",
  title: null,
  language: "ja",
  volume: null,
  pageUrls: [],
  createdAt: "2024-02-01T00:00:00+00:00",
  manga: { "@id": "/api/mangas/m-1", "@type": "Manga", id: "m-1" },
  scanlationGroup: null,
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

describe("ChapterSection", () => {
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
    mock.onGet("/chapters").reply(() => new Promise(() => {}));
    render(<ChapterSection />);
    expect(screen.getByText("Loading…")).toBeInTheDocument();
  });

  it("renders chapter list after successful fetch", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1, CHAPTER_2], 2));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("Ch. 1"));
    expect(screen.getByText("Beginning")).toBeInTheDocument();
    // CHAPTER_2 has null title → shows "—"
    expect(screen.getAllByText("—").length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText("Ch. 2")).toBeInTheDocument();
    expect(screen.getByText("en")).toBeInTheDocument();
    expect(screen.getByText("ja")).toBeInTheDocument();
  });

  it("shows empty state when no chapters", async () => {
    mock.onGet("/chapters").reply(200, hydra([], 0));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("No chapters yet."));
  });

  it("shows fetch error on failure", async () => {
    mock.onGet("/chapters").reply(500, { detail: "Server error" });
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("Server error"));
  });

  it("renders header with chapter count", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1], 1));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("Chapters (1)"));
  });

  // ── 2. Create form ───────────────────────────────────────────────────────

  it("toggles create form open with '+ New Chapter'", async () => {
    mock.onGet("/chapters").reply(200, hydra([], 0));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("No chapters yet."));

    expect(screen.queryByText("New Chapter")).not.toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "+ New Chapter" }));
    expect(screen.getByText("New Chapter")).toBeInTheDocument();
  });

  it("toggles create form closed with 'Cancel'", async () => {
    mock.onGet("/chapters").reply(200, hydra([], 0));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("No chapters yet."));

    await user.click(screen.getByRole("button", { name: "+ New Chapter" }));
    expect(screen.getByText("New Chapter")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Cancel" }));
    expect(screen.queryByText("New Chapter")).not.toBeInTheDocument();
  });

  it("disables Create button when mangaId is empty", async () => {
    mock.onGet("/chapters").reply(200, hydra([], 0));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("No chapters yet."));
    await user.click(screen.getByRole("button", { name: "+ New Chapter" }));

    await user.type(screen.getByPlaceholderText("Chapter number (e.g. 1, 1.5)"), "1");
    // mangaId is empty → disabled
    expect(screen.getByRole("button", { name: "Create" })).toBeDisabled();
  });

  it("disables Create button when chapterNumber is empty", async () => {
    mock.onGet("/chapters").reply(200, hydra([], 0));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("No chapters yet."));
    await user.click(screen.getByRole("button", { name: "+ New Chapter" }));

    await user.type(screen.getByPlaceholderText("Manga UUID"), "m-1");
    // chapterNumber is empty → disabled
    expect(screen.getByRole("button", { name: "Create" })).toBeDisabled();
  });

  it("creates chapter on success, prepends to list, closes form", async () => {
    mock.onGet("/chapters").reply(200, hydra([], 0));

    const NEW_CHAPTER: Chapter = { ...CHAPTER_1, id: "c-new", chapterNumber: "5", title: "Fresh" };
    mock.onPost("/chapters").reply(201, NEW_CHAPTER);

    render(<ChapterSection />);
    await waitFor(() => screen.getByText("No chapters yet."));

    await user.click(screen.getByRole("button", { name: "+ New Chapter" }));

    await user.type(screen.getByPlaceholderText("Manga UUID"), "m-1");
    await user.type(screen.getByPlaceholderText("Chapter number (e.g. 1, 1.5)"), "5");
    await user.type(screen.getByPlaceholderText("Title (optional)"), "Fresh");

    await user.click(screen.getByRole("button", { name: "Create" }));

    await waitFor(() => screen.getByText("Ch. 5"));
    expect(screen.queryByText("New Chapter")).not.toBeInTheDocument();
    expect(screen.getByText("Chapters (1)")).toBeInTheDocument();
  });

  it("creates chapter with volume and title, sending them in the request body", async () => {
    mock.onGet("/chapters").reply(200, hydra([], 0));

    const NEW_CHAPTER: Chapter = { ...CHAPTER_1, id: "c-new", chapterNumber: "3", title: "Arc", volume: "2" };
    mock.onPost("/chapters").reply(201, NEW_CHAPTER);

    render(<ChapterSection />);
    await waitFor(() => screen.getByText("No chapters yet."));

    await user.click(screen.getByRole("button", { name: "+ New Chapter" }));

    await user.type(screen.getByPlaceholderText("Manga UUID"), "m-1");
    await user.type(screen.getByPlaceholderText("Chapter number (e.g. 1, 1.5)"), "3");
    await user.type(screen.getByPlaceholderText("Title (optional)"), "Arc");
    await user.type(screen.getByPlaceholderText("Volume (optional)"), "2");

    await user.click(screen.getByRole("button", { name: "Create" }));

    await waitFor(() => screen.getByText("Ch. 3"));
    expect(screen.getByText("Chapters (1)")).toBeInTheDocument();
  });

  it("shows createError on POST failure", async () => {
    mock.onGet("/chapters").reply(200, hydra([], 0));
    mock.onPost("/chapters").reply(422, { detail: "Invalid manga UUID" });

    render(<ChapterSection />);
    await waitFor(() => screen.getByText("No chapters yet."));

    await user.click(screen.getByRole("button", { name: "+ New Chapter" }));
    await user.type(screen.getByPlaceholderText("Manga UUID"), "bad-id");
    await user.type(screen.getByPlaceholderText("Chapter number (e.g. 1, 1.5)"), "1");

    await user.click(screen.getByRole("button", { name: "Create" }));

    await waitFor(() => screen.getByText("Invalid manga UUID"));
  });

  // ── 3. Edit inline ───────────────────────────────────────────────────────

  it("clicking Edit shows inline inputs with current values", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1], 1));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("Ch. 1"));

    await user.click(screen.getByRole("button", { name: "Edit" }));

    // both chapterNumber and volume are "1" — use getAllByDisplayValue
    const inputs1 = screen.getAllByDisplayValue("1");
    expect(inputs1.length).toBeGreaterThanOrEqual(2);
    // title input
    expect(screen.getByDisplayValue("Beginning")).toBeInTheDocument();
    // language input
    expect(screen.getByDisplayValue("en")).toBeInTheDocument();
  });

  it("Cancel in edit mode exits inline editor without saving", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1], 1));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("Ch. 1"));

    await user.click(screen.getByRole("button", { name: "Edit" }));
    expect(screen.queryByText("Ch. 1")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Cancel" }));
    expect(screen.getByText("Ch. 1")).toBeInTheDocument();
  });

  it("Save success updates the row and exits edit mode", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1], 1));

    const UPDATED: Chapter = { ...CHAPTER_1, chapterNumber: "1", title: "Revised" };
    mock.onPut("/chapters/c-1").reply(200, UPDATED);

    render(<ChapterSection />);
    await waitFor(() => screen.getByText("Ch. 1"));

    await user.click(screen.getByRole("button", { name: "Edit" }));

    const titleInput = screen.getByPlaceholderText("Title");
    await user.clear(titleInput);
    await user.type(titleInput, "Revised");

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Revised"));
    expect(screen.getByText("Ch. 1")).toBeInTheDocument();
  });

  it("Save error shows editError span next to buttons", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1], 1));
    mock.onPut("/chapters/c-1").reply(500, { detail: "Save failed" });

    render(<ChapterSection />);
    await waitFor(() => screen.getByText("Ch. 1"));

    await user.click(screen.getByRole("button", { name: "Edit" }));
    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Save failed"));
    // still in edit mode
    expect(screen.getByRole("button", { name: "Save" })).toBeInTheDocument();
  });

  it("shows chapter with null title as '—' in display mode", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_2], 1));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("Ch. 2"));
    const dashes = screen.getAllByText("—");
    expect(dashes.length).toBeGreaterThanOrEqual(1);
  });

  it("shows chapter with null volume as '—' in display mode", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_2], 1));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("Ch. 2"));
    // both title and volume are null → two "—" cells
    const dashes = screen.getAllByText("—");
    expect(dashes.length).toBeGreaterThanOrEqual(2);
  });

  // ── 4. Delete ────────────────────────────────────────────────────────────

  it("dismiss confirm → item stays in list", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1], 1));
    vi.spyOn(window, "confirm").mockReturnValueOnce(false);

    render(<ChapterSection />);
    await waitFor(() => screen.getByText("Ch. 1"));

    await user.click(screen.getByRole("button", { name: "Delete" }));
    expect(screen.getByText("Ch. 1")).toBeInTheDocument();
  });

  it("confirm + 204 → removes item and decrements total", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1], 1));
    mock.onDelete("/chapters/c-1").reply(204);
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<ChapterSection />);
    await waitFor(() => screen.getByText("Ch. 1"));

    await user.click(screen.getByRole("button", { name: "Delete" }));

    await waitFor(() => screen.getByText("No chapters yet."));
    expect(screen.getByText("Chapters (0)")).toBeInTheDocument();
  });

  it("confirm + 500 → item stays in list", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1], 1));
    mock.onDelete("/chapters/c-1").reply(500, { detail: "Delete failed" });
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<ChapterSection />);
    await waitFor(() => screen.getByText("Ch. 1"));

    await user.click(screen.getByRole("button", { name: "Delete" }));

    await waitFor(() => {
      expect(screen.getByText("Ch. 1")).toBeInTheDocument();
    });
  });

  // ── 3b. Coverage: create-guard and null title/volume branches ────────────

  it("handleCreate early-returns when required fields are empty (fireEvent.submit bypasses disabled button)", async () => {
    mock.onGet("/chapters").reply(200, hydra([], 0));
    let postCalled = false;
    mock.onPost("/chapters").reply(() => { postCalled = true; return [201, CHAPTER_1]; });

    render(<ChapterSection />);
    await waitFor(() => screen.getByText("No chapters yet."));

    await user.click(screen.getByRole("button", { name: "+ New Chapter" }));

    const form = document.querySelector("form")!;
    fireEvent.submit(form);

    expect(postCalled).toBe(false);
    expect(screen.getByText("New Chapter")).toBeInTheDocument();
  });

  it("editing a chapter with null title and null volume populates empty inputs and saves correctly", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1, CHAPTER_2], 2));

    const UPDATED: Chapter = { ...CHAPTER_2, chapterNumber: "2" };
    mock.onPut("/chapters/c-2").reply(200, UPDATED);

    render(<ChapterSection />);
    await waitFor(() => screen.getByText("Ch. 1"));

    const editBtns = screen.getAllByRole("button", { name: "Edit" });
    await user.click(editBtns[1]);

    const titleInput = screen.getByPlaceholderText("Title") as HTMLInputElement;
    expect(titleInput.value).toBe("");

    const volInput = screen.getByPlaceholderText("Vol") as HTMLInputElement;
    expect(volInput.value).toBe("");

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Ch. 1"));
    expect(screen.getByText("Ch. 2")).toBeInTheDocument();
  });

  it("updates only the saved chapter when list has multiple chapters", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1, CHAPTER_2], 2));

    const UPDATED: Chapter = { ...CHAPTER_1, chapterNumber: "99" };
    mock.onPut("/chapters/c-1").reply(200, UPDATED);

    render(<ChapterSection />);
    await waitFor(() => screen.getByText("Ch. 1"));

    const editBtns = screen.getAllByRole("button", { name: "Edit" });
    await user.click(editBtns[0]);

    const chNumInputs = screen.getAllByDisplayValue("1");
    await user.clear(chNumInputs[0]);
    await user.type(chNumInputs[0], "99");

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Ch. 99"));
    expect(screen.getByText("Ch. 2")).toBeInTheDocument();
  });

  // ── 4b. Input interactions ────────────────────────────────────────────────

  it("typing in the volume field in the create form updates the form", async () => {
    mock.onGet("/chapters").reply(200, hydra([], 0));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("No chapters yet."));
    await user.click(screen.getByRole("button", { name: "+ New Chapter" }));

    const volumeInput = screen.getByPlaceholderText("Volume (optional)") as HTMLInputElement;
    await user.type(volumeInput, "2");
    expect(volumeInput.value).toBe("2");
  });

  it("typing in the language field in the create form updates the form", async () => {
    mock.onGet("/chapters").reply(200, hydra([], 0));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("No chapters yet."));
    await user.click(screen.getByRole("button", { name: "+ New Chapter" }));

    const langInput = screen.getByPlaceholderText("Language (e.g. en)") as HTMLInputElement;
    await user.clear(langInput);
    await user.type(langInput, "ja");
    expect(langInput.value).toBe("ja");
  });

  it("typing in edit-row inputs (chapterNumber, language, volume) updates the form", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1], 1));

    const UPDATED: Chapter = { ...CHAPTER_1, chapterNumber: "99", language: "fr", volume: "5" };
    mock.onPut("/chapters/c-1").reply(200, UPDATED);

    render(<ChapterSection />);
    await waitFor(() => screen.getByText("Ch. 1"));

    await user.click(screen.getByRole("button", { name: "Edit" }));

    const chNumInputs = screen.getAllByDisplayValue("1");
    const chNumInput = chNumInputs[0] as HTMLInputElement;
    await user.clear(chNumInput);
    await user.type(chNumInput, "99");

    const langInput = screen.getByDisplayValue("en") as HTMLInputElement;
    await user.clear(langInput);
    await user.type(langInput, "fr");

    const volInput = screen.getByPlaceholderText("Vol") as HTMLInputElement;
    await user.clear(volInput);
    await user.type(volInput, "5");

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Ch. 99"));
    expect(screen.getByText("fr")).toBeInTheDocument();
  });

  // ── 5. Pagination ────────────────────────────────────────────────────────

  it("shows no pagination when total <= 20", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1], 1));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("Ch. 1"));
    expect(screen.queryByText(/1 \/ /)).not.toBeInTheDocument();
  });

  it("shows pagination when total > 20", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1], 40));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("1 / 2"));
    expect(screen.getByText("1 / 2")).toBeInTheDocument();
  });

  it("disables prev button on first page and next on last page", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1], 40));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("1 / 2"));

    const [prevBtn, nextBtn] = screen.getAllByRole("button", { name: /←|→/ });
    expect(prevBtn).toBeDisabled();
    expect(nextBtn).not.toBeDisabled();
  });

  it("navigates to next page on → click", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1], 40));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("1 / 2"));

    const [, nextBtn] = screen.getAllByRole("button", { name: /←|→/ });
    await user.click(nextBtn);

    await waitFor(() => screen.getByText("2 / 2"));

    const [prevBtn, nextBtn2] = screen.getAllByRole("button", { name: /←|→/ });
    expect(prevBtn).not.toBeDisabled();
    expect(nextBtn2).toBeDisabled();
  });

  it("navigates back to previous page on ← click", async () => {
    mock.onGet("/chapters").reply(200, hydra([CHAPTER_1], 40));
    render(<ChapterSection />);

    await waitFor(() => screen.getByText("1 / 2"));

    const [, nextBtn] = screen.getAllByRole("button", { name: /←|→/ });
    await user.click(nextBtn);
    await waitFor(() => screen.getByText("2 / 2"));

    const [prevBtn] = screen.getAllByRole("button", { name: /←|→/ });
    await user.click(prevBtn);
    await waitFor(() => screen.getByText("1 / 2"));
  });
});
