import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, fireEvent } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { User } from "@/lib/types";
import Sidebar from "../Sidebar";

// ── next/image mock ──────────────────────────────────────────────────────────

vi.mock("next/image", () => ({
  // eslint-disable-next-line @next/next/no-img-element
  default: (props: { alt: string }) => <img alt={props.alt} />,
}));

// ── next/navigation mock ─────────────────────────────────────────────────────

const mockPathname = vi.hoisted(() => ({ value: "/" }));

vi.mock("next/navigation", () => ({
  usePathname: () => mockPathname.value,
}));

// ── AuthContext mock ─────────────────────────────────────────────────────────

const mockAuth = vi.hoisted(() => ({
  user: null as User | null,
  loading: false,
  logout: vi.fn(),
}));

vi.mock("@/contexts/AuthContext", () => ({
  useAuth: () => mockAuth,
}));

// ── Fixtures ─────────────────────────────────────────────────────────────────

const ADMIN_USER: User = {
  "@context": "",
  "@id": "",
  "@type": "User",
  id: "u-1",
  username: "admin",
  email: "admin@example.com",
  roles: ["ROLE_USER", "ROLE_ADMIN"],
  createdAt: "2024-01-01T00:00:00+00:00",
};

const REGULAR_USER: User = {
  "@context": "",
  "@id": "",
  "@type": "User",
  id: "u-2",
  username: "alice",
  email: "alice@example.com",
  roles: ["ROLE_USER"],
  createdAt: "2024-06-01T00:00:00+00:00",
};

// ── Helpers ───────────────────────────────────────────────────────────────────

function renderSidebar(open: boolean, onClose = vi.fn()) {
  return render(<Sidebar open={open} onClose={onClose} />);
}

// ── Tests ─────────────────────────────────────────────────────────────────────

