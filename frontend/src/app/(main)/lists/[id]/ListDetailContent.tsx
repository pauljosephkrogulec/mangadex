"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { useAuth } from "@/contexts/AuthContext";
import { customListApi, handleResponse } from "@/lib/api";
import type { CustomList, CustomListManga, ListVisibility } from "@/lib/types";

const VISIBILITY_STYLES: Record<ListVisibility, string> = {
  public: "bg-green-600/20 text-green-400 border-green-600/30",
  private: "bg-yellow-600/20 text-yellow-400 border-yellow-600/30",
  hidden: "bg-md-surface text-md-text-secondary border-md-border",
};

const STATUS_STYLES: Record<string, string> = {
  ongoing: "bg-green-600/20 text-green-400 border-green-600/30",
  completed: "bg-blue-600/20 text-blue-400 border-blue-600/30",
  hiatus: "bg-yellow-600/20 text-yellow-400 border-yellow-600/30",
  cancelled: "bg-red-600/20 text-red-400 border-red-600/30",
};

function MangaRow({
  manga,
  onRemove,
  removing,
}: {
  manga: CustomListManga;
  onRemove: () => void;
  removing: boolean;
}) {
  return (
    <li className="flex items-center gap-3 px-4 py-3 bg-md-surface border border-md-border rounded-xl">
      <Link href={`/manga/${manga.id}`} className="flex-1 min-w-0 group">
        <p className="font-medium text-md-text-primary group-hover:text-md-accent transition-colors truncate">
          {manga.title}
        </p>
        <p className="text-xs text-md-text-secondary mt-0.5 capitalize">
          {manga.year ? `${manga.year} · ` : ""}
          {manga.demographic !== "none" ? `${manga.demographic} · ` : ""}
          {manga.contentRating !== "safe" ? `${manga.contentRating}` : ""}
        </p>
      </Link>
      <span
        className={`shrink-0 text-xs font-medium px-2 py-0.5 rounded-full capitalize border ${
          STATUS_STYLES[manga.status] ?? "bg-md-surface text-md-text-secondary border-md-border"
        }`}
      >
        {manga.status}
      </span>
      <button
        onClick={onRemove}
        disabled={removing}
        className="shrink-0 p-1.5 rounded-lg text-md-text-secondary hover:bg-md-surface-hover hover:text-red-400 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        aria-label={`Remove ${manga.title} from list`}
      >
        {removing ? (
          <span className="w-4 h-4 border border-current border-t-transparent rounded-full animate-spin block" />
        ) : (
          <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        )}
      </button>
    </li>
  );
}

interface ListDetailContentProps {
  id: string;
}

