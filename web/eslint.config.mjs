import vitest from "@vitest/eslint-plugin";
import jestDom from "eslint-plugin-jest-dom";
import playwright from "eslint-plugin-playwright";
import testingLibrary from "eslint-plugin-testing-library";
import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";
import prettier from "eslint-config-prettier/flat";
import tseslint from "typescript-eslint";

/*
 * ESLint (issue #18).
 * - Next.js (core web vitals, a11y via jsx-a11y, règles React/hooks).
 * - typescript-eslint en mode *type-checked* : règles qui s'appuient sur les types
 *   (promesses non attendues, `any` qui se propage, conditions toujours vraies...).
 * - Plugins dédiés aux tests : Vitest + Testing Library + jest-dom (unitaires), Playwright (E2E).
 */
const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,

  // --- TypeScript avec informations de type --------------------------------
  {
    files: ["**/*.{ts,tsx,mts,cts}"],
    extends: [tseslint.configs.strictTypeChecked, tseslint.configs.stylisticTypeChecked],
    languageOptions: {
      parserOptions: {
        // Utilise le tsconfig.json le plus proche de chaque fichier.
        projectService: true,
        tsconfigRootDir: import.meta.dirname,
      },
    },
    rules: {
      // Imports de types explicites (`import type`) : effacés à la compilation, intention claire.
      "@typescript-eslint/consistent-type-imports": ["error", { fixStyle: "inline-type-imports" }],
      // Autorise les nombres et booléens dans les template literals (`${count} chapitres`).
      "@typescript-eslint/restrict-template-expressions": [
        "error",
        { allowNumber: true, allowBoolean: true },
      ],
      // Les handlers async passés en props JSX (onClick={async () => ...}) sont un usage normal de React.
      "@typescript-eslint/no-misused-promises": [
        "error",
        { checksVoidReturn: { attributes: false } },
      ],
    },
  },

  // --- Tests unitaires (Vitest + Testing Library) ---------------------------
  {
    files: ["src/**/*.test.{ts,tsx}"],
    extends: [
      vitest.configs.recommended,
      testingLibrary.configs["flat/react"],
      jestDom.configs["flat/recommended"],
    ],
    rules: {
      // Toujours au moins une assertion par test.
      "vitest/expect-expect": "error",
      "vitest/consistent-test-it": ["error", { fn: "it" }],
      "vitest/no-focused-tests": "error",
    },
  },

  // --- Tests E2E (Playwright) -----------------------------------------------
  {
    files: ["e2e/**/*.ts"],
    extends: [playwright.configs["flat/recommended"]],
  },

  // Désactive les règles de style qui entrent en conflit avec Prettier (doit rester en dernier).
  prettier,
  globalIgnores([".next/**", "out/**", "build/**", "coverage/**", "next-env.d.ts"]),
]);

export default eslintConfig;
