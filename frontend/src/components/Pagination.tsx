"use client";

interface PaginationProps {
  currentPage: number;
  totalPages: number;
  onPageChange: (page: number) => void;
}

export default function Pagination({
  currentPage,
  totalPages,
  onPageChange,
}: PaginationProps) {
  // Don't render for single page or empty
  if (totalPages <= 1 || !Number.isFinite(totalPages)) {
    return null;
  }

  /**
   * Generates a compact list of pages and ellipsis markers.
   * Example: [1, "...", 4, 5, 6, "...", 10]
   */
  function getPageNumbers(): (number | "ellipsis")[] {
    const siblingCount = 1;
    const totalPageNumbers = siblingCount * 2 + 5; // siblings + first + last + current + 2 ellipsis slots

    // If total pages fit within the limit, show them all
    if (totalPageNumbers >= totalPages) {
      return Array.from({ length: totalPages }, (_, i) => i + 1);
    }

    const leftSibling = Math.max(currentPage - siblingCount, 1);
    const rightSibling = Math.min(currentPage + siblingCount, totalPages);
    const showLeftEllipsis = leftSibling > 2;
    const showRightEllipsis = rightSibling < totalPages - 1;

    if (!showLeftEllipsis && showRightEllipsis) {
      // More pages on the right
      const leftCount = 3 + 2 * siblingCount;
      const pages: (number | "ellipsis")[] = Array.from(
        { length: leftCount },
        (_, i) => i + 1,
      );
      pages.push("ellipsis", totalPages);
      return pages;
    }

    if (showLeftEllipsis && !showRightEllipsis) {
      // More pages on the left
      const rightCount = 3 + 2 * siblingCount;
      const pages: (number | "ellipsis")[] = [1, "ellipsis"];
      const start = totalPages - rightCount + 1;
      for (let i = start; i <= totalPages; i++) {
        pages.push(i);
      }
      return pages;
    }

    // Ellipsis on both sides
    const pages: (number | "ellipsis")[] = [1, "ellipsis"];
    for (let i = leftSibling; i <= rightSibling; i++) {
      pages.push(i);
    }
    pages.push("ellipsis", totalPages);
    return pages;
  }

  const pageNumbers = getPageNumbers();

  return (
    <nav className="flex items-center justify-center gap-1" aria-label="Pagination">
      {/* Previous button */}
      <button
        onClick={() => onPageChange(currentPage - 1)}
        disabled={currentPage <= 1}
        className="px-3 py-2 rounded-lg text-sm font-medium text-md-text-secondary hover:text-md-text-primary hover:bg-md-surface-hover disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
        aria-label="Previous page"
      >
        &larr; Prev
      </button>

      {/* Page numbers */}
      {pageNumbers.map((item, index) =>
        item === "ellipsis" ? (
          <span
            key={`ellipsis-${index}`}
            className="px-2 py-2 text-sm text-md-text-secondary select-none"
            aria-hidden="true"
          >
            &hellip;
          </span>
        ) : (
          <button
            key={item}
            onClick={() => onPageChange(item)}
            disabled={item === currentPage}
            className={`min-w-[36px] px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
              item === currentPage
                ? "bg-md-accent text-white cursor-default"
                : "text-md-text-secondary hover:text-md-text-primary hover:bg-md-surface-hover"
            }`}
            aria-label={`Page ${item}`}
            aria-current={item === currentPage ? "page" : undefined}
          >
            {item}
          </button>
        ),
      )}

      {/* Next button */}
      <button
        onClick={() => onPageChange(currentPage + 1)}
        disabled={currentPage >= totalPages}
        className="px-3 py-2 rounded-lg text-sm font-medium text-md-text-secondary hover:text-md-text-primary hover:bg-md-surface-hover disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
        aria-label="Next page"
      >
        Next &rarr;
      </button>
    </nav>
  );
}
