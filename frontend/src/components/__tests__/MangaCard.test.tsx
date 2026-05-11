import { describe, it, expect } from "vitest";
import { render, screen, fireEvent } from "@testing-library/react";
import MangaCard from "../MangaCard";
import type { Manga } from "@/lib/types";

const baseManga: Manga = {
  "@context": "/api/contexts/Manga",
  "@id": "/api/mangas/a1b2c3d4",
  "@type": "Manga",
  id: "a1b2c3d4",
  title: "Berserk",
  createdAt: "2024-01-01T00:00:00+00:00",
  status: "ongoing",
  year: 1989,
  contentRating: "erotica",
  demographic: "seinen",
  altTitles: null,
  description: null,
};

function renderCard(manga: Manga) {
  return render(<MangaCard manga={manga} />);
}

describe("MangaCard", () => {
  it("renders the manga title", () => {
    renderCard(baseManga);
    expect(screen.getByText("Berserk")).toBeInTheDocument();
  });

  it("renders the cover image when primary cover exists", () => {
    const manga: Manga = {
      ...baseManga,
      coverArts: [
        { id: "1", imagePath: "/covers/berserk-vol1.jpg", volume: "1", isPrimary: true, createdAt: "2024-01-01T00:00:00+00:00", "@context": "/api/contexts/CoverArt", "@id": "/api/cover_arts/1", "@type": "CoverArt" },
      ],
    };
    renderCard(manga);
    const img = screen.getByAltText("Berserk") as HTMLImageElement;
    expect(img).toBeInTheDocument();
    expect(img.src).toContain("/uploads/covers/berserk-vol1.jpg");
  });

  it("falls back to the first cover when no primary cover is set", () => {
    const manga: Manga = {
      ...baseManga,
      coverArts: [
        { id: "2", imagePath: "/covers/berserk-vol2.jpg", volume: "2", isPrimary: false, createdAt: "2024-01-01T00:00:00+00:00", "@context": "/api/contexts/CoverArt", "@id": "/api/cover_arts/2", "@type": "CoverArt" },
      ],
    };
    renderCard(manga);
    const img = screen.getByAltText("Berserk") as HTMLImageElement;
    expect(img.src).toContain("/uploads/covers/berserk-vol2.jpg");
  });

  it("shows a placeholder icon when no cover arts exist", () => {
    renderCard(baseManga);
    expect(screen.queryByRole("img")).not.toBeInTheDocument();
    const svg = document.querySelector("svg");
    expect(svg).toBeInTheDocument();
  });

  it("renders the status badge with correct text", () => {
    renderCard(baseManga);
    expect(screen.getByText("ongoing")).toBeInTheDocument();
  });

  it("links to the manga detail page", () => {
    renderCard(baseManga);
    const link = screen.getByRole("link");
    expect(link).toHaveAttribute("href", "/manga/a1b2c3d4");
  });

  it.each([
    ["ongoing", "ongoing"],
    ["completed", "completed"],
    ["hiatus", "hiatus"],
    ["cancelled", "cancelled"],
  ])("renders the %s status badge", (status) => {
    const manga: Manga = { ...baseManga, status: status as Manga["status"] };
    renderCard(manga);
    expect(screen.getByText(status)).toBeInTheDocument();
  });

  it("shows placeholder icon when cover image fails to load", () => {
    const manga: Manga = {
      ...baseManga,
      coverArts: [
        { id: "1", imagePath: "/covers/berserk-vol1.jpg", volume: "1", isPrimary: true, createdAt: "2024-01-01T00:00:00+00:00", "@context": "/api/contexts/CoverArt", "@id": "/api/cover_arts/1", "@type": "CoverArt" },
      ],
    };
    renderCard(manga);

    // Image should initially be present
    const img = screen.getByAltText("Berserk");
    expect(img).toBeInTheDocument();

    // Simulate an image load error
    fireEvent.error(img);

    // Image should be replaced by the placeholder SVG
    expect(screen.queryByRole("img")).not.toBeInTheDocument();
    const svg = document.querySelector("svg");
    expect(svg).toBeInTheDocument();
  });

  it("falls back to default style for unknown status", () => {
    const manga: Manga = { ...baseManga, status: "unknown_status" as Manga["status"] };
    renderCard(manga);

    // The unknown status should still render (falls through to default style)
    const badge = screen.getByText("unknown_status");
    expect(badge.className).toContain("bg-gray-600/60");
  });

  it("renders demographic label for unknown demographic", () => {
    const manga: Manga = { ...baseManga, demographic: "unknown_demo" as Manga["demographic"] };
    renderCard(manga);

    // Falls through to display the raw value since it's not in DEMOGRAPHIC_LABELS
    expect(screen.getByText("unknown_demo")).toBeInTheDocument();
  });
});
