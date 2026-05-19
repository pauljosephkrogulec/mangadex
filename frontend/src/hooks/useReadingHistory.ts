"use client";

import { useCallback, useSyncExternalStore } from "react";

export interface ReadingHistoryEntry {
  mangaId: string;
  mangaTitle: string;
  chapterId: string;
  chapterNumber: string;
  readAt: string;
}

const STORAGE_KEY = "mangadex_reading_history";
const MAX_ENTRIES = 200;

function getStoredHistory(): ReadingHistoryEntry[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    return JSON.parse(raw) as ReadingHistoryEntry[];
  } catch {
    return [];
  }
}

function setStoredHistory(history: ReadingHistoryEntry[]): void {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(history));
  } catch {
    // localStorage full or unavailable
  }
}

function subscribe(callback: () => void): () => void {
  window.addEventListener("storage", callback);
  return () => window.removeEventListener("storage", callback);
}

let cachedSnapshot: ReadingHistoryEntry[] | undefined;

function getSnapshot(): ReadingHistoryEntry[] {
  const latest = getStoredHistory();
  if (
    cachedSnapshot &&
    cachedSnapshot.length === latest.length &&
    cachedSnapshot.every((entry, i) => {
      const l = latest[i];
      return (
        entry.mangaId === l.mangaId &&
        entry.mangaTitle === l.mangaTitle &&
        entry.chapterId === l.chapterId &&
        entry.chapterNumber === l.chapterNumber &&
        entry.readAt === l.readAt
      );
    })
  ) {
    return cachedSnapshot;
  }
  cachedSnapshot = latest;
  return latest;
}

export function useReadingHistory() {
  const history = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);

  const markAsRead = useCallback(
    (entry: Omit<ReadingHistoryEntry, "readAt">) => {
      const current = getStoredHistory();
      const existingIndex = current.findIndex(
        (e) => e.chapterId === entry.chapterId,
      );

      const newEntry: ReadingHistoryEntry = {
        ...entry,
        readAt: new Date().toISOString(),
      };

      if (existingIndex >= 0) {
        current[existingIndex] = newEntry;
      } else {
        current.unshift(newEntry);
      }

      if (current.length > MAX_ENTRIES) {
        current.length = MAX_ENTRIES;
      }

      setStoredHistory(current);
      window.dispatchEvent(new Event("storage"));
    },
    [],
  );

  const clearHistory = useCallback(() => {
    setStoredHistory([]);
    window.dispatchEvent(new Event("storage"));
  }, []);

  return { history, markAsRead, clearHistory };
}
