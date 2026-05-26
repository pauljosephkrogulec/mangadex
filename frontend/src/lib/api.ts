import axios, { AxiosError, type AxiosInstance, type CreateAxiosDefaults } from "axios";
import type {
  Chapter,
  ChapterWrite,
  Comment,
  CommentWrite,
  CoverArt,
  CoverArtWrite,
  Creator,
  CreatorWrite,
  CustomList,
  CustomListWrite,
  HydraCollection,
  LoginRequest,
  LoginResponse,
  Manga,
  MangaFollow,
  MangaWrite,
  RatingResponse,
  ScanlationGroup,
  ScanlationGroupWrite,
  Tag,
  TagWrite,
  User,
  UserRegistrationRequest,
  UserUpdateRequest,
} from "@/lib/types";

/* v8 ignore next */
const BASE_URL = process.env.NEXT_PUBLIC_API_URL || "/api";

const config: CreateAxiosDefaults = {
  baseURL: BASE_URL,
  timeout: 15_000,
  headers: {
    "Content-Type": "application/ld+json",
    Accept: "application/ld+json",
  },
  withCredentials: true,
};

let logoutCallback: (() => void) | null = null;

export function registerLogoutCallback(fn: () => void): void {
  logoutCallback = fn;
}

const api: AxiosInstance = axios.create(config);

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ detail?: string; message?: string }>) => {
    if (error.response) {
      const message =
        error.response.data?.detail ||
        error.response.data?.message ||
        error.response.statusText ||
        "An unexpected error occurred";

      if (error.response.status === 401 && message.includes("Expired JWT")) {
        logoutCallback?.();
      }

      return Promise.reject(new Error(message));
    }

    if (error.request) {
      return Promise.reject(new Error("Network error. Please check your connection."));
    }

    return Promise.reject(error);
  },
);

export type ApiResult<T> =
  | { success: true; data: T }
  | { success: false; error: string };

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

export const authApi = {
  login: (data: LoginRequest) =>
    api.post<LoginResponse>("/login_check", data),

  register: (data: UserRegistrationRequest) =>
    api.post<User>("/users", data),

  me: () =>
    api.get<User>("/me"),
};

export const mangaApi = {
  list: (params?: Record<string, string | number>) =>
    api.get<HydraCollection<Manga>>("/mangas", { params }),

  get: (id: string, params?: Record<string, string | number>) =>
    api.get<Manga>(`/mangas/${id}`, { params }),

  create: (data: MangaWrite) =>
    api.post<Manga>("/mangas", data),

  update: (id: string, data: Partial<MangaWrite>) =>
    api.put<Manga>(`/mangas/${id}`, data),

  delete: (id: string) =>
    api.delete(`/mangas/${id}`),

  feed: (id: string, params?: Record<string, string | number>) =>
    api.get<HydraCollection<Chapter>>(`/mangas/${id}/feed`, { params }),

  follow: (id: string) =>
    api.post(`/mangas/${id}/follow`),

  unfollow: (id: string) =>
    api.delete(`/mangas/${id}/follow`),

  followStatus: (id: string) =>
    api.get<{ following: boolean }>(`/mangas/${id}/follow`),

  getRating: (id: string) =>
    api.get<RatingResponse>(`/mangas/${id}/rating`),

  rate: (id: string, score: number) =>
    api.post<RatingResponse>(`/mangas/${id}/rate`, { score }),
};

export const chapterApi = {
  list: (params?: Record<string, string | number>) =>
    api.get<HydraCollection<Chapter>>("/chapters", { params }),

  get: (id: string) =>
    api.get<Chapter>(`/chapters/${id}`),

  create: (data: ChapterWrite) =>
    api.post<Chapter>("/chapters", data),

  update: (id: string, data: Partial<ChapterWrite>) =>
    api.put<Chapter>(`/chapters/${id}`, data),

  delete: (id: string) =>
    api.delete(`/chapters/${id}`),

  pageUrl: (chapterId: string, pageNum: number) =>
    `/api/chapters/${chapterId}/pages/${pageNum}`,

  uploadPages: (id: string, files: File[]) => {
    const formData = new FormData();
    files.forEach((f) => formData.append("pages[]", f));
    return api.post<{ chapterId: string; pages: string[]; pageCount: number }>(
      `/chapters/${id}/upload-pages`,
      formData,
    );
  },
};

