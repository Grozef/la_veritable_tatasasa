<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MediaRole;
use App\Models\WorkMedia;
use App\Services\PaletteExtractor;
use App\Support\Derivatives;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Derivees web et nuancier d'un media. Hors du cycle HTTP : reencoder une numerisation
 * en 3000 px vers deux formats occupe le CPU plusieurs secondes.
 *
 * `lqip_hex` n'est PAS refait ici : le controleur le lit deja au televersement, ou il
 * a l'image en main. Le job n'y revient pas.
 */
class ProcessWorkMedia implements ShouldQueue
{
    use Queueable;

    public function __construct(public WorkMedia $media) {}

    public function handle(PaletteExtractor $extractor): void
    {
        $prefix = Str::beforeLast($this->media->path_original, '.');

        $this->deriveTo($prefix);

        $this->media->update(['path_web' => $prefix]);

        // Le nuancier vient de la vue d'ensemble de l'oeuvre. Un detail cadre sur un
        // coin, un verso ou une mise en situation donneraient des proportions fausses.
        if ($this->media->role === MediaRole::Principal) {
            $this->syncPigments($extractor);
        }
    }

    /**
     * Les largeurs superieures a l'original sont ignorees, comme dans
     * `Derivatives::srcset` : agrandir n'ajoute aucun detail et le `srcset` publie
     * pointerait vers des fichiers absents. La derivee haute definition du zoom
     * (`Derivatives::hdWidth`) s'y ajoute.
     */
    private function deriveTo(string $prefix): void
    {
        // `orient()` : sans redressement EXIF, une photo prise en portrait sort couchee.
        // L'original se lit sur son disque prive ; les derivees s'ecrivent sur `disk`, public.
        $source = Image::fromStorage($this->media->path_original, $this->media->disk_original)->orient();

        foreach (Derivatives::widthsFor($this->media->width) as $width) {
            $scaled = $source->scale(width: $width);

            foreach (Derivatives::FORMATS as $format) {
                $encoded = $format === 'avif' ? $scaled->toAvif() : $scaled->toWebp();

                $encoded->quality(Derivatives::QUALITY)->storeAs("{$prefix}-{$width}.{$format}", null, $this->media->disk);
            }
        }
    }

    private function syncPigments(PaletteExtractor $extractor): void
    {
        $this->media->work->syncPalette($extractor->extract(
            Storage::disk($this->media->disk_original)->path($this->media->path_original)
        ));
    }
}
