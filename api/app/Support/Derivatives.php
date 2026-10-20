<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Convention de nommage des derivees d'image, partagee par le job qui les produit
 * (Phase 2) et par la ressource API qui les expose. Un seul endroit ou la changer.
 *
 * Un media stocke `path_web` = prefixe sans extension ; les fichiers reels sont
 * `<prefixe>-<largeur>.<format>`.
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
}
