"use client";

import { useEffect, useState } from "react";
import { chapterApi, handleResponse } from "@/lib/api";
import type { Chapter, ChapterWrite } from "@/lib/types";

interface ChapterFormData {
  mangaId: string;
  chapterNumber: string;
  title: string;
  language: string;
  volume: string;
}

const EMPTY_FORM: ChapterFormData = { mangaId: "", chapterNumber: "", title: "", language: "en", volume: "" };

function toWrite(form: ChapterFormData): ChapterWrite {
  return {
    manga: `/api/mangas/${form.mangaId.trim()}`,
    chapterNumber: form.chapterNumber,
    language: form.language,
    ...(form.title.trim() ? { title: form.title.trim() } : {}),
    ...(form.volume.trim() ? { volume: form.volume.trim() } : {}),
    pages: [],
  };
}

function extractMangaId(iri: string | { id: string }): string {
  /* v8 ignore start */
  if (typeof iri === "object") return iri.id;
  const parts = iri.split("/");
  return parts[parts.length - 1];
  /* v8 ignore stop */
}

export default function ChapterSection() {
  const [items, setItems] = useState<Chapter[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [showCreate, setShowCreate] = useState(false);
  const [createForm, setCreateForm] = useState<ChapterFormData>(EMPTY_FORM);
  const [createError, setCreateError] = useState<string | null>(null);
  const [createLoading, setCreateLoading] = useState(false);

  const [editingId, setEditingId] = useState<string | null>(null);
  const [editForm, setEditForm] = useState<ChapterFormData>(EMPTY_FORM);
  const [editError, setEditError] = useState<string | null>(null);
  const [editLoading, setEditLoading] = useState(false);

  const totalPages = Math.ceil(total / 20);

  useEffect(() => {
    async function fetch() {
      setLoading(true);
      setError(null);
      const result = await handleResponse(chapterApi.list({ page }));
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
    if (!createForm.mangaId.trim() || !createForm.chapterNumber.trim()) return;
    setCreateLoading(true);
    setCreateError(null);
    const result = await handleResponse(chapterApi.create(toWrite(createForm)));
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

  function startEdit(item: Chapter) {
    setEditingId(item.id);
    setEditForm({
      mangaId: extractMangaId(item.manga),
      chapterNumber: item.chapterNumber,
      title: item.title ?? "",
      language: item.language,
      volume: item.volume ?? "",
    });
    setEditError(null);
  }

  async function handleSave(id: string) {
    /* v8 ignore next */
    if (!editForm.chapterNumber.trim()) return;
    setEditLoading(true);
    setEditError(null);
    const result = await handleResponse(chapterApi.update(id, {
      chapterNumber: editForm.chapterNumber,
      language: editForm.language,
      ...(editForm.title.trim() ? { title: editForm.title.trim() } : { title: undefined }),
      ...(editForm.volume.trim() ? { volume: editForm.volume.trim() } : { volume: undefined }),
    }));
    if (result.success) {
      setItems((prev) => prev.map((it) => (it.id === id ? result.data : it)));
      setEditingId(null);
    } else {
      setEditError(result.error);
    }
    setEditLoading(false);
  }

  async function handleDelete(id: string) {
    if (!confirm("Delete this chapter? This cannot be undone.")) return;
    const result = await handleResponse(chapterApi.delete(id));
    if (result.success) {
      setItems((prev) => prev.filter((it) => it.id !== id));
      setTotal((t) => t - 1);
    }
  }

  const inputCls = "w-full bg-md-background border border-md-border text-md-text-primary text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-md-accent placeholder:text-md-text-secondary";
  const inlineCls = "w-full bg-md-background border border-md-border text-md-text-primary text-sm rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-md-accent placeholder:text-md-text-secondary";

  return (
    <section>
      <div className="flex items-center justify-between mb-3">
        <h2 className="text-lg font-semibold text-md-text-primary">Chapters ({total})</h2>
        <div className="flex items-center gap-2">
          <Pagination page={page} totalPages={totalPages} onChange={setPage} />
          <button
            onClick={() => { setShowCreate((v) => !v); setCreateError(null); setCreateForm(EMPTY_FORM); }}
            className="px-3 py-1.5 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 transition-colors"
          >
            {showCreate ? "Cancel" : "+ New Chapter"}
          </button>
        </div>
      </div>

      {showCreate && (
        <form onSubmit={handleCreate} className="mb-4 p-4 rounded-lg border border-md-border bg-md-surface space-y-3">
          <h3 className="text-sm font-semibold text-md-text-primary">New Chapter</h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <input value={createForm.mangaId} onChange={(e) => setCreateForm((f) => ({ ...f, mangaId: e.target.value }))} placeholder="Manga UUID" className={inputCls + " sm:col-span-2"} />
            <input value={createForm.chapterNumber} onChange={(e) => setCreateForm((f) => ({ ...f, chapterNumber: e.target.value }))} placeholder="Chapter number (e.g. 1, 1.5)" className={inputCls} />
            <input value={createForm.language} onChange={(e) => setCreateForm((f) => ({ ...f, language: e.target.value }))} placeholder="Language (e.g. en)" className={inputCls} />
            <input value={createForm.title} onChange={(e) => setCreateForm((f) => ({ ...f, title: e.target.value }))} placeholder="Title (optional)" className={inputCls} />
            <input value={createForm.volume} onChange={(e) => setCreateForm((f) => ({ ...f, volume: e.target.value }))} placeholder="Volume (optional)" className={inputCls} />
          </div>
          {createError && <p className="text-xs text-red-400">{createError}</p>}
          <button
            type="submit"
            disabled={createLoading || !createForm.mangaId.trim() || !createForm.chapterNumber.trim()}
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
              <th className="text-left px-4 py-2 font-medium">Chapter</th>
              <th className="text-left px-4 py-2 font-medium hidden sm:table-cell">Title</th>
              <th className="text-left px-4 py-2 font-medium hidden md:table-cell">Lang</th>
              <th className="text-left px-4 py-2 font-medium hidden md:table-cell">Volume</th>
              <th className="px-4 py-2" />
            </tr>
          </thead>
          <tbody className="divide-y divide-md-border">
            {loading && (
              <tr><td colSpan={5} className="px-4 py-4 text-center text-md-text-secondary text-sm">Loading…</td></tr>
            )}
            {!loading && items.length === 0 && (
              <tr><td colSpan={5} className="px-4 py-4 text-center text-md-text-secondary text-sm">No chapters yet.</td></tr>
            )}
            {items.map((item) =>
              editingId === item.id ? (
                <tr key={item.id} className="bg-md-surface/50">
                  <td className="px-4 py-2">
                    <input value={editForm.chapterNumber} onChange={(e) => setEditForm((f) => ({ ...f, chapterNumber: e.target.value }))} className={inlineCls} />
                  </td>
                  <td className="px-4 py-2 hidden sm:table-cell">
                    <input value={editForm.title} onChange={(e) => setEditForm((f) => ({ ...f, title: e.target.value }))} placeholder="Title" className={inlineCls} />
                  </td>
                  <td className="px-4 py-2 hidden md:table-cell">
                    <input value={editForm.language} onChange={(e) => setEditForm((f) => ({ ...f, language: e.target.value }))} className={inlineCls + " w-16"} />
                  </td>
                  <td className="px-4 py-2 hidden md:table-cell">
                    <input value={editForm.volume} onChange={(e) => setEditForm((f) => ({ ...f, volume: e.target.value }))} placeholder="Vol" className={inlineCls + " w-16"} />
                  </td>
                  <td className="px-4 py-2 text-right space-x-2">
                    {editError && <span className="text-xs text-red-400 mr-2">{editError}</span>}
                    <button onClick={() => handleSave(item.id)} disabled={editLoading || !editForm.chapterNumber.trim()} className="px-3 py-1 rounded text-xs font-medium bg-md-accent text-white hover:bg-md-accent/90 disabled:opacity-50 transition-colors">Save</button>
                    <button onClick={() => setEditingId(null)} className="px-3 py-1 rounded text-xs font-medium text-md-text-secondary border border-md-border hover:bg-md-surface-hover transition-colors">Cancel</button>
                  </td>
                </tr>
              ) : (
                <tr key={item.id} className="hover:bg-md-surface/50 transition-colors">
                  <td className="px-4 py-2 text-md-text-primary font-medium">Ch. {item.chapterNumber}</td>
                  <td className="px-4 py-2 text-md-text-secondary hidden sm:table-cell">{item.title ?? "—"}</td>
                  <td className="px-4 py-2 text-md-text-secondary hidden md:table-cell">{item.language}</td>
                  <td className="px-4 py-2 text-md-text-secondary hidden md:table-cell">{item.volume ?? "—"}</td>
                  <td className="px-4 py-2 text-right space-x-2">
                    <button onClick={() => startEdit(item)} className="px-3 py-1 rounded text-xs font-medium text-md-text-secondary border border-md-border hover:bg-md-surface-hover transition-colors">Edit</button>
                    <button onClick={() => handleDelete(item.id)} className="px-3 py-1 rounded text-xs font-medium text-red-400 border border-red-400/30 hover:bg-red-400/10 transition-colors">Delete</button>
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
