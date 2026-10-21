<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Convention de nommage des derivees d'image, partagee par le job qui les produit
 * (Phase 2) et par la ressource API qui les expose. Un seul endroit ou la changer.
 *
 * Un media stocke `path_web` = prefixe sans extension ; les fichiers reels sont
 * `<prefixe>-<largeur>.<format>`, derivee haute definition comprise.
 */
final class Derivatives
{
    /**
     * 3000 px sert le zoom sur le grain du papier, qui est le sujet meme d'une aquarelle.
     *
     * @var array<int, int>
     */
    public const WIDTHS = [400, 800, 1600, 3000];

    /**
     * @var array<int, string>
     */
    public const FORMATS = ['avif', 'webp'];

    /**
     * 82 plutot que le defaut de 70 : le grain du papier est le sujet meme d'une
     * aquarelle, et c'est la premiere chose que la compression lisse. Partagee par le
     * job et par `works:derivees-hd`, qui doivent produire les memes fichiers.
     */
    public const QUALITY = 82;

    /**
     * Vrai si `$path` est une derivee de `$prefix` (`<prefixe>-<largeur>.<format>`),
     * quelle que soit la largeur : une HD produite sous une ancienne regle compte aussi.
     */
    public static function derivesFrom(string $path, string $prefix): bool
    {
        return preg_match('/^'.preg_quote($prefix, '/').'-\d+\.('.implode('|', self::FORMATS).')$/', $path) === 1;
    }

    /**
     * Vrai si le nom suit le motif des derivees. Un original n'y repond jamais : son nom
     * vient de `hashName()` (40 caracteres alphanumeriques, sans tiret).
     */
    public static function isDerivative(string $path): bool
    {
        return preg_match('/-\d+\.('.implode('|', self::FORMATS).')$/', $path) === 1;
    }

    /**
     * Attribut `srcset` complet pour un format donne, largeurs reelles comprises.
     * Les largeurs superieures a l'original sont omises : agrandir une aquarelle
     * numerisee n'ajoute aucun detail et alourdit la page.
     */
    public static function srcset(string $disk, string $pathPrefix, string $format, int $originalWidth): string
    {
        $entries = [];

        foreach (self::WIDTHS as $width) {
            if ($width > $originalWidth) {
                continue;
            }

            $url = Storage::disk($disk)->url("{$pathPrefix}-{$width}.{$format}");
            $entries[] = "{$url} {$width}w";
        }

        return implode(', ', $entries);
    }

    /**
     * Largeur de la derivee haute definition, servie au seul zoom de la fiche : celle de
     * l'original, plafonnee a la plus grande largeur standard. Jamais au-dela de
     * l'original (aucun agrandissement), et au-dessus de 3000 px elle se confond avec la
     * derivee 3000 deja produite. Sans elle, un original entre deux paliers (1400 px
     * par exemple) ne publie que 800 px, que le zoom x6 etire jusqu'au flou.
     */
    public static function hdWidth(int $originalWidth): int
    {
        return min($originalWidth, max(self::WIDTHS));
    }

    /**
     * Toutes les largeurs a produire pour un original : les paliers standards qu'il peut
     * nourrir, plus la derivee haute definition.
     *
     * @return array<int, int>
     */
    public static function widthsFor(int $originalWidth): array
    {
        $widths = array_filter(self::WIDTHS, fn (int $width): bool => $width <= $originalWidth);
        $widths[] = self::hdWidth($originalWidth);

        return array_values(array_unique($widths));
    }

    /**
     * Derivee haute definition par format, en URL simple (pas de `srcset`) : le front ne
     * la demande qu'une fois l'image agrandie, jamais au chargement.
     *
     * @return array{width: int, avif: string, webp: string}
     */
    public static function hd(string $disk, string $pathPrefix, int $originalWidth): array
    {
        $width = self::hdWidth($originalWidth);
        $hd = ['width' => $width];

        foreach (self::FORMATS as $format) {
            $hd[$format] = Storage::disk($disk)->url("{$pathPrefix}-{$width}.{$format}");
        }

        return $hd;
    }
}
