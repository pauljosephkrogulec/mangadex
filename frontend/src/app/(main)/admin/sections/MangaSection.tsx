"use client";

import { useEffect, useState } from "react";
import { mangaApi, handleResponse } from "@/lib/api";
import type { Manga, MangaWrite, MangaStatus, ContentRating, Demographic } from "@/lib/types";

const STATUSES: MangaStatus[] = ["ongoing", "completed", "hiatus", "cancelled"];
const RATINGS: ContentRating[] = ["safe", "suggestive", "erotica", "pornographic"];
const DEMOGRAPHICS: Demographic[] = ["shounen", "shoujo", "josei", "seinen", "none"];

interface MangaFormData {
  title: string;
  status: MangaStatus;
  contentRating: ContentRating;
  demographic: Demographic;
  year: string;
  description: string;
}

const EMPTY_FORM: MangaFormData = {
  title: "",
  status: "ongoing",
  contentRating: "safe",
  demographic: "none",
  year: "",
  description: "",
};

function toWrite(form: MangaFormData): MangaWrite {
  return {
    title: form.title,
    status: form.status,
    contentRating: form.contentRating,
    demographic: form.demographic,
    ...(form.year ? { year: parseInt(form.year, 10) } : {}),
    ...(form.description.trim() ? { description: form.description.trim() } : {}),
  };
}

function fromManga(manga: Manga): MangaFormData {
  return {
    title: manga.title,
    status: manga.status,
    contentRating: manga.contentRating,
    demographic: manga.demographic,
    year: manga.year != null ? String(manga.year) : "",
    description: manga.description ?? "",
  };
}

