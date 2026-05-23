import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MockAdapter from "axios-mock-adapter";
import api from "@/lib/api";
import AdminContent from "../AdminContent";
import type { Manga, User } from "@/lib/types";

// ── Router mock ──────────────────────────────────────────────────────────────

const mockRouter = vi.hoisted(() => ({ replace: vi.fn() }));

vi.mock("next/navigation", () => ({
  useRouter: () => mockRouter,
}));

// ── AuthContext mock ─────────────────────────────────────────────────────────

const mockAuth = vi.hoisted(() => ({
  user: null as User | null,
  loading: false,
}));

vi.mock("@/contexts/AuthContext", () => ({
  useAuth: () => ({ user: mockAuth.user, loading: mockAuth.loading }),
}));

// ── Fixtures ─────────────────────────────────────────────────────────────────

const ADMIN_USER: User = {
  "@context": "/api/contexts/User",
  "@id": "/api/users/u-admin",
  "@type": "User",
  id: "u-admin",
  email: "admin@example.com",
  username: "admin",
  createdAt: "2024-01-01T00:00:00+00:00",
  roles: ["ROLE_USER", "ROLE_ADMIN"],
};

const REGULAR_USER: User = {
  "@context": "/api/contexts/User",
  "@id": "/api/users/u-1",
  "@type": "User",
  id: "u-1",
  email: "user@example.com",
  username: "regularuser",
  createdAt: "2024-06-01T00:00:00+00:00",
  roles: ["ROLE_USER"],
};

const MANGA_1: Manga = {
  "@context": "/api/contexts/Manga",
  "@id": "/api/mangas/m-1",
  "@type": "Manga",
  id: "m-1",
  title: "Test Manga",
  status: "ongoing",
  contentRating: "safe",
  demographic: "seinen",
  year: 2020,
  createdAt: "2024-01-01T00:00:00+00:00",
};

function hydra<T>(member: T[], totalItems: number) {
  return {
    "@context": "/api/contexts/Collection",
    "@id": "/api/collection",
    "@type": "hydra:Collection",
    totalItems,
    member,
  };
}

/**
 * Sets up standard mock responses.
 * Stats calls use itemsPerPage=1; section list calls use page param only.
 */
function setupMocks(
  mock: MockAdapter,
  opts: {
    mangaTotal?: number;
    userTotal?: number;
    chapterTotal?: number;
    mangas?: Manga[];
    users?: User[];
    mangaListStatus?: number;
    userListStatus?: number;
  } = {},
) {
  const {
    mangaTotal = 5,
    userTotal = 7,
    chapterTotal = 42,
    mangas = [MANGA_1],
    users = [ADMIN_USER, REGULAR_USER],
    mangaListStatus = 200,
    userListStatus = 200,
  } = opts;

  mock.onGet("/mangas").reply((cfg) => {
    if (cfg.params?.itemsPerPage === 1) return [200, hydra([], mangaTotal)];
    return [mangaListStatus, mangaListStatus === 200 ? hydra(mangas, mangaTotal) : { detail: "Manga list error" }];
  });

  mock.onGet("/users").reply((cfg) => {
    if (cfg.params?.itemsPerPage === 1) return [200, hydra([], userTotal)];
    return [userListStatus, userListStatus === 200 ? hydra(users, userTotal) : { detail: "User list error" }];
  });

  mock.onGet("/chapters").reply(200, hydra([], chapterTotal));
  mock.onGet("/tags").reply(200, hydra([], 0));
  mock.onGet("/creators").reply(200, hydra([], 0));
  mock.onGet("/scanlation_groups").reply(200, hydra([], 0));
}

// ── Tests ─────────────────────────────────────────────────────────────────────

