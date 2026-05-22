import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MockAdapter from "axios-mock-adapter";
import api from "@/lib/api";
import ListDetailContent from "../ListDetailContent";
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
    name: "Action Picks",
    visibility: "public",
    user: "/api/users/u-1",
    mangas: [],
    ...overrides,
  };
}

const MANGA = {
  id: "m-1",
  title: "Berserk",
  status: "ongoing" as const,
  contentRating: "safe" as const,
  demographic: "seinen" as const,
  year: 1989,
};

// ── Tests ─────────────────────────────────────────────────────────────────────

describe("ListDetailContent", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
    mockAuthUser.current = TEST_USER;
    mockRouter.replace.mockReset();
  });

  afterEach(() => {
    mock.restore();
    mockAuthUser.current = null;
  });

  it("redirects to /login when unauthenticated", async () => {
    mockAuthUser.current = null;
    render(<ListDetailContent id="cl-1" />);

    await waitFor(() => {
      expect(mockRouter.replace).toHaveBeenCalledWith("/login");
    });
  });

  it("shows skeleton while loading", () => {
    mock.onGet("/custom_lists/cl-1").reply(() => new Promise(() => {}));
    render(<ListDetailContent id="cl-1" />);

    const pulses = document.querySelectorAll(".animate-pulse");
    expect(pulses.length).toBeGreaterThan(0);
  });

  it("renders list name and visibility", async () => {
    mock.onGet("/custom_lists/cl-1").reply(200, makeList());

    render(<ListDetailContent id="cl-1" />);

    await waitFor(() => {
      expect(screen.getByRole("heading", { name: "Action Picks" })).toBeInTheDocument();
    });
    expect(screen.getByText("public")).toBeInTheDocument();
  });

  it("shows error state on fetch failure", async () => {
    mock.onGet("/custom_lists/cl-1").reply(404, { detail: "Not found" });

    render(<ListDetailContent id="cl-1" />);

    await waitFor(() => {
      expect(screen.getByText("Not found")).toBeInTheDocument();
    });
  });

  it("renders manga rows when list has mangas", async () => {
    mock.onGet("/custom_lists/cl-1").reply(200, makeList({ mangas: [MANGA] }));

    render(<ListDetailContent id="cl-1" />);

    await waitFor(() => {
      expect(screen.getByText("Berserk")).toBeInTheDocument();
    });
    expect(screen.getByRole("button", { name: /remove berserk/i })).toBeInTheDocument();
  });

  it("shows empty state when list has no mangas", async () => {
    mock.onGet("/custom_lists/cl-1").reply(200, makeList({ mangas: [] }));

    render(<ListDetailContent id="cl-1" />);

    await waitFor(() => {
      expect(screen.getByText(/No manga in this list/)).toBeInTheDocument();
    });
  });

  it("removes a manga from the list", async () => {
    const ue = userEvent.setup();
    mock.onGet("/custom_lists/cl-1").reply(200, makeList({ mangas: [MANGA] }));
    mock.onDelete("/custom_lists/cl-1/mangas/m-1").reply(204);

    render(<ListDetailContent id="cl-1" />);
    await waitFor(() => screen.getByText("Berserk"));

    await ue.click(screen.getByRole("button", { name: /remove berserk/i }));

    await waitFor(() => {
      expect(screen.queryByText("Berserk")).not.toBeInTheDocument();
    });
  });

  it("enters edit mode when Edit is clicked", async () => {
    const ue = userEvent.setup();
    mock.onGet("/custom_lists/cl-1").reply(200, makeList());

    render(<ListDetailContent id="cl-1" />);
    await waitFor(() => screen.getByRole("heading", { name: "Action Picks" }));

    await ue.click(screen.getByRole("button", { name: /^edit$/i }));

    await waitFor(() => {
      expect(screen.getByDisplayValue("Action Picks")).toBeInTheDocument();
    });
  });

  it("shows delete confirm when Delete is clicked", async () => {
    const ue = userEvent.setup();
    mock.onGet("/custom_lists/cl-1").reply(200, makeList());

    render(<ListDetailContent id="cl-1" />);
    await waitFor(() => screen.getByRole("heading", { name: "Action Picks" }));

    await ue.click(screen.getByRole("button", { name: /^delete$/i }));

    await waitFor(() => {
      expect(screen.getByText("Are you sure?")).toBeInTheDocument();
    });
  });

  it("redirects to /lists after deleting", async () => {
    const ue = userEvent.setup();
    mock.onGet("/custom_lists/cl-1").reply(200, makeList());
    mock.onDelete("/custom_lists/cl-1").reply(204);

    render(<ListDetailContent id="cl-1" />);
    await waitFor(() => screen.getByRole("heading", { name: "Action Picks" }));

    // First click shows the confirm prompt
    await ue.click(screen.getByRole("button", { name: /^delete$/i }));
    await waitFor(() => screen.getByText("Are you sure?"));

    // Confirm — there are now two "Delete" buttons; the confirm one is last
    const deleteBtns = await screen.findAllByRole("button", { name: /^delete$/i });
    await ue.click(deleteBtns[deleteBtns.length - 1]);

    await waitFor(() => {
      expect(mockRouter.replace).toHaveBeenCalledWith("/lists");
    });
  });

  it("changes visibility via select in edit form", async () => {
    const ue = userEvent.setup();
    const updated = makeList({ name: "Action Picks", visibility: "hidden" });
    mock.onGet("/custom_lists/cl-1").reply(200, makeList());
    mock.onPut("/custom_lists/cl-1").reply(200, updated);

    render(<ListDetailContent id="cl-1" />);
    await waitFor(() => screen.getByRole("heading", { name: "Action Picks" }));

    await ue.click(screen.getByRole("button", { name: /^edit$/i }));
    await screen.findByDisplayValue("Action Picks");

    const select = screen.getByRole("combobox");
    await ue.selectOptions(select, "hidden");
    await ue.click(screen.getByRole("button", { name: /^save$/i }));

    await waitFor(() => {
      expect(screen.getByText("hidden")).toBeInTheDocument();
    });
  });

  it("submits edit form and updates list name", async () => {
    const ue = userEvent.setup();
    const updated = makeList({ name: "Renamed List", visibility: "hidden" });
    mock.onGet("/custom_lists/cl-1").reply(200, makeList());
    mock.onPut("/custom_lists/cl-1").reply(200, updated);

    render(<ListDetailContent id="cl-1" />);
    await waitFor(() => screen.getByRole("heading", { name: "Action Picks" }));

    await ue.click(screen.getByRole("button", { name: /^edit$/i }));
    const input = await screen.findByDisplayValue("Action Picks");
    await ue.clear(input);
    await ue.type(input, "Renamed List");
    await ue.click(screen.getByRole("button", { name: /^save$/i }));

    await waitFor(() => {
      expect(screen.getByRole("heading", { name: "Renamed List" })).toBeInTheDocument();
    });
  });

  it("shows edit error when update fails", async () => {
    const ue = userEvent.setup();
    mock.onGet("/custom_lists/cl-1").reply(200, makeList());
    mock.onPut("/custom_lists/cl-1").reply(422, { detail: "Name required" });

    render(<ListDetailContent id="cl-1" />);
    await waitFor(() => screen.getByRole("heading", { name: "Action Picks" }));

    await ue.click(screen.getByRole("button", { name: /^edit$/i }));
    await screen.findByDisplayValue("Action Picks");
    await ue.click(screen.getByRole("button", { name: /^save$/i }));

    await waitFor(() => {
      expect(screen.getByText("Name required")).toBeInTheDocument();
    });
  });

  it("cancels edit mode without saving", async () => {
    const ue = userEvent.setup();
    mock.onGet("/custom_lists/cl-1").reply(200, makeList());

    render(<ListDetailContent id="cl-1" />);
    await waitFor(() => screen.getByRole("heading", { name: "Action Picks" }));

    await ue.click(screen.getByRole("button", { name: /^edit$/i }));
    await screen.findByDisplayValue("Action Picks");

    await ue.click(screen.getByRole("button", { name: /^cancel$/i }));

    await waitFor(() => {
      expect(screen.getByRole("heading", { name: "Action Picks" })).toBeInTheDocument();
    });
    expect(screen.queryByDisplayValue("Action Picks")).not.toBeInTheDocument();
  });

  it("stays on page when delete fails", async () => {
    const ue = userEvent.setup();
    mock.onGet("/custom_lists/cl-1").reply(200, makeList());
    mock.onDelete("/custom_lists/cl-1").reply(500, { detail: "Delete failed" });

    render(<ListDetailContent id="cl-1" />);
    await waitFor(() => screen.getByRole("heading", { name: "Action Picks" }));

    await ue.click(screen.getByRole("button", { name: /^delete$/i }));
    await waitFor(() => screen.getByText("Are you sure?"));

    const deleteBtns = await screen.findAllByRole("button", { name: /^delete$/i });
    await ue.click(deleteBtns[deleteBtns.length - 1]);

    await waitFor(() => {
      expect(mockRouter.replace).not.toHaveBeenCalledWith("/lists");
    });
    expect(screen.getByRole("heading", { name: "Action Picks" })).toBeInTheDocument();
  });

  it("cancels delete confirm without deleting", async () => {
    const ue = userEvent.setup();
    mock.onGet("/custom_lists/cl-1").reply(200, makeList());

    render(<ListDetailContent id="cl-1" />);
    await waitFor(() => screen.getByRole("heading", { name: "Action Picks" }));

    await ue.click(screen.getByRole("button", { name: /^delete$/i }));
    await waitFor(() => screen.getByText("Are you sure?"));

    await ue.click(screen.getByRole("button", { name: /^cancel$/i }));

    await waitFor(() => {
      expect(screen.queryByText("Are you sure?")).not.toBeInTheDocument();
    });
    expect(screen.getByRole("heading", { name: "Action Picks" })).toBeInTheDocument();
  });

  it("retry button re-fetches on error", async () => {
    const ue = userEvent.setup();
    let callCount = 0;
    mock.onGet("/custom_lists/cl-1").reply(() => {
      callCount++;
      if (callCount === 1) return [404, { detail: "Not found" }];
      return [200, makeList()];
    });

    render(<ListDetailContent id="cl-1" />);
    await waitFor(() => screen.getByText("Not found"));

    await ue.click(screen.getByRole("button", { name: /try again/i }));

    await waitFor(() => {
      expect(screen.getByRole("heading", { name: "Action Picks" })).toBeInTheDocument();
    });
  });

  it("renders MangaRow with unknown status using fallback style", async () => {
    const mangaUnknownStatus = {
      ...MANGA,
      status: "unknown_status" as never,
    };
    mock.onGet("/custom_lists/cl-1").reply(200, makeList({ mangas: [mangaUnknownStatus] }));

    render(<ListDetailContent id="cl-1" />);

    await waitFor(() => {
      expect(screen.getByText("unknown_status")).toBeInTheDocument();
    });
  });

  it("breadcrumb links back to /lists", async () => {
    mock.onGet("/custom_lists/cl-1").reply(200, makeList());

    render(<ListDetailContent id="cl-1" />);
    await waitFor(() => screen.getByRole("heading", { name: "Action Picks" }));

    const breadcrumb = screen.getByRole("link", { name: "My Lists" });
    expect(breadcrumb).toHaveAttribute("href", "/lists");
  });

  it("renders MangaRow metadata branches: null year, none demographic, non-safe rating", async () => {
    const diverseManga = {
      ...MANGA,
      year: null,
      demographic: "none" as const,
      contentRating: "suggestive" as const,
    };
    mock.onGet("/custom_lists/cl-1").reply(200, makeList({ mangas: [diverseManga] }));

    render(<ListDetailContent id="cl-1" />);

    await waitFor(() => {
      expect(screen.getByText("Berserk")).toBeInTheDocument();
    });
  });

  it("keeps manga in list when removal API fails", async () => {
    const ue = userEvent.setup();
    mock.onGet("/custom_lists/cl-1").reply(200, makeList({ mangas: [MANGA] }));
    mock.onDelete("/custom_lists/cl-1/mangas/m-1").reply(500, { detail: "Remove failed" });

    render(<ListDetailContent id="cl-1" />);
    await waitFor(() => screen.getByText("Berserk"));

    await ue.click(screen.getByRole("button", { name: /remove berserk/i }));

    await waitFor(() => {
      expect(screen.getByText("Berserk")).toBeInTheDocument();
    });
  });

  it("shows empty state when list has undefined mangas", async () => {
    mock.onGet("/custom_lists/cl-1").reply(200, makeList({ mangas: undefined }));

    render(<ListDetailContent id="cl-1" />);

    await waitFor(() => {
      expect(screen.getByText(/No manga in this list/)).toBeInTheDocument();
    });
  });
});
