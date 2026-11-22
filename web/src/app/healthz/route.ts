/**
 * Sonde de santé du serveur Next.js, utilisée par le HEALTHCHECK de l'image Docker de prod.
 * Ne dépend ni de l'API ni du rendu d'une page : elle répond tant que le serveur Node tourne.
 */
export function GET(): Response {
  return Response.json({ status: "ok" }, { headers: { "Cache-Control": "no-store" } });
}
