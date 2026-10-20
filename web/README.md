# web — portfolio public

Le carnet d'atelier consultable en ligne. Navigation par nuancier : une bande de pastilles de pigments filtre les oeuvres par dominante chromatique.

Pas d'Ionic ici, volontairement : la mise en page editoriale (grain du papier, marges, notes en marge) demande un controle total du rendu que les composants Ionic contrarieraient.

## Prerequis

Node 24, npm 11. L'API doit tourner pour le developpement reel (`cd ../api && php artisan serve`).

## Installation

```bash
npm install
npm run dev       # http://localhost:5173
```

## Commandes

```bash
npm run dev
npm run build           # vue-tsc -b puis vite build
npm run test:unit       # Vitest, une passe
npm run test:unit:watch
```

## Alias

| Alias | Cible |
|---|---|
| `@` | `src/` |
| `@shared` | `../shared/` |

Definis une seule fois dans `vite.config.ts` ; `vitest.config.ts` les reprend par fusion. Ils sont doubles cote TypeScript dans `tsconfig.app.json` — les deux doivent rester alignes, sinon les tests passent sur un module que l'application ne charge jamais.

## Contrat API

`src/api/types.ts` reexporte `shared/types.ts`. C'est le point d'entree unique du contrat.

`src/api/contract.spec.ts` verifie ce contrat contre `src/api/__fixtures__/works.response.json`, qui est une reponse REELLE de `GET /api/works` capturee depuis le serveur.

Quand l'API change, RECAPTURER la fixture, ne jamais l'editer a la main :

```bash
curl -H "Accept: application/json" http://127.0.0.1:8000/api/works > src/api/__fixtures__/works.response.json
```

Limite connue : la fixture est un instantane. Elle ne detecte pas seule une derive de l'API — c'est le test `test_response_shape_matches_the_shared_contract` cote Laravel qui garde la forme a la source. Les deux se completent.

## Environnement de test

`node` par defaut. Demarrer jsdom coutait 53 secondes pour des tests qui ne touchent pas au DOM. Les specs de composants activeront jsdom fichier par fichier :

```ts
// @vitest-environment jsdom
```

## A savoir

Vite 8 avertit sur `__dirname` et sur les imports de configuration sans extension. Les deux configurations utilisent donc `import.meta.dirname` et importent `./vite.config.ts` avec son extension.
