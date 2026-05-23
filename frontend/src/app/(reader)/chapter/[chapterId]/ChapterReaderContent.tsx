"use client";

import { useEffect, useState, useCallback } from "react";
import { useRouter } from "next/navigation";
import Image from "next/image";
import { chapterApi, mangaApi, handleResponse } from "@/lib/api";
import { useReadingHistory } from "@/hooks/useReadingHistory";
import type { Chapter } from "@/lib/types";
import ReaderSidebar from "@/components/ReaderSidebar";
import ReaderControls from "@/components/ReaderControls";

interface ChapterReaderContentProps {
  mangaId?: string;
  chapterId: string;
}

export default function ChapterReaderContent({
  mangaId: propMangaId,
  chapterId,
}: ChapterReaderContentProps) {
  const router = useRouter();
  const { markAsRead } = useReadingHistory();
  const [chapter, setChapter] = useState<Chapter | null>(null);
  const [chapters, setChapters] = useState<Chapter[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [retryCount, setRetryCount] = useState(0);
  const [currentPage, setCurrentPage] = useState(1);
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [sidebarPinned, setSidebarPinned] = useState(false);
  const [mangaTitle, setMangaTitle] = useState("");

  useEffect(() => {
    let cancelled = false;

    async function fetchChapter() {
      setLoading(true);
      setError(null);

      const result = await handleResponse(chapterApi.get(chapterId));

      if (cancelled) return;

      if (!result.success) {
        setError(result.error);
      } else if (result.data) {
        setChapter(result.data);
        setCurrentPage(1);
        const mangaId = result.data.manga?.id ?? propMangaId ?? "";
        if (mangaId) {
          markAsRead({
            mangaId,
            mangaTitle: mangaTitle,
            chapterId: result.data.id,
            chapterNumber: result.data.chapterNumber,
          });
        }
      }
      setLoading(false);
    }

    fetchChapter();
    return () => {
      cancelled = true;
    };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [chapterId, retryCount]);

  useEffect(() => {
    const mangaId = chapter?.manga.id ?? propMangaId;
    if (!mangaId) return;
    const currentMangaId = mangaId;
    let cancelled = false;

    async function fetchMangaTitle() {
      const result = await handleResponse(mangaApi.get(currentMangaId));
      if (cancelled) return;
      if (result.success) {
        const title = result.data.title;
        setMangaTitle(title);
        markAsRead({
          mangaId: currentMangaId,
          mangaTitle: title,
          chapterId: chapterId,
          chapterNumber: chapter?.chapterNumber ?? "",
        });
      }
    }

    fetchMangaTitle();
    return () => {
      cancelled = true;
    };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [chapter?.manga.id, propMangaId]);

  useEffect(() => {
    if (!chapter && !propMangaId) return;

    const mangaId = propMangaId || chapter?.manga.id;
    if (!mangaId) return;
    const currentMangaId = mangaId;
    let cancelled = false;

    async function fetchFeed() {
      const result = await handleResponse(
        mangaApi.feed(currentMangaId, {
          itemsPerPage: 500,
          "order[chapterNumber]": "asc",
        }),
      );

      if (cancelled) return;

      if (result.success) {
        setChapters(result.data.member);
      }
    }

    fetchFeed();
    return () => {
      cancelled = true;
    };
  }, [propMangaId, chapter, chapter?.manga.id]);

  const currentIndex = chapters.findIndex((ch) => ch.id === chapterId);
  const prevChapter =
    currentIndex > 0 ? chapters[currentIndex - 1] : null;
  const nextChapter =
    currentIndex >= 0 && currentIndex < chapters.length - 1
      ? chapters[currentIndex + 1]
      : null;
  const mangaId = propMangaId || chapter?.manga.id || "";

  const totalPages = chapter?.pageUrls.length ?? 0;

  const handlePrevPage = useCallback(() => {
    if (currentPage > 1) {
      setCurrentPage((p) => p - 1);
    } else if (prevChapter) {
      router.push(`/manga/${mangaId}/chapter/${prevChapter.id}`);
    }
  }, [currentPage, prevChapter, mangaId, router]);

  const handleNextPage = useCallback(() => {
    if (currentPage < totalPages) {
      setCurrentPage((p) => p + 1);
    } else if (nextChapter) {
      router.push(`/manga/${mangaId}/chapter/${nextChapter.id}`);
    }
  }, [currentPage, totalPages, nextChapter, mangaId, router]);

  const handlePrevChapter = useCallback(() => {
    router.push(`/manga/${mangaId}/chapter/${prevChapter!.id}`);
  }, [prevChapter, mangaId, router]);

  const handleNextChapter = useCallback(() => {
    router.push(`/manga/${mangaId}/chapter/${nextChapter!.id}`);
  }, [nextChapter, mangaId, router]);

  const handlePageChange = useCallback((page: number) => {
    setCurrentPage(page);
  }, []);

  const handleChapterChange = useCallback(
    (id: string) => {
      router.push(`/manga/${mangaId}/chapter/${id}`);
    },
    [mangaId, router],
  );

  useEffect(() => {
    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === "ArrowLeft") {
        e.preventDefault();
        handlePrevPage();
      } else if (e.key === "ArrowRight") {
        e.preventDefault();
        handleNextPage();
      } else if (e.key === "m" || e.key === "M") {
        e.preventDefault();
        setSidebarOpen((v) => !v);
      }
    }

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [handlePrevPage, handleNextPage]);

  useEffect(() => {
    if (!chapter) return;
    document.title = `${chapter.chapterNumber}${chapter.title ? ` - ${chapter.title}` : ""} - MangaDex`;
  }, [chapter]);

  if (loading) {
    return (
      <div className="min-h-screen bg-black flex items-center justify-center">
        <div className="w-8 h-8 border-2 border-md-accent border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  if (error && !chapter) {
    return (
      <div className="min-h-screen bg-black flex items-center justify-center px-4">
        <div className="flex flex-col items-center gap-4 text-center max-w-md">
          <svg
            className="w-12 h-12 text-md-text-secondary"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"
            />
          </svg>
          <p className="text-md-text-secondary text-sm">{error}</p>
          <button
            onClick={() => setRetryCount((c) => c + 1)}
            className="px-5 py-2 rounded-lg bg-md-accent text-white text-sm font-medium hover:bg-md-accent/90 transition-colors"
          >
            Retry
          </button>
        </div>
      </div>
    );
  }

  if (!chapter) return null;

  return (
    <div className="min-h-screen bg-black flex flex-col">
      <ReaderSidebar
        open={sidebarOpen}
        pinned={sidebarPinned}
        mangaId={mangaId}
        onClose={() => {
          setSidebarOpen(false);
          setSidebarPinned(false);
        }}
        onPinToggle={() => setSidebarPinned((v) => !v)}
        onPageChange={handlePageChange}
        onChapterChange={handleChapterChange}
        chapters={chapters}
        currentChapterId={chapterId}
        chapterNumber={chapter.chapterNumber}
        chapterTitle={chapter.title}
        volume={chapter.volume}
        language={chapter.language}
        scanlationGroup={chapter.scanlationGroup?.name ?? null}
        currentPage={currentPage}
        totalPages={totalPages}
      />

      <button
        onClick={() => setSidebarOpen((v) => !v)}
        className="fixed top-4 right-4 z-30 p-2 rounded-lg bg-black/60 backdrop-blur-sm border border-white/10 text-white/60 hover:text-white hover:bg-white/10 transition-colors"
        aria-label="Toggle reader menu"
      >
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>

      <div
        className="flex-1 flex items-start justify-center"
        onClick={() => setSidebarOpen((v) => !v)}
      >
        <Image
          src={chapter.pageUrls[currentPage - 1]}
          alt={`Page ${currentPage}`}
          width={0}
          height={0}
          sizes="100vw"
          className="w-full max-w-4xl h-auto block select-none"
          draggable={false}
          unoptimized
        />
        {currentPage < totalPages && (
          <Image
            src={chapter.pageUrls[currentPage]}
            alt=""
            aria-hidden
            width={0}
            height={0}
            sizes="100vw"
            className="hidden"
            unoptimized
          />
        )}
      </div>

      <ReaderControls
        currentPage={currentPage}
        totalPages={totalPages}
        onPrevPage={handlePrevPage}
        onNextPage={handleNextPage}
        onPrevChapter={prevChapter ? handlePrevChapter : null}
        onNextChapter={nextChapter ? handleNextChapter : null}
        chapterTitle={chapter.title}
        chapterNumber={chapter.chapterNumber}
      />
    </div>
  );
}
