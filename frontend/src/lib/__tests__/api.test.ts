import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { AxiosError } from "axios";
import MockAdapter from "axios-mock-adapter";
import api, { handleResponse } from "../api";

// ── Helpers ─────────────────────────────────────────────────────────────────

/** Clears localStorage between tests */
function clearStorage() {
  localStorage.clear();
  const keys = Object.keys(localStorage);
  for (const k of keys) localStorage.removeItem(k);
}

// ── api instance config ─────────────────────────────────────────────────────

describe("api instance", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    clearStorage();
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("defaults baseURL to /api when NEXT_PUBLIC_API_URL is unset", () => {
    expect(api.defaults.baseURL).toBe("/api");
  });

  it("has a 15 second timeout", () => {
    expect(api.defaults.timeout).toBe(15_000);
  });

  it("sends Content-Type and Accept JSON headers on every request", async () => {
    // Read the actual headers the client sends through MockAdapter rather than
    // inspecting defaults (Axios internally enriches them at send time).
    mock.onGet("/check-headers").reply((config) => {
      expect(config.headers).toHaveProperty("Content-Type", "application/json");
      // Axios adds extra Accept values at the adapter level, so we check
      // that at least application/json is present.
      expect(config.headers?.Accept).toMatch(/application\/json/);
      return [200, {}];
    });

    await api.get("/check-headers");
  });
});

// ── Request interceptor (auth token) ────────────────────────────────────────

describe("request interceptor — auth token", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    clearStorage();
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("attaches a Bearer token when auth_token exists in localStorage", async () => {
    localStorage.setItem("auth_token", "test-jwt");

    mock.onGet("/me").reply((config) => {
      expect(config.headers?.Authorization).toBe("Bearer test-jwt");
      return [200, { id: 1 }];
    });

    await api.get("/me");
  });

  it("does NOT attach an Authorization header when no token is stored", async () => {
    mock.onGet("/public").reply((config) => {
      expect(config.headers?.Authorization).toBeUndefined();
      return [200, { ok: true }];
    });

    await api.get("/public");
  });

  it("works after the token is removed from localStorage", async () => {
    localStorage.setItem("auth_token", "temp-token");
    localStorage.removeItem("auth_token");

    mock.onGet("/public").reply((config) => {
      expect(config.headers?.Authorization).toBeUndefined();
      return [200, { ok: true }];
    });

    await api.get("/public");
  });

  it("uses the latest token value on each request", async () => {
    localStorage.setItem("auth_token", "token-a");

    mock.onGet("/first").reply((config) => {
      expect(config.headers?.Authorization).toBe("Bearer token-a");
      return [200, {}];
    });

    await api.get("/first");

    localStorage.setItem("auth_token", "token-b");

    mock.onGet("/second").reply((config) => {
      expect(config.headers?.Authorization).toBe("Bearer token-b");
      return [200, {}];
    });

    await api.get("/second");
  });

  it("handles errors thrown by preceding request interceptors (line 45)", async () => {
    // Add a request interceptor before the auth one that throws.
    // When its error propagates, the auth interceptor's error handler
    // (line 45) catches and re-throws it.
    const ejectId = api.interceptors.request.use(
      () => { throw new Error("Preceding interceptor failure"); },
      undefined,
    );

    await expect(api.get("/test")).rejects.toThrow(
      "Preceding interceptor failure",
    );

    api.interceptors.request.eject(ejectId);
  });

  it("skips localStorage access on the server (typeof window === undefined, line 37)", async () => {
    // Simulate a server-side rendering environment where `window` is
    // unavailable, so the auth interceptor skips the localStorage read.
    vi.stubGlobal("window", undefined);

    mock.onGet("/ssr").reply((config) => {
      expect(config.headers?.Authorization).toBeUndefined();
      return [200, {}];
    });

    await api.get("/ssr");

    vi.unstubAllGlobals();
  });
});

// ── Response interceptor (error normalisation) ──────────────────────────────

