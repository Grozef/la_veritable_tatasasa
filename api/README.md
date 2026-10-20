# api — back Laravel

API JSON du portfolio : domaine des oeuvres, medias, nuancier de pigments, authentification de l'app atelier.

## Prerequis

PHP 8.3 (`gd` avec WebP et AVIF), Composer 2, MySQL 8.4.

Verifier que GD sait encoder les deux formats avant toute mise en place :

```bash
php -r "print_r(array_intersect_key(gd_info(), array_flip(['WebP Support','AVIF Support'])));"
```

## Installation

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

`--seed` cree le compte artiste et le nuancier de depart (16 pigments courants). Ces teintes sont des approximations d'affichage : elles doivent etre remplacees par les vrais tubes de l'artiste avant mise en ligne.

## Structure

```
app/
  Enums/           WorkStatus, PaperGrain, MediaRole
  Http/
    Controllers/Api/         routes publiques (lecture seule)
    Controllers/Api/Studio/  routes atelier (Sanctum)
    Requests/                validation, une classe par ecriture
    Resources/               contrat JSON, miroir de shared/types.ts
  Models/          Work, Series, Pigment, WorkMedia, SocialPost
  Policies/        WorkPolicy
  Support/         Lab, Derivatives, Slug
```

## Conventions

Modeles ecrits avec les attributs Eloquent de Laravel 13 (`#[Fillable]`, `#[RouteKey]`, `#[Table]`, `#[UsePolicy]`, `#[Scope]`) et non avec les proprietes historiques, pour rester aligne sur le modele `User` du squelette.

Deux pieges rencontres, a connaitre avant de modifier :

- `authorizeResource()` dans un constructeur de controller passe par `$this->middleware()`, retiree des controllers depuis Laravel 11. L'autorisation est posee explicitement dans chaque action. Le trait `AuthorizesRequests` a du etre ajoute au `Controller` de base, absent du squelette.
- Un `default()` de migration s'applique a l'INSERT, pas a l'instance Eloquent. `Work` porte donc un `protected $attributes` qui doit rester le miroir des defauts de ses migrations, faute de quoi une creation sans `paper_grain` renvoie `null` et la ressource casse sur `->value`.

## Modele de donnees

| Table | Role |
|---|---|
| `works` | l'oeuvre. `ulid` est l'identifiant stable expose a l'app atelier, `slug` l'identifiant public |
| `series` | regroupement d'oeuvres |
| `pigments` | le nuancier. `lab_l/a/b` precalcules pour le mapping par deltaE |
| `work_media` | fichiers d'une oeuvre. `path_original` n'est jamais reencode |
| `work_pigment` | part de surface de chaque pigment, `weight` entre 0 et 1 |
| `social_posts` | trace des partages |

Visibilite publique d'une oeuvre : `published_at` non nul ET passe ET `status != privee`. Le statut prime sur la date — une oeuvre privee datee reste invisible.

## Support

`App\Support\Lab` convertit sRGB en CIELAB (D65) et calcule un deltaE CIE76. Les distances de couleur doivent etre perceptuelles : en RGB, un bleu marine est plus proche d'un gris que d'un outremer.

`App\Support\Derivatives` porte la convention de nommage des derivees d'image (`<prefixe>-<largeur>.<format>`, largeurs 400/800/1600/3000, formats AVIF et WebP). Un seul endroit ou la changer, partage par le job de derivation et par la ressource API.

`App\Support\Slug` derive un slug unique d'un titre. « Sans titre » deux fois est le cas courant, pas le cas limite.

## Commandes

```bash
php artisan serve
php artisan queue:work     # requis des la Phase 2
php artisan test
./vendor/bin/pint app      # style, limite a app/ : bootstrap/app.php est genere
```

## Variables d'environnement

| Variable | Valeur | Role |
|---|---|---|
| `DB_CONNECTION` | `mysql` | |
| `DB_DATABASE` | `tatasasa` | |
| `IMAGE_DRIVER` | `gd` | `imagick` absent du PHP Laragon |
| `QUEUE_CONNECTION` | `database` | |
| `APP_LOCALE` | `fr` | |

Les tests tournent sur SQLite en memoire (`phpunit.xml`), pas sur MySQL. `RefreshDatabase` migre dans le meme processus, donc migration et tests partagent toujours le meme environnement.
