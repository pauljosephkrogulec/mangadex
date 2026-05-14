import { Suspense } from "react";
import ChapterReaderContent from "./ChapterReaderContent";

function ReaderFallback() {
  return (
    <div className="min-h-screen bg-black flex items-center justify-center">
      <div className="w-8 h-8 border-2 border-md-accent border-t-transparent rounded-full animate-spin" />
    </div>
  );
}

export default async function ChapterPage({
  params,
}: {
  params: Promise<{ chapterId: string }>;
}) {
  const { chapterId } = await params;

  return (
    <Suspense fallback={<ReaderFallback />}>
      <ChapterReaderContent chapterId={chapterId} />
    </Suspense>
  );
}