describe("AdminContent", () => {
  let mock: MockAdapter;
  const user = userEvent.setup();

  beforeEach(() => {
    mock = new MockAdapter(api);
    mockAuth.user = ADMIN_USER;
    mockAuth.loading = false;
    mockRouter.replace.mockReset();
  });

  afterEach(() => {
    mock.restore();
    mockAuth.user = null;
    mockAuth.loading = false;
  });

  // ── Auth / loading guards ─────────────────────────────────────────────────

  it("shows loading skeleton while authLoading is true", () => {
    mockAuth.loading = true;
    render(<AdminContent />);
    const pulses = document.querySelectorAll(".animate-pulse");
    expect(pulses.length).toBeGreaterThan(0);
  });

  it("shows loading skeleton while stats are fetching", () => {
    mock.onGet("/mangas").reply(() => new Promise(() => {}));
    mock.onGet("/users").reply(() => new Promise(() => {}));
    mock.onGet("/chapters").reply(() => new Promise(() => {}));

    render(<AdminContent />);
    const pulses = document.querySelectorAll(".animate-pulse");
    expect(pulses.length).toBeGreaterThan(0);
  });

  it("redirects to /login when not authenticated", async () => {
    mockAuth.user = null;
    render(<AdminContent />);

    await waitFor(() => {
      expect(mockRouter.replace).toHaveBeenCalledWith("/login");
    });
  });

  it("redirects to / when user lacks ROLE_ADMIN", async () => {
    mockAuth.user = REGULAR_USER;
    render(<AdminContent />);

    await waitFor(() => {
      expect(mockRouter.replace).toHaveBeenCalledWith("/");
    });
  });

  it("returns null after loading when user has no admin role", async () => {
    setupMocks(mock);
    const { rerender } = render(<AdminContent />);
    await waitFor(() => screen.getByText("Admin Panel"));

    mockAuth.user = REGULAR_USER;
    rerender(<AdminContent />);

    await waitFor(() => {
      expect(screen.queryByText("Admin Panel")).not.toBeInTheDocument();
    });
  });

  // ── Happy path ────────────────────────────────────────────────────────────

  it("renders stats and manga table on load", async () => {
    setupMocks(mock, { mangaTotal: 5, userTotal: 7, chapterTotal: 42 });

    render(<AdminContent />);

    await waitFor(() => screen.getByText("Test Manga"));

    expect(screen.getByText("5")).toBeInTheDocument();
    expect(screen.getByText("7")).toBeInTheDocument();
    expect(screen.getByText("42")).toBeInTheDocument();
  });

  it("renders user table when Users tab is clicked", async () => {
    setupMocks(mock);

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Admin Panel"));

    await user.click(screen.getByRole("button", { name: "Users" }));

    await waitFor(() => screen.getByText("regularuser"));
    expect(screen.getByText("admin")).toBeInTheDocument();
  });

  it("does not show a delete button for the logged-in admin user", async () => {
    setupMocks(mock);

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Admin Panel"));

    await user.click(screen.getByRole("button", { name: "Users" }));
    await waitFor(() => screen.getByText("regularuser"));

    const adminRow = screen.getByText("admin").closest("tr");
    expect(adminRow?.querySelector("button[class*='red']")).toBeNull();
  });

  // ── Tab navigation ────────────────────────────────────────────────────────

  it("switches between tabs", async () => {
    setupMocks(mock);

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Test Manga"));

    await user.click(screen.getByRole("button", { name: "Chapters" }));
    await waitFor(() => screen.getByText("No chapters yet."));

    expect(screen.queryByText("Test Manga")).not.toBeInTheDocument();
  });

  // ── Error state ───────────────────────────────────────────────────────────

  it("shows error state when stats fetch throws", async () => {
    mock.onGet("/mangas").reply(500, { detail: "Stats failed" });
    mock.onGet("/users").reply(500);
    mock.onGet("/chapters").reply(500);

    render(<AdminContent />);

    await waitFor(() => {
      expect(screen.getByText(/stats failed/i)).toBeInTheDocument();
    });
  });

  it("renders without manga rows when manga list request fails", async () => {
    setupMocks(mock, { mangaListStatus: 500 });

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Admin Panel"));

    expect(screen.queryByText("Test Manga")).not.toBeInTheDocument();
  });

  it("renders manga table even when user list would fail", async () => {
    setupMocks(mock, { userListStatus: 500 });

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Test Manga"));

    expect(screen.queryByText("regularuser")).not.toBeInTheDocument();
  });

  // ── Manga delete ──────────────────────────────────────────────────────────

  it("does not delete manga when confirm is dismissed", async () => {
    setupMocks(mock);
    vi.spyOn(window, "confirm").mockReturnValueOnce(false);

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Test Manga"));

    const mangaRow = screen.getByText("Test Manga").closest("tr");
    const deleteBtn = mangaRow!.querySelector("button[class*='red']")!;
    await user.click(deleteBtn);

    expect(screen.getByText("Test Manga")).toBeInTheDocument();
  });

  it("deletes manga and removes it from the table on success", async () => {
    setupMocks(mock, { mangaTotal: 5 });
    mock.onDelete("/mangas/m-1").reply(204);
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Test Manga"));

    const mangaRow = screen.getByText("Test Manga").closest("tr");
    const deleteBtn = mangaRow!.querySelector("button[class*='red']")!;
    await user.click(deleteBtn);

    await waitFor(() => {
      expect(screen.queryByText("Test Manga")).not.toBeInTheDocument();
    });
    expect(screen.getByText("Manga (4)")).toBeInTheDocument();
  });

  it("leaves manga in table when delete request fails", async () => {
    setupMocks(mock);
    mock.onDelete("/mangas/m-1").reply(500, { detail: "Delete failed" });
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Test Manga"));

    const mangaRow = screen.getByText("Test Manga").closest("tr");
    const deleteBtn = mangaRow!.querySelector("button[class*='red']")!;
    await user.click(deleteBtn);

    await waitFor(() => {
      expect(screen.getByText("Test Manga")).toBeInTheDocument();
    });
  });

  // ── User delete ───────────────────────────────────────────────────────────

  it("does not delete user when confirm is dismissed", async () => {
    setupMocks(mock);
    vi.spyOn(window, "confirm").mockReturnValueOnce(false);

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Admin Panel"));

    await user.click(screen.getByRole("button", { name: "Users" }));
    await waitFor(() => screen.getByText("regularuser"));

    const userRow = screen.getByText("regularuser").closest("tr");
    const deleteBtn = userRow!.querySelector("button[class*='red']")!;
    await user.click(deleteBtn);

    expect(screen.getByText("regularuser")).toBeInTheDocument();
  });

  it("deletes user and removes them from the table on success", async () => {
    setupMocks(mock, { userTotal: 7 });
    mock.onDelete("/users/u-1").reply(204);
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Admin Panel"));

    await user.click(screen.getByRole("button", { name: "Users" }));
    await waitFor(() => screen.getByText("regularuser"));

    const userRow = screen.getByText("regularuser").closest("tr");
    const deleteBtn = userRow!.querySelector("button[class*='red']")!;
    await user.click(deleteBtn);

    await waitFor(() => {
      expect(screen.queryByText("regularuser")).not.toBeInTheDocument();
    });
    expect(screen.getByText("Users (6)")).toBeInTheDocument();
  });

  it("leaves user in table when delete request fails", async () => {
    setupMocks(mock);
    mock.onDelete("/users/u-1").reply(500, { detail: "Delete failed" });
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Admin Panel"));

    await user.click(screen.getByRole("button", { name: "Users" }));
    await waitFor(() => screen.getByText("regularuser"));

    const userRow = screen.getByText("regularuser").closest("tr");
    const deleteBtn = userRow!.querySelector("button[class*='red']")!;
    await user.click(deleteBtn);

    await waitFor(() => {
      expect(screen.getByText("regularuser")).toBeInTheDocument();
    });
  });

  // ── Year display ─────────────────────────────────────────────────────────

  it("shows '—' when manga year is null", async () => {
    const noYearManga: Manga = { ...MANGA_1, id: "m-2", title: "No Year Manga", year: null };
    setupMocks(mock, { mangas: [noYearManga] });

    render(<AdminContent />);
    await waitFor(() => screen.getByText("No Year Manga"));

    expect(screen.getByText("—")).toBeInTheDocument();
  });

  // ── Manga pagination ──────────────────────────────────────────────────────

  it("does not show pagination when there is only one page", async () => {
    setupMocks(mock, { mangaTotal: 5 });

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Admin Panel"));

    expect(screen.queryByText(/1 \/ 1/)).not.toBeInTheDocument();
  });

  it("shows manga pagination and disables prev on first page", async () => {
    setupMocks(mock, { mangaTotal: 40 });

    render(<AdminContent />);
    await waitFor(() => screen.getByText("1 / 2"));

    const [prevBtn, nextBtn] = screen.getAllByRole("button", { name: /←|→/ });
    expect(prevBtn).toBeDisabled();
    expect(nextBtn).not.toBeDisabled();
  });

  it("navigates to next manga page and disables next on last page", async () => {
    setupMocks(mock, { mangaTotal: 40 });

    render(<AdminContent />);
    await waitFor(() => screen.getByText("1 / 2"));

    const [, nextBtn] = screen.getAllByRole("button", { name: /←|→/ });
    await user.click(nextBtn);

    await waitFor(() => screen.getByText("2 / 2"));

    const [prevBtn, nextBtn2] = screen.getAllByRole("button", { name: /←|→/ });
    expect(prevBtn).not.toBeDisabled();
    expect(nextBtn2).toBeDisabled();
  });

  it("navigates back to previous manga page", async () => {
    setupMocks(mock, { mangaTotal: 40 });

    render(<AdminContent />);
    await waitFor(() => screen.getByText("1 / 2"));

    const [, nextBtn] = screen.getAllByRole("button", { name: /←|→/ });
    await user.click(nextBtn);
    await waitFor(() => screen.getByText("2 / 2"));

    const [prevBtn] = screen.getAllByRole("button", { name: /←|→/ });
    await user.click(prevBtn);
    await waitFor(() => screen.getByText("1 / 2"));
  });

  // ── User pagination ───────────────────────────────────────────────────────

  it("shows user pagination and navigates", async () => {
    setupMocks(mock, { mangaTotal: 5, userTotal: 40 });

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Admin Panel"));

    await user.click(screen.getByRole("button", { name: "Users" }));
    await waitFor(() => screen.getByText("1 / 2"));

    const [, nextBtn] = screen.getAllByRole("button", { name: /←|→/ });
    await user.click(nextBtn);

    await waitFor(() => screen.getByText("2 / 2"));
  });

  // ── Additional tab branches ───────────────────────────────────────────────

  it("renders TagSection when Tags tab is clicked", async () => {
    setupMocks(mock);

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Admin Panel"));

    await user.click(screen.getByRole("button", { name: "Tags" }));
    await waitFor(() => screen.getByText("No tags yet."));
  });

  it("renders CreatorSection when Creators tab is clicked", async () => {
    setupMocks(mock);

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Admin Panel"));

    await user.click(screen.getByRole("button", { name: "Creators" }));
    await waitFor(() => screen.getByText("No creators yet."));
  });

  it("renders ScanlationGroupSection when Scanlation Groups tab is clicked", async () => {
    setupMocks(mock);

    render(<AdminContent />);
    await waitFor(() => screen.getByText("Admin Panel"));

    await user.click(screen.getByRole("button", { name: "Scanlation Groups" }));
    await waitFor(() => screen.getByText("No groups yet."));
  });
});
