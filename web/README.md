# Mangadex — front (Next.js 16)

App Router, TypeScript strict, Turbopack, Tailwind CSS v4, shadcn/ui.

## Prérequis

Node.js 26 (LTS), voir `.nvmrc`.

## Commandes

| Commande               | Rôle                                                 |
| ---------------------- | ---------------------------------------------------- |
| `npm ci`               | Installer les dépendances                            |
| `npm run dev`          | Serveur de dev (Turbopack) sur http://localhost:3000 |
| `npm run lint`         | ESLint                                               |
| `npm run typecheck`    | Génère les types de routes puis `tsc --noEmit`       |
| `npm test`             | Tests Vitest (Testing Library, jsdom)                |
| `npm run format:check` | Vérifie le formatage Prettier                        |
| `npm run build`        | Build de production                                  |

## Ajouter un composant shadcn/ui

```bash
npx shadcn@latest add <composant>
```
