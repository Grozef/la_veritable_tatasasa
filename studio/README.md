# studio — application atelier

L'application mobile de l'artiste. Elle remplace le back-office web : photographier une aquarelle, la redresser, corriger le blanc du papier, publier sur le portfolio, partager.

## Prerequis

Node 24, Ionic CLI (`npm i -g @ionic/cli`). Xcode ou Android Studio pour les builds natifs.

## Installation

```bash
npm install
ionic serve                      # navigateur, sans plugins natifs
ionic capacitor add ios          # ou android
ionic capacitor run ios -l       # sur appareil, rechargement a chaud
```

## Commandes

```bash
npm run dev
npm run build
npm run test:unit         # Vitest, une passe
npm run test:unit:watch
npm run test:e2e          # Cypress
npm run lint
```

Le starter Ionic livrait `test:unit` en mode surveillance, ce qui bloque toute execution automatisee. Le script lance desormais `vitest run` ; la surveillance est passee sur `test:unit:watch`.

## Plugins Capacitor

| Plugin | Usage |
|---|---|
| `@capacitor/camera` | capture de l'oeuvre |
| `@capacitor/filesystem` | fichiers intermediaires du traitement |
| `@capacitor/preferences` | jeton Sanctum, file d'attente d'envoi |
| `@capacitor/share` | feuille de partage native |

## Flux prevu (Phase 4)

Capture, redressement, balance des blancs, filtres, publication.

Le redressement de perspective n'est pas un confort : une aquarelle photographiee au telephone n'est jamais parfaitement de face, et une feuille en trapeze ruine le portfolio. La balance des blancs — pipette sur une zone de reserve pour ramener ce point au blanc du papier — fait davantage pour la justesse du rendu que tous les filtres.

Les filtres sont non destructifs : les reglages sont stockes, l'original part intact vers le serveur.

## Partage

La publication directe sur Instagram par API exige un compte Professionnel, une Page Facebook liee, une application Meta et une revue de 2 a 4 semaines. La version 1 passe donc par la feuille de partage native, qui fonctionne sans aucune de ces conditions.

## Alias

| Alias | Cible |
|---|---|
| `@` | `src/` |
| `@shared` | `../shared/` |

## Ecart de versions

`studio` tourne sur Vite 5, TypeScript 5.9, vue-tsc 2.1 — heritage du starter Ionic — la ou `web` est sur Vite 8, TypeScript 6, vue-tsc 3.3. Les deux fonctionnent. L'alignement n'a pas ete fait pour ne pas melanger une montee de version avec la mise en place du projet.
