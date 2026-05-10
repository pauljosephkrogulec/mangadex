export interface ApiResource {
  "@context": string;
  "@id": string;
  "@type": string;
}

export interface HydraCollection<T> extends ApiResource {
  totalItems: number;
  member: T[];
}

export interface Creator extends ApiResource {
  id: string;
  createdAt: string;
  name: string;
  type: "author" | "artist";
}

export interface Tag extends ApiResource {
  id: string;
  createdAt: string;
  name: string;
  description: string | null;
  groupName: string;
  isPrimary: boolean;
}

export interface CoverArt extends ApiResource {
  id: string;
  createdAt?: string;
  imagePath: string;
  volume: string | null;
  isPrimary: boolean;
}

export interface ScanlationGroup extends ApiResource {
  id: string;
  createdAt: string;
  name: string;
  website: string | null;
}

export type MangaStatus = "ongoing" | "completed" | "hiatus" | "cancelled";
export type ContentRating = "safe" | "suggestive" | "erotica" | "pornographic";
export type Demographic = "shounen" | "shoujo" | "josei" | "seinen" | "none";

export interface Manga extends ApiResource {
  id: string;
  createdAt: string;
  title: string;
  altTitles?: string[] | null;
  description?: string | null;
  status: MangaStatus;
  year: number | null;
  contentRating: ContentRating;
  demographic: Demographic;
  creators?: Creator[];
  tags?: Tag[];
  chapters?: Chapter[];
  coverArts?: CoverArt[];
}

export interface Chapter extends ApiResource {
  id: string;
  createdAt: string;
  manga: {
    "@id": string;
    "@type": string;
    id: string;
  };
  scanlationGroup: {
    "@id": string;
    "@type": string;
    id: string;
    name: string;
    website: string | null;
  } | null;
  volume: string | null;
  chapterNumber: string;
  title: string | null;
  language: string;
  pageUrls: string[];
}

export interface User extends ApiResource {
  id: string;
  createdAt: string;
  email: string;
  username: string;
}

export type ListVisibility = "public" | "private" | "hidden";

export interface CustomList extends ApiResource {
  id: string;
  createdAt: string;
  name: string;
  visibility: ListVisibility;
  user: string;
}

export interface MangaFollow extends ApiResource {
  id: string;
  manga: string;
  followedAt: string;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginResponse {
  token: string;
}

export interface UserRegistrationRequest {
  email: string;
  username: string;
  password: string;
}

export interface UserUpdateRequest {
  username?: string;
  password?: string;
}

export interface MangaWrite {
  title: string;
  altTitles?: string[];
  description?: string;
  status: MangaStatus;
  year?: number;
  contentRating: ContentRating;
  demographic?: Demographic;
  creators?: string[];
  tags?: string[];
}

export interface ChapterWrite {
  manga: string;
  volume?: string;
  chapterNumber: string;
  title?: string;
  language: string;
  pages: string[];
  scanlationGroup?: string;
}

export interface CoverArtWrite {
  manga: string;
  imagePath: string;
  volume?: string;
  isPrimary?: boolean;
}

export interface CreatorWrite {
  name: string;
  type: "author" | "artist";
}

export interface TagWrite {
  name: string;
  description?: string;
  groupName: string;
  isPrimary?: boolean;
}

export interface ScanlationGroupWrite {
  name: string;
  website?: string;
}

export interface CustomListWrite {
  name: string;
  visibility: ListVisibility;
}
