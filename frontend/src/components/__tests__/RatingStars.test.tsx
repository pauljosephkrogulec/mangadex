import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import MockAdapter from "axios-mock-adapter";
import api from "@/lib/api";
import RatingStars from "../RatingStars";
import type { RatingResponse } from "@/lib/types";

// ── Mock AuthContext ─────────────────────────────────────────────────────────

const mockAuthUser = vi.hoisted(() => ({
  current: null as null | { id: string; email: string; username: string },
}));

vi.mock("@/contexts/AuthContext", () => ({
  useAuth: () => ({ user: mockAuthUser.current, loading: false }),
}));

// ── Fixtures ─────────────────────────────────────────────────────────────────

const TEST_USER = { id: "u-1", email: "test@example.com", username: "tester" };

function makeRating(overrides: Partial<RatingResponse> = {}): RatingResponse {
  return {
    averageRating: 7.5,
    ratingCount: 4,
    userRating: null,
    ...overrides,
  };
}

// ── Tests ─────────────────────────────────────────────────────────────────────

describe("RatingStars", () => {
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
    mock.onGet("/mangas/m-1/rating").reply(() => new Promise(() => {}));
    render(<RatingStars mangaId="m-1" />);

    const pulses = document.querySelectorAll(".animate-pulse");
    expect(pulses.length).toBeGreaterThan(0);
  });

  it("renders average rating and count after fetch", async () => {
    mock.onGet("/mangas/m-1/rating").reply(200, makeRating());
    render(<RatingStars mangaId="m-1" />);

    await waitFor(() => {
      expect(screen.getByText("7.5")).toBeInTheDocument();
    });
    expect(screen.getByText("(4 ratings)")).toBeInTheDocument();
  });

  it("shows 'No ratings yet' when count is 0", async () => {
    mock.onGet("/mangas/m-1/rating").reply(200, makeRating({ averageRating: null, ratingCount: 0 }));
    render(<RatingStars mangaId="m-1" />);

    await waitFor(() => {
      expect(screen.getByText("No ratings yet")).toBeInTheDocument();
    });
  });

  it("shows login prompt for unauthenticated users", async () => {
    mockAuthUser.current = null;
    mock.onGet("/mangas/m-1/rating").reply(200, makeRating());
    render(<RatingStars mangaId="m-1" />);

    await waitFor(() => {
      expect(screen.getByText("Log in to rate this manga")).toBeInTheDocument();
    });
  });

  it("submits rating on star click", async () => {
    const user = userEvent.setup();
    mock.onGet("/mangas/m-1/rating").reply(200, makeRating({ userRating: null }));
    mock.onPost("/mangas/m-1/rate").reply(201, makeRating({ userRating: 8, averageRating: 7.6, ratingCount: 5 }));

    render(<RatingStars mangaId="m-1" />);
    await waitFor(() => expect(screen.getByLabelText("Rate 8 out of 10")).toBeInTheDocument());

    await user.click(screen.getByLabelText("Rate 8 out of 10"));

    await waitFor(() => {
      expect(screen.getByText("7.6")).toBeInTheDocument();
    });
    expect(screen.getByText("(5 ratings)")).toBeInTheDocument();
  });

  it("stars are disabled for unauthenticated users", async () => {
    mockAuthUser.current = null;
    mock.onGet("/mangas/m-1/rating").reply(200, makeRating());
    render(<RatingStars mangaId="m-1" />);

    await waitFor(() => expect(screen.getByLabelText("Rate 1 out of 10")).toBeInTheDocument());

    const star = screen.getByLabelText("Rate 1 out of 10");
    expect(star).toBeDisabled();
  });

  it("shows singular 'rating' when count is 1", async () => {
    mock.onGet("/mangas/m-1/rating").reply(200, makeRating({ ratingCount: 1, averageRating: 9.0 }));
    render(<RatingStars mangaId="m-1" />);

    await waitFor(() => {
      expect(screen.getByText("(1 rating)")).toBeInTheDocument();
    });
  });

  it("shows error message when rating submission fails", async () => {
    const user = userEvent.setup();
    mock.onGet("/mangas/m-1/rating").reply(200, makeRating());
    mock.onPost("/mangas/m-1/rate").reply(500);

    render(<RatingStars mangaId="m-1" />);
    await waitFor(() => expect(screen.getByLabelText("Rate 3 out of 10")).toBeInTheDocument());

    await user.click(screen.getByLabelText("Rate 3 out of 10"));

    await waitFor(() => {
      expect(screen.getByText(/unexpected error/i)).toBeInTheDocument();
    });
  });

  it("clears hover state when mouse leaves the star container", async () => {
    const user = userEvent.setup();
    mock.onGet("/mangas/m-1/rating").reply(200, makeRating({ userRating: 3 }));

    render(<RatingStars mangaId="m-1" />);
    await waitFor(() => expect(screen.getByLabelText("Rate 7 out of 10")).toBeInTheDocument());

    const star7 = screen.getByLabelText("Rate 7 out of 10");
    await user.hover(star7);
    await user.unhover(star7);

    // After unhovering the container the userRating (3) should be active again,
    // so star 3 should be filled (accent) and star 7 should not be
    expect(star7.className).toContain("text-md-text-secondary");
  });
});