describe("response interceptor — error normalisation", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    clearStorage();
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("passes through a successful response unchanged", async () => {
    mock.onGet("/ok").reply(200, { hello: "world" });

    const res = await api.get("/ok");

    expect(res.status).toBe(200);
    expect(res.data).toEqual({ hello: "world" });
  });

  it("rejects with the server's `detail` message on 4xx", async () => {
    mock.onGet("/error").reply(422, { detail: "Validation failed" });

    await expect(api.get("/error")).rejects.toThrow("Validation failed");
  });

  it("rejects with the server's `message` when `detail` is absent", async () => {
    mock.onGet("/error").reply(403, { message: "Forbidden" });

    await expect(api.get("/error")).rejects.toThrow("Forbidden");
  });

  it("falls back to the generic message when the response has no detail, message, or statusText", async () => {
    // axios-mock-adapter doesn't populate response.statusText, so the
    // interceptor falls through to the default message.
    mock.onGet("/error").reply(500, {});

    await expect(api.get("/error")).rejects.toThrow(
      "An unexpected error occurred",
    );
  });

  it("prefers `detail` over `message` when both are present", async () => {
    mock.onGet("/error").reply(400, {
      detail: "Detailed reason",
      message: "Generic message",
    });

    await expect(api.get("/error")).rejects.toThrow("Detailed reason");
  });

  it("rejects with a network error when the request cannot reach the server", async () => {
    // Note: axios-mock-adapter's networkError() produces an error that
    // doesn't carry `error.request`, so the interceptor's `request` check
    // isn't exercised here.  In a real browser the `request` property IS
    // set and the interceptor returns "Network error. Please check your
    // connection." instead.
    mock.onGet("/offline").networkError();

    await expect(api.get("/offline")).rejects.toThrow("Network Error");
  });

  it("returns a friendly message when error.request exists but error.response does not (line 67)", async () => {
    // Simulate a real browser network error where the AxiosError has a
    // `request` object but no `response` — this exercises the second
    // branch of the response interceptor.
    mock.onGet("/timeout").reply(() =>
      Promise.reject(
        new AxiosError("Network Error", AxiosError.ERR_NETWORK, undefined, {
          /* simulate XMLHttpRequest */ fake: true,
        }),
      ),
    );

    await expect(api.get("/timeout")).rejects.toThrow(
      "Network error. Please check your connection.",
    );
  });
});

// ── handleResponse utility ──────────────────────────────────────────────────

describe("handleResponse", () => {
  it("returns success: true with data when the promise resolves", async () => {
    const promise = Promise.resolve({ data: "hello" });

    const result = await handleResponse(promise);

    expect(result).toEqual({ success: true, data: "hello" });
  });

  it("returns success: false with error message when the promise rejects with an Error", async () => {
    const promise = Promise.reject(new Error("Something broke"));

    const result = await handleResponse(promise);

    expect(result).toEqual({ success: false, error: "Something broke" });
  });

  it("returns success: false with fallback when rejected with a non-Error value", async () => {
    const promise = Promise.reject("just a string");

    const result = await handleResponse(promise);

    expect(result).toEqual({
      success: false,
      error: "An unexpected error occurred",
    });
  });

  it("returns success: false with fallback when rejected with null", async () => {
    const promise = Promise.reject(null);

    const result = await handleResponse(promise);

    expect(result).toEqual({
      success: false,
      error: "An unexpected error occurred",
    });
  });

  it("preserves complex data shapes through resolve", async () => {
    const payload = { items: [1, 2, 3], total: 3 };
    const promise = Promise.resolve({ data: payload });

    const result = await handleResponse(promise);

    expect(result).toEqual({ success: true, data: payload });
  });

  it("works with axios response shapes via handleResponse(api.get(...))", async () => {
    const axiosShape = { data: { id: 42, name: "Test" }, status: 200 };
    const promise = Promise.resolve(axiosShape);

    const result = await handleResponse(promise);

    expect(result).toEqual({
      success: true,
      data: { id: 42, name: "Test" },
    });
  });
});

// ── Full integration: api + handleResponse ──────────────────────────────────

describe("integration — api.get + handleResponse", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    clearStorage();
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("returns success result for a 200 response", async () => {
    mock.onGet("/manga").reply(200, [{ id: 1, title: "Berserk" }]);

    const result = await handleResponse(api.get("/manga"));

    expect(result).toEqual({
      success: true,
      data: [{ id: 1, title: "Berserk" }],
    });
  });

  it("returns error result for a 4xx response", async () => {
    mock.onGet("/manga/999").reply(404, { detail: "Manga not found" });

    const result = await handleResponse(api.get("/manga/999"));

    expect(result).toEqual({
      success: false,
      error: "Manga not found",
    });
  });

  it("returns error result for a network failure", async () => {
    // See note above about axios-mock-adapter vs real browser behaviour.
    mock.onGet("/manga").networkError();

    const result = await handleResponse(api.get("/manga"));

    expect(result).toEqual({
      success: false,
      error: "Network Error",
    });
  });
});
