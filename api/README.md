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

## Deploiement : originaux prives

Depuis la migration `2026_10_21_120000_add_disk_original_to_work_media_table`, originaux et brutes vivent sur le disque `originaux` (`storage/app/originaux`), jamais servi ; `public` ne porte plus que les derivees. Ordre a suivre, sans en sauter :

1. Deployer le code. Creer `storage/app/originaux` dans le stockage PERSISTANT (le meme que `storage/app/public`, partage entre les versions), hors de toute racine web et sans lien symbolique vers `public/` : `storage:link` ne doit exposer que `storage/app/public`.
2. `php artisan migrate --force` : ajoute `disk_original`, recopie `disk` dans les lignes existantes (rien ne bouge sur disque).
3. `php artisan queue:restart` : un worker lance avant le deploiement garde l'ancien code en memoire et lirait l'original au mauvais endroit.
4. `php artisan works:originaux-prives --dry-run --orphelins` : relire la liste des originaux a deplacer, les introuvables (avertissements) et les orphelins (fichiers publics sous `works/` sans ligne).
5. `php artisan works:originaux-prives` puis, si la liste des orphelins a ete validee, `php artisan works:originaux-prives --orphelins` : les orphelins sont DEPLACES sous `orphelins/` du disque prive, jamais supprimes. Un echec (ecriture, checksum different ou illisible) s'arrete en FAILURE sans rien supprimer.
6. Seconde execution de controle : `php artisan works:originaux-prives --dry-run --orphelins` doit afficher `0 original(aux) a deplacer` et `0 orphelin(s) public(s) a deplacer`.
7. `php artisan works:derivees-hd --dry-run`, puis `php artisan works:derivees-hd` : cree les derivees haute definition absentes, en lisant l'original sur le disque prive.
8. Controle HTTP : l'URL publique d'un ancien original (`/storage/works/<ulid>/<nom>.<ext>`) repond 404 ; une derivee (`/storage/works/<ulid>/<nom>-400.webp`) repond 200 ; `/api/works` est inchange.

Rollback : `down()` de la migration REFUSE (exception) tant qu'un media a son original hors du disque de ses derivees. Avant `migrate:rollback`, avec le code de cette version encore en place et l'app atelier arretee (un televersement irait de nouveau sur `originaux`) :

```bash
php artisan works:originaux-prives --retour --dry-run
php artisan works:originaux-prives --retour   # fichiers ramenes sur public, puis disk_original = disk
php artisan migrate:rollback --step=1
```

Les orphelins deplaces sous `orphelins/` restent prives au retour : les rendre publics les exposerait de nouveau. Un media signale introuvable par `--retour` bloque le rollback jusqu'a ce que son fichier soit retrouve ou sa ligne supprimee.

Suppression : supprimer un media (ou une oeuvre, dont les medias partent en cascade) ne detruit jamais l'original, brute comprise. Il est DEPLACE dans la corbeille privee, `corbeille/<chemin relatif>` du disque `originaux` (suffixe `-2`, `-3`... si le chemin y existe deja, jamais d'ecrasement) ; seules ses derivees publiques (paliers et HD, AVIF et WebP) sont effacees, et seulement apres le DELETE reussi. Tout ou rien : si une mise a la corbeille ou le DELETE echoue, les originaux deja deplaces reviennent a leur place et l'erreur remonte (un retour impossible est journalise en `critical`, le fichier reste alors en corbeille). Un original introuvable (ni sur son disque, ni au meme chemin sur `originaux`) n'empeche pas la suppression : avertissement dans le journal. Le seul chemin de suppression est `App\Services\CorbeilleMedias` ; un `delete()` direct sur `Work` ou `WorkMedia` leve une `LogicException`. Recuperation a la main (recopier le fichier, recreer le media) ; la corbeille ne se vide pas seule et se purge manuellement.

## Variables d'environnement

| Variable | Valeur | Role |
|---|---|---|
| `DB_CONNECTION` | `mysql` | |
| `DB_DATABASE` | `tatasasa` | |
| `IMAGE_DRIVER` | `gd` | `imagick` absent du PHP Laragon |
| `QUEUE_CONNECTION` | `database` | |
| `APP_LOCALE` | `fr` | |

Les tests tournent sur SQLite en memoire (`phpunit.xml`), pas sur MySQL. `RefreshDatabase` migre dans le meme processus, donc migration et tests partagent toujours le meme environnement.
