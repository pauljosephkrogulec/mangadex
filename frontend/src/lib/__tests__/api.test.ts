import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { AxiosError } from "axios";
import MockAdapter from "axios-mock-adapter";
import api, { handleResponse, commentApi, userApi, adminApi, registerLogoutCallback } from "../api";

// ── api instance config ─────────────────────────────────────────────────────

describe("api instance", () => {
  let mock: MockAdapter;

  beforeEach(() => {
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

  it("sends Content-Type and Accept JSON-LD headers on every request", async () => {
    // Read the actual headers the client sends through MockAdapter rather than
    // inspecting defaults (Axios internally enriches them at send time).
    mock.onGet("/check-headers").reply((config) => {
      expect(config.headers).toHaveProperty("Content-Type", "application/ld+json");
      // Axios adds extra Accept values at the adapter level, so we check
      // that at least application/ld+json is present.
      expect(config.headers?.Accept).toMatch(/application\/ld\+json/);
      return [200, {}];
    });

    await api.get("/check-headers");
  });
});

// ── Request: no auth header (cookie-based auth) ─────────────────────────────

describe("request — cookie-based auth (no Authorization header)", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("does NOT attach an Authorization header (JWT is sent via httpOnly cookie)", async () => {
    mock.onGet("/public").reply((config) => {
      expect(config.headers?.Authorization).toBeUndefined();
      return [200, { ok: true }];
    });

    await api.get("/public");
  });

  it("sends withCredentials true so cookies are included in cross-origin requests", () => {
    expect(api.defaults.withCredentials).toBe(true);
  });
});

// ── Response interceptor (error normalisation) ──────────────────────────────

describe("response interceptor — error normalisation", () => {
  let mock: MockAdapter;

  beforeEach(() => {
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

  it("calls the logout callback on 401 Expired JWT and still rejects", async () => {
    const logoutFn = vi.fn();
    registerLogoutCallback(logoutFn);

    mock.onGet("/protected").reply(401, { message: "Expired JWT Token" });

    await expect(api.get("/protected")).rejects.toThrow("Expired JWT Token");
    expect(logoutFn).toHaveBeenCalledOnce();
  });

  it("does not call the logout callback on non-expired 401 errors", async () => {
    const logoutFn = vi.fn();
    registerLogoutCallback(logoutFn);

    mock.onGet("/protected").reply(401, { message: "JWT Token not found" });

    await expect(api.get("/protected")).rejects.toThrow("JWT Token not found");
    expect(logoutFn).not.toHaveBeenCalled();
  });

  it("does not call the logout callback on other 4xx errors", async () => {
    const logoutFn = vi.fn();
    registerLogoutCallback(logoutFn);

    mock.onGet("/protected").reply(403, { message: "Forbidden" });

    await expect(api.get("/protected")).rejects.toThrow("Forbidden");
    expect(logoutFn).not.toHaveBeenCalled();
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

// ── API endpoint modules ────────────────────────────────────────────────────

describe("mangaApi", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("list sends GET /mangas with params", async () => {
    mock.onGet("/mangas").reply((config) => {
      expect(config.params).toEqual({ page: 1, itemsPerPage: 20 });
      return [200, { member: [], totalItems: 0 }];
    });

    const { mangaApi } = await import("../api");
    await mangaApi.list({ page: 1, itemsPerPage: 20 });
  });

  it("get sends GET /mangas/:id", async () => {
    mock.onGet("/mangas/abc-123").reply(200, { id: "abc-123", title: "Test" });

    const { mangaApi } = await import("../api");
    const res = await mangaApi.get("abc-123");

    expect(res.data.title).toBe("Test");
  });

  it("create sends POST /mangas", async () => {
    mock.onPost("/mangas").reply((config) => {
      const body = JSON.parse(config.data);
      expect(body.title).toBe("New Manga");
      return [201, { id: "1", title: "New Manga" }];
    });

    const { mangaApi } = await import("../api");
    await mangaApi.create({ title: "New Manga" } as never);
  });

  it("update sends PUT /mangas/:id", async () => {
    mock.onPut("/mangas/abc").reply((config) => {
      const body = JSON.parse(config.data);
      expect(body.title).toBe("Updated");
      return [200, { id: "abc", title: "Updated" }];
    });

    const { mangaApi } = await import("../api");
    await mangaApi.update("abc", { title: "Updated" });
  });

  it("delete sends DELETE /mangas/:id", async () => {
    mock.onDelete("/mangas/abc").reply(204);

    const { mangaApi } = await import("../api");
    await mangaApi.delete("abc");
  });
});

describe("chapterApi", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("list sends GET /chapters", async () => {
    mock.onGet("/chapters").reply(200, { member: [], totalItems: 0 });

    const { chapterApi } = await import("../api");
    const res = await chapterApi.list();

    expect(res.data.totalItems).toBe(0);
  });

  it("get sends GET /chapters/:id", async () => {
    mock.onGet("/chapters/ch-1").reply(200, { id: "ch-1" });

    const { chapterApi } = await import("../api");
    await chapterApi.get("ch-1");
  });

  it("pageUrl returns the correct URL string", async () => {
    const { chapterApi } = await import("../api");
    const url = chapterApi.pageUrl("ch-1", 3);
    expect(url).toBe("/api/chapters/ch-1/pages/3");
  });
});

describe("coverArtApi", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("list sends GET /cover_arts", async () => {
    mock.onGet("/cover_arts").reply(200, { member: [] });

    const { coverArtApi } = await import("../api");
    await coverArtApi.list();
  });

  it("get sends GET /cover_arts/:id", async () => {
    mock.onGet("/cover_arts/ca-1").reply(200, { id: "ca-1" });

    const { coverArtApi } = await import("../api");
    await coverArtApi.get("ca-1");
  });
});

describe("tagApi", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("list sends GET /tags", async () => {
    mock.onGet("/tags").reply((config) => {
      expect(config.params).toEqual({ order: { name: "asc" } });
      return [200, { member: [], totalItems: 0 }];
    });

    const { tagApi } = await import("../api");
    await tagApi.list({ order: { name: "asc" } });
  });
});

describe("userApi", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("get sends GET /users/:id", async () => {
    mock.onGet("/users/u-1").reply(200, { id: "u-1" });

    const { userApi } = await import("../api");
    await userApi.get("u-1");
  });

  it("follows sends GET /users/:id/follows", async () => {
    mock.onGet("/users/u-1/follows").reply(200, { member: [] });

    const { userApi } = await import("../api");
    await userApi.follows("u-1");
  });
});

describe("authApi", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("login sends POST /login_check", async () => {
    mock.onPost("/login_check").reply((config) => {
      const body = JSON.parse(config.data);
      expect(body.username).toBe("user");
      expect(body.password).toBe("pass");
      return [200, { token: "jwt-token" }];
    });

    const { authApi } = await import("../api");
    await authApi.login({ username: "user", password: "pass" });
  });

  it("register sends POST /users", async () => {
    mock.onPost("/users").reply((config) => {
      const body = JSON.parse(config.data);
      expect(body.email).toBe("test@test.com");
      return [201, { id: "1", email: "test@test.com" }];
    });

    const { authApi } = await import("../api");
    await authApi.register({ email: "test@test.com" } as never);
  });

  it("me sends GET /me", async () => {
    mock.onGet("/me").reply(200, { id: "u-1", email: "test@test.com" });

    const { authApi } = await import("../api");
    const res = await authApi.me();

    expect(res.data.id).toBe("u-1");
    expect(res.data.email).toBe("test@test.com");
  });
});

describe("followApi", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("follows sends GET /users/:id/follows", async () => {
    mock.onGet("/users/u-1/follows").reply(200, { member: [] });

    const { followApi } = await import("../api");
    await followApi.follows("u-1");
  });
});

