"use client";

import { useEffect, useState } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { userApi, handleResponse } from "@/lib/api";
import type { User, UserUpdateRequest } from "@/lib/types";

export default function UserSection() {
  const { user: currentUser } = useAuth();

  const [items, setItems] = useState<User[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [editingId, setEditingId] = useState<string | null>(null);
  const [editForm, setEditForm] = useState<UserUpdateRequest>({});
  const [editError, setEditError] = useState<string | null>(null);
  const [editLoading, setEditLoading] = useState(false);

  const totalPages = Math.ceil(total / 20);

  useEffect(() => {
    async function fetch() {
      setLoading(true);
      setError(null);
      const result = await handleResponse(userApi.list(page));
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

  function startEdit(item: User) {
    setEditingId(item.id);
    setEditForm({ username: item.username });
    setEditError(null);
  }

  async function handleSave(id: string) {
    /* v8 ignore next */
    if (!editForm.username?.trim()) return;
    setEditLoading(true);
    setEditError(null);
    const result = await handleResponse(userApi.update(id, editForm));
    if (result.success) {
      setItems((prev) => prev.map((it) => (it.id === id ? result.data : it)));
      setEditingId(null);
    } else {
      setEditError(result.error);
    }
    setEditLoading(false);
  }

  async function handleDelete(id: string) {
    if (!confirm("Delete this user? This cannot be undone.")) return;
    const result = await handleResponse(userApi.delete(id));
    if (result.success) {
      setItems((prev) => prev.filter((it) => it.id !== id));
      setTotal((t) => t - 1);
    }
  }

  return (
    <section>
      <div className="flex items-center justify-between mb-3">
        <h2 className="text-lg font-semibold text-md-text-primary">Users ({total})</h2>
        <Pagination page={page} totalPages={totalPages} onChange={setPage} />
      </div>

      {error && <p className="text-sm text-red-400 mb-3">{error}</p>}

      <div className="rounded-lg border border-md-border overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-md-surface text-md-text-secondary">
            <tr>
              <th className="text-left px-4 py-2 font-medium">Username</th>
              <th className="text-left px-4 py-2 font-medium hidden sm:table-cell">Email</th>
              <th className="text-left px-4 py-2 font-medium hidden md:table-cell">Joined</th>
              <th className="px-4 py-2" />
            </tr>
          </thead>
          <tbody className="divide-y divide-md-border">
            {loading && (
              <tr><td colSpan={4} className="px-4 py-4 text-center text-md-text-secondary text-sm">Loading…</td></tr>
            )}
            {!loading && items.length === 0 && (
              <tr><td colSpan={4} className="px-4 py-4 text-center text-md-text-secondary text-sm">No users found.</td></tr>
            )}
            {items.map((item) =>
              editingId === item.id ? (
                <tr key={item.id} className="bg-md-surface/50">
                  <td className="px-4 py-2">
                    <input
                      value={editForm.username || ""}
                      onChange={(e) => setEditForm((f) => ({ ...f, username: e.target.value }))}
                      className="w-full bg-md-background border border-md-border text-md-text-primary text-sm rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-md-accent"
                    />
                  </td>
                  <td className="px-4 py-2 text-md-text-secondary hidden sm:table-cell">{item.email}</td>
                  <td className="px-4 py-2 text-md-text-secondary hidden md:table-cell">
                    {new Date(item.createdAt).toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" })}
                  </td>
                  <td className="px-4 py-2 text-right space-x-2">
                    {editError && <span className="text-xs text-red-400 mr-2">{editError}</span>}
                    <button
                      onClick={() => handleSave(item.id)}
                      disabled={editLoading || !editForm.username?.trim()}
                      className="px-3 py-1 rounded text-xs font-medium bg-md-accent text-white hover:bg-md-accent/90 disabled:opacity-50 transition-colors"
                    >
                      Save
                    </button>
                    <button onClick={() => setEditingId(null)} className="px-3 py-1 rounded text-xs font-medium text-md-text-secondary border border-md-border hover:bg-md-surface-hover transition-colors">Cancel</button>
                  </td>
                </tr>
              ) : (
                <tr key={item.id} className="hover:bg-md-surface/50 transition-colors">
                  <td className="px-4 py-2 text-md-text-primary font-medium">{item.username}</td>
                  <td className="px-4 py-2 text-md-text-secondary hidden sm:table-cell">{item.email}</td>
                  <td className="px-4 py-2 text-md-text-secondary hidden md:table-cell">
                    {new Date(item.createdAt).toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" })}
                  </td>
                  <td className="px-4 py-2 text-right space-x-2">
                    <button onClick={() => startEdit(item)} className="px-3 py-1 rounded text-xs font-medium text-md-text-secondary border border-md-border hover:bg-md-surface-hover transition-colors">Edit</button>
                    {item.id !== currentUser?.id && (
                      <button onClick={() => handleDelete(item.id)} className="px-3 py-1 rounded text-xs font-medium text-red-400 border border-red-400/30 hover:bg-red-400/10 transition-colors">Delete</button>
                    )}
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
