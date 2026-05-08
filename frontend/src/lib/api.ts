import axios, { AxiosError, type AxiosInstance, type CreateAxiosDefaults } from "axios";

/**
 * Base URL for all API requests.
 * In Docker: set via NEXT_PUBLIC_API_URL env var (nginx reverse proxy → /api)
 * In dev:    falls back to "/api" (expects a proxy rewrite rule)
 */
const BASE_URL = process.env.NEXT_PUBLIC_API_URL || "/api";

const config: CreateAxiosDefaults = {
  baseURL: BASE_URL,
  timeout: 15_000,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
};

/**
 * Pre-configured Axios instance for communicating with the MangaDex backend API.
 *
 * @example
 *   import api from "@/lib/api";
 *
 *   // GET /api/manga
 *   const { data } = await api.get("/manga");
 *
 *   // POST /api/auth/login
 *   await api.post("/auth/login", { email, password });
 */
const api: AxiosInstance = axios.create(config);

// ── Request interceptor ─────────────────────────────────────────────────────
// Attach JWT bearer token from localStorage when running in the browser.
api.interceptors.request.use(
  (config) => {
    if (typeof window !== "undefined") {
      const token = localStorage.getItem("auth_token");
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    }
    return config;
  },
  (error) => Promise.reject(error),
);

// ── Response interceptor ────────────────────────────────────────────────────
// Normalise error messages into plain strings so callers don't have to dig
// through AxiosError shapes.
api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ detail?: string; message?: string }>) => {
    if (error.response) {
      // Server responded with a non-2xx status
      const message =
        error.response.data?.detail ||
        error.response.data?.message ||
        error.response.statusText ||
        "An unexpected error occurred";

      return Promise.reject(new Error(message));
    }

    if (error.request) {
      // Request was sent but no response received
      return Promise.reject(new Error("Network error. Please check your connection."));
    }

    return Promise.reject(error);
  },
);

// ── Typed result helpers ────────────────────────────────────────────────────

/** Discriminated union returned by {@link handleResponse}. */
export type ApiResult<T> =
  | { success: true; data: T }
  | { success: false; error: string };

/**
 * Wraps an Axios promise so callers always get a clean `{ success, data|error }`
 * shape instead of try/catching around AxiosError.
 *
 * @example
 *   const result = await handleResponse(api.get<User[]>("/users"));
 *   if (result.success) {
 *     console.log(result.data); // typed as User[]
 *   } else {
 *     toast.error(result.error);
 *   }
 */
export async function handleResponse<T>(
  promise: Promise<{ data: T }>,
): Promise<ApiResult<T>> {
  try {
    const { data } = await promise;
    return { success: true, data };
  } catch (error) {
    return {
      success: false,
      error: error instanceof Error ? error.message : "An unexpected error occurred",
    };
  }
}

export default api;
