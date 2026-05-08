import { describe, it, expect } from "vitest";
import { render } from "@testing-library/react";
import MangaCardSkeleton from "../MangaCardSkeleton";

describe("MangaCardSkeleton", () => {
  it("renders without crashing", () => {
    const { container } = render(<MangaCardSkeleton />);
    expect(container.firstChild).toBeInTheDocument();
  });

  it("has animate-pulse class", () => {
    const { container } = render(<MangaCardSkeleton />);
    expect(container.firstChild).toHaveClass("animate-pulse");
  });

  it("contains a cover area div", () => {
    const { container } = render(<MangaCardSkeleton />);
    const coverArea = container.querySelector(".aspect-\\[3\\/4\\]");
    expect(coverArea).toBeInTheDocument();
  });
});
