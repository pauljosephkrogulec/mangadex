import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import Home from "./page";

describe("Home", () => {
  it("affiche le titre de l'application", () => {
    render(<Home />);

    expect(screen.getByRole("heading", { level: 1, name: "Mangadex" })).toBeInTheDocument();
  });

  it("propose un bouton vers le catalogue", () => {
    render(<Home />);

    expect(screen.getByRole("button", { name: "Explorer le catalogue" })).toBeEnabled();
  });
});