describe("creatorApi", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("list sends GET /creators", async () => {
    mock.onGet("/creators").reply(200, { member: [] });

    const { creatorApi } = await import("../api");
    await creatorApi.list();
  });

  it("get sends GET /creators/:id", async () => {
    mock.onGet("/creators/cr-1").reply(200, { id: "cr-1" });

    const { creatorApi } = await import("../api");
    await creatorApi.get("cr-1");
  });

  it("create sends POST /creators", async () => {
    mock.onPost("/creators").reply(201, { id: "cr-1" });

    const { creatorApi } = await import("../api");
    await creatorApi.create({ name: "Test" } as never);
  });

  it("update sends PUT /creators/:id", async () => {
    mock.onPut("/creators/cr-1").reply(200, { id: "cr-1" });

    const { creatorApi } = await import("../api");
    await creatorApi.update("cr-1", { name: "Updated" });
  });

  it("delete sends DELETE /creators/:id", async () => {
    mock.onDelete("/creators/cr-1").reply(204);

    const { creatorApi } = await import("../api");
    await creatorApi.delete("cr-1");
  });
});

describe("scanlationGroupApi", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("list sends GET /scanlation_groups", async () => {
    mock.onGet("/scanlation_groups").reply(200, { member: [] });

    const { scanlationGroupApi } = await import("../api");
    await scanlationGroupApi.list();
  });

  it("get sends GET /scanlation_groups/:id", async () => {
    mock.onGet("/scanlation_groups/sg-1").reply(200, { id: "sg-1" });

    const { scanlationGroupApi } = await import("../api");
    await scanlationGroupApi.get("sg-1");
  });

  it("create sends POST /scanlation_groups", async () => {
    mock.onPost("/scanlation_groups").reply(201, { id: "sg-1" });

    const { scanlationGroupApi } = await import("../api");
    await scanlationGroupApi.create({ name: "Group" } as never);
  });

  it("update sends PUT /scanlation_groups/:id", async () => {
    mock.onPut("/scanlation_groups/sg-1").reply(200, { id: "sg-1" });

    const { scanlationGroupApi } = await import("../api");
    await scanlationGroupApi.update("sg-1", { name: "Updated" });
  });

  it("delete sends DELETE /scanlation_groups/:id", async () => {
    mock.onDelete("/scanlation_groups/sg-1").reply(204);

    const { scanlationGroupApi } = await import("../api");
    await scanlationGroupApi.delete("sg-1");
  });
});

