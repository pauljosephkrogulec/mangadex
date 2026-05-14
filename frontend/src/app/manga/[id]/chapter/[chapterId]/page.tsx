import { Suspense } from "react";
import ChapterReaderContent from "@/app/(reader)/chapter/[chapterId]/ChapterReaderContent";

function ReaderFallback() {
  return (
    <div className="min-h-screen bg-black flex items-center justify-center">
      <div className="w-8 h-8 border-2 border-md-accent border-t-transparent rounded-full animate-spin" />
    </div>
  );
}

export default async function MangaChapterPage({
  params,
}: {
  params: Promise<{ id: string; chapterId: string }>;
}) {
  const { id: mangaId, chapterId } = await params;

  return (
    <Suspense fallback={<ReaderFallback />}>
      <ChapterReaderContent mangaId={mangaId} chapterId={chapterId} />
    </Suspense>
  );
}