describe("Sidebar", () => {
  beforeEach(() => {
    mockPathname.value = "/";
    mockAuth.user = null;
    mockAuth.loading = false;
    mockAuth.logout.mockReset();
    document.body.style.overflow = "";
  });

  afterEach(() => {
    document.body.style.overflow = "";
  });

  // ── Visibility ────────────────────────────────────────────────────────────

  it("renders aside with translate-x-0 when open", () => {
    renderSidebar(true);

    const aside = document.querySelector("aside");
    expect(aside).toBeInTheDocument();
    expect(aside).toHaveClass("translate-x-0");
    expect(screen.getByText("MangaDex")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /home/i })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /search/i })).toBeInTheDocument();
    expect(screen.getByText("v1.0.0")).toBeInTheDocument();
  });

  it("renders aside with -translate-x-full when closed", () => {
    renderSidebar(false);

    const aside = document.querySelector("aside");
    expect(aside).toBeInTheDocument();
    expect(aside).toHaveClass("-translate-x-full");
  });

  it("does not render the overlay div when closed", () => {
    renderSidebar(false);

    const overlay = document.querySelector(".bg-black\\/60");
    expect(overlay).not.toBeInTheDocument();
  });

  // ── Overlay ───────────────────────────────────────────────────────────────

  it("renders overlay div with bg-black/60 when open", () => {
    renderSidebar(true);

    const overlay = document.querySelector(".bg-black\\/60");
    expect(overlay).toBeInTheDocument();
  });

  it("calls onClose when overlay is clicked", async () => {
    const onClose = vi.fn();
    renderSidebar(true, onClose);

    const overlay = document.querySelector(".bg-black\\/60") as HTMLElement;
    await userEvent.click(overlay);

    expect(onClose).toHaveBeenCalled();
  });

  // ── Keyboard ──────────────────────────────────────────────────────────────

  it("calls onClose when Escape key is pressed while open", () => {
    const onClose = vi.fn();
    renderSidebar(true, onClose);

    fireEvent.keyDown(document, { key: "Escape" });

    expect(onClose).toHaveBeenCalled();
  });

  it("does not call onClose for non-Escape keys while open", () => {
    const onClose = vi.fn();
    renderSidebar(true, onClose);
    // Reset calls from the pathname effect that fires on mount
    onClose.mockClear();

    fireEvent.keyDown(document, { key: "Enter" });

    expect(onClose).not.toHaveBeenCalled();
  });

  it("does not call onClose for Escape key when closed", () => {
    const onClose = vi.fn();
    renderSidebar(false, onClose);
    // Reset calls from the pathname effect that fires on mount
    onClose.mockClear();

    fireEvent.keyDown(document, { key: "Escape" });

    expect(onClose).not.toHaveBeenCalled();
  });

  // ── Pathname effect ───────────────────────────────────────────────────────

  it("calls onClose on initial mount (pathname effect runs immediately)", () => {
    const onClose = vi.fn();
    renderSidebar(true, onClose);

    // The pathname effect fires on every render including the first
    expect(onClose).toHaveBeenCalled();
  });

  it("calls onClose again when pathname changes and component re-renders", () => {
    const onClose = vi.fn();
    const { rerender } = renderSidebar(true, onClose);
    const callsAfterMount = onClose.mock.calls.length;

    mockPathname.value = "/search";
    rerender(<Sidebar open={true} onClose={onClose} />);

    expect(onClose.mock.calls.length).toBeGreaterThan(callsAfterMount);
  });

  // ── Body overflow ─────────────────────────────────────────────────────────

  it("locks body overflow to hidden when open", () => {
    renderSidebar(true);

    expect(document.body.style.overflow).toBe("hidden");
  });

  it("restores body overflow to empty string when closed", () => {
    renderSidebar(false);

    expect(document.body.style.overflow).toBe("");
  });

  it("restores body overflow on cleanup when open component unmounts", () => {
    const { unmount } = renderSidebar(true);
    expect(document.body.style.overflow).toBe("hidden");

    unmount();

    expect(document.body.style.overflow).toBe("");
  });

  // ── Auth loading state ────────────────────────────────────────────────────

  it("shows animated loading skeleton when loading is true", () => {
    mockAuth.loading = true;
    renderSidebar(true);

    const skeleton = document.querySelector(".animate-pulse");
    expect(skeleton).toBeInTheDocument();
    expect(screen.queryByText("Sign In")).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /sign out/i })).not.toBeInTheDocument();
  });

  it("does not show My Library section while loading", () => {
    mockAuth.user = REGULAR_USER;
    mockAuth.loading = true;
    renderSidebar(true);

    expect(screen.queryByText("My Library")).not.toBeInTheDocument();
  });

  it("does not show Admin section while loading", () => {
    mockAuth.user = ADMIN_USER;
    mockAuth.loading = true;
    renderSidebar(true);

    expect(screen.queryByText("Admin")).not.toBeInTheDocument();
  });

  // ── Logged out state ──────────────────────────────────────────────────────

  it("shows Sign In and Create Account links when logged out", () => {
    renderSidebar(true);

    expect(screen.getByRole("link", { name: /sign in/i })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /create account/i })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /sign out/i })).not.toBeInTheDocument();
  });

  it("does not show My Library section when logged out", () => {
    renderSidebar(true);

    expect(screen.queryByText("My Library")).not.toBeInTheDocument();
    expect(screen.queryByRole("link", { name: /my lists/i })).not.toBeInTheDocument();
  });

  it("Sign In link points to /login and Create Account to /register", () => {
    renderSidebar(true);

    expect(screen.getByRole("link", { name: /sign in/i })).toHaveAttribute("href", "/login");
    expect(screen.getByRole("link", { name: /create account/i })).toHaveAttribute("href", "/register");
  });

  // ── Logged in (regular user) ──────────────────────────────────────────────

  it("shows '?' avatar initial when user has no username", () => {
    mockAuth.user = { ...REGULAR_USER, username: undefined as unknown as string };
    renderSidebar(true);

    expect(screen.getByText("?")).toBeInTheDocument();
  });

  it("shows username, email, and first-letter avatar for a logged-in regular user", () => {
    mockAuth.user = REGULAR_USER;
    renderSidebar(true);

    expect(screen.getByText("alice")).toBeInTheDocument();
    expect(screen.getByText("alice@example.com")).toBeInTheDocument();
    // First letter of username, uppercased
    expect(screen.getByText("A")).toBeInTheDocument();
  });

  it("shows Sign Out button for logged-in user", () => {
    mockAuth.user = REGULAR_USER;
    renderSidebar(true);

    expect(screen.getByRole("button", { name: /sign out/i })).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: /sign in/i })).not.toBeInTheDocument();
  });

  it("shows My Library section for a logged-in regular user", () => {
    mockAuth.user = REGULAR_USER;
    renderSidebar(true);

    expect(screen.getByText("My Library")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /my lists/i })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /follows/i })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /reading history/i })).toBeInTheDocument();
  });

  it("does not show Admin section for a regular user", () => {
    mockAuth.user = REGULAR_USER;
    renderSidebar(true);

    expect(screen.queryByText("Admin")).not.toBeInTheDocument();
    expect(screen.queryByRole("link", { name: /admin panel/i })).not.toBeInTheDocument();
  });

  // ── Logged in (admin user) ────────────────────────────────────────────────

  it("shows Admin section for an admin user", () => {
    mockAuth.user = ADMIN_USER;
    renderSidebar(true);

    expect(screen.getByText("Admin")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /admin panel/i })).toBeInTheDocument();
  });

  it("shows username, email, and avatar for admin user", () => {
    mockAuth.user = ADMIN_USER;
    renderSidebar(true);

    expect(screen.getByText("admin")).toBeInTheDocument();
    expect(screen.getByText("admin@example.com")).toBeInTheDocument();
    expect(screen.getByText("A")).toBeInTheDocument();
  });

  // ── Sign Out ──────────────────────────────────────────────────────────────

  it("calls logout when Sign Out button is clicked", async () => {
    mockAuth.user = REGULAR_USER;
    renderSidebar(true);

    await userEvent.click(screen.getByRole("button", { name: /sign out/i }));

    expect(mockAuth.logout).toHaveBeenCalledTimes(1);
  });

  // ── Active link styling ───────────────────────────────────────────────────

  it("applies text-md-accent class to the active nav link", () => {
    mockPathname.value = "/search";
    renderSidebar(true);

    const searchLink = screen.getByRole("link", { name: /search/i });
    expect(searchLink).toHaveClass("text-md-accent");
  });

  it("does not apply text-md-accent to inactive nav links", () => {
    mockPathname.value = "/search";
    renderSidebar(true);

    const homeLink = screen.getByRole("link", { name: /home/i });
    expect(homeLink).not.toHaveClass("text-md-accent");
  });

  it("applies text-md-accent to a My Library link when it is active", () => {
    mockAuth.user = REGULAR_USER;
    mockPathname.value = "/lists";
    renderSidebar(true);

    const listsLink = screen.getByRole("link", { name: /my lists/i });
    expect(listsLink).toHaveClass("text-md-accent");
  });

  it("applies text-md-accent to Admin Panel link when it is active", () => {
    mockAuth.user = ADMIN_USER;
    mockPathname.value = "/admin";
    renderSidebar(true);

    const adminLink = screen.getByRole("link", { name: /admin panel/i });
    expect(adminLink).toHaveClass("text-md-accent");
  });

  // ── Nav link hrefs ────────────────────────────────────────────────────────

  it("has correct href attributes for all nav links when logged in", () => {
    mockAuth.user = REGULAR_USER;
    renderSidebar(true);

    expect(screen.getByRole("link", { name: /home/i })).toHaveAttribute("href", "/");
    expect(screen.getByRole("link", { name: /search/i })).toHaveAttribute("href", "/search");
    expect(screen.getByRole("link", { name: /my lists/i })).toHaveAttribute("href", "/lists");
    expect(screen.getByRole("link", { name: /follows/i })).toHaveAttribute("href", "/follows");
    expect(screen.getByRole("link", { name: /reading history/i })).toHaveAttribute("href", "/history");
  });

  it("Admin Panel link points to /admin", () => {
    mockAuth.user = ADMIN_USER;
    renderSidebar(true);

    expect(screen.getByRole("link", { name: /admin panel/i })).toHaveAttribute("href", "/admin");
  });

  // ── Version ───────────────────────────────────────────────────────────────

  it("always renders the version string", () => {
    renderSidebar(false);

    expect(screen.getByText("v1.0.0")).toBeInTheDocument();
  });
});
