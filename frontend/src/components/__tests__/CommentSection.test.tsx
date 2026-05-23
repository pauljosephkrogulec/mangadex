import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MockAdapter from "axios-mock-adapter";
import api from "@/lib/api";
import CommentSection from "../CommentSection";
import type { Comment } from "@/lib/types";

// ── Mock AuthContext ─────────────────────────────────────────────────────────

const mockAuthUser = vi.hoisted(() => ({
  current: null as null | { id: string; email: string; username: string },
}));

vi.mock("@/contexts/AuthContext", () => ({
  useAuth: () => ({ user: mockAuthUser.current, loading: false }),
}));

// ── Fixtures ─────────────────────────────────────────────────────────────────

const TEST_USER = { id: "u-1", email: "test@example.com", username: "tester" };

function makeComment(overrides: Partial<Comment> = {}): Comment {
  return {
    "@context": "/api/contexts/Comment",
    "@id": "/api/comments/c-1",
    "@type": "Comment",
    id: "c-1",
    createdAt: new Date().toISOString(),
    content: "Great manga!",
    user: { id: "u-1", username: "tester" },
    ...overrides,
  };
}

function hydra(member: Comment[]) {
  return {
    "@context": "/api/contexts/Comment",
    "@id": "/api/mangas/m-1/comments",
    "@type": "hydra:Collection",
    totalItems: member.length,
    member,
  };
}

// ── Tests ─────────────────────────────────────────────────────────────────────

