import react from "@vitejs/plugin-react";
import { defineConfig } from "vitest/config";

export default defineConfig({
  plugins: [react()],
  resolve: {
    // Résout l'alias `@/*` défini dans tsconfig.json.
    tsconfigPaths: true,
  },
  test: {
    environment: "jsdom",
    setupFiles: ["./vitest.setup.ts"],
    include: ["src/**/*.test.{ts,tsx}"],
    coverage: {
      provider: "v8",
      include: ["src/**/*.{ts,tsx}"],
      exclude: [
        "src/**/*.test.{ts,tsx}",
        // Layout racine (<html>/<body>, polices) : non rendable dans jsdom, couvert par l'E2E Playwright.
        "src/app/layout.tsx",
      ],
      reporter: ["text", "lcov"],
      // `npm run test:coverage` échoue sous ces seuils (CI comprise).
      thresholds: {
        lines: 80,
        functions: 80,
        branches: 80,
        statements: 80,
      },
    },
  },
});