export default function MangaSection() {
  const [items, setItems] = useState<Manga[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [showCreate, setShowCreate] = useState(false);
  const [createForm, setCreateForm] = useState<MangaFormData>(EMPTY_FORM);
  const [createError, setCreateError] = useState<string | null>(null);
  const [createLoading, setCreateLoading] = useState(false);

  const [editingId, setEditingId] = useState<string | null>(null);
  const [editForm, setEditForm] = useState<MangaFormData>(EMPTY_FORM);
  const [editError, setEditError] = useState<string | null>(null);
  const [editLoading, setEditLoading] = useState(false);

  const totalPages = Math.ceil(total / 20);

  useEffect(() => {
    async function fetch() {
      setLoading(true);
      setError(null);
      const result = await handleResponse(mangaApi.list({ page }));
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
    if (!createForm.title.trim()) return;
    setCreateLoading(true);
    setCreateError(null);
    const result = await handleResponse(mangaApi.create(toWrite(createForm)));
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

  function startEdit(item: Manga) {
    setEditingId(item.id);
    setEditForm(fromManga(item));
    setEditError(null);
  }

  async function handleSave(id: string) {
    /* v8 ignore next */
    if (!editForm.title.trim()) return;
    setEditLoading(true);
    setEditError(null);
    const result = await handleResponse(mangaApi.update(id, toWrite(editForm)));
    if (result.success) {
      setItems((prev) => prev.map((it) => (it.id === id ? result.data : it)));
      setEditingId(null);
    } else {
      setEditError(result.error);
    }
    setEditLoading(false);
  }

  async function handleDelete(id: string) {
    if (!confirm("Delete this manga? This cannot be undone.")) return;
    const result = await handleResponse(mangaApi.delete(id));
    if (result.success) {
      setItems((prev) => prev.filter((it) => it.id !== id));
      setTotal((t) => t - 1);
    }
  }

  return (
    <section>
      <div className="flex items-center justify-between mb-3">
        <h2 className="text-lg font-semibold text-md-text-primary">Manga ({total})</h2>
        <div className="flex items-center gap-2">
          <Pagination page={page} totalPages={totalPages} onChange={setPage} />
          <button
            onClick={() => { setShowCreate((v) => !v); setCreateError(null); setCreateForm(EMPTY_FORM); }}
            className="px-3 py-1.5 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 transition-colors"
          >
            {showCreate ? "Cancel" : "+ New Manga"}
          </button>
        </div>
      </div>

      {showCreate && (
        <form onSubmit={handleCreate} className="mb-4 p-4 rounded-lg border border-md-border bg-md-surface space-y-3">
          <h3 className="text-sm font-semibold text-md-text-primary">New Manga</h3>
          <MangaFormFields form={createForm} onChange={setCreateForm} />
          {createError && <p className="text-xs text-red-400">{createError}</p>}
          <button
            type="submit"
            disabled={createLoading || !createForm.title.trim()}
            className="px-4 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 disabled:opacity-50 transition-colors"
          >
            {createLoading ? "Creating…" : "Create"}
          </button>
        </form>
      )}

      {editingId && (
        <div className="mb-4 p-4 rounded-lg border border-md-border bg-md-surface space-y-3">
          <h3 className="text-sm font-semibold text-md-text-primary">Edit Manga</h3>
          <MangaFormFields form={editForm} onChange={setEditForm} />
          {editError && <p className="text-xs text-red-400">{editError}</p>}
          <div className="flex gap-2">
            <button
              onClick={() => handleSave(editingId)}
              disabled={editLoading || !editForm.title.trim()}
              className="px-4 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 disabled:opacity-50 transition-colors"
            >
              {editLoading ? "Saving…" : "Save"}
            </button>
            <button
              onClick={() => setEditingId(null)}
              className="px-4 py-2 rounded-lg border border-md-border text-md-text-secondary text-sm font-medium hover:bg-md-surface-hover transition-colors"
            >
              Cancel
            </button>
          </div>
        </div>
      )}

      {error && <p className="text-sm text-red-400 mb-3">{error}</p>}

      <div className="rounded-lg border border-md-border overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-md-surface text-md-text-secondary">
            <tr>
              <th className="text-left px-4 py-2 font-medium">Title</th>
              <th className="text-left px-4 py-2 font-medium hidden sm:table-cell">Status</th>
              <th className="text-left px-4 py-2 font-medium hidden md:table-cell">Rating</th>
              <th className="text-left px-4 py-2 font-medium hidden md:table-cell">Year</th>
              <th className="px-4 py-2" />
            </tr>
          </thead>
          <tbody className="divide-y divide-md-border">
            {loading && (
              <tr><td colSpan={5} className="px-4 py-4 text-center text-md-text-secondary text-sm">Loading…</td></tr>
            )}
            {!loading && items.length === 0 && (
              <tr><td colSpan={5} className="px-4 py-4 text-center text-md-text-secondary text-sm">No manga yet.</td></tr>
            )}
            {items.map((item) => (
              <tr key={item.id} className={`hover:bg-md-surface/50 transition-colors ${editingId === item.id ? "bg-md-accent/5" : ""}`}>
                <td className="px-4 py-2 text-md-text-primary font-medium truncate max-w-[200px]">{item.title}</td>
                <td className="px-4 py-2 text-md-text-secondary hidden sm:table-cell capitalize">{item.status}</td>
                <td className="px-4 py-2 text-md-text-secondary hidden md:table-cell capitalize">{item.contentRating}</td>
                <td className="px-4 py-2 text-md-text-secondary hidden md:table-cell">{item.year ?? "—"}</td>
                <td className="px-4 py-2 text-right space-x-2">
                  <button
                    onClick={() => editingId === item.id ? setEditingId(null) : startEdit(item)}
                    className="px-3 py-1 rounded text-xs font-medium text-md-text-secondary border border-md-border hover:bg-md-surface-hover transition-colors"
                  >
                    {editingId === item.id ? "Cancel" : "Edit"}
                  </button>
                  <button
                    onClick={() => handleDelete(item.id)}
                    className="px-3 py-1 rounded text-xs font-medium text-red-400 border border-red-400/30 hover:bg-red-400/10 transition-colors"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}

function MangaFormFields({ form, onChange }: { form: MangaFormData; onChange: React.Dispatch<React.SetStateAction<MangaFormData>> }) {
  const inputCls = "w-full bg-md-background border border-md-border text-md-text-primary text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-md-accent placeholder:text-md-text-secondary";
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <input
        value={form.title}
        onChange={(e) => onChange((f) => ({ ...f, title: e.target.value }))}
        placeholder="Title"
        className={inputCls + " sm:col-span-2"}
      />
      <select value={form.status} onChange={(e) => onChange((f) => ({ ...f, status: e.target.value as MangaStatus }))} className={inputCls}>
        {STATUSES.map((s) => <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>)}
      </select>
      <select value={form.contentRating} onChange={(e) => onChange((f) => ({ ...f, contentRating: e.target.value as ContentRating }))} className={inputCls}>
        {RATINGS.map((r) => <option key={r} value={r}>{r.charAt(0).toUpperCase() + r.slice(1)}</option>)}
      </select>
      <select value={form.demographic} onChange={(e) => onChange((f) => ({ ...f, demographic: e.target.value as Demographic }))} className={inputCls}>
        {DEMOGRAPHICS.map((d) => <option key={d} value={d}>{d.charAt(0).toUpperCase() + d.slice(1)}</option>)}
      </select>
      <input
        type="number"
        value={form.year}
        onChange={(e) => onChange((f) => ({ ...f, year: e.target.value }))}
        placeholder="Year (optional)"
        min={1900}
        max={2100}
        className={inputCls}
      />
      <textarea
        value={form.description}
        onChange={(e) => onChange((f) => ({ ...f, description: e.target.value }))}
        placeholder="Description (optional)"
        rows={3}
        className={inputCls + " sm:col-span-2 resize-none"}
      />
    </div>
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
