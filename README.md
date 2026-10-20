# La veritable Tatasasa

Portfolio en ligne d'une aquarelliste, plus l'application mobile qui lui sert d'atelier numerique : elle photographie ses oeuvres, les redresse et les corrige sur son telephone, les publie et les partage.

La navigation du site public ne passe pas par un menu mais par un nuancier de pigments reels (Outremer, Garance, Terre de Sienne). On filtre les oeuvres par dominante chromatique.

## Structure

| Dossier | Role | Stack |
|---|---|---|
| `api/` | API JSON, medias, palettes, files d'attente | Laravel 13.24, PHP 8.3, MySQL 8.4 |
| `web/` | Portfolio public | Vue 3, Vite 8, TypeScript 6 |
| `studio/` | Application atelier iOS/Android | Ionic Vue 8, Capacitor 8, Vue 3 |
| `shared/` | Contrat de types partage par les deux fronts | TypeScript |

`web/` n'utilise pas Ionic : la mise en page editoriale du nuancier demande un controle total du rendu. Ionic sert la ou il apporte du natif — camera, gestes, hors-ligne, feuille de partage.

## Prerequis

- PHP 8.3 avec les extensions `gd` (WebP et AVIF actifs), `pdo_mysql`, `mbstring`, `fileinfo`, `exif`
- Composer 2
- Node 24, npm 11
- MySQL 8.4
- Ionic CLI (`npm i -g @ionic/cli`) pour `studio/`

`imagick` n'est pas requis : le driver image est `gd` (`IMAGE_DRIVER=gd`).

## Installation

```bash
# API
cd api
composer install
cp .env.example .env && php artisan key:generate
# creer la base au prealable :
#   CREATE DATABASE tatasasa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
php artisan migrate --seed
php artisan storage:link

# Portfolio public
cd ../web && npm install

# Application atelier
cd ../studio && npm install
```

## Demarrage

```bash
cd api    && php artisan serve          # http://127.0.0.1:8000
cd api    && php artisan queue:work     # necessaire des la Phase 2 (derivees, palettes)
cd web    && npm run dev
cd studio && ionic serve
```

## Tests

```bash
cd api    && php artisan test      # PHPUnit — 51 tests
cd web    && npm run test:unit     # Vitest — contrat API, 8 tests
cd studio && npm run test:unit     # Vitest — 37 tests
cd studio && npm run test:e2e      # Cypress (spec du starter uniquement pour l'instant)
```

## Variables d'environnement

Cote `api/.env`, au-dela des valeurs par defaut de Laravel :

| Variable | Valeur | Role |
|---|---|---|
| `DB_CONNECTION` | `mysql` | |
| `DB_DATABASE` | `tatasasa` | base en `utf8mb4_unicode_ci` |
| `IMAGE_DRIVER` | `gd` | `imagick` absent du PHP Laragon |
| `QUEUE_CONNECTION` | `database` | derivation d'images et extraction de palette |
| `APP_LOCALE` | `fr` | |

Cote fronts :
- `web/` n'a pas de variable : il appelle `/api`, relaye vers `php artisan serve` par le proxy de Vite en developpement ; en production, le front et l'API partagent l'hote.
- `studio/.env` porte `VITE_API_URL` (voir `studio/.env.example`), injectee au build.

## Etat

| Phase | Contenu | Etat |
|---|---|---|
| 0 | Socle : trois applications, types partages, base | fait |
| 1 | Domaine, API, authentification Sanctum | fait |
| 2 | Derivation d'images et extraction de palette | fait |
| 3 | Portfolio public (nuancier, carnet) | fait |
| 4 | Application atelier (capture, redressement, filtres) | fait |
| 5 | Partage reseaux | fait : feuille de partage native ; auto-publication Instagram a venir |