export const coverArtApi = {
  list: (params?: Record<string, string | number>) =>
    api.get<HydraCollection<CoverArt>>("/cover_arts", { params }),

  get: (id: string) =>
    api.get<CoverArt>(`/cover_arts/${id}`),

  create: (data: CoverArtWrite) =>
    api.post<CoverArt>("/cover_arts", data),

  update: (id: string, data: Partial<CoverArtWrite>) =>
    api.put<CoverArt>(`/cover_arts/${id}`, data),

  delete: (id: string) =>
    api.delete(`/cover_arts/${id}`),

  upload: (mangaId: string, file: File, volume?: string, isPrimary?: boolean) => {
    const formData = new FormData();
    formData.append("cover", file);
    formData.append("mangaId", mangaId);
    if (volume !== undefined) formData.append("volume", volume);
    if (isPrimary !== undefined) formData.append("isPrimary", String(isPrimary));
    return api.post<{
      id: string;
      imagePath: string;
      volume: string | null;
      isPrimary: boolean;
      manga: { id: string; title: string };
      // Omit Content-Type — axios auto-sets it with boundary for FormData
    }>("/covers/upload", formData);
  },
};

export const creatorApi = {
  list: (params?: Record<string, string | number>) =>
    api.get<HydraCollection<Creator>>("/creators", { params }),

  get: (id: string) =>
    api.get<Creator>(`/creators/${id}`),

  create: (data: CreatorWrite) =>
    api.post<Creator>("/creators", data),

  update: (id: string, data: Partial<CreatorWrite>) =>
    api.put<Creator>(`/creators/${id}`, data),

  delete: (id: string) =>
    api.delete(`/creators/${id}`),
};

export const tagApi = {
  list: (params?: Record<string, string | number>) =>
    api.get<HydraCollection<Tag>>("/tags", { params }),

  get: (id: string) =>
    api.get<Tag>(`/tags/${id}`),

  create: (data: TagWrite) =>
    api.post<Tag>("/tags", data),

  update: (id: string, data: Partial<TagWrite>) =>
    api.put<Tag>(`/tags/${id}`, data),

  delete: (id: string) =>
    api.delete(`/tags/${id}`),
};

export const userApi = {
  list: (page = 1) =>
    api.get<HydraCollection<User>>("/users", { params: { page } }),

  get: (id: string) =>
    api.get<User>(`/users/${id}`),

  update: (id: string, data: UserUpdateRequest) =>
    api.put<User>(`/users/${id}`, data),

  delete: (id: string) =>
    api.delete(`/users/${id}`),

  follows: (id: string) =>
    api.get<HydraCollection<MangaFollow>>(`/users/${id}/follows`),
};

export const adminApi = {
  stats: async () => {
    const [mangas, users, chapters] = await Promise.all([
      api.get<HydraCollection<Manga>>("/mangas", { params: { page: 1, itemsPerPage: 1 } }),
      api.get<HydraCollection<User>>("/users", { params: { page: 1, itemsPerPage: 1 } }),
      api.get<HydraCollection<Chapter>>("/chapters", { params: { page: 1, itemsPerPage: 1 } }),
    ]);
    return {
      mangaCount: mangas.data.totalItems,
      userCount: users.data.totalItems,
      chapterCount: chapters.data.totalItems,
    };
  },
};

export const scanlationGroupApi = {
  list: (params?: Record<string, string | number>) =>
    api.get<HydraCollection<ScanlationGroup>>("/scanlation_groups", { params }),

  get: (id: string) =>
    api.get<ScanlationGroup>(`/scanlation_groups/${id}`),

  create: (data: ScanlationGroupWrite) =>
    api.post<ScanlationGroup>("/scanlation_groups", data),

  update: (id: string, data: Partial<ScanlationGroupWrite>) =>
    api.put<ScanlationGroup>(`/scanlation_groups/${id}`, data),

  delete: (id: string) =>
    api.delete(`/scanlation_groups/${id}`),
};

export const customListApi = {
  list: (userId: string, params?: Record<string, string | number>) =>
    api.get<HydraCollection<CustomList>>(`/users/${userId}/custom_lists`, { params }),

  get: (id: string) =>
    api.get<CustomList>(`/custom_lists/${id}`),

  create: (data: CustomListWrite) =>
    api.post<CustomList>("/custom_lists", data),

  update: (id: string, data: Partial<CustomListWrite>) =>
    api.put<CustomList>(`/custom_lists/${id}`, data),

  delete: (id: string) =>
    api.delete(`/custom_lists/${id}`),

  addManga: (listId: string, mangaId: string) =>
    api.post(`/custom_lists/${listId}/mangas/${mangaId}`),

  removeManga: (listId: string, mangaId: string) =>
    api.delete(`/custom_lists/${listId}/mangas/${mangaId}`),
};

export const commentApi = {
  list: (mangaId: string, params?: Record<string, string | number>) =>
    api.get<HydraCollection<Comment>>(`/mangas/${mangaId}/comments`, { params }),

  create: (data: CommentWrite) =>
    api.post<Comment>("/comments", data),

  delete: (id: string) =>
    api.delete(`/comments/${id}`),
};

export const followApi = {
  follows: (userId: string) =>
    api.get<HydraCollection<MangaFollow>>(`/users/${userId}/follows`),
};

export default api;
