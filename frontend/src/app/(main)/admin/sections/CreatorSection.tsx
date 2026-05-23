"use client";

import { useEffect, useState } from "react";
import { creatorApi, handleResponse } from "@/lib/api";
import type { Creator, CreatorWrite } from "@/lib/types";

const EMPTY_FORM: CreatorWrite = { name: "", type: "author" };

export default function CreatorSection() {
  const [items, setItems] = useState<Creator[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [showCreate, setShowCreate] = useState(false);
  const [createForm, setCreateForm] = useState<CreatorWrite>(EMPTY_FORM);
  const [createError, setCreateError] = useState<string | null>(null);
  const [createLoading, setCreateLoading] = useState(false);

  const [editingId, setEditingId] = useState<string | null>(null);
  const [editForm, setEditForm] = useState<CreatorWrite>(EMPTY_FORM);
  const [editError, setEditError] = useState<string | null>(null);
  const [editLoading, setEditLoading] = useState(false);

  const totalPages = Math.ceil(total / 20);

  useEffect(() => {
    async function fetch() {
      setLoading(true);
      setError(null);
      const result = await handleResponse(creatorApi.list({ page }));
      if (result.success) {
        setItems(result.data.member);
        setTotal(result.data.totalItems);
      } else {
        setError(result.error);
      }
      setLoading(false);
    }
    fetch();
  }, [page]);

  async function handleCreate(e: React.FormEvent) {
    e.preventDefault();
    if (!createForm.name.trim()) return;
    setCreateLoading(true);
    setCreateError(null);
    const result = await handleResponse(creatorApi.create(createForm));
    if (result.success) {
      setItems((prev) => [result.data, ...prev]);
      setTotal((t) => t + 1);
      setCreateForm(EMPTY_FORM);
      setShowCreate(false);
    } else {
      setCreateError(result.error);
    }
    setCreateLoading(false);
  }

  function startEdit(item: Creator) {
    setEditingId(item.id);
    setEditForm({ name: item.name, type: item.type });
    setEditError(null);
  }

  async function handleSave(id: string) {
    /* v8 ignore next */
    if (!editForm.name.trim()) return;
    setEditLoading(true);
    setEditError(null);
    const result = await handleResponse(creatorApi.update(id, editForm));
    if (result.success) {
      setItems((prev) => prev.map((it) => (it.id === id ? result.data : it)));
      setEditingId(null);
    } else {
      setEditError(result.error);
    }
    setEditLoading(false);
  }

  async function handleDelete(id: string) {
    if (!confirm("Delete this creator? This cannot be undone.")) return;
    const result = await handleResponse(creatorApi.delete(id));
    if (result.success) {
      setItems((prev) => prev.filter((it) => it.id !== id));
      setTotal((t) => t - 1);
    }
  }

  return (
    <section>
      <div className="flex items-center justify-between mb-3">
        <h2 className="text-lg font-semibold text-md-text-primary">Creators ({total})</h2>
        <div className="flex items-center gap-2">
          <Pagination page={page} totalPages={totalPages} onChange={setPage} />
          <button
            onClick={() => { setShowCreate((v) => !v); setCreateError(null); setCreateForm(EMPTY_FORM); }}
            className="px-3 py-1.5 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 transition-colors"
          >
            {showCreate ? "Cancel" : "+ New Creator"}
          </button>
        </div>
      </div>

      {showCreate && (
        <form onSubmit={handleCreate} className="mb-4 p-4 rounded-lg border border-md-border bg-md-surface space-y-3">
          <h3 className="text-sm font-semibold text-md-text-primary">New Creator</h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <input
              value={createForm.name}
              onChange={(e) => setCreateForm((f) => ({ ...f, name: e.target.value }))}
              placeholder="Name"
              className="w-full bg-md-background border border-md-border text-md-text-primary text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-md-accent placeholder:text-md-text-secondary"
            />
            <select
              value={createForm.type}
              onChange={(e) => setCreateForm((f) => ({ ...f, type: e.target.value as "author" | "artist" }))}
              className="w-full bg-md-background border border-md-border text-md-text-primary text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-md-accent"
            >
              <option value="author">Author</option>
              <option value="artist">Artist</option>
            </select>
          </div>
          {createError && <p className="text-xs text-red-400">{createError}</p>}
          <button
            type="submit"
            disabled={createLoading || !createForm.name.trim()}
            className="px-4 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 disabled:opacity-50 transition-colors"
          >
            {createLoading ? "Creating…" : "Create"}
          </button>
        </form>
      )}

      {error && <p className="text-sm text-red-400 mb-3">{error}</p>}

      <div className="rounded-lg border border-md-border overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-md-surface text-md-text-secondary">
            <tr>
              <th className="text-left px-4 py-2 font-medium">Name</th>
              <th className="text-left px-4 py-2 font-medium">Type</th>
              <th className="px-4 py-2" />
            </tr>
          </thead>
          <tbody className="divide-y divide-md-border">
            {loading && (
              <tr><td colSpan={3} className="px-4 py-4 text-center text-md-text-secondary text-sm">Loading…</td></tr>
            )}
            {!loading && items.length === 0 && (
              <tr><td colSpan={3} className="px-4 py-4 text-center text-md-text-secondary text-sm">No creators yet.</td></tr>
            )}
            {items.map((item) =>
              editingId === item.id ? (
                <tr key={item.id} className="bg-md-surface/50">
                  <td className="px-4 py-2">
                    <input
                      value={editForm.name}
                      onChange={(e) => setEditForm((f) => ({ ...f, name: e.target.value }))}
                      className="w-full bg-md-background border border-md-border text-md-text-primary text-sm rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-md-accent"
                    />
                  </td>
                  <td className="px-4 py-2">
                    <select
                      value={editForm.type}
                      onChange={(e) => setEditForm((f) => ({ ...f, type: e.target.value as "author" | "artist" }))}
                      className="bg-md-background border border-md-border text-md-text-primary text-sm rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-md-accent"
                    >
                      <option value="author">Author</option>
                      <option value="artist">Artist</option>
                    </select>
                  </td>
                  <td className="px-4 py-2 text-right space-x-2">
                    {editError && <span className="text-xs text-red-400 mr-2">{editError}</span>}
                    <button
                      onClick={() => handleSave(item.id)}
                      disabled={editLoading || !editForm.name.trim()}
                      className="px-3 py-1 rounded text-xs font-medium bg-md-accent text-white hover:bg-md-accent/90 disabled:opacity-50 transition-colors"
                    >
                      Save
                    </button>
                    <button
                      onClick={() => setEditingId(null)}
                      className="px-3 py-1 rounded text-xs font-medium text-md-text-secondary border border-md-border hover:bg-md-surface-hover transition-colors"
                    >
                      Cancel
                    </button>
                  </td>
                </tr>
              ) : (
                <tr key={item.id} className="hover:bg-md-surface/50 transition-colors">
                  <td className="px-4 py-2 text-md-text-primary">{item.name}</td>
                  <td className="px-4 py-2 text-md-text-secondary capitalize">{item.type}</td>
                  <td className="px-4 py-2 text-right space-x-2">
                    <button
                      onClick={() => startEdit(item)}
                      className="px-3 py-1 rounded text-xs font-medium text-md-text-secondary border border-md-border hover:bg-md-surface-hover transition-colors"
                    >
                      Edit
                    </button>
                    <button
                      onClick={() => handleDelete(item.id)}
                      className="px-3 py-1 rounded text-xs font-medium text-red-400 border border-red-400/30 hover:bg-red-400/10 transition-colors"
                    >
                      Delete
                    </button>
                  </td>
                </tr>
              )
            )}
          </tbody>
        </table>
      </div>
    </section>
  );
}

function Pagination({ page, totalPages, onChange }: { page: number; totalPages: number; onChange: (p: number) => void }) {
  if (totalPages <= 1) return null;
  return (
    <div className="flex items-center gap-2 text-sm">
      <button onClick={() => onChange(page - 1)} disabled={page <= 1} className="px-2 py-1 rounded border border-md-border text-md-text-secondary hover:bg-md-surface-hover disabled:opacity-40 disabled:cursor-not-allowed transition-colors">←</button>
      <span className="text-md-text-secondary">{page} / {totalPages}</span>
      <button onClick={() => onChange(page + 1)} disabled={page >= totalPages} className="px-2 py-1 rounded border border-md-border text-md-text-secondary hover:bg-md-surface-hover disabled:opacity-40 disabled:cursor-not-allowed transition-colors">→</button>
    </div>
  );
}