describe("customListApi", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("list sends GET /users/:userId/custom_lists", async () => {
    mock.onGet("/users/u-1/custom_lists").reply(200, { member: [], totalItems: 0 });

    const { customListApi } = await import("../api");
    const res = await customListApi.list("u-1");

    expect(res.data.totalItems).toBe(0);
  });

  it("list passes query params", async () => {
    mock.onGet("/users/u-1/custom_lists").reply((config) => {
      expect(config.params).toEqual({ page: 2, itemsPerPage: 10 });
      return [200, { member: [], totalItems: 0 }];
    });

    const { customListApi } = await import("../api");
    await customListApi.list("u-1", { page: 2, itemsPerPage: 10 });
  });

  it("get sends GET /custom_lists/:id", async () => {
    mock.onGet("/custom_lists/cl-1").reply(200, { id: "cl-1" });

    const { customListApi } = await import("../api");
    await customListApi.get("cl-1");
  });

  it("create sends POST /custom_lists", async () => {
    mock.onPost("/custom_lists").reply(201, { id: "cl-1" });

    const { customListApi } = await import("../api");
    await customListApi.create({ name: "My List" } as never);
  });

  it("update sends PUT /custom_lists/:id", async () => {
    mock.onPut("/custom_lists/cl-1").reply(200, { id: "cl-1" });

    const { customListApi } = await import("../api");
    await customListApi.update("cl-1", { name: "Updated" });
  });

  it("delete sends DELETE /custom_lists/:id", async () => {
    mock.onDelete("/custom_lists/cl-1").reply(204);

    const { customListApi } = await import("../api");
    await customListApi.delete("cl-1");
  });

  it("addManga sends POST /custom_lists/:id/mangas/:mangaId", async () => {
    mock.onPost("/custom_lists/cl-1/mangas/m-1").reply(200);

    const { customListApi } = await import("../api");
    await customListApi.addManga("cl-1", "m-1");
  });

  it("removeManga sends DELETE /custom_lists/:id/mangas/:mangaId", async () => {
    mock.onDelete("/custom_lists/cl-1/mangas/m-1").reply(204);

    const { customListApi } = await import("../api");
    await customListApi.removeManga("cl-1", "m-1");
  });
});

describe("mangaApi additional endpoints", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("feed sends GET /mangas/:id/feed", async () => {
    mock.onGet("/mangas/m-1/feed").reply(200, { member: [] });

    const { mangaApi } = await import("../api");
    await mangaApi.feed("m-1");
  });

  it("follow sends POST /mangas/:id/follow", async () => {
    mock.onPost("/mangas/m-1/follow").reply(200);

    const { mangaApi } = await import("../api");
    await mangaApi.follow("m-1");
  });

  it("unfollow sends DELETE /mangas/:id/follow", async () => {
    mock.onDelete("/mangas/m-1/follow").reply(204);

    const { mangaApi } = await import("../api");
    await mangaApi.unfollow("m-1");
  });

  it("followStatus sends GET /mangas/:id/follow", async () => {
    mock.onGet("/mangas/m-1/follow").reply(200, { following: true });

    const { mangaApi } = await import("../api");
    const res = await mangaApi.followStatus("m-1");
    expect(res.data.following).toBe(true);
  });
});

