"use client";

import { useEffect, useState, useCallback } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { useAuth } from "@/contexts/AuthContext";
import { customListApi, handleResponse } from "@/lib/api";
import type { CustomList, ListVisibility } from "@/lib/types";

const VISIBILITY_STYLES: Record<ListVisibility, string> = {
  public: "bg-green-600/20 text-green-400 border-green-600/30",
  private: "bg-yellow-600/20 text-yellow-400 border-yellow-600/30",
  hidden: "bg-md-surface text-md-text-secondary border-md-border",
};

function VisibilitySelect({
  value,
  onChange,
}: {
  value: ListVisibility;
  onChange: (v: ListVisibility) => void;
}) {
  return (
    <select
      value={value}
      onChange={(e) => onChange(e.target.value as ListVisibility)}
      className="bg-md-surface border border-md-border text-md-text-primary text-sm rounded-lg px-2 py-1 focus:outline-none focus:ring-1 focus:ring-md-accent"
    >
      <option value="public">Public</option>
      <option value="private">Private</option>
      <option value="hidden">Hidden</option>
    </select>
  );
}

export default function ListsContent() {
  const router = useRouter();
  const { user, loading: authLoading } = useAuth();

  const [lists, setLists] = useState<CustomList[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Create form
  const [showCreate, setShowCreate] = useState(false);
  const [createName, setCreateName] = useState("");
  const [createVisibility, setCreateVisibility] = useState<ListVisibility>("private");
  const [createLoading, setCreateLoading] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);

  // Edit state: listId → { name, visibility }
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editName, setEditName] = useState("");
  const [editVisibility, setEditVisibility] = useState<ListVisibility>("private");
  const [editLoading, setEditLoading] = useState(false);
  const [editError, setEditError] = useState<string | null>(null);

  // Delete confirm
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const fetchLists = useCallback(async (userId: string) => {
    setLoading(true);
    setError(null);
    const result = await handleResponse(customListApi.list(userId));
    if (result.success) {
      setLists(result.data.member);
    } else {
      setError(result.error);
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    /* v8 ignore next */
    if (authLoading) return;
    if (!user) {
      router.replace("/login");
      return;
    }
    fetchLists(user.id);
  }, [authLoading, user, router, fetchLists]);

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    /* v8 ignore next */
    if (!createName.trim()) return;
    setCreateLoading(true);
    setCreateError(null);

    const result = await handleResponse(
      customListApi.create({ name: createName.trim(), visibility: createVisibility }),
    );

    if (result.success) {
      setLists((prev) => [result.data, ...prev]);
      setCreateName("");
      setCreateVisibility("private");
      setShowCreate(false);
    } else {
      setCreateError(result.error);
    }
    setCreateLoading(false);
  };

  const startEdit = (list: CustomList) => {
    setEditingId(list.id);
    setEditName(list.name);
    setEditVisibility(list.visibility);
    setEditError(null);
  };

  const handleEdit = async (e: React.FormEvent, listId: string) => {
    e.preventDefault();
    /* v8 ignore next */
    if (!editName.trim()) return;
    setEditLoading(true);
    setEditError(null);

    const result = await handleResponse(
      customListApi.update(listId, { name: editName.trim(), visibility: editVisibility }),
    );

    if (result.success) {
      setLists((prev) =>
        prev.map((l) => (l.id === listId ? result.data : l)),
      );
      setEditingId(null);
    } else {
      setEditError(result.error);
    }
    setEditLoading(false);
  };

  const handleDelete = async (listId: string) => {
    setDeleteLoading(true);
    const result = await handleResponse(customListApi.delete(listId));
    if (result.success) {
      setLists((prev) => prev.filter((l) => l.id !== listId));
      setDeletingId(null);
    }
    setDeleteLoading(false);
  };

  if (authLoading || (loading && !error)) {
    return (
      <div className="max-w-content mx-auto px-6 md:px-8 py-6">
        <div className="flex items-center justify-between mb-6">
          <div className="h-8 w-32 bg-md-surface rounded animate-pulse" />
          <div className="h-9 w-28 bg-md-surface rounded-lg animate-pulse" />
        </div>
        <div className="space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="h-16 bg-md-surface rounded-lg animate-pulse" />
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-content mx-auto px-6 md:px-8 py-6">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-md-text-primary">My Lists</h1>
        <button
          onClick={() => { setShowCreate((v) => !v); setCreateError(null); }}
          className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 transition-colors"
        >
          <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          New List
        </button>
      </div>

      {/* Create form */}
      {showCreate && (
        <form
          onSubmit={handleCreate}
          className="mb-6 p-4 bg-md-surface border border-md-border rounded-xl space-y-3"
        >
          <h2 className="text-sm font-semibold text-md-text-primary">Create new list</h2>
          <div className="flex flex-col sm:flex-row gap-2">
            <input
              type="text"
              value={createName}
              onChange={(e) => setCreateName(e.target.value)}
              placeholder="List name"
              maxLength={255}
              className="flex-1 bg-md-background border border-md-border text-md-text-primary text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-md-accent placeholder:text-md-text-secondary"
            />
            <VisibilitySelect value={createVisibility} onChange={setCreateVisibility} />
          </div>
          {createError && (
            <p className="text-xs text-red-400">{createError}</p>
          )}
          <div className="flex gap-2">
            <button
              type="submit"
              disabled={createLoading || !createName.trim()}
              className="px-4 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {createLoading ? "Creating…" : "Create"}
            </button>
            <button
              type="button"
              onClick={() => { setShowCreate(false); setCreateError(null); setCreateName(""); }}
              className="px-4 py-2 rounded-lg border border-md-border text-md-text-secondary text-sm hover:bg-md-surface-hover transition-colors"
            >
              Cancel
            </button>
          </div>
        </form>
      )}

      {/* Error */}
      {error && (
        <div className="flex flex-col items-center justify-center py-16 text-center">
          <p className="text-md-text-secondary mb-4">{error}</p>
          <button
            onClick={() => user && fetchLists(user.id)}
            className="px-4 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 transition-colors"
          >
            Try again
          </button>
        </div>
      )}

      {/* Empty state */}
      {!error && !loading && lists.length === 0 && (
        <div className="flex flex-col items-center justify-center py-16 text-center">
          <svg className="w-12 h-12 text-md-text-secondary mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
          </svg>
          <p className="text-md-text-secondary">No lists yet. Create your first one!</p>
        </div>
      )}

      {/* List cards */}
      {!error && lists.length > 0 && (
        <ul className="space-y-3">
          {lists.map((list) => (
            <li key={list.id} className="bg-md-surface border border-md-border rounded-xl overflow-hidden">
              {editingId === list.id ? (
                <form
                  onSubmit={(e) => handleEdit(e, list.id)}
                  className="p-4 space-y-3"
                >
                  <div className="flex flex-col sm:flex-row gap-2">
                    <input
                      type="text"
                      value={editName}
                      onChange={(e) => setEditName(e.target.value)}
                      maxLength={255}
                      className="flex-1 bg-md-background border border-md-border text-md-text-primary text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-md-accent"
                    />
                    <VisibilitySelect value={editVisibility} onChange={setEditVisibility} />
                  </div>
                  {editError && <p className="text-xs text-red-400">{editError}</p>}
                  <div className="flex gap-2">
                    <button
                      type="submit"
                      disabled={editLoading || !editName.trim()}
                      className="px-3 py-1.5 rounded-lg bg-md-accent text-white text-xs font-medium hover:bg-md-accent/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                      {editLoading ? "Saving…" : "Save"}
                    </button>
                    <button
                      type="button"
                      onClick={() => setEditingId(null)}
                      className="px-3 py-1.5 rounded-lg border border-md-border text-md-text-secondary text-xs hover:bg-md-surface-hover transition-colors"
                    >
                      Cancel
                    </button>
                  </div>
                </form>
              ) : deletingId === list.id ? (
                <div className="p-4 flex items-center justify-between gap-3">
                  <p className="text-sm text-md-text-primary">
                    Delete <span className="font-semibold">{list.name}</span>?
                  </p>
                  <div className="flex gap-2 shrink-0">
                    <button
                      onClick={() => handleDelete(list.id)}
                      disabled={deleteLoading}
                      className="px-3 py-1.5 rounded-lg bg-red-500/20 text-red-400 border border-red-500/30 text-xs font-medium hover:bg-red-500/30 disabled:opacity-50 transition-colors"
                    >
                      {deleteLoading ? "Deleting…" : "Delete"}
                    </button>
                    <button
                      onClick={() => setDeletingId(null)}
                      className="px-3 py-1.5 rounded-lg border border-md-border text-md-text-secondary text-xs hover:bg-md-surface-hover transition-colors"
                    >
                      Cancel
                    </button>
                  </div>
                </div>
              ) : (
                <div className="p-4 flex items-center gap-3">
                  <Link
                    href={`/lists/${list.id}`}
                    className="flex-1 min-w-0 group"
                  >
                    <p className="font-medium text-md-text-primary group-hover:text-md-accent transition-colors truncate">
                      {list.name}
                    </p>
                    <p className="text-xs text-md-text-secondary mt-0.5">
                      {list.mangas?.length ?? 0} manga{(list.mangas?.length ?? 0) !== 1 ? "s" : ""}
                    </p>
                  </Link>
                  <span
                    className={`shrink-0 text-xs font-medium px-2.5 py-1 rounded-full capitalize border ${VISIBILITY_STYLES[list.visibility]}`}
                  >
                    {list.visibility}
                  </span>
                  <div className="shrink-0 flex gap-1">
                    <button
                      onClick={() => startEdit(list)}
                      className="p-1.5 rounded-lg text-md-text-secondary hover:bg-md-surface-hover hover:text-md-text-primary transition-colors"
                      aria-label="Edit list"
                    >
                      <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>
                    <button
                      onClick={() => setDeletingId(list.id)}
                      className="p-1.5 rounded-lg text-md-text-secondary hover:bg-md-surface-hover hover:text-red-400 transition-colors"
                      aria-label="Delete list"
                    >
                      <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
