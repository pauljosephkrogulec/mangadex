import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Sortie autonome (server.js + dépendances minimales) utilisée par l'image Docker de prod.
  output: "standalone",
  cacheComponents: true,
  partialPrefetching: true,
  turbopack: {
    // L'app est autonome dans web/ : évite que Next prenne le package-lock.json racine (outillage Git) pour le workspace.
    root: __dirname,
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default nextConfig;
