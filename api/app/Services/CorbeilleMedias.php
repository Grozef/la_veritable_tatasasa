<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Work;
use App\Models\WorkMedia;
use App\Support\Deplacement;
use App\Support\Derivatives;
use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Seul chemin de suppression d'un media ou d'une oeuvre. L'original de la numerisation
 * (brute comprise) n'est jamais detruit : il part dans la corbeille privee
 * (`corbeille/<chemin>` du disque `originaux`) ; seules les derivees publiques sont
 * effacees, et seulement une fois le DELETE valide.
 *
 * Tout ou rien : si une mise a la corbeille ou le DELETE echoue, les originaux deja
 * deplaces reviennent a leur place (ordre inverse) avant que l'exception remonte.
 */
final class CorbeilleMedias
{
    /**
     * Leve par `WorkMedia` et `Work` sur un `delete()` qui ne passe pas par ce service :
     * sans lui, l'original resterait orphelin ou la cascade de schema l'oublierait.
     */
    private static bool $enCours = false;

    public static function exigerPassage(): void
    {
        if (! self::$enCours) {
            throw new LogicException('Suppression directe interdite : passer par '.self::class.' (l\'original doit aller a la corbeille).');
        }
    }

    public function supprimerMedia(WorkMedia $media): void
    {
        $this->supprimer(new Collection([$media]), fn (): ?bool => $media->delete());
    }

    /** Les lignes `work_media` partent en cascade de schema, sans evenement Eloquent. */
    public function supprimerOeuvre(Work $work): void
    {
        $this->supprimer($work->media()->get(), fn (): ?bool => $work->delete());
    }

    /**
     * @param  Collection<int, WorkMedia>  $medias
     * @param  Closure(): ?bool  $delete
     */
    private function supprimer(Collection $medias, Closure $delete): void
    {
        $deplaces = [];

        try {
            foreach ($medias as $media) {
                if (($deplacement = $this->mettreALaCorbeille($media)) !== null) {
                    $deplaces[] = $deplacement;
                }
            }

            self::$enCours = true;

            DB::transaction(function () use ($delete): void {
                if ($delete() === false) {
                    throw new RuntimeException('Suppression annulee par un evenement de modele.');
                }
            });
        } catch (Throwable $e) {
            foreach (array_reverse($deplaces) as [$disque, $chemin, $vers]) {
                $this->restaurer($disque, $chemin, $vers);
            }

            throw $e;
        } finally {
            self::$enCours = false;
        }

        foreach ($medias as $media) {
            $this->supprimerDerivees($media);
        }
    }

    /**
     * Cherche l'original sur son disque, puis au meme chemin sur `originaux` (commande
     * de deplacement interrompue entre fichier et base). Introuvable : avertissement et
     * suppression autorisee, la ligne ne pointe deja plus sur rien.
     *
     * @return array{0: string, 1: string, 2: string}|null disque, chemin, chemin en corbeille
     */
    private function mettreALaCorbeille(WorkMedia $media): ?array
    {
        $chemin = $media->path_original;
        $disque = $media->disk_original;

        if (! Storage::disk($disque)->exists($chemin)) {
            if ($disque === WorkMedia::DISQUE_ORIGINAUX || ! Storage::disk(WorkMedia::DISQUE_ORIGINAUX)->exists($chemin)) {
                Log::warning("Media {$media->id} supprime sans original : {$disque}:{$chemin} introuvable.");

                return null;
            }

            $disque = WorkMedia::DISQUE_ORIGINAUX;
        }

        $corbeille = Storage::disk(WorkMedia::DISQUE_ORIGINAUX);
        $vers = $this->cheminLibre($corbeille, $chemin);

        $erreur = $disque === WorkMedia::DISQUE_ORIGINAUX
            ? ($corbeille->move($chemin, $vers) ? null : 'deplacement interne impossible.')
            : Deplacement::entreDisques(Storage::disk($disque), $chemin, $corbeille, $vers);

        if ($erreur !== null) {
            throw new RuntimeException("Media {$media->id} : corbeille impossible pour {$disque}:{$chemin} : {$erreur}");
        }

        return [$disque, $chemin, $vers];
    }

    /** Compensation : un echec ici laisse le fichier en corbeille, jamais perdu. */
    private function restaurer(string $disque, string $chemin, string $vers): void
    {
        $corbeille = Storage::disk(WorkMedia::DISQUE_ORIGINAUX);

        $erreur = $disque === WorkMedia::DISQUE_ORIGINAUX
            ? ($corbeille->move($vers, $chemin) ? null : 'deplacement interne impossible.')
            : Deplacement::entreDisques($corbeille, $vers, Storage::disk($disque), $chemin);

        if ($erreur !== null) {
            Log::critical("Corbeille : retour impossible de originaux:{$vers} vers {$disque}:{$chemin} : {$erreur}");
        }
    }

    /** `corbeille/<chemin>`, ou `<nom>-2[.ext]`, `-3`... : jamais d'ecrasement. */
    private function cheminLibre(FilesystemAdapter $corbeille, string $chemin): string
    {
        $info = pathinfo($chemin);
        $base = ($info['dirname'] !== '.' ? $info['dirname'].'/' : '').$info['filename'];
        $extension = isset($info['extension']) ? '.'.$info['extension'] : '';
        $vers = WorkMedia::PREFIXE_CORBEILLE.$chemin;

        for ($n = 2; $corbeille->exists($vers); $n++) {
            $vers = WorkMedia::PREFIXE_CORBEILLE."{$base}-{$n}{$extension}";
        }

        return $vers;
    }

    /**
     * Toutes les derivees publiques (paliers et HD, AVIF et WebP). Le prefixe est tire de
     * l'original comme dans `ProcessWorkMedia` : une derivee ecrite par un job encore en
     * cours, `path_web` encore nul, part aussi.
     */
    private function supprimerDerivees(WorkMedia $media): void
    {
        $prefixe = Str::beforeLast($media->path_original, '.');
        $publics = Storage::disk($media->disk);

        $publics->delete(array_values(array_filter(
            $publics->files(dirname($prefixe)),
            fn (string $fichier): bool => Derivatives::derivesFrom($fichier, $prefixe),
        )));
    }
}
