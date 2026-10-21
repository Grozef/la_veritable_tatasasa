<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\WorkMedia;
use App\Support\Derivatives;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;

/**
 * Rattrapage de la derivee haute definition pour les medias derives avant son
 * introduction. Purement additive : n'ecrit que les fichiers HD absents, ne touche ni
 * aux autres derivees, ni aux originaux, ni a la palette. Relancable sans effet.
 */
class DeriveMissingHd extends Command
{
    protected $signature = 'works:derivees-hd {--dry-run : liste ce qui serait cree sans rien ecrire}';

    protected $description = 'Cree les derivees haute definition absentes des medias publics deja derives';

    public function handle(): int
    {
        $simulation = (bool) $this->option('dry-run');
        $crees = $presents = $sansOriginal = 0;

        $medias = WorkMedia::query()->publics()->whereNotNull('path_web')->get();

        foreach ($medias as $media) {
            $largeur = Derivatives::hdWidth($media->width);
            $publics = Storage::disk($media->disk);

            $absents = array_filter(
                Derivatives::FORMATS,
                fn (string $format): bool => ! $publics->exists("{$media->path_web}-{$largeur}.{$format}"),
            );

            $presents += count(Derivatives::FORMATS) - count($absents);

            if ($absents === []) {
                continue;
            }

            if (! Storage::disk($media->disk_original)->exists($media->path_original)) {
                $sansOriginal++;
                $this->warn("media {$media->id} : original introuvable ({$media->disk_original}), HD non creee.");

                continue;
            }

            // Meme chaine que `ProcessWorkMedia::deriveTo` : redressement EXIF, largeur,
            // qualite. `hdWidth` ne depasse jamais l'original : aucun agrandissement.
            $image = $simulation
                ? null
                : Image::fromStorage($media->path_original, $media->disk_original)->orient()->scale(width: $largeur);

            foreach ($absents as $format) {
                $chemin = "{$media->path_web}-{$largeur}.{$format}";

                if ($image !== null) {
                    $encode = $format === 'avif' ? $image->toAvif() : $image->toWebp();
                    $encode->quality(Derivatives::QUALITY)->storeAs($chemin, null, $media->disk);
                }

                $crees++;
                $this->line(($simulation ? 'a creer ' : 'cree ').$chemin);
            }
        }

        $this->info(sprintf(
            '%s%d fichier(s) %s, %d deja present(s), %d media(s) sans original.',
            $simulation ? '[dry-run] ' : '',
            $crees,
            $simulation ? 'a creer' : 'cree(s)',
            $presents,
            $sansOriginal,
        ));

        return self::SUCCESS;
    }
}