describe("chapterApi additional endpoints", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("create sends POST /chapters", async () => {
    mock.onPost("/chapters").reply(201, { id: "ch-1" });

    const { chapterApi } = await import("../api");
    await chapterApi.create({ title: "Ch 1" } as never);
  });

  it("update sends PUT /chapters/:id", async () => {
    mock.onPut("/chapters/ch-1").reply(200, { id: "ch-1" });

    const { chapterApi } = await import("../api");
    await chapterApi.update("ch-1", { title: "Updated" });
  });

  it("delete sends DELETE /chapters/:id", async () => {
    mock.onDelete("/chapters/ch-1").reply(204);

    const { chapterApi } = await import("../api");
    await chapterApi.delete("ch-1");
  });

  it("uploadPages sends POST /chapters/:id/upload-pages", async () => {
    let capturedData: unknown = null;
    mock.onPost("/chapters/ch-1/upload-pages").reply((config) => {
      capturedData = config.data;
      return [200, { chapterId: "ch-1", pages: ["p1.jpg"], pageCount: 1 }];
    });

    const { chapterApi } = await import("../api");
    const file = new File(["content"], "page1.jpg", { type: "image/jpeg" });
    const res = await chapterApi.uploadPages("ch-1", [file]);

    expect(res.data.chapterId).toBe("ch-1");
    expect(res.data.pages).toEqual(["p1.jpg"]);
    // Verify the request was sent (mock adapter serializes FormData,
    // so we check it captured something)
    expect(capturedData).not.toBeNull();
    expect(res.data.pageCount).toBe(1);
  });
});

describe("tagApi additional endpoints", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("get sends GET /tags/:id", async () => {
    mock.onGet("/tags/t-1").reply(200, { id: "t-1" });

    const { tagApi } = await import("../api");
    await tagApi.get("t-1");
  });

  it("create sends POST /tags", async () => {
    mock.onPost("/tags").reply(201, { id: "t-1" });

    const { tagApi } = await import("../api");
    await tagApi.create({ name: "Action" } as never);
  });

  it("update sends PUT /tags/:id", async () => {
    mock.onPut("/tags/t-1").reply(200, { id: "t-1" });

    const { tagApi } = await import("../api");
    await tagApi.update("t-1", { name: "Updated" });
  });

  it("delete sends DELETE /tags/:id", async () => {
    mock.onDelete("/tags/t-1").reply(204);

    const { tagApi } = await import("../api");
    await tagApi.delete("t-1");
  });
});

describe("userApi additional endpoints", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("update sends PUT /users/:id", async () => {
    mock.onPut("/users/u-1").reply(200, { id: "u-1" });

    const { userApi } = await import("../api");
    await userApi.update("u-1", { displayName: "NewName" });
  });

  it("delete sends DELETE /users/:id", async () => {
    mock.onDelete("/users/u-1").reply(204);

    const { userApi } = await import("../api");
    await userApi.delete("u-1");
  });
});

describe("coverArtApi additional endpoints", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("create sends POST /cover_arts", async () => {
    mock.onPost("/cover_arts").reply(201, { id: "ca-1" });

    const { coverArtApi } = await import("../api");
    await coverArtApi.create({ imagePath: "/cover.jpg" } as never);
  });

  it("update sends PUT /cover_arts/:id", async () => {
    mock.onPut("/cover_arts/ca-1").reply(200, { id: "ca-1" });

    const { coverArtApi } = await import("../api");
    await coverArtApi.update("ca-1", { volume: "2" });
  });

  it("delete sends DELETE /cover_arts/:id", async () => {
    mock.onDelete("/cover_arts/ca-1").reply(204);

    const { coverArtApi } = await import("../api");
    await coverArtApi.delete("ca-1");
  });

  it("upload sends POST /covers/upload with FormData (with optional params)", async () => {
    mock.onPost("/covers/upload").reply(200, {
      id: "ca-1",
      imagePath: "/covers/test.jpg",
      volume: "1",
      isPrimary: true,
      manga: { id: "m-1", title: "Test" },
    });

    const { coverArtApi } = await import("../api");
    const file = new File(["fake-image"], "cover.jpg", { type: "image/jpeg" });
    const res = await coverArtApi.upload("m-1", file, "1", true);

    expect(res.data.id).toBe("ca-1");
    expect(res.data.imagePath).toBe("/covers/test.jpg");
    expect(res.data.volume).toBe("1");
    expect(res.data.isPrimary).toBe(true);
  });

  it("upload sends POST /covers/upload without optional volume and isPrimary", async () => {
    mock.onPost("/covers/upload").reply(200, {
      id: "ca-2",
      imagePath: "/covers/test2.jpg",
      volume: null,
      isPrimary: false,
      manga: { id: "m-2", title: "Test 2" },
    });

    const { coverArtApi } = await import("../api");
    const file = new File(["fake-image"], "cover.jpg", { type: "image/jpeg" });
    const res = await coverArtApi.upload("m-2", file);

    expect(res.data.id).toBe("ca-2");
    expect(res.data.imagePath).toBe("/covers/test2.jpg");
    expect(res.data.volume).toBeNull();
  });
});

