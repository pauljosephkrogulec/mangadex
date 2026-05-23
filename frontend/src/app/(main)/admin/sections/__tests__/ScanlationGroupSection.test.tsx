import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor, fireEvent } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MockAdapter from "axios-mock-adapter";
import api from "@/lib/api";
import ScanlationGroupSection from "../ScanlationGroupSection";
import type { ScanlationGroup } from "@/lib/types";

// ── Fixtures ──────────────────────────────────────────────────────────────────

const GROUP_1: ScanlationGroup = {
  "@context": "",
  "@id": "/api/scanlation_groups/sg-1",
  "@type": "ScanlationGroup",
  id: "sg-1",
  name: "MangaScans",
  website: "https://mangascans.com",
  createdAt: "2024-01-01T00:00:00+00:00",
};

const GROUP_NO_SITE: ScanlationGroup = {
  "@context": "",
  "@id": "/api/scanlation_groups/sg-2",
  "@type": "ScanlationGroup",
  id: "sg-2",
  name: "NoSite Group",
  website: null,
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

describe("ScanlationGroupSection", () => {
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
    mock.onGet("/scanlation_groups").reply(() => new Promise(() => {}));
    render(<ScanlationGroupSection />);
    expect(screen.getByText("Loading…")).toBeInTheDocument();
  });

  it("renders group list with name and website link", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1], 1));
    render(<ScanlationGroupSection />);

    await waitFor(() => screen.getByText("MangaScans"));
    expect(
      screen.getByRole("link", { name: "https://mangascans.com" }),
    ).toBeInTheDocument();
    expect(screen.getByText("Scanlation Groups (1)")).toBeInTheDocument();
  });

  it("shows '—' for groups with null website", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_NO_SITE], 1));
    render(<ScanlationGroupSection />);

    await waitFor(() => screen.getByText("NoSite Group"));
    expect(screen.getByText("—")).toBeInTheDocument();
    expect(screen.queryByRole("link")).not.toBeInTheDocument();
  });

  it("shows loading state then resolves", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1], 1));
    render(<ScanlationGroupSection />);

    // Initially shows Loading…
    expect(screen.getByText("Loading…")).toBeInTheDocument();

    await waitFor(() => screen.getByText("MangaScans"));
    expect(screen.queryByText("Loading…")).not.toBeInTheDocument();
  });

  it("shows empty state when list is empty", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([], 0));
    render(<ScanlationGroupSection />);

    await waitFor(() => screen.getByText("No groups yet."));
  });

  it("shows fetch error when list request fails", async () => {
    mock.onGet("/scanlation_groups").reply(500, { detail: "DB is down" });
    render(<ScanlationGroupSection />);

    await waitFor(() => screen.getByText("DB is down"));
  });

  // ── 2. Create form ────────────────────────────────────────────────────────

  it("toggles create form with '+ New Group' and 'Cancel' button", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([], 0));
    render(<ScanlationGroupSection />);

    await waitFor(() => screen.getByText("No groups yet."));

    expect(screen.queryByPlaceholderText("Name")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "+ New Group" }));
    expect(screen.getByPlaceholderText("Name")).toBeInTheDocument();
    expect(screen.getByPlaceholderText("Website URL (optional)")).toBeInTheDocument();

    // Use [0] in case edit cancel also appears (it won't here, but be safe)
    await user.click(screen.getAllByRole("button", { name: "Cancel" })[0]);
    expect(screen.queryByPlaceholderText("Name")).not.toBeInTheDocument();
  });

  it("Submit button is disabled when name is empty", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([], 0));
    render(<ScanlationGroupSection />);

    await waitFor(() => screen.getByText("No groups yet."));
    await user.click(screen.getByRole("button", { name: "+ New Group" }));

    expect(screen.getByRole("button", { name: "Create" })).toBeDisabled();
  });

  it("Submit button becomes enabled when name is filled", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([], 0));
    render(<ScanlationGroupSection />);

    await waitFor(() => screen.getByText("No groups yet."));
    await user.click(screen.getByRole("button", { name: "+ New Group" }));

    await user.type(screen.getByPlaceholderText("Name"), "New Group");
    expect(screen.getByRole("button", { name: "Create" })).not.toBeDisabled();
  });

  it("creates group with website, prepends to list, resets and closes form", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_NO_SITE], 1));

    const NEW_GROUP: ScanlationGroup = {
      "@context": "",
      "@id": "/api/scanlation_groups/sg-new",
      "@type": "ScanlationGroup",
      id: "sg-new",
      name: "BestScans",
      website: "https://bestscans.com",
      createdAt: "2024-03-01T00:00:00+00:00",
    };
    mock.onPost("/scanlation_groups").reply(201, NEW_GROUP);

    render(<ScanlationGroupSection />);
    await waitFor(() => screen.getByText("NoSite Group"));

    await user.click(screen.getByRole("button", { name: "+ New Group" }));
    await user.type(screen.getByPlaceholderText("Name"), "BestScans");
    await user.type(
      screen.getByPlaceholderText("Website URL (optional)"),
      "https://bestscans.com",
    );
    await user.click(screen.getByRole("button", { name: "Create" }));

    await waitFor(() => screen.getByText("BestScans"));
    // form closed
    expect(screen.queryByPlaceholderText("Name")).not.toBeInTheDocument();
    // count updated
    expect(screen.getByText("Scanlation Groups (2)")).toBeInTheDocument();
    // new item is prepended
    const rows = screen.getAllByRole("row");
    const newIdx = rows.findIndex((r) => r.textContent?.includes("BestScans"));
    const oldIdx = rows.findIndex((r) => r.textContent?.includes("NoSite Group"));
    expect(newIdx).toBeLessThan(oldIdx);
  });

  it("creates group without website when website field is blank", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([], 0));

    const NEW_GROUP: ScanlationGroup = {
      "@context": "",
      "@id": "/api/scanlation_groups/sg-new",
      "@type": "ScanlationGroup",
      id: "sg-new",
      name: "NoWebGroup",
      website: null,
      createdAt: "2024-03-01T00:00:00+00:00",
    };

    let capturedPayload: unknown = null;
    mock.onPost("/scanlation_groups").reply((config) => {
      capturedPayload = JSON.parse(config.data);
      return [201, NEW_GROUP];
    });

    render(<ScanlationGroupSection />);
    await waitFor(() => screen.getByText("No groups yet."));

    await user.click(screen.getByRole("button", { name: "+ New Group" }));
    await user.type(screen.getByPlaceholderText("Name"), "NoWebGroup");
    // leave website blank
    await user.click(screen.getByRole("button", { name: "Create" }));

    await waitFor(() => screen.getByText("NoWebGroup"));
    // website should not be included in payload
    expect(capturedPayload).toEqual({ name: "NoWebGroup" });
  });

  it("shows createError when POST fails", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([], 0));
    mock.onPost("/scanlation_groups").reply(422, { detail: "Name taken" });

    render(<ScanlationGroupSection />);
    await waitFor(() => screen.getByText("No groups yet."));

    await user.click(screen.getByRole("button", { name: "+ New Group" }));
    await user.type(screen.getByPlaceholderText("Name"), "Duplicate");
    await user.click(screen.getByRole("button", { name: "Create" }));

    await waitFor(() => screen.getByText("Name taken"));
    // form stays open
    expect(screen.getByPlaceholderText("Name")).toBeInTheDocument();
  });

  // ── 3. Inline edit ────────────────────────────────────────────────────────

  it("clicking Edit shows name input and website input with current values", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1], 1));
    render(<ScanlationGroupSection />);

    await waitFor(() => screen.getByText("MangaScans"));
    await user.click(screen.getByRole("button", { name: "Edit" }));

    expect(screen.getByDisplayValue("MangaScans")).toBeInTheDocument();
    expect(screen.getByDisplayValue("https://mangascans.com")).toBeInTheDocument();
  });

  it("clicking Edit for null-website group shows empty website input", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_NO_SITE], 1));
    render(<ScanlationGroupSection />);

    await waitFor(() => screen.getByText("NoSite Group"));
    await user.click(screen.getByRole("button", { name: "Edit" }));

    expect(screen.getByDisplayValue("NoSite Group")).toBeInTheDocument();
    const websiteInput = screen.getByPlaceholderText("Website URL");
    expect(websiteInput).toHaveValue("");
  });

  it("Cancel in edit row closes inline editor without changing item", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1], 1));
    render(<ScanlationGroupSection />);

    await waitFor(() => screen.getByText("MangaScans"));
    await user.click(screen.getByRole("button", { name: "Edit" }));

    expect(screen.getByDisplayValue("MangaScans")).toBeInTheDocument();

    await user.click(screen.getAllByRole("button", { name: "Cancel" })[0]);

    expect(screen.queryByDisplayValue("MangaScans")).not.toBeInTheDocument();
    expect(screen.getByText("MangaScans")).toBeInTheDocument();
  });

  it("Save in edit row updates the row on success", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1], 1));

    const UPDATED: ScanlationGroup = {
      ...GROUP_1,
      name: "MangaScans Pro",
      website: "https://mangascanspro.com",
    };
    mock.onPut("/scanlation_groups/sg-1").reply(200, UPDATED);

    render(<ScanlationGroupSection />);
    await waitFor(() => screen.getByText("MangaScans"));

    await user.click(screen.getByRole("button", { name: "Edit" }));

    const nameInput = screen.getByDisplayValue("MangaScans");
    await user.clear(nameInput);
    await user.type(nameInput, "MangaScans Pro");

    const websiteInput = screen.getByDisplayValue("https://mangascans.com");
    await user.clear(websiteInput);
    await user.type(websiteInput, "https://mangascanspro.com");

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("MangaScans Pro"));
    expect(
      screen.getByRole("link", { name: "https://mangascanspro.com" }),
    ).toBeInTheDocument();
    // no longer in edit mode
    expect(screen.queryByDisplayValue("MangaScans Pro")).not.toBeInTheDocument();
  });

  it("Save with blank website sends website: null", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1], 1));

    const UPDATED: ScanlationGroup = {
      ...GROUP_1,
      website: null,
    };

    let capturedPayload: unknown = null;
    mock.onPut("/scanlation_groups/sg-1").reply((config) => {
      capturedPayload = JSON.parse(config.data);
      return [200, UPDATED];
    });

    render(<ScanlationGroupSection />);
    await waitFor(() => screen.getByText("MangaScans"));

    await user.click(screen.getByRole("button", { name: "Edit" }));

    const websiteInput = screen.getByDisplayValue("https://mangascans.com");
    await user.clear(websiteInput);

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() =>
      expect(screen.queryByDisplayValue("MangaScans")).not.toBeInTheDocument(),
    );

    expect(capturedPayload).toEqual({ name: "MangaScans", website: null });
    // display row shows "—"
    expect(screen.getByText("—")).toBeInTheDocument();
  });

  it("shows editError span when Save request fails", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1], 1));
    mock.onPut("/scanlation_groups/sg-1").reply(500, { detail: "Save failed" });

    render(<ScanlationGroupSection />);
    await waitFor(() => screen.getByText("MangaScans"));

    await user.click(screen.getByRole("button", { name: "Edit" }));
    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Save failed"));
    // still in edit mode
    expect(screen.getByDisplayValue("MangaScans")).toBeInTheDocument();
  });

  it("Save button is disabled when edit name is empty", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1], 1));
    render(<ScanlationGroupSection />);

    await waitFor(() => screen.getByText("MangaScans"));
    await user.click(screen.getByRole("button", { name: "Edit" }));

    const nameInput = screen.getByDisplayValue("MangaScans");
    await user.clear(nameInput);

    expect(screen.getByRole("button", { name: "Save" })).toBeDisabled();
  });

  // ── 4. Delete ─────────────────────────────────────────────────────────────

  it("keeps item when confirm is dismissed", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1], 1));
    vi.spyOn(window, "confirm").mockReturnValueOnce(false);

    render(<ScanlationGroupSection />);
    await waitFor(() => screen.getByText("MangaScans"));

    await user.click(screen.getByRole("button", { name: "Delete" }));

    expect(screen.getByText("MangaScans")).toBeInTheDocument();
    expect(screen.getByText("Scanlation Groups (1)")).toBeInTheDocument();
  });

  it("removes item and decrements count when confirm + 204", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1], 1));
    mock.onDelete("/scanlation_groups/sg-1").reply(204);
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<ScanlationGroupSection />);
    await waitFor(() => screen.getByText("MangaScans"));

    await user.click(screen.getByRole("button", { name: "Delete" }));

    await waitFor(() => {
      expect(screen.queryByText("MangaScans")).not.toBeInTheDocument();
    });
    expect(screen.getByText("Scanlation Groups (0)")).toBeInTheDocument();
    expect(screen.getByText("No groups yet.")).toBeInTheDocument();
  });

  it("keeps item when confirm + 500 error", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1], 1));
    mock.onDelete("/scanlation_groups/sg-1").reply(500, { detail: "Delete failed" });
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<ScanlationGroupSection />);
    await waitFor(() => screen.getByText("MangaScans"));

    await user.click(screen.getByRole("button", { name: "Delete" }));

    await waitFor(() => {
      expect(screen.getByText("MangaScans")).toBeInTheDocument();
    });
    expect(screen.getByText("Scanlation Groups (1)")).toBeInTheDocument();
  });

  // ── 3b. Coverage: create-guard and map two-items ─────────────────────────

  it("handleCreate early-returns when name is empty (fireEvent.submit bypasses disabled button)", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([], 0));
    let postCalled = false;
    mock.onPost("/scanlation_groups").reply(() => { postCalled = true; return [201, GROUP_1]; });

    render(<ScanlationGroupSection />);
    await waitFor(() => screen.getByText("No groups yet."));

    await user.click(screen.getByRole("button", { name: "+ New Group" }));

    const form = document.querySelector("form")!;
    fireEvent.submit(form);

    expect(postCalled).toBe(false);
    expect(screen.getByText("New Scanlation Group")).toBeInTheDocument();
  });

  it("updates only the saved group when list has multiple items", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1, GROUP_NO_SITE], 2));
    const UPDATED: ScanlationGroup = { ...GROUP_1, name: "MangaScans Updated" };
    mock.onPut("/scanlation_groups/sg-1").reply(200, UPDATED);

    render(<ScanlationGroupSection />);
    await waitFor(() => screen.getByText("MangaScans"));

    await user.click(screen.getAllByRole("button", { name: "Edit" })[0]);

    const nameInput = screen.getByDisplayValue("MangaScans");
    await user.clear(nameInput);
    await user.type(nameInput, "MangaScans Updated");

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("MangaScans Updated"));
    expect(screen.getByText("NoSite Group")).toBeInTheDocument();
  });

  // ── 5. Pagination ─────────────────────────────────────────────────────────

  it("does not show pagination when totalPages <= 1", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1], 1));
    render(<ScanlationGroupSection />);

    await waitFor(() => screen.getByText("MangaScans"));
    expect(screen.queryByRole("button", { name: "←" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "→" })).not.toBeInTheDocument();
  });

  it("shows pagination when totalPages > 1, prev disabled on first page", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1], 41));
    render(<ScanlationGroupSection />);

    await waitFor(() => screen.getByText("1 / 3"));

    const prevBtn = screen.getByRole("button", { name: "←" });
    const nextBtn = screen.getByRole("button", { name: "→" });
    expect(prevBtn).toBeDisabled();
    expect(nextBtn).not.toBeDisabled();
  });

  it("navigates to next page and disables next on last page", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1], 21));
    render(<ScanlationGroupSection />);

    await waitFor(() => screen.getByText("1 / 2"));

    await user.click(screen.getByRole("button", { name: "→" }));
    await waitFor(() => screen.getByText("2 / 2"));

    expect(screen.getByRole("button", { name: "←" })).not.toBeDisabled();
    expect(screen.getByRole("button", { name: "→" })).toBeDisabled();
  });

  it("navigates back to previous page", async () => {
    mock.onGet("/scanlation_groups").reply(200, hydra([GROUP_1], 21));
    render(<ScanlationGroupSection />);

    await waitFor(() => screen.getByText("1 / 2"));

    await user.click(screen.getByRole("button", { name: "→" }));
    await waitFor(() => screen.getByText("2 / 2"));

    await user.click(screen.getByRole("button", { name: "←" }));
    await waitFor(() => screen.getByText("1 / 2"));
  });
});
