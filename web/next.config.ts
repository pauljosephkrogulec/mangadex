import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Sortie autonome (server.js + dépendances minimales) utilisée par l'image Docker de prod.
  output: "standalone",
  cacheComponents: true,
  partialPrefetching: true,
  // Staging/prod (Vercel) : `/api/*` est relayé vers l'API Symfony sur Render. Le navigateur reste sur
  // la même origine (pas de CORS, cookies first-party). En local, Caddy route déjà `/api` : pas d'API_URL.
  async rewrites() {
    const apiUrl = process.env.API_URL;
    if (!apiUrl) return [];
    return [{ source: "/api/:path*", destination: `${apiUrl}/api/:path*` }];
  },
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