// ── Isolated env var test (LAST—resets module cache) ────────────────────────

describe("NEXT_PUBLIC_API_URL env var", () => {
  afterEach(() => {
    vi.unstubAllEnvs();
  });

  it("defaults to /api when env is not set", async () => {
    // The default import (at top of file) already proves this,
    // but we verify via a fresh dynamic import too.
    vi.resetModules();
    const { default: freshApi } = await import("../api");
    expect(freshApi.defaults.baseURL).toBe("/api");
  });

  it("uses custom URL when NEXT_PUBLIC_API_URL is set", async () => {
    vi.stubEnv("NEXT_PUBLIC_API_URL", "https://custom-api.example.com");
    vi.resetModules();
    const { default: freshApi } = await import("../api");
    expect(freshApi.defaults.baseURL).toBe("https://custom-api.example.com");
  });
});

// ── commentApi ───────────────────────────────────────────────────────────────

describe("commentApi", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("list sends GET /mangas/:mangaId/comments", async () => {
    const payload = { member: [], totalItems: 0 };
    mock.onGet("/mangas/m-1/comments").reply(200, payload);
    const res = await commentApi.list("m-1");
    expect(res.data).toEqual(payload);
  });

  it("list forwards query params", async () => {
    mock.onGet("/mangas/m-1/comments", { params: { page: 2 } }).reply(200, { member: [], totalItems: 0 });
    const res = await commentApi.list("m-1", { page: 2 });
    expect(res.status).toBe(200);
  });

  it("create sends POST /comments", async () => {
    const body = { content: "Great manga!", manga: "/api/mangas/m-1" };
    const response = { id: "c-1", content: "Great manga!", createdAt: "2026-01-01T00:00:00Z", user: { id: "u-1", username: "alice" } };
    mock.onPost("/comments", body).reply(201, response);
    const res = await commentApi.create(body);
    expect(res.data).toEqual(response);
  });

  it("delete sends DELETE /comments/:id", async () => {
    mock.onDelete("/comments/c-1").reply(204);
    const res = await commentApi.delete("c-1");
    expect(res.status).toBe(204);
  });
});

// ── userApi.list ─────────────────────────────────────────────────────────────

describe("userApi.list", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("sends GET /users with page 1 by default", async () => {
    const payload = { member: [], totalItems: 0 };
    mock.onGet("/users").reply(200, payload);

    const res = await userApi.list();
    expect(res.data).toEqual(payload);
  });

  it("sends GET /users with a custom page", async () => {
    const payload = { member: [], totalItems: 0 };
    mock.onGet("/users").reply(200, payload);

    const res = await userApi.list(3);
    expect(res.data).toEqual(payload);
  });
});

// ── adminApi.stats ────────────────────────────────────────────────────────────

describe("adminApi.stats", () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(api);
  });

  afterEach(() => {
    mock.restore();
  });

  it("fetches manga, user, and chapter counts in parallel and returns totals", async () => {
    mock.onGet("/mangas").reply(200, { totalItems: 42, member: [] });
    mock.onGet("/users").reply(200, { totalItems: 7, member: [] });
    mock.onGet("/chapters").reply(200, { totalItems: 100, member: [] });

    const stats = await adminApi.stats();

    expect(stats.mangaCount).toBe(42);
    expect(stats.userCount).toBe(7);
    expect(stats.chapterCount).toBe(100);
  });
});
