import { expect, test } from "@playwright/test";

test.describe("Page d'accueil", () => {
  test("affiche le titre et l'appel à l'action", async ({ page }) => {
    await page.goto("/");

    await expect(page).toHaveTitle(/Mangadex/);
    await expect(page.getByRole("heading", { level: 1, name: "Mangadex" })).toBeVisible();
    await expect(page.getByRole("button", { name: "Explorer le catalogue" })).toBeVisible();
  });
});

test.describe("Reverse proxy", () => {
  test("route /api vers Symfony", async ({ request }) => {
    const response = await request.get("/api");

    // Aucune route API n'existe encore : Symfony répond 404, mais c'est bien lui qui répond
    // (et pas Next.js), ce qui prouve que le proxy route correctement.
    expect(response.status()).toBe(404);
    expect(response.headers()["x-powered-by"]).toBeUndefined();
    expect(await response.text()).not.toContain("__next");
  });
});
