import { defineConfig, devices } from "@playwright/test";

const isCI = !!process.env.CI;

/**
 * Tests E2E : ils tournent contre la stack Docker Compose complète (proxy → web + php),
 * démarrée en amont (`docker compose up`). Playwright ne lance donc pas de serveur lui-même.
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
  testDir: "./e2e",
  fullyParallel: true,
  // Échoue en CI si un `test.only` a été oublié.
  forbidOnly: isCI,
  retries: isCI ? 2 : 0,
  // Un seul worker en CI ; en local, Playwright choisit selon les cœurs disponibles (clé absente).
  // `exactOptionalPropertyTypes` interdit `workers: undefined` : on n'ajoute la clé qu'en CI.
  ...(isCI && { workers: 1 }),
  reporter: isCI
    ? [["github"], ["html", { open: "never" }]]
    : [["list"], ["html", { open: "on-failure" }]],
  use: {
    // URL du reverse proxy Caddy (surchargeable, ex. HTTP_PORT différent en local).
    baseURL: process.env.E2E_BASE_URL ?? "http://localhost",
    // Artefacts conservés uniquement en cas d'échec (uploadés par la CI).
    trace: "retain-on-failure",
    video: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],
});
