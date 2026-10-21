<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\WorkMedia;
use App\Support\Deplacement;
use App\Support\Derivatives;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deplace les originaux (brutes comprises) televerses avant le disque prive. Le chemin
 * relatif ne change pas : le prefixe des derivees publiques en est tire et doit rester
 * le meme. Seule `disk_original` change en base.
 *
 * Ordre choisi pour qu'une interruption ne laisse jamais un original public oublie :
 * copie, verification, suppression de la copie publique, PUIS mise a jour en base. Une
 * reprise trouve alors la copie privee seule et ne fait que mettre la base a jour.
 *
 * Les originaux orphelins (sous `works/` du disque public, sans ligne qui les reference)
 * sont toujours listes ; `--orphelins` les deplace sous `orphelins/` du disque prive.
 * `--retour` fait le chemin inverse pour les lignes, prealable obligatoire au rollback
 * de la migration `disk_original` (son `down()` refuse sinon).
 */
class MoveOriginalsToPrivate extends Command
{
    protected $signature = 'works:originaux-prives
        {--dry-run : liste ce qui serait deplace sans rien toucher}
        {--orphelins : deplace aussi les originaux publics sans ligne vers orphelins/ du disque prive}
        {--retour : ramene les originaux sur le disque de leurs derivees, avant un rollback de la migration}';

    protected $description = 'Deplace les originaux et les brutes du disque public vers le disque prive';

    private const DISQUE_PUBLIC = 'public';

    private const PREFIXE_ORPHELINS = 'orphelins/';

    public function handle(): int
    {
        $simulation = (bool) $this->option('dry-run');

        if ($this->option('retour')) {
            if ($this->option('orphelins')) {
                $this->error('--retour et --orphelins sont incompatibles : les orphelins restent prives.');

                return self::FAILURE;
            }

            return $this->retour($simulation);
        }

        $cible = Storage::disk(WorkMedia::DISQUE_ORIGINAUX);
        $deplaces = $introuvables = 0;

        $dejaPrives = WorkMedia::query()->where('disk_original', WorkMedia::DISQUE_ORIGINAUX)->count();
        $medias = WorkMedia::query()->where('disk_original', '!=', WorkMedia::DISQUE_ORIGINAUX)->get();

        foreach ($medias as $media) {
            $source = Storage::disk($media->disk_original);
            $chemin = $media->path_original;
            $etiquette = "media {$media->id} ({$media->role->value}) {$media->disk_original}:{$chemin}";

            if (! $source->exists($chemin) && ! $cible->exists($chemin)) {
                $introuvables++;
                $this->warn("{$etiquette} : introuvable, base inchangee.");

                continue;
            }

            if ($simulation) {
                $deplaces++;
                $this->line("a deplacer {$etiquette}");

                continue;
            }

            if ($source->exists($chemin) && ($erreur = Deplacement::entreDisques($source, $chemin, $cible, $chemin)) !== null) {
                $this->error("{$etiquette} : {$erreur}");

                return self::FAILURE;
            }

            $media->update(['disk_original' => WorkMedia::DISQUE_ORIGINAUX]);
            $deplaces++;
            $this->line("deplace {$etiquette}");
        }

        $this->info(sprintf(
            '%s%d original(aux) %s, %d deja prive(s), %d introuvable(s).',
            $simulation ? '[dry-run] ' : '',
            $deplaces,
            $simulation ? 'a deplacer' : 'deplace(s)',
            $dejaPrives,
            $introuvables,
        ));

        return $this->orphelins($simulation, (bool) $this->option('orphelins'));
    }

    /**
     * Un orphelin est un fichier public sous `works/` qui n'a pas le nom d'une derivee et
     * qu'aucune ligne ne reference : un original dont la ligne a disparu (suppression
     * d'avant la purge des fichiers). Il est deplace, jamais supprime.
     */
    private function orphelins(bool $simulation, bool $deplacer): int
    {
        $publics = Storage::disk(self::DISQUE_PUBLIC);
        $cible = Storage::disk(WorkMedia::DISQUE_ORIGINAUX);
        $references = array_flip(WorkMedia::query()->pluck('path_original')->all());

        $orphelins = array_values(array_filter(
            $publics->allFiles('works'),
            fn (string $fichier): bool => ! Derivatives::isDerivative($fichier) && ! isset($references[$fichier]),
        ));

        $deplaces = 0;

        foreach ($orphelins as $chemin) {
            $etiquette = self::DISQUE_PUBLIC.":{$chemin} ({$publics->size($chemin)} octets)";

            if (! $deplacer || $simulation) {
                $this->line(($deplacer ? 'orphelin a deplacer ' : 'orphelin ').$etiquette);

                continue;
            }

            $vers = self::PREFIXE_ORPHELINS.$chemin;

            if (($erreur = Deplacement::entreDisques($publics, $chemin, $cible, $vers)) !== null) {
                $this->error("orphelin {$etiquette} : {$erreur}");

                return self::FAILURE;
            }

            $deplaces++;
            $this->line("orphelin deplace {$etiquette} -> ".WorkMedia::DISQUE_ORIGINAUX.":{$vers}");
        }

        $this->info(match (true) {
            $simulation && $deplacer => sprintf('[dry-run] %d orphelin(s) public(s) a deplacer.', count($orphelins)),
            $deplacer => sprintf('%d orphelin(s) public(s) deplace(s) sous %s.', $deplaces, self::PREFIXE_ORPHELINS),
            default => sprintf(
                '%s%d orphelin(s) public(s) laisse(s) en place (--orphelins pour les deplacer).',
                $simulation ? '[dry-run] ' : '',
                count($orphelins),
            ),
        });

        return self::SUCCESS;
    }

    /**
     * Ramene chaque original prive sur le disque de ses derivees, comme avant la
     * migration. Meme ordre que l'aller : copie, verification, suppression, base.
     */
    private function retour(bool $simulation): int
    {
        $source = Storage::disk(WorkMedia::DISQUE_ORIGINAUX);
        $ramenes = $introuvables = 0;

        foreach (WorkMedia::query()->where('disk_original', WorkMedia::DISQUE_ORIGINAUX)->get() as $media) {
            $cible = Storage::disk($media->disk);
            $chemin = $media->path_original;
            $etiquette = "media {$media->id} ({$media->role->value}) ".WorkMedia::DISQUE_ORIGINAUX.":{$chemin} -> {$media->disk}";

            if (! $source->exists($chemin) && ! $cible->exists($chemin)) {
                $introuvables++;
                $this->warn("{$etiquette} : introuvable, base inchangee.");

                continue;
            }

            if ($simulation) {
                $ramenes++;
                $this->line("a ramener {$etiquette}");

                continue;
            }

            if ($source->exists($chemin) && ($erreur = Deplacement::entreDisques($source, $chemin, $cible, $chemin)) !== null) {
                $this->error("{$etiquette} : {$erreur}");

                return self::FAILURE;
            }

            $media->update(['disk_original' => $media->disk]);
            $ramenes++;
            $this->line("ramene {$etiquette}");
        }

        $this->info(sprintf(
            '%s%d original(aux) %s, %d introuvable(s).',
            $simulation ? '[dry-run] ' : '',
            $ramenes,
            $simulation ? 'a ramener' : 'ramene(s)',
            $introuvables,
        ));

        return self::SUCCESS;
    }
}
