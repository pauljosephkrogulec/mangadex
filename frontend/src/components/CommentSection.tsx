"use client";

import { useEffect, useState } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { commentApi, handleResponse } from "@/lib/api";
import type { Comment } from "@/lib/types";

function formatRelativeDate(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime();
  const minutes = Math.floor(diff / 60_000);
  if (minutes < 1) return "just now";
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  if (days < 30) return `${days}d ago`;
  return new Intl.DateTimeFormat("en-US", { month: "short", day: "numeric", year: "numeric" }).format(
    new Date(dateStr),
  );
}

interface CommentSectionProps {
  mangaId: string;
}

export default function CommentSection({ mangaId }: CommentSectionProps) {
  const { user } = useAuth();

  const [comments, setComments] = useState<Comment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [content, setContent] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [deletingIds, setDeletingIds] = useState<Set<string>>(new Set());
  const [refreshKey, setRefreshKey] = useState(0);

  useEffect(() => {
    let cancelled = false;
    async function doFetch() {
      setLoading(true);
      setError(null);
      const result = await handleResponse(commentApi.list(mangaId));
      if (cancelled) return;
      if (result.success) {
        setComments(result.data.member);
      } else {
        setError(result.error);
      }
      setLoading(false);
    }
    void doFetch();
    return () => { cancelled = true; };
  }, [mangaId, refreshKey]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    /* v8 ignore next */
    if (!content.trim()) return;
    setSubmitting(true);
    setSubmitError(null);
    const result = await handleResponse(
      commentApi.create({ content: content.trim(), manga: `/api/mangas/${mangaId}` }),
    );
    if (result.success) {
      setComments((prev) => [result.data, ...prev]);
      setContent("");
    } else {
      setSubmitError(result.error);
    }
    setSubmitting(false);
  };

  const handleDelete = async (id: string) => {
    setDeletingIds((prev) => new Set(prev).add(id));
    const result = await handleResponse(commentApi.delete(id));
    if (result.success) {
      setComments((prev) => prev.filter((c) => c.id !== id));
    }
    setDeletingIds((prev) => {
      const next = new Set(prev);
      next.delete(id);
      return next;
    });
  };

  return (
    <section className="mt-10">
      <h2 className="text-lg font-semibold text-md-text-primary mb-4">Comments</h2>

      {user && (
        <form onSubmit={handleSubmit} className="mb-6 space-y-2">
          <textarea
            value={content}
            onChange={(e) => setContent(e.target.value)}
            placeholder="Write a comment…"
            maxLength={2000}
            rows={3}
            className="w-full bg-md-surface border border-md-border text-md-text-primary text-sm rounded-lg px-3 py-2 resize-none focus:outline-none focus:ring-1 focus:ring-md-accent placeholder:text-md-text-secondary"
          />
          {submitError && <p className="text-xs text-red-400">{submitError}</p>}
          <div className="flex justify-end">
            <button
              type="submit"
              disabled={submitting || !content.trim()}
              className="px-4 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {submitting ? "Posting…" : "Post"}
            </button>
          </div>
        </form>
      )}

      {loading ? (
        <ul className="space-y-3 animate-pulse">
          {Array.from({ length: 3 }).map((_, i) => (
            <li key={i} className="h-16 bg-md-surface rounded-xl border border-md-border" />
          ))}
        </ul>
      ) : error ? (
        <div className="flex flex-col items-center py-8 text-center">
          <p className="text-sm text-md-text-secondary mb-3">{error}</p>
          <button
            onClick={() => setRefreshKey(k => k + 1)}
            className="text-sm text-md-accent hover:text-md-accent/80 transition-colors"
          >
            Try again
          </button>
        </div>
      ) : comments.length === 0 ? (
        <p className="text-sm text-md-text-secondary py-6 text-center">
          No comments yet.{user ? " Be the first!" : ""}
        </p>
      ) : (
        <ul className="space-y-3">
          {comments.map((comment) => (
            <li
              key={comment.id}
              className="bg-md-surface border border-md-border rounded-xl px-4 py-3"
            >
              <div className="flex items-center justify-between gap-2 mb-1">
                <div className="flex items-center gap-2 min-w-0">
                  <span className="text-sm font-medium text-md-text-primary truncate">
                    {comment.user.username}
                  </span>
                  <span className="text-xs text-md-text-secondary shrink-0">
                    {formatRelativeDate(comment.createdAt)}
                  </span>
                </div>
                {user && (user.id === comment.user.id) && (
                  <button
                    onClick={() => handleDelete(comment.id)}
                    disabled={deletingIds.has(comment.id)}
                    aria-label="Delete comment"
                    className="shrink-0 p-1 rounded text-md-text-secondary hover:text-red-400 hover:bg-md-surface-hover disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    {deletingIds.has(comment.id) ? (
                      <span className="w-3.5 h-3.5 border border-current border-t-transparent rounded-full animate-spin block" />
                    ) : (
                      <svg className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    )}
                  </button>
                )}
              </div>
              <p className="text-sm text-md-text-primary whitespace-pre-wrap break-words">{comment.content}</p>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