describe("CommentSection", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
    mockAuthUser.current = TEST_USER;
  });

  afterEach(() => {
    mock.restore();
    mockAuthUser.current = null;
  });

  it("shows skeleton while loading", () => {
    mock.onGet("/mangas/m-1/comments").reply(() => new Promise(() => {}));
    render(<CommentSection mangaId="m-1" />);

    const pulses = document.querySelectorAll(".animate-pulse");
    expect(pulses.length).toBeGreaterThan(0);
  });

  it("renders comments after fetch", async () => {
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([makeComment()]));
    render(<CommentSection mangaId="m-1" />);

    await waitFor(() => {
      expect(screen.getByText("Great manga!")).toBeInTheDocument();
    });
    expect(screen.getByText("tester")).toBeInTheDocument();
  });

  it("shows empty state when no comments", async () => {
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([]));
    render(<CommentSection mangaId="m-1" />);

    await waitFor(() => {
      expect(screen.getByText(/No comments yet/)).toBeInTheDocument();
    });
  });

  it("shows error state and retry button on fetch failure", async () => {
    mock.onGet("/mangas/m-1/comments").reply(500, { detail: "Server error" });
    render(<CommentSection mangaId="m-1" />);

    await waitFor(() => {
      expect(screen.getByText("Server error")).toBeInTheDocument();
    });
    expect(screen.getByRole("button", { name: /try again/i })).toBeInTheDocument();
  });

  it("retry button re-fetches comments", async () => {
    mock.onGet("/mangas/m-1/comments").replyOnce(500, { detail: "Server error" });
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([makeComment()]));

    render(<CommentSection mangaId="m-1" />);

    await waitFor(() => expect(screen.getByText("Server error")).toBeInTheDocument());

    await userEvent.click(screen.getByRole("button", { name: /try again/i }));

    await waitFor(() => expect(screen.getByText("Great manga!")).toBeInTheDocument());
  });

  it("renders textarea and post button for authenticated users", async () => {
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([]));
    render(<CommentSection mangaId="m-1" />);

    await waitFor(() => expect(screen.getByText(/No comments yet/)).toBeInTheDocument());

    expect(screen.getByPlaceholderText(/write a comment/i)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /post/i })).toBeInTheDocument();
  });

  it("does not show post form when unauthenticated", async () => {
    mockAuthUser.current = null;
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([]));
    render(<CommentSection mangaId="m-1" />);

    await waitFor(() => expect(screen.getByText(/No comments yet/)).toBeInTheDocument());

    expect(screen.queryByPlaceholderText(/write a comment/i)).not.toBeInTheDocument();
  });

  it("post button is disabled when textarea is empty", async () => {
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([]));
    render(<CommentSection mangaId="m-1" />);

    await waitFor(() => expect(screen.getByRole("button", { name: /post/i })).toBeInTheDocument());

    expect(screen.getByRole("button", { name: /post/i })).toBeDisabled();
  });

  it("submits a comment and prepends it to the list", async () => {
    const user = userEvent.setup();
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([]));
    mock.onPost("/comments").reply(201, makeComment({ content: "My new comment" }));

    render(<CommentSection mangaId="m-1" />);

    await waitFor(() => expect(screen.getByPlaceholderText(/write a comment/i)).toBeInTheDocument());

    await user.type(screen.getByPlaceholderText(/write a comment/i), "My new comment");
    await user.click(screen.getByRole("button", { name: /post/i }));

    await waitFor(() => {
      expect(screen.getByText("My new comment")).toBeInTheDocument();
    });
  });

  it("clears textarea after successful submit", async () => {
    const user = userEvent.setup();
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([]));
    mock.onPost("/comments").reply(201, makeComment());

    render(<CommentSection mangaId="m-1" />);

    await waitFor(() => expect(screen.getByPlaceholderText(/write a comment/i)).toBeInTheDocument());

    const textarea = screen.getByPlaceholderText(/write a comment/i) as HTMLTextAreaElement;
    await user.type(textarea, "Hello");
    await user.click(screen.getByRole("button", { name: /post/i }));

    await waitFor(() => expect(textarea.value).toBe(""));
  });

  it("shows error message when submit fails", async () => {
    const user = userEvent.setup();
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([]));
    mock.onPost("/comments").reply(400, { detail: "Content too long" });

    render(<CommentSection mangaId="m-1" />);

    await waitFor(() => expect(screen.getByPlaceholderText(/write a comment/i)).toBeInTheDocument());

    await user.type(screen.getByPlaceholderText(/write a comment/i), "Bad content");
    await user.click(screen.getByRole("button", { name: /post/i }));

    await waitFor(() => {
      expect(screen.getByText("Content too long")).toBeInTheDocument();
    });
  });

  it("shows delete button only for own comments", async () => {
    const ownComment = makeComment({ id: "c-1", user: { id: "u-1", username: "tester" } });
    const otherComment = makeComment({ id: "c-2", content: "Someone else", user: { id: "u-2", username: "other" } });
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([ownComment, otherComment]));

    render(<CommentSection mangaId="m-1" />);

    await waitFor(() => expect(screen.getByText("Great manga!")).toBeInTheDocument());

    const deleteButtons = screen.getAllByRole("button", { name: /delete comment/i });
    expect(deleteButtons).toHaveLength(1);
  });

  it("deletes a comment and removes it from the list", async () => {
    const user = userEvent.setup();
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([makeComment()]));
    mock.onDelete("/comments/c-1").reply(204);

    render(<CommentSection mangaId="m-1" />);

    await waitFor(() => expect(screen.getByText("Great manga!")).toBeInTheDocument());

    await user.click(screen.getByRole("button", { name: /delete comment/i }));

    await waitFor(() => {
      expect(screen.queryByText("Great manga!")).not.toBeInTheDocument();
    });
  });

  // ── formatRelativeDate branches ───────────────────────────────────────────

  it("shows minutes ago for a comment posted 30 minutes ago", async () => {
    const ago30m = new Date(Date.now() - 30 * 60_000).toISOString();
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([makeComment({ createdAt: ago30m })]));
    render(<CommentSection mangaId="m-1" />);
    await waitFor(() => expect(screen.getByText("30m ago")).toBeInTheDocument());
  });

  it("shows hours ago for a comment posted 5 hours ago", async () => {
    const ago5h = new Date(Date.now() - 5 * 60 * 60_000).toISOString();
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([makeComment({ createdAt: ago5h })]));
    render(<CommentSection mangaId="m-1" />);
    await waitFor(() => expect(screen.getByText("5h ago")).toBeInTheDocument());
  });

  it("shows days ago for a comment posted 10 days ago", async () => {
    const ago10d = new Date(Date.now() - 10 * 24 * 60 * 60_000).toISOString();
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([makeComment({ createdAt: ago10d })]));
    render(<CommentSection mangaId="m-1" />);
    await waitFor(() => expect(screen.getByText("10d ago")).toBeInTheDocument());
  });

  it("keeps the comment in the list when delete fails", async () => {
    const user = userEvent.setup();
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([makeComment()]));
    mock.onDelete("/comments/c-1").reply(500, { detail: "Server error" });

    render(<CommentSection mangaId="m-1" />);

    await waitFor(() => expect(screen.getByText("Great manga!")).toBeInTheDocument());

    await user.click(screen.getByRole("button", { name: /delete comment/i }));

    await waitFor(() => {
      expect(screen.getByText("Great manga!")).toBeInTheDocument();
    });
  });

  it("shows formatted date for a comment posted more than 30 days ago", async () => {
    const ago60d = new Date(Date.now() - 60 * 24 * 60 * 60_000).toISOString();
    mock.onGet("/mangas/m-1/comments").reply(200, hydra([makeComment({ createdAt: ago60d })]));
    render(<CommentSection mangaId="m-1" />);
    await waitFor(() => {
      const expected = new Intl.DateTimeFormat("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
      }).format(new Date(ago60d));
      expect(screen.getByText(expected)).toBeInTheDocument();
    });
  });
});
