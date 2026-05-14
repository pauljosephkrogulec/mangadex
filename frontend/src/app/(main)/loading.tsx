export default function RootLoading() {
  return (
    <div className="flex items-center justify-center min-h-[60vh]">
      <div className="flex flex-col items-center gap-4">
        <div className="w-8 h-8 border-2 border-md-accent border-t-transparent rounded-full animate-spin" />
        <p className="text-sm text-md-text-secondary">Loading...</p>
      </div>
    </div>
  );
}
