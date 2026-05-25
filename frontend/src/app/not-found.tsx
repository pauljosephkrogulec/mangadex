import Link from "next/link";

export default function NotFound() {
  return (
    <div className="flex items-center justify-center min-h-[60vh]">
      <div className="flex flex-col items-center gap-4 text-center">
        <h1 className="text-8xl font-bold text-md-accent">404</h1>
        <h2 className="text-xl font-semibold text-md-text-primary">
          Page Not Found
        </h2>
        <p className="text-sm text-md-text-secondary">
          The page you&apos;re looking for doesn&apos;t exist.
        </p>
        <Link
          href="/"
          className="px-4 py-2 bg-md-accent text-white rounded-lg text-sm font-medium hover:opacity-90 transition-opacity"
        >
          Back to Home
        </Link>
      </div>
    </div>
  );
}