export default function ListDetailContent({ id }: ListDetailContentProps) {
  const router = useRouter();
  const { user, loading: authLoading } = useAuth();

  const [list, setList] = useState<CustomList | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Edit state
  const [editing, setEditing] = useState(false);
  const [editName, setEditName] = useState("");
  const [editVisibility, setEditVisibility] = useState<ListVisibility>("private");
  const [editLoading, setEditLoading] = useState(false);
  const [editError, setEditError] = useState<string | null>(null);

  // Delete confirm
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [deleteLoading, setDeleteLoading] = useState(false);

  // Removing mangas: set of manga ids being removed
  const [removingIds, setRemovingIds] = useState<Set<string>>(new Set());

  const [refreshKey, setRefreshKey] = useState(0);

  useEffect(() => {
    /* v8 ignore next */
    if (authLoading) return;
    if (!user) {
      router.replace("/login");
      return;
    }

    let cancelled = false;
    async function doFetch() {
      setLoading(true);
      setError(null);
      const result = await handleResponse(customListApi.get(id));
      /* v8 ignore next */
      if (cancelled) return;
      if (result.success) {
        setList(result.data);
      } else {
        setError(result.error);
      }
      setLoading(false);
    }
    void doFetch();
    return () => { cancelled = true; };
  }, [authLoading, user, router, id, refreshKey]);

  useEffect(() => {
    if (list) {
      document.title = `${list.name} - MangaDex`;
    }
  }, [list]);

  const startEdit = () => {
    /* v8 ignore next */
    if (!list) return;
    setEditName(list.name);
    setEditVisibility(list.visibility);
    setEditError(null);
    setEditing(true);
  };

  const handleEdit = async (e: React.FormEvent) => {
    e.preventDefault();
    /* v8 ignore next */
    if (!editName.trim()) return;
    setEditLoading(true);
    setEditError(null);

    const result = await handleResponse(
      customListApi.update(id, { name: editName.trim(), visibility: editVisibility }),
    );

    if (result.success) {
      setList(result.data);
      setEditing(false);
    } else {
      setEditError(result.error);
    }
    setEditLoading(false);
  };

  const handleDelete = async () => {
    setDeleteLoading(true);
    const result = await handleResponse(customListApi.delete(id));
    if (result.success) {
      router.replace("/lists");
    } else {
      setDeleteLoading(false);
    }
  };

  const handleRemoveManga = async (mangaId: string) => {
    setRemovingIds((prev) => new Set(prev).add(mangaId));
    const result = await handleResponse(customListApi.removeManga(id, mangaId));
    if (result.success) {
      /* v8 ignore next */
      setList((prev) =>
        prev ? { ...prev, mangas: prev.mangas?.filter((m) => m.id !== mangaId) } : prev,
      );
    }
    setRemovingIds((prev) => {
      const next = new Set(prev);
      next.delete(mangaId);
      return next;
    });
  };

  if (authLoading || loading) {
    return (
      <div className="max-w-content mx-auto px-6 md:px-8 py-6">
        <div className="space-y-4 animate-pulse">
          <div className="h-8 w-48 bg-md-surface rounded" />
          <div className="h-5 w-24 bg-md-surface rounded-full" />
          <div className="space-y-3 pt-4">
            {Array.from({ length: 5 }).map((_, i) => (
              <div key={i} className="h-16 bg-md-surface rounded-xl" />
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (error && !list) {
    return (
      <div className="max-w-content mx-auto px-6 md:px-8 py-6">
        <div className="flex flex-col items-center justify-center min-h-[40vh] text-center">
          <p className="text-lg text-md-text-secondary mb-4">{error}</p>
          <button
            onClick={() => setRefreshKey(k => k + 1)}
            className="px-4 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 transition-colors"
          >
            Try again
          </button>
        </div>
      </div>
    );
  }

  /* v8 ignore next */
  if (!list) return null;

  const mangas = list.mangas ?? [];

  return (
    <div className="max-w-content mx-auto px-6 md:px-8 py-6">
      {/* Breadcrumb */}
      <nav className="mb-4 text-sm text-md-text-secondary">
        <Link href="/lists" className="hover:text-md-text-primary transition-colors">
          My Lists
        </Link>
        <span className="mx-2">/</span>
        <span className="text-md-text-primary">{list.name}</span>
      </nav>

      {/* Header */}
      {editing ? (
        <form onSubmit={handleEdit} className="mb-6 space-y-3">
          <div className="flex flex-col sm:flex-row gap-2">
            <input
              type="text"
              value={editName}
              onChange={(e) => setEditName(e.target.value)}
              maxLength={255}
              className="flex-1 bg-md-surface border border-md-border text-md-text-primary text-lg font-bold rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-md-accent"
            />
            <select
              value={editVisibility}
              onChange={(e) => setEditVisibility(e.target.value as ListVisibility)}
              className="bg-md-surface border border-md-border text-md-text-primary text-sm rounded-lg px-2 py-1 focus:outline-none focus:ring-1 focus:ring-md-accent"
            >
              <option value="public">Public</option>
              <option value="private">Private</option>
              <option value="hidden">Hidden</option>
            </select>
          </div>
          {editError && <p className="text-xs text-red-400">{editError}</p>}
          <div className="flex gap-2">
            <button
              type="submit"
              disabled={editLoading || !editName.trim()}
              className="px-4 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {editLoading ? "Saving…" : "Save"}
            </button>
            <button
              type="button"
              onClick={() => setEditing(false)}
              className="px-4 py-2 rounded-lg border border-md-border text-md-text-secondary text-sm hover:bg-md-surface-hover transition-colors"
            >
              Cancel
            </button>
          </div>
        </form>
      ) : (
        <div className="flex flex-wrap items-start gap-3 mb-6">
          <div className="flex-1 min-w-0">
            <h1 className="text-2xl font-bold text-md-text-primary break-words">{list.name}</h1>
            <div className="flex items-center gap-2 mt-1.5">
              <span
                className={`text-xs font-medium px-2.5 py-1 rounded-full capitalize border ${VISIBILITY_STYLES[list.visibility]}`}
              >
                {list.visibility}
              </span>
              <span className="text-xs text-md-text-secondary">
                {mangas.length} manga{mangas.length !== 1 ? "s" : ""}
              </span>
            </div>
          </div>
          <div className="flex gap-2 shrink-0">
            <button
              onClick={startEdit}
              className="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-md-border text-md-text-secondary text-sm hover:bg-md-surface-hover hover:text-md-text-primary transition-colors"
            >
              <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              Edit
            </button>
            {showDeleteConfirm ? (
              <div className="flex gap-2 items-center">
                <span className="text-sm text-md-text-secondary">Are you sure?</span>
                <button
                  onClick={handleDelete}
                  disabled={deleteLoading}
                  className="px-3 py-2 rounded-lg bg-red-500/20 text-red-400 border border-red-500/30 text-sm font-medium hover:bg-red-500/30 disabled:opacity-50 transition-colors"
                >
                  {deleteLoading ? "Deleting…" : "Delete"}
                </button>
                <button
                  onClick={() => setShowDeleteConfirm(false)}
                  className="px-3 py-2 rounded-lg border border-md-border text-md-text-secondary text-sm hover:bg-md-surface-hover transition-colors"
                >
                  Cancel
                </button>
              </div>
            ) : (
              <button
                onClick={() => setShowDeleteConfirm(true)}
                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-md-border text-md-text-secondary text-sm hover:bg-md-surface-hover hover:text-red-400 transition-colors"
              >
                <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Delete
              </button>
            )}
          </div>
        </div>
      )}

      <hr className="border-md-border mb-6" />

      {/* Manga list */}
      {mangas.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16 text-center">
          <svg className="w-12 h-12 text-md-text-secondary mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
          <p className="text-md-text-secondary">No manga in this list yet.</p>
          <Link href="/search" className="mt-3 text-sm text-md-accent hover:text-md-accent/80 transition-colors">
            Browse manga to add
          </Link>
        </div>
      ) : (
        <ul className="space-y-2">
          {mangas.map((manga) => (
            <MangaRow
              key={manga.id}
              manga={manga}
              onRemove={() => handleRemoveManga(manga.id)}
              removing={removingIds.has(manga.id)}
            />
          ))}
        </ul>
      )}
    </div>
  );
}
