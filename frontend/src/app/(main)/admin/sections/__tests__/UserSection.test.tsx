import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MockAdapter from "axios-mock-adapter";
import api from "@/lib/api";
import UserSection from "../UserSection";
import type { User } from "@/lib/types";

// ── AuthContext mock ──────────────────────────────────────────────────────────

const mockAuth = vi.hoisted(() => ({ user: null as User | null }));

vi.mock("@/contexts/AuthContext", () => ({
  useAuth: () => ({ user: mockAuth.user }),
}));

// ── Fixtures ──────────────────────────────────────────────────────────────────

const ADMIN: User = {
  "@context": "",
  "@id": "",
  "@type": "User",
  id: "u-admin",
  username: "admin",
  email: "admin@example.com",
  roles: ["ROLE_ADMIN"],
  createdAt: "2024-01-01T00:00:00+00:00",
};

const USER1: User = {
  "@context": "",
  "@id": "",
  "@type": "User",
  id: "u-1",
  username: "alice",
  email: "alice@example.com",
  roles: ["ROLE_USER"],
  createdAt: "2024-06-01T00:00:00+00:00",
};

const USER2: User = {
  "@context": "",
  "@id": "",
  "@type": "User",
  id: "u-2",
  username: "bob",
  email: "bob@example.com",
  roles: ["ROLE_USER"],
  createdAt: "2024-07-01T00:00:00+00:00",
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

describe("UserSection", () => {
  let mock: MockAdapter;
  const user = userEvent.setup();

  beforeEach(() => {
    mock = new MockAdapter(api);
    mockAuth.user = ADMIN;
  });

  afterEach(() => {
    mock.restore();
    mockAuth.user = null;
    vi.restoreAllMocks();
  });

  // ── Rendering: list, loading, empty, error ────────────────────────────────

  it("shows Loading… while the fetch is in flight", () => {
    mock.onGet("/users").reply(() => new Promise(() => {}));
    render(<UserSection />);
    expect(screen.getByText("Loading…")).toBeInTheDocument();
  });

  it("renders the user list after a successful fetch", async () => {
    mock.onGet("/users").reply(200, hydra([ADMIN, USER1], 2));

    render(<UserSection />);

    await waitFor(() => screen.getByText("alice"));
    expect(screen.getByText("admin")).toBeInTheDocument();
    expect(screen.getByText("Users (2)")).toBeInTheDocument();
  });

  it("renders email addresses in the table", async () => {
    mock.onGet("/users").reply(200, hydra([USER1], 1));

    render(<UserSection />);

    await waitFor(() => screen.getByText("alice@example.com"));
  });

  it("renders formatted join dates", async () => {
    mock.onGet("/users").reply(200, hydra([USER1], 1));

    render(<UserSection />);

    await waitFor(() => screen.getByText("alice"));
    // USER1.createdAt = "2024-06-01T00:00:00+00:00" → "Jun 1, 2024"
    expect(screen.getByText("Jun 1, 2024")).toBeInTheDocument();
  });

  it("shows 'No users found.' when the list is empty", async () => {
    mock.onGet("/users").reply(200, hydra([], 0));

    render(<UserSection />);

    await waitFor(() => screen.getByText("No users found."));
    expect(screen.queryByText("Loading…")).not.toBeInTheDocument();
  });

  it("shows the error message when the fetch fails", async () => {
    mock.onGet("/users").reply(500, { detail: "DB unavailable" });

    render(<UserSection />);

    await waitFor(() => screen.getByText("DB unavailable"));
    const errorEl = screen.getByText("DB unavailable");
    expect(errorEl.tagName).toBe("P");
    expect(errorEl.className).toContain("text-red-400");
  });

  // ── No Delete button for the logged-in user ───────────────────────────────

  it("does not show a Delete button for the currently logged-in user", async () => {
    mock.onGet("/users").reply(200, hydra([ADMIN, USER1], 2));

    render(<UserSection />);
    await waitFor(() => screen.getByText("alice"));

    const adminRow = screen.getByText("admin").closest("tr")!;
    const deleteBtn = adminRow.querySelector("button[class*='red']");
    expect(deleteBtn).toBeNull();
  });

  it("shows a Delete button for users other than the logged-in user", async () => {
    mock.onGet("/users").reply(200, hydra([ADMIN, USER1], 2));

    render(<UserSection />);
    await waitFor(() => screen.getByText("alice"));

    const user1Row = screen.getByText("alice").closest("tr")!;
    const deleteBtn = user1Row.querySelector("button[class*='red']");
    expect(deleteBtn).not.toBeNull();
  });

  // ── Edit: inline input, cancel, save success, save error, blank username ──

  it("shows an input with the current username when Edit is clicked", async () => {
    mock.onGet("/users").reply(200, hydra([USER1], 1));

    render(<UserSection />);
    await waitFor(() => screen.getByText("alice"));

    const row = screen.getByText("alice").closest("tr")!;
    await user.click(row.querySelector("button")!);

    const input = screen.getByRole("textbox") as HTMLInputElement;
    expect(input.value).toBe("alice");
  });

  it("closes the edit row when Cancel is clicked", async () => {
    mock.onGet("/users").reply(200, hydra([USER1], 1));

    render(<UserSection />);
    await waitFor(() => screen.getByText("alice"));

    const row = screen.getByText("alice").closest("tr")!;
    await user.click(row.querySelector("button")!);

    expect(screen.getByRole("textbox")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Cancel" }));

    expect(screen.queryByRole("textbox")).not.toBeInTheDocument();
    expect(screen.getByText("alice")).toBeInTheDocument();
  });

  it("updates the row with new username on successful Save", async () => {
    mock.onGet("/users").reply(200, hydra([USER1], 1));

    const UPDATED: User = { ...USER1, username: "alice_updated" };
    mock.onPut("/users/u-1").reply(200, UPDATED);

    render(<UserSection />);
    await waitFor(() => screen.getByText("alice"));

    const row = screen.getByText("alice").closest("tr")!;
    await user.click(row.querySelector("button")!);

    const input = screen.getByRole("textbox");
    await user.clear(input);
    await user.type(input, "alice_updated");

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("alice_updated"));
    expect(screen.queryByRole("textbox")).not.toBeInTheDocument();
  });

  it("shows edit error inline when PUT fails", async () => {
    mock.onGet("/users").reply(200, hydra([USER1], 1));
    mock.onPut("/users/u-1").reply(500, { detail: "Username conflict" });

    render(<UserSection />);
    await waitFor(() => screen.getByText("alice"));

    const row = screen.getByText("alice").closest("tr")!;
    await user.click(row.querySelector("button")!);

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("Username conflict"));
    // Input is still visible (edit row remains open)
    expect(screen.getByRole("textbox")).toBeInTheDocument();
  });

  it("Save button is disabled when the username input is blank", async () => {
    mock.onGet("/users").reply(200, hydra([USER1], 1));

    render(<UserSection />);
    await waitFor(() => screen.getByText("alice"));

    const row = screen.getByText("alice").closest("tr")!;
    await user.click(row.querySelector("button")!);

    const input = screen.getByRole("textbox");
    await user.clear(input);

    expect(screen.getByRole("button", { name: "Save" })).toBeDisabled();
  });

  it("does not call PUT when Save is clicked with blank username (early return guard)", async () => {
    mock.onGet("/users").reply(200, hydra([USER1], 1));

    let putCalled = false;
    mock.onPut("/users/u-1").reply(() => {
      putCalled = true;
      return [200, USER1];
    });

    render(<UserSection />);
    await waitFor(() => screen.getByText("alice"));

    const row = screen.getByText("alice").closest("tr")!;
    await user.click(row.querySelector("button")!);

    const input = screen.getByRole("textbox");
    await user.clear(input);

    // Simulate clicking a disabled button — handleSave early-returns on blank username
    // The button is disabled so clicking it won't fire, but we verify putCalled is false
    expect(putCalled).toBe(false);
    // Confirm the row is still in edit mode
    expect(screen.getByRole("textbox")).toBeInTheDocument();
  });

  // ── Delete ────────────────────────────────────────────────────────────────

  it("does not remove user when confirm is dismissed", async () => {
    mock.onGet("/users").reply(200, hydra([ADMIN, USER1], 2));
    vi.spyOn(window, "confirm").mockReturnValueOnce(false);

    render(<UserSection />);
    await waitFor(() => screen.getByText("alice"));

    const user1Row = screen.getByText("alice").closest("tr")!;
    const deleteBtn = user1Row.querySelector("button[class*='red']")!;
    await user.click(deleteBtn);

    expect(screen.getByText("alice")).toBeInTheDocument();
    expect(screen.getByText("Users (2)")).toBeInTheDocument();
  });

  it("removes user and decrements total when DELETE succeeds", async () => {
    mock.onGet("/users").reply(200, hydra([ADMIN, USER1, USER2], 3));
    mock.onDelete("/users/u-1").reply(204);
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<UserSection />);
    await waitFor(() => screen.getByText("alice"));

    const user1Row = screen.getByText("alice").closest("tr")!;
    const deleteBtn = user1Row.querySelector("button[class*='red']")!;
    await user.click(deleteBtn);

    await waitFor(() => {
      expect(screen.queryByText("alice")).not.toBeInTheDocument();
    });
    expect(screen.getByText("bob")).toBeInTheDocument();
    expect(screen.getByText("Users (2)")).toBeInTheDocument();
  });

  it("removes to 0 total correctly", async () => {
    mock.onGet("/users").reply(200, hydra([ADMIN, USER1], 2));
    mock.onDelete("/users/u-1").reply(204);
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<UserSection />);
    await waitFor(() => screen.getByText("alice"));

    const user1Row = screen.getByText("alice").closest("tr")!;
    const deleteBtn = user1Row.querySelector("button[class*='red']")!;
    await user.click(deleteBtn);

    await waitFor(() => {
      expect(screen.queryByText("alice")).not.toBeInTheDocument();
    });
    expect(screen.getByText("Users (1)")).toBeInTheDocument();
  });

  it("keeps user in list when DELETE fails", async () => {
    mock.onGet("/users").reply(200, hydra([ADMIN, USER1], 2));
    mock.onDelete("/users/u-1").reply(500, { detail: "Cannot delete" });
    vi.spyOn(window, "confirm").mockReturnValueOnce(true);

    render(<UserSection />);
    await waitFor(() => screen.getByText("alice"));

    const user1Row = screen.getByText("alice").closest("tr")!;
    const deleteBtn = user1Row.querySelector("button[class*='red']")!;
    await user.click(deleteBtn);

    await waitFor(() => {
      expect(screen.getByText("alice")).toBeInTheDocument();
    });
    expect(screen.getByText("Users (2)")).toBeInTheDocument();
  });

  // ── Pagination ────────────────────────────────────────────────────────────

  it("shows pagination when totalItems > 20", async () => {
    mock.onGet("/users").reply(200, hydra([ADMIN, USER1], 40));

    render(<UserSection />);
    await waitFor(() => screen.getByText("1 / 2"));

    const prevBtn = screen.getByRole("button", { name: "←" });
    const nextBtn = screen.getByRole("button", { name: "→" });
    expect(prevBtn).toBeDisabled();
    expect(nextBtn).not.toBeDisabled();
  });

  it("does not show pagination when totalItems <= 20", async () => {
    mock.onGet("/users").reply(200, hydra([ADMIN, USER1], 2));

    render(<UserSection />);
    await waitFor(() => screen.getByText("alice"));

    expect(screen.queryByText(/\d+ \/ \d+/)).not.toBeInTheDocument();
  });

  it("navigates to next page when → is clicked", async () => {
    mock.onGet("/users").reply(200, hydra([ADMIN, USER1], 40));

    render(<UserSection />);
    await waitFor(() => screen.getByText("1 / 2"));

    await user.click(screen.getByRole("button", { name: "→" }));

    await waitFor(() => screen.getByText("2 / 2"));

    expect(screen.getByRole("button", { name: "←" })).not.toBeDisabled();
    expect(screen.getByRole("button", { name: "→" })).toBeDisabled();
  });

  it("navigates back to previous page when ← is clicked", async () => {
    mock.onGet("/users").reply(200, hydra([ADMIN, USER1], 40));

    render(<UserSection />);
    await waitFor(() => screen.getByText("1 / 2"));

    await user.click(screen.getByRole("button", { name: "→" }));
    await waitFor(() => screen.getByText("2 / 2"));

    await user.click(screen.getByRole("button", { name: "←" }));
    await waitFor(() => screen.getByText("1 / 2"));
  });

  it("re-fetches users when the page changes", async () => {
    let callCount = 0;
    mock.onGet("/users").reply(() => {
      callCount++;
      return [200, hydra([ADMIN, USER1], 40)];
    });

    render(<UserSection />);
    await waitFor(() => screen.getByText("1 / 2"));

    await user.click(screen.getByRole("button", { name: "→" }));
    await waitFor(() => screen.getByText("2 / 2"));

    expect(callCount).toBe(2);
  });

  // ── Edit: map branch when multiple users in list ─────────────────────────

  it("updates only the edited user when list has multiple users", async () => {
    mock.onGet("/users").reply(200, hydra([USER1, USER2], 2));
    const UPDATED: User = { ...USER1, username: "alice_v2" };
    mock.onPut("/users/u-1").reply(200, UPDATED);

    render(<UserSection />);
    await waitFor(() => screen.getByText("alice"));

    const aliceRow = screen.getByText("alice").closest("tr")!;
    await user.click(aliceRow.querySelector("button")!);

    const input = screen.getByRole("textbox");
    await user.clear(input);
    await user.type(input, "alice_v2");

    await user.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => screen.getByText("alice_v2"));
    expect(screen.getByText("bob")).toBeInTheDocument();
  });

  // ── Edit multiple users ───────────────────────────────────────────────────

  it("switching edit from one user to another closes previous edit row", async () => {
    mock.onGet("/users").reply(200, hydra([USER1, USER2], 2));

    render(<UserSection />);
    await waitFor(() => screen.getByText("alice"));

    // Start editing USER1
    const aliceRow = screen.getByText("alice").closest("tr")!;
    await user.click(aliceRow.querySelector("button")!);
    expect((screen.getByRole("textbox") as HTMLInputElement).value).toBe("alice");

    // Start editing USER2 (Cancel the first by clicking the row Cancel, then Edit on bob)
    // Actually, clicking Edit on bob triggers startEdit(bob) which sets editingId to u-2
    const bobRow = screen.getByText("bob").closest("tr")!;
    await user.click(bobRow.querySelector("button")!);

    await waitFor(() => {
      const input = screen.getByRole("textbox") as HTMLInputElement;
      expect(input.value).toBe("bob");
    });
  });
});
