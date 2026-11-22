# syntax=docker/dockerfile:1

# Image du front Next.js.
# Stages :
#   deps  : node_modules installés depuis package-lock.json
#   dev   : `next dev`, code monté en volume depuis l'hôte
#   build : `next build` (sortie "standalone")
#   prod  : serveur Node minimal, sans les devDependencies

FROM node:26-alpine AS base
WORKDIR /app
ENV NEXT_TELEMETRY_DISABLED=1

# ---------------------------------------------------------------------------
FROM base AS deps
COPY package.json package-lock.json ./
RUN npm ci

# ---------------------------------------------------------------------------
FROM base AS dev
ENV NODE_ENV=development
# L'utilisateur `node` (UID 1000) doit pouvoir écrire dans /app et .next/.
# .next est créé ici pour que le volume anonyme monté dessus hérite de ce propriétaire
# (sinon Docker le crée en root et `next dev` échoue en EACCES).
RUN mkdir .next && chown node:node /app .next
COPY --from=deps --chown=node:node /app/node_modules ./node_modules
USER node
EXPOSE 3000
# 0.0.0.0 : écoute sur toutes les interfaces, sinon le proxy ne peut pas joindre le conteneur.
CMD ["npm", "run", "dev", "--", "--hostname", "0.0.0.0"]

# ---------------------------------------------------------------------------
FROM base AS build
COPY --from=deps /app/node_modules ./node_modules
COPY . .
RUN npm run build

# ---------------------------------------------------------------------------
FROM base AS prod
ENV NODE_ENV=production \
    HOSTNAME=0.0.0.0 \
    PORT=3000
# npm/npx/corepack ne servent pas à l'exécution (`node server.js`) : on les retire
# pour réduire la surface d'attaque (leurs dépendances embarquées remontent des CVE dans Trivy).
RUN rm -rf /usr/local/lib/node_modules /usr/local/bin/npm /usr/local/bin/npx /usr/local/bin/corepack
COPY --from=build --chown=node:node /app/public ./public
COPY --from=build --chown=node:node /app/.next/standalone ./
COPY --from=build --chown=node:node /app/.next/static ./.next/static
USER node
EXPOSE 3000
# Sonde sur la route /healthz (src/app/healthz/route.ts). npm/curl absents : fetch natif de Node.
HEALTHCHECK --start-period=20s --start-interval=2s --interval=30s --timeout=5s --retries=3 \
    CMD ["node", "-e", "fetch('http://127.0.0.1:3000/healthz').then(r => process.exit(r.ok ? 0 : 1), () => process.exit(1))"]
CMD ["node", "server.js"]
