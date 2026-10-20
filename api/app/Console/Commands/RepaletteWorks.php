<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MediaRole;
use App\Models\Work;
use App\Services\PaletteExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Rejoue l'extraction de palette. Necessaire des que le nuancier bouge — corriger un hex
 * de pigment ou en ajouter un change le resultat du mapping sur TOUTES les oeuvres deja
 * en base, qu'aucun nouvel upload ne viendrait recalculer.
 *
 * Ne retouche pas les derivees d'image : elles ne dependent pas du nuancier.
 */
class RepaletteWorks extends Command
{
    protected $signature = 'works:repalette {work? : slug ou ULID ; toutes les oeuvres si absent}';

    protected $description = 'Rejoue l\'extraction de palette sur le media principal des oeuvres';

    public function handle(PaletteExtractor $extractor): int
    {
        $argument = $this->argument('work');

        $works = Work::query()
            ->when($argument, fn ($query) => $query->where('slug', $argument)->orWhere('ulid', $argument))
            ->with('media')
            ->get();

        if ($works->isEmpty()) {
            $this->error($argument ? "Aucune oeuvre pour « {$argument} »." : 'Aucune oeuvre en base.');

            return self::FAILURE;
        }

        foreach ($works as $work) {
            $media = $work->media->firstWhere('role', MediaRole::Principal);

            if ($media === null) {
                $this->warn("{$work->title} : pas de media principal, ignoree.");

                continue;
            }

            $work->syncPalette($extractor->extract(
                Storage::disk($media->disk)->path($media->path_original)
            ));

            $pigments = $work->load('pigments')->pigments;

            $this->line($pigments->isEmpty()
                ? "{$work->title} : aucun pigment retenu."
                : "{$work->title} : ".$pigments->pluck('name')->implode(', '));
        }

        return self::SUCCESS;
    }
}
